<?php
/**
 * OperatorCompareController
 * GET ?action=operator-compare&run=operators-list  — lista aktiva operatörer
 * GET ?action=operator-compare&run=compare&op_a=<id>&op_b=<id>&days=<30>
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
        session_start(['read_and_close' => true]);
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
            return;
        }

        $run = $_GET['run'] ?? '';

        match ($run) {
            'compare'        => $this->compare(),
            'operators-list' => $this->operatorsList(),
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

            echo json_encode($rows);
        } catch (PDOException $e) {
            error_log('OperatorCompareController operatorsList: ' . $e->getMessage());
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
            ]);
        } catch (Exception $e) {
            error_log('OperatorCompareController compare: ' . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }

    // -------------------------------------------------------------------------
    // Hämta aggregerad statistik för en operatör
    // -------------------------------------------------------------------------
    private function getOperatorStats(int $opId, int $days): ?array {
        // Hämta namn
        $nameStmt = $this->pdo->prepare(
            'SELECT id, name FROM operators WHERE id = ? LIMIT 1'
        );
        $nameStmt->execute([$opId]);
        $op = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if (!$op) {
            return null;
        }

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
                    MAX(runtime_plc) / 3600.0        AS skift_runtime_h
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  AND (op1 = ? OR op2 = ? OR op3 = ?)
                GROUP BY DATE(datum), skiftraknare
            ) skift_data
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days, $opId, $opId, $opId]);
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
        $stmt->execute([$opId, $opId, $opId]);
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
    // Hjälpmetod: skicka felsvar
    // -------------------------------------------------------------------------
    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}
