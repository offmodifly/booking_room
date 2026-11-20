<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('profile');
$me = current_user();

if (is_post()) {
    csrf_check();
    try {
        if (input('action') === 'info') {
            $email = input('email') ?: null;
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('รูปแบบอีเมลไม่ถูกต้อง');
            if (input('first_name') === '') throw new RuntimeException('กรุณากรอกชื่อ');
            db_exec('UPDATE users SET prefix=?, first_name=?, last_name=?, email=?, phone=? WHERE id=?',
                [mb_substr(input('prefix'), 0, 30), mb_substr(input('first_name'), 0, 100), mb_substr(input('last_name'), 0, 100), $email, mb_substr(input('phone'), 0, 30) ?: null, $me['id']]);
            log_action('profile', 'แก้ไขข้อมูลส่วนตัว');
            flash('success', 'บันทึกข้อมูลส่วนตัวแล้ว');
        } elseif (input('action') === 'avatar') {
            $file = save_avatar($_FILES['avatar'] ?? null);
            db_exec('UPDATE users SET avatar=? WHERE id=?', [$file, $me['id']]);
            delete_upload($me['avatar'] ?? null);
            log_action('profile', 'เปลี่ยนรูปโปรไฟล์');
            flash('success', 'อัปโหลดรูปโปรไฟล์เรียบร้อย');
        } elseif (input('action') === 'avatar_remove') {
            db_exec('UPDATE users SET avatar=NULL WHERE id=?', [$me['id']]);
            delete_upload($me['avatar'] ?? null);
            log_action('profile', 'ลบรูปโปรไฟล์');
            flash('success', 'ลบรูปโปรไฟล์แล้ว');
        } elseif (input('action') === 'password') {
            if (!password_verify((string)input('current'), $me['password_hash'])) throw new RuntimeException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
            if (strlen((string)input('new')) < 8) throw new RuntimeException('รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร');
            if (input('new') !== input('confirm')) throw new RuntimeException('ยืนยันรหัสผ่านไม่ตรงกัน');
            db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash((string)input('new'), PASSWORD_DEFAULT), $me['id']]);
            log_action('profile', 'เปลี่ยนรหัสผ่าน');
            flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อย');
        }
    } catch (Throwable $e) { flash('error', e($e->getMessage())); }
    redirect('admin/profile.php');
}

