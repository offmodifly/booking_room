<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('dashboard');
$me = current_user();

$monthStart = date('Y-m-01 00:00:00');
$monthEnd   = date('Y-m-t 23:59:59');
$today = date('Y-m-d');

// ---- stats
$stat = db_one("SELECT
   SUM(status IN " . ACTIVE_SQL . ") total,
   SUM(status IN ('pending','head_approved','deputy_approved')) pending, SUM(status='signed') signed, SUM(status='approved') approved
 FROM bookings WHERE start_datetime BETWEEN ? AND ?", [$monthStart, $monthEnd]);
$lastMonth = (int)db_val("SELECT COUNT(*) FROM bookings WHERE status IN " . ACTIVE_SQL . " AND start_datetime BETWEEN ? AND ?",
    [date('Y-m-01 00:00:00', strtotime('first day of last month')), date('Y-m-t 23:59:59', strtotime('last day of last month'))]);
$todayCount = (int)db_val("SELECT COUNT(*) FROM bookings WHERE status='approved' AND DATE(start_datetime)=?", [$today]);
$roomCount = (int)db_val('SELECT COUNT(*) FROM rooms');
$userCount = (int)db_val('SELECT COUNT(*) FROM users WHERE is_active=1');
$trend = $lastMonth ? round((($stat['total'] ?? 0) - $lastMonth) / $lastMonth * 100) : 0;

// ---- rooms + today status
$rooms = db_all('SELECT r.*, b.name building_name FROM rooms r JOIN buildings b ON b.id=r.building_id ORDER BY b.name, r.floor, r.name');
$todayBk = [];
foreach (db_all("SELECT * FROM bookings WHERE status IN " . ACTIVE_SQL . " AND start_datetime <= ? AND end_datetime >= ? ORDER BY start_datetime", [$today . ' 23:59:59', $today . ' 00:00:00']) as $b) {
    $todayBk[$b['room_id']][] = $b;
}
$nextBk = [];
foreach (db_all("SELECT b.* FROM bookings b WHERE b.status IN " . ACTIVE_SQL . " AND b.end_datetime >= NOW() ORDER BY b.start_datetime") as $b) {
    if (!isset($nextBk[$b['room_id']])) $nextBk[$b['room_id']] = $b;
}
// ชั่วโมงใช้งานเดือนนี้ (อนุมัติแล้ว) — เวลาทำการ 8 ชม. × วันทำการ
$workDays = 0;
for ($d = 1; $d <= (int)date('t'); $d++) { if ((int)date('N', strtotime(date('Y-m-') . $d)) < 6) $workDays++; }
$hours = [];
foreach (db_all("SELECT room_id, SUM(TIMESTAMPDIFF(MINUTE,start_datetime,end_datetime))/60 h, COUNT(*) c FROM bookings WHERE status='approved' AND start_datetime BETWEEN ? AND ? GROUP BY room_id", [$monthStart, $monthEnd]) as $h) {
    $hours[$h['room_id']] = $h;
}
$statusCount = ['available' => 0, 'booked' => 0, 'unavailable' => 0];
foreach ($rooms as &$r) {
    $r['eff'] = room_effective_status($r['status'], $todayBk[$r['id']] ?? []);
    $statusCount[$r['eff']]++;
    $r['hours'] = round((float)($hours[$r['id']]['h'] ?? 0), 1);
    $r['util'] = $workDays ? min(100, round($r['hours'] / ($workDays * 8) * 100)) : 0;
}
unset($r);

// ---- charts
$byStatus = db_all("SELECT status, COUNT(*) c FROM bookings WHERE start_datetime BETWEEN ? AND ? GROUP BY status", [$monthStart, $monthEnd]);
$trendRows = [];
for ($i = 5; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("first day of -$i month"));
    $me2 = date('Y-m-t 23:59:59', strtotime($ms));
    $row = db_one("SELECT COUNT(*) total, SUM(status='approved') approved FROM bookings WHERE status IN " . ACTIVE_SQL . " AND start_datetime BETWEEN ? AND ?", [$ms, $me2]);
    $trendRows[] = ['label' => TH_MONTHS_S[(int)date('n', strtotime($ms))], 'total' => (int)$row['total'], 'approved' => (int)$row['approved']];
}

