<?php
require_once __DIR__ . '/AuditController.php';

class OperatorController {
    public function handle() {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Endast admin har behörighet.']);
            return;
        }
        global $pdo;
        AuditLogger::ensureTable($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';

            if ($action === 'create') {
                $name = trim($data['name'] ?? '');
                $number = isset($data['number']) ? intval($data['number']) : null;

                if (empty($name) || $number === null || $number <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Namn och nummer krävs']);
                    return;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO operators (name, number) VALUES (?, ?)");
                    $stmt->execute([$name, $number]);
                    $newId = $pdo->lastInsertId();
                    AuditLogger::log($pdo, 'create_operator', 'operator', (int)$newId,
                        "Skapade operatör: $name (#$number)",
                        null, ['name' => $name, 'number' => $number]
                    );
                    echo json_encode(['success' => true, 'message' => 'Operatör skapad', 'id' => $newId]);
                } catch (PDOException $e) {
                    error_log('OperatorController create: ' . $e->getMessage());
                    if ($e->getCode() == 23000) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => 'Operatörsnumret är redan registrerat']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Kunde inte skapa operatör']);
                    }
                }
                return;
            }

            $id = isset($data['id']) ? intval($data['id']) : null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID saknas']);
                return;
            }

            if ($action === 'update') {
                $name = trim($data['name'] ?? '');
                $number = isset($data['number']) ? intval($data['number']) : null;

                if (empty($name) || $number === null || $number <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Namn och nummer krävs']);
                    return;
                }

                try {
                    $stmt = $pdo->prepare("UPDATE operators SET name = ?, number = ? WHERE id = ?");
                    $stmt->execute([$name, $number, $id]);
                    AuditLogger::log($pdo, 'update_operator', 'operator', $id,
                        "Uppdaterade operatör #$id: $name (#$number)",
                        null, ['name' => $name, 'number' => $number]
                    );
                    echo json_encode(['success' => true, 'message' => 'Operatör uppdaterad']);
                } catch (PDOException $e) {
                    error_log('OperatorController update: ' . $e->getMessage());
                    if ($e->getCode() == 23000) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => 'Operatörsnumret är redan registrerat']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera operatör']);
                    }
                }
                return;
            }

            if ($action === 'delete') {
                try {
                    $stmt = $pdo->prepare("SELECT name, number FROM operators WHERE id = ?");
                    $stmt->execute([$id]);
                    $op = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$op) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Operatör hittades inte']);
                        return;
                    }

                    $stmt = $pdo->prepare("DELETE FROM operators WHERE id = ?");
                    $stmt->execute([$id]);
                    AuditLogger::log($pdo, 'delete_operator', 'operator', $id,
                        "Tog bort operatör: " . ($op['name'] ?? 'okänd') . " (#" . ($op['number'] ?? '?') . ")",
                        $op, null
                    );
                    echo json_encode(['success' => true, 'message' => 'Operatör borttagen']);
                } catch (PDOException $e) {
                    error_log('OperatorController delete: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort operatör']);
                }
                return;
            }

            if ($action === 'toggleActive') {
                try {
                    $stmt = $pdo->prepare("SELECT active, name FROM operators WHERE id = ?");
                    $stmt->execute([$id]);
                    $op = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$op) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Operatör hittades inte']);
                        return;
                    }

                    $newActive = $op['active'] == 1 ? 0 : 1;
                    $stmt = $pdo->prepare("UPDATE operators SET active = ? WHERE id = ?");
                    $stmt->execute([$newActive, $id]);
                    AuditLogger::log($pdo, 'toggle_operator_active', 'operator', $id,
                        ($newActive ? 'Aktiverade' : 'Inaktiverade') . " operatör: " . $op['name'],
                        ['active' => $op['active']], ['active' => $newActive]
                    );
                    echo json_encode(['success' => true, 'active' => $newActive]);
                } catch (PDOException $e) {
                    error_log('OperatorController toggleActive: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte ändra status']);
                }
                return;
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Okänd åtgärd']);
            return;
        }

        // GET - Hämta alla operatörer
        try {
            $stmt = $pdo->query("SELECT * FROM operators ORDER BY number");
            $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['operators' => $operators]);
        } catch (PDOException $e) {
            error_log('OperatorController GET: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Kunde inte hämta operatörer']);
        }
    }
}
