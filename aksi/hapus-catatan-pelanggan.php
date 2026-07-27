<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/CustomerModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\CustomerModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$hp = clean_input($_POST['hp'] ?? '');
$userId = (int) ($_POST['user_id'] ?? 0);
$catatanId = (int) ($_POST['catatan_id'] ?? 0);

if (!verify_csrf_token($_POST['csrf_token'] ?? null) || $catatanId <= 0) {
    header('Location: ../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId);
    exit;
}

CustomerModel::hapusCatatan($catatanId);
catat_aktivitas((int) Auth::id(), 'hapus_catatan_pelanggan', 'Catatan pelanggan HP ' . $hp . ' dihapus');

header('Location: ../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId);
exit;
