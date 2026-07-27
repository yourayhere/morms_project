<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/toko.php';
require_once __DIR__ . '/app/Models/ItemModel.php';
require_once __DIR__ . '/app/Core/Auth.php';

use App\Core\Session;
use App\Models\ItemModel;

Session::start();

$tokoTutup = toko_sedang_tutup();
$id = (int) ($_GET['id'] ?? 0);
$item = ItemModel::getById($id);

if (!$item) {
    header('Location: katalog.php');
    exit;
}

$gambarList = ItemModel::getImages($id);
$variasiList = $item['kategori'] === 'pakaian_outdoor' ? ItemModel::getVariasi($id) : [];
$bulanTampil = clean_input($_GET['bulan'] ?? date('Y-m'));
$kalender = ItemModel::getKalenderKetersediaan($id, (int) $item['stok_total'], $bulanTampil);

$bulanSebelum = date('Y-m', strtotime($bulanTampil . '-01 -1 month'));
$bulanSesudah = date('Y-m', strtotime($bulanTampil . '-01 +1 month'));

$namaBulanList = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$labelBulan = $namaBulanList[(int) date('n', strtotime($bulanTampil . '-01'))] . ' ' . date('Y', strtotime($bulanTampil . '-01'));

$csrfToken = generate_csrf_token();

