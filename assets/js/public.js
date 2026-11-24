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
    const fDate = $('#fDate'), fDateEnd = $('#fDateEnd'), fStart = $('#fStart'), fEnd = $('#fEnd'), fAtt = $('#fAtt');
    const bar = $('#slotBar'), msg = $('#slotMsg'), avail = $('#sAvail'), btn = $('#btnSubmit');
    let busy = [], closed = false, reqId = 0, rangeClash = [];
    // ช่วงหลายวัน
    const dEnd = () => (fDateEnd && fDateEnd.value > fDate.value ? fDateEnd.value : fDate.value);
    const multi = () => dEnd() > fDate.value;
    const nDays = () => Math.round((new Date(dEnd()) - new Date(fDate.value)) / 864e5) + 1;
    const thShort = d => new Date(d + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    const whenText = () => multi()
      ? `${thShort(fDate.value)} ${fStart.value} น. – ${thShort(dEnd())} ${fEnd.value} น. (${nDays()} วัน)`
      : `${thDate(fDate.value)} · ${fStart.value}–${fEnd.value} น.`;
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
      const isMulti = multi();
      $('#sDate').textContent = !fDate.value ? '—' : isMulti ? `${thShort(fDate.value)} – ${thShort(dEnd())} (${nDays()} วัน)` : thDate(fDate.value);
      $('#sTime').textContent = isMulti ? `เริ่ม ${fStart.value} น. · สิ้นสุด ${fEnd.value} น.` : `${fStart.value}–${fEnd.value} น.`;
      const hint = $('#rangeHint');
      if (hint) {
        hint.hidden = !isMulti;
        if (isMulti) hint.querySelector('span').innerHTML = `จองต่อเนื่อง <b>${nDays()} วัน</b> ตั้งแต่ <b>${esc(thDate(fDate.value))}</b> เวลา ${fStart.value} น. ถึง <b>${esc(thDate(dEnd()))}</b> เวลา ${fEnd.value} น.`;
      }
      const st = $('#slotTitle'); if (st) st.textContent = isMulti ? `ช่วงเวลาของห้องในวันแรก (${thShort(fDate.value)})` : 'ช่วงเวลาของห้องในวันที่เลือก';
      $('#capHint').textContent = r ? `ความจุ ${r.dataset.cap} ที่นั่ง` : '';
      fAtt.max = r ? r.dataset.cap : '';
      fAtt.classList.toggle('is-invalid', !!r && +fAtt.value > +r.dataset.cap);

      const s = toMin(fStart.value), e = toMin(fEnd.value);
      let state = 'idle', text = 'เลือกห้องและเวลา', icon = 'bi-hourglass';
      if (!r) { /* idle */ }
      else if (closed) { state = 'bad'; text = 'ห้องปิดให้บริการ'; icon = 'bi-x-octagon'; }
      else if (!isMulti && e <= s) { state = 'bad'; text = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม'; icon = 'bi-exclamation-triangle'; }
      else if (isMulti ? rangeClash.length : overlaps().length) { state = 'bad'; text = 'ช่วงเวลานี้ไม่ว่าง'; icon = 'bi-x-circle'; }
      else if (+fAtt.value > +r.dataset.cap) { state = 'bad'; text = `ผู้เข้าร่วมเกินความจุห้อง (${r.dataset.cap} ที่นั่ง)`; icon = 'bi-people'; }
      else { state = 'ok'; text = isMulti ? `ว่าง · ใช้งาน ${nDays()} วัน` : `ว่าง · ใช้งาน ${((e - s) / 60).toLocaleString('th-TH', { maximumFractionDigits: 1 })} ชั่วโมง`; icon = 'bi-check-circle'; }
      avail.className = 'avail-pill ' + state;
      avail.innerHTML = `<i class="bi ${icon}"></i>${text}`;
      btn.disabled = state === 'bad';

      if (!r) return;
      const selE = isMulti ? DAY1 : e; // หลายวัน: วันแรกใช้ตั้งแต่เวลาเริ่มไปจนหมดวัน
      const sel = selE > s ? `<div class="slot sel ${state === 'bad' ? 'clash' : ''}" style="left:${pct(s)}%;width:${pct(selE) - pct(s)}%"></div>` : '';
      bar.innerHTML = (closed ? '<div class="slot-empty">ห้องนี้ปิดให้บริการชั่วคราว</div>' : '') +
        busy.map(b => { const bs = toMin(b.start), be = b.end === '24:00' ? 1440 : toMin(b.end);
          return `<div class="slot ${b.status}" style="left:${pct(bs)}%;width:${pct(be) - pct(bs)}%" title="${esc(b.label)} ${b.start}–${b.end}"><span>${b.start}–${b.end}</span></div>`; }).join('') + sel;
      if (isMulti) {
        msg.innerHTML = closed ? '' : rangeClash.length
          ? `<i class="bi bi-x-circle me-1 text-danger"></i>ช่วงที่เลือกชนกับการจองอื่น: ${rangeClash.map(c => `<b>${esc(c.from)} – ${esc(c.to)}</b>`).join(', ')}`
          : `<i class="bi bi-check2-circle me-1 text-success"></i>ห้องว่างตลอดช่วง ${nDays()} วันที่เลือก`;
      } else {
        msg.innerHTML = busy.length
          ? `<i class="bi bi-info-circle me-1"></i>ช่วงที่ไม่ว่าง: ${busy.map(b => `<b>${b.start}–${b.end}</b>`).join(', ')}`
          : (closed ? '' : '<i class="bi bi-check2-circle me-1 text-success"></i>ห้องว่างตลอดทั้งวัน');
      }
    }
    async function load() {
      const r = room();
      if (!r || !fDate.value) { render(); return; }
      const id = ++reqId;
      bar.classList.add('loading');
      try {
        const q = new URLSearchParams({ room_id: r.value, date: fDate.value });
        if (multi()) { q.set('date_end', dEnd()); q.set('start', fStart.value); q.set('end', fEnd.value); }
        const res = await fetch(`${APP.base}/api/public_slots.php?${q}`);
        const j = await res.json();
        if (id !== reqId) return;
        busy = j.busy || []; closed = !!j.closed; rangeClash = j.range || [];
      } catch { busy = []; closed = false; rangeClash = []; }
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
      fStart.value = pad(s);
      if (!multi()) fEnd.value = pad(e); // หลายวัน: คลิกแถบ (วันแรก) = เลือกเวลาเริ่มอย่างเดียว
      multi() ? load() : render();
    });
    $$('input[name=room_id]', form).forEach(i => i.addEventListener('change', load));
    // วันที่เริ่ม → ปรับ "ถึงวันที่" ให้ไม่ก่อนวันเริ่ม และไม่เกินจำนวนวันสูงสุด
    const MAXD = +(fDateEnd?.dataset.maxDays || 31);
    const addDays = (d, n) => { const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`; };
    fDate.addEventListener('change', () => {
      if (fDateEnd) {
        const fp = fDateEnd._flatpickr, lim = addDays(fDate.value, MAXD - 1);
        const max = fDateEnd.dataset.hardMax && fDateEnd.dataset.hardMax < lim ? fDateEnd.dataset.hardMax : lim;
        if (fp) { fp.set('minDate', fDate.value); fp.set('maxDate', max); } else { fDateEnd.min = fDate.value; fDateEnd.max = max; }
        if (!fDateEnd.value || fDateEnd.value < fDate.value || fDateEnd.value > max) { fp ? fp.setDate(fDate.value, false) : (fDateEnd.value = fDate.value); }
      }
      load();
    });
    fDateEnd?.addEventListener('change', load);
    [fStart, fEnd, fAtt].forEach(el => el.addEventListener('input', render));
    fStart.addEventListener('change', () => { if (!multi() && toMin(fEnd.value) <= toMin(fStart.value)) { const o = [...fEnd.options].find(o => toMin(o.value) > toMin(fStart.value)); if (o) fEnd.value = o.value; } multi() ? load() : render(); });
    fEnd.addEventListener('change', () => { if (multi()) load(); });

    form.addEventListener('submit', ev => {
      const miss = [];
      if (!room()) miss.push('เลือกห้องประชุม');
      Object.entries({ requester_name: 'ชื่อ-นามสกุล', contact_phone: 'เบอร์โทรศัพท์', title: 'ขอใช้สถานที่เพื่อ' }).forEach(([n, label]) => {
        const el = form.elements[n], empty = !el.value.trim();
        el.classList.toggle('is-invalid', empty); if (empty) miss.push(label);
      });
      const ph = form.elements.contact_phone.value.replace(/\D/g, '').replace(/^66(?=\d{9}$)/, '0');
      if (ph && !/^0\d{8,9}$/.test(ph)) { miss.push('เบอร์โทรศัพท์ให้ถูกต้อง'); form.elements.contact_phone.classList.add('is-invalid'); }
      const eqNeed = form.querySelector('input[name="equip[need]"]:checked');
      if (eqNeed && eqNeed.value === '1' && !form.querySelector('input[name^="equip[on]"]:checked')) miss.push('เลือกอุปกรณ์อย่างน้อย 1 รายการ (หรือเลือก "ไม่ต้องการ")');
      if (multi() && fDateEnd.value < fDate.value) miss.push('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มใช้');
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
        html: `<div class="text-start small"><b>${esc(r.dataset.name)}</b><br>${esc(whenText())}<br>ติดตามสถานะด้วยเบอร์ <b>${esc(form.elements.contact_phone.value)}</b></div>
          <img src="${sigPad.toDataURL()}" style="max-height:80px;margin-top:10px;background:#fff;border-radius:10px;padding:4px;border:1px solid #e4e7f1">`,
      }).then(x => { if (x.isConfirmed) { $('#reqSig').value = sigPad.toDataURL('image/png'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังส่ง...'; form.submit(); } });
    });
    load();
    // ส่งไม่สำเร็จ (เซิร์ฟเวอร์ปฏิเสธ) → แจ้งชัด ๆ เป็นป๊อปอัป
    if (window.__REQ_ERROR && window.Swal) {
      Swal.fire({ icon: 'error', title: 'ส่งคำขอไม่สำเร็จ', text: window.__REQ_ERROR, confirmButtonText: 'แก้ไขข้อมูล', confirmButtonColor: '#6366f1' })
        .then(() => $('#reqError')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    }
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
