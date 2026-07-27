<?php

namespace App\Models;

use App\Core\Database;

class ItemModel
{
    public static function getSemuaKategori(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT DISTINCT kategori FROM items ORDER BY kategori ASC');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function getKatalog(string $kategori = '', string $cari = '', string $urutan = 'terbaru'): array
    {
        $db = Database::getConnection();

        $sql = 'SELECT i.*, 
                (SELECT url FROM item_images WHERE item_id = i.id ORDER BY urutan ASC, id ASC LIMIT 1) AS foto_utama
                FROM items i WHERE i.status = "aktif"';
        $params = [];

        if ($kategori !== '') {
            $sql .= ' AND i.kategori = :kategori';
            $params['kategori'] = $kategori;
        }

        if ($cari !== '') {
            $sql .= ' AND i.nama LIKE :cari';
            $params['cari'] = '%' . $cari . '%';
        }

        switch ($urutan) {
            case 'harga_asc':
                $sql .= ' ORDER BY i.harga_per_malam ASC';
                break;
            case 'harga_desc':
                $sql .= ' ORDER BY i.harga_per_malam DESC';
                break;
            default:
                $sql .= ' ORDER BY i.created_at DESC';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM items WHERE id = :id AND status = "aktif"');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    public static function getImages(int $itemId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, url FROM item_images WHERE item_id = :id ORDER BY urutan ASC');
        $stmt->execute(['id' => $itemId]);
        return $stmt->fetchAll();
    }

    public static function getGambarById(int $imageId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM item_images WHERE id = :id');
        $stmt->execute(['id' => $imageId]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function hapusGambarById(int $imageId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM item_images WHERE id = :id');
        $stmt->execute(['id' => $imageId]);
    }

    // Filter tanggal DAN status di sini sengaja per-barang (bi.*), bukan
    // per-booking (b.*) - satu booking bisa berisi barang dengan periode sewa
    // berbeda-beda, dan barang yang sudah bi.status='disewa' -> 'dikembalikan'
    // WAJIB langsung lepas stoknya sendiri, terlepas dari barang lain di
    // booking yang sama yang mungkin belum kembali. Join ke `bookings` cuma
    // dipakai untuk mengecualikan booking yang batal/kedaluwarsa/selesai.
    public static function getStokTerpakai(int $itemId, string $tanggalAmbil, string $tanggalKembali): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(bi.jumlah), 0) AS total
             FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             WHERE bi.item_id = :item_id
             AND bi.status = "disewa"
             AND b.status NOT IN ("DIBATALKAN", "EXPIRED", "SELESAI")
             AND bi.tanggal_ambil < :tanggal_kembali
             AND bi.tanggal_kembali > :tanggal_ambil'
        );
        $stmt->execute([
            'item_id' => $itemId,
            'tanggal_ambil' => $tanggalAmbil,
            'tanggal_kembali' => $tanggalKembali,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Sama seperti getStokTerpakai(), tapi mengunci baris `items` (SELECT ...
     * FOR UPDATE) lebih dulu. HARUS dipanggil di dalam DB transaction, tepat
     * sebelum insert booking_items, supaya dua checkout yang bersamaan untuk
     * barang & periode yang sama tidak bisa lolos validasi stok berbarengan
     * (mencegah oversell / race condition).
     */
    public static function getSisaStokUntukLocking(int $itemId, string $tanggalAmbil, string $tanggalKembali): int
    {
        $db = Database::getConnection();

        $stmtLock = $db->prepare('SELECT stok_total FROM items WHERE id = :id FOR UPDATE');
        $stmtLock->execute(['id' => $itemId]);
        $stokTotal = (int) $stmtLock->fetchColumn();

        $terpakai = self::getStokTerpakai($itemId, $tanggalAmbil, $tanggalKembali);

        return $stokTotal - $terpakai;
    }

    public static function getKalenderKetersediaan(int $itemId, int $stokTotal, string $bulanTahun): array
    {
        $db = Database::getConnection();
        $tanggalAwal = $bulanTahun . '-01';
        $tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));

        $stmt = $db->prepare(
            'SELECT bi.tanggal_ambil, bi.tanggal_kembali, bi.jumlah
             FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             WHERE bi.item_id = :item_id
             AND bi.status = "disewa"
             AND b.status NOT IN ("DIBATALKAN", "EXPIRED", "SELESAI")
             AND bi.tanggal_kembali >= :awal AND bi.tanggal_ambil <= :akhir'
        );
        $stmt->execute([
            'item_id' => $itemId,
            'awal' => $tanggalAwal,
            'akhir' => $tanggalAkhir,
        ]);
        $bookingList = $stmt->fetchAll();

        $hasil = [];
        $jumlahHari = (int) date('t', strtotime($tanggalAwal));

        for ($tgl = 1; $tgl <= $jumlahHari; $tgl++) {
            $tanggalCek = $bulanTahun . '-' . str_pad((string) $tgl, 2, '0', STR_PAD_LEFT);
            $terpakai = 0;

            foreach ($bookingList as $booking) {
                if ($tanggalCek >= $booking['tanggal_ambil'] && $tanggalCek < $booking['tanggal_kembali']) {
                    $terpakai += (int) $booking['jumlah'];
                }
            }

            $sisa = max(0, $stokTotal - $terpakai);
            $hasil[$tanggalCek] = [
                'sisa' => $sisa,
                'status' => $sisa === 0 ? 'penuh' : ($sisa <= 2 ? 'terbatas' : 'tersedia'),
            ];
        }

        return $hasil;
    }

    public static function getSemuaAktifDenganStok(): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT i.*,
            (SELECT url FROM item_images WHERE item_id = i.id ORDER BY urutan ASC, id ASC LIMIT 1) AS foto_utama
         FROM items i WHERE i.status = "aktif" ORDER BY i.nama ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

public static function getSemuaUntukAdmin(): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT i.*,
            (SELECT url FROM item_images WHERE item_id = i.id ORDER BY urutan ASC, id ASC LIMIT 1) AS foto_utama
         FROM items i ORDER BY i.created_at DESC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

public static function getByIdAdmin(int $id): ?array
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
    return $item ?: null;
}

public static function buat(array $data): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'INSERT INTO items (nama, kategori, sub_kategori, harga_per_malam, deposit, denda_per_hari, stok_total, deskripsi, syarat_penyewaan, status)
         VALUES (:nama, :kategori, :sub_kategori, :harga, 0, 0, :stok, :deskripsi, :syarat, :status)'
    );
    $stmt->execute([
        'nama' => $data['nama'],
        'kategori' => $data['kategori'],
        'sub_kategori' => $data['sub_kategori'] ?: null,
        'harga' => $data['harga_per_malam'],
        'stok' => $data['stok_total'],
        'deskripsi' => $data['deskripsi'],
        'syarat' => $data['syarat_penyewaan'],
        'status' => $data['status'],
    ]);
    return (int) $db->lastInsertId();
}

