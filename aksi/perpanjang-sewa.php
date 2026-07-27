<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../penyewaan-aktif.php');
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$bookingItemId = (int) ($_POST['booking_item_id'] ?? 0);
$tanggalBaru = $_POST['tanggal_kembali_baru'] ?? '';

$barang = BookingModel::getBookingItemById($bookingItemId);

// Barang harus benar-benar milik booking yang dimaksud, masih berstatus
// "disewa" (barang yang sudah dikembalikan tidak bisa diperpanjang), dan
// tanggal baru harus lebih akhir dari tanggal kembali barang itu SENDIRI
// (bukan tanggal booking secara keseluruhan).
if (!$barang || $barang['booking_id'] !== $bookingId || $barang['status'] !== 'disewa'
    || $tanggalBaru === '' || strtotime($tanggalBaru) <= strtotime($barang['tanggal_kembali'])
) {
    header('Location: ../detail-penyewaan.php?id=' . $bookingId . '&error=tanggal');
    exit;
}

$tambahanHari = (strtotime($tanggalBaru) - strtotime($barang['tanggal_kembali'])) / 86400;
$tambahanSubtotal = $barang['harga_per_malam'] * $tambahanHari * $barang['jumlah'];

BookingModel::tambahSubtotalItem($bookingItemId, $tambahanSubtotal);
BookingModel::perpanjangDurasiItem($bookingItemId, $tanggalBaru);
BookingModel::pulihkanEnvelope($bookingId);

catat_aktivitas((int) Auth::id(), 'perpanjang_sewa', 'Barang ' . $barang['nama'] . ' pada booking #' . $bookingId . ' diperpanjang ' . $tambahanHari . ' hari');

header('Location: ../detail-penyewaan.php?id=' . $bookingId);
exit;
