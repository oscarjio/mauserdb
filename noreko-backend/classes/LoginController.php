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

        if ($user && $user['password'] === $this->hashPassword($password)) {
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

    private function hashPassword($password) {
        // Kombinerad hashning (för demo, använd password_hash i produktion!)
        return sha1(md5($password));
    }

    private function logout() {
        session_unset();
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Utloggad']);
    }
}
