<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/timeline.php';
require_once __DIR__ . '/app/Models/DashboardModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';
require_once __DIR__ . '/app/Models/BookingModel.php';
require_once __DIR__ . '/app/Models/BackupBuktiPembayaranModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\DashboardModel;
use App\Models\BackupBuktiPembayaranModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

// Jalankan opportunistically setiap dashboard dibuka, sebagai jaring pengaman
// selain scheduled task (scripts/expire-bookings.php) agar booking yang tidak
// pernah dibayar tidak menyandera stok selamanya.
\App\Models\BookingModel::expireBookingBelumDibayar(60);

// Pengingat jadwal backup bukti pembayaran - ditampilkan untuk owner/admin,
// tapi tautan "Kelola Backup"-nya cuma muncul untuk owner (fitur ini ada di
// halaman Pengaturan yang owner-only). Tidak ditampilkan sebelum backup
// pertama pernah dibuat, karena jadwalnya baru terbentuk setelah itu (lihat
// BackupBuktiPembayaranModel::getJadwalBerikutnya()).
$jadwalBackupBerikutnya = null;
$reminderBackup = null;
if (in_array(Auth::role(), ['owner', 'admin'], true)) {
    $jadwalBackupBerikutnya = BackupBuktiPembayaranModel::getJadwalBerikutnya();
    if ($jadwalBackupBerikutnya) {
        $sekarangTs = time();
        $jadwalTs = $jadwalBackupBerikutnya->getTimestamp();
        $selisihDetik = $jadwalTs - $sekarangTs;
        $akhirTenggangTs = $jadwalTs + (24 * 3600);

        if ($selisihDetik > 0 && $selisihDetik <= 7 * 86400) {
            $reminderBackup = [
                'level' => 'info',
                'pesan' => 'Pengingat: jadwal backup bukti pembayaran berikutnya adalah ' . format_tanggal_indo($jadwalBackupBerikutnya->format('Y-m-d')) . ' pukul ' . $jadwalBackupBerikutnya->format('H:i') . ' WIB (dalam ' . format_durasi_singkat($selisihDetik) . ').',
            ];
        } elseif ($selisihDetik <= 0 && $sekarangTs <= $akhirTenggangTs) {
            $reminderBackup = [
                'level' => 'tenggang',
                'akhir_tenggang_ts' => $akhirTenggangTs,
            ];
        } elseif ($sekarangTs > $akhirTenggangTs) {
            $reminderBackup = [
                'level' => 'lewat',
                'pesan' => 'Penyimpanan bukti pembayaran telah melewati batas yang disarankan. Segera lakukan Backup dan Arsip untuk mengosongkan ruang penyimpanan.',
            ];
        }
    }
}

$pendapatanHariIni = DashboardModel::getPendapatanHariIni();
$reservasiBaru = DashboardModel::getJumlahReservasiBaru();
$penyewaanAktif = DashboardModel::getJumlahPenyewaanAktif();
$pengembalianHariIni = DashboardModel::getJumlahPengembalianHariIni();
$barangHampirHabis = DashboardModel::getBarangHampirHabis();
$barangTerlambat = DashboardModel::getBarangTerlambat();
$grafikPendapatan = DashboardModel::getGrafikPendapatan7Hari();
$reservasiTerbaru = DashboardModel::getReservasiTerbaru();

$judulHalaman = 'Dashboard';
$halamanAktif = 'dashboard';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<h1 style="font-size: 22px; color: var(--color-primary-dark); margin-bottom: 4px;">Dashboard</h1>
<p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 24px;">Ringkasan operasional Merimba Outdoor hari ini.</p>

<?php if ($reminderBackup && $reminderBackup['level'] === 'info'): ?>
<div class="card" style="padding: 16px 18px; margin-bottom: 24px; display: flex; gap: 10px; align-items: flex-start; background-color: #FBF1EC; border-color: var(--color-accent);">
    <svg class="icon icon-accent" style="width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px;"><use href="assets/icons/sprite.svg#icon-database"></use></svg>
    <p style="font-size: 12.5px; color: var(--color-text); line-height: 1.6;">
        <?= htmlspecialchars($reminderBackup['pesan']) ?>
        <?php if (Auth::role() === 'owner'): ?>
            <a href="pengaturan.php#modal-backup-bukti" style="font-weight: 600; color: var(--color-accent);">Kelola Backup</a>
        <?php endif; ?>
    </p>
