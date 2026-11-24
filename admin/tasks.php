<?php
/** แจ้งเตือนงานดูแลห้องประชุม — สำหรับเจ้าหน้าที่ประจำห้องประชุม (room_staff) */
require __DIR__ . '/../includes/bootstrap.php';
page_guard('tasks');
$me = current_user();

if (is_post()) {
    csrf_check();
    $bid = (int)input('booking_id');
    $act = (string)input('action');
    $row = db_one("SELECT s.*, b.booking_no, b.title, b.status, b.start_datetime, b.end_datetime, r.name room_name
        FROM booking_staff s JOIN bookings b ON b.id=s.booking_id JOIN rooms r ON r.id=b.room_id WHERE s.booking_id=? AND s.user_id=?", [$bid, $me['id']]);
    if (!$row || $row['status'] !== 'approved') {
        flash('error', 'ไม่พบงานนี้ หรือการจองถูกยกเลิกแล้ว');
    } elseif ($act === 'ack' && !$row['ack_at']) {
        db_exec('UPDATE booking_staff SET ack_at=NOW() WHERE booking_id=? AND user_id=?', [$bid, $me['id']]);
        log_action('booking', "รับทราบงานดูแลห้อง {$row['booking_no']} · {$row['room_name']} · " . time_range($row['start_datetime'], $row['end_datetime']));
        flash('success', "รับทราบงาน {$row['booking_no']} แล้ว — ท่านเป็นผู้รับผิดชอบห้อง {$row['room_name']}");
    } elseif ($act === 'done') {
        db_exec('UPDATE booking_staff SET ack_at=COALESCE(ack_at, NOW()), done_at=NOW() WHERE booking_id=? AND user_id=?', [$bid, $me['id']]);
        log_action('booking', "จัดเตรียมห้องเรียบร้อย {$row['booking_no']} · {$row['room_name']}");
        flash('success', "บันทึกว่าจัดเตรียมห้อง {$row['room_name']} เรียบร้อยแล้ว");
    } elseif ($act === 'undo_done') {
        db_exec('UPDATE booking_staff SET done_at=NULL WHERE booking_id=? AND user_id=?', [$bid, $me['id']]);
        flash('success', 'ยกเลิกสถานะ "จัดเตรียมเรียบร้อย" แล้ว');
    }
    redirect('admin/tasks.php' . (qs('tab') ? '?tab=' . urlencode(qs('tab')) : '') . '#task-' . $bid);
}

$base = "SELECT b.*, s.assigned_at, s.ack_at, s.done_at, r.name room_name, r.code room_code, r.floor room_floor, r.capacity, bd.name building_name,
    CONCAT(a.prefix, a.first_name, ' ', a.last_name) assigned_by_name,
    (SELECT GROUP_CONCAT(CONCAT(u.prefix,u.first_name,' ',u.last_name) SEPARATOR ', ') FROM booking_staff s2 JOIN users u ON u.id=s2.user_id WHERE s2.booking_id=b.id AND s2.user_id<>s.user_id) co_staff
  FROM booking_staff s JOIN bookings b ON b.id=s.booking_id JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id
  LEFT JOIN users a ON a.id=s.assigned_by
  WHERE s.user_id=? AND b.status='approved'";
$all = [
    'new'      => db_all("$base AND s.ack_at IS NULL AND b.end_datetime>=NOW() ORDER BY b.start_datetime", [$me['id']]),
    'upcoming' => db_all("$base AND s.ack_at IS NOT NULL AND b.end_datetime>=NOW() ORDER BY b.start_datetime", [$me['id']]),
    'past'     => db_all("$base AND b.end_datetime<NOW() ORDER BY b.start_datetime DESC LIMIT 30", [$me['id']]),
];
$tabs = [
    'new'      => ['รอรับทราบ', 'bi-bell-fill', '#ef4444'],
    'upcoming' => ['ที่รับผิดชอบ', 'bi-clipboard-check', '#6366f1'],
    'past'     => ['ผ่านไปแล้ว', 'bi-clock-history', '#64748b'],
];
$tab = isset($tabs[qs('tab')]) ? qs('tab') : ($all['new'] ? 'new' : 'upcoming');
$list = $all[$tab];
$today = date('Y-m-d');

$pageTitle = 'แจ้งเตือน';
$activeMenu = 'tasks';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-bell"></i>Room Staff Tasks</span><h2>แจ้งเตือนงานดูแลห้องประชุม</h2>
    <p>รายการประชุมที่ผู้ดูแลระบบมอบหมายให้ท่านรับผิดชอบ — กด <strong>รับทราบ / รับผิดชอบ</strong> เมื่อได้รับงาน และ <strong>จัดเตรียมเรียบร้อย</strong> เมื่อเตรียมห้องเสร็จ</p></div>
</div>

<div class="task-stats reveal" style="--i:1">
  <?php foreach ($tabs as $k => [$label, $icon, $color]): ?>
    <a class="task-stat <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>" style="--c:<?= $color ?>">
      <i class="bi <?= $icon ?>"></i><div><strong><?= count($all[$k]) ?><?= $k === 'past' && count($all[$k]) >= 30 ? '+' : '' ?></strong><span><?= e($label) ?></span></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$list): ?>
  <div class="card-x empty-state reveal mt-4"><div class="empty-icon"><i class="bi <?= $tab === 'new' ? 'bi-bell-slash' : 'bi-clipboard' ?>"></i></div>
    <h5><?= $tab === 'new' ? 'ไม่มีงานใหม่ที่รอรับทราบ' : ($tab === 'upcoming' ? 'ยังไม่มีงานที่รับผิดชอบ' : 'ยังไม่มีประวัติงาน') ?></h5>
    <p class="text-muted mb-0">เมื่อผู้ดูแลระบบอนุมัติการจองและมอบหมายให้ท่าน งานจะแสดงที่นี่</p></div>
