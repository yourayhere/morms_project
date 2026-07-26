<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/Cart.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/toko.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\Cart;

Session::start();

if (empty(Cart::getAll())) {
    header('Location: keranjang.php');
    exit;
}

if (toko_sedang_tutup()) {
    // Tidak perlu query string error - banner peringatan toko tutup sudah
    // tampil global di header.php (lihat keranjang.php setelah redirect ini).
    header('Location: keranjang.php');
    exit;
}

$sudahLogin = Auth::isLoggedIn() && Auth::role() === 'member';
$modeCheckout = $_GET['mode'] ?? ($sudahLogin ? 'member' : '');

$pesanError = [
    'data'      => 'Data yang Anda isi belum lengkap. Silakan periksa kembali.',
    'identitas' => 'Berkas identitas wajib diunggah dengan format dan ukuran yang sesuai.',
    'token'     => 'Sesi Anda sudah kedaluwarsa. Silakan coba lagi.',
    'sistem'    => 'Terjadi kendala sistem. Silakan coba lagi beberapa saat lagi.',
    'diblokir'  => 'Nomor HP ini tidak dapat digunakan untuk melakukan reservasi. Silakan hubungi admin.',
    'stok'      => 'Maaf, stok salah satu barang di keranjang Anda baru saja habis diambil pemesan lain. Silakan kembali ke keranjang dan sesuaikan jumlahnya.',
    'format'    => 'Format nomor HP atau email tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.',
    'ukuran'    => 'Salah satu barang di keranjang Anda belum memiliki ukuran yang dipilih. Silakan kembali ke keranjang, hapus barang tersebut, lalu tambahkan kembali dengan memilih ukurannya.',
];
$errorCode = $_GET['error'] ?? '';

$csrfToken = generate_csrf_token();

$judulHalaman = 'Checkout';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px;">
    <div class="container" style="max-width: 560px;">

        <?php if ($modeCheckout === ''): ?>

            <div class="checkout-mode-select">
                <h1 class="checkout-mode-title">Checkout</h1>
                <p class="checkout-mode-subtitle">Pilih cara melanjutkan pesanan Anda.</p>

                <?php if ($errorCode !== '' && isset($pesanError[$errorCode])): ?>
                    <div class="card" style="padding: 14px 16px; margin-bottom: 20px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px; text-align: left;">
                        <?= htmlspecialchars($pesanError[$errorCode]) ?>
                    </div>
                <?php endif; ?>

                <div class="checkout-mode-grid">
                    <a href="checkout.php?mode=guest" class="card card-hover checkout-mode-card">
                        <span class="checkout-mode-icon checkout-mode-icon--guest">
                            <svg class="icon"><use href="assets/icons/sprite.svg#icon-guest"></use></svg>
                        </span>
                        <h3 class="checkout-mode-card-title">Lanjutkan sebagai Tamu</h3>
                        <p class="checkout-mode-card-desc">Isi data secara manual tanpa akun.</p>
                        <span class="checkout-mode-cta" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                    </a>

                    <a href="<?= $sudahLogin ? 'checkout.php?mode=member' : 'login.php?redirect=checkout' ?>" class="card card-hover checkout-mode-card">
                        <span class="checkout-mode-icon checkout-mode-icon--member">
                            <svg class="icon"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                            <span class="checkout-mode-icon-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </span>
                        <h3 class="checkout-mode-card-title">Masuk sebagai Member</h3>
                        <p class="checkout-mode-card-desc">Data otomatis terisi dari profil Anda.</p>
                        <span class="checkout-mode-cta" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 20px;">Checkout</h1>

            <?php if ($errorCode !== '' && isset($pesanError[$errorCode])): ?>
                <div class="card" style="padding: 14px 16px; margin-bottom: 20px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px;">
                    <?= htmlspecialchars($pesanError[$errorCode]) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="aksi/simpan-checkout.php" enctype="multipart/form-data" id="form-checkout">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="mode" value="<?= clean_input($modeCheckout) ?>">

                <?php if ($modeCheckout === 'guest'): ?>

                    <div class="card" style="padding: 24px; margin-bottom: 20px;">
                        <h3 style="font-size: 15px; margin-bottom: 16px;">Data Pemesan</h3>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Nama Lengkap</label>
                            <input type="text" name="nama" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Nomor HP</label>
                            <input type="tel" name="hp" required inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" placeholder="Contoh: 08123456789" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Alamat</label>
                            <textarea name="alamat" required rows="2" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
                            <p style="display: flex; align-items: flex-start; gap: 5px; font-size: 11.5px; color: var(--color-text-muted); margin: 6px 0 0;">
                                <svg class="icon" style="width: 13px; height: 13px; flex-shrink: 0; margin-top: 1px; color: var(--color-text-muted);"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
                                Isi dengan alamat tempat tinggal Anda saat ini.
                            </p>
                        </div>

                        <div style="margin-bottom: 0;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email (opsional)</label>
                            <input type="email" name="email" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card" style="padding: 18px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <svg class="icon icon-accent" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                        <p style="font-size: 13px; color: var(--color-text-muted);">Data Anda akan terisi otomatis dari profil member.</p>
                    </div>
                <?php endif; ?>

                <div class="card" style="padding: 24px; margin-bottom: 20px;">
                    <h3 style="font-size: 15px; margin-bottom: 12px;">Jaminan Identitas</h3>
                    <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 14px;">Unggah foto identitas aktif (KTM, KTP, atau SIM). Identitas asli wajib ditinggalkan saat pengambilan barang dan dikembalikan setelah barang dikembalikan dalam kondisi baik.</p>

                    <div class="img-upload-grid" id="grid-identitas"></div>

                    <label for="identitas" style="display: flex; flex-direction: column; align-items: center; gap: 8px; border: 1.5px dashed var(--color-border); border-radius: 8px; padding: 28px; cursor: pointer; background-color: var(--color-bg);">
                        <svg class="icon" style="width: 26px; height: 26px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                        <span id="label-file" style="font-size: 13px; color: var(--color-text-muted);">Klik untuk pilih berkas (JPG, PNG, maksimal 5MB)</span>
                    </label>
                    <input type="file" id="identitas" name="identitas" accept="image/jpeg,image/png,image/webp" required style="display: none;">
                </div>

                <div class="card" style="padding: 24px; margin-bottom: 20px;">
                    <h3 style="font-size: 15px; margin-bottom: 12px;">Catatan Tambahan</h3>
                    <textarea name="catatan" rows="2" placeholder="Contoh: Diambil atas nama Ray, mohon disiapkan lebih awal." style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Lanjut ke Review Pesanan
                    <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                </button>
            </form>

        <?php endif; ?>
    </div>
</section>

<script src="assets/js/image-uploader.js"></script>
<script>
var inputFile = document.getElementById('identitas');
if (inputFile) {
    MormsImageUploader.pasangPreview(inputFile, document.getElementById('grid-identitas'), {
        onChange: function (jumlah) {
            document.getElementById('label-file').textContent = jumlah > 0
                ? inputFile.files[0].name
                : 'Klik untuk pilih berkas (JPG, PNG, maksimal 5MB)';
        }
    });
}
</script>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>