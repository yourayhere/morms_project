<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/Cart.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/upload.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Helpers/toko.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\Cart;
use App\Core\Database;
use App\Models\BookingModel;
use App\Models\ItemModel;

Session::start();

if (empty(Cart::getAll())) {
    header('Location: ../keranjang.php');
    exit;
}

// Jaga-jaga (defense in depth) kalau toko baru saja ditutup owner setelah
// customer sudah membuka form checkout - gerbang utamanya ada di
// checkout.php (redirect sebelum form ditampilkan) dan tambah-keranjang.php.
if (toko_sedang_tutup()) {
    header('Location: ../keranjang.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../checkout.php?error=token');
    exit;
}

$mode = $_POST['mode'] ?? '';
$periode = Cart::getEnvelope();

if (!isset($_FILES['identitas']) || $_FILES['identitas']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../checkout.php?mode=' . $mode . '&error=identitas');
    exit;
}

$hasilUpload = validasi_dan_simpan_identitas($_FILES['identitas']);
if (!$hasilUpload['sukses']) {
    header('Location: ../checkout.php?mode=' . $mode . '&error=identitas');
    exit;
}

$kodeBooking = generate_kode_booking();

// Data guest divalidasi sebelum menyentuh database sama sekali.
if ($mode === 'guest') {
    $nama = clean_input($_POST['nama'] ?? '');
    $hp = clean_input($_POST['hp'] ?? '');
    $alamat = clean_input($_POST['alamat'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $catatan = clean_input($_POST['catatan'] ?? '');

    if ($nama === '' || $hp === '' || $alamat === '') {
        header('Location: ../checkout.php?mode=guest&error=data');
        exit;
    }

    if (!validasi_no_hp($hp) || ($email !== '' && !validasi_email($email))) {
        header('Location: ../checkout.php?mode=guest&error=format');
        exit;
    }

    // Cegah orang yang akunnya diblokir (status_aktif = nonaktif) sekadar
    // lanjut booking lewat mode Tamu memakai nomor HP yang sama.
    $db = Database::getConnection();
    $stmtBlokir = $db->prepare(
        "SELECT id FROM users WHERE no_hp = :hp AND role = 'member' AND status_aktif = 'nonaktif'"
    );
    $stmtBlokir->execute(['hp' => $hp]);
    if ($stmtBlokir->fetch()) {
        header('Location: ../checkout.php?mode=guest&error=diblokir');
        exit;
    }
} else {
    if (!Auth::isLoggedIn() || Auth::role() !== 'member') {
        header('Location: ../login.php?redirect=checkout');
        exit;
    }

    Auth::requireActiveMember('../login.php');

    $catatan = clean_input($_POST['catatan'] ?? '');
}

// Booking + item-item di dalamnya harus tersimpan sekaligus (atomik). Jika salah
// satu insert item gagal di tengah jalan, seluruh booking dibatalkan (rollback)
// alih-alih tersimpan setengah-jadi.
$db = Database::getConnection();
$db->beginTransaction();

try {
    if ($mode === 'guest') {
        $bookingId = BookingModel::buatBookingGuest([
            'kode_booking' => $kodeBooking,
            'nama' => $nama,
            'hp' => $hp,
            'alamat' => $alamat,
            'email' => $email,
            'identitas_file' => $hasilUpload['nama_file'],
            'tanggal_ambil' => $periode['ambil'],
            'tanggal_kembali' => $periode['kembali'],
            'jam_ambil' => $periode['jam'],
            'catatan' => $catatan,
        ]);
    } else {
        $bookingId = BookingModel::buatBookingMember([
            'kode_booking' => $kodeBooking,
            'user_id' => Auth::id(),
            'identitas_file' => $hasilUpload['nama_file'],
            'tanggal_ambil' => $periode['ambil'],
            'tanggal_kembali' => $periode['kembali'],
            'jam_ambil' => $periode['jam'],
            'catatan' => $catatan,
        ]);
    }

    foreach (Cart::getAll() as $barang) {
        // Kunci baris item (SELECT ... FOR UPDATE) dan validasi ulang stok di
        // dalam transaction, supaya dua checkout bersamaan untuk barang &
        // periode yang sama tidak bisa lolos validasi berbarengan (anti oversell).
        // Barang Pakaian Outdoor dikunci & divalidasi per ukuran (item_variasi),
        // bukan stok_total, karena stoknya dipecah per ukuran.
        $itemKeranjang = ItemModel::getById($barang['item_id']);
        $ukuranBarang = $barang['ukuran'] ?? '';

        if ($itemKeranjang && $itemKeranjang['kategori'] === 'pakaian_outdoor') {
            // Item ini ditambahkan ke keranjang sebelum ukuran dipilih (mis. data
            // sesi lama). Tolak checkout alih-alih diam-diam memakai stok total.
            if ($ukuranBarang === '') {
                throw new \RuntimeException('UKURAN_KOSONG');
            }
            $sisaStok = ItemModel::getSisaStokVarianUntukLocking($barang['item_id'], $ukuranBarang, $barang['tanggal_ambil'], $barang['tanggal_kembali']);
        } else {
            $sisaStok = ItemModel::getSisaStokUntukLocking($barang['item_id'], $barang['tanggal_ambil'], $barang['tanggal_kembali']);
        }
        if ($sisaStok < $barang['jumlah']) {
            throw new \RuntimeException('STOK_HABIS');
        }
        BookingModel::tambahItemBooking($bookingId, $barang['item_id'], $barang['jumlah'], $barang['subtotal'], $ukuranBarang, $barang['tanggal_ambil'], $barang['tanggal_kembali'], $barang['jam_ambil'] ?? '09:00');
    }

    // Isi jam_kembali amplop (jam dari barang dengan tanggal_kembali paling
    // akhir) - tidak diisi lewat INSERT bookings di atas karena baru bisa
    // dihitung setelah semua booking_items tersimpan.
    BookingModel::pulihkanEnvelope($bookingId);

    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    error_log('[morms] Booking checkout failed, rolled back: ' . $e->getMessage());
    $kodeError = $e->getMessage() === 'STOK_HABIS' ? 'stok' : ($e->getMessage() === 'UKURAN_KOSONG' ? 'ukuran' : 'sistem');
    header('Location: ../checkout.php?mode=' . $mode . '&error=' . $kodeError);
    exit;
}

// Set session and clear cart immediately so user can continue even if post-actions fail
Session::set('booking_id_proses', $bookingId);
Cart::clear();

// Run non-critical post actions (logging, notifications) but don't let them break user flow
try {
    catat_aktivitas(Auth::id(), 'buat_booking', 'Booking dibuat dengan kode ' . $kodeBooking);
    require_once __DIR__ . '/../app/Models/NotificationModel.php';
    \App\Models\NotificationModel::buatUntukSemuaAdmin(
        'Booking baru ' . $kodeBooking . ' telah dibuat.',
        'reservasi-online.php?booking_id=' . $bookingId
    );
} catch (\Throwable $e) {
    error_log('[morms] Post-booking action failed: ' . $e->getMessage());
}

header('Location: ../review.php');
exit;