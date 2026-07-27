<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/TransactionModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$id = (int) ($_GET['id'] ?? 0);
$booking = BookingModel::getDetailLengkap($id);

if (!$booking || $booking['status'] !== 'SEDANG_DISEWA') {
    header('Location: pengembalian.php');
    exit;
}

// Cuma barang yang belum dikembalikan yang tampil di sini - barang yang
// sudah diproses di event pengembalian sebelumnya otomatis hilang dari form,
// memungkinkan pengembalian bertahap (mis. carrier hari ini, jaket besok).
$itemBooking = array_values(array_filter(
    BookingModel::getItemBooking($id),
    fn($barang) => $barang['status'] === 'disewa'
));

if (empty($itemBooking)) {
    header('Location: pengembalian.php');
    exit;
}

$totalSewa = array_sum(array_column(BookingModel::getItemBooking($id), 'subtotal'));
$riwayatTransaksi = TransactionModel::getByBookingId($id);
$totalDibayar = 0;
foreach ($riwayatTransaksi as $trx) {
    if ($trx['status_verifikasi'] === 'terverifikasi') {
        $totalDibayar += (float) $trx['nominal'];
    }
}
$sisaSebelumDenda = max(0, $totalSewa - $totalDibayar);

// Batas waktu, keterlambatan, dan saran denda dihitung PER BARANG - masing-
// masing barang punya tanggal_kembali/jam_ambil sendiri sejak fitur periode
// sewa dinamis, jadi tidak lagi bisa dipukul rata satu angka untuk seluruh
// booking.
$sekarangTs = time();
$totalDendaSaran = 0;
$jumlahBarangTerlambat = 0;
foreach ($itemBooking as &$barang) {
    $batasWaktu = strtotime($barang['tanggal_kembali'] . ' ' . $barang['jam_ambil']);
    $jamTerlambat = max(0, ($sekarangTs - $batasWaktu) / 3600);
    $hariTerlambat = (int) ceil($jamTerlambat / 24);
    $dendaSaran = $hariTerlambat * $barang['harga_per_malam'] * $barang['jumlah'];

    $barang['jam_terlambat'] = $jamTerlambat;
    $barang['hari_terlambat'] = $hariTerlambat;
    $barang['denda_saran'] = $dendaSaran;

    $totalDendaSaran += $dendaSaran;
    if ($hariTerlambat > 0) {
        $jumlahBarangTerlambat++;
    }
}
unset($barang);

$namaPemesan = $booking['nama_member'] ?? $booking['guest_nama'];
$csrfToken = generate_csrf_token();

$pesanError = [
    'sistem' => 'Terjadi kendala sistem. Silakan coba lagi.',
    'pilih' => 'Pilih minimal satu barang untuk diproses pengembaliannya.',
][$_GET['error'] ?? ''] ?? null;

$judulHalaman = 'Proses Pengembalian';
$halamanAktif = 'pengembalian';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<a href="pengembalian.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Daftar Pengembalian
</a>

