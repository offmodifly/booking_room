<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('settings');

if (is_post()) {
    csrf_check();
    try {
        if (input('action') === 'logo') {
            save_logo($_FILES['logo'] ?? null);
            log_action('settings', 'เปลี่ยนโลโก้หน่วยงาน');
            flash('success', 'เปลี่ยนโลโก้เรียบร้อย');
        } elseif (input('action') === 'logo_reset') {
            delete_upload(LOGO_FILE);
            log_action('settings', 'คืนค่าโลโก้เริ่มต้น');
            flash('success', 'กลับไปใช้โลโก้เริ่มต้นแล้ว');
        }
    } catch (Throwable $e) { flash('error', e($e->getMessage())); }
    redirect('admin/settings.php');
}

$pageTitle = 'ตั้งค่าโลโก้';
$activeMenu = 'settings';
require __DIR__ . '/../includes/header.php';
$custom = has_custom_logo();
?>
<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" enctype="multipart/form-data" class="card-x reveal" id="logoForm"><?= csrf_field() ?><input type="hidden" name="action" value="logo">
      <div class="card-x-head"><h5><i class="bi bi-image"></i>โลโก้วิทยาลัย</h5><?= $custom ? badge('ใช้โลโก้วิทยาลัย', '#10b981', 'bi-check2-circle') : badge('ใช้โลโก้เริ่มต้น', '#64748b', 'bi-info-circle') ?></div>
      <div class="card-x-body">
        <label class="logo-drop" id="logoDrop">
          <img id="logoPreview" src="<?= e(logo_url()) ?>" alt="โลโก้ปัจจุบัน">
          <span><strong><i class="bi bi-cloud-arrow-up me-1"></i>คลิกหรือลากไฟล์โลโก้มาวางที่นี่</strong>
            <small>แนะนำ PNG พื้นหลังโปร่งใส · ทรงจัตุรัสหรือวงกลม · อย่างน้อย 256×256 px · ไม่เกิน 5MB</small></span>
          <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
        </label>
        <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
          <?php if ($custom): ?><button type="button" class="btn btn-light text-danger" id="logoReset"><i class="bi bi-arrow-counterclockwise me-1"></i>กลับไปใช้โลโก้เริ่มต้น</button><?php endif; ?>
          <button class="btn btn-primary" id="logoSave" disabled><i class="bi bi-check2 me-1"></i>บันทึกโลโก้</button>
        </div>
      </div>
    </form>
    <form method="post" id="logoResetForm" hidden><?= csrf_field() ?><input type="hidden" name="action" value="logo_reset"></form>
  </div>
  <div class="col-lg-5">
    <div class="card-x reveal" style="--i:1">
      <div class="card-x-head"><h5><i class="bi bi-eye"></i>ตัวอย่างการแสดงผล</h5></div>
      <div class="card-x-body d-grid gap-3">
        <div><small class="text-muted d-block mb-1">แถบเมนูด้านซ้าย</small>
          <div class="logo-sample dark"><span class="brand-logo is-custom"><img class="js-logo" src="<?= e(logo_url()) ?>" alt=""></span><span class="brand-text"><strong>NTC Meeting</strong><small><?= e(ORG_NAME) ?></small></span></div></div>
        <div><small class="text-muted d-block mb-1">หน้าเว็บสำหรับผู้ขอใช้ห้อง</small>
          <div class="logo-sample"><span class="pub-brand"><img class="js-logo is-custom" src="<?= e(logo_url()) ?>" alt=""><span><strong>NTC Meeting</strong><small><?= e(ORG_NAME) ?></small></span></span></div></div>
        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>โลโก้จะแสดงที่แถบเมนู หน้าเข้าสู่ระบบ หน้าเว็บสาธารณะ และไอคอนบนแท็บเบราว์เซอร์</small>
      </div>
    </div>
  </div>
</div>
<script>
(() => {
  const input = document.getElementById('logoInput'), drop = document.getElementById('logoDrop'), save = document.getElementById('logoSave');
  const show = f => {
    if (!f) return;
    if (!/^image\/(png|jpeg|webp|gif)$/.test(f.type)) { Swal.fire({icon: 'error', title: 'รองรับเฉพาะ PNG, JPG, WEBP, GIF'}); input.value = ''; return; }
    if (f.size > 5 * 1048576) { Swal.fire({icon: 'error', title: 'ไฟล์ใหญ่เกิน 5MB'}); input.value = ''; return; }
    const u = URL.createObjectURL(f);
    document.getElementById('logoPreview').src = u;
    document.querySelectorAll('.js-logo').forEach(i => i.src = u);
    save.disabled = false;
  };
  input.addEventListener('change', () => show(input.files[0]));
  ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
  drop.addEventListener('drop', e => { const f = e.dataTransfer.files[0]; if (!f) return; const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files; show(f); });
  document.getElementById('logoReset')?.addEventListener('click', () => {
    Swal.fire({icon: 'question', title: 'กลับไปใช้โลโก้เริ่มต้น?', showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก'})
      .then(r => { if (r.isConfirmed) document.getElementById('logoResetForm').submit(); });
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
