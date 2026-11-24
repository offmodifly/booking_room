<?php
/** แสดงรายละเอียดการจอง (HTML) — ใช้ใน modal/offcanvas */
function booking_load(int $id): ?array
{
    return db_one("SELECT b.*, r.name room_name, r.code room_code, r.floor room_floor, r.capacity, bd.name building_name,
        a.prefix a_prefix, a.first_name a_first, a.last_name a_last
      FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id
      LEFT JOIN users a ON a.id=b.approved_by WHERE b.id=?", [$id]);
}
function booking_staff_list(int $id): array
{
    return db_all('SELECT u.* FROM booking_staff s JOIN users u ON u.id=s.user_id WHERE s.booking_id=? ORDER BY u.first_name', [$id]);
}

/** สถานะของแต่ละขั้นการลงนาม: done | reject | now | idle */
function sign_step_states(array $b, array $chain): array
{
    $out = [];
    $stopped = false;
    foreach (SIGN_STEPS as $k => $s) {
        $sig = $chain['steps'][$k] ?? null;
        if ($sig) { $out[$k] = $sig['decision'] === 'reject' ? 'reject' : 'done'; if ($sig['decision'] === 'reject') $stopped = true; continue; }
        if ($stopped || in_array($b['status'], ['cancelled', 'rejected'], true)) { $out[$k] = 'idle'; continue; }
        $out[$k] = ($s['from'] === $b['status']) ? 'now' : 'idle';
    }
    return $out;
}

/** แผงลายเซ็นทุกขั้น (ใช้ทั้งในหน้า admin และหน้าลงนาม) */
function render_signature_panel(array $b, array $chain, bool $showHash = true): string
{
    $states = sign_step_states($b, $chain);
    ob_start(); ?>
    <div class="sig-chain">
      <?php foreach (SIGN_STEPS as $k => $s): $sig = $chain['steps'][$k] ?? null; $state = $states[$k]; ?>
        <div class="sig-card is-<?= $state ?>" style="--c:<?= $s['color'] ?>">
          <div class="sig-card-head"><span class="sig-step-ic"><i class="bi <?= $state === 'reject' ? 'bi-x-lg' : $s['icon'] ?>"></i></span>
            <div class="min-w-0"><small><?= $k === 'requester' ? 'ผู้ขอใช้ห้อง' : 'ขั้นที่ ' . array_search($k, array_keys(SIGN_STEPS)) . ' · ผู้อนุมัติ' ?></small><strong><?= e($s['label']) ?></strong></div>
            <?php if ($sig): ?><span class="ms-auto"><?= $sig['decision'] === 'reject' ? badge('ไม่อนุมัติ', '#ef4444') : badge($k === 'requester' ? 'ลงนามแล้ว' : 'อนุมัติ', '#10b981') ?></span>
            <?php elseif ($state === 'now'): ?><span class="ms-auto"><?= badge('รอลงนาม', '#f59e0b', 'bi-hourglass-split') ?></span><?php endif; ?>
          </div>
          <?php if ($sig): ?>
            <div class="sig-card-img"><img src="<?= e(upload_url($sig['file'])) ?>" alt="ลายเซ็น <?= e($sig['signer_name']) ?>"></div>
            <div class="sig-card-name">( <?= e($sig['signer_name']) ?> )</div>
            <div class="sig-card-pos"><?= e($sig['signer_position']) ?></div>
            <div class="sig-card-meta"><i class="bi bi-clock"></i><?= e(thai_date($sig['signed_at'], 'datetime')) ?></div>
            <?php if ($sig['note']): ?><div class="sig-card-note"><i class="bi bi-chat-left-quote"></i><?= e($sig['note']) ?></div><?php endif; ?>
            <div class="sig-card-verify <?= $sig['valid'] ? 'verify-ok' : 'verify-bad' ?>"><i class="bi <?= $sig['valid'] ? 'bi-patch-check-fill' : 'bi-exclamation-octagon-fill' ?>"></i><?= $sig['valid'] ? 'ลายเซ็นถูกต้อง' : 'ตรวจสอบไม่ผ่าน' ?></div>
            <?php if ($showHash): ?><div class="hash" title="HMAC-SHA256"><?= e(substr($sig['hash'], 0, 32)) ?>…</div><?php endif; ?>
          <?php else: ?>
            <div class="sig-card-empty"><?= $state === 'now' ? 'รอการพิจารณา' : '—' ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}

function render_booking_detail(array $b): string
{
    $staff = booking_staff_list((int)$b['id']);
    $chain = signature_chain($b);
    $admin = trim($b['a_prefix'] . $b['a_first'] . ' ' . $b['a_last']);
    $st = $b['status'];
    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
      <div><div class="eyebrow"><i class="bi bi-hash"></i><?= e($b['booking_no']) ?></div><h5 class="mb-1 mt-1"><?= e($b['title']) ?></h5></div>
      <?= booking_badge($st) ?>
    </div>
    <dl class="dl mb-4">
      <dt>ห้องประชุม</dt><dd><strong><?= e($b['room_name']) ?></strong> <span class="text-muted">· <?= e($b['building_name']) ?> ชั้น <?= (int)$b['room_floor'] ?></span></dd>
      <dt>วัน-เวลา</dt><dd><?= e(time_range($b['start_datetime'], $b['end_datetime'])) ?></dd>
      <dt>ผู้ขอใช้</dt><dd><?= e($b['requester_name']) ?><?= $b['department'] ? ' <span class="text-muted">· ' . e($b['department']) . '</span>' : '' ?></dd>
      <dt>ติดต่อ</dt><dd><?= e($b['contact_phone'] ?: '-') ?></dd>
      <dt>จำนวนผู้เข้าร่วม</dt><dd><?= (int)$b['attendees'] ?> คน <span class="text-muted">(ความจุห้อง <?= (int)$b['capacity'] ?>)</span></dd>
      <dt>ขอใช้สถานที่เพื่อ</dt><dd><?= e($b['title']) ?></dd>
      <dt>อุปกรณ์ให้จัด</dt><dd><?php $eqLines = booking_equipment_lines($b['equip'] ?? null); ?><?= $eqLines ? '<ul class="eq-list">' . implode('', array_map(fn($l) => '<li>' . e($l) . '</li>', $eqLines)) . '</ul>' : '<span class="text-muted">ไม่ต้องการ</span>' ?></dd>
      <dt>รายละเอียดเพิ่มเติม</dt><dd><?= nl2br(e($b['purpose'] ?: '-')) ?></dd>
      <?php if ($b['admin_note']): ?><dt>หมายเหตุ Admin</dt><dd><?= e($b['admin_note']) ?></dd><?php endif; ?>
    </dl>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">ลายเซ็นดิจิทัลตามลำดับขั้น</h6>
      <?php if ($chain['steps']): ?><span class="small fw-semibold <?= $chain['valid'] ? 'verify-ok' : 'verify-bad' ?>"><i class="bi <?= $chain['valid'] ? 'bi-shield-check' : 'bi-shield-exclamation' ?>"></i> <?= $chain['valid'] ? 'ห่วงโซ่ลายเซ็นถูกต้อง' : 'พบการแก้ไขข้อมูลหลังลงนาม' ?></span><?php endif; ?>
    </div>
    <?= render_signature_panel($b, $chain) ?>

    <h6 class="mb-3 mt-4">การดำเนินการของผู้ดูแลระบบ</h6>
    <ul class="timeline mb-2">
      <li class="<?= $b['approved_at'] ? '' : 'off' ?>"><span class="tl-dot" style="--c:#ef4444"><i class="bi bi-check2-circle"></i></span><strong>Admin ยืนยัน · เปลี่ยนสถานะเป็น "ไม่ว่าง"</strong><small><?= $b['approved_at'] ? e(thai_date($b['approved_at'], 'datetime') . ' · ' . $admin) : ($st === 'signed' ? 'พร้อมดำเนินการ' : 'รอผู้อำนวยการอนุมัติก่อน') ?></small></li>
      <li class="<?= $staff ? '' : 'off' ?>"><span class="tl-dot" style="--c:#10b981"><i class="bi bi-person-check"></i></span><strong>มอบหมายเจ้าหน้าที่ประจำห้อง</strong><small><?= $staff ? e(implode(', ', array_map('full_name', $staff))) : 'ยังไม่มอบหมาย' ?></small></li>
    </ul>
    <?php return ob_get_clean();
}
