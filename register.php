<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/logger.php';

use App\Core\Database;
use App\Core\Session;

Session::start();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $nama = clean_input($_POST['nama'] ?? '');
        $noHp = normalisasi_no_hp(clean_input($_POST['no_hp'] ?? ''));
        $alamat = clean_input($_POST['alamat'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';
        $honeypot = $_POST['website'] ?? '';

        // honeypot: jika terisi, anggap bot
        if ($honeypot !== '') {
            $errors[] = 'Pendaftaran tidak valid.';
        }

        $setujuKebijakan = isset($_POST['setuju_kebijakan']);

        if ($nama === '' || $noHp === '' || $alamat === '' || $password === '') {
            $errors[] = 'Nama, nomor HP, alamat domisili, dan kata sandi wajib diisi.';
        } elseif (!$setujuKebijakan) {
            $errors[] = 'Anda harus menyetujui Syarat & Ketentuan serta Kebijakan Privasi terlebih dahulu.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Kata sandi minimal 8 karakter.';
        } elseif ($password !== $konfirmasi) {
            $errors[] = 'Konfirmasi kata sandi tidak sesuai.';
        } elseif (!validasi_no_hp($noHp)) {
            $errors[] = 'Format nomor HP tidak valid. Gunakan format nomor HP Indonesia, contoh: 08123456789.';
        } elseif ($email !== '' && !validasi_email($email)) {
            $errors[] = 'Format email tidak valid.';
        } else {
            $db = Database::getConnection();

            // Simple rate-limit for registrations per IP (max 5 per hour)
            $rStmt = $db->prepare('SELECT COUNT(*) as cnt FROM registration_attempts WHERE ip_address = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            $rStmt->execute(['ip' => $ip]);
            $cnt = (int) $rStmt->fetchColumn();

            if ($cnt >= 5) {
                $errors[] = 'Terlalu banyak percobaan pendaftaran dari alamat IP ini. Silakan coba lagi nanti.';
            } else {
                // record this attempt
                $insAttempt = $db->prepare('INSERT INTO registration_attempts (ip_address, created_at) VALUES (:ip, NOW())');
                $insAttempt->execute(['ip' => $ip]);

                $cek = $db->prepare('SELECT id FROM users WHERE no_hp = :no_hp OR (email IS NOT NULL AND email = :email)');
                $cek->execute(['no_hp' => $noHp, 'email' => $email !== '' ? $email : null]);

                if ($cek->fetch()) {
                    $errors[] = 'Nomor HP atau email sudah terdaftar. Silakan masuk.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    $stmt = $db->prepare(
                        'INSERT INTO users (nama, email, no_hp, alamat, password_hash, role, status_aktif) VALUES (:nama, :email, :no_hp, :alamat, :hash, "member", "aktif")'
                    );
                    $stmt->execute([
                        'nama'   => $nama,
                        'email'  => $email !== '' ? $email : null,
                        'no_hp'  => $noHp,
                        'alamat' => $alamat,
                        'hash'   => $hash,
                    ]);

                    $newUserId = (int) $db->lastInsertId();

                    catat_aktivitas($newUserId, 'register_member', 'Pendaftaran akun member baru');
                    $success = true;
                }
            }
        }
    }
}

$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun / Merimba Outdoor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
</head>
<body>

