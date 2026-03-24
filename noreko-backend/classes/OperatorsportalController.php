<?php
/**
 * OperatorsportalController.php
 * Personlig dashboard för inloggad operatör
 *
 * Endpoints via ?action=operatorsportal&run=XXX:
 *   - run=my-stats   → personlig statistik: IBC idag/vecka/månad, IBC/h snitt, ranking, teamsnitt
 *   - run=my-trend   → daglig IBC-tidsserie för inloggad operatör + teamsnitt per dag (days=N)
 *   - run=my-bonus   → bonusberäkning: timmar, IBC, IBC/h, teamsnitt IBC/h, bonus-poäng
 *
 * Identifiering:
 *   - $_SESSION['operator_id'] = operators.number (sätts vid inloggning)
 *   - rebotling_ibc.op1/op2/op3 = operators.number
 *
 * OBS: ibc_ok, runtime_plc m.fl. är KUMULATIVA PLC-värden per skift.
 * Aggregering sker i två steg: MAX() per skiftraknare, sedan SUM()/AVG() över skift.
 */
class OperatorsportalController {
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

        $opId = $_SESSION['operator_id'] ?? null;
        if (!$opId || (int)$opId <= 0) {
            $this->sendError('Inget operatörskonto kopplat till din användare', 403);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'my-stats': $this->getMyStats((int)$opId);  break;
            case 'my-trend': $this->getMyTrend((int)$opId);  break;
            case 'my-bonus': $this->getMyBonus((int)$opId);  break;
            default:         $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
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

    /**
     * Hämtar kumulativt aggregerade IBC (ibc_ok) för en operatör under ett datumintervall.
     * Steg 1: MAX(ibc_ok) per skiftraknare → ger skiftets faktiska produktion.
     * Steg 2: SUM() över alla skift → total produktion för perioden.
     */
    private function getOperatorIbc(int $opId, string $fromDate, string $toDate): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ibc), 0)
                FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([
                ':op_id'     => $opId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getOperatorIbc: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Hämtar total runtime (runtime_plc i minuter) för en operatör under ett datumintervall.
     * Kumulativ — används MAX() per skiftraknare.
     */
    private function getOperatorRuntime(int $opId, string $fromDate, string $toDate): float {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_runtime), 0)
                FROM (
                    SELECT MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([
                ':op_id'     => $opId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            return (float)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getOperatorRuntime: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Hämtar total team-IBC för ett datumintervall (alla operatörer).
     * Aggregerar per skiftraknare med MAX(), sedan SUM().
     */
    private function getTeamIbc(string $fromDate, string $toDate): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_ibc), 0)
                FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getTeamIbc: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Hämtar antalet aktiva operatörer som jobbat under perioden.
     */
    private function getActiveOperatorCount(string $fromDate, string $toDate): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT op_id)
                FROM (
                    SELECT op1 AS op_id FROM rebotling_ibc
                    WHERE op1 IS NOT NULL AND op1 > 0
                      AND DATE(datum) BETWEEN :from_date1 AND :to_date1
                    UNION
                    SELECT op2 FROM rebotling_ibc
                    WHERE op2 IS NOT NULL AND op2 > 0
                      AND DATE(datum) BETWEEN :from_date2 AND :to_date2
                    UNION
                    SELECT op3 FROM rebotling_ibc
                    WHERE op3 IS NOT NULL AND op3 > 0
                      AND DATE(datum) BETWEEN :from_date3 AND :to_date3
                ) AS all_ops
            ");
            $stmt->execute([
                ':from_date1' => $fromDate,
                ':to_date1'   => $toDate,
                ':from_date2' => $fromDate,
                ':to_date2'   => $toDate,
                ':from_date3' => $fromDate,
                ':to_date3'   => $toDate,
            ]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getActiveOperatorCount: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Hämtar nuvarande skift och status.
     */
    private function getCurrentShift(int $opId): array {
        try {
            $today = date('Y-m-d');
            $stmt  = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))      AS ibc_ok,
                    MAX(COALESCE(runtime_plc, 0)) AS runtime_min,
                    MAX(datum)                    AS senaste_aktivitet
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                  AND DATE(datum) = :today
                  AND skiftraknare IS NOT NULL
                GROUP BY skiftraknare
                ORDER BY MAX(datum) DESC
                LIMIT 1
            ");
            $stmt->execute([':op_id' => $opId, ':today' => $today]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'aktiv'             => false,
                    'skiftraknare'      => null,
                    'ibc_idag'          => 0,
                    'runtime_min'       => 0,
                    'senaste_aktivitet' => null,
                ];
            }

            // Aktivt om senaste registrering är inom 30 minuter
            $senaste    = !empty($row['senaste_aktivitet']) ? strtotime($row['senaste_aktivitet']) : false;
            $aktiv      = ($senaste !== false) && (time() - $senaste) < 1800;

            return [
                'aktiv'             => $aktiv,
                'skiftraknare'      => $row['skiftraknare'],
                'ibc_idag'          => (int)$row['ibc_ok'],
                'runtime_min'       => round((float)$row['runtime_min'], 1),
                'senaste_aktivitet' => $row['senaste_aktivitet'],
            ];
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getCurrentShift: ' . $e->getMessage());
            return ['aktiv' => false, 'skiftraknare' => null, 'ibc_idag' => 0, 'runtime_min' => 0, 'senaste_aktivitet' => null];
        }
    }

    /**
     * Beräknar ranking-position för operatören bland aktiva operatörer (senaste 30 dagar).
     * Rangordnat på total IBC.
     */
    private function getRankingPosition(int $opId, string $fromDate, string $toDate): array {
        try {
            // Hämta IBC per operatör
            $stmt = $this->pdo->prepare("
                SELECT op_id, COALESCE(SUM(shift_ibc), 0) AS total_ibc
                FROM (
                    SELECT op1 AS op_id, MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE op1 IS NOT NULL AND op1 > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from1 AND :to1
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2, MAX(COALESCE(ibc_ok, 0))
                    FROM rebotling_ibc
                    WHERE op2 IS NOT NULL AND op2 > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from2 AND :to2
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3, MAX(COALESCE(ibc_ok, 0))
                    FROM rebotling_ibc
                    WHERE op3 IS NOT NULL AND op3 > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from3 AND :to3
                    GROUP BY op3, skiftraknare
                ) AS all_shifts
                GROUP BY op_id
                ORDER BY total_ibc DESC
            ");
            $stmt->execute([
                ':from1' => $fromDate, ':to1' => $toDate,
                ':from2' => $fromDate, ':to2' => $toDate,
                ':from3' => $fromDate, ':to3' => $toDate,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalOps = count($rows);
            $position = 0;
            foreach ($rows as $i => $r) {
                if ((int)$r['op_id'] === $opId) {
                    $position = $i + 1;
                    break;
                }
            }

            return [
                'position'  => $position,
                'total_ops' => $totalOps,
            ];
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getRankingPosition: ' . $e->getMessage());
            return ['position' => 0, 'total_ops' => 0];
        }
    }

    // ================================================================
    // ENDPOINT: my-stats
    // ================================================================

    /**
     * GET ?action=operatorsportal&run=my-stats
     * Returnerar:
     *   - operator_name, operator_id
     *   - ibc_idag, ibc_vecka, ibc_manad
     *   - team_snitt_idag, team_snitt_vecka, team_snitt_manad
     *   - ibc_per_timme (snitt, senaste 30 dagar)
     *   - team_ibc_per_timme
     *   - ranking (position + total antal operatörer)
     *   - skift (nuvarande skift och status)
     */
    private function getMyStats(int $opId): void {
        $today      = date('Y-m-d');
        // Bugfix #285: strtotime('monday this week') ger nasta mandag pa sondagar
        $weekStart  = date('Y-m-d', strtotime('-' . ((int)date('N') - 1) . ' days'));
        $monthStart = date('Y-m-01');
        $month30    = date('Y-m-d', strtotime('-30 days'));

        try {
            // Operatörsnamn
            $stmtName = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $stmtName->execute([$opId]);
            $opName = $stmtName->fetchColumn() ?: 'Okänd';

            // Personlig produktion
            $ibcIdag   = $this->getOperatorIbc($opId, $today, $today);
            $ibcVecka  = $this->getOperatorIbc($opId, $weekStart, $today);
            $ibcManad  = $this->getOperatorIbc($opId, $monthStart, $today);

            // Team-total och antal operatörer
            $teamIdag  = $this->getTeamIbc($today, $today);
            $teamVecka = $this->getTeamIbc($weekStart, $today);
            $teamManad = $this->getTeamIbc($monthStart, $today);

            $nIdag  = max(1, $this->getActiveOperatorCount($today, $today));
            $nVecka = max(1, $this->getActiveOperatorCount($weekStart, $today));
            $nManad = max(1, $this->getActiveOperatorCount($monthStart, $today));

            $teamSnittIdag  = round($teamIdag  / $nIdag,  1);
            $teamSnittVecka = round($teamVecka / $nVecka, 1);
            $teamSnittManad = round($teamManad / $nManad, 1);

            // IBC/timme (senaste 30 dagar)
            $myIbc30   = $this->getOperatorIbc($opId, $month30, $today);
            $myRuntime = $this->getOperatorRuntime($opId, $month30, $today); // minuter

            $myIbcPerH = 0.0;
            if ($myRuntime > 0) {
                $myIbcPerH = round($myIbc30 / ($myRuntime / 60), 2);
            }

            // Team IBC/timme (senaste 30 dagar) — alla operatörer sammanlagt / deras runtime
            $teamIbc30     = $this->getTeamIbc($month30, $today);
            $teamRuntime30 = $this->getTeamRuntime30($month30, $today);
            $nOps30        = max(1, $this->getActiveOperatorCount($month30, $today));

            $teamIbcPerH = 0.0;
            if ($teamRuntime30 > 0) {
                $teamIbcPerH = round(($teamIbc30 / ($teamRuntime30 / 60)) / $nOps30, 2);
            }

            // Ranking (senaste 30 dagar)
            $ranking = $this->getRankingPosition($opId, $month30, $today);

            // Nuvarande skift
            $skift = $this->getCurrentShift($opId);

            $this->sendSuccess([
                'operator_id'         => $opId,
                'operator_name'       => $opName,
                'ibc_idag'            => $ibcIdag,
                'ibc_vecka'           => $ibcVecka,
                'ibc_manad'           => $ibcManad,
                'team_snitt_idag'     => $teamSnittIdag,
                'team_snitt_vecka'    => $teamSnittVecka,
                'team_snitt_manad'    => $teamSnittManad,
                'ibc_per_timme'       => $myIbcPerH,
                'team_ibc_per_timme'  => $teamIbcPerH,
                'ranking'             => $ranking,
                'skift'               => $skift,
            ]);
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getMyStats: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Hämtar total team-runtime (alla operatörer) i minuter.
     * Kumulativ — MAX() per skiftraknare.
     */
    private function getTeamRuntime30(string $fromDate, string $toDate): float {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_runtime), 0)
                FROM (
                    SELECT MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            return (float)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getTeamRuntime30: ' . $e->getMessage());
            return 0.0;
        }
    }

    // ================================================================
    // ENDPOINT: my-trend
    // ================================================================

    /**
     * GET ?action=operatorsportal&run=my-trend&days=30
     * Returnerar daglig IBC-tidsserie för operatören + teamsnitt per dag.
     */
    private function getMyTrend(int $opId): void {
        $days     = max(7, min(90, intval($_GET['days'] ?? 30)));
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $toDate   = date('Y-m-d');

        try {
            // Operatörens IBC per dag
            $stmtOp = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, COALESCE(SUM(shift_ibc), 0) AS ibc
                FROM (
                    SELECT datum,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY DATE(datum)
                ORDER BY DATE(datum)
            ");
            $stmtOp->execute([
                ':op_id'     => $opId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $opRows = $stmtOp->fetchAll(\PDO::FETCH_ASSOC);

            // Team-IBC per dag + antal aktiva operatörer per dag
            $stmtTeam = $this->pdo->prepare("
                SELECT dag, COALESCE(SUM(shift_ibc), 0) AS team_ibc, COUNT(DISTINCT op_id) AS n_ops
                FROM (
                    SELECT DATE(datum) AS dag, op1 AS op_id,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                    FROM rebotling_ibc
                    WHERE op1 IS NOT NULL AND op1 > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from1 AND :to1
                    GROUP BY DATE(datum), op1, skiftraknare
                    UNION ALL
                    SELECT DATE(datum), op2,
                           MAX(COALESCE(ibc_ok, 0))
                    FROM rebotling_ibc
                    WHERE op2 IS NOT NULL AND op2 > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from2 AND :to2
                    GROUP BY DATE(datum), op2, skiftraknare
                    UNION ALL
                    SELECT DATE(datum), op3,
                           MAX(COALESCE(ibc_ok, 0))
                    FROM rebotling_ibc
                    WHERE op3 IS NOT NULL AND op3 > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from3 AND :to3
                    GROUP BY DATE(datum), op3, skiftraknare
                ) AS alla_skift
                GROUP BY dag
                ORDER BY dag
            ");
            $stmtTeam->execute([
                ':from1' => $fromDate, ':to1' => $toDate,
                ':from2' => $fromDate, ':to2' => $toDate,
                ':from3' => $fromDate, ':to3' => $toDate,
            ]);
            $teamRows = $stmtTeam->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg kartor dag → värde
            $opMap   = [];
            foreach ($opRows as $r) {
                $opMap[$r['dag']] = (int)$r['ibc'];
            }
            $teamMap = [];
            foreach ($teamRows as $r) {
                $n = max(1, (int)$r['n_ops']);
                $teamMap[$r['dag']] = round((float)$r['team_ibc'] / $n, 1);
            }

            // Generera alla dagar i intervallet
            $labels    = [];
            $myValues  = [];
            $teamValues = [];
            $current   = strtotime($fromDate);
            $end       = strtotime($toDate);
            while ($current <= $end) {
                $dag          = date('Y-m-d', $current);
                $labels[]     = $dag;
                $myValues[]   = $opMap[$dag]   ?? 0;
                $teamValues[] = $teamMap[$dag] ?? 0;
                $current      = strtotime('+1 day', $current);
            }

            $this->sendSuccess([
                'labels'       => $labels,
                'my_ibc'       => $myValues,
                'team_snitt'   => $teamValues,
                'days'         => $days,
                'from_date'    => $fromDate,
                'to_date'      => $toDate,
            ]);
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getMyTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: my-bonus
    // ================================================================

    /**
     * GET ?action=operatorsportal&run=my-bonus
     * Returnerar bonusberäkning för inloggad operatör (senaste 30 dagar):
     *   - timmar_arbetade, ibc_totalt, ibc_per_timme
     *   - team_ibc_per_timme (snitt per operatör)
     *   - diff_vs_team (min IBC/h minus teamsnitt IBC/h)
     *   - bonus_poang (senaste bonus_poang från rebotling_ibc)
     *   - bonus_mal (hårdkodad 3.0 poäng som mål)
     *   - bonus_pct (bonus_poang / bonus_mal * 100)
     */
    private function getMyBonus(int $opId): void {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-30 days'));

        try {
            // Personlig runtime + IBC
            $myIbc     = $this->getOperatorIbc($opId, $fromDate, $toDate);
            $myRuntime = $this->getOperatorRuntime($opId, $fromDate, $toDate); // minuter
            $myHours   = round($myRuntime / 60, 2);
            $myIbcPerH = $myHours > 0 ? round($myIbc / $myHours, 2) : 0.0;

            // Team IBC/h snitt per operatör
            $teamIbc     = $this->getTeamIbc($fromDate, $toDate);
            $teamRuntime = $this->getTeamRuntime30($fromDate, $toDate);
            $nOps        = max(1, $this->getActiveOperatorCount($fromDate, $toDate));
            $teamIbcPerH = 0.0;
            if ($teamRuntime > 0) {
                $teamIbcPerH = round(($teamIbc / ($teamRuntime / 60)) / $nOps, 2);
            }

            $diffVsTeam = round($myIbcPerH - $teamIbcPerH, 2);

            // Senaste bonus_poang från rebotling_ibc för operatören
            $stmtBonus = $this->pdo->prepare("
                SELECT COALESCE(
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),
                        '|', 1
                    ) + 0,
                    0
                ) AS senaste_bonus
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                  AND bonus_poang IS NOT NULL
                  AND DATE(datum) BETWEEN :from_date AND :to_date
            ");
            $stmtBonus->execute([
                ':op_id'     => $opId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $bonusPoang = round((float)$stmtBonus->fetchColumn(), 2);

            // Genomsnittlig bonus_poang (per skift)
            $stmtAvgBonus = $this->pdo->prepare("
                SELECT COALESCE(AVG(last_bonus), 0) AS avg_bonus
                FROM (
                    SELECT SUBSTRING_INDEX(
                               GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),
                               '|', 1
                           ) + 0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                      AND skiftraknare IS NOT NULL
                      AND bonus_poang IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY skiftraknare
                ) AS per_skift
            ");
            $stmtAvgBonus->execute([
                ':op_id'     => $opId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $avgBonusPoang = round((float)$stmtAvgBonus->fetchColumn(), 2);

            $bonusMal = 3.0;
            $bonusPct = $bonusMal > 0 ? min(100, round($avgBonusPoang / $bonusMal * 100, 1)) : 0;

            // Antal skift
            $stmtSkift = $this->pdo->prepare("
                SELECT COUNT(DISTINCT skiftraknare)
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                  AND skiftraknare IS NOT NULL
                  AND DATE(datum) BETWEEN :from_date AND :to_date
            ");
            $stmtSkift->execute([
                ':op_id'     => $opId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $antalSkift = (int)$stmtSkift->fetchColumn();

            $this->sendSuccess([
                'timmar_arbetade'     => $myHours,
                'ibc_totalt'          => $myIbc,
                'ibc_per_timme'       => $myIbcPerH,
                'team_ibc_per_timme'  => $teamIbcPerH,
                'diff_vs_team'        => $diffVsTeam,
                'bonus_poang'         => $bonusPoang,
                'avg_bonus_poang'     => $avgBonusPoang,
                'bonus_mal'           => $bonusMal,
                'bonus_pct'           => $bonusPct,
                'antal_skift'         => $antalSkift,
                'period_from'         => $fromDate,
                'period_to'           => $toDate,
            ]);
        } catch (\PDOException $e) {
            error_log('OperatorsportalController::getMyBonus: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
