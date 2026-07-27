<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Helpers/paths.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TransactionModel.php';
require_once __DIR__ . '/app/Models/ItemModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TransactionModel;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$id = (int) ($_GET['id'] ?? 0);
$booking = BookingModel::getDetailLengkap($id);

if (!$booking) {
    header('Location: penyewaan-aktif.php');
    exit;
}

$itemBooking = BookingModel::getItemBooking($id);
$totalSewa = array_sum(array_column($itemBooking, 'subtotal'));
$riwayatTransaksi = TransactionModel::getByBookingId($id);
$totalDibayar = 0;
foreach ($riwayatTransaksi as $trx) {
    if ($trx['status_verifikasi'] !== 'ditolak') {
        $totalDibayar += (float) $trx['nominal'];
    }
}
$sisaPembayaran = max(0, $totalSewa - $totalDibayar);

$namaPemesan = $booking['nama_member'] ?? $booking['guest_nama'];
$itemMasihDisewa = array_values(array_filter($itemBooking, fn($barang) => $barang['status'] === 'disewa'));
$daftarBarangTambahan = ItemModel::getSemuaAktifDenganStok();
$petaVariasiTambahan = [];
foreach ($daftarBarangTambahan as $barangTambahan) {
    if ($barangTambahan['kategori'] === 'pakaian_outdoor') {
        $petaVariasiTambahan[$barangTambahan['id']] = ItemModel::getVariasi($barangTambahan['id']);
    }
}
$csrfToken = generate_csrf_token();

$pesanSuksesIdentitas = isset($_GET['sukses']) ? 'Berkas identitas berhasil dilengkapi.' : null;
$pesanErrorIdentitas = [
    'token'     => 'Token keamanan tidak valid. Silakan coba lagi.',
    'identitas' => 'Berkas identitas wajib diunggah dengan format dan ukuran yang sesuai.',
][$_GET['error'] ?? ''] ?? null;

$judulHalaman = 'Kelola Penyewaan';
$halamanAktif = 'penyewaan';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<a href="penyewaan-aktif.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Penyewaan Aktif
</a>

<?php if ($pesanSuksesIdentitas): ?>
    <div class="alert alert-success" style="margin-bottom: 16px;">
        <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
        <?= htmlspecialchars($pesanSuksesIdentitas) ?>
    </div>
<?php endif; ?>

