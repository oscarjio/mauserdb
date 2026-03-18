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
 * - ?action=bonus&run=weekly_history&id=<op_id> → Bonuspoäng per ISO-vecka, senaste 8 veckorna
 */

class BonusController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        // POST-endpoints
        if ($method === 'POST') {
            switch ($run) {
                case 'simulate': $this->simulate(); break;
                default: $this->sendError('Ogiltig POST-action: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
            }
            return;
        }

        if ($method !== 'GET') {
            $this->sendError('Endast GET- och POST-requests stöds', 405);
            return;
        }

        switch ($run) {
            case 'operator':       $this->getOperatorStats();     break;
            case 'ranking':        $this->getRanking();           break;
            case 'team':           $this->getTeamStats();         break;
            case 'kpis':           $this->getKPIDetails();        break;
            case 'history':        $this->getOperatorHistory();   break;
            case 'summary':        $this->getDailySummary();      break;
            case 'weekly_history': $this->getWeeklyHistory();     break;
            case 'hall-of-fame':   $this->getHallOfFame();        break;
            case 'loneprognos':    $this->getLoneprognos();       break;
            case 'personal-best':  $this->getPersonalBest();      break;
            case 'streak':         $this->getStreak();            break;
            case 'my-ranking':        $this->getMyRanking();         break;
            case 'week-trend':        $this->getWeekTrend();         break;
            case 'ranking-position':  $this->getRankingPosition();   break;
            case 'achievements':      $this->getAchievements();      break;
            case 'peer-ranking':      $this->getPeerRanking();       break;
            default: $this->sendError('Ogiltig action: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
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
        $op_id      = isset($_GET['id']) ? intval($_GET['id']) : null;
        $period     = trim($_GET['period'] ?? 'week');
        $start_date = isset($_GET['start']) ? trim($_GET['start']) : null;
        $end_date   = isset($_GET['end'])   ? trim($_GET['end'])   : null;

        // Whitelist-validering av $period
        $allowed_periods = ['today', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $allowed_periods, true)) {
            $period = 'week';
        }

        if (!$op_id || $op_id <= 0) {
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

            // Hämta operatörens namn
            $opNameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $opNameStmt->execute([$op_id]);
            $opName = $opNameStmt->fetchColumn() ?: null;

            $total_hours      = round(($stats['total_runtime']  ?? 0) / 60, 1);
            $total_rast_hours = round(($stats['total_rasttime'] ?? 0) / 60, 1);

            $this->sendSuccess([
                'operator_id'   => (int)$op_id,
                'operator_name' => $opName,
                'position'      => $position,
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
            error_log('BonusController::getOperatorStats: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=ranking&period=week|month&limit=10
     */
    private function getRanking() {
        $period     = trim($_GET['period'] ?? 'week');
        $limit      = max(1, min((int)($_GET['limit'] ?? 10), 100));
        $start_date = isset($_GET['start']) ? trim($_GET['start']) : null;
        $end_date   = isset($_GET['end'])   ? trim($_GET['end'])   : null;

        // Whitelist-validering av $period
        $allowed_periods = ['today', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $allowed_periods, true)) {
            $period = 'week';
        }

        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);

        try {
            // Hämta operatörsnamn för lookup
            $opRows = $this->pdo->query("SELECT number, name FROM operators")->fetchAll(PDO::FETCH_KEY_PAIR);

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
                    LIMIT " . (int)$limit . "
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rankings["position_{$pos}"] = array_map(function ($row, $index) use ($pos, $opRows) {
                    $opId = (int)$row['operator_id'];
                    return [
                        'rank'          => $index + 1,
                        'operator_id'   => $opId,
                        'operator_name' => $opRows[$opId] ?? null,
                        'position'      => $this->getPositionName($pos),
                        'shifts'        => (int)$row['shifts'],
                        'cycles'        => (int)$row['shifts'],
                        'bonus_avg'     => round($row['avg_bonus']        ?? 0, 2),
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
                LIMIT " . (int)$limit . "
            ");
            $stmt->execute();
            $combined = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rankings['overall'] = array_map(function ($row, $index) use ($opRows) {
                $opId = (int)$row['operator_id'];
                return [
                    'rank'          => $index + 1,
                    'operator_id'   => $opId,
                    'operator_name' => $opRows[$opId] ?? null,
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
            error_log('BonusController::getRanking: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=team&period=week|month
     */
    private function getTeamStats() {
        $period     = trim($_GET['period'] ?? 'week');
        $start_date = isset($_GET['start']) ? trim($_GET['start']) : null;
        $end_date   = isset($_GET['end'])   ? trim($_GET['end'])   : null;

        // Whitelist-validering av $period
        $allowed_periods = ['today', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $allowed_periods, true)) {
            $period = 'week';
        }

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
            error_log('BonusController::getTeamStats: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=kpis&id=<op_id>&period=week
     *
     * KPI-trenddata per datum (för Chart.js). Kumulativa fält fixas per skift.
     */
    private function getKPIDetails() {
        $op_id  = isset($_GET['id']) ? intval($_GET['id']) : null;
        $period = trim($_GET['period'] ?? 'week');

        // Whitelist-validering av $period
        $allowed_periods = ['today', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $allowed_periods, true)) {
            $period = 'week';
        }

        if (!$op_id || $op_id <= 0) {
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
            error_log('BonusController::getKPIDetails: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
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
        $op_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $limit = max(1, min((int)($_GET['limit'] ?? 50), 500));

        if (!$op_id || $op_id <= 0) {
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
                LIMIT " . (int)$limit . "
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
            error_log('BonusController::getOperatorHistory: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
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
            error_log('BonusController::getDailySummary: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=weekly_history&id=<op_id>
     *
     * Returnerar bonuspoäng (snitt per skift) per ISO-vecka för de senaste 8 veckorna.
     * Aggregering: MAX() per skiftraknare (kumulativt fält), sedan AVG() per vecka.
     * Returnerar även teamets snitt för varje vecka (alla operatörer).
     * Returnerar IBC/h-snitt och kvalitet%-snitt per vecka för jämförelse.
     */
    private function getWeeklyHistory() {
        $op_id = isset($_GET['id']) ? intval($_GET['id']) : null;

        if (!$op_id || $op_id <= 0) {
            $this->sendError('Operatör-ID saknas (id)');
            return;
        }

        try {
            // --- Operatörens bonuspoäng per vecka (senaste 8 ISO-veckor) ---
            $opFilter = "(op1 = :op_id OR op2 = :op_id OR op3 = :op_id)";
            $stmt = $this->pdo->prepare("
                SELECT
                    YEARWEEK(first_datum, 3) AS yearweek,
                    YEAR(first_datum)        AS yr,
                    WEEK(first_datum, 3)     AS wk,
                    AVG(last_bonus)          AS avg_bonus,
                    AVG(last_produktivitet)  AS avg_ibc_per_hour,
                    AVG(last_kvalitet)       AS avg_kvalitet,
                    COUNT(*)                 AS shifts
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum) AS first_datum,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                        SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_produktivitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet
                    FROM rebotling_ibc
                    WHERE $opFilter
                      AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                    GROUP BY skiftraknare
                ) AS per_shift
                GROUP BY yearweek
                ORDER BY yearweek ASC
            ");
            $stmt->execute(['op_id' => $op_id]);
            $opWeeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Teamets snitt (alla operatörer, alla positioner) per vecka ---
            $stmt2 = $this->pdo->prepare("
                SELECT
                    YEARWEEK(first_datum, 3) AS yearweek,
                    AVG(last_bonus)          AS avg_bonus,
                    AVG(last_produktivitet)  AS avg_ibc_per_hour,
                    AVG(last_kvalitet)       AS avg_kvalitet
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum) AS first_datum,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                        SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_produktivitet,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                    GROUP BY skiftraknare
                ) AS per_shift
                GROUP BY yearweek
                ORDER BY yearweek ASC
            ");
            $stmt2->execute();
            $teamWeeks = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Indexera team per yearweek för snabb lookup
            $teamMap = [];
            foreach ($teamWeeks as $row) {
                $teamMap[$row['yearweek']] = [
                    'avg_bonus'        => round($row['avg_bonus']        ?? 0, 2),
                    'avg_ibc_per_hour' => round($row['avg_ibc_per_hour'] ?? 0, 2),
                    'avg_kvalitet'     => round($row['avg_kvalitet']      ?? 0, 2),
                ];
            }

            // Bygg listan med operatörens veckodata inkl. teamjämförelse
            $weeks = array_map(function ($row) use ($teamMap) {
                $yw = $row['yearweek'];
                $team = $teamMap[$yw] ?? ['avg_bonus' => 0, 'avg_ibc_per_hour' => 0, 'avg_kvalitet' => 0];
                return [
                    'yearweek'        => (int)$yw,
                    'year'            => (int)$row['yr'],
                    'week'            => (int)$row['wk'],
                    'label'           => 'V' . (int)$row['wk'],
                    'shifts'          => (int)$row['shifts'],
                    'my_bonus'        => round($row['avg_bonus']        ?? 0, 2),
                    'my_ibc_per_hour' => round($row['avg_ibc_per_hour'] ?? 0, 2),
                    'my_kvalitet'     => round($row['avg_kvalitet']      ?? 0, 2),
                    'team_bonus'      => $team['avg_bonus'],
                    'team_ibc_per_hour' => $team['avg_ibc_per_hour'],
                    'team_kvalitet'   => $team['avg_kvalitet'],
                ];
            }, $opWeeks);

            // Räkna ut snitt för operatören (för referenslinjen)
            $myBonusAvg = count($weeks) > 0
                ? round(array_sum(array_column($weeks, 'my_bonus')) / count($weeks), 2)
                : 0;

            $this->sendSuccess([
                'operator_id' => (int)$op_id,
                'weeks'       => $weeks,
                'my_avg'      => $myBonusAvg,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getWeeklyHistory: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=hall-of-fame
     *
     * Topplistor per KPI de senaste 90 dagarna.
     * Kategorier: ibc_per_h (IBC/h snitt per dag), kvalitet_pct, mest_aktiv (antal skift).
     * Returnerar topp 3 per kategori.
     */
    private function getHallOfFame(): void {
        try {
            $opRows = $this->pdo->query("SELECT number, name FROM operators")->fetchAll(PDO::FETCH_KEY_PAIR);

            // --- IBC/h per dag per operatör senaste 90 dagar ---
            // Aggregera per (datum, skiftraknare, op) → summera per datum per op → snitt-IBC/h per dag
            $ibcPerHRows = $this->pdo->query("
                SELECT
                    op_id,
                    AVG(ibc_per_h) AS avg_ibc_per_h
                FROM (
                    SELECT
                        op_id,
                        datum_day,
                        SUM(shift_ibc_ok) / NULLIF(SUM(shift_runtime_h), 0) AS ibc_per_h
                    FROM (
                        SELECT op1 AS op_id, DATE(datum) AS datum_day,
                               MAX(ibc_ok) AS shift_ibc_ok,
                               MAX(runtime_plc) / 60.0 AS shift_runtime_h
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                          AND op1 IS NOT NULL AND op1 > 0
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare, op1
                        UNION ALL
                        SELECT op2, DATE(datum), MAX(ibc_ok), MAX(runtime_plc)/60.0
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                          AND op2 IS NOT NULL AND op2 > 0
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare, op2
                        UNION ALL
                        SELECT op3, DATE(datum), MAX(ibc_ok), MAX(runtime_plc)/60.0
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                          AND op3 IS NOT NULL AND op3 > 0
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare, op3
                    ) AS per_shift
                    GROUP BY op_id, datum_day
                ) AS per_day
                GROUP BY op_id
                HAVING avg_ibc_per_h > 0
                ORDER BY avg_ibc_per_h DESC
                LIMIT 3
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Kvalitet % per operatör ---
            $kvalitetRows = $this->pdo->query("
                SELECT
                    op_id,
                    SUM(shift_ibc_ok) / NULLIF(SUM(shift_ibc_ok) + SUM(shift_ibc_ej_ok), 0) * 100 AS kvalitet_pct
                FROM (
                    SELECT op1 AS op_id,
                           MAX(ibc_ok) AS shift_ibc_ok,
                           MAX(ibc_ej_ok) AS shift_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      AND op1 IS NOT NULL AND op1 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare, op1
                    UNION ALL
                    SELECT op2, MAX(ibc_ok), MAX(ibc_ej_ok)
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      AND op2 IS NOT NULL AND op2 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare, op2
                    UNION ALL
                    SELECT op3, MAX(ibc_ok), MAX(ibc_ej_ok)
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      AND op3 IS NOT NULL AND op3 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare, op3
                ) AS per_shift
                GROUP BY op_id
                HAVING kvalitet_pct > 0
                ORDER BY kvalitet_pct DESC
                LIMIT 3
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Mest aktiv (antal unika skift) per operatör ---
            $aktivRows = $this->pdo->query("
                SELECT op_id, COUNT(*) AS antal_skift
                FROM (
                    SELECT op1 AS op_id, skiftraknare
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      AND op1 IS NOT NULL AND op1 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2, skiftraknare
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      AND op2 IS NOT NULL AND op2 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3, skiftraknare
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                      AND op3 IS NOT NULL AND op3 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY op3, skiftraknare
                ) AS per_shift
                GROUP BY op_id
                ORDER BY antal_skift DESC
                LIMIT 3
            ")->fetchAll(PDO::FETCH_ASSOC);

            $badges = ['gold', 'silver', 'bronze'];

            $formatRow = function (array $rows, string $valueKey, string $displayKey) use ($opRows, $badges): array {
                $result = [];
                foreach (array_values($rows) as $i => $row) {
                    $opId = (int)$row['op_id'];
                    $result[] = [
                        'rank'   => $i + 1,
                        'badge'  => $badges[$i] ?? 'bronze',
                        'name'   => $opRows[$opId] ?? ('Op ' . $opId),
                        'value'  => round((float)$row[$valueKey], 1),
                        'label'  => $displayKey,
                    ];
                }
                return $result;
            };

            $this->sendSuccess([
                'period_days' => 90,
                'ibc_per_h'   => $formatRow($ibcPerHRows,  'avg_ibc_per_h',  'IBC/h'),
                'kvalitet_pct'=> $formatRow($kvalitetRows, 'kvalitet_pct',   '%'),
                'mest_aktiv'  => $formatRow($aktivRows,    'antal_skift',    'skift'),
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getHallOfFame: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=loneprognos
     *
     * Beräknar förväntad bonusutbetalning innevarande lönperiod (1:a–sista i månaden).
     * Returnerar per operatör: antal skift, totalt IBC OK, och uppskattad bonus-tier
     * baserat på bonus_poang-genomsnitt.
     *
     * Bonus-tiers (poäng → SEK) om inga bonus_level_amounts finns i DB:
     *   ≥95 → 2500 kr (Outstanding)
     *   ≥90 → 2000 kr (Excellent)
     *   ≥80 → 1500 kr (God)
     *   ≥70 → 1000 kr (Bas)
     *   <70 → 0 kr
     */
    private function getLoneprognos(): void {
        try {
            $opRows = $this->pdo->query("SELECT number, name FROM operators")->fetchAll(PDO::FETCH_KEY_PAIR);

            $monthStart = date('Y-m-01');
            $today      = date('Y-m-d');
            $daysInMonth = (int)date('t');
            $dayOfMonth  = (int)date('j');
            $monthPct    = $daysInMonth > 0 ? round($dayOfMonth / $daysInMonth * 100) : 0;
            $daysLeft    = $daysInMonth - $dayOfMonth;

            // Per-skift per operatör denna månad (alla positioner)
            $stmt = $this->pdo->prepare("
                SELECT
                    op_id,
                    COUNT(*)           AS antal_skift,
                    SUM(shift_ibc_ok)  AS ibc_ok_manad,
                    AVG(last_bonus)    AS avg_bonus
                FROM (
                    SELECT op1 AS op_id, skiftraknare,
                           MAX(ibc_ok) AS shift_ibc_ok,
                           SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :ms1 AND :td1 AND op1 IS NOT NULL AND op1 > 0 AND skiftraknare IS NOT NULL
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2, skiftraknare, MAX(ibc_ok),
                           SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :ms2 AND :td2 AND op2 IS NOT NULL AND op2 > 0 AND skiftraknare IS NOT NULL
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3, skiftraknare, MAX(ibc_ok),
                           SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :ms3 AND :td3 AND op3 IS NOT NULL AND op3 > 0 AND skiftraknare IS NOT NULL
                    GROUP BY op3, skiftraknare
                ) AS per_shift
                GROUP BY op_id
                ORDER BY avg_bonus DESC
            ");
            $stmt->execute([
                ':ms1' => $monthStart, ':td1' => $today,
                ':ms2' => $monthStart, ':td2' => $today,
                ':ms3' => $monthStart, ':td3' => $today,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Standard bonus-tiers (SEK) baserade på bonus_poang
            $tiers = [
                ['label' => 'Outstanding', 'tier_key' => 'outstanding', 'min' => 95, 'sek' => 2500],
                ['label' => 'Excellent',   'tier_key' => 'excellent',   'min' => 90, 'sek' => 2000],
                ['label' => 'God',         'tier_key' => 'god',         'min' => 80, 'sek' => 1500],
                ['label' => 'Bas',         'tier_key' => 'bas',         'min' => 70, 'sek' => 1000],
                ['label' => 'Under mål',   'tier_key' => 'under',       'min' => 0,  'sek' => 0],
            ];

            $matchTier = function (float $avgBonus) use ($tiers): array {
                foreach ($tiers as $t) {
                    if ($avgBonus >= $t['min']) return $t;
                }
                return end($tiers);
            };

            $result = [];
            foreach ($rows as $row) {
                $opId    = (int)$row['op_id'];
                $avgBonus = (float)($row['avg_bonus'] ?? 0);
                $tier    = $matchTier($avgBonus);
                $skift   = (int)$row['antal_skift'];

                $result[] = [
                    'operator_id'        => $opId,
                    'operator_name'      => $opRows[$opId] ?? ('Op ' . $opId),
                    'antal_skift'        => $skift,
                    'ibc_ok_manad'       => (int)$row['ibc_ok_manad'],
                    'avg_bonus_poang'    => round($avgBonus, 1),
                    'tier_label'         => $tier['label'],
                    'tier_key'           => $tier['tier_key'],
                    'bonus_per_skift_sek'=> $tier['sek'],
                    'beraknad_bonus_sek' => $tier['sek'] * $skift,
                ];
            }

            // Svenska månadsnamn
            $monthNames = [
                1=>'januari', 2=>'februari', 3=>'mars', 4=>'april',
                5=>'maj', 6=>'juni', 7=>'juli', 8=>'augusti',
                9=>'september', 10=>'oktober', 11=>'november', 12=>'december'
            ];
            $manadsnamn = $monthNames[(int)date('n')] ?? date('F');

            $this->sendSuccess([
                'manadsnamn'  => $manadsnamn,
                'month_start' => $monthStart,
                'today'       => $today,
                'days_in_month' => $daysInMonth,
                'day_of_month'  => $dayOfMonth,
                'days_left'     => $daysLeft,
                'month_pct'     => $monthPct,
                'operatorer'    => $result,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getLoneprognos: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=personal-best&operator_id=X
     *
     * Returnerar operatörens personliga rekord senaste 365 dagarna:
     * - Bästa IBC/h i ett enskilt skift
     * - Bästa kvalitet% i ett enskilt skift
     * - Bästa skift (flest IBC OK)
     */
    private function getPersonalBest(): void {
        $opId = intval($_GET['operator_id'] ?? 0);
        if (!$opId) {
            $this->sendError('Saknar operator_id');
            return;
        }

        try {
            // Bästa IBC/h i ett enskilt skift (senaste 365 dagar)
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(first_datum) AS dag,
                    skiftraknare,
                    shift_ibc_ok,
                    shift_ibc_ej_ok,
                    shift_runtime_h,
                    CASE WHEN shift_runtime_h > 0 THEN shift_ibc_ok / shift_runtime_h ELSE 0 END AS ibc_per_h,
                    CASE WHEN (shift_ibc_ok + shift_ibc_ej_ok) > 0
                         THEN shift_ibc_ok / (shift_ibc_ok + shift_ibc_ej_ok) * 100
                         ELSE 0 END AS kvalitet_pct
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum) AS first_datum,
                        MAX(ibc_ok) - MIN(ibc_ok)       AS shift_ibc_ok,
                        MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS shift_ibc_ej_ok,
                        MAX(runtime_plc) / 60.0        AS shift_runtime_h
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                ) AS per_shift
                ORDER BY ibc_per_h DESC
                LIMIT 1
            ");
            $stmt->execute([$opId, $opId, $opId]);
            $bestIbcH = $stmt->fetch(PDO::FETCH_ASSOC);

            // Bästa kvalitet% i ett enskilt skift
            $stmt2 = $this->pdo->prepare("
                SELECT
                    DATE(first_datum) AS dag,
                    skiftraknare,
                    shift_ibc_ok,
                    shift_ibc_ej_ok,
                    CASE WHEN (shift_ibc_ok + shift_ibc_ej_ok) > 0
                         THEN shift_ibc_ok / (shift_ibc_ok + shift_ibc_ej_ok) * 100
                         ELSE 0 END AS kvalitet_pct
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum) AS first_datum,
                        MAX(ibc_ok) - MIN(ibc_ok)       AS shift_ibc_ok,
                        MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS shift_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                       AND (MAX(ibc_ok) + MAX(ibc_ej_ok) - MIN(ibc_ok) - MIN(ibc_ej_ok)) > 0
                ) AS per_shift
                ORDER BY kvalitet_pct DESC
                LIMIT 1
            ");
            $stmt2->execute([$opId, $opId, $opId]);
            $bestKvalitet = $stmt2->fetch(PDO::FETCH_ASSOC);

            // Bästa skift: flest IBC OK
            $stmt3 = $this->pdo->prepare("
                SELECT
                    DATE(first_datum) AS dag,
                    skiftraknare,
                    shift_ibc_ok
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum) AS first_datum,
                        MAX(ibc_ok) - MIN(ibc_ok) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                ) AS per_shift
                ORDER BY shift_ibc_ok DESC
                LIMIT 1
            ");
            $stmt3->execute([$opId, $opId, $opId]);
            $bestSkift = $stmt3->fetch(PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'best_ibc_per_h'      => $bestIbcH ? round(floatval($bestIbcH['ibc_per_h']), 1) : null,
                'best_ibc_per_h_date' => $bestIbcH ? $bestIbcH['dag'] : null,
                'best_kvalitet'       => $bestKvalitet ? round(floatval($bestKvalitet['kvalitet_pct']), 1) : null,
                'best_kvalitet_date'  => $bestKvalitet ? $bestKvalitet['dag'] : null,
                'best_skift_ibc'      => $bestSkift ? intval($bestSkift['shift_ibc_ok']) : null,
                'best_skift_ibc_date' => $bestSkift ? $bestSkift['dag'] : null,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getPersonalBest: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=streak&operator_id=X
     *
     * Räknar konsekutiva dagar operatören jobbat (senaste 60 dagar).
     * Returnerar nuvarande streak och längsta streak under perioden.
     */
    private function getStreak(): void {
        $opId = intval($_GET['operator_id'] ?? 0);
        if (!$opId) {
            $this->sendError('Saknar operator_id');
            return;
        }

        try {
            // Hämta dagliga IBC per skift senaste 60 dagar (nyast först)
            $stmt = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, SUM(delta_ok) AS ibc_dag
                FROM (
                    SELECT DATE(datum) AS datum, skiftraknare,
                           MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) x
                GROUP BY DATE(datum)
                ORDER BY dag DESC
            ");
            $stmt->execute([$opId, $opId, $opId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna nuvarande streak (dagar i rad med ibc_dag > 0, bakifrån idag)
            $streak   = 0;
            $prevDate = null;
            $tzBon = new DateTimeZone('Europe/Stockholm');
            $today = new DateTime('today', $tzBon);

            foreach ($rows as $row) {
                try {
                    $dag = new DateTime($row['dag'], $tzBon);
                } catch (Exception $e) {
                    continue;
                }
                if ($row['ibc_dag'] <= 0) {
                    if ($streak > 0) break;
                    if ($prevDate === null && $dag->diff($today)->days <= 1) continue;
                    break;
                }
                if ($prevDate !== null) {
                    $diff = $prevDate->diff($dag)->days;
                    if ($diff > 1) break;
                }
                $streak++;
                $prevDate = $dag;
            }

            // Längsta streak senaste 60 dagar (kronologisk ordning ASC)
            $rowsAsc = array_reverse($rows);
            $longest  = 0;
            $current  = 0;
            $prevD    = null;

            foreach ($rowsAsc as $row) {
                try {
                    $d = new DateTime($row['dag'], $tzBon);
                } catch (Exception $e) {
                    $current = 0;
                    continue;
                }
                if ($row['ibc_dag'] > 0) {
                    if ($prevD !== null && $prevD->diff($d)->days <= 1) {
                        $current++;
                    } else {
                        $current = 1;
                    }
                    if ($current > $longest) $longest = $current;
                } else {
                    $current = 0;
                }
                $prevD = $d;
            }

            $this->sendSuccess([
                'current_streak' => $streak,
                'longest_streak' => $longest,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getStreak: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=achievements&operator_id=X
     *
     * Returnerar achievement badges med status, progress och uppnått-datum.
     * Milstolpar: IBC-totalt (livstid), Perfekt vecka, Streak, Hastighets-mästare, Kvalitets-mästare.
     */
    private function getAchievements(): void {
        $opId = intval($_GET['operator_id'] ?? 0);
        if (!$opId) {
            $this->sendError('Saknar operator_id');
            return;
        }

        try {
            $badges = [];

            // --- 1. IBC-milstolpar (livstid) ---
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(delta_ok), 0) AS total_ibc
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok
                    FROM rebotling_ibc
                    WHERE (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                ) x
            ");
            $stmt->execute([$opId, $opId, $opId]);
            $totalIbc = intval($stmt->fetchColumn());

            $ibcMilestones = [
                ['id' => 'ibc_100',  'target' => 100,  'name' => '100 IBC',   'desc' => 'Producerat 100 IBC totalt',  'icon' => 'fa-box-open'],
                ['id' => 'ibc_500',  'target' => 500,  'name' => '500 IBC',   'desc' => 'Producerat 500 IBC totalt',  'icon' => 'fa-boxes-stacked'],
                ['id' => 'ibc_1000', 'target' => 1000, 'name' => '1 000 IBC', 'desc' => 'Producerat 1 000 IBC totalt','icon' => 'fa-trophy'],
                ['id' => 'ibc_2500', 'target' => 2500, 'name' => '2 500 IBC', 'desc' => 'Producerat 2 500 IBC totalt','icon' => 'fa-medal'],
                ['id' => 'ibc_5000', 'target' => 5000, 'name' => '5 000 IBC', 'desc' => 'Producerat 5 000 IBC totalt','icon' => 'fa-gem'],
            ];

            foreach ($ibcMilestones as $ms) {
                $earned = $totalIbc >= $ms['target'];
                $progress = $ms['target'] > 0 ? min(100, round(($totalIbc / $ms['target']) * 100)) : 0;
                $badges[] = [
                    'badge_id'    => $ms['id'],
                    'name'        => $ms['name'],
                    'description' => $ms['desc'],
                    'icon'        => $ms['icon'],
                    'earned'      => $earned,
                    'earned_date' => null,
                    'progress'    => $earned ? 100 : $progress,
                ];
            }

            // --- 2. Perfekt vecka: alla skift med kvalitet >= 95% ---
            $stmt2 = $this->pdo->prepare("
                SELECT COUNT(*) AS total_shifts, SUM(CASE WHEN kvalitet_pct >= 95 THEN 1 ELSE 0 END) AS perfect_shifts
                FROM (
                    SELECT skiftraknare,
                           MAX(ibc_ok) - MIN(ibc_ok) AS ok,
                           MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS ej_ok,
                           CASE WHEN (MAX(ibc_ok) - MIN(ibc_ok) + MAX(ibc_ej_ok) - MIN(ibc_ej_ok)) > 0
                                THEN (MAX(ibc_ok) - MIN(ibc_ok)) / (MAX(ibc_ok) - MIN(ibc_ok) + MAX(ibc_ej_ok) - MIN(ibc_ej_ok)) * 100
                                ELSE 100 END AS kvalitet_pct
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                ) x
            ");
            $stmt2->execute([$opId, $opId, $opId]);
            $kvalRow = $stmt2->fetch(PDO::FETCH_ASSOC);
            $totalShiftsWeek = intval($kvalRow['total_shifts'] ?? 0);
            $perfectShifts = intval($kvalRow['perfect_shifts'] ?? 0);
            $perfectWeekEarned = $totalShiftsWeek >= 3 && $perfectShifts === $totalShiftsWeek;
            $perfectWeekProgress = $totalShiftsWeek > 0 ? min(100, round(($perfectShifts / max($totalShiftsWeek, 5)) * 100)) : 0;

            $badges[] = [
                'badge_id'    => 'perfect_week',
                'name'        => 'Perfekt vecka',
                'description' => 'Alla skift med kvalitet >= 95% denna vecka',
                'icon'        => 'fa-star',
                'earned'      => $perfectWeekEarned,
                'earned_date' => null,
                'progress'    => $perfectWeekEarned ? 100 : $perfectWeekProgress,
            ];

            // --- 3. Streak: 5, 10, 20 dagar i rad ---
            $stmtStreak = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, SUM(delta_ok) AS ibc_dag
                FROM (
                    SELECT DATE(datum) AS datum, skiftraknare,
                           MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) x
                GROUP BY DATE(datum)
                ORDER BY dag DESC
            ");
            $stmtStreak->execute([$opId, $opId, $opId]);
            $streakRows = $stmtStreak->fetchAll(PDO::FETCH_ASSOC);

            $currentStreak = 0;
            $prevDate = null;
            $tzBon2 = new DateTimeZone('Europe/Stockholm');
            $todayDt = new DateTime('today', $tzBon2);
            foreach ($streakRows as $row) {
                try {
                    $dag = new DateTime($row['dag'], $tzBon2);
                } catch (Exception $e) {
                    continue;
                }
                if ($row['ibc_dag'] <= 0) {
                    if ($currentStreak > 0) break;
                    if ($prevDate === null && $dag->diff($todayDt)->days <= 1) continue;
                    break;
                }
                if ($prevDate !== null) {
                    $diff = $prevDate->diff($dag)->days;
                    if ($diff > 1) break;
                }
                $currentStreak++;
                $prevDate = $dag;
            }

            $streakLevels = [
                ['id' => 'streak_5',  'target' => 5,  'name' => '5-dagars streak',  'desc' => '5 dagar i rad med produktion'],
                ['id' => 'streak_10', 'target' => 10, 'name' => '10-dagars streak', 'desc' => '10 dagar i rad med produktion'],
                ['id' => 'streak_20', 'target' => 20, 'name' => '20-dagars streak', 'desc' => '20 dagar i rad med produktion'],
            ];

            foreach ($streakLevels as $sl) {
                $earned = $currentStreak >= $sl['target'];
                $progress = min(100, round(($currentStreak / $sl['target']) * 100));
                $badges[] = [
                    'badge_id'    => $sl['id'],
                    'name'        => $sl['name'],
                    'description' => $sl['desc'],
                    'icon'        => 'fa-fire',
                    'earned'      => $earned,
                    'earned_date' => null,
                    'progress'    => $earned ? 100 : $progress,
                ];
            }

            // --- 4. Hastighets-mästare: snitt IBC/h >= 12 en hel vecka ---
            $stmtSpeed = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ibc_ok), 0) AS week_ibc,
                    COALESCE(SUM(shift_runtime_h), 0) AS week_runtime_h,
                    COUNT(*) AS shift_count
                FROM (
                    SELECT skiftraknare,
                           MAX(ibc_ok) - MIN(ibc_ok) AS shift_ibc_ok,
                           MAX(runtime_plc) / 60.0 AS shift_runtime_h
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                ) x
            ");
            $stmtSpeed->execute([$opId, $opId, $opId]);
            $speedRow = $stmtSpeed->fetch(PDO::FETCH_ASSOC);
            $weekRuntime = floatval($speedRow['week_runtime_h'] ?? 0);
            $weekIbc = intval($speedRow['week_ibc'] ?? 0);
            $weekShifts = intval($speedRow['shift_count'] ?? 0);
            $avgIbcPerH = $weekRuntime > 0 ? $weekIbc / $weekRuntime : 0;
            $speedEarned = $weekShifts >= 3 && $avgIbcPerH >= 12;
            $speedProgress = min(100, round(($avgIbcPerH / 12) * 100));

            $badges[] = [
                'badge_id'    => 'speed_master',
                'name'        => 'Hastighets-mastare',
                'description' => 'Snitt IBC/h >= 12 en hel vecka (min 3 skift)',
                'icon'        => 'fa-bolt',
                'earned'      => $speedEarned,
                'earned_date' => null,
                'progress'    => $speedEarned ? 100 : $speedProgress,
            ];

            // --- 5. Kvalitets-mästare: snitt kvalitet >= 98% en hel vecka ---
            $stmtQual = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok), 0) AS total_ok,
                    COALESCE(SUM(shift_ok + shift_ej_ok), 0) AS total_all,
                    COUNT(*) AS shift_count
                FROM (
                    SELECT skiftraknare,
                           MAX(ibc_ok) - MIN(ibc_ok) AS shift_ok,
                           MAX(ibc_ej_ok) - MIN(ibc_ej_ok) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                    HAVING (MAX(ibc_ok) - MIN(ibc_ok)) > 0
                ) x
            ");
            $stmtQual->execute([$opId, $opId, $opId]);
            $qualRow = $stmtQual->fetch(PDO::FETCH_ASSOC);
            $qualTotalOk = intval($qualRow['total_ok'] ?? 0);
            $qualTotalAll = intval($qualRow['total_all'] ?? 0);
            $qualShifts = intval($qualRow['shift_count'] ?? 0);
            $avgQuality = $qualTotalAll > 0 ? ($qualTotalOk / $qualTotalAll) * 100 : 0;
            $qualEarned = $qualShifts >= 3 && $avgQuality >= 98;
            $qualProgress = min(100, round(($avgQuality / 98) * 100));

            $badges[] = [
                'badge_id'    => 'quality_master',
                'name'        => 'Kvalitets-mastare',
                'description' => 'Snitt kvalitet >= 98% en hel vecka (min 3 skift)',
                'icon'        => 'fa-gem',
                'earned'      => $qualEarned,
                'earned_date' => null,
                'progress'    => $qualEarned ? 100 : $qualProgress,
            ];

            $this->sendSuccess([
                'badges'  => $badges,
                'total_ibc_lifetime' => $totalIbc,
                'current_streak'     => $currentStreak,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getAchievements: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * POST /api.php?action=bonus&run=simulate
     *
     * What-if bonussimulator. Hämtar faktiska skiftdata för perioden
     * och beräknar hypotetisk bonus per operatör med de simulerade tier-parametrarna.
     *
     * Body (JSON):
     * {
     *   "period_start": "2026-02-01",
     *   "period_end": "2026-02-28",
     *   "ibc_goal_per_shift": 45,
     *   "bonus_tiers": [
     *     { "label": "Brons",    "min_ibc_per_hour": 4.0, "bonus_sek": 500  },
     *     { "label": "Silver",   "min_ibc_per_hour": 5.0, "bonus_sek": 1000 },
     *     { "label": "Guld",     "min_ibc_per_hour": 6.0, "bonus_sek": 1800 },
     *     { "label": "Platinum", "min_ibc_per_hour": 7.0, "bonus_sek": 2800 }
     *   ]
     * }
     */
    private function simulate(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        if (!is_array($body)) {
            $this->sendError('Ogiltig JSON i request-body');
            return;
        }

        $periodStart = $body['period_start'] ?? '';
        $periodEnd   = $body['period_end']   ?? '';
        $tiers       = $body['bonus_tiers']  ?? [];

        // Validera datumformat
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
            $this->sendError('Ogiltigt datumformat. Använd YYYY-MM-DD.');
            return;
        }

        if (empty($tiers) || !is_array($tiers)) {
            $this->sendError('bonus_tiers måste vara en icke-tom array');
            return;
        }

        // Validera och sanera tiers
        $cleanTiers = [];
        foreach ($tiers as $t) {
            if (!isset($t['label'], $t['min_ibc_per_hour'], $t['bonus_sek'])) {
                $this->sendError('Varje tier måste ha label, min_ibc_per_hour och bonus_sek');
                return;
            }
            $cleanTiers[] = [
                'label'            => substr(strip_tags((string)$t['label']), 0, 50),
                'min_ibc_per_hour' => (float)$t['min_ibc_per_hour'],
                'bonus_sek'        => (int)$t['bonus_sek'],
            ];
        }

        // Sortera tiers fallande efter min_ibc_per_hour så vi matchar bästa tier först
        usort($cleanTiers, fn($a, $b) => $b['min_ibc_per_hour'] <=> $a['min_ibc_per_hour']);

        // $periodStart/$periodEnd validated to YYYY-MM-DD (digits+hyphens only) — no injection possible
        $dateFilter = "DATE(datum) BETWEEN '" . $periodStart . "' AND '" . $periodEnd . "'";

        try {
            // Hämta operatörsnamn för lookup
            $opRows = $this->pdo->query("SELECT id, name FROM operators")
                                ->fetchAll(PDO::FETCH_KEY_PAIR);

            // Hämta per-skift-data för varje position och slå ihop
            $perShiftRows = [];

            for ($pos = 1; $pos <= 3; $pos++) {
                $inner = $this->perShiftByPosition($pos, $dateFilter);
                $stmt  = $this->pdo->prepare("
                    SELECT
                        operator_id,
                        skiftraknare,
                        shift_ibc_ok,
                        shift_runtime
                    FROM ($inner) AS ps
                    WHERE shift_runtime > 0
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $key = $row['operator_id'] . '_' . $row['skiftraknare'] . '_' . $pos;
                    $perShiftRows[$key] = $row;
                }
            }

            // Aggregera per operatör
            $opData = [];
            foreach ($perShiftRows as $row) {
                $opId = (int)$row['operator_id'];
                if (!isset($opData[$opId])) {
                    $opData[$opId] = [
                        'op_number'     => $opId,
                        'op_name'       => $opRows[$opId] ?? 'Operatör ' . $opId,
                        'shifts'        => 0,
                        'total_ibc_ok'  => 0,
                        'total_runtime' => 0,
                    ];
                }
                $opData[$opId]['shifts']++;
                $opData[$opId]['total_ibc_ok']  += (int)$row['shift_ibc_ok'];
                $opData[$opId]['total_runtime']  += (int)$row['shift_runtime'];
            }

            if (empty($opData)) {
                $this->sendSuccess([
                    'results'               => [],
                    'total_cost'            => 0,
                    'avg_bonus_per_operator'=> 0,
                    'period'                => $this->formatPeriodLabel($periodStart, $periodEnd),
                ]);
                return;
            }

            // Beräkna tier och bonus per operatör
            $results   = [];
            $totalCost = 0;

            foreach ($opData as $op) {
                $shifts       = $op['shifts'];
                $totalIbcOk   = $op['total_ibc_ok'];
                $totalRuntime = $op['total_runtime']; // minuter

                $totalHours       = $totalRuntime > 0 ? $totalRuntime / 60.0 : 0;
                $avgIbcPerHour    = ($totalHours > 0 && $shifts > 0)
                    ? round($totalIbcOk / $totalHours, 2)
                    : 0.0;

                // Matcha bästa tier (tiers är sorterade fallande)
                $matchedTier  = null;
                $bonusPerShift = 0;
                foreach ($cleanTiers as $tier) {
                    if ($avgIbcPerHour >= $tier['min_ibc_per_hour']) {
                        $matchedTier   = $tier['label'];
                        $bonusPerShift = $tier['bonus_sek'];
                        break;
                    }
                }

                $totalBonus = $bonusPerShift * $shifts;
                $totalCost += $totalBonus;

                $results[] = [
                    'op_name'          => $op['op_name'],
                    'op_number'        => $op['op_number'],
                    'shifts'           => $shifts,
                    'avg_ibc_per_hour' => $avgIbcPerHour,
                    'tier'             => $matchedTier ?? 'Ingen',
                    'bonus_sek'        => $bonusPerShift,
                    'total_bonus'      => $totalBonus,
                ];
            }

            // Sortera på total_bonus fallande
            usort($results, fn($a, $b) => $b['total_bonus'] <=> $a['total_bonus']);

            $numOps = count($results);
            $avgBonusPerOp = $numOps > 0 ? round($totalCost / $numOps) : 0;

            $this->sendSuccess([
                'results'               => $results,
                'total_cost'            => $totalCost,
                'avg_bonus_per_operator'=> $avgBonusPerOp,
                'period'                => $this->formatPeriodLabel($periodStart, $periodEnd),
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::simulate: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Formaterar ett datumintervall till en läsbar periodlabel, t.ex. "Feb 2026".
     * Om start och slut är i samma månad visas månadsnamn + år.
     * Annars visas "YYYY-MM-DD – YYYY-MM-DD".
     */
    private function formatPeriodLabel(string $start, string $end): string {
        $monthNames = [
            1  => 'Jan', 2  => 'Feb', 3  => 'Mar', 4  => 'Apr',
            5  => 'Maj', 6  => 'Jun', 7  => 'Jul', 8  => 'Aug',
            9  => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dec',
        ];
        $ts = strtotime($start);
        $te = strtotime($end);
        if ($ts && $te) {
            $startMonth = (int)date('n', $ts);
            $startYear  = (int)date('Y', $ts);
            $endMonth   = (int)date('n', $te);
            $endYear    = (int)date('Y', $te);
            if ($startMonth === $endMonth && $startYear === $endYear) {
                return ($monthNames[$startMonth] ?? $startMonth) . ' ' . $startYear;
            }
        }
        return $start . ' – ' . $end;
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
            // Validera att from <= to, annars byt plats
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }
            // Begränsa datumintervall till max 365 dagar för att förhindra timeout/memory exhaustion
            $tzBon3 = new DateTimeZone('Europe/Stockholm');
            try {
                $startDt = new DateTime($start, $tzBon3);
                $endDt   = new DateTime($end, $tzBon3);
            } catch (Exception $e) {
                error_log('BonusController::buildDateFilter — ogiltigt datumvärde: ' . $e->getMessage());
                return "1=0";
            }
            $diffDays = (int)$startDt->diff($endDt)->days;
            if ($diffDays > 365) {
                $start = (clone $endDt)->modify('-365 days')->format('Y-m-d');
            }
            // $start/$end validated to YYYY-MM-DD (digits+hyphens only) — no injection possible
            return "DATE(datum) BETWEEN '" . $start . "' AND '" . $end . "'";
        }

        switch ($period) {
            case 'today': return "DATE(datum) = CURDATE()";
            case 'week':  return "datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month': return "datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'year':  // fall through
            case 'all':   return "datum >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            default:      return "datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
    }

    /**
     * Returnerar den position en operatör jobbat mest på under perioden.
     */
    private function getOperatorPrimaryPosition(int $op_id, string $dateFilter): string {
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
            error_log('BonusController::getOperatorPrimaryPosition: ' . $e->getMessage());
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
     * GET /api.php?action=bonus&run=my-ranking&op_id=123&period=week|day|month
     *
     * Returnerar anonymiserad rankinginformation för en operatör:
     * - rank (position bland alla operatörer)
     * - total_ops (antal operatörer)
     * - ibc_per_h (operatörens IBC/h)
     * - quality_pct (operatörens kvalitet %)
     * - diff_from_leader_pct (differens mot ledaren i %)
     * - period_label (läsbar periodlabel)
     *
     * Säkerhet: kräver inloggning + op_id MÅSTE matcha inloggad användares operator_id.
     */
    private function getMyRanking(): void {
        // Kontrollera att op_id matchar inloggad användares kopplade operatör
        $requestedOpId = isset($_GET['op_id']) ? intval($_GET['op_id']) : null;
        $sessionOpId   = isset($_SESSION['operator_id']) ? (int)$_SESSION['operator_id'] : null;

        if (!$requestedOpId || $requestedOpId <= 0) {
            $this->sendError('op_id saknas');
            return;
        }

        if (!$sessionOpId || $sessionOpId !== $requestedOpId) {
            $this->sendError('Obehörig: op_id matchar inte inloggad användare', 403);
            return;
        }

        $period = trim($_GET['period'] ?? 'week');
        // Tillåt enbart giltiga perioder
        $allowedPeriods = ['day', 'today', 'week', 'month'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'week';
        }
        // Normalisera "day" -> "today"
        if ($period === 'day') $period = 'today';

        $periodLabels = [
            'today' => 'Idag',
            'week'  => 'Denna vecka',
            'month' => 'Denna månad',
        ];
        $periodLabel = $periodLabels[$period] ?? 'Denna vecka';

        $dateFilter = $this->getDateFilter($period);

        try {
            // Steg 1: Aggregera alla operatörers prestationer via UNION ALL (alla positioner)
            $s1 = $this->perShiftByPosition(1, $dateFilter);
            $s2 = $this->perShiftByPosition(2, $dateFilter);
            $s3 = $this->perShiftByPosition(3, $dateFilter);

            $stmt = $this->pdo->query("
                SELECT
                    operator_id,
                    SUM(total_shifts)   AS total_shifts,
                    SUM(total_ibc_ok)   AS total_ibc_ok,
                    SUM(total_runtime)  AS total_runtime,
                    AVG(avg_kval)       AS avg_kvalitet
                FROM (
                    SELECT operator_id,
                           COUNT(*)            AS total_shifts,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime,
                           AVG(last_kval)       AS avg_kval
                    FROM ($s1) AS x1
                    GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           COUNT(*)            AS total_shifts,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime,
                           AVG(last_kval)       AS avg_kval
                    FROM ($s2) AS x2
                    GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           COUNT(*)            AS total_shifts,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime,
                           AVG(last_kval)       AS avg_kval
                    FROM ($s3) AS x3
                    GROUP BY operator_id
                ) AS combined
                GROUP BY operator_id
                HAVING total_shifts >= 1
            ");
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($all)) {
                // Inga operatörer överhuvudtaget under perioden
                $this->sendSuccess([
                    'rank'                => null,
                    'total_ops'           => 0,
                    'ibc_per_h'           => null,
                    'quality_pct'         => null,
                    'diff_from_leader_pct'=> null,
                    'period_label'        => $periodLabel,
                    'no_data'             => true,
                ]);
                return;
            }

            // Beräkna IBC/h per operatör
            $opStats = [];
            foreach ($all as $row) {
                $opId    = (int)$row['operator_id'];
                $hours   = ($row['total_runtime'] ?? 0) > 0 ? (float)$row['total_runtime'] / 60.0 : 0;
                $ibcPerH = ($hours > 0) ? round((float)$row['total_ibc_ok'] / $hours, 2) : 0.0;
                $opStats[] = [
                    'operator_id' => $opId,
                    'ibc_per_h'   => $ibcPerH,
                    'quality_pct' => round((float)($row['avg_kvalitet'] ?? 0), 1),
                ];
            }

            // Sortera fallande på IBC/h för ranking
            usort($opStats, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

            $totalOps = count($opStats);
            $myRank   = null;
            $myStats  = null;

            foreach ($opStats as $idx => $op) {
                if ($op['operator_id'] === $requestedOpId) {
                    $myRank  = $idx + 1;
                    $myStats = $op;
                    break;
                }
            }

            if ($myRank === null) {
                // Operatören har ingen data under perioden
                $this->sendSuccess([
                    'rank'                => null,
                    'total_ops'           => $totalOps,
                    'ibc_per_h'           => null,
                    'quality_pct'         => null,
                    'diff_from_leader_pct'=> null,
                    'period_label'        => $periodLabel,
                    'no_data'             => true,
                ]);
                return;
            }

            // Ledarens IBC/h (rank #1)
            $leaderIbcPerH = $opStats[0]['ibc_per_h'];

            // Diff från ledaren i % (negativ = under ledaren, 0 = är ledaren)
            $diffFromLeader = 0.0;
            if ($leaderIbcPerH > 0 && $myRank > 1) {
                $diffFromLeader = round((($myStats['ibc_per_h'] - $leaderIbcPerH) / $leaderIbcPerH) * 100, 1);
            }

            $this->sendSuccess([
                'rank'                => $myRank,
                'total_ops'           => $totalOps,
                'ibc_per_h'           => $myStats['ibc_per_h'],
                'quality_pct'         => $myStats['quality_pct'],
                'diff_from_leader_pct'=> $diffFromLeader,
                'period_label'        => $periodLabel,
                'no_data'             => false,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getMyRanking: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=week-trend
     *
     * Returnerar daglig IBC/h per operatör för innevarande vecka (måndag–idag).
     * Används för att rita veckans IBC/h-trendgraf i bonus-dashboard.
     *
     * Returnerar:
     *   dates:     ["Mån 2 mar", "Tis 3 mar", ...]
     *   operators: [{ op_id, namn, initialer, data: [12.4, 14.1, null, ...] }]
     *   team_avg:  [11.2, 13.5, ...]
     */
    private function getWeekTrend(): void {
        try {
            // Hämta alla dagar från måndag denna vecka till idag
            $stmt = $this->pdo->query("
                SELECT DISTINCT DATE(datum) AS dag
                FROM rebotling_ibc
                WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND DATE(datum) <= CURDATE()
                ORDER BY dag ASC
            ");
            $dayRows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($dayRows)) {
                $this->sendSuccess([
                    'dates'      => [],
                    'operators'  => [],
                    'team_avg'   => [],
                ]);
                return;
            }

            // Hämta IBC/h per dag per operatör (alla tre positioner, aggregerat korrekt)
            $raw = $this->pdo->query("
                SELECT
                    dag,
                    op_id,
                    SUM(shift_ibc) / NULLIF(SUM(shift_runtime_h), 0) AS ibc_per_h
                FROM (
                    SELECT DATE(datum) AS dag, op1 AS op_id,
                        skiftraknare,
                        MAX(ibc_ok) AS shift_ibc,
                        MAX(runtime_plc) / 60.0 AS shift_runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                      AND DATE(datum) <= CURDATE()
                      AND op1 IS NOT NULL AND op1 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op1

                    UNION ALL

                    SELECT DATE(datum) AS dag, op2 AS op_id,
                        skiftraknare,
                        MAX(ibc_ok) AS shift_ibc,
                        MAX(runtime_plc) / 60.0 AS shift_runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                      AND DATE(datum) <= CURDATE()
                      AND op2 IS NOT NULL AND op2 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op2

                    UNION ALL

                    SELECT DATE(datum) AS dag, op3 AS op_id,
                        skiftraknare,
                        MAX(ibc_ok) AS shift_ibc,
                        MAX(runtime_plc) / 60.0 AS shift_runtime_h
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                      AND DATE(datum) <= CURDATE()
                      AND op3 IS NOT NULL AND op3 > 0
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare, op3
                ) AS t
                GROUP BY dag, op_id
                ORDER BY dag, op_id
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Hämta operatörsnamn
            $opIds = array_unique(array_column($raw, 'op_id'));
            $opNames = [];
            if (!empty($opIds)) {
                $placeholders = implode(',', array_fill(0, count($opIds), '?'));
                $nameStmt = $this->pdo->prepare("
                    SELECT number, name FROM operators WHERE number IN ($placeholders)
                ");
                $nameStmt->execute(array_values($opIds));
                foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $opNames[(int)$row['number']] = $row['name'];
                }
            }

            // Bygg en lookup: [op_id][dag] => ibc_per_h
            $lookup = [];
            foreach ($raw as $row) {
                $opId = (int)$row['op_id'];
                $dag  = $row['dag'];
                $lookup[$opId][$dag] = $row['ibc_per_h'] !== null ? round((float)$row['ibc_per_h'], 2) : null;
            }

            // Formatera dagsetiketter: "Mån 2 mar" etc.
            $dayNames = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
            $monthNames = ['jan','feb','mar','apr','maj','jun','jul','aug','sep','okt','nov','dec'];
            $dates = [];
            foreach ($dayRows as $dag) {
                $ts   = strtotime($dag);
                $dow  = (int)date('N', $ts) - 1; // 0=Mån..6=Sön
                $day  = (int)date('j', $ts);
                $mon  = (int)date('n', $ts) - 1;
                $dates[] = $dayNames[$dow] . ' ' . $day . ' ' . $monthNames[$mon];
            }

            // Bygg per-operatör dataset
            $operators = [];
            foreach ($lookup as $opId => $dagData) {
                $namn = $opNames[$opId] ?? ('Op ' . $opId);
                // Initials: första bokstav i varje ord
                $parts    = preg_split('/\s+/', trim($namn));
                $initialer = '';
                foreach ($parts as $p) {
                    if (strlen($p) > 0) $initialer .= strtoupper($p[0]);
                }
                if (strlen($initialer) > 2) $initialer = substr($initialer, 0, 2);
                if (empty($initialer)) $initialer = 'OP';

                $data = [];
                foreach ($dayRows as $dag) {
                    $data[] = isset($dagData[$dag]) ? $dagData[$dag] : null;
                }
                $operators[] = [
                    'op_id'     => $opId,
                    'namn'      => $namn,
                    'initialer' => $initialer,
                    'data'      => $data,
                ];
            }

            // Team-snitt per dag
            $teamAvg = [];
            foreach ($dayRows as $dag) {
                $vals = [];
                foreach ($lookup as $opId => $dagData) {
                    if (isset($dagData[$dag]) && $dagData[$dag] !== null) {
                        $vals[] = (float)$dagData[$dag];
                    }
                }
                $teamAvg[] = !empty($vals) ? round(array_sum($vals) / count($vals), 2) : null;
            }

            $this->sendSuccess([
                'dates'      => $dates,
                'operators'  => $operators,
                'team_avg'   => $teamAvg,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getWeekTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=ranking-position
     *
     * Returnerar anonymiserad kollegajämförelse för den inloggade operatören,
     * baserat på aktuell ISO-vecka. Kräver session user_id + kopplat operator_id.
     *
     * Returnerar:
     * {
     *   success: true,
     *   my_rank: 2,
     *   total_operators: 5,
     *   my_ibc_per_h: 14.5,
     *   top_ibc_per_h: 16.2,
     *   avg_ibc_per_h: 12.8,
     *   week_label: "Vecka 10"
     * }
     * Om operatören saknar data denna vecka: my_rank = null men övriga fält fylls i.
     */
    private function getRankingPosition(): void {
        // Hämta session-operatör
        $sessionOpId = isset($_SESSION['operator_id']) ? (int)$_SESSION['operator_id'] : null;

        if (!$sessionOpId || $sessionOpId <= 0) {
            $this->sendError('Inget operator_id kopplat till kontot');
            return;
        }

        // Vecka-filter: innevarande ISO-vecka
        $dateFilter = "YEARWEEK(DATE(datum), 3) = YEARWEEK(NOW(), 3)";
        $weekNum    = (int)date('W');
        $weekLabel  = "Vecka $weekNum";

        // Förra veckans filter för trend-beräkning
        $prevWeekFilter = "YEARWEEK(DATE(datum), 3) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 7 DAY), 3)";

        try {
            // --- Innevarande vecka ---
            $s1 = $this->perShiftByPosition(1, $dateFilter);
            $s2 = $this->perShiftByPosition(2, $dateFilter);
            $s3 = $this->perShiftByPosition(3, $dateFilter);

            $stmt = $this->pdo->query("
                SELECT
                    operator_id,
                    SUM(total_ibc_ok)   AS total_ibc_ok,
                    SUM(total_runtime)  AS total_runtime
                FROM (
                    SELECT operator_id,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime
                    FROM ($s1) AS x1
                    GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime
                    FROM ($s2) AS x2
                    GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime
                    FROM ($s3) AS x3
                    GROUP BY operator_id
                ) AS combined
                GROUP BY operator_id
                HAVING total_runtime > 0
            ");
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($all)) {
                $this->sendSuccess([
                    'my_rank'          => null,
                    'total_operators'  => 0,
                    'my_ibc_per_h'     => null,
                    'top_ibc_per_h'    => null,
                    'avg_ibc_per_h'    => null,
                    'percentile'       => null,
                    'trend'            => 'same',
                    'week_label'       => $weekLabel,
                ]);
                return;
            }

            // Beräkna IBC/h per operatör
            $opStats = [];
            foreach ($all as $row) {
                $hours   = (float)$row['total_runtime'] / 60.0;
                $ibcPerH = $hours > 0 ? round((float)$row['total_ibc_ok'] / $hours, 2) : 0.0;
                $opStats[] = [
                    'operator_id' => (int)$row['operator_id'],
                    'ibc_per_h'   => $ibcPerH,
                ];
            }

            // Sortera fallande på IBC/h
            usort($opStats, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

            $totalOps   = count($opStats);
            $topIbcPerH = $opStats[0]['ibc_per_h'];
            $avgIbcPerH = round(array_sum(array_column($opStats, 'ibc_per_h')) / $totalOps, 2);

            // Finn min placering
            $myRank     = null;
            $myIbcPerH  = null;
            foreach ($opStats as $idx => $op) {
                if ($op['operator_id'] === $sessionOpId) {
                    $myRank    = $idx + 1;
                    $myIbcPerH = $op['ibc_per_h'];
                    break;
                }
            }

            // Beräkna percentil (topp X%)
            $percentile = null;
            if ($myRank !== null && $totalOps > 0) {
                $percentile = round(($myRank / $totalOps) * 100);
            }

            // --- Förra veckan: beräkna trend ---
            $trend = 'same';
            try {
                $ps1 = $this->perShiftByPosition(1, $prevWeekFilter);
                $ps2 = $this->perShiftByPosition(2, $prevWeekFilter);
                $ps3 = $this->perShiftByPosition(3, $prevWeekFilter);

                $prevStmt = $this->pdo->query("
                    SELECT
                        operator_id,
                        SUM(total_ibc_ok)   AS total_ibc_ok,
                        SUM(total_runtime)  AS total_runtime
                    FROM (
                        SELECT operator_id,
                               SUM(shift_ibc_ok)   AS total_ibc_ok,
                               SUM(shift_runtime)  AS total_runtime
                        FROM ($ps1) AS px1
                        GROUP BY operator_id

                        UNION ALL

                        SELECT operator_id,
                               SUM(shift_ibc_ok)   AS total_ibc_ok,
                               SUM(shift_runtime)  AS total_runtime
                        FROM ($ps2) AS px2
                        GROUP BY operator_id

                        UNION ALL

                        SELECT operator_id,
                               SUM(shift_ibc_ok)   AS total_ibc_ok,
                               SUM(shift_runtime)  AS total_runtime
                        FROM ($ps3) AS px3
                        GROUP BY operator_id
                    ) AS prev_combined
                    GROUP BY operator_id
                    HAVING total_runtime > 0
                ");
                $prevAll = $prevStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($prevAll)) {
                    $prevOpStats = [];
                    foreach ($prevAll as $row) {
                        $hours   = (float)$row['total_runtime'] / 60.0;
                        $ibcPerH = $hours > 0 ? round((float)$row['total_ibc_ok'] / $hours, 2) : 0.0;
                        $prevOpStats[] = [
                            'operator_id' => (int)$row['operator_id'],
                            'ibc_per_h'   => $ibcPerH,
                        ];
                    }
                    usort($prevOpStats, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

                    $prevRank = null;
                    foreach ($prevOpStats as $idx => $op) {
                        if ($op['operator_id'] === $sessionOpId) {
                            $prevRank = $idx + 1;
                            break;
                        }
                    }

                    if ($prevRank !== null && $myRank !== null) {
                        if ($myRank < $prevRank) {
                            $trend = 'up';    // Lägre rank-nummer = bättre position
                        } elseif ($myRank > $prevRank) {
                            $trend = 'down';
                        } else {
                            $trend = 'same';
                        }
                    }
                }
            } catch (PDOException $e) {
                // Trend-beräkning är ej kritisk, logga och fortsätt
                error_log('BonusController::getRankingPosition trend: ' . $e->getMessage());
            }

            $this->sendSuccess([
                'my_rank'         => $myRank,
                'total_operators' => $totalOps,
                'my_ibc_per_h'    => $myIbcPerH,
                'top_ibc_per_h'   => $topIbcPerH,
                'avg_ibc_per_h'   => $avgIbcPerH,
                'percentile'      => $percentile,
                'trend'           => $trend,
                'week_label'      => $weekLabel,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getRankingPosition: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET /api.php?action=bonus&run=peer-ranking&operator_id=X
     *
     * Anonymiserad kollegajamforelse for aktuell ISO-vecka.
     * Returnerar operatorens ranking bland alla aktiva operatorer
     * samt en anonymiserad peers-lista (utan namn/id).
     */
    private function getPeerRanking(): void {
        $operatorId = isset($_GET['operator_id']) ? intval($_GET['operator_id']) : null;

        if (!$operatorId || $operatorId <= 0) {
            $this->sendError('operator_id saknas');
            return;
        }

        // Vecka-filter: innevarande ISO-vecka
        $dateFilter = "YEARWEEK(DATE(datum), 3) = YEARWEEK(NOW(), 3)";
        $weekNum    = (int)date('W');
        $yearNum    = (int)date('o');
        $weekLabel  = "Vecka $weekNum, $yearNum";

        try {
            // Aggregera alla operatorers prestationer via UNION ALL (alla tre positioner)
            $s1 = $this->perShiftByPosition(1, $dateFilter);
            $s2 = $this->perShiftByPosition(2, $dateFilter);
            $s3 = $this->perShiftByPosition(3, $dateFilter);

            $stmt = $this->pdo->query("
                SELECT
                    operator_id,
                    SUM(total_shifts)   AS total_shifts,
                    SUM(total_ibc_ok)   AS total_ibc_ok,
                    SUM(total_runtime)  AS total_runtime,
                    AVG(avg_kval)       AS avg_kvalitet
                FROM (
                    SELECT operator_id,
                           COUNT(*)            AS total_shifts,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime,
                           AVG(last_kval)      AS avg_kval
                    FROM ($s1) AS x1
                    GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           COUNT(*)            AS total_shifts,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime,
                           AVG(last_kval)      AS avg_kval
                    FROM ($s2) AS x2
                    GROUP BY operator_id

                    UNION ALL

                    SELECT operator_id,
                           COUNT(*)            AS total_shifts,
                           SUM(shift_ibc_ok)   AS total_ibc_ok,
                           SUM(shift_runtime)  AS total_runtime,
                           AVG(last_kval)      AS avg_kval
                    FROM ($s3) AS x3
                    GROUP BY operator_id
                ) AS combined
                GROUP BY operator_id
                HAVING total_runtime > 0
            ");
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($all)) {
                $this->sendSuccess([
                    'your_rank'       => null,
                    'total_operators' => 0,
                    'your_ibc_h'      => null,
                    'your_quality'    => null,
                    'peers'           => [],
                    'week_label'      => $weekLabel,
                ]);
                return;
            }

            // Berakna IBC/h och kvalitet per operator
            $opStats = [];
            foreach ($all as $row) {
                $hours   = (float)$row['total_runtime'] / 60.0;
                $ibcPerH = $hours > 0 ? round((float)$row['total_ibc_ok'] / $hours, 1) : 0.0;
                $quality = round((float)($row['avg_kvalitet'] ?? 0), 1);
                $opStats[] = [
                    'operator_id' => (int)$row['operator_id'],
                    'ibc_h'       => $ibcPerH,
                    'quality'     => $quality,
                ];
            }

            // Sortera fallande pa IBC/h
            usort($opStats, fn($a, $b) => $b['ibc_h'] <=> $a['ibc_h']);

            $totalOps  = count($opStats);
            $yourRank  = null;
            $yourIbcH  = null;
            $yourQual  = null;

            // Bygg anonymiserad peers-lista och finn operatorens position
            $peers = [];
            foreach ($opStats as $idx => $op) {
                $isYou = ($op['operator_id'] === $operatorId);
                if ($isYou) {
                    $yourRank = $idx + 1;
                    $yourIbcH = $op['ibc_h'];
                    $yourQual = $op['quality'];
                }
                $peers[] = [
                    'rank'    => $idx + 1,
                    'ibc_h'   => $op['ibc_h'],
                    'quality' => $op['quality'],
                    'is_you'  => $isYou,
                ];
            }

            $this->sendSuccess([
                'your_rank'       => $yourRank,
                'total_operators' => $totalOps,
                'your_ibc_h'      => $yourIbcH,
                'your_quality'    => $yourQual,
                'peers'           => $peers,
                'week_label'      => $weekLabel,
            ]);

        } catch (PDOException $e) {
            error_log('BonusController::getPeerRanking: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }


}
