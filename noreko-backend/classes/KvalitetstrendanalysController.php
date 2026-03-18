<?php
/**
 * KvalitetstrendanalysController.php
 * Rebotling kvalitetstrend-analys — kassationsrate per station/operator over tid,
 * identifiera kvalitetsavvikelser tidigt med troskellarm.
 *
 * Endpoints via ?action=kvalitetstrendanalys&run=XXX:
 *   - run=overview          -> 4 KPI:er: total kassationsrate, samsta station, samsta operator, trend vs foregaende
 *   - run=per-station-trend -> kassationsrate per station over tid (daglig), for linjediagram
 *   - run=per-operator      -> sorterad tabell med operatorsnamn, produktion, kasserade, rate, avvikelse, trend
 *   - run=alarm             -> aktiva larm: stationer/operatorer som overskrider troskelvarden
 *   - run=heatmap           -> station+vecka-matris med kassationsrate som fargintensitet
 *
 * Tabeller: rebotling_ibc, rebotling_stationer, operators
 */
class KvalitetstrendanalysController {
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
            case 'overview':          $this->getOverview();         break;
            case 'per-station-trend': $this->getPerStationTrend();  break;
            case 'per-operator':      $this->getPerOperator();      break;
            case 'alarm':             $this->getAlarm();            break;
            case 'heatmap':           $this->getHeatmap();          break;
            default:                  $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
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

