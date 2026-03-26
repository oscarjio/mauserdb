<?php
/**
 * SkiftrapportExportController.php
 * Hämtar komplett skiftdata för PDF-export av daglig skiftsammanfattning.
 *
 * Kräver inloggning (session_id).
 *
 * Endpoints via ?action=skiftrapport-export&run=XXX:
 *   - run=report-data&date=YYYY-MM-DD
 *       Produktionstal, cykeltider, drifttid/stopptid, OEE-approximation,
 *       top-operatörer, trender mot förra veckan.
 *   - run=multi-day&start=YYYY-MM-DD&end=YYYY-MM-DD
 *       Sammanfattning per dag för vecko-/månadsrapport.
 *
 * Tabeller: rebotling_ibc, operators
 */
class SkiftrapportExportController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning krävs för att hämta skiftrapport', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'report-data': $this->getReportData(); break;
            case 'multi-day':   $this->getMultiDay();   break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
                break;
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

    private function validateDate(string $date): bool {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    // ================================================================
    // run=report-data&date=YYYY-MM-DD
    // ================================================================

    private function getReportData(): void {
        $date = trim($_GET['date'] ?? '');

        if (empty($date)) {
            // Default: igår
            $date = date('Y-m-d', strtotime('-1 day'));
        }

        if (!$this->validateDate($date)) {
            $this->sendError('Ogiltigt datumformat. Använd YYYY-MM-DD.');
            return;
        }

        try {
            // ---- Produktionsdata för valt datum ----
            $stmt = $this->pdo->prepare(
                "SELECT
                    skiftraknare,
                    MAX(ibc_ok)        AS ibc_ok,
                    MAX(ibc_ej_ok)     AS ibc_ej_ok,
                    MAX(bur_ej_ok)     AS bur_ej_ok,
                    MAX(runtime_plc)   AS runtime_plc,
                    MAX(rasttime)      AS rasttime,
                    MIN(TIME(datum))   AS skift_start,
                    MAX(TIME(datum))   AS skift_slut,
                    DATE(MIN(datum)) AS skift_datum
                 FROM rebotling_ibc
                 WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY skiftraknare
                 HAVING COUNT(*) > 1
                 ORDER BY skiftraknare ASC"
            );
            $stmt->execute([$date, $date]);
            $skiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($skiftRows)) {
                $this->sendSuccess([
                    'datum'          => $date,
                    'har_data'       => false,
                    'produktion'     => null,
                    'cykeltider'     => null,
                    'drifttid'       => null,
                    'oee'            => null,
                    'operatorer'     => [],
                    'trender'        => null,
                    'skiften'        => [],
                ]);
                return;
            }

            // Summera alla skift på dagen
            $totalIbcOk    = 0;
            $totalIbcEjOk  = 0;
            $totalBurEjOk  = 0;
            $totalRuntime  = 0;
            $totalRast     = 0;
            $skiftStart    = null;
            $skiftSlut     = null;
            $skiftLista    = [];

            foreach ($skiftRows as $r) {
                $ibcOk   = (int)$r['ibc_ok'];
                $ibcEjOk = (int)$r['ibc_ej_ok'];
                $runtime = (int)$r['runtime_plc'];
                $rast    = (int)$r['rasttime'];

                $totalIbcOk   += $ibcOk;
                $totalIbcEjOk += $ibcEjOk;
                $totalBurEjOk += (int)$r['bur_ej_ok'];
                $totalRuntime += $runtime;
                $totalRast    += $rast;

                if ($skiftStart === null || $r['skift_start'] < $skiftStart) {
                    $skiftStart = $r['skift_start'];
                }
                if ($skiftSlut === null || $r['skift_slut'] > $skiftSlut) {
                    $skiftSlut = $r['skift_slut'];
                }

                $ibcTotalSkift = $ibcOk + $ibcEjOk;
                $skiftKvalitet = $ibcTotalSkift > 0
                    ? round(($ibcOk / $ibcTotalSkift) * 100, 1) : 0.0;
                $skiftIbcPerH = $runtime > 0
                    ? round($ibcOk / ($runtime / 60), 1) : 0.0;
                // Löpnummer för detta skift
                $lopnummer = $this->fetchLopnummer((int)$r['skiftraknare']);
                $lopnummerRange = $this->buildRanges($lopnummer);

                $skiftLista[] = [
                    'skiftraknare'    => (int)$r['skiftraknare'],
                    'skift_datum'     => $r['skift_datum'],
                    'skift_start'     => $r['skift_start'],
                    'skift_slut'      => $r['skift_slut'],
                    'ibc_ok'          => $ibcOk,
                    'ibc_ej_ok'       => $ibcEjOk,
                    'runtime_min'     => $runtime,
                    'kvalitet_pct'    => $skiftKvalitet,
                    'ibc_per_timme'   => $skiftIbcPerH,
                    'lopnummer_range' => $lopnummerRange,
                    'lopnummer_count' => count($lopnummer),
                ];
            }

            $ibcTotal  = $totalIbcOk + $totalIbcEjOk;
            $kvalitet  = $ibcTotal > 0
                ? round(($totalIbcOk / $ibcTotal) * 100, 1) : 0.0;
            $skiftMin  = count($skiftRows) * 480; // 8h per skift
            $stopptid  = max(0, $skiftMin - $totalRuntime);
            $ibcPerH   = $totalRuntime > 0
                ? round($totalIbcOk / ($totalRuntime / 60), 1) : 0.0;
            $stopptidPct = $skiftMin > 0
                ? round(($stopptid / $skiftMin) * 100, 1) : 0.0;
            $drifttidPct = $skiftMin > 0
                ? round(($totalRuntime / $skiftMin) * 100, 1) : 0.0;

            // ---- Cykeltider (via LAG) ----
            $cykelStmt = $this->pdo->prepare(
                "SELECT
                    AVG(cycle_sek) AS avg_sek,
                    MIN(cycle_sek) AS min_sek,
                    MAX(cycle_sek) AS max_sek,
                    COUNT(*)       AS antal_cykler
                 FROM (
                    SELECT
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) AS cycle_sek
                    FROM rebotling_ibc
                    WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                 ) raw
                 WHERE cycle_sek >= 30 AND cycle_sek <= 1800"
            );
            $cykelStmt->execute([$date, $date]);
            $cykelRow = $cykelStmt->fetch(PDO::FETCH_ASSOC);

            $cykeltider = [
                'avg_sek'      => $cykelRow['avg_sek'] !== null ? round((float)$cykelRow['avg_sek'], 1) : null,
                'min_sek'      => $cykelRow['min_sek'] !== null ? (float)$cykelRow['min_sek'] : null,
                'max_sek'      => $cykelRow['max_sek'] !== null ? (float)$cykelRow['max_sek'] : null,
                'antal_cykler' => $cykelRow['antal_cykler'] !== null ? (int)$cykelRow['antal_cykler'] : 0,
            ];

            // ---- OEE-approximation ----
            // Tillgänglighet = drifttid / planerad tid
            // Prestanda = faktisk IBC/h / teoretisk max (planlagd 60 IBC/h)
            // Kvalitet = ibc_ok / ibc_total
            $teoretiskMaxIbcPerH = 60.0; // justera efter faktiskt mål
            $tillganglighet = $skiftMin > 0
                ? round(($totalRuntime / $skiftMin) * 100, 1) : 0.0;
            $prestanda = ($totalRuntime > 0 && $teoretiskMaxIbcPerH > 0)
                ? round(min(100.0, ($ibcPerH / $teoretiskMaxIbcPerH) * 100), 1) : 0.0;
            $oeeKvalitet = $kvalitet;
            $oee = round(($tillganglighet / 100) * ($prestanda / 100) * ($oeeKvalitet / 100) * 100, 1);

            // ---- Top-operatörer ----
            $opStmt = $this->pdo->prepare(
                "SELECT
                    op_num,
                    COUNT(*)             AS antal_ibc,
                    ROUND(AVG(cycle_sek), 1) AS avg_cykeltid
                 FROM (
                    SELECT
                        op_num,
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) AS cycle_sek
                    FROM (
                        SELECT op1 AS op_num, datum, skiftraknare FROM rebotling_ibc
                        WHERE datum >= :d1 AND datum < DATE_ADD(:d1b, INTERVAL 1 DAY) AND op1 IS NOT NULL AND op1 > 0
                        UNION ALL
                        SELECT op2 AS op_num, datum, skiftraknare FROM rebotling_ibc
                        WHERE datum >= :d2 AND datum < DATE_ADD(:d2b, INTERVAL 1 DAY) AND op2 IS NOT NULL AND op2 > 0
                        UNION ALL
                        SELECT op3 AS op_num, datum, skiftraknare FROM rebotling_ibc
                        WHERE datum >= :d3 AND datum < DATE_ADD(:d3b, INTERVAL 1 DAY) AND op3 IS NOT NULL AND op3 > 0
                    ) ops_raw
                 ) with_lag
                 WHERE cycle_sek >= 30 AND cycle_sek <= 1800
                 GROUP BY op_num
                 ORDER BY antal_ibc DESC
                 LIMIT 10"
            );
            $opStmt->execute([':d1' => $date, ':d1b' => $date, ':d2' => $date, ':d2b' => $date, ':d3' => $date, ':d3b' => $date]);
            $opRows = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta operatörsnamn
            $operatorer = [];
            if (!empty($opRows)) {
                $opNums = array_column($opRows, 'op_num');
                $placeholders = implode(',', array_fill(0, count($opNums), '?'));
                $nameStmt = $this->pdo->prepare(
                    "SELECT number, name FROM operators WHERE number IN ({$placeholders})"
                );
                $nameStmt->execute($opNums);
                $names = [];
                foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                    $names[(int)$nr['number']] = $nr['name'];
                }
                foreach ($opRows as $or) {
                    $opNum = (int)$or['op_num'];
                    $opAntalIbc = (int)$or['antal_ibc'];
                    $opCykel = (float)$or['avg_cykeltid'];
                    // Kvalitet per operatör (grov approximation: ok/(ok+ej_ok))
                    $operatorer[] = [
                        'op_num'       => $opNum,
                        'namn'         => $names[$opNum] ?? ('Operatör ' . $opNum),
                        'antal_ibc'    => $opAntalIbc,
                        'avg_cykeltid' => $opCykel,
                    ];
                }
            }

            // ---- Trender mot förra veckan (samma veckodag -7 dagar) ----
            $prevDate = date('Y-m-d', strtotime($date . ' -7 days'));
            $prevStmt = $this->pdo->prepare(
                "SELECT
                    SUM(max_ibc_ok)   AS ibc_ok,
                    SUM(max_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(max_runtime)  AS runtime_min
                 FROM (
                    SELECT
                        MAX(ibc_ok)      AS max_ibc_ok,
                        MAX(ibc_ej_ok)   AS max_ibc_ej_ok,
                        MAX(runtime_plc) AS max_runtime
                    FROM rebotling_ibc
                    WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                    GROUP BY skiftraknare
                    HAVING COUNT(*) > 1
                 ) s"
            );
            $prevStmt->execute([$prevDate, $prevDate]);
            $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);

            $trender = null;
            if ($prevRow && $prevRow['ibc_ok'] !== null) {
                $prevIbcOk   = (int)$prevRow['ibc_ok'];
                $prevIbcEjOk = (int)$prevRow['ibc_ej_ok'];
                $prevIbcTotal = $prevIbcOk + $prevIbcEjOk;
                $prevRuntime = (int)$prevRow['runtime_min'];
                $prevKvalitet = $prevIbcTotal > 0
                    ? round(($prevIbcOk / $prevIbcTotal) * 100, 1) : 0.0;
                $prevIbcPerH = $prevRuntime > 0
                    ? round($prevIbcOk / ($prevRuntime / 60), 1) : 0.0;

                $diffIbcOk = $prevIbcOk > 0
                    ? round((($totalIbcOk - $prevIbcOk) / $prevIbcOk) * 100, 1) : null;
                $diffKvalitet = $prevKvalitet > 0
                    ? round($kvalitet - $prevKvalitet, 1) : null;
                $diffIbcPerH = $prevIbcPerH > 0
                    ? round((($ibcPerH - $prevIbcPerH) / $prevIbcPerH) * 100, 1) : null;

                $trender = [
                    'prev_datum'       => $prevDate,
                    'prev_ibc_ok'      => $prevIbcOk,
                    'prev_kvalitet'    => $prevKvalitet,
                    'prev_ibc_per_h'   => $prevIbcPerH,
                    'diff_ibc_ok_pct'  => $diffIbcOk,
                    'diff_kvalitet'    => $diffKvalitet,
                    'diff_ibc_per_h_pct' => $diffIbcPerH,
                ];
            }

            $this->sendSuccess([
                'datum'      => $date,
                'har_data'   => true,
                'produktion' => [
                    'ibc_ok'        => $totalIbcOk,
                    'ibc_ej_ok'     => $totalIbcEjOk,
                    'bur_ej_ok'     => $totalBurEjOk,
                    'ibc_total'     => $ibcTotal,
                    'kvalitet_pct'  => $kvalitet,
                    'ibc_per_timme' => $ibcPerH,
                    'skift_start'   => $skiftStart,
                    'skift_slut'    => $skiftSlut,
                    'antal_skiften' => count($skiftRows),
                ],
                'cykeltider' => $cykeltider,
                'drifttid'   => [
                    'drifttid_min'  => $totalRuntime,
                    'stopptid_min'  => $stopptid,
                    'rast_min'      => $totalRast,
                    'planerad_min'  => $skiftMin,
                    'drifttid_pct'  => $drifttidPct,
                    'stopptid_pct'  => $stopptidPct,
                ],
                'oee'        => [
                    'oee_pct'          => $oee,
                    'tillganglighet'   => $tillganglighet,
                    'prestanda'        => $prestanda,
                    'kvalitet'         => $oeeKvalitet,
                    'teoretisk_max_ibc_per_h' => $teoretiskMaxIbcPerH,
                ],
                'operatorer' => $operatorer,
                'trender'    => $trender,
                'skiften'    => $skiftLista,
            ]);

        } catch (Exception $e) {
            error_log('SkiftrapportExportController::getReportData: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skiftdata', 500);
        }
    }

    // ================================================================
    // run=multi-day&start=YYYY-MM-DD&end=YYYY-MM-DD
    // ================================================================

    private function fetchLopnummer(int $skiftraknare): array {
        $stmt = $this->pdo->prepare(
            "SELECT lopnummer FROM rebotling_ibc WHERE skiftraknare = ? ORDER BY lopnummer"
        );
        $stmt->execute([$skiftraknare]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $nums = array_map('intval', $rows);
        return array_values(array_unique(array_filter($nums, fn($n) => $n > 0 && $n < 998)));
    }

    private function buildRanges(array $nums): string {
        if (empty($nums)) return '–';
        sort($nums);
        $ranges = [];
        $start = $nums[0];
        $end   = $start;
        for ($i = 1; $i < count($nums); $i++) {
            if ($nums[$i] === $end + 1) {
                $end = $nums[$i];
            } else {
                $ranges[] = $start === $end ? "$start" : $start . '-' . $end;
                $start = $nums[$i];
                $end   = $start;
            }
        }
        $ranges[] = $start === $end ? "$start" : $start . '-' . $end;
        return implode(', ', $ranges);
    }

    private function getMultiDay(): void {
        $start = trim($_GET['start'] ?? '');
        $end   = trim($_GET['end']   ?? '');

        if (empty($start) || empty($end)) {
            $end   = date('Y-m-d', strtotime('-1 day'));
            $start = date('Y-m-d', strtotime('-7 days'));
        }

        if (!$this->validateDate($start) || !$this->validateDate($end)) {
            $this->sendError('Ogiltigt datumformat. Använd YYYY-MM-DD.');
            return;
        }

        // Max 31 dagars span (DateTime::diff ar DST-sakert)
        $diffDays = (int)(new \DateTime($start))->diff(new \DateTime($end))->days + 1;
        if ($diffDays < 1 || $diffDays > 31) {
            $this->sendError('Datumintervallet måste vara 1–31 dagar.');
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    s.dag               AS dag,
                    SUM(max_ibc_ok)    AS ibc_ok,
                    SUM(max_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(max_runtime)   AS runtime_min,
                    COUNT(DISTINCT skiftraknare) AS antal_skiften
                 FROM (
                    SELECT
                        DATE(datum)    AS dag,
                        skiftraknare,
                        MAX(ibc_ok)         AS max_ibc_ok,
                        MAX(ibc_ej_ok)      AS max_ibc_ej_ok,
                        MAX(runtime_plc)    AS max_runtime
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ?
                    GROUP BY DATE(datum), skiftraknare
                    HAVING COUNT(*) > 1
                 ) s
                 GROUP BY s.dag
                 ORDER BY dag ASC"
            );
            $stmt->execute([$start, $end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dagar = [];
            $summaTotalIbc = 0;
            $summaTotalOk  = 0;
            $summaRuntime  = 0;

            foreach ($rows as $r) {
                $ibcOk    = (int)$r['ibc_ok'];
                $ibcEjOk  = (int)$r['ibc_ej_ok'];
                $ibcTotal = $ibcOk + $ibcEjOk;
                $runtime  = (int)$r['runtime_min'];
                $kvalitet = $ibcTotal > 0
                    ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;
                $ibcPerH  = $runtime > 0
                    ? round($ibcOk / ($runtime / 60), 1) : 0.0;

                $skiftPlanMin = (int)$r['antal_skiften'] * 480;
                $stopptid     = max(0, $skiftPlanMin - $runtime);
                $drifttidPct  = $skiftPlanMin > 0
                    ? round(($runtime / $skiftPlanMin) * 100, 1) : 0.0;

                $tillganglighet  = $drifttidPct;
                $prestanda       = $runtime > 0
                    ? round(min(100.0, ($ibcPerH / 60.0) * 100), 1) : 0.0;
                $oee = round(($tillganglighet / 100) * ($prestanda / 100) * ($kvalitet / 100) * 100, 1);

                $dagar[] = [
                    'dag'           => $r['dag'],
                    'ibc_ok'        => $ibcOk,
                    'ibc_ej_ok'     => $ibcEjOk,
                    'ibc_total'     => $ibcTotal,
                    'kvalitet_pct'  => $kvalitet,
                    'ibc_per_timme' => $ibcPerH,
                    'runtime_min'   => $runtime,
                    'stopptid_min'  => $stopptid,
                    'drifttid_pct'  => $drifttidPct,
                    'oee_pct'       => $oee,
                    'antal_skiften' => (int)$r['antal_skiften'],
                ];

                $summaTotalIbc += $ibcTotal;
                $summaTotalOk  += $ibcOk;
                $summaRuntime  += $runtime;
            }

            $summaKvalitet = $summaTotalIbc > 0
                ? round(($summaTotalOk / $summaTotalIbc) * 100, 1) : 0.0;
            $summaIbcPerH  = $summaRuntime > 0
                ? round($summaTotalOk / ($summaRuntime / 60), 1) : 0.0;
            $antaldagar    = count($dagar);
            $snittIbcPerDag = $antaldagar > 0
                ? round($summaTotalOk / $antaldagar, 0) : 0;

            $this->sendSuccess([
                'start'          => $start,
                'end'            => $end,
                'antal_dagar'    => $antaldagar,
                'dagar'          => $dagar,
                'summa' => [
                    'ibc_ok'         => $summaTotalOk,
                    'ibc_total'      => $summaTotalIbc,
                    'kvalitet_pct'   => $summaKvalitet,
                    'ibc_per_timme'  => $summaIbcPerH,
                    'snitt_ibc_per_dag' => $snittIbcPerDag,
                ],
            ]);

        } catch (Exception $e) {
            error_log('SkiftrapportExportController::getMultiDay: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta multi-dag data', 500);
        }
    }
}
