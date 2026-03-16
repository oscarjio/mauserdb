<?php
/**
 * ParetoController.php
 * Stopporsak Pareto-analys — 80/20-analys av stopporsaker
 *
 * Endpoints via ?action=pareto&run=XXX:
 *   - run=pareto-data&days=N  → Pareto-data: orsaker sorterade fallande, kumulativ %, 80%-markering
 *   - run=summary&days=N      → KPI-sammanfattning: total stopptid, antal orsaker, #1 orsak, antal inom 80%
 *
 * Datakallor:
 *   - stoppage_log + stoppage_reasons        (PLC-baserade stopp)
 *   - stopporsak_registreringar + stopporsak_kategorier  (manuellt registrerade stopp)
 *
 * Auth: session kravs (401 om ej inloggad).
 */
class ParetoController {
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
            case 'pareto-data': $this->getParetoData(); break;
            case 'summary':     $this->getSummary();    break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
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
     * Samla stopptid per orsak fran bada kallorna for angiven period.
     * Returnerar array: [ 'orsak' => minutes, ... ] summaerad over alla kallor.
     */
    private function collectStopData(string $fromDate, string $toDate): array {
        $data = []; // 'orsak' => ['minutes' => int, 'count' => int]

        // ---- Kalla 1: stoppage_log + stoppage_reasons ----
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stoppage_log'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(sr.name, 'Okand orsak') AS orsak,
                        SUM(COALESCE(sl.duration_minutes, 0)) AS total_minutes,
                        COUNT(*) AS antal
                    FROM stoppage_log sl
                    LEFT JOIN stoppage_reasons sr ON sr.id = sl.reason_id
                    WHERE DATE(sl.start_time) BETWEEN :from_date AND :to_date
                      AND sl.duration_minutes IS NOT NULL
                      AND sl.duration_minutes > 0
                    GROUP BY COALESCE(sr.name, 'Okand orsak')
                ");
                $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $orsak = $row['orsak'];
                    if (!isset($data[$orsak])) {
                        $data[$orsak] = ['minutes' => 0, 'count' => 0];
                    }
                    $data[$orsak]['minutes'] += (int)$row['total_minutes'];
                    $data[$orsak]['count']   += (int)$row['antal'];
                }
            }
        } catch (\PDOException $e) {
            error_log('ParetoController::collectStopData (stoppage_log): ' . $e->getMessage());
        }

        // ---- Kalla 2: stopporsak_registreringar + stopporsak_kategorier ----
        try {
            $checkReg = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($checkReg && $checkReg->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(sk.namn, 'Okand orsak') AS orsak,
                        SUM(
                            CASE
                                WHEN sr.end_time IS NOT NULL
                                THEN TIMESTAMPDIFF(MINUTE, sr.start_time, sr.end_time)
                                ELSE 0
                            END
                        ) AS total_minutes,
                        COUNT(*) AS antal
                    FROM stopporsak_registreringar sr
                    LEFT JOIN stopporsak_kategorier sk ON sk.id = sr.kategori_id
                    WHERE DATE(sr.start_time) BETWEEN :from_date AND :to_date
                      AND sr.end_time IS NOT NULL
                    GROUP BY COALESCE(sk.namn, 'Okand orsak')
                    HAVING total_minutes > 0
                ");
                $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $orsak = $row['orsak'];
                    if (!isset($data[$orsak])) {
                        $data[$orsak] = ['minutes' => 0, 'count' => 0];
                    }
                    $data[$orsak]['minutes'] += (int)$row['total_minutes'];
                    $data[$orsak]['count']   += (int)$row['antal'];
                }
            }
        } catch (\PDOException $e) {
            error_log('ParetoController::collectStopData (stopporsak_registreringar): ' . $e->getMessage());
        }

        return $data;
    }

    // ================================================================
    // ENDPOINT: pareto-data
    // ================================================================

    /**
     * GET ?action=pareto&run=pareto-data&days=N
     * Returnerar Pareto-sorterad lista med kumulativ % och 80%-markering.
     */
    private function getParetoData(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $rawData = $this->collectStopData($fromDate, $toDate);

        // Sortera fallande efter total stopptid
        uasort($rawData, fn($a, $b) => $b['minutes'] - $a['minutes']);

        $totalMinutes = array_sum(array_column($rawData, 'minutes'));

        if ($totalMinutes === 0) {
            $this->sendSuccess([
                'items'         => [],
                'total_minutes' => 0,
                'days'          => $days,
                'from_date'     => $fromDate,
                'to_date'       => $toDate,
            ]);
            return;
        }

        $items      = [];
        $cumulative = 0.0;

        foreach ($rawData as $orsak => $vals) {
            $minutes    = $vals['minutes'];
            $pct        = round($minutes / $totalMinutes * 100, 2);
            $cumulative = round($cumulative + $pct, 2);
            $in80       = $cumulative <= 80.01; // liten tolerans for floating point

            $items[] = [
                'reason'         => $orsak,
                'minutes'        => $minutes,
                'count'          => $vals['count'],
                'percentage'     => $pct,
                'cumulative_pct' => $cumulative,
                'in_80pct'       => $in80,
            ];
        }

        $this->sendSuccess([
            'items'         => $items,
            'total_minutes' => $totalMinutes,
            'days'          => $days,
            'from_date'     => $fromDate,
            'to_date'       => $toDate,
        ]);
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    /**
     * GET ?action=pareto&run=summary&days=N
     * KPI-sammanfattning: total stopptid, antal orsaker, #1 orsak (%), antal inom 80%.
     */
    private function getSummary(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $rawData = $this->collectStopData($fromDate, $toDate);
        uasort($rawData, fn($a, $b) => $b['minutes'] - $a['minutes']);

        $totalMinutes  = array_sum(array_column($rawData, 'minutes'));
        $antalOrsaker  = count($rawData);

        // #1 orsak
        $topOrsak       = null;
        $topPct         = 0.0;
        $antalInom80    = 0;

        if ($totalMinutes > 0) {
            reset($rawData);
            $firstKey  = array_key_first($rawData);
            $topOrsak  = $firstKey;
            $topPct    = round($rawData[$firstKey]['minutes'] / $totalMinutes * 100, 1);

            // Rakna orsaker inom 80%
            $cumulative = 0.0;
            foreach ($rawData as $vals) {
                $pct        = $vals['minutes'] / $totalMinutes * 100;
                $cumulative += $pct;
                $antalInom80++;
                if ($cumulative >= 80.0) break;
            }
        }

        // Formatera total stopptid som h:min
        $totalH   = (int)floor($totalMinutes / 60);
        $totalMin = $totalMinutes % 60;
        $totalFormatted = $totalH > 0
            ? sprintf('%dh %02dmin', $totalH, $totalMin)
            : sprintf('%dmin', $totalMin);

        $this->sendSuccess([
            'days'             => $days,
            'from_date'        => $fromDate,
            'to_date'          => $toDate,
            'total_minutes'    => $totalMinutes,
            'total_formatted'  => $totalFormatted,
            'antal_orsaker'    => $antalOrsaker,
            'top_orsak'        => $topOrsak,
            'top_orsak_pct'    => $topPct,
            'antal_inom_80pct' => $antalInom80,
        ]);
    }
}
