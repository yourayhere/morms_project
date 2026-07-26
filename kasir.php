<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/CartKasir.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/kasir.php';
require_once __DIR__ . '/app/Models/ItemModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\CartKasir;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$daftarBarang = ItemModel::getSemuaAktifDenganStok();
$keranjangKasir = CartKasir::getAll();
$totalKasir = CartKasir::getTotal();
$periode = CartKasir::getPeriode();
$csrfToken = generate_csrf_token();

$judulHalaman = 'Kasir';
$halamanAktif = 'kasir';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Kasir / Point of Sale</h1>
<p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">Transaksi langsung untuk customer yang datang ke toko.</p>

<div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px;">

    <div>
        <div class="card" style="padding: 18px; margin-bottom: 16px;">
            <div style="position: relative;">
                <svg class="icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 17px; height: 17px; color: var(--color-text-muted);"><use href="assets/icons/sprite.svg#icon-search"></use></svg>
                <input type="text" id="cari-barang" placeholder="Cari nama barang..."
                    style="width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
            </div>
        </div>

        <div id="daftar-barang-kasir" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
            <?php foreach ($daftarBarang as $barang): ?>
                <?php $isPakaianKasir = $barang['kategori'] === 'pakaian_outdoor'; ?>
                <?php $variasiKasir = $isPakaianKasir ? ItemModel::getVariasi($barang['id']) : []; ?>
                <div class="card item-kasir" data-nama="<?= strtolower(htmlspecialchars($barang['nama'])) ?>" style="padding: 14px;">
                    <h4 style="font-size: 13.5px; margin-bottom: 4px;"><?= htmlspecialchars($barang['nama']) ?></h4>
                    <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 10px;"><?= format_rupiah((float) $barang['harga_per_malam']) ?> / malam</p>
                    <?php if ($isPakaianKasir): ?>
                        <select class="pilih-ukuran-kasir form-control" style="width: 100%; padding: 7px 8px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 12.5px; margin-bottom: 8px;">
                            <option value="">Pilih ukuran</option>
                            <?php foreach ($variasiKasir as $v): ?>
                                <option value="<?= htmlspecialchars($v['ukuran']) ?>" <?= (int) $v['stok'] <= 0 ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($v['ukuran']) ?><?= (int) $v['stok'] <= 0 ? ' (Stok Habis)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="button" class="btn-pilih-barang btn btn-secondary" style="width: 100%; justify-content: center; padding: 7px;"
                        data-id="<?= $barang['id'] ?>" data-nama="<?= htmlspecialchars($barang['nama']) ?>" data-harga="<?= $barang['harga_per_malam'] ?>" data-stok="<?= $barang['stok_total'] ?>">
                        <svg class="icon" style="width: 14px; height: 14px;"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
                        Tambah
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="btn-tampilkan-semua-kasir" class="btn btn-secondary" style="display: none; width: 100%; justify-content: center; margin-top: 12px;">
            Tampilkan Semua Barang
        </button>
    </div>

    <div>
        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Periode Sewa</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 5px;">Tanggal Ambil</label>
                    <input type="date" id="tanggal-ambil-kasir" value="<?= $periode['ambil'] ?? date('Y-m-d') ?>"
                        style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 5px;">Tanggal Kembali</label>
                    <input type="date" id="tanggal-kembali-kasir" value="<?= $periode['kembali'] ?? date('Y-m-d', strtotime('+1 day')) ?>"
                        style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
                </div>
            </div>
            <div>
                <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 5px;">Jam Ambil</label>
                <input type="time" id="jam-ambil-kasir" value="<?= $periode['jam'] ?? '09:00' ?>"
                    style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
                <p style="font-size: 11px; color: var(--color-text-muted); margin-top: 5px;">Batas pengembalian barang penyewaan otomatis mengikuti jam ambil ini. Tiap barang boleh punya periode sewa sendiri - ubah tanggal/jam di atas sebelum menekan "Tambah" untuk barang berikutnya kalau durasinya berbeda.</p>
            </div>
        </div>

        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Keranjang</h3>
            <div id="daftar-keranjang-kasir">
                <?php require __DIR__ . '/app/Views/partials/kasir-keranjang-list.php'; ?>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 15px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--color-border);">
                <span>Total</span>
                <strong id="total-kasir" style="color: var(--color-accent);"><?= format_rupiah($totalKasir) ?></strong>
            </div>
        </div>

        <a href="checkout-kasir.php" class="btn btn-primary" style="width: 100%; justify-content: center; <?= empty($keranjangKasir) ? 'opacity: 0.5; pointer-events: none;' : '' ?>" id="btn-lanjut-checkout">
            Lanjut ke Data Penyewa
            <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
        </a>
    </div>

