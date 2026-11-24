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

  // ---------- helpers exposed ----------
  window.escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  window.cssVar = n => getComputedStyle(root).getPropertyValue(n).trim();
})();
