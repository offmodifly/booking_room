<?php
/** ตรวจสอบสถานะการใช้ห้อง — มุมมองรายวัน: ทุกห้อง + แถบเวลา + รายการจองของวันนั้น (เจ้าหน้าที่งานอาคาร / admin) */
require __DIR__ . '/../includes/bootstrap.php';
page_guard('usage');

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)qs('date')) ? qs('date') : date('Y-m-d');
$bid = (int)qs('building_id');
$isToday = $date === date('Y-m-d');
$nowMin = (int)date('G') * 60 + (int)date('i');

$buildings = db_all('SELECT id, name FROM buildings ORDER BY name');
$rooms = db_all('SELECT r.*, b.name building_name FROM rooms r JOIN buildings b ON b.id=r.building_id' . ($bid ? ' WHERE r.building_id=?' : '') . ' ORDER BY b.name, r.floor, r.name', $bid ? [$bid] : []);
$dayBk = db_all("SELECT b.*, r.name room_name, bd.name building_name,
    (SELECT GROUP_CONCAT(CONCAT(u.first_name, IF(s.done_at IS NOT NULL,' ✔',IF(s.ack_at IS NOT NULL,' ✓',' •'))) SEPARATOR ', ') FROM booking_staff s JOIN users u ON u.id=s.user_id WHERE s.booking_id=b.id) staff
  FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id
  WHERE b.status IN " . ACTIVE_SQL . " AND b.start_datetime <= ? AND b.end_datetime >= ?" . ($bid ? ' AND r.building_id=?' : '') . ' ORDER BY b.start_datetime',
    array_merge(["$date 23:59:59", "$date 00:00:00"], $bid ? [$bid] : []));
$byRoom = [];
foreach ($dayBk as $b) $byRoom[$b['room_id']][] = $b;

// เวลาในวันที่เลือก (นาที) — การจองหลายวัน: วันกลางใช้เวลาเริ่ม-เลิกประจำวัน
$DAY0 = 7 * 60; $DAY1 = 21 * 60;
$clip = function (array $b) use ($date): array {
    $sd = substr($b['start_datetime'], 0, 10); $ed = substr($b['end_datetime'], 0, 10);
    $sm = (int)substr($b['start_datetime'], 11, 2) * 60 + (int)substr($b['start_datetime'], 14, 2);
    $em = (int)substr($b['end_datetime'], 11, 2) * 60 + (int)substr($b['end_datetime'], 14, 2);
    if ($sd === $ed) return [$sm, $em];
    if ($em <= $sm) return [$date === $sd ? $sm : 0, $date === $ed ? $em : 1440];
    return [$sm, $em]; // หลายวัน: ทุกวันใช้ช่วงเวลาเดียวกัน
};
$pct = fn(int $m) => max(0, min(100, ($m - $DAY0) / ($DAY1 - $DAY0) * 100));

$sum = ['inuse' => 0, 'booked' => 0, 'free' => 0, 'closed' => 0];
foreach ($rooms as &$r) {
    $list = $byRoom[$r['id']] ?? [];
    $r['inuse'] = null;
    foreach ($list as $b) { [$s, $e] = $clip($b); if ($isToday && $b['status'] === 'approved' && $s <= $nowMin && $nowMin < $e) $r['inuse'] = $b; }
    $r['state'] = $r['status'] === 'unavailable' ? 'closed' : ($r['inuse'] ? 'inuse' : ($list ? 'booked' : 'free'));
    $sum[$r['state']]++;
}
unset($r);
$STATE = ['inuse' => ['กำลังใช้งาน', '#ef4444', 'bi-broadcast'], 'booked' => ['มีการจองวันนี้', '#f59e0b', 'bi-calendar-event'], 'free' => ['ว่างทั้งวัน', '#10b981', 'bi-check-circle'], 'closed' => ['ปิดปรับปรุง', '#64748b', 'bi-slash-circle']];
if (!$isToday) $STATE['booked'][0] = 'มีการจอง';

