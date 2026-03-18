<?php
/**
 * StatistikDashboardController.php
 * Statistik-dashboard — komplett produktionsöverblick för VD
 *
 * Endpoints via ?action=statistikdashboard&run=XXX:
 *   - run=summary            → alla KPI:er samlade (idag/igår/vecka/förra veckan, kassation%, drifttid%, aktiv operatör, snitt IBC/h 7d)
 *   - run=production-trend   (?period=30) → daglig data: datum, ibc_ok, ibc_ej_ok, kassation_pct, drifttid_h, ibc_per_h
 *   - run=daily-table        → senaste 7 dagars detaljerad tabell med bästa operatör per dag
 *   - run=status-indicator   → beräkna grön/gul/röd baserat på dagens data vs mål
 *
 * Tabeller:
 *   rebotling_ibc             (ibc_ok, ibc_ej_ok, datum, skiftraknare, op1, op2, op3)
 *   rebotling_skiftrapport    (datum, skiftraknare, op1, op2, op3, ibc_ok, ibc_ej_ok, drifttid)
 *   operators                 (id, number, name)
 */
class StatistikDashboardController {
    private $pdo;

    // Mål: IBC per timme (kan justeras)
    const IBC_PER_H_MAL = 15;
    // Mål: kassation %
    const KASSATION_MAL_PCT = 5.0;
    // Planerad drifttid per dag (timmar) — 16h = 2 skift
    const PLANERAD_DRIFTTID_H = 16;

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
            case 'summary':           $this->getSummary();          break;
            case 'production-trend':  $this->getProductionTrend();  break;
            case 'daily-table':       $this->getDailyTable();        break;
            case 'status-indicator':  $this->getStatusIndicator();   break;
            default:
                $this->sendError('Okänt run-värde: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function sendJson(array $data): void {
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
     * Hämta IBC-produktions-summering för en dagsintervall.
     * Returnerar: ['ibc_ok' => X, 'ibc_ej_ok' => Y, 'total' => Z, 'kassation_pct' => W, 'drifttid_h' => D]
     */
    private function getDaySummary(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(shift_ok), 0)      AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0)   AS ibc_ej_ok,
                COALESCE(SUM(shift_drift), 0)    AS drifttid_min
            FROM (
                SELECT
                    DATE(datum)     AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok,
                    0                            AS shift_drift
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS per_shift
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ok    = (int)($row['ibc_ok']    ?? 0);
        $ejOk  = (int)($row['ibc_ej_ok'] ?? 0);
        $total = $ok + $ejOk;
        $pct   = $total > 0 ? round($ejOk / $total * 100, 2) : 0.0;

        // Hämta drifttid från skiftrapport
        $stmtDrift = $this->pdo->prepare("
            SELECT COALESCE(SUM(drifttid), 0) AS tot_drift
            FROM rebotling_skiftrapport
            WHERE datum BETWEEN :from_date AND :to_date
        ");
        $stmtDrift->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $driftRow = $stmtDrift->fetch(\PDO::FETCH_ASSOC);
        $drifttidMin = (int)($driftRow['tot_drift'] ?? 0);
        $drifttidH   = round($drifttidMin / 60, 2);

        return [
            'ibc_ok'        => $ok,
            'ibc_ej_ok'     => $ejOk,
            'total'         => $total,
            'kassation_pct' => $pct,
            'drifttid_h'    => $drifttidH,
        ];
    }

    /**
     * Hämta vecko-IBC summering (måndag–söndag).
     */
    private function getWeekSummary(string $monday, string $sunday): array {
        return $this->getDaySummary($monday, $sunday);
    }

    /**
     * Hämta aktiv operatör — senaste rad i rebotling_ibc.
     */
    private function getActiveOperator(): ?array {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    i.op1,
                    o1.name AS op1_name,
                    i.datum
                FROM rebotling_ibc i
                LEFT JOIN operators o1 ON o1.number = i.op1
                WHERE i.op1 IS NOT NULL
                ORDER BY i.datum DESC, i.id DESC
                LIMIT 1
            ");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && $row['op1']) {
                return [
                    'operator_id'   => (int)$row['op1'],
                    'operator_name' => $row['op1_name'] ?? ('Operatör ' . $row['op1']),
                    'senaste_datum' => $row['datum'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('StatistikDashboardController::getActiveOperator: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Beräkna snitt IBC/h för en period.
     */
    private function avgIbcPerH(string $fromDate, string $toDate): float {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok + shift_ej_ok), 0) AS total_ibc,
                    COALESCE(SUM(shift_drift), 0)             AS total_drift_min
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok,
                        0                            AS shift_drift
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Hämta drifttid separat
            $stmtD = $this->pdo->prepare("
                SELECT COALESCE(SUM(drifttid), 0) AS tot_drift
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from_date AND :to_date
            ");
            $stmtD->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $dRow = $stmtD->fetch(\PDO::FETCH_ASSOC);

            $totalIbc  = (int)($row['total_ibc'] ?? 0);
            $driftMin  = (int)($dRow['tot_drift'] ?? 0);
            $driftH    = $driftMin / 60;

            if ($driftH > 0) {
                return round($totalIbc / $driftH, 2);
            }
            // Fallback: anta 16h per dag
            $days = (int)(new \DateTime($fromDate))->diff(new \DateTime($toDate))->days + 1;
            $antsDagar = max(1, $days);
            $h = $antsDagar * self::PLANERAD_DRIFTTID_H;
            return $h > 0 ? round($totalIbc / $h, 2) : 0.0;
        } catch (\PDOException $e) {
            error_log('StatistikDashboardController::avgIbcPerH: ' . $e->getMessage());
            return 0.0;
        }
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    private function getSummary(): void {
        try {
            $today     = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // Veckans måndag och söndag
            $dow         = (int)date('N'); // 1=mån, 7=sön
            $thisMonday  = date('Y-m-d', strtotime("-" . ($dow - 1) . " days"));
            $thisSunday  = date('Y-m-d', strtotime($thisMonday . ' +6 days'));
            $lastMonday  = date('Y-m-d', strtotime($thisMonday . ' -7 days'));
            $lastSunday  = date('Y-m-d', strtotime($thisMonday . ' -1 day'));

            // Idag och igår
            $todayData     = $this->getDaySummary($today, $today);
            $yesterdayData = $this->getDaySummary($yesterday, $yesterday);

            // Denna vecka och förra veckan
            $thisWeekData = $this->getWeekSummary($thisMonday, $thisSunday);
            $lastWeekData = $this->getWeekSummary($lastMonday, $lastSunday);

            // Drifttid idag i %
            $planH          = self::PLANERAD_DRIFTTID_H;
            $drifttidIdagPct = $planH > 0
                ? round(($todayData['drifttid_h'] / $planH) * 100, 1)
                : 0.0;

            // Aktiv operatör
            $aktivOp = $this->getActiveOperator();

            // Snitt IBC/h senaste 7 dagar
            $from7d     = date('Y-m-d', strtotime('-6 days'));
            $snittIbcH7 = $this->avgIbcPerH($from7d, $today);

            // IBC/h idag
            $ibcHIdag = $todayData['drifttid_h'] > 0
                ? round($todayData['total'] / $todayData['drifttid_h'], 2)
                : 0.0;

            $this->sendJson([
                'idag' => [
                    'ibc_ok'         => $todayData['ibc_ok'],
                    'ibc_ej_ok'      => $todayData['ibc_ej_ok'],
                    'total'          => $todayData['total'],
                    'kassation_pct'  => $todayData['kassation_pct'],
                    'drifttid_h'     => $todayData['drifttid_h'],
                    'drifttid_pct'   => $drifttidIdagPct,
                    'ibc_per_h'      => $ibcHIdag,
                ],
                'igar' => [
                    'ibc_ok'         => $yesterdayData['ibc_ok'],
                    'ibc_ej_ok'      => $yesterdayData['ibc_ej_ok'],
                    'total'          => $yesterdayData['total'],
                    'kassation_pct'  => $yesterdayData['kassation_pct'],
                    'drifttid_h'     => $yesterdayData['drifttid_h'],
                ],
                'denna_vecka' => [
                    'ibc_ok'         => $thisWeekData['ibc_ok'],
                    'ibc_ej_ok'      => $thisWeekData['ibc_ej_ok'],
                    'total'          => $thisWeekData['total'],
                    'kassation_pct'  => $thisWeekData['kassation_pct'],
                    'drifttid_h'     => $thisWeekData['drifttid_h'],
                    'vecko_start'    => $thisMonday,
                ],
                'forra_veckan' => [
                    'ibc_ok'         => $lastWeekData['ibc_ok'],
                    'ibc_ej_ok'      => $lastWeekData['ibc_ej_ok'],
                    'total'          => $lastWeekData['total'],
                    'kassation_pct'  => $lastWeekData['kassation_pct'],
                    'drifttid_h'     => $lastWeekData['drifttid_h'],
                    'vecko_start'    => $lastMonday,
                ],
                'aktiv_operator'  => $aktivOp,
                'snitt_ibc_per_h' => $snittIbcH7,
                'mal_ibc_per_h'   => self::IBC_PER_H_MAL,
                'mal_kassation'   => self::KASSATION_MAL_PCT,
                'planerad_drift_h'=> self::PLANERAD_DRIFTTID_H,
            ]);
        } catch (\PDOException $e) {
            error_log('StatistikDashboardController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // ================================================================
    // ENDPOINT: production-trend
    // ================================================================

    private function getProductionTrend(): void {
        $period = (int)($_GET['period'] ?? 30);
        if (!in_array($period, [7, 14, 30, 60, 90], true)) {
            $period = 30;
        }

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        try {
            // Daglig IBC-data
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    COALESCE(SUM(shift_ok), 0)      AS ibc_ok,
                    COALESCE(SUM(shift_ej_ok), 0)   AS ibc_ej_ok
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $ibcRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Daglig drifttid från skiftrapport
            $stmtD = $this->pdo->prepare("
                SELECT datum, COALESCE(SUM(drifttid), 0) AS drift_min
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from_date AND :to_date
                GROUP BY datum
                ORDER BY datum ASC
            ");
            $stmtD->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $driftRows = $stmtD->fetchAll(\PDO::FETCH_ASSOC);
            $driftMap = [];
            foreach ($driftRows as $dr) {
                $driftMap[$dr['datum']] = (int)$dr['drift_min'];
            }

            $daily = [];
            $totalIbc = 0;
            $totalH   = 0;

            foreach ($ibcRows as $row) {
                $dag   = $row['dag'];
                $ok    = (int)$row['ibc_ok'];
                $ejOk  = (int)$row['ibc_ej_ok'];
                $total = $ok + $ejOk;
                $pct   = $total > 0 ? round($ejOk / $total * 100, 2) : 0.0;
                $driftMin = $driftMap[$dag] ?? 0;
                $driftH   = round($driftMin / 60, 2);
                $ibcH     = $driftH > 0
                    ? round($total / $driftH, 2)
                    : ($total > 0 ? round($total / self::PLANERAD_DRIFTTID_H, 2) : 0.0);

                $totalIbc += $total;
                $totalH   += $driftH > 0 ? $driftH : self::PLANERAD_DRIFTTID_H;

                $daily[] = [
                    'datum'         => $dag,
                    'ibc_ok'        => $ok,
                    'ibc_ej_ok'     => $ejOk,
                    'total'         => $total,
                    'kassation_pct' => $pct,
                    'drifttid_h'    => $driftH,
                    'ibc_per_h'     => $ibcH,
                ];
            }

            $daysCount  = count($daily);
            $snittTotal = $daysCount > 0 ? round($totalIbc / $daysCount, 1) : 0;
            $snittIbcH  = $totalH > 0 ? round($totalIbc / $totalH, 2) : 0.0;

            $this->sendJson([
                'daily'          => $daily,
                'period'         => $period,
                'snitt_ibc_dag'  => $snittTotal,
                'snitt_ibc_h'    => $snittIbcH,
                'mal_ibc_per_h'  => self::IBC_PER_H_MAL,
            ]);
        } catch (\PDOException $e) {
            error_log('StatistikDashboardController::getProductionTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta produktionstrend', 500);
        }
    }

    // ================================================================
    // ENDPOINT: daily-table
    // ================================================================

    private function getDailyTable(): void {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-6 days'));

        try {
            // Daglig IBC-data
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $ibcRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Daglig drifttid
            $stmtD = $this->pdo->prepare("
                SELECT datum, COALESCE(SUM(drifttid), 0) AS drift_min
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from_date AND :to_date
                GROUP BY datum
            ");
            $stmtD->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $driftMap = [];
            foreach ($stmtD->fetchAll(\PDO::FETCH_ASSOC) as $dr) {
                $driftMap[$dr['datum']] = (int)$dr['drift_min'];
            }

            // Bästa operatör per dag (flest IBC producerade = minst kassation bland dem med flest total)
            $stmtOp = $this->pdo->prepare("
                SELECT
                    s.datum,
                    s.op1,
                    o1.name AS op1_name,
                    COALESCE(s.ibc_ok, 0) + COALESCE(s.ibc_ej_ok, 0) AS total_ibc,
                    COALESCE(s.ibc_ok, 0) AS ibc_ok_skift
                FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                WHERE s.datum BETWEEN :from_date AND :to_date
                  AND s.op1 IS NOT NULL
                ORDER BY s.datum ASC, total_ibc DESC
            ");
            $stmtOp->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $opRows = $stmtOp->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg en karta: datum => bästa operatör (högst ibc_ok)
            $bestOpMap = [];
            foreach ($opRows as $opRow) {
                $d = $opRow['datum'];
                if (!isset($bestOpMap[$d])) {
                    $bestOpMap[$d] = [
                        'operator_id'   => (int)$opRow['op1'],
                        'operator_name' => $opRow['op1_name'] ?? ('Operatör ' . $opRow['op1']),
                        'ibc_ok'        => (int)$opRow['ibc_ok_skift'],
                    ];
                }
            }

            $rows = [];
            $sumOk   = 0;
            $sumEjOk = 0;
            $sumH    = 0;
            $sumIbc  = 0;

            foreach ($ibcRows as $row) {
                $dag   = $row['dag'];
                $ok    = (int)$row['ibc_ok'];
                $ejOk  = (int)$row['ibc_ej_ok'];
                $total = $ok + $ejOk;
                $pct   = $total > 0 ? round($ejOk / $total * 100, 2) : 0.0;
                $driftMin = $driftMap[$dag] ?? 0;
                $driftH   = round($driftMin / 60, 2);
                $ibcH     = $driftH > 0
                    ? round($total / $driftH, 2)
                    : ($total > 0 ? round($total / self::PLANERAD_DRIFTTID_H, 2) : 0.0);

                // Färgklass baserat på kassation
                if ($pct < self::KASSATION_MAL_PCT) {
                    $fargklass = 'grön';
                } elseif ($pct <= 10.0) {
                    $fargklass = 'gul';
                } else {
                    $fargklass = 'röd';
                }

                $rows[] = [
                    'datum'          => $dag,
                    'ibc_ok'         => $ok,
                    'ibc_ej_ok'      => $ejOk,
                    'total'          => $total,
                    'kassation_pct'  => $pct,
                    'drifttid_h'     => $driftH,
                    'ibc_per_h'      => $ibcH,
                    'basta_operator' => $bestOpMap[$dag] ?? null,
                    'fargklass'      => $fargklass,
                ];

                $sumOk   += $ok;
                $sumEjOk += $ejOk;
                $sumH    += $driftH > 0 ? $driftH : 0;
                $sumIbc  += $total;
            }

            // Veckosnitt
            $n         = count($rows);
            $snittPct  = ($sumOk + $sumEjOk) > 0
                ? round($sumEjOk / ($sumOk + $sumEjOk) * 100, 2) : 0.0;
            $snittH    = $n > 0 ? round($sumH / $n, 2) : 0.0;
            $snittIbcH = $sumH > 0 ? round($sumIbc / $sumH, 2) : 0.0;

            $this->sendJson([
                'rows' => $rows,
                'veckosnitt' => [
                    'ibc_ok'        => $sumOk,
                    'ibc_ej_ok'     => $sumEjOk,
                    'total'         => $sumIbc,
                    'kassation_pct' => $snittPct,
                    'drifttid_h'    => $snittH,
                    'ibc_per_h'     => $snittIbcH,
                ],
                'mal_kassation' => self::KASSATION_MAL_PCT,
            ]);
        } catch (\PDOException $e) {
            error_log('StatistikDashboardController::getDailyTable: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta dagstabell', 500);
        }
    }

    // ================================================================
    // ENDPOINT: status-indicator
    // ================================================================

    private function getStatusIndicator(): void {
        try {
            $today     = date('Y-m-d');
            $todayData = $this->getDaySummary($today, $today);

            // IBC/h idag
            $driftH  = $todayData['drifttid_h'];
            $ibcHIdag = $driftH > 0 && $todayData['total'] > 0
                ? round($todayData['total'] / $driftH, 2)
                : 0.0;

            // Stopporsaker idag
            $stoppMinuter = 0;
            try {
                $stmtS = $this->pdo->prepare("
                    SELECT COALESCE(SUM(duration_minutes), 0) AS total_min
                    FROM stoppage_log
                    WHERE DATE(start_time) = :datum
                ");
                $stmtS->execute([':datum' => $today]);
                $sRow = $stmtS->fetch(\PDO::FETCH_ASSOC);
                $stoppMinuter = (int)($sRow['total_min'] ?? 0);
            } catch (\PDOException $e) {
                error_log('StatistikDashboardController::getStatusIndicator stoppage_log: ' . $e->getMessage());
            }

            // Beräkna status
            $problem = [];
            $varning = [];

            $kassationIdag = $todayData['kassation_pct'];
            $malIbcH       = self::IBC_PER_H_MAL;
            $malKassation  = self::KASSATION_MAL_PCT;

            if ($kassationIdag > 10.0) {
                $problem[] = "Kassationsgrad " . $kassationIdag . "% (mål <" . $malKassation . "%)";
            } elseif ($kassationIdag > $malKassation) {
                $varning[] = "Kassationsgrad " . $kassationIdag . "% (mål <" . $malKassation . "%)";
            }

            if ($todayData['total'] > 0 && $ibcHIdag < $malIbcH * 0.7) {
                $problem[] = "IBC/h idag: " . $ibcHIdag . " (mål " . $malIbcH . ")";
            } elseif ($todayData['total'] > 0 && $ibcHIdag < $malIbcH) {
                $varning[] = "IBC/h idag: " . $ibcHIdag . " (mål " . $malIbcH . ")";
            }

            if ($stoppMinuter > 60) {
                $varning[] = "Stopp idag: " . $stoppMinuter . " min";
            }

            if (count($problem) > 0) {
                $status     = 'röd';
                $statusText = 'Problem';
                $statusIcon = 'fas fa-times-circle';
            } elseif (count($varning) > 0) {
                $status     = 'gul';
                $statusText = 'Uppmärksamhet';
                $statusIcon = 'fas fa-exclamation-triangle';
            } else {
                $status     = 'grön';
                $statusText = 'Allt bra';
                $statusIcon = 'fas fa-check-circle';
            }

            $this->sendJson([
                'status'          => $status,
                'status_text'     => $statusText,
                'status_icon'     => $statusIcon,
                'problem'         => $problem,
                'varning'         => $varning,
                'kassation_idag'  => $kassationIdag,
                'ibc_per_h_idag'  => $ibcHIdag,
                'stopp_min_idag'  => $stoppMinuter,
                'mal_ibc_per_h'   => $malIbcH,
                'mal_kassation'   => $malKassation,
            ]);
        } catch (\PDOException $e) {
            error_log('StatistikDashboardController::getStatusIndicator: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta statusindikator', 500);
        }
    }
}
