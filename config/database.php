<?php

// Kredensial bisa di-override lewat environment variable (DB_HOST, DB_PORT,
// DB_NAME, DB_USER, DB_PASS) tanpa mengubah file ini — cara aman untuk
// deployment production. Kalau env var tidak diset, jatuh ke nilai di bawah
// ini — ISI DENGAN KREDENSIAL DARI CONTROL PANEL INFINITYFREE (lihat
// DEPLOY.md bagian 2.4) sebelum aplikasi dijalankan di hosting.
return [
    'host' => getenv('DB_HOST') ?: 'ISI_HOSTNAME_MYSQL_DARI_CONTROL_PANEL',
    'port' => getenv('DB_PORT') ?: '3306',
    'dbname' => getenv('DB_NAME') ?: 'ISI_NAMA_DATABASE_DARI_CONTROL_PANEL',
    'username' => getenv('DB_USER') ?: 'ISI_USERNAME_MYSQL_DARI_CONTROL_PANEL',
    'password' => getenv('DB_PASS') ?: 'ISI_PASSWORD_MYSQL_DARI_CONTROL_PANEL',
    'charset' => 'utf8mb4',
];
