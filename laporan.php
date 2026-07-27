<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Helpers/format.php';
require_once __DIR__ . '/app/Helpers/laporan.php';
require_once __DIR__ . '/app/Models/LaporanModel.php';
require_once __DIR__ . '/app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\LaporanModel;

Session::start();
Auth::requireRole(['owner']);

$dari   = clean_input($_GET['dari']   ?? date('Y-m-01'));
$sampai = clean_input($_GET['sampai'] ?? date('Y-m-d'));
$tab    = clean_input($_GET['tab']    ?? 'pendapatan');

$tabValid = ['pendapatan', 'reservasi', 'barang', 'keterlambatan'];
if (!in_array($tab, $tabValid, true)) {
    $tab = 'pendapatan';
}

$ringkasan         = LaporanModel::getRingkasan($dari, $sampai);
$dataPendapatan    = LaporanModel::getPendapatan($dari, $sampai);
$dataReservasi     = LaporanModel::getReservasi($dari, $sampai);
$dataBarang        = LaporanModel::getBarangTerlaris($dari, $sampai);
$dataKeterlambatan = LaporanModel::getKeterlambatan($dari, $sampai);

$judulHalaman = 'Laporan';
$halamanAktif = 'laporan';
require __DIR__ . '/app/Views/partials/header-admin.php';

?>

<div class="admin-page-header-row no-print">
    <div>
        <h1 class="admin-page-title">Laporan</h1>
        <p class="admin-page-subtitle">Analisis operasional dan keuangan Merimba Outdoor.</p>
    </div>
</div>

<div class="print-only" style="display: none;">
    <p style="font-size: 18px; font-weight: 700; color: #2E4452; margin-bottom: 2px;">MERIMBA OUTDOOR</p>
    <p style="font-size: 11px; color: #6B6560; margin-bottom: 10px;">Jl. Sorowajan Baru, Tegal Tanda, Banguntapan, Bantul, DIY 55198</p>
    <?php
    $labelTabCetak = [
        'pendapatan'    => 'Pendapatan',
        'reservasi'     => 'Reservasi',
        'barang'        => 'Barang Terlaris',
        'keterlambatan' => 'Keterlambatan',
    ];
    ?>
    <p style="font-size: 14px; font-weight: 700; margin-bottom: 2px;">Laporan <?= htmlspecialchars($labelTabCetak[$tab] ?? ucfirst($tab)) ?></p>
    <p style="font-size: 11px; color: #6B6560;">Periode: <?= format_tanggal_indo($dari) ?> &ndash; <?= format_tanggal_indo($sampai) ?></p>
    <p style="font-size: 11px; color: #6B6560; margin-bottom: 12px;">Dicetak pada: <?= date('d/m/Y H:i') ?> WIB</p>
    <hr style="border-color: #E5E0D8; margin-bottom: 14px;">
</div>

<div class="card no-print" style="padding: 16px 20px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <div>
            <label class="form-label-xs" style="display: block; margin-bottom: 5px;">Dari Tanggal</label>
            <input type="date" name="dari" value="<?= $dari ?>" class="form-input" style="width: 180px;">
        </div>
        <div>
            <label class="form-label-xs" style="display: block; margin-bottom: 5px;">Sampai Tanggal</label>
            <input type="date" name="sampai" value="<?= $sampai ?>" class="form-input" style="width: 180px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto;">
            <a href="aksi/export-laporan.php?jenis=<?= $tab === 'barang' ? 'barang-terlaris' : ($tab === 'keterlambatan' ? 'keterlambatan' : $tab) ?>&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="btn btn-secondary btn-sm">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                Export Excel (CSV)
            </a>
            <a href="aksi/export-laporan.php?jenis=lengkap&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="btn btn-secondary btn-sm" title="Data mentah per item booking, lengkap dengan pelanggan, pembayaran, dan pengembalian — siap dipakai di Tableau/Excel/Power BI atau dikirim ke pusat.">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-download"></use></svg>
                Export Data Lengkap (CSV)
            </a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                <svg class="icon icon-sm"><use href="assets/icons/sprite.svg#icon-print"></use></svg>
                Cetak PDF
            </button>
        </div>
    </form>
