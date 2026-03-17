<?php
/**
 * ProduktionsDashboardController.php
 * Produktions-dashboard ("Command Center") — samlad overblick av HELA produktionen pa EN skarm.
 *
 * Endpoints via ?action=produktionsdashboard&run=XXX:
 *   run=oversikt          — alla KPI:er: dagens prod, OEE, kassation, drifttid, aktiva stationer, skiftinfo
 *   run=vecko-produktion  — daglig produktion senaste 7 dagar + ev. mal fran rebotling_produktionsmal
 *   run=vecko-oee         — daglig OEE med T/P/K-delkomponenter senaste 7 dagar
 *   run=stationer-status  — alla stationer: status (senaste on/off), dagens OEE, prod, senaste IBC
 *   run=senaste-alarm     — senaste 5 alarm/stopp fran rebotling_onoff
 *   run=senaste-ibc       — senaste 10 producerade IBC fran rebotling_ibc
 *
 * OEE-berakning:
 *   T (Tillganglighet) = drifttid / planerad(24h om inget annat)
 *   P (Prestanda)      = (IBC * 120s) / drifttid, max 100%
 *   K (Kvalitet)       = godkanda / totalt
 *   OEE                = T * P * K
 *
 * Skiftdefinitioner: Dag 06-14, Kvall 14-22, Natt 22-06
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_produktionsmal (om den finns)
 */
class ProduktionsDashboardController {
    private $pdo;

