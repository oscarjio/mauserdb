<?php
/**
 * OperatorDashboardController
 *
 * Endpoints:
 * GET ?action=operator-dashboard&run=today    → Dagens operatörsstatus
 * GET ?action=operator-dashboard&run=weekly   → Veckostats per operatör (senaste 7 dagar)
 * GET ?action=operator-dashboard&run=history&days=30 → Daglig IBC per operatör
 * GET ?action=operator-dashboard&run=summary  → Team-stats idag + vecka + månad
 *
 * Kräver INGEN session — publik GET.
 */
class OperatorDashboardController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->sendError('Endast GET tillåtet', 405);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        // Personliga endpoints kräver inloggning
        $personalEndpoints = ['min-produktion', 'mitt-tempo', 'min-bonus', 'mina-stopp', 'min-veckotrend'];
        if (in_array($run, $personalEndpoints, true)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start(['read_and_close' => true]);
            }
            if (empty($_SESSION['user_id'])) {
                $this->sendError('Inloggning krävs', 401);
                return;
            }
        }

        switch ($run) {
            case 'today':   $this->getToday();   break;
            case 'weekly':  $this->getWeekly();  break;
            case 'history': $this->getHistory(); break;
            case 'summary': $this->getSummary(); break;
            // --- Personligt operatörs-dashboard ---
            case 'operatorer':       $this->getOperatorer();       break;
            case 'min-produktion':   $this->getMinProduktion();    break;
            case 'mitt-tempo':       $this->getMittTempo();        break;
            case 'min-bonus':        $this->getMinBonus();         break;
            case 'mina-stopp':       $this->getMinaStopp();        break;
            case 'min-veckotrend':   $this->getMinVeckotrend();    break;
            default:
                $this->sendError('Okänd metod');
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function sendSuccess(array $data): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    /**
     * Räkna ut initialer (max 2 tecken) från ett namn.
     */
    private function initialer(string $namn): string {
        $delar = explode(' ', trim($namn));
        $ini = '';
        foreach ($delar as $d) {
            if ($d !== '') $ini .= mb_strtoupper(mb_substr($d, 0, 1));
        }
        return mb_substr($ini, 0, 2);
    }

    /**
     * Hämtar operatörsnamnkarta [ number => name ] för givna nummer.
     */
    private function getNamnMap(array $nums): array {
        if (empty($nums)) return [];
        $nums = array_values(array_map('intval', array_unique($nums)));
        $placeholders = implode(',', array_fill(0, count($nums), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT number, name FROM operators WHERE number IN ($placeholders)"
        );
        $stmt->execute($nums);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $op) {
            $map[(int)$op['number']] = $op['name'];
        }
        return $map;
    }

    // ================================================================
    // ENDPOINT: today
    // ================================================================

    /**
     * Hämtar operatörsstatus för idag.
     * Aggregerar rebotling_skiftrapport per operator-nummer.
     * Beräknar IBC/h baserat på total ibc_ok / total drifttid (minuter).
     * "Senaste aktivitet" = antalet minuter sedan senaste skiftet avslutades idag.
     */
    private function getToday() {
        try {
            $today = date('Y-m-d');

            // Hämta alla skift för idag, unioner op1/op2/op3 → per operator-nummer
            $sql = "
                SELECT
                    op_num,
                    COUNT(DISTINCT skift_id)            AS antal_skift,
                    SUM(ibc_ok)                         AS tot_ibc_ok,
                    SUM(tot_totalt)                     AS tot_totalt,
                    SUM(drifttid_min)                   AS tot_drifttid,
                    MAX(updated_at)                     AS senaste_aktivitet
                FROM (
                    SELECT id AS skift_id, op1 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE datum >= :today1 AND datum < DATE_ADD(:today1b, INTERVAL 1 DAY) AND op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT id AS skift_id, op2 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE datum >= :today2 AND datum < DATE_ADD(:today2b, INTERVAL 1 DAY) AND op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT id AS skift_id, op3 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE datum >= :today3 AND datum < DATE_ADD(:today3b, INTERVAL 1 DAY) AND op3 IS NOT NULL AND op3 > 0
                ) AS alla
                GROUP BY op_num
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':today1' => $today, ':today1b' => $today, ':today2' => $today, ':today2b' => $today, ':today3' => $today, ':today3b' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->sendSuccess([
                    'success'         => true,
                    'datum'           => $today,
                    'operatorer'      => [],
                    'total_ibc'       => 0,
                    'snitt_ibc_per_h' => 0,
                    'bast_namn'       => null,
                    'bast_ibc_per_h'  => 0,
                ]);
                return;
            }

            $nums = array_column($rows, 'op_num');
            $nameMap = $this->getNamnMap($nums);

            $now = time();
            $operatorer = [];
            $totalIbc = 0;
            $sumIbcPerH = 0.0;
            $countWithRate = 0;
            $bastNamn = '';
            $bastRate = 0.0;

            foreach ($rows as $r) {
                $opNum    = (int)$r['op_num'];
                $namn     = $nameMap[$opNum] ?? ('Operatör #' . $opNum);
                $ibcIdag  = (int)$r['tot_ibc_ok'];
                $totalt   = (int)$r['tot_totalt'];
                $drifttid = (float)$r['tot_drifttid']; // minuter

                // IBC per timme
                $ibcPerH = ($drifttid > 0) ? round($ibcIdag / ($drifttid / 60.0), 1) : 0;

                // Kvalitet%
                $kvalitet = ($totalt > 0) ? round($ibcIdag * 100.0 / $totalt, 1) : null;

                // Minuter sedan senaste aktivitet
                $senasteTid = $r['senaste_aktivitet'];
                $minuterSedan = null;
                if ($senasteTid) {
                    $ts = strtotime($senasteTid);
                    if ($ts !== false) {
                        $minuterSedan = (int)max(0, round(($now - $ts) / 60));
                    }
                }

                // Status-bedömning
                if ($minuterSedan === null || $minuterSedan > 30) {
                    $status = 'inaktiv';
                } elseif ($ibcPerH >= 18) {
                    $status = 'bra';
                } elseif ($ibcPerH >= 12) {
                    $status = 'ok';
                } else {
                    $status = 'lag';
                }

                $operatorer[] = [
                    'op_id'         => $opNum,
                    'namn'          => $namn,
                    'initialer'     => $this->initialer($namn),
                    'ibc_idag'      => $ibcIdag,
                    'ibc_per_h'     => $ibcPerH,
                    'kvalitet_pct'  => $kvalitet,
                    'minuter_sedan' => $minuterSedan,
                    'status'        => $status,
                ];

                $totalIbc += $ibcIdag;
                if ($ibcPerH > 0) {
                    $sumIbcPerH += $ibcPerH;
                    $countWithRate++;
                }
                if ($ibcPerH > $bastRate) {
                    $bastRate = $ibcPerH;
                    $bastNamn = $namn;
                }
            }

            // Sortera: aktiva överst (status != inaktiv), sedan efter ibc_per_h desc
            usort($operatorer, function($a, $b) {
                $aInaktiv = ($a['status'] === 'inaktiv') ? 1 : 0;
                $bInaktiv = ($b['status'] === 'inaktiv') ? 1 : 0;
                if ($aInaktiv !== $bInaktiv) return $aInaktiv <=> $bInaktiv;
                return $b['ibc_per_h'] <=> $a['ibc_per_h'];
            });

            $snittIbcPerH = ($countWithRate > 0)
                ? round($sumIbcPerH / $countWithRate, 1)
                : 0;

            $this->sendSuccess([
                'success'         => true,
                'datum'           => $today,
                'operatorer'      => $operatorer,
                'total_ibc'       => $totalIbc,
                'snitt_ibc_per_h' => $snittIbcPerH,
                'bast_namn'       => $bastNamn ?: null,
                'bast_ibc_per_h'  => $bastRate,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getToday: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsstatus', 500);
        }
    }

    // ================================================================
    // ENDPOINT: weekly
    // ================================================================

    /**
     * Veckostats per operatör — senaste 7 dagarna.
     *
     * Aggregering (korrigerat för kumulativa PLC-värden i rebotling_ibc):
     *   1. Per skiftraknare: MAX(ibc_ok), MAX(runtime_plc)
     *   2. Per dag: SUM av steg 1
     *   3. Per operatör + sista 7 dagar: SUM av per-dag
     *
     * Returnerar: op_id, namn, initialer, total_ibc, snitt_ibc_per_h,
     *             aktiva_dagar, trend (upp/ner/stabil), bast_dag_ibc
     */
    private function getWeekly() {
        try {
            $today = date('Y-m-d');
            $sjuDagarSen = date('Y-m-d', strtotime('-6 days'));

            // LAG-korrigerad: ibc_ok är dagligt löpande räkneverk
            $treDagarSen = date('Y-m-d', strtotime('-2 days')); // senaste 3 dagar inkl. idag

            $sql = "
                WITH lag_base AS (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS ibc_end,
                           MAX(COALESCE(runtime_plc, 0)) AS max_runtime,
                           MIN(op1) AS op1, MIN(op2) AS op2, MIN(op3) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= :from AND datum < DATE_ADD(:to, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ),
                lag_shifts AS (
                    SELECT dag, skiftraknare,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ibc,
                           max_runtime, op1, op2, op3
                    FROM lag_base
                ),
                op_dag AS (
                    SELECT op_num, dag, SUM(delta_ibc) AS dag_ibc, SUM(max_runtime) AS dag_runtime
                    FROM (
                        SELECT op1 AS op_num, dag, delta_ibc, max_runtime FROM lag_shifts WHERE op1 IS NOT NULL AND op1 > 0
                        UNION ALL
                        SELECT op2 AS op_num, dag, delta_ibc, max_runtime FROM lag_shifts WHERE op2 IS NOT NULL AND op2 > 0
                        UNION ALL
                        SELECT op3 AS op_num, dag, delta_ibc, max_runtime FROM lag_shifts WHERE op3 IS NOT NULL AND op3 > 0
                    ) AS sub
                    GROUP BY op_num, dag
                )
                SELECT op_num,
                       SUM(dag_ibc)                                                       AS total_ibc,
                       ROUND(SUM(dag_ibc) * 60.0 / NULLIF(SUM(dag_runtime), 0), 1)       AS snitt_ibc_per_h,
                       COUNT(DISTINCT dag)                                                AS aktiva_dagar,
                       MAX(dag_ibc)                                                       AS bast_dag_ibc,
                       SUM(CASE WHEN dag >= :tre_dagar_sen_a THEN dag_ibc ELSE 0 END)    AS senaste_3_ibc,
                       SUM(CASE WHEN dag <  :tre_dagar_sen_b THEN dag_ibc ELSE 0 END)    AS forega_4_ibc
                FROM op_dag
                GROUP BY op_num
                ORDER BY total_ibc DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from'            => $sjuDagarSen,
                ':to'              => $today,
                ':tre_dagar_sen_a' => $treDagarSen,
                ':tre_dagar_sen_b' => $treDagarSen,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->sendSuccess(['success' => true, 'operatorer' => []]);
                return;
            }

            $nums = array_column($rows, 'op_num');
            $nameMap = $this->getNamnMap($nums);

            $operatorer = [];
            foreach ($rows as $r) {
                $opNum = (int)$r['op_num'];
                $namn  = $nameMap[$opNum] ?? ('Operatör #' . $opNum);

                $sen3  = (float)$r['senaste_3_ibc'];
                $forg4 = (float)$r['forega_4_ibc'];

                // Trend: normalisera per dag (3 vs 4)
                $snittSen  = $sen3  / 3.0;
                $snittForg = ($forg4 > 0) ? ($forg4 / 4.0) : 0;

                if (abs($snittForg) < 0.0001) {
                    $trend = 'stabil';
                } elseif ($snittSen > $snittForg * 1.05) {
                    $trend = 'upp';
                } elseif ($snittSen < $snittForg * 0.95) {
                    $trend = 'ner';
                } else {
                    $trend = 'stabil';
                }

                $operatorer[] = [
                    'op_id'        => $opNum,
                    'namn'         => $namn,
                    'initialer'    => $this->initialer($namn),
                    'total_ibc'    => (int)$r['total_ibc'],
                    'snitt_ibc_per_h' => (float)($r['snitt_ibc_per_h'] ?? 0),
                    'aktiva_dagar' => (int)$r['aktiva_dagar'],
                    'trend'        => $trend,
                    'bast_dag_ibc' => (int)$r['bast_dag_ibc'],
                ];
            }

            $this->sendSuccess(['success' => true, 'operatorer' => $operatorer, 'fran' => $sjuDagarSen, 'till' => $today]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getWeekly: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta veckostats', 500);
        }
    }

    // ================================================================
    // ENDPOINT: history
    // ================================================================

    /**
     * Daglig IBC per operatör — senaste $days dagar.
     *
     * Returnerar:
     * {
     *   dates: ['2026-01-01', ...],
     *   operators: [{ op_id, namn, initialer, data: [12, 15, 0, ...] }]
     * }
     *
     * Aggregering: MAX(ibc_ok) per skiftraknare per dag (kumulativa PLC-värden),
     * sedan SUM per dag per operatör.
     */
    private function getHistory() {
        try {
            $days = isset($_GET['days']) ? max(1, min(90, (int)$_GET['days'])) : 30;
            $today = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));

            // Generera datumserien
            $dates = [];
            $cur = strtotime($fromDate);
            $end = strtotime($today);
            while ($cur <= $end) {
                $dates[] = date('Y-m-d', $cur);
                $cur = strtotime('+1 day', $cur);
            }

            // LAG-korrigerad: ibc_ok är dagligt löpande räkneverk
            $sql = "
                WITH lag_base AS (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_end,
                           MIN(op1) AS op1, MIN(op2) AS op2, MIN(op3) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= :from AND datum < DATE_ADD(:to, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ),
                lag_shifts AS (
                    SELECT dag, skiftraknare,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ibc,
                           op1, op2, op3
                    FROM lag_base
                )
                SELECT op_num, dag, SUM(delta_ibc) AS dag_ibc
                FROM (
                    SELECT op1 AS op_num, dag, delta_ibc FROM lag_shifts WHERE op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT op2 AS op_num, dag, delta_ibc FROM lag_shifts WHERE op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT op3 AS op_num, dag, delta_ibc FROM lag_shifts WHERE op3 IS NOT NULL AND op3 > 0
                ) AS sub
                GROUP BY op_num, dag
                ORDER BY op_num, dag
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $fromDate, ':to' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg lookup: op_num → [dag => ibc]
            $opDayMap = [];
            $allOps = [];
            foreach ($rows as $r) {
                $opNum = (int)$r['op_num'];
                $allOps[] = $opNum;
                $opDayMap[$opNum][$r['dag']] = (int)$r['dag_ibc'];
            }
            $allOps = array_unique($allOps);

            $nameMap = $this->getNamnMap($allOps);

            $operators = [];
            foreach ($allOps as $opNum) {
                $namn = $nameMap[$opNum] ?? ('Operatör #' . $opNum);
                $data = [];
                foreach ($dates as $dag) {
                    $data[] = $opDayMap[$opNum][$dag] ?? 0;
                }
                $operators[] = [
                    'op_id'     => $opNum,
                    'namn'      => $namn,
                    'initialer' => $this->initialer($namn),
                    'data'      => $data,
                ];
            }

            // Sortera på total IBC desc
            usort($operators, function($a, $b) {
                return array_sum($b['data']) <=> array_sum($a['data']);
            });

            $this->sendSuccess([
                'success'   => true,
                'dates'     => $dates,
                'operators' => $operators,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta historik', 500);
        }
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    /**
     * Team-stats för idag + veckan + månaden.
     *
     * Returnerar:
     * - idag_total_ibc, idag_snitt_ibc_per_h, idag_aktiva_operatorer
     * - vecka_total_ibc, vecka_snitt_ibc_per_h, vecka_bast_operatör (namn)
     * - manad_total_ibc
     */
    private function getSummary() {
        try {
            $today        = date('Y-m-d');
            // Bugfix #285: strtotime('monday this week') ger nasta mandag pa sondagar
            $mondayThisWeek = date('Y-m-d', strtotime('-' . ((int)date('N') - 1) . ' days'));
            $monthStart   = date('Y-m-01');

            // LAG-korrigerad hjälpfunktion: returnerar CTE-SQL för period
            $buildLagCte = function(string $from, string $to): string {
                return "
                    WITH lag_base AS (
                        SELECT DATE(datum) AS dag, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))      AS ibc_end,
                               MAX(COALESCE(runtime_plc, 0)) AS max_runtime,
                               MIN(op1) AS op1, MIN(op2) AS op2, MIN(op3) AS op3
                        FROM rebotling_ibc
                        WHERE datum >= '{$from}' AND datum < DATE_ADD('{$to}', INTERVAL 1 DAY)
                        GROUP BY DATE(datum), skiftraknare
                    ),
                    lag_shifts AS (
                        SELECT dag, skiftraknare,
                               GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ibc,
                               max_runtime, op1, op2, op3
                        FROM lag_base
                    ),
                    op_shifts AS (
                        SELECT op1 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op1 IS NOT NULL AND op1 > 0
                        UNION ALL
                        SELECT op2 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op2 IS NOT NULL AND op2 > 0
                        UNION ALL
                        SELECT op3 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op3 IS NOT NULL AND op3 > 0
                    )
                ";
            };

            // --- Idag ---
            $sqlIdag = $buildLagCte($today, $today) . "
                SELECT SUM(delta_ibc) AS total_ibc,
                       ROUND(SUM(delta_ibc) * 60.0 / NULLIF(SUM(max_runtime), 0), 1) AS snitt_ibc_per_h,
                       COUNT(DISTINCT op_num) AS aktiva_operatorer
                FROM op_shifts
            ";
            $stmtIdag = $this->pdo->query($sqlIdag);
            $rowIdag = $stmtIdag->fetch(PDO::FETCH_ASSOC) ?: [];

            // --- Vecka ---
            $sqlVecka = $buildLagCte($mondayThisWeek, $today) . "
                SELECT op_num, SUM(delta_ibc) AS op_total, SUM(max_runtime) AS op_runtime
                FROM op_shifts GROUP BY op_num ORDER BY op_total DESC
            ";
            $veckaRows = $this->pdo->query($sqlVecka)->fetchAll(PDO::FETCH_ASSOC);

            $veckaTotalIbc = 0;
            $veckaSumRuntime = 0.0;
            $veckaBastOpNum = null;
            foreach ($veckaRows as $vr) {
                $veckaTotalIbc  += (int)$vr['op_total'];
                $veckaSumRuntime += (float)$vr['op_runtime'];
                if ($veckaBastOpNum === null) {
                    $veckaBastOpNum = (int)$vr['op_num'];
                }
            }
            $veckaSnittIbcPerH = ($veckaSumRuntime > 0)
                ? round($veckaTotalIbc * 60.0 / $veckaSumRuntime, 1)
                : 0;

            $veckaBastNamn = null;
            if ($veckaBastOpNum !== null) {
                $nMap = $this->getNamnMap([$veckaBastOpNum]);
                $veckaBastNamn = $nMap[$veckaBastOpNum] ?? null;
            }

            // --- Månad ---
            $sqlManad = $buildLagCte($monthStart, $today) . "
                SELECT SUM(delta_ibc) AS total_ibc FROM op_shifts
            ";
            $rowManad = $this->pdo->query($sqlManad)->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->sendSuccess([
                'success'                 => true,
                'idag_total_ibc'          => (int)($rowIdag['total_ibc'] ?? 0),
                'idag_snitt_ibc_per_h'    => (float)($rowIdag['snitt_ibc_per_h'] ?? 0),
                'idag_aktiva_operatorer'  => (int)($rowIdag['aktiva_operatorer'] ?? 0),
                'vecka_total_ibc'         => $veckaTotalIbc,
                'vecka_snitt_ibc_per_h'   => $veckaSnittIbcPerH,
                'vecka_bast_operatör'     => $veckaBastNamn,
                'manad_total_ibc'         => (int)($rowManad['total_ibc'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // ================================================================
    // PERSONLIGT OPERATÖRS-DASHBOARD — NYA ENDPOINTS
    // ================================================================

    /**
     * Hämta lista med alla operatörer (för dropdown).
     */
    private function getOperatorer(): void {
        try {
            $stmt = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name ASC"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback: om operators-tabell inte har 'active'-kolumn
            if (empty($rows)) {
                $stmt = $this->pdo->query(
                    "SELECT number, name FROM operators ORDER BY name ASC"
                );
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $ops = [];
            foreach ($rows as $r) {
                $ops[] = [
                    'op_id' => (int)$r['number'],
                    'namn'  => $r['name'],
                ];
            }

            $this->sendSuccess(['success' => true, 'operatorer' => $ops]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getOperatorer: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörer', 500);
        }
    }

    /**
     * Min produktion idag — antal IBC + per timme (stapeldiagram-data).
     * ?run=min-produktion&op=<op_num>
     */
    private function getMinProduktion(): void {
        try {
            $opNum = (int)($_GET['op'] ?? 0);
            if ($opNum <= 0) {
                $this->sendError('Saknar op-parameter');
                return;
            }

            $today = date('Y-m-d');

            // Hämta IBC per timme idag för denna operatör
            $sql = "
                SELECT
                    HOUR(datum) AS timme,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                FROM (
                    SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                    WHERE op1 = :op1 AND datum >= :today1 AND datum < DATE_ADD(:today1b, INTERVAL 1 DAY) AND skiftraknare IS NOT NULL
                    UNION ALL
                    SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                    WHERE op2 = :op2 AND datum >= :today2 AND datum < DATE_ADD(:today2b, INTERVAL 1 DAY) AND skiftraknare IS NOT NULL
                    UNION ALL
                    SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                    WHERE op3 = :op3 AND datum >= :today3 AND datum < DATE_ADD(:today3b, INTERVAL 1 DAY) AND skiftraknare IS NOT NULL
                ) AS u
                GROUP BY HOUR(datum), skiftraknare
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':op1' => $opNum, ':op2' => $opNum, ':op3' => $opNum, ':today1' => $today, ':today1b' => $today, ':today2' => $today, ':today2b' => $today, ':today3' => $today, ':today3b' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aggregera: summera MAX per skifträknare, per timme
            $perTimme = [];
            foreach ($rows as $r) {
                $h = (int)$r['timme'];
                if (!isset($perTimme[$h])) $perTimme[$h] = 0;
                $perTimme[$h] += (int)$r['shift_ibc'];
            }

            // Bygg 24-timmars array
            $timmar = [];
            $ibcPerTimme = [];
            $totalIbc = 0;
            for ($h = 5; $h <= 23; $h++) {
                $timmar[] = sprintf('%02d:00', $h);
                $val = $perTimme[$h] ?? 0;
                $ibcPerTimme[] = $val;
                $totalIbc += $val;
            }

            // Hämta operatörsnamn
            $nameMap = $this->getNamnMap([$opNum]);
            $namn = $nameMap[$opNum] ?? ('Operatör #' . $opNum);

            $this->sendSuccess([
                'success'       => true,
                'total_ibc'     => $totalIbc,
                'operator_namn' => $namn,
                'timmar'        => $timmar,
                'ibc_per_timme' => $ibcPerTimme,
                'datum'         => $today,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getMinProduktion: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta produktion', 500);
        }
    }

    /**
     * Mitt tempo vs snitt — min IBC/h jämfört med genomsnitt.
     * ?run=mitt-tempo&op=<op_num>
     */
    private function getMittTempo(): void {
        try {
            $opNum = (int)($_GET['op'] ?? 0);
            if ($opNum <= 0) {
                $this->sendError('Saknar op-parameter');
                return;
            }

            $today = date('Y-m-d');

            // LAG CTE för idag (ibc_ok dagligt löpande räkneverk)
            $lagCteToday = "
                WITH lag_base AS (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS ibc_end,
                           MAX(COALESCE(runtime_plc, 0)) AS max_runtime,
                           MIN(op1) AS op1, MIN(op2) AS op2, MIN(op3) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= :today AND datum < DATE_ADD(:todayb, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ),
                lag_shifts AS (
                    SELECT dag, skiftraknare,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ibc,
                           max_runtime, op1, op2, op3
                    FROM lag_base
                )
            ";

            // Min IBC/h idag (LAG-korrigerad, filter på operator i ytterste WHERE)
            $stmtMin = $this->pdo->prepare($lagCteToday . "
                SELECT SUM(delta_ibc) AS total_ibc, SUM(max_runtime) AS total_runtime_s
                FROM lag_shifts
                WHERE op1 = :op OR op2 = :op2 OR op3 = :op3
            ");
            $stmtMin->execute([':today' => $today, ':todayb' => $today, ':op' => $opNum, ':op2' => $opNum, ':op3' => $opNum]);
            $myRow = $stmtMin->fetch(PDO::FETCH_ASSOC) ?: [];

            $myIbc = (int)($myRow['total_ibc'] ?? 0);
            $myRuntime = (float)($myRow['total_runtime_s'] ?? 0); // runtime_plc i minuter
            $myIbcPerH = ($myRuntime > 0) ? round($myIbc * 60.0 / $myRuntime, 1) : 0;

            // Snitt för alla operatörer idag (LAG-korrigerad)
            $stmtAll = $this->pdo->prepare($lagCteToday . "
                SELECT op_num, SUM(delta_ibc) AS total_ibc, SUM(max_runtime) AS total_runtime_s
                FROM (
                    SELECT op1 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT op2 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT op3 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op3 IS NOT NULL AND op3 > 0
                ) AS sub
                GROUP BY op_num
            ");
            $stmtAll->execute([':today' => $today, ':todayb' => $today]);
            $allRows = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            $sumRates = 0.0;
            $countOps = 0;
            foreach ($allRows as $ar) {
                $r = (float)$ar['total_runtime_s']; // runtime_plc i minuter
                $i = (int)$ar['total_ibc'];
                if ($r > 0 && $i > 0) {
                    $sumRates += ($i * 60.0 / $r);
                    $countOps++;
                }
            }
            $snittIbcPerH = ($countOps > 0) ? round($sumRates / $countOps, 1) : 0;

            // Procent jämfört med snitt
            $procentVsSnitt = ($snittIbcPerH > 0) ? round(($myIbcPerH / $snittIbcPerH) * 100, 0) : 0;

            $this->sendSuccess([
                'success'          => true,
                'min_ibc_per_h'    => $myIbcPerH,
                'snitt_ibc_per_h'  => $snittIbcPerH,
                'procent_vs_snitt' => $procentVsSnitt,
                'antal_operatorer' => $countOps,
                'datum'            => $today,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getMittTempo: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta tempo', 500);
        }
    }

    /**
     * Min bonus hittills — beräknad bonus med breakdown.
     * ?run=min-bonus&op=<op_num>
     *
     * Bonuslogik (från OperatorRankingController):
     *   Produktionspoäng: 10 per IBC
     *   Kvalitetsbonus:   (% godkända - 90) x 5, max 50
     *   Tempo-bonus:      Om IBC/h > snitt → (IBC/h - snitt) x 20
     *   Stopp-bonus:      0 stopp → 50p, stopptid < 10% → 30p
     */
    private function getMinBonus(): void {
        try {
            $opNum = (int)($_GET['op'] ?? 0);
            if ($opNum <= 0) {
                $this->sendError('Saknar op-parameter');
                return;
            }

            $today = date('Y-m-d');

            // Hämta min produktion idag (LAG-korrigerad)
            $lagCteBonus = "
                WITH lag_base AS (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS ibc_end,
                           MAX(COALESCE(ibc_ej_ok, 0))   AS ej_end,
                           MAX(COALESCE(runtime_plc, 0)) AS max_runtime,
                           MIN(op1) AS op1, MIN(op2) AS op2, MIN(op3) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= :today AND datum < DATE_ADD(:todayb, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), skiftraknare
                ),
                lag_shifts AS (
                    SELECT dag, skiftraknare,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ibc,
                           GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS delta_ej,
                           max_runtime, op1, op2, op3
                    FROM lag_base
                )
            ";
            $stmt = $this->pdo->prepare($lagCteBonus . "
                SELECT SUM(delta_ibc + delta_ej) AS total_ibc, SUM(delta_ibc) AS ok_ibc, SUM(max_runtime) AS total_runtime_s
                FROM lag_shifts
                WHERE op1 = :op OR op2 = :op2 OR op3 = :op3
            ");
            $stmt->execute([':today' => $today, ':todayb' => $today, ':op' => $opNum, ':op2' => $opNum, ':op3' => $opNum]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalIbc  = (int)($row['total_ibc'] ?? 0);
            $okIbc     = (int)($row['ok_ibc'] ?? 0);
            $runtimeS  = (float)($row['total_runtime_s'] ?? 0);

            // Produktionspoäng
            $produktionsPoang = $totalIbc * 10;

            // Kvalitetsbonus
            $okPct = ($totalIbc > 0) ? ($okIbc / $totalIbc * 100) : 0;
            $kvalitetsBonus = max(0, min(50, ($okPct - 90) * 5));

            // IBC/h (runtime_plc i minuter)
            $ibcPerH = ($runtimeS > 0) ? ($totalIbc * 60.0 / $runtimeS) : 0;

            // Snitt IBC/h alla idag (LAG-korrigerad)
            $stmtAll = $this->pdo->prepare($lagCteBonus . "
                SELECT op_num, SUM(delta_ibc) AS total_ibc, SUM(max_runtime) AS total_runtime_s
                FROM (
                    SELECT op1 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT op2 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT op3 AS op_num, delta_ibc, max_runtime FROM lag_shifts WHERE op3 IS NOT NULL AND op3 > 0
                ) AS sub
                GROUP BY op_num
            ");
            $stmtAll->execute([':today' => $today, ':todayb' => $today]);
            $allRows = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            $sumRates = 0.0;
            $countOps = 0;
            foreach ($allRows as $ar) {
                $r = (float)$ar['total_runtime_s']; // runtime_plc i minuter
                $i = (int)$ar['total_ibc'];
                if ($r > 0 && $i > 0) {
                    $sumRates += ($i * 60.0 / $r);
                    $countOps++;
                }
            }
            $avgIbcPerH = ($countOps > 0) ? ($sumRates / $countOps) : 0;

            // Tempo-bonus
            $tempoBonus = 0;
            if ($ibcPerH > $avgIbcPerH && $avgIbcPerH > 0) {
                $tempoBonus = round(($ibcPerH - $avgIbcPerH) * 20, 1);
            }

            // Stopp-bonus — kolla stopporsak_registreringar
            $antalStopp = 0;
            $stopptidSek = 0;
            try {
                $sqlStopp = "
                    SELECT COUNT(*) AS cnt,
                           COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_time, COALESCE(end_time, NOW()))), 0) AS sek
                    FROM stopporsak_registreringar
                    WHERE user_id = :op AND start_time >= :today AND start_time < DATE_ADD(:todayb, INTERVAL 1 DAY)
                ";
                $stStopp = $this->pdo->prepare($sqlStopp);
                $stStopp->execute([':op' => $opNum, ':today' => $today, ':todayb' => $today]);
                $stoppRow = $stStopp->fetch(PDO::FETCH_ASSOC);
                $antalStopp = (int)($stoppRow['cnt'] ?? 0);
                $stopptidSek = max(0, (int)($stoppRow['sek'] ?? 0));
            } catch (\Throwable $e) {
                error_log('OperatorDashboardController::getMinBonus stopp: ' . $e->getMessage());
            }

            $skiftSek = 8 * 3600;
            $stoppBonus = 0;
            if ($antalStopp === 0 && $totalIbc > 0) {
                $stoppBonus = 50;
            } elseif ($skiftSek > 0 && ($stopptidSek / $skiftSek) < 0.10) {
                $stoppBonus = 30;
            }

            $totalBonus = round($kvalitetsBonus + $tempoBonus + $stoppBonus, 1);
            $totalPoang = round($produktionsPoang + $totalBonus, 1);

            $this->sendSuccess([
                'success'            => true,
                'total_poang'        => $totalPoang,
                'produktions_poang'  => $produktionsPoang,
                'kvalitets_bonus'    => round($kvalitetsBonus, 1),
                'tempo_bonus'        => round($tempoBonus, 1),
                'stopp_bonus'        => $stoppBonus,
                'total_bonus'        => $totalBonus,
                'total_ibc'          => $totalIbc,
                'ok_pct'             => round($okPct, 1),
                'ibc_per_h'          => round($ibcPerH, 1),
                'snitt_ibc_per_h'    => round($avgIbcPerH, 1),
                'antal_stopp'        => $antalStopp,
                'datum'              => $today,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getMinBonus: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta bonus', 500);
        }
    }

    /**
     * Mina stopp idag — lista med stopporsaker och tid.
     * ?run=mina-stopp&op=<op_num>
     */
    private function getMinaStopp(): void {
        try {
            $opNum = (int)($_GET['op'] ?? 0);
            if ($opNum <= 0) {
                $this->sendError('Saknar op-parameter');
                return;
            }

            $today = date('Y-m-d');
            $stopp = [];
            $totalSek = 0;

            try {
                $sql = "
                    SELECT
                        sr.id,
                        COALESCE(sk.namn, sr.kommentar, 'Okänd') AS orsak,
                        sr.start_time,
                        sr.end_time,
                        TIMESTAMPDIFF(SECOND, sr.start_time, COALESCE(sr.end_time, NOW())) AS varaktighet_sek
                    FROM stopporsak_registreringar sr
                    LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                    WHERE sr.user_id = :op
                      AND sr.start_time >= :today AND sr.start_time < DATE_ADD(:todayb, INTERVAL 1 DAY)
                    ORDER BY sr.start_time DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':op' => $opNum, ':today' => $today, ':todayb' => $today]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $sek = max(0, (int)$r['varaktighet_sek']);
                    $totalSek += $sek;
                    $stopp[] = [
                        'id'              => (int)$r['id'],
                        'orsak'           => $r['orsak'],
                        'start_time'      => $r['start_time'],
                        'end_time'        => $r['end_time'],
                        'varaktighet_sek' => $sek,
                        'varaktighet_min' => round($sek / 60, 1),
                    ];
                }
            } catch (\Throwable $e) {
                error_log('OperatorDashboardController::getMinaStopp inner: ' . $e->getMessage());
            }

            $this->sendSuccess([
                'success'        => true,
                'stopp'          => $stopp,
                'antal_stopp'    => count($stopp),
                'total_stopptid_sek' => $totalSek,
                'total_stopptid_min' => round($totalSek / 60, 1),
                'datum'          => $today,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getMinaStopp: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta stopp', 500);
        }
    }

    /**
     * Min veckotrend — daglig produktion senaste 7 dagar.
     * ?run=min-veckotrend&op=<op_num>
     */
    private function getMinVeckotrend(): void {
        try {
            $opNum = (int)($_GET['op'] ?? 0);
            if ($opNum <= 0) {
                $this->sendError('Saknar op-parameter');
                return;
            }

            $today = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime('-6 days'));

            $sql = "
                SELECT dag, SUM(shift_ibc) AS dag_ibc
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM (
                        SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                        WHERE op1 = :op1 AND datum >= :from1 AND datum < DATE_ADD(:to1, INTERVAL 1 DAY) AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                        WHERE op2 = :op2 AND datum >= :from2 AND datum < DATE_ADD(:to2, INTERVAL 1 DAY) AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                        WHERE op3 = :op3 AND datum >= :from3 AND datum < DATE_ADD(:to3, INTERVAL 1 DAY) AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':op1' => $opNum, ':op2' => $opNum, ':op3' => $opNum, ':from1' => $fromDate, ':from2' => $fromDate, ':from3' => $fromDate, ':to1' => $today, ':to2' => $today, ':to3' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dagMap = [];
            foreach ($rows as $r) {
                $dagMap[$r['dag']] = (int)$r['dag_ibc'];
            }

            $dates = [];
            $values = [];
            $cur = strtotime($fromDate);
            $end = strtotime($today);
            while ($cur <= $end) {
                $d = date('Y-m-d', $cur);
                $dates[] = $d;
                $values[] = $dagMap[$d] ?? 0;
                $cur = strtotime('+1 day', $cur);
            }

            $this->sendSuccess([
                'success' => true,
                'dates'   => $dates,
                'values'  => $values,
                'from'    => $fromDate,
                'to'      => $today,
            ]);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardController::getMinVeckotrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta veckotrend', 500);
        }
    }
}
