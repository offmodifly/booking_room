<?php
require __DIR__ . '/../includes/bootstrap.php';
page_guard('users');
$me = current_user();

if (is_post()) {
    csrf_check();
    $action = input('action');
    $id = (int)input('id');
    try {
        if ($action === 'save') {
            $data = [
                'prefix' => mb_substr(input('prefix'), 0, 30),
                'first_name' => mb_substr(input('first_name'), 0, 100),
                'last_name' => mb_substr(input('last_name'), 0, 100),
                'position' => mb_substr(input('position'), 0, 150),
                'role' => input('role'),
                'username' => input('username'),
                'email' => input('email') ?: null,
                'phone' => mb_substr(input('phone'), 0, 30) ?: null,
                'is_active' => input('is_active') ? 1 : 0,
            ];
            $pw = (string)input('password');
            if ($data['first_name'] === '') throw new RuntimeException('กรุณากรอกชื่อ');
            if (!isset(ROLES[$data['role']])) throw new RuntimeException('สิทธิ์การใช้งานไม่ถูกต้อง');
            if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $data['username'])) throw new RuntimeException('ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ . ความยาว 3–50 ตัว');
            if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('รูปแบบอีเมลไม่ถูกต้อง');
            if (db_val('SELECT COUNT(*) FROM users WHERE username=? AND id<>?', [$data['username'], $id])) throw new RuntimeException('ชื่อผู้ใช้นี้ถูกใช้แล้ว');
            if (!$id && strlen($pw) < 8) throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
            if ($id && $pw !== '' && strlen($pw) < 8) throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');
            if ($id === (int)$me['id'] && ($data['role'] !== 'admin' || !$data['is_active'])) throw new RuntimeException('ไม่สามารถลดสิทธิ์หรือระงับบัญชีของตนเองได้');

            $cols = array_keys($data);
            if ($id) {
                $set = implode(',', array_map(fn($c) => "$c=?", $cols));
                $vals = array_values($data);
                if ($pw !== '') { $set .= ',password_hash=?'; $vals[] = password_hash($pw, PASSWORD_DEFAULT); }
                $vals[] = $id;
                db_exec("UPDATE users SET $set WHERE id=?", $vals);
                log_action('user', "แก้ไขผู้ใช้ {$data['username']} (" . ROLES[$data['role']]['label'] . ')' . ($pw !== '' ? ' + เปลี่ยนรหัสผ่าน' : ''));
                flash('success', 'บันทึกข้อมูลผู้ใช้เรียบร้อย');
            } else {
                $cols[] = 'password_hash'; $vals = array_values($data); $vals[] = password_hash($pw, PASSWORD_DEFAULT);
                db_exec('INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
                log_action('user', "เพิ่มผู้ใช้ {$data['username']} (" . ROLES[$data['role']]['label'] . ')');
                flash('success', 'เพิ่มผู้ใช้เรียบร้อย');
            }
        } elseif ($action === 'toggle') {
            if ($id === (int)$me['id']) throw new RuntimeException('ไม่สามารถระงับบัญชีของตนเองได้');
            db_exec('UPDATE users SET is_active = 1 - is_active WHERE id=?', [$id]);
            $u = db_one('SELECT username,is_active FROM users WHERE id=?', [$id]);
            log_action('user', ($u['is_active'] ? 'เปิดใช้งาน' : 'ระงับ') . "บัญชี {$u['username']}");
            json_out(['ok' => true, 'active' => (int)$u['is_active']]);
        } elseif ($action === 'delete') {
            if ($id === (int)$me['id']) throw new RuntimeException('ไม่สามารถลบบัญชีของตนเองได้');
            $u = db_one('SELECT * FROM users WHERE id=?', [$id]);
            if (!$u) throw new RuntimeException('ไม่พบผู้ใช้');
            db_exec('DELETE FROM users WHERE id=?', [$id]);
            delete_upload($u['avatar'] ?? null);
            log_action('user', "ลบผู้ใช้ {$u['username']} (" . full_name($u) . ')');
            flash('success', 'ลบผู้ใช้เรียบร้อย');
        }
    } catch (Throwable $e) {
        if ($action === 'toggle') json_out(['ok' => false, 'message' => $e->getMessage()], 422);
        flash('error', e($e->getMessage()));
    }
    redirect('admin/users.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
}

$roleFilter = qs('role');
$where = ''; $params = [];
if ($roleFilter && isset(ROLES[$roleFilter])) { $where = 'WHERE role=?'; $params[] = $roleFilter; }
$users = db_all("SELECT * FROM users $where ORDER BY FIELD(role,'admin','director','deputy_director','building_head','building_staff','room_staff','user'), first_name", $params);
$roleCounts = array_column(db_all('SELECT role, COUNT(*) c FROM users GROUP BY role'), 'c', 'role');

