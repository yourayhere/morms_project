<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/Cart.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/toko.php';

use App\Core\Session;
use App\Core\Cart;

Session::start();

$tokoTutup = toko_sedang_tutup();
$daftarKeranjang = Cart::getAll();
$totalKeseluruhan = Cart::getTotal();
$envelope = Cart::getEnvelope();
$csrfToken = generate_csrf_token();

$judulHalaman = 'Keranjang';
$halamanAktif = 'keranjang';
require __DIR__ . '/app/Views/partials/header.php';

?>

<section style="padding: 32px 0 56px;">
    <div class="container" style="max-width: 760px;">

        <h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 6px;">Keranjang Sewa</h1>

        <?php if (!empty($daftarKeranjang)): ?>
            <p id="ringkasan-envelope" style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">
                Sewa berlangsung <span id="envelope-text"><?= format_periode_sewa($envelope['ambil'], $envelope['kembali'], $envelope['jam'], $envelope['jam_kembali']) ?></span><span id="envelope-hint"><?= count($daftarKeranjang) > 1 ? ' - tiap barang bisa punya tanggal sewa sendiri, lihat rincian di bawah.' : '' ?></span>
            </p>
        <?php endif; ?>

        <?php if (empty($daftarKeranjang)): ?>
            <div class="card" style="padding: 40px; text-align: center;">
                <svg class="icon" style="width: 40px; height: 40px; color: var(--color-primary-light); margin-bottom: 12px;"><use href="assets/icons/sprite.svg#icon-cart"></use></svg>
                <p style="color: var(--color-text-muted); margin-bottom: 16px;">Keranjang masih kosong.</p>
                <a href="katalog.php" class="btn btn-primary">Lihat Katalog</a>
            </div>
        <?php else: ?>

            <div id="daftar-item">
                <?php foreach ($daftarKeranjang as $index => $barang): ?>
                    <div class="card item-keranjang" data-index="<?= $index ?>" data-stok="<?= 999 ?>" data-harga="<?= $barang['harga_per_malam'] ?>" style="padding: 18px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                            <div style="flex: 1;">
                                <h3 style="font-size: 15px; margin-bottom: 4px;"><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran'])): ?> <span style="font-weight: 400; color: var(--color-text-muted);">(Ukuran <?= htmlspecialchars($barang['ukuran']) ?>)</span><?php endif; ?></h3>
                                <p class="durasi-tampil" style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 2px;"><?= $barang['durasi'] ?> malam &times; <?= format_rupiah($barang['harga_per_malam']) ?></p>
                                <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span class="periode-tampil"><?= htmlspecialchars(format_periode_sewa($barang['tanggal_ambil'], $barang['tanggal_kembali'], $barang['jam_ambil'] ?? null)) ?></span>
                                    <button type="button" class="btn-toggle-tanggal" style="background: none; border: none; color: var(--color-accent); cursor: pointer; font-weight: 600; font-size: 11.5px; padding: 0;">Ubah Tanggal</button>
                                </p>

                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <button type="button" class="btn-kurang-item btn btn-secondary" style="padding: 6px 9px;">
                                        <svg class="icon" style="width: 14px; height: 14px;"><use href="assets/icons/sprite.svg#icon-minus"></use></svg>
                                    </button>
                                    <span class="jumlah-tampil" style="min-width: 24px; text-align: center; font-size: 14px; font-weight: 600;"><?= $barang['jumlah'] ?></span>
                                    <button type="button" class="btn-tambah-item btn btn-secondary" style="padding: 6px 9px;">
                                        <svg class="icon" style="width: 14px; height: 14px;"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
                                    </button>
                                </div>
                            </div>

                            <div style="text-align: right;">
                                <p class="subtotal-tampil" style="font-size: 15px; font-weight: 700; color: var(--color-primary-dark); margin-bottom: 10px;"><?= format_rupiah($barang['subtotal']) ?></p>
                                <button type="button" class="btn-hapus-item" style="background: none; border: none; color: var(--color-danger); cursor: pointer; display: flex; align-items: center; gap: 4px; font-size: 12px; margin-left: auto;">
                                    <svg class="icon" style="width: 15px; height: 15px;"><use href="assets/icons/sprite.svg#icon-trash"></use></svg>
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <div class="panel-ubah-tanggal" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--color-border);">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px;">Tanggal Ambil</label>
                                    <input type="date" class="input-ambil" value="<?= htmlspecialchars($barang['tanggal_ambil']) ?>" min="<?= date('Y-m-d') ?>"
                                        style="width: 100%; padding: 7px 9px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px;">Tanggal Kembali</label>
                                    <input type="date" class="input-kembali" value="<?= htmlspecialchars($barang['tanggal_kembali']) ?>" min="<?= date('Y-m-d') ?>"
                                        style="width: 100%; padding: 7px 9px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px;">Jam Ambil</label>
                                    <input type="time" class="input-jam" value="<?= htmlspecialchars($barang['jam_ambil'] ?? '09:00') ?>"
                                        style="width: 100%; padding: 7px 9px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px;">
                                </div>
                            </div>
                            <p class="pesan-error-tanggal" style="display: none; font-size: 12px; color: var(--color-danger); margin-bottom: 10px;"></p>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn-simpan-tanggal btn btn-primary btn-sm">Simpan</button>
                                <button type="button" class="btn-batal-tanggal btn btn-secondary btn-sm">Batal</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <a href="katalog.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-accent); font-size: 13px; font-weight: 600; margin-bottom: 24px;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
                Tambah Barang Lain
            </a>

            <div class="card" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 15px; margin-bottom: 16px;">
                    <span>Total Sewa</span>
                    <strong id="total-keseluruhan" style="color: var(--color-accent);"><?= format_rupiah($totalKeseluruhan) ?></strong>
                </div>
                <?php if ($tokoTutup): ?>
                    <button type="button" class="btn btn-primary" disabled style="width: 100%; justify-content: center; opacity: 0.5; cursor: not-allowed;">
                        Lanjut ke Checkout
                        <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                    </button>
                    <p style="font-size: 11.5px; color: var(--color-text-muted); text-align: center; margin-top: 8px;">Checkout belum bisa dilanjutkan selama toko tutup.</p>
                <?php else: ?>
                    <a href="checkout.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        Lanjut ke Checkout
                        <svg class="icon" style="width: 18px; height: 18px;"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</section>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;

