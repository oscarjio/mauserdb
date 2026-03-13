<?php
/**
 * KassationsanalysController.php
 * Kassationsanalys — drilldown per stopporsak + kassationstyp
 *
 * Endpoints via ?action=kassationsanalys&run=XXX:
 *   - run=summary        → totala kassationer, rate, topp-orsak, trend vs föregående period
 *   - run=by-cause       → kassationer grupperade per orsak, med antal + andel
 *   - run=daily-stacked  → kassationer per dag, stackad per orsak (för Chart.js)
 *   - run=drilldown      → detaljdata för en specifik orsak (cause=X): operatör, skift, tid
 *   - run=overview       → KPI-sammanfattning med uppskattad kostnad
 *   - run=by-period      → kassationer per vecka/månad, grupperade per orsak (topp 5)
 *   - run=details        → filtrbar detaljlista (orsak, operatör)
 *   - run=trend-rate     → kassationsgrad (%) per dag/vecka med glidande medelvärde
 *
 * Tabeller:
 *   kassationsregistrering  (id, datum, skiftraknare, orsak_id, antal, kommentar, registrerad_av, created_at)
 *   kassationsorsak_typer   (id, namn, aktiv)
 *   rebotling_ibc           (ibc_ok, ibc_ej_ok, datum, skiftraknare, op1, op2, op3)
 */
