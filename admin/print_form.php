<?php
/** แบบฟอร์มการขอใช้สถานที่ (พิมพ์ A4) — รูปแบบตามฟอร์มกระดาษของงานอาคารสถานที่ · 1 รายการต่อ 1 หน้า · เฉพาะรายการที่อนุมัติแล้ว */
require __DIR__ . '/../includes/bootstrap.php';
page_guard('printform');
$me = current_user();

$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)($_GET['id'] ?? []))))), 0, 100);
$rows = $ids ? db_all('SELECT b.*, r.name room_name, r.code room_code, r.floor room_floor, bd.name building_name, u.position req_position
  FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN buildings bd ON bd.id=r.building_id LEFT JOIN users u ON u.id=b.user_id
  WHERE b.status=\'approved\' AND b.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY b.start_datetime', $ids) : [];
if ($rows) log_action('booking', 'พิมพ์ฟอร์มขอใช้สถานที่ ' . implode(', ', array_column($rows, 'booking_no')));

// ตัวเลือกสถานที่ตามฟอร์มกระดาษ (ข้อ 1) — ถ้าชื่อห้อง/อาคารตรงกับตัวเลือกจะติ๊กตัวเลือกนั้น ไม่เช่นนั้นติ๊ก "ห้องหรืออาคาร"
const FORM_VENUES = [['หอประชุมวิษณุรักษ์', 'วิษณุรักษ์'], ['อาคารภักดีนวมินทร์', 'ภักดีนวมินทร์'], ['โรงอาหาร', 'โรงอาหาร'], ['สนามฟุตบอล', 'สนามฟุตบอล']];

