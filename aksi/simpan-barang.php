<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/upload.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin']);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../inventaris.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$kategori = clean_input($_POST['kategori'] ?? '');
$isPakaian = $kategori === 'pakaian_outdoor';

// Kategori Pakaian Outdoor pakai sistem varian ukuran+stok (bukan satu
// stok_total manual). Baris ukuran kosong diabaikan; stok negatif dianggap 0.
$daftarVariasi = [];
$variasiUkuranPost = $_POST['variasi_ukuran'] ?? [];
$variasiStokPost = $_POST['variasi_stok'] ?? [];
if ($isPakaian && is_array($variasiUkuranPost)) {
    foreach ($variasiUkuranPost as $idx => $ukuranMentah) {
        $ukuranBersih = trim(clean_input($ukuranMentah));
        if ($ukuranBersih === '') {
            continue;
        }
        $daftarVariasi[] = [
            'ukuran' => $ukuranBersih,
            'stok' => max(0, (int) ($variasiStokPost[$idx] ?? 0)),
        ];
    }
}
$totalStokVariasi = array_sum(array_column($daftarVariasi, 'stok'));

$data = [
    'nama' => clean_input($_POST['nama'] ?? ''),
    'kategori' => $kategori,
    'sub_kategori' => clean_input($_POST['sub_kategori'] ?? ''),
    'harga_per_malam' => (float) ($_POST['harga_per_malam'] ?? 0),
    'stok_total' => $isPakaian ? $totalStokVariasi : (int) ($_POST['stok_total'] ?? 0),
    'deskripsi' => clean_input($_POST['deskripsi'] ?? ''),
    'syarat_penyewaan' => clean_input($_POST['syarat_penyewaan'] ?? ''),
    'status' => in_array($_POST['status'] ?? '', ['maintenance', 'nonaktif'], true) ? $_POST['status'] : 'aktif',
];

// Sub kategori hanya berlaku untuk kategori "Pakaian Outdoor". Kategori lain
// tidak boleh menyimpan sisa data pakaian dari form.
if (!$isPakaian) {
    $data['sub_kategori'] = '';
}

if ($data['nama'] === '' || $data['kategori'] === '') {
    header('Location: ../form-barang.php' . ($id ? '?id=' . $id : '') . '&error=data');
    exit;
}

if ($isPakaian && ($data['sub_kategori'] === '' || empty($daftarVariasi))) {
    header('Location: ../form-barang.php' . ($id ? '?id=' . $id : '') . '&error=pakaian');
    exit;
}

if ($id > 0) {
    ItemModel::update($id, $data);
    $itemId = $id;
    catat_aktivitas((int) Auth::id(), 'edit_barang', 'Barang ' . $data['nama'] . ' diperbarui');
} else {
    $itemId = ItemModel::buat($data);
    catat_aktivitas((int) Auth::id(), 'tambah_barang', 'Barang baru ditambahkan: ' . $data['nama']);
}

if ($isPakaian) {
    ItemModel::simpanVariasi($itemId, $daftarVariasi);
}

if (isset($_FILES['foto']) && is_array($_FILES['foto']['name'])) {
    // Foto baru harus disambung SETELAH urutan foto yang sudah ada, bukan
    // mulai dari 0 lagi — kalau tidak, foto baru akan tabrakan urutan dengan
    // foto lama (sama-sama urutan 0), sehingga foto_utama yang tampil di
    // katalog/inventaris tetap memakai foto lama walau foto baru sudah
    // berhasil tersimpan di database & disk.
    $urutanAwal = count(ItemModel::getImages($itemId));
    $jumlahTersimpan = 0;

    foreach ($_FILES['foto']['name'] as $index => $namaAsli) {
        if ($_FILES['foto']['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        $fileTunggal = [
            'name' => $_FILES['foto']['name'][$index],
            'type' => $_FILES['foto']['type'][$index],
            'tmp_name' => $_FILES['foto']['tmp_name'][$index],
            'error' => $_FILES['foto']['error'][$index],
            'size' => $_FILES['foto']['size'][$index],
        ];

        $hasilUpload = validasi_dan_simpan_foto_produk($fileTunggal);
        if ($hasilUpload['sukses']) {
            ItemModel::tambahGambar($itemId, $hasilUpload['path_relatif'], $urutanAwal + $jumlahTersimpan);
            $jumlahTersimpan++;
        }
    }
}

header('Location: ../inventaris.php');
exit;
