<?php
class SkiftrapportController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    public function handle() {
        session_start();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'GET') {
            $this->getSkiftrapporter();
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';
            
            if ($action === 'create') {
                $this->createSkiftrapport($data);
            } elseif ($action === 'delete') {
                $this->checkOwnerOrAdmin($data['id'] ?? 0);
                $this->deleteSkiftrapport($data);
            } elseif ($action === 'updateInlagd') {
                $this->checkAdmin();
                $this->updateInlagd($data);
            } elseif ($action === 'bulkDelete') {
                $this->checkAdmin();
                $this->bulkDelete($data);
            } elseif ($action === 'bulkUpdateInlagd') {
                $this->checkAdmin();
                $this->bulkUpdateInlagd($data);
            } elseif ($action === 'update') {
                $this->checkOwnerOrAdmin($data['id'] ?? 0);
                $this->updateSkiftrapport($data);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig action']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Ogiltig metod']);
        }
    }

    private function checkAdmin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Endast admin har behörighet']);
            exit;
        }
    }

    private function checkOwnerOrAdmin($reportId) {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Inte inloggad']);
            exit;
        }

        // Admins can do anything
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return;
        }

        // Check if user owns this report
        try {
            $stmt = $this->pdo->prepare("SELECT user_id FROM rebotling_skiftrapport WHERE id = ?");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$report) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Skiftrapport hittades inte']);
                exit;
            }

            if ($report['user_id'] != $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Du kan bara ändra dina egna skiftrapporter']);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Databasfel']);
            exit;
        }
    }

    private function ensureTableExists() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'rebotling_skiftrapport'");
            if ($stmt->rowCount() === 0) {
                // Skapa tabellen
                $sql = "CREATE TABLE IF NOT EXISTS `rebotling_skiftrapport` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `datum` date NOT NULL,
                    `ibc_ok` int NOT NULL DEFAULT 0,
                    `bur_ej_ok` int NOT NULL DEFAULT 0,
                    `ibc_ej_ok` int NOT NULL DEFAULT 0,
                    `totalt` int NOT NULL DEFAULT 0,
                    `inlagd` tinyint(1) NOT NULL DEFAULT 0,
                    `product_id` int DEFAULT NULL,
                    `user_id` int DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_datum` (`datum`),
                    KEY `idx_product_id` (`product_id`),
                    KEY `idx_user_id` (`user_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $this->pdo->exec($sql);
            } else {
                // Check if new columns exist and add them if not
                $stmt = $this->pdo->query("SHOW COLUMNS FROM rebotling_skiftrapport LIKE 'product_id'");
                if ($stmt->rowCount() === 0) {
                    $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD COLUMN `product_id` int DEFAULT NULL AFTER `inlagd`");
                    $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD INDEX `idx_product_id` (`product_id`)");
                }
                
                $stmt = $this->pdo->query("SHOW COLUMNS FROM rebotling_skiftrapport LIKE 'user_id'");
                if ($stmt->rowCount() === 0) {
                    $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD COLUMN `user_id` int DEFAULT NULL AFTER `product_id`");
                    $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD INDEX `idx_user_id` (`user_id`)");
                }
            }
        } catch (PDOException $e) {
            // Ignorera om tabellen redan finns
        }
    }

    private function getSkiftrapporter() {
        try {
            $stmt = $this->pdo->query("SELECT s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt, s.inlagd, s.product_id, s.user_id, s.created_at, s.updated_at,
                u.username as user_name,
                p.name as product_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                ORDER BY s.datum DESC, s.id DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte hämta skiftrapporter: ' . $e->getMessage()
            ]);
        }
    }

    private function createSkiftrapport($data) {
        try {
            $datum = $data['datum'] ?? date('Y-m-d');
            $ibc_ok = intval($data['ibc_ok'] ?? 0);
            $bur_ej_ok = intval($data['bur_ej_ok'] ?? 0);
            $ibc_ej_ok = intval($data['ibc_ej_ok'] ?? 0);
            $totalt = $ibc_ok + $bur_ej_ok + $ibc_ej_ok;
            $product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
            $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

            $stmt = $this->pdo->prepare("INSERT INTO rebotling_skiftrapport (datum, ibc_ok, bur_ej_ok, ibc_ej_ok, totalt, product_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$datum, $ibc_ok, $bur_ej_ok, $ibc_ej_ok, $totalt, $product_id, $user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport skapad',
                'id' => $this->pdo->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte skapa skiftrapport: ' . $e->getMessage()
            ]);
        }
    }

    private function deleteSkiftrapport($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt ID']);
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM rebotling_skiftrapport WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport borttagen'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte ta bort skiftrapport: ' . $e->getMessage()
            ]);
        }
    }

    private function bulkDelete($data) {
        try {
            $ids = $data['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Inga ID:n angivna']);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("DELETE FROM rebotling_skiftrapport WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' skiftrapport(er) borttagna'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte ta bort skiftrapporter: ' . $e->getMessage()
            ]);
        }
    }

    private function updateInlagd($data) {
        try {
            $id = intval($data['id'] ?? 0);
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt ID']);
                return;
            }

            $stmt = $this->pdo->prepare("UPDATE rebotling_skiftrapport SET inlagd = ? WHERE id = ?");
            $stmt->execute([$inlagd, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Status uppdaterad',
                'inlagd' => $inlagd
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera status: ' . $e->getMessage()
            ]);
        }
    }

    private function bulkUpdateInlagd($data) {
        try {
            $ids = $data['ids'] ?? [];
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Inga ID:n angivna']);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$inlagd], $ids);
            $stmt = $this->pdo->prepare("UPDATE rebotling_skiftrapport SET inlagd = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' skiftrapport(er) uppdaterade',
                'inlagd' => $inlagd
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera status: ' . $e->getMessage()
            ]);
        }
    }

    private function updateSkiftrapport($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt ID']);
                return;
            }

            $datum = $data['datum'] ?? null;
            $ibc_ok = isset($data['ibc_ok']) ? intval($data['ibc_ok']) : null;
            $bur_ej_ok = isset($data['bur_ej_ok']) ? intval($data['bur_ej_ok']) : null;
            $ibc_ej_ok = isset($data['ibc_ej_ok']) ? intval($data['ibc_ej_ok']) : null;
            $product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
            
            $fields = [];
            $params = [];
            
            if ($datum) {
                $fields[] = 'datum = ?';
                $params[] = $datum;
            }
            if ($ibc_ok !== null) {
                $fields[] = 'ibc_ok = ?';
                $params[] = $ibc_ok;
            }
            if ($bur_ej_ok !== null) {
                $fields[] = 'bur_ej_ok = ?';
                $params[] = $bur_ej_ok;
            }
            if ($ibc_ej_ok !== null) {
                $fields[] = 'ibc_ej_ok = ?';
                $params[] = $ibc_ej_ok;
            }
            if ($product_id !== null) {
                $fields[] = 'product_id = ?';
                $params[] = $product_id;
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Inga fält att uppdatera']);
                return;
            }
            
            // Beräkna totalt om något av antalen ändras
            if ($ibc_ok !== null || $bur_ej_ok !== null || $ibc_ej_ok !== null) {
                // Hämta nuvarande värden om alla inte angivna
                $stmt = $this->pdo->prepare("SELECT ibc_ok, bur_ej_ok, ibc_ej_ok FROM rebotling_skiftrapport WHERE id = ?");
                $stmt->execute([$id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $final_ibc_ok = $ibc_ok !== null ? $ibc_ok : $current['ibc_ok'];
                $final_bur_ej_ok = $bur_ej_ok !== null ? $bur_ej_ok : $current['bur_ej_ok'];
                $final_ibc_ej_ok = $ibc_ej_ok !== null ? $ibc_ej_ok : $current['ibc_ej_ok'];
                $totalt = $final_ibc_ok + $final_bur_ej_ok + $final_ibc_ej_ok;
                
                $fields[] = 'totalt = ?';
                $params[] = $totalt;
            }
            
            $params[] = $id;
            $sql = 'UPDATE rebotling_skiftrapport SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport uppdaterad'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera skiftrapport: ' . $e->getMessage()
            ]);
        }
    }
}

