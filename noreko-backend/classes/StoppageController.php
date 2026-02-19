<?php
/**
 * StoppageController - Hanterar stopporsakslogg för produktionslinjer
 *
 * GET  ?action=stoppage                    → Lista stopporsaker (med filter)
 * GET  ?action=stoppage&run=reasons        → Lista tillgängliga orsakskoder
 * GET  ?action=stoppage&run=stats          → Statistik per orsak (Pareto)
 * POST ?action=stoppage (action=create)    → Skapa ny stoppost
 * POST ?action=stoppage (action=update)    → Uppdatera stoppost
 * POST ?action=stoppage (action=delete)    → Ta bort stoppost
 */
class StoppageController {
    private $pdo;

    // Vitlistade produktionslinjer
    private const VALID_LINES = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje'];

    // Standardorsakskoder
    private $defaultReasons = [
        ['code' => 'PLAN_MAINT', 'name' => 'Planerat underhåll', 'category' => 'planned', 'color' => '#3b82f6'],
        ['code' => 'BREAKDOWN', 'name' => 'Haveri/Maskinfel', 'category' => 'unplanned', 'color' => '#ef4444'],
        ['code' => 'MATERIAL', 'name' => 'Materialbrist', 'category' => 'unplanned', 'color' => '#f97316'],
        ['code' => 'CHANGEOVER', 'name' => 'Produktbyte', 'category' => 'planned', 'color' => '#8b5cf6'],
        ['code' => 'CLEANING', 'name' => 'Rengöring', 'category' => 'planned', 'color' => '#06b6d4'],
        ['code' => 'QUALITY', 'name' => 'Kvalitetsproblem', 'category' => 'unplanned', 'color' => '#eab308'],
        ['code' => 'OPERATOR', 'name' => 'Personalbrist', 'category' => 'unplanned', 'color' => '#ec4899'],
        ['code' => 'OTHER', 'name' => 'Övrigt', 'category' => 'unplanned', 'color' => '#6b7280'],
    ];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    public function handle() {
        session_start();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run = $_GET['run'] ?? '';

        if ($method === 'GET') {
            if ($run === 'reasons') {
                $this->getReasons();
            } elseif ($run === 'stats') {
                $this->getStats();
            } else {
                $this->getStoppages();
            }
            return;
        }

        if ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Ej inloggad']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';

            if ($action === 'create') {
                $this->createStoppage($data);
            } elseif ($action === 'update') {
                $this->updateStoppage($data);
            } elseif ($action === 'delete') {
                $this->deleteStoppage($data);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig action']);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod']);
    }

    private function ensureTablesExist() {
        try {
            // Stopporsakskoder
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `stoppage_reasons` (
                `id` int NOT NULL AUTO_INCREMENT,
                `code` varchar(50) NOT NULL,
                `name` varchar(100) NOT NULL,
                `category` enum('planned','unplanned') NOT NULL DEFAULT 'unplanned',
                `color` varchar(7) DEFAULT '#6b7280',
                `active` tinyint(1) NOT NULL DEFAULT 1,
                `sort_order` int NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            // Sätt in standardorsaker om tabellen är tom
            $count = $this->pdo->query("SELECT COUNT(*) FROM stoppage_reasons")->fetchColumn();
            if ($count == 0) {
                $stmt = $this->pdo->prepare("INSERT INTO stoppage_reasons (code, name, category, color, sort_order) VALUES (?, ?, ?, ?, ?)");
                foreach ($this->defaultReasons as $i => $reason) {
                    $stmt->execute([$reason['code'], $reason['name'], $reason['category'], $reason['color'], $i]);
                }
            }

            // Stopporsakslogg
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `stoppage_log` (
                `id` int NOT NULL AUTO_INCREMENT,
                `line` varchar(50) NOT NULL DEFAULT 'rebotling',
                `reason_id` int NOT NULL,
                `start_time` datetime NOT NULL,
                `end_time` datetime DEFAULT NULL,
                `duration_minutes` int DEFAULT NULL,
                `comment` text,
                `user_id` int DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_line` (`line`),
                KEY `idx_reason` (`reason_id`),
                KEY `idx_start` (`start_time`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (PDOException $e) {
            error_log('StoppageController ensureTablesExist: ' . $e->getMessage());
        }
    }

    private function getReasons() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM stoppage_reasons WHERE active = 1 ORDER BY sort_order, name");
            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        } catch (PDOException $e) {
            error_log('getReasons: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta orsakskoder']);
        }
    }

    private function getStoppages() {
        try {
            $line = $_GET['line'] ?? 'rebotling';
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $period = $_GET['period'] ?? 'week';

            $dateFilter = $this->getDateFilter($period);

            $stmt = $this->pdo->prepare("
                SELECT s.*, r.code as reason_code, r.name as reason_name,
                       r.category, r.color, u.username as user_name
                FROM stoppage_log s
                JOIN stoppage_reasons r ON s.reason_id = r.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.line = ? AND s.start_time >= ?
                ORDER BY s.start_time DESC
            ");
            $stmt->execute([$line, $dateFilter]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        } catch (PDOException $e) {
            error_log('getStoppages: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta stopporsaker']);
        }
    }

    private function getStats() {
        try {
            $line = $_GET['line'] ?? 'rebotling';
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $period = $_GET['period'] ?? 'month';

            $dateFilter = $this->getDateFilter($period);

            // Pareto-analys: total stopptid per orsak
            $stmt = $this->pdo->prepare("
                SELECT r.code, r.name, r.category, r.color,
                       COUNT(*) as count,
                       SUM(COALESCE(s.duration_minutes, 0)) as total_minutes,
                       ROUND(AVG(COALESCE(s.duration_minutes, 0)), 1) as avg_minutes
                FROM stoppage_log s
                JOIN stoppage_reasons r ON s.reason_id = r.id
                WHERE s.line = ? AND s.start_time >= ?
                GROUP BY r.id, r.code, r.name, r.category, r.color
                ORDER BY total_minutes DESC
            ");
            $stmt->execute([$line, $dateFilter]);
            $reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total stopptid
            $totalMinutes = array_sum(array_column($reasons, 'total_minutes'));
            $totalCount = array_sum(array_column($reasons, 'count'));

            // Planerat vs oplanerat
            $planned = 0;
            $unplanned = 0;
            foreach ($reasons as $r) {
                if ($r['category'] === 'planned') {
                    $planned += (int)$r['total_minutes'];
                } else {
                    $unplanned += (int)$r['total_minutes'];
                }
            }

            // Stopptid per dag (för trendgraf)
            $stmtDaily = $this->pdo->prepare("
                SELECT DATE(s.start_time) as dag,
                       SUM(COALESCE(s.duration_minutes, 0)) as total_minutes,
                       COUNT(*) as count
                FROM stoppage_log s
                WHERE s.line = ? AND s.start_time >= ?
                GROUP BY DATE(s.start_time)
                ORDER BY dag
            ");
            $stmtDaily->execute([$line, $dateFilter]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'reasons' => $reasons,
                    'total_minutes' => $totalMinutes,
                    'total_count' => $totalCount,
                    'planned_minutes' => $planned,
                    'unplanned_minutes' => $unplanned,
                    'daily' => $stmtDaily->fetchAll(PDO::FETCH_ASSOC)
                ]
            ]);
        } catch (PDOException $e) {
            error_log('getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta stoppstatistik']);
        }
    }

    private function createStoppage($data) {
        try {
            $line = $data['line'] ?? 'rebotling';
            $reasonId = intval($data['reason_id'] ?? 0);
            $startTime = $data['start_time'] ?? '';
            $endTime = $data['end_time'] ?? null;
            $comment = trim($data['comment'] ?? '');

            if ($reasonId <= 0 || empty($startTime)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Orsak och starttid krävs']);
                return;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $startTime)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt datumformat för starttid']);
                return;
            }

            $durationMinutes = null;
            if ($endTime && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $endTime)) {
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                $durationMinutes = max(0, (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60));
            }

            $userId = intval($_SESSION['user_id']);

            $stmt = $this->pdo->prepare("
                INSERT INTO stoppage_log (line, reason_id, start_time, end_time, duration_minutes, comment, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$line, $reasonId, $startTime, $endTime, $durationMinutes, $comment, $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'Stoppost registrerad',
                'id' => $this->pdo->lastInsertId()
            ]);
        } catch (PDOException $e) {
            error_log('createStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte registrera stoppost']);
        }
    }

    private function updateStoppage($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt ID']);
                return;
            }

            // Check ownership or admin
            $this->checkAccess($id);

            $fields = [];
            $params = [];

            if (isset($data['reason_id'])) {
                $fields[] = 'reason_id = ?';
                $params[] = intval($data['reason_id']);
            }
            if (isset($data['end_time'])) {
                $endTime = $data['end_time'];
                if ($endTime && !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $endTime)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ogiltigt datumformat']);
                    return;
                }
                $fields[] = 'end_time = ?';
                $params[] = $endTime ?: null;

                // Beräkna om duration
                if ($endTime) {
                    $stmt = $this->pdo->prepare("SELECT start_time FROM stoppage_log WHERE id = ?");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $start = new DateTime($row['start_time']);
                        $end = new DateTime($endTime);
                        $fields[] = 'duration_minutes = ?';
                        $params[] = max(0, (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60));
                    }
                }
            }
            if (isset($data['comment'])) {
                $fields[] = 'comment = ?';
                $params[] = trim($data['comment']);
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Inga fält att uppdatera']);
                return;
            }

            $params[] = $id;
            $sql = 'UPDATE stoppage_log SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $this->pdo->prepare($sql)->execute($params);

            echo json_encode(['success' => true, 'message' => 'Stoppost uppdaterad']);
        } catch (PDOException $e) {
            error_log('updateStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera stoppost']);
        }
    }

    private function deleteStoppage($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt ID']);
                return;
            }

            $this->checkAccess($id);

            $this->pdo->prepare("DELETE FROM stoppage_log WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Stoppost borttagen']);
        } catch (PDOException $e) {
            error_log('deleteStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort stoppost']);
        }
    }

    private function checkAccess($id) {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return;
        }
        $stmt = $this->pdo->prepare("SELECT user_id FROM stoppage_log WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Åtkomst nekad']);
            exit;
        }
    }

    private function getDateFilter($period) {
        switch ($period) {
            case 'today': return date('Y-m-d 00:00:00');
            case 'week': return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month': return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year': return date('Y-m-d 00:00:00', strtotime('-365 days'));
            default: return date('Y-m-d 00:00:00', strtotime('-7 days'));
        }
    }
}
