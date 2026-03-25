<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class NewsController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run = trim($_GET['run'] ?? '');

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
            echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function requireAdmin(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            error_log('NewsController::requireAdmin: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', role=' . ($_SESSION['role'] ?? 'none'));
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Ej behörig'], JSON_UNESCAPED_UNICODE);
            return false;
        }
        // Kontrollera session-timeout (inaktivitet)
        if (!AuthHelper::checkSessionTimeout()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
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
                LIMIT 500
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            echo json_encode(['success' => true, 'news' => $news], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("NewsController::adminList: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function create() {
        if (!$this->requireAdmin()) return;

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $title     = htmlspecialchars(trim($body['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $content   = htmlspecialchars(trim($body['content'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category  = htmlspecialchars(trim($body['category'] ?? 'info'), ENT_QUOTES, 'UTF-8');
        $pinned    = !empty($body['pinned']) ? 1 : 0;
        $published = !empty($body['published']) ? 1 : 0;
        $priority  = max(1, min(5, intval($body['priority'] ?? 3)));

        $allowedCategories = ['produktion', 'bonus', 'system', 'info', 'viktig', 'rekord', 'hog_oee', 'certifiering', 'urgent'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'info';
        }

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rubrik krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }
        if (mb_strlen($content) > 5000) {
            $content = mb_substr($content, 0, 5000);
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
            AuditLogger::log($this->pdo, 'create_news', 'news', $id,
                "Skapade nyhet: $title", null, ['title' => $title, 'category' => $category]);
            echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("NewsController::create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function update() {
        if (!$this->requireAdmin()) return;

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id        = intval($body['id'] ?? 0);
        $title     = htmlspecialchars(trim($body['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $content   = htmlspecialchars(trim($body['content'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category  = htmlspecialchars(trim($body['category'] ?? 'info'), ENT_QUOTES, 'UTF-8');
        $pinned    = !empty($body['pinned']) ? 1 : 0;
        $published = !empty($body['published']) ? 1 : 0;
        $priority  = max(1, min(5, intval($body['priority'] ?? 3)));

        $allowedCategories = ['produktion', 'bonus', 'system', 'info', 'viktig', 'rekord', 'hog_oee', 'certifiering', 'urgent'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'info';
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rubrik krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }
        if (mb_strlen($content) > 5000) {
            $content = mb_substr($content, 0, 5000);
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
            AuditLogger::log($this->pdo, 'update_news', 'news', $id,
                "Uppdaterade nyhet (ID: $id): $title", null, ['title' => $title, 'category' => $category]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("NewsController::update: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function delete() {
        if (!$this->requireAdmin()) return;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = intval($body['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM news WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::log($this->pdo, 'delete_news', 'news', $id, "Tog bort nyhet (ID: $id)");
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("NewsController::delete: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
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
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            error_log("NewsController::getEvents:manuella nyheter: " . $e->getMessage());
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
                WHERE datum >= (
                    SELECT best_day FROM (
                        SELECT DATE(datum) AS best_day
                        FROM rebotling_ibc
                        GROUP BY DATE(datum)
                        ORDER BY MAX(ibc_ok) DESC
                        LIMIT 1
                    ) AS bd
                )
                  AND datum < (
                    SELECT DATE_ADD(best_day, INTERVAL 1 DAY) FROM (
                        SELECT DATE(datum) AS best_day
                        FROM rebotling_ibc
                        GROUP BY DATE(datum)
                        ORDER BY MAX(ibc_ok) DESC
                        LIMIT 1
                    ) AS bd2
                )
                  AND datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LIMIT 1
            ";
            $stmt = $this->pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
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
            error_log("NewsController::getEvents:rekordag: " . $e->getMessage());
        }

        // 2. Hög OEE-dag — OEE >= 90% de senaste 14 dagarna
        try {
            $sql = "
                SELECT 'hog_oee' AS typ,
                       dag AS event_datum,
                       CONCAT(dag, ' 12:00:00') AS event_datetime,
                       oee_val AS value,
                       CONCAT('Utmärkt dag! ', DATE_FORMAT(dag,'%d %b'), ': OEE ', oee_val, '% — över 90%!') AS text
                FROM (
                    SELECT DATE(datum) AS dag,
                           ROUND(
                               CASE WHEN MAX(ibc_ok) + MAX(ibc_ej_ok) > 0
                                    THEN (MAX(ibc_ok) / (MAX(ibc_ok) + MAX(ibc_ej_ok))) * 100
                                    ELSE 0 END, 1) AS oee_val
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                      AND ibc_ok > 0
                    GROUP BY DATE(datum)
                ) AS dagdata
                WHERE oee_val >= 90
                ORDER BY event_datum DESC
                LIMIT 3
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            error_log("NewsController::getEvents:hog_oee: " . $e->getMessage());
        }

        // 3. Certifieringar — nya de senaste 14 dagarna
        try {
            $sql = "
                SELECT 'certifiering' AS typ,
                       DATE(oc.certified_date) AS event_datum,
                       DATE_FORMAT(oc.certified_date,'%Y-%m-%d %H:%i:%s') AS event_datetime,
                       NULL AS value,
                       CONCAT('Ny certifiering: ', o.name, ' certifierad för ', oc.line,
                              IF(oc.expires_date IS NOT NULL,
                                 CONCAT(' — giltig till ', DATE_FORMAT(oc.expires_date,'%d %b %Y')),
                                 '')) AS text
                FROM operator_certifications oc
                JOIN operators o ON o.number = oc.op_number
                WHERE oc.certified_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                ORDER BY oc.certified_date DESC
                LIMIT 3
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            error_log("NewsController::getEvents:certifiering: " . $e->getMessage());
        }

        // 4. Skiftnotat med brådskande prioritet — senaste 3 dagarna
        try {
            $sql = "
                SELECT 'urgent_note' AS typ,
                       DATE(created_at) AS event_datum,
                       DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') AS event_datetime,
                       NULL AS value,
                       CONCAT('Brådskande skiftnotat: ', LEFT(note, 80), IF(LENGTH(note) > 80, '...', '')) AS text
                FROM shift_handover
                WHERE priority = 'urgent'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                ORDER BY created_at DESC
                LIMIT 2
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            error_log("NewsController::getEvents:urgent_note: " . $e->getMessage());
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
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(datum)
                ORDER BY event_datum DESC
                LIMIT 5
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            error_log("NewsController::getEvents:produktion: " . $e->getMessage());
        }

        // 6. Produktionsrekord — dagens produktion slog bästa dagen senaste 30 dagarna
        try {
            $sql = "
                SELECT sub.event_datum, sub.today_ibc, prev.prev_best
                FROM (
                    SELECT DATE(t.datum) AS event_datum,
                           MAX(t.ibc_ok) AS today_ibc
                    FROM rebotling_ibc t
                    WHERE t.datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DATE(t.datum)
                ) sub
                LEFT JOIN (
                    SELECT DATE(datum) AS dag, MAX(ibc_ok) AS prev_best
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 37 DAY)
                      AND datum < CURDATE()
                    GROUP BY DATE(datum)
                ) prev ON prev.dag >= DATE_SUB(sub.event_datum, INTERVAL 30 DAY)
                      AND prev.dag < sub.event_datum
                GROUP BY sub.event_datum, sub.today_ibc
                HAVING sub.today_ibc > MAX(prev.prev_best)
                   AND MAX(prev.prev_best) IS NOT NULL
                ORDER BY sub.event_datum DESC
                LIMIT 3
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['event_datum']) {
                    $events[] = [
                        'id'       => null,
                        'typ'      => 'produktionsrekord',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datum'] . ' 18:00:00',
                        'text'     => '🏅 Produktionsrekord! ' . date('d M', strtotime($row['event_datum']) ?: time()) . ': '
                                      . $row['today_ibc'] . ' IBC — slog föregående bästa (' . $row['prev_best'] . ')!',
                        'ikon'     => 'medal',
                        'category' => 'rekord',
                        'pinned'   => false,
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController::getEvents:produktionsrekord: " . $e->getMessage());
        }

        // 7. OEE-milstolpe — WCM-klass (OEE >= 85%) senaste 14 dagarna
        try {
            $sql = "
                SELECT dag AS event_datum, oee_val
                FROM (
                    SELECT DATE(datum) AS dag,
                           ROUND(
                               CASE WHEN MAX(ibc_ok) + MAX(ibc_ej_ok) > 0
                                    THEN (MAX(ibc_ok) / (MAX(ibc_ok) + MAX(ibc_ej_ok))) * 100
                                    ELSE 0 END, 1) AS oee_val
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                      AND ibc_ok > 0
                    GROUP BY DATE(datum)
                ) AS dagdata
                WHERE oee_val >= 85 AND oee_val < 90
                ORDER BY event_datum DESC
                LIMIT 3
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['event_datum']) {
                    $events[] = [
                        'id'       => null,
                        'typ'      => 'oee_milstolpe',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datum'] . ' 16:00:00',
                        'text'     => '🎯 OEE-milstolpe! ' . date('d M', strtotime($row['event_datum']) ?: time()) . ': OEE '
                                      . $row['oee_val'] . '% — World Class Manufacturing-nivå!',
                        'ikon'     => 'bullseye',
                        'category' => 'produktion',
                        'pinned'   => false,
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController::getEvents:oee_milstolpe: " . $e->getMessage());
        }

        // 8. Bonus-milstolpe — nya bonusutbetalningar senaste 14 dagarna
        try {
            $sql = "
                SELECT bp.id,
                       o.name AS op_name,
                       bp.bonus_level,
                       bp.amount_sek,
                       bp.period_label,
                       DATE(bp.created_at) AS event_datum,
                       DATE_FORMAT(bp.created_at, '%Y-%m-%d %H:%i:%s') AS event_datetime
                FROM bonus_payouts bp
                JOIN operators o ON o.id = bp.op_id
                WHERE bp.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  AND bp.status IN ('pending', 'approved', 'paid')
                  AND bp.bonus_level IS NOT NULL
                  AND bp.bonus_level != 'none'
                ORDER BY bp.created_at DESC
                LIMIT 5
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['event_datum']) {
                    $levelLabel = ucfirst(str_replace('_', ' ', $row['bonus_level'] ?? ''));
                    $events[] = [
                        'id'       => null,
                        'typ'      => 'bonus_milstolpe',
                        'datum'    => $row['event_datum'],
                        'datetime' => $row['event_datetime'],
                        'text'     => '💰 Bonusmilstolpe! ' . $row['op_name'] . ' nådde ' . $levelLabel
                                      . ($row['period_label'] ? ' (' . $row['period_label'] . ')' : ''),
                        'ikon'     => 'coins',
                        'category' => 'bonus',
                        'pinned'   => false,
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController::getEvents:bonus_milstolpe: " . $e->getMessage());
        }

        // 9. Lång streak — operatörer med 5+ dagar i rad (beräknas i realtid)
        try {
            $sql = "
                SELECT o.number, o.name AS op_name,
                       GROUP_CONCAT(DISTINCT DATE(ri.datum) ORDER BY DATE(ri.datum) DESC) AS dagar
                FROM operators o
                JOIN rebotling_ibc ri ON (ri.op1 = o.number OR ri.op2 = o.number OR ri.op3 = o.number)
                WHERE ri.datum >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                GROUP BY o.number, o.name
                HAVING COUNT(DISTINCT DATE(ri.datum)) >= 5
                ORDER BY COUNT(DISTINCT DATE(ri.datum)) DESC
                LIMIT 5
            ";
            $stmt = $this->pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Beräkna faktisk streak (konsekutiva dagar bakåt från senaste dag)
                $dates = explode(',', $row['dagar']);
                $streak = 0;
                $prevDate = null;
                foreach ($dates as $ds) {
                    try {
                        $d = new \DateTime(trim($ds));
                    } catch (\Exception) {
                        break;
                    }
                    if ($prevDate !== null) {
                        $diff = $prevDate->diff($d)->days;
                        if ($diff > 1) break;
                    }
                    $streak++;
                    $prevDate = $d;
                }
                if ($streak >= 5) {
                    $events[] = [
                        'id'       => null,
                        'typ'      => 'lang_streak',
                        'datum'    => $dates[0],
                        'datetime' => $dates[0] . ' 08:00:00',
                        'text'     => '🔥 Lång streak! ' . $row['op_name'] . ' har arbetat '
                                      . $streak . ' dagar i rad!',
                        'ikon'     => 'fire',
                        'category' => 'bonus',
                        'pinned'   => false,
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("NewsController::getEvents:lang_streak: " . $e->getMessage());
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

        echo json_encode(['success' => true, 'events' => $events], JSON_UNESCAPED_UNICODE);
    }

    private function ikonForCategory(string $category): string {
        $map = [
            'produktion'   => 'chart-bar',
            'bonus'        => 'coins',
            'system'       => 'cog',
            'info'         => 'info-circle',
            'viktig'       => 'exclamation-triangle',
            'rekord'       => 'medal',
            'hog_oee'      => 'rocket',
            'certifiering' => 'certificate',
            'urgent'       => 'exclamation-circle',
            'varning'      => 'exclamation-triangle',
        ];
        return $map[$category] ?? 'bell';
    }
}
