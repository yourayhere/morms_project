<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/RateLimiter.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/logger.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Auth;
use App\Core\RateLimiter;

Session::start();

if (Auth::isLoggedIn() && in_array(Auth::role(), ['owner', 'admin', 'kasir'], true)) {
    header('Location: dashboard-admin.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (RateLimiter::isLocked($email)) {
            $sisaMenit = ceil(RateLimiter::remainingLockoutSeconds($email) / 60);
            $errors[] = 'Terlalu banyak percobaan gagal. Silakan coba lagi dalam ' . $sisaMenit . ' menit.';
        } elseif ($email === '' || $password === '') {
            $errors[] = 'Email dan kata sandi wajib diisi.';
        } else {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                'SELECT id, nama, email, password_hash, role, status_aktif FROM users WHERE email = :email AND role IN ("owner", "admin", "kasir")'
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            // Lihat catatan di login.php: menyamakan waktu respons supaya email
            // yang tidak terdaftar tidak bisa dibedakan dari password yang salah.
            $hashDummy = '$2y$12$38BQQDvu1u5pj1TXfex2yOXIHqHzAG4yM3m.xrJY1iEsOLSWvhp9.';
            $passwordValid = password_verify($password, $user['password_hash'] ?? $hashDummy);

            if (!$user || !$passwordValid) {
                RateLimiter::recordFailedAttempt($email);
                $errors[] = 'Email atau kata sandi tidak sesuai.';
            } elseif ($user['status_aktif'] !== 'aktif') {
                $errors[] = 'Akun tidak aktif. Silakan hubungi Owner.';
            } else {
                RateLimiter::reset($email);
                Auth::login($user);
                catat_aktivitas($user['id'], 'login_admin', 'Login berhasil sebagai ' . $user['role']);
                header('Location: dashboard-admin.php');
                exit;
            }
        }
    }
}

$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Admin / Merimba Outdoor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
</head>
<body>

<div class="auth-split">

    <div class="auth-split-promo auth-split-promo--admin">
        <svg class="auth-split-promo-illustration" viewBox="0 0 400 110" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M-10 98 L50 45 L90 75 L140 30 L190 70 L230 42 L280 80 L330 38 L410 95" />
            <path d="M95 98 L118 68 L128 82 L138 68 L155 98" />
            <path d="M118 68 L118 98" />
        </svg>
        <p class="auth-split-brand">Merimba Outdoor <span class="auth-split-brand-tag">Panel Admin</span></p>

        <h1 class="auth-split-headline auth-split-headline--desktop">Kelola operasional<br>penyewaan dengan<br><em>lebih efisien.</em></h1>
        <h1 class="auth-split-headline auth-split-headline--mobile">Kelola operasional<br>penyewaan dengan<br><em>lebih efisien.</em></h1>

        <div class="auth-split-features">
            <div class="auth-split-feature">
                <span class="auth-split-feature-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-package"></use></svg>
                </span>
                <div>
                    <p class="auth-split-feature-title">Kontrol Inventaris</p>
                    <p class="auth-split-feature-desc">Pantau stok dan status barang secara real-time.</p>
                </div>
            </div>
            <div class="auth-split-feature">
                <span class="auth-split-feature-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-report"></use></svg>
                </span>
                <div>
                    <p class="auth-split-feature-title">Laporan Lengkap</p>
                    <p class="auth-split-feature-desc">Rekap pendapatan dan transaksi dalam satu tempat.</p>
                </div>
            </div>
            <div class="auth-split-feature">
                <span class="auth-split-feature-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                </span>
                <div>
                    <p class="auth-split-feature-title">Akses Terkendali</p>
                    <p class="auth-split-feature-desc">Khusus untuk Owner, Admin, dan Kasir terverifikasi.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-split-form-side">
        <div class="auth-split-form-wrap">

            <h2 class="auth-split-title">Masuk ke Panel Admin</h2>
            <p class="auth-split-subtitle">Khusus Owner, Admin, dan Kasir Merimba Outdoor.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?= clean_input($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">

                <div class="form-group">
                    <label for="email" class="form-label">Alamat Email</label>
                    <div class="auth-split-input-group">
                        <span class="auth-split-input-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 6l10 7 10-7"/>
                            </svg>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="auth-split-input"
                            placeholder="Masukkan email Anda"
                            value="<?= clean_input($_POST['email'] ?? '') ?>"
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Kata Sandi</label>
                    <div class="auth-split-input-group">
                        <span class="auth-split-input-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="auth-split-input"
                            style="padding-right: 44px;"
                            placeholder="Masukkan kata sandi"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="auth-split-toggle-pw" id="btn-toggle-password" aria-label="Tampilkan kata sandi">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-split-btn" style="margin-top: 8px;">
                    Masuk
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <a href="login.php" class="auth-split-back" style="margin-top: 26px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Kembali ke halaman pelanggan
            </a>

        </div>
    </div>

</div>

<script>
var btnTogglePassword = document.getElementById('btn-toggle-password');
var inputPassword = document.getElementById('password');
btnTogglePassword.addEventListener('click', function () {
    var tampil = inputPassword.type === 'password';
    inputPassword.type = tampil ? 'text' : 'password';
    btnTogglePassword.innerHTML = tampil
        ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>';
    btnTogglePassword.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});
</script>

</body>
</html>
