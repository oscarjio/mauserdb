<?php
/**
 * SkiftjamforelseController.php
 * Skiftjamforelse-rapport — jamfor FM/EM/Natt-skift med normaliserade KPI:er.
 *
 * Endpoints via ?action=skiftjamforelse&run=XXX:
 *   run=sammanfattning   — KPI-kort: mest produktiva skiftet, snitt-OEE per skift, trend, antal skift
 *   run=jamforelse       — FM vs EM vs Natt tabell + radardata (5 axlar)
 *   run=trend            — OEE per skift per dag senaste 30d
 *   run=best-practices   — identifiera styrkor per skift och station
 *   run=detaljer         — detaljlista alla skift
 *
 * Skiftdefinition:
 *   FM   = 06:00-14:00
 *   EM   = 14:00-22:00
 *   Natt = 22:00-06:00
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_stationer,
 *           stopporsak_registreringar, operators
 */
class SkiftjamforelseController {
    private $pdo;

    private const SKIFT = [
        'FM'   => ['label' => 'FM-skiftet',    'start' => 6,  'end' => 14],
        'EM'   => ['label' => 'EM-skiftet',    'start' => 14, 'end' => 22],
        'Natt' => ['label' => 'Nattskiftet',   'start' => 22, 'end' => 6],
    ];

    private const TEORIETISK_MAX_IBC_H = 60.0;
    private const PLANERAD_MIN = 480;

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
            case 'sammanfattning':  $this->sammanfattning();  break;
            case 'jamforelse':      $this->jamforelse();      break;
            case 'trend':           $this->trend();           break;
            case 'best-practices':  $this->bestPractices();   break;
            case 'detaljer':        $this->detaljer();        break;
            // Backward-compat
            case 'shift-comparison': $this->jamforelse();     break;
            case 'shift-trend':      $this->trend();          break;
            case 'shift-operators':  $this->bestPractices();  break;
            default:
                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HELPERS
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

    private function getDays(): int {
        $p = (int)($_GET['days'] ?? $_GET['period'] ?? 30);
        if (in_array($p, [7, 30, 90], true)) return $p;
        return max(1, min(365, $p));
    }

    private function skiftForHour(int $hour): string {
        if ($hour >= 6 && $hour < 14) return 'FM';
        if ($hour >= 14 && $hour < 22) return 'EM';
        return 'Natt';
    }

    /**
     * Hamta produktionsdata per skift for en period.
     */
    private function getProduktionPerSkift(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                skift,
                COUNT(DISTINCT skiftraknare) AS antal_pass,
                COALESCE(SUM(delta_ok),    0) AS ibc_ok,
                COALESCE(SUM(delta_ej_ok), 0) AS ibc_ej_ok,
                COALESCE(SUM(max_runtime), 0) AS runtime_min
             FROM (
                SELECT skiftraknare, skift, max_runtime,
                    GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ok,
                    GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ej_ok
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                        CASE WHEN HOUR(MAX(datum)) >= 6  AND HOUR(MAX(datum)) < 14 THEN 'FM'
                             WHEN HOUR(MAX(datum)) >= 14 AND HOUR(MAX(datum)) < 22 THEN 'EM'
                             ELSE 'Natt' END AS skift,
                        MAX(ibc_ok)      AS ibc_end,
                        MAX(ibc_ej_ok)   AS ej_end,
                        MAX(runtime_plc) AS max_runtime
                    FROM rebotling_ibc
                    WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                    HAVING COUNT(*) > 1
                ) shifts
             ) deltas
             GROUP BY skift"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Map resultat per skift
        $dbData = [];
        foreach ($rows as $row) {
            $dbData[$row['skift']] = $row;
        }

