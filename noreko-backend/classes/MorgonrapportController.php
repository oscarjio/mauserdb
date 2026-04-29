<?php
/**
 * MorgonrapportController.php
 * VD:s morgonrapport -- sammanstaller gårdagens produktion.
 *
 * Endpoint via ?action=morgonrapport&run=rapport&date=YYYY-MM-DD
 *   Om date saknas → gårdagens datum.
 *
 * Returnerar all data i ett enda API-anrop:
 *   - rapport_info
 *   - produktion
 *   - effektivitet
 *   - stopp
 *   - kvalitet
 *   - trender
 *   - varningar
 *   - highlights
 *
 * Tabeller: rebotling_ibc, rebotling_weekday_goals, rebotling_settings,
 *           stoppage_log, stoppage_reasons,
 *           stopporsak_registreringar, stopporsak_kategorier,
 *           kassationsregistrering, kassationsorsak_typer,
 *           operators
 *
 * Auth: session kravs (401 om ej inloggad).
 */
class MorgonrapportController {
    private $pdo;

    /** Tillganglig tid per dag i timmar (3 skift x 7.5h efter rast) */
    private const TILLGANGLIG_TID_PER_DAG = 22.5;

    /** Cache TTL in seconds */
    private const CACHE_TTL = 30;

    /** Standard dagligt mal om rebotling_weekday_goals saknas */
    private const DEFAULT_DAILY_GOAL = 120;

    /** Kassationsgrad som triggar varning (%) */
    private const VARNING_KASSATION_PCT = 5.0;

    /** Max andel stopp-timmar av drifttid som triggar varning */
    private const VARNING_STOPP_PCT = 20.0;

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
            case 'rapport': $this->getRapport(); break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
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
     * Parsa och validera ett datumstrang (YYYY-MM-DD).
     * Returnerar formaterat datum eller null vid ogiltigt format.
     */
    private function parseDate(string $dateStr): ?string {
        $dt = \DateTime::createFromFormat('Y-m-d', $dateStr);
        if (!$dt || $dt->format('Y-m-d') !== $dateStr) {
            return null;
        }
        return $dateStr;
    }

    /**
     * Hamta gårdagens datum.
     */
    private function getYesterday(): string {
        $dt = new \DateTime();
        $dt->modify('-1 day');
        return $dt->format('Y-m-d');
    }

    /**
     * Hamta dagligt mal for en specifik veckodag.
     */
    private function getDailyGoalForDate(string $date): int {
        $dt = new \DateTime($date);
        $weekday = (int)$dt->format('N'); // 1=man, 7=son
        try {
            $stmt = $this->pdo->prepare(
                "SELECT daily_goal FROM rebotling_weekday_goals WHERE weekday = ? LIMIT 1"
            );
            $stmt->execute([$weekday]);
            $val = $stmt->fetchColumn();
            return $val ? (int)$val : self::DEFAULT_DAILY_GOAL;
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getDailyGoalForDate: ' . $e->getMessage());
            return self::DEFAULT_DAILY_GOAL;
        }
    }

