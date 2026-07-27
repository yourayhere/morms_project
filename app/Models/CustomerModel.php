<?php

namespace App\Models;

use App\Core\Database;

class CustomerModel
{
    public static function getSemuaPelanggan(string $kataKunci = ''): array
    {
        $db = Database::getConnection();

        $sql = 'SELECT
                u.id AS user_id, u.nama AS nama_member, u.no_hp AS hp_member, u.status_aktif AS status_blacklist, u.created_at,
                (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) AS jumlah_transaksi,
                "member" AS tipe
            FROM users u WHERE u.role = "member"';

        $params = [];
        if ($kataKunci !== '') {
            $sql .= ' AND (u.nama LIKE :kata1 OR u.no_hp LIKE :kata2)';
            $params['kata1'] = '%' . $kataKunci . '%';
            $params['kata2'] = '%' . $kataKunci . '%';
        }

        $sql .= ' UNION

            SELECT
                NULL AS user_id, b.guest_nama AS nama_member, b.guest_hp AS hp_member, "aktif" AS status_blacklist, MIN(b.created_at) AS created_at,
                COUNT(*) AS jumlah_transaksi,
                "guest" AS tipe
            FROM bookings b WHERE b.user_id IS NULL AND b.guest_hp IS NOT NULL';

        if ($kataKunci !== '') {
            $sql .= ' AND (b.guest_nama LIKE :kata3 OR b.guest_hp LIKE :kata4)';
            $params['kata3'] = '%' . $kataKunci . '%';
            $params['kata4'] = '%' . $kataKunci . '%';
        }

        $sql .= ' GROUP BY b.guest_hp, b.guest_nama ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getCatatanInternal(string $hp): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT c.*, u.nama AS nama_admin FROM catatan_pelanggan c
             LEFT JOIN users u ON u.id = c.dibuat_oleh
             WHERE c.hp = :hp ORDER BY c.created_at DESC'
        );
        $stmt->execute(['hp' => $hp]);
        return $stmt->fetchAll();
    }

    public static function getCatatanById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM catatan_pelanggan WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function tambahCatatan(string $hp, string $isi, int $adminId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('INSERT INTO catatan_pelanggan (hp, isi, dibuat_oleh) VALUES (:hp, :isi, :admin_id)');
        $stmt->execute(['hp' => $hp, 'isi' => $isi, 'admin_id' => $adminId]);
    }

    public static function updateCatatan(int $id, string $isi): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE catatan_pelanggan SET isi = :isi WHERE id = :id');
        $stmt->execute(['isi' => $isi, 'id' => $id]);
    }

    public static function hapusCatatan(int $id): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM catatan_pelanggan WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function setBlacklist(int $userId, bool $blacklist): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE users SET status_aktif = :status WHERE id = :id');
        $stmt->execute(['status' => $blacklist ? 'nonaktif' : 'aktif', 'id' => $userId]);
    }

    public static function getMemberById(int $userId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, nama, email, no_hp, status_aktif, created_at FROM users WHERE id = :id AND role = "member"');
        $stmt->execute(['id' => $userId]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function updateMember(int $userId, string $nama, string $noHp, string $email): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE users SET nama = :nama, no_hp = :no_hp, email = :email WHERE id = :id AND role = "member"');
        $stmt->execute([
            'nama'  => $nama,
            'no_hp' => $noHp,
            'email' => $email !== '' ? $email : null,
            'id'    => $userId,
        ]);
    }

    public static function cekHpDuplikatMember(string $noHp, int $kecualiId = 0): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE no_hp = :no_hp AND id != :id');
        $stmt->execute(['no_hp' => $noHp, 'id' => $kecualiId]);
        return $stmt->fetch() !== false;
    }

    public static function cekEmailDuplikatMember(string $email, int $kecualiId = 0): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $stmt->execute(['email' => $email, 'id' => $kecualiId]);
        return $stmt->fetch() !== false;
    }

    public static function getJumlahBooking(int $userId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    // Hapus akun member secara permanen. Riwayat booking-nya TIDAK ikut
    // dihapus (data transaksi/laporan lama harus tetap utuh) — sebelum akun
    // dihapus, nama/HP/email member disalin dulu ke kolom guest_* pada
    // booking-nya, supaya begitu users.user_id di-NULL-kan otomatis oleh FK
    // (ON DELETE SET NULL), riwayatnya tidak berubah jadi anonim tanpa nama.
    public static function hapusMember(int $userId): void
    {
        $db = Database::getConnection();

        $stmtSalin = $db->prepare(
            'UPDATE bookings b
             JOIN users u ON u.id = b.user_id
             SET b.guest_nama = u.nama, b.guest_hp = u.no_hp, b.guest_email = u.email
             WHERE b.user_id = :id'
        );
        $stmtSalin->execute(['id' => $userId]);

        $stmtHapus = $db->prepare('DELETE FROM users WHERE id = :id AND role = "member"');
        $stmtHapus->execute(['id' => $userId]);
    }

    // Tamu tidak punya baris di `users`, jadi "hapus" di sini berarti
    // menganonimkan identitasnya di semua booking-nya (bukan menghapus
    // booking-nya) - riwayat transaksi & laporan tetap utuh demi keutuhan
    // data keuangan, sama seperti prinsip hapusMember(). Begitu guest_hp
    // di-NULL-kan, baris ini otomatis tidak lagi muncul di getSemuaPelanggan()
    // (bucket tamu mensyaratkan guest_hp IS NOT NULL).
    public static function hapusTamu(string $hp): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE bookings
             SET guest_nama = "Tamu Dihapus", guest_hp = NULL, guest_email = NULL, guest_alamat = NULL
             WHERE user_id IS NULL AND guest_hp = :hp'
        );
        $stmt->execute(['hp' => $hp]);
        return $stmt->rowCount();
    }

    public static function getStatusAkun(int $userId): ?string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT status_aktif FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $hasil = $stmt->fetchColumn();
        return $hasil !== false ? $hasil : null;
    }

    public static function getRiwayatTransaksiByHp(string $hp): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT b.* FROM bookings b
             LEFT JOIN users u ON u.id = b.user_id
             WHERE b.guest_hp = :hp OR u.no_hp = :hp2
             ORDER BY b.created_at DESC'
        );
        $stmt->execute(['hp' => $hp, 'hp2' => $hp]);
        return $stmt->fetchAll();
    }
}