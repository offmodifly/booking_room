/**
 * NTC 3D Viewer — สร้างโมเดล 3 มิติของอาคาร/ห้องประชุมจากรูปถ่ายที่อัปโหลด
 * (texture-mapped massing model: นำรูปแต่ละด้านไปห่อหุ้มผนังของโมเดลตามขนาดจริง)
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { CSS2DRenderer, CSS2DObject } from 'three/addons/renderers/CSS2DRenderer.js';

const STATUS_COLORS = { available: '#10b981', booked: '#f59e0b', unavailable: '#ef4444' };
const loader = new THREE.TextureLoader();
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const dark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
const themeColors = () => dark()
  ? { bg: 0x0b1124, ground: 0x111a33, g1: 0x2b3762, g2: 0x18213f }
  : { bg: 0xe9eef8, ground: 0xdfe5f1, g1: 0xb9c3dc, g2: 0xd3d9e8 };

function shade(hex, amt) {
  const c = new THREE.Color(hex), hsl = {};
  c.getHSL(hsl);
  c.setHSL(hsl.h, hsl.s, Math.min(1, Math.max(0, hsl.l + amt)));
  return '#' + c.getHexString();
}
function tex(url, { mirror = false, onLoad } = {}) {
  const t = loader.load(url, onLoad, undefined, onLoad);
  t.colorSpace = THREE.SRGBColorSpace;
  t.anisotropy = 8;
  if (mirror) { t.wrapS = THREE.RepeatWrapping; t.repeat.x = -1; }
  return t;
}
function canvasTex(c, repeat) {
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8;
  if (repeat) { t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(repeat[0], repeat[1]); }
  return t;
}

/** ผนังอาคารแบบ procedural (กรณียังไม่มีรูปด้านนั้น) — windows: punched | band | glass */
function facadeTexture({ floors, wallW, color, door, windows = 'punched' }) {
  floors = Math.max(1, floors);
  const cols = Math.max(2, Math.round(wallW / 3.4));
  const W = Math.min(2048, Math.max(512, cols * 96)), H = Math.min(2048, Math.max(170, floors * 170));
  const c = document.createElement('canvas'); c.width = W; c.height = H;
  const g = c.getContext('2d');
  const grad = g.createLinearGradient(0, 0, 0, H);
  grad.addColorStop(0, shade(color, 0.05)); grad.addColorStop(1, shade(color, -0.07));
  g.fillStyle = grad; g.fillRect(0, 0, W, H);
  const rowH = H / floors, colW = W / cols;
  const glass = (x, y, w, h) => {
    const gg = g.createLinearGradient(x, y, x + w * 0.6, y + h);
    gg.addColorStop(0, '#a9d0f2'); gg.addColorStop(0.45, '#5a8dc0'); gg.addColorStop(1, '#22476e');
    g.fillStyle = gg; g.fillRect(x, y, w, h);
  };
  const drawDoor = y0 => {
    const i = Math.floor(cols / 2), wx = i * colW + colW * 0.12, ww = colW * 0.76;
    const dg = g.createLinearGradient(0, y0 + rowH * 0.15, 0, y0 + rowH * 0.9);
    dg.addColorStop(0, '#2b4a6b'); dg.addColorStop(1, '#0f1f33');
    g.fillStyle = shade(color, -0.3); g.fillRect(wx - 8, y0 + rowH * 0.1, ww + 16, rowH * 0.8);
    g.fillStyle = dg; g.fillRect(wx, y0 + rowH * 0.16, ww, rowH * 0.74);
    g.fillStyle = 'rgba(255,255,255,.35)'; g.fillRect(wx + ww / 2 - 2, y0 + rowH * 0.16, 4, rowH * 0.74);
  };
  if (windows === 'glass') {
    glass(0, 0, W, H);
    g.fillStyle = 'rgba(255,255,255,.12)';
    for (let i = 0; i < 6; i++) { g.beginPath(); g.moveTo(W * (i / 6), 0); g.lineTo(W * (i / 6) + W * 0.08, 0); g.lineTo(W * (i / 6) - W * 0.1, H); g.lineTo(W * (i / 6) - W * 0.18, H); g.fill(); }
    g.fillStyle = 'rgba(30,41,59,.55)';
    for (let x = 0; x <= W; x += colW / 2) g.fillRect(x - 1.5, 0, 3, H);
    for (let f = 0; f < floors; f++) { g.fillStyle = shade(color, -0.1); g.fillRect(0, f * rowH + rowH * 0.9, W, rowH * 0.1); }
    if (door) drawDoor((floors - 1) * rowH);
    return canvasTex(c);
  }
  for (let f = 0; f < floors; f++) {
    const y0 = f * rowH, ground = f === floors - 1;
    g.fillStyle = shade(color, -0.16); g.fillRect(0, y0 + rowH * 0.9, W, rowH * 0.1);
    if (windows === 'band') {
      const wy = y0 + rowH * 0.24, wh = rowH * 0.46, x0 = colW * 0.12, x1 = W - colW * 0.12;
      g.fillStyle = 'rgba(0,0,0,.2)'; g.fillRect(x0 - 4, wy - 4, x1 - x0 + 8, wh + 10);
      glass(x0, wy, x1 - x0, wh);
      g.fillStyle = 'rgba(255,255,255,.55)';
      for (let x = x0; x <= x1; x += colW / 2) g.fillRect(x - 2, wy, 4, wh);
      g.fillRect(x0, wy + wh * 0.3, x1 - x0, 2);
      g.fillStyle = shade(color, 0.12); g.fillRect(x0 - 6, wy + wh + 4, x1 - x0 + 12, 7);
      if (door && ground) drawDoor(y0);
      continue;
    }
    for (let i = 0; i < cols; i++) {
      if (door && ground && i === Math.floor(cols / 2)) { drawDoor(y0); continue; }
      const wx = i * colW + colW * 0.17, ww = colW * 0.66;
      const wy = y0 + rowH * 0.2, wh = rowH * 0.52;
      g.fillStyle = 'rgba(0,0,0,.2)'; g.fillRect(wx - 4, wy - 4, ww + 8, wh + 10);
      glass(wx, wy, ww, wh);
      if (Math.random() < 0.2) { g.fillStyle = 'rgba(255,214,140,.38)'; g.fillRect(wx, wy, ww, wh); }
      g.fillStyle = 'rgba(255,255,255,.45)'; g.fillRect(wx + ww / 2 - 1.5, wy, 3, wh); g.fillRect(wx, wy + wh * 0.35, ww, 2);
      g.fillStyle = shade(color, 0.12); g.fillRect(wx - 6, wy + wh + 4, ww + 12, 6);
    }
  }
  return canvasTex(c);
}
/** ผนังโถงบันได: ช่องแสงแนวตั้งเยื้องครึ่งชั้น */
function stairTexture(floors, color) {
  const c = document.createElement('canvas'); c.width = 256; c.height = Math.min(2048, floors * 170 + 60);
  const g = c.getContext('2d');
  g.fillStyle = shade(color, -0.05); g.fillRect(0, 0, c.width, c.height);
  const rowH = (c.height - 60) / floors;
  for (let f = 0; f < floors; f++) {
    const y = 60 + f * rowH + rowH * 0.45;
    g.fillStyle = 'rgba(0,0,0,.2)'; g.fillRect(84, y - 4, 88, rowH * 0.5 + 8);
    const gg = g.createLinearGradient(0, y, 0, y + rowH * 0.5); gg.addColorStop(0, '#a9d0f2'); gg.addColorStop(1, '#2f5d8c');
    g.fillStyle = gg; g.fillRect(88, y, 80, rowH * 0.5);
  }
  return canvasTex(c);
}
/** ป้ายชื่ออาคาร */
function signTexture(text) {
  const c = document.createElement('canvas'), g = c.getContext('2d');
  const font = 'bold 110px "IBM Plex Sans Thai", "Noto Sans Thai", Tahoma, sans-serif';
  g.font = font;
  c.width = Math.min(4096, Math.ceil(g.measureText(text).width) + 180); c.height = 220;
  g.fillStyle = '#1e293b'; g.fillRect(0, 0, c.width, c.height);
  g.fillStyle = '#d4a017'; g.fillRect(0, 0, c.width, 12); g.fillRect(0, c.height - 12, c.width, 12);
  g.font = font; g.fillStyle = '#f8fafc'; g.textAlign = 'center'; g.textBaseline = 'middle';
  g.fillText(text, c.width / 2, c.height / 2 + 6);
  return { map: canvasTex(c), aspect: c.width / c.height };
}

/* =====================================================================
 *  ปั้นโมเดลอาคารจาก "ลักษณะอาคาร" (ไม่ต้องมีรูปถ่าย)
 * ===================================================================== */
export const STYLE_DEFAULTS = { shape: 'box', roof: 'flat', corridor: 'none', ground: 'closed', windows: 'punched', stair: 'none', roofColor: '#9c4a3a', trim: '#f8fafc', sign: true };
const FH = 3.6, BASE = 0.45, CW = 2.4;

