<?php
/**
 * OeeWaterfallController.php
 * OEE-waterfall/brygga — visuell nedbrytning av OEE-förluster för rebotling.
 *
 * OEE = Tillgänglighet × Prestanda × Kvalitet
 *   Tillgänglighet = Drifttid / Total tillgänglig tid
 *   Prestanda       = (Antal IBC × Ideal cykeltid) / Drifttid
 *   Kvalitet        = OK IBC / Total IBC
 *
 * Endpoints via ?action=oee-waterfall&run=XXX:
 *   - run=waterfall-data&days=N  → Vattenfall-data: segment med timmar + % av total
 *   - run=summary&days=N         → OEE totalt + de 3 faktorerna + trend vs föregående period
 *
 * Tabeller: rebotling_onoff, rebotling_ibc, kassationsregistrering,
 *           stoppage_log, stopporsak_registreringar
 *
 * Auth: session krävs (401 om ej inloggad).
 */
class OeeWaterfallController {
    private $pdo;

    /** Ideal cykeltid per IBC i sekunder */
    private const IDEAL_CYCLE_SEC = 120;

    /** Tillgänglig tid per dag (3 skift × 7.5h = 22.5h) */
    private const TILLGANGLIG_TID_PER_DAG_H = 22.5;

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
            case 'waterfall-data': $this->getWaterfallData(); break;
            case 'summary':        $this->getSummary();       break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 7)));
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

    /**
     * Beräkna OEE-faktorer och förluster för ett datumintervall.
     * Returnerar alla nyckeltal i sekunder + faktorer (0–1).
     */
    private function calcDrifttidSek(string $from, string $to): int {
        $stmt = $this->pdo->prepare("
            SELECT datum, running FROM rebotling_onoff
            WHERE datum BETWEEN :from_dt AND :to_dt ORDER BY datum ASC
        ");
        $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sek = 0; $lastOn = null;
        foreach ($rows as $r) {
            $ts = strtotime($r['datum']);
            if ((int)$r['running'] === 1) { if ($lastOn === null) $lastOn = $ts; }
            else { if ($lastOn !== null) { $sek += max(0, $ts - $lastOn); $lastOn = null; } }
        }
        if ($lastOn !== null) $sek += max(0, min(time(), strtotime($to)) - $lastOn);
        return $sek;
    }

    private function calcOeeSegments(string $fromDate, string $toDate): array {
        $dagCount = max(1, (int)(new \DateTime($fromDate))->diff(new \DateTime($toDate))->days + 1);

        // -- Total tillgänglig tid (planerad drifttid) --
        $totalTillgangligSek = (int)round($dagCount * self::TILLGANGLIG_TID_PER_DAG_H * 3600);

        // -- DRIFTTID från rebotling_onoff (datum + running kolumner) --
        $drifttidSek = 0;
        try {
            $checkOnoff = $this->pdo->query("SHOW TABLES LIKE 'rebotling_onoff'");
            if ($checkOnoff && $checkOnoff->rowCount() > 0) {
                $drifttidSek = $this->calcDrifttidSek(
                    $fromDate . ' 00:00:00',
                    $toDate   . ' 23:59:59'
                );
            }
        } catch (\PDOException $e) {
            error_log('OeeWaterfallController::calcOeeSegments (rebotling_onoff): ' . $e->getMessage());
        }

        // -- Stopptid från stoppage_log --
        $stopptidFranLogg = 0;
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stoppage_log'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT SUM(COALESCE(duration_minutes, 0)) AS total_min
                    FROM stoppage_log
                    WHERE DATE(start_time) BETWEEN :from AND :to
                      AND duration_minutes IS NOT NULL
                      AND duration_minutes > 0
                ");
                $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stopptidFranLogg += (int)($row['total_min'] ?? 0) * 60;
            }
        } catch (\PDOException $e) {
            error_log('OeeWaterfallController::calcOeeSegments (stoppage_log): ' . $e->getMessage());
        }

        // -- Stopptid från stopporsak_registreringar --
        try {
            $checkReg = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($checkReg && $checkReg->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) AS total_min
                    FROM stopporsak_registreringar
                    WHERE DATE(start_time) BETWEEN :from AND :to
                      AND end_time IS NOT NULL
                      AND TIMESTAMPDIFF(MINUTE, start_time, end_time) > 0
                ");
                $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stopptidFranLogg += (int)($row['total_min'] ?? 0) * 60;
            }
        } catch (\PDOException $e) {
            error_log('OeeWaterfallController::calcOeeSegments (stopporsak_registreringar): ' . $e->getMessage());
        }

        // Tillgänglighetsförlust = total tillgänglig tid - drifttid
        // Om drifttid saknas, använd stopptid från loggar som approximation
        $tillganglighetsFörlustSek = 0;
        if ($drifttidSek > 0) {
            $tillganglighetsFörlustSek = max(0, $totalTillgangligSek - $drifttidSek);
        } else {
            // Fallback: använd loggad stopptid
            $tillganglighetsFörlustSek = min($stopptidFranLogg, $totalTillgangligSek);
            $drifttidSek = max(0, $totalTillgangligSek - $tillganglighetsFörlustSek);
        }

        // -- IBC-data från rebotling_ibc --
        $totalIbc = 0;
        $okIbc    = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ok), 0) AS ok_ibc,
                       COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_ibc
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from AND :to
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) sub
            ");
            $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
            $row      = $stmt->fetch(PDO::FETCH_ASSOC);
            $okIbc    = (int)($row['ok_ibc']    ?? 0);
            $totalIbc = $okIbc + (int)($row['ej_ok_ibc'] ?? 0);
        } catch (\PDOException $e) {
            error_log('OeeWaterfallController::calcOeeSegments (rebotling_ibc): ' . $e->getMessage());
        }

        // -- Kassationer från kassationsregistrering --
        $kasserade = 0;
        try {
            $checkKass = $this->pdo->query("SHOW TABLES LIKE 'kassationsregistrering'");
            if ($checkKass && $checkKass->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT SUM(COALESCE(antal, 1)) AS kasserade
                    FROM kassationsregistrering
                    WHERE DATE(datum) BETWEEN :from AND :to
                ");
                $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
                $row      = $stmt->fetch(PDO::FETCH_ASSOC);
                $kasserade = (int)($row['kasserade'] ?? 0);
            }
        } catch (\PDOException $e) {
            error_log('OeeWaterfallController::calcOeeSegments (kassationsregistrering): ' . $e->getMessage());
        }

        // -- PRESTANDA --
        // Ideal tid att producera alla IBC = totalIbc × idealCykelSek
        // Prestationsförlust = drifttid - ideal_tid (om drifttid > ideal_tid)
        $idealTidSek     = $totalIbc * self::IDEAL_CYCLE_SEC;
        $prestanda       = ($drifttidSek > 0) ? min(1.0, $idealTidSek / $drifttidSek) : 0.0;
        $prestandaFörlustSek = max(0, $drifttidSek - $idealTidSek);

        // -- KVALITET --
        // Kasserade IBC → kvalitetsförlust i tid
        // effektivTotalIbc används implicit via $totalIbc nedan

        $kvalitet         = ($totalIbc > 0) ? (($okIbc > 0 ? $okIbc : max(0, $totalIbc - $kasserade)) / $totalIbc) : 0.0;
        $kvalitet         = min(1.0, max(0.0, $kvalitet));

        // Effektiv (godkänd) produktion
        $godkandIbc       = max(0, $okIbc > 0 ? $okIbc : max(0, $totalIbc - $kasserade));
        $godkandTidSek    = $godkandIbc * self::IDEAL_CYCLE_SEC;
        $kassationsförlustsek = max(0, $idealTidSek - $godkandTidSek);

        // OEE
        $tillganglighet = $totalTillgangligSek > 0
            ? min(1.0, $drifttidSek / $totalTillgangligSek)
            : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        // Säkerställ att segmenten summerar korrekt
        // Effektiv tid = godkänd IBC × ideal cykeltid (begränsat av drifttid)
        $effektivTidSek = min($godkandTidSek, $drifttidSek);

        return [
            'total_tillganglig_sek'      => $totalTillgangligSek,
            'tillganglighets_forlust_sek' => $tillganglighetsFörlustSek,
            'drifttid_sek'               => $drifttidSek,
            'prestanda_forlust_sek'      => $prestandaFörlustSek,
            'kassations_forlust_sek'     => $kassationsförlustsek,
            'effektiv_tid_sek'           => $effektivTidSek,
            'tillganglighet'             => round($tillganglighet, 4),
            'prestanda'                  => round($prestanda,      4),
            'kvalitet'                   => round($kvalitet,        4),
            'oee'                        => round($oee,             4),
            'total_ibc'                  => $totalIbc,
            'ok_ibc'                     => $godkandIbc,
            'kasserade'                  => $kasserade,
            'dag_count'                  => $dagCount,
            'from_date'                  => $fromDate,
            'to_date'                    => $toDate,
        ];
    }

    private function sekToTimmar(int $sek): float {
        return round($sek / 3600, 2);
    }

    // ================================================================
    // ENDPOINT: waterfall-data
    // ================================================================

    /**
     * GET ?action=oee-waterfall&run=waterfall-data&days=N
     * Returnerar vattenfall-segment: varje segment med start, storlek (timmar + %) och typ.
     */
    private function getWaterfallData(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $seg = $this->calcOeeSegments($fromDate, $toDate);
        } catch (\Exception $e) {
            error_log('OeeWaterfallController::getWaterfallData: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna OEE-data', 500);
            return;
        }

        $totalSek = $seg['total_tillganglig_sek'];

        // Waterfall-segments (cumulative base för floating bar)
        // Format: [base, base+value] per kategori → waterfall-effekt i Chart.js
        $segs = [];

        // Segment 1: Total tillgänglig tid (hel stapel, grön bas)
        $segs[] = [
            'id'        => 'total',
            'label'     => 'Total tillgänglig tid',
            'timmar'    => $this->sekToTimmar($totalSek),
            'procent'   => 100.0,
            'typ'       => 'total',
            'farg'      => '#48bb78',
            'base'      => 0,
            'bar_start' => 0,
            'bar_slut'  => $this->sekToTimmar($totalSek),
        ];

        // Segment 2: Tillgänglighetsförlust (röd)
        $tillgH  = $this->sekToTimmar($seg['tillganglighets_forlust_sek']);
        $tillgPct = $totalSek > 0 ? round($seg['tillganglighets_forlust_sek'] / $totalSek * 100, 1) : 0.0;
        $segs[] = [
            'id'        => 'tillganglighet',
            'label'     => 'Tillgänglighetsförlust',
            'timmar'    => $tillgH,
            'procent'   => $tillgPct,
            'typ'       => 'forlust',
            'farg'      => '#fc8181',
            'base'      => $this->sekToTimmar($totalSek - $seg['tillganglighets_forlust_sek']),
            'bar_start' => $this->sekToTimmar($totalSek - $seg['tillganglighets_forlust_sek']),
            'bar_slut'  => $this->sekToTimmar($totalSek),
        ];

        // Segment 3: Prestationsförlust (orange)
        $prestH   = $this->sekToTimmar($seg['prestanda_forlust_sek']);
        $prestPct = $totalSek > 0 ? round($seg['prestanda_forlust_sek'] / $totalSek * 100, 1) : 0.0;
        $drifttidH = $this->sekToTimmar($seg['drifttid_sek']);
        $segs[] = [
            'id'        => 'prestanda',
            'label'     => 'Prestationsförlust',
            'timmar'    => $prestH,
            'procent'   => $prestPct,
            'typ'       => 'forlust',
            'farg'      => '#f6ad55',
            'base'      => $this->sekToTimmar($seg['effektiv_tid_sek'] + $seg['kassations_forlust_sek']),
            'bar_start' => $this->sekToTimmar($seg['effektiv_tid_sek'] + $seg['kassations_forlust_sek']),
            'bar_slut'  => $drifttidH,
        ];

        // Segment 4: Kvalitetsförlust / kassationer (gul)
        $kassH   = $this->sekToTimmar($seg['kassations_forlust_sek']);
        $kassPct = $totalSek > 0 ? round($seg['kassations_forlust_sek'] / $totalSek * 100, 1) : 0.0;
        $segs[] = [
            'id'        => 'kvalitet',
            'label'     => 'Kvalitetsförlust (kassationer)',
            'timmar'    => $kassH,
            'procent'   => $kassPct,
            'typ'       => 'forlust',
            'farg'      => '#ecc94b',
            'base'      => $this->sekToTimmar($seg['effektiv_tid_sek']),
            'bar_start' => $this->sekToTimmar($seg['effektiv_tid_sek']),
            'bar_slut'  => $this->sekToTimmar($seg['effektiv_tid_sek'] + $seg['kassations_forlust_sek']),
        ];

        // Segment 5: Effektiv produktionstid (grön)
        $effH   = $this->sekToTimmar($seg['effektiv_tid_sek']);
        $effPct = $totalSek > 0 ? round($seg['effektiv_tid_sek'] / $totalSek * 100, 1) : 0.0;
        $segs[] = [
            'id'        => 'effektiv',
            'label'     => 'Effektiv produktionstid',
            'timmar'    => $effH,
            'procent'   => $effPct,
            'typ'       => 'effektiv',
            'farg'      => '#4fd1c5',
            'base'      => 0,
            'bar_start' => 0,
            'bar_slut'  => $effH,
        ];

        $this->sendSuccess([
            'segments'               => $segs,
            'total_timmar'           => $this->sekToTimmar($totalSek),
            'oee_pct'                => round($seg['oee'] * 100, 1),
            'tillganglighet_pct'     => round($seg['tillganglighet'] * 100, 1),
            'prestanda_pct'          => round($seg['prestanda'] * 100, 1),
            'kvalitet_pct'           => round($seg['kvalitet'] * 100, 1),
            'total_ibc'              => $seg['total_ibc'],
            'ok_ibc'                 => $seg['ok_ibc'],
            'kasserade'              => $seg['kasserade'],
            'dag_count'              => $seg['dag_count'],
            'days'                   => $days,
            'from_date'              => $fromDate,
            'to_date'                => $toDate,
        ]);
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    /**
     * GET ?action=oee-waterfall&run=summary&days=N
     * OEE totalt + de 3 faktorerna + trend vs föregående period.
     */
    private function getSummary(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        // Föregående period
        $prevToDate   = date('Y-m-d', strtotime($fromDate . ' -1 day'));
        $prevFromDate = date('Y-m-d', strtotime($prevToDate . " -{$days} days"));

        try {
            $curr = $this->calcOeeSegments($fromDate, $toDate);
            $prev = $this->calcOeeSegments($prevFromDate, $prevToDate);
        } catch (\Exception $e) {
            error_log('OeeWaterfallController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna OEE-data', 500);
            return;
        }

        $oeeKlass = $this->oeeKlass($curr['oee']);

        $this->sendSuccess([
            'days'      => $days,
            'from_date' => $fromDate,
            'to_date'   => $toDate,

            // Aktuell period
            'oee_pct'                => round($curr['oee'] * 100, 1),
            'tillganglighet_pct'     => round($curr['tillganglighet'] * 100, 1),
            'prestanda_pct'          => round($curr['prestanda'] * 100, 1),
            'kvalitet_pct'           => round($curr['kvalitet'] * 100, 1),

            // Trend (differens i procentenheter)
            'oee_trend'              => round(($curr['oee'] - $prev['oee']) * 100, 1),
            'tillganglighet_trend'   => round(($curr['tillganglighet'] - $prev['tillganglighet']) * 100, 1),
            'prestanda_trend'        => round(($curr['prestanda'] - $prev['prestanda']) * 100, 1),
            'kvalitet_trend'         => round(($curr['kvalitet'] - $prev['kvalitet']) * 100, 1),

            // Status
            'oee_klass'              => $oeeKlass,

            // Detaljer
            'total_ibc'              => $curr['total_ibc'],
            'ok_ibc'                 => $curr['ok_ibc'],
            'kasserade'              => $curr['kasserade'],
            'dag_count'              => $curr['dag_count'],
        ]);
    }

    /**
     * Klassificera OEE: world-class ≥85%, bra 60–84%, lågt <60%
     */
    private function oeeKlass(float $oee): string {
        $pct = $oee * 100;
        if ($pct >= 85) return 'world-class';
        if ($pct >= 60) return 'bra';
        return 'lågt';
    }
}
