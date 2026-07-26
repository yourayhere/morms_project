<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Helpers/paths.php';
require_once __DIR__ . '/../app/Models/BackupBuktiPembayaranModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BackupBuktiPembayaranModel;

Session::start();
Auth::requireRole(['owner']);

// Nama file TIDAK dipakai langsung untuk membangun path - dicocokkan dulu ke
// baris yang benar-benar tercatat di backup_bukti_pembayaran, supaya request
// dengan nama file rekayasa (mis. "../../config/database.php") tidak pernah
// bisa dipakai untuk membaca berkas di luar folder backup.
$namaFile = clean_input($_GET['nama_file'] ?? '');
$riwayat = $namaFile !== '' ? BackupBuktiPembayaranModel::getByNamaFile($namaFile) : null;

if (!$riwayat) {
    header('HTTP/1.1 404 Not Found');
    die('Berkas backup tidak ditemukan.');
}

$path = backup_bukti_path($riwayat['nama_file']);

if (!file_exists($path)) {
    header('HTTP/1.1 404 Not Found');
    die('Berkas backup tidak ditemukan di penyimpanan.');
}

catat_aktivitas((int) Auth::id(), 'unduh_backup_bukti_pembayaran', 'Mengunduh backup: ' . $riwayat['nama_file']);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $riwayat['nama_file'] . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
