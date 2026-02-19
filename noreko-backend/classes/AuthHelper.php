<?php
/**
 * AuthHelper - Shared authentication utilities
 *
 * Centralizes password verification (bcrypt + legacy migration),
 * database-backed rate limiting, and login audit logging.
 */
class AuthHelper {
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Verify password against stored hash.
     * Supports bcrypt (current) and legacy sha1(md5()) with auto-migration.
     */
    public static function verifyPassword(string $password, string $storedHash, ?PDO $pdo = null, ?int $userId = null): bool {
        // Bcrypt (current standard)
        if (password_verify($password, $storedHash)) {
            return true;
        }

        // Legacy sha1(md5()) - migrate to bcrypt on successful match
        if ($storedHash === sha1(md5($password))) {
            if ($pdo && $userId) {
                try {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
                } catch (PDOException $e) {
                    error_log('AuthHelper: Failed to migrate password hash: ' . $e->getMessage());
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Ensure rate limiting table exists.
     */
    public static function ensureRateLimitTable(PDO $pdo): void {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` int NOT NULL AUTO_INCREMENT,
                `ip_address` varchar(45) NOT NULL,
                `username` varchar(100) DEFAULT NULL,
                `success` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ip_created` (`ip_address`, `created_at`),
                KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (PDOException $e) {
            error_log('AuthHelper ensureRateLimitTable: ' . $e->getMessage());
        }
    }

    /**
     * Record a login attempt.
     */
    public static function recordAttempt(PDO $pdo, string $ip, string $username, bool $success): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)");
            $stmt->execute([$ip, $username, $success ? 1 : 0]);
        } catch (PDOException $e) {
            error_log('AuthHelper recordAttempt: ' . $e->getMessage());
        }
    }

    /**
     * Check if IP is rate limited.
     */
    public static function isRateLimited(PDO $pdo, string $ip): bool {
        return self::getFailedAttemptCount($pdo, $ip) >= self::MAX_ATTEMPTS;
    }

    /**
     * Get number of failed attempts in the lockout window.
     */
    public static function getFailedAttemptCount(PDO $pdo, string $ip): int {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > ?"
            );
            $stmt->execute([$ip, $cutoff]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('AuthHelper getFailedAttemptCount: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get minutes remaining on lockout.
     */
    public static function getLockoutRemaining(PDO $pdo, string $ip): int {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));
            $stmt = $pdo->prepare(
                "SELECT MIN(created_at) FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > ?"
            );
            $stmt->execute([$ip, $cutoff]);
            $oldest = $stmt->fetchColumn();
            if (!$oldest) return 0;
            $unlockAt = strtotime($oldest) + (self::LOCKOUT_MINUTES * 60);
            return max(1, (int)ceil(($unlockAt - time()) / 60));
        } catch (PDOException $e) {
            return self::LOCKOUT_MINUTES;
        }
    }

    /**
     * Clear failed attempts for IP (called on successful login).
     */
    public static function clearAttempts(PDO $pdo, string $ip): void {
        try {
            $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0")->execute([$ip]);
        } catch (PDOException $e) {
            error_log('AuthHelper clearAttempts: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup old attempts (call periodically).
     */
    public static function cleanupOldAttempts(PDO $pdo): void {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - 86400); // 24h
            $pdo->prepare("DELETE FROM login_attempts WHERE created_at < ?")->execute([$cutoff]);
        } catch (PDOException $e) {
            error_log('AuthHelper cleanupOldAttempts: ' . $e->getMessage());
        }
    }
}
