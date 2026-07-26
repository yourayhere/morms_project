<?php

use App\Core\Database;

function catat_aktivitas(?int $userId, string $aksi, string $detail = ''): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'INSERT INTO activity_log (user_id, aksi, detail, ip_address) VALUES (:user_id, :aksi, :detail, :ip)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'aksi' => $aksi,
        'detail' => $detail,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
}