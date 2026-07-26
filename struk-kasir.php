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
$booking = BookingModel::getById($bookingId);

if (!$booking) {
    header('Location: kasir.php');
    exit;
}

$itemBooking = BookingModel::getItemBooking($bookingId);
$invoice = TransactionModel::getInvoiceUtama($bookingId);
$totalSewa = array_sum(array_column($itemBooking, 'subtotal'));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk <?= htmlspecialchars($booking['kode_booking']) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; max-width: 500px; margin: 0 auto; color: #2B2724; }
        .judul { font-size: 18px; font-weight: bold; color: #2E4452; text-align: center; }
        .sub { text-align: center; font-size: 12px; color: #6B6560; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #E5E0D8; }
        .total-row td { font-weight: bold; }
        .btn-print { display: block; margin: 24px auto 0; padding: 10px 24px; background-color: #C0623A; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .btn-kembali { display: block; text-align: center; margin-top: 14px; font-size: 13px; color: #6B6560; }
        @media print { .aksi-cetak { display: none !important; } }
    </style>
</head>
<body>

    <p class="judul">MERIMBA OUTDOOR</p>
    <p class="sub">Jl. Sorowajan Baru, Tegal Tanda, Banguntapan, Bantul, DIY</p>

    <p style="font-size: 13px;"><strong>Kode Booking:</strong> <?= htmlspecialchars($booking['kode_booking']) ?></p>
    <p style="font-size: 13px;"><strong>Invoice:</strong> <?= htmlspecialchars($invoice['invoice_no'] ?? '-') ?></p>
    <p style="font-size: 13px;"><strong>Penyewa:</strong> <?= htmlspecialchars($booking['guest_nama']) ?> (<?= htmlspecialchars($booking['guest_hp']) ?>)</p>
    <p style="font-size: 13px;"><strong>Periode:</strong> <?= format_periode_sewa($booking['tanggal_ambil'], $booking['tanggal_kembali'], $booking['jam_ambil'], $booking['jam_kembali']) ?></p>
    <p style="font-size: 13px;"><strong>Kasir:</strong> <?= htmlspecialchars(Session::get('user_nama')) ?></p>

    <table>
        <?php foreach ($itemBooking as $barang): ?>
            <tr>
                <td><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?></td>
                <td style="text-align: right;"><?= format_rupiah($barang['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td>Total Sewa</td>
            <td style="text-align: right;"><?= format_rupiah($totalSewa) ?></td>
        </tr>
        <tr>
            <td>Dibayar (<?= htmlspecialchars($invoice['jenis'] ?? '-') ?> &middot; <?= htmlspecialchars($invoice['metode'] ?? '-') ?>)</td>
            <td style="text-align: right;"><?= format_rupiah((float) ($invoice['nominal'] ?? 0)) ?></td>
        </tr>
        <tr>
            <td>Sisa Pembayaran</td>
            <td style="text-align: right;"><?= format_rupiah(max(0, $totalSewa - (float) ($invoice['nominal'] ?? 0))) ?></td>
        </tr>
    </table>

    <div class="aksi-cetak" style="text-align: center; margin-top: 14px; display: flex; gap: 10px; justify-content: center;">
        <button class="btn-print" onclick="window.print()">Cetak Struk</button>
        <a href="kasir.php" style="display: inline-block; padding: 10px 20px; background-color: #F0E9DE; color: #2C2018; border-radius: 6px; font-size: 13px; font-weight: 600;">Kembali ke Kasir</a>
        <a href="penyewaan-aktif.php" style="display: inline-block; padding: 10px 20px; background-color: #F0E9DE; color: #2C2018; border-radius: 6px; font-size: 13px; font-weight: 600;">Lihat Penyewaan Aktif</a>
    </div>

</body>
</html>