<div class="auth-split">

    <div class="auth-split-promo">
        <svg class="auth-split-promo-illustration" viewBox="0 0 400 110" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M-10 98 L50 45 L90 75 L140 30 L190 70 L230 42 L280 80 L330 38 L410 95" />
            <path d="M95 98 L118 68 L128 82 L138 68 L155 98" />
            <path d="M118 68 L118 98" />
        </svg>
        <p class="auth-split-brand">Merimba Outdoor</p>

        <h1 class="auth-split-headline auth-split-headline--desktop">Kelola reservasi<br>dan riwayat sewa<br><em>dengan lebih mudah.</em></h1>
        <h1 class="auth-split-headline auth-split-headline--mobile">Kelola reservasi dan<br>riwayat sewa<br><em>dengan lebih mudah.</em></h1>

        <div class="auth-split-features">
            <div class="auth-split-feature">
                <span class="auth-split-feature-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-calendar"></use></svg>
                </span>
                <div>
                    <p class="auth-split-feature-title">Reservasi Online</p>
                    <p class="auth-split-feature-desc">Pilih tanggal sewa dan lakukan booking kapan saja.</p>
                </div>
            </div>
            <div class="auth-split-feature">
                <span class="auth-split-feature-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                </span>
                <div>
                    <p class="auth-split-feature-title">Peralatan Terawat</p>
                    <p class="auth-split-feature-desc">Semua perlengkapan diperiksa sebelum siap digunakan.</p>
                </div>
            </div>
            <div class="auth-split-feature">
                <span class="auth-split-feature-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg>
                </span>
                <div>
                    <p class="auth-split-feature-title">Status Real-time</p>
                    <p class="auth-split-feature-desc">Pantau proses reservasi hingga pengembalian langsung dari akunmu.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-split-form-side">
        <div class="auth-split-form-wrap">

            <?php if ($success): ?>

                <div class="auth-success-state">
                    <div class="auth-success-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <h1 class="auth-split-title" style="margin-top: 20px; text-align: center;">Pendaftaran Berhasil!</h1>
                    <p class="auth-split-subtitle" style="text-align: center;">Akun Anda telah dibuat. Silakan masuk untuk mulai menyewa perlengkapan outdoor.</p>
                    <a href="login.php" class="auth-split-btn" style="text-decoration: none; margin-top: 0;">
                        Masuk Sekarang
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                </div>

            <?php else: ?>

                <h2 class="auth-split-title">Buat Akun Baru</h2>
                <p class="auth-split-subtitle">Nikmati kemudahan reservasi dan riwayat sewa kapan saja.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?= clean_input($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">

                    <div class="form-group">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <div class="auth-split-input-group">
                            <span class="auth-split-input-icon">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                            </span>
                            <input
                                type="text"
                                id="nama"
                                name="nama"
                                class="auth-split-input"
                                placeholder="Nama sesuai identitas resmi"
                                value="<?= clean_input($_POST['nama'] ?? '') ?>"
                                required
                                autocomplete="name"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="no_hp" class="form-label">Nomor HP</label>
                        <div class="auth-split-input-group">
                            <span class="auth-split-input-icon">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-phone"></use></svg>
                            </span>
                            <input
                                type="tel"
                                id="no_hp"
                                name="no_hp"
                                class="auth-split-input"
                                placeholder="Contoh: 08123456789"
                                value="<?= clean_input($_POST['no_hp'] ?? '') ?>"
                                required
                                inputmode="tel"
                                pattern="[+0-9][0-9\s\-]{8,17}"
                                title="Nomor HP Indonesia, contoh: 08123456789, +62 812-3456-789, atau 62812345678"
                                autocomplete="tel"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea
                            id="alamat"
                            name="alamat"
                            class="form-textarea"
                            rows="2"
                            placeholder="Alamat domisili tempat tinggal Anda saat ini"
                            required
                            autocomplete="street-address"
                        ><?= clean_input($_POST['alamat'] ?? '') ?></textarea>
                        <p class="form-hint">Wajib diisi dengan alamat domisili Anda saat ini, agar memudahkan proses verifikasi.</p>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">
                            Email
                            <span class="form-label-optional">(opsional)</span>
                        </label>
                        <div class="auth-split-input-group">
                            <span class="auth-split-input-icon">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 6l10 7 10-7"/>
                                </svg>
                            </span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="auth-split-input"
                                placeholder="Contoh: nama@email.com"
                                value="<?= clean_input($_POST['email'] ?? '') ?>"
                                autocomplete="email"
                            >
                        </div>
                    </div>

                    <div class="auth-split-row">
                        <div class="form-group">
                            <label for="password" class="form-label">Kata Sandi</label>
                            <div class="auth-split-input-group">
                                <span class="auth-split-input-icon">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                    </svg>
                                </span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="auth-split-input"
                                    style="padding-right: 44px;"
                                    placeholder="Minimal 8 karakter"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="auth-split-toggle-pw" data-target="password" aria-label="Tampilkan kata sandi">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="konfirmasi_password" class="form-label">Konfirmasi</label>
                            <div class="auth-split-input-group">
                                <span class="auth-split-input-icon">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                    </svg>
                                </span>
                                <input
                                    type="password"
                                    id="konfirmasi_password"
                                    name="konfirmasi_password"
                                    class="auth-split-input"
                                    style="padding-right: 44px;"
                                    placeholder="Ulangi kata sandi"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="auth-split-toggle-pw" data-target="konfirmasi_password" aria-label="Tampilkan kata sandi">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">

                    <label style="display: flex; align-items: flex-start; gap: 9px; margin-bottom: 20px; cursor: pointer;">
                        <input type="checkbox" name="setuju_kebijakan" required style="margin-top: 3px;">
                        <span style="font-size: 12.5px; color: var(--color-text); line-height: 1.6;">
                            Saya telah membaca dan menyetujui
                            <a href="syarat-privasi.php#syarat" target="_blank" rel="noopener" style="color: var(--color-accent); font-weight: 600;">Syarat & Ketentuan</a>
                            serta
                            <a href="syarat-privasi.php#privasi" target="_blank" rel="noopener" style="color: var(--color-accent); font-weight: 600;">Kebijakan Privasi</a>.
                        </span>
                    </label>

                    <button type="submit" class="auth-split-btn">
                        Buat Akun
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </button>
                </form>

                <p class="auth-split-footer" style="margin-top: 24px;">
                    Sudah punya akun?
                    <a href="login.php">Masuk di sini</a>
                </p>

                <a href="index.php" class="auth-split-back">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Kembali ke Beranda
                </a>

            <?php endif; ?>

        </div>
    </div>

</div>

<script>
var eyeSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>';
var eyeOffSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

document.querySelectorAll('.auth-split-toggle-pw').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        var target = document.getElementById(tombol.dataset.target);
        var tampil = target.type === 'password';
        target.type = tampil ? 'text' : 'password';
        tombol.innerHTML = tampil ? eyeOffSvg : eyeSvg;
        tombol.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
});
</script>

</body>
</html>