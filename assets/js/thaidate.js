/* ช่องวันที่แบบไทย: แสดง วัน/เดือน/ปี พ.ศ. (เช่น 25/11/2569) · ค่าที่ส่งไปเซิร์ฟเวอร์ยังเป็น YYYY-MM-DD เหมือนเดิม
 * ใช้: <input type="date" data-thai-date ...> (ต้องโหลด flatpickr + l10n/th ก่อน) */
(function () {
  if (!window.flatpickr) return;
  const pad = n => String(n).padStart(2, '0');
  const BE = 543;

  // แสดงปี พ.ศ. ที่หัวปฏิทิน (ซ่อนช่องปี ค.ศ. เดิม)
  function syncYear(fp) {
    const wrap = fp.calendarContainer.querySelector('.numInputWrapper');
    if (!wrap) return;
    let lbl = fp.calendarContainer.querySelector('.th-year');
    if (!lbl) { lbl = document.createElement('span'); lbl.className = 'th-year'; wrap.after(lbl); wrap.style.display = 'none'; }
    lbl.textContent = fp.currentYear + BE;
  }

  document.querySelectorAll('input[data-thai-date]').forEach(inp => {
    flatpickr(inp, {
      locale: flatpickr.l10ns.th,
      dateFormat: 'Y-m-d',          // ค่าจริงในฟอร์ม
      altInput: true,
      altFormat: 'd/m/Y',           // รูปแบบที่ผู้ใช้เห็น
      altInputClass: inp.className + ' th-date-alt',
      minDate: inp.min || null,
      maxDate: inp.max || null,
      disableMobile: true,          // ใช้ปฏิทินเดียวกันทุกอุปกรณ์ (มือถือจะได้ วัน/เดือน/ปี ด้วย)
      formatDate: (d, fmt, l) => fmt === 'd/m/Y'
        ? `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear() + BE}`
        : flatpickr.formatDate(d, fmt, l),
      onReady: (_, __, fp) => { syncYear(fp); fp.altInput.setAttribute('aria-label', 'วันที่ (วัน/เดือน/ปี พ.ศ.)'); fp.altInput.placeholder = 'วว/ดด/ปปปป'; },
      onMonthChange: (_, __, fp) => syncYear(fp),
      onYearChange: (_, __, fp) => syncYear(fp),
      onOpen: (_, __, fp) => syncYear(fp),
    });
  });
})();
