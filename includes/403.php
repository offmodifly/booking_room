<?php
$pageTitle = 'ไม่มีสิทธิ์เข้าถึง';
$activeMenu = '';
require __DIR__ . '/header.php';
?>
<div class="empty-state reveal">
  <div class="empty-icon" style="--c:#ef4444"><i class="bi bi-shield-lock"></i></div>
  <h3>ไม่มีสิทธิ์เข้าถึงหน้านี้</h3>
  <p class="text-muted">บัญชีของคุณ (<?= e(ROLES[current_user()['role']]['label'] ?? '') ?>) ไม่ได้รับสิทธิ์ใช้งานเมนูนี้</p>
  <a href="<?= url('admin/index.php') ?>" class="btn btn-primary"><i class="bi bi-house me-1"></i>กลับหน้าแดชบอร์ด</a>
</div>
<?php require __DIR__ . '/footer.php'; ?>
