<?php
/**
 * BemanningController — Bemanning-optimerare
 * Endpoints: ?action=bemanning&run=operator-stats | foreslag
 */
class BemanningController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET' && $run === 'operator-stats') {
            $this->getOperatorStats();
            return;
        }

        if ($method === 'GET' && $run === 'team-kombinationer') {
            $this->getTeamKombinationer();
            return;
        }

        if ($method === 'POST' && $run === 'foreslag') {
            $this->getForeslag();
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Okänd endpoint'], JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------
    // GET operator-stats
    // ---------------------------------------------------------------
    private function getOperatorStats(): void {
        $linje = $_GET['linje'] ?? 'rebotling';
        $dagar = max(1, min(365, (int)($_GET['dagar'] ?? 30)));
        $from  = date('Y-m-d', strtotime("-{$dagar} days"));

        if ($linje === 'tvattlinje') {
            $data = $this->getTvattlinjeStats($from);
        } else {
            $data = $this->getRebotlingStats($from);
        }

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------
    // Tvättlinje stats (direkta IBC-värden, ingen LAG behövs)
    // ---------------------------------------------------------------
    private function getTvattlinjeStats(string $from): array {
        $posNames = [1 => 'Påsatt', 2 => 'Spolplatform', 3 => 'Kontrollstation'];

        // Bygg UNION-frågan för op1/op2/op3
        $sql = "
            SELECT
                sub.op_id,
                o.name AS operator_namn,
                sub.position,
                COUNT(*)                                           AS skift_count,
                SUM(sub.ibc_share)                                 AS total_ibc,
                CASE WHEN SUM(sub.drifttid) > 0
                     THEN ROUND(SUM(sub.ibc_share) / (SUM(sub.drifttid) / 60.0), 2)
                     ELSE 0 END                                    AS avg_ibc_per_h
            FROM (
                SELECT
                    op1 AS op_id,
                    1   AS position,
                    totalt / GREATEST(1,
                        (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op3 > 0 THEN 1 ELSE 0 END)) AS ibc_share,
                    GREATEST(1, LEAST(drifttid, 1440)) AS drifttid
                FROM tvattlinje_skiftrapport
                WHERE datum >= :from1 AND op1 > 0 AND totalt > 0
                UNION ALL
                SELECT
                    op2, 2,
                    totalt / GREATEST(1,
                        (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op3 > 0 THEN 1 ELSE 0 END)),
                    GREATEST(1, LEAST(drifttid, 1440))
                FROM tvattlinje_skiftrapport
                WHERE datum >= :from2 AND op2 > 0 AND totalt > 0
                UNION ALL
                SELECT
                    op3, 3,
                    totalt / GREATEST(1,
                        (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op3 > 0 THEN 1 ELSE 0 END)),
                    GREATEST(1, LEAST(drifttid, 1440))
                FROM tvattlinje_skiftrapport
                WHERE datum >= :from3 AND op3 > 0 AND totalt > 0
            ) sub
            LEFT JOIN operators o ON o.number = sub.op_id
            GROUP BY sub.op_id, o.name, sub.position
            ORDER BY avg_ibc_per_h DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from1' => $from, ':from2' => $from, ':from3' => $from]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('BemanningController::getTvattlinjeStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = [];
        foreach ($rows as $r) {
            $pos = (int)$r['position'];
            $result[] = [
                'op_id'          => (int)$r['op_id'],
                'operator_namn'  => $r['operator_namn'] ?? ('Op#' . $r['op_id']),
                'linje'          => 'tvattlinje',
                'position'       => $pos,
                'position_namn'  => $posNames[$pos] ?? ("Pos{$pos}"),
                'skift_count'    => (int)$r['skift_count'],
                'total_ibc'      => round((float)$r['total_ibc'], 1),
                'avg_ibc_per_h'  => round((float)$r['avg_ibc_per_h'], 2),
            ];
        }
        return $result;
    }

    // ---------------------------------------------------------------
    // Rebotling stats (kumulativa räknare → MAX per skiftraknare)
    // ---------------------------------------------------------------
    private function getRebotlingStats(string $from): array {
        $posNames = [1 => 'Op1', 2 => 'Op2', 3 => 'Op3'];

        $sql = "
            WITH per_skift AS (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(ibc_ok)      AS ibc_end,
                    MAX(runtime_plc) AS runtime,
                    MIN(op1)         AS op1,
                    MIN(op2)         AS op2,
                    MIN(op3)         AS op3
                FROM rebotling_ibc
                WHERE datum >= :from
                GROUP BY DATE(datum), skiftraknare
            ),
            unioned AS (
                SELECT op1 AS op_id, 1 AS position,
                       ibc_end, runtime,
                       (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op3 > 0 THEN 1 ELSE 0 END) AS nops
                FROM per_skift WHERE op1 > 0 AND ibc_end > 0
                UNION ALL
                SELECT op2, 2,
                       ibc_end, runtime,
                       (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op3 > 0 THEN 1 ELSE 0 END)
                FROM per_skift WHERE op2 > 0 AND ibc_end > 0
                UNION ALL
                SELECT op3, 3,
                       ibc_end, runtime,
                       (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op3 > 0 THEN 1 ELSE 0 END)
                FROM per_skift WHERE op3 > 0 AND ibc_end > 0
            )
            SELECT
                u.op_id,
                o.name AS operator_namn,
                u.position,
                COUNT(*) AS skift_count,
                SUM(u.ibc_end / GREATEST(1, u.nops)) AS total_ibc,
                CASE WHEN SUM(u.runtime) > 0
                     THEN ROUND(SUM(u.ibc_end / GREATEST(1, u.nops)) / (SUM(u.runtime) / 60.0), 2)
                     ELSE 0 END AS avg_ibc_per_h
            FROM unioned u
            LEFT JOIN operators o ON o.number = u.op_id
            GROUP BY u.op_id, o.name, u.position
            ORDER BY avg_ibc_per_h DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('BemanningController::getRebotlingStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = [];
        foreach ($rows as $r) {
            $pos = (int)$r['position'];
            $result[] = [
                'op_id'         => (int)$r['op_id'],
                'operator_namn' => $r['operator_namn'] ?? ('Op#' . $r['op_id']),
                'linje'         => 'rebotling',
                'position'      => $pos,
                'position_namn' => $posNames[$pos] ?? ("Pos{$pos}"),
                'skift_count'   => (int)$r['skift_count'],
                'total_ibc'     => round((float)$r['total_ibc'], 1),
                'avg_ibc_per_h' => round((float)$r['avg_ibc_per_h'], 2),
            ];
        }
        return $result;
    }

    // ---------------------------------------------------------------
    // POST foreslag
    // ---------------------------------------------------------------
    private function getForeslag(): void {
        // Auth check
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Inte inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $body = file_get_contents('php://input');
        $input = json_decode($body, true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $linje       = in_array($input['linje'] ?? '', ['tvattlinje', 'rebotling'], true)
                         ? $input['linje']
                         : 'rebotling';
        $rawOps      = $input['available_ops'] ?? [];
        $availableIds = array_values(array_filter(
            array_map('intval', (array)$rawOps),
            fn($v) => $v > 0
        ));
        $dagar = max(1, min(365, (int)($input['dagar'] ?? 30)));
        $from  = date('Y-m-d', strtotime("-{$dagar} days"));

        if ($linje === 'tvattlinje') {
            $stats = $this->getTvattlinjeStats($from);
            $posNames = [1 => 'Påsatt', 2 => 'Spolplatform', 3 => 'Kontrollstation'];
        } else {
            $stats = $this->getRebotlingStats($from);
            $posNames = [1 => 'Op1', 2 => 'Op2', 3 => 'Op3'];
        }

        // Bygg en karta: op_id → [pos => stats]
        $opPosBest = [];
        foreach ($stats as $row) {
            $opId = $row['op_id'];
            $pos  = $row['position'];
            if (!isset($opPosBest[$opId][$pos]) ||
                $row['avg_ibc_per_h'] > $opPosBest[$opId][$pos]['avg_ibc_per_h']) {
                $opPosBest[$opId][$pos] = $row;
            }
        }

        // Hämta operatörsnamn för alla available_ops (inkl. de utan historik)
        $opNames = [];
        if (!empty($availableIds)) {
            $placeholders = implode(',', array_fill(0, count($availableIds), '?'));
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT number, name FROM operators WHERE number IN ($placeholders)"
                );
                $stmt->execute($availableIds);
                foreach ($stmt->fetchAll() as $row) {
                    $opNames[(int)$row['number']] = $row['name'];
                }
            } catch (\Throwable $e) {
                error_log('BemanningController::getForeslag opNames: ' . $e->getMessage());
            }
        }

        // Greedy-allokering per position (1, 2, 3)
        $pool   = $availableIds; // kopia
        $result = [];
        $totalEstimated = 0.0;

        for ($pos = 1; $pos <= 3; $pos++) {
            $bestScore = -1.0;
            $bestOpId  = null;

            foreach ($pool as $opId) {
                $score = isset($opPosBest[$opId][$pos])
                    ? (float)$opPosBest[$opId][$pos]['avg_ibc_per_h']
                    : 0.0;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestOpId  = $opId;
                }
            }

            if ($bestOpId !== null) {
                $skiftCount = isset($opPosBest[$bestOpId][$pos])
                    ? (int)$opPosBest[$bestOpId][$pos]['skift_count']
                    : 0;
                $confidence = $skiftCount >= 5 ? 'high'
                    : ($skiftCount >= 2 ? 'medium'
                    : ($skiftCount >= 1 ? 'low' : 'none'));

                $result["pos{$pos}"] = [
                    'op_id'         => $bestOpId,
                    'namn'          => $opNames[$bestOpId] ?? ('Op#' . $bestOpId),
                    'position_namn' => $posNames[$pos],
                    'avg_ibc_per_h' => round($bestScore, 2),
                    'skift_count'   => $skiftCount,
                    'confidence'    => $confidence,
                ];
                $totalEstimated += $bestScore;
                // Ta bort ur poolen
                $pool = array_values(array_filter($pool, fn($id) => $id !== $bestOpId));
            } else {
                $result["pos{$pos}"] = null;
            }
        }

        echo json_encode([
            'success'                 => true,
            'data'                    => $result,
            'total_estimated_ibc_h'   => round($totalEstimated, 1),
        ], JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------
    // GET team-kombinationer — historiska teamkombos med IBC/h
    // ?action=bemanning&run=team-kombinationer&linje=tvattlinje&dagar=60
    // ---------------------------------------------------------------
    private function getTeamKombinationer(): void {
        $linje = $_GET['linje'] ?? 'tvattlinje';
        $dagar = max(1, min(365, (int)($_GET['dagar'] ?? 60)));
        $from  = date('Y-m-d', strtotime("-{$dagar} days"));

        if ($linje !== 'tvattlinje') {
            echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Hämta operatörsnamn
        $opNames = [];
        try {
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators ORDER BY name");
            foreach ($stmtOps->fetchAll() as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }
        } catch (\Throwable $e) {
            error_log('BemanningController::getTeamKombinationer opNames: ' . $e->getMessage());
        }

        try {
            $sql = "
                SELECT
                    COALESCE(op1, 0) AS op1_id,
                    COALESCE(op2, 0) AS op2_id,
                    COALESCE(op3, 0) AS op3_id,
                    COUNT(*)         AS skift_count,
                    SUM(totalt)      AS total_ibc,
                    ROUND(AVG(CASE WHEN drifttid > 0
                        THEN totalt / (drifttid / 60.0) ELSE NULL END), 2) AS snitt_ibc_per_h,
                    ROUND(AVG(totalt), 1) AS snitt_per_skift
                FROM tvattlinje_skiftrapport
                WHERE datum >= :from AND totalt > 0 AND drifttid > 0
                GROUP BY COALESCE(op1, 0), COALESCE(op2, 0), COALESCE(op3, 0)
                HAVING COUNT(*) >= 3
                ORDER BY snitt_ibc_per_h DESC
                LIMIT 20
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('BemanningController::getTeamKombinationer query: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $posNamn = [1 => 'Påsatt', 2 => 'Spolplatform', 3 => 'Kontrollstation'];
        $result = [];
        foreach ($rows as $r) {
            $ops = [];
            foreach ([1 => (int)$r['op1_id'], 2 => (int)$r['op2_id'], 3 => (int)$r['op3_id']] as $pos => $opId) {
                if ($opId > 0) {
                    $ops[] = [
                        'op_id'    => $opId,
                        'namn'     => $opNames[$opId] ?? "Op{$opId}",
                        'position' => $pos,
                        'pos_namn' => $posNamn[$pos],
                    ];
                }
            }
            $result[] = [
                'op1_id'         => (int)$r['op1_id'],
                'op2_id'         => (int)$r['op2_id'],
                'op3_id'         => (int)$r['op3_id'],
                'operatorer'     => $ops,
                'skift_count'    => (int)$r['skift_count'],
                'total_ibc'      => round((float)$r['total_ibc'], 0),
                'snitt_ibc_per_h'=> round((float)$r['snitt_ibc_per_h'], 2),
                'snitt_per_skift'=> round((float)$r['snitt_per_skift'], 1),
            ];
        }

        echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
    }
}
