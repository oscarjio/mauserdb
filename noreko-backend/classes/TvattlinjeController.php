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
        
        if ($method === 'GET' && $action === 'status') {
            $this->getRunningStatus();
            return;
        }

        // Om ingen matchande metod finns
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
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
}
