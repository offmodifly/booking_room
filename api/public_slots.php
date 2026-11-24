<?php
/** ช่วงเวลาที่ห้องไม่ว่างในวันที่เลือก (สาธารณะ — ไม่เปิดเผยข้อมูลผู้จอง) */
require __DIR__ . '/../includes/bootstrap.php';
$room = db_one('SELECT id, status FROM rooms WHERE id=?', [(int)qs('room_id')]);
$date = (string)qs('date');
if (!$room || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok' => false, 'busy' => []], 400);
$rows = db_all("SELECT start_datetime, end_datetime, status FROM bookings WHERE room_id=? AND status IN " . ACTIVE_SQL . " AND start_datetime < ? AND end_datetime > ? ORDER BY start_datetime",
    [$room['id'], "$date 23:59:59", "$date 00:00:00"]);
// จองหลายวัน: ตรวจการชนตลอดช่วง วันแรก เวลาเริ่ม → วันสุดท้าย เวลาสิ้นสุด (คืนเฉพาะวัน-เวลาที่ชน ไม่เปิดเผยผู้จอง)
$range = null;
$dEnd = (string)qs('date_end');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dEnd) && $dEnd > $date && preg_match('/^\d{2}:\d{2}$/', (string)qs('start')) && preg_match('/^\d{2}:\d{2}$/', (string)qs('end'))) {
    $range = array_map(fn($b) => ['from' => thai_date($b['start_datetime'], 'datetime'), 'to' => thai_date($b['end_datetime'], 'datetime')],
        booking_conflicts((int)$room['id'], "$date " . qs('start') . ':00', "$dEnd " . qs('end') . ':00'));
}
json_out([
    'ok' => true,
    'range' => $range,
    'closed' => $room['status'] === 'unavailable',
    'busy' => array_map(fn($b) => [
        'start' => substr($b['start_datetime'], 0, 10) < $date ? '00:00' : substr($b['start_datetime'], 11, 5),
        'end' => substr($b['end_datetime'], 0, 10) > $date ? '24:00' : substr($b['end_datetime'], 11, 5),
        'status' => $b['status'] === 'approved' ? 'unavailable' : 'booked',
        'label' => $b['status'] === 'approved' ? 'ไม่ว่าง' : 'จองแล้ว (รออนุมัติ)',
    ], $rows),
]);
