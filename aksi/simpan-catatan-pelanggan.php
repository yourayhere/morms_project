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
$isi = trim(clean_input($_POST['isi'] ?? ''));

if (!verify_csrf_token($_POST['csrf_token'] ?? null) || $hp === '' || $isi === '') {
    header('Location: ../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId);
    exit;
}

if ($catatanId > 0) {
    CustomerModel::updateCatatan($catatanId, $isi);
    catat_aktivitas((int) Auth::id(), 'edit_catatan_pelanggan', 'Catatan pelanggan HP ' . $hp . ' diedit');
} else {
    CustomerModel::tambahCatatan($hp, $isi, (int) Auth::id());
    catat_aktivitas((int) Auth::id(), 'tambah_catatan_pelanggan', 'Catatan baru untuk pelanggan HP ' . $hp);
}

header('Location: ../detail-pelanggan.php?hp=' . urlencode($hp) . '&user_id=' . $userId);
exit;
