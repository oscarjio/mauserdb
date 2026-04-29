<?php
/**
 * NarvaroController — Operatornarvarotracker
 *
 * GET ?action=narvaro&run=monthly-overview&year=YYYY&month=MM
 *   Returnerar per-operator per-dag aggregering fran rebotling_skiftrapport.
 */
class NarvaroController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Inloggning kravs'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET' && $run === 'monthly-overview') {
            $this->monthlyOverview();
        } else {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Okand endpoint'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function monthlyOverview(): void {
        $year  = intval($_GET['year']  ?? date('Y'));
        $month = intval($_GET['month'] ?? date('n'));

        if ($year < 2020 || $year > 2100) $year = (int)date('Y');
        if ($month < 1 || $month > 12) $month = (int)date('n');

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate)); // last day of month

        try {
            // Hämta alla skiftrapporter för månaden med operatörer
            // Varje rad har op1, op2, op3 som refererar till operators.number
            // Anvander DATE() for att undvika midnight edge case (datum ar DATETIME)
            $sql = "
                SELECT
                    DATE(s.datum) AS dag,
                    s.skiftraknare,
                    s.ibc_ok,
                    s.drifttid,
                    s.op1,
                    s.op2,
                    s.op3
                FROM rebotling_skiftrapport s
                WHERE s.datum >= :start AND s.datum < DATE_ADD(:end, INTERVAL 1 DAY)
                  AND s.ibc_ok IS NOT NULL
                ORDER BY s.datum ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['start' => $startDate, 'end' => $endDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta operatörsnamn
            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $operators = [];
            foreach ($opStmt->fetchAll(PDO::FETCH_ASSOC) as $op) {
                $operators[(int)$op['number']] = $op['name'];
            }

            // Bygg per-operatör per-dag data
            // operatorData[opNumber][dag] = { ibc: int, shifts: [], totalCycleTime: float, shiftCount: int }
            $operatorData = [];

            foreach ($rows as $row) {
                $dag       = $row['dag'];
                $ibcOk     = (int)$row['ibc_ok'];
                $skift     = (int)$row['skiftraknare'];
                $drifttid  = (float)$row['drifttid'];
                $cycleTimeSek = ($ibcOk > 0 && $drifttid > 0) ? ($drifttid * 60.0 / $ibcOk) : 0;

                // Fördela IBC jämnt mellan aktiva operatörer på skiftet
                $opIds = array_filter([(int)$row['op1'], (int)$row['op2'], (int)$row['op3']], fn($v) => $v > 0);
                if (empty($opIds)) continue;

                foreach ($opIds as $opId) {
                    if (!isset($operators[$opId])) continue;
                    if (!isset($operatorData[$opId])) {
                        $operatorData[$opId] = [];
                    }
                    if (!isset($operatorData[$opId][$dag])) {
                        $operatorData[$opId][$dag] = [
                            'ibc'            => 0,
                            'shifts'         => [],
                            'totalCycleTime' => 0.0,
                            'shiftCount'     => 0,
                        ];
                    }
                    $operatorData[$opId][$dag]['ibc'] += $ibcOk;
                    if (!in_array($skift, $operatorData[$opId][$dag]['shifts'], true)) {
                        $operatorData[$opId][$dag]['shifts'][] = $skift;
                    }
                    if ($cycleTimeSek > 0) {
                        $operatorData[$opId][$dag]['totalCycleTime'] += $cycleTimeSek;
                        $operatorData[$opId][$dag]['shiftCount'] += 1;
                    }
                }
            }

            // Formatera output
            $result = [];
            foreach ($operatorData as $opId => $days) {
                $opName = $operators[$opId] ?? ('Op ' . $opId);
                $dayEntries = [];
                foreach ($days as $dag => $info) {
                    $avgCycle = ($info['shiftCount'] > 0)
                        ? round($info['totalCycleTime'] / $info['shiftCount'], 1)
                        : 0;
                    $dayEntries[] = [
                        'dag'          => $dag,
                        'ibc'          => $info['ibc'],
                        'skift'        => $info['shifts'],
                        'snitt_cykel'  => $avgCycle,
                    ];
                }
                $totalIbc  = array_sum(array_column($dayEntries, 'ibc'));
                $daysCount = count($dayEntries);
                $result[] = [
                    'operator_id'   => $opId,
                    'operator_name' => $opName,
                    'days'          => $dayEntries,
                    'total_ibc'     => $totalIbc,
                    'active_days'   => $daysCount,
                ];
            }

            // Sortera på namn
            usort($result, fn($a, $b) => strcmp($a['operator_name'], $b['operator_name']));

            // Sammanfattning
            $totalOperators  = count($result);
            $totalDaysAll    = array_sum(array_column($result, 'active_days'));
            $avgDays         = $totalOperators > 0 ? round($totalDaysAll / $totalOperators, 1) : 0;
            $topDaysOp       = null;
            $topIbcOp        = null;
            $maxDays         = 0;
            $maxIbc          = 0;
            foreach ($result as $op) {
                if ($op['active_days'] > $maxDays) {
                    $maxDays   = $op['active_days'];
                    $topDaysOp = $op['operator_name'];
                }
                if ($op['total_ibc'] > $maxIbc) {
                    $maxIbc   = $op['total_ibc'];
                    $topIbcOp = $op['operator_name'];
                }
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => [
                    'year'       => $year,
                    'month'      => $month,
                    'days_in_month' => (int)date('t', strtotime($startDate)),
                    'operators'  => $result,
                    'summary'    => [
                        'total_operators'     => $totalOperators,
                        'avg_days_per_op'     => $avgDays,
                        'top_days_operator'   => $topDaysOp,
                        'top_days_count'      => $maxDays,
                        'top_ibc_operator'    => $topIbcOp,
                        'top_ibc_count'       => $maxIbc,
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('NarvaroController::monthlyOverview: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta narvarodata'], JSON_UNESCAPED_UNICODE);
        }
    }
}
