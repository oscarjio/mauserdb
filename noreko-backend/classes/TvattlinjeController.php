<?php
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
            } elseif ($action === 'status') {
                $this->getRunningStatus();
            } else {
                $this->getLiveStats();
            }
            return;
        }

        if ($method === 'POST' && $action === 'admin-settings') {
            $this->saveAdminSettings();
            return;
        }

        // Om ingen matchande metod finns
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
    }

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
            // hourlyTarget = antal IBC per timme för att nå dagens mål
            $hourlyTarget = $ibcTarget / 8;

            // Beräkna total runtime för idag
            // Runtime räknas som summan av alla perioder när maskinen var running
            $totalRuntimeMinutes = 0;
            
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
                $now = new DateTime();
                
                foreach ($todayEntries as $entry) {
                    $entryTime = new DateTime($entry['datum']);
                    $isRunning = (bool)($entry['running'] ?? false);
                    
                    // Om maskinen startar (running=1) och vi inte redan räknar en period
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $entryTime;
                    }
                    // Om maskinen stoppar (running=0) och vi räknar en period
                    elseif (!$isRunning && $lastRunningStart !== null) {
                        $diff = $lastRunningStart->diff($entryTime);
                        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $totalRuntimeMinutes += $periodMinutes;
                        $lastRunningStart = null;
                    }
                }
                
                // Om maskinen fortfarande kör (senaste entry är running=1)
                if ($lastRunningStart !== null) {
                    $lastEntryTime = new DateTime($todayEntries[count($todayEntries) - 1]['datum']);
                    // Räkna från när maskinen startade till senaste entry
                    $diff = $lastRunningStart->diff($lastEntryTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                    
                    // Lägg till tiden från senaste entry till nu
                    $diffSinceLast = $lastEntryTime->diff($now);
                    $minutesSinceLastUpdate = ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                    $totalRuntimeMinutes += $minutesSinceLastUpdate;
                }
            }

            // Beräkna produktionsprocent
            // Produktion = (antal cykler * 60) / (total runtime i minuter) / hourlyTarget * 100
            $productionPercentage = 0;
            if ($totalRuntimeMinutes > 0 && $ibcToday > 0 && $hourlyTarget > 0) {
                // Beräkna faktisk produktion per timme: (antal cykler * 60) / runtime i minuter
                $actualProductionPerHour = ($ibcToday * 60) / $totalRuntimeMinutes;
                // Jämför med mål per timme för att få procent
                $productionPercentage = round(($actualProductionPerHour / $hourlyTarget) * 100, 1);
            }

            // Hämta senaste utetemperatur
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
                // Ignorera fel vid hämtning av väderdata
                error_log('Kunde inte hämta väderdata: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'ibcToday' => $ibcToday,
                    'ibcTarget' => $ibcTarget,
                    'productionPercentage' => $productionPercentage,
                    'utetemperatur' => $utetemperatur
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik: ' . $e->getMessage()
            ]);
        }
    }

    private function getRunningStatus() {
        try {
            // Hämta senaste running status för tvattlinje
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
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta status: ' . $e->getMessage()
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
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta admin-inställningar: ' . $e->getMessage()
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

            // Kontrollera om settings existerar
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM tvattlinje_settings");
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                // Uppdatera befintlig rad
                $stmt = $this->pdo->prepare("UPDATE tvattlinje_settings SET antal_per_dag = ?, updated_at = NOW() WHERE id = 1");
                $stmt->execute([$data['antal_per_dag']]);
            } else {
                // Skapa ny rad
                $stmt = $this->pdo->prepare("INSERT INTO tvattlinje_settings (antal_per_dag) VALUES (?)");
                $stmt->execute([$data['antal_per_dag']]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Inställningar sparade',
                'data' => [
                    'antal_per_dag' => $data['antal_per_dag']
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte spara inställningar: ' . $e->getMessage()
            ]);
        }
    }

    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT * FROM tvattlinje_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            // Returnera standardvärden om inga settings finns
            return [
                'id' => 1,
                'antal_per_dag' => 150
            ];
        }
        
        return $settings;
    }
}