<?php if ($pesanError): ?>
    <div class="alert alert-danger" style="margin-bottom: 18px;"><?= htmlspecialchars($pesanError) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px;">

    <div>
        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <p style="font-size: 11px; color: var(--color-text-muted);">Kode Booking</p>
            <p style="font-size: 18px; font-weight: 700; color: var(--color-primary-dark); margin-bottom: 14px;"><?= htmlspecialchars($booking['kode_booking']) ?></p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px;">
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Penyewa</p>
                    <strong><?= htmlspecialchars($namaPemesan) ?></strong>
                </div>
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Barang Belum Dikembalikan</p>
                    <strong><?= count($itemBooking) ?> barang<?= $jumlahBarangTerlambat > 0 ? ', ' . $jumlahBarangTerlambat . ' terlambat' : '' ?></strong>
                </div>
            </div>

            <?php if ($jumlahBarangTerlambat > 0): ?>
                <div style="margin-top: 14px; padding: 10px 14px; background-color: #FBEAE5; border-radius: 6px; font-size: 12.5px; color: var(--color-danger);">
                    <?= $jumlahBarangTerlambat ?> barang sudah melewati batas waktu kembali masing-masing - lihat rincian di tiap barang.
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 22px;">
            <h3 style="font-size: 14px; margin-bottom: 4px;">Barang yang Dikembalikan</h3>
            <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 14px;">Centang barang yang benar-benar dikembalikan sekarang. Barang yang belum dicentang tetap tercatat sedang disewa dan bisa diproses lain waktu.</p>

            <form method="POST" action="aksi/simpan-pengembalian.php" id="form-pengembalian">
                <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

                <?php foreach ($itemBooking as $barang): ?>
                    <div class="kartu-item-pengembalian" data-id="<?= $barang['id'] ?>" style="border: 1px solid var(--color-border); border-radius: 8px; padding: 14px; margin-bottom: 10px;">
                        <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; margin-bottom: 10px;">
                            <input type="checkbox" class="cek-sertakan-item" name="sertakan[<?= $barang['id'] ?>]" value="1" checked style="margin-top: 3px;">
                            <span>
                                <strong style="font-size: 13px;"><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?> &times; <?= $barang['jumlah'] ?></strong>
                                <p style="font-size: 11.5px; color: <?= $barang['hari_terlambat'] > 0 ? 'var(--color-danger)' : 'var(--color-text-muted)' ?>; margin-top: 2px;">
                                    Seharusnya kembali <?= format_tanggal_indo($barang['tanggal_kembali']) ?> pukul <?= format_jam($barang['jam_ambil']) ?><?= $barang['hari_terlambat'] > 0 ? ' - terlambat ' . round($barang['jam_terlambat'], 1) . ' jam' : '' ?>
                                </p>
                            </span>
                        </label>

                        <div class="isian-item-pengembalian" style="padding-left: 26px;">
                            <div style="display: flex; gap: 16px; font-size: 12.5px; margin-bottom: 10px;">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="kondisi_item[<?= $barang['id'] ?>]" value="lengkap" checked> Lengkap
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="kondisi_item[<?= $barang['id'] ?>]" value="kurang"> Kurang / Hilang
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="kondisi_item[<?= $barang['id'] ?>]" value="rusak"> Rusak
                                </label>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px;">Biaya Keterlambatan</label>
                                    <input type="text" inputmode="numeric" class="input-denda-item" name="denda_terlambat_item[<?= $barang['id'] ?>]" value="<?= number_format($barang['denda_saran'], 0, ',', '.') ?>"
                                        style="width: 100%; padding: 7px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 600; margin-bottom: 4px;">Biaya Kerusakan/Kehilangan</label>
                                    <input type="text" inputmode="numeric" class="input-rusak-item" name="biaya_kerusakan_item[<?= $barang['id'] ?>]" value="0"
                                        style="width: 100%; padding: 7px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 18px; margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Catatan (opsional)</label>
                    <textarea name="keterangan" rows="2" placeholder="Contoh: Resleting tenda rusak, diganti biaya Rp50.000"
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; resize: vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                    Selesaikan Pengembalian Barang Terpilih
                </button>
            </form>
        </div>
    </div>

    <div>
        <div class="card" style="padding: 22px; position: sticky; top: 84px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Ringkasan Biaya</h3>

            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span style="color: var(--color-text-muted);">Sisa Pembayaran Sewa</span>
                <strong style="color: <?= $sisaSebelumDenda > 0 ? 'var(--color-danger)' : 'var(--color-text)' ?>;"><?= format_rupiah($sisaSebelumDenda) ?></strong>
            </div>

            <?php if ($sisaSebelumDenda > 0): ?>
                <p style="font-size: 11.5px; color: var(--color-danger); margin-bottom: 12px; display: flex; align-items: flex-start; gap: 5px;">
                    <svg class="icon" style="width: 13px; height: 13px; flex-shrink: 0; margin-top: 1px;"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
                    Penyewa masih punya sisa tagihan sewa yang belum lunas. Pastikan sudah ditagihkan sebelum menyelesaikan pengembalian ini.
                </p>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span style="color: var(--color-text-muted);">Biaya Keterlambatan</span>
                <strong id="tampil-denda-terlambat" style="color: <?= $totalDendaSaran > 0 ? 'var(--color-danger)' : 'var(--color-text)' ?>;"><?= format_rupiah($totalDendaSaran) ?></strong>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span style="color: var(--color-text-muted);">Biaya Kerusakan/Kehilangan</span>
                <strong id="tampil-biaya-rusak">Rp0</strong>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 15px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-border);">
                <span>Total yang Harus Dibayar</span>
                <strong id="tampil-total-akhir" style="color: var(--color-accent);"><?= format_rupiah($sisaSebelumDenda + $totalDendaSaran) ?></strong>
            </div>

            <div style="margin-top: 16px; padding: 12px 14px; background-color: var(--color-bg); border-radius: 6px; display: flex; gap: 8px; align-items: flex-start;">
                <svg class="icon" style="width: 16px; height: 16px; color: var(--color-primary-mid); flex-shrink: 0; margin-top: 1px;"><use href="assets/icons/sprite.svg#icon-id-card"></use></svg>
                <p style="font-size: 11.5px; color: var(--color-text-muted); line-height: 1.5;">Identitas jaminan dikembalikan ke penyewa setelah SELURUH barang di booking ini sudah dikembalikan dan seluruh biaya dilunasi.</p>
            </div>
        </div>
    </div>

</div>

<script>
const sisaSewa = <?= $sisaSebelumDenda ?>;

function formatRupiah(angka) {
    return 'Rp' + Math.round(angka).toLocaleString('id-ID');
}

function angkaBersih(nilai) {
    return parseInt(nilai.replace(/\D/g, ''), 10) || 0;
}

function pasangInputRupiah(input) {
    input.addEventListener('input', function () {
        const angka = input.value.replace(/\D/g, '');
        input.value = angka === '' ? '' : parseInt(angka, 10).toLocaleString('id-ID');
        perbaruiTotal();
    });
    input.closest('form').addEventListener('submit', function () {
        input.value = input.value.replace(/\D/g, '');
    });
}

function perbaruiTotal() {
    let totalDenda = 0;
    let totalRusak = 0;

    document.querySelectorAll('.kartu-item-pengembalian').forEach(function (kartu) {
        const dicentang = kartu.querySelector('.cek-sertakan-item').checked;
        kartu.querySelector('.isian-item-pengembalian').style.display = dicentang ? 'block' : 'none';
        if (dicentang) {
            totalDenda += angkaBersih(kartu.querySelector('.input-denda-item').value);
            totalRusak += angkaBersih(kartu.querySelector('.input-rusak-item').value);
        }
    });

    document.getElementById('tampil-denda-terlambat').textContent = formatRupiah(totalDenda);
    document.getElementById('tampil-biaya-rusak').textContent = formatRupiah(totalRusak);
    document.getElementById('tampil-total-akhir').textContent = formatRupiah(sisaSewa + totalDenda + totalRusak);
}

document.querySelectorAll('.input-denda-item, .input-rusak-item').forEach(pasangInputRupiah);
document.querySelectorAll('.cek-sertakan-item').forEach(function (cek) {
    cek.addEventListener('change', perbaruiTotal);
});

perbaruiTotal();
</script>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>