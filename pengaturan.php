<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/upload.php';
require_once __DIR__ . '/app/Helpers/paths.php';
require_once __DIR__ . '/app/Helpers/logger.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/SettingModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';
require_once __DIR__ . '/app/Models/BackupBuktiPembayaranModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\SettingModel;
use App\Models\BackupBuktiPembayaranModel;

Session::start();
Auth::requireRole(['owner']);

$settings = SettingModel::getAll();
$errors = [];
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'umum') {
            $noHpUsaha = normalisasi_no_hp(clean_input($_POST['no_hp'] ?? ''));
            $whatsappUsaha = normalisasi_no_hp(clean_input($_POST['whatsapp'] ?? ''));
            $whatsappLupaSandi = normalisasi_no_hp(clean_input($_POST['whatsapp_lupa_sandi'] ?? ''));

            if ($noHpUsaha !== '' && !validasi_no_hp($noHpUsaha)) {
                $errors[] = 'Format Nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.';
            } elseif ($whatsappUsaha !== '' && !validasi_no_hp($whatsappUsaha)) {
                $errors[] = 'Format WhatsApp tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.';
            } elseif ($whatsappLupaSandi !== '' && !validasi_no_hp($whatsappLupaSandi)) {
                $errors[] = 'Format WhatsApp Lupa Kata Sandi tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.';
            } else {
                SettingModel::setMany([
                    'nama_usaha'           => clean_input($_POST['nama_usaha'] ?? ''),
                    'deskripsi_usaha'      => clean_input($_POST['deskripsi_usaha'] ?? ''),
                    'alamat'               => clean_input($_POST['alamat'] ?? ''),
                    'no_hp'                => $noHpUsaha,
                    'whatsapp'             => $whatsappUsaha,
                    'whatsapp_lupa_sandi'  => $whatsappLupaSandi,
                    'instagram'            => clean_input($_POST['instagram'] ?? ''),
                    'jam_buka'             => clean_input($_POST['jam_buka'] ?? ''),
                    'jam_tutup'            => clean_input($_POST['jam_tutup'] ?? ''),
                    'maps_url'             => clean_input($_POST['maps_url'] ?? ''),
                ]);
                catat_aktivitas((int) Auth::id(), 'ubah_pengaturan', 'Pengaturan umum diperbarui');
                $sukses = true;
            }

        } elseif ($aksi === 'status_toko') {
            $statusBaru = ($_POST['status_toko'] ?? 'buka') === 'tutup' ? 'tutup' : 'buka';
            SettingModel::setMany([
                'status_toko' => $statusBaru,
                'pesan_tutup' => clean_input($_POST['pesan_tutup'] ?? ''),
            ]);
            catat_aktivitas((int) Auth::id(), 'ubah_status_toko', 'Status toko diubah menjadi "' . $statusBaru . '"');
            $sukses = true;

        } elseif ($aksi === 'qris') {
            if (isset($_FILES['qris_image']) && $_FILES['qris_image']['error'] === UPLOAD_ERR_OK) {
                $hasil = validasi_dan_simpan_qris($_FILES['qris_image']);
                if ($hasil['sukses']) {
                    SettingModel::set('qris_image', $hasil['path_relatif']);
                    catat_aktivitas((int) Auth::id(), 'ubah_qris', 'Gambar QRIS diperbarui');
                    $sukses = true;
                } else {
                    $errors[] = $hasil['pesan'];
                }
            } else {
                $errors[] = 'Pilih file gambar QRIS terlebih dahulu.';
            }

        } elseif ($aksi === 'hapus_qris') {
            $current = $settings['qris_image'] ?? '';
            if ($current) {
                $fullPath = __DIR__ . '/' . $current;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                SettingModel::set('qris_image', '');
                catat_aktivitas((int) Auth::id(), 'hapus_qris', 'Gambar QRIS dihapus');
                $sukses = true;
            } else {
                $errors[] = 'Tidak ada gambar QRIS untuk dihapus.';
            }

        } elseif ($aksi === 'logo') {
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $hasil = validasi_dan_simpan_logo($_FILES['logo_file']);
                if ($hasil['sukses']) {
                    // jika sebelumnya ada file logo, biarkan file lama tetap ada (opsional: hapus file lama)
                    SettingModel::set('logo_file', $hasil['path_relatif']);
                    // Logo baru = posisi/skala lama sudah tidak relevan, mulai dari default lagi.
                    SettingModel::setMany(['logo_scale' => '1', 'logo_pos_x' => '0', 'logo_pos_y' => '0']);
                    catat_aktivitas((int) Auth::id(), 'ubah_logo', 'Logo diperbarui');
                    $sukses = true;
                } else {
                    $errors[] = $hasil['pesan'];
                }
            } else {
                $errors[] = 'Pilih file logo terlebih dahulu.';
            }

        } elseif ($aksi === 'hapus_logo') {
            $current = $settings['logo_file'] ?? '';
            if ($current) {
                $fullPath = __DIR__ . '/' . $current;
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                SettingModel::setMany(['logo_file' => '', 'logo_scale' => '1', 'logo_pos_x' => '0', 'logo_pos_y' => '0']);
                catat_aktivitas((int) Auth::id(), 'hapus_logo', 'Logo dikembalikan ke default');
                $sukses = true;
            } else {
                $errors[] = 'Tidak ada logo untuk dihapus.';
            }

        } elseif ($aksi === 'logo_posisi') {
            $skala = max(0.5, min(3, (float) ($_POST['logo_scale'] ?? 1)));
            $posX = max(-100, min(100, (int) ($_POST['logo_pos_x'] ?? 0)));
            $posY = max(-100, min(100, (int) ($_POST['logo_pos_y'] ?? 0)));
            SettingModel::setMany([
                'logo_scale' => (string) $skala,
                'logo_pos_x' => (string) $posX,
                'logo_pos_y' => (string) $posY,
            ]);
            catat_aktivitas((int) Auth::id(), 'ubah_posisi_logo', 'Posisi/skala logo diperbarui');
            $sukses = true;

        } elseif ($aksi === 'legal') {
            // Disimpan mentah (tanpa htmlspecialchars di sini) karena teks ini
            // sudah di-escape sekali saat ditampilkan (lihat render textarea
            // di bawah dan tab Tentang di dashboard.php). Meng-escape di sini
            // JUGA akan membuat karakter seperti "&" tampil sebagai "&amp;".
            SettingModel::setMany([
                'tentang_kami'      => trim($_POST['tentang_kami'] ?? ''),
                'kebijakan_privasi' => trim($_POST['kebijakan_privasi'] ?? ''),
                'syarat_ketentuan'  => trim($_POST['syarat_ketentuan'] ?? ''),
            ]);
            catat_aktivitas((int) Auth::id(), 'ubah_legal', 'Tentang Kami/Kebijakan Privasi/Syarat & Ketentuan diperbarui');
            $sukses = true;

        } elseif ($aksi === 'backup') {
            $namaFile = 'backup_morms_' . date('Ymd_His') . '.sql';
            if (!is_dir(storage_path())) {
                @mkdir(storage_path(), 0755, true);
            }
            $outputPath = storage_path($namaFile);

            $config = require __DIR__ . '/config/database.php';
            $host = $config['host'];
            $user = $config['username'];
            $pass = $config['password'];
            $db   = $config['dbname'];
            $port = $config['port'];

            // Di XAMPP Windows, mysqldump biasanya TIDAK ada di PATH sistem,
            // sehingga exec('mysqldump ...') gagal diam-diam. Coba lokasi
            // default XAMPP dulu sebelum jatuh ke 'mysqldump' polos (untuk
            // lingkungan lain yang sudah punya mysqldump di PATH).
            $mysqldumpBin = 'mysqldump';
            $xamppDefault = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
            if (DIRECTORY_SEPARATOR === '\\' && file_exists($xamppDefault)) {
                $mysqldumpBin = $xamppDefault;
            }

            $cmd = sprintf(
                '%s --host=%s --port=%s --user=%s %s > %s 2>&1',
                escapeshellarg($mysqldumpBin),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($db),
                escapeshellarg($outputPath)
            );

            // Password dikirim lewat environment variable, bukan argumen
            // command-line, agar tidak terlihat di process list (Task
            // Manager/ps) selama proses backup berjalan.
            putenv('MYSQL_PWD=' . $pass);
            exec($cmd, $output, $kodeReturn);
            putenv('MYSQL_PWD');

            if ($kodeReturn === 0 && file_exists($outputPath)) {
                catat_aktivitas((int) Auth::id(), 'backup_database', 'Backup database berhasil: ' . $namaFile);
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $namaFile . '"');
                header('Content-Length: ' . filesize($outputPath));
                readfile($outputPath);
                unlink($outputPath);
                exit;
            } else {
                $errors[] = 'Backup database gagal. Pastikan mysqldump tersedia di server.';
            }
        }

        $settings = SettingModel::getAll();
    }
}

