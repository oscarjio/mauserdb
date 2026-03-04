<?php
/**
 * WeeklyReportController.php
 * Veckosummering för VD — KPI, daglig produktion, bästa/sämsta dag, operatörsranking.
 *
 * Endpoint:
 * - GET ?action=weekly-report&run=summary&week=YYYY-WXX
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
            'summary' => $this->getSummary(),
            default   => $this->sendError('Okänd metod', 400)
        };
    }

    private function sendError(string $msg, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $msg]);
    }

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

            // Antal vardagar (mån–fre) i perioden med produktion
            $dagPaMal = 0;
            $totaltVardagar = 0;
            foreach ($dailyFormatted as $d) {
                $dow = date('N', strtotime($d['dag'])); // 1=Mån, 7=Sön
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
                    'name'          => $op['name'],
                    'ibc_ok_vecka'  => intval($op['ibc_ok_vecka'] ?? 0),
                    'snitt_ibc_per_h' => round(floatval($op['snitt_ibc_per_h'] ?? 0), 1),
                    'kvalitet_pct'  => round(floatval($op['kvalitet_pct'] ?? 0), 1),
                    'antal_skift'   => intval($op['antal_skift'] ?? 0),
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
