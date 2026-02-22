<?php
class StatusController {
    public function handle() {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['loggedIn' => false]);
            return;
        }

        global $pdo;
        $stmt = $pdo->prepare("SELECT username, email, admin, operator_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_unset();
            session_destroy();
            echo json_encode(['loggedIn' => false]);
            return;
        }

        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'] ?? null;
        $_SESSION['role'] = ($user['admin'] == 1) ? 'admin' : 'user';
        $_SESSION['operator_id'] = $user['operator_id'] ? (int)$user['operator_id'] : null;

        echo json_encode([
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'operator_id' => $_SESSION['operator_id']
            ]
        ]);
    }
}
