<?php

namespace App\Core;

use PDO;
use PDOException;

// Database.php adalah require_once PERTAMA di hampir semua halaman/aksi,
// sehingga tempat paling andal untuk memuat env.php sekali di awal request —
// supaya display_errors otomatis mati di production (mencegah path file,
// query, atau detail internal lain bocor ke browser pengunjung).
require_once __DIR__ . '/../../config/env.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die('Koneksi database gagal. Periksa konfigurasi pada config/database.php.');
            }
        }

        return self::$instance;
    }
}