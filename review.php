<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';

use App\Core\Session;
use App\Models\BookingModel;

Session::start();

$bookingId = (int) Session::get('booking_id_proses');
$booking = $bookingId ? BookingModel::getById($bookingId) : null;

if (!$booking) {
    header('Location: katalog.php');
    exit;
}

$itemBooking = BookingModel::getItemBooking($bookingId);
$totalSewa = array_sum(array_column($itemBooking, 'subtotal'));
$dpMinimal = $totalSewa * 0.5;

$csrfToken = generate_csrf_token();

$judulHalaman = 'Review Pesanan';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px;">
    <div class="container" style="max-width: 600px;">

        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Review Pesanan</h1>
        <p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">Kode Booking: <strong><?= htmlspecialchars($booking['kode_booking']) ?></strong></p>

        <div class="card" style="padding: 20px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Rincian Barang</h3>
            <?php foreach ($itemBooking as $barang): ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <span><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?> &times; <?= $barang['jumlah'] ?></span>
                        <p style="font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px;"><?= htmlspecialchars(format_periode_sewa($barang['tanggal_ambil'], $barang['tanggal_kembali'], $barang['jam_ambil'] ?? null)) ?></p>
                    </div>
                    <strong><?= format_rupiah($barang['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>

            <?php if (count($itemBooking) > 1): ?>
            <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 10px 0 4px;">
                <span style="color: var(--color-text-muted);">Sewa Berlangsung</span>
                <strong><?= format_periode_sewa($booking['tanggal_ambil'], $booking['tanggal_kembali'], $booking['jam_ambil'], $booking['jam_kembali']) ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 20px; margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; font-size: 15px; margin-bottom: 8px;">
                <span>Total Sewa</span>
                <strong style="color: var(--color-accent);"><?= format_rupiah($totalSewa) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                <span style="color: var(--color-text-muted);">DP Minimal (50%)</span>
                <strong><?= format_rupiah($dpMinimal) ?></strong>
            </div>
        </div>

        <div class="card" style="padding: 20px; margin-bottom: 24px; display: flex; gap: 10px; align-items: flex-start;">
            <svg class="icon icon-accent" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
            <p style="font-size: 12px; color: var(--color-text-muted); line-height: 1.6;">
                Identitas aktif (KTM/KTP/SIM) wajib ditinggalkan saat pengambilan barang sebagai jaminan, dan akan dikembalikan saat seluruh barang dikembalikan dalam kondisi baik. Keterlambatan pengembalian dikenakan biaya tambahan sebesar harga sewa satu hari. Kerusakan atau kehilangan barang wajib diganti dengan barang sejenis atau biaya yang setara.
                <a href="syarat-privasi.php#syarat" target="_blank" rel="noopener" style="color: var(--color-accent); font-weight: 600; white-space: nowrap;">Selengkapnya di Syarat & Ketentuan</a>
            </p>
        </div>

        <form method="POST" action="aksi/konfirmasi-review.php">
            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">

            <label style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px; cursor: pointer;">
                <input type="checkbox" name="setuju" required style="margin-top: 3px;">
                <span style="font-size: 13px; color: var(--color-text);">Saya menyetujui syarat penyewaan yang berlaku.</span>
            </label>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Lanjut ke Pembayaran
                <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
            </button>
        </form>

    </div>
</section>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>