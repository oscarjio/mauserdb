<?php
class StatusController {
    public function handle() {
        // Öppna sessionen i read_and_close-läge så att session-fillåset
        // släpps omedelbart efter läsning. Hindrar polling-requests på föregående
        // sida från att blockera den nya status-checken vid sidomladdning.
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['loggedIn' => false]);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        // Session-låset är nu fritt — gör DB-frågan utan att blockera andra requests.

        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT username, email, admin, operator_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Användaren borttagen ur DB — förstör sessionen
                session_start();
                session_unset();
                session_destroy();
                echo json_encode(['loggedIn' => false]);
                return;
            }

            echo json_encode([
                'loggedIn' => true,
                'user' => [
                    'id' => $userId,
                    'username' => $user['username'],
                    'email' => $user['email'] ?? null,
                    'role' => ($user['admin'] == 1) ? 'admin' : 'user',
                    'operator_id' => $user['operator_id'] ? (int)$user['operator_id'] : null
                ]
            ]);
        } catch (Exception $e) {
            error_log('StatusController fel: ' . $e->getMessage());
            echo json_encode(['loggedIn' => false]);
        }
    }
}
