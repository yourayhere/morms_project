<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/CartKasir.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\CartKasir;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$keranjang = CartKasir::getAll();

if (empty($keranjang)) {
    header('Location: kasir.php');
    exit;
}

$totalSewa = CartKasir::getTotal();
$dpMinimal = $totalSewa * 0.5;
$csrfToken = generate_csrf_token();

$pesanError = [
    'data'      => 'Nama dan nomor HP penyewa wajib diisi.',
    'identitas' => 'Berkas identitas wajib diunggah dengan format dan ukuran yang sesuai.',
    'token'     => 'Sesi Anda sudah kedaluwarsa. Silakan coba lagi.',
    'sistem'    => 'Terjadi kendala sistem. Silakan coba lagi.',
    'stok'      => 'Stok salah satu barang di keranjang baru saja habis. Silakan periksa kembali keranjang.',
    'format'    => 'Format nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.',
    'ukuran'    => 'Salah satu barang di keranjang belum memiliki ukuran yang dipilih. Hapus barang tersebut lalu tambahkan kembali dengan memilih ukurannya.',
];
$errorCode = $_GET['error'] ?? '';

$judulHalaman = 'Checkout Kasir';
$halamanAktif = 'kasir';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<a href="kasir.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Kasir
</a>

<div style="max-width: 600px;">
    <h1 style="font-size: 20px; color: var(--color-primary-dark); margin-bottom: 18px;">Data Penyewa dan Pembayaran</h1>

    <?php if ($errorCode !== '' && isset($pesanError[$errorCode])): ?>
        <div class="card" style="padding: 14px 16px; margin-bottom: 18px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px;">
            <?= htmlspecialchars($pesanError[$errorCode]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="aksi/proses-kasir.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
        <input type="hidden" name="total_sewa" value="<?= $totalSewa ?>">
        <input type="hidden" name="dp_minimal" value="<?= $dpMinimal ?>">

        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Data Penyewa</h3>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Nama Lengkap</label>
                <input type="text" name="nama" id="input-nama-kasir" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Nomor HP</label>
                <input type="tel" name="hp" id="input-hp-kasir" required inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" placeholder="Contoh: 08123456789" autocomplete="off" style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                <input type="hidden" name="member_user_id" id="input-member-user-id" value="">

                <div id="saran-member" style="display: none; margin-top: 10px; padding: 12px 14px; border: 1.5px solid var(--color-accent); background-color: #FBF1EC; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <svg class="icon" style="width: 16px; height: 16px; color: var(--color-accent-dark); flex-shrink: 0;"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                        <p style="font-size: 12.5px; color: var(--color-text);">Nomor ini terdaftar atas nama <strong id="saran-member-nama"></strong> (Member)</p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" id="btn-pakai-member" class="btn btn-primary btn-xs">Gunakan data member ini</button>
                        <button type="button" id="btn-bukan-member" class="btn btn-secondary btn-xs">Bukan orangnya, lanjut sebagai tamu</button>
                    </div>
                </div>

                <div id="member-tertaut" style="display: none; margin-top: 10px; padding: 10px 14px; border: 1.5px solid var(--color-success); background-color: var(--success-100); border-radius: 8px; align-items: center; justify-content: space-between; gap: 8px;">
                    <p style="font-size: 12.5px; color: var(--color-text); display: flex; align-items: center; gap: 8px;">
                        <svg class="icon" style="width: 16px; height: 16px; color: var(--color-success); flex-shrink: 0;"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                        Booking ini akan tertaut ke akun member <strong id="member-tertaut-nama"></strong>
                    </p>
                    <button type="button" id="btn-lepas-member" class="btn btn-ghost btn-xs">Ubah</button>
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Alamat (opsional)</label>
                <textarea name="alamat" id="input-alamat-kasir" rows="2" style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
                <p style="display: flex; align-items: flex-start; gap: 5px; font-size: 11px; color: var(--color-text-muted); margin: 6px 0 0;">
                    <svg class="icon" style="width: 12px; height: 12px; flex-shrink: 0; margin-top: 1px; color: var(--color-text-muted);"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
                    Isi dengan alamat tempat tinggal customer saat ini.
                </p>
            </div>
        </div>

        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Jaminan Identitas</h3>
            <div class="img-upload-grid" id="grid-identitas-kasir"></div>

            <label for="identitas" id="label-upload-identitas-kasir" style="display: flex; flex-direction: column; align-items: center; gap: 8px; border: 1.5px dashed var(--color-border); border-radius: 8px; padding: 22px; cursor: pointer; background-color: var(--color-bg);">
                <svg class="icon" style="width: 22px; height: 22px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                <span id="label-identitas-kasir" style="font-size: 12.5px; color: var(--color-text-muted);">Klik untuk unggah foto KTM/KTP/SIM</span>
            </label>
            <input type="file" id="identitas" name="identitas" accept="image/jpeg,image/png,image/webp" required style="display: none;">

            <label style="display: flex; align-items: flex-start; gap: 8px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--color-border); cursor: pointer;">
                <input type="checkbox" id="lewati-identitas" name="lewati_identitas" value="1" style="margin-top: 2px;">
                <span style="font-size: 12.5px; color: var(--color-text);">
                    Lewati untuk saat ini, lengkapi kemudian.
                    <span style="display: block; font-size: 11px; color: var(--color-text-muted); margin-top: 2px;">Gunakan opsi ini hanya bila pengambilan foto identitas tidak memungkinkan saat transaksi berlangsung.</span>
                </span>
            </label>
        </div>

        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Skema Pembayaran</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 14px;">
                <label style="flex: 1; border: 1.5px solid var(--color-border); border-radius: 8px; padding: 12px; text-align: center; cursor: pointer;" class="opsi-skema">
                    <input type="radio" name="skema" value="dp" checked style="margin-bottom: 6px;">
                    <div style="font-size: 12.5px; font-weight: 600;">DP 50%</div>
                    <div style="font-size: 11.5px; color: var(--color-text-muted);"><?= format_rupiah($dpMinimal) ?></div>
                </label>
                <label style="flex: 1; border: 1.5px solid var(--color-border); border-radius: 8px; padding: 12px; text-align: center; cursor: pointer;" class="opsi-skema">
                    <input type="radio" name="skema" value="lunas" style="margin-bottom: 6px;">
                    <div style="font-size: 12.5px; font-weight: 600;">Lunas</div>
                    <div style="font-size: 11.5px; color: var(--color-text-muted);"><?= format_rupiah($totalSewa) ?></div>
                </label>
            </div>

            <div style="display: flex; gap: 10px;">
                <label style="flex: 1; border: 1.5px solid var(--color-border); border-radius: 8px; padding: 12px; text-align: center; cursor: pointer;" class="opsi-metode">
                    <input type="radio" name="metode" value="cash" checked style="margin-bottom: 6px;">
                    <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-cash"></use></svg>
                    <div style="font-size: 12.5px; font-weight: 600;">Tunai</div>
                </label>
                <label style="flex: 1; border: 1.5px solid var(--color-border); border-radius: 8px; padding: 12px; text-align: center; cursor: pointer;" class="opsi-metode">
                    <input type="radio" name="metode" value="qris" style="margin-bottom: 6px;">
                    <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-qris"></use></svg>
                    <div style="font-size: 12.5px; font-weight: 600;">QRIS</div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
            <svg class="icon" style="width: 17px; height: 17px;"><use href="assets/icons/sprite.svg#icon-receipt"></use></svg>
            Cetak Invoice dan Selesaikan
        </button>
    </form>
</div>

<style>
.opsi-skema:has(input:checked), .opsi-metode:has(input:checked) {
    border-color: var(--color-accent);
    background-color: #FBF1EC;
}
</style>

<script src="assets/js/image-uploader.js"></script>
<script>
var inputIdentitasKasir = document.getElementById('identitas');
var previewIdentitasKasir = MormsImageUploader.pasangPreview(
    inputIdentitasKasir,
    document.getElementById('grid-identitas-kasir'),
    {
        onChange: function (jumlah) {
            document.getElementById('label-identitas-kasir').textContent = jumlah > 0
                ? inputIdentitasKasir.files[0].name
                : 'Klik untuk unggah foto KTM/KTP/SIM';
        }
    }
);

(function () {
    var checkboxLewati = document.getElementById('lewati-identitas');
    var labelUploadIdentitas = document.getElementById('label-upload-identitas-kasir');

    checkboxLewati.addEventListener('change', function () {
        var dilewati = checkboxLewati.checked;
        inputIdentitasKasir.required = !dilewati;
        inputIdentitasKasir.disabled = dilewati;
        labelUploadIdentitas.style.opacity = dilewati ? '0.5' : '1';
        labelUploadIdentitas.style.pointerEvents = dilewati ? 'none' : 'auto';

        if (dilewati && inputIdentitasKasir.files.length > 0) {
            inputIdentitasKasir.value = '';
            previewIdentitasKasir.render();
        }
    });
})();

(function () {
    var inputHp = document.getElementById('input-hp-kasir');
    var inputNama = document.getElementById('input-nama-kasir');
    var inputAlamat = document.getElementById('input-alamat-kasir');
    var inputMemberUserId = document.getElementById('input-member-user-id');
    var kotakSaran = document.getElementById('saran-member');
    var namaSaran = document.getElementById('saran-member-nama');
    var kotakTertaut = document.getElementById('member-tertaut');
    var namaTertaut = document.getElementById('member-tertaut-nama');
    var btnPakaiMember = document.getElementById('btn-pakai-member');
    var btnBukanMember = document.getElementById('btn-bukan-member');
    var btnLepasMember = document.getElementById('btn-lepas-member');

    var timerDebounce = null;
    var hpDitolakUntuk = null; // nomor yg sudah dijawab "bukan orangnya", jangan tanya lagi sampai nomornya berubah

    function sembunyikanSemua() {
        kotakSaran.style.display = 'none';
        kotakTertaut.style.display = 'none';
    }

    function lepaskanTautanMember() {
        inputMemberUserId.value = '';
        kotakTertaut.style.display = 'none';
    }

    inputHp.addEventListener('input', function () {
        lepaskanTautanMember();
        kotakSaran.style.display = 'none';

        var hp = inputHp.value.trim();
        if (hp !== hpDitolakUntuk) {
            hpDitolakUntuk = null;
        }

        clearTimeout(timerDebounce);
        if (!inputHp.checkValidity() || hp.length < 9 || hp === hpDitolakUntuk) {
            return;
        }

        timerDebounce = setTimeout(function () {
            fetch('aksi/cek-member-by-hp.php?hp=' + encodeURIComponent(hp))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (inputHp.value.trim() !== hp) return; // nomor sudah berubah lagi saat fetch berjalan
                    if (data.ditemukan) {
                        namaSaran.textContent = data.nama;
                        kotakSaran.dataset.userId = data.user_id;
                        kotakSaran.dataset.nama = data.nama;
                        kotakSaran.dataset.alamat = data.alamat || '';
                        kotakSaran.style.display = 'block';
                    }
                })
                .catch(function () { /* diamkan - saran ini cuma pelengkap, bukan wajib */ });
        }, 450);
    });

    btnPakaiMember.addEventListener('click', function () {
        inputMemberUserId.value = kotakSaran.dataset.userId;
        // "Gunakan data member ini" = otomatis isikan data member ke form,
        // bukan cuma menautkan id-nya - kalau tidak, kasir masih harus ketik
        // ulang nama/alamat manual dan struknya bisa salah/kosong.
        inputNama.value = kotakSaran.dataset.nama;
        if (kotakSaran.dataset.alamat) {
            inputAlamat.value = kotakSaran.dataset.alamat;
        }
        namaTertaut.textContent = kotakSaran.dataset.nama;
        kotakSaran.style.display = 'none';
        kotakTertaut.style.display = 'flex';
    });

    btnBukanMember.addEventListener('click', function () {
        hpDitolakUntuk = inputHp.value.trim();
        kotakSaran.style.display = 'none';
    });

    btnLepasMember.addEventListener('click', function () {
        hpDitolakUntuk = inputHp.value.trim();
        lepaskanTautanMember();
    });
})();
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>