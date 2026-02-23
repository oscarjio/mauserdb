<?php
class RegisterController {
    public function handle() {
        global $pdo;
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Endast POST-metod tillåten']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Hämta och validera input
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $password2 = $data['password2'] ?? '';
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $code = trim($data['code'] ?? '');

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
            echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
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
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Databasfel vid kontroll av användarnamn']);
            return;
        }
        
        // Hasha lösenord med sha1(md5()) – samma som befintlig produktion
        $hashedPassword = sha1(md5($password));
        
        // Spara användare i databasen
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, code, admin, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$username, $hashedPassword, $email, $phone ?: null, $code ?: null]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registrering lyckades! Du kan nu logga in.',
                'user' => [
                    'id' => $pdo->lastInsertId(),
                    'username' => $username,
                    'email' => $email
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte skapa användare. Försök igen senare.'
            ]);
        }
    }
}

