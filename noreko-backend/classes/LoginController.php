<?php
class LoginController {
    public function handle() {
        global $pdo;
        session_start();
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password'] === $this->hashPassword($password)) {
            // Uppdatera senaste inloggning
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            // Sätt session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode(['success' => true, 'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Felaktigt användarnamn eller lösenord']);
        }
    }

    private function hashPassword($password) {
        // Kombinerad hashning (för demo, använd password_hash i produktion!)
        return sha1(md5($password));
    }
}
