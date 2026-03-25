<?php
/**
 * KapacitetsplaneringController.php
 * Rebotling kapacitetsplanering -- planerad vs faktisk kapacitet, flaskhalsanalys,
 * bemanningsplanering, prognos-simulator.
 *
 * Endpoints via ?action=kapacitetsplanering&run=XXX:
 *   - run=kpi                  -- samlade KPI:er (utnyttjandegrad, flaskhals, ledig kap, rek bemanning)
 *   - run=daglig-kapacitet     -- daglig faktisk prod + teoretisk max + ev. mal (?period=30)
 *   - run=station-utnyttjande  -- kapacitetsutnyttjande per station (horisontellt stapeldiagram)
 *   - run=stopporsaker         -- fordelning av stopptid per orsak/typ
 *   - run=tid-fordelning       -- daglig fordelning: produktiv tid vs stopp vs idle (?period=30)
 *   - run=vecko-oversikt       -- veckosammanstalning senaste 12 veckor
 *   - run=utnyttjandegrad-trend -- utnyttjandegrad per dag med mal-linje (Chart.js linjediagram)
 *   - run=kapacitetstabell     -- detaljerad tabell per station
 *   - run=bemanning            -- bemanningsplanering baserat pa orderbehov
 *   - run=prognos              -- prognos-simulator (?timmar&operatorer)
 *   - run=config               -- hamta kapacitet_config
 *
 * OEE: T = drifttid/planerad, P = (IBC*cykeltid)/drifttid, K = godkanda/totalt
 * Optimal cykeltid: 120 sekunder (2 min per IBC)
 * Teoretisk max: antal_stationer * planerad_drifttid * (3600/optimal_cykeltid)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_produktionsmal (om finns), kapacitet_config
 */
class KapacitetsplaneringController {
    private $pdo;

    private const OPTIMAL_CYKELTID_SEK = 120;   // 2 minuter per IBC
    private const PLANERAD_DRIFTTID_SEK = 8 * 3600; // 8 timmar per dag
    private const DEFAULT_SKIFT_TIMMAR = 8;
    private const DEFAULT_IBC_PER_OPERATOR_TIMME = 15;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureConfigTable();
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
            case 'kpi':                   $this->getKpi();                  break;
            case 'daglig-kapacitet':      $this->getDagligKapacitet();      break;
            case 'station-utnyttjande':   $this->getStationUtnyttjande();   break;
            case 'stopporsaker':          $this->getStopporsaker();         break;
            case 'tid-fordelning':        $this->getTidFordelning();        break;
            case 'vecko-oversikt':        $this->getVeckoOversikt();        break;
            case 'utnyttjandegrad-trend': $this->getUtnyttjandegradTrend(); break;
            case 'kapacitetstabell':      $this->getKapacitetstabell();     break;
            case 'bemanning':            $this->getBemanning();             break;
            case 'prognos':              $this->getPrognos();               break;
            case 'config':               $this->getConfig();                break;
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

