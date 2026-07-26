<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$filterStatus = clean_input($_GET['status'] ?? '');
$daftarBooking = BookingModel::getDaftarOnline($filterStatus);

$daftarFilter = [
    '' => 'Semua Status',
    'DRAFT' => 'Draf Reservasi',
    'MENUNGGU_PEMBAYARAN' => 'Menunggu Pembayaran',
    'MENUNGGU_VERIFIKASI' => 'Menunggu Verifikasi',
    'MENUNGGU_KEDATANGAN' => 'Menunggu Kedatangan',
    'RESERVASI_DIKONFIRMASI' => 'Dikonfirmasi',
    'BARANG_DISIAPKAN' => 'Barang Disiapkan',
    'SIAP_DIAMBIL' => 'Siap Diambil',
    'EXPIRED' => 'Kedaluwarsa',
    'DIBATALKAN' => 'Dibatalkan',
];

$judulHalaman = 'Reservasi Online';
$halamanAktif = 'reservasi';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Reservasi Online</h1>
        <p style="color: var(--color-text-muted); font-size: 13px;">Kelola pesanan yang masuk melalui website.</p>
    </div>

    <form method="GET">
        <select name="status" onchange="this.form.submit()" style="padding: 9px 14px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; background-color: #FFFFFF;">
            <?php foreach ($daftarFilter as $value => $label): ?>
                <option value="<?= $value ?>" <?= $filterStatus === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (empty($daftarBooking)): ?>
    <div class="card empty-state">
        <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
        <p class="empty-state-title">Tidak ada reservasi</p>
        <p class="empty-state-text">Tidak ada reservasi untuk status yang dipilih.</p>
    </div>
<?php else: ?>

<div class="table-wrapper">
    <table class="table" style="min-width: 680px;">
        <thead>
            <tr>
                <th>Kode Booking</th>
                <th>Pemesan</th>
                <th>Periode</th>
                <th>Total</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daftarBooking as $b): ?>
                <tr>
                    <td style="font-weight: 600;"><?= htmlspecialchars($b['kode_booking']) ?></td>
                    <td><?= htmlspecialchars($b['nama_member'] ?? $b['guest_nama']) ?></td>
                    <td style="color: var(--color-text-muted);"><?= date('d/m', strtotime($b['tanggal_ambil'])) ?> hingga <?= date('d/m/Y', strtotime($b['tanggal_kembali'])) ?></td>
                    <td style="font-weight: 600;"><?= format_rupiah((float) $b['total_sewa']) ?></td>
                    <td>
                        <span class="badge badge-accent"><?= label_status_booking($b['status']) ?></span>
                    </td>
                    <td>
                        <a href="detail-reservasi.php?id=<?= $b['id'] ?>" style="color: var(--color-accent); font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                            <svg class="icon" style="width: 15px; height: 15px;"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                            Detail
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>