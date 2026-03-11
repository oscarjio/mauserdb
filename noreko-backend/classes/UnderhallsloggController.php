<?php
/**
 * UnderhallsloggController - Underhållslogg för maskiner
 *
 * GET  ?action=underhallslogg&run=categories          → Lista underhållskategorier
 * POST ?action=underhallslogg&run=log                 → Logga ett underhållstillfälle
 * GET  ?action=underhallslogg&run=list                → Lista underhållsposter (med filtrering)
 * GET  ?action=underhallslogg&run=stats               → Sammanfattningsstatistik
 * POST ?action=underhallslogg&run=delete              → Ta bort en post (admin-only)
 */
class UnderhallsloggController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }

        // Alla endpoints kräver inloggning
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Ej inloggad']);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            if ($run === 'categories') {
                $this->getCategories();
            } elseif ($run === 'list') {
                $this->getList();
            } elseif ($run === 'stats') {
                $this->getStats();
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig run-parameter']);
            }
            return;
        }

        if ($method === 'POST') {
            if ($run === 'log') {
                $this->logUnderhall();
            } elseif ($run === 'delete') {
                $this->deleteEntry();
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig run-parameter']);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod']);
    }

    private function ensureTablesExist() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `underhallslogg` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `kategori` VARCHAR(50) NOT NULL,
                `typ` ENUM('planerat','oplanerat') NOT NULL,
                `varaktighet_min` INT NOT NULL,
                `kommentar` TEXT,
                `maskin` VARCHAR(100) NOT NULL DEFAULT 'Rebotling',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_kategori` (`kategori`),
                KEY `idx_typ` (`typ`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `underhall_kategorier` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `namn` VARCHAR(50) NOT NULL,
                `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Seed standardkategorier om tabellen är tom
            $count = $this->pdo->query("SELECT COUNT(*) FROM underhall_kategorier")->fetchColumn();
            if ((int)$count === 0) {
                $defaults = ['Mekaniskt', 'Elektriskt', 'Hydraulik', 'Pneumatik', 'Rengöring', 'Kalibrering', 'Annat'];
                $stmt = $this->pdo->prepare("INSERT INTO underhall_kategorier (namn) VALUES (?)");
                foreach ($defaults as $namn) {
                    $stmt->execute([$namn]);
                }
            }
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController ensureTablesExist: ' . $e->getMessage());
        }
    }

    private function getCategories() {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, namn FROM underhall_kategorier WHERE aktiv = 1 ORDER BY id"
            );
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController getCategories: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta kategorier']);
        }
    }

    private function logUnderhall() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig JSON-data']);
                return;
            }

            $kategori       = mb_substr(strip_tags(trim($data['kategori'] ?? '')), 0, 50);
            $typ            = $data['typ'] ?? '';
            $varaktighetMin = intval($data['varaktighet_min'] ?? 0);
            $kommentar      = mb_substr(strip_tags(trim($data['kommentar'] ?? '')), 0, 2000);
            $maskin         = mb_substr(strip_tags(trim($data['maskin'] ?? 'Rebotling')), 0, 100);
            $userId         = intval($data['user_id'] ?? $_SESSION['user_id']);

            if (empty($kategori)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Kategori saknas']);
                return;
            }

            if (!in_array($typ, ['planerat', 'oplanerat'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig typ — ange planerat eller oplanerat']);
                return;
            }

            if ($varaktighetMin <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Varaktighet måste vara större än 0']);
                return;
            }

            $userId = intval($_SESSION['user_id']);

            $stmt = $this->pdo->prepare(
                "INSERT INTO underhallslogg (user_id, kategori, typ, varaktighet_min, kommentar, maskin)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $kategori, $typ, $varaktighetMin, $kommentar ?: null, $maskin ?: 'Rebotling']);
            $newId = (int)$this->pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Underhall loggat',
                'id'      => $newId,
            ]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController logUnderhall: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte logga underhall']);
        }
    }

    private function getList() {
        try {
            $days     = max(1, min(365, intval($_GET['days'] ?? 30)));
            $typ      = $_GET['type'] ?? 'all';
            $kategori = trim($_GET['category'] ?? 'all');

            $where = ["u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
            $params = [$days];

            if ($typ !== 'all' && in_array($typ, ['planerat', 'oplanerat'], true)) {
                $where[] = "u.typ = ?";
                $params[] = $typ;
            }

            if ($kategori !== 'all' && !empty($kategori)) {
                $where[] = "u.kategori = ?";
                $params[] = $kategori;
            }

            $whereClause = implode(' AND ', $where);

            $sql = "SELECT u.id, u.user_id, u.kategori, u.typ, u.varaktighet_min,
                           u.kommentar, u.maskin, u.created_at,
                           usr.username AS operator_namn
                    FROM underhallslogg u
                    LEFT JOIN users usr ON u.user_id = usr.id
                    WHERE {$whereClause}
                    ORDER BY u.created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController getList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta underhallslogg']);
        }
    }

    private function getStats() {
        try {
            $days = max(1, min(365, intval($_GET['days'] ?? 30)));

            // Totalt antal och tid
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) AS totalt_antal,
                        COALESCE(SUM(varaktighet_min), 0) AS total_tid_min,
                        COALESCE(SUM(CASE WHEN typ = 'planerat' THEN 1 ELSE 0 END), 0) AS planerat_antal,
                        COALESCE(SUM(CASE WHEN typ = 'oplanerat' THEN 1 ELSE 0 END), 0) AS oplanerat_antal
                 FROM underhallslogg
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $stmt->execute([$days]);
            $totals = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totaltAntal   = (int)$totals['totalt_antal'];
            $totalTidMin   = (int)$totals['total_tid_min'];
            $planeratAntal = (int)$totals['planerat_antal'];
            $oplaneratAntal = (int)$totals['oplanerat_antal'];

            // Veckor i perioden
            $veckor = max(1, round($days / 7));
            $snittPerVecka = $totaltAntal > 0 ? round($totaltAntal / $veckor, 1) : 0;

            // Procentandelar
            $planeratPct  = $totaltAntal > 0 ? round(($planeratAntal / $totaltAntal) * 100, 1) : 0;
            $oplaneratPct = $totaltAntal > 0 ? round(($oplaneratAntal / $totaltAntal) * 100, 1) : 0;

            // Topp-kategorier
            $stmtKat = $this->pdo->prepare(
                "SELECT kategori, COUNT(*) AS antal, SUM(varaktighet_min) AS total_min
                 FROM underhallslogg
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY kategori
                 ORDER BY antal DESC
                 LIMIT 7"
            );
            $stmtKat->execute([$days]);
            $topKategorier = $stmtKat->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success'       => true,
                'data' => [
                    'totalt_antal'    => $totaltAntal,
                    'total_tid_min'   => $totalTidMin,
                    'snitt_per_vecka' => $snittPerVecka,
                    'planerat_antal'  => $planeratAntal,
                    'oplanerat_antal' => $oplaneratAntal,
                    'planerat_pct'    => $planeratPct,
                    'oplanerat_pct'   => $oplaneratPct,
                    'top_kategorier'  => $topKategorier,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta statistik']);
        }
    }

    private function deleteEntry() {
        try {
            $role = $_SESSION['role'] ?? '';
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Åtkomst nekad — kräver admin']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig JSON-data']);
                return;
            }

            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt ID']);
                return;
            }

            $check = $this->pdo->prepare("SELECT id FROM underhallslogg WHERE id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Post hittades inte']);
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM underhallslogg WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Post borttagen']);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController deleteEntry: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort post']);
        }
    }
}
