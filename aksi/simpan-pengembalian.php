<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Helpers/paths.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';
require_once __DIR__ . '/../app/Models/TransactionModel.php';
require_once __DIR__ . '/../app/Models/ReturnModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;
use App\Models\BookingModel;
use App\Models\TransactionModel;
use App\Models\ReturnModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pengembalian.php');
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$booking = BookingModel::getById($bookingId);

if (!$booking) {
    header('Location: ../pengembalian.php');
    exit;
}

$sertakan = $_POST['sertakan'] ?? [];
$kondisiItem = $_POST['kondisi_item'] ?? [];
$dendaItem = $_POST['denda_terlambat_item'] ?? [];
$rusakItem = $_POST['biaya_kerusakan_item'] ?? [];
$keterangan = clean_input($_POST['keterangan'] ?? '');

$idBookingItemTerpilih = array_map('intval', array_keys(array_filter($sertakan, fn($v) => $v === '1')));

if (empty($idBookingItemTerpilih)) {
    header('Location: ../proses-pengembalian.php?id=' . $bookingId . '&error=pilih');
    exit;
}

// Catatan pengembalian, transaksi denda/kerusakan, status tiap barang, dan
// (kalau semua barang sudah kembali) status booking harus tersimpan
// sekaligus (atomik).
$db = Database::getConnection();
$db->beginTransaction();

try {
    $totalDenda = 0.0;
    $totalRusak = 0.0;
    $idReturnsBaru = [];
    $kondisiUntukLog = 'lengkap';

    foreach ($idBookingItemTerpilih as $bookingItemId) {
        // Kunci baris & pastikan barang ini benar-benar milik booking yang
        // dimaksud dan masih berstatus "disewa" - mencegah dua admin yang
        // memproses pengembalian booking yang sama secara bersamaan
        // memproses barang yang sama dua kali (race condition).
        $stmtLock = $db->prepare(
            'SELECT bi.*, i.nama, i.harga_per_malam FROM booking_items bi
             JOIN items i ON i.id = bi.item_id
             WHERE bi.id = :id AND bi.booking_id = :booking_id FOR UPDATE'
        );
        $stmtLock->execute(['id' => $bookingItemId, 'booking_id' => $bookingId]);
        $barang = $stmtLock->fetch();

        if (!$barang || $barang['status'] !== 'disewa') {
            // Sudah diproses lebih dulu (mis. oleh admin lain) - lewati diam-diam.
            continue;
        }

        $kondisi = $kondisiItem[$bookingItemId] ?? 'lengkap';
        $denda = (float) ($dendaItem[$bookingItemId] ?? 0);
        $rusak = (float) ($rusakItem[$bookingItemId] ?? 0);

        $returnId = ReturnModel::buat([
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'kondisi' => $kondisi,
            'keterangan' => $keterangan,
            'denda_terlambat' => $denda,
            'biaya_kerusakan' => $rusak,
            'admin_id' => Auth::id(),
        ]);
        $idReturnsBaru[] = $returnId;

        BookingModel::updateStatusItem($bookingItemId, 'dikembalikan');

        $totalDenda += $denda;
        $totalRusak += $rusak;

        if ($kondisi === 'hilang') {
            $kondisiUntukLog = 'hilang';
        } elseif ($kondisi === 'rusak' && $kondisiUntukLog !== 'hilang') {
            $kondisiUntukLog = 'rusak';
        } elseif ($kondisi === 'kurang' && $kondisiUntukLog === 'lengkap') {
            $kondisiUntukLog = 'kurang';
        }
    }

    if (empty($idReturnsBaru)) {
        throw new \RuntimeException('SUDAH_DIPROSES');
    }

    if ($totalDenda > 0) {
        $transactionId = TransactionModel::buat([
            'booking_id' => $bookingId,
            'jenis' => 'denda',
            'nominal' => $totalDenda,
            'metode' => 'cash',
            'bukti_bayar' => null,
            'status_verifikasi' => 'terverifikasi',
        ]);
        TransactionModel::verifikasi($transactionId, 'terverifikasi', (int) Auth::id());
    }

    if ($totalRusak > 0) {
        $transactionId = TransactionModel::buat([
            'booking_id' => $bookingId,
            'jenis' => 'tambahan',
            'nominal' => $totalRusak,
            'metode' => 'cash',
            'bukti_bayar' => null,
            'status_verifikasi' => 'terverifikasi',
        ]);
        TransactionModel::verifikasi($transactionId, 'terverifikasi', (int) Auth::id());
    }

    // Booking baru dianggap selesai total kalau SEMUA barangnya sudah
    // berstatus "dikembalikan" - bukan lagi otomatis begitu satu event
    // pengembalian diproses, karena sekarang pengembalian bisa bertahap.
    $bookingSelesai = BookingModel::getJumlahBelumDikembalikan($bookingId) === 0;
    if ($bookingSelesai) {
        BookingModel::updateStatus($bookingId, 'SELESAI');
    }

    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    if ($e->getMessage() !== 'SUDAH_DIPROSES') {
        error_log('[morms] Proses pengembalian gagal, rolled back: ' . $e->getMessage());
    }
    header('Location: ../proses-pengembalian.php?id=' . $bookingId . '&error=sistem');
    exit;
}

catat_aktivitas(
    (int) Auth::id(),
    'pengembalian_selesai',
    'Booking ' . $booking['kode_booking'] . ': ' . count($idReturnsBaru) . ' barang dikembalikan dengan kondisi ' . $kondisiUntukLog
    . ($bookingSelesai ? ' (booking selesai)' : ' (masih ada barang lain yang belum kembali)')
);

// Identitas jaminan baru dilepas begitu SELURUH barang di booking ini sudah
// kembali - sebelumnya (booking sengaja belum SELESAI), tetap ditahan karena
// masih ada barang lain yang dipinjam atas identitas yang sama.
if ($bookingSelesai && !empty($booking['identitas_file'])) {
    $pathIdentitas = storage_path('identitas/' . $booking['identitas_file']);
    if (file_exists($pathIdentitas)) {
        @unlink($pathIdentitas);
    }
    BookingModel::hapusIdentitas($bookingId);
    catat_aktivitas((int) Auth::id(), 'hapus_identitas', 'Identitas booking ' . $booking['kode_booking'] . ' dihapus otomatis setelah penyewaan selesai');
}

header('Location: ../struk-pengembalian.php?ids=' . implode(',', $idReturnsBaru));
exit;
