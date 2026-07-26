<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/ItemModel.php';

use App\Core\Session;
use App\Models\ItemModel;

Session::start();

$produkTerlaris = ItemModel::getKatalog('', '', 'terbaru');
$produkTerlaris = array_slice($produkTerlaris, 0, 6);

$judulHalaman = 'Beranda';
$halamanAktif = 'home';
require __DIR__ . '/app/Views/partials/header.php';

?>

<style>
.lp-hero { background: linear-gradient(160deg, var(--forest-800) 0%, var(--forest-600) 50%, var(--forest-400) 100%); position: relative; overflow: hidden; color: #fff; }
.lp-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 75% 40%, rgba(192,104,64,0.20) 0%, transparent 55%); pointer-events: none; }
.lp-hero-inner { position: relative; z-index: 1; padding: 88px 0 0; }
.lp-hero-tag { display: inline-flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 600; color: var(--terra-300); text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 20px; }
.lp-hero-title { font-family: var(--font-display); font-size: 48px; font-weight: 400; line-height: 1.08; letter-spacing: -0.02em; color: #fff; margin-bottom: 18px; }
.lp-hero-title em { font-style: italic; color: var(--terra-300); }
.lp-hero-desc { font-size: 15px; color: rgba(255,255,255,0.68); line-height: 1.8; max-width: 420px; margin-bottom: 32px; }
.lp-hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.lp-wave { line-height: 0; margin-top: 56px; }

.lp-trust { padding: 28px 0; background-color: var(--white); border-bottom: 1px solid var(--cream-300); }
.lp-trust-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; }
.lp-trust-item { display: flex; align-items: center; gap: 12px; padding: 10px 24px; border-right: 1px solid var(--cream-300); }
.lp-trust-item:last-child { border-right: none; }
.lp-trust-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.lp-trust-title { font-size: 13px; font-weight: 600; color: var(--forest-800); }
.lp-trust-sub { font-size: 11px; color: var(--warm-400); margin-top: 1px; }

.lp-gradient-wrap { background: linear-gradient(180deg, var(--cream-200) 0%, var(--cream-100) 100%); }

.lp-catalog { padding: 64px 0; }
.lp-catalog-head { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 32px; gap: 16px; flex-wrap: wrap; }

.lp-steps { padding: 64px 0; }
.lp-steps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; position: relative; margin-top: 40px; }
.lp-steps-line { position: absolute; top: 27px; left: calc(12.5% + 24px); right: calc(12.5% + 24px); height: 1px; background: linear-gradient(90deg, var(--terra-300), var(--cream-400)); z-index: 0; }
.lp-step { text-align: center; position: relative; z-index: 1; }
.lp-step-circle { width: 54px; height: 54px; border-radius: 50%; background: linear-gradient(135deg, var(--terra-500) 0%, var(--terra-700) 100%); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 4px 14px rgba(192,104,64,0.28); }
.lp-step-no { font-size: 10px; font-weight: 700; color: var(--terra-500); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
.lp-step-title { font-size: 14px; font-weight: 700; color: var(--forest-800); margin-bottom: 6px; }
.lp-step-desc { font-size: 12px; color: var(--warm-500); line-height: 1.65; }

.lp-cat { padding: 64px 0; background-color: var(--cream-100); }
.lp-cat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: center; }
.lp-cat-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.lp-cat-card { display: flex; align-items: center; gap: 10px; padding: 14px; background-color: var(--white); border: 1px solid var(--cream-300); border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 600; color: var(--forest-800); transition: border-color 0.15s, background-color 0.15s; }
.lp-cat-card:hover { border-color: var(--terra-400); background-color: var(--terra-50); }

