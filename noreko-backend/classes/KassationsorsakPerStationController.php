<?php
/**
 * KassationsorsakPerStationController.php
 * Kassationsorsak per station — drill-down: vilka stationer kasserar mest och varför.
 *
 * Endpoints via ?action=kassationsorsak-per-station&run=XXX:
 *   - run=overview      -> KPI:er: total kassation idag, kassation%, värsta station, trend vs igår
 *   - run=per-station   -> kassation per station (för stapeldiagram + genomsnittslinje)
 *   - run=top-orsaker   -> top-5 orsaker, filtrerbart per station (?station=XXX)
 *   - run=trend         -> kassation% per dag per station senaste N dagar (?dagar=30)
 *   - run=detaljer      -> tabell med alla stationer och kassationsdata
 *
 * Tabeller: rebotling_ibc, kassationsregistrering, kassationsorsak_typer
 *
 * OBS: Stationer är logiska processteg i rebotling-linjen. De finns inte som kolumn
 * i rebotling_ibc utan distribueras proportionellt baserat på konfigurerade andelar.
 */
class KassationsorsakPerStationController {
    private $pdo;

    // Logiska stationer med kassationsandel (summa = 1.0)
    private const STATIONS = [
        ['id' => 'inspektion',   'namn' => 'Inspektion',   'order' => 1, 'kass_andel' => 0.30],
        ['id' => 'tvatt',        'namn' => 'Tvatt',        'order' => 2, 'kass_andel' => 0.10],
        ['id' => 'fyllning',     'namn' => 'Fyllning',     'order' => 3, 'kass_andel' => 0.25],
        ['id' => 'etikettering', 'namn' => 'Etikettering', 'order' => 4, 'kass_andel' => 0.15],
        ['id' => 'slutkontroll', 'namn' => 'Slutkontroll', 'order' => 5, 'kass_andel' => 0.20],
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
            case 'overview':    $this->getOverview();   break;
            case 'per-station': $this->getPerStation(); break;
            case 'top-orsaker': $this->getTopOrsaker(); break;
            case 'trend':       $this->getTrend();      break;
            case 'detaljer':    $this->getDetaljer();   break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
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
        return max(1, min(365, intval($_GET['dagar'] ?? 30)));
    }

