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
                $this->getRunningStatus();
            } elseif ($action === 'rast') {
                $this->getRastStatus();
            } elseif ($action === 'driftstopp') {
                $this->getDriftstoppStatus();
            } elseif ($action === 'statistics') {
                $this->getStatistics();
            } elseif ($action === 'report') {
                $this->getReport();
            } elseif ($action === 'oee-trend') {
                $this->getOeeTrend();
            } elseif ($action === 'skiftrapport-statistik') {
                $this->getSkiftrapportStatistik();
            } elseif ($action === 'plc-diagnostics') {
                $this->getPlcDiagnostics();
            } elseif ($action === 'plc-diagnostik') {
                $this->getPlcDiagnostikStream();
            } else {
                $this->getLiveStats();
            }
            return;
        }

        if ($method === 'POST') {
            if ($action === 'admin-settings') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->saveAdminSettings();
                return;
            }

            if ($action === 'settings') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->setSettings();
                return;
            }

            if ($action === 'weekday-goals') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->setWeekdayGoals();
                return;
            }

            if ($action === 'save-alert-thresholds') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
                    if ($wgRow && (int)$wgRow['mal'] > 0) {
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
                mal INT NOT NULL DEFAULT 80,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [[0,80],[1,80],[2,80],[3,80],[4,80],[5,60],[6,0]];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO tvattlinje_weekday_goals (weekday, mal) VALUES (?, ?)");
        foreach ($defaults as [$wd, $mal]) {
            $stmt->execute([$wd, $mal]);
        }
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

    private function getLiveStats() {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COALESCE(SUM(totalt), 0)
                FROM tvattlinje_skiftrapport
                WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
            ');
            $stmt->execute();
            $ibcToday = (int)$stmt->fetchColumn();
            
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
                    'ibcToday' => $ibcToday,
                    'ibcTarget' => $ibcTarget,
                    'productionPercentage' => $productionPercentage,
                    'utetemperatur' => $utetemperatur
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
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
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
        try {
            $stmt = $this->pdo->prepare('
                SELECT running, datum
                FROM tvattlinje_onoff
                ORDER BY datum DESC
                LIMIT 1
            ');
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $isRunning = $result && isset($result['running']) ? (bool)$result['running'] : false;
            $lastUpdate = $result && isset($result['datum']) ? $result['datum'] : null;

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

            echo json_encode([
                'success' => true,
                'data' => [
                    'running'            => $isRunning,
                    'lastUpdate'         => $lastUpdate,
                    'on_rast'            => $onRast,
                    'rast_minutes_today' => round($rastMinutesToday, 1),
                    'rast_count_today'   => $rastCountToday,
                ]
            ], JSON_UNESCAPED_UNICODE);
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

            // Primär: SUM(drifttid) från inskickade skiftrapporter (D4007 — netto körtid utan rast)
            try {
                $driftStmt = $this->pdo->prepare(
                    "SELECT COALESCE(SUM(drifttid), 0) FROM tvattlinje_skiftrapport WHERE datum >= :s AND datum <= :e"
                );
                $driftStmt->execute(['s' => $start, 'e' => $end]);
                $srDrifttid = (float)$driftStmt->fetchColumn();
                if ($srDrifttid > 0) {
                    $totalRuntimeMinutes = $srDrifttid;
                    $runtimeSource = 'skiftrapport';
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

            // Verkligt antal cykler via LAG-delta på MAX(ibc_count) per dag — fångar missade webhooks.
            // ibc_count är kumulativt (nollställs aldrig); LAG är korrekt men kräver en extra dag FÖRE
            // perioden som baseline så att första dagenss LAG inte är NULL → COALESCE(0) → jättedelta.
            $total_cycles_true = 0;
            try {
                $stmtTrue = $this->pdo->prepare('
                    SELECT COALESCE(SUM(GREATEST(0, ibc_delta)), 0)
                    FROM (
                        SELECT dag,
                               day_end - LAG(day_end) OVER (ORDER BY dag) AS ibc_delta
                        FROM (
                            SELECT DATE(datum) AS dag, MAX(ibc_count) AS day_end
                            FROM tvattlinje_ibc
                            WHERE datum >= DATE_SUB(:start, INTERVAL 1 DAY)
                              AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                            GROUP BY DATE(datum)
                        ) daily_max
                    ) deltas
                    WHERE dag >= :start2
                ');
                $stmtTrue->execute(['start' => $start, 'end' => $end, 'start2' => $start]);
                $total_cycles_true = (int)$stmtTrue->fetchColumn();
            } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics total_cycles_true: ' . $e->getMessage()); }

            $total_cycles = $total_cycles_true > 0 ? $total_cycles_true : count($cycles);
            // Natt-idle-fallback BORTTAGEN: span av första-sista cykel inkluderar natt-idle.
            // Om inga on/off-events finns visas drifttid som 0 (ingen maskinstatusdata).

            // IBC från inskickade skiftrapporter: SUM(totalt) = antal_ok + antal_ej_ok + omtvaatt.
            // Matchar "TOTAL IBC"-kolumnen i skiftrapportlistan.
            // Notera: total_cycles (puck-webhooks via ibc_count) kan skilja sig med ~5-10 IBCer
            // (testcykler/rengöring utan inskickad rapport, eller PLC-nollställning mellan dagar).
            $total_ibc_skiftrapport = 0;
            try {
                $srStmt = $this->pdo->prepare(
                    "SELECT COALESCE(SUM(totalt), 0) FROM tvattlinje_skiftrapport WHERE datum >= :s AND datum <= :e"
                );
                $srStmt->execute(['s' => $start, 'e' => $end]);
                $total_ibc_skiftrapport = (int)$srStmt->fetchColumn();
            } catch (\Throwable $e) { error_log('TvattlinjeController::getStatistics sr_ibc: ' . $e->getMessage()); }

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

            // netRuntimeMinutes: när källan är skiftrapport (D4007) är rast redan exkluderad av PLC:n —
            // dra inte av $totalRastMinutes en gång till. Driftstopp dras alltid av.
            if ($runtimeSource === 'skiftrapport') {
                $netRuntimeMinutes = max(0, $totalRuntimeMinutes - $totalDriftstoppMinutes);
            } else {
                $netRuntimeMinutes = max(0, $totalRuntimeMinutes - $totalRastMinutes - $totalDriftstoppMinutes);
            }
            $total_runtime_hours = $totalRuntimeMinutes / 60;

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

            echo json_encode([
                'success' => true,
                'data' => [
                    'cycles'             => $cycles,
                    'onoff_events'       => $onoff_events,
                    'rast_events'        => $rast_events,
                    'driftstopp_events'  => $driftstopp_events,
                    'summary' => [
                        'total_cycles'              => $total_cycles,
                        'total_ibc_skiftrapport'    => $total_ibc_skiftrapport,
                        'received_webhooks'         => count($cycles),
                        'missed_webhooks'           => max(0, $total_cycles - count($cycles)),
                        'avg_production_percent'    => round($avg_production_percent, 1),
                        'avg_cycle_time'            => round($avg_cycle_time, 2),
                        'target_cycle_time'         => $target_cycle_time,
                        'total_runtime_hours'       => round($total_runtime_hours, 2),
                        'net_runtime_minutes'       => round($netRuntimeMinutes, 1),
                        'total_rast_minutes'        => round($totalRastMinutes, 1),
                        'total_driftstopp_minutes'  => round($totalDriftstoppMinutes, 1),
                        'days_with_production'      => $days_with_production,
                        'runtime_source'            => $runtimeSource,
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
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

            // Beräkna summerade KPI:er + bästa dag + godkänd % per rad
            $totalOk       = 0;
            $totalEjOk     = 0;
            $totalOmtvaatt = 0;
            $totalDrifttid = 0;
            $bastaDag      = null;
            $bastaDagIbc   = 0;
            $cycleTimes    = [];

            // Daglig aggregering för bästa dag
            $dagMap = [];
            foreach ($rows as &$r) {
                $ok       = (int)($r['antal_ok']    ?? 0);
                $ejOk     = (int)($r['antal_ej_ok'] ?? 0);
                $omtv     = (int)($r['omtvaatt']    ?? 0);
                $tot      = (int)($r['totalt']      ?? ($ok + $ejOk + $omtv));

                $totalOk       += $ok;
                $totalEjOk     += $ejOk;
                $totalOmtvaatt += $omtv;
                $totalDrifttid += (int)($r['drifttid'] ?? 0);

                // Cykeltid per skift
                if ($tot > 0 && ($r['drifttid'] ?? 0) > 0) {
                    $cycleTimes[] = (float)$r['drifttid'] / $tot;
                }

                // Godkänd % per rad
                $r['godkand_pct'] = $tot > 0 ? round($ok / $tot * 100, 1) : null;

                // Samla per dag för bästa dag (totalt inkl. omtvätt)
                $dag = substr($r['datum'] ?? '', 0, 10);
                if ($dag) {
                    $dagMap[$dag] = ($dagMap[$dag] ?? 0) + $tot;
                }
            }
            unset($r);

            $totalIbc = $totalOk + $totalEjOk + $totalOmtvaatt;
            $avgGodkandPct = $totalIbc > 0 ? round($totalOk / $totalIbc * 100, 1) : null;
            $avgCycleTime  = count($cycleTimes) > 0 ? round(array_sum($cycleTimes) / count($cycleTimes), 2) : null;

            // Bästa dag = dag med flest totala IBC
            if (!empty($dagMap)) {
                arsort($dagMap);
                $bastaDag    = array_key_first($dagMap);
                $bastaDagIbc = $dagMap[$bastaDag];
            }

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

            // Beräkna KPI för aktuellt datum
            $totalOk   = 0;
            $totalEjOk = 0;
            foreach ($rows as $r) {
                $totalOk   += (int)($r['antal_ok']    ?? 0);
                $totalEjOk += (int)($r['antal_ej_ok'] ?? 0);
            }
            $totalIbc  = $totalOk + $totalEjOk;
            $kvalitetPct = $totalIbc > 0 ? round(($totalOk / $totalIbc) * 100, 1) : 0;

            // Föregående dag
            $prevOk   = 0;
            $prevEjOk = 0;
            foreach ($prevRows as $r) {
                $prevOk   += (int)($r['antal_ok']    ?? 0);
                $prevEjOk += (int)($r['antal_ej_ok'] ?? 0);
            }
            $prevIbc   = $prevOk + $prevEjOk;
            $deltaIbc  = $totalIbc - $prevIbc;

            // Hämta runtime från tvattlinje_ibc om det finns
            $runtimeMinutes = 0;
            $rastMinutes    = 0;
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

            $events = [];

            // tvattlinje_onoff
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM tvattlinje_onoff WHERE DATE(datum) = :date ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'onoff';
                    $row['event_type'] = intval($row['running'] ?? 0) === 1 ? 'ON' : 'OFF';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream onoff: ' . $e->getMessage()); }

            // tvattlinje_ibc
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM tvattlinje_ibc WHERE DATE(datum) = :date ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'ibc';
                    $row['event_type'] = 'IBC';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream ibc: ' . $e->getMessage()); }

            // tvattlinje_rast
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, datum, rast_status FROM tvattlinje_rast WHERE DATE(datum) = :date ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date]);
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
                    $s = $this->pdo->prepare("SELECT id, datum, status FROM tvattlinje_driftstopp WHERE DATE(datum) = :date ORDER BY datum DESC LIMIT " . $limit);
                    $s->execute([':date' => $date]);
                    $dsRows = $s->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    $s = $this->pdo->prepare("SELECT id, datum, driftstopp_status FROM tvattlinje_driftstopp WHERE DATE(datum) = :date ORDER BY datum DESC LIMIT " . $limit);
                    $s->execute([':date' => $date]);
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
                    "SELECT *, created_at AS datum FROM tvattlinje_skiftrapport WHERE DATE(created_at) = :date ORDER BY created_at DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row['source'] = 'skiftrapport';
                    $row['event_type'] = 'SKIFTRAPPORT';
                    $events[] = $row;
                }
            } catch (\Throwable $e) { error_log('TvattlinjeController::plcDiagnostikStream skiftrapport: ' . $e->getMessage()); }

            // tvattlinje_plc_raw — append-only raw register log
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM tvattlinje_plc_raw WHERE DATE(datum) = :date ORDER BY datum DESC LIMIT " . $limit
                );
                $stmt->execute([':date' => $date]);
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
                        SELECT MAX(datum) AS datum FROM tvattlinje_onoff WHERE DATE(datum) = :date1
                        UNION ALL
                        SELECT MAX(datum) FROM tvattlinje_ibc WHERE DATE(datum) = :date2
                        UNION ALL
                        SELECT MAX(datum) FROM tvattlinje_rast WHERE DATE(datum) = :date3
                        UNION ALL
                        SELECT MAX(datum) FROM tvattlinje_driftstopp WHERE DATE(datum) = :date4
                    ) sub
                ");
                $latestEventRow->execute([':date1' => $date, ':date2' => $date, ':date3' => $date, ':date4' => $date]);
                $latestEventDatum = $latestEventRow->fetchColumn() ?: null;
            } catch (\Throwable $e) {}

            echo json_encode([
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
        } catch (\Throwable $e) {
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
                $diff   = $now->diff($lastDt);
                $plcAgeMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
            }
        } catch (\Throwable $e) { error_log('TvattlinjeController::getPlcDiagnostics plcLastSeen: ' . $e->getMessage()); }

        // Rådata IBC-poster för valt period
        $latestIbc = [];
        try {
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
        foreach (array_reverse($latestIbc) as $r) {
            $ts = strtotime($r['datum']);
            if ($prevTs !== null) $intervals[] = round(($ts - $prevTs) / 60.0, 1);
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
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)                              AS dag,
                        SUM(GREATEST(0, COALESCE(totalt,    0))) AS total_ibc,
                        SUM(GREATEST(0, COALESCE(antal_ok,  0))) AS total_ok,
                        SUM(GREATEST(0, COALESCE(antal_ej_ok,0))) AS total_ej_ok,
                        COUNT(*)                                 AS skift_count,
                        SUM(GREATEST(0, COALESCE(drifttid,  0))) AS total_runtime_min
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                    GROUP BY DATE(datum)
                    ORDER BY dag ASC
                ");
                $stmt->execute(['dagar' => $dagar]);
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

            foreach ($rows as $r) {
                $tot = max(0, (int)$r['total_ibc']);
                $ok  = max(0, (int)$r['total_ok']);

                // Kvalitet: antal_ok / totalt. Om totalt=0 → 100% (ingen data = ingen kassation).
                $qual_pct = ($ok > 0 && $tot > 0) ? round(($ok / $tot) * 100, 1) : 100.0;

                // Tillgänglighet: drifttid (D4007, minuter) från skiftrapport.
                $runtimeMin  = max(0, isset($r['total_runtime_min']) ? (float)$r['total_runtime_min'] : 0);
                $rastMin     = 0; // rast ingår inte i skiftrapportens drifttid (D4007 exkluderar rast)
                $netRuntimeMin = max(0, $runtimeMin - $rastMin);
                // Planerad arbetstid: mån-tors 07-16 med 45 min rast = 495 min, fre 07-15 = 480 min
                $dayN       = (int)date('N', strtotime($r['dag'])); // 1=mån … 7=sön
                $plannedMin = ($dayN === 5) ? 480.0 : 495.0;
                $avail_pct = ($runtimeMin > 0)
                    ? min(100.0, round(($netRuntimeMin / $plannedMin) * 100, 1))
                    : 100.0; // Ingen runtimedata = antag 100% tillgänglighet

                // Prestanda (0-100): faktisk takt / idealtakt (mål: 1 IBC per 3 min = 20 IBC/h)
                $idealPerDag = ($plannedMin / 60.0) * 20.0;
                $perf_pct = ($idealPerDag > 0)
                    ? min(100.0, round(($tot / $idealPerDag) * 100, 1))
                    : 100.0;

                // OEE = Tillgänglighet × Prestanda × Kvalitet (0-100 skala → dela med 10000)
                $oee_pct = round(($avail_pct / 100.0) * ($perf_pct / 100.0) * ($qual_pct / 100.0) * 100.0, 1);

                $totalIbcSum += $tot;
                $totalOkSum  += $ok;
                // Bästa dag = flest inskickade IBCer (totalt), exkludera pågående dag
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
                $snittOee      = round(array_sum(array_column($dagData, 'oee_pct'))  / $antalDagar, 1);
                $snittKvalitet = round(array_sum(array_column($dagData, 'qual_pct')) / $antalDagar, 1);
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

}