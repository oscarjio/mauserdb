<?php
require_once __DIR__ . '/AuditController.php';

class TvattlinjeController {
    private $pdo;
    private static bool $settingsTableEnsured = false;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = trim($_GET['run'] ?? '');
        
        if ($method === 'GET') {
            if ($action === 'admin-settings') {
                $this->getAdminSettings();
            } elseif ($action === 'settings') {
                $this->getSettings();
            } elseif ($action === 'weekday-goals') {
                $this->getWeekdayGoals();
            } elseif ($action === 'system-status') {
                $this->getSystemStatus();
            } elseif ($action === 'today-snapshot') {
                $this->getTodaySnapshot();
            } elseif ($action === 'alert-thresholds') {
                $this->getAlertThresholds();
            } elseif ($action === 'status') {
                if (class_exists('RemoteAgg') && RemoteAgg::enabled() && RemoteAgg::passthru('tvattlinje')) return;
                $this->getRunningStatus();
            } elseif ($action === 'rast') {
                $this->getRastStatus();
            } elseif ($action === 'driftstopp') {
                $this->getDriftstoppStatus();
            } elseif ($action === 'statistics') {
                if (class_exists('RemoteAgg') && RemoteAgg::enabled() && RemoteAgg::passthru('tvattlinje')) return;
                $this->getStatistics();
            } elseif ($action === 'report') {
                $this->getReport();
            } elseif ($action === 'oee-trend') {
                if (class_exists('RemoteAgg') && RemoteAgg::enabled() && RemoteAgg::passthru('tvattlinje')) return;
                $this->getOeeTrend();
            } elseif ($action === 'skiftrapport-statistik') {
                $this->getSkiftrapportStatistik();
            } elseif ($action === 'plc-diagnostics') {
                $this->getPlcDiagnostics();
            } elseif ($action === 'plc-diagnostik') {
                $this->getPlcDiagnostikStream();
            } elseif ($action === 'operator-scores') {
                $this->getOperatorScores();
            } elseif ($action === 'ibc-per-dag') {
                $this->getIbcPerDag();
            } else {
                $this->getLiveStats();
            }
            return;
        }

        if ($method === 'POST') {
            if ($action === 'admin-settings') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','developer'], true)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->saveAdminSettings();
                return;
            }