$pageTitle = 'ตรวจสอบสถานะการใช้ห้อง';
$activeMenu = 'usage';
require __DIR__ . '/../includes/header.php';
$prev = date('Y-m-d', strtotime("$date -1 day")); $next = date('Y-m-d', strtotime("$date +1 day"));
$q = fn(array $p) => '?' . http_build_query(array_filter(['date' => $date, 'building_id' => $bid ?: null] + $p));
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-calendar3-range"></i>Room Usage</span><h2>ตรวจสอบสถานะการใช้ห้อง</h2>
    <p><?= e(thai_date($date, 'long')) ?><?= $isToday ? ' · <span class="text-success fw-semibold">วันนี้ ' . date('H:i') . ' น.</span>' : '' ?></p></div>
  <form class="actions d-flex flex-wrap gap-2 align-items-center" id="usageForm">
    <a class="btn-icon" href="<?= $q(['date' => $prev]) ?>" title="วันก่อนหน้า"><i class="bi bi-chevron-left"></i></a>
    <input type="date" class="form-control" name="date" value="<?= e($date) ?>" data-thai-date style="width:150px">
    <a class="btn-icon" href="<?= $q(['date' => $next]) ?>" title="วันถัดไป"><i class="bi bi-chevron-right"></i></a>
    <?php if (!$isToday): ?><a class="btn btn-soft btn-sm" href="<?= $q(['date' => date('Y-m-d')]) ?>">วันนี้</a><?php endif; ?>
    <select class="form-select" name="building_id" style="width:auto" onchange="this.form.submit()"><option value="">ทุกอาคาร</option>
      <?php foreach ($buildings as $b): ?><option value="<?= $b['id'] ?>" <?= $bid === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select>
  </form>
</div>

<div class="task-stats usage-stats reveal" style="--i:1">
  <?php foreach ($STATE as $k => [$label, $color, $icon]): if ($k === 'inuse' && !$isToday) continue; ?>
    <div class="task-stat" style="--c:<?= $color ?>"><i class="bi <?= $icon ?>"></i><div><strong><?= $sum[$k] ?></strong><span><?= e($label) ?></span></div></div>
  <?php endforeach; ?>
</div>

<div class="card-x reveal mt-4" style="--i:2">
  <div class="card-x-head"><h5><i class="bi bi-door-open"></i>ห้องประชุม (<?= count($rooms) ?>)</h5>
    <span class="slot-legend"><i style="--c:#ef4444"></i>อนุมัติแล้ว <i style="--c:#f59e0b"></i>รออนุมัติ/ลงนาม <?php if ($isToday): ?><i style="--c:#6366f1"></i>เวลาปัจจุบัน<?php endif; ?></span></div>
  <div class="card-x-body">
    <div class="usage-scale"><span></span><div class="slot-scale"><?php for ($h = 7; $h <= 21; $h += 2): ?><span><?= sprintf('%02d:00', $h) ?></span><?php endfor; ?></div></div>
    <?php foreach ($rooms as $r): [$label, $color, $icon] = $STATE[$r['state']]; ?>
    <div class="usage-row">
      <div class="usage-room">
        <strong><?= e($r['name']) ?></strong>
        <small class="text-muted"><?= e($r['building_name']) ?> · ชั้น <?= (int)$r['floor'] ?> · <?= (int)$r['capacity'] ?> ที่นั่ง</small>
        <span class="usage-state" style="--c:<?= $color ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?><?= $r['inuse'] ? ' · ' . e(mb_strimwidth($r['inuse']['title'], 0, 28, '…')) : '' ?></span>
      </div>
      <div class="slot-bar usage-bar <?= $r['state'] === 'closed' ? 'is-closed' : '' ?>">
        <?php if ($r['state'] === 'closed'): ?><div class="slot-empty">ปิดปรับปรุง (Admin ตั้งสถานะ "ไม่ว่าง")</div><?php endif; ?>
        <?php foreach ($byRoom[$r['id']] ?? [] as $b): [$s, $e2] = $clip($b); $cls = $b['status'] === 'approved' ? 'unavailable' : 'booked'; ?>
          <a class="slot <?= $cls ?>" href="#bk-<?= $b['id'] ?>" style="left:<?= $pct($s) ?>%;width:<?= max(1.5, $pct($e2) - $pct($s)) ?>%" title="<?= e($b['booking_no'] . ' · ' . $b['title'] . ' · ' . sprintf('%02d:%02d–%02d:%02d', intdiv($s, 60), $s % 60, intdiv(min($e2, 1439), 60), min($e2, 1439) % 60)) ?>"><span><?= sprintf('%02d:%02d', intdiv($s, 60), $s % 60) ?>–<?= $e2 >= 1440 ? '24:00' : sprintf('%02d:%02d', intdiv($e2, 60), $e2 % 60) ?></span></a>
        <?php endforeach; ?>
        <?php if ($isToday && $nowMin >= $DAY0 && $nowMin <= $DAY1): ?><span class="usage-now" style="left:<?= $pct($nowMin) ?>%"></span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rooms): ?><p class="text-muted mb-0">ไม่มีห้องประชุม</p><?php endif; ?>
  </div>