// ---- upcoming & activity
$upcoming = db_all("SELECT b.*, r.name room_name FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.status IN " . ACTIVE_SQL . " AND b.end_datetime >= NOW() AND b.start_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY b.start_datetime LIMIT 6");
$activity = has_role('admin') ? db_all('SELECT * FROM logs ORDER BY id DESC LIMIT 7') : [];
$myJobs = $me['role'] === 'room_staff'
    ? db_all("SELECT b.*, r.name room_name, bd.name building_name FROM booking_staff s JOIN bookings b ON b.id=s.booking_id JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id WHERE s.user_id=? AND b.status='approved' AND b.end_datetime>=NOW() ORDER BY b.start_datetime", [$me['id']])
    : [];

$hour = (int)date('G');
$greet = $hour < 12 ? 'สวัสดีตอนเช้า' : ($hour < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');

$pageTitle = 'แดชบอร์ด';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div>
    <span class="eyebrow"><i class="bi bi-stars"></i><?= e($greet) ?></span>
    <h2>คุณ<?= e($me['first_name']) ?> <span class="text-grad">ภาพรวมการใช้ห้องประชุม</span></h2>
    <p>ข้อมูลประจำเดือน<?= e(thai_date(date('Y-m-d'), 'month')) ?> · อัปเดตล่าสุด <?= date('H:i') ?> น.</p>
  </div>
  <?php if (has_role('admin')): ?>
  <div class="actions">
    <a href="<?= url('admin/bookings.php?new=1') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มการจอง</a>
    <a href="<?= url('admin/bookings.php?status=signed') ?>" class="btn btn-ghost"><i class="bi bi-patch-check me-1"></i>รออนุมัติ <span class="badge rounded-pill text-bg-primary ms-1"><?= (int)$stat['signed'] ?></span></a>
  </div>
  <?php elseif ($ms = role_sign_step($me['role'])): ?>
  <div class="actions"><a href="<?= url('admin/sign.php') ?>" class="btn btn-primary"><i class="bi bi-pen me-1"></i>รอท่านลงนาม <span class="badge rounded-pill text-bg-light ms-1"><?= (int)db_val('SELECT COUNT(*) FROM bookings WHERE status=?', [SIGN_STEPS[$ms]['from']]) ?></span></a></div>
  <?php endif; ?>
</div>

<!-- ================= 1. CALENDAR ================= -->
<div class="card-x calendar-card mb-4 reveal" style="--i:1">
  <div class="card-x-head">
    <h5><i class="bi bi-calendar3"></i>ปฏิทินการใช้ห้องประชุม <small class="text-muted fw-normal">· ใครจองห้องอะไร ห้องไหนไม่ว่างวันใด</small></h5>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <select class="form-select form-select-sm" id="calRoom" style="width:auto;min-width:200px">
        <option value="">ทุกห้องประชุม</option>
        <?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="card-x-body">
    <div class="legend mb-3">
      <span style="--c:<?= BOOKING_STATUS['pending']['color'] ?>"><i></i>จอง · ระหว่างลงนามอนุมัติ</span>
      <span style="--c:<?= BOOKING_STATUS['signed']['color'] ?>"><i></i>จอง · ผอ. อนุมัติแล้ว</span>
      <span style="--c:<?= BOOKING_STATUS['approved']['color'] ?>"><i></i>ไม่ว่าง · อนุมัติแล้ว</span>
      <span><i style="background:repeating-linear-gradient(135deg,#ef444455 0 3px,transparent 3px 6px)"></i>วันที่มีห้องไม่ว่าง</span>
    </div>
    <div id="calendar"></div>
  </div>
</div>

<!-- ================= 2. STATS ================= -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['การจองเดือนนี้', (int)$stat['total'], 'bi-calendar2-week', '#6366f1', ($trend >= 0 ? '+' : '') . $trend . '% จากเดือนก่อน'],
      ['ระหว่างลงนามอนุมัติ', (int)$stat['pending'], 'bi-hourglass-split', '#f59e0b', '3 ขั้น'],
      ['ผอ. อนุมัติ · รอยืนยัน', (int)$stat['signed'], 'bi-pen', '#3b82f6', 'จอง'],
      ['อนุมัติแล้ว (ไม่ว่าง)', (int)$stat['approved'], 'bi-check2-circle', '#ef4444', 'วันนี้ ' . $todayCount . ' รายการ'],
      ['ห้องประชุมทั้งหมด', $roomCount, 'bi-door-open', '#10b981', 'ว่างวันนี้ ' . $statusCount['available'] . ' ห้อง'],
      ['ผู้ใช้งานในระบบ', $userCount, 'bi-people', '#8b5cf6', 'บัญชีที่ใช้งาน'],
  ];
  foreach ($cards as $i => $c): ?>
  <div class="col-6 col-md-4 col-xl-2 reveal" style="--i:<?= $i + 2 ?>">
    <div class="card-x stat-card" style="--c:<?= $c[3] ?>">
      <div class="stat-top"><span class="stat-icon"><i class="bi <?= $c[2] ?>"></i></span><span class="stat-trend"><?= e($c[4]) ?></span></div>
      <div class="stat-value" data-count="<?= $c[1] ?>">0</div>
      <div class="stat-label"><?= e($c[0]) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($myJobs): ?>
