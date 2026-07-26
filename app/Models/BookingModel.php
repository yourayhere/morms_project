<?php

namespace App\Models;

use App\Core\Database;

class BookingModel
{
    public static function buatBookingGuest(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO bookings (kode_booking, guest_nama, guest_hp, guest_alamat, guest_email, identitas_file, tanggal_ambil, tanggal_kembali, jam_ambil, catatan, status)
             VALUES (:kode_booking, :nama, :hp, :alamat, :email, :identitas, :ambil, :kembali, :jam_ambil, :catatan, "DRAFT")'
        );
        $stmt->execute([
            'kode_booking' => $data['kode_booking'],
            'nama' => $data['nama'],
            'hp' => $data['hp'],
            'alamat' => $data['alamat'],
            'email' => $data['email'] ?: null,
            'identitas' => $data['identitas_file'],
            'ambil' => $data['tanggal_ambil'],
            'kembali' => $data['tanggal_kembali'],
            'jam_ambil' => $data['jam_ambil'] ?: '09:00',
            'catatan' => $data['catatan'] ?: null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function buatBookingMember(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO bookings (kode_booking, user_id, identitas_file, tanggal_ambil, tanggal_kembali, jam_ambil, catatan, status)
             VALUES (:kode_booking, :user_id, :identitas, :ambil, :kembali, :jam_ambil, :catatan, "DRAFT")'
        );
        $stmt->execute([
            'kode_booking' => $data['kode_booking'],
            'user_id' => $data['user_id'],
            'identitas' => $data['identitas_file'],
            'ambil' => $data['tanggal_ambil'],
            'kembali' => $data['tanggal_kembali'],
            'jam_ambil' => $data['jam_ambil'] ?: '09:00',
            'catatan' => $data['catatan'] ?: null,
        ]);
        return (int) $db->lastInsertId();
    }

    // Tiap barang di satu booking punya periode sewanya sendiri (bisa beda
    // dari barang lain di booking yang sama) - lihat pulihkanEnvelope() untuk
    // bagaimana bookings.tanggal_ambil/tanggal_kembali (amplop keseluruhan
    // booking) dijaga tetap sinkron dengan ini.
    public static function tambahItemBooking(int $bookingId, int $itemId, int $jumlah, float $subtotal, ?string $ukuranDipilih, string $tanggalAmbil, string $tanggalKembali, string $jamAmbil): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO booking_items (booking_id, item_id, jumlah, subtotal, ukuran_dipilih, tanggal_ambil, tanggal_kembali, jam_ambil)
             VALUES (:booking_id, :item_id, :jumlah, :subtotal, :ukuran_dipilih, :ambil, :kembali, :jam_ambil)'
        );
        $stmt->execute([
            'booking_id' => $bookingId,
            'item_id' => $itemId,
            'jumlah' => $jumlah,
            'subtotal' => $subtotal,
            'ukuran_dipilih' => $ukuranDipilih !== '' ? $ukuranDipilih : null,
            'ambil' => $tanggalAmbil,
            'kembali' => $tanggalKembali,
            'jam_ambil' => $jamAmbil !== '' ? $jamAmbil : '09:00',
        ]);
    }

    // Hitung ulang "amplop" bookings.tanggal_ambil/tanggal_kembali dari
    // MIN/MAX tanggal seluruh booking_items di dalamnya - dipanggil tiap kali
    // tanggal salah satu barang berubah (tambah barang, perpanjang sewa).
    // TIDAK PERNAH dipanggil dari alur pengembalian - tanggal barang tidak
    // berubah saat dikembalikan, cuma statusnya.
    public static function pulihkanEnvelope(int $bookingId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE bookings b
             JOIN (
                 SELECT MIN(tanggal_ambil) AS ambil, MAX(tanggal_kembali) AS kembali,
                     (SELECT jam_ambil FROM booking_items WHERE booking_id = :booking_id2 ORDER BY tanggal_kembali DESC, id DESC LIMIT 1) AS jam_kembali
                 FROM booking_items WHERE booking_id = :booking_id
             ) envelope ON 1 = 1
             SET b.tanggal_ambil = envelope.ambil, b.tanggal_kembali = envelope.kembali, b.jam_kembali = envelope.jam_kembali
             WHERE b.id = :id'
        );
        $stmt->execute(['booking_id' => $bookingId, 'booking_id2' => $bookingId, 'id' => $bookingId]);
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $booking = $stmt->fetch();
        return $booking ?: null;
    }

    public static function getItemBooking(int $bookingId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT bi.*, i.nama, i.harga_per_malam FROM booking_items bi
             JOIN items i ON i.id = bi.item_id WHERE bi.booking_id = :booking_id'
        );
        $stmt->execute(['booking_id' => $bookingId]);
        return $stmt->fetchAll();
    }

    public static function getBookingItemById(int $bookingItemId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT bi.*, i.nama, i.harga_per_malam FROM booking_items bi
             JOIN items i ON i.id = bi.item_id WHERE bi.id = :id'
        );
        $stmt->execute(['id' => $bookingItemId]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function updateStatus(int $bookingId, string $status): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE bookings SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $bookingId]);
    }

    public static function updateStatusItem(int $bookingItemId, string $status): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE booking_items SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $bookingItemId]);
    }

    public static function getJumlahBelumDikembalikan(int $bookingId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM booking_items WHERE booking_id = :id AND status = "disewa"');
        $stmt->execute(['id' => $bookingId]);
        return (int) $stmt->fetchColumn();
    }

    // Pembatalan oleh admin wajib disertai alasan, supaya member/tamu tahu
    // kenapa reservasinya dibatalkan (bukan cuma "Dibatalkan" tanpa konteks).
    public static function batalkanDenganAlasan(int $bookingId, string $alasan): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE bookings SET status = "DIBATALKAN", catatan_admin = :alasan WHERE id = :id');
        $stmt->execute(['alasan' => $alasan, 'id' => $bookingId]);
    }

    // KTP/identitas jaminan hanya berlaku selama masa sewa. Dipanggil setelah
    // penyewaan dinyatakan selesai supaya referensinya dihapus dari database
    // (file fisiknya dihapus terpisah oleh pemanggil, lihat storage_path()).
    public static function hapusIdentitas(int $bookingId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE bookings SET identitas_file = NULL WHERE id = :id');
        $stmt->execute(['id' => $bookingId]);
    }

    // Melengkapi identitas booking kasir yang tadinya dilewati saat checkout
    // (lihat aksi/proses-kasir.php - opsi "lewati identitas") - dipanggil dari
    // aksi/lengkapi-identitas.php oleh admin/owner.
    public static function simpanIdentitas(int $bookingId, string $namaFile): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE bookings SET identitas_file = :identitas WHERE id = :id');
        $stmt->execute(['identitas' => $namaFile, 'id' => $bookingId]);
    }

    public static function getByKodeDanHp(string $kode, string $hp): ?array
{
    $db = Database::getConnection();
    // Nomor HP lama member (u.no_hp_lama) tetap bisa dipakai untuk melacak
    // booking ini SELAMA booking-nya masih berjalan (belum SELESAI/EXPIRED/
    // DIBATALKAN) — supaya member yang ganti nomor di tengah masa sewa tidak
    // kehilangan akses cek booking. Begitu booking selesai, hanya nomor HP
    // yang aktif sekarang (u.no_hp) yang berlaku lagi.
    $stmt = $db->prepare(
        'SELECT b.* FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         WHERE b.kode_booking = :kode
         AND (
             b.guest_hp = :hp
             OR u.no_hp = :hp2
             OR (u.no_hp_lama = :hp3 AND b.status NOT IN ("SELESAI", "EXPIRED", "DIBATALKAN"))
         )'
    );
    $stmt->execute(['kode' => $kode, 'hp' => $hp, 'hp2' => $hp, 'hp3' => $hp]);
    $booking = $stmt->fetch();
    return $booking ?: null;
}

