<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
if (!is_post()) json_out(['ok' => false], 405);
csrf_check();
$t = input('theme') === 'dark' ? 'dark' : 'light';
db_exec('UPDATE users SET theme = ? WHERE id = ?', [$t, current_user()['id']]);
json_out(['ok' => true, 'theme' => $t]);
