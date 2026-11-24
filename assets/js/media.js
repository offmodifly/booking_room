/* ตัวอัปโหลดรูปหลายไฟล์ + เลือกด้าน/ผนังให้แต่ละรูป
 * ย่อรูปในเบราว์เซอร์ก่อนอัปโหลด (ด้านยาวสุด maxSide px → JPG) — อัปโหลดเร็วขึ้น และรูปจากมือถือไม่ติดลิมิตขนาดไฟล์
 * ฝั่งเซิร์ฟเวอร์ (save_uploaded_images) ย่อซ้ำอีกชั้น เผื่อเบราว์เซอร์ย่อไม่ได้ */
const fmtMB = b => (b / 1048576).toFixed(b < 1048576 ? 2 : 1) + 'MB';

async function resizeImage(file, maxSide, quality) {
  // createImageBitmap หมุนรูปตาม EXIF ให้อัตโนมัติ (รูปจากมือถือไม่ตะแคง)
  const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
  const { width: w, height: h } = bmp;
  const scale = Math.min(1, maxSide / Math.max(w, h));
  const nw = Math.round(w * scale), nh = Math.round(h * scale);
  // ไม่ต้องย่อ และเป็น JPG ขนาดไม่ใหญ่อยู่แล้ว → ใช้ไฟล์เดิม
  if (scale === 1 && file.type === 'image/jpeg' && file.size < 1.5 * 1048576) { bmp.close(); return { file, w, h, nw, nh, resized: false }; }
  const c = document.createElement('canvas'); c.width = nw; c.height = nh;
  const ctx = c.getContext('2d');
  ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, nw, nh); // PNG โปร่งใส → พื้นขาว
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(bmp, 0, 0, nw, nh); bmp.close();
  const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', quality));
  if (!blob) throw new Error('toBlob failed');
  const out = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
  return { file: out, w, h, nw, nh, resized: true };
}

window.initImageUploader = function ({ input, preview, options, field, maxSide = 1920, quality = 0.85 }) {
  const inp = document.querySelector(input), box = document.querySelector(preview);
  if (!inp || !box) return;
  const form = inp.form;
  const opts = Object.entries(options).map(([v, l]) => `<option value="${v}">${escapeHtml(l)}</option>`).join('');
  let busy = null;

  inp.addEventListener('change', () => {
    const files = Array.from(inp.files);
    box.innerHTML = '';
    const cards = files.map((f, i) => {
      const div = document.createElement('div');
      div.className = 'thumb reveal'; div.style.setProperty('--i', i);
      div.innerHTML = `<img src="${URL.createObjectURL(f)}" alt=""><span class="thumb-new badge text-bg-primary">ใหม่</span>
        <div class="thumb-body"><select class="form-select form-select-sm" name="${field}[${i}]">${opts.replace('value="other"', 'value="other" selected')}</select>
        <div class="small text-muted text-truncate mt-1" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</div>
        <div class="small thumb-size text-muted"><span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>กำลังย่อรูป · ${fmtMB(f.size)}</div></div>`;
      box.appendChild(div);
      return div;
    });

    busy = (async () => {
      const dt = new DataTransfer();
      for (const [i, f] of files.entries()) {
        const info = cards[i].querySelector('.thumb-size');
        try {
          const r = await resizeImage(f, maxSide, quality);
          dt.items.add(r.file);
          info.className = 'small thumb-size ' + (r.resized ? 'text-success' : 'text-muted');
          info.innerHTML = r.resized
            ? `<i class="bi bi-arrows-angle-contract me-1"></i>${r.w}×${r.h} · ${fmtMB(f.size)} → <strong>${r.nw}×${r.nh} · ${fmtMB(r.file.size)}</strong>`
            : `<i class="bi bi-check2 me-1"></i>${r.w}×${r.h} · ${fmtMB(f.size)} (ขนาดพอดีแล้ว)`;
        } catch (err) {
          console.error(err);
          dt.items.add(f); // ย่อไม่ได้ → ส่งไฟล์เดิม ให้เซิร์ฟเวอร์ย่อแทน
          info.className = 'small thumb-size text-warning';
          info.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${fmtMB(f.size)} · ย่อในเบราว์เซอร์ไม่ได้ (เซิร์ฟเวอร์จะย่อให้)`;
        }
      }
      inp.files = dt.files; // แทนที่ไฟล์ที่จะอัปโหลดด้วยไฟล์ที่ย่อแล้ว (ลำดับเดิม → ช่องเลือกผนังตรงกัน)
    })().finally(() => { busy = null; });
  });

  // กดบันทึกระหว่างกำลังย่อ → รอให้เสร็จก่อนแล้วค่อยส่ง
  form?.addEventListener('submit', async e => {
    if (!busy) return;
    e.preventDefault();
    const btn = e.submitter;
    if (btn) { btn.disabled = true; btn.dataset.html = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังย่อรูป...'; }
    await busy;
    if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.html; }
    form.requestSubmit(btn || undefined);
  });

  // ทำเครื่องหมายลบรูปเดิม
  document.querySelectorAll('[data-del-img]').forEach(cb => cb.addEventListener('change', () => cb.closest('.thumb').classList.toggle('is-del', cb.checked)));
};
