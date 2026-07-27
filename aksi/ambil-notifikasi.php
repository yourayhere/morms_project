<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Models/NotificationModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\NotificationModel;

Session::start();
header('Content-Type: application/json');

if (!Auth::isLoggedIn() || !in_array(Auth::role(), ['owner', 'admin', 'kasir'], true)) {
    echo json_encode([]);
    exit;
}

$daftar = NotificationModel::getByUserId((int) Auth::id());
NotificationModel::tandaiSemuaDibaca((int) Auth::id());

$hasil = array_map(function ($n) {
    $selisihMenit = (time() - strtotime($n['created_at'])) / 60;
    if ($selisihMenit < 1) {
        $waktu = 'Baru saja';
    } elseif ($selisihMenit < 60) {
        $waktu = (int) $selisihMenit . ' menit lalu';
    } elseif ($selisihMenit < 1440) {
        $waktu = (int) ($selisihMenit / 60) . ' jam lalu';
    } else {
        $waktu = (int) ($selisihMenit / 1440) . ' hari lalu';
    }

    return [
        'pesan' => htmlspecialchars($n['pesan']),
        'link_tujuan' => $n['link_tujuan'] ? htmlspecialchars($n['link_tujuan']) : null,
        'dibaca' => $n['dibaca'],
        'waktu_relatif' => $waktu,
    ];
}, $daftar);

echo json_encode($hasil);