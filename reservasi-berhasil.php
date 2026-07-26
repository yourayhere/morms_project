<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TransactionModel.php';
require_once __DIR__ . '/app/Models/SettingModel.php';

use App\Core\Session;
use App\Models\BookingModel;
use App\Models\TransactionModel;
use App\Models\SettingModel;

Session::start();

$bookingId = (int) Session::get('booking_id_proses');
$booking = $bookingId ? BookingModel::getById($bookingId) : null;

if (!$booking) {
    header('Location: katalog.php');
    exit;
}

$itemBooking = BookingModel::getItemBooking($bookingId);
$invoice = TransactionModel::getInvoiceUtama($bookingId);

Session::set('booking_id_proses', null);

$labelStatus = [
    'MENUNGGU_VERIFIKASI' => 'Menunggu Verifikasi',
    'MENUNGGU_KEDATANGAN' => 'Menunggu Kedatangan',
];

$pengaturanJam = SettingModel::getAll();
$jamBukaToko = $pengaturanJam['jam_buka'] ?? '';
$jamTutupToko = $pengaturanJam['jam_tutup'] ?? '';
$diLuarJamOperasional = ($jamBukaToko !== '' && $jamTutupToko !== '')
    && !sedang_jam_operasional($jamBukaToko, $jamTutupToko);

$judulHalaman = 'Reservasi Berhasil';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 40px 0 56px;">
    <div class="container" style="max-width: 540px; text-align: center;">

        <svg class="icon icon-accent" style="width: 44px; height: 44px; margin-bottom: 12px;"><use href="assets/icons/sprite.svg#icon-party"></use></svg>
        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 6px;">Reservasi Berhasil</h1>
        <p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 28px;">Pesanan Anda telah kami terima dan sedang diproses.</p>

        <div class="card" style="padding: 24px; margin-bottom: 18px; text-align: left;">

            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 14px; border-bottom: 1px solid var(--color-border); margin-bottom: 14px;">
                <div>
                    <p style="font-size: 11px; color: var(--color-text-muted);">Kode Booking</p>
                    <p style="font-size: 18px; font-weight: 700; color: var(--color-primary-dark); letter-spacing: 0.02em;" id="kode-booking-text"><?= htmlspecialchars($booking['kode_booking']) ?></p>
                </div>
                <button type="button" id="btn-copy" style="background: none; border: 1px solid var(--color-border); border-radius: 6px; padding: 8px 10px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 12px;">
                    <svg class="icon" style="width: 15px; height: 15px;"><use href="assets/icons/sprite.svg#icon-copy"></use></svg>
                    Salin
                </button>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span style="color: var(--color-text-muted);">Nomor Invoice</span>
                <strong><?= htmlspecialchars($invoice['invoice_no'] ?? '-') ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span style="color: var(--color-text-muted);">Status</span>
                <strong style="color: var(--color-accent);"><?= htmlspecialchars($labelStatus[$booking['status']] ?? $booking['status']) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span style="color: var(--color-text-muted);">Jumlah Dibayar</span>
                <div style="text-align: right;">
                    <strong><?= format_rupiah((float) ($invoice['nominal'] ?? 0)) ?></strong>
                    <?php if (($invoice['status_verifikasi'] ?? 'menunggu') !== 'terverifikasi'): ?>
                        <p style="font-size: 11px; color: var(--color-accent-dark); margin-top: 2px;">Menunggu Verifikasi Admin</p>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                <span style="color: var(--color-text-muted);">Periode Sewa</span>
                <strong><?= format_periode_sewa($booking['tanggal_ambil'], $booking['tanggal_kembali'], $booking['jam_ambil'], $booking['jam_kembali']) ?></strong>
            </div>
        </div>

        <?php if ($diLuarJamOperasional): ?>
            <div class="card" style="padding: 16px 18px; margin-bottom: 18px; display: flex; gap: 10px; align-items: flex-start; text-align: left; background-color: #FBF1EC; border-color: var(--color-accent);">
                <svg class="icon icon-accent" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;"><use href="assets/icons/sprite.svg#icon-clock"></use></svg>
                <p style="font-size: 12px; color: var(--color-text); line-height: 1.6;">
                    Reservasi Anda sudah berhasil diterima sistem kami. Karena saat ini di luar jam operasional kami (<?= htmlspecialchars(format_jam_operasional($jamBukaToko, $jamTutupToko)) ?>), tim kami akan memproses verifikasi pembayaran dan hal lainnya begitu jam operasional berlangsung kembali. Terima kasih atas kesabaran Anda.
                </p>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 16px 18px; margin-bottom: 24px; display: flex; gap: 10px; align-items: flex-start; text-align: left; background-color: #FBF1EC; border-color: var(--color-accent);">
            <svg class="icon icon-accent" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
            <p style="font-size: 12px; color: var(--color-text); line-height: 1.6;">
                Simpan kode booking ini baik-baik. Kode ini diperlukan untuk melacak status pesanan Anda, terutama jika Anda memesan sebagai tamu.
            </p>
        </div>

        <div style="display: flex; gap: 12px;">
            <a href="invoice.php?booking_id=<?= $bookingId ?>&kode=<?= urlencode($booking['kode_booking']) ?>&hp=<?= urlencode($booking['guest_hp'] ?? '') ?>" class="btn btn-secondary" style="flex: 1; justify-content: center;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                Lihat Invoice
            </a>
            <a href="tracking.php?kode=<?= urlencode($booking['kode_booking']) ?>" class="btn btn-secondary" style="flex: 1; justify-content: center;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg>
                Lacak Pesanan
            </a>
        </div>

        <a href="katalog.php" style="display: inline-block; margin-top: 16px; font-size: 13px; color: var(--color-accent); font-weight: 600;">Booking Barang Lain</a>

    </div>
</section>

<script>
document.getElementById('btn-copy').addEventListener('click', function () {
    const teks = document.getElementById('kode-booking-text').textContent;
    navigator.clipboard.writeText(teks).then(function () {
        const tombol = document.getElementById('btn-copy');
        const teksAsli = tombol.innerHTML;
        tombol.innerHTML = 'Tersalin';
        setTimeout(function () { tombol.innerHTML = teksAsli; }, 1500);
    });
});
</script>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>