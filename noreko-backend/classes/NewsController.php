<?php
class NewsController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run = $_GET['run'] ?? '';

        if ($method === 'GET' && $run === 'events') {
            $this->getEvents();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte']);
        }
    }

    private function getEvents() {
        $antal = min(20, max(1, intval($_GET['antal'] ?? 10)));
        $events = [];

        // 1. Rekordag — bästa produktionsdagen någonsin, om den inträffade de senaste 30 dagarna
        try {
            $sql = "
                SELECT 'rekordag' AS typ,
                       DATE(datum) AS event_datum,
                       MAX(ibc_ok) AS value,
                       CONCAT('Rekordag! ', DATE_FORMAT(DATE(datum),'%d %b'), ': ', MAX(ibc_ok), ' IBC — nytt dagrekord!') AS text
                FROM rebotling_ibc
                WHERE DATE(datum) = (
                    SELECT DATE(datum)
                    FROM rebotling_ibc
                    GROUP BY DATE(datum)
                    ORDER BY MAX(ibc_ok) DESC
                    LIMIT 1
                )
                  AND DATE(datum) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LIMIT 1
            ";
            $stmt = $this->pdo->query($sql);
            $row = $stmt->fetch();
            if ($row && $row['event_datum']) {
                $events[] = [
                    'typ'   => 'rekordag',
                    'datum' => $row['event_datum'],
                    'text'  => '🏆 ' . $row['text'],
                    'ikon'  => 'trophy',
                ];
            }
        } catch (Exception $e) {
            error_log("NewsController rekordag: " . $e->getMessage());
        }

        // 2. Hög OEE-dag — OEE >= 90% de senaste 14 dagarna
        try {
            $sql = "
                SELECT 'hog_oee' AS typ,
                       DATE(datum) AS event_datum,
                       ROUND(MAX(oee_pct), 1) AS value,
                       CONCAT('Utmärkt dag! ', DATE_FORMAT(DATE(datum),'%d %b'), ': OEE ', ROUND(MAX(oee_pct),1), '% — över 90%!') AS text
                FROM rebotling_ibc
                WHERE DATE(datum) >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                GROUP BY DATE(datum)
                HAVING MAX(oee_pct) >= 90
                ORDER BY event_datum DESC
                LIMIT 3
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch()) {
                if ($row['event_datum']) {
                    $events[] = [
                        'typ'   => 'hog_oee',
                        'datum' => $row['event_datum'],
                        'text'  => '🚀 ' . $row['text'],
                        'ikon'  => 'rocket',
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController hog_oee: " . $e->getMessage());
        }

        // 3. Certifieringar — nya de senaste 14 dagarna
        try {
            $sql = "
                SELECT 'certifiering' AS typ,
                       DATE(oc.certified_at) AS event_datum,
                       NULL AS value,
                       CONCAT('Ny certifiering: ', o.name, ' certifierad för ', oc.line_name,
                              IF(oc.expires_at IS NOT NULL,
                                 CONCAT(' — giltig till ', DATE_FORMAT(oc.expires_at,'%d %b %Y')),
                                 '')) AS text
                FROM operator_certifications oc
                JOIN operators o ON o.operator_id = oc.operator_id
                WHERE oc.certified_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                ORDER BY oc.certified_at DESC
                LIMIT 3
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch()) {
                if ($row['event_datum']) {
                    $events[] = [
                        'typ'   => 'certifiering',
                        'datum' => $row['event_datum'],
                        'text'  => '📋 ' . $row['text'],
                        'ikon'  => 'certificate',
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController certifiering: " . $e->getMessage());
        }

        // 4. Skiftnotat med brådskande prioritet — senaste 3 dagarna
        try {
            $sql = "
                SELECT 'urgent_note' AS typ,
                       DATE(skapad_tid) AS event_datum,
                       NULL AS value,
                       CONCAT('Brådskande skiftnotat: ', LEFT(note, 80), IF(LENGTH(note) > 80, '...', '')) AS text
                FROM shift_handover
                WHERE priority = 'urgent'
                  AND skapad_tid >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                ORDER BY skapad_tid DESC
                LIMIT 2
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch()) {
                if ($row['event_datum']) {
                    $events[] = [
                        'typ'   => 'urgent_note',
                        'datum' => $row['event_datum'],
                        'text'  => '⚠️ ' . $row['text'],
                        'ikon'  => 'exclamation-triangle',
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController urgent_note: " . $e->getMessage());
        }

        // 5. Senaste produktionsdagar — alltid inkludera för att fylla upp flödet
        try {
            $sql = "
                SELECT 'produktion' AS typ,
                       DATE(datum) AS event_datum,
                       MAX(ibc_ok) AS value,
                       CONCAT('📊 ', DATE_FORMAT(DATE(datum),'%d %b %Y'), ': ', MAX(ibc_ok), ' IBC producerade') AS text
                FROM rebotling_ibc
                WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(datum)
                ORDER BY event_datum DESC
                LIMIT 5
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch()) {
                if ($row['event_datum']) {
                    $events[] = [
                        'typ'   => 'produktion',
                        'datum' => $row['event_datum'],
                        'text'  => $row['text'],
                        'ikon'  => 'chart-bar',
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController produktion: " . $e->getMessage());
        }

        // Sortera nyast först, ta bort dubbletter på samma typ+datum och begränsa antalet
        usort($events, function($a, $b) {
            return strcmp($b['datum'], $a['datum']);
        });

        // Ta bort dubbletter (samma typ och datum)
        $seen = [];
        $unique = [];
        foreach ($events as $e) {
            $key = $e['typ'] . '|' . $e['datum'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $e;
            }
        }

        $events = array_slice($unique, 0, $antal);

        echo json_encode(['success' => true, 'events' => $events]);
    }
}
