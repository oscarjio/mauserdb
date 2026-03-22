<?php

/**
 * HistoriskProduktionController
 * Historisk produktionsoversikt — statistik over tid for VD:n.
 *
 * Endpoints via ?action=historisk-produktion&run=XXX:
 *
 *   GET  run=overview              (?days=7|30|90|365, ?from&to)  — KPI:er: total, snitt/dag, basta dag, kassation%
 *   GET  run=produktion-per-period (?days=7|30|90|365, ?from&to)  — aggregerad data (dag/vecka/manad) for graf
 *   GET  run=jamforelse            (?days=7|30|90|365)            — jamfor vald period mot foregaende period
 *   GET  run=detalj-tabell         (?days=30, ?from&to, ?page=1, ?per_page=50, ?sort=date&order=desc)
 *
 * Tabeller: rebotling_ibc (datum, ibc_ok, ibc_ej_ok, skiftraknare)
 */
class HistoriskProduktionController {
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
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'overview':              $this->getOverview();           break;
            case 'produktion-per-period': $this->getProduktionPerPeriod(); break;
            case 'jamforelse':            $this->getJamforelse();          break;
            case 'detalj-tabell':         $this->getDetaljTabell();        break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // Helpers
    // ================================================================

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve date range from request params.
     * Supports: days=N  OR  from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    private function resolveDateRange(): array {
        $from = trim($_GET['from'] ?? '');
        $to   = trim($_GET['to']   ?? '');

        if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            // Validera att from <= to, annars byt plats
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            try {
                $dt1 = new \DateTime($from);
                $dt2 = new \DateTime($to);
            } catch (\Exception $e) {
                // Ogiltigt datum trots korrekt format — fallback till default
                error_log('HistoriskProduktionController: datumparse fallback — ' . $e->getMessage());
                $days = 30;
                return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d'), $days];
            }
            $days = (int)$dt1->diff($dt2)->days + 1;
            // Begränsa till max 365 dagar
            if ($days > 365) {
                $from = (clone $dt2)->modify('-364 days')->format('Y-m-d');
                $days = 365;
            }
            return [$from, $to, max(1, $days)];
        }

