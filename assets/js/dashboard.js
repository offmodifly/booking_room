(function () {
  'use strict';
  const D = window.DASH || {};
  const modalEl = document.getElementById('bookingModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

  // ---------------- booking modal ----------------
  async function openBooking(id) {
    if (!modal) return;
    document.getElementById('bookingBody').innerHTML = '<div class="text-center py-5"><div class="v3d-spinner mx-auto"></div></div>';
    const go = document.getElementById('bookingGo');
    if (go) go.href = APP.base + '/admin/bookings.php?view=' + id;
    modal.show();
    try {
      const r = await fetch(APP.base + '/api/booking.php?id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const j = await r.json();
      document.getElementById('bookingBody').innerHTML = j.ok ? j.html : '<div class="alert alert-danger">' + escapeHtml(j.message) + '</div>';
    } catch (e) { document.getElementById('bookingBody').innerHTML = '<div class="alert alert-danger">โหลดข้อมูลไม่สำเร็จ</div>'; }
  }
  document.querySelectorAll('[data-booking]').forEach(el => el.addEventListener('click', () => openBooking(el.dataset.booking)));

  // ---------------- calendar ----------------
  const calEl = document.getElementById('calendar');
  let busyDays = new Set();
  const fmtDay = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const cal = new FullCalendar.Calendar(calEl, {
    locale: 'th',
    initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
    height: 'auto',
    firstDay: 0,
    dayMaxEvents: 3,
    nowIndicator: true,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
    buttonText: { today: 'วันนี้', month: 'เดือน', week: 'สัปดาห์', list: 'รายการ' },
    moreLinkText: n => '+ อีก ' + n + ' รายการ',
    noEventsText: 'ไม่มีการจองในช่วงนี้',
    slotMinTime: '07:00:00', slotMaxTime: '20:00:00', allDaySlot: false,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    titleFormat: { year: 'numeric', month: 'long' },
    events(info, success, failure) {
      const room = document.getElementById('calRoom').value;
      const q = new URLSearchParams({ start: info.startStr, end: info.endStr });
      if (room) q.set('room_id', room);
      fetch(APP.base + '/api/events.php?' + q, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(evs => {
          busyDays = new Set();
          evs.forEach(e => { if (e.extendedProps.status === 'approved') busyDays.add(e.start.slice(0, 10)); });
          success(evs);
          markBusy();
        }).catch(failure);
    },
    eventContent(arg) {
      const p = arg.event.extendedProps;
      const t = arg.event.start ? arg.event.start.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
      if (arg.view.type.startsWith('list')) {
        return { html: `<div><b>${escapeHtml(p.room)}</b> · ${escapeHtml(p.topic)} <span class="text-muted">— ${escapeHtml(p.requester)}</span> <span class="badge-soft ms-1" style="--c:${arg.event.backgroundColor}">${escapeHtml(p.statusLabel)}</span></div>` };
      }
      return { html: `<div class="fc-ev" style="--c:${arg.event.backgroundColor}"><b>${t} ${escapeHtml(p.room)}</b><span>${escapeHtml(p.topic)} · ${escapeHtml(p.requester)}</span></div>` };
    },
    eventDidMount(info) {
      const p = info.event.extendedProps;
      info.el.title = `${p.room} (${p.building})\n${p.topic}\nผู้จอง: ${p.requester}${p.department ? ' · ' + p.department : ''}\n${p.time}\nสถานะ: ${p.statusLabel}`;
    },
    eventClick(info) { info.jsEvent.preventDefault(); openBooking(info.event.id); },
    datesSet: markBusy,
  });
  function markBusy() {
    calEl.querySelectorAll('.fc-daygrid-day').forEach(td => td.classList.toggle('has-full', busyDays.has(td.dataset.date)));
  }
  cal.render();
  document.getElementById('calRoom').addEventListener('change', () => cal.refetchEvents());

  // ---------------- room filter ----------------
  document.querySelectorAll('#roomFilter .chip').forEach(ch => ch.addEventListener('click', () => {
    document.querySelectorAll('#roomFilter .chip').forEach(c => c.classList.remove('active'));
    ch.classList.add('active');
    document.querySelectorAll('[data-room-status]').forEach(el => { el.style.display = ch.dataset.f === 'all' || el.dataset.roomStatus === ch.dataset.f ? '' : 'none'; });
  }));
  setTimeout(() => document.querySelectorAll('.progress-thin > div[data-w]').forEach(b => { b.style.width = b.dataset.w; }), 400);

  // ---------------- charts ----------------
  let charts = [];
  function drawCharts() {
    charts.forEach(c => c.destroy()); charts = [];
    const dark = isDark();
    const text = dark ? '#8b95b6' : '#64748b', grid = dark ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.06)';
    Chart.defaults.font.family = "'IBM Plex Sans Thai', sans-serif";
    Chart.defaults.color = text;
    const tooltip = { backgroundColor: dark ? '#1b2445' : '#0f172a', padding: 12, cornerRadius: 10, titleFont: { weight: '600' } };
    const ctxH = document.getElementById('chartHours').getContext('2d');
    const gH = ctxH.createLinearGradient(0, 0, 0, 300); gH.addColorStop(0, '#7c3aed'); gH.addColorStop(1, 'rgba(99,102,241,.35)');
    charts.push(new Chart(ctxH, {
      type: 'bar',
      data: { labels: D.rooms, datasets: [
        { label: 'ชั่วโมงใช้งาน', data: D.hours, backgroundColor: gH, borderRadius: 10, borderSkipped: false, maxBarThickness: 46, yAxisID: 'y' },
        { label: 'อัตราการใช้ (%)', data: D.util, type: 'line', borderColor: '#06b6d4', backgroundColor: '#06b6d4', tension: .4, pointRadius: 4, pointHoverRadius: 6, yAxisID: 'y1' },
      ] },
      options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip },
        scales: { x: { grid: { display: false } }, y: { grid: { color: grid }, beginAtZero: true, title: { display: true, text: 'ชั่วโมง' } }, y1: { position: 'right', grid: { display: false }, beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } } },
    }));
    charts.push(new Chart(document.getElementById('chartStatus'), {
      type: 'doughnut',
      data: { labels: D.status.map(s => s.label), datasets: [{ data: D.status.map(s => s.c), backgroundColor: D.status.map(s => s.color), borderWidth: 3, borderColor: dark ? '#10172d' : '#fff', hoverOffset: 10 }] },
      options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } }, tooltip } },
      plugins: [{ id: 'center', afterDraw(c) {
        const { ctx, chartArea: a } = c, total = D.status.reduce((s, x) => s + x.c, 0);
        ctx.save(); ctx.textAlign = 'center'; ctx.fillStyle = dark ? '#e7eaf6' : '#0f172a';
        ctx.font = "700 28px 'IBM Plex Sans Thai'"; ctx.fillText(total, (a.left + a.right) / 2, (a.top + a.bottom) / 2 + 6);
        ctx.font = "12px 'IBM Plex Sans Thai'"; ctx.fillStyle = text; ctx.fillText('รายการ', (a.left + a.right) / 2, (a.top + a.bottom) / 2 + 26); ctx.restore();
      } }],
    }));
    const ctxT = document.getElementById('chartTrend').getContext('2d');
    const gT = ctxT.createLinearGradient(0, 0, 0, 280); gT.addColorStop(0, 'rgba(99,102,241,.35)'); gT.addColorStop(1, 'rgba(99,102,241,0)');
    charts.push(new Chart(ctxT, {
      type: 'line',
      data: { labels: D.trend.map(t => t.label), datasets: [
        { label: 'การจองทั้งหมด', data: D.trend.map(t => t.total), borderColor: '#6366f1', backgroundColor: gT, fill: true, tension: .4, pointRadius: 5, pointBackgroundColor: '#fff', pointBorderWidth: 3 },
        { label: 'อนุมัติแล้ว', data: D.trend.map(t => t.approved), borderColor: '#ef4444', borderDash: [6, 6], tension: .4, pointRadius: 4, pointBackgroundColor: '#ef4444' },
      ] },
      options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip },
        scales: { x: { grid: { display: false } }, y: { grid: { color: grid }, beginAtZero: true, ticks: { precision: 0 } } } },
    }));
  }
  drawCharts();
  document.addEventListener('themechange', drawCharts);
})();
