<?php
/**
 * MaskinDrifttidController.php
 * Maskin-drifttid heatmap — visar visuellt nar maskiner kor vs star stilla.
 *
 * Endpoints via ?action=maskin-drifttid&run=XXX:
 *   - run=heatmap     -> timvis produktion per dag (for heatmap-grid)
 *   - run=kpi         -> KPI:er: total drifttid denna vecka, snitt, basta/samsta dag
 *   - run=dag-detalj  -> detaljer for en specifik dag (?datum=YYYY-MM-DD)
 *   - run=stationer   -> lista tillgangliga stationer/maskiner
 *
 * Drifttid beraknas fran rebotling_ibc — timestamp visar nar IBC:er processerats.
 * Timmar utan poster = potentiellt stopp.
 */
class MaskinDrifttidController {
    private $pdo;

    // Arbetstid (timmar att visa i heatmap)
    private const START_HOUR = 6;
    private const END_HOUR   = 22;

    // Logiska stationer (maskiner)
    private const STATIONS = [
        ['id' => 'alla',         'namn' => 'Alla maskiner'],
        ['id' => 'inspektion',   'namn' => 'Inspektion'],
        ['id' => 'tvatt',        'namn' => 'Tvatt'],
        ['id' => 'fyllning',     'namn' => 'Fyllning'],
        ['id' => 'etikettering', 'namn' => 'Etikettering'],
        ['id' => 'slutkontroll', 'namn' => 'Slutkontroll'],
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
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'heatmap':    $this->getHeatmap();    break;
            case 'kpi':        $this->getKpi();        break;
            case 'dag-detalj': $this->getDagDetalj();  break;
            case 'stationer':  $this->getStationer();  break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // Helpers
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

    private function getDagar(): int {
        return max(1, min(365, intval($_GET['dagar'] ?? 14)));
    }

    /**
     * Hamta antal IBC:er per timme per dag fran rebotling_ibc.
     * Returnerar array med [dag => [timme => antal]].
     */
    private function fetchHourlyProduction(string $fromDate, string $toDate): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    HOUR(datum) AS timme,
                    COUNT(*) AS antal
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND datum IS NOT NULL
                GROUP BY DATE(datum), HOUR(datum)
                ORDER BY dag ASC, timme ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $dag   = $row['dag'];
                $timme = (int)$row['timme'];
                $antal = (int)$row['antal'];
                if (!isset($result[$dag])) {
                    $result[$dag] = [];
                }
                $result[$dag][$timme] = $antal;
            }
            return $result;
        } catch (\PDOException $e) {
            error_log('MaskinDrifttidController::fetchHourlyProduction: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Berakna drifttid i timmar for en dag baserat pa timdata.
     * En timme raknas som "aktiv" om det finns minst 1 IBC producerad.
     */
    private function calculateDrifttid(array $hourData): float {
        $activeHours = 0;
        for ($h = self::START_HOUR; $h < self::END_HOUR; $h++) {
            if (isset($hourData[$h]) && $hourData[$h] > 0) {
                $activeHours++;
            }
        }
        return (float)$activeHours;
    }

    /**
     * Bestam fargkategori for en cell.
     * - gron (hog): >= 5 IBC
     * - gul (lag): 1-4 IBC
     * - rod (stopp): 0 IBC inom arbetstid
     * - gra: utanfor arbetstid
     */
    private function cellStatus(int $timme, int $antal): string {
        if ($timme < self::START_HOUR || $timme >= self::END_HOUR) {
            return 'utanfor';
        }
        if ($antal >= 5) return 'hog';
        if ($antal >= 1) return 'lag';
        return 'stopp';
    }

    // ================================================================
    // run=heatmap — Timvis produktion per dag (for heatmap-grid)
    // ================================================================

    private function getHeatmap(): void {
        $dagar    = $this->getDagar();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$dagar} days"));

        $hourlyData = $this->fetchHourlyProduction($fromDate, $toDate);

        // Bygg komplett datum-array
        $dagar_array = [];
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        while ($d <= $end) {
            $dagar_array[] = $d->format('Y-m-d');
            $d->modify('+1 day');
        }

        // Timmar (kolumner)
        $timmar = [];
        for ($h = self::START_HOUR; $h < self::END_HOUR; $h++) {
            $timmar[] = $h;
        }

        // Bygg heatmap-rader
        $rader = [];
        foreach ($dagar_array as $dag) {
            $dayData = $hourlyData[$dag] ?? [];
            $celler  = [];
            $dagTotal = 0;
            $aktivaTimmar = 0;

            foreach ($timmar as $h) {
                $antal  = $dayData[$h] ?? 0;
                $status = $this->cellStatus($h, $antal);
                $dagTotal += $antal;
                if ($antal > 0 && $h >= self::START_HOUR && $h < self::END_HOUR) {
                    $aktivaTimmar++;
                }
                $celler[] = [
                    'timme'  => $h,
                    'antal'  => $antal,
                    'status' => $status,
                ];
            }

            // Veckodag
            $dt = new \DateTime($dag);
            $weekdayNum = (int)$dt->format('N'); // 1=man, 7=son
            $veckodagar = ['', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor', 'Son'];

            $rader[] = [
                'datum'          => $dag,
                'veckodag'       => $veckodagar[$weekdayNum],
                'datum_kort'     => $dt->format('d/m'),
                'celler'         => $celler,
                'total_ibc'      => $dagTotal,
                'aktiva_timmar'  => $aktivaTimmar,
                'max_timmar'     => self::END_HOUR - self::START_HOUR,
                'drifttid_pct'   => (self::END_HOUR - self::START_HOUR) > 0
                    ? round(($aktivaTimmar / (self::END_HOUR - self::START_HOUR)) * 100, 1)
                    : 0.0,
            ];
        }

        $this->sendSuccess([
            'dagar'     => count($dagar_array),
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'timmar'    => $timmar,
            'rader'     => $rader,
        ]);
    }

    // ================================================================
    // run=kpi — KPI:er
    // ================================================================

    private function getKpi(): void {
        // Denna vecka (man-son)
        // Bugfix #285: strtotime('monday this week') ger nasta mandag pa sondagar
        $veckaStart = date('Y-m-d', strtotime('-' . ((int)date('N') - 1) . ' days'));
        $veckaSlut  = date('Y-m-d'); // idag
        $dagar      = $this->getDagar();
        $fromDate   = date('Y-m-d', strtotime("-{$dagar} days"));
        $toDate     = date('Y-m-d');

        // Hamta timdata for KPI-berakning (hela vald period)
        $hourlyData = $this->fetchHourlyProduction($fromDate, $toDate);

        // Denna vecka
        $veckaHourly = $this->fetchHourlyProduction($veckaStart, $veckaSlut);
        $totalDrifttidVecka = 0.0;
        foreach ($veckaHourly as $dag => $hours) {
            $totalDrifttidVecka += $this->calculateDrifttid($hours);
        }

        // Berakna daglig drifttid for perioden
        $dagligDrifttid = [];
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        while ($d <= $end) {
            $dag = $d->format('Y-m-d');
            $hours = $hourlyData[$dag] ?? [];
            $dt = $this->calculateDrifttid($hours);
            $dagligDrifttid[$dag] = $dt;
            $d->modify('+1 day');
        }

        // Genomsnittlig daglig drifttid (exkludera dagar med 0)
        $drifttidDagar = array_filter($dagligDrifttid, fn($v) => $v > 0);
        $snittDrifttid = count($drifttidDagar) > 0
            ? round(array_sum($drifttidDagar) / count($drifttidDagar), 1)
            : 0.0;

        // Basta & samsta dag
        $bastaDag  = null;
        $samstaDag = null;
        $maxDt     = -1.0;
        $minDt     = 999.0;

        foreach ($dagligDrifttid as $dag => $dt) {
            if ($dt > $maxDt) {
                $maxDt    = $dt;
                $bastaDag = $dag;
            }
            if ($dt < $minDt && $dt >= 0) {
                $minDt     = $dt;
                $samstaDag = $dag;
            }
        }

        // Total produktion denna vecka
        $totalIbcVecka = 0;
        foreach ($veckaHourly as $hours) {
            foreach ($hours as $antal) {
                $totalIbcVecka += $antal;
            }
        }

        $maxMojligTimmar = (self::END_HOUR - self::START_HOUR);

        $this->sendSuccess([
            'vecka_drifttid'       => $totalDrifttidVecka,
            'vecka_drifttid_str'   => $this->formatTimmar($totalDrifttidVecka),
            'vecka_ibc'            => $totalIbcVecka,
            'snitt_daglig_drifttid' => $snittDrifttid,
            'snitt_str'            => $this->formatTimmar($snittDrifttid),
            'snitt_pct'            => $maxMojligTimmar > 0
                ? round(($snittDrifttid / $maxMojligTimmar) * 100, 1) : 0.0,
            'basta_dag'            => $bastaDag,
            'basta_dag_timmar'     => $bastaDag ? ($dagligDrifttid[$bastaDag] ?? 0.0) : 0.0,
            'samsta_dag'           => $samstaDag,
            'samsta_dag_timmar'    => $samstaDag ? ($dagligDrifttid[$samstaDag] ?? 0.0) : 0.0,
            'max_timmar_per_dag'   => $maxMojligTimmar,
            'dagar'                => $dagar,
            'from_date'            => $fromDate,
            'to_date'              => $toDate,
        ]);
    }

    private function formatTimmar(float $timmar): string {
        $h = (int)$timmar;
        $m = (int)round(($timmar - $h) * 60);
        if ($m > 0) return "{$h}h {$m}m";
        return "{$h}h";
    }

    // ================================================================
    // run=dag-detalj — Detaljer for en specifik dag
    // ================================================================

    private function getDagDetalj(): void {
        $datum = trim($_GET['datum'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            $this->sendError('Ogiltigt datum (YYYY-MM-DD)');
            return;
        }

        $hourlyData = $this->fetchHourlyProduction($datum, $datum);
        $dayData    = $hourlyData[$datum] ?? [];

        $timmar = [];
        $totalIbc = 0;
        $aktivaTimmar = 0;

        for ($h = self::START_HOUR; $h < self::END_HOUR; $h++) {
            $antal  = $dayData[$h] ?? 0;
            $status = $this->cellStatus($h, $antal);
            $totalIbc += $antal;
            if ($antal > 0) $aktivaTimmar++;

            $timmar[] = [
                'timme'      => $h,
                'timme_str'  => sprintf('%02d:00-%02d:00', $h, $h + 1),
                'antal'      => $antal,
                'status'     => $status,
            ];
        }

        $maxTimmar = self::END_HOUR - self::START_HOUR;
        $dt = new \DateTime($datum);
        $weekdayNum = (int)$dt->format('N');
        $veckodagar = ['', 'Mandag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lordag', 'Sondag'];

        $this->sendSuccess([
            'datum'         => $datum,
            'veckodag'      => $veckodagar[$weekdayNum],
            'datum_kort'    => $dt->format('d/m'),
            'timmar'        => $timmar,
            'total_ibc'     => $totalIbc,
            'aktiva_timmar' => $aktivaTimmar,
            'max_timmar'    => $maxTimmar,
            'drifttid_pct'  => $maxTimmar > 0
                ? round(($aktivaTimmar / $maxTimmar) * 100, 1) : 0.0,
        ]);
    }

    // ================================================================
    // run=stationer — Lista tillgangliga stationer
    // ================================================================

    private function getStationer(): void {
        $this->sendSuccess([
            'stationer' => self::STATIONS,
        ]);
    }
}
