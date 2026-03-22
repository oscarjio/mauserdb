<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class AdminController {
    public function handle() {
        $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
        if (session_status() === PHP_SESSION_NONE) {
            if ($isPost) {
                session_start();
            } else {
                session_start(['read_and_close' => true]);
            }
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            error_log('AdminController::handle: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', role=' . ($_SESSION['role'] ?? 'none'));
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Kontrollera session-timeout (inaktivitet)
        if (!AuthHelper::checkSessionTimeout()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        global $pdo;
        AuditLogger::ensureTable($pdo);

        // POST - Skapa, uppdatera användare, ta bort, eller ändra status
        if ($isPost) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $action = trim($data['action'] ?? 'update');
            
            // CREATE - Skapa ny användare
            if ($action === 'create') {
                $username = strip_tags(trim($data['username'] ?? ''));
                $password = $data['password'] ?? '';
                $email = strip_tags(trim($data['email'] ?? ''));
                $phone = strip_tags(trim($data['phone'] ?? ''));

                // Enkel validering
                if (empty($username) || empty($password) || empty($email)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Användarnamn, lösenord och e-post krävs'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if (strlen($username) < 3 || strlen($username) > 50) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Användarnamn måste vara 3–50 tecken'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if (strlen($password) < 8 || strlen($password) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Lösenordet måste vara 8–255 tecken'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Lösenordet måste innehålla minst en bokstav och en siffra'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltig e-postadress'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if (strlen($phone) > 50) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Telefonnummer får vara max 50 tecken'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                $hashedPassword = AuthHelper::hashPassword($password);

                // Kontrollera om active-kolumnen finns
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'active'");
                    $activeExists = $stmt->rowCount() > 0;
                } catch (PDOException $e) {
                    error_log('AdminController::create_user SHOW COLUMNS: ' . $e->getMessage());
                    $activeExists = false;
                }

                // Kontrollera och skapa användare inom transaktion för att undvika race condition
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? FOR UPDATE");
                    $stmt->execute([$username]);
                    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                        $pdo->rollBack();
                        http_response_code(409);
                        echo json_encode(['success' => false, 'error' => 'Användarnamnet är redan taget'], JSON_UNESCAPED_UNICODE);
                        return;
                    }

                    if ($activeExists) {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, admin, active, created_at) VALUES (?, ?, ?, ?, 0, 1, NOW())");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                    }
                    $stmt->execute([$username, $hashedPassword, $email, $phone ?: null]);

                    $newId = $pdo->lastInsertId();
                    $pdo->commit();

                    AuditLogger::log($pdo, 'create_user', 'user', (int)$newId,
                        "Skapade användare: $username"
                    );
                    echo json_encode([
                        'success' => true,
                        'message' => 'Användare skapad!',
                        'user' => [
                            'id' => $newId,
                            'username' => $username,
                            'email' => $email
                        ]
                    ], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ((string)$e->getCode() === '23000') {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'error' => 'Användarnamnet är redan taget'], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                    error_log('AdminController::create_user insert: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Kunde inte skapa användare'], JSON_UNESCAPED_UNICODE);
                }
                return;
            }
            
            $id = isset($data['id']) ? (int)$data['id'] : 0;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID saknas'], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            // DELETE - Ta bort användare
            if ($action === 'delete') {
                // Förhindra att admin tar bort sig själv
                if ($id === (int)$_SESSION['user_id']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Du kan inte ta bort ditt eget konto'], JSON_UNESCAPED_UNICODE);
                    return;
                }

                try {
                    $pdo->beginTransaction();
                    // Hämta användardata innan radering för audit (FOR UPDATE förhindrar race condition)
                    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$id]);
                    $deletedUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$deletedUser) {
                        $pdo->rollBack();
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Användare hittades inte'], JSON_UNESCAPED_UNICODE);
                        return;
                    }

                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $pdo->commit();
                    AuditLogger::log($pdo, 'delete_user', 'user', (int)$id,
                        "Tog bort användare: " . ($deletedUser['username'] ?? 'okänd'),
                        $deletedUser, null
                    );
                    echo json_encode(['success' => true, 'message' => 'Användare borttagen'], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('AdminController::delete_user: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort användare'], JSON_UNESCAPED_UNICODE);
                }
                return;
            }
            
            // Toggle admin status
            if ($action === 'toggleAdmin') {
                // Förhindra att admin tar bort sin egen admin-status
                if ($id === (int)$_SESSION['user_id']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Du kan inte ändra din egen admin-status'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("SELECT admin, username FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $newAdminStatus = (int)$user['admin'] === 1 ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE users SET admin = ? WHERE id = ?");
                        $stmt->execute([$newAdminStatus, $id]);
                        $pdo->commit();
                        $uname = $user['username'] ?: 'okänd';
                        AuditLogger::log($pdo, 'toggle_admin', 'user', (int)$id,
                            "Ändrade admin-status för $uname: " . ($newAdminStatus ? 'admin' : 'user'),
                            ['admin' => $user['admin']], ['admin' => $newAdminStatus]
                        );
                        echo json_encode(['success' => true, 'message' => 'Admin-status uppdaterad', 'admin' => $newAdminStatus], JSON_UNESCAPED_UNICODE);
                    } else {
                        $pdo->rollBack();
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Användare hittades inte'], JSON_UNESCAPED_UNICODE);
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('AdminController::toggle_admin: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera admin-status'], JSON_UNESCAPED_UNICODE);
                }
                return;
            }
            
            // Toggle active status
            if ($action === 'toggleActive') {
                // Förhindra att admin inaktiverar sig själv
                if ($id === (int)$_SESSION['user_id']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Du kan inte inaktivera ditt eget konto'], JSON_UNESCAPED_UNICODE);
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

                    // Hämta och uppdatera inom transaktion för att undvika race condition
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("SELECT active, username FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        $newActiveStatus = ((int)$user['active'] === 1) ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE users SET active = ? WHERE id = ?");
                        $stmt->execute([$newActiveStatus, $id]);
                        $pdo->commit();
                        $uname = $user['username'] ?: 'okänd';
                        AuditLogger::log($pdo, 'toggle_active', 'user', (int)$id,
                            ($newActiveStatus ? 'Aktiverade' : 'Inaktiverade') . " användare: $uname",
                            ['active' => $user['active']], ['active' => $newActiveStatus]
                        );
                        echo json_encode(['success' => true, 'message' => 'Status uppdaterad', 'active' => $newActiveStatus], JSON_UNESCAPED_UNICODE);
                    } else {
                        $pdo->rollBack();
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Användare hittades inte'], JSON_UNESCAPED_UNICODE);
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('AdminController::toggleActive: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera status'], JSON_UNESCAPED_UNICODE);
                }
                return;
            }
            
            // Standard update
            $username = isset($data['username']) ? strip_tags(trim($data['username'])) : null;
            $email = isset($data['email']) ? strip_tags(trim($data['email'])) : null;
            $phone = isset($data['phone']) ? strip_tags(trim($data['phone'])) : null;
            $password = $data['password'] ?? null;
            $admin = isset($data['admin']) ? ($data['admin'] ? 1 : 0) : null;
            $operatorId = array_key_exists('operator_id', $data) ? $data['operator_id'] : 'SKIP';

            // Validera username om angiven
            if ($username !== null && (strlen($username) < 3 || strlen($username) > 50)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Användarnamn måste vara 3–50 tecken'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Validera email om angiven
            if ($email && (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig e-postadress (max 255 tecken)'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Validera telefon om angiven
            if ($phone !== null && strlen($phone) > 50) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Telefonnummer får vara max 50 tecken'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Validera lösenord om angivet
            if ($password && (strlen($password) < 8 || strlen($password) > 255 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Lösenord måste vara 8–255 tecken med bokstav och siffra'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $fields = [];
            $params = [];
            if ($username) { $fields[] = 'username = ?'; $params[] = $username; }
            if ($email) { $fields[] = 'email = ?'; $params[] = $email; }
            if ($phone !== null) { $fields[] = 'phone = ?'; $params[] = $phone; }
            if ($password) { $fields[] = 'password = ?'; $params[] = AuthHelper::hashPassword($password); }
            if ($admin !== null && $id !== (int)$_SESSION['user_id']) {
                $fields[] = 'admin = ?';
                $params[] = $admin;
            }
            if ($operatorId !== 'SKIP') {
                $fields[] = 'operator_id = ?';
                $params[] = ($operatorId === null || $operatorId === '') ? null : (int)$operatorId;
            }
            if ($fields) {
                try {
                    $params[] = $id;
                    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $changedFields = [];
                    if ($username) $changedFields['username'] = $username;
                    if ($email) $changedFields['email'] = $email;
                    if ($phone !== null) $changedFields['phone'] = $phone;
                    if ($password) $changedFields['password'] = '***';
                    if ($admin !== null) $changedFields['admin'] = $admin;
                    if ($operatorId !== 'SKIP') $changedFields['operator_id'] = $operatorId;
                    AuditLogger::log($pdo, 'update_user', 'user', (int)$id,
                        "Uppdaterade användare (ID: $id): " . implode(', ', array_keys($changedFields)),
                        null, $changedFields
                    );
                    echo json_encode(['success' => true, 'message' => 'Användare uppdaterad'], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e) {
                    error_log('AdminController::update_user: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera användare'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga fält att uppdatera'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }
        
        // GET - Hämta alla användare
        try {
            // Försök hämta med active-kolumnen, fallback om den inte finns
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'active'");
            $activeExists = $stmt->rowCount() > 0;
            
            if ($activeExists) {
                $stmt = $pdo->query("SELECT id, username, email, phone, last_login, admin, active, operator_id FROM users");
            } else {
                $stmt = $pdo->query("SELECT id, username, email, phone, last_login, admin, operator_id FROM users");
            }
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as &$u) {
                $u['role'] = ((int)$u['admin'] === 1) ? 'admin' : 'user';
                if (!isset($u['active'])) {
                    $u['active'] = 1; // Default till aktiv om kolumnen inte finns
                }
            }
            unset($u);
            echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('AdminController::get_users: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta användare'], JSON_UNESCAPED_UNICODE);
        }
    }
}
