<?php
/**
 * ระบบจองห้องประชุม วิทยาลัยเทคนิคน่าน — การตั้งค่าหลัก
 * คัดลอกไฟล์นี้เป็น config.php แล้วตั้ง APP_SECRET เป็นค่าสุ่ม (เช่น bin2hex(random_bytes(24)))
 */

// ===== ฐานข้อมูล =====
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'booking_room');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== แอปพลิเคชัน =====
define('APP_NAME', 'ระบบจองห้องประชุม');
define('ORG_NAME', 'วิทยาลัยเทคนิคน่าน');
define('APP_VERSION', '1.0.0');
// ใช้สร้าง HMAC ของลายเซ็นดิจิทัล — ห้ามเปลี่ยนหลังใช้งานจริง (ลายเซ็นเดิมจะตรวจสอบไม่ผ่าน)
define('APP_SECRET', 'CHANGE_ME_random_48_hex_chars');

// ===== พาธ =====
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('MAX_UPLOAD_MB', 10);

date_default_timezone_set('Asia/Bangkok');
