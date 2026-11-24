<?php
require __DIR__ . '/includes/bootstrap.php';
if ($u = current_user()) log_action('logout', 'ออกจากระบบ', $u);
remember_clear(); // ออกจากระบบ = ยกเลิก "จดจำการเข้าสู่ระบบ" ของเครื่องนี้ด้วย
$_SESSION = [];
session_destroy();
redirect('login.php');
