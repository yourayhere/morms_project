<?php

// Halaman error HTTP terpusat - didaftarkan lewat ErrorDocument di .htaccess
// untuk kode 400/401/403/404/429/500/503. Sengaja TIDAK menyentuh
// Database/Session/Model apa pun: kalau errornya justru DB down atau PHP
// fatal error, halaman ini tetap harus bisa tampil.
//
// Path asset dihitung dari SCRIPT_NAME (path file INI yang sungguh
// dieksekusi Apache), bukan dari URL asli yang gagal - supaya tetap benar
// walau originalnya URL dalam (mis. /some/deep/bogus-path) dan supaya jalan
// di localhost/morms maupun domain produksi tanpa hardcode path (lihat juga
// perbaikan serupa di commit "Fix hardcoded /morms/ paths").
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/error.php'));
$baseUrl = rtrim($scriptDir, '/') . '/';

// mod_rewrite (dipakai untuk kasus file benar-benar tidak ada, lihat
// .htaccess) ikut mengisi REDIRECT_STATUS tapi selalu dengan nilai "200" -
// bukan kode error sungguhan, cuma efek samping mekanisme internal redirect
// Apache. "200" (atau kosong) di sini berarti "bukan lewat ErrorDocument
// asli", jadi diperlakukan sebagai 404 biasa (file tidak ditemukan).
$kode = (int) ($_SERVER['REDIRECT_STATUS'] ?? 404);
if ($kode === 200 || $kode === 0) {
    $kode = 404;
}

$daftarError = [
    400 => [
        'judul' => 'Permintaan Tidak Valid',
        'pesan' => 'Server tidak bisa memproses permintaan ini. Coba muat ulang halaman atau kembali ke beranda.',
        'ikon' => 'icon-alert',
    ],
    401 => [
        'judul' => 'Perlu Masuk Dulu',
        'pesan' => 'Anda perlu masuk ke akun untuk mengakses halaman ini.',
        'ikon' => 'icon-account',
    ],
    403 => [
        'judul' => 'Akses Ditolak',
        'pesan' => 'Anda tidak punya izin untuk mengakses halaman ini.',
        'ikon' => 'icon-block',
    ],
    404 => [
        'judul' => 'Jalur Tidak Ditemukan',
        'pesan' => 'Sepertinya halaman yang dicari sudah pindah, berganti nama, atau memang belum pernah ada.',
        'ikon' => 'icon-mountain',
    ],
    429 => [
        'judul' => 'Terlalu Banyak Permintaan',
        'pesan' => 'Anda mengirim terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.',
        'ikon' => 'icon-clock',
    ],
    500 => [
        'judul' => 'Ada Kendala di Server Kami',
        'pesan' => 'Terjadi kesalahan tak terduga di sisi kami. Tim kami akan segera menanganinya - silakan coba lagi beberapa saat lagi.',
        'ikon' => 'icon-alert',
    ],
    503 => [
        'judul' => 'Sedang Pemeliharaan',
        'pesan' => 'Layanan sedang dalam pemeliharaan singkat. Silakan coba lagi dalam beberapa menit.',
        'ikon' => 'icon-settings',
    ],
];

$error = $daftarError[$kode] ?? [
    'judul' => 'Terjadi Kesalahan',
    'pesan' => 'Maaf, terjadi kesalahan saat memproses permintaan Anda.',
    'ikon' => 'icon-alert',
];

http_response_code($kode);

$tombolKedua = $kode === 401
    ? ['label' => 'Masuk ke Akun', 'href' => $baseUrl . 'login.php']
    : ['label' => 'Lihat Katalog', 'href' => $baseUrl . 'katalog.php'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $kode ?> - <?= htmlspecialchars($error['judul']) ?> / Merimba Outdoor</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($baseUrl) ?>assets/icons/favicon.svg">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at 15% 15%, var(--terra-50) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, var(--forest-100) 0%, transparent 40%),
                var(--color-bg);
        }
        .error-topbar {
            padding: 24px clamp(20px, 5vw, 48px);
        }
        .error-wordmark {
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0.04em;
            color: var(--forest-800);
            text-decoration: none;
        }
        .error-wordmark span { color: var(--terra-500); }
        .error-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 20px 60px;
        }
        .error-card {
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .error-badge {
            position: relative;
            width: 132px;
            height: 132px;
            margin: 0 auto 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-badge::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: var(--terra-100);
            transform: rotate(-6deg);
        }
        .error-badge svg {
            position: relative;
            width: 52px;
            height: 52px;
            color: var(--terra-600);
        }
        .error-kode {
            font-family: var(--font-display);
            font-size: clamp(56px, 12vw, 84px);
            line-height: 1;
            color: var(--forest-800);
            margin: 0 0 8px;
            letter-spacing: -0.01em;
        }
        .error-judul {
            font-family: var(--font-heading);
            font-size: 21px;
            font-weight: 700;
            color: var(--forest-800);
            margin: 0 0 10px;
        }
        .error-pesan {
            font-family: var(--font-body);
            font-size: 14.5px;
            line-height: 1.6;
            color: var(--color-text-muted);
            margin: 0 0 30px;
        }
        .error-aksi {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .error-footer {
            text-align: center;
            padding: 0 20px 28px;
            font-family: var(--font-body);
            font-size: 12px;
            color: var(--warm-400);
        }
        @media (max-width: 420px) {
            .error-aksi { flex-direction: column; }
            .error-aksi .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="error-page">
    <div class="error-topbar">
        <a href="<?= htmlspecialchars($baseUrl) ?>index.php" class="error-wordmark">MERIMBA <span>OUTDOOR</span></a>
    </div>

    <main class="error-main">
        <div class="error-card">
            <div class="error-badge">
                <svg><use href="<?= htmlspecialchars($baseUrl) ?>assets/icons/sprite.svg#<?= htmlspecialchars($error['ikon']) ?>"></use></svg>
            </div>
            <p class="error-kode"><?= $kode ?></p>
            <h1 class="error-judul"><?= htmlspecialchars($error['judul']) ?></h1>
            <p class="error-pesan"><?= htmlspecialchars($error['pesan']) ?></p>
            <div class="error-aksi">
                <a href="<?= htmlspecialchars($baseUrl) ?>index.php" class="btn btn-primary btn-lg">Kembali ke Beranda</a>
                <a href="<?= htmlspecialchars($tombolKedua['href']) ?>" class="btn btn-ghost btn-lg"><?= htmlspecialchars($tombolKedua['label']) ?></a>
            </div>
        </div>
    </main>

    <p class="error-footer">&copy; <?= date('Y') ?> Merimba Outdoor. Kode error: <?= $kode ?></p>
</div>

</body>
</html>
