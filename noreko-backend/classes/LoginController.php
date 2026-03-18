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
        // Begränsa indata-längd för att förhindra missbruk (bcrypt trunkerar vid 72 bytes)
        if (strlen($username) > 100 || strlen($password) > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Användarnamn eller lösenord är för långt'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Rate limiting
        if (AuthHelper::isRateLimited($pdo, $ip)) {
            $remaining = AuthHelper::getLockoutRemaining($pdo, $ip);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => "För många inloggningsförsök. Försök igen om {$remaining} minuter."
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, email, password, admin, operator_id, role, active FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Kontrollera om kontot är inaktiverat (active-kolumnen kan saknas i äldre DB:er)
            if ($user && array_key_exists('active', $user) && (int)$user['active'] === 0) {
                AuthHelper::recordAttempt($pdo, $ip, $username, false);
                AuditLogger::log($pdo, 'login_blocked_inactive', 'user', (int)$user['id'],
                    "Inloggningsförsök till inaktiverat konto: {$username}");
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Kontot är inaktiverat. Kontakta administratören.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($user && AuthHelper::verifyPassword($password, $user['password'])) {
                AuthHelper::clearAttempts($pdo, $ip);
                AuthHelper::recordAttempt($pdo, $ip, $username, true);

                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                // Starta session FÖRST HÄR (efter verifiering).
                // Om en befintlig session finns (t.ex. från en annan sida) — regenerera ID:t
                // för att förhindra session fixation. Om ingen session fanns startas en ny.
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                // Regenerera session-ID för att förhindra session fixation-attacker.
                // true = radera gammal session-fil.
                session_regenerate_id(true);

                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? (((int)$user['admin'] === 1) ? 'admin' : 'user');
                $_SESSION['email'] = $user['email'] ?? null;
                $_SESSION['operator_id'] = $user['operator_id'] ? (int)$user['operator_id'] : null;
                $_SESSION['last_activity'] = time();

                // Audit log
                AuditLogger::log($pdo, 'login', 'user', $user['id'], "Inloggning: {$user['username']}");

                echo json_encode(['success' => true, 'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'] ?? null,
                    'role' => $_SESSION['role'],
                    'operator_id' => $_SESSION['operator_id']
                ]], JSON_UNESCAPED_UNICODE);
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
            error_log('LoginController::handle: ' . $e->getMessage());
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
        // Radera session-cookien i browsern så att ingen stale PHPSESSID ligger kvar.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                       (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $isHttps,
                    'httponly'  => true,
                    'samesite' => 'Lax',
                ]
            );
        }
        echo json_encode(['success' => true, 'message' => 'Utloggad'], JSON_UNESCAPED_UNICODE);
    }
}
