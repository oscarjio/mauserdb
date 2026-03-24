<?php
/**
 * OeeJamforelseController.php
 * OEE-jamforelse per vecka — trendanalys for VD:n.
 *
 * Endpoints via ?action=oee-jamforelse&run=XXX:
 *   - run=weekly-oee   -> OEE per vecka senaste N veckor (?veckor=12)
 *                         Returnerar: aktuell vecka, forra veckan, forandring, plus lista med alla veckor
 *
 * OEE = Tillganglighet x Prestanda x Kvalitet
 *   Tillganglighet = drifttid / planerad tid (8h/dag, fran rebotling_onoff)
 *   Prestanda      = (totalIbc * IDEAL_CYCLE_SEC) / drifttid
 *   Kvalitet       = godkanda / totalt (ok=1 i rebotling_ibc)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff
 */
class OeeJamforelseController {
    private $pdo;

    private const IDEAL_CYCLE_SEC = 120;   // sekunder per IBC (ideal)
    private const SCHEMA_SEK_PER_DAG = 8 * 3600;  // 8 timmars skift
    private const OEE_MAL = 85.0; // mal-OEE i procent

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
            case 'weekly-oee': $this->getWeeklyOee(); break;
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

    /**
     * Beräkna drifttid i sekunder från rebotling_onoff (datum + running kolumner).
     */
    private function calcDrifttidSek(string $from, string $to): int {
        $stmt = $this->pdo->prepare("
            SELECT datum, running FROM rebotling_onoff
            WHERE datum >= :from_dt AND datum < :to_dt ORDER BY datum ASC
        ");
        $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $sek = 0; $lastOn = null;
        foreach ($rows as $r) {
            $ts = strtotime($r['datum']);
            if ((int)$r['running'] === 1) { if ($lastOn === null) $lastOn = $ts; }
            else { if ($lastOn !== null) { $sek += max(0, $ts - $lastOn); $lastOn = null; } }
        }
        if ($lastOn !== null) $sek += max(0, min(time(), strtotime($to)) - $lastOn);
        return $sek;
    }

    /**
     * Berakna OEE for ett datumintervall (from_date - to_date inklusive).
     * Returnerar oee_pct, tillganglighet_pct, prestanda_pct, kvalitet_pct samt ravardata.
     */
    private function calcOeeForRange(string $fromDate, string $toDate): array {
        $fromDt = $fromDate . ' 00:00:00';
        $toDt   = date('Y-m-d', strtotime($toDate . ' +1 day')) . ' 00:00:00';

        // 1) Drifttid fran rebotling_onoff (datum + running kolumner)
        try {
            $drifttidSek = $this->calcDrifttidSek($fromDt, $toDt);
        } catch (\PDOException $e) {
            error_log('OeeJamforelse::calcOeeForRange onoff: ' . $e->getMessage());
            $drifttidSek = 0;
        }

        // Planerad tid: antal vardagar * 8h
        $d = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        $arbetsdagar = 0;
        while ($d <= $end) {
            $dow = (int)$d->format('N'); // 1=man, 7=son
            if ($dow <= 5) $arbetsdagar++;
            $d->modify('+1 day');
        }
        $planeradSek = $arbetsdagar * self::SCHEMA_SEK_PER_DAG;
        $stopptidSek = max(0, $planeradSek - $drifttidSek);

        // 2) IBC-data via kumulativa PLC-fält
        try {
            $ibcStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ok), 0) AS ok_antal,
                       COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $ibcStmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $ibcRow   = $ibcStmt->fetch(\PDO::FETCH_ASSOC);
            $okIbc    = (int)($ibcRow['ok_antal']    ?? 0);
            $totalIbc = $okIbc + (int)($ibcRow['ej_ok_antal'] ?? 0);
        } catch (\PDOException $e) {
            error_log('OeeJamforelse::calcOeeForRange ibc: ' . $e->getMessage());
            $totalIbc = 0;
            $okIbc    = 0;
        }

