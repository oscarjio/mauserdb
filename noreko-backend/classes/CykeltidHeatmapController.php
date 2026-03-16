<?php
/**
 * CykeltidHeatmapController.php
 * Cykeltids-heatmap per operatör per timme på dygnet.
 *
 * Endpoints via ?action=cykeltid-heatmap&run=XXX:
 *   - run=heatmap         → matris: rader=operatörer, kolumner=timmar, celler=snittcykeltid (sek)
 *   - run=day-pattern     → aggregerad timmevy (alla operatörer ihop): snittcykeltid + antal IBC per timme
 *   - run=operator-detail → detaljerad vy för en operatör: ?operator_id=X&days=30
 *
 * Cykeltid beräknas via LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum).
 * Filtreringsregler: cykeltid 30–1800 sek (0.5–30 min) för att utesluta stopp/fel.
 *
 * Tabeller som används:
 *   rebotling_ibc  (datum, op1, op2, op3, skiftraknare, lopnummer)
 *   operators      (number, name)
 */
class CykeltidHeatmapController {
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
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'heatmap':          $this->getHeatmap();        break;
            case 'day-pattern':      $this->getDayPattern();     break;
            case 'operator-detail':  $this->getOperatorDetail(); break;
            default:                 $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
    }

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

    // ================================================================
    // run=heatmap
    // Returnerar matrisdata: rader=operatörer, kolumner=timmar, celler=snittcykeltid (sek)
    // ================================================================

    private function getHeatmap(): void {
        try {
            $days = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            // Beräkna cykeltid via LAG per skiftraknare.
            // Varje rad i rebotling_ibc representerar en cykel.
            // En operatör kan vara op1, op2 eller op3 — vi räknar cykeltid per op-position.
            // Strategi: UNION av op1/op2/op3, beräkna LAG per skiftraknare för tidsstämpeln.
            $sql = "
                SELECT
                    op_num,
                    timme,
                    ROUND(AVG(cycle_sek), 1) AS avg_sek,
                    COUNT(*) AS antal
                FROM (
                    SELECT
                        op_num,
                        HOUR(datum) AS timme,
                        cycle_sek
                    FROM (
                        SELECT
                            op_num,
                            datum,
                            TIMESTAMPDIFF(SECOND,
                                LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                                datum
                            ) AS cycle_sek
                        FROM (
                            SELECT op1 AS op_num, datum, skiftraknare FROM rebotling_ibc
                            WHERE datum >= :from1 AND op1 IS NOT NULL AND op1 > 0
                            UNION ALL
                            SELECT op2 AS op_num, datum, skiftraknare FROM rebotling_ibc
                            WHERE datum >= :from2 AND op2 IS NOT NULL AND op2 > 0
                            UNION ALL
                            SELECT op3 AS op_num, datum, skiftraknare FROM rebotling_ibc
                            WHERE datum >= :from3 AND op3 IS NOT NULL AND op3 > 0
                        ) ops_raw
                    ) with_lag
                    WHERE cycle_sek >= 30 AND cycle_sek <= 1800
                ) filtered
                GROUP BY op_num, timme
                ORDER BY op_num, timme
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from1' => $fromDate, ':from2' => $fromDate, ':from3' => $fromDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->sendSuccess([
                    'operators' => [],
                    'hours' => [],
                    'matrix' => [],
                    'globalMin' => null,
                    'globalMax' => null,
                    'globalAvg' => null,
                ]);
                return;
            }

            // Hämta operatörsnamn
            $opNums = array_unique(array_column($rows, 'op_num'));
            sort($opNums);
            $placeholders = implode(',', array_fill(0, count($opNums), '?'));
            $nameStmt = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ({$placeholders}) ORDER BY name");
            $nameStmt->execute($opNums);
            $opNames = [];
            foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $opNames[(int)$row['number']] = $row['name'];
            }

            // Bygg matris: [op_num][timme] = {avg_sek, antal}
            $matrixData = [];
            $allSek = [];
            foreach ($rows as $r) {
                $opNum = (int)$r['op_num'];
                $timme = (int)$r['timme'];
                $avgSek = (float)$r['avg_sek'];
                $matrixData[$opNum][$timme] = [
                    'avg_sek' => $avgSek,
                    'antal' => (int)$r['antal'],
                ];
                $allSek[] = $avgSek;
            }

            // Bestäm timintervall (dynamiskt baserat på data)
            $timmar = array_unique(array_column($rows, 'timme'));
            sort($timmar);
            $minHour = min($timmar);
            $maxHour = max($timmar);
            // Utöka lite för bättre visning
            $minHour = max(0, $minHour);
            $maxHour = min(23, $maxHour);
            $hourRange = range($minHour, $maxHour);

            // Bygg output-matris
            $operators = [];
            $matrix = [];

            // Sortera operatörer efter namn
            $sortedOps = array_keys($matrixData);
            usort($sortedOps, function($a, $b) use ($opNames) {
                $nameA = $opNames[$a] ?? ('Operatör ' . $a);
                $nameB = $opNames[$b] ?? ('Operatör ' . $b);
                return strcmp($nameA, $nameB);
            });

            foreach ($sortedOps as $opNum) {
                $namn = $opNames[$opNum] ?? ('Operatör ' . $opNum);
                $operators[] = ['id' => $opNum, 'namn' => $namn];

                $row = [];
                foreach ($hourRange as $h) {
                    if (isset($matrixData[$opNum][$h])) {
                        $row[] = [
                            'hour' => $h,
                            'avg_sek' => $matrixData[$opNum][$h]['avg_sek'],
                            'antal' => $matrixData[$opNum][$h]['antal'],
                        ];
                    } else {
                        $row[] = ['hour' => $h, 'avg_sek' => null, 'antal' => 0];
                    }
                }
                $matrix[] = $row;
            }

            $globalMin = !empty($allSek) ? round(min($allSek), 1) : null;
            $globalMax = !empty($allSek) ? round(max($allSek), 1) : null;
            $globalAvg = !empty($allSek) ? round(array_sum($allSek) / count($allSek), 1) : null;

            $this->sendSuccess([
                'operators' => $operators,
                'hours'     => $hourRange,
                'matrix'    => $matrix,
                'globalMin' => $globalMin,
                'globalMax' => $globalMax,
                'globalAvg' => $globalAvg,
                'days'      => $days,
            ]);

        } catch (Exception $e) {
            error_log('CykeltidHeatmapController::getHeatmap: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta heatmap-data', 500);
        }
    }

    // ================================================================
    // run=day-pattern
    // Aggregerad timmevy (alla operatörer ihop): snittcykeltid + antal IBC per timme
    // ================================================================

    private function getDayPattern(): void {
        try {
            $days = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            $sql = "
                SELECT
                    timme,
                    ROUND(AVG(cycle_sek), 1) AS avg_sek,
                    COUNT(*) AS antal
                FROM (
                    SELECT
                        HOUR(datum) AS timme,
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) AS cycle_sek
                    FROM rebotling_ibc
                    WHERE datum >= :from
                ) with_lag
                WHERE cycle_sek >= 30 AND cycle_sek <= 1800
                GROUP BY timme
                ORDER BY timme
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $fromDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pattern = [];
            foreach ($rows as $r) {
                $pattern[] = [
                    'hour'    => (int)$r['timme'],
                    'avg_sek' => (float)$r['avg_sek'],
                    'antal'   => (int)$r['antal'],
                ];
            }

            // Sammanfattning: snabbaste/långsammaste timme
            $summary = null;
            if (!empty($pattern)) {
                $sorted = $pattern;
                usort($sorted, fn($a, $b) => $a['avg_sek'] <=> $b['avg_sek']);
                $fastestHour = $sorted[0];
                $slowestHour = $sorted[count($sorted) - 1];

                $allSek = array_column($pattern, 'avg_sek');
                $summary = [
                    'snabbaste_timme' => $fastestHour['hour'],
                    'snabbaste_sek'   => $fastestHour['avg_sek'],
                    'langsammaste_timme' => $slowestHour['hour'],
                    'langsammaste_sek'   => $slowestHour['avg_sek'],
                    'global_avg_sek'     => round(array_sum($allSek) / count($allSek), 1),
                ];
            }

            $this->sendSuccess([
                'pattern' => $pattern,
                'summary' => $summary,
                'days'    => $days,
            ]);

        } catch (Exception $e) {
            error_log('CykeltidHeatmapController::getDayPattern: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta dygnsmönster', 500);
        }
    }

    // ================================================================
    // run=operator-detail
    // Detaljerad vy för en operatör: cykeltid per timme per dag
    // ?operator_id=X&days=30
    // ================================================================

    private function getOperatorDetail(): void {
        try {
            $opId = intval($_GET['operator_id'] ?? 0);
            if ($opId <= 0) {
                $this->sendError('Ogiltigt operator_id');
                return;
            }

            $days = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            // Hämta operatörsnamn
            $nameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ? LIMIT 1");
            $nameStmt->execute([$opId]);
            $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
            $operatorName = $nameRow ? $nameRow['name'] : ('Operatör ' . $opId);

            // Cykeltid per timme per dag för denna operatör
            $sql = "
                SELECT
                    dag,
                    timme,
                    ROUND(AVG(cycle_sek), 1) AS avg_sek,
                    COUNT(*) AS antal
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        HOUR(datum) AS timme,
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) AS cycle_sek
                    FROM (
                        SELECT datum, skiftraknare FROM rebotling_ibc
                        WHERE datum >= :from1 AND op1 = :op1
                        UNION ALL
                        SELECT datum, skiftraknare FROM rebotling_ibc
                        WHERE datum >= :from2 AND op2 = :op2
                        UNION ALL
                        SELECT datum, skiftraknare FROM rebotling_ibc
                        WHERE datum >= :from3 AND op3 = :op3
                    ) op_raw
                ) with_lag
                WHERE cycle_sek >= 30 AND cycle_sek <= 1800
                GROUP BY dag, timme
                ORDER BY dag, timme
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $fromDate, ':op1' => $opId,
                ':from2' => $fromDate, ':op2' => $opId,
                ':from3' => $fromDate, ':op3' => $opId,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Organisera data per dag
            $byDay = [];
            $timmar = [];
            foreach ($rows as $r) {
                $dag = $r['dag'];
                $h = (int)$r['timme'];
                if (!isset($byDay[$dag])) $byDay[$dag] = [];
                $byDay[$dag][$h] = [
                    'avg_sek' => (float)$r['avg_sek'],
                    'antal'   => (int)$r['antal'],
                ];
                $timmar[] = $h;
            }

            $timmar = array_unique($timmar);
            sort($timmar);

            $days_list = array_keys($byDay);
            sort($days_list);

            // Bygg daglig heatmap-matris
            $dagMatrix = [];
            foreach ($days_list as $dag) {
                $row = ['dag' => $dag, 'celler' => []];
                foreach ($timmar as $h) {
                    if (isset($byDay[$dag][$h])) {
                        $row['celler'][] = [
                            'hour'    => $h,
                            'avg_sek' => $byDay[$dag][$h]['avg_sek'],
                            'antal'   => $byDay[$dag][$h]['antal'],
                        ];
                    } else {
                        $row['celler'][] = ['hour' => $h, 'avg_sek' => null, 'antal' => 0];
                    }
                }
                $dagMatrix[] = $row;
            }

            // Snitt per timme (för att se om operatörens mönster är konsekvent)
            $hourAvg = [];
            foreach ($timmar as $h) {
                $vals = [];
                foreach ($byDay as $dag => $cells) {
                    if (isset($cells[$h])) $vals[] = $cells[$h]['avg_sek'];
                }
                if (!empty($vals)) {
                    $hourAvg[] = [
                        'hour'    => $h,
                        'avg_sek' => round(array_sum($vals) / count($vals), 1),
                        'stddev'  => count($vals) > 1 ? round($this->stddev($vals), 1) : 0,
                        'antal_dagar' => count($vals),
                    ];
                }
            }

            $this->sendSuccess([
                'operator_id'   => $opId,
                'operator_namn' => $operatorName,
                'hours'         => $timmar,
                'dag_matrix'    => $dagMatrix,
                'hour_avg'      => $hourAvg,
                'days'          => $days,
            ]);

        } catch (Exception $e) {
            error_log('CykeltidHeatmapController::getOperatorDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsdetaljer', 500);
        }
    }

    // ================================================================
    // UTIL
    // ================================================================

    private function stddev(array $vals): float {
        $n = count($vals);
        if ($n < 2) return 0.0;
        $mean = array_sum($vals) / $n;
        $sumSq = 0;
        foreach ($vals as $v) $sumSq += ($v - $mean) ** 2;
        return sqrt($sumSq / ($n - 1));
    }
}
