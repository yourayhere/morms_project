<?php

namespace App\Models;

use App\Core\Database;

class TeamModel
{
    public static function getSemuaAnggota(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, nama, email, no_hp, role, status_aktif, created_at
             FROM users WHERE role IN ("owner", "admin")
             ORDER BY FIELD(role, "owner", "admin"), nama ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT id, nama, email, no_hp, role, status_aktif FROM users WHERE id = :id AND role IN ("owner", "admin")'
        );
        $stmt->execute(['id' => $id]);
        $hasil = $stmt->fetch();
        return $hasil ?: null;
    }

    public static function tambah(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO users (nama, email, no_hp, password_hash, role, status_aktif)
             VALUES (:nama, :email, :no_hp, :hash, :role, "aktif")'
        );
        $stmt->execute([
            'nama'  => $data['nama'],
            'email' => $data['email'] ?: null,
            'no_hp' => $data['no_hp'],
            'hash'  => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'role'  => $data['role'],
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE users SET nama = :nama, email = :email, no_hp = :no_hp, role = :role, status_aktif = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'nama'   => $data['nama'],
            'email'  => $data['email'] ?: null,
            'no_hp'  => $data['no_hp'],
            'role'   => $data['role'],
            'status' => $data['status_aktif'],
            'id'     => $id,
        ]);
    }

    public static function gantiPassword(int $id, string $passwordBaru): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            'hash' => password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 12]),
            'id'   => $id,
        ]);
    }

    public static function hapus(int $id): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id AND role IN ("owner", "admin")');
        $stmt->execute(['id' => $id]);
    }

    // Dipakai sebagai jaring pengaman terakhir sebelum hapus/ubah peran akun
    // sendiri — jangan sampai jumlah Owner aktif jadi nol dan tidak ada yang
    // bisa login ke panel admin sama sekali.
    public static function hitungOwnerAktif(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE role = "owner" AND status_aktif = "aktif"');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function cekEmailDuplikat(string $email, int $kecualiId = 0): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $stmt->execute(['email' => $email, 'id' => $kecualiId]);
        return $stmt->fetch() !== false;
    }

    public static function cekHpDuplikat(string $noHp, int $kecualiId = 0): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE no_hp = :no_hp AND id != :id');
        $stmt->execute(['no_hp' => $noHp, 'id' => $kecualiId]);
        return $stmt->fetch() !== false;
    }
}