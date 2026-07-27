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
    header('Location: ../pelanggan.php?error=token');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$hp = clean_input($_POST['hp'] ?? '');
$konfirmasi = trim($_POST['konfirmasi'] ?? '');

$member = $userId ? CustomerModel::getMemberById($userId) : null;

if (!$member) {
    header('Location: ../pelanggan.php?error=tidak_ditemukan');
    exit;
}

if ($konfirmasi !== 'CONFIRM') {
    header('Location: ../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId . '&error=konfirmasi');
    exit;
}

CustomerModel::hapusMember($userId);
catat_aktivitas((int) Auth::id(), 'hapus_pelanggan', 'Akun member ' . $member['nama'] . ' dihapus permanen');

header('Location: ../pelanggan.php?sukses=hapus');
exit;