</div>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;
const elKeranjang = document.getElementById('daftar-keranjang-kasir');
const elTotal = document.getElementById('total-kasir');
const btnLanjutCheckout = document.getElementById('btn-lanjut-checkout');

const inputCari = document.getElementById('cari-barang');
const btnTampilkanSemuaKasir = document.getElementById('btn-tampilkan-semua-kasir');
const semuaKartuBarang = Array.from(document.querySelectorAll('.item-kasir'));
const BATAS_BARANG_MOBILE = 5;

// Di layar mobile, daftar barang dibatasi 5 dulu (biar tidak perlu scroll
// panjang sebelum sampai ke Periode Sewa/Keranjang) - tombol "Tampilkan
// Semua" membuka sisanya. Batas ini otomatis diabaikan begitu user mengetik
// pencarian, supaya hasil pencarian tidak ikut terpotong 5 barang.
let batasBarangAktif = window.matchMedia('(max-width: 768px)').matches && semuaKartuBarang.length > BATAS_BARANG_MOBILE;

function perbaruiTampilanBarang() {
    const kata = inputCari.value.toLowerCase();
    const batasBerlaku = batasBarangAktif && kata === '';
    let ditampilkan = 0;

    semuaKartuBarang.forEach(function (kartu) {
        if (!kartu.dataset.nama.includes(kata)) {
            kartu.style.display = 'none';
            return;
        }
        const disembunyikanKarenaBatas = batasBerlaku && ditampilkan >= BATAS_BARANG_MOBILE;
        kartu.style.display = disembunyikanKarenaBatas ? 'none' : 'block';
        if (!disembunyikanKarenaBatas) {
            ditampilkan++;
        }
    });

    btnTampilkanSemuaKasir.style.display = batasBerlaku ? 'block' : 'none';
}

inputCari.addEventListener('input', perbaruiTampilanBarang);

btnTampilkanSemuaKasir.addEventListener('click', function () {
    batasBarangAktif = false;
    perbaruiTampilanBarang();
});

perbaruiTampilanBarang();

function ambilTanggal() {
    return {
        ambil: document.getElementById('tanggal-ambil-kasir').value,
        kembali: document.getElementById('tanggal-kembali-kasir').value,
        jam: document.getElementById('jam-ambil-kasir').value,
    };
}

// Satu fungsi dipakai bersama oleh tambah/hapus/ubah jumlah - semuanya
// mengembalikan bentuk balasan yang sama (html_keranjang + total +
// jumlah_baris), jadi cukup satu tempat untuk menyuntikkannya ke DOM tanpa
// reload halaman sama sekali.
function perbaruiTampilanKeranjang(data) {
    if (data.html_keranjang !== undefined) {
        elKeranjang.innerHTML = data.html_keranjang;
    }
    if (data.total !== undefined) {
        elTotal.textContent = data.total;
    }
    if (data.jumlah_baris !== undefined) {
        const kosong = data.jumlah_baris === 0;
        btnLanjutCheckout.style.opacity = kosong ? '0.5' : '1';
        btnLanjutCheckout.style.pointerEvents = kosong ? 'none' : 'auto';
    }
}

function kirimAksiKasir(url, bodyTambahan) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: bodyTambahan + '&csrf_token=' + encodeURIComponent(csrfToken)
    }).then(function (res) { return res.json(); });
}

document.querySelectorAll('.btn-pilih-barang').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        const tanggal = ambilTanggal();
        if (!tanggal.ambil || !tanggal.kembali) {
            alert('Tentukan periode sewa terlebih dahulu.');
            return;
        }

        if (!tanggal.jam) {
            alert('Tentukan jam ambil terlebih dahulu.');
            return;
        }

        const selectUkuran = tombol.closest('.item-kasir').querySelector('.pilih-ukuran-kasir');
        const ukuran = selectUkuran ? selectUkuran.value : '';

        if (selectUkuran && !ukuran) {
            alert('Pilih ukuran terlebih dahulu.');
            return;
        }

        kirimAksiKasir('aksi/tambah-kasir.php', 'item_id=' + tombol.dataset.id + '&ambil=' + tanggal.ambil + '&kembali=' + tanggal.kembali + '&jam_ambil=' + encodeURIComponent(tanggal.jam) + '&ukuran=' + encodeURIComponent(ukuran))
            .then(function (data) {
                if (data.sukses) {
                    perbaruiTampilanKeranjang(data);
                } else {
                    alert(data.pesan || 'Gagal menambahkan barang.');
                }
            });
    });
});

