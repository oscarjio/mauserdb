<?php
require_once __DIR__ . '/AuditController.php';

class SaglinjeController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['run'] ?? '';

        if ($method === 'GET') {
            if ($action === 'settings') {
                $this->getSettings();
            } elseif ($action === 'weekday-goals') {
                $this->getWeekdayGoals();
            } elseif ($action === 'system-status') {
                $this->getSystemStatus();
            } elseif ($action === 'status') {
                $this->getRunningStatus();
            } elseif ($action === 'statistics') {
                $this->getStatistics();
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
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
                    return;
                }
                $this->setSettings();
                return;
            }

            if ($action === 'weekday-goals') {
                if (session_status() === PHP_SESSION_NONE) session_start();
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
                    return;
                }
                $this->setWeekdayGoals();
                return;
            }
        }

        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
    }

    // =========================================================
    // Key-value settings (saglinje_settings tabell)
    // =========================================================

    private function ensureSettingsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS saglinje_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting VARCHAR(100) NOT NULL UNIQUE,
                value VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['dagmal',      '50'],
            ['takt_mal',    '10'],
            ['skift_start', '06:00'],
            ['skift_slut',  '22:00'],
        ];
        foreach ($defaults as [$k, $v]) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO saglinje_settings (setting, value) VALUES (?, ?)");
            $stmt->execute([$k, $v]);
        }
    }

    private function getSettings() {
        try {
            $this->ensureSettingsTable();
            $rows = $this->pdo->query("SELECT setting, value FROM saglinje_settings ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            error_log('SaglinjeController getSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta inställningar']);
        }
    }

    private function setSettings() {
        $data    = json_decode(file_get_contents('php://input'), true);
        $allowed = ['dagmal', 'takt_mal', 'skift_start', 'skift_slut'];
        try {
            $this->ensureSettingsTable();
            $stmt = $this->pdo->prepare(
                "INSERT INTO saglinje_settings (setting, value) VALUES (?, ?)
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
            AuditLogger::log($this->pdo, 'update_saglinje_settings', 'saglinje_settings', null,
                json_encode(array_intersect_key($data, array_flip($allowed))));
            echo json_encode(['success' => true, 'message' => 'Inställningar sparade']);
        } catch (\Exception $e) {
            error_log('SaglinjeController setSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara inställningar']);
        }
    }

    // =========================================================
    // Systemstatus — returnerar null-värden, linjen ej i drift
    // =========================================================

    private function getSystemStatus() {
        try {
            $plcLastSeen   = null;
            $plcAgeMinutes = null;

            // Försök hämta senaste PLC-signal
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM saglinje_ibc")->fetch(\PDO::FETCH_ASSOC);
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
                $row = $this->pdo->query("SELECT ibc_count FROM saglinje_ibc ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
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
            ]);
        } catch (\Exception $e) {
            error_log('SaglinjeController getSystemStatus: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta systemstatus']);
        }
    }

    // =========================================================
    // Veckodagsmål
    // =========================================================

    private function ensureWeekdayGoalsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS saglinje_weekday_goals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                weekday TINYINT NOT NULL UNIQUE COMMENT '0=Måndag, 6=Söndag',
                mal INT NOT NULL DEFAULT 50,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [[0,50],[1,50],[2,50],[3,50],[4,50],[5,30],[6,0]];
        foreach ($defaults as [$wd, $mal]) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO saglinje_weekday_goals (weekday, mal) VALUES (?, ?)");
            $stmt->execute([$wd, $mal]);
        }
    }

    private function getWeekdayGoals() {
        try {
            $this->ensureWeekdayGoalsTable();
            $rows = $this->pdo->query("SELECT weekday, mal FROM saglinje_weekday_goals ORDER BY weekday")->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            error_log('SaglinjeController getWeekdayGoals: ' . $e->getMessage());
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
            $stmt = $this->pdo->prepare("UPDATE saglinje_weekday_goals SET mal = ? WHERE weekday = ?");
            foreach ($goals as $item) {
                $wd  = intval($item['weekday'] ?? -1);
                $mal = max(0, intval($item['mal'] ?? 0));
                if ($wd >= 0 && $wd <= 6) {
                    $stmt->execute([$mal, $wd]);
                }
            }
            AuditLogger::log($this->pdo, 'update_saglinje_weekday_goals', 'saglinje_weekday_goals', null,
                'goals=' . count($goals));
            echo json_encode(['success' => true, 'message' => 'Veckodagsmål sparade']);
        } catch (\Exception $e) {
            error_log('SaglinjeController setWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara veckodagsmål']);
        }
    }

    // =========================================================
    // Befintliga metoder (live + statistik)
    // =========================================================

    private function getRunningStatus() {
        try {
            $stmt = $this->pdo->prepare('
                SELECT running, datum
                FROM saglinje_onoff
                ORDER BY datum DESC
                LIMIT 1
            ');
            $stmt->execute();
            $result    = $stmt->fetch(PDO::FETCH_ASSOC);
            $isRunning = $result && isset($result['running']) ? (bool)$result['running'] : false;
            $lastUpdate = $result['datum'] ?? null;

            echo json_encode([
                'success' => true,
                'data' => [
                    'running'    => $isRunning,
                    'lastUpdate' => $lastUpdate
                ]
            ]);
        } catch (\Exception $e) {
            error_log('SaglinjeController getRunningStatus: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta status']);
        }
    }

    private function getLiveStats() {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) FROM saglinje_ibc WHERE DATE(datum) = CURDATE()
            ');
            $stmt->execute();
            $ibcToday = (int)$stmt->fetchColumn();

            // Hämta dagsmål från settings
            $this->ensureSettingsTable();
            $dagmal = (int)($this->pdo
                ->query("SELECT value FROM saglinje_settings WHERE setting = 'dagmal'")->fetchColumn() ?? 50);

            echo json_encode([
                'success' => true,
                'data' => [
                    'ibcToday'  => $ibcToday,
                    'ibcTarget' => $dagmal,
                ]
            ]);
        } catch (\Exception $e) {
            error_log('SaglinjeController getLiveStats: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta statistik']);
        }
    }

    private function getStatistics() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d');
            $end   = $_GET['end']   ?? date('Y-m-d');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');

            $stmt = $this->pdo->prepare('
                SELECT datum, ibc_count
                FROM saglinje_ibc
                WHERE DATE(datum) BETWEEN :start AND :end
                ORDER BY datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'cycles'  => $cycles,
                    'summary' => [
                        'total_cycles' => count($cycles),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            error_log('SaglinjeController getStatistics: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta statistik']);
        }
    }
}
