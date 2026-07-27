<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;
use App\Models\BookingModel;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../penyewaan-aktif.php');
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$itemId = (int) ($_POST['item_id'] ?? 0);
$jumlah = (int) ($_POST['jumlah'] ?? 1);
$ukuran = clean_input($_POST['ukuran'] ?? '');
$tanggalAmbilBaru = $_POST['tanggal_ambil'] ?? '';
$tanggalKembaliBaru = $_POST['tanggal_kembali'] ?? '';

$booking = BookingModel::getById($bookingId);
$item = ItemModel::getById($itemId);

if (!$booking || !$item || $jumlah < 1 || !$tanggalAmbilBaru || !$tanggalKembaliBaru
    || strtotime($tanggalKembaliBaru) <= strtotime($tanggalAmbilBaru)
) {
    header('Location: ../detail-penyewaan.php?id=' . $bookingId . '&error=data');
    exit;
}

$isPakaianTambahan = $item['kategori'] === 'pakaian_outdoor';

if ($isPakaianTambahan && $ukuran === '') {
    header('Location: ../detail-penyewaan.php?id=' . $bookingId . '&error=ukuran');
    exit;
}

// Barang tambahan ini punya periode sewa sendiri (dipilih admin lewat date
// picker di detail-penyewaan.php), tidak lagi otomatis mengikuti tanggal
// kembali booking secara keseluruhan.
$durasi = max(1, (strtotime($tanggalKembaliBaru) - strtotime($tanggalAmbilBaru)) / 86400);
$subtotal = $item['harga_per_malam'] * $durasi * $jumlah;

// Kunci baris item & validasi ulang stok di dalam transaction, supaya dua
// admin/kasir yang menambah barang bersamaan tidak lolos validasi berbarengan.
$db = Database::getConnection();
$db->beginTransaction();

try {
    if ($isPakaianTambahan) {
        $sisa = ItemModel::getSisaStokVarianUntukLocking($itemId, $ukuran, $tanggalAmbilBaru, $tanggalKembaliBaru);
    } else {
        $sisa = ItemModel::getSisaStokUntukLocking($itemId, $tanggalAmbilBaru, $tanggalKembaliBaru);
    }

    if ($sisa < $jumlah) {
        $db->rollBack();
        header('Location: ../detail-penyewaan.php?id=' . $bookingId . '&error=stok');
        exit;
    }

    BookingModel::tambahItemBooking($bookingId, $itemId, $jumlah, $subtotal, $isPakaianTambahan ? $ukuran : null, $tanggalAmbilBaru, $tanggalKembaliBaru, $booking['jam_ambil']);
    BookingModel::pulihkanEnvelope($bookingId);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    error_log('[morms] Tambah barang sewa gagal, rolled back: ' . $e->getMessage());
    header('Location: ../detail-penyewaan.php?id=' . $bookingId . '&error=sistem');
    exit;
}

catat_aktivitas((int) Auth::id(), 'tambah_barang_sewa', 'Barang ' . $item['nama'] . ' ditambahkan ke booking ' . $booking['kode_booking']);

header('Location: ../detail-penyewaan.php?id=' . $bookingId);
exit;