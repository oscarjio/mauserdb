<?php
/**
 * BonusController.php
 * Hanterar alla API-endpoints för bonussystemet
 *
 * VIKTIGT: ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, rasttime är KUMULATIVA
 * PLC-värden per skift (de växer med varje cykel inom skiftet). Aggregering
 * måste därför ske i två steg:
 *   1. Per skiftraknare: MAX() för kumulativa fält, LAST() för KPI-fält
 *   2. Över skift: SUM() / AVG() på de korrigerade per-skift-värdena
 *
 * Endpoints:
 * - ?action=bonus&run=operator&id=<op_id>     → Operatörsprestationer
 * - ?action=bonus&run=ranking                 → Top N ranking
 * - ?action=bonus&run=team                    → Team-översikt per skift
 * - ?action=bonus&run=kpis&id=<op_id>         → KPI-trenddata för operatör
 * - ?action=bonus&run=history&id=<op_id>      → Råhistorik för operatör
 * - ?action=bonus&run=summary                 → Dagens sammanfattning
 */

class BonusController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        session_start();

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad']);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = $_GET['run'] ?? '';

        if ($method !== 'GET') {
            $this->sendError('Endast GET-requests stöds');
            return;
        }

        switch ($run) {
            case 'operator': $this->getOperatorStats();   break;
            case 'ranking':  $this->getRanking();         break;
            case 'team':     $this->getTeamStats();       break;
            case 'kpis':     $this->getKPIDetails();      break;
            case 'history':  $this->getOperatorHistory(); break;
            case 'summary':  $this->getDailySummary();    break;
            default: $this->sendError('Ogiltig action: ' . $run);
        }
    }

    // ================================================================
    // PRIVATA HJÄLPFUNKTIONER FÖR KUMULATIVA SUBQUERIES
    // ================================================================

    /**
     * Returnerar SQL-fragment som aggregerar en operatörs skift korrekt.
     * Kumulativa fält hämtas med MAX(), KPI-fält med sista cykelns värde.
     *
     * @param string $opFilter   SQL-villkor för operatör, t.ex. "(op1=:op_id OR op2=:op_id OR op3=:op_id)"
     * @param string $dateFilter Returnerat av getDateFilter()
     */
    private function perShiftSubquery(string $opFilter, string $dateFilter): string {
        return "
            SELECT
                skiftraknare,
                MAX(ibc_ok)     AS shift_ibc_ok,
                MAX(ibc_ej_ok)  AS shift_ibc_ej_ok,
                MAX(bur_ej_ok)  AS shift_bur_ej_ok,
                MAX(runtime_plc) AS shift_runtime,
                MAX(rasttime)   AS shift_rasttime,
                SUBSTRING_INDEX(GROUP_CONCAT(effektivitet  ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_effektivitet,
                SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_produktivitet,
                SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet,
                SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                MIN(datum) AS first_datum,
                MAX(datum) AS last_datum
            FROM rebotling_ibc
            WHERE $opFilter
              AND skiftraknare IS NOT NULL
              AND $dateFilter
            GROUP BY skiftraknare
        ";
    }

    /**
     * Returnerar per-skift-subquery för EN specifik position (op1/op2/op3),
     * utan opFilter (filtrerar på NOT NULL/> 0 för positionen).
     */
    private function perShiftByPosition(int $pos, string $dateFilter): string {
        return "
            SELECT
                op{$pos}        AS operator_id,
                skiftraknare,
                MAX(ibc_ok)     AS shift_ibc_ok,
                MAX(runtime_plc) AS shift_runtime,
                SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                SUBSTRING_INDEX(GROUP_CONCAT(effektivitet  ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_eff,
                SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_prod,
                SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kval
            FROM rebotling_ibc
            WHERE op{$pos} IS NOT NULL AND op{$pos} > 0
              AND skiftraknare IS NOT NULL
              AND $dateFilter
            GROUP BY op{$pos}, skiftraknare
        ";
    }

    // ================================================================
    // ENDPOINTS
    // ================================================================

    /**
     * GET /api.php?action=bonus&run=operator&id=<op_id>&period=week|month|all
     */
    private function getOperatorStats() {
        $op_id      = $_GET['id']     ?? null;
        $period     = $_GET['period'] ?? 'week';
        $start_date = $_GET['start']  ?? null;
        $end_date   = $_GET['end']    ?? null;

        if (!$op_id) {
            $this->sendError('Operatör-ID saknas (id)');
            return;
        }

        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);
        $opFilter   = "(op1 = :op_id OR op2 = :op_id OR op3 = :op_id)";

        try {
            // Steg 1+2: per-skift MAX(), sedan aggregera över skift
            $inner = $this->perShiftSubquery($opFilter, $dateFilter);
            $stmt  = $this->pdo->prepare("
                SELECT
                    COUNT(*)                   AS total_shifts,
                    SUM(shift_ibc_ok)          AS total_ibc_ok,
                    SUM(shift_ibc_ej_ok)       AS total_ibc_ej_ok,
                    SUM(shift_bur_ej_ok)       AS total_bur_ej_ok,
                    SUM(shift_runtime)         AS total_runtime,
                    SUM(shift_rasttime)        AS total_rasttime,
                    AVG(last_effektivitet)     AS avg_effektivitet,
                    AVG(last_produktivitet)    AS avg_produktivitet,
                    AVG(last_kvalitet)         AS avg_kvalitet,
                    AVG(last_bonus)            AS avg_bonus,
                    MAX(last_bonus)            AS max_bonus,
                    MIN(last_bonus)            AS min_bonus,
                    DATE(MIN(first_datum))     AS first_date,
                    DATE(MAX(last_datum))      AS last_date
                FROM ($inner) AS per_shift
            ");
            $stmt->execute(['op_id' => $op_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats || (int)($stats['total_shifts'] ?? 0) === 0) {
                $this->sendError('Ingen data hittades för operatör ' . $op_id);
                return;
            }

            // Daglig breakdown: per datum + skiftraknare → sedan per datum
            $stmt = $this->pdo->prepare("
                SELECT
                    date,
                    COUNT(*)             AS shifts,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    AVG(last_effektivitet)  AS effektivitet,
                    AVG(last_produktivitet) AS produktivitet,
                    AVG(last_kvalitet)      AS kvalitet,
                    AVG(last_bonus)         AS bonus_poang
                FROM (
                    SELECT
                        DATE(datum)     AS date,
                        skiftraknare,
                        MAX(ibc_ok)     AS shift_ibc_ok,
                        MAX(ibc_ej_ok)  AS shift_ibc_ej_ok,
                        SUBSTRING_INDEX(GROUP_CONCAT(effektivitet  ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_effektivitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_produktivitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE $opFilter
                      AND skiftraknare IS NOT NULL
                      AND $dateFilter
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY date
                ORDER BY date DESC
                LIMIT 30
            ");
            $stmt->execute(['op_id' => $op_id]);
            $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Primärposition (den position operatören jobbat mest)
            $position = $this->getOperatorPrimaryPosition($op_id, $dateFilter);

            $total_hours      = round(($stats['total_runtime']  ?? 0) / 60, 1);
            $total_rast_hours = round(($stats['total_rasttime'] ?? 0) / 60, 1);

            $this->sendSuccess([
                'operator_id' => (int)$op_id,
                'position'    => $position,
                'period'      => $period,
                'date_range'  => [
                    'from' => $stats['first_date'],
                    'to'   => $stats['last_date'],
                ],
                'summary' => [
                    'total_shifts'   => (int)($stats['total_shifts']   ?? 0),
                    'total_cycles'   => (int)($stats['total_shifts']   ?? 0), // alias
                    'total_ibc_ok'   => (int)($stats['total_ibc_ok']   ?? 0),
                    'total_ibc_ej_ok'=> (int)($stats['total_ibc_ej_ok']?? 0),
                    'total_bur_ej_ok'=> (int)($stats['total_bur_ej_ok']?? 0),
                    'total_hours'    => $total_hours,
                    'total_rast_hours'=> $total_rast_hours,
                ],
                'kpis' => [
                    'effektivitet'  => round($stats['avg_effektivitet']  ?? 0, 2),
                    'produktivitet' => round($stats['avg_produktivitet'] ?? 0, 2),
                    'kvalitet'      => round($stats['avg_kvalitet']      ?? 0, 2),
                    'bonus'         => round($stats['avg_bonus']         ?? 0, 2), // frontend-nyckel
                    'bonus_avg'     => round($stats['avg_bonus']         ?? 0, 2), // bakåtkompatibilitet
                    'bonus_max'     => round($stats['max_bonus']         ?? 0, 2),
                    'bonus_min'     => round($stats['min_bonus']         ?? 0, 2),
                ],
                'daily_breakdown' => array_map(function ($row) {
                    return [
                        'date'        => $row['date'],
                        'shifts'      => (int)$row['shifts'],
                        'cycles'      => (int)$row['shifts'],
                        'ibc_ok'      => (int)($row['ibc_ok']    ?? 0),
                        'ibc_ej_ok'   => (int)($row['ibc_ej_ok'] ?? 0),
                        'effektivitet'  => round($row['effektivitet']  ?? 0, 2),
                        'produktivitet' => round($row['produktivitet'] ?? 0, 2),
                        'kvalitet'      => round($row['kvalitet']      ?? 0, 2),
                        'bonus_poang'   => round($row['bonus_poang']   ?? 0, 2),
                    ];
                }, $daily),
            ]);

        } catch (PDOException $e) {
            error_log('Bonus getOperatorStats error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * GET /api.php?action=bonus&run=ranking&period=week|month&limit=10
     */
    private function getRanking() {
        $period     = $_GET['period'] ?? 'week';
        $limit      = min((int)($_GET['limit'] ?? 10), 100);
        $start_date = $_GET['start']  ?? null;
        $end_date   = $_GET['end']    ?? null;

        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);

        try {
            $rankings = [];

            // Per-position ranking
            for ($pos = 1; $pos <= 3; $pos++) {
                $inner = $this->perShiftByPosition($pos, $dateFilter);
                $stmt  = $this->pdo->prepare("
                    SELECT
                        operator_id,
                        COUNT(*)           AS shifts,
                        AVG(last_bonus)    AS avg_bonus,
                        AVG(last_eff)      AS avg_effektivitet,
                        AVG(last_prod)     AS avg_produktivitet,
                        AVG(last_kval)     AS avg_kvalitet,
                        SUM(shift_ibc_ok)  AS total_ibc_ok,
                        SUM(shift_runtime) AS total_runtime
                    FROM ($inner) AS per_shift
                    GROUP BY operator_id
                    HAVING shifts >= 1
                    ORDER BY avg_bonus DESC
                    LIMIT {$limit}
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rankings["position_{$pos}"] = array_map(function ($row, $index) use ($pos) {
                    return [
                        'rank'        => $index + 1,
                        'operator_id' => (int)$row['operator_id'],
                        'position'    => $this->getPositionName($pos),
                        'shifts'      => (int)$row['shifts'],
                        'cycles'      => (int)$row['shifts'],
                        'bonus_avg'   => round($row['avg_bonus']        ?? 0, 2),
                        'effektivitet'  => round($row['avg_effektivitet']  ?? 0, 2),
                        'produktivitet' => round($row['avg_produktivitet'] ?? 0, 2),
                        'kvalitet'      => round($row['avg_kvalitet']      ?? 0, 2),
                        'total_ibc_ok'  => (int)($row['total_ibc_ok']  ?? 0),
                        'total_hours'   => round(($row['total_runtime'] ?? 0) / 60, 1),
                    ];
                }, $results, array_keys($results));
            }

            // Kombinerad ranking (alla positioner)
            $s1 = $this->perShiftByPosition(1, $dateFilter);
            $s2 = $this->perShiftByPosition(2, $dateFilter);
            $s3 = $this->perShiftByPosition(3, $dateFilter);

            $stmt = $this->pdo->prepare("
                SELECT
                    operator_id,
                    SUM(shifts)        AS total_shifts,
                    AVG(avg_bonus)     AS avg_bonus,
                    AVG(avg_eff)       AS avg_effektivitet,
                    AVG(avg_prod)      AS avg_produktivitet,
                    AVG(avg_kval)      AS avg_kvalitet,
                    SUM(total_ibc_ok)  AS total_ibc_ok,
                    SUM(total_runtime) AS total_runtime
                FROM (
                    SELECT operator_id,
                           COUNT(*) AS shifts, AVG(last_bonus) AS avg_bonus,
                           AVG(last_eff) AS avg_eff, AVG(last_prod) AS avg_prod,
                           AVG(last_kval) AS avg_kval,
                           SUM(shift_ibc_ok) AS total_ibc_ok, SUM(shift_runtime) AS total_runtime
                    FROM ($s1) AS x1 GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           COUNT(*) AS shifts, AVG(last_bonus) AS avg_bonus,
                           AVG(last_eff) AS avg_eff, AVG(last_prod) AS avg_prod,
                           AVG(last_kval) AS avg_kval,
                           SUM(shift_ibc_ok) AS total_ibc_ok, SUM(shift_runtime) AS total_runtime
                    FROM ($s2) AS x2 GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           COUNT(*) AS shifts, AVG(last_bonus) AS avg_bonus,
                           AVG(last_eff) AS avg_eff, AVG(last_prod) AS avg_prod,
                           AVG(last_kval) AS avg_kval,
                           SUM(shift_ibc_ok) AS total_ibc_ok, SUM(shift_runtime) AS total_runtime
                    FROM ($s3) AS x3 GROUP BY operator_id
                ) AS combined
                GROUP BY operator_id
                HAVING total_shifts >= 2
                ORDER BY avg_bonus DESC
                LIMIT {$limit}
            ");
            $stmt->execute();
            $combined = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rankings['overall'] = array_map(function ($row, $index) {
                return [
                    'rank'          => $index + 1,
                    'operator_id'   => (int)$row['operator_id'],
                    'total_shifts'  => (int)$row['total_shifts'],
                    'total_cycles'  => (int)$row['total_shifts'],
                    'bonus_avg'     => round($row['avg_bonus']        ?? 0, 2),
                    'effektivitet'  => round($row['avg_effektivitet']  ?? 0, 2),
                    'produktivitet' => round($row['avg_produktivitet'] ?? 0, 2),
                    'kvalitet'      => round($row['avg_kvalitet']      ?? 0, 2),
                    'total_ibc_ok'  => (int)($row['total_ibc_ok']  ?? 0),
                    'total_hours'   => round(($row['total_runtime'] ?? 0) / 60, 1),
                ];
            }, $combined, array_keys($combined));

            $this->sendSuccess([
                'period'   => $period,
                'limit'    => $limit,
                'rankings' => $rankings,
            ]);

        } catch (PDOException $e) {
            error_log('Bonus getRanking error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * GET /api.php?action=bonus&run=team&period=week|month
     */
    private function getTeamStats() {
        $period     = $_GET['period'] ?? 'week';
        $start_date = $_GET['start']  ?? null;
        $end_date   = $_GET['end']    ?? null;

        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);

        try {
            // Per skift: MAX för kumulativa fält, sista cykelns bonus
            $stmt = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    COUNT(*)    AS cycles,
                    MAX(ibc_ok)     AS total_ibc_ok,
                    MAX(ibc_ej_ok)  AS total_ibc_ej_ok,
                    MAX(bur_ej_ok)  AS total_bur_ej_ok,
                    AVG(effektivitet)  AS avg_effektivitet,
                    AVG(produktivitet) AS avg_produktivitet,
                    AVG(kvalitet)      AS avg_kvalitet,
                    SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                    MAX(runtime_plc) AS total_runtime,
                    DATE(MIN(datum)) AS shift_start,
                    DATE(MAX(datum)) AS shift_end,
                    GROUP_CONCAT(DISTINCT op1) AS operators_1,
                    GROUP_CONCAT(DISTINCT op2) AS operators_2,
                    GROUP_CONCAT(DISTINCT op3) AS operators_3
                FROM rebotling_ibc
                WHERE skiftraknare IS NOT NULL
                  AND $dateFilter
                GROUP BY skiftraknare
                ORDER BY skiftraknare DESC
                LIMIT 50
            ");
            $stmt->execute();
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $team_stats = array_map(function ($row) {
                $ops = array_merge(
                    $row['operators_1'] ? explode(',', $row['operators_1']) : [],
                    $row['operators_2'] ? explode(',', $row['operators_2']) : [],
                    $row['operators_3'] ? explode(',', $row['operators_3']) : []
                );
                $unique_operators = array_values(array_unique(array_filter($ops)));

                return [
                    'shift_number'   => (int)$row['skiftraknare'],
                    'shift_start'    => $row['shift_start'],
                    'shift_end'      => $row['shift_end'],
                    'operators'      => array_map('intval', $unique_operators),
                    'operator_count' => count($unique_operators),
                    'cycles'         => (int)$row['cycles'],
                    'total_ibc_ok'   => (int)($row['total_ibc_ok']    ?? 0),
                    'total_ibc_ej_ok'=> (int)($row['total_ibc_ej_ok'] ?? 0),
                    'total_bur_ej_ok'=> (int)($row['total_bur_ej_ok'] ?? 0),
                    'total_hours'    => round(($row['total_runtime'] ?? 0) / 60, 1),
                    'kpis' => [
                        'effektivitet'  => round($row['avg_effektivitet']  ?? 0, 2),
                        'produktivitet' => round($row['avg_produktivitet'] ?? 0, 2),
                        'kvalitet'      => round($row['avg_kvalitet']      ?? 0, 2),
                        'bonus_avg'     => round($row['last_bonus']        ?? 0, 2),
                        'bonus'         => round($row['last_bonus']        ?? 0, 2),
                    ],
                ];
            }, $shifts);

            // Aggregerat via korrekt per-skift-subquery
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc_ok) AS total_ibc_ok,
                    AVG(last_bonus)   AS avg_bonus
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(ibc_ok) AS shift_ibc_ok,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL AND $dateFilter
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute();
            $corrAgg = $stmt->fetch(PDO::FETCH_ASSOC);

            // Råräkningar (cycles, skift, unika operatörer)
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*)                 AS total_cycles,
                    COUNT(DISTINCT skiftraknare) AS total_shifts,
                    COUNT(DISTINCT op1) + COUNT(DISTINCT op2) + COUNT(DISTINCT op3) AS unique_operators
                FROM rebotling_ibc
                WHERE $dateFilter
            ");
            $stmt->execute();
            $rawAgg = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'period'    => $period,
                'aggregate' => [
                    'total_shifts'     => (int)($rawAgg['total_shifts']    ?? 0),
                    'total_cycles'     => (int)($rawAgg['total_cycles']    ?? 0),
                    'total_ibc_ok'     => (int)($corrAgg['total_ibc_ok']   ?? 0),
                    'avg_bonus'        => round($corrAgg['avg_bonus']       ?? 0, 2),
                    'unique_operators' => (int)($rawAgg['unique_operators'] ?? 0),
                ],
                'shifts' => $team_stats,
            ]);

        } catch (PDOException $e) {
            error_log('Bonus getTeamStats error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * GET /api.php?action=bonus&run=kpis&id=<op_id>&period=week
     *
     * KPI-trenddata per datum (för Chart.js). Kumulativa fält fixas per skift.
     */
    private function getKPIDetails() {
        $op_id  = $_GET['id']     ?? null;
        $period = $_GET['period'] ?? 'week';

        if (!$op_id) {
            $this->sendError('Operatör-ID saknas');
            return;
        }

        $dateFilter = $this->getDateFilter($period);
        $opFilter   = "(op1 = :op_id OR op2 = :op_id OR op3 = :op_id)";

        try {
            // Per datum + skiftraknare → sedan per datum
            $stmt = $this->pdo->prepare("
                SELECT
                    date,
                    COUNT(*)             AS shifts,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_bur_ej_ok) AS bur_ej_ok,
                    AVG(last_effektivitet)  AS effektivitet,
                    AVG(last_produktivitet) AS produktivitet,
                    AVG(last_kvalitet)      AS kvalitet,
                    AVG(last_bonus)         AS bonus_poang
                FROM (
                    SELECT
                        DATE(datum)     AS date,
                        skiftraknare,
                        MAX(ibc_ok)     AS shift_ibc_ok,
                        MAX(ibc_ej_ok)  AS shift_ibc_ej_ok,
                        MAX(bur_ej_ok)  AS shift_bur_ej_ok,
                        SUBSTRING_INDEX(GROUP_CONCAT(effektivitet  ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_effektivitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_produktivitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE $opFilter
                      AND skiftraknare IS NOT NULL
                      AND $dateFilter
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY date
                ORDER BY date ASC
            ");
            $stmt->execute(['op_id' => $op_id]);
            $daily_kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $chart_data = [
                'labels' => array_column($daily_kpis, 'date'),
                'datasets' => [
                    [
                        'label'           => 'Effektivitet (%)',
                        'data'            => array_map(fn($r) => round($r['effektivitet']  ?? 0, 2), $daily_kpis),
                        'borderColor'     => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    ],
                    [
                        'label'           => 'Produktivitet (IBC/h)',
                        'data'            => array_map(fn($r) => round($r['produktivitet'] ?? 0, 2), $daily_kpis),
                        'borderColor'     => 'rgb(54, 162, 235)',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    ],
                    [
                        'label'           => 'Kvalitet (%)',
                        'data'            => array_map(fn($r) => round($r['kvalitet']      ?? 0, 2), $daily_kpis),
                        'borderColor'     => 'rgb(255, 206, 86)',
                        'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    ],
                ],
            ];

            $this->sendSuccess([
                'operator_id' => (int)$op_id,
                'period'      => $period,
                'chart_data'  => $chart_data,
                'raw_data'    => $daily_kpis,
            ]);

        } catch (PDOException $e) {
            error_log('Bonus getKPIDetails error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * GET /api.php?action=bonus&run=history&id=<op_id>&limit=50
     *
     * Råhistorik per cykel (sista raden per skift är mest representativ).
     * Returnerar rader i kronologisk ordning — inga kumulativa aggregeringar behövs
     * här eftersom det är enskilda cyklar som visas.
     */
    private function getOperatorHistory() {
        $op_id = $_GET['id']    ?? null;
        $limit = min((int)($_GET['limit'] ?? 50), 500);

        if (!$op_id) {
            $this->sendError('Operatör-ID saknas');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    datum, lopnummer, skiftraknare, produkt,
                    ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc,
                    effektivitet, produktivitet, kvalitet, bonus_poang,
                    CASE
                        WHEN op1 = :op_id THEN 'Tvättplats'
                        WHEN op2 = :op_id THEN 'Kontrollstation'
                        WHEN op3 = :op_id THEN 'Truckförare'
                    END AS position
                FROM rebotling_ibc
                WHERE op1 = :op_id OR op2 = :op_id OR op3 = :op_id
                ORDER BY datum DESC
                LIMIT {$limit}
            ");
            $stmt->execute(['op_id' => $op_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'operator_id' => (int)$op_id,
                'count'       => count($history),
                'history'     => array_map(function ($row) {
                    return [
                        'datum'      => $row['datum'],
                        'lopnummer'  => (int)($row['lopnummer']   ?? 0),
                        'shift'      => (int)($row['skiftraknare']?? 0),
                        'position'   => $row['position'],
                        'produkt'    => (int)($row['produkt']     ?? 0),
                        'ibc_ok'     => (int)($row['ibc_ok']      ?? 0),
                        'ibc_ej_ok'  => (int)($row['ibc_ej_ok']   ?? 0),
                        'bur_ej_ok'  => (int)($row['bur_ej_ok']   ?? 0),
                        'runtime'    => (int)($row['runtime_plc']  ?? 0),
                        'kpis' => [
                            'effektivitet'  => round($row['effektivitet']  ?? 0, 2),
                            'produktivitet' => round($row['produktivitet'] ?? 0, 2),
                            'kvalitet'      => round($row['kvalitet']      ?? 0, 2),
                            'bonus'         => round($row['bonus_poang']   ?? 0, 2),
                        ],
                    ];
                }, $history),
            ]);

        } catch (PDOException $e) {
            error_log('Bonus getOperatorHistory error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * GET /api.php?action=bonus&run=summary
     *
     * Dagens sammanfattning. Korrigerar ibc_ok via per-skift-subquery.
     */
    private function getDailySummary() {
        try {
            // Korrekt ibc_ok och bonus via per-skift-aggregering
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc_ok)    AS total_ibc_ok,
                    SUM(shift_ibc_ej_ok) AS total_ibc_ej_ok,
                    AVG(last_bonus)      AS avg_bonus,
                    MAX(last_bonus)      AS max_bonus,
                    COUNT(*)             AS shifts_today
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(ibc_ok)    AS shift_ibc_ok,
                        MAX(ibc_ej_ok) AS shift_ibc_ej_ok,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE DATE(datum) = CURDATE()
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute();
            $corrSummary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Råräkningar (cycles, unika operatörer)
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total_cycles,
                    COUNT(DISTINCT op1) AS unique_op1,
                    COUNT(DISTINCT op2) AS unique_op2,
                    COUNT(DISTINCT op3) AS unique_op3
                FROM rebotling_ibc
                WHERE DATE(datum) = CURDATE()
            ");
            $stmt->execute();
            $rawSummary = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'date'           => date('Y-m-d'),
                'total_cycles'   => (int)($rawSummary['total_cycles']    ?? 0),
                'shifts_today'   => (int)($corrSummary['shifts_today']   ?? 0),
                'total_ibc_ok'   => (int)($corrSummary['total_ibc_ok']   ?? 0),
                'total_ibc_ej_ok'=> (int)($corrSummary['total_ibc_ej_ok']?? 0),
                'avg_bonus'      => round($corrSummary['avg_bonus']       ?? 0, 2),
                'max_bonus'      => round($corrSummary['max_bonus']       ?? 0, 2),
                'unique_operators' => [
                    'tvattplats' => (int)($rawSummary['unique_op1'] ?? 0),
                    'kontroll'   => (int)($rawSummary['unique_op2'] ?? 0),
                    'truck'      => (int)($rawSummary['unique_op3'] ?? 0),
                ],
            ]);

        } catch (PDOException $e) {
            error_log('Bonus getDailySummary error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDateFilter($period, $start = null, $end = null): string {
        if ($start && $end) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                return "1=0";
            }
            return "DATE(datum) BETWEEN '{$start}' AND '{$end}'";
        }

        switch ($period) {
            case 'today': return "DATE(datum) = CURDATE()";
            case 'week':  return "datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month': return "datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'year':  return "datum >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            default:      return "1=1";
        }
    }

    /**
     * Returnerar den position en operatör jobbat mest på under perioden.
     */
    private function getOperatorPrimaryPosition(string $op_id, string $dateFilter): string {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(op1 = :op_id) AS count_1,
                    SUM(op2 = :op_id) AS count_2,
                    SUM(op3 = :op_id) AS count_3
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                  AND $dateFilter
            ");
            $stmt->execute(['op_id' => $op_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $c1 = (int)($row['count_1'] ?? 0);
            $c2 = (int)($row['count_2'] ?? 0);
            $c3 = (int)($row['count_3'] ?? 0);

            if ($c1 >= $c2 && $c1 >= $c3) return 'Tvättplats';
            if ($c2 >= $c1 && $c2 >= $c3) return 'Kontrollstation';
            return 'Truckförare';
        } catch (PDOException $e) {
            return 'Okänd';
        }
    }

    private function getPositionName(int $pos): string {
        return [1 => 'Tvättplats', 2 => 'Kontrollstation', 3 => 'Truckförare'][$pos] ?? 'Okänd';
    }

    private function sendSuccess($data): void {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private function sendError(string $message): void {
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
