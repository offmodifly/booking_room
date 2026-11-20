<?php
/** ปฏิทินสาธารณะ — แสดงเฉพาะห้องและช่วงเวลาที่ไม่ว่าง (ไม่แสดงชื่อ/เบอร์ผู้จอง) */
require __DIR__ . '/../includes/bootstrap.php';
$start = date('Y-m-d H:i:s', strtotime(qs('start', date('Y-m-01'))) ?: time());
$end   = date('Y-m-d H:i:s', strtotime(qs('end', date('Y-m-t 23:59:59'))) ?: time());
$params = [$end, $start];
$where = "b.start_datetime < ? AND b.end_datetime > ? AND b.status IN " . ACTIVE_SQL . "";
if ($roomId = (int)qs('room_id')) { $where .= ' AND b.room_id = ?'; $params[] = $roomId; }
$rows = db_all("SELECT b.id, b.start_datetime, b.end_datetime, b.status, r.name room_name FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE $where ORDER BY b.start_datetime", $params);
json_out(array_map(fn($b) => [
    'id' => $b['id'],
    'title' => $b['room_name'] . ($b['status'] === 'approved' ? ' · ไม่ว่าง' : ' · จองแล้ว'),
    'start' => str_replace(' ', 'T', $b['start_datetime']),
    'end' => str_replace(' ', 'T', $b['end_datetime']),
    'color' => $b['status'] === 'approved' ? '#ef4444' : '#f59e0b',
], $rows));
