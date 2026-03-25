<?php
/**
 * VDVeckorapportController.php
 * VD veckorapport — automatisk veckosammanfattning för ledningen.
 *
 * Endpoints via ?action=vd-veckorapport&run=XXX:
 *
 *   run=kpi-jamforelse
 *       KPI-jämförelse denna vecka vs förra veckan:
 *       OEE, produktion (IBC), kassation (%), drifttid (h).
 *       Inkl. absolut och procentuell förändring, trend-indikator.
 *
 *   run=trender-anomalier
 *       Identifierade anomalier och trender senaste 14d.
 *       Returnerar lista med [datum, typ, beskrivning, allvarlighet].
 *
 *   run=top-bottom-operatorer&period=7
 *       Top 3 och bottom 3 operatörer (OEE, IBC, kassation) för perioden.
 *
 *   run=stopporsaker&period=7
 *       Stopporsaker rangordnade efter total stopptid senaste perioden.
 *
 *   run=vecka-sammanfattning&vecka=YYYY-WW
 *       Fullständig sammanfattning för en specifik vecka (för utskrift).
 *       Returnerar all data i ett anrop.
 *
 * Tabeller:
 *   rebotling_ibc                  — IBC-data (datum, ibc_ok, ibc_ej_ok, runtime_plc, op1/op2/op3)
 *   rebotling_onoff                — drift/stopp (datum, running)
 *   operators                      — (number, name)
 *   stoppage_log + stoppage_reasons — stopporsaker (reason_id, duration_minutes, created_at)
 *   stopporsak_registreringar      — stopp (kategori_id, start_time, end_time, kommentar)
 */