public static function update(int $id, array $data): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'UPDATE items SET nama = :nama, kategori = :kategori, sub_kategori = :sub_kategori, harga_per_malam = :harga,
            stok_total = :stok, deskripsi = :deskripsi, syarat_penyewaan = :syarat, status = :status
         WHERE id = :id'
    );
    $stmt->execute([
        'nama' => $data['nama'],
        'kategori' => $data['kategori'],
        'sub_kategori' => $data['sub_kategori'] ?: null,
        'harga' => $data['harga_per_malam'],
        'stok' => $data['stok_total'],
        'deskripsi' => $data['deskripsi'],
        'syarat' => $data['syarat_penyewaan'],
        'status' => $data['status'],
        'id' => $id,
    ]);
}

// items.ukuran (nilai tunggal lama) sudah digantikan sistem varian
// (item_variasi). Dipanggil sekali saat migrasi supaya data lama tidak
// hilang begitu saja — barang yang sebelumnya cuma punya 1 ukuran otomatis
// jadi 1 baris varian dengan stok = stok_total lama.
public static function migrasiUkuranLamaKeVarian(): void
{
    $db = Database::getConnection();
    $stmt = $db->query(
        "SELECT id, ukuran, stok_total FROM items
         WHERE kategori = 'pakaian_outdoor' AND ukuran IS NOT NULL AND ukuran != ''"
    );
    $daftarLama = $stmt->fetchAll();

    foreach ($daftarLama as $item) {
        $cek = $db->prepare('SELECT COUNT(*) FROM item_variasi WHERE item_id = :id');
        $cek->execute(['id' => $item['id']]);
        if ((int) $cek->fetchColumn() > 0) {
            continue;
        }
        $insert = $db->prepare('INSERT INTO item_variasi (item_id, ukuran, stok) VALUES (:item_id, :ukuran, :stok)');
        $insert->execute([
            'item_id' => $item['id'],
            'ukuran' => $item['ukuran'],
            'stok' => (int) $item['stok_total'],
        ]);
    }
}

// Daftar ukuran yang pernah dipakai admin untuk sub kategori pakaian
// tertentu (dari data variasi asli, bukan kolom items.ukuran yang sudah
// digantikan sistem varian stok). Dipakai untuk preset pilihan ukuran.
public static function getSemuaUkuran(string $subKategori): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT DISTINCT iv.ukuran FROM item_variasi iv
         JOIN items i ON i.id = iv.item_id
         WHERE i.sub_kategori = :sub
         ORDER BY iv.ukuran ASC'
    );
    $stmt->execute(['sub' => $subKategori]);
    return $stmt->fetchAll(\PDO::FETCH_COLUMN);
}

