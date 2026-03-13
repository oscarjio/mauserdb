<?php
/**
 * OeeBenchmarkController.php
 * OEE (Overall Equipment Effectiveness) benchmark-jämförelse för rebotling.
 *
 * OEE = Tillgänglighet × Prestanda × Kvalitet
 *   Tillgänglighet = Drifttid / (Drifttid + Stopptid)   — från rebotling_onoff
 *   Prestanda       = (Antal IBC × Ideal cykeltid) / Drifttid   — ideal = 120 sek
 *   Kvalitet        = OK IBC / Total IBC                 — från rebotling_ibc.ok
 *
 * Branschsnitt:
 *   World Class  ≥ 85%
 *   Typiskt       60–84%
 *   Lågt         < 40%
 *
 * Endpoints via ?action=oee-benchmark&run=XXX:
 *   run=current-oee  → aktuellt OEE + de 3 faktorerna för vald period
 *   run=benchmark    → jämförelse mot branschsnitt
 *   run=trend        → OEE per dag senaste N dagar (för Chart.js)
 *   run=breakdown    → de 3 faktorerna var för sig + trenddata
 *
 * Tabeller: rebotling_onoff (start_time, stop_time), rebotling_ibc (datum, ok, skiftraknare)
 */
class OeeBenchmarkController {
    private $pdo;

    // Ideal cykeltid per IBC i sekunder (branschriktmärke)
    private const IDEAL_CYCLE_SEC = 120;

