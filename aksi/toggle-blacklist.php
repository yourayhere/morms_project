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

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pelanggan.php');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$hp = clean_input($_POST['hp'] ?? '');

$db = \App\Core\Database::getConnection();
$stmt = $db->prepare('SELECT status_aktif FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$statusSekarang = $stmt->fetchColumn();

CustomerModel::setBlacklist($userId, $statusSekarang === 'aktif');

catat_aktivitas((int) Auth::id(), 'toggle_blacklist', 'Status akun member ID ' . $userId . ' diubah');

header('Location: ../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId);
exit;