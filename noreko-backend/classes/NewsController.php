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
        } elseif ($method === 'GET' && $run === 'admin-list') {
            $this->adminList();
        } elseif ($method === 'POST' && $run === 'create') {
            $this->create();
        } elseif ($method === 'POST' && $run === 'update') {
            $this->update();
        } elseif ($method === 'POST' && $run === 'delete') {
            $this->delete();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte']);
        }
    }

    private function requireAdmin(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Ej behörig']);
            return false;
        }
        return true;
    }

    private function adminList() {
        if (!$this->requireAdmin()) return;

        try {
            $stmt = $this->pdo->query("
                SELECT id, title, body, category, pinned, published, priority, created_at, updated_at
                FROM news
                ORDER BY priority DESC, created_at DESC
            ");
            $rows = $stmt->fetchAll();
            $news = array_map(function($row) {
                return [
                    'id'         => (int)$row['id'],
                    'title'      => $row['title'],
                    'body'       => $row['body'],
                    'category'   => $row['category'],
                    'pinned'     => (bool)$row['pinned'],
                    'published'  => (bool)$row['published'],
                    'priority'   => isset($row['priority']) ? (int)$row['priority'] : 3,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }, $rows);
            echo json_encode(['success' => true, 'news' => $news]);
        } catch (Exception $e) {
            error_log("NewsController adminList: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    private function create() {
        if (!$this->requireAdmin()) return;

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt JSON']);
            return;
        }

        $title     = trim($body['title'] ?? '');
        $content   = trim($body['content'] ?? '');
        $category  = trim($body['category'] ?? 'info');
        $pinned    = !empty($body['pinned']) ? 1 : 0;
        $published = !empty($body['published']) ? 1 : 0;
        $priority  = max(1, min(5, intval($body['priority'] ?? 3)));

        $allowedCategories = ['produktion', 'bonus', 'system', 'info', 'viktig', 'rekord', 'hog_oee', 'certifiering', 'urgent'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'info';
        }

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rubrik krävs']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO news (title, body, category, pinned, published, priority, created_at, updated_at)
                VALUES (:title, :body, :category, :pinned, :published, :priority, NOW(), NOW())
            ");
            $stmt->execute([
                ':title'     => $title,
                ':body'      => $content,
                ':category'  => $category,
                ':pinned'    => $pinned,
                ':published' => $published,
                ':priority'  => $priority,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            error_log("NewsController create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    private function update() {
        if (!$this->requireAdmin()) return;

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt JSON']);
            return;
        }

        $id        = intval($body['id'] ?? 0);
        $title     = trim($body['title'] ?? '');
        $content   = trim($body['content'] ?? '');
        $category  = trim($body['category'] ?? 'info');
        $pinned    = !empty($body['pinned']) ? 1 : 0;
        $published = !empty($body['published']) ? 1 : 0;
        $priority  = max(1, min(5, intval($body['priority'] ?? 3)));

        $allowedCategories = ['produktion', 'bonus', 'system', 'info', 'viktig', 'rekord', 'hog_oee', 'certifiering', 'urgent'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'info';
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt ID']);
            return;
        }

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rubrik krävs']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                UPDATE news
                SET title = :title, body = :body, category = :category,
                    pinned = :pinned, published = :published, priority = :priority, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':title'     => $title,
                ':body'      => $content,
                ':category'  => $category,
                ':pinned'    => $pinned,
                ':published' => $published,
                ':priority'  => $priority,
                ':id'        => $id,
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("NewsController update: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    private function delete() {
        if (!$this->requireAdmin()) return;

        $body = json_decode(file_get_contents('php://input'), true);
        $id   = intval($body['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt ID']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM news WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("NewsController delete: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    private function getEvents() {
        $antal = min(20, max(1, intval($_GET['antal'] ?? 15)));
        $filterCategory = trim($_GET['category'] ?? '');
        $allowedCategories = ['produktion', 'bonus', 'system', 'info', 'viktig', 'rekord', 'hog_oee', 'certifiering', 'urgent'];

        $events = [];

        // 0. Manuella nyheter från news-tabellen (inkl. pinned + priority)
        try {
            $sql = "
                SELECT id,
                       title,
                       body,
                       category,
                       pinned,
                       COALESCE(priority, 3) AS priority,
                       DATE(created_at) AS event_datum,
                       created_at AS event_datetime,
                       NULL AS value
                FROM news
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND (published IS NULL OR published = 1)
                ORDER BY pinned DESC, priority DESC, created_at DESC
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
                    'priority' => isset($row['priority']) ? (int)$row['priority'] : 3,
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
            'produktion'   => 'chart-bar',
            'bonus'        => 'trophy',
            'system'       => 'cog',
            'info'         => 'info-circle',
            'viktig'       => 'exclamation-triangle',
            'rekord'       => 'trophy',
            'hog_oee'      => 'rocket',
            'certifiering' => 'certificate',
            'urgent'       => 'exclamation-circle',
        ];
        return $map[$category] ?? 'bell';
    }
}