$csrfToken = generate_csrf_token();
$statusToko = ($settings['status_toko'] ?? 'buka') === 'tutup' ? 'tutup' : 'buka';
$pesanTutup = $settings['pesan_tutup'] ?? '';
$tentangKami = $settings['tentang_kami'] ?? '';
$kebijakanPrivasi = $settings['kebijakan_privasi'] ?? '';
$syaratKetentuan = $settings['syarat_ketentuan'] ?? '';
$qrisPath = $settings['qris_image'] ?? '';
$logoPath = $settings['logo_file'] ?? '';
$logoScale = (float) ($settings['logo_scale'] ?? 1);
$logoPosX = (int) ($settings['logo_pos_x'] ?? 0);
$logoPosY = (int) ($settings['logo_pos_y'] ?? 0);

$backupTerakhir    = BackupBuktiPembayaranModel::getBackupTerakhir();
$jadwalBerikutnya  = BackupBuktiPembayaranModel::getJadwalBerikutnya();
$statusPenyimpanan = BackupBuktiPembayaranModel::getStatusPenyimpanan();
$riwayatBackup     = BackupBuktiPembayaranModel::getRiwayat();

$pesanBackupSukses = isset($_GET['backup_sukses']) ? 'Backup berhasil dibuat.' : null;
$pesanBackupError  = $_GET['backup_error'] ?? null;
if ($pesanBackupError === 'token') {
    $pesanBackupError = 'Token keamanan tidak valid. Silakan coba lagi.';
}

