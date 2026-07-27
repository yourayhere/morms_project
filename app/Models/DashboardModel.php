<?php

namespace App\Models;

use App\Core\Database;

class DashboardModel
{
    public static function getPendapatanHariIni(): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(nominal), 0) FROM transactions
             WHERE DATE(created_at) = CURDATE() AND status_verifikasi != "ditolak"'
        );
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    public static function getJumlahReservasiBaru(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM bookings WHERE status IN ("MENUNGGU_VERIFIKASI", "MENUNGGU_KEDATANGAN") '
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function getJumlahPenyewaanAktif(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM bookings WHERE status IN ("RESERVASI_DIKONFIRMASI", "BARANG_DISIAPKAN", "SIAP_DIAMBIL", "SEDANG_DISEWA")'
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // Dihitung per-barang (booking_items), bukan per-booking - satu booking
    // bisa berisi barang dengan tanggal kembali berbeda-beda, jadi "berapa
    // barang yang jatuh tempo hari ini" lebih akurat daripada "berapa booking".
    public static function getJumlahPengembalianHariIni(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             WHERE bi.tanggal_kembali = CURDATE() AND bi.status = "disewa" AND b.status = "SEDANG_DISEWA"'
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // Filter tanggal & status di sini per-barang (bi.*) - lihat catatan yang
    // sama di ItemModel::getStokTerpakai().
    public static function getBarangHampirHabis(int $batas = 3): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT i.id, i.nama, i.stok_total,
                i.stok_total - COALESCE((
                    SELECT SUM(bi.jumlah) FROM booking_items bi
                    JOIN bookings b ON b.id = bi.booking_id
                    WHERE bi.item_id = i.id
                    AND bi.status = "disewa"
                    AND b.status NOT IN ("DIBATALKAN", "EXPIRED", "SELESAI")
                    AND CURDATE() >= bi.tanggal_ambil AND CURDATE() < bi.tanggal_kembali
                ), 0) AS sisa_stok
             FROM items i
             WHERE i.status = "aktif"
             HAVING sisa_stok <= :batas
             ORDER BY sisa_stok ASC'
        );
        $stmt->execute(['batas' => $batas]);
        return $stmt->fetchAll();
    }

    // Sekarang per-barang, bukan per-booking - satu booking dengan 3 barang
    // di mana cuma 1 barang telat tidak lagi salah ditandai "booking-nya
    // telat" secara keseluruhan; ditampilkan barang mana persisnya yang telat.
    public static function getBarangTerlambat(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT b.id, b.kode_booking, b.guest_nama, u.nama AS nama_member,
                i.nama AS nama_barang, bi.tanggal_kembali,
                DATEDIFF(CURDATE(), bi.tanggal_kembali) AS hari_terlambat
             FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             JOIN items i ON i.id = bi.item_id
             LEFT JOIN users u ON u.id = b.user_id
             WHERE b.status = "SEDANG_DISEWA" AND bi.status = "disewa" AND bi.tanggal_kembali < CURDATE()
             ORDER BY bi.tanggal_kembali ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getGrafikPendapatan7Hari(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT DATE(created_at) AS tanggal, SUM(nominal) AS total
             FROM transactions
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status_verifikasi != "ditolak"
             GROUP BY DATE(created_at)
             ORDER BY tanggal ASC'
        );
        $stmt->execute();
        $hasilMentah = $stmt->fetchAll();

        $hasil = [];
        for ($i = 6; $i >= 0; $i--) {
            $tanggal = date('Y-m-d', strtotime('-' . $i . ' day'));
            $hasil[$tanggal] = 0;
        }
        foreach ($hasilMentah as $baris) {
            $hasil[$baris['tanggal']] = (float) $baris['total'];
        }
        return $hasil;
    }

    public static function getReservasiTerbaru(int $limit = 5): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT b.id, b.kode_booking, b.guest_nama, u.nama AS nama_member, b.status, b.created_at
             FROM bookings b
             LEFT JOIN users u ON u.id = b.user_id
             ORDER BY b.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}