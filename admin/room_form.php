<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('rooms');

$buildings = db_all('SELECT * FROM buildings ORDER BY name');
if (!$buildings) { flash('warning', 'กรุณาเพิ่มอาคารก่อนเพิ่มห้องประชุม'); redirect('admin/building_form.php'); }
$id = (int)qs('id');
$r = $id ? db_one('SELECT * FROM rooms WHERE id=?', [$id]) : null;
if ($id && !$r) { flash('error', 'ไม่พบห้องประชุม'); redirect('admin/rooms.php'); }

if (is_post()) {
    csrf_check();
    $bld = db_one('SELECT * FROM buildings WHERE id=?', [(int)input('building_id')]);
    $d = [
        'building_id' => (int)($bld['id'] ?? 0),
        'name' => mb_substr(input('name'), 0, 150),
        'code' => mb_substr(input('code'), 0, 30),
        'floor' => max(1, min((int)($bld['floors'] ?? 1), (int)input('floor', 1))),
        'pos_x' => max(0, min(100, round((float)input('pos_x', 50), 2))),
        'pos_y' => max(0, min(100, round((float)input('pos_y', 50), 2))),
        'width_m' => max(2, min(200, (float)input('width_m', 10))),
        'depth_m' => max(2, min(200, (float)input('depth_m', 8))),
        'height_m' => max(2, min(30, (float)input('height_m', 3))),
        'capacity' => max(1, (int)input('capacity', 20)),
        'equipment' => input('equipment'),
        'description' => input('description'),
        'color' => preg_match('/^#[0-9a-f]{6}$/i', input('color')) ? input('color') : '#e8e3d8',
        'layout' => json_encode(room_layout((array)($_POST['layout'] ?? [])), JSON_UNESCAPED_UNICODE),
        'status' => isset(ROOM_STATUS[input('status')]) ? input('status') : 'available',
    ];
    if (!$bld) flash('error', 'กรุณาเลือกอาคาร');
    elseif ($d['name'] === '') flash('error', 'กรุณากรอกชื่อห้องประชุม');
    else {
        $cols = array_keys($d);
        if ($id) db_exec('UPDATE rooms SET ' . implode(',', array_map(fn($c) => "$c=?", $cols)) . ' WHERE id=?', [...array_values($d), $id]);
        else { db_exec('INSERT INTO rooms (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d)); $id = (int)db()->lastInsertId(); }
        foreach ((array)($_POST['wall'] ?? []) as $imgId => $wall) {
            if (isset(ROOM_WALLS[$wall])) db_exec('UPDATE room_images SET wall=? WHERE id=? AND room_id=?', [$wall, (int)$imgId, $id]);
        }
        foreach ((array)($_POST['delete_img'] ?? []) as $imgId) {
            $img = db_one('SELECT * FROM room_images WHERE id=? AND room_id=?', [(int)$imgId, $id]);
            if ($img) { delete_upload($img['file']); db_exec('DELETE FROM room_images WHERE id=?', [$img['id']]); }
        }
        [$saved, $errors] = save_uploaded_images($_FILES['images'] ?? null, 'rooms');
        $walls = (array)($_POST['new_wall'] ?? []);
        $order = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM room_images WHERE room_id=?', [$id]);
        foreach ($saved as $i => $file) {
            $w = isset($walls[$i], ROOM_WALLS[$walls[$i]]) ? $walls[$i] : 'other';
            db_exec('INSERT INTO room_images (room_id,file,wall,sort_order) VALUES (?,?,?,?)', [$id, $file, $w, ++$order]);
        }
        log_action('room', ($r ? 'แก้ไข' : 'เพิ่ม') . "ห้องประชุม {$d['name']} ({$bld['name']} ชั้น {$d['floor']} พิกัด {$d['pos_x']},{$d['pos_y']})" . ($saved ? ' · อัปโหลดรูป ' . count($saved) . ' รูป' : ''));
        if ($errors) flash('warning', 'บางไฟล์อัปโหลดไม่สำเร็จ: ' . implode(', ', $errors));
        flash('success', 'บันทึกห้องประชุมเรียบร้อย');
        redirect(input('then') === 'view' ? 'admin/room_view.php?id=' . $id : 'admin/room_form.php?id=' . $id);
    }
}

$r = $r ?: ['building_id' => (int)qs('building_id') ?: $buildings[0]['id'], 'name' => '', 'code' => '', 'floor' => 1, 'pos_x' => 50, 'pos_y' => 50, 'width_m' => 10, 'depth_m' => 8, 'height_m' => 3, 'capacity' => 20, 'equipment' => '', 'description' => '', 'color' => '#e8e3d8', 'layout' => null, 'status' => 'available'];
$layout = room_layout($r['layout'], (int)$r['capacity'], (string)$r['equipment']);
$images = $id ? db_all('SELECT * FROM room_images WHERE room_id=? ORDER BY sort_order, id', [$id]) : [];
$others = db_all('SELECT id, building_id, name, floor, pos_x, pos_y FROM rooms WHERE id<>?', [$id]);
$bData = array_map(fn($b) => ['id' => $b['id'], 'floors' => (int)$b['floors'], 'w' => (float)$b['width_m'], 'd' => (float)$b['depth_m']], $buildings);

$pageTitle = $id ? 'แก้ไขห้องประชุม' : 'เพิ่มห้องประชุม';
$activeMenu = 'rooms';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><a href="<?= url('admin/rooms.php') ?>" class="eyebrow"><i class="bi bi-arrow-left"></i>กลับรายการห้อง</a><h2><?= $id ? 'แก้ไข · ' . e($r['name']) : 'เพิ่มห้องประชุมใหม่' ?></h2><p>ระบุอาคาร ชั้น และคลิกบนผังเพื่อกำหนดพิกัดห้องภายในอาคาร</p></div>
  <?php if ($id): ?><div class="actions"><a href="<?= url('admin/room_view.php?id=' . $id) ?>" class="btn btn-soft"><i class="bi bi-badge-3d me-1"></i>ดู 3D</a></div><?php endif; ?>
</div>

<form method="post" enctype="multipart/form-data" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-xl-7">
    <div class="card-x reveal mb-4"><div class="card-x-head"><h5><i class="bi bi-info-circle"></i>ข้อมูลห้องประชุม</h5></div>
      <div class="card-x-body row g-3">
        <div class="col-md-8"><label class="form-label">ชื่อห้องประชุม <span class="text-danger">*</span></label><input class="form-control" name="name" value="<?= e($r['name']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">รหัสห้อง</label><input class="form-control" name="code" value="<?= e($r['code']) ?>" placeholder="เช่น R101"></div>
        <div class="col-md-6"><label class="form-label">อยู่อาคาร <span class="text-danger">*</span></label>
          <select class="form-select" name="building_id" id="bSel"><?php foreach ($buildings as $b): ?><option value="<?= $b['id'] ?>" <?= (int)$r['building_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">ชั้น</label><select class="form-select" name="floor" id="fSel" data-value="<?= (int)$r['floor'] ?>"></select></div>
        <div class="col-md-3"><label class="form-label">ความจุ (ที่นั่ง)</label><input class="form-control" type="number" min="1" name="capacity" value="<?= (int)$r['capacity'] ?>"></div>
        <div class="col-md-3"><label class="form-label">กว้าง (ม.)</label><input class="form-control" type="number" step="0.1" name="width_m" value="<?= (float)$r['width_m'] ?>"></div>
        <div class="col-md-3"><label class="form-label">ลึก (ม.)</label><input class="form-control" type="number" step="0.1" name="depth_m" value="<?= (float)$r['depth_m'] ?>"></div>
        <div class="col-md-3"><label class="form-label">สูง (ม.)</label><input class="form-control" type="number" step="0.1" name="height_m" value="<?= (float)$r['height_m'] ?>"></div>
        <div class="col-md-3"><label class="form-label">สีผนัง (3D)</label><input class="form-control form-control-color w-100" type="color" name="color" value="<?= e($r['color']) ?>"></div>
        <div class="col-12"><label class="form-label">สถานะห้อง</label>
          <div class="chip-tabs"><?php foreach (ROOM_STATUS as $k => $s): ?>
            <label class="chip <?= $r['status'] === $k ? 'active' : '' ?>" style="--c:<?= $s['color'] ?>"><input type="radio" class="d-none" name="status" value="<?= $k ?>" <?= $r['status'] === $k ? 'checked' : '' ?>><i class="bi <?= $s['icon'] ?>"></i><?= e($s['label']) ?></label>
          <?php endforeach; ?></div>
          <div class="form-text">สถานะจะถูกปรับอัตโนมัติตามการจองของวันนั้น (จอง = มีคำขอรออนุมัติ · ไม่ว่าง = อนุมัติแล้ว) ตั้ง "ไม่ว่าง" เพื่อปิดห้องชั่วคราว</div></div>
        <div class="col-12"><label class="form-label">อุปกรณ์ภายในห้อง</label><textarea class="form-control" name="equipment" rows="2" placeholder="เช่น โปรเจกเตอร์, ระบบเสียง, ไมค์ประชุม"><?= e($r['equipment']) ?></textarea></div>
        <div class="col-12"><label class="form-label">รายละเอียด</label><textarea class="form-control" name="description" rows="2"><?= e($r['description']) ?></textarea></div>
      </div>
    </div>
    <div class="card-x reveal mb-4" id="layout"><div class="card-x-head"><h5><i class="bi bi-magic"></i>จัดห้อง · ฉาก 3D</h5><span class="text-muted small">ปรับแล้วห้องเปลี่ยนทันที</span></div>
      <div class="card-x-body">
        <div class="v3d mb-2" id="layoutPreview" style="height:360px">
          <div class="v3d-loading"><div class="text-center"><div class="v3d-spinner mx-auto mb-3"></div><div class="small text-muted">กำลังจัดห้อง...</div></div></div>
          <div class="v3d-toolbar"><button type="button" class="btn btn-sm" data-pmode="inside"><i class="bi bi-eye me-1"></i>มุมมองภายใน</button><button type="button" class="btn btn-sm active" data-pmode="dollhouse"><i class="bi bi-box me-1"></i>มุมมองด้านบน</button></div>
          <div class="v3d-hint"><i class="bi bi-mouse me-1"></i>ลากเพื่อหมุน · สกรอลล์เพื่อซูม</div>
        </div>
        <div class="small mb-3" id="layoutStats">&nbsp;</div>
        <div class="row g-3">
          <div class="col-12"><label class="form-label mb-1"><i class="bi bi-easel2 me-1 text-muted"></i>เวทีหน้าห้อง</label>
            <div class="chip-group">
              <input type="radio" class="btn-check" name="layout[stage]" id="lyStage0" value="0" <?= !$layout['stage'] ? 'checked' : '' ?>><label class="chip" for="lyStage0">ไม่มี</label>
              <input type="radio" class="btn-check" name="layout[stage]" id="lyStage1" value="1" <?= $layout['stage'] ? 'checked' : '' ?>><label class="chip" for="lyStage1"><i class="bi bi-easel2 me-1"></i>มีเวที + โพเดียม</label>
            </div></div>
          <div class="col-12"><label class="form-label mb-1"><i class="bi bi-table me-1 text-muted"></i>ลักษณะการจัดโต๊ะ</label>
            <div class="chip-group">
              <?php foreach (ROOM_TABLE_TYPES as $k => [$t, $ic, $seat]): ?>
                <input type="radio" class="btn-check" name="layout[tableType]" id="lyT_<?= $k ?>" value="<?= $k ?>" data-seats="<?= $seat ?>" <?= $layout['tableType'] === $k ? 'checked' : '' ?>>
                <label class="chip" for="lyT_<?= $k ?>"><i class="bi <?= $ic ?> me-1"></i><?= e($t) ?></label>
              <?php endforeach; ?>
            </div></div>
          <?php foreach (ROOM_LAYOUT_COUNTS as $k => [$t, $ic, $max, $hint]): ?>
            <div class="col-sm-6 col-lg-4"><div class="layout-opt" data-opt="<?= $k ?>">
              <label class="form-label mb-1" for="ly_<?= $k ?>"><i class="bi <?= $ic ?> me-1 text-muted"></i><?= e($t) ?></label>
              <div class="num-step">
                <button type="button" class="btn btn-ghost" data-step="-1" aria-label="ลด"><i class="bi bi-dash"></i></button>
                <input class="form-control form-control-sm" type="number" min="0" max="<?= $max ?>" name="layout[<?= $k ?>]" id="ly_<?= $k ?>" value="<?= (int)$layout[$k] ?>">
                <button type="button" class="btn btn-ghost" data-step="1" aria-label="เพิ่ม"><i class="bi bi-plus"></i></button>
              </div>
              <div class="form-text mt-1"><?= e($hint) ?><?php if ($k === 'chairs'): ?> · <a href="#" id="chairsCap">เท่าความจุห้อง</a><?php elseif ($k === 'tables'): ?> · <a href="#" id="tablesAuto">คำนวณจากเก้าอี้</a><?php endif; ?></div>
            </div></div>
          <?php endforeach; ?>
          <div class="col-12"><label class="form-label mb-1"><i class="bi bi-projector me-1 text-muted"></i>เครื่องฉายภาพ / จอภาพ</label>
            <div class="chip-group">
              <?php foreach (ROOM_DISPLAYS as $k => $t): ?>
                <input type="radio" class="btn-check" name="layout[display]" id="lyD_<?= $k ?>" value="<?= $k ?>" <?= $layout['display'] === $k ? 'checked' : '' ?>><label class="chip" for="lyD_<?= $k ?>"><?= e($t) ?></label>
              <?php endforeach; ?>
            </div></div>
        </div>
      </div>
    </div>

    <div class="card-x reveal" style="--i:1"><div class="card-x-head"><h5><i class="bi bi-images"></i>รูปภาพห้องประชุม (ไม่บังคับ)</h5><span class="text-muted small"><?= count($images) ?> รูป</span></div>
      <div class="card-x-body">
        <label class="dropzone d-block mb-3"><input type="file" name="images[]" id="imgInput" accept="image/jpeg,image/png,image/webp" multiple><i class="bi bi-cloud-arrow-up"></i>
          <div class="fw-semibold mt-1">เลือกรูปห้องหลายรูป</div><div class="small text-muted">ถ่ายผนังแต่ละด้านแบบหันตรง (หน้า/ขวา/หลัง/ซ้าย) + พื้น/เพดาน เพื่อให้ห้อง 3D สมจริงที่สุด</div></label>
        <div class="thumb-grid mb-3" id="imgPreview"></div>
        <?php if ($images): ?><div class="thumb-grid">
          <?php foreach ($images as $img): ?>
            <div class="thumb"><a href="<?= e(upload_url($img['file'])) ?>" target="_blank"><img src="<?= e(upload_url($img['file'])) ?>" alt="" loading="lazy"></a>
              <label class="thumb-del btn btn-sm btn-light py-0 px-2"><input type="checkbox" class="form-check-input me-1" name="delete_img[]" value="<?= $img['id'] ?>" data-del-img>ลบ</label>
              <div class="thumb-body"><select class="form-select form-select-sm" name="wall[<?= $img['id'] ?>]"><?php foreach (ROOM_WALLS as $k => $l): ?><option value="<?= $k ?>" <?= $img['wall'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div></div>
          <?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card-x reveal mb-4" style="--i:2"><div class="card-x-head"><h5><i class="bi bi-pin-map"></i>พิกัดห้องภายในอาคาร</h5><span class="small text-muted" id="posLabel"></span></div>
      <div class="card-x-body">
        <div class="plan" id="plan"><span class="plan-label" style="left:50%;bottom:0;transform:translateX(-50%)">▼ ด้านหน้าอาคาร</span><span class="plan-label" style="left:0;top:0">ด้านหลัง-ซ้าย</span><div class="pin" id="pin"></div></div>
        <input type="hidden" name="pos_x" id="posX" value="<?= (float)$r['pos_x'] ?>"><input type="hidden" name="pos_y" id="posY" value="<?= (float)$r['pos_y'] ?>">
        <div class="form-text mt-2">ผังพื้นชั้นที่เลือก (มุมมองจากด้านบน) · จุดสีเทา = ห้องอื่นในชั้นเดียวกัน · คลิกเพื่อวางตำแหน่ง</div>
      </div>
    </div>
    <div class="card-x reveal" style="--i:3"><div class="card-x-body d-grid gap-2">
      <button class="btn btn-primary" name="then" value="view"><i class="bi bi-badge-3d me-1"></i>บันทึก และดูห้อง 3D</button>
      <button class="btn btn-ghost" name="then" value="stay"><i class="bi bi-check2 me-1"></i>บันทึก</button>
    </div></div>
  </div>
</form>
<?php
$extraScripts = '<script src="' . asset('js/media.js') . '"></script>
<script>
initImageUploader({ input: "#imgInput", preview: "#imgPreview", field: "new_wall", options: ' . json_encode(ROOM_WALLS, JSON_UNESCAPED_UNICODE) . ' });
document.querySelectorAll(".chip-tabs label.chip input[type=radio]").forEach(r => r.addEventListener("change", () => { document.querySelectorAll(".chip-tabs label.chip").forEach(l => l.classList.toggle("active", l.querySelector("input").checked)); }));
(function(){
  const B = ' . json_encode($bData) . ', O = ' . json_encode($others, JSON_UNESCAPED_UNICODE) . ';
  const bSel = document.getElementById("bSel"), fSel = document.getElementById("fSel"), plan = document.getElementById("plan"), pin = document.getElementById("pin");
  const px = document.getElementById("posX"), py = document.getElementById("posY"), lbl = document.getElementById("posLabel");
  function floors() {
    const b = B.find(x => x.id == bSel.value) || B[0], cur = fSel.value || fSel.dataset.value;
    fSel.innerHTML = Array.from({ length: b.floors }, (_, i) => `<option value="${i + 1}">ชั้น ${i + 1}</option>`).join("");
    fSel.value = Math.min(cur, b.floors) || 1;
    plan.style.aspectRatio = b.w + " / " + b.d;
    draw();
  }
  function draw() {
    plan.querySelectorAll(".other").forEach(n => n.remove());
    O.filter(o => o.building_id == bSel.value && o.floor == fSel.value).forEach(o => {
      const d = document.createElement("div"); d.className = "other"; d.style.left = o.pos_x + "%"; d.style.top = o.pos_y + "%";
      d.innerHTML = "<span>" + escapeHtml(o.name) + "</span>"; plan.appendChild(d);
    });
    pin.style.left = px.value + "%"; pin.style.top = py.value + "%";
    lbl.textContent = "X " + (+px.value).toFixed(1) + "% · Y " + (+py.value).toFixed(1) + "%";
  }
  plan.addEventListener("click", e => {
    const r = plan.getBoundingClientRect();
    px.value = Math.max(0, Math.min(100, (e.clientX - r.left) / r.width * 100)).toFixed(2);
    py.value = Math.max(0, Math.min(100, (e.clientY - r.top) / r.height * 100)).toFixed(2);
    draw();
  });
  bSel.addEventListener("change", floors); fSel.addEventListener("change", draw);
  floors();
})();
</script>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
<script type="module">
import { createRoomViewer } from "' . asset('js/viewer3d.js') . '";
const imgs = ' . json_encode(assign_faces($images, 'wall', ['front', 'right', 'back', 'left']), JSON_UNESCAPED_SLASHES) . ';
const form = document.querySelector("form[enctype]"), el = document.getElementById("layoutPreview"), statsEl = document.getElementById("layoutStats");
const $ = n => form.querySelector(`[name="${n}"]`);
let viewer = null, t = null, mode = "dollhouse";
const read = () => {
  const f = new FormData(form), layout = {};
  for (const [k, v] of f) { const m = k.match(/^layout\[(\w+)\]$/); if (m) layout[m[1]] = v; }
  layout.stage = layout.stage === "1";
  return { width: f.get("width_m"), depth: f.get("depth_m"), height: f.get("height_m"), color: f.get("color"), name: f.get("name") || "ห้องประชุม", capacity: f.get("capacity"), images: imgs, layout, furniture: true, mode, autoRotate: false };
};
const render = () => {
  viewer?.dispose(); const o = read(); viewer = createRoomViewer(el, o);
  const s = viewer.stats, L = o.layout, warn = [];
  if (+L.chairs > s.maxChairs) { statsEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>ใส่เก้าอี้ได้สูงสุด ${s.maxChairs.toLocaleString()} ตัว</span>`; return; }
  if (s.chairs < +L.chairs) warn.push(`เก้าอี้วางได้ ${s.chairs.toLocaleString()}/${(+L.chairs).toLocaleString()} ตัว`);
  if (L.tableType !== "none" && s.tables < +L.tables) warn.push(`โต๊ะวางได้ ${s.tables}/${L.tables} ตัว`);
  if (s.computers < +L.computers) warn.push(`คอมพิวเตอร์วางได้ ${s.computers}/${L.computers} ชุด`);
  if (s.mics < +L.mics) warn.push(`ไมค์วางได้ ${s.mics}/${L.mics} ตัว`);
  const cap = +form.querySelector("[name=capacity]").value || 0;
  statsEl.innerHTML = warn.length
    ? `<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>พื้นที่ห้องไม่พอ: ${warn.join(" · ")} — ลองเพิ่มขนาดห้องหรือเปลี่ยนการจัดโต๊ะ</span>`
    : `<span class="text-success"><i class="bi bi-check2-circle me-1"></i>จัดวางครบ: เก้าอี้ ${s.chairs} · โต๊ะ ${s.tables} · ที่นั่งรอบโต๊ะ ${s.seatsAtTables}${s.computers ? " · คอมพิวเตอร์ " + s.computers : ""}${s.speakers ? " · ลำโพง " + s.speakers : ""}${s.mics ? " · ไมค์ " + s.mics : ""}</span>` + (cap && +L.chairs !== cap ? ` <span class="text-muted">(ความจุที่ตั้งไว้ ${cap} ที่นั่ง)</span>` : "");
  document.querySelector("[data-opt=tables]").classList.toggle("is-off", L.tableType === "none");
};
const queue = (ms = 250) => { clearTimeout(t); t = setTimeout(render, ms); };
form.addEventListener("input", e => { if (e.target.closest("#layout") || ["width_m", "depth_m", "height_m", "color", "capacity"].includes(e.target.name)) queue(); });
form.addEventListener("change", e => { if (e.target.closest("#layout")) queue(50); });
form.querySelectorAll("[data-step]").forEach(b => b.addEventListener("click", () => {
  const i = b.parentElement.querySelector("input"); i.value = Math.max(0, Math.min(+i.max, (+i.value || 0) + +b.dataset.step)); queue(150);
}));
const autoTables = () => { const tt = form.querySelector("[name=\"layout[tableType]\"]:checked"), per = +tt.dataset.seats; $("layout[tables]").value = per ? Math.ceil((+$("layout[chairs]").value || 0) / per) : 0; };
document.getElementById("chairsCap").addEventListener("click", e => { e.preventDefault(); $("layout[chairs]").value = form.querySelector("[name=capacity]").value; queue(50); });
document.getElementById("tablesAuto").addEventListener("click", e => { e.preventDefault(); autoTables(); queue(50); });
form.querySelectorAll("[name=\"layout[tableType]\"]").forEach(r => r.addEventListener("change", () => { autoTables(); }));
document.querySelectorAll("[data-pmode]").forEach(b => b.addEventListener("click", () => {
  mode = b.dataset.pmode; document.querySelectorAll("[data-pmode]").forEach(x => x.classList.toggle("active", x === b)); viewer?.setMode(mode);
}));
render();
</script>';
require __DIR__ . '/../includes/footer.php';
