<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Helpers/logger.php';
require_once __DIR__ . '/app/Models/CustomerModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\CustomerModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$hp = clean_input($_GET['hp'] ?? '');
$userId = (int) ($_GET['user_id'] ?? 0);

if ($hp === '') {
    header('Location: pelanggan.php');
    exit;
}

$riwayatTransaksi = CustomerModel::getRiwayatTransaksiByHp($hp);
$catatanInternal = CustomerModel::getCatatanInternal($hp);
$statusAkun = $userId > 0 ? CustomerModel::getStatusAkun($userId) : null;
$memberData = $userId > 0 ? CustomerModel::getMemberById($userId) : null;
$jumlahBooking = $userId > 0 ? CustomerModel::getJumlahBooking($userId) : 0;
$namaTamu = $userId <= 0 && !empty($riwayatTransaksi) ? ($riwayatTransaksi[0]['guest_nama'] ?? '') : '';
$csrfToken = generate_csrf_token();

$pesanSukses = null;
if (($_GET['sukses'] ?? '') === 'password') {
    $pesanSukses = 'Kata sandi member berhasil diganti.';
} elseif (isset($_GET['sukses'])) {
    $pesanSukses = 'Data pelanggan berhasil diperbarui.';
}

$pesanError = [
    'token'               => 'Token keamanan tidak valid. Silakan coba lagi.',
    'data'                => 'Nama dan nomor HP wajib diisi.',
    'format_hp'           => 'Format nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.',
    'format_email'        => 'Format email tidak valid.',
    'hp_duplikat'         => 'Nomor HP sudah digunakan oleh akun lain.',
    'email_duplikat'      => 'Email sudah digunakan oleh akun lain.',
    'konfirmasi'          => 'Teks konfirmasi tidak sesuai. Ketik "CONFIRM" persis untuk menghapus akun ini.',
    'password_pendek'     => 'Kata sandi baru minimal 8 karakter.',
    'konfirmasi_password' => 'Konfirmasi kata sandi baru tidak sesuai.',
][$_GET['error'] ?? ''] ?? null;

$judulHalaman = 'Detail Pelanggan';
$halamanAktif = 'pelanggan';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<a href="pelanggan.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Pelanggan
</a>

<?php if ($pesanSukses): ?>
    <div class="alert alert-success" style="margin-bottom: 16px;">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
        <?= htmlspecialchars($pesanSukses) ?>
    </div>
<?php endif; ?>

