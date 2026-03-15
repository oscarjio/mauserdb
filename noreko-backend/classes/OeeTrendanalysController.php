<?php
/**
 * OeeTrendanalysController.php
 * Djupare OEE-trendanalys for rebotling-linjen.
 *
 * OEE = Tillganglighet x Prestanda x Kvalitet
 *   Tillganglighet = Drifttid / (Drifttid + Stopptid)
 *   Prestanda       = (Antal IBC x Ideal cykeltid) / Drifttid
 *   Kvalitet        = OK IBC / Total IBC
 *
 * Endpoints via ?action=oee-trendanalys&run=XXX:
 *   run=sammanfattning — KPI-kort: OEE idag, snitt 7d/30d, basta/samsta station, trend
 *   run=per-station    — OEE per station med breakdown + ranking + perioddelta
 *   run=trend          — OEE per dag senaste 30/90d, rullande 7d-snitt, per station eller totalt
 *   run=flaskhalsar    — Top 5 stationer/tidpunkter med lagst OEE-faktorer + atgardsforslag
 *   run=jamforelse     — Jamfor 2 perioder: OEE-delta per station
 *   run=prediktion     — Linjar regression + prediktion kommande 7d
 *
 * Tabeller: rebotling_onoff, rebotling_ibc, rebotling_stationer,
 *           stopporsak_registreringar, rebotling_underhallslogg
 */
