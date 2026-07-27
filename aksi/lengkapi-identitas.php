<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/upload.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$redirectBack = '../detail-penyewaan.php?id=' . $bookingId;

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $redirectBack . '&error=token');
    exit;
}

$booking = BookingModel::getById($bookingId);
if (!$booking) {
    header('Location: ../penyewaan-aktif.php');
    exit;
}

if (!isset($_FILES['identitas']) || $_FILES['identitas']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirectBack . '&error=identitas');
    exit;
}

$hasilUpload = validasi_dan_simpan_identitas($_FILES['identitas']);
if (!$hasilUpload['sukses']) {
    header('Location: ' . $redirectBack . '&error=identitas');
    exit;
}

BookingModel::simpanIdentitas($bookingId, $hasilUpload['nama_file']);
catat_aktivitas((int) Auth::id(), 'lengkapi_identitas', 'Identitas booking ' . $booking['kode_booking'] . ' dilengkapi');

header('Location: ' . $redirectBack . '&sukses=1');
exit;
