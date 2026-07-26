<?php

/**
 * Lokasi penyimpanan berkas privat (identitas pelanggan, bukti bayar, backup
 * database), WAJIB berada di dalam folder project (C:\xampp\htdocs\morms),
 * bukan di luar. Proteksi terhadap akses langsung lewat URL browser bukan
 * dari lokasinya, tapi dari storage/.htaccess ("Require all denied") yang
 * memblokir semua request HTTP ke folder ini apapun nama/tipe filenya.
 *
 * Resolve ke C:\xampp\htdocs\morms\storage\ — tetap di dalam project.
 */
function storage_path(string $relatif = ''): string
{
    $basis = dirname(__DIR__, 2) . '/storage';
    $basis = str_replace('\\', '/', $basis);
    $relatif = ltrim(str_replace('\\', '/', $relatif), '/');

    return $relatif !== '' ? $basis . '/' . $relatif : $basis;
}

// Folder khusus arsip ZIP backup bukti pembayaran - terpisah dari
// storage/identitas/ (tempat file bukti pembayaran ASLI disimpan) supaya
// tidak tercampur. Diproteksi lewat storage/backup-bukti-pembayaran/.htaccess
// ("Require all denied"), sama seperti folder identitas/.
function backup_bukti_path(string $relatif = ''): string
{
    return storage_path('backup-bukti-pembayaran/' . ltrim($relatif, '/'));
}
