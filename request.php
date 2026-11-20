<?php
/** แบบฟอร์มขอใช้ห้องประชุม — ไม่ต้องเป็นสมาชิก ติดตามสถานะด้วยเบอร์โทร */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$rooms = public_rooms();
$old = $_SESSION['_req_old'] ?? [];
unset($_SESSION['_req_old']);

if (is_post()) {
    csrf_check();
    $old = array_intersect_key($_POST, array_flip(['room_id', 'date', 'start_time', 'end_time', 'requester_name', 'department', 'contact_phone', 'title', 'purpose', 'attendees']));
    try {
        if (input('website') !== '') throw new RuntimeException('ไม่สามารถส่งคำขอได้');           // honeypot กันบอท
        if (!rate_hit('request', 5, 3600)) throw new RuntimeException('ส่งคำขอบ่อยเกินไป กรุณาลองใหม่ภายหลัง');
        $ipCount = (int)db_val("SELECT COUNT(*) FROM logs WHERE action='booking' AND user_id IS NULL AND ip=? AND created_at >= NOW() - INTERVAL 1 HOUR", [client_ip()]);
        if ($ipCount >= 10) throw new RuntimeException('มีการส่งคำขอจากเครือข่ายนี้มากเกินไป กรุณาลองใหม่ภายหลัง');

        $room = db_one('SELECT r.*, b.name building_name FROM rooms r JOIN buildings b ON b.id=r.building_id WHERE r.id=?', [(int)input('room_id')]);
        if (!$room) throw new RuntimeException('กรุณาเลือกห้องประชุม');
        if ($room['status'] === 'unavailable') throw new RuntimeException('ห้องนี้ปิดให้บริการชั่วคราว กรุณาเลือกห้องอื่น');

        $date = (string)input('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', (string)input('start_time')) || !preg_match('/^\d{2}:\d{2}$/', (string)input('end_time')))
            throw new RuntimeException('กรุณาระบุวันและเวลาให้ครบถ้วน');
        $start = "$date " . input('start_time') . ':00';
        $end = "$date " . input('end_time') . ':00';
        if ($end <= $start) throw new RuntimeException('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม');
        if (strtotime($start) < time()) throw new RuntimeException('ไม่สามารถจองย้อนหลังได้');
        if (strtotime($start) > strtotime('+180 days')) throw new RuntimeException('จองล่วงหน้าได้ไม่เกิน 180 วัน');

        $name = trim((string)input('requester_name'));
        $phone = phone_digits(input('contact_phone'));
        if ($name === '') throw new RuntimeException('กรุณากรอกชื่อ-นามสกุลผู้ขอใช้');
        if (!phone_valid($phone)) throw new RuntimeException('เบอร์โทรศัพท์ไม่ถูกต้อง (ตัวเลข 9–10 หลัก ขึ้นต้นด้วย 0)');
        if (trim((string)input('title')) === '') throw new RuntimeException('กรุณากรอกหัวข้อ/วัตถุประสงค์การประชุม');
        $att = max(1, (int)input('attendees'));
        if ($att > (int)$room['capacity']) throw new RuntimeException("จำนวนผู้เข้าร่วม ($att คน) เกินความจุห้อง ({$room['capacity']} ที่นั่ง)");
        if ($c = booking_conflicts((int)$room['id'], $start, $end))
            throw new RuntimeException('ช่วงเวลานี้มีการจองแล้ว (' . e(time_range($c[0]['start_datetime'], $c[0]['end_datetime'])) . ') กรุณาเลือกเวลาอื่น');
        if (!input('agree')) throw new RuntimeException('กรุณายืนยันว่าข้อมูลถูกต้องก่อนลงนาม');
        $png = signature_png_from_dataurl((string)input('signature'));

        $pdo = db();
        $pdo->beginTransaction();
        db_exec('INSERT INTO bookings (room_id,user_id,requester_name,department,title,purpose,attendees,start_datetime,end_datetime,contact_phone,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
            $room['id'], null, mb_substr($name, 0, 150), mb_substr(trim((string)input('department')), 0, 150), mb_substr(trim((string)input('title')), 0, 255),
            mb_substr(trim((string)input('purpose')), 0, 2000) ?: null, $att, $start, $end, phone_format($phone), 'pending', null,
        ]);
        $nid = (int)$pdo->lastInsertId();
        $no = 'BK' . date('ym', strtotime($start)) . '-' . str_pad((string)$nid, 4, '0', STR_PAD_LEFT);
        db_exec('UPDATE bookings SET booking_no=? WHERE id=?', [$no, $nid]);
        // ตรวจซ้ำหลังบันทึก กันกรณีส่งพร้อมกัน
        if (booking_conflicts((int)$room['id'], $start, $end, $nid)) { $pdo->rollBack(); throw new RuntimeException('ช่วงเวลานี้เพิ่งถูกจองไป กรุณาเลือกเวลาอื่น'); }
        // ขั้นที่ 1: ลายเซ็นผู้ขอใช้ห้อง (ต้นห่วงโซ่ลายเซ็น)
        signature_add(db_one('SELECT * FROM bookings WHERE id=?', [$nid]), 'requester',
            ['user_id' => null, 'name' => $name, 'position' => mb_substr(trim((string)input('department')), 0, 150)], 'sign', null, $png);
        $pdo->commit();

        log_action('booking', "ยื่นคำขอใช้ห้อง (ไม่เป็นสมาชิก) $no · {$room['name']} · " . time_range($start, $end) . " · $name", ['id' => null, 'username' => 'guest']);
        $_SESSION['_req_done'] = $nid;
        redirect('request.php?done=1');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['_req_old'] = $old;
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'ไม่สามารถบันทึกคำขอได้ กรุณาลองใหม่');
        redirect('request.php');
    }
}

