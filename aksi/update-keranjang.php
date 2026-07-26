<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Cart.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Cart;
use App\Models\ItemModel;

Session::start();
header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['sukses' => false]);
    exit;
}

$index = (int) ($_POST['index'] ?? -1);
$jumlah = (int) ($_POST['jumlah'] ?? 1);

$daftar = Cart::getAll();
if (!isset($daftar[$index])) {
    echo json_encode(['sukses' => false]);
    exit;
}

$itemId = $daftar[$index]['item_id'];
$ukuran = $daftar[$index]['ukuran'] ?? '';
$item = ItemModel::getById($itemId);

if ($item['kategori'] === 'pakaian_outdoor' && $ukuran !== '') {
    $variasi = array_values(array_filter(ItemModel::getVariasi($itemId), fn($v) => $v['ukuran'] === $ukuran));
    $stokVarian = $variasi[0]['stok'] ?? 0;
    $terpakai = ItemModel::getStokTerpakaiVarian($itemId, $ukuran, $daftar[$index]['tanggal_ambil'], $daftar[$index]['tanggal_kembali']);
    $stokTanpaItemIni = (int) $stokVarian - $terpakai + (int) $daftar[$index]['jumlah'];
} else {
    $terpakai = ItemModel::getStokTerpakai($itemId, $daftar[$index]['tanggal_ambil'], $daftar[$index]['tanggal_kembali']);
    $stokTanpaItemIni = (int) $item['stok_total'] - $terpakai + (int) $daftar[$index]['jumlah'];
}

if ($jumlah > $stokTanpaItemIni) {
    echo json_encode(['sukses' => false, 'pesan' => 'Stok tidak cukup.']);
    exit;
}

Cart::update($index, $jumlah, $stokTanpaItemIni);
$barangBaru = Cart::getAll()[$index];

echo json_encode([
    'sukses' => true,
    'jumlah' => $barangBaru['jumlah'],
    'subtotal' => $barangBaru['subtotal'],
    'total_keseluruhan' => Cart::getTotal(),
]);