$judulHalaman = $item['nama'];
$halamanAktif = 'katalog';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="background-color: var(--color-bg); padding: 32px 0 56px;">
    <div class="container">

        <a href="katalog.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 20px;">
            <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
            Kembali ke Katalog
        </a>

        <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 32px;" id="detail-grid">

            <div>
                <?php
                $gambarValid = array_values(array_filter($gambarList, function ($g) {
                    return file_exists(__DIR__ . '/' . $g['url']);
                }));
                ?>
                <div class="galeri-produk" style="margin-bottom: 24px;">
                    <div class="galeri-utama card" style="aspect-ratio: 1 / 1; background: linear-gradient(135deg, var(--cream-200) 0%, var(--cream-300) 100%); position: relative; overflow: hidden;">
                        <?php if (!empty($gambarValid)): ?>
                            <?php foreach ($gambarValid as $index => $gambar): ?>
                                <img src="<?= htmlspecialchars($gambar['url']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>"
                                    class="galeri-slide<?= $index === 0 ? ' aktif' : '' ?>" data-index="<?= $index ?>"
                                    style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; opacity: <?= $index === 0 ? '1' : '0' ?>; transition: opacity .25s ease; pointer-events: none;">
                            <?php endforeach; ?>

                            <?php if (count($gambarValid) > 1): ?>
                                <button type="button" class="galeri-nav galeri-prev" aria-label="Foto sebelumnya" style="position: absolute; top: 50%; left: 10px; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.9); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                                </button>
                                <button type="button" class="galeri-nav galeri-next" aria-label="Foto berikutnya" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.9); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                                </button>

                                <div class="galeri-dots" style="position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); display: flex; gap: 6px;">
                                    <?php foreach ($gambarValid as $index => $gambar): ?>
                                        <span class="galeri-dot<?= $index === 0 ? ' aktif' : '' ?>" data-index="<?= $index ?>" style="width: 7px; height: 7px; border-radius: 50%; background: <?= $index === 0 ? 'var(--color-accent)' : 'rgba(255,255,255,.8)' ?>; border: 1px solid rgba(0,0,0,.15); cursor: pointer;"></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                <svg class="icon" style="width: 48px; height: 48px; color: var(--color-primary-light);"><use href="assets/icons/sprite.svg#icon-image"></use></svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($gambarValid) > 1): ?>
                        <div class="galeri-thumbnail" style="display: flex; gap: 10px; margin-top: 10px;">
                            <?php foreach ($gambarValid as $index => $gambar): ?>
                                <button type="button" class="galeri-thumb-btn<?= $index === 0 ? ' aktif' : '' ?>" data-index="<?= $index ?>"
                                    style="width: 64px; height: 64px; border-radius: 8px; overflow: hidden; padding: 0; cursor: pointer; background: var(--cream-200); border: 2px solid <?= $index === 0 ? 'var(--color-accent)' : 'var(--color-border)' ?>;">
                                    <img src="<?= htmlspecialchars($gambar['url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <p style="font-size: 12px; color: var(--color-accent); font-weight: 600; text-transform: uppercase; margin-bottom: 6px;">
                    <?= label_kategori($item['kategori']) ?>
                    <?php if (!empty($item['sub_kategori'])): ?>
                        &middot; <?= label_sub_kategori_pakaian($item['sub_kategori']) ?>
                    <?php endif; ?>
                </p>
                <h1 style="font-size: 24px; color: var(--color-primary-dark); margin-bottom: 12px;"><?= htmlspecialchars($item['nama']) ?></h1>
                <p style="color: var(--color-text-muted); font-size: 14px; line-height: 1.7; margin-bottom: 20px;"><?= nl2br(htmlspecialchars($item['deskripsi'])) ?></p>

                <div class="card" style="padding: 16px; margin-bottom: 24px;">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <svg class="icon icon-accent" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px;"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                        <div>
                            <p style="font-size: 13px; font-weight: 600; margin-bottom: 4px;">Syarat Penyewaan</p>
                            <p style="font-size: 13px; color: var(--color-text-muted);"><?= htmlspecialchars($item['syarat_penyewaan']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3 style="font-size: 15px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                        <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-calendar"></use></svg>
                        Kalender Ketersediaan
                    </h3>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <a href="?id=<?= $id ?>&bulan=<?= $bulanSebelum ?>" style="color: var(--color-text-muted); font-size: 13px;">&laquo; Sebelumnya</a>
                        <strong style="font-size: 14px;"><?= $labelBulan ?></strong>
                        <a href="?id=<?= $id ?>&bulan=<?= $bulanSesudah ?>" style="color: var(--color-text-muted); font-size: 13px;">Berikutnya &raquo;</a>
                    </div>

                    <div class="kalender-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px;">
                        <?php
                        $hariLabel = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
                        foreach ($hariLabel as $hari) {
                            echo '<div style="text-align:center; font-size:11px; color: var(--color-text-muted); font-weight:600;">' . $hari . '</div>';
                        }

                        $hariPertama = (int) date('w', strtotime($bulanTampil . '-01'));
                        for ($kosong = 0; $kosong < $hariPertama; $kosong++) {
                            echo '<div></div>';
                        }

                        foreach ($kalender as $tanggal => $info) {
                            $warna = $info['status'] === 'penuh' ? '#B3432F' : ($info['status'] === 'terbatas' ? '#C0623A' : '#4A7A5C');
                            $tglNomor = (int) date('j', strtotime($tanggal));
                            echo '<div style="text-align:center; padding:6px 2px; border-radius:6px; background-color: ' . ($info['status'] === 'penuh' ? '#FBEAE5' : '#FFFFFF') . '; border: 1px solid var(--color-border);">';
                            echo '<div style="font-size:12px; font-weight:600;">' . $tglNomor . '</div>';
                            echo '<div style="width:6px; height:6px; border-radius:50%; background-color:' . $warna . '; margin: 3px auto 0;"></div>';
                            echo '</div>';
                        }
                        ?>
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 14px; font-size: 12px; color: var(--color-text-muted);">
                        <span><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:#4A7A5C; margin-right:4px;"></span>Tersedia</span>
                        <span><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:#C0623A; margin-right:4px;"></span>Terbatas</span>
                        <span><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:#B3432F; margin-right:4px;"></span>Penuh</span>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" style="padding: 24px; position: sticky; top: 84px;">
                    <h3 style="font-size: 16px; margin-bottom: 16px;">Ajukan Reservasi</h3>

                    <form id="form-reservasi">
                        <input type="hidden" id="csrf-token" value="<?= clean_input($csrfToken) ?>">
                        <input type="hidden" id="item-id" value="<?= $id ?>">
                        <input type="hidden" id="harga-per-malam" value="<?= $item['harga_per_malam'] ?>">
                        <input type="hidden" id="stok-total" value="<?= $item['stok_total'] ?>">

                        <?php if ($item['kategori'] === 'pakaian_outdoor'): ?>
                            <div style="margin-bottom: 14px;">
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Ukuran</label>
                                <select id="pilih-ukuran" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                                    <option value="">Pilih ukuran</option>
                                    <?php foreach ($variasiList as $variasi): ?>
                                        <option value="<?= htmlspecialchars($variasi['ukuran']) ?>" data-stok="<?= (int) $variasi['stok'] ?>" <?= (int) $variasi['stok'] <= 0 ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($variasi['ukuran']) ?><?= (int) $variasi['stok'] <= 0 ? ' (Stok Habis)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Tanggal Ambil</label>
                            <input type="date" id="tanggal-ambil" required min="<?= date('Y-m-d') ?>"
                                style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Tanggal Kembali</label>
                            <input type="date" id="tanggal-kembali" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Jam Ambil</label>
                            <input type="time" id="jam-ambil" required value="09:00"
                                style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                            <p style="font-size: 11.5px; color: var(--color-text-muted); margin-top: 5px;">Batas waktu pengembalian mengikuti jam saat barang diambil.</p>
                        </div>

                        <div style="margin-bottom: 18px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Jumlah</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <button type="button" id="btn-kurang" class="btn btn-secondary" style="padding: 8px 10px;">
                                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-minus"></use></svg>
                                </button>
                                <input type="number" id="jumlah" value="1" min="1" max="<?= $item['stok_total'] ?>" readonly
                                    style="width: 60px; text-align: center; padding: 8px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                                <button type="button" id="btn-tambah" class="btn btn-secondary" style="padding: 8px 10px;">
                                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
                                </button>
                            </div>
                        </div>

                        <div id="pesan-stok" style="display: none; background-color: #FBEAE5; color: var(--color-danger); font-size: 13px; padding: 10px 12px; border-radius: 6px; margin-bottom: 14px;"></div>

                        <div style="border-top: 1px solid var(--color-border); padding-top: 16px; margin-bottom: 18px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                            <span style="color: var(--color-text-muted);">Durasi</span>
                            <strong id="hasil-durasi">0 malam</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                            <span style="color: var(--color-text-muted);">Jumlah Barang</span>
                            <strong id="hasil-jumlah">1 unit</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 15px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--color-border);">
                            <span>Estimasi Total Sewa</span>
                            <strong id="hasil-total" style="color: var(--color-accent);">Rp0</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 8px;">
                            <span style="color: var(--color-text-muted);">DP Minimal (50%)</span>
                            <strong id="hasil-dp">Rp0</strong>
                        </div>

                        <div style="background-color: #FAF6F0; border: 1px solid var(--color-border); border-radius: 6px; padding: 10px 12px; margin-top: 14px; display: flex; gap: 8px; align-items: flex-start;">
                            <svg class="icon icon-accent" style="width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px;"><use href="assets/icons/sprite.svg#icon-shield"></use></svg>
                            <p style="font-size: 12px; color: var(--color-text-muted); line-height: 1.5;">
                                Jaminan berupa identitas aktif (KTM/KTP/SIM) wajib ditinggalkan saat pengambilan barang dan akan dikembalikan saat barang dikembalikan dalam kondisi baik.
                            </p>
                        </div>
                    </div>

                        <?php if ($tokoTutup): ?>
                            <button type="button" class="btn btn-primary" disabled style="width: 100%; justify-content: center; opacity: 0.5; cursor: not-allowed;">
                                <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-cart"></use></svg>
                                Tambah ke Keranjang
                            </button>
                            <p style="font-size: 11.5px; color: var(--color-text-muted); text-align: center; margin-top: 8px;">Sedang tutup - belum bisa menerima reservasi baru.</p>
                        <?php else: ?>
                            <button type="submit" id="btn-tambah-keranjang" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-cart"></use></svg>
                                Tambah ke Keranjang
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<style>
    @media (max-width: 900px) {
        #detail-grid { grid-template-columns: 1fr !important; }
    }