function buildStructure(o, photos = {}) {
  const st = { ...STYLE_DEFAULTS, ...(o.style || {}) };
  const floors = Math.max(1, parseInt(o.floors, 10) || 1);
  const W = parseFloat(o.width) || 30, D = parseFloat(o.depth) || 15, H = floors * FH, top = BASE + H;
  const color = o.color || '#d9d4c7';
  const open = st.ground === 'open' && floors > 1;
  const group = new THREE.Group(), shell = [], bodies = [];
  const M = (p, fade = true) => { const m = new THREE.MeshStandardMaterial({ roughness: .85, metalness: .03, ...p }); if (fade) shell.push(m); return m; };
  const add = (geo, mat, x, y, z, parent = group) => { const m = new THREE.Mesh(geo, mat); m.position.set(x, y, z); m.castShadow = m.receiveShadow = true; parent.add(m); return m; };
  const trimMat = M({ color: st.trim, roughness: .7 });
  const roofMat = M({ color: st.roofColor, roughness: .75, side: THREE.DoubleSide });
  const gableMat = M({ color: shade(color, -0.08) });
  const slabMat = M({ color: shade(color, -0.12) });
  const topMat = M({ color: shade(color, -0.28), roughness: .95 });
  const parapetMat = M({ color: shade(color, -0.2), roughness: .9 });
  const goldMat = M({ color: '#d4a017', roughness: .35, metalness: .6 });
  const plinthMat = M({ color: 0x9aa3b5, roughness: .9 }, false);

  // ปีกอาคาร: ปีกหลัก (W×D) อยู่กึ่งกลาง ปีกเสริมยื่นไปด้านหลัง
  const ext = Math.max(D * 1.2, W * 0.55);
  const wings = [{ cx: 0, cz: 0, w: W, d: D, main: true }];
  if (st.shape === 'L' || st.shape === 'U') wings.push({ cx: -W / 2 + D / 2, cz: -D / 2 - ext / 2, w: D, d: ext });
  if (st.shape === 'U') wings.push({ cx: W / 2 - D / 2, cz: -D / 2 - ext / 2, w: D, d: ext });
  if (st.shape === 'T') wings.push({ cx: 0, cz: -D / 2 - ext / 2, w: Math.min(D, W * 0.5), d: ext });

  const sidesFor = wg => st.corridor === 'around' ? (wg.main ? ['front', 'back', 'left', 'right'] : ['back', 'left', 'right'])
    : !wg.main ? [] : st.corridor === 'front' ? ['front'] : st.corridor === 'both' ? ['front', 'back'] : [];
  const y0 = open ? BASE + FH : BASE, bh = top - y0, fl = open ? floors - 1 : floors;

  const corridor = (wg, side) => {
    const alongX = side === 'front' || side === 'back', sg = side === 'front' || side === 'right' ? 1 : -1;
    const len = alongX ? wg.w + wg.ex.left + wg.ex.right : wg.d;
    const mid = alongX ? wg.cx + (wg.ex.right - wg.ex.left) / 2 : wg.cz;
    const at = (u, off) => alongX ? [u, wg.cz + sg * (wg.d / 2 + off)] : [wg.cx + sg * (wg.w / 2 + off), u];
    const box = (l, h, t) => alongX ? new THREE.BoxGeometry(l, h, t) : new THREE.BoxGeometry(t, h, l);
    for (let f = open ? 1 : 1; f <= floors; f++) {
      const y = BASE + f * FH;
      const [sx, sz] = at(mid, CW / 2);
      add(box(len, 0.22, CW), slabMat, sx, y - 0.11, sz);
      if (f < floors) { const [px, pz] = at(mid, CW - 0.06); add(box(len, 0.95, 0.12), trimMat, px, y + 0.475, pz); }
    }
    const n = Math.max(2, Math.round(len / 4) + 1), cg = new THREE.BoxGeometry(0.34, H, 0.34);
    for (let i = 0; i < n; i++) { const [cx, cz] = at(mid - len / 2 + 0.2 + i * (len - 0.4) / (n - 1), CW - 0.2); add(cg, trimMat, cx, BASE + H / 2, cz); }
  };

  const prism = (shape, depth) => new THREE.Mesh(new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: false, curveSegments: 24 }), [gableMat, roofMat]);
  /** หลังคาในพิกัดท้องถิ่น: แนวขวาง x ∈ [-a/2, a/2], แนวยาว z ∈ [0, L], ฐานที่ y = 0 */
  const roofLocal = (type, a, L) => {
    const rg = new THREE.Group();
    const put = m => { m.castShadow = m.receiveShadow = true; rg.add(m); return m; };
    if (type === 'gable' || type === 'thai') {
      // ความสูงหลังคาสัมพันธ์กับความกว้างและความสูงอาคาร (ไม่ให้หลังคาสูงเกินตัวอาคาร)
      const rise = type === 'thai' ? Math.min(a * 0.5, Math.max(4.5, H * 0.75)) : Math.min(a * 0.28, Math.max(3, H * 0.45));
      const s = new THREE.Shape(); s.moveTo(-a / 2, 0); s.lineTo(a / 2, 0); s.lineTo(0, rise); s.closePath();
      put(prism(s, L));
      if (type === 'thai') {
        const ang = Math.atan2(rise, a / 2), sl = Math.hypot(a / 2, rise);
        [0.05, L - 0.05].forEach((z, k) => {
          [-1, 1].forEach(sd => { const b = put(new THREE.Mesh(new THREE.BoxGeometry(sl, 0.28, 0.14), goldMat)); b.position.set(sd * a / 4, rise / 2 + 0.1, z); b.rotation.z = sd * -ang; });
          const horn = put(new THREE.Mesh(new THREE.ConeGeometry(0.2, 1.6, 10), goldMat)); horn.position.set(0, rise + 0.6, z + (k ? 0.35 : -0.35)); horn.rotation.x = k ? 0.55 : -0.55;
        });
        const ridge = put(new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.22, L), goldMat)); ridge.position.set(0, rise + 0.05, L / 2);
      }
    } else if (type === 'curved') {
      const rise = a * 0.17, r = (a * a / 4 + rise * rise) / (2 * rise);
      const s = new THREE.Shape(); s.moveTo(-a / 2, 0);
      for (let i = 0; i <= 32; i++) { const x = -a / 2 + a * i / 32; s.lineTo(x, Math.sqrt(Math.max(0, r * r - x * x)) - (r - rise)); }
      s.closePath(); put(prism(s, L));
    } else if (type === 'hip') {
      const h = Math.min(a * 0.3, Math.max(3, H * 0.45)), r1 = Math.min(a / 2, L / 2), r2 = L - r1;
      const A = [-a / 2, 0, 0], B = [a / 2, 0, 0], C = [a / 2, 0, L], Dd = [-a / 2, 0, L], R1 = [0, h, r1], R2 = [0, h, r2];
      const tri = [A, R1, B, B, R1, R2, B, R2, C, C, R2, Dd, Dd, R2, R1, Dd, R1, A].flat();
      const geo = new THREE.BufferGeometry(); geo.setAttribute('position', new THREE.Float32BufferAttribute(tri, 3)); geo.computeVertexNormals();
      put(new THREE.Mesh(geo, roofMat));
    } else if (type === 'sawtooth') {
      const n = Math.max(2, Math.round(L / 6)), tw = L / n, hT = Math.min(3.2, 1.2 + a * 0.08);
      const s = new THREE.Shape(); s.moveTo(0, 0);
      for (let i = 0; i < n; i++) { s.lineTo(i * tw, hT); s.lineTo((i + 1) * tw, 0); }
      s.closePath();
      const m = put(prism(s, a)); m.rotation.y = -Math.PI / 2; m.position.x = a / 2;
      const glassMat = M({ color: '#7fb0dc', roughness: .2, metalness: .3, side: THREE.DoubleSide });
      for (let i = 0; i < n; i++) { const p = put(new THREE.Mesh(new THREE.PlaneGeometry(a - 0.3, hT * 0.7), glassMat)); p.position.set(0, hT * 0.45, i * tw - 0.03); }
    }
    return rg;
  };

  wings.forEach(wg => {
    const sides = sidesFor(wg);
    wg.ex = Object.fromEntries(['front', 'back', 'left', 'right'].map(k => [k, sides.includes(k) ? CW : 0]));
    const ex = wg.ex, dy = wg.main ? 0 : -0.012; // ปีกเสริมต่ำกว่าเล็กน้อย กันผิวซ้อนกระพริบ
    const fx0 = wg.cx - wg.w / 2 - ex.left, fx1 = wg.cx + wg.w / 2 + ex.right, fz0 = wg.cz - wg.d / 2 - ex.back, fz1 = wg.cz + wg.d / 2 + ex.front;
    add(new THREE.BoxGeometry(fx1 - fx0 + 2.4, BASE, fz1 - fz0 + 2.4), plinthMat, (fx0 + fx1) / 2, BASE / 2 + dy, (fz0 + fz1) / 2);

    const face = (key, len, door) => M({ map: (wg.main && photos[key]) || facadeTexture({ floors: fl, wallW: len, color, door: door && !open, windows: st.windows }), roughness: .82, metalness: .04 });
    const mats = [face('right', wg.d), face('left', wg.d), topMat, slabMat, face('front', wg.w, wg.main), face('back', wg.w)];
    bodies.push(add(new THREE.BoxGeometry(wg.w, bh, wg.d), mats, wg.cx, y0 + bh / 2, wg.cz));

    if (open) { // ใต้ถุนโล่ง: เสารับอาคาร + โถงบันไดเล็ก
      const nx = Math.max(2, Math.round(wg.w / 5) + 1), nz = Math.max(2, Math.round(wg.d / 5) + 1), cg = new THREE.BoxGeometry(0.5, FH, 0.5);
      for (let i = 0; i < nx; i++) for (let j = 0; j < nz; j++) {
        if (!(i === 0 || j === 0 || i === nx - 1 || j === nz - 1)) continue;
        add(cg, trimMat, wg.cx - wg.w / 2 + 0.4 + i * (wg.w - 0.8) / (nx - 1), BASE + FH / 2, wg.cz - wg.d / 2 + 0.4 + j * (wg.d - 0.8) / (nz - 1));
      }
      if (wg.main) add(new THREE.BoxGeometry(Math.min(6, wg.w * 0.2), FH, wg.d * 0.5), gableMat, wg.cx, BASE + FH / 2, wg.cz - wg.d * 0.2);
    }
    sides.forEach(s => corridor(wg, s));

    if (st.roof === 'flat') {
      add(new THREE.BoxGeometry(fx1 - fx0 + 0.6, 0.7, fz1 - fz0 + 0.6), parapetMat, (fx0 + fx1) / 2, top + 0.35 + dy, (fz0 + fz1) / 2);
    } else {
      const ov = 0.9, x0 = fx0 - ov, x1 = fx1 + ov, z0 = fz0 - ov, z1 = fz1 + ov;
      const alongX = x1 - x0 >= z1 - z0, a = alongX ? z1 - z0 : x1 - x0, L = alongX ? x1 - x0 : z1 - z0;
      const rg = roofLocal(st.roof, a, L);
      if (alongX) { rg.rotation.y = Math.PI / 2; rg.position.set(x0, top, (z0 + z1) / 2); } else rg.position.set((x0 + x1) / 2, top, z0);
      add(new THREE.BoxGeometry(x1 - x0, 0.18, z1 - z0), roofMat, (x0 + x1) / 2, top + 0.09 + dy, (z0 + z1) / 2);
      group.add(rg);
    }
  });

  // โถงบันได
  const m0 = wings[0].ex;
  let frontZ = D / 2 + m0.front;
  if (st.stair !== 'none') {
    const sw = 4.4, sd = 5, sh = H + 1.6;
    const [sx, sz] = st.stair === 'left' ? [-W / 2 - m0.left - sw / 2, 0] : st.stair === 'right' ? [W / 2 + m0.right + sw / 2, 0] : [0, frontZ + sd / 2];
    if (st.stair === 'center') frontZ += sd;
    add(new THREE.BoxGeometry(sw + 2, BASE, sd + 2), plinthMat, sx, BASE / 2, sz);
    const sm = M({ map: stairTexture(floors, color), roughness: .85 });
    bodies.push(add(new THREE.BoxGeometry(sw, sh, sd), [sm, sm, topMat, slabMat, sm, sm], sx, BASE + sh / 2, sz));
    add(new THREE.BoxGeometry(sw + 0.5, 0.4, sd + 0.5), st.roof === 'flat' ? parapetMat : roofMat, sx, BASE + sh + 0.2, sz);
  }

  // ป้ายชื่ออาคารหน้าอาคาร
  if (st.sign && o.name) {
    const { map, aspect } = signTexture(String(o.name));
    const ph = 1.1, pw = Math.min(Math.max(W * 0.7, 6), ph * aspect);
    const sg = new THREE.Group(); sg.position.set(0, 0, frontZ + 4.5); group.add(sg);
    add(new THREE.BoxGeometry(pw + 0.8, 0.5, 0.9), plinthMat, 0, 0.25, 0, sg);
    add(new THREE.BoxGeometry(pw + 0.3, ph + 0.3, 0.4), trimMat, 0, 0.5 + (ph + 0.3) / 2, 0, sg);
    const plate = new THREE.Mesh(new THREE.PlaneGeometry(pw, pw / aspect), M({ map, roughness: .5 }));
    plate.position.set(0, 0.5 + (ph + 0.3) / 2, 0.21); sg.add(plate);
  }

  const box = new THREE.Box3().setFromObject(group);
  return { group, shell, bodies, box, floors, W, D, H, top };
}
function wallTexture(color, kind = 'wall') {
  const c = document.createElement('canvas'); c.width = 1024; c.height = 512;
  const g = c.getContext('2d');
  if (kind === 'floor') {
    g.fillStyle = '#5b6474'; g.fillRect(0, 0, 1024, 512);
    for (let i = 0; i < 4000; i++) { g.fillStyle = `rgba(${Math.random() < .5 ? '255,255,255' : '0,0,0'},.05)`; g.fillRect(Math.random() * 1024, Math.random() * 512, 2, 2); }
    g.strokeStyle = 'rgba(0,0,0,.18)'; g.lineWidth = 2;
    for (let x = 0; x <= 1024; x += 128) { g.beginPath(); g.moveTo(x, 0); g.lineTo(x, 512); g.stroke(); }
    for (let y = 0; y <= 512; y += 128) { g.beginPath(); g.moveTo(0, y); g.lineTo(1024, y); g.stroke(); }
    return canvasTex(c, [3, 3]);
  }
  if (kind === 'ceiling') {
    g.fillStyle = '#f1f2f4'; g.fillRect(0, 0, 1024, 512);
    g.strokeStyle = '#d6d9de'; g.lineWidth = 3;
    for (let x = 0; x <= 1024; x += 102.4) { g.beginPath(); g.moveTo(x, 0); g.lineTo(x, 512); g.stroke(); }
    for (let y = 0; y <= 512; y += 102.4) { g.beginPath(); g.moveTo(0, y); g.lineTo(1024, y); g.stroke(); }
    g.fillStyle = '#fffbe8';
    [[205, 154], [614, 154], [205, 358], [614, 358]].forEach(([x, y]) => { g.shadowColor = '#fff6c8'; g.shadowBlur = 30; g.fillRect(x, y - 40, 204, 80); });
    return canvasTex(c);
  }
  const grad = g.createLinearGradient(0, 0, 0, 512);
  grad.addColorStop(0, shade(color, 0.04)); grad.addColorStop(1, shade(color, -0.05));
  g.fillStyle = grad; g.fillRect(0, 0, 1024, 512);
  g.fillStyle = shade(color, -0.22); g.fillRect(0, 486, 1024, 26);
  if (kind === 'window') {
    for (let i = 0; i < 3; i++) {
      const x = 110 + i * 300, gg = g.createLinearGradient(x, 120, x + 200, 380);
      gg.addColorStop(0, '#cfe6fb'); gg.addColorStop(1, '#7fa9d1');
      g.fillStyle = '#ffffff'; g.fillRect(x - 8, 112, 216, 266);
      g.fillStyle = gg; g.fillRect(x, 120, 200, 250);
      g.fillStyle = 'rgba(255,255,255,.7)'; g.fillRect(x + 98, 120, 4, 250);
    }
  }
  if (kind === 'screen') {
    g.fillStyle = '#1f2937'; g.fillRect(262, 70, 500, 300);
    const sg = g.createLinearGradient(270, 78, 754, 362);
    sg.addColorStop(0, '#6366f1'); sg.addColorStop(1, '#c026d3');
    g.fillStyle = sg; g.fillRect(272, 80, 480, 280);
    g.fillStyle = '#fff'; g.font = 'bold 34px "IBM Plex Sans Thai", sans-serif'; g.textAlign = 'center';
    g.fillText('NTC Meeting Room', 512, 225);
  }
  return canvasTex(c);
}

