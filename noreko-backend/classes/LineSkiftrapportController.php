<?php
require_once __DIR__ . '/AuditController.php';
/**
 * LineSkiftrapportController – generisk skiftrapport för tvattlinje, saglinje, klassificeringslinje.
 * Endpoint: GET/POST /api.php?action=lineskiftrapport&line={tvattlinje|saglinje|klassificeringslinje}
 */
class LineSkiftrapportController {
    private $pdo;
    private static $allowedLines = ['tvattlinje', 'saglinje', 'klassificeringslinje'];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
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
        $line = strtolower(trim($_GET['line'] ?? ''));

        if (!in_array($line, self::$allowedLines, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig linje. Tillåtna: ' . implode(', ', self::$allowedLines)], JSON_UNESCAPED_UNICODE);
            return;
        }

        $table = $line . '_skiftrapport';
        $this->ensureTable($table);

        if ($method === 'GET') {
            $run = $_GET['run'] ?? '';
            if (class_exists('RemoteAgg') && RemoteAgg::enabled() && RemoteAgg::passthru('lineskiftrapport')) return;
            if ($run === 'lopnummer') {
                $this->getLopnummer($line);
                return;
            }
            if ($run === 'operators') {
                $this->getOperators();
                return;
            }
            if ($run === 'products') {
                $this->getProducts($line);
                return;
            }
            if ($run === 'subshifts') {
                $this->getSubShifts($line);
                return;
            }
            if ($run === 'daglig') {
                $this->getDagligBreakdown($line);
                return;
            }
            $this->getReports($table);
            return;
        }

        if ($method === 'POST') {
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $data['action'] ?? '';

            switch ($action) {
                case 'create':
                    $this->createReport($table, $data);
                    break;
                case 'update':
                    $this->checkOwnerOrAdmin($table, $data['id'] ?? 0);
                    $this->updateReport($table, $data);
                    break;
                case 'delete':
                    $this->checkOwnerOrAdmin($table, $data['id'] ?? 0);
                    $this->deleteReport($table, $data);
                    break;
                case 'updateInlagd':
                    $this->checkAdmin();
                    $this->updateInlagd($table, $data);
                    break;
                case 'bulkDelete':
                    $this->checkAdmin();
                    $this->bulkDelete($table, $data);
                    break;
                case 'bulkUpdateInlagd':
                    $this->checkAdmin();
                    $this->bulkUpdateInlagd($table, $data);
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltig action'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod'], JSON_UNESCAPED_UNICODE);
    }

    // ========== Auth Helpers ==========

