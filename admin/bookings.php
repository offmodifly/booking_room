<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/booking_detail.php';
page_guard('bookings');
$me = current_user();

if (is_post()) {
    csrf_check();
    $action = input('action');
    $id = (int)input('id');
    try {
        if ($action === 'create') {
            $room = db_one('SELECT * FROM rooms WHERE id=?', [(int)input('room_id')]);
            if (!$room) throw new RuntimeException('กรุณาเลือกห้องประชุม');
            $start = date('Y-m-d H:i:s', strtotime(input('date') . ' ' . input('start_time')));
            $end = date('Y-m-d H:i:s', strtotime(input('date') . ' ' . input('end_time')));
            if (!input('date') || $end <= $start) throw new RuntimeException('ช่วงเวลาไม่ถูกต้อง (เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม)');
            if (input('title') === '') throw new RuntimeException('กรุณาระบุว่ามีความประสงค์ขอใช้สถานที่เพื่ออะไร');
            $equipIn = is_array($_POST['equip'] ?? null) ? $_POST['equip'] : [];
            $equip = booking_equipment($equipIn);
            if (filter_var($equipIn['need'] ?? false, FILTER_VALIDATE_BOOLEAN) && !$equip['need']) throw new RuntimeException('เลือก "ต้องการให้จัด" แล้ว กรุณาเลือกอุปกรณ์อย่างน้อย 1 รายการ');
            if ($room['status'] === 'unavailable') throw new RuntimeException('ห้องนี้ถูกตั้งสถานะ "ไม่ว่าง" (ปิดใช้งาน)');
            if ($c = booking_conflicts((int)$room['id'], $start, $end)) throw new RuntimeException('ช่วงเวลาชนกับการจอง: ' . e($c[0]['title']) . ' (' . e(time_range($c[0]['start_datetime'], $c[0]['end_datetime'])) . ')');
            $req = (int)input('user_id') ? db_one('SELECT * FROM users WHERE id=?', [(int)input('user_id')]) : null;
            $reqName = input('requester_name') ?: ($req ? full_name($req) : '');
            if ($reqName === '') throw new RuntimeException('กรุณาระบุผู้ขอใช้ห้อง');
            $png = signature_png_from_dataurl((string)input('signature'));
            $pdo = db();
            $pdo->beginTransaction();
            db_exec('INSERT INTO bookings (room_id,user_id,requester_name,department,title,purpose,equip,attendees,start_datetime,end_datetime,contact_phone,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $room['id'], $req['id'] ?? null, mb_substr($reqName, 0, 150), mb_substr(input('department'), 0, 150), mb_substr(input('title'), 0, 255), input('purpose'),
                $equip['need'] ? json_encode($equip, JSON_UNESCAPED_UNICODE) : null,
                max(0, (int)input('attendees')), $start, $end, mb_substr(input('contact_phone'), 0, 30) ?: null, 'pending', $me['id'],
            ]);
            $nid = (int)db()->lastInsertId();
            $no = 'BK' . date('ym', strtotime($start)) . '-' . str_pad((string)$nid, 4, '0', STR_PAD_LEFT);
            db_exec('UPDATE bookings SET booking_no=? WHERE id=?', [$no, $nid]);
            signature_add(db_one('SELECT * FROM bookings WHERE id=?', [$nid]), 'requester',
                ['user_id' => $req['id'] ?? null, 'name' => $reqName, 'position' => $req ? ($req['position'] ?: '') : mb_substr(input('department'), 0, 150)], 'sign', null, $png);
            $pdo->commit();
            log_action('booking', "สร้างการจอง $no · {$room['name']} · " . time_range($start, $end));
            flash('success', "บันทึกการจอง $no แล้ว · สถานะ \"จอง\" ส่งหัวหน้างานอาคารพิจารณา");
        } elseif ($action === 'approve' || $action === 'assign') {
            $b = db_one('SELECT * FROM bookings WHERE id=?', [$id]);
            if (!$b) throw new RuntimeException('ไม่พบการจอง');
            $staff = array_unique(array_map('intval', (array)($_POST['staff'] ?? [])));
            if (!$staff) throw new RuntimeException('กรุณาเลือกเจ้าหน้าที่ประจำห้องประชุมอย่างน้อย 1 คน');
            $valid = db_all('SELECT id, prefix, first_name, last_name FROM users WHERE role="room_staff" AND is_active=1 AND id IN (' . implode(',', array_fill(0, count($staff), '?')) . ')', $staff);
            if (count($valid) !== count($staff)) throw new RuntimeException('รายชื่อเจ้าหน้าที่ไม่ถูกต้อง');
            if ($action === 'approve') {
                if ($b['status'] !== 'signed') throw new RuntimeException('อนุมัติได้เฉพาะรายการที่ผ่านการลงนามครบทุกขั้น (ผู้ขอ → หัวหน้างานอาคาร → รอง ผอ. → ผอ.) แล้วเท่านั้น');
                $chain = signature_chain($b);
                if (!$chain['complete']) throw new RuntimeException('ลายเซ็นดิจิทัลไม่ครบหรือตรวจสอบไม่ผ่าน (ข้อมูลอาจถูกแก้ไขหลังลงนาม)');
                if ($c = booking_conflicts((int)$b['room_id'], $b['start_datetime'], $b['end_datetime'], $id, ['approved'])) throw new RuntimeException('ห้องถูกอนุมัติให้ ' . e($c[0]['title']) . ' ในช่วงเวลานี้แล้ว');
            } elseif ($b['status'] !== 'approved') throw new RuntimeException('มอบหมายเจ้าหน้าที่ได้เฉพาะรายการที่อนุมัติแล้ว');
            $pdo = db();
            $pdo->beginTransaction();
            if ($action === 'approve') db_exec("UPDATE bookings SET status='approved', approved_by=?, approved_at=NOW(), admin_note=? WHERE id=?", [$me['id'], mb_substr(input('admin_note'), 0, 255) ?: null, $id]);
            db_exec('DELETE FROM booking_staff WHERE booking_id=?', [$id]);
            foreach ($staff as $sid) db_exec('INSERT INTO booking_staff (booking_id,user_id,assigned_by) VALUES (?,?,?)', [$id, $sid, $me['id']]);
            $pdo->commit();
            $names = implode(', ', array_map('full_name', $valid));
            log_action($action === 'approve' ? 'approve' : 'booking', ($action === 'approve' ? "อนุมัติการจอง {$b['booking_no']} (จอง → ไม่ว่าง)" : "เปลี่ยนเจ้าหน้าที่ {$b['booking_no']}") . " · มอบหมาย: $names");
            flash('success', $action === 'approve' ? "อนุมัติ {$b['booking_no']} แล้ว · ห้องเปลี่ยนสถานะเป็น \"ไม่ว่าง\" · มอบหมาย $names" : 'บันทึกเจ้าหน้าที่ประจำห้องเรียบร้อย');
        } elseif ($action === 'cancel') {
            $b = db_one('SELECT * FROM bookings WHERE id=?', [$id]);
            if (!$b || in_array($b['status'], ['cancelled', 'rejected'], true)) throw new RuntimeException('ไม่สามารถยกเลิกรายการนี้ได้');
            db_exec("UPDATE bookings SET status='cancelled', admin_note=? WHERE id=?", [mb_substr(input('admin_note'), 0, 255) ?: 'ยกเลิกโดยผู้ดูแลระบบ', $id]);
            log_action('booking', "ยกเลิกการจอง {$b['booking_no']}");
            flash('success', "ยกเลิกการจอง {$b['booking_no']} แล้ว");
        } elseif ($action === 'delete') {
            $b = db_one('SELECT * FROM bookings WHERE id=?', [$id]);
            if (!$b) throw new RuntimeException('ไม่พบการจอง');
            if (!in_array($b['status'], ['cancelled', 'rejected'], true)) throw new RuntimeException('ลบได้เฉพาะรายการที่ยกเลิก/ไม่อนุมัติ');
            foreach (booking_signatures((int)$b['id']) as $sg) delete_upload($sg['file']);
            db_exec('DELETE FROM bookings WHERE id=?', [$id]);
            log_action('booking', "ลบการจอง {$b['booking_no']}");
            flash('success', 'ลบรายการเรียบร้อย');
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', $e->getMessage());
    }
    $back = array_intersect_key($_GET, array_flip(['status', 'room_id', 'q', 'month']));
    redirect('admin/bookings.php' . ($back ? '?' . http_build_query($back) : ''));
}

