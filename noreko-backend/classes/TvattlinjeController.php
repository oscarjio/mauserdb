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
        $action = $_GET['run'] ?? '';
        
        if ($method === 'GET') {
            if ($action === 'admin-settings') {
                $this->getAdminSettings();
            } elseif ($action === 'settings') {
                $this->getSettings();
            } elseif ($action === 'weekday-goals') {
                $this->getWeekdayGoals();
            } elseif ($action === 'system-status') {
                $this->getSystemStatus();
            } elseif ($action === 'status') {
                $this->getRunningStatus();
            } elseif ($action === 'statistics') {
                $this->getStatistics();
            } elseif ($action === 'report') {
                $this->getReport();
            } elseif ($action === 'oee-trend') {
                $this->getOeeTrend();
            } else {
                $this->getLiveStats();
            }
            return;
        }

        if ($method === 'POST') {
            if ($action === 'admin-settings') {
                session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
                    return;
                }
                $this->saveAdminSettings();
                return;
            }

            if ($action === 'settings') {
                session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
                    return;
                }
                $this->setSettings();
                return;
            }

            if ($action === 'weekday-goals') {
                session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
                    return;
                }
                $this->setWeekdayGoals();
                return;
            }
        }

        // Om ingen matchande metod finns
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
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
        foreach ($defaults as [$k, $v]) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO tvattlinje_settings (setting, value) VALUES (?, ?)");
            $stmt->execute([$k, $v]);
        }
    }

    private function getSettings() {
        try {
            $this->ensureSettingsTable();
            $rows = $this->pdo->query("SELECT setting, value FROM tvattlinje_settings ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            error_log('TvattlinjeController getSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta inställningar']);
        }
    }

    private function setSettings() {
        $data = json_decode(file_get_contents('php://input'), true);
        $allowed = ['dagmal', 'takt_mal', 'skift_start', 'skift_slut'];
        try {
            $this->ensureSettingsTable();
            $stmt = $this->pdo->prepare("INSERT INTO tvattlinje_settings (setting, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP");
            foreach ($allowed as $key) {
                if (!isset($data[$key])) continue;
                $value = trim($data[$key]);
                // Validera tidsfält
                if (in_array($key, ['skift_start', 'skift_slut'])) {
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) continue;
                } else {
                    $value = (string)max(0, intval($value));
                }
                $stmt->execute([$key, $value]);
            }
            AuditLogger::log($this->pdo, 'update_tvattlinje_settings_v2', 'tvattlinje_settings', null,
                json_encode(array_intersect_key($data, array_flip($allowed))));
            echo json_encode(['success' => true, 'message' => 'Inställningar sparade']);
        } catch (\Exception $e) {
            error_log('TvattlinjeController setSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara inställningar']);
        }
    }

    // =========================================================
    // Systemstatus — returnerar null-värden tills linjen är i drift
    // =========================================================

    private function getSystemStatus() {
        try {
            $plcLastSeen   = null;
            $plcAgeMinutes = null;

            // Försök hämta senaste PLC-signal (tvattlinje_ibc)
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM tvattlinje_ibc")->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['last_ping']) {
                    $plcLastSeen   = $row['last_ping'];
                    $lastDt        = new \DateTime($plcLastSeen);
                    $now           = new \DateTime();
                    $diff          = $now->diff($lastDt);
                    $plcAgeMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
                }
            } catch (\Exception $e) { /* ignorera — tabellen kanske inte finns */ }

            // Lösnummer: försök om tabellen finns
            $losnummer = null;
            try {
                $row = $this->pdo->query("SELECT ibc_count FROM tvattlinje_ibc ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                $losnummer = $row ? (int)$row['ibc_count'] : null;
            } catch (\Exception $e) { /* ignorera */ }

            // Databas OK
            $dbStatus = 'ok';
            try {
                $this->pdo->query("SELECT 1");
            } catch (\Exception $e) {
                $dbStatus = 'error';
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'plc_last_seen'    => $plcLastSeen,
                    'plc_age_minutes'  => $plcAgeMinutes,
                    'db_status'        => $dbStatus,
                    'losnummer'        => $losnummer,
                    'note'             => 'Linjen ej i drift',
                    'server_time'      => date('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            error_log('TvattlinjeController getSystemStatus: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta systemstatus']);
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
        foreach ($defaults as [$wd, $mal]) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO tvattlinje_weekday_goals (weekday, mal) VALUES (?, ?)");
            $stmt->execute([$wd, $mal]);
        }
    }

    private function getWeekdayGoals() {
        try {
            $this->ensureWeekdayGoalsTable();
            $rows = $this->pdo->query("SELECT weekday, mal FROM tvattlinje_weekday_goals ORDER BY weekday")->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            error_log('TvattlinjeController getWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodagsmål']);
        }
    }

    private function setWeekdayGoals() {
        $data  = json_decode(file_get_contents('php://input'), true);
        $goals = $data['goals'] ?? [];
        if (!is_array($goals)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data']);
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
            echo json_encode(['success' => true, 'message' => 'Veckodagsmål sparade']);
        } catch (\Exception $e) {
            error_log('TvattlinjeController setWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara veckodagsmål']);
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
                WHERE DATE(datum) = CURDATE()
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
            $now = new DateTime();

            // Hämta alla rader för idag sorterade efter datum
            $stmt = $this->pdo->prepare('
                SELECT datum, running
                FROM tvattlinje_onoff
                WHERE DATE(datum) = CURDATE()
                ORDER BY datum ASC
            ');
            $stmt->execute();
            $todayEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($todayEntries) > 0) {
                $lastRunningStart = null;
                
                foreach ($todayEntries as $entry) {
                    $entryTime = new DateTime($entry['datum']);
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
                    $lastEntryTime = new DateTime($todayEntries[count($todayEntries) - 1]['datum']);
                    $diff = $lastRunningStart->diff($lastEntryTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                    
                    $diffSinceLast = $lastEntryTime->diff($now);
                    $minutesSinceLastUpdate = ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                    $totalRuntimeMinutes += $minutesSinceLastUpdate;
                }
            }

            if ($totalRuntimeMinutes == 0 && $ibcToday > 0) {
                $stmt = $this->pdo->prepare(
                    'SELECT MIN(datum) as first_ibc, MAX(datum) as last_ibc FROM tvattlinje_ibc WHERE DATE(datum) = CURDATE()'
                );
                $stmt->execute();
                $ibcRange = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ibcRange && $ibcRange['first_ibc']) {
                    $firstIbc = new DateTime($ibcRange['first_ibc']);
                    $lastIbc  = new DateTime($ibcRange['last_ibc']);
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
                error_log('Kunde inte hämta väderdata: ' . $e->getMessage());
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
            
            echo json_encode($response);
        } catch (\Throwable $e) {
            error_log('Kunde inte hämta statistik (tvattlinje getLiveStats): ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ]);
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
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta status (tvattlinje getRunningStatus): ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta status'
            ]);
        }
    }

    private function getAdminSettings() {
        try {
            $settings = $this->loadSettings();
            
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta admin-inställningar (tvattlinje): ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta admin-inställningar'
            ]);
        }
    }

    private function saveAdminSettings() {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            if (!isset($data['antal_per_dag'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'antal_per_dag är obligatoriskt'
                ]);
                return;
            }

            $antal_per_dag = intval($data['antal_per_dag']);
            $timtakt       = isset($data['timtakt'])    ? intval($data['timtakt'])         : 20;
            $skiftlangd    = isset($data['skiftlangd']) ? floatval($data['skiftlangd'])     : 8.0;

            try {
                $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN timtakt INT NOT NULL DEFAULT 20");
            } catch (\Exception $e) { /* Kolumn finns redan */ }
            try {
                $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0");
            } catch (\Exception $e) { /* Kolumn finns redan */ }

            $stmt = $this->pdo->query("SELECT COUNT(*) FROM tvattlinje_settings");
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                $stmt = $this->pdo->prepare(
                    "UPDATE tvattlinje_settings SET antal_per_dag = ?, timtakt = ?, skiftlangd = ?, updated_at = NOW() WHERE id = 1"
                );
                $stmt->execute([$antal_per_dag, $timtakt, $skiftlangd]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO tvattlinje_settings (antal_per_dag, timtakt, skiftlangd) VALUES (?, ?, ?)"
                );
                $stmt->execute([$antal_per_dag, $timtakt, $skiftlangd]);
            }

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
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte spara inställningar (tvattlinje): ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte spara inställningar'
            ]);
        }
    }

    private function loadSettings() {
        try {
            $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN timtakt INT NOT NULL DEFAULT 20");
        } catch (\Exception $e) { /* Kolumn finns redan */ }
        try {
            $this->pdo->exec("ALTER TABLE tvattlinje_settings ADD COLUMN skiftlangd DECIMAL(4,1) NOT NULL DEFAULT 8.0");
        } catch (\Exception $e) { /* Kolumn finns redan */ }

        $stmt = $this->pdo->query("SELECT * FROM tvattlinje_settings LIMIT 1");
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
        $settings['skiftlangd'] = isset($settings['skiftlangd']) ? floatval($settings['skiftlangd']) : 8.0;

        return $settings;
    }

    private function getStatistics() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d');
            $end = $_GET['end'] ?? date('Y-m-d');

            $stmt = $this->pdo->prepare('
                SELECT 
                    i.datum,
                    i.ibc_count,
                    i.s_count,
                    100 as produktion_procent,
                    1 as skiftraknare,
                    TIMESTAMPDIFF(MINUTE, 
                        LAG(i.datum) OVER (ORDER BY i.datum), 
                        i.datum
                    ) as cycle_time,
                    3 as target_cycle_time
                FROM tvattlinje_ibc i
                WHERE DATE(i.datum) BETWEEN :start AND :end
                ORDER BY i.datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $rawCycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $cycles = [];
            foreach ($rawCycles as $cycle) {
                if ($cycle['cycle_time'] !== null && $cycle['cycle_time'] > 0 && $cycle['cycle_time'] <= 15) {
                    $cycles[] = $cycle;
                } else if ($cycle['cycle_time'] !== null && $cycle['cycle_time'] > 15) {
                    $cycle['cycle_time'] = null;
                    $cycles[] = $cycle;
                }
            }

            $stmt = $this->pdo->prepare('
                SELECT 
                    datum,
                    running,
                    runtime_today
                FROM tvattlinje_onoff 
                WHERE DATE(datum) BETWEEN :start AND :end
                ORDER BY datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $onoff_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_cycles = count($cycles);
            $avg_production_percent = 100;
            $avg_cycle_time = 0;
            $total_runtime_hours = 0;
            $target_cycle_time = 3;
            
            if ($total_cycles > 0) {
                $cycle_times = array_filter(array_column($cycles, 'cycle_time'), function($val) {
                    return $val !== null && $val > 0;
                });
                
                if (count($cycle_times) > 0) {
                    $avg_cycle_time = array_sum($cycle_times) / count($cycle_times);
                }
            }

            $totalRuntimeMinutes = 0;
            
            if (count($onoff_events) > 0) {
                $lastRunningStart = null;
                
                foreach ($onoff_events as $event) {
                    $eventTime = new DateTime($event['datum']);
                    $isRunning = (bool)($event['running'] ?? false);
                    
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $eventTime;
                    } elseif (!$isRunning && $lastRunningStart !== null) {
                        $diff = $lastRunningStart->diff($eventTime);
                        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $totalRuntimeMinutes += $periodMinutes;
                        $lastRunningStart = null;
                    }
                }
                
                if ($lastRunningStart !== null) {
                    $lastEventTime = new DateTime($onoff_events[count($onoff_events) - 1]['datum']);
                    $diff = $lastRunningStart->diff($lastEventTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                }
            }
            
            if ($totalRuntimeMinutes == 0 && $total_cycles > 0) {
                $firstCycle = new DateTime($cycles[0]['datum']);
                $lastCycle = new DateTime($cycles[count($cycles) - 1]['datum']);
                $diff = $firstCycle->diff($lastCycle);
                $totalRuntimeMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
            }
            
            $total_runtime_hours = $totalRuntimeMinutes / 60;

            $unique_dates = array_unique(array_map(function($cycle) {
                return date('Y-m-d', strtotime($cycle['datum']));
            }, $cycles));
            $days_with_production = count($unique_dates);

            echo json_encode([
                'success' => true,
                'data' => [
                    'cycles' => $cycles,
                    'onoff_events' => $onoff_events,
                    'summary' => [
                        'total_cycles' => $total_cycles,
                        'avg_production_percent' => round($avg_production_percent, 1),
                        'avg_cycle_time' => round($avg_cycle_time, 1),
                        'target_cycle_time' => round($target_cycle_time, 1),
                        'total_runtime_hours' => round($total_runtime_hours, 1),
                        'days_with_production' => $days_with_production
                    ]
                ]
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta statistik (tvattlinje getStatistics): ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ]);
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
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
            return;
        }

        try {
            // Hämta skiftrapporter för datumet via line_skiftrapporter tabellen
            $rows = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ls.*, u.name as user_name
                    FROM line_skiftrapporter ls
                    LEFT JOIN users u ON ls.user_id = u.id
                    WHERE ls.line = 'tvattlinje' AND DATE(ls.datum) = :datum
                    ORDER BY ls.datum ASC
                ");
                $stmt->execute(['datum' => $datum]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Tabell finns inte eller fel — returnera tom data
            }

            // Föregående dags data för delta-beräkning
            $prevDatum = date('Y-m-d', strtotime($datum . ' -1 day'));
            $prevRows  = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ls.*
                    FROM line_skiftrapporter ls
                    WHERE ls.line = 'tvattlinje' AND DATE(ls.datum) = :datum
                    ORDER BY ls.datum ASC
                ");
                $stmt->execute(['datum' => $prevDatum]);
                $prevRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {}

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
                    WHERE DATE(datum) = :datum
                ");
                $stmt->execute(['datum' => $datum]);
                $ibcRange = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($ibcRange && $ibcRange['cnt'] > 0 && $ibcRange['first_ts']) {
                    $first = new \DateTime($ibcRange['first_ts']);
                    $last  = new \DateTime($ibcRange['last_ts']);
                    $diff  = $first->diff($last);
                    $runtimeMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
                    if ($runtimeMinutes < 1 && $ibcRange['cnt'] > 0) $runtimeMinutes = 5;
                }
            } catch (\Exception $e) {}

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
            ]);
        } catch (\Exception $e) {
            error_log('TvattlinjeController getReport: ' . $e->getMessage());
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
            ]);
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

            // Försök hämta daglig data från line_skiftrapporter
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)               AS dag,
                        SUM(antal_ok)             AS total_ok,
                        SUM(antal_ej_ok)          AS total_ej_ok,
                        SUM(antal_ok + antal_ej_ok) AS total_ibc,
                        COUNT(*)                  AS skift_count
                    FROM line_skiftrapporter
                    WHERE line = 'tvattlinje'
                      AND datum >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                    GROUP BY DATE(datum)
                    ORDER BY dag ASC
                ");
                $stmt->execute(['dagar' => $dagar]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Tabell finns inte — returnera empty
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
                ]);
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
            ]);
        } catch (\Exception $e) {
            error_log('TvattlinjeController getOeeTrend: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'empty'   => true,
                'message' => 'Linjen ej i drift',
                'data'    => [],
                'summary' => [
                    'total_ibc' => 0, 'snitt_per_dag' => 0,
                    'snitt_oee_pct' => 0, 'basta_dag' => null, 'basta_ibc' => 0,
                ],
            ]);
        }
    }

}