            if ($action === 'settings') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','developer'], true)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->setSettings();
                return;
            }

            if ($action === 'weekday-goals') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','developer'], true)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->setWeekdayGoals();
                return;
            }

            if ($action === 'save-alert-thresholds') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','developer'], true)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->saveAlertThresholds();
                return;
            }

            if ($action === 'plc-simulate') {
                $this->plcSimulate();
                return;
            }
        }

        // Om ingen matchande metod finns
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod eller action'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================
    // Ny key-value settings (tvattlinje_settings tabell)
    // =========================================================

    private function ensureSettingsTable() {
        if (self::$settingsTableEnsured) return;
        self::$settingsTableEnsured = true;
        // Tabellen har UNIK nyckel på `setting` (uq_setting) — se migration
        // 2026-07-06_fix_tvattlinje_settings_crashed_bloat.sql. Den unika nyckeln gör
        // INSERT IGNORE nedan idempotent (annars ackumulerades 4 rader/request → 9.5M
        // skräprader som kraschade tabellen och fick run=settings att timeouta/500:a).
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tvattlinje_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting VARCHAR(100) NOT NULL UNIQUE,
                value VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['dagmal',       '140'],
            ['takt_mal',     '3'],
            ['skift_start',  '06:00'],
            ['skift_slut',   '22:00'],
        ];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO tvattlinje_settings (setting, value) VALUES (?, ?)");
        foreach ($defaults as [$k, $v]) {
            $stmt->execute([$k, $v]);
        }
    }

    private function getSettings() {
        try {
            $this->ensureSettingsTable();
            $rows = $this->pdo->query("SELECT setting, value FROM tvattlinje_settings WHERE setting IS NOT NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta inställningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function setSettings() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $allowed = ['dagmal', 'takt_mal', 'skift_start', 'skift_slut'];
        try {
            $this->ensureSettingsTable();
            $stmtUpd = $this->pdo->prepare("UPDATE tvattlinje_settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting = ?");
            $stmtIns = $this->pdo->prepare("INSERT INTO tvattlinje_settings (setting, value) VALUES (?, ?)");
            foreach ($allowed as $key) {
                if (!isset($data[$key])) continue;
                $value = trim($data[$key]);
                if (in_array($key, ['skift_start', 'skift_slut'], true)) {
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) continue;
                } else {
                    $value = (string)max(0, intval($value));
                }
                $stmtUpd->execute([$value, $key]);
                if ($stmtUpd->rowCount() === 0) {
                    $stmtIns->execute([$key, $value]);
                }
            }
            AuditLogger::log($this->pdo, 'update_tvattlinje_settings_v2', 'tvattlinje_settings', null,
                json_encode(array_intersect_key($data, array_flip($allowed)), JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true, 'message' => 'Inställningar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::setSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara inställningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Systemstatus — returnerar null-värden tills linjen är i drift
    // =========================================================

    private function getSystemStatus() {
        try {
            $tz  = new \DateTimeZone('Europe/Stockholm');
            $now = new \DateTime('now', $tz);

            $plcLastSeen   = null;
            $plcAgeMinutes = null;

            // Senaste PLC-signal (tvattlinje_ibc)
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM tvattlinje_ibc")->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['last_ping']) {
                    $plcLastSeen  = $row['last_ping'];
                    $lastDt       = new \DateTime($plcLastSeen, $tz);
                    $diff         = $now->diff($lastDt);
                    $plcAgeMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getSystemStatus plcLastSeen: ' . $e->getMessage()); }

            // Lösnummer
            $losnummer = null;
            try {
                $row = $this->pdo->query("SELECT ibc_count FROM tvattlinje_ibc ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $losnummer = $row ? (int)$row['ibc_count'] : null;
            } catch (\Throwable $e) { error_log('TvattlinjeController::getSystemStatus losnummer: ' . $e->getMessage()); }

            // Antal poster idag
            $posterIdag = 0;
            try {
                $posterIdag = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM tvattlinje_ibc WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY"
                )->fetchColumn();
            } catch (\Throwable $e) { error_log('TvattlinjeController::getSystemStatus posterIdag: ' . $e->getMessage()); }

            // Är linjen i drift? PLC-data < 15 min gammal
            $isRunning = ($plcAgeMinutes !== null && $plcAgeMinutes < 15);

            // Databas OK
            $dbStatus = 'ok';
            try {
                $this->pdo->query("SELECT 1");
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getSystemStatus: ' . $e->getMessage());
                $dbStatus = 'error';
            }

            // Linjenotering
            $note = $isRunning ? 'Linjen i drift' : 'Linjen ej i drift';

            echo json_encode([
                'success' => true,
                'data' => [
                    'plc_last_seen'    => $plcLastSeen,
                    'plc_age_minutes'  => $plcAgeMinutes,
                    'db_status'        => $dbStatus,
                    'losnummer'        => $losnummer,
                    'poster_idag'      => $posterIdag,
                    'is_running'       => $isRunning,
                    'note'             => $note,
                    'server_time'      => $now->format('Y-m-d H:i:s'),
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getSystemStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta systemstatus'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Today-snapshot — snabb produktionsöversikt för idag
    // =========================================================

    private function getTodaySnapshot() {
        try {
            $tz  = new \DateTimeZone('Europe/Stockholm');
            $now = new \DateTime('now', $tz);

            // IBC idag — MAX(ibc_count) fångar upp missade webhooks
            $ibcIdag = 0;
            try {
                $ibcIdag = (int)$this->pdo->query(
                    "SELECT COALESCE(MAX(ibc_count), 0) FROM tvattlinje_ibc WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY"
                )->fetchColumn();
            } catch (\Throwable $e) { error_log('TvattlinjeController::getTodaySnapshot ibcIdag: ' . $e->getMessage()); }

            // Dagsmål — veckodagsmål (0=Måndag, PHP ISO-1 → 0-index: ISO-1)
            $dagmal = 140;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query(
                    "SELECT value FROM tvattlinje_settings WHERE setting = 'dagmal'"
                )->fetch(\PDO::FETCH_ASSOC);
                if ($sr) $dagmal = (int)$sr['value'];

                // Veckodagsmål: PHP ISO 1=Måndag → weekday index 0-6
                $isoDay = (int)$now->format('N') - 1; // 0=Måndag
                try {
                    $this->ensureWeekdayGoalsTable();
                    $wg = $this->pdo->prepare(
                        "SELECT mal FROM tvattlinje_weekday_goals WHERE weekday = ?"
                    );
                    $wg->execute([$isoDay]);
                    $wgRow = $wg->fetch(\PDO::FETCH_ASSOC);
                    if ($wgRow && $wgRow['mal'] !== null) {
                        $dagmal = (int)$wgRow['mal'];
                    }
                } catch (\Throwable $e) { error_log('TvattlinjeController::getTodaySnapshot weekdayGoal: ' . $e->getMessage()); }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getTodaySnapshot dagmal: ' . $e->getMessage()); }

            // Linjen kör? (senaste PLC < 15 min gammal)
            $isRunning = false;
            try {
                $row = $this->pdo->query(
                    "SELECT MAX(datum) as last_ping FROM tvattlinje_ibc"
                )->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['last_ping']) {
                    $lastDt = new \DateTime($row['last_ping'], $tz);
                    $diff   = $now->diff($lastDt);
                    $ageMin = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
                    $isRunning = ($ageMin < 15);
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getTodaySnapshot isRunning: ' . $e->getMessage()); }

            // Takt: IBC per timme senaste 2 timmar
            $taktPerTimme = 0.0;
            try {
                $cnt = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM tvattlinje_ibc WHERE datum >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
                )->fetchColumn();
                $taktPerTimme = round($cnt / 2.0, 1);
            } catch (\Throwable $e) { error_log('TvattlinjeController::getTodaySnapshot taktPerTimme: ' . $e->getMessage()); }

            // Skiftlängd
            $skiftTimmar = 8.0;
            try {
                $sr = $this->pdo->query(
                    "SELECT value FROM tvattlinje_settings WHERE setting = 'skift_start'"
                )->fetch(\PDO::FETCH_ASSOC);
                $skiftStart = $sr ? $sr['value'] : '06:00';
                $sr2 = $this->pdo->query(
                    "SELECT value FROM tvattlinje_settings WHERE setting = 'skift_slut'"
                )->fetch(\PDO::FETCH_ASSOC);
                $skiftSlut = $sr2 ? $sr2['value'] : '22:00';

                $startDt = new \DateTime($now->format('Y-m-d') . ' ' . $skiftStart, $tz);
                $slutDt  = new \DateTime($now->format('Y-m-d') . ' ' . $skiftSlut,  $tz);
                if ($slutDt > $startDt) {
                    $skiftTimmar = ($slutDt->getTimestamp() - $startDt->getTimestamp()) / 3600.0;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getTodaySnapshot skiftTimmar: ' . $e->getMessage()); }

            // Prognos
            $shiftStart = new \DateTime($now->format('Y-m-d') . ' 06:00:00', $tz);
            $minSinceStart = max(1, ($now->getTimestamp() - $shiftStart->getTimestamp()) / 60);
            $remainingMin  = max(0, ($skiftTimmar * 60) - $minSinceStart);
            $prognos = (int)round($ibcIdag + ($taktPerTimme / 60.0) * $remainingMin);

            $pctOfGoal = $dagmal > 0 ? round($ibcIdag / $dagmal * 100, 1) : 0;

            // Tom dag?
            $empty = ($ibcIdag === 0);

            echo json_encode([
                'success'      => true,
                'empty'        => $empty,
                'data' => [
                    'ibc_idag'      => $ibcIdag,
                    'dagmal'        => $dagmal,
                    'pct_of_goal'   => $pctOfGoal,
                    'takt_per_h'    => $taktPerTimme,
                    'prognos'       => $prognos,
                    'is_running'    => $isRunning,
                    'server_time'   => $now->format('H:i:s'),
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getTodaySnapshot: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta dagens snapshot'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Alert-trösklar — sparas som JSON i tvattlinje_settings
    // =========================================================

    private function defaultAlertThresholds(): array {
        return [
            'kvalitet_warn'  => 90,
            'plc_max_min'    => 15,
            'dagmal_warn_pct'=> 80,
        ];
    }

    private function ensureAlertThresholdsRow() {
        $this->ensureSettingsTable();
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO tvattlinje_settings (setting, value) VALUES ('alert_thresholds', ?)"
        );
        $stmt->execute([json_encode($this->defaultAlertThresholds(), JSON_UNESCAPED_UNICODE)]);
    }

    private function getAlertThresholds() {
        try {
            $this->ensureAlertThresholdsRow();
            $row = $this->pdo->query(
                "SELECT value FROM tvattlinje_settings WHERE setting = 'alert_thresholds'"
            )->fetch(\PDO::FETCH_ASSOC);

            $defaults   = $this->defaultAlertThresholds();
            $thresholds = $defaults;
            if ($row && !empty($row['value'])) {
                $saved = json_decode($row['value'], true);
                if (is_array($saved)) {
                    $thresholds = array_merge($defaults, $saved);
                }
            }
            echo json_encode(['success' => true, 'data' => $thresholds], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getAlertThresholds: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta alert-trösklar'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function saveAlertThresholds() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->ensureAlertThresholdsRow();
            $allowed = array_keys($this->defaultAlertThresholds());
            $cleaned = [];
            foreach ($allowed as $key) {
                if (isset($data[$key])) {
                    $cleaned[$key] = max(0, intval($data[$key]));
                }
            }
            $json = json_encode($cleaned, JSON_UNESCAPED_UNICODE);
            $stmt = $this->pdo->prepare(
                "UPDATE tvattlinje_settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting = 'alert_thresholds'"
            );
            $stmt->execute([$json]);
            AuditLogger::log($this->pdo, 'update_tvattlinje_alert_thresholds', 'tvattlinje_settings', null,
                'thresholds=' . $json);
            echo json_encode(['success' => true, 'message' => 'Alert-trösklar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::saveAlertThresholds: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara alert-trösklar'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Veckodagsmål
    // =========================================================

    private function ensureWeekdayGoalsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tvattlinje_weekday_goals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                weekday TINYINT NOT NULL UNIQUE COMMENT '0=Måndag, 6=Söndag',
                mal INT NOT NULL DEFAULT 140,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [[0,140],[1,140],[2,140],[3,140],[4,140],[5,60],[6,0]];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO tvattlinje_weekday_goals (weekday, mal) VALUES (?, ?)");
        foreach ($defaults as [$wd, $mal]) {
            $stmt->execute([$wd, $mal]);
        }
        $this->pdo->exec("UPDATE tvattlinje_weekday_goals SET mal=140 WHERE weekday IN (0,1,2,3,4) AND mal=80");
    }

    private function getWeekdayGoals() {
        try {
            $this->ensureWeekdayGoalsTable();
            $rows = $this->pdo->query("SELECT weekday, mal FROM tvattlinje_weekday_goals ORDER BY weekday")->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getWeekdayGoals: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodagsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function setWeekdayGoals() {
        $data  = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $goals = $data['goals'] ?? [];
        if (!is_array($goals)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->ensureWeekdayGoalsTable();
            $stmt = $this->pdo->prepare("UPDATE tvattlinje_weekday_goals SET mal = ? WHERE weekday = ?");
            foreach ($goals as $item) {
                $wd  = intval($item['weekday'] ?? -1);
                $mal = max(0, intval($item['mal'] ?? 0));
                if ($wd >= 0 && $wd <= 6) {
                    $stmt->execute([$mal, $wd]);
                }
            }
            AuditLogger::log($this->pdo, 'update_tvattlinje_weekday_goals', 'tvattlinje_weekday_goals', null,
                'goals=' . count($goals));
            echo json_encode(['success' => true, 'message' => 'Veckodagsmål sparade'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::setWeekdayGoals: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara veckodagsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Befintliga metoder (oförändrade)
    // =========================================================

    /**
     * Deduplicerad skiftrapport-IBC per dag ['Y-m-d' => ibc].
     * KONTINUITET: Multipla skiftrapport-poster för samma (datum, skiftraknare) är
     * snapshots av samma skift — ta senaste posten (MAX id), summera aldrig snapshots.
     * (SUM över snapshots dubbelräknade dagen: 291 istället för faktiska 138.)
     */
    private function skiftrapportIbcPerDag(string $start, string $end): array {
        $out = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(sr.datum) AS dag, COALESCE(SUM(sr.totalt), 0) AS ibc
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                GROUP BY DATE(sr.datum)
            ");
            $stmt->execute(['s' => $start, 'e' => $end]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $out[$row['dag']] = (int)$row['ibc'];
            }
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::skiftrapportIbcPerDag: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * PLC-IBC per dag ['Y-m-d' => ibc] = MAX(ibc_count) (kumulativ dygnsräknare).
     */
    private function plcIbcPerDag(string $start, string $end): array {
        $out = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, MAX(ibc_count) AS ibc
                FROM tvattlinje_ibc
                WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                GROUP BY DATE(datum)
            ");
            $stmt->execute(['s' => $start, 'e' => $end]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $out[$row['dag']] = (int)$row['ibc'];
            }
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::plcIbcPerDag: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * VPS-lokal endpoint: sammanslagen PLC-först IBC per dag för ett datumintervall.
     * ?action=tvattlinje&run=ibc-per-dag[&start=YYYY-MM-DD][&end=YYYY-MM-DD]
     * PLC (MAX ibc_count) vinner för alla dagar med PLC-data; deduplicerad skiftrapport
     * fyller PLC-lösa dagar. Används av skiftrapport-sidan (action=lineskiftrapport är
     * Pi-passthru och kan inte leverera detta) för korrekta dag-/grand-totaler.
     */
    private function getIbcPerDag() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d', strtotime('-90 days'));
            $end   = $_GET['end']   ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d', strtotime('-90 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');

            $merged = $this->skiftrapportIbcPerDag($start, $end);
            foreach ($this->plcIbcPerDag($start, $end) as $dag => $ibc) {
                $merged[$dag] = $ibc; // PLC vinner för alla dagar med PLC-data
            }
            echo json_encode([
                'success'         => true,
                'day_totals'      => $merged,
                'grand_total_ibc' => array_sum($merged),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getIbcPerDag: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta IBC per dag'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Körstatus baserat på FAKTISK PLC-produktion, inte bara onoff.
     * tvattlinje_onoff loggar bara TILLSTÅNDSÄNDRINGAR — en linje som kört sedan 07:04
     * har bara ETT onoff-event. Att mäta färskhet mot onoff-tidsstämpeln nedgraderar då
     * felaktigt en körande linje till "stoppad". Mät i stället mot senaste IBC-puls.
     * Returnerar ['running'=>bool, 'last_update'=>?string].
     */
    private function computeRunningState(): array {
        $onoffDatum = null; $isOn = false;
        try {
            $r = $this->pdo->query("SELECT running, datum FROM tvattlinje_onoff ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            if ($r) { $isOn = (bool)($r['running'] ?? false); $onoffDatum = $r['datum'] ?? null; }
        } catch (\Throwable $e) { error_log('TvattlinjeController::computeRunningState onoff: ' . $e->getMessage()); }

        $lastIbcDatum = null;
        try {
            $v = $this->pdo->query("
                SELECT MAX(datum) FROM tvattlinje_ibc
                WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
            ")->fetchColumn();
            $lastIbcDatum = ($v !== false && $v !== null) ? $v : null;
        } catch (\Throwable $e) { error_log('TvattlinjeController::computeRunningState ibc: ' . $e->getMessage()); }

        // UI-tid = senaste av onoff-event och senaste IBC (visa 08:54, inte 07:04)
        $lastUpdate = $onoffDatum;
        if ($lastIbcDatum !== null && ($lastUpdate === null || strtotime($lastIbcDatum) > strtotime($lastUpdate))) {
            $lastUpdate = $lastIbcDatum;
        }

        // Åldersgrind mot faktisk produktion: nedgradera bara till "ej körning" om ingen
        // PLC-aktivitet senaste 15 min (IBC om det finns, annars onoff-eventet).
        $running = $isOn;
        if ($running) {
            $freshRef = $lastIbcDatum ?? $onoffDatum;
            if ($freshRef !== null) {
                $tz = new \DateTimeZone('Europe/Stockholm');
                $diffMin = ((new \DateTime('now', $tz))->getTimestamp()
                          - (new \DateTime($freshRef, $tz))->getTimestamp()) / 60;
                if ($diffMin > 15) $running = false;
            }
        }
        return ['running' => $running, 'last_update' => $lastUpdate];
    }

    private function getLiveStats() {
        // Filcache 10s TTL — nyckel: dagens datum (Europe/Stockholm)
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $_cacheDay = (new \DateTime('now', new \DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
        $cacheFile = $cacheDir . '/tvattlinje_livestats_' . $_cacheDay . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 10) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false) {
                header('Content-Type: application/json; charset=utf-8');
                echo $cached;
                return;
            }
        }
        try {
            // Primär källa: PLC (MAX(ibc_count) = kumulativ räknare, nollställs varje dag)
            $stmtPlc = $this->pdo->query('
                SELECT COALESCE(MAX(ibc_count), 0)
                FROM tvattlinje_ibc
                WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
            ');
            $plcToday = (int)$stmtPlc->fetchColumn();

            // Fallback: inskickade skiftrapporter (operatörsbekräftad data)
            // KONTINUITET: dedup — senaste post per skiftraknare, summera aldrig snapshots
            // för samma skift (annars dubbelräknades dagen: 291 istället för faktiska 138).
            $stmtSr = $this->pdo->query('
                SELECT COALESCE(SUM(sr.totalt), 0)
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
                    GROUP BY COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
            ');
            $skiftrapportToday = (int)$stmtSr->fetchColumn();

            // Välj värde: PLC primär (faktisk maskinräknare), skiftrapport som fallback
            $ibcToday = $plcToday > 0 ? $plcToday : $skiftrapportToday;

            // Beräkna metadata för klienten
            $dataSource  = $plcToday > 0 ? 'plc' : 'skiftrapport';
            $ibcEmpty    = $plcToday === 0 && $skiftrapportToday === 0;
            $divergent   = false;
            if ($plcToday > 0 && $skiftrapportToday > 0) {
                $maxVal    = max($plcToday, $skiftrapportToday);
                $divergent = (abs($plcToday - $skiftrapportToday) / $maxVal) > 0.20;
            }
            
            // Läs dagmål — försöker key-value-tabellen (ny kod) och faller tillbaka på gamla kolumnen
            $ibcTarget = 140;
            try {
                $this->ensureSettingsTable();
                $row = $this->pdo->query("SELECT value FROM tvattlinje_settings WHERE setting = 'dagmal' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                if ($row && (int)$row['value'] > 0) {
                    $ibcTarget = (int)$row['value'];
                } else {
                    $settings = $this->loadSettings();
                    if (!empty($settings['antal_per_dag'])) $ibcTarget = (int)$settings['antal_per_dag'];
                }
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getLiveStats dagmal: ' . $e->getMessage());
            }
            // Veckodagsmål override (samma logik som getTodaySnapshot)
            try {
                $this->ensureWeekdayGoalsTable();
                $tz  = new \DateTimeZone('Europe/Stockholm');
                $isoDay = (int)(new \DateTime('now', $tz))->format('N') - 1; // 0=Måndag
                $wg = $this->pdo->prepare("SELECT mal FROM tvattlinje_weekday_goals WHERE weekday = ?");
                $wg->execute([$isoDay]);
                $wgRow = $wg->fetch(\PDO::FETCH_ASSOC);
                if ($wgRow && $wgRow['mal'] !== null) {
                    $ibcTarget = (int)$wgRow['mal'];
                }
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getLiveStats weekdayGoal: ' . $e->getMessage());
            }

            // Beräkna hourlyTarget baserat på 8 timmars arbetstid
            $hourlyTarget = $ibcTarget / 8;

            // Beräkna total runtime för idag
            $totalRuntimeMinutes = 0;
            $now = new DateTime('now', new DateTimeZone('Europe/Stockholm'));

            // Hämta alla rader för idag sorterade efter datum
            $stmt = $this->pdo->prepare('
                SELECT datum, running
                FROM tvattlinje_onoff
                WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
                ORDER BY datum ASC
            ');
            $stmt->execute();
            $todayEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($todayEntries) > 0) {
                $lastRunningStart = null;
                
                $tzSe = new DateTimeZone('Europe/Stockholm');
                foreach ($todayEntries as $entry) {
                    $entryTime = new DateTime($entry['datum'], $tzSe);
                    $isRunning = (bool)($entry['running'] ?? false);
                    
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $entryTime;
                    } elseif (!$isRunning && $lastRunningStart !== null) {
                        $diff = $lastRunningStart->diff($entryTime);
                        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $totalRuntimeMinutes += $periodMinutes;
                        $lastRunningStart = null;
                    }
                }
                
                if ($lastRunningStart !== null) {
                    $lastEntryTime = new DateTime($todayEntries[count($todayEntries) - 1]['datum'], $tzSe);
                    $diff = $lastRunningStart->diff($lastEntryTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                    
                    $diffSinceLast = $lastEntryTime->diff($now);
                    $minutesSinceLastUpdate = ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                    $totalRuntimeMinutes += $minutesSinceLastUpdate;
                }
            }

            if ((float)$totalRuntimeMinutes < 0.001 && $ibcToday > 0) {
                $stmt = $this->pdo->prepare(
                    'SELECT MIN(datum) as first_ibc, MAX(datum) as last_ibc FROM tvattlinje_ibc WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY'
                );
                $stmt->execute();
                $ibcRange = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ibcRange && $ibcRange['first_ibc']) {
                    $firstIbc = new DateTime($ibcRange['first_ibc'], new DateTimeZone('Europe/Stockholm'));
                    $lastIbc  = new DateTime($ibcRange['last_ibc'], new DateTimeZone('Europe/Stockholm'));
                    $spanDiff = $firstIbc->diff($lastIbc);
                    $spanMin  = ($spanDiff->days * 1440) + ($spanDiff->h * 60) + $spanDiff->i + ($spanDiff->s / 60);
                    if ($spanMin < 1) { $spanMin = 5; }
                    $diffSinceLast = $lastIbc->diff($now);
                    $minSinceLast  = ($diffSinceLast->days * 1440) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                    if ($minSinceLast < 30) { $spanMin += $minSinceLast; }
                    $totalRuntimeMinutes = $spanMin;
                }
            }

            $productionPercentage = 0;
            if ($ibcToday > 0 && $ibcTarget > 0) {
                $productionPercentage = round(($ibcToday / $ibcTarget) * 100, 1);
            }

            // Momentan takt: antal rader tvattlinje_ibc senaste 2h / 2.0
            $taktPerH       = 0.0;
            $taktPercentage = 0.0;
            try {
                $taktStmt = $this->pdo->query("SELECT COUNT(*) FROM tvattlinje_ibc WHERE datum >= DATE_SUB(NOW(), INTERVAL 2 HOUR)");
                $taktPer2h = (int)$taktStmt->fetchColumn();
                $taktPerH  = round($taktPer2h / 2.0, 1);
                $taktPercentage = $hourlyTarget > 0 ? round($taktPerH / $hourlyTarget * 100, 1) : 0.0;
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getLiveStats taktPerH: ' . $e->getMessage());
            }

            $utetemperatur = null;
            try {
                $stmt = $this->pdo->prepare('
                    SELECT utetemperatur, datum
                    FROM vader_data 
                    ORDER BY datum DESC 
                    LIMIT 1
                ');
                $stmt->execute();
                $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($weatherData) {
                    $utetemperatur = (float)$weatherData['utetemperatur'];
                }
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getLiveStats (väderdata): ' . $e->getMessage());
            }

            $response = [
                'success' => true,
                'data' => [
                    'ibcToday'          => $ibcToday,
                    'ibcTarget'         => $ibcTarget,
                    'productionPercentage' => $productionPercentage,
                    'utetemperatur'     => $utetemperatur,
                    'dataSource'        => $dataSource,
                    'plcToday'          => $plcToday,
                    'skiftrapportToday' => $skiftrapportToday,
                    'divergent'         => $divergent,
                    'empty'             => $ibcEmpty,
                    'taktPerH'          => $taktPerH,
                    'hourlyTarget'      => round($hourlyTarget, 1),
                    'taktPercentage'    => $taktPercentage,
                    // Körstatus mot faktisk PLC-produktion (VPS-lokal — run=status är Pi-passthru)
                    'running'           => $this->computeRunningState()['running'],
                ]
            ];
            
            if ($productionPercentage === 0 || $productionPercentage === null) {
                $response['debug'] = [
                    'totalRuntimeMinutes' => $totalRuntimeMinutes,
                    'ibcToday' => $ibcToday,
                    'hourlyTarget' => $hourlyTarget,
                    'productionPercentage' => $productionPercentage,
                    'calculation_condition' => [
                        'hasRuntime' => $totalRuntimeMinutes > 0,
                        'hasIbcToday' => $ibcToday > 0,
                        'hasHourlyTarget' => $hourlyTarget > 0
                    ]
                ];
            }
            
            $out = json_encode($response, JSON_UNESCAPED_UNICODE);
            @file_put_contents($cacheFile, $out, LOCK_EX);
            echo $out;
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getLiveStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=tvattlinje&run=plc-simulate
     * Simulerar PLC-signaler för testning (från PLC-diagnostik-konsolen).
     * Body JSON: { "command": "onoff", "value": "on"|"off" }
     *            { "command": "rast",  "value": "on"|"off" }
     */
    private function plcSimulate(): void {
        try {
            $body = json_decode(file_get_contents('php://input'), true);
            $command = $body['command'] ?? '';
            $value   = $body['value']   ?? '';

            if (!in_array($command, ['onoff', 'rast', 'driftstopp'])) {
                echo json_encode(['success' => false, 'error' => "Okänt kommando: $command. Tillgängliga: onoff, rast, driftstopp"], JSON_UNESCAPED_UNICODE);
                return;
            }
            if (!in_array($value, ['on', 'off'])) {
                echo json_encode(['success' => false, 'error' => "Ogiltigt värde: $value. Ange on eller off"], JSON_UNESCAPED_UNICODE);
                return;
            }

            $isOn = $value === 'on';

            switch ($command) {
                case 'onoff':
                    $lastRow = $this->pdo->query("SELECT runtime_today FROM tvattlinje_onoff ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                    $rt = $lastRow ? floatval($lastRow['runtime_today']) : 0;
                    $stmt = $this->pdo->prepare("INSERT INTO tvattlinje_onoff (s_count_h, s_count_l, running, runtime_today) VALUES (0, 0, :running, :rt)");
                    $stmt->execute(['running' => $isOn ? 1 : 0, 'rt' => $rt]);
                    $msg = $isOn ? "Linje STARTAD (simulerad)" : "Linje STOPPAD (simulerad)";
                    break;

                case 'rast':
                    // Skriv till tvattlinje_rast (fallback tvattlinje_runtime)
                    $inserted = false;
                    foreach (['tvattlinje_rast', 'tvattlinje_runtime'] as $tbl) {
                        try {
                            $stmt = $this->pdo->prepare("INSERT INTO {$tbl} (datum, rast_status) VALUES (NOW(), :status)");
                            $stmt->execute(['status' => $isOn ? 1 : 0]);
                            $inserted = true;
                            break;
                        } catch (\Throwable) { /* prova nästa */ }
                    }
                    if (!$inserted) throw new \RuntimeException("Ingen rasttabell tillgänglig");
                    $msg = $isOn ? "Rast STARTAD (simulerad)" : "Rast AVSLUTAD (simulerad)";
                    break;

                case 'driftstopp':
                    try {
                        $stmt = $this->pdo->prepare("INSERT INTO tvattlinje_driftstopp (datum, status) VALUES (NOW(), :s)");
                        $stmt->execute(['s' => $isOn ? 'start' : 'slut']);
                    } catch (\Throwable $e) {
                        $stmt = $this->pdo->prepare("INSERT INTO tvattlinje_driftstopp (datum, driftstopp_status) VALUES (NOW(), :s)");
                        $stmt->execute(['s' => $isOn ? 1 : 0]);
                    }
                    $msg = $isOn ? "Driftstopp AKTIVERAT (simulerat)" : "Driftstopp AVSLUTAT (simulerat)";
                    break;

                default:
                    $msg = '';
            }

            echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log("TvattlinjeController::plcSimulate: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Simulering misslyckades: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=tvattlinje&run=driftstopp
     * Speglar getRastStatus() men för driftstopp.
     */
    private function getDriftstoppStatus(): void {
        try {
            $tz = new \DateTimeZone('Europe/Stockholm');
            $now = new \DateTime('now', $tz);
            $todayStr = $now->format('Y-m-d');

            // Läs events — stöd både ny (status VARCHAR) och gammal (driftstopp_status TINYINT) kolumnstruktur
            $events = [];
            try {
                $stmt = $this->pdo->prepare("SELECT id, datum, status FROM tvattlinje_driftstopp WHERE DATE(datum) = :today ORDER BY datum ASC");
                $stmt->execute(['today' => $todayStr]);
                $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // Gammal schema: driftstopp_status TINYINT — normalisera till 'start'/'slut'
                try {
                    $stmt = $this->pdo->prepare("SELECT id, datum, driftstopp_status FROM tvattlinje_driftstopp WHERE DATE(datum) = :today ORDER BY datum ASC");
                    $stmt->execute(['today' => $todayStr]);
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $row['status'] = (int)$row['driftstopp_status'] === 1 ? 'start' : 'slut';
                        $events[] = $row;
                    }
                } catch (\Throwable $e2) { error_log('TvattlinjeController::getDriftstoppStatus fetch: ' . $e2->getMessage()); }
            }

            $totalMinutes = 0;
            $stoppStart = null;
            $currentlyOnStopp = false;

            foreach ($events as $event) {
                if (($event['status'] ?? '') === 'start' && $stoppStart === null) {
                    $stoppStart = new \DateTime($event['datum'], $tz);
                    $currentlyOnStopp = true;
                } elseif (($event['status'] ?? '') === 'slut' && $stoppStart !== null) {
                    $end = new \DateTime($event['datum'], $tz);
                    $diff = $stoppStart->diff($end);
                    $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalMinutes += max(0, (int)round($minutes));
                    $stoppStart = null;
                    $currentlyOnStopp = false;
                }
            }

            if ($stoppStart !== null) {
                $diff = $stoppStart->diff($now);
                $minutesOpen = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $minutesOpen = max(0, (int)round($minutesOpen));
                if ($minutesOpen <= 480) {
                    $currentlyOnStopp = true;
                    $totalMinutes += $minutesOpen;
                } else {
                    $currentlyOnStopp = false;
                }
            }

            $latestEvent = !empty($events) ? end($events) : null;

            echo json_encode([
                'success' => true,
                'data' => [
                    'on_driftstopp'            => $currentlyOnStopp,
                    'driftstopp_minutes_today' => round($totalMinutes, 1),
                    'driftstopp_count_today'   => count(array_filter($events, fn($e) => ($e['status'] ?? '') === 'start')),
                    'last_event'               => $latestEvent ? $latestEvent['datum'] : null,
                    'events'                   => array_map(fn($e) => [
                        'datum'             => $e['datum'],
                        'driftstopp_status' => ($e['status'] ?? '') === 'start' ? 1 : 0
                    ], $events)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getDriftstoppStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta driftstoppstatus'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=tvattlinje&run=rast
     *
     * Hämtar aktuell raststatus och beräknar total rasttid idag.
     * Läser från tvattlinje_rast (fallback tvattlinje_runtime).
     * rast_status=1 = rast börjar, rast_status=0 = rast slutar.
     */
    private function getRastStatus() {
        try {
            $tz = new \DateTimeZone('Europe/Stockholm');
            $now = new \DateTime('now', $tz);
            $todayStr = $now->format('Y-m-d');

            // Slå ihop tvattlinje_rast + tvattlinje_runtime — prod-plcbackend kan skriva
            // till runtime tills TvattLinje.php uppdateras med sudo på produktionsservern
            $events = [];
            foreach (['tvattlinje_rast', 'tvattlinje_runtime'] as $rastTable) {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT id, datum, rast_status
                        FROM {$rastTable}
                        WHERE DATE(datum) = :today
                        ORDER BY datum ASC
                    ");
                    $stmt->execute(['today' => $todayStr]);
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $events[] = $row;
                    }
                } catch (\Throwable $e) { /* tabell saknas */ }
            }
            usort($events, fn($a, $b) => strcmp($a['datum'], $b['datum']));

            $totalRastMinutes = 0;
            $rastStart = null;
            $currentlyOnRast = false;

            foreach ($events as $event) {
                if ((int)$event['rast_status'] === 1 && $rastStart === null) {
                    $rastStart = new \DateTime($event['datum'], $tz);
                    $currentlyOnRast = true;
                } elseif ((int)$event['rast_status'] === 0 && $rastStart !== null) {
                    $end = new \DateTime($event['datum'], $tz);
                    $diff = $rastStart->diff($end);
                    $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRastMinutes += max(0, (int)round($minutes));
                    $rastStart = null;
                    $currentlyOnRast = false;
                }
            }

            // Pågående rast — räkna till nu (max 8h)
            if ($rastStart !== null) {
                $diff = $rastStart->diff($now);
                $minutesOpen = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $minutesOpen = max(0, (int)round($minutesOpen));
                if ($minutesOpen <= 480) {
                    $currentlyOnRast = true;
                    $totalRastMinutes += $minutesOpen;
                } else {
                    $currentlyOnRast = false;
                }
            }

            $latestEvent = !empty($events) ? end($events) : null;

            echo json_encode([
                'success' => true,
                'data' => [
                    'on_rast'            => $currentlyOnRast,
                    'rast_minutes_today' => round($totalRastMinutes, 1),
                    'rast_count_today'   => count(array_filter($events, fn($e) => (int)$e['rast_status'] === 1)),
                    'last_event'         => $latestEvent ? $latestEvent['datum'] : null,
                    'events'             => array_map(fn($e) => [
                        'datum'       => $e['datum'],
                        'rast_status' => (int)$e['rast_status']
                    ], $events)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getRastStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta raststatus'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getRunningStatus() {
        // Kort filcache (5s) — statusen är minut-skalig (running/idle + dygnsräknare),
        // så 5s är osynligt men kapar DB-round-trips över den strypta tunneln drastiskt.
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $_cd = (new \DateTime('now', new \DateTimeZone('Europe/Stockholm')))->format('Y-m-d');
        $cacheFile = $cacheDir . '/tvattlinje_runningstatus_' . $_cd . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false) { header('Content-Type: application/json; charset=utf-8'); echo $cached; return; }
        }
        try {
            // Körstatus + färskhet mot FAKTISK PLC-produktion (se computeRunningState):
            // onoff loggar bara tillståndsändringar, så mät inte färskhet mot onoff-tiden.
            $state      = $this->computeRunningState();
            $isRunning  = $state['running'];
            $lastUpdate = $state['last_update'];

            // Hämta rast-status från tvattlinje_rast (fallback tvattlinje_runtime)
            $onRast = false;
            $rastMinutesToday = 0;
            $rastCountToday = 0;

            $rastEvents = [];
            foreach (['tvattlinje_rast', 'tvattlinje_runtime'] as $rastTable) {
                try {
                    $stmt2 = $this->pdo->prepare("
                        SELECT datum, rast_status
                        FROM {$rastTable}
                        WHERE DATE(datum) = CURDATE()
                        ORDER BY datum ASC
                    ");
                    $stmt2->execute();
                    $rastEvents = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
                    break;
                } catch (\Throwable $e) { /* prova nästa */ }
            }

            // Beräkna rastminuter idag och on_rast
            if (!empty($rastEvents)) {
                $lastEvent = end($rastEvents);
                $onRast = (int)($lastEvent['rast_status'] ?? 0) === 1;

                $rastStart = null;
                $counts = 0;
                foreach ($rastEvents as $evt) {
                    $status = (int)($evt['rast_status'] ?? 0);
                    if ($status === 1 && $rastStart === null) {
                        $rastStart = new \DateTime($evt['datum']);
                        $counts++;
                    } elseif ($status === 0 && $rastStart !== null) {
                        $end = new \DateTime($evt['datum']);
                        $diff = $rastStart->diff($end);
                        $rastMinutesToday += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $rastStart = null;
                    }
                }
                // Pågående rast
                if ($rastStart !== null) {
                    $now = new \DateTime();
                    $diff = $rastStart->diff($now);
                    $rastMinutesToday += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                }
                $rastCountToday = $counts;
            }

            $out = json_encode([
                'success' => true,
                'data' => [
                    'running'            => $isRunning,
                    'lastUpdate'         => $lastUpdate,
                    'on_rast'            => $onRast,
                    'rast_minutes_today' => round($rastMinutesToday, 1),
                    'rast_count_today'   => $rastCountToday,
                ]
            ], JSON_UNESCAPED_UNICODE);
            @file_put_contents($cacheFile, $out, LOCK_EX);
            echo $out;
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getRunningStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta status'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getAdminSettings() {
        try {
            $settings = $this->loadSettings();
            
            echo json_encode([
                'success' => true,
                'data' => $settings
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getAdminSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta admin-inställningar'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function saveAdminSettings() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (!isset($data['antal_per_dag'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'antal_per_dag är obligatoriskt'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $antal_per_dag = max(0, min(99999, intval($data['antal_per_dag'])));
            $timtakt       = isset($data['timtakt'])    ? max(1, min(999, intval($data['timtakt'])))         : 20;
            $skiftlangdRaw = isset($data['skiftlangd']) ? floatval($data['skiftlangd']) : 8.0;
            $skiftlangd    = is_finite($skiftlangdRaw) ? max(1.0, min(24.0, $skiftlangdRaw)) : 8.0;

            try {
                $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN timtakt INT NOT NULL DEFAULT 20");
            } catch (\Throwable $e) { /* Kolumn finns redan — OK */ }
            try {
                $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0");
            } catch (\Throwable $e) { /* Kolumn finns redan — OK */ }

            // Använd INSERT ... ON DUPLICATE KEY UPDATE för att undvika race condition
            // (concurrent requests som båda ser COUNT=0 och försöker INSERT)
            $stmt = $this->pdo->prepare(
                "INSERT INTO tvattlinje_settings (id, antal_per_dag, timtakt, skiftlangd)
                 VALUES (1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE antal_per_dag = VALUES(antal_per_dag), timtakt = VALUES(timtakt), skiftlangd = VALUES(skiftlangd), updated_at = NOW()"
            );
            $stmt->execute([$antal_per_dag, $timtakt, $skiftlangd]);

            AuditLogger::log($this->pdo, 'update_tvattlinje_settings', 'tvattlinje_settings', 1,
                "antal_per_dag={$antal_per_dag} timtakt={$timtakt} skiftlangd={$skiftlangd}");
            echo json_encode([
                'success' => true,
                'message' => 'Inställningar sparade',
                'data' => [
                    'antal_per_dag' => $antal_per_dag,
                    'timtakt'       => $timtakt,
                    'skiftlangd'    => $skiftlangd
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::saveAdminSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte spara inställningar'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function loadSettings() {
        try {
            $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN timtakt INT NOT NULL DEFAULT 20");
        } catch (\Throwable) { /* Kolumn finns redan — OK */ }
        try {
            $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0");
        } catch (\Throwable) { /* Kolumn finns redan — OK */ }

        $stmt = $this->pdo->query("SELECT * FROM tvattlinje_settings ORDER BY id ASC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            return [
                'id'            => 1,
                'antal_per_dag' => 150,
                'timtakt'       => 20,
                'skiftlangd'    => 8.0
            ];
        }

        $settings['timtakt']    = isset($settings['timtakt'])    ? intval($settings['timtakt'])      : 20;
        $skiftlangdDb = isset($settings['skiftlangd']) ? floatval($settings['skiftlangd']) : 8.0;
        $settings['skiftlangd'] = is_finite($skiftlangdDb) ? max(1.0, min(24.0, $skiftlangdDb)) : 8.0;

        return $settings;
    }

    private function getStatistics() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d');
            $end   = $_GET['end']   ?? date('Y-m-d');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');

            // Filcache — nyckel: start+end (paverkar svaret).
            // HISTORISK IMMUTABILITET: en avslutad period ($end < idag) ändras aldrig
            // → cacha 7 dygn i st f 15s. Skyddar den strypta 2Mbit-länken: varje
            // historisk månad hämtas EN gång (rå cykler), sen ~1ms för alla. Löpande
            // period behåller 15s för färskhet.
            $cacheDir = dirname(__DIR__) . '/cache';
            if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
            // Versionerad nyckel (CodeVersion) → gammal cache blir oåtkomlig direkt vid deploy,
            // så TTL 604800 (avslutad period) inte längre maskerar backend-fixar.
            $cacheFile = $cacheDir . '/tvattlinje_statistics_' . CodeVersion::get() . '_' . $start . '_' . $end . '.json';
            $statsTtl = ($end < date('Y-m-d')) ? 604800 : 15;
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $statsTtl) {
                $cached = file_get_contents($cacheFile);
                if ($cached !== false) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo $cached;
                    return;
                }
            }

            // Hämta takt_mal från settings
            $target_cycle_time = 3.0;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT value FROM tvattlinje_settings WHERE setting = 'takt_mal' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                if ($sr && (float)$sr['value'] > 0) $target_cycle_time = (float)$sr['value'];
            } catch (\Throwable $e) { /* ignorera */ }

            // Hämta cyklar — alla kolumner (även utökade för Avancerat-flik).
            // Fallback till baskolumner om migreringen ej är genomförd ännu.
            $rawCycles = [];
            try {
                $stmt = $this->pdo->prepare('
                    SELECT
                        i.datum,
                        i.ibc_count,
                        i.s_count,
                        i.ibc_ok,
                        i.ibc_ej_ok,
                        i.omtvaatt,
                        i.runtime_plc,
                        i.rasttime,
                        i.lopnummer,
                        i.skiftraknare,
                        i.op1,
                        i.op2,
                        i.op3,
                        TIMESTAMPDIFF(SECOND,
                            LAG(i.datum) OVER (ORDER BY i.datum),
                            i.datum
                        ) / 60.0 as cycle_time
                    FROM tvattlinje_ibc i
                    WHERE i.datum >= :start AND i.datum < DATE_ADD(:end, INTERVAL 1 DAY)
                    ORDER BY i.datum ASC
                ');
                $stmt->execute(['start' => $start, 'end' => $end]);
                $rawCycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                // Utökade kolumner saknas ännu — hämta bara baskolumner
                error_log('TvattlinjeController::getStatistics extended columns missing, using fallback: ' . $e->getMessage());
                try {
                    $stmt = $this->pdo->prepare('
                        SELECT
                            i.datum,
                            i.ibc_count,
                            i.s_count,
                            NULL as ibc_ok,
                            NULL as ibc_ej_ok,
                            NULL as omtvaatt,
                            NULL as runtime_plc,
                            NULL as rasttime,
                            NULL as lopnummer,
                            NULL as skiftraknare,
                            NULL as op1,
                            NULL as op2,
                            NULL as op3,
                            TIMESTAMPDIFF(SECOND,
                                LAG(i.datum) OVER (ORDER BY i.datum),
                                i.datum
                            ) / 60.0 as cycle_time
                        FROM tvattlinje_ibc i
                        WHERE i.datum >= :start AND i.datum < DATE_ADD(:end, INTERVAL 1 DAY)
                        ORDER BY i.datum ASC
                    ');
                    $stmt->execute(['start' => $start, 'end' => $end]);
                    $rawCycles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e2) {
                    error_log('TvattlinjeController::getStatistics fallback query failed: ' . $e2->getMessage());
                }
            }

            $cycles = [];
            foreach ($rawCycles as $cycle) {
                $ct = $cycle['cycle_time'] !== null ? (float)$cycle['cycle_time'] : null;
                if ($ct !== null && $ct > 0 && $ct <= 30) {
                    $cycle['cycle_time'] = round($ct, 2);
                    $cycles[] = $cycle;
                } else {
                    $cycle['cycle_time'] = null;
                    // Inkludera ändå för att visa i tidslinje
                    $cycles[] = $cycle;
                }
            }

            // On/off-events
            $stmt = $this->pdo->prepare('
                SELECT datum, running, runtime_today
                FROM tvattlinje_onoff
                WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $onoff_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Rast-events (från tvattlinje_rast eller tvattlinje_runtime)
            $rast_events = [];
            foreach (['tvattlinje_rast', 'tvattlinje_runtime'] as $rast_table) {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT datum, rast_status
                        FROM {$rast_table}
                        WHERE DATE(datum) BETWEEN :start AND :end
                        ORDER BY datum ASC
                    ");
                    $stmt->execute(['start' => $start, 'end' => $end]);
                    $rast_events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    if (count($rast_events) > 0) break;
                } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics rast ' . $rast_table . ': ' . $e->getMessage()); }
            }

            // Driftstopp-events — bakåtkompatibel: ny schema (status VARCHAR) eller gammal (driftstopp_status TINYINT)
            $driftstopp_events = [];
            try {
                $raw_ds = [];
                try {
                    $stmt = $this->pdo->prepare("SELECT datum, status FROM tvattlinje_driftstopp WHERE DATE(datum) BETWEEN :start AND :end ORDER BY datum ASC");
                    $stmt->execute(['start' => $start, 'end' => $end]);
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $row['driftstopp_status'] = ($row['status'] ?? '') === 'start' ? 1 : 0;
                        $raw_ds[] = $row;
                    }
                } catch (\Throwable $e) {
                    $stmt = $this->pdo->prepare("SELECT datum, driftstopp_status FROM tvattlinje_driftstopp WHERE DATE(datum) BETWEEN :start AND :end ORDER BY datum ASC");
                    $stmt->execute(['start' => $start, 'end' => $end]);
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $row['status'] = (int)$row['driftstopp_status'] === 1 ? 'start' : 'slut';
                        $raw_ds[] = $row;
                    }
                }
                $driftstopp_events = $raw_ds;
            } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics driftstopp: ' . $e->getMessage()); }

            // BUGG #106: Primär källa för drifttid är SUM(drifttid) från tvattlinje_skiftrapport.
            // Drifttid-kolumnen är PLC-register D4007 som räknar NETTO körtid (exkluderar rast automatiskt).
            // on/off-sensor-span används ENBART som fallback när inga skiftrapporter finns — det spannet
            // inkluderar rasttid och ger därför ett för högt värde (bugg: statistik visade 3h, skiftrapport 1h 46m).
            $totalRuntimeMinutes = 0;
            $runtimeSource = 'none';

            // BUGG T1: Drifttid byggs PER DAG över UNIONEN av dagar från tvattlinje_ibc
            // + tvattlinje_skiftrapport, med källprioritet per dag:
            //   1) SUM(sr.drifttid) — deduplicerad (senaste post per skiftraknare), D4007 netto körtid
            //   2) MAX(ibc.runtime_plc) — D4007 netto körtid från PLC (dagar utan inskickad skiftrapport)
            //   3) MAX(onoff.runtime_today) — sista fallback
            // Tidigare byggdes drifttid ENBART från skiftrapport-dagar (yttre FROM tvattlinje_skiftrapport
            // GROUP BY DATE) => dagar med PLC-IBC men utan inskickad rapport (t.ex. 2026-07-02, 159 IBC)
            // fick 0 min körtid, medan IBC-totalen kom från PLC för ALLA dagar => asymmetrisk statistik
            // (831 IBC men bara 19.1h). LEAST(day_min, 600) kapar per dag (max ~10h/skift).
            try {
                $driftStmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(LEAST(day_min, 600)), 0)
                    FROM (
                        SELECT d.dag,
                               COALESCE(NULLIF(sr.drift_min, 0), ibc.plc_min, onoff.onoff_min, 0) AS day_min
                        FROM (
                            SELECT DATE(datum) AS dag FROM tvattlinje_skiftrapport
                            WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                            UNION
                            SELECT DATE(datum) AS dag FROM tvattlinje_ibc
                            WHERE datum >= :s2 AND datum < DATE_ADD(:e2, INTERVAL 1 DAY)
                        ) d
                        LEFT JOIN (
                            SELECT DATE(sr.datum) AS dag, SUM(sr.drifttid) AS drift_min
                            FROM tvattlinje_skiftrapport sr
                            INNER JOIN (
                                SELECT MAX(id) AS max_id FROM tvattlinje_skiftrapport
                                WHERE datum >= :s3 AND datum < DATE_ADD(:e3, INTERVAL 1 DAY)
                                GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                            ) latest ON sr.id = latest.max_id
                            GROUP BY DATE(sr.datum)
                        ) sr ON sr.dag = d.dag
                        LEFT JOIN (
                            SELECT DATE(datum) AS dag, MAX(runtime_plc) AS plc_min
                            FROM tvattlinje_ibc
                            WHERE datum >= :s4 AND datum < DATE_ADD(:e4, INTERVAL 1 DAY)
                              AND runtime_plc IS NOT NULL AND runtime_plc > 0
                            GROUP BY DATE(datum)
                        ) ibc ON ibc.dag = d.dag
                        LEFT JOIN (
                            SELECT DATE(datum) AS dag, MAX(runtime_today) AS onoff_min
                            FROM tvattlinje_onoff
                            WHERE datum >= :s5 AND datum < DATE_ADD(:e5, INTERVAL 1 DAY)
                              AND runtime_today > 0
                            GROUP BY DATE(datum)
                        ) onoff ON onoff.dag = d.dag
                    ) per_dag
                ");
                $driftStmt->execute([
                    's'  => $start, 'e'  => $end, 's2' => $start, 'e2' => $end,
                    's3' => $start, 'e3' => $end, 's4' => $start, 'e4' => $end,
                    's5' => $start, 'e5' => $end,
                ]);
                $srDrifttid = (float)$driftStmt->fetchColumn();
                if ($srDrifttid > 0) {
                    $totalRuntimeMinutes = $srDrifttid;
                    $runtimeSource = 'per_dag';
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics drifttid_primary: ' . $e->getMessage()); }

            // Fallback: on/off-sensor-spann (klampa vid dygngräns för att exkludera natt-idle).
            // Notera: detta spann inkluderar rasttid — används bara när inga skiftrapporter finns.
            if ($totalRuntimeMinutes == 0 && count($onoff_events) > 0) {
                $lastRunningStart = null;
                $tz = new \DateTimeZone('Europe/Stockholm');
                $addSpan = function(\DateTime $from, \DateTime $to) use (&$totalRuntimeMinutes): void {
                    $midnight = (clone $from)->setTime(23, 59, 59);
                    $secs = max(0, min($to->getTimestamp(), $midnight->getTimestamp()) - $from->getTimestamp());
                    $totalRuntimeMinutes += $secs / 60.0;
                };
                foreach ($onoff_events as $event) {
                    $eventTime = new \DateTime($event['datum'], $tz);
                    $isRunning = (bool)($event['running'] ?? false);
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $eventTime;
                    } elseif (!$isRunning && $lastRunningStart !== null) {
                        $addSpan($lastRunningStart, $eventTime);
                        $lastRunningStart = null;
                    }
                }
                if ($lastRunningStart !== null) {
                    $lastEvt = new \DateTime($onoff_events[count($onoff_events) - 1]['datum'], $tz);
                    $addSpan($lastRunningStart, $lastEvt);
                }
                if ($totalRuntimeMinutes > 0) $runtimeSource = 'onoff';
            }

            // ibc_count nollställs varje dag (1..N) — SUM(MAX per dag) ger korrekt totalt antal.
            // missed_webhooks = total_cycles minus faktiskt mottagna rader.
            $total_cycles = 0;
            $received_webhooks = 0;
            try {
                $stmtTrue = $this->pdo->prepare('
                    SELECT COALESCE(SUM(day_max), 0), COALESCE(SUM(day_count), 0)
                    FROM (
                        SELECT MAX(ibc_count) AS day_max, COUNT(*) AS day_count
                        FROM tvattlinje_ibc
                        WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                        GROUP BY DATE(datum)
                    ) per_dag
                ');
                $stmtTrue->execute(['start' => $start, 'end' => $end]);
                $row = $stmtTrue->fetch(\PDO::FETCH_NUM);
                $total_cycles    = (int)($row[0] ?? 0);
                $received_webhooks = (int)($row[1] ?? 0);
            } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics total_cycles: ' . $e->getMessage()); }

            if ($total_cycles === 0) $total_cycles = count($cycles);
            // Natt-idle-fallback BORTTAGEN: span av första-sista cykel inkluderar natt-idle.
            // Om inga on/off-events finns visas drifttid som 0 (ingen maskinstatusdata).

            // IBC från inskickade skiftrapporter: SUM(totalt) = antal_ok + antal_ej_ok + omtvaatt.
            // Matchar "TOTAL IBC"-kolumnen i skiftrapportlistan.
            // Notera: total_cycles (puck-webhooks via ibc_count) kan skilja sig med ~5-10 IBCer
            // (testcykler/rengöring utan inskickad rapport, eller PLC-nollställning mellan dagar).
            // KONTINUITET: deduplicerad SR-karta (senaste post per skift) — undvik
            // dubbelräkning av snapshots. Total = summa över deduplicerade dagar.
            $ibcPerDagSr = $this->skiftrapportIbcPerDag($start, $end);
            $total_ibc_skiftrapport = array_sum($ibcPerDagSr);

            // Beräkna rasttid
            $totalRastMinutes = 0;
            if (count($rast_events) > 0) {
                $rastStart = null;
                foreach ($rast_events as $evt) {
                    $t = new DateTime($evt['datum'], new DateTimeZone('Europe/Stockholm'));
                    $status = (int)($evt['rast_status'] ?? 0);
                    if ($status === 1 && $rastStart === null) {
                        $rastStart = $t;
                    } elseif ($status === 0 && $rastStart !== null) {
                        $diff = $rastStart->diff($t);
                        $totalRastMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $rastStart = null;
                    }
                }
            }

            // Beräkna driftstopptid
            $totalDriftstoppMinutes = 0;
            if (count($driftstopp_events) > 0) {
                $dsStart = null;
                foreach ($driftstopp_events as $evt) {
                    $t = new DateTime($evt['datum'], new DateTimeZone('Europe/Stockholm'));
                    $status = (int)($evt['driftstopp_status'] ?? 0);
                    if ($status === 1 && $dsStart === null) {
                        $dsStart = $t;
                    } elseif ($status === 0 && $dsStart !== null) {
                        $diff = $dsStart->diff($t);
                        $totalDriftstoppMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $dsStart = null;
                    }
                }
            }

            // netRuntimeMinutes: när källan är skiftrapport/per_dag (D4007 resp runtime_plc) är rast redan
            // exkluderad av PLC:n — dra inte av $totalRastMinutes en gång till. Bara onoff-spannet
            // (wall-clock) innehåller rast och ska dra av den. Driftstopp dras alltid av.
            if ($runtimeSource === 'skiftrapport' || $runtimeSource === 'per_dag') {
                $netRuntimeMinutes = max(0, $totalRuntimeMinutes - $totalDriftstoppMinutes);
            } else {
                $netRuntimeMinutes = max(0, $totalRuntimeMinutes - $totalRastMinutes - $totalDriftstoppMinutes);
            }
            $total_runtime_hours = $netRuntimeMinutes / 60;

            // Snitt cykeltid
            $avg_cycle_time = 0;
            $cycle_times = array_filter(array_column($cycles, 'cycle_time'), fn($v) => $v !== null && $v > 0);
            if (count($cycle_times) > 0) {
                $avg_cycle_time = array_sum($cycle_times) / count($cycle_times);
            }

            // Effektivitet: target_cycle_time / avg_cycle_time * 100
            $avg_production_percent = 0;
            if ($avg_cycle_time > 0 && $target_cycle_time > 0) {
                $avg_production_percent = round(($target_cycle_time / $avg_cycle_time) * 100, 1);
            }

            $unique_dates = array_unique(array_map(fn($c) => date('Y-m-d', strtotime($c['datum'])), $cycles));
            $days_with_production = count($unique_dates);

            // Per-dag IBC-karta från skiftrapporter (source of truth för historiska dagar).
            // Redan deduplicerad ovan ($ibcPerDagSr) — återanvänds här för staplarna.

            // Per-dag IBC-karta från PLC (MAX(ibc_count) per dag — fyller in dagar utan skiftrapport inkl idag)
            $ibcPerDagPlc = [];
            try {
                $plcDagStmt = $this->pdo->prepare("
                    SELECT DATE(datum) AS dag, MAX(ibc_count) AS ibc
                    FROM tvattlinje_ibc
                    WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                    GROUP BY DATE(datum)
                ");
                $plcDagStmt->execute(['s' => $start, 'e' => $end]);
                foreach ($plcDagStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $ibcPerDagPlc[$row['dag']] = (int)$row['ibc'];
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics ibc_per_dag_plc: ' . $e->getMessage()); }

            $out = json_encode([
                'success' => true,
                'data' => [
                    'cycles'             => $cycles,
                    'onoff_events'       => $onoff_events,
                    'rast_events'        => $rast_events,
                    'driftstopp_events'  => $driftstopp_events,
                    'summary' => [
                        'total_cycles'              => $total_cycles,
                        'total_ibc_skiftrapport'    => $total_ibc_skiftrapport,
                        'received_webhooks'         => $received_webhooks > 0 ? $received_webhooks : count($cycles),
                        'missed_webhooks'           => max(0, $total_cycles - ($received_webhooks > 0 ? $received_webhooks : count($cycles))),
                        'avg_production_percent'    => round($avg_production_percent, 1),
                        'avg_cycle_time'            => round($avg_cycle_time, 2),
                        'target_cycle_time'         => $target_cycle_time,
                        'total_runtime_hours'       => round($total_runtime_hours, 2),
                        'net_runtime_minutes'       => round($netRuntimeMinutes, 1),
                        'total_rast_minutes'        => round($totalRastMinutes, 1),
                        'total_driftstopp_minutes'  => round($totalDriftstoppMinutes, 1),
                        'days_with_production'      => $days_with_production,
                        'runtime_source'            => $runtimeSource,
                        'ibc_per_dag_skiftrapport'  => $ibcPerDagSr,
                        'ibc_per_dag_plc'           => $ibcPerDagPlc,
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
            @file_put_contents($cacheFile, $out, LOCK_EX);
            echo $out;
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getStatistics: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta statistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skiftrapport-statistik — hämtar PLC-genererade skiftrapporter
    // =========================================================

    private function getSkiftrapportStatistik() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
            $end   = $_GET['end']   ?? date('Y-m-d');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d', strtotime('-30 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');

            $rows = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT sr.*
                    FROM tvattlinje_skiftrapport sr
                    WHERE sr.datum >= :start AND sr.datum <= :end
                    ORDER BY sr.datum DESC, sr.id DESC
                ");
                $stmt->execute(['start' => $start, 'end' => $end]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getSkiftrapportStatistik rows: ' . $e->getMessage());
            }

            // Bygg PLC-spann per dag för drifttid-sanering (cap mot faktisk körtid)
            $plcSpanPerDag = [];
            try {
                $spanStmt = $this->pdo->prepare("
                    SELECT DATE(datum) AS dag,
                           ROUND(TIMESTAMPDIFF(SECOND, MIN(datum), MAX(datum)) / 60.0) AS span_min
                    FROM tvattlinje_ibc
                    WHERE datum >= :s AND datum <= DATE_ADD(:e, INTERVAL 1 DAY)
                    GROUP BY DATE(datum)
                    HAVING span_min > 0
                ");
                $spanStmt->execute(['s' => $start, 'e' => $end]);
                foreach ($spanStmt->fetchAll(\PDO::FETCH_ASSOC) as $span) {
                    $plcSpanPerDag[$span['dag']] = (int)$span['span_min'];
                }
            } catch (\Throwable $e) { /* ignorera om tvattlinje_ibc saknas */ }

            // Beräkna summerade KPI:er + bästa dag + godkänd % per rad
            $totalOk       = 0;
            $totalEjOk     = 0;
            $totalOmtvaatt = 0;
            $bastaDag      = null;
            $bastaDagIbc   = 0;

            // Aggregera rå drifttid per dag — för att kunna cappa dygnssumman mot PLC-spann
            $driftPerDag   = [];

            // Daglig aggregering för bästa dag
            $dagMap = [];

            // KONTINUITET: deduplicera per (datum, skiftraknare) — multipla poster för
            // samma skift är snapshots; ta senaste (MAX id). Summera aldrig snapshots
            // (annars blev bästa-dag/total dubbelräknad, t.ex. 159/168/135 istället för ~138).
            $latestPerShift = [];
            foreach ($rows as $r) {
                $dagKey = substr($r['datum'] ?? '', 0, 10);
                if (!$dagKey) continue;
                $shiftKey = $dagKey . '#' . ($r['skiftraknare'] ?? '0');
                if (!isset($latestPerShift[$shiftKey]) || (int)$r['id'] > (int)$latestPerShift[$shiftKey]['id']) {
                    $latestPerShift[$shiftKey] = $r;
                }
            }

            // Godkänd % per rad för listvisning (alla rader visas, inte bara deduplicerade)
            foreach ($rows as &$r) {
                $ok  = (int)($r['antal_ok'] ?? 0);
                $tot = (int)($r['totalt'] ?? ($ok + (int)($r['antal_ej_ok'] ?? 0) + (int)($r['omtvaatt'] ?? 0)));
                $r['godkand_pct'] = $tot > 0 ? round($ok / $tot * 100, 1) : null;
            }
            unset($r);

            // Summerade KPI:er + dagsaggregat — från deduplicerade skift
            foreach ($latestPerShift as $r) {
                $ok       = (int)($r['antal_ok']    ?? 0);
                $ejOk     = (int)($r['antal_ej_ok'] ?? 0);
                $omtv     = (int)($r['omtvaatt']    ?? 0);
                $tot      = (int)($r['totalt']      ?? ($ok + $ejOk + $omtv));

                $dag      = substr($r['datum'] ?? '', 0, 10);

                // Ackumulera rå drifttid per dag (cappa per dag EFTER loopen)
                $rawDrift = (float)($r['drifttid'] ?? 0);
                if ($rawDrift > 0 && $dag) {
                    $driftPerDag[$dag] = ($driftPerDag[$dag] ?? 0) + $rawDrift;
                }

                $totalOk       += $ok;
                $totalEjOk     += $ejOk;
                $totalOmtvaatt += $omtv;

                // Samla per dag för bästa dag (totalt inkl. omtvätt)
                if ($dag) {
                    $dagMap[$dag] = ($dagMap[$dag] ?? 0) + $tot;
                }
            }

            // Cappa dygnssumman mot PLC-spann och summera till total_drifttid
            $totalDrifttid = 0;
            foreach ($driftPerDag as $d => $sum) {
                $plcSpan = isset($plcSpanPerDag[$d]) ? max($plcSpanPerDag[$d], 1) : 600;
                $totalDrifttid += min($sum, min($plcSpan, 600));
            }

            // EN PLC-baserad källa: PLC (MAX ibc_count) vinner för ALLA dagar med PLC-data
            // (inkl idag); deduplicerad skiftrapport fyller bara PLC-lösa dagar. Samma
            // merge-logik som overview-fliken → hem/statistik/bästa-dag/plc-diag matchar.
            $plcPerDag = $this->plcIbcPerDag($start, $end);
            foreach ($plcPerDag as $d => $ibc) {
                $dagMap[$d] = $ibc;
            }

            // Kvalitets-KPI:er baseras på skiftrapportens OK/ej-OK (PLC saknar den uppdelningen)
            $srTotalIbc = $totalOk + $totalEjOk + $totalOmtvaatt;
            $avgGodkandPct = $srTotalIbc > 0 ? round($totalOk / $srTotalIbc * 100, 1) : null;
            // Viktat snitt cykeltid: total drifttid / totalt antal IBC (SR-baserad)
            $avgCycleTime  = $srTotalIbc > 0 ? round($totalDrifttid / $srTotalIbc, 2) : null;

            // TOTAL IBC = summa av PLC-baserad dagskarta (kontinuitet med staplar/hem)
            $totalIbc = array_sum($dagMap);

            // Bästa dag = dag med flest totala IBC
            if (!empty($dagMap)) {
                arsort($dagMap);
                $bastaDag    = array_key_first($dagMap);
                $bastaDagIbc = $dagMap[$bastaDag];
            }

            // Dagar med PLC-data men utan skiftrapport
            $skiftDagar   = array_unique(array_map(fn($r) => substr($r['datum'] ?? '', 0, 10), $rows));
            $plcOnlyDays  = array_values(array_filter(array_keys($plcSpanPerDag), fn($d) => !in_array($d, $skiftDagar)));
            sort($plcOnlyDays);

            echo json_encode([
                'success' => true,
                'data'    => $rows,
                'summary' => [
                    'total_ibc'       => $totalIbc,
                    'total_ok'        => $totalOk,
                    'total_ej_ok'     => $totalEjOk,
                    'total_omtvaatt'  => $totalOmtvaatt,
                    'total_drifttid'  => $totalDrifttid,
                    'skift_count'     => count($rows),
                    'avg_godkand_pct' => $avgGodkandPct,
                    'avg_cycle_time'  => $avgCycleTime,
                    'basta_dag'       => $bastaDag,
                    'basta_dag_ibc'   => $bastaDagIbc,
                    'plc_only_days'   => $plcOnlyDays,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getSkiftrapportStatistik: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftrapportstatistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skiftrapport daglig — returnerar KPI-sammanfattning för ett datum
    // Om tabellen saknas eller är tom → graceful empty-state
    // =========================================================

    private function getReport() {
        $datum = $_GET['datum'] ?? date('Y-m-d');
        // Validera datumformat
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta skiftrapporter för datumet via tvattlinje_skiftrapport
            $rows = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ls.*, u.username as user_name
                    FROM tvattlinje_skiftrapport ls
                    LEFT JOIN users u ON ls.user_id = u.id
                    WHERE ls.datum = :datum
                    ORDER BY ls.id ASC
                ");
                $stmt->execute(['datum' => $datum]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getReport: ' . $e->getMessage());
                // Tabell finns inte eller fel — returnera tom data
            }

            // Föregående dags data för delta-beräkning
            $prevDatum = date('Y-m-d', strtotime($datum . ' -1 day'));
            $prevRows  = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ls.*
                    FROM tvattlinje_skiftrapport ls
                    WHERE ls.datum = :datum
                    ORDER BY ls.id ASC
                ");
                $stmt->execute(['datum' => $prevDatum]);
                $prevRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getReport prevRows: ' . $e->getMessage());
            }

            // Beräkna KPI för aktuellt datum.
            // Dedupe: tvattlinje_skiftrapport kan ha flera snapshot-poster för samma
            // (datum, skiftraknare). Behåll SENASTE (högsta id) per skift före summering,
            // annars dubbelräknas antal_ok/ej_ok/omtvaatt/rasttime på multi-snapshot-dagar.
            $latestPerShift = [];
            foreach ($rows as $r) {
                $key = (string)($r['skiftraknare'] ?? '0');
                if (!isset($latestPerShift[$key]) || (int)$r['id'] > (int)$latestPerShift[$key]['id']) {
                    $latestPerShift[$key] = $r;
                }
            }

            $totalOk   = 0;
            $totalEjOk = 0;
            $totalOmtv = 0;
            $totalRast = 0;
            foreach (array_values($latestPerShift) as $r) {
                $totalOk   += (int)($r['antal_ok']    ?? 0);
                $totalEjOk += (int)($r['antal_ej_ok'] ?? 0);
                $totalOmtv += (int)($r['omtvaatt']    ?? 0);
                $totalRast += (int)($r['rasttime']    ?? 0);
            }
            $totalIbc  = $totalOk + $totalEjOk + $totalOmtv;
            $kvalitetPct = $totalIbc > 0 ? round(($totalOk / $totalIbc) * 100, 1) : 0;

            // Föregående dag — samma dedupe (senaste snapshot per skift), annars
            // dubbelfel i delta_ibc på multi-snapshot-dagar.
            $prevLatestPerShift = [];
            foreach ($prevRows as $r) {
                $key = (string)($r['skiftraknare'] ?? '0');
                if (!isset($prevLatestPerShift[$key]) || (int)$r['id'] > (int)$prevLatestPerShift[$key]['id']) {
                    $prevLatestPerShift[$key] = $r;
                }
            }

            $prevOk   = 0;
            $prevEjOk = 0;
            $prevOmtv = 0;
            foreach (array_values($prevLatestPerShift) as $r) {
                $prevOk   += (int)($r['antal_ok']    ?? 0);
                $prevEjOk += (int)($r['antal_ej_ok'] ?? 0);
                $prevOmtv += (int)($r['omtvaatt']    ?? 0);
            }
            $prevIbc   = $prevOk + $prevEjOk + $prevOmtv;
            $deltaIbc  = $totalIbc - $prevIbc;

            // Hämta runtime från tvattlinje_ibc om det finns
            $runtimeMinutes = 0;
            $rastMinutes    = $totalRast;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT MIN(datum) as first_ts, MAX(datum) as last_ts, COUNT(*) as cnt
                    FROM tvattlinje_ibc
                    WHERE datum >= :datum AND datum < DATE_ADD(:datumb, INTERVAL 1 DAY)
                ");
                $stmt->execute(['datum' => $datum, 'datumb' => $datum]);
                $ibcRange = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($ibcRange && $ibcRange['cnt'] > 0 && $ibcRange['first_ts']) {
                    $first = new \DateTime($ibcRange['first_ts']);
                    $last  = new \DateTime($ibcRange['last_ts']);
                    $diff  = $first->diff($last);
                    $runtimeMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
                    if ($runtimeMinutes < 1 && $ibcRange['cnt'] > 0) $runtimeMinutes = 5;
                    $runtimeMinutes = min($runtimeMinutes, 600);
                }
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getReport runtime: ' . $e->getMessage());
            }

            // Snitt IBC/h
            $ibcPerHour = ($runtimeMinutes > 0 && $totalIbc > 0)
                ? round(($totalIbc / $runtimeMinutes) * 60, 1)
                : 0;

            $isEmpty = (count($rows) === 0 && $totalIbc === 0);

            echo json_encode([
                'success'  => true,
                'empty'    => $isEmpty,
                'message'  => $isEmpty ? 'Linjen ej i drift' : null,
                'datum'    => $datum,
                'data' => [
                    'total_ibc'       => $totalIbc,
                    'total_ok'        => $totalOk,
                    'total_ej_ok'     => $totalEjOk,
                    'total_omtv'      => $totalOmtv,
                    'kvalitet_pct'    => $kvalitetPct,
                    'runtime_minutes' => $runtimeMinutes,
                    'rast_minutes'    => $rastMinutes,
                    'ibc_per_hour'    => $ibcPerHour,
                    'delta_ibc'       => $deltaIbc,
                    'prev_ibc'        => $prevIbc,
                    'skift_count'     => count($rows),
                    'skift_data'      => $rows,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getReport: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'empty'   => true,
                'message' => 'Linjen ej i drift',
                'datum'   => $datum,
                'data'    => [
                    'total_ibc' => 0, 'total_ok' => 0, 'total_ej_ok' => 0,
                    'kvalitet_pct' => 0, 'runtime_minutes' => 0,
                    'rast_minutes' => 0, 'ibc_per_hour' => 0,
                    'delta_ibc' => 0, 'prev_ibc' => 0,
                    'skift_count' => 0, 'skift_data' => [],
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // PLC Diagnostik — rådata och signalkvalitet för felsökning
    // =========================================================

    // GET ?action=tvattlinje&run=plc-diagnostik[&date=YYYY-MM-DD][&limit=200]
    // Full-replace approach: always returns all events for the date, no since_id pagination.
    private function getPlcDiagnostikStream(): void {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
            $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 500) : 200;
            // Range-gräns istället för DATE(datum)= → använder index, undviker fullscan
            $dateNext = date('Y-m-d', strtotime($date . ' +1 day'));

            // Filcache 8s TTL per (datum, limit) — TTL >= pollintervall (5s) så varje poll
            // träffar färsk cache och den tunga fullscan-frågan körs som mest ~1 gång/8s
            // oavsett antal tittare. (TTL < poll → cache alltid utgången → FPM-kömättnad = 503.)
            $cacheDir = dirname(__DIR__) . '/cache';
            if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
            $cacheFile = $cacheDir . '/tvattlinje_plcdiag_' . $date . '_' . $limit . '.json';
            $freshTtl  = 8;
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $freshTtl) {
                $cached = file_get_contents($cacheFile);
                if ($cached !== false) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo $cached;
                    return;
                }
            }

            // Stampede-skydd: vid cache-miss regenererar bara EN request (flock NB).
            // Övriga serverar stale cache i stället för att alla samtidigt kör fullscan
            // mot DB:n och förstärker FPM-kömättnaden. Lås släpps vid write/return/exception.
            $lock = @fopen($cacheFile . '.lock', 'c');
            $haveLock = $lock && flock($lock, LOCK_EX | LOCK_NB);
            if (!$haveLock && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 60) {
                $cached = @file_get_contents($cacheFile);
                if ($cached !== false && $cached !== '') {
                    if ($lock) { fclose($lock); }
                    header('Content-Type: application/json; charset=utf-8');
                    echo $cached;
                    return;
                }
            }
            // Dubbelkoll under låset — någon kan ha regenererat precis före oss.
            if ($haveLock && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $freshTtl) {
                $cached = @file_get_contents($cacheFile);
                if ($cached !== false && $cached !== '') {
                    @flock($lock, LOCK_UN); @fclose($lock);
                    header('Content-Type: application/json; charset=utf-8');
                    echo $cached;
                    return;
                }
            }

            $events = [];

            // tvattlinje_onoff
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM tvattlinje_onoff WHERE datum >= :date AND datum < :dateNext ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date, ':dateNext' => $dateNext]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'onoff';
                    $row['event_type'] = intval($row['running'] ?? 0) === 1 ? 'ON' : 'OFF';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream onoff: ' . $e->getMessage()); }

            // tvattlinje_ibc
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM tvattlinje_ibc WHERE datum >= :date AND datum < :dateNext ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date, ':dateNext' => $dateNext]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'ibc';
                    $row['event_type'] = 'IBC';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream ibc: ' . $e->getMessage()); }

            // tvattlinje_rast
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, datum, rast_status FROM tvattlinje_rast WHERE datum >= :date AND datum < :dateNext ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date, ':dateNext' => $dateNext]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'rast';
                    $row['event_type'] = intval($row['rast_status'] ?? 0) === 1 ? 'RAST_START' : 'RAST_END';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream rast: ' . $e->getMessage()); }

            // tvattlinje_driftstopp — bakåtkompatibel: status VARCHAR eller driftstopp_status TINYINT
            try {
                $dsRows = [];
                try {
                    $s = $this->pdo->prepare("SELECT id, datum, status FROM tvattlinje_driftstopp WHERE datum >= :date AND datum < :dateNext ORDER BY datum DESC LIMIT " . $limit);
                    $s->execute([':date' => $date, ':dateNext' => $dateNext]);
                    $dsRows = $s->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    $s = $this->pdo->prepare("SELECT id, datum, driftstopp_status FROM tvattlinje_driftstopp WHERE datum >= :date AND datum < :dateNext ORDER BY datum DESC LIMIT " . $limit);
                    $s->execute([':date' => $date, ':dateNext' => $dateNext]);
                    foreach ($s->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                        $r['status'] = (int)$r['driftstopp_status'] === 1 ? 'start' : 'slut';
                        $dsRows[] = $r;
                    }
                }
                foreach ($dsRows as $row) {
                    $row['source'] = 'driftstopp';
                    $row['driftstopp_status'] = ($row['status'] ?? '') === 'start' ? 1 : 0;
                    $row['event_type'] = ($row['status'] ?? '') === 'start' ? 'DRIFTSTOPP_START' : 'DRIFTSTOPP_SLUT';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream driftstopp: ' . $e->getMessage()); }

            // tvattlinje_skiftrapport
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT *, created_at AS datum FROM tvattlinje_skiftrapport WHERE created_at >= :date AND created_at < :dateNext ORDER BY created_at DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date, ':dateNext' => $dateNext]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'skiftrapport';
                    $row['event_type'] = 'SKIFTRAPPORT';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream skiftrapport: ' . $e->getMessage()); }

            // tvattlinje_plc_raw — append-only raw register log
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM tvattlinje_plc_raw WHERE datum >= :date AND datum < :dateNext ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date, ':dateNext' => $dateNext]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'plc_raw';
                    // Parse registers JSON to top-level keys for convenience
                    if (!empty($row['registers'])) {
                        $row['registers_parsed'] = json_decode($row['registers'], true) ?: [];
                    }
                    $events[] = $row;
                }
            } catch (\Throwable $e) { /* table may not exist yet */ }

            usort($events, function ($a, $b) {
                $cmp = strcmp($b['datum'], $a['datum']);
                return $cmp !== 0 ? $cmp : intval($b['id']) - intval($a['id']);
            });
            $events = array_slice($events, 0, $limit);

            $maxId = 0;
            foreach ($events as $e) {
                $eid = intval($e['id'] ?? 0);
                if ($eid > $maxId) $maxId = $eid;
            }

            $latestOnoff = null;
            try {
                $latestOnoff = $this->pdo->query("SELECT running, datum FROM tvattlinje_onoff ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {}

            $ibcToday = 0;
            try {
                $ibcToday = (int)$this->pdo->query("SELECT COALESCE(MAX(ibc_count), 0) FROM tvattlinje_ibc WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY")->fetchColumn();
            } catch (\Throwable $e) {}

            // Senaste event från valfri källa (onoff, ibc, rast, driftstopp) för vald dag
            $latestEventDatum = null;
            try {
                $latestEventRow = $this->pdo->prepare("
                    SELECT MAX(datum) AS latest FROM (
                        SELECT MAX(datum) AS datum FROM tvattlinje_onoff WHERE datum >= :date1 AND datum < :next1
                        UNION ALL
                        SELECT MAX(datum) FROM tvattlinje_ibc WHERE datum >= :date2 AND datum < :next2
                        UNION ALL
                        SELECT MAX(datum) FROM tvattlinje_rast WHERE datum >= :date3 AND datum < :next3
                        UNION ALL
                        SELECT MAX(datum) FROM tvattlinje_driftstopp WHERE datum >= :date4 AND datum < :next4
                    ) sub
                ");
                $latestEventRow->execute([
                    ':date1' => $date, ':next1' => $dateNext,
                    ':date2' => $date, ':next2' => $dateNext,
                    ':date3' => $date, ':next3' => $dateNext,
                    ':date4' => $date, ':next4' => $dateNext,
                ]);
                $latestEventDatum = $latestEventRow->fetchColumn() ?: null;
            } catch (\Throwable $e) {}

            $out = json_encode([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'max_id' => $maxId,
                    'stats' => [
                        'running' => $latestOnoff ? intval($latestOnoff['running'] ?? 0) === 1 : false,
                        'skiftraknare' => 0,
                        'last_event' => $latestEventDatum ?? ($latestOnoff['datum'] ?? null),
                        'ibc_today' => $ibcToday,
                    ],
                    'date' => $date,
                    'event_count' => count($events),
                ]
            ], JSON_UNESCAPED_UNICODE);
            @file_put_contents($cacheFile, $out, LOCK_EX);
            if (isset($lock) && $lock) { @flock($lock, LOCK_UN); @fclose($lock); }
            header('Content-Type: application/json; charset=utf-8');
            echo $out;
        } catch (\Throwable $e) {
            if (isset($lock) && $lock) { @flock($lock, LOCK_UN); @fclose($lock); }
            error_log('TvattlinjeController::getPlcDiagnostikStream: ' . $e->getMessage());
            // Returnera 200 med tom data — förhindrar att klienten tolkar det som ett permanent fel
            // och triggar retries som kan orsaka PHP-FPM-köbildning (upplevd 503).
            echo json_encode([
                'success' => true,
                'data' => [
                    'events'       => [],
                    'max_id'       => 0,
                    'stats'        => ['running' => false, 'skiftraknare' => 0, 'last_event' => null, 'ibc_today' => 0],
                    'date'         => date('Y-m-d'),
                    'event_count'  => 0,
                ],
                '_error' => 'Tillfälligt fel — försök igen',
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getPlcDiagnostics() {
        $start = $_GET['start'] ?? date('Y-m-d');
        $end   = $_GET['end']   ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');

        $tz  = new \DateTimeZone('Europe/Stockholm');
        $now = new \DateTime('now', $tz);

        // Senaste globala PLC-signal
        $plcLastSeen   = null;
        $plcAgeMinutes = null;
        try {
            $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM tvattlinje_ibc")->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['last_ping']) {
                $plcLastSeen = $row['last_ping'];
                $lastDt = new \DateTime($plcLastSeen, $tz);
                // Signed delta: positiv = data är gammalt, negativ = klockor ur synk (framtida ts)
                $signedSec = (int)$now->getTimestamp() - $lastDt->getTimestamp();
                // Om -60 <= signedSec <= 0: data är i synk, sätt age till 0
                $plcAgeMinutes = ($signedSec <= 0 && $signedSec >= -60) ? 0 : round($signedSec / 60, 1);
            }
        } catch (\Throwable $e) { error_log('TvattlinjeController::getPlcDiagnostics plcLastSeen: ' . $e->getMessage()); }

        // Rådata IBC-poster för valt period
        $latestIbc  = [];
        $periodTotal = 0;
        $truncated   = false;
        try {
            // Räkna totalt antal poster i perioden (utan LIMIT) för truncated-flagga
            $cntStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM tvattlinje_ibc
                WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
            ");
            $cntStmt->execute(['start' => $start, 'end' => $end]);
            $periodTotal = (int)$cntStmt->fetchColumn();
            $truncated   = $periodTotal > 500;

            $stmt = $this->pdo->prepare("
                SELECT
                    datum, ibc_count, s_count, ibc_ok, ibc_ej_ok, omtvaatt,
                    runtime_plc, rasttime, lopnummer, skiftraknare, op1, op2, op3,
                    ROUND(TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (ORDER BY datum),
                        datum) / 60.0, 2) as cycle_time
                FROM tvattlinje_ibc
                WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                ORDER BY datum DESC
                LIMIT 500
            ");
            $stmt->execute(['start' => $start, 'end' => $end]);
            $latestIbc = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { error_log('TvattlinjeController::getPlcDiagnostics latestIbc: ' . $e->getMessage()); }

        // On/off-händelser för valt period
        $latestOnoff = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running, runtime_today
                FROM tvattlinje_onoff
                WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                ORDER BY datum DESC
                LIMIT 200
            ");
            $stmt->execute(['start' => $start, 'end' => $end]);
            $latestOnoff = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { error_log('TvattlinjeController::getPlcDiagnostics latestOnoff: ' . $e->getMessage()); }

        // Datakvalitet
        $totalCount    = count($latestIbc);
        $nullCycleCount = 0;
        $intervals     = [];
        $prevTs        = null;
        $nowTs         = time();
        foreach (array_reverse($latestIbc) as $r) {
            $ts = strtotime($r['datum']);
            // Hoppa rader med framtida timestamp (mer än 60s in i framtiden)
            if ($ts > $nowTs + 60) { $prevTs = $ts; continue; }
            if ($prevTs !== null) {
                $delta = round(max(0, ($ts - $prevTs)) / 60.0, 1);
                $intervals[] = $delta;
            }
            $prevTs = $ts;
            $ct = $r['cycle_time'];
            if ($ct === null || $ct <= 0 || $ct > 30) $nullCycleCount++;
        }
        $avgInterval  = count($intervals) > 0 ? round(array_sum($intervals) / count($intervals), 1) : null;
        $maxInterval  = count($intervals) > 0 ? round(max($intervals), 1) : null;
        $minInterval  = count($intervals) > 0 ? round(min($intervals), 1) : null;
        $gapsGt15     = count(array_filter($intervals, fn($i) => $i > 15));

        // Skiftrapporter för valt period
        $skiftrapporter = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, antal_ok, antal_ej_ok, omtvaatt, totalt, kommentar, inlagd, created_at, updated_at
                FROM tvattlinje_skiftrapport
                WHERE datum >= :start AND datum <= :end
                ORDER BY datum DESC
            ");
            $stmt->execute(['start' => $start, 'end' => $end]);
            $skiftrapporter = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { error_log('TvattlinjeController::getPlcDiagnostics skiftrapporter: ' . $e->getMessage()); }

        echo json_encode([
            'success' => true,
            'data' => [
                'system_status' => [
                    'plc_last_seen'   => $plcLastSeen,
                    'plc_age_minutes' => $plcAgeMinutes,
                    'is_running'      => $plcAgeMinutes !== null && $plcAgeMinutes < 15,
                    'server_time'     => $now->format('Y-m-d H:i:s'),
                ],
                'data_quality' => [
                    'total_records'    => $totalCount,
                    'period_total'     => $periodTotal,
                    'truncated'        => $truncated,
                    'null_cycle_count' => $nullCycleCount,
                    'valid_pct'        => $totalCount > 0 ? round(($totalCount - $nullCycleCount) / $totalCount * 100, 1) : 0,
                    'avg_interval_min' => $avgInterval,
                    'min_interval_min' => $minInterval,
                    'max_interval_min' => $maxInterval,
                    'gaps_gt_15min'    => $gapsGt15,
                ],
                'latest_ibc'        => $latestIbc,
                'latest_onoff'      => $latestOnoff,
                'skiftrapporter'    => $skiftrapporter,
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================
    // OEE-trend — daglig statistik över N dagar (standard 30)
    // Returnerar { empty: true } om ingen data finns
    // =========================================================

    private function getOeeTrend() {
        $dagar = max(7, min(365, intval($_GET['dagar'] ?? 30)));

        try {
            $rows = [];

            // Källdata: tvattlinje_skiftrapport — PLC-råvärden (D4004/D4005/D4007) inskickade per skift.
            // Aggregera per dag. Aldrig kumulativ diff → inga negativa värden vid PLC-nollställning.
            try {
                // A: Deduplicera per (dag, skiftraknare) — senaste posten (MAX id) — annars
                // dubbelräknar snapshot-poster IBC/drifttid. C: hämta även MAX(runtime_plc) per
                // dag från tvattlinje_ibc som körtidsfallback när skiftrapportens drifttid saknas.
                $stmt = $this->pdo->prepare("
                    SELECT
                        d.dag,
                        d.total_ibc,
                        d.total_ok,
                        d.total_ej_ok,
                        d.skift_count,
                        d.total_runtime_min,
                        COALESCE(plc.plc_runtime_min, 0) AS plc_runtime_min
                    FROM (
                        SELECT
                            DATE(sr.datum)                               AS dag,
                            SUM(GREATEST(0, COALESCE(sr.totalt,     0))) AS total_ibc,
                            SUM(GREATEST(0, COALESCE(sr.antal_ok,   0))) AS total_ok,
                            SUM(GREATEST(0, COALESCE(sr.antal_ej_ok, 0))) AS total_ej_ok,
                            COUNT(*)                                     AS skift_count,
                            SUM(GREATEST(0, COALESCE(sr.drifttid,   0))) AS total_runtime_min
                        FROM tvattlinje_skiftrapport sr
                        INNER JOIN (
                            SELECT MAX(id) AS max_id
                            FROM tvattlinje_skiftrapport
                            WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                            GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                        ) latest ON sr.id = latest.max_id
                        GROUP BY DATE(sr.datum)
                    ) d
                    LEFT JOIN (
                        SELECT DATE(datum) AS dag, MAX(runtime_plc) AS plc_runtime_min
                        FROM tvattlinje_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :dagar2 DAY)
                          AND runtime_plc IS NOT NULL AND runtime_plc > 0
                        GROUP BY DATE(datum)
                    ) plc ON plc.dag = d.dag
                    ORDER BY d.dag ASC
                ");
                $stmt->execute(['dagar' => $dagar, 'dagar2' => $dagar]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('TvattlinjeController::getOeeTrend: ' . $e->getMessage());
            }

            if (empty($rows)) {
                echo json_encode([
                    'success' => true,
                    'empty'   => true,
                    'message' => 'Linjen ej i drift',
                    'data'    => [],
                    'summary' => [
                        'total_ibc'      => 0,
                        'snitt_per_dag'  => 0,
                        'snitt_oee_pct'  => 0,
                        'basta_dag'      => null,
                        'basta_ibc'      => 0,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $dagData     = [];
            $totalIbcSum = 0;
            $totalOkSum  = 0;
            $bestaDag    = null;
            $bestaIbc    = 0;

            // D: Veckodagsmål (mål IBC/dag) per WEEKDAY (0=mån … 6=sön). Källa: tvattlinje_weekday_goals
            // (samma tabell som live/snapshot). Fallback om tabell/rad saknas: vardag 140, helg 60.
            $goalsMap = [];
            try {
                foreach ($this->pdo->query("SELECT weekday, mal FROM tvattlinje_weekday_goals")->fetchAll(\PDO::FETCH_ASSOC) as $g) {
                    $goalsMap[(int)$g['weekday']] = (int)$g['mal'];
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::getOeeTrend goals: ' . $e->getMessage()); }

            foreach ($rows as $r) {
                // A: ingen skift_count-filtrering längre — dubbelposter deduplicerade i SQL.
                $tot = max(0, (int)$r['total_ibc']);
                $ok  = max(0, (int)$r['total_ok']);

                // B: Kvalitet = antal_ok / totalt. totalt=0 → 0 (ingen godkänd produktion), och
                // allt kasserat (ok=0, tot>0) → 0 (INTE 100 som tidigare maskerade full kassation).
                $qual_pct = ($tot > 0) ? round(($ok / $tot) * 100, 1) : 0.0;

                // C: Körtid = skiftrapportens drifttid (D4007, exkl rast); fallback MAX(runtime_plc)
                // från tvattlinje_ibc. Ingen körtidskälla alls → 0% tillgänglighet (ej 100).
                $runtimeMin = max(0, isset($r['total_runtime_min']) ? (float)$r['total_runtime_min'] : 0);
                if ($runtimeMin <= 0) {
                    $runtimeMin = max(0, isset($r['plc_runtime_min']) ? (float)$r['plc_runtime_min'] : 0);
                }
                $rastMin       = 0; // rast ingår inte i D4007
                $netRuntimeMin = max(0, $runtimeMin - $rastMin);

                $dayN      = (int)date('N', strtotime($r['dag'])); // 1=mån … 7=sön
                $weekday   = $dayN - 1;                            // 0=mån … 6=sön (matchar weekday_goals/WEEKDAY())
                $isWeekend = ($dayN >= 6);

                // D: Tillgänglighet. Vardag = verkligt skiftschema (mån-tors 495 min, fre 480 min).
                // Helg = ingen tillgänglighetsstraff (oschemalagt, körs bara ibland): körd alls → 100%,
                // annars 0%. På helg mäts alltså bara prestanda och kvalitet.
                if ($isWeekend) {
                    $avail_pct = $runtimeMin > 0 ? 100.0 : 0.0;
                } else {
                    $plannedMin = ($dayN === 5) ? 480.0 : 495.0;
                    $avail_pct  = $runtimeMin > 0
                        ? min(100.0, round(($netRuntimeMin / $plannedMin) * 100, 1))
                        : 0.0;
                }

                // D: Prestanda mot veckodagsmålet (mål IBC/dag ur weekday_goals), ej hårdkodat 20 IBC/h.
                $idealPerDag = $goalsMap[$weekday] ?? ($isWeekend ? 60 : 140);
                $perf_pct = ($idealPerDag > 0)
                    ? min(100.0, round(($tot / $idealPerDag) * 100, 1))
                    : 0.0;

                // OEE = Tillgänglighet × Prestanda × Kvalitet (0-100 skala → dela med 10000)
                $oee_pct = round(($avail_pct / 100.0) * ($perf_pct / 100.0) * ($qual_pct / 100.0) * 100.0, 1);

                $totalIbcSum += $tot;
                $totalOkSum  += $ok;
                // A: Bästa dag = flest inskickade IBCer (dedupliceras i SQL); exkludera pågående dag.
                if ($tot > $bestaIbc && $r['dag'] < date('Y-m-d')) {
                    $bestaIbc = $tot;
                    $bestaDag = $r['dag'];
                }
                $dagData[] = [
                    'dag'         => $r['dag'],
                    'total_ibc'   => $tot,
                    'total_ok'    => $ok,
                    'total_ej_ok' => (int)$r['total_ej_ok'],
                    'avail_pct'   => $avail_pct,
                    'perf_pct'    => $perf_pct,
                    'qual_pct'    => $qual_pct,
                    'oee_pct'     => $oee_pct,
                    'skift_count' => (int)$r['skift_count'],
                ];
            }

            $antalDagar  = count($dagData);
            $snittPerDag = $antalDagar > 0 ? round($totalIbcSum / $antalDagar, 1) : 0;
            $snittOee = 0;
            $snittKvalitet = 0;
            if ($antalDagar > 0) {
                $snittOee      = round(array_sum(array_column($dagData, 'oee_pct')) / $antalDagar, 1);
                // Viktat snitt: SUM(ok) / SUM(totalt) — konsekvent med skiftrapport-sidan och statistik-fliken
                $snittKvalitet = $totalIbcSum > 0 ? round($totalOkSum / $totalIbcSum * 100, 1) : 0;
            }

            echo json_encode([
                'success' => true,
                'empty'   => false,
                'data'    => $dagData,
                'summary' => [
                    'total_ibc'      => $totalIbcSum,
                    'snitt_per_dag'  => $snittPerDag,
                    'snitt_oee_pct'  => $snittOee,
                    'snitt_kvalitet' => $snittKvalitet,
                    'basta_dag'      => $bestaDag,
                    'basta_ibc'      => $bestaIbc,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getOeeTrend: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'empty'   => true,
                'message' => 'Linjen ej i drift',
                'data'    => [],
                'summary' => [
                    'total_ibc' => 0, 'snitt_per_dag' => 0,
                    'snitt_oee_pct' => 0, 'basta_dag' => null, 'basta_ibc' => 0,
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Operator Scores — IBC/h ranking med kategorier
    // GET ?action=tvattlinje&run=operator-scores&from=YYYY-MM-DD&to=YYYY-MM-DD
    // =========================================================

    private function getOperatorScores(): void {
        try {
            $to   = $_GET['to']   ?? date('Y-m-d');
            $from = $_GET['from'] ?? date('Y-m-d', strtotime($to . ' -90 days'));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // tvattlinje_skiftrapport: totalt är direkt antal (inte kumulativt).
            // E: Deduplicera per (dag, skiftraknare) — senaste posten (MAX id) — annars
            // dubbelräknar snapshot-poster operatörernas IBC, minuter och antal_skift i rankingen.
            $stmtShifts = $this->pdo->prepare("
                SELECT sr.totalt, sr.drifttid, sr.op1, sr.op2, sr.op3,
                       YEARWEEK(sr.datum, 1) AS yw
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM tvattlinje_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                WHERE sr.drifttid > 0 AND sr.totalt > 0
                ORDER BY sr.datum ASC
            ");
            $stmtShifts->execute([':from' => $from, ':to' => $to]);
            $allShifts = $stmtShifts->fetchAll(\PDO::FETCH_ASSOC);

            $opTotals      = [];
            $opPosTotals   = [];
            $opWeekly      = [];
            $opBestWorst   = [];
            $teamPosTotals = ['op1'=>['ibc'=>0.0,'min'=>0.0], 'op2'=>['ibc'=>0.0,'min'=>0.0], 'op3'=>['ibc'=>0.0,'min'=>0.0]];
            $teamTotalIbc  = 0.0;
            $teamTotalMin  = 0.0;

            foreach ($allShifts as $s) {
                $aktiva = 0;
                foreach (['op1','op2','op3'] as $p) if ((int)$s[$p] > 0) $aktiva++;
                if ($aktiva === 0) continue;

                $tot = (float)$s['totalt'];
                // Cap 600 min (~10h = max ett skift). En felregistrerad drifttid
                // (t.ex. 1440) sänker annars ibc_per_h/score för ALLA operatörer på skiftet.
                $min = min((float)$s['drifttid'], 600.0);
                if ($min <= 0) continue;

                $ibcPerOp  = $tot / $aktiva;
                $shiftIbcH = round($ibcPerOp / ($min / 60.0), 2);
                $yw        = $s['yw'];

                $teamTotalIbc += $tot;
                $teamTotalMin += $min;

                foreach (['op1','op2','op3'] as $pos) {
                    $num = (int)$s[$pos];
                    if ($num <= 0) continue;
                    if (!isset($opNames[$num])) $opNames[$num] = "Operatör $num";

                    $opTotals[$num]['ibc']   = ($opTotals[$num]['ibc']   ?? 0.0) + $ibcPerOp;
                    $opTotals[$num]['min']   = ($opTotals[$num]['min']   ?? 0.0) + $min;
                    $opTotals[$num]['count'] = ($opTotals[$num]['count'] ?? 0) + 1;

                    $opPosTotals[$num][$pos]['ibc']   = ($opPosTotals[$num][$pos]['ibc']   ?? 0.0) + $ibcPerOp;
                    $opPosTotals[$num][$pos]['min']   = ($opPosTotals[$num][$pos]['min']   ?? 0.0) + $min;
                    $opPosTotals[$num][$pos]['count'] = ($opPosTotals[$num][$pos]['count'] ?? 0) + 1;

                    $opWeekly[$num][$yw]['ibc'] = ($opWeekly[$num][$yw]['ibc'] ?? 0.0) + $ibcPerOp;
                    $opWeekly[$num][$yw]['min'] = ($opWeekly[$num][$yw]['min'] ?? 0.0) + $min;

                    $opBestWorst[$num][] = $shiftIbcH;

                    $teamPosTotals[$pos]['ibc'] += $ibcPerOp;
                    $teamPosTotals[$pos]['min'] += $min;
                }
            }

            $teamAvgPerPos = [];
            foreach ($teamPosTotals as $pos => $t) {
                $teamAvgPerPos[$pos] = $t['min'] > 0 ? $t['ibc'] / ($t['min'] / 60.0) : 0;
            }
            $teamTotal = $teamTotalMin > 0 ? $teamTotalIbc / ($teamTotalMin / 60.0) : 1;

            $results = [];
            foreach ($opNames as $num => $name) {
                if (!isset($opTotals[$num])) continue;
                $tot = $opTotals[$num];
                if ($tot['count'] < 3) continue;

                $ibc_per_h = round($tot['ibc'] / ($tot['min'] / 60.0), 1);

                $posStats = [];
                foreach (($opPosTotals[$num] ?? []) as $pos => $pt) {
                    $posIbcH = $pt['min'] > 0 ? round($pt['ibc'] / ($pt['min'] / 60.0), 1) : 0;
                    $tAvg    = $teamAvgPerPos[$pos] ?? 0;
                    $posStats[$pos] = [
                        'ibc_per_h'   => $posIbcH,
                        'team_avg'    => round($tAvg, 1),
                        'antal_skift' => $pt['count'],
                        'vs_avg_pct'  => $tAvg > 0 ? round(($posIbcH / $tAvg - 1) * 100) : 0,
                    ];
                }

                $wMin = 0.0; $wAvg = 0.0;
                foreach (array_keys($posStats) as $pos) {
                    $pm = $opPosTotals[$num][$pos]['min'] ?? 0;
                    $wAvg += ($teamAvgPerPos[$pos] ?? 0) * $pm;
                    $wMin += $pm;
                }
                $posWeightedTeamAvg = $wMin > 0 ? $wAvg / $wMin : $teamTotal;
                $vsAvgPct = $posWeightedTeamAvg > 0 ? round(($ibc_per_h / $posWeightedTeamAvg - 1) * 100) : 0;
                $score    = max(0, min(100, round(50 + ($ibc_per_h - $posWeightedTeamAvg) * 5)));
                $rating   = $vsAvgPct >= 15  ? 'Elite'
                          : ($vsAvgPct >= 0   ? 'Solid'
                          : ($vsAvgPct >= -15  ? 'Developing'
                          : 'Needs attention'));

                ksort($opWeekly[$num]);
                $weeklyVals = [];
                foreach ($opWeekly[$num] as $bucket) {
                    $weeklyVals[] = $bucket['min'] > 0 ? round($bucket['ibc'] / ($bucket['min'] / 60.0), 1) : 0;
                }
                $weeklyVals = array_slice($weeklyVals, -8);
                $shiftVals  = $opBestWorst[$num] ?? [0];

                $results[] = [
                    'number'       => $num,
                    'name'         => $name,
                    'ibc_per_h'    => $ibc_per_h,
                    'team_avg'     => round($teamTotal, 1),
                    'vs_avg_pct'   => $vsAvgPct,
                    'score'        => (int)$score,
                    'rating'       => $rating,
                    'antal_skift'  => $tot['count'],
                    'best_shift'   => round(max($shiftVals), 1),
                    'worst_shift'  => round(min($shiftVals), 1),
                    'per_position' => $posStats,
                    'trend_weeks'  => $weeklyVals,
                ];
            }

            usort($results, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

            echo json_encode([
                'success' => true,
                'data' => [
                    'from'             => $from,
                    'to'               => $to,
                    'operatorer'       => $results,
                    'team_avg_per_pos' => [
                        'op1' => round($teamAvgPerPos['op1'] ?? 0, 1),
                        'op2' => round($teamAvgPerPos['op2'] ?? 0, 1),
                        'op3' => round($teamAvgPerPos['op3'] ?? 0, 1),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('TvattlinjeController::getOperatorScores: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörspoäng'], JSON_UNESCAPED_UNICODE);
        }
    }

}