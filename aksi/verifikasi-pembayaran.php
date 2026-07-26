<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/TransactionModel.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\TransactionModel;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../reservasi-online.php');
    exit;
}

$transactionId = (int) ($_POST['transaction_id'] ?? 0);
$bookingId = (int) ($_POST['booking_id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';

$transaksi = TransactionModel::getById($transactionId);
$booking = BookingModel::getById($bookingId);

if (!$transaksi || !$booking || (int) $transaksi['booking_id'] !== $bookingId) {
    header('Location: ../reservasi-online.php');
    exit;
}

if ($aksi === 'terima') {
    TransactionModel::verifikasi($transactionId, 'terverifikasi', (int) Auth::id());
    BookingModel::updateStatus($bookingId, 'MENUNGGU_KEDATANGAN');
    catat_aktivitas((int) Auth::id(), 'verifikasi_pembayaran', 'Pembayaran booking ' . $booking['kode_booking'] . ' diterima');
} elseif ($aksi === 'tolak') {
    TransactionModel::verifikasi($transactionId, 'ditolak', (int) Auth::id());
    catat_aktivitas((int) Auth::id(), 'tolak_pembayaran', 'Pembayaran booking ' . $booking['kode_booking'] . ' ditolak');
}

header('Location: ../detail-reservasi.php?id=' . $bookingId);
exit;