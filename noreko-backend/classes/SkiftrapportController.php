<?php
require_once __DIR__ . '/AuditController.php';

class SkiftrapportController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTableExists();
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

        if ($method === 'GET') {
            $run = trim($_GET['run'] ?? '');
            if ($run === 'lopnummer') {
                $this->getLopnummerForSkift();
                return;
            }
            if ($run === 'operator-list') {
                $this->getOperatorList();
                return;
            }
            if ($run === 'shift-report-by-operator') {
                $this->getShiftReportByOperator();
                return;
            }
            $this->getSkiftrapporter();
        } elseif ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Ej inloggad']);
                return;
            }
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
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sessionen har gått ut. Logga in igen.']);
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

            if ((int)$report['user_id'] !== (int)$_SESSION['user_id']) {
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
                    `skiftraknare` int DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_datum` (`datum`),
                    KEY `idx_product_id` (`product_id`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_skiftraknare` (`skiftraknare`)
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
                
                $stmt = $this->pdo->query("SHOW COLUMNS FROM rebotling_skiftrapport LIKE 'skiftraknare'");
                if ($stmt->rowCount() === 0) {
                    $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD COLUMN `skiftraknare` int DEFAULT NULL AFTER `user_id`");
                    $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD INDEX `idx_skiftraknare` (`skiftraknare`)");
                }

                // Migration 007 – PLC-fält från FX5
                $plcCols = [
                    'op1'       => 'INT DEFAULT NULL',
                    'op2'       => 'INT DEFAULT NULL',
                    'op3'       => 'INT DEFAULT NULL',
                    'drifttid'  => 'INT DEFAULT NULL',
                    'rasttime'  => 'INT DEFAULT NULL',
                    'lopnummer' => 'INT DEFAULT NULL',
                ];
                foreach ($plcCols as $col => $def) {
                    $chk = $this->pdo->query("SHOW COLUMNS FROM rebotling_skiftrapport LIKE '$col'");
                    if ($chk->rowCount() === 0) {
                        $this->pdo->exec("ALTER TABLE rebotling_skiftrapport ADD COLUMN `$col` $def");
                    }
                }
            }
        } catch (PDOException $e) {
            // Ignorera om tabellen redan finns
        }
    }

    private function getSkiftrapporter() {
        try {
            $stmt = $this->pdo->query("SELECT s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt, s.inlagd,
                s.product_id, s.user_id, s.skiftraknare, s.created_at, s.updated_at,
                s.op1, s.op2, s.op3, s.drifttid, s.rasttime, s.lopnummer,
                u.username as user_name,
                p.name as product_name,
                o1.name as op1_name,
                o2.name as op2_name,
                o3.name as op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                ORDER BY s.datum DESC, s.id DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte hämta skiftrapporter: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte hämta skiftrapporter'
            ]);
        }
    }

    private function createSkiftrapport($data) {
        try {
            $datum = $data['datum'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltigt datumformat']);
                return;
            }
            $ibc_ok = intval($data['ibc_ok'] ?? 0);
            $bur_ej_ok = intval($data['bur_ej_ok'] ?? 0);
            $ibc_ej_ok = intval($data['ibc_ej_ok'] ?? 0);
            $totalt = $ibc_ok + $bur_ej_ok + $ibc_ej_ok;
            $product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
            $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

            $stmt = $this->pdo->prepare("INSERT INTO rebotling_skiftrapport (datum, ibc_ok, bur_ej_ok, ibc_ej_ok, totalt, product_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$datum, $ibc_ok, $bur_ej_ok, $ibc_ej_ok, $totalt, $product_id, $user_id]);
            $newId = (int)$this->pdo->lastInsertId();

            AuditLogger::log($this->pdo, 'create_skiftrapport', 'rebotling_skiftrapport', $newId,
                'Skapad: datum=' . $datum . ', ibc_ok=' . $ibc_ok . ', totalt=' . $totalt,
                null, ['datum' => $datum, 'ibc_ok' => $ibc_ok, 'bur_ej_ok' => $bur_ej_ok, 'ibc_ej_ok' => $ibc_ej_ok, 'totalt' => $totalt]);

            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport skapad',
                'id' => $newId
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte skapa skiftrapport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte skapa skiftrapport'
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

            AuditLogger::log($this->pdo, 'delete_skiftrapport', 'rebotling_skiftrapport', $id,
                'Skiftrapport borttagen');

            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport borttagen'
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte ta bort skiftrapport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte ta bort skiftrapport'
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

            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("DELETE FROM rebotling_skiftrapport WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            AuditLogger::log($this->pdo, 'bulk_delete_skiftrapport', 'rebotling_skiftrapport', null,
                count($ids) . ' skiftrapporter borttagna (ID: ' . implode(', ', $ids) . ')');

            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' skiftrapport(er) borttagna'
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte ta bort skiftrapporter: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte ta bort skiftrapporter'
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
            AuditLogger::log($this->pdo, 'update_inlagd', 'rebotling_skiftrapport', $id,
                'inlagd=' . $inlagd);

            echo json_encode([
                'success' => true,
                'message' => 'Status uppdaterad',
                'inlagd' => $inlagd
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte uppdatera status (updateInlagd): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera status'
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

            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$inlagd], $ids);
            $stmt = $this->pdo->prepare("UPDATE rebotling_skiftrapport SET inlagd = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            AuditLogger::log($this->pdo, 'bulk_update_inlagd', 'rebotling_skiftrapport', null,
                count($ids) . ' rader, inlagd=' . $inlagd . ', ids=' . implode(',', $ids));

            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' skiftrapport(er) uppdaterade',
                'inlagd' => $inlagd
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte uppdatera status (bulkUpdateInlagd): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera status'
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
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ogiltigt datumformat']);
                    return;
                }
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
                if (!$current) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Skiftrapport hittades inte']);
                    return;
                }

                $final_ibc_ok = $ibc_ok !== null ? $ibc_ok : (int)($current['ibc_ok'] ?? 0);
                $final_bur_ej_ok = $bur_ej_ok !== null ? $bur_ej_ok : (int)($current['bur_ej_ok'] ?? 0);
                $final_ibc_ej_ok = $ibc_ej_ok !== null ? $ibc_ej_ok : (int)($current['ibc_ej_ok'] ?? 0);
                $totalt = $final_ibc_ok + $final_bur_ej_ok + $final_ibc_ej_ok;
                
                $fields[] = 'totalt = ?';
                $params[] = $totalt;
            }
            
            $params[] = $id;
            $sql = 'UPDATE rebotling_skiftrapport SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            AuditLogger::log($this->pdo, 'update_skiftrapport', 'rebotling_skiftrapport', $id,
                'Skiftrapport uppdaterad',
                null, array_filter(['datum' => $datum, 'ibc_ok' => $ibc_ok, 'bur_ej_ok' => $bur_ej_ok, 'ibc_ej_ok' => $ibc_ej_ok]));

            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport uppdaterad'
            ]);
        } catch (PDOException $e) {
            error_log('Kunde inte uppdatera skiftrapport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera skiftrapport'
            ]);
        }
    }

    private function getLopnummerForSkift() {
        $skiftraknare = isset($_GET['skiftraknare']) ? intval($_GET['skiftraknare']) : 0;
        if ($skiftraknare <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ogiltigt skifträknare']);
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT lopnummer FROM rebotling_ibc WHERE skiftraknare = ? ORDER BY lopnummer"
            );
            $stmt->execute([$skiftraknare]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $nums = array_map('intval', $rows);
            // Filtrera bort ogiltiga värden (0, 999 = PLC default) och duplicerade
            $nums = array_values(array_unique(array_filter($nums, fn($n) => $n > 0 && $n < 999)));
            echo json_encode([
                'success' => true,
                'ranges'  => $this->buildRanges($nums),
                'count'   => count($nums)
            ]);
        } catch (PDOException $e) {
            error_log('getLopnummerForSkift: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta löpnummer']);
        }
    }


    /**
     * GET ?action=skiftrapport&run=operator-list
     * Returnerar alla operatörer som förekommer i rebotling_skiftrapport.
     * Kräver ej admin — används för operatörsfilter i skiftrapport-vyn.
     */
    private function getOperatorList() {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT o.id, o.name, o.number
                FROM operators o
                WHERE o.id IN (
                    SELECT DISTINCT o1.id FROM rebotling_skiftrapport s
                    JOIN operators o1 ON o1.number = s.op1 WHERE s.op1 IS NOT NULL
                    UNION
                    SELECT DISTINCT o2.id FROM rebotling_skiftrapport s
                    JOIN operators o2 ON o2.number = s.op2 WHERE s.op2 IS NOT NULL
                    UNION
                    SELECT DISTINCT o3.id FROM rebotling_skiftrapport s
                    JOIN operators o3 ON o3.number = s.op3 WHERE s.op3 IS NOT NULL
                )
                ORDER BY o.name
            ");
            $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'data'    => $operators
            ]);
        } catch (PDOException $e) {
            error_log('getOperatorList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta operatörer']);
        }
    }

    /**
     * GET ?action=skiftrapport&run=shift-report-by-operator&operator_id=X&from=YYYY-MM-DD&to=YYYY-MM-DD
     * Returnerar skiftdata per dag/skift for given operator.
     */
    private function getShiftReportByOperator() {
        $operatorId = isset($_GET['operator_id']) ? intval($_GET['operator_id']) : 0;
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';

        if ($operatorId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'operator_id saknas']);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ogiltigt datumformat (from/to)']);
            return;
        }

        try {
            // Hämta operatörens nummer från operators-tabellen
            $stmtOp = $this->pdo->prepare("SELECT number, name FROM operators WHERE id = ?");
            $stmtOp->execute([$operatorId]);
            $operator = $stmtOp->fetch(PDO::FETCH_ASSOC);
            if (!$operator) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Operator hittades inte']);
                return;
            }
            $opNumber = intval($operator['number']);

            // Hämta alla skiftrapporter där operatören var op1, op2 eller op3
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id,
                    s.datum,
                    s.skiftraknare,
                    s.ibc_ok,
                    s.bur_ej_ok,
                    s.ibc_ej_ok,
                    s.totalt,
                    s.drifttid,
                    s.rasttime,
                    s.op1, s.op2, s.op3,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE s.datum >= :from_date AND s.datum <= :to_date
                  AND (s.op1 = :op1 OR s.op2 = :op2 OR s.op3 = :op3)
                ORDER BY s.datum DESC, s.skiftraknare DESC
            ");
            $stmt->execute([
                'from_date' => $from,
                'to_date'   => $to,
                'op1'       => $opNumber,
                'op2'       => $opNumber,
                'op3'       => $opNumber,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg resultat med beräknade KPI:er
            $data = [];
            foreach ($rows as $r) {
                $ibcOk     = intval($r['ibc_ok'] ?? 0);
                $ibcEjOk   = intval($r['ibc_ej_ok'] ?? 0);
                $burEjOk   = intval($r['bur_ej_ok'] ?? 0);
                $totalt    = intval($r['totalt'] ?? 0);
                $drifttid  = intval($r['drifttid'] ?? 0);   // minuter
                $rasttime  = intval($r['rasttime'] ?? 0);    // minuter
                $kasserade = $ibcEjOk + $burEjOk;

                // Cykeltid i minuter (drifttid / antal IBC)
                $cykeltid = ($ibcOk > 0 && $drifttid > 0) ? round($drifttid / $ibcOk, 2) : null;

                // OEE = (ibc_ok / totalt) * 100 (kvalitetsbaserad approx)
                $oee = ($totalt > 0) ? round($ibcOk / $totalt * 100, 1) : null;

                // Stopptid = total tillgänglig tid - drifttid - rasttime (uppskattning)
                // Skift = ca 480 min (8h). Om drifttid finns, beräkna stopptid
                $stopptid = ($drifttid > 0) ? max(0, 480 - $drifttid - $rasttime) : null;

                // Skiftnamn baserat på skifträknare
                $skiftNr = intval($r['skiftraknare'] ?? 0);
                $skiftNamn = $skiftNr > 0 ? 'Skift ' . $skiftNr : '-';

                $data[] = [
                    'id'           => intval($r['id']),
                    'datum'        => $r['datum'],
                    'skift'        => $skiftNamn,
                    'skiftraknare' => $skiftNr,
                    'ibc_ok'       => $ibcOk,
                    'kasserade'    => $kasserade,
                    'totalt'       => $totalt,
                    'cykeltid'     => $cykeltid,
                    'oee'          => $oee,
                    'drifttid'     => $drifttid,
                    'stopptid'     => $stopptid,
                    'rasttime'     => $rasttime,
                    'op1_name'     => $r['op1_name'],
                    'op2_name'     => $r['op2_name'],
                    'op3_name'     => $r['op3_name'],
                ];
            }

            echo json_encode([
                'success'       => true,
                'operator_name' => $operator['name'],
                'data'          => $data,
            ]);
        } catch (PDOException $e) {
            error_log('getShiftReportByOperator: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Kunde inte hämta skiftrapport per operatör']);
        }
    }

    private function buildRanges(array $nums): string {
        if (empty($nums)) return '–';
        sort($nums);
        $ranges = [];
        $start = $nums[0];
        $prev  = $nums[0];
        for ($i = 1; $i < count($nums); $i++) {
            if ($nums[$i] === $prev + 1) {
                $prev = $nums[$i];
            } else {
                $ranges[] = $start === $prev ? (string)$start : "$start–$prev";
                $start = $prev = $nums[$i];
            }
        }
        $ranges[] = $start === $prev ? (string)$start : "$start–$prev";
        return implode(', ', $ranges);
    }
}