    private const IDEAL_CYCLE_SEC   = 120;      // sekunder per IBC (ideal cykeltid)
    private const PLANERAD_DAG_SEK  = 24 * 3600; // 24h planeringshorisont

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
            case 'oversikt':          $this->getOversikt();         break;
            case 'vecko-produktion':  $this->getVeckoProduktion();  break;
            case 'vecko-oee':         $this->getVeckoOee();         break;
            case 'stationer-status':  $this->getStationerStatus();  break;
            case 'senaste-alarm':     $this->getSenasteAlarm();     break;
            case 'senaste-ibc':       $this->getSenasteIbc();       break;
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
     * Beraknar drifttid i sekunder fran rebotling_onoff for ett datumintervall.
     * rebotling_onoff har datum + running (boolean), inte start_time/stop_time.
     */
    private function getDrifttidSek(string $fromDt, string $toDt): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum BETWEEN :from_dt AND :to_dt ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $fromDt, ':to_dt' => $toDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $sek = 0; $lastOn = null;
            foreach ($rows as $r) {
                $ts = strtotime($r['datum']);
                if ((int)$r['running'] === 1) { if ($lastOn === null) $lastOn = $ts; }
                else { if ($lastOn !== null) { $sek += max(0, $ts - $lastOn); $lastOn = null; } }
            }
            if ($lastOn !== null) $sek += max(0, min(time(), strtotime($toDt)) - $lastOn);
            return $sek;
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboardController::getDrifttidSek: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Tar reda pa aktuellt skift och kvarvarande tid.
     * Returnerar ['namn', 'start', 'slut', 'kvarvarande_min', 'start_dt', 'slut_dt']
     */
    private function getAktuelltSkift(): array {
        $now     = new \DateTime();
        $nowTime = $now->format('H:i');

        // Dag 06-14
        if ($nowTime >= '06:00' && $nowTime < '14:00') {
            $start = clone $now;
            $start->setTime(6, 0, 0);
            $slut  = clone $now;
            $slut->setTime(14, 0, 0);
            $namn = 'Dag';
        // Kvall 14-22
        } elseif ($nowTime >= '14:00' && $nowTime < '22:00') {
            $start = clone $now;
            $start->setTime(14, 0, 0);
            $slut  = clone $now;
            $slut->setTime(22, 0, 0);
            $namn = 'Kvall';
        // Natt 22-06 — kan spanna over midnatt
        } else {
            $namn = 'Natt';
            if ($nowTime >= '22:00') {
                $start = clone $now;
                $start->setTime(22, 0, 0);
                $slut  = clone $now;
                $slut->modify('+1 day');
                $slut->setTime(6, 0, 0);
            } else {
                // 00:00-05:59 — nattskiftet startade igardags
                $start = clone $now;
                $start->modify('-1 day');
                $start->setTime(22, 0, 0);
                $slut  = clone $now;
                $slut->setTime(6, 0, 0);
            }
        }

        $kvarvarandeSek = max(0, $slut->getTimestamp() - $now->getTimestamp());
        $kvarvarandeMin = (int)round($kvarvarandeSek / 60);

        return [
            'namn'             => $namn,
            'start'            => $start->format('H:i'),
            'slut'             => $slut->format('H:i'),
            'kvarvarande_min'  => $kvarvarandeMin,
            'start_dt'         => $start->format('Y-m-d H:i:s'),
            'slut_dt'          => $slut->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Hamtar dagligt produktionsmal om tabellen rebotling_produktionsmal finns.
     * Returnerar 0 om tabellen saknas eller inget mal finns.
     */
    private function getDagligtMal(string $datum): int {
        try {
            // Kontrollera om tabellen finns
            $check = $this->pdo->query("
                SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'rebotling_produktionsmal'
            ");
            if ((int)$check->fetchColumn() === 0) return 0;

            $stmt = $this->pdo->prepare("
                SELECT mal_antal FROM rebotling_produktionsmal
                WHERE typ IN ('daglig','dag','dagligt')
                  AND (start_datum IS NULL OR start_datum <= :datum)
                  AND (slut_datum  IS NULL OR slut_datum  >= :datum2)
                ORDER BY skapad_av DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([':datum' => $datum, ':datum2' => $datum]);
            $mal = $stmt->fetchColumn();
            return $mal !== false ? (int)$mal : 0;
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboardController::getDagligtMal: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Beraknar OEE-komponenter for ett datumintervall (alla stationer sammanslagna).
     */
    private function calcOeeForPeriod(string $fromDt, string $toDt): array {
        $drifttidSek = $this->getDrifttidSek($fromDt, $toDt);

        // Antal sekunder i perioden som "planerad" tid
        $periodSek = max(1, strtotime($toDt) - strtotime($fromDt));

        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ok), 0) AS ok_antal,
                       COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum BETWEEN :from AND :to
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
            $row     = $stmt->fetch(\PDO::FETCH_ASSOC);
            $okAntal = (int)($row['ok_antal']    ?? 0);
            $total   = $okAntal + (int)($row['ej_ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboardController::calcOeeForPeriod: ' . $e->getMessage());
            $total   = 0;
            $okAntal = 0;
        }

        $tillganglighet = $periodSek > 0 ? min(1.0, $drifttidSek / $periodSek) : 0.0;
        $prestanda = $drifttidSek > 0
            ? min(1.0, ($total * self::IDEAL_CYCLE_SEC) / $drifttidSek)
            : 0.0;
        $kvalitet = $total > 0 ? ($okAntal / $total) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'oee_pct'            => round($oee * 100, 1),
            'tillganglighet_pct' => round($tillganglighet * 100, 1),
            'prestanda_pct'      => round($prestanda * 100, 1),
            'kvalitet_pct'       => round($kvalitet * 100, 1),
            'drifttid_sek'       => $drifttidSek,
            'drifttid_h'         => round($drifttidSek / 3600, 2),
            'total_ibc'          => $total,
            'ok_ibc'             => $okAntal,
            'kasserade_ibc'      => $total - $okAntal,
            'kassationsgrad_pct' => $total > 0 ? round(($total - $okAntal) / $total * 100, 1) : 0.0,
        ];
    }

    // ================================================================
    // run=oversikt — alla KPI:er i ett anrop
    // ================================================================

    private function getOversikt(): void {
        $idag    = date('Y-m-d');
        $igar    = date('Y-m-d', strtotime('-1 day'));
        $fromSek7 = date('Y-m-d', strtotime('-7 days'));

        // --- Dagens produktion ---
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ok), 0) AS ok_antal,
                       COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :idag
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $stmt->execute([':idag' => $idag]);
            $radIdag   = $stmt->fetch(\PDO::FETCH_ASSOC);
            $ibcOkIdag = (int)($radIdag['ok_antal'] ?? 0);
            $ibcIdag   = $ibcOkIdag + (int)($radIdag['ej_ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::oversikt idag: ' . $e->getMessage());
            $ibcIdag = 0; $ibcOkIdag = 0;
        }

        // --- Gardag produktion (samma skift-aggregering som idag for korrekt jamforelse) ---
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ok), 0) AS ok_antal,
                       COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :igar
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $stmt->execute([':igar' => $igar]);
            $radIgar = $stmt->fetch(\PDO::FETCH_ASSOC);
            $ibcIgar = (int)($radIgar['ok_antal'] ?? 0) + (int)($radIgar['ej_ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::oversikt igar: ' . $e->getMessage());
            $ibcIgar = 0;
        }

