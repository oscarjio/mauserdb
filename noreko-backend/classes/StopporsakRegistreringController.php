<?php
/**
 * StopporsakRegistreringController - Snabbregistrering av stopporsaker för operatörer
 *
 * GET  ?action=stopporsak-reg&run=categories        → Hämta alla aktiva kategorier
 * GET  ?action=stopporsak-reg&run=active            → Hämta aktiva (ej avslutade) stopp
 * GET  ?action=stopporsak-reg&run=recent&limit=N    → Senaste N registrerade stopp
 * POST ?action=stopporsak-reg&run=register          → Registrera ett nytt stopp
 * POST ?action=stopporsak-reg&run=end-stop          → Avsluta ett aktivt stopp
 */
class StopporsakRegistreringController {
    private $pdo;
    private const VALID_LINJER = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje'];

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

        $run = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            if ($run === 'categories') {
                $this->getCategories();
            } elseif ($run === 'active') {
                $this->getActiveStops();
            } elseif ($run === 'recent') {
                $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
                $this->getRecentStops($limit);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig run-parameter']);
            }
            return;
        }

        if ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Ej inloggad']);
                return;
            }

            if ($run === 'register') {
                $this->registerStop();
            } elseif ($run === 'end-stop') {
                $this->endStop();
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
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `stopporsak_kategorier` (
                `id` int NOT NULL AUTO_INCREMENT,
                `namn` varchar(100) NOT NULL,
                `ikon` varchar(10) NOT NULL DEFAULT '📝',
                `sort_order` int NOT NULL DEFAULT 0,
                `active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `stopporsak_registreringar` (
                `id` int NOT NULL AUTO_INCREMENT,
                `kategori_id` int NOT NULL,
                `linje` varchar(50) NOT NULL DEFAULT 'rebotling',
                `kommentar` text,
                `user_id` int DEFAULT NULL,
                `start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `end_time` datetime DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_kategori` (`kategori_id`),
                KEY `idx_linje` (`linje`),
                KEY `idx_user` (`user_id`),
                KEY `idx_start` (`start_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Seed standardkategorier om tabellen är tom
            $count = $this->pdo->query("SELECT COUNT(*) FROM stopporsak_kategorier")->fetchColumn();
            if ((int)$count === 0) {
                $defaults = [
                    ['Underhåll',         '🔧', 1],
                    ['Materialbrist',      '📦', 2],
                    ['Kvalitetskontroll',  '🔍', 3],
                    ['Rast',               '☕', 4],
                    ['Rengöring',          '🧹', 5],
                    ['Maskinhaveri',       '⚠️', 6],
                    ['Verktygsbyte',       '🔄', 7],
                    ['Övrigt',             '📝', 8],
                ];
                $stmt = $this->pdo->prepare(
                    "INSERT INTO stopporsak_kategorier (namn, ikon, sort_order) VALUES (?, ?, ?)"
                );
                foreach ($defaults as $row) {
                    $stmt->execute($row);
                }
            }
        } catch (\PDOException $e) {
            error_log('StopporsakRegistreringController ensureTablesExist: ' . $e->getMessage());
        }
    }

    private function getCategories() {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, namn, ikon, sort_order FROM stopporsak_kategorier
                 WHERE active = 1 ORDER BY sort_order, namn"
            );
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\PDOException $e) {
            error_log('getCategories: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta kategorier']);
        }
    }

    private function getActiveStops() {
        try {
            $linje = $this->validatedLinje($_GET['linje'] ?? 'rebotling');
            $stmt = $this->pdo->prepare(
                "SELECT r.id, r.kategori_id, k.namn AS kategori_namn, k.ikon,
                        r.linje, r.kommentar, r.user_id, r.start_time, r.end_time,
                        u.username AS operator_namn
                 FROM stopporsak_registreringar r
                 JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                 LEFT JOIN users u ON r.user_id = u.id
                 WHERE r.end_time IS NULL AND r.linje = ?
                 ORDER BY r.start_time DESC"
            );
            $stmt->execute([$linje]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\PDOException $e) {
            error_log('getActiveStops: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta aktiva stopp']);
        }
    }

    private function getRecentStops(int $limit) {
        try {
            $linje = $this->validatedLinje($_GET['linje'] ?? 'rebotling');
            $stmt = $this->pdo->prepare(
                "SELECT r.id, r.kategori_id, k.namn AS kategori_namn, k.ikon,
                        r.linje, r.kommentar, r.user_id, r.start_time, r.end_time,
                        u.username AS operator_namn,
                        CASE
                          WHEN r.end_time IS NOT NULL
                          THEN ROUND(TIMESTAMPDIFF(SECOND, r.start_time, r.end_time) / 60, 1)
                          ELSE NULL
                        END AS varaktighet_minuter
                 FROM stopporsak_registreringar r
                 JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                 LEFT JOIN users u ON r.user_id = u.id
                 WHERE r.linje = ?
                 ORDER BY r.start_time DESC
                 LIMIT ?"
            );
            $stmt->execute([$linje, $limit]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\PDOException $e) {
            error_log('getRecentStops: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta senaste stopp']);
        }
    }

    private function registerStop() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig JSON-data']);
                return;
            }

            $kategoriId = intval($data['category_id'] ?? 0);
            if ($kategoriId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Kategori-ID saknas']);
                return;
            }

            // Verify category exists
            $check = $this->pdo->prepare("SELECT id FROM stopporsak_kategorier WHERE id = ? AND active = 1");
            $check->execute([$kategoriId]);
            if (!$check->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig kategori']);
                return;
            }

            $linje     = $this->validatedLinje($data['linje'] ?? 'rebotling');
            $kommentar = mb_substr(strip_tags(trim($data['kommentar'] ?? '')), 0, 500);
            $userId    = intval($_SESSION['user_id']);
            $startTime = date('Y-m-d H:i:s');

            $stmt = $this->pdo->prepare(
                "INSERT INTO stopporsak_registreringar (kategori_id, linje, kommentar, user_id, start_time)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$kategoriId, $linje, $kommentar ?: null, $userId, $startTime]);
            $newId = (int)$this->pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Stopp registrerat',
                'id'      => $newId,
            ]);
        } catch (\PDOException $e) {
            error_log('registerStop: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte registrera stopp']);
        }
    }

    private function endStop() {
        try {
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

            // Verify the stop exists and belongs to user (or user is admin)
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id, end_time FROM stopporsak_registreringar WHERE id = ?"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Stopp hittades inte']);
                return;
            }

            if ($row['end_time'] !== null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Stoppet är redan avslutat']);
                return;
            }

            $userId  = intval($_SESSION['user_id']);
            $role    = $_SESSION['role'] ?? '';
            if ($role !== 'admin' && (int)$row['user_id'] !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Åtkomst nekad']);
                return;
            }

            $endTime = date('Y-m-d H:i:s');
            $upd = $this->pdo->prepare(
                "UPDATE stopporsak_registreringar SET end_time = ? WHERE id = ?"
            );
            $upd->execute([$endTime, $id]);

            echo json_encode(['success' => true, 'message' => 'Stopp avslutat', 'end_time' => $endTime]);
        } catch (\PDOException $e) {
            error_log('endStop: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte avsluta stopp']);
        }
    }

    private function validatedLinje(string $linje): string {
        return in_array($linje, self::VALID_LINJER, true) ? $linje : 'rebotling';
    }
}
