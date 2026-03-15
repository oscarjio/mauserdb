<?php
/**
 * KassationsorsakController.php
 * Kassationsorsak-statistik — Pareto-diagram + trendanalys per kassationsorsak
 *
 * Endpoints via ?action=kassationsorsakstatistik&run=XXX:
 *   - run=overview        -> KPI:er: totalt kasserade, vanligaste orsak, trend, unika orsaker
 *   - run=pareto          -> orsaker sorterade fallande med antal, procent, kumulativ%
 *   - run=trend           -> daglig tidsserie per orsak
 *   - run=per-operator    -> kassationsorsak-matris per operatör
 *   - run=per-shift       -> kassationsorsak-matris per skift (dag/kväll/natt)
 *   - run=drilldown       -> detaljlista per orsak: datum, operatör, skift, antal
 *
 * Tabeller:
 *   kassationsregistrering  (id, datum, skiftraknare, skift_typ, orsak_id, antal, kommentar, registrerad_av, created_at)
 *   kassationsorsak_typer   (id, namn, aktiv)
 *   rebotling_ibc           (ibc_ok, ibc_ej_ok, datum, skiftraknare, op1, op2, op3)
 *   operators               (id, name, number, active)
 */
class KassationsorsakController {
    private $pdo;

    private const SHIFT_STARTS = [
        'dag'    => '06:00',
        'kväll'  => '14:00',
        'natt'   => '22:00',
    ];

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
            case 'overview':      $this->getOverview();     break;
            case 'pareto':        $this->getPareto();       break;
            case 'trend':         $this->getTrend();        break;
            case 'per-operator':  $this->getPerOperator();  break;
            case 'per-shift':     $this->getPerShift();     break;
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