    private function ensureConfigTable(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'kapacitet_config'"
            )->fetchColumn();
            if (!$check) {
                $migrationPath = __DIR__ . '/../migrations/2026-03-13_kapacitet_config.sql';
                $sql = file_get_contents($migrationPath);
                if ($sql === false) {
                    error_log('KapacitetsplaneringController::ensureConfigTable: kunde inte läsa migrationsfil: ' . $migrationPath);
                } elseif ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::ensureConfigTable: ' . $e->getMessage());
        }
    }

    private function loadKapacitetConfig(): array {
        try {
            $stmt = $this->pdo->query(
                "SELECT station_id, station_namn, teoretisk_kapacitet_per_timme,
                        mal_utnyttjandegrad_pct, ibc_per_operator_timme
                 FROM kapacitet_config WHERE aktiv = 1 ORDER BY station_id"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::loadKapacitetConfig: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Hamtar antal stationer — rebotling_ibc har ingen station-kolumn, returnera 1.
     */
    private function getAntalStationer(string $fromDate, string $toDate): int {
        return 1;
    }

    /**
     * Beraknar total drifttid (sek) fran rebotling_onoff for ett intervall.
     * rebotling_onoff has columns: datum (DATETIME), running (BOOLEAN).
     */
    private function getDrifttidSek(string $fromDt, string $toDt): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum < :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $drifttid = 0;
            $prevTime = null;
            $prevRunning = null;

            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];

                if ($prevTime !== null && $prevRunning === 1) {
                    $drifttid += ($ts - $prevTime);
                }

                $prevTime = $ts;
                $prevRunning = $running;
            }

            return max(0, $drifttid);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getDrifttidSek: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Hamtar produktionsmal for ett datum om rebotling_produktionsmal finns.
     */
    private function getProduktionsmal(string $datum): ?int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mal_antal FROM rebotling_produktionsmal
                WHERE start_datum <= :datum
                  AND (slut_datum IS NULL OR slut_datum >= :datum2)
                  AND typ = 'daglig'
                ORDER BY skapad_av DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([':datum' => $datum, ':datum2' => $datum]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : null;
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getProduktionsmal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Beraknar genomsnittlig cykeltid (sek) for ett datumintervall.
     */
    private function getAvgCykeltidSek(string $fromDate, string $toDate): float {
        try {
            $stmt = $this->pdo->prepare("
                SELECT AVG(diff_sek) AS avg_sek FROM (
                    SELECT TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (ORDER BY datum),
                        datum
                    ) AS diff_sek
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                ) t
                WHERE diff_sek > 0 AND diff_sek < 3600
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            return (float)($stmt->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getAvgCykeltidSek: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Returnera datumgranser for period-filter (idag/vecka/manad).
     */
    private function getPeriodBounds(string $period): array {
        $today = date('Y-m-d');
        switch ($period) {
            case 'vecka':
                $dt = new \DateTime($today);
                $dayOfWeek = (int)$dt->format('N');
                $from = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
                $to = $today;
                $dagar = $dayOfWeek;
                break;
            case 'manad':
                $dt = new \DateTime($today);
                $from = $dt->format('Y-m-01');
                $to = $today;
                $dagar = (int)$dt->format('j');
                break;
            default: // idag
                $from = $today;
                $to = $today;
                $dagar = 1;
                break;
        }
        return ['from' => $from, 'to' => $to, 'dagar' => $dagar];
    }

    // ================================================================
    // run=kpi -- samlade KPI:er
    // ================================================================

    private function getKpi(): void {
        $periodFilter = trim($_GET['period_filter'] ?? 'idag');
        $bounds = $this->getPeriodBounds($periodFilter);
        $fromDate = $bounds['from'];
        $toDate = $bounds['to'];
        $dagar = $bounds['dagar'];

        $today = date('Y-m-d');

        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = date('Y-m-d', strtotime($toDate . ' +1 day')) . ' 00:00:00';

        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        try {
            $stmtProd = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_antal,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmtProd->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $radProd = $stmtProd->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getKpi prod: ' . $e->getMessage());
            $radProd = ['ok_antal' => 0, 'ej_ok_antal' => 0];
        }

        $antalStationer = 1; // rebotling_ibc has no station column
        $faktisk = (int)($radProd['ok_antal'] ?? 0) + (int)($radProd['ej_ok_antal'] ?? 0);

        // Teoretisk max
        $ibcPerTimme = 3600 / self::OPTIMAL_CYKELTID_SEK;
        $maxPerStationPerDag = self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK;
        $teorMax = $antalStationer * $maxPerStationPerDag * $dagar;

        // Kapacitetsutnyttjande
        $utnyttjande = $teorMax > 0 ? min(100, round($faktisk / $teorMax * 100, 1)) : 0.0;

        // Ledig kapacitet
        $ledigKapacitet = max(0, (int)$teorMax - $faktisk);

        // Flaskhals
        $flaskhals = $this->beraknaFlaskhals($toDate);

        // Genomsnittlig cykeltid
        $avgCykeltid = $this->getAvgCykeltidSek($fromDate, $toDate);

        // Rekommenderad bemanning baserat pa orderbehov
        $config = $this->loadKapacitetConfig();
        $ibcPerOpTimme = self::DEFAULT_IBC_PER_OPERATOR_TIMME;
        if (!empty($config)) {
            $sumIbcPerOp = 0;
            foreach ($config as $c) {
                $sumIbcPerOp += (float)$c['ibc_per_operator_timme'];
            }
            $ibcPerOpTimme = $sumIbcPerOp / count($config);
        }
        // Rek bemanning = antal operatorer som behovs for att na mal_utnyttjandegrad
        $malProd = $teorMax * 0.85; // 85% utnyttjandegrad som standard
        $rekBemanning = $ibcPerOpTimme > 0
            ? (int)ceil($malProd / ($ibcPerOpTimme * self::DEFAULT_SKIFT_TIMMAR * $dagar))
            : 0;

        // Snitt per dag (senaste 30 dagar for referens)
        $refFrom = date('Y-m-d', strtotime('-29 days'));
        try {
            $stmtTrend = $this->pdo->prepare("
                SELECT AVG(dag_total) AS snitt FROM (
                    SELECT DATE(datum) AS dag, COUNT(*) AS dag_total
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum)
                ) t
            ");
            $stmtTrend->execute([':from_date' => $refFrom, ':to_date' => $today]);
            $snittPerDag = (float)($stmtTrend->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getKpi (snitt): ' . $e->getMessage());
            $snittPerDag = 0.0;
        }

        $prognosVecka = round($snittPerDag * 5, 0);

        // Genomsnittligt utnyttjande senaste 30 dagar
        $antalStRef = $this->getAntalStationer($refFrom, $today);
        $teorMaxRef = $antalStRef * $maxPerStationPerDag;
        $utnyttjandeSnitt = $teorMaxRef > 0 ? min(100, round($snittPerDag / $teorMaxRef * 100, 1)) : 0.0;

        $this->sendSuccess([
            'idag' => [
                'datum'                 => $toDate,
                'faktisk_idag'          => $faktisk,
                'teormax_idag'          => (int)$teorMax,
                'utnyttjande_pct'       => $utnyttjande,
                'antal_stationer'       => $antalStationer,
                'drifttid_h'            => round($drifttidSek / 3600, 1),
                'ledig_kapacitet'       => $ledigKapacitet,
                'rek_bemanning'         => $rekBemanning,
            ],
            'period' => [
                'from_date'             => $refFrom,
                'to_date'               => $today,
                'snitt_per_dag'         => round($snittPerDag, 0),
                'teormax_per_dag'       => (int)$teorMaxRef,
                'utnyttjande_snitt_pct' => $utnyttjandeSnitt,
                'antal_stationer'       => $antalStRef,
                'optimal_cykeltid_sek'  => self::OPTIMAL_CYKELTID_SEK,
                'avg_cykeltid_sek'      => round($avgCykeltid, 1),
                'ibc_per_timme_optimal' => $ibcPerTimme,
                'prognos_vecka'         => (int)$prognosVecka,
            ],
            'flaskhals' => $flaskhals,
            'period_filter' => $periodFilter,
        ]);
    }

    /**
     * Beraknar flaskhals — rebotling_ibc har ingen station-kolumn.
     * Returnerar enkel produktionssammanfattning for dagen.
     */
    private function beraknaFlaskhals(string $datum): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS total_ok,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS total_ej_ok
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :datum AND datum < DATE_ADD(:datumb, INTERVAL 1 DAY)
                    GROUP BY skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':datum' => $datum, ':datumb' => $datum]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totalOk = (int)($row['total_ok'] ?? 0);
            $totalEjOk = (int)($row['total_ej_ok'] ?? 0);
            $total = $totalOk + $totalEjOk;

            if ($total === 0) {
                return ['station' => 'Rebotling', 'typ' => 'data', 'forklaring' => 'Ingen produktion registrerad idag'];
            }

            $kassationsGrad = $total > 0 ? round($totalEjOk / $total * 100, 1) : 0;
            $typ = $kassationsGrad > 5 ? 'kvalitet' : 'kapacitet';

            return [
                'station'           => 'Rebotling',
                'typ'               => $typ,
                'antal_idag'        => $total,
                'ok_idag'           => $totalOk,
                'ej_ok_idag'        => $totalEjOk,
                'kassationsgrad_pct'=> $kassationsGrad,
                'forklaring'        => "Produktion idag: {$totalOk} ok, {$totalEjOk} ej ok ({$kassationsGrad}% kassation)",
            ];
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::beraknaFlaskhals: ' . $e->getMessage());
            return ['station' => 'Okand', 'typ' => 'fel', 'forklaring' => 'Kunde inte berakna flaskhals'];
        }
    }

    // ================================================================
    // run=daglig-kapacitet
    // ================================================================

    private function getDagligKapacitet(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today  = new \DateTime();
        $result = [];

        $fromDateStr = (clone $today)->modify("-{$period} days")->format('Y-m-d');
        $toDateStr   = $today->format('Y-m-d');

        try {
            $stmtSnitt = $this->pdo->prepare("
                SELECT AVG(dag_total) AS snitt FROM (
                    SELECT DATE(datum) AS dag, COUNT(*) AS dag_total
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum)
                ) t
            ");
            $stmtSnitt->execute([':from_date' => $fromDateStr, ':to_date' => $toDateStr]);
            $snitt = (float)($stmtSnitt->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getDagligKapacitet (snitt): ' . $e->getMessage());
            $snitt = 0.0;
        }

        // Batch query: hämta produktion per dag i en enda query istället för N+1
        $prodPerDag = [];
        try {
            $stmtBatch = $this->pdo->prepare("
                SELECT
                    dag,
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_antal,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmtBatch->execute([':from_date' => $fromDateStr, ':to_date' => $toDateStr]);
            foreach ($stmtBatch->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $prodPerDag[$row['dag']] = $row;
            }
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getDagligKapacitet (batch): ' . $e->getMessage());
        }

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $today;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $rad = $prodPerDag[$dagStr] ?? null;
            $faktisk = $rad ? (int)($rad['ok_antal']) + (int)($rad['ej_ok_antal']) : 0;
            $antalStationer = 1;

            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = date('Y-m-d', strtotime($dagStr . ' +1 day')) . ' 00:00:00';
            $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);
            $teorMax     = $antalStationer * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK);
            $mal         = $this->getProduktionsmal($dagStr);
            $outnyttjad  = max(0, (int)$teorMax - $faktisk);

            $result[] = [
                'datum'           => $dagStr,
                'faktisk'         => $faktisk,
                'teor_max'        => (int)$teorMax,
                'mal'             => $mal,
                'outnyttjad'      => $outnyttjad,
                'utnyttjande_pct' => $teorMax > 0 ? round($faktisk / $teorMax * 100, 1) : 0.0,
                'drifttid_h'      => round($drifttidSek / 3600, 1),
                'antal_stationer' => $antalStationer,
            ];
        }

        $this->sendSuccess([
            'period_dagar' => $period,
            'from_date'    => $fromDateStr,
            'to_date'      => $toDateStr,
            'dagdata'      => $result,
            'genomsnitt'   => round($snitt, 0),
        ]);
    }

    // ================================================================
    // run=station-utnyttjande
    // ================================================================

    private function getStationUtnyttjande(): void {
        $periodFilter = trim($_GET['period_filter'] ?? '');
        if ($periodFilter) {
            $bounds = $this->getPeriodBounds($periodFilter);
            $fromDate = $bounds['from'];
            $today = $bounds['to'];
            $period = $bounds['dagar'];
        } else {
            $period   = max(7, min(365, (int)($_GET['period'] ?? 30)));
            $today    = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$period} days"));
        }

        // Hamta config for teoretisk kapacitet per station
        $config = $this->loadKapacitetConfig();
        $configMap = [];
        foreach ($config as $c) {
            $configMap[mb_strtolower(trim($c['station_id']))] = $c;
            $configMap[mb_strtolower(trim($c['station_namn']))] = $c;
        }

        // rebotling_ibc has no station column — aggregate as single line
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_ibc,
                    COUNT(DISTINCT dag) AS aktiva_dagar
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $rad = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getStationUtnyttjande: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta station-utnyttjande', 500);
            return;
        }

        $drifttidSek = $this->getDrifttidSek($fromDate . ' 00:00:00', date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00');
        $defaultTeorPerTimme = 3600 / self::OPTIMAL_CYKELTID_SEK;
        $timmarPeriod = $period * (self::PLANERAD_DRIFTTID_SEK / 3600);

        $okIbc = (int)($rad['ok_ibc'] ?? 0);
        $ejOkIbc = (int)($rad['ej_ok_ibc'] ?? 0);
        $total = $okIbc + $ejOkIbc;

        $teorPerTimme = $defaultTeorPerTimme;
        $malPct = 85.0;

        $teorMax = $teorPerTimme * $timmarPeriod;
        $utnyttjande = $teorMax > 0 ? min(100, round($total / $teorMax * 100, 1)) : 0.0;

        $stationer = [
            [
                'station'              => 'Rebotling',
                'total_ibc'            => $total,
                'ok_ibc'               => $okIbc,
                'aktiva_dagar'         => (int)($rad['aktiva_dagar'] ?? 0),
                'teor_max'             => (int)$teorMax,
                'teor_per_timme'       => $teorPerTimme,
                'utnyttjande_pct'      => $utnyttjande,
                'mal_pct'              => $malPct,
                'kassationsgrad_pct'   => $total > 0 ? round($ejOkIbc / $total * 100, 1) : 0.0,
            ],
        ];

        $this->sendSuccess([
            'period_dagar'    => $period,
            'from_date'       => $fromDate,
            'to_date'         => $today,
            'drifttid_h'      => round($drifttidSek / 3600, 1),
            'stationer'       => $stationer,
            'antal_stationer' => count($stationer),
        ]);
    }

    // ================================================================
    // run=stopporsaker
    // ================================================================

    private function getStopporsaker(): void {
        $period   = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';

        $planeradSek = $period * self::PLANERAD_DRIFTTID_SEK;
        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);
        $stoppSek = max(0, $planeradSek - $drifttidSek);

        // rebotling_onoff: datum (DATETIME), running (BOOLEAN)
        // Calculate stop durations from running state transitions
        $totalStoppSek = 0;
        $antalStopp = 0;
        $stoppLista = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum < :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prevTime = null;
            $prevRunning = null;

            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];

                if ($prevTime !== null && $prevRunning === 0) {
                    $stoppDuration = $ts - $prevTime;
                    if ($running === 1) {
                        // Stopp ended
                        $totalStoppSek += $stoppDuration;
                        $stoppLista[] = $stoppDuration;
                        $antalStopp++;
                    }
                }

                $prevTime = $ts;
                $prevRunning = $running;
            }
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getStopporsaker: ' . $e->getMessage());
        }

        $avgStoppSek = $antalStopp > 0 ? $totalStoppSek / $antalStopp : 0;
        $stoppRad = ['antal_stopp' => $antalStopp, 'total_stopp_sek' => $totalStoppSek, 'avg_stopp_sek' => $avgStoppSek];

        $kategorier = [
            'Kort stopp (<5 min)'    => 0,
            'Medel stopp (5-30 min)' => 0,
            'Langt stopp (>30 min)'  => 0,
        ];
        foreach ($stoppLista as $sek) {
            $sek = (int)$sek;
            if ($sek < 300) {
                $kategorier['Kort stopp (<5 min)'] += $sek;
            } elseif ($sek < 1800) {
                $kategorier['Medel stopp (5-30 min)'] += $sek;
            } else {
                $kategorier['Langt stopp (>30 min)'] += $sek;
            }
        }

        $idleSek = max(0, $stoppSek - (int)($stoppRad['total_stopp_sek'] ?? 0));
        $kategorier['Idle / ej registrerat'] = $idleSek;

        $orsaker = [];
        $totalKategoriserat = array_sum($kategorier);
        foreach ($kategorier as $namn => $sek) {
            $orsaker[] = [
                'namn'      => $namn,
                'sek'       => $sek,
                'min'       => round($sek / 60, 1),
                'andel_pct' => $totalKategoriserat > 0 ? round($sek / $totalKategoriserat * 100, 1) : 0.0,
            ];
        }

        $this->sendSuccess([
            'period_dagar'     => $period,
            'from_date'        => $fromDate,
            'to_date'          => $today,
            'planerad_h'       => round($planeradSek / 3600, 1),
            'drifttid_h'       => round($drifttidSek / 3600, 1),
            'stopp_h'          => round($stoppSek / 3600, 1),
            'antal_stopp'      => (int)($stoppRad['antal_stopp'] ?? 0),
            'avg_stopp_min'    => round((float)($stoppRad['avg_stopp_sek'] ?? 0) / 60, 1),
            'orsaker'          => $orsaker,
        ]);
    }

    // ================================================================
    // run=tid-fordelning
    // ================================================================

    private function getTidFordelning(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today  = new \DateTime();
        $result = [];

        // Batch query: hämta IBC-totaler per dag istället för N+1
        $fromDateStr = (clone $today)->modify("-{$period} days")->format('Y-m-d');
        $toDateStr   = $today->format('Y-m-d');
        $ibcPerDag = [];
        try {
            $stmtBatch = $this->pdo->prepare("
                SELECT dag, COALESCE(SUM(max_ok + max_ej_ok), 0) AS total
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(ibc_ok) AS max_ok, MAX(ibc_ej_ok) AS max_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
            ");
            $stmtBatch->execute([':from_date' => $fromDateStr, ':to_date' => $toDateStr]);
            foreach ($stmtBatch->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ibcPerDag[$row['dag']] = (int)$row['total'];
            }
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getTidFordelning (batch): ' . $e->getMessage());
        }

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $today;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');
            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = date('Y-m-d', strtotime($dagStr . ' +1 day')) . ' 00:00:00';

            $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);
            $planeradSek = self::PLANERAD_DRIFTTID_SEK;

            $antalIbc = $ibcPerDag[$dagStr] ?? 0;

            $produktivSek = min($drifttidSek, $antalIbc * self::OPTIMAL_CYKELTID_SEK);
            $stoppSek     = max(0, $planeradSek - $drifttidSek);
            $idleSek      = max(0, $drifttidSek - $produktivSek);

            $result[] = [
                'datum'        => $dagStr,
                'produktiv_h'  => round($produktivSek / 3600, 2),
                'stopp_h'      => round($stoppSek / 3600, 2),
                'idle_h'       => round($idleSek / 3600, 2),
                'drifttid_h'   => round($drifttidSek / 3600, 2),
                'planerad_h'   => round($planeradSek / 3600, 1),
                'antal_ibc'    => $antalIbc,
            ];
        }

        $fromDateStr = (clone $today)->modify("-{$period} days")->format('Y-m-d');
        $this->sendSuccess([
            'period_dagar' => $period,
            'from_date'    => $fromDateStr,
            'to_date'      => $today->format('Y-m-d'),
            'dagdata'      => $result,
        ]);
    }

    // ================================================================
    // run=vecko-oversikt
    // ================================================================

    private function getVeckoOversikt(): void {
        $antalVeckor = 12;
        $today = new \DateTime();
        $result = [];

        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(dag_tot), 0) AS total_ibc,
                COUNT(DISTINCT dag) AS prod_dagar,
                MAX(dag_tot) AS basta_dag_antal,
                MIN(dag_tot) AS samsta_dag_antal,
                MAX(dag) AS basta_datum,
                MIN(dag) AS samsta_datum
            FROM (
                SELECT
                    dag,
                    SUM(max_ok + max_ej_ok) AS dag_tot
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS max_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS max_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :monday AND :friday
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) per_skift
                GROUP BY dag
            ) t
        ");

        for ($w = $antalVeckor - 1; $w >= 0; $w--) {
            $veckoSlut = clone $today;
            $veckoSlut->modify("-{$w} weeks");
            $dayOfWeek = (int)$veckoSlut->format('N');
            $mdag = clone $veckoSlut;
            $mdag->modify('-' . ($dayOfWeek - 1) . ' days');
            $fdag = clone $mdag;
            $fdag->modify('+4 days');

            $mondayStr = $mdag->format('Y-m-d');
            $fridayStr = $fdag->format('Y-m-d');
            $weekNum   = (int)$mdag->format('W');
            $yearNum   = (int)$mdag->format('Y');

            try {
                $stmt->execute([':monday' => $mondayStr, ':friday' => $fridayStr]);
                $rad = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log('KapacitetsplaneringController::getVeckoOversikt: ' . $e->getMessage());
                $rad = null;
            }

            if (!$rad || (int)$rad['total_ibc'] === 0) {
                $result[] = [
                    'vecka' => $weekNum, 'ar' => $yearNum,
                    'from_datum' => $mondayStr, 'to_datum' => $fridayStr,
                    'total_ibc' => 0,
                    'max_kapacitet' => 5 * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK),
                    'utnyttjande_pct' => 0.0, 'trend' => 'neutral',
                    'basta_dag' => null, 'samsta_dag' => null,
                    'basta_dag_antal' => 0, 'samsta_dag_antal' => 0,
                ];
                continue;
            }

            $totalIbc     = (int)$rad['total_ibc'];
            $maxKapacitet = 5 * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK);
            $utnyttjande  = $maxKapacitet > 0 ? min(100, round($totalIbc / $maxKapacitet * 100, 1)) : 0.0;

            $result[] = [
                'vecka'            => $weekNum,
                'ar'               => $yearNum,
                'from_datum'       => $mondayStr,
                'to_datum'         => $fridayStr,
                'total_ibc'        => $totalIbc,
                'max_kapacitet'    => (int)$maxKapacitet,
                'utnyttjande_pct'  => $utnyttjande,
                'trend'            => $utnyttjande >= 80 ? 'upp' : ($utnyttjande >= 60 ? 'neutral' : 'ned'),
                'basta_dag'        => $rad['basta_datum'],
                'samsta_dag'       => $rad['samsta_datum'],
                'basta_dag_antal'  => (int)($rad['basta_dag_antal'] ?? 0),
                'samsta_dag_antal' => (int)($rad['samsta_dag_antal'] ?? 0),
            ];
        }

        for ($i = 1; $i < count($result); $i++) {
            $foreg   = $result[$i - 1]['total_ibc'];
            $aktuell = $result[$i]['total_ibc'];
            if ($foreg > 0) {
                $result[$i]['trend'] = $aktuell > $foreg * 1.05 ? 'upp' : ($aktuell < $foreg * 0.95 ? 'ned' : 'neutral');
            }
        }

        $this->sendSuccess([
            'antal_veckor' => $antalVeckor,
            'veckor'       => $result,
        ]);
    }

    // ================================================================
    // run=utnyttjandegrad-trend -- linjediagram med mal-linje
    // ================================================================

    private function getUtnyttjandegradTrend(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today  = new \DateTime();
        $result = [];

        $config = $this->loadKapacitetConfig();
        $malPct = 85.0;
        if (!empty($config)) {
            $sumMal = 0;
            foreach ($config as $c) {
                $sumMal += (float)$c['mal_utnyttjandegrad_pct'];
            }
            $malPct = round($sumMal / count($config), 1);
        }

        // Batch query: hämta produktion per dag istället för N+1
        $fromDateStr = (clone $today)->modify("-{$period} days")->format('Y-m-d');
        $toDateStr   = $today->format('Y-m-d');
        $prodPerDag = [];
        try {
            $stmtBatch = $this->pdo->prepare("
                SELECT
                    dag,
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_antal,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
            ");
            $stmtBatch->execute([':from_date' => $fromDateStr, ':to_date' => $toDateStr]);
            foreach ($stmtBatch->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $prodPerDag[$row['dag']] = $row;
            }
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getUtnyttjandegradTrend (batch): ' . $e->getMessage());
        }

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $today;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $rad = $prodPerDag[$dagStr] ?? null;
            $faktisk = $rad ? (int)($rad['ok_antal']) + (int)($rad['ej_ok_antal']) : 0;
            $antalStationer = 1;

            $teorMax = $antalStationer * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK);
            $utnyttjande = $teorMax > 0 ? round($faktisk / $teorMax * 100, 1) : 0.0;

            $result[] = [
                'datum'           => $dagStr,
                'utnyttjande_pct' => $utnyttjande,
                'faktisk'         => $faktisk,
                'teor_max'        => (int)$teorMax,
            ];
        }

        $this->sendSuccess([
            'period_dagar' => $period,
            'mal_pct'      => $malPct,
            'dagdata'      => $result,
        ]);
    }

    // ================================================================
    // run=kapacitetstabell -- detaljerad tabell per station
    // ================================================================

    private function getKapacitetstabell(): void {
        $periodFilter = trim($_GET['period_filter'] ?? '');
        if ($periodFilter) {
            $bounds = $this->getPeriodBounds($periodFilter);
            $fromDate = $bounds['from'];
            $today = $bounds['to'];
            $dagar = $bounds['dagar'];
        } else {
            $dagar    = max(1, min(365, (int)($_GET['period'] ?? 30)));
            $today    = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$dagar} days"));
        }

        $config = $this->loadKapacitetConfig();
        $configMap = [];
        foreach ($config as $c) {
            $configMap[mb_strtolower(trim($c['station_id']))] = $c;
            $configMap[mb_strtolower(trim($c['station_namn']))] = $c;
        }

        // rebotling_ibc has no station column — aggregate as single line
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_ibc,
                    COUNT(DISTINCT dag) AS aktiva_dagar
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $aggRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $rader = [
                [
                    'station' => 'Rebotling',
                    'total_ibc' => (int)($aggRow['ok_ibc'] ?? 0) + (int)($aggRow['ej_ok_ibc'] ?? 0),
                    'ok_ibc' => (int)($aggRow['ok_ibc'] ?? 0),
                    'aktiva_dagar' => (int)($aggRow['aktiva_dagar'] ?? 0),
                ],
            ];
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getKapacitetstabell: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta kapacitetstabell', 500);
            return;
        }

        // Hamta forsta halvan av perioden for trendberakning
        $halfDagar = max(1, (int)floor($dagar / 2));
        $halfDate = date('Y-m-d', strtotime("-{$halfDagar} days", strtotime($today)));

        try {
            $stmtHalf = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok) + SUM(max_ibc_ej_ok), 0) AS total_ibc
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :half_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmtHalf->execute([':from_date' => $fromDate, ':half_date' => $halfDate]);
            $halfRow = $stmtHalf->fetch(\PDO::FETCH_ASSOC);
            $halfMap = ['rebotling' => (int)($halfRow['total_ibc'] ?? 0)];
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getKapacitetstabell (half): ' . $e->getMessage());
            $halfMap = [];
        }

        $timmarPerDag = self::PLANERAD_DRIFTTID_SEK / 3600;
        $defaultTeorPerTimme = 3600 / self::OPTIMAL_CYKELTID_SEK;

        $tabellRader = [];
        foreach ($rader as $rad) {
            $stationKey = mb_strtolower(trim($rad['station']));
            $aktivaDagar = max(1, (int)$rad['aktiva_dagar']);
            $total = (int)$rad['total_ibc'];

            $teorPerTimme = $defaultTeorPerTimme;
            $malPct = 85.0;
            if (isset($configMap[$stationKey])) {
                $teorPerTimme = (float)$configMap[$stationKey]['teoretisk_kapacitet_per_timme'];
                $malPct = (float)$configMap[$stationKey]['mal_utnyttjandegrad_pct'];
            }

            $faktiskPerTimme = $aktivaDagar > 0 ? round($total / ($aktivaDagar * $timmarPerDag), 2) : 0;
            $utnyttjande = $teorPerTimme > 0 ? round($faktiskPerTimme / $teorPerTimme * 100, 1) : 0;

            // Flaskhalsfaktor: 1.0 = lika bra som basta; <1.0 = flaskhals
            $bestPerTimme = 0;
            foreach ($rader as $r) {
                $rAktiva = max(1, (int)$r['aktiva_dagar']);
                $rPerTimme = (int)$r['total_ibc'] / ($rAktiva * $timmarPerDag);
                if ($rPerTimme > $bestPerTimme) $bestPerTimme = $rPerTimme;
            }
            $flaskhalsFaktor = $bestPerTimme > 0 ? round($faktiskPerTimme / $bestPerTimme, 2) : 1.0;

            // Trend: jamfor forsta vs andra halvan
            $halfTotal = $halfMap[$stationKey] ?? 0;
            $secondHalf = $total - $halfTotal;
            $trend = 'stabil';
            if ($halfTotal > 0) {
                $diff = ($secondHalf - $halfTotal) / $halfTotal;
                if ($diff > 0.05) $trend = 'upp';
                elseif ($diff < -0.05) $trend = 'ned';
            }

            $tabellRader[] = [
                'station'            => $rad['station'],
                'teor_kap_per_h'     => $teorPerTimme,
                'faktisk_kap_per_h'  => $faktiskPerTimme,
                'utnyttjande_pct'    => $utnyttjande,
                'mal_pct'            => $malPct,
                'flaskhals_faktor'   => $flaskhalsFaktor,
                'trend'              => $trend,
                'total_ibc'          => $total,
                'ok_ibc'             => (int)$rad['ok_ibc'],
                'aktiva_dagar'       => $aktivaDagar,
            ];
        }

        $this->sendSuccess([
            'from_date'  => $fromDate,
            'to_date'    => $today,
            'dagar'      => $dagar,
            'stationer'  => $tabellRader,
        ]);
    }

    // ================================================================
    // run=bemanning -- bemanningsplanering
    // ================================================================

    private function getBemanning(): void {
        $orderbehov = max(0, (int)($_GET['orderbehov'] ?? 500));
        $periodFilter = trim($_GET['period_filter'] ?? 'idag');
        $bounds = $this->getPeriodBounds($periodFilter);
        $dagar = max(1, $bounds['dagar']);

        $config = $this->loadKapacitetConfig();
        $defaultIbcPerOpTimme = self::DEFAULT_IBC_PER_OPERATOR_TIMME;
        $skiftTimmar = self::DEFAULT_SKIFT_TIMMAR;

        // Hamta historisk produktivitet per operator
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ok + max_ej_ok), 0) AS total_ibc,
                    COUNT(DISTINCT dag) AS prod_dagar
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(ibc_ok) AS max_ok, MAX(ibc_ej_ok) AS max_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmt->execute();
            $histRad = $stmt->fetch(\PDO::FETCH_ASSOC);
            $histIbc = (int)($histRad['total_ibc'] ?? 0);
            $histDagar = max(1, (int)($histRad['prod_dagar'] ?? 1));
            $histIbcPerOpPerDag = $histIbc / $histDagar;
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getBemanning (hist): ' . $e->getMessage());
            $histIbcPerOpPerDag = $defaultIbcPerOpTimme * $skiftTimmar;
        }

        $ibcPerOpPerDag = max(1, $histIbcPerOpPerDag);

        // Antal operatorer som behovs totalt
        $totalOpBehov = ceil($orderbehov / ($ibcPerOpPerDag * $dagar));

        // Per skift (anta 2 skift)
        $opPerSkift = ceil($totalOpBehov / 2);

        // Per station: berakna vilka stationer som behover extra
        $stationBemanning = [];
        if (!empty($config)) {
            $ibcPerStation = $orderbehov / max(1, count($config));
            foreach ($config as $c) {
                $stationIbcPerOp = max(1, (float)$c['ibc_per_operator_timme'] * $skiftTimmar * $dagar);
                $opForStation = ceil($ibcPerStation / $stationIbcPerOp);
                $stationBemanning[] = [
                    'station'    => $c['station_namn'],
                    'operatorer' => (int)$opForStation,
                    'extra'      => $opForStation > 1,
                ];
            }
        }

        $this->sendSuccess([
            'orderbehov'            => $orderbehov,
            'period_filter'         => $periodFilter,
            'dagar'                 => $dagar,
            'ibc_per_op_per_dag'    => round($ibcPerOpPerDag, 1),
            'total_operatorer'      => (int)$totalOpBehov,
            'operatorer_per_skift'  => (int)$opPerSkift,
            'antal_skift'           => 2,
            'stationer'             => $stationBemanning,
        ]);
    }

    // ================================================================
    // run=prognos -- simulator
    // ================================================================

    private function getPrognos(): void {
        $timmar     = max(1, min(24, (int)($_GET['timmar'] ?? 8)));
        $operatorer = max(1, min(50, (int)($_GET['operatorer'] ?? 4)));

        $config = $this->loadKapacitetConfig();
        $antalStationer = !empty($config) ? count($config) : $this->getAntalStationer(
            date('Y-m-d', strtotime('-30 days')), date('Y-m-d')
        );

        // Historisk produktivitet
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ok + max_ej_ok), 0) AS total_ibc,
                    COUNT(DISTINCT dag) AS prod_dagar
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(ibc_ok) AS max_ok, MAX(ibc_ej_ok) AS max_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
            ");
            $stmt->execute();
            $histRad = $stmt->fetch(\PDO::FETCH_ASSOC);
            $histIbc = (int)($histRad['total_ibc'] ?? 0);
            $histDagar = max(1, (int)($histRad['prod_dagar'] ?? 1));
            $ibcPerOpPerTimme = $histIbc / ($histDagar * (self::PLANERAD_DRIFTTID_SEK / 3600));
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getPrognos (hist): ' . $e->getMessage());
            $ibcPerOpPerTimme = self::DEFAULT_IBC_PER_OPERATOR_TIMME;
        }

        // Teoretisk max baserat pa stationer
        $teorMaxPerTimme = 0;
        if (!empty($config)) {
            foreach ($config as $c) {
                $teorMaxPerTimme += (float)$c['teoretisk_kapacitet_per_timme'];
            }
        } else {
            $teorMaxPerTimme = $antalStationer * (3600 / self::OPTIMAL_CYKELTID_SEK);
        }

        $teorMaxTotal = $teorMaxPerTimme * $timmar;

        // Berakna baserat pa operatorer
        $opBaserad = round($ibcPerOpPerTimme * $operatorer * $timmar, 0);

        // Begransas av maskinkapacitet
        $prognos = min($opBaserad, $teorMaxTotal);
        $begransadAv = $opBaserad <= $teorMaxTotal ? 'bemanning' : 'maskinkapacitet';

        $this->sendSuccess([
            'timmar'              => $timmar,
            'operatorer'          => $operatorer,
            'antal_stationer'     => $antalStationer,
            'ibc_per_op_per_h'    => round($ibcPerOpPerTimme, 1),
            'teor_max_per_h'      => round($teorMaxPerTimme, 0),
            'teor_max_total'      => (int)$teorMaxTotal,
            'op_baserad'          => (int)$opBaserad,
            'prognos_ibc'         => (int)$prognos,
            'begransad_av'        => $begransadAv,
        ]);
    }

    // ================================================================
    // run=config -- hamta kapacitet_config
    // ================================================================

    private function getConfig(): void {
        $config = $this->loadKapacitetConfig();
        $this->sendSuccess([
            'config' => $config,
        ]);
    }
}
