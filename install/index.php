<?php
/**
 * ติดตั้งระบบ — สร้างฐานข้อมูล ตาราง และข้อมูลตัวอย่าง
 * เว็บ: /booking_room/install/   |   CLI: php install/index.php [--demo] [--force]
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', detect_base_url());

$cli  = PHP_SAPI === 'cli';
$lock = __DIR__ . '/install.lock';

function make_demo_signature(string $name): string
{
    $w = 600; $h = 220;
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    $ink = imagecolorallocate($im, 20, 40, 120);
    imagesetthickness($im, 4);
    $seed = crc32($name);
    $f1 = 15 + $seed % 17; $f2 = 70 + $seed % 60; $amp = 28 + $seed % 20;
    $px = 40; $py = 130;
    for ($x = 40; $x < 560; $x += 3) {
        $y = 120 + sin($x / $f1 + $seed % 7) * $amp * sin($x / $f2) + cos($x / (9 + $seed % 5)) * 12;
        imageline($im, $px, (int)$py, $x, (int)$y, $ink);
        $px = $x; $py = $y;
    }
    imageline($im, 90 + $seed % 40, 175, 520, 160 - $seed % 25, $ink);
    ob_start(); imagepng($im); $png = ob_get_clean();
    imagedestroy($im);
    return $png;
}

function run_install(bool $demo, string $adminUser, string $adminPass, bool $force = false): array
{
    $log = [];
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($force) { $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`'); $log[] = 'ลบฐานข้อมูลเดิม'; }
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    $pdo->exec("SET time_zone = '+07:00'");
    $log[] = 'สร้างฐานข้อมูล ' . DB_NAME;
    foreach (require __DIR__ . '/schema.php' as $sql) $pdo->exec($sql);
    $log[] = 'สร้างตาราง ' . count(require __DIR__ . '/schema.php') . ' ตาราง';

    $ins = $pdo->prepare('INSERT IGNORE INTO users (username,password_hash,prefix,first_name,last_name,position,role,email,phone) VALUES (?,?,?,?,?,?,?,?,?)');
    $ins->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), 'นาย', 'ผู้ดูแล', 'ระบบ', 'ผู้ดูแลระบบสารสนเทศ', 'admin', null, null]);
    $log[] = "สร้างผู้ดูแลระบบ: $adminUser";

    if (!$demo) return $log;
    if ((int)$pdo->query('SELECT COUNT(*) FROM buildings')->fetchColumn() > 0) { $log[] = 'มีข้อมูลอยู่แล้ว ข้ามข้อมูลตัวอย่าง'; return $log; }

    $pw = password_hash('password', PASSWORD_DEFAULT);
    $users = [
        ['director', 'นาย', 'สมชาย', 'ใจดี', 'ผู้อำนวยการวิทยาลัยเทคนิคน่าน', 'director'],
        ['deputy', 'นาง', 'สมศรี', 'มีทรัพย์', 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร', 'deputy_director'],
        ['bhead', 'นาย', 'ประเสริฐ', 'ศรีน่าน', 'หัวหน้างานอาคารสถานที่', 'building_head'],
        ['bstaff', 'นางสาว', 'วิไลวรรณ', 'คำแสง', 'เจ้าหน้าที่งานอาคารสถานที่', 'building_staff'],
        ['rstaff1', 'นาย', 'อนุชา', 'ปัญญาดี', 'เจ้าหน้าที่ประจำห้องประชุม', 'room_staff'],
        ['rstaff2', 'นาย', 'ธนากร', 'บุญมา', 'เจ้าหน้าที่ประจำห้องประชุม', 'room_staff'],
        ['teacher1', 'นางสาว', 'กมลชนก', 'อินทร์แก้ว', 'ครู แผนกวิชาเทคโนโลยีสารสนเทศ', 'user'],
        ['teacher2', 'นาย', 'วรวุฒิ', 'ทองคำ', 'ครู แผนกวิชาช่างไฟฟ้ากำลัง', 'user'],
    ];
    foreach ($users as $u) $ins->execute([$u[0], $pw, $u[1], $u[2], $u[3], $u[4], $u[5], $u[0] . '@ntc.ac.th', '054-71' . rand(1000, 9999)]);
    $uid = fn($n) => (int)$pdo->query('SELECT id FROM users WHERE username=' . $pdo->quote($n))->fetchColumn();
    $log[] = 'สร้างผู้ใช้ตัวอย่าง ' . count($users) . ' บัญชี (รหัสผ่าน: password)';

    $b = $pdo->prepare('INSERT INTO buildings (name,code,location,latitude,longitude,floors,width_m,depth_m,color,style,description) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $st = fn(array $s) => json_encode(building_style($s));
    $b->execute(['อาคารอำนวยการ', 'B1', 'ด้านหน้าวิทยาลัย ติดถนนสุมนเทวราช', 18.7867, 100.7765, 4, 42, 16, '#e6dccb',
        $st(['roof' => 'hip', 'corridor' => 'front', 'windows' => 'punched', 'stair' => 'right', 'roofColor' => '#8b3a2e']), 'อาคารสำนักงานผู้อำนวยการ งานบริหารทรัพยากร และห้องประชุมหลัก']);
    $b1 = (int)$pdo->lastInsertId();
    $b->execute(['อาคารเฉลิมพระเกียรติ', 'B2', 'กลางวิทยาลัย ข้างลานกิจกรรม', 18.7860, 100.7772, 3, 34, 20, '#d7e0e8',
        $st(['roof' => 'thai', 'corridor' => 'around', 'windows' => 'band', 'roofColor' => '#7a2e2e', 'trim' => '#fff7e6']), 'อาคารหอประชุมและห้องอบรมสัมมนา']);
    $b2 = (int)$pdo->lastInsertId();
    $b->execute(['อาคารเทคโนโลยีสารสนเทศ', 'B3', 'ฝั่งทิศตะวันออก ใกล้โรงอาหาร', 18.7855, 100.7760, 5, 30, 14, '#efe5d6',
        $st(['shape' => 'L', 'corridor' => 'front', 'ground' => 'open', 'windows' => 'band', 'stair' => 'left', 'roofColor' => '#475569', 'trim' => '#e2e8f0']), 'อาคารเรียนแผนกเทคโนโลยีสารสนเทศและคอมพิวเตอร์']);
    $b3 = (int)$pdo->lastInsertId();
    $log[] = 'สร้างอาคารตัวอย่าง 3 อาคาร';

    $r = $pdo->prepare('INSERT INTO rooms (building_id,name,code,floor,pos_x,pos_y,width_m,depth_m,height_m,capacity,equipment,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $rooms = [
        [$b1, 'ห้องประชุมผู้บริหาร', 'R101', 2, 22, 40, 10, 7, 3, 20, 'จอ LED 75", ระบบประชุมทางไกล, ไมค์ห้องประชุม 20 ชุด', 'ห้องประชุมสำหรับผู้บริหาร', 'available'],
        [$b1, 'ห้องประชุมใหญ่ อาคารอำนวยการ', 'R401', 4, 60, 50, 18, 12, 3.6, 120, 'โปรเจกเตอร์ 2 ตัว, ระบบเสียง, ไมค์ไร้สาย 4 ตัว', 'ห้องประชุมขนาดใหญ่ชั้นบนสุด', 'available'],
        [$b2, 'หอประชุมเฉลิมพระเกียรติ', 'H201', 1, 50, 55, 28, 18, 6, 400, 'เวที, ระบบแสงสี, ระบบเสียง Line Array', 'หอประชุมสำหรับกิจกรรมใหญ่', 'available'],
        [$b2, 'ห้องอบรมสัมมนา 1', 'S301', 3, 25, 35, 12, 9, 3, 60, 'โปรเจกเตอร์, ไวท์บอร์ด, Wi-Fi', '', 'available'],
        [$b3, 'ห้องประชุมแผนก IT', 'IT501', 5, 75, 45, 9, 7, 3, 25, 'Smart Board, คอมพิวเตอร์ 1 ชุด', '', 'available'],
        [$b3, 'ห้องประชุมย่อย 302', 'IT302', 3, 30, 60, 7, 6, 3, 12, 'ทีวี 55", กล้องประชุมออนไลน์', 'อยู่ระหว่างปรับปรุงระบบไฟ', 'unavailable'],
    ];
    $roomIds = [];
    foreach ($rooms as $x) { $r->execute($x); $roomIds[] = (int)$pdo->lastInsertId(); }
    $log[] = 'สร้างห้องประชุมตัวอย่าง ' . count($rooms) . ' ห้อง';

    // bookings across current month — พร้อมห่วงโซ่ลายเซ็น (ผู้ขอ → หัวหน้างานอาคาร → รอง ผอ. → ผอ.)
    $adm = $uid($adminUser); $t1 = $uid('teacher1'); $t2 = $uid('teacher2');
    $staff = [$uid('rstaff1'), $uid('rstaff2')];
    $approvers = [];
    foreach (['building_head' => 'bhead', 'deputy_director' => 'deputy', 'director' => 'director'] as $step => $un) {
        $u = $pdo->query('SELECT * FROM users WHERE username=' . $pdo->quote($un))->fetch(PDO::FETCH_ASSOC);
        $approvers[$step] = ['signer' => signer_of($u), 'png' => make_demo_signature($un)];
    }
    $reqPng = ['นางสาวกมลชนก อินทร์แก้ว' => make_demo_signature('teacher1'), 'นายวรวุฒิ ทองคำ' => make_demo_signature('teacher2')];
    $reqPos = ['นางสาวกมลชนก อินทร์แก้ว' => 'ครู แผนกวิชาเทคโนโลยีสารสนเทศ', 'นายวรวุฒิ ทองคำ' => 'ครู แผนกวิชาช่างไฟฟ้ากำลัง'];
    $titles = ['ประชุมผู้บริหารประจำเดือน', 'ประชุมครูและบุคลากร', 'อบรมการใช้ระบบ RMS', 'ประชุมคณะกรรมการสถานศึกษา', 'ประชุมเตรียมงานประกันคุณภาพ', 'สัมมนาเครือข่ายสถานประกอบการ', 'ประชุมแผนกวิชา', 'ประชุมชี้แจงทวิภาคี', 'อบรม Digital Skill ครู', 'ประชุมงบประมาณประจำปี', 'ปฐมนิเทศนักศึกษาฝึกงาน', 'ประชุมออนไลน์ สอศ.'];
    $depts = ['ฝ่ายบริหารทรัพยากร', 'ฝ่ายวิชาการ', 'แผนกวิชาเทคโนโลยีสารสนเทศ', 'ฝ่ายแผนงานและความร่วมมือ', 'งานประกันคุณภาพ', 'ฝ่ายพัฒนากิจการนักเรียน'];
    $bk = $pdo->prepare('INSERT INTO bookings (room_id,user_id,requester_name,department,title,purpose,attendees,start_datetime,end_datetime,contact_phone,status,approved_by,approved_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $first = new DateTime('first day of this month');
    $days = (int)$first->format('t');
    $today = (int)date('j');
    $statuses = ['approved', 'approved', 'approved', 'signed', 'pending', 'approved', 'head_approved', 'deputy_approved', 'approved', 'rejected', 'approved', 'pending', 'approved', 'cancelled', 'approved', 'signed', 'head_approved', 'approved'];
    $slots = [['08:30', '12:00'], ['09:00', '11:30'], ['13:00', '16:30'], ['13:30', '15:30'], ['10:00', '12:00']];
    $chainFor = ['pending' => 1, 'cancelled' => 1, 'head_approved' => 2, 'deputy_approved' => 3, 'signed' => 4, 'approved' => 4, 'rejected' => 3];
    $now = time();
    $makeBooking = function (array $row, string $st, string $reqName, int $req, int $staffId) use ($pdo, $bk, $adm, $approvers, $reqPng, $reqPos, $chainFor, $now) {
        [$roomId, $dept, $title, $purpose, $att, $start, $end, $phone] = $row;
        $created = min($now - 3600, strtotime(substr($start, 0, 10) . " 09:15:00 -6 days"));
        $bk->execute([$roomId, $req, $reqName, $dept, $title, $purpose, $att, $start, $end, $phone, $st,
            $st === 'approved' ? $adm : null, $st === 'approved' ? date('Y-m-d H:i:s', min($now - 600, $created + 4 * 86400)) : null, $req, date('Y-m-d H:i:s', $created)]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE bookings SET booking_no=? WHERE id=?')->execute(['BK' . date('ym', strtotime($start)) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]);
        $b = $pdo->query("SELECT * FROM bookings WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        signature_add($b, 'requester', ['user_id' => $req, 'name' => $reqName, 'position' => $reqPos[$reqName]], 'sign', null, $reqPng[$reqName], date('Y-m-d H:i:s', $created));
        $k = 1;
        foreach ($approvers as $step => $a) {
            if ($k >= $chainFor[$st]) break;
            $reject = $st === 'rejected' && $k === $chainFor[$st] - 1;
            signature_add($b, $step, $a['signer'], $reject ? 'reject' : 'approve', $reject ? 'ช่วงเวลาดังกล่าวมีภารกิจของวิทยาลัย' : 'เห็นควรอนุมัติ', $a['png'],
                date('Y-m-d H:i:s', min($now - 900 + $k, $created + $k * 86400)));
            $k++;
        }
        if ($st === 'approved') $pdo->prepare('INSERT INTO booking_staff (booking_id,user_id,assigned_by) VALUES (?,?,?)')->execute([$id, $staffId, $adm]);
    };
    $used = [];
    $n = 0;
    foreach ($statuses as $i => $st) {
        do {
            $day = ($i * 7 + 3) % $days + 1;
            $day = ($day + $n) % $days + 1; $n++;
            $roomId = $roomIds[($i + $n) % 5];
            $slot = $slots[($i + $n) % count($slots)];
            $key = "$day-$roomId-{$slot[0]}";
        } while (isset($used[$key]));
        $used[$key] = 1;
        // อดีต → อนุมัติแล้ว
        if ($day < $today && in_array($st, ['pending', 'head_approved', 'deputy_approved', 'signed'], true)) $st = 'approved';
        $date = $first->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        $req = $i % 2 ? $t1 : $t2;
        $reqName = $i % 2 ? 'นางสาวกมลชนก อินทร์แก้ว' : 'นายวรวุฒิ ทองคำ';
        $makeBooking([$roomId, $depts[$i % count($depts)], $titles[$i % count($titles)], 'การประชุมตามแผนปฏิบัติราชการของวิทยาลัย', 10 + ($i * 7) % 60,
            "$date {$slot[0]}:00", "$date {$slot[1]}:00", '08' . rand(10000000, 99999999)], $st, $reqName, $req, $staff[$i % 2]);
    }
    // การจองวันนี้ 1 รายการ (เพื่อแสดงสถานะ "ไม่ว่าง")
    $d = date('Y-m-d');
    $makeBooking([$roomIds[0], 'ฝ่ายบริหารทรัพยากร', 'ประชุมคณะกรรมการบริหารวิทยาลัย', 'วาระประจำสัปดาห์', 15, "$d 09:00:00", "$d 12:00:00", '0812345678'], 'approved', 'นางสาวกมลชนก อินทร์แก้ว', $t1, $staff[0]);
    // การจองล่วงหน้าที่อยู่ระหว่างแต่ละขั้นของการลงนาม (เวลา 16:30–18:00 ไม่ชนกับรายการด้านบน)
    $waiting = [['pending', 'ประชุมชมรมวิชาชีพ', 2], ['pending', 'ประชุมเตรียมงานวันวิชาการ', 3], ['head_approved', 'ประชุมคณะทำงานงานพัสดุ', 4], ['deputy_approved', 'อบรมเชิงปฏิบัติการ AI เพื่อการสอน', 5], ['signed', 'ประชุมผู้ปกครองนักเรียน ปวช.1', 6]];
    foreach ($waiting as $j => [$st, $title, $plus]) {
        $d = date('Y-m-d', strtotime("+$plus days"));
        $reqName = $j % 2 ? 'นางสาวกมลชนก อินทร์แก้ว' : 'นายวรวุฒิ ทองคำ';
        $makeBooking([$roomIds[$j % 5], $depts[$j % count($depts)], $title, 'ขอใช้ห้องประชุมตามแผนงานโครงการ', 15 + $j * 3, "$d 16:30:00", "$d 18:00:00", '08' . rand(10000000, 99999999)],
            $st, $reqName, $j % 2 ? $t1 : $t2, $staff[0]);
    }
    $log[] = 'สร้างการจองตัวอย่าง ' . (count($statuses) + 1 + count($waiting)) . ' รายการ พร้อมลายเซ็นดิจิทัลตามขั้นตอน';

    $pdo->prepare("INSERT INTO logs (user_id,username,action,detail,ip) VALUES (?,?,?,?,?)")->execute([$adm, $adminUser, 'backup', 'ติดตั้งระบบและสร้างข้อมูลตัวอย่าง', client_ip()]);
    return $log;
}

// ---------------- CLI ----------------
if ($cli) {
    $args = $argv ?? [];
    if (is_file($lock) && !in_array('--force', $args, true)) exit("ติดตั้งแล้ว (ใช้ --force เพื่อติดตั้งใหม่ — ข้อมูลเดิมจะถูกลบ)\n");
    try {
        foreach (run_install(in_array('--demo', $args, true), 'admin', 'admin1234', in_array('--force', $args, true)) as $l) echo "✓ $l\n";
        file_put_contents($lock, date('c'));
        echo "เสร็จสิ้น — admin / admin1234\n";
    } catch (Throwable $e) { echo 'ผิดพลาด: ' . $e->getMessage() . "\n"; exit(1); }
    exit;
}

// ---------------- WEB ----------------
session_start();
$done = null; $error = null;
$locked = is_file($lock);
if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $u = trim($_POST['username'] ?? 'admin');
    $p = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $u)) $error = 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ . ความยาว 3–50 ตัว';
    elseif (strlen($p) < 8) $error = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    else {
        try { $done = run_install(!empty($_POST['demo']), $u, $p); file_put_contents($lock, date('c')); $locked = true; }
        catch (Throwable $e) { $error = $e->getMessage(); }
    }
}
?><!doctype html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้งระบบ · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center p-3" style="min-height:100vh">
<div class="card-x reveal" style="max-width:560px;width:100%">
  <div class="card-x-body p-4">
    <div class="d-flex align-items-center gap-3 mb-4">
      <img src="<?= asset('img/logo.svg') ?>" width="52" height="52" alt="">
      <div><h4 class="mb-0">ติดตั้งระบบจองห้องประชุม</h4><small class="text-muted"><?= e(ORG_NAME) ?></small></div>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($done): ?>
      <div class="alert alert-success"><strong>ติดตั้งสำเร็จ!</strong></div>
      <ul class="small"><?php foreach ($done as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
      <a class="btn btn-primary w-100" href="<?= url('login.php') ?>">ไปหน้าเข้าสู่ระบบ</a>
    <?php elseif ($locked): ?>
      <div class="alert alert-info">ระบบติดตั้งแล้ว หากต้องการติดตั้งใหม่ให้ลบไฟล์ <code>install/install.lock</code></div>
      <a class="btn btn-primary w-100" href="<?= url('login.php') ?>">ไปหน้าเข้าสู่ระบบ</a>
    <?php else: ?>
      <p class="text-muted small">ฐานข้อมูล: <code><?= e(DB_USER) ?>@<?= e(DB_HOST) ?>/<?= e(DB_NAME) ?></code> (แก้ไขได้ที่ config/config.php)</p>
      <form method="post">
        <div class="mb-3"><label class="form-label">ชื่อผู้ใช้ผู้ดูแลระบบ</label><input class="form-control" name="username" value="admin" required></div>
        <div class="mb-3"><label class="form-label">รหัสผ่าน (อย่างน้อย 8 ตัว)</label><input class="form-control" type="password" name="password" value="" required minlength="8"></div>
        <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="demo" id="demo" checked><label class="form-check-label" for="demo">สร้างข้อมูลตัวอย่าง (อาคาร ห้อง ผู้ใช้ และการจอง)</label></div>
        <button class="btn btn-primary w-100"><i class="bi bi-rocket-takeoff me-1"></i>เริ่มติดตั้ง</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
