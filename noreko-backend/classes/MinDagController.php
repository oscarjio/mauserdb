<?php
/**
 * MinDagController.php
 * Personlig operatörs-dashboard "Min dag"
 *
 * Endpoints via ?action=min-dag&run=XXX:
 *   - run=today-summary  → dagens IBC-count, snittcykeltid, kvalitetsprocent, bonuspoäng
 *   - run=cycle-trend    → cykeltider per timme idag (för linjediagram)
 *   - run=goals-progress → progress mot dagsmål (IBC-mål, kvalitetsmål)
 *
 * Operatör hämtas från ?operator=<id> eller session->operator_id.
 *
 * VIKTIGT: ibc_ok, ibc_ej_ok är KUMULATIVA PLC-värden per skift.
 * Aggregering sker i två steg: MAX() per skift, sedan SUM() över skift.
 */
class MinDagController {
    private $pdo;

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
            case 'today-summary':  $this->getTodaySummary();  break;
            case 'cycle-trend':    $this->getCycleTrend();    break;
            case 'goals-progress': $this->getGoalsProgress(); break;
            default:               $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    /**
     * Hämta operatör-ID: från ?operator= eller session->operator_id.
     */
    private function getOperatorId(): ?int {
        if (!empty($_GET['operator'])) {
            $id = intval($_GET['operator']);
            return $id > 0 ? $id : null;
        }
        if (!empty($_SESSION['operator_id'])) {
            $id = intval($_SESSION['operator_id']);
            return $id > 0 ? $id : null;
        }
        return null;
    }

