<?php
/**
 * OperatorCompareController
 * GET ?action=operator-compare&run=operators-list  — lista aktiva operatörer
 * GET ?action=operator-compare&run=compare&op_a=<id>&op_b=<id>&days=<30>
 * GET ?action=operator-compare&run=radar-data&op1=<id>&op2=<id>&days=<30>
 *
 * Kräver inloggad admin-session.
 */
class OperatorCompareController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            error_log('OperatorCompareController::handle: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', role=' . ($_SESSION['role'] ?? 'none'));
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        match ($run) {
            'compare'        => $this->compare(),
            'operators-list' => $this->operatorsList(),
            'radar-data'     => $this->radarData(),
            default          => $this->sendError('Okänd metod', 400),
        };
    }

    // -------------------------------------------------------------------------
    // Endpoint: operators-list
    // -------------------------------------------------------------------------
    private function operatorsList(): void {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name FROM operators WHERE active = 1 ORDER BY name'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Typkonvertering
            foreach ($rows as &$r) {
                $r['id'] = (int)$r['id'];
            }
            unset($r);

            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('OperatorCompareController::operatorsList: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörer', 500);
        }
    }

    // -------------------------------------------------------------------------
    // Endpoint: compare
    // -------------------------------------------------------------------------
    private function compare(): void {
        $opA  = isset($_GET['op_a'])  ? intval($_GET['op_a'])  : 0;
        $opB  = isset($_GET['op_b'])  ? intval($_GET['op_b'])  : 0;
        $days = isset($_GET['days'])  ? intval($_GET['days'])  : 30;

        if ($opA <= 0 || $opB <= 0) {
            $this->sendError('Ogiltiga operatörs-ID:n', 400);
            return;
        }
        if ($days <= 0 || $days > 365) {
            $days = 30;
        }

        try {
            $dataA = $this->getOperatorStats($opA, $days);
            $dataB = $this->getOperatorStats($opB, $days);

            if (!$dataA || !$dataB) {
                $this->sendError('Kunde inte hämta operatörsdata', 500);
                return;
            }

            $trendA = $this->getWeeklyTrend($opA);
            $trendB = $this->getWeeklyTrend($opB);

            $dataA['trend_veckor'] = $trendA;
            $dataB['trend_veckor'] = $trendB;

            echo json_encode([
                'success' => true,
                'op_a'    => $dataA,
                'op_b'    => $dataB,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('OperatorCompareController::compare: ' . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }

    // -------------------------------------------------------------------------
    // Endpoint: radar-data
    // -------------------------------------------------------------------------
    private function radarData(): void {
        $op1  = isset($_GET['op1'])  ? intval($_GET['op1'])  : 0;
        $op2  = isset($_GET['op2'])  ? intval($_GET['op2'])  : 0;
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;

        if ($op1 <= 0 || $op2 <= 0) {
            $this->sendError('Ogiltiga operatörs-ID:n', 400);
            return;
        }
        if ($days <= 0 || $days > 365) {
            $days = 30;
        }

        try {
            // Hämta rådata för op1, op2 + alla aktiva operatörer för normalisering
            $allRaw  = $this->getRadarNormData($days);
            $op1Data = $this->getOperatorRadarRaw($op1, $days);
            $op2Data = $this->getOperatorRadarRaw($op2, $days);

            // Namn
            $op1Name = $this->getOpName($op1);
            $op2Name = $this->getOpName($op2);

            // Normaliseringsvärden
            $maxIbcPerH  = $allRaw['max_ibc_per_h'] > 0 ? $allRaw['max_ibc_per_h'] : 1;
            $maxCykeltid = $allRaw['max_cykeltid']  > 0 ? $allRaw['max_cykeltid']  : 1;
            $totalOps    = $allRaw['total_ops']      > 1 ? $allRaw['total_ops']      : 1;

            // Rank för op1 och op2
            $op1Rank = $this->getIbcRank($op1, $days, (int)$totalOps);
            $op2Rank = $this->getIbcRank($op2, $days, (int)$totalOps);

            // Beräkna scores
            $scores1 = $this->calcScores($op1Data, $op1Rank, $maxIbcPerH, $maxCykeltid, $totalOps, $days);
            $scores2 = $this->calcScores($op2Data, $op2Rank, $maxIbcPerH, $maxCykeltid, $totalOps, $days);

            echo json_encode([
                'success'   => true,
                'labels'    => ['IBC/h', 'Kvalitet%', 'Aktivitet', 'Cykeltid', 'Bonus-rank'],
                'operators' => [
                    [
                        'op_id'     => $op1,
                        'namn'      => $op1Name,
                        'initialer' => $this->getInitials($op1Name),
                        'scores'    => $scores1,
                    ],
                    [
                        'op_id'     => $op2,
                        'namn'      => $op2Name,
                        'initialer' => $this->getInitials($op2Name),
                        'scores'    => $scores2,
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('OperatorCompareController::radarData: ' . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }

    // -------------------------------------------------------------------------
    // Beräkna normaliserade scores (0–100) för en operatör
    // -------------------------------------------------------------------------
    private function calcScores(array $raw, float $rank, float $maxIbcPerH, float $maxCykeltid, int $totalOps, int $days): array {
        if (!$raw['has_data']) {
            return [0, 0, 0, 0, 0];
        }

        // 1. IBC/h — normaliserat mot max bland aktiva operatörer
        $ibcScore = $maxIbcPerH > 0
            ? (int)round(min(100, ($raw['ibc_per_h'] / $maxIbcPerH) * 100))
            : 0;

        // 2. Kvalitet% — direkt som 0–100
        $kvalScore = (int)round(min(100, max(0, $raw['kvalitet_pct'])));

        // 3. Aktiva dagar — andel av perioden (0–100)
        $aktivScore = (int)round(min(100, ($raw['aktiva_dagar'] / max(1, $days)) * 100));

        // 4. Cykeltid — inverterad (lägre = bättre)
        $cykelScore = $maxCykeltid > 0 && $raw['cykeltid'] > 0
            ? (int)round(max(0, 100 - (($raw['cykeltid'] / $maxCykeltid) * 100)))
            : ((float)$raw['cykeltid'] < 0.001 ? 0 : 100);

        // 5. Bonus-rank — invertering (rank 1 = 100, sista = 0)
        $rankScore = $totalOps > 1
            ? (int)round(max(0, 100 - (($rank - 1) / ($totalOps - 1)) * 100))
            : 100;

        return [$ibcScore, $kvalScore, $aktivScore, $cykelScore, $rankScore];
    }

    // -------------------------------------------------------------------------
    // Rådata för en specifik operatör (för radar)
    // -------------------------------------------------------------------------
    private function getOperatorRadarRaw(int $opId, int $days): array {
        // Slå upp operators.number (op1/op2/op3 = number)
        $numStmt = $this->pdo->prepare('SELECT number FROM operators WHERE id = ? LIMIT 1');
        $numStmt->execute([$opId]);
        $opNumber = (int)($numStmt->fetchColumn() ?: $opId);

        $sql = '
            SELECT
                COALESCE(
                    SUM(skift_ibc_ok) / NULLIF(SUM(skift_runtime_h), 0),
                    0
                ) AS ibc_per_h,
                COALESCE(
                    SUM(skift_ibc_ok) / NULLIF(SUM(skift_ibc_ok + skift_ibc_ej_ok), 0) * 100,
                    0
                ) AS kvalitet_pct,
                COUNT(DISTINCT dag) AS aktiva_dagar,
                COALESCE(
                    SUM(skift_runtime_plc) / NULLIF(SUM(skift_ibc_ok), 0),
                    0
                ) AS cykeltid
            FROM (
                SELECT
                    DATE(datum)          AS dag,
                    skiftraknare,
                    MAX(ibc_ok)    - MIN(ibc_ok)    AS skift_ibc_ok,
                    MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS skift_ibc_ej_ok,
                    MAX(runtime_plc) / 60.0          AS skift_runtime_h,
                    MAX(runtime_plc)                 AS skift_runtime_plc
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND (op1 = ? OR op2 = ? OR op3 = ?)
                GROUP BY DATE(datum), skiftraknare
            ) s
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days, $opNumber, $opNumber, $opNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)$row['aktiva_dagar'] === 0) {
            return [
                'has_data'     => false,
                'ibc_per_h'    => 0.0,
                'kvalitet_pct' => 0.0,
                'aktiva_dagar' => 0,
                'cykeltid'     => 0.0,
            ];
        }

        return [
            'has_data'     => true,
            'ibc_per_h'    => (float)$row['ibc_per_h'],
            'kvalitet_pct' => (float)$row['kvalitet_pct'],
            'aktiva_dagar' => (int)$row['aktiva_dagar'],
            'cykeltid'     => (float)$row['cykeltid'],
        ];
    }

    // -------------------------------------------------------------------------
    // Max-värden bland alla aktiva operatörer (för normalisering)
    // -------------------------------------------------------------------------
    private function getRadarNormData(int $days): array {
        $sql = '
            SELECT
                MAX(op_ibc_per_h) AS max_ibc_per_h,
                MAX(op_cykeltid)  AS max_cykeltid,
                COUNT(*)          AS total_ops
            FROM (
                SELECT
                    o.id,
                    COALESCE(
                        SUM(s.skift_ibc_ok) / NULLIF(SUM(s.skift_runtime_h), 0),
                        0
                    ) AS op_ibc_per_h,
                    COALESCE(
                        SUM(s.skift_runtime_plc) / NULLIF(SUM(s.skift_ibc_ok), 0),
                        0
                    ) AS op_cykeltid
                FROM operators o
                LEFT JOIN (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        op1, op2, op3,
                        MAX(ibc_ok)    - MIN(ibc_ok)    AS skift_ibc_ok,
                        MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS skift_ibc_ej_ok,
                        MAX(runtime_plc) / 60.0          AS skift_runtime_h,
                        MAX(runtime_plc)                 AS skift_runtime_plc
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) s ON (o.number = s.op1 OR o.number = s.op2 OR o.number = s.op3)
                WHERE o.active = 1
                GROUP BY o.id
            ) all_ops
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'max_ibc_per_h' => $row ? (float)$row['max_ibc_per_h'] : 1.0,
            'max_cykeltid'  => $row ? (float)$row['max_cykeltid']  : 1.0,
            'total_ops'     => $row ? (int)$row['total_ops']        : 1,
        ];
    }

    // -------------------------------------------------------------------------
    // Rank för en operatör baserat på IBC/h (rank 1 = bäst)
    // -------------------------------------------------------------------------
    private function getIbcRank(int $opId, int $days, int $totalOps): float {
        $sql = '
            SELECT ranked.op_id, ranked.rn
            FROM (
                SELECT
                    o.id AS op_id,
                    RANK() OVER (
                        ORDER BY COALESCE(
                            SUM(s.skift_ibc_ok) / NULLIF(SUM(s.skift_runtime_h), 0),
                            0
                        ) DESC
                    ) AS rn
                FROM operators o
                LEFT JOIN (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        op1, op2, op3,
                        MAX(ibc_ok)    - MIN(ibc_ok)    AS skift_ibc_ok,
                        MAX(runtime_plc) / 60.0          AS skift_runtime_h
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) s ON (o.number = s.op1 OR o.number = s.op2 OR o.number = s.op3)
                WHERE o.active = 1
                GROUP BY o.id
            ) ranked
            WHERE ranked.op_id = ?
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days, $opId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (float)$row['rn'] : (float)$totalOps;
    }

    // -------------------------------------------------------------------------
    // Hämta aggregerad statistik för en operatör
    // -------------------------------------------------------------------------
    private function getOperatorStats(int $opId, int $days): ?array {
        // Hämta namn och number (op1/op2/op3 = operators.number)
        $nameStmt = $this->pdo->prepare(
            'SELECT id, name, number FROM operators WHERE id = ? LIMIT 1'
        );
        $nameStmt->execute([$opId]);
        $op = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if (!$op) {
            return null;
        }
        $opNumber = (int)$op['number'];

        // Aggregera statistik via subquery (MAX per skiftraknare per dag)
        $sql = '
            SELECT
                SUM(skift_ibc_ok)                                                  AS total_ibc_ok,
                SUM(skift_ibc_ej_ok)                                               AS total_ibc_ej_ok,
                SUM(skift_ibc_ok + skift_ibc_ej_ok)                               AS total_ibc,
                SUM(skift_runtime_h)                                               AS total_runtime_h,
                COUNT(*)                                                           AS antal_skift,
                AVG(skift_ibc_ok / NULLIF(skift_runtime_h, 0))                    AS snitt_ibc_per_h,
                SUM(skift_ibc_ok) / NULLIF(SUM(skift_ibc_ok + skift_ibc_ej_ok), 0) * 100 AS kvalitet_pct
            FROM (
                SELECT
                    DATE(datum)          AS dag,
                    skiftraknare,
                    MAX(ibc_ok)    - MIN(ibc_ok)    AS skift_ibc_ok,
                    MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS skift_ibc_ej_ok,
                    MAX(runtime_plc) / 60.0          AS skift_runtime_h
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND (op1 = ? OR op2 = ? OR op3 = ?)
                GROUP BY DATE(datum), skiftraknare
            ) skift_data
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days, $opNumber, $opNumber, $opNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'id'             => (int)$op['id'],
            'name'           => $op['name'],
            'total_ibc_ok'   => $row['total_ibc_ok']   !== null ? (int)$row['total_ibc_ok']   : 0,
            'total_ibc_ej_ok'=> $row['total_ibc_ej_ok']!== null ? (int)$row['total_ibc_ej_ok']: 0,
            'total_ibc'      => $row['total_ibc']       !== null ? (int)$row['total_ibc']       : 0,
            'total_runtime_h'=> $row['total_runtime_h'] !== null ? round((float)$row['total_runtime_h'], 1) : 0.0,
            'antal_skift'    => $row['antal_skift']     !== null ? (int)$row['antal_skift']     : 0,
            'snitt_ibc_per_h'=> $row['snitt_ibc_per_h'] !== null ? round((float)$row['snitt_ibc_per_h'], 1) : 0.0,
            'kvalitet_pct'   => $row['kvalitet_pct']    !== null ? round((float)$row['kvalitet_pct'], 1) : 0.0,
        ];
    }

    // -------------------------------------------------------------------------
    // Veckovis trend senaste 8 veckor (56 dagar) för en operatör
    // -------------------------------------------------------------------------
    private function getWeeklyTrend(int $opId): array {
        // Slå upp operators.number (op1/op2/op3 = number)
        $numStmt = $this->pdo->prepare('SELECT number FROM operators WHERE id = ? LIMIT 1');
        $numStmt->execute([$opId]);
        $opNumber = (int)($numStmt->fetchColumn() ?: $opId);

        $sql = '
            SELECT
                YEARWEEK(datum, 1)  AS vecka,
                SUM(delta_ok)       AS ibc_vecka
            FROM (
                SELECT
                    DATE(datum)          AS d,
                    skiftraknare,
                    YEARWEEK(datum, 1)   AS vecka,
                    MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
                  AND (op1 = ? OR op2 = ? OR op3 = ?)
                GROUP BY DATE(datum), skiftraknare
            ) x
            GROUP BY vecka
            ORDER BY vecka
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$opNumber, $opNumber, $opNumber]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'vecka'     => (string)$r['vecka'],
                'ibc_vecka' => (int)$r['ibc_vecka'],
            ];
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Hjälpmetoder
    // -------------------------------------------------------------------------
    private function getOpName(int $opId): string {
        $stmt = $this->pdo->prepare('SELECT name FROM operators WHERE id = ? LIMIT 1');
        $stmt->execute([$opId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['name'] : 'Okänd';
    }

    private function getInitials(string $name): string {
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }
}
