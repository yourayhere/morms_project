# MORMS — Merimba Outdoor Rental Management System

Sistem manajemen penyewaan alat outdoor (camping & hiking) berbasis web, dibangun dari nol dengan **PHP native 8.2 + PDO** (tanpa framework) untuk studi kasus bisnis rental peralatan.

Repo ini dipublikasikan sebagai **portofolio** — menunjukkan cara sistem ini dirancang dan dibangun end-to-end, mulai dari alur pemesanan customer sampai manajemen inventaris & tim admin. Kredensial, data pelanggan, dan detail konfigurasi produksi **tidak** disertakan (lihat bagian [Catatan Keamanan Repo](#catatan-keamanan-repo) di bawah).

---

## Fitur Utama

**Customer (web publik)**
- Katalog barang dengan cek ketersediaan stok real-time (termasuk stok per ukuran untuk pakaian outdoor)
- Reservasi online sebagai Member atau Tamu, lengkap dengan upload identitas & bukti pembayaran
- Tracking status booking lewat kode booking + nomor HP (tanpa perlu login)
- Akun member: riwayat transaksi, ubah profil, ubah password

**Admin / Owner**
- Dashboard, manajemen inventaris (CRUD barang, varian ukuran, multi-foto)
- Verifikasi reservasi online & pembayaran (cash/QRIS)
- Kasir walk-in dengan deteksi otomatis apakah nomor HP customer sudah terdaftar sebagai member
- Manajemen pengembalian barang (kondisi, denda keterlambatan, biaya kerusakan)
- Manajemen tim (Owner & Admin Operasional) dengan pembatasan hak akses berjenjang
- Laporan & backup data

## Tech Stack

- **Backend**: PHP 8.2 native, pola `App\Core` / `App\Models` sebagai pengganti MVC penuh
- **Database**: MySQL/MariaDB lewat PDO (prepared statements di seluruh query)
- **Frontend**: HTML/CSS/JS vanilla, tanpa build step
- **Auth**: Session PHP native dengan role-based access control (`owner`, `admin`, `member`)

## Praktik Keamanan yang Diterapkan

Beberapa hal yang sengaja dibangun sebagai latihan penerapan security best practice:

- Seluruh query database memakai **prepared statement** (PDO) — tidak ada input user yang disambung langsung ke SQL
- Input disaring saat masuk, output di-escape lagi saat ditampilkan (defense in depth terhadap XSS)
- **CSRF token** di semua form yang mengubah data, dibandingkan dengan `hash_equals`
- Password di-hash dengan **bcrypt**, verifikasi memakai dummy-hash agar waktu respons tidak membocorkan apakah akun ada
- Upload file divalidasi dari **isi file asli** (`finfo`/`getimagesize`), bukan dari ekstensi atau `Content-Type` yang dikirim browser; nama file disimpan dalam bentuk acak
- ID yang bisa ditebak (mis. nomor booking) tidak dipakai sebagai kunci akses — dikombinasikan dengan data kepemilikan (IDOR-safe)
- Rate limiting pada percobaan login & registrasi
- Locking database (`SELECT ... FOR UPDATE`) untuk mencegah oversell stok saat checkout bersamaan
- Konfigurasi (kredensial database, dsb.) dibaca lewat **environment variable**, dengan fallback placeholder di kode — tidak ada kredensial asli yang pernah ditulis di repo (lihat `config/database.php`)

## Struktur Singkat

```
app/
  Core/       -> Auth, Session, Database, Cart, dll.
  Models/     -> logic akses data per entitas (Item, Booking, Customer, dst.)
  Helpers/    -> util bersama (upload, format, laporan, dsb.)
aksi/         -> endpoint pemroses form (POST) per aksi
config/       -> konfigurasi env & database (tanpa kredensial asli)
database/     -> skema tabel (schema.sql, tanpa data produksi)
assets/       -> css, js, ikon
*.php (root)  -> halaman-halaman aplikasi
```

## Database

Skema lengkap ada di [`database/schema.sql`](database/schema.sql) — berisi struktur tabel saja (tanpa data pengguna/transaksi asli). Entitas utama: `users`, `items` + `item_variasi` (stok per ukuran) + `item_images`, `bookings` + `booking_items`, `transactions`, `returns`, `settings`, `activity_log`.

## Catatan Keamanan Repo

Repo ini murni untuk portofolio/showcase kode, **bukan** salinan environment produksi:

- Tidak ada file `.env` atau kredensial database asli yang pernah disertakan — `config/database.php` hanya berisi placeholder dan pembacaan dari environment variable.
- Data identitas (KTP/SIM) dan bukti pembayaran pelanggan tidak pernah masuk ke repo (folder `storage/` diabaikan lewat `.gitignore`, dan diblokir dari akses publik lewat `.htaccess` di server).
- `database/schema.sql` hanya memuat struktur tabel; tidak ada data akun atau transaksi nyata.
- Nama domain, akses admin, dan panduan deploy spesifik hosting yang digunakan untuk instance produksi sengaja tidak dipublikasikan di sini.

---

Dibuat oleh Uray sebagai proyek pembelajaran full-stack development & penerapan keamanan aplikasi web.
