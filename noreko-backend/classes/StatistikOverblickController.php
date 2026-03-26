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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
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
                               MAX(COALESCE(ibc_ok, 0)) AS max_ibc_ok, MAX(COALESCE(ibc_ej_ok, 0)) AS max_ibc_ej_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
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

            // OEE snitt senaste 30 dagar (batch — 2 queries istallet for ~60)
            $oeeValues = [];
            $oeeBatch = $this->calcOeeBatch($from30, $today);
            foreach ($oeeBatch as $dag => $oee) {
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
                               MAX(COALESCE(ibc_ok, 0)) AS max_ibc_ok, MAX(COALESCE(ibc_ej_ok, 0)) AS max_ibc_ej_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
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

            // OEE foregaende period (batch — 2 queries istallet for ~60)
            $prevOeeValues = [];
            $prevOeeBatch = $this->calcOeeBatch($prevFrom, $prevTo);
            foreach ($prevOeeBatch as $dag => $oee) {
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
        // Bugfix: strtotime('-N months') ger fel datum pa manad-slut (t.ex. 31 mars - 1 manad = 3 mars).
        // Anvand DateTime med 'first day of' for korrekt manad-aritmetik.
        $fromDt = new \DateTime('first day of this month');
        $fromDt->modify("-{$months} months");
        $fromDate = $fromDt->format('Y-m-d');
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
                        MAX(COALESCE(ibc_ok, 0)) AS max_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
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
        // Bugfix: strtotime('-N months') ger fel datum pa manad-slut (t.ex. 31 mars - 1 manad = 3 mars).
        $fromDt = new \DateTime('first day of this month');
        $fromDt->modify("-{$months} months");
        $fromDate = $fromDt->format('Y-m-d');
        $toDate = date('Y-m-d');

        try {
            // Hamta alla dagar i perioden med OEE (batch — 2 queries totalt)
            $dagar = $this->getWorkingDays($fromDate, $toDate);
            $oeeBatch = $this->calcOeeBatch($fromDate, $toDate);
            $weeklyOee = [];

            foreach ($dagar as $dag) {
                $yw = date('oW', strtotime($dag));
                $weekNum = (int)date('W', strtotime($dag));
                if (!isset($weeklyOee[$yw])) {
                    $weeklyOee[$yw] = ['weekNum' => $weekNum, 'oeeValues' => []];
                }
                $oee = $oeeBatch[$dag] ?? ['oee' => 0, 'total_ibc' => 0, 'ok_ibc' => 0];
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
        // Bugfix: strtotime('-N months') ger fel datum pa manad-slut (t.ex. 31 mars - 1 manad = 3 mars).
        $fromDt = new \DateTime('first day of this month');
        $fromDt->modify("-{$months} months");
        $fromDate = $fromDt->format('Y-m-d');
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
                        MAX(COALESCE(ibc_ok, 0)) AS max_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
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

    /**
     * Batch-berakna OEE for alla dagar i en period (2 queries totalt istallet for 2 per dag).
     * Returnerar [datum => ['oee' => X, 'total_ibc' => Y, 'ok_ibc' => Z]]
     */
    private function calcOeeBatch(string $fromDate, string $toDate): array {
        $schemaSek = self::SCHEMA_SEK_PER_DAG;
        $result = [];

        // 1) Hamta ALL onoff-data for perioden i en enda query
        $drifttidPerDag = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum < DATE_ADD(:to_dt, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDate . ' 00:00:00', ':to_dt' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Gruppera per dag och berakna drifttid
            $dayRows = [];
            foreach ($rows as $row) {
                $dayRows[$row['dag']][] = $row;
            }

            foreach ($dayRows as $dag => $dagRader) {
                $driftSek = 0;
                $prevTime = null;
                $prevRunning = null;
                foreach ($dagRader as $row) {
                    $ts = strtotime($row['datum']);
                    $running = (int)$row['running'];
                    if ($prevTime !== null && $prevRunning === 1) {
                        $driftSek += ($ts - $prevTime);
                    }
                    $prevTime = $ts;
                    $prevRunning = $running;
                }
                $drifttidPerDag[$dag] = max(0, $driftSek);
            }
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::calcOeeBatch onoff: ' . $e->getMessage());
        }

        // 2) Hamta IBC per dag i en enda query
        $ibcPerDag = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT dag,
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_ibc
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS max_ibc_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ibcPerDag[$row['dag']] = [
                    'ok_ibc'    => (int)$row['ok_ibc'],
                    'ej_ok_ibc' => (int)$row['ej_ok_ibc'],
                ];
            }
        } catch (\Exception $e) {
            error_log('StatistikOverblickController::calcOeeBatch ibc: ' . $e->getMessage());
        }

        // 3) Berakna OEE per dag
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        while ($d <= $end) {
            $dag = $d->format('Y-m-d');
            $drifttidSek = $drifttidPerDag[$dag] ?? 0;
            $okIbc    = $ibcPerDag[$dag]['ok_ibc'] ?? 0;
            $ejOkIbc  = $ibcPerDag[$dag]['ej_ok_ibc'] ?? 0;
            $totalIbc = $okIbc + $ejOkIbc;

            $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;
            $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
            $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
            $oee = $tillganglighet * $prestanda * $kvalitet;

            $result[$dag] = [
                'oee'       => round($oee, 4),
                'total_ibc' => $totalIbc,
                'ok_ibc'    => $okIbc,
            ];
            $d->modify('+1 day');
        }

        return $result;
    }

    /**
     * Berakna OEE for en enskild dag (wrapper kring calcOeeBatch for bakatkompat).
     */
    private function calcOeeForDay(string $date): array {
        $batch = $this->calcOeeBatch($date, $date);
        return $batch[$date] ?? ['oee' => 0, 'total_ibc' => 0, 'ok_ibc' => 0];
    }
}
