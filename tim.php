<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/logger.php';
require_once __DIR__ . '/app/Models/TeamModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\TeamModel;

Session::start();
Auth::requireRole(['owner']);

$daftarAnggota = TeamModel::getSemuaAnggota();
$csrfToken = generate_csrf_token();

$labelRole = [
    'owner' => 'Owner',
    'admin' => 'Admin',
];

$labelStatus = [
    'aktif'    => 'Aktif',
    'cuti'     => 'Cuti',
    'nonaktif' => 'Nonaktif',
];

$pesanSukses = [
    'hapus' => 'Akun tim berhasil dihapus.',
][$_GET['sukses'] ?? ''] ?? null;

$pesanError = [
    'token'          => 'Token keamanan tidak valid. Silakan coba lagi.',
    'tidak_ditemukan' => 'Akun tim tidak ditemukan.',
][$_GET['error'] ?? ''] ?? null;

$judulHalaman = 'Manajemen Tim';
$halamanAktif = 'tim';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<div class="admin-page-header-row">
    <div>
        <h1 class="admin-page-title">Manajemen Tim</h1>
        <p class="admin-page-subtitle">Kelola anggota tim dan hak akses sistem.</p>
    </div>
    <a href="form-anggota.php" class="btn btn-primary btn-sm">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
        Tambah Anggota
    </a>
</div>

<?php if ($pesanSukses): ?>
    <div class="alert alert-success" style="margin-bottom: 16px;">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
        <?= htmlspecialchars($pesanSukses) ?>
    </div>
<?php endif; ?>

<?php if ($pesanError): ?>
    <div class="alert alert-danger" style="margin-bottom: 16px;">
        <?= htmlspecialchars($pesanError) ?>
    </div>
<?php endif; ?>

<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>No. HP</th>
                <th>Peran</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daftarAnggota as $anggota): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--forest-600) 0%, var(--forest-800) 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg class="icon icon-xs icon-white"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                            </div>
                            <span style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($anggota['nama']) ?><?php if ($anggota['id'] === Auth::id()): ?> <span class="text-muted text-xs" style="font-weight: 400;">(Anda)</span><?php endif; ?></span>
                        </div>
                    </td>
                    <td class="text-muted text-sm"><?= htmlspecialchars($anggota['email'] ?? '-') ?></td>
                    <td class="text-sm"><?= htmlspecialchars($anggota['no_hp'] ?? '-') ?></td>
                    <td>
                        <?php
                        $badgeRole = [
                            'owner' => 'badge-accent',
                            'admin' => 'badge-forest',
                        ];
                        ?>
                        <span class="badge <?= $badgeRole[$anggota['role']] ?? 'badge-neutral' ?>">
                            <?php if ($anggota['role'] === 'owner'): ?>
                                <svg class="icon icon-xs"><use href="assets/icons/sprite.svg#icon-crown"></use></svg>
                            <?php endif; ?>
                            <?= $labelRole[$anggota['role']] ?? $anggota['role'] ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $badgeStatus = [
                            'aktif'    => 'badge-success',
                            'cuti'     => 'badge-warning',
                            'nonaktif' => 'badge-neutral',
                        ];
                        ?>
                        <span class="badge <?= $badgeStatus[$anggota['status_aktif']] ?? 'badge-neutral' ?>">
                            <?= $labelStatus[$anggota['status_aktif']] ?? $anggota['status_aktif'] ?>
                        </span>
                    </td>
                    <td>
                        <a href="form-anggota.php?id=<?= $anggota['id'] ?>" style="color: var(--terra-500); font-weight: 600; font-size: 13px;">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>