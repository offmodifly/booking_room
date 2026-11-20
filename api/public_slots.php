<?php
/** ช่วงเวลาที่ห้องไม่ว่างในวันที่เลือก (สาธารณะ — ไม่เปิดเผยข้อมูลผู้จอง) */
require __DIR__ . '/../includes/bootstrap.php';
$room = db_one('SELECT id, status FROM rooms WHERE id=?', [(int)qs('room_id')]);
$date = (string)qs('date');
if (!$room || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok' => false, 'busy' => []], 400);
$rows = db_all("SELECT start_datetime, end_datetime, status FROM bookings WHERE room_id=? AND status IN " . ACTIVE_SQL . " AND start_datetime < ? AND end_datetime > ? ORDER BY start_datetime",
    [$room['id'], "$date 23:59:59", "$date 00:00:00"]);
json_out([
    'ok' => true,
    'closed' => $room['status'] === 'unavailable',
    'busy' => array_map(fn($b) => [
        'start' => substr($b['start_datetime'], 0, 10) < $date ? '00:00' : substr($b['start_datetime'], 11, 5),
        'end' => substr($b['end_datetime'], 0, 10) > $date ? '24:00' : substr($b['end_datetime'], 11, 5),
        'status' => $b['status'] === 'approved' ? 'unavailable' : 'booked',
        'label' => $b['status'] === 'approved' ? 'ไม่ว่าง' : 'จองแล้ว (รออนุมัติ)',
    ], $rows),
]);
