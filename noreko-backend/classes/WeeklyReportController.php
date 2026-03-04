<?php
/**
 * WeeklyReportController.php
 * Veckosummering för VD — KPI, daglig produktion, bästa/sämsta dag, operatörsranking.
 *
 * Endpoints:
 * - GET ?action=weekly-report&run=summary&week=YYYY-WXX
 * - GET ?action=weekly-report&run=week-compare&week_start=YYYY-MM-DD
 */

class WeeklyReportController {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad']);
            return;
        }

        $run = $_GET['run'] ?? '';

        match ($run) {
            'summary'      => $this->getSummary(),
            'week-compare' => $this->getWeekCompare(),
            default        => $this->sendError('Okänd metod', 400)
        };
    }

    private function sendError(string $msg, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $msg]);
    }

    // -----------------------------------------------------------------------
    // Helper: Hämta veckosummering för ett datumintervall
    // Returnerar array med total_ibc, avg_ibc_per_day, avg_oee_pct,
    // avg_quality_pct, best_day_ibc, best_day_date, working_days, week_label
    // -----------------------------------------------------------------------
    private function fetchWeekStats(string $mondayStr, string $sundayStr): array {
        $sqlDaily = "
            SELECT DATE(datum) AS dag,
                   SUM(delta_ok) AS ibc_ok,
                   SUM(delta_ej) AS ibc_ej,
                   SUM(delta_ok + delta_ej) AS ibc_total,
                   SUM(delta_ok) / NULLIF(SUM(delta_ok + delta_ej), 0) * 100 AS kvalitet_pct,
                   SUM(runtime_h) AS drifttid_h
            FROM (
                SELECT DATE(datum) AS datum, skiftraknare,
                       MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok,
                       MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS delta_ej,
                       MAX(runtime_plc) / 3600.0 AS runtime_h
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN ? AND ?
                GROUP BY DATE(datum), skiftraknare
            ) x
            GROUP BY DATE(datum)
            ORDER BY dag
        ";
        $stmt = $this->pdo->prepare($sqlDaily);
        $stmt->execute([$mondayStr, $sundayStr]);
        $daily = $stmt->fetchAll();

        $totalIbc     = 0;
        $totalIbcEj   = 0;
        $totalIbcTot  = 0;
        $totalDrift   = 0.0;
        $workingDays  = 0;
        $qualityPcts  = [];
        $bestDayIbc   = 0;
        $bestDayDate  = null;

        foreach ($daily as $row) {
            $ok   = intval($row['ibc_ok'] ?? 0);
            $ej   = intval($row['ibc_ej'] ?? 0);
            $tot  = $ok + $ej;
            $h    = floatval($row['drifttid_h'] ?? 0);
            $kval = floatval($row['kvalitet_pct'] ?? 0);
            $dag  = $row['dag'];
            $dow  = date('N', strtotime($dag));

            $totalIbc    += $ok;
            $totalIbcEj  += $ej;
            $totalIbcTot += $tot;
            $totalDrift  += $h;

            if ($dow <= 5) {
                $workingDays++;
                if ($tot > 0) {
                    $qualityPcts[] = $kval;
                }
            }

            if ($ok > $bestDayIbc) {
                $bestDayIbc  = $ok;
                $bestDayDate = $dag;
            }
        }

        if ($workingDays === 0) $workingDays = 5;

        $avgIbcPerDay = $workingDays > 0 ? round($totalIbc / $workingDays) : 0;
        $avgQuality   = count($qualityPcts) > 0
            ? round(array_sum($qualityPcts) / count($qualityPcts), 1)
            : 0.0;

        // OEE: (runtime / total_possible_h_per_dag) * quality_factor
        // Simplified: IBC-baserat OEE = (total_ibc / (dagmal * working_days)) * quality
        $dagmal = 1200;
        try {
            $stmtG = $this->pdo->query("SELECT dagmal FROM rebotling_settings LIMIT 1");
            $gr    = $stmtG->fetch();
            if ($gr && isset($gr['dagmal'])) $dagmal = intval($gr['dagmal']);
        } catch (Exception $e) { /* ignore */ }

        $maxIbc   = $dagmal * $workingDays;
        $oee      = $maxIbc > 0 ? round(($totalIbc / $maxIbc) * 100, 1) : 0.0;

        // Week label: "V08 2026"
        $mondayDt = new DateTime($mondayStr);
        $weekNum  = intval($mondayDt->format('W'));
        $yearNum  = intval($mondayDt->format('o'));
        $weekLabel = sprintf('V%02d %d', $weekNum, $yearNum);

        return [
            'total_ibc'       => $totalIbc,
            'avg_ibc_per_day' => $avgIbcPerDay,
            'avg_oee_pct'     => $oee,
            'avg_quality_pct' => $avgQuality,
            'best_day_ibc'    => $bestDayIbc,
            'best_day_date'   => $bestDayDate,
            'working_days'    => $workingDays,
            'week_label'      => $weekLabel,
        ];
    }

    // -----------------------------------------------------------------------
    // GET ?action=weekly-report&run=week-compare&week_start=YYYY-MM-DD
    // Returnerar this_week + prev_week + diff + operator_of_week
    // -----------------------------------------------------------------------
    private function getWeekCompare(): void {
        try {
            $weekStartParam = $_GET['week_start'] ?? '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
                // Räkna ut förra veckans måndag som default
                $dt = new DateTime('last monday -1 week');
                $weekStartParam = $dt->format('Y-m-d');
            }

            // Denna vecka
            $thisMon = new DateTime($weekStartParam);
            $thisSun = clone $thisMon;
            $thisSun->modify('+6 days');
            $thisMonStr = $thisMon->format('Y-m-d');
            $thisSunStr = $thisSun->format('Y-m-d');

            // Föregående vecka
            $prevMon = clone $thisMon;
            $prevMon->modify('-7 days');
            $prevSun = clone $prevMon;
            $prevSun->modify('+6 days');
            $prevMonStr = $prevMon->format('Y-m-d');
            $prevSunStr = $prevSun->format('Y-m-d');

            $thisWeek = $this->fetchWeekStats($thisMonStr, $thisSunStr);
            $prevWeek = $this->fetchWeekStats($prevMonStr, $prevSunStr);

            // Diff
            $totalIbcPct = $prevWeek['total_ibc'] > 0
                ? round(($thisWeek['total_ibc'] - $prevWeek['total_ibc']) / $prevWeek['total_ibc'] * 100, 1)
                : null;
            $avgIbcPct   = $prevWeek['avg_ibc_per_day'] > 0
                ? round(($thisWeek['avg_ibc_per_day'] - $prevWeek['avg_ibc_per_day']) / $prevWeek['avg_ibc_per_day'] * 100, 1)
                : null;
            $oeeDiff     = round($thisWeek['avg_oee_pct'] - $prevWeek['avg_oee_pct'], 1);
            $qualityDiff = round($thisWeek['avg_quality_pct'] - $prevWeek['avg_quality_pct'], 1);

            // Bästa operatör denna vecka
            $operatorOfWeek = null;
            $sqlOp = "
                SELECT op_id, o.name AS namn, o.initialer,
                       SUM(delta_ok) AS total_ibc,
                       SUM(delta_ok) / NULLIF(SUM(runtime_h), 0) AS avg_ibc_per_h,
                       SUM(delta_ok) / NULLIF(SUM(delta_ok + delta_ej), 0) * 100 AS avg_quality_pct
                FROM (
                    SELECT op1 AS op_id, DATE(datum) AS datum, skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok) AS delta_ok,
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok) AS delta_ej,
                           MAX(runtime_plc)/3600.0 AS runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op1 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op1
                    UNION ALL
                    SELECT op2, DATE(datum), skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok),
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok),
                           MAX(runtime_plc)/3600.0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op2 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op2
                    UNION ALL
                    SELECT op3, DATE(datum), skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok),
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok),
                           MAX(runtime_plc)/3600.0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op3 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op3
                ) raw
                JOIN operators o ON o.id = raw.op_id
                GROUP BY op_id
                ORDER BY total_ibc DESC
                LIMIT 1
            ";
            $stmtOp = $this->pdo->prepare($sqlOp);
            $stmtOp->execute([
                $thisMonStr, $thisSunStr,
                $thisMonStr, $thisSunStr,
                $thisMonStr, $thisSunStr,
            ]);
            $opRow = $stmtOp->fetch();
            if ($opRow) {
                $operatorOfWeek = [
                    'op_id'           => intval($opRow['op_id']),
                    'namn'            => $opRow['namn'],
                    'initialer'       => $opRow['initialer'] ?? strtoupper(substr($opRow['namn'], 0, 2)),
                    'total_ibc'       => intval($opRow['total_ibc'] ?? 0),
                    'avg_ibc_per_h'   => round(floatval($opRow['avg_ibc_per_h'] ?? 0), 1),
                    'avg_quality_pct' => round(floatval($opRow['avg_quality_pct'] ?? 0), 1),
                ];
            }

            echo json_encode([
                'success'          => true,
                'this_week'        => $thisWeek,
                'prev_week'        => $prevWeek,
                'diff'             => [
                    'total_ibc_pct'         => $totalIbcPct,
                    'avg_ibc_per_day_pct'   => $avgIbcPct,
                    'avg_oee_pct_diff'      => $oeeDiff,
                    'avg_quality_pct_diff'  => $qualityDiff,
                ],
                'operator_of_week' => $operatorOfWeek,
            ]);

        } catch (Exception $e) {
            error_log("WeeklyReportController::getWeekCompare error: " . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }

    // -----------------------------------------------------------------------
    // GET ?action=weekly-report&run=summary&week=YYYY-WXX
    // -----------------------------------------------------------------------
    private function getSummary(): void {
        try {
            // Parsa veckoparameter
            $weekParam = $_GET['week'] ?? '';
            if (preg_match('/^(\d{4})-W(\d{2})$/', $weekParam, $m)) {
                $year = intval($m[1]);
                $week = intval($m[2]);
            } else {
                // Default: förra veckan
                $dt = new DateTime('last monday -1 week');
                $year = intval($dt->format('o'));
                $week = intval($dt->format('W'));
            }

            // Beräkna måndag och söndag för veckan
            $monday = new DateTime();
            $monday->setISODate($year, $week, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            $mondayStr = $monday->format('Y-m-d');
            $sundayStr = $sunday->format('Y-m-d');

            // Hämta veckomål från settings
            $dagmal = 1200; // fallback
            try {
                $stmtGoal = $this->pdo->query("SELECT dagmal FROM rebotling_settings LIMIT 1");
                $goalRow = $stmtGoal->fetch();
                if ($goalRow && isset($goalRow['dagmal'])) {
                    $dagmal = intval($goalRow['dagmal']);
                }
            } catch (Exception $e) {
                error_log("WeeklyReportController: kunde ej hämta dagmal: " . $e->getMessage());
            }

            // Daglig aggregering
            $sqlDaily = "
                SELECT DATE(datum) AS dag,
                       SUM(delta_ok) AS ibc_ok,
                       SUM(delta_ej) AS ibc_ej,
                       SUM(delta_ok + delta_ej) AS ibc_total,
                       SUM(delta_ok) / NULLIF(SUM(delta_ok + delta_ej), 0) * 100 AS kvalitet_pct,
                       SUM(runtime_h) AS drifttid_h,
                       SUM(delta_ok) / NULLIF(SUM(runtime_h), 0) AS ibc_per_h
                FROM (
                    SELECT DATE(datum) AS datum, skiftraknare,
                           MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok,
                           MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS delta_ej,
                           MAX(runtime_plc) / 3600.0 AS runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ?
                    GROUP BY DATE(datum), skiftraknare
                ) x
                GROUP BY DATE(datum)
                ORDER BY dag
            ";
            $stmtDaily = $this->pdo->prepare($sqlDaily);
            $stmtDaily->execute([$mondayStr, $sundayStr]);
            $daily = $stmtDaily->fetchAll();

            // Formatera daglig data
            $dailyFormatted = [];
            foreach ($daily as $row) {
                $dailyFormatted[] = [
                    'dag'          => $row['dag'],
                    'ibc_ok'       => intval($row['ibc_ok'] ?? 0),
                    'ibc_ej'       => intval($row['ibc_ej'] ?? 0),
                    'ibc_total'    => intval($row['ibc_total'] ?? 0),
                    'kvalitet_pct' => round(floatval($row['kvalitet_pct'] ?? 0), 1),
                    'drifttid_h'   => round(floatval($row['drifttid_h'] ?? 0), 1),
                    'ibc_per_h'    => round(floatval($row['ibc_per_h'] ?? 0), 1),
                ];
            }

            // Beräkna KPI-totaler
            $totalIbcOk  = array_sum(array_column($dailyFormatted, 'ibc_ok'));
            $totalIbcEj  = array_sum(array_column($dailyFormatted, 'ibc_ej'));
            $totalIbcTot = $totalIbcOk + $totalIbcEj;
            $drifttidH   = array_sum(array_column($dailyFormatted, 'drifttid_h'));

            $kvalitetPct = $totalIbcTot > 0
                ? round($totalIbcOk / $totalIbcTot * 100, 1)
                : 0.0;

            $snittIbcPerH = $drifttidH > 0
                ? round($totalIbcOk / $drifttidH, 1)
                : 0.0;

            // Antal vardagar (mån-fre) i perioden med produktion
            $dagPaMal = 0;
            $totaltVardagar = 0;
            foreach ($dailyFormatted as $d) {
                $dow = date('N', strtotime($d['dag'])); // 1=Mon, 7=Sun
                if ($dow <= 5) { // Måndag till fredag
                    $totaltVardagar++;
                    if ($d['ibc_ok'] >= $dagmal) {
                        $dagPaMal++;
                    }
                }
            }
            // Minimum 5 vardagar i en vecka
            if ($totaltVardagar === 0) $totaltVardagar = 5;

            $malPerVecka    = $dagmal * 5;
            $malUppfylldPct = $malPerVecka > 0
                ? round($totalIbcOk / $malPerVecka * 100, 1)
                : 0.0;

            // Bästa och sämsta dag
            $bestDay  = null;
            $worstDay = null;
            if (!empty($dailyFormatted)) {
                $bestDay  = $dailyFormatted[0];
                $worstDay = $dailyFormatted[0];
                foreach ($dailyFormatted as $d) {
                    if ($d['ibc_ok'] > $bestDay['ibc_ok']) $bestDay  = $d;
                    if ($d['ibc_ok'] < $worstDay['ibc_ok']) $worstDay = $d;
                }
            }

            // Operatörsranking för veckan
            $sqlOp = "
                SELECT op_id, o.name,
                       SUM(delta_ok) AS ibc_ok_vecka,
                       SUM(delta_ok) / NULLIF(SUM(runtime_h), 0) AS snitt_ibc_per_h,
                       SUM(delta_ok) / NULLIF(SUM(delta_ok + delta_ej), 0) * 100 AS kvalitet_pct,
                       COUNT(DISTINCT CONCAT(datum, '-', skiftraknare)) AS antal_skift
                FROM (
                    SELECT op1 AS op_id, DATE(datum) AS datum, skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok) AS delta_ok,
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok) AS delta_ej,
                           MAX(runtime_plc)/3600.0 AS runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op1 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op1
                    UNION ALL
                    SELECT op2, DATE(datum), skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok),
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok),
                           MAX(runtime_plc)/3600.0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op2 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op2
                    UNION ALL
                    SELECT op3, DATE(datum), skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok),
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok),
                           MAX(runtime_plc)/3600.0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op3 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op3
                ) raw
                JOIN operators o ON o.id = raw.op_id
                GROUP BY op_id
                ORDER BY ibc_ok_vecka DESC
            ";
            $stmtOp = $this->pdo->prepare($sqlOp);
            $stmtOp->execute([
                $mondayStr, $sundayStr,
                $mondayStr, $sundayStr,
                $mondayStr, $sundayStr,
            ]);
            $operators = $stmtOp->fetchAll();

            $operatorsFormatted = [];
            foreach ($operators as $op) {
                $operatorsFormatted[] = [
                    'name'            => $op['name'],
                    'ibc_ok_vecka'    => intval($op['ibc_ok_vecka'] ?? 0),
                    'snitt_ibc_per_h' => round(floatval($op['snitt_ibc_per_h'] ?? 0), 1),
                    'kvalitet_pct'    => round(floatval($op['kvalitet_pct'] ?? 0), 1),
                    'antal_skift'     => intval($op['antal_skift'] ?? 0),
                ];
            }

            echo json_encode([
                'success' => true,
                'week'    => sprintf('%04d-W%02d', $year, $week),
                'period'  => [
                    'from' => $mondayStr,
                    'to'   => $sundayStr,
                ],
                'kpi' => [
                    'total_ibc_ok'      => $totalIbcOk,
                    'total_ibc_ej'      => $totalIbcEj,
                    'kvalitet_pct'      => $kvalitetPct,
                    'drifttid_h'        => round($drifttidH, 1),
                    'snitt_ibc_per_h'   => $snittIbcPerH,
                    'dagmal'            => $dagmal,
                    'mal_per_vecka'     => $malPerVecka,
                    'mal_uppfylld_pct'  => $malUppfylldPct,
                    'dagar_pa_mal'      => $dagPaMal,
                    'totalt_vardagar'   => $totaltVardagar,
                ],
                'daily'     => $dailyFormatted,
                'best_day'  => $bestDay,
                'worst_day' => $worstDay,
                'operators' => $operatorsFormatted,
            ]);

        } catch (Exception $e) {
            error_log("WeeklyReportController::getSummary error: " . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }
}
