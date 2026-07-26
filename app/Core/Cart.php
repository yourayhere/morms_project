<?php

namespace App\Core;

class Cart
{
    public static function add(array $itemData): void
    {
        if (!isset($_SESSION['keranjang'])) {
            $_SESSION['keranjang'] = [];
        }
        $_SESSION['keranjang'][] = $itemData;
    }

    public static function getAll(): array
    {
        return $_SESSION['keranjang'] ?? [];
    }

    public static function remove(int $index): void
    {
        if (isset($_SESSION['keranjang'][$index])) {
            unset($_SESSION['keranjang'][$index]);
            $_SESSION['keranjang'] = array_values($_SESSION['keranjang']);
        }
    }

    public static function clear(): void
    {
        $_SESSION['keranjang'] = [];
    }

    public static function update(int $index, int $jumlah, int $stokTotal): bool
    {
        if (!isset($_SESSION['keranjang'][$index])) {
            return false;
        }

        $jumlah = max(1, min($jumlah, $stokTotal));
        $barang = $_SESSION['keranjang'][$index];
        $barang['jumlah'] = $jumlah;
        $barang['subtotal'] = $barang['harga_per_malam'] * $barang['durasi'] * $jumlah;
        $_SESSION['keranjang'][$index] = $barang;

        return true;
    }

    // Ubah periode sewa satu baris keranjang (tanggal ambil/kembali/jam) tanpa
    // mengubah jumlah - dipakai fitur "Ubah Tanggal" di halaman Keranjang.
    // Durasi & subtotal dihitung ulang mengikuti tanggal barunya.
    public static function updateTanggal(int $index, string $tanggalAmbil, string $tanggalKembali, string $jamAmbil): bool
    {
        if (!isset($_SESSION['keranjang'][$index])) {
            return false;
        }

        $barang = $_SESSION['keranjang'][$index];
        $durasi = max(1, (int) ((strtotime($tanggalKembali) - strtotime($tanggalAmbil)) / 86400));
        $barang['tanggal_ambil'] = $tanggalAmbil;
        $barang['tanggal_kembali'] = $tanggalKembali;
        $barang['jam_ambil'] = $jamAmbil;
        $barang['durasi'] = $durasi;
        $barang['subtotal'] = $barang['harga_per_malam'] * $durasi * $barang['jumlah'];
        $_SESSION['keranjang'][$index] = $barang;

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

    // Setiap barang di keranjang bisa punya periode sewa sendiri-sendiri.
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