function baseScene(container, { preserve = true } = {}) {
  container.querySelectorAll('canvas,.v3d-labels').forEach(n => n.remove());
  const tc = themeColors();
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(tc.bg);
  const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: preserve });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  container.appendChild(renderer.domElement);
  const labels = new CSS2DRenderer();
  labels.domElement.className = 'v3d-labels';
  container.appendChild(labels.domElement);
  const camera = new THREE.PerspectiveCamera(45, 1, 0.05, 3000);
  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true; controls.dampingFactor = 0.08;
  const resize = () => {
    const w = container.clientWidth, h = container.clientHeight || 400;
    renderer.setSize(w, h); labels.setSize(w, h);
    camera.aspect = w / h; camera.updateProjectionMatrix();
  };
  resize();
  const ro = new ResizeObserver(resize); ro.observe(container);
  return { scene, renderer, labels, camera, controls, ro };
}
function flyTo(camera, controls, pos, target, ms = 1400) {
  const p0 = camera.position.clone(), t0 = controls.target.clone(), start = performance.now();
  const ease = x => 1 - Math.pow(1 - x, 3);
  return new Promise(res => {
    const step = now => {
      const k = ease(Math.min(1, (now - start) / ms));
      camera.position.lerpVectors(p0, pos, k);
      controls.target.lerpVectors(t0, target, k);
      if (k < 1) requestAnimationFrame(step); else res();
    };
    requestAnimationFrame(step);
  });
}
function hideLoading(container) { const l = container.querySelector('.v3d-loading'); if (l) l.classList.add('done'); }

/* =====================================================================
 *  อาคาร
 * ===================================================================== */