    private function getDateRange(int $days): array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        return [$fromDate, $toDate];
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
     * Hämta totalt producerade IBC (ok + ej ok) från rebotling_ibc för perioden.
     */
    private function getTotalProducerade(string $fromDate, string $toDate): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok), 0) AS totalt_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS totalt_ej_ok
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch();
            return [
                'ok'     => (int)($row['totalt_ok'] ?? 0),
                'ej_ok'  => (int)($row['totalt_ej_ok'] ?? 0),
            ];
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getTotalProducerade: ' . $e->getMessage());
            return ['ok' => 0, 'ej_ok' => 0];
        }
    }

    /**
     * Härleda skifttyp från skifträknare (1=dag, 2=kväll, 3=natt)
     */
    private function skiftTypFromRaknare(?int $raknare): string {
        switch ($raknare) {
            case 1: return 'dag';
            case 2: return 'kväll';
            case 3: return 'natt';
            default: return 'okänd';
        }
    }

    /**
     * Hämta operatörsnamn som map number => name
     */
    private function getOperatorNames(): array {
        try {
            $stmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1");
            $map = [];
            while ($row = $stmt->fetch()) {
                $map[(int)$row['number']] = $row['name'];
            }
            return $map;
        } catch (\PDOException) {
            return [];
        }
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        // Föregående period
        $prevTo   = date('Y-m-d', strtotime("-" . ($days + 1) . " days"));
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        // Nuvarande period - kassationsregistreringar
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(antal), 0) AS total_kasserade
                FROM kassationsregistrering
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $totalKasserade = (int)$stmt->fetchColumn();

            // Föregående period
            $stmt->execute([':from_date' => $prevFrom, ':to_date' => $prevTo]);
            $prevKasserade = (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getOverview: ' . $e->getMessage());
            $totalKasserade = 0;
            $prevKasserade = 0;
        }

        // Totalt producerade
        $current = $this->getTotalProducerade($fromDate, $toDate);
        $totalProducerade = $current['ok'] + $current['ej_ok'];
        $kassationsGrad = $totalProducerade > 0 ? round(($totalKasserade / $totalProducerade) * 100, 2) : 0;

        $prev = $this->getTotalProducerade($prevFrom, $prevTo);
        $prevProducerade = $prev['ok'] + $prev['ej_ok'];
        $prevKassationsGrad = $prevProducerade > 0 ? round(($prevKasserade / $prevProducerade) * 100, 2) : 0;

        // Trend
        $trendDiff = $kassationsGrad - $prevKassationsGrad;
        if ($trendDiff < -0.01) {
            $trendDirection = 'down';   // bättre
        } elseif ($trendDiff > 0.01) {
            $trendDirection = 'up';     // sämre
        } else {
            $trendDirection = 'flat';
        }

        // Vanligaste orsak
        $topReason = null;
        $uniqueReasons = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(kt.namn, 'Okänd') AS reason,
                    SUM(kr.antal) AS total_antal
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                GROUP BY kt.id, kt.namn
                ORDER BY total_antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();
            $uniqueReasons = count($rows);
            if (!empty($rows)) {
                $topReason = $rows[0]['reason'];
            }
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getOverview reasons: ' . $e->getMessage());
        }

        $this->sendSuccess([
            'days'                 => $days,
            'from_date'            => $fromDate,
            'to_date'              => $toDate,
            'total_kasserade'      => $totalKasserade,
            'total_producerade'    => $totalProducerade,
            'kassationsgrad'       => $kassationsGrad,
            'prev_kasserade'       => $prevKasserade,
            'prev_kassationsgrad'  => $prevKassationsGrad,
            'trend_diff'           => round($trendDiff, 2),
            'trend_direction'      => $trendDirection,
            'top_reason'           => $topReason,
            'unique_reasons'       => $uniqueReasons,
        ]);
    }

    // ================================================================
    // run=pareto — Pareto-data
    // ================================================================

    private function getPareto(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    kt.id AS orsak_id,
                    COALESCE(kt.namn, 'Okänd') AS orsak,
                    SUM(kr.antal) AS antal
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                GROUP BY kt.id, kt.namn
                ORDER BY antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            $totalAntal = array_sum(array_column($rows, 'antal'));
            $cumulative = 0;
            $pareto = [];

            foreach ($rows as $row) {
                $antal = (int)$row['antal'];
                $procent = $totalAntal > 0 ? round(($antal / $totalAntal) * 100, 1) : 0;
                $cumulative += $procent;

                $pareto[] = [
                    'orsak_id'      => (int)$row['orsak_id'],
                    'orsak'         => $row['orsak'],
                    'antal'         => $antal,
                    'procent'       => $procent,
                    'kumulativ_pct' => round($cumulative, 1),
                ];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'total'      => $totalAntal,
                'pareto'     => $pareto,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getPareto: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ================================================================
    // run=trend — Daglig tidsserie per orsak
    // ================================================================

    private function getTrend(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            // Hämta alla orsaker som finns i perioden
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT
                    kt.id AS orsak_id,
                    COALESCE(kt.namn, 'Okänd') AS orsak
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                ORDER BY orsak
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $orsaker = $stmt->fetchAll();

            // Hämta daglig data per orsak
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(kr.datum) AS dag,
                    kr.orsak_id,
                    COALESCE(kt.namn, 'Okänd') AS orsak,
                    SUM(kr.antal) AS antal
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                GROUP BY DATE(kr.datum), kr.orsak_id, kt.namn
                ORDER BY dag ASC, antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            // Bygg datumsekvens
            $dates = [];
            $d = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($d <= $end) {
                $dates[] = $d->format('Y-m-d');
                $d->modify('+1 day');
            }

            // Organisera per orsak
            $dataByOrsak = [];
            foreach ($rows as $row) {
                $key = (int)$row['orsak_id'];
                if (!isset($dataByOrsak[$key])) {
                    $dataByOrsak[$key] = [];
                }
                $dataByOrsak[$key][$row['dag']] = (int)$row['antal'];
            }

            // Bygg serier
            $series = [];
            foreach ($orsaker as $o) {
                $id = (int)$o['orsak_id'];
                $values = [];
                foreach ($dates as $date) {
                    $values[] = $dataByOrsak[$id][$date] ?? 0;
                }
                $series[] = [
                    'orsak_id' => $id,
                    'orsak'    => $o['orsak'],
                    'values'   => $values,
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'dates'     => $dates,
                'series'    => $series,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ================================================================
    // run=per-operator — Kassationsorsak-matris per operatör
    // ================================================================

    private function getPerOperator(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);
        $opNames = $this->getOperatorNames();

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    kr.registrerad_av AS op_id,
                    kr.orsak_id,
                    COALESCE(kt.namn, 'Okänd') AS orsak,
                    SUM(kr.antal) AS antal
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                  AND kr.registrerad_av IS NOT NULL
                GROUP BY kr.registrerad_av, kr.orsak_id, kt.namn
                ORDER BY kr.registrerad_av, antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            // Aggregera per operatör
            $operatorData = [];
            $orsakTotals = [];

            foreach ($rows as $row) {
                $opId = (int)$row['op_id'];
                $orsakNamn = $row['orsak'];
                $antal = (int)$row['antal'];

                if (!isset($operatorData[$opId])) {
                    $operatorData[$opId] = [
                        'op_id'   => $opId,
                        'op_namn' => $opNames[$opId] ?? ('Op. ' . $opId),
                        'total'   => 0,
                        'orsaker' => [],
                    ];
                }
                $operatorData[$opId]['total'] += $antal;
                $operatorData[$opId]['orsaker'][] = [
                    'orsak_id' => (int)$row['orsak_id'],
                    'orsak'    => $orsakNamn,
                    'antal'    => $antal,
                ];

                // Totaler per orsak (för snittberäkning)
                if (!isset($orsakTotals[$orsakNamn])) {
                    $orsakTotals[$orsakNamn] = 0;
                }
                $orsakTotals[$orsakNamn] += $antal;
            }

            // Beräkna snittandel per orsak
            $totalAll = array_sum($orsakTotals);
            $snittAndelar = [];
            foreach ($orsakTotals as $namn => $summa) {
                $snittAndelar[$namn] = $totalAll > 0 ? round(($summa / $totalAll) * 100, 1) : 0;
            }

            // Beräkna andel per operatör per orsak jämfört med snitt
            $result = [];
            foreach ($operatorData as $op) {
                $orsakerMedAndel = [];
                foreach ($op['orsaker'] as $o) {
                    $andel = $op['total'] > 0 ? round(($o['antal'] / $op['total']) * 100, 1) : 0;
                    $snitt = $snittAndelar[$o['orsak']] ?? 0;
                    $orsakerMedAndel[] = [
                        'orsak_id' => $o['orsak_id'],
                        'orsak'    => $o['orsak'],
                        'antal'    => $o['antal'],
                        'andel'    => $andel,
                        'snitt'    => $snitt,
                        'diff'     => round($andel - $snitt, 1),
                    ];
                }

                $result[] = [
                    'op_id'   => $op['op_id'],
                    'op_namn' => $op['op_namn'],
                    'total'   => $op['total'],
                    'orsaker' => $orsakerMedAndel,
                ];
            }

            // Sortera efter total fallande
            usort($result, fn($a, $b) => $b['total'] - $a['total']);

            $this->sendSuccess([
                'days'           => $days,
                'from_date'      => $fromDate,
                'to_date'        => $toDate,
                'operators'      => $result,
                'snitt_andelar'  => $snittAndelar,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getPerOperator: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ================================================================
    // run=per-shift — Kassationsorsak-matris per skift
    // ================================================================

    private function getPerShift(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            // Försök skift_typ först, annars härleda från skifträknare
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(kr.skift_typ,
                        CASE kr.skiftraknare
                            WHEN 1 THEN 'dag'
                            WHEN 2 THEN 'kväll'
                            WHEN 3 THEN 'natt'
                            ELSE 'okänd'
                        END
                    ) AS skift,
                    kr.orsak_id,
                    COALESCE(kt.namn, 'Okänd') AS orsak,
                    SUM(kr.antal) AS antal
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                GROUP BY skift, kr.orsak_id, kt.namn
                ORDER BY skift, antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            $shifts = [];
            foreach ($rows as $row) {
                $skift = $row['skift'];
                if (!isset($shifts[$skift])) {
                    $shifts[$skift] = [
                        'skift'   => $skift,
                        'total'   => 0,
                        'orsaker' => [],
                    ];
                }
                $antal = (int)$row['antal'];
                $shifts[$skift]['total'] += $antal;
                $shifts[$skift]['orsaker'][] = [
                    'orsak_id' => (int)$row['orsak_id'],
                    'orsak'    => $row['orsak'],
                    'antal'    => $antal,
                ];
            }

            // Beräkna andelar inom varje skift
            foreach ($shifts as &$shift) {
                foreach ($shift['orsaker'] as &$o) {
                    $o['andel'] = $shift['total'] > 0 ? round(($o['antal'] / $shift['total']) * 100, 1) : 0;
                }
            }

            // Sortera: dag, kväll, natt
            $sortOrder = ['dag' => 1, 'kväll' => 2, 'natt' => 3, 'okänd' => 4];
            $result = array_values($shifts);
            usort($result, fn($a, $b) => ($sortOrder[$a['skift']] ?? 9) - ($sortOrder[$b['skift']] ?? 9));

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'shifts'    => $result,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getPerShift: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ================================================================
    // run=drilldown — Detaljlista per orsak
    // ================================================================

    private function getDrilldown(): void {
        $days = $this->getDays();
        $orsakId = intval($_GET['orsak'] ?? 0);

        if ($orsakId <= 0) {
            $this->sendError('Orsak (orsak) krävs');
            return;
        }

        [$fromDate, $toDate] = $this->getDateRange($days);
        $opNames = $this->getOperatorNames();

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    kr.id,
                    DATE(kr.datum) AS dag,
                    kr.skiftraknare,
                    COALESCE(kr.skift_typ,
                        CASE kr.skiftraknare
                            WHEN 1 THEN 'dag'
                            WHEN 2 THEN 'kväll'
                            WHEN 3 THEN 'natt'
                            ELSE 'okänd'
                        END
                    ) AS skift,
                    kr.antal,
                    kr.kommentar,
                    kr.registrerad_av AS op_id,
                    kr.created_at,
                    COALESCE(kt.namn, 'Okänd') AS orsak
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE kr.orsak_id = :orsak_id
                  AND DATE(kr.datum) BETWEEN :from_date AND :to_date
                ORDER BY kr.datum DESC, kr.created_at DESC
            ");
            $stmt->execute([
                ':orsak_id'  => $orsakId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $rows = $stmt->fetchAll();

            $events = [];
            $dagStats = [];
            foreach ($rows as $row) {
                $opId = (int)$row['op_id'];
                $events[] = [
                    'id'        => (int)$row['id'],
                    'datum'     => $row['dag'],
                    'skift'     => $row['skift'],
                    'antal'     => (int)$row['antal'],
                    'kommentar' => $row['kommentar'],
                    'operator'  => $opNames[$opId] ?? ('Op. ' . $opId),
                    'op_id'     => $opId,
                    'created_at' => $row['created_at'],
                ];

                // Dagvis aggregering
                $dag = $row['dag'];
                if (!isset($dagStats[$dag])) {
                    $dagStats[$dag] = 0;
                }
                $dagStats[$dag] += (int)$row['antal'];
            }

            // Sortera dagsstats
            ksort($dagStats);
            $dagSeries = [];
            foreach ($dagStats as $datum => $antal) {
                $dagSeries[] = ['datum' => $datum, 'antal' => $antal];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'orsak_id'   => $orsakId,
                'events'     => $events,
                'dag_series' => $dagSeries,
                'total'      => count($events),
                'total_antal' => array_sum(array_column($events, 'antal')),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsorsakController::getDrilldown: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }
}
