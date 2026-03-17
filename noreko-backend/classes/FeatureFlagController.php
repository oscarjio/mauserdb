<?php
/**
 * FeatureFlagController - Hanterar feature flags för rollbaserad synlighet
 *
 * GET  ?action=feature-flags&run=list    → Alla feature flags (public)
 * POST ?action=feature-flags&run=update  → Uppdatera en feature flag (admin/developer)
 * POST ?action=feature-flags&run=bulk-update → Batch-uppdatera (admin/developer)
 */
class FeatureFlagController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run = trim($_GET['run'] ?? 'list');

        if ($method === 'GET') {
            if ($run === 'list') {
                $this->getList();
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig run-parameter'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        if ($method === 'POST') {
            // Admin/Developer-only
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!$this->isDeveloper()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Kräver admin- eller developer-behörighet'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($run === 'update') {
                $this->updateFlag();
            } elseif ($run === 'bulk-update') {
                $this->bulkUpdate();
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig run-parameter'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metod ej tillåten'], JSON_UNESCAPED_UNICODE);
    }

    private function isDeveloper(): bool {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['developer', 'admin'], true);
    }

    private function ensureTableExists() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS feature_flags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                feature_key VARCHAR(100) NOT NULL UNIQUE,
                label VARCHAR(200) NOT NULL,
                category VARCHAR(50) DEFAULT 'rebotling',
                min_role ENUM('public','user','admin','developer') NOT NULL DEFAULT 'developer',
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\PDOException $e) {
            error_log('FeatureFlagController::ensureTableExists: ' . $e->getMessage());
        }
    }

    private function getList() {
        try {
            $stmt = $this->pdo->query(
                "SELECT feature_key, label, category, min_role, enabled
                 FROM feature_flags
                 ORDER BY category, label"
            );
            $flags = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $flags], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('FeatureFlagController::getList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta feature flags'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateFlag() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $featureKey = trim($data['feature_key'] ?? '');
        $minRole = trim($data['min_role'] ?? '');
        $validRoles = ['public', 'user', 'admin', 'developer'];

        if ($featureKey === '' || !in_array($minRole, $validRoles, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig feature_key eller min_role'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE feature_flags SET min_role = ? WHERE feature_key = ?"
            );
            $stmt->execute([$minRole, $featureKey]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Feature flag hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode(['success' => true, 'message' => 'Uppdaterad'], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('FeatureFlagController::updateFlag: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkUpdate() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['updates']) || !is_array($data['updates'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON — förväntar { updates: [{feature_key, min_role}] }'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $validRoles = ['public', 'user', 'admin', 'developer'];
        $updated = 0;

        try {
            $stmt = $this->pdo->prepare("UPDATE feature_flags SET min_role = ? WHERE feature_key = ?");

            foreach ($data['updates'] as $item) {
                $key = trim($item['feature_key'] ?? '');
                $role = trim($item['min_role'] ?? '');
                if ($key !== '' && in_array($role, $validRoles, true)) {
                    $stmt->execute([$role, $key]);
                    $updated += $stmt->rowCount();
                }
            }

            echo json_encode(['success' => true, 'message' => "$updated feature flags uppdaterade", 'count' => $updated], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('FeatureFlagController::bulkUpdate: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
        }
    }
}