<?php if ($pesanErrorIdentitas): ?>
    <div class="alert alert-danger" style="margin-bottom: 16px;">
        <?= htmlspecialchars($pesanErrorIdentitas) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px;">

    <div>
        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div>
                    <p style="font-size: 11px; color: var(--color-text-muted);">Kode Booking</p>
                    <p style="font-size: 18px; font-weight: 700; color: var(--color-primary-dark);"><?= htmlspecialchars($booking['kode_booking']) ?></p>
                </div>
                <span style="font-size: 12px; background-color: #FBF1EC; color: var(--color-accent-dark); padding: 5px 12px; border-radius: 20px; font-weight: 600;"><?= label_status_booking($booking['status']) ?></span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px;">
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Penyewa</p>
                    <strong><?= htmlspecialchars($namaPemesan) ?></strong>
                </div>
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Rentang Keseluruhan</p>
                    <strong><?= format_tanggal_indo($booking['tanggal_ambil']) ?> - <?= format_tanggal_indo($booking['tanggal_kembali']) ?></strong>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 22px;">
            <h3 style="font-size: 14px; margin-bottom: 4px;">Rincian Barang</h3>
            <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 12px;">Tiap barang punya periode sewa sendiri.</p>
            <?php foreach ($itemBooking as $barang): ?>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; font-size: 13px; padding: 10px 0; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <span><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?> &times; <?= $barang['jumlah'] ?></span>
                        <p style="font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px;">
                            <?= htmlspecialchars(format_periode_sewa($barang['tanggal_ambil'], $barang['tanggal_kembali'], $barang['jam_ambil'])) ?>
                            &middot;
                            <span style="color: <?= $barang['status'] === 'dikembalikan' ? 'var(--color-success)' : 'var(--color-accent-dark)' ?>; font-weight: 600;">
                                <?= $barang['status'] === 'dikembalikan' ? 'Sudah dikembalikan' : 'Sedang disewa' ?>
                            </span>
                        </p>
                    </div>
                    <strong><?= format_rupiah($barang['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
            <div style="display: flex; justify-content: space-between; font-size: 14px; padding-top: 10px; font-weight: 700;">
                <span>Total Sewa</span>
                <span><?= format_rupiah($totalSewa) ?></span>
            </div>
        </div>

        <div class="card" style="padding: 22px; margin-top: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Jaminan Identitas</h3>
            <?php if ($booking['identitas_file'] && file_exists(storage_path('identitas/' . $booking['identitas_file']))): ?>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <svg class="icon" style="width: 18px; height: 18px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-id-card"></use></svg>
                    <span style="font-size: 13px;">Berkas identitas sudah tersimpan.</span>
                </div>
                <a href="lihat-identitas.php?booking_id=<?= $booking['id'] ?>" class="btn btn-secondary" style="display: inline-flex;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                    Lihat Berkas
                </a>
            <?php else: ?>
                <p style="display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: var(--color-text-muted); margin-bottom: 14px;">
                    <svg class="icon" style="width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; color: var(--color-accent-dark);"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
                    Berkas identitas belum dilengkapi saat transaksi kasir. Unggah sekarang untuk melengkapi jaminan penyewaan ini.
                </p>
                <form method="POST" action="aksi/lengkapi-identitas.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                    <input type="file" name="identitas" accept="image/jpeg,image/png,image/webp" required style="display: block; font-size: 12.5px; margin-bottom: 10px;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg class="icon" style="width: 15px; height: 15px;"><use href="assets/icons/sprite.svg#icon-upload"></use></svg>
                        Unggah Identitas
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if ($booking['status'] === 'SEDANG_DISEWA'): ?>
        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <a href="proses-pengembalian.php?id=<?= $booking['id'] ?>" class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="return confirm('Yakin ingin menyelesaikan penyewaan ini sekarang? Pastikan barang sudah benar-benar dikembalikan oleh penyewa.');">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-return"></use></svg>
                Selesaikan Penyewaan
            </a>
        </div>
        <?php endif; ?>

        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 6px;">Status Pembayaran</h3>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 10px;">
                <span style="color: var(--color-text-muted);">Sudah Dibayar</span>
                <strong style="color: var(--color-success);"><?= format_rupiah($totalDibayar) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 6px;">
                <span style="color: var(--color-text-muted);">Sisa</span>
                <strong style="color: <?= $sisaPembayaran > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;"><?= format_rupiah($sisaPembayaran) ?></strong>
            </div>
        </div>

        <?php if ($sisaPembayaran > 0): ?>
        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 7px;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-money-plus"></use></svg>
                Pelunasan
            </h3>
            <form method="POST" action="aksi/pelunasan-sewa.php">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Jumlah Pelunasan</label>
                <input type="text" inputmode="numeric" name="nominal" id="input-nominal-pelunasan" value="<?= number_format($sisaPembayaran, 0, ',', '.') ?>" required
                    style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; margin-bottom: 10px;">

                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Metode</label>
                <select name="metode" style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; margin-bottom: 14px;">
                    <option value="cash">Tunai</option>
                    <option value="qris">QRIS</option>
                </select>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Catat Pelunasan</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!empty($itemMasihDisewa)): ?>
        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <h3 style="font-size: 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 7px;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-extend"></use></svg>
                Perpanjang Durasi
            </h3>
            <form method="POST" action="aksi/perpanjang-sewa.php">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Barang</label>
                <select name="booking_item_id" id="pilih-item-perpanjang" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; margin-bottom: 10px;">
                    <?php foreach ($itemMasihDisewa as $barang): ?>
                        <option value="<?= $barang['id'] ?>" data-kembali="<?= $barang['tanggal_kembali'] ?>">
                            <?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (<?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?> - kembali <?= format_tanggal_indo($barang['tanggal_kembali']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Tanggal Kembali Baru</label>
                <input type="date" name="tanggal_kembali_baru" id="input-tanggal-perpanjang" required
                    min="<?= date('Y-m-d', strtotime($itemMasihDisewa[0]['tanggal_kembali'] . ' +1 day')) ?>"
                    style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; margin-bottom: 12px;">

                <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 12px;">Biaya tambahan dihitung otomatis dari harga sewa per malam barang yang dipilih.</p>

                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">Perpanjang</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card" style="padding: 20px;">
            <h3 style="font-size: 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 7px;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-plus"></use></svg>
                Tambah Barang
            </h3>
            <form method="POST" action="aksi/tambah-barang-sewa.php">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

                <select name="item_id" id="pilih-barang-tambah" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; margin-bottom: 10px;">
                    <option value="">Pilih barang</option>
                    <?php foreach ($daftarBarangTambahan as $barang): ?>
                        <option value="<?= $barang['id'] ?>" data-pakaian="<?= $barang['kategori'] === 'pakaian_outdoor' ? '1' : '0' ?>"><?= htmlspecialchars($barang['nama']) ?> hingga <?= format_rupiah((float) $barang['harga_per_malam']) ?>/malam</option>
                    <?php endforeach; ?>
                </select>

                <div id="blok-ukuran-tambah" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Ukuran</label>
                    <select name="ukuran" id="pilih-ukuran-tambah" style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
                        <option value="">Pilih ukuran</option>
                    </select>
                </div>

                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Jumlah</label>
                <input type="number" name="jumlah" value="1" min="1" required
                    style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; margin-bottom: 12px;">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Tanggal Ambil</label>
                        <input type="date" name="tanggal_ambil" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Tanggal Kembali</label>
                        <input type="date" name="tanggal_kembali" value="<?= htmlspecialchars($booking['tanggal_kembali']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                </div>

                <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 12px;">Barang tambahan ini punya periode sewa sendiri, tidak harus sama dengan barang lain di booking ini.</p>

                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">Tambah ke Sewa</button>
            </form>
        </div>
    </div>

</div>

<script>
const pilihItemPerpanjang = document.getElementById('pilih-item-perpanjang');
const inputTanggalPerpanjang = document.getElementById('input-tanggal-perpanjang');

function perbaruiMinTanggalPerpanjang() {
    if (!pilihItemPerpanjang || !inputTanggalPerpanjang) return;
    const opsi = pilihItemPerpanjang.selectedOptions[0];
    if (!opsi) return;
    const tanggalKembaliSaatIni = opsi.dataset.kembali;
    const tanggalMin = new Date(tanggalKembaliSaatIni + 'T00:00:00');
    tanggalMin.setDate(tanggalMin.getDate() + 1);
    inputTanggalPerpanjang.min = tanggalMin.toISOString().slice(0, 10);
    inputTanggalPerpanjang.value = '';
}

if (pilihItemPerpanjang) {
    pilihItemPerpanjang.addEventListener('change', perbaruiMinTanggalPerpanjang);
}

const petaVariasiTambah = <?= json_encode($petaVariasiTambahan) ?>;

const pilihBarangTambah = document.getElementById('pilih-barang-tambah');
const blokUkuranTambah = document.getElementById('blok-ukuran-tambah');
const pilihUkuranTambah = document.getElementById('pilih-ukuran-tambah');

pilihBarangTambah.addEventListener('change', function () {
    const opsi = pilihBarangTambah.selectedOptions[0];
    const isPakaian = opsi && opsi.dataset.pakaian === '1';

    pilihUkuranTambah.innerHTML = '<option value="">Pilih ukuran</option>';

    if (isPakaian) {
        blokUkuranTambah.style.display = 'block';
        pilihUkuranTambah.required = true;
        const daftarVariasi = petaVariasiTambah[pilihBarangTambah.value] || [];
        daftarVariasi.forEach(function (v) {
            const habis = parseInt(v.stok) <= 0;
            const opt = new Option(v.ukuran + (habis ? ' (Stok Habis)' : ''), v.ukuran);
            opt.disabled = habis;
            pilihUkuranTambah.appendChild(opt);
        });
    } else {
        blokUkuranTambah.style.display = 'none';
        pilihUkuranTambah.required = false;
    }
});

const inputNominalPelunasan = document.getElementById('input-nominal-pelunasan');
if (inputNominalPelunasan) {
    inputNominalPelunasan.addEventListener('input', function () {
        const angka = inputNominalPelunasan.value.replace(/\D/g, '');
        inputNominalPelunasan.value = angka === '' ? '' : parseInt(angka, 10).toLocaleString('id-ID');
    });
    inputNominalPelunasan.closest('form').addEventListener('submit', function () {
        inputNominalPelunasan.value = inputNominalPelunasan.value.replace(/\D/g, '');
    });
}
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>