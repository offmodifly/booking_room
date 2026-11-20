<?php
/** ลงนามอนุมัติตามลำดับขั้น: หัวหน้างานอาคาร → รองผู้อำนวยการฝ่ายบริหารทรัพยากร → ผู้อำนวยการ */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/booking_detail.php';
page_guard('sign');
$me = current_user();
$stepKey = role_sign_step($me['role']);
if (!$stepKey) { http_response_code(403); require __DIR__ . '/../includes/403.php'; exit; }
$step = SIGN_STEPS[$stepKey];
$order = array_keys(SIGN_STEPS);
$stepNo = array_search($stepKey, $order, true);
$next = $order[$stepNo + 1] ?? null;

if (is_post()) {
    csrf_check();
    $pdo = db();
    try {
        $b = db_one('SELECT * FROM bookings WHERE id=?', [(int)input('id')]);
        if (!$b || $b['status'] !== $step['from']) throw new RuntimeException('รายการนี้ไม่อยู่ในขั้นตอนรอท่านลงนาม (อาจมีผู้ดำเนินการไปแล้ว)');
        if (!password_verify((string)input('password'), $me['password_hash'])) throw new RuntimeException('รหัสผ่านยืนยันตัวตนไม่ถูกต้อง');
        $chain = signature_chain($b);
        if (!$chain['valid'] || count($chain['steps']) !== $stepNo) throw new RuntimeException('ลายเซ็นขั้นก่อนหน้าตรวจสอบไม่ผ่าน (ข้อมูลอาจถูกแก้ไข) — ไม่สามารถลงนามต่อได้');
        $decision = input('decision') === 'reject' ? 'reject' : 'approve';
        $note = mb_substr(trim((string)input('note')), 0, 255);
        if ($decision === 'reject' && $note === '') throw new RuntimeException('กรุณาระบุเหตุผลที่ไม่อนุมัติ');
        $png = signature_png_from_dataurl((string)input('signature'));

        $pdo->beginTransaction();
        $newStatus = $decision === 'approve' ? $step['to'] : 'rejected';
        $upd = $pdo->prepare('UPDATE bookings SET status=? WHERE id=? AND status=?');
        $upd->execute([$newStatus, $b['id'], $step['from']]);
        if ($upd->rowCount() !== 1) throw new RuntimeException('สถานะรายการเปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่');
        $sig = signature_add($b, $stepKey, signer_of($me), $decision, $note ?: null, $png);
        $pdo->commit();

        log_action('sign', ($decision === 'approve' ? 'ลงนามอนุมัติ' : 'ลงนามไม่อนุมัติ') . " ({$step['label']}) {$b['booking_no']} · {$b['title']} · hash " . substr($sig['hash'], 0, 16) . '…');
        flash('success', $decision === 'approve'
            ? "ลงนามอนุมัติ {$b['booking_no']} เรียบร้อย · ส่งต่อ" . ($next ? SIGN_STEPS[$next]['label'] : 'ผู้ดูแลระบบ (ยืนยันและจัดเจ้าหน้าที่)')
            : "บันทึกการไม่อนุมัติ {$b['booking_no']} แล้ว");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', e($e->getMessage()));
    }
    redirect('admin/sign.php');
}

$tab = qs('tab') === 'history' ? 'history' : 'pending';
$pending = db_all("SELECT b.*, r.name room_name, bd.name building_name, r.capacity FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id WHERE b.status=? ORDER BY b.start_datetime", [$step['from']]);
$history = db_all("SELECT b.*, r.name room_name, s.decision, s.signed_at sig_at, s.note sig_note FROM booking_signatures s JOIN bookings b ON b.id=s.booking_id JOIN rooms r ON r.id=b.room_id WHERE s.user_id=? AND s.step=? ORDER BY s.signed_at DESC LIMIT 50", [$me['id'], $stepKey]);