        // Trend idag vs igar
        $prodTrendPct = 0.0;
        $prodTrendRiktning = 'neutral';
        if ($ibcIgar > 0) {
            $prodTrendPct = round(($ibcIdag - $ibcIgar) / $ibcIgar * 100, 1);
            $prodTrendRiktning = $prodTrendPct > 0 ? 'upp' : ($prodTrendPct < 0 ? 'ned' : 'neutral');
        }

        // --- Drifttid idag ---
        $fromDtIdag = $idag . ' 00:00:00';
        $toDtIdag   = $idag . ' 23:59:59';
        $drifttidSekIdag = $this->getDrifttidSek($fromDtIdag, $toDtIdag);
        $planeradSekIdag = self::PLANERAD_DAG_SEK;
        $drifttidPctIdag = round(min(100, $drifttidSekIdag / $planeradSekIdag * 100), 1);

        // --- OEE idag ---
        $oeeIdag = $this->calcOeeForPeriod($fromDtIdag, $toDtIdag);

        // --- OEE forra veckan (7 dagar sedan) ---
        $fromDt7 = $fromSek7 . ' 00:00:00';
        $toDt7   = $igar     . ' 23:59:59';
        $oee7 = $this->calcOeeForPeriod($fromDt7, $toDt7);

        // OEE-trend vs forra veckan
        $oeeTrendPct = round($oeeIdag['oee_pct'] - $oee7['oee_pct'], 1);
        $oeeTrendRiktning = $oeeTrendPct > 0 ? 'upp' : ($oeeTrendPct < 0 ? 'ned' : 'neutral');

        // --- Kassationsgrad farg ---
        $kassGrad = $oeeIdag['kassationsgrad_pct'];
        $kassGradFarg = 'green';
        if ($kassGrad > 5.0)      $kassGradFarg = 'red';
        elseif ($kassGrad > 2.0)  $kassGradFarg = 'yellow';

