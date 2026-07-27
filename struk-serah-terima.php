<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TransactionModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$bookingId = (int) ($_GET['booking_id'] ?? 0);
$booking = BookingModel::getDetailLengkap($bookingId);

if (!$booking || !in_array($booking['status'], ['SEDANG_DISEWA', 'PENGEMBALIAN', 'SELESAI'], true)) {
    header('Location: reservasi-online.php');
    exit;
}

$itemBooking = BookingModel::getItemBooking($bookingId);
$totalSewa = array_sum(array_column($itemBooking, 'subtotal'));

$totalDibayar = 0;
foreach (TransactionModel::getByBookingId($bookingId) as $trx) {
    if ($trx['status_verifikasi'] !== 'ditolak') {
        $totalDibayar += (float) $trx['nominal'];
    }
}
$sisaPembayaran = max(0, $totalSewa - $totalDibayar);

$namaPenyewa = $booking['nama_member'] ?? $booking['guest_nama'];
$hpPenyewa = $booking['hp_member'] ?? $booking['guest_hp'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Serah Terima <?= htmlspecialchars($booking['kode_booking']) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; max-width: 500px; margin: 0 auto; color: #2B2724; }
        .judul { font-size: 18px; font-weight: bold; color: #2E4452; text-align: center; }
        .sub { text-align: center; font-size: 12px; color: #6B6560; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #E5E0D8; }
        .total-row td { font-weight: bold; }
        .btn-print { display: block; margin: 24px auto 0; padding: 10px 24px; background-color: #C0623A; color: white; border: none; border-radius: 6px; cursor: pointer; }
        @media print { .aksi-cetak { display: none !important; } }
    </style>
</head>
<body>

    <p class="judul">MERIMBA OUTDOOR</p>
    <p class="sub">Struk Serah Terima Barang</p>

    <p style="font-size: 13px;"><strong>Kode Booking:</strong> <?= htmlspecialchars($booking['kode_booking']) ?></p>
    <p style="font-size: 13px;"><strong>Penyewa:</strong> <?= htmlspecialchars($namaPenyewa) ?> (<?= htmlspecialchars($hpPenyewa) ?>)</p>
    <p style="font-size: 13px;"><strong>Periode Sewa:</strong> <?= format_periode_sewa($booking['tanggal_ambil'], $booking['tanggal_kembali'], $booking['jam_ambil'], $booking['jam_kembali']) ?></p>

    <table>
        <thead>
            <tr><th>Barang</th><th>Jumlah</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            <?php foreach ($itemBooking as $barang): ?>
                <tr>
                    <td><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?></td>
                    <td><?= (int) $barang['jumlah'] ?></td>
                    <td style="text-align: right;"><?= format_rupiah($barang['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2">Total Sewa</td>
                <td style="text-align: right;"><?= format_rupiah($totalSewa) ?></td>
            </tr>
            <tr>
                <td colspan="2">Sudah Dibayar</td>
                <td style="text-align: right;"><?= format_rupiah($totalDibayar) ?></td>
            </tr>
            <tr>
                <td colspan="2">Sisa Pembayaran</td>
                <td style="text-align: right;"><?= format_rupiah($sisaPembayaran) ?></td>
            </tr>
        </tbody>
    </table>

    <p style="font-size: 12px; color: #6B6560; margin-top: 16px;">
        Barang di atas diserahkan kepada penyewa dalam kondisi baik dan lengkap sesuai daftar. Penyewa wajib mengembalikan barang dalam kondisi yang sama pada tanggal yang telah disepakati. Kerusakan, kehilangan, atau keterlambatan pengembalian dapat dikenakan biaya tambahan.
    </p>

    <div class="aksi-cetak" style="text-align: center; margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
        <button class="btn-print" onclick="window.print()">Cetak</button>
        <a href="detail-reservasi.php?id=<?= $bookingId ?>" style="display: inline-block; padding: 10px 20px; background-color: #F0E9DE; color: #2C2018; border-radius: 6px; font-size: 13px; font-weight: 600;">Kembali ke Detail</a>
        <a href="dashboard-admin.php" style="display: inline-block; padding: 10px 20px; background-color: #F0E9DE; color: #2C2018; border-radius: 6px; font-size: 13px; font-weight: 600;">Ke Dashboard</a>
    </div>

</body>
</html>
