<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/logger.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TeamModel.php';
require_once __DIR__ . '/app/Models/SettingModel.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TeamModel;
use App\Models\SettingModel;

Session::start();
Auth::requireRole(['member'], 'login.php');
Auth::requireActiveMember('login.php');

// Jaring pengaman yang sama seperti di dashboard-admin.php: booking yang
// belum dibayar lewat 60 menit ditandai EXPIRED opportunistically di sini
// juga, supaya member yang belum tentu ada admin memantau dashboard tidak
// melihat "Menunggu Pembayaran" + tombol Lanjutkan untuk reservasi yang
// sebenarnya sudah kedaluwarsa.
BookingModel::expireBookingBelumDibayar(60);

$userId = (int) Auth::id();
$db = Database::getConnection();

$profilErrors = [];
$profilSukses = false;
$passwordErrors = [];
$passwordSukses = false;
$hapusAkunErrors = [];
$tabAktif = 'profil';

if (($_GET['error'] ?? '') === 'konfirmasi') {
    $tabAktif = 'keamanan';
    $hapusAkunErrors[] = 'Konfirmasi tidak sesuai. Ketik "CONFIRM" persis untuk menghapus akun.';
} elseif (($_GET['error'] ?? '') === 'token') {
    $tabAktif = 'keamanan';
    $hapusAkunErrors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
}