public static function getVariasi(int $itemId): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT * FROM item_variasi WHERE item_id = :item_id ORDER BY ukuran ASC');
    $stmt->execute(['item_id' => $itemId]);
    return $stmt->fetchAll();
}

// Mengganti seluruh variasi ukuran sebuah barang (hapus yang lama, simpan
// yang baru) dan mengembalikan total stok gabungan seluruh ukuran, supaya
// kolom stok_total barang bisa disinkronkan otomatis oleh pemanggil.
public static function simpanVariasi(int $itemId, array $daftarVariasi): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare('DELETE FROM item_variasi WHERE item_id = :item_id');
    $stmt->execute(['item_id' => $itemId]);

    $totalStok = 0;
    $stmtInsert = $db->prepare('INSERT INTO item_variasi (item_id, ukuran, stok) VALUES (:item_id, :ukuran, :stok)');
    foreach ($daftarVariasi as $variasi) {
        $ukuran = trim((string) ($variasi['ukuran'] ?? ''));
        if ($ukuran === '') {
            continue;
        }
        $stok = max(0, (int) ($variasi['stok'] ?? 0));
        $stmtInsert->execute(['item_id' => $itemId, 'ukuran' => $ukuran, 'stok' => $stok]);
        $totalStok += $stok;
    }

    return $totalStok;
}

public static function getStokTerpakaiVarian(int $itemId, string $ukuran, string $tanggalAmbil, string $tanggalKembali): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(bi.jumlah), 0) AS total
         FROM booking_items bi
         JOIN bookings b ON b.id = bi.booking_id
         WHERE bi.item_id = :item_id
         AND bi.ukuran_dipilih = :ukuran
         AND bi.status = "disewa"
         AND b.status NOT IN ("DIBATALKAN", "EXPIRED", "SELESAI")
         AND bi.tanggal_ambil < :tanggal_kembali
         AND bi.tanggal_kembali > :tanggal_ambil'
    );
    $stmt->execute([
        'item_id' => $itemId,
        'ukuran' => $ukuran,
        'tanggal_ambil' => $tanggalAmbil,
        'tanggal_kembali' => $tanggalKembali,
    ]);
    return (int) $stmt->fetchColumn();
}

/**
 * Sama seperti getSisaStokUntukLocking(), tapi mengunci baris item_variasi
 * (per ukuran) alih-alih baris items. Dipakai untuk barang kategori Pakaian
 * Outdoor yang stoknya dipecah per ukuran, supaya dua checkout untuk ukuran
 * & periode yang sama tidak bisa lolos validasi berbarengan.
 */
public static function getSisaStokVarianUntukLocking(int $itemId, string $ukuran, string $tanggalAmbil, string $tanggalKembali): int
{
    $db = Database::getConnection();

    $stmtLock = $db->prepare('SELECT stok FROM item_variasi WHERE item_id = :id AND ukuran = :ukuran FOR UPDATE');
    $stmtLock->execute(['id' => $itemId, 'ukuran' => $ukuran]);
    $stokVarian = $stmtLock->fetchColumn();

    if ($stokVarian === false) {
        return 0;
    }

    $terpakai = self::getStokTerpakaiVarian($itemId, $ukuran, $tanggalAmbil, $tanggalKembali);

    return (int) $stokVarian - $terpakai;
}

public static function toggleStatus(int $id): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('UPDATE items SET status = IF(status = "aktif", "nonaktif", "aktif") WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

// Dipakai sebelum hapus barang: kalau barang ini pernah masuk booking_items
// (riwayat sewa apa pun, termasuk booking lama), jangan izinkan hapus
// permanen — cukup nonaktifkan lewat toggleStatus() supaya data riwayat
// transaksi/laporan lama tidak kehilangan rincian barangnya.
public static function getJumlahRiwayatSewa(int $id): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT COUNT(*) FROM booking_items WHERE item_id = :id');
    $stmt->execute(['id' => $id]);
    return (int) $stmt->fetchColumn();
}

// item_images & item_variasi ikut terhapus otomatis (FK ON DELETE CASCADE).
// booking_items.item_id sengaja ON DELETE RESTRICT di level database sebagai
// jaring pengaman terakhir kalau getJumlahRiwayatSewa() somehow terlewat.
public static function hapus(int $id): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

public static function tambahGambar(int $itemId, string $url, int $urutan = 0): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('INSERT INTO item_images (item_id, url, urutan) VALUES (:item_id, :url, :urutan)');
    $stmt->execute(['item_id' => $itemId, 'url' => $url, 'urutan' => $urutan]);
}

public static function hapusSemuaGambar(int $itemId): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('DELETE FROM item_images WHERE item_id = :item_id');
    $stmt->execute(['item_id' => $itemId]);
}
}