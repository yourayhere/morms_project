<?php

namespace App\Models;

use App\Core\Database;

class SettingModel
{
    public static function get(string $key): string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);
        $hasil = $stmt->fetchColumn();
        return $hasil !== false ? (string) $hasil : '';
    }

    public static function set(string $key, string $value): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = :value2'
        );
        $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
    }

    public static function getAll(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT setting_key, setting_value FROM settings');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $hasil = [];
        foreach ($rows as $row) {
            $hasil[$row['setting_key']] = $row['setting_value'];
        }
        return $hasil;
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            self::set($key, (string) $value);
        }
    }
}