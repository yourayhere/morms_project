<?php

namespace App\Models;

// storage_path()/backup_bukti_path() dipakai di hampir semua method di model
// ini - di-require di sini juga (bukan cuma mengandalkan halaman pemanggil)
// supaya model ini tidak gagal kalau suatu saat dipanggil dari halaman yang
// lupa meng-require app/Helpers/paths.php lebih dulu.
require_once __DIR__ . '/../Helpers/paths.php';

use App\Core\Database;
use DateTime;
use PDO;
use RuntimeException;
use ZipArchive;

// Backup & Arsip Bukti Pembayaran.
//
// PENTING: bukti pembayaran (transactions.bukti_bayar) dan foto identitas/KTP
// jaminan (bookings.identitas_file) disimpan bersamaan di folder fisik yang
// SAMA (storage/identitas/), hanya dibedakan lewat kolom database yang
// mereferensikannya (lihat app/Helpers/upload.php). Karena itu, model ini
// SELALU menentukan file mana yang boleh disentuh lewat query ke kolom
// transactions.bukti_bayar - TIDAK PERNAH memindai folder secara langsung -
// supaya foto identitas pelanggan tidak pernah ikut ter-backup atau
// terhapus oleh fitur ini.
class BackupBuktiPembayaranModel
{
    public static function getRiwayat(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT b.*, u.nama AS nama_pembuat
             FROM backup_bukti_pembayaran b
             LEFT JOIN users u ON u.id = b.dibuat_oleh
             ORDER BY b.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getBackupTerakhir(): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT b.*, u.nama AS nama_pembuat
             FROM backup_bukti_pembayaran b
             LEFT JOIN users u ON u.id = b.dibuat_oleh
             ORDER BY b.created_at DESC LIMIT 1'
        );
        $stmt->execute();
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function getByNamaFile(string $namaFile): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM backup_bukti_pembayaran WHERE nama_file = :nama_file');
        $stmt->execute(['nama_file' => $namaFile]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    // Jadwal backup berikutnya: selalu backup terakhir + 1 bulan (rolling,
    // bukan tanggal tetap). Null kalau belum pernah ada backup sama sekali.
    public static function getJadwalBerikutnya(): ?DateTime
    {
        $terakhir = self::getBackupTerakhir();
        if (!$terakhir) {
            return null;
        }
        return (new DateTime($terakhir['cutoff_at']))->modify('+1 month');
    }

    // Status penyimpanan saat ini: jumlah & ukuran seluruh bukti pembayaran
    // yang fisiknya masih ada di disk, plus berapa yang akan terbebas kalau
    // Arsip dijalankan sekarang (mengacu ke backup terakhir yang belum
    // diarsipkan).
    public static function getStatusPenyimpanan(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT bukti_bayar FROM transactions WHERE bukti_bayar IS NOT NULL AND bukti_bayar != ''");
        $stmt->execute();
        $daftar = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $jumlahAda = 0;
        $ukuranTotal = 0;
        foreach ($daftar as $nama) {
            $path = storage_path('identitas/' . $nama);
            if (file_exists($path)) {
                $jumlahAda++;
                $ukuranTotal += filesize($path);
            }
        }

        $terakhir = self::getBackupTerakhir();
        $bisaDiarsipkan = 0;
        if ($terakhir && $terakhir['diarsipkan_at'] === null) {
            $stmtBisa = $db->prepare(
                "SELECT COUNT(*) FROM transactions
                 WHERE bukti_bayar IS NOT NULL AND bukti_bayar != '' AND created_at <= :cutoff"
            );
            $stmtBisa->execute(['cutoff' => $terakhir['cutoff_at']]);
            $bisaDiarsipkan = (int) $stmtBisa->fetchColumn();
        }

        return [
            'jumlah_file' => $jumlahAda,
            'ukuran_bytes' => $ukuranTotal,
            'bisa_diarsipkan' => $bisaDiarsipkan,
        ];
    }

    /**
     * Buat backup ZIP dari seluruh bukti pembayaran yang ada saat ini.
     * Backup TIDAK PERNAH menghapus apa pun - murni membuat salinan.
     *
     * Titik waktu "cutoff" dipatok SEKALI di awal (sebelum query dijalankan)
     * dan dipakai sebagai batas WHERE sekaligus disimpan sebagai cutoff_at -
     * supaya transaksi baru yang masuk persis saat/setelah proses backup
     * berjalan tidak pernah ikut tercatat sebagai "sudah dibackup", dan
     * karena itu tidak akan pernah ikut terhapus oleh Arsip nantinya.
     */
    public static function buatBackup(?int $ownerId): array
    {
        $cutoff = date('Y-m-d H:i:s');

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT bukti_bayar FROM transactions
             WHERE bukti_bayar IS NOT NULL AND bukti_bayar != '' AND created_at <= :cutoff"
        );
        $stmt->execute(['cutoff' => $cutoff]);
        $daftarFile = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($daftarFile)) {
            throw new RuntimeException('Belum ada bukti pembayaran yang bisa dibackup.');
        }