// ---------------- list
$status = qs('status');
$roomId = (int)qs('room_id');
$q = qs('q');
$month = qs('month');
$where = ['1=1']; $params = [];
if ($status && isset(BOOKING_STATUS[$status])) { $where[] = 'b.status=?'; $params[] = $status; }
if ($roomId) { $where[] = 'b.room_id=?'; $params[] = $roomId; }
if ($q !== '') { $where[] = '(b.title LIKE ? OR b.requester_name LIKE ? OR b.booking_no LIKE ? OR b.department LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
if (preg_match('/^\d{4}-\d{2}$/', $month)) { $where[] = 'DATE_FORMAT(b.start_datetime,"%Y-%m")=?'; $params[] = $month; }
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM bookings b WHERE $w", $params);
$pg = paginate($total, (int)qs('page', 1), 15);
$rows = db_all("SELECT b.*, r.name room_name, bd.name building_name,
   (SELECT GROUP_CONCAT(CONCAT(u.prefix,u.first_name,' ',u.last_name) SEPARATOR ', ') FROM booking_staff s JOIN users u ON u.id=s.user_id WHERE s.booking_id=b.id) staff_names,
   (SELECT GROUP_CONCAT(s.user_id) FROM booking_staff s WHERE s.booking_id=b.id) staff_ids
  FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id
  WHERE $w ORDER BY FIELD(b.status,'signed','pending','head_approved','deputy_approved','approved','rejected','cancelled'), b.start_datetime DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$counts = array_column(db_all('SELECT status, COUNT(*) c FROM bookings GROUP BY status'), 'c', 'status');
$rooms = db_all('SELECT r.id, r.name, r.capacity, r.status, b.name building_name FROM rooms r JOIN buildings b ON b.id=r.building_id ORDER BY b.name, r.name');
$staffUsers = db_all("SELECT * FROM users WHERE role='room_staff' AND is_active=1 ORDER BY first_name");
$requesters = db_all('SELECT id, prefix, first_name, last_name, position, phone FROM users WHERE is_active=1 ORDER BY first_name');
$qsBase = array_intersect_key($_GET, array_flip(['room_id', 'q', 'month']));

$pageTitle = 'รายการจอง / อนุมัติ';
$activeMenu = 'bookings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-calendar2-check"></i>Booking Workflow</span><h2>รายการจองและการอนุมัติ</h2>
    <p>ขั้นตอน: ผู้ขอลงนาม (<strong>จอง</strong>) → หัวหน้างานอาคาร → รองผู้อำนวยการฝ่ายบริหารทรัพยากร → ผู้อำนวยการ → Admin ยืนยัน (เปลี่ยนเป็น <strong>ไม่ว่าง</strong>) + มอบหมายเจ้าหน้าที่ประจำห้อง</p></div>
  <div class="actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newModal"><i class="bi bi-calendar-plus me-1"></i>เพิ่มการจอง</button></div>
</div>

<div class="chip-tabs mb-3 reveal" style="--i:1">
  <a class="chip <?= !$status ? 'active' : '' ?>" href="?<?= http_build_query($qsBase) ?>">ทั้งหมด <span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach (BOOKING_STATUS as $k => $s): ?>
    <a class="chip <?= $status === $k ? 'active' : '' ?>" style="--c:<?= $s['color'] ?>" href="?<?= http_build_query($qsBase + ['status' => $k]) ?>"><i class="bi <?= $s['icon'] ?>"></i><?= e($s['short']) ?> <span class="count"><?= (int)($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<div class="card-x reveal" style="--i:2">
  <form class="card-x-head" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="d-flex flex-wrap gap-2 flex-grow-1">
      <div class="search-box flex-grow-1" style="max-width:340px"><i class="bi bi-search"></i><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="ค้นหาเลขที่ หัวข้อ ผู้จอง หน่วยงาน"></div>
      <select class="form-select form-select-sm" name="room_id" style="width:auto"><option value="">ทุกห้อง</option><?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>" <?= $roomId === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
      <input type="month" class="form-control form-control-sm" name="month" value="<?= e($month) ?>" style="width:auto">
      <button class="btn btn-sm btn-ghost"><i class="bi bi-funnel"></i> กรอง</button>
      <?php if ($q || $roomId || $month): ?><a class="btn btn-sm btn-ghost" href="?<?= $status ? 'status=' . e($status) : '' ?>"><i class="bi bi-x"></i></a><?php endif; ?>
    </div>
    <span class="text-muted small"><?= number_format($total) ?> รายการ</span>
  </form>
  <div class="table-responsive">
    <table class="table table-x table-hover">
      <thead><tr><th>เลขที่</th><th>หัวข้อ / ผู้ขอใช้</th><th>ห้องประชุม</th><th>วัน-เวลา</th><th>สถานะ</th><th>เจ้าหน้าที่ประจำห้อง</th><th class="text-end">จัดการ</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-mini"><i class="bi bi-inbox"></i>ไม่พบรายการ</div></td></tr><?php endif; ?>
      <?php foreach ($rows as $b): ?>
        <tr>
          <td><span class="code-pill"><?= e($b['booking_no']) ?></span></td>
          <td style="min-width:220px"><strong class="d-block"><?= e($b['title']) ?></strong><small class="text-muted"><?= e($b['requester_name']) ?><?= $b['department'] ? ' · ' . e($b['department']) : '' ?><?= $b['contact_phone'] ? ' · <i class="bi bi-telephone"></i> ' . e($b['contact_phone']) : '' ?></small><?= !$b['user_id'] && !$b['created_by'] ? ' <span class="badge-soft" style="--c:#0ea5e9;font-size:.68rem"><i class="bi bi-globe2"></i>ไม่เป็นสมาชิก</span>' : '' ?></td>
          <td><?= e($b['room_name']) ?><br><small class="text-muted"><?= e($b['building_name']) ?></small></td>
          <td class="small text-nowrap"><?= e(thai_date($b['start_datetime'])) ?><br><span class="text-muted"><?= date('H:i', strtotime($b['start_datetime'])) ?>–<?= date('H:i', strtotime($b['end_datetime'])) ?> น.</span></td>
          <td><?= booking_badge($b['status']) ?></td>
          <td class="small"><?= $b['staff_names'] ? '<i class="bi bi-person-check text-success me-1"></i>' . e($b['staff_names']) : '<span class="text-muted">-</span>' ?></td>
          <td><div class="table-actions">
            <button class="btn-icon sm" title="รายละเอียด" data-view="<?= $b['id'] ?>"><i class="bi bi-eye"></i></button>
            <?php if ($b['status'] === 'signed'): ?>
              <button class="btn btn-sm btn-primary text-nowrap" data-approve="<?= $b['id'] ?>" data-no="<?= e($b['booking_no']) ?>" data-title="<?= e($b['title'] . ' · ' . $b['room_name'] . ' · ' . time_range($b['start_datetime'], $b['end_datetime'])) ?>"><i class="bi bi-check2-circle me-1"></i>อนุมัติ</button>
            <?php elseif ($b['status'] === 'approved'): ?>
              <button class="btn-icon sm" title="เปลี่ยนเจ้าหน้าที่" data-assign="<?= $b['id'] ?>" data-no="<?= e($b['booking_no']) ?>" data-staff="<?= e($b['staff_ids']) ?>" data-title="<?= e($b['title'] . ' · ' . $b['room_name']) ?>"><i class="bi bi-person-gear"></i></button>
            <?php elseif (in_array($b['status'], WAITING_STATUSES, true)): ?>
              <span class="btn btn-sm btn-ghost disabled text-nowrap" title="รอการลงนามตามลำดับขั้น"><i class="bi bi-hourglass-split me-1"></i><?= e(BOOKING_STATUS[$b['status']]['short']) ?></span>
            <?php endif; ?>
            <?php if (in_array($b['status'], ACTIVE_STATUSES, true)): ?>
              <form method="post" data-confirm="ยกเลิกการจอง <?= e($b['booking_no']) ?> ?" data-ok="ยกเลิกการจอง"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn-icon sm text-warning" title="ยกเลิก"><i class="bi bi-slash-circle"></i></button></form>
            <?php else: ?>
              <form method="post" data-confirm="ลบรายการ <?= e($b['booking_no']) ?> ถาวร?" data-ok="ลบ"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn-icon sm text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?><div class="card-x-body d-flex justify-content-end border-top"><?= pagination_html($pg) ?></div><?php endif; ?>
</div>

<!-- detail offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="detailPanel" style="width:min(560px,100vw)">
  <div class="offcanvas-header border-bottom"><h5 class="offcanvas-title"><i class="bi bi-file-earmark-text me-2 text-primary"></i>รายละเอียดการจอง</h5><button class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body" id="detailBody"></div>
</div>

<!-- approve / assign modal -->
<div class="modal fade" id="approveModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" id="ap_action" value="approve"><input type="hidden" name="id" id="ap_id">
  <div class="modal-header"><h5 class="modal-title"><span class="modal-icon"><i class="bi bi-check2-circle"></i></span><span id="ap_head">อนุมัติการจอง</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="p-3 rounded-4 mb-3" style="background:var(--surface-2)"><div class="code-pill d-inline-block mb-1" id="ap_no"></div><div class="small" id="ap_title"></div></div>
    <div id="ap_info" class="alert alert-success small d-flex gap-2"><i class="bi bi-patch-check-fill"></i><div>ลงนามครบทุกขั้นแล้ว (ผู้ขอ · หัวหน้างานอาคาร · รอง ผอ. · ผอ.) · เมื่อยืนยัน สถานะจะเปลี่ยนจาก <strong>"จอง"</strong> เป็น <strong>"ไม่ว่าง"</strong></div></div>
    <label class="form-label">มอบหมายเจ้าหน้าที่ประจำห้องประชุม (ดูแลห้องในวันประชุม) <span class="text-danger">*</span></label>
    <?php if (!$staffUsers): ?><div class="alert alert-warning small">ยังไม่มีผู้ใช้สิทธิ์ "เจ้าหน้าที่ประจำห้องประชุม" — <a href="<?= url('admin/users.php') ?>">เพิ่มผู้ใช้</a></div><?php endif; ?>
    <div class="d-grid gap-2 mb-3">
      <?php foreach ($staffUsers as $s): ?>
        <label class="d-flex align-items-center gap-3 p-2 px-3 rounded-3 border" style="cursor:pointer"><input class="form-check-input m-0" type="checkbox" name="staff[]" value="<?= $s['id'] ?>"><?= avatar($s, 32) ?><span><strong class="d-block small"><?= e(full_name($s)) ?></strong><small class="text-muted"><?= e($s['phone'] ?: $s['position']) ?></small></span></label>
      <?php endforeach; ?>
    </div>
    <div id="ap_note_wrap"><label class="form-label">หมายเหตุ</label><input class="form-control" name="admin_note" maxlength="255" placeholder="(ถ้ามี)"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" id="ap_btn"><i class="bi bi-check2-circle me-1"></i>อนุมัติ · เปลี่ยนเป็นไม่ว่าง</button></div>
</form></div></div>

<!-- new booking modal -->
<div class="modal fade" id="newModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="post" id="newForm">
  <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="signature" id="nb_sig">
  <div class="modal-header"><h5 class="modal-title"><span class="modal-icon"><i class="bi bi-calendar-plus"></i></span>เพิ่มการจองห้องประชุม</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-md-7"><label class="form-label">ห้องประชุม <span class="text-danger">*</span></label><select class="form-select" name="room_id" id="nb_room" required><option value="">— เลือกห้อง —</option>
      <?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>" <?= (int)qs('room_id') === (int)$r['id'] ? 'selected' : '' ?> <?= $r['status'] === 'unavailable' ? 'disabled' : '' ?>><?= e($r['name']) ?> · <?= e($r['building_name']) ?> (<?= (int)$r['capacity'] ?> ที่นั่ง)<?= $r['status'] === 'unavailable' ? ' — ปิดใช้งาน' : '' ?></option><?php endforeach; ?></select></div>
    <div class="col-md-5"><label class="form-label">จำนวนผู้เข้าร่วม</label><input class="form-control" type="number" min="0" name="attendees" value="10"></div>
    <div class="col-12"><label class="form-label">มีความประสงค์ขอใช้สถานที่เพื่อ <span class="text-danger">*</span></label><input class="form-control" name="title" required maxlength="255"></div>
    <div class="col-md-4"><label class="form-label">วันที่ <span class="text-danger">*</span></label><input class="form-control" type="date" name="date" required value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-4"><label class="form-label">เวลาเริ่ม</label><input class="form-control" type="time" name="start_time" required value="09:00"></div>
    <div class="col-md-4"><label class="form-label">เวลาสิ้นสุด</label><input class="form-control" type="time" name="end_time" required value="12:00"></div>
    <div class="col-md-6"><label class="form-label">ผู้ขอใช้ (บัญชีในระบบ)</label><select class="form-select" name="user_id" id="nb_user"><option value="">— บุคคลภายนอก / ระบุชื่อเอง —</option>
      <?php foreach ($requesters as $u): ?><option value="<?= $u['id'] ?>" data-name="<?= e(full_name($u)) ?>" data-phone="<?= e($u['phone']) ?>"><?= e(full_name($u)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">ชื่อผู้ขอใช้ <span class="text-danger">*</span></label><input class="form-control" name="requester_name" id="nb_name"></div>
    <div class="col-md-6"><label class="form-label">หน่วยงาน / แผนก</label><input class="form-control" name="department"></div>
    <div class="col-md-6"><label class="form-label">เบอร์ติดต่อ</label><input class="form-control" name="contact_phone" id="nb_phone"></div>
    <div class="col-12"><label class="form-label">อุปกรณ์</label><?= booking_equipment_fields([], 'nbeq') ?></div>
    <div class="col-12"><label class="form-label">รายละเอียดเพิ่มเติม</label><textarea class="form-control" name="purpose" rows="2"></textarea></div>
    <div class="col-12"><label class="form-label d-flex justify-content-between">ลายมือชื่อผู้ขอใช้ห้อง <span class="text-danger">*</span><button type="button" class="btn btn-sm btn-ghost py-0 ms-auto" id="nb_clear"><i class="bi bi-eraser me-1"></i>ล้าง</button></label>
      <div class="sig-wrap sm"><canvas id="nbPad"></canvas><div class="sig-line"></div><div class="sig-ph" id="nb_sigph">( ผู้ขอใช้ห้อง )</div></div></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-send me-1"></i>บันทึกการจอง (ส่งหัวหน้างานอาคาร)</button></div>
</form></div></div>
<?php
$extraScripts = <<<'JS'
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function(){
  // ลายเซ็นผู้ขอใช้ (ฟอร์มเพิ่มการจอง)
  const nbModal = document.getElementById('newModal'), nbCanvas = document.getElementById('nbPad');
  const nbPad = new SignaturePad(nbCanvas, { penColor: '#1e3a8a', minWidth: 0.8, maxWidth: 2.6, backgroundColor: 'rgba(0,0,0,0)' });
  const nbFit = () => { const r = Math.max(window.devicePixelRatio || 1, 1); nbCanvas.width = nbCanvas.offsetWidth * r; nbCanvas.height = nbCanvas.offsetHeight * r; nbCanvas.getContext('2d').scale(r, r); nbPad.clear(); };
  nbModal.addEventListener('shown.bs.modal', nbFit);
  document.getElementById('nb_clear').onclick = () => nbPad.clear();
  document.getElementById('nb_name').addEventListener('input', e => document.getElementById('nb_sigph').textContent = '( ' + (e.target.value || 'ผู้ขอใช้ห้อง') + ' )');
  document.getElementById('newForm').addEventListener('submit', e => {
    if (nbPad.isEmpty()) { e.preventDefault(); Swal.fire({ icon: 'warning', title: 'กรุณาให้ผู้ขอใช้ห้องลงลายมือชื่อ', confirmButtonColor: '#6366f1' }); return; }
    document.getElementById('nb_sig').value = nbPad.toDataURL('image/png');
  });
  const panel = new bootstrap.Offcanvas('#detailPanel'), body = document.getElementById('detailBody');
  async function view(id) {
    body.innerHTML = '<div class="text-center py-5"><div class="v3d-spinner mx-auto"></div></div>'; panel.show();
    const j = await fetch(APP.base + '/api/booking.php?id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).catch(() => ({}));
    body.innerHTML = j.ok ? j.html : '<div class="alert alert-danger">ไม่พบข้อมูล</div>';
  }
  document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => view(b.dataset.view)));
  const m = new bootstrap.Modal('#approveModal');
  function openAp(btn, mode) {
    document.getElementById('ap_action').value = mode;
    document.getElementById('ap_id').value = btn.dataset.approve || btn.dataset.assign;
    document.getElementById('ap_no').textContent = btn.dataset.no;
    document.getElementById('ap_title').textContent = btn.dataset.title;
    document.getElementById('ap_head').textContent = mode === 'approve' ? 'อนุมัติการจอง' : 'มอบหมายเจ้าหน้าที่ประจำห้อง';
    document.getElementById('ap_info').style.display = mode === 'approve' ? '' : 'none';
    document.getElementById('ap_note_wrap').style.display = mode === 'approve' ? '' : 'none';
    document.getElementById('ap_btn').innerHTML = mode === 'approve' ? '<i class="bi bi-check2-circle me-1"></i>อนุมัติ · เปลี่ยนเป็นไม่ว่าง' : '<i class="bi bi-check2 me-1"></i>บันทึก';
    const ids = (btn.dataset.staff || '').split(',');
    document.querySelectorAll('#approveModal [name="staff[]"]').forEach(c => c.checked = ids.includes(c.value));
    m.show();
  }
  document.querySelectorAll('[data-approve]').forEach(b => b.addEventListener('click', () => openAp(b, 'approve')));
  document.querySelectorAll('[data-assign]').forEach(b => b.addEventListener('click', () => openAp(b, 'assign')));
  document.getElementById('nb_user').addEventListener('change', e => {
    const o = e.target.selectedOptions[0];
    if (o && o.value) { document.getElementById('nb_name').value = o.dataset.name; document.getElementById('nb_name').dispatchEvent(new Event('input')); if (o.dataset.phone) document.getElementById('nb_phone').value = o.dataset.phone; }
  });
  const p = new URLSearchParams(location.search);
  if (p.get('view')) view(p.get('view'));
  if (p.get('new')) new bootstrap.Modal('#newModal').show();
})();
</script>
JS;
require __DIR__ . '/../includes/footer.php';
