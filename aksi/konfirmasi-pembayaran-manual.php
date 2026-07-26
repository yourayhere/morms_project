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
$booking = BookingModel::getById($bookingId);

// Hanya untuk reservasi yang macet sebelum menyelesaikan alur pembayaran
// online (DRAFT/MENUNGGU_PEMBAYARAN) — mencegah dobel transaksi kalau
// reservasi sudah lanjut ke tahap berikutnya lewat alur normal.
if (!$booking || !in_array($booking['status'], ['DRAFT', 'MENUNGGU_PEMBAYARAN'], true)) {
    header('Location: ../reservasi-online.php');
    exit;
}

$skema = $_POST['skema'] === 'lunas' ? 'lunas' : 'dp';
$metode = $_POST['metode'] === 'qris' ? 'qris' : 'cash';

// Nominal dihitung ulang dari data booking di server, bukan dipercaya dari
// input manapun di form.
$itemBooking = BookingModel::getItemBooking($bookingId);
$totalSewa = (float) array_sum(array_column($itemBooking, 'subtotal'));
$dpMinimal = $totalSewa * 0.5;
$nominal = $skema === 'lunas' ? $totalSewa : $dpMinimal;

$transactionId = TransactionModel::buat([
    'booking_id' => $bookingId,
    'jenis' => $skema,
    'nominal' => $nominal,
    'metode' => $metode,
    'bukti_bayar' => null,
    'status_verifikasi' => 'terverifikasi',
]);
TransactionModel::verifikasi($transactionId, 'terverifikasi', (int) Auth::id());

BookingModel::updateStatus($bookingId, 'MENUNGGU_KEDATANGAN');

catat_aktivitas(
    (int) Auth::id(),
    'konfirmasi_pembayaran_manual',
    'Pembayaran booking ' . $booking['kode_booking'] . ' dikonfirmasi manual oleh admin (' . $skema . ', ' . $metode . ', Rp' . number_format($nominal, 0, ',', '.') . ')'
);

header('Location: ../detail-reservasi.php?id=' . $bookingId);
exit;
