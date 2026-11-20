# CLAUDE.md

ระบบจองห้องประชุม วิทยาลัยเทคนิคน่าน: PHP 8.2 + MySQL 8, ไม่มี framework/Composer/build step. รันบน XAMPP (Windows) ที่ `http://localhost/booking_room`. UI และข้อความทั้งหมดเป็นภาษาไทย ให้ตอบผู้ใช้เป็นภาษาไทย

## Setup / environment

- Config: copy `config/config.example.php` → `config/config.php` (gitignored, holds `APP_SECRET`). **Never change `APP_SECRET` on a live system**: it HMACs the digital signatures, so old signatures would fail verification.
- First install: open `install/index.php`. It creates the schema from `install/schema.php`, seeds the demo data, and writes `install/install.lock`.
- DB: root with no password, accessed via PDO. The `mysql` CLI fails (caching_sha2 auth), so do DB changes with PHP: `/c/xampp/php/php.exe` and `require 'includes/bootstrap.php'`.
- MySQL has no `ADD COLUMN IF NOT EXISTS`. Check `information_schema` first, and update both `install/schema.php` and the live DB.
- Demo logins: `admin` / `admin1234`. `bhead`, `deputy`, `director`, `rstaff1` / `password`.
- Lint: `/c/xampp/php/php.exe -l file.php`. There is no test suite. Verify pages with curl or headless Chrome (CDP via node).
- Thai text POSTed through Git Bash curl gets mangled. Use ASCII values in curl tests.

## Layout

- `includes/bootstrap.php`: session + config + `functions.php`. Every page `require`s it first.
- `includes/functions.php`: all helpers and domain constants.
  - helpers: `db_*`, `e`, `url`, `asset`, `csrf_*`, `flash`, `page_guard`, `log_action`, `avatar`, upload/logo helpers
  - domain constants: `ROLES`, `BOOKING_STATUS`, `SIGN_STEPS`, `ROOM_STATUS`, `MENU`, building/room style options
- `includes/header.php` / `footer.php`: admin shell. Pages set `$pageTitle`, `$activeMenu`, `$extraScripts` before including. `public_layout.php` is the shell for the public pages.
- `admin/*.php`: back office, one file per screen. Each POSTs to itself (PRG pattern: `csrf_check()` → act → `flash()` → `redirect()`).
- Public pages: root `index.php` (calendar), `request.php` (book by phone), `status.php`. `api/*.php` returns JSON for the calendar, slots and bookings.
- `assets/js/viewer3d.js`: all Three.js code (ES module, three@0.160 from jsDelivr through an importmap that each page includes).
  - Interactive viewers: `createBuildingViewer`, `createRoomViewer`.
  - Shared-renderer still images: `renderBuildingSnapshot`, `renderRoomSnapshot`.
  - `buildStructure` generates a building from its `style`; `buildRoomFurniture` generates a room from its `layout`.
- `assets/css/app.css`: single stylesheet (Bootstrap 5 + custom, light and dark via `data-bs-theme`).
- `uploads/`, `backups/`: runtime data, gitignored. `.htaccess` blocks web access to `backups/` and `config/`.

## Domain rules

- Roles: admin, director, deputy_director, building_head, building_staff, room_staff, user. Menu and page access come from `MENU` + `page_guard('<key>')`.
- Bookings go through a 4-step signature chain (`SIGN_STEPS`): requester → building_head → deputy_director → director.
  - Each signature hashes the previous one (`signature_hash`).
  - Statuses: pending → head_approved → deputy_approved → signed/approved. `ACTIVE_SQL` = statuses that occupy a room.
- 3D models are generated from data, not photos. Photos are optional textures.
  - `buildings.style` (JSON) is normalised by `building_style()`, and the config is built by `building_3d_cfg()`.
  - `rooms.layout` (JSON) holds stage, table type and count, chairs ≤ 2000, computers, speakers, mics and display. It is normalised by `room_layout()`.
  - Photos map to faces with `assign_faces()`.
- A room with bookings cannot be deleted (guard in `admin/rooms.php`). Use status "ไม่ว่าง" instead, or clear its bookings after a `db_backup()`.
- Log every admin action with `log_action(type, message)`. Types are listed in `LOG_ACTIONS`.

## Conventions

- Escape all output with `e()`. Build every link with `url()` / `asset()` / `upload_url()`, never a hard-coded path.
- Use parameterised queries only (`db_one/db_all/db_val/db_exec` with `?`).
- Constants are validated server-side. Invalid POST values fall back to defaults; they never error.
- When adding CSS for a shared class name (e.g. `.chip`), scope it (`.chip-group .chip`) so other pages don't break.
- Use the Edit/Write tools for file changes. Long Bash heredocs or inline `php -r` patches with Thai text and nested quotes are fragile.
