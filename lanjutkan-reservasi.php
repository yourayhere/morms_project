<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Models/BookingModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();

// Jaring pengaman: pastikan status booking sudah paling baru sebelum dicek
// di bawah, supaya reservasi yang sebenarnya sudah lewat 60 menit tidak
// terlanjur dianggap masih MENUNGGU_PEMBAYARAN gara-gara belum sempat
// ditandai EXPIRED oleh dashboard-admin.php/cron.
BookingModel::expireBookingBelumDibayar(60);

// Otorisasi: pemilik booking (member login, dicek lewat session) atau
// pembuktian lewat kode booking LENGKAP + nomor HP (tamu) — persis seperti
// alur tracking.php/invoice.php. booking_id (integer, mudah ditebak/diurutkan)
// TIDAK PERNAH dipakai sebagai bukti kepemilikan untuk akses tamu; kode
// booking acak (mis. MRB-A1B2C3) yang jadi buktinya.
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

if (!$booking || !in_array($booking['status'], ['DRAFT', 'MENUNGGU_PEMBAYARAN'], true)) {
    header('Location: katalog.php');
    exit;
}

$bookingId = (int) $booking['id'];

// Sesi proses booking dipulihkan supaya review.php/pembayaran.php (yang
// bergantung pada session ini) bisa melanjutkan meski sesi lama sudah hilang
// (mis. browser ditutup, ganti perangkat, atau kembali di hari berikutnya).
Session::set('booking_id_proses', $bookingId);

header('Location: ' . ($booking['status'] === 'DRAFT' ? 'review.php' : 'pembayaran.php'));
exit;
