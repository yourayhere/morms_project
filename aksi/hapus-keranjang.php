<?php

require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Cart.php';
require_once __DIR__ . '/../app/Helpers/security.php';

use App\Core\Session;
use App\Core\Cart;

Session::start();
header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['sukses' => false]);
    exit;
}

$index = (int) ($_POST['index'] ?? -1);
Cart::remove($index);

echo json_encode(['sukses' => true]);