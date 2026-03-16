<?php
/**
 * MaskinOeeController.php
 * OEE (Overall Equipment Effectiveness) per maskin/station i rebotling-linjen.
 * OEE = Tillganglighet x Prestanda x Kvalitet
 *
 * Endpoints via ?action=maskin-oee&run=XXX:
 *   - run=overview      -> Total OEE idag + per maskin, trend vs forra veckan
 *   - run=per-maskin    -> OEE per maskin/station med T/P/K-uppdelning
 *   - run=trend         -> OEE per dag per maskin (30/90 dagar), linjediagram
 *   - run=benchmark     -> jamfor maskiner mot varandra och mot mal-OEE
 *   - run=detalj        -> detaljerad breakdown per maskin: planerad tid, drifttid, ideal cykeltid, output, kassation
 *   - run=maskiner      -> lista maskiner (for filter)
 *
 * Parametrar:
 *   period=dag|vecka|manad (standard: manad = 30 dagar)
 *   days=N (alternativt direkt antal dagar, standard: 30)
 *   maskin_id=N (filter)
 *
 * Tabeller:
 *   maskin_oee_config   (maskin_id, planerad_tid_min, ideal_cykeltid_sek, oee_mal_pct)
 *   maskin_oee_daglig   (maskin_id, datum, planerad_tid_min, drifttid_min, stopptid_min, total_output, ok_output, kassation, T/P/K/OEE%)
 *   maskin_register     (id, namn, beskrivning, aktiv)
 *   maskin_stopptid     (maskin_id, startad_at, duration_min, ...)
 */
