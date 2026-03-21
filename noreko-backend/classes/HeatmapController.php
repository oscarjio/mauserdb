<?php
/**
 * HeatmapController.php
 * Produktions-heatmap per timme och dag for rebotling-linjen.
 *
 * Endpoints via ?action=heatmap&run=XXX:
 *   - run=heatmap-data&days=N  → matris [{date, hour, count}] + min/max/avg for fargskala
 *   - run=summary&days=N       → totalt IBC, basta timme (snitt), samsta timme, basta veckodag
 *
 * Kalla: rebotling_ibc
 * Auth: session kravs (401 om ej inloggad)
 */
class HeatmapController {
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
            case 'heatmap-data': $this->getHeatmapData(); break;
            case 'summary':      $this->getSummary();     break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
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

    /**
     * Kontrollera att tabellen rebotling_ibc finns.
     */
    private function tableExists(): bool {
        $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_ibc'");
        return ($check && $check->rowCount() > 0);
    }

    // ================================================================
    // run=heatmap-data
    // Returnerar [{date, hour, count}] + skalvarden for fargkodning
    // ================================================================

    /**
     * GET ?action=heatmap&run=heatmap-data&days=N
     *
     * Aggregerar antal IBC per timme per dag.
     * Anvander MAX() per skiftraknare (kumulativa PLC-falt) och summerar sedan per timme.
     * Returnerar:
     *   matrix    — [{date, hour, count}] sorterat pa date+hour
     *   scale     — {min, max, avg} for fargkodning i frontend
     *   days      — anvand period
     *   from_date — startdatum
     *   to_date   — slutdatum
     */
    private function getHeatmapData(): void {
        try {
            $days     = $this->getDays();
            $toDate   = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            if (!$this->tableExists()) {
                $this->sendSuccess([
                    'matrix'    => [],
                    'scale'     => ['min' => 0, 'max' => 0, 'avg' => 0],
                    'days'      => $days,
                    'from_date' => $fromDate,
                    'to_date'   => $toDate,
                ]);
                return;
            }

            // Aggregera IBC per dag+timme.
            // rebotling_ibc lagrar kumulativa rakneverk per skiftraknare,
            // sa vi tar MAX(ibc_ok) per skiftraknare+timme och summerar.
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)  AS date,
                    HOUR(datum)  AS hour,
                    SUM(shift_ibc) AS ibc_count
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), HOUR(datum), skiftraknare
                ) AS per_hour_shift
                GROUP BY DATE(datum), HOUR(datum)
                HAVING ibc_count > 0
                ORDER BY date ASC, hour ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $matrix = [];
            foreach ($rows as $r) {
                $matrix[] = [
                    'date'  => $r['date'],
                    'hour'  => (int)$r['hour'],
                    'count' => (int)$r['ibc_count'],
                ];
            }

            // Berakna min/max/avg for fargskala
            $counts = array_column($matrix, 'count');
            if (!empty($counts)) {
                $min = min($counts);
                $max = max($counts);
                $avg = round(array_sum($counts) / count($counts), 1);
            } else {
                $min = 0;
                $max = 0;
                $avg = 0;
            }

            $this->sendSuccess([
                'matrix'    => $matrix,
                'scale'     => ['min' => $min, 'max' => $max, 'avg' => $avg],
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ]);
        } catch (\PDOException $e) {
            error_log('HeatmapController::getHeatmapData: ' . $e->getMessage());
            $this->sendError('Databasfel vid hamtning av heatmap-data', 500);
        }
    }

    // ================================================================
    // run=summary
    // Totalt IBC, basta timme (snitt), samsta timme, basta veckodag
    // ================================================================

    /**
     * GET ?action=heatmap&run=summary&days=N
     *
     * Returnerar:
     *   total_ibc      — totalt antal IBC under perioden
     *   best_hour      — timme med hogst genomsnittlig produktion (0-23)
     *   best_hour_avg  — snitt IBC for basta timmen
     *   worst_hour     — timme med lagst genomsnittlig produktion (0-23, bland timmar med produktion)
     *   worst_hour_avg — snitt IBC for samsta timmen
     *   best_weekday   — veckodag (1=man..7=son) med hogst genomsnittlig produktion
     *   best_weekday_name — namn pa basta veckodagen
     *   best_weekday_avg  — snitt IBC for basta veckodagen
     *   days, from_date, to_date
     */
    private function getSummary(): void {
        try {
            $days     = $this->getDays();
            $toDate   = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            if (!$this->tableExists()) {
                $this->sendSuccess($this->emptySummary($days, $fromDate, $toDate));
                return;
            }

            // Totalt IBC under perioden
            $stmtTotal = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ibc), 0) AS total_ibc
                FROM (
                    SELECT skiftraknare, MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmtTotal->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $totalRow  = $stmtTotal->fetch(\PDO::FETCH_ASSOC);
            $totalIbc  = (int)($totalRow['total_ibc'] ?? 0);

            // Snitt IBC per timme (genomsnitt over alla dagar)
            $stmtHour = $this->pdo->prepare("
                SELECT
                    hour,
                    AVG(daily_count) AS avg_count
                FROM (
                    SELECT
                        DATE(datum)  AS day,
                        HOUR(datum)  AS hour,
                        SUM(shift_ibc) AS daily_count
                    FROM (
                        SELECT datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), HOUR(datum), skiftraknare
                    ) AS hs
                    GROUP BY DATE(datum), HOUR(datum)
                    HAVING daily_count > 0
                ) AS hd
                GROUP BY hour
                ORDER BY avg_count DESC
            ");
            $stmtHour->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $hourRows = $stmtHour->fetchAll(\PDO::FETCH_ASSOC);

            $bestHour     = null;
            $bestHourAvg  = 0;
            $worstHour    = null;
            $worstHourAvg = 0;

            if (!empty($hourRows)) {
                $first = $hourRows[0];
                $last  = end($hourRows);
                $bestHour    = (int)$first['hour'];
                $bestHourAvg = round((float)$first['avg_count'], 1);
                $worstHour   = (int)$last['hour'];
                $worstHourAvg = round((float)$last['avg_count'], 1);
            }

            // Snitt IBC per veckodag (1=man..7=son, MySQL: DAYOFWEEK 1=son, 2=man..7=lor)
            // Vi anvander WEEKDAY() som ger 0=man..6=son, plus 1 for ISO-format
            $stmtWday = $this->pdo->prepare("
                SELECT
                    (WEEKDAY(day) + 1) AS weekday,
                    AVG(day_count) AS avg_count
                FROM (
                    SELECT
                        DATE(datum) AS day,
                        SUM(shift_ibc) AS day_count
                    FROM (
                        SELECT datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ds
                    GROUP BY DATE(datum)
                    HAVING day_count > 0
                ) AS dd
                GROUP BY weekday
                ORDER BY avg_count DESC
                LIMIT 1
            ");
            $stmtWday->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $wdayRow = $stmtWday->fetch(\PDO::FETCH_ASSOC);

            $bestWeekday     = null;
            $bestWeekdayName = null;
            $bestWeekdayAvg  = 0;

            if ($wdayRow) {
                $bestWeekday    = (int)$wdayRow['weekday'];
                $bestWeekdayAvg = round((float)$wdayRow['avg_count'], 1);
                $wdayNames = [1 => 'Mandag', 2 => 'Tisdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lordag', 7 => 'Sondag'];
                $bestWeekdayName = $wdayNames[$bestWeekday] ?? "Dag {$bestWeekday}";
            }

            $this->sendSuccess([
                'total_ibc'        => $totalIbc,
                'best_hour'        => $bestHour,
                'best_hour_avg'    => $bestHourAvg,
                'worst_hour'       => $worstHour,
                'worst_hour_avg'   => $worstHourAvg,
                'best_weekday'     => $bestWeekday,
                'best_weekday_name' => $bestWeekdayName,
                'best_weekday_avg' => $bestWeekdayAvg,
                'days'             => $days,
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
            ]);
        } catch (\PDOException $e) {
            error_log('HeatmapController::getSummary: ' . $e->getMessage());
            $this->sendError('Databasfel vid hamtning av summering');
        }
    }

    private function emptySummary(int $days, string $fromDate, string $toDate): array {
        return [
            'total_ibc'         => 0,
            'best_hour'         => null,
            'best_hour_avg'     => 0,
            'worst_hour'        => null,
            'worst_hour_avg'    => 0,
            'best_weekday'      => null,
            'best_weekday_name' => null,
            'best_weekday_avg'  => 0,
            'days'              => $days,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
        ];
    }
}
