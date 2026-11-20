<?php
/** สำรอง / กู้คืนฐานข้อมูลด้วย PHP (ไม่ต้องพึ่ง mysqldump) */

function backup_dir(): string
{
    if (!is_dir(BACKUP_PATH)) mkdir(BACKUP_PATH, 0775, true);
    return BACKUP_PATH;
}
function backup_valid_name(string $n): bool { return (bool)preg_match('/^[A-Za-z0-9_\-]+\.sql$/', $n) && is_file(backup_dir() . '/' . $n); }

function db_backup(string $label = 'manual'): string
{
    $pdo = db();
    $name = 'backup_' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9_]/i', '', $label) . '.sql';
    $fh = fopen(backup_dir() . '/' . $name, 'w');
    fwrite($fh, "-- NTC Meeting Room Booking — Database Backup\n-- Database: " . DB_NAME . "\n-- Created: " . date('Y-m-d H:i:s') . "\n-- By: " . (current_user()['username'] ?? 'system') . "\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET time_zone='+07:00';\n\n");
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as [$t]) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($fh, "-- ---------------------------------------- `$t`\nDROP TABLE IF EXISTS `$t`;\n$create;\n\n");
        $s = $pdo->query("SELECT * FROM `$t`");
        $batch = []; $cols = null;
        while ($row = $s->fetch(PDO::FETCH_ASSOC)) {
            $cols ??= '(`' . implode('`,`', array_keys($row)) . '`)';
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
            if (count($batch) >= 200) { fwrite($fh, "INSERT INTO `$t` $cols VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
        }
        if ($batch) fwrite($fh, "INSERT INTO `$t` $cols VALUES\n" . implode(",\n", $batch) . ";\n");
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
    return $name;
}

/** แยกคำสั่ง SQL โดยไม่ตัดเครื่องหมาย ; ที่อยู่ในสตริง/คอมเมนต์ */
function sql_split(string $sql): array
{
    $out = []; $buf = ''; $len = strlen($sql); $q = null;
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($q) {
            $buf .= $c;
            if ($c === '\\' && $q !== '`') { $buf .= $sql[++$i] ?? ''; continue; }
            if ($c === $q) $q = null;
            continue;
        }
        if (($c === '-' && ($sql[$i + 1] ?? '') === '-' && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\n", "\r"], true)) || $c === '#') { $e = strpos($sql, "\n", $i); $i = $e === false ? $len : $e; continue; }
        if ($c === '/' && ($sql[$i + 1] ?? '') === '*' && ($sql[$i + 2] ?? '') !== '!') { $e = strpos($sql, '*/', $i + 2); $i = $e === false ? $len : $e + 1; continue; }
        if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $buf .= $c; continue; }
        if ($c === ';') { if (trim($buf) !== '') $out[] = trim($buf); $buf = ''; continue; }
        $buf .= $c;
    }
    if (trim($buf) !== '') $out[] = trim($buf);
    return $out;
}

function db_restore(string $sql): int
{
    $pdo = db();
    $stmts = sql_split($sql);
    if (!$stmts) throw new RuntimeException('ไฟล์ไม่มีคำสั่ง SQL');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $n = 0;
    try {
        foreach ($stmts as $st) { $pdo->exec($st); $n++; }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    return $n;
}
