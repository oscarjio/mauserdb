<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

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
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }
        $run = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            if ($run === 'reasons') {
                $this->getReasons();
            } elseif ($run === 'stats') {
                $this->getStats();
            } elseif ($run === 'weekly_summary') {
                $this->getWeeklySummary();
            } elseif ($run === 'pareto') {
                $this->getPareto();
            } elseif ($run === 'pattern-analysis') {
                $this->getPatternAnalysis();
            } else {
                $this->getStoppages();
            }
            return;
        }

        if ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Kontrollera session-timeout (inaktivitet)
            if (!AuthHelper::checkSessionTimeout()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $action = trim($data['action'] ?? '');

            if ($action === 'create') {
                $this->createStoppage($data);
            } elseif ($action === 'update') {
                $this->updateStoppage($data);
            } elseif ($action === 'delete') {
                $this->deleteStoppage($data);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig action'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod'], JSON_UNESCAPED_UNICODE);
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
            if ((int)$count === 0) {
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
            error_log('StoppageController::ensureTablesExist: ' . $e->getMessage());
        }
    }

    private function getReasons() {
        try {
            $stmt = $this->pdo->query("SELECT id, code, name, category, color, sort_order FROM stoppage_reasons WHERE active = 1 ORDER BY sort_order, name");
            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('StoppageController::getReasons: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta orsakskoder'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getStoppages() {
        try {
            $line = trim($_GET['line'] ?? 'rebotling');
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $period = trim($_GET['period'] ?? 'week');

            $dateFilter = $this->getDateFilter($period);

            $stmt = $this->pdo->prepare("
                SELECT s.id, s.line, s.reason_id, s.start_time, s.end_time,
                       s.duration_minutes, s.comment, s.user_id, s.created_at,
                       COALESCE(r.code, 'UNKNOWN') as reason_code, COALESCE(r.name, 'Okänd orsak') as reason_name,
                       r.category, r.color, u.username as user_name
                FROM stoppage_log s
                LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.line = ? AND s.start_time >= ?
                ORDER BY s.start_time DESC
                LIMIT 2000
            ");
            $stmt->execute([$line, $dateFilter]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('StoppageController::getStoppages: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta stopporsaker'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getStats() {
        try {
            $line = trim($_GET['line'] ?? 'rebotling');
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $period = trim($_GET['period'] ?? 'month');

            $dateFilter = $this->getDateFilter($period);

            // Pareto-analys: total stopptid per orsak
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(r.code, 'UNKNOWN') AS code, COALESCE(r.name, 'Okänd orsak') AS name, r.category, r.color,
                       COUNT(*) as count,
                       SUM(COALESCE(s.duration_minutes, 0)) as total_minutes,
                       ROUND(AVG(COALESCE(s.duration_minutes, 0)), 1) as avg_minutes
                FROM stoppage_log s
                LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('StoppageController::getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta stoppstatistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function createStoppage($data) {
        try {
            $line = $data['line'] ?? 'rebotling';
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $reasonId = intval($data['reason_id'] ?? 0);
            $startTime = $data['start_time'] ?? '';
            $endTime = $data['end_time'] ?? null;
            $comment = strip_tags(trim($data['comment'] ?? ''));

            if ($reasonId <= 0 || empty($startTime)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Orsak och starttid krävs'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $startTime)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat för starttid'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $durationMinutes = null;
            if ($endTime && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $endTime)) {
                $tz = new DateTimeZone('Europe/Stockholm');
                try {
                    $start = new DateTime($startTime, $tz);
                    $end = new DateTime($endTime, $tz);
                } catch (Exception $e) {
                    error_log('StoppageController::createStoppage — ogiltigt datumvärde: ' . $e->getMessage());
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumvärde'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                if ($end < $start) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Sluttid kan inte vara före starttid'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $durationMinutes = max(0, (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60));
            }

            // Begränsa kommentarlängd
            if (mb_strlen($comment) > 500) {
                $comment = mb_substr($comment, 0, 500);
            }

            $userId = (int)$_SESSION['user_id'];

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO stoppage_log (line, reason_id, start_time, end_time, duration_minutes, comment, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$line, $reasonId, $startTime, $endTime, $durationMinutes, $comment, $userId]);

            $newId = (int)$this->pdo->lastInsertId();
            AuditLogger::log($this->pdo, 'create_stoppage', 'stoppage_log', $newId,
                "Skapad: line=$line, reason_id=$reasonId, start=$startTime");

            $this->pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Stoppost registrerad',
                'id' => $newId
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('StoppageController::createStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte registrera stoppost'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateStoppage($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Check ownership or admin
            $this->checkAccess($id);

            $fields = [];
            $params = [];

            if (isset($data['reason_id'])) {
                $fields[] = 'reason_id = ?';
                $params[] = (int)$data['reason_id'];
            }
            if (isset($data['end_time'])) {
                $endTime = $data['end_time'];
                if ($endTime && !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $endTime)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
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
                        $tz = new DateTimeZone('Europe/Stockholm');
                        try {
                            $start = new DateTime($row['start_time'], $tz);
                            $end = new DateTime($endTime, $tz);
                            $fields[] = 'duration_minutes = ?';
                            $params[] = max(0, (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60));
                        } catch (Exception $e) {
                            error_log('StoppageController::updateStoppage: Ogiltigt datumvärde: ' . $e->getMessage());
                        }
                    }
                }
            }
            if (isset($data['comment'])) {
                $comment = strip_tags(trim($data['comment']));
                if (mb_strlen($comment) > 500) {
                    $comment = mb_substr($comment, 0, 500);
                }
                $fields[] = 'comment = ?';
                $params[] = $comment;
            }
            if (isset($data['duration_minutes'])) {
                $dm = $data['duration_minutes'];
                $fields[] = 'duration_minutes = ?';
                $params[] = ($dm === null || $dm === '') ? null : max(0, min(14400, intval($dm)));
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga fält att uppdatera'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $params[] = $id;
            $sql = 'UPDATE stoppage_log SET ' . implode(', ', $fields) . ' WHERE id = ?';

            $this->pdo->beginTransaction();
            $this->pdo->prepare($sql)->execute($params);

            AuditLogger::log($this->pdo, 'update_stoppage', 'stoppage_log', $id,
                'Uppdaterad: fields=' . implode(',', array_map(fn($f) => strtok($f, ' '), $fields)));
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Stoppost uppdaterad'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('StoppageController::updateStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera stoppost'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function deleteStoppage($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->checkAccess($id);

            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM stoppage_log WHERE id = ?")->execute([$id]);
            AuditLogger::log($this->pdo, 'delete_stoppage', 'stoppage_log', $id, 'Stoppost borttagen');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Stoppost borttagen'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('StoppageController::deleteStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort stoppost'], JSON_UNESCAPED_UNICODE);
        }
    }


    private function getPareto() {
        try {
            $line = trim($_GET['line'] ?? 'rebotling');
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $dagar = max(1, min(365, intval($_GET['dagar'] ?? 30)));

            $stmt = $this->pdo->prepare("
                SELECT COALESCE(r.name, 'Okänd orsak') as orsak,
                       COUNT(*) AS antal,
                       COALESCE(SUM(s.duration_minutes), 0) AS total_minuter
                FROM stoppage_log s
                LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                WHERE s.line = ?
                  AND s.start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND s.duration_minutes IS NOT NULL
                  AND s.duration_minutes > 0
                GROUP BY r.id, r.name
                ORDER BY total_minuter DESC
                LIMIT 20
            ");
            $stmt->execute([$line, $dagar]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalMinuter = array_sum(array_column($rows, 'total_minuter'));

            $kumulativ = 0;
            $orsaker = [];
            foreach ($rows as $row) {
                $pct = $totalMinuter > 0 ? round($row['total_minuter'] / $totalMinuter * 100, 1) : 0;
                $kumulativ = round($kumulativ + $pct, 1);
                $orsaker[] = [
                    'orsak'         => $row['orsak'],
                    'antal'         => (int)$row['antal'],
                    'total_minuter' => (int)$row['total_minuter'],
                    'pct'           => $pct,
                    'kumulativ_pct' => $kumulativ,
                ];
            }

            echo json_encode([
                'success'        => true,
                'orsaker'        => $orsaker,
                'total_minuter'  => (int)$totalMinuter,
                'dagar'          => $dagar,
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('StoppageController::getPareto: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta Pareto-data'], JSON_UNESCAPED_UNICODE);
        }
    }


    private function getPatternAnalysis() {
        try {
            $line = trim($_GET['line'] ?? 'rebotling');
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';
            $days = max(1, min(365, intval($_GET['days'] ?? 30)));

            // 1. Återkommande stopp — orsaker som inträffat 3+ gånger på 7 dagar
            $stmtRepeat = $this->pdo->prepare("
                SELECT s.reason_id, COALESCE(r.name, 'Okänd') AS orsak, r.category,
                    COUNT(*) AS antal_7d,
                    ROUND(AVG(COALESCE(s.duration_minutes, 0)), 1) AS snitt_tid,
                    MIN(s.start_time) AS forsta,
                    MAX(s.start_time) AS senaste
                FROM stoppage_log s
                LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                WHERE s.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND s.line = ?
                GROUP BY s.reason_id, r.name, r.category
                HAVING COUNT(*) >= 3
                ORDER BY antal_7d DESC
            ");
            $stmtRepeat->execute([$line]);
            $repeatStoppages = $stmtRepeat->fetchAll(PDO::FETCH_ASSOC);

            // Cast number fields
            foreach ($repeatStoppages as &$r) {
                $r['antal_7d'] = (int)$r['antal_7d'];
                $r['snitt_tid'] = $r['snitt_tid'] !== null ? (float)$r['snitt_tid'] : null;
                $r['reason_id'] = (int)$r['reason_id'];
            }
            unset($r);

            // 2. Fördelning av stopp per timme (0-23)
            $stmtHourly = $this->pdo->prepare("
                SELECT HOUR(start_time) AS timme,
                    COUNT(*) AS antal,
                    ROUND(AVG(COALESCE(duration_minutes, 0)), 1) AS snitt_min
                FROM stoppage_log
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND line = ?
                GROUP BY HOUR(start_time)
                ORDER BY timme
            ");
            $stmtHourly->execute([$days, $line]);
            $hourlyRaw = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

            // Fill all 24 hours
            $hourlyDistribution = [];
            $hourlyMap = [];
            foreach ($hourlyRaw as $row) {
                $hourlyMap[(int)$row['timme']] = $row;
            }
            for ($h = 0; $h < 24; $h++) {
                if (isset($hourlyMap[$h])) {
                    $hourlyDistribution[] = [
                        'timme' => $h,
                        'antal' => (int)$hourlyMap[$h]['antal'],
                        'snitt_min' => $hourlyMap[$h]['snitt_min'] !== null ? (float)$hourlyMap[$h]['snitt_min'] : null,
                    ];
                } else {
                    $hourlyDistribution[] = ['timme' => $h, 'antal' => 0, 'snitt_min' => null];
                }
            }

            // 3. Kostsammaste orsaker — topp 5 per total stopptid
            $stmtCostly = $this->pdo->prepare("
                SELECT COALESCE(r.name, 'Okänd') AS orsak, r.category,
                    COUNT(*) AS antal,
                    COALESCE(SUM(s.duration_minutes), 0) AS total_min
                FROM stoppage_log s
                LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                WHERE s.start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND s.line = ?
                GROUP BY r.id, COALESCE(r.name, 'Okänd'), r.category
                ORDER BY total_min DESC
                LIMIT 5
            ");
            $stmtCostly->execute([$days, $line]);
            $costlyReasons = $stmtCostly->fetchAll(PDO::FETCH_ASSOC);

            $totalMin = array_sum(array_column($costlyReasons, 'total_min'));
            foreach ($costlyReasons as &$c) {
                $c['antal'] = (int)$c['antal'];
                $c['total_min'] = (int)$c['total_min'];
                $c['pct'] = $totalMin > 0 ? round($c['total_min'] / $totalMin * 100, 1) : 0;
            }
            unset($c);

            echo json_encode([
                'success' => true,
                'repeat_stoppages' => $repeatStoppages,
                'hourly_distribution' => $hourlyDistribution,
                'costly_reasons' => $costlyReasons,
                'period_days' => $days,
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('StoppageController::getPatternAnalysis: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta mönsteranalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getWeeklySummary() {
        try {
            $line = trim($_GET['line'] ?? 'rebotling');
            if (!in_array($line, self::VALID_LINES, true)) $line = 'rebotling';

            // Bugfix #285: strtotime('monday this week') ger nasta mandag pa sondagar
            $dow = (int)date('N'); // 1=man..7=son
            $thisWeekStart = date('Y-m-d 00:00:00', strtotime('-' . ($dow - 1) . ' days'));
            $prevWeekStart = date('Y-m-d 00:00:00', strtotime('-' . ($dow - 1 + 7) . ' days'));
            $prevWeekEnd   = date('Y-m-d 23:59:59', strtotime('-' . $dow . ' days'));

            // This week
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count,
                       COALESCE(SUM(duration_minutes), 0) as total_minutes,
                       COALESCE(ROUND(AVG(duration_minutes), 0), 0) as avg_minutes
                FROM stoppage_log
                WHERE line = ? AND start_time >= ?
            ");
            $stmt->execute([$line, $thisWeekStart]);
            $thisWeek = $stmt->fetch(PDO::FETCH_ASSOC);

            // Prev week
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count,
                       COALESCE(SUM(duration_minutes), 0) as total_minutes,
                       COALESCE(ROUND(AVG(duration_minutes), 0), 0) as avg_minutes
                FROM stoppage_log
                WHERE line = ? AND start_time >= ? AND start_time <= ?
            ");
            $stmt->execute([$line, $prevWeekStart, $prevWeekEnd]);
            $prevWeek = $stmt->fetch(PDO::FETCH_ASSOC);

            // Daily counts last 14 days (for mini bar chart)
            $stmt = $this->pdo->prepare("
                SELECT DATE(start_time) as dag,
                       COUNT(*) as count,
                       COALESCE(SUM(duration_minutes), 0) as total_minutes
                FROM stoppage_log
                WHERE line = ? AND start_time >= ?
                GROUP BY DATE(start_time)
                ORDER BY dag
            ");
            $stmt->execute([$line, date('Y-m-d 00:00:00', strtotime('-14 days'))]);
            $daily14 = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'this_week'  => $thisWeek,
                    'prev_week'  => $prevWeek,
                    'daily_14'   => $daily14
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('StoppageController::getWeeklySummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckosummering'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function checkAccess($id) {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return;
        }
        $stmt = $this->pdo->prepare("SELECT user_id FROM stoppage_log WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['user_id'] !== (int)$_SESSION['user_id']) {
            error_log('StoppageController::checkAccess: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', stoppage_id=' . $id);
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
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
