<?php

/**
 * ProduktionskostnadController
 * Produktionskostnad per IBC — beräknar uppskattad kostnad per producerad IBC
 * baserat på stopptid, energi, bemanning och kassation.
 *
 * Endpoints via ?action=produktionskostnad&run=XXX:
 *
 *   GET  run=overview          — KPI:er: kostnad/IBC idag, totalkostnad, trend, kassationskostnad
 *   GET  run=breakdown         (?date, ?period=dag|vecka|manad)  — kostnadsuppdelning per kategori
 *   GET  run=trend             (?period=30|90)  — kostnad/IBC per dag (linjediagram)
 *   GET  run=daily-table       (?from&to)       — daglig tabell
 *   GET  run=shift-comparison  (?date, ?period) — kostnad per skift (stapeldiagram)
 *   GET  run=config            — hämta kostnadskonfiguration
 *   POST run=update-config     — uppdatera kostnadsfaktorer
 */
class ProduktionskostnadController {
    private $pdo;

    // Default-konfiguration om tabellen är tom
    private $defaultConfig = [
        'energi'    => ['varde' => 150.0, 'enhet' => 'kr/h'],
        'bemanning' => ['varde' => 350.0, 'enhet' => 'kr/h'],
        'material'  => ['varde' =>  50.0, 'enhet' => 'kr/IBC'],
        'kassation' => ['varde' => 200.0, 'enhet' => 'kr/IBC'],
        'overhead'  => ['varde' => 100.0, 'enhet' => 'kr/h'],
    ];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'overview':         $this->getOverview();        break;
                case 'breakdown':        $this->getBreakdown();       break;
                case 'trend':            $this->getTrend();           break;
                case 'daily-table':      $this->getDailyTable();      break;
                case 'shift-comparison': $this->getShiftComparison(); break;
                case 'config':           $this->getConfig();          break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'update-config':
                    $this->requireLogin();
                    $this->updateConfig();
                    break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
            }
            return;
        }

        $this->sendError('Ogiltig metod', 405);
    }

    // =========================================================================
    // Auth
    // =========================================================================

    private function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            $this->sendError('Sessionen har gått ut. Logga in igen.', 401);
            exit;
        }
    }

    private function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function ensureTables(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'produktionskostnad_config'"
            )->fetchColumn();
            if (!$check) {
                $migrationPath = __DIR__ . '/../migrations/2026-03-12_produktionskostnad.sql';
                $sql = file_get_contents($migrationPath);
                if ($sql === false) {
                    error_log('ProduktionskostnadController::ensureTables: kunde inte läsa migrationsfil: ' . $migrationPath);
                } elseif ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::ensureTables: ' . $e->getMessage());
        }
    }

    /**
     * Hämta aktiv kostnadskonfiguration från DB.
     */
    private function loadConfig(): array {
        try {
            $stmt = $this->pdo->query(
                "SELECT faktor, varde, enhet FROM produktionskostnad_config"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $config = $this->defaultConfig;
            foreach ($rows as $row) {
                $config[$row['faktor']] = [
                    'varde' => (float)$row['varde'],
                    'enhet' => $row['enhet'],
                ];
            }
            return $config;
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::loadConfig: ' . $e->getMessage());
            return $this->defaultConfig;
        }
    }

    /**
     * Hämta produktionsdata (IBC ok, ej ok) per dag i ett datumintervall.
     * Använder MAX per skift — samma mönster som ProduktionsSlaController.
     */
    private function getProductionPerDay(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                dag,
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
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
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Hämta produktionsdata för ett intervall (aggregerat).
     */
    private function getProductionForRange(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
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
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $ok   = (int)($row['ibc_ok'] ?? 0);
        $ejOk = (int)($row['ibc_ej_ok'] ?? 0);
        return ['ibc_ok' => $ok, 'ibc_ej_ok' => $ejOk, 'total' => $ok + $ejOk];
    }

    /**
     * Hämta stopptid (minuter) för ett datumintervall från stoppage_log.
     */
    private function getStopptidMinuter(string $fromDate, string $toDate): float {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(duration_minutes), 0) AS total_stopp
                FROM stoppage_log
                WHERE line = 'rebotling'
                  AND DATE(start_time) BETWEEN :from_date AND :to_date
                  AND duration_minutes IS NOT NULL
                  AND duration_minutes > 0
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (float)($row['total_stopp'] ?? 0);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getStopptidMinuter: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Hämta stopptid per dag.
     */
    private function getStopptidPerDay(string $fromDate, string $toDate): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(start_time) AS dag,
                    COALESCE(SUM(duration_minutes), 0) AS stopp_minuter
                FROM stoppage_log
                WHERE line = 'rebotling'
                  AND DATE(start_time) BETWEEN :from_date AND :to_date
                  AND duration_minutes IS NOT NULL
                  AND duration_minutes > 0
                GROUP BY DATE(start_time)
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getStopptidPerDay: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Beräkna kostnader för ett datumintervall baserat på produktion + konfiguration.
     * Antal dagar används för att skala bemannings-/overhead-kostnad.
     */
    private function beraknaKostnader(
        int $ibcOk,
        int $ibcEjOk,
        float $stopptidMinuter,
        int $antalDagar,
        array $config
    ): array {
        // Drifttimmar = antagna arbetstimmar minus stopptid
        // Anta 16h/dag arbetstid (06-22)
        $totalArbetsTimmar = $antalDagar * 16.0;
        $stopptidTimmar    = $stopptidMinuter / 60.0;
        $driftTimmar       = max(0.0, $totalArbetsTimmar - $stopptidTimmar);

        // Operatörstimmar: anta 2 operatörer × 8h/dag om ingen skiftdata finns
        $operatorTimmar = $antalDagar * 2 * 8.0;

        $energi    = $driftTimmar       * $config['energi']['varde'];
        $bemanning = $operatorTimmar    * $config['bemanning']['varde'];
        $material  = $ibcOk             * $config['material']['varde'];
        $kassation = $ibcEjOk           * $config['kassation']['varde'];
        $overhead  = $totalArbetsTimmar * $config['overhead']['varde'];

        $total = $energi + $bemanning + $material + $kassation + $overhead;
        $kostnadPerIbc = $ibcOk > 0 ? round($total / $ibcOk, 2) : 0.0;

        return [
            'energi'         => round($energi, 2),
            'bemanning'      => round($bemanning, 2),
            'material'       => round($material, 2),
            'kassation'      => round($kassation, 2),
            'overhead'       => round($overhead, 2),
            'total'          => round($total, 2),
            'kostnad_per_ibc'=> $kostnadPerIbc,
            'ibc_ok'         => $ibcOk,
            'ibc_ej_ok'      => $ibcEjOk,
            'drift_timmar'   => round($driftTimmar, 1),
        ];
    }

    /**
     * Returnera datumgränser för ett period-filter (dag/vecka/manad).
     */
    private function getPeriodBounds(string $period, ?string $date = null): array {
        $today = $date ?: date('Y-m-d');
        switch ($period) {
            case 'vecka':
                $dt = new \DateTime($today);
                $dayOfWeek = (int)$dt->format('N');
                $from = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
                $to   = (clone $dt)->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');
                $dagar = 7;
                break;
            case 'manad':
                $dt = new \DateTime($today);
                $from = $dt->format('Y-m-01');
                $to   = $dt->format('Y-m-t');
                $dagar = (int)$dt->format('t');
                break;
            default: // dag
                $from  = $today;
                $to    = $today;
                $dagar = 1;
                break;
        }
        return ['from' => $from, 'to' => $to, 'dagar' => $dagar];
    }

    // =========================================================================
    // GET run=overview
    // KPI:er: kostnad/IBC idag, totalkostnad, trend vs förra veckan, kassationskostnad
    // =========================================================================

    private function getOverview(): void {
        try {
            $config = $this->loadConfig();
            $today  = date('Y-m-d');

            // Idag
            $dagProd   = $this->getProductionForRange($today, $today);
            $dagStopp  = $this->getStopptidMinuter($today, $today);
            $dagKost   = $this->beraknaKostnader(
                $dagProd['ibc_ok'], $dagProd['ibc_ej_ok'], $dagStopp, 1, $config
            );

            // Förra veckan (för trenden)
            $dt = new \DateTime($today);
            $dayOfWeek  = (int)$dt->format('N');
            $thisMon    = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
            $lastMon    = (new \DateTime($thisMon))->modify('-7 days')->format('Y-m-d');
            $lastSun    = (new \DateTime($thisMon))->modify('-1 day')->format('Y-m-d');
            $lastWkProd = $this->getProductionForRange($lastMon, $lastSun);
            $lastWkStopp= $this->getStopptidMinuter($lastMon, $lastSun);
            $lastWkKost = $this->beraknaKostnader(
                $lastWkProd['ibc_ok'], $lastWkProd['ibc_ej_ok'], $lastWkStopp, 7, $config
            );

            // Trend: jämför kostnad/IBC idag vs förra veckan
            $trendPct = 0.0;
            $trendRiktn = 'stabil';
            if ($lastWkKost['kostnad_per_ibc'] > 0 && $dagKost['kostnad_per_ibc'] > 0) {
                $diff = $dagKost['kostnad_per_ibc'] - $lastWkKost['kostnad_per_ibc'];
                $trendPct = round($diff / $lastWkKost['kostnad_per_ibc'] * 100, 1);
                if ($trendPct > 2) $trendRiktn = 'uppat';
                elseif ($trendPct < -2) $trendRiktn = 'nedat';
            }

            // Kassationskostnad som andel av total
            $kassAndel = $dagKost['total'] > 0
                ? round($dagKost['kassation'] / $dagKost['total'] * 100, 1)
                : 0.0;

            $this->sendSuccess([
                'data' => [
                    'kostnad_per_ibc_idag'    => $dagKost['kostnad_per_ibc'],
                    'totalkostnad_idag'        => $dagKost['total'],
                    'ibc_ok_idag'              => $dagKost['ibc_ok'],
                    'ibc_ej_ok_idag'           => $dagKost['ibc_ej_ok'],
                    'trend_pct'                => $trendPct,
                    'trend_riktning'           => $trendRiktn,
                    'kostnad_per_ibc_forr_vecka' => $lastWkKost['kostnad_per_ibc'],
                    'kassationskostnad_idag'   => $dagKost['kassation'],
                    'kassation_andel_pct'      => $kassAndel,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // =========================================================================
    // GET run=breakdown (?date, ?period=dag|vecka|manad)
    // Kostnadsuppdelning per kategori (för doughnut-diagram)
    // =========================================================================

    private function getBreakdown(): void {
        try {
            $config = $this->loadConfig();
            $period = trim($_GET['period'] ?? 'dag');
            $date   = trim($_GET['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            $bounds = $this->getPeriodBounds($period, $date);
            $prod   = $this->getProductionForRange($bounds['from'], $bounds['to']);
            $stopp  = $this->getStopptidMinuter($bounds['from'], $bounds['to']);
            $kost   = $this->beraknaKostnader(
                $prod['ibc_ok'], $prod['ibc_ej_ok'], $stopp, $bounds['dagar'], $config
            );

            $this->sendSuccess([
                'data' => [
                    'period'          => $period,
                    'from'            => $bounds['from'],
                    'to'              => $bounds['to'],
                    'energi'          => $kost['energi'],
                    'bemanning'       => $kost['bemanning'],
                    'material'        => $kost['material'],
                    'kassation'       => $kost['kassation'],
                    'overhead'        => $kost['overhead'],
                    'total'           => $kost['total'],
                    'kostnad_per_ibc' => $kost['kostnad_per_ibc'],
                    'ibc_ok'          => $kost['ibc_ok'],
                    'ibc_ej_ok'       => $kost['ibc_ej_ok'],
                    'drift_timmar'    => $kost['drift_timmar'],
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getBreakdown: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta kostnadsuppdelning', 500);
        }
    }

    // =========================================================================
    // GET run=trend (?period=30|90)
    // Kostnad per IBC per dag senaste N dagarna
    // =========================================================================

    private function getTrend(): void {
        try {
            $config  = $this->loadConfig();
            $period  = max(7, min(365, (int)($_GET['period'] ?? 30)));
            $today   = date('Y-m-d');
            $from    = (new \DateTime())->modify("-{$period} days")->format('Y-m-d');

            $perDag    = $this->getProductionPerDay($from, $today);
            $stoppPerDag = $this->getStopptidPerDay($from, $today);

            // Indexera stopptid per dag
            $stoppMap = [];
            foreach ($stoppPerDag as $s) {
                $stoppMap[$s['dag']] = (float)$s['stopp_minuter'];
            }

            $trend = [];
            $sum = 0.0;
            $count = 0;
            foreach ($perDag as $d) {
                $stopp = $stoppMap[$d['dag']] ?? 0.0;
                $kost  = $this->beraknaKostnader(
                    (int)$d['ibc_ok'], (int)$d['ibc_ej_ok'], $stopp, 1, $config
                );
                $kostPerIbc = $kost['kostnad_per_ibc'];
                if ($kostPerIbc > 0) {
                    $sum += $kostPerIbc;
                    $count++;
                }
                $trend[] = [
                    'date'            => $d['dag'],
                    'kostnad_per_ibc' => $kostPerIbc,
                    'total_kostnad'   => $kost['total'],
                    'ibc_ok'          => (int)$d['ibc_ok'],
                    'ibc_ej_ok'       => (int)$d['ibc_ej_ok'],
                ];
            }

            $snitt = $count > 0 ? round($sum / $count, 2) : 0.0;

            $this->sendSuccess([
                'data' => [
                    'period'     => $period,
                    'from'       => $from,
                    'to'         => $today,
                    'snitt'      => $snitt,
                    'trend'      => $trend,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta trend', 500);
        }
    }

    // =========================================================================
    // GET run=daily-table (?from&to)
    // Daglig kostnadstabell
    // =========================================================================

    private function getDailyTable(): void {
        try {
            $config = $this->loadConfig();
            $today  = date('Y-m-d');
            $from   = trim($_GET['from'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d'));
            $to     = trim($_GET['to']   ?? $today);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $from = (new \DateTime())->modify('-30 days')->format('Y-m-d');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $to = $today;
            }
            // Validera att from <= to, annars byt plats
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            // Begränsa till max 365 dagar
            try {
                $diffDays = (int)(new \DateTime($from))->diff(new \DateTime($to))->days;
                if ($diffDays > 365) {
                    $from = (new \DateTime($to))->modify('-1 year')->format('Y-m-d');
                }
            } catch (\Exception $e) {
                error_log('ProduktionskostnadController: datumberäkning fallback — ' . $e->getMessage());
                $from = (new \DateTime())->modify('-30 days')->format('Y-m-d');
                $to   = date('Y-m-d');
            }

            $perDag    = $this->getProductionPerDay($from, $to);
            $stoppPerDag = $this->getStopptidPerDay($from, $to);

            $stoppMap = [];
            foreach ($stoppPerDag as $s) {
                $stoppMap[$s['dag']] = (float)$s['stopp_minuter'];
            }

            $rows = [];
            foreach ($perDag as $d) {
                $stopp = $stoppMap[$d['dag']] ?? 0.0;
                $kost  = $this->beraknaKostnader(
                    (int)$d['ibc_ok'], (int)$d['ibc_ej_ok'], $stopp, 1, $config
                );
                $rows[] = [
                    'date'               => $d['dag'],
                    'ibc_ok'             => (int)$d['ibc_ok'],
                    'ibc_ej_ok'          => (int)$d['ibc_ej_ok'],
                    'ibc_total'          => (int)$d['ibc_ok'] + (int)$d['ibc_ej_ok'],
                    'total_kostnad'      => $kost['total'],
                    'kostnad_per_ibc'    => $kost['kostnad_per_ibc'],
                    'kassationskostnad'  => $kost['kassation'],
                    'stopp_minuter'      => round($stopp, 1),
                ];
            }

            // Summor
            $totalIbcOk   = array_sum(array_column($rows, 'ibc_ok'));
            $totalKostnad = array_sum(array_column($rows, 'total_kostnad'));
            $snittPerIbc  = $totalIbcOk > 0 ? round($totalKostnad / $totalIbcOk, 2) : 0.0;

            $this->sendSuccess([
                'data' => [
                    'from'             => $from,
                    'to'               => $to,
                    'rows'             => $rows,
                    'total_ibc_ok'     => $totalIbcOk,
                    'total_kostnad'    => round($totalKostnad, 2),
                    'snitt_per_ibc'    => $snittPerIbc,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getDailyTable: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta daglig tabell', 500);
        }
    }

    // =========================================================================
    // GET run=shift-comparison (?date, ?period=dag|vecka|manad)
    // Kostnad per skift (stapeldiagram)
    // =========================================================================

    private function getShiftComparison(): void {
        try {
            $config = $this->loadConfig();
            $date   = trim($_GET['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            $period = trim($_GET['period'] ?? 'dag');
            $bounds = $this->getPeriodBounds($period, $date);

            // Hämta per-skift-data
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)    AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS ibc_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
                ORDER BY dag ASC, skiftraknare ASC
            ");
            $stmt->execute([':from_date' => $bounds['from'], ':to_date' => $bounds['to']]);
            $skiftData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Beräkna kostnad per skift (anta 8h/skift)
            $skiftRows = [];
            foreach ($skiftData as $s) {
                // Stopptid per skift inte tillgänglig — fördela daglig stopptid jämnt
                $dagStopp = 0.0;
                $kost = $this->beraknaKostnader(
                    (int)$s['ibc_ok'], (int)$s['ibc_ej_ok'], $dagStopp, 1, $config
                );
                // Skala energi/bemanning/overhead till 8h (istf 16h default)
                $faktor = 0.5; // 8h / 16h
                $energi   = round($kost['energi']   * $faktor, 2);
                $bemanning= round($kost['bemanning'] * $faktor, 2);
                $overhead = round($kost['overhead']  * $faktor, 2);
                $material = $kost['material'];
                $kassation= $kost['kassation'];
                $total    = $energi + $bemanning + $material + $kassation + $overhead;
                $perIbc   = (int)$s['ibc_ok'] > 0 ? round($total / (int)$s['ibc_ok'], 2) : 0.0;

                $skiftRows[] = [
                    'dag'             => $s['dag'],
                    'skiftraknare'    => (int)$s['skiftraknare'],
                    'label'           => 'Skift ' . $s['skiftraknare'] . ' (' . $s['dag'] . ')',
                    'ibc_ok'          => (int)$s['ibc_ok'],
                    'ibc_ej_ok'       => (int)$s['ibc_ej_ok'],
                    'kostnad_per_ibc' => $perIbc,
                    'total_kostnad'   => round($total, 2),
                ];
            }

            $this->sendSuccess([
                'data' => [
                    'period' => $period,
                    'from'   => $bounds['from'],
                    'to'     => $bounds['to'],
                    'skift'  => $skiftRows,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getShiftComparison: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skiftjämförelse', 500);
        }
    }

    // =========================================================================
    // GET run=config
    // Hämta aktuell kostnadskonfiguration
    // =========================================================================

    private function getConfig(): void {
        try {
            $config = $this->loadConfig();
            $result = [];
            foreach ($config as $faktor => $val) {
                $result[] = [
                    'faktor' => $faktor,
                    'label'  => $this->faktorLabel($faktor),
                    'varde'  => $val['varde'],
                    'enhet'  => $val['enhet'],
                ];
            }
            $this->sendSuccess(['config' => $result]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::getConfig: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta konfiguration', 500);
        }
    }

    private function faktorLabel(string $faktor): string {
        $labels = [
            'energi'    => 'Energikostnad per drifttimme',
            'bemanning' => 'Bemanningskostnad per operatörstimme',
            'material'  => 'Materialkostnad per IBC',
            'kassation' => 'Kassationskostnad per kasserad IBC',
            'overhead'  => 'Overhead per timme',
        ];
        return $labels[$faktor] ?? $faktor;
    }

    // =========================================================================
    // POST run=update-config
    // Body: [{ faktor, varde }]
    // =========================================================================

    private function updateConfig(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!is_array($data) || empty($data)) {
            $this->sendError('Ogiltig data — förväntade array av {faktor, varde}');
            return;
        }

        $validFaktorer = ['energi', 'bemanning', 'material', 'kassation', 'overhead'];
        $userId = $this->currentUserId();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO produktionskostnad_config (faktor, varde, enhet, updated_by)
                 VALUES (:faktor, :varde, :enhet, :updated_by)
                 ON DUPLICATE KEY UPDATE
                   varde      = VALUES(varde),
                   updated_by = VALUES(updated_by),
                   updated_at = NOW()"
            );

            $updated = 0;
            foreach ($data as $item) {
                $faktor = trim($item['faktor'] ?? '');
                if (!in_array($faktor, $validFaktorer, true)) continue;

                $varde = max(0.0, (float)($item['varde'] ?? 0));
                $enhet = $this->defaultConfig[$faktor]['enhet'];

                $stmt->execute([
                    ':faktor'     => $faktor,
                    ':varde'      => $varde,
                    ':enhet'      => $enhet,
                    ':updated_by' => $userId,
                ]);
                $updated++;
            }

            $this->sendSuccess([
                'message' => "Konfiguration uppdaterad ({$updated} faktorer)",
                'updated' => $updated,
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionskostnadController::updateConfig: ' . $e->getMessage());
            $this->sendError('Kunde inte spara konfiguration', 500);
        }
    }
}
