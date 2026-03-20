<?php
/**
 * AuditController - Hanterar aktivitetslogg / audit trail
 *
 * GET  ?action=audit                    → Lista audit-loggar (med filter)
 * GET  ?action=audit&run=stats          → Statistik per action/användare
 *
 * Audit-loggar skapas av andra controllers via AuditLogger::log()
 */
class AuditController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        AuditLogger::ensureTable($this->pdo);
    }

    public function handle() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);

        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            error_log('AuditController::handle: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', role=' . ($_SESSION['role'] ?? 'none'));
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        if ($run === 'stats') {
            $this->getStats();
        } elseif ($run === 'actions') {
            $this->getActions();
        } else {
            $this->getLogs();
        }
    }

    private function getLogs() {
        try {
            // Verify table exists (ensureTable may have failed silently)
            $check = $this->pdo->query("SHOW TABLES LIKE 'audit_log'");
            if (!$check || $check->rowCount() === 0) {
                echo json_encode(['success' => true, 'data' => [], 'total' => 0, 'page' => 1, 'pages' => 0], JSON_UNESCAPED_UNICODE);
                return;
            }

            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(200, max(10, intval($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $actionFilter = mb_substr(trim($_GET['filter_action'] ?? ''), 0, 100);
            $userFilter   = mb_substr(trim($_GET['filter_user'] ?? ''), 0, 100);
            $entityFilter = mb_substr(trim($_GET['filter_entity'] ?? ''), 0, 100);
            $searchText   = mb_substr(trim($_GET['search'] ?? ''), 0, 200);
            $periodFilter = trim($_GET['period'] ?? 'custom');
            $fromDate     = trim($_GET['from_date'] ?? '');
            $toDate       = trim($_GET['to_date'] ?? '');

            // Date range: explicit from/to takes priority over period preset
            if ($periodFilter !== 'custom' || (empty($fromDate) && empty($toDate))) {
                $dateStart = $this->getDateFilter($periodFilter);
                $dateEnd   = null;
            } else {
                $dateStart = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate))
                    ? $fromDate . ' 00:00:00'
                    : date('Y-m-d 00:00:00', strtotime('-30 days'));
                $dateEnd = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))
                    ? $toDate . ' 23:59:59'
                    : null;
                // Validera att from <= to, annars byt plats
                if ($dateEnd !== null && $dateStart > $dateEnd) {
                    [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
                }
                // Begränsa till max 365 dagar
                if ($dateEnd !== null) {
                    try {
                        $dtStart = new \DateTime($dateStart);
                        $dtEnd   = new \DateTime($dateEnd);
                        $diffDays = (int)$dtStart->diff($dtEnd)->days;
                        if ($diffDays > 365) {
                            $dateStart = date('Y-m-d 00:00:00', strtotime($dateEnd . ' -365 days'));
                        }
                    } catch (\Exception $e) {
                        error_log('AuditController: datumberäkning fallback — ' . $e->getMessage());
                        $dateStart = date('Y-m-d 00:00:00', strtotime('-30 days'));
                        $dateEnd   = date('Y-m-d 23:59:59');
                    }
                }
            }

            $where  = ['created_at >= ?'];
            $params = [$dateStart];

            if ($dateEnd) {
                $where[]  = 'created_at <= ?';
                $params[] = $dateEnd;
            }
            if ($actionFilter) {
                $where[]  = 'action = ?';
                $params[] = $actionFilter;
            }
            if ($userFilter) {
                $where[]  = '`user` LIKE ?';
                $params[] = '%' . addcslashes($userFilter, '%_\\') . '%';
            }
            if ($entityFilter) {
                $where[]  = 'entity_type = ?';
                $params[] = $entityFilter;
            }
            if ($searchText !== '') {
                $where[]  = '(action LIKE ? OR `user` LIKE ? OR description LIKE ? OR entity_type LIKE ?)';
                $like     = '%' . addcslashes($searchText, '%_\\') . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $whereClause = implode(' AND ', $where);

            // Count total
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch page — välj specifika kolumner istället för SELECT * för att undvika
            // att skicka potentiellt stora old_value/new_value JSON-fält i listningen
            $stmt = $this->pdo->prepare("
                SELECT id, action, entity_type, entity_id, description, `user`, ip_address, created_at
                FROM audit_log
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
            ");
            $stmt->execute($params);

            $hasMore = ($offset + $limit) < $total;

            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total'   => $total,
                'page'    => $page,
                'limit'   => $limit,
                'pages'   => (int)ceil($total / $limit),
                'hasMore' => $hasMore
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('AuditController::getLogs: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta loggar'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getStats() {
        try {
            $periodFilter = trim($_GET['period'] ?? 'month');
            $dateFilter = $this->getDateFilter($periodFilter);

            // Actions per type
            $stmt = $this->pdo->prepare("
                SELECT action, COUNT(*) as count
                FROM audit_log WHERE created_at >= ?
                GROUP BY action ORDER BY count DESC
            ");
            $stmt->execute([$dateFilter]);
            $byAction = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Actions per user
            $stmt = $this->pdo->prepare("
                SELECT `user`, COUNT(*) as count
                FROM audit_log WHERE created_at >= ?
                GROUP BY `user` ORDER BY count DESC
            ");
            $stmt->execute([$dateFilter]);
            $byUser = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Daily activity
            $stmt = $this->pdo->prepare("
                SELECT DATE(created_at) as dag, COUNT(*) as count
                FROM audit_log WHERE created_at >= ?
                GROUP BY DATE(created_at) ORDER BY dag
            ");
            $stmt->execute([$dateFilter]);
            $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total count
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE created_at >= ?");
            $stmt->execute([$dateFilter]);
            $total = (int)$stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_action' => $byAction,
                    'by_user' => $byUser,
                    'daily' => $daily
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('AuditController::getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta statistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getActions() {
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'audit_log'");
            if (!$check || $check->rowCount() === 0) {
                echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
                return;
            }
            $stmt = $this->pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll(PDO::FETCH_COLUMN)
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('AuditController::getActions: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta åtgärdstyper'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getDateFilter($period) {
        switch ($period) {
            case 'today':  return date('Y-m-d 00:00:00');
            case 'week':   return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':  return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year':   return date('Y-m-d 00:00:00', strtotime('-365 days'));
            case 'custom': return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default:       return date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
    }
}

/**
 * AuditLogger - Statisk hjälpklass för att logga aktiviteter
 * Kan anropas från vilken controller som helst.
 */
class AuditLogger {
    public static function ensureTable(PDO $pdo) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
                `id` int NOT NULL AUTO_INCREMENT,
                `action` varchar(100) NOT NULL,
                `entity_type` varchar(50) NOT NULL,
                `entity_id` int DEFAULT NULL,
                `description` varchar(500) DEFAULT NULL,
                `old_value` json DEFAULT NULL,
                `new_value` json DEFAULT NULL,
                `user` varchar(100) NOT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_action` (`action`),
                KEY `idx_entity` (`entity_type`, `entity_id`),
                KEY `idx_user` (`user`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (PDOException $e) {
            error_log('AuditController::ensureTable (json): ' . $e->getMessage());
            // Fallback for MySQL < 5.7.8 that lacks native JSON type
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `action` varchar(100) NOT NULL,
                    `entity_type` varchar(50) NOT NULL,
                    `entity_id` int DEFAULT NULL,
                    `description` varchar(500) DEFAULT NULL,
                    `old_value` LONGTEXT DEFAULT NULL,
                    `new_value` LONGTEXT DEFAULT NULL,
                    `user` varchar(100) NOT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_action` (`action`),
                    KEY `idx_entity` (`entity_type`, `entity_id`),
                    KEY `idx_user` (`user`),
                    KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            } catch (PDOException $e2) {
                error_log('AuditController::ensureTable (longtext fallback): ' . $e2->getMessage());
            }
        }
    }

    public static function log(
        PDO $pdo,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValue = null,
        ?array $newValue = null
    ): void {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (action, entity_type, entity_id, description, old_value, new_value, user, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $action,
                $entityType,
                $entityId,
                $description,
                $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
                $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
                $_SESSION['username'] ?? 'system',
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('AuditController::log — audit logging misslyckades: ' . $e->getMessage());
        }
    }
}
