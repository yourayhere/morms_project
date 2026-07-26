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

$itemId = (int) ($_POST['item_id'] ?? 0);
$ambil = $_POST['ambil'] ?? '';
$kembali = $_POST['kembali'] ?? '';
$jamAmbil = $_POST['jam_ambil'] ?? '';
$ukuran = clean_input($_POST['ukuran'] ?? '');

$item = ItemModel::getById($itemId);

if (!$item || !$ambil || !$kembali || strtotime($kembali) <= strtotime($ambil) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamAmbil)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Data tidak valid.']);
    exit;
}

$isPakaianKasir = $item['kategori'] === 'pakaian_outdoor';

if ($isPakaianKasir && $ukuran === '') {
    echo json_encode(['sukses' => false, 'pesan' => 'Pilih ukuran terlebih dahulu.']);
    exit;
}

// Setiap barang boleh punya periode sewa sendiri-sendiri (tidak lagi harus
// sama dengan barang lain yang sudah dipilih kasir) - lihat
// CartKasir::getEnvelope() untuk bagaimana rentang keseluruhan dihitung saat
// checkout.
$keranjangSaatIni = CartKasir::getAll();

if (!$isPakaianKasir) {
    $ukuran = '';
}

if ($isPakaianKasir) {
    $variasi = array_values(array_filter(ItemModel::getVariasi($itemId), fn($v) => $v['ukuran'] === $ukuran));
    if (empty($variasi)) {
        echo json_encode(['sukses' => false, 'pesan' => 'Ukuran tidak tersedia.']);
        exit;
    }
    $terpakaiDb = ItemModel::getStokTerpakaiVarian($itemId, $ukuran, $ambil, $kembali);
    $stokTotal = (int) $variasi[0]['stok'];
} else {
    $terpakaiDb = ItemModel::getStokTerpakai($itemId, $ambil, $kembali);
    $stokTotal = (int) $item['stok_total'];
}

// Stok yang sebenarnya tersedia untuk direservasi lewat kasir SEKARANG -
// belum dikurangi apa pun yang sudah ada di keranjang kasir ini sendiri,
// karena baris keranjang belum pernah tersimpan ke database (booking_items)
// sampai checkout selesai, jadi getStokTerpakai() tidak pernah tahu soal itu.
$sisaTersedia = $stokTotal - $terpakaiDb;

// Kalau barang+ukuran+periode ini SUDAH ada barisnya di keranjang, jumlah
// baru yang diusulkan adalah jumlah baris itu + 1 (bakal digabung, bukan
// baris baru) - supaya validasi stok memperhitungkan apa yang sudah
// "dipesan" secara virtual di keranjang, bukan cuma stok mentah dari DB.
$jumlahSudahDiKeranjang = 0;
foreach ($keranjangSaatIni as $baris) {
    if ((int) $baris['item_id'] === $itemId
        && (string) ($baris['ukuran'] ?? '') === $ukuran
        && $baris['tanggal_ambil'] === $ambil
        && $baris['tanggal_kembali'] === $kembali
        && (string) ($baris['jam_ambil'] ?? '') === $jamAmbil
    ) {
        $jumlahSudahDiKeranjang += (int) $baris['jumlah'];
    }
}

$jumlahDiusulkan = $jumlahSudahDiKeranjang + 1;

if ($jumlahDiusulkan > $sisaTersedia) {
    echo json_encode(['sukses' => false, 'pesan' => $sisaTersedia > 0
        ? 'Stok tidak cukup. Sisa stok untuk periode ini: ' . $sisaTersedia . '.'
        : 'Stok tidak tersedia untuk periode tersebut.']);
    exit;
}

$durasi = (strtotime($kembali) - strtotime($ambil)) / 86400;
$subtotal = $item['harga_per_malam'] * $durasi;

CartKasir::add([
    'item_id' => $itemId,
    'nama' => $item['nama'],
    'harga_per_malam' => (float) $item['harga_per_malam'],
    'tanggal_ambil' => $ambil,
    'tanggal_kembali' => $kembali,
    'jam_ambil' => $jamAmbil,
    'jumlah' => 1,
    'durasi' => (int) $durasi,
    'subtotal' => $subtotal,
    'ukuran' => $ukuran,
]);

$keranjangKasir = CartKasir::getAll();

echo json_encode([
    'sukses' => true,
    'html_keranjang' => render_keranjang_kasir_html($keranjangKasir),
    'total' => format_rupiah(CartKasir::getTotal()),
    'jumlah_baris' => count($keranjangKasir),
]);