public static function getRiwayatByUserId(int $userId): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT * FROM bookings WHERE user_id = :user_id ORDER BY created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

    public static function getStatistikMember(int $userId): array
    {
        $db = Database::getConnection();

        $stmtAktif = $db->prepare(
            'SELECT COUNT(*) FROM bookings WHERE user_id = :user_id AND status NOT IN ("SELESAI", "EXPIRED", "DIBATALKAN")'
        );
        $stmtAktif->execute(['user_id' => $userId]);
        $reservasiAktif = (int) $stmtAktif->fetchColumn();

        $stmtRiwayat = $db->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = :user_id');
        $stmtRiwayat->execute(['user_id' => $userId]);
        $totalRiwayat = (int) $stmtRiwayat->fetchColumn();

        $stmtBelumLunas = $db->prepare(
            'SELECT b.id, b.kode_booking,
                (SELECT COALESCE(SUM(bi.subtotal), 0) FROM booking_items bi WHERE bi.booking_id = b.id) AS total_sewa,
                (SELECT COALESCE(SUM(t.nominal), 0) FROM transactions t WHERE t.booking_id = b.id AND t.jenis IN ("dp", "lunas", "sisa") AND t.status_verifikasi = "terverifikasi") AS total_dibayar
            FROM bookings b
            WHERE b.user_id = :user_id AND b.status NOT IN ("SELESAI", "EXPIRED", "DIBATALKAN")'
        );
        $stmtBelumLunas->execute(['user_id' => $userId]);
        $daftarBooking = $stmtBelumLunas->fetchAll();

        $totalBelumLunas = 0;
        foreach ($daftarBooking as $b) {
            $sisa = (float) $b['total_sewa'] - (float) $b['total_dibayar'];
            if ($sisa > 0) {
                $totalBelumLunas += $sisa;
            }
        }

        return [
            'reservasi_aktif' => $reservasiAktif,
            'total_riwayat' => $totalRiwayat,
            'belum_lunas' => $totalBelumLunas,
        ];
    }

    public static function getDaftarOnline(string $filterStatus = ''): array
{
    $db = Database::getConnection();

    $sql = 'SELECT b.*, u.nama AS nama_member, u.no_hp AS hp_member,
            (SELECT COALESCE(SUM(bi.subtotal), 0) FROM booking_items bi WHERE bi.booking_id = b.id) AS total_sewa
            FROM bookings b
            LEFT JOIN users u ON u.id = b.user_id
            WHERE b.status IN ("DRAFT", "MENUNGGU_PEMBAYARAN", "MENUNGGU_VERIFIKASI", "MENUNGGU_KEDATANGAN",
                                "RESERVASI_DIKONFIRMASI", "BARANG_DISIAPKAN", "SIAP_DIAMBIL", "EXPIRED", "DIBATALKAN")';
    $params = [];

    if ($filterStatus !== '') {
        $sql .= ' AND b.status = :status';
        $params['status'] = $filterStatus;
    }

    $sql .= ' ORDER BY b.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

public static function getDetailLengkap(int $id): ?array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT b.*, u.nama AS nama_member, u.no_hp AS hp_member, u.email AS email_member
         FROM bookings b LEFT JOIN users u ON u.id = b.user_id WHERE b.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $booking = $stmt->fetch();
    return $booking ?: null;
}


