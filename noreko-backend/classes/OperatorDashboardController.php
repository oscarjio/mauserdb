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
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Endast GET tillåtet']);
            return;
        }

        $run = trim($_GET['run'] ?? '');

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
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Okänd metod']);
        }
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
            if ($d !== '') $ini .= strtoupper(mb_substr($d, 0, 1));
        }
        return mb_substr($ini, 0, 2);
    }

    /**
     * Hämtar operatörsnamnkarta [ number => name ] för givna nummer.
     */
    private function getNamnMap(array $nums): array {
        if (empty($nums)) return [];
        $nums = array_map('intval', array_unique($nums));
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
                    WHERE DATE(datum) = :today AND op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT id AS skift_id, op2 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE DATE(datum) = :today AND op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT id AS skift_id, op3 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE DATE(datum) = :today AND op3 IS NOT NULL AND op3 > 0
                ) AS alla
                GROUP BY op_num
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':today' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode([
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
                if ($aInaktiv !== $bInaktiv) return $aInaktiv - $bInaktiv;
                return $b['ibc_per_h'] <=> $a['ibc_per_h'];
            });

            $snittIbcPerH = ($countWithRate > 0)
                ? round($sumIbcPerH / $countWithRate, 1)
                : 0;

            echo json_encode([
                'success'         => true,
                'datum'           => $today,
                'operatorer'      => $operatorer,
                'total_ibc'       => $totalIbc,
                'snitt_ibc_per_h' => $snittIbcPerH,
                'bast_namn'       => $bastNamn ?: null,
                'bast_ibc_per_h'  => $bastRate,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getToday: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörsstatus']);
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

            // UNION ALL op1/op2/op3 → per operatör, per dag, per skiftraknare
            // Steg 1: MAX per (operator, dag, skiftraknare)
            // Steg 2: SUM per (operator, dag)
            // Steg 3: SUM per operatör
            $sql = "
                SELECT
                    op_num,
                    SUM(dag_ibc)                                AS total_ibc,
                    ROUND(
                        SUM(dag_ibc) * 60.0 / NULLIF(SUM(dag_runtime_s), 0)
                    , 1)                                        AS snitt_ibc_per_h,
                    COUNT(DISTINCT dag)                         AS aktiva_dagar,
                    MAX(dag_ibc)                                AS bast_dag_ibc,
                    -- Trend: senaste 3 dagar vs föregående 4 dagar
                    SUM(CASE WHEN dag >= :tre_dagar_sen THEN dag_ibc ELSE 0 END)    AS senaste_3_ibc,
                    SUM(CASE WHEN dag <  :tre_dagar_sen THEN dag_ibc ELSE 0 END)    AS forega_4_ibc
                FROM (
                    -- Per operatör, dag: summera MAX-värdena per skiftraknare
                    SELECT op_num, dag,
                           SUM(shift_ibc)     AS dag_ibc,
                           SUM(shift_runtime) AS dag_runtime_s
                    FROM (
                        -- Steg 1: MAX per (operator, dag, skiftraknare)
                        SELECT op_num, DATE(datum) AS dag, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                               MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                        FROM (
                            SELECT op1 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                            FROM rebotling_ibc
                            WHERE op1 IS NOT NULL AND op1 > 0
                              AND DATE(datum) BETWEEN :from_date AND :to_date
                              AND skiftraknare IS NOT NULL
                            UNION ALL
                            SELECT op2 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                            FROM rebotling_ibc
                            WHERE op2 IS NOT NULL AND op2 > 0
                              AND DATE(datum) BETWEEN :from_date AND :to_date
                              AND skiftraknare IS NOT NULL
                            UNION ALL
                            SELECT op3 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                            FROM rebotling_ibc
                            WHERE op3 IS NOT NULL AND op3 > 0
                              AND DATE(datum) BETWEEN :from_date AND :to_date
                              AND skiftraknare IS NOT NULL
                        ) AS union_rows
                        GROUP BY op_num, DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY op_num, dag
                ) AS per_dag
                GROUP BY op_num
                ORDER BY total_ibc DESC
            ";

            $treDagarSen = date('Y-m-d', strtotime('-2 days')); // senaste 3 dagar inkl. idag

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from_date'     => $sjuDagarSen,
                ':to_date'       => $today,
                ':tre_dagar_sen' => $treDagarSen,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode(['success' => true, 'operatorer' => []]);
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

                if ($snittForg == 0) {
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

            echo json_encode(['success' => true, 'operatorer' => $operatorer, 'fran' => $sjuDagarSen, 'till' => $today]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getWeekly: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckostats']);
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

            // Hämta per-dag per-operatör (kumulativt korrekt)
            $sql = "
                SELECT op_num, dag, SUM(shift_ibc) AS dag_ibc
                FROM (
                    SELECT op_num, DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM (
                        SELECT op1 AS op_num, datum, skiftraknare, ibc_ok
                        FROM rebotling_ibc
                        WHERE op1 IS NOT NULL AND op1 > 0
                          AND DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op2 AS op_num, datum, skiftraknare, ibc_ok
                        FROM rebotling_ibc
                        WHERE op2 IS NOT NULL AND op2 > 0
                          AND DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op3 AS op_num, datum, skiftraknare, ibc_ok
                        FROM rebotling_ibc
                        WHERE op3 IS NOT NULL AND op3 > 0
                          AND DATE(datum) BETWEEN :from_date AND :to_date
                          AND skiftraknare IS NOT NULL
                    ) AS union_rows
                    GROUP BY op_num, DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY op_num, dag
                ORDER BY op_num, dag
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $today]);
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
                return array_sum($b['data']) - array_sum($a['data']);
            });

            echo json_encode([
                'success'   => true,
                'dates'     => $dates,
                'operators' => $operators,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getHistory: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta historik']);
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
            $mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
            $monthStart   = date('Y-m-01');

            // Hjälpfunktion: bygg UNION ALL-subquery med datumfilter för kumulativ IBC
            $buildUnion = function(string $fromParam, string $toParam): string {
                return "
                    SELECT op_num, skiftraknare, datum,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM (
                        SELECT op1 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                        FROM rebotling_ibc
                        WHERE op1 IS NOT NULL AND op1 > 0
                          AND DATE(datum) BETWEEN $fromParam AND $toParam
                          AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op2 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                        FROM rebotling_ibc
                        WHERE op2 IS NOT NULL AND op2 > 0
                          AND DATE(datum) BETWEEN $fromParam AND $toParam
                          AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op3 AS op_num, datum, skiftraknare, ibc_ok, runtime_plc
                        FROM rebotling_ibc
                        WHERE op3 IS NOT NULL AND op3 > 0
                          AND DATE(datum) BETWEEN $fromParam AND $toParam
                          AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY op_num, skiftraknare
                ";
            };

            // --- Idag ---
            $sqlIdag = "
                SELECT
                    SUM(shift_ibc)  AS total_ibc,
                    ROUND(SUM(shift_ibc) * 60.0 / NULLIF(SUM(shift_runtime), 0), 1) AS snitt_ibc_per_h,
                    COUNT(DISTINCT op_num) AS aktiva_operatorer
                FROM ({$buildUnion(':today_a', ':today_b')}) AS s
            ";
            $stmtIdag = $this->pdo->prepare($sqlIdag);
            $stmtIdag->execute([':today_a' => $today, ':today_b' => $today]);
            $rowIdag = $stmtIdag->fetch(PDO::FETCH_ASSOC) ?: [];

            // --- Vecka ---
            $sqlVecka = "
                SELECT
                    op_num,
                    SUM(shift_ibc)     AS op_total,
                    SUM(shift_runtime) AS op_runtime
                FROM ({$buildUnion(':week_a', ':week_b')}) AS s
                GROUP BY op_num
                ORDER BY op_total DESC
            ";
            $stmtVecka = $this->pdo->prepare($sqlVecka);
            $stmtVecka->execute([':week_a' => $mondayThisWeek, ':week_b' => $today]);
            $veckaRows = $stmtVecka->fetchAll(PDO::FETCH_ASSOC);

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
            $sqlManad = "
                SELECT SUM(shift_ibc) AS total_ibc
                FROM ({$buildUnion(':month_a', ':month_b')}) AS s
            ";
            $stmtManad = $this->pdo->prepare($sqlManad);
            $stmtManad->execute([':month_a' => $monthStart, ':month_b' => $today]);
            $rowManad = $stmtManad->fetch(PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success'                 => true,
                'idag_total_ibc'          => (int)($rowIdag['total_ibc'] ?? 0),
                'idag_snitt_ibc_per_h'    => (float)($rowIdag['snitt_ibc_per_h'] ?? 0),
                'idag_aktiva_operatorer'  => (int)($rowIdag['aktiva_operatorer'] ?? 0),
                'vecka_total_ibc'         => $veckaTotalIbc,
                'vecka_snitt_ibc_per_h'   => $veckaSnittIbcPerH,
                'vecka_bast_operatör'     => $veckaBastNamn,
                'manad_total_ibc'         => (int)($rowManad['total_ibc'] ?? 0),
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getSummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta sammanfattning']);
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

            echo json_encode(['success' => true, 'operatorer' => $ops]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getOperatorer: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörer']);
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
                echo json_encode(['success' => false, 'error' => 'Saknar op-parameter']);
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
                    WHERE op1 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                    UNION ALL
                    SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                    WHERE op2 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                    UNION ALL
                    SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                    WHERE op3 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                ) AS u
                GROUP BY HOUR(datum), skiftraknare
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':op' => $opNum, ':today' => $today]);
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

            echo json_encode([
                'success'       => true,
                'total_ibc'     => $totalIbc,
                'operator_namn' => $namn,
                'timmar'        => $timmar,
                'ibc_per_timme' => $ibcPerTimme,
                'datum'         => $today,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getMinProduktion: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta produktion']);
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
                echo json_encode(['success' => false, 'error' => 'Saknar op-parameter']);
                return;
            }

            $today = date('Y-m-d');

            // Min IBC/h idag
            $sqlMin = "
                SELECT
                    SUM(shift_ibc)     AS total_ibc,
                    SUM(shift_runtime) AS total_runtime_s
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM (
                        SELECT skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op1 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op2 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op3 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY skiftraknare
                ) AS per_shift
            ";
            $stmt = $this->pdo->prepare($sqlMin);
            $stmt->execute([':op' => $opNum, ':today' => $today]);
            $myRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $myIbc = (int)($myRow['total_ibc'] ?? 0);
            $myRuntime = (float)($myRow['total_runtime_s'] ?? 0);
            $myIbcPerH = ($myRuntime > 0) ? round($myIbc * 3600.0 / $myRuntime, 1) : 0;

            // Snitt för alla operatörer idag
            $sqlAll = "
                SELECT
                    op_num,
                    SUM(shift_ibc)     AS total_ibc,
                    SUM(shift_runtime) AS total_runtime_s
                FROM (
                    SELECT op_num, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM (
                        SELECT op1 AS op_num, skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op1 IS NOT NULL AND op1 > 0 AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op2 AS op_num, skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op2 IS NOT NULL AND op2 > 0 AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op3 AS op_num, skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op3 IS NOT NULL AND op3 > 0 AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY op_num, skiftraknare
                ) AS per_shift
                GROUP BY op_num
            ";
            $stmtAll = $this->pdo->prepare($sqlAll);
            $stmtAll->execute([':today' => $today]);
            $allRows = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            $sumRates = 0.0;
            $countOps = 0;
            foreach ($allRows as $ar) {
                $r = (float)$ar['total_runtime_s'];
                $i = (int)$ar['total_ibc'];
                if ($r > 0 && $i > 0) {
                    $sumRates += ($i * 3600.0 / $r);
                    $countOps++;
                }
            }
            $snittIbcPerH = ($countOps > 0) ? round($sumRates / $countOps, 1) : 0;

            // Procent jämfört med snitt
            $procentVsSnitt = ($snittIbcPerH > 0) ? round(($myIbcPerH / $snittIbcPerH) * 100, 0) : 0;

            echo json_encode([
                'success'          => true,
                'min_ibc_per_h'    => $myIbcPerH,
                'snitt_ibc_per_h'  => $snittIbcPerH,
                'procent_vs_snitt' => $procentVsSnitt,
                'antal_operatorer' => $countOps,
                'datum'            => $today,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getMittTempo: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta tempo']);
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
                echo json_encode(['success' => false, 'error' => 'Saknar op-parameter']);
                return;
            }

            $today = date('Y-m-d');

            // Hämta min produktion idag (kumulativt korrekt)
            $sqlProd = "
                SELECT
                    SUM(shift_ibc)     AS total_ibc,
                    SUM(shift_ok)      AS ok_ibc,
                    SUM(shift_runtime) AS total_runtime_s
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ibc,
                           MAX(COALESCE(ibc_ok, 0))      AS shift_ok,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM (
                        SELECT skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op1 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op2 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op3 = :op AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY skiftraknare
                ) AS ps
            ";
            $stmt = $this->pdo->prepare($sqlProd);
            $stmt->execute([':op' => $opNum, ':today' => $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalIbc  = (int)($row['total_ibc'] ?? 0);
            $okIbc     = (int)($row['ok_ibc'] ?? 0);
            $runtimeS  = (float)($row['total_runtime_s'] ?? 0);

            // Produktionspoäng
            $produktionsPoang = $totalIbc * 10;

            // Kvalitetsbonus
            $okPct = ($totalIbc > 0) ? ($okIbc / $totalIbc * 100) : 0;
            $kvalitetsBonus = max(0, min(50, ($okPct - 90) * 5));

            // IBC/h
            $ibcPerH = ($runtimeS > 0) ? ($totalIbc * 3600.0 / $runtimeS) : 0;

            // Snitt IBC/h alla idag
            $sqlAll = "
                SELECT
                    op_num,
                    SUM(shift_ibc) AS total_ibc,
                    SUM(shift_runtime) AS total_runtime_s
                FROM (
                    SELECT op_num, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM (
                        SELECT op1 AS op_num, skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op1 IS NOT NULL AND op1 > 0 AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op2 AS op_num, skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op2 IS NOT NULL AND op2 > 0 AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT op3 AS op_num, skiftraknare, ibc_ok, runtime_plc FROM rebotling_ibc
                        WHERE op3 IS NOT NULL AND op3 > 0 AND DATE(datum) = :today AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY op_num, skiftraknare
                ) AS per_shift
                GROUP BY op_num
            ";
            $stmtAll = $this->pdo->prepare($sqlAll);
            $stmtAll->execute([':today' => $today]);
            $allRows = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            $sumRates = 0.0;
            $countOps = 0;
            foreach ($allRows as $ar) {
                $r = (float)$ar['total_runtime_s'];
                $i = (int)$ar['total_ibc'];
                if ($r > 0 && $i > 0) {
                    $sumRates += ($i * 3600.0 / $r);
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
                    WHERE operator_id = :op AND DATE(start_time) = :today
                ";
                $stStopp = $this->pdo->prepare($sqlStopp);
                $stStopp->execute([':op' => $opNum, ':today' => $today]);
                $stoppRow = $stStopp->fetch(PDO::FETCH_ASSOC);
                $antalStopp = (int)($stoppRow['cnt'] ?? 0);
                $stopptidSek = max(0, (int)($stoppRow['sek'] ?? 0));
            } catch (Exception $e) {
                // tabellen kanske saknas — ignorera
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

            echo json_encode([
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
        } catch (Exception $e) {
            error_log('OperatorDashboardController getMinBonus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta bonus']);
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
                echo json_encode(['success' => false, 'error' => 'Saknar op-parameter']);
                return;
            }

            $today = date('Y-m-d');
            $stopp = [];
            $totalSek = 0;

            try {
                $sql = "
                    SELECT
                        sr.id,
                        COALESCE(sr.orsak, sr.stopporsak, 'Okänd') AS orsak,
                        sr.start_time,
                        sr.end_time,
                        TIMESTAMPDIFF(SECOND, sr.start_time, COALESCE(sr.end_time, NOW())) AS varaktighet_sek
                    FROM stopporsak_registreringar sr
                    WHERE sr.operator_id = :op
                      AND DATE(sr.start_time) = :today
                    ORDER BY sr.start_time DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':op' => $opNum, ':today' => $today]);
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
            } catch (Exception $e) {
                // tabellen kanske saknas
            }

            echo json_encode([
                'success'        => true,
                'stopp'          => $stopp,
                'antal_stopp'    => count($stopp),
                'total_stopptid_sek' => $totalSek,
                'total_stopptid_min' => round($totalSek / 60, 1),
                'datum'          => $today,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getMinaStopp: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta stopp']);
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
                echo json_encode(['success' => false, 'error' => 'Saknar op-parameter']);
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
                        WHERE op1 = :op AND DATE(datum) BETWEEN :from AND :to AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                        WHERE op2 = :op AND DATE(datum) BETWEEN :from AND :to AND skiftraknare IS NOT NULL
                        UNION ALL
                        SELECT datum, skiftraknare, ibc_ok FROM rebotling_ibc
                        WHERE op3 = :op AND DATE(datum) BETWEEN :from AND :to AND skiftraknare IS NOT NULL
                    ) AS u
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':op' => $opNum, ':from' => $fromDate, ':to' => $today]);
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

            echo json_encode([
                'success' => true,
                'dates'   => $dates,
                'values'  => $values,
                'from'    => $fromDate,
                'to'      => $today,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getMinVeckotrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckotrend']);
        }
    }
}