    // Branschsnitt (procent som decimal)
    private const WORLD_CLASS   = 0.85;
    private const TYPICAL       = 0.60;
    private const LOW_THRESHOLD = 0.40;

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
            case 'current-oee': $this->getCurrentOee();  break;
            case 'benchmark':   $this->getBenchmark();   break;
            case 'trend':       $this->getTrend();       break;
            case 'breakdown':   $this->getBreakdown();   break;
            default:            $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
    }

    private function sendSuccess(array $data): void {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Beräkna drifttid i sekunder från rebotling_onoff (datum + running kolumner).
     * Itererar raderna och summerar tid mellan running=1 och running=0.
     */
    private function calcDrifttidSek(string $from, string $to): int {
        $stmt = $this->pdo->prepare("
            SELECT datum, running
            FROM rebotling_onoff
            WHERE datum BETWEEN :from_dt AND :to_dt
            ORDER BY datum ASC
        ");
        $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $drifttidSek = 0;
        $lastOnTime = null;
        foreach ($rows as $row) {
            $ts = strtotime($row['datum']);
            if ((int)$row['running'] === 1) {
                if ($lastOnTime === null) {
                    $lastOnTime = $ts;
                }
            } else {
                if ($lastOnTime !== null) {
                    $drifttidSek += max(0, $ts - $lastOnTime);
                    $lastOnTime = null;
                }
            }
        }
        if ($lastOnTime !== null) {
            $endTs = min(time(), strtotime($to));
            $drifttidSek += max(0, $endTs - $lastOnTime);
        }
        return $drifttidSek;
    }

    /**
     * Beräkna OEE-faktorer för ett givet datumintervall.
     * Returnerar ['tillganglighet', 'prestanda', 'kvalitet', 'oee', 'drifttid_sek',
     *             'stopptid_sek', 'total_ibc', 'ok_ibc', 'schema_sek']
     */
    private function calcOeeForPeriod(string $fromDate, string $toDate): array {
        // ---- TILLGÄNGLIGHET ----
        // Summera drifttid (ON-perioder) från rebotling_onoff.
        // Kolumner: datum (DATETIME), running (BOOLEAN). En rad = en statusändring.
        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = $toDate   . ' 23:59:59';
        $drifttidSek = $this->calcDrifttidSek($fromDt, $toDt);

        // Schemad tid = antal dagar i perioden × 8 tim (en normalarbetsdag).
        $fromTs    = strtotime($fromDate);
        $toTs      = strtotime($toDate);
        $dagCount  = max(1, (int)(($toTs - $fromTs) / 86400) + 1);
        $schemaSek = $dagCount * 8 * 3600; // 8 tim/dag

        // Stopptid = schemad tid − drifttid (aldrig negativt)
        $stopptidSek = max(0, $schemaSek - $drifttidSek);

        // Tillgänglighet (A)
        $totalSek       = $drifttidSek + $stopptidSek; // = schemaSek
        $tillganglighet = $totalSek > 0 ? ($drifttidSek / $totalSek) : 0.0;

        // ---- KVALITET ----
        // Använd kumulativa PLC-fält ibc_ok / ibc_ej_ok per skiftraknare
        $sqlIbc = "
            SELECT
                COALESCE(SUM(shift_ok), 0) AS ok_ibc,
                COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_ibc
            FROM (
                SELECT skiftraknare,
                       MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                       MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from AND :to
                  AND skiftraknare IS NOT NULL
                GROUP BY skiftraknare
            ) sub
        ";
        $stmtIbc = $this->pdo->prepare($sqlIbc);
        $stmtIbc->execute([':from' => $fromDate, ':to' => $toDate]);
        $ibcRow   = $stmtIbc->fetch(PDO::FETCH_ASSOC);
        $okIbc    = (int)($ibcRow['ok_ibc']    ?? 0);
        $ejOkIbc  = (int)($ibcRow['ej_ok_ibc'] ?? 0);
        $totalIbc = $okIbc + $ejOkIbc;
        $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;

        // ---- PRESTANDA ----
        // Prestanda = (Antal IBC × Ideal cykeltid) / Drifttid
        if ($drifttidSek > 0) {
            $prestanda = min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek);
        } else {
            $prestanda = 0.0;
        }

        // ---- OEE ----
        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'tillganglighet' => round($tillganglighet, 4),
            'prestanda'      => round($prestanda,      4),
            'kvalitet'       => round($kvalitet,        4),
            'oee'            => round($oee,             4),
            'drifttid_sek'   => $drifttidSek,
            'stopptid_sek'   => $stopptidSek,
            'schema_sek'     => $schemaSek,
            'total_ibc'      => $totalIbc,
            'ok_ibc'         => $okIbc,
            'dag_count'      => $dagCount,
        ];
    }

    // ================================================================
    // run=current-oee
    // ================================================================

    private function getCurrentOee(): void {
        try {
            $days     = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));
            $toDate   = date('Y-m-d');

            $result = $this->calcOeeForPeriod($fromDate, $toDate);

            // Färgklass
            $oeeVal = $result['oee'];
            if ($oeeVal >= self::WORLD_CLASS) {
                $status = 'world-class';
                $color  = 'teal';
            } elseif ($oeeVal >= self::TYPICAL) {
                $status = 'bra';
                $color  = 'green';
            } elseif ($oeeVal >= self::LOW_THRESHOLD) {
                $status = 'typiskt';
                $color  = 'yellow';
            } else {
                $status = 'lågt';
                $color  = 'red';
            }

            $this->sendSuccess([
                'oee'            => $result['oee'],
                'oee_pct'        => round($result['oee'] * 100, 1),
                'tillganglighet' => $result['tillganglighet'],
                'tillganglighet_pct' => round($result['tillganglighet'] * 100, 1),
                'prestanda'      => $result['prestanda'],
                'prestanda_pct'  => round($result['prestanda'] * 100, 1),
                'kvalitet'       => $result['kvalitet'],
                'kvalitet_pct'   => round($result['kvalitet'] * 100, 1),
                'drifttid_h'     => round($result['drifttid_sek'] / 3600, 1),
                'stopptid_h'     => round($result['stopptid_sek'] / 3600, 1),
                'schema_h'       => round($result['schema_sek'] / 3600, 1),
                'total_ibc'      => $result['total_ibc'],
                'ok_ibc'         => $result['ok_ibc'],
                'status'         => $status,
                'color'          => $color,
                'days'           => $days,
                'from_date'      => $fromDate,
                'to_date'        => $toDate,
            ]);

        } catch (Exception $e) {
            error_log('OeeBenchmarkController::getCurrentOee: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna OEE', 500);
        }
    }

    // ================================================================
    // run=benchmark
    // ================================================================

    private function getBenchmark(): void {
        try {
            $days     = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));
            $toDate   = date('Y-m-d');

            $result = $this->calcOeeForPeriod($fromDate, $toDate);
            $oee    = $result['oee'];

            // Jämförelsetabell
            $benchmarks = [
                [
                    'namn'        => 'World Class',
                    'mal'         => self::WORLD_CLASS,
                    'mal_pct'     => round(self::WORLD_CLASS * 100, 0),
                    'gap'         => round(($oee - self::WORLD_CLASS) * 100, 1),
                    'gap_pct'     => round(($oee - self::WORLD_CLASS) * 100, 1),
                    'over_target' => $oee >= self::WORLD_CLASS,
                    'color'       => 'teal',
                ],
                [
                    'namn'        => 'Branschsnitt',
                    'mal'         => self::TYPICAL,
                    'mal_pct'     => round(self::TYPICAL * 100, 0),
                    'gap'         => round(($oee - self::TYPICAL) * 100, 1),
                    'gap_pct'     => round(($oee - self::TYPICAL) * 100, 1),
                    'over_target' => $oee >= self::TYPICAL,
                    'color'       => 'blue',
                ],
                [
                    'namn'        => 'Lägsta godtagbara',
                    'mal'         => self::LOW_THRESHOLD,
                    'mal_pct'     => round(self::LOW_THRESHOLD * 100, 0),
                    'gap'         => round(($oee - self::LOW_THRESHOLD) * 100, 1),
                    'gap_pct'     => round(($oee - self::LOW_THRESHOLD) * 100, 1),
                    'over_target' => $oee >= self::LOW_THRESHOLD,
                    'color'       => 'orange',
                ],
            ];

            // Förbättringsanalys: vilken faktor är lägst?
            $faktorer = [
                'tillganglighet' => $result['tillganglighet'],
                'prestanda'      => $result['prestanda'],
                'kvalitet'       => $result['kvalitet'],
            ];
            asort($faktorer);
            $lagstaFaktor    = array_key_first($faktorer);
            $lagstaVarde     = $faktorer[$lagstaFaktor];

            $forbattringsforslag = $this->getForbattringsforslag($lagstaFaktor, $lagstaVarde, $oee);

            $this->sendSuccess([
                'oee_pct'             => round($oee * 100, 1),
                'benchmarks'          => $benchmarks,
                'lagsta_faktor'       => $lagstaFaktor,
                'lagsta_faktor_pct'   => round($lagstaVarde * 100, 1),
                'forbattringsforslag' => $forbattringsforslag,
                'days'                => $days,
            ]);

        } catch (Exception $e) {
            error_log('OeeBenchmarkController::getBenchmark: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta benchmark-data', 500);
        }
    }

    // ================================================================
    // run=trend
    // ================================================================

    private function getTrend(): void {
        try {
            $days = $this->getDays();

            $trendPoints = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dag  = date('Y-m-d', strtotime("-{$i} days"));
                $calc = $this->calcOeeForPeriod($dag, $dag);

                $trendPoints[] = [
                    'datum'          => $dag,
                    'oee_pct'        => round($calc['oee'] * 100, 1),
                    'tillganglighet_pct' => round($calc['tillganglighet'] * 100, 1),
                    'prestanda_pct'  => round($calc['prestanda'] * 100, 1),
                    'kvalitet_pct'   => round($calc['kvalitet'] * 100, 1),
                    'total_ibc'      => $calc['total_ibc'],
                ];
            }

            // Summering
            $oeeVarden = array_column($trendPoints, 'oee_pct');
            $nonZero   = array_filter($oeeVarden, fn($v) => $v > 0);
            $avgOee    = !empty($nonZero) ? round(array_sum($nonZero) / count($nonZero), 1) : 0.0;
            $maxOee    = !empty($oeeVarden) ? max($oeeVarden) : 0.0;
            $minOee    = !empty($nonZero) ? min($nonZero) : 0.0;

            $this->sendSuccess([
                'trend'      => $trendPoints,
                'avg_oee'    => $avgOee,
                'max_oee'    => $maxOee,
                'min_oee'    => $minOee,
                'world_class_pct' => round(self::WORLD_CLASS * 100, 0),
                'typical_pct'     => round(self::TYPICAL * 100, 0),
                'days'       => $days,
            ]);

        } catch (Exception $e) {
            error_log('OeeBenchmarkController::getTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta trenddata', 500);
        }
    }

    // ================================================================
    // run=breakdown
    // ================================================================

    private function getBreakdown(): void {
        try {
            $days = $this->getDays();

            // Aktuell period
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));
            $toDate   = date('Y-m-d');
            $current  = $this->calcOeeForPeriod($fromDate, $toDate);

            // Föregående period (för trend-pil)
            $prevFrom = date('Y-m-d', strtotime("-{$days} days", strtotime($fromDate)));
            $prevTo   = date('Y-m-d', strtotime('-1 day', strtotime($fromDate)));
            $prev     = $this->calcOeeForPeriod($prevFrom, $prevTo);

            $faktorer = [
                [
                    'id'         => 'tillganglighet',
                    'namn'       => 'Tillganglighet',
                    'visningsnamn' => 'Tillgänglighet',
                    'pct'        => round($current['tillganglighet'] * 100, 1),
                    'prev_pct'   => round($prev['tillganglighet']    * 100, 1),
                    'trend'      => $this->trendRiktning($current['tillganglighet'], $prev['tillganglighet']),
                    'forklaring' => 'Drifttid / (Drifttid + Stopptid)',
                    'drifttid_h' => round($current['drifttid_sek'] / 3600, 1),
                    'stopptid_h' => round($current['stopptid_sek'] / 3600, 1),
                    'icon'       => 'fa-power-off',
                    'color'      => 'blue',
                ],
                [
                    'id'         => 'prestanda',
                    'namn'       => 'Prestanda',
                    'visningsnamn' => 'Prestanda',
                    'pct'        => round($current['prestanda'] * 100, 1),
                    'prev_pct'   => round($prev['prestanda']    * 100, 1),
                    'trend'      => $this->trendRiktning($current['prestanda'], $prev['prestanda']),
                    'forklaring' => 'Faktisk produktion / Teoretisk maxproduktion',
                    'total_ibc'  => $current['total_ibc'],
                    'ideal_ibc'  => $current['drifttid_sek'] > 0
                        ? (int)floor($current['drifttid_sek'] / self::IDEAL_CYCLE_SEC) : 0,
                    'icon'       => 'fa-tachometer-alt',
                    'color'      => 'purple',
                ],
                [
                    'id'         => 'kvalitet',
                    'namn'       => 'Kvalitet',
                    'visningsnamn' => 'Kvalitet',
                    'pct'        => round($current['kvalitet'] * 100, 1),
                    'prev_pct'   => round($prev['kvalitet']    * 100, 1),
                    'trend'      => $this->trendRiktning($current['kvalitet'], $prev['kvalitet']),
                    'forklaring' => 'Godkända IBC / Totala IBC',
                    'ok_ibc'     => $current['ok_ibc'],
                    'total_ibc'  => $current['total_ibc'],
                    'kasserade'  => $current['total_ibc'] - $current['ok_ibc'],
                    'icon'       => 'fa-check-circle',
                    'color'      => 'green',
                ],
            ];

            // Trendpunkter för varje faktor (7 senaste dagarna alltid, oavsett vald period)
            $sparkDays  = min($days, 14);
            $sparkLines = ['tillganglighet' => [], 'prestanda' => [], 'kvalitet' => []];
            for ($i = $sparkDays - 1; $i >= 0; $i--) {
                $dag  = date('Y-m-d', strtotime("-{$i} days"));
                $calc = $this->calcOeeForPeriod($dag, $dag);
                $sparkLines['tillganglighet'][] = ['datum' => $dag, 'pct' => round($calc['tillganglighet'] * 100, 1)];
                $sparkLines['prestanda'][]      = ['datum' => $dag, 'pct' => round($calc['prestanda']      * 100, 1)];
                $sparkLines['kvalitet'][]        = ['datum' => $dag, 'pct' => round($calc['kvalitet']       * 100, 1)];
            }

            $this->sendSuccess([
                'faktorer'   => $faktorer,
                'spark'      => $sparkLines,
                'oee_pct'    => round($current['oee'] * 100, 1),
                'prev_oee_pct' => round($prev['oee'] * 100, 1),
                'oee_trend'  => $this->trendRiktning($current['oee'], $prev['oee']),
                'days'       => $days,
            ]);

        } catch (Exception $e) {
            error_log('OeeBenchmarkController::getBreakdown: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta breakdown-data', 500);
        }
    }

    // ================================================================
    // UTIL
    // ================================================================

    private function trendRiktning(float $current, float $prev): string {
        $diff = $current - $prev;
        if ($diff > 0.005) return 'up';
        if ($diff < -0.005) return 'down';
        return 'flat';
    }

    private function getForbattringsforslag(string $faktor, float $varde, float $oee): array {
        $forslag = [];

        if ($faktor === 'tillganglighet') {
            $forslag[] = 'Fokusera pa att minska oplanerade stopp och nedtid';
            $forslag[] = 'Analysera stopporsakerna — vilka stannar maskinen mest?';
            $forslag[] = 'Implementera forebyggande underhall for att undvika haverier';
        } elseif ($faktor === 'prestanda') {
            $forslag[] = 'Undersok om operatorerna kors upp till ideal cykeltid (' . self::IDEAL_CYCLE_SEC . ' sek)';
            $forslag[] = 'Kolla om det finns flaskhalsar i floden som bromsar takten';
            $forslag[] = 'Utbilda operatorerna i effektiv arbetsmetod';
        } else {
            $forslag[] = 'Undersok orsaker till kasserade IBC-behallar';
            $forslag[] = 'Genomfor rotorsaksanalys pa kvalitetsavvikelserna';
            $forslag[] = 'Kolla att utrustning och slangar ar i gott skick';
        }

        // Extra generellt tips baserat pa total OEE
        if ($oee < self::LOW_THRESHOLD) {
            $forslag[] = 'OEE ar kritiskt lagt — prioritera omedelbar atgard for att na minst 40%';
        } elseif ($oee < self::TYPICAL) {
            $forslag[] = 'Nasta mal: na branschsnittet pa ' . round(self::TYPICAL * 100) . '%';
        } elseif ($oee < self::WORLD_CLASS) {
            $forslag[] = 'Bra niva! Fokusera pa att na World Class (' . round(self::WORLD_CLASS * 100) . '%)';
        }

        return $forslag;
    }
}
