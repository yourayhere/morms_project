<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/format.php';
require_once __DIR__ . '/../app/Helpers/laporan.php';
require_once __DIR__ . '/../app/Models/LaporanModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\LaporanModel;

Session::start();
Auth::requireRole(['owner']);

$jenis  = $_GET['jenis'] ?? '';
$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

switch ($jenis) {

    case 'pendapatan':
        $data = LaporanModel::getPendapatan($dari, $sampai);
        $header = ['Tanggal', 'Hari', 'Jumlah Transaksi', 'Total Pendapatan (Rp)'];
        $baris = [];
        foreach ($data as $row) {
            $baris[] = [
                date('d/m/Y', strtotime($row['tanggal'])),
                date('l', strtotime($row['tanggal'])),
                (int) $row['jumlah_transaksi'],
                (float) $row['total'],
            ];
        }
        export_csv($header, $baris, 'laporan-pendapatan-' . $dari . '-' . $sampai . '.csv');
        break;

    case 'reservasi':
        $data = LaporanModel::getReservasi($dari, $sampai);
        $header = ['Kode Booking', 'Nama Pemesan', 'Tipe', 'Tanggal Ambil', 'Tanggal Kembali', 'Durasi (Malam)', 'Status', 'Total Sewa (Rp)'];
        $baris = [];
        foreach ($data as $row) {
            $durasi = (strtotime($row['tanggal_kembali']) - strtotime($row['tanggal_ambil'])) / 86400;
            $baris[] = [
                $row['kode_booking'],
                $row['nama_member'] ?? $row['guest_nama'],
                $row['nama_member'] ? 'Member' : 'Tamu',
                date('d/m/Y', strtotime($row['tanggal_ambil'])),
                date('d/m/Y', strtotime($row['tanggal_kembali'])),
                (int) $durasi,
                label_status_laporan($row['status']),
                (float) $row['total_sewa'],
            ];
        }
        export_csv($header, $baris, 'laporan-reservasi-' . $dari . '-' . $sampai . '.csv');
        break;

    case 'barang-terlaris':
        $data = LaporanModel::getBarangTerlaris($dari, $sampai, 50);
        $header = ['Peringkat', 'Nama Barang', 'Kategori', 'Ukuran', 'Total Unit Disewa', 'Jumlah Booking', 'Total Pendapatan (Rp)'];
        $baris = [];
        foreach ($data as $urutan => $row) {
            $baris[] = [
                $urutan + 1,
                $row['nama'],
                label_kategori($row['kategori']),
                $row['ukuran'] ?? '-',
                (int) $row['total_unit'],
                (int) $row['jumlah_booking'],
                (float) $row['total_pendapatan'],
            ];
        }
        export_csv($header, $baris, 'laporan-barang-terlaris-' . $dari . '-' . $sampai . '.csv');
        break;

    case 'keterlambatan':
        $data = LaporanModel::getKeterlambatan($dari, $sampai);
        $header = ['Kode Booking', 'Nama Pemesan', 'Seharusnya Kembali', 'Aktual Kembali', 'Kondisi Barang', 'Denda (Rp)'];
        $baris = [];
        foreach ($data as $row) {
            $baris[] = [
                $row['kode_booking'],
                $row['nama_member'] ?? $row['guest_nama'],
                date('d/m/Y', strtotime($row['tanggal_kembali'])),
                $row['tanggal_kembali_aktual'] ? date('d/m/Y', strtotime($row['tanggal_kembali_aktual'])) : 'Belum Dikembalikan',
                label_kondisi_pengembalian($row['kondisi']),
                (float) $row['denda_terlambat'],
            ];
        }
        export_csv($header, $baris, 'laporan-keterlambatan-' . $dari . '-' . $sampai . '.csv');
        break;

    case 'lengkap':
        $data = LaporanModel::getDataLengkap($dari, $sampai);
        $header = [
            'Kode Booking', 'Booking ID', 'Tanggal Booking Dibuat', 'Jam Booking Dibuat',
            'Tipe Pelanggan', 'Nama Pelanggan', 'No HP Pelanggan', 'Email Pelanggan',
            'Status Booking', 'Tanggal Ambil', 'Tanggal Kembali (Rencana)', 'Durasi Sewa (Malam)',
            'Nama Barang', 'Kategori Barang', 'Ukuran', 'Jumlah Unit', 'Subtotal Item (Rp)',
            'Total Sewa Booking (Rp)', 'Total Terverifikasi (Rp)', 'Total Menunggu Verifikasi (Rp)', 'Total Ditolak (Rp)',
            'Metode Pembayaran', 'Kondisi Pengembalian', 'Tanggal Kembali Aktual',
            'Denda Keterlambatan (Rp)', 'Biaya Kerusakan (Rp)', 'Catatan Pengembalian',
        ];
        $baris = [];
        foreach ($data as $row) {
            $baris[] = [
                $row['kode_booking'],
                (int) $row['booking_id'],
                date('d/m/Y', strtotime($row['booking_dibuat'])),
                date('H:i', strtotime($row['booking_dibuat'])),
                $row['tipe_pelanggan'],
                $row['nama_pelanggan'],
                $row['no_hp_pelanggan'],
                $row['email_pelanggan'] ?? '',
                label_status_laporan($row['status_booking']),
                date('d/m/Y', strtotime($row['tanggal_ambil'])),
                date('d/m/Y', strtotime($row['tanggal_kembali'])),
                (int) $row['durasi_malam'],
                $row['nama_barang'],
                label_kategori($row['kategori']),
                $row['ukuran'] ?? '-',
                (int) $row['jumlah'],
                (float) $row['subtotal_item'],
                (float) $row['total_sewa_booking'],
                (float) $row['total_terverifikasi'],
                (float) $row['total_menunggu_verifikasi'],
                (float) $row['total_ditolak'],
                $row['metode_pembayaran'] ?? '',
                $row['kondisi_pengembalian'] ? label_kondisi_pengembalian($row['kondisi_pengembalian']) : '',
                $row['tanggal_kembali_aktual'] ? date('d/m/Y H:i', strtotime($row['tanggal_kembali_aktual'])) : '',
                (float) $row['denda_terlambat'],
                (float) $row['biaya_kerusakan'],
                $row['catatan_pengembalian'] ?? '',
            ];
        }
        export_csv($header, $baris, 'laporan-data-lengkap-' . $dari . '-' . $sampai . '.csv');
        break;

    default:
        header('Location: ../laporan.php');
        exit;
}