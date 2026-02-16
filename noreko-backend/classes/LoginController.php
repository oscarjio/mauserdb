<?php
class LoginController {
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

        $stmt = $pdo->prepare("SELECT id, username, email, password, admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $this->verifyPassword($password, $user['password'], $pdo, $user['id'])) {
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
            echo json_encode(['success' => false, 'message' => 'Felaktigt användarnamn eller lösenord']);
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
}
