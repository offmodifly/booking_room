<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('logs');

$q = qs('q'); $action = qs('action'); $from = qs('from'); $to = qs('to');
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(username LIKE ? OR detail LIKE ? OR ip LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($action && isset(LOG_ACTIONS[$action])) { $where[] = 'action=?'; $params[] = $action; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'created_at >= ?'; $params[] = "$from 00:00:00"; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $where[] = 'created_at <= ?'; $params[] = "$to 23:59:59"; }
$w = implode(' AND ', $where);

if (qs('export') === 'csv') {
    log_action('backup', 'ส่งออก Log เป็น CSV');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['วันเวลา', 'ผู้ใช้', 'ประเภท', 'รายละเอียด', 'IP', 'User-Agent']);
    $s = db()->prepare("SELECT * FROM logs WHERE $w ORDER BY id DESC");
    $s->execute($params);
    while ($r = $s->fetch()) fputcsv($out, [$r['created_at'], $r['username'], LOG_ACTIONS[$r['action']][0] ?? $r['action'], $r['detail'], $r['ip'], $r['user_agent']]);
    exit;
}

$total = (int)db_val("SELECT COUNT(*) FROM logs WHERE $w", $params);
$pg = paginate($total, (int)qs('page', 1), 25);
$logs = db_all("SELECT * FROM logs WHERE $w ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$stats = db_one("SELECT COUNT(*) total, SUM(DATE(created_at)=CURDATE()) today, SUM(action='login_failed' AND created_at >= NOW() - INTERVAL 1 DAY) failed, COUNT(DISTINCT CASE WHEN created_at >= NOW() - INTERVAL 1 DAY THEN user_id END) users FROM logs");

$pageTitle = 'บันทึกการใช้งาน';
$activeMenu = 'logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-journal-text"></i>Audit Log</span><h2>บันทึกการใช้งานระบบ (Log)</h2><p>ทุกการกระทำสำคัญถูกบันทึก พร้อมผู้ใช้ เวลา และ IP</p></div>
  <div class="actions"><a class="btn btn-ghost" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-filetype-csv me-1"></i>ส่งออก CSV</a></div>
</div>
<div class="row g-3 mb-4">
  <?php foreach ([['Log ทั้งหมด', $stats['total'], 'bi-journal-text', '#6366f1'], ['วันนี้', $stats['today'], 'bi-calendar-day', '#10b981'], ['ผู้ใช้ที่ใช้งาน 24 ชม.', $stats['users'], 'bi-people', '#0ea5e9'], ['เข้าสู่ระบบล้มเหลว 24 ชม.', $stats['failed'], 'bi-shield-exclamation', '#ef4444']] as $i => $c): ?>
    <div class="col-6 col-xl-3 reveal" style="--i:<?= $i ?>"><div class="card-x stat-card" style="--c:<?= $c[3] ?>"><div class="stat-top"><span class="stat-icon"><i class="bi <?= $c[2] ?>"></i></span></div><div class="stat-value" data-count="<?= (int)$c[1] ?>">0</div><div class="stat-label"><?= $c[0] ?></div></div></div>
  <?php endforeach; ?>
</div>
<div class="card-x reveal" style="--i:2">
  <form class="card-x-head" method="get">
    <div class="d-flex flex-wrap gap-2 flex-grow-1">
      <div class="search-box" style="min-width:240px"><i class="bi bi-search"></i><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="ค้นหาผู้ใช้ รายละเอียด IP"></div>
      <select class="form-select form-select-sm" name="action" style="width:auto"><option value="">ทุกประเภท</option><?php foreach (LOG_ACTIONS as $k => $a): ?><option value="<?= $k ?>" <?= $action === $k ? 'selected' : '' ?>><?= e($a[0]) ?></option><?php endforeach; ?></select>
      <input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>" style="width:auto" title="ตั้งแต่วันที่">
      <input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>" style="width:auto" title="ถึงวันที่">
      <button class="btn btn-sm btn-ghost"><i class="bi bi-funnel"></i> กรอง</button>
      <?php if ($q || $action || $from || $to): ?><a class="btn btn-sm btn-ghost" href="?"><i class="bi bi-x"></i></a><?php endif; ?>
    </div>
    <span class="small text-muted"><?= number_format($total) ?> รายการ</span>
  </form>
  <div class="table-responsive"><table class="table table-x table-hover">
    <thead><tr><th>วันเวลา</th><th>ผู้ใช้</th><th>ประเภท</th><th>รายละเอียด</th><th>IP</th></tr></thead><tbody>
    <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-mini"><i class="bi bi-inbox"></i>ไม่พบข้อมูล</div></td></tr><?php endif; ?>
    <?php foreach ($logs as $l): $a = LOG_ACTIONS[$l['action']] ?? [$l['action'], '#64748b', 'bi-dot']; ?>
      <tr><td class="small text-nowrap"><?= e(thai_date($l['created_at'], 'datetime')) ?><br><span class="text-muted"><?= e(time_ago($l['created_at'])) ?></span></td>
        <td><strong><?= e($l['username']) ?></strong></td>
        <td><?= badge($a[0], $a[1], $a[2]) ?></td>
        <td class="small" style="max-width:520px"><?= e($l['detail']) ?></td>
        <td class="small"><span class="code-pill" title="<?= e($l['user_agent']) ?>"><?= e($l['ip']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if ($pg['pages'] > 1): ?><div class="card-x-body d-flex justify-content-end border-top"><?= pagination_html($pg) ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
