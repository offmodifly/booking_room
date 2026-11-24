<?php
/** @var string $pageTitle @var string $activeMenu @var string $extraHead */
$me = current_user();
$pageTitle  = $pageTitle ?? 'แดชบอร์ด';
$activeMenu = $activeMenu ?? '';
$theme = $me['theme'] ?? 'light';

// การแจ้งเตือน
$notifs = [];
if ($me) {
    if ($mySignStep = role_sign_step($me['role'])) {
        foreach (db_all("SELECT b.id,b.title,b.start_datetime,r.name room FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.status=? ORDER BY b.start_datetime LIMIT 6", [SIGN_STEPS[$mySignStep]['from']]) as $n) {
            $notifs[] = ['icon' => 'bi-pen', 'color' => '#f59e0b', 'title' => 'รอท่านลงนาม: ' . $n['title'], 'sub' => $n['room'] . ' · ' . thai_date($n['start_datetime'], 'datetime'), 'url' => url('admin/sign.php?id=' . $n['id'])];
        }
    }
    if ($me['role'] === 'admin') {
        foreach (db_all("SELECT b.id,b.title,b.start_datetime,r.name room FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.status='signed' ORDER BY b.start_datetime LIMIT 6") as $n) {
            $notifs[] = ['icon' => 'bi-patch-check', 'color' => '#3b82f6', 'title' => 'ลงนามครบแล้ว · รอยืนยัน: ' . $n['title'], 'sub' => $n['room'] . ' · ' . thai_date($n['start_datetime'], 'datetime'), 'url' => url('admin/bookings.php?status=signed&view=' . $n['id'])];
        }
    }
    if ($me['role'] === 'room_staff') {
        foreach (db_all("SELECT b.id,b.title,b.start_datetime,r.name room,s.ack_at FROM booking_staff s JOIN bookings b ON b.id=s.booking_id JOIN rooms r ON r.id=b.room_id WHERE s.user_id=? AND b.status='approved' AND b.end_datetime>=NOW() AND s.done_at IS NULL ORDER BY (s.ack_at IS NULL) DESC, b.start_datetime LIMIT 6", [$me['id']]) as $n) {
            $notifs[] = $n['ack_at']
                ? ['icon' => 'bi-clipboard-check', 'color' => '#10b981', 'title' => 'งานดูแลห้อง: ' . $n['title'], 'sub' => $n['room'] . ' · ' . thai_date($n['start_datetime'], 'datetime'), 'url' => url('admin/tasks.php#task-' . $n['id'])]
                : ['icon' => 'bi-bell-fill', 'color' => '#ef4444', 'title' => 'งานใหม่ (รอรับทราบ): ' . $n['title'], 'sub' => $n['room'] . ' · ' . thai_date($n['start_datetime'], 'datetime'), 'url' => url('admin/tasks.php#task-' . $n['id'])];
        }
    }
}
?><!doctype html>
<html lang="th" data-bs-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?> <?= e(ORG_NAME) ?></title>
<script>(function(){try{var t=localStorage.getItem('ntc-theme');if(t)document.documentElement.setAttribute('data-bs-theme',t);if(localStorage.getItem('ntc-mini')==='1')document.documentElement.classList.add('sidebar-mini');}catch(e){}})();</script>
<?= favicon_tag() ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
<script>window.APP={base:<?= json_encode(BASE_URL) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<?= $extraHead ?? '' ?>
</head>
<body>
<aside class="app-sidebar" id="sidebar">
  <a class="brand" href="<?= url('admin/index.php') ?>">
    <span class="brand-logo<?= has_custom_logo() ? ' is-custom' : '' ?>"><img src="<?= e(logo_url()) ?>" alt="โลโก้"></span>
    <span class="brand-text"><strong>NTC Meeting</strong><small><?= e(ORG_NAME) ?></small></span>
  </a>
  <nav class="side-nav">
    <?php
    $pendingHeader = null;
    foreach (MENU as $m):
        if (isset($m['header'])) { $pendingHeader = $m['header']; continue; }
        if (!has_role($m['roles'])) continue;
        if ($pendingHeader) { echo '<div class="nav-header">' . e($pendingHeader) . '</div>'; $pendingHeader = null; }
        $count = 0;
        if ($m['key'] === 'bookings') $count = (int)db_val("SELECT COUNT(*) FROM bookings WHERE status='signed'");
        if ($m['key'] === 'sign' && ($ss = role_sign_step($me['role']))) $count = (int)db_val('SELECT COUNT(*) FROM bookings WHERE status=?', [SIGN_STEPS[$ss]['from']]);
        if ($m['key'] === 'tasks') $count = staff_task_count((int)$me['id']);
    ?>
      <a class="nav-link-x <?= $activeMenu === $m['key'] ? 'active' : '' ?>" href="<?= url($m['url']) ?>" data-bs-title="<?= e($m['label']) ?>">
        <i class="bi <?= e($m['icon']) ?>"></i><span class="nav-text"><?= e($m['label']) ?></span>
        <?php if ($count): ?><span class="nav-badge"><?= $count ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <div class="side-user">
      <?= avatar($me, 38) ?>
      <div class="side-user-text"><strong><?= e(full_name($me)) ?></strong><small><?= e(ROLES[$me['role']]['label'] ?? '') ?></small></div>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>

<main class="app-main">
  <header class="topbar">
    <button class="btn-icon d-lg-none" data-sidebar-toggle aria-label="เมนู"><i class="bi bi-list"></i></button>
    <button class="btn-icon d-none d-lg-inline-grid" data-sidebar-mini aria-label="ย่อเมนู"><i class="bi bi-layout-sidebar-inset"></i></button>
    <div class="topbar-title">
      <h1><?= e($pageTitle) ?></h1>
      <small class="d-none d-md-block"><?= e(thai_date(date('Y-m-d'), 'long')) ?> · <span data-clock><?= date('H:i:s') ?></span> น.</small>
    </div>
    <div class="topbar-actions">
      <button class="btn-icon theme-toggle" data-theme-toggle aria-label="สลับโหมด" title="สลับโหมด Dark / Light">
        <i class="bi bi-moon-stars icon-dark"></i><i class="bi bi-sun icon-light"></i>
      </button>
      <div class="dropdown">
        <button class="btn-icon position-relative" data-bs-toggle="dropdown" aria-label="การแจ้งเตือน">
          <i class="bi bi-bell"></i><?php if ($notifs): ?><span class="dot-badge"><?= count($notifs) ?></span><?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end notif-menu">
          <div class="notif-head">การแจ้งเตือน <span class="text-muted small"><?= count($notifs) ?> รายการ</span></div>
          <?php if (!$notifs): ?><div class="empty-mini"><i class="bi bi-bell-slash"></i>ไม่มีการแจ้งเตือนใหม่</div><?php endif; ?>
          <?php foreach ($notifs as $n): ?>
            <a class="notif-item" href="<?= e($n['url']) ?>"><span class="notif-icon" style="--c:<?= $n['color'] ?>"><i class="bi <?= $n['icon'] ?>"></i></span><span><strong><?= e($n['title']) ?></strong><small><?= e($n['sub']) ?></small></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="dropdown">
        <button class="user-chip" data-bs-toggle="dropdown">
          <?= avatar($me, 34) ?><span class="d-none d-md-flex flex-column text-start"><strong><?= e($me['first_name']) ?></strong><small><?= e($me['username']) ?></small></span><i class="bi bi-chevron-down small d-none d-md-inline"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="px-3 py-2"><div class="fw-semibold"><?= e(full_name($me)) ?></div><div class="mt-1"><?= role_badge($me['role']) ?></div></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= url('admin/profile.php') ?>"><i class="bi bi-person-circle me-2"></i>โปรไฟล์ / เปลี่ยนรหัสผ่าน</a></li>
          <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
        </ul>
      </div>
    </div>
  </header>
  <div class="app-content">
