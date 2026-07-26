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

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pengaturan.php?backup_error=token');
    exit;
}

try {
    $hasil = BackupBuktiPembayaranModel::buatBackup((int) Auth::id());

    catat_aktivitas(
        (int) Auth::id(),
        'backup_bukti_pembayaran',
        'Backup bukti pembayaran dibuat: ' . $hasil['nama_file'] . ' (' . $hasil['jumlah_file'] . ' berkas)'
    );

    header('Location: ../pengaturan.php?backup_sukses=1#modal-backup-bukti');
    exit;
} catch (\RuntimeException $e) {
    header('Location: ../pengaturan.php?backup_error=' . rawurlencode($e->getMessage()) . '#modal-backup-bukti');
    exit;
}
