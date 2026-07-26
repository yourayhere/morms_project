<?php

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Helpers/logger.php';

use App\Core\Session;
use App\Core\Auth;

Session::start();

$role = Auth::role();
$userId = Auth::id();

if ($userId !== null) {
    catat_aktivitas($userId, 'logout', 'Logout dari sistem');
}

Auth::logout();

if (in_array($role, ['owner', 'admin', 'kasir'], true)) {
    header('Location: workspace-merimba.php');
} else {
    header('Location: login.php');
}
exit;