    private function checkAdmin() {
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','developer'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function checkOwnerOrAdmin($table, $reportId) {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Inte inloggad'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT user_id FROM `$table` WHERE id = ?");
            $stmt->execute([intval($reportId)]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$report) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Rapport hittades inte'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ((int)$report['user_id'] !== (int)$_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Du kan bara ändra dina egna rapporter'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (PDOException $e) {
            error_log('LineSkiftrapportController::checkOwnerOrAdmin: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ========== Schema ==========

    private function ensureTable($table) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            if ((int)$stmt->fetchColumn() === 0) {
                $sql = "CREATE TABLE IF NOT EXISTS `$table` (
                    `id`         INT NOT NULL AUTO_INCREMENT,
                    `datum`      DATE NOT NULL,
                    `skiftraknare` INT DEFAULT NULL,
                    `antal_ok`   INT NOT NULL DEFAULT 0,
                    `antal_ej_ok` INT NOT NULL DEFAULT 0,
                    `totalt`     INT NOT NULL DEFAULT 0,
                    `kommentar`  TEXT DEFAULT NULL,
                    `inlagd`     TINYINT(1) NOT NULL DEFAULT 0,
                    `user_id`    INT DEFAULT NULL,
                    `op1`        INT DEFAULT NULL,
                    `op2`        INT DEFAULT NULL,
                    `op3`        INT DEFAULT NULL,
                    `product_id` INT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_datum` (`datum`),
                    KEY `idx_user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                $this->pdo->exec($sql);
            }
            // Add missing columns to existing tables (schema migration)
            $cols = ['skiftraknare' => 'INT DEFAULT NULL', 'op1' => 'INT DEFAULT NULL', 'op2' => 'INT DEFAULT NULL', 'op3' => 'INT DEFAULT NULL', 'product_id' => 'INT DEFAULT NULL'];
            foreach ($cols as $col => $def) {
                $exists = $this->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'")->fetchColumn();
                if (!$exists) {
                    $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                }
            }
        } catch (PDOException $e) {
            error_log("LineSkiftrapportController::ensureTable($table): " . $e->getMessage());
        }
    }

    // ========== CRUD ==========

    private function getReports($table) {
        $ibcTable = str_replace('_skiftrapport', '_ibc', $table);
        $ibcExists = $this->pdo->query("SHOW TABLES LIKE '$ibcTable'")->rowCount() > 0;
        try {
            if ($ibcExists) {
                $stmt = $this->pdo->prepare("
                    SELECT base.*,
                        (SELECT MIN(i.datum) FROM `$ibcTable` i
                         WHERE i.datum > base.prev_created_at AND i.datum <= base.created_at
                           AND DATE(i.datum) = base.datum) AS plc_start,
                        (SELECT MAX(i.datum) FROM `$ibcTable` i
                         WHERE i.datum > base.prev_created_at AND i.datum <= base.created_at
                           AND DATE(i.datum) = base.datum) AS plc_end
                    FROM (
                        SELECT r.*, u.username AS user_name,
                            o1.name AS op1_name, o2.name AS op2_name, o3.name AS op3_name,
                            COALESCE(
                                (SELECT MAX(r2.created_at) FROM `$table` r2 WHERE r2.created_at < r.created_at),
                                DATE_SUB(r.created_at, INTERVAL 24 HOUR)
                            ) AS prev_created_at
                        FROM `$table` r
                        LEFT JOIN users u ON r.user_id = u.id
                        LEFT JOIN operators o1 ON r.op1 IS NOT NULL AND (o1.number = r.op1)
                        LEFT JOIN operators o2 ON r.op2 IS NOT NULL AND (o2.number = r.op2)
                        LEFT JOIN operators o3 ON r.op3 IS NOT NULL AND (o3.number = r.op3)
                        ORDER BY r.datum DESC, r.id DESC
                        LIMIT 1000
                    ) base
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT r.*, u.username AS user_name,
                        o1.name AS op1_name, o2.name AS op2_name, o3.name AS op3_name,
                        COALESCE(
                            (SELECT MAX(r2.created_at) FROM `$table` r2 WHERE r2.created_at < r.created_at),
                            DATE_SUB(r.created_at, INTERVAL 24 HOUR)
                        ) AS prev_created_at,
                        NULL AS plc_start, NULL AS plc_end
                    FROM `$table` r
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN operators o1 ON r.op1 IS NOT NULL AND (o1.number = r.op1)
                    LEFT JOIN operators o2 ON r.op2 IS NOT NULL AND (o2.number = r.op2)
                    LEFT JOIN operators o3 ON r.op3 IS NOT NULL AND (o3.number = r.op3)
                    ORDER BY r.datum DESC, r.id DESC
                    LIMIT 1000
                ");
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dayTotals = $this->computeDayTotals($table, $ibcTable, $ibcExists);
            $grand = array_sum($dayTotals);
            echo json_encode(['success' => true, 'data' => $results, 'day_totals' => $dayTotals, 'grand_total_ibc' => $grand], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log("LineSkiftrapportController::getReports($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta rapporter'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Beräknar deduplicerad IBC-total per dag ['Y-m-d' => ibc].
     * PLC-först (kontinuitet, jfr commit 396063a): PLC-värdet (MAX(ibc_count) per dag)
     * skriver över den deduplicerade skiftrapport-summan för varje dag som har PLC-data.
     * Skiftrapport dedupliceras: senaste post (MAX id) per (dag, skiftraknare) — summera ALDRIG snapshots.
     */
    private function computeDayTotals($table, $ibcTable, $ibcExists) {
        $dayTotals = [];
        try {
            // Kolla om skiftraknare-kolumnen finns (samma mönster som ensureTable rad ~181)
            $hasSkiftraknare = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = 'skiftraknare'"
            )->fetchColumn() > 0;
            $grp = $hasSkiftraknare ? "DATE(datum), COALESCE(skiftraknare, 0)" : "DATE(datum)";

            // 1) Deduplicerad skiftrapport-summa per dag
            $stmt = $this->pdo->query("
                SELECT DATE(sr.datum) AS dag, COALESCE(SUM(sr.totalt), 0) AS ibc
                FROM `$table` sr
                INNER JOIN (SELECT MAX(id) AS max_id FROM `$table` GROUP BY $grp) latest
                    ON sr.id = latest.max_id
                GROUP BY DATE(sr.datum)
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dayTotals[$row['dag']] = (int)$row['ibc'];
            }
        } catch (PDOException $e) {
            error_log("LineSkiftrapportController::computeDayTotals($table) SR: " . $e->getMessage());
        }

        // 2) PLC-först: PLC-värdet skriver över SR-värdet för dagar med PLC-data.
        // ibc_count är KUMULATIVT och nollställs vid SKIFTRAPPORT (inte vid midnatt).
        // Ett skift = MAX(ibc_count) (kumulativ topp), daterat till skiftets MIN(datum).
        // Om _ibc-tabellen har skiftraknare grupperas per skiftraknare; annars grupperas
        // på reset-segment (gaps-and-islands: nytt segment när ibc_count sjunker).
        if ($ibcExists) {
            try {
                $ibcHasSkiftraknare = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$ibcTable' AND COLUMN_NAME = 'skiftraknare'"
                )->fetchColumn() > 0;

                if ($ibcHasSkiftraknare) {
                    // ibc_count nollställs vid SKIFTRAPPORT, inte vid midnatt. Ett skift
                    // (skiftraknare) = MAX(ibc_count) (kumulativ topp), daterat till skiftets
                    // MIN(datum). Summera per dag. Ingen LAG, ingen PARTITION BY DATE.
                    $plcSql = "
                        SELECT dag, COALESCE(SUM(ibc_end), 0) AS ibc
                        FROM (
                            SELECT skiftraknare, DATE(MIN(datum)) AS dag, MAX(ibc_count) AS ibc_end
                            FROM `$ibcTable`
                            GROUP BY skiftraknare
                        ) t
                        GROUP BY dag
                    ";
                } else {
                    // Ingen skiftraknare → gruppera på RESET-SEGMENT (gaps-and-islands).
                    // Ett nytt segment börjar när ibc_count < föregående rad (räknaren
                    // nollställdes). seg_id = löpande SUM över reset-flaggan. Segmentets
                    // total = MAX(ibc_count); datera till DATE(MIN(datum)); summera per dag.
                    $plcSql = "
                        SELECT dag, COALESCE(SUM(ibc_end), 0) AS ibc
                        FROM (
                            SELECT seg_id, DATE(MIN(datum)) AS dag, MAX(ibc_count) AS ibc_end
                            FROM (
                                SELECT datum, ibc_count,
                                       SUM(is_reset) OVER (ORDER BY datum ROWS UNBOUNDED PRECEDING) AS seg_id
                                FROM (
                                    SELECT datum, ibc_count,
                                           CASE WHEN ibc_count < LAG(ibc_count) OVER (ORDER BY datum) THEN 1 ELSE 0 END AS is_reset
                                    FROM `$ibcTable`
                                ) f
                            ) s
                            GROUP BY seg_id
                        ) t
                        GROUP BY dag
                    ";
                }

                $plc = $this->pdo->query($plcSql);
                foreach ($plc->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $dayTotals[$row['dag']] = (int)$row['ibc'];
                }
            } catch (PDOException $e) {
                // ibc_count kan saknas för andra linjer → behåll SR-dedup
                error_log("LineSkiftrapportController::computeDayTotals($table) PLC: " . $e->getMessage());
            }
        }

        return $dayTotals;
    }

    private function createReport($table, $data) {
        try {
            // Om datum inte skickas in — hämta senaste PLC-datum från _ibc-tabellen (stöd för sen inskickning)
            $ibcTable = str_replace('_skiftrapport', '_ibc', $table);
            $datumFran = trim($data['datum'] ?? '');
            if ($datumFran === '') {
                try {
                    $ibcExists = $this->pdo->query("SHOW TABLES LIKE '$ibcTable'")->rowCount() > 0;
                    if ($ibcExists) {
                        // B6-fix: välj den kalenderdag med FLEST pulser i senaste 16h-spannet
                        // (undviker felmatchning när nattskift korsar midnatt)
                        $majStmt = $this->pdo->prepare("
                            SELECT DATE(datum) AS dag, COUNT(*) AS cnt
                            FROM `$ibcTable`
                            WHERE datum >= DATE_SUB(
                                (SELECT MAX(datum) FROM `$ibcTable`),
                                INTERVAL 16 HOUR
                            )
                            GROUP BY DATE(datum)
                            ORDER BY cnt DESC
                            LIMIT 1
                        ");
                        $majStmt->execute();
                        $majRow = $majStmt->fetch(PDO::FETCH_ASSOC);
                        $datumFran = ($majRow && $majRow['dag']) ? $majRow['dag'] : date('Y-m-d');
                    } else {
                        $datumFran = date('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    error_log("LineSkiftrapportController::createReport – IBC-datum fallback: " . $e->getMessage());
                    $datumFran = date('Y-m-d');
                }
            }
            $datum = $datumFran;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Detektera sen inskickning: datum skiljer sig från dagens datum
            $today = date('Y-m-d');
            $sentInskickad = ($datum !== $today) ? 1 : 0;

            $antal_ok    = max(0, min(999999, intval($data['antal_ok'] ?? 0)));
            $antal_ej_ok = max(0, min(999999, intval($data['antal_ej_ok'] ?? 0)));
            $totalt      = $antal_ok + $antal_ej_ok;
            $kommentar   = htmlspecialchars(trim($data['kommentar'] ?? ''), ENT_QUOTES, 'UTF-8') ?: null;
            if ($kommentar !== null && mb_strlen($kommentar) > 2000) {
                $kommentar = mb_substr($kommentar, 0, 2000);
            }
            $user_id     = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $op1         = isset($data['op1']) && $data['op1'] !== '' && $data['op1'] !== null ? max(0, intval($data['op1'])) : null;
            $op2         = isset($data['op2']) && $data['op2'] !== '' && $data['op2'] !== null ? max(0, intval($data['op2'])) : null;
            $op3         = isset($data['op3']) && $data['op3'] !== '' && $data['op3'] !== null ? max(0, intval($data['op3'])) : null;
            $product_id  = isset($data['product_id']) && $data['product_id'] !== '' && $data['product_id'] !== null ? intval($data['product_id']) : null;

            // Kontrollera om sent_inskickad-kolumnen finns (migration kanske inte körd ännu)
            $hasSentCol = false;
            try {
                $check = $this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = '$table'
                       AND COLUMN_NAME = 'sent_inskickad'"
                )->fetchColumn();
                $hasSentCol = (int)$check > 0;
            } catch (\Throwable $e) {
                // Ignorera — faller tillbaka till INSERT utan kolumnen
            }

            $this->pdo->beginTransaction();
            if ($hasSentCol) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO `$table` (datum, antal_ok, antal_ej_ok, totalt, kommentar, user_id, op1, op2, op3, product_id, sent_inskickad)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$datum, $antal_ok, $antal_ej_ok, $totalt, $kommentar, $user_id, $op1, $op2, $op3, $product_id, $sentInskickad]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO `$table` (datum, antal_ok, antal_ej_ok, totalt, kommentar, user_id, op1, op2, op3, product_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$datum, $antal_ok, $antal_ej_ok, $totalt, $kommentar, $user_id, $op1, $op2, $op3, $product_id]);
            }
            $newId = (int)$this->pdo->lastInsertId();
            $auditMsg = "Skapad: datum=$datum, antal_ok=$antal_ok, totalt=$totalt";
            if ($sentInskickad) {
                $auditMsg .= ", sent_inskickad=1 (datum=$datum, idag=$today)";
            }
            AuditLogger::log($this->pdo, 'create_rapport', $table, $newId, $auditMsg);
            $this->pdo->commit();
            echo json_encode([
                'success'         => true,
                'message'         => 'Rapport skapad',
                'id'              => $newId,
                'datum'           => $datum,
                'sent_inskickad'  => $sentInskickad,
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::createReport($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte skapa rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateReport($table, $data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $fields = [];
            $params = [];

            if (isset($data['datum'])) {
                $datum = trim($data['datum']);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $fields[] = 'datum = ?';
                $params[] = $datum;
            }
            if (isset($data['antal_ok'])) {
                $fields[] = 'antal_ok = ?';
                $params[] = max(0, min(999999, intval($data['antal_ok'])));
            }
            if (isset($data['antal_ej_ok'])) {
                $fields[] = 'antal_ej_ok = ?';
                $params[] = max(0, min(999999, intval($data['antal_ej_ok'])));
            }
            if (array_key_exists('kommentar', $data)) {
                $kommentar = htmlspecialchars(trim($data['kommentar']), ENT_QUOTES, 'UTF-8') ?: null;
                if ($kommentar !== null && mb_strlen($kommentar) > 2000) {
                    $kommentar = mb_substr($kommentar, 0, 2000);
                }
                $fields[] = 'kommentar = ?';
                $params[] = $kommentar;
            }

            // Räkna om totalt om några av antal-fälten ändrats
            if (isset($data['antal_ok']) || isset($data['antal_ej_ok'])) {
                $stmt = $this->pdo->prepare("SELECT antal_ok, antal_ej_ok FROM `$table` WHERE id = ?");
                $stmt->execute([$id]);
                $cur = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$cur) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Rapport hittades inte'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $final_ok    = isset($data['antal_ok'])    ? max(0, min(999999, intval($data['antal_ok'])))    : (int)$cur['antal_ok'];
                $final_ej_ok = isset($data['antal_ej_ok']) ? max(0, min(999999, intval($data['antal_ej_ok']))) : (int)$cur['antal_ej_ok'];
                $fields[] = 'totalt = ?';
                $params[] = $final_ok + $final_ej_ok;
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga fält att uppdatera'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $params[] = $id;
            $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            AuditLogger::log($this->pdo, 'update_rapport', $table, $id,
                'Rapport uppdaterad');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Rapport uppdaterad'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::updateReport($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function deleteReport($table, $data) {
        try {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->execute([$id]);
            AuditLogger::log($this->pdo, 'delete_rapport', $table, $id,
                'Rapport borttagen');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Rapport borttagen'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::deleteReport($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateInlagd($table, $data) {
        try {
            $id     = intval($data['id'] ?? 0);
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE `$table` SET inlagd = ? WHERE id = ?");
            $stmt->execute([$inlagd, $id]);
            AuditLogger::log($this->pdo, 'update_inlagd', $table, $id, 'inlagd=' . $inlagd);
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Status uppdaterad', 'inlagd' => $inlagd], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::updateInlagd($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera status'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkDelete($table, $data) {
        try {
            $ids = $data['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga giltiga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("DELETE FROM `$table` WHERE id IN ($placeholders)");
            $stmt->execute(array_values($ids));
            AuditLogger::log($this->pdo, 'bulk_delete_rapport', $table, null,
                count($ids) . ' rapporter borttagna (ID: ' . implode(', ', $ids) . ')');
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => count($ids) . ' rapport(er) borttagna'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::bulkDelete($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort rapporter'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bulkUpdateInlagd($table, $data) {
        try {
            $ids    = $data['ids'] ?? [];
            $inlagd = isset($data['inlagd']) ? ($data['inlagd'] ? 1 : 0) : 0;
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga giltiga ID:n angivna'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$inlagd], $ids);
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE `$table` SET inlagd = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            AuditLogger::log($this->pdo, 'bulk_update_inlagd', $table, null,
                count($ids) . ' rader, inlagd=' . $inlagd . ', ids=' . implode(',', $ids));
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => count($ids) . ' rapport(er) uppdaterade'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("LineSkiftrapportController::bulkUpdateInlagd($table): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera status'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperators(): void {
        try {
            $stmt = $this->pdo->query("SELECT id, number, name FROM operators ORDER BY name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log("LineSkiftrapportController::getOperators: " . $e->getMessage());
            echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getProducts(string $line): void {
        $productsTable = $line . '_products';
        try {
            $stmt = $this->pdo->query("SELECT id, name, cycle_time_minutes FROM `$productsTable` ORDER BY name");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log("LineSkiftrapportController::getProducts($line): " . $e->getMessage());
            echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getLopnummer(string $line): void {
        $ibcTable = $line . '_ibc';
        $today = date('Y-m-d');
        $normDt = $this->makeDtNormalizer($today);

        // Primär: from/to datetime-fönster (används av syntetiska pass)
        $from = $normDt(isset($_GET['from']) ? trim(str_replace('T', ' ', $_GET['from'])) : null);
        $to   = $normDt(isset($_GET['to'])   ? trim(str_replace('T', ' ', $_GET['to']))   : null);
        $validateDt = fn($s) => (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s ?? '');

        if ($validateDt($from)) {
            $toVal = $validateDt($to) ? $to : date('Y-m-d H:i:s');
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT DISTINCT lopnummer FROM `$ibcTable`
                     WHERE datum > ? AND datum <= ? AND lopnummer > 0 AND lopnummer < 9998
                     ORDER BY lopnummer"
                );
                $stmt->execute([$from, $toVal]);
                $nums = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'lopnummer');
                echo json_encode([
                    'success' => true,
                    'ranges'  => $this->buildLopnummerRanges(array_map('intval', $nums)),
                    'count'   => count($nums),
                ], JSON_UNESCAPED_UNICODE);
            } catch (\PDOException $e) {
                error_log("LineSkiftrapportController::getLopnummer($line) dt: " . $e->getMessage());
                echo json_encode(['success' => false, 'ranges' => '–', 'count' => 0], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        // Fallback: skiftraknare
        $skiftraknare = isset($_GET['skiftraknare']) ? intval($_GET['skiftraknare']) : 0;
        if ($skiftraknare <= 0) {
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skiftraknare'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT lopnummer FROM `$ibcTable`
                 WHERE skiftraknare = ? AND lopnummer > 0 AND lopnummer < 9998
                 ORDER BY lopnummer"
            );
            $stmt->execute([$skiftraknare]);
            $nums = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'lopnummer');
            echo json_encode([
                'success' => true,
                'ranges'  => $this->buildLopnummerRanges(array_map('intval', $nums)),
                'count'   => count($nums),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log("LineSkiftrapportController::getLopnummer($line): " . $e->getMessage());
            echo json_encode(['success' => false, 'ranges' => '–', 'count' => 0], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getSubShifts(string $line): void {
        $today = date('Y-m-d');
        $normDt = $this->makeDtNormalizer($today);

        $from = $normDt(isset($_GET['from']) ? trim(str_replace('T', ' ', $_GET['from'])) : null);
        $to   = $normDt(isset($_GET['to'])   ? trim(str_replace('T', ' ', $_GET['to']))   : null);
        $validateDt = fn($s) => (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s ?? '');

        $ibcTable = $line . '_ibc';
        $selectCols = "i.id, i.datum, i.ibc_count, i.s_count, i.skiftraknare,
                       i.op1, i.op2, i.op3, i.produkt,
                       i.ibc_ok, i.ibc_ej_ok, i.omtvaatt,
                       i.runtime_plc, i.rasttime, i.driftstopptime, i.lopnummer, i.effektivitet,
                       o1.name AS op1_name, o2.name AS op2_name, o3.name AS op3_name";
        $joins = "LEFT JOIN operators o1 ON i.op1 IS NOT NULL AND (o1.number = i.op1)
                  LEFT JOIN operators o2 ON i.op2 IS NOT NULL AND (o2.number = i.op2)
                  LEFT JOIN operators o3 ON i.op3 IS NOT NULL AND (o3.number = i.op3)";

        if (!$validateDt($from)) {
            // Legacy fallback: query by skiftraknare
            $skiftraknare = intval($_GET['skiftraknare'] ?? 0);
            if ($skiftraknare <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ogiltigt from/skiftraknare'], JSON_UNESCAPED_UNICODE);
                return;
            }
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT $selectCols FROM `$ibcTable` i $joins
                     WHERE i.skiftraknare = ? ORDER BY i.datum ASC"
                );
                $stmt->execute([$skiftraknare]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            } catch (\PDOException $e) {
                error_log("LineSkiftrapportController::getSubShifts($line) legacy: " . $e->getMessage());
                echo json_encode(['success' => false, 'data' => []], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        try {
            if ($validateDt($to)) {
                // Normal window: (from, to]
                $stmt = $this->pdo->prepare(
                    "SELECT $selectCols FROM `$ibcTable` i $joins
                     WHERE i.datum > ? AND i.datum <= ?
                     ORDER BY i.datum ASC LIMIT 2000"
                );
                $stmt->execute([$from, $to]);
            } else {
                // Preliminary / öppet fönster: from till NOW
                $stmt = $this->pdo->prepare(
                    "SELECT $selectCols FROM `$ibcTable` i $joins
                     WHERE i.datum > ?
                     ORDER BY i.datum ASC LIMIT 2000"
                );
                $stmt->execute([$from]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log("LineSkiftrapportController::getSubShifts($line): " . $e->getMessage());
            echo json_encode(['success' => false, 'data' => []], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Normaliserar ett datetime-strängar: HH:MM:SS → YYYY-MM-DD HH:MM:SS, etc. */
    private function makeDtNormalizer(string $today): \Closure {
        return function(?string $s) use ($today): ?string {
            if (!$s) return null;
            $s = trim(str_replace('T', ' ', $s));
            // Stripp millisekunder/Z (t.ex. ".000Z")
            $s = preg_replace('/\.\d+Z?$/', '', $s);
            // Full datetime OK
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s)) return $s;
            // Bara tid HH:MM:SS → lägg till dagens datum
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) return "$today $s";
            // YYYY-MM-DD HH:MM (utan sekunder)
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) return "$s:00";
            // Bara datum YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return "$s 00:00:00";
            return null;
        };
    }

    private function getDagligBreakdown($line) {
        $dagligTable = $line . '_skiftrapport_daglig';
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt id'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            // Om tabellen saknas returnera tom array (migration ej körd ännu)
            $exists = $this->pdo->query("SHOW TABLES LIKE '$dagligTable'")->rowCount() > 0;
            if (!$exists) {
                echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
                return;
            }
            $stmt = $this->pdo->prepare("SELECT * FROM `$dagligTable` WHERE skiftrapport_id = ? ORDER BY dag ASC");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log("LineSkiftrapportController::getDagligBreakdown($dagligTable): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function buildLopnummerRanges(array $nums): string {
        if (empty($nums)) return '–';
        sort($nums);
        $ranges = [];
        $start = $prev = $nums[0];
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
