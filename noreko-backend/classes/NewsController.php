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
        $antal = min(20, max(1, intval($_GET['antal'] ?? 15)));
        $filterCategory = trim($_GET['category'] ?? '');
        $allowedCategories = ['produktion', 'bonus', 'system', 'info', 'viktig'];

        $events = [];

        // 0. Manuella nyheter från news-tabellen (inkl. pinned)
        try {
            $sql = "
                SELECT id,
                       title,
                       body,
                       category,
                       pinned,
                       DATE(created_at) AS event_datum,
                       created_at AS event_datetime,
                       NULL AS value
                FROM news
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY pinned DESC, created_at DESC
                LIMIT 10
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch()) {
                $events[] = [
                    'id'       => (int)$row['id'],
                    'typ'      => 'news_' . $row['category'],
                    'datum'    => $row['event_datum'],
                    'datetime' => $row['event_datetime'],
                    'text'     => ($row['title'] ? $row['title'] . ': ' : '') . $row['body'],
                    'ikon'     => $this->ikonForCategory($row['category']),
                    'category' => $row['category'],
                    'pinned'   => (bool)$row['pinned'],
                ];
            }
        } catch (Exception $e) {
            error_log("NewsController manual news: " . $e->getMessage());
        }

        // 1. Rekordag — bästa produktionsdagen någonsin, om den inträffade de senaste 30 dagarna
        try {
            $sql = "
                SELECT 'rekordag' AS typ,
                       DATE(datum) AS event_datum,
                       DATE_FORMAT(DATE(datum),'%Y-%m-%d 12:00:00') AS event_datetime,
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
                    'id'       => null,
                    'typ'      => 'rekordag',
                    'datum'    => $row['event_datum'],
                    'datetime' => $row['event_datetime'],
                    'text'     => '🏆 ' . $row['text'],
                    'ikon'     => 'trophy',
                    'category' => 'produktion',
                    'pinned'   => false,
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
                       DATE_FORMAT(DATE(datum),'%Y-%m-%d 12:00:00') AS event_datetime,
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
                        'id'       => null,
                        'typ'      => 'hog_oee',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datetime'],
                        'text'     => '🚀 ' . $row['text'],
                        'ikon'     => 'rocket',
                        'category' => 'produktion',
                        'pinned'   => false,
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
                       DATE_FORMAT(oc.certified_at,'%Y-%m-%d %H:%i:%s') AS event_datetime,
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
                        'id'       => null,
                        'typ'      => 'certifiering',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datetime'],
                        'text'     => '📋 ' . $row['text'],
                        'ikon'     => 'certificate',
                        'category' => 'info',
                        'pinned'   => false,
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
                       DATE_FORMAT(skapad_tid,'%Y-%m-%d %H:%i:%s') AS event_datetime,
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
                        'id'       => null,
                        'typ'      => 'urgent_note',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datetime'],
                        'text'     => '⚠️ ' . $row['text'],
                        'ikon'     => 'exclamation-triangle',
                        'category' => 'viktig',
                        'pinned'   => false,
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
                       DATE_FORMAT(DATE(datum),'%Y-%m-%d 12:00:00') AS event_datetime,
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
                        'id'       => null,
                        'typ'      => 'produktion',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datetime'],
                        'text'     => $row['text'],
                        'ikon'     => 'chart-bar',
                        'category' => 'produktion',
                        'pinned'   => false,
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController produktion: " . $e->getMessage());
        }

        // Filtrera på kategori om angiven
        if ($filterCategory && in_array($filterCategory, $allowedCategories, true)) {
            $events = array_filter($events, function($e) use ($filterCategory) {
                return ($e['category'] ?? '') === $filterCategory;
            });
            $events = array_values($events);
        }

        // Sortera: pinned först, sedan nyast datetime
        usort($events, function($a, $b) {
            $pinnedDiff = (int)($b['pinned'] ?? 0) - (int)($a['pinned'] ?? 0);
            if ($pinnedDiff !== 0) return $pinnedDiff;
            return strcmp($b['datetime'] ?? $b['datum'], $a['datetime'] ?? $a['datum']);
        });

        // Ta bort dubbletter (samma typ och datum), men behåll alla pinned
        $seen = [];
        $unique = [];
        foreach ($events as $e) {
            if (!empty($e['pinned'])) {
                $unique[] = $e;
                continue;
            }
            $key = $e['typ'] . '|' . $e['datum'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $e;
            }
        }

        $events = array_slice($unique, 0, $antal);

        echo json_encode(['success' => true, 'events' => $events]);
    }

    private function ikonForCategory(string $category): string {
        $map = [
            'produktion' => 'chart-bar',
            'bonus'      => 'trophy',
            'system'     => 'cog',
            'info'       => 'info-circle',
            'viktig'     => 'exclamation-triangle',
        ];
        return $map[$category] ?? 'bell';
    }
}
