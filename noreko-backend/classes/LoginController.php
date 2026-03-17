<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class LoginController {
    public function handle() {
        global $pdo;
        // OBS: session_start() anropas INTE här — det görs endast vid lyckat inlogg
        // nedan (se kommentar). På så sätt skickas bara EN Set-Cookie: PHPSESSID-header
        // per inloggning. Tidigare startades sessionen här + session_regenerate_id(true)
        // skickade en ANDRA cookie, vilket fick browsern att ibland skicka fel PHPSESSID
        // vid sidomladdning → status-endpoint returnerade loggedIn:false → utloggad.

        AuthHelper::ensureRateLimitTable($pdo);
        AuditLogger::ensureTable($pdo);

        $run = trim($_GET['run'] ?? 'login');
        if ($run === 'logout') {
            $this->logout();
            return;
        }

        // Login MÅSTE vara POST — avvisa alla andra metoder
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Endast POST-metod tillåten'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $username = strip_tags(trim($data['username'] ?? ''));
        $password = $data['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($username === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Användarnamn och lösenord krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Rate limiting
        if (AuthHelper::isRateLimited($pdo, $ip)) {
            $remaining = AuthHelper::getLockoutRemaining($pdo, $ip);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => "För många inloggningsförsök. Försök igen om {$remaining} minuter."
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, email, password, admin, operator_id, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && AuthHelper::verifyPassword($password, $user['password'], $pdo, $user['id'])) {
                AuthHelper::clearAttempts($pdo, $ip);
                AuthHelper::recordAttempt($pdo, $ip, $username, true);

                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                // Starta session FÖRST HÄR (efter verifiering) — inga gamla session-ID:n
                // att regenerera, så exakt EN Set-Cookie: PHPSESSID skickas till browsern.
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? (((int)$user['admin'] === 1) ? 'admin' : 'user');
                $_SESSION['email'] = $user['email'] ?? null;
                $_SESSION['operator_id'] = $user['operator_id'] ? (int)$user['operator_id'] : null;

                // Audit log
                AuditLogger::log($pdo, 'login', 'user', $user['id'], "Inloggning: {$user['username']}");

                echo json_encode(['success' => true, 'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'] ?? null,
                    'role' => $_SESSION['role'],
                    'operator_id' => $_SESSION['operator_id']
                ]]);
            } else {
                AuthHelper::recordAttempt($pdo, $ip, $username, false);
                $attemptsLeft = 5 - AuthHelper::getFailedAttemptCount($pdo, $ip);
                $msg = 'Felaktigt användarnamn eller lösenord';
                if ($attemptsLeft <= 2 && $attemptsLeft > 0) {
                    $msg .= '. Kontot låses snart tillfälligt vid ytterligare misslyckade försök.';
                }

                AuditLogger::log($pdo, 'login_failed', 'user', null, "Misslyckat inloggningsförsök: {$username}");

                http_response_code(401);
                echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
            }

            // Cleanup old attempts ~1% of requests
            if (mt_rand(1, 100) === 1) {
                AuthHelper::cleanupOldAttempts($pdo);
            }
        } catch (PDOException $e) {
            error_log('LoginController handle: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel — försök igen senare.'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function logout() {
        global $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            AuditLogger::log($pdo, 'logout', 'user', (int)$_SESSION['user_id'],
                "Utloggning: " . ($_SESSION['username'] ?? 'okänd'));
        }
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Utloggad'], JSON_UNESCAPED_UNICODE);
    }
}
