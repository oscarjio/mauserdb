<?php
class RebotlingAnalyticsController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Hämta start/stopptid för ett skift.
     * 1) Tar skifträknare från rapporten
     * 2) Fallback nedåt (n-1, n-2) + dagen innan om inga cykler finns
     * 3) Hittar första/sista cykel i rebotling_ibc
     * 4) Använder rebotling_onoff: senaste running=1 FÖRE första cykel = starttid,
     *    första running=0 EFTER sista cykel = stopptid
     */
    private function resolveSkiftTider(array $skiftraknareList, string $date): array {
        if (count($skiftraknareList) === 0) {
            return ['start' => null, 'slut' => null, 'cykel_datum' => null];
        }

        // Steg 1: Hitta rätt skifträknare i rebotling_ibc (original + fallback nedåt)
        // Sök UTAN datumfilter först (skifträknare är tillräckligt unikt),
        // med datumfilter som sekundärt fallback (hanterar dag-efter-scenariot)
        $foundSkiftraknare = null;

        foreach (array_keys($skiftraknareList) as $sk) {
            // Testa original, sedan n-1, n-2 — utan datumfilter
            foreach ([(int)$sk, (int)$sk - 1, (int)$sk - 2] as $testId) {
                $chk = $this->pdo->prepare(
                    "SELECT COUNT(*) as cnt FROM rebotling_ibc
                     WHERE skiftraknare = ?
                     AND lopnummer > 0 AND lopnummer < 998"
                );
                $chk->execute([$testId]);
                $cnt = (int)$chk->fetchColumn();
                if ($cnt > 0) {
                    $foundSkiftraknare = $testId;
                    break 2;
                }
            }
        }

        if ($foundSkiftraknare === null) {
            return ['start' => null, 'slut' => null, 'cykel_datum' => null];
        }

        // Steg 2: Hämta cykeldatum från rebotling_ibc med den hittade skifträknaren
        $ibcStmt = $this->pdo->prepare("
            SELECT GROUP_CONCAT(DISTINCT DATE(datum) ORDER BY DATE(datum) ASC) AS cykel_datum
            FROM rebotling_ibc
            WHERE skiftraknare = ?
            AND lopnummer > 0 AND lopnummer < 998
        ");
        $ibcStmt->execute([$foundSkiftraknare]);
        $ibcRow = $ibcStmt->fetch(PDO::FETCH_ASSOC);
        $cykelDatum = $ibcRow['cykel_datum'] ?? null;

        // Steg 3: Använd SAMMA skifträknare i rebotling_onoff för start/stopp
        // Första running=1 = maskinstart, sista running=0 = maskinstopp
        $skiftStart = null;
        $skiftSlut  = null;
        try {
            $onStmt = $this->pdo->prepare(
                "SELECT
                    (SELECT MIN(datum) FROM rebotling_onoff WHERE skiftraknare = ? AND running = 1) AS first_start,
                    (SELECT MAX(datum) FROM rebotling_onoff WHERE skiftraknare = ? AND running = 0) AS last_stop"
            );
            $onStmt->execute([$foundSkiftraknare, $foundSkiftraknare]);
            $onRow = $onStmt->fetch(PDO::FETCH_ASSOC);
            if ($onRow) {
                $skiftStart = $onRow['first_start'] ?? null;
                $skiftSlut  = $onRow['last_stop'] ?? null;
            }
            // Om inget running=0 finns (maskin lämnades på), fallback till MAX(datum)
            if (!$skiftSlut && $skiftStart) {
                $maxStmt = $this->pdo->prepare(
                    "SELECT MAX(datum) AS last FROM rebotling_onoff WHERE skiftraknare = ?"
                );
                $maxStmt->execute([$foundSkiftraknare]);
                $skiftSlut = $maxStmt->fetchColumn() ?: null;
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::resolveSkiftTider onoff: ' . $e->getMessage());
        }

        // Fallback: om onoff saknar data, använd cykeltider
        if (!$skiftStart || !$skiftSlut) {
            $fallStmt = $this->pdo->prepare(
                "SELECT MIN(datum) AS first_cycle, MAX(datum) AS last_cycle
                 FROM rebotling_ibc WHERE skiftraknare = ?
                 AND lopnummer > 0 AND lopnummer < 998"
            );
            $fallStmt->execute([$foundSkiftraknare]);
            $fallRow = $fallStmt->fetch(PDO::FETCH_ASSOC);
            if (!$skiftStart) $skiftStart = $fallRow['first_cycle'] ?? null;
            if (!$skiftSlut)  $skiftSlut  = $fallRow['last_cycle'] ?? null;
        }

        // Fallback: estimera från runtime_plc om fortfarande saknas
        if ((!$skiftStart || !$skiftSlut) && $date) {
            try {
                $rtStmt = $this->pdo->prepare(
                    "SELECT MAX(COALESCE(runtime_plc, 0)) AS runtime_min
                     FROM rebotling_ibc WHERE skiftraknare = ?"
                );
                $rtStmt->execute([$foundSkiftraknare]);
                $runtimeMin = (int)($rtStmt->fetchColumn() ?? 0);
                if ($runtimeMin > 0) {
                    $baseDate = substr($date, 0, 10);
                    if (!$skiftStart) {
                        $skiftStart = $baseDate . ' 06:00:00';
                    }
                    if (!$skiftSlut) {
                        $skiftSlut = date('Y-m-d H:i:s', strtotime($skiftStart) + ($runtimeMin * 60));
                    }
                }
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::resolveSkiftTider runtime fallback: ' . $e->getMessage());
            }
        }

        return ['start' => $skiftStart, 'slut' => $skiftSlut, 'cykel_datum' => $cykelDatum];
    }

    private function ensureSettingsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_settings` (
                `id`               INT          NOT NULL DEFAULT 1,
                `rebotling_target` INT          NOT NULL DEFAULT 1000,
                `hourly_target`    INT          NOT NULL DEFAULT 50,
                `auto_start`       TINYINT(1)   NOT NULL DEFAULT 0,
                `maintenance_mode` TINYINT(1)   NOT NULL DEFAULT 0,
                `alert_threshold`  INT          NOT NULL DEFAULT 80,
                `shift_hours`      DECIMAL(4,1) NOT NULL DEFAULT 8.0,
                `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec(
            "INSERT IGNORE INTO `rebotling_settings` (id) VALUES (1)"
        );
    }


    private function ensureShiftTimesTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_shift_times` (
                `id`         INT         NOT NULL AUTO_INCREMENT,
                `shift_name` VARCHAR(50) NOT NULL,
                `start_time` TIME        NOT NULL DEFAULT '06:00:00',
                `end_time`   TIME        NOT NULL DEFAULT '14:00:00',
                `enabled`    TINYINT(1)  NOT NULL DEFAULT 1,
                `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_shift_name` (`shift_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['förmiddag',   '06:00:00', '14:00:00', 1],
            ['eftermiddag', '14:00:00', '22:00:00', 1],
            ['natt',        '22:00:00', '06:00:00', 0],
        ];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO rebotling_shift_times (shift_name, start_time, end_time, enabled) VALUES (?, ?, ?, ?)");
        foreach ($defaults as [$name, $start, $end, $enabled]) {
            $stmt->execute([$name, $start, $end, $enabled]);
        }
    }


    private function ensureNotificationEmailsColumn(): void {
        try {
            $this->ensureSettingsTable();
            $col = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'notification_emails'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN notification_emails TEXT NULL DEFAULT NULL"
                );
            }

            // notification_config — JSON med enabled-toggle och händelsetyp-toggles
            $col2 = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'notification_config'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col2) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN notification_config TEXT NULL DEFAULT NULL"
                );
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::ensureNotificationEmailsColumn: ' . $e->getMessage());
        }
    }


    public function getProductionReport() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin-behörighet krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $period = $_GET['period'] ?? 'week';
        $format = $_GET['format'] ?? 'json';

        $days = match($period) {
            'today' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => 7
        };

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Dagliga produktionssiffror.
            // ibc_ok, ibc_ej_ok, bur_ej_ok och runtime_plc är KUMULATIVA per skift.
            // Aggregera korrekt: MAX() per skiftraknare (inner), sedan SUM() per dag (outer).
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(cykler)          AS cykler,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_bur_ej_ok) AS bur_ej_ok,
                    ROUND(AVG(avg_prod_pct), 1)  AS snitt_produktion_pct,
                    ROUND(AVG(avg_runtime_plc), 1) AS snitt_cykeltid,
                    SUM(shift_runtime)   AS kortid_minuter
                FROM (
                    SELECT
                        DATE(datum)          AS dag,
                        skiftraknare,
                        COUNT(*)             AS cykler,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok,  0)) AS shift_bur_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        AVG(produktion_procent)  AS avg_prod_pct,
                        AVG(runtime_plc)         AS avg_runtime_plc
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= ?
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag
            ");
            $stmt->execute([$startDate]);
            $daily = $stmt->fetchAll();

            // Starter/stopp-data från on/off-events
            $stmtRuntime = $this->pdo->prepare("
                SELECT
                    DATE(datum) as dag,
                    COUNT(CASE WHEN running = 1 THEN 1 END) as starter,
                    COUNT(CASE WHEN running = 0 THEN 1 END) as stopp
                FROM rebotling_onoff
                WHERE DATE(datum) >= ?
                GROUP BY DATE(datum)
                ORDER BY dag
            ");
            $stmtRuntime->execute([$startDate]);
            $runtime = $stmtRuntime->fetchAll();
            $runtimeMap = [];
            foreach ($runtime as $r) {
                $runtimeMap[$r['dag']] = $r;
            }

            // Sammanfattning
            $totalIbcOk = array_sum(array_column($daily, 'ibc_ok'));
            $totalIbcEjOk = array_sum(array_column($daily, 'ibc_ej_ok'));
            $totalCykler = array_sum(array_column($daily, 'cykler'));
            $daysWithProduction = count($daily);

            $report = [];
            foreach ($daily as $d) {
                $rt = $runtimeMap[$d['dag']] ?? null;
                $report[] = [
                    'datum' => $d['dag'],
                    'cykler' => (int)$d['cykler'],
                    'ibc_ok' => (int)$d['ibc_ok'],
                    'ibc_ej_ok' => (int)$d['ibc_ej_ok'],
                    'bur_ej_ok' => (int)$d['bur_ej_ok'],
                    'kvalitet_pct' => $d['ibc_ok'] > 0 ? round(($d['ibc_ok'] / ($d['ibc_ok'] + $d['ibc_ej_ok'])) * 100, 1) : 0,
                    'snitt_cykeltid' => (float)$d['snitt_cykeltid'],
                    'snitt_produktion_pct' => (float)$d['snitt_produktion_pct'],
                    'kortid_h' => round((float)$d['kortid_minuter'] / 60, 1),
                    'starter' => $rt ? (int)$rt['starter'] : 0,
                    'stopp' => $rt ? (int)$rt['stopp'] : 0,
                ];
            }

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="produktionsrapport_rebotling_' . $startDate . '.csv"');
                // BOM for Excel
                echo "\xEF\xBB\xBF";
                echo "Datum;Cykler;IBC OK;IBC Ej OK;Bur Ej OK;Kvalitet %;Snitt cykeltid (min);Produktion %;Körtid (h);Starter;Stopp\n";
                foreach ($report as $r) {
                    echo implode(';', [
                        $r['datum'], $r['cykler'], $r['ibc_ok'], $r['ibc_ej_ok'],
                        $r['bur_ej_ok'], $r['kvalitet_pct'], $r['snitt_cykeltid'],
                        $r['snitt_produktion_pct'], $r['kortid_h'], $r['starter'], $r['stopp']
                    ]) . "\n";
                }
                echo "\n;Totalt;{$totalIbcOk};{$totalIbcEjOk};;;;;;;\n";
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'start_date' => $startDate,
                    'daily' => $report,
                    'summary' => [
                        'total_cykler' => $totalCykler,
                        'total_ibc_ok' => $totalIbcOk,
                        'total_ibc_ej_ok' => $totalIbcEjOk,
                        'dagar_med_produktion' => $daysWithProduction,
                        'snitt_cykler_per_dag' => $daysWithProduction > 0 ? round($totalCykler / $daysWithProduction, 1) : 0,
                        'kvalitet_pct' => ($totalIbcOk + $totalIbcEjOk) > 0 ? round(($totalIbcOk / ($totalIbcOk + $totalIbcEjOk)) * 100, 1) : 0,
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getProductionReport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte generera rapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=week-comparison
     * Returnerar IBC/dag för denna vecka (mån–idag) + förra veckan (14 dagar).
     * Används av Veckojämförelse-panelen i statistiksidan.
     */

    public function getWeekComparison() {
        try {
            $granularity = $_GET['granularity'] ?? 'day';
            if (!in_array($granularity, ['day', 'shift'], true)) {
                $granularity = 'day';
            }

            if ($granularity === 'shift') {
                // Per-skift: hämta senaste 14 dagarna, varje skift = en datapunkt
                $weekdays = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        MIN(datum)            AS skift_start,
                        COUNT(*)              AS cykler,
                        MAX(COALESCE(ibc_ok,  0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt->execute();
                $shiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $allShifts = [];
                foreach ($shiftRows as $r) {
                    $ts = strtotime($r['dag']);
                    $wd = (int)date('w', $ts); // 0=Sun, 1=Mon ... 6=Sat
                    $label = $weekdays[$wd] . ' Skift ' . (int)$r['skiftraknare'];
                    $allShifts[] = [
                        'date'         => $r['dag'],
                        'label'        => $label,
                        'skiftraknare' => (int)$r['skiftraknare'],
                        'ibc_ok'       => (int)$r['shift_ibc_ok'],
                        'cykler'       => (int)$r['cykler']
                    ];
                }

                // Dela på mittpunkt 7 dagar
                $cutoff = date('Y-m-d', strtotime('-7 days'));
                $thisWeekShifts = array_values(array_filter($allShifts, fn($s) => $s['date'] >= $cutoff));
                $prevWeekShifts = array_values(array_filter($allShifts, fn($s) => $s['date'] < $cutoff));

                echo json_encode([
                    'success' => true,
                    'granularity' => 'shift',
                    'data' => [
                        'this_week' => $thisWeekShifts,
                        'prev_week' => $prevWeekShifts,
                        'all_days'  => $allShifts
                    ]
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Hämta daglig IBC-räkning för de senaste 14 dagarna
            // ibc_ok är kumulativt per skift → MAX per skiftraknare per dag, sedan SUM per dag
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok) AS ibc_ok,
                    SUM(cykler)       AS cykler
                FROM (
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        COUNT(*)              AS cykler,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg karta dag -> data
            $map = [];
            foreach ($rows as $r) {
                $map[$r['dag']] = ['ibc_ok' => (int)$r['ibc_ok'], 'cykler' => (int)$r['cykler']];
            }

            // Generera fullständig 14-dagars lista (idag + 13 dagar bakåt)
            $days = [];
            for ($i = 13; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $days[] = [
                    'date'   => $date,
                    'ibc_ok' => $map[$date]['ibc_ok'] ?? 0,
                    'cykler' => $map[$date]['cykler'] ?? 0
                ];
            }

            // Dela upp i denna vecka (dag 7-13, index 7-13) och förra (dag 0-6, index 0-6)
            $prevWeek = array_slice($days, 0, 7);
            $thisWeek = array_slice($days, 7, 7);

            echo json_encode([
                'success'   => true,
                'data'      => [
                    'this_week' => $thisWeek,
                    'prev_week' => $prevWeek,
                    'all_days'  => $days
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getWeekComparison: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckojämförelsedata'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=oee-trend&days=30[&granularity=shift]
     * OEE-trend senaste N dagarna (Availability, Performance, Quality, OEE per dag eller per skift).
     */

    public function getOEETrend() {
        $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;
        $toDate   = isset($_GET['to_date'])   ? trim($_GET['to_date'])   : null;
        if ($fromDate && $toDate) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                return;
            }
            // Validera att from <= to
            if ($fromDate > $toDate) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
            // Begränsa till max 365 dagar för att förhindra timeout/memory exhaustion
            try {
                $startDt = new DateTime($fromDate, new DateTimeZone('Europe/Stockholm'));
                $endDt   = new DateTime($toDate, new DateTimeZone('Europe/Stockholm'));
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::getPeriodicData — ogiltigt datumvärde: ' . $e->getMessage());
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumvärde'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $diffDays = (int)$startDt->diff($endDt)->days;
            if ($diffDays > 365) {
                $fromDate = (clone $endDt)->modify('-365 days')->format('Y-m-d');
            }
            $oeeStart = $fromDate;
            $oeeEnd   = $toDate;
        } else {
            $days = min(365, max(7, intval($_GET['days'] ?? 30)));
            $oeeStart = date('Y-m-d', strtotime("-{$days} days"));
            $oeeEnd   = date('Y-m-d');
        }
        $granularity = $_GET['granularity'] ?? 'day';
        if (!in_array($granularity, ['day', 'shift'], true)) {
            $granularity = 'day';
        }
        try {
            if ($granularity === 'shift') {
                // Per-skift: varje skift = en datapunkt med eget OEE-värde
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        MIN(datum)            AS skift_start,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= :oee_start AND datum <= :oee_end
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt->execute(['oee_start' => $oeeStart . ' 00:00:00', 'oee_end' => $oeeEnd . ' 23:59:59']);
                $shiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $idealRatePerMin = 15.0 / 60.0;
                $shiftData = [];
                foreach ($shiftRows as $r) {
                    $dagParts = explode('-', $r['dag']);
                    $label = sprintf('%s/%s Skift %d',
                        $dagParts[2], $dagParts[1], (int)$r['skiftraknare']
                    );

                    $ibcOk   = (float)$r['shift_ibc_ok'];
                    $ibcEjOk = (float)$r['shift_ibc_ej_ok'];
                    $totalIBC = $ibcOk + $ibcEjOk;
                    $opMin   = max((float)$r['shift_runtime'], 1);
                    $planMin = max($opMin + (float)$r['shift_rast'], 1);

                    $avail = min($opMin / $planMin, 1.0);
                    $perf  = min(($totalIBC / $opMin) / $idealRatePerMin, 1.0);
                    $qual  = $totalIBC > 0 ? $ibcOk / $totalIBC : 0;
                    $oee   = $avail * $perf * $qual;

                    $shiftData[] = [
                        'date'         => $r['dag'],
                        'label'        => $label,
                        'skiftraknare' => (int)$r['skiftraknare'],
                        'oee'          => round($oee   * 100, 1),
                        'availability' => round($avail  * 100, 1),
                        'performance'  => round($perf   * 100, 1),
                        'quality'      => round($qual   * 100, 1),
                        'ibc_ok'       => (int)$ibcOk
                    ];
                }

                echo json_encode(['success' => true, 'granularity' => 'shift', 'data' => $shiftData], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= :oee_start AND datum <= :oee_end
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute(['oee_start' => $oeeStart . ' 00:00:00', 'oee_end' => $oeeEnd . ' 23:59:59']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $idealRatePerMin = 15.0 / 60.0;
            $daily = [];
            foreach ($rows as $r) {
                $ibcOk    = (float)$r['ibc_ok'];
                $ibcEjOk  = (float)$r['ibc_ej_ok'];
                $totalIBC = $ibcOk + $ibcEjOk;
                $opMin    = max((float)$r['runtime_min'], 1);
                $planMin  = max($opMin + (float)$r['rast_min'], 1);

                $avail = min($opMin / $planMin, 1.0);
                $perf  = min(($totalIBC / $opMin) / $idealRatePerMin, 1.0);
                $qual  = $totalIBC > 0 ? $ibcOk / $totalIBC : 0;
                $oee   = $avail * $perf * $qual;

                $daily[] = [
                    'date'         => $r['dag'],
                    'oee'          => round($oee   * 100, 1),
                    'availability' => round($avail  * 100, 1),
                    'performance'  => round($perf   * 100, 1),
                    'quality'      => round($qual   * 100, 1),
                    'ibc_ok'       => (int)$ibcOk
                ];
            }

            echo json_encode(['success' => true, 'data' => $daily], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getOEETrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta OEE-trend'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=best-shifts&limit=10
     * De historiskt bästa skiften sorterade på ibc_ok DESC.
     */

    public function getBestShifts() {
        $limit = min(50, max(5, intval($_GET['limit'] ?? 10)));
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    DATE(MIN(datum)) AS dag,
                    COUNT(*)         AS cykler,
                    MAX(COALESCE(ibc_ok,   0)) AS ibc_ok,
                    MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                    MAX(COALESCE(runtime_plc,0)) AS runtime_min,
                    ROUND(AVG(NULLIF(produktion_procent,0)), 1) AS avg_kvalitet
                FROM rebotling_ibc
                WHERE skiftraknare IS NOT NULL
                  AND ibc_ok IS NOT NULL
                  AND datum >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                GROUP BY skiftraknare
                HAVING ibc_ok > 0
                ORDER BY ibc_ok DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $i => $r) {
                $ibcOk   = (int)$r['ibc_ok'];
                $ibcEjOk = (int)$r['ibc_ej_ok'];
                $total   = $ibcOk + $ibcEjOk;
                $result[] = [
                    'rank'        => $i + 1,
                    'skiftraknare' => (int)$r['skiftraknare'],
                    'dag'         => $r['dag'],
                    'cykler'      => (int)$r['cykler'],
                    'ibc_ok'      => $ibcOk,
                    'ibc_ej_ok'   => $ibcEjOk,
                    'kvalitet_pct'=> $total > 0 ? round($ibcOk / $total * 100, 1) : 0,
                    'runtime_h'   => round((float)$r['runtime_min'] / 60, 1),
                    'avg_kvalitet' => (float)$r['avg_kvalitet']
                ];
            }

            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getBestShifts: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta bästa skift'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=heatmap&days=30
     * Returnerar produktionsintensitet per timme och dag som
     * { date: "YYYY-MM-DD", hour: 0-23, count: N }[]
     * Används av statistiksidans heatmap-vy.
     */

    public function getExecDashboard() {
        try {
            $tz  = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // ---- Dagsmål (från rebotling_settings) ----
            $dailyTarget = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dailyTarget = (int)$sr['rebotling_target'];
            } catch (Exception) { /* ignorera */ }

            // Kolla om undantag finns för idag (getExecDashboard)
            try {
                $stmtEx = $this->pdo->prepare('SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE()');
                $stmtEx->execute();
                $exceptionRow = $stmtEx->fetch(PDO::FETCH_ASSOC);
                if ($exceptionRow) {
                    $dailyTarget = (int)$exceptionRow['justerat_mal'];
                }
            } catch (Exception) { /* tabell saknas ännu — ignorera */ }

            // ---- IBC idag ----
            $ibcToday = (int)$this->pdo->query("SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()")->fetchColumn();

            // ---- Skiftstart (används för prognos). Standard 06:00 ----
            $shiftStart = '06:00:00';
            try {
                $this->ensureShiftTimesTable();
                $st = $this->pdo->query("SELECT start_time FROM rebotling_shift_times WHERE shift_name='förmiddag' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($st) $shiftStart = $st['start_time'];
            } catch (Exception) { /* ignorera */ }

            $shiftStartDt = new DateTime($now->format('Y-m-d') . ' ' . $shiftStart, $tz);
            if ($shiftStartDt > $now) {
                // Kan hända om skiftet inte startat — räkna ändå från 06:00
                $shiftStartDt->modify('-1 day');
            }
            $minutesSinceShiftStart = max(1, ($now->getTimestamp() - $shiftStartDt->getTimestamp()) / 60);

            // Prognos: om vi producerat X IBC på Y minuter, hur många till skiftets slut (480 min)?
            $shiftLengthMin = 480;
            try {
                $st2 = $this->pdo->query("SELECT shift_hours FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($st2) $shiftLengthMin = (float)$st2['shift_hours'] * 60;
            } catch (Exception) { /* ignorera */ }

            $rate = $minutesSinceShiftStart > 0 ? $ibcToday / $minutesSinceShiftStart : 0;
            $remainingMin = max(0, $shiftLengthMin - $minutesSinceShiftStart);
            $forecast = (int)round($ibcToday + $rate * $remainingMin);

            // ---- OEE idag ----
            $oeeToday = 0;
            try {
                $oRow = $this->pdo->query("
                    SELECT
                        SUM(shift_ibc_ok)    AS ibc_ok,
                        SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                        SUM(shift_runtime)   AS runtime_min,
                        SUM(shift_rast)      AS rast_min
                    FROM (
                        SELECT
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                            MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                            MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                            MAX(COALESCE(rasttime,   0)) AS shift_rast
                        FROM rebotling_ibc
                        WHERE DATE(datum) = CURDATE()
                          AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY skiftraknare
                    ) AS ps
                ")->fetch(PDO::FETCH_ASSOC);
                if ($oRow && $oRow['runtime_min'] > 0) {
                    $ibcOk   = (float)$oRow['ibc_ok'];
                    $ibcEjOk = (float)$oRow['ibc_ej_ok'];
                    $total   = $ibcOk + $ibcEjOk;
                    $opMin   = max((float)$oRow['runtime_min'], 1);
                    $planMin = max($opMin + (float)$oRow['rast_min'], 1);
                    $avail   = min($opMin / $planMin, 1.0);
                    $perf    = min(($total / $opMin) / (15.0 / 60.0), 1.0);
                    $qual    = $total > 0 ? $ibcOk / $total : 0;
                    $oeeToday = round($avail * $perf * $qual * 100, 1);
                }
            } catch (Exception $e) { error_log('RebotlingAnalyticsController::execDashboard OEE today: ' . $e->getMessage()); }

            // ---- OEE igår ----
            $oeeYesterday = 0;
            try {
                $oRow2 = $this->pdo->query("
                    SELECT
                        SUM(shift_ibc_ok)    AS ibc_ok,
                        SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                        SUM(shift_runtime)   AS runtime_min,
                        SUM(shift_rast)      AS rast_min
                    FROM (
                        SELECT
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                            MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                            MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                            MAX(COALESCE(rasttime,   0)) AS shift_rast
                        FROM rebotling_ibc
                        WHERE DATE(datum) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                          AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY skiftraknare
                    ) AS ps
                ")->fetch(PDO::FETCH_ASSOC);
                if ($oRow2 && $oRow2['runtime_min'] > 0) {
                    $ibcOk2   = (float)$oRow2['ibc_ok'];
                    $ibcEjOk2 = (float)$oRow2['ibc_ej_ok'];
                    $total2   = $ibcOk2 + $ibcEjOk2;
                    $opMin2   = max((float)$oRow2['runtime_min'], 1);
                    $planMin2 = max($opMin2 + (float)$oRow2['rast_min'], 1);
                    $avail2   = min($opMin2 / $planMin2, 1.0);
                    $perf2    = min(($total2 / $opMin2) / (15.0 / 60.0), 1.0);
                    $qual2    = $total2 > 0 ? $ibcOk2 / $total2 : 0;
                    $oeeYesterday = round($avail2 * $perf2 * $qual2 * 100, 1);
                }
            } catch (Exception $e) { error_log('RebotlingAnalyticsController::execDashboard OEE yesterday: ' . $e->getMessage()); }

            // ---- Senaste 7 dagarna (IBC/dag) ----
            $stmt7 = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok) AS ibc_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt7->execute();
            $rows7 = $stmt7->fetchAll(PDO::FETCH_ASSOC);

            // Fyll i tomma dagar (inga produktionsdagar ger 0)
            $map7 = [];
            foreach ($rows7 as $r) { $map7[$r['dag']] = (int)$r['ibc_ok']; }
            $days7 = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $days7[] = ['date' => $d, 'ibc' => $map7[$d] ?? 0, 'target' => $dailyTarget];
            }

            // ---- Veckototaler (mon–idag vs förra veckan mån–sön) ----
            // ISO vecka: måndag = weekday 1
            $stmt14 = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok) AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt14->execute();
            $rows14 = $stmt14->fetchAll(PDO::FETCH_ASSOC);

            $map14 = [];
            foreach ($rows14 as $r) { $map14[$r['dag']] = ['ibc' => (int)$r['ibc_ok'], 'ej' => (int)$r['ibc_ej_ok']]; }

            $thisWeekIbc = 0; $prevWeekIbc = 0;
            $thisWeekOkSum = 0; $thisWeekEjSum = 0;
            for ($i = 13; $i >= 7; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $prevWeekIbc += $map14[$d]['ibc'] ?? 0;
            }
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $thisWeekIbc  += $map14[$d]['ibc'] ?? 0;
                $thisWeekOkSum += $map14[$d]['ibc'] ?? 0;
                $thisWeekEjSum += $map14[$d]['ej']  ?? 0;
            }
            $weekDiff = $prevWeekIbc > 0 ? round((($thisWeekIbc - $prevWeekIbc) / $prevWeekIbc) * 100, 1) : 0;
            $thisWeekQuality = ($thisWeekOkSum + $thisWeekEjSum) > 0
                ? round($thisWeekOkSum / ($thisWeekOkSum + $thisWeekEjSum) * 100, 1)
                : 0;

            // ---- OEE denna vecka (snitt per dag) ----
            $weekOeeRows = $this->pdo->query("
                SELECT
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                      AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                    GROUP BY skiftraknare
                ) AS ps
            ")->fetch(PDO::FETCH_ASSOC);
            $weekOee = 0;
            if ($weekOeeRows && $weekOeeRows['runtime_min'] > 0) {
                $wOk   = (float)$weekOeeRows['ibc_ok'];
                $wEj   = (float)$weekOeeRows['ibc_ej_ok'];
                $wTot  = $wOk + $wEj;
                $wOp   = max((float)$weekOeeRows['runtime_min'], 1);
                $wPlan = max($wOp + (float)$weekOeeRows['rast_min'], 1);
                $wA    = min($wOp / $wPlan, 1.0);
                $wP    = min(($wTot / $wOp) / (15.0 / 60.0), 1.0);
                $wQ    = $wTot > 0 ? $wOk / $wTot : 0;
                $weekOee = round($wA * $wP * $wQ * 100, 1);
            }

            // ---- Bästa operatör denna vecka (IBC/h, position 1 = tvättplats) ----
            $bestOperator = null;
            try {
                $boStmt = $this->pdo->query("
                    SELECT
                        op1 AS operator_id,
                        SUM(shift_ibc_ok) AS ibc_ok,
                        SUM(shift_runtime) AS runtime_min
                    FROM (
                        SELECT
                            op1,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                            MAX(COALESCE(runtime_plc,0)) AS shift_runtime
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                          AND op1 IS NOT NULL AND op1 > 0
                          AND skiftraknare IS NOT NULL
                        GROUP BY op1, skiftraknare
                    ) AS ps
                    GROUP BY op1
                    HAVING runtime_min > 0
                    ORDER BY (ibc_ok * 60.0 / runtime_min) DESC
                    LIMIT 1
                ");
                $boRow = $boStmt->fetch(PDO::FETCH_ASSOC);
                if ($boRow) {
                    $opId = (int)$boRow['operator_id'];
                    $ibcH = $boRow['runtime_min'] > 0
                        ? round($boRow['ibc_ok'] * 60.0 / $boRow['runtime_min'], 1)
                        : 0;
                    // Hämta namn från operators-tabellen (op1 = operators.number)
                    $nameRow = null;
                    try {
                        $ns = $this->pdo->prepare("SELECT name FROM operators WHERE number = ? LIMIT 1");
                        $ns->execute([$opId]);
                        $nameRow = $ns->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception) { /* ignorera */ }
                    $bestOperator = [
                        'id'    => $opId,
                        'name'  => $nameRow ? ($nameRow['name'] ?? 'Okänd') : 'Op #' . $opId,
                        'ibc_h' => $ibcH
                    ];
                }
            } catch (Exception $e) { error_log('RebotlingAnalyticsController::execDashboard bestOp: ' . $e->getMessage()); }

            // ---- Aktiva operatörer senaste skiftet ----
            $lastShiftOps = [];
            try {
                // Hitta senaste skiftraknare
                $lastShiftRow = $this->pdo->query("
                    SELECT skiftraknare FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                    ORDER BY datum DESC LIMIT 1
                ")->fetch(PDO::FETCH_ASSOC);

                if ($lastShiftRow) {
                    $lastShift = (int)$lastShiftRow['skiftraknare'];
                    // Hämta alla operatörer i skiftet (pos 1,2,3)
                    $opRows = $this->pdo->prepare("
                        SELECT
                            pos,
                            operator_id,
                            MAX(ibc_ok)      AS ibc_ok,
                            MAX(ibc_ej_ok)   AS ibc_ej_ok,
                            MAX(runtime_plc) AS runtime_min,
                            SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS bonus
                        FROM (
                            SELECT 'op1' AS pos, op1 AS operator_id, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, datum
                            FROM rebotling_ibc
                            WHERE skiftraknare = ? AND op1 IS NOT NULL AND op1 > 0
                            UNION ALL
                            SELECT 'op2', op2, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, datum
                            FROM rebotling_ibc
                            WHERE skiftraknare = ? AND op2 IS NOT NULL AND op2 > 0
                            UNION ALL
                            SELECT 'op3', op3, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, datum
                            FROM rebotling_ibc
                            WHERE skiftraknare = ? AND op3 IS NOT NULL AND op3 > 0
                        ) AS all_ops
                        GROUP BY pos, operator_id
                    ");
                    $opRows->execute([$lastShift, $lastShift, $lastShift]);
                    $opData = $opRows->fetchAll(PDO::FETCH_ASSOC);

                    // Hämta namn för alla operatörer
                    $opIds = array_unique(array_column($opData, 'operator_id'));
                    $nameMap = [];
                    if (!empty($opIds)) {
                        $placeholders = implode(',', array_fill(0, count($opIds), '?'));
                        $ns2 = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ($placeholders)");
                        $ns2->execute($opIds);
                        foreach ($ns2->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                            $nameMap[(int)$nr['number']] = $nr['name'] ?? 'Okänd';
                        }
                    }

                    $posLabels = ['op1' => 'Tvätt', 'op2' => 'Kontroll', 'op3' => 'Truck'];
                    foreach ($opData as $op) {
                        $opId  = (int)$op['operator_id'];
                        $ok    = (float)$op['ibc_ok'];
                        $ej    = (float)$op['ibc_ej_ok'];
                        $rtMin = max((float)$op['runtime_min'], 1);
                        $ibcH  = round($ok * 60.0 / $rtMin, 1);
                        $qual  = ($ok + $ej) > 0 ? round($ok / ($ok + $ej) * 100, 1) : 0;
                        $lastShiftOps[] = [
                            'id'       => $opId,
                            'name'     => $nameMap[$opId] ?? 'Op #' . $opId,
                            'position' => $posLabels[$op['pos']] ?? $op['pos'],
                            'ibc_h'    => $ibcH,
                            'kvalitet' => $qual,
                            'bonus'    => round((float)$op['bonus'], 1)
                        ];
                    }
                }
            } catch (Exception $e) { error_log('RebotlingAnalyticsController::execDashboard lastShiftOps: ' . $e->getMessage()); }

            // Produktionsprocent idag
            $pct = $dailyTarget > 0 ? round($ibcToday / $dailyTarget * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => [
                    'today' => [
                        'ibc'         => $ibcToday,
                        'target'      => $dailyTarget,
                        'pct'         => $pct,
                        'forecast'    => $forecast,
                        'oee_today'   => $oeeToday,
                        'oee_yesterday' => $oeeYesterday,
                        'rate_per_h'  => round($rate * 60, 1),
                        'shift_start' => $shiftStart
                    ],
                    'week' => [
                        'this_week_ibc'  => $thisWeekIbc,
                        'prev_week_ibc'  => $prevWeekIbc,
                        'week_diff_pct'  => $weekDiff,
                        'quality_pct'    => $thisWeekQuality,
                        'oee_pct'        => $weekOee,
                        'best_operator'  => $bestOperator
                    ],
                    'days7'              => $days7,
                    'last_shift_operators' => $lastShiftOps
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getExecDashboard: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta executive dashboard-data'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Systemstatus
    // =========================================================


    public function getShiftCompare() {
        $date_a = trim($_GET['date_a'] ?? '');
        $date_b = trim($_GET['date_b'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_a) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_b)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $result = [];
            foreach (['a' => $date_a, 'b' => $date_b] as $key => $date) {
                // Aggregerad sammanfattning per dag (summera alla rader för datumet)
                $stmt = $this->pdo->prepare("
                    SELECT
                        SUM(s.ibc_ok)    AS ibc_ok,
                        SUM(s.bur_ej_ok) AS bur_ej_ok,
                        SUM(s.ibc_ej_ok) AS ibc_ej_ok,
                        SUM(s.totalt)    AS totalt,
                        SUM(s.drifttid)  AS drifttid,
                        SUM(s.rasttime)  AS rasttime
                    FROM rebotling_skiftrapport s
                    WHERE s.datum = :date
                ");
                $stmt->execute(['date' => $date]);
                $agg = $stmt->fetch(PDO::FETCH_ASSOC);

                // Beräkna KPI:er
                $totalt   = (int)($agg['totalt']   ?? 0);
                $ibc_ok   = (int)($agg['ibc_ok']   ?? 0);
                $drifttid = (int)($agg['drifttid']  ?? 0);
                $rasttime = (int)($agg['rasttime']  ?? 0);

                $kvalitet = ($totalt > 0)
                    ? round(($ibc_ok / $totalt) * 100, 1)
                    : null;

                $planned = $drifttid + $rasttime;
                $avail   = ($planned > 0)
                    ? min($drifttid / $planned, 1)
                    : null;
                $quality_ratio = ($totalt > 0) ? ($ibc_ok / $totalt) : null;
                $idealRatePerMin = 15.0 / 60.0; // 0.25 IBC/min
                $perf = ($drifttid > 0 && $totalt > 0)
                    ? min(($totalt / $drifttid) / $idealRatePerMin, 1.0)
                    : null;
                $oee = ($avail !== null && $perf !== null && $quality_ratio !== null)
                    ? round($avail * $perf * $quality_ratio * 100, 1)
                    : null;

                $ibc_per_h = ($drifttid > 0)
                    ? round(($ibc_ok / ($drifttid / 60)), 1)
                    : null;

                // Operatörer som jobbade denna dag (från skiftrapporter)
                $opStmt = $this->pdo->prepare("
                    SELECT
                        u.username AS user_name,
                        SUM(s.ibc_ok)  AS ibc_ok,
                        SUM(s.totalt)  AS totalt,
                        SUM(s.drifttid) AS drifttid,
                        o1.name AS op1_name,
                        o2.name AS op2_name,
                        o3.name AS op3_name
                    FROM rebotling_skiftrapport s
                    LEFT JOIN users     u  ON s.user_id = u.id
                    LEFT JOIN operators o1 ON o1.number = s.op1
                    LEFT JOIN operators o2 ON o2.number = s.op2
                    LEFT JOIN operators o3 ON o3.number = s.op3
                    WHERE s.datum = :date
                    GROUP BY s.user_id, u.username, o1.name, o2.name, o3.name
                    ORDER BY ibc_ok DESC
                ");
                $opStmt->execute(['date' => $date]);
                $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

                // Lägg till IBC/h per operatör
                foreach ($operators as &$op) {
                    $op_drift = (int)($op['drifttid'] ?? 0);
                    $op_ibc   = (int)($op['ibc_ok']   ?? 0);
                    $op_tot   = (int)($op['totalt']    ?? 0);
                    $op['ibc_per_h'] = ($op_drift > 0)
                        ? round(($op_ibc / ($op_drift / 60)), 1)
                        : null;
                    $op['kvalitet'] = ($op_tot > 0)
                        ? round(($op_ibc / $op_tot) * 100, 1)
                        : null;
                }
                unset($op);

                $result[$key] = [
                    'date'      => $date,
                    'totalt'    => $totalt,
                    'ibc_ok'    => $ibc_ok,
                    'bur_ej_ok' => (int)($agg['bur_ej_ok'] ?? 0),
                    'ibc_ej_ok' => (int)($agg['ibc_ej_ok'] ?? 0),
                    'kvalitet'  => $kvalitet,
                    'oee'       => $oee,
                    'drifttid'  => $drifttid,
                    'rasttime'  => $rasttime,
                    'ibc_per_h' => $ibc_per_h,
                    'operators' => $operators,
                    'has_data'  => $totalt > 0,
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $result
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getShiftCompare: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte jämföra skift'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Live Ranking — TV-skärm på fabriksgolvet
    // GET ?action=rebotling&run=live-ranking  (ingen auth krävs)
    // =========================================================

    public function getCycleHistogram() {
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        try {
            // Hämta ibc_ok och drifttid per skift för valt datum från rebotling_skiftrapport
            $stmt = $this->pdo->prepare("
                SELECT ibc_ok, drifttid
                FROM rebotling_skiftrapport
                WHERE datum = :date
                  AND ibc_ok > 0
                  AND drifttid > 0
            ");
            $stmt->execute(['date' => $date]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna cykeltid (min/IBC) per skift
            $cycleTimes = [];
            foreach ($shifts as $s) {
                $ibcOk    = (int)$s['ibc_ok'];
                $driftMin = (float)$s['drifttid'];
                if ($ibcOk > 0 && $driftMin > 0) {
                    $cycleTimes[] = $driftMin / $ibcOk;
                }
            }

            // Om inga skiftrapporter finns: hämta cykeltider per cykel från PLC-data
            if (empty($cycleTimes)) {
                $stmt2 = $this->pdo->prepare("
                    SELECT
                        skiftraknare,
                        datum,
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) / 60.0 AS cycle_time_min
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                      AND skiftraknare IS NOT NULL
                    ORDER BY skiftraknare, datum ASC
                ");
                $stmt2->execute(['date' => $date]);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows2 as $r) {
                    $ct = (float)($r['cycle_time_min'] ?? 0);
                    // Filtrera: 0.5 – 30 min
                    if ($ct >= 0.5 && $ct <= 30) {
                        $cycleTimes[] = $ct;
                    }
                }
            }

            // Bygg histogrambuckets
            $buckets = [
                '0-2 min'  => 0,
                '2-3 min'  => 0,
                '3-4 min'  => 0,
                '4-5 min'  => 0,
                '5-7 min'  => 0,
                '7+ min'   => 0,
            ];
            foreach ($cycleTimes as $ct) {
                if ($ct < 2)      $buckets['0-2 min']++;
                elseif ($ct < 3)  $buckets['2-3 min']++;
                elseif ($ct < 4)  $buckets['3-4 min']++;
                elseif ($ct < 5)  $buckets['4-5 min']++;
                elseif ($ct < 7)  $buckets['5-7 min']++;
                else              $buckets['7+ min']++;
            }

            // Statistik
            $n      = count($cycleTimes);
            $snitt  = $n > 0 ? array_sum($cycleTimes) / $n : 0;
            $p50 = $p90 = $p95 = 0;
            if ($n > 0) {
                sort($cycleTimes);
                $p50 = $cycleTimes[(int)floor(($n - 1) * 0.50)];
                $p90 = $cycleTimes[(int)floor(($n - 1) * 0.90)];
                $p95 = $cycleTimes[(int)floor(($n - 1) * 0.95)];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'date'    => $date,
                    'buckets' => array_map(function($label, $count) {
                        return ['label' => $label, 'count' => $count];
                    }, array_keys($buckets), array_values($buckets)),
                    'stats' => [
                        'n'      => $n,
                        'snitt'  => round($snitt, 2),
                        'p50'    => round($p50, 2),
                        'p90'    => round($p90, 2),
                        'p95'    => round($p95, 2),
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getCycleHistogram: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta cykeltidsfordelning'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // SPC-kontrollkort
    // GET ?action=rebotling&run=spc&days=7
    // =========================================================

    public function getSPC() {
        $days = min(30, max(3, intval($_GET['days'] ?? 7)));

        try {
            // Hämta IBC/h per skift de senaste N dagarna från rebotling_skiftrapport
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    skift_nr,
                    ibc_ok,
                    drifttid
                FROM rebotling_skiftrapport
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  AND ibc_ok > 0
                  AND drifttid > 0
                ORDER BY datum ASC, skift_nr ASC
            ");
            $stmt->execute(['days' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $points = [];
            foreach ($rows as $r) {
                $ibcOk    = (int)$r['ibc_ok'];
                $driftMin = (float)$r['drifttid'];
                if ($driftMin > 0) {
                    $ibcPerH = round($ibcOk * 60.0 / $driftMin, 2);
                    $points[] = [
                        'label'        => $r['datum'] . ' S' . $r['skift_nr'],
                        'ibc_per_hour' => $ibcPerH,
                    ];
                }
            }

            // Fallback: PLC-data aggregerat per dag+skift om inga skiftrapporter
            if (empty($points)) {
                $stmt2 = $this->pdo->prepare("
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                    HAVING shift_runtime > 0 AND shift_ibc_ok > 0
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt2->execute(['days' => $days]);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows2 as $r) {
                    $ibcPerH = round($r['shift_ibc_ok'] * 60.0 / $r['shift_runtime'], 2);
                    $points[] = [
                        'label'        => $r['dag'] . ' #' . $r['skiftraknare'],
                        'ibc_per_hour' => $ibcPerH,
                    ];
                }
            }

            // Beräkna medelvärde (X̄) och standardavvikelse (σ)
            $n      = count($points);
            $mean   = 0;
            $stddev = 0;
            if ($n > 0) {
                $values = array_column($points, 'ibc_per_hour');
                $mean   = array_sum($values) / $n;
                if ($n > 1) {
                    $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($n - 1);
                    $stddev   = sqrt($variance);
                }
            }

            $ucl = round($mean + 2 * $stddev, 2);
            $lcl = round(max(0, $mean - 2 * $stddev), 2);

            echo json_encode([
                'success' => true,
                'data' => [
                    'points' => $points,
                    'mean'   => round($mean, 2),
                    'stddev' => round($stddev, 2),
                    'ucl'    => $ucl,
                    'lcl'    => $lcl,
                    'n'      => $n,
                    'days'   => $days,
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getSPC: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta SPC-data'], JSON_UNESCAPED_UNICODE);
        }
    }


    // =========================================================
    // Cykeltid per operatör
    // GET ?action=rebotling&run=cycle-by-operator&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
    // =========================================================

    public function getCycleByOperator() {
        $today = date('Y-m-d');
        $startDate = trim($_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days')));
        $endDate   = trim($_GET['end_date']   ?? $today);

        // Validera datumformat
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d', strtotime('-29 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $today;
        }
        // Validera att start <= end
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Begränsa datumintervall till max 365 dagar
        try {
            $startDt = new DateTime($startDate, new DateTimeZone('Europe/Stockholm'));
            $endDt   = new DateTime($endDate, new DateTimeZone('Europe/Stockholm'));
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getHourlyBreakdown — ogiltigt datumvärde: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumvärde'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $diffDays = (int)$startDt->diff($endDt)->days;
        if ($diffDays > 365) {
            $startDate = (clone $endDt)->modify('-365 days')->format('Y-m-d');
        }

        try {
            // Hämta alla enskilda skift-rader per operatör för att kunna beräkna median och P90
            // Cykeltid (sek/IBC) = (drifttid minuter * 60) / ibc_ok
            $sqlRaw = "
                SELECT t.op_num, o.number AS op_id, o.name AS namn,
                       t.ibc_ok_shift, t.snitt_cykel_sek
                FROM (
                    SELECT s.op1 AS op_num,
                           s.ibc_ok AS ibc_ok_shift,
                           (COALESCE(s.drifttid, 0) * 60.0 / s.ibc_ok) AS snitt_cykel_sek
                    FROM rebotling_skiftrapport s
                    WHERE s.datum BETWEEN ? AND ?
                      AND s.op1 IS NOT NULL
                      AND s.ibc_ok > 0
                      AND s.drifttid > 0
                    UNION ALL
                    SELECT s.op2 AS op_num,
                           s.ibc_ok AS ibc_ok_shift,
                           (COALESCE(s.drifttid, 0) * 60.0 / s.ibc_ok) AS snitt_cykel_sek
                    FROM rebotling_skiftrapport s
                    WHERE s.datum BETWEEN ? AND ?
                      AND s.op2 IS NOT NULL
                      AND s.ibc_ok > 0
                      AND s.drifttid > 0
                    UNION ALL
                    SELECT s.op3 AS op_num,
                           s.ibc_ok AS ibc_ok_shift,
                           (COALESCE(s.drifttid, 0) * 60.0 / s.ibc_ok) AS snitt_cykel_sek
                    FROM rebotling_skiftrapport s
                    WHERE s.datum BETWEEN ? AND ?
                      AND s.op3 IS NOT NULL
                      AND s.ibc_ok > 0
                      AND s.drifttid > 0
                ) t
                JOIN operators o ON o.number = t.op_num
                WHERE t.snitt_cykel_sek BETWEEN 30 AND 600
                ORDER BY t.op_num, t.snitt_cykel_sek ASC
            ";

            $stmt = $this->pdo->prepare($sqlRaw);
            $stmt->execute([
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate,
            ]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Gruppera per operatör och beräkna statistik inklusive median och P90
            $grouped = [];
            foreach ($rawRows as $r) {
                $id = (int)$r['op_id'];
                if (!isset($grouped[$id])) {
                    $grouped[$id] = [
                        'op_id'  => $id,
                        'namn'   => $r['namn'],
                        'values' => [],
                        'total_ibc' => 0,
                    ];
                }
                $grouped[$id]['values'][] = (float)$r['snitt_cykel_sek'];
                $grouped[$id]['total_ibc'] += (int)$r['ibc_ok_shift'];
            }

            // Hjälpfunktion: percentil (linjär interpolation)
            $percentile = function(array $sorted, float $p): float {
                $n = count($sorted);
                if ($n === 0) return 0.0;
                if ($n === 1) return $sorted[0];
                $idx = $p / 100.0 * ($n - 1);
                $lo  = (int)floor($idx);
                $hi  = (int)ceil($idx);
                if ($lo === $hi) return $sorted[$lo];
                return $sorted[$lo] + ($idx - $lo) * ($sorted[$hi] - $sorted[$lo]);
            };

            $operators = [];
            foreach ($grouped as $id => $g) {
                $vals = $g['values']; // redan sorterat ASC från SQL
                sort($vals);
                $n = count($vals);
                if ($n === 0) continue;
                $nameParts = array_filter(explode(' ', trim($g['namn'])));
                $initialer = '';
                foreach ($nameParts as $p) {
                    if ($p !== '') $initialer .= strtoupper(substr($p, 0, 1));
                }
                $initialer = substr($initialer, 0, 3) ?: ('OP' . $id);

                $median_sek = $percentile($vals, 50);
                $p90_sek    = $percentile($vals, 90);

                $mean_sek = array_sum($vals) / $n;
                $stddev_sek = 0.0;
                if ($n > 1) {
                    $variance = array_sum(array_map(fn($v) => pow($v - $mean_sek, 2), $vals)) / ($n - 1);
                    $stddev_sek = sqrt($variance);
                }

                $operators[] = [
                    'op_id'          => $id,
                    'namn'           => $g['namn'],
                    'initialer'      => $initialer,
                    'antal_skift'    => $n,
                    'snitt_cykel_sek'=> round($mean_sek, 1),
                    'bast_cykel_sek' => round($vals[0], 1),
                    'samst_cykel_sek'=> round($vals[$n - 1], 1),
                    'median_min'     => round($median_sek / 60.0, 2),
                    'min_min'        => round($vals[0] / 60.0, 2),
                    'max_min'        => round($vals[$n - 1] / 60.0, 2),
                    'p90_min'        => round($p90_sek / 60.0, 2),
                    'stddev_min'     => round($stddev_sek / 60.0, 2),
                    'total_ibc'      => (int)$g['total_ibc'],
                ];
            }

            // Sortera fallande på antal_skift (flest registrerade cykler överst)
            usort($operators, fn($a, $b) => $b['antal_skift'] - $a['antal_skift']);

            echo json_encode([
                'success'    => true,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'data'       => $operators,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getCycleByOperator: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta cykeltid per operatör'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Produktionskalender — GitHub-liknande heatmap per år
    // GET ?action=rebotling&run=year-calendar&year=YYYY
    // =========================================================

    public function getYearCalendar() {
        $year = intval($_GET['year'] ?? date('Y'));
        if ($year < 2020 || $year > 2100) {
            $year = (int)date('Y');
        }

        try {
            // Hämta dagsmål: försök veckodagsmål, annars rebotling_settings
            $weekdayGoals = [];
            try {
                $wgStmt = $this->pdo->query("SELECT weekday, daily_goal FROM rebotling_weekday_goals");
                foreach ($wgStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $weekdayGoals[(int)$row['weekday']] = (int)$row['daily_goal'];
                }
            } catch (Exception) { /* tabell saknas */ }

            $defaultGoal = 1000;
            try {
                $sgRow = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sgRow) $defaultGoal = (int)$sgRow['rebotling_target'];
            } catch (Exception) { /* ignorera */ }

            // Hämta produktion per dag för hela året från rebotling_skiftrapport
            // SUM(ibc_ok) per datum
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    SUM(ibc_ok) AS ibc_ok
                FROM rebotling_skiftrapport
                WHERE YEAR(datum) = :year
                  AND ibc_ok IS NOT NULL
                GROUP BY datum
                ORDER BY datum ASC
            ");
            $stmt->execute(['year' => $year]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback: om inga skiftrapporter — hämta från rebotling_ibc (PLC-data)
            if (empty($rows)) {
                $stmt2 = $this->pdo->prepare("
                    SELECT
                        DATE(datum) AS datum,
                        SUM(shift_ibc_ok) AS ibc_ok
                    FROM (
                        SELECT
                            datum,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                        FROM rebotling_ibc
                        WHERE YEAR(datum) = :year
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ps
                    GROUP BY DATE(datum)
                    ORDER BY datum ASC
                ");
                $stmt2->execute(['year' => $year]);
                $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }

            $days = [];
            foreach ($rows as $row) {
                $dateStr = $row['datum'];
                $ibc     = (int)$row['ibc_ok'];
                if ($ibc <= 0) continue; // hoppa över nolldagar

                // Bestäm dagsmål: ISO-veckodag 1=Måndag ... 7=Söndag
                $dt      = new DateTime($dateStr, new DateTimeZone('Europe/Stockholm'));
                $wday    = (int)$dt->format('N'); // 1=Mån, 7=Sön
                $goal    = isset($weekdayGoals[$wday]) ? $weekdayGoals[$wday] : $defaultGoal;

                // Om dagsmål är 0 (t.ex. lördag/söndag) men det ändå producerats — sätt mål till defaultGoal
                if ($goal <= 0) $goal = $defaultGoal;

                $pct = $goal > 0 ? round($ibc / $goal * 100, 2) : 0;

                $days[] = [
                    'date' => $dateStr,
                    'ibc'  => $ibc,
                    'goal' => $goal,
                    'pct'  => $pct,
                ];
            }

            echo json_encode([
                'success' => true,
                'year'    => $year,
                'days'    => $days,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getYearCalendar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kalenderdata'], JSON_UNESCAPED_UNICODE);
        }
    }


    // =========================================================
    // Dagdetalj — timvis nedbrytning för en vald dag
    // GET ?action=rebotling&run=day-detail&date=YYYY-MM-DD
    // =========================================================

    public function getDayDetail() {
        $date = trim($_GET['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Beräkna delta-IBC per timme (differens från föregående ackumulerat värde per skift)
            // Hämtar rådata per skift och timme för att kunna beräkna deltas korrekt
            $rawStmt = $this->pdo->prepare("
                SELECT
                    HOUR(datum)      AS timme,
                    skiftraknare,
                    MAX(ibc_ok)      AS acc_ibc,
                    MAX(ibc_ej_ok)   AS acc_ej_ok,
                    MAX(runtime_plc) AS runtime_min
                FROM rebotling_ibc
                WHERE DATE(datum) = ?
                  AND skiftraknare IS NOT NULL
                GROUP BY skiftraknare, HOUR(datum)
                ORDER BY skiftraknare, timme
            ");
            $rawStmt->execute([$date]);
            $rawRows = $rawStmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg delta-beräkning per skift
            // key: skiftraknare → [timme → delta_ibc]
            $shiftPrevIbc   = [];
            $shiftPrevEjOk  = [];
            $deltaMap       = []; // timme → delta_ibc (summerat över alla skift)
            $deltaEjOkMap   = []; // timme → delta_ej_ok
            $runtimeMap     = []; // timme → runtime_min (max över skift)

            foreach ($rawRows as $r) {
                $t    = (int)$r['timme'];
                $sk   = (int)$r['skiftraknare'];
                $acc  = (int)$r['acc_ibc'];
                $eo   = (int)$r['acc_ej_ok'];
                $rt   = (int)$r['runtime_min'];

                // Delta IBC
                $prev = $shiftPrevIbc[$sk] ?? 0;
                $delta = max(0, $acc - $prev);
                $shiftPrevIbc[$sk] = $acc;

                // Delta ej_ok
                $prevEo = $shiftPrevEjOk[$sk] ?? 0;
                $deltaEo = max(0, $eo - $prevEo);
                $shiftPrevEjOk[$sk] = $eo;

                $deltaMap[$t] = ($deltaMap[$t] ?? 0) + $delta;
                $deltaEjOkMap[$t] = ($deltaEjOkMap[$t] ?? 0) + $deltaEo;
                $runtimeMap[$t] = max($runtimeMap[$t] ?? 0, $rt);
            }

            // Bestäm skift (1=06-13, 2=14-21, 3=22-05)
            $hourToSkift = function(int $h): int {
                if ($h >= 6 && $h <= 13) return 1;
                if ($h >= 14 && $h <= 21) return 2;
                return 3; // 22-05
            };

            // Bygg timvis array
            $hourly = [];
            $totalIbc    = 0;
            $totalEjOk   = 0;
            $skift1Ibc   = 0;
            $skift2Ibc   = 0;
            $skift3Ibc   = 0;
            $activeHours = 0;
            $ibcPerHList = [];

            foreach ($deltaMap as $timme => $deltaIbc) {
                $rt      = $runtimeMap[$timme] ?? 0; // runtime_plc i minuter (kumulativt)
                $rtH     = $rt / 60.0; // konvertera till timmar
                $deltaEo = $deltaEjOkMap[$timme] ?? 0;
                $skift   = $hourToSkift($timme);

                // IBC/h: ibc producerat under timmen / effektiv drifttid i timmar
                $ibcPerH = $rtH > 0 ? round($deltaIbc / $rtH, 1) : 0.0;

                if ($deltaIbc > 0) {
                    $activeHours++;
                    $ibcPerHList[] = $ibcPerH;
                }

                $totalIbc  += $deltaIbc;
                $totalEjOk += $deltaEo;
                if ($skift === 1) $skift1Ibc += $deltaIbc;
                elseif ($skift === 2) $skift2Ibc += $deltaIbc;
                else $skift3Ibc += $deltaIbc;

                $hourly[] = [
                    'timme'      => $timme,
                    'ibc'        => $deltaIbc,
                    'ibc_per_h'  => $ibcPerH,
                    'runtime_min'=> round($rt, 1),
                    'ej_ok'      => $deltaEo,
                    'skift'      => $skift,
                ];
            }

            // Sortera på timme
            usort($hourly, fn($a, $b) => $a['timme'] - $b['timme']);

            $avgIbcPerH  = count($ibcPerHList) > 0 ? round(array_sum($ibcPerHList) / count($ibcPerHList), 1) : 0.0;
            $totalProduced = $totalIbc + $totalEjOk;
            $qualityPct    = $totalProduced > 0 ? round($totalIbc / $totalProduced * 100, 1) : 0.0;

            // Hämta aktiva operatörer för denna dag
            $opStmt = $this->pdo->prepare("
                SELECT DISTINCT op_id, o.name AS op_name
                FROM (
                    SELECT op1 AS op_id FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT op2 FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT op3 FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op3 IS NOT NULL AND op3 > 0
                ) AS ops
                LEFT JOIN operators o ON o.number = op_id
                WHERE op_id IS NOT NULL
            ");
            $opStmt->execute([$date, $date, $date]);
            $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatera operatörer — initials + namn
            $opList = [];
            foreach ($operators as $op) {
                $name = $op['op_name'] ?? 'Op ' . $op['op_id'];
                $parts = explode(' ', trim($name));
                $initials = '';
                foreach ($parts as $p) {
                    if (strlen($p) > 0) $initials .= strtoupper($p[0]);
                }
                $opList[] = [
                    'id'       => (int)$op['op_id'],
                    'name'     => $name,
                    'initials' => $initials,
                ];
            }

            echo json_encode([
                'success' => true,
                'date'    => $date,
                'hourly'  => $hourly,
                'summary' => [
                    'total_ibc'     => $totalIbc,
                    'avg_ibc_per_h' => $avgIbcPerH,
                    'skift1_ibc'    => $skift1Ibc,
                    'skift2_ibc'    => $skift2Ibc,
                    'skift3_ibc'    => $skift3Ibc,
                    'total_ej_ok'   => $totalEjOk,
                    'quality_pct'   => $qualityPct,
                    'active_hours'  => $activeHours,
                ],
                'operators' => $opList,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getDayDetail: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta dagdetalj'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Benchmarking — denna vecka vs rekordveckan
    // =========================================================

    /**
     * GET ?action=rebotling&run=benchmarking
     *
     * Returnerar:
     *   - current_week: IBC totalt, IBC/dag, snitt kvalitet%, snitt OEE%, aktiva dagar för innevarande vecka
     *   - best_week_ever: rekordveckan (höst IBC totalt)
     *   - best_day_ever: dag med flest IBC
     *   - top_weeks: topp-10 veckor sorterade på ibc_total DESC
     *   - monthly_totals: IBC per månad senaste 12 månaderna
     */

    public function getBenchmarking() {
        try {
            $tz = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // ---- Ideal rate för OEE-beräkning ----
            $idealRatePerMin = 15.0 / 60.0;

            // ---- Hjälpfunktion: beräkna OEE% för en mängd skift-aggregat ----
            // Används inline nedan.

            // ================================================================
            // 1. Topp-10 veckor (aggregerat korrekt: MAX per skift → SUM per vecka)
            // ================================================================
            $stmtTop = $this->pdo->query("
                SELECT
                    YEAR(datum)     AS yr,
                    WEEK(datum, 1)  AS wk,
                    SUM(shift_ibc)  AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS avg_quality,
                    COUNT(DISTINCT DATE(datum))  AS days_active
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        COALESCE(MAX(ibc_ok), 0) AS shift_ibc,
                        ROUND(
                            COALESCE(MAX(ibc_ok), 0) * 100.0
                            / NULLIF(COALESCE(MAX(ibc_ok), 0) + COALESCE(MAX(ibc_ej_ok), 0), 0),
                        1) AS shift_quality
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY YEAR(datum), WEEK(datum, 1)
                ORDER BY ibc_total DESC
                LIMIT 10
            ");
            $topWeeksRaw = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            // ---- OEE per vecka (topp-10 veckor) ----
            // För OEE behöver vi runtime_plc och rasttime per skift per vecka.
            $stmtOeeWeeks = $this->pdo->query("
                SELECT
                    YEAR(datum)    AS yr,
                    WEEK(datum, 1) AS wk,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY YEAR(datum), WEEK(datum, 1)
            ");
            $oeeByWeek = [];
            foreach ($stmtOeeWeeks->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = $row['yr'] . '-' . $row['wk'];
                $totalIBC  = (float)$row['ibc_ok'] + (float)$row['ibc_ej_ok'];
                $goodIBC   = (float)$row['ibc_ok'];
                $runtimeM  = max((float)$row['runtime_min'], 1);
                $plannedM  = max((float)$row['runtime_min'] + (float)$row['rast_min'], 1);
                $avail     = min($runtimeM / $plannedM, 1.0);
                $perf      = min(($totalIBC / $runtimeM) / $idealRatePerMin, 1.0);
                $qual      = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;
                $oeeByWeek[$key] = round($avail * $perf * $qual * 100, 1);
            }

            // ---- Bygg topp-10-lista ----
            $topWeeks = [];
            foreach ($topWeeksRaw as $row) {
                $yr  = (int)$row['yr'];
                $wk  = (int)$row['wk'];
                $key = $yr . '-' . $wk;
                $oee = $oeeByWeek[$key] ?? null;
                // ISO veckonummer → veckoetiketten
                $weekLabel = 'V' . $wk . ' ' . $yr;
                $topWeeks[] = [
                    'week_label'  => $weekLabel,
                    'yr'          => $yr,
                    'wk'          => $wk,
                    'ibc_total'   => (int)$row['ibc_total'],
                    'avg_quality' => (float)$row['avg_quality'],
                    'avg_oee'     => $oee,
                    'days_active' => (int)$row['days_active'],
                ];
            }

            // ================================================================
            // 2. Bästa veckan — första raden i topWeeks
            // ================================================================
            $bestWeekEver = null;
            if (!empty($topWeeks)) {
                $bw = $topWeeks[0];
                $ipcDay = $bw['days_active'] > 0 ? round($bw['ibc_total'] / $bw['days_active'], 1) : 0.0;
                $bestWeekEver = [
                    'week_label'  => $bw['week_label'],
                    'ibc_total'   => $bw['ibc_total'],
                    'ibc_per_day' => $ipcDay,
                    'avg_quality' => $bw['avg_quality'],
                    'avg_oee'     => $bw['avg_oee'],
                ];
            }

            // ================================================================
            // 3. Innevarande vecka
            // ================================================================
            $curYr = (int)$now->format('Y');
            $curWk = (int)$now->format('W'); // ISO 8601 veckonummer

            $stmtCur = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc)  AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS avg_quality,
                    COUNT(DISTINCT DATE(datum))   AS days_active
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        COALESCE(MAX(ibc_ok), 0) AS shift_ibc,
                        ROUND(
                            COALESCE(MAX(ibc_ok), 0) * 100.0
                            / NULLIF(COALESCE(MAX(ibc_ok), 0) + COALESCE(MAX(ibc_ej_ok), 0), 0),
                        1) AS shift_quality
                    FROM rebotling_ibc
                    WHERE YEAR(datum) = ?
                      AND WEEK(datum, 1) = ?
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmtCur->execute([$curYr, $curWk]);
            $curRow = $stmtCur->fetch(PDO::FETCH_ASSOC);

            // OEE innevarande vecka
            $stmtCurOee = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE YEAR(datum) = ?
                      AND WEEK(datum, 1) = ?
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY skiftraknare
                ) AS ps
            ");
            $stmtCurOee->execute([$curYr, $curWk]);
            $curOeeRow = $stmtCurOee->fetch(PDO::FETCH_ASSOC);

            $curOee = null;
            if ($curOeeRow && $curOeeRow['runtime_min'] > 0) {
                $totalIBC = (float)$curOeeRow['ibc_ok'] + (float)$curOeeRow['ibc_ej_ok'];
                $goodIBC  = (float)$curOeeRow['ibc_ok'];
                $runtimeM = max((float)$curOeeRow['runtime_min'], 1);
                $plannedM = max((float)$curOeeRow['runtime_min'] + (float)$curOeeRow['rast_min'], 1);
                $avail    = min($runtimeM / $plannedM, 1.0);
                $perf     = min(($totalIBC / $runtimeM) / $idealRatePerMin, 1.0);
                $qual     = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;
                $curOee   = round($avail * $perf * $qual * 100, 1);
            }

            $curIbcTotal = (int)($curRow['ibc_total'] ?? 0);
            $curDaysActive = (int)($curRow['days_active'] ?? 0);
            $curIbcPerDay  = $curDaysActive > 0 ? round($curIbcTotal / $curDaysActive, 1) : 0.0;
            $weekLabel = 'V' . $curWk . ' ' . $curYr;

            $currentWeek = [
                'week_label'  => $weekLabel,
                'ibc_total'   => $curIbcTotal,
                'ibc_per_day' => $curIbcPerDay,
                'avg_quality' => (float)($curRow['avg_quality'] ?? 0),
                'avg_oee'     => $curOee,
                'days_active' => $curDaysActive,
            ];

            // ================================================================
            // 4. Bästa dagen någonsin
            // ================================================================
            $stmtBestDay = $this->pdo->query("
                SELECT
                    DATE(datum) AS datum,
                    SUM(shift_ibc) AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS quality
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        COALESCE(MAX(ibc_ok), 0) AS shift_ibc,
                        ROUND(
                            COALESCE(MAX(ibc_ok), 0) * 100.0
                            / NULLIF(COALESCE(MAX(ibc_ok), 0) + COALESCE(MAX(ibc_ej_ok), 0), 0),
                        1) AS shift_quality
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY DATE(datum)
                ORDER BY ibc_total DESC
                LIMIT 1
            ");
            $bestDayRow = $stmtBestDay->fetch(PDO::FETCH_ASSOC);
            $bestDayEver = $bestDayRow ? [
                'date'      => $bestDayRow['datum'],
                'ibc_total' => (int)$bestDayRow['ibc_total'],
                'quality'   => (float)$bestDayRow['quality'],
            ] : null;

            // ================================================================
            // 5. Månadsöversikt senaste 13 månader (innevar. månad + 12 bakåt)
            // ================================================================
            $stmtMonthly = $this->pdo->query("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m') AS month,
                    SUM(shift_ibc)  AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS avg_quality
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        COALESCE(MAX(ibc_ok), 0) AS shift_ibc,
                        ROUND(
                            COALESCE(MAX(ibc_ok), 0) * 100.0
                            / NULLIF(COALESCE(MAX(ibc_ok), 0) + COALESCE(MAX(ibc_ej_ok), 0), 0),
                        1) AS shift_quality
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(LAST_DAY(NOW()), INTERVAL 13 MONTH)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ORDER BY month ASC
            ");
            $monthlyRaw = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

            // OEE per månad
            $stmtMonthlyOee = $this->pdo->query("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m') AS month,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(LAST_DAY(NOW()), INTERVAL 13 MONTH)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
            ");
            $oeeByMonth = [];
            foreach ($stmtMonthlyOee->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $totalIBC = (float)$row['ibc_ok'] + (float)$row['ibc_ej_ok'];
                $goodIBC  = (float)$row['ibc_ok'];
                $runtimeM = max((float)$row['runtime_min'], 1);
                $plannedM = max((float)$row['runtime_min'] + (float)$row['rast_min'], 1);
                $avail    = min($runtimeM / $plannedM, 1.0);
                $perf     = min(($totalIBC / $runtimeM) / $idealRatePerMin, 1.0);
                $qual     = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;
                $oeeByMonth[$row['month']] = round($avail * $perf * $qual * 100, 1);
            }

            $monthlyTotals = [];
            foreach ($monthlyRaw as $row) {
                $monthlyTotals[] = [
                    'month'       => $row['month'],
                    'ibc_total'   => (int)$row['ibc_total'],
                    'avg_quality' => (float)$row['avg_quality'],
                    'avg_oee'     => $oeeByMonth[$row['month']] ?? null,
                ];
            }

            // ================================================================
            // Bygg slutsvar
            // ================================================================
            echo json_encode([
                'success'       => true,
                'current_week'  => $currentWeek,
                'best_week_ever'=> $bestWeekEver,
                'best_day_ever' => $bestDayEver,
                'top_weeks'     => $topWeeks,
                'monthly_totals'=> $monthlyTotals,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getBenchmarking: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta benchmarking-data'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Månadsrapport — Jämförelse föregående månad
    // GET ?action=rebotling&run=month-compare&month=YYYY-MM
    // =========================================================

    public function getMonthCompare() {
        try {
            $monthParam = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
                $monthParam = date('Y-m');
            }

            [$year, $mon] = explode('-', $monthParam);
            $year = (int)$year;
            $mon  = (int)$mon;

            // Beräkna föregående månad
            $prevMon  = $mon - 1;
            $prevYear = $year;
            if ($prevMon < 1) {
                $prevMon  = 12;
                $prevYear = $year - 1;
            }
            $prevMonth = sprintf('%04d-%02d', $prevYear, $prevMon);

            // Dagsmål
            $dailyGoal = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['rebotling_target'])) {
                    $dailyGoal = (int)$sr['rebotling_target'];
                }
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::getMonthCompare: kunde ej läsa dagsmål: ' . $e->getMessage());
            }

            // Hjälpfunktion: hämta summering för en månad
            $fetchMonthData = function(string $m) use ($dailyGoal): array {
                // Räkna vardagar
                $daysInMonth = (int)date('t', strtotime($m . '-01'));
                $workdays = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dow = (int)date('N', strtotime(sprintf('%s-%02d', $m, $d)));
                    if ($dow < 6) $workdays++;
                }
                $monthGoal = $dailyGoal * $workdays;

                $perShiftSQL = "
                    SELECT
                        DATE(datum)                                                               AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))                                                 AS shift_ibc,
                        MAX(COALESCE(ibc_ej_ok, 0))                                              AS shift_ej_ok,
                        ROUND(MAX(COALESCE(ibc_ok,0))*100.0 /
                            NULLIF(MAX(COALESCE(ibc_ok,0))+MAX(COALESCE(ibc_ej_ok,0)),0),1)     AS shift_quality,
                        MAX(COALESCE(runtime_plc, 0))                                            AS shift_runtime,
                        MAX(COALESCE(rasttime, 0))                                               AS shift_rast
                    FROM rebotling_ibc
                    WHERE DATE_FORMAT(datum,'%Y-%m') = ?
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ";

                $summarySQL = "
                    SELECT
                        COUNT(DISTINCT dag)                   AS production_days,
                        SUM(shift_ibc)                        AS ibc_total,
                        ROUND(AVG(shift_quality),1)           AS avg_quality,
                        ROUND(SUM(shift_runtime)/60.0,1)      AS total_runtime_hours,
                        ROUND(SUM(shift_rast)/60.0,1)         AS total_stoppage_hours
                    FROM ({$perShiftSQL}) AS per_shift
                ";
                $stmt = $this->pdo->prepare($summarySQL);
                $stmt->execute([$m]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                $ibcTotal       = (int)($row['ibc_total'] ?? 0);
                $prodDays       = (int)($row['production_days'] ?? 0);
                $avgQuality     = (float)($row['avg_quality'] ?? 0);

                // OEE daglig för snittberäkning
                $oeeSQL = "
                    SELECT dag,
                           SUM(shift_ibc)     AS ibc_ok,
                           SUM(shift_ej_ok)   AS ibc_ej_ok,
                           SUM(shift_runtime) AS runtime_min,
                           SUM(shift_rast)    AS rast_min
                    FROM ({$perShiftSQL}) AS ps2
                    GROUP BY dag
                ";
                $stmt2 = $this->pdo->prepare($oeeSQL);
                $stmt2->execute([$m]);
                $oeeRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                $idealRatePerMin = 15.0 / 60.0;
                $oeeSum = 0.0;
                $oeeDays = 0;
                $bestDay  = null;
                $worstDay = null;
                foreach ($oeeRows as $r) {
                    $ibcOk   = (float)$r['ibc_ok'];
                    $ibcEjOk = (float)$r['ibc_ej_ok'];
                    $total   = $ibcOk + $ibcEjOk;
                    $opMin   = max((float)$r['runtime_min'], 1);
                    $planMin = max($opMin + (float)$r['rast_min'], 1);
                    $avail   = min($opMin / $planMin, 1.0);
                    $perf    = min(($total / $opMin) / $idealRatePerMin, 1.0);
                    $qual    = $total > 0 ? $ibcOk / $total : 0;
                    $oee     = round($avail * $perf * $qual * 100, 1);
                    if ($ibcOk > 0) {
                        $oeeSum += $oee;
                        $oeeDays++;
                        $targetPct = $dailyGoal > 0 ? round($ibcOk / $dailyGoal * 100, 1) : 0;
                        if ($bestDay === null || $ibcOk > $bestDay['ibc']) {
                            $bestDay = ['datum' => $r['dag'], 'ibc' => (int)$ibcOk, 'target_pct' => $targetPct];
                        }
                        if ($worstDay === null || $ibcOk < $worstDay['ibc']) {
                            $worstDay = ['datum' => $r['dag'], 'ibc' => (int)$ibcOk, 'target_pct' => $targetPct];
                        }
                    }
                }
                $avgOee = $oeeDays > 0 ? round($oeeSum / $oeeDays, 1) : 0;
                $avgIbcPerDay = $prodDays > 0 ? round($ibcTotal / $prodDays, 1) : 0;

                return [
                    'total_ibc'       => $ibcTotal,
                    'avg_ibc_per_day' => $avgIbcPerDay,
                    'avg_oee_pct'     => $avgOee,
                    'avg_quality_pct' => $avgQuality,
                    'working_days'    => $prodDays,
                    'month_goal'      => $monthGoal,
                    'best_day'        => $bestDay,
                    'worst_day'       => $worstDay,
                ];
            };

            $thisMonthData = $fetchMonthData($monthParam);
            $prevMonthData = $fetchMonthData($prevMonth);

            // Beräkna diff
            $diffIbcPct = null;
            if ($prevMonthData['total_ibc'] > 0) {
                $diffIbcPct = round(($thisMonthData['total_ibc'] - $prevMonthData['total_ibc']) / $prevMonthData['total_ibc'] * 100, 1);
            }
            $diffAvgIbcPerDayPct = null;
            if ($prevMonthData['avg_ibc_per_day'] > 0) {
                $diffAvgIbcPerDayPct = round(($thisMonthData['avg_ibc_per_day'] - $prevMonthData['avg_ibc_per_day']) / $prevMonthData['avg_ibc_per_day'] * 100, 1);
            }
            $diffOee     = round($thisMonthData['avg_oee_pct'] - $prevMonthData['avg_oee_pct'], 1);
            $diffQuality = round($thisMonthData['avg_quality_pct'] - $prevMonthData['avg_quality_pct'], 1);

            // Operatör av månaden — använder rebotling_ibc
            $opOfMonth = null;
            try {
                $firstDay = $monthParam . '-01';
                $lastDay  = date('Y-m-t', strtotime($firstDay));
                $opSQL = "
                    SELECT op_id, SUM(shift_ibc) AS total_ibc,
                           SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) AS avg_ibc_per_h,
                           SUM(shift_ok * 100.0) / NULLIF(SUM(shift_total), 0) AS avg_quality_pct
                    FROM (
                        SELECT op1 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1, skiftraknare
                        UNION ALL
                        SELECT op2 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2, skiftraknare
                        UNION ALL
                        SELECT op3 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3, skiftraknare
                    ) t
                    GROUP BY op_id
                    ORDER BY (SUM(shift_ibc) * 0.6 + SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) * 0.4) DESC
                    LIMIT 1
                ";
                $stmtOp = $this->pdo->prepare($opSQL);
                $stmtOp->execute([$firstDay, $lastDay, $firstDay, $lastDay, $firstDay, $lastDay]);
                $opRow = $stmtOp->fetch(PDO::FETCH_ASSOC);
                if ($opRow && $opRow['op_id']) {
                    // Hämta operatörens namn
                    $nameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ? LIMIT 1");
                    $nameStmt->execute([$opRow['op_id']]);
                    $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
                    $namn = $nameRow ? $nameRow['name'] : 'Okänd';
                    // Generera initialer från namn
                    $parts = explode(' ', trim($namn));
                    $initialer = '';
                    foreach ($parts as $p) {
                        if ($p !== '') $initialer .= strtoupper(substr($p, 0, 1));
                    }
                    $initialer = substr($initialer, 0, 3);
                    $opOfMonth = [
                        'op_id'           => (int)$opRow['op_id'],
                        'namn'            => $namn,
                        'initialer'       => $initialer,
                        'total_ibc'       => (int)($opRow['total_ibc'] ?? 0),
                        'avg_ibc_per_h'   => round((float)($opRow['avg_ibc_per_h'] ?? 0), 1),
                        'avg_quality_pct' => round((float)($opRow['avg_quality_pct'] ?? 0), 1),
                    ];
                }
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::getMonthCompare: operatör av månaden fel: ' . $e->getMessage());
            }

            // Full operatörsranking (topp 10) med poäng
            $operatorRanking = [];
            try {
                $firstDay = $monthParam . '-01';
                $lastDay  = date('Y-m-t', strtotime($firstDay));
                $rankSQL = "
                    SELECT op_id,
                           COUNT(DISTINCT skiftraknare) AS shifts,
                           SUM(shift_ibc) AS total_ibc,
                           SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) AS avg_ibc_per_h,
                           SUM(shift_ok * 100.0) / NULLIF(SUM(shift_total), 0) AS avg_quality_pct
                    FROM (
                        SELECT op1 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1, skiftraknare
                        UNION ALL
                        SELECT op2 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2, skiftraknare
                        UNION ALL
                        SELECT op3 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3, skiftraknare
                    ) t
                    GROUP BY op_id
                    ORDER BY (SUM(shift_ibc) * 0.6 + SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) * 0.4) DESC
                    LIMIT 10
                ";
                $stmtRank = $this->pdo->prepare($rankSQL);
                $stmtRank->execute([$firstDay, $lastDay, $firstDay, $lastDay, $firstDay, $lastDay]);
                $rankRows = $stmtRank->fetchAll(PDO::FETCH_ASSOC);
                // Batch-hämta operatörsnamn istället för en query per rad
                $opIds2 = array_column($rankRows, 'op_id');
                $opNameMap2 = [];
                if (!empty($opIds2)) {
                    $ph2 = implode(',', array_fill(0, count($opIds2), '?'));
                    $ns2 = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ($ph2)");
                    $ns2->execute($opIds2);
                    foreach ($ns2->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                        $opNameMap2[(int)$nr['number']] = $nr['name'];
                    }
                }
                foreach ($rankRows as $rr) {
                    $opNamn = $opNameMap2[(int)$rr['op_id']] ?? 'Okänd';
                    $parts2 = explode(' ', trim($opNamn));
                    $init2 = '';
                    foreach ($parts2 as $p2) {
                        if ($p2 !== '') $init2 .= strtoupper(substr($p2, 0, 1));
                    }
                    $ibcH = (float)($rr['avg_ibc_per_h'] ?? 0);
                    $qualP = (float)($rr['avg_quality_pct'] ?? 0);
                    $totalIbc = (int)($rr['total_ibc'] ?? 0);
                    $score = round($totalIbc * 0.6 + $ibcH * 100 * 0.25 + $qualP * 0.15, 1);
                    $operatorRanking[] = [
                        'op_id'           => (int)$rr['op_id'],
                        'namn'            => $opNamn,
                        'initialer'       => substr($init2, 0, 3),
                        'shifts'          => (int)($rr['shifts'] ?? 0),
                        'total_ibc'       => $totalIbc,
                        'avg_ibc_per_h'   => round($ibcH, 1),
                        'avg_quality_pct' => round($qualP, 1),
                        'score'           => $score,
                    ];
                }
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::getMonthCompare: operatörsranking fel: ' . $e->getMessage());
            }

            // Antal operatörer på bästa dagen
            $bestDayData = $thisMonthData['best_day'];
            try {
                if ($bestDayData) {
                    $bdStmt = $this->pdo->prepare("
                        SELECT COUNT(DISTINCT op_id) AS op_count FROM (
                            SELECT DISTINCT op1 AS op_id FROM rebotling_ibc WHERE DATE(datum) = ? AND op1 IS NOT NULL AND op1 > 0
                            UNION SELECT DISTINCT op2 FROM rebotling_ibc WHERE DATE(datum) = ? AND op2 IS NOT NULL AND op2 > 0
                            UNION SELECT DISTINCT op3 FROM rebotling_ibc WHERE DATE(datum) = ? AND op3 IS NOT NULL AND op3 > 0
                        ) ops
                    ");
                    $bdDate = $bestDayData['datum'];
                    $bdStmt->execute([$bdDate, $bdDate, $bdDate]);
                    $bdRow = $bdStmt->fetch(PDO::FETCH_ASSOC);
                    $bestDayData['operator_count'] = (int)($bdRow['op_count'] ?? 0);
                }
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::getMonthCompare: best day operators fel: ' . $e->getMessage());
            }

            echo json_encode([
                'success'            => true,
                'month'              => $monthParam,
                'prev_month'         => $prevMonth,
                'this_month'         => $thisMonthData,
                'prev_month_data'    => $prevMonthData,
                'diff'               => [
                    'total_ibc_pct'          => $diffIbcPct,
                    'avg_ibc_per_day_pct'    => $diffAvgIbcPerDayPct,
                    'avg_oee_pct_diff'       => $diffOee,
                    'avg_quality_pct_diff'   => $diffQuality,
                ],
                'operator_of_month'  => $opOfMonth,
                'operator_ranking'   => $operatorRanking,
                'best_day'           => $bestDayData,
                'worst_day'          => $thisMonthData['worst_day'],
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getMonthCompare: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta jämförelsedata'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Månadsrapport
    // GET ?action=rebotling&run=monthly-report&month=YYYY-MM
    // =========================================================

    public function getMonthlyReport() {
        try {
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = date('Y-m');
            }

            // Bygg mennesklig månadsrubrik (svenska)
            $monthNames = [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Mars',
                '04' => 'April',   '05' => 'Maj',       '06' => 'Juni',
                '07' => 'Juli',    '08' => 'Augusti',   '09' => 'September',
                '10' => 'Oktober', '11' => 'November',  '12' => 'December',
            ];
            [$year, $mon] = explode('-', $month);
            $monthLabel = ($monthNames[$mon] ?? $mon) . ' ' . $year;

            // Dagsmål från rebotling_settings
            $dailyGoal = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['rebotling_target'])) {
                    $dailyGoal = (int)$sr['rebotling_target'];
                }
            } catch (Exception $e) {
                error_log('RebotlingAnalyticsController::getMonthlyReport: kunde inte läsa dagsmål: ' . $e->getMessage());
            }

            // Antal kalenderdagar i månaden
            $activeDays = (int)date('t', strtotime($month . '-01'));
            // Månadsmål baserat på dagsmål * antal produktionsdagar (alla dagar som inte är lördag/söndag)
            $workdays = 0;
            for ($d = 1; $d <= $activeDays; $d++) {
                $dow = (int)date('N', strtotime(sprintf('%s-%02d', $month, $d)));
                if ($dow < 6) $workdays++;
            }
            $monthGoal = $dailyGoal * $workdays;

            // ---- Per-skift-subquery som bas ----
            $perShiftSQL = "
                SELECT
                    DATE(datum)                                                             AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))                                               AS shift_ibc,
                    MAX(COALESCE(ibc_ej_ok, 0))                                            AS shift_ej_ok,
                    ROUND(MAX(COALESCE(ibc_ok,0))*100.0 /
                        NULLIF(MAX(COALESCE(ibc_ok,0))+MAX(COALESCE(ibc_ej_ok,0)),0),1)   AS shift_quality,
                    MAX(COALESCE(runtime_plc, 0))                                          AS shift_runtime,
                    MAX(COALESCE(rasttime, 0))                                             AS shift_rast
                FROM rebotling_ibc
                WHERE DATE_FORMAT(datum,'%Y-%m') = ?
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ";

            // ---- Summary ----
            $summarySQL = "
                SELECT
                    COUNT(DISTINCT dag)                   AS production_days,
                    SUM(shift_ibc)                        AS ibc_total,
                    ROUND(AVG(shift_quality),1)           AS avg_quality,
                    ROUND(SUM(shift_runtime)/60.0,1)      AS total_runtime_hours,
                    ROUND(SUM(shift_rast)/60.0,1)         AS total_stoppage_hours,
                    ROUND(AVG(shift_ibc),1)               AS avg_ibc_per_shift,
                    SUM(shift_ibc+shift_ej_ok)            AS total_ibc_all
                FROM ({$perShiftSQL}) AS per_shift
            ";
            $stmt = $this->pdo->prepare($summarySQL);
            $stmt->execute([$month]);
            $sumRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $ibcTotal          = (int)($sumRow['ibc_total'] ?? 0);
            $productionDays    = (int)($sumRow['production_days'] ?? 0);
            $avgQuality        = (float)($sumRow['avg_quality'] ?? 0);
            $totalRuntimeHours = (float)($sumRow['total_runtime_hours'] ?? 0);
            $totalStoppageHours= (float)($sumRow['total_stoppage_hours'] ?? 0);
            $goalPct           = $monthGoal > 0 ? round($ibcTotal * 100.0 / $monthGoal, 1) : 0;
            $avgIbcPerDay      = $productionDays > 0 ? round($ibcTotal / $productionDays, 1) : 0;

            // ---- OEE per dag (aggregerat) ----
            $oeeSQL = "
                SELECT
                    dag,
                    SUM(shift_ibc)      AS ibc_ok,
                    SUM(shift_ej_ok)    AS ibc_ej_ok,
                    SUM(shift_runtime)  AS runtime_min,
                    SUM(shift_rast)     AS rast_min
                FROM ({$perShiftSQL}) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ";
            $stmt = $this->pdo->prepare($oeeSQL);
            $stmt->execute([$month]);
            $oeeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $idealRatePerMin = 15.0 / 60.0;
            $dailyProduction = [];
            $oeeSum = 0;
            $oeeDays = 0;
            $bestDay  = null;
            $worstDay = null;

            foreach ($oeeRows as $r) {
                $ibcOk   = (float)$r['ibc_ok'];
                $ibcEjOk = (float)$r['ibc_ej_ok'];
                $total   = $ibcOk + $ibcEjOk;
                $opMin   = max((float)$r['runtime_min'], 1);
                $planMin = max($opMin + (float)$r['rast_min'], 1);

                $avail = min($opMin / $planMin, 1.0);
                $perf  = min(($total / $opMin) / $idealRatePerMin, 1.0);
                $qual  = $total > 0 ? $ibcOk / $total : 0;
                $oee   = round($avail * $perf * $qual * 100, 1);
                $qualPct = $total > 0 ? round($ibcOk / $total * 100, 1) : 0;

                $dailyProduction[] = [
                    'date'    => $r['dag'],
                    'ibc'     => (int)$ibcOk,
                    'quality' => $qualPct,
                    'oee'     => $oee,
                ];

                if ($ibcOk > 0) {
                    $oeeSum += $oee;
                    $oeeDays++;

                    if ($bestDay === null || $ibcOk > $bestDay['ibc']) {
                        $bestDay = ['date' => $r['dag'], 'ibc' => (int)$ibcOk, 'quality' => $qualPct];
                    }
                    if ($worstDay === null || $ibcOk < $worstDay['ibc']) {
                        $worstDay = ['date' => $r['dag'], 'ibc' => (int)$ibcOk, 'quality' => $qualPct];
                    }
                }
            }

            $avgOee = $oeeDays > 0 ? round($oeeSum / $oeeDays, 1) : 0;

            // ---- Veckosammanfattning ----
            // Använd ISO-år (date('o')) för att hantera år-gränser korrekt
            // (t.ex. dec 30 kan tillhöra V01 nästa år)
            $weekMap = [];
            foreach ($dailyProduction as $day) {
                $ts  = strtotime($day['date']);
                $isoYear = (int)date('o', $ts);
                $wk  = $isoYear . '-V' . (int)date('W', $ts);
                if (!isset($weekMap[$wk])) {
                    $weekMap[$wk] = ['ibc' => 0, 'quality_sum' => 0, 'oee_sum' => 0, 'days' => 0];
                }
                $weekMap[$wk]['ibc']         += $day['ibc'];
                $weekMap[$wk]['quality_sum'] += $day['quality'];
                $weekMap[$wk]['oee_sum']     += $day['oee'];
                $weekMap[$wk]['days']++;
            }
            $weekSummary = [];
            foreach ($weekMap as $wk => $wd) {
                $days = max($wd['days'], 1);
                // Visa kort etikett "V1" men behåll årskvalificerad nyckel internt
                $shortLabel = preg_replace('/^\d{4}-/', '', $wk);
                $weekSummary[] = [
                    'week'        => $shortLabel,
                    'ibc'         => $wd['ibc'],
                    'avg_quality' => round($wd['quality_sum'] / $days, 1),
                    'avg_oee'     => round($wd['oee_sum'] / $days, 1),
                ];
            }

            // ---- Operatörsranking för månaden ----
            $opSQL = "
                SELECT
                    o.number        AS number,
                    o.name          AS name,
                    SUM(sub.ibc_ok)       AS ibc_ok,
                    SUM(sub.totalt)       AS totalt,
                    SUM(sub.drifttid)     AS drifttid,
                    COUNT(sub.skift_id)   AS shifts
                FROM (
                    SELECT s.id AS skift_id, s.op1 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE DATE_FORMAT(s.datum,'%Y-%m') = ?
                      AND s.op1 IS NOT NULL
                    UNION ALL
                    SELECT s.id AS skift_id, s.op2 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE DATE_FORMAT(s.datum,'%Y-%m') = ?
                      AND s.op2 IS NOT NULL
                    UNION ALL
                    SELECT s.id AS skift_id, s.op3 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE DATE_FORMAT(s.datum,'%Y-%m') = ?
                      AND s.op3 IS NOT NULL
                ) sub
                JOIN operators o ON o.number = sub.op_num
                GROUP BY o.number, o.name
                ORDER BY (SUM(sub.ibc_ok) / GREATEST(SUM(sub.drifttid)/60.0, 0.01)) DESC
                LIMIT 20
            ";
            $stmt = $this->pdo->prepare($opSQL);
            $stmt->execute([$month, $month, $month]);
            $opRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $operatorRanking = [];
            foreach ($opRows as $row) {
                $ibcOk   = (int)($row['ibc_ok']  ?? 0);
                $totalt  = (int)($row['totalt']   ?? 0);
                $drMin   = (int)($row['drifttid'] ?? 0);
                $ibcPerH = $drMin > 0 ? round($ibcOk / ($drMin / 60.0), 1) : null;
                $qualPct = $totalt > 0 ? round($ibcOk / $totalt * 100, 1) : null;
                $operatorRanking[] = [
                    'name'             => $row['name'],
                    'number'           => (int)$row['number'],
                    'shifts'           => (int)$row['shifts'],
                    'ibc_ok'           => $ibcOk,
                    'avg_ibc_per_hour' => $ibcPerH,
                    'avg_quality'      => $qualPct,
                ];
            }

            // ---- Bästa & sämsta vecka (baserat på IBC) ----
            $bastaVecka  = null;
            $samstaVecka = null;
            foreach ($weekSummary as $wk) {
                if ($bastaVecka === null || $wk['ibc'] > $bastaVecka['ibc']) {
                    $bastaVecka = ['week' => $wk['week'], 'ibc' => $wk['ibc'], 'avg_oee' => $wk['avg_oee']];
                }
                if ($samstaVecka === null || $wk['ibc'] < $samstaVecka['ibc']) {
                    $samstaVecka = ['week' => $wk['week'], 'ibc' => $wk['ibc'], 'avg_oee' => $wk['avg_oee']];
                }
            }

            // ---- OEE-trend (daglig OEE% för linjegraf) ----
            $oeeTrend = array_map(function($d) {
                return ['date' => $d['date'], 'oee' => $d['oee']];
            }, $dailyProduction);

            // ---- Topp-3 operatörer ----
            $topOperatorer = array_map(function($op) {
                return [
                    'namn'      => $op['name'],
                    'ibc_total' => $op['ibc_ok'],
                ];
            }, array_slice($operatorRanking, 0, 3));

            // ---- Total stilleståndstid i minuter ----
            $totalStoppMin = (int)round($totalStoppageHours * 60);

            echo json_encode([
                'success'          => true,
                'month'            => $month,
                'month_label'      => $monthLabel,
                'summary'          => [
                    'ibc_total'            => $ibcTotal,
                    'ibc_goal'             => $monthGoal,
                    'goal_pct'             => $goalPct,
                    'avg_ibc_per_day'      => $avgIbcPerDay,
                    'active_days'          => $activeDays,
                    'production_days'      => $productionDays,
                    'avg_quality'          => $avgQuality,
                    'avg_oee'              => $avgOee,
                    'total_runtime_hours'  => $totalRuntimeHours,
                    'total_stoppage_hours' => $totalStoppageHours,
                    'total_stopp_min'      => $totalStoppMin,
                ],
                'best_day'         => $bestDay,
                'worst_day'        => $worstDay,
                'basta_vecka'      => $bastaVecka,
                'samsta_vecka'     => $samstaVecka,
                'oee_trend'        => $oeeTrend,
                'top_operatorer'   => $topOperatorer,
                'operator_ranking' => $operatorRanking,
                'daily_production' => $dailyProduction,
                'week_summary'     => $weekSummary,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getMonthlyReport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta månadsrapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ========== Prediktiv underhållsindikator ==========

    public function getMaintenanceIndicator() {
        try {
            $sql = "
                SELECT
                    YEAR(datum) AS yr,
                    WEEK(datum, 1) AS wk,
                    MIN(DATE(datum)) AS week_start,
                    SUM(shift_ibc) AS week_ibc,
                    SUM(shift_runtime) AS week_runtime,
                    ROUND(SUM(shift_runtime) / NULLIF(SUM(shift_ibc), 0), 2) AS avg_cycle_time
                FROM (
                    SELECT DATE(datum) AS datum, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok > 0
                      AND runtime_plc > 0
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY YEAR(datum), WEEK(datum, 1)
                ORDER BY yr ASC, wk ASC
            ";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode([
                    'success' => true,
                    'status'  => 'ok',
                    'message' => 'Inte tillräckligt med data för att beräkna underhållsindikator.',
                    'weeks'   => [],
                    'baseline_cycle_time' => null,
                    'current_cycle_time'  => null,
                    'trend_pct'           => null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Bygg veckoarray med etiketter
            $weeks = [];
            foreach ($rows as $row) {
                $weeks[] = [
                    'week_label'     => 'V' . (int)$row['wk'],
                    'week_start'     => $row['week_start'],
                    'avg_cycle_time' => $row['avg_cycle_time'] !== null ? (float)$row['avg_cycle_time'] : null,
                    'week_ibc'       => (int)$row['week_ibc'],
                ];
            }

            // Filtrera bort veckor utan cykeltidsdata
            $validWeeks = array_filter($weeks, function($w) { return $w['avg_cycle_time'] !== null && $w['avg_cycle_time'] > 0; });
            $validWeeks = array_values($validWeeks);

            if (count($validWeeks) < 2) {
                echo json_encode([
                    'success' => true,
                    'status'  => 'ok',
                    'message' => 'Inte tillräckligt med data för trendbedömning.',
                    'weeks'   => $weeks,
                    'baseline_cycle_time' => null,
                    'current_cycle_time'  => null,
                    'trend_pct'           => null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Baslinje = snitt av de 4 första veckorna (eller färre om data saknas)
            $baselineCount = min(4, count($validWeeks) - 1);
            $baselineSlice = array_slice($validWeeks, 0, $baselineCount);
            $baselineSum   = array_sum(array_column($baselineSlice, 'avg_cycle_time'));
            $baselineCycleTime = round($baselineSum / $baselineCount, 2);

            // Aktuell = senaste veckan
            $lastWeek = $validWeeks[count($validWeeks) - 1];
            $currentCycleTime = (float)$lastWeek['avg_cycle_time'];

            // Trend i procent
            $trendPct = $baselineCycleTime > 0
                ? round((($currentCycleTime - $baselineCycleTime) / $baselineCycleTime) * 100, 1)
                : 0;

            // Bestäm status
            if ($trendPct > 30) {
                $status  = 'danger';
                $message = 'Cykeltiden har ökat ' . abs($trendPct) . '% under de senaste veckorna — kontrollera maskinens slitage omgående (ventiler, pumpar, dubbar).';
            } elseif ($trendPct > 15) {
                $status  = 'warning';
                $message = 'Cykeltiden har ökat ' . abs($trendPct) . '% under de senaste veckorna — kontrollera maskinens slitage.';
            } else {
                $status  = 'ok';
                $message = 'Cykeltiden är stabil. Ingen ökande trend detekterad.';
            }

            echo json_encode([
                'success'             => true,
                'status'              => $status,
                'message'             => $message,
                'weeks'               => $weeks,
                'baseline_cycle_time' => $baselineCycleTime,
                'current_cycle_time'  => $currentCycleTime,
                'trend_pct'           => $trendPct,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getMaintenanceIndicator: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta underhållsindikator'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=annotations&start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * Returnerar händelseannotationer från tre källor:
     *  1. Lång stopptid (> 2 h per dag) — från rebotling_skiftrapport.rasttime
     *  2. Låg produktion (< 50 % av dagsmål) — från rebotling_skiftrapport
     *  3. Audit-log-händelser (om tabellen finns)
     *
     * Varje källa hanteras i ett eget try-catch, så övriga källor returneras
     * även om en källa misslyckas.
     */

    public function getAnnotations() {
        // Validera datumparametrar
        $start = trim($_GET['start'] ?? '');
        $end   = trim($_GET['end']   ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltiga datumparametrar'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // Validera att start <= end
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $annotations = [];

        // ----------------------------------------------------------------
        // Källa 1: Dagar med total rasttime > 120 min i rebotling_skiftrapport
        // ----------------------------------------------------------------
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)    AS event_date,
                    SUM(rasttime)  AS total_rast_min
                FROM rebotling_skiftrapport
                WHERE DATE(datum) BETWEEN :start AND :end
                GROUP BY DATE(datum)
                HAVING SUM(rasttime) > 120
                ORDER BY event_date
            ");
            $stmt->execute([':start' => $start, ':end' => $end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $hours = round($row['total_rast_min'] / 60, 1);
                $annotations[] = [
                    'date'      => $row['event_date'],
                    'type'      => 'stopp',
                    'label'     => 'Lång stopptid: ' . $hours . 'h',
                ];
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getAnnotations stopp-källa: ' . $e->getMessage());
        }

        // ----------------------------------------------------------------
        // Källa 2: Dagar med låg produktion (< 50 % av dagsmålet)
        // ----------------------------------------------------------------
        try {
            // Hämta dagsmål från rebotling_settings
            $halfGoal = 500; // fallback
            try {
                $sr = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['rebotling_target'])) {
                    $halfGoal = intval($sr['rebotling_target']) / 2;
                }
            } catch (Exception $e2) {
                error_log('RebotlingAnalyticsController::getAnnotations: kunde inte läsa rebotling_settings: ' . $e2->getMessage());
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)   AS event_date,
                    SUM(ibc_ok)   AS total_ibc
                FROM rebotling_skiftrapport
                WHERE DATE(datum) BETWEEN :start AND :end
                GROUP BY DATE(datum)
                HAVING SUM(ibc_ok) < :half_goal AND SUM(ibc_ok) > 0
                ORDER BY event_date
            ");
            $stmt->execute([':start' => $start, ':end' => $end, ':half_goal' => $halfGoal]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                // Undvik dubbelregistrering om datumet redan finns som stopp
                $alreadyExists = false;
                foreach ($annotations as $ann) {
                    if ($ann['date'] === $row['event_date']) {
                        $alreadyExists = true;
                        break;
                    }
                }
                if (!$alreadyExists) {
                    $annotations[] = [
                        'date'  => $row['event_date'],
                        'type'  => 'low_production',
                        'label' => 'Låg prod: ' . intval($row['total_ibc']) . ' IBC',
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getAnnotations low_production-källa: ' . $e->getMessage());
        }

        // ----------------------------------------------------------------
        // Källa 3: Audit-log (om tabellen finns)
        // ----------------------------------------------------------------
        try {
            // Kontrollera om tabellen existerar
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = 'audit_log'"
            )->fetchColumn();

            if ($check > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(created_at) AS event_date,
                        'audit'          AS event_type,
                        action           AS label
                    FROM audit_log
                    WHERE created_at BETWEEN :start AND :end
                      AND action IN ('create_operator', 'update_settings', 'approve_bonus')
                    ORDER BY created_at
                    LIMIT 5
                ");
                $stmt->execute([':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $auditLabels = [
                    'create_operator' => 'Ny operatör',
                    'update_settings' => 'Inställningar uppdaterade',
                    'approve_bonus'   => 'Bonus godkänd',
                ];
                foreach ($rows as $row) {
                    $annotations[] = [
                        'date'  => $row['event_date'],
                        'type'  => 'audit',
                        'label' => $auditLabels[$row['label']] ?? $row['label'],
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getAnnotations audit-källa: ' . $e->getMessage());
        }

        // Sortera på datum
        usort($annotations, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        echo json_encode(['success' => true, 'annotations' => $annotations], JSON_UNESCAPED_UNICODE);
    }

    // ----------------------------------------------------------------
    // Kvalitetstrendkort
    // GET ?action=rebotling&run=quality-trend&days=30
    // ----------------------------------------------------------------

    public function getQualityTrend() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        try {
            $stmt = $this->pdo->prepare("
                SELECT dag,
                       ROUND(SUM(ibc_ok) * 100.0 / NULLIF(SUM(ibc_totalt), 0), 1) AS quality_pct,
                       SUM(ibc_ok) AS ibc_ok,
                       SUM(ibc_totalt) AS ibc_totalt
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0)) AS ibc_totalt
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':days' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna 7-dagars rullande medelvärde
            $rolling = [];
            for ($i = 0; $i < count($rows); $i++) {
                $window = array_slice($rows, max(0, $i - 6), min(7, $i + 1));
                $validValues = array_filter(array_column($window, 'quality_pct'), function($v) { return $v !== null; });
                if (count($validValues) > 0) {
                    $avg = array_sum($validValues) / count($validValues);
                } else {
                    $avg = null;
                }
                $rolling[] = $avg !== null ? round($avg, 1) : null;
            }

            // Beräkna KPI-värden
            $validPcts = array_filter(array_column($rows, 'quality_pct'), function($v) { return $v !== null; });
            $avgQuality = count($validPcts) > 0 ? round(array_sum($validPcts) / count($validPcts), 1) : null;
            $minQuality = count($validPcts) > 0 ? round(min($validPcts), 1) : null;
            $maxQuality = count($validPcts) > 0 ? round(max($validPcts), 1) : null;

            // Trend: jämför snitt av sista 7 dagar mot snitt av perioden dessförinnan
            $trend = 'stable';
            if (count($rows) >= 14) {
                $last7 = array_slice($rows, -7);
                $prev7 = array_slice($rows, -14, 7);
                $last7vals = array_filter(array_column($last7, 'quality_pct'), function($v) { return $v !== null; });
                $prev7vals = array_filter(array_column($prev7, 'quality_pct'), function($v) { return $v !== null; });
                if (count($last7vals) > 0 && count($prev7vals) > 0) {
                    $lastAvg = array_sum($last7vals) / count($last7vals);
                    $prevAvg = array_sum($prev7vals) / count($prev7vals);
                    if ($lastAvg > $prevAvg + 0.5) $trend = 'up';
                    elseif ($lastAvg < $prevAvg - 0.5) $trend = 'down';
                }
            }

            // Bygg svar
            $days_data = [];
            foreach ($rows as $i => $row) {
                $days_data[] = [
                    'date'        => $row['dag'],
                    'quality_pct' => $row['quality_pct'] !== null ? (float)$row['quality_pct'] : null,
                    'rolling_avg' => $rolling[$i],
                    'ibc_ok'      => (int)$row['ibc_ok'],
                    'ibc_totalt'  => (int)$row['ibc_totalt'],
                ];
            }

            echo json_encode([
                'success' => true,
                'days'    => $days_data,
                'kpi'     => [
                    'avg'   => $avgQuality,
                    'min'   => $minQuality,
                    'max'   => $maxQuality,
                    'trend' => $trend,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getQualityTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av kvalitetstrend.'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ----------------------------------------------------------------
    // Waterfalldiagram OEE
    // GET ?action=rebotling&run=oee-waterfall&days=30
    // ----------------------------------------------------------------

    public function getOeeWaterfall() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(shift_runtime) AS total_runtime,
                       SUM(shift_rast) AS total_rast,
                       SUM(shift_ibc_ok) AS ibc_ok,
                       SUM(shift_ibc_ej_ok) AS ibc_ej_ok
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime,
                           MAX(COALESCE(rasttime, 0)) AS shift_rast,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':days' => $days]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $runtime     = (float)($row['total_runtime'] ?? 0);  // minuter
            $rast        = (float)($row['total_rast'] ?? 0);     // minuter
            $ibcOk       = (int)($row['ibc_ok'] ?? 0);
            $ibcEjOk     = (int)($row['ibc_ej_ok'] ?? 0);
            $ibcTotalt   = $ibcOk + $ibcEjOk;
            $available   = $runtime + $rast;                      // minuter

            // Tillgänglighet
            $availability = $available > 0 ? round($runtime / $available * 100, 1) : 0.0;

            // Kvalitet
            $quality = $ibcTotalt > 0 ? round($ibcOk / $ibcTotalt * 100, 1) : 0.0;

            // Prestanda: 15 IBC/h = standard => ideal_cycle = 60/15 = 4 min/IBC
            $idealCycleMin = 60.0 / 15.0; // 4 min per IBC
            $performance = 0.0;
            if ($runtime > 0) {
                $performance = round(($ibcOk * $idealCycleMin) / $runtime * 100, 1);
                if ($performance > 100) $performance = 100.0;
            }

            // OEE
            $oee = round($availability * $performance * $quality / 10000, 1);

            // Förluster
            $availabilityLoss = round(100 - $availability, 1);
            $performanceLoss  = round($availability - ($availability * $performance / 100), 1);
            $qualityLoss      = round(($availability * $performance / 100) - $oee, 1);

            echo json_encode([
                'success'           => true,
                'availability'      => $availability,
                'performance'       => $performance,
                'quality'           => $quality,
                'oee'               => $oee,
                'availability_loss' => $availabilityLoss,
                'performance_loss'  => $performanceLoss,
                'quality_loss'      => $qualityLoss,
                'runtime_h'         => round($runtime / 60, 1),
                'rast_h'            => round($rast / 60, 1),
                'ibc_ok'            => $ibcOk,
                'ibc_ej_ok'         => $ibcEjOk,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getOeeWaterfall: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av OEE-waterfall.'], JSON_UNESCAPED_UNICODE);
        }
    }


    public function getWeekdayStats() {
        $dagar = min(365, max(7, intval($_GET['dagar'] ?? 90)));

        // Svenska veckodagsnamn (MySQL returnerar engelska)
        $dagNamn = [1 => 'Söndag', 2 => 'Måndag', 3 => 'Tisdag', 4 => 'Onsdag', 5 => 'Torsdag', 6 => 'Fredag', 7 => 'Lördag'];

        try {
            // Aggregera per skift först, summera sedan per dag, gruppera slutligen per veckodag
            $stmt = $this->pdo->prepare("
                SELECT
                    DAYOFWEEK(dag)        AS veckodag_nr,
                    COUNT(DISTINCT dag)   AS antal_dagar,
                    ROUND(AVG(dag_ibc), 1)  AS snitt_ibc,
                    MAX(dag_ibc)            AS max_ibc,
                    MIN(dag_ibc)            AS min_ibc,
                    ROUND(AVG(dag_oee), 1)  AS snitt_oee
                FROM (
                    SELECT
                        dag,
                        SUM(shift_ibc_ok)    AS dag_ibc,
                        SUM(shift_runtime)   AS dag_runtime,
                        SUM(shift_rast)      AS dag_rast,
                        SUM(shift_ibc_ej_ok) AS dag_ibc_ej_ok,
                        CASE
                            WHEN SUM(shift_runtime) > 0 THEN
                                ROUND(
                                    LEAST(SUM(shift_runtime) / GREATEST(SUM(shift_runtime) + SUM(shift_rast), 1), 1.0)
                                    * LEAST((SUM(shift_ibc_ok) + SUM(shift_ibc_ej_ok)) / SUM(shift_runtime) / (15.0 / 60.0), 1.0)
                                    * CASE WHEN (SUM(shift_ibc_ok) + SUM(shift_ibc_ej_ok)) > 0
                                           THEN SUM(shift_ibc_ok) / (SUM(shift_ibc_ok) + SUM(shift_ibc_ej_ok))
                                           ELSE 0 END
                                    * 100, 1)
                            ELSE NULL
                        END AS dag_oee
                    FROM (
                        SELECT
                            DATE(datum)                           AS dag,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0))           AS shift_ibc_ok,
                            MAX(COALESCE(ibc_ej_ok,  0))          AS shift_ibc_ej_ok,
                            MAX(COALESCE(runtime_plc,0))          AS shift_runtime,
                            MAX(COALESCE(rasttime,   0))          AS shift_rast
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                          AND skiftraknare IS NOT NULL
                          AND ibc_ok IS NOT NULL
                          AND ibc_ok > 0
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY dag
                ) AS per_dag
                CROSS JOIN (SELECT 0) AS dummy
                GROUP BY DAYOFWEEK(dag)
                ORDER BY veckodag_nr
            ");
            $stmt->execute([$dagar]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $veckodagar = [];
            foreach ($rows as $r) {
                $nr = (int)$r['veckodag_nr'];
                $namn = $dagNamn[$nr] ?? 'Okänd';

                // Beräkna OEE om det saknas (snitt_oee kan vara NULL om runtime är 0)
                $snittOee = $r['snitt_oee'] !== null ? (float)$r['snitt_oee'] : null;

                $veckodagar[] = [
                    'veckodag_nr' => $nr,
                    'namn'        => $namn,
                    'antal_dagar' => (int)$r['antal_dagar'],
                    'snitt_ibc'   => (float)$r['snitt_ibc'],
                    'snitt_oee'   => $snittOee,
                    'max_ibc'     => (int)$r['max_ibc'],
                    'min_ibc'     => (int)$r['min_ibc']
                ];
            }

            echo json_encode([
                'success'     => true,
                'veckodagar'  => $veckodagar,
                'dagar'       => $dagar
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getWeekdayStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodag-statistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ----------------------------------------------------------------
    // Produktionshändelse-annotationer
    // GET ?action=rebotling&run=events&start=YYYY-MM-DD&end=YYYY-MM-DD
    // ----------------------------------------------------------------


    public function getStoppageAnalysis(): void {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        // Kontrollera att tabellerna finns
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('stoppage_log','stoppage_reasons')"
            );
            $found = (int)$check->fetchColumn();
            if ($found < 2) {
                echo json_encode([
                    'success' => true,
                    'empty'   => true,
                    'reason'  => 'Tabellerna stoppage_log/stoppage_reasons finns inte än. Kör migreringsfil 2026-03-04_stoppage_log.sql.',
                    'by_day'  => [],
                    'by_category' => [],
                    'top_reasons' => [],
                    'total_events'   => 0,
                    'total_minutes'  => 0,
                    'days'    => $days
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getStoppageAnalysis check: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'empty'   => true,
                'reason'  => 'Kunde inte kontrollera tabeller.',
                'by_day'  => [],
                'by_category' => [],
                'top_reasons' => [],
                'total_events'   => 0,
                'total_minutes'  => 0,
                'days'    => $days
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Aggregering per dag och kategori
            $stmtDay = $this->pdo->prepare(
                "SELECT
                     DATE(sl.created_at)        AS dag,
                     sr.category,
                     sr.name                    AS reason_name,
                     COUNT(*)                   AS antal,
                     COALESCE(SUM(sl.duration_minutes), 0)  AS total_minuter,
                     COALESCE(AVG(sl.duration_minutes), 0)  AS snitt_minuter
                 FROM stoppage_log sl
                 JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE sl.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY DATE(sl.created_at), sr.category, sr.name
                 ORDER BY dag DESC, total_minuter DESC"
            );
            $stmtDay->execute([$days]);
            $byDay = $stmtDay->fetchAll(PDO::FETCH_ASSOC);

            // Aggregering per kategori
            $stmtCat = $this->pdo->prepare(
                "SELECT
                     sr.category,
                     COUNT(*)                              AS antal,
                     COALESCE(SUM(sl.duration_minutes), 0) AS total_min
                 FROM stoppage_log sl
                 JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE sl.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY sr.category
                 ORDER BY total_min DESC"
            );
            $stmtCat->execute([$days]);
            $byCategory = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            // Topplista per orsak (max 10)
            $stmtTop = $this->pdo->prepare(
                "SELECT
                     sr.name,
                     sr.category,
                     COUNT(*)                              AS antal,
                     COALESCE(SUM(sl.duration_minutes), 0) AS total_min,
                     COALESCE(AVG(sl.duration_minutes), 0) AS snitt_min
                 FROM stoppage_log sl
                 JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE sl.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY sr.name, sr.category
                 ORDER BY total_min DESC
                 LIMIT 10"
            );
            $stmtTop->execute([$days]);
            $topReasons = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            // Summering
            $stmtSum = $this->pdo->prepare(
                "SELECT COUNT(*) AS total_events,
                        COALESCE(SUM(duration_minutes), 0) AS total_minutes
                 FROM stoppage_log
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"
            );
            $stmtSum->execute([$days]);
            $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

            $totalEvents  = (int)($summary['total_events']  ?? 0);
            $totalMinutes = (float)($summary['total_minutes'] ?? 0);

            // Bygg daglig aggregering (total per dag, för diagram)
            $dagMap = [];
            foreach ($byDay as $row) {
                $dag = $row['dag'];
                if (!isset($dagMap[$dag])) {
                    $dagMap[$dag] = ['dag' => $dag, 'total_minuter' => 0, 'antal' => 0, 'kategorier' => []];
                }
                $dagMap[$dag]['total_minuter'] += (float)$row['total_minuter'];
                $dagMap[$dag]['antal']         += (int)$row['antal'];
                $cat = $row['category'];
                if (!isset($dagMap[$dag]['kategorier'][$cat])) $dagMap[$dag]['kategorier'][$cat] = 0;
                $dagMap[$dag]['kategorier'][$cat] += (float)$row['total_minuter'];
            }
            $dagList = array_values($dagMap);

            // Konvertera numeriska strängar
            foreach ($byCategory as &$r) {
                $r['antal']     = (int)$r['antal'];
                $r['total_min'] = (float)$r['total_min'];
            }
            unset($r);
            foreach ($topReasons as &$r) {
                $r['antal']     = (int)$r['antal'];
                $r['total_min'] = (float)$r['total_min'];
                $r['snitt_min'] = round((float)$r['snitt_min'], 1);
            }
            unset($r);
            foreach ($dagList as &$r) {
                $r['total_minuter'] = round($r['total_minuter'], 1);
            }
            unset($r);

            $empty = ($totalEvents === 0);

            echo json_encode([
                'success'      => true,
                'empty'        => $empty,
                'by_day'       => $dagList,
                'by_category'  => $byCategory,
                'top_reasons'  => $topReasons,
                'total_events' => $totalEvents,
                'total_minutes'=> round($totalMinutes, 1),
                'days'         => $days
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getStoppageAnalysis: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success'      => false,
                'empty'        => true,
                'reason'       => 'Databasfel vid hämtning av stoppdata.',
                'by_day'       => [],
                'by_category'  => [],
                'top_reasons'  => [],
                'total_events' => 0,
                'total_minutes'=> 0,
                'days'         => $days
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST ?action=rebotling&run=delete-event (kräver admin)

    public function getShiftTrend() {
        $datum = trim($_GET['datum'] ?? '');
        $skift = intval($_GET['skift'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || $skift <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltiga parametrar'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta rådata per timme för detta skift — kumulativa max/min-värden
            $stmt = $this->pdo->prepare('
                SELECT
                    HOUR(datum) AS timme,
                    MAX(ibc_ok)  AS ibc_max,
                    MIN(ibc_ok)  AS ibc_min,
                    MAX(runtime_plc) AS runtime_cumulative
                FROM rebotling_ibc
                WHERE DATE(datum) = ?
                  AND skiftraknare = ?
                GROUP BY HOUR(datum)
                ORDER BY timme
            ');
            $stmt->execute([$datum, $skift]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna delta per timme (kumulativa fält)
            $trend = [];
            $prevIbc = null;
            $accIbc  = 0;
            foreach ($rows as $r) {
                $delta = ($prevIbc === null) ? 0 : max(0, (int)$r['ibc_max'] - (int)$prevIbc);
                $accIbc += $delta;
                $trend[] = [
                    'timme'          => (int)$r['timme'],
                    'ibc_ok'         => $delta,
                    'ackumulerat_ibc'=> $accIbc,
                    'takt_ibc_per_h' => $delta, // delta IS takt over 1h
                    'runtime'        => (int)$r['runtime_cumulative'],
                ];
                $prevIbc = (int)$r['ibc_max'];
            }

            // Genomsnittsprofil: samma veckodag de senaste 28 dagarna, samma skift-nummer
            $stmt2 = $this->pdo->prepare('
                SELECT HOUR(datum) AS timme, AVG(delta_ok) AS snitt_ibc_timma
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        HOUR(datum) AS h,
                        skiftraknare,
                        MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok
                    FROM rebotling_ibc
                    WHERE DAYOFWEEK(datum) = DAYOFWEEK(?)
                      AND skiftraknare = ?
                      AND datum >= DATE_SUB(?, INTERVAL 28 DAY)
                      AND DATE(datum) != ?
                    GROUP BY DATE(datum), HOUR(datum), skiftraknare
                ) x
                GROUP BY timme
                ORDER BY timme
            ');
            $stmt2->execute([$datum, $skift, $datum, $datum]);
            $avgRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $avgProfile = [];
            foreach ($avgRows as $ar) {
                $avgProfile[] = [
                    'timme'           => (int)$ar['timme'],
                    'snitt_ibc_timma' => round((float)$ar['snitt_ibc_timma'], 1),
                ];
            }

            // Beräkna totala KPI-värden
            $totalIbc   = $accIbc;
            $hours      = count($trend) > 0 ? count($trend) : 1;
            $snitttakt  = $hours > 0 ? round($totalIbc / $hours, 1) : 0;

            $avgTotal     = array_sum(array_column($avgProfile, 'snitt_ibc_timma'));
            $avgHours     = count($avgProfile) > 0 ? count($avgProfile) : 1;
            $snitttaktAvg = $avgHours > 0 ? round($avgTotal / $avgHours, 1) : 0;

            $diffPct = ($snitttaktAvg > 0)
                ? round((($snitttakt - $snitttaktAvg) / $snitttaktAvg) * 100, 1)
                : null;

            echo json_encode([
                'success'      => true,
                'trend'        => $trend,
                'avg_profile'  => $avgProfile,
                'kpi' => [
                    'snitt_ibc_per_h'     => $snitttakt,
                    'snitt_ibc_per_h_avg' => $snitttaktAvg,
                    'diff_pct'            => $diffPct,
                    'total_ibc'           => $totalIbc,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getShiftTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }


    // GET ?action=rebotling&run=pareto-stoppage&days=30
    // Returnerar Pareto-analys (80/20) av stopporsaker.

    public function getParetoStoppage(): void {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        // Kontrollera att tabellerna finns
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('stoppage_log','stoppage_reasons')"
            );
            $found = (int)$check->fetchColumn();
            if ($found < 2) {
                echo json_encode([
                    'success'       => true,
                    'empty'         => true,
                    'reason'        => 'Tabellerna stoppage_log/stoppage_reasons finns inte än.',
                    'items'         => [],
                    'period_days'   => $days,
                    'total_stopp'   => 0,
                    'total_minuter' => 0
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getParetoStoppage check: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success'       => false,
                'empty'         => true,
                'reason'        => 'Kunde inte kontrollera tabeller.',
                'items'         => [],
                'period_days'   => $days,
                'total_stopp'   => 0,
                'total_minuter' => 0
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                   COALESCE(r.name, s.reason_free) AS orsak,
                   COALESCE(r.category, 'övrigt')  AS kategori,
                   COUNT(*)                         AS antal_stopp,
                   COALESCE(SUM(s.duration_minutes), 0) AS total_minuter,
                   COALESCE(AVG(s.duration_minutes), 0) AS snitt_minuter
                 FROM stoppage_log s
                 LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                 WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND s.deleted_at IS NULL
                 GROUP BY r.id, COALESCE(r.name, s.reason_free)
                 ORDER BY total_minuter DESC
                 LIMIT 20"
            );
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Summering
            $stmtSum = $this->pdo->prepare(
                "SELECT COUNT(*) AS total_stopp,
                        COALESCE(SUM(duration_minutes), 0) AS total_minuter
                 FROM stoppage_log
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND deleted_at IS NULL"
            );
            $stmtSum->execute([$days]);
            $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

            $totalStopp   = (int)($summary['total_stopp']   ?? 0);
            $totalMinuter = (float)($summary['total_minuter'] ?? 0);

            // Beräkna kumulativ procent
            $items = [];
            $kumulativMin = 0.0;
            foreach ($rows as $row) {
                $min = (float)$row['total_minuter'];
                $kumulativMin += $min;
                $pctAvTotal   = $totalMinuter > 0 ? round($min / $totalMinuter * 100, 1) : 0;
                $kumulativPct = $totalMinuter > 0 ? round($kumulativMin / $totalMinuter * 100, 1) : 0;
                $items[] = [
                    'orsak'         => $row['orsak'] ?? 'Okänd',
                    'kategori'      => $row['kategori'],
                    'antal_stopp'   => (int)$row['antal_stopp'],
                    'total_minuter' => round($min, 1),
                    'snitt_minuter' => round((float)$row['snitt_minuter'], 1),
                    'pct_av_total'  => $pctAvTotal,
                    'kumulativ_pct' => $kumulativPct
                ];
            }

            $empty = ($totalStopp === 0);

            echo json_encode([
                'success'       => true,
                'empty'         => $empty,
                'items'         => $items,
                'period_days'   => $days,
                'total_stopp'   => $totalStopp,
                'total_minuter' => round($totalMinuter, 1)
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getParetoStoppage: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success'       => false,
                'empty'         => true,
                'reason'        => 'Databasfel vid hämtning av paretodata.',
                'items'         => [],
                'period_days'   => $days,
                'total_stopp'   => 0,
                'total_minuter' => 0
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    // GET ?action=rebotling&run=stop-cause-drilldown&cause=...&days=30
    // Returnerar drill-down-data för en specifik stopporsak.

    public function getStopCauseDrilldown(): void {
        $cause = trim($_GET['cause'] ?? '');
        $days  = max(1, min(365, intval($_GET['days'] ?? 30)));

        if ($cause === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Parameter "cause" saknas.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Kontrollera att tabellerna finns
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('stoppage_log','stoppage_reasons')"
            );
            if ((int)$check->fetchColumn() < 2) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tabellerna finns inte.'], JSON_UNESCAPED_UNICODE);
                return;
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getStopCauseDrilldown check: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta alla enskilda stopp för denna orsak
            $stmt = $this->pdo->prepare(
                "SELECT
                   s.id,
                   s.start_time,
                   s.end_time,
                   COALESCE(s.duration_minutes, 0) AS duration_minutes,
                   s.comment,
                   COALESCE(u.username, 'Okänd') AS operator,
                   COALESCE(r.name, s.reason_free) AS orsak,
                   COALESCE(r.category, 'övrigt') AS kategori
                 FROM stoppage_log s
                 LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE COALESCE(r.name, s.reason_free) = ?
                   AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND (s.deleted_at IS NULL)
                 ORDER BY s.start_time DESC"
            );
            $stmt->execute([$cause, $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Summering
            $totalMinutes = 0;
            $count = count($rows);
            $byOperator = [];
            $byDay = [];
            $stops = [];

            foreach ($rows as $row) {
                $min = (float)$row['duration_minutes'];
                $totalMinutes += $min;
                $op = $row['operator'];
                $day = substr($row['start_time'], 0, 10);

                // Per operatör
                if (!isset($byOperator[$op])) {
                    $byOperator[$op] = ['operator' => $op, 'count' => 0, 'total_minutes' => 0];
                }
                $byOperator[$op]['count']++;
                $byOperator[$op]['total_minutes'] += $min;

                // Per dag
                if (!isset($byDay[$day])) {
                    $byDay[$day] = ['date' => $day, 'count' => 0, 'minutes' => 0];
                }
                $byDay[$day]['count']++;
                $byDay[$day]['minutes'] += $min;

                // Enskilt stopp
                $stops[] = [
                    'id'       => (int)$row['id'],
                    'date'     => $day,
                    'time'     => substr($row['start_time'], 11, 5),
                    'end_time' => $row['end_time'] ? substr($row['end_time'], 11, 5) : null,
                    'minutes'  => round($min, 1),
                    'operator' => $op,
                    'comment'  => $row['comment'] ?? ''
                ];
            }

            // Sortera by_operator fallande
            $byOperatorArr = array_values($byOperator);
            usort($byOperatorArr, fn($a, $b) => $b['total_minutes'] <=> $a['total_minutes']);
            foreach ($byOperatorArr as &$o) {
                $o['total_minutes'] = round($o['total_minutes'], 1);
            }
            unset($o);

            // Sortera by_day stigande
            $byDayArr = array_values($byDay);
            usort($byDayArr, fn($a, $b) => $a['date'] <=> $b['date']);
            foreach ($byDayArr as &$d) {
                $d['minutes'] = round($d['minutes'], 1);
            }
            unset($d);

            $avgMinutes = $count > 0 ? round($totalMinutes / $count, 1) : 0;

            echo json_encode([
                'success' => true,
                'cause'   => $cause,
                'summary' => [
                    'total_minutes' => round($totalMinutes, 1),
                    'total_hours'   => round($totalMinutes / 60, 2),
                    'count'         => $count,
                    'avg_minutes'   => $avgMinutes
                ],
                'by_operator' => $byOperatorArr,
                'by_day'      => $byDayArr,
                'stops'       => $stops
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getStopCauseDrilldown: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid drill-down.'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // GET ?action=rebotling&run=all-lines-status
    //
    // Returnerar live-status för alla 4 produktionslinjer.
    // Rebotling: hämtar senaste data från rebotling_ibc.
    // Övriga linjer: kontrollerar om settings-tabellen finns.
    // Kräver admin-session.
    // ============================================================

    public function getSkiftrapportList() {
        try {
            $operator = trim($_GET['operator'] ?? '');
            $limit  = max(1, min(500, (int)($_GET['limit'] ?? 100)));
            $offset = max(0, min(100000, (int)($_GET['offset'] ?? 0)));

            $where = '';
            $params = [];

            if ($operator !== '') {
                $where = 'WHERE (o1.name = :op1 OR o2.name = :op2 OR o3.name = :op3)';
                $params['op1'] = $operator;
                $params['op2'] = $operator;
                $params['op3'] = $operator;
            }

            // Räkna totalt antal rader (för pagination)
            $countSql = "
                SELECT COUNT(*) FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                {$where}
            ";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRows = (int)$countStmt->fetchColumn();

            // Hämta rader
            $sql = "
                SELECT
                    s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt,
                    s.drifttid, s.rasttime, s.lopnummer, s.skiftraknare,
                    s.op1, s.op2, s.op3,
                    s.created_at,
                    u.username AS user_name,
                    p.name AS product_name,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                {$where}
                ORDER BY s.datum DESC, s.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // KPI-sammanfattning
            $kpiSql = "
                SELECT
                    COALESCE(SUM(s.ibc_ok), 0)   AS total_ibc_ok,
                    COALESCE(SUM(s.totalt), 0)    AS total_totalt,
                    COALESCE(SUM(s.drifttid), 0)  AS total_drifttid,
                    COUNT(*)                       AS antal_skift
                FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                {$where}
            ";
            $kpiStmt = $this->pdo->prepare($kpiSql);
            $kpiStmt->execute($params);
            $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

            $antalSkift = (int)($kpi['antal_skift'] ?? 0);
            $totalIbc   = (int)($kpi['total_ibc_ok'] ?? 0);
            $snittPerSkift = $antalSkift > 0 ? round($totalIbc / $antalSkift, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => $totalRows,
                'kpi' => [
                    'total_ibc'      => $totalIbc,
                    'snitt_per_skift' => $snittPerSkift,
                    'antal_skift'    => $antalSkift,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getSkiftrapportList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftrapporter'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Unika operatörer i skiftrapporter
    // GET ?action=rebotling&run=skiftrapport-operators
    // =========================================================

    public function getSkiftrapportOperators() {
        try {
            $sql = "
                SELECT DISTINCT name FROM (
                    SELECT o.name FROM rebotling_skiftrapport s
                    INNER JOIN operators o ON o.number = s.op1
                    WHERE s.op1 IS NOT NULL
                    UNION
                    SELECT o.name FROM rebotling_skiftrapport s
                    INNER JOIN operators o ON o.number = s.op2
                    WHERE s.op2 IS NOT NULL
                    UNION
                    SELECT o.name FROM rebotling_skiftrapport s
                    INNER JOIN operators o ON o.number = s.op3
                    WHERE s.op3 IS NOT NULL
                ) AS ops
                WHERE name IS NOT NULL AND name != ''
                ORDER BY name
            ";
            $stmt = $this->pdo->query($sql);
            $operators = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success' => true,
                'operators' => $operators,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getSkiftrapportOperators: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörslista'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skiftsammanfattning — detaljvy per specifikt skift
    // GET ?action=rebotling&run=shift-summary&date=YYYY-MM-DD&shift=1|2|3
    // =========================================================

    public function getShiftSummary() {
        $date  = $_GET['date']  ?? '';
        $shift = (int)($_GET['shift'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $shift < 1 || $shift > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum eller skiftnummer (1-3)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta skiftrapporter för detta datum OCH specifikt skift
            // Skiftnummer bestäms via rebotling_ibc: HOUR(MIN(datum)) → skift 1/2/3
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt,
                    s.drifttid, s.rasttime, s.lopnummer, s.skiftraknare,
                    s.op1, s.op2, s.op3,
                    s.created_at,
                    u.username AS user_name,
                    p.name AS product_name,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE DATE(s.datum) = :date
                  AND s.skiftraknare IS NOT NULL
                  AND (
                    SELECT CASE
                        WHEN HOUR(MIN(i.datum)) BETWEEN 6 AND 13 THEN 1
                        WHEN HOUR(MIN(i.datum)) BETWEEN 14 AND 21 THEN 2
                        ELSE 3
                    END
                    FROM rebotling_ibc i WHERE i.skiftraknare = s.skiftraknare
                  ) = :shift
                ORDER BY s.id
            ");
            $stmt->execute(['date' => $date, 'shift' => $shift]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aggregera KPI:er
            $totalIbcOk  = 0;
            $totalBurEj  = 0;
            $totalIbcEj  = 0;
            $totalTotalt = 0;
            $totalDrift  = 0;
            $totalRast   = 0;
            $operatorNames = [];
            $products    = [];

            foreach ($rows as $r) {
                $totalIbcOk  += (int)($r['ibc_ok']    ?? 0);
                $totalBurEj  += (int)($r['bur_ej_ok']  ?? 0);
                $totalIbcEj  += (int)($r['ibc_ej_ok']  ?? 0);
                $totalTotalt += (int)($r['totalt']      ?? 0);
                $totalDrift  += (int)($r['drifttid']    ?? 0);
                $totalRast   += (int)($r['rasttime']    ?? 0);

                foreach (['op1_name', 'op2_name', 'op3_name'] as $opField) {
                    if (!empty($r[$opField])) {
                        $operatorNames[$r[$opField]] = true;
                    }
                }
                if (!empty($r['product_name'])) {
                    $products[$r['product_name']] = true;
                }
            }

            $kvalitet = $totalTotalt > 0
                ? round(($totalIbcOk / $totalTotalt) * 100, 1)
                : null;

            $planned = $totalDrift + $totalRast;
            $avail   = $planned > 0 ? min($totalDrift / $planned, 1) : null;
            $idealRatePerMin = 15.0 / 60.0;
            $perf = ($totalDrift > 0 && $totalTotalt > 0)
                ? min(($totalTotalt / $totalDrift) / $idealRatePerMin, 1.0)
                : null;
            $qualityRatio = $totalTotalt > 0 ? ($totalIbcOk / $totalTotalt) : null;
            $oee = ($avail !== null && $perf !== null && $qualityRatio !== null)
                ? round($avail * $perf * $qualityRatio * 100, 1)
                : null;

            $ibcPerH = $totalDrift > 0
                ? round(($totalIbcOk / ($totalDrift / 60)), 1)
                : null;

            // Delta vs föregående skift — hämta föregående skifts totalt
            $prevShift = $shift - 1;
            $prevDate  = $date;
            if ($prevShift < 1) {
                $prevShift = 3;
                $prevDate  = date('Y-m-d', strtotime($date . ' -1 day'));
            }

            $prevStmt = $this->pdo->prepare("
                SELECT SUM(s.totalt) AS prev_totalt
                FROM rebotling_skiftrapport s
                WHERE DATE(s.datum) = :date
                  AND s.skiftraknare IS NOT NULL
                  AND (
                    SELECT CASE
                        WHEN HOUR(MIN(i.datum)) BETWEEN 6 AND 13 THEN 1
                        WHEN HOUR(MIN(i.datum)) BETWEEN 14 AND 21 THEN 2
                        ELSE 3
                    END
                    FROM rebotling_ibc i WHERE i.skiftraknare = s.skiftraknare
                  ) = :shift
            ");
            $prevStmt->execute(['date' => $prevDate, 'shift' => $prevShift]);
            $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);

            $prevTotalt = (int)($prevRow['prev_totalt'] ?? 0);
            $delta = $prevTotalt > 0 ? $totalTotalt - $prevTotalt : null;

            // Timvis produktion från PLC-data (om tillgänglig)
            $hourlyData = [];
            $skiftraknareList = [];
            foreach ($rows as $r) {
                if (!empty($r['skiftraknare'])) {
                    $skiftraknareList[(int)$r['skiftraknare']] = true;
                }
            }
            $skiftraknare = count($skiftraknareList) > 0 ? array_key_first($skiftraknareList) : null;

            if ($skiftraknare) {
                // Sök enbart på skifträknare (ej datumfilter) — hanterar dag-efter-scenariot
                $hourlyStmt = $this->pdo->prepare("
                    SELECT
                        HOUR(datum) AS timme,
                        COUNT(*)    AS antal,
                        MAX(COALESCE(ibc_ok, 0)) - MIN(COALESCE(ibc_ok, 0)) AS ibc_diff
                    FROM rebotling_ibc
                    WHERE skiftraknare = :skift
                    GROUP BY HOUR(datum)
                    ORDER BY HOUR(datum)
                ");
                $hourlyStmt->execute(['skift' => $skiftraknare]);
                $hourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Hämta start/stopptid: fallback nedåt + onoff (maskin start/stopp)
            $tider = $this->resolveSkiftTider($skiftraknareList, $date);
            $skiftStart = $tider['start'];
            $skiftSlut  = $tider['slut'];

            // Skiftkommentar
            $kommentar = '';
            try {
                $komStmt = $this->pdo->prepare("
                    SELECT kommentar FROM rebotling_skift_kommentar
                    WHERE datum = :datum AND skift_nr = :skift_nr
                    LIMIT 1
                ");
                $komStmt->execute(['datum' => $date, 'skift_nr' => $shift]);
                $komRow = $komStmt->fetch(PDO::FETCH_ASSOC);
                if ($komRow) $kommentar = $komRow['kommentar'] ?? '';
            } catch (Exception) {
                // Tabellen kanske inte finns — ignorera
            }

            $shiftNames = [1 => 'Förmiddag (06–14)', 2 => 'Eftermiddag (14–22)', 3 => 'Natt (22–06)'];

            echo json_encode([
                'success' => true,
                'data' => [
                    'date'       => $date,
                    'shift'      => $shift,
                    'shift_name' => $shiftNames[$shift] ?? "Skift $shift",
                    'total_ibc'  => $totalTotalt,
                    'ibc_ok'     => $totalIbcOk,
                    'bur_ej_ok'  => $totalBurEj,
                    'ibc_ej_ok'  => $totalIbcEj,
                    'kvalitet'   => $kvalitet,
                    'oee'        => $oee,
                    'ibc_per_h'  => $ibcPerH,
                    'drifttid'   => $totalDrift,
                    'rasttime'   => $totalRast,
                    'skift_start'  => $skiftStart,
                    'skift_slut'   => $skiftSlut,
                    'cykel_datum'  => $tider['cykel_datum'],
                    'delta_vs_prev' => $delta,
                    'operators'  => array_keys($operatorNames),
                    'products'   => array_keys($products),
                    'reports'    => $rows,
                    'hourly'     => $hourlyData,
                    'kommentar'  => $kommentar,
                    'antal_rapporter' => count($rows),
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getShiftSummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftsammanfattning'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Korrelationsanalys: underhållshändelser vs maskinstopp per vecka (senaste 12 veckorna).
     * Hämtar data från maintenance_log och stoppage_log, grupperat per ISO-vecka.
     */

    public function getMaintenanceCorrelation() {
        try {
            $weeks = intval($_GET['weeks'] ?? 12);
            if ($weeks < 4 || $weeks > 52) $weeks = 12;

            // Underhållshändelser per vecka (från maintenance_log)
            $stmtMaint = $this->pdo->prepare("
                SELECT
                    YEAR(start_time) AS yr,
                    WEEK(start_time, 1) AS wk,
                    CONCAT(YEAR(start_time), '-V', LPAD(WEEK(start_time, 1), 2, '0')) AS vecka,
                    COUNT(*) AS antal_underhall,
                    COALESCE(SUM(duration_minutes), 0) AS total_underhallstid
                FROM maintenance_log
                WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
                  AND status != 'avbokat'
                GROUP BY YEAR(start_time), WEEK(start_time, 1)
                ORDER BY yr ASC, wk ASC
            ");
            $stmtMaint->execute([':weeks' => $weeks]);
            $maintRows = $stmtMaint->fetchAll(PDO::FETCH_ASSOC);

            // Maskinstopp per vecka (från stoppage_log)
            $stmtStop = $this->pdo->prepare("
                SELECT
                    YEAR(start_time) AS yr,
                    WEEK(start_time, 1) AS wk,
                    CONCAT(YEAR(start_time), '-V', LPAD(WEEK(start_time, 1), 2, '0')) AS vecka,
                    COUNT(*) AS antal_stopp,
                    COALESCE(SUM(duration_minutes), 0) AS total_stopptid
                FROM stoppage_log
                WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
                  AND line = 'rebotling'
                GROUP BY YEAR(start_time), WEEK(start_time, 1)
                ORDER BY yr ASC, wk ASC
            ");
            $stmtStop->execute([':weeks' => $weeks]);
            $stopRows = $stmtStop->fetchAll(PDO::FETCH_ASSOC);

            // Bygg index per veckonyckel
            $maintIndex = [];
            foreach ($maintRows as $r) {
                $maintIndex[$r['vecka']] = $r;
            }
            $stopIndex = [];
            foreach ($stopRows as $r) {
                $stopIndex[$r['vecka']] = $r;
            }

            // Samla alla unika veckor
            $allKeys = array_unique(array_merge(array_keys($maintIndex), array_keys($stopIndex)));
            sort($allKeys);

            $series = [];
            foreach ($allKeys as $vecka) {
                $m = $maintIndex[$vecka] ?? null;
                $s = $stopIndex[$vecka] ?? null;
                $series[] = [
                    'vecka'               => $vecka,
                    'antal_underhall'      => $m ? (int)$m['antal_underhall'] : 0,
                    'total_underhallstid'  => $m ? (int)$m['total_underhallstid'] : 0,
                    'antal_stopp'          => $s ? (int)$s['antal_stopp'] : 0,
                    'total_stopptid'       => $s ? (int)$s['total_stopptid'] : 0,
                ];
            }

            // Beräkna KPI:er — jämför första halvan vs andra halvan
            $halfLen = max(1, intval(count($series) / 2));
            $firstHalf = array_slice($series, 0, $halfLen);
            $secondHalf = array_slice($series, $halfLen);

            $avgStoppForst = count($firstHalf) > 0
                ? round(array_sum(array_column($firstHalf, 'antal_stopp')) / count($firstHalf), 1)
                : 0;
            $avgStoppSenare = count($secondHalf) > 0
                ? round(array_sum(array_column($secondHalf, 'antal_stopp')) / count($secondHalf), 1)
                : 0;

            $avgUnderhallForst = count($firstHalf) > 0
                ? round(array_sum(array_column($firstHalf, 'antal_underhall')) / count($firstHalf), 1)
                : 0;
            $avgUnderhallSenare = count($secondHalf) > 0
                ? round(array_sum(array_column($secondHalf, 'antal_underhall')) / count($secondHalf), 1)
                : 0;

            // Förändring i stopp (negativ = förbättring)
            $stoppForandring = $avgStoppForst > 0
                ? round((($avgStoppSenare - $avgStoppForst) / $avgStoppForst) * 100, 1)
                : 0;

            // Beräkna enkel Pearson-korrelation mellan underhåll och stopp (vecka+1)
            $korrelation = null;
            if (count($series) >= 4) {
                $n = count($series) - 1;
                $xArr = []; // underhåll vecka i
                $yArr = []; // stopp vecka i+1
                for ($i = 0; $i < $n; $i++) {
                    $xArr[] = $series[$i]['antal_underhall'];
                    $yArr[] = $series[$i + 1]['antal_stopp'];
                }
                $xMean = array_sum($xArr) / $n;
                $yMean = array_sum($yArr) / $n;
                $num = 0; $denX = 0; $denY = 0;
                for ($i = 0; $i < $n; $i++) {
                    $dx = $xArr[$i] - $xMean;
                    $dy = $yArr[$i] - $yMean;
                    $num += $dx * $dy;
                    $denX += $dx * $dx;
                    $denY += $dy * $dy;
                }
                $den = sqrt($denX * $denY);
                $korrelation = $den > 0 ? round($num / $den, 2) : 0;
            }

            echo json_encode([
                'success'              => true,
                'series'               => $series,
                'kpi' => [
                    'avg_stopp_forsta_halvan'  => $avgStoppForst,
                    'avg_stopp_andra_halvan'   => $avgStoppSenare,
                    'avg_underh_forsta_halvan' => $avgUnderhallForst,
                    'avg_underh_andra_halvan'  => $avgUnderhallSenare,
                    'stopp_forandring_pct'     => $stoppForandring,
                    'korrelation'              => $korrelation,
                ],
                'weeks_param' => $weeks,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getMaintenanceCorrelation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta korrelationsdata'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Automatisk skiftrapport via email
    // POST ?action=rebotling&run=auto-shift-report
    // =========================================================

    public function sendAutoShiftReport(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $date  = $data['date']  ?? date('Y-m-d');
        $shift = (int)($data['shift'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $shift < 1 || $shift > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum eller skiftnummer (1-3)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta mottagare
            $this->ensureNotificationEmailsColumn();
            $row = $this->pdo->query(
                "SELECT notification_emails FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            $emailsRaw = $row['notification_emails'] ?? '';
            if (empty(trim($emailsRaw))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga e-postmottagare konfigurerade. Konfigurera under E-postnotifikationer.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $recipients = [];
            $parts = array_map('trim', explode(';', str_replace(',', ';', $emailsRaw)));
            foreach ($parts as $email) {
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }

            if (count($recipients) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga giltiga e-postadresser konfigurerade.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Hämta skiftdata
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt,
                    s.drifttid, s.rasttime, s.lopnummer, s.skiftraknare,
                    s.op1, s.op2, s.op3,
                    u.username AS user_name,
                    p.name AS product_name,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE DATE(s.datum) = :date
                ORDER BY s.id
            ");
            $stmt->execute(['date' => $date]);
            $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Filtrera rader per skift
            $rows = [];
            foreach ($allRows as $r) {
                $timeStr = substr($r['datum'] ?? '', 11, 5);
                $rowShift = 1;
                if ($timeStr) {
                    $p = explode(':', $timeStr);
                    $minutes = ((int)$p[0]) * 60 + ((int)($p[1] ?? 0));
                    if ($minutes >= 360 && $minutes < 840) $rowShift = 1;
                    elseif ($minutes >= 840 && $minutes < 1320) $rowShift = 2;
                    else $rowShift = 3;
                }
                if ($rowShift === $shift) {
                    $rows[] = $r;
                }
            }

            if (count($rows) === 0 && count($allRows) > 0) {
                $rows = $allRows;
            }

            // Aggregera KPI:er
            $totalIbcOk  = 0;
            $totalBurEj  = 0;
            $totalIbcEj  = 0;
            $totalTotalt = 0;
            $totalDrift  = 0;
            $totalRast   = 0;
            $operatorNames = [];
            $products    = [];

            foreach ($rows as $r) {
                $totalIbcOk  += (int)($r['ibc_ok']    ?? 0);
                $totalBurEj  += (int)($r['bur_ej_ok']  ?? 0);
                $totalIbcEj  += (int)($r['ibc_ej_ok']  ?? 0);
                $totalTotalt += (int)($r['totalt']      ?? 0);
                $totalDrift  += (int)($r['drifttid']    ?? 0);
                $totalRast   += (int)($r['rasttime']    ?? 0);

                foreach (['op1_name', 'op2_name', 'op3_name'] as $opField) {
                    if (!empty($r[$opField])) {
                        $operatorNames[$r[$opField]] = true;
                    }
                }
                if (!empty($r['product_name'])) {
                    $products[$r['product_name']] = true;
                }
            }

            $kvalitet = $totalTotalt > 0
                ? round(($totalIbcOk / $totalTotalt) * 100, 1)
                : 0;

            $ibcPerH = $totalDrift > 0
                ? round(($totalIbcOk / ($totalDrift / 60)), 1)
                : 0;

            $shiftNames = [1 => 'Förmiddag (06-14)', 2 => 'Eftermiddag (14-22)', 3 => 'Natt (22-06)'];
            $shiftName  = $shiftNames[$shift] ?? "Skift $shift";

            // Bygg HTML-rapport
            $html = $this->buildShiftReportHtml(
                $date, $shift, $shiftName,
                $totalIbcOk, $totalBurEj, $totalIbcEj, $totalTotalt,
                $totalDrift, $totalRast, $kvalitet, $ibcPerH,
                array_keys($operatorNames), array_keys($products),
                $rows
            );

            // Skicka email
            $subject = "Skiftrapport — $date — $shiftName";
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Rebotling System <noreply@noreko.se>\r\n";

            $sentTo = [];
            foreach ($recipients as $email) {
                if (mail($email, $subject, $html, $headers)) {
                    $sentTo[] = $email;
                } else {
                    error_log("RebotlingAnalyticsController::sendAutoShiftReport: Kunde inte skicka e-post till mottagare");
                }
            }

            if (count($sentTo) === 0) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Kunde inte skicka till någon mottagare. Kontrollera serverinställningar.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'success'      => true,
                'recipients'   => $sentTo,
                'shift_date'   => $date,
                'shift_number' => $shift,
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::sendAutoShiftReport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid sändning av skiftrapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Bygg en HTML-baserad skiftrapport.
     */

    private function buildShiftReportHtml(
        string $date, int $shift, string $shiftName,
        int $ibcOk, int $burEj, int $ibcEj, int $totalt,
        int $drifttid, int $rasttime, float $kvalitet, float $ibcPerH,
        array $operators, array $products, array $rows
    ): string {
        // $shift används i subject/metadata men inte direkt i HTML-kroppen.
        // Behåller parametern för API-kompatibilitet men undertrycker unused-varning.
        unset($shift);
        $driftH = floor($drifttid / 60);
        $driftM = $drifttid % 60;
        $rastH  = floor($rasttime / 60);
        $rastM  = $rasttime % 60;
        $opList      = !empty($operators) ? implode(', ', $operators) : '<em>Ej angivet</em>';
        $productList = !empty($products) ? implode(', ', $products) : '<em>Ej angivet</em>';

        $kvalitetColor = $kvalitet >= 95 ? '#38a169' : ($kvalitet >= 85 ? '#d69e2e' : '#e53e3e');

        $rowsHtml = '';
        foreach ($rows as $r) {
            $opNames = array_filter([$r['op1_name'] ?? '', $r['op2_name'] ?? '', $r['op3_name'] ?? '']);
            $rowsHtml .= '<tr>'
                . '<td>' . htmlspecialchars($r['product_name'] ?? '–', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right;">' . (int)($r['ibc_ok'] ?? 0) . '</td>'
                . '<td style="text-align:right;">' . (int)($r['bur_ej_ok'] ?? 0) . '</td>'
                . '<td style="text-align:right;">' . (int)($r['ibc_ej_ok'] ?? 0) . '</td>'
                . '<td style="text-align:right;">' . (int)($r['totalt'] ?? 0) . '</td>'
                . '<td>' . htmlspecialchars(implode(', ', $opNames) ?: '–') . '</td>'
                . '</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="sv">
<head><meta charset="UTF-8"><title>Skiftrapport {$date}</title></head>
<body style="margin:0; padding:0; font-family:Arial,Helvetica,sans-serif; background:#f5f5f5;">
<div style="max-width:640px; margin:20px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);">

  <!-- Header -->
  <div style="background:#1a202c; color:#e2e8f0; padding:20px 24px;">
    <h1 style="margin:0; font-size:20px;">Skiftrapport</h1>
    <p style="margin:6px 0 0; font-size:14px; color:#a0aec0;">{$date} &mdash; {$shiftName}</p>
  </div>

  <!-- KPI:er -->
  <div style="padding:20px 24px;">
    <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
      <tr>
        <td style="padding:10px 8px; text-align:center; background:#f7fafc; border-radius:6px;">
          <div style="font-size:24px; font-weight:700; color:#2d3748;">{$ibcOk}</div>
          <div style="font-size:12px; color:#718096; text-transform:uppercase;">IBC OK</div>
        </td>
        <td style="padding:10px 8px; text-align:center; background:#f7fafc; border-radius:6px;">
          <div style="font-size:24px; font-weight:700; color:{$kvalitetColor};">{$kvalitet}%</div>
          <div style="font-size:12px; color:#718096; text-transform:uppercase;">Kvalitet</div>
        </td>
        <td style="padding:10px 8px; text-align:center; background:#f7fafc; border-radius:6px;">
          <div style="font-size:24px; font-weight:700; color:#2d3748;">{$ibcPerH}</div>
          <div style="font-size:12px; color:#718096; text-transform:uppercase;">IBC/h</div>
        </td>
      </tr>
    </table>

    <table style="width:100%; border-collapse:collapse; font-size:14px; color:#4a5568; margin-bottom:16px;">
      <tr><td style="padding:4px 0;"><strong>Totalt bearbetade:</strong></td><td style="text-align:right;">{$totalt}</td></tr>
      <tr><td style="padding:4px 0;"><strong>Bur ej OK:</strong></td><td style="text-align:right;">{$burEj}</td></tr>
      <tr><td style="padding:4px 0;"><strong>IBC ej OK:</strong></td><td style="text-align:right;">{$ibcEj}</td></tr>
      <tr><td style="padding:4px 0;"><strong>Drifttid:</strong></td><td style="text-align:right;">{$driftH}h {$driftM}min</td></tr>
      <tr><td style="padding:4px 0;"><strong>Rasttid:</strong></td><td style="text-align:right;">{$rastH}h {$rastM}min</td></tr>
      <tr><td style="padding:4px 0;"><strong>Operatörer:</strong></td><td style="text-align:right;">{$opList}</td></tr>
      <tr><td style="padding:4px 0;"><strong>Produkter:</strong></td><td style="text-align:right;">{$productList}</td></tr>
    </table>

    <!-- Detaljrader -->
    <h3 style="font-size:14px; color:#2d3748; margin:16px 0 8px;">Rapportrader</h3>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
      <thead>
        <tr style="background:#edf2f7; color:#4a5568;">
          <th style="padding:6px 8px; text-align:left;">Produkt</th>
          <th style="padding:6px 8px; text-align:right;">OK</th>
          <th style="padding:6px 8px; text-align:right;">Bur ej</th>
          <th style="padding:6px 8px; text-align:right;">IBC ej</th>
          <th style="padding:6px 8px; text-align:right;">Totalt</th>
          <th style="padding:6px 8px; text-align:left;">Operatörer</th>
        </tr>
      </thead>
      <tbody>{$rowsHtml}</tbody>
    </table>
  </div>

  <!-- Footer -->
  <div style="background:#f7fafc; padding:12px 24px; font-size:12px; color:#a0aec0; text-align:center;">
    Automatiskt genererad av Rebotling-systemet &mdash; Noreko
  </div>

</div>
</body>
</html>
HTML;
    }

    // =========================================================================
    // IBC-kvalitets deep-dive: Rejection Analysis
    // GET ?action=rebotling&run=rejection-analysis&days=30
    // =========================================================================

    public function getRejectionAnalysis() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $toDate = date('Y-m-d');

        try {
            // 1. Daglig kvalitets% (ibc_ok / totalt * 100) med glidande 7-dagars snitt
            $stmtDaily = $this->pdo->prepare("
                SELECT dag,
                       SUM(ibc_ok) AS ibc_ok,
                       SUM(ibc_totalt) AS ibc_totalt,
                       SUM(ibc_ej_ok) AS ibc_ej_ok,
                       ROUND(SUM(ibc_ok) * 100.0 / NULLIF(SUM(ibc_totalt), 0), 1) AS kvalitet_pct
                FROM (
                    SELECT DATE(datum) AS dag,
                           skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0)) AS ibc_totalt,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmtDaily->execute([':days' => $days]);
            $dailyRows = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna glidande 7-dagars snitt
            $trendData = [];
            for ($i = 0; $i < count($dailyRows); $i++) {
                $window = array_slice($dailyRows, max(0, $i - 6), min(7, $i + 1));
                $validVals = array_filter(array_column($window, 'kvalitet_pct'), function($v) { return $v !== null; });
                $glidandeSnitt = count($validVals) > 0 ? round(array_sum($validVals) / count($validVals), 1) : null;

                $trendData[] = [
                    'datum'          => $dailyRows[$i]['dag'],
                    'kvalitet_pct'   => $dailyRows[$i]['kvalitet_pct'] !== null ? (float)$dailyRows[$i]['kvalitet_pct'] : null,
                    'glidande_snitt' => $glidandeSnitt,
                    'ibc_ok'         => (int)$dailyRows[$i]['ibc_ok'],
                    'ibc_kasserade'  => (int)$dailyRows[$i]['ibc_ej_ok'],
                    'ibc_totalt'     => (int)$dailyRows[$i]['ibc_totalt'],
                ];
            }

            // 2. KPI-beräkningar
            $todayStr = date('Y-m-d');
            $todayRow = null;
            $last7Rows = array_slice($dailyRows, -7);
            $prev7Rows = count($dailyRows) >= 14 ? array_slice($dailyRows, -14, 7) : [];

            foreach ($dailyRows as $r) {
                if ($r['dag'] === $todayStr) {
                    $todayRow = $r;
                    break;
                }
            }

            $kvalitetIdag = $todayRow && $todayRow['kvalitet_pct'] !== null
                ? (float)$todayRow['kvalitet_pct'] : null;
            $kasseradeIdag = $todayRow ? (int)$todayRow['ibc_ej_ok'] : 0;

            // Vecko glidande snitt
            $last7Vals = array_filter(array_column($last7Rows, 'kvalitet_pct'), function($v) { return $v !== null; });
            $kvalitetVecka = count($last7Vals) > 0
                ? round(array_sum($last7Vals) / count($last7Vals), 1)
                : null;

            // Trend vs förra veckan
            $prev7Vals = array_filter(array_column($prev7Rows, 'kvalitet_pct'), function($v) { return $v !== null; });
            $prevAvg = count($prev7Vals) > 0 ? array_sum($prev7Vals) / count($prev7Vals) : null;
            $trendVsForraVeckan = 'stable';
            $trendDiff = null;
            if ($kvalitetVecka !== null && $prevAvg !== null) {
                $trendDiff = round($kvalitetVecka - $prevAvg, 1);
                if ($trendDiff > 0.5) $trendVsForraVeckan = 'up';
                elseif ($trendDiff < -0.5) $trendVsForraVeckan = 'down';
            }

            // 3. Kassationsorsaker (Pareto) om tabeller finns
            $paretoData = [];
            $totalKassation = 0;
            $hasParetoData = false;
            try {
                $stmtPareto = $this->pdo->prepare("
                    SELECT
                        t.id,
                        t.namn,
                        COALESCE(SUM(r.antal), 0) AS total_antal
                    FROM kassationsorsak_typer t
                    LEFT JOIN kassationsregistrering r
                        ON r.orsak_id = t.id
                        AND r.datum BETWEEN :from_date AND :to_date
                    WHERE t.aktiv = 1
                    GROUP BY t.id, t.namn
                    ORDER BY total_antal DESC
                ");
                $stmtPareto->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                $paretoRows = $stmtPareto->fetchAll(PDO::FETCH_ASSOC);
                $totalKassation = array_sum(array_column($paretoRows, 'total_antal'));

                $cumulative = 0;
                foreach ($paretoRows as $pr) {
                    $antal = (int)$pr['total_antal'];
                    $pct = $totalKassation > 0 ? round($antal / $totalKassation * 100, 1) : 0;
                    $cumulative += $pct;
                    $paretoData[] = [
                        'id'            => (int)$pr['id'],
                        'namn'          => $pr['namn'],
                        'antal'         => $antal,
                        'pct'           => $pct,
                        'kumulativ_pct' => round($cumulative, 1),
                    ];
                }
                $hasParetoData = $totalKassation > 0;
            } catch (\Exception) {
                // Tabellerna finns inte ännu — pareto-data förblir tom
                $hasParetoData = false;
            }

            // 4. Trend per orsak vs föregående period (om Pareto-data finns)
            if ($hasParetoData) {
                $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
                $prevTo = date('Y-m-d', strtotime("-{$days} days"));
                try {
                    $stmtPrev = $this->pdo->prepare("
                        SELECT
                            t.id,
                            t.namn,
                            COALESCE(SUM(r.antal), 0) AS prev_antal
                        FROM kassationsorsak_typer t
                        LEFT JOIN kassationsregistrering r
                            ON r.orsak_id = t.id
                            AND r.datum BETWEEN :prev_from AND :prev_to
                        WHERE t.aktiv = 1
                        GROUP BY t.id, t.namn
                    ");
                    $stmtPrev->execute([':prev_from' => $prevFrom, ':prev_to' => $prevTo]);
                    $prevRows = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);
                    $prevMap = [];
                    foreach ($prevRows as $pv) {
                        $prevMap[(int)$pv['id']] = (int)$pv['prev_antal'];
                    }
                    foreach ($paretoData as &$pd) {
                        $prevAntal = $prevMap[$pd['id']] ?? 0;
                        $pd['prev_antal'] = $prevAntal;
                        $pd['trend'] = $pd['antal'] > $prevAntal ? 'up'
                            : ($pd['antal'] < $prevAntal ? 'down' : 'stable');
                    }
                    unset($pd);
                } catch (\Exception) {
                    // Ignorera — trend blir otillgänglig
                }
            }

            echo json_encode([
                'success' => true,
                'days'    => $days,
                'kpi' => [
                    'kvalitet_idag'          => $kvalitetIdag,
                    'kvalitet_vecka'         => $kvalitetVecka,
                    'kasserade_idag'         => $kasseradeIdag,
                    'trend_vs_forra_veckan'  => $trendVsForraVeckan,
                    'trend_diff'             => $trendDiff,
                ],
                'trend'   => $trendData,
                'pareto'  => $paretoData,
                'has_pareto_data' => $hasParetoData,
                'total_kassation' => (int)$totalKassation,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingAnalyticsController::getRejectionAnalysis: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av kvalitetsanalys.'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================================
    // IBC-kvalitets deep-dive: Kassationsorsaker nedbrytning
    // GET ?action=rebotling&run=quality-rejection-breakdown&days=30
    // =========================================================================

    public function getQualityRejectionBreakdown() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $toDate = date('Y-m-d');
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
        $prevTo = $fromDate;

        try {
            // 1. Totala IBC (godkända + kasserade) för perioden
            $stmtTotal = $this->pdo->prepare("
                SELECT
                    SUM(ibc_ok) AS total_ok,
                    SUM(ibc_ej_ok) AS total_ej_ok,
                    SUM(ibc_ok) + SUM(ibc_ej_ok) AS total_ibc
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum <= :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmtTotal->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $totals = $stmtTotal->fetch(PDO::FETCH_ASSOC);

            $totalIbc = (int)($totals['total_ibc'] ?? 0);
            $totalOk = (int)($totals['total_ok'] ?? 0);
            $totalEjOk = (int)($totals['total_ej_ok'] ?? 0);
            $godkandPct = $totalIbc > 0 ? round($totalOk / $totalIbc * 100, 1) : 0;
            $kasseradPct = $totalIbc > 0 ? round($totalEjOk / $totalIbc * 100, 1) : 0;

            // 2. Föregående period för trend
            $stmtPrevTotal = $this->pdo->prepare("
                SELECT
                    SUM(ibc_ok) AS total_ok,
                    SUM(ibc_ej_ok) AS total_ej_ok,
                    SUM(ibc_ok) + SUM(ibc_ej_ok) AS total_ibc
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :prev_from AND datum < :prev_to
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmtPrevTotal->execute([':prev_from' => $prevFrom, ':prev_to' => $prevTo]);
            $prevTotals = $stmtPrevTotal->fetch(PDO::FETCH_ASSOC);
            $prevIbc = (int)($prevTotals['total_ibc'] ?? 0);
            $prevEjOk = (int)($prevTotals['total_ej_ok'] ?? 0);
            $prevKasseradPct = $prevIbc > 0 ? round($prevEjOk / $prevIbc * 100, 1) : 0;
            $kassationTrendDiff = round($kasseradPct - $prevKasseradPct, 1);
            $kassationTrend = 'stable';
            if ($kassationTrendDiff > 0.5) $kassationTrend = 'up';
            elseif ($kassationTrendDiff < -0.5) $kassationTrend = 'down';

            // 3. Kassationsorsaker (nedbrytning)
            $orsaker = [];
            $totalKassationRegistrerad = 0;
            $hasParetoData = false;
            try {
                $stmtOrsaker = $this->pdo->prepare("
                    SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS total_antal
                    FROM kassationsorsak_typer t
                    LEFT JOIN kassationsregistrering r
                        ON r.orsak_id = t.id
                        AND r.datum BETWEEN :from_date AND :to_date
                    WHERE t.aktiv = 1
                    GROUP BY t.id, t.namn
                    ORDER BY total_antal DESC
                ");
                $stmtOrsaker->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                $orsakRows = $stmtOrsaker->fetchAll(PDO::FETCH_ASSOC);
                $totalKassationRegistrerad = array_sum(array_column($orsakRows, 'total_antal'));

                // Föregående period per orsak
                $stmtPrevOrsaker = $this->pdo->prepare("
                    SELECT t.id, COALESCE(SUM(r.antal), 0) AS prev_antal
                    FROM kassationsorsak_typer t
                    LEFT JOIN kassationsregistrering r
                        ON r.orsak_id = t.id
                        AND r.datum BETWEEN :prev_from AND :prev_to
                    WHERE t.aktiv = 1
                    GROUP BY t.id
                ");
                $stmtPrevOrsaker->execute([':prev_from' => $prevFrom, ':prev_to' => $prevTo]);
                $prevOrsakRows = $stmtPrevOrsaker->fetchAll(PDO::FETCH_ASSOC);
                $prevMap = [];
                foreach ($prevOrsakRows as $pv) {
                    $prevMap[(int)$pv['id']] = (int)$pv['prev_antal'];
                }

                $cumulative = 0;
                foreach ($orsakRows as $or) {
                    $antal = (int)$or['total_antal'];
                    $pct = $totalKassationRegistrerad > 0 ? round($antal / $totalKassationRegistrerad * 100, 1) : 0;
                    $cumulative += $pct;
                    $prevAntal = $prevMap[(int)$or['id']] ?? 0;
                    $trend = $antal > $prevAntal ? 'up' : ($antal < $prevAntal ? 'down' : 'stable');
                    $orsaker[] = [
                        'id'            => (int)$or['id'],
                        'namn'          => $or['namn'],
                        'antal'         => $antal,
                        'andel'         => $pct,
                        'kumulativ_pct' => round($cumulative, 1),
                        'prev_antal'    => $prevAntal,
                        'trend'         => $trend,
                    ];
                }
                $hasParetoData = $totalKassationRegistrerad > 0;
            } catch (\Exception) {
                $hasParetoData = false;
            }

            echo json_encode([
                'success' => true,
                'days'    => $days,
                'summary' => [
                    'total_ibc'          => $totalIbc,
                    'godkanda'           => $totalOk,
                    'godkand_pct'        => $godkandPct,
                    'kasserade'          => $totalEjOk,
                    'kasserad_pct'       => $kasseradPct,
                    'kassation_trend'    => $kassationTrend,
                    'kassation_trend_diff' => $kassationTrendDiff,
                    'prev_kasserad_pct'  => $prevKasseradPct,
                ],
                'orsaker'          => $orsaker,
                'has_pareto_data'  => $hasParetoData,
                'total_kassation_registrerad' => $totalKassationRegistrerad,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingAnalyticsController::getQualityRejectionBreakdown: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av kassationsanalys.'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================================
    // IBC-kvalitets deep-dive: Kassationsorsak-trend per dag
    // GET ?action=rebotling&run=quality-rejection-trend&days=30
    // =========================================================================

    public function getQualityRejectionTrend() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $toDate = date('Y-m-d');

        try {
            // Hämta top 5 orsaker för perioden
            $stmtTop = $this->pdo->prepare("
                SELECT t.id, t.namn, COALESCE(SUM(r.antal), 0) AS total_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id
                    AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn
                ORDER BY total_antal DESC
                LIMIT 5
            ");
            $stmtTop->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $topOrsaker = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            if (empty($topOrsaker)) {
                echo json_encode(['success' => true, 'days' => $days, 'orsaker' => [], 'trend' => []], JSON_UNESCAPED_UNICODE);
                return;
            }

            $topIds = array_column($topOrsaker, 'id');
            $orsakNamn = [];
            foreach ($topOrsaker as $to) {
                $orsakNamn[(int)$to['id']] = $to['namn'];
            }

            // Hämta daglig data per orsak
            $placeholders = implode(',', array_fill(0, count($topIds), '?'));
            $stmtDaily = $this->pdo->prepare("
                SELECT r.datum, r.orsak_id, SUM(r.antal) AS antal
                FROM kassationsregistrering r
                WHERE r.datum BETWEEN ? AND ?
                  AND r.orsak_id IN ({$placeholders})
                GROUP BY r.datum, r.orsak_id
                ORDER BY r.datum ASC
            ");
            $params = array_merge([$fromDate, $toDate], $topIds);
            $stmtDaily->execute($params);
            $dailyRows = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

            // Bygg tidsseriedata
            $dataByDate = [];
            foreach ($dailyRows as $row) {
                $datum = $row['datum'];
                $orsakId = (int)$row['orsak_id'];
                if (!isset($dataByDate[$datum])) {
                    $dataByDate[$datum] = [];
                }
                $dataByDate[$datum][$orsakId] = (int)$row['antal'];
            }

            // Generera alla datum i perioden
            $trendData = [];
            $current = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                $dayEntry = ['datum' => $dateStr];
                foreach ($topIds as $id) {
                    $dayEntry['orsak_' . $id] = $dataByDate[$dateStr][(int)$id] ?? 0;
                }
                $trendData[] = $dayEntry;
                $current->modify('+1 day');
            }

            $orsakerMeta = [];
            foreach ($topOrsaker as $to) {
                $orsakerMeta[] = [
                    'id'   => (int)$to['id'],
                    'namn' => $to['namn'],
                    'key'  => 'orsak_' . $to['id'],
                ];
            }

            echo json_encode([
                'success' => true,
                'days'    => $days,
                'orsaker' => $orsakerMeta,
                'trend'   => $trendData,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingAnalyticsController::getQualityRejectionTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av kassationstrend.'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skiftsammanfattning som utskriftsvanlig HTML (PDF via webblasarens print)
    // GET ?action=rebotling&run=shift-pdf-summary&date=YYYY-MM-DD&shift=1|2|3
    // =========================================================

    public function getShiftPdfSummary() {
        // Sätt HTML content-type direkt — api.php sätter application/json som default
        header('Content-Type: text/html; charset=utf-8');

        $date  = trim($_GET['date']  ?? '');
        $shift = (int)($_GET['shift'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $shift < 1 || $shift > 3) {
            http_response_code(400);
            echo '<!DOCTYPE html><html><body><h1>Ogiltigt datum eller skiftnummer (1-3)</h1></body></html>';
            return;
        }

        try {
            // Hämta skiftrapporter för detta datum OCH specifikt skift
            // Skiftnummer bestäms via rebotling_ibc: HOUR(MIN(datum)) → skift 1/2/3
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt,
                    s.drifttid, s.rasttime, s.lopnummer, s.skiftraknare,
                    s.op1, s.op2, s.op3,
                    s.created_at,
                    u.username AS user_name,
                    p.name AS product_name,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE DATE(s.datum) = :date
                  AND s.skiftraknare IS NOT NULL
                  AND (
                    SELECT CASE
                        WHEN HOUR(MIN(i.datum)) BETWEEN 6 AND 13 THEN 1
                        WHEN HOUR(MIN(i.datum)) BETWEEN 14 AND 21 THEN 2
                        ELSE 3
                    END
                    FROM rebotling_ibc i WHERE i.skiftraknare = s.skiftraknare
                  ) = :shift
                ORDER BY s.id
            ");
            $stmt->execute(['date' => $date, 'shift' => $shift]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Samla skifträknare för start/stopp-tid
            $skiftraknareList = [];
            $totalIbcOk = 0; $totalBurEj = 0; $totalIbcEj = 0;
            $totalTotalt = 0; $totalDrift = 0; $totalRast = 0;
            $operatorNames = []; $products = [];

            foreach ($rows as $r) {
                $totalIbcOk  += (int)($r['ibc_ok'] ?? 0);
                $totalBurEj  += (int)($r['bur_ej_ok'] ?? 0);
                $totalIbcEj  += (int)($r['ibc_ej_ok'] ?? 0);
                $totalTotalt += (int)($r['totalt'] ?? 0);
                $totalDrift  += (int)($r['drifttid'] ?? 0);
                $totalRast   += (int)($r['rasttime'] ?? 0);
                foreach (['op1_name','op2_name','op3_name'] as $opField) {
                    if (!empty($r[$opField])) $operatorNames[$r[$opField]] = true;
                }
                if (!empty($r['product_name'])) $products[$r['product_name']] = true;
                if (!empty($r['skiftraknare'])) {
                    $skiftraknareList[(int)$r['skiftraknare']] = true;
                }
            }

            // Hämta start/stopptid: fallback nedåt + onoff (maskin start/stopp)
            $tider = $this->resolveSkiftTider($skiftraknareList, $date);
            $skiftStart = $tider['start'];
            $skiftSlut  = $tider['slut'];

            $kvalitet = $totalTotalt > 0 ? round(($totalIbcOk / $totalTotalt) * 100, 1) : null;
            $planned = $totalDrift + $totalRast;
            $avail = $planned > 0 ? min($totalDrift / $planned, 1) : null;
            $idealRatePerMin = 15.0 / 60.0;
            $perf = ($totalDrift > 0 && $totalTotalt > 0)
                ? min(($totalTotalt / $totalDrift) / $idealRatePerMin, 1.0)
                : null;
            $qr = $totalTotalt > 0 ? ($totalIbcOk / $totalTotalt) : null;
            $oee = ($avail !== null && $perf !== null && $qr !== null) ? round($avail * $perf * $qr * 100, 1) : null;
            $ibcPerH = $totalDrift > 0 ? round(($totalIbcOk / ($totalDrift / 60)), 1) : null;

            $kommentar = '';
            try {
                $komStmt = $this->pdo->prepare("SELECT kommentar FROM rebotling_skift_kommentar WHERE datum = :datum AND skift_nr = :skift_nr LIMIT 1");
                $komStmt->execute(['datum' => $date, 'skift_nr' => $shift]);
                $komRow = $komStmt->fetch(PDO::FETCH_ASSOC);
                if ($komRow) $kommentar = $komRow['kommentar'] ?? '';
            } catch (Exception $e) { error_log("RebotlingAnalyticsController::getShiftPdfSummary kommentar: " . $e->getMessage()); }

            $shiftNames = [1 => 'Formiddag (06-14)', 2 => 'Eftermiddag (14-22)', 3 => 'Natt (22-06)'];
            $shiftName = $shiftNames[$shift] ?? "Skift $shift";

            $drifttidStr = '-';
            if ($totalDrift > 0) {
                $h = floor($totalDrift / 60); $m = $totalDrift % 60;
                $drifttidStr = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
            }
            $rasttidStr = $totalRast > 0 ? "{$totalRast} min" : '-';

            // Formatera start/stopp-tid
            $startTidStr = '-';
            $slutTidStr  = '-';
            $cykelDatumStr = '-';
            if ($skiftStart) {
                $startTidStr = date('Y-m-d H:i', strtotime($skiftStart));
            }
            if ($skiftSlut) {
                $slutTidStr = date('Y-m-d H:i', strtotime($skiftSlut));
            }
            if (!empty($tider['cykel_datum'])) {
                $cykelDatumStr = $tider['cykel_datum'];
            }

            $kvalitetFarg = '#333';
            if ($kvalitet !== null) {
                if ($kvalitet >= 90) $kvalitetFarg = '#006600';
                elseif ($kvalitet >= 70) $kvalitetFarg = '#996600';
                else $kvalitetFarg = '#cc0000';
            }
            $oeeFarg = '#333';
            if ($oee !== null) {
                if ($oee >= 75) $oeeFarg = '#006600';
                elseif ($oee >= 50) $oeeFarg = '#996600';
                else $oeeFarg = '#cc0000';
            }

            $operatorHtml = '';
            $opNames = array_keys($operatorNames);
            if (count($opNames) > 0) {
                foreach ($opNames as $op) {
                    $e = htmlspecialchars($op, ENT_QUOTES, 'UTF-8');
                    $operatorHtml .= "<span style='display:inline-block;background:#e8e8e8;border-radius:4px;padding:3px 10px;margin:2px 4px 2px 0;font-size:0.9rem;'>{$e}</span>";
                }
            } else {
                $operatorHtml = '<span style="color:#999;">Inga operatorer registrerade</span>';
            }

            $productHtml = '';
            $prodNames = array_keys($products);
            if (count($prodNames) > 0) {
                $productHtml = '<div style="margin-top:6px;"><small style="color:#666;">Produkter:</small><br>';
                foreach ($prodNames as $p) {
                    $e = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
                    $productHtml .= "<span style='display:inline-block;background:#d4edfc;border-radius:4px;padding:3px 10px;margin:2px 4px 2px 0;font-size:0.9rem;'>{$e}</span>";
                }
                $productHtml .= '</div>';
            }

            $kommentarHtml = '';
            if (!empty($kommentar)) {
                $ke = htmlspecialchars($kommentar, ENT_QUOTES, 'UTF-8');
                $kommentarHtml = "<div style='margin-top:16px;padding:10px 14px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;'><strong style='font-size:0.85rem;color:#555;'>Skiftkommentar:</strong><p style='margin:4px 0 0;white-space:pre-wrap;'>{$ke}</p></div>";
            }

            $opTableHtml = '';
            if (count($rows) > 0) {
                $opTableHtml = "<table style='width:100%;border-collapse:collapse;margin-top:16px;font-size:0.85rem;'><thead><tr style='background:#f0f0f0;'><th style='padding:6px 10px;border:1px solid #ccc;text-align:left;'>Produkt</th><th style='padding:6px 10px;border:1px solid #ccc;text-align:right;'>Löpnr</th><th style='padding:6px 10px;border:1px solid #ccc;text-align:right;'>IBC OK</th><th style='padding:6px 10px;border:1px solid #ccc;text-align:right;'>Bur ej OK</th><th style='padding:6px 10px;border:1px solid #ccc;text-align:right;'>IBC ej OK</th><th style='padding:6px 10px;border:1px solid #ccc;text-align:right;'>Totalt</th><th style='padding:6px 10px;border:1px solid #ccc;text-align:left;'>Operatorer</th></tr></thead><tbody>";
                foreach ($rows as $r) {
                    $prod = htmlspecialchars($r['product_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                    $lopnr = (int)($r['lopnummer'] ?? 0);
                    $lopnrStr = $lopnr > 0 ? (string)$lopnr : '-';
                    $ibcOk = (int)($r['ibc_ok'] ?? 0);
                    $burEj = (int)($r['bur_ej_ok'] ?? 0);
                    $ibcEj = (int)($r['ibc_ej_ok'] ?? 0);
                    $totalt = (int)($r['totalt'] ?? 0);
                    $ops = array_filter([$r['op1_name'] ?? null, $r['op2_name'] ?? null, $r['op3_name'] ?? null]);
                    $opsStr = htmlspecialchars(implode(', ', $ops) ?: '-', ENT_QUOTES, 'UTF-8');
                    $opTableHtml .= "<tr><td style='padding:5px 10px;border:1px solid #ddd;'>{$prod}</td><td style='padding:5px 10px;border:1px solid #ddd;text-align:right;'>{$lopnrStr}</td><td style='padding:5px 10px;border:1px solid #ddd;text-align:right;color:#006600;font-weight:600;'>{$ibcOk}</td><td style='padding:5px 10px;border:1px solid #ddd;text-align:right;color:#996600;'>{$burEj}</td><td style='padding:5px 10px;border:1px solid #ddd;text-align:right;color:#cc0000;'>{$ibcEj}</td><td style='padding:5px 10px;border:1px solid #ddd;text-align:right;font-weight:600;'>{$totalt}</td><td style='padding:5px 10px;border:1px solid #ddd;'>{$opsStr}</td></tr>";
                }
                $opTableHtml .= "</tbody></table>";
            }

            $dateEsc = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
            $shiftNameEsc = htmlspecialchars($shiftName, ENT_QUOTES, 'UTF-8');
            $kvalitetStr = $kvalitet !== null ? "{$kvalitet}%" : '-';
            $oeeStr = $oee !== null ? "{$oee}%" : '-';
            $ibcPerHStr = $ibcPerH !== null ? "{$ibcPerH} st/h" : '-';
            $antalRap = count($rows);
            $genererad = date('Y-m-d H:i');

            $html = '<!DOCTYPE html><html lang="sv"><head><meta charset="UTF-8"><title>Skiftsammanfattning - ' . $dateEsc . ' ' . $shiftNameEsc . '</title>';
            $html .= '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#333;background:#fff;padding:20mm;font-size:14px;line-height:1.4}@page{size:A4;margin:15mm}@media print{body{padding:0}.no-print{display:none!important}}.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}.header h1{font-size:1.4rem;color:#1a202c;margin-bottom:2px}.header h2{font-size:1.1rem;color:#555;font-weight:500}.header .right{text-align:right}.header .right h3{font-size:1.2rem;color:#2d3748;margin-bottom:2px}.header .right p{font-size:0.9rem;color:#666}hr{border:none;border-top:2px solid #e2e8f0;margin:12px 0}.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}.kpi-card{background:#f8f8f8;border:1px solid #ddd;border-radius:6px;padding:10px 8px;text-align:center}.kpi-label{font-size:0.7rem;color:#555;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px}.kpi-value{font-size:1.4rem;font-weight:700;line-height:1.2}.time-row{display:flex;gap:16px;margin-bottom:16px;padding:10px 14px;background:#f0f7ff;border:1px solid #b3d4fc;border-radius:6px}.time-item{flex:1}.time-item .label{font-size:0.75rem;color:#555;text-transform:uppercase;letter-spacing:0.03em}.time-item .value{font-size:1.1rem;font-weight:600;color:#1a202c}.section-title{font-size:0.9rem;font-weight:600;color:#2d3748;margin:16px 0 8px;padding-bottom:4px;border-bottom:1px solid #e2e8f0}.ops-box{margin-bottom:10px}.footer{margin-top:20px;border-top:1px solid #ddd;padding-top:8px;display:flex;justify-content:space-between;font-size:0.8rem;color:#666}.print-btn{position:fixed;bottom:20px;right:20px;padding:10px 24px;background:#198754;color:#fff;border:none;border-radius:6px;font-size:1rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.2)}.print-btn:hover{background:#157347}</style>';
            $html .= '</head><body>';
            $html .= '<button class="print-btn no-print" onclick="window.print()">Skriv ut / Spara PDF</button>';
            $html .= '<div class="header"><div><h1>Skiftsammanfattning</h1><h2>Rebotling - IBC-tvatt</h2></div><div class="right"><h3>NOREKO</h3><p>' . $dateEsc . '</p><p>' . $shiftNameEsc . '</p></div></div><hr>';

            // Start- och stopptid + cykeldatum
            $html .= '<div class="time-row">';
            $html .= '<div class="time-item"><div class="label">Starttid</div><div class="value">' . htmlspecialchars($startTidStr, ENT_QUOTES, 'UTF-8') . '</div></div>';
            $html .= '<div class="time-item"><div class="label">Stopptid</div><div class="value">' . htmlspecialchars($slutTidStr, ENT_QUOTES, 'UTF-8') . '</div></div>';
            $html .= '<div class="time-item"><div class="label">Drifttid</div><div class="value">' . $drifttidStr . '</div></div>';
            $html .= '<div class="time-item"><div class="label">Rasttid</div><div class="value">' . $rasttidStr . '</div></div>';
            $html .= '</div>';
            if ($cykelDatumStr !== '-' && $cykelDatumStr !== $date) {
                $html .= '<div style="margin-bottom:12px;padding:6px 14px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;font-size:0.85rem;color:#6d4c00;">OBS: Cyklerna är registrerade <strong>' . htmlspecialchars($cykelDatumStr, ENT_QUOTES, 'UTF-8') . '</strong> (skiftrapport daterad ' . $dateEsc . ')</div>';
            }

            $html .= '<div class="kpi-grid">';
            $html .= '<div class="kpi-card"><div class="kpi-label">IBC OK</div><div class="kpi-value" style="color:#0066cc;">' . $totalIbcOk . '</div></div>';
            $html .= '<div class="kpi-card"><div class="kpi-label">Kvalitet</div><div class="kpi-value" style="color:' . $kvalitetFarg . ';">' . $kvalitetStr . '</div></div>';
            $html .= '<div class="kpi-card"><div class="kpi-label">OEE</div><div class="kpi-value" style="color:' . $oeeFarg . ';">' . $oeeStr . '</div></div>';
            $html .= '<div class="kpi-card"><div class="kpi-label">IBC / timme</div><div class="kpi-value" style="color:#996600;">' . $ibcPerHStr . '</div></div>';
            $html .= '</div>';
            $html .= '<div class="section-title">Operatorer &amp; produkter</div><div class="ops-box">' . $operatorHtml . $productHtml . '</div>';
            $html .= $kommentarHtml;
            $html .= '<div class="section-title">Skiftrapporter (' . $antalRap . ' st)</div>' . $opTableHtml;
            $html .= '<div class="footer"><span>Genererad: ' . $genererad . ' | Noreko Rebotling</span><span>Antal rapporter: ' . $antalRap . '</span></div>';
            $html .= '</body></html>';

            echo $html;
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getShiftPdfSummary: ' . $e->getMessage());
            http_response_code(500);
            echo '<!DOCTYPE html><html><body><h1>Serverfel</h1><p>Kunde inte generera skiftsammanfattning</p></body></html>';
        }
    }

    // =========================================================
    // VD Veckosammanfattning — GET (JSON preview) + POST (send)
    // =========================================================

    /**
     * Beräkna veckosammanfattnings-data för en given ISO-vecka.
     * Returnerar en assoc array med all aggregerad data.
     */

    private function computeWeeklySummary(string $week): array {
        // Parsa YYYY-WXX
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
            throw new InvalidArgumentException('Ogiltigt veckoformat. Använd YYYY-WXX (t.ex. 2026-W10).');
        }
        $year = (int)$m[1];
        $wk   = (int)$m[2];
        if ($wk < 1 || $wk > 53) {
            throw new InvalidArgumentException('Veckonummer måste vara mellan 01 och 53.');
        }

        // Beräkna måndag–söndag för denna vecka och förra veckan
        $mondayThis = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
        $mondayThis->setISODate($year, $wk, 1);
        $sundayThis = clone $mondayThis;
        $sundayThis->modify('+6 days');
        $startThis = $mondayThis->format('Y-m-d');
        $endThis   = $sundayThis->format('Y-m-d');

        $mondayPrev = clone $mondayThis;
        $mondayPrev->modify('-7 days');
        $sundayPrev = clone $mondayPrev;
        $sundayPrev->modify('+6 days');
        $startPrev = $mondayPrev->format('Y-m-d');
        $endPrev   = $sundayPrev->format('Y-m-d');

        // ---- Total IBC per dag (denna vecka) ----
        $stmtDays = $this->pdo->prepare("
            SELECT
                dag,
                SUM(shift_ibc_ok)    AS ibc_ok,
                SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                SUM(shift_runtime)   AS runtime_min,
                SUM(shift_rast)      AS rast_min,
                COUNT(DISTINCT skiftraknare) AS skift_count
            FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                    MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                    MAX(COALESCE(runtime_plc, 0)) AS shift_runtime,
                    MAX(COALESCE(rasttime,   0)) AS shift_rast
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN ? AND ?
                  AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS ps
            GROUP BY dag
            ORDER BY dag ASC
        ");
        $stmtDays->execute([$startThis, $endThis]);
        $daysRows = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

        $totalIbc = 0;
        $totalIbcEj = 0;
        $totalRuntime = 0;
        $totalRast = 0;
        $totalSkift = 0;
        $bestDay = null;
        $worstDay = null;
        $dayOees = [];

        foreach ($daysRows as $d) {
            $ok = (int)$d['ibc_ok'];
            $ej = (int)$d['ibc_ej_ok'];
            $rt = (float)$d['runtime_min'];
            $ra = (float)$d['rast_min'];
            $sc = (int)$d['skift_count'];

            $totalIbc     += $ok;
            $totalIbcEj   += $ej;
            $totalRuntime += $rt;
            $totalRast    += $ra;
            $totalSkift   += $sc;

            if ($bestDay === null || $ok > $bestDay['ibc']) {
                $bestDay = ['date' => $d['dag'], 'ibc' => $ok];
            }
            if (($worstDay === null || $ok < $worstDay['ibc']) && $ok > 0) {
                $worstDay = ['date' => $d['dag'], 'ibc' => $ok];
            }

            // Day OEE
            $tot = $ok + $ej;
            if ($rt > 0) {
                $avail = min($rt / max($rt + $ra, 1), 1.0);
                $perf  = min(($tot / $rt) / (15.0 / 60.0), 1.0);
                $qual  = $tot > 0 ? $ok / $tot : 0;
                $dayOees[] = round($avail * $perf * $qual * 100, 1);
            }
        }

        // Snitt OEE
        $avgOee = count($dayOees) > 0 ? round(array_sum($dayOees) / count($dayOees), 1) : 0;

        // ---- Förra veckan (total IBC + OEE) ----
        $stmtPrev = $this->pdo->prepare("
            SELECT
                SUM(shift_ibc_ok)    AS ibc_ok,
                SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                SUM(shift_runtime)   AS runtime_min,
                SUM(shift_rast)      AS rast_min
            FROM (
                SELECT
                    skiftraknare,
                    MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                    MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                    MAX(COALESCE(runtime_plc, 0)) AS shift_runtime,
                    MAX(COALESCE(rasttime,   0)) AS shift_rast
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN ? AND ?
                  AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                GROUP BY skiftraknare
            ) AS ps
        ");
        $stmtPrev->execute([$startPrev, $endPrev]);
        $prevRow = $stmtPrev->fetch(PDO::FETCH_ASSOC);

        $prevIbc = (int)($prevRow['ibc_ok'] ?? 0);
        $prevOee = 0;
        if ($prevRow && (float)($prevRow['runtime_min'] ?? 0) > 0) {
            $pOk  = (float)$prevRow['ibc_ok'];
            $pEj  = (float)$prevRow['ibc_ej_ok'];
            $pTot = $pOk + $pEj;
            $pRt  = max((float)$prevRow['runtime_min'], 1);
            $pRa  = (float)$prevRow['rast_min'];
            $pA   = min($pRt / max($pRt + $pRa, 1), 1.0);
            $pP   = min(($pTot / $pRt) / (15.0 / 60.0), 1.0);
            $pQ   = $pTot > 0 ? $pOk / $pTot : 0;
            $prevOee = round($pA * $pP * $pQ * 100, 1);
        }

        $ibcDiffPct = $prevIbc > 0 ? round(($totalIbc - $prevIbc) / $prevIbc * 100, 1) : 0;
        $oeeDiff = round($avgOee - $prevOee, 1);
        if ($oeeDiff > 0.5) $oeeTrend = 'up';
        elseif ($oeeDiff < -0.5) $oeeTrend = 'down';
        else $oeeTrend = 'stable';

        // Drifttid / Stopptid formaterat
        $driftH = floor($totalRuntime / 60);
        $driftM = (int)($totalRuntime % 60);
        $stoppMin = $totalRast;
        $stoppH = floor($stoppMin / 60);
        $stoppM = (int)($stoppMin % 60);

        // Kvalitet %
        $totalAll = $totalIbc + $totalIbcEj;
        $kvalitet = $totalAll > 0 ? round($totalIbc / $totalAll * 100, 1) : 0;

        // ---- Per operatör (op1 = tvättplats) ----
        $stmtOps = $this->pdo->prepare("
            SELECT
                operator_id,
                SUM(shift_ibc_ok) AS ibc_ok,
                SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                SUM(shift_runtime) AS runtime_min,
                COUNT(DISTINCT skiftraknare) AS antal_skift
            FROM (
                SELECT
                    op1 AS operator_id,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                    MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                    MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN ? AND ?
                  AND op1 IS NOT NULL AND op1 > 0
                  AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                GROUP BY op1, skiftraknare
            ) AS ps
            GROUP BY operator_id
            HAVING runtime_min > 0
            ORDER BY (ibc_ok * 60.0 / runtime_min) DESC
        ");
        $stmtOps->execute([$startThis, $endThis]);
        $opRows = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

        $opIds = array_unique(array_column($opRows, 'operator_id'));
        $nameMap = [];
        if (!empty($opIds)) {
            $ph = implode(',', array_fill(0, count($opIds), '?'));
            $ns = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ($ph)");
            $ns->execute(array_values($opIds));
            foreach ($ns->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                $nameMap[(int)$nr['number']] = $nr['name'] ?? 'Okänd';
            }
        }

        $operators = [];
        foreach ($opRows as $op) {
            $opId = (int)$op['operator_id'];
            $ok   = (float)$op['ibc_ok'];
            $ej   = (float)$op['ibc_ej_ok'];
            $rt   = max((float)$op['runtime_min'], 1);
            $ibcH = round($ok * 60.0 / $rt, 1);
            $oTot = $ok + $ej;
            $qual = $oTot > 0 ? round($ok / $oTot * 100, 1) : 0;

            // Bonus-tier baserat på IBC/h
            if ($ibcH >= 15) $bonusTier = 'Guld';
            elseif ($ibcH >= 12) $bonusTier = 'Silver';
            elseif ($ibcH >= 10) $bonusTier = 'Brons';
            else $bonusTier = '-';

            $operators[] = [
                'id'          => $opId,
                'name'        => $nameMap[$opId] ?? 'Op #' . $opId,
                'ibc_total'   => (int)$ok,
                'ibc_h'       => $ibcH,
                'kvalitet'    => $qual,
                'bonus_tier'  => $bonusTier,
                'antal_skift' => (int)$op['antal_skift'],
            ];
        }

        // ---- Topp 3 stopporsaker ----
        $topStops = [];
        try {
            $stmtStops = $this->pdo->prepare("
                SELECT
                    sr.name AS orsak,
                    sr.category,
                    COUNT(*) AS antal,
                    COALESCE(SUM(sl.duration_minutes), 0) AS total_min
                FROM stoppage_log sl
                JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                WHERE DATE(sl.created_at) BETWEEN ? AND ?
                GROUP BY sr.name, sr.category
                ORDER BY total_min DESC
                LIMIT 3
            ");
            $stmtStops->execute([$startThis, $endThis]);
            $topStops = $stmtStops->fetchAll(PDO::FETCH_ASSOC);
            foreach ($topStops as &$s) {
                $s['total_min'] = (int)$s['total_min'];
                $s['antal'] = (int)$s['antal'];
            }
            unset($s);
        } catch (Exception $e) {
            // stoppage_log kanske inte finns
            error_log('RebotlingAnalyticsController::weeklySummary stoppage: ' . $e->getMessage());
        }

        return [
            'week'       => $week,
            'start_date' => $startThis,
            'end_date'   => $endThis,
            'total_ibc'  => $totalIbc,
            'prev_ibc'   => $prevIbc,
            'ibc_diff_pct' => $ibcDiffPct,
            'avg_oee'    => $avgOee,
            'prev_oee'   => $prevOee,
            'oee_diff'   => $oeeDiff,
            'oee_trend'  => $oeeTrend,
            'kvalitet'   => $kvalitet,
            'best_day'   => $bestDay,
            'worst_day'  => $worstDay,
            'drifttid'   => sprintf('%d:%02d', $driftH, $driftM),
            'drifttid_min' => (int)$totalRuntime,
            'stopptid'   => sprintf('%d:%02d', $stoppH, $stoppM),
            'stopptid_min' => (int)$stoppMin,
            'antal_skift' => $totalSkift,
            'operators'  => $operators,
            'top_stops'  => $topStops,
            'days'       => $daysRows,
        ];
    }

    /**
     * GET ?action=rebotling&run=weekly-summary-email&week=YYYY-WXX
     * Returnerar JSON-preview av veckosammanfattningen.
     */

    public function getWeeklySummaryEmail(): void {
        $week = trim($_GET['week'] ?? '');
        if (empty($week)) {
            // Default: förra veckan
            $dt = new DateTime('last monday', new DateTimeZone('Europe/Stockholm'));
            $dt->modify('-7 days');
            $week = $dt->format('o') . '-W' . str_pad($dt->format('W'), 2, '0', STR_PAD_LEFT);
        }

        try {
            $summary = $this->computeWeeklySummary($week);
            echo json_encode(['success' => true, 'data' => $summary], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            error_log('RebotlingAnalyticsController::getWeeklySummaryEmail validation: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Ogiltigt veckoformat. Anvand YYYY-WXX (t.ex. 2026-W10).'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getWeeklySummaryEmail: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid generering av veckosammanfattning'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=send-weekly-summary
     * Genererar HTML-email och skickar via mail().
     */

    public function sendWeeklySummaryEmail(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $week = $data['week'] ?? '';

        if (empty($week)) {
            $dt = new DateTime('last monday', new DateTimeZone('Europe/Stockholm'));
            $dt->modify('-7 days');
            $week = $dt->format('o') . '-W' . str_pad($dt->format('W'), 2, '0', STR_PAD_LEFT);
        }

        try {
            $summary = $this->computeWeeklySummary($week);

            // Hämta mottagare
            $this->ensureNotificationEmailsColumn();
            $row = $this->pdo->query(
                "SELECT notification_emails FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            $emailsRaw = $row['notification_emails'] ?? '';
            if (empty(trim($emailsRaw))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga e-postmottagare konfigurerade. Konfigurera under E-postnotifikationer.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $recipients = [];
            $parts = array_map('trim', explode(';', str_replace(',', ';', $emailsRaw)));
            foreach ($parts as $email) {
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }

            if (count($recipients) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Inga giltiga e-postadresser konfigurerade.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $html = $this->buildWeeklySummaryHtml($summary);

            $subject = "=?UTF-8?B?" . base64_encode("MauserDB Veckorapport — v." . substr($week, 6)) . "?=";
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: MauserDB <noreply@noreko.se>\r\n";

            $sentTo = [];
            foreach ($recipients as $email) {
                if (mail($email, $subject, $html, $headers)) {
                    $sentTo[] = $email;
                } else {
                    error_log("RebotlingAnalyticsController::sendWeeklySummaryEmail: Kunde inte skicka e-post till mottagare");
                }
            }

            if (count($sentTo) === 0) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Kunde inte skicka till nagon mottagare. Kontrollera serverinställningar.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'success'    => true,
                'recipients' => $sentTo,
                'week'       => $week,
            ], JSON_UNESCAPED_UNICODE);

        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            error_log('RebotlingAnalyticsController::sendWeeklySummaryEmail validation: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Ogiltigt veckoformat. Anvand YYYY-WXX (t.ex. 2026-W10).'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::sendWeeklySummaryEmail: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid sändning av veckorapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Bygg HTML-email med inline CSS for veckosammanfattningen.
     */

    private function buildWeeklySummaryHtml(array $s): string {
        $week     = htmlspecialchars($s['week'], ENT_QUOTES, 'UTF-8');
        $startD   = htmlspecialchars($s['start_date'], ENT_QUOTES, 'UTF-8');
        $endD     = htmlspecialchars($s['end_date'], ENT_QUOTES, 'UTF-8');
        $totalIbc = (int)$s['total_ibc'];
        $prevIbc  = (int)$s['prev_ibc'];
        $diffPct  = (float)$s['ibc_diff_pct'];
        $avgOee   = (float)$s['avg_oee'];
        $oeeDiff  = (float)$s['oee_diff'];
        $oeeTrend = $s['oee_trend'];
        $kvalitet = (float)$s['kvalitet'];
        $drifttid = htmlspecialchars($s['drifttid'], ENT_QUOTES, 'UTF-8');
        $stopptid = htmlspecialchars($s['stopptid'], ENT_QUOTES, 'UTF-8');
        $skift    = (int)$s['antal_skift'];

        // Trendpilar
        $diffSign  = $diffPct >= 0 ? '+' : '';
        $diffColor = $diffPct >= 0 ? '#38a169' : '#e53e3e';

        $oeeTrendArrow = $oeeTrend === 'up' ? '&#8593;' : ($oeeTrend === 'down' ? '&#8595;' : '&#8594;');
        $oeeTrendColor = $oeeTrend === 'up' ? '#38a169' : ($oeeTrend === 'down' ? '#e53e3e' : '#718096');

        $kvalColor = $kvalitet >= 95 ? '#38a169' : ($kvalitet >= 85 ? '#d69e2e' : '#e53e3e');

        // Bästa/sämsta dag
        $bestDayText = $s['best_day'] ? htmlspecialchars($s['best_day']['date'], ENT_QUOTES, 'UTF-8') . ' (' . (int)$s['best_day']['ibc'] . ' IBC)' : 'Ingen data';
        $worstDayText = $s['worst_day'] ? htmlspecialchars($s['worst_day']['date'], ENT_QUOTES, 'UTF-8') . ' (' . (int)$s['worst_day']['ibc'] . ' IBC)' : 'Ingen data';

        // Operatörstabell
        $opsHtml = '';
        $i = 0;
        foreach ($s['operators'] as $op) {
            $bgColor = $i % 2 === 0 ? '#f7fafc' : '#ffffff';
            $tierColor = $op['bonus_tier'] === 'Guld' ? '#d69e2e' : ($op['bonus_tier'] === 'Silver' ? '#718096' : ($op['bonus_tier'] === 'Brons' ? '#c05621' : '#a0aec0'));
            $qColor = $op['kvalitet'] >= 95 ? '#38a169' : ($op['kvalitet'] >= 85 ? '#d69e2e' : '#e53e3e');
            $opsHtml .= '<tr style="background:' . $bgColor . ';">'
                . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; font-weight:600; color:#2d3748;">' . htmlspecialchars($op['name'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; text-align:right; color:#2d3748;">' . $op['ibc_total'] . '</td>'
                . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; text-align:right; font-weight:700; color:#2d3748;">' . $op['ibc_h'] . '</td>'
                . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; text-align:right; color:' . $qColor . ';">' . $op['kvalitet'] . '%</td>'
                . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; text-align:center; color:' . $tierColor . '; font-weight:700;">' . htmlspecialchars($op['bonus_tier'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
            $i++;
        }

        // Stopporsaker
        $stopsHtml = '';
        if (!empty($s['top_stops'])) {
            foreach ($s['top_stops'] as $idx => $st) {
                $stBg = $idx % 2 === 0 ? '#f7fafc' : '#ffffff';
                $stH = floor($st['total_min'] / 60);
                $stM = $st['total_min'] % 60;
                $stopsHtml .= '<tr style="background:' . $stBg . ';">'
                    . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; color:#2d3748;">' . htmlspecialchars($st['orsak'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; text-align:right; color:#2d3748;">' . $st['antal'] . '</td>'
                    . '<td style="padding:8px 12px; border-bottom:1px solid #e2e8f0; text-align:right; font-weight:700; color:#e53e3e;">' . $stH . ':' . str_pad($stM, 2, '0', STR_PAD_LEFT) . '</td>'
                    . '</tr>';
            }
        } else {
            $stopsHtml = '<tr><td colspan="3" style="padding:12px; text-align:center; color:#718096;">Inga stopporsaker registrerade denna vecka.</td></tr>';
        }

        $genererad = date('Y-m-d H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="sv">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>MauserDB Veckorapport {$week}</title></head>
<body style="margin:0; padding:0; font-family:Arial,Helvetica,sans-serif; background:#f0f2f5; -webkit-text-size-adjust:100%;">
<div style="max-width:600px; margin:20px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);">

  <!-- Header -->
  <div style="background:#1a202c; color:#e2e8f0; padding:24px 28px; text-align:center;">
    <h1 style="margin:0; font-size:22px; font-weight:800; letter-spacing:0.5px;">MauserDB Veckorapport</h1>
    <p style="margin:8px 0 0; font-size:14px; color:#a0aec0;">Vecka {$week} &mdash; {$startD} till {$endD}</p>
  </div>

  <!-- KPI-kort 2x2 -->
  <div style="padding:20px 24px 0;">
    <table style="width:100%; border-collapse:separate; border-spacing:10px;">
      <tr>
        <!-- Total IBC -->
        <td style="width:50%; padding:14px 16px; background:#f7fafc; border-radius:8px; border-left:4px solid #4299e1; vertical-align:top;">
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#718096; margin-bottom:4px;">Total IBC</div>
          <div style="font-size:28px; font-weight:800; color:#2d3748; line-height:1.1;">{$totalIbc}</div>
          <div style="font-size:12px; margin-top:4px; color:{$diffColor}; font-weight:600;">{$diffSign}{$diffPct}% vs f.v. ({$prevIbc})</div>
        </td>
        <!-- Snitt OEE -->
        <td style="width:50%; padding:14px 16px; background:#f7fafc; border-radius:8px; border-left:4px solid #48bb78; vertical-align:top;">
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#718096; margin-bottom:4px;">Snitt OEE</div>
          <div style="font-size:28px; font-weight:800; color:#2d3748; line-height:1.1;">{$avgOee}%</div>
          <div style="font-size:12px; margin-top:4px; color:{$oeeTrendColor}; font-weight:600;">{$oeeTrendArrow} {$oeeDiff}pp vs f.v.</div>
        </td>
      </tr>
      <tr>
        <!-- Bästa dag -->
        <td style="width:50%; padding:14px 16px; background:#f7fafc; border-radius:8px; border-left:4px solid #d69e2e; vertical-align:top;">
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#718096; margin-bottom:4px;">Bästa dag</div>
          <div style="font-size:16px; font-weight:700; color:#2d3748;">{$bestDayText}</div>
        </td>
        <!-- Drifttid vs Stopptid -->
        <td style="width:50%; padding:14px 16px; background:#f7fafc; border-radius:8px; border-left:4px solid #e53e3e; vertical-align:top;">
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#718096; margin-bottom:4px;">Drifttid / Stopptid</div>
          <div style="font-size:16px; font-weight:700; color:#2d3748;">{$drifttid} / {$stopptid}</div>
          <div style="font-size:12px; margin-top:4px; color:#718096;">{$skift} skift körda</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- Extra KPI-rad -->
  <div style="padding:8px 24px 16px;">
    <table style="width:100%; border-collapse:separate; border-spacing:10px;">
      <tr>
        <td style="width:50%; padding:10px 16px; background:#f7fafc; border-radius:8px; vertical-align:top;">
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#718096;">Kvalitet</div>
          <div style="font-size:20px; font-weight:800; color:{$kvalColor};">{$kvalitet}%</div>
        </td>
        <td style="width:50%; padding:10px 16px; background:#f7fafc; border-radius:8px; vertical-align:top;">
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.8px; color:#718096;">Sämsta dag</div>
          <div style="font-size:14px; font-weight:600; color:#e53e3e;">{$worstDayText}</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- Operatörstabell -->
  <div style="padding:0 24px 16px;">
    <h3 style="font-size:15px; color:#2d3748; margin:0 0 10px; font-weight:700;">Operatörer</h3>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
      <thead>
        <tr style="background:#edf2f7;">
          <th style="padding:8px 12px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">Namn</th>
          <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">IBC</th>
          <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">IBC/h</th>
          <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">Kvalitet</th>
          <th style="padding:8px 12px; text-align:center; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">Bonus</th>
        </tr>
      </thead>
      <tbody>
        {$opsHtml}
      </tbody>
    </table>
  </div>

  <!-- Topp 3 stopporsaker -->
  <div style="padding:0 24px 20px;">
    <h3 style="font-size:15px; color:#2d3748; margin:0 0 10px; font-weight:700;">Topp 3 stopporsaker</h3>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
      <thead>
        <tr style="background:#edf2f7;">
          <th style="padding:8px 12px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">Orsak</th>
          <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">Antal</th>
          <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#718096; border-bottom:2px solid #e2e8f0;">Total tid</th>
        </tr>
      </thead>
      <tbody>
        {$stopsHtml}
      </tbody>
    </table>
  </div>

  <!-- Footer -->
  <div style="background:#f7fafc; padding:16px 24px; text-align:center; border-top:1px solid #e2e8f0;">
    <p style="margin:0; font-size:12px; color:#a0aec0;">Genererad automatiskt av MauserDB &mdash; {$genererad}</p>
  </div>

</div>
</body>
</html>
HTML;
    }


    // ================================================================
    // Manuella annotationer (driftstopp, helgdagar, händelser)
    // ================================================================

    private function ensureAnnotationsTable(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_annotations` (
                `id`          INT          NOT NULL AUTO_INCREMENT,
                `datum`       DATE         NOT NULL,
                `typ`         ENUM('driftstopp','helgdag','handelse','ovrigt') NOT NULL DEFAULT 'ovrigt',
                `titel`       VARCHAR(120) NOT NULL,
                `beskrivning` TEXT         NULL DEFAULT NULL,
                `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_datum` (`datum`),
                INDEX `idx_typ`   (`typ`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * GET ?action=rebotling&run=annotations-list&start=YYYY-MM-DD&end=YYYY-MM-DD[&typ=driftstopp]
     */
    public function getAnnotationsList(): void {
        $start = trim($_GET['start'] ?? '');
        $end   = trim($_GET['end']   ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltiga datumparametrar (YYYY-MM-DD)'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // Validera att start <= end
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        try {
            $this->ensureAnnotationsTable();

            $sql = "SELECT id, datum, typ, titel, beskrivning, created_at
                    FROM rebotling_annotations
                    WHERE datum BETWEEN :start AND :end";
            $params = [':start' => $start, ':end' => $end];

            $typ = $_GET['typ'] ?? '';
            if ($typ !== '' && in_array($typ, ['driftstopp', 'helgdag', 'handelse', 'ovrigt'], true)) {
                $sql .= " AND typ = :typ";
                $params[':typ'] = $typ;
            }

            $sql .= " ORDER BY datum ASC, created_at ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'annotations' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getAnnotationsList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta annotationer'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=annotation-create
     * Body: datum, typ, titel, beskrivning (optional)
     * Kräver admin-session.
     */
    public function createAnnotation(): void {
        $datum       = trim($_POST['datum'] ?? '');
        $typ         = trim($_POST['typ'] ?? '');
        $titel       = strip_tags(trim($_POST['titel'] ?? ''));
        $beskrivning = strip_tags(trim($_POST['beskrivning'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum (YYYY-MM-DD)'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!in_array($typ, ['driftstopp', 'helgdag', 'handelse', 'ovrigt'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig typ'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($titel === '' || mb_strlen($titel) > 120) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Titel krävs (max 120 tecken)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->ensureAnnotationsTable();
            $stmt = $this->pdo->prepare("
                INSERT INTO rebotling_annotations (datum, typ, titel, beskrivning)
                VALUES (:datum, :typ, :titel, :beskrivning)
            ");
            $stmt->execute([
                ':datum'       => $datum,
                ':typ'         => $typ,
                ':titel'       => $titel,
                ':beskrivning' => $beskrivning ?: null,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::createAnnotation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte skapa annotation'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=annotation-delete
     * Body: id
     * Kräver admin-session.
     */
    public function deleteAnnotation(): void {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->ensureAnnotationsTable();
            $stmt = $this->pdo->prepare("DELETE FROM rebotling_annotations WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $deleted = $stmt->rowCount();
            echo json_encode(['success' => true, 'deleted' => $deleted], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::deleteAnnotation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort annotation'], JSON_UNESCAPED_UNICODE);
        }
    }


    // ================================================================
    //  Realtids-OEE gauge — endpoint: run=realtime-oee&period=today|7d|30d
    // ================================================================

    public function getRealtimeOee() {
        $period = $_GET['period'] ?? 'today';
        $allowed = ['today', '7d', '30d'];
        if (!in_array($period, $allowed, true)) $period = 'today';

        try {
            // Bestäm datumfilter
            $dateFilter = match($period) {
                'today' => "DATE(r.datum) = CURDATE()",
                '7d'    => "r.datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                '30d'   => "r.datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                default => "DATE(r.datum) = CURDATE()"
            };

            $periodLabel = match($period) {
                'today' => 'Idag',
                '7d'    => 'Senaste 7 dagar',
                '30d'   => 'Senaste 30 dagar',
                default => 'Idag'
            };

            // Aggregera per skift (kumulativa PLC-värden → MAX per skiftraknare, sedan SUM)
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*)                 AS shifts,
                    SUM(shift_ibc_ok)        AS total_ibc_ok,
                    SUM(shift_ibc_ej_ok)     AS total_ibc_ej_ok,
                    SUM(shift_runtime)       AS total_runtime_min,
                    SUM(shift_rast)          AS total_rast_min
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc r
                    WHERE $dateFilter
                      AND ibc_ok IS NOT NULL
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $ibcOk    = (int)($data['total_ibc_ok']      ?? 0);
            $ibcEjOk  = (int)($data['total_ibc_ej_ok']   ?? 0);
            $totalIBC = $ibcOk + $ibcEjOk;

            $runtimeMin = (float)($data['total_runtime_min'] ?? 0);
            $rastMin    = (float)($data['total_rast_min']    ?? 0);

            // Stopptid från stoppage_log (om tabell finns)
            $stoppageMin = 0;
            try {
                $stmtStop = $this->pdo->prepare("
                    SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))), 0) AS stop_min
                    FROM stoppage_log s
                    WHERE " . str_replace('r.datum', 's.start_time', $dateFilter)
                );
                $stmtStop->execute();
                $stoppageMin = (float)$stmtStop->fetchColumn();
            } catch (Exception) {
                // stoppage_log finns kanske inte — falla tillbaka till rast som proxy
                $stoppageMin = $rastMin;
            }

            // Operativ tid (runtime_plc exkluderar redan rast)
            $operatingMin = max($runtimeMin, 1);
            // Planerad tid = driftstid + rasttid
            $plannedMin = max($runtimeMin + $rastMin, 1);

            // Ideal cykeltid: hämta median från senaste 30 dagarnas data
            $idealRatePerMin = 15.0 / 60.0; // fallback: 15 IBC/h
            try {
                $stmtMedian = $this->pdo->query("
                    SELECT AVG(ibc_per_min) AS median_rate FROM (
                        SELECT
                            shift_ibc / GREATEST(shift_runtime, 1) AS ibc_per_min,
                            ROW_NUMBER() OVER (ORDER BY ibc_per_min) AS rn,
                            COUNT(*) OVER () AS cnt
                        FROM (
                            SELECT
                                skiftraknare,
                                MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ibc,
                                MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                            FROM rebotling_ibc
                            WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                              AND ibc_ok IS NOT NULL
                              AND skiftraknare IS NOT NULL
                            GROUP BY skiftraknare
                            HAVING shift_runtime > 30
                        ) AS shifts
                    ) AS ranked
                    WHERE rn BETWEEN FLOOR((cnt+1)/2) AND CEIL((cnt+1)/2)
                ");
                $medianRate = (float)$stmtMedian->fetchColumn();
                if ($medianRate > 0) {
                    // Ideal = 120% av median (top-performance benchmark)
                    $idealRatePerMin = $medianRate * 1.2;
                }
            } catch (Exception) {
                // använd fallback
            }

            // === OEE-beräkning ===
            // Tillgänglighet = Operativ tid / Planerad tid
            $availability = min($operatingMin / $plannedMin, 1.0);

            // Prestanda = (Antal IBC / Operativ tid) / Ideal rate
            $actualRate = $totalIBC / max($operatingMin, 1);
            $performance = min($actualRate / max($idealRatePerMin, 0.001), 1.5);
            // Cap vid 150% men visa verkligt värde (>100% = bättre än benchmark)

            // Kvalitet = Godkända / Totalt
            $quality = $totalIBC > 0 ? $ibcOk / $totalIBC : 0;

            // OEE = A × P × Q (cappa vid 100%)
            $oee = min($availability * min($performance, 1.0) * $quality, 1.0);

            echo json_encode([
                'success' => true,
                'data' => [
                    'period'              => $period,
                    'period_label'        => $periodLabel,
                    'oee_percent'         => round($oee * 100, 1),
                    'availability_percent'=> round($availability * 100, 1),
                    'performance_percent' => round(min($performance, 1.0) * 100, 1),
                    'quality_percent'     => round($quality * 100, 1),
                    'ibc_count'           => $totalIBC,
                    'ibc_approved'        => $ibcOk,
                    'ibc_rejected'        => $ibcEjOk,
                    'stoppage_minutes'    => round($stoppageMin, 0),
                    'runtime_hours'       => round($runtimeMin / 60, 1),
                    'planned_hours'       => round($plannedMin / 60, 1),
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getRealtimeOee: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte beräkna realtids-OEE'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // PRODUKTIONSMÅL – GET progress
    // =========================================================

    private function ensureProductionGoalsTable(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_production_goals` (
                `id`           INT          NOT NULL AUTO_INCREMENT,
                `period_type`  ENUM('daily','weekly') NOT NULL DEFAULT 'daily',
                `target_count` INT          NOT NULL DEFAULT 0,
                `created_by`   INT          NULL DEFAULT NULL,
                `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_period_type` (`period_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Standardvärden om tabellen precis skapades
        $this->pdo->exec("
            INSERT IGNORE INTO `rebotling_production_goals` (`id`, `period_type`, `target_count`)
            VALUES (1, 'daily', 200), (2, 'weekly', 1000)
        ");
    }

    public function getProductionGoalProgress(): void {
        try {
            $this->ensureProductionGoalsTable();

            $period = trim($_GET['period'] ?? 'today');
            if (!in_array($period, ['today', 'week'], true)) {
                $period = 'today';
            }
            $now    = new DateTime('now', new DateTimeZone('Europe/Stockholm'));

            if ($period === 'week') {
                // Veckamål
                $goalRow = $this->pdo->query(
                    "SELECT target_count FROM rebotling_production_goals WHERE period_type='weekly' ORDER BY id DESC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                $target = (int)($goalRow['target_count'] ?? 1000);

                // Veckostart (måndag)
                $monday = clone $now;
                $dow = (int)$monday->format('N'); // 1=mån, 7=sön
                $monday->modify('-' . ($dow - 1) . ' days');
                $monday->setTime(0, 0, 0);
                $sunday = clone $monday;
                $sunday->modify('+6 days');
                $sunday->setTime(23, 59, 59);

                $weekStart = $monday->format('Y-m-d 00:00:00');
                $weekEnd   = $sunday->format('Y-m-d 23:59:59');

                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE datum BETWEEN ? AND ? AND produktion_procent > 0"
                );
                $stmt->execute([$weekStart, $weekEnd]);
                $actual = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

                // Tid kvar i veckan
                $endOfWeek = $sunday;
                $secRemaining = max(0, $endOfWeek->getTimestamp() - $now->getTimestamp());

                // Streak: antal veckor i rad som veckamålet nåtts
                $streak = $this->calcWeeklyStreak($target);

                echo json_encode([
                    'success'          => true,
                    'period'           => 'week',
                    'target'           => $target,
                    'actual'           => $actual,
                    'percentage'       => $target > 0 ? round($actual / $target * 100, 1) : 0,
                    'remaining'        => max(0, $target - $actual),
                    'time_remaining_seconds' => $secRemaining,
                    'streak'           => $streak,
                    'period_label'     => 'Denna vecka',
                ], JSON_UNESCAPED_UNICODE);

            } else {
                // Dagsmål (today)
                $goalRow = $this->pdo->query(
                    "SELECT target_count FROM rebotling_production_goals WHERE period_type='daily' ORDER BY id DESC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                $target = (int)($goalRow['target_count'] ?? 200);

                $today = $now->format('Y-m-d');

                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE DATE(datum) = ? AND produktion_procent > 0"
                );
                $stmt->execute([$today]);
                $actual = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

                // Tid kvar av "skiftet" — vi räknar till midnatt
                $endOfDay = clone $now;
                $endOfDay->setTime(23, 59, 59);
                $secRemaining = max(0, $endOfDay->getTimestamp() - $now->getTimestamp());

                // Streak: antal dagar i rad som dagsmålet nåtts (exklusive idag)
                $streak = $this->calcDailyStreak($target, $today);

                echo json_encode([
                    'success'          => true,
                    'period'           => 'today',
                    'target'           => $target,
                    'actual'           => $actual,
                    'percentage'       => $target > 0 ? round($actual / $target * 100, 1) : 0,
                    'remaining'        => max(0, $target - $actual),
                    'time_remaining_seconds' => $secRemaining,
                    'streak'           => $streak,
                    'period_label'     => 'Idag',
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getProductionGoalProgress: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta produktionsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function calcDailyStreak(int $target, string $today): int {
        $streak = 0;
        try {
            $date = new DateTime($today, new DateTimeZone('Europe/Stockholm'));
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::calcDailyStreak — ogiltigt datum: ' . $e->getMessage());
            return 0;
        }
        $date->modify('-1 day'); // Börja med igår
        $startDate = (clone $date)->modify('-364 days')->format('Y-m-d');

        // Hämta alla dagars antal i en enda query istället för en per dag
        $stmt = $this->pdo->prepare(
            "SELECT DATE(datum) AS d, COUNT(*) AS cnt
             FROM rebotling_ibc
             WHERE DATE(datum) BETWEEN ? AND ? AND produktion_procent > 0
             GROUP BY DATE(datum)
             ORDER BY DATE(datum) DESC"
        );
        $stmt->execute([$startDate, $date->format('Y-m-d')]);
        $dayCounts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dayCounts[$row['d']] = (int)$row['cnt'];
        }

        for ($i = 0; $i < 365; $i++) {
            $d = $date->format('Y-m-d');
            $cnt = $dayCounts[$d] ?? 0;
            if ($cnt >= $target) {
                $streak++;
                $date->modify('-1 day');
            } else {
                break;
            }
        }
        return $streak;
    }

    private function calcWeeklyStreak(int $target): int {
        $streak = 0;
        $now    = new DateTime('now', new DateTimeZone('Europe/Stockholm'));
        $dow    = (int)$now->format('N');
        $monday = clone $now;
        $monday->modify('-' . ($dow - 1 + 7) . ' days'); // Föregående veckas måndag
        $monday->setTime(0, 0, 0);

        // Hämta antal per vecka i en enda query istället för en per vecka
        $startDate = (clone $monday)->modify('-51 weeks')->format('Y-m-d');
        $endDate   = (clone $monday)->modify('+6 days')->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT DATE(DATE_SUB(datum, INTERVAL (WEEKDAY(datum)) DAY)) AS week_monday, COUNT(*) AS cnt
             FROM rebotling_ibc
             WHERE datum BETWEEN ? AND ? AND produktion_procent > 0
             GROUP BY week_monday
             ORDER BY week_monday DESC"
        );
        $stmt->execute([$startDate, $endDate]);
        $weekCounts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $weekCounts[$row['week_monday']] = (int)$row['cnt'];
        }

        for ($i = 0; $i < 52; $i++) {
            $mondayKey = $monday->format('Y-m-d');
            $cnt = $weekCounts[$mondayKey] ?? 0;

            if ($cnt >= $target) {
                $streak++;
                $monday->modify('-7 days');
            } else {
                break;
            }
        }
        return $streak;
    }

    // =========================================================
    // PRODUKTIONSMÅL – POST set goal (admin)
    // =========================================================

    public function setProductionGoal(): void {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin-behörighet krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->ensureProductionGoalsTable();

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            // Stöd även form-encoded
            if (empty($input)) {
                parse_str(file_get_contents('php://input'), $input);
            }

            $periodType  = trim($input['period_type'] ?? '');
            $targetCount = (int)($input['target_count'] ?? 0);

            if (!in_array($periodType, ['daily', 'weekly'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig period_type. Tillåtna värden: daily, weekly'], JSON_UNESCAPED_UNICODE);
                return;
            }
            if ($targetCount <= 0 || $targetCount > 100000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'target_count måste vara mellan 1 och 100 000'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $userId = (int)$_SESSION['user_id'];

            $this->pdo->beginTransaction();

            // Uppdatera befintlig rad eller infoga ny (med FOR UPDATE för att undvika race condition)
            $existing = $this->pdo->prepare(
                "SELECT id FROM rebotling_production_goals WHERE period_type = ? ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $existing->execute([$periodType]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $stmt = $this->pdo->prepare(
                    "UPDATE rebotling_production_goals SET target_count = ?, created_by = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$targetCount, $userId, $row['id']]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO rebotling_production_goals (period_type, target_count, created_by) VALUES (?, ?, ?)"
                );
                $stmt->execute([$periodType, $targetCount, $userId]);
            }

            $this->pdo->commit();

            $label = $periodType === 'daily' ? 'Dagsmål' : 'Veckamål';
            echo json_encode([
                'success' => true,
                'message' => "{$label} sparat: {$targetCount} IBC",
                'period_type'  => $periodType,
                'target_count' => $targetCount,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RebotlingAnalyticsController::setProductionGoal: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara produktionsmål'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=shift-day-night&days=30
     * Jämför dagskift (06:00–22:00) vs nattskift (22:00–06:00).
     * Returnerar KPI:er per skifttyp samt daglig tidsserie med båda skiftens värden.
     */
    public function getShiftDayNightComparison() {
        $days = isset($_GET['days']) ? max(1, min(365, intval($_GET['days']))) : 30;
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            /*
             * Aggregera rebotling_ibc per dag + skiftraknare (ett skift = en skiftraknare).
             * Klassificera skifttyp utifrån starttimmen för skiftets första rad:
             *   dagskift  = HOUR(MIN(datum)) BETWEEN 6 AND 21   (06:00–21:59)
             *   nattskift = HOUR(MIN(datum)) NOT BETWEEN 6 AND 21
             *
             * Kumulativa värden per skift: MAX(ibc_ok), MAX(ibc_ej_ok), MAX(bur_ej_ok), MAX(runtime_plc).
             */
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)                   AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok,    0))   AS ibc_ok,
                    MAX(COALESCE(ibc_ej_ok, 0))   AS ibc_ej_ok,
                    MAX(COALESCE(bur_ej_ok, 0))   AS bur_ej_ok,
                    MAX(COALESCE(runtime_plc, 0)) AS runtime_min,
                    MAX(COALESCE(rasttime, 0))    AS rast_min,
                    AVG(COALESCE(runtime_plc, 0)) AS avg_cykeltid,
                    HOUR(MIN(datum))              AS start_hour
                FROM rebotling_ibc
                WHERE DATE(datum) >= ?
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
                ORDER BY dag ASC, skiftraknare ASC
            ");
            $stmt->execute([$startDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aggregerat per skifttyp (dag/natt)
            $dagAgg  = ['ibc_ok' => 0, 'ibc_ej_ok' => 0, 'bur_ej_ok' => 0, 'runtime_min' => 0, 'rast_min' => 0, 'cykeltid_sum' => 0.0, 'cykeltid_count' => 0, 'skift_count' => 0];
            $nattAgg = ['ibc_ok' => 0, 'ibc_ej_ok' => 0, 'bur_ej_ok' => 0, 'runtime_min' => 0, 'rast_min' => 0, 'cykeltid_sum' => 0.0, 'cykeltid_count' => 0, 'skift_count' => 0];

            // Daglig tidsserie indexerad på datum
            $dagByDate  = [];
            $nattByDate = [];

            foreach ($rows as $r) {
                $ibcOk   = (int)$r['ibc_ok'];
                $ibcEjOk = (int)$r['ibc_ej_ok'];
                $burEjOk = (int)$r['bur_ej_ok'];
                $totalt  = $ibcOk + $ibcEjOk + $burEjOk;
                $rtMin   = (float)$r['runtime_min'];
                $rastMin = (float)$r['rast_min'];
                $avgCykel = ($ibcOk > 0 && $rtMin > 0) ? round($rtMin / $ibcOk, 2) : 0.0;

                // Klassificera: 06 <= hour < 22 → dag, annars natt
                $startHour = (int)$r['start_hour'];
                $isDag = ($startHour >= 6 && $startHour < 22);
                $dag = $r['dag'];

                if ($isDag) {
                    $dagAgg['ibc_ok']       += $ibcOk;
                    $dagAgg['ibc_ej_ok']    += $ibcEjOk;
                    $dagAgg['bur_ej_ok']    += $burEjOk;
                    $dagAgg['runtime_min']  += $rtMin;
                    $dagAgg['rast_min']     += $rastMin;
                    $dagAgg['skift_count']  += 1;
                    if ($avgCykel > 0) {
                        $dagAgg['cykeltid_sum']   += $avgCykel;
                        $dagAgg['cykeltid_count'] += 1;
                    }
                } else {
                    $nattAgg['ibc_ok']       += $ibcOk;
                    $nattAgg['ibc_ej_ok']    += $ibcEjOk;
                    $nattAgg['bur_ej_ok']    += $burEjOk;
                    $nattAgg['runtime_min']  += $rtMin;
                    $nattAgg['rast_min']     += $rastMin;
                    $nattAgg['skift_count']  += 1;
                    if ($avgCykel > 0) {
                        $nattAgg['cykeltid_sum']   += $avgCykel;
                        $nattAgg['cykeltid_count'] += 1;
                    }
                }

                // Daglig tidsserie
                if ($isDag) {
                    if (!isset($dagByDate[$dag])) {
                        $dagByDate[$dag] = ['ibc_ok' => 0, 'totalt' => 0, 'cykeltid_sum' => 0.0, 'cykeltid_count' => 0, 'runtime_min' => 0];
                    }
                    $dagByDate[$dag]['ibc_ok']       += $ibcOk;
                    $dagByDate[$dag]['totalt']        += $totalt;
                    $dagByDate[$dag]['runtime_min']   += $rtMin;
                    if ($avgCykel > 0) {
                        $dagByDate[$dag]['cykeltid_sum']   += $avgCykel;
                        $dagByDate[$dag]['cykeltid_count'] += 1;
                    }
                } else {
                    if (!isset($nattByDate[$dag])) {
                        $nattByDate[$dag] = ['ibc_ok' => 0, 'totalt' => 0, 'cykeltid_sum' => 0.0, 'cykeltid_count' => 0, 'runtime_min' => 0];
                    }
                    $nattByDate[$dag]['ibc_ok']       += $ibcOk;
                    $nattByDate[$dag]['totalt']        += $totalt;
                    $nattByDate[$dag]['runtime_min']   += $rtMin;
                    if ($avgCykel > 0) {
                        $nattByDate[$dag]['cykeltid_sum']   += $avgCykel;
                        $nattByDate[$dag]['cykeltid_count'] += 1;
                    }
                }
            }

            // Hjälp-funktion: beräkna KPI-sammanfattning
            $calcKpi = function(array $agg): array {
                $ibcOk   = $agg['ibc_ok'];
                $totalt  = $ibcOk + $agg['ibc_ej_ok'] + $agg['bur_ej_ok'];
                $rtMin   = $agg['runtime_min'];
                $rastMin = $agg['rast_min'];
                $nSkift  = max($agg['skift_count'], 1);

                $kvalitet = ($totalt > 0) ? round($ibcOk / $totalt * 100, 1) : null;
                $ibcPerH  = ($rtMin > 0) ? round($ibcOk / ($rtMin / 60), 1) : null;

                // OEE-approximation (avail * perf * qual)
                $planned   = $rtMin + $rastMin;
                $avail     = ($planned > 0) ? min($rtMin / $planned, 1.0) : null;
                $idealRate = 15.0 / 60.0; // IBC/min
                $perf      = ($rtMin > 0 && $totalt > 0) ? min(($totalt / $rtMin) / $idealRate, 1.0) : null;
                $qual      = ($totalt > 0) ? $ibcOk / $totalt : null;
                $oee = ($avail !== null && $perf !== null && $qual !== null)
                    ? round($avail * $perf * $qual * 100, 1)
                    : null;

                $avgCykel = ($agg['cykeltid_count'] > 0)
                    ? round($agg['cykeltid_sum'] / $agg['cykeltid_count'], 2)
                    : null;
                $stopptid = ($rtMin > 0) ? max(0, round(($nSkift * 480) - $rtMin - $rastMin, 0)) : null;

                return [
                    'ibc_ok'       => $ibcOk,
                    'ibc_ej_ok'    => $agg['ibc_ej_ok'],
                    'bur_ej_ok'    => $agg['bur_ej_ok'],
                    'totalt'       => $totalt,
                    'skift_count'  => $agg['skift_count'],
                    'avg_ibc_per_skift' => $agg['skift_count'] > 0 ? round($ibcOk / $agg['skift_count'], 1) : 0,
                    'kvalitet_pct' => $kvalitet,
                    'oee_pct'      => $oee,
                    'avg_cykeltid' => $avgCykel,
                    'ibc_per_h'    => $ibcPerH,
                    'runtime_min'  => (int)round($rtMin),
                    'stopptid_min' => $stopptid,
                ];
            };

            $dagKpi  = $calcKpi($dagAgg);
            $nattKpi = $calcKpi($nattAgg);

            // Bygg daglig tidsserie (alla datum i perioden)
            $allDates = array_unique(array_merge(array_keys($dagByDate), array_keys($nattByDate)));
            sort($allDates);

            $trend = [];
            foreach ($allDates as $d) {
                $dagEntry  = $dagByDate[$d]  ?? null;
                $nattEntry = $nattByDate[$d] ?? null;
                $trend[] = [
                    'datum'       => $d,
                    'dag_ibc'     => $dagEntry  ? $dagEntry['ibc_ok']  : null,
                    'natt_ibc'    => $nattEntry ? $nattEntry['ibc_ok'] : null,
                    'dag_cykeltid'  => ($dagEntry && $dagEntry['cykeltid_count'] > 0)
                        ? round($dagEntry['cykeltid_sum'] / $dagEntry['cykeltid_count'], 2)
                        : null,
                    'natt_cykeltid' => ($nattEntry && $nattEntry['cykeltid_count'] > 0)
                        ? round($nattEntry['cykeltid_sum'] / $nattEntry['cykeltid_count'], 2)
                        : null,
                    'dag_kvalitet'  => ($dagEntry && $dagEntry['totalt'] > 0)
                        ? round($dagEntry['ibc_ok'] / $dagEntry['totalt'] * 100, 1)
                        : null,
                    'natt_kvalitet' => ($nattEntry && $nattEntry['totalt'] > 0)
                        ? round($nattEntry['ibc_ok'] / $nattEntry['totalt'] * 100, 1)
                        : null,
                ];
            }

            echo json_encode([
                'success'  => true,
                'days'     => $days,
                'from'     => $startDate,
                'dag'      => $dagKpi,
                'natt'     => $nattKpi,
                'trend'    => $trend,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getShiftDayNightComparison: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftjämförelse'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // TOPP-5 OPERATÖRER LEADERBOARD
    // ================================================================

    /**
     * GET /api.php?action=rebotling&run=top-operators-leaderboard&days=30
     *
     * Returnerar en rangordnad lista av de 5 bästa operatörerna baserat på
     * bonuspoäng (genomsnitt per skift) under senaste X dagarna.
     * Inkluderar trend jämfört med föregående period.
     */
    public function getTopOperatorsLeaderboard(): void {
        $days  = max(1, min(365, (int)($_GET['days'] ?? 30)));
        $limit = 5;

        try {
            // Hämta alla operatörsnamn
            $opRows = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 OR active IS NULL")
                               ->fetchAll(PDO::FETCH_KEY_PAIR);

            // Hjälpfunktion: perShiftByPosition (liknande BonusController)
            $makeInner = function(int $pos): string {
                return "
                    SELECT
                        op{$pos}          AS operator_id,
                        skiftraknare,
                        MAX(ibc_ok)       AS shift_ibc_ok,
                        MAX(ibc_ej_ok)    AS shift_ibc_ej_ok,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang  ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet     ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kval,
                        SUBSTRING_INDEX(GROUP_CONCAT(effektivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_eff
                    FROM rebotling_ibc
                    WHERE op{$pos} IS NOT NULL AND op{$pos} > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN ? AND ?
                    GROUP BY op{$pos}, skiftraknare
                ";
            };

            $calcRanking = function(string $fromDate, string $toDate) use ($makeInner, $opRows, $limit): array {
                $s1 = $makeInner(1);
                $s2 = $makeInner(2);
                $s3 = $makeInner(3);

                $stmt = $this->pdo->prepare("
                    SELECT
                        operator_id,
                        COUNT(*)          AS skift_count,
                        SUM(shift_ibc_ok) AS total_ibc,
                        SUM(GREATEST(shift_ibc_ok - shift_ibc_ej_ok, 0)) AS total_ibc_ok,
                        AVG(last_bonus)   AS avg_bonus,
                        AVG(last_kval)    AS avg_kvalitet,
                        AVG(last_eff)     AS avg_eff
                    FROM (
                        SELECT operator_id, skiftraknare, shift_ibc_ok, shift_ibc_ej_ok, last_bonus, last_kval, last_eff FROM ($s1) AS x1
                        UNION ALL
                        SELECT operator_id, skiftraknare, shift_ibc_ok, shift_ibc_ej_ok, last_bonus, last_kval, last_eff FROM ($s2) AS x2
                        UNION ALL
                        SELECT operator_id, skiftraknare, shift_ibc_ok, shift_ibc_ej_ok, last_bonus, last_kval, last_eff FROM ($s3) AS x3
                    ) AS combined
                    GROUP BY operator_id
                    HAVING skift_count >= 1
                    ORDER BY avg_bonus DESC
                    LIMIT " . (int)$limit . "
                ");
                // 3 UNION-delar x 2 parametrar (from, to) = 6 parametrar
                $stmt->execute([$fromDate, $toDate, $fromDate, $toDate, $fromDate, $toDate]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            };

            // Nuvarande period
            $endDate   = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            $curRows   = $calcRanking($startDate, $endDate);

            // Föregående period (för trendberäkning)
            $prevEnd   = date('Y-m-d', strtotime("-{$days} days - 1 day"));
            $prevStart = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
            $prevRows   = $calcRanking($prevStart, $prevEnd);

            // Bygg previous-rank-map
            $prevRankMap = [];
            foreach ($prevRows as $idx => $row) {
                $prevRankMap[(int)$row['operator_id']] = $idx + 1;
            }

            // Bygg svar
            $maxScore = !empty($curRows) ? (float)($curRows[0]['avg_bonus'] ?? 1) : 1;
            if ($maxScore <= 0) $maxScore = 1;

            $leaderboard = [];
            foreach ($curRows as $idx => $row) {
                $opId    = (int)$row['operator_id'];
                $rank    = $idx + 1;
                $prevR   = $prevRankMap[$opId] ?? null;

                if ($prevR === null) {
                    $trend = 'new';
                } elseif ($rank < $prevR) {
                    $trend = 'up';
                } elseif ($rank > $prevR) {
                    $trend = 'down';
                } else {
                    $trend = 'same';
                }

                $totalIbc  = (int)($row['total_ibc']    ?? 0);
                $totalOk   = (int)($row['total_ibc_ok'] ?? 0);
                $qualPct   = $totalIbc > 0 ? round($totalOk / $totalIbc * 100, 1) : null;
                $score     = round((float)($row['avg_bonus'] ?? 0), 2);
                $scorePct  = round($score / $maxScore * 100, 1);

                $leaderboard[] = [
                    'rank'          => $rank,
                    'operator_id'   => $opId,
                    'operator_name' => $opRows[$opId] ?? 'Okänd',
                    'score'         => $score,
                    'score_pct'     => $scorePct,
                    'ibc_count'     => $totalIbc,
                    'quality_pct'   => $qualPct,
                    'skift_count'   => (int)($row['skift_count'] ?? 0),
                    'avg_eff'       => round((float)($row['avg_eff'] ?? 0), 1),
                    'trend'         => $trend,
                    'previous_rank' => $prevR,
                ];
            }

            echo json_encode([
                'success'     => true,
                'days'        => $days,
                'from'        => $startDate,
                'to'          => $endDate,
                'leaderboard' => $leaderboard,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingAnalyticsController::getTopOperatorsLeaderboard: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta leaderboard'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function getMachineUptimeHeatmap() {
        $days = max(1, min(90, (int)($_GET['days'] ?? 7)));
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $today = date('Y-m-d');

        try {
            // Hämta IBC-rader per datum och timme
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)        AS dag,
                    HOUR(datum)        AS timme,
                    COUNT(*)           AS ibc_count
                FROM rebotling_ibc
                WHERE DATE(datum) >= ?
                  AND DATE(datum) <= ?
                GROUP BY DATE(datum), HOUR(datum)
                ORDER BY dag, timme
            ");
            $stmt->execute([$startDate, $today]);
            $ibcRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta stopp-data per datum och timme (on/off-events)
            $stmtOff = $this->pdo->prepare("
                SELECT
                    DATE(datum)        AS dag,
                    HOUR(datum)        AS timme,
                    COUNT(*)           AS stopp_count
                FROM rebotling_onoff
                WHERE running = 0
                  AND DATE(datum) >= ?
                  AND DATE(datum) <= ?
                GROUP BY DATE(datum), HOUR(datum)
                ORDER BY dag, timme
            ");
            $stmtOff->execute([$startDate, $today]);
            $offRows = $stmtOff->fetchAll(PDO::FETCH_ASSOC);

            // Bygg lookup-map: dag+timme -> ibc_count
            $ibcMap = [];
            foreach ($ibcRows as $r) {
                $ibcMap[$r['dag']][$r['timme']] = (int)$r['ibc_count'];
            }

            // Bygg lookup-map: dag+timme -> stopp_count
            $offMap = [];
            foreach ($offRows as $r) {
                $offMap[$r['dag']][$r['timme']] = (int)$r['stopp_count'];
            }

            // Generera alla datum i perioden
            $result = [];
            for ($i = $days; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime("-{$i} days"));
                if ($dag > $today) continue;

                for ($h = 0; $h < 24; $h++) {
                    $ibc = $ibcMap[$dag][$h] ?? 0;
                    $stopp = $offMap[$dag][$h] ?? 0;

                    if ($ibc > 0) {
                        $status = 'running';
                    } elseif ($stopp > 0) {
                        $status = 'stopped';
                    } else {
                        $status = 'idle';
                    }

                    $stopMinutes = $stopp > 0 ? min(60, $stopp * 15) : 0;

                    $result[] = [
                        'date'         => $dag,
                        'hour'         => $h,
                        'status'       => $status,
                        'ibc_count'    => $ibc,
                        'stop_minutes' => $stopMinutes,
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'days'    => $days,
                'from'    => $startDate,
                'to'      => $today,
                'cells'   => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingAnalyticsController::getMachineUptimeHeatmap: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta maskinupptid-heatmap'], JSON_UNESCAPED_UNICODE);
        }
    }
}