        $result = [];
        foreach (array_keys(self::SKIFT) as $skift) {
            $row = $dbData[$skift] ?? null;

            $ibcOk    = (int)($row['ibc_ok']    ?? 0);
            $ibcEjOk  = (int)($row['ibc_ej_ok'] ?? 0);
            $runtime  = (int)($row['runtime_min'] ?? 0);
            $antalPass= (int)($row['antal_pass'] ?? 0);
            $ibcTotal = $ibcOk + $ibcEjOk;

            $ibcPerH   = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;
            $kvalitet  = $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;

            // OEE
            $planMinTotal   = $antalPass * self::PLANERAD_MIN;
            $tillganglighet = $planMinTotal > 0 ? min(1.0, $runtime / $planMinTotal) : 0.0;
            $prestanda      = $runtime > 0
                ? min(1.0, ($ibcPerH / self::TEORIETISK_MAX_IBC_H))
                : 0.0;
            $kvalFaktor     = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
            $oee = $tillganglighet * $prestanda * $kvalFaktor;

            // Genomsnittlig cykeltid
            $avgCykelSek = ($ibcOk > 0 && $runtime > 0) ? round(($runtime * 60) / $ibcOk, 1) : 0;

            $result[$skift] = [
                'skift'              => $skift,
                'label'              => self::SKIFT[$skift]['label'],
                'antal_pass'         => $antalPass,
                'ibc_ok'             => $ibcOk,
                'ibc_ej_ok'          => $ibcEjOk,
                'ibc_total'          => $ibcTotal,
                'runtime_min'        => $runtime,
                'ibc_per_h'          => $ibcPerH,
                'kvalitet_pct'       => $kvalitet,
                'oee_pct'            => round($oee * 100, 1),
                'tillganglighet_pct' => round($tillganglighet * 100, 1),
                'prestanda_pct'      => round($prestanda * 100, 1),
                'avg_cykeltid_sek'   => $avgCykelSek,
            ];
        }

