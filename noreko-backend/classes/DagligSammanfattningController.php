<?php
/**
 * DagligSammanfattningController.php
 * Daglig KPI-sammanfattning för VD — allt på en sida utan navigering.
 *
 * Endpoints via ?action=daglig-sammanfattning&run=XXX:
 *   run=daily-summary&date=YYYY-MM-DD
 *       Hämtar ALL data för vald dag i ett anrop:
 *       - Produktionsdata (IBC OK, Ej OK, kvalitet %, IBC/h)
 *       - OEE-snapshot (ett tal + 3 faktorer)
 *       - Top 3 operatörer (namn, antal IBC, snitt cykeltid)
 *       - Stopptid (total + topp 3 orsaker)
 *       - Skiftdata (pågående/senaste skift KPI)
 *       - Statusmeddelande (auto-genererad text)
 *
 *   run=comparison&date=YYYY-MM-DD
 *       Jämförelsedata mot igår och veckosnittet
 *
 * Auth: session_id krävs.
 * Tabeller: rebotling_ibc, rebotling_onoff, stopporsak_registreringar, stopporsak_kategorier, operators
 */
class DagligSammanfattningController {
    private $pdo;

    // Ideal cykeltid (sekunder) — branschriktmärke
    private const IDEAL_CYCLE_SEC   = 120;
    // Planerad arbetstid per skift (8 timmar i minuter)
    private const SKIFT_MIN         = 480;
    // IBC/h mål
    private const MAL_IBC_PER_TIMME = 30.0;
    // OEE-gränser
    private const OEE_WORLD_CLASS   = 0.85;
    private const OEE_TYPICAL       = 0.60;
    private const OEE_LOW           = 0.40;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
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
            case 'daily-summary': $this->getDailySummary(); break;
            case 'comparison':    $this->getComparison();   break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
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