export function createBuildingViewer(container, opts = {}) {
  const imgs = opts.images || {};
  const { scene, renderer, labels, camera, controls, ro } = baseScene(container);
  let pending = 0;
  const done = () => { if (--pending <= 0) hideLoading(container); };
  const photos = {};
  ['front', 'back', 'left', 'right'].forEach(k => { if (imgs[k]) { pending++; photos[k] = tex(imgs[k], { onLoad: done }); } });
  const S = buildStructure(opts, photos);
  const { floors, W, D, H } = S;
  const ctr = S.box.getCenter(new THREE.Vector3());
  const R = S.box.getSize(new THREE.Vector3()).length() / 2;
  let tc = themeColors();
  scene.fog = new THREE.Fog(tc.bg, R * 5, R * 14);

  // lights
  scene.add(new THREE.HemisphereLight(0xffffff, 0x8796b0, 1.15));
  const sun = new THREE.DirectionalLight(0xffffff, 1.8);
  sun.position.set(ctr.x + R * 1.3, R * 2.2, ctr.z + R * 1.6);
  sun.target.position.set(ctr.x, 0, ctr.z); scene.add(sun.target);
  sun.castShadow = true;
  sun.shadow.mapSize.set(2048, 2048); sun.shadow.bias = -0.0004; sun.shadow.normalBias = 0.04;
  Object.assign(sun.shadow.camera, { left: -R * 2, right: R * 2, top: R * 2, bottom: -R * 2, near: 1, far: R * 8 });
  scene.add(sun);

  // ground
  const groundMat = new THREE.MeshStandardMaterial({ color: tc.ground, roughness: 1 });
  const ground = new THREE.Mesh(new THREE.CircleGeometry(R * 16, 64), groundMat);
  ground.rotation.x = -Math.PI / 2; ground.position.set(ctr.x, 0, ctr.z); ground.receiveShadow = true; scene.add(ground);
  let grid = new THREE.GridHelper(R * 8, 48, tc.g1, tc.g2);
  grid.position.set(ctr.x, 0.02, ctr.z); grid.material.transparent = true; grid.material.opacity = 0.55; scene.add(grid);

  scene.add(S.group);
  if (!pending) setTimeout(() => hideLoading(container), 300);
  const edgeMat = new THREE.LineBasicMaterial({ color: 0x1e293b, transparent: true, opacity: .35 });
  S.bodies.forEach(b => { const e = new THREE.LineSegments(new THREE.EdgesGeometry(b.geometry), edgeMat); e.position.copy(b.position); scene.add(e); });

  // floor slabs + labels (โหมด X-Ray)
  const slabs = new THREE.Group(); slabs.visible = false; scene.add(slabs);
  const slabMat = new THREE.MeshStandardMaterial({ color: 0x818cf8, transparent: true, opacity: .16, depthWrite: false, side: THREE.DoubleSide });
  for (let f = 0; f < floors; f++) {
    const s = new THREE.Mesh(new THREE.BoxGeometry(W - 0.1, 0.12, D - 0.1), slabMat);
    s.position.y = BASE + f * FH + 0.06; slabs.add(s);
    const el = document.createElement('div'); el.className = 'v3d-floor'; el.textContent = 'ชั้น ' + (f + 1);
    const lb = new CSS2DObject(el); lb.position.set(-W / 2 - 1.2, BASE + f * FH + FH / 2, D / 2); slabs.add(lb);
  }

  // room markers
  const ms = Math.max(0.45, Math.min(W, D) * 0.035);
  const markers = [];
  const hl = opts.highlightRoomId ? String(opts.highlightRoomId) : null;
  (opts.rooms || []).forEach(r => {
    const active = hl && String(r.id) === hl;
    const col = new THREE.Color(STATUS_COLORS[r.status] || '#6366f1');
    const fl = Math.min(Math.max(1, parseInt(r.floor, 10) || 1), floors);
    const pos = new THREE.Vector3((parseFloat(r.x) / 100 - 0.5) * W, BASE + (fl - 1) * FH + FH * 0.5, (parseFloat(r.y) / 100 - 0.5) * D);
    const size = active ? ms * 1.5 : ms;
    const sphere = new THREE.Mesh(new THREE.SphereGeometry(size, 24, 16), new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: active ? 1 : .55 }));
    sphere.position.copy(pos); sphere.userData.room = r; scene.add(sphere);
    const ring = new THREE.Mesh(new THREE.RingGeometry(size * 1.4, size * 1.8, 48), new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: .7, side: THREE.DoubleSide, depthWrite: false }));
    ring.rotation.x = -Math.PI / 2; ring.position.set(pos.x, pos.y - FH * 0.45, pos.z); scene.add(ring);
    const beam = new THREE.Mesh(new THREE.CylinderGeometry(size * 0.12, size * 0.12, FH * 0.45, 8), new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: .5 }));
    beam.position.set(pos.x, pos.y - FH * 0.22, pos.z); scene.add(beam);
    const el = document.createElement('div');
    el.className = 'v3d-label' + (active ? ' is-active' : (hl ? ' is-dim' : ''));
    el.innerHTML = `<span class="dot" style="background:${col.getStyle()}"></span>${esc(r.name)}`;
    el.addEventListener('click', () => opts.onRoomClick && opts.onRoomClick(r));
    const lb = new CSS2DObject(el); lb.position.set(0, size * 2.4, 0); sphere.add(lb);
    markers.push({ sphere, ring, active });
  });

  // X-ray
  let xray = false;
  function setXray(on) {
    xray = on;
    S.shell.forEach(m => { m.transparent = on; m.opacity = on ? .2 : 1; m.depthWrite = !on; m.needsUpdate = true; });
    edgeMat.opacity = on ? .8 : .35;
    slabs.visible = on;
    slabs.children.forEach(c => { if (c.isCSS2DObject) { c.visible = on; c.element.style.display = on ? '' : 'none'; } });
  }
  setXray(opts.xray ?? (opts.rooms || []).length > 0);

  // camera
  const homePos = new THREE.Vector3(ctr.x + R * 1.45, BASE + H * 0.55 + R * 0.75, ctr.z + R * 1.9);
  const homeTarget = new THREE.Vector3(ctr.x, BASE + H * 0.42, ctr.z);
  camera.position.set(homePos.x * 2.2, homePos.y * 2.4, homePos.z * 2.2);
  controls.target.copy(homeTarget);
  controls.maxPolarAngle = Math.PI * 0.495;
  controls.minDistance = R * 0.6; controls.maxDistance = R * 6;
  controls.autoRotate = opts.autoRotate ?? true; controls.autoRotateSpeed = 0.7;
  flyTo(camera, controls, homePos, homeTarget, 1800);

  // click on markers
  const ray = new THREE.Raycaster(), mouse = new THREE.Vector2();
  let down = null;
  renderer.domElement.addEventListener('pointerdown', e => { down = [e.clientX, e.clientY]; controls.autoRotate = false; });
  renderer.domElement.addEventListener('pointerup', e => {
    if (!down || Math.hypot(e.clientX - down[0], e.clientY - down[1]) > 5) return;
    const r = renderer.domElement.getBoundingClientRect();
    mouse.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(mouse, camera);
    const hit = ray.intersectObjects(markers.map(m => m.sphere))[0];
    if (hit && opts.onRoomClick) opts.onRoomClick(hit.object.userData.room);
  });

  const onTheme = () => {
    tc = themeColors();
    scene.background.set(tc.bg); scene.fog.color.set(tc.bg); groundMat.color.set(tc.ground);
    scene.remove(grid); grid = new THREE.GridHelper(R * 8, 48, tc.g1, tc.g2);
    grid.position.set(ctr.x, 0.02, ctr.z); grid.material.transparent = true; grid.material.opacity = .55; scene.add(grid);
  };
  document.addEventListener('themechange', onTheme);

  const clock = new THREE.Clock();
  let raf;
  (function loop() {
    raf = requestAnimationFrame(loop);
    const t = clock.getElapsedTime();
    markers.forEach((m, i) => {
      const k = 1 + 0.35 * Math.sin(t * 3 + i);
      m.ring.scale.setScalar(k); m.ring.material.opacity = 0.75 - 0.35 * (k - 0.65);
      if (m.active) m.sphere.position.y += Math.sin(t * 4) * 0.004;
    });
    controls.update();
    renderer.render(scene, camera);
    labels.render(scene, camera);
  })();

  return {
    setXray, get xray() { return xray; },
    setAutoRotate(on) { controls.autoRotate = on; },
    get autoRotate() { return controls.autoRotate; },
    resetView() { flyTo(camera, controls, homePos, homeTarget, 1000); },
    screenshot(name = 'building-3d.png') { const a = document.createElement('a'); a.download = name; a.href = renderer.domElement.toDataURL('image/png'); a.click(); },
    dispose() {
      cancelAnimationFrame(raf); ro.disconnect(); document.removeEventListener('themechange', onTheme);
      scene.traverse(o => { o.geometry?.dispose(); [].concat(o.material || []).forEach(m => { m.map?.dispose(); m.dispose(); }); });
      renderer.dispose(); renderer.forceContextLoss();
      container.querySelectorAll('canvas,.v3d-labels').forEach(n => n.remove());
    },
  };
}

/* =====================================================================
 *  จัดห้องประชุมจาก "ลักษณะห้อง" — เวที โต๊ะ เก้าอี้ คอมพิวเตอร์ เครื่องเสียง ไมค์ จอภาพ
 *  พิกัดห้อง: x = กว้าง (ซ้าย→ขวา), z = ลึก (หน้าห้อง = -D/2 → หลังห้อง = +D/2)
 * ===================================================================== */
const MAX_CHAIRS = 2000;
export const LAYOUT_DEFAULTS ={ stage: false, tableType: 'boardroom', tables: 4, chairs: 16, computers: 0, speakers: 0, mics: 0, display: 'projector' };

