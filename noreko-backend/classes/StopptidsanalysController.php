<?php
/**
 * StopptidsanalysController.php
 * Stopptidsanalys per maskin — drill-down, flaskhalsar, jämförelse
 *
 * Endpoints via ?action=stopptidsanalys&run=XXX:
 *   - run=overview          -> KPI:er: total stopptid idag, flaskhals-maskin, antal stopp idag, snitt per stopp
 *   - run=per-maskin        -> horisontellt stapeldiagram: total stopptid per maskin, sorterat störst->minst
 *   - run=trend             -> linjediagram: stopptid per dag per maskin (senaste N dagar)
 *   - run=fordelning        -> doughnut: andel stopptid per maskin
 *   - run=detaljtabell      -> alla stopp med tidpunkt, maskin, varaktighet, orsak, operatör
 *   - run=maskiner          -> lista alla maskiner (för filter-dropdowns)
 *
 * Parametrar:
 *   period=dag|vecka|manad (standard: manad = 30 dagar)
 *   days=N (alternativt direkt antal dagar, standard: 30)
 *   maskin_id=N (filter för trend-endpoint)
 *
 * Tabeller:
 *   maskin_stopptid    (id, maskin_id, maskin_namn, startad_at, avslutad_at, duration_min, orsak, orsak_kategori, operator_namn)
 *   maskin_register    (id, namn, beskrivning, aktiv)
 */