    private function validateDate(string $date): bool {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function getDate(): string {
        $date = trim($_GET['date'] ?? '');
        if (empty($date) || !$this->validateDate($date)) {
            return date('Y-m-d');
        }
        return $date;
    }

    /**
     * Hämta produktionsdata för ett datum.
     * Returnerar: total_ibc, ibc_ok, ibc_ej_ok, kvalitet_pct, ibc_per_timme,
     *             total_runtime_min, skift_start, skift_slut, antal_skiften
     */
    private function getProduktionsdata(string $date): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                skiftraknare,
                MAX(ibc_ok)      AS ibc_ok,
                MAX(ibc_ej_ok)   AS ibc_ej_ok,
                MAX(runtime_plc) AS runtime_plc,
                MIN(time_of_day) AS skift_start,
                MAX(time_of_day) AS skift_slut
             FROM rebotling_ibc
             WHERE DATE(datum) = ?
             GROUP BY skiftraknare
             HAVING COUNT(*) > 1
             ORDER BY skiftraknare ASC"
        );
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'har_data'        => false,
                'total_ibc'       => 0,
                'ibc_ok'          => 0,
                'ibc_ej_ok'       => 0,
                'kvalitet_pct'    => 0.0,
                'ibc_per_timme'   => 0.0,
                'runtime_min'     => 0,
                'skift_start'     => null,
                'skift_slut'      => null,
                'antal_skiften'   => 0,
                'skiften'         => [],
            ];
        }

        $totIbcOk   = 0;
        $totIbcEjOk = 0;
        $totRuntime = 0;
        $skiftStart = null;
        $skiftSlut  = null;
        $skiftLista = [];

        foreach ($rows as $r) {
            $ibcOk    = (int)$r['ibc_ok'];
            $ibcEjOk  = (int)$r['ibc_ej_ok'];
            $runtime  = (int)$r['runtime_plc'];

            $totIbcOk   += $ibcOk;
            $totIbcEjOk += $ibcEjOk;
            $totRuntime += $runtime;

            if ($skiftStart === null || $r['skift_start'] < $skiftStart) {
                $skiftStart = $r['skift_start'];
            }
            if ($skiftSlut === null || $r['skift_slut'] > $skiftSlut) {
                $skiftSlut = $r['skift_slut'];
            }

            $ibcTot         = $ibcOk + $ibcEjOk;
            $skiftKvalitet  = $ibcTot > 0 ? round(($ibcOk / $ibcTot) * 100, 1) : 0.0;
            $skiftIbcPerH   = $runtime > 0 ? round($ibcOk / ($runtime / 60), 1) : 0.0;

            $skiftLista[] = [
                'skiftraknare'  => (int)$r['skiftraknare'],
                'ibc_ok'        => $ibcOk,
                'ibc_ej_ok'     => $ibcEjOk,
                'runtime_min'   => $runtime,
                'kvalitet_pct'  => $skiftKvalitet,
                'ibc_per_timme' => $skiftIbcPerH,
                'skift_start'   => $r['skift_start'],
                'skift_slut'    => $r['skift_slut'],
            ];
        }

        $totalIbc    = $totIbcOk + $totIbcEjOk;
        $kvalitetPct = $totalIbc > 0 ? round(($totIbcOk / $totalIbc) * 100, 1) : 0.0;
        $ibcPerTimme = $totRuntime > 0 ? round($totIbcOk / ($totRuntime / 60), 1) : 0.0;

        return [
            'har_data'      => true,
            'total_ibc'     => $totalIbc,
            'ibc_ok'        => $totIbcOk,
            'ibc_ej_ok'     => $totIbcEjOk,
            'kvalitet_pct'  => $kvalitetPct,
            'ibc_per_timme' => $ibcPerTimme,
            'runtime_min'   => $totRuntime,
            'skift_start'   => $skiftStart,
            'skift_slut'    => $skiftSlut,
            'antal_skiften' => count($rows),
            'skiften'       => $skiftLista,
        ];
    }

    /**
     * Beräkna drifttid i sekunder från rebotling_onoff (datum + running kolumner).
     */
    private function calcDrifttidSek(string $from, string $to): int {
        $stmt = $this->pdo->prepare("
            SELECT datum, running FROM rebotling_onoff
            WHERE datum BETWEEN :from_dt AND :to_dt ORDER BY datum ASC
        ");
        $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Beräkna OEE för ett datum (baserat på rebotling_onoff).
     * Returnerar oee, tillganglighet, prestanda, kvalitet (alla som 0–1), drifttid_sek, stopptid_sek.
     */
    private function calcOee(string $date): array {
        $fromDt = $date . ' 00:00:00';
        $toDt   = $date . ' 23:59:59';

        // Drifttid från rebotling_onoff (datum + running kolumner)
        $drifttidSek = $this->calcDrifttidSek($fromDt, $toDt);

        // Schemad tid: 8h
        $schemaSek   = 8 * 3600;
        $stopptidSek = max(0, $schemaSek - $drifttidSek);
        $totalSek    = $schemaSek;

        // IBC via kumulativa PLC-fält
        $ibcStmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(shift_ok), 0) AS ok_antal,
                    COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_antal
             FROM (
                 SELECT skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                 FROM rebotling_ibc
                 WHERE DATE(datum) = ?
                   AND skiftraknare IS NOT NULL
                 GROUP BY skiftraknare
             ) sub"
        );
        $ibcStmt->execute([$date]);
        $ibcRow   = $ibcStmt->fetch(PDO::FETCH_ASSOC);
        $okIbc    = (int)($ibcRow['ok_antal']    ?? 0);
        $ejOkIbc  = (int)($ibcRow['ej_ok_antal'] ?? 0);
        $totalIbc = $okIbc + $ejOkIbc;

        // Faktorer
        $tillganglighet = $totalSek > 0 ? ($drifttidSek / $totalSek) : 0.0;
        $prestanda = $drifttidSek > 0
            ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek)
            : 0.0;
        $kvalitet  = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
        $oee       = $tillganglighet * $prestanda * $kvalitet;

        return [
            'oee'            => round($oee,            4),
            'oee_pct'        => round($oee * 100,      1),
            'tillganglighet' => round($tillganglighet, 4),
            'tillganglighet_pct' => round($tillganglighet * 100, 1),
            'prestanda'      => round($prestanda,      4),
            'prestanda_pct'  => round($prestanda * 100, 1),
            'kvalitet'       => round($kvalitet,       4),
            'kvalitet_pct'   => round($kvalitet * 100,  1),
            'drifttid_sek'   => $drifttidSek,
            'stopptid_sek'   => $stopptidSek,
            'drifttid_h'     => round($drifttidSek / 3600, 1),
            'stopptid_h'     => round($stopptidSek / 3600, 1),
        ];
    }

    /**
     * Hämta OEE-färgklass och etikett.
     */
    private function oeeStatus(float $oee): array {
        if ($oee >= self::OEE_WORLD_CLASS) {
            return ['status' => 'world-class', 'color' => 'success', 'label' => 'Utmärkt'];
        } elseif ($oee >= self::OEE_TYPICAL) {
            return ['status' => 'bra',         'color' => 'info',    'label' => 'Bra'];
        } elseif ($oee >= self::OEE_LOW) {
            return ['status' => 'typiskt',     'color' => 'warning', 'label' => 'OK'];
        }
        return ['status' => 'lågt', 'color' => 'danger', 'label' => 'Lågt'];
    }

    /**
     * Hämta topp-3 operatörer för ett datum.
     */
    private function getTopOperatorer(string $date): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                op_num,
                COUNT(*) AS antal_ibc,
                ROUND(AVG(cycle_sek), 1) AS avg_cykeltid_sek
             FROM (
                SELECT op1 AS op_num,
                    TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                        datum
                    ) AS cycle_sek,
                    skiftraknare
                FROM rebotling_ibc
                WHERE DATE(datum) = :d1 AND op1 IS NOT NULL AND op1 > 0
                UNION ALL
                SELECT op2 AS op_num,
                    TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                        datum
                    ) AS cycle_sek,
                    skiftraknare
                FROM rebotling_ibc
                WHERE DATE(datum) = :d2 AND op2 IS NOT NULL AND op2 > 0
                UNION ALL
                SELECT op3 AS op_num,
                    TIMESTAMPDIFF(SECOND,
                        LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                        datum
                    ) AS cycle_sek,
                    skiftraknare
                FROM rebotling_ibc
                WHERE DATE(datum) = :d3 AND op3 IS NOT NULL AND op3 > 0
             ) lagd
             WHERE cycle_sek >= 30 AND cycle_sek <= 1800
             GROUP BY op_num
             ORDER BY antal_ibc DESC
             LIMIT 3"
        );
        $stmt->execute([':d1' => $date, ':d2' => $date, ':d3' => $date]);
        $opRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($opRows)) return [];

        // Hämta namn
        $opNums = array_column($opRows, 'op_num');
        $ph = implode(',', array_fill(0, count($opNums), '?'));
        $nameStmt = $this->pdo->prepare(
            "SELECT number, name FROM operators WHERE number IN ({$ph})"
        );
        $nameStmt->execute($opNums);
        $names = [];
        foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $nr) {
            $names[(int)$nr['number']] = $nr['name'];
        }

        $result = [];
        foreach ($opRows as $i => $r) {
            $num = (int)$r['op_num'];
            $result[] = [
                'plats'          => $i + 1,
                'operator_num'   => $num,
                'operator_namn'  => $names[$num] ?? "Op #{$num}",
                'antal_ibc'      => (int)$r['antal_ibc'],
                'avg_cykeltid_sek' => (float)$r['avg_cykeltid_sek'],
                'avg_cykeltid_min' => round((float)$r['avg_cykeltid_sek'] / 60, 1),
            ];
        }
        return $result;
    }

    /**
     * Hämta stopptidsdata för ett datum.
     * Returnerar total_stopp_min, antal_stopp, top3_orsaker.
     */
    private function getStopptid(string $date): array {
        // Kontrollera att tabellerna existerar
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if (!$check || $check->rowCount() === 0) {
                return [
                    'har_data'        => false,
                    'total_stopp_min' => 0,
                    'antal_stopp'     => 0,
                    'top3_orsaker'    => [],
                ];
            }
        } catch (\PDOException) {
            return [
                'har_data'        => false,
                'total_stopp_min' => 0,
                'antal_stopp'     => 0,
                'top3_orsaker'    => [],
            ];
        }

        // Totalt
        $totStmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS antal,
                ROUND(SUM(
                    TIMESTAMPDIFF(MINUTE,
                        start_time,
                        COALESCE(end_time, NOW())
                    )
                ), 0) AS total_min
             FROM stopporsak_registreringar
             WHERE linje = 'rebotling'
               AND DATE(start_time) = ?"
        );
        $totStmt->execute([$date]);
        $totRow = $totStmt->fetch(PDO::FETCH_ASSOC);

        // Top 3 orsaker
        $topStmt = $this->pdo->prepare(
            "SELECT
                k.namn AS kategori,
                k.ikon AS ikon,
                COUNT(*) AS antal,
                ROUND(SUM(TIMESTAMPDIFF(MINUTE, r.start_time, COALESCE(r.end_time, NOW()))), 0) AS total_min
             FROM stopporsak_registreringar r
             JOIN stopporsak_kategorier k ON k.id = r.kategori_id
             WHERE r.linje = 'rebotling'
               AND DATE(r.start_time) = ?
             GROUP BY k.id, k.namn, k.ikon
             ORDER BY total_min DESC
             LIMIT 3"
        );
        $topStmt->execute([$date]);
        $topRows = $topStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'har_data'        => true,
            'total_stopp_min' => (int)($totRow['total_min'] ?? 0),
            'antal_stopp'     => (int)($totRow['antal']     ?? 0),
            'top3_orsaker'    => array_map(fn($r) => [
                'kategori'  => $r['kategori'],
                'ikon'      => $r['ikon'],
                'antal'     => (int)$r['antal'],
                'total_min' => (int)$r['total_min'],
            ], $topRows),
        ];
    }

    /**
     * Beräkna trend mot förra veckan samma veckodag.
     * Returnerar trend (up/down/flat), diff_pct, ibc_idag, ibc_foreg.
     */
    private function getTrendmot(string $date, int $ibcIdag): array {
        $foregDatum = date('Y-m-d', strtotime($date . ' -7 days'));

        // Summera per skift + datum föreg
        $foStmt = $this->pdo->prepare(
            "SELECT SUM(max_ok) AS ibc_ok FROM (
                SELECT skiftraknare, MAX(ibc_ok) AS max_ok
                FROM rebotling_ibc
                WHERE DATE(datum) = ?
                GROUP BY skiftraknare
                HAVING COUNT(*) > 1
             ) skiften"
        );
        $foStmt->execute([$foregDatum]);
        $foRow  = $foStmt->fetch(PDO::FETCH_ASSOC);
        $ibcForeg = (int)($foRow['ibc_ok'] ?? 0);

        $diff    = $ibcForeg > 0 ? round((($ibcIdag - $ibcForeg) / $ibcForeg) * 100, 1) : 0.0;
        $trendDir = 'flat';
        if ($diff > 2.0)  $trendDir = 'up';
        if ($diff < -2.0) $trendDir = 'down';

        return [
            'trend'        => $trendDir,
            'diff_pct'     => $diff,
            'ibc_idag'     => $ibcIdag,
            'ibc_foreg'    => $ibcForeg,
            'foreg_datum'  => $foregDatum,
        ];
    }

    /**
     * Hämta veckosnitt (senaste 5 motsvarande veckodagar exklusive idag).
     */
    private function getVeckosnitt(string $date): array {
        $dagIndex = (int)date('N', strtotime($date)); // 1=mån … 7=sön
        $snittPoints = [];
        // Hämta senaste 5 veckorna
        for ($w = 1; $w <= 5; $w++) {
            $d = date('Y-m-d', strtotime($date . " -{$w} weeks"));
            $s = $this->pdo->prepare(
                "SELECT SUM(max_ok) AS ibc FROM (
                    SELECT MAX(ibc_ok) AS max_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) = ?
                    GROUP BY skiftraknare
                    HAVING COUNT(*) > 1
                 ) t"
            );
            $s->execute([$d]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $val = (int)($row['ibc'] ?? 0);
            if ($val > 0) $snittPoints[] = $val;
        }

        $snitt = !empty($snittPoints)
            ? round(array_sum($snittPoints) / count($snittPoints), 0)
            : 0;

        return [
            'veckosnitt_ibc' => (int)$snitt,
            'antal_dagar'    => count($snittPoints),
        ];
    }

    /**
     * Hämta KPI för senaste/pågående skift.
     */
    private function getSenasteSkift(string $date, array $skiften): array {
        if (empty($skiften)) {
            return ['har_data' => false];
        }
        // Senaste skiftet = sista i listan
        $s = end($skiften);
        return [
            'har_data'      => true,
            'skiftraknare'  => $s['skiftraknare'],
            'ibc_ok'        => $s['ibc_ok'],
            'ibc_ej_ok'     => $s['ibc_ej_ok'],
            'runtime_min'   => $s['runtime_min'],
            'kvalitet_pct'  => $s['kvalitet_pct'],
            'ibc_per_timme' => $s['ibc_per_timme'],
            'skift_start'   => $s['skift_start'],
            'skift_slut'    => $s['skift_slut'],
        ];
    }

    /**
     * Generera ett auto-statusmeddelande.
     */
    private function generateStatusText(
        array $prod,
        array $oee,
        array $trend,
        array $vecko
    ): string {
        if (!$prod['har_data']) {
            return 'Ingen produktionsdata registrerad for detta datum.';
        }

        $ibcOk       = $prod['ibc_ok'];
        $kvalitetPct = $prod['kvalitet_pct'];
        $oeePct      = $oee['oee_pct'];
        $trendDir    = $trend['trend'];
        $trendPct    = abs($trend['diff_pct']);
        $snittIbc    = $vecko['veckosnitt_ibc'];

        $delar = [];

        // OEE-kommentar
        if ($oeePct >= 85) {
            $delar[] = "Utmarkt dag! OEE {$oeePct}% — world class-niva.";
        } elseif ($oeePct >= 60) {
            $delar[] = "Bra dag. OEE {$oeePct}%.";
        } elseif ($oeePct >= 40) {
            $delar[] = "Godkand niva. OEE {$oeePct}% — utrymme for forbattring.";
        } else {
            $delar[] = "OEE {$oeePct}% — under mal. Atgard behovs.";
        }

        // Trendkommentar
        if ($trendDir === 'up') {
            $delar[] = "Produktionen ar {$trendPct}% hogre an samma dag forra veckan.";
        } elseif ($trendDir === 'down') {
            $delar[] = "Produktionen ar {$trendPct}% lagre an samma dag forra veckan.";
        } else {
            $delar[] = "Produktionen ligger i linje med samma dag forra veckan.";
        }

        // Kvalitetskommentar
        if ($kvalitetPct >= 98) {
            $delar[] = "Mycket hog kvalitet ({$kvalitetPct}%).";
        } elseif ($kvalitetPct < 90) {
            $delar[] = "Kvaliteten ({$kvalitetPct}%) behover ses over.";
        }

        // Veckosnitt
        if ($snittIbc > 0 && $ibcOk > 0) {
            $diffFranSnitt = round((($ibcOk - $snittIbc) / $snittIbc) * 100, 0);
            if ($diffFranSnitt > 5) {
                $delar[] = "Dagens IBC OK ({$ibcOk}) ar {$diffFranSnitt}% over veckosnittet ({$snittIbc}).";
            } elseif ($diffFranSnitt < -5) {
                $absD = abs($diffFranSnitt);
                $delar[] = "Dagens IBC OK ({$ibcOk}) ar {$absD}% under veckosnittet ({$snittIbc}).";
            }
        }

        return implode(' ', $delar);
    }

    // ================================================================
    // run=daily-summary
    // ================================================================

    private function getDailySummary(): void {
        $date = $this->getDate();

        try {
            // 1. Produktionsdata
            $prod = $this->getProduktionsdata($date);

            // 2. OEE
            $oee    = $this->calcOee($date);
            $oeeInfo = $this->oeeStatus($oee['oee']);

            // 3. Top 3 operatörer
            $topOp = $this->getTopOperatorer($date);

            // 4. Stopptid
            $stopp = $this->getStopptid($date);

            // 5. Trend mot förra veckan
            $trend = $this->getTrendmot($date, $prod['ibc_ok']);

            // 6. Veckosnitt
            $vecko = $this->getVeckosnitt($date);

            // 7. Senaste skift
            $senasteSkift = $this->getSenasteSkift($date, $prod['skiften'] ?? []);

            // 8. Statustext
            $statusText = $this->generateStatusText($prod, $oee, $trend, $vecko);

            // Kvalitetsfärg
            $kvalitetColor = 'success';
            if ($prod['kvalitet_pct'] < 90) $kvalitetColor = 'danger';
            elseif ($prod['kvalitet_pct'] < 97) $kvalitetColor = 'warning';

            // IBC/h färg (mot mål)
            $ibcPerHColor = 'success';
            if ($prod['ibc_per_timme'] < self::MAL_IBC_PER_TIMME * 0.7) {
                $ibcPerHColor = 'danger';
            } elseif ($prod['ibc_per_timme'] < self::MAL_IBC_PER_TIMME) {
                $ibcPerHColor = 'warning';
            }

            $this->sendSuccess([
                'datum'         => $date,
                'produktion'    => array_merge($prod, [
                    'kvalitet_color'    => $kvalitetColor,
                    'ibc_per_h_color'   => $ibcPerHColor,
                    'mal_ibc_per_timme' => self::MAL_IBC_PER_TIMME,
                ]),
                'oee'           => array_merge($oee, $oeeInfo),
                'top_operatorer' => $topOp,
                'stopptid'      => $stopp,
                'trend'         => $trend,
                'veckosnitt'    => $vecko,
                'senaste_skift' => $senasteSkift,
                'status_text'   => $statusText,
            ]);

        } catch (\Exception $e) {
            error_log('DagligSammanfattningController::getDailySummary: ' . $e->getMessage());
            $this->sendError('Kunde inte generera daglig sammanfattning', 500);
        }
    }

    // ================================================================
    // run=comparison
    // ================================================================

    private function getComparison(): void {
        $date = $this->getDate();

        try {
            $igarnDatum   = date('Y-m-d', strtotime($date . ' -1 day'));
            $foregVecka   = date('Y-m-d', strtotime($date . ' -7 days'));

            // Idag
            $idag  = $this->getProduktionsdata($date);
            // Igår
            $igar  = $this->getProduktionsdata($igarnDatum);
            // Förra veckan (samma dag)
            $foreg = $this->getProduktionsdata($foregVecka);

            // OEE för alla tre
            $oeeIdag  = $this->calcOee($date);
            $oeeIgar  = $this->calcOee($igarnDatum);
            $oeeForeg = $this->calcOee($foregVecka);

            // Diff IBC mot igår
            $diffIgarIbc  = $igar['ibc_ok'] > 0
                ? round((($idag['ibc_ok'] - $igar['ibc_ok']) / $igar['ibc_ok']) * 100, 1)
                : 0.0;
            $diffIgarOee  = round($oeeIdag['oee_pct'] - $oeeIgar['oee_pct'], 1);

            // Diff IBC mot förra veckan
            $diffForegIbc = $foreg['ibc_ok'] > 0
                ? round((($idag['ibc_ok'] - $foreg['ibc_ok']) / $foreg['ibc_ok']) * 100, 1)
                : 0.0;
            $diffForegOee = round($oeeIdag['oee_pct'] - $oeeForeg['oee_pct'], 1);

            // Veckosnitt
            $vecko = $this->getVeckosnitt($date);

            $this->sendSuccess([
                'datum'           => $date,
                'idag'            => [
                    'datum'         => $date,
                    'ibc_ok'        => $idag['ibc_ok'],
                    'ibc_ej_ok'     => $idag['ibc_ej_ok'],
                    'kvalitet_pct'  => $idag['kvalitet_pct'],
                    'ibc_per_timme' => $idag['ibc_per_timme'],
                    'oee_pct'       => $oeeIdag['oee_pct'],
                ],
                'igar'            => [
                    'datum'         => $igarnDatum,
                    'ibc_ok'        => $igar['ibc_ok'],
                    'ibc_ej_ok'     => $igar['ibc_ej_ok'],
                    'kvalitet_pct'  => $igar['kvalitet_pct'],
                    'ibc_per_timme' => $igar['ibc_per_timme'],
                    'oee_pct'       => $oeeIgar['oee_pct'],
                ],
                'foreg_vecka'     => [
                    'datum'         => $foregVecka,
                    'ibc_ok'        => $foreg['ibc_ok'],
                    'ibc_ej_ok'     => $foreg['ibc_ej_ok'],
                    'kvalitet_pct'  => $foreg['kvalitet_pct'],
                    'ibc_per_timme' => $foreg['ibc_per_timme'],
                    'oee_pct'       => $oeeForeg['oee_pct'],
                ],
                'veckosnitt'      => $vecko,
                'diff_mot_igar'   => [
                    'ibc_pct' => $diffIgarIbc,
                    'oee_pct' => $diffIgarOee,
                    'trend'   => $diffIgarIbc > 2.0 ? 'up' : ($diffIgarIbc < -2.0 ? 'down' : 'flat'),
                ],
                'diff_mot_foreg_vecka' => [
                    'ibc_pct' => $diffForegIbc,
                    'oee_pct' => $diffForegOee,
                    'trend'   => $diffForegIbc > 2.0 ? 'up' : ($diffForegIbc < -2.0 ? 'down' : 'flat'),
                ],
            ]);

        } catch (\Exception $e) {
            error_log('DagligSammanfattningController::getComparison: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta jämförelsedata', 500);
        }
    }
}
