<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/CustomerModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\CustomerModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$kataKunci = clean_input($_GET['cari'] ?? '');
$daftarPelanggan = CustomerModel::getSemuaPelanggan($kataKunci);
$csrfToken = generate_csrf_token();

$pesanSukses = [
    'hapus'       => 'Akun member berhasil dihapus.',
    'hapus_tamu'  => 'Data tamu berhasil dihapus.',
    'tambah'      => 'Member baru berhasil ditambahkan.',
][$_GET['sukses'] ?? ''] ?? null;

$pesanError = [
    'token'               => 'Token keamanan tidak valid. Silakan coba lagi.',
    'tidak_ditemukan'     => 'Akun member tidak ditemukan.',
    'data'                => 'Nama, nomor HP, alamat, dan kata sandi wajib diisi.',
    'password_pendek'     => 'Kata sandi minimal 8 karakter.',
    'konfirmasi_password' => 'Konfirmasi kata sandi tidak sesuai.',
    'format_hp'           => 'Format nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.',
    'format_email'        => 'Format email tidak valid.',
    'hp_duplikat'         => 'Nomor HP sudah digunakan oleh akun lain.',
    'email_duplikat'      => 'Email sudah digunakan oleh akun lain.',
][$_GET['error'] ?? ''] ?? null;

$formTambahTerbuka = ($_GET['form'] ?? '') === 'tambah';

$judulHalaman = 'Pelanggan';
$halamanAktif = 'pelanggan';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<div class="admin-page-header-row">
    <div>
        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Manajemen Pelanggan</h1>
        <p style="color: var(--color-text-muted); font-size: 13px;">Daftar member dan tamu yang pernah melakukan reservasi.</p>
    </div>
    <button type="button" id="btn-toggle-tambah-member" class="btn btn-primary btn-sm">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
        Tambah Member Baru
    </button>
</div>

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

<div class="card" id="card-tambah-member" style="padding: 22px; margin-bottom: 20px; display: <?= $formTambahTerbuka ? 'block' : 'none' ?>;">
    <h3 style="font-size: 14px; margin-bottom: 4px;">Tambah Member Baru</h3>
    <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px;">Sama seperti pendaftaran member lewat halaman web - lengkapi data di bawah ini.</p>

    <form method="POST" action="aksi/tambah-member-admin.php">
        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-input" required>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label">Nomor HP</label>
                <input type="tel" name="no_hp" class="form-input" required inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" placeholder="08123456789">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Alamat</label>
            <textarea name="alamat" class="form-textarea" rows="2" required placeholder="Alamat domisili tempat tinggal saat ini"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Email <span style="font-weight: 400; color: var(--warm-400);">(opsional)</span></label>
            <input type="email" name="email" class="form-input">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 4px;">
            <div class="form-group">
                <label class="form-label">Kata Sandi</label>
                <input type="password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label">Konfirmasi Kata Sandi</label>
                <input type="password" name="konfirmasi_password" class="form-input" required minlength="8" autocomplete="new-password">
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                Simpan Member Baru
            </button>
            <button type="button" id="btn-batal-tambah-member" class="btn btn-secondary">Batal</button>
        </div>
    </form>
</div>

<form method="GET" style="margin-bottom: 20px;">
    <div style="position: relative; max-width: 380px;">
        <svg class="icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 17px; height: 17px; color: var(--color-text-muted);"><use href="assets/icons/sprite.svg#icon-search"></use></svg>
        <input type="text" name="cari" value="<?= clean_input($kataKunci) ?>" placeholder="Cari nama atau nomor HP..."
            style="width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
    </div>
</form>

<?php if (empty($daftarPelanggan)): ?>
    <div class="card empty-state">
        <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
        <p class="empty-state-title">Belum ada pelanggan</p>
        <p class="empty-state-text"><?= $kataKunci !== '' ? 'Tidak ada hasil untuk pencarian ini.' : 'Member dan tamu yang pernah bertransaksi akan muncul di sini.' ?></p>
    </div>
<?php else: ?>
<div class="table-wrapper">
    <table class="table" style="min-width: 640px;">
        <thead>
            <tr>
                <th>Nama</th>
                <th>No. HP</th>
                <th>Tipe</th>
                <th>Bergabung</th>
                <th>Transaksi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daftarPelanggan as $p): ?>
                <tr>
                    <td style="font-weight: 600;"><?= htmlspecialchars($p['nama_member'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['hp_member'] ?? '-') ?></td>
                    <td>
                        <span class="badge <?= $p['tipe'] === 'member' ? 'badge-accent' : 'badge-neutral' ?>">
                            <?= $p['tipe'] === 'member' ? 'Member' : 'Tamu' ?>
                        </span>
                    </td>
                    <td style="white-space: nowrap;">
                        <?php if ($p['tipe'] === 'member' && !empty($p['created_at'])): ?>
                            <?= format_tanggal_indo($p['created_at']) ?>
                        <?php else: ?>
                            <span style="color: var(--color-text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $p['jumlah_transaksi'] ?> kali</td>
                    <td>
                        <?php if ($p['tipe'] === 'member'): ?>
                            <span class="badge <?= $p['status_blacklist'] === 'aktif' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $p['status_blacklist'] === 'aktif' ? 'Aktif' : 'Diblokir' ?>
                            </span>
                        <?php else: ?>
                            <span style="font-size: 11.5px; color: var(--color-text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="detail-pelanggan.php?hp=<?= urlencode($p['hp_member']) ?>&user_id=<?= $p['user_id'] ?? '' ?>" style="color: var(--color-accent); font-weight: 600; font-size: 12.5px;">Lihat</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
var kartuTambahMember = document.getElementById('card-tambah-member');
var btnToggleTambahMember = document.getElementById('btn-toggle-tambah-member');
var btnBatalTambahMember = document.getElementById('btn-batal-tambah-member');

btnToggleTambahMember.addEventListener('click', function () {
    var terbuka = kartuTambahMember.style.display !== 'none';
    kartuTambahMember.style.display = terbuka ? 'none' : 'block';
    if (!terbuka) kartuTambahMember.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
});

btnBatalTambahMember.addEventListener('click', function () {
    kartuTambahMember.style.display = 'none';
});
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>