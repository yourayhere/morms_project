<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Models\ItemModel;

header('Content-Type: application/json');

$itemId = (int) ($_GET['item_id'] ?? 0);
$ambil = $_GET['ambil'] ?? '';
$kembali = $_GET['kembali'] ?? '';
$jumlah = (int) ($_GET['jumlah'] ?? 1);
$ukuran = clean_input($_GET['ukuran'] ?? '');

$item = ItemModel::getById($itemId);

if (!$item || !$ambil || !$kembali) {
    echo json_encode(['tersedia' => false, 'sisa' => 0]);
    exit;
}

if ($item['kategori'] === 'pakaian_outdoor') {
    if ($ukuran === '') {
        echo json_encode(['tersedia' => false, 'sisa' => 0]);
        exit;
    }
    $variasi = array_values(array_filter(ItemModel::getVariasi($itemId), fn($v) => $v['ukuran'] === $ukuran));
    if (empty($variasi)) {
        echo json_encode(['tersedia' => false, 'sisa' => 0]);
        exit;
    }
    $terpakai = ItemModel::getStokTerpakaiVarian($itemId, $ukuran, $ambil, $kembali);
    $sisa = max(0, (int) $variasi[0]['stok'] - $terpakai);
} else {
    $terpakai = ItemModel::getStokTerpakai($itemId, $ambil, $kembali);
    $sisa = max(0, (int) $item['stok_total'] - $terpakai);
}

echo json_encode([
    'tersedia' => $sisa >= $jumlah,
    'sisa' => $sisa,
]);