/* NTC Meeting Room — หน้าเว็บสาธารณะ (ขอใช้ห้อง / ปฏิทิน) */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const DAY0 = 7 * 60, DAY1 = 21 * 60, SPAN = DAY1 - DAY0;
  const pct = m => Math.max(0, Math.min(100, (m - DAY0) / SPAN * 100));
  const thDate = d => new Date(d + 'T00:00:00').toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  /* ---------------- แบบฟอร์มขอใช้ห้อง ---------------- */
  const form = $('#reqForm');
  if (form) {
    const fDate = $('#fDate'), fStart = $('#fStart'), fEnd = $('#fEnd'), fAtt = $('#fAtt');
    const bar = $('#slotBar'), msg = $('#slotMsg'), avail = $('#sAvail'), btn = $('#btnSubmit');
    let busy = [], closed = false, reqId = 0;
    // ลายเซ็นผู้ขอใช้
    const sigCanvas = $('#reqPad');
    const sigPad = window.SignaturePad ? new SignaturePad(sigCanvas, { penColor: '#1e3a8a', minWidth: 0.8, maxWidth: 2.6, backgroundColor: 'rgba(0,0,0,0)' }) : null;
    const sigFit = () => { if (!sigPad) return; const data = sigPad.toData(), r = Math.max(window.devicePixelRatio || 1, 1);
      sigCanvas.width = sigCanvas.offsetWidth * r; sigCanvas.height = sigCanvas.offsetHeight * r; sigCanvas.getContext('2d').scale(r, r); sigPad.clear(); sigPad.fromData(data); };
    sigFit(); window.addEventListener('resize', sigFit);
    $('#reqSigClear').addEventListener('click', () => sigPad && sigPad.clear());
    const nameEl = form.elements.requester_name;
    const syncName = () => { $('#reqSigName').textContent = '( ' + (nameEl.value.trim() || 'ผู้ขอใช้ห้อง') + ' )'; };
    nameEl.addEventListener('input', syncName); syncName();
    const room = () => $('input[name=room_id]:checked', form);

    function overlaps() {
      const s = toMin(fStart.value), e = toMin(fEnd.value);
      return busy.filter(b => toMin(b.start) < e && toMin(b.end === '24:00' ? '23:59' : b.end) > s);
    }
    function render() {
      const r = room();
      $('#sRoom').innerHTML = r ? `<strong>${esc(r.dataset.name)}</strong><br><small class="text-muted">${esc(r.dataset.building)}</small>` : '—';
      $('#sDate').textContent = fDate.value ? thDate(fDate.value) : '—';
      $('#sTime').textContent = `${fStart.value}–${fEnd.value} น.`;
      $('#capHint').textContent = r ? `ความจุ ${r.dataset.cap} ที่นั่ง` : '';
      fAtt.max = r ? r.dataset.cap : '';
      fAtt.classList.toggle('is-invalid', !!r && +fAtt.value > +r.dataset.cap);

      const s = toMin(fStart.value), e = toMin(fEnd.value);
      let state = 'idle', text = 'เลือกห้องและเวลา', icon = 'bi-hourglass';
      if (!r) { /* idle */ }
      else if (closed) { state = 'bad'; text = 'ห้องปิดให้บริการ'; icon = 'bi-x-octagon'; }
      else if (e <= s) { state = 'bad'; text = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม'; icon = 'bi-exclamation-triangle'; }
      else if (overlaps().length) { state = 'bad'; text = 'ช่วงเวลานี้ไม่ว่าง'; icon = 'bi-x-circle'; }
      else if (+fAtt.value > +r.dataset.cap) { state = 'bad'; text = `ผู้เข้าร่วมเกินความจุห้อง (${r.dataset.cap} ที่นั่ง)`; icon = 'bi-people'; }
      else { state = 'ok'; text = `ว่าง · ใช้งาน ${((e - s) / 60).toLocaleString('th-TH', { maximumFractionDigits: 1 })} ชั่วโมง`; icon = 'bi-check-circle'; }
      avail.className = 'avail-pill ' + state;
      avail.innerHTML = `<i class="bi ${icon}"></i>${text}`;
      btn.disabled = state === 'bad';

      if (!r) return;
      const sel = e > s ? `<div class="slot sel ${state === 'bad' ? 'clash' : ''}" style="left:${pct(s)}%;width:${pct(e) - pct(s)}%"></div>` : '';
      bar.innerHTML = (closed ? '<div class="slot-empty">ห้องนี้ปิดให้บริการชั่วคราว</div>' : '') +
        busy.map(b => { const bs = toMin(b.start), be = b.end === '24:00' ? 1440 : toMin(b.end);
          return `<div class="slot ${b.status}" style="left:${pct(bs)}%;width:${pct(be) - pct(bs)}%" title="${esc(b.label)} ${b.start}–${b.end}"><span>${b.start}–${b.end}</span></div>`; }).join('') + sel;
      msg.innerHTML = busy.length
        ? `<i class="bi bi-info-circle me-1"></i>ช่วงที่ไม่ว่าง: ${busy.map(b => `<b>${b.start}–${b.end}</b>`).join(', ')}`
        : (closed ? '' : '<i class="bi bi-check2-circle me-1 text-success"></i>ห้องว่างตลอดทั้งวัน');
    }
    async function load() {
      const r = room();
      if (!r || !fDate.value) { render(); return; }
      const id = ++reqId;
      bar.classList.add('loading');
      try {
        const res = await fetch(`${APP.base}/api/public_slots.php?room_id=${r.value}&date=${fDate.value}`);
        const j = await res.json();
        if (id !== reqId) return;
        busy = j.busy || []; closed = !!j.closed;
      } catch { busy = []; closed = false; }
      bar.classList.remove('loading');
      render();
    }
    // คลิกบนแถบเวลาเพื่อเลือกเวลาเริ่ม
    bar.addEventListener('click', ev => {
      if (!room()) return;
      const rc = bar.getBoundingClientRect();
      const m = Math.round((DAY0 + (ev.clientX - rc.left) / rc.width * SPAN) / 30) * 30;
      const dur = Math.max(30, toMin(fEnd.value) - toMin(fStart.value));
      const pad = v => String(Math.floor(v / 60)).padStart(2, '0') + ':' + String(v % 60).padStart(2, '0');
      const s = Math.min(Math.max(m, DAY0), DAY1 - 30), e = Math.min(s + dur, DAY1);
      fStart.value = pad(s); fEnd.value = pad(e); render();
    });
    $$('input[name=room_id]', form).forEach(i => i.addEventListener('change', load));
    fDate.addEventListener('change', load);
    [fStart, fEnd, fAtt].forEach(el => el.addEventListener('input', render));
    fStart.addEventListener('change', () => { if (toMin(fEnd.value) <= toMin(fStart.value)) { const o = [...fEnd.options].find(o => toMin(o.value) > toMin(fStart.value)); if (o) fEnd.value = o.value; } render(); });

    form.addEventListener('submit', ev => {
      const miss = [];
      if (!room()) miss.push('เลือกห้องประชุม');
      Object.entries({ requester_name: 'ชื่อ-นามสกุล', contact_phone: 'เบอร์โทรศัพท์', title: 'หัวข้อการประชุม' }).forEach(([n, label]) => {
        const el = form.elements[n], empty = !el.value.trim();
        el.classList.toggle('is-invalid', empty); if (empty) miss.push(label);
      });
      const ph = form.elements.contact_phone.value.replace(/\D/g, '').replace(/^66(?=\d{9}$)/, '0');
      if (ph && !/^0\d{8,9}$/.test(ph)) { miss.push('เบอร์โทรศัพท์ให้ถูกต้อง'); form.elements.contact_phone.classList.add('is-invalid'); }
      if (!sigPad || sigPad.isEmpty()) miss.push('ลงลายมือชื่อผู้ขอใช้ห้อง');
      if (!$('#fAgree').checked) miss.push('ยืนยันความถูกต้องของข้อมูล');
      if (miss.length) {
        ev.preventDefault();
        Swal.fire({ icon: 'warning', title: 'กรุณากรอกข้อมูลให้ครบ', html: miss.map(m => '• ' + esc(m)).join('<br>'), confirmButtonColor: '#6366f1' });
        return;
      }
      ev.preventDefault();
      const r = room();
      Swal.fire({
        icon: 'question', title: 'ยืนยันส่งคำขอใช้ห้อง?', confirmButtonText: 'ส่งคำขอ', cancelButtonText: 'ตรวจสอบอีกครั้ง', showCancelButton: true, confirmButtonColor: '#6366f1',
        html: `<div class="text-start small"><b>${esc(r.dataset.name)}</b><br>${thDate(fDate.value)} · ${fStart.value}–${fEnd.value} น.<br>ติดตามสถานะด้วยเบอร์ <b>${esc(form.elements.contact_phone.value)}</b></div>
          <img src="${sigPad.toDataURL()}" style="max-height:80px;margin-top:10px;background:#fff;border-radius:10px;padding:4px;border:1px solid #e4e7f1">`,
      }).then(x => { if (x.isConfirmed) { $('#reqSig').value = sigPad.toDataURL('image/png'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังส่ง...'; form.submit(); } });
    });
    load();
  }

  /* ---------------- หน้าแรก: ตัวกรองห้อง + ปฏิทิน ---------------- */
  const filter = $('#roomFilter');
  if (filter && $('#roomGrid')) filter.addEventListener('click', e => {
    const b = e.target.closest('[data-f]'); if (!b) return;
    $$('.chip', filter).forEach(x => x.classList.toggle('active', x === b));
    $$('#roomGrid > [data-eff]').forEach(c => { c.hidden = b.dataset.f !== 'all' && c.dataset.eff !== b.dataset.f; });
  });

  const calEl = $('#pubCal');
  if (calEl && window.FullCalendar) {
    const sel = $('#calRoom');
    const cal = new FullCalendar.Calendar(calEl, {
      locale: 'th', initialView: 'dayGridMonth', height: 'auto', dayMaxEvents: 3,
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
      buttonText: { today: 'วันนี้', month: 'เดือน', week: 'สัปดาห์', list: 'รายการ' },
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      slotMinTime: '07:00:00', slotMaxTime: '21:00:00', allDaySlot: false,
      events: (info, ok, fail) => fetch(`${APP.base}/api/public_events.php?start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}&room_id=${sel.value}`).then(r => r.json()).then(ok).catch(fail),
      dateClick: info => {
        const d = info.dateStr.slice(0, 10);
        const n = new Date(), today = `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`;
        if (d < today) return;
        location.href = `${APP.base}/request.php?date=${d}` + (sel.value ? `&room=${sel.value}` : '');
      },
    });
    cal.render();
    sel.addEventListener('change', () => cal.refetchEvents());
    $$('[data-room-cal]').forEach(b => b.addEventListener('click', () => {
      sel.value = b.dataset.roomCal; cal.refetchEvents();
      $('#calendarCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
  }
})();
