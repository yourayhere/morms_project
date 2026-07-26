<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/RateLimiter.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/logger.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/SettingModel.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Models\SettingModel;

Session::start();

if (Auth::isLoggedIn() && Auth::role() === 'member') {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$sukses = null;

if (($_GET['error'] ?? '') === 'diblokir') {
    $errors[] = 'Akun Anda telah dinonaktifkan oleh admin. Anda telah dikeluarkan dari sesi ini.';
}

if (($_GET['sukses'] ?? '') === 'akun_dihapus') {
    $sukses = 'Akun Anda telah berhasil dihapus secara permanen. Terima kasih pernah menjadi bagian dari Merimba Outdoor.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $identitas = clean_input($_POST['identitas'] ?? '');
        $password = $_POST['password'] ?? '';

        if (RateLimiter::isLocked($identitas)) {
            $sisaMenit = ceil(RateLimiter::remainingLockoutSeconds($identitas) / 60);
            $errors[] = 'Terlalu banyak percobaan gagal. Silakan coba lagi dalam ' . $sisaMenit . ' menit.';
        } elseif ($identitas === '' || $password === '') {
            $errors[] = 'Nomor HP/email dan kata sandi wajib diisi.';
        } else {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                'SELECT id, nama, no_hp, password_hash, role, status_aktif FROM users WHERE (no_hp = :identitas1 OR email = :identitas2) AND role = "member"'
            );
            // Nomor HP boleh diketik dalam format apa pun (08xx, +62xx, dst)
            // saat login - dinormalisasi dulu supaya cocok dengan format
            // kanonik yang tersimpan. Kolom email tetap dicocokkan apa adanya.
            $stmt->execute(['identitas1' => normalisasi_no_hp($identitas), 'identitas2' => $identitas]);
            $user = $stmt->fetch();

            // Hash bcrypt valid tapi tidak terhubung ke akun manapun. Dipakai supaya
            // password_verify() tetap dijalankan walau nomor HP/email tidak ditemukan,
            // agar waktu respons "tidak terdaftar" dan "password salah" hampir sama
            // (mencegah timing attack untuk menebak identitas yang terdaftar).
            $hashDummy = '$2y$12$38BQQDvu1u5pj1TXfex2yOXIHqHzAG4yM3m.xrJY1iEsOLSWvhp9.';
            $passwordValid = password_verify($password, $user['password_hash'] ?? $hashDummy);

            if (!$user || !$passwordValid) {
                RateLimiter::recordFailedAttempt($identitas);
                $errors[] = 'Nomor HP/email atau kata sandi tidak sesuai.';
            } elseif ($user['status_aktif'] !== 'aktif') {
                $errors[] = 'Akun tidak aktif. Silakan hubungi admin untuk aktivasi.';
            } else {
                RateLimiter::reset($identitas);
                Auth::login($user);
                catat_aktivitas($user['id'], 'login_member', 'Login berhasil sebagai member');
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

$csrfToken = generate_csrf_token();

$settings = SettingModel::getAll();
$nomorWaSumber = $settings['whatsapp_lupa_sandi'] ?? '';
if ($nomorWaSumber === '') {
    $nomorWaSumber = $settings['whatsapp'] ?? '';
}
if ($nomorWaSumber === '') {
    $nomorWaSumber = $settings['no_hp'] ?? '';
}
$nomorWaLupaSandi = format_nomor_wa($nomorWaSumber);
$pesanLupaSandiDasar = 'Halo Admin, saya lupa kata sandi untuk akun Merimba Outdoor saya. Mohon bantuannya untuk mereset kata sandi saya. Terima kasih.';
$waLinkLupaSandi = $nomorWaLupaSandi !== ''
    ? 'https://wa.me/' . $nomorWaLupaSandi . '?text=' . rawurlencode($pesanLupaSandiDasar)
    : '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk / Merimba Outdoor</title>
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

            <h2 class="auth-split-title">Masuk ke Akun Anda</h2>
            <p class="auth-split-subtitle">Kelola reservasi dan riwayat sewa Anda dengan lebih mudah.</p>

            <?php if ($sukses): ?>
                <div class="alert alert-success">
                    <p><?= clean_input($sukses) ?></p>
                </div>
            <?php endif; ?>

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
                    <label for="identitas" class="form-label">Nomor HP atau Email</label>
                    <div class="auth-split-input-group">
                        <span class="auth-split-input-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-user"></use></svg>
                        </span>
                        <input
                            type="text"
                            id="identitas"
                            name="identitas"
                            class="auth-split-input"
                            placeholder="Masukkan nomor HP atau email"
                            value="<?= clean_input($_POST['identitas'] ?? '') ?>"
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

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
                            placeholder="Masukkan kata sandi"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="auth-split-toggle-pw" id="btn-toggle-password" aria-label="Tampilkan kata sandi">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                        </button>
                    </div>
                </div>

                <?php if ($waLinkLupaSandi !== ''): ?>
                    <a href="<?= htmlspecialchars($waLinkLupaSandi) ?>" class="auth-split-forgot" id="link-lupa-sandi" target="_blank" rel="noopener">Lupa kata sandi?</a>
                    <div class="auth-split-forgot-note" id="catatan-lupa-sandi-wa">
                        Anda akan diarahkan ke WhatsApp Admin dengan pesan yang sudah terisi otomatis.
                    </div>
                <?php else: ?>
                    <a href="#" class="auth-split-forgot" id="link-lupa-sandi">Lupa kata sandi?</a>
                    <div class="auth-split-forgot-note" id="catatan-lupa-sandi">
                        Untuk reset kata sandi, silakan hubungi admin Merimba Outdoor melalui toko atau kontak yang tertera di halaman Beranda.
                    </div>
                <?php endif; ?>

                <button type="submit" class="auth-split-btn">
                    Masuk
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <p class="auth-split-footer" style="margin-top: 24px;">
                Belum punya akun?
                <a href="register.php">Daftar sekarang</a>
            </p>

            <a href="index.php" class="auth-split-back">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Kembali ke Beranda
            </a>

        </div>
    </div>

</div>

<script>
var btnTogglePassword = document.getElementById('btn-toggle-password');
var inputPassword = document.getElementById('password');
btnTogglePassword.addEventListener('click', function () {
    var tampil = inputPassword.type === 'password';
    inputPassword.type = tampil ? 'text' : 'password';
    btnTogglePassword.innerHTML = tampil
        ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>';
    btnTogglePassword.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});

var linkLupaSandi = document.getElementById('link-lupa-sandi');
var catatanLupaSandi = document.getElementById('catatan-lupa-sandi');

if (catatanLupaSandi) {
    // Belum ada nomor WhatsApp dikonfigurasi admin - tampilkan catatan saja.
    linkLupaSandi.addEventListener('click', function (e) {
        e.preventDefault();
        catatanLupaSandi.classList.toggle('show');
    });
} else if (linkLupaSandi) {
    // Sisipkan nomor HP/email yang sudah diketik user ke pesan WhatsApp,
    // supaya admin langsung tahu akun mana yang perlu direset.
    var waHrefDasar = linkLupaSandi.getAttribute('href');
    var inputIdentitas = document.getElementById('identitas');
    inputIdentitas.addEventListener('input', function () {
        var nilai = inputIdentitas.value.trim();
        if (nilai === '') {
            linkLupaSandi.setAttribute('href', waHrefDasar);
            return;
        }
        var pesan = 'Halo Admin, saya lupa kata sandi untuk akun Merimba Outdoor saya (terdaftar dengan nomor HP/email: ' + nilai + '). Mohon bantuannya untuk mereset kata sandi saya. Terima kasih.';
        var pemisah = waHrefDasar.indexOf('?text=') !== -1 ? waHrefDasar.split('?text=')[0] : waHrefDasar;
        linkLupaSandi.setAttribute('href', pemisah + '?text=' + encodeURIComponent(pesan));
    });

    // Catatan baru muncul saat teks "Lupa kata sandi?" diklik, lalu
    // pengguna tetap diarahkan ke WhatsApp Admin di tab baru (target="_blank"
    // pada link ini, jadi navigasi default tidak perlu dicegah).
    var catatanLupaSandiWa = document.getElementById('catatan-lupa-sandi-wa');
    linkLupaSandi.addEventListener('click', function () {
        catatanLupaSandiWa.classList.add('show');
    });
}
</script>

</body>
</html>