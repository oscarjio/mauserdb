<?php
/**
 * MyStatsController.php
 * Personligt operatörsdashboard — "Min statistik"
 *
 * Kräver inloggad session med $_SESSION['operator_id'] satt.
 *
 * Endpoints via ?action=my-stats&run=XXX:
 *   run=my-stats&period=7|30|90
 *     → Statistik för inloggad operatör: total IBC, snitt IBC/h, kvalitet%,
 *       bästa dag, jämförelse mot teamsnitt, ranking
 *
 *   run=my-trend&period=30|90
 *     → Daglig trend för operatören: IBC/dag, IBC/h per dag, kvalitet per dag.
 *       Även teamsnitt per dag för jämförelse.
 *
 *   run=my-achievements
 *     → Milstolpar: karriär-total, bästa dag någonsin, streak (dagar i rad),
 *       förbättring senaste veckan vs föregående
 *
 * Tabeller:
 *   rebotling_ibc  (ibc_ok, ibc_ej_ok, datum, skiftraknare, op1, op2, op3, runtime_plc)
 *   operators      (id, number, name, active)
 *   users          (id, operator_id, username)
 *
 * VIKTIGT: ibc_ok/ibc_ej_ok/runtime_plc är KUMULATIVA PLC-värden per skift.
 * Korrekt aggregering: MAX() per skiftraknare, sedan SUM() för perioden.
 */
class MyStatsController {
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
            case 'my-stats':        $this->getMyStats();        break;
            case 'my-trend':        $this->getMyTrend();        break;
            case 'my-achievements': $this->getMyAchievements(); break;
            default:                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getPeriod(): int {
        $p = intval($_GET['period'] ?? 30);
        return in_array($p, [7, 30, 90], true) ? $p : 30;
    }

