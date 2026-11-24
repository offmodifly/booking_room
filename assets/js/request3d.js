/* หน้าขอใช้ห้อง (request.php): ไอคอนห้องแบบ 3D + popup รายละเอียดห้อง */
import { renderRoomSnapshot, createRoomViewer } from './viewer3d.js';

const dataEl = document.getElementById('roomData');
const ROOMS = dataEl ? JSON.parse(dataEl.textContent) : {};
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const $ = id => document.getElementById(id);

// ---------- popup รายละเอียดห้อง ----------
const modalEl = $('roomModal');
const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
let current = null, viewer = null;

function syncModes() {
  document.querySelectorAll('[data-rm-mode]').forEach(b => b.classList.toggle('active', viewer && b.dataset.rmMode === viewer.mode));
}
function fill(r) {
  $('rmName').textContent = r.name;
  $('rmSub').textContent = [r.code, r.building, 'ชั้น ' + r.floor].filter(Boolean).join(' · ');
  $('rmCap').textContent = r.capacity.toLocaleString('th-TH');
  $('rmArea').textContent = r.area.toLocaleString('th-TH');
  $('rmFloor').textContent = r.floor;
  $('rmBuilding').textContent = r.building;
  $('rmSize').textContent = r.size + ' (กว้าง × ลึก × สูง)';
  $('rmStatus').innerHTML = `<span class="status-dot me-1" style="--c:${esc(r.statusColor)}"></span>${esc(r.closed ? 'ปิดปรับปรุง' : r.status)}`;
  $('rmFeat').innerHTML = r.features.map(([ico, txt]) => `<li><i class="bi ${esc(ico)}"></i>${esc(txt)}</li>`).join('');
  $('rmEquipWrap').hidden = !r.equipment; $('rmEquip').textContent = r.equipment;
  $('rmDescWrap').hidden = !r.description; $('rmDesc').textContent = r.description;
  $('rmPhotosWrap').hidden = !r.photos.length;
  $('rmPhotos').innerHTML = r.photos.map(u => `<a href="${esc(u)}" target="_blank" rel="noopener"><img src="${esc(u)}" alt="" loading="lazy"></a>`).join('');
  const sel = $('rmSelect'), input = document.querySelector(`input[name=room_id][value="${r.id}"]`);
  sel.disabled = r.closed || !input;
  sel.innerHTML = r.closed ? '<i class="bi bi-slash-circle me-1"></i>ห้องนี้ปิดปรับปรุง'
    : input?.checked ? '<i class="bi bi-check2-circle me-1"></i>เลือกห้องนี้แล้ว' : '<i class="bi bi-check2-circle me-1"></i>เลือกห้องนี้';
}
function openRoom(id) {
  const r = ROOMS[id]; if (!r || !modal) return;
  current = r; fill(r); modal.show();
}

if (modalEl) {
  modalEl.addEventListener('shown.bs.modal', () => {
    if (!current) return;
    const box = $('rmViewer');
    box.querySelector('.v3d-loading')?.classList.remove('done');
    viewer = createRoomViewer(box, { ...current.viewer, mode: 'dollhouse', autoRotate: true });
    syncModes();
  });
  modalEl.addEventListener('hidden.bs.modal', () => { viewer?.dispose(); viewer = null; current = null; });
  document.querySelectorAll('[data-rm-mode]').forEach(b => b.addEventListener('click', () => { viewer?.setMode(b.dataset.rmMode); syncModes(); }));
  $('rmSelect').addEventListener('click', () => {
    const input = current && document.querySelector(`input[name=room_id][value="${current.id}"]`);
    if (!input || input.disabled) return;
    input.checked = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    modal.hide();
    input.closest('.room-opt')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
}
// ปุ่ม (i) และภาพ 3D ในการ์ด → เปิดรายละเอียด (ไม่เลือกห้อง)
document.addEventListener('click', e => {
  const t = e.target.closest('[data-room-info]');
  if (!t) return;
  e.preventDefault(); e.stopPropagation();
  openRoom(t.dataset.roomInfo);
});

// ---------- ไอคอน 3D ของแต่ละห้อง (เรนเดอร์ทีละห้องด้วย renderer ร่วม) ----------
for (const el of document.querySelectorAll('.room-opt-img[data-snap-room]')) {
  const r = ROOMS[el.dataset.snapRoom]; if (!r) continue;
  try {
    const url = await renderRoomSnapshot(r.snap, { width: 160, height: 160 });
    const img = el.querySelector('img');
    img.onload = () => { img.hidden = false; el.classList.add('is-ready'); };
    img.src = url;
  } catch (err) { console.error(err); el.innerHTML = '<i class="bi bi-easel2"></i>'; }
}
