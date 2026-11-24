<?php
/** สรุปยอดการใช้ห้องรายเดือน — ต่อห้อง: จำนวนครั้ง วันใช้งาน ชั่วโมง ผู้เข้าร่วม อัตราการใช้ · พิมพ์ / ส่งออก CSV */
require __DIR__ . '/../includes/bootstrap.php';
page_guard('report');
$me = current_user();

$month = preg_match('/^\d{4}-\d{2}$/', (string)qs('month')) ? qs('month') : date('Y-m');
$bid = (int)qs('building_id');
$mStart = "$month-01 00:00:00";
$mEnd = date('Y-m-t 23:59:59', strtotime($mStart));
$monthLabel = thai_date($mStart, 'month');

// วันทำการ (จ.–ศ.) × 8 ชม. = ชั่วโมงที่ห้องพร้อมให้บริการ
$workDays = 0;
for ($d = strtotime($mStart); $d <= strtotime($mEnd); $d += 86400) if ((int)date('N', $d) < 6) $workDays++;
$capHours = $workDays * 8;

$rooms = db_all('SELECT r.id, r.name, r.code, r.capacity, r.floor, b.name building_name FROM rooms r JOIN buildings b ON b.id=r.building_id'
    . ($bid ? ' WHERE r.building_id=?' : '') . ' ORDER BY b.name, r.floor, r.name', $bid ? [$bid] : []);
