/* ตัวอัปโหลดรูปหลายไฟล์ + เลือกด้าน/ผนังให้แต่ละรูป */
window.initImageUploader = function ({ input, preview, options, field }) {
  const inp = document.querySelector(input), box = document.querySelector(preview);
  if (!inp || !box) return;
  const opts = Object.entries(options).map(([v, l]) => `<option value="${v}">${escapeHtml(l)}</option>`).join('');
  inp.addEventListener('change', () => {
    box.innerHTML = '';
    Array.from(inp.files).forEach((f, i) => {
      const url = URL.createObjectURL(f);
      const div = document.createElement('div');
      div.className = 'thumb reveal'; div.style.setProperty('--i', i);
      div.innerHTML = `<img src="${url}" alt=""><span class="thumb-new badge text-bg-primary">ใหม่</span>
        <div class="thumb-body"><select class="form-select form-select-sm" name="${field}[${i}]">${opts.replace('value="other"', 'value="other" selected')}</select>
        <div class="small text-muted text-truncate mt-1" title="${escapeHtml(f.name)}">${escapeHtml(f.name)} · ${(f.size / 1048576).toFixed(1)}MB</div></div>`;
      box.appendChild(div);
    });
  });
  // ทำเครื่องหมายลบรูปเดิม
  document.querySelectorAll('[data-del-img]').forEach(cb => cb.addEventListener('change', () => cb.closest('.thumb').classList.toggle('is-del', cb.checked)));
};