function buildRoomFurniture(layout, W, D, H) {
  const L = { ...LAYOUT_DEFAULTS, ...(layout || {}) };
  const g = new THREE.Group();
  const mat = (color, p = {}) => new THREE.MeshStandardMaterial({ color, roughness: .6, ...p });
  const wood = mat(0x7a4a2c, { roughness: .55 }), woodLight = mat(0xb08257, { roughness: .6 }), chairMat = mat(0x1f2937),
    metal = mat(0x9ca3af, { metalness: .6, roughness: .35 }), black = mat(0x111827, { roughness: .4 }), white = mat(0xf3f4f6), stageMat = mat(0x8b5a3c, { roughness: .7 }),
    carpet = mat(0x7f1d1d, { roughness: .95 });
  const box = (w, h, d, m, x, y, z, parent = g) => { const o = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), m); o.position.set(x, y, z); parent.add(o); return o; };
  const cyl = (rt, rb, h, m, x, y, z, parent = g, seg = 16) => { const o = new THREE.Mesh(new THREE.CylinderGeometry(rt, rb, h, seg), m); o.position.set(x, y, z); parent.add(o); return o; };
  // เก้าอี้: rotY = 0 หันหน้าไปทาง -z (หน้าห้อง)
  // เก็บตำแหน่งไว้ก่อน แล้ววาดทีเดียวด้วย InstancedMesh (รองรับเก้าอี้หลักพันตัวโดยไม่หน่วง)
  const seats = [], chairT = [];
  const chair = (x, z, rotY, y0 = 0) => chairT.push([x, y0, z, rotY]);
  const stats = { tables: 0, seatsAtTables: 0, chairs: 0, computers: 0, speakers: 0, mics: 0 };

  // ---- เวที + โพเดียม
  const stageD = L.stage ? Math.min(2.6, Math.max(1.6, D * 0.2)) : 0, stageH = 0.45;
  let pod = null;
  if (L.stage) {
    const sw = Math.min(W - 0.4, Math.max(4, W * 0.75));
    box(sw, stageH, stageD, stageMat, 0, stageH / 2, -D / 2 + stageD / 2);
    box(sw, 0.02, stageD - 0.2, carpet, 0, stageH + 0.01, -D / 2 + stageD / 2);
    box(1.2, stageH * 0.5, 0.4, stageMat, sw / 2 - 0.9, stageH * 0.25, -D / 2 + stageD + 0.2); // บันไดขึ้นเวที
    pod = new THREE.Group(); pod.position.set(W * 0.28 > sw / 2 - 0.5 ? sw / 2 - 0.7 : W * 0.28, stageH, -D / 2 + stageD * 0.55); g.add(pod);
    box(0.6, 1.1, 0.45, wood, 0, 0.55, 0, pod); box(0.66, 0.05, 0.5, woodLight, 0, 1.12, -0.02, pod);
  }

  // ---- พื้นที่ใช้งาน
  const nPc = Math.max(0, Math.min(80, parseInt(L.computers, 10) || 0));
  const sideM = nPc ? 1.9 : 0.7, front = -D / 2 + (L.stage ? stageD + 1.2 : 1.7);
  let back = D / 2 - 0.8;

  // ---- คอมพิวเตอร์พร้อมโต๊ะ (ชิดผนังซ้าย/ขวา แล้วต่อผนังหลัง)
  if (nPc) {
    const slots = [], step = 1.35;
    for (let z = front + 0.3; z <= D / 2 - 0.8; z += step) { slots.push([-W / 2 + 0.33, z, Math.PI / 2, 'x']); slots.push([W / 2 - 0.33, z, -Math.PI / 2, 'x']); }
    const backSlots = [];
    for (let x = -W / 2 + sideM + 0.7; x <= W / 2 - sideM - 0.7; x += step) backSlots.push([x, D / 2 - 0.33, Math.PI, 'z']);
    const all = [...slots, ...backSlots].slice(0, nPc);
    if (all.some(s => s[3] === 'z')) back = D / 2 - 2.0;
    all.forEach(([x, z, rot]) => {
      const pc = new THREE.Group(); pc.position.set(x, 0, z); pc.rotation.y = rot; g.add(pc);
      // โต๊ะคอม: กว้าง 1.2 ลึก 0.6 (ด้านหลังโต๊ะชิดผนังที่ +z ท้องถิ่น)
      box(1.2, 0.04, 0.6, woodLight, 0, 0.74, 0, pc); box(0.04, 0.72, 0.56, woodLight, -0.57, 0.36, 0, pc); box(0.04, 0.72, 0.56, woodLight, 0.57, 0.36, 0, pc);
      box(0.56, 0.34, 0.03, black, 0, 1.12, 0.18, pc); box(0.08, 0.16, 0.06, black, 0, 0.84, 0.2, pc);
      const scr = new THREE.Mesh(new THREE.PlaneGeometry(0.52, 0.3), new THREE.MeshBasicMaterial({ color: 0x3b82f6 })); scr.position.set(0, 1.12, 0.163); scr.rotation.y = Math.PI; pc.add(scr);
      box(0.44, 0.02, 0.15, black, 0, 0.77, -0.05, pc); box(0.2, 0.42, 0.42, black, 0.4, 0.21, 0.05, pc);
      const c = new THREE.Group(); c.position.set(0, 0, -0.62); pc.add(c);
      box(0.48, 0.07, 0.48, chairMat, 0, 0.46, 0, c); box(0.48, 0.5, 0.06, chairMat, 0, 0.75, -0.22, c); cyl(0.03, 0.03, 0.42, metal, 0, 0.22, 0, c, 8);
    });
    stats.computers = all.length;
  }

  const ax0 = -W / 2 + sideM, ax1 = W / 2 - sideM, aw = Math.max(1, ax1 - ax0), ad = Math.max(1, back - front), acx = (ax0 + ax1) / 2, acz = (front + back) / 2;
  const nT = Math.max(0, Math.min(80, parseInt(L.tables, 10) || 0));
  const table = (w, d, x, z, rot = 0) => {
    const t = new THREE.Group(); t.position.set(x, 0, z); t.rotation.y = rot; g.add(t);
    box(w, 0.05, d, wood, 0, 0.75, 0, t);
    [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => box(0.05, 0.72, 0.05, metal, sx * (w / 2 - 0.08), 0.36, sz * (d / 2 - 0.08), t));
    stats.tables++;
  };
  // seat = [x, z, rotY, tableEdgeX, tableEdgeZ] (ตำแหน่งขอบโต๊ะไว้วางไมค์)
  const seat = (x, z, rot) => { const fx = -Math.sin(rot), fz = -Math.cos(rot); seats.push([x, z, rot, x + fx * 0.5, z + fz * 0.5]); };

  if (L.tableType === 'boardroom' && nT) {
    const maxLen = Math.max(1.8, aw - 1.4);
    let rows = 1; while (rows < 6 && Math.ceil(nT / rows) * 1.8 > maxLen) rows++;
    const per = Math.ceil(nT / rows), len = Math.min(per * 1.8, maxLen), dep = rows * 0.9, cz = acz;
    for (let r = 0; r < rows; r++) for (let i = 0; i < per && r * per + i < nT; i++) table(len / per, 0.9, acx - len / 2 + len / per * (i + 0.5), cz - dep / 2 + 0.45 + r * 0.9);
    const n = Math.max(1, Math.floor(len / 0.75));
    for (let i = 0; i < n; i++) { const x = acx - len / 2 + len / n * (i + 0.5); seat(x, cz + dep / 2 + 0.42, 0); seat(x, cz - dep / 2 - 0.42, Math.PI); }
    for (let r = 0; r < Math.max(1, Math.round(dep / 0.9)); r++) { const z = cz - dep / 2 + 0.45 + r * 0.9; seat(acx - len / 2 - 0.45, z, -Math.PI / 2); seat(acx + len / 2 + 0.45, z, Math.PI / 2); }
  } else if (L.tableType === 'u' && nT) {
    const backLen = Math.min(aw - 1.0, Math.max(1.8, Math.ceil(nT / 3) * 1.8));
    const nBack = Math.max(1, Math.min(nT, Math.floor(backLen / 1.8))), tw = backLen / nBack;
    const nArm = Math.floor((nT - nBack) / 2), armMax = Math.max(0, Math.floor((ad - 1.2) / 1.8)), arm = Math.min(nArm, armMax);
    const bz = back - 0.75;
    for (let i = 0; i < nBack; i++) { const x = acx - backLen / 2 + tw * (i + 0.5); table(tw, 0.6, x, bz); for (let k = 0; k < 3; k++) seat(x - tw / 2 + tw / 3 * (k + 0.5), bz + 0.72, 0); }
    [-1, 1].forEach(sd => {
      const x = acx + sd * (backLen / 2 - 0.3);
      for (let i = 0; i < arm; i++) {
        const z = bz - 0.3 - 0.9 - i * 1.8; table(1.8, 0.6, x, z, Math.PI / 2);
        for (let k = 0; k < 3; k++) seat(x + sd * 0.72, z - 0.6 + 0.6 * k, sd < 0 ? -Math.PI / 2 : Math.PI / 2);
      }
    });
  } else if (L.tableType === 'rows' && nT) {
    const cols = Math.max(1, Math.floor((aw + 0.6) / 2.4)), rowsMax = Math.max(1, Math.floor(ad / 1.6));
    const n = Math.min(nT, cols * rowsMax), usedCols = Math.min(cols, n), usedW = usedCols * 1.8 + (usedCols - 1) * 0.6;
    for (let i = 0; i < n; i++) {
      const c = i % cols, r = Math.floor(i / cols), x = acx - usedW / 2 + 0.9 + c * 2.4, z = front + 0.3 + r * 1.6;
      table(1.8, 0.6, x, z); for (let k = 0; k < 3; k++) seat(x - 0.6 + 0.6 * k, z + 0.68, 0);
    }
  } else if (L.tableType === 'round' && nT) {
    const cell = 3.1, cols = Math.max(1, Math.floor(aw / cell)), rowsMax = Math.max(1, Math.floor(ad / cell));
    const n = Math.min(nT, cols * rowsMax), usedCols = Math.min(cols, n), rowsUsed = Math.ceil(n / cols);
    for (let i = 0; i < n; i++) {
      const c = i % cols, r = Math.floor(i / cols), x = acx - (usedCols * cell) / 2 + cell * (c + 0.5), z = acz - (rowsUsed * cell) / 2 + cell * (r + 0.5);
      cyl(0.8, 0.8, 0.05, wood, x, 0.75, z, g, 32); cyl(0.06, 0.06, 0.72, metal, x, 0.36, z); cyl(0.35, 0.35, 0.03, metal, x, 0.015, z);
      cyl(0.84, 0.9, 0.28, white, x, 0.6, z, g, 32); stats.tables++;
      for (let k = 0; k < 8; k++) { const a = k / 8 * Math.PI * 2; seat(x + Math.cos(a) * 1.2, z + Math.sin(a) * 1.2, Math.PI / 2 - a); }
    }
  }
  stats.seatsAtTables = seats.length;

  // ---- เก้าอี้: วางที่โต๊ะก่อน ที่เหลือจัดเป็นแถวแบบโรงละคร (หันหน้าเข้าเวที)
  const nChairs = Math.max(0, Math.min(MAX_CHAIRS, parseInt(L.chairs, 10) || 0));
  const atTables = seats.slice(0, nChairs);
  atTables.forEach(([x, z, r]) => chair(x, z, r));
  let left = nChairs - atTables.length;
  if (left > 0) {
    // มีโต๊ะ: ต่อแถวหลังโต๊ะแถวสุดท้าย · ไม่มีโต๊ะ: แถวเต็มห้องตั้งแต่หน้าห้อง
    const z0 = seats.length ? Math.max(...seats.map(s => s[1])) + 0.9 : front + 0.2, z1 = (nPc && back < D / 2 - 1) ? back : D / 2 - 0.5;
    const cols = Math.max(1, Math.floor(aw / 0.6)), aisle = cols > 8 ? Math.floor(cols / 2) : -1;
    for (let z = z0; z <= z1 + 0.01 && left > 0; z += 0.9)
      for (let c = 0; c < cols && left > 0; c++) {
        if (c === aisle) continue;
        chair(ax0 + 0.3 + c * (aw - 0.6) / Math.max(1, cols - 1), z, 0); left--;
      }
  }
  stats.chairs = nChairs - left;

  // ---- ไมค์: ไมค์โพเดียมก่อน แล้ววางบนโต๊ะหน้าที่นั่ง (ไม่มีโต๊ะ = ไมค์ขาตั้งหน้าห้อง)
  let nMic = Math.max(0, Math.min(100, parseInt(L.mics, 10) || 0));
  const gooseneck = (x, z, rot, y) => {
    const m = new THREE.Group(); m.position.set(x, y, z); m.rotation.y = rot; g.add(m);
    box(0.12, 0.03, 0.09, black, 0, 0.015, 0, m);
    const neck = cyl(0.008, 0.008, 0.38, metal, 0, 0.2, 0.04, m, 6); neck.rotation.x = 0.35;
    const head = cyl(0.022, 0.016, 0.07, black, 0, 0.38, 0.1, m, 8); head.rotation.x = 1.1;
    const led = new THREE.Mesh(new THREE.SphereGeometry(0.012, 8, 6), new THREE.MeshBasicMaterial({ color: 0xef4444 })); led.position.set(0.04, 0.035, -0.03); m.add(led);
    stats.mics++;
  };
  const standMic = (x, z, y = 0) => { cyl(0.13, 0.13, 0.02, black, x, y + 0.01, z); cyl(0.01, 0.01, 1.45, metal, x, y + 0.73, z, g, 6); cyl(0.025, 0.02, 0.12, black, x, y + 1.5, z, g, 8); stats.mics++; };
  if (nMic && pod) { gooseneck(pod.position.x, pod.position.z - 0.08, Math.PI, stageH + 1.14); nMic--; }
  atTables.forEach(([, , rot, tx, tz]) => { if (nMic > 0) { gooseneck(tx, tz, rot, 0.775); nMic--; } });
  for (let i = 0; nMic > 0 && i < 12; i++, nMic--) standMic(-W / 4 + (i % 4) * (W / 6), (L.stage ? -D / 2 + stageD * 0.4 : front - 0.6) + Math.floor(i / 4) * 0.5, L.stage ? stageH : 0);

  // ---- จอภาพ: โปรเจกเตอร์ + จอ / จอ LED
  const screenTex = (() => {
    const c = document.createElement('canvas'); c.width = 640; c.height = 360; const x = c.getContext('2d');
    const gr = x.createLinearGradient(0, 0, 640, 360); gr.addColorStop(0, '#6366f1'); gr.addColorStop(1, '#c026d3');
    x.fillStyle = gr; x.fillRect(0, 0, 640, 360); x.fillStyle = '#fff'; x.font = 'bold 44px "IBM Plex Sans Thai", sans-serif'; x.textAlign = 'center';
    x.fillText('NTC Meeting Room', 320, 195); return canvasTex(c);
  })();
  const baseY = L.stage ? stageH : 0;
  const projScreen = (cx) => {
    const sw = Math.min(W * 0.45, 4.2, (H - 0.9) * 16 / 9 * 0.8), sh = sw * 9 / 16, cy = Math.min(H - 0.35 - sh / 2, baseY + 1.1 + sh / 2);
    box(sw + 0.2, 0.12, 0.14, white, cx, cy + sh / 2 + 0.1, -D / 2 + 0.08);
    const s = new THREE.Mesh(new THREE.PlaneGeometry(sw, sh), new THREE.MeshBasicMaterial({ map: screenTex })); s.position.set(cx, cy, -D / 2 + 0.03); g.add(s);
    box(sw + 0.1, sh + 0.1, 0.01, black, cx, cy, -D / 2 + 0.02);
    const pz = -D / 2 + Math.min(D * 0.5, sw * 1.6 + 0.5);
    cyl(0.02, 0.02, 0.35, metal, cx, H - 0.17, pz, g, 6); box(0.4, 0.14, 0.34, white, cx, H - 0.42, pz);
    const lens = cyl(0.05, 0.05, 0.04, black, cx, H - 0.42, pz - 0.18, g, 12); lens.rotation.x = Math.PI / 2;
  };
  const led = (cx, onStand) => {
    const tw = Math.min(1.9, W * 0.22), th = tw * 9 / 16, cy = onStand ? baseY + 0.9 + th / 2 : Math.min(H - 0.4 - th / 2, baseY + 1.2 + th / 2), z = onStand ? -D / 2 + (L.stage ? stageD * 0.7 : 0.9) : -D / 2 + 0.06;
    box(tw + 0.05, th + 0.05, 0.06, black, cx, cy, z);
    const s = new THREE.Mesh(new THREE.PlaneGeometry(tw, th), new THREE.MeshBasicMaterial({ map: screenTex })); s.position.set(cx, cy, z + 0.032); g.add(s);
    if (onStand) { cyl(0.03, 0.03, cy - th / 2 - baseY, metal, cx, baseY + (cy - th / 2 - baseY) / 2, z - 0.05, g, 8); box(0.7, 0.04, 0.45, black, cx, baseY + 0.02, z - 0.05); }
  };
  if (L.display === 'projector') projScreen(0);
  else if (L.display === 'led') led(0, false);
  else if (L.display === 'both') { projScreen(0); if (W > 6) { led(-W / 2 + Math.max(1.3, W * 0.14), true); led(W / 2 - Math.max(1.3, W * 0.14), true); } }

  // ---- เครื่องเสียง: ลำโพงบนขาตั้ง + แร็กมิกเซอร์หลังห้อง
  const nSp = Math.max(0, Math.min(12, parseInt(L.speakers, 10) || 0));
  if (nSp) {
    const spots = [[-W / 2 + 0.6, -D / 2 + 0.7], [W / 2 - 0.6, -D / 2 + 0.7], [-W / 2 + 0.35, 0], [W / 2 - 0.35, 0], [-W / 2 + 0.6, D / 2 - 0.7], [W / 2 - 0.6, D / 2 - 0.7]];
    for (let i = 0; i < nSp; i++) {
      const [x, z] = spots[i % spots.length], wall = i >= spots.length;
      const zz = wall ? z + (i < 9 ? -1.4 : 1.4) : z;
      const y = i >= 2 && i < 4 || wall ? 2.1 : 1.55;
      if (!(i >= 2 && i < 4) && !wall) { cyl(0.02, 0.02, 1.25, metal, x, 0.62, zz, g, 6); [0, 2.1, 4.2].forEach(a => { const leg = cyl(0.012, 0.012, 0.6, metal, x + Math.cos(a) * 0.18, 0.2, zz + Math.sin(a) * 0.18, g, 4); leg.rotation.z = Math.cos(a) * 0.6; leg.rotation.x = -Math.sin(a) * 0.6; }); }
      const sp = new THREE.Group(); sp.position.set(x, y, zz); sp.lookAt(0, 1.4, 0); g.add(sp);
      box(0.34, 0.56, 0.32, black, 0, 0, 0, sp);
      [0.12, -0.1].forEach((dy, k) => { const cone = cyl(k ? 0.12 : 0.05, k ? 0.12 : 0.05, 0.02, mat(0x374151), 0, dy, 0.165, sp, 16); cone.rotation.x = Math.PI / 2; });
      stats.speakers++;
    }
    const rx = W / 2 - 0.5, rz = D / 2 - 0.5;
    box(0.55, 1.0, 0.55, black, rx, 0.5, rz); box(0.5, 0.08, 0.4, mat(0x374151), rx, 1.04, rz - 0.02);
    for (let i = 0; i < 4; i++) box(0.5, 0.012, 0.01, mat(0x22c55e, { emissive: 0x22c55e, emissiveIntensity: .6 }), rx, 0.25 + i * 0.18, rz - 0.28);
  }

  // ---- วาดเก้าอี้ทั้งหมด (เบาะ / พนักพิง / ขา) แบบ instanced
  if (chairT.length) {
    const m4 = new THREE.Matrix4(), q = new THREE.Quaternion(), up = new THREE.Vector3(0, 1, 0), one = new THREE.Vector3(1, 1, 1), p = new THREE.Vector3();
    [[new THREE.BoxGeometry(0.48, 0.07, 0.48).translate(0, 0.46, 0), chairMat],
     [new THREE.BoxGeometry(0.48, 0.5, 0.06).translate(0, 0.75, 0.22), chairMat],
     [new THREE.CylinderGeometry(0.03, 0.03, 0.42, 8).translate(0, 0.22, 0), metal]].forEach(([geo, m]) => {
      const im = new THREE.InstancedMesh(geo, m, chairT.length);
      chairT.forEach(([x, y, z, r], i) => { q.setFromAxisAngle(up, r); im.setMatrixAt(i, m4.compose(p.set(x, y, z), q, one)); });
      im.instanceMatrix.needsUpdate = true; g.add(im);
    });
  }
  stats.maxChairs = MAX_CHAIRS;
  return { group: g, stats };
}

