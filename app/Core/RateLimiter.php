<?php

namespace App\Core;

class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300;

    public static function isLocked(string $identifier): bool
    {
        $data = self::getData($identifier);
        if (!$data) {
            return false;
        }

        if ((int) $data['attempt_count'] >= self::MAX_ATTEMPTS) {
            $waktuTerlewat = time() - strtotime($data['last_attempt_at']);
            if ($waktuTerlewat < self::LOCKOUT_SECONDS) {
                return true;
            }
            self::reset($identifier);
        }

        return false;
    }

    public static function recordFailedAttempt(string $identifier): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (identifier, attempt_count, last_attempt_at)
             VALUES (:identifier, 1, NOW())
             ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt_at = NOW()'
        );
        $stmt->execute(['identifier' => $identifier]);
    }

    public static function reset(string $identifier): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM login_attempts WHERE identifier = :identifier');
        $stmt->execute(['identifier' => $identifier]);
    }

    public static function remainingLockoutSeconds(string $identifier): int
    {
        $data = self::getData($identifier);
        if (!$data) {
            return 0;
        }
        $sisaWaktu = self::LOCKOUT_SECONDS - (time() - strtotime($data['last_attempt_at']));
        return max(0, $sisaWaktu);
    }

    private static function getData(string $identifier): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT attempt_count, last_attempt_at FROM login_attempts WHERE identifier = :identifier');
        $stmt->execute(['identifier' => $identifier]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
