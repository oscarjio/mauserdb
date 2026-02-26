<?php
/**
 * RuntimeController - Hanterar rast-registrering för tvättlinje och rebotling
 * 
 * Används för att registrera när en rastingång är hög/låg på Shelly-pucken
 * och beräkna total rasttid per dag.
 */
class RuntimeController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['run'] ?? '';
        
        if ($method === 'GET') {
            if ($action === 'stats') {
                // Hämta statistik för en specifik linje
                $this->getBreakStats();
            } elseif ($action === 'today') {
                // Hämta dagens totala rasttid
                $this->getTodayBreakTime();
            } else {
                // Registrera rast från Shelly (GET request från webhook)
                $this->registerBreakFromShelly();
            }
            return;
        }

        if ($method === 'POST') {
            // POST endpoint för manuell registrering om behövs
            $this->registerBreak();
            return;
        }

        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
    }

    /**
     * Registrerar rast från Shelly webhook
     * GET parameters: line, rast (0 eller 1)
     */
    private function registerBreakFromShelly() {
        try {
            $line = $_GET['line'] ?? null;
            $rast = isset($_GET['rast']) ? (int)$_GET['rast'] : null;

            if (!$line || $rast === null) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Parametrar saknas: line och rast krävs'
                ]);
                return;
            }

            // Validera line
            $validLines = ['tvattlinje', 'rebotling'];
            if (!in_array($line, $validLines)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Ogiltig line. Måste vara: tvattlinje eller rebotling'
                ]);
                return;
            }

            // Hämta senaste status för att undvika duplicering
            $tableName = $line . '_runtime';
            $stmt = $this->pdo->prepare("
                SELECT rast_status 
                FROM $tableName 
                ORDER BY datum DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $lastStatus = $lastEntry ? (int)$lastEntry['rast_status'] : -1;

            // Endast spara om status har ändrats
            if ($lastStatus !== $rast) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO $tableName (datum, rast_status) 
                    VALUES (NOW(), ?)
                ");
                $stmt->execute([$rast]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Rast-status registrerad',
                    'data' => [
                        'line' => $line,
                        'rast_status' => $rast,
                        'changed' => true
                    ]
                ]);
            } else {
                // Status oförändrad, skippa
                echo json_encode([
                    'success' => true,
                    'message' => 'Status oförändrad',
                    'data' => [
                        'line' => $line,
                        'rast_status' => $rast,
                        'changed' => false
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte registrera rast: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Registrerar rast via POST
     */
    private function registerBreak() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $line = $data['line'] ?? null;
            $rastStatus = isset($data['rast_status']) ? (int)$data['rast_status'] : null;

            if (!$line || $rastStatus === null) {
                echo json_encode([
                    'success' => false,
                    'error' => 'line och rast_status krävs'
                ]);
                return;
            }

            $validLines = ['tvattlinje', 'rebotling'];
            if (!in_array($line, $validLines)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Ogiltig line'
                ]);
                return;
            }

            $tableName = $line . '_runtime';
            $stmt = $this->pdo->prepare("
                INSERT INTO $tableName (datum, rast_status) 
                VALUES (NOW(), ?)
            ");
            $stmt->execute([$rastStatus]);

            echo json_encode([
                'success' => true,
                'message' => 'Rast registrerad',
                'data' => [
                    'line' => $line,
                    'rast_status' => $rastStatus
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte registrera rast: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Hämtar dagens totala rasttid för en linje
     * GET parameters: line
     */
    private function getTodayBreakTime() {
        try {
            $line = $_GET['line'] ?? null;

            if (!$line) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Parameter line saknas'
                ]);
                return;
            }

            $validLines = ['tvattlinje', 'rebotling'];
            if (!in_array($line, $validLines)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Ogiltig line'
                ]);
                return;
            }

            $totalBreakMinutes = $this->calculateBreakTime($line, 'today');

            echo json_encode([
                'success' => true,
                'data' => [
                    'line' => $line,
                    'date' => date('Y-m-d'),
                    'total_break_minutes' => round($totalBreakMinutes, 2),
                    'total_break_hours' => round($totalBreakMinutes / 60, 2)
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta rasttid: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Hämtar detaljerad statistik för raster
     * GET parameters: line, period (today, week, month)
     */
    private function getBreakStats() {
        try {
            $line = $_GET['line'] ?? null;
            $period = $_GET['period'] ?? 'today';

            if (!$line) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Parameter line saknas'
                ]);
                return;
            }

            $validLines = ['tvattlinje', 'rebotling'];
            if (!in_array($line, $validLines)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Ogiltig line'
                ]);
                return;
            }

            $totalBreakMinutes = $this->calculateBreakTime($line, $period);

            echo json_encode([
                'success' => true,
                'data' => [
                    'line' => $line,
                    'period' => $period,
                    'total_break_minutes' => round($totalBreakMinutes, 2),
                    'total_break_hours' => round($totalBreakMinutes / 60, 2)
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Beräknar total rasttid för en period
     * 
     * @param string $line Linjens namn (tvattlinje eller rebotling)
     * @param string $period Tidsperiod (today, week, month)
     * @return float Total rasttid i minuter
     */
    private function calculateBreakTime($line, $period = 'today') {
        $tableName = $line . '_runtime';
        
        // Bestäm datumfilter baserat på period
        $dateFilter = '';
        switch ($period) {
            case 'week':
                $dateFilter = 'DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $dateFilter = 'DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
            case 'today':
            default:
                $dateFilter = 'DATE(datum) = CURDATE()';
                break;
        }

        // Hämta alla rader för perioden sorterade efter datum
        $stmt = $this->pdo->prepare("
            SELECT datum, rast_status
            FROM $tableName 
            WHERE $dateFilter
            ORDER BY datum ASC
        ");
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($entries) === 0) {
            return 0;
        }

        $totalBreakMinutes = 0;
        $lastBreakStart = null;
        $now = new DateTime();
        
        foreach ($entries as $entry) {
            $entryTime = new DateTime($entry['datum']);
            $isOnBreak = (bool)($entry['rast_status'] ?? false);
            
            // Om rasten startar (rast_status=1) och vi inte redan räknar en period
            if ($isOnBreak && $lastBreakStart === null) {
                $lastBreakStart = $entryTime;
            }
            // Om rasten slutar (rast_status=0) och vi räknar en period
            elseif (!$isOnBreak && $lastBreakStart !== null) {
                $diff = $lastBreakStart->diff($entryTime);
                $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $totalBreakMinutes += $periodMinutes;
                $lastBreakStart = null;
            }
        }
        
        // Om rasten fortfarande pågår (senaste entry är rast_status=1)
        if ($lastBreakStart !== null) {
            $lastEntryTime = new DateTime($entries[count($entries) - 1]['datum']);
            
            // För "today": räkna till nu
            // För andra perioder: räkna bara till senaste entry
            if ($period === 'today') {
                // Räkna från rast-start till senaste entry
                $diff = $lastBreakStart->diff($lastEntryTime);
                $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $totalBreakMinutes += $periodMinutes;
                
                // Lägg till tiden från senaste entry till nu
                $diffSinceLast = $lastEntryTime->diff($now);
                $minutesSinceLastUpdate = ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                $totalBreakMinutes += $minutesSinceLastUpdate;
            } else {
                // För längre perioder, räkna bara till senaste entry
                $diff = $lastBreakStart->diff($lastEntryTime);
                $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $totalBreakMinutes += $periodMinutes;
            }
        }

        return $totalBreakMinutes;
    }
}
