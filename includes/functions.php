<?php
/**
 * ฟังก์ชันกลางของระบบ
 */

const ROLES = [
    'admin'           => ['label' => 'ผู้ดูแลระบบ (Admin)',                'color' => '#ef4444', 'icon' => 'bi-shield-lock'],
    'director'        => ['label' => 'ผู้อำนวยการวิทยาลัยเทคนิคน่าน',      'color' => '#8b5cf6', 'icon' => 'bi-award'],
    'deputy_director' => ['label' => 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร',  'color' => '#6366f1', 'icon' => 'bi-person-badge'],
    'building_head'   => ['label' => 'หัวหน้างานอาคาร',                    'color' => '#0ea5e9', 'icon' => 'bi-person-gear'],
    'building_staff'  => ['label' => 'เจ้าหน้าที่งานอาคาร',                'color' => '#14b8a6', 'icon' => 'bi-buildings'],
    'room_staff'      => ['label' => 'เจ้าหน้าที่ประจำห้องประชุม',         'color' => '#f59e0b', 'icon' => 'bi-door-open'],
    'user'            => ['label' => 'บุคลากร (ผู้ขอใช้ห้อง)',              'color' => '#64748b', 'icon' => 'bi-person'],
];

const BOOKING_STATUS = [
    'pending'         => ['label' => 'จอง · รอหัวหน้างานอาคาร',   'short' => 'รอหัวหน้างานอาคาร', 'color' => '#f59e0b', 'icon' => 'bi-hourglass-split'],
    'head_approved'   => ['label' => 'จอง · รอรองผู้อำนวยการฯ',   'short' => 'รอรอง ผอ.',        'color' => '#0ea5e9', 'icon' => 'bi-person-gear'],
    'deputy_approved' => ['label' => 'จอง · รอผู้อำนวยการ',       'short' => 'รอ ผอ.',           'color' => '#8b5cf6', 'icon' => 'bi-person-badge'],
    'signed'          => ['label' => 'จอง · ผอ. อนุมัติแล้ว',     'short' => 'รอ Admin ยืนยัน',  'color' => '#3b82f6', 'icon' => 'bi-pen'],
    'approved'        => ['label' => 'ไม่ว่าง · อนุมัติแล้ว',     'short' => 'อนุมัติแล้ว',      'color' => '#ef4444', 'icon' => 'bi-check2-circle'],
    'rejected'        => ['label' => 'ไม่อนุมัติ',                'short' => 'ไม่อนุมัติ',       'color' => '#64748b', 'icon' => 'bi-x-circle'],
    'cancelled'       => ['label' => 'ยกเลิก',                    'short' => 'ยกเลิก',           'color' => '#94a3b8', 'icon' => 'bi-slash-circle'],
];

/**
 * ขั้นตอนการลงนามตามลำดับ: ผู้ขอใช้ → หัวหน้างานอาคาร → รองผู้อำนวยการฝ่ายบริหารทรัพยากร → ผู้อำนวยการ
 * from = สถานะที่รอขั้นนี้ลงนาม, to = สถานะหลังลงนามอนุมัติ (ไม่อนุมัติ → rejected)
 */
const SIGN_STEPS = [
    'requester'       => ['label' => 'ผู้ขอใช้ห้อง',                     'role' => null,              'from' => null,              'to' => 'pending',         'icon' => 'bi-person-lines-fill', 'color' => '#6366f1'],
    'building_head'   => ['label' => 'หัวหน้างานอาคาร',                  'role' => 'building_head',   'from' => 'pending',         'to' => 'head_approved',   'icon' => 'bi-person-gear',       'color' => '#0ea5e9'],
    'deputy_director' => ['label' => 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร', 'role' => 'deputy_director', 'from' => 'head_approved',   'to' => 'deputy_approved', 'icon' => 'bi-person-badge',      'color' => '#8b5cf6'],
    'director'        => ['label' => 'ผู้อำนวยการ',                      'role' => 'director',        'from' => 'deputy_approved', 'to' => 'signed',          'icon' => 'bi-award',             'color' => '#c026d3'],
];
/** สถานะที่ยังรอผู้ลงนาม (ก่อน ผอ. อนุมัติ) */
const WAITING_STATUSES = ['pending', 'head_approved', 'deputy_approved'];

const ROOM_STATUS = [
    'available'   => ['label' => 'ว่าง',    'color' => '#10b981', 'icon' => 'bi-check-circle'],
    'booked'      => ['label' => 'จอง',     'color' => '#f59e0b', 'icon' => 'bi-bookmark-check'],
    'unavailable' => ['label' => 'ไม่ว่าง', 'color' => '#ef4444', 'icon' => 'bi-x-octagon'],
];

const BUILDING_FACES = ['front' => 'ด้านหน้า', 'right' => 'ด้านขวา', 'back' => 'ด้านหลัง', 'left' => 'ด้านซ้าย', 'roof' => 'หลังคา', 'other' => 'อื่นๆ (ระบบจัดให้)'];
/** ลักษณะอาคารสำหรับปั้นโมเดล 3D: key => [หัวข้อ, ไอคอน, [ค่า => ป้ายชื่อ]] */
const BUILDING_STYLE_OPTS = [
    'shape'    => ['ทรงอาคาร', 'bi-bounding-box', ['box' => 'สี่เหลี่ยม', 'L' => 'ตัว L', 'U' => 'ตัว U', 'T' => 'ตัว T']],
    'roof'     => ['หลังคา', 'bi-house', ['flat' => 'ดาดฟ้า (แบน)', 'gable' => 'จั่ว', 'hip' => 'ปั้นหยา', 'thai' => 'ทรงไทย', 'curved' => 'โค้ง', 'sawtooth' => 'ฟันเลื่อย']],
    'corridor' => ['ระเบียงทางเดิน', 'bi-layout-three-columns', ['none' => 'ไม่มี', 'front' => 'ด้านหน้า', 'both' => 'หน้า-หลัง', 'around' => 'รอบอาคาร']],
    'ground'   => ['ชั้นล่าง', 'bi-columns', ['closed' => 'เป็นห้องปิด', 'open' => 'ใต้ถุนโล่ง (มีเสา)']],
    'windows'  => ['หน้าต่าง', 'bi-window', ['punched' => 'บานเดี่ยวเป็นช่อง', 'band' => 'บานยาวต่อเนื่อง', 'glass' => 'กระจกทั้งผนัง']],
    'stair'    => ['โถงบันได', 'bi-bar-chart-steps', ['none' => 'ไม่มี', 'left' => 'ด้านซ้าย', 'right' => 'ด้านขวา', 'center' => 'กลางด้านหน้า']],
];
const BUILDING_STYLE_DEFAULT = ['shape' => 'box', 'roof' => 'flat', 'corridor' => 'none', 'ground' => 'closed', 'windows' => 'punched', 'stair' => 'none', 'roofColor' => '#9c4a3a', 'trim' => '#f8fafc', 'sign' => true];
/** ลักษณะห้องประชุมสำหรับจัดฉาก 3D: ชนิดโต๊ะ => [ป้าย, ไอคอน, ที่นั่งต่อโต๊ะ] */
const ROOM_TABLE_TYPES = ['boardroom' => ['โต๊ะประชุมยาว', 'bi-distribute-horizontal', 4], 'u' => ['จัดตัว U', 'bi-align-bottom', 3], 'rows' => ['แถวหน้ากระดาน', 'bi-list-columns', 3], 'round' => ['โต๊ะกลม (จัดเลี้ยง)', 'bi-circle', 8], 'none' => ['ไม่มีโต๊ะ (เก้าอี้แถว)', 'bi-grid-3x3', 0]];
const ROOM_DISPLAYS    = ['none' => 'ไม่มี', 'projector' => 'โปรเจกเตอร์ + จอ', 'led' => 'จอ LED / TV', 'both' => 'โปรเจกเตอร์ + จอ LED'];
/** ช่องตัวเลข: key => [ป้าย, ไอคอน, สูงสุด, คำอธิบาย] */
const ROOM_LAYOUT_COUNTS = [
    'chairs'    => ['เก้าอี้', 'bi-person-workspace', 2000, 'ตัว'],
    'tables'    => ['โต๊ะ', 'bi-table', 80, 'ตัว'],
    'computers' => ['คอมพิวเตอร์พร้อมโต๊ะ', 'bi-pc-display', 80, 'ชุด · 0 = ไม่มี'],
    'speakers'  => ['เครื่องเสียง (ลำโพง)', 'bi-speaker', 12, 'ตัว · มีแร็กมิกเซอร์ให้อัตโนมัติ'],
    'mics'      => ['ไมโครโฟน', 'bi-mic', 100, 'ตัว · วางบนโต๊ะ/โพเดียม'],
];
const ROOM_WALLS     = ['front' => 'ผนังหน้า', 'right' => 'ผนังขวา', 'back' => 'ผนังหลัง', 'left' => 'ผนังซ้าย', 'floor' => 'พื้น', 'ceiling' => 'เพดาน', 'other' => 'อื่นๆ (ระบบจัดให้)'];

const ACTIVE_STATUSES = ['pending', 'head_approved', 'deputy_approved', 'signed', 'approved'];
/** สำหรับใช้ใน SQL: status IN ACTIVE_SQL */
const ACTIVE_SQL = "('pending','head_approved','deputy_approved','signed','approved')";

/** เมนูหลัก + สิทธิ์ (ใช้ทั้ง sidebar และการตรวจสิทธิ์หน้า) */
const MENU = [
    ['header' => 'ภาพรวม'],
    ['key' => 'dashboard', 'label' => 'แดชบอร์ด',            'icon' => 'bi-grid-1x2',        'url' => 'admin/index.php',    'roles' => '*'],
    ['header' => 'การจองห้อง'],
    ['key' => 'bookings',  'label' => 'รายการจอง / อนุมัติ',  'icon' => 'bi-calendar2-check', 'url' => 'admin/bookings.php', 'roles' => ['admin']],
    ['key' => 'sign',      'label' => 'ลงนามอนุมัติ',         'icon' => 'bi-pen',             'url' => 'admin/sign.php',     'roles' => ['building_head', 'deputy_director', 'director']],
    ['header' => 'ข้อมูลหลัก'],
    ['key' => 'users',     'label' => 'จัดการผู้ใช้',          'icon' => 'bi-people',          'url' => 'admin/users.php',    'roles' => ['admin']],
    ['key' => 'buildings', 'label' => 'จัดการอาคาร',          'icon' => 'bi-buildings',       'url' => 'admin/buildings.php','roles' => ['admin']],
    ['key' => 'rooms',     'label' => 'จัดการห้องประชุม',      'icon' => 'bi-door-open',       'url' => 'admin/rooms.php',    'roles' => ['admin']],
    ['header' => 'ระบบ'],
    ['key' => 'logs',      'label' => 'บันทึกการใช้งาน (Log)', 'icon' => 'bi-journal-text',    'url' => 'admin/logs.php',     'roles' => ['admin']],
    ['key' => 'backup',    'label' => 'สำรอง / กู้คืนข้อมูล',  'icon' => 'bi-database-gear',   'url' => 'admin/backup.php',   'roles' => ['admin']],
    ['key' => 'settings',  'label' => 'ตั้งค่าโลโก้',          'icon' => 'bi-image',           'url' => 'admin/settings.php', 'roles' => ['admin']],
    ['key' => 'profile',   'label' => 'โปรไฟล์ของฉัน',        'icon' => 'bi-person-circle',   'url' => 'admin/profile.php',  'roles' => '*'],
];

// ---------------------------------------------------------------- base
function detect_base_url(): string
{
    $doc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($doc) {
        $doc = rtrim(str_replace('\\', '/', $doc), '/');
        $app = str_replace('\\', '/', ROOT_PATH);
        if (stripos($app, $doc) === 0) {
            return rtrim(substr($app, strlen($doc)), '/');
        }
    }
    return '/booking_room';
}

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function asset(string $path): string
{
    $f = ROOT_PATH . '/assets/' . $path;
    return url('assets/' . $path) . '?v=' . (is_file($f) ? filemtime($f) : APP_VERSION);
}
function upload_url(?string $file): string { return $file ? url('uploads/' . $file) : ''; }
function redirect(string $path): void
{
    header('Location: ' . (preg_match('~^(https?://|/)~', $path) ? $path : url($path)));
    exit;
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function input(string $k, $default = '')
{
    if (!isset($_POST[$k])) return $default;
    return is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
}
function qs(string $k, $default = '')
{
    if (!isset($_GET[$k])) return $default;
    return is_string($_GET[$k]) ? trim($_GET[$k]) : $default;
}
function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}
function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------- db
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}
function db_one(string $sql, array $p = []): ?array { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r ?: null; }
function db_all(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function db_val(string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function db_exec(string $sql, array $p = []): int { $s = db()->prepare($sql); $s->execute($p); return $s->rowCount(); }

// ---------------------------------------------------------------- flash / csrf
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg]; }
function take_flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (is_ajax()) json_out(['ok' => false, 'message' => 'CSRF token ไม่ถูกต้อง'], 419);
        http_response_code(419);
        exit('CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
}

// ---------------------------------------------------------------- auth
function current_user(): ?array
{
    static $loaded = false, $u = null;
    if (!$loaded) {
        $loaded = true;
        if (!empty($_SESSION['uid'])) {
            $u = db_one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['uid']]);
            if (!$u) unset($_SESSION['uid']);
        }
    }
    return $u;
}
function require_login(): void
{
    if (current_user()) return;
    if (is_ajax()) json_out(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
    redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
}
function has_role($roles): bool
{
    $u = current_user();
    if (!$u) return false;
    if ($roles === '*') return true;
    return in_array($u['role'], (array)$roles, true);
}
function menu_item(string $key): ?array
{
    foreach (MENU as $m) if (($m['key'] ?? null) === $key) return $m;
    return null;
}
function page_guard(string $key): void
{
    require_login();
    $m = menu_item($key);
    if ($m && !has_role($m['roles'])) {
        http_response_code(403);
        if (is_ajax()) json_out(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        require ROOT_PATH . '/includes/403.php';
        exit;
    }
}

// ---------------------------------------------------------------- logging
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45); }
function log_action(string $action, string $detail = '', ?array $user = null): void
{
    try {
        $u = $user ?? current_user();
        db_exec(
            'INSERT INTO logs (user_id, username, action, detail, ip, user_agent) VALUES (?,?,?,?,?,?)',
            [$u['id'] ?? null, $u['username'] ?? 'guest', $action, $detail, client_ip(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
        );
    } catch (Throwable $e) { /* ไม่ให้ log ทำให้ระบบล้ม */ }
}
const LOG_ACTIONS = [
    'login' => ['เข้าสู่ระบบ', '#10b981', 'bi-box-arrow-in-right'],
    'login_failed' => ['เข้าสู่ระบบไม่สำเร็จ', '#ef4444', 'bi-shield-exclamation'],
    'logout' => ['ออกจากระบบ', '#64748b', 'bi-box-arrow-right'],
    'user' => ['ผู้ใช้', '#6366f1', 'bi-people'],
    'building' => ['อาคาร', '#0ea5e9', 'bi-buildings'],
    'room' => ['ห้องประชุม', '#14b8a6', 'bi-door-open'],
    'booking' => ['การจอง', '#f59e0b', 'bi-calendar2-check'],
    'sign' => ['ลงนามดิจิทัล', '#8b5cf6', 'bi-pen'],
    'approve' => ['อนุมัติ', '#ef4444', 'bi-check2-circle'],
    'backup' => ['สำรองข้อมูล', '#22c55e', 'bi-database-down'],
    'restore' => ['กู้คืนข้อมูล', '#e11d48', 'bi-database-up'],
    'profile' => ['โปรไฟล์', '#64748b', 'bi-person'],
    'settings' => ['ตั้งค่าระบบ', '#0f766e', 'bi-gear'],
];

// ---------------------------------------------------------------- display helpers
function badge(string $label, string $color, string $icon = ''): string
{
    return '<span class="badge-soft" style="--c:' . e($color) . '">' . ($icon ? '<i class="bi ' . e($icon) . '"></i>' : '') . e($label) . '</span>';
}
function role_badge(string $role): string { $r = ROLES[$role] ?? ROLES['user']; return badge($r['label'], $r['color'], $r['icon']); }
function booking_badge(string $s, bool $short = false): string
{
    $b = BOOKING_STATUS[$s] ?? BOOKING_STATUS['pending'];
    return badge($short ? $b['short'] : $b['label'], $b['color'], $b['icon']);
}
function room_badge(string $s): string { $r = ROOM_STATUS[$s] ?? ROOM_STATUS['available']; return badge($r['label'], $r['color'], $r['icon']); }
function full_name(?array $u): string
{
    if (!$u) return '-';
    return trim(($u['prefix'] ?? '') . ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}
function initials(?array $u): string
{
    $n = trim(($u['first_name'] ?? '') ?: ($u['username'] ?? '?'));
    return mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($u['last_name'] ?? '', 0, 1);
}
function avatar(?array $u, int $size = 36): string
{
    $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9', '#ef4444', '#14b8a6'];
    $c = $colors[crc32((string)($u['username'] ?? '')) % count($colors)];
    if (!empty($u['avatar'])) {
        $v = !empty($u['updated_at']) ? '?v=' . strtotime($u['updated_at']) : '';
        return '<img class="avatar avatar-img" src="' . e(upload_url($u['avatar']) . $v) . '" alt="' . e(full_name($u)) . '" style="--c:' . $c . ';width:' . $size . 'px;height:' . $size . 'px" loading="lazy">';
    }
    return '<span class="avatar" style="--c:' . $c . ';width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size * 0.38) . 'px">' . e(initials($u)) . '</span>';
}
const TH_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const TH_MONTHS_S = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const TH_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
function thai_date(?string $dt, string $fmt = 'date'): string
{
    if (!$dt) return '-';
    $ts = strtotime($dt);
    if (!$ts) return '-';
    $d = (int)date('j', $ts); $m = (int)date('n', $ts); $y = (int)date('Y', $ts) + 543;
    return match ($fmt) {
        'long'     => 'วัน' . TH_DAYS[(int)date('w', $ts)] . "ที่ $d " . TH_MONTHS[$m] . " $y",
        'month'    => TH_MONTHS[$m] . " $y",
        'time'     => date('H:i', $ts) . ' น.',
        'datetime' => "$d " . TH_MONTHS_S[$m] . " $y · " . date('H:i', $ts) . ' น.',
        default    => "$d " . TH_MONTHS_S[$m] . " $y",
    };
}
function time_range(string $s, string $e): string
{
    if (substr($s, 0, 10) === substr($e, 0, 10)) {
        return thai_date($s) . ' · ' . date('H:i', strtotime($s)) . '–' . date('H:i', strtotime($e)) . ' น.';
    }
    return thai_date($s, 'datetime') . ' – ' . thai_date($e, 'datetime');
}
/** จองต่อเนื่องได้สูงสุดกี่วัน (หน้าขอใช้ห้อง) */
const MAX_BOOKING_DAYS = 31;
/** วัน-เวลาการจองแบบ HTML 2 บรรทัด — วันเดียว: "วันพุธที่ 25 … <br>09:00–12:00 น." · หลายวัน: "25 … ถึง 27 … (3 วัน)<br>เริ่ม 09:00 น. · สิ้นสุด 16:00 น." */
function booking_when_html(string $s, string $e): string
{
    $sd = substr($s, 0, 10); $ed = substr($e, 0, 10);
    if ($sd === $ed) return e(thai_date($s, 'long')) . '<br>' . date('H:i', strtotime($s)) . '–' . date('H:i', strtotime($e)) . ' น.';
    $days = (int)round((strtotime($ed) - strtotime($sd)) / 86400) + 1;
    return e(thai_date($s)) . ' ถึง ' . e(thai_date($e)) . " <span class=\"text-muted\">($days วัน)</span><br>เริ่ม " . date('H:i', strtotime($s)) . ' น. · สิ้นสุด ' . date('H:i', strtotime($e)) . ' น.';
}
function time_ago(?string $dt): string
{
    if (!$dt) return '-';
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 86400 * 7) return floor($diff / 86400) . ' วันที่แล้ว';
    return thai_date($dt);
}
function human_size(int $b): string
{
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return round($b, $i ? 1 : 0) . ' ' . $u[$i];
}
function paginate(int $total, int $page, int $per): array
{
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per, 'per' => $per, 'total' => $total];
}
function pagination_html(array $p): string
{
    if ($p['pages'] <= 1) return '';
    $q = $_GET;
    $link = function ($n) use ($q) { $q['page'] = $n; return '?' . http_build_query($q); };
    $h = '<nav><ul class="pagination pagination-sm mb-0">';
    $h .= '<li class="page-item ' . ($p['page'] <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] - 1)) . '">&laquo;</a></li>';
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) {
        $h .= '<li class="page-item ' . ($i === $p['page'] ? 'active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    $h .= '<li class="page-item ' . ($p['page'] >= $p['pages'] ? 'disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] + 1)) . '">&raquo;</a></li>';
    return $h . '</ul></nav>';
}

// ---------------------------------------------------------------- bookings / rooms
function booking_conflicts(int $roomId, string $start, string $end, int $excludeId = 0, array $statuses = ACTIVE_STATUSES): array
{
    $in = implode(',', array_fill(0, count($statuses), '?'));
    return db_all(
        "SELECT * FROM bookings WHERE room_id = ? AND id <> ? AND status IN ($in) AND start_datetime < ? AND end_datetime > ? ORDER BY start_datetime",
        array_merge([$roomId, $excludeId], $statuses, [$end, $start])
    );
}
/** สถานะห้อง "วันนี้" = สถานะที่ admin กำหนด + การจองของวันนี้ */
function room_effective_status(string $manual, array $todayBookings): string
{
    if ($manual === 'unavailable') return 'unavailable';
    $st = $manual === 'booked' ? 'booked' : 'available';
    foreach ($todayBookings as $b) {
        if ($b['status'] === 'approved') return 'unavailable';
        if (in_array($b['status'], ACTIVE_STATUSES, true)) $st = 'booked';
    }
    return $st;
}
/** แยกรูปตามด้าน ถ้าด้านใดยังไม่มีรูป ระบบจะนำรูป "อื่นๆ" มาเติมให้ตามลำดับ */
/** ลักษณะอาคาร (JSON หรือ array) → ค่าที่ผ่านการตรวจสอบแล้ว */
function building_style(string|array|null $src): array
{
    $in = is_array($src) ? $src : (json_decode((string)$src, true) ?: []);
    $out = BUILDING_STYLE_DEFAULT;
    foreach (BUILDING_STYLE_OPTS as $k => [, , $opts]) if (isset($in[$k]) && isset($opts[$in[$k]])) $out[$k] = $in[$k];
    foreach (['roofColor', 'trim'] as $k) if (isset($in[$k]) && preg_match('/^#[0-9a-f]{6}$/i', (string)$in[$k])) $out[$k] = strtolower($in[$k]);
    if (array_key_exists('sign', $in)) $out['sign'] = filter_var($in['sign'], FILTER_VALIDATE_BOOLEAN);
    return $out;
}
/**
 * ลักษณะห้อง (JSON/array) → ค่าที่ตรวจสอบแล้ว
 * ถ้ายังไม่เคยกำหนด จะคาดเดาจากความจุและข้อความ "อุปกรณ์ภายในห้อง"
 */
function room_layout(string|array|null $src, int $capacity = 20, string $equipment = ''): array
{
    $in = is_array($src) ? $src : (json_decode((string)$src, true) ?: null);
    if ($in === null) { // ค่าเริ่มต้นจากข้อมูลเดิม
        $eq = mb_strtolower($equipment);
        $type = $capacity > 80 ? 'none' : ($capacity > 30 ? 'rows' : 'boardroom');
        $num = fn(string $re) => preg_match($re, $eq, $m) ? (int)$m[1] : null;
        $in = [
            'stage' => $capacity > 60 || str_contains($eq, 'เวที'),
            'tableType' => $type,
            'tables' => $type === 'none' ? 0 : (int)ceil($capacity / ROOM_TABLE_TYPES[$type][2]),
            'chairs' => $capacity,
            'computers' => $num('/คอมพิวเตอร์\D{0,12}(\d+)/u') ?? (str_contains($eq, 'คอมพิวเตอร์') ? min(20, $capacity) : 0),
            'speakers' => preg_match('/เสียง|ลำโพง/u', $eq) ? 2 : 0,
            'mics' => $num('/ไมค์\D{0,20}?(\d+)/u') ?? (str_contains($eq, 'ไมค์') ? 2 : 0),
            'display' => (preg_match('/โปรเจ/u', $eq) && preg_match('/led|tv|ทีวี|จอ\s*led/u', $eq)) ? 'both' : (preg_match('/led|tv|ทีวี/u', $eq) ? 'led' : 'projector'),
        ];
    }
    $out = ['stage' => filter_var($in['stage'] ?? false, FILTER_VALIDATE_BOOLEAN)];
    $out['tableType'] = isset(ROOM_TABLE_TYPES[$in['tableType'] ?? '']) ? $in['tableType'] : 'boardroom';
    foreach (ROOM_LAYOUT_COUNTS as $k => [, , $max]) $out[$k] = max(0, min($max, (int)($in[$k] ?? 0)));
    if ($out['tableType'] === 'none') $out['tables'] = 0;
    $out['display'] = isset(ROOM_DISPLAYS[$in['display'] ?? '']) ? $in['display'] : 'projector';
    return $out;
}
/** ค่าที่ส่งให้ viewer3d.js */
function building_3d_cfg(array $b, array $images = []): array
{
    return [
        'name' => $b['name'], 'floors' => (int)$b['floors'], 'width' => (float)$b['width_m'], 'depth' => (float)$b['depth_m'], 'color' => $b['color'],
        'style' => building_style($b['style'] ?? null),
        'images' => assign_faces($images, 'face', ['front', 'right', 'back', 'left']),
    ];
}
/** ค่าห้องประชุมสำหรับโมเดล 3D — ปั้นจากลักษณะห้องล้วน (ไม่นำรูปถ่ายมาเคลือบผนัง · รูปแสดงในแกลเลอรีเท่านั้น) */
function room_3d_cfg(array $r): array
{
    return [
        'width' => (float)$r['width_m'], 'depth' => (float)$r['depth_m'], 'height' => (float)$r['height_m'], 'color' => $r['color'],
        'layout' => room_layout($r['layout'] ?? null, (int)$r['capacity'], (string)($r['equipment'] ?? '')),
        'images' => [],
    ];
}
function assign_faces(array $images, string $col, array $order): array
{
    $map = []; $rest = [];
    foreach ($images as $img) {
        $f = $img[$col];
        if ($f !== 'other' && !isset($map[$f])) $map[$f] = upload_url($img['file']);
        else $rest[] = $img;
    }
    foreach ($order as $f) {
        if (!isset($map[$f]) && $rest) $map[$f] = upload_url(array_shift($rest)['file']);
    }
    return $map;
}
// ---------------------------------------------------------------- ลายเซ็นดิจิทัลแบบลูกโซ่ (หลายขั้นตอน)
/** ขั้นที่ผู้ใช้บทบาทนี้ต้องลงนาม */
function role_sign_step(?string $role): ?string
{
    foreach (SIGN_STEPS as $k => $s) if ($s['role'] && $s['role'] === $role) return $k;
    return null;
}
// ---------------------------------------------------------------- อุปกรณ์ที่ขอให้จัด (แบบฟอร์มขอใช้สถานที่ ข้อ 2)
/** key => [ป้าย, ชนิด (qty = จำนวน · text = ระบุข้อความ · check = ติ๊กอย่างเดียว), หน่วย/คำนำหน้าช่อง, ไอคอน] */
const BOOKING_EQUIPMENT = [
    'reg_table'     => ['โต๊ะลงทะเบียน',   'qty',   'ตัว', 'bi-clipboard-check'],
    'sofa'          => ['ชุดรับแขก',        'check', '',    'bi-cup-hot'],
    'plastic_chair' => ['เก้าอี้พลาสติก',   'qty',   'ตัว', 'bi-person-workspace'],
    'altar'         => ['โต๊ะหมู่บูชา',     'check', '',    'bi-flower1'],
    'podium'        => ['โพเดียม',          'qty',   'ตัว', 'bi-easel3'],
    'flowers'       => ['ดอกไม้',           'qty',   'ชุด', 'bi-flower3'],
    'stage'         => ['เวที',             'text',  'ขนาด', 'bi-aspect-ratio'],
    'sound'         => ['เครื่องขยายเสียง', 'text',  'รายละเอียด', 'bi-speaker'],
    'other'         => ['อื่นๆ',            'text',  'ระบุ', 'bi-three-dots'],
];
/** ทำความสะอาดค่าอุปกรณ์จากฟอร์ม/ฐานข้อมูล → ['need' => bool, 'items' => [key => จำนวน|ข้อความ|true]] */
function booking_equipment(string|array|null $src): array
{
    $in = is_array($src) ? $src : (json_decode((string)$src, true) ?: []);
    $items = [];
    $need = filter_var($in['need'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($need) {
        foreach (BOOKING_EQUIPMENT as $k => [, $type]) {
            $on = filter_var($in['on'][$k] ?? (isset($in['items'][$k]) ? true : false), FILTER_VALIDATE_BOOLEAN);
            if (!$on) continue;
            $v = $in['val'][$k] ?? $in['items'][$k] ?? '';
            $items[$k] = match ($type) {
                'qty' => max(0, min(9999, (int)$v)),
                'text' => mb_substr(trim((string)$v), 0, 150),
                default => true,
            };
        }
    }
    return ['need' => $need && $items, 'items' => $items];
}
/** แปลงเป็นรายการข้อความภาษาไทย เช่น ['โต๊ะลงทะเบียน 2 ตัว', 'เวที ขนาด 4×6 ม.'] */
function booking_equipment_lines(string|array|null $src): array
{
    $eq = booking_equipment($src);
    $out = [];
    foreach ($eq['items'] as $k => $v) {
        [$label, $type, $unit] = BOOKING_EQUIPMENT[$k];
        $out[] = match ($type) {
            'qty' => $label . ($v ? " $v $unit" : ''),
            'text' => $v === '' ? $label : match ($k) { 'stage' => "เวที ขนาด $v", 'other' => "อื่นๆ: $v", default => "$label ($v)" },
            default => $label,
        };
    }
    return $out;
}
/** ช่องกรอกอุปกรณ์ (ใช้ทั้งหน้าขอใช้ห้องและหน้า admin) — $old = ค่าที่ส่งมา (กรณีฟอร์มผิดพลาด) */
function booking_equipment_fields(array $old = [], string $idp = 'eq'): string
{
    $need = filter_var($old['need'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $h = '<div class="eq-box" data-eq>';
    $h .= '<div class="eq-need">'
        . '<label class="eq-radio"><input type="radio" name="equip[need]" value="0" ' . ($need ? '' : 'checked') . '><span><i class="bi bi-x-circle"></i>ไม่ต้องการ</span></label>'
        . '<label class="eq-radio"><input type="radio" name="equip[need]" value="1" ' . ($need ? 'checked' : '') . '><span><i class="bi bi-check2-circle"></i>ต้องการให้จัด</span></label></div>';
    $h .= '<div class="eq-grid"' . ($need ? '' : ' hidden') . '>';
    foreach (BOOKING_EQUIPMENT as $k => [$label, $type, $unit, $icon]) {
        $on = !empty($old['on'][$k]);
        $val = (string)($old['val'][$k] ?? '');
        $id = $idp . '_' . $k;
        $h .= '<div class="eq-item' . ($type === 'text' ? ' is-wide' : '') . '"><label class="eq-check" for="' . $id . '">'
            . '<input type="checkbox" id="' . $id . '" name="equip[on][' . $k . ']" value="1" ' . ($on ? 'checked' : '') . '>'
            . '<i class="bi ' . $icon . '"></i><span>' . e($label) . '</span></label>';
        if ($type === 'qty') $h .= '<div class="eq-in"><input type="number" class="form-control form-control-sm" name="equip[val][' . $k . ']" min="1" max="9999" value="' . e($val) . '" placeholder="จำนวน" ' . ($on ? '' : 'disabled') . '><span>' . e($unit) . '</span></div>';
        elseif ($type === 'text') $h .= '<div class="eq-in"><input type="text" class="form-control form-control-sm" name="equip[val][' . $k . ']" maxlength="150" value="' . e($val) . '" placeholder="' . e($unit) . ($k === 'stage' ? ' เช่น 4 × 6 ม.' : '') . '" ' . ($on ? '' : 'disabled') . '></div>';
        $h .= '</div>';
    }
    return $h . '</div></div>';
}
/** ข้อมูลที่ลงนาม = ข้อมูลหลักของการจอง + ผู้ลงนาม + ภาพลายเซ็น + hash ของขั้นก่อนหน้า (ห่วงโซ่ — แก้ขั้นใดขั้นหนึ่ง ขั้นถัดไปจะไม่ผ่านทั้งหมด) */
function signature_hash(array $b, array $s, string $png, string $prevHash): string
{
    $data = implode('|', [
        $b['id'], $b['booking_no'], $b['room_id'], $b['start_datetime'], $b['end_datetime'], $b['requester_name'], $b['title'], $b['attendees'],
        $s['step'], $s['user_id'] ?? '', $s['signer_name'], $s['decision'], $s['note'] ?? '', $s['signed_at'], hash('sha256', $png), $prevHash,
    ]);
    // อุปกรณ์ที่ขอให้จัดเป็นส่วนหนึ่งของข้อมูลที่ลงนาม (ต่อท้ายเฉพาะเมื่อมี → ลายเซ็นของคำขอเดิมยังตรวจผ่าน)
    $eq = booking_equipment($b['equip'] ?? null);
    if ($eq['need']) $data .= '|' . json_encode($eq['items'], JSON_UNESCAPED_UNICODE);
    return hash_hmac('sha256', $data, APP_SECRET);
}
/** ตรวจสอบภาพลายเซ็น PNG จาก data URL → คืนค่าไบต์ของไฟล์ */
function signature_png_from_dataurl(string $dataUrl): string
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) throw new RuntimeException('กรุณาลงลายมือชื่อ');
    $png = base64_decode($m[1], true);
    if ($png === false || strlen($png) > 1048576) throw new RuntimeException('ไฟล์ลายเซ็นไม่ถูกต้อง');
    $info = @getimagesizefromstring($png);
    if (!$info || $info[2] !== IMAGETYPE_PNG) throw new RuntimeException('ไฟล์ลายเซ็นไม่ถูกต้อง');
    return $png;
}
function booking_signatures(int $bookingId): array
{
    return db_all('SELECT * FROM booking_signatures WHERE booking_id=? ORDER BY id', [$bookingId]);
}
/**
 * บันทึกลายเซ็นของขั้นตอน $step ต่อท้ายห่วงโซ่ (ควรเรียกภายใน transaction)
 * $signer = ['user_id' => ?, 'name' => ..., 'position' => ...]
 */
function signature_add(array $b, string $step, array $signer, string $decision, ?string $note, string $png, ?string $at = null): array
{
    $last = db_one('SELECT hash FROM booking_signatures WHERE booking_id=? ORDER BY id DESC LIMIT 1', [$b['id']]);
    $dir = UPLOAD_PATH . '/signatures';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $file = 'signatures/sig_' . $b['id'] . '_' . $step . '_' . bin2hex(random_bytes(6)) . '.png';
    file_put_contents(UPLOAD_PATH . '/' . $file, $png);
    $s = [
        'booking_id' => $b['id'], 'step' => $step, 'user_id' => $signer['user_id'] ?? null,
        'signer_name' => mb_substr((string)$signer['name'], 0, 150), 'signer_position' => mb_substr((string)($signer['position'] ?? ''), 0, 150),
        'decision' => $decision, 'note' => $note ?: null, 'file' => $file, 'signed_at' => $at ?? date('Y-m-d H:i:s'),
    ];
    $s['hash'] = signature_hash($b, $s, $png, $last['hash'] ?? '');
    db_exec('INSERT INTO booking_signatures (booking_id,step,user_id,signer_name,signer_position,decision,note,file,hash,signed_at,ip) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$s['booking_id'], $s['step'], $s['user_id'], $s['signer_name'], $s['signer_position'], $s['decision'], $s['note'], $s['file'], $s['hash'], $s['signed_at'], client_ip()]);
    return $s;
}
/**
 * ตรวจสอบห่วงโซ่ลายเซ็นทั้งหมดของการจอง
 * คืนค่า ['steps' => [step => แถวลายเซ็น + valid], 'valid' => ทุกขั้นถูกต้องและเรียงตามลำดับ, 'complete' => ลงนามอนุมัติครบ 4 ขั้น]
 */
function signature_chain(array $b, ?array $sigs = null): array
{
    $sigs ??= booking_signatures((int)$b['id']);
    $order = array_keys(SIGN_STEPS);
    $prev = ''; $valid = true; $steps = [];
    foreach (array_values($sigs) as $i => $s) {
        $f = UPLOAD_PATH . '/' . $s['file'];
        $ok = ($order[$i] ?? null) === $s['step'] && is_file($f)
            && hash_equals($s['hash'], signature_hash($b, $s, file_get_contents($f), $prev));
        $s['valid'] = $ok;
        $valid = $valid && $ok;
        $steps[$s['step']] = $s;
        $prev = $s['hash'];
    }
    $complete = $valid && count($steps) === count(SIGN_STEPS) && !array_filter($steps, fn($x) => $x['decision'] === 'reject');
    return ['steps' => $steps, 'valid' => $valid && (bool)$steps, 'complete' => $complete];
}
/** ข้อมูลผู้ลงนามจากบัญชีผู้ใช้ */
function signer_of(array $u): array
{
    return ['user_id' => (int)$u['id'], 'name' => full_name($u), 'position' => $u['position'] ?: (ROLES[$u['role']]['label'] ?? '')];
}

// ---------------------------------------------------------------- uploads
/** บันทึกรูปที่อัปโหลด (หลายไฟล์) → ย่อขนาด + แปลงเป็น JPG; คืนค่า [ไฟล์ที่บันทึก(index=>path), errors] */
function save_uploaded_images(?array $files, string $subdir, int $maxSide = 2048): array
{
    $saved = []; $errors = [];
    if (!$files || !isset($files['name']) || !is_array($files['name'])) return [$saved, $errors];
    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    @ini_set('memory_limit', '512M');
    foreach ($files['name'] as $i => $name) {
        $err = $files['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) { $errors[] = "$name: อัปโหลดไม่สำเร็จ (code $err)"; continue; }
        if ($files['size'][$i] > MAX_UPLOAD_MB * 1048576) { $errors[] = "$name: ไฟล์ใหญ่เกิน " . MAX_UPLOAD_MB . 'MB'; continue; }
        $tmp = $files['tmp_name'][$i];
        $info = @getimagesize($tmp);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            $errors[] = "$name: รองรับเฉพาะไฟล์ JPG, PNG, WEBP, GIF"; continue;
        }
        $file = bin2hex(random_bytes(12)) . '.jpg';
        if (!image_to_jpeg($tmp, $info, "$dir/$file", $maxSide)) { $errors[] = "$name: ประมวลผลรูปไม่สำเร็จ"; continue; }
        $saved[$i] = "$subdir/$file";
    }
    return [$saved, $errors];
}
function image_to_jpeg(string $src, array $info, string $dest, int $maxSide): bool
{
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        default => false,
    };
    if (!$img) return false;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $o = $exif['Orientation'] ?? 1;
        if ($o == 3) $img = imagerotate($img, 180, 0);
        elseif ($o == 6) $img = imagerotate($img, -90, 0);
        elseif ($o == 8) $img = imagerotate($img, 90, 0);
    }
    $w = imagesx($img); $h = imagesy($img);
    $scale = min(1, $maxSide / max($w, $h));
    $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($out, $dest, 86);
    imagedestroy($img); imagedestroy($out);
    return $ok;
}
/** บันทึกรูปโปรไฟล์: ตัดเป็นสี่เหลี่ยมจัตุรัสกึ่งกลาง ย่อเป็น $side px แล้วบันทึกเป็น JPG → คืน path ใน uploads */
function save_avatar(?array $f, int $side = 400): string
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('กรุณาเลือกรูปภาพ');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดไม่สำเร็จ (code ' . (int)$f['error'] . ')');
    if ($f['size'] > 5 * 1048576) throw new RuntimeException('ไฟล์ใหญ่เกิน 5MB');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) throw new RuntimeException('รองรับเฉพาะไฟล์ JPG, PNG, WEBP, GIF');
    @ini_set('memory_limit', '512M');
    $dir = UPLOAD_PATH . '/avatars';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $tmp = "$dir/tmp_" . bin2hex(random_bytes(6)) . '.jpg';
    if (!image_to_jpeg($f['tmp_name'], $info, $tmp, 1200)) throw new RuntimeException('ประมวลผลรูปไม่สำเร็จ');
    $img = imagecreatefromjpeg($tmp); @unlink($tmp);
    $w = imagesx($img); $h = imagesy($img); $s = min($w, $h);
    $out = imagecreatetruecolor($side, $side);
    imagecopyresampled($out, $img, 0, 0, (int)(($w - $s) / 2), (int)(($h - $s) / 2), $side, $side, $s, $s);
    $file = 'avatars/' . bin2hex(random_bytes(12)) . '.jpg';
    $ok = imagejpeg($out, UPLOAD_PATH . '/' . $file, 88);
    imagedestroy($img); imagedestroy($out);
    if (!$ok) throw new RuntimeException('บันทึกรูปไม่สำเร็จ');
    return $file;
}
// ---------------------------------------------------------------- โลโก้หน่วยงาน
const LOGO_FILE = 'branding/logo.png';
/** มีโลโก้ที่อัปโหลดเองหรือไม่ */
function has_custom_logo(): bool { return is_file(UPLOAD_PATH . '/' . LOGO_FILE); }
/** URL โลโก้ (โลโก้วิทยาลัยที่อัปโหลด หรือโลโก้เริ่มต้นของระบบ) */
function logo_url(): string
{
    $p = UPLOAD_PATH . '/' . LOGO_FILE;
    return is_file($p) ? upload_url(LOGO_FILE) . '?v=' . filemtime($p) : asset('img/logo.svg');
}
/** <link rel=icon> ตามโลโก้ปัจจุบัน */
function favicon_tag(): string
{
    return '<link rel="icon" href="' . e(logo_url()) . '" type="' . (has_custom_logo() ? 'image/png' : 'image/svg+xml') . '">';
}
/** บันทึกโลโก้: ย่อด้านยาวสุด $max px เก็บเป็น PNG (คงพื้นหลังโปร่งใส) */
function save_logo(?array $f, int $max = 512): void
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('กรุณาเลือกไฟล์โลโก้');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('อัปโหลดไม่สำเร็จ (code ' . (int)$f['error'] . ')');
    if ($f['size'] > 5 * 1048576) throw new RuntimeException('ไฟล์ใหญ่เกิน 5MB');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) throw new RuntimeException('รองรับเฉพาะไฟล์ PNG, JPG, WEBP, GIF (แนะนำ PNG พื้นหลังโปร่งใส)');
    @ini_set('memory_limit', '512M');
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($f['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($f['tmp_name']),
    };
    if (!$img) throw new RuntimeException('อ่านไฟล์รูปไม่ได้');
    $w = imagesx($img); $h = imagesy($img);
    $sc = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false); imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $dir = dirname(UPLOAD_PATH . '/' . LOGO_FILE);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $ok = imagepng($out, UPLOAD_PATH . '/' . LOGO_FILE, 6);
    imagedestroy($img); imagedestroy($out);
    if (!$ok) throw new RuntimeException('บันทึกโลโก้ไม่สำเร็จ');
}
function delete_upload(?string $file): void
{
    if (!$file) return;
    $p = realpath(UPLOAD_PATH . '/' . $file);
    $base = realpath(UPLOAD_PATH);
    if ($p && $base && str_starts_with($p, $base) && is_file($p)) @unlink($p);
}

