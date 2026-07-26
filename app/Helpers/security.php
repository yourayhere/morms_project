<?php

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function clean_input(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function generate_kode_booking(): string
{
    return 'MRB-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function generate_invoice_no(\PDO $db): string
{
    $tanggalHariIni = date('Ymd');
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM transactions WHERE invoice_no LIKE :pola'
    );
    $stmt->execute(['pola' => 'INV-' . $tanggalHariIni . '-%']);
    $jumlah = (int) $stmt->fetchColumn();
    return 'INV-' . $tanggalHariIni . '-' . str_pad((string) ($jumlah + 1), 3, '0', STR_PAD_LEFT);
}

function sanitize_filename(string $nama): string
{
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($nama));
}

function is_valid_date(string $tanggal): bool
{
    $d = \DateTime::createFromFormat('Y-m-d', $tanggal);
    return $d && $d->format('Y-m-d') === $tanggal;
}

// Ubah berbagai varian penulisan nomor HP Indonesia (08123456789,
// +62 812-3456-789, +628123456789, 628123456789, dst - spasi dan tanda
// hubung di posisi mana pun diperbolehkan) menjadi satu format kanonik
// "08xxxxxxxxxx". Dipakai sebelum validasi, penyimpanan ke database, dan
// pengecekan duplikat, supaya nomor yang sama selalu tersimpan dan
// tercocokkan secara konsisten apa pun format yang diketik pengguna.
function normalisasi_no_hp(string $noHp): string
{
    $bersih = preg_replace('/[^0-9+]/', '', trim($noHp));
    $bersih = ltrim($bersih, '+');
    if (str_starts_with($bersih, '62')) {
        $bersih = '0' . substr($bersih, 2);
    }
    return $bersih;
}

// Nomor HP Indonesia: setelah dinormalisasi, harus diawali 0, diikuti
// prefix seluler "8", lalu 8-11 digit lagi (total 10-13 digit).
function validasi_no_hp(string $noHp): bool
{
    return (bool) preg_match('/^08[1-9][0-9]{7,10}$/', normalisasi_no_hp($noHp));
}

// Domain email publik/besar yang sudah pasti sah - selalu diterima tanpa
// perlu cek DNS lagi.
const DOMAIN_EMAIL_TERPERCAYA = [
    'gmail.com', 'yahoo.com', 'yahoo.co.id', 'ymail.com',
    'outlook.com', 'outlook.co.id', 'hotmail.com', 'live.com', 'msn.com',
    'icloud.com', 'me.com', 'proton.me', 'protonmail.com',
    'aol.com', 'zoho.com',
];

// Domain email sekali-pakai/sementara yang umum dipakai untuk menghindari
// verifikasi asli - selalu ditolak.
const DOMAIN_EMAIL_SEKALI_PAKAI = [
    'mailinator.com', 'tempmail.com', 'temp-mail.org', 'guerrillamail.com',
    '10minutemail.com', 'yopmail.com', 'throwawaymail.com', 'trashmail.com',
    'sharklasers.com', 'dispostable.com', 'getnada.com', 'moakt.com',
    'discard.email', 'fakeinbox.com', 'maildrop.cc', 'mintemail.com',
];

// Validasi email: format harus benar (RFC), lalu domainnya harus benar-benar
// bisa menerima email (dicek lewat DNS record MX/A) dan bukan domain
// email sekali-pakai. Ini menyaring domain karangan/asal ketik (mis.
// "@manusia.com" kalau tidak benar-benar terdaftar) tanpa membatasi hanya ke
// penyedia besar - domain perusahaan resmi mana pun tetap diterima selama
// DNS-nya benar-benar dikonfigurasi untuk menerima email.
function validasi_email(string $email): bool
{
    $email = trim($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if ($domain === '') {
        return false;
    }

    if (in_array($domain, DOMAIN_EMAIL_SEKALI_PAKAI, true)) {
        return false;
    }

    if (in_array($domain, DOMAIN_EMAIL_TERPERCAYA, true)) {
        return true;
    }

    if (function_exists('checkdnsrr')) {
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }

    return true;
}