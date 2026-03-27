<?php
/**
 * UtnyttjandegradController.php
 * Maskinutnyttjandegrad — hur stor andel av tillgänglig tid maskinen producerar.
 * Identifierar dolda tidstjuvar (uppstart, raster, omställning).
 *
 * Endpoints via ?action=utnyttjandegrad&run=XXX:
 *   run=summary
 *       Utnyttjandegrad idag + snitt 7d + snitt 30d med trend.
 *
 *   run=daily&days=N
 *       Daglig tidsserie med tillgänglig tid, drifttid, stopptid,
 *       utnyttjandegrad-%, antal stopp per dag.
 *
 *   run=losses&days=N
 *       Tidsförlustanalys — kategoriserade förluster:
 *       planerade stopp, oplanerade stopp, uppstart/avslut, okänt.
 *
 * Auth: session krävs (401 om ej inloggad).
 *
 * Beräkningsmodell:
 *   - Drifttid: SUM av MAX(runtime_plc) per skiftraknare per dag (minuter → timmar).
 *   - Tillgänglig tid: skiftlängd per dag (3 skift × 8h = 24h, minus planerade raster).
 *     Standardantagande: 22.5h tillgänglig tid per dag (3 × 7.5h = 24h − 3 × 30min rast).
 *   - Stopptid: summa duration_minutes från stoppage_log per dag.
 *   - Utnyttjandegrad = drifttid / tillgänglig tid × 100.
 *
 * Tabeller: rebotling_ibc, stoppage_log, stoppage_reasons
 */
class UtnyttjandegradController {
    private $pdo;

    /** Tillgänglig tid per dag i timmar (3 skift × 7.5h efter rast) */
    private const TILLGANGLIG_TID_PER_DAG = 22.5;

    /** Tröskel (%) för trending-bedömning */
    private const TREND_TROSKEL_PCT = 2.0;

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
            case 'summary': $this->getSummary(); break;
            case 'daily':   $this->getDaily();   break;
            case 'losses':  $this->getLosses();   break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
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

    private function getDays(): int {
        $d = (int)($_GET['days'] ?? 30);
        return max(7, min(365, $d));
    }