.lp-cta { padding: 80px 0; background: linear-gradient(135deg, var(--forest-700) 0%, var(--forest-800) 100%); text-align: center; }
.lp-cta-tag { font-size: 11px; font-weight: 600; color: var(--terra-300); text-transform: uppercase; letter-spacing: 0.12em; margin-bottom: 14px; }
.lp-cta-title { font-family: var(--font-display); font-size: 40px; font-weight: 400; color: #fff; margin-bottom: 14px; letter-spacing: -0.02em; line-height: 1.1; }
.lp-cta-title em { color: var(--terra-300); font-style: italic; }
.lp-cta-desc { font-size: 14px; color: rgba(255,255,255,0.55); max-width: 360px; margin: 0 auto 30px; line-height: 1.8; }
.lp-cta-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

@media (max-width: 900px) {
    .lp-hero-title { font-size: 36px; }
    .lp-trust-grid { grid-template-columns: 1fr 1fr; }
    .lp-trust-item { border-right: none; border-bottom: 1px solid var(--cream-300); padding: 14px 16px; }
    .lp-trust-item:nth-child(odd) { border-right: 1px solid var(--cream-300); }
    .lp-trust-item:nth-child(3), .lp-trust-item:nth-child(4) { border-bottom: none; }
    .lp-steps-grid { grid-template-columns: 1fr 1fr; }
    .lp-steps-line { display: none; }
    .lp-cat-grid { grid-template-columns: 1fr; }
    .lp-cta-title { font-size: 30px; }
}

@media (max-width: 600px) {
    .lp-hero-inner { padding: 60px 0 0; }
    .lp-hero-title { font-size: 28px; }
    .lp-hero-desc { font-size: 14px; }
    .lp-hero-actions { flex-direction: column; }
    .lp-hero-actions .btn { width: 100%; justify-content: center; }
    .lp-trust-grid { grid-template-columns: 1fr; }
    .lp-trust-item { border-right: none; border-bottom: 1px solid var(--cream-300); }
    .lp-trust-item:last-child { border-bottom: none; }
    .lp-steps-grid { grid-template-columns: 1fr; gap: 20px; }
    .lp-cat-cards { grid-template-columns: 1fr; }
    .lp-cta-title { font-size: 26px; }
    .lp-cta-actions { flex-direction: column; align-items: center; }
    .lp-cta-actions .btn { width: 100%; max-width: 280px; justify-content: center; }
}
</style>

<section class="lp-hero">
    <div class="lp-hero-inner">
        <div class="container">
            <div style="max-width: 540px;">
                <p class="lp-hero-tag">
                    Merimba Outdoor, Yogyakarta
                </p>
                <h1 class="lp-hero-title">
                    Sewa Perlengkapan<br>
                    <em>Camping &amp; Hiking</em><br>
                    Tanpa Ribet
                </h1>
                <p class="lp-hero-desc">Dari tenda hingga kompor portable, semua tersedia. Pesan online, ambil langsung, dan nikmati petualanganmu.</p>
                <div class="lp-hero-actions">
                    <a href="katalog.php" class="btn btn-hero btn-hero-primary">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-catalog"></use></svg>
                        Lihat Katalog
                    </a>
                    <a href="tracking.php" class="btn btn-hero btn-hero-ghost">
                        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg>
                        Cek Booking
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="lp-wave">
        <svg viewBox="0 0 1440 56" preserveAspectRatio="none" style="width:100%; height:56px; display:block;">
            <path d="M0,28 C360,56 1080,0 1440,28 L1440,56 L0,56 Z" fill="var(--white)"/>
        </svg>
    </div>
</section>

<section class="lp-trust">
    <div class="container">
        <div class="lp-trust-grid">
            <div class="lp-trust-item">
                <div class="lp-trust-icon" style="background-color: var(--terra-100);">
                    <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                </div>
                <div>
                    <p class="lp-trust-title">Booking Online</p>
                    <p class="lp-trust-sub">24 jam, tanpa antri</p>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon" style="background-color: var(--terra-100);">
                    <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                </div>
                <div>
                    <p class="lp-trust-title">Peralatan Terawat</p>
                    <p class="lp-trust-sub">Dicek setiap pengembalian</p>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon" style="background-color: var(--terra-100);">
                    <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-cash"></use></svg>
                </div>
                <div>
                    <p class="lp-trust-title">Bayar DP 50%</p>
                    <p class="lp-trust-sub">Atau lunas sekaligus</p>
                </div>
            </div>
            <div class="lp-trust-item">
                <div class="lp-trust-icon" style="background-color: var(--terra-100);">
                    <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg>
                </div>
                <div>
                    <p class="lp-trust-title">Lacak Status</p>
                    <p class="lp-trust-sub">Real-time via kode booking</p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="lp-gradient-wrap">
<section class="lp-catalog">
    <div class="container">
        <div class="lp-catalog-head">
            <div>
                <span class="eyebrow">Peralatan Sewa</span>
                <h2 class="section-title">Tersedia untuk Disewa</h2>
                <p class="section-subtitle">Perlengkapan camping dan hiking berkualitas untuk petualanganmu.</p>
            </div>
            <a href="katalog.php" class="btn btn-secondary btn-sm">
                Lihat Semua
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
            </a>
        </div>

        <?php if (!empty($produkTerlaris)): ?>
        <div class="product-grid">
            <?php foreach ($produkTerlaris as $item): ?>
                <a href="detail-barang.php?id=<?= $item['id'] ?>" class="product-card">
                    <div class="product-card-image">
                        <?php if ($item['foto_utama'] && file_exists(__DIR__ . '/' . $item['foto_utama'])): ?>
                            <img src="<?= htmlspecialchars($item['foto_utama']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>">
                        <?php else: ?>
                            <svg class="icon icon-lg icon-muted"><use href="assets/icons/sprite.svg#icon-image"></use></svg>
                        <?php endif; ?>
                    </div>
                    <div class="product-card-body">
                        <p class="product-card-category"><?= label_kategori($item['kategori']) ?></p>
                        <p class="product-card-name"><?= htmlspecialchars($item['nama']) ?></p>
                        <p class="product-card-price">
                            <?= format_rupiah((float) $item['harga_per_malam']) ?>
                            <span class="product-card-price-unit">/ malam</span>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="lp-steps">
    <div class="container">
        <div style="text-align: center;">
            <span class="eyebrow">Cara Sewa</span>
            <h2 class="section-title">Mudah dalam 4 Langkah</h2>
        </div>
        <div class="lp-steps-grid">
            <div class="lp-steps-line"></div>
            <?php
            $langkah = [
                ['no' => '1', 'icon' => 'icon-catalog', 'judul' => 'Pilih Barang',     'teks' => 'Jelajahi katalog dan pilih peralatan yang kamu butuhkan.'],
                ['no' => '2', 'icon' => 'icon-cart',    'judul' => 'Isi Form Booking', 'teks' => 'Tentukan tanggal sewa dan isi data pemesan.'],
                ['no' => '3', 'icon' => 'icon-cash',    'judul' => 'Bayar DP',         'teks' => 'Bayar DP 50% via tunai atau QRIS untuk konfirmasi booking.'],
                ['no' => '4', 'icon' => 'icon-check',   'judul' => 'Ambil Barang',     'teks' => 'Datang ke toko, tinggalkan identitas, barang siap dibawa.'],
            ];
            foreach ($langkah as $l):
            ?>
                <div class="lp-step">
                    <div class="lp-step-circle">
                        <svg class="icon icon-md" style="color: white;"><use href="assets/icons/sprite.svg#<?= $l['icon'] ?>"></use></svg>
                    </div>
                    <p class="lp-step-no">Langkah <?= $l['no'] ?></p>
                    <p class="lp-step-title"><?= $l['judul'] ?></p>
                    <p class="lp-step-desc"><?= $l['teks'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
</div>

<section class="lp-cat">
    <div class="container">
        <div class="lp-cat-grid">
            <div>
                <span class="eyebrow">Kategori</span>
                <h2 class="section-title" style="margin-bottom: 12px;">Semua Kebutuhan<br>Petualanganmu</h2>
                <p style="font-size: 14px; color: var(--warm-500); line-height: 1.8; margin-bottom: 24px;">Dari perlengkapan tidur, hiking, memasak, hingga aksesoris dan jasa pemandu gunung profesional.</p>
                <a href="katalog.php" class="btn btn-primary">
                    Jelajahi Katalog
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                </a>
            </div>
            <div class="lp-cat-cards">
                <?php
                $kategoriList = [
                    ['slug' => 'tidur',     'label' => 'Perlengkapan Tidur',  'icon' => 'icon-box'],
                    ['slug' => 'hiking',    'label' => 'Perlengkapan Hiking', 'icon' => 'icon-mountain'],
                    ['slug' => 'masak',     'label' => 'Peralatan Masak',     'icon' => 'icon-receipt'],
                    ['slug' => 'aksesoris', 'label' => 'Aksesoris',           'icon' => 'icon-catalog'],
                    ['slug' => 'guide',     'label' => 'Jasa Pemandu',        'icon' => 'icon-people'],
                    ['slug' => 'pakaian_outdoor', 'label' => 'Pakaian Outdoor', 'icon' => 'icon-shirt'],
                ];
                foreach ($kategoriList as $kat):
                ?>
                    <a href="katalog.php?kategori=<?= $kat['slug'] ?>" class="lp-cat-card">
                        <svg class="icon icon-sm icon-accent"><use href="assets/icons/sprite.svg#<?= $kat['icon'] ?>"></use></svg>
                        <?= $kat['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="lp-cta">
    <div class="container">
        <p class="lp-cta-tag">Siap Berangkat?</p>
        <h2 class="lp-cta-title">Mulai Petualanganmu<br><em>Sekarang</em></h2>
        <p class="lp-cta-desc">Pesan sekarang dan pastikan perlengkapanmu siap sebelum hari-H.</p>
        <div class="lp-cta-actions">
            <a href="katalog.php" class="btn btn-hero btn-hero-primary">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-catalog"></use></svg>
                Booking Sekarang
            </a>
            <a href="tracking.php" class="btn btn-hero btn-hero-ghost">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-tracking"></use></svg>
                Cek Booking Saya
            </a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>