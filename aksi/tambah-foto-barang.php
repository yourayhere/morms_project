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

$itemId = (int) ($_POST['item_id'] ?? 0);

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../form-barang.php?id=' . $itemId . '&error=token');
    exit;
}

$item = $itemId ? ItemModel::getByIdAdmin($itemId) : null;

if (!$item) {
    header('Location: ../inventaris.php?error=tidak_ditemukan');
    exit;
}

if (!isset($_FILES['foto']) || !is_array($_FILES['foto']['name']) || empty(array_filter($_FILES['foto']['name']))) {
    header('Location: ../form-barang.php?id=' . $itemId . '&error=foto_kosong');
    exit;
}

// Foto baru harus disambung SETELAH urutan foto yang sudah ada, bukan mulai
// dari 0 lagi — kalau tidak, foto baru akan tabrakan urutan dengan foto lama
// sehingga foto_utama yang tampil di katalog/inventaris tetap memakai foto
// lama walau foto baru sudah berhasil tersimpan (lihat juga simpan-barang.php).
$urutanAwal = count(ItemModel::getImages($itemId));
$jumlahBerhasil = 0;

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
        ItemModel::tambahGambar($itemId, $hasilUpload['path_relatif'], $urutanAwal + $jumlahBerhasil);
        $jumlahBerhasil++;
    }
}

if ($jumlahBerhasil > 0) {
    catat_aktivitas((int) Auth::id(), 'tambah_foto_barang', $jumlahBerhasil . ' foto ditambahkan ke barang ' . $item['nama']);
    header('Location: ../form-barang.php?id=' . $itemId . '&sukses=foto');
} else {
    header('Location: ../form-barang.php?id=' . $itemId . '&error=foto_gagal');
}
exit;
