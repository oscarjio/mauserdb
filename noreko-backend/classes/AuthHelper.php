<?php
/**
 * AuthHelper - Shared authentication utilities
 *
 * Centralizes password verification (bcrypt med transparent migration från sha1(md5())),
 * database-backed rate limiting, and login audit logging.
 */
class AuthHelper {
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;
    /** Session timeout i sekunder (8 timmar — matchar session.gc_maxlifetime i api.php) */
    private const SESSION_TIMEOUT = 28800;

    /**
     * Verify password using bcrypt.
     */
    public static function verifyPassword(string $password, string $storedHash): bool {
        return password_verify($password, $storedHash);
    }

    /**
     * Hash a password using bcrypt.
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
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
            error_log('AuthHelper::ensureRateLimitTable: ' . $e->getMessage());
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
            error_log('AuthHelper::recordAttempt: ' . $e->getMessage());
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
            error_log('AuthHelper::getFailedAttemptCount: ' . $e->getMessage());
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
            $oldestTs = strtotime($oldest);
            if ($oldestTs === false) return self::LOCKOUT_MINUTES;
            $unlockAt = $oldestTs + (self::LOCKOUT_MINUTES * 60);
            return max(1, (int)ceil(($unlockAt - time()) / 60));
        } catch (PDOException $e) {
            error_log('AuthHelper::getLockoutRemaining: ' . $e->getMessage());
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
            error_log('AuthHelper::clearAttempts: ' . $e->getMessage());
        }
    }

    /**
     * Kontrollera om sessionen har gått ut p.g.a. inaktivitet.
     * Uppdaterar last_activity vid varje anrop.
     * Returnerar true om sessionen är giltig, false om den bör förstöras.
     */
    public static function checkSessionTimeout(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT) {
            // Sessionen har gått ut — rensa allt
            session_unset();
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Cleanup old attempts (call periodically).
     */
    public static function cleanupOldAttempts(PDO $pdo): void {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - 86400); // 24h
            $pdo->prepare("DELETE FROM login_attempts WHERE created_at < ?")->execute([$cutoff]);
        } catch (PDOException $e) {
            error_log('AuthHelper::cleanupOldAttempts: ' . $e->getMessage());
        }
    }
}
