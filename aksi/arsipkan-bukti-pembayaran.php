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
    header('Location: ../pengaturan.php?arsip_error=token');
    exit;
}

// Konfirmasi checkbox "saya sudah unduh & pastikan backup aman" WAJIB
// tercentang di form - ditegakkan ulang di server, tidak cukup hanya
// tombolnya dikunci lewat JS di klien.
if (($_POST['konfirmasi_unduh'] ?? '') !== '1') {
    header('Location: ../pengaturan.php?arsip_error=konfirmasi#modal-backup-bukti');
    exit;
}

try {
    $hasil = BackupBuktiPembayaranModel::arsipkanBackupTerakhir();

    catat_aktivitas(
        (int) Auth::id(),
        'arsipkan_bukti_pembayaran',
        'Bukti pembayaran diarsipkan (dihapus): ' . $hasil['jumlah_dihapus'] . ' berkas, backup #' . $hasil['backup_id']
    );

    header('Location: ../pengaturan.php?arsip_sukses=' . $hasil['jumlah_dihapus'] . '#modal-backup-bukti');
    exit;
} catch (\RuntimeException $e) {
    header('Location: ../pengaturan.php?arsip_error=' . rawurlencode($e->getMessage()) . '#modal-backup-bukti');
    exit;
}
