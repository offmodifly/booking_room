<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('rooms');

$r = db_one('SELECT r.*, b.name building_name, b.floors, b.width_m bw, b.depth_m bd, b.color bcolor, b.style bstyle FROM rooms r JOIN buildings b ON b.id=r.building_id WHERE r.id=?', [(int)qs('id')]);
if (!$r) { flash('error', 'ไม่พบห้องประชุม'); redirect('admin/rooms.php'); }
$images = db_all('SELECT * FROM room_images WHERE room_id=? ORDER BY sort_order, id', [$r['id']]);
$bImages = db_all('SELECT * FROM building_images WHERE building_id=? ORDER BY sort_order, id', [$r['building_id']]);
$today = date('Y-m-d');
$todayBk = db_all("SELECT * FROM bookings WHERE room_id=? AND status IN " . ACTIVE_SQL . " AND start_datetime<=? AND end_datetime>=?", [$r['id'], "$today 23:59:59", "$today 00:00:00"]);
$eff = room_effective_status($r['status'], $todayBk);
$upcoming = db_all("SELECT * FROM bookings WHERE room_id=? AND status IN " . ACTIVE_SQL . " AND end_datetime>=NOW() ORDER BY start_datetime LIMIT 8", [$r['id']]);
$siblings = db_all('SELECT id,name,floor,pos_x,pos_y,status FROM rooms WHERE building_id=?', [$r['building_id']]);

$roomCfg = ['width' => (float)$r['width_m'], 'depth' => (float)$r['depth_m'], 'height' => (float)$r['height_m'], 'capacity' => (int)$r['capacity'], 'color' => $r['color'], 'name' => $r['name'],
    'layout' => room_layout($r['layout'], (int)$r['capacity'], (string)$r['equipment']),
    'images' => (function () use ($images) {
        $m = assign_faces($images, 'wall', ['front', 'right', 'back', 'left']);
        return $m;
    })()];
