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

$userId = (int) ($_POST['user_id'] ?? 0);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../detail-pelanggan.php?user_id=' . $userId . '&error=token');
    exit;
}

$member = $userId ? CustomerModel::getMemberById($userId) : null;

if (!$member) {
    header('Location: ../pelanggan.php');
    exit;
}

$nama  = clean_input($_POST['nama'] ?? '');
$noHp  = normalisasi_no_hp(clean_input($_POST['no_hp'] ?? ''));
$email = clean_input($_POST['email'] ?? '');

$redirectBack = '../detail-pelanggan.php?hp=' . urlencode($noHp !== '' ? $noHp : $member['no_hp']) . '&user_id=' . $userId;

if ($nama === '' || $noHp === '') {
    header('Location: ' . $redirectBack . '&error=data');
    exit;
}

if (!validasi_no_hp($noHp)) {
    header('Location: ' . $redirectBack . '&error=format_hp');
    exit;
}

if ($email !== '' && !validasi_email($email)) {
    header('Location: ' . $redirectBack . '&error=format_email');
    exit;
}

if (CustomerModel::cekHpDuplikatMember($noHp, $userId)) {
    header('Location: ' . $redirectBack . '&error=hp_duplikat');
    exit;
}

if ($email !== '' && CustomerModel::cekEmailDuplikatMember($email, $userId)) {
    header('Location: ' . $redirectBack . '&error=email_duplikat');
    exit;
}

CustomerModel::updateMember($userId, $nama, $noHp, $email);
catat_aktivitas((int) Auth::id(), 'edit_pelanggan', 'Data pelanggan ' . $nama . ' diperbarui');

header('Location: ../detail-pelanggan.php?hp=' . urlencode($noHp) . '&user_id=' . $userId . '&sukses=1');
exit;
