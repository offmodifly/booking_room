<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('buildings');

$b = db_one('SELECT * FROM buildings WHERE id=?', [(int)qs('id')]);
if (!$b) { flash('error', 'ไม่พบอาคาร'); redirect('admin/buildings.php'); }
$images = db_all('SELECT * FROM building_images WHERE building_id=? ORDER BY sort_order, id', [$b['id']]);
$rooms = db_all('SELECT * FROM rooms WHERE building_id=? ORDER BY floor, name', [$b['id']]);
$today = date('Y-m-d');
$todayBk = [];
foreach (db_all("SELECT bk.* FROM bookings bk JOIN rooms r ON r.id=bk.room_id WHERE r.building_id=? AND bk.status IN " . ACTIVE_SQL . " AND bk.start_datetime<=? AND bk.end_datetime>=?", [$b['id'], "$today 23:59:59", "$today 00:00:00"]) as $x) $todayBk[$x['room_id']][] = $x;
$roomData = array_map(fn($r) => [
    'id' => $r['id'], 'name' => $r['name'], 'floor' => (int)$r['floor'], 'x' => (float)$r['pos_x'], 'y' => (float)$r['pos_y'],
    'status' => room_effective_status($r['status'], $todayBk[$r['id']] ?? []), 'capacity' => (int)$r['capacity'], 'code' => $r['code'],
    'url' => url('admin/room_view.php?id=' . $r['id']),
], $rooms);
$cfg = [...building_3d_cfg($b, $images), 'rooms' => $roomData];

$pageTitle = 'โมเดล 3D · ' . $b['name'];
$activeMenu = 'buildings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><a href="<?= url('admin/buildings.php') ?>" class="eyebrow"><i class="bi bi-arrow-left"></i>กลับรายการอาคาร</a>
    <h2><?= e($b['name']) ?> <span class="code-pill align-middle"><?= e($b['code']) ?></span></h2>
    <p><i class="bi bi-geo-alt"></i> <?= e($b['location'] ?: '-') ?> · <?= (int)$b['floors'] ?> ชั้น · <?= (float)$b['width_m'] ?>×<?= (float)$b['depth_m'] ?> เมตร · <?= count($rooms) ?> ห้องประชุม</p></div>
  <div class="actions">
    <a href="<?= url('admin/room_form.php?building_id=' . $b['id']) ?>" class="btn btn-ghost"><i class="bi bi-plus-lg me-1"></i>เพิ่มห้องในอาคารนี้</a>
    <a href="<?= url('admin/building_form.php?id=' . $b['id']) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>แก้ไข / เพิ่มรูป</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="v3d reveal" id="viewer" style="height:600px">
      <div class="v3d-loading"><div class="text-center"><div class="v3d-spinner mx-auto mb-3"></div><div class="small text-muted">กำลังประมวลผลรูปภาพเป็นโมเดล 3 มิติ...</div></div></div>
      <div class="v3d-toolbar">
        <button class="btn btn-sm" id="btnXray"><i class="bi bi-transparency me-1"></i>X-Ray ดูห้องภายใน</button>
        <button class="btn btn-sm" id="btnRotate"><i class="bi bi-arrow-repeat me-1"></i>หมุนอัตโนมัติ</button>
        <button class="btn btn-sm" id="btnReset"><i class="bi bi-bullseye me-1"></i>มุมเริ่มต้น</button>
        <button class="btn btn-sm" id="btnShot"><i class="bi bi-camera me-1"></i>บันทึกภาพ</button>
        <button class="btn btn-sm" id="btnFull"><i class="bi bi-arrows-fullscreen"></i></button>
      </div>
      <div class="v3d-info" id="roomInfo"></div>
      <div class="v3d-hint"><i class="bi bi-mouse me-1"></i>ลากเพื่อหมุน · สกรอลล์เพื่อซูม · คลิกห้องเพื่อดูรายละเอียด</div>
    </div>
    <?php if (!$images): ?><div class="alert alert-info mt-3 small"><i class="bi bi-magic me-1"></i>โมเดลนี้ปั้นจากลักษณะอาคารที่กำหนดไว้ — <a href="<?= url('admin/building_form.php?id=' . $b['id']) ?>#style">ปรับลักษณะอาคาร</a> หรืออัปโหลดรูปถ่ายแต่ละด้านเพื่อให้ผนังเหมือนจริง</div><?php endif; ?>
  </div>
  <div class="col-xl-4">
    <div class="card-x reveal mb-4" style="--i:1"><div class="card-x-head"><h5><i class="bi bi-door-open"></i>ห้องประชุมในอาคาร</h5></div>
      <div class="card-x-body pt-2">
        <?php if (!$rooms): ?><div class="empty-mini"><i class="bi bi-door-closed"></i>ยังไม่มีห้องประชุม</div><?php endif; ?>
        <ul class="feed">
          <?php foreach ($roomData as $r): $s = ROOM_STATUS[$r['status']]; ?>
            <li><span class="fi" style="--c:<?= $s['color'] ?>"><i class="bi bi-door-open"></i></span>
              <div class="flex-grow-1"><strong><?= e($r['name']) ?></strong><p>ชั้น <?= $r['floor'] ?> · <?= $r['capacity'] ?> ที่นั่ง · พิกัด (<?= $r['x'] ?>%, <?= $r['y'] ?>%)</p></div>
              <div class="text-end"><?= room_badge($r['status']) ?><br><a class="small" href="<?= e($r['url']) ?>">ดู 3D <i class="bi bi-arrow-right"></i></a></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php if ($b['latitude'] && $b['longitude']): ?>
    <div class="card-x reveal mb-4" style="--i:2"><div class="card-x-head"><h5><i class="bi bi-map"></i>ตำแหน่งที่ตั้ง</h5><a class="small" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= (float)$b['latitude'] ?>,<?= (float)$b['longitude'] ?>">Google Maps <i class="bi bi-box-arrow-up-right"></i></a></div>
      <div class="card-x-body"><iframe class="gmap" style="height:240px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="แผนที่ <?= e($b['name']) ?>" src="https://maps.google.com/maps?q=<?= (float)$b['latitude'] ?>,<?= (float)$b['longitude'] ?>&amp;z=17&amp;hl=th&amp;output=embed"></iframe></div></div>
    <?php endif; ?>
  </div>
  <?php if ($images): ?>
  <div class="col-12"><div class="card-x reveal"><div class="card-x-head"><h5><i class="bi bi-images"></i>รูปภาพต้นฉบับ (<?= count($images) ?>)</h5></div>
    <div class="card-x-body"><div class="gallery">
      <?php foreach ($images as $img): ?><a href="<?= e(upload_url($img['file'])) ?>" target="_blank"><img src="<?= e(upload_url($img['file'])) ?>" alt="" loading="lazy"><span class="g-tag"><?= e(BUILDING_FACES[$img['face']]) ?></span></a><?php endforeach; ?>
    </div></div></div></div>
  <?php endif; ?>
