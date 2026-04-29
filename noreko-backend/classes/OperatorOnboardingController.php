<?php
/**
 * OperatorOnboardingController.php
 * Operatörs-onboarding tracker — visar hur snabbt nya operatörer
 * når teamgenomsnitt i IBC/h under sina första veckor.
 *
 * Endpoints via ?action=operator-onboarding&run=XXX:
 *
 *   run=overview&months=3|6|12
 *       Alla operatörer med onboarding-status, KPI-kort.
 *       Returnerar: { operatorer, kpi, team_snitt_ibc_h, months }
 *
 *   run=operator-curve&operator_number=X
 *       Veckovis IBC/h de första 12 veckorna för en operatör.
 *       Returnerar: { operator, weeks: [{week, ibc_h}], team_snitt_ibc_h }
 *
 *   run=team-stats
 *       Teamstatistik: snitt IBC/h, antal aktiva operatörer.
 *       Returnerar: { team_snitt_ibc_h, antal_aktiva }
 *
 * Auth: session_id krävs (401 om ej inloggad).
 *
 * Tabeller: rebotling_skiftrapport (op1/op2/op3, ibc_ok, drifttid, datum),
 *           operators (number, name, active)
 */
class OperatorOnboardingController {
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
            case 'overview':        $this->getOverview();       break;
            case 'operator-curve':  $this->getOperatorCurve();  break;
            case 'team-stats':      $this->getTeamStats();      break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
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

    private function getMonths(): int {
        $m = (int)($_GET['months'] ?? 6);
        if (!in_array($m, [3, 6, 12], true)) return 6;
        return $m;
    }

