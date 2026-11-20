<?php
/** โครงหน้าเว็บสาธารณะ (ผู้ขอใช้ห้อง — ไม่ต้องเข้าสู่ระบบ) */
function public_header(string $title, string $active = '', string $extraHead = ''): void
{
    $me = current_user();
    $nav = [
        'home'    => ['หน้าแรก', 'index.php', 'bi-house'],
        'request' => ['ขอใช้ห้องประชุม', 'request.php', 'bi-calendar-plus'],
        'status'  => ['ตรวจสอบสถานะ', 'status.php', 'bi-search'],
    ];
?><!doctype html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?> <?= e(ORG_NAME) ?></title>
<script>(function(){try{var t=localStorage.getItem('ntc-theme');if(t)document.documentElement.setAttribute('data-bs-theme',t);}catch(e){}})();</script>
<?= favicon_tag() ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
<script>window.APP={base:<?= json_encode(BASE_URL) ?>,csrf:<?= json_encode(csrf_token()) ?>,guest:true};</script>
<?= $extraHead ?>
</head>
<body class="pub">
<header class="pub-nav">
  <div class="container d-flex align-items-center gap-3">
    <a class="pub-brand" href="<?= url('index.php') ?>"><img src="<?= e(logo_url()) ?>" alt="โลโก้"<?= has_custom_logo() ? ' class="is-custom"' : '' ?>><span><strong>NTC Meeting</strong><small><?= e(ORG_NAME) ?></small></span></a>
    <nav class="pub-links ms-auto" id="pubLinks">
      <?php foreach ($nav as $k => [$label, $href, $icon]): ?>
        <a href="<?= url($href) ?>" class="<?= $active === $k ? 'active' : '' ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <button class="btn-icon theme-toggle" data-theme-toggle title="สลับโหมดมืด/สว่าง"><i class="bi bi-moon-stars icon-dark"></i><i class="bi bi-sun icon-light"></i></button>
    <?php if ($me): ?>
      <a class="btn btn-ghost btn-sm d-none d-md-inline-flex" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-1"></i>ส่วนจัดการ</a>
    <?php else: ?>
      <a class="btn btn-ghost btn-sm d-none d-md-inline-flex" href="<?= url('login.php') ?>"><i class="bi bi-person-lock me-1"></i>เจ้าหน้าที่</a>
    <?php endif; ?>
    <button class="btn-icon d-lg-none" onclick="document.getElementById('pubLinks').classList.toggle('open')" aria-label="เมนู"><i class="bi bi-list"></i></button>
  </div>
</header>
<main class="pub-main">
<?php
}

function public_footer(string $extraScripts = ''): void
{
?>
</main>
<footer class="pub-footer">
  <div class="container d-flex flex-wrap justify-content-between gap-2">
    <span>© <?= date('Y') + 543 ?> <?= e(APP_NAME) ?> · <?= e(ORG_NAME) ?></span>
    <span><a href="<?= url('status.php') ?>">ตรวจสอบสถานะการจอง</a> · <a href="<?= url('login.php') ?>">สำหรับเจ้าหน้าที่</a></span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>window.__FLASH = <?= json_encode(take_flashes(), JSON_UNESCAPED_UNICODE) ?>;</script>
<?= $extraScripts ?>
</body>
</html>
<?php
}

/** ข้อมูลห้องสำหรับหน้าเว็บสาธารณะ */
function public_rooms(): array
{
    return db_all("SELECT r.*, b.name building_name, b.code building_code,
        (SELECT file FROM room_images i WHERE i.room_id=r.id ORDER BY (wall='front') DESC, sort_order, id LIMIT 1) cover
      FROM rooms r JOIN buildings b ON b.id=r.building_id ORDER BY b.code, r.floor, r.name");
}
