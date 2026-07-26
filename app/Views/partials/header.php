<?php
use App\Core\Auth;
$halamanAktif = $halamanAktif ?? '';
require_once __DIR__ . '/../../Models/SettingModel.php';
require_once __DIR__ . '/../../Helpers/toko.php';
$logoPath = \App\Models\SettingModel::get('logo_file');
$logoScale = (float) (\App\Models\SettingModel::get('logo_scale') ?: 1);
$logoPosX = (int) (\App\Models\SettingModel::get('logo_pos_x') ?: 0);
$logoPosY = (int) (\App\Models\SettingModel::get('logo_pos_y') ?: 0);
$tokoTutup = toko_sedang_tutup();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($judulHalaman) ? htmlspecialchars($judulHalaman) . ' / Merimba Outdoor' : 'Merimba Outdoor' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
</head>
<body>

<?php if ($tokoTutup): ?>
    <div class="toko-tutup-banner">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
        <span><?= htmlspecialchars(pesan_toko_tutup()) ?></span>
    </div>
<?php endif; ?>

<header class="site-header">
    <div class="container">
        <div class="site-header-inner">

            <a href="index.php" class="site-logo">
                <?php if ($logoPath && file_exists(__DIR__ . '/../../../' . $logoPath)): ?>
                    <span style="display: inline-flex; width: 120px; height: 36px; overflow: hidden; align-items: center; justify-content: center;">
                        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo"
                            style="max-width: 100%; max-height: 100%; object-fit: contain; transform: scale(<?= $logoScale ?>) translate(<?= $logoPosX ?>px, <?= $logoPosY ?>px);">
                    </span>
                <?php else: ?>
                    MERIMBA <span class="site-logo-accent">OUTDOOR</span>
                <?php endif; ?>
            </a>

            <nav class="nav-desktop">
                <a href="index.php" class="nav-link <?= $halamanAktif === 'home' ? 'active' : '' ?>">Beranda</a>
                <a href="katalog.php" class="nav-link <?= $halamanAktif === 'katalog' ? 'active' : '' ?>">Katalog</a>
                <a href="tracking.php" class="nav-link <?= $halamanAktif === 'tracking' ? 'active' : '' ?>">Cek Booking</a>
                <a href="keranjang.php" class="nav-link <?= $halamanAktif === 'keranjang' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 6px;">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-cart"></use></svg>
                    Keranjang
                </a>
                <?php if (Auth::isLoggedIn() && Auth::role() === 'member'): ?>
                    <a href="dashboard.php" class="nav-link nav-icon-link <?= $halamanAktif === 'akun' ? 'active' : '' ?>" aria-label="Akun Saya" title="Akun Saya" style="display: flex; align-items: center;">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-account"></use></svg>
                    </a>
                    <a href="logout.php" class="nav-link nav-icon-link text-muted" aria-label="Keluar" title="Keluar" style="display: flex; align-items: center;" onclick="return confirm('Yakin ingin keluar dari akun ini?');">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-logout"></use></svg>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm">Masuk</a>
                <?php endif; ?>
            </nav>

            <?php if (Auth::isLoggedIn() && Auth::role() === 'member' && $halamanAktif === 'akun'): ?>
                <a href="logout.php" class="mobile-logout-btn" aria-label="Keluar" title="Keluar" onclick="return confirm('Yakin ingin keluar dari akun ini?');">
                    <svg class="icon" style="width: 20px; height: 20px;"><use href="assets/icons/sprite.svg#icon-logout"></use></svg>
                </a>
            <?php endif; ?>

        </div>
    </div>
</header>

<main>