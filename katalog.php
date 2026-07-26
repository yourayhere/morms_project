<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/ItemModel.php';

use App\Core\Session;
use App\Models\ItemModel;

Session::start();

$kategori = clean_input($_GET['kategori'] ?? '');
$cari     = clean_input($_GET['cari'] ?? '');
$urutan   = clean_input($_GET['urutan'] ?? 'terbaru');

$daftarKategori = ItemModel::getSemuaKategori();
$daftarItem     = ItemModel::getKatalog($kategori, $cari, $urutan);

$pesanError = [
    'kedaluwarsa' => 'Waktu pembayaran reservasi sebelumnya sudah habis, sehingga reservasi tersebut otomatis dibatalkan. Silakan buat reservasi baru.',
][$_GET['error'] ?? ''] ?? null;

$judulHalaman = 'Katalog';
$halamanAktif = 'katalog';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section class="page-section">
    <div class="container">

        <div class="page-header">
            <h1 class="page-title">Katalog Peralatan</h1>
            <p class="page-subtitle">Pilih peralatan camping dan hiking sesuai kebutuhan perjalananmu.</p>
        </div>

        <?php if ($pesanError): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <?= htmlspecialchars($pesanError) ?>
            </div>
        <?php endif; ?>

        <form method="GET" class="filter-bar">

            <div class="filter-search">
                <svg class="filter-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari peralatan..."
                    value="<?= clean_input($cari) ?>"
                >
            </div>

            <select name="kategori" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                <?php foreach ($daftarKategori as $kat): ?>
                    <option value="<?= $kat ?>" <?= $kategori === $kat ? 'selected' : '' ?>>
                        <?= label_kategori($kat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="urutan" class="filter-select" onchange="this.form.submit()">
                <option value="terbaru"    <?= $urutan === 'terbaru'    ? 'selected' : '' ?>>Terbaru</option>
                <option value="harga_asc"  <?= $urutan === 'harga_asc'  ? 'selected' : '' ?>>Harga Terendah</option>
                <option value="harga_desc" <?= $urutan === 'harga_desc' ? 'selected' : '' ?>>Harga Tertinggi</option>
            </select>

            <button type="submit" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="6" x2="20" y2="6"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                    <line x1="11" y1="18" x2="13" y2="18"/>
                </svg>
                Filter
            </button>

        </form>

        <?php if (empty($daftarItem)): ?>

            <div class="empty-state">
                <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
                <p class="empty-state-text">Tidak ada barang yang sesuai dengan pencarian.</p>
            </div>

        <?php else: ?>

            <div class="product-grid catalog-grid">
                <?php foreach ($daftarItem as $item): ?>
                    <a href="detail-barang.php?id=<?= $item['id'] ?>" class="product-card">

                        <div class="product-card-image product-card-img">
                            <?php if ($item['foto_utama'] && file_exists(__DIR__ . '/' . $item['foto_utama'])): ?>
                                <img
                                    src="<?= htmlspecialchars($item['foto_utama']) ?>"
                                    alt="<?= htmlspecialchars($item['nama']) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <svg class="product-card-img-placeholder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                            <?php endif; ?>
                        </div>

                        <div class="product-card-body">
                            <p class="product-card-category"><?= label_kategori($item['kategori']) ?></p>
                            <h3 class="product-card-name"><?= htmlspecialchars($item['nama']) ?></h3>
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

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>