<?php
/**
 * KapacitetsplaneringController.php
 * Rebotling kapacitetsplanering — planerad vs faktisk kapacitet, flaskhalsanalys.
 *
 * Endpoints via ?action=kapacitetsplanering&run=XXX:
 *   - run=kpi                — samlade KPI:er (kapacitetsutnyttjande, faktisk/teoretisk, flaskhals, cykeltider, prognos)
 *   - run=daglig-kapacitet   — daglig faktisk prod + teoretisk max + ev. mal, senaste N dagar (?period=30)
 *   - run=station-utnyttjande — kapacitetsutnyttjande per station
 *   - run=stopporsaker       — fordelning av stopptid per orsak/typ
 *   - run=tid-fordelning     — daglig fordelning: produktiv tid vs stopp vs idle per dag (?period=30)
 *   - run=vecko-oversikt     — veckosammanstalning senaste 12 veckor
 *
 * OEE: T = drifttid/planerad, P = (IBC*cykeltid)/drifttid, K = godkanda/totalt
 * Optimal cykeltid: 120 sekunder (2 min per IBC)
 * Teoretisk max: antal_stationer * planerad_drifttid * (3600/optimal_cykeltid)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_produktionsmal (om finns)
 */
class KapacitetsplaneringController {
    private $pdo;

    private const OPTIMAL_CYKELTID_SEK = 120;   // 2 minuter per IBC
    private const PLANERAD_DRIFTTID_SEK = 8 * 3600; // 8 timmar per dag

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
            case 'kpi':                 $this->getKpi();                break;
            case 'daglig-kapacitet':    $this->getDagligKapacitet();    break;
            case 'station-utnyttjande': $this->getStationUtnyttjande(); break;
            case 'stopporsaker':        $this->getStopporsaker();       break;
            case 'tid-fordelning':      $this->getTidFordelning();      break;
            case 'vecko-oversikt':      $this->getVeckoOversikt();      break;
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

