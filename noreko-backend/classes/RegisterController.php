<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class RegisterController {
    public function handle() {
        global $pdo;
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Endast POST-metod tillåten'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Hämta och validera input
        $username = strip_tags(trim($data['username'] ?? ''));
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
        }
        
        if (empty($password)) {
            $errors[] = 'Lösenord krävs';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Lösenord måste vara minst 8 tecken';
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
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ogiltig e-postadress';
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
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => implode('. ', $errors)], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Kontrollera om användarnamn redan finns
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Användarnamnet är redan taget']);
                return;
            }
        } catch (PDOException $e) {
            error_log('RegisterController check_username: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Databasfel vid kontroll av användarnamn'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $hashedPassword = AuthHelper::hashPassword($password);
        
        // Spara användare i databasen
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, code, admin, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$username, $hashedPassword, $email, $phone ?: null, $code ?: null]);
            $newUserId = (int)$pdo->lastInsertId();
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
            error_log('RegisterController create_user: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte skapa användare. Försök igen senare.'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}