        $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        return [$fromDate, $toDate, $days];
    }

    /**
     * Determine granularity based on number of days.
     */
    private function getGranularity(int $days): string {
        if ($days <= 30) return 'dag';
        if ($days <= 90) return 'vecka';
        return 'manad';
    }

    /**
     * Get production per day (using MAX per shift pattern from ProduktionskostnadController).
     */
    private function getProductionPerDay(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                dag,
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
            FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS per_shift
            GROUP BY dag
            ORDER BY dag ASC
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get aggregated production for a range.
     */
    private function getProductionForRange(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
            FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS per_shift
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $ok   = (int)($row['ibc_ok'] ?? 0);
        $ejOk = (int)($row['ibc_ej_ok'] ?? 0);
        return ['ibc_ok' => $ok, 'ibc_ej_ok' => $ejOk, 'total' => $ok + $ejOk];
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();

            $perDay = $this->getProductionPerDay($fromDate, $toDate);

            $totalOk    = 0;
            $totalEjOk  = 0;
            $bestDay     = '';
            $bestDayVal  = 0;
            $daysWithData = 0;

            foreach ($perDay as $d) {
                $ok   = (int)$d['ibc_ok'];
                $ejOk = (int)$d['ibc_ej_ok'];
                $dayTotal = $ok + $ejOk;
                $totalOk   += $ok;
                $totalEjOk += $ejOk;
                $daysWithData++;

                if ($dayTotal > $bestDayVal) {
                    $bestDayVal = $dayTotal;
                    $bestDay    = $d['dag'];
                }
            }

            $grandTotal    = $totalOk + $totalEjOk;
            $snittPerDag   = $daysWithData > 0 ? round($grandTotal / $daysWithData, 1) : 0;
            $kassationPct  = $grandTotal > 0 ? round(($totalEjOk / $grandTotal) * 100, 1) : 0;

            $this->sendSuccess([
                'data' => [
                    'total_produktion'  => $grandTotal,
                    'total_ok'          => $totalOk,
                    'total_ej_ok'       => $totalEjOk,
                    'snitt_per_dag'     => $snittPerDag,
                    'basta_dag'         => $bestDay,
                    'basta_dag_antal'   => $bestDayVal,
                    'kassation_pct'     => $kassationPct,
                    'dagar_med_data'    => $daysWithData,
                    'from'              => $fromDate,
                    'to'                => $toDate,
                    'days'              => $days,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('HistoriskProduktionController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta oversikt', 500);
        }
    }

    // ================================================================
    // run=produktion-per-period — aggregerad data for graf
    // ================================================================

    private function getProduktionPerPeriod(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();
            $granularity = $this->getGranularity($days);

            $perDay = $this->getProductionPerDay($fromDate, $toDate);

            // Aggregate based on granularity
            $aggregated = [];

            if ($granularity === 'dag') {
                foreach ($perDay as $d) {
                    $aggregated[] = [
                        'label'   => $d['dag'],
                        'ibc_ok'  => (int)$d['ibc_ok'],
                        'ibc_ej_ok' => (int)$d['ibc_ej_ok'],
                        'total'   => (int)$d['ibc_ok'] + (int)$d['ibc_ej_ok'],
                    ];
                }
            } elseif ($granularity === 'vecka') {
                $weeks = [];
                foreach ($perDay as $d) {
                    $dt = new \DateTime($d['dag']);
                    // ISO week: YYYY-WNN
                    $weekKey = $dt->format('o') . '-V' . $dt->format('W');
                    if (!isset($weeks[$weekKey])) {
                        $weeks[$weekKey] = ['ibc_ok' => 0, 'ibc_ej_ok' => 0];
                    }
                    $weeks[$weekKey]['ibc_ok']    += (int)$d['ibc_ok'];
                    $weeks[$weekKey]['ibc_ej_ok'] += (int)$d['ibc_ej_ok'];
                }
                foreach ($weeks as $label => $vals) {
                    $aggregated[] = [
                        'label'     => $label,
                        'ibc_ok'    => $vals['ibc_ok'],
                        'ibc_ej_ok' => $vals['ibc_ej_ok'],
                        'total'     => $vals['ibc_ok'] + $vals['ibc_ej_ok'],
                    ];
                }
            } else { // manad
                $months = [];
                foreach ($perDay as $d) {
                    $monthKey = substr($d['dag'], 0, 7); // YYYY-MM
                    if (!isset($months[$monthKey])) {
                        $months[$monthKey] = ['ibc_ok' => 0, 'ibc_ej_ok' => 0];
                    }
                    $months[$monthKey]['ibc_ok']    += (int)$d['ibc_ok'];
                    $months[$monthKey]['ibc_ej_ok'] += (int)$d['ibc_ej_ok'];
                }
                foreach ($months as $label => $vals) {
                    $aggregated[] = [
                        'label'     => $label,
                        'ibc_ok'    => $vals['ibc_ok'],
                        'ibc_ej_ok' => $vals['ibc_ej_ok'],
                        'total'     => $vals['ibc_ok'] + $vals['ibc_ej_ok'],
                    ];
                }
            }

            $this->sendSuccess([
                'data' => [
                    'granularity' => $granularity,
                    'from'        => $fromDate,
                    'to'          => $toDate,
                    'days'        => $days,
                    'series'      => $aggregated,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('HistoriskProduktionController::getProduktionPerPeriod: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta produktionsdata', 500);
        }
    }

    // ================================================================
    // run=jamforelse — jamfor vald period mot foregaende
    // ================================================================

    private function getJamforelse(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();

            // Current period
            $current = $this->getProductionForRange($fromDate, $toDate);

            // Previous period (same length, immediately before)
            $prevTo   = date('Y-m-d', strtotime($fromDate . ' -1 day'));
            $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
            $previous = $this->getProductionForRange($prevFrom, $prevTo);

            // Calculate per-day data for both
            $currentPerDay = $this->getProductionPerDay($fromDate, $toDate);
            $prevPerDay    = $this->getProductionPerDay($prevFrom, $prevTo);

            $currentDaysCount = count($currentPerDay);
            $prevDaysCount    = count($prevPerDay);

            $currentSnitt = $currentDaysCount > 0 ? round($current['total'] / $currentDaysCount, 1) : 0;
            $prevSnitt    = $prevDaysCount > 0     ? round($previous['total'] / $prevDaysCount, 1)   : 0;

            // Trend calculations
            $totalDiff  = $current['total'] - $previous['total'];
            $totalPct   = $previous['total'] > 0 ? round(($totalDiff / $previous['total']) * 100, 1) : 0;

            $snittDiff  = $currentSnitt - $prevSnitt;
            $snittPct   = $prevSnitt > 0 ? round(($snittDiff / $prevSnitt) * 100, 1) : 0;

            $currentKass = $current['total'] > 0 ? round(($current['ibc_ej_ok'] / $current['total']) * 100, 1) : 0;
            $prevKass    = $previous['total'] > 0 ? round(($previous['ibc_ej_ok'] / $previous['total']) * 100, 1) : 0;
            $kassDiff    = round($currentKass - $prevKass, 1);

            // Trend direction
            $trendDirection = 'stabil';
            if ($totalPct > 2) $trendDirection = 'upp';
            elseif ($totalPct < -2) $trendDirection = 'ner';

            $this->sendSuccess([
                'data' => [
                    'nuvarande' => [
                        'from'           => $fromDate,
                        'to'             => $toDate,
                        'total'          => $current['total'],
                        'ibc_ok'         => $current['ibc_ok'],
                        'ibc_ej_ok'      => $current['ibc_ej_ok'],
                        'snitt_per_dag'  => $currentSnitt,
                        'kassation_pct'  => $currentKass,
                        'dagar_med_data' => $currentDaysCount,
                    ],
                    'foregaende' => [
                        'from'           => $prevFrom,
                        'to'             => $prevTo,
                        'total'          => $previous['total'],
                        'ibc_ok'         => $previous['ibc_ok'],
                        'ibc_ej_ok'      => $previous['ibc_ej_ok'],
                        'snitt_per_dag'  => $prevSnitt,
                        'kassation_pct'  => $prevKass,
                        'dagar_med_data' => $prevDaysCount,
                    ],
                    'diff' => [
                        'total'          => $totalDiff,
                        'total_pct'      => $totalPct,
                        'snitt_diff'     => round($snittDiff, 1),
                        'snitt_pct'      => $snittPct,
                        'kassation_diff' => $kassDiff,
                    ],
                    'trend_direction' => $trendDirection,
                    'days'            => $days,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('HistoriskProduktionController::getJamforelse: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta jamforelsedata', 500);
        }
    }

    // ================================================================
    // run=detalj-tabell — daglig detaljdata med pagination
    // ================================================================

    private function getDetaljTabell(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();

            $page    = max(1, min(10000, (int)($_GET['page'] ?? 1)));
            $perPage = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
            $sort    = in_array(($_GET['sort'] ?? ''), ['date', 'ibc_ok', 'ibc_ej_ok', 'total', 'kassation_pct'], true) ? $_GET['sort'] : 'date';
            $order   = strtoupper(trim($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

            $perDay = $this->getProductionPerDay($fromDate, $toDate);

            // Build rows
            $rows = [];
            foreach ($perDay as $d) {
                $ok   = (int)$d['ibc_ok'];
                $ejOk = (int)$d['ibc_ej_ok'];
                $total = $ok + $ejOk;
                $kassPct = $total > 0 ? round(($ejOk / $total) * 100, 1) : 0;

                $rows[] = [
                    'date'           => $d['dag'],
                    'ibc_ok'         => $ok,
                    'ibc_ej_ok'      => $ejOk,
                    'total'          => $total,
                    'kassation_pct'  => $kassPct,
                ];
            }

            // Sort
            $sortKey = $sort;
            usort($rows, function ($a, $b) use ($sortKey, $order) {
                $va = $a[$sortKey] ?? 0;
                $vb = $b[$sortKey] ?? 0;
                if ($sortKey === 'date') {
                    $cmp = strcmp($va, $vb);
                } else {
                    $cmp = $va <=> $vb;
                }
                return $order === 'ASC' ? $cmp : -$cmp;
            });

            $totalRows = count($rows);
            $totalPages = max(1, ceil($totalRows / $perPage));
            $offset = ($page - 1) * $perPage;
            $pagedRows = array_slice($rows, $offset, $perPage);

            // Summor
            $sumOk   = array_sum(array_column($rows, 'ibc_ok'));
            $sumEjOk = array_sum(array_column($rows, 'ibc_ej_ok'));
            $sumTotal = $sumOk + $sumEjOk;
            $avgKass  = $sumTotal > 0 ? round(($sumEjOk / $sumTotal) * 100, 1) : 0;

            $this->sendSuccess([
                'data' => [
                    'rows'        => $pagedRows,
                    'from'        => $fromDate,
                    'to'          => $toDate,
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total_rows'  => $totalRows,
                    'total_pages' => $totalPages,
                    'sort'        => $sort,
                    'order'       => $order,
                    'summor'      => [
                        'ibc_ok'        => $sumOk,
                        'ibc_ej_ok'     => $sumEjOk,
                        'total'         => $sumTotal,
                        'kassation_pct' => $avgKass,
                    ],
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('HistoriskProduktionController::getDetaljTabell: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta detaljdata', 500);
        }
    }
}