</div>
<?php elseif ($reminderBackup && $reminderBackup['level'] === 'tenggang'): ?>
<div class="card" style="padding: 16px 18px; margin-bottom: 24px; display: flex; gap: 10px; align-items: flex-start; border-color: var(--color-danger);">
    <svg class="icon" style="width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px; color: var(--color-danger);"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
    <p style="font-size: 12.5px; color: var(--color-text); line-height: 1.6;">
        Backup bukti pembayaran telah melewati jadwal. Lakukan backup dan arsip dalam waktu <strong id="countdown-tenggang-backup" style="color: var(--color-danger);">24:00:00</strong> agar penyimpanan tetap optimal.
        <?php if (Auth::role() === 'owner'): ?>
            <a href="pengaturan.php#modal-backup-bukti" style="font-weight: 600; color: var(--color-accent);">Kelola Backup</a>
        <?php endif; ?>
    </p>
</div>
<script>
(function () {
    var akhirTenggangTs = <?= (int) $reminderBackup['akhir_tenggang_ts'] ?> * 1000;
    var el = document.getElementById('countdown-tenggang-backup');
    if (!el) return;

    function perbarui() {
        var sisaMs = akhirTenggangTs - Date.now();
        if (sisaMs <= 0) {
            el.textContent = '00:00:00';
            clearInterval(timer);
            return;
        }
        var totalDetik = Math.floor(sisaMs / 1000);
        var jam = Math.floor(totalDetik / 3600);
        var menit = Math.floor((totalDetik % 3600) / 60);
        var detik = totalDetik % 60;
        el.textContent = String(jam).padStart(2, '0') + ':' + String(menit).padStart(2, '0') + ':' + String(detik).padStart(2, '0');
    }

    perbarui();
    var timer = setInterval(perbarui, 1000);
})();
</script>
<?php elseif ($reminderBackup && $reminderBackup['level'] === 'lewat'): ?>
<div class="card" style="padding: 16px 18px; margin-bottom: 24px; display: flex; gap: 10px; align-items: flex-start; border-color: var(--color-danger);">
    <svg class="icon" style="width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px; color: var(--color-danger);"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
    <p style="font-size: 12.5px; color: var(--color-text); line-height: 1.6;">
        <?= htmlspecialchars($reminderBackup['pesan']) ?>
        <?php if (Auth::role() === 'owner'): ?>
            <a href="pengaturan.php#modal-backup-bukti" style="font-weight: 600; color: var(--color-accent);">Kelola Backup</a>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">

    <div class="stat-card">
        <div class="stat-icon stat-icon-success"><svg class="icon"><use href="assets/icons/sprite.svg#icon-revenue"></use></svg></div>
        <p class="stat-value"><?= format_rupiah($pendapatanHariIni) ?></p>
        <p class="stat-label">Pendapatan Hari Ini</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-accent"><svg class="icon"><use href="assets/icons/sprite.svg#icon-box"></use></svg></div>
        <p class="stat-value"><?= $reservasiBaru ?></p>
        <p class="stat-label">Reservasi Baru</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-forest"><svg class="icon"><use href="assets/icons/sprite.svg#icon-package"></use></svg></div>
        <p class="stat-value"><?= $penyewaanAktif ?></p>
        <p class="stat-label">Penyewaan Aktif</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-forest"><svg class="icon"><use href="assets/icons/sprite.svg#icon-clock"></use></svg></div>
        <p class="stat-value"><?= $pengembalianHariIni ?></p>
        <p class="stat-label">Pengembalian Hari Ini</p>
    </div>

</div>