$all = db_all('SELECT bk.*, r.name room_name, bd.name building_name FROM bookings bk JOIN rooms r ON r.id=bk.room_id JOIN buildings bd ON bd.id=r.building_id
  WHERE bk.start_datetime BETWEEN ? AND ?' . ($bid ? ' AND r.building_id=?' : '') . ' ORDER BY bk.start_datetime', array_merge([$mStart, $mEnd], $bid ? [$bid] : []));

$stat = ['approved' => 0, 'waiting' => 0, 'rejected' => 0, 'cancelled' => 0];
$per = [];
foreach ($rooms as $r) $per[$r['id']] = $r + ['count' => 0, 'days' => 0, 'hours' => 0.0, 'people' => 0, 'equip' => 0];
$approved = [];
foreach ($all as $b) {
    $k = $b['status'] === 'approved' ? 'approved' : (in_array($b['status'], ['rejected', 'cancelled'], true) ? $b['status'] : 'waiting');
    $stat[$k]++;
    if ($b['status'] !== 'approved' || !isset($per[$b['room_id']])) continue;
    $approved[] = $b;
    $p = &$per[$b['room_id']];
    $p['count']++;
    $p['days'] += booking_days($b['start_datetime'], $b['end_datetime']);
    $p['hours'] += booking_hours($b['start_datetime'], $b['end_datetime']);
    $p['people'] += (int)$b['attendees'];
    if (booking_equipment($b['equip'] ?? null)['need']) $p['equip']++;
    unset($p);
}
foreach ($per as &$p) $p['util'] = $capHours ? min(100, round($p['hours'] / $capHours * 100, 1)) : 0;
unset($p);
$tot = ['count' => array_sum(array_column($per, 'count')), 'days' => array_sum(array_column($per, 'days')), 'hours' => array_sum(array_column($per, 'hours')), 'people' => array_sum(array_column($per, 'people'))];
$used = count(array_filter($per, fn($p) => $p['count'] > 0));
$fmtH = fn(float $h) => rtrim(rtrim(number_format($h, 1), '0'), '.');

// ---------- ส่งออก CSV (Excel เปิดภาษาไทยได้ด้วย BOM) ----------
if (qs('export') === 'csv') {
    log_action('booking', "ส่งออกสรุปการใช้ห้อง $monthLabel (CSV)");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="room-usage-' . $month . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ["สรุปยอดการใช้ห้องประชุม $monthLabel", ORG_NAME]);
    fputcsv($out, ['ห้อง', 'รหัส', 'อาคาร', 'ชั้น', 'ความจุ', 'จำนวนครั้ง', 'วันใช้งาน', 'ชั่วโมงรวม', 'ผู้เข้าร่วมรวม', 'ขออุปกรณ์ (ครั้ง)', 'อัตราการใช้ (%)']);
    foreach ($per as $p) fputcsv($out, [$p['name'], $p['code'], $p['building_name'], $p['floor'], $p['capacity'], $p['count'], $p['days'], $fmtH($p['hours']), $p['people'], $p['equip'], $p['util']]);
    fputcsv($out, ['รวม', '', '', '', '', $tot['count'], $tot['days'], $fmtH($tot['hours']), $tot['people'], '', $capHours && $per ? round($tot['hours'] / ($capHours * count($per)) * 100, 1) : 0]);
    fputcsv($out, []);
    fputcsv($out, ['รายการที่อนุมัติแล้ว']);
    fputcsv($out, ['เลขที่', 'เริ่ม', 'สิ้นสุด', 'ห้อง', 'ใช้สถานที่เพื่อ', 'ผู้ขอใช้', 'หน่วยงาน', 'ผู้เข้าร่วม', 'ชั่วโมง', 'อุปกรณ์']);
    foreach ($approved as $b) fputcsv($out, [$b['booking_no'], $b['start_datetime'], $b['end_datetime'], $b['room_name'], $b['title'], $b['requester_name'], $b['department'], $b['attendees'], $fmtH(booking_hours($b['start_datetime'], $b['end_datetime'])), implode(', ', booking_equipment_lines($b['equip'] ?? null))]);
    fclose($out);
    exit;
}

$buildings = db_all('SELECT id, name FROM buildings ORDER BY name');
$monthsOpt = [];
for ($i = -12; $i <= 3; $i++) { $m = date('Y-m', strtotime(date('Y-m-01') . " $i month")); $monthsOpt[$m] = thai_date("$m-01", 'month'); }
if (!isset($monthsOpt[$month])) $monthsOpt[$month] = $monthLabel;
krsort($monthsOpt);
$prev = date('Y-m', strtotime("$mStart -1 month")); $next = date('Y-m', strtotime("$mStart +1 month"));
$q = fn(array $p) => '?' . http_build_query(array_filter(['month' => $month, 'building_id' => $bid ?: null] + $p));

$pageTitle = 'สรุปยอดการใช้ห้องรายเดือน';
$activeMenu = 'report';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-bar-chart-line"></i>Monthly Report</span><h2>สรุปยอดการใช้ห้องประชุม <span class="text-grad"><?= e($monthLabel) ?></span></h2>
    <p>นับเฉพาะรายการที่<strong>อนุมัติแล้ว</strong> ตามวันที่เริ่มใช้ · วันทำการ <?= $workDays ?> วัน (<?= $capHours ?> ชม./ห้อง)<?= $bid ? ' · ' . e(array_column($buildings, 'name', 'id')[$bid] ?? '') : '' ?></p></div>
  <form class="actions d-flex flex-wrap gap-2 align-items-center no-print">
    <a class="btn-icon" href="<?= $q(['month' => $prev]) ?>" title="เดือนก่อน"><i class="bi bi-chevron-left"></i></a>
    <select class="form-select" name="month" style="width:auto" onchange="this.form.submit()"><?php foreach ($monthsOpt as $m => $l): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <a class="btn-icon" href="<?= $q(['month' => $next]) ?>" title="เดือนถัดไป"><i class="bi bi-chevron-right"></i></a>
    <select class="form-select" name="building_id" style="width:auto" onchange="this.form.submit()"><option value="">ทุกอาคาร</option>
      <?php foreach ($buildings as $b): ?><option value="<?= $b['id'] ?>" <?= $bid === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select>
    <a class="btn btn-soft" href="<?= $q(['export' => 'csv']) ?>"><i class="bi bi-filetype-csv me-1"></i>CSV / Excel</a>
    <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
  </form>
</div>
<div class="print-only report-print-head"><strong>สรุปยอดการใช้ห้องประชุม <?= e($monthLabel) ?></strong> · <?= e(ORG_NAME) ?><?= $bid ? ' · ' . e(array_column($buildings, 'name', 'id')[$bid] ?? '') : '' ?></div>

<div class="task-stats usage-stats reveal" style="--i:1">
  <div class="task-stat" style="--c:#6366f1"><i class="bi bi-calendar2-check"></i><div><strong><?= $tot['count'] ?></strong><span>ครั้งที่ใช้ห้อง (อนุมัติ)</span></div></div>
  <div class="task-stat" style="--c:#0ea5e9"><i class="bi bi-clock-history"></i><div><strong><?= $fmtH($tot['hours']) ?></strong><span>ชั่วโมงใช้งานรวม</span></div></div>
  <div class="task-stat" style="--c:#10b981"><i class="bi bi-people"></i><div><strong><?= number_format($tot['people']) ?></strong><span>ผู้เข้าร่วมรวม (คน)</span></div></div>
  <div class="task-stat" style="--c:#f59e0b"><i class="bi bi-door-open"></i><div><strong><?= $used ?>/<?= count($per) ?></strong><span>ห้องที่มีการใช้</span></div></div>
</div>
<div class="report-status reveal" style="--i:2">
  <span><i class="bi bi-check-circle text-success"></i>อนุมัติ <b><?= $stat['approved'] ?></b></span>
  <span><i class="bi bi-hourglass-split text-warning"></i>รอลงนาม/รอยืนยัน <b><?= $stat['waiting'] ?></b></span>
  <span><i class="bi bi-x-circle text-danger"></i>ไม่อนุมัติ <b><?= $stat['rejected'] ?></b></span>
  <span><i class="bi bi-slash-circle text-secondary"></i>ยกเลิก <b><?= $stat['cancelled'] ?></b></span>
  <span class="text-muted">คำขอทั้งหมดในเดือน <b><?= count($all) ?></b></span>
</div>

<div class="row g-4 mt-1">
  <div class="col-xl-7">
    <div class="card-x h-100 reveal" style="--i:3">
      <div class="card-x-head"><h5><i class="bi bi-table"></i>สรุปรายห้อง</h5></div>
      <div class="table-responsive"><table class="table table-x align-middle mb-0 report-table">
        <thead><tr><th>ห้อง</th><th class="text-end">ครั้ง</th><th class="text-end">วัน</th><th class="text-end">ชั่วโมง</th><th class="text-end">ผู้เข้าร่วม</th><th style="min-width:130px">อัตราการใช้</th></tr></thead>
        <tbody>
        <?php foreach ($per as $p): ?>
          <tr class="<?= $p['count'] ? '' : 'text-muted' ?>">
            <td><strong><?= e($p['name']) ?></strong><br><small class="text-muted"><?= e($p['building_name']) ?> · <?= (int)$p['capacity'] ?> ที่นั่ง</small></td>
            <td class="text-end"><?= $p['count'] ?></td>
            <td class="text-end"><?= $p['days'] ?></td>
            <td class="text-end"><?= $fmtH($p['hours']) ?></td>
            <td class="text-end"><?= number_format($p['people']) ?></td>
            <td><div class="util"><div class="util-bar"><span style="width:<?= $p['util'] ?>%"></span></div><small><?= $p['util'] ?>%</small></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>รวม <?= count($per) ?> ห้อง</th><th class="text-end"><?= $tot['count'] ?></th><th class="text-end"><?= $tot['days'] ?></th><th class="text-end"><?= $fmtH($tot['hours']) ?></th><th class="text-end"><?= number_format($tot['people']) ?></th>
          <th><?= $capHours && $per ? round($tot['hours'] / ($capHours * count($per)) * 100, 1) : 0 ?>% <small class="text-muted fw-normal">เฉลี่ย</small></th></tr></tfoot>
      </table></div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card-x h-100 reveal" style="--i:4">
      <div class="card-x-head"><h5><i class="bi bi-bar-chart"></i>ชั่วโมงใช้งานรายห้อง</h5></div>
      <div class="card-x-body"><div style="height:<?= max(220, count($per) * 38) ?>px"><canvas id="chartRooms" aria-label="กราฟชั่วโมงใช้งานรายห้อง" role="img"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card-x reveal mt-4" style="--i:5">
  <div class="card-x-head"><h5><i class="bi bi-list-ul"></i>รายการที่อนุมัติแล้ว (<?= count($approved) ?>)</h5></div>
  <?php if ($approved): ?>
  <div class="table-responsive"><table class="table table-x table-hover align-middle mb-0">
    <thead><tr><th>เลขที่</th><th>วัน-เวลา</th><th>ห้อง</th><th>ใช้สถานที่เพื่อ</th><th>ผู้ขอใช้</th><th class="text-end">คน</th><th class="text-end">ชม.</th></tr></thead>
    <tbody><?php foreach ($approved as $b): ?>
      <tr><td><span class="code-pill"><?= e($b['booking_no']) ?></span></td><td class="small"><?= booking_when_html($b['start_datetime'], $b['end_datetime']) ?></td>
        <td><?= e($b['room_name']) ?></td><td><?= e($b['title']) ?></td><td><?= e($b['requester_name']) ?><?= $b['department'] ? '<br><small class="text-muted">' . e($b['department']) . '</small>' : '' ?></td>
        <td class="text-end"><?= (int)$b['attendees'] ?></td><td class="text-end"><?= $fmtH(booking_hours($b['start_datetime'], $b['end_datetime'])) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php else: ?><div class="card-x-body text-muted"><i class="bi bi-inbox me-1"></i>ยังไม่มีรายการที่อนุมัติในเดือนนี้</div><?php endif; ?>
</div>
<div class="print-only small text-muted mt-3">พิมพ์โดย <?= e(full_name($me)) ?> · <?= e(thai_date(date('Y-m-d H:i:s'), 'datetime')) ?></div>
<?php
$chart = ['labels' => array_values(array_map(fn($p) => $p['name'], $per)), 'hours' => array_values(array_map(fn($p) => round($p['hours'], 1), $per)), 'count' => array_values(array_column($per, 'count'))];
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const D = ' . json_encode($chart, JSON_UNESCAPED_UNICODE) . ';
  const el = document.getElementById("chartRooms"); if (!el || !window.Chart) return;
  const css = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  Chart.defaults.font.family = "\'IBM Plex Sans Thai\', sans-serif";
  Chart.defaults.color = css("--muted") || "#64748b";
  new Chart(el, { type: "bar", data: { labels: D.labels, datasets: [{ label: "ชั่วโมง", data: D.hours, backgroundColor: "#6366f1", borderRadius: 6, maxBarThickness: 22 }] },
    options: { indexAxis: "y", maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `${c.parsed.x} ชม. · ${D.count[c.dataIndex]} ครั้ง` } } },
      scales: { x: { beginAtZero: true, grid: { color: css("--line") || "#e5e7eb" }, title: { display: true, text: "ชั่วโมง" } }, y: { grid: { display: false } } } } });
})();
</script>';
require __DIR__ . '/../includes/footer.php';