</style>

<script>
(function () {
    const galeri = document.querySelector('.galeri-produk');
    if (galeri) {
        const slides = Array.from(galeri.querySelectorAll('.galeri-slide'));
        const dots = Array.from(galeri.querySelectorAll('.galeri-dot'));
        const thumbs = Array.from(galeri.querySelectorAll('.galeri-thumb-btn'));
        let indexAktif = 0;

        function tampilkanSlide(index) {
            if (index < 0) index = slides.length - 1;
            if (index >= slides.length) index = 0;
            indexAktif = index;

            slides.forEach(function (slide) {
                const aktif = parseInt(slide.dataset.index, 10) === index;
                slide.style.opacity = aktif ? '1' : '0';
                slide.classList.toggle('aktif', aktif);
            });
            dots.forEach(function (dot) {
                const aktif = parseInt(dot.dataset.index, 10) === index;
                dot.style.background = aktif ? 'var(--color-accent)' : 'rgba(255,255,255,.8)';
                dot.classList.toggle('aktif', aktif);
            });
            thumbs.forEach(function (thumb) {
                const aktif = parseInt(thumb.dataset.index, 10) === index;
                thumb.style.borderColor = aktif ? 'var(--color-accent)' : 'var(--color-border)';
                thumb.classList.toggle('aktif', aktif);
            });
        }

        const btnPrev = galeri.querySelector('.galeri-prev');
        const btnNext = galeri.querySelector('.galeri-next');
        if (btnPrev) btnPrev.addEventListener('click', function () { tampilkanSlide(indexAktif - 1); });
        if (btnNext) btnNext.addEventListener('click', function () { tampilkanSlide(indexAktif + 1); });

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () { tampilkanSlide(parseInt(dot.dataset.index, 10)); });
        });
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () { tampilkanSlide(parseInt(thumb.dataset.index, 10)); });
        });

        // Swipe geser di layar sentuh
        const galeriUtama = galeri.querySelector('.galeri-utama');
        let touchStartX = 0;
        galeriUtama.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        galeriUtama.addEventListener('touchend', function (e) {
            const touchEndX = e.changedTouches[0].screenX;
            const selisih = touchEndX - touchStartX;
            if (Math.abs(selisih) > 40) {
                tampilkanSlide(selisih > 0 ? indexAktif - 1 : indexAktif + 1);
            }
        }, { passive: true });

        // Navigasi keyboard saat galeri difokus
        galeriUtama.setAttribute('tabindex', '0');
        galeriUtama.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') tampilkanSlide(indexAktif - 1);
            if (e.key === 'ArrowRight') tampilkanSlide(indexAktif + 1);
        });
    }

    const tanggalAmbil = document.getElementById('tanggal-ambil');
    const tanggalKembali = document.getElementById('tanggal-kembali');
    const jumlahInput = document.getElementById('jumlah');
    const btnTambah = document.getElementById('btn-tambah');
    const btnKurang = document.getElementById('btn-kurang');
    const hargaPerMalam = parseFloat(document.getElementById('harga-per-malam').value);
    const stokTotalDasar = parseInt(document.getElementById('stok-total').value);
    const pesanStok = document.getElementById('pesan-stok');
    const pilihUkuran = document.getElementById('pilih-ukuran');

    // Untuk barang Pakaian Outdoor, batas stok mengikuti ukuran yang dipilih
    // (bukan total gabungan semua ukuran). Barang lain tetap pakai stok_total.
    function stokMaksimalSaatIni() {
        if (!pilihUkuran) {
            return stokTotalDasar;
        }
        const opsi = pilihUkuran.selectedOptions[0];
        return opsi ? parseInt(opsi.dataset.stok, 10) || 0 : 0;
    }

    if (pilihUkuran) {
        pilihUkuran.addEventListener('change', function () {
            jumlahInput.value = 1;
            jumlahInput.max = stokMaksimalSaatIni();
            hitungKalkulasi();
        });
    }

    function formatRupiah(angka) {
        return 'Rp' + Math.round(angka).toLocaleString('id-ID');
    }

    function hitungKalkulasi() {
        const ambil = tanggalAmbil.value;
        const kembali = tanggalKembali.value;
        const jumlah = parseInt(jumlahInput.value) || 1;

        if (!ambil || !kembali) {
            return;
        }

        const selisihMs = new Date(kembali) - new Date(ambil);
        const durasi = Math.max(0, Math.round(selisihMs / (1000 * 60 * 60 * 24)));

        const total = hargaPerMalam * durasi * jumlah;
        const dpMinimal = total * 0.5;

        document.getElementById('hasil-durasi').textContent = durasi + ' malam';
        document.getElementById('hasil-jumlah').textContent = jumlah + ' unit';
        document.getElementById('hasil-dp').textContent = formatRupiah(dpMinimal);
        document.getElementById('hasil-total').textContent = formatRupiah(total);
    }

    tanggalAmbil.addEventListener('change', function () {
        const ambilDate = new Date(tanggalAmbil.value);
        ambilDate.setDate(ambilDate.getDate() + 1);
        tanggalKembali.min = ambilDate.toISOString().split('T')[0];
        hitungKalkulasi();
    });

    tanggalKembali.addEventListener('change', hitungKalkulasi);

    btnTambah.addEventListener('click', function () {
        const nilai = parseInt(jumlahInput.value);
        if (nilai < stokMaksimalSaatIni()) {
            jumlahInput.value = nilai + 1;
            hitungKalkulasi();
        }
    });

    btnKurang.addEventListener('click', function () {
        const nilai = parseInt(jumlahInput.value);
        if (nilai > 1) {
            jumlahInput.value = nilai - 1;
            hitungKalkulasi();
        }
    });

    document.getElementById('form-reservasi').addEventListener('submit', function (e) {
        e.preventDefault();

        const itemId = document.getElementById('item-id').value;
        const csrfToken = document.getElementById('csrf-token').value;
        const ambil = tanggalAmbil.value;
        const kembali = tanggalKembali.value;
        const jamAmbil = document.getElementById('jam-ambil').value;
        const jumlah = jumlahInput.value;
        const ukuran = pilihUkuran ? pilihUkuran.value : '';

        if (pilihUkuran && !ukuran) {
            pesanStok.style.display = 'block';
            pesanStok.textContent = 'Silakan pilih ukuran terlebih dahulu.';
            return;
        }

        if (!ambil || !kembali) {
            pesanStok.style.display = 'block';
            pesanStok.textContent = 'Silakan pilih tanggal ambil dan tanggal kembali.';
            return;
        }

        if (!jamAmbil) {
            pesanStok.style.display = 'block';
            pesanStok.textContent = 'Silakan pilih jam ambil barang.';
            return;
        }

        pesanStok.style.display = 'none';

        fetch('aksi/cek-stok.php?item_id=' + itemId + '&ambil=' + ambil + '&kembali=' + kembali + '&jumlah=' + jumlah + '&ukuran=' + encodeURIComponent(ukuran))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.tersedia) {
                    pesanStok.style.display = 'block';
                    pesanStok.textContent = 'Stok tidak cukup untuk tanggal yang dipilih. Sisa stok tersedia: ' + data.sisa + '.';
                    return;
                }

                fetch('aksi/tambah-keranjang.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'item_id=' + itemId + '&ambil=' + ambil + '&kembali=' + kembali + '&jam_ambil=' + encodeURIComponent(jamAmbil) + '&jumlah=' + jumlah + '&ukuran=' + encodeURIComponent(ukuran) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(function (response) { return response.json(); })
                .then(function (hasil) {
                    if (hasil.sukses) {
                        window.location.href = 'keranjang.php';
                    } else {
                        pesanStok.style.display = 'block';
                        pesanStok.textContent = hasil.pesan || 'Gagal menambahkan ke keranjang.';
                    }
                });
            });
    });
})();
</script>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>