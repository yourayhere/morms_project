<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/ItemModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$daftarBarang = ItemModel::getSemuaUntukAdmin();
$csrfToken = generate_csrf_token();

$pesanSukses = [
    'hapus' => 'Barang berhasil dihapus permanen dari inventaris.',
][$_GET['sukses'] ?? ''] ?? null;

$pesanError = [
    'token'           => 'Token keamanan tidak valid. Silakan coba lagi.',
    'tidak_ditemukan' => 'Barang tidak ditemukan.',
][$_GET['error'] ?? ''] ?? null;

$judulHalaman = 'Inventaris';
$halamanAktif = 'inventaris';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Inventaris</h1>
        <p style="color: var(--color-text-muted); font-size: 13px;">Kelola data barang yang disewakan.</p>
    </div>
    <a href="form-barang.php" class="btn btn-primary">
        <svg class="icon" style="width: 17px; height: 17px;"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
        Tambah Barang
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

<?php if (empty($daftarBarang)): ?>
    <div class="card empty-state">
        <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
        <p class="empty-state-title">Belum ada barang</p>
        <p class="empty-state-text">Tambahkan barang pertama Anda untuk mulai menyewakan.</p>
    </div>
<?php else: ?>
<div class="table-wrapper">
    <table class="table" style="min-width: 640px;">
        <thead>
            <tr>
                <th>Barang</th>
                <th>Kategori</th>
                <th>Harga/Malam</th>
                <th>Stok</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daftarBarang as $barang): ?>
                <tr>
                    <td style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 6px; background-color: var(--color-bg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                            <?php if ($barang['foto_utama'] && file_exists(__DIR__ . '/' . $barang['foto_utama'])): ?>
                                <img src="<?= htmlspecialchars($barang['foto_utama']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <svg class="icon" style="width: 16px; height: 16px; color: var(--color-primary-light);"><use href="assets/icons/sprite.svg#icon-image"></use></svg>
                            <?php endif; ?>
                        </div>
                        <span style="font-weight: 600;"><?= htmlspecialchars($barang['nama']) ?></span>
                    </td>
                    <td style="color: var(--color-text-muted);"><?= label_kategori($barang['kategori']) ?></td>
                    <td style="font-weight: 600;"><?= format_rupiah((float) $barang['harga_per_malam']) ?></td>
                    <td><?= $barang['stok_total'] ?> unit</td>
                    <td>
                        <?php
                        $labelStatus = [
                            'aktif' => 'Aktif',
                            'maintenance' => 'Maintenance',
                            'nonaktif' => 'Nonaktif',
                        ][$barang['status']] ?? $barang['status'];
                        $warnaStatus = [
                            'aktif' => 'var(--color-success)',
                            'maintenance' => 'var(--color-accent-dark)',
                            'nonaktif' => 'var(--color-text-muted)',
                        ][$barang['status']] ?? 'var(--color-text-muted)';
                        ?>
                        <form method="POST" action="aksi/toggle-status-barang.php">
                            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                            <input type="hidden" name="item_id" value="<?= $barang['id'] ?>">
                            <button type="submit" title="<?= $barang['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; color: <?= $warnaStatus ?>; padding: 0;">
                                <svg class="icon" style="width: 24px; height: 24px;"><use href="assets/icons/sprite.svg#<?= $barang['status'] === 'aktif' ? 'icon-toggle-on' : 'icon-toggle-off' ?>"></use></svg>
                                <?= htmlspecialchars($labelStatus) ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <a href="form-barang.php?id=<?= $barang['id'] ?>" style="color: var(--color-accent); font-weight: 600; font-size: 12.5px;">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>