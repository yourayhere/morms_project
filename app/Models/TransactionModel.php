<?php

namespace App\Models;

use App\Core\Database;

class TransactionModel
{
    public static function buat(array $data): int
    {
        $db = Database::getConnection();
        $invoiceNo = generate_invoice_no($db);

        $stmt = $db->prepare(
            'INSERT INTO transactions (booking_id, invoice_no, jenis, nominal, metode, bukti_bayar, status_verifikasi)
             VALUES (:booking_id, :invoice_no, :jenis, :nominal, :metode, :bukti, :status_verifikasi)'
        );
        $stmt->execute([
            'booking_id' => $data['booking_id'],
            'invoice_no' => $invoiceNo,
            'jenis' => $data['jenis'],
            'nominal' => $data['nominal'],
            'metode' => $data['metode'],
            'bukti' => $data['bukti_bayar'] ?? null,
            'status_verifikasi' => $data['status_verifikasi'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function getByBookingId(int $bookingId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM transactions WHERE booking_id = :id ORDER BY created_at ASC');
        $stmt->execute(['id' => $bookingId]);
        return $stmt->fetchAll();
    }

    public static function getInvoiceUtama(int $bookingId): ?array
    {
        $daftar = self::getByBookingId($bookingId);
        return $daftar[0] ?? null;
    }

    public static function verifikasi(int $transactionId, string $statusBaru, int $adminId): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'UPDATE transactions SET status_verifikasi = :status, diproses_oleh = :admin_id WHERE id = :id'
    );
    $stmt->execute([
        'status' => $statusBaru,
        'admin_id' => $adminId,
        'id' => $transactionId,
    ]);
}

public static function getById(int $id): ?array
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT * FROM transactions WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $transaksi = $stmt->fetch();
    return $transaksi ?: null;
}
}