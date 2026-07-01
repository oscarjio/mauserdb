<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

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
                error_log('FeatureFlagController::handle: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', role=' . ($_SESSION['role'] ?? 'none'));
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Kräver admin- eller developer-behörighet'], JSON_UNESCAPED_UNICODE);
                return;
            }
            // Kontrollera session-timeout (inaktivitet)
            if (!AuthHelper::checkSessionTimeout()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
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
        // Filcache (60s) — trivial fråga men långsam över DB-tunneln, och den
        // blockerar Angular-appInitializer (fryser hela app-bootet) om den är seg.
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $cf = $cacheDir . '/feature_flags_list.json';
        if (is_file($cf) && (time() - filemtime($cf)) < 60) {
            $c = @file_get_contents($cf);
            if ($c !== false && $c !== '') {
                header('Content-Type: application/json; charset=utf-8');
                echo $c;
                return;
            }
        }
        // Stampede-skydd: vid cache-miss regenererar bara EN request (flock NB).
        // Övriga serverar stale cache (upp till 5 min) i stället för att alla
        // samtidigt går mot den strypta DB-tunneln och förstärker 503-bursten.
        $lock = @fopen($cf . '.lock', 'c');
        $haveLock = $lock && flock($lock, LOCK_EX | LOCK_NB);
        if (!$haveLock && is_file($cf) && (time() - filemtime($cf)) < 300) {
            $c = @file_get_contents($cf);
            if ($c !== false && $c !== '') {
                if ($lock) { fclose($lock); }
                header('Content-Type: application/json; charset=utf-8');
                echo $c;
                return;
            }
        }
        try {
            // Dubbelkoll under låset — någon kan ha regenererat precis före oss.
            if ($haveLock && is_file($cf) && (time() - filemtime($cf)) < 60) {
                $c = @file_get_contents($cf);
                if ($c !== false && $c !== '') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo $c;
                    return;
                }
            }
            $stmt = $this->pdo->query(
                "SELECT feature_key, label, category, min_role, enabled
                 FROM feature_flags
                 ORDER BY category, label"
            );
            $flags = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $out = json_encode(['success' => true, 'data' => $flags], JSON_UNESCAPED_UNICODE);
            @file_put_contents($cf, $out, LOCK_EX);
            echo $out;
        } catch (\PDOException $e) {
            // Tabellen kan saknas i ny miljö — returnera tom lista med 200 OK
            // så att frontend-appen inte blockeras vid initiering (APP_INITIALIZER).
            error_log('FeatureFlagController::getList: ' . $e->getMessage());
            echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
        } finally {
            if ($haveLock) { flock($lock, LOCK_UN); }
            if ($lock) { fclose($lock); }
        }
    }

    private function updateFlag() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $featureKey = strip_tags(trim($data['feature_key'] ?? ''));
        $minRole = strip_tags(trim($data['min_role'] ?? ''));
        $validRoles = ['public', 'user', 'admin', 'developer'];

        if (strlen($featureKey) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'feature_key får vara max 100 tecken'], JSON_UNESCAPED_UNICODE);
            return;
        }

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

            AuditLogger::log($this->pdo, 'update_feature_flag', 'feature_flags', null,
                "Uppdaterade feature flag: $featureKey -> min_role: $minRole",
                null, ['feature_key' => $featureKey, 'min_role' => $minRole]);
            @unlink(dirname(__DIR__) . '/cache/feature_flags_list.json');
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

        // Begränsa array-storlek för att förhindra missbruk
        if (count($data['updates']) > 200) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Max 200 uppdateringar per anrop'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $validRoles = ['public', 'user', 'admin', 'developer'];
        $updated = 0;

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE feature_flags SET min_role = ? WHERE feature_key = ?");

            foreach ($data['updates'] as $item) {
                $key = strip_tags(trim($item['feature_key'] ?? ''));
                $role = trim($item['min_role'] ?? '');
                if ($key !== '' && in_array($role, $validRoles, true)) {
                    $stmt->execute([$role, $key]);
                    $updated += $stmt->rowCount();
                }
            }

            $this->pdo->commit();
            AuditLogger::log($this->pdo, 'bulk_update_feature_flags', 'feature_flags', null,
                "Batch-uppdaterade $updated funktionsflaggor");
            @unlink(dirname(__DIR__) . '/cache/feature_flags_list.json');
            echo json_encode(['success' => true, 'message' => "$updated funktionsflaggor uppdaterade", 'count' => $updated], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('FeatureFlagController::bulkUpdate: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
        }
    }
}
