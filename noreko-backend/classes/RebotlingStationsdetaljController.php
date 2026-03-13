<?php
/**
 * RebotlingStationsdetaljController.php
 * Stationsdetalj-dashboard — drill-down per rebotling-station för VD-vy.
 *
 * Endpoints via ?action=rebotling-stationsdetalj&run=XXX:
 *   - run=stationer           -> lista unika stationer
 *   - run=kpi-idag            -> KPI-kort för idag: OEE, drifttid%, antal IBC, snittcykeltid (?station=X)
 *   - run=senaste-ibc         -> senaste IBCer med tidsstämpel, resultat, cykeltid (?station=X&limit=N)
 *   - run=stopphistorik       -> stopphistorik per station (?station=X&limit=N)
 *   - run=oee-trend           -> OEE per dag senaste 30 dagar per station (?station=X&dagar=30)
 *   - run=realtid-oee         -> realtids-OEE för vald station (senaste timmen) (?station=X)
 *
 * OEE = Tillgänglighet × Prestanda × Kvalitet
 *   Tillgänglighet = drifttid / planerad tid (8h/dag)
 *   Prestanda      = (antal_IBC × IDEAL_CYCLE_SEC) / drifttid (max 100%)
 *   Kvalitet       = godkända / totalt
 *
 * Tabeller: rebotling_ibc, rebotling_onoff — INGA nya tabeller skapas
 */
class RebotlingStationsdetaljController {
    private $pdo;

