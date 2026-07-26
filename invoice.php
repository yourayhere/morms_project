<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TransactionModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();

// Otorisasi: hanya pemilik booking (member login, dicek lewat session — bukan
// booking_id di URL yang bisa ditebak) atau pihak yang membuktikan kepemilikan
// lewat kode booking LENGKAP + nomor HP (persis seperti alur tracking.php)
// yang boleh melihat invoice ini.
//
// PENTING: booking_id (integer auto-increment) TIDAK PERNAH dipakai sebagai
// bukti kepemilikan untuk akses tamu, karena gampang ditebak/diurutkan
// (1, 2, 3, ...). Kode booking (mis. MRB-A1B2C3) yang jadi buktinya, karena
// nilainya acak (~16 juta kemungkinan) dan tidak bisa ditebak lewat iterasi.
$booking = null;

if (Auth::isLoggedIn() && Auth::role() === 'member') {
    $bookingIdSesi = (int) ($_GET['booking_id'] ?? 0);
    $kandidat = $bookingIdSesi ? BookingModel::getById($bookingIdSesi) : null;
    if ($kandidat && $kandidat['user_id'] !== null && (int) $kandidat['user_id'] === Auth::id()) {
        $booking = $kandidat;
    }
}

if (!$booking) {
    $kodeInput = clean_input($_GET['kode'] ?? '');
    $hpInput = clean_input($_GET['hp'] ?? '');
    if ($kodeInput !== '' && $hpInput !== '') {
        $booking = BookingModel::getByKodeDanHp($kodeInput, $hpInput);
    }
}

if (!$booking) {
    die('Invoice tidak ditemukan atau Anda tidak memiliki akses ke booking ini.');
}

$bookingId = (int) $booking['id'];

$itemBooking = BookingModel::getItemBooking($bookingId);
$invoice = TransactionModel::getInvoiceUtama($bookingId);
$riwayatTransaksi = TransactionModel::getByBookingId($bookingId);
$totalSewa = array_sum(array_column($itemBooking, 'subtotal'));

$totalTerverifikasi = 0;
foreach ($riwayatTransaksi as $trx) {
    if ($trx['status_verifikasi'] === 'terverifikasi') {
        $totalTerverifikasi += (float) $trx['nominal'];
    }
}
$sisaPembayaran = max(0, $totalSewa - $totalTerverifikasi);

$namaPemesan = $booking['guest_nama'] ?? null;
if ($namaPemesan === null && $booking['user_id'] !== null) {
    $dbNama = \App\Core\Database::getConnection();
    $stmtNama = $dbNama->prepare('SELECT nama FROM users WHERE id = :id');
    $stmtNama->execute(['id' => $booking['user_id']]);
    $namaPemesan = $stmtNama->fetchColumn() ?: 'Member';
}
$namaPemesan = $namaPemesan ?? 'Member';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_no'] ?? '') ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; color: #2B2724; }
        .judul { font-size: 20px; font-weight: bold; color: #2E4452; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #E5E0D8; font-size: 13px; }
        .total-row td { font-weight: bold; font-size: 14px; }
        .btn-print { margin-top: 24px; padding: 10px 20px; background-color: #C0623A; color: white; border: none; border-radius: 6px; cursor: pointer; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>

    <div class="judul">MERIMBA OUTDOOR</div>
    <p style="font-size: 12px; color: #6B6560;">Jl. Sorowajan Baru, Tegal Tanda, Banguntapan, Bantul, DIY 55198</p>

    <hr style="margin: 20px 0; border-color: #E5E0D8;">

    <p><strong>Invoice:</strong> <?= htmlspecialchars($invoice['invoice_no'] ?? '-') ?></p>
    <p><strong>Kode Booking:</strong> <?= htmlspecialchars($booking['kode_booking']) ?></p>
    <p><strong>Nama Pemesan:</strong> <?= htmlspecialchars($namaPemesan) ?></p>
    <p><strong>Periode Sewa:</strong> <?= format_periode_sewa($booking['tanggal_ambil'], $booking['tanggal_kembali'], $booking['jam_ambil'], $booking['jam_kembali']) ?></p>

    <table>
        <thead>
            <tr><th>Barang</th><th>Jumlah</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            <?php foreach ($itemBooking as $barang): ?>
                <tr>
                    <td><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?></td>
                    <td><?= $barang['jumlah'] ?></td>
                    <td><?= format_rupiah($barang['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2">Total Sewa</td>
                <td><?= format_rupiah($totalSewa) ?></td>
            </tr>
            <?php foreach ($riwayatTransaksi as $trx): ?>
            <tr>
                <td colspan="2">
                    Dibayar (<?= htmlspecialchars($trx['jenis']) ?>)
                    <span style="font-size: 11px; color: <?= $trx['status_verifikasi'] === 'terverifikasi' ? '#4A7A5C' : ($trx['status_verifikasi'] === 'ditolak' ? '#B3413A' : '#B3893A') ?>;">
                        &middot; <?= htmlspecialchars(label_status_verifikasi($trx['status_verifikasi'])) ?>
                    </span>
                </td>
                <td><?= format_rupiah((float) $trx['nominal']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($sisaPembayaran > 0): ?>
            <tr class="total-row">
                <td colspan="2">Sisa Pembayaran</td>
                <td><?= format_rupiah($sisaPembayaran) ?></td>
            </tr>
            <?php else: ?>
            <tr class="total-row">
                <td colspan="2">Status Pembayaran</td>
                <td style="color: #4A7A5C;">LUNAS</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalTerverifikasi < $totalSewa && $sisaPembayaran > 0): ?>
        <p style="font-size: 12px; color: #B3893A; margin-top: 10px;">Catatan: nominal yang berstatus "Menunggu Verifikasi" belum dihitung sebagai pembayaran sah sampai dikonfirmasi oleh admin.</p>
    <?php endif; ?>

    <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>

</body>
</html>