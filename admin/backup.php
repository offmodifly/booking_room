<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/backup.php';
page_guard('backup');

// ดาวน์โหลด
if (($dl = qs('download')) !== '') {
    if (!backup_valid_name($dl)) { flash('error', 'ไม่พบไฟล์'); redirect('admin/backup.php'); }
    log_action('backup', "ดาวน์โหลดไฟล์สำรอง $dl");
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $dl . '"');
    header('Content-Length: ' . filesize(backup_dir() . '/' . $dl));
    readfile(backup_dir() . '/' . $dl);
    exit;
}

if (is_post()) {
    csrf_check();
    @set_time_limit(300);
    $action = input('action');
    try {
        if ($action === 'backup') {
            $name = db_backup('manual');
            log_action('backup', "สำรองฐานข้อมูล $name (" . human_size(filesize(backup_dir() . "/$name")) . ')');
            flash('success', "สำรองข้อมูลสำเร็จ: $name");
        } elseif ($action === 'restore') {
            $src = input('file');
            if ($src !== '') {
                if (!backup_valid_name($src)) throw new RuntimeException('ไม่พบไฟล์สำรอง');
                $sql = file_get_contents(backup_dir() . '/' . $src);
                $label = $src;
            } else {
                $f = $_FILES['sqlfile'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('กรุณาเลือกไฟล์ .sql');
                if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') throw new RuntimeException('รองรับเฉพาะไฟล์ .sql');
                if ($f['size'] > 100 * 1048576) throw new RuntimeException('ไฟล์ใหญ่เกิน 100MB');
                $sql = file_get_contents($f['tmp_name']);
                $label = basename($f['name']);
            }
            if (!str_contains($sql, 'CREATE TABLE')) throw new RuntimeException('ไฟล์นี้ไม่ใช่ไฟล์สำรองฐานข้อมูลที่ถูกต้อง');
            $pre = db_backup('before_restore');
            $n = db_restore($sql);
            log_action('restore', "กู้คืนฐานข้อมูลจาก $label ($n คำสั่ง) · สำรองก่อนกู้คืน: $pre");
            flash('success', "กู้คืนข้อมูลสำเร็จ ($n คำสั่ง) · ระบบสำรองข้อมูลก่อนกู้คืนไว้ที่ $pre");
        } elseif ($action === 'delete') {
            $n = input('file');
            if (!backup_valid_name($n)) throw new RuntimeException('ไม่พบไฟล์');
            unlink(backup_dir() . '/' . $n);
            log_action('backup', "ลบไฟล์สำรอง $n");
            flash('success', 'ลบไฟล์สำรองแล้ว');
        }
    } catch (Throwable $e) {
        flash('error', e($e->getMessage()));
    }
    redirect('admin/backup.php');
}

$files = [];
foreach (glob(backup_dir() . '/*.sql') ?: [] as $f) $files[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
$tables = db_all("SELECT TABLE_NAME name, TABLE_ROWS row_est, DATA_LENGTH + INDEX_LENGTH size FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME", [DB_NAME]);
$dbSize = array_sum(array_column($tables, 'size'));
$counts = [];
foreach ($tables as $t) $counts[$t['name']] = (int)db_val('SELECT COUNT(*) FROM `' . str_replace('`', '', $t['name']) . '`');

$pageTitle = 'สำรอง / กู้คืนข้อมูล';
$activeMenu = 'backup';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-database-gear"></i>Backup &amp; Restore</span><h2>สำรองและกู้คืนฐานข้อมูล</h2><p>ฐานข้อมูล <span class="code-pill"><?= e(DB_NAME) ?></span> · <?= count($tables) ?> ตาราง · <?= human_size((int)$dbSize) ?></p></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-6 reveal">
    <div class="card-x h-100"><div class="card-x-body d-flex gap-3">
      <span class="stat-icon" style="--c:#10b981"><i class="bi bi-database-down"></i></span>
      <div class="flex-grow-1"><h5 class="mb-1">สำรองข้อมูล (Backup)</h5><p class="text-muted small">สร้างไฟล์ .sql ของฐานข้อมูลทั้งหมด (โครงสร้าง + ข้อมูล) เก็บไว้บนเซิร์ฟเวอร์ และดาวน์โหลดเก็บไว้ภายนอกได้</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><button class="btn btn-primary"><i class="bi bi-database-down me-1"></i>สำรองข้อมูลตอนนี้</button></form></div>
    </div></div>
  </div>
  <div class="col-lg-6 reveal" style="--i:1">
    <div class="card-x h-100"><div class="card-x-body d-flex gap-3">
      <span class="stat-icon" style="--c:#e11d48"><i class="bi bi-database-up"></i></span>
      <div class="flex-grow-1"><h5 class="mb-1">กู้คืนข้อมูล (Restore)</h5><p class="text-muted small">อัปโหลดไฟล์ .sql เพื่อแทนที่ข้อมูลปัจจุบัน — ระบบจะสำรองข้อมูลปัจจุบันให้อัตโนมัติก่อนกู้คืน</p>
        <form method="post" enctype="multipart/form-data" class="d-flex gap-2" data-confirm="ยืนยันกู้คืนข้อมูล?" data-confirm-text="ข้อมูลปัจจุบันจะถูกแทนที่ด้วยข้อมูลจากไฟล์ (ระบบสำรองข้อมูลก่อนกู้คืนให้อัตโนมัติ)" data-ok="กู้คืนข้อมูล">
          <?= csrf_field() ?><input type="hidden" name="action" value="restore"><input class="form-control form-control-sm" type="file" name="sqlfile" accept=".sql" required><button class="btn btn-soft text-nowrap" style="--c:#e11d48"><i class="bi bi-upload me-1"></i>กู้คืน</button></form></div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8 reveal" style="--i:2">
    <div class="card-x"><div class="card-x-head"><h5><i class="bi bi-archive"></i>ไฟล์สำรองบนเซิร์ฟเวอร์</h5><span class="small text-muted"><?= count($files) ?> ไฟล์</span></div>
      <div class="table-responsive"><table class="table table-x table-hover"><thead><tr><th>ไฟล์</th><th>ขนาด</th><th>วันที่สร้าง</th><th class="text-end">จัดการ</th></tr></thead><tbody>
        <?php if (!$files): ?><tr><td colspan="4"><div class="empty-mini"><i class="bi bi-archive"></i>ยังไม่มีไฟล์สำรอง</div></td></tr><?php endif; ?>
        <?php foreach ($files as $f): ?>
          <tr><td><i class="bi bi-filetype-sql text-primary me-2"></i><span class="small fw-semibold"><?= e($f['name']) ?></span><?= str_contains($f['name'], 'before_restore') ? ' ' . badge('อัตโนมัติก่อนกู้คืน', '#f59e0b') : '' ?></td>
            <td class="small"><?= human_size($f['size']) ?></td>
            <td class="small"><?= e(thai_date(date('Y-m-d H:i:s', $f['time']), 'datetime')) ?></td>
            <td><div class="table-actions">
              <a class="btn-icon sm" title="ดาวน์โหลด" href="?download=<?= urlencode($f['name']) ?>"><i class="bi bi-download"></i></a>
              <form method="post" data-confirm="กู้คืนจาก <?= e($f['name']) ?> ?" data-confirm-text="ข้อมูลปัจจุบันจะถูกแทนที่ (ระบบสำรองข้อมูลก่อนกู้คืนให้อัตโนมัติ)" data-ok="กู้คืนข้อมูล"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="file" value="<?= e($f['name']) ?>"><button class="btn-icon sm text-warning" title="กู้คืนจากไฟล์นี้"><i class="bi bi-arrow-counterclockwise"></i></button></form>
              <form method="post" data-confirm="ลบไฟล์ <?= e($f['name']) ?> ?" data-ok="ลบไฟล์"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="file" value="<?= e($f['name']) ?>"><button class="btn-icon sm text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            </div></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
  </div>
  <div class="col-xl-4 reveal" style="--i:3">
    <div class="card-x"><div class="card-x-head"><h5><i class="bi bi-table"></i>ตารางในฐานข้อมูล</h5></div>
      <div class="card-x-body pt-2"><ul class="feed">
        <?php foreach ($tables as $t): ?><li><span class="fi" style="--c:#6366f1"><i class="bi bi-table"></i></span><div class="flex-grow-1"><strong><?= e($t['name']) ?></strong><p><?= number_format($counts[$t['name']]) ?> แถว · <?= human_size((int)$t['size']) ?></p></div></li><?php endforeach; ?>
      </ul></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
