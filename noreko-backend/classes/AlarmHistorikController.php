<?php
/**
 * AlarmHistorikController.php
 * Alarm-historik — visa och analysera alla larm/alerts som triggats i systemet.
 *
 * Endpoints via ?action=alarm-historik&run=XXX:
 *   - run=list      → filtrerad lista med alla larm (days, status, severity)
 *   - run=summary   → KPI-sammanfattning: totalt, per severity, per typ, snitt/dag, vanligaste
 *   - run=timeline  → tidslinje for Chart.js: larm per dag per severity
 *
 * Larm byggs fran befintliga datakallor:
 *   - Langa stopp (>30 min) fran stoppage_log               → severity: critical
 *   - Lag takt (<50% av mal) fran rebotling_ibc vs goals    → severity: warning
 *   - Hog kassationsgrad (>5%) fran kassationsregistrering  → severity: warning
 *   - Maskin-stopp (status ej KOR) fran rebotling_ibc       → severity: critical
 *
 * Auth: session kravs.
 */
class AlarmHistorikController {
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
            case 'list':     $this->getList();     break;
            case 'summary':  $this->getSummary();  break;
            case 'timeline': $this->getTimeline(); break;
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
     * Samla alla larm fran alla kallor for given period.
     * Returnerar array av larm-poster med:
     *   id, datum, tid, typ, severity, beskrivning, varaktighet_min, status, kalla
     */
    private function collectAlarms(string $fromDate, string $toDate): array {
        $alarms = [];

        // ---- 1. Langa stopp (>30 min) fran stoppage_log ----
        $alarms = array_merge($alarms, $this->getLangaStopp($fromDate, $toDate));

        // ---- 2. Lag produktionstakt (<50% av mal) per dag ----
        $alarms = array_merge($alarms, $this->getLagTakt($fromDate, $toDate));

        // ---- 3. Hog kassationsgrad (>5%) per dag ----
        $alarms = array_merge($alarms, $this->getHogKassation($fromDate, $toDate));

        // ---- 4. Maskin-stopp (status ej KOR) ----
        $alarms = array_merge($alarms, $this->getMaskinStopp($fromDate, $toDate));

        // Sortera pa datum+tid, nyast forst
        usort($alarms, function ($a, $b) {
            $da = $a['datum'] . ' ' . $a['tid'];
            $db = $b['datum'] . ' ' . $b['tid'];
            return strcmp($db, $da);
        });

        return $alarms;
    }