class StopptidsanalysController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
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
            case 'overview':     $this->getOverview();    break;
            case 'per-maskin':   $this->getPerMaskin();   break;
            case 'trend':        $this->getTrend();        break;
            case 'fordelning':   $this->getFordelning();   break;
            case 'detaljtabell': $this->getDetailjtabell(); break;
            case 'maskiner':     $this->getMaskiner();     break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    /**
     * Hämta antal dagar baserat på period-parameter eller days-parameter
     */
    private function getDays(): int {
        $period = trim($_GET['period'] ?? '');
        switch ($period) {
            case 'dag':    return 1;
            case 'vecka':  return 7;
            case 'manad':  return 30;
            default:       return max(1, min(365, (int)($_GET['days'] ?? 30)));
        }
    }

    private function getDateRange(int $days): array {
        $toDate   = date('Y-m-d');
        $fromDate = $days === 1
            ? $toDate
            : date('Y-m-d', strtotime("-{$days} days"));
        return [$fromDate, $toDate];
    }

    private function sendSuccess(array $data): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Skapa tabeller om de saknas (kör migration inline)
     */
    private function ensureTables(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `maskin_register` (
                    `id`                      INT AUTO_INCREMENT PRIMARY KEY,
                    `namn`                    VARCHAR(100) NOT NULL,
                    `beskrivning`             TEXT,
                    `service_intervall_dagar` INT NOT NULL DEFAULT 90,
                    `aktiv`                   TINYINT(1) DEFAULT 1,
                    `created_at`              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed maskin_register om tom
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM maskin_register")->fetchColumn();
            if ($count === 0) {
                $this->pdo->exec("
                    INSERT INTO maskin_register (namn, beskrivning, service_intervall_dagar) VALUES
                    ('Tvättmaskin',        'Huvudtvätt för IBC-tankar',        90),
                    ('Torkugn',            'Torkstation efter tvätt',          180),
                    ('Inspektionsstation', 'Visuell kontroll och test',         60),
                    ('Transportband',      'Huvudtransportör rebotling-linje',  30),
                    ('Etiketterare',       'Automatisk märkningsmaskin',        45),
                    ('Ventiltestare',      'Testning av IBC-ventiler',          60)
                ");
            }

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `maskin_stopptid` (
                    `id`             INT NOT NULL AUTO_INCREMENT,
                    `maskin_id`      INT NOT NULL,
                    `maskin_namn`    VARCHAR(100) NOT NULL,
                    `startad_at`     DATETIME NOT NULL,
                    `avslutad_at`    DATETIME DEFAULT NULL,
                    `duration_min`   DECIMAL(8,2) DEFAULT NULL,
                    `orsak`          VARCHAR(200) DEFAULT NULL,
                    `orsak_kategori` ENUM('maskin','material','operatör','planerat','övrigt') NOT NULL DEFAULT 'övrigt',
                    `operator_id`    INT DEFAULT NULL,
                    `operator_namn`  VARCHAR(100) DEFAULT NULL,
                    `kommentar`      TEXT DEFAULT NULL,
                    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_maskin_id`  (`maskin_id`),
                    KEY `idx_startad_at` (`startad_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::ensureTables: ' . $e->getMessage());
        }
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);
        $idag = date('Y-m-d');

        // Total stopptid idag (minuter)
        $totalIdagMin   = 0.0;
        $antalStoppIdag = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(duration_min), 0) AS total_min,
                    COUNT(*) AS antal
                FROM maskin_stopptid
                WHERE DATE(startad_at) = :idag
                  AND duration_min IS NOT NULL
            ");
            $stmt->execute([':idag' => $idag]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalIdagMin   = round((float)($row['total_min'] ?? 0), 1);
            $antalStoppIdag = (int)($row['antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getOverview idag: ' . $e->getMessage());
        }

        // Genomsnittlig stopptid per stopp (idag)
        $snittPerStopp = $antalStoppIdag > 0 ? round($totalIdagMin / $antalStoppIdag, 1) : 0.0;

        // Flaskhals-maskin (mest stopptid inom vald period)
        $flaskhalsMaskin  = null;
        $flaskhalsMaskinMin = 0.0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT maskin_namn,
                       COALESCE(SUM(duration_min), 0) AS total_min,
                       COUNT(*) AS antal_stopp
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
                GROUP BY maskin_id, maskin_namn
                ORDER BY total_min DESC
                LIMIT 1
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $flaskhalsMaskin    = $row['maskin_namn'];
                $flaskhalsMaskinMin = round((float)$row['total_min'], 1);
            }
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getOverview flaskhals: ' . $e->getMessage());
        }

        // Jämför med föregående period
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
        $prevTo   = date('Y-m-d', strtotime("-" . ($days + 1) . " days"));
        $prevTotalMin = 0.0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(duration_min), 0) AS total_min
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
            ");
            $stmt->execute([':from_date' => $prevFrom, ':to_date' => $prevTo]);
            $prevTotalMin = round((float)$stmt->fetchColumn(), 1);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getOverview prev: ' . $e->getMessage());
        }

        // Total stopptid för perioden
        $periodTotalMin = 0.0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(duration_min), 0) AS total_min
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $periodTotalMin = round((float)$stmt->fetchColumn(), 1);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getOverview period: ' . $e->getMessage());
        }

        $diff = $periodTotalMin - $prevTotalMin;
        $trendDirection = abs($diff) < 0.5 ? 'flat' : ($diff > 0 ? 'up' : 'down');

        $this->sendSuccess([
            'days'                 => $days,
            'from_date'            => $fromDate,
            'to_date'              => $toDate,
            'total_idag_min'       => $totalIdagMin,
            'antal_stopp_idag'     => $antalStoppIdag,
            'snitt_per_stopp_min'  => $snittPerStopp,
            'flaskhals_maskin'     => $flaskhalsMaskin,
            'flaskhals_maskin_min' => $flaskhalsMaskinMin,
            'period_total_min'     => $periodTotalMin,
            'prev_total_min'       => $prevTotalMin,
            'trend_diff_min'       => round($diff, 1),
            'trend_direction'      => $trendDirection,
        ]);
    }

    // ================================================================
    // run=per-maskin — Stopptid per maskin (för horisontellt stapeldiagram)
    // ================================================================

    private function getPerMaskin(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    ms.maskin_id,
                    ms.maskin_namn,
                    COALESCE(SUM(ms.duration_min), 0)  AS total_min,
                    COUNT(*)                            AS antal_stopp,
                    COALESCE(AVG(ms.duration_min), 0)  AS snitt_min,
                    MAX(ms.duration_min)                AS max_stopp_min,
                    MAX(ms.startad_at)                  AS senaste_stopp
                FROM maskin_stopptid ms
                WHERE DATE(ms.startad_at) BETWEEN :from_date AND :to_date
                  AND ms.duration_min IS NOT NULL
                GROUP BY ms.maskin_id, ms.maskin_namn
                ORDER BY total_min DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $maskiner = [];
            $totalMin = 0.0;
            foreach ($rows as $row) {
                $min = round((float)$row['total_min'], 1);
                $totalMin += $min;
                $maskiner[] = [
                    'maskin_id'    => (int)$row['maskin_id'],
                    'maskin_namn'  => $row['maskin_namn'],
                    'total_min'    => $min,
                    'antal_stopp'  => (int)$row['antal_stopp'],
                    'snitt_min'    => round((float)$row['snitt_min'], 1),
                    'max_stopp_min' => round((float)$row['max_stopp_min'], 1),
                    'senaste_stopp' => $row['senaste_stopp'],
                ];
            }

            // Beräkna andel %
            foreach ($maskiner as &$m) {
                $m['andel_pct'] = $totalMin > 0
                    ? round(($m['total_min'] / $totalMin) * 100, 1)
                    : 0.0;
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'total_min' => round($totalMin, 1),
                'maskiner'  => $maskiner,
            ]);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getPerMaskin: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=trend — Stopptid per dag per maskin (linjediagram)
    // ================================================================

    private function getTrend(): void {
        $days     = $this->getDays();
        $maskinId = isset($_GET['maskin_id']) ? (int)$_GET['maskin_id'] : 0;
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            // Hämta alla maskiner i perioden
            $maskinParam = '';
            $params = [':from_date' => $fromDate, ':to_date' => $toDate];
            if ($maskinId > 0) {
                $maskinParam = ' AND maskin_id = :maskin_id';
                $params[':maskin_id'] = $maskinId;
            }

            $stmt = $this->pdo->prepare("
                SELECT DISTINCT maskin_id, maskin_namn
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
                  {$maskinParam}
                ORDER BY maskin_namn
            ");
            $stmt->execute($params);
            $maskiner = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta daglig data
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(startad_at)             AS dag,
                    maskin_id,
                    maskin_namn,
                    COALESCE(SUM(duration_min), 0) AS total_min,
                    COUNT(*)                       AS antal_stopp
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
                  {$maskinParam}
                GROUP BY DATE(startad_at), maskin_id, maskin_namn
                ORDER BY dag ASC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg datumsekvens
            $dates = [];
            $d   = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($d <= $end) {
                $dates[] = $d->format('Y-m-d');
                $d->modify('+1 day');
            }

            // Organisera per maskin
            $dataByMaskin = [];
            foreach ($rows as $row) {
                $id = (int)$row['maskin_id'];
                if (!isset($dataByMaskin[$id])) {
                    $dataByMaskin[$id] = [];
                }
                $dataByMaskin[$id][$row['dag']] = round((float)$row['total_min'], 1);
            }

            // Bygg serier
            $series = [];
            foreach ($maskiner as $m) {
                $id = (int)$m['maskin_id'];
                $values = [];
                foreach ($dates as $date) {
                    $values[] = $dataByMaskin[$id][$date] ?? 0;
                }
                $series[] = [
                    'maskin_id'   => $id,
                    'maskin_namn' => $m['maskin_namn'],
                    'values'      => $values,
                ];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'maskin_id'  => $maskinId,
                'dates'      => $dates,
                'series'     => $series,
            ]);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=fordelning — Doughnut: andel stopptid per maskin
    // ================================================================

    private function getFordelning(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    maskin_id,
                    maskin_namn,
                    COALESCE(SUM(duration_min), 0) AS total_min,
                    COUNT(*) AS antal_stopp
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
                GROUP BY maskin_id, maskin_namn
                ORDER BY total_min DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalMin = array_sum(array_column($rows, 'total_min'));
            $fordelning = [];
            foreach ($rows as $row) {
                $min = round((float)$row['total_min'], 1);
                $fordelning[] = [
                    'maskin_id'   => (int)$row['maskin_id'],
                    'maskin_namn' => $row['maskin_namn'],
                    'total_min'   => $min,
                    'antal_stopp' => (int)$row['antal_stopp'],
                    'andel_pct'   => $totalMin > 0 ? round(($min / $totalMin) * 100, 1) : 0.0,
                ];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'total_min'  => round((float)$totalMin, 1),
                'fordelning' => $fordelning,
            ]);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getFordelning: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=detaljtabell — Alla stopp med detaljer
    // ================================================================

    private function getDetailjtabell(): void {
        $days     = $this->getDays();
        $maskinId = isset($_GET['maskin_id']) ? (int)$_GET['maskin_id'] : 0;
        [$fromDate, $toDate] = $this->getDateRange($days);

        $maskinParam = '';
        $params = [':from_date' => $fromDate, ':to_date' => $toDate];
        if ($maskinId > 0) {
            $maskinParam = ' AND maskin_id = :maskin_id';
            $params[':maskin_id'] = $maskinId;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    maskin_id,
                    maskin_namn,
                    startad_at,
                    avslutad_at,
                    duration_min,
                    orsak,
                    orsak_kategori,
                    operator_namn,
                    kommentar,
                    created_at
                FROM maskin_stopptid
                WHERE DATE(startad_at) BETWEEN :from_date AND :to_date
                  AND duration_min IS NOT NULL
                  {$maskinParam}
                ORDER BY startad_at DESC
                LIMIT 500
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stopp = [];
            foreach ($rows as $row) {
                $stopp[] = [
                    'id'             => (int)$row['id'],
                    'maskin_id'      => (int)$row['maskin_id'],
                    'maskin_namn'    => $row['maskin_namn'],
                    'startad_at'     => $row['startad_at'],
                    'avslutad_at'    => $row['avslutad_at'],
                    'duration_min'   => round((float)$row['duration_min'], 1),
                    'orsak'          => $row['orsak'] ?? 'Okänd',
                    'orsak_kategori' => $row['orsak_kategori'],
                    'operator_namn'  => $row['operator_namn'] ?? '-',
                    'kommentar'      => $row['kommentar'],
                ];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'maskin_id'  => $maskinId,
                'stopp'      => $stopp,
                'total'      => count($stopp),
            ]);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getDetailjtabell: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=maskiner — Lista alla maskiner
    // ================================================================

    private function getMaskiner(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, namn, beskrivning, aktiv
                FROM maskin_register
                WHERE aktiv = 1
                ORDER BY namn ASC
            ");
            $maskiner = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($maskiner as $m) {
                $result[] = [
                    'id'          => (int)$m['id'],
                    'namn'        => $m['namn'],
                    'beskrivning' => $m['beskrivning'],
                ];
            }

            $this->sendSuccess(['maskiner' => $result]);
        } catch (\PDOException $e) {
            error_log('StopptidsanalysController::getMaskiner: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
