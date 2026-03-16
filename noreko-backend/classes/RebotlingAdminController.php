<?php
class RebotlingAdminController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function ensureSettingsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_settings` (
                `id`               INT          NOT NULL DEFAULT 1,
                `rebotling_target` INT          NOT NULL DEFAULT 1000,
                `hourly_target`    INT          NOT NULL DEFAULT 50,
                `auto_start`       TINYINT(1)   NOT NULL DEFAULT 0,
                `maintenance_mode` TINYINT(1)   NOT NULL DEFAULT 0,
                `alert_threshold`  INT          NOT NULL DEFAULT 80,
                `shift_hours`      DECIMAL(4,1) NOT NULL DEFAULT 8.0,
                `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec(
            "INSERT IGNORE INTO `rebotling_settings` (id) VALUES (1)"
        );
    }


    public function getAdminSettings() {
        try {
            $this->ensureSettingsTable();
            $row = $this->pdo->query("SELECT * FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

            $settings = [
                'rebotlingTarget' => (int)($row['rebotling_target'] ?? 1000),
                'hourlyTarget'    => (int)($row['hourly_target']    ?? 50),
                'shiftHours'      => (float)($row['shift_hours']    ?? 8.0),
                'minOperators'    => (int)($row['min_operators']    ?? 2),
                'systemSettings'  => [
                    'autoStart'        => (bool)($row['auto_start']       ?? false),
                    'maintenanceMode'  => (bool)($row['maintenance_mode'] ?? false),
                    'alertThreshold'   => (int)($row['alert_threshold']   ?? 80)
                ]
            ];

            echo json_encode(['success' => true, 'data' => $settings], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('Kunde inte hämta admin-inställningar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta admin-inställningar'], JSON_UNESCAPED_UNICODE);
        }
    }


    public function saveAdminSettings() {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $this->ensureSettingsTable();

            $rebotlingTarget = isset($data['rebotlingTarget']) ? max(1, intval($data['rebotlingTarget'])) : null;
            $hourlyTarget    = isset($data['hourlyTarget'])    ? max(1, intval($data['hourlyTarget']))    : null;
            $shiftHours      = isset($data['shiftHours'])      ? max(1.0, min(24.0, floatval($data['shiftHours']))) : null;
            $sys             = $data['systemSettings'] ?? [];
            $autoStart       = isset($sys['autoStart'])       ? ($sys['autoStart']       ? 1 : 0) : null;
            $maintenanceMode = isset($sys['maintenanceMode']) ? ($sys['maintenanceMode'] ? 1 : 0) : null;
            $alertThreshold  = isset($sys['alertThreshold'])  ? max(0, min(100, intval($sys['alertThreshold']))) : null;
            $minOperators    = isset($data['minOperators'])   ? max(1, min(10, intval($data['minOperators']))) : null;

            $fields = [];
            $params = [];
            if ($rebotlingTarget !== null) { $fields[] = 'rebotling_target = ?'; $params[] = $rebotlingTarget; }
            if ($hourlyTarget    !== null) { $fields[] = 'hourly_target = ?';    $params[] = $hourlyTarget; }
            if ($shiftHours      !== null) { $fields[] = 'shift_hours = ?';      $params[] = $shiftHours; }
            if ($autoStart       !== null) { $fields[] = 'auto_start = ?';       $params[] = $autoStart; }
            if ($maintenanceMode !== null) { $fields[] = 'maintenance_mode = ?'; $params[] = $maintenanceMode; }
            if ($alertThreshold  !== null) { $fields[] = 'alert_threshold = ?';  $params[] = $alertThreshold; }
            // min_operators sparas om kolumnen finns
            if ($minOperators !== null) {
                try {
                    $fields[] = 'min_operators = ?';
                    $params[] = $minOperators;
                } catch (\Exception $ignored) {}
            }

            if (!empty($fields)) {
                $params[] = 1; // id
                $stmt = $this->pdo->prepare(
                    'UPDATE rebotling_settings SET ' . implode(', ', $fields) . ' WHERE id = ?'
                );
                $stmt->execute($params);
            }

            // Logga mål-ändringar i historiktoken
            if ($rebotlingTarget !== null) {
                $user = $_SESSION['username'] ?? ($_SESSION['user_login'] ?? 'system');
                try {
                    $this->pdo->exec("
                        CREATE TABLE IF NOT EXISTS rebotling_goal_history (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            goal_type VARCHAR(50) NOT NULL DEFAULT 'dagmal',
                            value INT NOT NULL,
                            changed_by VARCHAR(100),
                            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_type_time (goal_type, changed_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    $logStmt = $this->pdo->prepare(
                        "INSERT INTO rebotling_goal_history (goal_type, value, changed_by) VALUES ('dagmal', ?, ?)"
                    );
                    $logStmt->execute([intval($rebotlingTarget), $user]);
                } catch (Exception $logEx) {
                    error_log('Kunde inte logga mål-historik: ' . $logEx->getMessage());
                }
            }

            echo json_encode(['success' => true, 'message' => 'Inställningar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('Kunde inte spara inställningar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara inställningar'], JSON_UNESCAPED_UNICODE);
        }
    }


    private function ensureWeekdayGoalsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_weekday_goals` (
                `id`          INT         NOT NULL AUTO_INCREMENT,
                `weekday`     TINYINT     NOT NULL COMMENT '1=Måndag ... 7=Söndag (ISO)',
                `daily_goal`  INT         NOT NULL DEFAULT 1000,
                `label`       VARCHAR(20) NOT NULL DEFAULT '',
                `updated_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_weekday` (`weekday`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Standardvärden
        $defaults = [
            [1, 900,  'Måndag'],
            [2, 1000, 'Tisdag'],
            [3, 1000, 'Onsdag'],
            [4, 1000, 'Torsdag'],
            [5, 950,  'Fredag'],
            [6, 0,    'Lördag'],
            [7, 0,    'Söndag'],
        ];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO rebotling_weekday_goals (weekday, daily_goal, label) VALUES (?, ?, ?)");
        foreach ($defaults as [$wd, $goal, $lbl]) {
            $stmt->execute([$wd, $goal, $lbl]);
        }
    }


    public function getWeekdayGoals() {
        try {
            $this->ensureWeekdayGoalsTable();
            $rows = $this->pdo->query("SELECT weekday, daily_goal, label FROM rebotling_weekday_goals ORDER BY weekday")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getWeekdayGoals: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodagsmål'], JSON_UNESCAPED_UNICODE);
        }
    }


    public function saveWeekdayGoals() {
        $data = json_decode(file_get_contents('php://input'), true);
        $goals = $data['goals'] ?? [];
        if (!is_array($goals)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->ensureWeekdayGoalsTable();
            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("UPDATE rebotling_weekday_goals SET daily_goal = ? WHERE weekday = ?");
                foreach ($goals as $item) {
                    $wd   = intval($item['weekday'] ?? 0);
                    $goal = max(0, intval($item['daily_goal'] ?? 0));
                    if ($wd >= 1 && $wd <= 7) {
                        $stmt->execute([$goal, $wd]);
                    }
                }
                $this->pdo->commit();
            } catch (Exception $txEx) {
                $this->pdo->rollBack();
                throw $txEx;
            }
            echo json_encode(['success' => true, 'message' => 'Veckodagsmål sparade'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('saveWeekdayGoals: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara veckodagsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Alert-trösklar
    // =========================================================

    /**
     * Säkerställ att alert_thresholds-kolumnen finns i rebotling_settings.
     * Kolumnen lagrar ett JSON-objekt med tröskelvärden.
     */

    private function ensureAlertThresholdsColumn() {
        try {
            $this->ensureSettingsTable();
            // Kontrollera om kolumnen finns
            $col = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'alert_thresholds'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN alert_thresholds TEXT NULL DEFAULT NULL"
                );
            }
        } catch (Exception $e) {
            error_log('ensureAlertThresholdsColumn: ' . $e->getMessage());
        }
    }


    private function defaultAlertThresholds(): array {
        return [
            'oee_warn'     => 80,
            'oee_danger'   => 70,
            'prod_warn'    => 80,
            'prod_danger'  => 60,
            'plc_max_min'  => 15,
            'quality_warn' => 95,
        ];
    }


    public function getAlertThresholds() {
        try {
            $this->ensureAlertThresholdsColumn();
            $row = $this->pdo->query(
                "SELECT alert_thresholds FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            $defaults   = $this->defaultAlertThresholds();
            $thresholds = $defaults;

            if ($row && !empty($row['alert_thresholds'])) {
                $saved = json_decode($row['alert_thresholds'], true);
                if (is_array($saved)) {
                    $thresholds = array_merge($defaults, $saved);
                }
            }

            echo json_encode(['success' => true, 'data' => $thresholds], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getAlertThresholds: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta alert-trösklar'], JSON_UNESCAPED_UNICODE);
        }
    }


    public function saveAlertThresholds() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->ensureAlertThresholdsColumn();

            $allowed = array_keys($this->defaultAlertThresholds());
            $cleaned = [];
            foreach ($allowed as $key) {
                if (isset($data[$key])) {
                    $cleaned[$key] = max(0, intval($data[$key]));
                }
            }

            $json = json_encode($cleaned);
            $stmt = $this->pdo->prepare(
                "UPDATE rebotling_settings SET alert_thresholds = ? WHERE id = 1"
            );
            $stmt->execute([$json]);

            echo json_encode(['success' => true, 'message' => 'Trösklar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('saveAlertThresholds: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara trösklar'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Snabb produktionsöversikt — idag
    // =========================================================


    public function getTodaySnapshot() {
        try {
            $tz  = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // IBC idag
            $ibcToday = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()"
            )->fetchColumn();

            // Dagsmål — försök veckodagsmål annars settings
            $dailyTarget = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dailyTarget = (int)$sr['rebotling_target'];

                // Veckodagsmål: ISO weekday 1=Måndag
                $iso = (int)$now->format('N');
                try {
                    $wg = $this->pdo->prepare(
                        "SELECT daily_goal FROM rebotling_weekday_goals WHERE weekday = ?"
                    );
                    $wg->execute([$iso]);
                    $wgRow = $wg->fetch(PDO::FETCH_ASSOC);
                    if ($wgRow && (int)$wgRow['daily_goal'] > 0) {
                        $dailyTarget = (int)$wgRow['daily_goal'];
                    }
                } catch (Exception) { /* tabell saknas */ }

                // Kolla om undantag finns för idag
                try {
                    $stmtEx = $this->pdo->prepare('SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE()');
                    $stmtEx->execute();
                    $exceptionRow = $stmtEx->fetch(PDO::FETCH_ASSOC);
                    if ($exceptionRow) {
                        $dailyTarget = (int)$exceptionRow['justerat_mal'];
                    }
                } catch (Exception) { /* tabell saknas ännu — ignorera */ }
            } catch (Exception) { /* ignorera */ }

            // Linjen kör?
            $isRunning = false;
            try {
                $row = $this->pdo->query(
                    "SELECT running FROM rebotling_onoff ORDER BY datum DESC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                $isRunning = $row ? (bool)$row['running'] : false;
            } catch (Exception) { /* ignorera */ }

            // Takt: IBC per timme baserat på produktion senaste 2 timmar
            $ratePerHour = 0.0;
            try {
                $cnt = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM rebotling_ibc
                     WHERE datum >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
                )->fetchColumn();
                $ratePerHour = round($cnt / 2.0, 1);
            } catch (Exception) { /* ignorera */ }

            // Skiftlängd från settings
            $shiftHours = 8.0;
            try {
                $sh = $this->pdo->query(
                    "SELECT shift_hours FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sh) $shiftHours = (float)$sh['shift_hours'];
            } catch (Exception) { /* ignorera */ }

            // Prognos: skiftstart 06:00 lokal tid
            $shiftStart = new DateTime($now->format('Y-m-d') . ' 06:00:00', $tz);
            $minutesSinceStart = max(1, ($now->getTimestamp() - $shiftStart->getTimestamp()) / 60);
            $remainingMin = max(0, ($shiftHours * 60) - $minutesSinceStart);
            $forecast = (int)round($ibcToday + ($ratePerHour / 60.0) * $remainingMin);

            $pct = $dailyTarget > 0 ? round($ibcToday / $dailyTarget * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => [
                    'ibc_today'    => $ibcToday,
                    'daily_target' => $dailyTarget,
                    'pct_of_goal'  => $pct,
                    'rate_per_h'   => $ratePerHour,
                    'forecast'     => $forecast,
                    'is_running'   => $isRunning,
                    'server_time'  => $now->format('H:i:s'),
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getTodaySnapshot: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta dagens snapshot'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skifttider
    // =========================================================


    private function ensureShiftTimesTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_shift_times` (
                `id`         INT         NOT NULL AUTO_INCREMENT,
                `shift_name` VARCHAR(50) NOT NULL,
                `start_time` TIME        NOT NULL DEFAULT '06:00:00',
                `end_time`   TIME        NOT NULL DEFAULT '14:00:00',
                `enabled`    TINYINT(1)  NOT NULL DEFAULT 1,
                `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_shift_name` (`shift_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['förmiddag',   '06:00:00', '14:00:00', 1],
            ['eftermiddag', '14:00:00', '22:00:00', 1],
            ['natt',        '22:00:00', '06:00:00', 0],
        ];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO rebotling_shift_times (shift_name, start_time, end_time, enabled) VALUES (?, ?, ?, ?)");
        foreach ($defaults as [$name, $start, $end, $enabled]) {
            $stmt->execute([$name, $start, $end, $enabled]);
        }
    }


    public function getShiftTimes() {
        try {
            $this->ensureShiftTimesTable();
            $rows = $this->pdo->query("SELECT shift_name, start_time, end_time, enabled FROM rebotling_shift_times ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getShiftTimes: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skifttider'], JSON_UNESCAPED_UNICODE);
        }
    }


    public function saveShiftTimes() {
        $data = json_decode(file_get_contents('php://input'), true);
        $shifts = $data['shifts'] ?? [];
        if (!is_array($shifts)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->ensureShiftTimesTable();
            $stmt = $this->pdo->prepare("UPDATE rebotling_shift_times SET start_time = ?, end_time = ?, enabled = ? WHERE shift_name = ?");
            foreach ($shifts as $s) {
                $name    = $s['shift_name'] ?? '';
                $start   = $s['start_time'] ?? '06:00:00';
                $end     = $s['end_time']   ?? '14:00:00';
                $enabled = isset($s['enabled']) ? ($s['enabled'] ? 1 : 0) : 1;
                // Validera tidsformat
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start)) continue;
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end))   continue;
                if (in_array($name, ['förmiddag', 'eftermiddag', 'natt'])) {
                    $stmt->execute([$start, $end, $enabled, $name]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Skifttider sparade'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('saveShiftTimes: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara skifttider'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Executive Dashboard — samlad endpoint för VD-vyn
    // =========================================================

    /**
     * GET ?action=rebotling&run=exec-dashboard
     *
     * Returnerar allt som executive dashboard behöver i ett anrop:
     *   - today: ibcToday, ibcTarget, pct, prognos, oee (idag vs igår)
     *   - week: total IBC denna vecka, förra veckan, diff, snitt kvalitet%, snitt OEE%, bästa operatör
     *   - days7: IBC per dag senaste 7 dagarna + dagsmål per rad
     *   - lastShiftOperators: aktiva operatörer senaste skiftet (namn, ibc/h, kvalitet%, bonus)
     */

    public function getSystemStatus() {
        try {
            // Senaste PLC-ping (senaste raden i rebotling_ibc eller rebotling_onoff)
            $lastPlcPing  = null;
            $lastLopnummer = null;
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM rebotling_ibc")->fetch(PDO::FETCH_ASSOC);
                $lastPlcPing = $row ? $row['last_ping'] : null;
            } catch (Exception) { /* ignorera */ }

            try {
                $row = $this->pdo->query("SELECT lopnummer FROM rebotling_lopnummer_current WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $lastLopnummer = $row ? (int)$row['lopnummer'] : null;
            } catch (Exception) { /* ignorera */ }

            // Databas OK
            $dbOk = true;
            try {
                $this->pdo->query("SELECT 1");
            } catch (Exception) {
                $dbOk = false;
            }

            // Räkna skiftrapporter idag
            $reportsToday = 0;
            try {
                $row = $this->pdo->query("SELECT COUNT(*) FROM rebotling_skiftrapport WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                $reportsToday = (int)$row;
            } catch (Exception) { /* ignorera */ }

            // Totalt IBC idag från PLC
            $ibcToday = 0;
            try {
                $row = $this->pdo->query("SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()")->fetchColumn();
                $ibcToday = (int)$row;
            } catch (Exception) { /* ignorera */ }

            echo json_encode([
                'success' => true,
                'data' => [
                    'last_plc_ping'   => $lastPlcPing,
                    'last_lopnummer'  => $lastLopnummer,
                    'db_ok'           => $dbOk,
                    'reports_today'   => $reportsToday,
                    'ibc_today'       => $ibcToday,
                    'server_time'     => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getSystemStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta systemstatus'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Jämför aggregerad skiftdata för två datum.
     * GET ?action=rebotling&run=shift-compare&date_a=YYYY-MM-DD&date_b=YYYY-MM-DD
     */

    public function getAllLinesStatus() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $lines = [];

        // --- Rebotling ---
        try {
            $tz  = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // Senaste raden i rebotling_ibc
            $stmt = $this->pdo->query(
                "SELECT datum FROM rebotling_ibc ORDER BY datum DESC LIMIT 1"
            );
            $latestRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $senaste_data_min = null;
            $kor = false;
            if ($latestRow && !empty($latestRow['datum'])) {
                $latestDt = new DateTime($latestRow['datum'], $tz);
                $diffSec  = $now->getTimestamp() - $latestDt->getTimestamp();
                $senaste_data_min = round($diffSec / 60, 1);
                $kor = ($diffSec < 600); // kör om senaste data < 10 min
            }

            // IBC idag
            $ibcIdag = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()"
            )->fetchColumn();

            // OEE idag (om tabellen stödjer det) — hämta via befintlig logik
            $oeePct   = null;
            $malPct   = null;
            $dagsMal  = 1000;
            try {
                $sr = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dagsMal = (int)$sr['rebotling_target'];
            } catch (Exception) { /* ignorera */ }

            if ($dagsMal > 0) {
                $malPct = round($ibcIdag / $dagsMal * 100, 1);
            }

            // OEE idag: hämta senaste skiftets aggregat
            try {
                $oeeRow = $this->pdo->query(
                    "SELECT
                        SUM(max_ibc_ok)      AS ibc_ok,
                        SUM(max_runtime_plc) AS runtime,
                        SUM(max_rasttime)    AS rasttime
                     FROM (
                        SELECT
                            skiftraknare,
                            MAX(ibc_ok)      AS max_ibc_ok,
                            MAX(runtime_plc) AS max_runtime_plc,
                            MAX(rasttime)    AS max_rasttime
                        FROM rebotling_ibc
                        WHERE DATE(datum) = CURDATE()
                        GROUP BY skiftraknare
                     ) agg"
                )->fetch(PDO::FETCH_ASSOC);

                if ($oeeRow && $oeeRow['runtime'] > 0) {
                    $ibcOk      = (float)$oeeRow['ibc_ok'];
                    $runtimeSek = (float)$oeeRow['runtime'];
                    $rastSek    = (float)$oeeRow['rasttime'];
                    $prodTid    = $runtimeSek - $rastSek;
                    if ($prodTid > 0) {
                        // Genomsnittlig cykeltid ~60 sek (fallback)
                        $snittCykel = 60;
                        try {
                            $cRow = $this->pdo->query(
                                "SELECT AVG(cykel_tid) FROM rebotling_ibc WHERE DATE(datum) = CURDATE() AND cykel_tid > 0"
                            )->fetchColumn();
                            if ($cRow > 0) $snittCykel = (float)$cRow;
                        } catch (Exception) { /* ignorera */ }
                        $maxMojlig = $prodTid / $snittCykel;
                        if ($maxMojlig > 0) {
                            $oeePct = round(($ibcOk / $maxMojlig) * 100, 1);
                        }
                    }
                }
            } catch (Exception $e) { error_log('getAllLinesStatus OEE: ' . $e->getMessage()); }

            $lines[] = [
                'id'              => 'rebotling',
                'namn'            => 'Rebotling',
                'kor'             => $kor,
                'senaste_data_min'=> $senaste_data_min,
                'ibc_idag'        => $ibcIdag,
                'oee_pct'         => $oeePct,
                'mal_pct'         => $malPct,
                'ej_i_drift'      => false
            ];
        } catch (Exception $e) {
            error_log('getAllLinesStatus rebotling: ' . $e->getMessage());
            $lines[] = [
                'id'         => 'rebotling',
                'namn'       => 'Rebotling',
                'kor'        => false,
                'ej_i_drift' => false
            ];
        }

        // --- Tvättlinje ---
        $lines[] = $this->getOtherLineStatus('tvattlinje', 'Tvättlinje', 'tvattlinje_settings');

        // --- Såglinje ---
        $lines[] = $this->getOtherLineStatus('saglinje', 'Såglinje', 'saglinje_settings');

        // --- Klassificeringslinje ---
        $lines[] = $this->getOtherLineStatus('klassificeringslinje', 'Klassificeringslinje', 'klassificeringslinje_settings');

        echo json_encode(['success' => true, 'lines' => $lines], JSON_UNESCAPED_UNICODE);
    }


    private function getOtherLineStatus(string $id, string $namn, string $settingsTable): array {
        try {
            $tables = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = " . $this->pdo->quote($settingsTable)
            )->fetchColumn();

            if ((int)$tables === 0) {
                return ['id' => $id, 'namn' => $namn, 'kor' => false, 'ej_i_drift' => true];
            }
            // Tabell finns — försök hämta aktiv-status
            return ['id' => $id, 'namn' => $namn, 'kor' => false, 'ej_i_drift' => true];
        } catch (Exception $e) {
            error_log("getAllLinesStatus $id: " . $e->getMessage());
            return ['id' => $id, 'namn' => $namn, 'kor' => false, 'ej_i_drift' => true];
        }
    }

    // =========================================================
    // E-postnotifikationer — inställningar
    // =========================================================

    /**
     * Säkerställ att notification_emails + notification_config-kolumnerna finns i rebotling_settings.
     */

    private function ensureNotificationEmailsColumn(): void {
        try {
            $this->ensureSettingsTable();
            $col = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'notification_emails'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN notification_emails TEXT NULL DEFAULT NULL"
                );
            }

            // notification_config — JSON med enabled-toggle och händelsetyp-toggles
            $col2 = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'notification_config'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col2) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN notification_config TEXT NULL DEFAULT NULL"
                );
            }
        } catch (Exception $e) {
            error_log('ensureNotificationEmailsColumn: ' . $e->getMessage());
        }
    }

    /**
     * GET ?action=rebotling&run=notification-settings
     * Returnerar aktuella e-postadresser för notifikationer.
     */

    private function defaultNotificationConfig(): array {
        return [
            'enabled'           => false,
            'on_stopp'          => true,
            'on_low_oee'        => true,
            'on_cert_expiry'    => false,
            'on_maintenance'    => false,
            'on_shift_report'   => true,
        ];
    }


    public function getNotificationSettings(): void {
        try {
            $this->ensureNotificationEmailsColumn();
            $row = $this->pdo->query(
                "SELECT notification_emails, notification_config FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            $config = $this->defaultNotificationConfig();
            if (!empty($row['notification_config'])) {
                $saved = json_decode($row['notification_config'], true);
                if (is_array($saved)) {
                    $config = array_merge($config, $saved);
                }
            }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'notification_emails' => $row['notification_emails'] ?? '',
                    'config'              => $config,
                ],
            ]);
        } catch (Exception $e) {
            error_log('getNotificationSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta notifikationsinställningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=save-notification-settings
     * Sparar e-postadresser (semikolonseparerade) för brådskande notifikationer.
     */

    public function saveNotificationSettings(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->ensureNotificationEmailsColumn();

            $rawEmails = isset($data['notification_emails']) ? trim($data['notification_emails']) : '';
            // Tillåt komma eller semikolon som separator — normalisera till semikolon
            $rawEmails = str_replace(',', ';', $rawEmails);
            // Validera varje e-postadress
            $parts  = array_map('trim', explode(';', $rawEmails));
            $valid  = [];
            foreach ($parts as $email) {
                if ($email === '') continue;
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltig e-postadress: ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $valid[] = $email;
            }
            $normalized = implode(';', $valid);

            // Spara notification_config (händelsetyp-toggles + enabled)
            $defaults = $this->defaultNotificationConfig();
            $configInput = isset($data['config']) && is_array($data['config']) ? $data['config'] : [];
            $cleanedConfig = [];
            foreach (array_keys($defaults) as $key) {
                $cleanedConfig[$key] = isset($configInput[$key]) ? (bool)$configInput[$key] : $defaults[$key];
            }
            $configJson = json_encode($cleanedConfig);

            $stmt = $this->pdo->prepare(
                "UPDATE rebotling_settings SET notification_emails = ?, notification_config = ? WHERE id = 1"
            );
            $stmt->execute([$normalized, $configJson]);

            echo json_encode(['success' => true, 'message' => 'Notifikationsinställningar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('saveNotificationSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara notifikationsinställningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Hämta admin-e-postadresser från rebotling_settings.
     * Returnerar array med validerade e-postadresser.
     * Används av ShiftHandoverController vid brådskande notiser.
     */

    public function getAdminEmailsPublic(): array {
        try {
            $this->ensureNotificationEmailsColumn();
            $row = $this->pdo->query(
                "SELECT notification_emails FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            if (empty($row['notification_emails'])) {
                return [];
            }
            $parts  = array_map('trim', explode(';', $row['notification_emails']));
            $emails = [];
            foreach ($parts as $email) {
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
            return $emails;
        } catch (Exception $e) {
            error_log('RebotlingController getAdminEmailsPublic: ' . $e->getMessage());
            return [];
        }
    }


    // =========================================================
    // Personbästa per operatör vs teamrekord
    // GET ?action=rebotling&run=personal-bests
    // =========================================================

    public function getGoalHistory() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $days = intval($_GET['days'] ?? 180);
        if ($days < 1 || $days > 730) $days = 180;

        try {
            // Skapa tabellen om den inte finns
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rebotling_goal_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    goal_type VARCHAR(50) NOT NULL DEFAULT 'dagmal',
                    value INT NOT NULL,
                    changed_by VARCHAR(100),
                    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type_time (goal_type, changed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $stmt = $this->pdo->prepare("
                SELECT goal_type, value, changed_by, changed_at
                FROM rebotling_goal_history
                WHERE changed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY changed_at ASC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Om inga rader: returnera nuvarande värde som startpunkt
            if (empty($rows)) {
                $current = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1 LIMIT 1"
                )->fetchColumn();
                if ($current !== false) {
                    $rows = [[
                        'goal_type'  => 'dagmal',
                        'value'      => (int)$current,
                        'changed_by' => 'system',
                        'changed_at' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))
                    ]];
                }
            }

            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getGoalHistory: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }
    /**
     * GET ?action=rebotling&run=live-ranking-settings
     */

    public function getLiveRankingSettings(): void {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        try {
            $keys = ['lr_show_quality', 'lr_show_progress', 'lr_show_motto', 'lr_poll_interval', 'lr_title'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare("SELECT `key`, `value` FROM rebotling_settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => [
                'lr_show_quality'  => ($rows['lr_show_quality']  ?? '1') === '1',
                'lr_show_progress' => ($rows['lr_show_progress'] ?? '1') === '1',
                'lr_show_motto'    => ($rows['lr_show_motto']    ?? '1') === '1',
                'lr_poll_interval' => intval($rows['lr_poll_interval'] ?? 30),
                'lr_title'         => $rows['lr_title'] ?? 'Live Ranking',
            ]], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getLiveRankingSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=save-live-ranking-settings
     */

    public function saveLiveRankingSettings(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Ej behörig'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $settings = [
                'lr_show_quality'  => isset($body['lr_show_quality'])  ? ($body['lr_show_quality']  ? '1' : '0') : '1',
                'lr_show_progress' => isset($body['lr_show_progress']) ? ($body['lr_show_progress'] ? '1' : '0') : '1',
                'lr_show_motto'    => isset($body['lr_show_motto'])    ? ($body['lr_show_motto']    ? '1' : '0') : '1',
                'lr_poll_interval' => strval(max(10, min(120, intval($body['lr_poll_interval'] ?? 30)))),
                'lr_title'         => substr(strip_tags($body['lr_title'] ?? 'Live Ranking'), 0, 80),
            ];
            $stmt = $this->pdo->prepare("INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('saveLiveRankingSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=live-ranking-config
     * Hämtar KPI-kolumner, sortering och refresh-intervall för Live Ranking.
     */
    public function getLiveRankingConfig(): void {
        try {
            $keys = ['lrc_columns', 'lrc_sort_by', 'lrc_refresh_interval'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare("SELECT `key`, `value` FROM rebotling_settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $columns = json_decode($rows['lrc_columns'] ?? '{}', true);
            if (empty($columns)) {
                $columns = [
                    'ibc_per_hour'  => true,
                    'quality_pct'   => true,
                    'bonus_level'   => false,
                    'goal_progress' => true,
                    'ibc_today'     => true,
                ];
            }
            echo json_encode(['success' => true, 'data' => [
                'columns'          => $columns,
                'sort_by'          => $rows['lrc_sort_by'] ?? 'ibc_per_hour',
                'refresh_interval' => intval($rows['lrc_refresh_interval'] ?? 30),
            ]], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getLiveRankingConfig: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=set-live-ranking-config
     * Sparar KPI-kolumner, sortering och refresh-intervall för Live Ranking.
     */
    public function setLiveRankingConfig(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Ej behörig'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $columns = $body['columns'] ?? [
                'ibc_per_hour'  => true,
                'quality_pct'   => true,
                'bonus_level'   => false,
                'goal_progress' => true,
                'ibc_today'     => true,
            ];
            $sortBy = in_array($body['sort_by'] ?? '', ['ibc_per_hour', 'quality_pct', 'bonus_level', 'goal_progress', 'ibc_today'], true)
                ? $body['sort_by']
                : 'ibc_per_hour';
            $refreshInterval = max(10, min(120, intval($body['refresh_interval'] ?? 30)));

            $settings = [
                'lrc_columns'          => json_encode($columns),
                'lrc_sort_by'          => $sortBy,
                'lrc_refresh_interval' => strval($refreshInterval),
            ];
            $stmt = $this->pdo->prepare("INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('setLiveRankingConfig: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }


    public function createRecordNewsManual(): void {
        try {
            // Hämta dagens IBC-total
            $stmtToday = $this->pdo->query("
                SELECT COALESCE(SUM(dag_ibc), 0) AS idag_total
                FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS dag_ibc
                    FROM rebotling_ibc
                    WHERE DATE(datum) = CURDATE()
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $todayRow = $stmtToday->fetch(PDO::FETCH_ASSOC);
            $ibcIdag = (int)($todayRow['idag_total'] ?? 0);

            if ($ibcIdag <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ingen IBC-data för idag'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Hämta historiskt rekord (bästa dag före idag)
            $stmtRekord = $this->pdo->query("
                SELECT MAX(dag_total) AS rekord_ibc
                FROM (
                    SELECT DATE(datum) AS datum_rekord, SUM(shift_ibc) AS dag_total
                    FROM (
                        SELECT DATE(datum) AS datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE DATE(datum) < CURDATE()
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY datum_rekord
                ) AS per_day
            ");
            $rekordRow = $stmtRekord->fetch(PDO::FETCH_ASSOC);
            $rekordIbc = (int)($rekordRow['rekord_ibc'] ?? 0);

            $titel = 'Ny rekordag!';
            $body = 'Idag producerades ' . $ibcIdag . ' IBC — ett nytt rekord! '
                  . ($rekordIbc > 0 ? 'Förra rekordet var ' . $rekordIbc . ' IBC.' : 'Det är det bästa resultatet hittills!');

            $stmtInsert = $this->pdo->prepare("
                INSERT INTO news (title, body, category, pinned, published, priority, created_at, updated_at)
                VALUES (:title, :body, 'rekord', 1, 1, 5, NOW(), NOW())
            ");
            $stmtInsert->execute([
                ':title' => $titel,
                ':body'  => $body,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            echo json_encode([
                'success'   => true,
                'id'        => $newId,
                'ibc_idag'  => $ibcIdag,
                'rekord_ibc'=> $rekordIbc,
                'message'   => 'Rekordnyhet skapad!'
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('createRecordNewsManual: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ========== Logga underhållsåtgärd ==========

    public function saveMaintenanceLog(): void {
        try {
            $body = json_decode(file_get_contents('php://input'), true);
            $actionText = trim($body['action_text'] ?? '');
            if (strlen($actionText) === 0 || strlen($actionText) > 1000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Åtgärdstext saknas eller är för lång (max 1000 tecken)'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $userId = $_SESSION['user_id'] ?? null;

            // Försök att infoga i underhållslogg-tabellen om den finns
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO rebotling_maintenance_log (action_text, logged_by_user_id, logged_at)
                    VALUES (:action_text, :user_id, NOW())
                ");
                $stmt->execute([
                    ':action_text' => $actionText,
                    ':user_id'     => $userId,
                ]);
                echo json_encode(['success' => true, 'message' => 'Underhållsåtgärd sparad'], JSON_UNESCAPED_UNICODE);
            } catch (\Exception $tableErr) {
                // Tabellen finns inte ännu
                error_log('saveMaintenanceLog: rebotling_maintenance_log saknas: ' . $tableErr->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Logg-tabell ej konfigurerad'], JSON_UNESCAPED_UNICODE);
            }
        } catch (\Exception $e) {
            error_log('saveMaintenanceLog: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================================
    // Kassationsorsaksanalys
    // =========================================================================

    /**
     * GET ?action=rebotling&run=kassation-typer
     * Returnerar alla aktiva kassationsorsakstyper.
     */

    public function getGoalExceptions() {
        $month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])
            ? $_GET['month']
            : null;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT datum, justerat_mal, orsak
                 FROM produktionsmal_undantag
                 WHERE (:month IS NULL OR DATE_FORMAT(datum, '%Y-%m') = :month)
                 ORDER BY datum ASC"
            );
            $stmt->execute([':month' => $month]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exceptions = array_map(function ($r) {
                return [
                    'datum'        => $r['datum'],
                    'justerat_mal' => (int)$r['justerat_mal'],
                    'orsak'        => $r['orsak'],
                ];
            }, $rows);
            echo json_encode(['success' => true, 'exceptions' => $exceptions], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('getGoalExceptions: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta undantag'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=save-goal-exception
     * Body: {"datum":"YYYY-MM-DD","justerat_mal":40,"orsak":"..."}
     */

    public function saveGoalException() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $datum = $data['datum'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat (YYYY-MM-DD krävs)'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $mal = intval($data['justerat_mal'] ?? 0);
        if ($mal <= 0 || $mal >= 10000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Justerat mål måste vara mellan 1 och 9999'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $orsak = isset($data['orsak']) ? mb_substr(trim((string)$data['orsak']), 0, 255) : null;
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $stmt = $this->pdo->prepare(
                'INSERT INTO produktionsmal_undantag (datum, justerat_mal, orsak, skapad_av)
                 VALUES (:datum, :mal, :orsak, :uid)
                 ON DUPLICATE KEY UPDATE
                   justerat_mal  = VALUES(justerat_mal),
                   orsak         = VALUES(orsak),
                   uppdaterad_at = NOW()'
            );
            $stmt->execute([
                ':datum' => $datum,
                ':mal'   => $mal,
                ':orsak' => $orsak,
                ':uid'   => $userId,
            ]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('saveGoalException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara undantag'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=delete-goal-exception
     * Body: {"datum":"YYYY-MM-DD"}
     */

    public function deleteGoalException() {
        $data = json_decode(file_get_contents('php://input'), true);
        $datum = $data['datum'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $stmt = $this->pdo->prepare('DELETE FROM produktionsmal_undantag WHERE datum = :datum');
            $stmt->execute([':datum' => $datum]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('deleteGoalException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort undantag'], JSON_UNESCAPED_UNICODE);
        }
    }


    /**
     * GET ?action=rebotling&run=oee-components&days=14
     * Returnerar dagliga trendlinjer för OEE-komponenterna Tillgänglighet och Kvalitet.
     */

    public function getServiceStatus(): void {
        try {
            // Hämta inställningar från rebotling_settings (key-value)
            $keys = ['service_interval_ibc', 'last_service_ibc_total', 'last_service_at', 'last_service_note'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare("SELECT `key`, `value` FROM rebotling_settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $serviceInterval     = max(1, intval($rows['service_interval_ibc']   ?? 5000));
            $lastServiceIbcTotal = intval($rows['last_service_ibc_total']         ?? 0);
            $lastServiceAt       = $rows['last_service_at']                       ?? null;
            $lastServiceNote     = $rows['last_service_note']                     ?? null;

            // Beräkna total IBC producerat (MAX per skift, summerat)
            $stmtIbc = $this->pdo->query(
                "SELECT COALESCE(SUM(shift_max), 0) AS total FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS shift_max
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) t"
            );
            $totalIbc = intval($stmtIbc->fetchColumn());

            $ibcSedanService = $totalIbc - $lastServiceIbcTotal;
            $ibcKvar         = $serviceInterval - $ibcSedanService;
            $pctKvar         = $serviceInterval > 0
                ? round(max(0, $ibcKvar) / $serviceInterval * 100, 1)
                : 0;

            if ($pctKvar > 25) {
                $status = 'ok';
            } elseif ($pctKvar > 10) {
                $status = 'warning';
            } else {
                $status = 'danger';
            }

            echo json_encode([
                'success'                => true,
                'service_interval'       => $serviceInterval,
                'last_service_at'        => $lastServiceAt,
                'last_service_note'      => $lastServiceNote,
                'ibc_total'              => $totalIbc,
                'ibc_sedan_service'      => $ibcSedanService,
                'ibc_kvar_till_service'  => $ibcKvar,
                'pct_kvar'               => $pctKvar,
                'status'                 => $status,
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getServiceStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=reset-service  (kräver admin)
     */

    public function resetService(): void {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $note = isset($body['note']) ? substr(strip_tags($body['note']), 0, 255) : '';

            // Hämta aktuell total IBC
            $stmtIbc = $this->pdo->query(
                "SELECT COALESCE(SUM(shift_max), 0) AS total FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS shift_max
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) t"
            );
            $totalIbc = intval($stmtIbc->fetchColumn());

            // Uppdatera last_service_ibc_total, last_service_at, last_service_note
            $settings = [
                'last_service_ibc_total' => strval($totalIbc),
                'last_service_at'        => date('Y-m-d H:i:s'),
                'last_service_note'      => $note,
            ];
            $stmt = $this->pdo->prepare(
                "INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
            );
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }

            echo json_encode([
                'success'            => true,
                'message'            => 'Service registrerad',
                'ibc_total_at_reset' => $totalIbc,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController resetService: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=save-service-interval  (kräver admin)
     */

    public function saveServiceInterval(): void {
        try {
            $body    = json_decode(file_get_contents('php://input'), true) ?? [];
            $interval = intval($body['service_interval_ibc'] ?? 5000);
            if ($interval < 100 || $interval > 50000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Intervall måste vara mellan 100 och 50 000 IBC.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $stmt = $this->pdo->prepare(
                "INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
            );
            $stmt->execute(['service_interval_ibc', strval($interval)]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController saveServiceInterval: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }



}
