<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class LoginController {
    public function handle() {
        global $pdo;
        session_start();

        AuthHelper::ensureRateLimitTable($pdo);
        AuditLogger::ensureTable($pdo);

        $run = $_GET['run'] ?? 'login';
        if ($run === 'logout') {
            $this->logout();
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Rate limiting
        if (AuthHelper::isRateLimited($pdo, $ip)) {
            $remaining = AuthHelper::getLockoutRemaining($pdo, $ip);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "För många inloggningsförsök. Försök igen om {$remaining} minuter."
            ]);
            return;
        }

        $stmt = $pdo->prepare("SELECT id, username, email, password, admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && AuthHelper::verifyPassword($password, $user['password'], $pdo, $user['id'])) {
            AuthHelper::clearAttempts($pdo, $ip);
            AuthHelper::recordAttempt($pdo, $ip, $username, true);

            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = ($user['admin'] == 1) ? 'admin' : 'user';
            $_SESSION['email'] = $user['email'] ?? null;

            // Audit log
            AuditLogger::log($pdo, 'login', 'user', $user['id'], "Inloggning: {$user['username']}");

            echo json_encode(['success' => true, 'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'role' => $_SESSION['role']
            ]]);
        } else {
            AuthHelper::recordAttempt($pdo, $ip, $username, false);
            $attemptsLeft = 5 - AuthHelper::getFailedAttemptCount($pdo, $ip);
            $msg = 'Felaktigt användarnamn eller lösenord';
            if ($attemptsLeft <= 2 && $attemptsLeft > 0) {
                $msg .= ". {$attemptsLeft} försök kvar.";
            }

            AuditLogger::log($pdo, 'login_failed', 'user', null, "Misslyckat inloggningsförsök: {$username}");

            echo json_encode(['success' => false, 'message' => $msg]);
        }

        // Cleanup old attempts ~1% of requests
        if (mt_rand(1, 100) === 1) {
            AuthHelper::cleanupOldAttempts($pdo);
        }
    }

    private function logout() {
        global $pdo;
        if (!empty($_SESSION['user_id'])) {
            AuditLogger::log($pdo, 'logout', 'user', (int)$_SESSION['user_id'],
                "Utloggning: " . ($_SESSION['username'] ?? 'okänd'));
        }
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Utloggad']);
    }
}
