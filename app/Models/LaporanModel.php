<?php

namespace App\Models;

use App\Core\Database;

class LaporanModel
{
    public static function getPendapatan(string $dari, string $sampai): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT DATE(t.created_at) AS tanggal,
                SUM(t.nominal) AS total,
                COUNT(t.id) AS jumlah_transaksi
             FROM transactions t
             WHERE t.status_verifikasi = "terverifikasi"
             AND DATE(t.created_at) BETWEEN :dari AND :sampai
             GROUP BY DATE(t.created_at)
             ORDER BY tanggal ASC'
        );
        $stmt->execute(['dari' => $dari, 'sampai' => $sampai]);
        return $stmt->fetchAll();
    }

    public static function getTotalPendapatan(string $dari, string $sampai): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(nominal), 0) FROM transactions
             WHERE status_verifikasi = "terverifikasi"
             AND DATE(created_at) BETWEEN :dari AND :sampai'
        );
        $stmt->execute(['dari' => $dari, 'sampai' => $sampai]);
        return (float) $stmt->fetchColumn();
    }

    public static function getReservasi(string $dari, string $sampai, string $status = ''): array
    {
        $db = Database::getConnection();
        $sql = 'SELECT b.kode_booking, b.guest_nama, u.nama AS nama_member,
                    b.tanggal_ambil, b.tanggal_kembali, b.status, b.created_at,
                    (SELECT COALESCE(SUM(bi.subtotal), 0) FROM booking_items bi WHERE bi.booking_id = b.id) AS total_sewa
                FROM bookings b
                LEFT JOIN users u ON u.id = b.user_id
                WHERE DATE(b.created_at) BETWEEN :dari AND :sampai';
        $params = ['dari' => $dari, 'sampai' => $sampai];

        if ($status !== '') {
            $sql .= ' AND b.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY b.created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Barang Pakaian Outdoor dikelompokkan per ukuran (bukan hanya per barang)
    // supaya peringkat mencerminkan ukuran mana yang paling laris — penting
    // sejak stok dipecah per ukuran lewat sistem varian. Barang non-pakaian
    // tetap satu baris seperti biasa karena ukuran_dipilih-nya selalu NULL.
    public static function getBarangTerlaris(string $dari, string $sampai, int $limit = 10): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT i.nama, i.kategori, bi.ukuran_dipilih AS ukuran,
                SUM(bi.jumlah) AS total_unit,
                SUM(bi.subtotal) AS total_pendapatan,
                COUNT(DISTINCT bi.booking_id) AS jumlah_booking
             FROM booking_items bi
             JOIN items i ON i.id = bi.item_id
             JOIN bookings b ON b.id = bi.booking_id
             WHERE b.status NOT IN ("DIBATALKAN", "EXPIRED")
             AND DATE(b.created_at) BETWEEN :dari AND :sampai
             GROUP BY i.id, i.nama, i.kategori, bi.ukuran_dipilih
             ORDER BY total_unit DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':dari', $dari);
        $stmt->bindValue(':sampai', $sampai);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // r.booking_item_id ada sejak fitur tanggal per-barang - kalau terisi,
    // pakai tanggal_kembali milik barang itu sendiri (lebih akurat untuk hitung
    // keterlambatan); baris returns lama (sebelum fitur ini) booking_item_id-nya
    // NULL, jadi jatuh balik ke tanggal_kembali booking (amplop).
    public static function getKeterlambatan(string $dari, string $sampai): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT b.kode_booking, b.guest_nama, u.nama AS nama_member,
                COALESCE(bi.tanggal_kembali, b.tanggal_kembali) AS tanggal_kembali,
                r.tanggal_kembali_aktual,
                r.denda_terlambat, r.kondisi
             FROM returns r
             JOIN bookings b ON b.id = r.booking_id
             LEFT JOIN booking_items bi ON bi.id = r.booking_item_id
             LEFT JOIN users u ON u.id = b.user_id
             WHERE r.denda_terlambat > 0
             AND DATE(r.created_at) BETWEEN :dari AND :sampai
             ORDER BY r.denda_terlambat DESC'
        );
        $stmt->execute(['dari' => $dari, 'sampai' => $sampai]);
        return $stmt->fetchAll();
    }

    // Grain: satu baris per item dalam booking. Dipakai untuk export "Data
    // Lengkap" agar bisa langsung ditarik ke tools BI (Tableau dkk.) atau
    // dikirim ke pusat tanpa perlu olah data manual lagi.
    public static function getDataLengkap(string $dari, string $sampai): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT
                b.kode_booking,
                b.id AS booking_id,
                b.created_at AS booking_dibuat,
                CASE WHEN b.user_id IS NOT NULL THEN "Member" ELSE "Tamu" END AS tipe_pelanggan,
                COALESCE(u.nama, b.guest_nama) AS nama_pelanggan,
                COALESCE(u.no_hp, b.guest_hp) AS no_hp_pelanggan,
                COALESCE(u.email, b.guest_email) AS email_pelanggan,
                b.status AS status_booking,
                bi.tanggal_ambil,
                bi.tanggal_kembali,
                bi.status AS status_barang,
                DATEDIFF(bi.tanggal_kembali, bi.tanggal_ambil) AS durasi_malam,
                i.nama AS nama_barang,
                i.kategori,
                bi.ukuran_dipilih AS ukuran,
                bi.jumlah,
                bi.subtotal AS subtotal_item,
                (SELECT COALESCE(SUM(bi2.subtotal), 0) FROM booking_items bi2 WHERE bi2.booking_id = b.id) AS total_sewa_booking,
                (SELECT COALESCE(SUM(t.nominal), 0) FROM transactions t WHERE t.booking_id = b.id AND t.status_verifikasi = "terverifikasi") AS total_terverifikasi,
                (SELECT COALESCE(SUM(t.nominal), 0) FROM transactions t WHERE t.booking_id = b.id AND t.status_verifikasi = "menunggu") AS total_menunggu_verifikasi,
                (SELECT COALESCE(SUM(t.nominal), 0) FROM transactions t WHERE t.booking_id = b.id AND t.status_verifikasi = "ditolak") AS total_ditolak,
                (SELECT GROUP_CONCAT(DISTINCT t.metode) FROM transactions t WHERE t.booking_id = b.id) AS metode_pembayaran,
                r.kondisi AS kondisi_pengembalian,
                r.tanggal_kembali_aktual,
                r.denda_terlambat,
                r.biaya_kerusakan,
                r.keterangan AS catatan_pengembalian
            FROM booking_items bi
            JOIN bookings b ON b.id = bi.booking_id
            JOIN items i ON i.id = bi.item_id
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN returns r ON (r.booking_item_id = bi.id) OR (r.booking_item_id IS NULL AND r.booking_id = b.id)
            WHERE DATE(b.created_at) BETWEEN :dari AND :sampai
            ORDER BY b.created_at DESC, b.id, bi.id'
        );
        $stmt->execute(['dari' => $dari, 'sampai' => $sampai]);
        return $stmt->fetchAll();
    }

    public static function getRingkasan(string $dari, string $sampai): array
    {
        $db = Database::getConnection();

        $stmtPendapatan = $db->prepare(
            'SELECT COALESCE(SUM(nominal), 0) FROM transactions
             WHERE status_verifikasi = "terverifikasi"
             AND DATE(created_at) BETWEEN :dari AND :sampai'
        );
        $stmtPendapatan->execute(['dari' => $dari, 'sampai' => $sampai]);

        $stmtBooking = $db->prepare(
            'SELECT COUNT(*) FROM bookings WHERE DATE(created_at) BETWEEN :dari AND :sampai'
        );
        $stmtBooking->execute(['dari' => $dari, 'sampai' => $sampai]);

        $stmtSelesai = $db->prepare(
            'SELECT COUNT(*) FROM bookings WHERE status = "SELESAI" AND DATE(created_at) BETWEEN :dari AND :sampai'
        );
        $stmtSelesai->execute(['dari' => $dari, 'sampai' => $sampai]);

        $stmtDenda = $db->prepare(
            'SELECT COALESCE(SUM(denda_terlambat), 0) FROM returns r
             JOIN bookings b ON b.id = r.booking_id
             WHERE DATE(r.created_at) BETWEEN :dari AND :sampai'
        );
        $stmtDenda->execute(['dari' => $dari, 'sampai' => $sampai]);

        return [
            'total_pendapatan' => (float) $stmtPendapatan->fetchColumn(),
            'total_booking'    => (int) $stmtBooking->fetchColumn(),
            'booking_selesai'  => (int) $stmtSelesai->fetchColumn(),
            'total_denda'      => (float) $stmtDenda->fetchColumn(),
        ];
    }
}