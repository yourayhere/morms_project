<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/CustomerModel.php';
require_once __DIR__ . '/../app/Models/TeamModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\CustomerModel;
use App\Models\TeamModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$userId = (int) ($_POST['user_id'] ?? 0);
$hp = clean_input($_POST['hp'] ?? '');
$redirectBack = '../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId;

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $redirectBack . '&error=token');
    exit;
}

$member = $userId ? CustomerModel::getMemberById($userId) : null;

if (!$member) {
    header('Location: ../pelanggan.php');
    exit;
}

$passwordBaru = $_POST['password_baru'] ?? '';
$konfirmasi = $_POST['konfirmasi_password'] ?? '';

if (strlen($passwordBaru) < 8) {
    header('Location: ' . $redirectBack . '&error=password_pendek');
    exit;
}

if ($passwordBaru !== $konfirmasi) {
    header('Location: ' . $redirectBack . '&error=konfirmasi_password');
    exit;
}

TeamModel::gantiPassword($userId, $passwordBaru);
catat_aktivitas((int) Auth::id(), 'reset_password_pelanggan', 'Kata sandi member ' . $member['nama'] . ' direset oleh admin');

header('Location: ' . $redirectBack . '&sukses=password');
exit;
