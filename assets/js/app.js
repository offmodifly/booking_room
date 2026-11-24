/* NTC Meeting Room — global UI behaviours */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const root = document.documentElement;

  // ---------- toast / alert helpers ----------
  const Toast = window.Swal ? Swal.mixin({
    toast: true, position: 'top-end', showConfirmButton: false, timer: 3200, timerProgressBar: true,
  }) : null;
  window.notify = (icon, title) => Toast ? Toast.fire({ icon, title }) : console.log(icon, title);
  const typeMap = { success: 'success', error: 'error', danger: 'error', warning: 'warning', info: 'info' };
  (window.__FLASH || []).forEach((f, i) => setTimeout(() => {
    if (f.type === 'error' || f.type === 'danger') Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', html: f.msg, confirmButtonColor: '#6366f1' });
    else notify(typeMap[f.type] || 'info', f.msg);
  }, 250 + i * 400));

  // ---------- theme ----------
  function setTheme(t) {
    root.setAttribute('data-bs-theme', t);
    try { localStorage.setItem('ntc-theme', t); } catch (e) {}
    document.dispatchEvent(new CustomEvent('themechange', { detail: t }));
    fetch(APP.base + '/api/theme.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': APP.csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: 'theme=' + encodeURIComponent(t)
    }).catch(() => {});
  }
  $$('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
    setTheme(root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
    b.querySelectorAll('.bi').forEach(i => { i.style.animation = 'none'; i.offsetHeight; i.style.animation = ''; });
  }));
  window.isDark = () => root.getAttribute('data-bs-theme') === 'dark';

  // ---------- sidebar ----------
  $$('[data-sidebar-toggle]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('sidebar-open')));
  $$('[data-sidebar-close]').forEach(b => b.addEventListener('click', () => document.body.classList.remove('sidebar-open')));
  $$('[data-sidebar-mini]').forEach(b => b.addEventListener('click', () => {
    root.classList.toggle('sidebar-mini');
    try { localStorage.setItem('ntc-mini', root.classList.contains('sidebar-mini') ? '1' : '0'); } catch (e) {}
    setTimeout(() => window.dispatchEvent(new Event('resize')), 320);
  }));

  // ---------- clock ----------
  const clock = $('[data-clock]');
  if (clock) setInterval(() => { clock.textContent = new Date().toLocaleTimeString('th-TH', { hour12: false }); }, 1000);

  // ---------- count-up numbers ----------
  const counters = $$('[data-count]');
  const io = 'IntersectionObserver' in window ? new IntersectionObserver(ents => ents.forEach(en => {
    if (!en.isIntersecting) return;
    io.unobserve(en.target);
    const el = en.target, to = parseFloat(el.dataset.count) || 0, dec = (el.dataset.count.split('.')[1] || '').length, t0 = performance.now();
    const step = now => {
      const p = Math.min(1, (now - t0) / 1100), v = to * (1 - Math.pow(1 - p, 3));
      el.textContent = v.toLocaleString('th-TH', { minimumFractionDigits: dec, maximumFractionDigits: dec });
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }), { threshold: .3 }) : null;
  counters.forEach(c => io ? io.observe(c) : (c.textContent = c.dataset.count));

  // ---------- confirm before submit / link ----------
  document.addEventListener('submit', e => {
    const f = e.target;
    if (!f.matches('[data-confirm]') || f.dataset.confirmed) return;
    e.preventDefault();
    Swal.fire({
      icon: f.dataset.icon || 'warning', title: f.dataset.confirm, html: f.dataset.confirmText || '',
      showCancelButton: true, confirmButtonText: f.dataset.ok || 'ยืนยัน', cancelButtonText: 'ยกเลิก',
      confirmButtonColor: f.dataset.color || '#ef4444', cancelButtonColor: '#64748b', reverseButtons: true,
    }).then(r => { if (r.isConfirmed) { f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); } });
  }, true);

  // ---------- tooltips ----------
  if (window.bootstrap) $$('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // ---------- table quick-filter ----------
  $$('[data-filter-table]').forEach(inp => {
    const table = $(inp.dataset.filterTable);
    inp.addEventListener('input', () => {
      const q = inp.value.trim().toLowerCase();
      $$('tbody tr', table).forEach(tr => { tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none'; });
    });
  });

  // ---------- dropzone highlight ----------
  $$('.dropzone').forEach(dz => {
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('drag')));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('drag')));
  });

  // ---------- ช่องอุปกรณ์ที่ขอให้จัด (booking_equipment_fields) ----------
  document.addEventListener('change', e => {
    const box = e.target.closest('[data-eq]');
    if (!box) return;
    if (e.target.name === 'equip[need]') {
      box.querySelector('.eq-grid').hidden = e.target.value !== '1';
    } else if (e.target.type === 'checkbox') {
      const inp = e.target.closest('.eq-item').querySelector('.eq-in input');
      if (inp) { inp.disabled = !e.target.checked; if (e.target.checked) inp.focus(); }
    }
  });

  // ---------- ช่องเบอร์โทรแบบบล็อก (data-phone-blocks) : [0][x][x]-[x][x][x]-[x][x][x][x] ----------
  document.querySelectorAll('[data-phone-blocks]').forEach(box => {
    const inp = box.querySelector('input');
    if (!inp) return;
    const GROUPS = [3, 3, 4], MAX = 10;
    const cellsWrap = document.createElement('div');
    cellsWrap.className = 'pb-cells'; cellsWrap.setAttribute('aria-hidden', 'true');
    const cells = [];
    GROUPS.forEach((n, gi) => {
      if (gi) { const s = document.createElement('span'); s.className = 'pb-sep'; s.textContent = '–'; cellsWrap.appendChild(s); }
      for (let i = 0; i < n; i++) { const c = document.createElement('span'); c.className = 'pb-cell'; cellsWrap.appendChild(c); cells.push(c); }
    });
    const ok = document.createElement('i'); ok.className = 'bi bi-check-circle-fill pb-ok'; cellsWrap.appendChild(ok);
    box.appendChild(cellsWrap);

    const digitsOf = v => { let d = String(v).replace(/\D/g, ''); if (d.startsWith('66') && d.length >= 11) d = '0' + d.slice(2); return d.slice(0, MAX); };
    const fmt = d => d.length === 9 && d.startsWith('02') && document.activeElement !== inp
      ? `${d.slice(0, 2)}-${d.slice(2, 5)}-${d.slice(5)}`                 // กรุงเทพฯ 02-xxx-xxxx (ตอนกรอกเสร็จ)
      : [d.slice(0, 3), d.slice(3, 6), d.slice(6)].filter(Boolean).join('-'); // มือถือ 0xx-xxx-xxxx · ต่างจังหวัด 0xx-xxx-xxx
    const valid = d => /^0[689]\d{8}$/.test(d) || /^0[2-7]\d{7}$/.test(d);
    const paint = () => {
      const d = digitsOf(inp.value), focused = document.activeElement === inp;
      cells.forEach((c, i) => {
        c.textContent = d[i] || '';
        c.classList.toggle('filled', i < d.length);
        c.classList.toggle('active', focused && i === Math.min(d.length, MAX - 1) && !(d.length === MAX));
      });
      const good = valid(d);
      box.classList.toggle('is-valid', good);
      box.classList.toggle('is-focus', focused);
      if (good && inp.classList.contains('is-invalid')) inp.classList.remove('is-invalid');
    };
    const sync = () => {
      const d = digitsOf(inp.value);
      inp.value = fmt(d);
      const end = inp.value.length; try { inp.setSelectionRange(end, end); } catch (e) {}
      paint();
    };
    inp.addEventListener('input', sync);
    inp.addEventListener('focus', () => { sync(); });
    inp.addEventListener('blur', () => { inp.value = fmt(digitsOf(inp.value)); paint(); });
    // คลิกที่ไหนในกล่องก็โฟกัส + เคอร์เซอร์ไปท้ายเสมอ (ป้อนต่อ/ลบจากท้าย)
    inp.addEventListener('click', () => { const end = inp.value.length; try { inp.setSelectionRange(end, end); } catch (e) {} });
    inp.value = fmt(digitsOf(inp.value)); paint();
  });

  // ---------- helpers exposed ----------
  window.escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  window.cssVar = n => getComputedStyle(root).getPropertyValue(n).trim();
})();
