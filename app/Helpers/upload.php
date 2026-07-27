<?php

require_once __DIR__ . '/paths.php';

function validasi_dan_simpan_identitas(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['sukses' => false, 'pesan' => 'Berkas identitas wajib diunggah.'];
    }

    $ukuranMaksimal = 5 * 1024 * 1024;
    if ($file['size'] > $ukuranMaksimal) {
        return ['sukses' => false, 'pesan' => 'Ukuran berkas maksimal 5MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $mimeDiizinkan = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $mimeDiizinkan, true)) {
        return ['sukses' => false, 'pesan' => 'Format berkas harus berupa gambar (JPG, PNG, atau WEBP).'];
    }

    $ukuranGambar = @getimagesize($file['tmp_name']);
    if ($ukuranGambar === false) {
        return ['sukses' => false, 'pesan' => 'Berkas yang diunggah bukan gambar yang valid.'];
    }

    $ekstensi = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/webp' ? 'webp' : 'jpg');
    $namaFile = bin2hex(random_bytes(16)) . '.' . $ekstensi;
    $tujuanFolder = storage_path('identitas/');
    $tujuanLengkap = $tujuanFolder . $namaFile;

    if (!is_dir($tujuanFolder)) {
        @mkdir($tujuanFolder, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $tujuanLengkap)) {
        return ['sukses' => false, 'pesan' => 'Gagal menyimpan berkas. Silakan coba lagi.'];
    }

    return ['sukses' => true, 'nama_file' => $namaFile];
}

// Helper bersama untuk semua upload gambar publik (foto produk, QRIS, logo).
// Setiap jenis gambar punya folder tujuannya sendiri di bawah assets/images/
// supaya tidak tercampur satu sama lain (mis. logo/QRIS ikut masuk folder
// foto produk, yang membuat folder aslinya kosong padahal tetap terisi).
function simpan_gambar_ke_folder(array $file, string $folderRelatif): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['sukses' => false, 'pesan' => 'Tidak ada berkas yang diunggah.'];
    }

    $ukuranMaksimal = 3 * 1024 * 1024;
    if ($file['size'] > $ukuranMaksimal) {
        return ['sukses' => false, 'pesan' => 'Ukuran foto maksimal 3MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $mimeDiizinkan = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $mimeDiizinkan, true)) {
        return ['sukses' => false, 'pesan' => 'Format foto harus JPG, PNG, atau WEBP.'];
    }

    $ukuranGambar = @getimagesize($file['tmp_name']);
    if ($ukuranGambar === false) {
        return ['sukses' => false, 'pesan' => 'Berkas bukan gambar yang valid.'];
    }

    $ekstensi = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/webp' ? 'webp' : 'jpg');
    $namaFile = bin2hex(random_bytes(16)) . '.' . $ekstensi;
    $folderRelatif = trim($folderRelatif, '/') . '/';
    $tujuanFolder = __DIR__ . '/../../' . $folderRelatif;

    if (!is_dir($tujuanFolder)) {
        mkdir($tujuanFolder, 0755, true);
    }

    $tujuanLengkap = $tujuanFolder . $namaFile;

    if (!move_uploaded_file($file['tmp_name'], $tujuanLengkap)) {
        return ['sukses' => false, 'pesan' => 'Gagal menyimpan foto.'];
    }

    return ['sukses' => true, 'path_relatif' => $folderRelatif . $namaFile];
}

function validasi_dan_simpan_foto_produk(array $file): array
{
    return simpan_gambar_ke_folder($file, 'assets/images/produk');
}

function validasi_dan_simpan_qris(array $file): array
{
    return simpan_gambar_ke_folder($file, 'assets/images/qris');
}

function validasi_dan_simpan_logo(array $file): array
{
    return simpan_gambar_ke_folder($file, 'assets/images/logo');
}
