<?php
/**
 * StatistikOverblickController.php
 * Statistik overblick — VD:ns go-to-sida "hur gar det?"
 * Enkel, ren oversikt med KPI:er och veckodata for grafer.
 *
 * Endpoints via ?action=statistik-overblick&run=XXX:
 *   run=kpi           -> 4 KPI-kort: total produktion (30d), snitt-OEE (30d), kassationsrate (30d), trend vs foregaende 30d
 *   run=produktion    -> produktion per vecka (antal IBC) for stapeldiagram
 *   run=oee           -> OEE% per vecka for linjediagram
 *   run=kassation     -> kassationsrate% per vecka for linjediagram
 *
 * Query-param: months=3|6|12 (default 3)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff
 */
class StatistikOverblickController {
    private $pdo;
    private const IDEAL_CYCLE_SEC = 120;
    private const SCHEMA_SEK_PER_DAG = 28800; // 8h

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
            case 'kpi':         $this->getKpi();        break;
            case 'produktion':  $this->getProduktion();  break;
            case 'oee':         $this->getOee();         break;
            case 'kassation':   $this->getKassation();   break;
            default:            $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function getMonths(): int {
        $m = intval($_GET['months'] ?? 3);
        if (!in_array($m, [3, 6, 12], true)) $m = 3;
        return $m;
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
    // run=kpi — 4 KPI-kort
    // ================================================================

    private function getKpi(): void {
        try {
            $today = date('Y-m-d');
            $from30 = date('Y-m-d', strtotime('-30 days'));
            $prevFrom = date('Y-m-d', strtotime('-60 days'));
            $prevTo = date('Y-m-d', strtotime('-31 days'));

            // --- Nuvarande period (senaste 30 dagar) ---
            $totalIbc = 0;
            $okIbc = 0;
            $kasserade = 0;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                        COALESCE(SUM(max_ibc_ej_ok), 0) AS kasserade
                    FROM (
                        SELECT skiftraknare, DATE(datum) AS dag,
                               MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_skift
                ");
                $stmt->execute([':from_date' => $from30, ':to_date' => $today]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $okIbc = (int)($row['ok_ibc'] ?? 0);
                $kasserade = (int)($row['kasserade'] ?? 0);
                $totalIbc = $okIbc + $kasserade;
            } catch (\Exception $e) {
                error_log('StatistikOverblickController::getKpi ibc: ' . $e->getMessage());
            }

            $kassationsrate = $totalIbc > 0 ? round(($kasserade / $totalIbc) * 100, 2) : 0;

            // OEE snitt senaste 30 dagar (dagvis, sen medelvarde)
            $oeeValues = [];
            $dagar = $this->getWorkingDays($from30, $today);
            foreach ($dagar as $dag) {
                $oee = $this->calcOeeForDay($dag);
                if ($oee['total_ibc'] > 0) {
                    $oeeValues[] = $oee['oee'];
                }
            }
            $snittOee = count($oeeValues) > 0 ? round((array_sum($oeeValues) / count($oeeValues)) * 100, 1) : 0;

            // --- Foregaende period (30 dagar innan) ---
            $prevTotalIbc = 0;
            $prevKasserade = 0;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                        COALESCE(SUM(max_ibc_ej_ok), 0) AS kasserade
                    FROM (
                        SELECT skiftraknare, DATE(datum) AS dag,
                               MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_skift
                ");
                $stmt->execute([':from_date' => $prevFrom, ':to_date' => $prevTo]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $prevKasserade = (int)($row['kasserade'] ?? 0);
                $prevTotalIbc = (int)($row['ok_ibc'] ?? 0) + $prevKasserade;
            } catch (\Exception $e) {
                error_log('StatistikOverblickController::getKpi prev: ' . $e->getMessage());
            }

            $prevKassationsrate = $prevTotalIbc > 0 ? round(($prevKasserade / $prevTotalIbc) * 100, 2) : 0;

            // OEE foregaende period
            $prevOeeValues = [];
            $prevDagar = $this->getWorkingDays($prevFrom, $prevTo);
            foreach ($prevDagar as $dag) {
                $oee = $this->calcOeeForDay($dag);
                if ($oee['total_ibc'] > 0) {
                    $prevOeeValues[] = $oee['oee'];
                }
            }
            $prevSnittOee = count($prevOeeValues) > 0 ? round((array_sum($prevOeeValues) / count($prevOeeValues)) * 100, 1) : 0;

            // Trend
            $produktionTrend = $prevTotalIbc > 0 ? round((($totalIbc - $prevTotalIbc) / $prevTotalIbc) * 100, 1) : 0;
            $oeeTrend = round($snittOee - $prevSnittOee, 1);
            $kassationTrend = round($kassationsrate - $prevKassationsrate, 2);

            $this->sendSuccess([
                'total_produktion'    => $totalIbc,
                'snitt_oee'           => $snittOee,
                'kassationsrate'      => $kassationsrate,
                'produktion_trend'    => $produktionTrend,
                'oee_trend'           => $oeeTrend,
                'kassation_trend'     => $kassationTrend,
                'prev_total'          => $prevTotalIbc,
                'prev_oee'            => $prevSnittOee,
                'prev_kassationsrate' => $prevKassationsrate,
            ]);
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::getKpi: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta KPI-data', 500);
        }
    }

    // ================================================================
    // run=produktion — IBC per vecka
    // ================================================================

    private function getProduktion(): void {
        $months = $this->getMonths();
        $fromDate = date('Y-m-d', strtotime("-{$months} months"));
        $toDate = date('Y-m-d');

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    yearweek,
                    MIN(dag) AS week_start,
                    COALESCE(SUM(max_ibc_ok + max_ibc_ej_ok), 0) AS total_ibc
                FROM (
                    SELECT
                        YEARWEEK(datum, 1) AS yearweek,
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY YEARWEEK(datum, 1), DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY yearweek
                ORDER BY yearweek ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $weekNum = (int)substr($row['yearweek'], -2);
                $labels[] = 'V' . $weekNum;
                $values[] = (int)$row['total_ibc'];
            }

            $this->sendSuccess([
                'months'    => $months,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'labels'    => $labels,
                'values'    => $values,
            ]);
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::getProduktion: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta produktionsdata', 500);
        }
    }

    // ================================================================
    // run=oee — OEE% per vecka
    // ================================================================

    private function getOee(): void {
        $months = $this->getMonths();
        $fromDate = date('Y-m-d', strtotime("-{$months} months"));
        $toDate = date('Y-m-d');

        try {
            // Hamta alla dagar i perioden med OEE
            $dagar = $this->getWorkingDays($fromDate, $toDate);
            $weeklyOee = [];

            foreach ($dagar as $dag) {
                $yw = date('oW', strtotime($dag));
                $weekNum = (int)date('W', strtotime($dag));
                if (!isset($weeklyOee[$yw])) {
                    $weeklyOee[$yw] = ['weekNum' => $weekNum, 'oeeValues' => []];
                }
                $oee = $this->calcOeeForDay($dag);
                if ($oee['total_ibc'] > 0) {
                    $weeklyOee[$yw]['oeeValues'][] = $oee['oee'];
                }
            }

            ksort($weeklyOee);
            $labels = [];
            $values = [];
            foreach ($weeklyOee as $yw => $data) {
                $labels[] = 'V' . $data['weekNum'];
                if (count($data['oeeValues']) > 0) {
                    $values[] = round((array_sum($data['oeeValues']) / count($data['oeeValues'])) * 100, 1);
                } else {
                    $values[] = null;
                }
            }

            $this->sendSuccess([
                'months'    => $months,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'labels'    => $labels,
                'values'    => $values,
                'mal'       => 65, // OEE-mal i procent
            ]);
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::getOee: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta OEE-data', 500);
        }
    }

    // ================================================================
    // run=kassation — Kassationsrate% per vecka
    // ================================================================

    private function getKassation(): void {
        $months = $this->getMonths();
        $fromDate = date('Y-m-d', strtotime("-{$months} months"));
        $toDate = date('Y-m-d');

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    yearweek,
                    SUM(max_ibc_ok) AS ok_total,
                    SUM(max_ibc_ej_ok) AS kasserade
                FROM (
                    SELECT
                        YEARWEEK(datum, 1) AS yearweek,
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY YEARWEEK(datum, 1), DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY yearweek
                ORDER BY yearweek ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $weekNum = (int)substr($row['yearweek'], -2);
                $labels[] = 'V' . $weekNum;
                $ok = (int)($row['ok_total'] ?? 0);
                $kass = (int)($row['kasserade'] ?? 0);
                $total = $ok + $kass;
                $values[] = $total > 0 ? round(($kass / $total) * 100, 2) : 0;
            }

            $this->sendSuccess([
                'months'    => $months,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'labels'    => $labels,
                'values'    => $values,
                'troskel'   => 3, // Kassations-troskel i procent
            ]);
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::getKassation: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta kassationsdata', 500);
        }
    }

    // ================================================================
    // PRIVATE HELPERS
    // ================================================================

    private function getWorkingDays(string $from, string $to): array {
        $days = [];
        $d = new \DateTime($from);
        $end = new \DateTime($to);
        while ($d <= $end) {
            $days[] = $d->format('Y-m-d');
            $d->modify('+1 day');
        }
        return $days;
    }

    private function calcOeeForDay(string $date): array {
        $from = $date . ' 00:00:00';
        $to   = $date . ' 23:59:59';

        // rebotling_onoff: datum (DATETIME), running (BOOLEAN)
        $drifttidSek = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum <= :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prevTime = null;
            $prevRunning = null;
            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];
                if ($prevTime !== null && $prevRunning === 1) {
                    $drifttidSek += ($ts - $prevTime);
                }
                $prevTime = $ts;
                $prevRunning = $running;
            }
            $drifttidSek = max(0, $drifttidSek);
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::calcOeeForDay onoff: ' . $e->getMessage());
        }

        $schemaSek = self::SCHEMA_SEK_PER_DAG;
        $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;

        // rebotling_ibc: MAX per skiftraknare, then SUM
        $totalIbc = 0;
        $okIbc = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_ibc
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                    GROUP BY skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':date' => $date]);
            $ibcRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $okIbc    = (int)($ibcRow['ok_ibc'] ?? 0);
            $totalIbc = $okIbc + (int)($ibcRow['ej_ok_ibc'] ?? 0);
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::calcOeeForDay ibc: ' . $e->getMessage());
        }

        $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
        $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'oee'       => round($oee, 4),
            'total_ibc' => $totalIbc,
            'ok_ibc'    => $okIbc,
        ];
    }
}
