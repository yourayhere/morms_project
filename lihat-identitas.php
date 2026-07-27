<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/paths.php';
require_once __DIR__ . '/app/Models/BookingModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\BookingModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$bookingId = (int) ($_GET['booking_id'] ?? 0);
$booking = BookingModel::getById($bookingId);

if (!$booking || !$booking['identitas_file']) {
    die('Berkas tidak ditemukan.');
}

$path = storage_path('identitas/' . $booking['identitas_file']);

if (!file_exists($path)) {
    die('Berkas tidak ditemukan.');
}

$mime = mime_content_type($path);
$base64 = base64_encode(file_get_contents($path));
$judul = 'Identitas ' . $booking['kode_booking'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($judul) ?></title>
<link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { background: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
img { max-width: 100%; max-height: 100vh; object-fit: contain; }
.btn-tutup {
    position: fixed; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.15); color: #fff; border: none; font-size: 22px; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.btn-tutup:hover { background: rgba(255,255,255,0.3); }
</style>
</head>
<body>
<button class="btn-tutup" onclick="if (window.history.length > 1) { history.back(); } else { window.close(); }" title="Tutup" aria-label="Tutup">&times;</button>
<img src="data:<?= htmlspecialchars($mime) ?>;base64,<?= $base64 ?>" alt="<?= htmlspecialchars($judul) ?>">
</body>
</html>