    /**
     * Hamta total drifttid i timmar fran rebotling_ibc (runtime_plc) for ett datum.
     */
    private function getRuntimeHoursForDate(string $date): float {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(max_runtime), 0) AS runtime_min
                 FROM (
                     SELECT skiftraknare, MAX(runtime_plc) AS max_runtime
                     FROM rebotling_ibc
                     WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                     GROUP BY skiftraknare
                     HAVING COUNT(*) > 1
                 ) sub"
            );
            $stmt->execute([$date, $date]);
            $val = $stmt->fetchColumn();
            return round((float)($val ?: 0) / 60.0, 2);
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getRuntimeHoursForDate: ' . $e->getMessage());
            return 0;
        }
    }

    // ================================================================
    // run=rapport
    // ================================================================

    private function getRapport(): void {
        try {
            // Parsa datum
            $dateParam = trim($_GET['date'] ?? '');
            if (empty($dateParam)) {
                $dateParam = $this->getYesterday();
            }

            $date = $this->parseDate($dateParam);
            if (!$date) {
                $this->sendError('Ogiltigt datumformat. Anvand YYYY-MM-DD (t.ex. 2026-03-11).');
                return;
            }

            // Filcache 30s TTL — morgonrapporten andras sallan
            $cacheDir = dirname(__DIR__) . '/cache';
            if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
            $cacheFile = $cacheDir . '/morgonrapport_' . $date . '.json';
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
                $cached = @file_get_contents($cacheFile);
                if ($cached !== false) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo $cached;
                    return;
                }
            }

            // Foregaende vecka, samma dag
            $prevWeekDt = new \DateTime($date);
            $prevWeekDt->modify('-7 days');
            $prevWeekDate = $prevWeekDt->format('Y-m-d');

            // 30-dagarsperiod (exkl. rapportdatumet)
            $avg30Start = (new \DateTime($date))->modify('-30 days')->format('Y-m-d');
            $avg30End   = (new \DateTime($date))->modify('-1 day')->format('Y-m-d');

            // Samla all data
            $produktion  = $this->getProduktionData($date, $prevWeekDate, $avg30Start, $avg30End);
            $effektivitet = $this->getEffektivitetData($date, $prevWeekDate);
            $stopp       = $this->getStoppData($date, $prevWeekDate);
            $kvalitet    = $this->getKvalitetData($date, $prevWeekDate);
            $trender     = $this->getTrenderData($date, $prevWeekDate, $avg30Start);
            $highlights  = $this->getHighlightsData($date);
            $varningar   = $this->buildVarningar($produktion, $stopp, $kvalitet, $effektivitet);

            $responseData = [
                'rapport_info' => [
                    'datum'           => $date,
                    'prev_week_datum' => $prevWeekDate,
                    'avg30_start'     => $avg30Start,
                    'avg30_end'       => $avg30End,
                    'genererad'       => date('Y-m-d H:i:s'),
                ],
                'produktion'   => $produktion,
                'effektivitet' => $effektivitet,
                'stopp'        => $stopp,
                'kvalitet'     => $kvalitet,
                'trender'      => $trender,
                'highlights'   => $highlights,
                'varningar'    => $varningar,
            ];
            // Cacha hela rapporten
            $jsonResult = json_encode(['success' => true, 'data' => $responseData, 'timestamp' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
            @file_put_contents($cacheFile, $jsonResult, LOCK_EX);
            $this->sendSuccess($responseData);

        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getRapport: ' . $e->getMessage());
            $this->sendError('Kunde inte generera morgonrapport', 500);
        }
    }

    // ================================================================
    // PRODUKTION
    // ================================================================

    private function getProduktionData(
        string $date,
        string $prevWeekDate,
        string $avg30Start,
        string $avg30End
    ): array {
        // ibc_count = daglig räknare (startar om varje dag) → MAX ger korrekt dagstotal
        $totalIbc = 0;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(MAX(ibc_count), 0) AS ibc_total
                 FROM rebotling_ibc
                 WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)"
            );
            $stmt->execute([$date, $date]);
            $totalIbc = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getProduktionData (ibc): ' . $e->getMessage());
        }

        // Dagligt mal
        $mal = $this->getDailyGoalForDate($date);
        $uppfyllnadPct = $mal > 0 ? round(($totalIbc / $mal) * 100, 1) : 0;

        // Foregaende vecka, samma dag
        $prevWeekIbc = 0;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(MAX(ibc_count), 0) AS ibc_total
                 FROM rebotling_ibc
                 WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)"
            );
            $stmt->execute([$prevWeekDate, $prevWeekDate]);
            $prevWeekIbc = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getProduktionData (prevWeek): ' . $e->getMessage());
        }

        // Genomsnitt senaste 30 dagar
        $avg30 = 0;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT ROUND(AVG(dag_ibc), 1) AS snitt
                 FROM (
                     SELECT DATE(datum) AS dag, MAX(ibc_count) AS dag_ibc
                     FROM rebotling_ibc
                     WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)
                     GROUP BY DATE(datum)
                 ) sub_outer"
            );
            $stmt->execute([$avg30Start, $avg30End]);
            $avg30 = (float)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getProduktionData (avg30): ' . $e->getMessage());
        }

        $andraVsPrevWeek = $prevWeekIbc > 0
            ? round((($totalIbc - $prevWeekIbc) / $prevWeekIbc) * 100, 1)
            : 0;

        $andraVsAvg30 = $avg30 > 0
            ? round((($totalIbc - $avg30) / $avg30) * 100, 1)
            : 0;

        return [
            'totalt_ibc'         => $totalIbc,
            'mal'                => $mal,
            'uppfyllnad_pct'     => $uppfyllnadPct,
            'prev_week_ibc'      => $prevWeekIbc,
            'andring_vs_prev_vecka' => $andraVsPrevWeek,
            'snitt_30d'          => $avg30,
            'andring_vs_30d'     => $andraVsAvg30,
            'under_mal'          => ($totalIbc < $mal),
        ];
    }

    // ================================================================
    // EFFEKTIVITET
    // ================================================================

    private function getEffektivitetData(string $date, string $prevWeekDate): array {
        $drifttid      = $this->getRuntimeHoursForDate($date);
        $prevDrifttid  = $this->getRuntimeHoursForDate($prevWeekDate);

        // ibc_count = daglig räknare (startar om varje dag) → MAX ger korrekt dagstotal
        $totalIbc = 0;
        $prevIbc  = 0;
        try {
            $stmtIbc = $this->pdo->prepare(
                "SELECT COALESCE(MAX(ibc_count), 0) AS ibc_total
                 FROM rebotling_ibc
                 WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)"
            );
            $stmtIbc->execute([$date, $date]);
            $totalIbc = (int)($stmtIbc->fetchColumn() ?: 0);
            $stmtIbc->execute([$prevWeekDate, $prevWeekDate]);
            $prevIbc = (int)($stmtIbc->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getEffektivitetData: ' . $e->getMessage());
        }

        $ibcPerTimme     = $drifttid > 0 ? round($totalIbc / $drifttid, 1) : 0;
        $prevIbcPerTimme = $prevDrifttid > 0 ? round($prevIbc / $prevDrifttid, 1) : 0;

        // Utnyttjandegrad
        $utnyttjandegrad = self::TILLGANGLIG_TID_PER_DAG > 0
            ? round(($drifttid / self::TILLGANGLIG_TID_PER_DAG) * 100, 1)
            : 0;

        $andringIbcPerTimme = $prevIbcPerTimme > 0
            ? round((($ibcPerTimme - $prevIbcPerTimme) / $prevIbcPerTimme) * 100, 1)
            : 0;

        return [
            'ibc_per_timme'           => $ibcPerTimme,
            'prev_ibc_per_timme'      => $prevIbcPerTimme,
            'andring_ibc_per_timme'   => $andringIbcPerTimme,
            'total_drifttid_h'        => round($drifttid, 1),
            'tillganglig_tid_h'       => self::TILLGANGLIG_TID_PER_DAG,
            'utnyttjandegrad_pct'     => $utnyttjandegrad,
        ];
    }

    // ================================================================
    // STOPP
    // ================================================================

    private function getStoppData(string $date, string $prevWeekDate): array {
        $totalAntal  = 0;
        $totalTim    = 0.0;
        $topOrsaker  = [];
        $prevAntal   = 0;

        // stoppage_log
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(duration_minutes), 0) AS total_min
                 FROM stoppage_log
                 WHERE start_time >= ? AND start_time < DATE_ADD(?, INTERVAL 1 DAY)
                   AND line = 'rebotling'"
            );
            $stmt->execute([$date, $date]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalAntal += (int)($row['cnt'] ?? 0);
            $totalTim   += (float)($row['total_min'] ?? 0) / 60.0;

            $stmt->execute([$prevWeekDate, $prevWeekDate]);
            $prevRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $prevAntal += (int)($prevRow['cnt'] ?? 0);

            // Topp orsaker fran stoppage_log
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(r.name, 'Okänd orsak') AS orsak, COUNT(*) AS cnt,
                        COALESCE(SUM(s.duration_minutes), 0) AS total_min
                 FROM stoppage_log s
                 LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                 WHERE s.start_time >= ? AND s.start_time < DATE_ADD(?, INTERVAL 1 DAY)
                   AND s.line = 'rebotling'
                 GROUP BY r.id, r.name
                 ORDER BY total_min DESC
                 LIMIT 10"
            );
            $stmt->execute([$date, $date]);
            $topOrsaker = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getStoppData (stoppage_log): ' . $e->getMessage());
        }

        // stopporsak_registreringar
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) AS cnt,
                            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))), 0) AS total_min
                     FROM stopporsak_registreringar
                     WHERE start_time >= ? AND start_time < DATE_ADD(?, INTERVAL 1 DAY)
                       AND linje = 'rebotling'"
                );
                $stmt->execute([$date, $date]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalAntal += (int)($row['cnt'] ?? 0);
                $totalTim   += (float)($row['total_min'] ?? 0) / 60.0;

                $stmt->execute([$prevWeekDate, $prevWeekDate]);
                $prevRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                $prevAntal += (int)($prevRow['cnt'] ?? 0);

                // Topp orsaker fran stopporsak_registreringar
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(k.namn, 'Okänd kategori') AS orsak, COUNT(*) AS cnt,
                            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.start_time, COALESCE(r.end_time, NOW()))), 0) AS total_min
                     FROM stopporsak_registreringar r
                     LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                     WHERE r.start_time >= ? AND r.start_time < DATE_ADD(?, INTERVAL 1 DAY)
                       AND r.linje = 'rebotling'
                     GROUP BY k.id, k.namn
                     ORDER BY total_min DESC
                     LIMIT 10"
                );
                $stmt->execute([$date, $date]);
                $extraOrsaker = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Sla ihop med befintliga orsaker
                foreach ($extraOrsaker as $eo) {
                    $hittad = false;
                    foreach ($topOrsaker as &$to) {
                        if (mb_strtolower($to['orsak']) === mb_strtolower($eo['orsak'])) {
                            $to['cnt']       = (int)$to['cnt'] + (int)$eo['cnt'];
                            $to['total_min'] = (float)$to['total_min'] + (float)$eo['total_min'];
                            $hittad = true;
                            break;
                        }
                    }
                    unset($to);
                    if (!$hittad) {
                        $topOrsaker[] = $eo;
                    }
                }

                // Sortera pa total_min DESC
                usort($topOrsaker, fn($a, $b) => (float)$b['total_min'] <=> (float)$a['total_min']);
            }
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getStoppData (stopporsak_registreringar): ' . $e->getMessage());
        }

        // Formatera topp 3
        $top3 = [];
        foreach (array_slice($topOrsaker, 0, 3) as $r) {
            $top3[] = [
                'orsak'  => $r['orsak'],
                'antal'  => (int)$r['cnt'],
                'timmar' => round((float)$r['total_min'] / 60.0, 1),
            ];
        }

        $totalTim  = round($totalTim, 1);
        $andring   = $prevAntal > 0
            ? round((($totalAntal - $prevAntal) / $prevAntal) * 100, 1)
            : 0;

        return [
            'totalt_antal'   => $totalAntal,
            'totalt_timmar'  => $totalTim,
            'top3_orsaker'   => $top3,
            'prev_week_antal' => $prevAntal,
            'andring_pct'    => $andring,
        ];
    }

    // ================================================================
    // KVALITET
    // ================================================================

    private function getKvalitetData(string $date, string $prevWeekDate): array {
        $kasserade      = 0;
        $totaltProducerade = 0;
        $prevKasserade  = 0;
        $prevTotalt     = 0;
        $toppOrsak      = '-';

        // ibc_count = daglig räknare (startar om varje dag) → MAX ger korrekt dagstotal
        try {
            $stmtKval = $this->pdo->prepare(
                "SELECT COALESCE(MAX(ibc_count), 0) AS total,
                        COALESCE(MAX(ibc_ok), 0)    AS ok_total
                 FROM rebotling_ibc
                 WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)"
            );
            $stmtKval->execute([$date, $date]);
            $row = $stmtKval->fetch(\PDO::FETCH_ASSOC);
            $totaltProducerade = (int)($row['total'] ?? 0);
            $okDag = min($totaltProducerade, (int)($row['ok_total'] ?? 0));
            $kasserade        += $totaltProducerade - $okDag;

            $stmtKval->execute([$prevWeekDate, $prevWeekDate]);
            $prevRow = $stmtKval->fetch(\PDO::FETCH_ASSOC);
            $prevTotalt = (int)($prevRow['total'] ?? 0);
            $prevOk     = min($prevTotalt, (int)($prevRow['ok_total'] ?? 0));
            $prevKasserade += $prevTotalt - $prevOk;
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getKvalitetData (ibc): ' . $e->getMessage());
        }

        // kassationsregistrering
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'kassationsregistrering'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(SUM(antal), 0) AS kasserade
                     FROM kassationsregistrering
                     WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)"
                );
                $stmt->execute([$date, $date]);
                $kasserade += (int)($stmt->fetchColumn() ?: 0);

                $stmt->execute([$prevWeekDate, $prevWeekDate]);
                $prevKasserade += (int)($stmt->fetchColumn() ?: 0);

                // Topp-orsak
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(t.namn, 'Okänd') AS namn, COALESCE(SUM(r.antal), 0) AS total_antal
                     FROM kassationsregistrering r
                     LEFT JOIN kassationsorsak_typer t ON r.orsak_id = t.id
                     WHERE r.datum >= ? AND r.datum < DATE_ADD(?, INTERVAL 1 DAY)
                     GROUP BY t.id, t.namn
                     ORDER BY total_antal DESC
                     LIMIT 1"
                );
                $stmt->execute([$date, $date]);
                $topRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($topRow && !empty($topRow['namn'])) {
                    $toppOrsak = $topRow['namn'];
                }
            }
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getKvalitetData (kassationsregistrering): ' . $e->getMessage());
        }

        $kassationsgrad = $totaltProducerade > 0
            ? round(($kasserade / $totaltProducerade) * 100, 2)
            : 0;

        $prevKassationsgrad = $prevTotalt > 0
            ? round(($prevKasserade / $prevTotalt) * 100, 2)
            : 0;

        $andring = $prevKassationsgrad > 0
            ? round((($kassationsgrad - $prevKassationsgrad) / $prevKassationsgrad) * 100, 1)
            : 0;

        return [
            'kassationsgrad_pct'       => $kassationsgrad,
            'kasserade_antal'          => $kasserade,
            'totalt_producerade'       => $totaltProducerade,
            'topp_orsak'               => $toppOrsak,
            'prev_week_kassationsgrad' => $prevKassationsgrad,
            'andring_pct'              => $andring,
        ];
    }

    // ================================================================
    // TRENDER
    // ================================================================

    private function getTrenderData(
        string $date,
        string $prevWeekDate,
        string $avg30Start
    ): array {
        // Daglig IBC fran 30 dagar bakåt (for trendlinje) — korrekt MAX/GROUP BY-aggregering
        $dagligIbc = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT dag, SUM(max_ok) AS cnt
                 FROM (
                     SELECT DATE(datum) AS dag, skiftraknare,
                            MAX(COALESCE(ibc_ok, 0)) AS max_ok
                     FROM rebotling_ibc
                     WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)

                     GROUP BY DATE(datum), skiftraknare
                     HAVING COUNT(*) > 1
                 ) sub
                 GROUP BY dag
                 ORDER BY dag ASC"
            );
            $stmt->execute([$avg30Start, $date]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $dagligIbc[$row['dag']] = (int)$row['cnt'];
            }
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getTrenderData: ' . $e->getMessage());
        }

        // Berakna 7-dagars glidande medelvarde
        $glidande7d = [];
        $dagar = array_keys($dagligIbc);
        foreach ($dagar as $i => $dag) {
            $start7 = max(0, $i - 6);
            $slice = array_slice($dagligIbc, $start7, $i - $start7 + 1);
            $glidande7d[$dag] = count($slice) > 0 ? round(array_sum($slice) / count($slice), 1) : 0;
        }

        return [
            'daglig_ibc'       => $dagligIbc,
            'glidande_7d'      => $glidande7d,
            'prev_week_datum'  => $prevWeekDate,
        ];
    }

    // ================================================================
    // HIGHLIGHTS
    // ================================================================

    private function getHighlightsData(string $date): array {
        $bastaTimme        = null;
        $bastaTimmeAntal   = 0;
        $snabbastOperator  = null;
        $snabbastAntal     = 0;

        // Basta timme (flest IBC) — korrekt MAX/GROUP BY per skiftraknare+timme
        try {
            $stmt = $this->pdo->prepare("
                SELECT timme, SUM(max_ok) AS cnt
                FROM (
                    SELECT HOUR(datum) AS timme, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS max_ok
                    FROM rebotling_ibc
                    WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY)

                    GROUP BY DATE(datum), HOUR(datum), skiftraknare
                    HAVING COUNT(*) > 1
                ) sub
                GROUP BY timme
                ORDER BY cnt DESC
                LIMIT 1
            ");
            $stmt->execute([$date, $date]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $bastaTimme      = (int)$row['timme'];
                $bastaTimmeAntal = (int)$row['cnt'];
            }
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getHighlightsData (basta_timme): ' . $e->getMessage());
        }

        // Snabbaste operator (flest IBC under datumet)
        // rebotling_ibc uses op1/op2/op3 = operators.number (INTE operators.id)
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'operators'");
            if ($check && $check->rowCount() > 0) {
                // Operator-IBC raknas per skiftraknare dar operatorn ar registrerad (korrekt aggregering)
                $stmt = $this->pdo->prepare("
                    SELECT op, SUM(max_ok) AS total_ibc, COALESCE(o.name, CONCAT('Operator ', op)) AS operator_namn
                    FROM (
                        SELECT op1 AS op, skiftraknare, MAX(COALESCE(ibc_ok, 0)) AS max_ok
                        FROM rebotling_ibc
                        WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY) AND op1 IS NOT NULL AND op1 > 0

                        GROUP BY DATE(datum), skiftraknare, op1
                        HAVING COUNT(*) > 1
                        UNION ALL
                        SELECT op2 AS op, skiftraknare, MAX(COALESCE(ibc_ok, 0)) AS max_ok
                        FROM rebotling_ibc
                        WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY) AND op2 IS NOT NULL AND op2 > 0

                        GROUP BY DATE(datum), skiftraknare, op2
                        HAVING COUNT(*) > 1
                        UNION ALL
                        SELECT op3 AS op, skiftraknare, MAX(COALESCE(ibc_ok, 0)) AS max_ok
                        FROM rebotling_ibc
                        WHERE datum >= ? AND datum < DATE_ADD(?, INTERVAL 1 DAY) AND op3 IS NOT NULL AND op3 > 0

                        GROUP BY DATE(datum), skiftraknare, op3
                        HAVING COUNT(*) > 1
                    ) AS sub
                    LEFT JOIN operators o ON o.number = sub.op
                    GROUP BY op, o.name
                    ORDER BY total_ibc DESC
                    LIMIT 1
                ");
                $stmt->execute([$date, $date, $date, $date, $date, $date]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $snabbastOperator = $row['operator_namn'];
                    $snabbastAntal    = (int)$row['total_ibc'];
                }
            }
        } catch (\Throwable $e) {
            error_log('MorgonrapportController::getHighlightsData (operator): ' . $e->getMessage());
        }

        $bastaTimmeFormaterat = $bastaTimme !== null
            ? sprintf('%02d:00–%02d:00', $bastaTimme, ($bastaTimme + 1) % 24)
            : null;

        return [
            'basta_timme'         => $bastaTimme,
            'basta_timme_label'   => $bastaTimmeFormaterat,
            'basta_timme_antal'   => $bastaTimmeAntal,
            'snabbast_operator'   => $snabbastOperator,
            'snabbast_antal'      => $snabbastAntal,
        ];
    }

    // ================================================================
    // VARNINGAR
    // ================================================================

    private function buildVarningar(
        array $produktion,
        array $stopp,
        array $kvalitet,
        array $effektivitet
    ): array {
        $varningar = [];

        // Produktion under mal
        if (!empty($produktion['under_mal'])) {
            $pct = $produktion['uppfyllnad_pct'];
            $severity = $pct < 50 ? 'rod' : 'gul';
            $varningar[] = [
                'typ'       => 'produktion_under_mal',
                'severity'  => $severity,
                'meddelande' => sprintf(
                    'Produktionen uppnådde bara %s%% av dagsmålet (%s av %s IBC).',
                    $pct,
                    $produktion['totalt_ibc'],
                    $produktion['mal']
                ),
            ];
        }

        // Hog kassationsgrad
        if ($kvalitet['kassationsgrad_pct'] >= self::VARNING_KASSATION_PCT) {
            $varningar[] = [
                'typ'       => 'hog_kassation',
                'severity'  => $kvalitet['kassationsgrad_pct'] >= 10 ? 'rod' : 'gul',
                'meddelande' => sprintf(
                    'Kassationsgraden var %s%% (%s kasserade IBC).',
                    $kvalitet['kassationsgrad_pct'],
                    $kvalitet['kasserade_antal']
                ),
            ];
        }

        // Hog andel stopptid av drifttid
        if ($effektivitet['total_drifttid_h'] > 0) {
            $stoppPctAvDrift = ($stopp['totalt_timmar'] / $effektivitet['total_drifttid_h']) * 100;
            if ($stoppPctAvDrift >= self::VARNING_STOPP_PCT) {
                $varningar[] = [
                    'typ'       => 'hog_stopptid',
                    'severity'  => $stoppPctAvDrift >= 35 ? 'rod' : 'gul',
                    'meddelande' => sprintf(
                        '%sh stopp registrerades (%s stopp, %.0f%% av drifttiden).',
                        $stopp['totalt_timmar'],
                        $stopp['totalt_antal'],
                        $stoppPctAvDrift
                    ),
                ];
            }
        }

        // Lag utnyttjandegrad
        if ($effektivitet['utnyttjandegrad_pct'] > 0 && $effektivitet['utnyttjandegrad_pct'] < 50) {
            $varningar[] = [
                'typ'       => 'lag_utnyttjandegrad',
                'severity'  => 'gul',
                'meddelande' => sprintf(
                    'Utnyttjandegraden var bara %s%% (%sh av tillgangliga %sh).',
                    $effektivitet['utnyttjandegrad_pct'],
                    $effektivitet['total_drifttid_h'],
                    $effektivitet['tillganglig_tid_h']
                ),
            ];
        }

        // Inga varningar = gront
        if (empty($varningar)) {
            $varningar[] = [
                'typ'       => 'ok',
                'severity'  => 'gron',
                'meddelande' => 'Allt normalt — inga avvikelser att rapportera.',
            ];
        }

        return $varningar;
    }
}
