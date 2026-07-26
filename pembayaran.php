<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/SettingModel.php';

use App\Core\Session;
use App\Models\BookingModel;
use App\Models\SettingModel;

Session::start();

// Jaring pengaman: kalau halaman ini dibuka/di-refresh setelah 60 menit
// berlalu tapi status di database belum sempat ditandai EXPIRED oleh
// dashboard-admin.php/cron, tandai dulu di sini supaya pengecekan status di
// bawah akurat - bukan cuma mengandalkan countdown di sisi klien.
BookingModel::expireBookingBelumDibayar(60);

$bookingId = (int) Session::get('booking_id_proses');
$booking = $bookingId ? BookingModel::getById($bookingId) : null;

if ($booking && $booking['status'] === 'EXPIRED') {
    header('Location: katalog.php?error=kedaluwarsa');
    exit;
}

if (!$booking || $booking['status'] !== 'MENUNGGU_PEMBAYARAN') {
    header('Location: katalog.php');
    exit;
}

$itemBooking = BookingModel::getItemBooking($bookingId);
$totalSewa = (float) array_sum(array_column($itemBooking, 'subtotal'));
$dpMinimal = $totalSewa * 0.5;

// Batas waktu pembayaran 60 menit sejak reservasi dibuat, berlaku sama baik
// untuk skema DP maupun Lunas karena keduanya sama-sama wajib QRIS. Lewat
// dari batas ini, BookingModel::expireBookingBelumDibayar() akan menandai
// booking sebagai EXPIRED (lihat dashboard-admin.php dan
// scripts/expire-bookings.php).
$batasBayar = (new DateTime($booking['created_at']))->modify('+60 minutes');

// Pakai gambar QRIS yang diunggah admin lewat halaman Pengaturan kalau ada,
// jatuh ke gambar default kalau admin belum pernah unggah.
$qrisPath = SettingModel::get('qris_image') ?: 'assets/images/qris/qris-merimba.png';
$qrisTersedia = file_exists(__DIR__ . '/' . $qrisPath);

$pesanError = [
    'bukti' => 'Berkas bukti transfer wajib diunggah dengan format dan ukuran yang sesuai.',
    'token' => 'Sesi Anda sudah kedaluwarsa. Silakan coba lagi.',
][$_GET['error'] ?? ''] ?? null;

$csrfToken = generate_csrf_token();