</div>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px;">
        <p style="font-size: 11px; font-weight: 600; color: var(--warm-400); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px;">Total Pendapatan</p>
        <p style="font-size: 22px; font-weight: 700; color: var(--forest-800);"><?= format_rupiah($ringkasan['total_pendapatan']) ?></p>
    </div>
    <div class="card" style="padding: 18px;">
        <p style="font-size: 11px; font-weight: 600; color: var(--warm-400); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px;">Total Booking</p>
        <p style="font-size: 22px; font-weight: 700; color: var(--forest-800);"><?= $ringkasan['total_booking'] ?></p>
    </div>
    <div class="card" style="padding: 18px;">
        <p style="font-size: 11px; font-weight: 600; color: var(--warm-400); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px;">Booking Selesai</p>
        <p style="font-size: 22px; font-weight: 700; color: var(--forest-800);"><?= $ringkasan['booking_selesai'] ?></p>
    </div>
    <div class="card" style="padding: 18px;">
        <p style="font-size: 11px; font-weight: 600; color: var(--warm-400); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px;">Total Denda</p>
        <p style="font-size: 22px; font-weight: 700; color: var(--danger-500);"><?= format_rupiah($ringkasan['total_denda']) ?></p>
    </div>
</div>

<div class="no-print" style="display: flex; gap: 0; border-bottom: 1px solid var(--cream-300); margin-bottom: 20px; overflow-x: auto; -webkit-overflow-scrolling: touch;">
    <?php
    $tabs = [
        'pendapatan'    => 'Pendapatan',
        'reservasi'     => 'Reservasi',
        'barang'        => 'Barang Terlaris',
        'keterlambatan' => 'Keterlambatan',
    ];
    foreach ($tabs as $key => $label):
    ?>
        <a href="?tab=<?= $key ?>&dari=<?= $dari ?>&sampai=<?= $sampai ?>"
           style="padding: 10px 20px; font-size: 13px; font-weight: <?= $tab === $key ? '600' : '500' ?>; color: <?= $tab === $key ? 'var(--terra-500)' : 'var(--warm-500)' ?>; border-bottom: 2px solid <?= $tab === $key ? 'var(--terra-500)' : 'transparent' ?>; margin-bottom: -1px; text-decoration: none; transition: color 0.15s; white-space: nowrap; flex-shrink: 0;">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'pendapatan'): ?>

    <?php if (empty($dataPendapatan)): ?>
        <div class="card" style="padding: 48px; text-align: center; color: var(--warm-400);">
            <p>Tidak ada data pendapatan pada periode ini.</p>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 22px; margin-bottom: 20px;">
            <h3 style="font-size: 14px; margin-bottom: 14px;">Grafik Pendapatan Harian</h3>
            <?php
            $maxNilai = max(array_column($dataPendapatan, 'total'));
            $idxGrafikLap = 0;
            ?>
<div style="overflow-x: auto; overflow-y: hidden; padding-top: 46px;">
<div class="mini-chart-wrap" style="height: 160px; min-width: fit-content;">
    <?php foreach ($dataPendapatan as $baris):
        $tinggi = $maxNilai > 0 ? ((float) $baris['total'] / $maxNilai) * 100 : 0;
        $idxGrafikLap++;
    ?>
        <div class="mini-chart-col" style="flex: 0 0 36px;">
            <div class="mini-chart-tooltip" id="tip-lap-<?= $idxGrafikLap ?>">
                <?= format_rupiah($baris['total']) ?>
                <small><?= date('d M Y', strtotime($baris['tanggal'])) ?></small>
            </div>
            <div class="mini-chart-bar" data-tooltip="tip-lap-<?= $idxGrafikLap ?>" style="max-width: 28px; height: <?= max(4, $tinggi) ?>%;"></div>
            <p class="mini-chart-label"><?= date('d/m', strtotime($baris['tanggal'])) ?></p>
        </div>
    <?php endforeach; ?>