$dShort = fn(?string $dt) => $dt ? date('j', strtotime($dt)) . '/' . date('n', strtotime($dt)) . '/' . ((int)date('Y', strtotime($dt)) + 543) : '......../......../........';
$thDay = function (string $dt): string { $t = strtotime($dt); return (int)date('j', $t) . ' ' . TH_MONTHS[(int)date('n', $t)] . ' ' . ((int)date('Y', $t) + 543); };
/** ช่วงวันที่แบบกระชับ: "25 – 26 พฤศจิกายน 2569" (เดือนเดียวกัน) · "30 พ.ย. – 2 ธ.ค. 2569" */
$thRange = function (string $s, string $e) use ($thDay): string {
    $a = strtotime($s); $b = strtotime($e);
    if (date('Y-m', $a) === date('Y-m', $b)) return (int)date('j', $a) . ' – ' . $thDay($e);
    if (date('Y', $a) === date('Y', $b)) return (int)date('j', $a) . ' ' . TH_MONTHS_S[(int)date('n', $a)] . ' – ' . (int)date('j', $b) . ' ' . TH_MONTHS_S[(int)date('n', $b)] . ' ' . ((int)date('Y', $b) + 543);
    return $thDay($s) . ' – ' . $thDay($e);
};
// ช่องกรอก (เส้นจุด) — ค่าจากระบบแสดงเป็นหมึกสีน้ำเงินเหมือนเขียนด้วยมือ
$f = fn($v, string $w = '') => '<span class="f' . ($w ? " $w" : '') . '">' . ($v !== null && $v !== '' ? e((string)$v) : '') . '</span>';
$radio = fn(bool $on) => '<span class="rd' . ($on ? ' on' : '') . '"></span>';
$box = fn(bool $on) => '<span class="cb' . ($on ? ' on' : '') . '"></span>';
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>แบบฟอร์มการขอใช้สถานที่<?= count($rows) === 1 ? ' ' . e($rows[0]['booking_no']) : '' ?></title>
<?= favicon_tag() ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root{--ink:#111;--pen:#1d3b8f}
  *{box-sizing:border-box}
  html,body{margin:0;background:#d1d5db;color:var(--ink);font-family:"Sarabun",sans-serif;font-size:15.5px;line-height:1.42}
  .toolbar{position:sticky;top:0;z-index:5;display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:center;padding:.7rem 1rem;background:#111827;color:#fff}
  .toolbar button,.toolbar a{display:inline-flex;align-items:center;gap:.4rem;border:0;border-radius:10px;padding:.5rem 1rem;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}
  .toolbar .p{background:#4f46e5;color:#fff}.toolbar .s{background:#374151;color:#fff}
  .toolbar small{opacity:.75}
  .page{width:210mm;height:297mm;margin:12px auto;padding:13mm 17mm 10mm 19mm;background:#fff;box-shadow:0 6px 24px rgba(0,0,0,.18);position:relative;overflow:hidden;page-break-after:always;break-after:page}
  .page:last-child{page-break-after:auto;break-after:auto}

  .title{border:1.5px solid var(--ink);box-shadow:3px 3px 0 var(--ink);text-align:center;font-weight:700;padding:8px 10px 6px;margin:0 18mm 0 8mm;line-height:1.75}
  .title .t1{font-size:19px}.title .t2{font-size:16.5px}
  .date{text-align:right;margin:10px 0 10px}
  p{margin:0 0 4px}
  .row{margin-bottom:3px}
  .f{display:inline-block;min-width:60px;border-bottom:1.2px dotted var(--ink);padding:0 6px;color:var(--pen);font-weight:400;line-height:1.25;vertical-align:baseline;text-align:center}
  .f.s{min-width:46px}.f.m{min-width:130px}.f.l{min-width:230px}.f.xl{min-width:300px}
  .row{display:flex;align-items:baseline;gap:6px}
  .row .f.grow{flex:1;text-align:left}
  .k{font-weight:700}
  .opts{display:grid;grid-template-columns:150px 1fr 1fr;gap:3px 10px;margin:6px 0 0 40px}
  .opts .wide{grid-column:2/-1}
  .rd{display:inline-block;width:15px;height:15px;border:1.5px solid var(--ink);border-radius:50%;vertical-align:-2px;margin-right:6px;position:relative}
  .rd.on::after,.cb.on::after{content:"";position:absolute;left:4px;top:-3px;width:6px;height:12px;border:solid var(--pen);border-width:0 2.5px 2.5px 0;transform:rotate(40deg)}
  .cb{display:inline-block;width:14px;height:14px;border:1.5px solid var(--ink);vertical-align:-2px;margin-right:6px;position:relative}
  .eq{display:grid;grid-template-columns:225px 1fr;gap:2px 14px;margin:4px 0 0 190px}
  .eq .f.m{min-width:100px}
  .eq .wide{grid-column:1/-1}
  .fee{margin-top:8px;text-indent:52px}
  .fee .f.m{min-width:90px}.fee .f.l{min-width:150px}
  .signs{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:26px;row-gap:26px;margin-top:22px}
  .sb{display:flex;flex-direction:column}
  .sb .h{font-weight:400;margin-bottom:2px}
  .sb .line{border-bottom:1.2px dotted var(--ink);height:25px;color:var(--pen);padding:0 4px;line-height:25px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
  .sb .sig{position:relative;margin-top:24px;padding-left:4px;white-space:nowrap}
  .sb .sig .img{position:absolute;left:78px;bottom:2px;height:46px;pointer-events:none}
  .sb .sig .img img{max-height:46px;max-width:180px}
  .sb .sig .dots{display:inline-block;width:62%;border-bottom:1.2px dotted var(--ink);height:1.2em}
  .sb .nm{text-align:center;width:80%;margin-top:2px}
  .sb .nm .pen{color:var(--pen)}
  .sb .role{text-align:center;width:80%;font-weight:700}
  .sb .dt{text-align:center;width:80%;margin-top:2px}
  .sb .dt span{color:var(--pen);border-bottom:1.2px dotted var(--ink);padding:0 10px}
  .req .sig{margin-top:34px}
  .meta{position:absolute;left:19mm;right:17mm;bottom:7mm;font-size:10.5px;white-space:nowrap;color:#6b7280;display:flex;justify-content:space-between;gap:10px;border-top:1px solid #e5e7eb;padding-top:4px}
  .meta .ok{color:#047857}.meta .bad{color:#b91c1c}
  .empty{max-width:520px;margin:60px auto;background:#fff;border-radius:14px;padding:30px;text-align:center}
  @page{size:A4;margin:0}
  @media print{html,body{background:#fff}.toolbar{display:none}.page{margin:0;box-shadow:none}}
  @media screen and (max-width:820px){.page{width:auto;height:auto;margin:8px;padding:18px}.title{margin:0}.opts,.eq{margin-left:0;grid-template-columns:1fr}.opts .wide{grid-column:auto}.signs{grid-template-columns:1fr}.meta{position:static;margin-top:16px}}
</style>
</head>
<body>
<div class="toolbar">
  <button class="p" onclick="window.print()"><i class="bi bi-printer"></i>พิมพ์ / บันทึกเป็น PDF</button>
  <a class="s" href="<?= url('admin/print_forms.php') ?>"><i class="bi bi-arrow-left"></i>กลับไปเลือกรายการ</a>
  <small><?= count($rows) ?> รายการ · กระดาษ A4 · ปิด "ส่วนหัวและท้ายกระดาษ" ในหน้าต่างพิมพ์</small>
</div>
<?php if (!$rows): ?>
  <div class="empty"><h3>ไม่พบรายการที่อนุมัติแล้ว</h3><p>กรุณาเลือกรายการจากหน้า "พิมพ์ฟอร์มขอใช้สถานที่"</p></div>
<?php endif; ?>
<?php foreach ($rows as $b):
    $sigs = booking_signatures((int)$b['id']);
    $chain = signature_chain($b, $sigs);
    $S = []; foreach ($sigs as $s) $S[$s['step']] = $s;
    $eq = booking_equipment($b['equip'] ?? null);
    $I = $eq['items'];
    $multi = substr($b['start_datetime'], 0, 10) !== substr($b['end_datetime'], 0, 10);
    $place = $b['room_name'] . ' ' . $b['building_name'];
    $venue = null; foreach (FORM_VENUES as $i => [, $kw]) if (mb_strpos($place, $kw) !== false) { $venue = $i; break; }
    $c = strtotime($b['created_at']);
    $sign = function (?array $s, string $roleLabel = '', string $fallbackName = '') use ($dShort) {
        ob_start(); ?>
        <div class="sig"><?php if ($s): ?><span class="img"><img src="<?= e(upload_url($s['file'])) ?>" alt="ลายเซ็น"></span><?php endif; ?>(ลงชื่อ)<span class="dots"></span></div>
        <div class="nm">( <span class="pen"><?= e($s['signer_name'] ?? $fallbackName) ?: str_repeat('&nbsp;', 40) ?></span> )</div>
        <?php if ($roleLabel): ?><div class="role"><?= e($roleLabel) ?></div><?php endif; ?>
        <div class="dt"><span><?= $s ? e($dShort($s['signed_at'])) : '......../......../........' ?></span></div>
        <?php return ob_get_clean();
    };
    // ความเห็น = หมายเหตุที่ผู้ลงนามเขียน (ถ้าไม่มี ใช้ผลการพิจารณา) — ไม่พิมพ์ซ้ำถ้าหมายเหตุขึ้นต้นด้วยผลเดียวกัน
    $note = function (string $step) use ($S): string {
        if (!isset($S[$step])) return '';
        $dec = ($S[$step]['decision'] === 'reject' ? 'ไม่' : '') . ($step === 'director' ? 'อนุมัติ' : 'เห็นควรอนุมัติ');
        $n = trim((string)$S[$step]['note']);
        return $n === '' ? $dec : (mb_strpos($n, $dec) === 0 ? $n : "$dec — $n");
    };
?>
<section class="page">
  <div class="title"><div class="t1">แบบฟอร์มการขอใช้สถานที่</div><div class="t2">งานอาคารสถานที่ &nbsp;<?= e(ORG_NAME) ?></div></div>

  <div class="date">วันที่<?= $f(date('j', $c), 's') ?>เดือน<?= $f(TH_MONTHS[(int)date('n', $c)], 'm') ?>พ.ศ.<?= $f((int)date('Y', $c) + 543, 's') ?></div>

  <div class="row" style="padding-left:52px">ข้าพเจ้า<?= $f($b['requester_name'], 'grow') ?>ตำแหน่ง<?= $f($b['req_position'] ?: '', 'l') ?></div>
  <div class="row">แผนก/งาน<?= $f($b['department'], 'grow') ?>โทร<?= $f($b['contact_phone'], 'l') ?></div>
  <div class="row">มีความประสงค์ขออนุญาตใช้สถานที่เพื่อ<?= $f($b['title'], 'grow') ?></div>
  <div class="row">จำนวนผู้ใช้<?= $f((int)$b['attendees'], 's') ?>คน ในวันที่<?= $f($multi ? $thRange($b['start_datetime'], $b['end_datetime']) . ' (' . booking_days($b['start_datetime'], $b['end_datetime']) . ' วัน)' : $thDay($b['start_datetime']), 'grow') ?>เวลา<?= $f(date('H.i', strtotime($b['start_datetime'])), 's') ?>น. ถึง<?= $f(date('H.i', strtotime($b['end_datetime'])), 's') ?>น.</div>
  <p>และโปรดดำเนินการดังนี้</p>

  <div class="opts">
    <div class="k">1. จัดห้อง/สถานที่</div>
    <div class="wide"><?= $radio($venue === null) ?>ห้องหรืออาคาร<?= $f($venue === null ? $place : '', 'xl') ?></div>
    <div></div><div><?= $radio($venue === 0) ?><?= FORM_VENUES[0][0] ?></div><div><?= $radio($venue === 1) ?><?= FORM_VENUES[1][0] ?></div>
    <div></div><div><?= $radio($venue === 2) ?><?= FORM_VENUES[2][0] ?></div><div><?= $radio($venue === 3) ?><?= FORM_VENUES[3][0] ?></div>
  </div>

  <div class="opts" style="grid-template-columns:150px auto 1fr;margin-top:12px">
    <div class="k">2. อุปกรณ์</div><div><?= $radio(!$eq['need']) ?>ไม่ต้องการ</div><div style="padding-left:40px"><?= $radio($eq['need']) ?>ต้องการให้จัด</div>
  </div>
  <div class="eq">
    <div><?= $box(isset($I['reg_table'])) ?>โต๊ะลงทะเบียน<?= $f($I['reg_table'] ?? '', 's') ?>ตัว</div><div><?= $box(isset($I['sofa'])) ?>ชุดรับแขก</div>
    <div><?= $box(isset($I['plastic_chair'])) ?>เก้าอี้พลาสติก<?= $f($I['plastic_chair'] ?? '', 's') ?>ตัว</div><div><?= $box(isset($I['altar'])) ?>โต๊ะหมู่บูชา</div>
    <div><?= $box(isset($I['podium'])) ?>โพเดียม<?= $f($I['podium'] ?? '', 's') ?>ตัว</div><div><?= $box(isset($I['flowers'])) ?>ดอกไม้<?= $f($I['flowers'] ?? '', 's') ?>ชุด</div>
    <div><?= $box(isset($I['stage'])) ?>เวทีขนาด<?= $f($I['stage'] ?? '', 'm') ?></div><div><?= $box(isset($I['sound'])) ?>เครื่องขยายเสียง<?= $f(is_string($I['sound'] ?? null) ? $I['sound'] : '', 'm') ?></div>
    <div class="wide"><?= $box(isset($I['other'])) ?>อื่นๆ<?= $f($I['other'] ?? '', 'xl') ?></div>
  </div>

  <p class="fee">ผู้ขอใช้ยินดีชำระค่าบำรุงสถานที่เป็นเงิน<?= $f('', 'm') ?>(<?= $f('', 'l') ?>)
    และหากมีอุปกรณ์ชำรุด เสียหาย ที่เกิดจากการขอใช้สถานที่ในครั้งนี้ยินดีชดเชยค่าเสียหายตามสภาพและความเหมาะสมที่วิทยาลัยฯพิจารณา</p>
  <?php if ($b['purpose']): ?><p style="font-size:14.5px">หมายเหตุ: <span style="color:var(--pen)"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $b['purpose']), 0, 160, '…')) ?></span></p><?php endif; ?>

  <div class="signs">
    <div class="sb req"><?= $sign($S['requester'] ?? null, 'ผู้ขอใช้', $b['requester_name']) ?></div>
    <div class="sb">
      <div class="h">ความเห็นของหัวหน้างานอาคารสถานที่</div>
      <div class="line"><?= e($note('building_head')) ?></div>
      <?= $sign($S['building_head'] ?? null) ?>
    </div>
    <div class="sb">
      <div class="h">ความเห็นของรองผู้อำนวยการฝ่ายบริหารฯ</div>
      <div class="line"><?= e($note('deputy_director')) ?></div><div class="line"></div>
      <?= $sign($S['deputy_director'] ?? null) ?>
    </div>
    <div class="sb">
      <div class="h">ความเห็นของผู้อำนวยการ<?= e(ORG_NAME) ?></div>
      <div class="line"><?= e($note('director')) ?></div><div class="line"></div>
      <?= $sign($S['director'] ?? null) ?>
    </div>
  </div>

  <div class="meta">
    <span>เลขที่คำขอ <?= e($b['booking_no']) ?> · <?= e($place) ?> ชั้น <?= (int)$b['room_floor'] ?></span>
    <span class="<?= $chain['complete'] ? 'ok' : 'bad' ?>"><?= $chain['complete'] ? '✔ ลายเซ็นดิจิทัลครบ 4 ขั้น ตรวจสอบถูกต้อง' : '✖ ลายเซ็นดิจิทัลไม่ครบ/ตรวจสอบไม่ผ่าน' ?></span>
    <span>พิมพ์ <?= e($dShort(date('Y-m-d'))) ?> <?= date('H:i') ?> น.</span>
  </div>
</section>
<?php endforeach; ?>
</body>
</html>