class KassationsanalysController {
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
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'summary':       $this->getSummary();      break;
            case 'by-cause':      $this->getByCause();      break;
            case 'daily-stacked': $this->getDailyStacked(); break;
            case 'drilldown':     $this->getDrilldown();    break;
            case 'overview':      $this->getOverview();     break;
            case 'by-period':     $this->getByPeriod();     break;
            case 'details':       $this->getDetails();      break;
            case 'trend-rate':    $this->getTrendRate();    break;
            case 'sammanfattning': $this->getSammanfattning(); break;
            case 'orsaker':        $this->getOrsaker();        break;
            case 'orsaker-trend':  $this->getOrsakerTrend();   break;
            case 'per-station':    $this->getPerStation();     break;
            case 'per-operator':   $this->getPerOperator();    break;
            case 'detaljer':       $this->getDetaljer();       break;
            default:              $this->sendError('Ogiltig run: ' . $run); break;
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
     * Hämta totalt kasserade IBC (ibc_ej_ok) från rebotling_ibc för perioden.
     * Aggregeringslogik: MAX() per skiftraknare (kumulativa PLC-värden), sedan SUM().
     */
    private function getTotalKasserade(string $fromDate, string $toDate): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ej_ok), 0) AS totalt
                FROM (
                    SELECT skiftraknare, MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getTotalKasserade: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Hämta totalt godkända IBC från rebotling_ibc för perioden.
     */
    private function getTotalProduktion(string $fromDate, string $toDate): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ok) + SUM(shift_ej_ok), 0) AS totalt
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getTotalProduktion: ' . $e->getMessage());
            return 0;
        }
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=summary&days=30
     * Returnerar:
     *   - total_kassationer (från kassationsregistrering)
     *   - kassations_rate_pct (kasserade IBC / total IBC)
     *   - topp_orsak (orsak med flest registreringar)
     *   - trend vs föregående period (pil + %)
     *   - total_ibc_produktion
     *   - kasserade_ibc (från rebotling_ibc.ibc_ej_ok)
     */
    private function getSummary(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $prevTo   = $fromDate;
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        try {
            // Totala registrerade kassationer (från kassationsregistrering)
            $stmtTotal = $this->pdo->prepare("
                SELECT COALESCE(SUM(antal), 0) AS total
                FROM kassationsregistrering
                WHERE datum BETWEEN :from_date AND :to_date
            ");
            $stmtTotal->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $totalKassationer = (int)$stmtTotal->fetchColumn();

            // Föregående period
            $stmtPrev = $this->pdo->prepare("
                SELECT COALESCE(SUM(antal), 0) AS total
                FROM kassationsregistrering
                WHERE datum BETWEEN :prev_from AND :prev_to
            ");
            $stmtPrev->execute([':prev_from' => $prevFrom, ':prev_to' => $prevTo]);
            $prevKassationer = (int)$stmtPrev->fetchColumn();

            // Trend beräkning
            $trendPct  = 0.0;
            $trendRiktn = 'stable';
            if ($prevKassationer > 0) {
                $trendPct = round(($totalKassationer - $prevKassationer) / $prevKassationer * 100, 1);
                if ($trendPct > 2) $trendRiktn = 'up';
                elseif ($trendPct < -2) $trendRiktn = 'down';
            } elseif ($totalKassationer > 0) {
                $trendRiktn = 'up';
                $trendPct   = 100.0;
            }

            // Topp-orsak
            $stmtTopp = $this->pdo->prepare("
                SELECT t.namn, COALESCE(SUM(r.antal), 0) AS antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY antal DESC
                LIMIT 1
            ");
            $stmtTopp->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $toppRow    = $stmtTopp->fetch(\PDO::FETCH_ASSOC);
            $toppOrsak  = $toppRow ? $toppRow['namn'] : null;
            $toppAntal  = $toppRow ? (int)$toppRow['antal'] : 0;

            // Kasserade IBC från rebotling_ibc (PLC-data)
            $kassaradeIbc  = $this->getTotalKasserade($fromDate, $toDate);
            $totalProduktion = $this->getTotalProduktion($fromDate, $toDate);
            $kassationsRate = $totalProduktion > 0
                ? round($kassaradeIbc / $totalProduktion * 100, 2)
                : 0.0;

            // Föregående periods kassationsrate
            $prevKassaradeIbc   = $this->getTotalKasserade($prevFrom, $prevTo);
            $prevTotalProduktion = $this->getTotalProduktion($prevFrom, $prevTo);
            $prevKassationsRate  = $prevTotalProduktion > 0
                ? round($prevKassaradeIbc / $prevTotalProduktion * 100, 2)
                : 0.0;
            $rateTrendDiff = round($kassationsRate - $prevKassationsRate, 2);
            $rateTrend     = 'stable';
            if ($rateTrendDiff > 0.5) $rateTrend = 'up';
            elseif ($rateTrendDiff < -0.5) $rateTrend = 'down';

            $this->sendSuccess([
                'days'                  => $days,
                'period_from'           => $fromDate,
                'period_to'             => $toDate,
                'total_kassationer'     => $totalKassationer,
                'prev_kassationer'      => $prevKassationer,
                'trend_riktning'        => $trendRiktn,
                'trend_pct'             => $trendPct,
                'topp_orsak'            => $toppOrsak,
                'topp_orsak_antal'      => $toppAntal,
                'kasserade_ibc'         => $kassaradeIbc,
                'total_ibc_produktion'  => $totalProduktion,
                'kassations_rate_pct'   => $kassationsRate,
                'prev_kassations_rate'  => $prevKassationsRate,
                'rate_trend'            => $rateTrend,
                'rate_trend_diff'       => $rateTrendDiff,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getSummary: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: by-cause
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=by-cause&days=30
     * Returnerar kassationer per orsak med:
     *   antal, andel %, kumulativ %, föregående period, trend
     */
    private function getByCause(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $prevTo   = $fromDate;
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        try {
            // Nuvarande period
            $stmtCurr = $this->pdo->prepare("
                SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY antal DESC
            ");
            $stmtCurr->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $orsaker = $stmtCurr->fetchAll(\PDO::FETCH_ASSOC);

            $total = array_sum(array_column($orsaker, 'antal'));

            // Föregående period
            $stmtPrev = $this->pdo->prepare("
                SELECT t.id, COALESCE(SUM(r.antal), 0) AS prev_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :prev_from AND :prev_to
                WHERE t.aktiv = 1
                GROUP BY t.id
            ");
            $stmtPrev->execute([':prev_from' => $prevFrom, ':prev_to' => $prevTo]);
            $prevRows = $stmtPrev->fetchAll(\PDO::FETCH_ASSOC);
            $prevMap  = [];
            foreach ($prevRows as $pr) {
                $prevMap[(int)$pr['id']] = (int)$pr['prev_antal'];
            }

            $cumulative = 0.0;
            $result     = [];
            foreach ($orsaker as $o) {
                $antal     = (int)$o['antal'];
                $andel     = $total > 0 ? round($antal / $total * 100, 1) : 0.0;
                $cumulative += $andel;
                $prevAntal  = $prevMap[(int)$o['id']] ?? 0;
                $trend      = $antal > $prevAntal ? 'up' : ($antal < $prevAntal ? 'down' : 'stable');
                $trendPct   = $prevAntal > 0
                    ? round(($antal - $prevAntal) / $prevAntal * 100, 1)
                    : ($antal > 0 ? 100.0 : 0.0);

                $result[] = [
                    'id'            => (int)$o['id'],
                    'namn'          => $o['namn'],
                    'antal'         => $antal,
                    'andel'         => $andel,
                    'kumulativ_pct' => round($cumulative, 1),
                    'prev_antal'    => $prevAntal,
                    'trend'         => $trend,
                    'trend_pct'     => $trendPct,
                ];
            }

            $this->sendSuccess([
                'days'    => $days,
                'from'    => $fromDate,
                'to'      => $toDate,
                'total'   => (int)$total,
                'orsaker' => $result,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getByCause: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: daily-stacked
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=daily-stacked&days=30
     * Returnerar daglig kassationsdata stackad per orsak för Chart.js.
     * Format:
     *   labels: ['2026-02-10', ...]
     *   datasets: [{ label: 'Orsaknamn', data: [2, 0, 5, ...], orsakId: X }, ...]
     */
    private function getDailyStacked(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Top-orsaker (max 8 för läsbarhet)
            $stmtTop = $this->pdo->prepare("
                SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS total_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY total_antal DESC
                LIMIT 8
            ");
            $stmtTop->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $topOrsaker = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($topOrsaker)) {
                $this->sendSuccess([
                    'days'     => $days,
                    'from'     => $fromDate,
                    'to'       => $toDate,
                    'labels'   => [],
                    'datasets' => [],
                    'har_data' => false,
                ]);
                return;
            }

            $topIds = array_column($topOrsaker, 'id');
            $placeholders = implode(',', array_fill(0, count($topIds), '?'));

            // Daglig data per orsak
            $stmtDaily = $this->pdo->prepare("
                SELECT datum, orsak_id, SUM(antal) AS antal
                FROM kassationsregistrering
                WHERE datum BETWEEN ? AND ?
                  AND orsak_id IN ({$placeholders})
                GROUP BY datum, orsak_id
                ORDER BY datum ASC
            ");
            $params = array_merge([$fromDate, $toDate], $topIds);
            $stmtDaily->execute($params);
            $dailyRows = $stmtDaily->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg datums-index
            $dataByDate = [];
            foreach ($dailyRows as $row) {
                $d = $row['datum'];
                $oid = (int)$row['orsak_id'];
                if (!isset($dataByDate[$d])) $dataByDate[$d] = [];
                $dataByDate[$d][$oid] = (int)$row['antal'];
            }

            // Generera alla datum i perioden
            $labels  = [];
            $current = new \DateTime($fromDate);
            $end     = new \DateTime($toDate);
            while ($current <= $end) {
                $labels[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }

            // Palettfärger (mörkt tema)
            $palette = [
                'rgba(252,129,129,0.85)',  // röd
                'rgba(246,173,85,0.85)',   // orange
                'rgba(104,211,145,0.85)',  // grön
                'rgba(99,179,237,0.85)',   // blå
                'rgba(183,148,246,0.85)',  // lila
                'rgba(237,211,104,0.85)',  // gul
                'rgba(237,122,122,0.75)',  // röd2
                'rgba(129,230,217,0.85)',  // teal
            ];

            $datasets = [];
            foreach ($topOrsaker as $i => $orsak) {
                $orsakId = (int)$orsak['id'];
                $data    = [];
                foreach ($labels as $datum) {
                    $data[] = $dataByDate[$datum][$orsakId] ?? 0;
                }
                $color    = $palette[$i % count($palette)];
                $datasets[] = [
                    'label'           => $orsak['namn'],
                    'orsakId'         => $orsakId,
                    'data'            => $data,
                    'backgroundColor' => $color,
                    'borderColor'     => str_replace('0.85', '1', str_replace('0.75', '1', $color)),
                    'borderWidth'     => 1,
                ];
            }

            $this->sendSuccess([
                'days'     => $days,
                'from'     => $fromDate,
                'to'       => $toDate,
                'labels'   => $labels,
                'datasets' => $datasets,
                'har_data' => true,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getDailyStacked: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: drilldown
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=drilldown&cause=X&days=30
     * Returnerar detaljdata för en specifik orsak:
     *   - Per skift (skiftraknare, datum, antal, kommentar)
     *   - Per dag (aggregerat)
     *   - Operatörer som jobbade under dessa skift (från rebotling_ibc)
     */
    private function getDrilldown(): void {
        $causeId  = intval($_GET['cause'] ?? 0);
        if ($causeId <= 0) {
            $this->sendError('Ogiltig cause-parameter');
            return;
        }

        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Orsaksnamn
            $stmtOrsak = $this->pdo->prepare(
                "SELECT namn FROM kassationsorsak_typer WHERE id = ? LIMIT 1"
            );
            $stmtOrsak->execute([$causeId]);
            $orsakRow = $stmtOrsak->fetch(\PDO::FETCH_ASSOC);
            if (!$orsakRow) {
                $this->sendError('Orsak hittades inte', 404);
                return;
            }

            // Detaljrader — alla registreringar för orsaken i perioden
            $stmtDetalj = $this->pdo->prepare("
                SELECT
                    r.id,
                    r.datum,
                    r.skiftraknare,
                    r.antal,
                    r.kommentar,
                    r.created_at,
                    u.username AS registrerad_av
                FROM kassationsregistrering r
                LEFT JOIN users u ON u.id = r.registrerad_av
                WHERE r.orsak_id = :cause_id
                  AND r.datum BETWEEN :from_date AND :to_date
                ORDER BY r.datum DESC, r.created_at DESC
            ");
            $stmtDetalj->execute([
                ':cause_id'  => $causeId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $detaljer = $stmtDetalj->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($detaljer as &$d) {
                $d['id']          = (int)$d['id'];
                $d['antal']       = (int)$d['antal'];
                $d['skiftraknare'] = $d['skiftraknare'] !== null ? (int)$d['skiftraknare'] : null;
            }
            unset($d);

            // Per dag — summerat antal
            $stmtPerDag = $this->pdo->prepare("
                SELECT datum, SUM(antal) AS dag_antal
                FROM kassationsregistrering
                WHERE orsak_id = :cause_id
                  AND datum BETWEEN :from_date AND :to_date
                GROUP BY datum
                ORDER BY datum ASC
            ");
            $stmtPerDag->execute([
                ':cause_id'  => $causeId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $perDag = $stmtPerDag->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($perDag as &$pd) {
                $pd['dag_antal'] = (int)$pd['dag_antal'];
            }
            unset($pd);

            // Operatörer — koppla skiftraknare till operatörer i rebotling_ibc
            $skiftList = array_filter(array_unique(array_column($detaljer, 'skiftraknare')));
            $operatorer = [];
            if (!empty($skiftList)) {
                $placeholders = implode(',', array_fill(0, count($skiftList), '?'));
                try {
                    $stmtOps = $this->pdo->prepare("
                        SELECT
                            i.skiftraknare,
                            DATE(i.datum) AS datum,
                            o1.name AS op1_namn,
                            o2.name AS op2_namn,
                            o3.name AS op3_namn,
                            i.op1, i.op2, i.op3
                        FROM rebotling_ibc i
                        LEFT JOIN operators o1 ON o1.id = i.op1
                        LEFT JOIN operators o2 ON o2.id = i.op2
                        LEFT JOIN operators o3 ON o3.id = i.op3
                        WHERE i.skiftraknare IN ({$placeholders})
                        GROUP BY i.skiftraknare, DATE(i.datum), i.op1, i.op2, i.op3
                        ORDER BY i.skiftraknare
                    ");
                    $stmtOps->execute(array_values($skiftList));
                    $opRows = $stmtOps->fetchAll(\PDO::FETCH_ASSOC);

                    // Bygg en map: skiftraknare → lista av operatörsnamn
                    $skiftOpMap = [];
                    foreach ($opRows as $op) {
                        $sk = (int)$op['skiftraknare'];
                        if (!isset($skiftOpMap[$sk])) $skiftOpMap[$sk] = [];
                        $names = array_filter([
                            $op['op1_namn'] ?? null,
                            $op['op2_namn'] ?? null,
                            $op['op3_namn'] ?? null,
                        ]);
                        foreach ($names as $n) {
                            if (!in_array($n, $skiftOpMap[$sk])) {
                                $skiftOpMap[$sk][] = $n;
                            }
                        }
                    }

                    foreach ($skiftList as $sk) {
                        $operatorer[] = [
                            'skiftraknare' => $sk,
                            'operatorer'   => $skiftOpMap[$sk] ?? [],
                        ];
                    }
                } catch (\PDOException $e) {
                    // operators-tabellen eller kolumner saknas — ignorera
                    error_log('KassationsanalysController::getDrilldown (operators): ' . $e->getMessage());
                }
            }

            // Sammanfattning
            $totalAntal = array_sum(array_column($detaljer, 'antal'));

            $this->sendSuccess([
                'cause_id'    => $causeId,
                'cause_namn'  => $orsakRow['namn'],
                'days'        => $days,
                'from'        => $fromDate,
                'to'          => $toDate,
                'total_antal' => $totalAntal,
                'detaljer'    => $detaljer,
                'per_dag'     => $perDag,
                'operatorer'  => $operatorer,
                'har_data'    => !empty($detaljer),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getDrilldown: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: overview  (utökad KPI-sammanfattning med kostnad)
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=overview&days=30
     * Returnerar 4 KPI:er:
     *   1. total_kasserade — totalt antal kasserade IBC
     *   2. kassationsgrad_pct — kasserade / total produktion %
     *   3. vanligaste_orsak — namn + antal
     *   4. uppskattad_kostnad — antal × enhetskostnad (SEK)
     */
    private function getOverview(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $prevTo   = $fromDate;
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        // Uppskattad kostnad per kasserad IBC (SEK) — konfigurerbar, defaultvärde 850 SEK
        $kostnadPerIbc = 850;

        try {
            // Kasserade IBC från PLC-data
            $kassaradeIbc    = $this->getTotalKasserade($fromDate, $toDate);
            $totalProduktion = $this->getTotalProduktion($fromDate, $toDate);
            $kassationsGrad  = $totalProduktion > 0
                ? round($kassaradeIbc / $totalProduktion * 100, 2)
                : 0.0;

            // Föregående period
            $prevKasserade   = $this->getTotalKasserade($prevFrom, $prevTo);
            $prevProduktion  = $this->getTotalProduktion($prevFrom, $prevTo);
            $prevGrad        = $prevProduktion > 0
                ? round($prevKasserade / $prevProduktion * 100, 2)
                : 0.0;

            // Registrerade kassationer (detaljerad)
            $stmtReg = $this->pdo->prepare("
                SELECT COALESCE(SUM(antal), 0) AS total
                FROM kassationsregistrering
                WHERE datum BETWEEN :from_date AND :to_date
            ");
            $stmtReg->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $totalRegistrerade = (int)$stmtReg->fetchColumn();

            // Topp-orsak
            $stmtTopp = $this->pdo->prepare("
                SELECT t.namn, COALESCE(SUM(r.antal), 0) AS antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY antal DESC
                LIMIT 1
            ");
            $stmtTopp->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $toppRow = $stmtTopp->fetch(\PDO::FETCH_ASSOC);

            // Uppskattad kostnad
            $uppskattadKostnad = $kassaradeIbc * $kostnadPerIbc;

            // Trend
            $gradDiff  = round($kassationsGrad - $prevGrad, 2);
            $gradTrend = 'stable';
            if ($gradDiff > 0.5) $gradTrend = 'up';
            elseif ($gradDiff < -0.5) $gradTrend = 'down';

            $antalDiff  = $kassaradeIbc - $prevKasserade;
            $antalTrend = 'stable';
            if ($antalDiff > 2) $antalTrend = 'up';
            elseif ($antalDiff < -2) $antalTrend = 'down';

            $this->sendSuccess([
                'days'                => $days,
                'period_from'         => $fromDate,
                'period_to'           => $toDate,
                // KPI 1: Total kasserade
                'total_kasserade'     => $kassaradeIbc,
                'total_registrerade'  => $totalRegistrerade,
                'prev_kasserade'      => $prevKasserade,
                'antal_trend'         => $antalTrend,
                'antal_diff'          => $antalDiff,
                // KPI 2: Kassationsgrad
                'kassationsgrad_pct'  => $kassationsGrad,
                'prev_grad_pct'       => $prevGrad,
                'grad_trend'          => $gradTrend,
                'grad_diff'           => $gradDiff,
                // KPI 3: Vanligaste orsak
                'vanligaste_orsak'    => $toppRow ? $toppRow['namn'] : null,
                'vanligaste_antal'    => $toppRow ? (int)$toppRow['antal'] : 0,
                // KPI 4: Kostnad
                'uppskattad_kostnad'  => $uppskattadKostnad,
                'kostnad_per_ibc'     => $kostnadPerIbc,
                // Total produktion
                'total_produktion'    => $totalProduktion,
                'prev_produktion'     => $prevProduktion,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getOverview: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: by-period  (vecka/månad-gruppering per orsak)
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=by-period&days=90&group=week
     * group: 'week' | 'month'
     * Returnerar Chart.js-vänlig data med labels + datasets (topp 5 orsaker)
     */
    private function getByPeriod(): void {
        $days     = $this->getDays();
        $group    = $_GET['group'] ?? 'week';
        if (!in_array($group, ['week', 'month'])) $group = 'week';
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // SQL-gruppering beroende på vald period
            if ($group === 'week') {
                $groupExpr  = "CONCAT(YEAR(datum), '-V', LPAD(WEEK(datum, 3), 2, '0'))";
                $orderExpr  = "YEAR(datum), WEEK(datum, 3)";
            } else {
                $groupExpr  = "DATE_FORMAT(datum, '%Y-%m')";
                $orderExpr  = "DATE_FORMAT(datum, '%Y-%m')";
            }

            // Topp 5 orsaker i perioden
            $stmtTop = $this->pdo->prepare("
                SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS total_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY total_antal DESC
                LIMIT 5
            ");
            $stmtTop->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $topOrsaker = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($topOrsaker)) {
                $this->sendSuccess([
                    'days' => $days, 'group' => $group,
                    'labels' => [], 'datasets' => [], 'har_data' => false,
                ]);
                return;
            }

            $topIds = array_column($topOrsaker, 'id');
            $ph = implode(',', array_fill(0, count($topIds), '?'));

            // Grupperad data
            $sql = "
                SELECT {$groupExpr} AS period_label, orsak_id, SUM(antal) AS antal
                FROM kassationsregistrering
                WHERE datum BETWEEN ? AND ?
                  AND orsak_id IN ({$ph})
                GROUP BY period_label, orsak_id
                ORDER BY {$orderExpr} ASC
            ";
            $stmtData = $this->pdo->prepare($sql);
            $params = array_merge([$fromDate, $toDate], $topIds);
            $stmtData->execute($params);
            $rows = $stmtData->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg labels + data-map
            $periodMap = [];
            foreach ($rows as $row) {
                $p   = $row['period_label'];
                $oid = (int)$row['orsak_id'];
                if (!isset($periodMap[$p])) $periodMap[$p] = [];
                $periodMap[$p][$oid] = (int)$row['antal'];
            }
            $labels = array_keys($periodMap);

            // Färgpalett
            $palette = [
                'rgba(252,129,129,0.85)',
                'rgba(246,173,85,0.85)',
                'rgba(104,211,145,0.85)',
                'rgba(99,179,237,0.85)',
                'rgba(183,148,246,0.85)',
            ];

            $datasets = [];
            foreach ($topOrsaker as $i => $orsak) {
                $oid  = (int)$orsak['id'];
                $data = [];
                foreach ($labels as $lbl) {
                    $data[] = $periodMap[$lbl][$oid] ?? 0;
                }
                $color = $palette[$i % count($palette)];
                $datasets[] = [
                    'label'           => $orsak['namn'],
                    'orsakId'         => $oid,
                    'data'            => $data,
                    'backgroundColor' => $color,
                    'borderColor'     => str_replace('0.85', '1', $color),
                    'borderWidth'     => 1,
                ];
            }

            $this->sendSuccess([
                'days'     => $days,
                'group'    => $group,
                'from'     => $fromDate,
                'to'       => $toDate,
                'labels'   => $labels,
                'datasets' => $datasets,
                'har_data' => !empty($labels),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getByPeriod: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: details  (filtrbar detaljlista)
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=details&days=30&orsak=X&operator=Y
     * Returnerar alla kassationsregistreringar med filtermöjligheter.
     */
    private function getDetails(): void {
        $days      = $this->getDays();
        $toDate    = date('Y-m-d');
        $fromDate  = date('Y-m-d', strtotime("-{$days} days"));
        $orsakId   = isset($_GET['orsak']) && $_GET['orsak'] !== '' ? intval($_GET['orsak']) : null;
        $operatorNamn = isset($_GET['operator']) && $_GET['operator'] !== '' ? trim($_GET['operator']) : null;

        $kostnadPerIbc = 850;

        try {
            // Bygg WHERE dynamiskt
            $where  = "r.datum BETWEEN :from_date AND :to_date";
            $params = [':from_date' => $fromDate, ':to_date' => $toDate];

            if ($orsakId !== null) {
                $where .= " AND r.orsak_id = :orsak_id";
                $params[':orsak_id'] = $orsakId;
            }

            $sql = "
                SELECT
                    r.id,
                    r.datum,
                    r.skiftraknare,
                    r.antal,
                    r.kommentar,
                    r.created_at,
                    t.namn AS orsak_namn,
                    u.username AS registrerad_av
                FROM kassationsregistrering r
                LEFT JOIN kassationsorsak_typer t ON t.id = r.orsak_id
                LEFT JOIN users u ON u.id = r.registrerad_av
                WHERE {$where}
                ORDER BY r.datum DESC, r.created_at DESC
                LIMIT 500
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Slå upp operatörer via skifträknare
            $skiftList = array_filter(array_unique(array_column($rows, 'skiftraknare')));
            $opMap = [];
            if (!empty($skiftList)) {
                $ph = implode(',', array_fill(0, count($skiftList), '?'));
                try {
                    $stmtOps = $this->pdo->prepare("
                        SELECT i.skiftraknare, o1.name AS op1, o2.name AS op2, o3.name AS op3
                        FROM rebotling_ibc i
                        LEFT JOIN operators o1 ON o1.id = i.op1
                        LEFT JOIN operators o2 ON o2.id = i.op2
                        LEFT JOIN operators o3 ON o3.id = i.op3
                        WHERE i.skiftraknare IN ({$ph})
                        GROUP BY i.skiftraknare, i.op1, i.op2, i.op3
                    ");
                    $stmtOps->execute(array_values($skiftList));
                    foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                        $sk = (int)$op['skiftraknare'];
                        $names = array_filter([$op['op1'], $op['op2'], $op['op3']]);
                        if (!isset($opMap[$sk])) $opMap[$sk] = [];
                        foreach ($names as $n) {
                            if (!in_array($n, $opMap[$sk])) $opMap[$sk][] = $n;
                        }
                    }
                } catch (\PDOException $e) {
                    // Kan ignoreras om operators saknas
                }
            }

            // Filtrera på operatör (om angivet) — efter uppslagning
            $result = [];
            foreach ($rows as &$r) {
                $sk = $r['skiftraknare'] !== null ? (int)$r['skiftraknare'] : null;
                $ops = $sk !== null ? ($opMap[$sk] ?? []) : [];
                $r['operatorer']       = implode(', ', $ops);
                $r['uppskattad_kostnad'] = (int)$r['antal'] * $kostnadPerIbc;
                $r['id']              = (int)$r['id'];
                $r['antal']           = (int)$r['antal'];
                $r['skiftraknare']    = $sk;

                // Filtrera på operatörsnamn
                if ($operatorNamn !== null) {
                    $matchOp = false;
                    foreach ($ops as $opName) {
                        if (mb_stripos($opName, $operatorNamn) !== false) {
                            $matchOp = true;
                            break;
                        }
                    }
                    if (!$matchOp) continue;
                }
                $result[] = $r;
            }
            unset($r);

            // Orsaker (för filterdropdown)
            $stmtOrsaker = $this->pdo->prepare("
                SELECT id, namn FROM kassationsorsak_typer WHERE aktiv = 1 ORDER BY sortorder, namn
            ");
            $stmtOrsaker->execute();
            $orsakerLista = $stmtOrsaker->fetchAll(\PDO::FETCH_ASSOC);

            // Unika operatörer (för filterdropdown)
            $allOps = [];
            foreach ($opMap as $ops) {
                foreach ($ops as $n) {
                    if (!in_array($n, $allOps)) $allOps[] = $n;
                }
            }
            sort($allOps);

            $this->sendSuccess([
                'days'      => $days,
                'from'      => $fromDate,
                'to'        => $toDate,
                'total'     => count($result),
                'detaljer'  => $result,
                'orsaker'   => $orsakerLista,
                'operatorer' => $allOps,
                'har_data'  => !empty($result),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getDetails: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: trend-rate  (kassationsgrad % per vecka med trendlinje)
    // ================================================================

    /**
     * GET ?action=kassationsanalys&run=trend-rate&days=90
     * Returnerar kassationsgrad (%) per vecka plus glidande medelvärde.
     */
    private function getTrendRate(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Per vecka: kasserade vs totalt producerade
            $stmt = $this->pdo->prepare("
                SELECT
                    CONCAT(YEAR(sub.datum), '-V', LPAD(WEEK(sub.datum, 3), 2, '0')) AS vecka,
                    MIN(sub.datum) AS vecka_start,
                    SUM(sub.shift_ej_ok) AS kasserade,
                    SUM(sub.shift_ok) + SUM(sub.shift_ej_ok) AS totalt
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS sub
                GROUP BY YEAR(sub.datum), WEEK(sub.datum, 3)
                ORDER BY MIN(sub.datum) ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $veckor = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $labels = [];
            $rates  = [];
            foreach ($veckor as $v) {
                $labels[] = $v['vecka'];
                $totalt   = (int)$v['totalt'];
                $kass     = (int)$v['kasserade'];
                $rates[]  = $totalt > 0 ? round($kass / $totalt * 100, 2) : 0.0;
            }

            // Glidande medelvärde (4 veckors fönster)
            $movingAvg = [];
            $window    = 4;
            for ($i = 0; $i < count($rates); $i++) {
                $start = max(0, $i - $window + 1);
                $slice = array_slice($rates, $start, $i - $start + 1);
                $movingAvg[] = round(array_sum($slice) / count($slice), 2);
            }

            // Enkel linjär trendlinje (least squares)
            $n = count($rates);
            $trendline = [];
            if ($n >= 2) {
                $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
                for ($i = 0; $i < $n; $i++) {
                    $sumX  += $i;
                    $sumY  += $rates[$i];
                    $sumXY += $i * $rates[$i];
                    $sumX2 += $i * $i;
                }
                $denom = ($n * $sumX2 - $sumX * $sumX);
                if ($denom != 0) {
                    $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
                    $intercept = ($sumY - $slope * $sumX) / $n;
                    for ($i = 0; $i < $n; $i++) {
                        $trendline[] = round($intercept + $slope * $i, 2);
                    }
                }
            }

            $this->sendSuccess([
                'days'       => $days,
                'from'       => $fromDate,
                'to'         => $toDate,
                'labels'     => $labels,
                'rates'      => $rates,
                'moving_avg' => $movingAvg,
                'trendline'  => $trendline,
                'har_data'   => !empty($labels),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getTrendRate: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: sammanfattning — KPI-data for kassationsanalys-sidan
    // ================================================================

    private function getSammanfattning(): void {
        try {
            $periods = [7, 30, 90];
            $result  = [];
            $toDate  = date('Y-m-d');

            foreach ($periods as $days) {
                $fromDate = date('Y-m-d', strtotime("-{$days} days"));
                $prevTo   = $fromDate;
                $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

                $kasserade   = $this->getTotalKasserade($fromDate, $toDate);
                $produktion  = $this->getTotalProduktion($fromDate, $toDate);
                $andel       = $produktion > 0 ? round($kasserade / $produktion * 100, 2) : 0.0;

                $prevKasserade  = $this->getTotalKasserade($prevFrom, $prevTo);
                $prevProduktion = $this->getTotalProduktion($prevFrom, $prevTo);
                $prevAndel      = $prevProduktion > 0 ? round($prevKasserade / $prevProduktion * 100, 2) : 0.0;

                $diff  = round($andel - $prevAndel, 2);
                $trend = 'stable';
                if ($diff > 0.3) $trend = 'up';
                elseif ($diff < -0.3) $trend = 'down';

                $result[$days] = [
                    'dagar'            => $days,
                    'kasserade'        => $kasserade,
                    'produktion'       => $produktion,
                    'andel_pct'        => $andel,
                    'prev_andel_pct'   => $prevAndel,
                    'diff_pct'         => $diff,
                    'trend'            => $trend,
                ];
            }

            // Varsta station (senaste 30d)
            $varstaStation = $this->getVarstaStation(30);

            $this->sendSuccess([
                'perioder'       => $result,
                'varsta_station' => $varstaStation,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getSammanfattning: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Hitta stationen med hogst kassationsandel.
     */
    private function getVarstaStation(int $days): ?array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    station,
                    SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) AS kasserade,
                    COUNT(*) AS totalt,
                    ROUND(SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS andel_pct
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                  AND datum BETWEEN :from_date AND :to_date
                GROUP BY station
                HAVING totalt > 0
                ORDER BY andel_pct DESC
                LIMIT 1
            ");
            $stmt->execute([':from_date' => $fromDate . ' 00:00:00', ':to_date' => $toDate . ' 23:59:59']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'station'   => $row['station'],
                    'kasserade' => (int)$row['kasserade'],
                    'totalt'    => (int)$row['totalt'],
                    'andel_pct' => (float)$row['andel_pct'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getVarstaStation: ' . $e->getMessage());
        }
        return null;
    }

    // ================================================================
    // ENDPOINT: orsaker — kassationsorsaker med trend
    // ================================================================

    private function getOrsaker(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $prevTo   = $fromDate;
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        try {
            // Nuvarande period — fran kassationsregistrering
            $stmtCurr = $this->pdo->prepare("
                SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY antal DESC
            ");
            $stmtCurr->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $orsaker = $stmtCurr->fetchAll(\PDO::FETCH_ASSOC);

            $total = array_sum(array_column($orsaker, 'antal'));

            // Foregaende period
            $stmtPrev = $this->pdo->prepare("
                SELECT t.id, COALESCE(SUM(r.antal), 0) AS prev_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :prev_from AND :prev_to
                WHERE t.aktiv = 1
                GROUP BY t.id
            ");
            $stmtPrev->execute([':prev_from' => $prevFrom, ':prev_to' => $prevTo]);
            $prevMap = [];
            foreach ($stmtPrev->fetchAll(\PDO::FETCH_ASSOC) as $pr) {
                $prevMap[(int)$pr['id']] = (int)$pr['prev_antal'];
            }

            $cumulative = 0.0;
            $result     = [];
            foreach ($orsaker as $o) {
                $antal      = (int)$o['antal'];
                $andel      = $total > 0 ? round($antal / $total * 100, 1) : 0.0;
                $cumulative += $andel;
                $prevAntal  = $prevMap[(int)$o['id']] ?? 0;
                $trend      = $antal > $prevAntal ? 'up' : ($antal < $prevAntal ? 'down' : 'stable');

                $result[] = [
                    'id'            => (int)$o['id'],
                    'namn'          => $o['namn'],
                    'antal'         => $antal,
                    'andel_pct'     => $andel,
                    'kumulativ_pct' => round($cumulative, 1),
                    'prev_antal'    => $prevAntal,
                    'trend'         => $trend,
                ];
            }

            $this->sendSuccess([
                'days'    => $days,
                'total'   => (int)$total,
                'orsaker' => $result,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getOrsaker: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: orsaker-trend — kassationsorsaker over tid
    // ================================================================

    private function getOrsakerTrend(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $group    = ($_GET['group'] ?? 'day') === 'week' ? 'week' : 'day';

        try {
            // Top 5 orsaker
            $stmtTop = $this->pdo->prepare("
                SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS total_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY total_antal DESC
                LIMIT 5
            ");
            $stmtTop->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $topOrsaker = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($topOrsaker)) {
                $this->sendSuccess([
                    'days' => $days, 'group' => $group,
                    'labels' => [], 'datasets' => [], 'har_data' => false,
                ]);
                return;
            }

            $topIds = array_column($topOrsaker, 'id');
            $ph = implode(',', array_fill(0, count($topIds), '?'));

            if ($group === 'week') {
                $groupExpr = "CONCAT(YEAR(datum), '-V', LPAD(WEEK(datum, 3), 2, '0'))";
                $orderExpr = "YEAR(datum), WEEK(datum, 3)";
            } else {
                $groupExpr = "DATE(datum)";
                $orderExpr = "DATE(datum)";
            }

            $sql = "
                SELECT {$groupExpr} AS period_label, orsak_id, SUM(antal) AS antal
                FROM kassationsregistrering
                WHERE datum BETWEEN ? AND ?
                  AND orsak_id IN ({$ph})
                GROUP BY period_label, orsak_id
                ORDER BY {$orderExpr} ASC
            ";
            $stmtData = $this->pdo->prepare($sql);
            $stmtData->execute(array_merge([$fromDate, $toDate], $topIds));
            $rows = $stmtData->fetchAll(\PDO::FETCH_ASSOC);

            $periodMap = [];
            foreach ($rows as $row) {
                $p   = $row['period_label'];
                $oid = (int)$row['orsak_id'];
                if (!isset($periodMap[$p])) $periodMap[$p] = [];
                $periodMap[$p][$oid] = (int)$row['antal'];
            }

            // Generera labels
            if ($group === 'day') {
                $labels  = [];
                $current = new \DateTime($fromDate);
                $end     = new \DateTime($toDate);
                while ($current <= $end) {
                    $labels[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            } else {
                $labels = array_keys($periodMap);
            }

            $palette = [
                '#fc8181', '#f6ad55', '#68d391', '#63b3ed', '#b794f6',
            ];

            $datasets = [];
            foreach ($topOrsaker as $i => $orsak) {
                $oid  = (int)$orsak['id'];
                $data = [];
                foreach ($labels as $lbl) {
                    $data[] = $periodMap[$lbl][$oid] ?? 0;
                }
                $datasets[] = [
                    'label'       => $orsak['namn'],
                    'data'        => $data,
                    'borderColor' => $palette[$i % count($palette)],
                ];
            }

            $this->sendSuccess([
                'days'     => $days,
                'group'    => $group,
                'labels'   => $labels,
                'datasets' => $datasets,
                'har_data' => !empty($labels),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getOrsakerTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: per-station — kassationsandel per station
    // ================================================================

    private function getPerStation(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    station,
                    SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) AS kasserade,
                    SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS godkanda,
                    COUNT(*) AS totalt,
                    ROUND(SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS andel_pct
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                  AND datum BETWEEN :from_date AND :to_date
                GROUP BY station
                ORDER BY andel_pct DESC
            ");
            $stmt->execute([
                ':from_date' => $fromDate . ' 00:00:00',
                ':to_date'   => $toDate . ' 23:59:59',
            ]);
            $stationer = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($stationer as &$s) {
                $s['kasserade']  = (int)$s['kasserade'];
                $s['godkanda']   = (int)$s['godkanda'];
                $s['totalt']     = (int)$s['totalt'];
                $s['andel_pct']  = (float)$s['andel_pct'];
            }
            unset($s);

            $this->sendSuccess([
                'days'      => $days,
                'stationer' => $stationer,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getPerStation: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: per-operator — kassationsandel per operator
    // ================================================================

    private function getPerOperator(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Hamta alla IBC-rader med operatorsinfo i perioden
            $stmt = $this->pdo->prepare("
                SELECT
                    i.ok,
                    o1.name AS op1_namn,
                    o2.name AS op2_namn,
                    o3.name AS op3_namn
                FROM rebotling_ibc i
                LEFT JOIN operators o1 ON o1.id = i.op1
                LEFT JOIN operators o2 ON o2.id = i.op2
                LEFT JOIN operators o3 ON o3.id = i.op3
                WHERE i.datum BETWEEN :from_date AND :to_date
                  AND (i.op1 IS NOT NULL OR i.op2 IS NOT NULL OR i.op3 IS NOT NULL)
            ");
            $stmt->execute([
                ':from_date' => $fromDate . ' 00:00:00',
                ':to_date'   => $toDate . ' 23:59:59',
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Aggregera per operator
            $opData = [];
            foreach ($rows as $row) {
                $ok = (int)$row['ok'];
                $ops = array_filter([$row['op1_namn'], $row['op2_namn'], $row['op3_namn']]);
                foreach ($ops as $opName) {
                    if (!isset($opData[$opName])) {
                        $opData[$opName] = ['totalt' => 0, 'kasserade' => 0];
                    }
                    $opData[$opName]['totalt']++;
                    if ($ok === 0) $opData[$opName]['kasserade']++;
                }
            }

            $result = [];
            foreach ($opData as $namn => $data) {
                $andel = $data['totalt'] > 0
                    ? round($data['kasserade'] / $data['totalt'] * 100, 1)
                    : 0.0;
                $result[] = [
                    'operator'   => $namn,
                    'kasserade'  => $data['kasserade'],
                    'totalt'     => $data['totalt'],
                    'andel_pct'  => $andel,
                ];
            }

            // Sortera efter andel DESC
            usort($result, fn($a, $b) => $b['andel_pct'] <=> $a['andel_pct']);

            // Lagg till ranking
            foreach ($result as $i => &$r) {
                $r['ranking'] = $i + 1;
            }
            unset($r);

            $this->sendSuccess([
                'days'       => $days,
                'operatorer' => $result,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getPerOperator: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: detaljer — lista kasserade IBCer
    // ================================================================

    private function getDetaljer(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $limit    = max(10, min(500, intval($_GET['limit'] ?? 100)));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    i.id,
                    i.datum,
                    i.station,
                    i.ok,
                    i.skiftraknare,
                    o1.name AS op1_namn,
                    o2.name AS op2_namn,
                    o3.name AS op3_namn
                FROM rebotling_ibc i
                LEFT JOIN operators o1 ON o1.id = i.op1
                LEFT JOIN operators o2 ON o2.id = i.op2
                LEFT JOIN operators o3 ON o3.id = i.op3
                WHERE i.ok = 0
                  AND i.datum BETWEEN :from_date AND :to_date
                ORDER BY i.datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':from_date', $fromDate . ' 00:00:00', \PDO::PARAM_STR);
            $stmt->bindValue(':to_date', $toDate . ' 23:59:59', \PDO::PARAM_STR);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Forsok koppla kassationsorsak via skiftraknare + datum
            $skiftList = array_filter(array_unique(array_column($rows, 'skiftraknare')));
            $orsakMap  = [];
            if (!empty($skiftList)) {
                $ph = implode(',', array_fill(0, count($skiftList), '?'));
                try {
                    $stmtOrsak = $this->pdo->prepare("
                        SELECT r.skiftraknare, t.namn AS orsak_namn
                        FROM kassationsregistrering r
                        LEFT JOIN kassationsorsak_typer t ON t.id = r.orsak_id
                        WHERE r.skiftraknare IN ({$ph})
                        GROUP BY r.skiftraknare, t.namn
                    ");
                    $stmtOrsak->execute(array_values($skiftList));
                    foreach ($stmtOrsak->fetchAll(\PDO::FETCH_ASSOC) as $or) {
                        $sk = (int)$or['skiftraknare'];
                        if (!isset($orsakMap[$sk])) $orsakMap[$sk] = [];
                        if ($or['orsak_namn'] && !in_array($or['orsak_namn'], $orsakMap[$sk])) {
                            $orsakMap[$sk][] = $or['orsak_namn'];
                        }
                    }
                } catch (\PDOException $e) {
                    // Ignorera om tabellen saknas
                }
            }

            $result = [];
            foreach ($rows as $r) {
                $ops = array_filter([$r['op1_namn'], $r['op2_namn'], $r['op3_namn']]);
                $sk  = $r['skiftraknare'] !== null ? (int)$r['skiftraknare'] : null;
                $orsaker = $sk !== null ? ($orsakMap[$sk] ?? []) : [];

                $result[] = [
                    'id'       => (int)$r['id'],
                    'datum'    => $r['datum'],
                    'station'  => $r['station'] ?? '',
                    'operator' => implode(', ', $ops) ?: 'Okand',
                    'orsak'    => implode(', ', $orsaker) ?: 'Ej angiven',
                ];
            }

            $this->sendSuccess([
                'days'    => $days,
                'total'   => count($result),
                'ibc'     => $result,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsanalysController::getDetaljer: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