<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; margin-bottom: 24px;">

    <div class="card" style="padding: 22px;">
        <h3 style="font-size: 14px; margin-bottom: 14px;">Grafik Pendapatan 7 Hari Terakhir</h3>
    <div class="mini-chart-wrap" style="height: 150px;">
        <?php
        $nilaiMaksimal = max(array_merge(array_values($grafikPendapatan), [1]));
        $idxGrafikDash = 0;
        foreach ($grafikPendapatan as $tanggal => $nilai):
            $tinggiPersen = $nilaiMaksimal > 0 ? ($nilai / $nilaiMaksimal) * 100 : 0;
            $idxGrafikDash++;
        ?>
            <div class="mini-chart-col">
                <div class="mini-chart-tooltip" id="tip-dash-<?= $idxGrafikDash ?>">
                    <?= format_rupiah($nilai) ?>
                    <small><?= date('d M Y', strtotime($tanggal)) ?></small>
                </div>
                <div class="mini-chart-bar" data-tooltip="tip-dash-<?= $idxGrafikDash ?>" style="height: <?= max(4, $tinggiPersen) ?>%;"></div>
                <p class="mini-chart-label"><?= date('d/m', strtotime($tanggal)) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    </div>

    <script>
    (function () {
        function tutupSemuaTooltip() {
            document.querySelectorAll('.mini-chart-tooltip.show').forEach(function (t) { t.classList.remove('show'); });
            document.querySelectorAll('.mini-chart-bar.active').forEach(function (b) { b.classList.remove('active'); });
        }
        document.querySelectorAll('.mini-chart-bar').forEach(function (bar) {
            bar.addEventListener('click', function (e) {
                e.stopPropagation();
                var tip = document.getElementById(this.dataset.tooltip);
                var sudahAktif = tip.classList.contains('show');
                tutupSemuaTooltip();
                if (!sudahAktif) {
                    tip.classList.add('show');
                    this.classList.add('active');
                }
            });
        });
        document.addEventListener('click', tutupSemuaTooltip);
    })();
    </script>

    <div class="card" style="padding: 22px;">
        <h3 style="font-size: 14px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
            <svg class="icon" style="width: 17px; height: 17px; color: var(--color-danger);"><use href="assets/icons/sprite.svg#icon-alert-stock"></use></svg>
            Barang Hampir Habis
        </h3>
        <?php if (empty($barangHampirHabis)): ?>
            <p style="font-size: 12px; color: var(--color-text-muted);">Semua stok dalam jumlah aman.</p>
        <?php else: ?>
            <?php foreach ($barangHampirHabis as $barang): ?>
                <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 7px 0; border-bottom: 1px solid var(--color-border);">
                    <span><?= htmlspecialchars($barang['nama']) ?></span>
                    <strong style="color: <?= $barang['sisa_stok'] <= 0 ? 'var(--color-danger)' : 'var(--color-accent)' ?>;"><?= $barang['sisa_stok'] ?> tersisa</strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php if (!empty($barangTerlambat)): ?>
<div class="card" style="padding: 22px; margin-bottom: 24px; border-color: var(--color-danger);">
    <h3 style="font-size: 14px; margin-bottom: 14px; color: var(--color-danger); display: flex; align-items: center; gap: 8px;">
        <svg class="icon" style="width: 17px; height: 17px;"><use href="assets/icons/sprite.svg#icon-alert"></use></svg>
        Barang Terlambat Dikembalikan
    </h3>
    <?php foreach ($barangTerlambat as $b): ?>
        <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--color-border);">
            <span><strong><?= htmlspecialchars($b['nama_barang']) ?></strong> - <?= htmlspecialchars($b['kode_booking']) ?> (<?= htmlspecialchars($b['nama_member'] ?? $b['guest_nama']) ?>)</span>
            <strong style="color: var(--color-danger);"><?= $b['hari_terlambat'] ?> hari terlambat</strong>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="padding: 22px;">
    <h3 style="font-size: 14px; margin-bottom: 14px;">Reservasi Terbaru</h3>
    <?php if (empty($reservasiTerbaru)): ?>
        <div class="empty-state" style="padding: 32px 24px;">
            <svg class="empty-state-icon"><use href="assets/icons/sprite.svg#icon-x"></use></svg>
            <p class="empty-state-title">Belum ada reservasi</p>
            <p class="empty-state-text">Reservasi terbaru akan muncul di sini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper" style="box-shadow: none; border: none;">
        <table class="table" style="min-width: 480px;">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservasiTerbaru as $r): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= htmlspecialchars($r['kode_booking']) ?></td>
                        <td><?= htmlspecialchars($r['nama_member'] ?? $r['guest_nama']) ?></td>
                        <td><span class="badge badge-accent"><?= label_status_booking($r['status']) ?></span></td>
                        <td style="color: var(--color-text-muted);"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>