    /**
     * Hamtar antal unika aktiva stationer for ett datumintervall.
     */
    private function getAntalStationer(string $fromDate, string $toDate): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT station) AS antal
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                  AND DATE(datum) BETWEEN :from_date AND :to_date
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            return max(1, (int)($stmt->fetchColumn() ?? 1));
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getAntalStationer: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Beraknar total drifttid (sek) fran rebotling_onoff for ett intervall.
     */
    private function getDrifttidSek(string $fromDt, string $toDt): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(
                    TIMESTAMPDIFF(SECOND,
                        GREATEST(start_time, :from1),
                        LEAST(COALESCE(stop_time, NOW()), :to1)
                    )
                ), 0) AS drifttid_sek
                FROM rebotling_onoff
                WHERE start_time < :to2
                  AND (stop_time IS NULL OR stop_time > :from2)
                  AND TIMESTAMPDIFF(SECOND,
                        GREATEST(start_time, :from3),
                        LEAST(COALESCE(stop_time, NOW()), :to3)
                      ) > 0
            ");
            $stmt->execute([
                ':from1' => $fromDt, ':to1' => $toDt,
                ':from2' => $fromDt, ':to2' => $toDt,
                ':from3' => $fromDt, ':to3' => $toDt,
            ]);
            return max(0, (int)($stmt->fetchColumn() ?? 0));
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
        } catch (\PDOException) {
            // Tabellen kanske inte finns — ignorera
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
                        LAG(datum) OVER (PARTITION BY station ORDER BY datum),
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

    // ================================================================
    // run=kpi — samlade KPI:er
    // ================================================================

    private function getKpi(): void {
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-29 days'));

        // Idag
        $todayFromDt = $today . ' 00:00:00';
        $todayToDt   = $today . ' 23:59:59';

        $drifttidIdagSek = $this->getDrifttidSek($todayFromDt, $todayToDt);

        try {
            $stmtIdag = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_antal,
                    COUNT(DISTINCT station) AS antal_stationer
                FROM rebotling_ibc
                WHERE DATE(datum) = :idag
            ");
            $stmtIdag->execute([':idag' => $today]);
            $radIdag = $stmtIdag->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getKpi idag: ' . $e->getMessage());
            $radIdag = ['total' => 0, 'ok_antal' => 0, 'antal_stationer' => 1];
        }

        $antalStationerIdag = max(1, (int)($radIdag['antal_stationer'] ?? 1));
        $faktiskIdag        = (int)($radIdag['total'] ?? 0);

        // Teoretisk max per dag: stationer * planerad_drifttid * (3600/optimal_cykeltid)
        $ibcPerTimme       = 3600 / self::OPTIMAL_CYKELTID_SEK;  // 30 IBC/h
        $maxPerStation     = self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK; // 240 IBC/dag
        $teorMaxIdag       = $antalStationerIdag * $maxPerStation;

        // Kapacitetsutnyttjande idag
        $utnyttjandeIdag = $teorMaxIdag > 0 ? min(100, round($faktiskIdag / $teorMaxIdag * 100, 1)) : 0.0;

        // Senaste 30 dagar — snittcykeltid
        $avgCykeltid = $this->getAvgCykeltidSek($fromDate, $today);

        // Flaskhals — station med lagst produktion relativt genomsnitt
        $flaskhals = $this->beraknaFlaskhals($today);

        // Prognos vecka: genomsnitt per dag * 5 (man-fre)
        try {
            $stmtTrend = $this->pdo->prepare("
                SELECT AVG(dag_total) AS snitt FROM (
                    SELECT DATE(datum) AS dag, COUNT(*) AS dag_total
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum)
                ) t
            ");
            $stmtTrend->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $snittPerDag = (float)($stmtTrend->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            $snittPerDag = 0.0;
        }

        $prognosVecka = round($snittPerDag * 5, 0);

        // Antal aktiva stationer senaste 30 dagar
        $antalStationer = $this->getAntalStationer($fromDate, $today);
        $teorMaxPerDag  = $antalStationer * $maxPerStation;

        // Genomsnittligt utnyttjande senaste 30 dagar
        $utnyttjandeSnitt = $teorMaxPerDag > 0 ? min(100, round($snittPerDag / $teorMaxPerDag * 100, 1)) : 0.0;

        $this->sendSuccess([
            'idag' => [
                'datum'                 => $today,
                'faktisk_idag'          => $faktiskIdag,
                'teormax_idag'          => (int)$teorMaxIdag,
                'utnyttjande_pct'       => $utnyttjandeIdag,
                'antal_stationer'       => $antalStationerIdag,
                'drifttid_h'            => round($drifttidIdagSek / 3600, 1),
            ],
            'period' => [
                'from_date'             => $fromDate,
                'to_date'               => $today,
                'snitt_per_dag'         => round($snittPerDag, 0),
                'teormax_per_dag'       => (int)$teorMaxPerDag,
                'utnyttjande_snitt_pct' => $utnyttjandeSnitt,
                'antal_stationer'       => $antalStationer,
                'optimal_cykeltid_sek'  => self::OPTIMAL_CYKELTID_SEK,
                'avg_cykeltid_sek'      => round($avgCykeltid, 1),
                'ibc_per_timme_optimal' => $ibcPerTimme,
                'prognos_vecka'         => (int)$prognosVecka,
            ],
            'flaskhals' => $flaskhals,
        ]);
    }

    /**
     * Beraknar flaskhals — vilken station/faktor begransar mest.
     */
    private function beraknaFlaskhals(string $datum): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    station,
                    COUNT(*) AS antal,
                    AVG(TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY station ORDER BY datum),
                        datum
                    )) AS avg_cykeltid
                FROM rebotling_ibc
                WHERE DATE(datum) = :datum
                GROUP BY station
                ORDER BY antal ASC
            ");
            $stmt->execute([':datum' => $datum]);
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rader)) {
                return ['station' => 'Ingen data', 'typ' => 'data', 'forklaring' => 'Ingen produktion registrerad idag'];
            }

            // Station med minst produktion = potentiell flaskhals
            $samst = $rader[0];

            $avgAll = array_sum(array_column($rader, 'antal')) / count($rader);
            $gapPct = $avgAll > 0 ? round((1 - (float)$samst['antal'] / $avgAll) * 100, 1) : 0.0;

            // Kolla ocksa om genomsnittlig cykeltid ar hog
            $avgCykeltid = (float)($samst['avg_cykeltid'] ?? 0);
            $typ = 'kapacitet';
            if ($avgCykeltid > self::OPTIMAL_CYKELTID_SEK * 1.5) {
                $typ = 'cykeltid';
            }

            return [
                'station'       => $samst['station'],
                'typ'           => $typ,
                'antal_idag'    => (int)$samst['antal'],
                'snitt_alla'    => round($avgAll, 0),
                'gap_pct'       => $gapPct,
                'avg_cykeltid_sek' => round($avgCykeltid, 1),
                'forklaring'    => $typ === 'cykeltid'
                    ? "Station {$samst['station']} har lang cykeltid ({$avgCykeltid}s vs optimal " . self::OPTIMAL_CYKELTID_SEK . "s)"
                    : "Station {$samst['station']} producerade {$samst['antal']} IBC vs snitt {$avgAll}",
            ];
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::beraknaFlaskhals: ' . $e->getMessage());
            return ['station' => 'Okand', 'typ' => 'fel', 'forklaring' => 'Kunde inte berakna flaskhals'];
        }
    }

    // ================================================================
    // run=daglig-kapacitet — daglig faktisk prod + teoretisk max
    // ================================================================

    private function getDagligKapacitet(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today  = new \DateTime();
        $result = [];

        // Hamta genomsnitt over perioden for referenslinje
        $fromDateStr = (clone $today)->modify("-{$period} days")->format('Y-m-d');
        $toDateStr   = $today->format('Y-m-d');

        // Genomsnitt
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
        } catch (\PDOException) {
            $snitt = 0.0;
        }

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $today;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = $dagStr . ' 23:59:59';

            // Faktisk produktion
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COUNT(*) AS total,
                        COUNT(DISTINCT station) AS antal_stationer
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :dag
                ");
                $stmt->execute([':dag' => $dagStr]);
                $rad = $stmt->fetch(\PDO::FETCH_ASSOC);
                $faktisk         = (int)($rad['total'] ?? 0);
                $antalStationer  = max(1, (int)($rad['antal_stationer'] ?? 1));
            } catch (\PDOException) {
                $faktisk        = 0;
                $antalStationer = 1;
            }

            // Drifttid
            $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

            // Teoretisk max: stationer * planerad_drifttid / optimal_cykeltid
            $teorMax = $antalStationer * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK);

            // Planerat mal
            $mal = $this->getProduktionsmal($dagStr);

            // Outnyttjad kapacitet
            $outnyttjad = max(0, (int)$teorMax - $faktisk);

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
    // run=station-utnyttjande — kapacitetsutnyttjande per station
    // ================================================================

    private function getStationUtnyttjande(): void {
        $period   = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    station,
                    COUNT(*) AS total_ibc,
                    SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_ibc,
                    COUNT(DISTINCT DATE(datum)) AS aktiva_dagar
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                  AND DATE(datum) BETWEEN :from_date AND :to_date
                GROUP BY station
                ORDER BY total_ibc DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $today]);
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getStationUtnyttjande: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta station-utnyttjande');
            return;
        }

        // Drifttid (gemensam for hela anlaggningen)
        $drifttidSek = $this->getDrifttidSek($fromDate . ' 00:00:00', $today . ' 23:59:59');

        $maxPerStation = (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK) * $period; // 240 * period

        $stationer = [];
        foreach ($rader as $rad) {
            $total = (int)$rad['total_ibc'];
            $utnyttjande = $maxPerStation > 0 ? min(100, round($total / $maxPerStation * 100, 1)) : 0.0;
            $stationer[] = [
                'station'         => $rad['station'],
                'total_ibc'       => $total,
                'ok_ibc'          => (int)$rad['ok_ibc'],
                'aktiva_dagar'    => (int)$rad['aktiva_dagar'],
                'teor_max'        => (int)$maxPerStation,
                'utnyttjande_pct' => $utnyttjande,
                'kassationsgrad_pct' => $total > 0 ? round(($total - (int)$rad['ok_ibc']) / $total * 100, 1) : 0.0,
            ];
        }

        $this->sendSuccess([
            'period_dagar'   => $period,
            'from_date'      => $fromDate,
            'to_date'        => $today,
            'drifttid_h'     => round($drifttidSek / 3600, 1),
            'stationer'      => $stationer,
            'antal_stationer'=> count($stationer),
        ]);
    }

    // ================================================================
    // run=stopporsaker — fordelning av stopptid
    // ================================================================

    private function getStopporsaker(): void {
        $period   = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = $today   . ' 23:59:59';

        // Hamta total daglig tid (planerad)
        $planeradSek = $period * self::PLANERAD_DRIFTTID_SEK;

        // Drifttid (maskin pa)
        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        // Stopp-tid = planerad - drifttid
        $stoppSek = max(0, $planeradSek - $drifttidSek);

        // Antal stopp-intervaller
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS antal_stopp,
                    COALESCE(SUM(
                        TIMESTAMPDIFF(SECOND, start_time, COALESCE(stop_time, NOW()))
                    ), 0) AS total_stopp_sek,
                    AVG(
                        TIMESTAMPDIFF(SECOND, start_time, COALESCE(stop_time, NOW()))
                    ) AS avg_stopp_sek
                FROM rebotling_onoff
                WHERE start_time >= :from_dt
                  AND start_time < :to_dt
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $stoppRad = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('KapacitetsplaneringController::getStopporsaker stopp: ' . $e->getMessage());
            $stoppRad = ['antal_stopp' => 0, 'total_stopp_sek' => 0, 'avg_stopp_sek' => 0];
        }

        // Simulera stopporsaks-fordelning baserat pa timing-monster
        // (rebotling_onoff har ingen orsak-kolumn — vi kategoriserar efter varaktighet)
        try {
            $stmt2 = $this->pdo->prepare("
                SELECT
                    TIMESTAMPDIFF(SECOND, start_time, COALESCE(stop_time, NOW())) AS varaktighet_sek
                FROM rebotling_onoff
                WHERE start_time >= :from_dt
                  AND start_time < :to_dt
                ORDER BY start_time
            ");
            $stmt2->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $stoppLista = $stmt2->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            $stoppLista = [];
        }

        // Kategorisera stopp efter varaktighet
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

        // Lagg till idle-tid
        $idleSek = max(0, $stoppSek - (int)($stoppRad['total_stopp_sek'] ?? 0));
        $kategorier['Idle / ej registrerat'] = $idleSek;

        $orsaker = [];
        $totalKategoriserat = array_sum($kategorier);
        foreach ($kategorier as $namn => $sek) {
            $orsaker[] = [
                'namn'    => $namn,
                'sek'     => $sek,
                'min'     => round($sek / 60, 1),
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
    // run=tid-fordelning — daglig produktiv vs stopp vs idle
    // ================================================================

    private function getTidFordelning(): void {
        $period = max(7, min(365, (int)($_GET['period'] ?? 30)));
        $today  = new \DateTime();
        $result = [];

        for ($i = $period - 1; $i >= 0; $i--) {
            $dag    = clone $today;
            $dag->modify("-{$i} days");
            $dagStr = $dag->format('Y-m-d');

            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = $dagStr . ' 23:59:59';

            $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);
            $planeradSek = self::PLANERAD_DRIFTTID_SEK;

            // Produktiv tid = IBC * optimal_cykeltid (max drifttid)
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS total FROM rebotling_ibc WHERE DATE(datum) = :dag
                ");
                $stmt->execute([':dag' => $dagStr]);
                $antalIbc = (int)($stmt->fetchColumn() ?? 0);
            } catch (\PDOException) {
                $antalIbc = 0;
            }

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
    // run=vecko-oversikt — veckosammanstallning senaste 12 veckor
    // ================================================================

    private function getVeckoOversikt(): void {
        $antalVeckor = 12;
        $today = new \DateTime();

        $result = [];

        for ($w = $antalVeckor - 1; $w >= 0; $w--) {
            // Berakna veckostart (mandag) och veckoslut (sondag)
            $veckoSlut   = clone $today;
            $veckoSlut->modify("-{$w} weeks");

            // Ga till mandag i den veckan
            $dayOfWeek = (int)$veckoSlut->format('N'); // 1=Mon 7=Sun
            $mdag = clone $veckoSlut;
            $mdag->modify('-' . ($dayOfWeek - 1) . ' days');
            $fdag = clone $mdag;
            $fdag->modify('+4 days'); // fredag

            $mondayStr = $mdag->format('Y-m-d');
            $fridayStr = $fdag->format('Y-m-d');
            $weekNum   = (int)$mdag->format('W');
            $yearNum   = (int)$mdag->format('Y');

            // Produktion for veckan
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COUNT(*) AS total_ibc,
                        COUNT(DISTINCT DATE(datum)) AS prod_dagar,
                        MAX(dag_tot) AS basta_dag_antal,
                        MIN(dag_tot) AS samsta_dag_antal,
                        MAX(basta_dat) AS basta_datum,
                        MIN(samsta_dat) AS samsta_datum
                    FROM (
                        SELECT
                            DATE(datum) AS basta_dat,
                            DATE(datum) AS samsta_dat,
                            COUNT(*) AS dag_tot
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :monday AND :friday
                        GROUP BY DATE(datum)
                    ) t
                ");
                $stmt->execute([':monday' => $mondayStr, ':friday' => $fridayStr]);
                $rad = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log('KapacitetsplaneringController::getVeckoOversikt: ' . $e->getMessage());
                $rad = null;
            }

            if (!$rad || (int)$rad['total_ibc'] === 0) {
                $result[] = [
                    'vecka'           => $weekNum,
                    'ar'              => $yearNum,
                    'from_datum'      => $mondayStr,
                    'to_datum'        => $fridayStr,
                    'total_ibc'       => 0,
                    'max_kapacitet'   => 5 * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK), // 5 dagar
                    'utnyttjande_pct' => 0.0,
                    'trend'           => 'neutral',
                    'basta_dag'       => null,
                    'samsta_dag'      => null,
                ];
                continue;
            }

            $totalIbc    = (int)$rad['total_ibc'];
            $maxKapacitet = 5 * (self::PLANERAD_DRIFTTID_SEK / self::OPTIMAL_CYKELTID_SEK);
            $utnyttjande  = $maxKapacitet > 0 ? min(100, round($totalIbc / $maxKapacitet * 100, 1)) : 0.0;

            $result[] = [
                'vecka'           => $weekNum,
                'ar'              => $yearNum,
                'from_datum'      => $mondayStr,
                'to_datum'        => $fridayStr,
                'total_ibc'       => $totalIbc,
                'max_kapacitet'   => (int)$maxKapacitet,
                'utnyttjande_pct' => $utnyttjande,
                'trend'           => $utnyttjande >= 80 ? 'upp' : ($utnyttjande >= 60 ? 'neutral' : 'ned'),
                'basta_dag'       => $rad['basta_datum'],
                'samsta_dag'      => $rad['samsta_datum'],
                'basta_dag_antal' => (int)($rad['basta_dag_antal'] ?? 0),
                'samsta_dag_antal'=> (int)($rad['samsta_dag_antal'] ?? 0),
            ];
        }

        // Berakna trend for varje vecka (jamfor med foregaende)
        for ($i = 1; $i < count($result); $i++) {
            $foreg = $result[$i - 1]['total_ibc'];
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
}
