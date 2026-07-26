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
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;
use App\Models\TransactionModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$id = (int) ($_GET['id'] ?? 0);
$booking = BookingModel::getDetailLengkap($id);

if (!$booking) {
    header('Location: reservasi-online.php');
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
$hpPemesan = $booking['hp_member'] ?? $booking['guest_hp'];

$adaPembayaranPending = false;
foreach ($riwayatTransaksi as $trx) {
    if ($trx['status_verifikasi'] === 'menunggu') {
        $adaPembayaranPending = true;
        break;
    }
}

$urutanAksiBerikut = [
    'MENUNGGU_KEDATANGAN' => ['RESERVASI_DIKONFIRMASI', 'Konfirmasi Reservasi'],
    'RESERVASI_DIKONFIRMASI' => ['BARANG_DISIAPKAN', 'Tandai Barang Disiapkan'],
    'BARANG_DISIAPKAN' => ['SIAP_DIAMBIL', 'Tandai Siap Diambil'],
    'SIAP_DIAMBIL' => ['SEDANG_DISEWA', 'Barang Sudah Diambil Customer'],
];
$aksiBerikut = $urutanAksiBerikut[$booking['status']] ?? null;
$aksiDiblokirPembayaran = $aksiBerikut && $aksiBerikut[0] === 'RESERVASI_DIKONFIRMASI' && $adaPembayaranPending;

$csrfToken = generate_csrf_token();

$judulHalaman = 'Detail Reservasi';
$halamanAktif = 'reservasi';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<a href="reservasi-online.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
    <svg class="icon" style="width: 16px; height: 16px; transform: rotate(180deg);"><use href="assets/icons/sprite.svg#icon-arrow-right"></use></svg>
    Kembali ke Daftar Reservasi
</a>

<?php if (($_GET['error'] ?? '') === 'pembayaran_pending'): ?>
    <div class="card" style="padding: 14px 18px; margin-bottom: 18px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px;">
        Reservasi belum bisa dikonfirmasi karena masih ada pembayaran yang menunggu verifikasi.
    </div>
<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'alasan_wajib'): ?>
    <div class="card" style="padding: 14px 18px; margin-bottom: 18px; background-color: #FBEAE5; border-color: var(--color-danger); color: var(--color-danger); font-size: 13px;">
        Alasan pembatalan wajib diisi supaya penyewa tahu kenapa reservasinya dibatalkan.
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px;">

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
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Nama Pemesan</p>
                    <strong><?= htmlspecialchars($namaPemesan) ?></strong>
                </div>
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Nomor HP</p>
                    <strong><?= htmlspecialchars($hpPemesan) ?></strong>
                </div>
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Tanggal Ambil</p>
                    <strong><?= format_tanggal_indo($booking['tanggal_ambil']) ?>, pukul <?= format_jam($booking['jam_ambil']) ?></strong>
                </div>
                <div>
                    <p style="color: var(--color-text-muted); margin-bottom: 2px;">Tanggal Kembali</p>
                    <strong><?= format_tanggal_indo($booking['tanggal_kembali']) ?>, pukul <?= format_jam($booking['jam_kembali']) ?></strong>
                </div>
            </div>

            <?php if (!empty($booking['catatan'])): ?>
                <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--color-border);">
                    <p style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 4px;">Catatan Customer</p>
                    <p style="font-size: 13px;"><?= nl2br(htmlspecialchars($booking['catatan'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Rincian Barang</h3>
            <?php foreach ($itemBooking as $barang): ?>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <span><?= htmlspecialchars($barang['nama']) ?><?php if (!empty($barang['ukuran_dipilih'])): ?> (Ukuran <?= htmlspecialchars($barang['ukuran_dipilih']) ?>)<?php endif; ?> &times; <?= $barang['jumlah'] ?></span>
                        <p style="font-size: 11.5px; color: var(--color-text-muted); margin-top: 2px;"><?= htmlspecialchars(format_periode_sewa($barang['tanggal_ambil'], $barang['tanggal_kembali'], $barang['jam_ambil'])) ?></p>
                    </div>
                    <strong><?= format_rupiah($barang['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="padding: 22px;">
            <h3 style="font-size: 14px; margin-bottom: 12px;">Jaminan Identitas</h3>
            <?php if ($booking['identitas_file'] && file_exists(storage_path('identitas/' . $booking['identitas_file']))): ?>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <svg class="icon" style="width: 18px; height: 18px; color: var(--color-primary-mid);"><use href="assets/icons/sprite.svg#icon-id-card"></use></svg>
                    <span style="font-size: 13px;">Berkas identitas telah diunggah customer.</span>
                </div>
                <a href="lihat-identitas.php?booking_id=<?= $booking['id'] ?>" class="btn btn-secondary" style="display: inline-flex;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-eye"></use></svg>
                    Lihat Berkas
                </a>
            <?php else: ?>
                <p style="font-size: 13px; color: var(--color-text-muted);">Belum ada berkas identitas.</p>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card" style="padding: 22px; margin-bottom: 18px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Status Pembayaran</h3>

            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                <span style="color: var(--color-text-muted);">Total Sewa</span>
                <strong><?= format_rupiah($totalSewa) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                <span style="color: var(--color-text-muted);">Sudah Dibayar</span>
                <strong style="color: var(--color-success);"><?= format_rupiah($totalDibayar) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 14px;">
                <span style="color: var(--color-text-muted);">Sisa</span>
                <strong style="color: <?= $sisaPembayaran > 0 ? 'var(--color-danger)' : 'var(--color-success)' ?>;"><?= format_rupiah($sisaPembayaran) ?></strong>
            </div>

            <?php foreach ($riwayatTransaksi as $trx): ?>
                <div style="border-top: 1px solid var(--color-border); padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                        <span><?= htmlspecialchars($trx['invoice_no']) ?></span>
                        <strong><?= format_rupiah((float) $trx['nominal']) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11px; color: var(--color-text-muted); text-transform: uppercase;"><?= htmlspecialchars($trx['metode']) ?> &middot; <?= htmlspecialchars($trx['jenis']) ?></span>

                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if ($trx['metode'] === 'qris' && $trx['bukti_bayar'] && file_exists(storage_path('identitas/' . $trx['bukti_bayar']))): ?>
                                <a href="lihat-bukti-bayar.php?transaction_id=<?= $trx['id'] ?>" style="font-size: 11px; color: var(--color-accent); font-weight: 600;">Lihat Bukti</a>
                            <?php endif; ?>

                            <?php if ($trx['status_verifikasi'] === 'menunggu'): ?>
                                <div style="display: flex; gap: 6px;">
                                    <form method="POST" action="aksi/verifikasi-pembayaran.php" style="display: inline;" onsubmit="return confirm('Yakin pembayaran ini sudah diverifikasi dan diterima?');">
                                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                                        <input type="hidden" name="transaction_id" value="<?= $trx['id'] ?>">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="aksi" value="terima">
                                        <button type="submit" style="background: none; border: none; color: var(--success-500); cursor: pointer; font-size: 11px; font-weight: 600;">Terima</button>
                                    </form>
                                    <form method="POST" action="aksi/verifikasi-pembayaran.php" style="display: inline;" onsubmit="return confirm('Yakin ingin menolak pembayaran ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                                        <input type="hidden" name="transaction_id" value="<?= $trx['id'] ?>">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="aksi" value="tolak">
                                        <button type="submit" style="background: none; border: none; color: var(--danger-500); cursor: pointer; font-size: 11px; font-weight: 600;">Tolak</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 11px; color: <?= $trx['status_verifikasi'] === 'terverifikasi' ? 'var(--success-500)' : 'var(--warm-400)' ?>;"><?= ucfirst($trx['status_verifikasi']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="padding: 22px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Aksi</h3>

            <?php if ($aksiDiblokirPembayaran): ?>
                <button type="button" class="btn btn-secondary" disabled style="width: 100%; justify-content: center; margin-bottom: 6px; opacity: 0.5; cursor: not-allowed;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                    Konfirmasi Reservasi
                </button>
                <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 10px;">Masih ada pembayaran yang menunggu verifikasi. Klik Terima/Tolak pada riwayat transaksi di atas dulu.</p>
            <?php elseif ($aksiBerikut): ?>
                <form method="POST" action="aksi/ubah-status-reservasi.php" style="margin-bottom: 10px;">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                    <input type="hidden" name="status_baru" value="<?= $aksiBerikut[0] ?>">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                        <?= $aksiBerikut[1] ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (in_array($booking['status'], ['DRAFT', 'MENUNGGU_PEMBAYARAN'], true)): ?>
                <div style="padding: 12px 14px; background-color: var(--color-bg); border-radius: 8px; margin-bottom: 10px;">
                    <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">Konfirmasi Pembayaran Manual</p>
                    <p style="font-size: 11.5px; color: var(--color-text-muted); margin-bottom: 10px;">Reservasi ini belum menyelesaikan alur pembayaran online (mis. penyewa belum lanjut bayar, atau bayar langsung ke rekening/tunai di luar sistem). Gunakan ini untuk mencatat pembayaran secara manual.</p>
                    <form method="POST" action="aksi/konfirmasi-pembayaran-manual.php" onsubmit="return confirm('Yakin sudah menerima pembayaran ini secara manual?');">
                        <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

                        <label style="display: block; font-size: 11.5px; font-weight: 600; margin-bottom: 5px;">Skema Bayar</label>
                        <select name="skema" style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 12.5px; margin-bottom: 8px;">
                            <option value="dp">DP 50%</option>
                            <option value="lunas">Lunas</option>
                        </select>

                        <label style="display: block; font-size: 11.5px; font-weight: 600; margin-bottom: 5px;">Metode</label>
                        <select name="metode" style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 12.5px; margin-bottom: 10px;">
                            <option value="cash">Tunai</option>
                            <option value="qris">QRIS / Transfer</option>
                        </select>

                        <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                            <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-check"></use></svg>
                            Konfirmasi Pembayaran
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($booking['status'] === 'SEDANG_DISEWA'): ?>
                <a href="struk-serah-terima.php?booking_id=<?= $booking['id'] ?>" target="_blank" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-bottom: 10px;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-note"></use></svg>
                    Cetak Struk Serah Terima
                </a>
                <a href="proses-pengembalian.php?id=<?= $booking['id'] ?>" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: 10px;">
                    <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-return"></use></svg>
                    Proses Pengembalian
                </a>
            <?php endif; ?>

            <?php if (!in_array($booking['status'], ['EXPIRED', 'DIBATALKAN', 'SELESAI', 'SEDANG_DISEWA'], true)): ?>
                <form method="POST" action="aksi/ubah-status-reservasi.php" style="margin-bottom: 10px;" onsubmit="return confirm('Yakin ingin membatalkan reservasi ini? Alasan akan ditampilkan ke penyewa.');">
                    <input type="hidden" name="csrf_token" value="<?= clean_input($csrfToken) ?>">
                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                    <input type="hidden" name="status_baru" value="DIBATALKAN">
                    <label style="display: block; font-size: 11.5px; font-weight: 600; margin-bottom: 5px;">Alasan Pembatalan (wajib diisi, akan dilihat penyewa)</label>
                    <textarea name="alasan_pembatalan" required rows="2" placeholder="Contoh: Stok barang ternyata rusak dan tidak bisa disewakan pada tanggal tersebut."
                        style="width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 12.5px; resize: vertical; margin-bottom: 8px;"></textarea>
                    <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center; color: var(--color-danger); border-color: var(--color-danger);">
                        <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
                        Batalkan Reservasi
                    </button>
                </form>
            <?php endif; ?>

            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', ltrim($hpPemesan, '0') === $hpPemesan ? $hpPemesan : '62' . ltrim($hpPemesan, '0')) ?>" target="_blank" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                <svg class="icon" style="width: 16px; height: 16px;"><use href="assets/icons/sprite.svg#icon-phone"></use></svg>
                Hubungi Customer
            </a>
        </div>
    </div>

</div>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>