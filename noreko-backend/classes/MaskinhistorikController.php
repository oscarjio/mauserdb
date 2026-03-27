<?php
/**
 * MaskinhistorikController.php
 * Maskinhistorik — detaljerad vy av rebotling-linjen over tid.
 *
 * Endpoints via ?action=maskinhistorik&run=XXX:
 *   - run=stationer           -> (tom lista — rebotling_ibc har ingen station-kolumn)
 *   - run=station-kpi         -> KPI:er for rebotling-linjen for period (?period=30)
 *   - run=station-drifttid    -> daglig drifttid (?period=30)
 *   - run=station-oee-trend   -> daglig OEE med delkomponenter (?period=30)
 *   - run=station-stopp       -> senaste driftstatus fran rebotling_onoff (?limit=20)
 *   - run=jamforelse          -> KPI-oversikt (?period=30)
 *
 * OEE = Tillganglighet x Prestanda x Kvalitet
 *   Tillganglighet = drifttid / planerad tid (8h/dag)
 *   Prestanda      = (antalIBC * IDEAL_CYCLE_SEC) / drifttid (max 100%)
 *   Kvalitet       = ibc_ok / (ibc_ok + ibc_ej_ok)
 *
 * rebotling_ibc columns: datum, lopnummer, skiftraknare, ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, rasttime, op1, op2, op3
 * rebotling_onoff columns: datum, running
 */
class MaskinhistorikController {
    private $pdo;

    private const IDEAL_CYCLE_SEC  = 120;      // sekunder per IBC (ideal)
    private const SCHEMA_SEK_PER_DAG = 8 * 3600; // 8 timmars skift

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
            case 'stationer':         $this->getStationer();       break;
            case 'station-kpi':       $this->getStationKpi();      break;
            case 'station-drifttid':  $this->getStationDrifttid(); break;
            case 'station-oee-trend': $this->getStationOeeTrend(); break;
            case 'station-stopp':     $this->getStationStopp();    break;
            case 'jamforelse':        $this->getJamforelse();      break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // Helpers
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
     * Returnerar antal arbetsdagar (man-fre) i ett intervall.
     */
    private function raknaDagar(string $fromDate, string $toDate): int {
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $dagar = 0;
        while ($d <= $end) {
            if ((int)$d->format('N') <= 5) $dagar++;
            $d->modify('+1 day');
        }
        return $dagar;
    }

