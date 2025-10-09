<?php
class AdminController {
    public function handle() {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Endast admin har behörighet.']);
            return;
        }
        global $pdo;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $username = $data['username'] ?? null;
            $email = $data['email'] ?? null;
            $phone = $data['phone'] ?? null;
            $password = $data['password'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID saknas']);
                return;
            }
            $fields = [];
            $params = [];
            if ($username) { $fields[] = 'username = ?'; $params[] = $username; }
            if ($email) { $fields[] = 'email = ?'; $params[] = $email; }
            if ($phone) { $fields[] = 'phone = ?'; $params[] = $phone; }
            if ($password) { $fields[] = 'password = ?'; $params[] = sha1(md5($password)); }
            if ($fields) {
                $params[] = $id;
                $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => 'Användare uppdaterad']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Inga fält att uppdatera']);
            }
            return;
        }
        $stmt = $pdo->query("SELECT id, username, email, phone, code, last_login, admin FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) { $u['role'] = ($u['admin'] == 1) ? 'admin' : 'user'; }
        echo json_encode(['users' => $users]);
    }
}
