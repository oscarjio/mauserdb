<?php
/**
 * AlertsController.php
 * Realtidsvarningar — OEE-låg, lång stopptid, hög kassationsrate
 *
 * Endpoints via ?action=alerts&run=XXX:
 *   GET  run=active              — alla aktiva (ej kvitterade) alerts, nyast först
 *   GET  run=history&days=30     — historik senaste N dagar
 *   POST run=acknowledge&id=X   — kvittera en alert
 *   GET  run=settings            — hämta tröskelvärden
 *   POST run=settings            — spara tröskelvärden
 *   GET  run=check               — kör alertcheck mot aktuell data
 */
class AlertsController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `alerts` (
                    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type`             ENUM('oee_low','stop_long','scrap_high') NOT NULL,
                    `message`          VARCHAR(500) NOT NULL,
                    `value`            DECIMAL(10,2) DEFAULT NULL,
                    `threshold`        DECIMAL(10,2) DEFAULT NULL,
                    `severity`         ENUM('warning','critical') NOT NULL DEFAULT 'warning',
                    `acknowledged`     TINYINT(1) NOT NULL DEFAULT 0,
                    `acknowledged_by`  INT UNSIGNED DEFAULT NULL,
                    `acknowledged_at`  DATETIME DEFAULT NULL,
                    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `idx_acknowledged` (`acknowledged`),
                    INDEX `idx_type` (`type`),
                    INDEX `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `alert_settings` (
                    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type`            ENUM('oee_low','stop_long','scrap_high') NOT NULL,
                    `threshold_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `enabled`         TINYINT(1) NOT NULL DEFAULT 1,
                    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `updated_by`      INT UNSIGNED DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_type` (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->pdo->exec("
                INSERT IGNORE INTO `alert_settings` (`type`, `threshold_value`, `enabled`) VALUES
                    ('oee_low',    60.00, 1),
                    ('stop_long',  30.00, 1),
                    ('scrap_high', 10.00, 1)
            ");
        } catch (\PDOException $e) {
            error_log('AlertsController::ensureTablesExist: ' . $e->getMessage());
        }
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run    = trim($_GET['run'] ?? '');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        switch ($run) {
            case 'active':
                $this->getActiveAlerts();
                break;
            case 'history':
                $this->getAlertHistory();
                break;
            case 'acknowledge':
                if ($method !== 'POST') {
                    $this->sendError('Metod inte tillåten — använd POST', 405);
                    return;
                }
                $this->acknowledgeAlert();
                break;
            case 'settings':
                if ($method === 'POST') {
                    $this->saveSettings();
                } else {
                    $this->getSettings();
                }
                break;
            case 'check':
                $this->runAlertCheck();
                break;
            default:
                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
                break;
        }
    }

    // ================================================================
    // ENDPOINT: active
    // ================================================================

    private function getActiveAlerts(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, type, message, value, threshold, severity, created_at
                FROM alerts
                WHERE acknowledged = 0
                ORDER BY
                    CASE severity WHEN 'critical' THEN 0 ELSE 1 END ASC,
                    created_at DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $alerts = array_map(function ($r) {
                return [
                    'id'         => (int)$r['id'],
                    'type'       => $r['type'],
                    'message'    => $r['message'],
                    'value'      => $r['value'] !== null ? (float)$r['value'] : null,
                    'threshold'  => $r['threshold'] !== null ? (float)$r['threshold'] : null,
                    'severity'   => $r['severity'],
                    'created_at' => $r['created_at'],
                ];
            }, $rows);

            $this->sendSuccess([
                'alerts' => $alerts,
                'count'  => count($alerts),
            ]);
        } catch (\PDOException $e) {
            error_log('AlertsController::getActiveAlerts: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: history
    // ================================================================

    private function getAlertHistory(): void {
        $days = max(1, min(365, (int)($_GET['days'] ?? 30)));

        try {
            $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $stmt  = $this->pdo->prepare("
                SELECT
                    a.id, a.type, a.message, a.value, a.threshold, a.severity,
                    a.acknowledged, a.acknowledged_at, a.created_at,
                    u.username AS acknowledged_by_name
                FROM alerts a
                LEFT JOIN users u ON u.id = a.acknowledged_by
                WHERE a.created_at >= :since
                ORDER BY a.created_at DESC
                LIMIT 500
            ");
            $stmt->execute(['since' => $since]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $alerts = array_map(function ($r) {
                return [
                    'id'                   => (int)$r['id'],
                    'type'                 => $r['type'],
                    'message'              => $r['message'],
                    'value'                => $r['value'] !== null ? (float)$r['value'] : null,
                    'threshold'            => $r['threshold'] !== null ? (float)$r['threshold'] : null,
                    'severity'             => $r['severity'],
                    'acknowledged'         => (bool)$r['acknowledged'],
                    'acknowledged_at'      => $r['acknowledged_at'],
                    'acknowledged_by_name' => $r['acknowledged_by_name'],
                    'created_at'           => $r['created_at'],
                ];
            }, $rows);

            $this->sendSuccess([
                'alerts' => $alerts,
                'count'  => count($alerts),
                'days'   => $days,
                'since'  => $since,
            ]);
        } catch (\PDOException $e) {
            error_log('AlertsController::getAlertHistory: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: acknowledge (POST)
    // ================================================================

    private function acknowledgeAlert(): void {
        // Stöd både GET-param och POST-body
        $id = (int)($_GET['id'] ?? 0);
        if ($id === 0) {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $id   = (int)($body['id'] ?? 0);
        }

        if ($id <= 0) {
            $this->sendError('Ogiltigt alert-ID');
            return;
        }

        $userId = (int)$_SESSION['user_id'];

        try {
            $stmt = $this->pdo->prepare("
                UPDATE alerts
                SET acknowledged = 1,
                    acknowledged_by = :uid,
                    acknowledged_at = NOW()
                WHERE id = :id
                  AND acknowledged = 0
            ");
            $stmt->execute(['uid' => $userId, 'id' => $id]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Alert hittades inte eller redan kvitterad', 404);
                return;
            }

            $this->sendSuccess(['acknowledged' => true, 'id' => $id]);
        } catch (\PDOException $e) {
            error_log('AlertsController::acknowledgeAlert: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: settings GET
    // ================================================================

    private function getSettings(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT type, threshold_value, enabled, updated_at
                FROM alert_settings
                ORDER BY FIELD(type, 'oee_low', 'stop_long', 'scrap_high')
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexera på type för bekvämligheten
            $settings = [];
            foreach ($rows as $r) {
                $settings[$r['type']] = [
                    'threshold_value' => (float)$r['threshold_value'],
                    'enabled'         => (bool)$r['enabled'],
                    'updated_at'      => $r['updated_at'],
                ];
            }

            // Standardvärden om tabellen är tom
            $defaults = [
                'oee_low'    => ['threshold_value' => 60.0, 'enabled' => true, 'updated_at' => null],
                'stop_long'  => ['threshold_value' => 30.0, 'enabled' => true, 'updated_at' => null],
                'scrap_high' => ['threshold_value' => 10.0, 'enabled' => true, 'updated_at' => null],
            ];
            foreach ($defaults as $type => $def) {
                if (!isset($settings[$type])) {
                    $settings[$type] = $def;
                }
            }

            $this->sendSuccess(['settings' => $settings]);
        } catch (\PDOException $e) {
            error_log('AlertsController::getSettings: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: settings POST
    // ================================================================

    private function saveSettings(): void {
        // Admin-kontroll
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->sendError('Otillräckliga behörigheter', 403);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $this->sendError('Ogiltig JSON-payload');
            return;
        }

        $validTypes = ['oee_low', 'stop_long', 'scrap_high'];
        $userId     = (int)$_SESSION['user_id'];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO alert_settings (type, threshold_value, enabled, updated_by)
                VALUES (:type, :threshold, :enabled, :uid)
                ON DUPLICATE KEY UPDATE
                    threshold_value = VALUES(threshold_value),
                    enabled         = VALUES(enabled),
                    updated_by      = VALUES(updated_by)
            ");

            foreach ($validTypes as $type) {
                if (!isset($body[$type])) continue;
                $cfg       = $body[$type];
                $threshold = (float)($cfg['threshold_value'] ?? 0);
                $enabled   = isset($cfg['enabled']) ? (int)(bool)$cfg['enabled'] : 1;
                $stmt->execute([
                    'type'      => $type,
                    'threshold' => $threshold,
                    'enabled'   => $enabled,
                    'uid'       => $userId,
                ]);
            }

            $this->pdo->commit();
            $this->sendSuccess(['saved' => true]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AlertsController::saveSettings: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: check — kör alertkontroll mot aktuell data
    // ================================================================

    private function runAlertCheck(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->sendError('Metod inte tillåten — använd POST', 405);
            return;
        }

        try {
            // Hämta aktiva inställningar
            $stmtS = $this->pdo->query("
                SELECT type, threshold_value, enabled
                FROM alert_settings
            ");
            $settingsRows = $stmtS->fetchAll(PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($settingsRows as $r) {
                $settings[$r['type']] = [
                    'threshold' => (float)$r['threshold_value'],
                    'enabled'   => (bool)$r['enabled'],
                ];
            }

            $defaults = [
                'oee_low'    => ['threshold' => 60.0, 'enabled' => true],
                'stop_long'  => ['threshold' => 30.0, 'enabled' => true],
                'scrap_high' => ['threshold' => 10.0, 'enabled' => true],
            ];
            foreach ($defaults as $type => $def) {
                if (!isset($settings[$type])) {
                    $settings[$type] = $def;
                }
            }

            $created = 0;

            // ---- 1. OEE-låg (senaste timmen, baserat på kvalitet och drifttid) ----
            if ($settings['oee_low']['enabled']) {
                $oeeThreshold = $settings['oee_low']['threshold'];
                $oeeValue     = $this->calcCurrentOee();

                if ($oeeValue !== null && $oeeValue < $oeeThreshold) {
                    // Kontrollera om en liknande alert redan är aktiv (ej kvitterad, skapad inom 2h)
                    if (!$this->recentActiveAlertExists('oee_low', 120)) {
                        $severity = $oeeValue < ($oeeThreshold * 0.75) ? 'critical' : 'warning';
                        $message  = sprintf(
                            'OEE är %.1f%% — under tröskeln %.0f%%. Kontrollera linjen!',
                            $oeeValue, $oeeThreshold
                        );
                        $this->insertAlert('oee_low', $message, $oeeValue, $oeeThreshold, $severity);
                        $created++;
                    }
                }
            }

            // ---- 2. Lång stopptid (öppet stopp utan sluttid) ----
            if ($settings['stop_long']['enabled']) {
                $stopThreshold = $settings['stop_long']['threshold'];
                $stoppages     = $this->getLongActiveStoppages($stopThreshold);

                foreach ($stoppages as $stop) {
                    if (!$this->recentActiveAlertExists('stop_long', 60)) {
                        $severity = $stop['duration_min'] >= ($stopThreshold * 2) ? 'critical' : 'warning';
                        $message  = sprintf(
                            'Stopp på %s har pågått i %.0f min (tröskel: %.0f min). Orsak: %s',
                            $stop['line'],
                            $stop['duration_min'],
                            $stopThreshold,
                            $stop['reason'] ?: 'okänd'
                        );
                        $this->insertAlert('stop_long', $message, $stop['duration_min'], $stopThreshold, $severity);
                        $created++;
                        break; // Skapa max en stopp-alert per check
                    }
                }
            }

            // ---- 3. Hög kassationsrate (senaste timmen) ----
            if ($settings['scrap_high']['enabled']) {
                $scrapThreshold = $settings['scrap_high']['threshold'];
                $scrapRate      = $this->calcCurrentScrapRate();

                if ($scrapRate !== null && $scrapRate > $scrapThreshold) {
                    if (!$this->recentActiveAlertExists('scrap_high', 120)) {
                        $severity = $scrapRate > ($scrapThreshold * 1.5) ? 'critical' : 'warning';
                        $message  = sprintf(
                            'Kassationsrate är %.1f%% — över tröskeln %.0f%%. Kontrollera kvaliteten!',
                            $scrapRate, $scrapThreshold
                        );
                        $this->insertAlert('scrap_high', $message, $scrapRate, $scrapThreshold, $severity);
                        $created++;
                    }
                }
            }

            // Räkna aktiva alerts
            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM alerts WHERE acknowledged = 0");
            $activeCount = (int)$countStmt->fetchColumn();

            $this->sendSuccess([
                'checked'      => true,
                'alerts_created' => $created,
                'active_count' => $activeCount,
            ]);
        } catch (\PDOException $e) {
            error_log('AlertsController::runAlertCheck: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // HJÄLPMETODER FÖR ALERTCHECK
    // ================================================================

    /**
     * Beräkna aktuell OEE-procent baserat på senaste timmen (rebotling).
     * OEE = Tillgänglighet × Prestanda × Kvalitet
     * Förenklad: kvalitetsprocent ibc_ok / (ibc_ok + ibc_ej_ok) senaste timmen.
     */
    private function calcCurrentOee(): ?float {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    MAX(ibc_ok)    AS ibc_ok,
                    MAX(ibc_ej_ok) AS ibc_ej_ok
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                  AND skiftraknare IS NOT NULL
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            $ok    = (int)($row['ibc_ok']    ?? 0);
            $ejOk  = (int)($row['ibc_ej_ok'] ?? 0);
            $total = $ok + $ejOk;
            if ($total === 0) return null;

            // Kvalitetsprocent som OEE-proxy
            return round($ok / $total * 100, 1);
        } catch (\PDOException $e) {
            error_log('AlertsController::calcCurrentOee: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Hämta aktiva stopporsaker som pågått längre än $minMinutes.
     */
    private function getLongActiveStoppages(float $minMinutes): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    sl.id,
                    sl.line,
                    TIMESTAMPDIFF(MINUTE, sl.start_time, NOW()) AS duration_min,
                    COALESCE(sr.name, sl.comment, 'okänd') AS reason
                FROM stoppage_log sl
                LEFT JOIN stoppage_reasons sr ON sr.id = sl.reason_id
                WHERE sl.end_time IS NULL
                  AND TIMESTAMPDIFF(MINUTE, sl.start_time, NOW()) >= :mins
                ORDER BY duration_min DESC
                LIMIT 5
            ");
            $stmt->execute(['mins' => $minMinutes]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            // Tabellen kanske saknas
            return [];
        }
    }

    /**
     * Beräkna kassationsrate senaste timmen (rebotling).
     */
    private function calcCurrentScrapRate(): ?float {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    MAX(ibc_ok)    AS ibc_ok,
                    MAX(ibc_ej_ok) AS ibc_ej_ok
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                  AND skiftraknare IS NOT NULL
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            $ok    = (int)($row['ibc_ok']    ?? 0);
            $ejOk  = (int)($row['ibc_ej_ok'] ?? 0);
            $total = $ok + $ejOk;
            if ($total === 0) return null;

            return round($ejOk / $total * 100, 1);
        } catch (\PDOException $e) {
            error_log('AlertsController::calcCurrentScrapRate: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Kontrollera om det finns en aktiv (ej kvitterad) alert av given typ
     * skapad inom de senaste $withinMinutes minuterna.
     */
    private function recentActiveAlertExists(string $type, int $withinMinutes): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM alerts
                WHERE type = :type
                  AND acknowledged = 0
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)
            ");
            $stmt->execute(['type' => $type, 'mins' => $withinMinutes]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Infoga en ny alert i databasen.
     */
    private function insertAlert(string $type, string $message, float $value, float $threshold, string $severity): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO alerts (type, message, value, threshold, severity)
                VALUES (:type, :message, :value, :threshold, :severity)
            ");
            $stmt->execute([
                'type'      => $type,
                'message'   => $message,
                'value'     => $value,
                'threshold' => $threshold,
                'severity'  => $severity,
            ]);
        } catch (\PDOException $e) {
            error_log('AlertsController::insertAlert: ' . $e->getMessage());
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function sendSuccess(array $data): void {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }
}
