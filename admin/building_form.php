<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('buildings');

$id = (int)qs('id');
$b = $id ? db_one('SELECT * FROM buildings WHERE id=?', [$id]) : null;
if ($id && !$b) { flash('error', 'ไม่พบอาคาร'); redirect('admin/buildings.php'); }

if (is_post()) {
    csrf_check();
    $d = [
        'name' => mb_substr(input('name'), 0, 150),
        'code' => mb_substr(input('code'), 0, 30),
        'location' => mb_substr(input('location'), 0, 255),
        'latitude' => is_numeric(input('latitude')) ? (float)input('latitude') : null,
        'longitude' => is_numeric(input('longitude')) ? (float)input('longitude') : null,
        'floors' => max(1, min(50, (int)input('floors', 1))),
        'width_m' => max(3, min(500, (float)input('width_m', 30))),
        'depth_m' => max(3, min(500, (float)input('depth_m', 15))),
        'color' => preg_match('/^#[0-9a-f]{6}$/i', input('color')) ? input('color') : '#d9d4c7',
        'style' => json_encode(building_style((array)($_POST['style'] ?? [])), JSON_UNESCAPED_SLASHES),
        'description' => input('description'),
    ];
    if ($d['name'] === '') {
        flash('error', 'กรุณากรอกชื่ออาคาร');
    } else {
        $cols = array_keys($d);
        if ($id) {
            db_exec('UPDATE buildings SET ' . implode(',', array_map(fn($c) => "$c=?", $cols)) . ' WHERE id=?', [...array_values($d), $id]);
        } else {
            db_exec('INSERT INTO buildings (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
            $id = (int)db()->lastInsertId();
        }
        // รูปเดิม: เปลี่ยนด้าน / ลบ
        foreach ((array)($_POST['face'] ?? []) as $imgId => $face) {
            if (isset(BUILDING_FACES[$face])) db_exec('UPDATE building_images SET face=? WHERE id=? AND building_id=?', [$face, (int)$imgId, $id]);
        }
        $deleted = 0;
        foreach ((array)($_POST['delete_img'] ?? []) as $imgId) {
            $img = db_one('SELECT * FROM building_images WHERE id=? AND building_id=?', [(int)$imgId, $id]);
            if ($img) { delete_upload($img['file']); db_exec('DELETE FROM building_images WHERE id=?', [$img['id']]); $deleted++; }
        }
        // รูปใหม่
        [$saved, $errors] = save_uploaded_images($_FILES['images'] ?? null, 'buildings');
        $faces = (array)($_POST['new_face'] ?? []);
        $order = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM building_images WHERE building_id=?', [$id]);
        foreach ($saved as $i => $file) {
            $face = isset($faces[$i], BUILDING_FACES[$faces[$i]]) ? $faces[$i] : 'other';
            db_exec('INSERT INTO building_images (building_id,file,face,sort_order) VALUES (?,?,?,?)', [$id, $file, $face, ++$order]);
        }
        log_action('building', ($b ? 'แก้ไข' : 'เพิ่ม') . "อาคาร {$d['name']}" . ($saved ? ' · อัปโหลดรูป ' . count($saved) . ' รูป' : '') . ($deleted ? " · ลบรูป $deleted รูป" : ''));
        if ($errors) flash('warning', 'บางไฟล์อัปโหลดไม่สำเร็จ: ' . implode(', ', $errors));
        flash('success', 'บันทึกข้อมูลอาคารเรียบร้อย ระบบสร้างโมเดล 3D ใหม่แล้ว');
        redirect(input('then') === 'view' ? 'admin/building_view.php?id=' . $id : 'admin/building_form.php?id=' . $id);
    }
}

$b = $b ?: ['name' => '', 'code' => '', 'location' => '', 'latitude' => '', 'longitude' => '', 'floors' => 3, 'width_m' => 30, 'depth_m' => 15, 'color' => '#d9d4c7', 'style' => null, 'description' => ''];
$style = building_style($b['style']);
$images = $id ? db_all('SELECT * FROM building_images WHERE building_id=? ORDER BY sort_order, id', [$id]) : [];

$pageTitle = $id ? 'แก้ไขอาคาร' : 'เพิ่มอาคาร';
$activeMenu = 'buildings';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><a href="<?= url('admin/buildings.php') ?>" class="eyebrow"><i class="bi bi-arrow-left"></i>กลับรายการอาคาร</a><h2><?= $id ? 'แก้ไขอาคาร · ' . e($b['name']) : 'เพิ่มอาคารใหม่' ?></h2><p>เลือกลักษณะอาคาร ระบบจะปั้นโมเดล 3 มิติให้ทันทีโดยไม่ต้องมีรูป · ถ้ามีรูปถ่ายแต่ละด้าน ระบบจะใช้รูปเป็นผนังด้านนั้นแทน</p></div>
  <?php if ($id): ?><div class="actions"><a href="<?= url('admin/building_view.php?id=' . $id) ?>" class="btn btn-soft"><i class="bi bi-badge-3d me-1"></i>ดูโมเดล 3D</a></div><?php endif; ?>
</div>

<form method="post" enctype="multipart/form-data" class="row g-4">
  <?= csrf_field() ?>
  <div class="col-xl-7">
    <div class="card-x reveal mb-4"><div class="card-x-head"><h5><i class="bi bi-info-circle"></i>ข้อมูลอาคาร</h5></div>
      <div class="card-x-body row g-3">
        <div class="col-md-8"><label class="form-label">ชื่ออาคาร <span class="text-danger">*</span></label><input class="form-control" name="name" value="<?= e($b['name']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">รหัสอาคาร</label><input class="form-control" name="code" value="<?= e($b['code']) ?>" placeholder="เช่น B1"></div>
        <div class="col-12"><label class="form-label">ตำแหน่งที่ตั้ง</label><input class="form-control" name="location" value="<?= e($b['location']) ?>" placeholder="เช่น ด้านหน้าวิทยาลัย ติดถนน..."></div>
        <div class="col-md-3"><label class="form-label">จำนวนชั้น</label><input class="form-control" type="number" min="1" max="50" name="floors" value="<?= (int)$b['floors'] ?>"></div>
        <div class="col-md-3"><label class="form-label">ความกว้าง (ม.)</label><input class="form-control" type="number" step="0.1" min="3" name="width_m" value="<?= (float)$b['width_m'] ?>"></div>
        <div class="col-md-3"><label class="form-label">ความลึก (ม.)</label><input class="form-control" type="number" step="0.1" min="3" name="depth_m" value="<?= (float)$b['depth_m'] ?>"></div>
        <div class="col-md-3"><label class="form-label">สีผนัง (3D)</label><input class="form-control form-control-color w-100" type="color" name="color" value="<?= e($b['color']) ?>"></div>
        <div class="col-12"><label class="form-label">รายละเอียด</label><textarea class="form-control" name="description" rows="2"><?= e($b['description']) ?></textarea></div>
      </div>
    </div>

    <div class="card-x reveal mb-4" id="style"><div class="card-x-head"><h5><i class="bi bi-magic"></i>ลักษณะอาคาร · ปั้นโมเดล 3D</h5><span class="text-muted small">ปรับแล้วโมเดลเปลี่ยนทันที</span></div>
      <div class="card-x-body">
        <div class="v3d mb-3" id="stylePreview" style="height:340px">
          <div class="v3d-loading"><div class="text-center"><div class="v3d-spinner mx-auto mb-3"></div><div class="small text-muted">กำลังปั้นโมเดล...</div></div></div>
          <div class="v3d-hint"><i class="bi bi-mouse me-1"></i>ลากเพื่อหมุน · สกรอลล์เพื่อซูม</div>
        </div>
        <div class="row g-3">
          <?php foreach (BUILDING_STYLE_OPTS as $k => [$label, $icon, $opts]): ?>
            <div class="col-12"><label class="form-label mb-1"><i class="bi <?= $icon ?> me-1 text-muted"></i><?= e($label) ?></label>
              <div class="chip-group">
                <?php foreach ($opts as $v => $t): ?>
                  <input type="radio" class="btn-check" name="style[<?= $k ?>]" id="st_<?= $k ?>_<?= $v ?>" value="<?= e($v) ?>" <?= $style[$k] === $v ? 'checked' : '' ?>>
                  <label class="chip" for="st_<?= $k ?>_<?= $v ?>"><?= e($t) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="col-sm-4"><label class="form-label">สีหลังคา</label><input class="form-control form-control-color w-100" type="color" name="style[roofColor]" value="<?= e($style['roofColor']) ?>"></div>
          <div class="col-sm-4"><label class="form-label">สีเสา / ราวระเบียง</label><input class="form-control form-control-color w-100" type="color" name="style[trim]" value="<?= e($style['trim']) ?>"></div>
          <div class="col-sm-4 d-flex align-items-end"><input type="hidden" name="style[sign]" value="0">
            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="style[sign]" value="1" id="stSign" <?= $style['sign'] ? 'checked' : '' ?>><label class="form-check-label" for="stSign">ป้ายชื่ออาคาร</label></div></div>
        </div>
      </div>
    </div>

    <div class="card-x reveal" style="--i:1"><div class="card-x-head"><h5><i class="bi bi-images"></i>รูปภาพอาคาร (ไม่บังคับ)</h5><span class="text-muted small"><?= count($images) ?> รูป</span></div>
      <div class="card-x-body">
        <label class="dropzone d-block mb-3">
          <input type="file" name="images[]" id="imgInput" accept="image/jpeg,image/png,image/webp" multiple>
          <i class="bi bi-cloud-arrow-up"></i>
          <div class="fw-semibold mt-1">ลากรูปมาวาง หรือคลิกเพื่อเลือกหลายรูป</div>
          <div class="small text-muted">JPG / PNG / WEBP ไม่เกิน <?= MAX_UPLOAD_MB ?>MB ต่อรูป · แนะนำถ่ายตรงทั้ง 4 ด้าน (หน้า ขวา หลัง ซ้าย)</div>
        </label>
        <div class="thumb-grid mb-3" id="imgPreview"></div>
        <?php if ($images): ?>
          <h6 class="small text-muted mb-2">รูปที่มีอยู่ — เลือกด้านของอาคารให้ตรงกับรูป</h6>
          <div class="thumb-grid">
            <?php foreach ($images as $img): ?>
              <div class="thumb">
                <a href="<?= e(upload_url($img['file'])) ?>" target="_blank"><img src="<?= e(upload_url($img['file'])) ?>" alt="" loading="lazy"></a>
                <label class="thumb-del btn btn-sm btn-light py-0 px-2" title="ลบรูป"><input type="checkbox" class="form-check-input me-1" name="delete_img[]" value="<?= $img['id'] ?>" data-del-img>ลบ</label>
                <div class="thumb-body"><select class="form-select form-select-sm" name="face[<?= $img['id'] ?>]"><?php foreach (BUILDING_FACES as $k => $l): ?><option value="<?= $k ?>" <?= $img['face'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card-x reveal mb-4" style="--i:2"><div class="card-x-head"><h5><i class="bi bi-geo-alt"></i>พิกัดบนแผนที่</h5><button type="button" class="btn btn-sm btn-ghost" id="myLoc"><i class="bi bi-crosshair"></i> ตำแหน่งปัจจุบัน</button></div>
      <div class="card-x-body">
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text"><i class="bi bi-google"></i></span>
          <input class="form-control" id="gLink" placeholder="วางลิงก์ Google Maps หรือพิกัด เช่น 18.786, 100.777">
          <button type="button" class="btn btn-ghost" id="gApply">ใช้พิกัดนี้</button>
        </div>
        <div id="map" class="mb-2"></div>
        <iframe id="gPreview" class="gmap mb-3" style="height:200px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Google Maps"></iframe>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">ละติจูด</label><input class="form-control form-control-sm" name="latitude" id="lat" value="<?= e($b['latitude']) ?>"></div>
          <div class="col-6"><label class="form-label">ลองจิจูด</label><input class="form-control form-control-sm" name="longitude" id="lng" value="<?= e($b['longitude']) ?>"></div>
        </div>
        <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
          <div class="form-text m-0">คลิกบนแผนที่ดาวเทียมหรือลากหมุดเพื่อกำหนดตำแหน่ง · ด้านล่างเป็นตัวอย่างจาก Google Maps</div>
          <a class="small" id="gOpen" target="_blank" rel="noopener" href="#">เปิดใน Google Maps <i class="bi bi-box-arrow-up-right"></i></a>
        </div>
      </div>
    </div>
    <div class="card-x reveal" style="--i:3"><div class="card-x-body">
      <div class="d-flex gap-3 align-items-start mb-3"><span class="stat-icon" style="--c:#8b5cf6"><i class="bi bi-magic"></i></span>
        <div class="small"><strong>การประมวลผลภาพเป็น 3D</strong><div class="text-muted">ระบบนำรูปแต่ละด้านไปห่อหุ้มโมเดลอาคารตามขนาดจริง (กว้าง × ลึก × จำนวนชั้น) ด้านที่ไม่มีรูปจะสร้างผนัง-หน้าต่างให้อัตโนมัติ และแสดงตำแหน่งห้องประชุมทุกห้องในอาคาร</div></div></div>
      <div class="d-grid gap-2">
        <button class="btn btn-primary" name="then" value="view"><i class="bi bi-badge-3d me-1"></i>บันทึก และดูโมเดล 3D</button>
        <button class="btn btn-ghost" name="then" value="stay"><i class="bi bi-check2 me-1"></i>บันทึก</button>
      </div>
    </div></div>
  </div>
</form>

<?php
$extraScripts = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="' . asset('js/media.js') . '"></script>
<script>
initImageUploader({ input: "#imgInput", preview: "#imgPreview", field: "new_face", options: ' . json_encode(BUILDING_FACES, JSON_UNESCAPED_UNICODE) . ' });
(function(){
  const lat = document.getElementById("lat"), lng = document.getElementById("lng");
  const start = [parseFloat(lat.value) || 18.7863, parseFloat(lng.value) || 100.7766];
  const map = L.map("map").setView(start, 18);
  const sat = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", { maxZoom: 19, attribution: "Imagery &copy; Esri" });
  const street = L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", { maxZoom: 20, subdomains: "abcd", className: "tile-street", attribution: "&copy; OpenStreetMap &copy; CARTO" });
  sat.addTo(map);
  L.control.layers({ "ดาวเทียม": sat, "แผนที่ถนน": street }, null, { position: "topright" }).addTo(map);
  const mk = L.marker(start, { draggable: true }).addTo(map);
  const gOpen = document.getElementById("gOpen"), gPrev = document.getElementById("gPreview");
  let gT;
  const sync = () => {
    gOpen.href = "https://www.google.com/maps?q=" + lat.value + "," + lng.value;
    clearTimeout(gT); gT = setTimeout(() => { gPrev.src = "https://maps.google.com/maps?q=" + lat.value + "," + lng.value + "&z=17&hl=th&output=embed"; }, 600);
  };
  const set = ll => { mk.setLatLng(ll); lat.value = ll.lat.toFixed(7); lng.value = ll.lng.toFixed(7); sync(); };
  sync();
  /* อ่านพิกัดจากลิงก์ Google Maps: !3d..!4d.. (ตำแหน่งสถานที่), q=lat,lng, @lat,lng หรือ "lat, lng" */
  const parseLL = t => {
    try { t = decodeURIComponent(t || ""); } catch (e) {}
    const m = t.match(/!3d(-?\\d+\\.\\d+)!4d(-?\\d+\\.\\d+)/) || t.match(/[?&](?:q|query|ll|destination)=(-?\\d+\\.\\d+),\\s*(-?\\d+\\.\\d+)/) || t.match(/@(-?\\d+\\.\\d+),(-?\\d+\\.\\d+)/) || t.match(/^\\s*(-?\\d+\\.\\d+)\\s*,\\s*(-?\\d+\\.\\d+)\\s*$/);
    if (!m) return null;
    const a = parseFloat(m[1]), b = parseFloat(m[2]);
    return Math.abs(a) <= 90 && Math.abs(b) <= 180 ? L.latLng(a, b) : null;
  };
  const gLink = document.getElementById("gLink");
  const apply = () => {
    const ll = parseLL(gLink.value);
    if (!ll) { Swal.fire({ icon: "warning", title: "อ่านพิกัดไม่ได้", html: "เปิด Google Maps → คลิกขวาที่ตำแหน่งอาคาร → คลิกตัวเลขพิกัดเพื่อคัดลอก แล้ววางที่นี่<br><small class=\\"text-muted\\">ลิงก์แบบย่อ (maps.app.goo.gl) ให้เปิดในเบราว์เซอร์ก่อน แล้วคัดลอกลิงก์เต็มจากแถบที่อยู่</small>" }); return; }
    set(ll); map.setView(ll, 18); gLink.value = "";
  };
  document.getElementById("gApply").addEventListener("click", apply);
  gLink.addEventListener("keydown", e => { if (e.key === "Enter") { e.preventDefault(); apply(); } });
  gLink.addEventListener("paste", () => setTimeout(() => { if (parseLL(gLink.value)) apply(); }, 0));
  map.on("click", e => set(e.latlng));
  mk.on("dragend", () => set(mk.getLatLng()));
  [lat, lng].forEach(i => i.addEventListener("change", () => { const ll = L.latLng(parseFloat(lat.value), parseFloat(lng.value)); if (!isNaN(ll.lat)) { mk.setLatLng(ll); map.panTo(ll); sync(); } }));
  document.getElementById("myLoc").addEventListener("click", () => navigator.geolocation && navigator.geolocation.getCurrentPosition(p => { const ll = L.latLng(p.coords.latitude, p.coords.longitude); set(ll); map.setView(ll, 18); }));
})();
</script>';
$extraScripts .= '
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
<script type="module">
import { createBuildingViewer } from "' . asset('js/viewer3d.js') . '";
const base = ' . json_encode(building_3d_cfg($b, $images), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
const form = document.querySelector("form[enctype]"), el = document.getElementById("stylePreview");
const read = () => {
  const f = new FormData(form), style = {};
  for (const [k, v] of f) { const m = k.match(/^style\[(\w+)\]$/); if (m) style[m[1]] = v; }
  style.sign = document.getElementById("stSign").checked;
  return { ...base, name: f.get("name") || base.name, floors: f.get("floors"), width: f.get("width_m"), depth: f.get("depth_m"), color: f.get("color"), style, autoRotate: false };
};
let viewer = null, t = null;
const render = () => { viewer?.dispose(); viewer = createBuildingViewer(el, read()); };
form.addEventListener("input", e => { if (e.target.closest("#style") || ["name", "floors", "width_m", "depth_m", "color"].includes(e.target.name)) { clearTimeout(t); t = setTimeout(render, 250); } });
form.addEventListener("change", e => { if (e.target.closest("#style")) { clearTimeout(t); t = setTimeout(render, 50); } });
render();
</script>';
require __DIR__ . '/../includes/footer.php';