</div>

<div class="card-x reveal mt-4" style="--i:3">
  <div class="card-x-head"><h5><i class="bi bi-list-check"></i>รายการใช้ห้อง <?= e(thai_date($date)) ?> (<?= count($dayBk) ?>)</h5></div>
  <?php if ($dayBk): ?>
  <div class="table-responsive"><table class="table table-x table-hover align-middle mb-0">
    <thead><tr><th>เลขที่</th><th>วัน-เวลา</th><th>ห้อง</th><th>ใช้สถานที่เพื่อ</th><th>ผู้ขอใช้</th><th>อุปกรณ์</th><th>เจ้าหน้าที่</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($dayBk as $b): $eq = booking_equipment_lines($b['equip'] ?? null); ?>
      <tr id="bk-<?= $b['id'] ?>">
        <td><span class="code-pill"><?= e($b['booking_no']) ?></span></td>
        <td class="small"><?= booking_when_html($b['start_datetime'], $b['end_datetime']) ?></td>
        <td><strong><?= e($b['room_name']) ?></strong><br><small class="text-muted"><?= e($b['building_name']) ?></small></td>
        <td><?= e($b['title']) ?><br><small class="text-muted"><?= (int)$b['attendees'] ?> คน</small></td>
        <td><?= e($b['requester_name']) ?><?= $b['contact_phone'] ? '<br><small><a href="tel:' . e(phone_digits($b['contact_phone'])) . '">' . e($b['contact_phone']) . '</a></small>' : '' ?></td>
        <td class="small"><?= $eq ? e(implode(', ', $eq)) : '<span class="text-muted">-</span>' ?></td>
        <td class="small"><?= $b['staff'] ? e($b['staff']) : '<span class="text-muted">-</span>' ?></td>
        <td><?= booking_badge($b['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="card-x-body pt-2 small text-muted">เจ้าหน้าที่: • ยังไม่รับทราบ · ✓ รับทราบแล้ว · ✔ จัดเตรียมเรียบร้อย</div>
  <?php else: ?>
    <div class="card-x-body text-muted"><i class="bi bi-calendar-x me-1"></i>ไม่มีการใช้ห้องในวันนี้</div>
  <?php endif; ?>
</div>
<?php
$extraScripts = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/th.js"></script>
<script src="' . asset('js/thaidate.js') . '"></script>
<script>document.querySelector("#usageForm [name=date]").addEventListener("change", e => e.target.form.submit());</script>';
require __DIR__ . '/../includes/footer.php';