        // --- Aktiva stationer just nu ---
        try {
            $stmtAktiva = $this->pdo->prepare("
                SELECT COUNT(DISTINCT station) AS aktiva
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                  AND station IS NOT NULL AND station != ''
            ");
            $stmtAktiva->execute();
            $aktivaStationer = (int)($stmtAktiva->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::oversikt aktiva: ' . $e->getMessage());
            $aktivaStationer = 0;
        }

        // --- Totalt antal stationer ---
        try {
            $stmtTot = $this->pdo->query("
                SELECT COUNT(DISTINCT station) AS tot
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                  AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $totalStationer = (int)($stmtTot->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::oversikt totalStationer: ' . $e->getMessage());
            $totalStationer = 0;
        }

        // --- Skiftinfo ---
        $skift = $this->getAktuelltSkift();

        // --- Dagligt mal ---
        $dagligtMal = $this->getDagligtMal($idag);

        $this->sendSuccess([
            // Produktion
            'ibc_idag'              => $ibcIdag,
            'ibc_ok_idag'           => $ibcOkIdag,
            'ibc_igar'              => $ibcIgar,
            'prod_trend_pct'        => $prodTrendPct,
            'prod_trend_riktning'   => $prodTrendRiktning,
            'dagligt_mal'           => $dagligtMal,
            'mal_uppfyllnad_pct'    => $dagligtMal > 0 ? round($ibcIdag / $dagligtMal * 100, 1) : null,

            // OEE
            'oee_pct'               => $oeeIdag['oee_pct'],
            'tillganglighet_pct'    => $oeeIdag['tillganglighet_pct'],
            'prestanda_pct'         => $oeeIdag['prestanda_pct'],
            'kvalitet_pct'          => $oeeIdag['kvalitet_pct'],
            'oee_trend_pct'         => $oeeTrendPct,
            'oee_trend_riktning'    => $oeeTrendRiktning,
            'oee_forrad_vecka_pct'  => $oee7['oee_pct'],

            // Kassation
            'kassationsgrad_pct'    => $kassGrad,
            'kassationsgrad_farg'   => $kassGradFarg,
            'kasserade_ibc'         => $oeeIdag['kasserade_ibc'],

            // Drifttid
            'drifttid_h'            => round($drifttidSekIdag / 3600, 2),
            'drifttid_pct'          => $drifttidPctIdag,
            'planerad_h'            => round($planeradSekIdag / 3600, 1),

            // Stationer
            'aktiva_stationer'      => $aktivaStationer,
            'totalt_stationer'      => $totalStationer,

            // Skift
            'skift_namn'            => $skift['namn'],
            'skift_start'           => $skift['start'],
            'skift_slut'            => $skift['slut'],
            'skift_kvarvarande_min' => $skift['kvarvarande_min'],

            'datum'                 => $idag,
        ]);
    }

    // ================================================================
    // run=vecko-produktion — daglig produktion senaste 7 dagar + ev. mal
    // ================================================================

    private function getVeckoProduktion(): void {
        $result = [];

        for ($i = 6; $i >= 0; $i--) {
            $dagStr = date('Y-m-d', strtotime("-{$i} days"));

            try {
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(shift_ok), 0) AS ok_antal,
                           COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
                    FROM (
                        SELECT skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) = :dag
                          AND skiftraknare IS NOT NULL
                        GROUP BY skiftraknare
                    ) sub
                ");
                $stmt->execute([':dag' => $dagStr]);
                $radDag = $stmt->fetch(\PDO::FETCH_ASSOC);
                $total = (int)($radDag['ok_antal'] ?? 0) + (int)($radDag['ej_ok_antal'] ?? 0);
            } catch (\PDOException $e) {
                error_log('ProduktionsDashboard::vecko-produktion: ' . $e->getMessage());
                $total = 0;
            }

            $mal = $this->getDagligtMal($dagStr);

            $result[] = [
                'datum'    => $dagStr,
                'total'    => $total,
                'mal'      => $mal,
                'veckodag' => $this->veckodagNamn((int)date('N', strtotime($dagStr))),
            ];
        }

        $this->sendSuccess([
            'dagar'  => $result,
            'period' => 7,
        ]);
    }

    private function veckodagNamn(int $n): string {
        $namn = ['', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor', 'Son'];
        return $namn[$n] ?? '';
    }

    // ================================================================
    // run=vecko-oee — daglig OEE med T/P/K senaste 7 dagar
    // ================================================================

    private function getVeckoOee(): void {
        $result = [];

        for ($i = 6; $i >= 0; $i--) {
            $dagStr = date('Y-m-d', strtotime("-{$i} days"));
            $fromDt = $dagStr . ' 00:00:00';
            $toDt   = $dagStr . ' 23:59:59';

            $oee = $this->calcOeeForPeriod($fromDt, $toDt);

            $result[] = [
                'datum'              => $dagStr,
                'oee_pct'            => $oee['oee_pct'],
                'tillganglighet_pct' => $oee['tillganglighet_pct'],
                'prestanda_pct'      => $oee['prestanda_pct'],
                'kvalitet_pct'       => $oee['kvalitet_pct'],
                'total_ibc'          => $oee['total_ibc'],
                'veckodag'           => $this->veckodagNamn((int)date('N', strtotime($dagStr))),
            ];
        }

        $this->sendSuccess([
            'dagar'  => $result,
            'period' => 7,
        ]);
    }

    // ================================================================
    // run=stationer-status — alla stationer med status, OEE, prod, senaste IBC
    // ================================================================

    private function getStationerStatus(): void {
        $idag = date('Y-m-d');

        // Hamta alla aktiva stationer (senaste 30 dagarna)
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT station
                FROM rebotling_ibc
                WHERE station IS NOT NULL AND station != ''
                  AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY station
            ");
            $stationer = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::stationer-status stationer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationer');
            return;
        }

        // Maskinens totala drifttid for idag (ej per station)
        $drifttidSekIdag = $this->getDrifttidSek($idag . ' 00:00:00', $idag . ' 23:59:59');
        $periodSek = self::PLANERAD_DAG_SEK;

        $result = [];
        foreach ($stationer as $station) {
            // Dagens produktion for stationen
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(shift_ok), 0) AS ok_antal,
                        COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal,
                        MAX(senaste) AS senaste_datum
                    FROM (
                        SELECT skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok,
                               MAX(datum) AS senaste
                        FROM rebotling_ibc
                        WHERE station = :station
                          AND DATE(datum) = :idag
                          AND skiftraknare IS NOT NULL
                        GROUP BY skiftraknare
                    ) sub
                ");
                $stmt->execute([':station' => $station, ':idag' => $idag]);
                $rad = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log('ProduktionsDashboard::stationer-status idag: ' . $e->getMessage());
                $rad = ['total' => 0, 'ok_antal' => 0, 'senaste_datum' => null];
            }

            $okAntal     = (int)($rad['ok_antal']      ?? 0);
            $total       = $okAntal + (int)($rad['ej_ok_antal'] ?? 0);
            $senasteDatum = $rad['senaste_datum']       ?? null;

            // OEE for stationen idag (anvander maskinens drifttid)
            $tillganglighet = $periodSek > 0 ? min(1.0, $drifttidSekIdag / $periodSek) : 0.0;
            $prestanda = $drifttidSekIdag > 0
                ? min(1.0, ($total * self::IDEAL_CYCLE_SEC) / $drifttidSekIdag)
                : 0.0;
            $kvalitet   = $total > 0 ? ($okAntal / $total) : 0.0;
            $oee        = $tillganglighet * $prestanda * $kvalitet;

            // Status: produerat nagot senaste 30 min = 'kor', annars 'stopp'
            $statusKor = false;
            if ($senasteDatum) {
                $senasteTid = strtotime($senasteDatum);
                $statusKor  = ($senasteTid !== false) && (time() - $senasteTid) < 1800;
            }

            $result[] = [
                'station'            => $station,
                'status'             => $statusKor ? 'kor' : 'stopp',
                'ibc_idag'           => $total,
                'ok_idag'            => $okAntal,
                'oee_pct'            => round($oee * 100, 1),
                'tillganglighet_pct' => round($tillganglighet * 100, 1),
                'prestanda_pct'      => round($prestanda * 100, 1),
                'kvalitet_pct'       => round($kvalitet * 100, 1),
                'senaste_ibc_tid'    => $senasteDatum,
            ];
        }