public static function buatBookingKasir(array $data): int
{
    $db = Database::getConnection();
    // user_id ditautkan kalau kasir mengonfirmasi nomor HP yang dimasukkan
    // memang milik member terdaftar (lihat aksi/cek-member-by-hp.php dan
    // validasi ulang di aksi/proses-kasir.php) — guest_nama/guest_hp tetap
    // diisi seperti biasa supaya struk & tampilan lama tidak berubah, tapi
    // dengan user_id terisi, transaksi ini juga otomatis muncul di riwayat
    // member yang bersangkutan (BookingModel::getRiwayatByUserId, dst).
    $stmt = $db->prepare(
        'INSERT INTO bookings (kode_booking, user_id, guest_nama, guest_hp, guest_alamat, identitas_file, tanggal_ambil, tanggal_kembali, jam_ambil, status)
         VALUES (:kode_booking, :user_id, :nama, :hp, :alamat, :identitas, :ambil, :kembali, :jam_ambil, "SEDANG_DISEWA")'
    );
    $stmt->execute([
        'kode_booking' => $data['kode_booking'],
        'user_id' => $data['user_id'] ?? null,
        'nama' => $data['nama'],
        'hp' => $data['hp'],
        'alamat' => $data['alamat'] ?: 'Walk-in / Tidak diisi',
        'identitas' => $data['identitas_file'],
        'ambil' => $data['tanggal_ambil'],
        'kembali' => $data['tanggal_kembali'],
        'jam_ambil' => $data['jam_ambil'] ?: '09:00',
    ]);
    return (int) $db->lastInsertId();
}

