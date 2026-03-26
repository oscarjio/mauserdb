<?php
/**
 * EffektivitetController.php
 * Energi/effektivitetsvy — IBC per drifttimme trendartat.
 * Visar maskinens effektivitet över tid så VD kan se slitage eller optimering.
 *
 * Endpoints via ?action=effektivitet&run=XXX:
 *   run=trend&days=N
 *       Daglig IBC/drifttimme senaste N dagar (standard 30).
 *       Returnerar: [{date, ibc_count, drift_hours, ibc_per_hour, moving_avg_7d}]
 *
 *   run=summary
 *       Nyckeltal: aktuell IBC/h (idag), snitt 7d, snitt 30d, bästa dag, sämsta dag.
 *       Trend: jämför senaste 7d vs föregående 7d → improving|declining|stable.
 *       Returnerar: {current, avg_7d, avg_30d, best_day, worst_day, trend, change_pct}
 *
 *   run=by-shift
 *       Effektivitet per skift (dag/kväll/natt) — valt via &days=N.
 *
 * Auth: session krävs (401 om ej inloggad).
 *
 * Beräkningsmodell:
 *   - IBC-antal per dag: SUM av MAX(ibc_ok) per skiftraknare (kumulativ räknare), grupp per dag.
 *   - Drifttid per dag: SUM av MAX(runtime_plc) per skiftraknare, omvandlat till timmar.
 *   - runtime_plc är i minuter (samma enhet som i SkiftjamforelseController).
 *
 * Tabeller: rebotling_ibc, stoppage_log
 */
class EffektivitetController {
    private $pdo;

    /** Skiftdefinitioner: timme-intervall (CET) */
    private const SKIFT = [
        'dag'   => ['label' => 'Dagskift',   'start' => 6,  'end' => 14],
        'kvall' => ['label' => 'Kvällsskift', 'start' => 14, 'end' => 22],
        'natt'  => ['label' => 'Nattskift',   'start' => 22, 'end' => 6],
    ];

    /** Minsta drifttimmar per dag för att raden ska räknas som giltig */
    private const MIN_DRIFT_TIMMAR = 0.1;

