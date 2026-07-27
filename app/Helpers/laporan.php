<?php

function label_status_laporan(string $status): string
{
    $label = [
        'DRAFT'                  => 'Draf',
        'MENUNGGU_PEMBAYARAN'    => 'Menunggu Bayar',
        'MENUNGGU_VERIFIKASI'    => 'Menunggu Verifikasi',
        'MENUNGGU_KEDATANGAN'    => 'Menunggu Kedatangan',
        'RESERVASI_DIKONFIRMASI' => 'Dikonfirmasi',
        'BARANG_DISIAPKAN'       => 'Disiapkan',
        'SIAP_DIAMBIL'           => 'Siap Diambil',
        'SEDANG_DISEWA'          => 'Sedang Disewa',
        'PENGEMBALIAN'           => 'Pengembalian',
        'SELESAI'                => 'Selesai',
        'EXPIRED'                => 'Kedaluwarsa',
        'DIBATALKAN'             => 'Dibatalkan',
    ];
    return $label[$status] ?? $status;
}

function export_csv(array $header, array $baris, string $namaFile): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $header);
    foreach ($baris as $brs) {
        fputcsv($output, $brs);
    }
    fclose($output);
    exit;
}