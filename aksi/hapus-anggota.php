<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/TeamModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\TeamModel;

Session::start();
Auth::requireRole(['owner']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../tim.php?error=token');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$konfirmasi = trim($_POST['konfirmasi'] ?? '');

// Jangan pernah izinkan akun menghapus dirinya sendiri — ini juga jaring
// pengaman terakhir supaya jumlah Owner aktif tidak pernah bisa jadi nol
// (satu-satunya cara mencapai itu adalah Owner terakhir menghapus akun
// sendiri, yang mana selalu diblokir di sini).
if ($id === (int) Auth::id()) {
    header('Location: ../form-anggota.php?id=' . $id . '&error=hapus_diri');
    exit;
}

$anggota = TeamModel::getById($id);

if (!$anggota) {
    header('Location: ../tim.php?error=tidak_ditemukan');
    exit;
}

if ($konfirmasi !== 'CONFIRM') {
    header('Location: ../form-anggota.php?id=' . $id . '&error=konfirmasi');
    exit;
}

TeamModel::hapus($id);
catat_aktivitas((int) Auth::id(), 'hapus_anggota', 'Akun tim ' . $anggota['nama'] . ' (' . $anggota['role'] . ') dihapus');

header('Location: ../tim.php?sukses=hapus');
exit;