        $this->sendSuccess([
            'stationer' => $result,
            'antal'     => count($result),
            'datum'     => $idag,
        ]);
    }

    // ================================================================
    // run=senaste-alarm — senaste 5 stopp/alarm
    // ================================================================

    private function getSenasteAlarm(): void {
        try {
            // rebotling_onoff har datum + running, hitta stopp-handelser (running=0)
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    datum,
                    running
                FROM rebotling_onoff
                WHERE running = 0
                ORDER BY datum DESC
                LIMIT 5
            ");
            $stmt->execute();
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $alarm = [];
            foreach ($rader as $rad) {
                $alarm[] = [
                    'id'              => (int)$rad['id'],
                    'start_time'      => $rad['datum'],
                    'stop_time'       => $rad['datum'],
                    'varaktighet_sek' => 0,
                    'varaktighet_min' => 0,
                    'status'          => 'Avslutat',
                    'typ'             => 'stopp',
                ];
            }

            $this->sendSuccess([
                'alarm' => $alarm,
                'antal' => count($alarm),
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::senaste-alarm: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta alarm');
        }
    }

    // ================================================================
    // run=senaste-ibc — senaste 10 producerade IBC
    // ================================================================

    private function getSenasteIbc(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    id,
                    datum,
                    station,
                    COALESCE(ibc_ok, 0) AS ibc_ok,
                    COALESCE(ibc_ej_ok, 0) AS ibc_ej_ok,
                    lopnummer
                FROM rebotling_ibc
                ORDER BY datum DESC, id DESC
                LIMIT 10
            ");
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $ibc = [];
            foreach ($rader as $rad) {
                $ibc[] = [
                    'id'          => (int)$rad['id'],
                    'datum'       => $rad['datum'],
                    'station'     => $rad['station'] ?? null,
                    'ok'          => (int)$rad['ibc_ok'],
                    'status_text' => ((int)$rad['ibc_ej_ok'] > 0) ? 'Kasserad' : 'OK',
                ];
            }

            $this->sendSuccess([
                'ibc'   => $ibc,
                'antal' => count($ibc),
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsDashboard::senaste-ibc: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta senaste IBC');
        }
    }
}
