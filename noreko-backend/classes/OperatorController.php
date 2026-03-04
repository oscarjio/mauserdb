<?php
require_once __DIR__ . '/AuditController.php';

class OperatorController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Endast admin har behörighet.']);
            return;
        }
        global $pdo;
        AuditLogger::ensureTable($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';

            if ($action === 'create') {
                $name = trim($data['name'] ?? '');
                $number = isset($data['number']) ? intval($data['number']) : null;

                if (empty($name) || $number === null || $number <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Namn och nummer krävs']);
                    return;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO operators (name, number) VALUES (?, ?)");
                    $stmt->execute([$name, $number]);
                    $newId = $pdo->lastInsertId();
                    AuditLogger::log($pdo, 'create_operator', 'operator', (int)$newId,
                        "Skapade operatör: $name (#$number)",
                        null, ['name' => $name, 'number' => $number]
                    );
                    echo json_encode(['success' => true, 'message' => 'Operatör skapad', 'id' => $newId]);
                } catch (PDOException $e) {
                    error_log('OperatorController create: ' . $e->getMessage());
                    if ($e->getCode() == 23000) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => 'Operatörsnumret är redan registrerat']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Kunde inte skapa operatör']);
                    }
                }
                return;
            }

            $id = isset($data['id']) ? intval($data['id']) : null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID saknas']);
                return;
            }

            if ($action === 'update') {
                $name = trim($data['name'] ?? '');
                $number = isset($data['number']) ? intval($data['number']) : null;

                if (empty($name) || $number === null || $number <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Namn och nummer krävs']);
                    return;
                }

                try {
                    $stmt = $pdo->prepare("UPDATE operators SET name = ?, number = ? WHERE id = ?");
                    $stmt->execute([$name, $number, $id]);
                    AuditLogger::log($pdo, 'update_operator', 'operator', $id,
                        "Uppdaterade operatör #$id: $name (#$number)",
                        null, ['name' => $name, 'number' => $number]
                    );
                    echo json_encode(['success' => true, 'message' => 'Operatör uppdaterad']);
                } catch (PDOException $e) {
                    error_log('OperatorController update: ' . $e->getMessage());
                    if ($e->getCode() == 23000) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => 'Operatörsnumret är redan registrerat']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera operatör']);
                    }
                }
                return;
            }

            if ($action === 'delete') {
                try {
                    $stmt = $pdo->prepare("SELECT name, number FROM operators WHERE id = ?");
                    $stmt->execute([$id]);
                    $op = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$op) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Operatör hittades inte']);
                        return;
                    }

                    $stmt = $pdo->prepare("DELETE FROM operators WHERE id = ?");
                    $stmt->execute([$id]);
                    AuditLogger::log($pdo, 'delete_operator', 'operator', $id,
                        "Tog bort operatör: " . ($op['name'] ?? 'okänd') . " (#" . ($op['number'] ?? '?') . ")",
                        $op, null
                    );
                    echo json_encode(['success' => true, 'message' => 'Operatör borttagen']);
                } catch (PDOException $e) {
                    error_log('OperatorController delete: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte ta bort operatör']);
                }
                return;
            }

            if ($action === 'toggleActive') {
                try {
                    $stmt = $pdo->prepare("SELECT active, name FROM operators WHERE id = ?");
                    $stmt->execute([$id]);
                    $op = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$op) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Operatör hittades inte']);
                        return;
                    }

                    $newActive = $op['active'] == 1 ? 0 : 1;
                    $stmt = $pdo->prepare("UPDATE operators SET active = ? WHERE id = ?");
                    $stmt->execute([$newActive, $id]);
                    AuditLogger::log($pdo, 'toggle_operator_active', 'operator', $id,
                        ($newActive ? 'Aktiverade' : 'Inaktiverade') . " operatör: " . $op['name'],
                        ['active' => $op['active']], ['active' => $newActive]
                    );
                    echo json_encode(['success' => true, 'active' => $newActive]);
                } catch (PDOException $e) {
                    error_log('OperatorController toggleActive: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Kunde inte ändra status']);
                }
                return;
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Okänd åtgärd']);
            return;
        }

        // GET - dispatch based on run param
        $run = $_GET['run'] ?? '';
        if ($run === 'stats') {
            $this->getStats();
            return;
        }
        if ($run === 'trend') {
            $this->getOperatorTrend();
            return;
        }
        if ($run === 'pairs') {
            $this->getPairs();
            return;
        }

        // GET - Hämta alla operatörer
        try {
            $stmt = $pdo->query("SELECT * FROM operators ORDER BY number");
            $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['operators' => $operators]);
        } catch (PDOException $e) {
            error_log('OperatorController GET: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Kunde inte hämta operatörer']);
        }
    }

    private function getStats() {
        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    o.id,
                    o.name,
                    o.number,
                    o.active,
                    COUNT(DISTINCT s.id) AS shifts,
                    ROUND(
                        SUM(COALESCE(s.ibc_ok, 0)) /
                        NULLIF(SUM(COALESCE(s.drifttid, 0)) / 60.0, 0),
                    1) AS ibc_per_hour,
                    ROUND(AVG(
                        CASE WHEN s.totalt > 0
                             THEN s.ibc_ok * 100.0 / s.totalt
                             ELSE NULL END
                    ), 1) AS avg_quality,
                    MAX(s.datum) AS last_shift,
                    (
                        SELECT MAX(s2.datum)
                        FROM rebotling_skiftrapport s2
                        WHERE (s2.op1 = o.number OR s2.op2 = o.number OR s2.op3 = o.number)
                    ) AS all_time_last_shift
                FROM operators o
                LEFT JOIN rebotling_skiftrapport s
                    ON (s.op1 = o.number OR s.op2 = o.number OR s.op3 = o.number)
                    AND DATE(s.datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY o.id, o.name, o.number, o.active
                ORDER BY ibc_per_hour DESC
            ');
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna aktivitetsstatus per operatör
            foreach ($stats as &$s) {
                $lastShift = $s['all_time_last_shift'];
                if (!$lastShift) {
                    $s['activity_status'] = 'never';
                } elseif (strtotime($lastShift) >= strtotime('-7 days')) {
                    $s['activity_status'] = 'active';
                } elseif (strtotime($lastShift) >= strtotime('-30 days')) {
                    $s['activity_status'] = 'recent';
                } else {
                    $s['activity_status'] = 'inactive';
                }
            }
            unset($s);

            echo json_encode(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            error_log('OperatorController getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörsstatistik']);
        }
    }

    /**
     * GET ?action=operators&run=pairs
     * Analyserar parvisa operatörskombinationer senaste 90 dagarna.
     * Returnerar top-20 par sorterade på snitt-IBC/h (minst 3 gemensamma skift).
     */
    private function getPairs() {
        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    LEAST(op_a, op_b)   AS op1_num,
                    GREATEST(op_a, op_b) AS op2_num,
                    COUNT(*)             AS shifts_together,
                    ROUND(
                        SUM(COALESCE(ibc_ok, 0)) /
                        NULLIF(SUM(COALESCE(drifttid, 0)) / 60.0, 0),
                    1) AS avg_ibc_per_hour,
                    ROUND(AVG(
                        CASE WHEN totalt > 0 THEN ibc_ok * 100.0 / totalt ELSE NULL END
                    ), 1) AS avg_quality
                FROM (
                    SELECT op1 AS op_a, op2 AS op_b, ibc_ok, totalt, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND op2 IS NOT NULL AND op2 > 0
                      AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    UNION ALL
                    SELECT op1, op3, ibc_ok, totalt, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND op3 IS NOT NULL AND op3 > 0
                      AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    UNION ALL
                    SELECT op2, op3, ibc_ok, totalt, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op2 IS NOT NULL AND op3 IS NOT NULL AND op2 > 0 AND op3 > 0
                      AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                ) AS pairs
                GROUP BY LEAST(op_a, op_b), GREATEST(op_a, op_b)
                HAVING shifts_together >= 3
                ORDER BY avg_ibc_per_hour DESC
                LIMIT 20
            ');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode(['success' => true, 'pairs' => []]);
                return;
            }

            // Hämta operatörsnamn för alla berörda nummer
            $nums = [];
            foreach ($rows as $r) {
                $nums[] = (int)$r['op1_num'];
                $nums[] = (int)$r['op2_num'];
            }
            $nums = array_unique($nums);
            $placeholders = implode(',', array_fill(0, count($nums), '?'));

            $nameStmt = $this->pdo->prepare(
                "SELECT number, name FROM operators WHERE number IN ($placeholders)"
            );
            $nameStmt->execute($nums);
            $nameMap = [];
            foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $op) {
                $nameMap[(int)$op['number']] = $op['name'];
            }

            $pairs = [];
            foreach ($rows as $r) {
                $op1 = (int)$r['op1_num'];
                $op2 = (int)$r['op2_num'];
                $pairs[] = [
                    'op1_num'          => $op1,
                    'op1_name'         => $nameMap[$op1] ?? ('Operatör #' . $op1),
                    'op2_num'          => $op2,
                    'op2_name'         => $nameMap[$op2] ?? ('Operatör #' . $op2),
                    'shifts_together'  => (int)$r['shifts_together'],
                    'avg_ibc_per_hour' => $r['avg_ibc_per_hour'] !== null ? (float)$r['avg_ibc_per_hour'] : null,
                    'avg_quality'      => $r['avg_quality']      !== null ? (float)$r['avg_quality']      : null,
                ];
            }

            echo json_encode(['success' => true, 'pairs' => $pairs]);
        } catch (Exception $e) {
            error_log('OperatorController getPairs: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta pardata']);
        }
    }

    /**
     * GET ?action=operators&run=trend&op_number=<number>
     * Returnerar IBC/h och kvalitet% per vecka för senaste 8 veckorna.
     */
    private function getOperatorTrend() {
        $opNumber = isset($_GET['op_number']) ? intval($_GET['op_number']) : 0;
        if ($opNumber <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt operatörsnummer']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    YEAR(datum)                          AS year,
                    WEEK(datum, 1)                       AS week_num,
                    MIN(DATE(datum))                     AS week_start,
                    COUNT(DISTINCT id)                   AS shifts,
                    SUM(COALESCE(ibc_ok, 0))             AS ibc_ok,
                    SUM(COALESCE(totalt, 0))             AS totalt,
                    ROUND(
                        SUM(COALESCE(ibc_ok, 0)) /
                        NULLIF(SUM(COALESCE(drifttid, 0)) / 60.0, 0),
                    1) AS ibc_per_hour
                FROM rebotling_skiftrapport
                WHERE (op1 = :num OR op2 = :num OR op3 = :num)
                  AND datum >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
                GROUP BY YEAR(datum), WEEK(datum, 1)
                ORDER BY year ASC, week_num ASC
            ');
            $stmt->execute([':num' => $opNumber]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                $r['avg_quality'] = ($r['totalt'] > 0)
                    ? round(($r['ibc_ok'] / $r['totalt']) * 100, 1)
                    : null;
                $r['ibc_per_hour'] = $r['ibc_per_hour'] !== null ? (float)$r['ibc_per_hour'] : null;
                $r['shifts']       = (int)$r['shifts'];
                $r['ibc_ok']       = (int)$r['ibc_ok'];
            }
            unset($r);

            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            error_log('OperatorController getOperatorTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta trenddata']);
        }
    }
}
