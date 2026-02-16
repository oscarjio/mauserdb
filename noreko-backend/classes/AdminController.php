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
        
        // POST - Skapa, uppdatera användare, ta bort, eller ändra status
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'update';
            
            // CREATE - Skapa ny användare
            if ($action === 'create') {
                $username = trim($data['username'] ?? '');
                $password = $data['password'] ?? '';
                $email = trim($data['email'] ?? '');
                $phone = trim($data['phone'] ?? '');
                
                // Enkel validering
                if (empty($username) || empty($password) || empty($email)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Användarnamn, lösenord och e-post krävs']);
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
                
                // Hasha lösenord med bcrypt
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                
                // Kontrollera om active-kolumnen finns
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'active'");
                    $activeExists = $stmt->rowCount() > 0;
                } catch (PDOException $e) {
                    $activeExists = false;
                }
                
                // Spara användare i databasen
                try {
                    if ($activeExists) {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, admin, active, created_at) VALUES (?, ?, ?, ?, 0, 1, NOW())");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                    }
                    $stmt->execute([$username, $hashedPassword, $email, $phone ?: null]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Användare skapad!',
                        'user' => [
                            'id' => $pdo->lastInsertId(),
                            'username' => $username,
                            'email' => $email
                        ]
                    ]);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte skapa användare']);
                }
                return;
            }
            
            $id = $data['id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID saknas']);
                return;
            }
            
            // DELETE - Ta bort användare
            if ($action === 'delete') {
                // Förhindra att admin tar bort sig själv
                if ($id == $_SESSION['user_id']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Du kan inte ta bort ditt eget konto']);
                    return;
                }
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true, 'message' => 'Användare borttagen']);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort användare']);
                }
                return;
            }
            
            // Toggle admin status
            if ($action === 'toggleAdmin') {
                // Förhindra att admin tar bort sin egen admin-status
                if ($id == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Du kan inte ändra din egen admin-status']);
                    return;
                }
                try {
                    $stmt = $pdo->prepare("SELECT admin FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $newAdminStatus = $user['admin'] == 1 ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE users SET admin = ? WHERE id = ?");
                        $stmt->execute([$newAdminStatus, $id]);
                        echo json_encode(['success' => true, 'message' => 'Admin-status uppdaterad', 'admin' => $newAdminStatus]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Användare hittades inte']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera admin-status']);
                }
                return;
            }
            
            // Toggle active status
            if ($action === 'toggleActive') {
                // Förhindra att admin inaktiverar sig själv
                if ($id == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Du kan inte inaktivera ditt eget konto']);
                    return;
                }
                try {
                    // Kontrollera om active-kolumnen finns
                    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'active'");
                    $activeExists = $stmt->rowCount() > 0;
                    
                    if (!$activeExists) {
                        // Skapa active-kolumnen om den inte finns
                        $stmt = $pdo->prepare("ALTER TABLE users ADD COLUMN active TINYINT(1) DEFAULT 1");
                        $stmt->execute();
                        $stmt = $pdo->prepare("UPDATE users SET active = 1 WHERE active IS NULL");
                        $stmt->execute();
                    }
                    
                    // Hämta nuvarande status
                    $stmt = $pdo->prepare("SELECT active FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        $newActiveStatus = ($user['active'] == 1) ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE users SET active = ? WHERE id = ?");
                        $stmt->execute([$newActiveStatus, $id]);
                        echo json_encode(['success' => true, 'message' => 'Status uppdaterad', 'active' => $newActiveStatus]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Användare hittades inte']);
                    }
                } catch (PDOException $e) {
                    error_log('Kunde inte uppdatera status (toggleActive): ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera status']);
                }
                return;
            }
            
            // Standard update
            $username = $data['username'] ?? null;
            $email = $data['email'] ?? null;
            $phone = $data['phone'] ?? null;
            $password = $data['password'] ?? null;
            $admin = isset($data['admin']) ? ($data['admin'] ? 1 : 0) : null;
            
            $fields = [];
            $params = [];
            if ($username) { $fields[] = 'username = ?'; $params[] = $username; }
            if ($email) { $fields[] = 'email = ?'; $params[] = $email; }
            if ($phone !== null) { $fields[] = 'phone = ?'; $params[] = $phone; }
            if ($password) { $fields[] = 'password = ?'; $params[] = password_hash($password, PASSWORD_BCRYPT); }
            if ($admin !== null && $id != $_SESSION['user_id']) { 
                $fields[] = 'admin = ?'; 
                $params[] = $admin; 
            }
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
        
        // GET - Hämta alla användare
        try {
            // Försök hämta med active-kolumnen, fallback om den inte finns
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'active'");
            $activeExists = $stmt->rowCount() > 0;
            
            if ($activeExists) {
                $stmt = $pdo->query("SELECT id, username, email, phone, code, last_login, admin, active FROM users");
            } else {
                $stmt = $pdo->query("SELECT id, username, email, phone, code, last_login, admin FROM users");
            }
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as &$u) { 
                $u['role'] = ($u['admin'] == 1) ? 'admin' : 'user';
                if (!isset($u['active'])) {
                    $u['active'] = 1; // Default till aktiv om kolumnen inte finns
                }
            }
            echo json_encode(['users' => $users]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Kunde inte hämta användare']);
        }
    }
}