$judulHalaman = 'Pembayaran';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px;">
    <div class="container" style="max-width: 540px;">

        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Pembayaran</h1>
        <p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">Kode Booking: <strong><?= htmlspecialchars($booking['kode_booking']) ?></strong></p>

        <?php if ($pesanError): ?>
            <div class="card" style="padding: 14px 16px; margin-bottom: 20px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px;">
                <?= htmlspecialchars($pesanError) ?>
            </div>
        <?php endif; ?>

        <div class="card" id="kartu-batas-bayar" style="padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background-color: #FBF1EC; border-color: var(--color-accent);">
            <svg class="icon icon-accent" style="width: 18px; height: 18px; flex-shrink: 0;"><use href="assets/icons/sprite.svg#icon-clock"></use></svg>
            <p id="teks-batas-bayar" style="font-size: 12.5px; color: var(--color-text); line-height: 1.5;">
                Sisa waktu pembayaran: <strong id="countdown-bayar">60:00</strong>. Baik DP maupun Lunas sama-sama memiliki batas waktu 60 menit sejak reservasi dibuat. Jika melewati batas waktu tersebut, reservasi akan otomatis dibatalkan dan stok akan dilepas kembali.
            </p>
        </div>

        <div class="card" style="padding: 18px 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px;">
                <span style="color: var(--color-text-muted);">Total Sewa</span>
                <strong><?= format_rupiah($totalSewa) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                <span style="color: var(--color-text-muted);">DP Minimal (50%)</span>
                <strong><?= format_rupiah($dpMinimal) ?></strong>
            </div>
        </div>

        <form method="POST" action="aksi/proses-pembayaran.php" enctype="multipart/form-data" id="form-pembayaran">
            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
            <input type="hidden" name="total_sewa" value="<?= $totalSewa ?>">
            <input type="hidden" name="dp_minimal" value="<?= $dpMinimal ?>">

            <div class="card" style="padding: 20px; margin-bottom: 18px;">
                <h3 style="font-size: 14px; margin-bottom: 14px;">Skema Pembayaran</h3>
                <div style="display: flex; gap: 12px;">
                    <label style="flex: 1; border: 1.5px solid var(--color-border); border-radius: 8px; padding: 14px; text-align: center; cursor: pointer;" class="opsi-skema">
                        <input type="radio" name="skema" value="dp" checked style="margin-bottom: 8px;">
                        <div style="font-size: 13px; font-weight: 600;">Bayar DP 50%</div>
                        <div style="font-size: 12px; color: var(--color-text-muted);"><?= format_rupiah($dpMinimal) ?></div>
                    </label>
                    <label style="flex: 1; border: 1.5px solid var(--color-border); border-radius: 8px; padding: 14px; text-align: center; cursor: pointer;" class="opsi-skema">
                        <input type="radio" name="skema" value="lunas" style="margin-bottom: 8px;">
                        <div style="font-size: 13px; font-weight: 600;">Bayar Lunas</div>
                        <div style="font-size: 12px; color: var(--color-text-muted);"><?= format_rupiah($totalSewa) ?></div>
                    </label>
                </div>
            </div>

            <input type="hidden" name="metode" value="qris">

            <div class="card" style="padding: 20px; margin-bottom: 18px;">
                <h3 style="font-size: 14px; margin-bottom: 4px;">Metode Pembayaran</h3>
                <p style="font-size: 12px; color: var(--color-text-muted);">Reservasi online dibayar melalui QRIS, baik untuk DP maupun Lunas. Setelah pembayaran terverifikasi, reservasi Anda akan dikonfirmasi. Jika memilih DP, sisa pembayaran dapat dilunasi secara tunai atau melalui QRIS saat pengambilan maupun pengembalian barang.</p>
            </div>

            <div id="panel-qris" class="card" style="padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 16px;">
                    <?php if ($qrisTersedia): ?>
                        <img src="<?= htmlspecialchars($qrisPath) ?>" alt="QRIS Merimba Outdoor"
                            style="display: block; width: 240px; max-width: 100%; aspect-ratio: 1 / 1; object-fit: contain; margin: 0 auto; background-color: #fff; border: 1px solid var(--color-border); border-radius: 10px; padding: 10px;">
                    <?php else: ?>
                        <div style="width: 240px; max-width: 100%; aspect-ratio: 1 / 1; margin: 0 auto; display: flex; align-items: center; justify-content: center; background-color: var(--color-bg); border-radius: 10px;">
                            <svg class="icon" style="width: 48px; height: 48px; color: var(--color-primary-light);"><use href="assets/icons/sprite.svg#icon-qris"></use></svg>
                        </div>
                    <?php endif; ?>
                    <p style="font-size: 12px; color: var(--color-text-muted); margin-top: 10px;">Pindai kode QRIS di atas menggunakan aplikasi pembayaran Anda.</p>
                </div>

                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Unggah Bukti Transfer</label>
                <div class="img-upload-grid" id="grid-bukti-bayar"></div>
                <label for="bukti_bayar" style="display: flex; flex-direction: column; align-items: center; gap: 8px; border: 1.5px dashed var(--color-border); border-radius: 8px; padding: 22px; cursor: pointer; background-color: var(--color-bg);">
                    <svg class="icon" style="width: 22px; height: 22px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                    <span id="label-bukti" style="font-size: 12px; color: var(--color-text-muted);">Klik untuk pilih berkas (JPG, PNG, maksimal 5MB)</span>
                </label>
                <input type="file" id="bukti_bayar" name="bukti_bayar" accept="image/jpeg,image/png,image/webp" required style="display: none;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Konfirmasi Pembayaran
                <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
            </button>
        </form>

    </div>
</section>

<style>
.opsi-skema:has(input:checked) {
    border-color: var(--color-accent);
    background-color: #FBF1EC;
}
</style>

<script src="assets/js/image-uploader.js"></script>
<script>
var batasBayarTs = new Date(<?= json_encode($batasBayar->format('c')) ?>).getTime();
var elCountdown = document.getElementById('countdown-bayar');
var elTeksBatasBayar = document.getElementById('teks-batas-bayar');
var elKartuBatasBayar = document.getElementById('kartu-batas-bayar');
var btnSubmitBayar = document.querySelector('#form-pembayaran button[type="submit"]');

function perbaruiCountdownBayar() {
    var sisaMs = batasBayarTs - Date.now();

    if (sisaMs <= 0) {
        elTeksBatasBayar.textContent = 'Waktu pembayaran sudah habis. Reservasi ini akan otomatis dibatalkan dan stok akan dilepas kembali.';
        elKartuBatasBayar.style.backgroundColor = '#FBEAE5';
        elKartuBatasBayar.style.borderColor = 'var(--color-danger)';
        if (btnSubmitBayar) {
            btnSubmitBayar.disabled = true;
            btnSubmitBayar.style.opacity = '0.5';
            btnSubmitBayar.style.cursor = 'not-allowed';
        }
        clearInterval(timerBatasBayar);
        return;
    }

    var totalDetik = Math.floor(sisaMs / 1000);
    var menit = Math.floor(totalDetik / 60);
    var detik = totalDetik % 60;
    elCountdown.textContent = menit + ':' + (detik < 10 ? '0' : '') + detik;
}

perbaruiCountdownBayar();
var timerBatasBayar = setInterval(perbaruiCountdownBayar, 1000);

var inputBukti = document.getElementById('bukti_bayar');
MormsImageUploader.pasangPreview(inputBukti, document.getElementById('grid-bukti-bayar'), {
    onChange: function (jumlah) {
        document.getElementById('label-bukti').textContent = jumlah > 0
            ? inputBukti.files[0].name
            : 'Klik untuk pilih berkas (JPG, PNG, maksimal 5MB)';
    }
});
</script>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>