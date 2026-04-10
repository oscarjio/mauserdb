<?php
require_once __DIR__ . '/AuditController.php';

class TvattlinjeController {
    private $pdo;

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
            } elseif ($action === 'statistics') {
                $this->getStatistics();
            } elseif ($action === 'report') {
                $this->getReport();
            } elseif ($action === 'oee-trend') {
                $this->getOeeTrend();
            } elseif ($action === 'skiftrapport-statistik') {
                $this->getSkiftrapportStatistik();
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
        }

        // Om ingen matchande metod finns
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod eller action'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================
    // Ny key-value settings (tvattlinje_settings tabell)
    // =========================================================

    private function ensureSettingsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tvattlinje_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting VARCHAR(100) NOT NULL UNIQUE,
                value VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['dagmal',       '80'],
            ['takt_mal',     '15'],
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
            $rows = $this->pdo->query("SELECT setting, value FROM tvattlinje_settings ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
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
            $stmt = $this->pdo->prepare("INSERT INTO tvattlinje_settings (setting, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP");
            foreach ($allowed as $key) {
                if (!isset($data[$key])) continue;
                $value = trim($data[$key]);
                // Validera tidsfält
                if (in_array($key, ['skift_start', 'skift_slut'], true)) {
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) continue;
                } else {
                    $value = (string)max(0, intval($value));
                }
                $stmt->execute([$key, $value]);
            }
            AuditLogger::log($this->pdo, 'update_tvattlinje_settings_v2', 'tvattlinje_settings', null,
                json_encode(array_intersect_key($data, array_flip($allowed)), JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true, 'message' => 'Inställningar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
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
            } catch (\Exception $e) { error_log('TvattlinjeController::getSystemStatus plcLastSeen: ' . $e->getMessage()); }

            // Lösnummer
            $losnummer = null;
            try {
                $row = $this->pdo->query("SELECT ibc_count FROM tvattlinje_ibc ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $losnummer = $row ? (int)$row['ibc_count'] : null;
            } catch (\Exception $e) { error_log('TvattlinjeController::getSystemStatus losnummer: ' . $e->getMessage()); }

            // Antal poster idag
            $posterIdag = 0;
            try {
                $posterIdag = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM tvattlinje_ibc WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY"
                )->fetchColumn();
            } catch (\Exception $e) { error_log('TvattlinjeController::getSystemStatus posterIdag: ' . $e->getMessage()); }

            // Är linjen i drift? PLC-data < 15 min gammal
            $isRunning = ($plcAgeMinutes !== null && $plcAgeMinutes < 15);

            // Databas OK
            $dbStatus = 'ok';
            try {
                $this->pdo->query("SELECT 1");
            } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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

            // IBC idag — antal rader i tvattlinje_ibc
            $ibcIdag = 0;
            try {
                $ibcIdag = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM tvattlinje_ibc WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY"
                )->fetchColumn();
            } catch (\Exception $e) { error_log('TvattlinjeController::getTodaySnapshot ibcIdag: ' . $e->getMessage()); }

            // Dagsmål — veckodagsmål (0=Måndag, PHP ISO-1 → 0-index: ISO-1)
            $dagmal = 80;
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
                } catch (\Exception $e) { error_log('TvattlinjeController::getTodaySnapshot weekdayGoal: ' . $e->getMessage()); }
            } catch (\Exception $e) { error_log('TvattlinjeController::getTodaySnapshot dagmal: ' . $e->getMessage()); }

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
            } catch (\Exception $e) { error_log('TvattlinjeController::getTodaySnapshot isRunning: ' . $e->getMessage()); }

            // Takt: IBC per timme senaste 2 timmar
            $taktPerTimme = 0.0;
            try {
                $cnt = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM tvattlinje_ibc WHERE datum >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
                )->fetchColumn();
                $taktPerTimme = round($cnt / 2.0, 1);
            } catch (\Exception $e) { error_log('TvattlinjeController::getTodaySnapshot taktPerTimme: ' . $e->getMessage()); }

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
            } catch (\Exception $e) { error_log('TvattlinjeController::getTodaySnapshot skiftTimmar: ' . $e->getMessage()); }

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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
            // Hämta antal IBCer producerade idag
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*)
                FROM tvattlinje_ibc
                WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
            ');
            $stmt->execute();
            $ibcToday = (int)$stmt->fetchColumn();
            
            // Hämta target från settings
            $settings = $this->loadSettings();
            $ibcTarget = $settings['antal_per_dag'] ?? 150;

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
            if ($totalRuntimeMinutes > 0 && $ibcToday > 0 && $hourlyTarget > 0) {
                $actualProductionPerHour = ($ibcToday * 60) / $totalRuntimeMinutes;
                $productionPercentage = round(($actualProductionPerHour / $hourlyTarget) * 100, 1);
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
            } catch (Exception $e) {
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

            echo json_encode([
                'success' => true,
                'data' => [
                    'running' => $isRunning,
                    'lastUpdate' => $lastUpdate
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
            } catch (\Exception $e) { /* Kolumn finns redan — OK */ }
            try {
                $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0");
            } catch (\Exception $e) { /* Kolumn finns redan — OK */ }

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
        } catch (Exception $e) {
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
        } catch (\Exception) { /* Kolumn finns redan — OK */ }
        try {
            $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0");
        } catch (\Exception) { /* Kolumn finns redan — OK */ }

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
                $sr = $this->pdo->query("SELECT value FROM tvattlinje_settings WHERE setting = 'takt_mal' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                if ($sr && (float)$sr['value'] > 0) $target_cycle_time = (float)$sr['value'];
            } catch (\Exception $e) { /* ignorera */ }

            // Hämta cyklar med PLC-fält (om de finns)
            $stmt = $this->pdo->prepare('
                SELECT
                    i.datum,
                    i.ibc_count,
                    i.s_count,
                    COALESCE(i.effektivitet, 100) as produktion_procent,
                    COALESCE(i.skiftraknare, 1) as skiftraknare,
                    TIMESTAMPDIFF(SECOND,
                        LAG(i.datum) OVER (ORDER BY i.datum),
                        i.datum
                    ) / 60.0 as cycle_time,
                    i.op1,
                    i.op2,
                    i.op3,
                    i.ibc_ok,
                    i.ibc_ej_ok,
                    i.omtvaatt,
                    i.runtime_plc,
                    i.rasttime,
                    i.lopnummer
                FROM tvattlinje_ibc i
                WHERE i.datum >= :start AND i.datum < DATE_ADD(:end, INTERVAL 1 DAY)
                ORDER BY i.datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $rawCycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                        ORDER BY datum ASC
                    ");
                    $stmt->execute(['start' => $start, 'end' => $end]);
                    $rast_events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    break; // Använd första funna tabellen
                } catch (\Exception $e) { /* prova nästa tabell */ }
            }

            // Beräkna runtime
            $totalRuntimeMinutes = 0;
            if (count($onoff_events) > 0) {
                $lastRunningStart = null;
                foreach ($onoff_events as $event) {
                    $eventTime = new DateTime($event['datum'], new DateTimeZone('Europe/Stockholm'));
                    $isRunning = (bool)($event['running'] ?? false);
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $eventTime;
                    } elseif (!$isRunning && $lastRunningStart !== null) {
                        $diff = $lastRunningStart->diff($eventTime);
                        $totalRuntimeMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $lastRunningStart = null;
                    }
                }
                if ($lastRunningStart !== null) {
                    $lastEvt = new DateTime($onoff_events[count($onoff_events) - 1]['datum'], new DateTimeZone('Europe/Stockholm'));
                    $diff = $lastRunningStart->diff($lastEvt);
                    $totalRuntimeMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                }
            }

            $total_cycles = count($cycles);
            if ((float)$totalRuntimeMinutes < 0.001 && $total_cycles > 0) {
                // Fallback: span av första–sista cykel
                $validCycles = array_filter($cycles, fn($c) => $c['datum'] !== null);
                if (count($validCycles) > 1) {
                    $first = new DateTime(reset($validCycles)['datum'], new DateTimeZone('Europe/Stockholm'));
                    $last  = new DateTime(end($validCycles)['datum'], new DateTimeZone('Europe/Stockholm'));
                    $diff  = $first->diff($last);
                    $totalRuntimeMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                }
            }

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

            $netRuntimeMinutes = max(0, $totalRuntimeMinutes - $totalRastMinutes);
            $total_runtime_hours = $totalRuntimeMinutes / 60;

            // Snitt cykeltid
            $avg_cycle_time = 0;
            $cycle_times = array_filter(array_column($cycles, 'cycle_time'), fn($v) => $v !== null && $v > 0);
            if (count($cycle_times) > 0) {
                $avg_cycle_time = array_sum($cycle_times) / count($cycle_times);
            }

            // Effektivitet: (total_cycles * target_cycle_time) / net_runtime_minutes * 100
            $avg_production_percent = 0;
            if ($netRuntimeMinutes > 0 && $total_cycles > 0 && $target_cycle_time > 0) {
                $avg_production_percent = round(($total_cycles * $target_cycle_time) / $netRuntimeMinutes * 100, 1);
            }

            $unique_dates = array_unique(array_map(fn($c) => date('Y-m-d', strtotime($c['datum'])), $cycles));
            $days_with_production = count($unique_dates);

            echo json_encode([
                'success' => true,
                'data' => [
                    'cycles'       => $cycles,
                    'onoff_events' => $onoff_events,
                    'rast_events'  => $rast_events,
                    'summary' => [
                        'total_cycles'          => $total_cycles,
                        'avg_production_percent'=> round($avg_production_percent, 1),
                        'avg_cycle_time'        => round($avg_cycle_time, 2),
                        'target_cycle_time'     => $target_cycle_time,
                        'total_runtime_hours'   => round($total_runtime_hours, 2),
                        'net_runtime_minutes'   => round($netRuntimeMinutes, 1),
                        'total_rast_minutes'    => round($totalRastMinutes, 1),
                        'days_with_production'  => $days_with_production,
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
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
                    SELECT
                        sr.*,
                        u.username as op1_name
                    FROM tvattlinje_skiftrapport sr
                    LEFT JOIN users u ON sr.op1 = u.operator_id AND u.operator_id IS NOT NULL
                    WHERE sr.datum >= :start AND sr.datum <= :end
                    ORDER BY sr.datum DESC, sr.id DESC
                ");
                $stmt->execute(['start' => $start, 'end' => $end]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log('TvattlinjeController::getSkiftrapportStatistik rows: ' . $e->getMessage());
                // Försök utan JOIN
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT * FROM tvattlinje_skiftrapport
                        WHERE datum >= :start AND datum <= :end
                        ORDER BY datum DESC, id DESC
                    ");
                    $stmt->execute(['start' => $start, 'end' => $end]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e2) {
                    error_log('TvattlinjeController::getSkiftrapportStatistik fallback: ' . $e2->getMessage());
                }
            }

            // Beräkna summerade KPI:er
            $totalOk     = 0;
            $totalEjOk   = 0;
            $totalOmtvatt = 0;
            $totalDrifttid = 0;
            foreach ($rows as $r) {
                $totalOk     += (int)($r['antal_ok']   ?? 0);
                $totalEjOk   += (int)($r['antal_ej_ok'] ?? 0);
                $totalOmtvatt+= (int)($r['omtvaatt']   ?? 0);
                $totalDrifttid+= (int)($r['drifttid']  ?? 0);
            }
            $totalIbc = $totalOk + $totalEjOk + $totalOmtvatt;

            echo json_encode([
                'success' => true,
                'data'    => $rows,
                'summary' => [
                    'total_ibc'      => $totalIbc,
                    'total_ok'       => $totalOk,
                    'total_ej_ok'    => $totalEjOk,
                    'total_omtvaatt' => $totalOmtvatt,
                    'total_drifttid' => $totalDrifttid,
                    'skift_count'    => count($rows),
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
    // OEE-trend — daglig statistik över N dagar (standard 30)
    // Returnerar { empty: true } om ingen data finns
    // =========================================================

    private function getOeeTrend() {
        $dagar = max(7, min(365, intval($_GET['dagar'] ?? 30)));

        try {
            $rows = [];

            // Hämta daglig data från tvattlinje_skiftrapport
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        datum                     AS dag,
                        SUM(antal_ok)             AS total_ok,
                        SUM(antal_ej_ok)          AS total_ej_ok,
                        SUM(antal_ok + antal_ej_ok) AS total_ibc,
                        COUNT(*)                  AS skift_count
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                    GROUP BY datum
                    ORDER BY dag ASC
                ");
                $stmt->execute(['dagar' => $dagar]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
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

            // Bygg daglig OEE (här: kvalitet som OEE-proxy eftersom linjen saknar runtime-data)
            $dagData = [];
            $totalIbcSum = 0;
            $bestaDag    = null;
            $bestaIbc    = 0;
            $oeeSum      = 0;

            foreach ($rows as $r) {
                $tot = (int)$r['total_ibc'];
                $ok  = (int)$r['total_ok'];
                $oee = ($tot > 0) ? round(($ok / $tot) * 100, 1) : 0;
                $totalIbcSum += $tot;
                $oeeSum      += $oee;
                if ($tot > $bestaIbc) {
                    $bestaIbc = $tot;
                    $bestaDag = $r['dag'];
                }
                $dagData[] = [
                    'dag'        => $r['dag'],
                    'total_ibc'  => $tot,
                    'total_ok'   => $ok,
                    'total_ej_ok'=> (int)$r['total_ej_ok'],
                    'oee_pct'    => $oee,
                    'skift_count'=> (int)$r['skift_count'],
                ];
            }

            $antalDagar = count($dagData);
            $snittPerDag = $antalDagar > 0 ? round($totalIbcSum / $antalDagar, 1) : 0;
            $snittOee    = $antalDagar > 0 ? round($oeeSum / $antalDagar, 1)      : 0;

            echo json_encode([
                'success' => true,
                'empty'   => false,
                'data'    => $dagData,
                'summary' => [
                    'total_ibc'      => $totalIbcSum,
                    'snitt_per_dag'  => $snittPerDag,
                    'snitt_oee_pct'  => $snittOee,
                    'basta_dag'      => $bestaDag,
                    'basta_ibc'      => $bestaIbc,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
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