class VDVeckorapportController {
    private $pdo;
    private const IDEAL_CYKELTID = 120; // sekunder per IBC

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? '');
        switch ($run) {
            case 'kpi-jamforelse':
                $this->kpiJamforelse();
                break;
            case 'trender-anomalier':
                $this->trenderAnomalier();
                break;
            case 'top-bottom-operatorer':
                $this->topBottomOperatorer();
                break;
            case 'stopporsaker':
                $this->stopporsaker();
                break;
            case 'vecka-sammanfattning':
                $this->veckaSammanfattning();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Okänt run-kommando'], JSON_UNESCAPED_UNICODE);
                return;
        }
    }

    // ============================================================
    // run=kpi-jamforelse
    // ============================================================

    private function kpiJamforelse(): void {
        // Beräkna veckointervall: ISO-vecka
        // Denna vecka = aktuell ISO-vecka (måndag-söndag)
        $today       = new DateTime('today', new DateTimeZone('Europe/Stockholm'));
        $monday      = clone $today;
        $monday->modify('monday this week');
        $sunday      = clone $monday;
        $sunday->modify('+6 days');

        $lastMonday  = clone $monday;
        $lastMonday->modify('-7 days');
        $lastSunday  = clone $lastMonday;
        $lastSunday->modify('+6 days');

        $thisVecka = $this->beraknaKpiForPeriod(
            $monday->format('Y-m-d'),
            $today->format('Y-m-d')
        );
        $forraVecka = $this->beraknaKpiForPeriod(
            $lastMonday->format('Y-m-d'),
            $lastSunday->format('Y-m-d')
        );

        $kpiLista = ['oee', 'produktion', 'kassation', 'drifttid_h'];
        $jamforelse = [];

        foreach ($kpiLista as $kpi) {
            $denna   = $thisVecka[$kpi]  ?? 0;
            $forra   = $forraVecka[$kpi] ?? 0;
            $diff    = $denna - $forra;
            $diffPct = $forra > 0 ? round(($diff / $forra) * 100, 1) : null;

            // Trend: för kassation är lägre bättre
            $positivtUpp = ($kpi !== 'kassation');
            if ($diff > 0) {
                $trend = $positivtUpp ? 'upp' : 'ned';
            } elseif ($diff < 0) {
                $trend = $positivtUpp ? 'ned' : 'upp';
            } else {
                $trend = 'stabil';
            }

            $jamforelse[$kpi] = [
                'denna_vecka'  => round($denna, 2),
                'forra_vecka'  => round($forra, 2),
                'diff'         => round($diff, 2),
                'diff_pct'     => $diffPct,
                'trend'        => $trend,
            ];
        }

        // Daglig produktion denna vecka (sparkline)
        $daglig = $this->dagligProduktion(
            $monday->format('Y-m-d'),
            $today->format('Y-m-d')
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'denna_vecka_fran'  => $monday->format('Y-m-d'),
                'denna_vecka_till'  => $today->format('Y-m-d'),
                'forra_vecka_fran'  => $lastMonday->format('Y-m-d'),
                'forra_vecka_till'  => $lastSunday->format('Y-m-d'),
                'veckonummer'       => (int)$today->format('W'),
                'ar'                => (int)$today->format('Y'),
                'jamforelse'        => $jamforelse,
                'daglig_produktion' => $daglig,
            ],
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function beraknaKpiForPeriod(string $fran, string $till): array {
        // IBC-produktion och kassation
        $sql = "
            SELECT
                COUNT(*) AS total_ibc,
                SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 0 ELSE 1 END) AS godkanda,
                SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade,
                MIN(datum) AS forsta,
                MAX(datum) AS sista
            FROM rebotling_ibc
            WHERE DATE(datum) BETWEEN :fran AND :till
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fran' => $fran, ':till' => $till]);
        $rad = $stmt->fetch(PDO::FETCH_ASSOC);

        $total    = (int)($rad['total_ibc']  ?? 0);
        $godkanda = (int)($rad['godkanda']   ?? 0);
        $kasserade = (int)($rad['kasserade'] ?? 0);

        $kassation = $total > 0 ? round(($kasserade / $total) * 100, 2) : 0;

        // Drifttid från rebotling_onoff
        $drifttid_sek = $this->hamtaDrifttid($fran, $till);

        // OEE-beräkning (förenklad)
        $planerad_sek = $this->beraknaPlanadTid($fran, $till);
        $T = $planerad_sek > 0 ? min($drifttid_sek / $planerad_sek, 1.0) : 0;
        $P = ($drifttid_sek > 0 && $total > 0)
            ? min(($total * self::IDEAL_CYKELTID) / $drifttid_sek, 1.0)
            : 0;
        $K = $total > 0 ? $godkanda / $total : 0;
        $oee = round($T * $P * $K * 100, 2);

        return [
            'oee'        => $oee,
            'produktion' => $total,
            'kassation'  => $kassation,
            'drifttid_h' => round($drifttid_sek / 3600, 1),
        ];
    }

    private function hamtaDrifttid(string $fran, string $till): float {
        // Fallback: skatta drifttid från IBC-data om rebotling_onoff saknas
        $sql = "
            SELECT
                MIN(datum) AS forsta,
                MAX(datum) AS sista,
                COUNT(DISTINCT DATE(datum)) AS dagar
            FROM rebotling_ibc
            WHERE DATE(datum) BETWEEN :fran AND :till
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fran' => $fran, ':till' => $till]);
        $rad = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rad || !$rad['forsta']) return 0;

        $drifttid = strtotime($rad['sista']) - strtotime($rad['forsta']);
        return max($drifttid, 0);
    }

    private function beraknaPlanadTid(string $fran, string $till): float {
        // Antal unika produktionsdagar * 8h (ett skift per dag som minimum)
        $sql = "
            SELECT COUNT(DISTINCT DATE(datum)) AS dagar
            FROM rebotling_ibc
            WHERE DATE(datum) BETWEEN :fran AND :till
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fran' => $fran, ':till' => $till]);
        $dagar = (int)$stmt->fetchColumn();
        return $dagar * 28800; // 8h per dag
    }

    private function dagligProduktion(string $fran, string $till): array {
        $sql = "
            SELECT
                DATE(datum) AS dag,
                COUNT(*) AS total_ibc,
                SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 0 ELSE 1 END) AS godkanda
            FROM rebotling_ibc
            WHERE DATE(datum) BETWEEN :fran AND :till
            GROUP BY DATE(datum)
            ORDER BY dag ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fran' => $fran, ':till' => $till]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $total = (int)$r['total_ibc'];
            $kass  = $total > 0 ? round((1 - (int)$r['godkanda'] / $total) * 100, 1) : 0;
            $result[] = [
                'dag'       => $r['dag'],
                'ibc'       => $total,
                'kassation' => $kass,
            ];
        }
        return $result;
    }

    // ============================================================
    // run=trender-anomalier
    // ============================================================

    private function trenderAnomalier(): void {
        $dagar = 14;

        $dagar = (int)$dagar; // Säkerställ int
        $sql = "
            SELECT
                DATE(datum) AS dag,
                COUNT(*) AS total_ibc,
                SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 0 ELSE 1 END) AS godkanda
            FROM rebotling_ibc
            WHERE datum >= DATE_SUB(CURDATE(), INTERVAL {$dagar} DAY)
            GROUP BY DATE(datum)
            ORDER BY dag ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) < 3) {
            echo json_encode(['success' => true, 'data' => ['anomalier' => [], 'trender' => []]], JSON_UNESCAPED_UNICODE);
            return;
        }

        $produktion = array_map(fn($r) => (int)$r['total_ibc'], $rows);
        $kassation  = array_map(fn($r) => (int)$r['total_ibc'] > 0
            ? round((1 - (int)$r['godkanda'] / (int)$r['total_ibc']) * 100, 2)
            : 0, $rows);

        $medProd = array_sum($produktion) / count($produktion);
        $medKass = array_sum($kassation) / count($kassation);
        $stdProd = $this->stdavvikelse($produktion);
        $stdKass = $this->stdavvikelse($kassation);

        $anomalier = [];
        foreach ($rows as $i => $rad) {
            $dag  = $rad['dag'];
            $prod = $produktion[$i];
            $kass = $kassation[$i];

            // Produktionsanomalier (> 2 stdav)
            if ($stdProd > 0 && abs($prod - $medProd) > 2 * $stdProd) {
                $positivt = $prod > $medProd;
                $anomalier[] = [
                    'datum'       => $dag,
                    'typ'         => 'produktion',
                    'beskrivning' => $positivt
                        ? "Ovanligt hög produktion ({$prod} IBC)"
                        : "Ovanligt låg produktion ({$prod} IBC)",
                    'allvarlighet' => $positivt ? 'positiv' : 'varning',
                    'varde'        => $prod,
                    'medel'        => round($medProd, 1),
                ];
            }

            // Kassationsanomalier (> 1.5 stdav)
            if ($stdKass > 0 && ($kass - $medKass) > 1.5 * $stdKass) {
                $anomalier[] = [
                    'datum'       => $dag,
                    'typ'         => 'kassation',
                    'beskrivning' => "Hög kassationsgrad ({$kass}%)",
                    'allvarlighet' => $kass > $medKass + 2.5 * $stdKass ? 'kritisk' : 'varning',
                    'varde'        => $kass,
                    'medel'        => round($medKass, 1),
                ];
            }
        }

        // Trender (linjär regression senaste 7 dagar)
        $sista7prod = array_slice($produktion, -7);
        $sista7kass = array_slice($kassation, -7);
        $regProd = $this->linjarRegression($sista7prod);
        $regKass = $this->linjarRegression($sista7kass);

        $trender = [
            'produktion' => [
                'slope'  => round($regProd['slope'], 2),
                'trend'  => $regProd['slope'] > 2 ? 'stiger' : ($regProd['slope'] < -2 ? 'sjunker' : 'stabil'),
                'r2'     => round($regProd['r2'], 3),
            ],
            'kassation' => [
                'slope'  => round($regKass['slope'], 2),
                'trend'  => $regKass['slope'] > 0.3 ? 'forsamras' : ($regKass['slope'] < -0.3 ? 'forbattras' : 'stabil'),
                'r2'     => round($regKass['r2'], 3),
            ],
        ];

        // Sortera anomalier nyast först
        usort($anomalier, fn($a, $b) => strcmp($b['datum'], $a['datum']));

        echo json_encode([
            'success' => true,
            'data'    => [
                'anomalier' => $anomalier,
                'trender'   => $trender,
            ],
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // run=top-bottom-operatorer
    // ============================================================

    private function topBottomOperatorer(): void {
        $period = max(1, min(90, (int)($_GET['period'] ?? 7)));
        $fran   = date('Y-m-d', strtotime("-{$period} days"));
        $till   = date('Y-m-d');

        // Samla operatorsdata fran rebotling_ibc (kumulativa PLC-falt)
        $sql = "
            SELECT
                sub.op_num AS operator_id,
                COALESCE(o.name, CONCAT('Op #', sub.op_num)) AS operator_namn,
                SUM(sub.shift_ok)                  AS ibc_ok,
                SUM(sub.shift_ej_ok)               AS ibc_kasserade,
                SUM(sub.shift_ok + sub.shift_ej_ok) AS ibc_totalt,
                SUM(sub.runtime_sek)               AS drifttid_sek,
                COUNT(*)                           AS antal_skift
            FROM (
                SELECT op_num, skiftraknare,
                       MAX(ibc_ok) AS shift_ok,
                       MAX(ibc_ej_ok) AS shift_ej_ok,
                       MAX(runtime_plc) * 60 AS runtime_sek
                FROM (
                    SELECT op1 AS op_num, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :fran1 AND :till1 AND op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT op2, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :fran2 AND :till2 AND op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT op3, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :fran3 AND :till3 AND op3 IS NOT NULL AND op3 > 0
                ) raw
                GROUP BY op_num, skiftraknare
            ) sub
            LEFT JOIN operators o ON o.number = sub.op_num
            GROUP BY sub.op_num, o.name
            HAVING ibc_totalt > 0
            ORDER BY ibc_ok DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':fran1' => $fran, ':till1' => $till,
            ':fran2' => $fran, ':till2' => $till,
            ':fran3' => $fran, ':till3' => $till,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            echo json_encode([
                'success' => true,
                'data'    => ['top' => [], 'bottom' => [], 'period' => $period],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $operatorer = [];
        foreach ($rows as $r) {
            $total   = (int)$r['ibc_totalt'];
            $ok      = (int)$r['ibc_ok'];
            $kass    = (int)$r['ibc_kasserade'];
            $drifttid = (float)$r['drifttid_sek'];

            $kassGrad = $total > 0 ? round(($kass / $total) * 100, 1) : 0;
            $oee = 0;
            if ($drifttid > 0 && $total > 0) {
                $T = min($drifttid / ($r['antal_skift'] * 28800), 1.0);
                $P = min(($total * self::IDEAL_CYKELTID) / $drifttid, 1.0);
                $K = $total > 0 ? $ok / $total : 0;
                $oee = round($T * $P * $K * 100, 1);
            }

            $operatorer[] = [
                'operator_id'   => (int)$r['operator_id'],
                'operator_namn' => $r['operator_namn'],
                'ibc_ok'        => $ok,
                'kassationsgrad' => $kassGrad,
                'oee'           => $oee,
                'antal_skift'   => (int)$r['antal_skift'],
            ];
        }

        // Sortera efter OEE för top/bottom
        usort($operatorer, fn($a, $b) => $b['oee'] <=> $a['oee']);

        $antal = count($operatorer);
        $top3   = array_slice($operatorer, 0, min(3, $antal));
        $bottom = $antal >= 4
            ? array_slice($operatorer, max(0, $antal - 3))
            : [];

        // Lägg till rank
        foreach ($top3 as $i => &$op) {
            $op['rank'] = $i + 1;
        }
        unset($op);

        echo json_encode([
            'success' => true,
            'data'    => [
                'top'    => $top3,
                'bottom' => array_values(array_reverse($bottom)),
                'totalt' => $antal,
                'period' => $period,
            ],
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    // ============================================================
    // run=stopporsaker
    // ============================================================

    private function stopporsaker(): void {
        $period = max(1, min(90, (int)($_GET['period'] ?? 7)));
        $fran   = date('Y-m-d', strtotime("-{$period} days"));
        $till   = date('Y-m-d');

        // Försök stoppage_log
        $stopp = $this->hamtaStopporsaker($fran, $till);

        echo json_encode([
            'success'   => true,
            'data'      => [
                'stopporsaker' => $stopp,
                'period'       => $period,
                'fran'         => $fran,
                'till'         => $till,
            ],
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function hamtaStopporsaker(string $fran, string $till): array {
        // Prova stoppage_log
        try {
            $sql = "
                SELECT
                    COALESCE(sr.name, 'Okänd orsak') AS orsak,
                    COUNT(*)                         AS antal,
                    SUM(sl.duration_minutes * 60)    AS total_tid_sek,
                    AVG(sl.duration_minutes * 60)    AS medel_tid_sek
                FROM stoppage_log sl
                LEFT JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                WHERE DATE(sl.created_at) BETWEEN :fran AND :till
                  AND sl.duration_minutes > 0
                GROUP BY sr.name
                ORDER BY total_tid_sek DESC
                LIMIT 15
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':fran' => $fran, ':till' => $till]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $total = array_sum(array_column($rows, 'total_tid_sek'));
                return array_map(function($r) use ($total) {
                    $tid = (float)$r['total_tid_sek'];
                    return [
                        'orsak'         => $r['orsak'] ?: 'Okänd',
                        'antal'         => (int)$r['antal'],
                        'total_min'     => round($tid / 60, 1),
                        'medel_min'     => round((float)$r['medel_tid_sek'] / 60, 1),
                        'andel_pct'     => $total > 0 ? round($tid / $total * 100, 1) : 0,
                    ];
                }, $rows);
            }
        } catch (\Exception $e) {
            error_log('VDVeckorapportController::getTopStopporsaker: ' . $e->getMessage());
        }

        // Fallback: prova stopporsak_registreringar
        try {
            $sql = "
                SELECT
                    COALESCE(sk.namn, 'Okand') AS orsak,
                    COUNT(*) AS antal,
                    SUM(TIMESTAMPDIFF(SECOND, sr.start_time, COALESCE(sr.end_time, NOW()))) AS total_tid_sek
                FROM stopporsak_registreringar sr
                LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                WHERE DATE(sr.start_time) BETWEEN :fran AND :till
                GROUP BY COALESCE(sk.namn, 'Okand')
                ORDER BY total_tid_sek DESC
                LIMIT 15
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':fran' => $fran, ':till' => $till]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = array_sum(array_column($rows, 'total_tid_sek'));
            return array_map(function($r) use ($total) {
                $tid = (float)($r['total_tid_sek'] ?? 0);
                return [
                    'orsak'     => $r['orsak'] ?: 'Okänd',
                    'antal'     => (int)$r['antal'],
                    'total_min' => round($tid / 60, 1),
                    'medel_min' => $r['antal'] > 0 ? round($tid / (int)$r['antal'] / 60, 1) : 0,
                    'andel_pct' => $total > 0 ? round($tid / $total * 100, 1) : 0,
                ];
            }, $rows);
        } catch (\Exception $e) {
            error_log('VDVeckorapportController::hamtaStopporsaker: ' . $e->getMessage());
            return [];
        }
    }

    // ============================================================
    // run=vecka-sammanfattning
    // ============================================================

    private function veckaSammanfattning(): void {
        // Hämta all data för utskriftsvy i ett enda anrop
        $veckaParam = $_GET['vecka'] ?? null; // format: YYYY-WW

        if ($veckaParam && preg_match('/^(\d{4})-(\d{1,2})$/', $veckaParam, $m)) {
            $ar    = (int)$m[1];
            $vecka = (int)$m[2];
            // Hitta måndag för given vecka
            $tz = new DateTimeZone('Europe/Stockholm');
            $monday = new DateTime('now', $tz);
            $monday->setISODate($ar, $vecka, 1);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
        } else {
            // Aktuell vecka
            $tz = new DateTimeZone('Europe/Stockholm');
            $monday = new DateTime('monday this week', $tz);
            $today  = new DateTime('today', $tz);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            // Begränsa till idag
            if ($today < $sunday) {
                $sunday = $today;
            }
            $ar    = (int)$monday->format('Y');
            $vecka = (int)$monday->format('W');
        }

        $fran = $monday->format('Y-m-d');
        $till = $sunday->format('Y-m-d');

        // Samla all data
        $kpiDenna  = $this->beraknaKpiForPeriod($fran, $till);

        // Förra veckan
        $lastMonday = clone $monday;
        $lastMonday->modify('-7 days');
        $lastSunday = clone $lastMonday;
        $lastSunday->modify('+6 days');
        $kpiForra = $this->beraknaKpiForPeriod(
            $lastMonday->format('Y-m-d'),
            $lastSunday->format('Y-m-d')
        );

        $daglig      = $this->dagligProduktion($fran, $till);
        $stopp       = $this->hamtaStopporsaker($fran, $till);
        $operatorer  = $this->hamtaOperatorsData($fran, $till);

        // Anomalier & trender (senaste 14d)
        $anomaliData = $this->beraknaAnomalierPeriod($fran, $till);

        echo json_encode([
            'success' => true,
            'data'    => [
                'ar'         => $ar,
                'vecka'      => $vecka,
                'fran'       => $fran,
                'till'       => $till,
                'kpi_denna'  => $kpiDenna,
                'kpi_forra'  => $kpiForra,
                'daglig'     => $daglig,
                'stopporsaker'  => array_slice($stopp, 0, 10),
                'operatorer' => $operatorer,
                'anomalier'  => $anomaliData,
                'genererad'  => date('Y-m-d H:i:s'),
            ],
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function hamtaOperatorsData(string $fran, string $till): array {
        try {
            $sql = "
                SELECT
                    sub.op_num AS operator_id,
                    COALESCE(o.name, CONCAT('Op #', sub.op_num)) AS operator_namn,
                    SUM(sub.shift_ok)                  AS ibc_ok,
                    SUM(sub.shift_ej_ok)               AS ibc_kasserade,
                    SUM(sub.shift_ok + sub.shift_ej_ok) AS ibc_totalt,
                    SUM(sub.runtime_sek)               AS drifttid_sek,
                    COUNT(*)                           AS antal_skift
                FROM (
                    SELECT op_num, skiftraknare,
                           MAX(ibc_ok) AS shift_ok,
                           MAX(ibc_ej_ok) AS shift_ej_ok,
                           MAX(runtime_plc) * 60 AS runtime_sek
                    FROM (
                        SELECT op1 AS op_num, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :fran1 AND :till1 AND op1 IS NOT NULL AND op1 > 0
                        UNION ALL
                        SELECT op2, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :fran2 AND :till2 AND op2 IS NOT NULL AND op2 > 0
                        UNION ALL
                        SELECT op3, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :fran3 AND :till3 AND op3 IS NOT NULL AND op3 > 0
                    ) raw
                    GROUP BY op_num, skiftraknare
                ) sub
                LEFT JOIN operators o ON o.number = sub.op_num
                GROUP BY sub.op_num, o.name
                HAVING ibc_totalt > 0
                ORDER BY ibc_ok DESC
                LIMIT 20
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':fran1' => $fran, ':till1' => $till,
                ':fran2' => $fran, ':till2' => $till,
                ':fran3' => $fran, ':till3' => $till,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($r) {
                $total = (int)$r['ibc_totalt'];
                $ok    = (int)$r['ibc_ok'];
                $kass  = $total > 0 ? round(((int)$r['ibc_kasserade'] / $total) * 100, 1) : 0;
                $drifttid = (float)$r['drifttid_sek'];
                $oee = 0;
                if ($drifttid > 0 && $total > 0) {
                    $T = min($drifttid / ($r['antal_skift'] * 28800), 1.0);
                    $P = min(($total * self::IDEAL_CYKELTID) / $drifttid, 1.0);
                    $K = $ok / $total;
                    $oee = round($T * $P * $K * 100, 1);
                }
                return [
                    'operator_id'    => (int)$r['operator_id'],
                    'operator_namn'  => $r['operator_namn'],
                    'ibc_ok'         => $ok,
                    'kassationsgrad' => $kass,
                    'oee'            => $oee,
                    'antal_skift'    => (int)$r['antal_skift'],
                ];
            }, $rows);
        } catch (\Exception $e) {
            error_log('VDVeckorapportController::hamtaOperatorsData: ' . $e->getMessage());
            return [];
        }
    }

    private function beraknaAnomalierPeriod(string $fran, string $till): array {
        try {
            $sql = "
                SELECT
                    DATE(datum) AS dag,
                    COUNT(*) AS total_ibc,
                    SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 0 ELSE 1 END) AS godkanda
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :fran AND :till
                GROUP BY DATE(datum)
                ORDER BY dag ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':fran' => $fran, ':till' => $till]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) < 3) return [];

            $produktion = array_map(fn($r) => (int)$r['total_ibc'], $rows);
            $kassation  = array_map(fn($r) => (int)$r['total_ibc'] > 0
                ? round((1 - (int)$r['godkanda'] / (int)$r['total_ibc']) * 100, 2)
                : 0, $rows);

            $medProd = array_sum($produktion) / count($produktion);
            $medKass = array_sum($kassation)  / count($kassation);
            $stdProd = $this->stdavvikelse($produktion);
            $stdKass = $this->stdavvikelse($kassation);

            $anomalier = [];
            foreach ($rows as $i => $rad) {
                $prod = $produktion[$i];
                $kass = $kassation[$i];

                if ($stdProd > 0 && abs($prod - $medProd) > 2 * $stdProd) {
                    $anomalier[] = [
                        'datum'        => $rad['dag'],
                        'typ'          => 'produktion',
                        'beskrivning'  => $prod > $medProd
                            ? "Ovanligt hög produktion ({$prod} IBC)"
                            : "Ovanligt låg produktion ({$prod} IBC)",
                        'allvarlighet' => $prod > $medProd ? 'positiv' : 'varning',
                    ];
                }
                if ($stdKass > 0 && ($kass - $medKass) > 1.5 * $stdKass) {
                    $anomalier[] = [
                        'datum'        => $rad['dag'],
                        'typ'          => 'kassation',
                        'beskrivning'  => "Hög kassationsgrad ({$kass}%)",
                        'allvarlighet' => 'varning',
                    ];
                }
            }
            return $anomalier;
        } catch (\Exception $e) {
            error_log('VDVeckorapportController::beraknaAnomalierPeriod: ' . $e->getMessage());
            return [];
        }
    }

    // ============================================================
    // Hjälpfunktioner
    // ============================================================

    private function stdavvikelse(array $values): float {
        $n = count($values);
        if ($n < 2) return 0;
        $medel = array_sum($values) / $n;
        $sumSq = 0;
        foreach ($values as $v) {
            $sumSq += ($v - $medel) ** 2;
        }
        return sqrt($sumSq / ($n - 1));
    }

    private function linjarRegression(array $values): array {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0, 'r2' => 0];
        }
        $sumX  = 0; $sumY  = 0;
        $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX  += $i;
            $sumY  += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if (abs($denom) < 0.0001) {
            return ['slope' => 0, 'intercept' => $sumY / $n, 'r2' => 0];
        }
        $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;
        // R²
        $medel = $sumY / $n;
        $ssTot = 0; $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $i + $intercept;
            $ssTot    += ($values[$i] - $medel) ** 2;
            $ssRes    += ($values[$i] - $predicted) ** 2;
        }
        $r2 = $ssTot > 0 ? 1 - $ssRes / $ssTot : 0;
        return ['slope' => round($slope, 4), 'intercept' => round($intercept, 4), 'r2' => max(0, round($r2, 4))];
    }
}
