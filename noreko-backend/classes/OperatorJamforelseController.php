<?php
/**
 * OperatorJamforelseController.php
 * Operatörsjämförelse sida-vid-sida — VD väljer 2-3 operatörer och ser
 * deras KPI:er jämförda i samma graf/tabell.
 *
 * Endpoints via ?action=operator-jamforelse&run=XXX:
 *
 *   run=operators-list
 *       Lista alla operatörer (id, namn) för dropdown-val.
 *       Returnerar: { operatorer: [{id, namn}] }
 *
 *   run=compare&operators=1,2,3&period=7|30|90
 *       KPI:er per vald operatör.
 *       Returnerar per operatör: totalt_ibc, ibc_per_h, kvalitetsgrad,
 *       antal_stopp, total_stopptid_min, aktiva_timmar
 *
 *   run=compare-trend&operators=1,2,3&period=30
 *       Daglig trenddata per operatör (datum, ibc_count, ibc_per_hour).
 *
 * Auth: session_id krävs (401 om ej inloggad).
 *
 * Tabeller: rebotling_skiftrapport (op1/op2/op3, ibc_ok, ibc_ej_ok,
 *           drifttid, datum), operators (number, name),
 *           stoppage_log (operator_id, duration_minutes, datum)
 */
class OperatorJamforelseController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'operators-list':  $this->getOperatorsList();   break;
            case 'compare':         $this->getCompare();         break;
            case 'compare-trend':   $this->getCompareTrend();    break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function sendSuccess(array $data): void {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parsear och validerar ?operators=1,2,3 → [1, 2, 3]
     * Max 3 operatörer, inga negativa id.
     */
    private function parseOperatorIds(): array {
        $raw = trim($_GET['operators'] ?? '');
        if ($raw === '') return [];
        $parts = explode(',', $raw);
        $ids   = [];
        foreach ($parts as $p) {
            $id = (int)trim($p);
            if ($id > 0) $ids[] = $id;
        }
        return array_unique(array_slice($ids, 0, 3));
    }

    /**
     * Validerar och returnerar period (7/30/90 dagar).
     */
    private function getPeriod(): int {
        $p = (int)($_GET['period'] ?? 30);
        if (!in_array($p, [7, 30, 90], true)) return 30;
        return $p;
    }

    /**
     * Hämta operatörsnamn som map: number => name
     */
    private function getOperatorNames(): array {
        $result = [];
        try {
            $stmt = $this->pdo->query("SELECT number, name FROM operators ORDER BY name");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['number']] = $row['name'];
            }
        } catch (\PDOException $e) {
            error_log('OperatorJamforelseController::getOperatorNames: ' . $e->getMessage());
        }
        return $result;
    }

    // ================================================================
    // run=operators-list
    // ================================================================

    private function getOperatorsList(): void {
        try {
            $stmt = $this->pdo->query(
                "SELECT number AS id, name AS namn
                 FROM operators
                 WHERE active = 1
                 ORDER BY name"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $operatorer = array_map(function($r) {
                return ['id' => (int)$r['id'], 'namn' => $r['namn']];
            }, $rows);

            $this->sendSuccess(['operatorer' => $operatorer]);
        } catch (\Exception $e) {
            error_log('OperatorJamforelseController::getOperatorsList: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta operatorslista', 500);
        }
    }

    // ================================================================
    // run=compare
    // ================================================================

    private function getCompare(): void {
        $ids    = $this->parseOperatorIds();
        $period = $this->getPeriod();

        if (count($ids) < 1) {
            $this->sendError('Minst en operator maste valjas');
            return;
        }

        try {
            $opNames = $this->getOperatorNames();
            $cutoff  = date('Y-m-d', strtotime("-{$period} days"));
            $result  = [];

            // Prepare statements utanför loopen för bättre prestanda
            $stmt = $this->pdo->prepare(
                "SELECT
                    SUM(sub.ibc_ok)    AS total_ibc_ok,
                    SUM(sub.ibc_ej_ok) AS total_ibc_ej_ok,
                    SUM(sub.drifttid)  AS total_drifttid
                 FROM (
                    SELECT ibc_ok, COALESCE(ibc_ej_ok, 0) AS ibc_ej_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 = ? AND datum >= ?
                    UNION ALL
                    SELECT ibc_ok, COALESCE(ibc_ej_ok, 0), COALESCE(drifttid, 0)
                    FROM rebotling_skiftrapport
                    WHERE op2 = ? AND datum >= ?
                    UNION ALL
                    SELECT ibc_ok, COALESCE(ibc_ej_ok, 0), COALESCE(drifttid, 0)
                    FROM rebotling_skiftrapport
                    WHERE op3 = ? AND datum >= ?
                 ) AS sub"
            );
            $stmtStopp = $this->pdo->prepare(
                "SELECT COUNT(*) AS antal, COALESCE(SUM(duration_minutes), 0) AS total_min
                 FROM stoppage_log
                 WHERE user_id = ? AND start_time >= ?"
            );
            $stmtStopp2 = $this->pdo->prepare(
                "SELECT COUNT(*) AS antal, COALESCE(SUM(sub.stopp_min), 0) AS total_min
                 FROM (
                    SELECT COALESCE(stopp_min, 0) AS stopp_min
                    FROM rebotling_skiftrapport
                    WHERE op1 = ? AND datum >= ? AND COALESCE(stopp_min, 0) > 0
                    UNION ALL
                    SELECT COALESCE(stopp_min, 0)
                    FROM rebotling_skiftrapport
                    WHERE op2 = ? AND datum >= ? AND COALESCE(stopp_min, 0) > 0
                    UNION ALL
                    SELECT COALESCE(stopp_min, 0)
                    FROM rebotling_skiftrapport
                    WHERE op3 = ? AND datum >= ? AND COALESCE(stopp_min, 0) > 0
                 ) AS sub"
            );

            foreach ($ids as $opId) {
                $namn = $opNames[$opId] ?? "Operatör #{$opId}";

                // ---- IBC och drifttid ----
                $stmt->execute([$opId, $cutoff, $opId, $cutoff, $opId, $cutoff]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                $totalIbc      = (int)($row['total_ibc_ok']    ?? 0);
                $totalIbcEjOk  = (int)($row['total_ibc_ej_ok'] ?? 0);
                $totalDrifttid = (int)($row['total_drifttid']  ?? 0);
                $aktivaTimmar  = round($totalDrifttid / 60.0, 2);
                $ibcPerH       = $totalDrifttid > 0
                    ? round($totalIbc / ($totalDrifttid / 60.0), 2)
                    : 0.0;
                $totaltIbc     = $totalIbc + $totalIbcEjOk;
                $kvalitetsgrad = $totaltIbc > 0
                    ? round(($totalIbc / $totaltIbc) * 100, 1)
                    : null;

                // ---- Stopptillfällen och stopptid ----
                // Försök via stoppage_log (om tabellen finns)
                $antalStopp    = 0;
                $totalStopptid = 0.0;
                try {
                    $stmtStopp->execute([$opId, $cutoff]);
                    $stoppRow      = $stmtStopp->fetch(\PDO::FETCH_ASSOC);
                    $antalStopp    = $stoppRow ? (int)$stoppRow['antal']     : 0;
                    $totalStopptid = $stoppRow ? (float)$stoppRow['total_min'] : 0.0;
                } catch (\PDOException $ignored) {
                    error_log('OperatorJamforelseController::getCompare stoppage_log fallback: ' . $ignored->getMessage());
                    // Tabellen kanske inte finns — använd skiftrapport stopp_min
                    try {
                        $stmtStopp2->execute([$opId, $cutoff, $opId, $cutoff, $opId, $cutoff]);
                        $stoppRow2     = $stmtStopp2->fetch(\PDO::FETCH_ASSOC);
                        $antalStopp    = $stoppRow2 ? (int)$stoppRow2['antal']     : 0;
                        $totalStopptid = $stoppRow2 ? (float)$stoppRow2['total_min'] : 0.0;
                    } catch (\PDOException $e2) {
                        error_log('OperatorJamforelseController::getCompare stopp fallback: ' . $e2->getMessage());
                    }
                }

                $result[] = [
                    'operator_id'       => $opId,
                    'namn'              => $namn,
                    'totalt_ibc'        => $totalIbc,
                    'ibc_per_h'         => $ibcPerH,
                    'kvalitetsgrad'     => $kvalitetsgrad,
                    'antal_stopp'       => $antalStopp,
                    'total_stopptid_min'=> round($totalStopptid, 1),
                    'aktiva_timmar'     => $aktivaTimmar,
                    'period_dagar'      => $period,
                ];
            }

            $this->sendSuccess([
                'period'     => $period,
                'operatorer' => $result,
            ]);

        } catch (\Exception $e) {
            error_log('OperatorJamforelseController::getCompare: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta jamforelsedata', 500);
        }
    }

    // ================================================================
    // run=compare-trend
    // ================================================================

    private function getCompareTrend(): void {
        $ids    = $this->parseOperatorIds();
        $period = $this->getPeriod();

        if (count($ids) < 1) {
            $this->sendError('Minst en operator maste valjas');
            return;
        }

        try {
            $opNames = $this->getOperatorNames();
            $cutoff  = date('Y-m-d', strtotime("-{$period} days"));
            $result  = [];

            $stmt = $this->pdo->prepare(
                "SELECT
                    sub.datum,
                    SUM(sub.ibc_ok)   AS ibc_count,
                    SUM(sub.drifttid) AS drifttid_min
                 FROM (
                    SELECT datum, ibc_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 = ? AND datum >= ?
                    UNION ALL
                    SELECT datum, ibc_ok, COALESCE(drifttid, 0)
                    FROM rebotling_skiftrapport
                    WHERE op2 = ? AND datum >= ?
                    UNION ALL
                    SELECT datum, ibc_ok, COALESCE(drifttid, 0)
                    FROM rebotling_skiftrapport
                    WHERE op3 = ? AND datum >= ?
                 ) AS sub
                 GROUP BY sub.datum
                 ORDER BY sub.datum ASC"
            );

            foreach ($ids as $opId) {
                $namn = $opNames[$opId] ?? "Operatör #{$opId}";

                $stmt->execute([$opId, $cutoff, $opId, $cutoff, $opId, $cutoff]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $trend = [];
                foreach ($rows as $r) {
                    $ibc    = (int)$r['ibc_count'];
                    $drift  = (int)$r['drifttid_min'];
                    $ibcH   = $drift > 0 ? round($ibc / ($drift / 60.0), 2) : 0.0;
                    $trend[] = [
                        'datum'        => $r['datum'],
                        'ibc_count'    => $ibc,
                        'ibc_per_hour' => $ibcH,
                    ];
                }

                $result[] = [
                    'operator_id' => $opId,
                    'namn'        => $namn,
                    'trend'       => $trend,
                ];
            }

            $this->sendSuccess([
                'period'     => $period,
                'operatorer' => $result,
            ]);

        } catch (\Exception $e) {
            error_log('OperatorJamforelseController::getCompareTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta trenddata', 500);
        }
    }
}
