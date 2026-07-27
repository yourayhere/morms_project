<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$kataKunci = clean_input($_GET['cari'] ?? '');
$daftarSewa = $kataKunci !== '' ? BookingModel::cariUntukPengembalian($kataKunci) : BookingModel::getSiapDikembalikan();

$judulHalaman = 'Pengembalian';
$halamanAktif = 'pengembalian';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Pengembalian Barang</h1>
<p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 20px;">Cari invoice atau pilih dari daftar sewa yang sedang berjalan.</p>

<form method="GET" style="margin-bottom: 20px;">
    <div style="position: relative; max-width: 400px;">
        <svg class="icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 17px; height: 17px; color: var(--color-text-muted);"><use href="assets/icons/sprite.svg#icon-search"></use></svg>
        <input type="text" name="cari" value="<?= clean_input($kataKunci) ?>" placeholder="Cari kode booking atau nama penyewa..."
            style="width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 14px;">
    </div>
</form>

<?php if (empty($daftarSewa)): ?>
    <div class="card empty-state">
        <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
        <p class="empty-state-title">Tidak ada sewa untuk dikembalikan</p>
        <p class="empty-state-text">Sewa yang sedang berjalan akan muncul di sini saat siap dikembalikan.</p>
    </div>
<?php else: ?>

<div class="table-wrapper">
    <table class="table" style="min-width: 560px;">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Penyewa</th>
                <th>Barang</th>
                <th>Tanggal Kembali Terdekat</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daftarSewa as $b):
                $tanggalAcuan = $b['tanggal_kembali_terdekat'] ?? $b['tanggal_kembali'];
                $terlambat = strtotime($tanggalAcuan) < strtotime(date('Y-m-d'));
                $sudahKembali = isset($b['jumlah_total_item']) ? (int) $b['jumlah_total_item'] - (int) $b['jumlah_belum_kembali'] : null;
            ?>
                <tr class="<?= $terlambat ? 'row-danger' : '' ?>">
                    <td style="font-weight: 600;"><?= htmlspecialchars($b['kode_booking']) ?></td>
                    <td><?= htmlspecialchars($b['nama_member'] ?? $b['guest_nama']) ?></td>
                    <td>
                        <?php if ($sudahKembali !== null): ?>
                            <span class="badge <?= $sudahKembali > 0 ? 'badge-accent' : 'badge-neutral' ?>"><?= $sudahKembali ?>/<?= (int) $b['jumlah_total_item'] ?> kembali</span>
                        <?php endif; ?>
                    </td>
                    <td style="color: <?= $terlambat ? 'var(--color-danger)' : 'var(--color-text)' ?>;"><?= date('d/m/Y', strtotime($tanggalAcuan)) ?></td>
                    <td>
                        <?php if ($terlambat): ?>
                            <span class="badge badge-danger">Terlambat</span>
                        <?php else: ?>
                            <span class="badge badge-success">Tepat Waktu</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="proses-pengembalian.php?id=<?= $b['id'] ?>" style="color: var(--color-accent); font-weight: 600;">Proses</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>