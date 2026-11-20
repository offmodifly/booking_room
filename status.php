<?php
/** ตรวจสอบสถานะการจองด้วยเบอร์โทรศัพท์ (ไม่ต้องเข้าสู่ระบบ) */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$phone = ''; $no = ''; $results = null; $error = null;

if (is_post()) {
    csrf_check();
    $phone = phone_digits(input('phone'));
    $no = strtoupper(trim((string)input('booking_no')));
    try {
        if (!phone_valid($phone)) throw new RuntimeException('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (ตัวเลข 9–10 หลัก)');

        // ผู้ขอยกเลิกคำขอเอง (ได้เฉพาะคำขอที่ยังไม่อนุมัติ)
        if (input('action') === 'cancel') {
            $b = db_one("SELECT * FROM bookings WHERE id=? AND REGEXP_REPLACE(contact_phone,'[^0-9]','')=?", [(int)input('id'), $phone]);
            if (!$b) throw new RuntimeException('ไม่พบคำขอ');
            if (!in_array($b['status'], [...WAITING_STATUSES, 'signed'], true)) throw new RuntimeException('คำขอนี้ยกเลิกเองไม่ได้ (อนุมัติแล้วหรือสิ้นสุดแล้ว) กรุณาติดต่อเจ้าหน้าที่');
            db_exec("UPDATE bookings SET status='cancelled', admin_note=? WHERE id=?", ['ผู้ขอยกเลิกคำขอเอง', $b['id']]);
            log_action('booking', "ผู้ขอยกเลิกคำขอเอง {$b['booking_no']} · {$b['requester_name']}", ['id' => null, 'username' => 'guest']);
            flash('success', "ยกเลิกคำขอ {$b['booking_no']} แล้ว");
        } elseif (!rate_hit('status', 20, 600)) {
            throw new RuntimeException('ค้นหาบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่');
        }

        $sql = "SELECT b.*, r.name room_name, r.floor, bd.name building_name,
                  (SELECT GROUP_CONCAT(CONCAT(u.prefix,u.first_name,' ',u.last_name) SEPARATOR ', ') FROM booking_staff s JOIN users u ON u.id=s.user_id WHERE s.booking_id=b.id) staff_names
                FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id
                WHERE REGEXP_REPLACE(b.contact_phone,'[^0-9]','') = ?";
        $params = [$phone];
        if ($no !== '') { $sql .= ' AND b.booking_no = ?'; $params[] = $no; }
        $results = db_all($sql . ' ORDER BY b.start_datetime DESC LIMIT 50', $params);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

/** ขั้นตอนของคำขอ: [ชื่อขั้น, รายละเอียด, สถานะ(done|now|fail|idle), ไอคอน] */
function status_steps(array $b): array
{
    $sigs = [];
    foreach (booking_signatures((int)$b['id']) as $sg) $sigs[$sg['step']] = $sg;
    $out = []; $stopped = false;
    foreach (SIGN_STEPS as $k => $st) {
        $sg = $sigs[$k] ?? null;
        $title = $k === 'requester' ? 'ผู้ขอใช้ลงนาม · ยื่นคำขอ' : $st['label'];
        if ($sg) {
            $fail = $sg['decision'] === 'reject';
            $out[] = [$title, thai_date($sg['signed_at'], 'datetime') . ($k === 'requester' ? '' : ' · ' . $sg['signer_name']) . ($fail ? ' · ไม่อนุมัติ' : ''), $fail ? 'fail' : 'done', $st['icon']];
            if ($fail) $stopped = true;
        } else {
            $now = !$stopped && $st['from'] === $b['status'];
            $out[] = [$title, $now ? 'กำลังพิจารณา' : '—', $now ? 'now' : 'idle', $st['icon']];
        }
    }
    $out[] = ['ยืนยันการใช้ห้อง · จัดเจ้าหน้าที่', $b['approved_at'] ? thai_date($b['approved_at'], 'datetime') : ($b['status'] === 'signed' ? 'กำลังดำเนินการ' : '—'),
        $b['status'] === 'approved' ? 'done' : ($b['status'] === 'signed' ? 'now' : 'idle'), 'bi-check2-circle'];
    return $out;
}
/** เหตุผลที่ไม่อนุมัติ (ถ้ามี) */
function reject_reason(array $b): ?array
{
    $r = db_one("SELECT step, note, signer_name FROM booking_signatures WHERE booking_id=? AND decision='reject' LIMIT 1", [$b['id']]);
    return $r ? [SIGN_STEPS[$r['step']]['label'] ?? '', $r['note']] : null;
}
$lookupTitle = [
    'pending' => ['รอหัวหน้างานอาคารพิจารณา', 'คำขอและลายเซ็นของคุณถูกส่งถึงหัวหน้างานอาคารแล้ว'],
    'head_approved' => ['หัวหน้างานอาคารอนุมัติแล้ว', 'อยู่ระหว่างรองผู้อำนวยการฝ่ายบริหารทรัพยากรพิจารณา'],
    'deputy_approved' => ['รองผู้อำนวยการฯ อนุมัติแล้ว', 'อยู่ระหว่างผู้อำนวยการพิจารณา (ผู้อนุมัติสูงสุด)'],
    'signed' => ['ผู้อำนวยการอนุมัติแล้ว', 'อยู่ระหว่างผู้ดูแลระบบยืนยันและจัดเจ้าหน้าที่ประจำห้อง'],
    'approved' => ['อนุมัติแล้ว', 'ห้องพร้อมใช้งานตามวันเวลาที่จอง'],
    'rejected' => ['ไม่อนุมัติ', 'คำขอนี้ไม่ได้รับการอนุมัติ'],
    'cancelled' => ['ยกเลิกแล้ว', 'คำขอนี้ถูกยกเลิก'],
];

public_header('ตรวจสอบสถานะการจอง', 'status');
?>
<section class="pub-hero sm">
  <div class="container">
    <span class="eyebrow light"><i class="bi bi-search"></i>Booking Status</span>
    <h1>ตรวจสอบสถานะการจอง</h1>
    <p>กรอกเบอร์โทรศัพท์ที่ใช้ตอนขอใช้ห้อง เพื่อดูความคืบหน้าของคำขอทั้งหมด</p>
  </div>
</section>

<div class="container pub-body">
  <form method="post" class="card-x lookup-card reveal mx-auto">
    <?= csrf_field() ?>
    <div class="card-x-body row g-3 align-items-end">
      <div class="col-md-5"><label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
        <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-telephone"></i></span><input class="form-control" name="phone" value="<?= e($phone ? phone_format($phone) : '') ?>" inputmode="tel" maxlength="20" placeholder="08x-xxx-xxxx" required autofocus></div></div>
      <div class="col-md-4"><label class="form-label">เลขที่คำขอ <span class="text-muted small">(ไม่บังคับ)</span></label>
        <input class="form-control form-control-lg" name="booking_no" value="<?= e($no) ?>" maxlength="20" placeholder="เช่น BK2611-0012"></div>
      <div class="col-md-3"><button class="btn btn-primary btn-lg w-100"><i class="bi bi-search me-1"></i>ค้นหา</button></div>
    </div>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-danger mx-auto mt-4 lookup-w"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
  <?php elseif ($results !== null && !$results): ?>
    <div class="card-x empty-state mx-auto mt-4 lookup-w"><div class="empty-icon"><i class="bi bi-inbox"></i></div><h5>ไม่พบคำขอที่ใช้เบอร์นี้</h5>
      <p class="text-muted">ตรวจสอบเบอร์โทรอีกครั้ง หรือยื่นคำขอใหม่</p><a class="btn btn-primary" href="<?= url('request.php') ?>">ขอใช้ห้องประชุม</a></div>
  <?php elseif ($results): ?>
    <div class="lookup-w mx-auto mt-4">
      <p class="text-muted small mb-3">พบ <?= count($results) ?> คำขอของเบอร์ <strong><?= e(phone_format($phone)) ?></strong></p>
      <?php foreach ($results as $i => $b): $st = BOOKING_STATUS[$b['status']]; $past = strtotime($b['end_datetime']) < time(); ?>
        <div class="card-x status-card reveal mb-3" style="--i:<?= $i ?>;--c:<?= e($st['color']) ?>">
          <div class="status-card-head">
            <div class="status-ic"><i class="bi <?= e($st['icon']) ?>"></i></div>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex flex-wrap align-items-center gap-2"><span class="code-pill"><?= e($b['booking_no']) ?></span><?= booking_badge($b['status']) ?><?= $past && $b['status'] === 'approved' ? badge('ใช้งานแล้ว', '#64748b', 'bi-clock-history') : '' ?></div>
              <h5 class="mt-2 mb-1"><?= e($lookupTitle[$b['status']][0]) ?></h5>
              <p class="small text-muted mb-0"><?= e($lookupTitle[$b['status']][1]) ?></p>
            </div>
          </div>
          <div class="card-x-body pt-0">
            <div class="row g-3">
              <div class="col-md-6"><dl class="dl" style="grid-template-columns:90px 1fr">
                <dt>หัวข้อ</dt><dd><strong><?= e($b['title']) ?></strong></dd>
                <dt>ห้อง</dt><dd><?= e($b['room_name']) ?> · <?= e($b['building_name']) ?> ชั้น <?= (int)$b['floor'] ?></dd>
                <dt>วันที่</dt><dd><?= e(thai_date($b['start_datetime'], 'long')) ?></dd>
                <dt>เวลา</dt><dd><?= date('H:i', strtotime($b['start_datetime'])) ?>–<?= date('H:i', strtotime($b['end_datetime'])) ?> น. · <?= (int)$b['attendees'] ?> คน</dd>
                <dt>ผู้ขอใช้</dt><dd><?= e($b['requester_name']) ?><?= $b['department'] ? ' · ' . e($b['department']) : '' ?></dd>
                <?php if ($b['status'] === 'approved' && $b['staff_names']): ?><dt>เจ้าหน้าที่</dt><dd><i class="bi bi-person-badge me-1"></i><?= e($b['staff_names']) ?></dd><?php endif; ?>
                <?php if ($b['status'] === 'rejected' && ($rr = reject_reason($b))): ?><dt>เหตุผล</dt><dd class="text-danger"><?= e($rr[1]) ?> <span class="text-muted">(<?= e($rr[0]) ?>)</span></dd><?php endif; ?>
                <?php if ($b['admin_note']): ?><dt>หมายเหตุ</dt><dd><?= e($b['admin_note']) ?></dd><?php endif; ?>
              </dl></div>
              <div class="col-md-6">
                <?php if ($b['status'] === 'cancelled'): ?>
                  <div class="steps-v"><div class="sv idle"><span><i class="bi bi-slash-circle"></i></span><div><strong>ยกเลิกคำขอ</strong><small><?= e(thai_date($b['updated_at'] ?: $b['created_at'], 'datetime')) ?></small></div></div></div>
                <?php else: ?>
                  <div class="steps-v"><?php foreach (status_steps($b) as [$t, $sub, $state, $ic]): ?>
                    <div class="sv <?= $state ?>"><span><i class="bi <?= $state === 'fail' ? 'bi-x-lg' : $ic ?>"></i></span><div><strong><?= e($t) ?></strong><small><?= e($sub) ?></small></div></div>
                  <?php endforeach; ?></div>
                <?php endif; ?>
                <?php if (in_array($b['status'], [...WAITING_STATUSES, 'signed'], true) && !$past): ?>
                  <form method="post" class="mt-3 text-end" data-confirm="ยกเลิกคำขอ <?= e($b['booking_no']) ?> ?" data-confirm-text="เมื่อยกเลิกแล้วจะไม่สามารถกู้คืนคำขอนี้ได้" data-ok="ยกเลิกคำขอ">
                    <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <input type="hidden" name="phone" value="<?= e($phone) ?>"><input type="hidden" name="booking_no" value="<?= e($no) ?>">
                    <button class="btn btn-sm btn-soft" style="--c:#ef4444"><i class="bi bi-x-circle me-1"></i>ยกเลิกคำขอ</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php public_footer();
