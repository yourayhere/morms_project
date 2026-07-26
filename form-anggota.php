<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/logger.php';
require_once __DIR__ . '/app/Models/TeamModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\TeamModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$id = (int) ($_GET['id'] ?? 0);
$anggota = $id ? TeamModel::getById($id) : null;
$modeEdit = $anggota !== null;
$editingSelf = $modeEdit && $id === (int) Auth::id();

// Admin hanya boleh membuka halaman ini untuk mengedit akunnya sendiri —
// membuat anggota baru, mengedit anggota lain, atau menghapus akun tetap
// eksklusif untuk Owner.
if (Auth::role() !== 'owner' && !$editingSelf) {
    header('Location: dashboard-admin.php');
    exit;
}

// Admin Operasional boleh mengubah status akunnya sendiri (cuma Aktif/Cuti,
// lihat validasi di bawah) - Owner tetap tidak boleh mengubah status
// akunnya sendiri sama sekali.
$statusBisaDiubahSendiri = $editingSelf && $anggota['role'] === 'admin';

$errors = [];
$sukses = false;

$pesanErrorHapus = [
    'hapus_diri' => 'Anda tidak dapat menghapus akun Anda sendiri.',
    'konfirmasi' => 'Teks konfirmasi tidak sesuai. Ketik "CONFIRM" persis untuk menghapus akun ini.',
    'token'      => 'Token keamanan tidak valid. Silakan coba lagi.',
][$_GET['error'] ?? ''] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid.';
    } else {
        $nama       = clean_input($_POST['nama'] ?? '');
        $email      = clean_input($_POST['email'] ?? '');
        $noHp       = normalisasi_no_hp(clean_input($_POST['no_hp'] ?? ''));
        $role       = $editingSelf ? $anggota['role'] : ($_POST['role'] ?? '');
        $password   = $_POST['password'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';

        // Akun tidak boleh mengubah perannya sendiri — mencegah siapa pun
        // menaikkan/menurunkan perannya sendiri.
        //
        // Sistem hanya boleh punya SATU Owner. Lewat form ini, peran "owner"
        // tidak pernah bisa dipilih/diberikan ke siapa pun (baik anggota baru
        // maupun promosi anggota lain) — satu-satunya cara sebuah akun
        // berstatus owner adalah karena memang sudah begitu sejak awal (baris
        // ini sendiri), yang diizinkan lolos validasi hanya saat self-edit.
        $roleValid = $editingSelf ? [$anggota['role']] : ['admin'];

        // Status: Owner tidak boleh mengubah status akunnya sendiri sama
        // sekali (mencegah Owner tidak sengaja menonaktifkan diri sendiri
        // sampai tidak ada yang bisa login ke panel admin). Admin Operasional
        // BOLEH mengubah status akunnya sendiri, tapi cuma Aktif atau Cuti —
        // "Nonaktif" (dinonaktifkan/dipecat) tetap eksklusif keputusan Owner.
        if ($editingSelf && !$statusBisaDiubahSendiri) {
            $statusAktif = $anggota['status_aktif'];
        } else {
            $statusAktif = $_POST['status_aktif'] ?? ($editingSelf ? $anggota['status_aktif'] : 'aktif');
        }
        $statusValid = $statusBisaDiubahSendiri ? ['aktif', 'cuti'] : ['aktif', 'cuti', 'nonaktif'];

        if ($nama === '' || $noHp === '') {
            $errors[] = 'Nama dan nomor HP wajib diisi.';
        } elseif (!validasi_no_hp($noHp)) {
            $errors[] = 'Format nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.';
        } elseif ($email !== '' && !validasi_email($email)) {
            $errors[] = 'Format email tidak valid.';
        } elseif (!in_array($role, $roleValid, true)) {
            $errors[] = 'Peran tidak valid.';
        } elseif (!in_array($statusAktif, $statusValid, true)) {
            $errors[] = 'Status tidak valid.';
        } elseif (!$modeEdit && strlen($password) < 8) {
            $errors[] = 'Kata sandi minimal 8 karakter.';
        } elseif (!$modeEdit && $password !== $konfirmasi) {
            $errors[] = 'Konfirmasi kata sandi tidak sesuai.';
        } elseif ($email !== '' && TeamModel::cekEmailDuplikat($email, $id)) {
            $errors[] = 'Email sudah digunakan oleh anggota lain.';
        } elseif (TeamModel::cekHpDuplikat($noHp, $id)) {
            $errors[] = 'Nomor HP sudah digunakan oleh anggota lain.';
        } else {
            if ($modeEdit) {
                TeamModel::update($id, [
                    'nama'       => $nama,
                    'email'      => $email,
                    'no_hp'      => $noHp,
                    'role'       => $role,
                    'status_aktif' => $statusAktif,
                ]);

                if ($password !== '') {
                    if (strlen($password) < 8) {
                        $errors[] = 'Kata sandi baru minimal 8 karakter.';
                    } elseif ($password !== $konfirmasi) {
                        $errors[] = 'Konfirmasi kata sandi tidak sesuai.';
                    } else {
                        TeamModel::gantiPassword($id, $password);
                    }
                }

                if (empty($errors)) {
                    catat_aktivitas((int) Auth::id(), 'edit_anggota', 'Data anggota ' . $nama . ' diperbarui');
                    $sukses = true;
                }
            } else {
                $idBaru = TeamModel::tambah([
                    'nama'     => $nama,
                    'email'    => $email,
                    'no_hp'    => $noHp,
                    'role'     => $role,
                    'password' => $password,
                ]);
                catat_aktivitas((int) Auth::id(), 'tambah_anggota', 'Anggota baru ditambahkan: ' . $nama);
                header('Location: tim.php');
                exit;
            }
        }
    }
}

