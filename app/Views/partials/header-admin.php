<?php
use App\Core\Session;
use App\Core\Auth;
use App\Models\NotificationModel;

$halamanAktif = $halamanAktif ?? '';
$jumlahNotifikasi = Auth::id() ? NotificationModel::getJumlahBelumDibaca((int) Auth::id()) : 0;

$menuSidebar = [
    [
        'group' => 'Utama',
        'items' => [
            ['key' => 'dashboard',    'label' => 'Dashboard',        'icon' => 'icon-grid',    'href' => 'dashboard-admin.php',  'role' => ['owner', 'admin', 'kasir']],
            ['key' => 'reservasi',    'label' => 'Reservasi Online',  'icon' => 'icon-box',     'href' => 'reservasi-online.php', 'role' => ['owner', 'admin']],
            ['key' => 'kasir',        'label' => 'Kasir',             'icon' => 'icon-pos',     'href' => 'kasir.php',            'role' => ['owner', 'admin', 'kasir']],
            ['key' => 'penyewaan',    'label' => 'Penyewaan Aktif',   'icon' => 'icon-package', 'href' => 'penyewaan-aktif.php',  'role' => ['owner', 'admin']],
            ['key' => 'pengembalian', 'label' => 'Pengembalian',      'icon' => 'icon-return',  'href' => 'pengembalian.php',     'role' => ['owner', 'admin', 'kasir']],
        ],
    ],
    [
        'group' => 'Data',
        'items' => [
            ['key' => 'inventaris', 'label' => 'Inventaris', 'icon' => 'icon-catalog', 'href' => 'inventaris.php', 'role' => ['owner', 'admin']],
            ['key' => 'pelanggan',  'label' => 'Pelanggan',  'icon' => 'icon-people',  'href' => 'pelanggan.php',  'role' => ['owner', 'admin']],
        ],
    ],
    [
        'group' => 'Sistem',
        'items' => [
            ['key' => 'tim',        'label' => 'Tim',        'icon' => 'icon-user',     'href' => 'tim.php',        'role' => ['owner']],
            ['key' => 'laporan',    'label' => 'Laporan',    'icon' => 'icon-report',   'href' => 'laporan.php',    'role' => ['owner']],
            ['key' => 'pengaturan', 'label' => 'Pengaturan', 'icon' => 'icon-settings', 'href' => 'pengaturan.php', 'role' => ['owner']],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($judulHalaman) ? htmlspecialchars($judulHalaman) . ' / Admin Merimba' : 'Admin Merimba Outdoor' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
</head>
<body>

<div class="admin-layout">

    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <p class="admin-sidebar-brand-name">MERIMBA OUTDOOR</p>
            <p class="admin-sidebar-brand-tag">Panel Admin</p>
        </div>

        <nav class="admin-nav">
            <?php foreach ($menuSidebar as $grup):
                $adaMenuTerlihat = false;
                foreach ($grup['items'] as $menu) {
                    if (in_array(Auth::role(), $menu['role'], true)) {
                        $adaMenuTerlihat = true;
                        break;
                    }
                }
                if (!$adaMenuTerlihat) continue;
            ?>
                <p class="admin-nav-group"><?= $grup['group'] ?></p>
                <?php foreach ($grup['items'] as $menu): ?>
                    <?php if (in_array(Auth::role(), $menu['role'], true)): ?>
                        <a href="<?= $menu['href'] ?>" class="admin-nav-link <?= $halamanAktif === $menu['key'] ? 'active' : '' ?>">
                            <svg class="icon"><use href="assets/icons/sprite.svg#<?= $menu['icon'] ?>"></use></svg>
                            <?= $menu['label'] ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-bottom">
            <svg class="admin-sidebar-illustration" viewBox="0 0 180 90" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 82 L46 34 L70 60 L100 20 L146 82 Z" />
                <path d="M100 82 L124 50 L154 82" />
                <path d="M46 34 L54 34 L58 26 L62 34 L46 34" />
            </svg>
            <a href="logout.php" class="admin-nav-link" onclick="return confirm('Yakin ingin keluar dari akun ini?');">
                <svg class="icon"><use href="assets/icons/sprite.svg#icon-logout"></use></svg>
                Keluar
            </a>
        </div>
    </aside>

    <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop"></div>

    <div class="admin-content">

        <header class="admin-topbar">

            <button type="button" class="sidebar-toggle-btn" id="btn-sidebar-toggle" aria-label="Buka/tutup menu">
                <svg class="icon icon-md"><use href="assets/icons/sprite.svg#icon-menu"></use></svg>
            </button>

            <div class="notif-wrapper">
                <button type="button" class="notif-trigger" id="btn-notifikasi">
                    <svg class="icon icon-md"><use href="assets/icons/sprite.svg#icon-bell"></use></svg>
                    <?php if ($jumlahNotifikasi > 0): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </button>
                <div class="notif-panel" id="panel-notifikasi">
                    <p class="notif-panel-title">Notifikasi</p>
                    <div id="daftar-notifikasi">
                        <p class="notif-item text-muted">Memuat...</p>
                    </div>
                </div>
            </div>

            <a href="form-anggota.php?id=<?= (int) Auth::id() ?>" class="topbar-user" aria-label="Profil Saya" title="Profil Saya">
                <div class="topbar-avatar">
                    <svg class="icon icon-sm icon-white"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                </div>
                <div>
                    <p class="topbar-name"><?= htmlspecialchars(Session::get('user_nama') ?? '') ?></p>
                    <p class="topbar-role"><?= htmlspecialchars(Auth::role() ?? '') ?></p>
                </div>
            </a>

        </header>

        <main class="admin-main">