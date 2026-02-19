<?php
require_once __DIR__ . '/AuthHelper.php';

class ProfileController {
    public function handle() {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Du måste vara inloggad för att uppdatera kontot.']);
            return;
        }

        global $pdo;

        $stmt = $pdo->prepare("SELECT id, username, email, password, admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_unset();
            session_destroy();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Användaren hittades inte.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => ($user['admin'] == 1) ? 'admin' : 'user'
                ]
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Endast POST-metod tillåten.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = isset($data['email']) ? trim($data['email']) : null;
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        $fields = [];
        $params = [];

        if ($email !== null && $email !== '' && $email !== $user['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig e-postadress.']);
                return;
            }
            $fields[] = 'email = ?';
            $params[] = $email;
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lösenordet måste vara minst 8 tecken och innehålla både bokstäver och siffror.']);
                return;
            }
            if (empty($currentPassword)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nuvarande lösenord krävs för att ändra lösenord.']);
                return;
            }
            if (!AuthHelper::verifyPassword($currentPassword, $user['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nuvarande lösenord är felaktigt.']);
                return;
            }
            $fields[] = 'password = ?';
            $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        if (!$fields) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Inga ändringar att spara.']);
            return;
        }

        $params[] = $user['id'];
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        $stmt = $pdo->prepare("SELECT id, username, email, admin FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['username'] = $updatedUser['username'];
        $_SESSION['email'] = $updatedUser['email'];
        $_SESSION['role'] = ($updatedUser['admin'] == 1) ? 'admin' : 'user';

        echo json_encode([
            'success' => true,
            'message' => 'Konto uppdaterat.',
            'user' => [
                'id' => $updatedUser['id'],
                'username' => $updatedUser['username'],
                'email' => $updatedUser['email'],
                'role' => $_SESSION['role']
            ]
        ]);
    }

}

