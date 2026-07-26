-- Merimba Outdoor - Skema database siap-hosting
-- Berisi: struktur seluruh tabel (kosong) + data settings (profil usaha) + 1 akun Owner.
-- Semua data uji coba (items, bookings, transaksi, member percobaan, log) sengaja TIDAK disertakan.
-- Cara pakai: Import file ini SEKALI lewat phpMyAdmin InfinityFree (database masih kosong) - lihat DEPLOY.md bagian 2.4.
-- Login setelah import: pakai email/No HP + password Owner yang SAMA seperti yang dipakai di lokal (hash password ikut dibawa, tidak berubah).


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `aksi` varchar(150) NOT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backup_bukti_pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_bukti_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_file` varchar(255) NOT NULL,
  `cutoff_at` datetime NOT NULL COMMENT 'batas atas created_at transaksi yang disertakan (upload_time <= ini)',
  `jumlah_file` int(11) NOT NULL DEFAULT 0,
  `ukuran_bytes` bigint(20) NOT NULL DEFAULT 0,
  `diarsipkan_at` datetime DEFAULT NULL COMMENT 'kapan file terkait backup ini dihapus lewat Arsip, NULL = belum diarsipkan',
  `dibuat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  CONSTRAINT `backup_bukti_pembayaran_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `booking_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `ukuran_dipilih` varchar(20) DEFAULT NULL,
  `tanggal_ambil` date NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `jam_ambil` time NOT NULL DEFAULT '09:00:00',
  `status` enum('disewa','dikembalikan') NOT NULL DEFAULT 'disewa',
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `idx_stok_lookup` (`item_id`,`status`,`tanggal_ambil`,`tanggal_kembali`),
  CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_booking` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_nama` varchar(100) DEFAULT NULL,
  `guest_hp` varchar(20) DEFAULT NULL,
  `guest_alamat` text DEFAULT NULL,
  `guest_email` varchar(100) DEFAULT NULL,
  `identitas_file` varchar(255) DEFAULT NULL,
  `tanggal_ambil` date NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `jam_ambil` time NOT NULL DEFAULT '09:00:00',
  `jam_kembali` time NOT NULL DEFAULT '09:00:00',
  `metode_pembayaran` enum('cash','qris') DEFAULT NULL,
  `skema_bayar` enum('dp','lunas') DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `catatan_admin` text DEFAULT NULL,
  `status` enum('DRAFT','MENUNGGU_PEMBAYARAN','MENUNGGU_VERIFIKASI','MENUNGGU_KEDATANGAN','RESERVASI_DIKONFIRMASI','BARANG_DISIAPKAN','SIAP_DIAMBIL','SEDANG_DISEWA','PENGEMBALIAN','SELESAI','EXPIRED','DIBATALKAN') NOT NULL DEFAULT 'DRAFT',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_booking` (`kode_booking`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catatan_pelanggan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `catatan_pelanggan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hp` varchar(20) NOT NULL,
  `isi` text NOT NULL,
  `dibuat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hp` (`hp`),
  KEY `catatan_pelanggan_ibfk_1` (`dibuat_oleh`),
  CONSTRAINT `catatan_pelanggan_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `url` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `item_variasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_variasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `ukuran` varchar(20) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_ukuran` (`item_id`,`ukuran`),
  KEY `idx_item_id` (`item_id`),
  CONSTRAINT `item_variasi_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `sub_kategori` varchar(30) DEFAULT NULL,
  `ukuran` varchar(20) DEFAULT NULL,
  `harga_per_malam` decimal(12,2) NOT NULL,
  `deposit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `denda_per_hari` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stok_total` int(11) NOT NULL DEFAULT 0,
  `deskripsi` text DEFAULT NULL,
  `syarat_penyewaan` text DEFAULT NULL,
  `status` enum('aktif','maintenance','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `identifier` varchar(150) NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 1,
  `last_attempt_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `pesan` varchar(255) NOT NULL,
  `link_tujuan` varchar(255) DEFAULT NULL,
  `dibaca` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registration_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_created` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `booking_item_id` int(11) DEFAULT NULL,
  `kondisi` enum('lengkap','kurang','rusak','hilang') DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `denda_terlambat` decimal(12,2) DEFAULT 0.00,
  `biaya_kerusakan` decimal(12,2) DEFAULT 0.00,
  `tanggal_kembali_aktual` datetime DEFAULT NULL,
  `diproses_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `diproses_oleh` (`diproses_oleh`),
  KEY `booking_item_id` (`booking_item_id`),
  CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `returns_ibfk_3` FOREIGN KEY (`booking_item_id`) REFERENCES `booking_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `invoice_no` varchar(30) NOT NULL,
  `jenis` enum('dp','sisa','lunas','denda','tambahan') NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `metode` enum('cash','qris') NOT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `status_verifikasi` enum('menunggu','terverifikasi','ditolak') NOT NULL DEFAULT 'menunggu',
  `diproses_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `booking_id` (`booking_id`),
  KEY `diproses_oleh` (`diproses_oleh`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `no_hp_lama` varchar(20) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('owner','admin','kasir','member') NOT NULL DEFAULT 'member',
  `status_aktif` enum('aktif','cuti','nonaktif') NOT NULL DEFAULT 'aktif',
  `identitas_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `no_hp` (`no_hp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ================= DATA: akun Owner =================
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `nama`, `email`, `no_hp`, `no_hp_lama`, `alamat`, `password_hash`, `role`, `status_aktif`, `identitas_file`, `created_at`, `updated_at`) VALUES (1,'Owner Merimba','merimbaoutdoor.yk@gmail.com','082397937746',NULL,NULL,'$2y$12$swUP4zgMMQY2hPo8L3dq.e.HDywRAEJdngJajqIl7wmmsSb3DEQvm','owner','aktif',NULL,'2026-06-29 18:13:41','2026-07-24 15:25:30');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ================= DATA: pengaturan usaha (settings) =================
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('alamat','Jl. Sorowajan Baru, Tegal Tanda, Banguntapan, Kec. Banguntapan, Yogyakarta, Daerah Istimewa Yogyakarta 55198'),('deskripsi_usaha','Partner setia petualanganmu. Sewa peralatan camping dan hiking dengan mudah dan terpercaya di Yogyakarta.'),('instagram','merimba.yk'),('jam_buka','09:00'),('jam_operasional','10:00 - 22:00'),('jam_tutup','10:00'),('kebijakan_privasi','1. Data yang Kami Kumpulkan\r\nKami mengumpulkan data yang Anda berikan saat mendaftar dan bertransaksi: nama, nomor HP, email, alamat, foto identitas (KTP/SIM), dan bukti pembayaran.\r\n\r\n2. Tujuan Penggunaan Data\r\nData digunakan untuk memproses reservasi, verifikasi identitas penyewa, komunikasi terkait transaksi, serta pencatatan dan pelaporan usaha.\r\n\r\n3. Penyimpanan & Keamanan Data\r\nFoto identitas dan bukti pembayaran disimpan di server dan diblokir dari akses publik langsung. Kata sandi akun disimpan dalam bentuk terenkripsi (hash), bukan teks biasa. Bahkan admin tidak dapat melihat kata sandi Anda.\r\n\r\n4. Berbagi Data dengan Pihak Ketiga\r\nKami tidak membagikan, menjual, atau menyewakan data pribadi Anda kepada pihak ketiga di luar keperluan operasional Merimba Outdoor.\r\n\r\n5. Hak Anda atas Data Pribadi\r\nAnda berhak mengakses dan memperbarui data profil Anda sendiri kapan saja melalui menu Akun Saya. Anda juga berhak menghapus akun Anda secara permanen kapan saja. Lihat bagian \"Penghapusan Akun\" di Syarat & Ketentuan.\r\n\r\n6. Reset Kata Sandi\r\nApabila Anda sudah berhasil masuk ke akun, Anda dapat mengganti kata sandi sendiri melalui menu Pengaturan Akun. Namun, jika Anda lupa kata sandi sehingga tidak dapat masuk ke akun, gunakan fitur \"Lupa Kata Sandi?\" pada halaman login untuk menghubungi Admin melalui WhatsApp. Setelah identitas Anda berhasil diverifikasi, Admin akan mengatur kata sandi baru untuk akun Anda. Demi menjaga keamanan, sistem tidak menyimpan kata sandi dalam bentuk yang dapat dibaca, sehingga Admin tidak dapat melihat kata sandi lama maupun kata sandi yang tersimpan. Seluruh kata sandi hanya disimpan dalam bentuk hash (enkripsi satu arah).\r\n\r\n7. Cookie & Sesi\r\nKami menggunakan sesi (session) browser untuk menjaga status login Anda selama menggunakan layanan. Sesi akan berakhir otomatis saat Anda logout atau setelah periode tidak aktif tertentu.\r\n\r\n8. Retensi Data Transaksi\r\nMeskipun Anda menghapus akun, riwayat transaksi (nama & kontak yang tercatat saat transaksi) tetap kami simpan untuk keperluan pembukuan dan pelaporan usaha, sesuai praktik pencatatan bisnis yang wajar.\r\n\r\n9. Perubahan Kebijakan\r\nKebijakan privasi ini dapat diperbarui sewaktu-waktu mengikuti perkembangan layanan kami. Perubahan berlaku sejak dipublikasikan di halaman ini.\r\n\r\n10. Kontak\r\nJika ada pertanyaan mengenai kebijakan privasi ini, silakan hubungi Admin melalui kontak yang tersedia di halaman Beranda.'),('logo_file',''),('logo_pos_x','0'),('logo_pos_y','0'),('logo_scale','1'),('maps_nama_lokasi','Jl. Sorowajan Baru, Tegal Tanda, Banguntapan, Kec. Banguntapan, Yogyakarta, Daerah Istimewa Yogyakarta 55198'),('maps_url','https://maps.app.goo.gl/qXzMCWVWTCp9kYi3A'),('nama_usaha','Merimba Outdoor'),('no_hp','082397937746'),('pesan_tutup',''),('qris_image',''),('status_toko','buka'),('syarat_ketentuan','1. Penerimaan Ketentuan\r\nDengan mendaftar dan menggunakan layanan Merimba Outdoor, Anda dianggap telah membaca, memahami, dan menyetujui seluruh syarat & ketentuan berikut.\r\n\r\n2. Pendaftaran Akun\r\nAnda wajib mengisi data pendaftaran (nama, nomor HP, email) dengan benar dan terkini. Anda bertanggung jawab penuh menjaga kerahasiaan kata sandi akun Anda. Segala aktivitas yang terjadi melalui akun Anda dianggap sebagai tindakan Anda sendiri.\r\n\r\n3. Reservasi & Penyewaan\r\nReservasi dianggap sah setelah pembayaran (DP atau lunas) diverifikasi oleh admin. Jadwal pengambilan dan pengembalian barang mengikuti tanggal yang disepakati saat booking. Keterlambatan pengembalian dikenakan denda sesuai ketentuan yang berlaku di toko.\r\n\r\n4. Verifikasi Identitas\r\nUntuk keamanan transaksi, setiap penyewa (member maupun tamu) wajib mengunggah foto identitas (KTP/SIM) yang masih berlaku saat proses checkout.\r\n\r\n5. Pembayaran\r\nPembayaran dapat dilakukan tunai langsung di toko atau transfer/QRIS dengan mengunggah bukti pembayaran. Pembayaran via QRIS/transfer akan diverifikasi manual oleh admin sebelum reservasi dikonfirmasi.\r\n\r\n6. Pembatalan & Perubahan Reservasi\r\nPembatalan reservasi dapat diajukan sebelum tanggal pengambilan barang. Perubahan jadwal atau perpanjangan durasi sewa dapat diajukan melalui admin, mengikuti ketersediaan stok.\r\n\r\n7. Tanggung Jawab Penyewa\r\nPenyewa wajib mengembalikan barang dalam kondisi baik sesuai saat diterima. Kerusakan, kehilangan, atau kekurangan barang akan dikenakan biaya penggantian sesuai kondisi yang ditemukan saat proses pengembalian.\r\n\r\n8. Larangan & Pemblokiran Akun\r\nMerimba Outdoor berhak menonaktifkan (memblokir) akun yang terbukti melakukan pelanggaran berulang, seperti keterlambatan pengembalian tanpa konfirmasi atau kerusakan barang yang disengaja.\r\n\r\n9. Lupa Kata Sandi\r\nApabila Anda lupa kata sandi dan tidak dapat masuk ke akun, klik tombol \"Lupa Kata Sandi?\" pada halaman login. Anda akan diarahkan ke WhatsApp Admin dengan pesan permintaan bantuan yang telah terisi otomatis. Setelah identitas Anda berhasil diverifikasi, Admin akan mengatur kata sandi baru untuk akun Anda. Setelah kata sandi baru ditetapkan, kata sandi sebelumnya otomatis tidak dapat digunakan lagi.\r\n\r\n10. Penghapusan Akun\r\nAnda berhak menghapus akun Anda kapan saja melalui menu Akun Saya > Keamanan > Hapus Akun. Penghapusan bersifat permanen dan tidak dapat dibatalkan. Riwayat transaksi tetap disimpan oleh sistem untuk keperluan pembukuan usaha, namun tidak lagi terhubung dengan akun aktif Anda.\r\n\r\n11. Perubahan Ketentuan\r\nMerimba Outdoor dapat memperbarui syarat & ketentuan ini sewaktu-waktu. Perubahan berlaku sejak dipublikasikan di halaman ini.'),('tentang_kami','Merimba Outdoor merupakan penyedia layanan penyewaan perlengkapan camping yang berlokasi di Yogyakarta. Kami berkomitmen menyediakan berbagai kebutuhan kegiatan outdoor dengan mengutamakan kualitas perlengkapan, kemudahan proses penyewaan, serta pelayanan yang profesional. Seluruh perlengkapan yang kami sewakan dipersiapkan agar berada dalam kondisi layak pakai sehingga siap mendukung berbagai aktivitas alam terbuka, mulai dari perjalanan singkat hingga ekspedisi yang membutuhkan perlengkapan yang andal.\r\n\r\nMelalui sistem reservasi online Merimba Outdoor, pelanggan dapat melihat ketersediaan perlengkapan, melakukan reservasi, mengunggah dokumen yang diperlukan, melakukan pembayaran, serta memantau status penyewaan secara lebih mudah, cepat, dan transparan. Sistem ini dirancang untuk memberikan pengalaman penyewaan yang efisien sekaligus membantu pengelolaan inventaris dan transaksi secara lebih tertata.\r\n\r\nKepercayaan pelanggan merupakan prioritas utama bagi kami. Oleh karena itu, kami berupaya menjaga kualitas layanan, keamanan data pribadi, serta transparansi dalam setiap proses penyewaan sesuai dengan kebijakan dan ketentuan yang berlaku. Dengan semangat memberikan pelayanan terbaik, Merimba Outdoor siap menjadi mitra terpercaya dalam memenuhi kebutuhan perlengkapan camping dan mendukung setiap perjalanan serta pengalaman berpetualang Anda.'),('whatsapp','082397937746'),('whatsapp_lupa_sandi','082397937746');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

