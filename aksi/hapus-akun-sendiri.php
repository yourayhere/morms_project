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
Auth::requireRole(['member'], '../login.php');

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../dashboard.php?error=token');
    exit;
}

$konfirmasi = trim($_POST['konfirmasi'] ?? '');

if ($konfirmasi !== 'CONFIRM') {
    header('Location: ../dashboard.php?error=konfirmasi');
    exit;
}

$userId = (int) Auth::id();
$nama = Session::get('user_nama');

// Log dulu sebelum baris user-nya dihapus - kolom user_id di activity_log
// otomatis jadi NULL lewat FK ON DELETE SET NULL, tapi detailnya tetap
// menyimpan nama untuk jejak audit.
catat_aktivitas($userId, 'hapus_akun_sendiri', 'Member "' . $nama . '" menghapus akun sendiri secara permanen');

CustomerModel::hapusMember($userId);

Auth::logout();

header('Location: ../login.php?sukses=akun_dihapus');
exit;