    /** Tröskel (%) för trending-bedömning — under denna = stable */
    private const TREND_TRÖSKEL_PCT = 2.0;

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
            case 'trend':    $this->getTrend();   break;
            case 'summary':  $this->getSummary(); break;
            case 'by-shift': $this->getByShift(); break;
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
     * Hämta daglig effektivitetsdata för de senaste $days dagarna.
     * Returnerar array av ['date' => 'Y-m-d', 'ibc_count' => N, 'drift_hours' => X, 'ibc_per_hour' => Y]
     * sorterad stigande på datum.
     */
    private function getDagligData(int $days): array {
        $days = max(1, min(365, $days));
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        // Hämta per dag och skiftraknare: MAX(ibc_ok) + MAX(runtime_plc)
        // runtime_plc = maskinens runtime i minuter (kumulativ per skiftraknare).
        // Vi summerar per dag för att få totalt per dag.
        $stmt = $this->pdo->prepare(
            "SELECT
                dag,
                SUM(max_ibc)     AS ibc_count,
                SUM(max_runtime) AS runtime_min
             FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))      AS max_ibc,
                    MAX(COALESCE(runtime_plc, 0)) AS max_runtime
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN ? AND ?
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
             ) sub
             GROUP BY dag
             ORDER BY dag ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Indexera per datum
        $byDate = [];
        foreach ($rows as $row) {
            $dag = $row['dag'];
            $ibc     = (int)($row['ibc_count']  ?? 0);
            $runtime = (float)($row['runtime_min'] ?? 0.0);
            $hours   = round($runtime / 60.0, 4);
            $ibcH    = ($hours >= self::MIN_DRIFT_TIMMAR) ? round($ibc / $hours, 2) : null;

            $byDate[$dag] = [
                'date'         => $dag,
                'ibc_count'    => $ibc,
                'drift_hours'  => round($hours, 2),
                'ibc_per_hour' => $ibcH,
            ];
        }

        // Bygg fullständig lista för datumintervallet (null för dagar utan produktion)
        $result = [];
        $current = strtotime($fromDate);
        $end     = strtotime($toDate);
        while ($current <= $end) {
            $dag = date('Y-m-d', $current);
            if (isset($byDate[$dag])) {
                $result[] = $byDate[$dag];
            } else {
                $result[] = [
                    'date'         => $dag,
                    'ibc_count'    => 0,
                    'drift_hours'  => 0.0,
                    'ibc_per_hour' => null,
                ];
            }
            $current = strtotime('+1 day', $current);
        }

        return $result;
    }

    /**
     * Beräkna 7-dagars glidande medelvärde (centrerat bakåt) för ibc_per_hour.
     */
    private function beraknaGlidandeMedelvarde(array $dagData): array {
        $n = count($dagData);
        for ($i = 0; $i < $n; $i++) {
            // Ta upp till 7 dagar bakåt (inklusive aktuell)
            $start = max(0, $i - 6);
            $vals = [];
            for ($j = $start; $j <= $i; $j++) {
                $v = $dagData[$j]['ibc_per_hour'];
                if ($v !== null && $v > 0) {
                    $vals[] = $v;
                }
            }
            $dagData[$i]['moving_avg_7d'] = count($vals) >= 3
                ? round(array_sum($vals) / count($vals), 2)
                : null;
        }
        return $dagData;
    }

    /**
     * Beräkna snitt ibc_per_hour för en delmängd av dagData.
     */
    private function snittibc_per_hour(array $dagar): ?float {
        $vals = array_filter(
            array_column($dagar, 'ibc_per_hour'),
            fn($v) => $v !== null && $v > 0
        );
        if (count($vals) === 0) return null;
        return round(array_sum($vals) / count($vals), 2);
    }

    /**
     * WHERE-villkor för skift baserat på timme i given kolumn.
     */
    private function skiftTimewhere(string $skift, string $col): string {
        $def = self::SKIFT[$skift];
        if ($skift === 'natt') {
            return "(HOUR({$col}) >= {$def['start']} OR HOUR({$col}) < {$def['end']})";
        }
        return "(HOUR({$col}) >= {$def['start']} AND HOUR({$col}) < {$def['end']})";
    }

    // ================================================================
    // run=trend
    // ================================================================

    private function getTrend(): void {
        $days = $this->getDays();

        try {
            $dagData = $this->getDagligData($days);
            $dagData = $this->beraknaGlidandeMedelvarde($dagData);

            // Snitt 30d för referenslinje
            $snitt30d = $this->snittibc_per_hour($dagData);

            $this->sendSuccess([
                'days'     => $days,
                'trend'    => $dagData,
                'snitt_30d' => $snitt30d,
            ]);

        } catch (\Exception $e) {
            error_log('EffektivitetController::getTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta trenddata', 500);
        }
    }

    // ================================================================
    // run=summary
    // ================================================================

    private function getSummary(): void {
        try {
            // Hämta 90 dagars data för att kunna beräkna snitt 30d + jämförelse
            $dagData90 = $this->getDagligData(90);

            // Idag (dagens datum)
            $today = date('Y-m-d');
            $todayRow = null;
            foreach ($dagData90 as $r) {
                if ($r['date'] === $today) {
                    $todayRow = $r;
                    break;
                }
            }
            $currentIbcH = $todayRow['ibc_per_hour'] ?? null;

            // Senaste 30 dagarna (bakåt från idag)
            $last30 = array_filter($dagData90, function($r) {
                return strtotime($r['date']) >= strtotime('-30 days') &&
                       strtotime($r['date']) <= strtotime('today');
            });
            $last30 = array_values($last30);

            // Senaste 7 dagarna
            $last7 = array_filter($last30, function($r) {
                return strtotime($r['date']) >= strtotime('-7 days');
            });
            $last7 = array_values($last7);

            // Föregående 7 dagar (dag -14 till dag -8)
            $prev7 = array_filter($last30, function($r) {
                $ts = strtotime($r['date']);
                return $ts >= strtotime('-14 days') && $ts < strtotime('-7 days');
            });
            $prev7 = array_values($prev7);

            $snitt7d  = $this->snittibc_per_hour($last7);
            $snitt30d = $this->snittibc_per_hour($last30);
            $snittPrev7 = $this->snittibc_per_hour($prev7);

            // Bästa och sämsta dag
            $giltiga = array_filter($last30, fn($r) => $r['ibc_per_hour'] !== null && $r['ibc_per_hour'] > 0);
            $bastaDag   = null;
            $sammstaDag = null;
            if (!empty($giltiga)) {
                $bestRow  = array_reduce($giltiga, fn($carry, $r) => ($carry === null || $r['ibc_per_hour'] > $carry['ibc_per_hour']) ? $r : $carry, null);
                $worstRow = array_reduce($giltiga, fn($carry, $r) => ($carry === null || $r['ibc_per_hour'] < $carry['ibc_per_hour']) ? $r : $carry, null);
                $bastaDag   = $bestRow  ? ['date' => $bestRow['date'],  'value' => $bestRow['ibc_per_hour']]  : null;
                $sammstaDag = $worstRow ? ['date' => $worstRow['date'], 'value' => $worstRow['ibc_per_hour']] : null;
            }

            // Trend: jämför snitt 7d vs föregående 7d
            $trend     = 'stable';
            $changePct = null;
            if ($snitt7d !== null && $snittPrev7 !== null && $snittPrev7 > 0) {
                $changePct = round(($snitt7d - $snittPrev7) / $snittPrev7 * 100, 1);
                if ($changePct >= self::TREND_TRÖSKEL_PCT)  $trend = 'improving';
                elseif ($changePct <= -self::TREND_TRÖSKEL_PCT) $trend = 'declining';
                else $trend = 'stable';
            }

            $this->sendSuccess([
                'current'    => $currentIbcH,
                'avg_7d'     => $snitt7d,
                'avg_30d'    => $snitt30d,
                'best_day'   => $bastaDag,
                'worst_day'  => $sammstaDag,
                'trend'      => $trend,
                'change_pct' => $changePct,
            ]);

        } catch (\Exception $e) {
            error_log('EffektivitetController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=by-shift
    // ================================================================

    private function getByShift(): void {
        $days = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $skiftData = [];
            $bastaSkift = null;
            $bastaIbcH  = -1.0;

            foreach (array_keys(self::SKIFT) as $skift) {
                $timeCond = $this->skiftTimewhere($skift, 'datum');

                $stmt = $this->pdo->prepare(
                    "SELECT
                        COALESCE(SUM(max_ibc),     0) AS ibc_count,
                        COALESCE(SUM(max_runtime),  0) AS runtime_min,
                        COUNT(DISTINCT dag)            AS dagar_med_produktion
                     FROM (
                        SELECT
                            DATE(datum) AS dag,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok, 0))      AS max_ibc,
                            MAX(COALESCE(runtime_plc, 0)) AS max_runtime
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND skiftraknare IS NOT NULL
                          AND {$timeCond}
                        GROUP BY DATE(datum), skiftraknare
                     ) sub"
                );
                $stmt->execute([$fromDate, $toDate]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                $ibc     = (int)($row['ibc_count']  ?? 0);
                $runtime = (float)($row['runtime_min'] ?? 0.0);
                $dagar   = (int)($row['dagar_med_produktion'] ?? 0);
                $hours   = round($runtime / 60.0, 2);
                $ibcH    = $hours >= self::MIN_DRIFT_TIMMAR ? round($ibc / $hours, 2) : null;

                $skiftData[$skift] = [
                    'skift'       => $skift,
                    'label'       => self::SKIFT[$skift]['label'],
                    'ibc_count'   => $ibc,
                    'drift_hours' => $hours,
                    'ibc_per_hour'=> $ibcH,
                    'dagar'       => $dagar,
                    'ar_bast'     => false,
                ];

                if ($ibcH !== null && $ibcH > $bastaIbcH) {
                    $bastaIbcH  = $ibcH;
                    $bastaSkift = $skift;
                }
            }

            // Markera bästa skiftet
            if ($bastaSkift !== null) {
                $skiftData[$bastaSkift]['ar_bast'] = true;
            }

            $this->sendSuccess([
                'days'        => $days,
                'skift'       => array_values($skiftData),
                'basta_skift' => $bastaSkift,
            ]);

        } catch (\Exception $e) {
            error_log('EffektivitetController::getByShift: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skiftdata', 500);
        }
    }
}
