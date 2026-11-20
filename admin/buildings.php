<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('buildings');

if (is_post()) {
    csrf_check();
    if (input('action') === 'delete') {
        $id = (int)input('id');
        $b = db_one('SELECT * FROM buildings WHERE id=?', [$id]);
        if (!$b) flash('error', 'ไม่พบอาคาร');
        elseif (db_val('SELECT COUNT(*) FROM rooms WHERE building_id=?', [$id])) flash('error', 'ไม่สามารถลบอาคารที่ยังมีห้องประชุมอยู่ได้ กรุณาลบหรือย้ายห้องประชุมก่อน');
        else {
            foreach (db_all('SELECT file FROM building_images WHERE building_id=?', [$id]) as $img) delete_upload($img['file']);
            db_exec('DELETE FROM buildings WHERE id=?', [$id]);
            log_action('building', "ลบอาคาร {$b['name']}");
            flash('success', 'ลบอาคารเรียบร้อย');
        }
    }
    redirect('admin/buildings.php');
}

$buildings = db_all('SELECT b.*, (SELECT COUNT(*) FROM rooms r WHERE r.building_id=b.id) rooms,
  (SELECT COUNT(*) FROM building_images i WHERE i.building_id=b.id) imgs,
  (SELECT file FROM building_images i WHERE i.building_id=b.id ORDER BY (face="front") DESC, sort_order, id LIMIT 1) cover
  FROM buildings b ORDER BY b.code, b.name');
$imgsBy = [];
foreach (db_all('SELECT * FROM building_images ORDER BY sort_order, id') as $img) $imgsBy[$img['building_id']][] = $img;
$snapCfg = fn($b) => building_3d_cfg($b, $imgsBy[$b['id']] ?? []);

$pageTitle = 'จัดการอาคาร';
$activeMenu = 'buildings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-buildings"></i>Buildings</span><h2>จัดการอาคาร</h2><p>กำหนดชื่ออาคาร ตำแหน่งที่ตั้ง รูปถ่ายหลายมุม และดูโมเดล 3 มิติที่ระบบสร้างจากรูป</p></div>
  <div class="actions"><a href="<?= url('admin/building_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มอาคาร</a></div>
</div>

<?php if (!$buildings): ?>
  <div class="card-x empty-state reveal"><div class="empty-icon"><i class="bi bi-building-add"></i></div><h5>ยังไม่มีข้อมูลอาคาร</h5><p class="text-muted">เริ่มต้นเพิ่มอาคารแรกของวิทยาลัย</p><a href="<?= url('admin/building_form.php') ?>" class="btn btn-primary">เพิ่มอาคาร</a></div>
<?php endif; ?>

<div class="row g-4">
<?php foreach ($buildings as $i => $b): ?>
  <div class="col-md-6 col-xl-4 reveal" style="--i:<?= $i ?>">
    <div class="card-x hover h-100 d-flex flex-column">
      <a class="cover cover-3d" href="<?= url('admin/building_view.php?id=' . $b['id']) ?>" data-snap='<?= e(json_encode($snapCfg($b), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
        <div class="cover-ph"><span class="snap-spin"></span></div>
        <img class="snap-img" alt="โมเดล 3D <?= e($b['name']) ?>" hidden>
        <div class="cover-top"><span class="glass-pill"><?= e($b['code'] ?: 'อาคาร') ?></span><span class="glass-pill"><i class="bi bi-badge-3d me-1"></i>3D</span></div>
      </a>
      <div class="card-x-body d-flex flex-column flex-grow-1">
        <h5 class="mb-1"><?= e($b['name']) ?></h5>
        <p class="text-muted small mb-3"><i class="bi bi-geo-alt me-1"></i><?= e($b['location'] ?: 'ไม่ระบุตำแหน่ง') ?></p>
        <div class="meta-row mb-3">
          <span><i class="bi bi-layers"></i><?= (int)$b['floors'] ?> ชั้น</span>
          <span><i class="bi bi-door-open"></i><?= (int)$b['rooms'] ?> ห้องประชุม</span>
          <span><i class="bi bi-images"></i><?= (int)$b['imgs'] ?> รูป</span>
          <span><i class="bi bi-arrows-angle-expand"></i><?= (float)$b['width_m'] ?>×<?= (float)$b['depth_m'] ?> ม.</span>
        </div>
        <div class="d-flex gap-2 mt-auto">
          <a class="btn btn-soft flex-fill" href="<?= url('admin/building_view.php?id=' . $b['id']) ?>"><i class="bi bi-badge-3d me-1"></i>ดูโมเดล 3D</a>
          <a class="btn-icon" title="แก้ไข" href="<?= url('admin/building_form.php?id=' . $b['id']) ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" data-confirm="ลบอาคาร <?= e($b['name']) ?> ?" data-confirm-text="รูปภาพของอาคารจะถูกลบทั้งหมด" data-ok="ลบอาคาร">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>">
            <button class="btn-icon text-danger" title="ลบ"><i class="bi bi-trash"></i></button>
          </form>
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
import { renderBuildingSnapshot } from "' . asset('js/viewer3d.js') . '";
for (const el of document.querySelectorAll(".cover[data-snap]")) {
  try {
    const url = await renderBuildingSnapshot(JSON.parse(el.dataset.snap), { width: 800, height: 500 });
    const img = el.querySelector(".snap-img");
    img.onload = () => { img.hidden = false; el.classList.add("is-ready"); };
    img.src = url;
  } catch (err) { console.error(err); el.querySelector(".cover-ph").innerHTML = `<i class="bi bi-building"></i>`; }
}
</script>';
require __DIR__ . '/../includes/footer.php'; ?>
