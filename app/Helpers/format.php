<?php

function format_rupiah(float $angka): string
{
    return 'Rp' . number_format($angka, 0, ',', '.');
}

function format_ukuran_file(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024 * 1024), 2, ',', '.') . ' GB';
    }
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' byte';
}

// Format durasi (dari sekarang ke suatu waktu di masa depan) jadi teks
// ringkas seperti "5 hari 3 jam" atau "12 jam 40 menit" - dipakai untuk
// pengingat jadwal backup bukti pembayaran.
function format_durasi_singkat(int $totalDetik): string
{
    if ($totalDetik <= 0) {
        return '0 menit';
    }

    $hari = intdiv($totalDetik, 86400);
    $jam = intdiv($totalDetik % 86400, 3600);
    $menit = intdiv($totalDetik % 3600, 60);

    if ($hari > 0) {
        return $jam > 0 ? "{$hari} hari {$jam} jam" : "{$hari} hari";
    }
    if ($jam > 0) {
        return $menit > 0 ? "{$jam} jam {$menit} menit" : "{$jam} jam";
    }
    return "{$menit} menit";
}

function format_tanggal_indo(string $tanggal): string
{
    $bulanList = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $waktu = strtotime($tanggal);
    return date('j', $waktu) . ' ' . $bulanList[(int) date('n', $waktu)] . ' ' . date('Y', $waktu);
}

function format_jam(string $jam): string
{
    return substr($jam, 0, 5);
}

// Format jam 24-jam ("08:00", "20:00") menjadi format 12-jam formal dengan
// AM/PM ("08:00 AM", "08:00 PM"), sesuai konvensi yang dipakai di halaman
// pemesanan (input Jam Ambil).
function format_jam_ampm(string $jamHM): string
{
    if ($jamHM === '') {
        return '';
    }
    $waktu = DateTime::createFromFormat('H:i', substr($jamHM, 0, 5));
    return $waktu ? $waktu->format('h:i A') : $jamHM;
}

// Gabungan jam buka & tutup jadi satu label formal, mis. "08:00 AM - 08:00 PM".
// Mengembalikan string kosong kalau salah satu jam belum diatur admin.
function format_jam_operasional(string $jamBuka, string $jamTutup): string
{
    if ($jamBuka === '' || $jamTutup === '') {
        return '';
    }
    return format_jam_ampm($jamBuka) . ' - ' . format_jam_ampm($jamTutup);
}

// Apakah waktu saat ini (jam berjalan di server) berada dalam rentang jam
// operasional toko. Kalau jam buka/tutup belum diatur admin, dianggap selalu
// dalam jam operasional (fitur pengecekan tidak diaktifkan).
function sedang_jam_operasional(string $jamBuka, string $jamTutup, ?string $waktuSekarang = null): bool
{
    if ($jamBuka === '' || $jamTutup === '') {
        return true;
    }
    $sekarang = $waktuSekarang ?? date('H:i');
    if ($jamBuka <= $jamTutup) {
        return $sekarang >= $jamBuka && $sekarang <= $jamTutup;
    }
    // Rentang melewati tengah malam (mis. buka 18:00, tutup 02:00).
    return $sekarang >= $jamBuka || $sekarang <= $jamTutup;
}

// Ubah nomor HP format lokal (08xx) jadi format internasional (628xx) yang
// dipakai link wa.me/api.whatsapp.com. Nomor yang sudah 62xx/+62xx dibiarkan.
function format_nomor_wa(string $noHp): string
{
    $bersih = preg_replace('/[^0-9]/', '', $noHp);
    if ($bersih === '' ) {
        return '';
    }
    return str_starts_with($bersih, '0') ? '62' . substr($bersih, 1) : $bersih;
}

// Sistem sewa per malam: jam kembali otomatis mengikuti jam ambil (jam yang
// sama, hanya beda tanggal), jadi tidak perlu kolom jam_kembali terpisah.
function format_periode_sewa(string $tanggalAmbil, string $tanggalKembali, ?string $jamAmbil = null, ?string $jamKembali = null): string
{
    $jamKembali = $jamKembali ?? $jamAmbil;
    $textAmbil = $jamAmbil ? ' pukul ' . format_jam($jamAmbil) : '';
    $textKembali = $jamKembali ? ' pukul ' . format_jam($jamKembali) : '';
    return format_tanggal_indo($tanggalAmbil) . $textAmbil . ' hingga ' . format_tanggal_indo($tanggalKembali) . $textKembali;
}

