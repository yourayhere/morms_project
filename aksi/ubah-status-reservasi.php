<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';
require_once __DIR__ . '/../app/Models/TransactionModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../reservasi-online.php');
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$statusBaru = $_POST['status_baru'] ?? '';

$statusDiizinkan = [
    'RESERVASI_DIKONFIRMASI', 'BARANG_DISIAPKAN', 'SIAP_DIAMBIL', 'SEDANG_DISEWA', 'DIBATALKAN',
];

$booking = BookingModel::getById($bookingId);

if (!$booking || !in_array($statusBaru, $statusDiizinkan, true)) {
    header('Location: ../reservasi-online.php');
    exit;
}

// Reservasi tidak boleh dikonfirmasi selama masih ada transaksi yang menunggu
// verifikasi admin (mis. bukti QRIS belum dicek, atau cash belum dikonfirmasi
// diterima). Dicek ulang di server, bukan cuma disembunyikan di tombol UI.
if ($statusBaru === 'RESERVASI_DIKONFIRMASI') {
    foreach (TransactionModel::getByBookingId($bookingId) as $trx) {
        if ($trx['status_verifikasi'] === 'menunggu') {
            header('Location: ../detail-reservasi.php?id=' . $bookingId . '&error=pembayaran_pending');
            exit;
        }
    }
}

if ($statusBaru === 'DIBATALKAN') {
    $alasan = clean_input($_POST['alasan_pembatalan'] ?? '');
    if ($alasan === '') {
        header('Location: ../detail-reservasi.php?id=' . $bookingId . '&error=alasan_wajib');
        exit;
    }
    BookingModel::batalkanDenganAlasan($bookingId, $alasan);
    catat_aktivitas((int) Auth::id(), 'batalkan_reservasi', 'Booking ' . $booking['kode_booking'] . ' dibatalkan. Alasan: ' . $alasan);
} else {
    BookingModel::updateStatus($bookingId, $statusBaru);
    catat_aktivitas((int) Auth::id(), 'ubah_status_reservasi', 'Booking ' . $booking['kode_booking'] . ' diubah ke ' . $statusBaru);
}

header('Location: ../detail-reservasi.php?id=' . $bookingId);
exit;

