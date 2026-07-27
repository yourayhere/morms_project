<?php

namespace App\Models;

use App\Core\Database;

class ReturnModel
{
    // Satu baris `returns` = satu barang (booking_item) yang dikembalikan
    // dalam satu event pengembalian. booking_id tetap disertakan untuk
    // kemudahan query, meski selalu bisa ditelusuri lewat booking_item_id.
    public static function buat(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO returns (booking_id, booking_item_id, kondisi, keterangan, denda_terlambat, biaya_kerusakan, tanggal_kembali_aktual, diproses_oleh)
             VALUES (:booking_id, :booking_item_id, :kondisi, :keterangan, :denda, :rusak, NOW(), :admin_id)'
        );
        $stmt->execute([
            'booking_id' => $data['booking_id'],
            'booking_item_id' => $data['booking_item_id'],
            'kondisi' => $data['kondisi'],
            'keterangan' => $data['keterangan'] ?: null,
            'denda' => $data['denda_terlambat'],
            'rusak' => $data['biaya_kerusakan'],
            'admin_id' => $data['admin_id'],
        ]);
        return (int) $db->lastInsertId();
    }

    // Dipertahankan untuk baris riwayat lama (sebelum pengembalian per-barang)
    // yang cuma punya satu baris `returns` mewakili seluruh booking.
    public static function getByBookingId(int $bookingId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM returns WHERE booking_id = :id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['id' => $bookingId]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function getSemuaByBookingId(int $bookingId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM returns WHERE booking_id = :id ORDER BY created_at ASC');
        $stmt->execute(['id' => $bookingId]);
        return $stmt->fetchAll();
    }

    // Ambil sekumpulan baris returns berdasarkan ID-nya - dipakai struk
    // pengembalian supaya struk mencerminkan PERSIS barang yang baru saja
    // dikembalikan dalam satu event, bukan cuma baris terakhir di booking itu.
    public static function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $db = Database::getConnection();
        $placeholder = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT r.*, i.nama AS nama_barang, bi.ukuran_dipilih
             FROM returns r
             LEFT JOIN booking_items bi ON bi.id = r.booking_item_id
             LEFT JOIN items i ON i.id = bi.item_id
             WHERE r.id IN ($placeholder)
             ORDER BY r.id ASC"
        );
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }
}
