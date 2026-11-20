<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (!defined('BASE_URL')) define('BASE_URL', detect_base_url());

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('NTC_BOOKING');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_URL ?: '') . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// ยังไม่ได้ติดตั้งฐานข้อมูล → ไปหน้าติดตั้ง
try {
    db();
} catch (PDOException $e) {
    if (PHP_SAPI !== 'cli' && in_array((int)$e->getCode(), [1049], true)) redirect('install/index.php');
    http_response_code(500);
    exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . e($e->getMessage()));
}
