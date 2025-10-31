<?php
class RebotlingController {
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
        // TODO: Hämta verklig data från PLC/DB
        // Placeholdervärden för nu
        $nowHour = (int)date('G');
        $working = $nowHour >= 6 && $nowHour <= 18;
        $rebotlingThisHour = $working ? rand(2, 16) : rand(12, 15);
        $rebotlingToday = rand(40, 85);
        $hourlyTarget = 15;
        $rebotlingTarget = 120;

        echo json_encode([
            'success' => true,
            'data' => [
                'rebotlingToday' => $rebotlingToday,
                'rebotlingTarget' => $rebotlingTarget,
                'rebotlingThisHour' => $rebotlingThisHour,
                'hourlyTarget' => $hourlyTarget
            ]
        ]);
    }

    private function getRunningStatus() {
        try {
            // Hämta senaste running status för rebotling
            $stmt = $this->pdo->prepare('
                SELECT running, datum
                FROM rebotling_onoff 
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
            // Hämta admin-inställningar från databasen eller returnera standardvärden
            $settings = [
                'rebotlingTarget' => 1000,
                'hourlyTarget' => 50,
                'systemSettings' => [
                    'autoStart' => false,
                    'maintenanceMode' => false,
                    'alertThreshold' => 80
                ]
            ];

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
            // TODO: Spara inställningar i databasen
            // För nu bara returnera success
            
            echo json_encode([
                'success' => true,
                'message' => 'Inställningar sparade',
                'data' => $data
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte spara inställningar: ' . $e->getMessage()
            ]);
        }
    }
}
