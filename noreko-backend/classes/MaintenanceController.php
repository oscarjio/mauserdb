<?php
/**
 * MaintenanceController.php
 * Hanterar underhållslogg — planerat/akut underhåll, reparationer, driftstopp.
 *
 * Endpoints (alla kräver admin-session):
 * GET  ?action=maintenance&run=list   → Lista poster (filter: line, status, from_date)
 * POST ?action=maintenance&run=add    → Lägg till post
 * POST ?action=maintenance&run=update&id=X → Uppdatera post
 * POST ?action=maintenance&run=delete&id=X → Soft-delete (status=avbokat)
 * GET  ?action=maintenance&run=stats  → KPI-statistik senaste 30 dagar
 */
class MaintenanceController {
    private $pdo;

    private const VALID_LINES = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje', 'allmant'];
    private const VALID_TYPES = ['planerat', 'akut', 'inspektion', 'kalibrering', 'rengoring', 'ovrigt'];
    private const VALID_STATUSES = ['planerat', 'pagaende', 'klart', 'avbokat'];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Åtkomst nekad']);
            return;
        }

        $run = $_GET['run'] ?? 'list';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        match ($run) {
            'list'   => $this->listEntries(),
            'add'    => $method === 'POST' ? $this->addEntry() : $this->sendError('POST krävs', 405),
            'update' => $method === 'POST' ? $this->updateEntry() : $this->sendError('POST krävs', 405),
            'delete' => $method === 'POST' ? $this->deleteEntry() : $this->sendError('POST krävs', 405),
            'stats'  => $this->getStats(),
            default  => $this->sendError('Okänd metod', 400)
        };
    }

    private function listEntries(): void {
        try {
            $line     = $_GET['line'] ?? null;
            $status   = $_GET['status'] ?? null;
            $fromDate = $_GET['from_date'] ?? null;

            // Validera filter-värden
            if ($line !== null && !in_array($line, self::VALID_LINES, true)) {
                $line = null;
            }
            if ($status !== null && !in_array($status, self::VALID_STATUSES, true)) {
                $status = null;
            }
            if ($fromDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
                $fromDate = null;
            }

            // Standardperiod: senaste 90 dagar om inget from_date anges
            if ($fromDate === null) {
                $fromDate = date('Y-m-d', strtotime('-90 days'));
            }

            $conditions = ['start_time >= :from_date'];
            $params = [':from_date' => $fromDate . ' 00:00:00'];

            if ($line !== null) {
                $conditions[] = 'line = :line';
                $params[':line'] = $line;
            }
            if ($status !== null) {
                $conditions[] = 'status = :status';
                $params[':status'] = $status;
            }

            $where = implode(' AND ', $conditions);

            $sql = "SELECT id, line, maintenance_type, title, description, start_time,
                           duration_minutes, performed_by, cost_sek, status, created_by, created_at
                    FROM maintenance_log
                    WHERE {$where}
                    ORDER BY start_time DESC
                    LIMIT 100";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Räkna totalt utan LIMIT (för display)
            $countSql = "SELECT COUNT(*) FROM maintenance_log WHERE {$where}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            echo json_encode([
                'entries'     => $entries,
                'total_count' => $total
            ]);
        } catch (PDOException $e) {
            error_log('MaintenanceController::listEntries: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta underhållsposter', 500);
        }
    }

    private function addEntry(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $title           = strip_tags(trim($data['title'] ?? ''));
            $line            = $data['line'] ?? 'rebotling';
            $maintenanceType = $data['maintenance_type'] ?? 'ovrigt';
            $description     = strip_tags(trim($data['description'] ?? ''));
            $startTime       = $data['start_time'] ?? '';
            $durationMinutes = isset($data['duration_minutes']) && $data['duration_minutes'] !== '' && $data['duration_minutes'] !== null
                               ? intval($data['duration_minutes']) : null;
            $performedBy     = strip_tags(trim($data['performed_by'] ?? ''));
            $costSek         = isset($data['cost_sek']) && $data['cost_sek'] !== '' && $data['cost_sek'] !== null
                               ? floatval($data['cost_sek']) : null;
            $status          = $data['status'] ?? 'klart';
            $createdBy       = intval($_SESSION['user_id']);

            // Validering
            if (empty($title)) {
                $this->sendError('Titel krävs', 400);
                return;
            }
            if (mb_strlen($title) > 150) {
                $this->sendError('Titel för lång (max 150 tecken)', 400);
                return;
            }
            if (!in_array($line, self::VALID_LINES, true)) {
                $this->sendError('Ogiltig linje', 400);
                return;
            }
            if (!in_array($maintenanceType, self::VALID_TYPES, true)) {
                $this->sendError('Ogiltig typ', 400);
                return;
            }
            if (!in_array($status, self::VALID_STATUSES, true)) {
                $this->sendError('Ogiltigt status', 400);
                return;
            }
            if (empty($startTime) || !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $startTime)) {
                $this->sendError('Ogiltig starttid', 400);
                return;
            }

            // Normalisera datetime (T → mellanslag)
            $startTime = str_replace('T', ' ', substr($startTime, 0, 16)) . ':00';

            $stmt = $this->pdo->prepare("
                INSERT INTO maintenance_log
                    (line, maintenance_type, title, description, start_time, duration_minutes,
                     performed_by, cost_sek, status, created_by)
                VALUES
                    (:line, :maintenance_type, :title, :description, :start_time, :duration_minutes,
                     :performed_by, :cost_sek, :status, :created_by)
            ");
            $stmt->execute([
                ':line'             => $line,
                ':maintenance_type' => $maintenanceType,
                ':title'            => $title,
                ':description'      => $description ?: null,
                ':start_time'       => $startTime,
                ':duration_minutes' => $durationMinutes,
                ':performed_by'     => $performedBy ?: null,
                ':cost_sek'         => $costSek,
                ':status'           => $status,
                ':created_by'       => $createdBy,
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Underhållspost sparad',
                'id'      => $newId
            ]);
        } catch (PDOException $e) {
            error_log('MaintenanceController::addEntry: ' . $e->getMessage());
            $this->sendError('Kunde inte spara underhållspost', 500);
        }
    }

    private function updateEntry(): void {
        try {
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                $this->sendError('Ogiltigt ID', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $fields = [];
            $params = [];

            if (isset($data['title'])) {
                $title = strip_tags(trim($data['title']));
                if (empty($title)) {
                    $this->sendError('Titel krävs', 400);
                    return;
                }
                if (mb_strlen($title) > 150) {
                    $this->sendError('Titel för lång', 400);
                    return;
                }
                $fields[] = 'title = :title';
                $params[':title'] = $title;
            }
            if (isset($data['line'])) {
                if (!in_array($data['line'], self::VALID_LINES, true)) {
                    $this->sendError('Ogiltig linje', 400);
                    return;
                }
                $fields[] = 'line = :line';
                $params[':line'] = $data['line'];
            }
            if (isset($data['maintenance_type'])) {
                if (!in_array($data['maintenance_type'], self::VALID_TYPES, true)) {
                    $this->sendError('Ogiltig typ', 400);
                    return;
                }
                $fields[] = 'maintenance_type = :maintenance_type';
                $params[':maintenance_type'] = $data['maintenance_type'];
            }
            if (array_key_exists('description', $data)) {
                $fields[] = 'description = :description';
                $params[':description'] = strip_tags(trim($data['description'])) ?: null;
            }
            if (isset($data['start_time'])) {
                $st = $data['start_time'];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $st)) {
                    $this->sendError('Ogiltig starttid', 400);
                    return;
                }
                $fields[] = 'start_time = :start_time';
                $params[':start_time'] = str_replace('T', ' ', substr($st, 0, 16)) . ':00';
            }
            if (array_key_exists('duration_minutes', $data)) {
                $dur = $data['duration_minutes'];
                $fields[] = 'duration_minutes = :duration_minutes';
                $params[':duration_minutes'] = ($dur !== null && $dur !== '') ? intval($dur) : null;
            }
            if (array_key_exists('performed_by', $data)) {
                $fields[] = 'performed_by = :performed_by';
                $params[':performed_by'] = strip_tags(trim($data['performed_by'])) ?: null;
            }
            if (array_key_exists('cost_sek', $data)) {
                $cost = $data['cost_sek'];
                $fields[] = 'cost_sek = :cost_sek';
                $params[':cost_sek'] = ($cost !== null && $cost !== '') ? floatval($cost) : null;
            }
            if (isset($data['status'])) {
                if (!in_array($data['status'], self::VALID_STATUSES, true)) {
                    $this->sendError('Ogiltigt status', 400);
                    return;
                }
                $fields[] = 'status = :status';
                $params[':status'] = $data['status'];
            }

            if (empty($fields)) {
                $this->sendError('Inga fält att uppdatera', 400);
                return;
            }

            $params[':id'] = $id;
            $sql = 'UPDATE maintenance_log SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $this->pdo->prepare($sql)->execute($params);

            echo json_encode(['success' => true, 'message' => 'Underhållspost uppdaterad']);
        } catch (PDOException $e) {
            error_log('MaintenanceController::updateEntry: ' . $e->getMessage());
            $this->sendError('Kunde inte uppdatera underhållspost', 500);
        }
    }

    private function deleteEntry(): void {
        try {
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                $this->sendError('Ogiltigt ID', 400);
                return;
            }

            // Soft delete: sätt status till 'avbokat' för att bevara historik
            $stmt = $this->pdo->prepare("UPDATE maintenance_log SET status = 'avbokat' WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Post hittades inte', 404);
                return;
            }

            echo json_encode(['success' => true, 'message' => 'Underhållspost borttagen']);
        } catch (PDOException $e) {
            error_log('MaintenanceController::deleteEntry: ' . $e->getMessage());
            $this->sendError('Kunde inte ta bort underhållspost', 500);
        }
    }

    private function getStats(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) AS total_events,
                    COALESCE(SUM(duration_minutes), 0) AS total_minutes,
                    COALESCE(SUM(cost_sek), 0) AS total_cost,
                    COUNT(CASE WHEN maintenance_type = 'akut' THEN 1 END) AS akut_count,
                    COUNT(CASE WHEN status = 'pagaende' THEN 1 END) AS pagaende_count
                FROM maintenance_log
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND status != 'avbokat'
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'stats'   => $stats
            ]);
        } catch (PDOException $e) {
            error_log('MaintenanceController::getStats: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta statistik', 500);
        }
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}
