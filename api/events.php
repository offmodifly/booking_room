<?php
/** ข้อมูลปฏิทิน (FullCalendar) */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$start = date('Y-m-d H:i:s', strtotime(qs('start', date('Y-m-01'))) ?: time());
$end   = date('Y-m-d H:i:s', strtotime(qs('end', date('Y-m-t 23:59:59'))) ?: time());
$params = [$end, $start];
$where = "b.start_datetime < ? AND b.end_datetime > ? AND b.status IN " . ACTIVE_SQL . "";
if ($roomId = (int)qs('room_id')) { $where .= ' AND b.room_id = ?'; $params[] = $roomId; }

$rows = db_all("SELECT b.*, r.name room_name, bd.name building_name
  FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN buildings bd ON bd.id = r.building_id
  WHERE $where ORDER BY b.start_datetime", $params);

$events = [];
foreach ($rows as $b) {
    $st = BOOKING_STATUS[$b['status']];
    $events[] = [
        'id' => $b['id'],
        'title' => $b['room_name'] . ' · ' . $b['title'],
        'start' => str_replace(' ', 'T', $b['start_datetime']),
        'end' => str_replace(' ', 'T', $b['end_datetime']),
        'color' => $st['color'],
        'extendedProps' => [
            'no' => $b['booking_no'], 'room' => $b['room_name'], 'building' => $b['building_name'], 'topic' => $b['title'],
            'requester' => $b['requester_name'], 'department' => $b['department'], 'attendees' => (int)$b['attendees'],
            'status' => $b['status'], 'statusLabel' => $st['label'], 'time' => time_range($b['start_datetime'], $b['end_datetime']),
        ],
    ];
}
json_out($events);
