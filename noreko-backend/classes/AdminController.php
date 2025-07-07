<?php
class AdminController {
    public function handle() {
        global $pdo;
        $stmt = $pdo->query("SELECT id, username, email, phone, code, last_login FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['users' => $users]);
    }
}