        return $result;
    }

    private function getStopptidPerSkift(string $fromDate, string $toDate): array {
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if (!$check || $check->rowCount() === 0) {
                return ['FM' => 0, 'EM' => 0, 'Natt' => 0];
            }
        } catch (\PDOException $e) {
            error_log('SkiftjamforelseController::getStopptidPerSkift: ' . $e->getMessage());
            return ['FM' => 0, 'EM' => 0, 'Natt' => 0];
        }

        // Optimerad: en enda query for alla skift (istallet for 3 separata)
        $stmt = $this->pdo->prepare(
            "SELECT
                CASE
                    WHEN HOUR(start_time) >= 6 AND HOUR(start_time) < 14 THEN 'FM'
                    WHEN HOUR(start_time) >= 14 AND HOUR(start_time) < 22 THEN 'EM'
                    ELSE 'Natt'
                END AS skift,
                COALESCE(ROUND(SUM(
                    TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))
                ), 0), 0) AS total_min
             FROM stopporsak_registreringar
             WHERE linje = 'rebotling'
               AND start_time >= ? AND start_time < DATE_ADD(?, INTERVAL 1 DAY)
             GROUP BY skift"
        );
        $stmt->execute([$fromDate, $toDate]);

        $stoppResult = ['FM' => 0, 'EM' => 0, 'Natt' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $stoppResult[$row['skift']] = (int)$row['total_min'];
        }

        return $stoppResult;
    }

    private function getStationer(): array {
        try {
            $stmt = $this->pdo->query("SELECT id, namn FROM maskin_register WHERE aktiv = 1 ORDER BY id");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) return $rows;
        } catch (\Throwable $e) {
            error_log('SkiftjamforelseController::getStationer: ' . $e->getMessage());
        }

        return [
            ['id' => 1, 'namn' => 'Station 1'],
            ['id' => 2, 'namn' => 'Station 2'],
            ['id' => 3, 'namn' => 'Station 3'],
            ['id' => 4, 'namn' => 'Station 4'],
            ['id' => 5, 'namn' => 'Station 5'],
        ];
    }

    // ================================================================
    // run=sammanfattning
    // ================================================================

    private function sammanfattning(): void {
        try {
            $days    = $this->getDays();
            $today   = date('Y-m-d');
            $from    = date('Y-m-d', strtotime("-{$days} days"));

            // Dagens data
            $dagensData = $this->getProduktionPerSkift($today, $today);

            // Periodens data
            $periodData = $this->getProduktionPerSkift($from, $today);
            $stopp      = $this->getStopptidPerSkift($from, $today);
            foreach (array_keys(self::SKIFT) as $s) {
                $periodData[$s]['stopptid_min'] = $stopp[$s] ?? 0;
            }

            // Mest produktiva idag
            $mestProduktiv = null;
            $maxIbcH = -1;
            foreach ($dagensData as $skift => $d) {
                if ($d['ibc_per_h'] > $maxIbcH) {
                    $maxIbcH = $d['ibc_per_h'];
                    $mestProduktiv = $skift;
                }
            }

            // Snitt OEE per skift
            $snittOee = [];
            foreach ($periodData as $skift => $d) {
                $snittOee[$skift] = $d['oee_pct'];
            }

            // Trend: jamfor period mot foregaende period
            $prevFrom = date('Y-m-d', strtotime("-{$days} days", strtotime($from)));
            $prevTo   = date('Y-m-d', strtotime('-1 day', strtotime($from)));
            $prevData = $this->getProduktionPerSkift($prevFrom, $prevTo);

            $mestForbattrad = null;
            $maxDelta = -999;
            foreach ($periodData as $skift => $d) {
                $prevOee = $prevData[$skift]['oee_pct'] ?? 0;
                $delta = $d['oee_pct'] - $prevOee;
                if ($delta > $maxDelta) {
                    $maxDelta = $delta;
                    $mestForbattrad = $skift;
                }
            }

            // Totalt antal skift i perioden
            $totalSkift = 0;
            foreach ($periodData as $d) {
                $totalSkift += $d['antal_pass'];
            }

            $this->sendSuccess([
                'mest_produktiva_idag' => $mestProduktiv ? [
                    'skift'    => $mestProduktiv,
                    'label'    => self::SKIFT[$mestProduktiv]['label'],
                    'ibc_per_h' => $dagensData[$mestProduktiv]['ibc_per_h'],
                ] : null,
                'snitt_oee' => $snittOee,
                'mest_forbattrad' => $mestForbattrad ? [
                    'skift' => $mestForbattrad,
                    'label' => self::SKIFT[$mestForbattrad]['label'],
                    'delta' => round($maxDelta, 1),
                ] : null,
                'antal_skift'  => $totalSkift,
                'days'         => $days,
                'from_date'    => $from,
                'to_date'      => $today,
            ]);

        } catch (\Throwable $e) {
            error_log('SkiftjamforelseController::sammanfattning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=jamforelse
    // ================================================================

    private function jamforelse(): void {
        try {
            $days    = $this->getDays();
            $today   = date('Y-m-d');
            $from    = date('Y-m-d', strtotime("-{$days} days"));

            $prod  = $this->getProduktionPerSkift($from, $today);
            $stopp = $this->getStopptidPerSkift($from, $today);

            foreach (array_keys(self::SKIFT) as $s) {
                $prod[$s]['stopptid_min'] = $stopp[$s] ?? 0;
            }

            // Berakna radardata (5 axlar: Tillganglighet, Prestanda, Kvalitet, Volym, Stabilitet)
            // Volym = normaliserad IBC/h (max = 100)
            $maxIbcH = max(1, max(array_column($prod, 'ibc_per_h')));

            // Stabilitet: berakna per dag-variation for varje skift via en enda query
            $stabilitet = [];
            $stabStmt = $this->pdo->prepare(
                "SELECT dag, skift,
                    COUNT(DISTINCT skiftraknare) AS antal_pass,
                    COALESCE(SUM(delta_ok),    0) AS ibc_ok,
                    COALESCE(SUM(delta_ej_ok), 0) AS ibc_ej_ok,
                    COALESCE(SUM(max_runtime), 0) AS runtime_min
                 FROM (
                    SELECT dag, skiftraknare, skift, max_runtime,
                        GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ok,
                        GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ej_ok
                    FROM (
                        SELECT DATE(datum) AS dag, skiftraknare,
                            CASE WHEN HOUR(MAX(datum)) >= 6  AND HOUR(MAX(datum)) < 14 THEN 'FM'
                                 WHEN HOUR(MAX(datum)) >= 14 AND HOUR(MAX(datum)) < 22 THEN 'EM'
                                 ELSE 'Natt' END AS skift,
                            MAX(ibc_ok)      AS ibc_end,
                            MAX(ibc_ej_ok)   AS ej_end,
                            MAX(runtime_plc) AS max_runtime
                        FROM rebotling_ibc
                        WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                        HAVING COUNT(*) > 1
                    ) shifts
                 ) deltas
                 GROUP BY dag, skift"
            );
            $stabStmt->execute([$from, $today]);
            $stabRows = $stabStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg daglig OEE per skift
            $dagOeeMap = [];
            foreach ($stabRows as $sr) {
                $skiftKey = $sr['skift'];
                $ibcOk    = (int)$sr['ibc_ok'];
                $ibcEjOk  = (int)$sr['ibc_ej_ok'];
                $runtime  = (int)$sr['runtime_min'];
                $antalPass = (int)$sr['antal_pass'];
                $ibcTotal = $ibcOk + $ibcEjOk;
                $ibcPerH  = $runtime > 0 ? $ibcOk / ($runtime / 60) : 0.0;
                $planMin  = $antalPass * self::PLANERAD_MIN;
                $tillg    = $planMin > 0 ? min(1.0, $runtime / $planMin) : 0.0;
                $prest    = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
                $kvalFakt = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
                $oee      = round($tillg * $prest * $kvalFakt * 100, 1);
                if ($ibcOk > 0) {
                    $dagOeeMap[$skiftKey][] = $oee;
                }
            }

            foreach (array_keys(self::SKIFT) as $skift) {
                $dagOee = $dagOeeMap[$skift] ?? [];
                if (count($dagOee) >= 2) {
                    $mean = array_sum($dagOee) / count($dagOee);
                    $variance = 0;
                    foreach ($dagOee as $v) {
                        $variance += ($v - $mean) ** 2;
                    }
                    $variance /= count($dagOee);
                    $stddev = sqrt($variance);
                    $stabilitet[$skift] = max(0, min(100, round(100 - $stddev * 2, 1)));
                } else {
                    $stabilitet[$skift] = 50;
                }
            }

            $radarData = [];
            foreach ($prod as $skift => $d) {
                $radarData[$skift] = [
                    'tillganglighet' => $d['tillganglighet_pct'],
                    'prestanda'      => $d['prestanda_pct'],
                    'kvalitet'       => $d['kvalitet_pct'],
                    'volym'          => round(($d['ibc_per_h'] / $maxIbcH) * 100, 1),
                    'stabilitet'     => $stabilitet[$skift],
                ];
            }

            $this->sendSuccess([
                'skift'      => array_values($prod),
                'radar'      => $radarData,
                'days'       => $days,
                'from_date'  => $from,
                'to_date'    => $today,
            ]);

        } catch (\Throwable $e) {
            error_log('SkiftjamforelseController::jamforelse: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta jamforelsedata', 500);
        }
    }

    // ================================================================
    // run=trend
    // ================================================================

    private function trend(): void {
        try {
            $days  = $this->getDays();
            $today = date('Y-m-d');
            $from  = date('Y-m-d', strtotime("-{$days} days"));

            $stmt = $this->pdo->prepare(
                "SELECT dag, skift,
                    COUNT(DISTINCT skiftraknare) AS antal_pass,
                    COALESCE(SUM(delta_ok),    0) AS ibc_ok,
                    COALESCE(SUM(delta_ej_ok), 0) AS ibc_ej_ok,
                    COALESCE(SUM(max_runtime), 0) AS runtime_min
                 FROM (
                    SELECT dag, skiftraknare, skift, max_runtime,
                        GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ok,
                        GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ej_ok
                    FROM (
                        SELECT DATE(datum) AS dag, skiftraknare,
                            CASE WHEN HOUR(MAX(datum)) >= 6  AND HOUR(MAX(datum)) < 14 THEN 'FM'
                                 WHEN HOUR(MAX(datum)) >= 14 AND HOUR(MAX(datum)) < 22 THEN 'EM'
                                 ELSE 'Natt' END AS skift,
                            MAX(ibc_ok)      AS ibc_end,
                            MAX(ibc_ej_ok)   AS ej_end,
                            MAX(runtime_plc) AS max_runtime
                        FROM rebotling_ibc
                        WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                        HAVING COUNT(*) > 1
                    ) shifts
                 ) deltas
                 GROUP BY dag, skift
                 ORDER BY dag ASC, skift ASC"
            );
            $stmt->execute([$from, $today]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg en map: dag -> skift -> oee_pct
            $dagMap = [];
            foreach ($rows as $row) {
                $dag    = $row['dag'];
                $skift  = $row['skift'];
                $ibcOk  = (int)$row['ibc_ok'];
                $ibcEjOk = (int)$row['ibc_ej_ok'];
                $runtime = (int)$row['runtime_min'];
                $antalPass = (int)$row['antal_pass'];
                $ibcTotal = $ibcOk + $ibcEjOk;

                $ibcPerH   = $runtime > 0 ? $ibcOk / ($runtime / 60) : 0.0;
                $planMin   = $antalPass * self::PLANERAD_MIN;
                $tillg     = $planMin > 0 ? min(1.0, $runtime / $planMin) : 0.0;
                $prest     = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
                $kvalFakt  = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
                $oee       = round($tillg * $prest * $kvalFakt * 100, 1);

                $dagMap[$dag][$skift] = $ibcOk > 0 ? $oee : null;
            }

            // Bygg trendpunkter for alla dagar
            $trendPoints = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime("-{$i} days"));
                $point = ['datum' => $dag];
                foreach (array_keys(self::SKIFT) as $skift) {
                    $point[$skift] = $dagMap[$dag][$skift] ?? null;
                }
                $trendPoints[] = $point;
            }

            $this->sendSuccess([
                'trend' => $trendPoints,
                'days'  => $days,
            ]);

        } catch (\Throwable $e) {
            error_log('SkiftjamforelseController::trend: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta trenddata', 500);
        }
    }

    // ================================================================
    // run=best-practices
    // ================================================================

    private function bestPractices(): void {
        try {
            $days    = $this->getDays();
            $today   = date('Y-m-d');
            $from    = date('Y-m-d', strtotime("-{$days} days"));

            $stationer = $this->getStationer();
            $stationMap = [];
            foreach ($stationer as $st) {
                $stationMap[(int)$st['id']] = $st['namn'];
            }
            $practices = [];

            // Hamta alla stationer och skift i en enda query (istallet for N+1)
            // Berakna skift via HOUR(datum) i SQL med CASE
            // rebotling_ibc har ingen station_id-kolumn — hardkoda till 1
            $stmt = $this->pdo->prepare(
                "SELECT
                    1 AS sid,
                    skift,
                    COUNT(DISTINCT skiftraknare) AS antal_pass,
                    COALESCE(SUM(delta_ok),    0) AS ibc_ok,
                    COALESCE(SUM(delta_ej_ok), 0) AS ibc_ej_ok,
                    COALESCE(SUM(max_runtime), 0) AS runtime_min
                 FROM (
                    SELECT skiftraknare, skift, max_runtime,
                        GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ok,
                        GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ej_ok
                    FROM (
                        SELECT DATE(datum) AS dag, skiftraknare,
                            CASE WHEN HOUR(MAX(datum)) >= 6  AND HOUR(MAX(datum)) < 14 THEN 'FM'
                                 WHEN HOUR(MAX(datum)) >= 14 AND HOUR(MAX(datum)) < 22 THEN 'EM'
                                 ELSE 'Natt' END AS skift,
                            MAX(ibc_ok)      AS ibc_end,
                            MAX(ibc_ej_ok)   AS ej_end,
                            MAX(runtime_plc) AS max_runtime
                        FROM rebotling_ibc
                        WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                        HAVING COUNT(*) > 1
                    ) shifts
                 ) deltas
                 GROUP BY skift"
            );
            $stmt->execute([$from, $today]);
            $allStationSkift = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $key = $row['skift'] . '_' . $row['sid'];
                $allStationSkift[$key] = $row;
            }

            // Hamta stopptid och produktion en gang (utanfor loopen)
            $stoppData = $this->getStopptidPerSkift($from, $today);
            $prodData  = $this->getProduktionPerSkift($from, $today);

            // For varje skift, hitta basta station
            foreach (array_keys(self::SKIFT) as $skift) {
                $skiftLabel = self::SKIFT[$skift]['label'];

                $bastaStation = null;
                $bastaOee = -1;

                foreach ($stationer as $st) {
                    $sid = (int)$st['id'];
                    $sNamn = $st['namn'];

                    $key = $skift . '_' . $sid;
                    $row = $allStationSkift[$key] ?? ['ibc_ok' => 0, 'ibc_ej_ok' => 0, 'runtime_min' => 0, 'antal_pass' => 0];

                    $ibcOk    = (int)($row['ibc_ok']    ?? 0);
                    $ibcEjOk  = (int)($row['ibc_ej_ok'] ?? 0);
                    $runtime  = (int)($row['runtime_min'] ?? 0);
                    $antalPass= (int)($row['antal_pass'] ?? 0);
                    $ibcTotal = $ibcOk + $ibcEjOk;
                    $ibcPerH  = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;

                    $planMin  = $antalPass * self::PLANERAD_MIN;
                    $tillg    = $planMin > 0 ? min(1.0, $runtime / $planMin) : 0.0;
                    $prest    = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
                    $kvalFakt = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
                    $oee      = round($tillg * $prest * $kvalFakt * 100, 1);

                    if ($ibcOk > 0 && $oee > $bastaOee) {
                        $bastaOee = $oee;
                        $bastaStation = $sNamn;
                    }
                }

                $stoppMin  = $stoppData[$skift] ?? 0;

                $skiftData = $prodData[$skift];

                $insights = [];
                if ($bastaStation && $bastaOee > 0) {
                    $insights[] = "Bast pa {$bastaStation} (OEE {$bastaOee}%)";
                }
                if ($skiftData['kvalitet_pct'] >= 98) {
                    $insights[] = "Utmarkt kvalitet ({$skiftData['kvalitet_pct']}%)";
                }
                if ($stoppMin < 30) {
                    $insights[] = "Lag stopptid ({$stoppMin} min)";
                }
                if ($skiftData['ibc_per_h'] > 0) {
                    $insights[] = "{$skiftData['ibc_per_h']} IBC/h";
                }

                $practices[] = [
                    'skift'          => $skift,
                    'label'          => $skiftLabel,
                    'oee_pct'        => $skiftData['oee_pct'],
                    'ibc_per_h'      => $skiftData['ibc_per_h'],
                    'kvalitet_pct'   => $skiftData['kvalitet_pct'],
                    'stopptid_min'   => $stoppMin,
                    'basta_station'  => $bastaStation,
                    'basta_station_oee' => $bastaOee > 0 ? $bastaOee : null,
                    'insights'       => $insights,
                ];
            }

            // Sortera efter OEE (hogst forst)
            usort($practices, fn($a, $b) => $b['oee_pct'] <=> $a['oee_pct']);

            $this->sendSuccess([
                'practices' => $practices,
                'days'      => $days,
                'from_date' => $from,
                'to_date'   => $today,
            ]);

        } catch (\Throwable $e) {
            error_log('SkiftjamforelseController::bestPractices: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta best practices', 500);
        }
    }

    // ================================================================
    // run=detaljer
    // ================================================================

    private function detaljer(): void {
        try {
            $days    = $this->getDays();
            $today   = date('Y-m-d');
            $from    = date('Y-m-d', strtotime("-{$days} days"));

            // Hamta per skiftraknare: datum, skifttyp, station, operator, IBC, OEE, stopptid
            $stmt = $this->pdo->prepare(
                "SELECT
                    skiftraknare,
                    DATE(MIN(datum)) AS datum,
                    MIN(HOUR(datum)) AS forsta_timme,
                    1 AS station_id,
                    MAX(ibc_ok)      AS ibc_ok,
                    MAX(ibc_ej_ok)   AS ibc_ej_ok,
                    MAX(runtime_plc) AS runtime_min,
                    MIN(NULLIF(op1, 0)) AS op_num
                 FROM rebotling_ibc
                 WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY skiftraknare
                 HAVING COUNT(*) > 1
                 ORDER BY MIN(datum) DESC
                 LIMIT 500"
            );
            $stmt->execute([$from, $today]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Hamta stationsnamn
            $stationer = $this->getStationer();
            $stNamn = [];
            foreach ($stationer as $s) {
                $stNamn[(int)$s['id']] = $s['namn'];
            }

            // Hamta operatorsnamn
            $opNums = array_unique(array_filter(array_column($rows, 'op_num')));
            $names = [];
            if (!empty($opNums)) {
                $ph = implode(',', array_fill(0, count($opNums), '?'));
                $nameStmt = $this->pdo->prepare(
                    "SELECT number, name FROM operators WHERE number IN ({$ph})"
                );
                $nameStmt->execute(array_values($opNums));
                foreach ($nameStmt->fetchAll(\PDO::FETCH_ASSOC) as $nr) {
                    $names[(int)$nr['number']] = $nr['name'];
                }
            }

            $detaljer = [];
            foreach ($rows as $row) {
                $ibcOk    = (int)($row['ibc_ok'] ?? 0);
                $ibcEjOk  = (int)($row['ibc_ej_ok'] ?? 0);
                $ibcTotal = $ibcOk + $ibcEjOk;
                $runtime  = (int)($row['runtime_min'] ?? 0);
                $ibcPerH  = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;

                $tillg    = self::PLANERAD_MIN > 0 ? min(1.0, $runtime / self::PLANERAD_MIN) : 0.0;
                $prest    = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
                $kvalFakt = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
                $oee      = round($tillg * $prest * $kvalFakt * 100, 1);

                $hour  = (int)$row['forsta_timme'];
                $skift = $this->skiftForHour($hour);
                $opNum = $row['op_num'] ? (int)$row['op_num'] : null;
                $sid   = (int)$row['station_id'];

                $detaljer[] = [
                    'datum'        => $row['datum'],
                    'skift'        => $skift,
                    'skift_label'  => self::SKIFT[$skift]['label'],
                    'station'      => $stNamn[$sid] ?? "Station {$sid}",
                    'operator'     => $opNum ? ($names[$opNum] ?? "Op #{$opNum}") : '-',
                    'ibc_ok'       => $ibcOk,
                    'ibc_total'    => $ibcTotal,
                    'oee_pct'      => $oee,
                    'stopptid_min' => max(0, self::PLANERAD_MIN - $runtime),
                    'runtime_min'  => $runtime,
                ];
            }

            $this->sendSuccess([
                'detaljer'  => $detaljer,
                'days'      => $days,
                'from_date' => $from,
                'to_date'   => $today,
                'total'     => count($detaljer),
            ]);

        } catch (\Throwable $e) {
            error_log('SkiftjamforelseController::detaljer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta detaljer', 500);
        }
    }
}
