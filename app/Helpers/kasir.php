<?php

require_once __DIR__ . '/format.php';

// Render partial keranjang kasir jadi string HTML - dipakai endpoint AJAX
// (tambah/hapus/ubah jumlah) supaya balasannya bisa langsung disuntikkan ke
// DOM tanpa reload halaman, dengan markup yang identik dengan render awal.
function render_keranjang_kasir_html(array $keranjangKasir): string
{
    ob_start();
    require __DIR__ . '/../Views/partials/kasir-keranjang-list.php';
    return ob_get_clean();
}
