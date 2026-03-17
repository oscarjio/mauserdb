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
            if ($run === 'daglig-sammanstallning') {
                $this->getDagligSammanstallning();
                return;
            }
            if ($run === 'veckosammanstallning') {
                $this->getVeckosammanstallning();
                return;
            }
            if ($run === 'skiftjamforelse') {
                $this->getSkiftjamforelse();
                return;
            }
            $this->getSkiftrapporter();
        } elseif ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
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
                echo json_encode(['success' => false, 'error' => 'Ogiltig action'], JSON_UNESCAPED_UNICODE);
            }
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Ogiltig metod'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function checkAdmin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function checkOwnerOrAdmin($reportId) {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
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
                echo json_encode(['success' => false, 'error' => 'Skiftrapport hittades inte'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ((int)$report['user_id'] !== (int)$_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Du kan bara ändra dina egna skiftrapporter'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (PDOException $e) {
            error_log('SkiftrapportController::checkOwnerOrAdmin: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
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
        } catch (PDOException) {
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte hämta skiftrapporter: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte hämta skiftrapporter'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function createSkiftrapport($data) {
        try {
            $datum = $data['datum'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte skapa skiftrapport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte skapa skiftrapport'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function deleteSkiftrapport($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM rebotling_skiftrapport WHERE id = ?");
            $stmt->execute([$id]);

            AuditLogger::log($this->pdo, 'delete_skiftrapport', 'rebotling_skiftrapport', $id,
                'Skiftrapport borttagen');

            echo json_encode([
                'success' => true,
                'message' => 'Skiftrapport borttagen'
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte ta bort skiftrapport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte ta bort skiftrapport'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkDelete($data) {
        try {
            $ids = $data['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga ID:n angivna'], JSON_UNESCAPED_UNICODE);
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte ta bort skiftrapporter: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte ta bort skiftrapporter'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateInlagd($data) {
        try {
            $id = intval($data['id'] ?? 0);
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte uppdatera status (updateInlagd): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera status'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkUpdateInlagd($data) {
        try {
            $ids = $data['ids'] ?? [];
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga ID:n angivna'], JSON_UNESCAPED_UNICODE);
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte uppdatera status (bulkUpdateInlagd): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera status'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateSkiftrapport($data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
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
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
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
                echo json_encode(['success' => false, 'error' => 'Inga fält att uppdatera'], JSON_UNESCAPED_UNICODE);
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
                    echo json_encode(['success' => false, 'error' => 'Skiftrapport hittades inte'], JSON_UNESCAPED_UNICODE);
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::inte uppdatera skiftrapport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kunde inte uppdatera skiftrapport'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getLopnummerForSkift() {
        $skiftraknare = isset($_GET['skiftraknare']) ? intval($_GET['skiftraknare']) : 0;
        if ($skiftraknare <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skifträknare'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $usedSkiftraknare = $skiftraknare;
            $fallbackUsed = false;

            // Försök med exakt skifträknare först
            $nums = $this->fetchLopnummer($skiftraknare);

            // Fallback: om inga (eller för få) giltiga löpnummer, sök närliggande skifträknare
            // Scenarion: skiftrapport sparad dag efter (datum=10) men PLC-cykler ligger på dag 09
            // PLC räknar upp → data under föregående räknarvärde (nedåt)
            // Söker utan datumfilter — skifträknare är tillräckligt unikt
            if (count($nums) <= 1) {
                $stmt = $this->pdo->prepare(
                    "SELECT skiftraknare, COUNT(DISTINCT lopnummer) as cnt
                     FROM rebotling_ibc
                     WHERE skiftraknare BETWEEN ? AND ?
                     AND lopnummer > 0 AND lopnummer < 998
                     GROUP BY skiftraknare
                     ORDER BY cnt DESC
                     LIMIT 1"
                );
                $stmt->execute([
                    $skiftraknare - 2,
                    $skiftraknare - 1
                ]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && intval($row['cnt']) > count($nums)) {
                    $usedSkiftraknare = intval($row['skiftraknare']);
                    $nums = $this->fetchLopnummer($usedSkiftraknare);
                    $fallbackUsed = true;
                }
            }

            // Hämta start- och stopptid för skiftet från rebotling_ibc
            $datum = isset($_GET['datum']) ? substr($_GET['datum'], 0, 10) : null;
            $skiftTider = $this->getSkiftTider($usedSkiftraknare, $datum);

            $response = [
                'success'      => true,
                'ranges'       => $this->buildRanges($nums),
                'count'        => count($nums),
                'skift_start'  => $skiftTider['start'],
                'skift_slut'   => $skiftTider['slut'],
                'cykel_datum'  => $skiftTider['cykel_datum'],
            ];

            if ($fallbackUsed) {
                $response['fallback_skiftraknare'] = $usedSkiftraknare;
                $response['original_skiftraknare'] = $skiftraknare;
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::getLopnummerForSkift: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta löpnummer'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Hämta start/stopptid för ett skift.
     * 1) Hitta rätt skifträknare i rebotling_ibc (original, n-1, n-2)
     * 2) Använd SAMMA skifträknare i rebotling_onoff för start/stopp
     * 3) Fallback: cykeltider från rebotling_ibc
     * 4) Fallback: estimera från runtime_plc om allt annat saknas
     *
     * @param int         $skiftraknare  Skifträknare att söka
     * @param string|null $datum         Rapportens datum (YYYY-MM-DD), för runtime-fallback
     */
    private function getSkiftTider(int $skiftraknare, ?string $datum = null): array {
        // Steg 1: Hitta rätt skifträknare i rebotling_ibc (fallback nedåt)
        $foundSkiftraknare = null;
        foreach ([$skiftraknare, $skiftraknare - 1, $skiftraknare - 2] as $testId) {
            $chk = $this->pdo->prepare(
                "SELECT COUNT(*) FROM rebotling_ibc
                 WHERE skiftraknare = ? AND lopnummer > 0 AND lopnummer < 998"
            );
            $chk->execute([$testId]);
            if ((int)$chk->fetchColumn() > 0) {
                $foundSkiftraknare = $testId;
                break;
            }
        }

        if ($foundSkiftraknare === null) {
            return ['start' => null, 'slut' => null, 'cykel_datum' => null];
        }

        // Steg 2: Hämta cykeldatum
        $ibcStmt = $this->pdo->prepare(
            "SELECT GROUP_CONCAT(DISTINCT DATE(datum) ORDER BY DATE(datum) ASC) AS cykel_datum
             FROM rebotling_ibc WHERE skiftraknare = ? AND lopnummer > 0 AND lopnummer < 998"
        );
        $ibcStmt->execute([$foundSkiftraknare]);
        $cykelDatum = $ibcStmt->fetchColumn() ?: null;

        // Steg 3: Använd samma skifträknare i rebotling_onoff
        // Första running=1 = maskinstart, sista running=0 = maskinstopp
        $startTid = null;
        $slutTid  = null;
        try {
            $onStmt = $this->pdo->prepare(
                "SELECT
                    (SELECT MIN(datum) FROM rebotling_onoff WHERE skiftraknare = ? AND running = 1) AS first_start,
                    (SELECT MAX(datum) FROM rebotling_onoff WHERE skiftraknare = ? AND running = 0) AS last_stop"
            );
            $onStmt->execute([$foundSkiftraknare, $foundSkiftraknare]);
            $onRow = $onStmt->fetch(PDO::FETCH_ASSOC);
            if ($onRow) {
                $startTid = $onRow['first_start'] ?? null;
                $slutTid  = $onRow['last_stop'] ?? null;
            }
            // Om inget running=0 finns (maskin lämnades på), fallback till MAX(datum) oavsett
            if (!$slutTid && $startTid) {
                $maxStmt = $this->pdo->prepare(
                    "SELECT MAX(datum) AS last FROM rebotling_onoff WHERE skiftraknare = ?"
                );
                $maxStmt->execute([$foundSkiftraknare]);
                $slutTid = $maxStmt->fetchColumn() ?: null;
            }
        } catch (Exception) {}

        // Fallback: cykeltider om onoff saknas
        if (!$startTid || !$slutTid) {
            $fallStmt = $this->pdo->prepare(
                "SELECT MIN(datum) AS f, MAX(datum) AS l FROM rebotling_ibc
                 WHERE skiftraknare = ? AND lopnummer > 0 AND lopnummer < 998"
            );
            $fallStmt->execute([$foundSkiftraknare]);
            $fallRow = $fallStmt->fetch(PDO::FETCH_ASSOC);
            if (!$startTid) $startTid = $fallRow['f'] ?? null;
            if (!$slutTid)  $slutTid  = $fallRow['l'] ?? null;
        }

        // Fallback: estimera från runtime_plc om fortfarande saknas
        // Beräkna: start = rapportdatum + 06:00, stop = start + runtime minuter
        if ((!$startTid || !$slutTid) && $datum) {
            try {
                $rtStmt = $this->pdo->prepare(
                    "SELECT MAX(COALESCE(runtime_plc, 0)) AS runtime_min
                     FROM rebotling_ibc WHERE skiftraknare = ?"
                );
                $rtStmt->execute([$foundSkiftraknare]);
                $runtimeMin = (int)($rtStmt->fetchColumn() ?? 0);
                if ($runtimeMin > 0) {
                    $baseDate = substr($datum, 0, 10);
                    // Använd 06:00 som default skiftstart
                    if (!$startTid) {
                        $startTid = $baseDate . ' 06:00:00';
                    }
                    if (!$slutTid) {
                        $slutTid = date('Y-m-d H:i:s', strtotime($startTid) + ($runtimeMin * 60));
                    }
                }
            } catch (Exception) {}
        }

        return [
            'start'       => $startTid,
            'slut'        => $slutTid,
            'cykel_datum' => $cykelDatum,
        ];
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::getOperatorList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörer'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=skiftrapport&run=shift-report-by-operator&operator_id=X&from=YYYY-MM-DD&to=YYYY-MM-DD
     * Returnerar skiftdata per dag/skift for given operator.
     */
    private function getShiftReportByOperator() {
        $operatorId = isset($_GET['operator_id']) ? intval($_GET['operator_id']) : 0;
        $from = trim($_GET['from'] ?? '');
        $to   = trim($_GET['to'] ?? '');

        if ($operatorId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'operator_id saknas'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat (from/to)'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // Validera att from <= to
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        try {
            // Hämta operatörens nummer från operators-tabellen
            $stmtOp = $this->pdo->prepare("SELECT number, name FROM operators WHERE id = ?");
            $stmtOp->execute([$operatorId]);
            $operator = $stmtOp->fetch(PDO::FETCH_ASSOC);
            if (!$operator) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Operator hittades inte'], JSON_UNESCAPED_UNICODE);
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
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('SkiftrapportController::getShiftReportByOperator: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftrapport per operatör'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // Rebotling Skiftrapport-sammanstallning
    // Skift: Dag 06-14, Kvall 14-22, Natt 22-06
    // OEE = Tillganglighet x Prestanda x Kvalitet
    // ================================================================

    private const IDEAL_CYCLE_SEC = 120;
    private const SKIFT_LANGD_SEK = 8 * 3600; // 8 timmar

    /**
     * Berakna skiftintervall for ett datum.
     * Dag: 06:00-14:00, Kvall: 14:00-22:00, Natt: 22:00-06:00 (nasta dag)
     */
    private function getSkiftIntervall(string $datum): array {
        return [
            'dag'   => [$datum . ' 06:00:00', $datum . ' 14:00:00'],
            'kvall' => [$datum . ' 14:00:00', $datum . ' 22:00:00'],
            'natt'  => [$datum . ' 22:00:00', date('Y-m-d', strtotime($datum . ' +1 day')) . ' 06:00:00'],
        ];
    }

    /**
     * Berakna OEE + produktionsdata for ett tidsintervall (skift).
     */
    private function calcSkiftData(string $fromDt, string $toDt): array {
        // 1) Drifttid fran rebotling_onoff (datum + running columns)
        $drifttidSek = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum <= :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prevTime = null;
            $prevRunning = null;
            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];
                if ($prevTime !== null && $prevRunning === 1) {
                    $drifttidSek += ($ts - $prevTime);
                }
                $prevTime = $ts;
                $prevRunning = $running;
            }
            $drifttidSek = max(0, $drifttidSek);
        } catch (\PDOException $e) {
            error_log('SkiftrapportController::calcSkiftData onoff: ' . $e->getMessage());
        }

        // 2) IBC-data fran rebotling_ibc (MAX per skiftraknare, then SUM)
        $totalIbc = 0;
        $okIbc = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_antal,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_dt AND datum < :to_dt
                    GROUP BY skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $okIbc = (int)($row['ok_antal'] ?? 0);
            $totalIbc = $okIbc + (int)($row['ej_ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('SkiftrapportController::calcSkiftData ibc: ' . $e->getMessage());
        }

        $kasserade = $totalIbc - $okIbc;

        // 3) OEE-berakning
        $tillganglighet = self::SKIFT_LANGD_SEK > 0 ? min(1.0, $drifttidSek / self::SKIFT_LANGD_SEK) : 0.0;
        $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
        $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        $stopptidH = max(0, (self::SKIFT_LANGD_SEK - $drifttidSek)) / 3600;

        // 4) Top-3 kassationsorsaker
        $topOrsaker = [];
        try {
            $stmtK = $this->pdo->prepare("
                SELECT kt.namn AS orsak, COUNT(*) AS antal
                FROM kassationsregistrering kr
                JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE kr.datum >= :from_dt AND kr.datum < :to_dt
                GROUP BY kt.id, kt.namn
                ORDER BY antal DESC
                LIMIT 3
            ");
            $stmtK->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $topOrsaker = $stmtK->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            // kassationsregistrering kanske inte finns
        }

        return [
            'producerade'        => $totalIbc,
            'godkanda'           => $okIbc,
            'kasserade'          => $kasserade,
            'kassationsgrad_pct' => $totalIbc > 0 ? round(($kasserade / $totalIbc) * 100, 1) : 0,
            'oee_pct'            => round($oee * 100, 1),
            'tillganglighet_pct' => round($tillganglighet * 100, 1),
            'prestanda_pct'      => round($prestanda * 100, 1),
            'kvalitet_pct'       => round($kvalitet * 100, 1),
            'drifttid_h'         => round($drifttidSek / 3600, 2),
            'stopptid_h'         => round($stopptidH, 2),
            'top_kassationsorsaker' => $topOrsaker,
        ];
    }

    /**
     * run=daglig-sammanstallning
     * Hamtar skiftdata (dag/kvall/natt) for ett visst datum.
     * ?datum=YYYY-MM-DD (default: idag)
     */
    private function getDagligSammanstallning(): void {
        $datum = trim($_GET['datum'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $skift = $this->getSkiftIntervall($datum);
        $resultat = [];
        foreach ($skift as $namn => $intervall) {
            $data = $this->calcSkiftData($intervall[0], $intervall[1]);
            $data['skift'] = $namn;
            $data['start'] = $intervall[0];
            $data['slut'] = $intervall[1];
            $resultat[] = $data;
        }

        // Totalt for dagen
        $totProducerade = array_sum(array_column($resultat, 'producerade'));
        $totKasserade = array_sum(array_column($resultat, 'kasserade'));
        $totGodkanda = array_sum(array_column($resultat, 'godkanda'));

        echo json_encode([
            'success' => true,
            'data' => [
                'datum' => $datum,
                'skift' => $resultat,
                'totalt' => [
                    'producerade' => $totProducerade,
                    'godkanda' => $totGodkanda,
                    'kasserade' => $totKasserade,
                    'kassationsgrad_pct' => $totProducerade > 0 ? round(($totKasserade / $totProducerade) * 100, 1) : 0,
                ],
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * run=veckosammanstallning
     * Sammanstallning per dag, senaste 7 dagarna.
     */
    private function getVeckosammanstallning(): void {
        $dagar = [];
        for ($i = 6; $i >= 0; $i--) {
            $datum = date('Y-m-d', strtotime("-{$i} days"));
            $skift = $this->getSkiftIntervall($datum);
            $dagData = [
                'datum' => $datum,
                'veckodag' => $this->veckodagNamn(date('N', strtotime($datum))),
            ];
            foreach ($skift as $namn => $intervall) {
                $dagData[$namn] = $this->calcSkiftData($intervall[0], $intervall[1]);
            }
            // Dagstotalt
            $dagTotProd = ($dagData['dag']['producerade'] ?? 0) + ($dagData['kvall']['producerade'] ?? 0) + ($dagData['natt']['producerade'] ?? 0);
            $dagTotKass = ($dagData['dag']['kasserade'] ?? 0) + ($dagData['kvall']['kasserade'] ?? 0) + ($dagData['natt']['kasserade'] ?? 0);
            $dagData['totalt_producerade'] = $dagTotProd;
            $dagData['totalt_kasserade'] = $dagTotKass;
            $dagData['totalt_oee_pct'] = 0;
            if ($dagTotProd > 0) {
                // Snitt-OEE av skiften
                $oees = array_filter([
                    $dagData['dag']['oee_pct'] ?? 0,
                    $dagData['kvall']['oee_pct'] ?? 0,
                    $dagData['natt']['oee_pct'] ?? 0,
                ], fn($v) => $v > 0);
                $dagData['totalt_oee_pct'] = count($oees) > 0 ? round(array_sum($oees) / count($oees), 1) : 0;
            }
            $dagar[] = $dagData;
        }

        echo json_encode([
            'success' => true,
            'data' => ['dagar' => $dagar],
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * run=skiftjamforelse
     * Jamfor skift dag/kvall/natt senaste N dagar (default 30).
     */
    private function getSkiftjamforelse(): void {
        $antalDagar = max(7, min(90, intval($_GET['dagar'] ?? 30)));

        $dagData = [];
        $skiftTotaler = [
            'dag'   => ['producerade' => 0, 'kasserade' => 0, 'godkanda' => 0, 'oee_sum' => 0, 'count' => 0],
            'kvall' => ['producerade' => 0, 'kasserade' => 0, 'godkanda' => 0, 'oee_sum' => 0, 'count' => 0],
            'natt'  => ['producerade' => 0, 'kasserade' => 0, 'godkanda' => 0, 'oee_sum' => 0, 'count' => 0],
        ];

        for ($i = $antalDagar - 1; $i >= 0; $i--) {
            $datum = date('Y-m-d', strtotime("-{$i} days"));
            $skift = $this->getSkiftIntervall($datum);
            $dagEntry = ['datum' => $datum];

            foreach ($skift as $namn => $intervall) {
                $sd = $this->calcSkiftData($intervall[0], $intervall[1]);
                $dagEntry[$namn . '_producerade'] = $sd['producerade'];
                $dagEntry[$namn . '_oee_pct'] = $sd['oee_pct'];

                $skiftTotaler[$namn]['producerade'] += $sd['producerade'];
                $skiftTotaler[$namn]['kasserade'] += $sd['kasserade'];
                $skiftTotaler[$namn]['godkanda'] += $sd['godkanda'];
                if ($sd['producerade'] > 0) {
                    $skiftTotaler[$namn]['oee_sum'] += $sd['oee_pct'];
                    $skiftTotaler[$namn]['count']++;
                }
            }
            $dagData[] = $dagEntry;
        }

        // Snitt per skift
        $snitt = [];
        foreach ($skiftTotaler as $namn => $t) {
            $snitt[$namn] = [
                'totalt_producerade' => $t['producerade'],
                'totalt_kasserade' => $t['kasserade'],
                'totalt_godkanda' => $t['godkanda'],
                'snitt_oee_pct' => $t['count'] > 0 ? round($t['oee_sum'] / $t['count'], 1) : 0,
                'snitt_producerade_per_dag' => $antalDagar > 0 ? round($t['producerade'] / $antalDagar, 1) : 0,
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'antal_dagar' => $antalDagar,
                'dagdata' => $dagData,
                'snitt' => $snitt,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function veckodagNamn(int $dayOfWeek): string {
        $namn = ['', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor', 'Son'];
        return $namn[$dayOfWeek] ?? '';
    }

    private function fetchLopnummer(int $skiftraknare): array {
        $stmt = $this->pdo->prepare(
            "SELECT lopnummer FROM rebotling_ibc WHERE skiftraknare = ? ORDER BY lopnummer"
        );
        $stmt->execute([$skiftraknare]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $nums = array_map('intval', $rows);
        // Filtrera bort 0 (PLC start-default), 998 och 999 (PLC reset/ogiltiga) och duplicerade
        return array_values(array_unique(array_filter($nums, fn($n) => $n > 0 && $n < 998)));
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
                $ranges[] = $start === $prev ? (string)$start : $start . '–' . $prev;
                $start = $prev = $nums[$i];
            }
        }
        $ranges[] = $start === $prev ? (string)$start : $start . '–' . $prev;
        return implode(', ', $ranges);
    }
}