// ---------- หน้าส่งคำขอสำเร็จ ----------
$done = qs('done') && !empty($_SESSION['_req_done'])
    ? db_one('SELECT b.*, r.name room_name, bd.name building_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id WHERE b.id=?', [$_SESSION['_req_done']])
    : null;

$selRoom = (int)($old['room_id'] ?? qs('room', 0));
$selDate = $old['date'] ?? (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)qs('date')) ? qs('date') : date('Y-m-d', strtotime('+1 day')));
$times = [];
for ($m = 7 * 60; $m <= 21 * 60; $m += 30) $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

public_header('ขอใช้ห้องประชุม', 'request');
?>
<section class="pub-hero sm">
  <div class="container">
    <span class="eyebrow light"><i class="bi bi-calendar-plus"></i>Room Request</span>
    <h1>แบบฟอร์มขอใช้ห้องประชุม</h1>
    <p>ไม่ต้องสมัครสมาชิก — กรอกข้อมูลแล้วติดตามสถานะได้ด้วย <strong>เบอร์โทรศัพท์</strong> ที่ใช้ในการจอง</p>
  </div>
</section>

<div class="container pub-body">
<?php if ($done): ?>
  <div class="card-x done-card reveal mx-auto">
    <div class="done-icon"><i class="bi bi-check2"></i></div>
    <h3>ส่งคำขอใช้ห้องเรียบร้อยแล้ว</h3>
    <p class="text-muted mb-4">คำขอของคุณอยู่ในสถานะ <?= booking_badge($done['status']) ?> ระบบจะดำเนินการตามขั้นตอน</p>
    <div class="ticket">
      <div class="ticket-no"><small>เลขที่คำขอ</small><strong><?= e($done['booking_no']) ?></strong></div>
      <dl class="dl"><dt>ห้อง</dt><dd><?= e($done['room_name']) ?> · <?= e($done['building_name']) ?></dd>
        <dt>วันเวลา</dt><dd><?= e(thai_date($done['start_datetime'], 'long')) ?><br><?= date('H:i', strtotime($done['start_datetime'])) ?>–<?= date('H:i', strtotime($done['end_datetime'])) ?> น.</dd>
        <dt>หัวข้อ</dt><dd><?= e($done['title']) ?></dd>
        <dt>ผู้ขอใช้</dt><dd><?= e($done['requester_name']) ?></dd>
        <dt>เบอร์ติดต่อ</dt><dd><?= e($done['contact_phone']) ?></dd></dl>
    </div>
    <div class="steps-mini my-4">
      <span class="on"><i class="bi bi-pen"></i>ผู้ขอลงนาม</span><i class="bi bi-chevron-right"></i>
      <span><i class="bi bi-person-gear"></i>หัวหน้างานอาคาร</span><i class="bi bi-chevron-right"></i>
      <span><i class="bi bi-person-badge"></i>รอง ผอ. ฝ่ายบริหารทรัพยากร</span><i class="bi bi-chevron-right"></i>
      <span><i class="bi bi-award"></i>ผู้อำนวยการ</span>
    </div>
    <p class="small text-muted">กรุณาจดเลขที่คำขอไว้ และตรวจสอบสถานะได้ตลอดเวลาด้วยเบอร์โทร <strong><?= e($done['contact_phone']) ?></strong></p>
    <div class="d-flex flex-wrap gap-2 justify-content-center">
      <a class="btn btn-primary" href="<?= url('status.php') ?>"><i class="bi bi-search me-1"></i>ตรวจสอบสถานะ</a>
      <a class="btn btn-ghost" href="<?= url('request.php') ?>"><i class="bi bi-plus-lg me-1"></i>ขอใช้ห้องอีกครั้ง</a>
      <button class="btn btn-ghost" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
    </div>
  </div>
