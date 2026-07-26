<?php

require_once __DIR__ . '/../Models/SettingModel.php';

use App\Models\SettingModel;

// Status toko (buka/tutup) dipakai untuk menghentikan sementara SELURUH alur
// pemesanan baru dari sisi customer (tambah keranjang, checkout) tanpa perlu
// menonaktifkan barang satu-satu - dipakai owner saat tutup mendadak (urusan
// lain, libur, dsb.) supaya customer tidak terlanjur order di hari toko
// sebenarnya tidak beroperasi. Tidak memengaruhi transaksi Kasir (dijalankan
// staf secara langsung) maupun akses akun/tracking booking yang sudah ada.
function toko_sedang_tutup(): bool
{
    return SettingModel::get('status_toko') === 'tutup';
}

function pesan_toko_tutup(): string
{
    $pesan = trim(SettingModel::get('pesan_tutup'));
    if ($pesan !== '') {
        return $pesan;
    }

    return 'Mohon maaf, untuk sementara kami belum bisa menerima reservasi baru. Silakan coba kembali beberapa saat lagi, atau hubungi kami langsung untuk informasi lebih lanjut.';
}
