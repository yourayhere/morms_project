<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/ItemModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$id = (int) ($_GET['id'] ?? 0);
$item = $id ? ItemModel::getByIdAdmin($id) : null;
$gambarList = $id ? ItemModel::getImages($id) : [];
$modeEdit = $item !== null;

$pesanSuksesFoto = isset($_GET['sukses']) && $_GET['sukses'] === 'foto' ? 'Foto berhasil diunggah.' : null;

$pesanErrorFoto = [
    'token'       => 'Token keamanan tidak valid. Silakan coba lagi.',
    'foto_kosong' => 'Pilih minimal satu foto terlebih dahulu.',
    'foto_gagal'  => 'Foto gagal diunggah. Pastikan format JPG/PNG/WEBP dan ukuran maksimal 3MB.',
][$_GET['error'] ?? ''] ?? null;

$daftarKategori = array_unique(array_merge(['tidur', 'hiking', 'masak', 'aksesoris', 'guide', 'pakaian_outdoor'], ItemModel::getSemuaKategori()));
$csrfToken = generate_csrf_token();

// Preset ukuran bawaan per sub kategori pakaian, digabung dengan ukuran yang
// sudah pernah diinput admin sebelumnya (supaya konsisten & tidak duplikat
// beda penulisan tiap kali tambah barang baru).
$presetUkuran = [
    'atasan' => ['S', 'M', 'L', 'XL', 'XXL'],
    'bawahan' => ['28', '30', '32', '34', '36', '38'],
    'alas_kaki' => ['36', '37', '38', '39', '40', '41', '42', '43', '44'],
];
$daftarUkuran = [];
foreach ($presetUkuran as $sub => $preset) {
    $daftarUkuran[$sub] = array_values(array_unique(array_merge($preset, ItemModel::getSemuaUkuran($sub))));
}

$variasiTersimpan = $id ? ItemModel::getVariasi($id) : [];

$judulHalaman = $modeEdit ? 'Edit Barang' : 'Tambah Barang';
$halamanAktif = 'inventaris';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<a href="inventaris.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Inventaris
</a>