    private const IDEAL_CYCLE_SEC    = 120;      // sekunder per IBC (ideal)
    private const SCHEMA_SEK_PER_DAG = 8 * 3600; // 8 timmars skift per dag

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
            case 'stationer':     $this->getStationer();     break;
            case 'kpi-idag':      $this->getKpiIdag();       break;
            case 'senaste-ibc':   $this->getSenasteIbc();    break;
            case 'stopphistorik': $this->getStopphistorik(); break;
            case 'oee-trend':     $this->getOeeTrend();      break;
            case 'realtid-oee':   $this->getRealtidOee();    break;
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
     * Hämtar total drifttid (sek) från rebotling_onoff för ett givet tidsintervall.
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
            error_log('RebotlingStationsdetaljController::getDrifttidSek: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Beräknar OEE-komponenter för en station och ett datumintervall.
     */
    private function calcOee(string $station, string $fromDt, string $toDt): array {
        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        // Beräkna planerad tid (antal sekunder från fromDt till toDt, max 8h per dag)
        $fromDate = substr($fromDt, 0, 10);
        $toDate   = substr($toDt,   0, 10);
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $dagar = 0;
        while ($d <= $end) {
            if ((int)$d->format('N') <= 5) $dagar++; // mån-fre
            $d->modify('+1 day');
        }
        // För intra-dag räknas faktisk tid om fromDt och toDt är samma dag
        if ($fromDate === $toDate) {
            $planeradSek = self::SCHEMA_SEK_PER_DAG;
        } else {
            $planeradSek = max(1, $dagar) * self::SCHEMA_SEK_PER_DAG;
        }

        // IBC-data för stationen
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_antal
                FROM rebotling_ibc
                WHERE station = :station
                  AND datum BETWEEN :from_dt AND :to_dt
            ");
            $stmt->execute([':station' => $station, ':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $row     = $stmt->fetch(\PDO::FETCH_ASSOC);
            $total   = (int)($row['total']    ?? 0);
            $okAntal = (int)($row['ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::calcOee ibc: ' . $e->getMessage());
            $total   = 0;
            $okAntal = 0;
        }

        // Genomsnittlig cykeltid
        try {
            $ctStmt = $this->pdo->prepare("
                SELECT AVG(diff_sek) AS avg_sek FROM (
                    SELECT TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY station ORDER BY datum),
                        datum
                    ) AS diff_sek
                    FROM rebotling_ibc
                    WHERE station = :station
                      AND datum BETWEEN :from_dt AND :to_dt
                ) t
                WHERE diff_sek > 0 AND diff_sek < 3600
            ");
            $ctStmt->execute([':station' => $station, ':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $avgCykeltid = (float)($ctStmt->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::calcOee cykeltid: ' . $e->getMessage());
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
            'drifttid_sek'       => $drifttidSek,
            'drifttid_h'         => round($drifttidSek / 3600, 2),
            'planerad_h'         => round($planeradSek / 3600, 1),
            'total_ibc'          => $total,
            'ok_ibc'             => $okAntal,
            'kasserade_ibc'      => $total - $okAntal,
            'kassationsgrad_pct' => $total > 0 ? round(($total - $okAntal) / $total * 100, 1) : 0.0,
            'avg_cykeltid_sek'   => round($avgCykeltid, 1),
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
            error_log('RebotlingStationsdetaljController::getStationer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationer');
        }
    }

    // ================================================================
    // run=kpi-idag — KPI-kort för idag per station
    // ================================================================

    private function getKpiIdag(): void {
        $station = trim($_GET['station'] ?? '');
        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        $idag    = date('Y-m-d');
        $fromDt  = $idag . ' 00:00:00';
        $toDt    = $idag . ' 23:59:59';

        $oee = $this->calcOee($station, $fromDt, $toDt);

        // Beräkna drifttid% av planerad (8h)
        $drifttidProcent = $oee['planerad_h'] > 0
            ? round($oee['drifttid_h'] / $oee['planerad_h'] * 100, 1)
            : 0.0;

        $this->sendSuccess([
            'station'            => $station,
            'datum'              => $idag,
            'oee_pct'            => $oee['oee_pct'],
            'tillganglighet_pct' => $oee['tillganglighet_pct'],
            'prestanda_pct'      => $oee['prestanda_pct'],
            'kvalitet_pct'       => $oee['kvalitet_pct'],
            'drifttid_h'         => $oee['drifttid_h'],
            'drifttid_procent'   => $drifttidProcent,
            'planerad_h'         => $oee['planerad_h'],
            'total_ibc'          => $oee['total_ibc'],
            'ok_ibc'             => $oee['ok_ibc'],
            'kasserade_ibc'      => $oee['kasserade_ibc'],
            'kassationsgrad_pct' => $oee['kassationsgrad_pct'],
            'avg_cykeltid_sek'   => $oee['avg_cykeltid_sek'],
        ]);
    }

    // ================================================================
    // run=senaste-ibc — senaste IBCer med tidsstämpel, resultat, cykeltid
    // ================================================================

    private function getSenasteIbc(): void {
        $station = trim($_GET['station'] ?? '');
        $limit   = max(5, min(100, (int)($_GET['limit'] ?? 20)));

        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        try {
            // Hämta senaste IBCer med beräknad cykeltid (diff mot föregående)
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    station,
                    datum,
                    ok,
                    TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY station ORDER BY datum),
                        datum
                    ) AS cykeltid_sek
                FROM rebotling_ibc
                WHERE station = :station
                ORDER BY datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':station', $station, \PDO::PARAM_STR);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rader as $rad) {
                $cykeltid = (int)($rad['cykeltid_sek'] ?? 0);
                // Ignorera orimliga cykeltider (>1h eller negativa)
                if ($cykeltid < 0 || $cykeltid > 3600) $cykeltid = 0;

                $result[] = [
                    'id'          => (int)$rad['id'],
                    'datum'       => $rad['datum'],
                    'ok'          => (bool)$rad['ok'],
                    'resultat'    => $rad['ok'] ? 'OK' : 'Kasserad',
                    'cykeltid_sek'=> $cykeltid,
                    'cykeltid_fmt'=> $cykeltid > 0 ? gmdate('i:s', $cykeltid) : '—',
                ];
            }

            $this->sendSuccess([
                'station' => $station,
                'ibc'     => $result,
                'antal'   => count($result),
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::getSenasteIbc: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta IBC-data');
        }
    }

    // ================================================================
    // run=stopphistorik — stopphistorik (från rebotling_onoff)
    // ================================================================

    private function getStopphistorik(): void {
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));