$aksiPost = $_POST['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksiPost === 'ubah_profil') {
    $tabAktif = 'profil';
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $profilErrors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $nama = clean_input($_POST['nama'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $noHpBaru = normalisasi_no_hp(clean_input($_POST['no_hp'] ?? ''));
        $alamat = clean_input($_POST['alamat'] ?? '');

        $stmtNoHpSaatIni = $db->prepare('SELECT no_hp FROM users WHERE id = :id');
        $stmtNoHpSaatIni->execute(['id' => $userId]);
        $noHpSaatIni = (string) $stmtNoHpSaatIni->fetchColumn();
        $noHpBerubah = $noHpBaru !== $noHpSaatIni;

        if ($nama === '') {
            $profilErrors[] = 'Nama lengkap wajib diisi.';
        } elseif ($noHpBaru === '') {
            $profilErrors[] = 'Nomor HP wajib diisi.';
        } elseif (!validasi_no_hp($noHpBaru)) {
            $profilErrors[] = 'Format nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.';
        } elseif ($noHpBerubah && TeamModel::cekHpDuplikat($noHpBaru, $userId)) {
            $profilErrors[] = 'Nomor HP sudah digunakan oleh akun lain.';
        } elseif ($email !== '' && !validasi_email($email)) {
            $profilErrors[] = 'Format email tidak valid.';
        } elseif ($email !== '' && TeamModel::cekEmailDuplikat($email, $userId)) {
            $profilErrors[] = 'Email sudah digunakan oleh akun lain.';
        } else {
            if ($noHpBerubah) {
                // Nomor lama disimpan supaya booking yang masih berjalan tetap
                // bisa dilacak pakai nomor lama sampai booking itu selesai —
                // lihat BookingModel::getByKodeDanHp().
                $stmt = $db->prepare('UPDATE users SET nama = :nama, email = :email, no_hp = :no_hp, no_hp_lama = :no_hp_lama, alamat = :alamat WHERE id = :id');
                $stmt->execute([
                    'nama'       => $nama,
                    'email'      => $email !== '' ? $email : null,
                    'no_hp'      => $noHpBaru,
                    'no_hp_lama' => $noHpSaatIni !== '' ? $noHpSaatIni : null,
                    'alamat'     => $alamat !== '' ? $alamat : null,
                    'id'         => $userId,
                ]);
            } else {
                $stmt = $db->prepare('UPDATE users SET nama = :nama, email = :email, alamat = :alamat WHERE id = :id');
                $stmt->execute([
                    'nama'   => $nama,
                    'email'  => $email !== '' ? $email : null,
                    'alamat' => $alamat !== '' ? $alamat : null,
                    'id'     => $userId,
                ]);
            }
            Session::set('user_nama', $nama);
            catat_aktivitas($userId, 'ubah_profil_sendiri', 'Member memperbarui profil akun sendiri');
            $profilSukses = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksiPost === 'ubah_password') {
    $tabAktif = 'keamanan';
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $passwordErrors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $passwordBaru = $_POST['password_baru'] ?? '';
        $konfirmasiPassword = $_POST['konfirmasi_password'] ?? '';

        if (strlen($passwordBaru) < 8) {
            $passwordErrors[] = 'Kata sandi baru minimal 8 karakter.';
        } elseif ($passwordBaru !== $konfirmasiPassword) {
            $passwordErrors[] = 'Konfirmasi kata sandi baru tidak sesuai.';
        } else {
            TeamModel::gantiPassword($userId, $passwordBaru);
            catat_aktivitas($userId, 'ubah_password_sendiri', 'Member mengubah kata sandi akun sendiri');
            $passwordSukses = true;
        }
    }
}

$csrfToken = generate_csrf_token();
$statistik = BookingModel::getStatistikMember($userId);
$riwayat = BookingModel::getRiwayatByUserId($userId);

$stmtProfil = $db->prepare('SELECT nama, email, no_hp, alamat, created_at FROM users WHERE id = :id');
$stmtProfil->execute(['id' => $userId]);
$profilSaya = $stmtProfil->fetch();

$lamaBergabung = format_lama_bergabung($profilSaya['created_at']);
$milestoneBergabung = milestone_bergabung($profilSaya['created_at']);

$tentangKami = SettingModel::get('tentang_kami');
$kebijakanPrivasi = SettingModel::get('kebijakan_privasi');
$syaratKetentuan = SettingModel::get('syarat_ketentuan');

$judulHalaman = 'Dashboard Saya';
$halamanAktif = 'akun';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px; background-color: var(--color-bg);">
    <div class="container" style="max-width: 760px;">

        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 2px;">Halo, <?= htmlspecialchars(Session::get('user_nama')) ?></h1>
        <p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 8px;">Kelola reservasi dan riwayat sewa Anda di sini.</p>
        <p style="color: var(--color-text-muted); font-size: 12px; margin-bottom: 24px;">Member sejak <?= format_tanggal_indo($profilSaya['created_at']) ?> - sudah bergabung selama <?= $lamaBergabung ?>.</p>

        <?php if ($milestoneBergabung): ?>
            <div class="card" style="padding: 16px 18px; margin-bottom: 24px; display: flex; gap: 10px; align-items: flex-start; background-color: #FBF1EC; border-color: var(--color-accent);">
                <svg class="icon icon-accent" style="width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px;"><use href="assets/icons/sprite.svg#icon-party"></use></svg>
                <p style="font-size: 13px; color: var(--color-text); line-height: 1.6;">
                    <strong>Selamat!</strong> Anda telah bergabung menjadi member Merimba Outdoor selama <?= $milestoneBergabung ?>. Terima kasih telah menjadi bagian dari perjalanan petualangan bersama kami.
                </p>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px;">
            <div class="card" style="padding: 18px;">
                <svg class="icon icon-accent" style="width: 20px; height: 20px; margin-bottom: 8px;"><use href="assets/icons/sprite.svg#icon-box"></use></svg>
                <p style="font-size: 20px; font-weight: 700; color: var(--color-primary-dark);"><?= $statistik['reservasi_aktif'] ?></p>
                <p style="font-size: 12px; color: var(--color-text-muted);">Reservasi Aktif</p>
            </div>
            <div class="card" style="padding: 18px;">
                <svg class="icon" style="width: 20px; height: 20px; margin-bottom: 8px; color: var(--color-danger);"><use href="assets/icons/sprite.svg#icon-wallet"></use></svg>
                <p style="font-size: 20px; font-weight: 700; color: var(--color-primary-dark);"><?= format_rupiah($statistik['belum_lunas']) ?></p>
                <p style="font-size: 12px; color: var(--color-text-muted);">Belum Lunas</p>
            </div>
            <div class="card" style="padding: 18px;">
                <svg class="icon" style="width: 20px; height: 20px; margin-bottom: 8px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-history"></use></svg>
                <p style="font-size: 20px; font-weight: 700; color: var(--color-primary-dark);"><?= $statistik['total_riwayat'] ?></p>
                <p style="font-size: 12px; color: var(--color-text-muted);">Total Riwayat</p>
            </div>
        </div>

        <a href="katalog.php" class="btn btn-primary" style="margin-bottom: 28px; display: inline-flex;">
            <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-cart"></use></svg>
            Booking Lagi
        </a>

        <h3 style="font-size: 15px; margin-bottom: 14px;">Riwayat Reservasi</h3>

        <?php if (empty($riwayat)): ?>
            <div class="card" style="padding: 32px; text-align: center;">
                <p style="color: var(--color-text-muted); font-size: 13px;">Anda belum memiliki riwayat reservasi.</p>
            </div>
        <?php else: ?>
            <?php foreach ($riwayat as $indexRiwayat => $b): ?>
                <div class="riwayat-item<?= $indexRiwayat >= 7 ? ' riwayat-item-extra' : '' ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 18px; margin-bottom: 10px; background-color: var(--white); border: 1px solid var(--cream-300); border-radius: 12px;">
                    <div>
                        <p style="font-size: 14px; font-weight: 600; color: var(--forest-800);"><?= htmlspecialchars($b['kode_booking']) ?></p>
                        <p style="font-size: 12px; color: var(--warm-500); margin-top: 2px;"><?= format_tanggal_indo($b['tanggal_ambil']) ?> hingga <?= format_tanggal_indo($b['tanggal_kembali']) ?></p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 12px; font-weight: 600; color: var(--terra-600); background-color: var(--terra-100); padding: 4px 10px; border-radius: 20px;">
                            <?= htmlspecialchars(label_status_booking($b['status'])) ?>
                        </span>
                        <?php if (in_array($b['status'], ['DRAFT', 'MENUNGGU_PEMBAYARAN'], true)): ?>
                            <a href="lanjutkan-reservasi.php?booking_id=<?= $b['id'] ?>" style="font-size: 12px; font-weight: 700; color: var(--white); background-color: var(--terra-500); padding: 5px 12px; border-radius: 20px;">Lanjutkan</a>
                        <?php else: ?>
                            <a href="tracking.php?kode=<?= urlencode($b['kode_booking']) ?>" style="font-size: 12px; font-weight: 600; color: var(--terra-500);">Lacak</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($riwayat) > 7): ?>
                <button type="button" id="btn-toggle-riwayat" class="btn btn-secondary btn-sm" style="margin-bottom: 8px;">
                    <svg class="icon icon-sm" id="icon-toggle-riwayat"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                    <span id="label-toggle-riwayat">Tampilkan Semua (<?= count($riwayat) ?>)</span>
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <h3 style="font-size: 15px; margin: 28px 0 14px;">Pengaturan Akun</h3>

        <div class="settings-tabs" data-default-tab="<?= clean_input($tabAktif) ?>">
            <button type="button" class="settings-tab" data-tab="profil">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                Profil
            </button>
            <button type="button" class="settings-tab" data-tab="keamanan">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                Keamanan
            </button>
            <button type="button" class="settings-tab" data-tab="tentang">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-note"></use></svg>
                Tentang
            </button>
        </div>

        <!-- Tab: Profil -->
        <div class="settings-panel" data-panel="profil">
            <div class="card" style="padding: 22px;">
                <h4 style="font-size: 13px; margin-bottom: 4px;">Informasi Profil</h4>
                <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px;">Data ini digunakan untuk keperluan reservasi Anda.</p>

                <?php if ($profilSukses): ?>
                    <div class="alert alert-success" style="margin-bottom: 16px;">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                        Profil berhasil diperbarui.
                    </div>
                <?php endif; ?>

                <?php if (!empty($profilErrors)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 16px;">
                        <?php foreach ($profilErrors as $error): ?>
                            <p><?= clean_input($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 360px;">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="aksi" value="ubah_profil">

                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-input" required value="<?= htmlspecialchars($profilSaya['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($profilSaya['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nomor HP</label>
                        <input type="tel" name="no_hp" class="form-input" required inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" value="<?= htmlspecialchars($profilSaya['no_hp'] ?? '') ?>">
                        <p class="form-hint">Jika Anda mengganti nomor ini saat masih memiliki sewa yang berjalan, nomor lama tetap dapat dipakai untuk Cek Booking sampai sewa tersebut selesai. Setelahnya, hanya nomor baru yang berlaku.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-textarea" rows="2"><?= htmlspecialchars($profilSaya['alamat'] ?? '') ?></textarea>
                        <p class="form-hint">Wajib diisi dengan alamat domisili Anda saat ini, agar memudahkan proses verifikasi.</p>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                        Simpan Profil
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab: Keamanan -->
        <div class="settings-panel" data-panel="keamanan">
            <div class="card" style="padding: 22px;">
                <h4 style="font-size: 13px; margin-bottom: 4px;">Ubah Kata Sandi</h4>
                <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px;">Hanya Anda yang dapat mengubah kata sandi akun Anda sendiri.</p>

                <?php if ($passwordSukses): ?>
                    <div class="alert alert-success" style="margin-bottom: 16px;">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                        Kata sandi berhasil diperbarui.
                    </div>
                <?php endif; ?>

                <?php if (!empty($passwordErrors)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 16px;">
                        <?php foreach ($passwordErrors as $error): ?>
                            <p><?= clean_input($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="max-width: 360px;">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="aksi" value="ubah_password">

                    <div class="form-group">
                        <label class="form-label">Kata Sandi Baru</label>
                        <input type="password" name="password_baru" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" name="konfirmasi_password" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                        Simpan Kata Sandi Baru
                    </button>
                </form>
            </div>

            <div class="card" style="padding: 22px; margin-top: 18px; border-color: var(--danger-300, #EEC4C0);">
                <h4 style="font-size: 13px; margin-bottom: 4px; color: var(--color-danger, #B3432F);">Hapus Akun</h4>
                <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px; line-height: 1.6;">Menghapus akun ini bersifat permanen dan tidak bisa dibatalkan. Anda tidak akan bisa login lagi dengan akun ini. Riwayat reservasi tetap tersimpan (nama &amp; kontak Anda disalin ke data pemesan) demi keutuhan laporan usaha.</p>

                <?php if (!empty($hapusAkunErrors)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 16px;">
                        <?php foreach ($hapusAkunErrors as $error): ?>
                            <p><?= clean_input($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button type="button" id="btn-buka-hapus-akun" class="btn btn-secondary" style="width: 100%; justify-content: center; color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F);">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                    Hapus Akun Saya
                </button>

                <div id="blok-konfirmasi-hapus-akun" style="display: none; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--color-border);">
                    <p style="font-size: 13px; font-weight: 600; margin-bottom: 6px;">Are you sure?</p>
                    <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 12px;">
                        Ketik <strong>CONFIRM</strong> untuk menghapus akun Anda secara permanen. Tindakan ini tidak bisa dibatalkan.
                    </p>
                    <form method="POST" action="aksi/hapus-akun-sendiri.php">
                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                        <input type="text" id="input-konfirmasi-hapus-akun" class="form-input" placeholder='Ketik "CONFIRM"' autocomplete="off" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; margin-bottom: 12px;">
                        <input type="hidden" name="konfirmasi" id="input-konfirmasi-hidden-akun">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" id="btn-hapus-permanen-akun" class="btn btn-primary" disabled style="background-color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F); opacity: 0.5; cursor: not-allowed;">
                                Hapus Permanen
                            </button>
                            <button type="button" id="btn-batal-hapus-akun" class="btn btn-secondary">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: Tentang -->
        <div class="settings-panel" data-panel="tentang">
            <div class="card" style="padding: 0; overflow: hidden;">
                <button type="button" class="settings-accordion-toggle" data-target="accordion-tentang">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-mountain"></use></svg>
                        Tentang Kami
                    </span>
                    <svg class="icon icon-sm settings-accordion-chevron"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                </button>
                <div class="settings-accordion-body" id="accordion-tentang">
                    <?php if ($tentangKami !== ''): ?>
                        <p style="font-size: 12.5px; line-height: 1.7; color: var(--color-text);"><?= nl2br(htmlspecialchars($tentangKami)) ?></p>
                    <?php else: ?>
                        <p style="font-size: 12.5px; color: var(--color-text-muted);">Belum ditambahkan oleh admin.</p>
                    <?php endif; ?>
                </div>

                <button type="button" class="settings-accordion-toggle" data-target="accordion-privasi">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                        Kebijakan Privasi
                    </span>
                    <svg class="icon icon-sm settings-accordion-chevron"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                </button>
                <div class="settings-accordion-body" id="accordion-privasi">
                    <?php if ($kebijakanPrivasi !== ''): ?>
                        <p style="font-size: 12.5px; line-height: 1.7; color: var(--color-text);"><?= nl2br(htmlspecialchars($kebijakanPrivasi)) ?></p>
                    <?php else: ?>
                        <p style="font-size: 12.5px; color: var(--color-text-muted);">Kebijakan privasi belum ditambahkan oleh admin.</p>
                    <?php endif; ?>
                </div>

                <button type="button" class="settings-accordion-toggle" data-target="accordion-syarat">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-note"></use></svg>
                        Syarat & Ketentuan
                    </span>
                    <svg class="icon icon-sm settings-accordion-chevron"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                </button>
                <div class="settings-accordion-body" id="accordion-syarat">
                    <?php if ($syaratKetentuan !== ''): ?>
                        <p style="font-size: 12.5px; line-height: 1.7; color: var(--color-text);"><?= nl2br(htmlspecialchars($syaratKetentuan)) ?></p>
                    <?php else: ?>
                        <p style="font-size: 12.5px; color: var(--color-text-muted);">Syarat & ketentuan belum ditambahkan oleh admin.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</section>

<script>
(function () {
    var tabs = document.querySelectorAll('.settings-tab');
    var panels = document.querySelectorAll('.settings-panel');
    var tabsWrap = document.querySelector('.settings-tabs');
    var tabAwal = (tabsWrap && tabsWrap.dataset.defaultTab) || 'profil';

    function aktifkanTab(nama) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === nama); });
        panels.forEach(function (p) { p.classList.toggle('active', p.dataset.panel === nama); });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { aktifkanTab(tab.dataset.tab); });
    });

    aktifkanTab(tabAwal);

    document.querySelectorAll('.settings-accordion-toggle').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            var body = document.getElementById(tombol.dataset.target);
            var sudahTerbuka = body.classList.contains('open');
            document.querySelectorAll('.settings-accordion-body.open').forEach(function (b) { b.classList.remove('open'); });
            document.querySelectorAll('.settings-accordion-toggle.open').forEach(function (b) { b.classList.remove('open'); });
            if (!sudahTerbuka) {
                body.classList.add('open');
                tombol.classList.add('open');
            }
        });
    });

    var btnBukaHapusAkun = document.getElementById('btn-buka-hapus-akun');
    var blokKonfirmasiAkun = document.getElementById('blok-konfirmasi-hapus-akun');
    var inputKonfirmasiAkun = document.getElementById('input-konfirmasi-hapus-akun');
    var inputKonfirmasiHiddenAkun = document.getElementById('input-konfirmasi-hidden-akun');
    var btnHapusPermanenAkun = document.getElementById('btn-hapus-permanen-akun');
    var btnBatalHapusAkun = document.getElementById('btn-batal-hapus-akun');

    btnBukaHapusAkun.addEventListener('click', function () {
        var lanjut = confirm('Apakah Anda yakin ingin menghapus akun ini? Tindakan ini tidak dapat dibatalkan.');
        if (!lanjut) {
            return;
        }
        btnBukaHapusAkun.style.display = 'none';
        blokKonfirmasiAkun.style.display = 'block';
        inputKonfirmasiAkun.focus();
    });

    btnBatalHapusAkun.addEventListener('click', function () {
        blokKonfirmasiAkun.style.display = 'none';
        btnBukaHapusAkun.style.display = 'inline-flex';
        inputKonfirmasiAkun.value = '';
        perbaruiTombolHapusAkun();
    });

    function perbaruiTombolHapusAkun() {
        var cocok = inputKonfirmasiAkun.value === 'CONFIRM';
        inputKonfirmasiHiddenAkun.value = inputKonfirmasiAkun.value;
        btnHapusPermanenAkun.disabled = !cocok;
        btnHapusPermanenAkun.style.opacity = cocok ? '1' : '0.5';
        btnHapusPermanenAkun.style.cursor = cocok ? 'pointer' : 'not-allowed';
    }

    inputKonfirmasiAkun.addEventListener('input', perbaruiTombolHapusAkun);

    var btnToggleRiwayat = document.getElementById('btn-toggle-riwayat');
    if (btnToggleRiwayat) {
        var labelToggleRiwayat = document.getElementById('label-toggle-riwayat');
        var iconToggleRiwayat = document.getElementById('icon-toggle-riwayat');
        var jumlahRiwayat = document.querySelectorAll('.riwayat-item').length;
        var terbuka = false;

        btnToggleRiwayat.addEventListener('click', function () {
            terbuka = !terbuka;
            document.querySelectorAll('.riwayat-item-extra').forEach(function (item) {
                item.style.display = terbuka ? 'flex' : 'none';
            });
            labelToggleRiwayat.textContent = terbuka ? 'Tampilkan Lebih Sedikit' : 'Tampilkan Semua (' + jumlahRiwayat + ')';
            iconToggleRiwayat.classList.toggle('open', terbuka);
        });
    }
})();
</script>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>