        if (!is_dir(backup_bukti_path())) {
            mkdir(backup_bukti_path(), 0755, true);
        }

        $namaFile = 'backup-bukti-pembayaran-' . date('Y-m-d_H-i', strtotime($cutoff)) . '.zip';
        $pathZip = backup_bukti_path($namaFile);

        $zip = new ZipArchive();
        if ($zip->open($pathZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat berkas ZIP.');
        }

        $jumlahDitambahkan = 0;
        foreach ($daftarFile as $namaBukti) {
            $sumberPath = storage_path('identitas/' . $namaBukti);
            if (file_exists($sumberPath)) {
                $zip->addFile($sumberPath, $namaBukti);
                $jumlahDitambahkan++;
            }
        }
        $zip->close();

        if ($jumlahDitambahkan === 0) {
            @unlink($pathZip);
            throw new RuntimeException('Berkas bukti pembayaran yang tercatat di database tidak ditemukan di penyimpanan.');
        }

        $ukuranBytes = (int) filesize($pathZip);

        $stmtInsert = $db->prepare(
            'INSERT INTO backup_bukti_pembayaran (nama_file, cutoff_at, jumlah_file, ukuran_bytes, dibuat_oleh)
             VALUES (:nama_file, :cutoff_at, :jumlah_file, :ukuran_bytes, :dibuat_oleh)'
        );
        $stmtInsert->execute([
            'nama_file' => $namaFile,
            'cutoff_at' => $cutoff,
            'jumlah_file' => $jumlahDitambahkan,
            'ukuran_bytes' => $ukuranBytes,
            'dibuat_oleh' => $ownerId,
        ]);

        return [
            'id' => (int) $db->lastInsertId(),
            'nama_file' => $namaFile,
            'cutoff_at' => $cutoff,
            'jumlah_file' => $jumlahDitambahkan,
            'ukuran_bytes' => $ukuranBytes,
        ];
    }

    /**
     * Hapus fisik seluruh bukti pembayaran yang termasuk dalam backup
     * TERAKHIR (created_at <= cutoff_at backup itu), lalu tandai backup
     * tersebut sebagai sudah diarsipkan. Kolom transactions.bukti_bayar
     * SENGAJA tidak diubah/dikosongkan - riwayat transaksi tetap utuh
     * sebagai catatan bahwa bukti pernah ada, dan halaman yang
     * menampilkannya (detail-reservasi.php, lihat-bukti-bayar.php) sudah
     * memeriksa file_exists() sehingga aman menampilkan "berkas tidak
     * ditemukan" begitu file diarsipkan.
     *
     * Transaksi yang dibuat SETELAH cutoff_at (mis. pelanggan checkout baru
     * saat/setelah proses backup berjalan) tidak pernah masuk kriteria ini,
     * apa pun jarak waktu antara backup dan proses arsip dijalankan.
     */
    public static function arsipkanBackupTerakhir(): array
    {
        $backupTerakhir = self::getBackupTerakhir();
        if (!$backupTerakhir) {
            throw new RuntimeException('Belum ada backup yang bisa diarsipkan.');
        }
        if ($backupTerakhir['diarsipkan_at'] !== null) {
            throw new RuntimeException('Backup terakhir sudah pernah diarsipkan sebelumnya.');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT bukti_bayar FROM transactions
             WHERE bukti_bayar IS NOT NULL AND bukti_bayar != '' AND created_at <= :cutoff"
        );
        $stmt->execute(['cutoff' => $backupTerakhir['cutoff_at']]);
        $daftarFile = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $jumlahDihapus = 0;
        foreach ($daftarFile as $namaBukti) {
            $path = storage_path('identitas/' . $namaBukti);
            if (file_exists($path)) {
                @unlink($path);
                $jumlahDihapus++;
            }
        }

        $stmtTandai = $db->prepare('UPDATE backup_bukti_pembayaran SET diarsipkan_at = NOW() WHERE id = :id');
        $stmtTandai->execute(['id' => $backupTerakhir['id']]);

        return [
            'jumlah_dihapus' => $jumlahDihapus,
            'backup_id' => $backupTerakhir['id'],
        ];
    }
}