    /**
     * Hämta daglig drifttid (timmar) från rebotling_ibc.
     * runtime_plc är i minuter, kumulativ per skiftraknare.
     * Vi tar MAX per skiftraknare + dag och summerar.
     */
    private function getDagligDrifttid(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                dag,
                SUM(max_runtime) AS runtime_min
             FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(runtime_plc) AS max_runtime
                FROM rebotling_ibc
                WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
             ) sub
             GROUP BY dag
             ORDER BY dag ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['dag']] = round((float)($row['runtime_min'] ?? 0) / 60.0, 4);
        }
        return $result;
    }

    /**
     * Hämta daglig stopptid och antal stopp från stoppage_log.
     * Returnerar [dag => ['stopp_timmar' => X, 'antal_stopp' => N, 'planerad' => P, 'oplanerad' => U]]
     */
    private function getDagligStopp(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                DATE(s.start_time) AS dag,
                COUNT(*)           AS antal_stopp,
                COALESCE(SUM(s.duration_minutes), 0) AS stopp_min,
                COALESCE(SUM(CASE WHEN r.category = 'planned' THEN s.duration_minutes ELSE 0 END), 0) AS planerad_min,
                COALESCE(SUM(CASE WHEN r.category = 'unplanned' THEN s.duration_minutes ELSE 0 END), 0) AS oplanerad_min
             FROM stoppage_log s
             LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
             WHERE DATE(s.start_time) BETWEEN ? AND ?
               AND s.line = 'rebotling'
             GROUP BY dag
             ORDER BY dag ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['dag']] = [
                'stopp_timmar'   => round((float)($row['stopp_min'] ?? 0) / 60.0, 4),
                'antal_stopp'    => (int)($row['antal_stopp'] ?? 0),
                'planerad_h'     => round((float)($row['planerad_min'] ?? 0) / 60.0, 4),
                'oplanerad_h'    => round((float)($row['oplanerad_min'] ?? 0) / 60.0, 4),
            ];
        }
        return $result;
    }

    /**
     * Bygg fullständig daglig data-array.
     */
    private function byggDagligData(int $days): array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $drifttid = $this->getDagligDrifttid($fromDate, $toDate);
        $stopp    = $this->getDagligStopp($fromDate, $toDate);

        $result = [];
        $current = strtotime($fromDate);
        $end     = strtotime($toDate);

        while ($current <= $end) {
            $dag = date('Y-m-d', $current);
            $dayOfWeek = (int)date('N', $current); // 1=Mån, 7=Sön

            // Tillgänglig tid: 0 på söndag (ingen produktion), annars standard
            $tillganglig = ($dayOfWeek === 7) ? 0.0 : self::TILLGANGLIG_TID_PER_DAG;

            $drift   = $drifttid[$dag] ?? 0.0;
            $stoppH  = $stopp[$dag]['stopp_timmar'] ?? 0.0;
            $antalSt = $stopp[$dag]['antal_stopp'] ?? 0;

            // Begränsa drifttid till max tillgänglig tid
            if ($drift > $tillganglig && $tillganglig > 0) {
                $drift = $tillganglig;
            }

            $utnyttjandegrad = ($tillganglig > 0) ? round(($drift / $tillganglig) * 100, 1) : null;

            // Okänd tid = tillgänglig - drift - registrerade stopp
            $okandTid = max(0, round($tillganglig - $drift - $stoppH, 2));

            $result[] = [
                'date'              => $dag,
                'tillganglig_h'     => round($tillganglig, 2),
                'drifttid_h'        => round($drift, 2),
                'stopptid_h'        => round($stoppH, 2),
                'okand_tid_h'       => $okandTid,
                'utnyttjandegrad'   => $utnyttjandegrad,
                'antal_stopp'       => $antalSt,
            ];

            $current = strtotime('+1 day', $current);
        }

        return $result;
    }

    /**
     * Beräkna snitt utnyttjandegrad för en array av dagData.
     */
    private function snittUtnyttjandegrad(array $dagar): ?float {
        $vals = array_filter(
            array_column($dagar, 'utnyttjandegrad'),
            fn($v) => $v !== null && $v > 0
        );
        if (count($vals) === 0) return null;
        return round(array_sum($vals) / count($vals), 1);
    }

    // ================================================================
    // run=summary
    // ================================================================

    private function getSummary(): void {
        try {
            $dagData = $this->byggDagligData(90);

            // Idag
            $today = date('Y-m-d');
            $todayRow = null;
            foreach ($dagData as $r) {
                if ($r['date'] === $today) {
                    $todayRow = $r;
                    break;
                }
            }
            $idagPct = $todayRow['utnyttjandegrad'] ?? null;
            $idagDrifttid = $todayRow['drifttid_h'] ?? 0;
            $idagTillganglig = $todayRow['tillganglig_h'] ?? 0;

            // Senaste 30 dagarna
            $last30 = array_filter($dagData, function($r) {
                return strtotime($r['date']) >= strtotime('-30 days') &&
                       strtotime($r['date']) <= strtotime('today');
            });
            $last30 = array_values($last30);

            // Senaste 7 dagarna
            $last7 = array_filter($last30, function($r) {
                return strtotime($r['date']) >= strtotime('-7 days');
            });
            $last7 = array_values($last7);

            // Föregående 7 dagar
            $prev7 = array_filter($last30, function($r) {
                $ts = strtotime($r['date']);
                return $ts >= strtotime('-14 days') && $ts < strtotime('-7 days');
            });
            $prev7 = array_values($prev7);

            $snitt7d  = $this->snittUtnyttjandegrad($last7);
            $snitt30d = $this->snittUtnyttjandegrad($last30);
            $snittPrev7 = $this->snittUtnyttjandegrad($prev7);

            // Trend
            $trend     = 'stable';
            $changePct = null;
            if ($snitt7d !== null && $snittPrev7 !== null && $snittPrev7 > 0) {
                $changePct = round(($snitt7d - $snittPrev7) / $snittPrev7 * 100, 1);
                if ($changePct >= self::TREND_TROSKEL_PCT)       $trend = 'improving';
                elseif ($changePct <= -self::TREND_TROSKEL_PCT)  $trend = 'declining';
                else $trend = 'stable';
            }

            // Totala timmar senaste 7d för kontext
            $totalDrift7d = array_sum(array_column($last7, 'drifttid_h'));
            $totalTillg7d = array_sum(array_column($last7, 'tillganglig_h'));
            $totalStopp7d = array_sum(array_column($last7, 'stopptid_h'));

            $this->sendSuccess([
                'idag_pct'          => $idagPct,
                'idag_drifttid_h'   => round($idagDrifttid, 2),
                'idag_tillganglig_h'=> round($idagTillganglig, 2),
                'snitt_7d'          => $snitt7d,
                'snitt_30d'         => $snitt30d,
                'trend'             => $trend,
                'change_pct'        => $changePct,
                'total_drift_7d_h'  => round($totalDrift7d, 1),
                'total_tillg_7d_h'  => round($totalTillg7d, 1),
                'total_stopp_7d_h'  => round($totalStopp7d, 1),
            ]);

        } catch (\Exception $e) {
            error_log('UtnyttjandegradController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=daily
    // ================================================================

    private function getDaily(): void {
        $days = $this->getDays();

        try {
            $dagData = $this->byggDagligData($days);

            $this->sendSuccess([
                'days'  => $days,
                'daily' => $dagData,
            ]);

        } catch (\Exception $e) {
            error_log('UtnyttjandegradController::getDaily: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta daglig data', 500);
        }
    }

    // ================================================================
    // run=losses
    // ================================================================

    private function getLosses(): void {
        $days = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $dagData = $this->byggDagligData($days);

            // Summera totalt
            $totalTillganglig = array_sum(array_column($dagData, 'tillganglig_h'));
            $totalDrift       = array_sum(array_column($dagData, 'drifttid_h'));
            $totalStopp       = array_sum(array_column($dagData, 'stopptid_h'));
            $totalOkand       = array_sum(array_column($dagData, 'okand_tid_h'));

            // Hämta stoppkategorier
            $stopp = $this->getDagligStopp($fromDate, $toDate);
            $totalPlanerad  = 0;
            $totalOplanerad = 0;
            foreach ($stopp as $data) {
                $totalPlanerad  += $data['planerad_h'];
                $totalOplanerad += $data['oplanerad_h'];
            }
            $totalPlanerad  = round($totalPlanerad, 2);
            $totalOplanerad = round($totalOplanerad, 2);

            // Uppskatta uppstart/avslut-tid:
            // Räkna produktionsdagar, antag 0.5h uppstart + 0.25h avslut per dag = 0.75h
            $prodDagar = count(array_filter($dagData, fn($r) => $r['drifttid_h'] > 0));
            $uppstartTid = round($prodDagar * 0.75, 2);

            // Justera okänd tid: dra bort uppstartstid
            $residualOkand = max(0, round($totalOkand - $uppstartTid, 2));

            // Förlustfördelning
            $losses = [
                [
                    'kategori'    => 'Drifttid',
                    'timmar'      => round($totalDrift, 2),
                    'procent'     => $totalTillganglig > 0 ? round($totalDrift / $totalTillganglig * 100, 1) : 0,
                    'farg'        => '#48bb78',
                    'typ'         => 'produktion',
                ],
                [
                    'kategori'    => 'Planerade stopp',
                    'timmar'      => $totalPlanerad,
                    'procent'     => $totalTillganglig > 0 ? round($totalPlanerad / $totalTillganglig * 100, 1) : 0,
                    'farg'        => '#4299e1',
                    'typ'         => 'planerat',
                ],
                [
                    'kategori'    => 'Oplanerade stopp',
                    'timmar'      => $totalOplanerad,
                    'procent'     => $totalTillganglig > 0 ? round($totalOplanerad / $totalTillganglig * 100, 1) : 0,
                    'farg'        => '#e53e3e',
                    'typ'         => 'oplanerat',
                ],
                [
                    'kategori'    => 'Uppstart/avslut',
                    'timmar'      => $uppstartTid,
                    'procent'     => $totalTillganglig > 0 ? round($uppstartTid / $totalTillganglig * 100, 1) : 0,
                    'farg'        => '#ed8936',
                    'typ'         => 'uppstart',
                ],
                [
                    'kategori'    => 'Okänd tid',
                    'timmar'      => $residualOkand,
                    'procent'     => $totalTillganglig > 0 ? round($residualOkand / $totalTillganglig * 100, 1) : 0,
                    'farg'        => '#718096',
                    'typ'         => 'okant',
                ],
            ];

            // Hämta topp stopporsaker
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(r.name, 'Okänd orsak') AS orsak, r.category,
                        COUNT(*) AS antal,
                        COALESCE(SUM(s.duration_minutes), 0) AS total_min
                 FROM stoppage_log s
                 LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                 WHERE DATE(s.start_time) BETWEEN ? AND ?
                   AND s.line = 'rebotling'
                 GROUP BY r.id, r.name, r.category
                 ORDER BY total_min DESC
                 LIMIT 10"
            );
            $stmt->execute([$fromDate, $toDate]);
            $toppOrsaker = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($toppOrsaker as &$o) {
                $o['total_h'] = round((float)$o['total_min'] / 60.0, 2);
                $o['antal']   = (int)$o['antal'];
                unset($o['total_min']);
            }
            unset($o);

            $this->sendSuccess([
                'days'              => $days,
                'total_tillganglig_h' => round($totalTillganglig, 1),
                'total_drifttid_h'    => round($totalDrift, 1),
                'total_stopptid_h'    => round($totalStopp, 1),
                'losses'              => $losses,
                'topp_orsaker'        => $toppOrsaker,
            ]);

        } catch (\Exception $e) {
            error_log('UtnyttjandegradController::getLosses: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta förlustanalys', 500);
        }
    }
}
