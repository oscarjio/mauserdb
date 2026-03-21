<?php
/**
 * VeckorapportController.php
 * Veckorapport-generator -- sammanstaller veckans KPI:er for VD-rapport.
 *
 * Endpoint via ?action=veckorapport&run=report&week=YYYY-WNN
 *   Om week saknas -> senaste avslutade veckan.
 *
 * Returnerar ALL data i ett enda API-anrop:
 *   - week_info
 *   - production
 *   - efficiency
 *   - stops
 *   - quality
 *
 * Tabeller: rebotling_ibc, rebotling_weekday_goals,
 *           stoppage_log, stoppage_reasons,
 *           stopporsak_registreringar, stopporsak_kategorier,
 *           kassationsregistrering, kassationsorsak_typer
 *
 * Auth: session kravs.
 */
class VeckorapportController {
    private $pdo;

    /** Tillganglig tid per dag i timmar (3 skift x 7.5h efter rast) */
    private const TILLGANGLIG_TID_PER_DAG = 22.5;

    /** Standard dagligt mal om rebotling_weekday_goals saknas */
    private const DEFAULT_DAILY_GOAL = 120;

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
            case 'report': $this->getReport(); break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
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
     * Parsa YYYY-WNN till start_date (mandag) och end_date (sondag).
     * Returnerar [year, week, start_date, end_date] eller null vid ogiltigt format.
     */
    private function parseWeek(string $weekStr): ?array {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $weekStr, $m)) {
            return null;
        }
        $year = (int)$m[1];
        $week = (int)$m[2];
        if ($week < 1 || $week > 53) return null;

        // Mandag i den angivna veckan
        $dt = new \DateTime();
        $dt->setISODate($year, $week, 1); // 1 = mandag
        $startDate = $dt->format('Y-m-d');
        $dt->setISODate($year, $week, 7); // 7 = sondag
        $endDate = $dt->format('Y-m-d');

        return [
            'year'       => $year,
            'week_number' => $week,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];
    }

    /**
     * Hamta senaste avslutade veckans YYYY-WNN.
     */
    private function getLastCompletedWeek(): string {
        $now = new \DateTime();
        $dayOfWeek = (int)$now->format('N'); // 1=man, 7=son
        // Om vi ar pa mandag-sondag, ga till foregaende vecka
        // Vi vill alltid ha en avslutad vecka, sa vi tar foregaende vecka
        $now->modify('-' . $dayOfWeek . ' days'); // Gar till foregaende sondag
        return $now->format('o') . '-W' . str_pad($now->format('W'), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Hamta dagliga mal fran rebotling_weekday_goals.
     * Returnerar array med weekday (1-7) => daily_goal.
     */
    private function getWeekdayGoals(): array {
        $goals = [];
        try {
            $rows = $this->pdo->query(
                "SELECT weekday, daily_goal FROM rebotling_weekday_goals ORDER BY weekday"
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $goals[(int)$row['weekday']] = (int)$row['daily_goal'];
            }
        } catch (\Exception $e) {
            // Tabellen kanske inte finns
            error_log('VeckorapportController::getWeekdayGoals: ' . $e->getMessage());
        }
        return $goals;
    }

    /**
     * Hamta veckomal baserat pa veckodags-mal.
     */
    private function getWeekGoal(string $startDate, string $endDate): int {
        $goals = $this->getWeekdayGoals();
        $total = 0;
        $dt = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        while ($dt <= $end) {
            $dayNum = (int)$dt->format('N'); // 1=man, 7=son
            $total += $goals[$dayNum] ?? self::DEFAULT_DAILY_GOAL;
            $dt->modify('+1 day');
        }
        return $total;
    }

    // ================================================================
    // run=report
    // ================================================================

    private function getReport(): void {
        try {
            // Parsa vecka
            $weekParam = trim($_GET['week'] ?? '');
            if (empty($weekParam)) {
                $weekParam = $this->getLastCompletedWeek();
            }

            $weekInfo = $this->parseWeek($weekParam);
            if (!$weekInfo) {
                $this->sendError('Ogiltigt veckoformat. Anvand YYYY-WNN (t.ex. 2026-W10).');
                return;
            }

            $startDate = $weekInfo['start_date'];
            $endDate   = $weekInfo['end_date'];

            // Foregaende vecka for jamforelse
            $prevDt = new \DateTime($startDate);
            $prevDt->modify('-7 days');
            $prevStartDate = $prevDt->format('Y-m-d');
            $prevDt->modify('+6 days');
            $prevEndDate = $prevDt->format('Y-m-d');

            // Samla all data
            $production = $this->getProductionData($startDate, $endDate, $prevStartDate, $prevEndDate);
            $efficiency = $this->getEfficiencyData($startDate, $endDate, $prevStartDate, $prevEndDate);
            $stops      = $this->getStopsData($startDate, $endDate, $prevStartDate, $prevEndDate);
            $quality    = $this->getQualityData($startDate, $endDate, $prevStartDate, $prevEndDate);

            $this->sendSuccess([
                'week_info'  => $weekInfo,
                'production' => $production,
                'efficiency' => $efficiency,
                'stops'      => $stops,
                'quality'    => $quality,
            ]);

        } catch (\Exception $e) {
            error_log('VeckorapportController::getReport: ' . $e->getMessage());
            $this->sendError('Kunde inte generera veckorapport', 500);
        }
    }

    // ================================================================
    // PRODUKTION
    // ================================================================

    private function getProductionData(string $start, string $end, string $prevStart, string $prevEnd): array {
        // Dagvis produktion for aktuell vecka
        $stmt = $this->pdo->prepare(
            "SELECT DATE(datum) AS dag, COUNT(*) AS cnt
             FROM rebotling_ibc
             WHERE DATE(datum) BETWEEN ? AND ?
             GROUP BY DATE(datum)
             ORDER BY dag ASC"
        );
        $stmt->execute([$start, $end]);
        $dailyRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dailyMap[$row['dag']] = (int)$row['cnt'];
        }

        // Totalt producerade
        $totalIbc = array_sum($dailyMap);

        // Mal
        $goal = $this->getWeekGoal($start, $end);
        $fulfillmentPct = $goal > 0 ? round(($totalIbc / $goal) * 100, 1) : 0;

        // Basta/samsta dag
        $bestDay = null;
        $worstDay = null;
        if (!empty($dailyMap)) {
            $maxCount = max($dailyMap);
            $minCount = min($dailyMap);
            foreach ($dailyMap as $dag => $cnt) {
                if ($cnt === $maxCount && $bestDay === null) {
                    $bestDay = ['date' => $dag, 'count' => $cnt];
                }
                if ($cnt === $minCount && $worstDay === null) {
                    $worstDay = ['date' => $dag, 'count' => $cnt];
                }
            }
        }

        // Snitt IBC/dag
        $numDays = count($dailyMap) ?: 1;
        $avgPerDay = round($totalIbc / $numDays, 1);

        // Foregaende vecka for jamforelse
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE DATE(datum) BETWEEN ? AND ?"
        );
        $stmt->execute([$prevStart, $prevEnd]);
        $prevWeekTotal = (int)($stmt->fetchColumn() ?: 0);

        $changePct = $prevWeekTotal > 0
            ? round((($totalIbc - $prevWeekTotal) / $prevWeekTotal) * 100, 1)
            : 0;

        return [
            'total_ibc'       => $totalIbc,
            'goal'            => $goal,
            'fulfillment_pct' => $fulfillmentPct,
            'best_day'        => $bestDay,
            'worst_day'       => $worstDay,
            'avg_per_day'     => $avgPerDay,
            'prev_week_total' => $prevWeekTotal,
            'change_pct'      => $changePct,
            'daily'           => $dailyMap,
        ];
    }

    // ================================================================
    // EFFEKTIVITET
    // ================================================================

    private function getEfficiencyData(string $start, string $end, string $prevStart, string $prevEnd): array {
        // Drifttid aktuell vecka (fran rebotling_ibc runtime_plc)
        $runtimeCurrent = $this->getTotalRuntimeHours($start, $end);
        $runtimePrev    = $this->getTotalRuntimeHours($prevStart, $prevEnd);

        // Antal producerade IBC
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE DATE(datum) BETWEEN ? AND ?"
        );
        $stmt->execute([$start, $end]);
        $totalIbc = (int)($stmt->fetchColumn() ?: 0);

        $stmt->execute([$prevStart, $prevEnd]);
        $prevTotalIbc = (int)($stmt->fetchColumn() ?: 0);

        // Antal produktionsdagar (dagar med IBC)
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT DATE(datum)) AS cnt FROM rebotling_ibc WHERE DATE(datum) BETWEEN ? AND ?"
        );
        $stmt->execute([$start, $end]);
        $prodDays = max(1, (int)($stmt->fetchColumn() ?: 1));

        // Tillganglig tid
        $availableHours = round($prodDays * self::TILLGANGLIG_TID_PER_DAG, 1);

        // IBC per drifttimme
        $avgIbcPerHour = $runtimeCurrent > 0 ? round($totalIbc / $runtimeCurrent, 1) : 0;
        $prevIbcPerHour = $runtimePrev > 0 ? round($prevTotalIbc / $runtimePrev, 1) : 0;

        // Utnyttjandegrad
        $utilizationPct = $availableHours > 0 ? round(($runtimeCurrent / $availableHours) * 100, 1) : 0;

        $changePct = $prevIbcPerHour > 0
            ? round((($avgIbcPerHour - $prevIbcPerHour) / $prevIbcPerHour) * 100, 1)
            : 0;

        return [
            'avg_ibc_per_hour'      => $avgIbcPerHour,
            'total_runtime_hours'   => round($runtimeCurrent, 1),
            'available_hours'       => $availableHours,
            'utilization_pct'       => $utilizationPct,
            'prev_week_ibc_per_hour' => $prevIbcPerHour,
            'change_pct'            => $changePct,
        ];
    }

    /**
     * Hamta total drifttid i timmar fran rebotling_ibc (runtime_plc).
     */
    private function getTotalRuntimeHours(string $fromDate, string $toDate): float {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT SUM(max_runtime) AS runtime_min
                 FROM (
                     SELECT DATE(datum) AS dag, skiftraknare,
                            MAX(runtime_plc) AS max_runtime
                     FROM rebotling_ibc
                     WHERE DATE(datum) BETWEEN ? AND ?
                     GROUP BY DATE(datum), skiftraknare
                     HAVING COUNT(*) > 1
                 ) sub"
            );
            $stmt->execute([$fromDate, $toDate]);
            $val = $stmt->fetchColumn();
            return round((float)($val ?: 0) / 60.0, 2);
        } catch (\Exception $e) {
            error_log('VeckorapportController::getTotalRuntimeHours: ' . $e->getMessage());
            return 0;
        }
    }

    // ================================================================
    // STOPP
    // ================================================================

    private function getStopsData(string $start, string $end, string $prevStart, string $prevEnd): array {
        // Forsok hamta fran stoppage_log forst
        $totalCount = 0;
        $totalHours = 0;
        $topReasons = [];
        $prevCount = 0;

        // Kalla 1: stoppage_log
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(duration_minutes), 0) AS total_min
                 FROM stoppage_log
                 WHERE DATE(start_time) BETWEEN ? AND ?
                   AND line = 'rebotling'"
            );
            $stmt->execute([$start, $end]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalCount += (int)($row['cnt'] ?? 0);
            $totalHours += (float)($row['total_min'] ?? 0) / 60.0;

            // Foregaende vecka
            $stmt->execute([$prevStart, $prevEnd]);
            $prevRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $prevCount += (int)($prevRow['cnt'] ?? 0);

            // Topp orsaker fran stoppage_log
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(r.name, 'Okänd orsak') AS reason, COUNT(*) AS cnt,
                        COALESCE(SUM(s.duration_minutes), 0) AS total_min
                 FROM stoppage_log s
                 LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                 WHERE DATE(s.start_time) BETWEEN ? AND ?
                   AND s.line = 'rebotling'
                 GROUP BY r.id, r.name
                 ORDER BY total_min DESC
                 LIMIT 10"
            );
            $stmt->execute([$start, $end]);
            $topReasons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('VeckorapportController::getStopsData (stoppage_log): ' . $e->getMessage());
        }

        // Kalla 2: stopporsak_registreringar
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) AS cnt,
                            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))), 0) AS total_min
                     FROM stopporsak_registreringar
                     WHERE DATE(start_time) BETWEEN ? AND ?
                       AND linje = 'rebotling'"
                );
                $stmt->execute([$start, $end]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalCount += (int)($row['cnt'] ?? 0);
                $totalHours += (float)($row['total_min'] ?? 0) / 60.0;

                // Foregaende vecka
                $stmt->execute([$prevStart, $prevEnd]);
                $prevRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                $prevCount += (int)($prevRow['cnt'] ?? 0);

                // Topp orsaker
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(k.namn, 'Okänd kategori') AS reason, COUNT(*) AS cnt,
                            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.start_time, COALESCE(r.end_time, NOW()))), 0) AS total_min
                     FROM stopporsak_registreringar r
                     LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                     WHERE DATE(r.start_time) BETWEEN ? AND ?
                       AND r.linje = 'rebotling'
                     GROUP BY k.id, k.namn
                     ORDER BY total_min DESC
                     LIMIT 10"
                );
                $stmt->execute([$start, $end]);
                $extraReasons = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Slå ihop och sortera
                foreach ($extraReasons as $er) {
                    $found = false;
                    foreach ($topReasons as &$tr) {
                        if (mb_strtolower($tr['reason']) === mb_strtolower($er['reason'])) {
                            $tr['cnt'] = (int)$tr['cnt'] + (int)$er['cnt'];
                            $tr['total_min'] = (float)$tr['total_min'] + (float)$er['total_min'];
                            $found = true;
                            break;
                        }
                    }
                    unset($tr);
                    if (!$found) {
                        $topReasons[] = $er;
                    }
                }

                // Sortera pa total_min DESC
                usort($topReasons, fn($a, $b) => (float)$b['total_min'] <=> (float)$a['total_min']);
            }
        } catch (\Exception $e) {
            error_log('VeckorapportController::getStopsData (stopporsak_registreringar): ' . $e->getMessage());
        }

        // Formatera topp 3
        $top3 = [];
        $topSlice = array_slice($topReasons, 0, 3);
        foreach ($topSlice as $r) {
            $top3[] = [
                'reason' => $r['reason'],
                'count'  => (int)$r['cnt'],
                'hours'  => round((float)$r['total_min'] / 60.0, 1),
            ];
        }

        $totalHours = round($totalHours, 1);
        $changePct = $prevCount > 0
            ? round((($totalCount - $prevCount) / $prevCount) * 100, 1)
            : 0;

        return [
            'total_count'     => $totalCount,
            'total_hours'     => $totalHours,
            'top_reasons'     => $top3,
            'prev_week_count' => $prevCount,
            'change_pct'      => $changePct,
        ];
    }

    // ================================================================
    // KVALITET
    // ================================================================

    private function getQualityData(string $start, string $end, string $prevStart, string $prevEnd): array {
        $scrappedCount = 0;
        $totalProduced = 0;
        $topScrapReason = '-';
        $prevScrappedCount = 0;
        $prevTotalProduced = 0;

        // rebotling_ibc: ibc_ej_ok for kassationer
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(ibc_ej_ok), 0) AS kasserade,
                        COUNT(*) AS total
                 FROM rebotling_ibc
                 WHERE DATE(datum) BETWEEN ? AND ?"
            );
            $stmt->execute([$start, $end]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $scrappedCount = (int)($row['kasserade'] ?? 0);
            $totalProduced = (int)($row['total'] ?? 0);

            // Foregaende vecka
            $stmt->execute([$prevStart, $prevEnd]);
            $prevRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $prevScrappedCount = (int)($prevRow['kasserade'] ?? 0);
            $prevTotalProduced = (int)($prevRow['total'] ?? 0);
        } catch (\Exception $e) {
            error_log('VeckorapportController::getQualityData (ibc): ' . $e->getMessage());
        }

        // kassationsregistrering for extra kassationer och topporsak
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'kassationsregistrering'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(SUM(antal), 0) AS kasserade
                     FROM kassationsregistrering
                     WHERE DATE(datum) BETWEEN ? AND ?"
                );
                $stmt->execute([$start, $end]);
                $extraKasserade = (int)($stmt->fetchColumn() ?: 0);
                $scrappedCount += $extraKasserade;

                $stmt->execute([$prevStart, $prevEnd]);
                $prevExtraKasserade = (int)($stmt->fetchColumn() ?: 0);
                $prevScrappedCount += $prevExtraKasserade;

                // Topp-orsak
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(t.namn, 'Okänd') AS namn, SUM(r.antal) AS total_antal
                     FROM kassationsregistrering r
                     LEFT JOIN kassationsorsak_typer t ON r.orsak_id = t.id
                     WHERE DATE(r.datum) BETWEEN ? AND ?
                     GROUP BY t.id, t.namn
                     ORDER BY total_antal DESC
                     LIMIT 1"
                );
                $stmt->execute([$start, $end]);
                $topRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($topRow && !empty($topRow['namn'])) {
                    $topScrapReason = $topRow['namn'];
                }
            }
        } catch (\Exception $e) {
            error_log('VeckorapportController::getQualityData (kassationsregistrering): ' . $e->getMessage());
        }

        // Berakna grader
        $scrapRatePct = $totalProduced > 0
            ? round(($scrappedCount / $totalProduced) * 100, 2)
            : 0;

        $prevScrapRate = $prevTotalProduced > 0
            ? round(($prevScrappedCount / $prevTotalProduced) * 100, 2)
            : 0;

        $changePct = $prevScrapRate > 0
            ? round((($scrapRatePct - $prevScrapRate) / $prevScrapRate) * 100, 1)
            : 0;

        return [
            'scrap_rate_pct'       => $scrapRatePct,
            'scrapped_count'       => $scrappedCount,
            'total_produced'       => $totalProduced,
            'top_scrap_reason'     => $topScrapReason,
            'prev_week_scrap_rate' => $prevScrapRate,
            'change_pct'           => $changePct,
        ];
    }
}