/* =====================================================================
 *  ห้องประชุม (มุมมองภายใน 360° / Dollhouse)
 * ===================================================================== */
export function createRoomViewer(container, opts = {}) {
  const W = parseFloat(opts.width) || 10, D = parseFloat(opts.depth) || 8, H = parseFloat(opts.height) || 3;
  const imgs = opts.images || {};
  const wallColor = opts.color || '#e8e3d8';
  const { scene, renderer, labels, camera, controls, ro } = baseScene(container);
  renderer.shadowMap.enabled = false;

  scene.add(new THREE.AmbientLight(0xffffff, .55));
  scene.add(new THREE.HemisphereLight(0xffffff, 0x9aa3b5, .9));
  const key = new THREE.PointLight(0xfff4e0, 30, Math.max(W, D) * 2, 1.4); key.position.set(0, H - 0.3, 0); scene.add(key);

  let pending = 0;
  const done = () => { if (--pending <= 0) hideLoading(container); };
  const img = (k, fallback) => {
    const wall = k !== 'floor' && k !== 'ceiling';
    if (imgs[k]) { pending++; return tex(imgs[k], { mirror: wall, onLoad: done }); }
    if (wall) { fallback.wrapS = THREE.RepeatWrapping; fallback.repeat.x = -1; }
    return fallback;
  };
  // BackSide: มองจากภายใน — order [+x right, -x left, +y ceiling, -y floor, +z back, -z front]
  const mats = [
    img('right', wallTexture(wallColor, 'window')),
    img('left', wallTexture(wallColor)),
    img('ceiling', wallTexture('#fff', 'ceiling')),
    img('floor', wallTexture('#666', 'floor')),
    img('back', wallTexture(wallColor)),
    img('front', wallTexture(wallColor, opts.layout ? 'wall' : 'screen')),
  ].map(map => new THREE.MeshStandardMaterial({ map, side: THREE.BackSide, roughness: .9 }));
  const box = new THREE.Mesh(new THREE.BoxGeometry(W, H, D), mats);
  box.position.y = H / 2; scene.add(box);
  if (!pending) setTimeout(() => hideLoading(container), 300);

  // furniture: จัดตามลักษณะห้อง (layout) — ถ้าไม่มี layout ใช้โต๊ะประชุม + เก้าอี้ตามความจุแบบเดิม
  let furniture, stats = null;
  if (opts.layout) {
    const f = buildRoomFurniture(opts.layout, W, D, H);
    furniture = f.group; stats = f.stats; scene.add(furniture);
    furniture.visible = opts.furniture ?? true;
  } else {
  furniture = new THREE.Group(); scene.add(furniture);
  const cap = Math.min(Math.max(parseInt(opts.capacity, 10) || 12, 4), 40);
  const perSide = Math.ceil((cap - 2) / 2);
  const L = Math.min(W * 0.72, Math.max(2.4, perSide * 0.8)), TW = Math.min(D * 0.35, 1.4);
  const wood = new THREE.MeshStandardMaterial({ color: 0x7a4a2c, roughness: .55, metalness: .05 });
  const top = new THREE.Mesh(new THREE.BoxGeometry(L, 0.06, TW), wood); top.position.y = 0.75; furniture.add(top);
  const legGeo = new THREE.BoxGeometry(0.08, 0.72, TW * 0.8);
  [-L / 2 + 0.25, L / 2 - 0.25].forEach(x => { const l = new THREE.Mesh(legGeo, wood); l.position.set(x, 0.36, 0); furniture.add(l); });
  const chairMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, roughness: .6 });
  const chair = (x, z, rotY) => {
    const g = new THREE.Group();
    const seat = new THREE.Mesh(new THREE.BoxGeometry(0.48, 0.07, 0.48), chairMat); seat.position.y = 0.46; g.add(seat);
    const back = new THREE.Mesh(new THREE.BoxGeometry(0.48, 0.5, 0.06), chairMat); back.position.set(0, 0.75, 0.22); g.add(back);
    const post = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.42), chairMat); post.position.y = 0.22; g.add(post);
    g.position.set(x, 0, z); g.rotation.y = rotY; furniture.add(g);
  };
  const step = L / perSide;
  for (let i = 0; i < perSide; i++) {
    const x = -L / 2 + step * (i + 0.5);
    chair(x, TW / 2 + 0.42, 0); chair(x, -TW / 2 - 0.42, Math.PI);
  }
  chair(-L / 2 - 0.5, 0, -Math.PI / 2); chair(L / 2 + 0.5, 0, Math.PI / 2);
  furniture.visible = opts.furniture ?? !Object.keys(imgs).length;
  }

  const name = document.createElement('div'); name.className = 'v3d-label is-active'; name.textContent = opts.name || 'ห้องประชุม';
  const nameObj = new CSS2DObject(name); nameObj.position.set(0, H + 0.4, 0); nameObj.visible = false; scene.add(nameObj);

  // modes
  let mode = 'inside';
  const eye = 1.6;
  function setMode(m) {
    mode = m;
    if (m === 'inside') {
      camera.fov = 72; camera.updateProjectionMatrix();
      controls.enableZoom = false; controls.enablePan = false; controls.rotateSpeed = -0.35;
      controls.minDistance = 0; controls.maxDistance = Infinity;
      controls.minPolarAngle = Math.PI * 0.18; controls.maxPolarAngle = Math.PI * 0.82;
      nameObj.visible = false;
      flyTo(camera, controls, new THREE.Vector3(0, eye, 0.01), new THREE.Vector3(0, eye, 0), 900);
    } else {
      camera.fov = 45; camera.updateProjectionMatrix();
      controls.enableZoom = true; controls.enablePan = false; controls.rotateSpeed = 0.6;
      const R = Math.sqrt(W * W + D * D + H * H);
      controls.minDistance = R * 0.6; controls.maxDistance = R * 3;
      controls.minPolarAngle = 0; controls.maxPolarAngle = Math.PI * 0.47;
      nameObj.visible = true;
      flyTo(camera, controls, new THREE.Vector3(R * 0.75, R * 1.05, R * 0.95), new THREE.Vector3(0, H * 0.25, 0), 1100);
    }
  }
  camera.position.set(0, eye, 0.01); controls.target.set(0, eye, 0);
  setMode(opts.mode || 'inside');
  controls.autoRotate = opts.autoRotate ?? true; controls.autoRotateSpeed = 0.6;
  renderer.domElement.addEventListener('pointerdown', () => { controls.autoRotate = false; });
  renderer.domElement.addEventListener('wheel', e => {
    if (mode !== 'inside') return;
    e.preventDefault();
    camera.fov = Math.min(95, Math.max(30, camera.fov + e.deltaY * 0.04)); camera.updateProjectionMatrix();
  }, { passive: false });

  const onTheme = () => scene.background.set(themeColors().bg);
  document.addEventListener('themechange', onTheme);
  let raf;
  (function loop() { raf = requestAnimationFrame(loop); controls.update(); renderer.render(scene, camera); labels.render(scene, camera); })();

  return {
    setMode, get mode() { return mode; },
    setFurniture(on) { furniture.visible = on; }, get furniture() { return furniture.visible; },
    setAutoRotate(on) { controls.autoRotate = on; }, get autoRotate() { return controls.autoRotate; },
    get stats() { return stats; },
    screenshot(n = 'room-3d.png') { const a = document.createElement('a'); a.download = n; a.href = renderer.domElement.toDataURL('image/png'); a.click(); },
    dispose() {
      cancelAnimationFrame(raf); ro.disconnect(); document.removeEventListener('themechange', onTheme);
      scene.traverse(o => { o.geometry?.dispose(); [].concat(o.material || []).forEach(m => { m.map?.dispose(); m.dispose(); }); });
      renderer.dispose(); renderer.forceContextLoss();
      container.querySelectorAll('canvas,.v3d-labels').forEach(n => n.remove());
    },
  };
}

