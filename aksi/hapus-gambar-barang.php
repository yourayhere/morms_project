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

$imageId = (int) ($_POST['image_id'] ?? 0);
$gambar = $imageId ? ItemModel::getGambarById($imageId) : null;

if (!$gambar) {
    header('Location: ../inventaris.php');
    exit;
}

$itemId = (int) $gambar['item_id'];

$fullPath = __DIR__ . '/../' . $gambar['url'];
if (file_exists($fullPath)) {
    @unlink($fullPath);
}

ItemModel::hapusGambarById($imageId);

catat_aktivitas((int) Auth::id(), 'hapus_gambar_barang', 'Foto barang ID ' . $itemId . ' dihapus');

header('Location: ../form-barang.php?id=' . $itemId);
exit;
