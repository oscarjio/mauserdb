<?php
class VeckotrendController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handle() {
        $run = trim($_GET['run'] ?? '');

        if ($run === 'weekly-kpis') {
            $this->getWeeklyKpis();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Okänd run: ' . $run], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Returnerar 7 dagars KPI-data för sparkline-kort:
     *  - ibc_count      (IBC per dag)
     *  - snitt_cykeltid (snitt cykeltid i min per dag)
     *  - kvalitet_pct   (godkänd-andel per dag)
     *  - drifttid_pct   (drifttidsprocent per dag baserat på rebotling_skiftrapport)
     */
    public function getWeeklyKpis() {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate   = date('Y-m-d');

            // --- IBC per dag + snitt cykeltid + kvalitet ---
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok)            AS ibc_ok,
                    SUM(shift_ibc_ej_ok)         AS ibc_ej_ok,
                    ROUND(AVG(avg_cykeltid), 2)  AS snitt_cykeltid
                FROM (
                    SELECT
                        DATE(datum)                         AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))            AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0))         AS shift_ibc_ej_ok,
                        AVG(runtime_plc)                    AS avg_cykeltid
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= ?
                      AND DATE(datum) <= ?
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $ibcRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Drifttid från skiftrapport ---
            // Kolumner: drifttid_pct eller korrekt körtid / planerad tid
            // Försöker hämta drifttid_pct direkt om kolumnen finns,
            // annars beräknar vi från korttid_min / planerad_tid_min.
            $drifttidByDay = [];
            try {
                $stmtDrift = $this->pdo->prepare("
                    SELECT
                        DATE(skapad_datum) AS dag,
                        AVG(COALESCE(drifttid_pct, 0)) AS snitt_drifttid_pct
                    FROM rebotling_skiftrapport
                    WHERE DATE(skapad_datum) >= ?
                      AND DATE(skapad_datum) <= ?
                    GROUP BY DATE(skapad_datum)
                    ORDER BY dag ASC
                ");
                $stmtDrift->execute([$startDate, $endDate]);
                foreach ($stmtDrift->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $drifttidByDay[$row['dag']] = (float)$row['snitt_drifttid_pct'];
                }
            } catch (Exception $e) {
                // Kolumnen kanske inte finns — försök med alternativ beräkning
                try {
                    $stmtDrift2 = $this->pdo->prepare("
                        SELECT
                            DATE(skapad_datum) AS dag,
                            AVG(
                                CASE
                                    WHEN planerad_tid_min > 0
                                    THEN LEAST(100, ROUND((korttid_min / planerad_tid_min) * 100, 1))
                                    ELSE NULL
                                END
                            ) AS snitt_drifttid_pct
                        FROM rebotling_skiftrapport
                        WHERE DATE(skapad_datum) >= ?
                          AND DATE(skapad_datum) <= ?
                        GROUP BY DATE(skapad_datum)
                        ORDER BY dag ASC
                    ");
                    $stmtDrift2->execute([$startDate, $endDate]);
                    foreach ($stmtDrift2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $drifttidByDay[$row['dag']] = $row['snitt_drifttid_pct'] !== null
                            ? (float)$row['snitt_drifttid_pct']
                            : null;
                    }
                } catch (Exception $e2) {
                    // Tabellen saknas eller annan struktur — lämna tomt
                    error_log('VeckotrendController drifttid fallback failed: ' . $e2->getMessage());
                }
            }

            // Fyll i alla 7 dagar (även dagar utan data → null)
            $allDays = [];
            for ($i = 6; $i >= 0; $i--) {
                $allDays[] = date('Y-m-d', strtotime("-{$i} days"));
            }

            // Indexera ibc-data på dag
            $ibcByDay = [];
            foreach ($ibcRows as $row) {
                $ibcByDay[$row['dag']] = $row;
            }

            // Bygg per-KPI-arrays
            $ibcValues      = [];
            $cykeltidValues = [];
            $kvalitetValues = [];
            $drifttidValues = [];

            foreach ($allDays as $dag) {
                $row = $ibcByDay[$dag] ?? null;

                // IBC
                $ibcValues[] = $row ? (int)$row['ibc_ok'] : null;

                // Snitt cykeltid (min)
                $cykeltidValues[] = $row && $row['snitt_cykeltid'] > 0
                    ? round((float)$row['snitt_cykeltid'], 1)
                    : null;

                // Kvalitet %
                if ($row) {
                    $tot = (int)$row['ibc_ok'] + (int)$row['ibc_ej_ok'];
                    $kvalitetValues[] = $tot > 0
                        ? round(((int)$row['ibc_ok'] / $tot) * 100, 1)
                        : null;
                } else {
                    $kvalitetValues[] = null;
                }

                // Drifttid %
                $drifttidValues[] = $drifttidByDay[$dag] ?? null;
            }

            // Beräkna trend (jämför snitt senaste 3 dagar vs snitt föregående 4 dagar)
            $kpis = [
                [
                    'kpi'    => 'ibc_count',
                    'label'  => 'IBC per dag',
                    'unit'   => 'st',
                    'values' => $ibcValues,
                    'dates'  => $allDays,
                    'trend'  => $this->calcTrend($ibcValues),
                    'latest' => $this->lastNonNull($ibcValues),
                    'min'    => $this->minNonNull($ibcValues),
                    'max'    => $this->maxNonNull($ibcValues),
                ],
                [
                    'kpi'    => 'snitt_cykeltid',
                    'label'  => 'Snitt cykeltid',
                    'unit'   => 'min',
                    'values' => $cykeltidValues,
                    'dates'  => $allDays,
                    // För cykeltid är "ned" bra (kortare = bättre), invertera trend
                    'trend'  => $this->calcTrendInverted($cykeltidValues),
                    'latest' => $this->lastNonNull($cykeltidValues),
                    'min'    => $this->minNonNull($cykeltidValues),
                    'max'    => $this->maxNonNull($cykeltidValues),
                ],
                [
                    'kpi'    => 'kvalitet_pct',
                    'label'  => 'Kvalitetsprocent',
                    'unit'   => '%',
                    'values' => $kvalitetValues,
                    'dates'  => $allDays,
                    'trend'  => $this->calcTrend($kvalitetValues),
                    'latest' => $this->lastNonNull($kvalitetValues),
                    'min'    => $this->minNonNull($kvalitetValues),
                    'max'    => $this->maxNonNull($kvalitetValues),
                ],
                [
                    'kpi'    => 'drifttid_pct',
                    'label'  => 'Drifttid',
                    'unit'   => '%',
                    'values' => $drifttidValues,
                    'dates'  => $allDays,
                    'trend'  => $this->calcTrend($drifttidValues),
                    'latest' => $this->lastNonNull($drifttidValues),
                    'min'    => $this->minNonNull($drifttidValues),
                    'max'    => $this->maxNonNull($drifttidValues),
                ],
            ];

            // Lägg till pct-förändring vs förra veckan (senaste dag vs 7 dagar sedan)
            foreach ($kpis as &$kpi) {
                $kpi['change_pct'] = $this->calcChangePct($kpi['values']);
            }
            unset($kpi);

            echo json_encode([
                'success' => true,
                'from'    => $startDate,
                'to'      => $endDate,
                'kpis'    => $kpis,
            ]);

        } catch (Exception $e) {
            error_log('VeckotrendController::getWeeklyKpis error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel vid hämtning av vecko-KPI'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function calcTrend(array $values): string {
        $nonNull = array_values(array_filter($values, fn($v) => $v !== null));
        $n = count($nonNull);
        if ($n < 2) return 'stable';

        $half = (int)floor($n / 2);
        $recent = array_slice($nonNull, $n - $half);
        $older  = array_slice($nonNull, 0, $half);

        $avgRecent = array_sum($recent) / count($recent);
        $avgOlder  = array_sum($older)  / count($older);

        if ((float)$avgOlder === 0.0) return 'stable';
        $diff = ($avgRecent - $avgOlder) / abs($avgOlder);

        if ($diff > 0.02)  return 'up';
        if ($diff < -0.02) return 'down';
        return 'stable';
    }

    private function calcTrendInverted(array $values): string {
        $t = $this->calcTrend($values);
        if ($t === 'up')   return 'down';
        if ($t === 'down') return 'up';
        return 'stable';
    }

    private function calcChangePct(array $values): ?float {
        $nonNull = array_values(array_filter($values, fn($v) => $v !== null));
        $n = count($nonNull);
        if ($n < 2) return null;

        $first = $nonNull[0];
        $last  = $nonNull[$n - 1];

        if ((float)$first === 0.0) return null;
        return round((($last - $first) / abs($first)) * 100, 1);
    }

    private function lastNonNull(array $values): mixed {
        for ($i = count($values) - 1; $i >= 0; $i--) {
            if ($values[$i] !== null) return $values[$i];
        }
        return null;
    }

    private function minNonNull(array $values): mixed {
        $filtered = array_filter($values, fn($v) => $v !== null);
        return count($filtered) > 0 ? min($filtered) : null;
    }

    private function maxNonNull(array $values): mixed {
        $filtered = array_filter($values, fn($v) => $v !== null);
        return count($filtered) > 0 ? max($filtered) : null;
    }
}
