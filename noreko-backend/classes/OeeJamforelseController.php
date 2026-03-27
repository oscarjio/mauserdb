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
     * Fördela drifttidssekunder mellan ISO-veckor.
     */
    private function addDrifttidToWeeks(array &$perWeek, int $fromTs, int $toTs): void {
        $sek = max(0, $toTs - $fromTs);
        if ($sek <= 0) return;
        // Enkel fördelning: tilldela alla sekunder till starttidens vecka
        $yw = date('oW', $fromTs);
        if (!isset($perWeek[$yw])) $perWeek[$yw] = 0;
        $perWeek[$yw] += $sek;
    }

    // ================================================================
    // run=weekly-oee — OEE per vecka
    // ================================================================

    private function getWeeklyOee(): void {
        $veckor = max(4, min(52, intval($_GET['veckor'] ?? 12)));

        // Berakna veckointervall (ISO-veckor)
        $now = new \DateTime();

        // Beräkna fullständigt datumintervall för alla veckor
        $dtOldest = clone $now;
        $dtOldest->modify('-' . ($veckor - 1) . ' weeks');
        $oldestYear = (int)$dtOldest->format('o');
        $oldestWeek = (int)$dtOldest->format('W');
        $globalFrom = new \DateTime();
        $globalFrom->setISODate($oldestYear, $oldestWeek, 1);
        $globalFromStr = $globalFrom->format('Y-m-d');

        $newestYear = (int)$now->format('o');
        $newestWeek = (int)$now->format('W');
        $globalTo = new \DateTime();
        $globalTo->setISODate($newestYear, $newestWeek, 7);
        $globalToStr = $globalTo->format('Y-m-d');

        // Batch-hämta IBC-data per ISO-vecka i EN query
        $ibcPerWeek = [];
        try {
            $ibcStmt = $this->pdo->prepare("
                SELECT
                    YEARWEEK(DATE(datum), 3) AS yw,
                    COALESCE(SUM(shift_ok), 0) AS ok_antal,
                    COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
                FROM (
                    SELECT skiftraknare, datum,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)

                    GROUP BY skiftraknare
                ) sub
                GROUP BY yw
            ");
            $ibcStmt->execute([':from_date' => $globalFromStr, ':to_date' => $globalToStr]);
            foreach ($ibcStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ibcPerWeek[$row['yw']] = $row;
            }
        } catch (\PDOException $e) {
            error_log('OeeJamforelse::getWeeklyOee ibc-batch: ' . $e->getMessage());
        }

        // Batch-hämta drifttid från rebotling_onoff för hela perioden
        $drifttidPerWeek = [];
        try {
            $globalFromDt = $globalFromStr . ' 00:00:00';
            $globalToDt   = date('Y-m-d', strtotime($globalToStr . ' +1 day')) . ' 00:00:00';
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum < :to_dt ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $globalFromDt, ':to_dt' => $globalToDt]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $lastOn = null;
            foreach ($rows as $r) {
                $ts = strtotime($r['datum']);
                if ((int)$r['running'] === 1) {
                    if ($lastOn === null) $lastOn = $ts;
                } else {
                    if ($lastOn !== null) {
                        // Fördela sekunder per vecka
                        $this->addDrifttidToWeeks($drifttidPerWeek, $lastOn, $ts);
                        $lastOn = null;
                    }
                }
            }
            if ($lastOn !== null) {
                $endTs = min(time(), strtotime($globalToDt));
                $this->addDrifttidToWeeks($drifttidPerWeek, $lastOn, $endTs);
            }
        } catch (\PDOException $e) {
            error_log('OeeJamforelse::getWeeklyOee onoff-batch: ' . $e->getMessage());
        }

        $weeks = [];
        for ($i = 0; $i < $veckor; $i++) {
            $dt = clone $now;
            $dt->modify('-' . $i . ' weeks');
            $isoYear = (int)$dt->format('o');
            $isoWeek = (int)$dt->format('W');

            $monday = new \DateTime();
            $monday->setISODate($isoYear, $isoWeek, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');

            $fromDate = $monday->format('Y-m-d');
            $toDate   = $sunday->format('Y-m-d');

            // Arbetsdagar
            $d = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            $arbetsdagar = 0;
            while ($d <= $end) {
                if ((int)$d->format('N') <= 5) $arbetsdagar++;
                $d->modify('+1 day');
            }
            $planeradSek = $arbetsdagar * self::SCHEMA_SEK_PER_DAG;

            // IBC-data från batch
            $yw = $isoYear . str_pad($isoWeek, 2, '0', STR_PAD_LEFT);
            $ibcRow = $ibcPerWeek[$yw] ?? null;
            $okIbc    = $ibcRow ? (int)$ibcRow['ok_antal'] : 0;
            $totalIbc = $okIbc + ($ibcRow ? (int)$ibcRow['ej_ok_antal'] : 0);

            // Drifttid från batch
            $drifttidSek = $drifttidPerWeek[$yw] ?? 0;
            $stopptidSek = max(0, $planeradSek - $drifttidSek);

            // OEE
            $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0.0;
            $prestanda = $drifttidSek > 0
                ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek)
                : 0.0;
            $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
            $oee = $tillganglighet * $prestanda * $kvalitet;

            $weeks[] = [
                'vecka'                => $isoWeek,
                'ar'                   => $isoYear,
                'vecko_label'          => 'V' . $isoWeek,
                'from_date'            => $fromDate,
                'to_date'              => $toDate,
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
