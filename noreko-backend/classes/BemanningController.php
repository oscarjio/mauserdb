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
            if (class_exists('RemoteAgg') && RemoteAgg::enabled() && RemoteAgg::passthru('bemanning')) return;
            $this->getOperatorStats();
            return;
        }

        if ($method === 'GET' && $run === 'team-kombinationer') {
            if (class_exists('RemoteAgg') && RemoteAgg::enabled() && RemoteAgg::passthru('bemanning')) return;
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
                    sr.op1 AS op_id,
                    1   AS position,
                    sr.totalt / GREATEST(1,
                        (CASE WHEN sr.op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN sr.op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN sr.op3 > 0 THEN 1 ELSE 0 END)) AS ibc_share,
                    LEAST(sr.drifttid, 600) AS drifttid
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id FROM tvattlinje_skiftrapport
                    WHERE datum >= :from1
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                WHERE sr.op1 > 0 AND sr.totalt > 0 AND sr.drifttid > 0
                UNION ALL
                SELECT
                    sr.op2, 2,
                    sr.totalt / GREATEST(1,
                        (CASE WHEN sr.op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN sr.op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN sr.op3 > 0 THEN 1 ELSE 0 END)),
                    LEAST(sr.drifttid, 600)
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id FROM tvattlinje_skiftrapport
                    WHERE datum >= :from2
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                WHERE sr.op2 > 0 AND sr.totalt > 0 AND sr.drifttid > 0
                UNION ALL
                SELECT
                    sr.op3, 3,
                    sr.totalt / GREATEST(1,
                        (CASE WHEN sr.op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN sr.op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN sr.op3 > 0 THEN 1 ELSE 0 END)),
                    LEAST(sr.drifttid, 600)
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id FROM tvattlinje_skiftrapport
                    WHERE datum >= :from3
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                WHERE sr.op3 > 0 AND sr.totalt > 0 AND sr.drifttid > 0
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
                    DATE(MIN(datum))  AS dag,
                    skiftraknare,
                    MAX(ibc_ok)      AS ibc_end,
                    MAX(runtime_plc) AS runtime_end,
                    MIN(op1)         AS op1,
                    MIN(op2)         AS op2,
                    MIN(op3)         AS op3
                FROM rebotling_ibc
                WHERE datum >= :from
                GROUP BY skiftraknare
            ),
            per_skift_delta AS (
                SELECT
                    dag,
                    skiftraknare,
                    CASE WHEN ibc_end >= COALESCE(LAG(ibc_end) OVER (ORDER BY skiftraknare), 0)
                         THEN ibc_end - COALESCE(LAG(ibc_end) OVER (ORDER BY skiftraknare), 0)
                         ELSE ibc_end END AS ibc_end,
                    CASE WHEN runtime_end >= COALESCE(LAG(runtime_end) OVER (ORDER BY skiftraknare), 0)
                         THEN runtime_end - COALESCE(LAG(runtime_end) OVER (ORDER BY skiftraknare), 0)
                         ELSE runtime_end END AS runtime,
                    op1, op2, op3
                FROM per_skift
            ),
            unioned AS (
                SELECT op1 AS op_id, 1 AS position,
                       ibc_end, runtime,
                       (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op3 > 0 THEN 1 ELSE 0 END) AS nops
                FROM per_skift_delta WHERE op1 > 0 AND ibc_end > 0
                UNION ALL
                SELECT op2, 2,
                       ibc_end, runtime,
                       (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op3 > 0 THEN 1 ELSE 0 END)
                FROM per_skift_delta WHERE op2 > 0 AND ibc_end > 0
                UNION ALL
                SELECT op3, 3,
                       ibc_end, runtime,
                       (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                        CASE WHEN op3 > 0 THEN 1 ELSE 0 END)
                FROM per_skift_delta WHERE op3 > 0 AND ibc_end > 0
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
                // Ta bort ur poolen
                $pool = array_values(array_filter($pool, fn($id) => $id !== $bestOpId));
            } else {
                $result["pos{$pos}"] = null;
            }
        }

        // Slå upp verklig kombinations-linjetakt för den valda trion
        $chosenOps = array_filter([
            $result['pos1']['op_id'] ?? null,
            $result['pos2']['op_id'] ?? null,
            $result['pos3']['op_id'] ?? null,
        ], fn($v) => $v !== null);

        $komboIbcPerH   = null;
        $komboKonfidens = 'ingen_historik';

        if (count($chosenOps) >= 2) {
            $sorted = array_values($chosenOps);
            sort($sorted);
            $komboResult = ($linje === 'tvattlinje')
                ? $this->lookupTvattlinjeKombo($sorted, $dagar)
                : $this->lookupRebotlingKombo($sorted, $dagar);
            if ($komboResult !== null) {
                $komboIbcPerH   = $komboResult['snitt_ibc_per_h'];
                $komboKonfidens = $komboResult['skift_count'] >= 5 ? 'high'
                    : ($komboResult['skift_count'] >= 2 ? 'medium' : 'low');
            }
        }

        echo json_encode([
            'success'           => true,
            'data'              => $result,
            'kombo_ibc_per_h'   => $komboIbcPerH,
            'kombo_konfidens'   => $komboKonfidens,
        ], JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------
    // Slå upp historisk kombinationstakt för tvättlinjens trio
    // $sortedOps = sorted array of op_ids (2-3 st)
    // Returnerar ['snitt_ibc_per_h' => float, 'skift_count' => int] eller null
    // ---------------------------------------------------------------
    private function lookupTvattlinjeKombo(array $sortedOps, int $dagar): ?array {
        $from = date('Y-m-d', strtotime("-{$dagar} days"));
        $op1 = $sortedOps[0] ?? 0;
        $op2 = $sortedOps[1] ?? 0;
        $op3 = $sortedOps[2] ?? 0;

        // Matcha exakt trio oavsett ordning (alla permutationer via LEAST/GREATEST)
        $sql = "
            SELECT
                COUNT(*)         AS skift_count,
                ROUND(SUM(sr.totalt) / NULLIF(SUM(LEAST(sr.drifttid, 600)) / 60.0, 0), 2) AS snitt_ibc_per_h
            FROM tvattlinje_skiftrapport sr
            INNER JOIN (
                SELECT MAX(id) AS max_id FROM tvattlinje_skiftrapport
                WHERE datum >= :from
                GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
            ) latest ON sr.id = latest.max_id
            WHERE sr.totalt > 0 AND sr.drifttid > 0
              AND LEAST(COALESCE(sr.op1,0), COALESCE(sr.op2,0), COALESCE(sr.op3,0)) = LEAST(:a, :b, :c)
              AND GREATEST(COALESCE(sr.op1,0), COALESCE(sr.op2,0), COALESCE(sr.op3,0)) = GREATEST(:a2, :b2, :c2)
              AND (COALESCE(sr.op1,0) + COALESCE(sr.op2,0) + COALESCE(sr.op3,0)) = (:a3 + :b3 + :c3)
        ";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from' => $from,
                ':a'    => $op1, ':b'  => $op2, ':c'  => $op3,
                ':a2'   => $op1, ':b2' => $op2, ':c2' => $op3,
                ':a3'   => $op1, ':b3' => $op2, ':c3' => $op3,
            ]);
            $row = $stmt->fetch();
            if ($row && (int)$row['skift_count'] > 0 && $row['snitt_ibc_per_h'] !== null) {
                return [
                    'snitt_ibc_per_h' => round((float)$row['snitt_ibc_per_h'], 2),
                    'skift_count'     => (int)$row['skift_count'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('BemanningController::lookupTvattlinjeKombo: ' . $e->getMessage());
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Slå upp historisk kombinationstakt för rebotlingens trio
    // Speglar lookupTvattlinjeKombo men mot rebotling_ibc (kumulativa räknare)
    // ---------------------------------------------------------------
    private function lookupRebotlingKombo(array $sortedOps, int $dagar): ?array {
        $from = date('Y-m-d', strtotime("-{$dagar} days"));
        $op1 = $sortedOps[0] ?? 0;
        $op2 = $sortedOps[1] ?? 0;
        $op3 = $sortedOps[2] ?? 0;

        // Aggregera kumulativa räknare → delta per skiftraknare, sedan kombinations-linjetakt
        $sql = "
            WITH per_skift AS (
                SELECT
                    skiftraknare,
                    MAX(ibc_ok)      AS ibc_end,
                    MAX(runtime_plc) AS runtime_end,
                    MIN(op1)         AS op1,
                    MIN(op2)         AS op2,
                    MIN(op3)         AS op3
                FROM rebotling_ibc
                WHERE datum >= :from
                GROUP BY skiftraknare
            ),
            per_skift_delta AS (
                SELECT
                    skiftraknare,
                    CASE WHEN ibc_end >= COALESCE(LAG(ibc_end) OVER (ORDER BY skiftraknare), 0)
                         THEN ibc_end - COALESCE(LAG(ibc_end) OVER (ORDER BY skiftraknare), 0)
                         ELSE ibc_end END AS ibc_delta,
                    CASE WHEN runtime_end >= COALESCE(LAG(runtime_end) OVER (ORDER BY skiftraknare), 0)
                         THEN runtime_end - COALESCE(LAG(runtime_end) OVER (ORDER BY skiftraknare), 0)
                         ELSE runtime_end END AS runtime_delta,
                    op1, op2, op3
                FROM per_skift
            )
            SELECT
                COUNT(*)  AS skift_count,
                ROUND(SUM(ibc_delta) / NULLIF(SUM(LEAST(runtime_delta, 600)) / 60.0, 0), 2) AS snitt_ibc_per_h
            FROM per_skift_delta
            WHERE ibc_delta > 0 AND runtime_delta > 0
              AND LEAST(COALESCE(op1,0), COALESCE(op2,0), COALESCE(op3,0)) = LEAST(:a, :b, :c)
              AND GREATEST(COALESCE(op1,0), COALESCE(op2,0), COALESCE(op3,0)) = GREATEST(:a2, :b2, :c2)
              AND (COALESCE(op1,0) + COALESCE(op2,0) + COALESCE(op3,0)) = (:a3 + :b3 + :c3)
        ";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from' => $from,
                ':a'    => $op1, ':b'  => $op2, ':c'  => $op3,
                ':a2'   => $op1, ':b2' => $op2, ':c2' => $op3,
                ':a3'   => $op1, ':b3' => $op2, ':c3' => $op3,
            ]);
            $row = $stmt->fetch();
            if ($row && (int)$row['skift_count'] > 0 && $row['snitt_ibc_per_h'] !== null) {
                return [
                    'snitt_ibc_per_h' => round((float)$row['snitt_ibc_per_h'], 2),
                    'skift_count'     => (int)$row['skift_count'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('BemanningController::lookupRebotlingKombo: ' . $e->getMessage());
        }
        return null;
    }

    // ---------------------------------------------------------------
    // GET team-kombinationer — historiska teamkombos med IBC/h
    // ?action=bemanning&run=team-kombinationer&linje=tvattlinje&dagar=60
    // ---------------------------------------------------------------
    private function getTeamKombinationer(): void {
        $linje = $_GET['linje'] ?? 'tvattlinje';
        $dagar = max(1, min(365, (int)($_GET['dagar'] ?? 60)));
        $from  = date('Y-m-d', strtotime("-{$dagar} days"));

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

        if ($linje === 'tvattlinje') {
            try {
                $sql = "
                    SELECT
                        COALESCE(sr.op1, 0) AS op1_id,
                        COALESCE(sr.op2, 0) AS op2_id,
                        COALESCE(sr.op3, 0) AS op3_id,
                        COUNT(*)         AS skift_count,
                        SUM(sr.totalt)   AS total_ibc,
                        ROUND(SUM(sr.totalt) / NULLIF(SUM(LEAST(sr.drifttid, 600)) / 60.0, 0), 2) AS snitt_ibc_per_h,
                        ROUND(AVG(sr.totalt), 1) AS snitt_per_skift
                    FROM tvattlinje_skiftrapport sr
                    INNER JOIN (
                        SELECT MAX(id) AS max_id FROM tvattlinje_skiftrapport
                        WHERE datum >= :from
                        GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                    ) latest ON sr.id = latest.max_id
                    WHERE sr.totalt > 0 AND sr.drifttid > 0
                    GROUP BY COALESCE(sr.op1, 0), COALESCE(sr.op2, 0), COALESCE(sr.op3, 0)
                    HAVING COUNT(*) >= 2
                    ORDER BY snitt_ibc_per_h DESC
                    LIMIT 20
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $from]);
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e) {
                error_log('BemanningController::getTeamKombinationer tvattlinje query: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $posNamn = [1 => 'Påsatt', 2 => 'Spolplatform', 3 => 'Kontrollstation'];
        } else {
            // Rebotling — kumulativa räknare, delta per skiftraknare
            try {
                $sql = "
                    WITH per_skift AS (
                        SELECT
                            skiftraknare,
                            MAX(ibc_ok)      AS ibc_end,
                            MAX(runtime_plc) AS runtime_end,
                            MIN(op1)         AS op1,
                            MIN(op2)         AS op2,
                            MIN(op3)         AS op3
                        FROM rebotling_ibc
                        WHERE datum >= :from
                        GROUP BY skiftraknare
                    ),
                    per_skift_delta AS (
                        SELECT
                            skiftraknare,
                            CASE WHEN ibc_end >= COALESCE(LAG(ibc_end) OVER (ORDER BY skiftraknare), 0)
                                 THEN ibc_end - COALESCE(LAG(ibc_end) OVER (ORDER BY skiftraknare), 0)
                                 ELSE ibc_end END AS ibc_delta,
                            CASE WHEN runtime_end >= COALESCE(LAG(runtime_end) OVER (ORDER BY skiftraknare), 0)
                                 THEN runtime_end - COALESCE(LAG(runtime_end) OVER (ORDER BY skiftraknare), 0)
                                 ELSE runtime_end END AS runtime_delta,
                            op1, op2, op3
                        FROM per_skift
                    )
                    SELECT
                        COALESCE(op1, 0) AS op1_id,
                        COALESCE(op2, 0) AS op2_id,
                        COALESCE(op3, 0) AS op3_id,
                        COUNT(*)         AS skift_count,
                        SUM(ibc_delta)   AS total_ibc,
                        ROUND(SUM(ibc_delta) / NULLIF(SUM(LEAST(runtime_delta, 600)) / 60.0, 0), 2) AS snitt_ibc_per_h,
                        ROUND(AVG(ibc_delta), 1) AS snitt_per_skift
                    FROM per_skift_delta
                    WHERE ibc_delta > 0 AND runtime_delta > 0
                    GROUP BY COALESCE(op1, 0), COALESCE(op2, 0), COALESCE(op3, 0)
                    HAVING COUNT(*) >= 2
                    ORDER BY snitt_ibc_per_h DESC
                    LIMIT 20
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $from]);
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e) {
                error_log('BemanningController::getTeamKombinationer rebotling query: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $posNamn = [1 => 'Op1', 2 => 'Op2', 3 => 'Op3'];
        }

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