$bCfg = [...building_3d_cfg(['name' => $r['building_name'], 'floors' => $r['floors'], 'width_m' => $r['bw'], 'depth_m' => $r['bd'], 'color' => $r['bcolor'], 'style' => $r['bstyle']], $bImages),
    'highlightRoomId' => $r['id'], 'xray' => true,
    'rooms' => array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name'], 'floor' => (int)$s['floor'], 'x' => (float)$s['pos_x'], 'y' => (float)$s['pos_y'], 'status' => (int)$s['id'] === (int)$r['id'] ? $eff : $s['status']], $siblings)];

$pageTitle = '3D · ' . $r['name'];
$activeMenu = 'rooms';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><a href="<?= url('admin/rooms.php') ?>" class="eyebrow"><i class="bi bi-arrow-left"></i>กลับรายการห้อง</a>
    <h2><?= e($r['name']) ?> <span class="code-pill align-middle"><?= e($r['code']) ?></span> <?= room_badge($eff) ?></h2>
    <p><i class="bi bi-building"></i> <?= e($r['building_name']) ?> · ชั้น <?= (int)$r['floor'] ?> · <?= (float)$r['width_m'] ?>×<?= (float)$r['depth_m'] ?>×<?= (float)$r['height_m'] ?> ม. · <?= (int)$r['capacity'] ?> ที่นั่ง</p></div>
  <div class="actions">
    <a href="<?= url('admin/bookings.php?new=1&room_id=' . $r['id']) ?>" class="btn btn-ghost"><i class="bi bi-calendar-plus me-1"></i>จองห้องนี้</a>
    <a href="<?= url('admin/room_form.php?id=' . $r['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>แก้ไข / เพิ่มรูป</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="v3d reveal" id="roomViewer" style="height:580px">
      <div class="v3d-loading"><div class="text-center"><div class="v3d-spinner mx-auto mb-3"></div><div class="small text-muted">กำลังสร้างห้อง 3 มิติจากรูปภาพ...</div></div></div>
      <div class="v3d-toolbar">
        <button class="btn btn-sm active" data-mode="inside"><i class="bi bi-eye me-1"></i>มุมมองภายใน 360°</button>
        <button class="btn btn-sm" data-mode="dollhouse"><i class="bi bi-box me-1"></i>Dollhouse</button>
        <button class="btn btn-sm" id="btnFurn"><i class="bi bi-easel me-1"></i>เฟอร์นิเจอร์</button>
        <button class="btn btn-sm" id="btnRot"><i class="bi bi-arrow-repeat"></i></button>
        <button class="btn btn-sm" id="btnShot"><i class="bi bi-camera"></i></button>
        <button class="btn btn-sm" id="btnFull"><i class="bi bi-arrows-fullscreen"></i></button>
      </div>
      <div class="v3d-hint"><i class="bi bi-hand-index me-1"></i>ลากเพื่อมองรอบห้อง · สกรอลล์เพื่อซูม</div>
    </div>
    <?php if (!$images): ?><div class="alert alert-info mt-3 small"><i class="bi bi-magic me-1"></i>ห้องนี้จัดฉากจากลักษณะห้องที่กำหนด (เวที โต๊ะ เก้าอี้ อุปกรณ์) — <a href="<?= url('admin/room_form.php?id=' . $r['id']) ?>#layout">ปรับการจัดห้อง</a> หรืออัปโหลดรูปผนังแต่ละด้านเพื่อให้สมจริง</div><?php endif; ?>
  </div>
  <div class="col-xl-4">
    <div class="card-x reveal mb-4" style="--i:1"><div class="card-x-head"><h5><i class="bi bi-geo"></i>ตำแหน่งในอาคาร</h5><a class="small" href="<?= url('admin/building_view.php?id=' . $r['building_id']) ?>">ดูทั้งอาคาร</a></div>
      <div class="v3d" id="bViewer" style="height:260px;border:0;border-radius:0 0 var(--radius) var(--radius)"><div class="v3d-loading"><div class="v3d-spinner"></div></div></div>
    </div>
    <div class="card-x reveal mb-4" style="--i:2"><div class="card-x-head"><h5><i class="bi bi-calendar-event"></i>การจองที่กำลังจะมาถึง</h5></div>
      <div class="card-x-body pt-2">
        <?php if (!$upcoming): ?><div class="empty-mini"><i class="bi bi-calendar-check"></i>ว่าง ไม่มีการจอง</div><?php endif; ?>
        <ul class="feed"><?php foreach ($upcoming as $u): $bs = BOOKING_STATUS[$u['status']]; ?>
          <li><span class="fi" style="--c:<?= $bs['color'] ?>"><i class="bi <?= $bs['icon'] ?>"></i></span><div><strong><?= e($u['title']) ?></strong><p><?= e(time_range($u['start_datetime'], $u['end_datetime'])) ?> · <?= e($bs['short']) ?></p></div></li>
        <?php endforeach; ?></ul>
      </div>
    </div>
    <div class="card-x reveal" style="--i:3"><div class="card-x-body"><dl class="dl" style="grid-template-columns:110px 1fr">
      <dt>อุปกรณ์</dt><dd><?= nl2br(e($r['equipment'] ?: '-')) ?></dd>
      <dt>รายละเอียด</dt><dd><?= nl2br(e($r['description'] ?: '-')) ?></dd>
      <dt>พิกัด</dt><dd>X <?= (float)$r['pos_x'] ?>% · Y <?= (float)$r['pos_y'] ?>% · ชั้น <?= (int)$r['floor'] ?></dd>
    </dl></div></div>
  </div>
  <?php if ($images): ?>
  <div class="col-12"><div class="card-x reveal"><div class="card-x-head"><h5><i class="bi bi-images"></i>รูปภาพห้อง (<?= count($images) ?>)</h5></div>
    <div class="card-x-body"><div class="gallery"><?php foreach ($images as $img): ?><a href="<?= e(upload_url($img['file'])) ?>" target="_blank"><img src="<?= e(upload_url($img['file'])) ?>" alt="" loading="lazy"><span class="g-tag"><?= e(ROOM_WALLS[$img['wall']]) ?></span></a><?php endforeach; ?></div></div></div></div>
  <?php endif; ?>
</div>
<?php
$extraScripts = '
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
<script type="module">
import { createRoomViewer, createBuildingViewer } from "' . asset('js/viewer3d.js') . '";
const el = document.getElementById("roomViewer");
const v = createRoomViewer(el, ' . json_encode($roomCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');
createBuildingViewer(document.getElementById("bViewer"), ' . json_encode($bCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');
const modes = document.querySelectorAll("[data-mode]"), bf = document.getElementById("btnFurn"), br = document.getElementById("btnRot");
const sync = () => { modes.forEach(b => b.classList.toggle("active", b.dataset.mode === v.mode)); bf.classList.toggle("active", v.furniture); br.classList.toggle("active", v.autoRotate); };
modes.forEach(b => b.onclick = () => { v.setMode(b.dataset.mode); sync(); });
bf.onclick = () => { v.setFurniture(!v.furniture); sync(); };
br.onclick = () => { v.setAutoRotate(!v.autoRotate); sync(); };
el.addEventListener("pointerdown", () => setTimeout(sync, 10));
document.getElementById("btnShot").onclick = () => v.screenshot("room-' . (int)$r['id'] . '.png");
document.getElementById("btnFull").onclick = () => document.fullscreenElement ? document.exitFullscreen() : el.requestFullscreen();
sync();
</script>';
require __DIR__ . '/../includes/footer.php';
