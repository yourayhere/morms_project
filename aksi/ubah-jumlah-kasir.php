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
$arah = $_POST['arah'] ?? '';

$baris = CartKasir::getByIndex($index);

if (!$baris || !in_array($arah, ['plus', 'minus'], true)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Data tidak valid.']);
    exit;
}

if ($arah === 'minus') {
    CartKasir::updateJumlah($index, (int) $baris['jumlah'] - 1);
} else {
    $itemId = (int) $baris['item_id'];
    $ukuran = (string) ($baris['ukuran'] ?? '');
    $item = ItemModel::getById($itemId);

    if (!$item) {
        echo json_encode(['sukses' => false, 'pesan' => 'Barang tidak ditemukan.']);
        exit;
    }

    if ($ukuran !== '') {
        $variasi = array_values(array_filter(ItemModel::getVariasi($itemId), fn($v) => $v['ukuran'] === $ukuran));
        $stokTotal = !empty($variasi) ? (int) $variasi[0]['stok'] : 0;
        $terpakaiDb = ItemModel::getStokTerpakaiVarian($itemId, $ukuran, $baris['tanggal_ambil'], $baris['tanggal_kembali']);
    } else {
        $stokTotal = (int) $item['stok_total'];
        $terpakaiDb = ItemModel::getStokTerpakai($itemId, $baris['tanggal_ambil'], $baris['tanggal_kembali']);
    }

    $sisaTersedia = $stokTotal - $terpakaiDb;
    $jumlahBaru = (int) $baris['jumlah'] + 1;

    if ($jumlahBaru > $sisaTersedia) {
        echo json_encode([
            'sukses' => false,
            'pesan' => 'Stok tidak cukup. Sisa stok untuk periode ini: ' . max(0, $sisaTersedia) . '.',
            'html_keranjang' => render_keranjang_kasir_html(CartKasir::getAll()),
            'total' => format_rupiah(CartKasir::getTotal()),
        ]);
        exit;
    }

    CartKasir::updateJumlah($index, $jumlahBaru);
}

$keranjangKasir = CartKasir::getAll();

echo json_encode([
    'sukses' => true,
    'html_keranjang' => render_keranjang_kasir_html($keranjangKasir),
    'total' => format_rupiah(CartKasir::getTotal()),
    'jumlah_baris' => count($keranjangKasir),
]);
