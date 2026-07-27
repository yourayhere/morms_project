<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';

use App\Core\Session;
use App\Models\BookingModel;

Session::start();

if (!verify_csrf_token($_POST['csrf_token'] ?? null) || empty($_POST['setuju'])) {
    header('Location: ../review.php');
    exit;
}

$bookingId = (int) Session::get('booking_id_proses');
if (!$bookingId) {
    header('Location: ../katalog.php');
    exit;
}

BookingModel::updateStatus($bookingId, 'MENUNGGU_PEMBAYARAN');

header('Location: ../pembayaran.php');
exit;