public static function getPenyewaanAktif(): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT b.*, u.nama AS nama_member,
            (SELECT COALESCE(SUM(bi.subtotal), 0) FROM booking_items bi WHERE bi.booking_id = b.id) AS total_sewa,
            (SELECT COALESCE(SUM(t.nominal), 0) FROM transactions t WHERE t.booking_id = b.id AND t.status_verifikasi != "ditolak") AS total_dibayar,
            (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.id) AS jumlah_total_item,
            (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa") AS jumlah_belum_kembali,
            COALESCE(
                (SELECT MIN(bi.tanggal_kembali) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa"),
                b.tanggal_kembali
            ) AS tanggal_kembali_terdekat
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         WHERE b.status IN ("RESERVASI_DIKONFIRMASI", "BARANG_DISIAPKAN", "SIAP_DIAMBIL", "SEDANG_DISEWA")
         ORDER BY tanggal_kembali_terdekat ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Perpanjangan sekarang di-scope ke SATU barang (booking_items), bukan
// seluruh booking - setiap barang punya tanggal_kembali sendiri sejak fitur
// periode sewa dinamis. Setelah dipanggil, pemanggil WAJIB memanggil
// pulihkanEnvelope() supaya bookings.tanggal_kembali (amplop) ikut sinkron.
public static function perpanjangDurasiItem(int $bookingItemId, string $tanggalKembaliBaru): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('UPDATE booking_items SET tanggal_kembali = :tanggal WHERE id = :id');
    $stmt->execute(['tanggal' => $tanggalKembaliBaru, 'id' => $bookingItemId]);
}

public static function tambahSubtotalItem(int $bookingItemId, float $tambahanSubtotal): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('UPDATE booking_items SET subtotal = subtotal + :tambahan WHERE id = :id');
    $stmt->execute(['tambahan' => $tambahanSubtotal, 'id' => $bookingItemId]);
}

// "Terdekat" di sini merujuk ke barang yang statusnya masih "disewa" dengan
// tanggal_kembali paling awal - bukan lagi b.tanggal_kembali (amplop, yang
// mencerminkan barang PALING AKHIR) - supaya booking dengan salah satu
// barang sudah telat tetap terdeteksi telat meski amplopnya sendiri belum lewat.
public static function getSiapDikembalikan(): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT b.*, u.nama AS nama_member,
            (SELECT COALESCE(SUM(bi.subtotal), 0) FROM booking_items bi WHERE bi.booking_id = b.id) AS total_sewa,
            (SELECT COALESCE(SUM(t.nominal), 0) FROM transactions t WHERE t.booking_id = b.id AND t.status_verifikasi != "ditolak") AS total_dibayar,
            (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.id) AS jumlah_total_item,
            (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa") AS jumlah_belum_kembali,
            COALESCE(
                (SELECT MIN(bi.tanggal_kembali) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa"),
                b.tanggal_kembali
            ) AS tanggal_kembali_terdekat,
            DATEDIFF(CURDATE(), COALESCE(
                (SELECT MIN(bi.tanggal_kembali) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa"),
                b.tanggal_kembali
            )) AS hari_terlambat
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         WHERE b.status = "SEDANG_DISEWA"
         ORDER BY tanggal_kembali_terdekat ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

public static function cariUntukPengembalian(string $kataKunci): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT b.*, u.nama AS nama_member,
            (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.id) AS jumlah_total_item,
            (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa") AS jumlah_belum_kembali,
            COALESCE(
                (SELECT MIN(bi.tanggal_kembali) FROM booking_items bi WHERE bi.booking_id = b.id AND bi.status = "disewa"),
                b.tanggal_kembali
            ) AS tanggal_kembali_terdekat
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         WHERE b.status = "SEDANG_DISEWA"
         AND (b.kode_booking LIKE :kata OR b.guest_nama LIKE :kata OR u.nama LIKE :kata)
         ORDER BY tanggal_kembali_terdekat ASC'
    );
    $stmt->execute(['kata' => '%' . $kataKunci . '%']);
    return $stmt->fetchAll();
}

/**
 * Tandai booking yang belum juga dibayar (DP/lunas) dalam N menit sebagai
 * EXPIRED, supaya stok yang "disandera" oleh reservasi yang ditinggalkan
 * bisa lepas lagi. Batas waktunya 60 menit (lihat countdown di
 * pembayaran.php) karena checkout online sekarang wajib QRIS - kalau
 * pelanggan tidak menyelesaikan pembayaran dalam waktu itu, tidak ada
 * alasan untuk terus menahan stoknya. Dipanggil secara opportunistic dari
 * dashboard admin dan bisa juga dijalankan lewat scheduled task (lihat
 * scripts/expire-bookings.php).
 */
public static function expireBookingBelumDibayar(int $batasMenit = 60): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'UPDATE bookings
         SET status = "EXPIRED"
         WHERE status = "MENUNGGU_PEMBAYARAN"
         AND created_at < (NOW() - INTERVAL :batas_menit MINUTE)'
    );
    $stmt->bindValue(':batas_menit', $batasMenit, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
}

}