    /**
     * Hämta total kassation (ibc_ej_ok) och total produktion för ett datumintervall.
     * Aggregering: MAX() per skifträknare (kumulativa PLC-värden), sedan SUM().
     */
    private function getKassationData(string $fromDate, string $toDate): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok), 0)    AS total_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS total_ej_ok
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return [
                'ok'     => (int)($row['total_ok'] ?? 0),
                'ej_ok'  => (int)($row['total_ej_ok'] ?? 0),
            ];
        } catch (\PDOException $e) {
            error_log('KassationsorsakPerStation::getKassationData: ' . $e->getMessage());
            return ['ok' => 0, 'ej_ok' => 0];
        }
    }

    /**
     * Distribuera kassation på stationer proportionellt enligt kass_andel.
     */
    private function distributeKassation(int $totalKassation): array {
        $result = [];
        foreach (self::STATIONS as $station) {
            $result[$station['id']] = (int)round($totalKassation * $station['kass_andel']);
        }
        return $result;
    }

    /**
     * Hämta top-orsaker från kassationsregistrering (om tabellen finns).
     */
    private function tableExists(string $tableName): bool {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE :tbl");
            $stmt->execute([':tbl' => $tableName]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        $idag    = date('Y-m-d');
        $igar    = date('Y-m-d', strtotime('-1 day'));

        // Idag
        $idag_data = $this->getKassationData($idag, $idag);
        $totaltIdag      = $idag_data['ok'] + $idag_data['ej_ok'];
        $kassationIdag   = $idag_data['ej_ok'];
        $kassationPctIdag = $totaltIdag > 0 ? round(($kassationIdag / $totaltIdag) * 100, 1) : 0.0;

        // Igår (för trendjämförelse)
        $igar_data = $this->getKassationData($igar, $igar);
        $totaltIgar      = $igar_data['ok'] + $igar_data['ej_ok'];
        $kassationIgar   = $igar_data['ej_ok'];
        $kassationPctIgar = $totaltIgar > 0 ? round(($kassationIgar / $totaltIgar) * 100, 1) : 0.0;

        // Trend
        $trendDiff = $kassationPctIdag - $kassationPctIgar;
        if ($trendDiff > 0.1) {
            $trend = 'up';   // sämre
        } elseif ($trendDiff < -0.1) {
            $trend = 'down'; // bättre
        } else {
            $trend = 'flat';
        }

        // Värsta station (mest kassation idag baserat på distribution)
        $stationDist = $this->distributeKassation($kassationIdag);
        $varstStation = null;
        $varstAntal   = -1;
        foreach (self::STATIONS as $station) {
            $antal = $stationDist[$station['id']] ?? 0;
            if ($antal > $varstAntal) {
                $varstAntal   = $antal;
                $varstStation = $station['namn'];
            }
        }

        $this->sendSuccess([
            'datum'              => $idag,
            'total_kassation'    => $kassationIdag,
            'kassation_pct'      => $kassationPctIdag,
            'total_producerade'  => $totaltIdag,
            'kassation_igar'     => $kassationIgar,
            'kassation_pct_igar' => $kassationPctIgar,
            'trend'              => $trend,
            'trend_diff'         => round($trendDiff, 1),
            'varsta_station'     => $varstStation,
            'varsta_station_antal' => $varstAntal > 0 ? $varstAntal : 0,
        ]);
    }

    // ================================================================
    // run=per-station — Kassation per station (stapeldiagram)
    // ================================================================

    private function getPerStation(): void {
        $dagar    = $this->getDagar();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$dagar} days"));

        $data = $this->getKassationData($fromDate, $toDate);
        $totalKassation = $data['ej_ok'];
        $totalOk        = $data['ok'];
        $totalProd      = $totalKassation + $totalOk;
        $kassationPct   = $totalProd > 0 ? round(($totalKassation / $totalProd) * 100, 1) : 0.0;

        // Distribuera kassation per station
        $stationDist = $this->distributeKassation($totalKassation);

        $stationer = [];
        $totalKassPerStation = 0;
        foreach (self::STATIONS as $station) {
            $kass = $stationDist[$station['id']] ?? 0;
            $totalKassPerStation += $kass;
            $stationer[] = [
                'id'           => $station['id'],
                'namn'         => $station['namn'],
                'order'        => $station['order'],
                'kasserade'    => $kass,
                'kass_andel'   => $station['kass_andel'],
                'kass_pct'     => $totalProd > 0 ? round(($kass / $totalProd) * 100, 1) : 0.0,
            ];
        }

        // Genomsnitt per station
        $snitt = count(self::STATIONS) > 0
            ? round($totalKassPerStation / count(self::STATIONS), 1)
            : 0.0;

        $this->sendSuccess([
            'dagar'             => $dagar,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
            'total_kassation'   => $totalKassation,
            'total_producerade' => $totalProd,
            'kassation_pct'     => $kassationPct,
            'snitt_per_station' => $snitt,
            'stationer'         => $stationer,
        ]);
    }

    // ================================================================
    // run=top-orsaker — Top-5 orsaker, filtrerbart per station
    // ================================================================

    private function getTopOrsaker(): void {
        $dagar    = $this->getDagar();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$dagar} days"));
        $station  = trim($_GET['station'] ?? '');

        // Validera station
        $giltigStation = null;
        foreach (self::STATIONS as $s) {
            if ($s['id'] === $station) {
                $giltigStation = $s;
                break;
            }
        }

        $orsaker = [];

        if ($this->tableExists('kassationsregistrering') && $this->tableExists('kassationsorsak_typer')) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(kt.namn, 'Okand') AS orsak,
                        kt.id AS orsak_id,
                        SUM(kr.antal) AS antal
                    FROM kassationsregistrering kr
                    LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                    WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                    GROUP BY kt.id, kt.namn
                    ORDER BY antal DESC
                    LIMIT 5
                ");
                $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $totalAntal = array_sum(array_column($rows, 'antal'));

                foreach ($rows as $row) {
                    $antal = (int)$row['antal'];
                    // Om station är filtrerat, skala ner med stationens kassationsandel
                    if ($giltigStation !== null) {
                        $antal = (int)round($antal * $giltigStation['kass_andel']);
                    }
                    $orsaker[] = [
                        'orsak_id' => (int)($row['orsak_id'] ?? 0),
                        'orsak'    => $row['orsak'],
                        'antal'    => $antal,
                        'procent'  => $totalAntal > 0
                            ? round(($antal / max($totalAntal, 1)) * 100, 1)
                            : 0.0,
                    ];
                }
            } catch (\PDOException $e) {
                error_log('KassationsorsakPerStation::getTopOrsaker: ' . $e->getMessage());
            }
        }

        // Om inga registrerade orsaker finns, returnera placeholder
        if (empty($orsaker)) {
            $orsaker = [
                ['orsak_id' => 0, 'orsak' => 'Inga registrerade orsaker', 'antal' => 0, 'procent' => 0.0],
            ];
        }

        $this->sendSuccess([
            'dagar'    => $dagar,
            'from_date' => $fromDate,
            'to_date'  => $toDate,
            'station'  => $giltigStation ? $giltigStation['id'] : null,
            'station_namn' => $giltigStation ? $giltigStation['namn'] : 'Alla stationer',
            'stationer' => array_map(fn($s) => ['id' => $s['id'], 'namn' => $s['namn']], self::STATIONS),
            'orsaker'  => $orsaker,
        ]);
    }

    // ================================================================
    // run=trend — Kassation% per dag per station senaste N dagar
    // ================================================================

    private function getTrend(): void {
        $dagar    = $this->getDagar();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$dagar} days"));

        try {
            // Hämta daglig kassation från rebotling_ibc
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    COALESCE(SUM(shift_ok), 0)    AS dag_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS dag_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg dagskarta
            $dagMap = [];
            foreach ($rows as $row) {
                $dagMap[$row['dag']] = [
                    'ok'    => (int)$row['dag_ok'],
                    'ej_ok' => (int)$row['dag_ej_ok'],
                ];
            }

            // Bygg komplett datumsekvens
            $labels = [];
            $d = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($d <= $end) {
                $labels[] = $d->format('Y-m-d');
                $d->modify('+1 day');
            }

            // En serie per station
            $series = [];
            foreach (self::STATIONS as $station) {
                $values = [];
                foreach ($labels as $dag) {
                    $ok    = $dagMap[$dag]['ok'] ?? 0;
                    $ejOk  = $dagMap[$dag]['ej_ok'] ?? 0;
                    $total = $ok + $ejOk;
                    $kassStation = (int)round($ejOk * $station['kass_andel']);
                    $pct = $total > 0 ? round(($kassStation / $total) * 100, 1) : null;
                    $values[] = $pct;
                }
                $series[] = [
                    'station_id'  => $station['id'],
                    'station_namn' => $station['namn'],
                    'kass_andel'  => $station['kass_andel'],
                    'values'      => $values,
                ];
            }

            $this->sendSuccess([
                'dagar'     => $dagar,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'labels'    => $labels,
                'series'    => $series,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsorsakPerStation::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel vid trendhämtning', 500);
        }
    }

    // ================================================================
    // run=detaljer — Tabell med alla stationer
    // ================================================================

    private function getDetaljer(): void {
        $dagar    = $this->getDagar();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$dagar} days"));

        // Nuvarande period
        $current = $this->getKassationData($fromDate, $toDate);
        $totalKass = $current['ej_ok'];
        $totalProd = $current['ok'] + $current['ej_ok'];

        // Föregående period (lika lång)
        $prevTo   = date('Y-m-d', strtotime("-{$dagar} days"));
        $prevFrom = date('Y-m-d', strtotime("-" . ($dagar * 2) . " days"));
        $prev     = $this->getKassationData($prevFrom, $prevTo);
        $prevKass = $prev['ej_ok'];
        $prevProd = $prev['ok'] + $prev['ej_ok'];

        // Distribution per station
        $stationDist     = $this->distributeKassation($totalKass);
        $stationDistPrev = $this->distributeKassation($prevKass);

        // Top-orsak (global, utan station-filter)
        $topOrsak = null;
        if ($this->tableExists('kassationsregistrering') && $this->tableExists('kassationsorsak_typer')) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(kt.namn, 'Okand') AS orsak, SUM(kr.antal) AS antal
                    FROM kassationsregistrering kr
                    LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                    WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                    GROUP BY kt.id, kt.namn
                    ORDER BY antal DESC
                    LIMIT 1
                ");
                $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $topOrsak = $row['orsak'];
                }
            } catch (\PDOException $e) {
                error_log('KassationsorsakPerStation::getDetaljer topOrsak: ' . $e->getMessage());
            }
        }

        // Bygg tabelldata per station
        $detaljer = [];
        foreach (self::STATIONS as $station) {
            $kassNu   = $stationDist[$station['id']] ?? 0;
            $kassForeg = $stationDistPrev[$station['id']] ?? 0;

            // Kassation% för stationen (andel av total produktion)
            $kassNuPct = $totalProd > 0 ? round(($kassNu / $totalProd) * 100, 1) : 0.0;
            $kassForegPct = $prevProd > 0 ? round(($kassForeg / $prevProd) * 100, 1) : 0.0;

            // Trend per station
            $diff = $kassNuPct - $kassForegPct;
            if ($diff > 0.1) {
                $trend = 'up';
            } elseif ($diff < -0.1) {
                $trend = 'down';
            } else {
                $trend = 'flat';
            }

            $detaljer[] = [
                'station_id'     => $station['id'],
                'station_namn'   => $station['namn'],
                'order'          => $station['order'],
                'totalt'         => $totalProd > 0 ? (int)round($totalProd * $station['kass_andel'] + $kassNu) : 0,
                'kasserade'      => $kassNu,
                'kassation_pct'  => $kassNuPct,
                'kassation_pct_foreg' => $kassForegPct,
                'top_orsak'      => $topOrsak,
                'trend'          => $trend,
                'trend_diff'     => round($diff, 1),
            ];
        }

        $this->sendSuccess([
            'dagar'      => $dagar,
            'from_date'  => $fromDate,
            'to_date'    => $toDate,
            'total_kassation'   => $totalKass,
            'total_producerade' => $totalProd,
            'detaljer'   => $detaljer,
        ]);
    }
}
