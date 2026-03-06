<?php
require_once __DIR__ . '/AuditController.php';

class OperatorController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
        if (session_status() === PHP_SESSION_NONE) {
            if ($isPost) {
                session_start();
            } else {
                session_start(['read_and_close' => true]);
            }
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Endast admin har behörighet.']);
            return;
        }
        global $pdo;
        AuditLogger::ensureTable($pdo);

        if ($isPost) {
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';

            if ($action === 'create') {
                $name = strip_tags(trim($data['name'] ?? ''));
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
                $name = strip_tags(trim($data['name'] ?? ''));
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
        $run = trim($_GET['run'] ?? '');
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
        if ($run === 'profile') {
            $this->getProfile();
            return;
        }
        if ($run === 'machine-compatibility') {
            $this->getMachineCompatibility();
            return;
        }

        // GET - Hämta alla operatörer med senaste aktivitet
        try {
            $stmt = $pdo->query("
                SELECT o.*,
                    MAX(r.datum) AS senaste_aktivitet,
                    COUNT(DISTINCT DATE(r.datum)) AS aktiva_dagar_30d
                FROM operators o
                LEFT JOIN (
                    SELECT datum, op1_id AS op_id FROM rebotling_ibc WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    UNION ALL
                    SELECT datum, op2_id FROM rebotling_ibc WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND op2_id IS NOT NULL
                    UNION ALL
                    SELECT datum, op3_id FROM rebotling_ibc WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND op3_id IS NOT NULL
                ) r ON r.op_id = o.id
                GROUP BY o.id
                ORDER BY o.number
            ");
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
     * GET ?action=operator&run=profile&id=123
     * Returnerar en fullständig profil för operatören med det givna id:t.
     */
    private function getProfile() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt operatörs-ID']);
            return;
        }

        try {
            // -------------------------------------------------------
            // 1. Grundinfo om operatören
            // -------------------------------------------------------
            $stmt = $this->pdo->prepare(
                "SELECT id, name, number, active, created_at FROM operators WHERE id = ?"
            );
            $stmt->execute([$id]);
            $op = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$op) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Operatör hittades inte']);
                return;
            }

            // Bygg initialer från namn
            $nameParts = explode(' ', trim($op['name']));
            $initialer = '';
            foreach ($nameParts as $part) {
                if ($part !== '') $initialer .= strtoupper(substr($part, 0, 1));
            }
            $initialer = substr($initialer, 0, 2);

            $operator = [
                'id'           => (int)$op['id'],
                'namn'         => $op['name'],
                'initialer'    => $initialer,
                'aktiv'        => (bool)$op['active'],
                'nummer'       => (int)$op['number'],
                'skapad_datum' => $op['created_at'],
            ];

            // -------------------------------------------------------
            // 2. Stats senaste 30 dagarna (rebotling_ibc — kumulativa fält)
            //    Aggregering: MAX() per skiftraknare → SUM() per period
            // -------------------------------------------------------
            $stmt30 = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT skiftraknare)                                  AS skift_count,
                    SUM(shift_ibc_ok)                                             AS total_ibc,
                    ROUND(SUM(shift_ibc_ok) / NULLIF(SUM(shift_runtime_sek) / 3600.0, 0), 1) AS avg_ibc_per_h,
                    ROUND(
                        SUM(shift_ibc_ok) * 100.0 /
                        NULLIF(SUM(shift_ibc_ok) + SUM(shift_ibc_ej_ok), 0),
                    1) AS avg_quality_pct
                FROM (
                    SELECT skiftraknare,
                           MAX(ibc_ok)      AS shift_ibc_ok,
                           MAX(ibc_ej_ok)   AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt30->execute([$id, $id, $id]);
            $row30 = $stmt30->fetch(PDO::FETCH_ASSOC);

            $stats_30d = [
                'total_ibc'       => (int)($row30['total_ibc']       ?? 0),
                'avg_ibc_per_h'   => $row30['avg_ibc_per_h']   !== null ? (float)$row30['avg_ibc_per_h']   : null,
                'avg_quality_pct' => $row30['avg_quality_pct'] !== null ? (float)$row30['avg_quality_pct'] : null,
                'avg_oee'         => null, // Beräknas inte separat just nu
                'skift_count'     => (int)($row30['skift_count']     ?? 0),
            ];

            // -------------------------------------------------------
            // 3. All-time stats
            // -------------------------------------------------------
            $stmtAll = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc_ok)  AS total_ibc_all_time,
                    MAX(shift_ibc_ok)  AS bast_ibc_skift,
                    MAX(first_datum)   AS bast_datum,
                    ROUND(MAX(CASE WHEN shift_runtime_sek > 0
                        THEN shift_ibc_ok / (shift_runtime_sek / 3600.0)
                        ELSE NULL END), 1) AS bast_ibc_per_h_ever
                FROM (
                    SELECT skiftraknare,
                           MAX(ibc_ok)      AS shift_ibc_ok,
                           MAX(runtime_plc) AS shift_runtime_sek,
                           MIN(datum)       AS first_datum
                    FROM rebotling_ibc
                    WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmtAll->execute([$id, $id, $id]);
            $rowAll = $stmtAll->fetch(PDO::FETCH_ASSOC);

            // Hämta datum för bästa skiftet (max ibc_ok)
            $stmtBestDate = $this->pdo->prepare("
                SELECT MIN(datum) AS bast_datum
                FROM rebotling_ibc
                WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                  AND skiftraknare = (
                      SELECT skiftraknare
                      FROM rebotling_ibc
                      WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                        AND skiftraknare IS NOT NULL
                      GROUP BY skiftraknare
                      ORDER BY MAX(ibc_ok) DESC
                      LIMIT 1
                  )
                LIMIT 1
            ");
            $stmtBestDate->execute([$id, $id, $id, $id, $id, $id]);
            $bestDatumRow = $stmtBestDate->fetch(PDO::FETCH_ASSOC);

            $stats_all = [
                'total_ibc_all_time'  => (int)($rowAll['total_ibc_all_time']  ?? 0),
                'bast_ibc_per_h_ever' => $rowAll['bast_ibc_per_h_ever'] !== null ? (float)$rowAll['bast_ibc_per_h_ever'] : null,
                'bast_ibc_skift'      => (int)($rowAll['bast_ibc_skift']      ?? 0),
                'bast_datum'          => $bestDatumRow['bast_datum'] ?? null,
            ];

            // -------------------------------------------------------
            // 4. Trenddata per vecka — senaste 8 veckorna (UNION ALL op1/op2/op3)
            // -------------------------------------------------------
            $stmtTrend = $this->pdo->prepare("
                SELECT
                    YEARWEEK(first_datum, 1)                                      AS yw,
                    SUM(shift_ibc_ok)                                             AS ibc,
                    ROUND(SUM(shift_ibc_ok) / NULLIF(SUM(shift_runtime_sek) / 3600.0, 0), 1) AS ibc_per_h,
                    ROUND(
                        SUM(shift_ibc_ok) * 100.0 /
                        NULLIF(SUM(shift_ibc_ok) + SUM(shift_ibc_ej_ok), 0),
                    1) AS quality_pct,
                    MIN(first_datum) AS vecka_start
                FROM (
                    SELECT skiftraknare, MIN(datum) AS first_datum,
                           MAX(ibc_ok)      AS shift_ibc_ok,
                           MAX(ibc_ej_ok)   AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE op1 = ? AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 56 DAY)
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT skiftraknare, MIN(datum) AS first_datum,
                           MAX(ibc_ok)      AS shift_ibc_ok,
                           MAX(ibc_ej_ok)   AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE op2 = ? AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 56 DAY)
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT skiftraknare, MIN(datum) AS first_datum,
                           MAX(ibc_ok)      AS shift_ibc_ok,
                           MAX(ibc_ej_ok)   AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE op3 = ? AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 56 DAY)
                    GROUP BY skiftraknare
                ) AS t
                GROUP BY YEARWEEK(first_datum, 1)
                ORDER BY yw DESC
                LIMIT 8
            ");
            $stmtTrend->execute([$id, $id, $id]);
            $trendRows = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

            $trend_weekly = array_reverse(array_map(function ($r) {
                $yw = (int)$r['yw'];
                $year = intdiv($yw, 100);
                $week = $yw % 100;
                return [
                    'vecka'       => sprintf('%d-V%02d', $year, $week),
                    'yw'          => $yw,
                    'ibc'         => (int)$r['ibc'],
                    'ibc_per_h'   => $r['ibc_per_h']   !== null ? (float)$r['ibc_per_h']   : null,
                    'quality_pct' => $r['quality_pct'] !== null ? (float)$r['quality_pct'] : null,
                    'vecka_start' => $r['vecka_start'],
                ];
            }, $trendRows));

            // -------------------------------------------------------
            // 5. Senaste 5 skift (detaljerat)
            // -------------------------------------------------------
            $stmtShifts = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    MIN(datum)       AS datum,
                    MAX(ibc_ok)      AS ibc,
                    MAX(ibc_ej_ok)   AS ibc_ej_ok,
                    MAX(runtime_plc) AS runtime_sek
                FROM rebotling_ibc
                WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                  AND skiftraknare IS NOT NULL
                GROUP BY skiftraknare
                ORDER BY skiftraknare DESC
                LIMIT 5
            ");
            $stmtShifts->execute([$id, $id, $id]);
            $shiftRows = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);

            $recent_shifts = array_map(function ($r) {
                $ibc      = (int)$r['ibc'];
                $ej_ok    = (int)$r['ibc_ej_ok'];
                $runtime  = (int)$r['runtime_sek'];
                $ibc_per_h = ($runtime > 0) ? round($ibc / ($runtime / 3600.0), 1) : null;
                $totalt    = $ibc + $ej_ok;
                $qual_pct  = ($totalt > 0) ? round($ibc * 100.0 / $totalt, 1) : null;
                return [
                    'datum'        => substr($r['datum'], 0, 10),
                    'skiftnr'      => (int)$r['skiftraknare'],
                    'ibc'          => $ibc,
                    'ibc_per_h'    => $ibc_per_h,
                    'quality_pct'  => $qual_pct,
                    'runtime_min'  => $runtime > 0 ? round($runtime / 60) : null,
                ];
            }, $shiftRows);

            // -------------------------------------------------------
            // 6. Certifieringar (om tabellen finns)
            // -------------------------------------------------------
            $certifications = [];
            try {
                $stmtCert = $this->pdo->prepare("
                    SELECT line, certified_date, expires_date, notes, active
                    FROM operator_certifications
                    WHERE op_number = ? AND active = 1
                    ORDER BY line ASC, certified_date DESC
                ");
                $stmtCert->execute([$op['number']]);
                $certifications = $stmtCert->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $certEx) {
                // Tabell kanske inte finns ännu — returnera tom array
                error_log('OperatorController getProfile certifications: ' . $certEx->getMessage());
                $certifications = [];
            }

            // -------------------------------------------------------
            // 7. Achievements
            // -------------------------------------------------------

            // 100 IBC på ett skift
            $has100 = false;
            if ($stats_all['bast_ibc_skift'] >= 100) {
                $has100 = true;
            }

            // 95%+ kvalitet senaste aktiva veckan (minst 2 skift)
            $has95QualWeek = false;
            if (!empty($trend_weekly)) {
                $lastWeek = end($trend_weekly);
                if ($lastWeek['quality_pct'] !== null && $lastWeek['quality_pct'] >= 95.0) {
                    $has95QualWeek = true;
                }
            }

            // Streak: antal dagar i rad med aktivitet (senaste 90 dagar)
            $stmtStreak = $this->pdo->prepare("
                SELECT DISTINCT DATE(datum) AS active_date
                FROM rebotling_ibc
                WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                  AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                ORDER BY active_date DESC
            ");
            $stmtStreak->execute([$id, $id, $id]);
            $streakDates = $stmtStreak->fetchAll(PDO::FETCH_COLUMN);

            $streakDays = 0;
            if (!empty($streakDates)) {
                $prev = new DateTime('today');
                foreach ($streakDates as $ds) {
                    $dt = new DateTime($ds);
                    $diff = (int)$prev->diff($dt)->days;
                    if ($diff <= 1) {
                        $streakDays++;
                        $prev = $dt;
                    } else {
                        break;
                    }
                }
            }

            $achievements = [
                'has_100_ibc_day'      => $has100,
                'has_95_quality_week'  => $has95QualWeek,
                'streak_days'          => $streakDays,
            ];

            // -------------------------------------------------------
            // 8. Rank denna vecka
            // -------------------------------------------------------
            $stmtRank = $this->pdo->prepare("
                SELECT ranked.op_id, ranked.rn AS rank_pos,
                       (SELECT COUNT(DISTINCT op_id) FROM (
                            SELECT op1 AS op_id FROM rebotling_ibc
                            WHERE op1 IS NOT NULL AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            UNION
                            SELECT op2 FROM rebotling_ibc
                            WHERE op2 IS NOT NULL AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            UNION
                            SELECT op3 FROM rebotling_ibc
                            WHERE op3 IS NOT NULL AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       ) AS all_ops WHERE op_id IS NOT NULL) AS total_ops
                FROM (
                    SELECT sub.op_id,
                           RANK() OVER (ORDER BY SUM(sub.shift_ibc_ok) / NULLIF(SUM(sub.shift_runtime_sek) / 3600.0, 0) DESC) AS rn
                    FROM (
                        SELECT op1 AS op_id, skiftraknare,
                               MAX(ibc_ok)      AS shift_ibc_ok,
                               MAX(runtime_plc) AS shift_runtime_sek
                        FROM rebotling_ibc
                        WHERE op1 IS NOT NULL AND skiftraknare IS NOT NULL
                          AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY op1, skiftraknare
                        UNION ALL
                        SELECT op2, skiftraknare,
                               MAX(ibc_ok), MAX(runtime_plc)
                        FROM rebotling_ibc
                        WHERE op2 IS NOT NULL AND skiftraknare IS NOT NULL
                          AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY op2, skiftraknare
                        UNION ALL
                        SELECT op3, skiftraknare,
                               MAX(ibc_ok), MAX(runtime_plc)
                        FROM rebotling_ibc
                        WHERE op3 IS NOT NULL AND skiftraknare IS NOT NULL
                          AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY op3, skiftraknare
                    ) sub
                    GROUP BY sub.op_id
                ) ranked
                WHERE ranked.op_id = ?
                LIMIT 1
            ");
            $stmtRank->execute([$id]);
            $rankRow = $stmtRank->fetch(PDO::FETCH_ASSOC);

            $rank_this_week = [
                'rank'       => $rankRow ? (int)$rankRow['rank_pos']  : null,
                'total_ops'  => $rankRow ? (int)$rankRow['total_ops'] : null,
            ];

            // -------------------------------------------------------
            // Svara
            // -------------------------------------------------------
            echo json_encode([
                'success'       => true,
                'operator'      => $operator,
                'stats_30d'     => $stats_30d,
                'stats_all'     => $stats_all,
                'trend_weekly'  => $trend_weekly,
                'recent_shifts' => $recent_shifts,
                'certifications' => $certifications,
                'achievements'  => $achievements,
                'rank_this_week' => $rank_this_week,
            ]);

        } catch (Exception $e) {
            error_log('OperatorController getProfile: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörsprofil']);
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

    /**
     * GET ?action=operators&run=machine-compatibility&days=90
     * Returnerar kompatibilitetsmatris: operatör × produkt med IBC/h, kvalitet, OEE.
     * Aggregerar från rebotling_ibc — UNION ALL op1/op2/op3, GROUP BY operator + produkt.
     */
    private function getMachineCompatibility() {
        try {
            $days = isset($_GET['days']) ? max(1, intval($_GET['days'])) : 90;

            $stmt = $this->pdo->prepare("
                SELECT
                    sub.op_id,
                    o.name                                                      AS operator_namn,
                    o.number                                                    AS operator_nummer,
                    sub.produkt_id,
                    COALESCE(p.name, CONCAT('Produkt #', sub.produkt_id))       AS produkt_namn,
                    COUNT(DISTINCT sub.skiftraknare)                             AS antal_skift,
                    ROUND(
                        SUM(sub.shift_ibc_ok) /
                        NULLIF(SUM(sub.shift_runtime_sek) / 3600.0, 0),
                    1)                                                          AS avg_ibc_per_h,
                    ROUND(
                        SUM(sub.shift_ibc_ok) * 100.0 /
                        NULLIF(SUM(sub.shift_ibc_ok) + SUM(sub.shift_ibc_ej_ok), 0),
                    1)                                                          AS avg_kvalitet,
                    ROUND(
                        (SUM(sub.shift_ibc_ok) * 100.0 /
                         NULLIF(SUM(sub.shift_ibc_ok) + SUM(sub.shift_ibc_ej_ok), 0))
                        *
                        (SUM(sub.shift_ibc_ok) /
                         NULLIF(SUM(sub.shift_runtime_sek) / 3600.0, 0))
                        / NULLIF(
                            COALESCE(
                                (SELECT MAX(rp2.cycle_time_minutes)
                                 FROM rebotling_products rp2
                                 WHERE rp2.id = sub.produkt_id),
                                60.0 / 10.0
                            ), 0)
                        / 100.0,
                    1)                                                          AS oee
                FROM (
                    SELECT op1 AS op_id, produkt AS produkt_id, skiftraknare,
                           MAX(ibc_ok) AS shift_ibc_ok,
                           MAX(ibc_ej_ok) AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE op1 IS NOT NULL AND produkt IS NOT NULL AND produkt > 0
                      AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY op1, produkt, skiftraknare

                    UNION ALL

                    SELECT op2 AS op_id, produkt AS produkt_id, skiftraknare,
                           MAX(ibc_ok) AS shift_ibc_ok,
                           MAX(ibc_ej_ok) AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE op2 IS NOT NULL AND op2 > 0 AND produkt IS NOT NULL AND produkt > 0
                      AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY op2, produkt, skiftraknare

                    UNION ALL

                    SELECT op3 AS op_id, produkt AS produkt_id, skiftraknare,
                           MAX(ibc_ok) AS shift_ibc_ok,
                           MAX(ibc_ej_ok) AS shift_ibc_ej_ok,
                           MAX(runtime_plc) AS shift_runtime_sek
                    FROM rebotling_ibc
                    WHERE op3 IS NOT NULL AND op3 > 0 AND produkt IS NOT NULL AND produkt > 0
                      AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY op3, produkt, skiftraknare
                ) sub
                INNER JOIN operators o ON o.id = sub.op_id
                LEFT JOIN rebotling_products p ON p.id = sub.produkt_id
                GROUP BY sub.op_id, sub.produkt_id
                HAVING antal_skift >= 1
                ORDER BY o.name ASC, avg_ibc_per_h DESC
            ");
            $stmt->execute([$days, $days, $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatera numeriska fält
            $result = [];
            foreach ($rows as $r) {
                $result[] = [
                    'operator_id'    => (int)$r['op_id'],
                    'operator_namn'  => $r['operator_namn'],
                    'operator_nummer' => (int)$r['operator_nummer'],
                    'produkt_id'     => (int)$r['produkt_id'],
                    'produkt_namn'   => $r['produkt_namn'],
                    'antal_skift'    => (int)$r['antal_skift'],
                    'avg_ibc_per_h'  => $r['avg_ibc_per_h'] !== null ? (float)$r['avg_ibc_per_h'] : null,
                    'avg_kvalitet'   => $r['avg_kvalitet']  !== null ? (float)$r['avg_kvalitet']  : null,
                    'oee'            => $r['oee']           !== null ? (float)$r['oee']           : null,
                ];
            }

            echo json_encode(['success' => true, 'data' => $result, 'days' => $days]);
        } catch (Exception $e) {
            error_log('OperatorController getMachineCompatibility: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kompatibilitetsdata']);
        }
    }
}