    /**
     * Langa stopp (>30 min) fran stoppage_log.
     * severity: critical
     */
    private function getLangaStopp(string $fromDate, string $toDate): array {
        try {
            // Kontrollera att tabellen finns
            $check = $this->pdo->query("SHOW TABLES LIKE 'stoppage_log'");
            if (!$check || $check->rowCount() === 0) return [];

            $stmt = $this->pdo->prepare("
                SELECT
                    sl.id,
                    DATE(sl.start_time)           AS datum,
                    TIME(sl.start_time)           AS tid,
                    sl.duration_minutes           AS varaktighet_min,
                    COALESCE(sr.name, 'Okand orsak') AS orsak,
                    sl.comment AS notes
                FROM stoppage_log sl
                LEFT JOIN stoppage_reasons sr ON sr.id = sl.reason_id
                WHERE DATE(sl.start_time) BETWEEN :from_date AND :to_date
                  AND sl.duration_minutes > 30
                ORDER BY sl.start_time DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $r) {
                $dur = (int)$r['varaktighet_min'];
                $result[] = [
                    'id'             => 'stopp_' . $r['id'],
                    'datum'          => $r['datum'],
                    'tid'            => $r['tid'] ?? '00:00:00',
                    'typ'            => 'Langt stopp',
                    'severity'       => 'critical',
                    'beskrivning'    => 'Stopp >' . $dur . ' min: ' . $r['orsak'],
                    'varaktighet_min'=> $dur,
                    'status'         => 'resolved',
                    'kalla'          => 'stoppage_log',
                ];
            }
            return $result;
        } catch (\PDOException $e) {
            error_log('AlarmHistorikController::getLangaStopp: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lag produktionstakt (<50% av dagligt mal) per dag.
     * severity: warning
     */
    private function getLagTakt(string $fromDate, string $toDate): array {
        try {
            // Kontrollera tabeller
            $check1 = $this->pdo->query("SHOW TABLES LIKE 'rebotling_ibc'");
            if (!$check1 || $check1->rowCount() === 0) return [];

            // Hamta daglig produktion (MAX per skift, SUM per dag)
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    SUM(shift_ok + shift_ej_ok) AS total_ibc
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY DATE(datum)
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $dagsProd = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($dagsProd)) return [];

            // Hamta mal per veckodag om tabellen finns
            $goals = [];
            $goalCheck = $this->pdo->query("SHOW TABLES LIKE 'rebotling_weekday_goals'");
            if ($goalCheck && $goalCheck->rowCount() > 0) {
                $goalStmt = $this->pdo->query("SELECT weekday, daily_goal AS goal FROM rebotling_weekday_goals");
                foreach ($goalStmt->fetchAll(\PDO::FETCH_ASSOC) as $g) {
                    $goals[(int)$g['weekday']] = (int)$g['goal'];
                }
            }

            $defaultGoal = 120; // Standard dagligt mal

            $result = [];
            foreach ($dagsProd as $row) {
                $dag    = $row['dag'];
                $actual = (int)$row['total_ibc'];
                $dow    = (int)date('N', strtotime($dag)); // 1=man, 7=son
                $goal   = $goals[$dow] ?? $defaultGoal;

                if ($goal <= 0) continue;
                $ratio = $actual / $goal;
                if ($ratio >= 0.5) continue; // OK

                $pct = round($ratio * 100, 0);
                $result[] = [
                    'id'             => 'takt_' . str_replace('-', '', $dag),
                    'datum'          => $dag,
                    'tid'            => '23:59:00',
                    'typ'            => 'Lag produktionstakt',
                    'severity'       => 'warning',
                    'beskrivning'    => "Produktion {$actual} IBC = {$pct}% av mal ({$goal} IBC)",
                    'varaktighet_min'=> null,
                    'status'         => 'resolved',
                    'kalla'          => 'rebotling_ibc',
                ];
            }
            return $result;
        } catch (\PDOException $e) {
            error_log('AlarmHistorikController::getLagTakt: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Hog kassationsgrad (>5%) per dag.
     * severity: warning
     */
    private function getHogKassation(string $fromDate, string $toDate): array {
        try {
            $checkKass = $this->pdo->query("SHOW TABLES LIKE 'kassationsregistrering'");
            if (!$checkKass || $checkKass->rowCount() === 0) return [];
            $checkIbc = $this->pdo->query("SHOW TABLES LIKE 'rebotling_ibc'");
            if (!$checkIbc || $checkIbc->rowCount() === 0) return [];

            // Kassationer per dag
            $stmtKass = $this->pdo->prepare("
                SELECT datum, SUM(antal) AS kasserade
                FROM kassationsregistrering
                WHERE datum BETWEEN :from_date AND :to_date
                GROUP BY datum
            ");
            $stmtKass->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $kassPerDag = [];
            foreach ($stmtKass->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $kassPerDag[$r['datum']] = (int)$r['kasserade'];
            }

            // Produktion per dag (ej_ok fran PLC)
            $stmtIbc = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, SUM(shift_ok + shift_ej_ok) AS total_ibc
                FROM (
                    SELECT datum, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY DATE(datum)
            ");
            $stmtIbc->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $ibcPerDag = [];
            foreach ($stmtIbc->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $ibcPerDag[$r['dag']] = (int)$r['total_ibc'];
            }

            $result = [];
            foreach ($kassPerDag as $dag => $kasserade) {
                $total = $ibcPerDag[$dag] ?? 0;
                if ($total <= 0) continue;
                $rate = $kasserade / $total * 100;
                if ($rate <= 5.0) continue;
                $rateFmt = round($rate, 1);
                $result[] = [
                    'id'             => 'kass_' . str_replace('-', '', $dag),
                    'datum'          => $dag,
                    'tid'            => '23:59:00',
                    'typ'            => 'Hog kassationsgrad',
                    'severity'       => 'warning',
                    'beskrivning'    => "Kassationsgrad {$rateFmt}% (>{5}%) — {$kasserade} av {$total} IBC kasserade",
                    'varaktighet_min'=> null,
                    'status'         => 'resolved',
                    'kalla'          => 'kassationsregistrering',
                ];
            }
            return $result;
        } catch (\PDOException $e) {
            error_log('AlarmHistorikController::getHogKassation: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Maskin-stopp: dagar da ingen produktion registrerats (maskin stod stilla).
     * Detekteras som dagar dar ibc-poster finns men total = 0, eller dar shift-status
     * indikerar att maskinen inte var i KOR.
     * severity: critical
     */
    private function getMaskinStopp(string $fromDate, string $toDate): array {
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_ibc'");
            if (!$check || $check->rowCount() === 0) return [];

            // Dagar dar total IBC = 0 trots att det finns rader (maskin registrerad men ingen produktion)
            $stmt = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, COUNT(*) AS rader,
                       SUM(COALESCE(ibc_ok, 0)) AS total_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                GROUP BY DATE(datum)
                HAVING total_ok = 0 AND rader > 0
                ORDER BY dag DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $r) {
                $dag = $r['dag'];
                $result[] = [
                    'id'             => 'maskin_' . str_replace('-', '', $dag),
                    'datum'          => $dag,
                    'tid'            => '00:00:00',
                    'typ'            => 'Maskinstopp',
                    'severity'       => 'critical',
                    'beskrivning'    => 'Ingen produktion registrerad — maskinen producerade 0 IBC',
                    'varaktighet_min'=> null,
                    'status'         => 'resolved',
                    'kalla'          => 'rebotling_ibc',
                ];
            }
            return $result;
        } catch (\PDOException $e) {
            error_log('AlarmHistorikController::getMaskinStopp: ' . $e->getMessage());
            return [];
        }
    }

    // ================================================================
    // ENDPOINT: list
    // ================================================================

    /**
     * GET ?action=alarm-historik&run=list&days=N&status=all|active|resolved&severity=all|critical|warning|info
     */
    private function getList(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $status   = trim($_GET['status'] ?? 'all');
        $severity = trim($_GET['severity'] ?? 'all');
        $typ      = trim($_GET['typ'] ?? 'all');

        $alarms = $this->collectAlarms($fromDate, $toDate);

        // Filtrera pa severity
        if ($severity !== 'all') {
            $alarms = array_values(array_filter($alarms, fn($a) => $a['severity'] === $severity));
        }

        // Filtrera pa status (alla ar "resolved" i historiken, men behalter for framtida bruk)
        if ($status !== 'all') {
            $alarms = array_values(array_filter($alarms, fn($a) => $a['status'] === $status));
        }

        // Filtrera pa typ
        if ($typ !== 'all' && $typ !== '') {
            $alarms = array_values(array_filter($alarms, fn($a) => $a['typ'] === $typ));
        }

        $this->sendSuccess([
            'alarms'    => $alarms,
            'count'     => count($alarms),
            'days'      => $days,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
        ]);
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    /**
     * GET ?action=alarm-historik&run=summary&days=N
     * KPI-sammanfattning: totalt, per severity, per typ, snitt/dag, vanligaste
     */
    private function getSummary(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $alarms = $this->collectAlarms($fromDate, $toDate);

        $total    = count($alarms);
        $critical = count(array_filter($alarms, fn($a) => $a['severity'] === 'critical'));
        $warning  = count(array_filter($alarms, fn($a) => $a['severity'] === 'warning'));
        $info     = count(array_filter($alarms, fn($a) => $a['severity'] === 'info'));

        // Per typ
        $perTyp = [];
        foreach ($alarms as $a) {
            $t = $a['typ'];
            if (!isset($perTyp[$t])) $perTyp[$t] = 0;
            $perTyp[$t]++;
        }
        arsort($perTyp);

        $vanligastTyp = !empty($perTyp) ? array_key_first($perTyp) : null;

        // Snitt per dag (baserat pa antal unika dagar med larm)
        $unikaDagar = count(array_unique(array_column($alarms, 'datum')));
        // Snitt per dag baserat pa hela perioden
        $periodDagar = max(1, $days);
        $snittPeriod = round($total / $periodDagar, 2);

        $this->sendSuccess([
            'days'           => $days,
            'from_date'      => $fromDate,
            'to_date'        => $toDate,
            'total'          => $total,
            'critical'       => $critical,
            'warning'        => $warning,
            'info'           => $info,
            'per_typ'        => $perTyp,
            'vanligast_typ'  => $vanligastTyp,
            'snitt_per_dag'  => $snittPeriod,
            'dagar_med_larm' => $unikaDagar,
        ]);
    }

    // ================================================================
    // ENDPOINT: timeline
    // ================================================================

    /**
     * GET ?action=alarm-historik&run=timeline&days=N
     * Tidslinje for Chart.js: larm per dag uppdelat per severity
     */
    private function getTimeline(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $alarms = $this->collectAlarms($fromDate, $toDate);

        // Bygg datum-lista
        $allDates = [];
        $current  = strtotime($fromDate);
        $end      = strtotime($toDate);
        while ($current <= $end) {
            $allDates[] = date('Y-m-d', $current);
            $current    = strtotime('+1 day', $current);
        }

        // Gruppera per datum+severity
        $perDatum = [];
        foreach ($alarms as $a) {
            $d = $a['datum'];
            $s = $a['severity'];
            if (!isset($perDatum[$d])) {
                $perDatum[$d] = ['critical' => 0, 'warning' => 0, 'info' => 0];
            }
            if (isset($perDatum[$d][$s])) {
                $perDatum[$d][$s]++;
            }
        }

        // Bygg datasets
        $labels   = [];
        $critical = [];
        $warning  = [];
        $info     = [];

        foreach ($allDates as $dag) {
            // Visa kortare etikett (dag/man)
            $labels[]   = date('d/m', strtotime($dag));
            $critical[] = $perDatum[$dag]['critical'] ?? 0;
            $warning[]  = $perDatum[$dag]['warning']  ?? 0;
            $info[]     = $perDatum[$dag]['info']     ?? 0;
        }

        $harData = array_sum($critical) + array_sum($warning) + array_sum($info) > 0;

        $this->sendSuccess([
            'labels'   => $labels,
            'dates'    => $allDates,
            'datasets' => [
                [
                    'label'           => 'Kritiska',
                    'data'            => $critical,
                    'backgroundColor' => '#fc8181',
                    'borderColor'     => '#e53e3e',
                    'borderWidth'     => 1,
                    'stack'           => 'larm',
                ],
                [
                    'label'           => 'Varningar',
                    'data'            => $warning,
                    'backgroundColor' => '#f6ad55',
                    'borderColor'     => '#dd6b20',
                    'borderWidth'     => 1,
                    'stack'           => 'larm',
                ],
                [
                    'label'           => 'Info',
                    'data'            => $info,
                    'backgroundColor' => '#63b3ed',
                    'borderColor'     => '#3182ce',
                    'borderWidth'     => 1,
                    'stack'           => 'larm',
                ],
            ],
            'har_data' => $harData,
            'days'     => $days,
        ]);
    }
}