<?php if ($pesanError): ?>
    <div class="alert alert-danger" style="margin-bottom: 16px;">
        <?= htmlspecialchars($pesanError) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px;">

    <div>
        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Riwayat Transaksi (<?= count($riwayatTransaksi) ?>)</h3>
            <?php if (empty($riwayatTransaksi)): ?>
                <p style="font-size: 13px; color: var(--color-text-muted);">Belum ada riwayat transaksi.</p>
            <?php else: ?>
                <?php foreach ($riwayatTransaksi as $r): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--color-border);">
                        <div>
                            <p style="font-size: 13px; font-weight: 600;"><?= htmlspecialchars($r['kode_booking']) ?></p>
                            <p style="font-size: 11.5px; color: var(--color-text-muted);"><?= format_tanggal_indo($r['tanggal_ambil']) ?> hingga <?= format_tanggal_indo($r['tanggal_kembali']) ?></p>
                        </div>
                        <span style="font-size: 11.5px; background-color: #FBF1EC; color: var(--color-accent-dark); padding: 4px 10px; border-radius: 20px;"><?= label_status_booking($r['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 22px;">
            <h3 style="font-size: 14px; margin-bottom: 14px; display: flex; align-items: center; gap: 7px;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-note"></use></svg>
                Catatan Internal Admin
            </h3>

            <form method="POST" action="aksi/simpan-catatan-pelanggan.php" style="margin-bottom: 16px;">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <textarea name="isi" rows="2" placeholder="Tulis catatan internal tentang pelanggan ini..." required
                    style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; resize: vertical; margin-bottom: 8px;"></textarea>
                <button type="submit" class="btn btn-secondary" style="padding: 7px 16px;">Simpan Catatan</button>
            </form>

            <?php if (empty($catatanInternal)): ?>
                <p style="font-size: 12.5px; color: var(--color-text-muted);">Belum ada catatan.</p>
            <?php else: ?>
                <?php foreach ($catatanInternal as $catatan): ?>
                    <div id="catatan-view-<?= $catatan['id'] ?>" style="padding: 10px 0; border-bottom: 1px solid var(--color-border);">
                        <p style="font-size: 12.5px; white-space: pre-wrap;"><?= htmlspecialchars($catatan['isi']) ?></p>
                        <p style="font-size: 11px; color: var(--color-text-muted); margin-top: 3px;">
                            <?= date('d/m/Y H:i', strtotime($catatan['created_at'])) ?>
                            <?php if (!empty($catatan['nama_admin'])): ?> &middot; oleh <?= htmlspecialchars($catatan['nama_admin']) ?><?php endif; ?>
                            <?php if ($catatan['updated_at'] !== $catatan['created_at']): ?> &middot; diedit<?php endif; ?>
                        </p>
                        <div style="display: flex; gap: 12px; margin-top: 5px;">
                            <button type="button"
                                onclick="document.getElementById('catatan-view-<?= $catatan['id'] ?>').style.display='none'; document.getElementById('catatan-edit-<?= $catatan['id'] ?>').style.display='block';"
                                style="background: none; border: none; color: var(--color-accent); font-size: 11px; font-weight: 600; cursor: pointer; padding: 0;">Edit</button>
                            <form method="POST" action="aksi/hapus-catatan-pelanggan.php" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus catatan ini?');">
                                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                                <input type="hidden" name="catatan_id" value="<?= $catatan['id'] ?>">
                                <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">
                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                <button type="submit" style="background: none; border: none; color: var(--danger-500); font-size: 11px; font-weight: 600; cursor: pointer; padding: 0;">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <div id="catatan-edit-<?= $catatan['id'] ?>" style="display: none; padding: 10px 0; border-bottom: 1px solid var(--color-border);">
                        <form method="POST" action="aksi/simpan-catatan-pelanggan.php">
                            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                            <input type="hidden" name="catatan_id" value="<?= $catatan['id'] ?>">
                            <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            <textarea name="isi" rows="2" required
                                style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; resize: vertical; margin-bottom: 8px;"><?= htmlspecialchars($catatan['isi']) ?></textarea>
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" class="btn btn-secondary" style="padding: 6px 14px; font-size: 12px;">Simpan</button>
                                <button type="button"
                                    onclick="document.getElementById('catatan-edit-<?= $catatan['id'] ?>').style.display='none'; document.getElementById('catatan-view-<?= $catatan['id'] ?>').style.display='block';"
                                    style="background: none; border: 1px solid var(--color-border); border-radius: 6px; padding: 6px 14px; font-size: 12px; cursor: pointer;">Batal</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if ($userId > 0):
            $sedangAktif = $statusAkun === 'aktif';
            $warnaAksi = $sedangAktif ? 'var(--danger-500)' : 'var(--success-500)';
            $labelAksi = $sedangAktif ? 'Blokir Akun Ini' : 'Buka Blokir Akun Ini';
            $pesanConfirm = $sedangAktif
                ? 'Yakin ingin memblokir akun ini? Member tidak akan bisa login atau membuat reservasi baru selama diblokir.'
                : 'Yakin ingin membuka blokir akun ini?';
        ?>
        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Edit Informasi Pelanggan</h3>
            <form method="POST" action="aksi/edit-pelanggan.php">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-input" required value="<?= htmlspecialchars($memberData['nama'] ?? '') ?>"
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Nomor HP</label>
                    <input type="tel" name="no_hp" class="form-input" required inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" value="<?= htmlspecialchars($memberData['no_hp'] ?? '') ?>"
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Email (opsional)</label>
                    <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($memberData['email'] ?? '') ?>"
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>

                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                    Simpan Perubahan
                </button>
            </form>
        </div>

        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 4px;">Reset Kata Sandi</h3>
            <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 14px; line-height: 1.6;">Gunakan ini kalau member menghubungi lewat "Lupa kata sandi?" dan minta dibantu reset. Kata sandi lama otomatis tidak berlaku lagi begitu diganti.</p>
            <form method="POST" action="aksi/reset-password-pelanggan.php" onsubmit="return confirm('Yakin ingin mengganti kata sandi member ini? Kata sandi lamanya tidak akan bisa dipakai lagi.');">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Kata Sandi Baru</label>
                    <input type="password" name="password_baru" class="form-input" required minlength="8" autocomplete="new-password"
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" name="konfirmasi_password" class="form-input" required minlength="8" autocomplete="new-password"
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>

                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                    Ganti Kata Sandi
                </button>
            </form>
        </div>

        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Status Akun Member</h3>
            <?php if (!empty($memberData['created_at'])): ?>
                <p style="font-size: 12.5px; color: var(--color-text-muted); margin-bottom: 12px;">
                    Member sejak <?= format_tanggal_indo($memberData['created_at']) ?> - sudah bergabung selama <?= format_lama_bergabung($memberData['created_at']) ?>.
                </p>
            <?php endif; ?>
            <p style="font-size: 13px; margin-bottom: 14px;">
                Status saat ini:
                <strong style="color: <?= $sedangAktif ? 'var(--success-500)' : 'var(--danger-500)' ?>;">
                    <?= $sedangAktif ? 'Aktif' : 'Diblokir' ?>
                </strong>
            </p>
            <form method="POST" action="aksi/toggle-blacklist.php" onsubmit="return confirm(<?= htmlspecialchars(json_encode($pesanConfirm), ENT_QUOTES) ?>);">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">
                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center; color: <?= $warnaAksi ?>; border-color: <?= $warnaAksi ?>;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-block"></use></svg>
                    <?= $labelAksi ?>
                </button>
            </form>
        </div>

        <div class="card" style="padding: 22px; border-color: var(--danger-300, #EEC4C0);">
            <h3 style="font-size: 14px; margin-bottom: 4px; color: var(--color-danger, #B3432F);">Hapus Akun Pelanggan</h3>
            <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px;">Menghapus akun ini bersifat permanen. Member tidak akan bisa login lagi. Riwayat transaksi tetap tersimpan (nama &amp; kontak disalin ke data pemesan) demi keutuhan laporan.</p>

            <button type="button" id="btn-buka-hapus-pelanggan" class="btn btn-secondary" style="width: 100%; justify-content: center; color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F);">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                Hapus Akun Ini
            </button>

            <div id="blok-konfirmasi-hapus-pelanggan" style="display: none; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--color-border);">
                <p style="font-size: 13px; font-weight: 600; margin-bottom: 6px;">Are you sure?</p>
                <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 12px;">
                    Ketik <strong>CONFIRM</strong> untuk menghapus akun <strong><?= htmlspecialchars($memberData['nama'] ?? '') ?></strong> secara permanen.
                </p>
                <form method="POST" action="aksi/hapus-pelanggan.php">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">
                    <input type="text" id="input-konfirmasi-hapus-pelanggan" class="form-input" placeholder='Ketik "CONFIRM"' autocomplete="off" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; margin-bottom: 12px;">
                    <input type="hidden" name="konfirmasi" id="input-konfirmasi-hidden-pelanggan">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" id="btn-hapus-permanen-pelanggan" class="btn btn-primary" disabled style="background-color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F); opacity: 0.5; cursor: not-allowed;">
                            Hapus Permanen
                        </button>
                        <button type="button" id="btn-batal-hapus-pelanggan" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <p style="font-size: 12.5px; color: var(--color-text-muted);">Pelanggan ini melakukan reservasi sebagai tamu (tanpa akun), sehingga tidak punya akun yang bisa diedit atau diblokir. Gunakan catatan internal untuk menandai pelanggan bermasalah.</p>
        </div>

        <div class="card" style="padding: 22px; border-color: var(--danger-300, #EEC4C0);">
            <h3 style="font-size: 14px; margin-bottom: 4px; color: var(--color-danger, #B3432F);">Hapus Data Tamu</h3>
            <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px;">Menghapus data tamu ini bersifat permanen - nama dan kontaknya akan dianonimkan dari daftar Pelanggan. Riwayat transaksi tetap tersimpan demi keutuhan laporan. Tindakan ini selalu tercatat di log aktivitas.</p>

            <form method="POST" action="aksi/hapus-tamu.php" onsubmit="return confirm('Yakin ingin menghapus data tamu ini? Nama dan kontaknya akan dianonimkan secara permanen.');">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="hp" value="<?= clean_input($hp) ?>">
                <input type="hidden" name="nama" value="<?= clean_input($namaTamu) ?>">
                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center; color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F);">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                    Hapus Data Tamu Ini
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($userId > 0): ?>
<script>
var btnBukaHapusPelanggan = document.getElementById('btn-buka-hapus-pelanggan');
var blokKonfirmasiPelanggan = document.getElementById('blok-konfirmasi-hapus-pelanggan');
var inputKonfirmasiPelanggan = document.getElementById('input-konfirmasi-hapus-pelanggan');
var inputKonfirmasiHiddenPelanggan = document.getElementById('input-konfirmasi-hidden-pelanggan');
var btnHapusPermanenPelanggan = document.getElementById('btn-hapus-permanen-pelanggan');
var btnBatalHapusPelanggan = document.getElementById('btn-batal-hapus-pelanggan');

btnBukaHapusPelanggan.addEventListener('click', function () {
    btnBukaHapusPelanggan.style.display = 'none';
    blokKonfirmasiPelanggan.style.display = 'block';
    inputKonfirmasiPelanggan.focus();
});

btnBatalHapusPelanggan.addEventListener('click', function () {
    blokKonfirmasiPelanggan.style.display = 'none';
    btnBukaHapusPelanggan.style.display = 'inline-flex';
    inputKonfirmasiPelanggan.value = '';
    perbaruiTombolHapusPelanggan();
});

function perbaruiTombolHapusPelanggan() {
    var cocok = inputKonfirmasiPelanggan.value === 'CONFIRM';
    inputKonfirmasiHiddenPelanggan.value = inputKonfirmasiPelanggan.value;
    btnHapusPermanenPelanggan.disabled = !cocok;
    btnHapusPermanenPelanggan.style.opacity = cocok ? '1' : '0.5';
    btnHapusPermanenPelanggan.style.cursor = cocok ? 'pointer' : 'not-allowed';
}

inputKonfirmasiPelanggan.addEventListener('input', perbaruiTombolHapusPelanggan);
</script>
<?php endif; ?>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>