/* =====================================================================
 *  ภาพนิ่ง 3D ของอาคาร (ใช้ renderer ตัวเดียวร่วมกัน → ไม่เปลือง WebGL context)
 * ===================================================================== */
let snapRenderer = null;
const loadTex = url => new Promise(res => loader.load(url, t => { t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8; res(t); }, undefined, () => res(null)));

export async function renderBuildingSnapshot(opts = {}, { width = 800, height = 500 } = {}) {
  const imgs = opts.images || {};
  if (!snapRenderer) {
    snapRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    snapRenderer.shadowMap.enabled = true; snapRenderer.shadowMap.type = THREE.PCFSoftShadowMap;
    snapRenderer.setClearColor(0x000000, 0);
  }
  const renderer = snapRenderer;
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(width, height, false);

  const keys = ['right', 'left', 'front', 'back'];
  const loaded = Object.fromEntries(await Promise.all(keys.map(async k => [k, imgs[k] ? await loadTex(imgs[k]) : null])));
  const S = buildStructure(opts, Object.fromEntries(Object.entries(loaded).filter(([, t]) => t)));
  const ctr = S.box.getCenter(new THREE.Vector3()), size = S.box.getSize(new THREE.Vector3());
  const R = size.length() / 2;

  const scene = new THREE.Scene();
  scene.add(new THREE.HemisphereLight(0xffffff, 0x8796b0, 1.2));
  const sun = new THREE.DirectionalLight(0xffffff, 1.9);
  sun.position.set(ctr.x + R * 1.3, R * 2.2, ctr.z + R * 1.6); sun.target.position.set(ctr.x, 0, ctr.z); scene.add(sun.target);
  sun.castShadow = true; sun.shadow.mapSize.set(1024, 1024); sun.shadow.bias = -0.0004; sun.shadow.normalBias = 0.04;
  Object.assign(sun.shadow.camera, { left: -R * 2, right: R * 2, top: R * 2, bottom: -R * 2, near: 1, far: R * 8 });
  sun.shadow.radius = 4; scene.add(sun);

  // พื้นโปร่งใสรับเงา + โมเดลอาคาร
  const ground = new THREE.Mesh(new THREE.PlaneGeometry(R * 10, R * 10), new THREE.ShadowMaterial({ opacity: 0.35 }));
  ground.rotation.x = -Math.PI / 2; ground.position.set(ctr.x, 0, ctr.z); ground.receiveShadow = true; scene.add(ground);
  scene.add(S.group);
  const edgeMat = new THREE.LineBasicMaterial({ color: 0x1e293b, transparent: true, opacity: .3 });
  S.bodies.forEach(b => { const e = new THREE.LineSegments(new THREE.EdgesGeometry(b.geometry), edgeMat); e.position.copy(b.position); scene.add(e); });

  // กล้องมุม 3/4 ให้อาคารพอดีกรอบ
  const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 3000);
  const target = new THREE.Vector3(ctr.x, size.y * 0.42, ctr.z);
  const fit = R / Math.sin(THREE.MathUtils.degToRad(35) / 2) * (width / height < 1.4 ? 1.15 : 0.95);
  const dir = new THREE.Vector3(1, 0.62, 1.35).normalize();
  camera.position.copy(target).addScaledVector(dir, fit);
  camera.lookAt(target);

  renderer.render(scene, camera);
  const url = renderer.domElement.toDataURL('image/png');

  scene.traverse(o => {
    o.geometry?.dispose();
    [].concat(o.material || []).forEach(m => { if (m.map && !Object.values(loaded).includes(m.map)) m.map.dispose(); m.dispose(); });
  });
  Object.values(loaded).forEach(t => t?.dispose());
  return url;
}

