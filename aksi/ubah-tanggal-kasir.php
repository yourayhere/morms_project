<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/CartKasir.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/kasir.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\CartKasir;
use App\Models\ItemModel;

Session::start();
header('Content-Type: application/json');

if (!Auth::isLoggedIn() || !in_array(Auth::role(), ['owner', 'admin', 'kasir'], true)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Akses ditolak.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Token keamanan tidak valid. Muat ulang halaman.']);
    exit;
}

$index = (int) ($_POST['index'] ?? -1);
$ambil = $_POST['ambil'] ?? '';
$kembali = $_POST['kembali'] ?? '';
$jamAmbil = $_POST['jam_ambil'] ?? '';

$keranjangSaatIni = CartKasir::getAll();
if (!isset($keranjangSaatIni[$index])) {
    echo json_encode(['sukses' => false, 'pesan' => 'Barang tidak ditemukan di keranjang.']);
    exit;
}

if (!$ambil || !$kembali || strtotime($kembali) <= strtotime($ambil) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamAmbil)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Tanggal atau jam tidak valid.']);
    exit;
}

$barang = $keranjangSaatIni[$index];
$item = ItemModel::getById($barang['item_id']);
$ukuran = $barang['ukuran'] ?? '';

if (!$item) {
    echo json_encode(['sukses' => false, 'pesan' => 'Barang sudah tidak tersedia.']);
    exit;
}

if ($item['kategori'] === 'pakaian_outdoor' && $ukuran !== '') {
    $variasi = array_values(array_filter(ItemModel::getVariasi($barang['item_id']), fn($v) => $v['ukuran'] === $ukuran));
    $stokTotal = (int) ($variasi[0]['stok'] ?? 0);
    $terpakaiDb = ItemModel::getStokTerpakaiVarian($barang['item_id'], $ukuran, $ambil, $kembali);
} else {
    $stokTotal = (int) $item['stok_total'];
    $terpakaiDb = ItemModel::getStokTerpakai($barang['item_id'], $ambil, $kembali);
}

$sisaTersedia = $stokTotal - $terpakaiDb;

// Baris lain (BUKAN baris yang sedang diedit ini sendiri) di keranjang kasir
// yang kebetulan sama barang+ukuran+tanggal barunya - jumlahnya ikut
// bersaing atas stok virtual yang sama, sama seperti logika di tambah-kasir.php.
$jumlahBarisLain = 0;
foreach ($keranjangSaatIni as $i => $baris) {
    if ($i === $index) {
        continue;
    }
    if ((int) $baris['item_id'] === (int) $barang['item_id']
        && (string) ($baris['ukuran'] ?? '') === $ukuran
        && $baris['tanggal_ambil'] === $ambil
        && $baris['tanggal_kembali'] === $kembali
        && (string) ($baris['jam_ambil'] ?? '') === $jamAmbil
    ) {
        $jumlahBarisLain += (int) $baris['jumlah'];
    }
}

$jumlahDiusulkan = $jumlahBarisLain + (int) $barang['jumlah'];

if ($jumlahDiusulkan > $sisaTersedia) {
    echo json_encode(['sukses' => false, 'pesan' => $sisaTersedia > 0
        ? 'Stok tidak cukup untuk tanggal tersebut. Sisa stok: ' . $sisaTersedia . '.'
        : 'Stok tidak tersedia untuk tanggal tersebut.']);
    exit;
}

CartKasir::updateTanggal($index, $ambil, $kembali, $jamAmbil);
$keranjangKasir = CartKasir::getAll();

echo json_encode([
    'sukses' => true,
    'html_keranjang' => render_keranjang_kasir_html($keranjangKasir),
    'total' => format_rupiah(CartKasir::getTotal()),
    'jumlah_baris' => count($keranjangKasir),
]);
