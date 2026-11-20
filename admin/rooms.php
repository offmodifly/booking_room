<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('rooms');

if (is_post()) {
    csrf_check();
    $id = (int)input('id');
    $r = db_one('SELECT * FROM rooms WHERE id=?', [$id]);
    if (!$r) { flash('error', 'ไม่พบห้องประชุม'); redirect('admin/rooms.php'); }
    if (input('action') === 'status' && isset(ROOM_STATUS[input('status')])) {
        db_exec('UPDATE rooms SET status=? WHERE id=?', [input('status'), $id]);
        log_action('room', "เปลี่ยนสถานะห้อง {$r['name']} เป็น " . ROOM_STATUS[input('status')]['label']);
        flash('success', 'เปลี่ยนสถานะห้องเรียบร้อย');
    } elseif (input('action') === 'delete') {
        if (db_val('SELECT COUNT(*) FROM bookings WHERE room_id=?', [$id])) {
            flash('error', 'ห้องนี้มีประวัติการจอง ไม่สามารถลบได้ — แนะนำให้ตั้งสถานะเป็น "ไม่ว่าง" แทน');
        } else {
            foreach (db_all('SELECT file FROM room_images WHERE room_id=?', [$id]) as $img) delete_upload($img['file']);
            db_exec('DELETE FROM rooms WHERE id=?', [$id]);
            log_action('room', "ลบห้องประชุม {$r['name']}");
            flash('success', 'ลบห้องประชุมเรียบร้อย');
        }
    }
    redirect('admin/rooms.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
}

$bid = (int)qs('building_id');
$params = []; $where = '';
if ($bid) { $where = 'WHERE r.building_id=?'; $params[] = $bid; }
$rooms = db_all("SELECT r.*, b.name building_name, b.floors building_floors,
   (SELECT file FROM room_images i WHERE i.room_id=r.id ORDER BY (wall='front') DESC, sort_order, id LIMIT 1) cover,
   (SELECT COUNT(*) FROM room_images i WHERE i.room_id=r.id) imgs,
   (SELECT COUNT(*) FROM bookings k WHERE k.room_id=r.id AND k.status IN " . ACTIVE_SQL . " AND k.end_datetime>=NOW()) upcoming
  FROM rooms r JOIN buildings b ON b.id=r.building_id $where ORDER BY b.name, r.floor, r.name", $params);
$today = date('Y-m-d');
$todayBk = [];
foreach (db_all("SELECT * FROM bookings WHERE status IN " . ACTIVE_SQL . " AND start_datetime<=? AND end_datetime>=?", ["$today 23:59:59", "$today 00:00:00"]) as $x) $todayBk[$x['room_id']][] = $x;
$buildings = db_all('SELECT id,name FROM buildings ORDER BY name');
$imgsBy = [];
foreach (db_all('SELECT * FROM room_images ORDER BY sort_order, id') as $img) $imgsBy[$img['room_id']][] = $img;
$snapCfg = fn($r) => ['width' => (float)$r['width_m'], 'depth' => (float)$r['depth_m'], 'height' => (float)$r['height_m'], 'color' => $r['color'],
    'layout' => room_layout($r['layout'], (int)$r['capacity'], (string)$r['equipment']),
    'images' => assign_faces($imgsBy[$r['id']] ?? [], 'wall', ['front', 'right', 'back', 'left'])];

$pageTitle = 'จัดการห้องประชุม';
$activeMenu = 'rooms';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-door-open"></i>Meeting Rooms</span><h2>จัดการห้องประชุม</h2><p>กำหนดชื่อห้อง อาคาร ชั้น พิกัดภายในอาคาร รูปภาพ (สร้าง 3D) และสถานะ ว่าง / จอง / ไม่ว่าง</p></div>
  <div class="actions"><a href="<?= url('admin/room_form.php' . ($bid ? '?building_id=' . $bid : '')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มห้องประชุม</a></div>
</div>

<div class="d-flex flex-wrap gap-2 align-items-center mb-4 reveal" style="--i:1">
  <div class="chip-tabs">
    <a class="chip <?= !$bid ? 'active' : '' ?>" href="?">ทุกอาคาร</a>
    <?php foreach ($buildings as $b): ?><a class="chip <?= $bid === (int)$b['id'] ? 'active' : '' ?>" href="?building_id=<?= $b['id'] ?>"><i class="bi bi-building"></i><?= e($b['name']) ?></a><?php endforeach; ?>
  </div>
  <div class="search-box ms-auto"><i class="bi bi-search"></i><input class="form-control form-control-sm" placeholder="ค้นหาห้อง..." id="roomSearch" style="min-width:220px"></div>
</div>

<?php if (!$rooms): ?>
  <div class="card-x empty-state reveal"><div class="empty-icon"><i class="bi bi-door-closed"></i></div><h5>ยังไม่มีห้องประชุม</h5><p class="text-muted"><?= $buildings ? 'เพิ่มห้องประชุมห้องแรก' : 'กรุณาเพิ่มอาคารก่อน' ?></p><a href="<?= url($buildings ? 'admin/room_form.php' : 'admin/building_form.php') ?>" class="btn btn-primary"><?= $buildings ? 'เพิ่มห้องประชุม' : 'เพิ่มอาคาร' ?></a></div>
<?php endif; ?>

<div class="row g-4" id="roomGrid">
<?php foreach ($rooms as $i => $r): $eff = room_effective_status($r['status'], $todayBk[$r['id']] ?? []); $s = ROOM_STATUS[$eff]; ?>
  <div class="col-md-6 col-xl-4 col-xxl-3 reveal" style="--i:<?= $i % 8 ?>" data-search="<?= e(mb_strtolower($r['name'] . ' ' . $r['code'] . ' ' . $r['building_name'])) ?>">
    <div class="card-x hover h-100 d-flex flex-column">
      <a class="cover cover-3d" href="<?= url('admin/room_view.php?id=' . $r['id']) ?>" data-snap='<?= e(json_encode($snapCfg($r), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
        <div class="cover-ph"><span class="snap-spin"></span></div>
        <img class="snap-img" alt="โมเดล 3D <?= e($r['name']) ?>" hidden>
        <div class="cover-top"><span class="glass-pill"><span class="status-dot me-1" style="--c:<?= $s['color'] ?>;width:8px;height:8px"></span><?= e($s['label']) ?> วันนี้</span><span class="glass-pill"><i class="bi bi-badge-3d"></i></span></div>
      </a>
      <div class="card-x-body d-flex flex-column flex-grow-1">
        <div class="d-flex justify-content-between gap-2"><h6 class="mb-1"><?= e($r['name']) ?></h6><span class="code-pill"><?= e($r['code']) ?></span></div>
        <p class="small text-muted mb-2"><i class="bi bi-building me-1"></i><?= e($r['building_name']) ?> · ชั้น <?= (int)$r['floor'] ?></p>
        <div class="meta-row mb-3"><span><i class="bi bi-people"></i><?= (int)$r['capacity'] ?> ที่นั่ง</span><span><i class="bi bi-calendar-event"></i><?= (int)$r['upcoming'] ?> รายการ</span><span><i class="bi bi-images"></i><?= (int)$r['imgs'] ?></span></div>
        <form method="post" class="mb-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <label class="form-label small mb-1">สถานะห้อง (กำหนดโดย Admin)</label>
          <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
            <?php foreach (ROOM_STATUS as $k => $st): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($st['label']) ?></option><?php endforeach; ?>
          </select>
        </form>
        <div class="d-flex gap-2 mt-auto">
          <a class="btn btn-soft btn-sm flex-fill" href="<?= url('admin/room_view.php?id=' . $r['id']) ?>"><i class="bi bi-badge-3d me-1"></i>3D</a>
          <a class="btn-icon sm" title="แก้ไข" href="<?= url('admin/room_form.php?id=' . $r['id']) ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" data-confirm="ลบห้อง <?= e($r['name']) ?> ?" data-ok="ลบห้อง"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn-icon sm text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php
$extraScripts = '
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
<script type="module">
import { renderRoomSnapshot } from "' . asset('js/viewer3d.js') . '";
for (const el of document.querySelectorAll(".cover[data-snap]")) {
  try {
    const url = await renderRoomSnapshot(JSON.parse(el.dataset.snap), { width: 800, height: 500 });
    const img = el.querySelector(".snap-img");
    img.onload = () => { img.hidden = false; el.classList.add("is-ready"); };
    img.src = url;
  } catch (err) { console.error(err); el.querySelector(".cover-ph").innerHTML = `<i class="bi bi-easel2"></i>`; }
}
</script>
<script>document.getElementById("roomSearch").addEventListener("input", e => { const q = e.target.value.trim().toLowerCase(); document.querySelectorAll("[data-search]").forEach(c => c.style.display = !q || c.dataset.search.includes(q) ? "" : "none"); });</script>';
require __DIR__ . '/../includes/footer.php';
