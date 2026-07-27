<?php

/**
 * CLI script: tandai booking yang belum dibayar dalam 60 menit sebagai EXPIRED.
 * Jalankan berkala lewat Windows Task Scheduler, contoh:
 *
 *   Program/script : C:\xampp\php\php.exe
 *   Argumen        : C:\xampp\htdocs\morms\scripts\expire-bookings.php
 *   Jadwal         : setiap 5-10 menit (supaya batas 60 menit tidak molor
 *                    terlalu jauh - dashboard-admin.php juga menjalankan
 *                    pengecekan yang sama sebagai jaring pengaman tambahan
 *                    setiap admin membuka dashboard)
 *
 * Bisa juga dijalankan manual dari terminal:
 *   php scripts/expire-bookings.php
 */

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/BookingModel.php';

use App\Models\BookingModel;

$jumlah = BookingModel::expireBookingBelumDibayar(60);

echo date('Y-m-d H:i:s') . " - {$jumlah} booking ditandai EXPIRED." . PHP_EOL;
