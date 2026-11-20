<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/booking_detail.php';
require_login();
$b = booking_load((int)qs('id'));
if (!$b) json_out(['ok' => false, 'message' => 'ไม่พบข้อมูลการจอง'], 404);
json_out(['ok' => true, 'status' => $b['status'], 'html' => render_booking_detail($b)]);
