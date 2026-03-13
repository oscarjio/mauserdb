<?php
/**
 * MaskinhistorikController.php
 * Maskinhistorik per station — detaljerad vy per maskin/station i rebotling-linjen.
 *
 * Endpoints via ?action=maskinhistorik&run=XXX:
 *   - run=stationer           -> lista unika stationer fran rebotling_ibc
 *   - run=station-kpi         -> KPI:er for vald station + period (?station=X&period=30)
 *   - run=station-drifttid    -> daglig drifttid for vald station (?station=X&period=30)
 *   - run=station-oee-trend   -> daglig OEE med delkomponenter (?station=X&period=30)
 *   - run=station-stopp       -> senaste stopp fran rebotling_onoff (?station=X&limit=20)
 *   - run=jamforelse          -> alla stationer jamforda (?period=30)
 *
 * OEE = Tillganglighet x Prestanda x Kvalitet
 *   Tillganglighet = drifttid / planerad tid (8h/dag)
 *   Prestanda      = (antalIBC * IDEAL_CYCLE_SEC) / drifttid (max 100%)
 *   Kvalitet       = godkanda / totalt (ok=1 i rebotling_ibc)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff — INGA nya tabeller
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
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
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
     * station-parametern ignoreras — onoff-tabellen har ingen station-kolumn.
     */
    private function getDrifttidSek(string $fromDt, string $toDt): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(
                    TIMESTAMPDIFF(SECOND,
                        GREATEST(start_time, :from1),
                        LEAST(COALESCE(stop_time, NOW()), :to1)
                    )
                ), 0) AS drifttid_sek
                FROM rebotling_onoff
                WHERE start_time < :to2
                  AND (stop_time IS NULL OR stop_time > :from2)
                  AND TIMESTAMPDIFF(SECOND,
                        GREATEST(start_time, :from3),
                        LEAST(COALESCE(stop_time, NOW()), :to3)
                      ) > 0
            ");
            $stmt->execute([
                ':from1' => $fromDt, ':to1' => $toDt,
                ':from2' => $fromDt, ':to2' => $toDt,
                ':from3' => $fromDt, ':to3' => $toDt,
            ]);
            return max(0, (int)($stmt->fetchColumn() ?? 0));
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getDrifttidSek: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Beraknar OEE-komponenter for en station och ett datumintervall.
     */
    private function calcOee(string $station, string $fromDate, string $toDate): array {
        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = $toDate   . ' 23:59:59';

        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        $arbetsdagar = $this->raknaDagar($fromDate, $toDate);
        $planeradSek = $arbetsdagar * self::SCHEMA_SEK_PER_DAG;

        // IBC-data for stationen
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_antal,
                    AVG(TIMESTAMPDIFF(SECOND, LAG(datum) OVER (ORDER BY datum), datum)) AS avg_cykeltid_sek
                FROM rebotling_ibc
                WHERE station = :station
                  AND DATE(datum) BETWEEN :from_date AND :to_date
            ");
            $stmt->execute([
                ':station'   => $station,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $row     = $stmt->fetch(\PDO::FETCH_ASSOC);
            $total   = (int)($row['total']    ?? 0);
            $okAntal = (int)($row['ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::calcOee ibc: ' . $e->getMessage());
            $total   = 0;
            $okAntal = 0;
        }

        // Genomsnittlig cykeltid for stationen
        try {
            $ctStmt = $this->pdo->prepare("
                SELECT AVG(diff_sek) AS avg_sek FROM (
                    SELECT TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY station ORDER BY datum),
                        datum
                    ) AS diff_sek
                    FROM rebotling_ibc
                    WHERE station = :station
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                ) t
                WHERE diff_sek > 0 AND diff_sek < 3600
            ");
            $ctStmt->execute([
                ':station'   => $station,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $avgCykeltid = (float)($ctStmt->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::calcOee cykeltid: ' . $e->getMessage());
            $avgCykeltid = 0;
        }

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
            'avg_cykeltid_sek'   => round($avgCykeltid, 1),
            'arbetsdagar'        => $arbetsdagar,
        ];
    }

    // ================================================================
    // run=stationer — lista unika stationer
    // ================================================================

    private function getStationer(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT station
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                ORDER BY station
            ");
            $stationer = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $this->sendSuccess(['stationer' => $stationer]);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getStationer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationer');
        }
    }

    // ================================================================
    // run=station-kpi — KPI:er for vald station
    // ================================================================

    private function getStationKpi(): void {
        $station = trim($_GET['station'] ?? '');
        $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));

        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        $oee = $this->calcOee($station, $fromDate, $toDate);

        $this->sendSuccess([
            'station'            => $station,
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
    // run=station-drifttid — daglig drifttid for vald station
    // ================================================================

    private function getStationDrifttid(): void {
        $station = trim($_GET['station'] ?? '');
        $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));

        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        $toDate = new \DateTime();
        $result = [];

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $toDate;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = $dagStr . ' 23:59:59';

            $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

            // IBC for denna dag och station
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM rebotling_ibc
                    WHERE station = :station AND DATE(datum) = :dag
                ");
                $stmt->execute([':station' => $station, ':dag' => $dagStr]);
                $totalIbc = (int)($stmt->fetchColumn() ?? 0);
            } catch (\PDOException) {
                $totalIbc = 0;
            }

            $result[] = [
                'datum'        => $dagStr,
                'drifttid_h'   => round($drifttidSek / 3600, 2),
                'total_ibc'    => $totalIbc,
            ];
        }

        $this->sendSuccess([
            'station'     => $station,
            'period_dagar'=> $period,
            'dagdata'     => $result,
        ]);
    }

    // ================================================================
    // run=station-oee-trend — daglig OEE med delkomponenter
    // ================================================================

    private function getStationOeeTrend(): void {
        $station = trim($_GET['station'] ?? '');
        $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));

        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        $toDate = new \DateTime();
        $result = [];

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $toDate;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $oee = $this->calcOee($station, $dagStr, $dagStr);

            $result[] = [
                'datum'              => $dagStr,
                'oee_pct'            => $oee['oee_pct'],
                'tillganglighet_pct' => $oee['tillganglighet_pct'],
                'prestanda_pct'      => $oee['prestanda_pct'],
                'kvalitet_pct'       => $oee['kvalitet_pct'],
                'total_ibc'          => $oee['total_ibc'],
            ];
        }

        $this->sendSuccess([
            'station'      => $station,
            'period_dagar' => $period,
            'dagdata'      => $result,
        ]);
    }

    // ================================================================
    // run=station-stopp — senaste stopp fran rebotling_onoff
    // ================================================================

    private function getStationStopp(): void {
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));

        // rebotling_onoff har ingen station-kolumn — visar generella stopp
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    start_time,
                    stop_time,
                    CASE
                        WHEN stop_time IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, start_time, stop_time)
                        ELSE TIMESTAMPDIFF(SECOND, start_time, NOW())
                    END AS varaktighet_sek,
                    CASE
                        WHEN stop_time IS NULL THEN 'Pagaende'
                        ELSE 'Avslutat'
                    END AS status
                FROM rebotling_onoff
                ORDER BY start_time DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $stopp = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Berakna drifttid (tid maskinen gick) mellan stoppen
            $stoppData = [];
            foreach ($stopp as $rad) {
                $stoppData[] = [
                    'id'             => (int)$rad['id'],
                    'start_time'     => $rad['start_time'],
                    'stop_time'      => $rad['stop_time'],
                    'varaktighet_sek'=> (int)($rad['varaktighet_sek'] ?? 0),
                    'varaktighet_min'=> round((int)($rad['varaktighet_sek'] ?? 0) / 60, 1),
                    'status'         => $rad['status'],
                ];
            }

            $this->sendSuccess([
                'stopp'  => $stoppData,
                'antal'  => count($stoppData),
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getStationStopp: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stopphistorik');
        }
    }

    // ================================================================
    // run=jamforelse — alla stationer jamforda
    // ================================================================

    private function getJamforelse(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        // Hamta alla stationer
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT station
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                ORDER BY station
            ");
            $stationer = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            error_log('MaskinhistorikController::getJamforelse stationer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationer');
            return;
        }

        $jamforelse = [];
        foreach ($stationer as $station) {
            $oee = $this->calcOee($station, $fromDate, $toDate);
            $jamforelse[] = [
                'station'            => $station,
                'oee_pct'            => $oee['oee_pct'],
                'tillganglighet_pct' => $oee['tillganglighet_pct'],
                'prestanda_pct'      => $oee['prestanda_pct'],
                'kvalitet_pct'       => $oee['kvalitet_pct'],
                'drifttid_h'         => $oee['drifttid_h'],
                'total_ibc'          => $oee['total_ibc'],
                'kasserade_ibc'      => $oee['kasserade_ibc'],
                'kassationsgrad_pct' => $oee['kassationsgrad_pct'],
                'avg_cykeltid_sek'   => $oee['avg_cykeltid_sek'],
            ];
        }

        // Sortera efter OEE (hogst forst)
        usort($jamforelse, fn($a, $b) => $b['oee_pct'] <=> $a['oee_pct']);

        // Markera bast och samst
        $count = count($jamforelse);
        if ($count > 0) {
            $jamforelse[0]['rang']          = 'bast';
            $jamforelse[$count - 1]['rang'] = 'samst';
        }
        for ($i = 1; $i < $count - 1; $i++) {
            $jamforelse[$i]['rang'] = 'normal';
        }

        $this->sendSuccess([
            'period_dagar' => $period,
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
            'jamforelse'   => $jamforelse,
            'antal'        => $count,
        ]);
    }
}
