<?php

function get_urutan_timeline(): array
{
    return [
        'Reservasi Dibuat',
        'Pembayaran Diterima',
        'Barang Disiapkan',
        'Siap Diambil',
        'Sedang Disewa',
        'Pengembalian',
        'Selesai',
    ];
}

function get_step_aktif(string $status): int
{
    $pemetaan = [
        'DRAFT' => 0,
        'MENUNGGU_PEMBAYARAN' => 0,
        'MENUNGGU_VERIFIKASI' => 1,
        'MENUNGGU_KEDATANGAN' => 1,
        'RESERVASI_DIKONFIRMASI' => 2,
        'BARANG_DISIAPKAN' => 3,
        'SIAP_DIAMBIL' => 4,
        'SEDANG_DISEWA' => 5,
        'PENGEMBALIAN' => 6,
        'SELESAI' => 7,
    ];
    return $pemetaan[$status] ?? 0;
}

function status_dibatalkan(string $status): bool
{
    return in_array($status, ['EXPIRED', 'DIBATALKAN'], true);
}

function label_status_booking(string $status): string
{
    $label = [
        'DRAFT' => 'Draf Reservasi',
        'MENUNGGU_PEMBAYARAN' => 'Menunggu Pembayaran',
        'MENUNGGU_VERIFIKASI' => 'Menunggu Verifikasi',
        'MENUNGGU_KEDATANGAN' => 'Menunggu Kedatangan',
        'RESERVASI_DIKONFIRMASI' => 'Reservasi Dikonfirmasi',
        'BARANG_DISIAPKAN' => 'Barang Disiapkan',
        'SIAP_DIAMBIL' => 'Siap Diambil',
        'SEDANG_DISEWA' => 'Sedang Disewa',
        'PENGEMBALIAN' => 'Proses Pengembalian',
        'SELESAI' => 'Selesai',
        'EXPIRED' => 'Kedaluwarsa',
        'DIBATALKAN' => 'Dibatalkan',
    ];
    return $label[$status] ?? $status;
}