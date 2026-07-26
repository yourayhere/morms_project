<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Cart.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/format.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Cart;
use App\Models\ItemModel;

Session::start();
header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Token keamanan tidak valid. Muat ulang halaman.']);
    exit;
}

$index = (int) ($_POST['index'] ?? -1);
$ambil = $_POST['ambil'] ?? '';
$kembali = $_POST['kembali'] ?? '';
$jamAmbil = $_POST['jam_ambil'] ?? '';

$daftar = Cart::getAll();
if (!isset($daftar[$index])) {
    echo json_encode(['sukses' => false, 'pesan' => 'Barang tidak ditemukan di keranjang.']);
    exit;
}

if (!$ambil || !$kembali || strtotime($kembali) <= strtotime($ambil) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamAmbil)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Tanggal atau jam tidak valid.']);
    exit;
}

$barang = $daftar[$index];
$item = ItemModel::getById($barang['item_id']);
$ukuran = $barang['ukuran'] ?? '';

if (!$item) {
    echo json_encode(['sukses' => false, 'pesan' => 'Barang sudah tidak tersedia.']);
    exit;
}

// Cek ulang stok untuk tanggal BARU (sama seperti saat pertama kali
// menambahkan barang ke keranjang) - jumlah barang di baris ini tidak
// berubah, cuma periode sewanya.
if ($item['kategori'] === 'pakaian_outdoor' && $ukuran !== '') {
    $variasi = array_values(array_filter(ItemModel::getVariasi($barang['item_id']), fn($v) => $v['ukuran'] === $ukuran));
    $stokVarian = $variasi[0]['stok'] ?? 0;
    $terpakai = ItemModel::getStokTerpakaiVarian($barang['item_id'], $ukuran, $ambil, $kembali);
    $sisa = (int) $stokVarian - $terpakai;
} else {
    $terpakai = ItemModel::getStokTerpakai($barang['item_id'], $ambil, $kembali);
    $sisa = (int) $item['stok_total'] - $terpakai;
}

if ($sisa < (int) $barang['jumlah']) {
    echo json_encode(['sukses' => false, 'pesan' => 'Stok tidak cukup untuk tanggal tersebut. Sisa stok: ' . max(0, $sisa) . '.']);
    exit;
}

Cart::updateTanggal($index, $ambil, $kembali, $jamAmbil);
$barangBaru = Cart::getAll()[$index];
$envelope = Cart::getEnvelope();

echo json_encode([
    'sukses' => true,
    'tanggal_ambil' => $barangBaru['tanggal_ambil'],
    'tanggal_kembali' => $barangBaru['tanggal_kembali'],
    'jam_ambil' => $barangBaru['jam_ambil'],
    'durasi' => $barangBaru['durasi'],
    'subtotal' => $barangBaru['subtotal'],
    'periode_text' => format_periode_sewa($barangBaru['tanggal_ambil'], $barangBaru['tanggal_kembali'], $barangBaru['jam_ambil']),
    'total_keseluruhan' => Cart::getTotal(),
    'envelope_text' => format_periode_sewa($envelope['ambil'], $envelope['kembali'], $envelope['jam'], $envelope['jam_kembali']),
]);