$pageTitle = 'โปรไฟล์ของฉัน';
$activeMenu = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <div class="col-lg-4 reveal"><div class="card-x text-center"><div class="card-x-body p-4">
    <form method="post" enctype="multipart/form-data" id="avatarForm" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="avatar">
      <label class="avatar-edit" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
        <span id="avatarView"><?= avatar($me, 120) ?></span>
        <span class="avatar-edit-btn"><i class="bi bi-camera-fill"></i></span>
        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
      </label>
      <div id="avatarActions" class="d-none gap-2 justify-content-center mt-2">
        <button class="btn btn-primary btn-sm"><i class="bi bi-cloud-arrow-up me-1"></i>บันทึกรูป</button>
        <button type="button" class="btn btn-light btn-sm" id="avatarCancel">ยกเลิก</button>
      </div>
      <div class="small text-muted mt-2" id="avatarHint">คลิกที่รูปเพื่ออัปโหลด · JPG / PNG / WEBP ไม่เกิน 5MB<br>ระบบจะตัดเป็นสี่เหลี่ยมจัตุรัสให้อัตโนมัติ</div>
    </form>
    <?php if (!empty($me['avatar'])): ?>
    <form method="post" class="mb-3" id="avatarRemoveForm"><?= csrf_field() ?><input type="hidden" name="action" value="avatar_remove">
      <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash3 me-1"></i>ลบรูปโปรไฟล์</button></form>
    <?php endif; ?>
    <h5 class="mb-1"><?= e(full_name($me)) ?></h5><p class="text-muted small mb-2"><?= e($me['position']) ?></p><?= role_badge($me['role']) ?>
    <hr><dl class="dl text-start" style="grid-template-columns:110px 1fr"><dt>ชื่อผู้ใช้</dt><dd>@<?= e($me['username']) ?></dd><dt>เข้าใช้ล่าสุด</dt><dd><?= e(thai_date($me['last_login'], 'datetime')) ?></dd><dt>สมัครเมื่อ</dt><dd><?= e(thai_date($me['created_at'])) ?></dd></dl>
  </div></div></div>
  <div class="col-lg-8">
    <form method="post" class="card-x reveal mb-4" style="--i:1"><?= csrf_field() ?><input type="hidden" name="action" value="info">
      <div class="card-x-head"><h5><i class="bi bi-person"></i>ข้อมูลส่วนตัว</h5></div>
      <div class="card-x-body row g-3">
        <div class="col-md-3"><label class="form-label">คำนำหน้า</label><input class="form-control" name="prefix" value="<?= e($me['prefix']) ?>"></div>
        <div class="col-md-4"><label class="form-label">ชื่อ</label><input class="form-control" name="first_name" value="<?= e($me['first_name']) ?>" required></div>
        <div class="col-md-5"><label class="form-label">นามสกุล</label><input class="form-control" name="last_name" value="<?= e($me['last_name']) ?>"></div>
        <div class="col-md-6"><label class="form-label">อีเมล</label><input class="form-control" type="email" name="email" value="<?= e($me['email']) ?>"></div>
        <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e($me['phone']) ?>"></div>
        <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>บันทึก</button></div>
      </div></form>
    <form method="post" class="card-x reveal" style="--i:2"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="card-x-head"><h5><i class="bi bi-key"></i>เปลี่ยนรหัสผ่าน</h5></div>
      <div class="card-x-body row g-3">
        <div class="col-md-4"><label class="form-label">รหัสผ่านปัจจุบัน</label><input class="form-control" type="password" name="current" required autocomplete="current-password"></div>
        <div class="col-md-4"><label class="form-label">รหัสผ่านใหม่</label><input class="form-control" type="password" name="new" minlength="8" required autocomplete="new-password"></div>
        <div class="col-md-4"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input class="form-control" type="password" name="confirm" minlength="8" required autocomplete="new-password"></div>
        <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>เปลี่ยนรหัสผ่าน</button></div>
      </div></form>
  </div>
</div>
<script>
(() => {
  const input = document.getElementById('avatarInput'), view = document.getElementById('avatarView');
  const actions = document.getElementById('avatarActions'), hint = document.getElementById('avatarHint');
  const original = view.innerHTML;
  input.addEventListener('change', () => {
    const f = input.files[0];
    if (!f) return;
    if (!/^image\/(jpeg|png|webp|gif)$/.test(f.type)) { Swal.fire({icon: 'error', title: 'รองรับเฉพาะ JPG, PNG, WEBP, GIF'}); input.value = ''; return; }
    if (f.size > 5 * 1048576) { Swal.fire({icon: 'error', title: 'ไฟล์ใหญ่เกิน 5MB'}); input.value = ''; return; }
    const url = URL.createObjectURL(f);
    view.innerHTML = `<img class="avatar avatar-img" src="${url}" style="width:120px;height:120px" alt="">`;
    actions.classList.replace('d-none', 'd-flex'); hint.hidden = true;
  });
  document.getElementById('avatarCancel').addEventListener('click', () => {
    input.value = ''; view.innerHTML = original; actions.classList.replace('d-flex', 'd-none'); hint.hidden = false;
  });
  document.getElementById('avatarRemoveForm')?.addEventListener('submit', e => {
    e.preventDefault();
    Swal.fire({icon: 'warning', title: 'ลบรูปโปรไฟล์?', text: 'จะกลับไปใช้ตัวอักษรย่อชื่อแทน', showCancelButton: true, confirmButtonText: 'ลบรูป', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#ef4444'})
      .then(r => { if (r.isConfirmed) e.target.submit(); });
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