    /**
     * Hämta första datum en operatör registrerades i rebotling_skiftrapport.
     * Returnerar: [operator_number => 'YYYY-MM-DD']
     */
    private function getFirstDates(): array {
        $result = [];
        try {
            $stmt = $this->pdo->query(
                "SELECT op_num, MIN(datum) AS first_date FROM (
                    SELECT op1 AS op_num, datum FROM rebotling_skiftrapport WHERE op1 IS NOT NULL
                    UNION ALL
                    SELECT op2 AS op_num, datum FROM rebotling_skiftrapport WHERE op2 IS NOT NULL
                    UNION ALL
                    SELECT op3 AS op_num, datum FROM rebotling_skiftrapport WHERE op3 IS NOT NULL
                ) AS sub
                GROUP BY op_num
                ORDER BY op_num"
            );
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['op_num']] = $row['first_date'];
            }
        } catch (\PDOException $e) {
            error_log('OperatorOnboardingController::getFirstDates: ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Hämta alla operatörer med namn.
     * Returnerar: [number => name]
     */
    private function getOperatorNames(): array {
        $result = [];
        try {
            $stmt = $this->pdo->query("SELECT number, name FROM operators ORDER BY name");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['number']] = $row['name'];
            }
        } catch (\PDOException $e) {
            error_log('OperatorOnboardingController::getOperatorNames: ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Beräkna IBC/h per vecka för en operatör från deras startdatum.
     * Returnerar: [{week => N, ibc_h => float, ibc_ok => int, drifttid_min => int}]
     */
    private function getWeeklyCurve(int $opNumber, string $startDate, int $maxWeeks = 12): array {
        $weeks = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    FLOOR(DATEDIFF(sub.datum, ?) / 7) + 1 AS vecka,
                    SUM(sub.ibc_ok) AS ibc_ok,
                    SUM(sub.drifttid) AS drifttid_min
                 FROM (
                    SELECT datum, ibc_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 = ? AND datum >= ? AND datum < DATE_ADD(?, INTERVAL ? WEEK)
                    UNION ALL
                    SELECT datum, ibc_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op2 = ? AND datum >= ? AND datum < DATE_ADD(?, INTERVAL ? WEEK)
                    UNION ALL
                    SELECT datum, ibc_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op3 = ? AND datum >= ? AND datum < DATE_ADD(?, INTERVAL ? WEEK)
                 ) AS sub
                 GROUP BY vecka
                 HAVING vecka >= 1 AND vecka <= ?
                 ORDER BY vecka"
            );
            $stmt->execute([
                $startDate,
                $opNumber, $startDate, $startDate, $maxWeeks,
                $opNumber, $startDate, $startDate, $maxWeeks,
                $opNumber, $startDate, $startDate, $maxWeeks,
                $maxWeeks
            ]);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $ibc = (int)$row['ibc_ok'];
                $drift = (int)$row['drifttid_min'];
                $ibc_h = $drift > 0 ? round($ibc / ($drift / 60.0), 1) : 0.0;
                $weeks[] = [
                    'week'         => (int)$row['vecka'],
                    'ibc_h'        => $ibc_h,
                    'ibc_ok'       => $ibc,
                    'drifttid_min' => $drift,
                ];
            }
        } catch (\PDOException $e) {
            error_log('OperatorOnboardingController::getWeeklyCurve: ' . $e->getMessage());
        }
        return $weeks;
    }

    /**
     * Beräkna teamgenomsnitt IBC/h (alla operatörer, senaste 90 dagar).
     */
    private function getTeamAverageIbcH(): float {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    SUM(sub.ibc_ok) AS total_ibc,
                    SUM(sub.drifttid) AS total_drifttid
                 FROM (
                    SELECT ibc_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND ibc_ok > 0
                 ) AS sub"
            );
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && (int)$row['total_drifttid'] > 0) {
                return round((int)$row['total_ibc'] / ((int)$row['total_drifttid'] / 60.0), 1);
            }
        } catch (\PDOException $e) {
            error_log('OperatorOnboardingController::getTeamAverageIbcH: ' . $e->getMessage());
        }
        return 0.0;
    }

    /**
     * Batch-hämta nuvarande IBC/h för ALLA operatörer (senaste 30 dagar) i EN query.
     * Eliminerar N+1-problem i overview().
     * @return array<int, float>  op_number => ibc_h
     */
    private function getAllCurrentIbcH(): array {
        $result = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT op_id,
                       SUM(total_ibc) AS total_ibc,
                       SUM(total_drift) AS total_drift
                FROM (
                    SELECT op1 AS op_id, SUM(ibc_ok) AS total_ibc, SUM(COALESCE(drifttid, 0)) AS total_drift
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ibc_ok > 0
                    GROUP BY op1
                    UNION ALL
                    SELECT op2, SUM(ibc_ok), SUM(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE op2 IS NOT NULL AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ibc_ok > 0
                    GROUP BY op2
                    UNION ALL
                    SELECT op3, SUM(ibc_ok), SUM(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE op3 IS NOT NULL AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ibc_ok > 0
                    GROUP BY op3
                ) AS sub
                GROUP BY op_id
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $opId = (int)$row['op_id'];
                $drift = (int)$row['total_drift'];
                $result[$opId] = $drift > 0 ? round((int)$row['total_ibc'] / ($drift / 60.0), 1) : 0.0;
            }
        } catch (\PDOException $e) {
            error_log('OperatorOnboardingController::getAllCurrentIbcH: ' . $e->getMessage());
        }
        return $result;
    }

    // ================================================================
    // run=overview
    // ================================================================

    private function getOverview(): void {
        $months = $this->getMonths();
        // Bugfix: strtotime('-N months') ger fel datum pa manad-slut (t.ex. 31 mars - 1 manad = 3 mars).
        // Anvand DateTime fran forsta dagen i innevarande manad for korrekt manad-aritmetik.
        $cutoffDt = new \DateTime('first day of this month');
        $cutoffDt->modify("-{$months} months");
        $cutoffDate = $cutoffDt->format('Y-m-d');

        try {
            $firstDates = $this->getFirstDates();
            $opNames    = $this->getOperatorNames();
            $teamSnitt  = $this->getTeamAverageIbcH();

            // --- Batch-hämta nuvarande IBC/h för ALLA operatörer i EN query ---
            $allCurrentIbcH = $this->getAllCurrentIbcH();

            // --- Batch-hämta veckor-till-snitt för ALLA operatörer i EN query ---
            $filteredOps = [];
            foreach ($firstDates as $opNum => $firstDate) {
                if ($firstDate < $cutoffDate) continue;
                $filteredOps[$opNum] = $firstDate;
            }
            $batchVeckorTillSnitt = $this->getBatchWeeksToTarget($filteredOps, $teamSnitt, 12);

            $operatorer = [];
            $nyaCount   = 0;
            $totalVeckorTillSnitt = 0;
            $antalNaddSnitt = 0;
            $bastaIbcH  = 0.0;
            $bastaNamn  = '';

            foreach ($filteredOps as $opNum => $firstDate) {
                $name = $opNames[$opNum] ?? "Operatör #{$opNum}";
                $daysSinceFirst = (int)(new \DateTime($firstDate))->diff(new \DateTime('today'))->days;
                $isNy = $daysSinceFirst < 90;
                $veckorAktiv = max(1, (int)ceil($daysSinceFirst / 7));

                $currentIbcH = $allCurrentIbcH[$opNum] ?? 0.0;
                $pctAvSnitt = $teamSnitt > 0 ? round(($currentIbcH / $teamSnitt) * 100, 1) : 0.0;

                // Status: grön >= 90%, gul 70-90%, röd < 70%
                $status = 'rod';
                if ($pctAvSnitt >= 90) $status = 'gron';
                elseif ($pctAvSnitt >= 70) $status = 'gul';

                $veckorTillSnitt = $batchVeckorTillSnitt[$opNum] ?? null;

                if ($veckorTillSnitt !== null) {
                    $totalVeckorTillSnitt += $veckorTillSnitt;
                    $antalNaddSnitt++;
                }

                if ($isNy) $nyaCount++;

                if ($currentIbcH > $bastaIbcH) {
                    $bastaIbcH = $currentIbcH;
                    $bastaNamn = $name;
                }

                $operatorer[] = [
                    'operator_number'   => $opNum,
                    'namn'              => $name,
                    'start_datum'       => $firstDate,
                    'nuvarande_ibc_h'   => $currentIbcH,
                    'team_snitt_ibc_h'  => $teamSnitt,
                    'pct_av_snitt'      => $pctAvSnitt,
                    'veckor_aktiv'      => $veckorAktiv,
                    'veckor_till_snitt' => $veckorTillSnitt,
                    'is_ny'             => $isNy,
                    'status'            => $status,
                ];
            }

            // Sortera: nyaste först
            usort($operatorer, fn($a, $b) => $b['start_datum'] <=> $a['start_datum']);

            $snittVeckorTillSnitt = $antalNaddSnitt > 0
                ? round($totalVeckorTillSnitt / $antalNaddSnitt, 1)
                : null;

            $this->sendSuccess([
                'months'           => $months,
                'operatorer'       => $operatorer,
                'team_snitt_ibc_h' => $teamSnitt,
                'kpi' => [
                    'antal_nya'            => $nyaCount,
                    'snitt_veckor_till_snitt' => $snittVeckorTillSnitt,
                    'basta_nykomling_namn' => $bastaNamn,
                    'basta_nykomling_ibc_h'=> $bastaIbcH,
                    'team_snitt_ibc_h'     => $teamSnitt,
                    'antal_operatorer'     => count($operatorer),
                ],
            ]);

        } catch (\Throwable $e) {
            error_log('OperatorOnboardingController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta onboarding-data', 500);
        }
    }

    // ================================================================
    // run=operator-curve
    // ================================================================

    private function getOperatorCurve(): void {
        $opNumber = (int)($_GET['operator_number'] ?? 0);
        if ($opNumber <= 0) {
            $this->sendError('operator_number krävs');
            return;
        }

        try {
            $firstDates = $this->getFirstDates();
            $opNames    = $this->getOperatorNames();
            $teamSnitt  = $this->getTeamAverageIbcH();

            $startDate = $firstDates[$opNumber] ?? null;
            if (!$startDate) {
                $this->sendError('Operatör har ingen registrerad data');
                return;
            }

            $name  = $opNames[$opNumber] ?? "Operatör #{$opNumber}";
            $weeks = $this->getWeeklyCurve($opNumber, $startDate, 12);

            $this->sendSuccess([
                'operator_number'  => $opNumber,
                'operator_namn'    => $name,
                'start_datum'      => $startDate,
                'team_snitt_ibc_h' => $teamSnitt,
                'weeks'            => $weeks,
            ]);

        } catch (\Throwable $e) {
            error_log('OperatorOnboardingController::getOperatorCurve: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsdata', 500);
        }
    }

    // ================================================================
    // run=team-stats
    // ================================================================

    private function getTeamStats(): void {
        try {
            $teamSnitt = $this->getTeamAverageIbcH();

            // Antal aktiva operatörer (har data senaste 30 dagarna)
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT op_num) AS cnt FROM (
                    SELECT op1 AS op_num FROM rebotling_skiftrapport WHERE op1 IS NOT NULL AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ibc_ok > 0
                    UNION ALL
                    SELECT op2 AS op_num FROM rebotling_skiftrapport WHERE op2 IS NOT NULL AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ibc_ok > 0
                    UNION ALL
                    SELECT op3 AS op_num FROM rebotling_skiftrapport WHERE op3 IS NOT NULL AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ibc_ok > 0
                ) AS sub"
            );
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $antalAktiva = $row ? (int)$row['cnt'] : 0;

            $this->sendSuccess([
                'team_snitt_ibc_h' => $teamSnitt,
                'antal_aktiva'     => $antalAktiva,
            ]);

        } catch (\Throwable $e) {
            error_log('OperatorOnboardingController::getTeamStats: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta teamstatistik', 500);
        }
    }

    /**
     * Batch-hämta "veckor till teamsnitt" för alla operatörer i EN query.
     * Eliminerar N+1 getWeeklyCurve()-anrop i overview().
     *
     * @param array<int, string> $opStartDates  op_number => first_date
     * @param float $teamSnitt  Team-genomsnitt IBC/h
     * @param int $maxWeeks  Max veckor att söka
     * @return array<int, int|null>  op_number => vecka_nr (null om aldrig nått)
     */
    private function getBatchWeeksToTarget(array $opStartDates, float $teamSnitt, int $maxWeeks = 12): array {
        $result = [];
        if (empty($opStartDates) || $teamSnitt <= 0) return $result;

        try {
            $opNums = array_keys($opStartDates);
            if (empty($opNums)) return $result;

            $placeholders = implode(',', array_fill(0, count($opNums), '?'));

            // Hämta rådata: op_id, datum, ibc_ok, drifttid — EN query för alla operatörer
            // Använder datum >= äldsta startdatum som grov filter
            $minStart = min($opStartDates);

            $sql = "
                SELECT op_id, datum, SUM(ibc_ok) AS ibc_ok, SUM(drifttid) AS drifttid
                FROM (
                    SELECT op1 AS op_id, datum, ibc_ok, COALESCE(drifttid, 0) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 IN ({$placeholders}) AND datum >= ?
                    UNION ALL
                    SELECT op2, datum, ibc_ok, COALESCE(drifttid, 0)
                    FROM rebotling_skiftrapport
                    WHERE op2 IN ({$placeholders}) AND datum >= ?
                    UNION ALL
                    SELECT op3, datum, ibc_ok, COALESCE(drifttid, 0)
                    FROM rebotling_skiftrapport
                    WHERE op3 IN ({$placeholders}) AND datum >= ?
                ) AS raw_data
                GROUP BY op_id, datum
            ";

            $params = array_merge(
                $opNums, [$minStart],
                $opNums, [$minStart],
                $opNums, [$minStart]
            );

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Gruppera per operatör och beräkna veckonummer relativt startdatum
            $weeklyData = []; // op_num => [vecka => {ibc_ok, drifttid}]
            foreach ($rows as $row) {
                $opId = (int)$row['op_id'];
                if (!isset($opStartDates[$opId])) continue;
                $startDate = $opStartDates[$opId];
                $daysDiff = (int)(new \DateTime($startDate))->diff(new \DateTime($row['datum']))->days;
                $vecka = (int)floor($daysDiff / 7) + 1;
                if ($vecka < 1 || $vecka > $maxWeeks) continue;

                if (!isset($weeklyData[$opId][$vecka])) {
                    $weeklyData[$opId][$vecka] = ['ibc_ok' => 0, 'drifttid' => 0];
                }
                $weeklyData[$opId][$vecka]['ibc_ok'] += (int)$row['ibc_ok'];
                $weeklyData[$opId][$vecka]['drifttid'] += (int)$row['drifttid'];
            }

            // Hitta första vecka där IBC/h >= teamSnitt
            foreach ($opStartDates as $opNum => $startDate) {
                $result[$opNum] = null;
                if (!isset($weeklyData[$opNum])) continue;
                ksort($weeklyData[$opNum]);
                foreach ($weeklyData[$opNum] as $vecka => $data) {
                    if ($data['drifttid'] > 0) {
                        $ibcH = $data['ibc_ok'] / ($data['drifttid'] / 60.0);
                        if ($ibcH >= $teamSnitt) {
                            $result[$opNum] = $vecka;
                            break;
                        }
                    }
                }
            }
        } catch (\PDOException $e) {
            error_log('OperatorOnboardingController::getBatchWeeksToTarget: ' . $e->getMessage());
        }

        return $result;
    }
}