// Event delegation: tombol Hapus/+/- ada di dalam #daftar-keranjang-kasir
// yang isinya diganti total lewat innerHTML setiap kali keranjang berubah,
// jadi listener dipasang di elemen induknya (bukan di tiap tombol) supaya
// tetap berfungsi walau tombolnya baru saja "lahir" dari render ulang.
elKeranjang.addEventListener('click', function (e) {
    const btnHapus = e.target.closest('.btn-hapus-kasir');
    const btnTambah = e.target.closest('.btn-qty-tambah');
    const btnKurang = e.target.closest('.btn-qty-kurang');
    const btnToggleTanggal = e.target.closest('.btn-toggle-tanggal-kasir');
    const btnBatalTanggal = e.target.closest('.btn-batal-tanggal-kasir');
    const btnSimpanTanggal = e.target.closest('.btn-simpan-tanggal-kasir');

    if (btnHapus) {
        kirimAksiKasir('aksi/hapus-kasir.php', 'index=' + btnHapus.dataset.index)
            .then(perbaruiTampilanKeranjang);
        return;
    }

    if (btnTambah || btnKurang) {
        const tombol = btnTambah || btnKurang;
        const arah = btnTambah ? 'plus' : 'minus';
        kirimAksiKasir('aksi/ubah-jumlah-kasir.php', 'index=' + tombol.dataset.index + '&arah=' + arah)
            .then(function (data) {
                perbaruiTampilanKeranjang(data);
                if (!data.sukses && data.pesan) {
                    alert(data.pesan);
                }
            });
        return;
    }

    if (btnToggleTanggal) {
        const panel = elKeranjang.querySelector('.panel-ubah-tanggal-kasir[data-index="' + btnToggleTanggal.dataset.index + '"]');
        const tampil = panel.style.display !== 'none';
        panel.style.display = tampil ? 'none' : 'block';
        btnToggleTanggal.textContent = tampil ? 'Ubah Tanggal' : 'Sembunyikan';
        return;
    }

    if (btnBatalTanggal) {
        const panel = e.target.closest('.panel-ubah-tanggal-kasir');
        panel.querySelectorAll('input').forEach(function (input) { input.value = input.defaultValue; });
        panel.querySelector('.pesan-error-tanggal-kasir').style.display = 'none';
        panel.style.display = 'none';
        elKeranjang.querySelector('.btn-toggle-tanggal-kasir[data-index="' + btnBatalTanggal.dataset.index + '"]').textContent = 'Ubah Tanggal';
        return;
    }

    if (btnSimpanTanggal) {
        const panel = e.target.closest('.panel-ubah-tanggal-kasir');
        const inputAmbil = panel.querySelector('.input-ambil-kasir');
        const inputKembali = panel.querySelector('.input-kembali-kasir');
        const inputJam = panel.querySelector('.input-jam-kasir');
        const pesanError = panel.querySelector('.pesan-error-tanggal-kasir');
        pesanError.style.display = 'none';

        if (!inputAmbil.value || !inputKembali.value || inputKembali.value <= inputAmbil.value) {
            pesanError.textContent = 'Tanggal kembali harus setelah tanggal ambil.';
            pesanError.style.display = 'block';
            return;
        }

        kirimAksiKasir('aksi/ubah-tanggal-kasir.php', 'index=' + btnSimpanTanggal.dataset.index + '&ambil=' + inputAmbil.value + '&kembali=' + inputKembali.value + '&jam_ambil=' + encodeURIComponent(inputJam.value))
            .then(function (data) {
                if (!data.sukses) {
                    pesanError.textContent = data.pesan || 'Gagal mengubah tanggal.';
                    pesanError.style.display = 'block';
                    return;
                }
                perbaruiTampilanKeranjang(data);
            });
    }
});
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>