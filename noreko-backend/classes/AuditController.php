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
        session_start();

        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Åtkomst nekad']);
            return;
        }

        $run = $_GET['run'] ?? '';

        if ($run === 'stats') {
            $this->getStats();
        } else {
            $this->getLogs();
        }
    }

    private function getLogs() {
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $actionFilter = $_GET['filter_action'] ?? '';
            $userFilter = $_GET['filter_user'] ?? '';
            $entityFilter = $_GET['filter_entity'] ?? '';
            $periodFilter = $_GET['period'] ?? 'month';

            $dateFilter = $this->getDateFilter($periodFilter);

            $where = ['created_at >= ?'];
            $params = [$dateFilter];

            if ($actionFilter) {
                $where[] = 'action = ?';
                $params[] = $actionFilter;
            }
            if ($userFilter) {
                $where[] = 'user LIKE ?';
                $params[] = '%' . $userFilter . '%';
            }
            if ($entityFilter) {
                $where[] = 'entity_type = ?';
                $params[] = $entityFilter;
            }

            $whereClause = implode(' AND ', $where);

            // Count total
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch page
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $this->pdo->prepare("
                SELECT * FROM audit_log
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            error_log('AuditController getLogs: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta loggar']);
        }
    }

    private function getStats() {
        try {
            $periodFilter = $_GET['period'] ?? 'month';
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
                SELECT user, COUNT(*) as count
                FROM audit_log WHERE created_at >= ?
                GROUP BY user ORDER BY count DESC
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
            ]);
        } catch (PDOException $e) {
            error_log('AuditController getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta statistik']);
        }
    }

    private function getDateFilter($period) {
        switch ($period) {
            case 'today': return date('Y-m-d 00:00:00');
            case 'week': return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month': return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'year': return date('Y-m-d 00:00:00', strtotime('-365 days'));
            default: return date('Y-m-d 00:00:00', strtotime('-30 days'));
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
            error_log('AuditLogger ensureTable: ' . $e->getMessage());
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
                $oldValue ? json_encode($oldValue) : null,
                $newValue ? json_encode($newValue) : null,
                $_SESSION['username'] ?? 'system',
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('AuditLogger log failed: ' . $e->getMessage());
        }
    }
}