<?php else: ?>
  <form method="post" class="row g-4" id="reqForm" novalidate>
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="col-lg-8">
      <!-- 1. เลือกห้อง -->
      <div class="card-x reveal mb-4"><div class="card-x-head"><h5><span class="step-no">1</span>เลือกห้องประชุม</h5></div>
        <div class="card-x-body"><div class="room-pick">
          <?php foreach ($rooms as $r): $closed = $r['status'] === 'unavailable'; ?>
            <label class="room-opt <?= $closed ? 'is-closed' : '' ?>">
              <input type="radio" name="room_id" value="<?= $r['id'] ?>" data-cap="<?= (int)$r['capacity'] ?>" data-name="<?= e($r['name']) ?>" data-building="<?= e($r['building_name']) ?>" <?= $selRoom === (int)$r['id'] ? 'checked' : '' ?> <?= $closed ? 'disabled' : '' ?> required>
              <span class="room-opt-img" style="--c:<?= e($r['color']) ?>"><?php if ($r['cover']): ?><img src="<?= e(upload_url($r['cover'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-easel2"></i><?php endif; ?></span>
              <span class="room-opt-body"><strong><?= e($r['name']) ?></strong>
                <small><?= e($r['building_name']) ?> · ชั้น <?= (int)$r['floor'] ?></small>
                <small><i class="bi bi-people"></i> <?= (int)$r['capacity'] ?> ที่นั่ง<?= $closed ? ' · <span class="text-danger">ปิดปรับปรุง</span>' : '' ?></small></span>
              <i class="bi bi-check-circle-fill room-opt-check"></i>
            </label>
          <?php endforeach; ?>
        </div></div></div>

      <!-- 2. วันเวลา -->
      <div class="card-x reveal mb-4" style="--i:1"><div class="card-x-head"><h5><span class="step-no">2</span>วันและเวลา</h5></div>
        <div class="card-x-body row g-3">
          <div class="col-md-4"><label class="form-label">วันที่ใช้ห้อง <span class="text-danger">*</span></label><input type="date" class="form-control" name="date" id="fDate" value="<?= e($selDate) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+180 days')) ?>" required></div>
          <div class="col-6 col-md-4"><label class="form-label">เวลาเริ่ม <span class="text-danger">*</span></label><select class="form-select" name="start_time" id="fStart" required><?php foreach (array_slice($times, 0, -1) as $t): ?><option <?= ($old['start_time'] ?? '09:00') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
          <div class="col-6 col-md-4"><label class="form-label">เวลาสิ้นสุด <span class="text-danger">*</span></label><select class="form-select" name="end_time" id="fEnd" required><?php foreach (array_slice($times, 1) as $t): ?><option <?= ($old['end_time'] ?? '12:00') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
          <div class="col-12">
            <div class="slot-head"><span class="small fw-semibold">ช่วงเวลาของห้องในวันที่เลือก</span>
              <span class="slot-legend"><i style="--c:#10b981"></i>ว่าง <i style="--c:#f59e0b"></i>จองแล้ว <i style="--c:#ef4444"></i>ไม่ว่าง <i style="--c:#6366f1"></i>ที่คุณเลือก</span></div>
            <div class="slot-bar" id="slotBar"><div class="slot-empty">เลือกห้องเพื่อดูช่วงเวลาว่าง</div></div>
            <div class="slot-scale"><?php for ($h = 7; $h <= 21; $h += 2): ?><span><?= sprintf('%02d:00', $h) ?></span><?php endfor; ?></div>
            <div class="slot-msg" id="slotMsg"></div>
          </div>
        </div></div>

      <!-- 3. ผู้ขอใช้ -->
      <div class="card-x reveal mb-4" style="--i:2"><div class="card-x-head"><h5><span class="step-no">3</span>ข้อมูลผู้ขอใช้</h5></div>
        <div class="card-x-body row g-3">
          <div class="col-md-6"><label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label><input class="form-control" name="requester_name" value="<?= e($old['requester_name'] ?? '') ?>" maxlength="150" required autocomplete="name"></div>
          <div class="col-md-6"><label class="form-label">หน่วยงาน / แผนก / สังกัด</label><input class="form-control" name="department" value="<?= e($old['department'] ?? '') ?>" maxlength="150" placeholder="เช่น แผนกวิชาช่างยนต์ หรือ หน่วยงานภายนอก"></div>
          <div class="col-md-6"><label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span><input class="form-control" name="contact_phone" id="fPhone" value="<?= e($old['contact_phone'] ?? '') ?>" inputmode="tel" maxlength="20" placeholder="08x-xxx-xxxx" required autocomplete="tel"></div>
            <div class="form-text"><i class="bi bi-info-circle me-1"></i>ใช้เบอร์นี้ตรวจสอบสถานะการจอง และเจ้าหน้าที่จะติดต่อกลับ</div></div>
        </div></div>

      <!-- 4. รายละเอียดการประชุม -->
      <div class="card-x reveal" style="--i:3"><div class="card-x-head"><h5><span class="step-no">4</span>รายละเอียดการประชุม</h5></div>
        <div class="card-x-body row g-3">
          <div class="col-md-9"><label class="form-label">หัวข้อการประชุม / กิจกรรม <span class="text-danger">*</span></label><input class="form-control" name="title" value="<?= e($old['title'] ?? '') ?>" maxlength="255" required></div>
          <div class="col-md-3"><label class="form-label">ผู้เข้าร่วม (คน) <span class="text-danger">*</span></label><input type="number" class="form-control" name="attendees" id="fAtt" value="<?= e($old['attendees'] ?? '10') ?>" min="1" required><div class="form-text" id="capHint"></div></div>
          <div class="col-12"><label class="form-label">รายละเอียดเพิ่มเติม / อุปกรณ์ที่ต้องการ</label><textarea class="form-control" name="purpose" rows="3" maxlength="2000" placeholder="เช่น ต้องการโปรเจกเตอร์ ไมโครโฟน 2 ตัว จัดโต๊ะแบบตัว U"><?= e($old['purpose'] ?? '') ?></textarea></div>
        </div></div>

      <!-- 5. ลงลายมือชื่อผู้ขอใช้ -->
      <div class="card-x reveal mt-4" style="--i:4"><div class="card-x-head"><h5><span class="step-no">5</span>ลงลายมือชื่อผู้ขอใช้ห้อง</h5><button type="button" class="btn btn-sm btn-ghost" id="reqSigClear"><i class="bi bi-eraser me-1"></i>ล้าง</button></div>
        <div class="card-x-body">
          <input type="hidden" name="signature" id="reqSig">
          <div class="sig-wrap"><canvas id="reqPad"></canvas><div class="sig-line"></div><div class="sig-ph" id="reqSigName">( ผู้ขอใช้ห้อง )</div></div>
          <div class="form-text mb-3"><i class="bi bi-hand-index me-1"></i>ใช้นิ้ว ปากกา หรือเมาส์เซ็นชื่อภายในกรอบ</div>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="agree" value="1" id="fAgree" required><span class="form-check-label small">ข้าพเจ้าขอรับรองว่าข้อมูลข้างต้นเป็นความจริง และจะใช้ห้องประชุมตามวัตถุประสงค์ที่ระบุ</span></label>
          <div class="approve-path mt-3">
            <span class="ap-title">ลำดับการอนุมัติหลังส่งคำขอ</span>
            <?php foreach (SIGN_STEPS as $k => $st): ?><span class="ap-step" style="--c:<?= $st['color'] ?>"><i class="bi <?= $st['icon'] ?>"></i><?= e($st['label']) ?></span><?php endforeach; ?>
          </div>
        </div></div>
    </div>

    <!-- สรุป -->
    <div class="col-lg-4"><div class="sticky-lg">
      <div class="card-x summary-card reveal" style="--i:2">
        <div class="summary-top"><i class="bi bi-receipt"></i><span>สรุปคำขอ</span></div>
        <div class="card-x-body">
          <dl class="dl" style="grid-template-columns:80px 1fr"><dt>ห้อง</dt><dd id="sRoom">—</dd><dt>วันที่</dt><dd id="sDate">—</dd><dt>เวลา</dt><dd id="sTime">—</dd></dl>
          <div class="avail-pill" id="sAvail"><i class="bi bi-hourglass"></i>เลือกห้องและเวลา</div>
          <button class="btn btn-primary w-100 btn-lg mt-3" id="btnSubmit"><i class="bi bi-send me-1"></i>ส่งคำขอใช้ห้อง</button>
          <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>คำขอจะผ่านการลงนามอนุมัติตามลำดับ: หัวหน้างานอาคาร → รองผู้อำนวยการฝ่ายบริหารทรัพยากร → ผู้อำนวยการ ตรวจสอบผลได้ที่เมนู <a href="<?= url('status.php') ?>">ตรวจสอบสถานะ</a></p>
        </div>
      </div>
    </div></div>
  </form>
<?php endif; ?>
</div>
<?php
public_footer('<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script><script src="' . asset('js/public.js') . '"></script>');
