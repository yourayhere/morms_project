<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/ReturnModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\ReturnModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

// Struk mencerminkan PERSIS barang yang dikembalikan dalam satu event
// (mendukung pengembalian bertahap) - lihat aksi/simpan-pengembalian.php
// yang mengirim daftar returns.id hasil event itu lewat ?ids=1,2,3.
$idMentah = $_GET['ids'] ?? '';
$idList = array_filter(array_map('intval', explode(',', $idMentah)));
$daftarPengembalian = ReturnModel::getByIds($idList);

if (empty($daftarPengembalian)) {
    header('Location: pengembalian.php');
    exit;
}

$bookingId = (int) $daftarPengembalian[0]['booking_id'];
$booking = BookingModel::getById($bookingId);

if (!$booking) {
    header('Location: pengembalian.php');
    exit;
}

$totalDenda = array_sum(array_column($daftarPengembalian, 'denda_terlambat'));
$totalRusak = array_sum(array_column($daftarPengembalian, 'biaya_kerusakan'));
$sisaBelumKembali = BookingModel::getJumlahBelumDikembalikan($bookingId);
$keterangan = trim((string) ($daftarPengembalian[0]['keterangan'] ?? ''));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Pengembalian <?= htmlspecialchars($booking['kode_booking']) ?></title>
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
    <p class="sub">Struk Pengembalian Barang</p>

    <p style="font-size: 13px;"><strong>Kode Booking:</strong> <?= htmlspecialchars($booking['kode_booking']) ?></p>
    <?php if ($keterangan !== ''): ?>
        <p style="font-size: 13px;"><strong>Keterangan:</strong> <?= htmlspecialchars($keterangan) ?></p>
    <?php endif; ?>

    <table>
        <tr>
            <th>Barang</th>
            <th>Kondisi</th>
            <th style="text-align: right;">Biaya</th>
        </tr>
        <?php foreach ($daftarPengembalian as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['nama_barang'] ?? '-') ?><?php if (!empty($p['ukuran_dipilih'])): ?> (<?= htmlspecialchars($p['ukuran_dipilih']) ?>)<?php endif; ?></td>
            <td><?= label_kondisi_pengembalian($p['kondisi']) ?></td>
            <td style="text-align: right;"><?= format_rupiah((float) $p['denda_terlambat'] + (float) $p['biaya_kerusakan']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($totalDenda > 0): ?>
        <tr>
            <td colspan="2">Total Biaya Keterlambatan</td>
            <td style="text-align: right;"><?= format_rupiah($totalDenda) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($totalRusak > 0): ?>
        <tr>
            <td colspan="2">Total Biaya Kerusakan/Kehilangan</td>
            <td style="text-align: right;"><?= format_rupiah($totalRusak) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
            <td colspan="2">Status Booking</td>
            <td style="text-align: right; color: <?= $sisaBelumKembali === 0 ? '#4A7A5C' : '#B3893A' ?>;">
                <?= $sisaBelumKembali === 0 ? 'Selesai' : $sisaBelumKembali . ' barang lagi belum kembali' ?>
            </td>
        </tr>
    </table>

    <p style="font-size: 12px; color: #6B6560; margin-top: 16px;">
        <?= $sisaBelumKembali === 0
            ? 'Seluruh barang sudah kembali. Identitas jaminan telah dikembalikan kepada penyewa.'
            : 'Masih ada barang lain dari booking ini yang sedang disewa. Identitas jaminan ditahan sampai semua barang kembali.' ?>
    </p>

    <div class="aksi-cetak" style="text-align: center; margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
        <button class="btn-print" onclick="window.print()">Cetak</button>
        <a href="pengembalian.php" style="display: inline-block; padding: 10px 20px; background-color: #F0E9DE; color: #2C2018; border-radius: 6px; font-size: 13px; font-weight: 600;">Kembali ke Pengembalian</a>
        <a href="dashboard-admin.php" style="display: inline-block; padding: 10px 20px; background-color: #F0E9DE; color: #2C2018; border-radius: 6px; font-size: 13px; font-weight: 600;">Ke Dashboard</a>
    </div>

</body>
</html>