        // rebotling_onoff saknar station-kolumn — visar generella stopp för linjen
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

            $result = [];
            foreach ($stopp as $rad) {
                $varSek = max(0, (int)($rad['varaktighet_sek'] ?? 0));
                $result[] = [
                    'id'              => (int)$rad['id'],
                    'start_time'      => $rad['start_time'],
                    'stop_time'       => $rad['stop_time'],
                    'varaktighet_sek' => $varSek,
                    'varaktighet_min' => round($varSek / 60, 1),
                    'varaktighet_fmt' => $varSek >= 3600
                        ? sprintf('%dh %02dm', intdiv($varSek, 3600), intdiv($varSek % 3600, 60))
                        : sprintf('%dm %02ds', intdiv($varSek, 60), $varSek % 60),
                    'status'          => $rad['status'],
                ];
            }

            $this->sendSuccess([
                'stopp' => $result,
                'antal' => count($result),
                'notat' => 'rebotling_onoff saknar station-kolumn — visar linjeövergripande stopp',
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::getStopphistorik: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stopphistorik');
        }
    }

    // ================================================================
    // run=oee-trend — OEE per dag senaste N dagar för vald station
    // ================================================================

    private function getOeeTrend(): void {
        $station = trim($_GET['station'] ?? '');
        $dagar   = max(7, min(90, (int)($_GET['dagar'] ?? 30)));

        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        $toDate = new \DateTime();
        $result = [];

        for ($i = $dagar - 1; $i >= 0; $i--) {
            $dag    = clone $toDate;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');
            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = $dagStr . ' 23:59:59';

            $oee = $this->calcOee($station, $fromDt, $toDt);

            $result[] = [
                'datum'              => $dagStr,
                'oee_pct'            => $oee['oee_pct'],
                'tillganglighet_pct' => $oee['tillganglighet_pct'],
                'prestanda_pct'      => $oee['prestanda_pct'],
                'kvalitet_pct'       => $oee['kvalitet_pct'],
                'total_ibc'          => $oee['total_ibc'],
                'drifttid_h'         => $oee['drifttid_h'],
            ];
        }

        $this->sendSuccess([
            'station' => $station,
            'dagar'   => $dagar,
            'trend'   => $result,
        ]);
    }

    // ================================================================
    // run=realtid-oee — OEE för senaste timmen (realtid)
    // ================================================================

    private function getRealtidOee(): void {
        $station = trim($_GET['station'] ?? '');
        if ($station === '') {
            $this->sendError('Parameter station kravs');
            return;
        }

        $now    = new \DateTime();
        $from1h = clone $now;
        $from1h->modify('-1 hour');

        $fromDt = $from1h->format('Y-m-d H:i:s');
        $toDt   = $now->format('Y-m-d H:i:s');

        $oee = $this->calcOee($station, $fromDt, $toDt);

        // Kolla om stationen är aktiv just nu (pågående ON-period)
        try {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM rebotling_onoff
                WHERE start_time IS NOT NULL AND stop_time IS NULL
            ");
            $aktiv = (int)($stmt->fetchColumn() ?? 0) > 0;
        } catch (\PDOException) {
            $aktiv = false;
        }

        $this->sendSuccess([
            'station'            => $station,
            'from_dt'            => $fromDt,
            'to_dt'              => $toDt,
            'aktiv_nu'           => $aktiv,
            'oee_pct'            => $oee['oee_pct'],
            'tillganglighet_pct' => $oee['tillganglighet_pct'],
            'prestanda_pct'      => $oee['prestanda_pct'],
            'kvalitet_pct'       => $oee['kvalitet_pct'],
            'total_ibc'          => $oee['total_ibc'],
            'ok_ibc'             => $oee['ok_ibc'],
            'kasserade_ibc'      => $oee['kasserade_ibc'],
            'avg_cykeltid_sek'   => $oee['avg_cykeltid_sek'],
            'drifttid_sek'       => $oee['drifttid_sek'],
        ]);
    }
}