<?php else: ?>
<div class="task-list mt-4">
  <?php foreach ($list as $i => $b):
      $eq = booking_equipment_lines($b['equip'] ?? null);
      $isToday = substr($b['start_datetime'], 0, 10) <= $today && substr($b['end_datetime'], 0, 10) >= $today;
      $state = $b['done_at'] ? ['จัดเตรียมเรียบร้อย', 'success', 'bi-check2-all'] : ($b['ack_at'] ? ['รับทราบแล้ว', 'primary', 'bi-check2'] : ['รอรับทราบ', 'danger', 'bi-bell-fill']);
  ?>
  <div class="card-x task-card reveal <?= !$b['ack_at'] && $tab !== 'past' ? 'is-new' : '' ?>" id="task-<?= $b['id'] ?>" style="--i:<?= $i % 8 ?>">
    <div class="task-date">
      <span class="d"><?= (int)date('j', strtotime($b['start_datetime'])) ?></span>
      <span class="m"><?= TH_MONTHS_S[(int)date('n', strtotime($b['start_datetime']))] ?> <?= (int)date('Y', strtotime($b['start_datetime'])) + 543 ?></span>
      <?php if ($isToday && $tab !== 'past'): ?><span class="badge text-bg-warning mt-1">วันนี้</span><?php endif; ?>
    </div>
    <div class="task-body">
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
        <div><span class="code-pill me-1"><?= e($b['booking_no']) ?></span><span class="badge rounded-pill text-bg-<?= $state[1] ?>"><i class="bi <?= $state[2] ?> me-1"></i><?= $state[0] ?></span></div>
        <small class="text-muted">มอบหมายโดย <?= e($b['assigned_by_name'] ?: 'ผู้ดูแลระบบ') ?> · <?= e(time_ago($b['assigned_at'])) ?></small>
      </div>
      <h5 class="mb-2"><?= e($b['title']) ?></h5>
      <dl class="dl task-dl">
        <dt><i class="bi bi-door-open"></i>ห้อง</dt><dd><strong><?= e($b['room_name']) ?></strong> <span class="text-muted">· <?= e($b['building_name']) ?> ชั้น <?= (int)$b['room_floor'] ?></span></dd>
        <dt><i class="bi bi-clock"></i>วัน-เวลา</dt><dd><?= booking_when_html($b['start_datetime'], $b['end_datetime']) ?></dd>
        <dt><i class="bi bi-people"></i>ผู้เข้าร่วม</dt><dd><?= (int)$b['attendees'] ?> คน <span class="text-muted">(ความจุ <?= (int)$b['capacity'] ?>)</span></dd>
        <dt><i class="bi bi-person"></i>ผู้ขอใช้</dt><dd><?= e($b['requester_name']) ?><?= $b['department'] ? ' <span class="text-muted">· ' . e($b['department']) . '</span>' : '' ?><?php if ($b['contact_phone']): ?> · <a href="tel:<?= e(phone_digits($b['contact_phone'])) ?>"><i class="bi bi-telephone"></i> <?= e($b['contact_phone']) ?></a><?php endif; ?></dd>
        <dt><i class="bi bi-tools"></i>อุปกรณ์ให้จัด</dt><dd><?= $eq ? '<div class="task-eq">' . implode('', array_map(fn($l) => '<span>' . e($l) . '</span>', $eq)) . '</div>' : '<span class="text-muted">ไม่ต้องการ</span>' ?></dd>
        <?php if ($b['purpose']): ?><dt><i class="bi bi-card-text"></i>รายละเอียด</dt><dd><?= nl2br(e($b['purpose'])) ?></dd><?php endif; ?>
        <?php if ($b['admin_note']): ?><dt><i class="bi bi-chat-left-text"></i>หมายเหตุ Admin</dt><dd><?= e($b['admin_note']) ?></dd><?php endif; ?>
        <?php if ($b['co_staff']): ?><dt><i class="bi bi-person-badge"></i>ร่วมกับ</dt><dd><?= e($b['co_staff']) ?></dd><?php endif; ?>
      </dl>
      <?php if ($tab !== 'past'): ?>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if (!$b['ack_at']): ?>
          <form method="post" action="?tab=<?= $tab ?>"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="ack">
            <button class="btn btn-primary"><i class="bi bi-hand-thumbs-up me-1"></i>รับทราบ / รับผิดชอบ</button></form>
        <?php elseif (!$b['done_at']): ?>
          <span class="small text-muted align-self-center"><i class="bi bi-check2 me-1"></i>รับทราบเมื่อ <?= e(thai_date($b['ack_at'], 'datetime')) ?></span>
          <form method="post" action="?tab=<?= $tab ?>" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="done">
            <button class="btn btn-success"><i class="bi bi-check2-all me-1"></i>จัดเตรียมห้องเรียบร้อย</button></form>
        <?php else: ?>
          <span class="small text-success align-self-center"><i class="bi bi-check2-all me-1"></i>จัดเตรียมเรียบร้อยเมื่อ <?= e(thai_date($b['done_at'], 'datetime')) ?></span>
          <form method="post" action="?tab=<?= $tab ?>" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="undo_done">
            <button class="btn btn-sm btn-ghost"><i class="bi bi-arrow-counterclockwise me-1"></i>ยกเลิกสถานะ</button></form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