$csrfToken = generate_csrf_token();
$modeProfilSendiri = Auth::role() !== 'owner';

$judulHalaman = $modeProfilSendiri ? 'Profil Saya' : ($modeEdit ? 'Edit Anggota' : 'Tambah Anggota');
$halamanAktif = $modeProfilSendiri ? '' : 'tim';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<?php if (!$modeProfilSendiri): ?>
<a href="tim.php" class="back-link">
    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Tim
</a>
<?php endif; ?>

<div style="max-width: 560px;">
    <h1 class="admin-page-title" style="margin-bottom: 20px;"><?= $modeProfilSendiri ? 'Profil Saya' : ($modeEdit ? 'Edit Anggota' : 'Tambah Anggota Baru') ?></h1>

    <?php if ($sukses): ?>
        <div class="alert alert-success" style="margin-bottom: 16px;">
            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
            Data anggota berhasil diperbarui.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 16px;">
            <div>
                <?php foreach ($errors as $err): ?>
                    <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($pesanErrorHapus): ?>
        <div class="alert alert-danger" style="margin-bottom: 16px;">
            <?= htmlspecialchars($pesanErrorHapus) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">

        <div class="card" style="padding: 24px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 16px;">Informasi Anggota</h3>

            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-input" required value="<?= htmlspecialchars($anggota['nama'] ?? $_POST['nama'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Nomor HP</label>
                <input type="tel" name="no_hp" class="form-input" required inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" value="<?= htmlspecialchars($anggota['no_hp'] ?? $_POST['no_hp'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($anggota['email'] ?? $_POST['email'] ?? '') ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Peran</label>
                    <select name="role" class="form-select" required <?= $editingSelf ? 'disabled' : '' ?>>
                        <option value="admin" <?= ($anggota['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin Operasional</option>
                        <?php if ($editingSelf && $anggota['role'] === 'owner'): ?>
                            <option value="owner" selected>Owner</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status_aktif" class="form-select" <?= ($editingSelf && !$statusBisaDiubahSendiri) ? 'disabled' : '' ?>>
                        <option value="aktif" <?= ($anggota['status_aktif'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="cuti"  <?= ($anggota['status_aktif'] ?? '') === 'cuti' ? 'selected' : '' ?>>Cuti</option>
                        <?php if (!$statusBisaDiubahSendiri): ?>
                            <option value="nonaktif" <?= ($anggota['status_aktif'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <?php if ($statusBisaDiubahSendiri): ?>
                <p style="font-size: 11.5px; color: var(--warm-400); margin-top: 8px;">Anda tidak dapat mengubah peran akun sendiri. Status akun hanya dapat diubah menjadi Aktif atau Cuti. Status Nonaktif hanya dapat ditetapkan oleh Owner.</p>
            <?php elseif ($editingSelf): ?>
                <p style="font-size: 11.5px; color: var(--warm-400); margin-top: 8px;">Anda tidak dapat mengubah peran atau status akun Anda sendiri, demi keamanan.</p>
            <?php else: ?>
                <p style="font-size: 11.5px; color: var(--warm-400); margin-top: 8px;">Sistem hanya mengizinkan satu akun Owner. Peran "Owner" tidak dapat diberikan lewat form ini.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 24px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 4px;">Kata Sandi</h3>
            <?php if ($modeEdit): ?>
                <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 14px;">Kosongkan jika tidak ingin mengubah kata sandi.</p>
            <?php else: ?>
                <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 14px;">Minimal 8 karakter.</p>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label"><?= $modeEdit ? 'Kata Sandi Baru' : 'Kata Sandi' ?></label>
                <input type="password" name="password" class="form-input" <?= $modeEdit ? '' : 'required minlength="8"' ?>>
            </div>

            <div class="form-group">
                <label class="form-label">Konfirmasi Kata Sandi</label>
                <input type="password" name="konfirmasi_password" class="form-input" <?= $modeEdit ? '' : 'required minlength="8"' ?>>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
            <?= $modeEdit ? 'Simpan Perubahan' : 'Tambah Anggota' ?>
        </button>
    </form>

    <?php if ($modeEdit && !$editingSelf): ?>
        <div class="card" style="padding: 24px; margin-top: 28px; border-color: var(--danger-300, #EEC4C0);">
            <h3 style="font-size: 14px; margin-bottom: 4px; color: var(--color-danger, #B3432F);">Hapus Akun Anggota Tim</h3>
            <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 16px;">Menghapus akun ini bersifat permanen dan tidak dapat dibatalkan. Akun tidak akan bisa login lagi setelah dihapus.</p>

            <button type="button" id="btn-buka-hapus" class="btn btn-secondary" style="color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F);">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                Hapus Akun Ini
            </button>

            <div id="blok-konfirmasi-hapus" style="display: none; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--cream-300);">
                <p style="font-size: 13px; font-weight: 600; margin-bottom: 6px;">Are you sure?</p>
                <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 12px;">
                    Ketik <strong>CONFIRM</strong> untuk menghapus akun <strong><?= htmlspecialchars($anggota['nama']) ?></strong> secara permanen.
                </p>
                <form method="POST" action="aksi/hapus-anggota.php">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $anggota['id'] ?>">
                    <input type="text" id="input-konfirmasi-hapus" class="form-input" placeholder='Ketik "CONFIRM"' autocomplete="off" style="margin-bottom: 12px; max-width: 260px;">
                    <input type="hidden" name="konfirmasi" id="input-konfirmasi-hidden">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" id="btn-hapus-permanen" class="btn btn-primary" disabled style="background-color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F); opacity: 0.5; cursor: not-allowed;">
                            Hapus Permanen
                        </button>
                        <button type="button" id="btn-batal-hapus" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($modeEdit && !$editingSelf): ?>
<script>
var btnBukaHapus = document.getElementById('btn-buka-hapus');
var blokKonfirmasi = document.getElementById('blok-konfirmasi-hapus');
var inputKonfirmasi = document.getElementById('input-konfirmasi-hapus');
var inputKonfirmasiHidden = document.getElementById('input-konfirmasi-hidden');
var btnHapusPermanen = document.getElementById('btn-hapus-permanen');
var btnBatalHapus = document.getElementById('btn-batal-hapus');

btnBukaHapus.addEventListener('click', function () {
    btnBukaHapus.style.display = 'none';
    blokKonfirmasi.style.display = 'block';
    inputKonfirmasi.focus();
});

btnBatalHapus.addEventListener('click', function () {
    blokKonfirmasi.style.display = 'none';
    btnBukaHapus.style.display = 'inline-flex';
    inputKonfirmasi.value = '';
    perbaruiTombolHapus();
});

function perbaruiTombolHapus() {
    var cocok = inputKonfirmasi.value === 'CONFIRM';
    inputKonfirmasiHidden.value = inputKonfirmasi.value;
    btnHapusPermanen.disabled = !cocok;
    btnHapusPermanen.style.opacity = cocok ? '1' : '0.5';
    btnHapusPermanen.style.cursor = cocok ? 'pointer' : 'not-allowed';
}

inputKonfirmasi.addEventListener('input', perbaruiTombolHapus);
</script>
<?php endif; ?>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>