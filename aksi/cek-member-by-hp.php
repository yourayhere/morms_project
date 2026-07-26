<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Auth;

Session::start();
header('Content-Type: application/json');

// Data member (nama) yang dikembalikan di sini bersifat sensitif, jadi
// endpoint ini hanya boleh diakses oleh staf yang sedang login lewat panel
// admin/kasir — bukan endpoint publik seperti cek-stok.php.
if (!Auth::isLoggedIn() || !in_array(Auth::role(), ['owner', 'admin', 'kasir'], true)) {
    http_response_code(403);
    echo json_encode(['ditemukan' => false]);
    exit;
}

$hp = normalisasi_no_hp(clean_input($_GET['hp'] ?? ''));

if ($hp === '' || !validasi_no_hp($hp)) {
    echo json_encode(['ditemukan' => false]);
    exit;
}

$db = Database::getConnection();
$stmt = $db->prepare('SELECT id, nama, alamat FROM users WHERE no_hp = :hp AND role = "member"');
$stmt->execute(['hp' => $hp]);
$member = $stmt->fetch();

if (!$member) {
    echo json_encode(['ditemukan' => false]);
    exit;
}

echo json_encode([
    'ditemukan' => true,
    'user_id'   => (int) $member['id'],
    'nama'      => $member['nama'],
    'alamat'    => $member['alamat'] ?? '',
]);
