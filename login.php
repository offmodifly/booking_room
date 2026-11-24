<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('admin/index.php');

$error = null;
$lockUntil = $_SESSION['login_lock'] ?? 0;
if (is_post()) {
    csrf_check();
    if ($lockUntil > time()) {
        $error = 'พยายามเข้าสู่ระบบผิดหลายครั้ง กรุณารอ ' . ceil(($lockUntil - time()) / 60) . ' นาที';
    } else {
        $username = input('username');
        $u = db_one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($u && $u['is_active'] && password_verify((string)input('password'), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            unset($_SESSION['login_fail'], $_SESSION['login_lock']);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash(input('password'), PASSWORD_DEFAULT), $u['id']]);
            db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
            if (input('remember')) remember_issue((int)$u['id']);
            log_action('login', 'เข้าสู่ระบบสำเร็จ' . (input('remember') ? ' (จดจำการเข้าสู่ระบบ ' . REMEMBER_DAYS . ' วัน)' : ''), $u);
            flash('success', 'ยินดีต้อนรับ ' . full_name($u));
            $next = (string)qs('next');
            if ($next && str_starts_with($next, BASE_URL . '/') && !str_contains($next, '//') && !str_contains($next, '\\')) redirect($next);
            redirect('admin/index.php');
        }
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) { $_SESSION['login_lock'] = time() + 300; $_SESSION['login_fail'] = 0; }
        log_action('login_failed', 'ชื่อผู้ใช้: ' . mb_substr($username, 0, 50), ['id' => $u['id'] ?? null, 'username' => mb_substr($username, 0, 50)]);
        $error = ($u && !$u['is_active']) ? 'บัญชีนี้ถูกระงับการใช้งาน' : 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?><!doctype html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ · <?= e(APP_NAME) ?> <?= e(ORG_NAME) ?></title>
<script>(function(){try{var t=localStorage.getItem('ntc-theme');if(t)document.documentElement.setAttribute('data-bs-theme',t);}catch(e){}})();</script>
<?= favicon_tag() ?>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth">
  <section class="auth-hero">
    <div class="grid-bg"></div>
    <div class="d-flex align-items-center gap-3">
      <img src="<?= e(logo_url()) ?>" width="48" height="48" alt="โลโก้"<?= has_custom_logo() ? ' class="auth-logo is-custom"' : '' ?>>
      <div><strong class="fs-5">NTC Meeting</strong><div class="small opacity-75"><?= e(ORG_NAME) ?></div></div>
    </div>
    <div style="max-width:520px">
      <span class="badge rounded-pill text-bg-light bg-opacity-10 border border-light border-opacity-25 mb-3 px-3 py-2" style="background:rgba(255,255,255,.08)!important;color:#fff!important"><i class="bi bi-stars me-1"></i>Smart Meeting Room Booking</span>
      <h1>ระบบจองห้องประชุม<br><span class="grad-text">วิทยาลัยเทคนิคน่าน</span></h1>
      <p class="opacity-75 mt-3 mb-0">จองห้อง ตรวจสอบสถานะแบบเรียลไทม์ ลงนามอนุมัติด้วยลายเซ็นดิจิทัล และสำรวจอาคารด้วยโมเดล 3 มิติ — ครบในที่เดียว</p>
    </div>
    <div class="small opacity-50">© <?= date('Y') + 543 ?> <?= e(ORG_NAME) ?></div>
    <div class="float-card" style="right:9%;top:22%;--c:#10b981"><span class="fc-i"><i class="bi bi-check2-circle"></i></span><div><strong>ห้องประชุมผู้บริหาร</strong><div class="opacity-75">ว่าง · พร้อมใช้งาน</div></div></div>
    <div class="float-card" style="right:16%;bottom:24%;animation-delay:-2s;--c:#8b5cf6"><span class="fc-i"><i class="bi bi-pen"></i></span><div><strong>ผอ. ลงนามแล้ว</strong><div class="opacity-75">ลายเซ็นดิจิทัลยืนยันแล้ว</div></div></div>
    <div class="float-card" style="right:38%;top:12%;animation-delay:-4s;--c:#0ea5e9"><span class="fc-i"><i class="bi bi-badge-3d"></i></span><div><strong>3D Building</strong></div></div>
  </section>
  <section class="auth-form">
    <div class="auth-box reveal">
      <div class="d-lg-none text-center mb-4"><img src="<?= e(logo_url()) ?>" width="60" alt="โลโก้"<?= has_custom_logo() ? ' class="auth-logo-m is-custom"' : '' ?>></div>
      <h2 class="mb-1">เข้าสู่ระบบ</h2>
      <p class="text-muted mb-4">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าใช้งาน</p>
      <?php if ($error): ?><div class="alert alert-danger d-flex gap-2 align-items-center py-2"><i class="bi bi-exclamation-triangle"></i><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">ชื่อผู้ใช้</label><div class="field"><i class="bi bi-person"></i><input class="form-control" name="username" value="<?= e(input('username')) ?>" required autofocus></div></div>
        <div class="mb-3"><label class="form-label">รหัสผ่าน</label><div class="field"><i class="bi bi-lock"></i><input class="form-control" type="password" name="password" id="pw" required>
          <button type="button" class="btn btn-sm position-absolute end-0 top-50 translate-middle-y me-1 text-muted" onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password';this.firstElementChild.classList.toggle('bi-eye');this.firstElementChild.classList.toggle('bi-eye-slash')"><i class="bi bi-eye"></i></button></div></div>
        <label class="remember-me">
          <input class="form-check-input" type="checkbox" name="remember" value="1" <?= input('remember') ? 'checked' : '' ?>>
          <span><strong>จดจำการเข้าสู่ระบบ</strong><small>เข้าสู่ระบบอัตโนมัติในเครื่องนี้ <?= REMEMBER_DAYS ?> วัน · ไม่ควรเลือกบนเครื่องสาธารณะ</small></span>
        </label>
        <button class="btn btn-primary w-100 py-2 mt-2"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</button>
      </form>
      <a class="guest-cta mt-4" href="<?= url('request.php') ?>"><i class="bi bi-calendar-plus"></i><span><strong>ต้องการขอใช้ห้องประชุม?</strong><small>ไม่ต้องเข้าสู่ระบบ กรอกแบบฟอร์มได้เลย · <span class="text-decoration-underline">ตรวจสอบสถานะด้วยเบอร์โทร</span></small></span><i class="bi bi-arrow-right ms-auto"></i></a>
      <div class="text-center text-muted small mt-3">เจ้าหน้าที่ติดปัญหาการเข้าสู่ระบบ ติดต่อผู้ดูแลระบบ · <a href="<?= url('index.php') ?>">หน้าแรก</a></div>
    </div>
  </section>
</div>
</body>
</html>