    private function getOperatorNumber(): ?int {
        if (!empty($_SESSION['operator_id'])) {
            return (int)$_SESSION['operator_id'];
        }
        return null;
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

    /**
     * Hämta operatörens namn från operators-tabellen via operators.number = op_num.
     */
    private function getOperatorName(int $opNum): string {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT name FROM operators WHERE number = ? LIMIT 1"
            );
            $stmt->execute([$opNum]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row && isset($row['name'])) ? $row['name'] : ('Operatör #' . $opNum);
        } catch (\PDOException $e) {
            error_log('MyStatsController::getOperatorName: ' . $e->getMessage());
            return 'Operatör #' . $opNum;
        }
    }

    /**
     * Bygg UNION ALL-subquery: op1/op2/op3 → rader med (op_num, datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc)
     * Filtrerad på datum-intervall via :from_date/:to_date.
     * @param int|null $onlyOpNum  Om satt filtreras även på operatörsnumret.
     */
    private function buildUnion(?int $onlyOpNum = null): string {
        $opFilter = $onlyOpNum !== null ? "AND op_num = {$onlyOpNum}" : '';
        return "
            SELECT op_num, datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
            FROM (
                SELECT op1 AS op_num, datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                FROM rebotling_ibc
                WHERE op1 IS NOT NULL AND op1 > 0
                  AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)

                UNION ALL
                SELECT op2 AS op_num, datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                FROM rebotling_ibc
                WHERE op2 IS NOT NULL AND op2 > 0
                  AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)

                UNION ALL
                SELECT op3 AS op_num, datum, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                FROM rebotling_ibc
                WHERE op3 IS NOT NULL AND op3 > 0
                  AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)

            ) AS u
            WHERE 1=1 {$opFilter}
        ";
    }

    // ================================================================
    // ENDPOINT: my-stats
    // ================================================================

    /**
     * Statistik för inloggad operatör under vald period.
     *
     * Returnerar:
     * - operator_num, operator_namn, period (dagar)
     * - total_ibc, snitt_ibc_per_h, kvalitet_pct
     * - bast_dag (datum + IBC)
     * - team_snitt_ibc_per_h, team_snitt_kvalitet_pct
     * - ranking (plats + totalt antal)
     */
    private function getMyStats(): void {
        $opNum = $this->getOperatorNumber();
        if (!$opNum) {
            $this->sendError('Inget operator_id kopplat till kontot. Koppla ditt operatörsnummer i inställningarna.', 403);
            return;
        }

        $period   = $this->getPeriod();
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days", strtotime($today)));

        try {
            // ---- Operatörens statistik ----
            $sqlOp = "
                SELECT
                    SUM(dag_ibc)     AS total_ibc,
                    SUM(dag_ej_ok)   AS total_ej_ok,
                    SUM(dag_runtime) AS total_runtime_s
                FROM (
                    SELECT DATE(datum) AS dag,
                           SUM(shift_ibc)     AS dag_ibc,
                           SUM(shift_ej_ok)   AS dag_ej_ok,
                           SUM(shift_runtime) AS dag_runtime
                    FROM (
                        SELECT DATE(datum) AS datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                               MAX(COALESCE(ibc_ej_ok, 0))   AS shift_ej_ok,
                               MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                        FROM ({$this->buildUnion($opNum)}) AS u2
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY DATE(datum)
                ) AS per_dag
            ";
            $stmtOp = $this->pdo->prepare($sqlOp);
            $stmtOp->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $rowOp = $stmtOp->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalIbc     = (int)($rowOp['total_ibc']     ?? 0);
            $totalEjOk    = (int)($rowOp['total_ej_ok']   ?? 0);
            $totalRuntime = (float)($rowOp['total_runtime_s'] ?? 0); // sekunder (runtime_plc är minuter? → kolla)

            // runtime_plc är i minuter (baserat på befintliga controllers)
            $snittIbcPerH = ($totalRuntime > 0)
                ? round($totalIbc * 60.0 / $totalRuntime, 2)
                : 0.0;

            $totalProducerade = $totalIbc + $totalEjOk;
            $kvalitetPct = ($totalProducerade > 0)
                ? round($totalIbc * 100.0 / $totalProducerade, 2)
                : null;

            // ---- Bästa dag ----
            $sqlBast = "
                SELECT dag, dag_ibc
                FROM (
                    SELECT DATE(datum) AS dag,
                           SUM(shift_ibc) AS dag_ibc
                    FROM (
                        SELECT DATE(datum) AS datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM ({$this->buildUnion($opNum)}) AS u3
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ps
                    GROUP BY DATE(datum)
                ) AS pd
                ORDER BY dag_ibc DESC
                LIMIT 1
            ";
            $stmtBast = $this->pdo->prepare($sqlBast);
            $stmtBast->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $rowBast = $stmtBast->fetch(PDO::FETCH_ASSOC) ?: null;
            $bastDag    = $rowBast ? $rowBast['dag']     : null;
            $bastDagIbc = $rowBast ? (int)$rowBast['dag_ibc'] : 0;

            // ---- Teamsnitt IBC/h och kvalitet ----
            $sqlTeam = "
                SELECT
                    op_num,
                    SUM(shift_ibc)     AS op_ibc,
                    SUM(shift_ej_ok)   AS op_ej_ok,
                    SUM(shift_runtime) AS op_runtime
                FROM (
                    SELECT op_num, DATE(datum) AS datum, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(ibc_ej_ok, 0))   AS shift_ej_ok,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM ({$this->buildUnion()}) AS tall
                    GROUP BY op_num, DATE(datum), skiftraknare
                ) AS tall2
                GROUP BY op_num
            ";
            $stmtTeam = $this->pdo->prepare($sqlTeam);
            $stmtTeam->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $teamRows = $stmtTeam->fetchAll(PDO::FETCH_ASSOC);

            $teamIbcPerHList  = [];
            $teamKvalitetList = [];
            $rankingData      = []; // [op_num => ibc_per_h]

            foreach ($teamRows as $tr) {
                $oIbc     = (int)$tr['op_ibc'];
                $oEjOk    = (int)$tr['op_ej_ok'];
                $oRuntime = (float)$tr['op_runtime'];

                $oIbcPerH = ($oRuntime > 0) ? ($oIbc * 60.0 / $oRuntime) : 0.0;
                $oProd    = $oIbc + $oEjOk;
                $oKval    = ($oProd > 0) ? ($oIbc * 100.0 / $oProd) : null;

                if ($oIbcPerH > 0) {
                    $teamIbcPerHList[] = $oIbcPerH;
                }
                if ($oKval !== null) {
                    $teamKvalitetList[] = $oKval;
                }
                $rankingData[(int)$tr['op_num']] = $oIbcPerH;
            }

            $teamSnittIbcPerH  = count($teamIbcPerHList)  > 0 ? round(array_sum($teamIbcPerHList)  / count($teamIbcPerHList),  2) : 0.0;
            $teamSnittKvalitet = count($teamKvalitetList) > 0 ? round(array_sum($teamKvalitetList) / count($teamKvalitetList), 2) : null;

            // Ranking: sortera på IBC/h, hitta plats för denna operatör
            arsort($rankingData);
            $ranking       = 1;
            $totalOpsCount = count($rankingData);
            foreach ($rankingData as $num => $rate) {
                if ($num === $opNum) break;
                $ranking++;
            }

            $this->sendSuccess([
                'operator_num'          => $opNum,
                'operator_namn'         => $this->getOperatorName($opNum),
                'period'                => $period,
                'from_date'             => $fromDate,
                'to_date'               => $today,
                'total_ibc'             => $totalIbc,
                'snitt_ibc_per_h'       => $snittIbcPerH,
                'kvalitet_pct'          => $kvalitetPct,
                'bast_dag'              => $bastDag,
                'bast_dag_ibc'          => $bastDagIbc,
                'team_snitt_ibc_per_h'  => $teamSnittIbcPerH,
                'team_snitt_kvalitet'   => $teamSnittKvalitet,
                'ranking'               => $ranking,
                'total_ops'             => $totalOpsCount,
            ]);

        } catch (\PDOException $e) {
            error_log('MyStatsController::getMyStats: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta statistik', 500);
        }
    }

    // ================================================================
    // ENDPOINT: my-trend
    // ================================================================

    /**
     * Daglig trend för operatören + teamsnitt per dag.
     *
     * Returnerar:
     * - dates[]
     * - my_ibc[]         — IBC/dag för operatören
     * - my_ibc_per_h[]   — IBC/h per dag för operatören
     * - my_kvalitet[]    — kvalitet% per dag (null om ingen data)
     * - team_ibc_per_h[] — teamsnitt IBC/h per dag
     */
    private function getMyTrend(): void {
        $opNum = $this->getOperatorNumber();
        if (!$opNum) {
            $this->sendError('Inget operator_id kopplat till kontot.', 403);
            return;
        }

        $period   = $this->getPeriod();
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days", strtotime($today)));

        try {
            // Generera datum-serie
            $dates = [];
            $cur   = strtotime($fromDate);
            $end   = strtotime($today);
            while ($cur <= $end) {
                $dates[] = date('Y-m-d', $cur);
                $cur     = strtotime('+1 day', $cur);
            }

            // ---- Operatörens dagliga stats ----
            $sqlMy = "
                SELECT dag,
                       SUM(shift_ibc)     AS dag_ibc,
                       SUM(shift_ej_ok)   AS dag_ej_ok,
                       SUM(shift_runtime) AS dag_runtime
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(ibc_ej_ok, 0))   AS shift_ej_ok,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM ({$this->buildUnion($opNum)}) AS u
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY dag
                ORDER BY dag
            ";
            $stmtMy = $this->pdo->prepare($sqlMy);
            $stmtMy->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $myRows = $stmtMy->fetchAll(PDO::FETCH_ASSOC);

            // Bygg lookup dag → data
            $myMap = [];
            foreach ($myRows as $r) {
                $myMap[$r['dag']] = $r;
            }

            // ---- Teamsnitt IBC/h per dag ----
            $sqlTeamDag = "
                SELECT dag,
                       ROUND(
                           SUM(shift_ibc) * 60.0 / NULLIF(SUM(shift_runtime), 0)
                           / NULLIF(COUNT(DISTINCT op_num), 0)
                       , 2) AS team_ibc_per_h
                FROM (
                    SELECT op_num, DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM ({$this->buildUnion()}) AS tall
                    GROUP BY op_num, DATE(datum), skiftraknare
                ) AS tall2
                GROUP BY dag
                ORDER BY dag
            ";
            $stmtTeam = $this->pdo->prepare($sqlTeamDag);
            $stmtTeam->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $teamDagRows = $stmtTeam->fetchAll(PDO::FETCH_ASSOC);

            $teamDagMap = [];
            foreach ($teamDagRows as $tr) {
                $teamDagMap[$tr['dag']] = (float)$tr['team_ibc_per_h'];
            }

            // Bygg arrays för Chart.js
            $myIbc       = [];
            $myIbcPerH   = [];
            $myKvalitet  = [];
            $teamIbcPerH = [];

            foreach ($dates as $dag) {
                $r = $myMap[$dag] ?? null;
                if ($r) {
                    $ibc     = (int)$r['dag_ibc'];
                    $ejOk    = (int)$r['dag_ej_ok'];
                    $runtime = (float)$r['dag_runtime'];

                    $myIbc[]      = $ibc;
                    $myIbcPerH[]  = $runtime > 0 ? round($ibc * 60.0 / $runtime, 2) : 0.0;
                    $prod         = $ibc + $ejOk;
                    $myKvalitet[] = $prod > 0 ? round($ibc * 100.0 / $prod, 2) : null;
                } else {
                    $myIbc[]      = 0;
                    $myIbcPerH[]  = 0.0;
                    $myKvalitet[] = null;
                }
                $teamIbcPerH[] = isset($teamDagMap[$dag]) ? $teamDagMap[$dag] : 0.0;
            }

            $this->sendSuccess([
                'operator_num' => $opNum,
                'period'       => $period,
                'from_date'    => $fromDate,
                'to_date'      => $today,
                'dates'        => $dates,
                'my_ibc'       => $myIbc,
                'my_ibc_per_h' => $myIbcPerH,
                'my_kvalitet'  => $myKvalitet,
                'team_ibc_per_h' => $teamIbcPerH,
            ]);

        } catch (\PDOException $e) {
            error_log('MyStatsController::getMyTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta trend', 500);
        }
    }

    // ================================================================
    // ENDPOINT: my-achievements
    // ================================================================

    /**
     * Milstolpar för operatören (all-time data).
     *
     * Returnerar:
     * - karriar_total         — totalt IBC producerade genom tiderna
     * - bast_dag_ever         — bästa dag (datum + IBC) all-time
     * - bast_dag_ever_ibc
     * - streak                — nuvarande antal dagar i rad med produktion (> 0 IBC)
     * - forbattring_pct       — % förändring senaste 7 dagar vs föregående 7 dagar (IBC/h)
     * - forbattring_direction — 'upp' | 'ner' | 'stabil'
     */
    private function getMyAchievements(): void {
        $opNum = $this->getOperatorNumber();
        if (!$opNum) {
            $this->sendError('Inget operator_id kopplat till kontot.', 403);
            return;
        }

        $today = date('Y-m-d');

        try {
            // ---- Karriär-total (all-time) ----
            $sqlTotal = "
                SELECT COALESCE(SUM(shift_ibc), 0) AS karriar_total
                FROM (
                    SELECT op_num, DATE(datum) AS datum, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM (
                        SELECT op1 AS op_num, datum, skiftraknare, ibc_ok
                        FROM rebotling_ibc
                        WHERE op1 = :op_num_a AND op1 > 0 AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op2 AS op_num, datum, skiftraknare, ibc_ok
                        FROM rebotling_ibc
                        WHERE op2 = :op_num_b AND op2 > 0 AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op3 AS op_num, datum, skiftraknare, ibc_ok
                        FROM rebotling_ibc
                        WHERE op3 = :op_num_c AND op3 > 0 AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
            ";
            $stmtTotal = $this->pdo->prepare($sqlTotal);
            $stmtTotal->execute([
                ':op_num_a' => $opNum,
                ':op_num_b' => $opNum,
                ':op_num_c' => $opNum,
            ]);
            $karriarTotal = (int)($stmtTotal->fetchColumn() ?? 0);

            // ---- Bästa dag ever ----
            $sqlBastEver = "
                SELECT dag, dag_ibc
                FROM (
                    SELECT DATE(datum) AS dag, SUM(shift_ibc) AS dag_ibc
                    FROM (
                        SELECT op_num, DATE(datum) AS datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM (
                            SELECT op1 AS op_num, datum, skiftraknare, ibc_ok
                            FROM rebotling_ibc
                            WHERE op1 = :op_num_a AND op1 > 0 AND skiftraknare IS NOT NULL
                            UNION ALL
                            SELECT op2 AS op_num, datum, skiftraknare, ibc_ok
                            FROM rebotling_ibc
                            WHERE op2 = :op_num_b AND op2 > 0 AND skiftraknare IS NOT NULL
                            UNION ALL
                            SELECT op3 AS op_num, datum, skiftraknare, ibc_ok
                            FROM rebotling_ibc
                            WHERE op3 = :op_num_c AND op3 > 0 AND skiftraknare IS NOT NULL
                        ) AS u
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ps
                    GROUP BY DATE(datum)
                    HAVING dag_ibc > 0
                ) AS pd
                ORDER BY dag_ibc DESC
                LIMIT 1
            ";
            $stmtBast = $this->pdo->prepare($sqlBastEver);
            $stmtBast->execute([
                ':op_num_a' => $opNum,
                ':op_num_b' => $opNum,
                ':op_num_c' => $opNum,
            ]);
            $rowBast = $stmtBast->fetch(PDO::FETCH_ASSOC) ?: null;
            $bastDagEver    = $rowBast ? $rowBast['dag']          : null;
            $bastDagEverIbc = $rowBast ? (int)$rowBast['dag_ibc'] : 0;

            // ---- Streak: dagar i rad med > 0 IBC (bakåt från idag) ----
            // Hämta senaste 90 dagar, kolla bakåt
            $fromStreak = date('Y-m-d', strtotime('-90 days'));
            $sqlStreak  = "
                SELECT dag, SUM(dag_ibc) AS tot
                FROM (
                    SELECT DATE(datum) AS dag, SUM(shift_ibc) AS dag_ibc
                    FROM (
                        SELECT op1 AS op_num, datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE op1 = :op_num_a AND op1 > 0 AND skiftraknare IS NOT NULL
                          AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                        UNION ALL
                        SELECT op2 AS op_num, datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE op2 = :op_num_b AND op2 > 0 AND skiftraknare IS NOT NULL
                          AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                        UNION ALL
                        SELECT op3 AS op_num, datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE op3 = :op_num_c AND op3 > 0 AND skiftraknare IS NOT NULL
                          AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                    ) AS u
                    GROUP BY DATE(datum)
                ) AS pd
                GROUP BY dag
                ORDER BY dag DESC
            ";
            $stmtStreak = $this->pdo->prepare($sqlStreak);
            $stmtStreak->execute([
                ':op_num_a'  => $opNum,
                ':op_num_b'  => $opNum,
                ':op_num_c'  => $opNum,
                ':from_date' => $fromStreak,
                ':to_date'   => $today,
            ]);
            $streakRows = $stmtStreak->fetchAll(PDO::FETCH_ASSOC);

            // Bygg map dag → total
            $streakMap = [];
            foreach ($streakRows as $sr) {
                $streakMap[$sr['dag']] = (int)$sr['tot'];
            }

            // Räkna streak bakåt från idag
            $streak  = 0;
            $checkTs = strtotime($today);
            for ($i = 0; $i <= 90; $i++) {
                $dag = date('Y-m-d', $checkTs);
                if (isset($streakMap[$dag]) && $streakMap[$dag] > 0) {
                    $streak++;
                    $checkTs = strtotime('-1 day', $checkTs);
                } else {
                    break;
                }
            }

            // ---- Förbättring: IBC/h senaste 7 d vs föregående 7 d ----
            $week1End   = $today;
            $week1Start = date('Y-m-d', strtotime('-6 days'));
            $week2End   = date('Y-m-d', strtotime('-7 days'));
            $week2Start = date('Y-m-d', strtotime('-13 days'));

            $getWeekIbcPerH = function(string $from, string $to) use ($opNum): float {
                $sql = "
                    SELECT ROUND(
                        SUM(shift_ibc) * 60.0 / NULLIF(SUM(shift_runtime), 0)
                    , 2) AS ibc_per_h
                    FROM (
                        SELECT DATE(datum) AS dag, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                               MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                        FROM (
                            SELECT op1 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                            FROM rebotling_ibc
                            WHERE op1 = :op_num_a AND op1 > 0 AND skiftraknare IS NOT NULL
                              AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                            UNION ALL
                            SELECT op2 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                            FROM rebotling_ibc
                            WHERE op2 = :op_num_b AND op2 > 0 AND skiftraknare IS NOT NULL
                              AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                            UNION ALL
                            SELECT op3 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                            FROM rebotling_ibc
                            WHERE op3 = :op_num_c AND op3 > 0 AND skiftraknare IS NOT NULL
                              AND datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                        ) AS u
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ps
                ";
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        ':op_num_a'  => $opNum,
                        ':op_num_b'  => $opNum,
                        ':op_num_c'  => $opNum,
                        ':from_date' => $from,
                        ':to_date'   => $to,
                    ]);
                    $val = $stmt->fetchColumn();
                    return $val !== null ? (float)$val : 0.0;
                } catch (\PDOException $e) {
                    error_log('MyStatsController::getMyAchievements weekIbcPerH: ' . $e->getMessage());
                    return 0.0;
                }
            };

            $week1IbcPerH = $getWeekIbcPerH($week1Start, $week1End);
            $week2IbcPerH = $getWeekIbcPerH($week2Start, $week2End);

            $forbattringPct = 0.0;
            $forbattringDir = 'stabil';
            if ($week2IbcPerH > 0) {
                $forbattringPct = round(($week1IbcPerH - $week2IbcPerH) / $week2IbcPerH * 100, 1);
                if ($forbattringPct > 3) {
                    $forbattringDir = 'upp';
                } elseif ($forbattringPct < -3) {
                    $forbattringDir = 'ner';
                }
            } elseif ($week1IbcPerH > 0) {
                $forbattringDir = 'upp';
                $forbattringPct = 100.0;
            }

            $this->sendSuccess([
                'operator_num'           => $opNum,
                'operator_namn'          => $this->getOperatorName($opNum),
                'karriar_total'          => $karriarTotal,
                'bast_dag_ever'          => $bastDagEver,
                'bast_dag_ever_ibc'      => $bastDagEverIbc,
                'streak'                 => $streak,
                'forbattring_pct'        => $forbattringPct,
                'forbattring_direction'  => $forbattringDir,
                'week1_ibc_per_h'        => $week1IbcPerH,
                'week2_ibc_per_h'        => $week2IbcPerH,
            ]);

        } catch (\PDOException $e) {
            error_log('MyStatsController::getMyAchievements: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta prestationer', 500);
        }
    }
}
