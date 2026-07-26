<?php

namespace App\Core;

class CartKasir
{
    /**
     * Tambah barang ke keranjang kasir. Kalau sudah ada baris dengan
     * kombinasi barang + ukuran + periode + jam + harga yang PERSIS sama,
     * jumlahnya digabung (jumlah += ...) alih-alih membuat baris baru -
     * supaya barang yang sama tidak muncul berulang sebagai baris terpisah.
     */
    public static function add(array $itemData): void
    {
        if (!isset($_SESSION['keranjang_kasir'])) {
            $_SESSION['keranjang_kasir'] = [];
        }

        foreach ($_SESSION['keranjang_kasir'] as $index => $baris) {
            if (self::kombinasiSama($baris, $itemData)) {
                $jumlahBaru = (int) $baris['jumlah'] + (int) $itemData['jumlah'];
                $_SESSION['keranjang_kasir'][$index]['jumlah'] = $jumlahBaru;
                $_SESSION['keranjang_kasir'][$index]['subtotal'] = self::hitungSubtotal($baris, $jumlahBaru);
                return;
            }
        }

        $_SESSION['keranjang_kasir'][] = $itemData;
    }

    // Dua baris dianggap "barang yang sama" kalau ID barang, ukuran, periode
    // sewa (tanggal ambil/kembali/jam), dan harga per malamnya identik. Kalau
    // nanti ada atribut varian lain (warna, dst.), tambahkan perbandingannya
    // di sini juga.
    private static function kombinasiSama(array $a, array $b): bool
    {
        return (int) $a['item_id'] === (int) $b['item_id']
            && (string) ($a['ukuran'] ?? '') === (string) ($b['ukuran'] ?? '')
            && $a['tanggal_ambil'] === $b['tanggal_ambil']
            && $a['tanggal_kembali'] === $b['tanggal_kembali']
            && (string) ($a['jam_ambil'] ?? '') === (string) ($b['jam_ambil'] ?? '')
            && (float) $a['harga_per_malam'] === (float) $b['harga_per_malam'];
    }

    private static function hitungSubtotal(array $baris, int $jumlah): float
    {
        return (float) $baris['harga_per_malam'] * (int) $baris['durasi'] * $jumlah;
    }

    public static function getAll(): array
    {
        return $_SESSION['keranjang_kasir'] ?? [];
    }

    public static function getByIndex(int $index): ?array
    {
        return $_SESSION['keranjang_kasir'][$index] ?? null;
    }

    /**
     * Ubah jumlah satu baris secara langsung (dipakai tombol +/-). Jumlah
     * baru <= 0 menghapus barisnya sepenuhnya dari keranjang.
     */
    public static function updateJumlah(int $index, int $jumlahBaru): void
    {
        if (!isset($_SESSION['keranjang_kasir'][$index])) {
            return;
        }

        if ($jumlahBaru <= 0) {
            self::remove($index);
            return;
        }

        $_SESSION['keranjang_kasir'][$index]['jumlah'] = $jumlahBaru;
        $_SESSION['keranjang_kasir'][$index]['subtotal'] = self::hitungSubtotal($_SESSION['keranjang_kasir'][$index], $jumlahBaru);
    }

    public static function remove(int $index): void
    {
        if (isset($_SESSION['keranjang_kasir'][$index])) {
            unset($_SESSION['keranjang_kasir'][$index]);
            $_SESSION['keranjang_kasir'] = array_values($_SESSION['keranjang_kasir']);
        }
    }

    // Ubah periode sewa satu baris keranjang (tanggal ambil/kembali/jam) tanpa
    // mengubah jumlah - dipakai fitur "Ubah Tanggal" di halaman Kasir.
    // Durasi & subtotal dihitung ulang mengikuti tanggal barunya.
    public static function updateTanggal(int $index, string $tanggalAmbil, string $tanggalKembali, string $jamAmbil): bool
    {
        if (!isset($_SESSION['keranjang_kasir'][$index])) {
            return false;
        }

        $barang = $_SESSION['keranjang_kasir'][$index];
        $durasi = max(1, (int) ((strtotime($tanggalKembali) - strtotime($tanggalAmbil)) / 86400));
        $barang['tanggal_ambil'] = $tanggalAmbil;
        $barang['tanggal_kembali'] = $tanggalKembali;
        $barang['jam_ambil'] = $jamAmbil;
        $barang['durasi'] = $durasi;
        $barang['subtotal'] = $barang['harga_per_malam'] * $durasi * $barang['jumlah'];
        $_SESSION['keranjang_kasir'][$index] = $barang;

        return true;
    }

    public static function getTotal(): float
    {
        $total = 0;
        foreach (self::getAll() as $barang) {
            $total += $barang['subtotal'];
        }
        return $total;
    }

    public static function clear(): void
    {
        $_SESSION['keranjang_kasir'] = [];
    }

    public static function getPeriode(): array
    {
        $semua = self::getAll();
        if (empty($semua)) {
            return ['ambil' => null, 'kembali' => null, 'jam' => null];
        }
        return [
            'ambil' => $semua[0]['tanggal_ambil'],
            'kembali' => $semua[0]['tanggal_kembali'],
            'jam' => $semua[0]['jam_ambil'] ?? '09:00',
        ];
    }

    // Setiap barang di keranjang kasir bisa punya periode sewa sendiri-sendiri.
    // "Amplop" (envelope) di sini adalah rentang terluar yang mencakup semua
    // barang - dipakai untuk mengisi bookings.tanggal_ambil/tanggal_kembali
    // (lihat BookingModel::pulihkanEnvelope()), BUKAN periode barang manapun
    // secara spesifik.
    public static function getEnvelope(): array
    {
        $semua = self::getAll();
        if (empty($semua)) {
            return ['ambil' => null, 'kembali' => null, 'jam' => null];
        }

        $ambilTerawal = $semua[0];
        $kembaliTerakhir = $semua[0];
        foreach ($semua as $barang) {
            $ambilTerawal = $barang['tanggal_ambil'] < $ambilTerawal['tanggal_ambil'] ? $barang : $ambilTerawal;
            $kembaliTerakhir = $barang['tanggal_kembali'] > $kembaliTerakhir['tanggal_kembali'] ? $barang : $kembaliTerakhir;
        }

        return [
            'ambil' => min(array_column($semua, 'tanggal_ambil')),
            'kembali' => max(array_column($semua, 'tanggal_kembali')),
            'jam' => $ambilTerawal['jam_ambil'] ?? '09:00',
            'jam_kembali' => $kembaliTerakhir['jam_ambil'] ?? '09:00',
        ];
    }
}