</div>

<?php
$extraScripts = '
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
<script type="module">
import { createBuildingViewer } from "' . asset('js/viewer3d.js') . '";
const cfg = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
const STATUS = ' . json_encode(ROOM_STATUS, JSON_UNESCAPED_UNICODE) . ';
const el = document.getElementById("viewer"), info = document.getElementById("roomInfo");
const v = createBuildingViewer(el, { ...cfg, onRoomClick(r) {
  const s = STATUS[r.status];
  info.innerHTML = `<div class="d-flex justify-content-between align-items-start mb-2"><strong>${escapeHtml(r.name)}</strong><button class="btn-close btn-close-white btn-sm" id="infoClose"></button></div>
    <div class="small opacity-75 mb-2">${escapeHtml(r.code || "")} · ชั้น ${r.floor} · ${r.capacity} ที่นั่ง</div>
    <span class="badge-soft" style="--c:${s.color}">${s.label}</span>
    <a class="btn btn-primary btn-sm w-100 mt-3" href="${r.url}"><i class="bi bi-badge-3d me-1"></i>เข้าชมห้อง 3D</a>`;
  info.classList.add("show");
  document.getElementById("infoClose").onclick = () => info.classList.remove("show");
}});
const bx = document.getElementById("btnXray"), br = document.getElementById("btnRotate");
const sync = () => { bx.classList.toggle("active", v.xray); br.classList.toggle("active", v.autoRotate); };
sync();
bx.onclick = () => { v.setXray(!v.xray); sync(); };
br.onclick = () => { v.setAutoRotate(!v.autoRotate); sync(); };
el.addEventListener("pointerdown", () => setTimeout(sync, 10));
document.getElementById("btnReset").onclick = () => v.resetView();
document.getElementById("btnShot").onclick = () => v.screenshot("building-' . (int)$b['id'] . '.png");
document.getElementById("btnFull").onclick = () => document.fullscreenElement ? document.exitFullscreen() : el.requestFullscreen();
</script>';
require __DIR__ . '/../includes/footer.php';
