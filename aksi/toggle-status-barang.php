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
    header('Location: ../inventaris.php');
    exit;
}

$itemId = (int) ($_POST['item_id'] ?? 0);
ItemModel::toggleStatus($itemId);

catat_aktivitas((int) Auth::id(), 'toggle_status_barang', 'Status barang ID ' . $itemId . ' diubah');

header('Location: ../inventaris.php');
exit;