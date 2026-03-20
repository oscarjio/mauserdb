<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class ProfileController {
    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Du måste vara inloggad för att uppdatera kontot.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Kontrollera session-timeout (inaktivitet)
        if (!AuthHelper::checkSessionTimeout()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT id, username, email, password, admin, operator_id FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProfileController::handle fetch user: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av användarprofil.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$user) {
            session_unset();
            session_destroy();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Användaren hittades inte.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => ((int)$user['admin'] === 1) ? 'admin' : 'user',
                    'operator_id' => $user['operator_id'] ? (int)$user['operator_id'] : null
                ]
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Endast POST-metod tillåten.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = isset($data['email']) ? strip_tags(trim($data['email'])) : null;
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $operatorId = array_key_exists('operator_id', $data) ? $data['operator_id'] : 'SKIP';

        $fields = [];
        $params = [];

        if ($email !== null && $email !== '' && $email !== $user['email']) {
            if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig e-postadress.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $fields[] = 'email = ?';
            $params[] = $email;
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8 || strlen($newPassword) > 255 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Lösenordet måste vara minst 8 tecken och innehålla både bokstäver och siffror.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            if (empty($currentPassword)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nuvarande lösenord krävs för att ändra lösenord.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Rate limiting — förhindra brute-force av nuvarande lösenord via profilsidan
            AuthHelper::ensureRateLimitTable($pdo);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $pwChangeIp = 'pwchange:' . $ip;
            if (AuthHelper::isRateLimited($pdo, $pwChangeIp)) {
                $remaining = AuthHelper::getLockoutRemaining($pdo, $pwChangeIp);
                error_log('ProfileController::handle: Rate limit för lösenordsbyte, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', IP=' . $ip);
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'error' => "För många misslyckade lösenordsförsök. Försök igen om {$remaining} minuter."
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!AuthHelper::verifyPassword($currentPassword, $user['password'])) {
                AuthHelper::recordAttempt($pdo, $pwChangeIp, $user['username'], false);
                error_log('ProfileController::handle: Felaktigt lösenord vid byte, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', username=' . ($user['username'] ?? 'unknown'));
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nuvarande lösenord är felaktigt.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            // Rensa misslyckade försök vid rätt lösenord
            AuthHelper::clearAttempts($pdo, $pwChangeIp);
            $fields[] = 'password = ?';
            $params[] = AuthHelper::hashPassword($newPassword);
        }

        // Operator ID (kan sättas till null för att ta bort kopplingen)
        if ($operatorId !== 'SKIP') {
            if ($operatorId === null || $operatorId === '') {
                $fields[] = 'operator_id = ?';
                $params[] = null;
            } else {
                $oid = (int)$operatorId;
                if ($oid > 0) {
                    $fields[] = 'operator_id = ?';
                    $params[] = $oid;
                }
            }
        }

        if (!$fields) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Inga ändringar att spara.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $pdo->beginTransaction();

            $params[] = $user['id'];
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);
            $changedFields = array_map(fn($f) => strtok($f, ' '), $fields);
            AuditLogger::log($pdo, 'update_profile', 'users', (int)$user['id'],
                'Profil uppdaterad: ' . implode(', ', $changedFields));

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT id, username, email, admin, operator_id FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$updatedUser) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Kunde inte verifiera uppdaterad profil.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $_SESSION['username'] = $updatedUser['username'];
            $_SESSION['email'] = $updatedUser['email'];
            $_SESSION['role'] = ((int)$updatedUser['admin'] === 1) ? 'admin' : 'user';
            $_SESSION['operator_id'] = $updatedUser['operator_id'] ? (int)$updatedUser['operator_id'] : null;

            echo json_encode([
                'success' => true,
                'message' => 'Konto uppdaterat.',
                'user' => [
                    'id' => $updatedUser['id'],
                    'username' => $updatedUser['username'],
                    'email' => $updatedUser['email'],
                    'role' => $_SESSION['role'],
                    'operator_id' => $_SESSION['operator_id']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ProfileController::update: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid uppdatering av profil.'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ProfileController::update: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel vid uppdatering av profil.'], JSON_UNESCAPED_UNICODE);
        }
    }

}

