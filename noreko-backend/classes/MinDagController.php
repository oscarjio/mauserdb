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
            default:               $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
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
                "SELECT name, initialer FROM operators WHERE number = ? LIMIT 1"
            );
            $stmt->execute([$opId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'name'      => $row['name']      ?? 'Operatör #' . $opId,
                'initialer' => $row['initialer'] ?? '',
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
                "SELECT target_value FROM rebotling_production_goals
                 WHERE period_type = 'daily'
                 ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== false ? (int)$val : 200;
        } catch (\PDOException) {
            return 200;
        }
    }

    /**
     * Hämta snittcykeltid för hela teamet de senaste 30 dagarna (sekunder).
     * Används för jämförelse i KPI-korten.
     */
    private function getTeamAvgCycleSek(): float {
        try {
            $since = date('Y-m-d', strtotime('-30 days'));
            $stmt  = $this->pdo->prepare("
                SELECT AVG(cykel_sek) AS snitt
                FROM (
                    SELECT skiftraknare,
                           SUM(shift_runtime_sek) / NULLIF(SUM(shift_ibc_ok), 0) AS cykel_sek
                    FROM (
                        SELECT skiftraknare,
                               MAX(runtime_plc) * 60  AS shift_runtime_sek,
                               MAX(ibc_ok)             AS shift_ibc_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) >= :since
                          AND skiftraknare IS NOT NULL
                          AND runtime_plc IS NOT NULL
                        GROUP BY skiftraknare
                    ) AS ps
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute(['since' => $since]);
            $val = $stmt->fetchColumn();
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
        $opFilter = "(op1 = :op_id OR op2 = :op_id OR op3 = :op_id)";
        $opInfo   = $this->getOperatorInfo($opId);

        try {
            // Steg 1: per skift — kumulativa fält med MAX()
            $stmt = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    MAX(ibc_ok)      AS shift_ibc_ok,
                    MAX(ibc_ej_ok)   AS shift_ibc_ej_ok,
                    MAX(runtime_plc) AS shift_runtime_min,
                    SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                    SUBSTRING_INDEX(GROUP_CONCAT(kvalitet      ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet
                FROM rebotling_ibc
                WHERE $opFilter
                  AND DATE(datum) = :today
                  AND skiftraknare IS NOT NULL
                GROUP BY skiftraknare
            ");
            $stmt->execute(['op_id' => $opId, 'today' => $today]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

            // 30-dagarssnitt för operatören (IBC per dag med produktion)
            $since30 = date('Y-m-d', strtotime('-30 days'));
            $stmtSnitt = $this->pdo->prepare("
                SELECT AVG(dag_ibc) AS snitt_ibc
                FROM (
                    SELECT DATE(datum) AS dag, SUM(shift_ibc_ok) AS dag_ibc
                    FROM (
                        SELECT DATE(datum) AS datum, skiftraknare, MAX(ibc_ok) AS shift_ibc_ok
                        FROM rebotling_ibc
                        WHERE $opFilter
                          AND DATE(datum) >= :since30
                          AND DATE(datum) < :today
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ps
                    GROUP BY dag
                ) AS per_day
            ");
            $stmtSnitt->execute(['op_id' => $opId, 'since30' => $since30, 'today' => $today]);
            $snittIbc30d = (float)($stmtSnitt->fetchColumn() ?? 0);

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
        $opFilter = "(op1 = :op_id OR op2 = :op_id OR op3 = :op_id)";

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
                  AND DATE(datum) = :today
                  AND skiftraknare IS NOT NULL
                GROUP BY HOUR(datum)
                ORDER BY timme ASC
            ");
            $stmt->execute(['op_id' => $opId, 'today' => $today]);
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

        $today    = date('Y-m-d');
        $opFilter = "(op1 = :op_id OR op2 = :op_id OR op3 = :op_id)";

        try {
            // Hämta dagens produktion
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc_ok)    AS total_ibc_ok,
                    SUM(shift_ibc_ej_ok) AS total_ibc_ej_ok,
                    AVG(last_kvalitet)   AS snitt_kvalitet
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(ibc_ok)    AS shift_ibc_ok,
                        MAX(ibc_ej_ok) AS shift_ibc_ej_ok,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_kvalitet
                    FROM rebotling_ibc
                    WHERE $opFilter
                      AND DATE(datum) = :today
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) AS ps
            ");
            $stmt->execute(['op_id' => $opId, 'today' => $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

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
