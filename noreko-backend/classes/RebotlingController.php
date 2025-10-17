<?php
class RebotlingController {
    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $this->getLiveStats();
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['data'])) {
            echo json_encode(['success' => true, 'message' => 'Data mottagen', 'received' => $data['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ingen data mottagen']);
        }
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
}