        // 3) Berakna OEE-faktorer
        $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0.0;
        $prestanda = $drifttidSek > 0
            ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek)
            : 0.0;
        $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'oee_pct'              => round($oee * 100, 1),
            'tillganglighet_pct'   => round($tillganglighet * 100, 1),
            'prestanda_pct'        => round($prestanda * 100, 1),
            'kvalitet_pct'         => round($kvalitet * 100, 1),
            'drifttid_h'           => round($drifttidSek / 3600, 1),
            'stopptid_h'           => round($stopptidSek / 3600, 1),
            'planerad_h'           => round($planeradSek / 3600, 1),
            'total_ibc'            => $totalIbc,
            'ok_ibc'               => $okIbc,
            'kasserade_ibc'        => $totalIbc - $okIbc,
            'arbetsdagar'          => $arbetsdagar,
        ];
    }

    // ================================================================
    // run=weekly-oee — OEE per vecka
    // ================================================================

    private function getWeeklyOee(): void {
        $veckor = max(4, min(52, intval($_GET['veckor'] ?? 12)));

        // Berakna veckointervall (ISO-veckor)
        $now = new \DateTime();
        $weeks = [];
        for ($i = 0; $i < $veckor; $i++) {
            // Ga baklanges $i veckor fran nu
            $dt = clone $now;
            $dt->modify('-' . $i . ' weeks');
            $isoYear = (int)$dt->format('o');
            $isoWeek = (int)$dt->format('W');

            // Forsta och sista dag i ISO-veckan
            $monday = new \DateTime();
            $monday->setISODate($isoYear, $isoWeek, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');

            $fromDate = $monday->format('Y-m-d');
            $toDate   = $sunday->format('Y-m-d');

            $oeeData = $this->calcOeeForRange($fromDate, $toDate);

            $weeks[] = [
                'vecka'                => $isoWeek,
                'ar'                   => $isoYear,
                'vecko_label'          => 'V' . $isoWeek,
                'from_date'            => $fromDate,
                'to_date'              => $toDate,
                'oee_pct'              => $oeeData['oee_pct'],
                'tillganglighet_pct'   => $oeeData['tillganglighet_pct'],
                'prestanda_pct'        => $oeeData['prestanda_pct'],
                'kvalitet_pct'         => $oeeData['kvalitet_pct'],
                'drifttid_h'           => $oeeData['drifttid_h'],
                'stopptid_h'           => $oeeData['stopptid_h'],
                'planerad_h'           => $oeeData['planerad_h'],
                'total_ibc'            => $oeeData['total_ibc'],
                'ok_ibc'               => $oeeData['ok_ibc'],
                'kasserade_ibc'        => $oeeData['kasserade_ibc'],
                'arbetsdagar'          => $oeeData['arbetsdagar'],
            ];
        }

        // Reversa sa att aeldsta veckan kommer forst (for diagram)
        $weeks = array_reverse($weeks);

        // Berakna forandring vs foersta veckan
        for ($i = 0; $i < count($weeks); $i++) {
            if ($i === 0) {
                $weeks[$i]['forandring']     = null;
                $weeks[$i]['forandring_pil'] = 'flat';
            } else {
                $diff = round($weeks[$i]['oee_pct'] - $weeks[$i - 1]['oee_pct'], 1);
                $weeks[$i]['forandring'] = $diff;
                if ($diff > 0.5) {
                    $weeks[$i]['forandring_pil'] = 'up';   // forbattring
                } elseif ($diff < -0.5) {
                    $weeks[$i]['forandring_pil'] = 'down';  // forsamring
                } else {
                    $weeks[$i]['forandring_pil'] = 'flat';
                }
            }
        }

        // Aktuell vecka och foersta vecka KPI
        $count = count($weeks);
        $aktuellVecka = $count > 0 ? $weeks[$count - 1] : null;
        $forraVecka   = $count > 1 ? $weeks[$count - 2] : null;

        $oeeFoerst     = $aktuellVecka ? $aktuellVecka['oee_pct'] : 0;
        $oeeForegaende = $forraVecka   ? $forraVecka['oee_pct']  : 0;
        $diff = round($oeeFoerst - $oeeForegaende, 1);

        if ($diff > 0.5) {
            $trendPil = 'up';
        } elseif ($diff < -0.5) {
            $trendPil = 'down';
        } else {
            $trendPil = 'flat';
        }

        $this->sendSuccess([
            'veckor'            => $veckor,
            'mal_oee'           => self::OEE_MAL,
            'aktuell_vecka'     => $aktuellVecka,
            'forra_vecka'       => $forraVecka,
            'forandring'        => $diff,
            'forandring_pil'    => $trendPil,
            'veckodata'         => $weeks,
        ]);
    }
}
