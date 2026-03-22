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
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Endast GET tillåtet'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        match ($run) {
            'summary'      => $this->getSummary(),
            'week-compare' => $this->getWeekCompare(),
            default        => $this->sendError('Okänd metod', 400)
        };
    }

    private function sendError(string $msg, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    }

    // -------------------------------------------------------------------------
    // week-compare: returnerar stats för vald vecka + föregående vecka + diff
    // -------------------------------------------------------------------------
    private function getWeekCompare(): void {
        try {
            $weekStartParam = trim($_GET['week_start'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
                $this->sendError('Ogiltigt week_start-format (YYYY-MM-DD)', 400);
                return;
            }

            $thisMonday = new DateTime($weekStartParam, new DateTimeZone('Europe/Stockholm'));
            $thisSunday = clone $thisMonday;
            $thisSunday->modify('+6 days');

            $prevMonday = clone $thisMonday;
            $prevMonday->modify('-7 days');
            $prevSunday = clone $prevMonday;
            $prevSunday->modify('+6 days');

            $thisMon = $thisMonday->format('Y-m-d');
            $thisSun = $thisSunday->format('Y-m-d');
            $prevMon = $prevMonday->format('Y-m-d');
            $prevSun = $prevSunday->format('Y-m-d');

            $thisWeekData = $this->aggregateWeekStats($thisMon, $thisSun, $thisMonday);
            $prevWeekData = $this->aggregateWeekStats($prevMon, $prevSun, $prevMonday);

            // diff
            $diffTotalIbcPct = $prevWeekData['total_ibc'] > 0
                ? round(($thisWeekData['total_ibc'] - $prevWeekData['total_ibc']) / $prevWeekData['total_ibc'] * 100, 1)
                : null;

            $diffAvgIbcDayPct = $prevWeekData['avg_ibc_per_day'] > 0
                ? round(($thisWeekData['avg_ibc_per_day'] - $prevWeekData['avg_ibc_per_day']) / $prevWeekData['avg_ibc_per_day'] * 100, 1)
                : null;

            $diffOeePctDiff = round($thisWeekData['avg_oee_pct'] - $prevWeekData['avg_oee_pct'], 1);
            $diffQualityPctDiff = round($thisWeekData['avg_quality_pct'] - $prevWeekData['avg_quality_pct'], 1);

            // Veckans bästa operatör (för vald vecka)
            $operatorOfWeek = $this->getOperatorOfWeek($thisMon, $thisSun);

            echo json_encode([
                'success'           => true,
                'this_week'         => $thisWeekData,
                'prev_week'         => $prevWeekData,
                'diff'              => [
                    'total_ibc_pct'        => $diffTotalIbcPct,
                    'avg_ibc_per_day_pct'  => $diffAvgIbcDayPct,
                    'avg_oee_pct_diff'     => $diffOeePctDiff,
                    'avg_quality_pct_diff' => $diffQualityPctDiff,
                ],
                'operator_of_week'  => $operatorOfWeek,
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("WeeklyReportController::getWeekCompare: " . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }

    /**
     * Aggregera veckostatistik för en given period.
     * Returnerar array med total_ibc, avg_ibc_per_day, avg_oee_pct, avg_quality_pct,
     * best_day_ibc, best_day_date, working_days, week_label.
     */
    private function aggregateWeekStats(string $fromDate, string $toDate, DateTime $mondayDt): array {
        // OEE-definition: total runtime / (antal skift * skiftlängd)
        // Skiftlängd = 8 h = 28800 sek
        $shiftSeconds = 28800;

        $sql = "
            SELECT DATE(datum) AS dag,
                   SUM(delta_ok)              AS ibc_ok,
                   SUM(delta_ok + delta_ej)   AS ibc_total,
                   SUM(runtime_sek)           AS runtime_sek,
                   COUNT(DISTINCT skiftraknare) AS num_shifts
            FROM (
                SELECT DATE(datum) AS datum, skiftraknare,
                       MAX(ibc_ok)      - MIN(ibc_ok)      AS delta_ok,
                       MAX(ibc_ej_ok)   - MIN(ibc_ej_ok)   AS delta_ej,
                       MAX(runtime_plc)                     AS runtime_sek
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN ? AND ?
                GROUP BY DATE(datum), skiftraknare
            ) x
            GROUP BY DATE(datum)
            ORDER BY dag
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalIbc      = 0;
        $totalIbcTotal = 0;
        $totalRuntime  = 0;
        $totalShifts   = 0;
        $workingDays   = 0;
        $bestIbc       = 0;
        $bestDate      = null;

        foreach ($rows as $r) {
            $ibcOk  = intval($r['ibc_ok'] ?? 0);
            $ibcTot = intval($r['ibc_total'] ?? 0);
            $rt     = intval($r['runtime_sek'] ?? 0);
            $shifts = intval($r['num_shifts'] ?? 0);

            // Räkna bara vardagar
            $dow = date('N', strtotime($r['dag']));
            if ($dow <= 5) {
                $workingDays++;
            }

            $totalIbc      += $ibcOk;
            $totalIbcTotal += $ibcTot;
            $totalRuntime  += $rt;
            $totalShifts   += $shifts;

            if ($ibcOk > $bestIbc) {
                $bestIbc  = $ibcOk;
                $bestDate = $r['dag'];
            }
        }

        $avgIbcPerDay  = $workingDays > 0 ? round($totalIbc / $workingDays) : 0;
        $avgQuality    = $totalIbcTotal > 0 ? round($totalIbc / $totalIbcTotal * 100, 1) : 0.0;
        // OEE = runtime / (shifts * shiftSeconds)
        $maxRuntime    = $totalShifts * $shiftSeconds;
        $avgOee        = $maxRuntime > 0 ? round($totalRuntime / $maxRuntime * 100, 1) : 0.0;

        // ISO-veckonummer för perioden (använd måndagen)
        $weekNum = intval($mondayDt->format('W'));

        return [
            'total_ibc'       => $totalIbc,
            'avg_ibc_per_day' => $avgIbcPerDay,
            'avg_oee_pct'     => $avgOee,
            'avg_quality_pct' => $avgQuality,
            'best_day_ibc'    => $bestIbc,
            'best_day_date'   => $bestDate,
            'working_days'    => $workingDays,
            'week_label'      => 'v.' . $weekNum,
        ];
    }

    /**
     * Hämtar veckans bästa operatör för en given period.
     */
    private function getOperatorOfWeek(string $fromDate, string $toDate): ?array {
        $sqlOp = "
            SELECT op_id, o.name,
                   SUM(delta_ok)                              AS total_ibc,
                   SUM(delta_ok) / NULLIF(SUM(runtime_h), 0) AS avg_ibc_per_h,
                   SUM(delta_ok) / NULLIF(SUM(delta_ok + delta_ej), 0) * 100 AS avg_quality_pct
            FROM (
                SELECT op1 AS op_id, DATE(datum) AS datum, skiftraknare,
                       MAX(ibc_ok)-MIN(ibc_ok)     AS delta_ok,
                       MAX(ibc_ej_ok)-MIN(ibc_ej_ok) AS delta_ej,
                       MAX(runtime_plc)/3600.0      AS runtime_h
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
            JOIN operators o ON o.number = raw.op_id
            GROUP BY op_id
            ORDER BY total_ibc DESC
            LIMIT 1
        ";
        $stmtOp = $this->pdo->prepare($sqlOp);
        $stmtOp->execute([
            $fromDate, $toDate,
            $fromDate, $toDate,
            $fromDate, $toDate,
        ]);
        $op = $stmtOp->fetch(PDO::FETCH_ASSOC);

        if (!$op) return null;

        // Beräkna initialer från namn (operators-tabellen saknar initialer-kolumn)
        $words = preg_split('/\s+/', trim($op['name'] ?? ''));
        $initials = '';
        foreach ($words as $w) {
            if ($w !== '') $initials .= strtoupper(mb_substr($w, 0, 1));
        }

        return [
            'op_id'           => intval($op['op_id']),
            'namn'            => $op['name'],
            'initialer'       => $initials,
            'total_ibc'       => intval($op['total_ibc'] ?? 0),
            'avg_ibc_per_h'   => round(floatval($op['avg_ibc_per_h'] ?? 0), 1),
            'avg_quality_pct' => round(floatval($op['avg_quality_pct'] ?? 0), 1),
        ];
    }

    // -------------------------------------------------------------------------
    // summary (befintlig)
    // -------------------------------------------------------------------------
    private function getSummary(): void {
        try {
            // Parsa veckoparameter
            $weekParam = trim($_GET['week'] ?? '');
            if (preg_match('/^(\d{4})-W(\d{2})$/', $weekParam, $m)) {
                $year = intval($m[1]);
                $week = intval($m[2]);
            } else {
                // Default: förra veckan
                // -7 days från idag hamnar alltid i "förra veckan" oavsett veckodag
                $dt = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
                $dt->modify('-7 days');
                $year = intval($dt->format('o'));
                $week = intval($dt->format('W'));
            }

            // Beräkna måndag och söndag för veckan
            $monday = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
            $monday->setISODate($year, $week, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            $mondayStr = $monday->format('Y-m-d');
            $sundayStr = $sunday->format('Y-m-d');

            // Hämta veckomål från settings
            $dagmal = 1200; // fallback
            try {
                $stmtGoal = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings ORDER BY id ASC LIMIT 1");
                $goalRow = $stmtGoal->fetch(PDO::FETCH_ASSOC);
                if ($goalRow && isset($goalRow['rebotling_target'])) {
                    $dagmal = intval($goalRow['rebotling_target']);
                }
            } catch (Exception $e) {
                error_log("WeeklyReportController::getSummary: kunde ej hämta dagmal: " . $e->getMessage());
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
                           MAX(runtime_plc) / 60.0 AS runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ?
                    GROUP BY DATE(datum), skiftraknare
                ) x
                GROUP BY DATE(datum)
                ORDER BY dag
            ";
            $stmtDaily = $this->pdo->prepare($sqlDaily);
            $stmtDaily->execute([$mondayStr, $sundayStr]);
            $daily = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

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
                           MAX(runtime_plc)/60.0 AS runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op1 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op1
                    UNION ALL
                    SELECT op2, DATE(datum), skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok),
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok),
                           MAX(runtime_plc)/60.0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op2 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op2
                    UNION ALL
                    SELECT op3, DATE(datum), skiftraknare,
                           MAX(ibc_ok)-MIN(ibc_ok),
                           MAX(ibc_ej_ok)-MIN(ibc_ej_ok),
                           MAX(runtime_plc)/60.0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ? AND op3 IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op3
                ) raw
                JOIN operators o ON o.number = raw.op_id
                GROUP BY op_id
                ORDER BY ibc_ok_vecka DESC
            ";
            $stmtOp = $this->pdo->prepare($sqlOp);
            $stmtOp->execute([
                $mondayStr, $sundayStr,
                $mondayStr, $sundayStr,
                $mondayStr, $sundayStr,
            ]);
            $operators = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

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
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("WeeklyReportController::getSummary: " . $e->getMessage());
            $this->sendError('Internt serverfel', 500);
        }
    }
}
