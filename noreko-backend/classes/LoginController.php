<?php
class LoginController {
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function handle() {
        global $pdo;
        session_start();
        $run = $_GET['run'] ?? 'login';
        if ($run === 'logout') {
            $this->logout();
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($this->isRateLimited($ip)) {
            http_response_code(429);
            $remaining = $this->getLockoutRemaining($ip);
            echo json_encode([
                'success' => false,
                'message' => "För många inloggningsförsök. Försök igen om {$remaining} minuter."
            ]);
            return;
        }

        $stmt = $pdo->prepare("SELECT id, username, email, password, admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $this->verifyPassword($password, $user['password'], $pdo, $user['id'])) {
            // Rensa misslyckade försök vid lyckad inloggning
            $this->clearAttempts($ip);
            // Uppdatera senaste inloggning
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            // Sätt session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = ($user['admin'] == 1) ? 'admin' : 'user';
            $_SESSION['email'] = $user['email'] ?? null;
            echo json_encode(['success' => true, 'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'role' => ($_SESSION['role'])
            ]]);
        } else {
            $this->recordFailedAttempt($ip);
            $attemptsLeft = self::MAX_ATTEMPTS - $this->getAttemptCount($ip);
            $msg = 'Felaktigt användarnamn eller lösenord';
            if ($attemptsLeft <= 2 && $attemptsLeft > 0) {
                $msg .= ". {$attemptsLeft} försök kvar.";
            }
            echo json_encode(['success' => false, 'message' => $msg]);
        }
    }

    private function verifyPassword($password, $storedHash, $pdo, $userId) {
        // Försök med bcrypt först (nya lösenord)
        if (password_verify($password, $storedHash)) {
            return true;
        }

        // Fallback: legacy sha1(md5()) hash - migrera till bcrypt vid lyckad inloggning
        if ($storedHash === sha1(md5($password))) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
            return true;
        }

        return false;
    }

    private function logout() {
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Utloggad']);
    }

    // --- Rate Limiting (session-baserad med fil-cache för IP) ---

    private function getAttemptsFile(): string {
        $dir = sys_get_temp_dir() . '/mauserdb_login_attempts';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir . '/attempts.json';
    }

    private function loadAttempts(): array {
        $file = $this->getAttemptsFile();
        if (!file_exists($file)) return [];
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function saveAttempts(array $attempts): void {
        file_put_contents($this->getAttemptsFile(), json_encode($attempts), LOCK_EX);
    }

    private function cleanOldAttempts(array &$attempts): void {
        $cutoff = time() - (self::LOCKOUT_MINUTES * 60);
        foreach ($attempts as $ip => $data) {
            $attempts[$ip]['timestamps'] = array_values(
                array_filter($data['timestamps'] ?? [], fn($t) => $t > $cutoff)
            );
            if (empty($attempts[$ip]['timestamps'])) {
                unset($attempts[$ip]);
            }
        }
    }

    private function recordFailedAttempt(string $ip): void {
        $attempts = $this->loadAttempts();
        $this->cleanOldAttempts($attempts);
        $attempts[$ip]['timestamps'][] = time();
        $this->saveAttempts($attempts);
    }

    private function getAttemptCount(string $ip): int {
        $attempts = $this->loadAttempts();
        $this->cleanOldAttempts($attempts);
        return count($attempts[$ip]['timestamps'] ?? []);
    }

    private function isRateLimited(string $ip): bool {
        return $this->getAttemptCount($ip) >= self::MAX_ATTEMPTS;
    }

    private function getLockoutRemaining(string $ip): int {
        $attempts = $this->loadAttempts();
        $timestamps = $attempts[$ip]['timestamps'] ?? [];
        if (empty($timestamps)) return 0;
        $oldest = min($timestamps);
        $unlockAt = $oldest + (self::LOCKOUT_MINUTES * 60);
        return max(1, (int)ceil(($unlockAt - time()) / 60));
    }

    private function clearAttempts(string $ip): void {
        $attempts = $this->loadAttempts();
        unset($attempts[$ip]);
        $this->saveAttempts($attempts);
    }
}
