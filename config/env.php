<?php

// Zona waktu aplikasi dipatok ke WIB (Asia/Jakarta), tidak bergantung pada
// timezone default server hosting yang mungkin berbeda (mis. default PHP
// yang belum pernah diset eksplisit bisa jatuh ke zona lain sama sekali,
// yang membuat semua perhitungan date()/time()/DateTime() tanpa timezone
// eksplisit meleset dari jam Indonesia sebenarnya).
date_default_timezone_set('Asia/Jakarta');

// Deteksi environment otomatis: diakses lewat localhost/127.0.0.1 (XAMPP lokal)
// dianggap development, domain apa pun selain itu dianggap production. Bisa
// dipaksa lewat environment variable APP_ENV kalau perlu (mis. staging server).
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'cli'));
$host = explode(':', $host)[0];

$hostLokal = in_array($host, ['localhost', '127.0.0.1', '::1', 'cli'], true);
$appEnv = getenv('APP_ENV') ?: ($hostLokal ? 'development' : 'production');

define('APP_ENV', $appEnv);
define('APP_DEBUG', APP_ENV !== 'production');

if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