$pageTitle = 'จัดการผู้ใช้';
$activeMenu = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head reveal">
  <div><span class="eyebrow"><i class="bi bi-people"></i>User Management</span><h2>จัดการผู้ใช้งาน</h2><p>กำหนดชื่อ นามสกุล ตำแหน่ง และสิทธิ์ในการใช้ระบบ</p></div>
  <div class="actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-user="{}"><i class="bi bi-person-plus me-1"></i>เพิ่มผู้ใช้</button></div>
</div>

<div class="row g-3 mb-4">
  <?php $i = 0; foreach (ROLES as $k => $r): ?>
  <div class="col-6 col-md-4 col-xl reveal" style="--i:<?= $i++ ?>">
    <a href="?role=<?= $roleFilter === $k ? '' : $k ?>" class="card-x stat-card d-block text-reset hover <?= $roleFilter === $k ? 'border-primary' : '' ?>" style="--c:<?= $r['color'] ?>;padding:1rem">
      <div class="stat-top"><span class="stat-icon" style="width:40px;height:40px;font-size:1.1rem"><i class="bi <?= $r['icon'] ?>"></i></span><span class="fs-4 fw-bold"><?= (int)($roleCounts[$k] ?? 0) ?></span></div>
      <div class="stat-label small mt-2" style="line-height:1.3"><?= e($r['label']) ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="card-x reveal" style="--i:3">
  <div class="card-x-head">
    <h5><i class="bi bi-person-lines-fill"></i>รายชื่อผู้ใช้ <span class="text-muted fw-normal small">(<?= count($users) ?> คน<?= $roleFilter ? ' · ' . e(ROLES[$roleFilter]['label'] ?? '') : '' ?>)</span></h5>
    <div class="d-flex gap-2">
      <?php if ($roleFilter): ?><a href="?" class="btn btn-sm btn-ghost"><i class="bi bi-x"></i> ล้างตัวกรอง</a><?php endif; ?>
      <div class="search-box"><i class="bi bi-search"></i><input class="form-control form-control-sm" placeholder="ค้นหาชื่อ ตำแหน่ง ชื่อผู้ใช้..." data-filter-table="#userTable" style="min-width:260px"></div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-x table-hover" id="userTable">
      <thead><tr><th>ผู้ใช้</th><th>ตำแหน่ง</th><th>สิทธิ์การใช้งาน</th><th>ติดต่อ</th><th>เข้าใช้ล่าสุด</th><th class="text-center">สถานะ</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $json = $u; unset($json['password_hash']); ?>
        <tr>
          <td><div class="cell-user"><?= avatar($u, 40) ?><div><strong><?= e(full_name($u)) ?></strong><small>@<?= e($u['username']) ?><?= (int)$u['id'] === (int)$me['id'] ? ' · <span class="text-primary">คุณ</span>' : '' ?></small></div></div></td>
          <td><?= e($u['position'] ?: '-') ?></td>
          <td><?= role_badge($u['role']) ?></td>
          <td class="small"><?= $u['email'] ? '<div><i class="bi bi-envelope me-1 text-muted"></i>' . e($u['email']) . '</div>' : '' ?><?= $u['phone'] ? '<div><i class="bi bi-telephone me-1 text-muted"></i>' . e($u['phone']) . '</div>' : '' ?><?= !$u['email'] && !$u['phone'] ? '-' : '' ?></td>
          <td class="small text-muted"><?= e(time_ago($u['last_login'])) ?></td>
          <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" data-toggle-user="<?= $u['id'] ?>" <?= $u['is_active'] ? 'checked' : '' ?> <?= (int)$u['id'] === (int)$me['id'] ? 'disabled' : '' ?>></div></td>
          <td><div class="table-actions">
            <button class="btn-icon sm" title="แก้ไข" data-bs-toggle="modal" data-bs-target="#userModal" data-user="<?= e(json_encode($json, JSON_UNESCAPED_UNICODE)) ?>"><i class="bi bi-pencil"></i></button>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form method="post" class="d-inline" data-confirm="ลบผู้ใช้ <?= e(full_name($u)) ?> ?" data-confirm-text="ประวัติการจองจะยังคงอยู่ แต่ไม่ผูกกับบัญชีนี้" data-ok="ลบผู้ใช้">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn-icon sm text-danger" title="ลบ"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" method="post" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="u_id">
  <div class="modal-header"><h5 class="modal-title"><span class="modal-icon"><i class="bi bi-person-gear"></i></span><span id="u_title">เพิ่มผู้ใช้</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">คำนำหน้า</label><input class="form-control" name="prefix" id="u_prefix" list="prefixList"><datalist id="prefixList"><option>นาย</option><option>นาง</option><option>นางสาว</option><option>ว่าที่ร้อยตรี</option><option>ดร.</option><option>ผศ.</option></datalist></div>
      <div class="col-md-4"><label class="form-label">ชื่อ <span class="text-danger">*</span></label><input class="form-control" name="first_name" id="u_first_name" required></div>
      <div class="col-md-5"><label class="form-label">นามสกุล</label><input class="form-control" name="last_name" id="u_last_name"></div>
      <div class="col-md-7"><label class="form-label">ตำแหน่ง</label><input class="form-control" name="position" id="u_position" placeholder="เช่น ครู แผนกวิชาเทคโนโลยีสารสนเทศ"></div>
      <div class="col-md-5"><label class="form-label">สิทธิ์ในการใช้ระบบ <span class="text-danger">*</span></label>
        <select class="form-select" name="role" id="u_role" required><?php foreach (ROLES as $k => $r): ?><option value="<?= $k ?>"><?= e($r['label']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">อีเมล</label><input class="form-control" type="email" name="email" id="u_email"></div>
      <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" id="u_phone"></div>
      <div class="col-12"><hr class="my-1"></div>
      <div class="col-md-6"><label class="form-label">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">@</span><input class="form-control" name="username" id="u_username" required pattern="[a-zA-Z0-9_.]{3,50}"></div></div>
      <div class="col-md-6"><label class="form-label">รหัสผ่าน <span id="u_pwhint" class="text-muted small"></span></label>
        <div class="input-group"><input class="form-control" type="text" name="password" id="u_password" minlength="8" autocomplete="new-password"><button type="button" class="btn btn-ghost" id="genPw" title="สุ่มรหัสผ่าน"><i class="bi bi-shuffle"></i></button></div></div>
      <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="u_is_active" value="1" checked><label class="form-check-label" for="u_is_active">เปิดใช้งานบัญชี</label></div></div>
    </div>
    <div class="alert alert-info small mt-3 mb-0 d-flex gap-2"><i class="bi bi-info-circle"></i><div><strong>สิทธิ์ในระบบ:</strong> Admin = จัดการทุกเมนู · ผู้อำนวยการ = ลงนามดิจิทัลอนุมัติการจอง · เจ้าหน้าที่ประจำห้องประชุม = รับมอบหมายดูแลห้องในวันประชุม · อื่นๆ = ดูแดชบอร์ด/ปฏิทิน</div></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>บันทึก</button></div>
</form></div></div>

<?php
$extraScripts = <<<'JS'
<script>
document.getElementById('userModal').addEventListener('show.bs.modal', e => {
  const u = JSON.parse(e.relatedTarget.dataset.user || '{}');
  const isNew = !u.id;
  document.getElementById('u_title').textContent = isNew ? 'เพิ่มผู้ใช้' : 'แก้ไขผู้ใช้ · ' + (u.first_name || '');
  ['id','prefix','first_name','last_name','position','email','phone','username'].forEach(k => document.getElementById('u_' + k).value = u[k] || '');
  document.getElementById('u_role').value = u.role || 'user';
  document.getElementById('u_is_active').checked = isNew || u.is_active == 1;
  document.getElementById('u_password').value = '';
  document.getElementById('u_password').required = isNew;
  document.getElementById('u_pwhint').textContent = isNew ? '(อย่างน้อย 8 ตัว)' : '(เว้นว่างหากไม่เปลี่ยน)';
});
document.getElementById('genPw').addEventListener('click', () => {
  const c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#$';
  const a = new Uint32Array(12); crypto.getRandomValues(a);
  document.getElementById('u_password').value = Array.from(a, x => c[x % c.length]).join('');
});
document.querySelectorAll('[data-toggle-user]').forEach(sw => sw.addEventListener('change', async () => {
  const fd = new FormData(); fd.append('action', 'toggle'); fd.append('id', sw.dataset.toggleUser); fd.append('_csrf', APP.csrf);
  const r = await fetch(location.pathname, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const j = await r.json().catch(() => ({}));
  if (j.ok) notify('success', j.active ? 'เปิดใช้งานบัญชีแล้ว' : 'ระงับบัญชีแล้ว');
  else { sw.checked = !sw.checked; notify('error', j.message || 'ไม่สำเร็จ'); }
}));
</script>
JS;
require __DIR__ . '/../includes/footer.php';
