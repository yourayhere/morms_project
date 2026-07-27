<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TransactionModel.php';

use App\Core\Session;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();

$kodeInput = clean_input($_GET['kode'] ?? '');
$hpInput = clean_input($_GET['hp'] ?? '');
$booking = null;
$pesanError = '';

if ($kodeInput !== '' && $hpInput !== '') {
    $booking = BookingModel::getByKodeDanHp($kodeInput, $hpInput);
    if (!$booking) {
        $pesanError = 'Booking tidak ditemukan. Periksa kembali kode booking dan nomor HP yang Anda masukkan.';
    }
}

$itemBooking = $booking ? BookingModel::getItemBooking((int) $booking['id']) : [];
$totalSewa = $itemBooking ? array_sum(array_column($itemBooking, 'subtotal')) : 0;
$riwayatTransaksi = $booking ? TransactionModel::getByBookingId((int) $booking['id']) : [];
$totalDibayar = 0;
$totalMenunggu = 0;
foreach ($riwayatTransaksi as $trx) {
    if ($trx['status_verifikasi'] === 'terverifikasi') {
        $totalDibayar += (float) $trx['nominal'];
    } elseif ($trx['status_verifikasi'] === 'menunggu') {
        $totalMenunggu += (float) $trx['nominal'];
    }
}
$sisaPembayaran = max(0, $totalSewa - $totalDibayar);

$stepAktif = $booking ? get_step_aktif($booking['status']) : 0;
$urutanTimeline = get_urutan_timeline();

$judulHalaman = 'Cek Booking';
$halamanAktif = 'tracking';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px;">
    <div class="container" style="max-width: 620px;">

        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 6px;">Cek Status Booking</h1>
        <p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">Masukkan kode booking dan nomor HP yang digunakan saat melakukan reservasi.</p>

        <form method="GET" class="card" style="padding: 20px; margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px;">Kode Booking</label>
                <input type="text" name="kode" required value="<?= clean_input($kodeInput) ?>" placeholder="MRB-XXXXXX"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; text-transform: uppercase;">
            </div>
            <div style="flex: 1; min-width: 180px;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px;">Nomor HP</label>
                <input type="text" name="hp" required value="<?= clean_input($hpInput) ?>" placeholder="08xxxxxxxxxx"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-search"></use></svg>
                    Lacak
                </button>
            </div>
        </form>

        <?php if ($pesanError !== ''): ?>
            <div class="card" style="padding: 16px 18px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px; margin-bottom: 20px;">
                <?= clean_input($pesanError) ?>
            </div>
        <?php endif; ?>

        <?php if ($booking): ?>

            <div class="card" style="padding: 22px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px;">
                    <div>
                        <p style="font-size: 11px; color: var(--color-text-muted);">Kode Booking</p>
                        <p style="font-size: 16px; font-weight: 700; color: var(--color-primary-dark);"><?= htmlspecialchars($booking['kode_booking']) ?></p>
                    </div>
                    <span style="background-color: #FBF1EC; color: var(--color-accent-dark); font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 20px;">
                        <?= status_dibatalkan($booking['status']) ? 'Dibatalkan' : htmlspecialchars(label_status_booking($booking['status'])) ?>
                    </span>
                </div>

                <?php if (!status_dibatalkan($booking['status'])): ?>
                <div style="margin-bottom: 4px;">
                    <?php foreach ($urutanTimeline as $index => $label):
                        $nomorStep = $index + 1;
                        $selesai = $nomorStep <= $stepAktif;
                    ?>
                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                            <div style="display: flex; flex-direction: column; align-items: center;">
                                <svg class="icon" style="width: 20px; height: 20px; color: <?= $selesai ? 'var(--color-success)' : 'var(--color-border)' ?>;">
                                    <use href="assets/icons/sprite.svg#<?= $selesai ? 'icon-check' : 'icon-circle' ?>"></use>
                                </svg>
                                <?php if ($index < count($urutanTimeline) - 1): ?>
                                    <div style="width: 2px; height: 26px; background-color: <?= $nomorStep < $stepAktif ? 'var(--color-success)' : 'var(--color-border)' ?>;"></div>
                                <?php endif; ?>
                            </div>
                            <p style="font-size: 13px; padding-top: 1px; color: <?= $selesai ? 'var(--color-text)' : 'var(--color-text-muted)' ?>; font-weight: <?= $selesai ? '600' : '400' ?>;"><?= $label ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <p style="font-size: 13px; color: var(--color-text-muted);">Reservasi ini telah dibatalkan atau kedaluwarsa.</p>
                    <?php if ($booking['status'] === 'DIBATALKAN' && !empty($booking['catatan_admin'])): ?>
                        <div style="margin-top: 10px; padding: 10px 14px; background-color: #FBEAE5; border-radius: 6px; font-size: 12.5px; color: var(--color-danger);">
                            <strong>Alasan pembatalan:</strong> <?= htmlspecialchars($booking['catatan_admin']) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card" style="padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 14px; margin-bottom: 12px;">Rincian Barang</h3>
                <?php foreach ($itemBooking as $barang): ?>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--color-border);">
                        <span><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?> &times; <?= $barang['jumlah'] ?></span>
                        <strong><?= format_rupiah($barang['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding-top: 10px;">
                    <span style="color: var(--color-text-muted);">Periode Sewa</span>
                    <strong><?= format_periode_sewa($booking['tanggal_ambil'], $booking['tanggal_kembali'], $booking['jam_ambil'], $booking['jam_kembali']) ?></strong>
                </div>
            </div>

            <div class="card" style="padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 14px; margin-bottom: 12px;">Status Pembayaran</h3>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                    <span style="color: var(--color-text-muted);">Total Sewa</span>
                    <strong><?= format_rupiah($totalSewa) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                    <span style="color: var(--color-text-muted);">Sudah Dibayar (Terverifikasi)</span>
                    <strong style="color: var(--color-success);"><?= format_rupiah($totalDibayar) ?></strong>
                </div>
                <?php if ($totalMenunggu > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                    <span style="color: var(--color-text-muted);">Menunggu Verifikasi Admin</span>
                    <strong style="color: var(--color-accent-dark);"><?= format_rupiah($totalMenunggu) ?></strong>
                </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px;">
                    <span style="color: var(--color-text-muted);">Sisa Pembayaran</span>
                    <strong style="color: <?= $sisaPembayaran > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;"><?= format_rupiah($sisaPembayaran) ?></strong>
                </div>
            </div>

            <?php if (in_array($booking['status'], ['DRAFT', 'MENUNGGU_PEMBAYARAN'], true)): ?>
                <div class="card" style="padding: 14px 16px; margin-bottom: 14px; background-color: #FBF1EC; border-color: var(--color-accent);">
                    <p style="font-size: 12.5px; color: var(--color-text); margin-bottom: 10px;">
                        <?= $booking['status'] === 'DRAFT' ? 'Reservasi ini belum diselesaikan. Lanjutkan untuk memilih metode pembayaran.' : 'Reservasi ini masih menunggu pembayaran. Lanjutkan untuk membayar sekarang.' ?>
                    </p>
                    <a href="lanjutkan-reservasi.php?kode=<?= urlencode($booking['kode_booking']) ?>&hp=<?= urlencode($hpInput) ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                        Lanjutkan Reservasi
                    </a>
                </div>
            <?php endif; ?>

            <a href="invoice.php?kode=<?= urlencode($booking['kode_booking']) ?>&hp=<?= urlencode($hpInput) ?>" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                Lihat Invoice
            </a>

        <?php endif; ?>

    </div>
</section>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>