$pageTitle = 'ลงนามอนุมัติ · ' . $step['label'];
$activeMenu = 'sign';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-pen"></i>Digital Signature · ขั้นที่ <?= $stepNo ?> จาก <?= count($order) - 1 ?></span><h2>ลงนามอนุมัติ — <?= e($step['label']) ?></h2>
    <p>ลงลายมือชื่อดิจิทัล + ยืนยันรหัสผ่าน ลายเซ็นทุกขั้นถูกผูกต่อกันเป็นห่วงโซ่ด้วย HMAC-SHA256 หากข้อมูลถูกแก้ไขภายหลัง การตรวจสอบจะไม่ผ่าน</p></div>
</div>

<div class="flow-bar reveal mb-4" style="--i:1">
  <?php foreach (SIGN_STEPS as $k => $s): ?>
    <div class="flow-step <?= $k === $stepKey ? 'is-me' : '' ?>" style="--c:<?= $s['color'] ?>"><span><i class="bi <?= $s['icon'] ?>"></i></span><small><?= e($s['label']) ?></small></div>
    <i class="bi bi-chevron-right flow-arrow"></i>
  <?php endforeach; ?>
  <div class="flow-step" style="--c:#ef4444"><span><i class="bi bi-check2-circle"></i></span><small>Admin ยืนยัน · จัดเจ้าหน้าที่</small></div>
</div>

<div class="chip-tabs mb-3">
  <a class="chip <?= $tab === 'pending' ? 'active' : '' ?>" href="?tab=pending" style="--c:#f59e0b"><i class="bi bi-hourglass-split"></i>รอท่านลงนาม <span class="count"><?= count($pending) ?></span></a>
  <a class="chip <?= $tab === 'history' ? 'active' : '' ?>" href="?tab=history"><i class="bi bi-clock-history"></i>ประวัติการลงนาม <span class="count"><?= count($history) ?></span></a>
</div>

<?php if ($tab === 'pending'): ?>
  <?php if (!$pending): ?><div class="card-x empty-state reveal"><div class="empty-icon" style="--c:#10b981"><i class="bi bi-check2-all"></i></div><h5>ไม่มีรายการรอลงนาม</h5><p class="text-muted">ลงนามครบทุกรายการแล้ว</p></div><?php endif; ?>
  <div class="row g-3">
  <?php foreach ($pending as $i => $b): $chain = signature_chain($b); ?>
    <div class="col-md-6 col-xxl-4 reveal" style="--i:<?= $i ?>">
      <div class="card-x hover h-100"><div class="card-x-body d-flex flex-column h-100">
        <div class="d-flex justify-content-between mb-2"><span class="code-pill"><?= e($b['booking_no']) ?></span><?= booking_badge($b['status']) ?></div>
        <h6 class="mb-1"><?= e($b['title']) ?></h6>
        <p class="small text-muted mb-3"><?= e($b['requester_name']) ?><?= $b['department'] ? ' · ' . e($b['department']) : '' ?></p>
        <div class="meta-row mb-3"><span><i class="bi bi-door-open"></i><?= e($b['room_name']) ?></span><span><i class="bi bi-calendar"></i><?= e(time_range($b['start_datetime'], $b['end_datetime'])) ?></span><span><i class="bi bi-people"></i><?= (int)$b['attendees'] ?> คน</span></div>
        <div class="mini-chain mb-3">
          <?php foreach (SIGN_STEPS as $k => $s): $sg = $chain['steps'][$k] ?? null; ?>
            <span class="<?= $sg ? ($sg['valid'] ? 'ok' : 'bad') : ($k === $stepKey ? 'now' : '') ?>" title="<?= e($s['label'] . ($sg ? ' · ' . $sg['signer_name'] : '')) ?>"><i class="bi <?= $sg ? 'bi-check-lg' : $s['icon'] ?>"></i></span>
          <?php endforeach; ?>
          <small class="<?= $chain['valid'] ? 'verify-ok' : 'verify-bad' ?>"><?= $chain['valid'] ? 'ลายเซ็นก่อนหน้าถูกต้อง' : 'ลายเซ็นก่อนหน้าไม่ผ่าน' ?></small>
        </div>
        <button class="btn btn-primary mt-auto" data-sign="<?= $b['id'] ?>" data-no="<?= e($b['booking_no']) ?>" <?= $chain['valid'] ? '' : 'disabled' ?>><i class="bi bi-pen me-1"></i>พิจารณา / ลงนาม</button>
      </div></div>
    </div>
  <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card-x reveal"><div class="table-responsive"><table class="table table-x table-hover">
    <thead><tr><th>เลขที่</th><th>หัวข้อ</th><th>ห้อง</th><th>วันประชุม</th><th>ลงนามเมื่อ</th><th>ผลของท่าน</th><th>สถานะปัจจุบัน</th><th>ความถูกต้อง</th></tr></thead><tbody>
    <?php if (!$history): ?><tr><td colspan="8"><div class="empty-mini">ยังไม่มีประวัติ</div></td></tr><?php endif; ?>
    <?php foreach ($history as $h): $ok = signature_chain($h)['valid']; ?>
      <tr><td><span class="code-pill"><?= e($h['booking_no']) ?></span></td><td><?= e($h['title']) ?><?= $h['sig_note'] ? '<br><small class="text-muted">' . e($h['sig_note']) . '</small>' : '' ?></td><td><?= e($h['room_name']) ?></td><td class="small"><?= e(time_range($h['start_datetime'], $h['end_datetime'])) ?></td><td class="small"><?= e(thai_date($h['sig_at'], 'datetime')) ?></td>
        <td><?= $h['decision'] === 'reject' ? badge('ไม่อนุมัติ', '#ef4444', 'bi-x-circle') : badge('อนุมัติ', '#10b981', 'bi-check2') ?></td><td><?= booking_badge($h['status'], true) ?></td>
        <td class="small <?= $ok ? 'verify-ok' : 'verify-bad' ?>"><i class="bi <?= $ok ? 'bi-patch-check-fill' : 'bi-exclamation-octagon' ?>"></i> <?= $ok ? 'ถูกต้อง' : 'ไม่ผ่าน' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>

