<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class RegisterController {
    public function handle() {
        global $pdo;
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Endast POST-metod tillåten'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Rate limiting — förhindra mass-registreringar från samma IP
        AuthHelper::ensureRateLimitTable($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $regIp = 'reg:' . $ip; // Prefix för att skilja från login-attempts
        if (AuthHelper::isRateLimited($pdo, $regIp)) {
            $remaining = AuthHelper::getLockoutRemaining($pdo, $regIp);
            error_log("RegisterController::handle: Rate limit för registrering, IP={$ip}");
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => "För många registreringsförsök. Försök igen om {$remaining} minuter."
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Hämta och validera input
        $username = strip_tags(trim($data['username'] ?? ''));
        // Sanera användarnamn för loggning — ta bort kontrolltecken (\n, \r, \t etc.)
        // för att förhindra log injection.
        $safeUsername = preg_replace('/[\x00-\x1F\x7F]/', '', $username);
        $password = $data['password'] ?? '';
        $password2 = $data['password2'] ?? '';
        $email = strip_tags(trim($data['email'] ?? ''));
        $phone = strip_tags(trim($data['phone'] ?? ''));
        $code = strip_tags(trim($data['code'] ?? ''));

        // Valideringar
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'Användarnamn krävs';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Användarnamn måste vara minst 3 tecken';
        } elseif (strlen($username) > 50) {
            $errors[] = 'Användarnamn får vara max 50 tecken';
        }
        
        if (empty($password)) {
            $errors[] = 'Lösenord krävs';
        } elseif (strlen($password) < 8 || strlen($password) > 255) {
            $errors[] = 'Lösenord måste vara 8–255 tecken';
        } elseif (!preg_match('/[A-Za-z]/', $password)) {
            $errors[] = 'Lösenord måste innehålla minst en bokstav';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Lösenord måste innehålla minst en siffra';
        }
        
        if ($password !== $password2) {
            $errors[] = 'Lösenorden matchar inte';
        }
        
        if (empty($email)) {
            $errors[] = 'E-postadress krävs';
        } elseif (strlen($email) > 255) {
            $errors[] = 'E-postadress får vara max 255 tecken';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ogiltig e-postadress';
        }
        
        if (strlen($phone) > 50) {
            $errors[] = 'Telefonnummer får vara max 50 tecken';
        }

        // Load registration code from config
        $registrationCode = 'Noreko2025'; // fallback
        $configFile = __DIR__ . '/../app_config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $registrationCode = $config['registration_code'] ?? $registrationCode;
        }
        if ($code !== $registrationCode) {
            $errors[] = 'Fel Kontrollkod.';
        }

        if (!empty($errors)) {
            AuthHelper::recordAttempt($pdo, $regIp, $username ?: 'unknown', false);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => implode('. ', $errors)], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $hashedPassword = AuthHelper::hashPassword($password);

        // Kontrollera och registrera inom transaktion för att undvika race condition med duplicerade användarnamn
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? FOR UPDATE");
            $stmt->execute([$username]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Användarnamnet är redan taget'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, code, admin, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$username, $hashedPassword, $email, $phone ?: null, $code ?: null]);
            $newUserId = (int)$pdo->lastInsertId();

            $pdo->commit();

            AuditLogger::log($pdo, 'register', 'users', $newUserId, "Ny användare registrerad: $username");

            echo json_encode([
                'success' => true,
                'message' => 'Registrering lyckades! Du kan nu logga in.',
                'user' => [
                    'id' => $newUserId,
                    'username' => $username,
                    'email' => $email
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Hantera duplicate key-fel (extra säkerhet om UNIQUE constraint finns)
            if ((string)$e->getCode() === '23000') {
                error_log('RegisterController::create_user — duplicate key for username: ' . $safeUsername);
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Användarnamnet är redan taget'], JSON_UNESCAPED_UNICODE);
                return;
            }
            error_log('RegisterController::create_user: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte skapa användare. Försök igen senare.'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}

