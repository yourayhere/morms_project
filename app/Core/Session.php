<?php

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

            session_set_cookie_params([
                'lifetime' => 7200,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();

            if (empty($_SESSION['_token_time'])) {
                $_SESSION['_token_time'] = time();
            }

            if (time() - $_SESSION['_token_time'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['_token_time'] = time();
            }
        }
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key)
    {
        return $_SESSION[$key] ?? null;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Hapus juga cookie sesi di browser, bukan cuma data sesi di
            // server — kalau tidak, cookie sesi yang sudah tidak valid tetap
            // tersimpan di browser (kosmetik/tidak berbahaya, tapi cookie
            // seharusnya benar-benar bersih setelah logout).
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }
}