// ---------------------------------------------------------------- public (ผู้ขอใช้ห้องที่ไม่ใช่สมาชิก)
/** เก็บเฉพาะตัวเลข และแปลง +66 → 0 */
function phone_digits(?string $p): string
{
    $d = preg_replace('/\D+/', '', (string)$p);
    if (str_starts_with($d, '66') && strlen($d) === 11) $d = '0' . substr($d, 2);
    return $d;
}
function phone_valid(string $digits): bool { return (bool)preg_match('/^0\d{8,9}$/', $digits); }
function phone_format(string $d): string
{
    if (strlen($d) === 10) return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
    if (strlen($d) === 9) return str_starts_with($d, '02')
        ? substr($d, 0, 2) . '-' . substr($d, 2, 3) . '-' . substr($d, 5)   // กรุงเทพฯ 02-xxx-xxxx
        : substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);  // ต่างจังหวัด 054-xxx-xxx
    return $d;
}
/** จำกัดจำนวนครั้ง (ต่อ session) — คืน false ถ้าเกิน */
function rate_hit(string $key, int $max, int $windowSec): bool
{
    $now = time();
    $hits = array_values(array_filter($_SESSION['_rate'][$key] ?? [], fn($t) => $t > $now - $windowSec));
    if (count($hits) >= $max) { $_SESSION['_rate'][$key] = $hits; return false; }
    $hits[] = $now;
    $_SESSION['_rate'][$key] = $hits;
    return true;
}