</div>
</div>
        </div>

        <script>
        (function () {
            function tutupSemuaTooltipLaporan() {
                document.querySelectorAll('.mini-chart-tooltip.show').forEach(function (t) { t.classList.remove('show'); });
                document.querySelectorAll('.mini-chart-bar.active').forEach(function (b) { b.classList.remove('active'); });
            }
            document.querySelectorAll('.mini-chart-bar').forEach(function (bar) {
                bar.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var tip = document.getElementById(this.dataset.tooltip);
                    var sudahAktif = tip.classList.contains('show');
                    tutupSemuaTooltipLaporan();
                    if (!sudahAktif) {
                        tip.classList.add('show');
                        this.classList.add('active');
                    }
                });
            });
            document.addEventListener('click', tutupSemuaTooltipLaporan);
        })();
        </script>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jumlah Transaksi</th>
                        <th style="text-align: right;">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataPendapatan as $baris): ?>
                        <tr>
                            <td><?= format_tanggal_indo($baris['tanggal']) ?></td>
                            <td><?= $baris['jumlah_transaksi'] ?> transaksi</td>
                            <td style="text-align: right; font-weight: 600;"><?= format_rupiah((float) $baris['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: var(--cream-100);">
                        <td colspan="2" style="font-weight: 700;">Total</td>
                        <td style="text-align: right; font-weight: 700; color: var(--terra-600);"><?= format_rupiah($ringkasan['total_pendapatan']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'reservasi'): ?>

    <?php if (empty($dataReservasi)): ?>
        <div class="card" style="padding: 48px; text-align: center; color: var(--warm-400);">
            <p>Tidak ada data reservasi pada periode ini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Pemesan</th>
                        <th>Tanggal Ambil</th>
                        <th>Tanggal Kembali</th>
                        <th>Status</th>
                        <th style="text-align: right;">Total Sewa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataReservasi as $baris): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($baris['kode_booking']) ?></td>
                            <td><?= htmlspecialchars($baris['nama_member'] ?? $baris['guest_nama']) ?></td>
                            <td><?= date('d/m/Y', strtotime($baris['tanggal_ambil'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($baris['tanggal_kembali'])) ?></td>
                            <td><span class="badge badge-accent"><?= label_status_laporan($baris['status']) ?></span></td>
                            <td style="text-align: right; font-weight: 600;"><?= format_rupiah((float) $baris['total_sewa']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'barang'): ?>

    <?php if (empty($dataBarang)): ?>
        <div class="card" style="padding: 48px; text-align: center; color: var(--warm-400);">
            <p>Tidak ada data barang pada periode ini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Ukuran</th>
                        <th>Total Unit Disewa</th>
                        <th>Jumlah Booking</th>
                        <th style="text-align: right;">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataBarang as $urutan => $baris): ?>
                        <tr>
                            <td>
                                <span style="font-weight: 700; color: <?= $urutan < 3 ? 'var(--terra-500)' : 'var(--warm-400)' ?>;">
                                    #<?= $urutan + 1 ?>
                                </span>
                            </td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($baris['nama']) ?></td>
                            <td><span class="badge badge-neutral"><?= label_kategori($baris['kategori']) ?></span></td>
                            <td><?= $baris['ukuran'] ? htmlspecialchars($baris['ukuran']) : '<span style="color: var(--warm-300);">—</span>' ?></td>
                            <td><?= $baris['total_unit'] ?> unit</td>
                            <td><?= $baris['jumlah_booking'] ?> booking</td>
                            <td style="text-align: right; font-weight: 600;"><?= format_rupiah((float) $baris['total_pendapatan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'keterlambatan'): ?>

    <?php if (empty($dataKeterlambatan)): ?>
        <div class="card" style="padding: 48px; text-align: center; color: var(--warm-400);">
            <p>Tidak ada data keterlambatan pada periode ini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Pemesan</th>
                        <th>Seharusnya Kembali</th>
                        <th>Aktual Kembali</th>
                        <th>Kondisi</th>
                        <th style="text-align: right;">Denda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataKeterlambatan as $baris): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($baris['kode_booking']) ?></td>
                            <td><?= htmlspecialchars($baris['nama_member'] ?? $baris['guest_nama']) ?></td>
                            <td><?= date('d/m/Y', strtotime($baris['tanggal_kembali'])) ?></td>
                            <td><?= $baris['tanggal_kembali_aktual'] ? date('d/m/Y', strtotime($baris['tanggal_kembali_aktual'])) : '-' ?></td>
                            <td><span class="badge badge-danger"><?= label_kondisi_pengembalian($baris['kondisi']) ?></span></td>
                            <td style="text-align: right; font-weight: 600; color: var(--danger-500);"><?= format_rupiah((float) $baris['denda_terlambat']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<style>
@media print {
    @page { margin: 1.5cm; }

    .admin-sidebar, .admin-topbar, .no-print,
    form[method="GET"], .btn, .notif-wrapper { display: none !important; }

    .admin-content { margin-left: 0 !important; }
    .admin-main { padding: 0 !important; }
    body { background: white; }

    .print-only { display: block !important; }

    /* Browser secara default membuang warna latar (gradient bar, badge, dsb)
       saat print kecuali dipaksa lewat properti ini, terlepas dari opsi
       "Background graphics" di dialog print pengguna. */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }

    .table-wrapper { overflow: visible !important; }
    table { break-inside: auto; width: 100% !important; }
    tr, td, th { break-inside: avoid; }
    thead { display: table-header-group; }
}
</style>

<?php require __DIR__ . '/app/Views/partials/footer-admin.php'; ?>