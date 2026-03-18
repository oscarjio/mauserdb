<?php
require_once __DIR__ . '/AuditController.php';
/**
 * LineSkiftrapportController – generisk skiftrapport för tvattlinje, saglinje, klassificeringslinje.
 * Endpoint: GET/POST /api.php?action=lineskiftrapport&line={tvattlinje|saglinje|klassificeringslinje}
 */
class LineSkiftrapportController {
    private $pdo;
    private static $allowedLines = ['tvattlinje', 'saglinje', 'klassificeringslinje'];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }
        $line = strtolower(trim($_GET['line'] ?? ''));

        if (!in_array($line, self::$allowedLines, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig linje. Tillåtna: ' . implode(', ', self::$allowedLines)], JSON_UNESCAPED_UNICODE);
            return;
        }

        $table = $line . '_skiftrapport';
        $this->ensureTable($table);

        if ($method === 'GET') {
            $this->getReports($table);
            return;
        }

        if ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $data['action'] ?? '';

            switch ($action) {
                case 'create':
                    $this->createReport($table, $data);
                    break;
                case 'update':
                    $this->checkOwnerOrAdmin($table, $data['id'] ?? 0);
                    $this->updateReport($table, $data);
                    break;
                case 'delete':
                    $this->checkOwnerOrAdmin($table, $data['id'] ?? 0);
                    $this->deleteReport($table, $data);
                    break;
                case 'updateInlagd':
                    $this->checkAdmin();
                    $this->updateInlagd($table, $data);
                    break;
                case 'bulkDelete':
                    $this->checkAdmin();
                    $this->bulkDelete($table, $data);
                    break;
                case 'bulkUpdateInlagd':
                    $this->checkAdmin();
                    $this->bulkUpdateInlagd($table, $data);
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltig action'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod'], JSON_UNESCAPED_UNICODE);
    }

    // ========== Auth Helpers ==========

    private function checkAdmin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function checkOwnerOrAdmin($table, $reportId) {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Inte inloggad'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT user_id FROM `$table` WHERE id = ?");
            $stmt->execute([intval($reportId)]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$report) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Rapport hittades inte'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ((int)$report['user_id'] !== (int)$_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Du kan bara ändra dina egna rapporter'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (PDOException $e) {
            error_log('LineSkiftrapportController::checkOwnerOrAdmin: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ========== Schema ==========

    private function ensureTable($table) {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() === 0) {
                $sql = "CREATE TABLE IF NOT EXISTS `$table` (
                    `id`         INT NOT NULL AUTO_INCREMENT,
                    `datum`      DATE NOT NULL,
                    `antal_ok`   INT NOT NULL DEFAULT 0,
                    `antal_ej_ok` INT NOT NULL DEFAULT 0,
                    `totalt`     INT NOT NULL DEFAULT 0,
                    `kommentar`  TEXT DEFAULT NULL,
                    `inlagd`     TINYINT(1) NOT NULL DEFAULT 0,
                    `user_id`    INT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_datum` (`datum`),
                    KEY `idx_user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                $this->pdo->exec($sql);
            }
        } catch (PDOException $e) {
            error_log("LineSkiftrapportController::ensureTable($table): " . $e->getMessage());
        }
    }

    // ========== CRUD ==========

    private function getReports($table) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt,
                       r.kommentar, r.inlagd, r.user_id, r.created_at, r.updated_at,
                       u.username AS user_name
                FROM `$table` r
                LEFT JOIN users u ON r.user_id = u.id
                ORDER BY r.datum DESC, r.id DESC
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log("LineSkiftrapportController::getReports($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta rapporter'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function createReport($table, $data) {
        try {
            $datum = trim($data['datum'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $antal_ok    = max(0, min(999999, intval($data['antal_ok'] ?? 0)));
            $antal_ej_ok = max(0, min(999999, intval($data['antal_ej_ok'] ?? 0)));
            $totalt      = $antal_ok + $antal_ej_ok;
            $kommentar   = strip_tags(trim($data['kommentar'] ?? '')) ?: null;
            if ($kommentar !== null && mb_strlen($kommentar) > 2000) {
                $kommentar = mb_substr($kommentar, 0, 2000);
            }
            $user_id     = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("
                INSERT INTO `$table` (datum, antal_ok, antal_ej_ok, totalt, kommentar, user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$datum, $antal_ok, $antal_ej_ok, $totalt, $kommentar, $user_id]);
            $newId = (int)$this->pdo->lastInsertId();
            AuditLogger::log($this->pdo, 'create_rapport', $table, $newId,
                "Skapad: datum=$datum, antal_ok=$antal_ok, totalt=$totalt");
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Rapport skapad', 'id' => $newId], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::createReport($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte skapa rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateReport($table, $data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $fields = [];
            $params = [];

            if (isset($data['datum'])) {
                $datum = trim($data['datum']);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $fields[] = 'datum = ?';
                $params[] = $datum;
            }
            if (isset($data['antal_ok'])) {
                $fields[] = 'antal_ok = ?';
                $params[] = max(0, min(999999, intval($data['antal_ok'])));
            }
            if (isset($data['antal_ej_ok'])) {
                $fields[] = 'antal_ej_ok = ?';
                $params[] = max(0, min(999999, intval($data['antal_ej_ok'])));
            }
            if (array_key_exists('kommentar', $data)) {
                $kommentar = strip_tags(trim($data['kommentar'])) ?: null;
                if ($kommentar !== null && mb_strlen($kommentar) > 2000) {
                    $kommentar = mb_substr($kommentar, 0, 2000);
                }
                $fields[] = 'kommentar = ?';
                $params[] = $kommentar;
            }

            // Räkna om totalt om några av antal-fälten ändrats
            if (isset($data['antal_ok']) || isset($data['antal_ej_ok'])) {
                $stmt = $this->pdo->prepare("SELECT antal_ok, antal_ej_ok FROM `$table` WHERE id = ?");
                $stmt->execute([$id]);
                $cur = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$cur) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Rapport hittades inte'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $final_ok    = isset($data['antal_ok'])    ? intval($data['antal_ok'])    : (int)$cur['antal_ok'];
                $final_ej_ok = isset($data['antal_ej_ok']) ? intval($data['antal_ej_ok']) : (int)$cur['antal_ej_ok'];
                $fields[] = 'totalt = ?';
                $params[] = $final_ok + $final_ej_ok;
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga fält att uppdatera'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $params[] = $id;
            $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            AuditLogger::log($this->pdo, 'update_rapport', $table, $id,
                'Rapport uppdaterad');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Rapport uppdaterad'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::updateReport($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function deleteReport($table, $data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->execute([$id]);
            AuditLogger::log($this->pdo, 'delete_rapport', $table, $id,
                'Rapport borttagen');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Rapport borttagen'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::deleteReport($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateInlagd($table, $data) {
        try {
            $id     = intval($data['id'] ?? 0);
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE `$table` SET inlagd = ? WHERE id = ?");
            $stmt->execute([$inlagd, $id]);
            AuditLogger::log($this->pdo, 'update_inlagd', $table, $id, 'inlagd=' . $inlagd);
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Status uppdaterad', 'inlagd' => $inlagd], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::updateInlagd($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera status'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkDelete($table, $data) {
        try {
            $ids = $data['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga giltiga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE id IN ($placeholders)");
            $stmt->execute(array_values($ids));
            AuditLogger::log($this->pdo, 'bulk_delete_rapport', $table, null,
                count($ids) . ' rapporter borttagna (ID: ' . implode(', ', $ids) . ')');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => count($ids) . ' rapport(er) borttagna'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::bulkDelete($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort rapporter'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkUpdateInlagd($table, $data) {
        try {
            $ids    = $data['ids'] ?? [];
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga giltiga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$inlagd], $ids);
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE `$table` SET inlagd = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            AuditLogger::log($this->pdo, 'bulk_update_inlagd', $table, null,
                count($ids) . ' rader, inlagd=' . $inlagd . ', ids=' . implode(',', $ids));
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => count($ids) . ' rapport(er) uppdaterade'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::bulkUpdateInlagd($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera status'], JSON_UNESCAPED_UNICODE);
        }
    }
}