<div style="max-width: 640px;">
    <h1 style="font-size: 20px; color: var(--color-primary-dark); margin-bottom: 18px;"><?= $modeEdit ? 'Edit Barang' : 'Tambah Barang Baru' ?></h1>

    <form method="POST" action="aksi/simpan-barang.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
        <?php if ($modeEdit): ?>
            <input type="hidden" name="id" value="<?= $item['id'] ?>">
        <?php endif; ?>

        <div class="card" style="padding: 22px; margin-bottom: 16px;">
            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Nama Barang</label>
                <input type="text" name="nama" required value="<?= htmlspecialchars($item['nama'] ?? '') ?>"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Kategori</label>
                    <select name="kategori" id="select-kategori" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        <?php foreach ($daftarKategori as $kat): ?>
                            <option value="<?= $kat ?>" <?= ($item['kategori'] ?? '') === $kat ? 'selected' : '' ?>><?= label_kategori($kat) ?></option>
                        <?php endforeach; ?>
                        <option value="__tambah_baru__">+ Tambahkan Kategori Baru</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Harga per Malam</label>
                    <input type="text" inputmode="numeric" name="harga_per_malam" id="input-harga-per-malam" required placeholder="Contoh: 15.000" value="<?= isset($item['harga_per_malam']) ? number_format((float) $item['harga_per_malam'], 0, ',', '.') : '' ?>"
                        style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                </div>
            </div>

            <script>
            (function () {
                var inputHarga = document.getElementById('input-harga-per-malam');
                inputHarga.addEventListener('input', function () {
                    var angka = inputHarga.value.replace(/\D/g, '');
                    inputHarga.value = angka === '' ? '' : parseInt(angka, 10).toLocaleString('id-ID');
                });
                inputHarga.closest('form').addEventListener('submit', function () {
                    inputHarga.value = inputHarga.value.replace(/\D/g, '');
                });
            })();
            </script>

            <script>
            (function () {
                var select = document.getElementById('select-kategori');
                select.dataset.terakhir = select.value;

                select.addEventListener('change', function () {
                    if (this.value !== '__tambah_baru__') {
                        this.dataset.terakhir = this.value;
                        return;
                    }

                    var nama = prompt('Nama kategori baru:');
                    nama = nama ? nama.trim() : '';

                    if (!nama) {
                        this.value = this.dataset.terakhir || this.options[0].value;
                        return;
                    }

                    var opsiKhusus = this.querySelector('option[value="__tambah_baru__"]');
                    var existing = Array.prototype.find.call(this.options, function (o) {
                        return o.value !== '__tambah_baru__' && o.value.toLowerCase() === nama.toLowerCase();
                    });

                    if (existing) {
                        this.value = existing.value;
                    } else {
                        var opsiBaru = document.createElement('option');
                        opsiBaru.value = nama;
                        opsiBaru.textContent = nama;
                        this.insertBefore(opsiBaru, opsiKhusus);
                        this.value = nama;
                    }
                    this.dataset.terakhir = this.value;
                });
            })();
            </script>

            <div id="blok-pakaian-outdoor" style="display: none; margin-bottom: 14px; padding: 14px; border: 1px solid var(--color-border); border-radius: 8px; background-color: var(--color-bg);">
                <p style="font-size: 12px; font-weight: 600; margin-bottom: 10px;">Detail Pakaian Outdoor</p>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Sub Kategori</label>
                    <select name="sub_kategori" id="select-sub-kategori" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        <option value="">Pilih sub kategori</option>
                        <option value="atasan" <?= ($item['sub_kategori'] ?? '') === 'atasan' ? 'selected' : '' ?>>Atasan</option>
                        <option value="bawahan" <?= ($item['sub_kategori'] ?? '') === 'bawahan' ? 'selected' : '' ?>>Bawahan</option>
                        <option value="alas_kaki" <?= ($item['sub_kategori'] ?? '') === 'alas_kaki' ? 'selected' : '' ?>>Alas Kaki</option>
                    </select>
                    <p style="font-size: 11px; color: var(--color-text-muted); margin-top: 5px;">Atasan pakai ukuran S/M/L/XL, Bawahan pakai ukuran lingkar pinggang, Alas Kaki pakai nomor sepatu.</p>
                </div>

                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Ukuran & Stok</label>
                <div id="daftar-variasi-ukuran" style="margin-bottom: 8px;"></div>
                <button type="button" id="btn-tambah-variasi" class="btn btn-secondary" style="padding: 6px 14px; font-size: 12.5px;">+ Tambah Ukuran</button>

                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; font-size: 13px; font-weight: 700;">
                    <span>Total Stok (otomatis)</span>
                    <span id="tampil-total-stok-variasi">0</span>
                </div>
            </div>

            <div id="blok-stok-biasa" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Stok Total</label>
                <input type="number" name="stok_total" id="input-stok-total" required min="0" value="<?= $item['stok_total'] ?? '' ?>"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
            </div>

            <script>
            (function () {
                var DAFTAR_UKURAN = <?= json_encode($daftarUkuran) ?>;
                var VARIASI_AWAL = <?= json_encode(array_map(function ($v) {
                    return ['ukuran' => $v['ukuran'], 'stok' => (int) $v['stok']];
                }, $variasiTersimpan)) ?>;
                var SUB_TERPILIH = <?= json_encode($item['sub_kategori'] ?? '') ?>;

                var selectKategori = document.getElementById('select-kategori');
                var blokPakaian = document.getElementById('blok-pakaian-outdoor');
                var blokStokBiasa = document.getElementById('blok-stok-biasa');
                var selectSubKategori = document.getElementById('select-sub-kategori');
                var containerVariasi = document.getElementById('daftar-variasi-ukuran');
                var tampilTotalVariasi = document.getElementById('tampil-total-stok-variasi');
                var inputStokTotal = document.getElementById('input-stok-total');

                function hitungTotalStok() {
                    var total = 0;
                    containerVariasi.querySelectorAll('input[name="variasi_stok[]"]').forEach(function (inp) {
                        total += parseInt(inp.value, 10) || 0;
                    });
                    tampilTotalVariasi.textContent = total;
                    if (selectKategori.value === 'pakaian_outdoor') {
                        inputStokTotal.value = total;
                    }
                }

                function isiOpsiUkuran(select, subKategori, nilaiTerpilih) {
                    select.innerHTML = '';
                    select.appendChild(new Option('Pilih ukuran', ''));
                    (DAFTAR_UKURAN[subKategori] || []).forEach(function (ukuran) {
                        select.appendChild(new Option(ukuran, ukuran));
                    });
                    select.appendChild(new Option('+ Tambahkan Ukuran Baru', '__tambah_ukuran__'));
                    if (nilaiTerpilih) {
                        select.value = nilaiTerpilih;
                    }
                    select.dataset.terakhir = select.value;
                }

                function tanganiPilihUkuran(select) {
                    if (select.value !== '__tambah_ukuran__') {
                        select.dataset.terakhir = select.value;
                        return;
                    }
                    var nama = prompt('Ukuran baru (contoh: XXL, 42, dsb):');
                    nama = nama ? nama.trim() : '';
                    if (!nama) {
                        select.value = select.dataset.terakhir || '';
                        return;
                    }
                    var opsiKhusus = select.querySelector('option[value="__tambah_ukuran__"]');
                    var existing = Array.prototype.find.call(select.options, function (o) {
                        return o.value !== '__tambah_ukuran__' && o.value.toLowerCase() === nama.toLowerCase();
                    });
                    if (existing) {
                        select.value = existing.value;
                    } else {
                        var opsiBaru = document.createElement('option');
                        opsiBaru.value = nama;
                        opsiBaru.textContent = nama;
                        select.insertBefore(opsiBaru, opsiKhusus);
                        select.value = nama;

                        var subAktif = selectSubKategori.value;
                        if (!DAFTAR_UKURAN[subAktif]) {
                            DAFTAR_UKURAN[subAktif] = [];
                        }
                        if (DAFTAR_UKURAN[subAktif].indexOf(nama) === -1) {
                            DAFTAR_UKURAN[subAktif].push(nama);
                        }
                    }
                    select.dataset.terakhir = select.value;
                }

                function tambahBarisVariasi(ukuranTerpilih, stokTerpilih) {
                    var baris = document.createElement('div');
                    baris.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px; align-items: center;';

                    var select = document.createElement('select');
                    select.name = 'variasi_ukuran[]';
                    select.style.cssText = 'flex: 1; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;';
                    isiOpsiUkuran(select, selectSubKategori.value, ukuranTerpilih || '');
                    select.addEventListener('change', function () { tanganiPilihUkuran(this); });

                    var inputStok = document.createElement('input');
                    inputStok.type = 'number';
                    inputStok.name = 'variasi_stok[]';
                    inputStok.min = '0';
                    inputStok.value = stokTerpilih || 0;
                    inputStok.style.cssText = 'width: 90px; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;';
                    inputStok.addEventListener('input', hitungTotalStok);

                    var btnHapus = document.createElement('button');
                    btnHapus.type = 'button';
                    btnHapus.innerHTML = '&times;';
                    btnHapus.title = 'Hapus ukuran ini';
                    btnHapus.style.cssText = 'width: 34px; height: 34px; flex-shrink: 0; border: 1px solid var(--color-danger); border-radius: 6px; background: none; color: var(--color-danger); cursor: pointer; font-size: 16px;';
                    btnHapus.addEventListener('click', function () {
                        baris.remove();
                        hitungTotalStok();
                    });

                    baris.appendChild(select);
                    baris.appendChild(inputStok);
                    baris.appendChild(btnHapus);
                    containerVariasi.appendChild(baris);
                }

                function perbaruiTampilanPakaian() {
                    var aktif = selectKategori.value === 'pakaian_outdoor';
                    blokPakaian.style.display = aktif ? 'block' : 'none';
                    blokStokBiasa.style.display = aktif ? 'none' : 'block';
                    selectSubKategori.required = aktif;
                    inputStokTotal.required = !aktif;
                    inputStokTotal.readOnly = aktif;
                    if (aktif) {
                        hitungTotalStok();
                    }
                }

                document.getElementById('btn-tambah-variasi').addEventListener('click', function () {
                    tambahBarisVariasi('', 0);
                });

                selectSubKategori.addEventListener('change', function () {
                    containerVariasi.innerHTML = '';
                    tambahBarisVariasi('', 0);
                    hitungTotalStok();
                });

                selectKategori.addEventListener('change', perbaruiTampilanPakaian);
                perbaruiTampilanPakaian();

                if (VARIASI_AWAL.length > 0) {
                    VARIASI_AWAL.forEach(function (v) { tambahBarisVariasi(v.ukuran, v.stok); });
                } else if (SUB_TERPILIH) {
                    tambahBarisVariasi('', 0);
                }
                hitungTotalStok();
            })();
            </script>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Deskripsi</label>
                <textarea name="deskripsi" rows="3" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; resize: vertical;"><?= htmlspecialchars($item['deskripsi'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Syarat Penyewaan</label>
                <textarea name="syarat_penyewaan" rows="2" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; resize: vertical;"><?= htmlspecialchars($item['syarat_penyewaan'] ?? 'Wajib menyertakan identitas asli (KTP/SIM) saat pengambilan barang.') ?></textarea>
            </div>

            <div>
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px;">Status</label>
                <select name="status" style="width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                    <option value="aktif" <?= ($item['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (bisa disewa)</option>
                    <option value="maintenance" <?= ($item['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance / Rusak (sembunyikan sementara)</option>
                    <option value="nonaktif" <?= ($item['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
                <p style="font-size: 11px; color: var(--color-text-muted); margin-top: 5px;">Barang berstatus Maintenance atau Nonaktif tidak akan muncul atau bisa disewa oleh customer.</p>
            </div>
        </div>

        <?php if (!$modeEdit): ?>
            <div class="card" style="padding: 22px; margin-bottom: 16px;">
                <h3 style="font-size: 14px; margin-bottom: 12px;">Foto Barang</h3>
                <div class="img-upload-grid" id="grid-foto-baru"></div>
                <label for="foto" style="display: flex; flex-direction: column; align-items: center; gap: 8px; border: 1.5px dashed var(--color-border); border-radius: 8px; padding: 22px; cursor: pointer; background-color: var(--color-bg);">
                    <svg class="icon" style="width: 22px; height: 22px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                    <span id="label-foto" style="font-size: 12.5px; color: var(--color-text-muted);">Klik untuk unggah foto produk (boleh lebih dari satu)</span>
                </label>
                <input type="file" id="foto" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple style="display: none;">
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
            <?= $modeEdit ? 'Simpan Perubahan' : 'Tambah Barang' ?>
        </button>
    </form>

    <?php if ($modeEdit): ?>
        <div class="card" style="padding: 22px; margin-top: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Foto Barang</h3>

            <?php if ($pesanSuksesFoto): ?>
                <div class="alert alert-success" style="margin-bottom: 14px;">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                    <?= htmlspecialchars($pesanSuksesFoto) ?>
                </div>
            <?php endif; ?>

            <?php if ($pesanErrorFoto): ?>
                <div class="alert alert-danger" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($pesanErrorFoto) ?>
                </div>
            <?php endif; ?>

            <div class="img-upload-grid" id="grid-foto-existing">
                <?php foreach ($gambarList as $gambar): ?>
                    <div class="img-upload-thumb">
                        <?php if (file_exists(__DIR__ . '/' . $gambar['url'])): ?>
                            <img src="<?= htmlspecialchars($gambar['url']) ?>" alt="Foto produk">
                        <?php endif; ?>
                        <form method="POST" action="aksi/hapus-gambar-barang.php" onsubmit="return confirm('Hapus foto ini?');">
                            <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                            <input type="hidden" name="image_id" value="<?= $gambar['id'] ?>">
                            <button type="submit" class="img-upload-delete-btn" title="Hapus foto" aria-label="Hapus foto">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($gambarList)): ?>
                <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 12px;">Foto baru akan ditambahkan ke galeri yang sudah ada.</p>
            <?php endif; ?>

            <form method="POST" action="aksi/tambah-foto-barang.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                <div class="img-upload-grid" id="grid-foto-baru"></div>

                <label for="foto" style="display: flex; flex-direction: column; align-items: center; gap: 8px; border: 1.5px dashed var(--color-border); border-radius: 8px; padding: 22px; cursor: pointer; background-color: var(--color-bg); margin-bottom: 12px;">
                    <svg class="icon" style="width: 22px; height: 22px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                    <span id="label-foto" style="font-size: 12.5px; color: var(--color-text-muted);">Klik untuk pilih foto produk (boleh lebih dari satu)</span>
                </label>
                <input type="file" id="foto" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple required style="display: none;">

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                    Unggah Foto
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($modeEdit): ?>
        <?php
        $jumlahRiwayat = ItemModel::getJumlahRiwayatSewa($id);
        $pesanErrorHapus = [
            'konfirmasi'  => 'Teks konfirmasi tidak sesuai. Ketik "CONFIRM" persis untuk menghapus barang ini.',
            'ada_riwayat' => 'Barang ini tidak dapat dihapus permanen karena sudah pernah masuk riwayat sewa/transaksi. Nonaktifkan saja lewat status di halaman Inventaris agar data laporan lama tetap utuh.',
            'token'       => 'Token keamanan tidak valid. Silakan coba lagi.',
        ][$_GET['error'] ?? ''] ?? null;
        ?>
        <div class="card" style="padding: 24px; margin-top: 28px; border-color: var(--danger-300, #EEC4C0);">
            <h3 style="font-size: 14px; margin-bottom: 4px; color: var(--color-danger, #B3432F);">Hapus Barang</h3>
            <p style="font-size: 12px; color: var(--warm-400, var(--color-text-muted)); margin-bottom: 16px;">Menghapus barang ini bersifat permanen dan tidak dapat dibatalkan, termasuk seluruh foto dan data ukurannya.</p>

            <?php if ($pesanErrorHapus): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;">
                    <?= htmlspecialchars($pesanErrorHapus) ?>
                </div>
            <?php endif; ?>

            <?php if ($jumlahRiwayat > 0): ?>
                <p style="font-size: 12.5px; color: var(--color-text-muted);">
                    Barang ini sudah <?= $jumlahRiwayat ?>x masuk riwayat sewa, sehingga tidak dapat dihapus permanen (data laporan/transaksi lama harus tetap utuh). Gunakan toggle status di halaman Inventaris untuk menonaktifkannya.
                </p>
            <?php else: ?>
                <button type="button" id="btn-buka-hapus-barang" class="btn btn-secondary" style="color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F);">
                    <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                    Hapus Barang Ini
                </button>

                <div id="blok-konfirmasi-hapus-barang" style="display: none; margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--color-border);">
                    <p style="font-size: 13px; font-weight: 600; margin-bottom: 6px;">Are you sure?</p>
                    <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 12px;">
                        Ketik <strong>CONFIRM</strong> untuk menghapus barang <strong><?= htmlspecialchars($item['nama']) ?></strong> secara permanen.
                    </p>
                    <form method="POST" action="aksi/hapus-barang.php">
                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <input type="text" id="input-konfirmasi-hapus-barang" class="form-input" placeholder='Ketik "CONFIRM"' autocomplete="off" style="margin-bottom: 12px; max-width: 260px;">
                        <input type="hidden" name="konfirmasi" id="input-konfirmasi-hidden-barang">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" id="btn-hapus-permanen-barang" class="btn btn-primary" disabled style="background-color: var(--color-danger, #B3432F); border-color: var(--color-danger, #B3432F); opacity: 0.5; cursor: not-allowed;">
                                Hapus Permanen
                            </button>
                            <button type="button" id="btn-batal-hapus-barang" class="btn btn-secondary">Batal</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="assets/js/image-uploader.js"></script>
<script>
MormsImageUploader.pasangPreview(
    document.getElementById('foto'),
    document.getElementById('grid-foto-baru'),
    {
        onChange: function (jumlah) {
            document.getElementById('label-foto').textContent = jumlah > 0
                ? jumlah + ' berkas dipilih'
                : 'Klik untuk pilih foto produk (boleh lebih dari satu)';
        }
    }
);

var btnBukaHapusBarang = document.getElementById('btn-buka-hapus-barang');
if (btnBukaHapusBarang) {
    var blokKonfirmasiBarang = document.getElementById('blok-konfirmasi-hapus-barang');
    var inputKonfirmasiBarang = document.getElementById('input-konfirmasi-hapus-barang');
    var inputKonfirmasiHiddenBarang = document.getElementById('input-konfirmasi-hidden-barang');
    var btnHapusPermanenBarang = document.getElementById('btn-hapus-permanen-barang');
    var btnBatalHapusBarang = document.getElementById('btn-batal-hapus-barang');

    btnBukaHapusBarang.addEventListener('click', function () {
        btnBukaHapusBarang.style.display = 'none';
        blokKonfirmasiBarang.style.display = 'block';
        inputKonfirmasiBarang.focus();
    });

    btnBatalHapusBarang.addEventListener('click', function () {
        blokKonfirmasiBarang.style.display = 'none';
        btnBukaHapusBarang.style.display = 'inline-flex';
        inputKonfirmasiBarang.value = '';
        perbaruiTombolHapusBarang();
    });

    function perbaruiTombolHapusBarang() {
        var cocok = inputKonfirmasiBarang.value === 'CONFIRM';
        inputKonfirmasiHiddenBarang.value = inputKonfirmasiBarang.value;
        btnHapusPermanenBarang.disabled = !cocok;
        btnHapusPermanenBarang.style.opacity = cocok ? '1' : '0.5';
        btnHapusPermanenBarang.style.cursor = cocok ? 'pointer' : 'not-allowed';
    }

    inputKonfirmasiBarang.addEventListener('input', perbaruiTombolHapusBarang);
}
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>