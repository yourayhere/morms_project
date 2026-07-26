<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/CartKasir.php';
require_once __DIR__ . '/../app/Helpers/security.php';
require_once __DIR__ . '/../app/Helpers/upload.php';
require_once __DIR__ . '/../app/Helpers/logger.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';
require_once __DIR__ . '/../app/Models/TransactionModel.php';
require_once __DIR__ . '/../app/Models/ItemModel.php';

use App\Core\Session;
use App\Core\Auth;
use App\Core\CartKasir;
use App\Core\Database;
use App\Models\BookingModel;
use App\Models\TransactionModel;
use App\Models\ItemModel;

Session::start();
Auth::requireRole(['owner', 'admin', 'kasir']);

$keranjang = CartKasir::getAll();

if (empty($keranjang)) {
    header('Location: ../kasir.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../checkout-kasir.php?error=token');
    exit;
}

$nama = clean_input($_POST['nama'] ?? '');
$hp = clean_input($_POST['hp'] ?? '');
$alamat = clean_input($_POST['alamat'] ?? '');
$skema = $_POST['skema'] ?? 'dp';
$metode = $_POST['metode'] ?? 'cash';

// Nominal dihitung ulang dari keranjang kasir di server, bukan dari hidden
// field $_POST yang bisa dimanipulasi (mis. oleh kasir untuk selisih uang tunai).
$totalSewa = CartKasir::getTotal();
$dpMinimal = $totalSewa * 0.5;

if ($nama === '' || $hp === '') {
    header('Location: ../checkout-kasir.php?error=data');
    exit;
}

if (!validasi_no_hp($hp)) {
    header('Location: ../checkout-kasir.php?error=format');
    exit;
}

// Jangan percaya begitu saja member_user_id dari form — validasi ulang di
// server bahwa id tersebut benar member aktif dengan nomor HP yang PERSIS
// sama dengan yang diketik kasir, supaya kasir tidak bisa menautkan booking
// tamu ke sembarang akun member lewat manipulasi request.
$memberUserId = null;
$memberUserIdInput = (int) ($_POST['member_user_id'] ?? 0);
if ($memberUserIdInput > 0) {
    $dbCekMember = Database::getConnection();
    $stmtCekMember = $dbCekMember->prepare('SELECT id FROM users WHERE id = :id AND no_hp = :hp AND role = "member"');
    $stmtCekMember->execute(['id' => $memberUserIdInput, 'hp' => $hp]);
    if ($stmtCekMember->fetch()) {
        $memberUserId = $memberUserIdInput;
    }
}

$lewatiIdentitas = ($_POST['lewati_identitas'] ?? '') === '1';
$namaFileIdentitas = null;

if (!$lewatiIdentitas) {
    if (!isset($_FILES['identitas']) || $_FILES['identitas']['error'] !== UPLOAD_ERR_OK) {
        header('Location: ../checkout-kasir.php?error=identitas');
        exit;
    }

    $hasilUpload = validasi_dan_simpan_identitas($_FILES['identitas']);
    if (!$hasilUpload['sukses']) {
        header('Location: ../checkout-kasir.php?error=identitas');
        exit;
    }

    $namaFileIdentitas = $hasilUpload['nama_file'];
}

$periode = CartKasir::getEnvelope();
$kodeBooking = generate_kode_booking();

$nominalBayar = $skema === 'lunas' ? $totalSewa : $dpMinimal;
$jenisTransaksi = $skema === 'lunas' ? 'lunas' : 'dp';

// Booking, item-itemnya, dan transaksi pembayaran harus tersimpan sekaligus
// (atomik) supaya tidak ada transaksi kasir yang tersimpan setengah-jadi.
$db = Database::getConnection();
$db->beginTransaction();

try {
    $bookingId = BookingModel::buatBookingKasir([
        'kode_booking' => $kodeBooking,
        'user_id' => $memberUserId,
        'nama' => $nama,
        'hp' => $hp,
        'alamat' => $alamat,
        'identitas_file' => $namaFileIdentitas,
        'tanggal_ambil' => $periode['ambil'],
        'tanggal_kembali' => $periode['kembali'],
        'jam_ambil' => $periode['jam'],
    ]);

    foreach ($keranjang as $barang) {
        // Kunci baris item & validasi ulang stok di dalam transaction (anti oversell).
        // Barang Pakaian Outdoor dikunci & divalidasi per ukuran (item_variasi).
        $itemKeranjang = ItemModel::getById($barang['item_id']);
        $ukuranBarang = $barang['ukuran'] ?? '';

        if ($itemKeranjang && $itemKeranjang['kategori'] === 'pakaian_outdoor') {
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

    $transactionId = TransactionModel::buat([
        'booking_id' => $bookingId,
        'jenis' => $jenisTransaksi,
        'nominal' => $nominalBayar,
        'metode' => $metode,
        'bukti_bayar' => null,
        'status_verifikasi' => 'terverifikasi',
    ]);

    TransactionModel::verifikasi($transactionId, 'terverifikasi', (int) Auth::id());

    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    error_log('[morms] Transaksi kasir gagal, rolled back: ' . $e->getMessage());
    $kodeError = $e->getMessage() === 'STOK_HABIS' ? 'stok' : ($e->getMessage() === 'UKURAN_KOSONG' ? 'ukuran' : 'sistem');
    header('Location: ../checkout-kasir.php?error=' . $kodeError);
    exit;
}

catat_aktivitas((int) Auth::id(), 'transaksi_kasir', 'Transaksi kasir untuk booking ' . $kodeBooking . ' oleh ' . Session::get('user_nama'));

CartKasir::clear();

header('Location: ../struk-kasir.php?booking_id=' . $bookingId);
exit;