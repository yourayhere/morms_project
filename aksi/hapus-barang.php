<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../inventaris.php?error=token');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$konfirmasi = trim($_POST['konfirmasi'] ?? '');

$item = $id ? ItemModel::getByIdAdmin($id) : null;

if (!$item) {
    header('Location: ../inventaris.php?error=tidak_ditemukan');
    exit;
}

if ($konfirmasi !== 'CONFIRM') {
    header('Location: ../form-barang.php?id=' . $id . '&error=konfirmasi');
    exit;
}

if (ItemModel::getJumlahRiwayatSewa($id) > 0) {
    header('Location: ../form-barang.php?id=' . $id . '&error=ada_riwayat');
    exit;
}

// Ambil daftar foto dulu sebelum baris item dihapus (item_images ikut
// terhapus otomatis lewat FK cascade), supaya file fisiknya masih bisa
// ditemukan & dibersihkan dari disk setelahnya.
$gambarList = ItemModel::getImages($id);

try {
    ItemModel::hapus($id);
} catch (\PDOException $e) {
    // Jaring pengaman terakhir kalau ada riwayat sewa yang somehow terlewat
    // oleh pengecekan di atas (mis. race condition) — booking_items.item_id
    // di database sengaja ON DELETE RESTRICT.
    error_log('[morms] Hapus barang gagal, kemungkinan masih ada riwayat sewa: ' . $e->getMessage());
    header('Location: ../form-barang.php?id=' . $id . '&error=ada_riwayat');
    exit;
}

foreach ($gambarList as $gambar) {
    $fullPath = __DIR__ . '/../' . $gambar['url'];
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }
}

catat_aktivitas((int) Auth::id(), 'hapus_barang', 'Barang ' . $item['nama'] . ' dihapus permanen dari inventaris');

header('Location: ../inventaris.php?sukses=hapus');
exit;
