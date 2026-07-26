<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';

use App\Core\Session;
use App\Core\Auth;

Session::start();

if (Auth::isLoggedIn() && Auth::role() === 'member') {
    header('Location: dashboard.php');
    exit;
}

$judulHalaman = 'Akun Saya';
$halamanAktif = 'akun';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 40px 0 56px;">
    <div class="container" style="max-width: 420px; text-align: center;">

        <div class="guest-account-badge">
            <span class="guest-account-badge-inner">
                <svg class="icon icon-white" style="width: 26px; height: 26px;"><use href="assets/icons/sprite.svg#icon-account"></use></svg>
            </span>
            <span class="guest-account-badge-dot"></span>
        </div>

        <h1 style="font-size: 20px; color: var(--color-primary-dark); margin-bottom: 8px;">Anda Belum Masuk</h1>
        <p style="color: var(--color-text-muted); font-size: 13px; line-height: 1.6; margin-bottom: 24px;">Buat akun supaya riwayat sewa Anda tersimpan dan booking berikutnya lebih cepat.</p>

        <div class="card guest-account-perks">
            <p class="guest-account-perks-title">
                <svg class="icon icon-accent" style="width: 14px; height: 14px;"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                Keuntungan memiliki akun
            </p>
            <div class="guest-account-perk">
                <span class="guest-account-perk-icon"><svg class="icon icon-accent" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-history"></use></svg></span>
                <span class="guest-account-perk-label">Riwayat sewa tersimpan</span>
            </div>
            <div class="guest-account-perk">
                <span class="guest-account-perk-icon"><svg class="icon icon-accent" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-extend"></use></svg></span>
                <span class="guest-account-perk-label">Booking lebih cepat</span>
            </div>
            <div class="guest-account-perk">
                <span class="guest-account-perk-icon"><svg class="icon icon-accent" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg></span>
                <span class="guest-account-perk-label">Status reservasi mudah dipantau</span>
            </div>
        </div>

        <a href="register.php" class="btn btn-primary btn-block" style="justify-content: center;">
            <svg class="icon icon-white" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
            Buat Akun
        </a>

        <div class="guest-account-divider">atau</div>

        <a href="login.php" class="btn btn-secondary btn-block" style="justify-content: center; margin-bottom: 16px;">
            <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-account"></use></svg>
            Masuk
            <svg class="icon" style="width: 14px; height: 14px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
        </a>

        <div class="card" style="padding: 14px 16px; display: flex; align-items: flex-start; gap: 10px; text-align: left; background-color: var(--terra-50); border-color: var(--terra-300);">
            <svg class="icon icon-accent" style="width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px;"><use href="assets/icons/sprite.svg#icon-calendar"></use></svg>
            <p style="font-size: 12px; color: var(--color-text); line-height: 1.6;">
                Booking tanpa akun? Gunakan menu Tracking untuk cek status reservasi Anda.
                <a href="tracking.php" class="guest-account-tracking-link">
                    Buka Tracking
                    <svg class="icon"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                </a>
            </p>
        </div>

    </div>
</section>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>
