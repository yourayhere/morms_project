<?php

namespace App\Core;

class Auth
{
    public static function login(array $user): void
    {
        Session::set('user_id', $user['id']);
        Session::set('user_nama', $user['nama']);
        Session::set('user_role', $user['role']);
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function isLoggedIn(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function requireLogin(string $redirectTo = '/morms/login.php'): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireRole(array $allowedRoles, string $redirectTo = '/morms/workspace-merimba.php'): void
    {
        if (!self::isLoggedIn() || !in_array(self::role(), $allowedRoles, true)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * Cek ulang status_aktif member ke database (bukan cuma percaya session).
     * Mencegah member yang baru saja diblokir admin tetap bisa memakai fitur
     * member (booking, dashboard, dll) selama sesi lognnya masih berjalan.
     */
    public static function requireActiveMember(string $redirectTo = '/morms/login.php'): void
    {
        if (!self::isLoggedIn() || self::role() !== 'member') {
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT status_aktif FROM users WHERE id = :id');
        $stmt->execute(['id' => self::id()]);
        $status = $stmt->fetchColumn();

        if ($status !== 'aktif') {
            self::logout();
            header('Location: ' . $redirectTo . '?error=diblokir');
            exit;
        }
    }
}