<div class="modal fade" id="signModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="post" id="signForm">
  <?= csrf_field() ?><input type="hidden" name="id" id="sg_id"><input type="hidden" name="signature" id="sg_sig"><input type="hidden" name="decision" id="sg_decision" value="approve">
  <div class="modal-header"><h5 class="modal-title"><span class="modal-icon"><i class="bi bi-pen"></i></span>ลงนามดิจิทัล (<?= e($step['label']) ?>) · <span id="sg_no"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-4">
    <div class="col-lg-7" id="sg_detail"></div>
    <div class="col-lg-5">
      <div class="sign-sticky">
      <label class="form-label d-flex justify-content-between">ลงลายมือชื่อ <button type="button" class="btn btn-sm btn-ghost py-0" id="sg_clear"><i class="bi bi-eraser me-1"></i>ล้าง</button></label>
      <div class="sig-wrap mb-2"><canvas id="sigPad"></canvas><div class="sig-line"></div><div class="sig-ph">( <?= e(full_name($me)) ?> ) · <?= e($me['position'] ?: $step['label']) ?></div></div>
      <div class="form-text mb-3">ใช้เมาส์ ปากกา หรือนิ้วสัมผัสเพื่อเซ็นชื่อภายในกรอบ</div>
      <div class="mb-3"><label class="form-label">ความเห็น / เหตุผล</label><input class="form-control" name="note" id="sg_note" maxlength="255" placeholder="เช่น เห็นควรอนุมัติ / ไม่อนุมัติเนื่องจาก..."></div>
      <div class="mb-2"><label class="form-label">ยืนยันรหัสผ่านของท่าน <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text"><i class="bi bi-shield-lock"></i></span><input class="form-control" type="password" name="password" required autocomplete="current-password"></div></div>
      <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>เมื่ออนุมัติ เรื่องจะถูกส่งต่อให้ <strong><?= e($next ? SIGN_STEPS[$next]['label'] : 'ผู้ดูแลระบบ (ยืนยันและจัดเจ้าหน้าที่ประจำห้อง)') ?></strong></div>
      </div>
    </div>
  </div></div>
  <div class="modal-footer">
    <button type="button" class="btn btn-soft me-auto" style="--c:#ef4444" data-decide="reject"><i class="bi bi-x-circle me-1"></i>ไม่อนุมัติ</button>
    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">ปิด</button>
    <button type="button" class="btn btn-primary" data-decide="approve"><i class="bi bi-pen me-1"></i>ลงนามอนุมัติ</button>
  </div>
