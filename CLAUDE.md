# CLAUDE.md

ระบบจองห้องประชุม วิทยาลัยเทคนิคน่าน: PHP 8.2 + MySQL 8, ไม่มี framework/Composer/build step. รันบน XAMPP (Windows) ที่ `http://localhost/booking_room`. UI และข้อความทั้งหมดเป็นภาษาไทย ให้ตอบผู้ใช้เป็นภาษาไทย

## Setup / environment

- Config: copy `config/config.example.php` → `config/config.php` (gitignored, holds `APP_SECRET`). **Never change `APP_SECRET` on a live system**: it HMACs the digital signatures, so old signatures would fail verification.
- First install: open `install/index.php`. It creates the schema from `install/schema.php`, seeds the demo data, and writes `install/install.lock`.
- DB: root with no password, accessed via PDO. The `mysql` CLI fails (caching_sha2 auth), so do DB changes with PHP: `/c/xampp/php/php.exe` and `require 'includes/bootstrap.php'`.
- MySQL has no `ADD COLUMN IF NOT EXISTS`. Check `information_schema` first, and update both `install/schema.php` and the live DB.
- Demo logins: `admin` / `admin1234`. Every other seeded account uses `password`: `bhead` (building_head), `deputy`, `director`, `bstaff` (building_staff), `rstaff1` / `rstaff2` (room_staff).
- Lint: `/c/xampp/php/php.exe -l file.php` (and `node --check` for JS). There is no test suite. Verify pages with curl or headless Chrome (CDP via node).
  - The admin pages use smooth scrolling. In CDP tests, call `scrollIntoView({behavior:'instant'})` before clicking by coordinates.
- Thai text POSTed through Git Bash curl gets mangled. Use ASCII values in curl tests.
- The live DB holds real bookings. Give test bookings a recognisable `requester_name`, delete them afterwards (signature files too), and never touch other rows.
- `request.php` logs each rejected submission and its reason to `C:\xampp\apache\logs\error.log` (`[request.php] rejected: …`). Check there when a user says "ข้อมูลไม่เข้า".

## Layout

- `includes/bootstrap.php`: session + config + `functions.php`. Every page `require`s it first.
- `includes/functions.php`: all helpers and domain constants.
  - helpers: `db_*`, `e`, `url`, `asset`, `csrf_*`, `flash`, `page_guard`, `log_action`, `avatar`, upload/logo helpers
  - domain constants: `ROLES`, `BOOKING_STATUS`, `SIGN_STEPS`, `ROOM_STATUS`, `MENU`, building/room style options
- `includes/header.php` / `footer.php`: admin shell. Pages set `$pageTitle`, `$activeMenu`, `$extraScripts` before including. `public_layout.php` is the shell for the public pages.
- `admin/*.php`: back office, one file per screen. Each POSTs to itself (PRG pattern: `csrf_check()` → act → `flash()` → `redirect()`).
- Public pages: root `index.php` (calendar), `request.php` (book by phone), `status.php`. `api/*.php` returns JSON for the calendar, slots and bookings.
  - `request.php` behaviour:
    - Room picker: 3D icons plus a details modal (`assets/js/request3d.js`).
    - Dates: a start date to end date range.
    - Phone: digit-block input.
    - A rejected submission shows a modal plus an inline alert (`$_SESSION['_req_error']`).
    - Honeypot field: `hp_trap_x`. Don't name it something Chrome autofills.
  - `api/public_slots.php?date_end=&start=&end=` returns `range` conflicts for multi-day requests.
- Role-specific screens:
  - room_staff: `admin/tasks.php` (menu "แจ้งเตือน"). It lists bookings assigned through `booking_staff`.
    - "รับทราบ" sets `ack_at`; "จัดเตรียมเรียบร้อย" sets `done_at`.
    - The badge comes from `staff_task_count()`.
  - building_staff and admin (menu group "งานอาคาร"):
    - `admin/usage.php`: daily room timeline.
    - `admin/print_forms.php` → `admin/print_form.php?id[]=`: a standalone A4 page mimicking the college's paper form "แบบฟอร์มการขอใช้สถานที่". Approved bookings only.
    - `admin/report.php`: monthly per-room summary, with `?export=csv` (UTF-8 BOM).
- Shared front-end helpers:
  - `assets/js/app.js` (admin and public): equipment checkbox toggles (`[data-eq]`) and the phone block input (`[data-phone-blocks]`).
  - `assets/js/thaidate.js` + flatpickr 4.6.13 (CDN): any `input[type=date][data-thai-date]` shows dd/mm/พ.ศ. but still submits `YYYY-MM-DD`.
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
- 3D models are generated from data, not photos.
  - `buildings.style` (JSON) is normalised by `building_style()`, and the config is built by `building_3d_cfg()`. Building photos are still mapped onto faces with `assign_faces()`.
  - `rooms.layout` (JSON) holds stage, table type and count, chairs ≤ 2000, computers, speakers, mics and display. It is normalised by `room_layout()`.
  - Room models never use photos: `room_3d_cfg()` always returns `images: []`. Room photos are gallery-only, resized client-side by `assets/js/media.js` (1920px JPG) and again server-side.
- Bookings: `title` is labelled "มีความประสงค์ขอใช้สถานที่เพื่อ". `bookings.equip` (JSON) holds the requested equipment from `BOOKING_EQUIPMENT`, normalised by `booking_equipment()`. It is part of the signed data (appended to the HMAC input only when present).
- Multi-day bookings are one row, from start date + start time to end date + end time.
  - They are signed once, and conflict checks cover the whole span.
  - The maximum length is `MAX_BOOKING_DAYS`.
  - Display with `booking_when_html()` / `time_range()`.
  - For usage statistics use `booking_days()` / `booking_hours()`: daily hours × days, never the raw span.
- Staff assignment (`admin/bookings.php`) upserts `booking_staff` and only removes people taken off the list. This keeps existing `ack_at` / `done_at`.
- "จดจำการเข้าสู่ระบบ" (auto-login, `REMEMBER_DAYS` = 30):
  - The `NTC_REMEMBER` cookie holds `selector:validator`. `remember_tokens` stores only `sha256(validator)`.
  - `current_user()` calls `remember_autologin()` when the session has no user.
  - Logout calls `remember_clear()`.
  - A password change or account suspension must call `remember_revoke_user()`.
- Phone numbers: `phone_digits()` to store and compare, `phone_format()` to display (mobile `0xx-xxx-xxxx`, Bangkok `02-xxx-xxxx`, other landlines `0xx-xxx-xxx`).
- A room with bookings cannot be deleted (guard in `admin/rooms.php`). Use status "ไม่ว่าง" instead, or clear its bookings after a `db_backup()`.
- Log every admin action with `log_action(type, message)`. Types are listed in `LOG_ACTIONS`.

## Conventions

- Escape all output with `e()`. Build every link with `url()` / `asset()` / `upload_url()`, never a hard-coded path.
- Use parameterised queries only (`db_one/db_all/db_val/db_exec` with `?`).
- Constants are validated server-side. Invalid POST values fall back to defaults; they never error.
- When adding CSS for a shared class name (e.g. `.chip`), scope it (`.chip-group .chip`) so other pages don't break.
- Use the Edit/Write tools for file changes. Long Bash heredocs or inline `php -r` patches with Thai text and nested quotes are fragile.