/* =====================================================================
 *  ภาพนิ่ง 3D ของห้องประชุม (มุมมองตัดผนัง / dollhouse) — ใช้ renderer ร่วมกับอาคาร
 * ===================================================================== */
export async function renderRoomSnapshot(opts = {}, { width = 800, height = 500 } = {}) {
  const W = parseFloat(opts.width) || 10, D = parseFloat(opts.depth) || 8, H = parseFloat(opts.height) || 3;
  const imgs = opts.images || {};
  const wallColor = opts.color || '#e8e3d8';
  if (!snapRenderer) {
    snapRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    snapRenderer.shadowMap.enabled = true; snapRenderer.shadowMap.type = THREE.PCFSoftShadowMap;
    snapRenderer.setClearColor(0x000000, 0);
  }
  const renderer = snapRenderer;
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(width, height, false);

  // ผนังที่เห็นจากมุมกล้อง (+x,+y,+z) คือด้านซ้าย / หน้า / พื้น — BackSide ซ่อนผนังฝั่งใกล้กล้องให้อัตโนมัติ
  const keys = ['right', 'left', 'ceiling', 'floor', 'back', 'front'];
  const loaded = Object.fromEntries(await Promise.all(keys.map(async k => [k, imgs[k] ? await loadTex(imgs[k]) : null])));
  const pick = (k, fallback) => {
    const wall = k !== 'floor' && k !== 'ceiling';
    const t = loaded[k] || fallback;
    if (wall) { t.wrapS = THREE.RepeatWrapping; t.repeat.x = -1; }
    return t;
  };
  const mats = [
    pick('right', wallTexture(wallColor, 'window')),
    pick('left', wallTexture(wallColor)),
    pick('ceiling', wallTexture('#fff', 'ceiling')),
    pick('floor', wallTexture('#666', 'floor')),
    pick('back', wallTexture(wallColor)),
    pick('front', wallTexture(wallColor, opts.layout ? 'wall' : 'screen')),
  ].map(map => new THREE.MeshStandardMaterial({ map, side: THREE.BackSide, roughness: .9 }));

  const scene = new THREE.Scene();
  const box = new THREE.Mesh(new THREE.BoxGeometry(W, H, D), mats);
  box.position.y = H / 2; scene.add(box);
  // ฐานพื้นหนา + เส้นขอบ ให้ดูเป็นโมเดลจำลอง
  const slab = new THREE.Mesh(new THREE.BoxGeometry(W + 0.3, 0.25, D + 0.3), new THREE.MeshStandardMaterial({ color: 0x94a3b8, roughness: .8 }));
  slab.position.y = -0.18; // ต่ำกว่าพื้นห้องเล็กน้อย กัน z-fighting slab.receiveShadow = true; scene.add(slab);
  const edgeMat = new THREE.LineBasicMaterial({ color: 0x1e293b, transparent: true, opacity: .35 });
  const edges = new THREE.LineSegments(new THREE.EdgesGeometry(box.geometry), edgeMat); edges.position.copy(box.position); scene.add(edges);

  if (opts.layout) {
    const f = buildRoomFurniture(opts.layout, W, D, H);
    f.group.traverse(o => { if (o.isMesh) { o.castShadow = true; o.receiveShadow = true; } });
    scene.add(f.group);
  }
  box.receiveShadow = true;

  const R = Math.sqrt(W * W + D * D + H * H) / 2;
  scene.add(new THREE.HemisphereLight(0xffffff, 0x8796b0, 1.25));
  scene.add(new THREE.AmbientLight(0xffffff, .35));
  const sun = new THREE.DirectionalLight(0xfff6e8, 1.6);
  sun.position.set(R * 1.2, R * 2.6, R * 1.6); scene.add(sun); // ไม่ใช้เงา: กล่อง BackSide ทำให้เกิดลายเงา (shadow acne)
  scene.traverse(o => { o.castShadow = o.receiveShadow = false; });

  // กล้องมุมสูง 3/4 มองเข้าไปในห้อง (เห็นเวที/จอที่ผนังหน้า)
  const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 3000);
  const target = new THREE.Vector3(0, H * 0.15, 0);
  const fit = R / Math.sin(THREE.MathUtils.degToRad(35) / 2) * (width / height < 1.4 ? 1.2 : 1.0);
  const dir = new THREE.Vector3(0.85, 1.25, 1.3).normalize();
  camera.position.copy(target).addScaledVector(dir, fit);
  camera.lookAt(target);

  renderer.render(scene, camera);
  const url = renderer.domElement.toDataURL('image/png');

  scene.traverse(o => { o.geometry?.dispose(); [].concat(o.material || []).forEach(m => { m.map?.dispose(); m.dispose(); }); });
  return url;
}
