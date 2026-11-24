<?php
/** พิมพ์ฟอร์มขอใช้สถานที่ — เลือกรายการที่อนุมัติแล้ว (หลายรายการ) แล้วเปิดหน้าพิมพ์ */
require __DIR__ . '/../includes/bootstrap.php';
page_guard('printform');

$month = preg_match('/^\d{4}-\d{2}$/', (string)qs('month')) ? qs('month') : '';
$q = trim((string)qs('q'));
$where = ["b.status='approved'"]; $params = [];
if ($month) { $where[] = 'b.start_datetime BETWEEN ? AND ?'; $params[] = "$month-01 00:00:00"; $params[] = date('Y-m-t 23:59:59', strtotime("$month-01")); }
if ($q !== '') { $where[] = '(b.booking_no LIKE ? OR b.title LIKE ? OR b.requester_name LIKE ? OR r.name LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
$rows = db_all('SELECT b.*, r.name room_name, bd.name building_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id
  WHERE ' . implode(' AND ', $where) . ' ORDER BY b.start_datetime DESC LIMIT 300', $params);
$months = db_all("SELECT DATE_FORMAT(start_datetime,'%Y-%m') m, COUNT(*) c FROM bookings WHERE status='approved' GROUP BY m ORDER BY m DESC");

$pageTitle = 'พิมพ์ฟอร์มขอใช้สถานที่';
$activeMenu = 'printform';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-printer"></i>Print Forms</span><h2>พิมพ์ฟอร์มขอใช้สถานที่</h2>
    <p>เลือกรายการที่<strong>อนุมัติแล้ว</strong> (เลือกได้หลายรายการ) แล้วกด "พิมพ์ฟอร์ม" — 1 รายการต่อ 1 หน้า A4 พร้อมลายมือชื่อผู้ลงนามทุกขั้น</p></div>
</div>

<form class="d-flex flex-wrap gap-2 align-items-center mb-3 reveal" style="--i:1">
  <select class="form-select" name="month" style="width:auto" onchange="this.form.submit()">
    <option value="">ทุกเดือน</option>
    <?php foreach ($months as $m): ?><option value="<?= e($m['m']) ?>" <?= $month === $m['m'] ? 'selected' : '' ?>><?= e(thai_date($m['m'] . '-01', 'month')) ?> (<?= (int)$m['c'] ?>)</option><?php endforeach; ?>
  </select>
  <div class="search-box"><i class="bi bi-search"></i><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="ค้นหาเลขที่ / ชื่อ / ห้อง..."></div>
  <button class="btn btn-soft">ค้นหา</button>
  <?php if ($month || $q !== ''): ?><a class="btn btn-ghost" href="?">ล้างตัวกรอง</a><?php endif; ?>
</form>

<form method="get" action="<?= url('admin/print_form.php') ?>" target="_blank" id="printPick" class="reveal" style="--i:2">
  <div class="card-x">
    <div class="card-x-head">
      <h5><i class="bi bi-check2-square"></i>รายการที่อนุมัติแล้ว (<?= count($rows) ?>)</h5>
      <div class="d-flex gap-2 align-items-center">
        <span class="small text-muted" id="pickCount">เลือก 0 รายการ</span>
        <button class="btn btn-primary" id="btnPrint" disabled><i class="bi bi-printer me-1"></i>พิมพ์ฟอร์ม</button>
      </div>
    </div>
    <?php if ($rows): ?>
    <div class="table-responsive"><table class="table table-x table-hover align-middle mb-0">
      <thead><tr><th style="width:40px"><input type="checkbox" class="form-check-input" id="pickAll" aria-label="เลือกทั้งหมด"></th><th>เลขที่</th><th>วัน-เวลา</th><th>ห้อง</th><th>ใช้สถานที่เพื่อ</th><th>ผู้ขอใช้</th><th class="text-end">พิมพ์</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $b): ?>
        <tr>
          <td><input type="checkbox" class="form-check-input pick" name="id[]" value="<?= $b['id'] ?>" aria-label="เลือก <?= e($b['booking_no']) ?>"></td>
          <td><span class="code-pill"><?= e($b['booking_no']) ?></span></td>
          <td class="small"><?= booking_when_html($b['start_datetime'], $b['end_datetime']) ?></td>
          <td><strong><?= e($b['room_name']) ?></strong><br><small class="text-muted"><?= e($b['building_name']) ?></small></td>
          <td><?= e($b['title']) ?></td>
          <td><?= e($b['requester_name']) ?><?= $b['department'] ? '<br><small class="text-muted">' . e($b['department']) . '</small>' : '' ?></td>
          <td class="text-end"><a class="btn-icon sm" title="พิมพ์รายการนี้" target="_blank" href="<?= url('admin/print_form.php?id[]=' . $b['id']) ?>"><i class="bi bi-printer"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?>
      <div class="card-x-body text-muted"><i class="bi bi-inbox me-1"></i>ไม่มีรายการที่อนุมัติแล้ว<?= $month || $q !== '' ? 'ตามตัวกรอง' : '' ?></div>
    <?php endif; ?>
  </div>
</form>
<?php
$extraScripts = '<script>
(() => {
  const picks = [...document.querySelectorAll(".pick")], all = document.getElementById("pickAll"), btn = document.getElementById("btnPrint"), cnt = document.getElementById("pickCount");
  const sync = () => { const n = picks.filter(p => p.checked).length; btn.disabled = !n; cnt.textContent = "เลือก " + n + " รายการ"; if (all) { all.checked = n && n === picks.length; all.indeterminate = n > 0 && n < picks.length; } };
  picks.forEach(p => p.addEventListener("change", sync));
  all?.addEventListener("change", () => { picks.forEach(p => p.checked = all.checked); sync(); });
  document.querySelectorAll("#printPick tbody tr").forEach(tr => tr.addEventListener("click", e => { if (e.target.closest("a,input,button")) return; const c = tr.querySelector(".pick"); c.checked = !c.checked; sync(); }));
  sync();
})();
</script>';
require __DIR__ . '/../includes/footer.php';
