<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Cart.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/toko.php';
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

if (toko_sedang_tutup()) {
    echo json_encode(['sukses' => false, 'pesan' => pesan_toko_tutup()]);
    exit;
}

$itemId = (int) ($_POST['item_id'] ?? 0);
$ambil = $_POST['ambil'] ?? '';
$kembali = $_POST['kembali'] ?? '';
$jamAmbil = $_POST['jam_ambil'] ?? '';
$jumlah = (int) ($_POST['jumlah'] ?? 1);
$ukuran = clean_input($_POST['ukuran'] ?? '');

$item = ItemModel::getById($itemId);

if (!$item || !$ambil || !$kembali || $jumlah < 1 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamAmbil)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Data tidak valid.']);
    exit;
}

$isPakaian = $item['kategori'] === 'pakaian_outdoor';

if ($isPakaian) {
    if ($ukuran === '') {
        echo json_encode(['sukses' => false, 'pesan' => 'Silakan pilih ukuran terlebih dahulu.']);
        exit;
    }
    $variasi = array_values(array_filter(ItemModel::getVariasi($itemId), fn($v) => $v['ukuran'] === $ukuran));
    if (empty($variasi)) {
        echo json_encode(['sukses' => false, 'pesan' => 'Ukuran tidak tersedia.']);
        exit;
    }
    $terpakai = ItemModel::getStokTerpakaiVarian($itemId, $ukuran, $ambil, $kembali);
    $sisa = (int) $variasi[0]['stok'] - $terpakai;
} else {
    $ukuran = '';
    $terpakai = ItemModel::getStokTerpakai($itemId, $ambil, $kembali);
    $sisa = (int) $item['stok_total'] - $terpakai;
}

if ($sisa < $jumlah) {
    echo json_encode(['sukses' => false, 'pesan' => 'Stok tidak cukup untuk tanggal tersebut.']);
    exit;
}

$durasi = (strtotime($kembali) - strtotime($ambil)) / 86400;
$subtotal = $item['harga_per_malam'] * $durasi * $jumlah;

// Setiap barang boleh punya periode sewa sendiri-sendiri (tidak lagi harus
// sama dengan barang lain yang sudah ada di keranjang) - lihat
// Cart::getEnvelope() untuk bagaimana rentang keseluruhan dihitung saat
// checkout.
Cart::add([
    'item_id' => $itemId,
    'nama' => $item['nama'],
    'harga_per_malam' => (float) $item['harga_per_malam'],
    'tanggal_ambil' => $ambil,
    'tanggal_kembali' => $kembali,
    'jam_ambil' => $jamAmbil,
    'jumlah' => $jumlah,
    'durasi' => (int) $durasi,
    'subtotal' => $subtotal,
    'ukuran' => $ukuran,
]);

echo json_encode(['sukses' => true]);