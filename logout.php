<?php
require __DIR__ . '/includes/bootstrap.php';
if ($u = current_user()) log_action('logout', 'ออกจากระบบ', $u);
$_SESSION = [];
session_destroy();
redirect('login.php');