class MaskinOeeController {
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
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'overview':    $this->getOverview();    break;
            case 'per-maskin':  $this->getPerMaskin();   break;
            case 'trend':       $this->getTrend();       break;
            case 'benchmark':   $this->getBenchmark();   break;
            case 'detalj':      $this->getDetalj();      break;
            case 'maskiner':    $this->getMaskiner();    break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
    // ================================================================

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
     * Skapa tabeller om de saknas
     */
    private function ensureTables(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `maskin_oee_config` (
                    `id`                INT AUTO_INCREMENT PRIMARY KEY,
                    `maskin_id`         INT NOT NULL,
                    `planerad_tid_min`  DECIMAL(8,2) NOT NULL DEFAULT 480,
                    `ideal_cykeltid_sek` DECIMAL(8,2) NOT NULL DEFAULT 120,
                    `oee_mal_pct`       DECIMAL(5,2) NOT NULL DEFAULT 85.00,
                    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `updated_by`        INT DEFAULT NULL,
                    UNIQUE KEY `uk_maskin_id` (`maskin_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed config om tom
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM maskin_oee_config")->fetchColumn();
            if ($count === 0) {
                $this->pdo->exec("
                    INSERT IGNORE INTO maskin_oee_config (maskin_id, planerad_tid_min, ideal_cykeltid_sek, oee_mal_pct) VALUES
                    (1, 480, 120, 85.00),
                    (2, 480, 90, 85.00),
                    (3, 480, 60, 85.00),
                    (4, 480, 30, 85.00),
                    (5, 480, 45, 85.00),
                    (6, 480, 60, 85.00)
                ");
            }

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `maskin_oee_daglig` (
                    `id`                INT AUTO_INCREMENT PRIMARY KEY,
                    `maskin_id`         INT NOT NULL,
                    `datum`             DATE NOT NULL,
                    `planerad_tid_min`  DECIMAL(8,2) NOT NULL DEFAULT 0,
                    `drifttid_min`      DECIMAL(8,2) NOT NULL DEFAULT 0,
                    `stopptid_min`      DECIMAL(8,2) NOT NULL DEFAULT 0,
                    `total_output`      INT NOT NULL DEFAULT 0,
                    `ok_output`         INT NOT NULL DEFAULT 0,
                    `kassation`         INT NOT NULL DEFAULT 0,
                    `ideal_cykeltid_sek` DECIMAL(8,2) NOT NULL DEFAULT 120,
                    `tillganglighet_pct` DECIMAL(5,2) DEFAULT NULL,
                    `prestanda_pct`     DECIMAL(5,2) DEFAULT NULL,
                    `kvalitet_pct`      DECIMAL(5,2) DEFAULT NULL,
                    `oee_pct`           DECIMAL(5,2) DEFAULT NULL,
                    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_maskin_datum` (`maskin_id`, `datum`),
                    KEY `idx_datum` (`datum`),
                    KEY `idx_maskin_id` (`maskin_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed daglig data om tom
            $dagCount = (int)$this->pdo->query("SELECT COUNT(*) FROM maskin_oee_daglig")->fetchColumn();
            if ($dagCount === 0) {
                $this->seedDailyData();
            }
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::ensureTables: ' . $e->getMessage());
        }
    }

    private function seedDailyData(): void {
        try {
            $maskiner = $this->pdo->query("
                SELECT m.id, oc.ideal_cykeltid_sek, oc.planerad_tid_min
                FROM maskin_register m
                JOIN maskin_oee_config oc ON oc.maskin_id = m.id
                WHERE m.aktiv = 1
            ")->fetchAll();

            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO maskin_oee_daglig
                (maskin_id, datum, planerad_tid_min, drifttid_min, stopptid_min,
                 total_output, ok_output, kassation, ideal_cykeltid_sek,
                 tillganglighet_pct, prestanda_pct, kvalitet_pct, oee_pct)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($maskiner as $m) {
                $planerad = (float)$m['planerad_tid_min'];
                $idealSek = (float)$m['ideal_cykeltid_sek'];

                for ($d = 0; $d < 30; $d++) {
                    $datum = date('Y-m-d', strtotime("-{$d} days"));
                    $stopp = round(mt_rand(10, 70) + mt_rand(0, 99) / 100, 2);
                    $drift = round($planerad - $stopp, 2);
                    if ($drift < 60) $drift = 60;
                    $stopp = round($planerad - $drift, 2);

                    $totalOut = mt_rand(120, 200);
                    $kassation = mt_rand(1, 8);
                    $okOut = $totalOut - $kassation;

                    $T = round(($drift / $planerad) * 100, 2);
                    $P = round(min(($totalOut * $idealSek / 60) / $drift * 100, 100), 2);
                    $K = $totalOut > 0 ? round(($okOut / $totalOut) * 100, 2) : 0;
                    $OEE = round(($T / 100) * ($P / 100) * ($K / 100) * 100, 2);

                    $stmt->execute([
                        (int)$m['id'], $datum, $planerad, $drift, $stopp,
                        $totalOut, $okOut, $kassation, $idealSek,
                        $T, $P, $K, $OEE
                    ]);
                }
            }
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::seedDailyData: ' . $e->getMessage());
        }
    }

    // ================================================================
    // run=overview — Total OEE idag + per maskin, trend vs forra veckan
    // ================================================================

    private function getOverview(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);
        $idag = date('Y-m-d');

        // OEE idag per maskin
        $totalOeeIdag = null;
        $bastaMaskin = null;
        $samstaMaskin = null;

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    d.maskin_id,
                    mr.namn AS maskin_namn,
                    d.oee_pct,
                    d.tillganglighet_pct,
                    d.prestanda_pct,
                    d.kvalitet_pct
                FROM maskin_oee_daglig d
                JOIN maskin_register mr ON mr.id = d.maskin_id
                WHERE d.datum = :idag
                ORDER BY d.oee_pct DESC
            ");
            $stmt->execute([':idag' => $idag]);
            $idagRows = $stmt->fetchAll();

            if (count($idagRows) > 0) {
                $sumOee = 0;
                foreach ($idagRows as $r) {
                    $sumOee += (float)$r['oee_pct'];
                }
                $totalOeeIdag = round($sumOee / count($idagRows), 1);
                $bastaMaskin = [
                    'namn' => $idagRows[0]['maskin_namn'],
                    'oee'  => round((float)$idagRows[0]['oee_pct'], 1),
                ];
                $last = end($idagRows);
                $samstaMaskin = [
                    'namn' => $last['maskin_namn'],
                    'oee'  => round((float)$last['oee_pct'], 1),
                ];
            }
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getOverview idag: ' . $e->getMessage());
        }

        // Trend: jamfor med forra veckan
        $trendDiff = null;
        $trendDirection = 'flat';
        try {
            // Snitt OEE senaste 7 dagarna
            $stmt = $this->pdo->prepare("
                SELECT AVG(oee_pct) AS avg_oee
                FROM maskin_oee_daglig
                WHERE datum BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
            ");
            $stmt->execute();
            $avgNu = (float)($stmt->fetchColumn() ?: 0);

            // Snitt OEE forra veckan (8-14 dagar sedan)
            $stmt = $this->pdo->prepare("
                SELECT AVG(oee_pct) AS avg_oee
                FROM maskin_oee_daglig
                WHERE datum BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 8 DAY)
            ");
            $stmt->execute();
            $avgForr = (float)($stmt->fetchColumn() ?: 0);

            $trendDiff = round($avgNu - $avgForr, 1);
            if (abs($trendDiff) < 0.5) {
                $trendDirection = 'flat';
            } elseif ($trendDiff > 0) {
                $trendDirection = 'up';
            } else {
                $trendDirection = 'down';
            }
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getOverview trend: ' . $e->getMessage());
        }

        // OEE-mal
        $oeeMal = 85.0;
        try {
            $row = $this->pdo->query("SELECT AVG(oee_mal_pct) AS mal FROM maskin_oee_config")->fetch();
            if ($row) $oeeMal = round((float)$row['mal'], 1);
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getOverview oee_mal: ' . $e->getMessage());
        }

        $this->sendSuccess([
            'days'              => $days,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
            'total_oee_idag'    => $totalOeeIdag,
            'basta_maskin'      => $bastaMaskin,
            'samsta_maskin'     => $samstaMaskin,
            'trend_diff'        => $trendDiff,
            'trend_direction'   => $trendDirection,
            'oee_mal'           => $oeeMal,
        ]);
    }

    // ================================================================
    // run=per-maskin — OEE per maskin med T/P/K-uppdelning
    // ================================================================

    private function getPerMaskin(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    d.maskin_id,
                    mr.namn AS maskin_namn,
                    AVG(d.tillganglighet_pct) AS avg_tillganglighet,
                    AVG(d.prestanda_pct) AS avg_prestanda,
                    AVG(d.kvalitet_pct) AS avg_kvalitet,
                    AVG(d.oee_pct) AS avg_oee,
                    SUM(d.planerad_tid_min) AS total_planerad,
                    SUM(d.drifttid_min) AS total_drifttid,
                    SUM(d.stopptid_min) AS total_stopptid,
                    SUM(d.total_output) AS total_output,
                    SUM(d.ok_output) AS total_ok,
                    SUM(d.kassation) AS total_kassation,
                    oc.oee_mal_pct
                FROM maskin_oee_daglig d
                JOIN maskin_register mr ON mr.id = d.maskin_id
                LEFT JOIN maskin_oee_config oc ON oc.maskin_id = d.maskin_id
                WHERE d.datum BETWEEN :from_date AND :to_date
                GROUP BY d.maskin_id, mr.namn, oc.oee_mal_pct
                ORDER BY avg_oee DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            $maskiner = [];
            foreach ($rows as $row) {
                $maskiner[] = [
                    'maskin_id'        => (int)$row['maskin_id'],
                    'maskin_namn'      => $row['maskin_namn'],
                    'tillganglighet'   => round((float)$row['avg_tillganglighet'], 1),
                    'prestanda'        => round((float)$row['avg_prestanda'], 1),
                    'kvalitet'         => round((float)$row['avg_kvalitet'], 1),
                    'oee'              => round((float)$row['avg_oee'], 1),
                    'oee_mal'          => round((float)($row['oee_mal_pct'] ?? 85), 1),
                    'total_planerad'   => round((float)$row['total_planerad'], 1),
                    'total_drifttid'   => round((float)$row['total_drifttid'], 1),
                    'total_stopptid'   => round((float)$row['total_stopptid'], 1),
                    'total_output'     => (int)$row['total_output'],
                    'total_ok'         => (int)$row['total_ok'],
                    'total_kassation'  => (int)$row['total_kassation'],
                    'kassation_pct'    => (int)$row['total_output'] > 0
                        ? round(((int)$row['total_kassation'] / (int)$row['total_output']) * 100, 1)
                        : 0,
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'maskiner'  => $maskiner,
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getPerMaskin: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=trend — OEE per dag per maskin (linjediagram)
    // ================================================================

    private function getTrend(): void {
        $days     = $this->getDays();
        $maskinId = isset($_GET['maskin_id']) ? (int)$_GET['maskin_id'] : 0;
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $maskinParam = '';
            $params = [':from_date' => $fromDate, ':to_date' => $toDate];
            if ($maskinId > 0) {
                $maskinParam = ' AND d.maskin_id = :maskin_id';
                $params[':maskin_id'] = $maskinId;
            }

            // Hamta alla maskiner i perioden
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT d.maskin_id, mr.namn AS maskin_namn
                FROM maskin_oee_daglig d
                JOIN maskin_register mr ON mr.id = d.maskin_id
                WHERE d.datum BETWEEN :from_date AND :to_date
                  {$maskinParam}
                ORDER BY mr.namn
            ");
            $stmt->execute($params);
            $maskiner = $stmt->fetchAll();

            // Hamta daglig data
            $stmt = $this->pdo->prepare("
                SELECT
                    d.datum,
                    d.maskin_id,
                    d.oee_pct
                FROM maskin_oee_daglig d
                WHERE d.datum BETWEEN :from_date AND :to_date
                  {$maskinParam}
                ORDER BY d.datum ASC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

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
                $dataByMaskin[$id][$row['datum']] = round((float)$row['oee_pct'], 1);
            }

            // Bygg serier
            $series = [];
            foreach ($maskiner as $m) {
                $id = (int)$m['maskin_id'];
                $values = [];
                foreach ($dates as $date) {
                    $values[] = $dataByMaskin[$id][$date] ?? null;
                }
                $series[] = [
                    'maskin_id'   => $id,
                    'maskin_namn' => $m['maskin_namn'],
                    'values'      => $values,
                ];
            }

            // OEE-mal for mallinjen
            $oeeMal = 85.0;
            try {
                $row = $this->pdo->query("SELECT AVG(oee_mal_pct) AS mal FROM maskin_oee_config")->fetch();
                if ($row) $oeeMal = round((float)$row['mal'], 1);
            } catch (\PDOException $e) {
                error_log('MaskinOeeController::getTrend oee_mal: ' . $e->getMessage());
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'maskin_id'  => $maskinId,
                'dates'      => $dates,
                'series'     => $series,
                'oee_mal'    => $oeeMal,
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=benchmark — Jamfor maskiner mot varandra och mot mal-OEE
    // ================================================================

    private function getBenchmark(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    d.maskin_id,
                    mr.namn AS maskin_namn,
                    AVG(d.tillganglighet_pct) AS avg_t,
                    AVG(d.prestanda_pct) AS avg_p,
                    AVG(d.kvalitet_pct) AS avg_k,
                    AVG(d.oee_pct) AS avg_oee,
                    MIN(d.oee_pct) AS min_oee,
                    MAX(d.oee_pct) AS max_oee,
                    oc.oee_mal_pct
                FROM maskin_oee_daglig d
                JOIN maskin_register mr ON mr.id = d.maskin_id
                LEFT JOIN maskin_oee_config oc ON oc.maskin_id = d.maskin_id
                WHERE d.datum BETWEEN :from_date AND :to_date
                GROUP BY d.maskin_id, mr.namn, oc.oee_mal_pct
                ORDER BY avg_oee DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            $benchmark = [];
            foreach ($rows as $row) {
                $avgOee = round((float)$row['avg_oee'], 1);
                $mal    = round((float)($row['oee_mal_pct'] ?? 85), 1);
                $benchmark[] = [
                    'maskin_id'    => (int)$row['maskin_id'],
                    'maskin_namn'  => $row['maskin_namn'],
                    'avg_oee'      => $avgOee,
                    'min_oee'      => round((float)$row['min_oee'], 1),
                    'max_oee'      => round((float)$row['max_oee'], 1),
                    'avg_t'        => round((float)$row['avg_t'], 1),
                    'avg_p'        => round((float)$row['avg_p'], 1),
                    'avg_k'        => round((float)$row['avg_k'], 1),
                    'oee_mal'      => $mal,
                    'over_mal'     => $avgOee >= $mal,
                    'diff_vs_mal'  => round($avgOee - $mal, 1),
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'benchmark' => $benchmark,
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getBenchmark: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=detalj — Detaljerad breakdown per maskin
    // ================================================================

    private function getDetalj(): void {
        $days     = $this->getDays();
        $maskinId = isset($_GET['maskin_id']) ? (int)$_GET['maskin_id'] : 0;
        [$fromDate, $toDate] = $this->getDateRange($days);

        $maskinParam = '';
        $params = [':from_date' => $fromDate, ':to_date' => $toDate];
        if ($maskinId > 0) {
            $maskinParam = ' AND d.maskin_id = :maskin_id';
            $params[':maskin_id'] = $maskinId;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    d.maskin_id,
                    mr.namn AS maskin_namn,
                    d.datum,
                    d.planerad_tid_min,
                    d.drifttid_min,
                    d.stopptid_min,
                    d.total_output,
                    d.ok_output,
                    d.kassation,
                    d.ideal_cykeltid_sek,
                    d.tillganglighet_pct,
                    d.prestanda_pct,
                    d.kvalitet_pct,
                    d.oee_pct
                FROM maskin_oee_daglig d
                JOIN maskin_register mr ON mr.id = d.maskin_id
                WHERE d.datum BETWEEN :from_date AND :to_date
                  {$maskinParam}
                ORDER BY d.datum DESC, mr.namn ASC
                LIMIT 500
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $detaljer = [];
            foreach ($rows as $row) {
                $detaljer[] = [
                    'maskin_id'         => (int)$row['maskin_id'],
                    'maskin_namn'       => $row['maskin_namn'],
                    'datum'             => $row['datum'],
                    'planerad_tid_min'  => round((float)$row['planerad_tid_min'], 1),
                    'drifttid_min'      => round((float)$row['drifttid_min'], 1),
                    'stopptid_min'      => round((float)$row['stopptid_min'], 1),
                    'total_output'      => (int)$row['total_output'],
                    'ok_output'         => (int)$row['ok_output'],
                    'kassation'         => (int)$row['kassation'],
                    'ideal_cykeltid_sek'=> round((float)$row['ideal_cykeltid_sek'], 1),
                    'tillganglighet'    => round((float)$row['tillganglighet_pct'], 1),
                    'prestanda'         => round((float)$row['prestanda_pct'], 1),
                    'kvalitet'          => round((float)$row['kvalitet_pct'], 1),
                    'oee'               => round((float)$row['oee_pct'], 1),
                    'kassation_pct'     => (int)$row['total_output'] > 0
                        ? round(((int)$row['kassation'] / (int)$row['total_output']) * 100, 1)
                        : 0,
                ];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'maskin_id'  => $maskinId,
                'detaljer'   => $detaljer,
                'total'      => count($detaljer),
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinOeeController::getDetalj: ' . $e->getMessage());
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
            $maskiner = $stmt->fetchAll();

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
            error_log('MaskinOeeController::getMaskiner: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