function formatRupiah(angka) {
    return 'Rp' + Math.round(angka).toLocaleString('id-ID');
}

document.querySelectorAll('.item-keranjang').forEach(function (kartu) {
    const index = kartu.dataset.index;
    const jumlahTampil = kartu.querySelector('.jumlah-tampil');
    const subtotalTampil = kartu.querySelector('.subtotal-tampil');

    function kirimUpdate(jumlahBaru) {
        fetch('aksi/update-keranjang.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'index=' + index + '&jumlah=' + jumlahBaru + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.sukses) {
                jumlahTampil.textContent = data.jumlah;
                subtotalTampil.textContent = formatRupiah(data.subtotal);
                document.getElementById('total-keseluruhan').textContent = formatRupiah(data.total_keseluruhan);
            }
        });
    }

    kartu.querySelector('.btn-tambah-item').addEventListener('click', function () {
        kirimUpdate(parseInt(jumlahTampil.textContent) + 1);
    });

    kartu.querySelector('.btn-kurang-item').addEventListener('click', function () {
        const nilai = parseInt(jumlahTampil.textContent);
        if (nilai > 1) {
            kirimUpdate(nilai - 1);
        }
    });

    kartu.querySelector('.btn-hapus-item').addEventListener('click', function () {
        fetch('aksi/hapus-keranjang.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'index=' + index + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function () { window.location.reload(); });
    });

    // --- Ubah Tanggal (per barang) ---
    const hargaPerMalam = parseFloat(kartu.dataset.harga);
    const panel = kartu.querySelector('.panel-ubah-tanggal');
    const tombolToggle = kartu.querySelector('.btn-toggle-tanggal');
    const periodeTampil = kartu.querySelector('.periode-tampil');
    const durasiTampil = kartu.querySelector('.durasi-tampil');
    const inputAmbil = kartu.querySelector('.input-ambil');
    const inputKembali = kartu.querySelector('.input-kembali');
    const inputJam = kartu.querySelector('.input-jam');
    const pesanError = kartu.querySelector('.pesan-error-tanggal');
    let nilaiAwal = { ambil: inputAmbil.value, kembali: inputKembali.value, jam: inputJam.value };

    tombolToggle.addEventListener('click', function () {
        const tampil = panel.style.display !== 'none';
        panel.style.display = tampil ? 'none' : 'block';
        tombolToggle.textContent = tampil ? 'Ubah Tanggal' : 'Sembunyikan';
    });

    kartu.querySelector('.btn-batal-tanggal').addEventListener('click', function () {
        inputAmbil.value = nilaiAwal.ambil;
        inputKembali.value = nilaiAwal.kembali;
        inputJam.value = nilaiAwal.jam;
        pesanError.style.display = 'none';
        panel.style.display = 'none';
        tombolToggle.textContent = 'Ubah Tanggal';
    });

    kartu.querySelector('.btn-simpan-tanggal').addEventListener('click', function () {
        pesanError.style.display = 'none';

        if (!inputAmbil.value || !inputKembali.value || inputKembali.value <= inputAmbil.value) {
            pesanError.textContent = 'Tanggal kembali harus setelah tanggal ambil.';
            pesanError.style.display = 'block';
            return;
        }

        fetch('aksi/ubah-tanggal-keranjang.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'index=' + index + '&ambil=' + inputAmbil.value + '&kembali=' + inputKembali.value + '&jam_ambil=' + encodeURIComponent(inputJam.value) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.sukses) {
                pesanError.textContent = data.pesan || 'Gagal mengubah tanggal.';
                pesanError.style.display = 'block';
                return;
            }

            nilaiAwal = { ambil: data.tanggal_ambil, kembali: data.tanggal_kembali, jam: data.jam_ambil };
            periodeTampil.textContent = data.periode_text;
            durasiTampil.textContent = data.durasi + ' malam × ' + formatRupiah(hargaPerMalam);
            subtotalTampil.textContent = formatRupiah(data.subtotal);
            document.getElementById('total-keseluruhan').textContent = formatRupiah(data.total_keseluruhan);

            const envelopeText = document.getElementById('envelope-text');
            if (envelopeText) {
                envelopeText.textContent = data.envelope_text;
            }

            panel.style.display = 'none';
            tombolToggle.textContent = 'Ubah Tanggal';
        });
    });
});
</script>

<?php require __DIR__ . '/app/Views/partials/footer.php'; ?>