</form></div></div>
<?php
$extraScripts = <<<'JS'
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function(){
  const modalEl = document.getElementById('signModal'), modal = new bootstrap.Modal(modalEl), canvas = document.getElementById('sigPad');
  const pad = new SignaturePad(canvas, { penColor: '#1e3a8a', minWidth: 0.8, maxWidth: 2.6, backgroundColor: 'rgba(0,0,0,0)' });
  function fit() { const r = Math.max(window.devicePixelRatio || 1, 1); canvas.width = canvas.offsetWidth * r; canvas.height = canvas.offsetHeight * r; canvas.getContext('2d').scale(r, r); pad.clear(); }
  modalEl.addEventListener('shown.bs.modal', fit);
  window.addEventListener('resize', () => modalEl.classList.contains('show') && fit());
  document.getElementById('sg_clear').onclick = () => pad.clear();
  async function open(id, no) {
    document.getElementById('sg_id').value = id; document.getElementById('sg_no').textContent = no;
    document.getElementById('sg_note').value = '';
    document.getElementById('sg_detail').innerHTML = '<div class="text-center py-5"><div class="v3d-spinner mx-auto"></div></div>';
    modal.show();
    const j = await fetch(APP.base + '/api/booking.php?id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).catch(() => ({}));
    document.getElementById('sg_detail').innerHTML = j.ok ? j.html : 'โหลดข้อมูลไม่สำเร็จ';
  }
  document.querySelectorAll('[data-sign]').forEach(b => b.addEventListener('click', () => open(b.dataset.sign, b.dataset.no)));
  const p = new URLSearchParams(location.search).get('id');
  if (p) { const b = document.querySelector('[data-sign="' + CSS.escape(p) + '"]'); if (b) b.click(); }
  document.querySelectorAll('[data-decide]').forEach(btn => btn.addEventListener('click', () => {
    const f = document.getElementById('signForm'), d = btn.dataset.decide;
    if (pad.isEmpty()) return Swal.fire({ icon: 'warning', title: 'กรุณาลงลายมือชื่อ', confirmButtonColor: '#6366f1' });
    if (d === 'reject' && !document.getElementById('sg_note').value.trim()) return Swal.fire({ icon: 'warning', title: 'กรุณาระบุเหตุผลที่ไม่อนุมัติ', confirmButtonColor: '#6366f1' });
    if (!f.password.value) return f.password.focus();
    document.getElementById('sg_decision').value = d;
    document.getElementById('sg_sig').value = pad.toDataURL('image/png');
    Swal.fire({ icon: d === 'approve' ? 'question' : 'warning', title: d === 'approve' ? 'ยืนยันลงนามอนุมัติ?' : 'ยืนยันไม่อนุมัติ?',
      html: '<img src="' + pad.toDataURL() + '" style="max-height:90px;background:#fff;border-radius:10px;padding:4px">',
      showCancelButton: true, confirmButtonText: 'ยืนยันลงนาม', cancelButtonText: 'ยกเลิก', confirmButtonColor: d === 'approve' ? '#6366f1' : '#ef4444', reverseButtons: true
    }).then(r => r.isConfirmed && f.submit());
  }));
})();
</script>
JS;
require __DIR__ . '/../includes/footer.php';
