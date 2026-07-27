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

$hp = clean_input($_POST['hp'] ?? '');
$namaTamu = clean_input($_POST['nama'] ?? '');

if ($hp === '') {
    header('Location: ../pelanggan.php?error=tidak_ditemukan');
    exit;
}

$jumlahTerpengaruh = CustomerModel::hapusTamu($hp);

// Dicatat terlepas dari hasilnya (termasuk kalau 0 baris terpengaruh) demi
// jejak audit - supaya percobaan hapus data tamu oleh admin/owner mana pun
// selalu tercatat, sesuai permintaan keamanan terhadap potensi penyalahgunaan.
catat_aktivitas(
    (int) Auth::id(),
    'hapus_tamu',
    'Data tamu ' . ($namaTamu !== '' ? $namaTamu . ' ' : '') . '(' . $hp . ') dihapus/dianonimkan dari ' . $jumlahTerpengaruh . ' booking'
);

header('Location: ../pelanggan.php?sukses=hapus_tamu');
exit;
