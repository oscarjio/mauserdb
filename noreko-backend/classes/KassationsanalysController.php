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
}
