<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/upload.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';
require_once __DIR__ . '/../app/Models/TransactionModel.php';

use App\Core\Session;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();

$bookingId = (int) Session::get('booking_id_proses');
$booking = $bookingId ? BookingModel::getById($bookingId) : null;

if (!$booking || $booking['status'] !== 'MENUNGGU_PEMBAYARAN') {
    header('Location: ../katalog.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pembayaran.php?error=token');
    exit;
}

// Jaring pengaman sisi server untuk batas waktu 60 menit yang juga
// ditampilkan sebagai countdown di pembayaran.php - dicek ulang di sini
// supaya tetap ditegakkan walau scheduled task (scripts/expire-bookings.php)
// belum sempat jalan atau JS di klien tidak aktif.
$batasBayar = (new DateTime($booking['created_at']))->modify('+60 minutes');
if (new DateTime() > $batasBayar) {
    BookingModel::updateStatus($bookingId, 'EXPIRED');
    header('Location: ../katalog.php?error=kedaluwarsa');
    exit;
}

$skema = $_POST['skema'] ?? 'dp';

// Checkout mandiri (bukan kasir di toko) wajib QRIS, baik untuk DP maupun
// Lunas, supaya ada uang yang benar-benar terkunci di muka sebelum stok
// ditahan untuk pelanggan ini. Nilai "metode" dari klien tidak dipakai sama
// sekali - dipaksa "qris" di server supaya tidak bisa dilewati lewat request
// langsung. Pelunasan sisa tagihan (kalau skema DP) boleh tunai/QRIS, tapi
// itu ditangani terpisah oleh admin saat pelanggan datang ke toko (lihat
// aksi/pelunasan-sewa.php).
$metode = 'qris';

// Nominal dihitung ulang dari data booking di server. JANGAN percaya
// total_sewa/dp_minimal dari $_POST (hidden field) karena bisa dimanipulasi klien.
$itemBooking = BookingModel::getItemBooking($bookingId);
$totalSewa = (float) array_sum(array_column($itemBooking, 'subtotal'));
$dpMinimal = $totalSewa * 0.5;

$nominalBayar = $skema === 'lunas' ? $totalSewa : $dpMinimal;
$jenisTransaksi = $skema === 'lunas' ? 'lunas' : 'dp';

if (!isset($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../pembayaran.php?error=bukti');
    exit;
}

$hasilUpload = validasi_dan_simpan_identitas($_FILES['bukti_bayar']);
if (!$hasilUpload['sukses']) {
    header('Location: ../pembayaran.php?error=bukti');
    exit;
}
$buktiNamaFile = $hasilUpload['nama_file'];

// Checkout ini dilakukan mandiri oleh customer (bukan kasir di toko), jadi
// uangnya belum benar-benar diterima di titik ini - baru dianggap
// terverifikasi setelah admin mengecek bukti transfer QRIS-nya (lihat
// detail-reservasi.php & verifikasi-pembayaran.php).
TransactionModel::buat([
    'booking_id' => $bookingId,
    'jenis' => $jenisTransaksi,
    'nominal' => $nominalBayar,
    'metode' => $metode,
    'bukti_bayar' => $buktiNamaFile,
    'status_verifikasi' => 'menunggu',
]);

BookingModel::updateStatus($bookingId, 'MENUNGGU_VERIFIKASI');

catat_aktivitas(null, 'pembayaran_dibuat', 'Booking ' . $booking['kode_booking'] . ' metode ' . $metode . ' skema ' . $skema);

header('Location: ../reservasi-berhasil.php');
exit;