<?php
/**
 * ProduktionseffektivitetController.php
 *
 * Produktionseffektivitet per timme — heatmap, sammanfattning och toppanalys.
 * Hjälper VD förstå vilka timmar på dygnet som är mest/minst produktiva.
 *
 * Endpoints via ?action=produktionseffektivitet&run=XXX:
 *   - run=hourly-heatmap   (GET, ?period=7/30/90) — matris veckodag x timme (0-23), snitt IBC/h
 *   - run=hourly-summary   (GET, ?period=30)       — per timme (0-23): snitt IBC, snitt IBC/h, antal, bästa/sämsta dag
 *   - run=peak-analysis    (GET, ?period=30)       — topp-3 mest produktiva + botten-3 minst produktiva timmar
 *
 * Auth: session krävs (401 om ej inloggad).
 */
class ProduktionseffektivitetController {
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
            case 'hourly-heatmap':  $this->getHourlyHeatmap();  break;
            case 'hourly-summary':  $this->getHourlySummary();  break;
            case 'peak-analysis':   $this->getPeakAnalysis();   break;
            default:                $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
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
     * Returnera kolumnnamnet för timestamp i rebotling_ibc.
     */
    private function getIbcTimestampColumn(): string {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM rebotling_ibc")->fetchAll(PDO::FETCH_COLUMN);
            return in_array('timestamp', $cols) ? 'timestamp' : 'datum';
        } catch (\Exception $e) {
            error_log('ProduktionseffektivitetController::getIbcTimestampColumn: ' . $e->getMessage());
            return 'datum';
        }
    }

    /**
     * Hämta period från GET, validera och returnera antal dagar (7, 30 eller 90).
     */
    private function getPeriod(): int {
        $p = (int)($_GET['period'] ?? 30);
        return in_array($p, [7, 30, 90]) ? $p : 30;
    }

    /**
     * Veckodagsnamn på svenska (0=måndag ... 6=söndag).
     * MySQL DAYOFWEEK: 1=sön, 2=mån, ..., 7=lör
     * Vi konverterar till 0=mån .. 6=sön.
     */
    private function veckodagNamn(int $idx): string {
        $namn = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
        return $namn[$idx] ?? "Dag $idx";
    }

    // ================================================================
    // run=hourly-heatmap
    // Returnerar matris: veckodag (0-6, mån-sön) x timme (0-23)
    // Varje cell = snitt antal IBC registrerade under den timmen
    // ================================================================

    private function getHourlyHeatmap(): void {
        $period = $this->getPeriod();
        $ibcCol = $this->getIbcTimestampColumn();

        // Initiera matris med null för alla celler
        $matrix = [];
        for ($d = 0; $d < 7; $d++) {
            $matrix[$d] = array_fill(0, 24, null);
        }

        try {
            // MySQL DAYOFWEEK: 1=sön, 2=mån, 3=tis, 4=ons, 5=tor, 6=fre, 7=lör
            // Konvertera till 0=mån..6=sön: (DAYOFWEEK - 2 + 7) % 7
            $sql = "
                SELECT
                    MOD(DAYOFWEEK({$ibcCol}) - 2 + 7, 7) AS veckodag,
                    HOUR({$ibcCol}) AS timme,
                    COUNT(*) AS antal
                FROM rebotling_ibc
                WHERE {$ibcCol} >= DATE_SUB(NOW(), INTERVAL :period DAY)
                GROUP BY veckodag, timme
                ORDER BY veckodag, timme
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':period' => $period]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Räkna antal unika dagar per veckodag för att beräkna snitt
            $dagCountSql = "
                SELECT
                    MOD(DAYOFWEEK({$ibcCol}) - 2 + 7, 7) AS veckodag,
                    COUNT(DISTINCT DATE({$ibcCol})) AS antal_dagar
                FROM rebotling_ibc
                WHERE {$ibcCol} >= DATE_SUB(NOW(), INTERVAL :period DAY)
                GROUP BY veckodag
            ";
            $dagStmt = $this->pdo->prepare($dagCountSql);
            $dagStmt->execute([':period' => $period]);
            $dagCounts = [];
            foreach ($dagStmt->fetchAll(PDO::FETCH_ASSOC) as $dc) {
                $dagCounts[(int)$dc['veckodag']] = (int)$dc['antal_dagar'];
            }

            // Fyll in snitt per cell
            foreach ($rows as $row) {
                $d = (int)$row['veckodag'];
                $t = (int)$row['timme'];
                $antal = (int)$row['antal'];
                $antalDagar = $dagCounts[$d] ?? 1;
                if ($antalDagar > 0) {
                    $matrix[$d][$t] = round($antal / $antalDagar, 1);
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionseffektivitetController::getHourlyHeatmap: ' . $e->getMessage());
        }

        // Bygg svarsstruktur
        $veckodagar = [];
        for ($d = 0; $d < 7; $d++) {
            $veckodagar[] = [
                'index' => $d,
                'namn'  => $this->veckodagNamn($d),
                'data'  => $matrix[$d],
            ];
        }

        // Beräkna globalt max-värde för färgskala
        $maxVal = 0;
        foreach ($matrix as $dagData) {
            foreach ($dagData as $v) {
                if ($v !== null && $v > $maxVal) $maxVal = $v;
            }
        }

        $this->sendSuccess([
            'period'    => $period,
            'veckodagar' => $veckodagar,
            'max_val'   => $maxVal,
            'timmar'    => range(0, 23),
        ]);
    }

    // ================================================================
    // run=hourly-summary
    // Per timme (0-23): snitt IBC/h, antal skift-dagar med data,
    // bästa dag (veckodag-namn), sämsta dag
    // ================================================================

    private function getHourlySummary(): void {
        $period = $this->getPeriod();
        $ibcCol = $this->getIbcTimestampColumn();

        $summary = [];
        for ($h = 0; $h < 24; $h++) {
            $summary[$h] = [
                'timme'        => $h,
                'snitt_ibc'    => null,
                'snitt_ibc_h'  => null,
                'antal_dagar'  => 0,
                'basta_dag'    => null,
                'samsta_dag'   => null,
            ];
        }

        try {
            // Totalt antal IBC per timme och datum
            $sql = "
                SELECT
                    HOUR({$ibcCol}) AS timme,
                    DATE({$ibcCol}) AS datum,
                    MOD(DAYOFWEEK({$ibcCol}) - 2 + 7, 7) AS veckodag,
                    COUNT(*) AS antal
                FROM rebotling_ibc
                WHERE {$ibcCol} >= DATE_SUB(NOW(), INTERVAL :period DAY)
                GROUP BY timme, datum, veckodag
                ORDER BY timme, datum
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':period' => $period]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Grupper per timme
            $perTimme = [];
            for ($h = 0; $h < 24; $h++) {
                $perTimme[$h] = [];
            }
            foreach ($rows as $row) {
                $h = (int)$row['timme'];
                $perTimme[$h][] = [
                    'datum'    => $row['datum'],
                    'veckodag' => (int)$row['veckodag'],
                    'antal'    => (int)$row['antal'],
                ];
            }

            // Beräkna statistik per timme
            foreach ($perTimme as $h => $dagData) {
                if (empty($dagData)) continue;

                $antal_dagar = count($dagData);
                $totalt = array_sum(array_column($dagData, 'antal'));
                $snitt = round($totalt / $antal_dagar, 1);

                // Bästa och sämsta dag (veckodag med höst/lägst snitt)
                $veckodagSumma = [];
                $veckodagCount = [];
                foreach ($dagData as $dd) {
                    $vd = $dd['veckodag'];
                    $veckodagSumma[$vd] = ($veckodagSumma[$vd] ?? 0) + $dd['antal'];
                    $veckodagCount[$vd] = ($veckodagCount[$vd] ?? 0) + 1;
                }
                $veckodagSnitt = [];
                foreach ($veckodagSumma as $vd => $sum) {
                    $veckodagSnitt[$vd] = $sum / $veckodagCount[$vd];
                }

                arsort($veckodagSnitt);
                $bastaDagIdx  = array_key_first($veckodagSnitt);
                $samstaDagIdx = array_key_last($veckodagSnitt);

                $summary[$h] = [
                    'timme'       => $h,
                    'snitt_ibc'   => $snitt,
                    'snitt_ibc_h' => $snitt, // 1 observation per timme = samma värde
                    'antal_dagar' => $antal_dagar,
                    'basta_dag'   => $this->veckodagNamn($bastaDagIdx),
                    'samsta_dag'  => $this->veckodagNamn($samstaDagIdx),
                ];
            }
        } catch (\PDOException $e) {
            error_log('ProduktionseffektivitetController::getHourlySummary: ' . $e->getMessage());
        }

        $this->sendSuccess([
            'period'  => $period,
            'timmar'  => array_values($summary),
        ]);
    }

    // ================================================================
    // run=peak-analysis
    // Identifiera topp-3 mest produktiva + botten-3 minst produktiva timmar
    // ================================================================

    private function getPeakAnalysis(): void {
        $period = $this->getPeriod();
        $ibcCol = $this->getIbcTimestampColumn();

        $timmeData = [];

        try {
            $sql = "
                SELECT
                    HOUR({$ibcCol}) AS timme,
                    COUNT(*) AS totalt_ibc,
                    COUNT(DISTINCT DATE({$ibcCol})) AS antal_dagar,
                    ROUND(COUNT(*) / COUNT(DISTINCT DATE({$ibcCol})), 1) AS snitt_ibc_per_dag
                FROM rebotling_ibc
                WHERE {$ibcCol} >= DATE_SUB(NOW(), INTERVAL :period DAY)
                GROUP BY timme
                ORDER BY snitt_ibc_per_dag DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':period' => $period]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $timmeData[] = [
                    'timme'           => (int)$row['timme'],
                    'timme_label'     => sprintf('%02d:00–%02d:00', (int)$row['timme'], ((int)$row['timme'] + 1) % 24),
                    'totalt_ibc'      => (int)$row['totalt_ibc'],
                    'antal_dagar'     => (int)$row['antal_dagar'],
                    'snitt_ibc'       => (float)$row['snitt_ibc_per_dag'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('ProduktionseffektivitetController::getPeakAnalysis: ' . $e->getMessage());
        }

        if (empty($timmeData)) {
            $this->sendSuccess([
                'period'     => $period,
                'topp3'      => [],
                'botten3'    => [],
                'skillnad_pct' => null,
                'har_data'   => false,
            ]);
            return;
        }

        // Sortera fallande för topp, stigande för botten
        $sortedDesc = $timmeData; // redan sorterat fallande från SQL
        $sortedAsc  = $timmeData;
        usort($sortedAsc, fn($a, $b) => $a['snitt_ibc'] <=> $b['snitt_ibc']);

        $topp3   = array_slice($sortedDesc, 0, 3);
        $botten3 = array_slice($sortedAsc, 0, 3);

        // Skillnad i % mellan mest och minst produktiv timme
        $maxSnitt = $topp3[0]['snitt_ibc'] ?? 0;
        $minSnitt = $botten3[0]['snitt_ibc'] ?? 0;
        $skillnadPct = null;
        if ($minSnitt > 0) {
            $skillnadPct = round((($maxSnitt - $minSnitt) / $minSnitt) * 100, 1);
        } elseif ($maxSnitt > 0) {
            $skillnadPct = 100.0;
        }

        $this->sendSuccess([
            'period'      => $period,
            'topp3'       => $topp3,
            'botten3'     => $botten3,
            'skillnad_pct' => $skillnadPct,
            'har_data'    => true,
        ]);
    }
}
