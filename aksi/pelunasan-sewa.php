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
    header('Location: ../penyewaan-aktif.php');
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$nominal = (float) ($_POST['nominal'] ?? 0);
$metode = $_POST['metode'] ?? 'cash';

$booking = BookingModel::getById($bookingId);

if (!$booking || $nominal <= 0) {
    header('Location: ../detail-penyewaan.php?id=' . $bookingId);
    exit;
}

// Batasi nominal pelunasan agar tidak melebihi sisa tagihan riil (dihitung
// ulang dari data booking di server), bukan hanya bergantung pada atribut
// "max" di form yang bisa dilewati lewat request langsung.
$itemBooking = BookingModel::getItemBooking($bookingId);
$totalSewa = (float) array_sum(array_column($itemBooking, 'subtotal'));
$riwayatTransaksi = TransactionModel::getByBookingId($bookingId);
$totalDibayar = 0.0;
foreach ($riwayatTransaksi as $trx) {
    if ($trx['status_verifikasi'] !== 'ditolak') {
        $totalDibayar += (float) $trx['nominal'];
    }
}
$sisaPembayaran = max(0, $totalSewa - $totalDibayar);

if ($sisaPembayaran <= 0) {
    header('Location: ../detail-penyewaan.php?id=' . $bookingId . '&error=lunas');
    exit;
}

$nominal = min($nominal, $sisaPembayaran);

$transactionId = TransactionModel::buat([
    'booking_id' => $bookingId,
    'jenis' => 'sisa',
    'nominal' => $nominal,
    'metode' => $metode,
    'bukti_bayar' => null,
    'status_verifikasi' => 'terverifikasi',
]);

TransactionModel::verifikasi($transactionId, 'terverifikasi', (int) Auth::id());

catat_aktivitas((int) Auth::id(), 'pelunasan_sewa', 'Pelunasan booking ' . $booking['kode_booking'] . ' sebesar ' . $nominal);

header('Location: ../detail-penyewaan.php?id=' . $bookingId);
exit;