$pesanArsipSukses = isset($_GET['arsip_sukses']) ? 'Arsip berhasil diproses. ' . (int) $_GET['arsip_sukses'] . ' berkas bukti pembayaran telah dihapus.' : null;
$pesanArsipError  = $_GET['arsip_error'] ?? null;
if ($pesanArsipError === 'token') {
    $pesanArsipError = 'Token keamanan tidak valid. Silakan coba lagi.';
} elseif ($pesanArsipError === 'konfirmasi') {
    $pesanArsipError = 'Centang dulu konfirmasi bahwa backup sudah diunduh dan tersimpan dengan aman.';
}

$modalBackupTerbuka = $pesanBackupSukses || $pesanBackupError || $pesanArsipSukses || $pesanArsipError;

$judulHalaman = 'Pengaturan';
$halamanAktif = 'pengaturan';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<div class="admin-page-header">
    <h1 class="admin-page-title">Pengaturan Sistem</h1>
    <p class="admin-page-subtitle">Kelola informasi usaha, pembayaran, dan sistem.</p>
</div>

<?php if ($sukses): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
        Perubahan berhasil disimpan.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <div><?php foreach ($errors as $err): ?><p><?= htmlspecialchars($err) ?></p><?php endforeach; ?></div>
    </div>
<?php endif; ?>

<div class="card" style="padding: 24px; margin-bottom: 20px; border-color: <?= $statusToko === 'tutup' ? 'var(--danger-300, #EEC4C0)' : 'var(--color-border)' ?>;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: <?= $statusToko === 'tutup' ? '16px' : '0' ?>;">
        <div>
            <h3 style="font-size: 14px; margin-bottom: 4px;">Status Toko</h3>
            <p style="font-size: 12px; color: var(--warm-400);">Saat "Tutup", seluruh reservasi baru dari customer (tambah keranjang &amp; checkout online) otomatis dihentikan sementara dengan pesan pemberitahuan. Transaksi Kasir dan akun/booking yang sudah ada tidak terpengaruh.</p>
        </div>
        <form method="POST" id="form-status-toko" style="flex-shrink: 0;">
            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
            <input type="hidden" name="aksi" value="status_toko">
            <input type="hidden" name="pesan_tutup" value="<?= htmlspecialchars($pesanTutup) ?>">
            <input type="hidden" name="status_toko" id="input-status-toko" value="<?= $statusToko ?>">
            <?php
            $pesanKonfirmasiToko = $statusToko === 'buka'
                ? 'Tutup toko sekarang? Customer tidak akan bisa menambah barang ke keranjang atau checkout online sampai Anda membuka kembali.'
                : 'Buka toko sekarang? Customer akan bisa kembali melakukan reservasi online seperti biasa.';
            ?>
            <button type="submit" id="btn-toggle-status-toko" onclick="if (!confirm(<?= htmlspecialchars(json_encode($pesanKonfirmasiToko), ENT_QUOTES) ?>)) { return false; } document.getElementById('input-status-toko').value = <?= $statusToko === 'buka' ? "'tutup'" : "'buka'" ?>;"
                style="display: flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 700;
                background-color: <?= $statusToko === 'buka' ? 'var(--success-100)' : 'var(--danger-100)' ?>; color: <?= $statusToko === 'buka' ? 'var(--success-600)' : 'var(--danger-600)' ?>;">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#<?= $statusToko === 'buka' ? 'icon-toggle-on' : 'icon-toggle-off' ?>"></use></svg>
                <?= $statusToko === 'buka' ? 'Sedang Buka' : 'Sedang Tutup' ?>
            </button>
        </form>
    </div>

    <?php if ($statusToko === 'tutup'): ?>
        <form method="POST" style="padding-top: 16px; border-top: 1px solid var(--color-border);">
            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
            <input type="hidden" name="aksi" value="status_toko">
            <input type="hidden" name="status_toko" value="tutup">
            <label class="form-label" style="display: block; margin-bottom: 6px;">Pesan untuk Customer <span class="form-label-optional">(opsional)</span></label>
            <textarea name="pesan_tutup" class="form-textarea" rows="3" placeholder="Kosongkan untuk memakai pesan default yang profesional."><?= htmlspecialchars($pesanTutup) ?></textarea>
            <button type="submit" class="btn btn-secondary btn-sm" style="margin-top: 10px;">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                Simpan Pesan
            </button>
        </form>
    <?php endif; ?>
</div>

