<?php
require_once __DIR__ . '/AuditController.php';

class KlassificeringslinjeController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            if ($action === 'settings') {
                $this->getSettings();
            } elseif ($action === 'weekday-goals') {
                $this->getWeekdayGoals();
            } elseif ($action === 'system-status') {
                $this->getSystemStatus();
            } elseif ($action === 'today-snapshot') {
                $this->getTodaySnapshot();
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
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod eller action'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================
    // Settings (klassificeringslinje_settings tabell)
    // =========================================================

    private function ensureSettingsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS klassificeringslinje_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting VARCHAR(100) NOT NULL UNIQUE,
                value VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['dagmal',       '120'],
            ['takt_mal',     '20'],
            ['skift_start',  '06:00'],
            ['skift_slut',   '22:00'],
        ];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO klassificeringslinje_settings (setting, value) VALUES (?, ?)");
        foreach ($defaults as [$k, $v]) {
            $stmt->execute([$k, $v]);
        }
    }

    public function getSettings() {
        try {
            $this->ensureSettingsTable();
            $rows = $this->pdo->query("SELECT setting, value FROM klassificeringslinje_settings ORDER BY id")->fetchAll(\PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta inställningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function setSettings() {
        $data    = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $allowed = ['dagmal', 'takt_mal', 'skift_start', 'skift_slut'];
        try {
            $this->ensureSettingsTable();
            $stmt = $this->pdo->prepare(
                "INSERT INTO klassificeringslinje_settings (setting, value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP"
            );
            foreach ($allowed as $key) {
                if (!isset($data[$key])) continue;
                $value = trim($data[$key]);
                if (in_array($key, ['skift_start', 'skift_slut'])) {
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) continue;
                } else {
                    $value = (string)max(0, intval($value));
                }
                $stmt->execute([$key, $value]);
            }
            AuditLogger::log($this->pdo, 'update_klassificeringslinje_settings', 'klassificeringslinje_settings', null,
                json_encode(array_intersect_key($data, array_flip($allowed)), JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true, 'message' => 'Inställningar sparade'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::setSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara inställningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Systemstatus — returnerar null-värden tills linjen är i drift
    // =========================================================

    public function getSystemStatus() {
        try {
            $plcLastSeen   = null;
            $plcAgeMinutes = null;

            // Försök hämta senaste PLC-signal
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM klassificeringslinje_ibc")->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['last_ping']) {
                    $plcLastSeen   = $row['last_ping'];
                    $lastDt        = new \DateTime($plcLastSeen);
                    $now           = new \DateTime();
                    $diff          = $now->diff($lastDt);
                    $plcAgeMinutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
                }
            } catch (\Exception $e) { /* ignorera — tabellen kanske inte finns */ }

            // Lösnummer
            $losnummer = null;
            try {
                $row = $this->pdo->query("SELECT ibc_count FROM klassificeringslinje_ibc ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
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
                    'plc_last_seen'   => $plcLastSeen,
                    'plc_age_minutes' => $plcAgeMinutes,
                    'db_status'       => $dbStatus,
                    'losnummer'       => $losnummer,
                    'note'            => 'Linjen ej i drift',
                    'server_time'     => date('Y-m-d H:i:s'),
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getSystemStatus: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta systemstatus'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Veckodagsmål
    // =========================================================

    private function ensureWeekdayGoalsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS klassificeringslinje_weekday_goals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                weekday TINYINT NOT NULL UNIQUE COMMENT '0=Måndag, 6=Söndag',
                mal INT NOT NULL DEFAULT 120,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [[0,120],[1,120],[2,120],[3,120],[4,120],[5,80],[6,0]];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO klassificeringslinje_weekday_goals (weekday, mal) VALUES (?, ?)");
        foreach ($defaults as [$wd, $mal]) {
            $stmt->execute([$wd, $mal]);
        }
    }

    public function getWeekdayGoals() {
        try {
            $this->ensureWeekdayGoalsTable();
            $rows = $this->pdo->query("SELECT weekday, mal FROM klassificeringslinje_weekday_goals ORDER BY weekday")->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodagsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function setWeekdayGoals() {
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
            $stmt = $this->pdo->prepare("UPDATE klassificeringslinje_weekday_goals SET mal = ? WHERE weekday = ?");
            foreach ($goals as $item) {
                $wd  = intval($item['weekday'] ?? -1);
                $mal = max(0, intval($item['mal'] ?? 0));
                if ($wd >= 0 && $wd <= 6) {
                    $stmt->execute([$mal, $wd]);
                }
            }
            AuditLogger::log($this->pdo, 'update_klassificeringslinje_weekday_goals', 'klassificeringslinje_weekday_goals', null,
                'goals=' . count($goals));
            echo json_encode(['success' => true, 'message' => 'Veckodagsmål sparade'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::setWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara veckodagsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Today-snapshot — dagens produktionsöversikt
    // =========================================================

    private function getTodaySnapshot() {
        try {
            // Summera IBC idag via delta per skiftraknare
            $ibcIdag = 0;
            $senasteDatum = null;
            try {
                $stmt = $this->pdo->query("
                    SELECT SUM(delta_ok) AS ibc_idag
                    FROM (
                        SELECT MAX(ibc_count) - MIN(ibc_count) AS delta_ok
                        FROM klassificeringslinje_ibc
                        WHERE DATE(datum) = CURDATE()
                        GROUP BY skiftraknare
                    ) x
                ");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $ibcIdag = max(0, (int)($row['ibc_idag'] ?? 0));
            } catch (\Exception $e) { /* tabell kanske inte finns */ }

            // Senaste PLC-record — kontrollera om linjen kör
            $isRunning = false;
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ts FROM klassificeringslinje_ibc")->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['last_ts']) {
                    $senasteDatum = $row['last_ts'];
                    $lastDt = new \DateTime($row['last_ts']);
                    $now    = new \DateTime();
                    $diffMin = ($now->getTimestamp() - $lastDt->getTimestamp()) / 60;
                    $isRunning = ($diffMin < 15);
                }
            } catch (\Exception $e) { /* ignorera */ }

            // Dagsmål från settings
            $dagmal = 0;
            try {
                $this->ensureSettingsTable();
                $val = $this->pdo->query("SELECT value FROM klassificeringslinje_settings WHERE setting = 'dagmal'")->fetchColumn();
                $dagmal = (int)($val ?? 0);
            } catch (\Exception $e) { /* ignorera */ }

            // Tomt om ingen data idag
            if ($ibcIdag === 0 && !$isRunning) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'empty'         => true,
                        'ibc_idag'      => 0,
                        'dagmal'        => $dagmal,
                        'is_running'    => false,
                        'pct_of_goal'   => 0,
                        'senaste_datum' => $senasteDatum,
                    ]
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $pctOfGoal = ($dagmal > 0) ? round(($ibcIdag / $dagmal) * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => [
                    'empty'         => false,
                    'ibc_idag'      => $ibcIdag,
                    'dagmal'        => $dagmal,
                    'is_running'    => $isRunning,
                    'pct_of_goal'   => $pctOfGoal,
                    'senaste_datum' => $senasteDatum,
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getTodaySnapshot: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta dagens snapshot'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Live stats (stub — returnerar tomma värden tills linjen är i drift)
    // =========================================================

    private function getLiveStats() {
        try {
            $ibcToday  = 0;
            $ibcTarget = 120;

            try {
                $settings  = $this->pdo->query("SELECT setting, value FROM klassificeringslinje_settings")->fetchAll(\PDO::FETCH_KEY_PAIR);
                $ibcTarget = (int)($settings['dagmal'] ?? 120);
            } catch (\Exception $e) { /* ignorera */ }

            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM klassificeringslinje_ibc WHERE DATE(datum) = CURDATE()");
                $stmt->execute();
                $ibcToday = (int)$stmt->fetchColumn();
            } catch (\Exception $e) { /* ignorera */ }

            echo json_encode([
                'success' => true,
                'data' => [
                    'ibcToday'            => $ibcToday,
                    'ibcTarget'           => $ibcTarget,
                    'productionPercentage'=> 0,
                    'utetemperatur'       => null,
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getLiveStats: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta statistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Running status (stub)
    // =========================================================

    private function getRunningStatus() {
        echo json_encode([
            'success' => true,
            'data' => [
                'running'    => false,
                'lastUpdate' => null,
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================
    // Statistics (stub)
    // =========================================================

    private function getStatistics() {
        echo json_encode([
            'success' => true,
            'data' => [
                'cycles'       => [],
                'onoff_events' => [],
                'summary' => [
                    'total_cycles'           => 0,
                    'avg_production_percent' => 0,
                    'avg_cycle_time'         => 0,
                    'target_cycle_time'      => 3,
                    'total_runtime_hours'    => 0,
                    'days_with_production'   => 0,
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================
    // Skiftrapport daglig KPI — hämtar data från line_skiftrapporter
    // =========================================================

    private function getReport() {
        $datum = $_GET['datum'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $rows = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ls.*, u.name as user_name
                    FROM line_skiftrapporter ls
                    LEFT JOIN users u ON ls.user_id = u.id
                    WHERE ls.line = 'klassificeringslinje' AND DATE(ls.datum) = :datum
                    ORDER BY ls.datum ASC
                ");
                $stmt->execute(['datum' => $datum]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) { /* tabell finns kanske inte */ }

            $prevDatum = date('Y-m-d', strtotime($datum . ' -1 day'));
            $prevRows  = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT ls.*
                    FROM line_skiftrapporter ls
                    WHERE ls.line = 'klassificeringslinje' AND DATE(ls.datum) = :datum
                    ORDER BY ls.datum ASC
                ");
                $stmt->execute(['datum' => $prevDatum]);
                $prevRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log('KlassificeringslinjeController::getReport prevRows: ' . $e->getMessage());
            }

            $totalOk   = 0;
            $totalEjOk = 0;
            foreach ($rows as $r) {
                $totalOk   += (int)($r['antal_ok']    ?? 0);
                $totalEjOk += (int)($r['antal_ej_ok'] ?? 0);
            }
            $totalIbc    = $totalOk + $totalEjOk;
            $kvalitetPct = $totalIbc > 0 ? round(($totalOk / $totalIbc) * 100, 1) : 0;

            $prevOk   = 0;
            $prevEjOk = 0;
            foreach ($prevRows as $r) {
                $prevOk   += (int)($r['antal_ok']    ?? 0);
                $prevEjOk += (int)($r['antal_ej_ok'] ?? 0);
            }
            $prevIbc  = $prevOk + $prevEjOk;
            $deltaIbc = $totalIbc - $prevIbc;

            $runtimeMinutes = 0;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT MIN(datum) as first_ts, MAX(datum) as last_ts, COUNT(*) as cnt
                    FROM klassificeringslinje_ibc
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
            } catch (\Exception $e) {
                error_log('KlassificeringslinjeController::getReport runtime: ' . $e->getMessage());
            }

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
                    'ibc_per_hour'    => $ibcPerHour,
                    'delta_ibc'       => $deltaIbc,
                    'prev_ibc'        => $prevIbc,
                    'skift_count'     => count($rows),
                    'skift_data'      => $rows,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getReport: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'empty'   => true,
                'message' => 'Linjen ej i drift',
                'datum'   => $datum,
                'data'    => [
                    'total_ibc' => 0, 'total_ok' => 0, 'total_ej_ok' => 0,
                    'kvalitet_pct' => 0, 'runtime_minutes' => 0,
                    'ibc_per_hour' => 0, 'delta_ibc' => 0, 'prev_ibc' => 0,
                    'skift_count' => 0, 'skift_data' => [],
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // OEE-trend — daglig statistik baserad på skiftrapporter
    // =========================================================

    private function getOeeTrend() {
        $dagar = max(7, min(365, intval($_GET['dagar'] ?? 30)));

        try {
            $rows = [];
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)                   AS dag,
                        SUM(antal_ok)                 AS total_ok,
                        SUM(antal_ej_ok)              AS total_ej_ok,
                        SUM(antal_ok + antal_ej_ok)   AS total_ibc,
                        COUNT(*)                      AS skift_count
                    FROM line_skiftrapporter
                    WHERE line = 'klassificeringslinje'
                      AND datum >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                    GROUP BY DATE(datum)
                    ORDER BY dag ASC
                ");
                $stmt->execute(['dagar' => $dagar]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) { /* tabell finns kanske inte */ }

            if (empty($rows)) {
                echo json_encode([
                    'success' => true,
                    'empty'   => true,
                    'message' => 'Linjen ej i drift',
                    'data'    => [],
                    'summary' => [
                        'total_ibc'     => 0,
                        'snitt_per_dag' => 0,
                        'snitt_oee_pct' => 0,
                        'basta_dag'     => null,
                        'basta_ibc'     => 0,
                    ],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $dagData     = [];
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
                    'dag'         => $r['dag'],
                    'total_ibc'   => $tot,
                    'total_ok'    => $ok,
                    'total_ej_ok' => (int)$r['total_ej_ok'],
                    'oee_pct'     => $oee,
                    'skift_count' => (int)$r['skift_count'],
                ];
            }

            $antalDagar  = count($dagData);
            $snittPerDag = $antalDagar > 0 ? round($totalIbcSum / $antalDagar, 1) : 0;
            $snittOee    = $antalDagar > 0 ? round($oeeSum / $antalDagar, 1)      : 0;

            echo json_encode([
                'success' => true,
                'empty'   => false,
                'data'    => $dagData,
                'summary' => [
                    'total_ibc'     => $totalIbcSum,
                    'snitt_per_dag' => $snittPerDag,
                    'snitt_oee_pct' => $snittOee,
                    'basta_dag'     => $bestaDag,
                    'basta_ibc'     => $bestaIbc,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('KlassificeringslinjeController::getOeeTrend: ' . $e->getMessage());
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