// "Kurang" dan "Hilang" digabung jadi satu pilihan "Kurang / Hilang" di form
// pengembalian. Enum database & histori lama tetap menyimpan nilai aslinya
// (kurang/hilang) supaya data historis tidak berubah, tapi label yang
// ditampilkan ke pengguna selalu disatukan lewat helper ini.
function label_kondisi_pengembalian(string $kondisi): string
{
    $label = [
        'lengkap' => 'Lengkap',
        'kurang' => 'Kurang / Hilang',
        'hilang' => 'Kurang / Hilang',
        'rusak' => 'Rusak',
    ];
    return $label[$kondisi] ?? ucfirst($kondisi);
}

function label_status_verifikasi(string $status): string
{
    $label = [
        'menunggu' => 'Menunggu Verifikasi',
        'terverifikasi' => 'Terverifikasi',
        'ditolak' => 'Ditolak',
    ];
    return $label[$status] ?? $status;
}

// Lama bergabung dalam bentuk teks ringkas dan mudah dibaca: "3 hari",
// "2 minggu", "1 bulan", "6 bulan", "1 tahun 3 bulan", "2 tahun", dst.
function format_lama_bergabung(string $tanggalGabung): string
{
    // Dibandingkan per tanggal kalender (jam direset ke 00:00), bukan
    // timestamp persis - supaya hasilnya selalu konsisten dengan
    // milestone_bergabung() dan tidak "kurang satu" hanya karena jam
    // bergabungnya lebih siang dari jam sekarang pada hari yang sama.
    $gabung = new DateTime($tanggalGabung);
    $gabung->setTime(0, 0, 0);
    $sekarang = new DateTime();
    $sekarang->setTime(0, 0, 0);
    $selisih = $gabung->diff($sekarang);

    if ($selisih->y >= 1) {
        $teks = $selisih->y . ' tahun';
        if ($selisih->m > 0) {
            $teks .= ' ' . $selisih->m . ' bulan';
        }
        return $teks;
    }

    if ($selisih->m >= 1) {
        return $selisih->m . ' bulan';
    }

    if ($selisih->d >= 7) {
        return intdiv($selisih->d, 7) . ' minggu';
    }

    if ($selisih->d >= 1) {
        return $selisih->d . ' hari';
    }

    return 'hari ini';
}

// Deteksi apakah HARI INI persis jatuh pada tonggak keanggotaan (3 hari,
// 1 minggu, 1 bulan, 6 bulan, 1/2/3/... tahun). Mengembalikan null kalau
// bukan tonggak, atau teks tonggaknya kalau iya - dipakai untuk menampilkan
// ucapan selamat hanya pada hari yang tepat, bukan setiap hari member login.
function milestone_bergabung(string $tanggalGabung): ?string
{
    $gabung = new DateTime($tanggalGabung);
    $gabung->setTime(0, 0, 0);
    $sekarang = new DateTime();
    $sekarang->setTime(0, 0, 0);
    $selisih = $gabung->diff($sekarang);

    if ($selisih->y === 0 && $selisih->m === 0 && $selisih->d === 3) {
        return '3 hari';
    }
    if ($selisih->y === 0 && $selisih->m === 0 && $selisih->d === 7) {
        return '1 minggu';
    }
    if ($selisih->y === 0 && $selisih->m === 1 && $selisih->d === 0) {
        return '1 bulan';
    }
    if ($selisih->y === 0 && $selisih->m === 6 && $selisih->d === 0) {
        return '6 bulan';
    }
    if ($selisih->y >= 1 && $selisih->m === 0 && $selisih->d === 0) {
        return $selisih->y . ' tahun';
    }

    return null;
}

function label_kategori(string $kategori): string
{
    $label = [
        'tidur' => 'Perlengkapan Tidur',
        'hiking' => 'Perlengkapan Hiking',
        'masak' => 'Peralatan Masak',
        'aksesoris' => 'Aksesoris',
        'guide' => 'Jasa Pemandu',
        'pakaian_outdoor' => 'Pakaian Outdoor',
    ];
    return $label[$kategori] ?? $kategori;
}

function label_sub_kategori_pakaian(string $subKategori): string
{
    $label = [
        'atasan' => 'Atasan',
        'bawahan' => 'Bawahan',
        'alas_kaki' => 'Alas Kaki',
    ];
    return $label[$subKategori] ?? $subKategori;
}