<div class="card-x mb-4 reveal">
  <div class="card-x-head"><h5><i class="bi bi-clipboard-check"></i>งานดูแลห้องประชุมที่ได้รับมอบหมาย</h5></div>
  <div class="table-responsive"><table class="table table-x table-hover"><thead><tr><th>วัน-เวลา</th><th>ห้อง</th><th>หัวข้อ</th><th>ผู้ขอใช้</th></tr></thead><tbody>
  <?php foreach ($myJobs as $j): ?><tr><td><?= e(time_range($j['start_datetime'], $j['end_datetime'])) ?></td><td><strong><?= e($j['room_name']) ?></strong><br><small class="text-muted"><?= e($j['building_name']) ?></small></td><td><?= e($j['title']) ?></td><td><?= e($j['requester_name']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<!-- ================= 3. ROOM STATUS ================= -->
<div class="card-x mb-4 reveal" style="--i:3">
  <div class="card-x-head">
    <h5><i class="bi bi-broadcast"></i>สถานะห้องประชุมวันนี้ <span class="status-dot ms-1" style="--c:#10b981"></span><small class="text-muted fw-normal">Live</small></h5>
    <div class="chip-tabs" id="roomFilter">
      <button class="chip active" data-f="all">ทั้งหมด <span class="count"><?= count($rooms) ?></span></button>
      <?php foreach (ROOM_STATUS as $k => $s): ?>
        <button class="chip" data-f="<?= $k ?>" style="--c:<?= $s['color'] ?>"><span class="swatch"></span><?= e($s['label']) ?> <span class="count"><?= $statusCount[$k] ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card-x-body">
    <?php if (!$rooms): ?>
      <div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-door-closed"></i></div><p class="text-muted">ยังไม่มีห้องประชุม</p></div>
    <?php endif; ?>
    <div class="row g-3">
      <?php foreach ($rooms as $r): $s = ROOM_STATUS[$r['eff']]; $nx = $nextBk[$r['id']] ?? null; ?>
      <div class="col-sm-6 col-lg-4 col-xxl-3" data-room-status="<?= $r['eff'] ?>">
        <div class="room-tile" style="--c:<?= $s['color'] ?>">
          <div class="rt-head">
            <div><h6><?= e($r['name']) ?></h6><small><i class="bi bi-building"></i> <?= e($r['building_name']) ?> · ชั้น <?= (int)$r['floor'] ?> · <i class="bi bi-people"></i> <?= (int)$r['capacity'] ?></small></div>
            <span class="d-inline-flex align-items-center gap-2"><span class="status-dot"></span><?= room_badge($r['eff']) ?></span>
          </div>
          <div class="rt-next">
            <?php if ($nx): ?><i class="bi bi-clock-history me-1"></i><strong>ถัดไป:</strong> <?= e(mb_strimwidth($nx['title'], 0, 34, '…')) ?><br><span class="text-muted"><?= e(time_range($nx['start_datetime'], $nx['end_datetime'])) ?></span>
            <?php else: ?><i class="bi bi-emoji-smile me-1"></i>ไม่มีการจองที่กำลังจะมาถึง<?php endif; ?>
          </div>
          <div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">อัตราการใช้เดือนนี้</span><strong><?= $r['util'] ?>% · <?= $r['hours'] ?> ชม.</strong></div>
            <div class="progress-thin"><div style="width:0" data-w="<?= $r['util'] ?>%"></div></div>
          </div>
          <?php if (has_role('admin')): ?>
          <div class="d-flex gap-2 mt-auto">
            <a class="btn btn-sm btn-soft flex-fill" href="<?= url('admin/room_view.php?id=' . $r['id']) ?>"><i class="bi bi-badge-3d me-1"></i>ดู 3D</a>
            <a class="btn btn-sm btn-ghost flex-fill" href="<?= url('admin/bookings.php?room_id=' . $r['id']) ?>"><i class="bi bi-list-ul me-1"></i>การจอง</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ================= 4. CHARTS ================= -->
<div class="row g-3 mb-4">
  <div class="col-xl-8 reveal" style="--i:4">
    <div class="card-x h-100">
      <div class="card-x-head"><h5><i class="bi bi-bar-chart-line"></i>ชั่วโมงการใช้ห้องประชุม เดือนนี้</h5><span class="text-muted small">เฉพาะรายการที่อนุมัติแล้ว</span></div>
      <div class="card-x-body"><div style="height:300px"><canvas id="chartHours"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-4 reveal" style="--i:5">
    <div class="card-x h-100">
      <div class="card-x-head"><h5><i class="bi bi-pie-chart"></i>สถานะการจอง เดือนนี้</h5></div>
      <div class="card-x-body"><div style="height:300px"><canvas id="chartStatus"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-8 reveal" style="--i:6">
    <div class="card-x h-100">
      <div class="card-x-head"><h5><i class="bi bi-graph-up-arrow"></i>แนวโน้มการจอง 6 เดือน</h5></div>
      <div class="card-x-body"><div style="height:280px"><canvas id="chartTrend"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-4 reveal" style="--i:7">
    <div class="card-x h-100">
      <div class="card-x-head"><h5><i class="bi bi-alarm"></i>กำลังจะมาถึง (7 วัน)</h5></div>
      <div class="card-x-body pt-2">
        <?php if (!$upcoming): ?><div class="empty-mini"><i class="bi bi-calendar-x"></i>ไม่มีการจอง</div><?php endif; ?>
        <ul class="feed">
          <?php foreach ($upcoming as $u): $bs = BOOKING_STATUS[$u['status']]; ?>
            <li role="button" data-booking="<?= $u['id'] ?>">
              <span class="fi" style="--c:<?= $bs['color'] ?>"><i class="bi <?= $bs['icon'] ?>"></i></span>
              <div class="flex-grow-1"><strong><?= e($u['title']) ?></strong><p><?= e($u['room_name']) ?> · <?= e(time_range($u['start_datetime'], $u['end_datetime'])) ?></p></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php if ($activity): ?>
<div class="card-x reveal mb-2">
  <div class="card-x-head"><h5><i class="bi bi-activity"></i>กิจกรรมล่าสุดในระบบ</h5><a href="<?= url('admin/logs.php') ?>" class="btn btn-sm btn-ghost">ดูทั้งหมด</a></div>
  <div class="card-x-body pt-2">
    <ul class="feed">
      <?php foreach ($activity as $a): $la = LOG_ACTIONS[$a['action']] ?? ['อื่นๆ', '#64748b', 'bi-dot']; ?>
        <li><span class="fi" style="--c:<?= $la[1] ?>"><i class="bi <?= $la[2] ?>"></i></span>
          <div class="flex-grow-1"><strong><?= e($a['username']) ?></strong> <span class="text-muted small">· <?= e($la[0]) ?></span><p><?= e($a['detail']) ?></p></div>
          <small class="text-muted text-nowrap"><?= e(time_ago($a['created_at'])) ?></small></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<!-- booking modal -->
<div class="modal fade" id="bookingModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><span class="modal-icon"><i class="bi bi-calendar-event"></i></span>รายละเอียดการจอง</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="bookingBody"><div class="text-center py-5"><div class="v3d-spinner mx-auto"></div></div></div>
  <div class="modal-footer">
    <?php if (has_role('admin')): ?><a class="btn btn-primary" id="bookingGo" href="#"><i class="bi bi-box-arrow-up-right me-1"></i>จัดการการจองนี้</a><?php endif; ?>
    <button class="btn btn-ghost" data-bs-dismiss="modal">ปิด</button>
  </div>
</div></div></div>

<?php
$chartData = [
    'rooms' => array_map(fn($r) => mb_strimwidth($r['name'], 0, 22, '…'), $rooms),
    'hours' => array_map(fn($r) => $r['hours'], $rooms),
    'util' => array_map(fn($r) => $r['util'], $rooms),
    'status' => array_map(fn($s) => ['label' => BOOKING_STATUS[$s['status']]['short'], 'c' => (int)$s['c'], 'color' => BOOKING_STATUS[$s['status']]['color']], $byStatus),
    'trend' => $trendRows,
];
$extraScripts = '
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/th.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>window.DASH = ' . json_encode($chartData, JSON_UNESCAPED_UNICODE) . '; window.CAN_MANAGE = ' . (has_role('admin') ? 'true' : 'false') . ';</script>
<script src="' . asset('js/dashboard.js') . '"></script>';
require __DIR__ . '/../includes/footer.php';