<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px;">

    <div>
        <div class="card" style="padding: 24px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 18px;">Informasi Usaha</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="aksi" value="umum">

                <div class="form-group">
                    <label class="form-label">Nama Usaha</label>
                    <input type="text" name="nama_usaha" class="form-input" value="<?= htmlspecialchars($settings['nama_usaha'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi Usaha <span style="font-weight: 400; color: var(--warm-400);">(ditampilkan di footer website)</span></label>
                    <textarea name="deskripsi_usaha" class="form-textarea" rows="2" placeholder="Partner setia petualanganmu. Sewa peralatan camping dan hiking dengan mudah dan terpercaya di Yogyakarta."><?= htmlspecialchars($settings['deskripsi_usaha'] ?? 'Partner setia petualanganmu. Sewa peralatan camping dan hiking dengan mudah dan terpercaya di Yogyakarta.') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-textarea" rows="2"><?= htmlspecialchars($settings['alamat'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Link Google Maps <span style="font-weight: 400; color: var(--warm-400);">(opsional, supaya "Lokasi" di footer bisa diklik)</span></label>
                    <input type="url" name="maps_url" class="form-input" placeholder="https://maps.app.goo.gl/..." value="<?= htmlspecialchars($settings['maps_url'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Nomor HP</label>
                        <input type="tel" name="no_hp" class="form-input" inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" value="<?= htmlspecialchars($settings['no_hp'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp</label>
                        <input type="tel" name="whatsapp" class="form-input" inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" value="<?= htmlspecialchars($settings['whatsapp'] ?? '') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Instagram</label>
                        <input type="text" name="instagram" class="form-input" placeholder="@username" value="<?= htmlspecialchars($settings['instagram'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Jam Operasional</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <label style="display: block; font-size: 11px; color: var(--warm-400); margin-bottom: 4px;">Jam Buka</label>
                            <input type="time" name="jam_buka" class="form-input" value="<?= htmlspecialchars($settings['jam_buka'] ?? '') ?>">
                        </div>
                        <div>
                            <label style="display: block; font-size: 11px; color: var(--warm-400); margin-bottom: 4px;">Jam Tutup</label>
                            <input type="time" name="jam_tutup" class="form-input" value="<?= htmlspecialchars($settings['jam_tutup'] ?? '') ?>">
                        </div>
                    </div>
                    <?php $previewJamOperasional = format_jam_operasional($settings['jam_buka'] ?? '', $settings['jam_tutup'] ?? ''); ?>
                    <p class="form-hint">
                        <?= $previewJamOperasional !== '' ? 'Ditampilkan sebagai: ' . htmlspecialchars($previewJamOperasional) : 'Ditampilkan di footer website customer. Pesanan di luar jam ini akan diberi tahu ke customer.' ?>
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">WhatsApp untuk "Lupa Kata Sandi" <span style="font-weight: 400; color: var(--warm-400);">(opsional)</span></label>
                    <input type="tel" name="whatsapp_lupa_sandi" class="form-input" inputmode="tel" pattern="[+0-9][0-9\s\-]{8,17}" title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678" placeholder="Kosongkan untuk pakai nomor WhatsApp di atas" value="<?= htmlspecialchars($settings['whatsapp_lupa_sandi'] ?? '') ?>">
                    <p class="form-hint">Saat member klik "Lupa kata sandi?" di halaman login, mereka otomatis diarahkan WhatsApp ke nomor ini dengan pesan yang sudah terisi. Kosongkan untuk pakai nomor WhatsApp usaha di atas.</p>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                    Simpan
                </button>
            </form>
        </div>

        <div class="card" style="padding: 24px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 4px;">Tentang Kami, Kebijakan Privasi & Syarat Ketentuan</h3>
            <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 16px;">Ditampilkan kepada member di halaman akun mereka (tab "Tentang"), di halaman publik Syarat & Ketentuan/Kebijakan Privasi, di footer, dan saat pendaftaran akun baru.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="aksi" value="legal">

                <div class="form-group">
                    <label class="form-label">Tentang Kami</label>
                    <textarea name="tentang_kami" class="form-textarea" rows="6" placeholder="Ceritakan tentang usaha ini di sini..."><?= htmlspecialchars($tentangKami) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Kebijakan Privasi</label>
                    <textarea name="kebijakan_privasi" class="form-textarea" rows="6" placeholder="Tulis kebijakan privasi di sini..."><?= htmlspecialchars($kebijakanPrivasi) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Syarat & Ketentuan</label>
                    <textarea name="syarat_ketentuan" class="form-textarea" rows="6" placeholder="Tulis syarat & ketentuan di sini..."><?= htmlspecialchars($syaratKetentuan) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-save"></use></svg>
                    Simpan
                </button>
            </form>
        </div>

        <div class="card" style="padding: 24px;">
            <h3 style="font-size: 14px; margin-bottom: 6px;">Gambar QRIS</h3>
            <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 14px;">Gambar ini ditampilkan kepada customer saat memilih metode pembayaran QRIS.</p>

            <?php if ($qrisPath && file_exists(__DIR__ . '/' . $qrisPath)): ?>
                <div class="img-upload-grid">
                    <div class="img-upload-thumb" style="width: 160px; height: 160px;">
                        <img src="<?= htmlspecialchars($qrisPath) ?>" alt="QRIS" style="object-fit: contain; background-color: var(--white);">
                        <form method="POST" onsubmit="return confirm('Hapus gambar QRIS ini?');">
                            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                            <input type="hidden" name="aksi" value="hapus_qris">
                            <button type="submit" class="img-upload-delete-btn" title="Hapus QRIS" aria-label="Hapus QRIS">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="img-upload-grid" id="grid-qris-baru"></div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="aksi" value="qris">
                <label for="qris_image" class="form-upload" style="margin-bottom: 12px;">
                    <svg class="icon icon-md icon-muted"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                    <span class="form-upload-text" id="label-qris">Klik untuk pilih gambar QRIS (JPG/PNG, maks. 3MB)</span>
                </label>
                <input type="file" id="qris_image" name="qris_image" accept="image/jpeg,image/png,image/webp" style="display: none;">
                <button type="submit" class="btn btn-secondary btn-sm">Unggah QRIS</button>
            </form>
        </div>
    </div>

    <div>
        <div class="card" style="padding: 24px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 6px;">Logo Usaha</h3>
            <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 14px;">Logo ditampilkan di header website customer.</p>

            <?php if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)): ?>
                <div style="position: relative; width: 100%; max-width: 260px; height: 80px; border: 1px solid var(--cream-300); border-radius: 8px; background-color: var(--forest-800); display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 14px;">
                    <img id="preview-logo" src="<?= htmlspecialchars($logoPath) ?>" alt="Logo"
                        style="max-width: 90%; max-height: 90%; object-fit: contain; transform: scale(<?= $logoScale ?>) translate(<?= $logoPosX ?>px, <?= $logoPosY ?>px); transition: transform 0.1s ease;">
                    <form method="POST" onsubmit="return confirm('Hapus logo dan kembalikan ke default?');">
                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                        <input type="hidden" name="aksi" value="hapus_logo">
                        <button type="submit" class="img-upload-delete-btn" title="Hapus Logo" aria-label="Hapus Logo">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                        </button>
                    </form>
                </div>
                <p style="font-size: 11px; color: var(--warm-400); margin-bottom: 14px;">Pratinjau ini menyerupai tampilan logo di header website (latar gelap).</p>
            <?php endif; ?>

            <div class="img-upload-grid" id="grid-logo-baru"></div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="aksi" value="logo">
                <label for="logo_file" class="form-upload" style="margin-bottom: 12px;">
                    <svg class="icon icon-md icon-muted"><use href="assets/icons/sprite.svg#icon-image-upload"></use></svg>
                    <span class="form-upload-text" id="label-logo">Klik untuk pilih logo (PNG transparan disarankan)</span>
                </label>
                <input type="file" id="logo_file" name="logo_file" accept="image/jpeg,image/png,image/webp" style="display: none;">
                <button type="submit" class="btn btn-secondary btn-sm">Unggah Logo</button>
            </form>

            <?php if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)): ?>
                <div style="border-top: 1px solid var(--cream-300); margin-top: 18px; padding-top: 16px;">
                    <h4 style="font-size: 13px; margin-bottom: 12px;">Atur Posisi & Ukuran Logo</h4>

                    <div style="margin-bottom: 12px;">
                        <label style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 6px;">
                            <span>Zoom</span>
                            <span id="label-zoom-value"><?= number_format($logoScale, 2) ?>x</span>
                        </label>
                        <input type="range" id="input-zoom" min="0.5" max="3" step="0.05" value="<?= $logoScale ?>" style="width: 100%;">
                    </div>

                    <div class="logo-pos-grid" style="display: grid; grid-template-columns: repeat(3, 36px); grid-template-rows: repeat(3, 36px); gap: 6px; justify-content: center; margin-bottom: 14px;">
                        <div></div>
                        <button type="button" id="btn-geser-atas" class="btn btn-secondary" style="padding: 0; display: flex; align-items: center; justify-content: center;" title="Geser ke atas">
                            <svg class="icon icon-sm" style="transform: rotate(-90deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                        </button>
                        <div></div>
                        <button type="button" id="btn-geser-kiri" class="btn btn-secondary" style="padding: 0; display: flex; align-items: center; justify-content: center;" title="Geser ke kiri">
                            <svg class="icon icon-sm" style="transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                        </button>
                        <button type="button" id="btn-reset-posisi" class="btn btn-secondary" style="padding: 0; display: flex; align-items: center; justify-content: center; font-size: 10px;" title="Reset posisi">Reset</button>
                        <button type="button" id="btn-geser-kanan" class="btn btn-secondary" style="padding: 0; display: flex; align-items: center; justify-content: center;" title="Geser ke kanan">
                            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                        </button>
                        <div></div>
                        <button type="button" id="btn-geser-bawah" class="btn btn-secondary" style="padding: 0; display: flex; align-items: center; justify-content: center;" title="Geser ke bawah">
                            <svg class="icon icon-sm" style="transform: rotate(90deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                        </button>
                        <div></div>
                    </div>

                    <form method="POST" id="form-posisi-logo">
                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                        <input type="hidden" name="aksi" value="logo_posisi">
                        <input type="hidden" name="logo_scale" id="input-logo-scale" value="<?= $logoScale ?>">
                        <input type="hidden" name="logo_pos_x" id="input-logo-pos-x" value="<?= $logoPosX ?>">
                        <input type="hidden" name="logo_pos_y" id="input-logo-pos-y" value="<?= $logoPosY ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Posisi & Ukuran</button>
                    </form>
                </div>

                <script>
                (function () {
                    var scale = <?= $logoScale ?>;
                    var posX = <?= $logoPosX ?>;
                    var posY = <?= $logoPosY ?>;
                    var LANGKAH = 4;

                    var preview = document.getElementById('preview-logo');
                    var inputZoom = document.getElementById('input-zoom');
                    var labelZoom = document.getElementById('label-zoom-value');
                    var inputScale = document.getElementById('input-logo-scale');
                    var inputPosX = document.getElementById('input-logo-pos-x');
                    var inputPosY = document.getElementById('input-logo-pos-y');

                    function terapkan() {
                        preview.style.transform = 'scale(' + scale + ') translate(' + posX + 'px, ' + posY + 'px)';
                        labelZoom.textContent = scale.toFixed(2) + 'x';
                        inputScale.value = scale;
                        inputPosX.value = posX;
                        inputPosY.value = posY;
                    }

                    inputZoom.addEventListener('input', function () {
                        scale = parseFloat(this.value);
                        terapkan();
                    });

                    document.getElementById('btn-geser-atas').addEventListener('click', function () { posY -= LANGKAH; terapkan(); });
                    document.getElementById('btn-geser-bawah').addEventListener('click', function () { posY += LANGKAH; terapkan(); });
                    document.getElementById('btn-geser-kiri').addEventListener('click', function () { posX -= LANGKAH; terapkan(); });
                    document.getElementById('btn-geser-kanan').addEventListener('click', function () { posX += LANGKAH; terapkan(); });
                    document.getElementById('btn-reset-posisi').addEventListener('click', function () {
                        scale = 1; posX = 0; posY = 0;
                        inputZoom.value = 1;
                        terapkan();
                    });
                })();
                </script>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 24px;">
            <h3 style="font-size: 14px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                <svg class="icon icon-sm" style="color: var(--forest-600);"><use href="assets/icons/sprite.svg#icon-database"></use></svg>
                Backup Database
            </h3>
            <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 16px; line-height: 1.6;">Unduh seluruh data sistem dalam format SQL. Simpan di tempat yang aman secara berkala.</p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="aksi" value="backup">
                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--forest-700); border-color: var(--forest-300);" onclick="return confirm('Mulai proses backup database sekarang?');">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                    Unduh Backup SQL
                </button>
            </form>
        </div>

        <div class="card" style="padding: 24px; margin-top: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                <svg class="icon icon-sm" style="color: var(--forest-600);"><use href="assets/icons/sprite.svg#icon-database"></use></svg>
                Backup Bukti Pembayaran
            </h3>
            <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 16px; line-height: 1.6;">Arsipkan bukti pembayaran QRIS pelanggan ke ZIP secara berkala agar penyimpanan hosting tidak penuh.</p>

            <button type="button" class="btn btn-secondary btn-sm" id="btn-buka-backup-bukti" style="color: var(--forest-700); border-color: var(--forest-300);">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                Kelola Backup Bukti Pembayaran
            </button>
        </div>
    </div>