    /**
     * Beraknar drifttid (sek) fran rebotling_onoff for ett datumintervall.
     * rebotling_onoff has columns: datum (DATETIME), running (BOOLEAN).
     * Drifttid = sum of intervals where running=1.
     */
    private function getDrifttidSek(string $fromDt, string $toDt): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum < :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $drifttid = 0;
            $prevTime = null;
            $prevRunning = null;

            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];

                if ($prevTime !== null && $prevRunning === 1) {
                    $drifttid += ($ts - $prevTime);
                }

                $prevTime = $ts;
                $prevRunning = $running;
            }

            return max(0, $drifttid);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getDrifttidSek: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Beraknar drifttid (sek) per dag fran rebotling_onoff for ett datumintervall.
     * Returnerar associativt array: [datum => drifttid_sek]
     * En enda query istallet for en per dag (N+1 fix).
     */
    private function getDrifttidPerDag(string $fromDt, string $toDt): array {
        $result = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum < :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prevTime = null;
            $prevRunning = null;

            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];

                if ($prevTime !== null && $prevRunning === 1) {
                    // Berakna drifttid och tilldela till korrekt dag
                    // Om drifttiden spanner over midnatt, dela upp per dag
                    $intervalStart = $prevTime;
                    $intervalEnd   = $ts;
                    while ($intervalStart < $intervalEnd) {
                        $dagStr = date('Y-m-d', $intervalStart);
                        $nextMidnight = strtotime(date('Y-m-d', $intervalStart) . ' +1 day');
                        $segmentEnd = min($intervalEnd, $nextMidnight);
                        $segmentSek = $segmentEnd - $intervalStart;
                        if (!isset($result[$dagStr])) $result[$dagStr] = 0;
                        $result[$dagStr] += $segmentSek;
                        $intervalStart = $segmentEnd;
                    }
                }

                $prevTime = $ts;
                $prevRunning = $running;
            }
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getDrifttidPerDag: ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Hamta IBC-data for ett datumintervall.
     * Uses MAX(ibc_ok) per skiftraknare then SUM over skift.
     */
    private function getIbcData(string $fromDate, string $toDate): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS total_ok,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS total_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $ok = (int)($row['total_ok'] ?? 0);
            $ejOk = (int)($row['total_ej_ok'] ?? 0);
            return ['ok' => $ok, 'ej_ok' => $ejOk, 'total' => $ok + $ejOk];
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getIbcData: ' . $e->getMessage());
            return ['ok' => 0, 'ej_ok' => 0, 'total' => 0];
        }
    }

    /**
     * Beraknar OEE-komponenter for ett datumintervall.
     */
    private function calcOee(string $fromDate, string $toDate): array {
        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = date('Y-m-d', strtotime($toDate . ' +1 day')) . ' 00:00:00';

        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        $arbetsdagar = $this->raknaDagar($fromDate, $toDate);
        $planeradSek = $arbetsdagar * self::SCHEMA_SEK_PER_DAG;

        $ibcData = $this->getIbcData($fromDate, $toDate);
        $total   = $ibcData['total'];
        $okAntal = $ibcData['ok'];

        $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0.0;
        $prestanda = $drifttidSek > 0
            ? min(1.0, ($total * self::IDEAL_CYCLE_SEC) / $drifttidSek)
            : 0.0;
        $kvalitet = $total > 0 ? ($okAntal / $total) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'oee_pct'            => round($oee * 100, 1),
            'tillganglighet_pct' => round($tillganglighet * 100, 1),
            'prestanda_pct'      => round($prestanda * 100, 1),
            'kvalitet_pct'       => round($kvalitet * 100, 1),
            'drifttid_h'         => round($drifttidSek / 3600, 2),
            'drifttid_sek'       => $drifttidSek,
            'planerad_h'         => round($planeradSek / 3600, 1),
            'total_ibc'          => $total,
            'ok_ibc'             => $okAntal,
            'kasserade_ibc'      => $total - $okAntal,
            'kassationsgrad_pct' => $total > 0 ? round(($total - $okAntal) / $total * 100, 1) : 0.0,
            'avg_cykeltid_sek'   => $total > 0 ? round($drifttidSek / $total, 1) : 0,
            'arbetsdagar'        => $arbetsdagar,
        ];
    }

    // ================================================================
    // run=stationer — lista unika stationer
    // ================================================================

    private function getStationer(): void {
        // rebotling_ibc has no station column — return generic line entry
        $this->sendSuccess(['stationer' => ['Rebotling']]);
    }

    // ================================================================
    // run=station-kpi — KPI:er for linjen
    // ================================================================

    private function getStationKpi(): void {
        $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        $oee = $this->calcOee($fromDate, $toDate);

        $this->sendSuccess([
            'station'            => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
            'period_dagar'       => $period,
            'from_date'          => $fromDate,
            'to_date'            => $toDate,
            'oee_pct'            => $oee['oee_pct'],
            'tillganglighet_pct' => $oee['tillganglighet_pct'],
            'prestanda_pct'      => $oee['prestanda_pct'],
            'kvalitet_pct'       => $oee['kvalitet_pct'],
            'drifttid_h'         => $oee['drifttid_h'],
            'planerad_h'         => $oee['planerad_h'],
            'total_ibc'          => $oee['total_ibc'],
            'ok_ibc'             => $oee['ok_ibc'],
            'kasserade_ibc'      => $oee['kasserade_ibc'],
            'kassationsgrad_pct' => $oee['kassationsgrad_pct'],
            'avg_cykeltid_sek'   => $oee['avg_cykeltid_sek'],
            'arbetsdagar'        => $oee['arbetsdagar'],
        ]);
    }

    // ================================================================
    // run=station-drifttid — daglig drifttid
    // ================================================================

    private function getStationDrifttid(): void {
        $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));

        $toDate = new \DateTime();
        $fromDateStr = (clone $toDate)->modify("-" . ($period - 1) . " days")->format('Y-m-d');
        $toDateStr   = $toDate->format('Y-m-d');

        // Batch-hamta IBC-data for hela perioden (1 query istallet for N)
        $ibcPerDag = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    COALESCE(SUM(max_ibc_ok), 0) AS total_ok,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS total_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS datum_dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDateStr, ':to_date' => $toDateStr]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ok = (int)($row['total_ok'] ?? 0);
                $ejOk = (int)($row['total_ej_ok'] ?? 0);
                $ibcPerDag[$row['dag']] = ['ok' => $ok, 'ej_ok' => $ejOk, 'total' => $ok + $ejOk];
            }
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getStationDrifttid (batch ibc): ' . $e->getMessage());
        }

        // Batch-hamta drifttid for hela perioden (1 query istallet for N)
        $drifttidPerDag = $this->getDrifttidPerDag($fromDateStr . ' 00:00:00', date('Y-m-d', strtotime($toDateStr . ' +1 day')) . ' 00:00:00');

        $result = [];
        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $toDate;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $drifttidSek = $drifttidPerDag[$dagStr] ?? 0;
            $ibcData = $ibcPerDag[$dagStr] ?? ['ok' => 0, 'ej_ok' => 0, 'total' => 0];

            $result[] = [
                'datum'        => $dagStr,
                'drifttid_h'   => round($drifttidSek / 3600, 2),
                'total_ibc'    => $ibcData['total'],
            ];
        }

        $this->sendSuccess([
            'station'     => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
            'period_dagar'=> $period,
            'dagdata'     => $result,
        ]);
    }

    // ================================================================
    // run=station-oee-trend — daglig OEE med delkomponenter
    // ================================================================

    private function getStationOeeTrend(): void {
        $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));

        $toDate = new \DateTime();
        $fromDateStr = (clone $toDate)->modify("-" . ($period - 1) . " days")->format('Y-m-d');
        $toDateStr   = $toDate->format('Y-m-d');

        // Batch-hamta all data i 2 queries istallet for 3*N (N+1 fix)
        // 1. Drifttid per dag
        $drifttidPerDag = $this->getDrifttidPerDag($fromDateStr . ' 00:00:00', date('Y-m-d', strtotime($toDateStr . ' +1 day')) . ' 00:00:00');

        // 2. IBC-data per dag
        $ibcPerDag = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    COALESCE(SUM(max_ibc_ok), 0) AS total_ok,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS total_ej_ok
                FROM (
                    SELECT DATE(datum) AS datum_dag, skiftraknare,
                           MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
            ");
            $stmt->execute([':from_date' => $fromDateStr, ':to_date' => $toDateStr]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ok = (int)($row['total_ok'] ?? 0);
                $ejOk = (int)($row['total_ej_ok'] ?? 0);
                $ibcPerDag[$row['dag']] = ['ok' => $ok, 'ej_ok' => $ejOk, 'total' => $ok + $ejOk];
            }
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getStationOeeTrend (batch ibc): ' . $e->getMessage());
        }

        $result = [];
        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $toDate;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            // Berakna OEE med batch-data istallet for per-dag queries
            $drifttidSek = $drifttidPerDag[$dagStr] ?? 0;
            $ibcData     = $ibcPerDag[$dagStr] ?? ['ok' => 0, 'ej_ok' => 0, 'total' => 0];
            $isWeekday   = ((int)(new \DateTime($dagStr))->format('N')) <= 5;
            $planeradSek = $isWeekday ? self::SCHEMA_SEK_PER_DAG : 0;

            $total   = $ibcData['total'];
            $okAntal = $ibcData['ok'];

            $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0.0;
            $prestanda = $drifttidSek > 0
                ? min(1.0, ($total * self::IDEAL_CYCLE_SEC) / $drifttidSek)
                : 0.0;
            $kvalitet = $total > 0 ? ($okAntal / $total) : 0.0;
            $oee = $tillganglighet * $prestanda * $kvalitet;

            $result[] = [
                'datum'              => $dagStr,
                'oee_pct'            => round($oee * 100, 1),
                'tillganglighet_pct' => round($tillganglighet * 100, 1),
                'prestanda_pct'      => round($prestanda * 100, 1),
                'kvalitet_pct'       => round($kvalitet * 100, 1),
                'total_ibc'          => $total,
            ];
        }

        $this->sendSuccess([
            'station'      => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
            'period_dagar' => $period,
            'dagdata'      => $result,
        ]);
    }

    // ================================================================
    // run=station-stopp — senaste driftstatus fran rebotling_onoff
    // ================================================================

    private function getStationStopp(): void {
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));

        // rebotling_onoff has datum + running columns — show recent status changes
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running
                FROM rebotling_onoff
                ORDER BY datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stoppData = [];
            foreach ($rows as $rad) {
                $stoppData[] = [
                    'datum'   => $rad['datum'],
                    'running' => (bool)$rad['running'],
                    'status'  => (int)$rad['running'] ? 'Drift' : 'Stopp',
                ];
            }

            $this->sendSuccess([
                'stopp'  => $stoppData,
                'antal'  => count($stoppData),
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getStationStopp: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stopphistorik', 500);
        }
    }

    // ================================================================
    // run=jamforelse — oversikt
    // ================================================================

    private function getJamforelse(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        $oee = $this->calcOee($fromDate, $toDate);

        $jamforelse = [
            [
                'station'            => 'Rebotling',
                'oee_pct'            => $oee['oee_pct'],
                'tillganglighet_pct' => $oee['tillganglighet_pct'],
                'prestanda_pct'      => $oee['prestanda_pct'],
                'kvalitet_pct'       => $oee['kvalitet_pct'],
                'drifttid_h'         => $oee['drifttid_h'],
                'total_ibc'          => $oee['total_ibc'],
                'kasserade_ibc'      => $oee['kasserade_ibc'],
                'kassationsgrad_pct' => $oee['kassationsgrad_pct'],
                'avg_cykeltid_sek'   => $oee['avg_cykeltid_sek'],
                'rang'               => 'normal',
            ],
        ];

        $this->sendSuccess([
            'period_dagar' => $period,
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
            'jamforelse'   => $jamforelse,
            'antal'        => 1,
        ]);
    }
}
