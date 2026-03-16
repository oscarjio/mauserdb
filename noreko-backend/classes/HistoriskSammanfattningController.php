<?php
/**
 * HistoriskSammanfattningController.php
 * Auto-genererad manads-/kvartalsrapport med text och diagram.
 *
 * Endpoints via ?action=historisk-sammanfattning&run=XXX:
 *   run=perioder      — lista tillgangliga manader/kvartal (for dropdown)
 *   run=rapport       — huvudrapport med auto-genererad text, KPI:er, jamforelse mot foregaende period
 *   run=trend         — OEE/IBC per dag inom vald period
 *   run=operatorer    — top operatorer inom perioden med jamforelse
 *   run=stationer     — per-station breakdown for perioden
 *   run=stopporsaker  — Pareto stopporsaker inom perioden
 *
 * Parametrar: typ (manad/kvartal), period (2026-03, Q1-2026)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_stationer,
 *           stopporsak_registreringar, stopporsak_kategorier, operators
 */
class HistoriskSammanfattningController {
    private $pdo;
    private const IDEAL_CYCLE_SEC = 120;
    private const PLANERAD_MIN = 480;
    private const TEORIETISK_MAX_IBC_H = 60.0;

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
            case 'perioder':     $this->perioder();     break;
            case 'rapport':      $this->rapport();      break;
            case 'trend':        $this->trend();        break;
            case 'operatorer':   $this->operatorer();   break;
            case 'stationer':    $this->stationer();    break;
            case 'stopporsaker': $this->stopporsaker(); break;
            default:             $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HELPERS
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
     * Tolka typ + period -> from/to datum
     * typ=manad, period=2026-03 -> 2026-03-01 / 2026-03-31
     * typ=kvartal, period=Q1-2026 -> 2026-01-01 / 2026-03-31
     */
    private function parsePeriod(): array {
        $typ    = strtolower(trim($_GET['typ'] ?? 'manad'));
        $period = trim($_GET['period'] ?? '');

        if ($typ === 'kvartal') {
            // Q1-2026 format
            if (preg_match('/^Q([1-4])-(\d{4})$/', $period, $m)) {
                $q    = (int)$m[1];
                $year = (int)$m[2];
                $startMonth = ($q - 1) * 3 + 1;
                $endMonth   = $startMonth + 2;
                $from = sprintf('%04d-%02d-01', $year, $startMonth);
                $to   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
                $label = "Q{$q} {$year}";
                // Foregaende kvartal
                $prevQ    = $q > 1 ? $q - 1 : 4;
                $prevYear = $q > 1 ? $year : $year - 1;
                $prevStartMonth = ($prevQ - 1) * 3 + 1;
                $prevEndMonth   = $prevStartMonth + 2;
                $prevFrom = sprintf('%04d-%02d-01', $prevYear, $prevStartMonth);
                $prevTo   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevEndMonth)));
                $prevLabel = "Q{$prevQ} {$prevYear}";
                return [
                    'typ' => 'kvartal', 'from' => $from, 'to' => $to, 'label' => $label,
                    'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'prev_label' => $prevLabel,
                ];
            }
        }

        // Default: manad (2026-03 format)
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            $year  = (int)$m[1];
            $month = (int)$m[2];
        } else {
            $year  = (int)date('Y');
            $month = (int)date('m');
        }
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $manadNamn = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Mars', 4 => 'April',
            5 => 'Maj', 6 => 'Juni', 7 => 'Juli', 8 => 'Augusti',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December',
        ];
        $label = ($manadNamn[$month] ?? 'Manad') . " {$year}";

        // Foregaende manad
        $prevMonth = $month > 1 ? $month - 1 : 12;
        $prevYear  = $month > 1 ? $year : $year - 1;
        $prevFrom  = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
        $prevTo    = date('Y-m-t', strtotime($prevFrom));
        $prevLabel = ($manadNamn[$prevMonth] ?? 'Manad') . " {$prevYear}";

        return [
            'typ' => 'manad', 'from' => $from, 'to' => $to, 'label' => $label,
            'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'prev_label' => $prevLabel,
        ];
    }

    private function getStationer(): array {
        try {
            $stmt = $this->pdo->query("SELECT id, namn FROM rebotling_stationer ORDER BY id");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) return $rows;
        } catch (\Exception) {}

        return [
            ['id' => 1, 'namn' => 'Station 1'],
            ['id' => 2, 'namn' => 'Station 2'],
            ['id' => 3, 'namn' => 'Station 3'],
            ['id' => 4, 'namn' => 'Station 4'],
            ['id' => 5, 'namn' => 'Station 5'],
        ];
    }

    /**
     * Berakna produktionsdata for en period.
     */
    private function calcPeriodData(string $from, string $to): array {
        // IBC-data
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(DISTINCT skiftraknare) AS antal_skift,
                COALESCE(SUM(max_ok),    0) AS ibc_ok,
                COALESCE(SUM(max_ej_ok), 0) AS ibc_ej_ok,
                COALESCE(SUM(max_runtime), 0) AS runtime_min
             FROM (
                SELECT
                    skiftraknare,
                    MAX(ibc_ok)      AS max_ok,
                    MAX(ibc_ej_ok)   AS max_ej_ok,
                    MAX(runtime_plc) AS max_runtime
                FROM rebotling_ibc
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY skiftraknare
                HAVING COUNT(*) > 1
             ) s"
        );
        $stmt->execute([$from, $to]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ibcOk      = (int)($row['ibc_ok'] ?? 0);
        $ibcEjOk    = (int)($row['ibc_ej_ok'] ?? 0);
        $runtime    = (int)($row['runtime_min'] ?? 0);
        $antalSkift = (int)($row['antal_skift'] ?? 0);
        $ibcTotal   = $ibcOk + $ibcEjOk;

        $ibcPerH   = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;
        $kvalitet  = $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;

        // OEE
        $planMinTotal   = $antalSkift * self::PLANERAD_MIN;
        $tillganglighet = $planMinTotal > 0 ? min(1.0, $runtime / $planMinTotal) : 0.0;
        $prestanda      = $runtime > 0 ? min(1.0, ($ibcPerH / self::TEORIETISK_MAX_IBC_H)) : 0.0;
        $kvalFaktor     = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalFaktor;

        $dagCount = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
        $snittIbcPerDag = $dagCount > 0 ? round($ibcOk / $dagCount, 1) : 0;

        // Stopptid
        $stopptidMin = max(0, $planMinTotal - $runtime);

        return [
            'ibc_ok'             => $ibcOk,
            'ibc_ej_ok'          => $ibcEjOk,
            'ibc_total'          => $ibcTotal,
            'runtime_min'        => $runtime,
            'antal_skift'        => $antalSkift,
            'ibc_per_h'          => $ibcPerH,
            'kvalitet_pct'       => $kvalitet,
            'oee_pct'            => round($oee * 100, 1),
            'tillganglighet_pct' => round($tillganglighet * 100, 1),
            'prestanda_pct'      => round($prestanda * 100, 1),
            'stopptid_min'       => $stopptidMin,
            'snitt_ibc_per_dag'  => $snittIbcPerDag,
            'dag_count'          => $dagCount,
        ];
    }

    // ================================================================
    // run=perioder
    // ================================================================

    private function perioder(): void {
        try {
            // Hitta min/max datum fran rebotling_ibc
            $stmt = $this->pdo->query("SELECT MIN(DATE(created_at)) AS min_date, MAX(DATE(created_at)) AS max_date FROM rebotling_ibc");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $minDate = $row['min_date'] ?? date('Y-m-01');
            $maxDate = $row['max_date'] ?? date('Y-m-d');

            // Generera manader
            $manader = [];
            $manadNamn = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Mars', 4 => 'April',
                5 => 'Maj', 6 => 'Juni', 7 => 'Juli', 8 => 'Augusti',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December',
            ];

            $current = new \DateTime(substr($maxDate, 0, 7) . '-01');
            $earliest = new \DateTime(substr($minDate, 0, 7) . '-01');

            while ($current >= $earliest) {
                $y = (int)$current->format('Y');
                $m = (int)$current->format('m');
                $manader[] = [
                    'value' => $current->format('Y-m'),
                    'label' => $manadNamn[$m] . " {$y}",
                ];
                $current->modify('-1 month');
            }

            // Generera kvartal
            $kvartal = [];
            $maxQ = ceil((int)date('m', strtotime($maxDate)) / 3);
            $maxY = (int)date('Y', strtotime($maxDate));
            $minQ = ceil((int)date('m', strtotime($minDate)) / 3);
            $minY = (int)date('Y', strtotime($minDate));

            $curQ = $maxQ;
            $curY = $maxY;
            while ($curY > $minY || ($curY === $minY && $curQ >= $minQ)) {
                $kvartal[] = [
                    'value' => "Q{$curQ}-{$curY}",
                    'label' => "Q{$curQ} {$curY}",
                ];
                $curQ--;
                if ($curQ < 1) { $curQ = 4; $curY--; }
                if (count($kvartal) > 20) break;
            }

            $this->sendSuccess([
                'manader' => $manader,
                'kvartal' => $kvartal,
            ]);
        } catch (\Exception $e) {
            error_log('HistoriskSammanfattningController::perioder: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta perioder', 500);
        }
    }

    // ================================================================
    // run=rapport
    // ================================================================

    private function rapport(): void {
        try {
            $p = $this->parsePeriod();
            $current = $this->calcPeriodData($p['from'], $p['to']);
            $prev    = $this->calcPeriodData($p['prev_from'], $p['prev_to']);

            // Jamforelse-delta
            $oeeDelta      = round($current['oee_pct'] - $prev['oee_pct'], 1);
            $ibcDelta      = $current['ibc_ok'] - $prev['ibc_ok'];
            $snittDelta    = round($current['snitt_ibc_per_dag'] - $prev['snitt_ibc_per_dag'], 1);
            $stopptidDelta = $current['stopptid_min'] - $prev['stopptid_min'];
            $kvalitetDelta = round($current['kvalitet_pct'] - $prev['kvalitet_pct'], 1);

            // Hitta flaskhals (lagst OEE station)
            $stationer = $this->getStationer();
            $flaskhalsStn = null;
            $flaskhalsOee = 999;
            foreach ($stationer as $st) {
                $sid = (int)$st['id'];
                $sData = $this->calcStationData($p['from'], $p['to'], $sid);
                if ($sData['ibc_ok'] > 0 && $sData['oee_pct'] < $flaskhalsOee) {
                    $flaskhalsOee = $sData['oee_pct'];
                    $flaskhalsStn = $st['namn'];
                }
            }

            // Baste operator
            $topOp = $this->getTopOperator($p['from'], $p['to']);

            // Auto-genererad text
            $text = $this->generateText($p, $current, $prev, $oeeDelta, $flaskhalsStn, $flaskhalsOee, $topOp);

            $this->sendSuccess([
                'period'      => $p,
                'current'     => $current,
                'previous'    => $prev,
                'jamforelse'  => [
                    'oee_delta'      => $oeeDelta,
                    'ibc_delta'      => $ibcDelta,
                    'snitt_delta'    => $snittDelta,
                    'stopptid_delta' => $stopptidDelta,
                    'kvalitet_delta' => $kvalitetDelta,
                ],
                'flaskhals' => $flaskhalsStn ? [
                    'station' => $flaskhalsStn,
                    'oee_pct' => $flaskhalsOee,
                ] : null,
                'top_operator' => $topOp,
                'rapport_text' => $text,
            ]);
        } catch (\Exception $e) {
            error_log('HistoriskSammanfattningController::rapport: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta rapport', 500);
        }
    }

    private function generateText(array $p, array $current, array $prev, float $oeeDelta, ?string $flaskhalsStn, float $flaskhalsOee, ?array $topOp): string {
        $label = $p['label'];
        $prevLabel = $p['prev_label'];

        $oeeStr = $current['oee_pct'] . '%';
        $deltaStr = ($oeeDelta >= 0 ? '+' : '') . $oeeDelta . '%';
        $ibcStr = number_format($current['ibc_ok'], 0, ',', ' ');

        $text = "{$label}: OEE {$oeeStr} ({$deltaStr} vs {$prevLabel}). ";
        $text .= "Totalt {$ibcStr} IBC producerade. ";

        if ($flaskhalsStn) {
            $text .= "Flaskhals: {$flaskhalsStn} ({$flaskhalsOee}% OEE). ";
        }

        if ($topOp) {
            $text .= "Baste operator: {$topOp['namn']} med {$topOp['ibc_ok']} IBC.";
        }

        return $text;
    }

    private function getTopOperator(string $from, string $to): ?array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    MIN(NULLIF(op1, 0)) AS op_num,
                    SUM(max_ok) AS total_ibc
                 FROM (
                    SELECT
                        skiftraknare,
                        MIN(NULLIF(op1, 0)) AS op1,
                        MAX(ibc_ok) AS max_ok
                    FROM rebotling_ibc
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY skiftraknare
                    HAVING COUNT(*) > 1
                 ) s
                 WHERE op1 IS NOT NULL
                 GROUP BY op1
                 ORDER BY total_ibc DESC
                 LIMIT 1"
            );
            $stmt->execute([$from, $to]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || !$row['op_num']) return null;

            $opNum = (int)$row['op_num'];
            $ibcOk = (int)$row['total_ibc'];

            // Hamta namn
            $nameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $nameStmt->execute([$opNum]);
            $nameRow = $nameStmt->fetch(\PDO::FETCH_ASSOC);
            $namn = $nameRow ? $nameRow['name'] : "Op #{$opNum}";

            return ['op_num' => $opNum, 'namn' => $namn, 'ibc_ok' => $ibcOk];
        } catch (\Exception) {
            return null;
        }
    }

    private function calcStationData(string $from, string $to, int $stationId): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(max_ok),    0) AS ibc_ok,
                COALESCE(SUM(max_ej_ok), 0) AS ibc_ej_ok,
                COALESCE(SUM(max_runtime), 0) AS runtime_min,
                COUNT(DISTINCT skiftraknare) AS antal_skift
             FROM (
                SELECT
                    skiftraknare,
                    MAX(ibc_ok)      AS max_ok,
                    MAX(ibc_ej_ok)   AS max_ej_ok,
                    MAX(runtime_plc) AS max_runtime
                FROM rebotling_ibc
                WHERE DATE(created_at) BETWEEN ? AND ?
                  AND COALESCE(station_id, 1) = ?
                GROUP BY skiftraknare
                HAVING COUNT(*) > 1
             ) s"
        );
        $stmt->execute([$from, $to, $stationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ibcOk      = (int)($row['ibc_ok'] ?? 0);
        $ibcEjOk    = (int)($row['ibc_ej_ok'] ?? 0);
        $runtime    = (int)($row['runtime_min'] ?? 0);
        $antalSkift = (int)($row['antal_skift'] ?? 0);
        $ibcTotal   = $ibcOk + $ibcEjOk;

        $ibcPerH   = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;
        $kvalitet  = $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;

        $planMin   = $antalSkift * self::PLANERAD_MIN;
        $tillg     = $planMin > 0 ? min(1.0, $runtime / $planMin) : 0.0;
        $prest     = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
        $kvalFakt  = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
        $oee       = $tillg * $prest * $kvalFakt;
        $stopptid  = max(0, $planMin - $runtime);

        return [
            'ibc_ok'             => $ibcOk,
            'ibc_ej_ok'          => $ibcEjOk,
            'ibc_total'          => $ibcTotal,
            'runtime_min'        => $runtime,
            'ibc_per_h'          => $ibcPerH,
            'kvalitet_pct'       => $kvalitet,
            'oee_pct'            => round($oee * 100, 1),
            'tillganglighet_pct' => round($tillg * 100, 1),
            'prestanda_pct'      => round($prest * 100, 1),
            'stopptid_min'       => $stopptid,
        ];
    }

    // ================================================================
    // run=trend
    // ================================================================

    private function trend(): void {
        try {
            $p = $this->parsePeriod();
            $from = $p['from'];
            $to   = $p['to'];

            // OEE/IBC per dag
            $stmt = $this->pdo->prepare(
                "SELECT
                    DATE(created_at) AS datum,
                    COUNT(DISTINCT skiftraknare) AS antal_skift,
                    SUM(max_ok) AS ibc_ok,
                    SUM(max_ej_ok) AS ibc_ej_ok,
                    SUM(max_runtime) AS runtime_min
                 FROM (
                    SELECT
                        skiftraknare,
                        DATE(created_at) AS created_at,
                        MAX(ibc_ok)      AS max_ok,
                        MAX(ibc_ej_ok)   AS max_ej_ok,
                        MAX(runtime_plc) AS max_runtime
                    FROM rebotling_ibc
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY skiftraknare, DATE(created_at)
                    HAVING COUNT(*) > 1
                 ) s
                 GROUP BY DATE(created_at)
                 ORDER BY datum"
            );
            $stmt->execute([$from, $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $byDate = [];
            foreach ($rows as $row) {
                $byDate[$row['datum']] = $row;
            }

            $trendPoints = [];
            $current = new \DateTime($from);
            $end     = new \DateTime($to);
            while ($current <= $end) {
                $dag = $current->format('Y-m-d');
                $row = $byDate[$dag] ?? null;

                if ($row) {
                    $ibcOk   = (int)$row['ibc_ok'];
                    $ibcEjOk = (int)$row['ibc_ej_ok'];
                    $runtime = (int)$row['runtime_min'];
                    $nSkift  = (int)$row['antal_skift'];
                    $ibcTotal = $ibcOk + $ibcEjOk;

                    $ibcPerH = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;
                    $planMin = $nSkift * self::PLANERAD_MIN;
                    $tillg   = $planMin > 0 ? min(1.0, $runtime / $planMin) : 0.0;
                    $prest   = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
                    $kvalF   = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
                    $oee     = $tillg * $prest * $kvalF;

                    $trendPoints[] = [
                        'datum'   => $dag,
                        'oee_pct' => round($oee * 100, 1),
                        'ibc_ok'  => $ibcOk,
                    ];
                } else {
                    $trendPoints[] = [
                        'datum'   => $dag,
                        'oee_pct' => 0,
                        'ibc_ok'  => 0,
                    ];
                }
                $current->modify('+1 day');
            }

            // Rullande 7d-snitt
            $oeeVals = array_column($trendPoints, 'oee_pct');
            $n = count($oeeVals);
            for ($i = 0; $i < $n; $i++) {
                $start = max(0, $i - 6);
                $slice = array_slice($oeeVals, $start, $i - $start + 1);
                $nonZero = array_filter($slice, fn($v) => $v > 0);
                $trendPoints[$i]['oee_ma7'] = !empty($nonZero) ? round(array_sum($nonZero) / count($nonZero), 1) : null;
            }

            $this->sendSuccess([
                'period' => $p,
                'trend'  => $trendPoints,
            ]);
        } catch (\Exception $e) {
            error_log('HistoriskSammanfattningController::trend: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta trenddata', 500);
        }
    }

    // ================================================================
    // run=operatorer
    // ================================================================

    private function operatorer(): void {
        try {
            $p = $this->parsePeriod();

            // Hamta top operatorer for aktuell period
            $stmt = $this->pdo->prepare(
                "SELECT
                    op1 AS op_num,
                    SUM(max_ok) AS ibc_ok,
                    SUM(max_ej_ok) AS ibc_ej_ok,
                    SUM(max_runtime) AS runtime_min,
                    COUNT(DISTINCT skiftraknare) AS antal_skift
                 FROM (
                    SELECT
                        skiftraknare,
                        MIN(NULLIF(op1, 0)) AS op1,
                        MAX(ibc_ok)      AS max_ok,
                        MAX(ibc_ej_ok)   AS max_ej_ok,
                        MAX(runtime_plc) AS max_runtime
                    FROM rebotling_ibc
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY skiftraknare
                    HAVING COUNT(*) > 1
                 ) s
                 WHERE op1 IS NOT NULL
                 GROUP BY op1
                 ORDER BY ibc_ok DESC
                 LIMIT 10"
            );
            $stmt->execute([$p['from'], $p['to']]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Foregaende period
            $prevStmt = $this->pdo->prepare(
                "SELECT
                    op1 AS op_num,
                    SUM(max_ok) AS ibc_ok
                 FROM (
                    SELECT
                        skiftraknare,
                        MIN(NULLIF(op1, 0)) AS op1,
                        MAX(ibc_ok) AS max_ok
                    FROM rebotling_ibc
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY skiftraknare
                    HAVING COUNT(*) > 1
                 ) s
                 WHERE op1 IS NOT NULL
                 GROUP BY op1"
            );
            $prevStmt->execute([$p['prev_from'], $p['prev_to']]);
            $prevData = [];
            foreach ($prevStmt->fetchAll(\PDO::FETCH_ASSOC) as $pr) {
                $prevData[(int)$pr['op_num']] = (int)$pr['ibc_ok'];
            }

            // Hamta operatorsnamn
            $opNums = array_filter(array_column($rows, 'op_num'));
            $names = [];
            if (!empty($opNums)) {
                $ph = implode(',', array_fill(0, count($opNums), '?'));
                $nameStmt = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ({$ph})");
                $nameStmt->execute(array_values($opNums));
                foreach ($nameStmt->fetchAll(\PDO::FETCH_ASSOC) as $nr) {
                    $names[(int)$nr['number']] = $nr['name'];
                }
            }

            $operatorer = [];
            $rank = 1;
            foreach ($rows as $row) {
                $opNum   = (int)$row['op_num'];
                $ibcOk   = (int)$row['ibc_ok'];
                $ibcEjOk = (int)$row['ibc_ej_ok'];
                $runtime = (int)$row['runtime_min'];
                $ibcTotal = $ibcOk + $ibcEjOk;

                $ibcPerH = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;
                $nSkift  = (int)$row['antal_skift'];
                $planMin = $nSkift * self::PLANERAD_MIN;
                $tillg   = $planMin > 0 ? min(1.0, $runtime / $planMin) : 0.0;
                $prest   = $runtime > 0 ? min(1.0, $ibcPerH / self::TEORIETISK_MAX_IBC_H) : 0.0;
                $kvalF   = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
                $oee     = $tillg * $prest * $kvalF;

                $prevIbc = $prevData[$opNum] ?? 0;
                $ibcDelta = $ibcOk - $prevIbc;

                $operatorer[] = [
                    'rank'        => $rank++,
                    'op_num'      => $opNum,
                    'namn'        => $names[$opNum] ?? "Op #{$opNum}",
                    'ibc_ok'      => $ibcOk,
                    'ibc_total'   => $ibcTotal,
                    'oee_pct'     => round($oee * 100, 1),
                    'ibc_per_h'   => $ibcPerH,
                    'kvalitet_pct'=> $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0,
                    'prev_ibc'    => $prevIbc,
                    'ibc_delta'   => $ibcDelta,
                    'trend'       => $ibcDelta > 0 ? 'up' : ($ibcDelta < 0 ? 'down' : 'stable'),
                ];
            }

            // Begr top 5
            $operatorer = array_slice($operatorer, 0, 5);

            $this->sendSuccess([
                'period'     => $p,
                'operatorer' => $operatorer,
            ]);
        } catch (\Exception $e) {
            error_log('HistoriskSammanfattningController::operatorer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta operatorsdata', 500);
        }
    }

    // ================================================================
    // run=stationer
    // ================================================================

    private function stationer(): void {
        try {
            $p = $this->parsePeriod();
            $stationer = $this->getStationer();

            $result = [];
            foreach ($stationer as $st) {
                $sid  = (int)$st['id'];
                $namn = $st['namn'];

                $current = $this->calcStationData($p['from'], $p['to'], $sid);
                $prev    = $this->calcStationData($p['prev_from'], $p['prev_to'], $sid);

                $oeeDelta = round($current['oee_pct'] - $prev['oee_pct'], 1);

                $result[] = [
                    'station_id'         => $sid,
                    'station_namn'       => $namn,
                    'oee_pct'            => $current['oee_pct'],
                    'tillganglighet_pct' => $current['tillganglighet_pct'],
                    'prestanda_pct'      => $current['prestanda_pct'],
                    'kvalitet_pct'       => $current['kvalitet_pct'],
                    'ibc_ok'             => $current['ibc_ok'],
                    'stopptid_min'       => $current['stopptid_min'],
                    'oee_delta'          => $oeeDelta,
                    'trend'              => $oeeDelta > 0.5 ? 'up' : ($oeeDelta < -0.5 ? 'down' : 'stable'),
                    'prev_oee_pct'       => $prev['oee_pct'],
                ];
            }

            // Sortera efter OEE
            usort($result, fn($a, $b) => $b['oee_pct'] <=> $a['oee_pct']);

            $this->sendSuccess([
                'period'    => $p,
                'stationer' => $result,
            ]);
        } catch (\Exception $e) {
            error_log('HistoriskSammanfattningController::stationer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationsdata', 500);
        }
    }

    // ================================================================
    // run=stopporsaker
    // ================================================================

    private function stopporsaker(): void {
        try {
            $p = $this->parsePeriod();

            $orsaker = [];
            try {
                $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
                if ($check && $check->rowCount() > 0) {
                    $stmt = $this->pdo->prepare(
                        "SELECT
                            COALESCE(sk.namn, 'Okand') AS orsak,
                            COUNT(*) AS antal,
                            COALESCE(ROUND(SUM(
                                TIMESTAMPDIFF(MINUTE, sr.start_time, COALESCE(sr.end_time, NOW()))
                            ), 0), 0) AS total_min
                         FROM stopporsak_registreringar sr
                         LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                         WHERE sr.linje = 'rebotling'
                           AND DATE(sr.start_time) BETWEEN ? AND ?
                         GROUP BY COALESCE(sk.namn, 'Okand')
                         ORDER BY total_min DESC
                         LIMIT 10"
                    );
                    $stmt->execute([$p['from'], $p['to']]);
                    $orsaker = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            } catch (\Exception) {
                // Tabellen kanske inte finns
            }

            // Berakna cumulative % for Pareto
            $totalMin = 0;
            foreach ($orsaker as $o) {
                $totalMin += (int)$o['total_min'];
            }

            $cumulative = 0;
            $paretoData = [];
            foreach ($orsaker as $o) {
                $min = (int)$o['total_min'];
                $cumulative += $min;
                $paretoData[] = [
                    'orsak'           => $o['orsak'],
                    'antal'           => (int)$o['antal'],
                    'total_min'       => $min,
                    'total_h'         => round($min / 60, 1),
                    'andel_pct'       => $totalMin > 0 ? round(($min / $totalMin) * 100, 1) : 0,
                    'cumulative_pct'  => $totalMin > 0 ? round(($cumulative / $totalMin) * 100, 1) : 0,
                ];
            }

            // Top 5
            $paretoData = array_slice($paretoData, 0, 5);

            $this->sendSuccess([
                'period'       => $p,
                'stopporsaker' => $paretoData,
                'total_min'    => $totalMin,
                'total_h'      => round($totalMin / 60, 1),
            ]);
        } catch (\Exception $e) {
            error_log('HistoriskSammanfattningController::stopporsaker: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stopporsaker', 500);
        }
    }
}