</div>

<script src="assets/js/image-uploader.js"></script>
<script>
MormsImageUploader.pasangPreview(
    document.getElementById('qris_image'),
    document.getElementById('grid-qris-baru'),
    { onChange: function (jumlah) {
        document.getElementById('label-qris').textContent = jumlah > 0
            ? document.getElementById('qris_image').files[0].name
            : 'Klik untuk pilih gambar QRIS (JPG/PNG, maks. 3MB)';
    } }
);
MormsImageUploader.pasangPreview(
    document.getElementById('logo_file'),
    document.getElementById('grid-logo-baru'),
    { onChange: function (jumlah) {
        document.getElementById('label-logo').textContent = jumlah > 0
            ? document.getElementById('logo_file').files[0].name
            : 'Klik untuk pilih logo (PNG transparan disarankan)';
    } }
);
</script>

<div class="modal-overlay" id="modal-backup-bukti" style="display: <?= $modalBackupTerbuka ? 'flex' : 'none' ?>;">
    <div class="modal-panel modal-panel-lg">

        <div class="modal-header">
            <h2 class="modal-title">
                Backup Bukti Pembayaran
                <button type="button" class="info-icon-btn" id="btn-info-backup" aria-label="Tentang Backup Bukti Pembayaran" title="Tentang Backup Bukti Pembayaran">i</button>
            </h2>
            <button type="button" class="modal-close-btn" id="btn-tutup-backup-bukti" aria-label="Tutup">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
            </button>

            <div class="info-popover" id="popover-info-backup">
                <p style="font-weight: 700; margin-bottom: 8px;">Tentang Backup Bukti Pembayaran</p>
                <p style="margin-bottom: 8px;">Bukti pembayaran disimpan sebagai arsip transaksi dan digunakan untuk membantu proses administrasi maupun verifikasi apabila diperlukan.</p>
                <p style="margin-bottom: 8px;">Karena sistem menggunakan kapasitas penyimpanan hosting yang terbatas, disarankan untuk melakukan backup secara berkala agar ruang penyimpanan tetap tersedia untuk transaksi berikutnya.</p>
                <p style="margin-bottom: 8px;">Fitur Backup akan membuat arsip dalam bentuk file ZIP tanpa menghapus data apa pun.</p>
                <p style="margin-bottom: 8px;">Setelah memastikan file backup telah berhasil diunduh dan disimpan dengan aman, Anda dapat menggunakan fitur Arsip untuk menghapus bukti pembayaran lama yang sudah termasuk ke dalam backup tersebut.</p>
                <p style="margin-bottom: 8px;">Penghapusan dilakukan secara manual oleh Owner dan tidak pernah berjalan otomatis.</p>
                <p>Bukti pembayaran yang diunggah setelah proses backup terakhir tidak akan ikut terhapus sehingga transaksi baru tetap aman.</p>
            </div>
        </div>

        <div class="modal-body">

            <?php if ($pesanBackupSukses): ?>
                <div class="alert alert-success" style="margin-bottom: 16px;"><?= htmlspecialchars($pesanBackupSukses) ?></div>
            <?php endif; ?>
            <?php if ($pesanBackupError): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;"><?= htmlspecialchars($pesanBackupError) ?></div>
            <?php endif; ?>
            <?php if ($pesanArsipSukses): ?>
                <div class="alert alert-success" style="margin-bottom: 16px;"><?= htmlspecialchars($pesanArsipSukses) ?></div>
            <?php endif; ?>
            <?php if ($pesanArsipError): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;"><?= htmlspecialchars($pesanArsipError) ?></div>
            <?php endif; ?>

            <div class="backup-info-grid">

                <div class="card" style="padding: 16px;">
                    <p class="backup-info-label">Informasi Backup Terakhir</p>
                    <?php if ($backupTerakhir): ?>
                        <p class="backup-info-value"><?= format_tanggal_indo($backupTerakhir['created_at']) ?></p>
                        <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 10px;">pukul <?= date('H:i', strtotime($backupTerakhir['created_at'])) ?> WIB</p>
                        <p style="font-size: 12.5px; line-height: 1.8;">
                            <?= $backupTerakhir['jumlah_file'] ?> bukti pembayaran<br>
                            <?= format_ukuran_file((int) $backupTerakhir['ukuran_bytes']) ?><br>
                            <span class="badge <?= $backupTerakhir['diarsipkan_at'] ? 'badge-success' : 'badge-neutral' ?>" style="margin-top: 4px;">
                                <?= $backupTerakhir['diarsipkan_at'] ? 'Sudah diarsipkan' : 'Belum diarsipkan' ?>
                            </span>
                        </p>
                    <?php else: ?>
                        <p style="font-size: 12.5px; color: var(--warm-400);">Belum pernah melakukan backup.</p>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding: 16px;">
                    <p class="backup-info-label">Jadwal Backup Berikutnya</p>
                    <?php if ($jadwalBerikutnya): ?>
                        <p class="backup-info-value"><?= format_tanggal_indo($jadwalBerikutnya->format('Y-m-d')) ?></p>
                        <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 10px;">pukul <?= $jadwalBerikutnya->format('H:i') ?> WIB</p>
                        <?php
                        $selisihJadwal = $jadwalBerikutnya->getTimestamp() - time();
                        ?>
                        <p style="font-size: 12.5px;">
                            <?= $selisihJadwal > 0
                                ? 'Dalam ' . format_durasi_singkat($selisihJadwal)
                                : '<span style="color: var(--color-danger);">Sudah lewat ' . format_durasi_singkat(abs($selisihJadwal)) . '</span>' ?>
                        </p>
                    <?php else: ?>
                        <p style="font-size: 12.5px; color: var(--warm-400);">Ditentukan otomatis setelah backup pertama dibuat (berlaku 1 bulan sejak saat itu).</p>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding: 16px;">
                    <p class="backup-info-label">Status Penyimpanan</p>
                    <p class="backup-info-value"><?= $statusPenyimpanan['jumlah_file'] ?> berkas</p>
                    <p style="font-size: 12px; color: var(--warm-400); margin-bottom: 10px;"><?= format_ukuran_file((int) $statusPenyimpanan['ukuran_bytes']) ?> terpakai</p>
                    <p style="font-size: 12.5px;">
                        <?php if ($statusPenyimpanan['bisa_diarsipkan'] > 0): ?>
                            <?= $statusPenyimpanan['bisa_diarsipkan'] ?> berkas siap diarsipkan.
                        <?php else: ?>
                            Tidak ada berkas yang menunggu diarsipkan.
                        <?php endif; ?>
                    </p>
                </div>

            </div>

            <div class="card" style="padding: 18px; margin-top: 16px;">
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 18px;">
                    <form method="POST" action="aksi/backup-bukti-pembayaran.php" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                            Backup Bukti Pembayaran
                        </button>
                    </form>
                    <p style="font-size: 12px; color: var(--warm-400); max-width: 320px;">Membuat arsip ZIP dari seluruh bukti pembayaran yang ada saat ini. Tidak menghapus berkas apa pun.</p>
                </div>

                <div style="border-top: 1px solid var(--cream-300); padding-top: 16px;">
                    <?php
                    $adaYangBisaDiarsipkan = $backupTerakhir && !$backupTerakhir['diarsipkan_at'] && $statusPenyimpanan['bisa_diarsipkan'] > 0;
                    ?>
                    <?php if (!$adaYangBisaDiarsipkan): ?>
                        <p style="font-size: 12.5px; color: var(--warm-400);">
                            <?= !$backupTerakhir
                                ? 'Belum ada backup yang bisa diarsipkan. Buat backup terlebih dahulu.'
                                : 'Backup terakhir sudah diarsipkan (atau tidak ada lagi berkas yang bisa dihapus). Buat backup baru untuk bisa mengarsipkan lagi.' ?>
                        </p>
                    <?php else: ?>
                        <form method="POST" action="aksi/arsipkan-bukti-pembayaran.php" id="form-arsip-bukti" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                            <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; margin-bottom: 12px; cursor: pointer;">
                                <input type="checkbox" name="konfirmasi_unduh" value="1" id="checkbox-konfirmasi-arsip" style="margin-top: 2px;">
                                Saya telah mengunduh dan memastikan file backup telah tersimpan dengan aman.
                            </label>
                            <button type="submit" id="btn-arsipkan-bukti" class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed; color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F);"
                                onclick="return confirm('Yakin ingin mengarsipkan (menghapus) bukti pembayaran yang termasuk backup terakhir? Tindakan ini tidak dapat dibatalkan.');">
                                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                                Arsipkan Bukti Pembayaran
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <h3 style="font-size: 14px; margin-bottom: 12px;">Riwayat Backup</h3>
                <?php if (empty($riwayatBackup)): ?>
                    <p style="font-size: 12.5px; color: var(--warm-400);">Belum ada riwayat backup.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tanggal Backup</th>
                                    <th>Periode Backup</th>
                                    <th>Jumlah Bukti</th>
                                    <th>Ukuran ZIP</th>
                                    <th>Nama File</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayatBackup as $baris): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($baris['created_at'])) ?></td>
                                        <td>s.d. <?= date('d/m/Y H:i', strtotime($baris['cutoff_at'])) ?></td>
                                        <td><?= $baris['jumlah_file'] ?> berkas</td>
                                        <td><?= format_ukuran_file((int) $baris['ukuran_bytes']) ?></td>
                                        <td>
                                            <a href="aksi/download-backup-bukti-pembayaran.php?nama_file=<?= urlencode($baris['nama_file']) ?>" style="color: var(--color-accent); font-weight: 600; font-size: 12.5px;">
                                                <?= htmlspecialchars($baris['nama_file']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?= $baris['diarsipkan_at'] ? 'badge-success' : 'badge-neutral' ?>">
                                                <?= $baris['diarsipkan_at'] ? 'Diarsipkan' : 'Belum' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background-color: rgba(28, 24, 20, 0.55);
    z-index: 1000;
    align-items: flex-start;
    justify-content: center;
    padding: 32px 16px;
    overflow-y: auto;
}
.modal-panel {
    background-color: var(--white, #fff);
    border-radius: 14px;
    width: 100%;
    max-width: 640px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    position: relative;
}
.modal-panel-lg { max-width: 920px; }
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--cream-300, #eee);
    position: relative;
}
.modal-title { font-size: 17px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.modal-close-btn {
    background: none; border: none; cursor: pointer; padding: 6px;
    color: var(--warm-400); border-radius: 6px; display: flex;
}
.modal-close-btn:hover { background-color: var(--cream-100, #f5f5f5); }
.modal-body { padding: 24px; max-height: 72vh; overflow-y: auto; }

.info-icon-btn {
    width: 20px; height: 20px; border-radius: 50%;
    background-color: var(--cream-300, #eee); color: var(--warm-500);
    border: none; font-size: 12px; font-weight: 700; font-style: italic;
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    font-family: Georgia, serif;
}
.info-icon-btn:hover { background-color: var(--terra-300, #ddd); color: #fff; }

.info-popover {
    display: none;
    position: absolute;
    top: 100%;
    left: 24px;
    margin-top: 8px;
    width: min(420px, calc(100vw - 64px));
    background-color: var(--white, #fff);
    border: 1px solid var(--cream-300, #eee);
    border-radius: 10px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
    padding: 18px;
    font-size: 12.5px;
    line-height: 1.6;
    color: var(--color-text, #333);
    z-index: 1001;
}
.info-popover.open { display: block; }

.backup-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.backup-info-label {
    font-size: 11px; font-weight: 600; color: var(--warm-400);
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px;
}
.backup-info-value { font-size: 16px; font-weight: 700; color: var(--forest-800); }

@media (max-width: 720px) {
    .backup-info-grid { grid-template-columns: 1fr; }
    .modal-body { max-height: 78vh; }
}
</style>

<script>
(function () {
    var overlay = document.getElementById('modal-backup-bukti');
    var btnBuka = document.getElementById('btn-buka-backup-bukti');
    var btnTutup = document.getElementById('btn-tutup-backup-bukti');

    function bukaModalBackup() {
        overlay.style.display = 'flex';
    }
    function tutupModalBackup() {
        overlay.style.display = 'none';
    }

    btnBuka.addEventListener('click', bukaModalBackup);
    btnTutup.addEventListener('click', tutupModalBackup);
    if (window.location.hash === '#modal-backup-bukti') {
        bukaModalBackup();
    }
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) tutupModalBackup();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tutupModalBackup();
    });

    var btnInfo = document.getElementById('btn-info-backup');
    var popoverInfo = document.getElementById('popover-info-backup');
    btnInfo.addEventListener('click', function (e) {
        e.stopPropagation();
        popoverInfo.classList.toggle('open');
    });
    document.addEventListener('click', function () {
        popoverInfo.classList.remove('open');
    });

    var checkboxArsip = document.getElementById('checkbox-konfirmasi-arsip');
    var btnArsip = document.getElementById('btn-arsipkan-bukti');
    if (checkboxArsip && btnArsip) {
        checkboxArsip.addEventListener('change', function () {
            btnArsip.disabled = !checkboxArsip.checked;
            btnArsip.style.opacity = checkboxArsip.checked ? '1' : '0.5';
            btnArsip.style.cursor = checkboxArsip.checked ? 'pointer' : 'not-allowed';
        });
    }
})();
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>