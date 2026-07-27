<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/CustomerModel.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Auth;
use App\Models\CustomerModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pelanggan.php?error=token&form=tambah');
    exit;
}

$nama = clean_input($_POST['nama'] ?? '');
$noHp = normalisasi_no_hp(clean_input($_POST['no_hp'] ?? ''));
$alamat = clean_input($_POST['alamat'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$konfirmasi = $_POST['konfirmasi_password'] ?? '';

// Validasi yang sama seperti pendaftaran member lewat web (register.php),
// karena ini pada dasarnya mendaftarkan member baru atas nama admin/owner.
$errors = [];
if ($nama === '' || $noHp === '' || $alamat === '' || $password === '') {
    $errors[] = 'data';
} elseif (strlen($password) < 8) {
    $errors[] = 'password_pendek';
} elseif ($password !== $konfirmasi) {
    $errors[] = 'konfirmasi_password';
} elseif (!validasi_no_hp($noHp)) {
    $errors[] = 'format_hp';
} elseif ($email !== '' && !validasi_email($email)) {
    $errors[] = 'format_email';
} elseif (CustomerModel::cekHpDuplikatMember($noHp)) {
    $errors[] = 'hp_duplikat';
} elseif ($email !== '' && CustomerModel::cekEmailDuplikatMember($email)) {
    $errors[] = 'email_duplikat';
}

if (!empty($errors)) {
    header('Location: ../pelanggan.php?error=' . $errors[0] . '&form=tambah');
    exit;
}

$db = Database::getConnection();
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $db->prepare(
    'INSERT INTO users (nama, email, no_hp, alamat, password_hash, role, status_aktif)
     VALUES (:nama, :email, :no_hp, :alamat, :hash, "member", "aktif")'
);
$stmt->execute([
    'nama'   => $nama,
    'email'  => $email !== '' ? $email : null,
    'no_hp'  => $noHp,
    'alamat' => $alamat,
    'hash'   => $hash,
]);

$newUserId = (int) $db->lastInsertId();

catat_aktivitas((int) Auth::id(), 'tambah_member_admin', 'Member baru ditambahkan lewat panel admin: ' . $nama);

header('Location: ../pelanggan.php?sukses=tambah');
exit;