    /**
     * Hämta operatörens namn och initialer från operators-tabellen.
     */
    private function getOperatorInfo(int $opId): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT name FROM operators WHERE number = ? LIMIT 1"
            );
            $stmt->execute([$opId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $name = $row['name'] ?? 'Operatör #' . $opId;
            // Generera initialer från namn (operators-tabellen saknar initialer-kolumn)
            $parts = explode(' ', trim($name));
            $ini = '';
            foreach ($parts as $p) {
                if ($p !== '') $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            }
            return [
                'name'      => $name,
                'initialer' => mb_substr($ini, 0, 3) ?: '',
            ];
        } catch (\PDOException $e) {
            error_log('MinDagController::getOperatorInfo: ' . $e->getMessage());
            return ['name' => 'Operatör #' . $opId, 'initialer' => ''];
        }
    }

    /**
     * Hämta dagsmål (IBC) från rebotling_production_goals eller returnera default.
     */
    private function getDailyGoal(): int {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT target_count FROM rebotling_production_goals
                 WHERE period_type = 'daily'
                 ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : 200;
        } catch (\PDOException $e) {
            error_log('MinDagController::getDailyGoal: ' . $e->getMessage());
            return 200;
        }
    }

    /**
     * Snittcykeltid för hela teamet de senaste 30 dagarna (sekunder), LAG-korrigerad.
     */
    private function getTeamAvgCycleSek(): float {
        try {
            $since = $this->pdo->quote(date('Y-m-d', strtotime('-30 days')));
            $sql = "
                WITH lag_base AS (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))      AS ibc_end,
                           MAX(COALESCE(runtime_plc, 0))  AS runtime_end
                    FROM rebotling_ibc
                    WHERE datum >= {$since} AND runtime_plc IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ),
                lag_shifts AS (
                    SELECT dag, skiftraknare,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc,
                           runtime_end AS shift_runtime_min
                    FROM lag_base
                )
                SELECT AVG(shift_runtime_min * 60 / NULLIF(shift_ibc, 0)) AS snitt
                FROM lag_shifts
                WHERE shift_ibc > 0
            ";
            $val = $this->pdo->query($sql)->fetchColumn();
            return $val !== null && $val > 0 ? (float)$val : 0.0;
        } catch (\PDOException $e) {
            error_log('MinDagController::getTeamAvgCycleSek: ' . $e->getMessage());
            return 0.0;
        }
    }

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

    // ================================================================
    // ENDPOINT: today-summary
    // ================================================================

    /**
     * Dagens sammanfattning för en operatör:
     * - Totalt IBC idag
     * - Snittcykeltid (sekunder)
     * - Kvalitetsprocent (ibc_ok / (ibc_ok + ibc_ej_ok))
     * - Bonuspoäng senaste skiftet idag
     * - Jämförelse mot 30-dagarssnitt
     */
    private function getTodaySummary(): void {
        $opId = $this->getOperatorId();
        if (!$opId) {
            $this->sendError('Operatör-ID saknas. Ange ?operator=<id> eller koppla operatör till kontot.');
            return;
        }

        $today    = date('Y-m-d');
        $qToday   = $this->pdo->quote($today);
        $opInfo   = $this->getOperatorInfo($opId);

        try {
            // LAG-korrigerad per-skift-query: operator-filter på yttre nivå så LAG ser alla skift
            $shifts = $this->pdo->query("
                WITH lag_base AS (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))     AS ibc_end,
                           MAX(COALESCE(ibc_ej_ok, 0))  AS ibc_ej_end,
                           MAX(COALESCE(runtime_plc, 0)) AS runtime_end,
                           SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                           SUBSTRING_INDEX(GROUP_CONCAT(kvalitet    ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet,
                           MIN(COALESCE(op1, 0)) AS op1,
                           MIN(COALESCE(op2, 0)) AS op2,
                           MIN(COALESCE(op3, 0)) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= {$qToday} AND datum < DATE_ADD({$qToday}, INTERVAL 1 DAY)
                    GROUP BY skiftraknare
                ),
                lag_shifts AS (
                    SELECT skiftraknare, op1, op2, op3, last_bonus, last_kvalitet,
                           GREATEST(0, ibc_end    - COALESCE(LAG(ibc_end)    OVER (ORDER BY skiftraknare), 0)) AS shift_ibc_ok,
                           GREATEST(0, ibc_ej_end - COALESCE(LAG(ibc_ej_end) OVER (ORDER BY skiftraknare), 0)) AS shift_ibc_ej_ok,
                           runtime_end AS shift_runtime_min
                    FROM lag_base
                )
                SELECT skiftraknare, shift_ibc_ok, shift_ibc_ej_ok, shift_runtime_min, last_bonus, last_kvalitet
                FROM lag_shifts
                WHERE op1 = {$opId} OR op2 = {$opId} OR op3 = {$opId}
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (empty($shifts)) {
                // Inga data idag — returnera noll-sammanfattning
                $this->sendSuccess([
                    'operator_id'    => $opId,
                    'operator_name'  => $opInfo['name'],
                    'initialer'      => $opInfo['initialer'],
                    'datum'          => $today,
                    'ibc_today'      => 0,
                    'snitt_cykel_sek'=> 0,
                    'kvalitet_pct'   => 0,
                    'bonus_poang'    => 0,
                    'vs_team_cykel'  => 0,   // diff i sekunder vs team-snitt
                    'team_snitt_sek' => $this->getTeamAvgCycleSek(),
                    'antal_skift'    => 0,
                    'har_data'       => false,
                ]);
                return;
            }

            // Steg 2: aggregera över skift
            $totalIbcOk    = 0;
            $totalIbcEjOk  = 0;
            $totalRunMin   = 0;
            $lastBonus     = 0;
            $lastKvalitet  = 0;
            foreach ($shifts as $s) {
                $totalIbcOk   += (int)$s['shift_ibc_ok'];
                $totalIbcEjOk += (int)$s['shift_ibc_ej_ok'];
                $totalRunMin  += (float)$s['shift_runtime_min'];
                $lastBonus    = max($lastBonus,    (float)$s['last_bonus']);
                $lastKvalitet = max($lastKvalitet, (float)$s['last_kvalitet']);
            }

            $totalIbc    = $totalIbcOk + $totalIbcEjOk;
            $kvalitetPct = $totalIbc > 0
                ? round($totalIbcOk / $totalIbc * 100, 1)
                : ($lastKvalitet > 0 ? round((float)$lastKvalitet, 1) : 0);

            // Snittcykeltid i sekunder: total runtime i minuter / antal ok IBC
            $snittCykelSek = ($totalRunMin > 0 && $totalIbcOk > 0)
                ? round($totalRunMin * 60 / $totalIbcOk, 1)
                : 0;

            $teamSnittSek = $this->getTeamAvgCycleSek();
            $vsTeamCykel  = $teamSnittSek > 0
                ? round($snittCykelSek - $teamSnittSek, 1)
                : 0;

            // 30-dagarssnitt för operatören (IBC per dag, LAG-korrigerat)
            $since30  = $this->pdo->quote(date('Y-m-d', strtotime('-30 days')));
            $snittIbc30d = (float)($this->pdo->query("
                WITH lag_base AS (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_end,
                           MIN(COALESCE(op1, 0)) AS op1,
                           MIN(COALESCE(op2, 0)) AS op2,
                           MIN(COALESCE(op3, 0)) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= {$since30} AND datum < {$qToday}
                    GROUP BY DATE(datum), skiftraknare
                ),
                lag_shifts AS (
                    SELECT dag, op1, op2, op3,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc
                    FROM lag_base
                )
                SELECT AVG(dag_ibc) AS snitt_ibc
                FROM (
                    SELECT dag, SUM(shift_ibc) AS dag_ibc
                    FROM lag_shifts
                    WHERE op1 = {$opId} OR op2 = {$opId} OR op3 = {$opId}
                    GROUP BY dag
                ) AS per_day
            ")->fetchColumn() ?? 0);

            $this->sendSuccess([
                'operator_id'    => $opId,
                'operator_name'  => $opInfo['name'],
                'initialer'      => $opInfo['initialer'],
                'datum'          => $today,
                'ibc_today'      => $totalIbcOk,
                'snitt_cykel_sek'=> $snittCykelSek,
                'kvalitet_pct'   => $kvalitetPct,
                'bonus_poang'    => round((float)$lastBonus, 1),
                'vs_team_cykel'  => $vsTeamCykel,
                'team_snitt_sek' => round($teamSnittSek, 1),
                'snitt_ibc_30d'  => round($snittIbc30d, 1),
                'antal_skift'    => count($shifts),
                'har_data'       => true,
            ]);
        } catch (\PDOException $e) {
            error_log('MinDagController::getTodaySummary: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: cycle-trend
    // ================================================================

    /**
     * Cykeltider per timme idag för den inloggade operatören.
     * Returnerar array: [{timme: 6, cykel_sek: 185, ibc: 12}, ...]
     */
    private function getCycleTrend(): void {
        $opId = $this->getOperatorId();
        if (!$opId) {
            $this->sendError('Operatör-ID saknas');
            return;
        }

        $today    = date('Y-m-d');
        // Unika paramnamn per kolumn (ATTR_EMULATE_PREPARES=false kräver unika named params)
        $opFilter = "(op1 = :op_id_a OR op2 = :op_id_b OR op3 = :op_id_c)";

        try {
            // Hämta råcykeltider per timme — använd cykeltid-kolumnen om den finns,
            // annars beräkna från runtime_plc och ibc_ok per timme
            $stmt = $this->pdo->prepare("
                SELECT
                    HOUR(datum)                        AS timme,
                    COUNT(*)                           AS antal_rader,
                    MAX(ibc_ok)  - MIN(ibc_ok)         AS ibc_denna_timme,
                    MAX(runtime_plc) - MIN(runtime_plc) AS runtime_denna_timme_min
                FROM rebotling_ibc
                WHERE $opFilter
                  AND datum >= :today AND datum < DATE_ADD(:todayb, INTERVAL 1 DAY)

                GROUP BY HOUR(datum)
                ORDER BY timme ASC
            ");
            $stmt->execute(['op_id_a' => $opId, 'op_id_b' => $opId, 'op_id_c' => $opId, 'today' => $today, 'todayb' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $trend = [];
            foreach ($rows as $r) {
                $ibc     = max(0, (int)$r['ibc_denna_timme']);
                $runMin  = max(0, (float)$r['runtime_denna_timme_min']);
                $cykelSek = ($ibc > 0 && $runMin > 0)
                    ? round($runMin * 60 / $ibc, 1)
                    : 0;

                $trend[] = [
                    'timme'    => (int)$r['timme'],
                    'label'    => sprintf('%02d:00', (int)$r['timme']),
                    'cykel_sek'=> $cykelSek,
                    'ibc'      => $ibc,
                ];
            }

            // Teamsnitt för mållinjen
            $teamSnittSek = $this->getTeamAvgCycleSek();

            $this->sendSuccess([
                'trend'         => $trend,
                'mal_sek'       => round($teamSnittSek, 1),
                'datum'         => $today,
                'har_data'      => !empty($trend),
            ]);
        } catch (\PDOException $e) {
            error_log('MinDagController::getCycleTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: goals-progress
    // ================================================================

    /**
     * Progress mot dagsmål:
     * - IBC-mål: hur många IBC operatören producerat idag vs dagsmål
     * - Kvalitetsmål: % godkända vs 95 %-mål
     */
    private function getGoalsProgress(): void {
        $opId = $this->getOperatorId();
        if (!$opId) {
            $this->sendError('Operatör-ID saknas');
            return;
        }

        $today  = date('Y-m-d');
        $qToday = $this->pdo->quote($today);

        try {
            // LAG-korrigerad per-skift-aggregering; operator-filter på yttre nivå
            $row = $this->pdo->query("
                WITH lag_base AS (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))    AS ibc_end,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_end,
                           SUBSTRING_INDEX(GROUP_CONCAT(kvalitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet,
                           MIN(COALESCE(op1, 0)) AS op1,
                           MIN(COALESCE(op2, 0)) AS op2,
                           MIN(COALESCE(op3, 0)) AS op3
                    FROM rebotling_ibc
                    WHERE datum >= {$qToday} AND datum < DATE_ADD({$qToday}, INTERVAL 1 DAY)
                    GROUP BY skiftraknare
                ),
                lag_shifts AS (
                    SELECT skiftraknare, op1, op2, op3, last_kvalitet,
                           GREATEST(0, ibc_end    - COALESCE(LAG(ibc_end)    OVER (ORDER BY skiftraknare), 0)) AS shift_ibc_ok,
                           GREATEST(0, ibc_ej_end - COALESCE(LAG(ibc_ej_end) OVER (ORDER BY skiftraknare), 0)) AS shift_ibc_ej_ok
                    FROM lag_base
                )
                SELECT
                    SUM(shift_ibc_ok)    AS total_ibc_ok,
                    SUM(shift_ibc_ej_ok) AS total_ibc_ej_ok,
                    AVG(last_kvalitet)   AS snitt_kvalitet
                FROM lag_shifts
                WHERE op1 = {$opId} OR op2 = {$opId} OR op3 = {$opId}
            ")->fetch(PDO::FETCH_ASSOC);

            $ibcOk    = (int)($row['total_ibc_ok']    ?? 0);
            $ibcEjOk  = (int)($row['total_ibc_ej_ok'] ?? 0);
            $totalIbc = $ibcOk + $ibcEjOk;

            $kvalitetPct = $totalIbc > 0
                ? round($ibcOk / $totalIbc * 100, 1)
                : (float)($row['snitt_kvalitet'] ?? 0);

            // Mål
            $ibcMal       = $this->getDailyGoal();
            $kvalitetsMal = 95.0; // fast mål 95 %

            $ibcProgress       = $ibcMal > 0 ? min(100, round($ibcOk / $ibcMal * 100, 1)) : 0;
            $kvalitetProgress  = $kvalitetsMal > 0 ? min(100, round($kvalitetPct / $kvalitetsMal * 100, 1)) : 0;

            $this->sendSuccess([
                'ibc' => [
                    'actual'   => $ibcOk,
                    'mal'      => $ibcMal,
                    'progress' => $ibcProgress,
                    'kvar'     => max(0, $ibcMal - $ibcOk),
                ],
                'kvalitet' => [
                    'actual'   => round($kvalitetPct, 1),
                    'mal'      => $kvalitetsMal,
                    'progress' => $kvalitetProgress,
                ],
                'datum'    => $today,
                'har_data' => ($ibcOk + $ibcEjOk) > 0,
            ]);
        } catch (\PDOException $e) {
            error_log('MinDagController::getGoalsProgress: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
