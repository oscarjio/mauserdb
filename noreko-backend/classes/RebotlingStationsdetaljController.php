<?php
/**
 * RebotlingStationsdetaljController.php
 * Stationsdetalj-dashboard — drill-down for rebotling-linjen.
 *
 * Endpoints via ?action=rebotling-stationsdetalj&run=XXX:
 *   - run=stationer           -> lista (single entry — rebotling har ingen station-kolumn)
 *   - run=kpi-idag            -> KPI-kort for idag: OEE, drifttid%, antal IBC, snittcykeltid
 *   - run=senaste-ibc         -> senaste skiftraknare med ibc_ok/ibc_ej_ok (?limit=N)
 *   - run=stopphistorik       -> driftstatus fran rebotling_onoff (?limit=N)
 *   - run=oee-trend           -> OEE per dag senaste 30 dagar (?dagar=30)
 *   - run=realtid-oee         -> realtids-OEE (senaste timmen)
 *
 * OEE = Tillganglighet x Prestanda x Kvalitet
 *   Tillganglighet = drifttid / planerad tid (8h/dag)
 *   Prestanda      = (antal_IBC * IDEAL_CYCLE_SEC) / drifttid (max 100%)
 *   Kvalitet       = ibc_ok / (ibc_ok + ibc_ej_ok)
 *
 * rebotling_ibc columns: datum, lopnummer, skiftraknare, ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, rasttime, op1, op2, op3
 * rebotling_onoff columns: datum, running
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
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // Helpers
    // ================================================================

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

    /**
     * Hamtar total drifttid (sek) fran rebotling_onoff for ett givet tidsintervall.
     * rebotling_onoff has columns: datum (DATETIME), running (BOOLEAN).
     */
    private function getDrifttidSek(string $fromDt, string $toDt): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum <= :to_dt
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
            error_log('RebotlingStationsdetaljController::getDrifttidSek: ' . $e->getMessage());
            return 0;
        }
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
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $ok = (int)($row['total_ok'] ?? 0);
            $ejOk = (int)($row['total_ej_ok'] ?? 0);
            return ['ok' => $ok, 'ej_ok' => $ejOk, 'total' => $ok + $ejOk];
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::getIbcData: ' . $e->getMessage());
            return ['ok' => 0, 'ej_ok' => 0, 'total' => 0];
        }
    }

    /**
     * Beraknar OEE-komponenter for ett datumintervall.
     */
    private function calcOee(string $fromDt, string $toDt): array {
        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        // Berakna planerad tid
        $fromDate = substr($fromDt, 0, 10);
        $toDate   = substr($toDt,   0, 10);
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $dagar = 0;
        while ($d <= $end) {
            if ((int)$d->format('N') <= 5) $dagar++;
            $d->modify('+1 day');
        }
        if ($fromDate === $toDate) {
            $planeradSek = self::SCHEMA_SEK_PER_DAG;
        } else {
            $planeradSek = max(1, $dagar) * self::SCHEMA_SEK_PER_DAG;
        }

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
            'drifttid_sek'       => $drifttidSek,
            'drifttid_h'         => round($drifttidSek / 3600, 2),
            'planerad_h'         => round($planeradSek / 3600, 1),
            'total_ibc'          => $total,
            'ok_ibc'             => $okAntal,
            'kasserade_ibc'      => $total - $okAntal,
            'kassationsgrad_pct' => $total > 0 ? round(($total - $okAntal) / $total * 100, 1) : 0.0,
            'avg_cykeltid_sek'   => 0,
        ];
    }

    // ================================================================
    // run=stationer — lista
    // ================================================================

    private function getStationer(): void {
        $this->sendSuccess(['stationer' => ['Rebotling']]);
    }

    // ================================================================
    // run=kpi-idag — KPI-kort for idag
    // ================================================================

    private function getKpiIdag(): void {
        $idag    = date('Y-m-d');
        $fromDt  = $idag . ' 00:00:00';
        $toDt    = $idag . ' 23:59:59';

        $oee = $this->calcOee($fromDt, $toDt);

        $drifttidProcent = $oee['planerad_h'] > 0
            ? round($oee['drifttid_h'] / $oee['planerad_h'] * 100, 1)
            : 0.0;

        $this->sendSuccess([
            'station'            => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
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
    // run=senaste-ibc — senaste skiftraknare med ibc_ok/ibc_ej_ok
    // ================================================================

    private function getSenasteIbc(): void {
        $limit   = max(5, min(100, (int)($_GET['limit'] ?? 20)));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    skiftraknare,
                    ibc_ok,
                    ibc_ej_ok,
                    bur_ej_ok,
                    lopnummer,
                    op1, op2, op3
                FROM rebotling_ibc
                ORDER BY datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rader as $rad) {
                $result[] = [
                    'datum'        => $rad['datum'],
                    'skiftraknare' => (int)$rad['skiftraknare'],
                    'ibc_ok'       => (int)$rad['ibc_ok'],
                    'ibc_ej_ok'    => (int)$rad['ibc_ej_ok'],
                    'bur_ej_ok'    => (int)$rad['bur_ej_ok'],
                    'lopnummer'    => (int)$rad['lopnummer'],
                ];
            }

            $this->sendSuccess([
                'station' => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
                'ibc'     => $result,
                'antal'   => count($result),
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::getSenasteIbc: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta IBC-data', 500);
        }
    }

    // ================================================================
    // run=stopphistorik — driftstatus (fran rebotling_onoff)
    // ================================================================

    private function getStopphistorik(): void {
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));

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

            $result = [];
            foreach ($rows as $rad) {
                $result[] = [
                    'datum'   => $rad['datum'],
                    'running' => (bool)$rad['running'],
                    'status'  => (int)$rad['running'] ? 'Drift' : 'Stopp',
                ];
            }

            $this->sendSuccess([
                'stopp' => $result,
                'antal' => count($result),
                'notat' => 'rebotling_onoff visar driftstatus (datum + running)',
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::getStopphistorik: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta stopphistorik', 500);
        }
    }

    // ================================================================
    // run=oee-trend — OEE per dag senaste N dagar
    // ================================================================

    private function getOeeTrend(): void {
        $dagar   = max(7, min(90, (int)($_GET['dagar'] ?? 30)));

        $toDate = new \DateTime();
        $result = [];

        for ($i = $dagar - 1; $i >= 0; $i--) {
            $dag    = clone $toDate;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');
            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = $dagStr . ' 23:59:59';

            $oee = $this->calcOee($fromDt, $toDt);

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
            'station' => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
            'dagar'   => $dagar,
            'trend'   => $result,
        ]);
    }

    // ================================================================
    // run=realtid-oee — OEE for senaste timmen (realtid)
    // ================================================================

    private function getRealtidOee(): void {
        $now    = new \DateTime();
        $from1h = clone $now;
        $from1h->modify('-1 hour');

        $fromDt = $from1h->format('Y-m-d H:i:s');
        $toDt   = $now->format('Y-m-d H:i:s');

        $oee = $this->calcOee($fromDt, $toDt);

        // Kolla om linjen ar aktiv just nu (senaste posten i rebotling_onoff)
        $aktiv = false;
        try {
            $stmt = $this->pdo->query("
                SELECT running FROM rebotling_onoff
                ORDER BY datum DESC LIMIT 1
            ");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $aktiv = $row && (int)$row['running'] === 1;
        } catch (\PDOException $e) {
            error_log('RebotlingStationsdetaljController::getRealtidOee aktiv: ' . $e->getMessage());
            $aktiv = false;
        }

        $this->sendSuccess([
            'station'            => htmlspecialchars($_GET['station'] ?? 'Rebotling', ENT_QUOTES, 'UTF-8'),
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
