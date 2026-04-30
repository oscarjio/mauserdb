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
 * VIKTIGT: ibc_ok/ibc_ej_ok är kumulativa PLC-räknare per dag (återställs vid midnatt).
 * Korrekt aggregering: MAX() per skiftraknare → LAG()-delta → SUM().
 * runtime_plc återställs per skift → MAX() per skiftraknare → SUM() är korrekt.
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
     * Bygg LAG-CTE för korrekta per-skift-deltor (ibc_ok är daglig kumulativ räknare).
     * Datum och opNum injiceras direkt (PHP-genererade värden — ej användarinput).
     * @param string|null $fromDate  NULL = ingen nedre datumgräns (all-time)
     * @param string|null $toDate    NULL = idag
     */
    private function buildLagCte(?string $fromDate = null, ?string $toDate = null): string {
        if ($fromDate !== null) {
            $f = $this->pdo->quote($fromDate);
            $t = $this->pdo->quote($toDate ?? date('Y-m-d'));
            $dateWhere = "WHERE datum >= {$f} AND datum < DATE_ADD({$t}, INTERVAL 1 DAY)";
        } else {
            $dateWhere = '';
        }
        return "
            WITH lag_base AS (
                SELECT DATE(datum) AS dag, skiftraknare,
                       MAX(COALESCE(ibc_ok, 0))      AS ibc_end,
                       MAX(COALESCE(ibc_ej_ok, 0))   AS ibc_ej_end,
                       MAX(COALESCE(runtime_plc, 0))  AS runtime_end,
                       MIN(COALESCE(op1, 0))           AS op1,
                       MIN(COALESCE(op2, 0))           AS op2,
                       MIN(COALESCE(op3, 0))           AS op3
                FROM rebotling_ibc
                {$dateWhere}
                GROUP BY DATE(datum), skiftraknare
            ),
            lag_shifts AS (
                SELECT dag, skiftraknare, op1, op2, op3,
                       GREATEST(0, ibc_end    - COALESCE(LAG(ibc_end)    OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc,
                       GREATEST(0, ibc_ej_end - COALESCE(LAG(ibc_ej_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ej_ok,
                       runtime_end AS shift_runtime
                FROM lag_base
            )
        ";
    }

    // ================================================================
    // ENDPOINT: my-stats
    // ================================================================

    private function getMyStats(): void {
        $opNum = $this->getOperatorNumber();
        if (!$opNum) {
            $this->sendError('Inget operator_id kopplat till kontot. Koppla ditt operatörsnummer i inställningarna.', 403);
            return;
        }

        $period   = $this->getPeriod();
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days", strtotime($today)));
        $lagCte   = $this->buildLagCte($fromDate, $today);

        try {
            // ---- Operatörens statistik ----
            $sqlOp = "
                {$lagCte}
                SELECT
                    COALESCE(SUM(shift_ibc), 0)     AS total_ibc,
                    COALESCE(SUM(shift_ej_ok), 0)   AS total_ej_ok,
                    COALESCE(SUM(shift_runtime), 0) AS total_runtime_s
                FROM lag_shifts
                WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
            ";
            $rowOp = $this->pdo->query($sqlOp)->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalIbc     = (int)($rowOp['total_ibc']      ?? 0);
            $totalEjOk    = (int)($rowOp['total_ej_ok']    ?? 0);
            $totalRuntime = (float)($rowOp['total_runtime_s'] ?? 0);

            $snittIbcPerH = ($totalRuntime > 0)
                ? round($totalIbc * 60.0 / $totalRuntime, 2)
                : 0.0;

            $totalProducerade = $totalIbc + $totalEjOk;
            $kvalitetPct = ($totalProducerade > 0)
                ? round($totalIbc * 100.0 / $totalProducerade, 2)
                : null;

            // ---- Bästa dag ----
            $sqlBast = "
                {$lagCte}
                SELECT dag, SUM(shift_ibc) AS dag_ibc
                FROM lag_shifts
                WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
                GROUP BY dag
                HAVING dag_ibc > 0
                ORDER BY dag_ibc DESC
                LIMIT 1
            ";
            $rowBast    = $this->pdo->query($sqlBast)->fetch(PDO::FETCH_ASSOC) ?: null;
            $bastDag    = $rowBast ? $rowBast['dag']          : null;
            $bastDagIbc = $rowBast ? (int)$rowBast['dag_ibc'] : 0;

            // ---- Teamsnitt IBC/h och kvalitet (alla operatörer) ----
            $sqlTeam = "
                {$lagCte}
                SELECT op_num,
                       SUM(shift_ibc)     AS op_ibc,
                       SUM(shift_ej_ok)   AS op_ej_ok,
                       SUM(shift_runtime) AS op_runtime
                FROM (
                    SELECT op1 AS op_num, shift_ibc, shift_ej_ok, shift_runtime FROM lag_shifts WHERE op1 > 0
                    UNION ALL
                    SELECT op2 AS op_num, shift_ibc, shift_ej_ok, shift_runtime FROM lag_shifts WHERE op2 > 0
                    UNION ALL
                    SELECT op3 AS op_num, shift_ibc, shift_ej_ok, shift_runtime FROM lag_shifts WHERE op3 > 0
                ) AS ops
                GROUP BY op_num
            ";
            $teamRows = $this->pdo->query($sqlTeam)->fetchAll(PDO::FETCH_ASSOC);

            $teamIbcPerHList  = [];
            $teamKvalitetList = [];
            $rankingData      = [];

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

    private function getMyTrend(): void {
        $opNum = $this->getOperatorNumber();
        if (!$opNum) {
            $this->sendError('Inget operator_id kopplat till kontot.', 403);
            return;
        }

        $period   = $this->getPeriod();
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days", strtotime($today)));
        $lagCte   = $this->buildLagCte($fromDate, $today);

        try {
            $dates = [];
            $cur   = strtotime($fromDate);
            $end   = strtotime($today);
            while ($cur <= $end) {
                $dates[] = date('Y-m-d', $cur);
                $cur     = strtotime('+1 day', $cur);
            }

            // ---- Operatörens dagliga stats ----
            $sqlMy = "
                {$lagCte}
                SELECT dag,
                       SUM(shift_ibc)     AS dag_ibc,
                       SUM(shift_ej_ok)   AS dag_ej_ok,
                       SUM(shift_runtime) AS dag_runtime
                FROM lag_shifts
                WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
                GROUP BY dag
                ORDER BY dag
            ";
            $myRows = $this->pdo->query($sqlMy)->fetchAll(PDO::FETCH_ASSOC);

            $myMap = [];
            foreach ($myRows as $r) {
                $myMap[$r['dag']] = $r;
            }

            // ---- Teamsnitt IBC/h per dag ----
            $sqlTeamDag = "
                {$lagCte}
                SELECT dag,
                       ROUND(
                           SUM(shift_ibc) * 60.0 / NULLIF(SUM(shift_runtime), 0)
                           / NULLIF(COUNT(DISTINCT op_num), 0)
                       , 2) AS team_ibc_per_h
                FROM (
                    SELECT op1 AS op_num, dag, shift_ibc, shift_runtime FROM lag_shifts WHERE op1 > 0
                    UNION ALL
                    SELECT op2 AS op_num, dag, shift_ibc, shift_runtime FROM lag_shifts WHERE op2 > 0
                    UNION ALL
                    SELECT op3 AS op_num, dag, shift_ibc, shift_runtime FROM lag_shifts WHERE op3 > 0
                ) AS ops
                GROUP BY dag
                ORDER BY dag
            ";
            $teamDagRows = $this->pdo->query($sqlTeamDag)->fetchAll(PDO::FETCH_ASSOC);

            $teamDagMap = [];
            foreach ($teamDagRows as $tr) {
                $teamDagMap[$tr['dag']] = (float)$tr['team_ibc_per_h'];
            }

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
                'operator_num'   => $opNum,
                'period'         => $period,
                'from_date'      => $fromDate,
                'to_date'        => $today,
                'dates'          => $dates,
                'my_ibc'         => $myIbc,
                'my_ibc_per_h'   => $myIbcPerH,
                'my_kvalitet'    => $myKvalitet,
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

    private function getMyAchievements(): void {
        $opNum = $this->getOperatorNumber();
        if (!$opNum) {
            $this->sendError('Inget operator_id kopplat till kontot.', 403);
            return;
        }

        $today = date('Y-m-d');

        try {
            // ---- Karriär-total (all-time) ----
            $lagCteAllTime = $this->buildLagCte();
            $sqlTotal = "
                {$lagCteAllTime}
                SELECT COALESCE(SUM(shift_ibc), 0) AS karriar_total
                FROM lag_shifts
                WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
            ";
            $karriarTotal = (int)($this->pdo->query($sqlTotal)->fetchColumn() ?? 0);

            // ---- Bästa dag ever (all-time) ----
            $sqlBastEver = "
                {$lagCteAllTime}
                SELECT dag, SUM(shift_ibc) AS dag_ibc
                FROM lag_shifts
                WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
                GROUP BY dag
                HAVING dag_ibc > 0
                ORDER BY dag_ibc DESC
                LIMIT 1
            ";
            $rowBast        = $this->pdo->query($sqlBastEver)->fetch(PDO::FETCH_ASSOC) ?: null;
            $bastDagEver    = $rowBast ? $rowBast['dag']          : null;
            $bastDagEverIbc = $rowBast ? (int)$rowBast['dag_ibc'] : 0;

            // ---- Streak: dagar i rad med > 0 IBC (bakåt från idag) ----
            $fromStreak    = date('Y-m-d', strtotime('-90 days'));
            $lagCteStreak  = $this->buildLagCte($fromStreak, $today);
            $sqlStreak = "
                {$lagCteStreak}
                SELECT dag, SUM(shift_ibc) AS tot
                FROM lag_shifts
                WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
                GROUP BY dag
                ORDER BY dag DESC
            ";
            $streakRows = $this->pdo->query($sqlStreak)->fetchAll(PDO::FETCH_ASSOC);

            $streakMap = [];
            foreach ($streakRows as $sr) {
                $streakMap[$sr['dag']] = (int)$sr['tot'];
            }

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
                $lagCteWeek = $this->buildLagCte($from, $to);
                $sql = "
                    {$lagCteWeek}
                    SELECT ROUND(
                        SUM(shift_ibc) * 60.0 / NULLIF(SUM(shift_runtime), 0)
                    , 2) AS ibc_per_h
                    FROM lag_shifts
                    WHERE op1 = {$opNum} OR op2 = {$opNum} OR op3 = {$opNum}
                ";
                try {
                    $val = $this->pdo->query($sql)->fetchColumn();
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
                'operator_num'          => $opNum,
                'operator_namn'         => $this->getOperatorName($opNum),
                'karriar_total'         => $karriarTotal,
                'bast_dag_ever'         => $bastDagEver,
                'bast_dag_ever_ibc'     => $bastDagEverIbc,
                'streak'                => $streak,
                'forbattring_pct'       => $forbattringPct,
                'forbattring_direction' => $forbattringDir,
                'week1_ibc_per_h'       => $week1IbcPerH,
                'week2_ibc_per_h'       => $week2IbcPerH,
            ]);

        } catch (\PDOException $e) {
            error_log('MyStatsController::getMyAchievements: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta prestationer', 500);
        }
    }
}
