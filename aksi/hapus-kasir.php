<?php

require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/CartKasir.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/kasir.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\CartKasir;

Session::start();
header('Content-Type: application/json');

if (!Auth::isLoggedIn() || !in_array(Auth::role(), ['owner', 'admin', 'kasir'], true)) {
    echo json_encode(['sukses' => false]);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['sukses' => false]);
    exit;
}

$index = (int) ($_POST['index'] ?? -1);
CartKasir::remove($index);

$keranjangKasir = CartKasir::getAll();

echo json_encode([
    'sukses' => true,
    'html_keranjang' => render_keranjang_kasir_html($keranjangKasir),
    'total' => format_rupiah(CartKasir::getTotal()),
    'jumlah_baris' => count($keranjangKasir),
]);