    private function getStationer(): array {
        try {
            $stmt = $this->pdo->query("SELECT id, namn FROM rebotling_stationer ORDER BY id");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) return $rows;
        } catch (\Exception) {
            // Tabellen kanske inte finns
        }
        return [
            ['id' => 1, 'namn' => 'Station 1'],
            ['id' => 2, 'namn' => 'Station 2'],
            ['id' => 3, 'namn' => 'Station 3'],
            ['id' => 4, 'namn' => 'Station 4'],
            ['id' => 5, 'namn' => 'Station 5'],
        ];
    }

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

    /**
     * Hamta daglig data per station for en period.
     * Returnerar [station_id => [datum => [total, kasserade]]]
     */
    private function fetchStationDailyData(string $fromDate, string $toDate): array {
        $result = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    COALESCE(station_id, 1) AS station_id,
                    COUNT(*) AS total,
                    SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                GROUP BY DATE(datum), COALESCE(station_id, 1)
                ORDER BY dag ASC, station_id ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $sid = (int)$row['station_id'];
                $dag = $row['dag'];
                if (!isset($result[$sid])) $result[$sid] = [];
                $result[$sid][$dag] = [
                    'total' => (int)$row['total'],
                    'kasserade' => (int)$row['kasserade'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('KvalitetstrendanalysController::fetchStationDailyData: ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Hamta operator-data for en period via op1/op2/op3-kolumnerna.
     * Returnerar [op_num => [total, kasserade]]
     */
    private function fetchOperatorData(string $fromDate, string $toDate): array {
        $result = [];
        try {
            // UNION for op1, op2, op3
            $stmt = $this->pdo->prepare("
                SELECT op_num, SUM(total) AS total, SUM(kasserade) AS kasserade FROM (
                    SELECT op1 AS op_num, COUNT(*) AS total,
                           SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :f1 AND :t1 AND op1 IS NOT NULL
                    GROUP BY op1
                    UNION ALL
                    SELECT op2 AS op_num, COUNT(*) AS total,
                           SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :f2 AND :t2 AND op2 IS NOT NULL
                    GROUP BY op2
                    UNION ALL
                    SELECT op3 AS op_num, COUNT(*) AS total,
                           SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :f3 AND :t3 AND op3 IS NOT NULL
                    GROUP BY op3
                ) AS combined
                GROUP BY op_num
                ORDER BY total DESC
            ");
            $stmt->execute([
                ':f1' => $fromDate, ':t1' => $toDate,
                ':f2' => $fromDate, ':t2' => $toDate,
                ':f3' => $fromDate, ':t3' => $toDate,
            ]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $opNum = (int)$row['op_num'];
                if ($opNum <= 0) continue;
                $result[$opNum] = [
                    'total' => (int)$row['total'],
                    'kasserade' => (int)$row['kasserade'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('KvalitetstrendanalysController::fetchOperatorData: ' . $e->getMessage());
        }
        return $result;
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        // Foregaende period
        $prevTo   = date('Y-m-d', strtotime("-" . ($days + 1) . " days"));
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        $stationer = $this->getStationer();
        $stationMap = [];
        foreach ($stationer as $s) $stationMap[(int)$s['id']] = $s['namn'];

        // Nuvarande period per station
        $stationDaily = $this->fetchStationDailyData($fromDate, $toDate);
        $totalAll = 0;
        $kasseradeAll = 0;
        $stationTotals = [];
        foreach ($stationDaily as $sid => $days_data) {
            $t = 0; $k = 0;
            foreach ($days_data as $d) {
                $t += $d['total'];
                $k += $d['kasserade'];
            }
            $stationTotals[$sid] = ['total' => $t, 'kasserade' => $k];
            $totalAll += $t;
            $kasseradeAll += $k;
        }

        $totalRate = $totalAll > 0 ? round(($kasseradeAll / $totalAll) * 100, 2) : 0;

        // Samsta station
        $worstStation = null;
        $worstStationRate = 0;
        foreach ($stationTotals as $sid => $st) {
            $rate = $st['total'] > 0 ? round(($st['kasserade'] / $st['total']) * 100, 2) : 0;
            if ($rate > $worstStationRate || $worstStation === null) {
                $worstStationRate = $rate;
                $worstStation = $stationMap[$sid] ?? ('Station ' . $sid);
            }
        }

        // Samsta operator
        $opData = $this->fetchOperatorData($fromDate, $toDate);
        $opNames = $this->getOperatorNames();
        $worstOp = null;
        $worstOpRate = 0;
        foreach ($opData as $opNum => $od) {
            $rate = $od['total'] > 0 ? round(($od['kasserade'] / $od['total']) * 100, 2) : 0;
            if ($od['total'] >= 10 && ($rate > $worstOpRate || $worstOp === null)) {
                $worstOpRate = $rate;
                $worstOp = $opNames[$opNum] ?? ('Op. ' . $opNum);
            }
        }

        // Foregaende period
        $prevDaily = $this->fetchStationDailyData($prevFrom, $prevTo);
        $prevTotal = 0;
        $prevKasserade = 0;
        foreach ($prevDaily as $days_data) {
            foreach ($days_data as $d) {
                $prevTotal += $d['total'];
                $prevKasserade += $d['kasserade'];
            }
        }
        $prevRate = $prevTotal > 0 ? round(($prevKasserade / $prevTotal) * 100, 2) : 0;

        $trendDiff = round($totalRate - $prevRate, 2);
        if ($trendDiff < -0.01) {
            $trendDirection = 'down'; // battre
        } elseif ($trendDiff > 0.01) {
            $trendDirection = 'up'; // samre
        } else {
            $trendDirection = 'flat';
        }

        $this->sendSuccess([
            'days'               => $days,
            'from_date'          => $fromDate,
            'to_date'            => $toDate,
            'total_rate'         => $totalRate,
            'total_producerade'  => $totalAll,
            'total_kasserade'    => $kasseradeAll,
            'worst_station'      => $worstStation,
            'worst_station_rate' => $worstStationRate,
            'worst_operator'     => $worstOp,
            'worst_operator_rate'=> $worstOpRate,
            'prev_rate'          => $prevRate,
            'trend_diff'         => $trendDiff,
            'trend_direction'    => $trendDirection,
        ]);
    }

    // ================================================================
    // run=per-station-trend — daglig kassationsrate per station
    // ================================================================

    private function getPerStationTrend(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        $stationer = $this->getStationer();
        $stationMap = [];
        foreach ($stationer as $s) $stationMap[(int)$s['id']] = $s['namn'];

        $stationDaily = $this->fetchStationDailyData($fromDate, $toDate);

        // Bygg datumsekvens
        $dates = [];
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        while ($d <= $end) {
            $dates[] = $d->format('Y-m-d');
            $d->modify('+1 day');
        }

        // Bygg serier per station
        $series = [];
        foreach ($stationMap as $sid => $namn) {
            $values = [];
            foreach ($dates as $date) {
                $dayData = $stationDaily[$sid][$date] ?? null;
                if ($dayData && $dayData['total'] > 0) {
                    $values[] = round(($dayData['kasserade'] / $dayData['total']) * 100, 2);
                } else {
                    $values[] = null;
                }
            }
            $series[] = [
                'station_id' => $sid,
                'station'    => $namn,
                'values'     => $values,
            ];
        }

        $this->sendSuccess([
            'days'      => $days,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'dates'     => $dates,
            'series'    => $series,
        ]);
    }

    // ================================================================
    // run=per-operator — tabell med operatorsdata
    // ================================================================

    private function getPerOperator(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        // Foregaende period for trend
        $prevTo   = date('Y-m-d', strtotime("-" . ($days + 1) . " days"));
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        $opData = $this->fetchOperatorData($fromDate, $toDate);
        $prevOpData = $this->fetchOperatorData($prevFrom, $prevTo);
        $opNames = $this->getOperatorNames();

        // Berakna totalt snitt
        $totalAll = 0;
        $kasseradeAll = 0;
        foreach ($opData as $od) {
            $totalAll += $od['total'];
            $kasseradeAll += $od['kasserade'];
        }
        $avgRate = $totalAll > 0 ? round(($kasseradeAll / $totalAll) * 100, 2) : 0;

        $operators = [];
        foreach ($opData as $opNum => $od) {
            $rate = $od['total'] > 0 ? round(($od['kasserade'] / $od['total']) * 100, 2) : 0;
            $avvikelse = round($rate - $avgRate, 2);

            // Trend vs foregaende period
            $prevOd = $prevOpData[$opNum] ?? null;
            $prevRate = ($prevOd && $prevOd['total'] > 0) ? round(($prevOd['kasserade'] / $prevOd['total']) * 100, 2) : null;
            $trendDiff = $prevRate !== null ? round($rate - $prevRate, 2) : null;
            if ($trendDiff !== null) {
                if ($trendDiff < -0.01) $trendDir = 'down';
                elseif ($trendDiff > 0.01) $trendDir = 'up';
                else $trendDir = 'flat';
            } else {
                $trendDir = 'flat';
            }

            $operators[] = [
                'op_num'      => $opNum,
                'op_namn'     => $opNames[$opNum] ?? ('Op. ' . $opNum),
                'total'       => $od['total'],
                'kasserade'   => $od['kasserade'],
                'rate'        => $rate,
                'avvikelse'   => $avvikelse,
                'trend_diff'  => $trendDiff,
                'trend_dir'   => $trendDir,
            ];
        }

        // Sortera efter rate fallande
        usort($operators, fn($a, $b) => $b['rate'] <=> $a['rate']);

        $this->sendSuccess([
            'days'       => $days,
            'from_date'  => $fromDate,
            'to_date'    => $toDate,
            'avg_rate'   => $avgRate,
            'operators'  => $operators,
        ]);
    }

    // ================================================================
    // run=alarm — aktiva larm baserat pa troskelvarden
    // ================================================================

    private function getAlarm(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        $warningThreshold  = floatval($_GET['warning'] ?? 3);
        $criticalThreshold = floatval($_GET['critical'] ?? 5);

        $stationer = $this->getStationer();
        $stationMap = [];
        foreach ($stationer as $s) $stationMap[(int)$s['id']] = $s['namn'];

        $stationDaily = $this->fetchStationDailyData($fromDate, $toDate);
        $opData = $this->fetchOperatorData($fromDate, $toDate);
        $opNames = $this->getOperatorNames();

        $alarms = [];

        // Per station
        foreach ($stationDaily as $sid => $days_data) {
            $t = 0; $k = 0;
            foreach ($days_data as $d) {
                $t += $d['total'];
                $k += $d['kasserade'];
            }
            $rate = $t > 0 ? round(($k / $t) * 100, 2) : 0;
            if ($rate >= $warningThreshold && $t >= 10) {
                $alarms[] = [
                    'typ'      => 'station',
                    'namn'     => $stationMap[$sid] ?? ('Station ' . $sid),
                    'rate'     => $rate,
                    'total'    => $t,
                    'kasserade'=> $k,
                    'niva'     => $rate >= $criticalThreshold ? 'kritisk' : 'varning',
                ];
            }
        }

        // Per operator
        foreach ($opData as $opNum => $od) {
            $rate = $od['total'] > 0 ? round(($od['kasserade'] / $od['total']) * 100, 2) : 0;
            if ($rate >= $warningThreshold && $od['total'] >= 10) {
                $alarms[] = [
                    'typ'      => 'operator',
                    'namn'     => $opNames[$opNum] ?? ('Op. ' . $opNum),
                    'rate'     => $rate,
                    'total'    => $od['total'],
                    'kasserade'=> $od['kasserade'],
                    'niva'     => $rate >= $criticalThreshold ? 'kritisk' : 'varning',
                ];
            }
        }

        // Sortera efter rate fallande
        usort($alarms, fn($a, $b) => $b['rate'] <=> $a['rate']);

        $this->sendSuccess([
            'days'               => $days,
            'from_date'          => $fromDate,
            'to_date'            => $toDate,
            'warning_threshold'  => $warningThreshold,
            'critical_threshold' => $criticalThreshold,
            'alarms'             => $alarms,
            'total_alarms'       => count($alarms),
            'critical_count'     => count(array_filter($alarms, fn($a) => $a['niva'] === 'kritisk')),
            'warning_count'      => count(array_filter($alarms, fn($a) => $a['niva'] === 'varning')),
        ]);
    }

    // ================================================================
    // run=heatmap — station+vecka kassationsrate-matris
    // ================================================================

    private function getHeatmap(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        $stationer = $this->getStationer();
        $stationMap = [];
        foreach ($stationer as $s) $stationMap[(int)$s['id']] = $s['namn'];

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(station_id, 1) AS station_id,
                    YEARWEEK(datum, 1) AS yearweek,
                    MIN(DATE(datum)) AS week_start,
                    COUNT(*) AS total,
                    SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                GROUP BY COALESCE(station_id, 1), YEARWEEK(datum, 1)
                ORDER BY yearweek ASC, station_id ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KvalitetstrendanalysController::getHeatmap: ' . $e->getMessage());
            $rows = [];
        }

        // Bygg unika veckor
        $weekSet = [];
        $dataMap = [];
        foreach ($rows as $row) {
            $sid = (int)$row['station_id'];
            $yw  = $row['yearweek'];
            $ws  = $row['week_start'];
            $weekSet[$yw] = $ws;

            $total = (int)$row['total'];
            $kass  = (int)$row['kasserade'];
            $rate  = $total > 0 ? round(($kass / $total) * 100, 2) : 0;

            $dataMap[$sid][$yw] = [
                'rate'      => $rate,
                'total'     => $total,
                'kasserade' => $kass,
            ];
        }

        ksort($weekSet);
        $weeks = [];
        foreach ($weekSet as $yw => $ws) {
            $weekNum = (int)substr($yw, -2);
            $weeks[] = [
                'yearweek'   => $yw,
                'week_start' => $ws,
                'label'      => 'V' . $weekNum,
            ];
        }

        // Bygg stationsrader
        $heatmapRows = [];
        foreach ($stationMap as $sid => $namn) {
            $cells = [];
            foreach ($weeks as $w) {
                $cell = $dataMap[$sid][$w['yearweek']] ?? null;
                $cells[] = $cell ? $cell['rate'] : null;
            }
            $heatmapRows[] = [
                'station_id' => $sid,
                'station'    => $namn,
                'cells'      => $cells,
            ];
        }

        $this->sendSuccess([
            'days'      => $days,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'weeks'     => $weeks,
            'rows'      => $heatmapRows,
        ]);
    }
}
