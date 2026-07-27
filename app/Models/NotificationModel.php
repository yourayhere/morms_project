<?php

namespace App\Models;

use App\Core\Database;

class NotificationModel
{
    public static function buatUntukSemuaAdmin(string $pesan, ?string $link = null): void
    {
        $db = Database::getConnection();
        $stmtAdmin = $db->prepare('SELECT id FROM users WHERE role IN ("owner", "admin", "kasir") AND status_aktif = "aktif"');
        $stmtAdmin->execute();
        $daftarAdmin = $stmtAdmin->fetchAll();

        $stmtInsert = $db->prepare(
            'INSERT INTO notifications (user_id, pesan, link_tujuan) VALUES (:user_id, :pesan, :link)'
        );

        foreach ($daftarAdmin as $admin) {
            $stmtInsert->execute([
                'user_id' => $admin['id'],
                'pesan' => $pesan,
                'link' => $link,
            ]);
        }
    }

    public static function getByUserId(int $userId, int $limit = 8): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getJumlahBelumDibaca(int $userId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND dibaca = 0');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function tandaiSemuaDibaca(int $userId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE notifications SET dibaca = 1 WHERE user_id = :user_id AND dibaca = 0');
        $stmt->execute(['user_id' => $userId]);
    }
}