class OeeTrendanalysController {
    private $pdo;
    private const IDEAL_CYCLE_SEC = 120;
    private const WORLD_CLASS = 0.85;
    private const TYPICAL = 0.60;

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
            case 'sammanfattning': $this->sammanfattning(); break;
            case 'per-station':    $this->perStation();     break;
            case 'trend':          $this->trend();          break;
            case 'flaskhalsar':    $this->flaskhalsar();    break;
            case 'jamforelse':     $this->jamforelse();     break;
            case 'prediktion':     $this->prediktion();     break;
            default:               $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
    }

    private function getStation(): ?int {
        $s = $_GET['station'] ?? '';
        return $s !== '' ? max(1, intval($s)) : null;
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
     * Hamta lista av alla stationer
     */
    private function getStationer(): array {
        // Prova rebotling_stationer forst
        try {
            $stmt = $this->pdo->query("SELECT id, namn FROM rebotling_stationer ORDER BY id");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) return $rows;
        } catch (Exception) {
            // Tabellen kanske inte finns
        }

        // Fallback: harda stationsnamn (typiska for rebotling)
        return [
            ['id' => 1, 'namn' => 'Station 1'],
            ['id' => 2, 'namn' => 'Station 2'],
            ['id' => 3, 'namn' => 'Station 3'],
            ['id' => 4, 'namn' => 'Station 4'],
            ['id' => 5, 'namn' => 'Station 5'],
        ];
    }

    /**
     * Beräkna drifttid i sekunder från rebotling_onoff (datum + running kolumner).
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

    /**
     * Berakna OEE for en period (totalt, inte per station).
     */
    private function calcOeeForPeriod(string $from, string $to): array {
        // Drifttid fran rebotling_onoff (datum + running)
        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';
        $drifttidSek = $this->calcDrifttidSek($fromDt, $toDt);

        // Schemad tid
        $dagCount  = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
        $schemaSek = $dagCount * 8 * 3600;
        $stopptidSek = max(0, $schemaSek - $drifttidSek);

        $tillganglighet = $schemaSek > 0 ? ($drifttidSek / $schemaSek) : 0.0;

        // IBC via kumulativa PLC-fält
        $sqlIbc = "
            SELECT COALESCE(SUM(shift_ok), 0) AS ok_ibc,
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
        $stmt = $this->pdo->prepare($sqlIbc);
        $stmt->execute([':from' => $from, ':to' => $to]);
        $ibcRow   = $stmt->fetch(PDO::FETCH_ASSOC);
        $okIbc    = (int)($ibcRow['ok_ibc']    ?? 0);
        $totalIbc = $okIbc + (int)($ibcRow['ej_ok_ibc'] ?? 0);
        $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;

        $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;

        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'tillganglighet' => round($tillganglighet, 4),
            'prestanda'      => round($prestanda, 4),
            'kvalitet'       => round($kvalitet, 4),
            'oee'            => round($oee, 4),
            'drifttid_sek'   => $drifttidSek,
            'stopptid_sek'   => $stopptidSek,
            'total_ibc'      => $totalIbc,
            'ok_ibc'         => $okIbc,
        ];
    }

    /**
     * Berakna OEE for en period per station (via rebotling_ibc.station_id).
     * Returnerar array indexed by station_id.
     */
    private function calcOeePerStation(string $from, string $to): array {
        $stationer = $this->getStationer();
        $dagCount  = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);

        // Hamta IBC-data per station via kumulativa PLC-fält
        $sqlIbc = "
            SELECT
                COALESCE(station_id, 1) AS station_id,
                SUM(shift_ok) AS ok_ibc,
                SUM(shift_ej_ok) AS ej_ok_ibc
            FROM (
                SELECT station_id, skiftraknare,
                       MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                       MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from AND :to
                  AND skiftraknare IS NOT NULL
                GROUP BY COALESCE(station_id, 1), skiftraknare
            ) sub
            GROUP BY station_id
        ";
        $stmt = $this->pdo->prepare($sqlIbc);
        $stmt->execute([':from' => $from, ':to' => $to]);
        $ibcByStation = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['total_ibc'] = (int)$row['ok_ibc'] + (int)$row['ej_ok_ibc'];
            $ibcByStation[(int)$row['station_id']] = $row;
        }

        // Total drifttid (rebotling_onoff saknar station_id, dela lika)
        $driftByStation = [];
        try {
            $fromDt = $from . ' 00:00:00';
            $toDt   = $to   . ' 23:59:59';
            $totalDrift = $this->calcDrifttidSek($fromDt, $toDt);
            $stationCount = max(1, count($stationer));
            foreach ($stationer as $s) {
                $driftByStation[(int)$s['id']] = (int)($totalDrift / $stationCount);
            }
            // Dummy loop to match following code
            foreach ([] as $row) {
                $driftByStation[(int)$row['station_id']] = max(0, (int)$row['drifttid_sek']);
            }
        } catch (Exception) {
            // Fallback: fordela total drifttid jamt
        }

        // Om ingen stationsdata i rebotling_onoff, anvand total och fordela
        if (empty($driftByStation)) {
            $total = $this->calcOeeForPeriod($from, $to);
            $stationCount = max(1, count($stationer));
            foreach ($stationer as $s) {
                $driftByStation[(int)$s['id']] = (int)($total['drifttid_sek'] / $stationCount);
            }
        }

        $schemaSek = $dagCount * 8 * 3600;
        $results = [];

        foreach ($stationer as $s) {
            $sid = (int)$s['id'];
            $namn = $s['namn'];
            $ibc = $ibcByStation[$sid] ?? null;
            $totalIbc = $ibc ? (int)$ibc['total_ibc'] : 0;
            $okIbc    = $ibc ? (int)$ibc['ok_ibc'] : 0;
            $drifttidSek = $driftByStation[$sid] ?? 0;

            $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;
            $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
            $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
            $oee = $tillganglighet * $prestanda * $kvalitet;

            $results[$sid] = [
                'station_id'     => $sid,
                'station_namn'   => $namn,
                'tillganglighet' => round($tillganglighet, 4),
                'prestanda'      => round($prestanda, 4),
                'kvalitet'       => round($kvalitet, 4),
                'oee'            => round($oee, 4),
                'total_ibc'      => $totalIbc,
                'ok_ibc'         => $okIbc,
            ];
        }

        return $results;
    }

    private function trendRiktning(float $current, float $prev): string {
        $diff = $current - $prev;
        if ($diff > 0.005) return 'up';
        if ($diff < -0.005) return 'down';
        return 'stable';
    }

    private function linjarRegression(array $values): array {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0, 'r2' => 0];
        }
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i; $sumY += $values[$i];
            $sumXY += $i * $values[$i]; $sumX2 += $i * $i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if ($denom == 0) {
            return ['slope' => 0, 'intercept' => $sumY / $n, 'r2' => 0];
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;
        $meanY = $sumY / $n;
        $ssTot = 0; $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $i + $intercept;
            $ssTot += ($values[$i] - $meanY) ** 2;
            $ssRes += ($values[$i] - $predicted) ** 2;
        }
        $r2 = $ssTot > 0 ? round(1 - $ssRes / $ssTot, 4) : 0;
        return ['slope' => round($slope, 4), 'intercept' => round($intercept, 4), 'r2' => $r2];
    }

    private function glidandeMedel(array $values, int $fonster): array {
        $result = [];
        $n = count($values);
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $fonster + 1);
            $slice = array_slice($values, $start, $i - $start + 1);
            $result[] = count($slice) > 0 ? round(array_sum($slice) / count($slice), 2) : null;
        }
        return $result;
    }

    // ================================================================
    // run=sammanfattning
    // ================================================================

    private function sammanfattning(): void {
        try {
            $today = date('Y-m-d');

            // OEE idag
            $idagOee = $this->calcOeeForPeriod($today, $today);

            // OEE snitt 7d
            $from7 = date('Y-m-d', strtotime('-6 days'));
            $oee7d = $this->calcOeeForPeriod($from7, $today);

            // OEE snitt 30d
            $from30 = date('Y-m-d', strtotime('-29 days'));
            $oee30d = $this->calcOeeForPeriod($from30, $today);

            // Foregaende 30d for trend
            $prevFrom30 = date('Y-m-d', strtotime('-59 days'));
            $prevTo30   = date('Y-m-d', strtotime('-30 days'));
            $prevOee30d = $this->calcOeeForPeriod($prevFrom30, $prevTo30);

            $trend = $this->trendRiktning($oee30d['oee'], $prevOee30d['oee']);

            // Basta & samsta station senaste 7d
            $perStation = $this->calcOeePerStation($from7, $today);
            $basta = null;
            $samsta = null;
            foreach ($perStation as $s) {
                if ($s['total_ibc'] === 0) continue;
                if ($basta === null || $s['oee'] > $basta['oee']) $basta = $s;
                if ($samsta === null || $s['oee'] < $samsta['oee']) $samsta = $s;
            }

            $this->sendSuccess([
                'oee_idag_pct'  => round($idagOee['oee'] * 100, 1),
                'oee_7d_pct'    => round($oee7d['oee'] * 100, 1),
                'oee_30d_pct'   => round($oee30d['oee'] * 100, 1),
                'basta_station' => $basta ? [
                    'namn'    => $basta['station_namn'],
                    'oee_pct' => round($basta['oee'] * 100, 1),
                ] : null,
                'samsta_station' => $samsta ? [
                    'namn'    => $samsta['station_namn'],
                    'oee_pct' => round($samsta['oee'] * 100, 1),
                ] : null,
                'trend' => $trend,
                'tillganglighet_idag_pct' => round($idagOee['tillganglighet'] * 100, 1),
                'prestanda_idag_pct'      => round($idagOee['prestanda'] * 100, 1),
                'kvalitet_idag_pct'       => round($idagOee['kvalitet'] * 100, 1),
            ]);
        } catch (Exception $e) {
            error_log('OeeTrendanalysController::sammanfattning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=per-station
    // ================================================================

    private function perStation(): void {
        try {
            $days = $this->getDays();
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            $current = $this->calcOeePerStation($from, $to);

            // Foregaende period for delta
            $prevFrom = date('Y-m-d', strtotime("-{$days} days", strtotime($from)));
            $prevTo   = date('Y-m-d', strtotime('-1 day', strtotime($from)));
            $prev     = $this->calcOeePerStation($prevFrom, $prevTo);

            $stationer = [];
            foreach ($current as $sid => $s) {
                $prevS = $prev[$sid] ?? null;
                $prevOee = $prevS ? $prevS['oee'] : 0;
                $delta = round(($s['oee'] - $prevOee) * 100, 1);

                $stationer[] = [
                    'station_id'          => $s['station_id'],
                    'station_namn'        => $s['station_namn'],
                    'oee_pct'             => round($s['oee'] * 100, 1),
                    'tillganglighet_pct'  => round($s['tillganglighet'] * 100, 1),
                    'prestanda_pct'       => round($s['prestanda'] * 100, 1),
                    'kvalitet_pct'        => round($s['kvalitet'] * 100, 1),
                    'total_ibc'           => $s['total_ibc'],
                    'ok_ibc'              => $s['ok_ibc'],
                    'delta_pct'           => $delta,
                    'trend'               => $this->trendRiktning($s['oee'], $prevOee),
                ];
            }

            // Sortera efter OEE (hogst forst)
            usort($stationer, fn($a, $b) => $b['oee_pct'] <=> $a['oee_pct']);

            // Lagg till ranking
            foreach ($stationer as $i => &$s) {
                $s['ranking'] = $i + 1;
            }
            unset($s);

            $this->sendSuccess([
                'stationer' => $stationer,
                'days'      => $days,
                'from_date' => $from,
                'to_date'   => $to,
            ]);
        } catch (Exception $e) {
            error_log('OeeTrendanalysController::perStation: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationsdata', 500);
        }
    }

    // ================================================================
    // run=trend
    // ================================================================

    private function trend(): void {
        try {
            $days    = $this->getDays();
            $station = $this->getStation();

            $trendPoints = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime("-{$i} days"));

                if ($station !== null) {
                    $perStation = $this->calcOeePerStation($dag, $dag);
                    $s = $perStation[$station] ?? null;
                    if ($s) {
                        $trendPoints[] = [
                            'datum'              => $dag,
                            'oee_pct'            => round($s['oee'] * 100, 1),
                            'tillganglighet_pct' => round($s['tillganglighet'] * 100, 1),
                            'prestanda_pct'      => round($s['prestanda'] * 100, 1),
                            'kvalitet_pct'       => round($s['kvalitet'] * 100, 1),
                            'total_ibc'          => $s['total_ibc'],
                        ];
                    } else {
                        $trendPoints[] = ['datum' => $dag, 'oee_pct' => 0, 'tillganglighet_pct' => 0, 'prestanda_pct' => 0, 'kvalitet_pct' => 0, 'total_ibc' => 0];
                    }
                } else {
                    $calc = $this->calcOeeForPeriod($dag, $dag);
                    $trendPoints[] = [
                        'datum'              => $dag,
                        'oee_pct'            => round($calc['oee'] * 100, 1),
                        'tillganglighet_pct' => round($calc['tillganglighet'] * 100, 1),
                        'prestanda_pct'      => round($calc['prestanda'] * 100, 1),
                        'kvalitet_pct'       => round($calc['kvalitet'] * 100, 1),
                        'total_ibc'          => $calc['total_ibc'],
                    ];
                }
            }

            // Rullande 7d-snitt for OEE
            $oeeValues = array_column($trendPoints, 'oee_pct');
            $ma7 = $this->glidandeMedel($oeeValues, 7);

            foreach ($trendPoints as $i => &$tp) {
                $tp['oee_ma7'] = $ma7[$i];
            }
            unset($tp);

            // Summering
            $nonZero = array_filter($oeeValues, fn($v) => $v > 0);
            $avgOee  = !empty($nonZero) ? round(array_sum($nonZero) / count($nonZero), 1) : 0;
            $maxOee  = !empty($oeeValues) ? max($oeeValues) : 0;
            $minOee  = !empty($nonZero) ? min($nonZero) : 0;

            $this->sendSuccess([
                'trend'           => $trendPoints,
                'avg_oee'         => $avgOee,
                'max_oee'         => $maxOee,
                'min_oee'         => $minOee,
                'world_class_pct' => round(self::WORLD_CLASS * 100, 0),
                'typical_pct'     => round(self::TYPICAL * 100, 0),
                'days'            => $days,
                'station'         => $station,
            ]);
        } catch (Exception $e) {
            error_log('OeeTrendanalysController::trend: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta trenddata', 500);
        }
    }

    // ================================================================
    // run=flaskhalsar
    // ================================================================

    private function flaskhalsar(): void {
        try {
            $days = $this->getDays();
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            $perStation = $this->calcOeePerStation($from, $to);

            $flaskhalsar = [];
            foreach ($perStation as $s) {
                if ($s['total_ibc'] === 0) continue;

                // Identifiera svagaste faktor
                $faktorer = [
                    'tillganglighet' => $s['tillganglighet'],
                    'prestanda'      => $s['prestanda'],
                    'kvalitet'       => $s['kvalitet'],
                ];
                asort($faktorer);
                $svagest = array_key_first($faktorer);
                $svagVarde = $faktorer[$svagest];

                $atgard = $this->getAtgardsforslag($svagest, $svagVarde);

                $flaskhalsar[] = [
                    'station_id'   => $s['station_id'],
                    'station_namn' => $s['station_namn'],
                    'oee_pct'      => round($s['oee'] * 100, 1),
                    'orsak'        => $svagest,
                    'orsak_pct'    => round($svagVarde * 100, 1),
                    'atgardsforslag' => $atgard,
                    'tillganglighet_pct' => round($s['tillganglighet'] * 100, 1),
                    'prestanda_pct'      => round($s['prestanda'] * 100, 1),
                    'kvalitet_pct'       => round($s['kvalitet'] * 100, 1),
                ];
            }

            // Sortera: lagst OEE forst
            usort($flaskhalsar, fn($a, $b) => $a['oee_pct'] <=> $b['oee_pct']);

            // Top 5
            $flaskhalsar = array_slice($flaskhalsar, 0, 5);

            // Hamta stopporsaker om mojligt
            $stoppInfo = [];
            try {
                $sql = "
                    SELECT
                        COALESCE(sr.station_id, 1) AS station_id,
                        sk.namn AS orsak,
                        COUNT(*) AS antal
                    FROM stopporsak_registreringar sr
                    LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                    WHERE sr.start_time >= :from
                      AND sr.start_time <= :to
                    GROUP BY COALESCE(sr.station_id, 1), sk.namn
                    ORDER BY antal DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $sid = (int)$row['station_id'];
                    if (!isset($stoppInfo[$sid])) {
                        $stoppInfo[$sid] = $row['orsak'] . ' (' . $row['antal'] . ' ggr)';
                    }
                }
            } catch (Exception) {
                // Tabellen kanske inte finns
            }

            foreach ($flaskhalsar as &$f) {
                $f['stopp_info'] = $stoppInfo[$f['station_id']] ?? null;
            }
            unset($f);

            $this->sendSuccess([
                'flaskhalsar' => $flaskhalsar,
                'days'        => $days,
                'from_date'   => $from,
                'to_date'     => $to,
            ]);
        } catch (Exception $e) {
            error_log('OeeTrendanalysController::flaskhalsar: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta flaskhalsar', 500);
        }
    }

    private function getAtgardsforslag(string $faktor, float $varde): string {
        if ($faktor === 'tillganglighet') {
            if ($varde < 0.5) return 'Kritisk nedtid. Granska stopporsaker och implementera forebyggande underhall omgaende.';
            return 'Minska oplanerade stopp. Analysera de vanligaste stopporsakerna och prioritera atgarder.';
        } elseif ($faktor === 'prestanda') {
            if ($varde < 0.5) return 'Mycket lag prestanda. Kontrollera ideal cykeltid, utbilda operatorer och identifiera flaskhalsar i floden.';
            return 'Optimera cykeltider. Undersok om det finns vantepunkter eller materialbrister som bromsar takten.';
        } else {
            if ($varde < 0.9) return 'Hog kassationsandel. Genomfor rotorsaksanalys och kontrollera maskininstallningar och material.';
            return 'Forbattra kvalitetskontrollen. Kontrollera slangar, munstycken och tvattmedelskoncentration.';
        }
    }

    // ================================================================
    // run=jamforelse
    // ================================================================

    private function jamforelse(): void {
        try {
            $days = $this->getDays();

            // Period 1 (aktuell)
            $from1 = $_GET['from1'] ?? date('Y-m-d', strtotime("-{$days} days"));
            $to1   = $_GET['to1']   ?? date('Y-m-d');

            // Period 2 (foregaende)
            $from2 = $_GET['from2'] ?? date('Y-m-d', strtotime("-{$days} days", strtotime($from1)));
            $to2   = $_GET['to2']   ?? date('Y-m-d', strtotime('-1 day', strtotime($from1)));

            $period1 = $this->calcOeePerStation($from1, $to1);
            $period2 = $this->calcOeePerStation($from2, $to2);

            $total1 = $this->calcOeeForPeriod($from1, $to1);
            $total2 = $this->calcOeeForPeriod($from2, $to2);

            $stationer = [];
            foreach ($period1 as $sid => $s1) {
                $s2 = $period2[$sid] ?? null;
                $oee1 = round($s1['oee'] * 100, 1);
                $oee2 = $s2 ? round($s2['oee'] * 100, 1) : 0;
                $delta = round($oee1 - $oee2, 1);

                $stationer[] = [
                    'station_id'   => $s1['station_id'],
                    'station_namn' => $s1['station_namn'],
                    'period1_oee'  => $oee1,
                    'period2_oee'  => $oee2,
                    'delta'        => $delta,
                    'forbattrad'   => $delta > 0.5,
                    'forsamrad'    => $delta < -0.5,
                    'period1_t'    => round($s1['tillganglighet'] * 100, 1),
                    'period1_p'    => round($s1['prestanda'] * 100, 1),
                    'period1_k'    => round($s1['kvalitet'] * 100, 1),
                    'period2_t'    => $s2 ? round($s2['tillganglighet'] * 100, 1) : 0,
                    'period2_p'    => $s2 ? round($s2['prestanda'] * 100, 1) : 0,
                    'period2_k'    => $s2 ? round($s2['kvalitet'] * 100, 1) : 0,
                ];
            }

            // Sortera efter delta (storst forbattring forst)
            usort($stationer, fn($a, $b) => $b['delta'] <=> $a['delta']);

            $this->sendSuccess([
                'stationer'  => $stationer,
                'period1'    => ['from' => $from1, 'to' => $to1, 'oee_pct' => round($total1['oee'] * 100, 1)],
                'period2'    => ['from' => $from2, 'to' => $to2, 'oee_pct' => round($total2['oee'] * 100, 1)],
                'total_delta' => round(($total1['oee'] - $total2['oee']) * 100, 1),
            ]);
        } catch (Exception $e) {
            error_log('OeeTrendanalysController::jamforelse: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta jamforelsedata', 500);
        }
    }

    // ================================================================
    // run=prediktion
    // ================================================================

    private function prediktion(): void {
        try {
            $days = 30;

            $oeeValues = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime("-{$i} days"));
                $calc = $this->calcOeeForPeriod($dag, $dag);
                $oeeValues[] = round($calc['oee'] * 100, 1);
            }

            $reg = $this->linjarRegression($oeeValues);
            $n = count($oeeValues);

            // Prediktion kommande 7 dagar
            $prediktion = [];
            for ($d = 1; $d <= 7; $d++) {
                $idx = $n - 1 + $d;
                $datum = date('Y-m-d', strtotime('+' . $d . ' days'));
                $predicted = round($reg['slope'] * $idx + $reg['intercept'], 1);
                $predicted = max(0, min(100, $predicted));
                $prediktion[] = [
                    'datum'   => $datum,
                    'oee_pct' => $predicted,
                ];
            }

            // Historisk data med datum
            $historisk = [];
            for ($i = 0; $i < $n; $i++) {
                $dag = date('Y-m-d', strtotime('-' . ($days - 1 - $i) . ' days'));
                $historisk[] = [
                    'datum'   => $dag,
                    'oee_pct' => $oeeValues[$i],
                ];
            }

            // Rullande 7d-snitt
            $ma7 = $this->glidandeMedel($oeeValues, 7);
            foreach ($historisk as $i => &$h) {
                $h['oee_ma7'] = $ma7[$i];
            }
            unset($h);

            $trendDir = 'stable';
            if ($reg['slope'] > 0.1) $trendDir = 'up';
            elseif ($reg['slope'] < -0.1) $trendDir = 'down';

            $this->sendSuccess([
                'historisk'   => $historisk,
                'prediktion'  => $prediktion,
                'slope'       => $reg['slope'],
                'intercept'   => $reg['intercept'],
                'r2'          => $reg['r2'],
                'trend'       => $trendDir,
                'medel_30d'   => count($oeeValues) > 0 ? round(array_sum($oeeValues) / count($oeeValues), 1) : 0,
            ]);
        } catch (Exception $e) {
            error_log('OeeTrendanalysController::prediktion: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta prediktionsdata', 500);
        }
    }
}
