<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$daftarSewa = BookingModel::getPenyewaanAktif();

$judulHalaman = 'Penyewaan Aktif';
$halamanAktif = 'penyewaan';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Penyewaan Aktif</h1>
<p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">Pemantauan seluruh sewa yang sedang berjalan.</p>

<?php if (empty($daftarSewa)): ?>
    <div class="card empty-state">
        <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
        <p class="empty-state-title">Tidak ada penyewaan aktif</p>
        <p class="empty-state-text">Sewa yang sedang berjalan akan muncul di sini.</p>
    </div>
<?php else: ?>

<div class="table-wrapper">
    <table class="table" style="min-width: 640px;">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Barang</th>
                <th>Tanggal Kembali Terdekat</th>
                <th>Sisa Bayar</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daftarSewa as $b):
                $sisa = max(0, (float) $b['total_sewa'] - (float) $b['total_dibayar']);
                $terlambat = strtotime($b['tanggal_kembali_terdekat']) < strtotime(date('Y-m-d')) && $b['status'] === 'SEDANG_DISEWA';
                $sudahKembali = (int) $b['jumlah_total_item'] - (int) $b['jumlah_belum_kembali'];
            ?>
                <tr class="<?= $terlambat ? 'row-danger' : '' ?>">
                    <td style="font-weight: 600;"><?= htmlspecialchars($b['kode_booking']) ?></td>
                    <td><?= htmlspecialchars($b['nama_member'] ?? $b['guest_nama']) ?></td>
                    <td>
                        <span class="badge <?= $sudahKembali > 0 ? 'badge-accent' : 'badge-neutral' ?>"><?= $sudahKembali ?>/<?= (int) $b['jumlah_total_item'] ?> kembali</span>
                    </td>
                    <td style="color: <?= $terlambat ? 'var(--color-danger)' : 'var(--color-text)' ?>;">
                        <?= date('d/m/Y', strtotime($b['tanggal_kembali_terdekat'])) ?>
                        <?= $terlambat ? ' (terlambat)' : '' ?>
                    </td>
                    <td style="font-weight: 600; color: <?= $sisa > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;"><?= format_rupiah($sisa) ?></td>
                    <td>
                        <span class="badge badge-accent"><?= label_status_booking($b['status']) ?></span>
                    </td>
                    <td>
                        <a href="detail-penyewaan.php?id=<?= $b['id'] ?>" style="color: var(--color-accent); font-weight: 600;">Kelola</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>