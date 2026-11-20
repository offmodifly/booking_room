<?php
/** หน้าแรก (สาธารณะ) — ดูห้อง ปฏิทินห้องว่าง ขอใช้ห้อง ตรวจสอบสถานะ */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$rooms = public_rooms();
$today = date('Y-m-d');
$todayBk = [];
foreach (db_all("SELECT * FROM bookings WHERE status IN " . ACTIVE_SQL . " AND start_datetime<=? AND end_datetime>=?", ["$today 23:59:59", "$today 00:00:00"]) as $x) $todayBk[$x['room_id']][] = $x;
$freeToday = 0;
foreach ($rooms as &$r) { $r['eff'] = room_effective_status($r['status'], $todayBk[$r['id']] ?? []); if ($r['eff'] === 'available') $freeToday++; }
unset($r);
$stats = db_one("SELECT COUNT(*) total, SUM(status='approved' AND start_datetime>=NOW()) upcoming FROM bookings");
$buildings = (int)db_val('SELECT COUNT(*) FROM buildings');

public_header('หน้าแรก', 'home');
?>
<section class="pub-hero">
  <div class="grid-bg"></div>
  <div class="container position-relative">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="hero-chip"><span class="pulse"></span>วันนี้มีห้องว่าง <?= $freeToday ?> จาก <?= count($rooms) ?> ห้อง</span>
        <h1>จองห้องประชุม<br><span class="grad-text"><?= e(ORG_NAME) ?></span></h1>
        <p class="lead">ยื่นคำขอใช้ห้องได้ทันที <strong>ไม่ต้องสมัครสมาชิก</strong> แล้วติดตามผลการพิจารณาด้วยเบอร์โทรศัพท์ของคุณ</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <a class="btn btn-light btn-lg hero-btn" href="<?= url('request.php') ?>"><i class="bi bi-calendar-plus me-2"></i>ขอใช้ห้องประชุม</a>
          <a class="btn btn-outline-light btn-lg" href="<?= url('status.php') ?>"><i class="bi bi-search me-2"></i>ตรวจสอบสถานะ</a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="hero-stats">
          <div><strong data-count="<?= count($rooms) ?>">0</strong><span>ห้องประชุม</span></div>
          <div><strong data-count="<?= $buildings ?>">0</strong><span>อาคาร</span></div>
          <div><strong data-count="<?= (int)$stats['upcoming'] ?>">0</strong><span>นัดหมายที่จะถึง</span></div>
          <div><strong data-count="<?= (int)$stats['total'] ?>">0</strong><span>คำขอทั้งหมด</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container pub-body">
  <!-- ขั้นตอน -->
  <div class="how row g-3 mb-5">
    <?php foreach ([
      ['bi-ui-checks', 'กรอกแบบฟอร์ม', 'เลือกห้อง วันเวลา และกรอกข้อมูลพร้อมเบอร์โทร', '#6366f1'],
      ['bi-pen', 'ผู้อำนวยการลงนาม', 'ผู้อำนวยการพิจารณาและลงนามอนุมัติแบบดิจิทัล', '#8b5cf6'],
      ['bi-check2-circle', 'อนุมัติ · จัดเจ้าหน้าที่', 'ผู้ดูแลยืนยันและมอบหมายเจ้าหน้าที่ประจำห้อง', '#10b981'],
      ['bi-phone', 'ติดตามด้วยเบอร์โทร', 'ตรวจสอบสถานะได้ตลอดเวลาที่เมนูตรวจสอบสถานะ', '#0ea5e9'],
    ] as $i => [$ic, $t, $d, $c]): ?>
      <div class="col-sm-6 col-lg-3 reveal" style="--i:<?= $i ?>"><div class="how-item" style="--c:<?= $c ?>"><span class="how-no"><?= $i + 1 ?></span><i class="bi <?= $ic ?>"></i><h6><?= $t ?></h6><p><?= $d ?></p></div></div>
    <?php endforeach; ?>
  </div>

  <!-- ห้อง -->
  <div class="section-head reveal"><div><span class="eyebrow"><i class="bi bi-door-open"></i>Meeting Rooms</span><h3>ห้องประชุมทั้งหมด</h3></div>
    <div class="chip-tabs" id="roomFilter"><button class="chip active" data-f="all">ทั้งหมด <span class="count"><?= count($rooms) ?></span></button><button class="chip" data-f="available" style="--c:#10b981"><span class="swatch"></span>ว่างวันนี้ <span class="count"><?= $freeToday ?></span></button></div></div>
  <div class="row g-4 mb-5" id="roomGrid">
    <?php foreach ($rooms as $i => $r): $rs = ROOM_STATUS[$r['eff']]; $closed = $r['status'] === 'unavailable'; ?>
      <div class="col-sm-6 col-xl-4 reveal" data-eff="<?= $r['eff'] ?>" style="--i:<?= $i % 6 ?>">
        <div class="card-x hover h-100 d-flex flex-column pub-room">
          <div class="cover"><?php if ($r['cover']): ?><img src="<?= e(upload_url($r['cover'])) ?>" alt="" loading="lazy"><?php else: ?><div class="cover-ph" style="background:linear-gradient(135deg,<?= e($r['color']) ?>,#4c1d95)"><i class="bi bi-easel2"></i></div><?php endif; ?>
            <div class="cover-top"><span class="glass-pill"><?= e($r['building_name']) ?> · ชั้น <?= (int)$r['floor'] ?></span><span class="badge-soft solid" style="--c:<?= $rs['color'] ?>"><i class="bi <?= $rs['icon'] ?>"></i>วันนี้: <?= $rs['label'] ?></span></div></div>
          <div class="card-x-body d-flex flex-column flex-grow-1">
            <h5 class="mb-1"><?= e($r['name']) ?></h5>
            <div class="meta-row mb-2"><span><i class="bi bi-people"></i><?= (int)$r['capacity'] ?> ที่นั่ง</span><span><i class="bi bi-arrows-angle-expand"></i><?= (float)$r['width_m'] ?>×<?= (float)$r['depth_m'] ?> ม.</span></div>
            <?php if ($r['equipment']): ?><p class="small text-muted mb-3 line-2"><i class="bi bi-tools me-1"></i><?= e($r['equipment']) ?></p><?php endif; ?>
            <div class="mt-auto d-flex gap-2">
              <?php if ($closed): ?><button class="btn btn-ghost flex-fill" disabled><i class="bi bi-cone-striped me-1"></i>ปิดให้บริการชั่วคราว</button>
              <?php else: ?><a class="btn btn-primary flex-fill" href="<?= url('request.php?room=' . $r['id']) ?>"><i class="bi bi-calendar-plus me-1"></i>ขอใช้ห้องนี้</a><?php endif; ?>
              <button class="btn-icon" title="ดูตารางห้องนี้" data-room-cal="<?= $r['id'] ?>"><i class="bi bi-calendar3"></i></button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ปฏิทิน -->
  <div class="card-x reveal" id="calendarCard">
    <div class="card-x-head"><h5><i class="bi bi-calendar3"></i>ปฏิทินการใช้ห้อง</h5>
      <div class="d-flex gap-2 align-items-center"><span class="slot-legend d-none d-md-inline-flex"><i style="--c:#f59e0b"></i>จองแล้ว (รออนุมัติ) <i style="--c:#ef4444"></i>ไม่ว่าง</span>
        <select class="form-select form-select-sm" id="calRoom" style="width:auto"><option value="">ทุกห้อง</option><?php foreach ($rooms as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div></div>
    <div class="card-x-body"><div id="pubCal"></div><p class="small text-muted mt-3 mb-0"><i class="bi bi-hand-index me-1"></i>คลิกวันที่ว่างในปฏิทินเพื่อขอใช้ห้องในวันนั้น</p></div>
  </div>
</div>
<?php
public_footer('
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/th.global.min.js"></script>
<script src="' . asset('js/public.js') . '"></script>');
