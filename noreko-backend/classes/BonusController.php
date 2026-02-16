<?php
/**
 * BonusController.php
 * Hanterar alla API-endpoints för bonussystemet
 * 
 * Endpoints:
 * - ?action=bonus&run=operator&id=<op_id>     → Operatörsprestationer
 * - ?action=bonus&run=ranking                 → Top 10 ranking
 * - ?action=bonus&run=team                    → Team-översikt
 * - ?action=bonus&run=kpis&id=<op_id>         → KPI-detaljer för operatör
 * - ?action=bonus&run=history&id=<op_id>      → Historik för operatör
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
        $run = $_GET['run'] ?? '';

        if ($method !== 'GET') {
            $this->sendError('Endast GET-requests stöds');
            return;
        }
        
        switch ($run) {
            case 'operator':
                $this->getOperatorStats();
                break;
            case 'ranking':
                $this->getRanking();
                break;
            case 'team':
                $this->getTeamStats();
                break;
            case 'kpis':
                $this->getKPIDetails();
                break;
            case 'history':
                $this->getOperatorHistory();
                break;
            case 'summary':
                $this->getDailySummary();
                break;
            default:
                $this->sendError('Ogiltig action: ' . $run);
        }
    }

    /**
     * GET /api.php?action=bonus&run=operator&id=<op_id>&period=week|month|all
     * 
     * Hämtar prestationer för en specifik operatör
     */
    private function getOperatorStats() {
        $op_id = $_GET['id'] ?? null;
        $period = $_GET['period'] ?? 'week'; // week, month, all
        $start_date = $_GET['start'] ?? null;
        $end_date = $_GET['end'] ?? null;
        
        if (!$op_id) {
            $this->sendError('Operatör-ID saknas (id)');
            return;
        }
        
        // Bestäm datumintervall
        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);
        
        try {
            // Hämta grundstatistik för operatören
            $stmt = $this->pdo->prepare("
                SELECT 
                    op1, op2, op3,
                    COUNT(*) as total_cycles,
                    SUM(ibc_ok) as total_ibc_ok,
                    SUM(ibc_ej_ok) as total_ibc_ej_ok,
                    SUM(bur_ej_ok) as total_bur_ej_ok,
                    SUM(runtime_plc) as total_runtime,
                    SUM(rasttime) as total_rasttime,
                    AVG(effektivitet) as avg_effektivitet,
                    AVG(produktivitet) as avg_produktivitet,
                    AVG(kvalitet) as avg_kvalitet,
                    AVG(bonus_poang) as avg_bonus,
                    MAX(bonus_poang) as max_bonus,
                    MIN(bonus_poang) as min_bonus,
                    DATE(MIN(datum)) as first_date,
                    DATE(MAX(datum)) as last_date
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                AND $dateFilter
                GROUP BY 1,2,3
            ");
            
            $stmt->execute(['op_id' => $op_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stats) {
                $this->sendError('Ingen data hittades för operatör ' . $op_id);
                return;
            }
            
            // Hämta daglig breakdown
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(datum) as date,
                    COUNT(*) as cycles,
                    SUM(ibc_ok) as ibc_ok,
                    SUM(ibc_ej_ok) as ibc_ej_ok,
                    AVG(effektivitet) as effektivitet,
                    AVG(produktivitet) as produktivitet,
                    AVG(kvalitet) as kvalitet,
                    AVG(bonus_poang) as bonus_poang
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                AND $dateFilter
                GROUP BY DATE(datum)
                ORDER BY DATE(datum) DESC
                LIMIT 30
            ");
            
            $stmt->execute(['op_id' => $op_id]);
            $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Hämta position/roll för operatören
            $position = $this->getOperatorPosition($op_id, $stats);
            
            // Beräkna total arbetstid
            $total_hours = round(($stats['total_runtime'] ?? 0) / 60, 1);
            $total_rast_hours = round(($stats['total_rasttime'] ?? 0) / 60, 1);
            
            $response = [
                'success' => true,
                'operator_id' => (int)$op_id,
                'position' => $position,
                'period' => $period,
                'date_range' => [
                    'from' => $stats['first_date'],
                    'to' => $stats['last_date']
                ],
                'summary' => [
                    'total_cycles' => (int)$stats['total_cycles'],
                    'total_ibc_ok' => (int)($stats['total_ibc_ok'] ?? 0),
                    'total_ibc_ej_ok' => (int)($stats['total_ibc_ej_ok'] ?? 0),
                    'total_bur_ej_ok' => (int)($stats['total_bur_ej_ok'] ?? 0),
                    'total_hours' => $total_hours,
                    'total_rast_hours' => $total_rast_hours
                ],
                'kpis' => [
                    'effektivitet' => round($stats['avg_effektivitet'] ?? 0, 2),
                    'produktivitet' => round($stats['avg_produktivitet'] ?? 0, 2),
                    'kvalitet' => round($stats['avg_kvalitet'] ?? 0, 2),
                    'bonus_avg' => round($stats['avg_bonus'] ?? 0, 2),
                    'bonus_max' => round($stats['max_bonus'] ?? 0, 2),
                    'bonus_min' => round($stats['min_bonus'] ?? 0, 2)
                ],
                'daily_breakdown' => array_map(function($row) {
                    return [
                        'date' => $row['date'],
                        'cycles' => (int)$row['cycles'],
                        'ibc_ok' => (int)($row['ibc_ok'] ?? 0),
                        'ibc_ej_ok' => (int)($row['ibc_ej_ok'] ?? 0),
                        'effektivitet' => round($row['effektivitet'] ?? 0, 2),
                        'produktivitet' => round($row['produktivitet'] ?? 0, 2),
                        'kvalitet' => round($row['kvalitet'] ?? 0, 2),
                        'bonus_poang' => round($row['bonus_poang'] ?? 0, 2)
                    ];
                }, $daily)
            ];
            
            $this->sendSuccess($response);
            
        } catch (PDOException $e) {
            $this->sendError('Databasfel: ' . $e->getMessage());
        }
    }

    /**
     * GET /api.php?action=bonus&run=ranking&period=week|month&limit=10
     * 
     * Hämtar Top N operatörer baserat på bonuspoäng
     */
    private function getRanking() {
        $period = $_GET['period'] ?? 'week';
        $limit = min((int)($_GET['limit'] ?? 10), 100);
        $start_date = $_GET['start'] ?? null;
        $end_date = $_GET['end'] ?? null;
        
        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);
        
        try {
            // Hämta ranking för varje position (op1, op2, op3)
            $rankings = [];
            
            for ($pos = 1; $pos <= 3; $pos++) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        op{$pos} as operator_id,
                        COUNT(*) as cycles,
                        AVG(bonus_poang) as avg_bonus,
                        AVG(effektivitet) as avg_effektivitet,
                        AVG(produktivitet) as avg_produktivitet,
                        AVG(kvalitet) as avg_kvalitet,
                        SUM(ibc_ok) as total_ibc_ok,
                        SUM(ibc_ej_ok) as total_ibc_ej_ok,
                        SUM(runtime_plc) as total_runtime
                    FROM rebotling_ibc
                    WHERE op{$pos} IS NOT NULL 
                    AND op{$pos} > 0
                    AND $dateFilter
                    GROUP BY op{$pos}
                    HAVING cycles >= 5
                    ORDER BY avg_bonus DESC
                    LIMIT {$limit}
                ");
                
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $rankings["position_{$pos}"] = array_map(function($row, $index) use ($pos) {
                    return [
                        'rank' => $index + 1,
                        'operator_id' => (int)$row['operator_id'],
                        'position' => $this->getPositionName($pos),
                        'cycles' => (int)$row['cycles'],
                        'bonus_avg' => round($row['avg_bonus'] ?? 0, 2),
                        'effektivitet' => round($row['avg_effektivitet'] ?? 0, 2),
                        'produktivitet' => round($row['avg_produktivitet'] ?? 0, 2),
                        'kvalitet' => round($row['avg_kvalitet'] ?? 0, 2),
                        'total_ibc_ok' => (int)($row['total_ibc_ok'] ?? 0),
                        'total_hours' => round(($row['total_runtime'] ?? 0) / 60, 1)
                    ];
                }, $results, array_keys($results));
            }
            
            // Kombinerad ranking (alla positioner)
            $stmt = $this->pdo->prepare("
                SELECT 
                    operator_id,
                    SUM(cycles) as total_cycles,
                    AVG(avg_bonus) as avg_bonus,
                    AVG(avg_effektivitet) as avg_effektivitet,
                    AVG(avg_produktivitet) as avg_produktivitet,
                    AVG(avg_kvalitet) as avg_kvalitet,
                    SUM(total_ibc_ok) as total_ibc_ok,
                    SUM(total_runtime) as total_runtime
                FROM (
                    SELECT op1 as operator_id, COUNT(*) as cycles, 
                           AVG(bonus_poang) as avg_bonus,
                           AVG(effektivitet) as avg_effektivitet,
                           AVG(produktivitet) as avg_produktivitet,
                           AVG(kvalitet) as avg_kvalitet,
                           SUM(ibc_ok) as total_ibc_ok,
                           SUM(runtime_plc) as total_runtime
                    FROM rebotling_ibc 
                    WHERE op1 IS NOT NULL AND op1 > 0 AND $dateFilter
                    GROUP BY op1
                    
                    UNION ALL
                    
                    SELECT op2 as operator_id, COUNT(*) as cycles, 
                           AVG(bonus_poang) as avg_bonus,
                           AVG(effektivitet) as avg_effektivitet,
                           AVG(produktivitet) as avg_produktivitet,
                           AVG(kvalitet) as avg_kvalitet,
                           SUM(ibc_ok) as total_ibc_ok,
                           SUM(runtime_plc) as total_runtime
                    FROM rebotling_ibc 
                    WHERE op2 IS NOT NULL AND op2 > 0 AND $dateFilter
                    GROUP BY op2
                    
                    UNION ALL
                    
                    SELECT op3 as operator_id, COUNT(*) as cycles, 
                           AVG(bonus_poang) as avg_bonus,
                           AVG(effektivitet) as avg_effektivitet,
                           AVG(produktivitet) as avg_produktivitet,
                           AVG(kvalitet) as avg_kvalitet,
                           SUM(ibc_ok) as total_ibc_ok,
                           SUM(runtime_plc) as total_runtime
                    FROM rebotling_ibc 
                    WHERE op3 IS NOT NULL AND op3 > 0 AND $dateFilter
                    GROUP BY op3
                ) as combined
                GROUP BY operator_id
                HAVING total_cycles >= 10
                ORDER BY avg_bonus DESC
                LIMIT {$limit}
            ");
            
            $stmt->execute();
            $combined_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $rankings['overall'] = array_map(function($row, $index) {
                return [
                    'rank' => $index + 1,
                    'operator_id' => (int)$row['operator_id'],
                    'total_cycles' => (int)$row['total_cycles'],
                    'bonus_avg' => round($row['avg_bonus'] ?? 0, 2),
                    'effektivitet' => round($row['avg_effektivitet'] ?? 0, 2),
                    'produktivitet' => round($row['avg_produktivitet'] ?? 0, 2),
                    'kvalitet' => round($row['avg_kvalitet'] ?? 0, 2),
                    'total_ibc_ok' => (int)($row['total_ibc_ok'] ?? 0),
                    'total_hours' => round(($row['total_runtime'] ?? 0) / 60, 1)
                ];
            }, $combined_ranking, array_keys($combined_ranking));
            
            $this->sendSuccess([
                'period' => $period,
                'limit' => $limit,
                'rankings' => $rankings
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Databasfel: ' . $e->getMessage());
        }
    }

    /**
     * GET /api.php?action=bonus&run=team&period=week|month
     * 
     * Hämtar team-översikt per linje/skift
     */
    private function getTeamStats() {
        $period = $_GET['period'] ?? 'week';
        $start_date = $_GET['start'] ?? null;
        $end_date = $_GET['end'] ?? null;
        
        $dateFilter = $this->getDateFilter($period, $start_date, $end_date);
        
        try {
            // Hämta team-stats per skift
            $stmt = $this->pdo->prepare("
                SELECT 
                    skiftraknare,
                    COUNT(*) as cycles,
                    SUM(ibc_ok) as total_ibc_ok,
                    SUM(ibc_ej_ok) as total_ibc_ej_ok,
                    SUM(bur_ej_ok) as total_bur_ej_ok,
                    AVG(effektivitet) as avg_effektivitet,
                    AVG(produktivitet) as avg_produktivitet,
                    AVG(kvalitet) as avg_kvalitet,
                    AVG(bonus_poang) as avg_bonus,
                    SUM(runtime_plc) as total_runtime,
                    DATE(MIN(datum)) as shift_start,
                    DATE(MAX(datum)) as shift_end,
                    GROUP_CONCAT(DISTINCT op1) as operators_1,
                    GROUP_CONCAT(DISTINCT op2) as operators_2,
                    GROUP_CONCAT(DISTINCT op3) as operators_3
                FROM rebotling_ibc
                WHERE skiftraknare IS NOT NULL
                AND $dateFilter
                GROUP BY skiftraknare
                ORDER BY skiftraknare DESC
                LIMIT 50
            ");
            
            $stmt->execute();
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $team_stats = array_map(function($row) {
                // Samla alla unika operatörer
                $ops = array_merge(
                    $row['operators_1'] ? explode(',', $row['operators_1']) : [],
                    $row['operators_2'] ? explode(',', $row['operators_2']) : [],
                    $row['operators_3'] ? explode(',', $row['operators_3']) : []
                );
                $unique_operators = array_values(array_unique(array_filter($ops)));
                
                return [
                    'shift_number' => (int)$row['skiftraknare'],
                    'shift_start' => $row['shift_start'],
                    'shift_end' => $row['shift_end'],
                    'operators' => array_map('intval', $unique_operators),
                    'operator_count' => count($unique_operators),
                    'cycles' => (int)$row['cycles'],
                    'total_ibc_ok' => (int)($row['total_ibc_ok'] ?? 0),
                    'total_ibc_ej_ok' => (int)($row['total_ibc_ej_ok'] ?? 0),
                    'total_bur_ej_ok' => (int)($row['total_bur_ej_ok'] ?? 0),
                    'total_hours' => round(($row['total_runtime'] ?? 0) / 60, 1),
                    'kpis' => [
                        'effektivitet' => round($row['avg_effektivitet'] ?? 0, 2),
                        'produktivitet' => round($row['avg_produktivitet'] ?? 0, 2),
                        'kvalitet' => round($row['avg_kvalitet'] ?? 0, 2),
                        'bonus_avg' => round($row['avg_bonus'] ?? 0, 2)
                    ]
                ];
            }, $shifts);
            
            // Aggregerad team-statistik
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT skiftraknare) as total_shifts,
                    COUNT(*) as total_cycles,
                    SUM(ibc_ok) as total_ibc_ok,
                    AVG(bonus_poang) as avg_bonus,
                    COUNT(DISTINCT op1) + COUNT(DISTINCT op2) + COUNT(DISTINCT op3) as unique_operators
                FROM rebotling_ibc
                WHERE $dateFilter
            ");
            
            $stmt->execute();
            $aggregate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->sendSuccess([
                'period' => $period,
                'aggregate' => [
                    'total_shifts' => (int)($aggregate['total_shifts'] ?? 0),
                    'total_cycles' => (int)($aggregate['total_cycles'] ?? 0),
                    'total_ibc_ok' => (int)($aggregate['total_ibc_ok'] ?? 0),
                    'avg_bonus' => round($aggregate['avg_bonus'] ?? 0, 2),
                    'unique_operators' => (int)($aggregate['unique_operators'] ?? 0)
                ],
                'shifts' => $team_stats
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Databasfel: ' . $e->getMessage());
        }
    }

    /**
     * GET /api.php?action=bonus&run=kpis&id=<op_id>&period=week
     * 
     * Detaljerad KPI-breakdown för operatör
     */
    private function getKPIDetails() {
        $op_id = $_GET['id'] ?? null;
        $period = $_GET['period'] ?? 'week';
        
        if (!$op_id) {
            $this->sendError('Operatör-ID saknas');
            return;
        }
        
        $dateFilter = $this->getDateFilter($period);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(datum) as date,
                    COUNT(*) as cycles,
                    AVG(effektivitet) as effektivitet,
                    AVG(produktivitet) as produktivitet,
                    AVG(kvalitet) as kvalitet,
                    AVG(bonus_poang) as bonus_poang,
                    SUM(ibc_ok) as ibc_ok,
                    SUM(ibc_ej_ok) as ibc_ej_ok,
                    SUM(bur_ej_ok) as bur_ej_ok
                FROM rebotling_ibc
                WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                AND $dateFilter
                GROUP BY DATE(datum)
                ORDER BY DATE(datum) ASC
            ");
            
            $stmt->execute(['op_id' => $op_id]);
            $daily_kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatera för Chart.js
            $chart_data = [
                'labels' => array_column($daily_kpis, 'date'),
                'datasets' => [
                    [
                        'label' => 'Effektivitet (%)',
                        'data' => array_map(fn($r) => round($r['effektivitet'] ?? 0, 2), $daily_kpis),
                        'borderColor' => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)'
                    ],
                    [
                        'label' => 'Produktivitet (IBC/h)',
                        'data' => array_map(fn($r) => round($r['produktivitet'] ?? 0, 2), $daily_kpis),
                        'borderColor' => 'rgb(54, 162, 235)',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)'
                    ],
                    [
                        'label' => 'Kvalitet (%)',
                        'data' => array_map(fn($r) => round($r['kvalitet'] ?? 0, 2), $daily_kpis),
                        'borderColor' => 'rgb(255, 206, 86)',
                        'backgroundColor' => 'rgba(255, 206, 86, 0.2)'
                    ]
                ]
            ];
            
            $this->sendSuccess([
                'operator_id' => (int)$op_id,
                'period' => $period,
                'chart_data' => $chart_data,
                'raw_data' => $daily_kpis
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Databasfel: ' . $e->getMessage());
        }
    }

    /**
     * GET /api.php?action=bonus&run=history&id=<op_id>&limit=50
     * 
     * Hämtar historik för operatör (senaste cyklerna)
     */
    private function getOperatorHistory() {
        $op_id = $_GET['id'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 50), 500);
        
        if (!$op_id) {
            $this->sendError('Operatör-ID saknas');
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    datum,
                    lopnummer,
                    skiftraknare,
                    produkt,
                    ibc_ok,
                    ibc_ej_ok,
                    bur_ej_ok,
                    runtime_plc,
                    effektivitet,
                    produktivitet,
                    kvalitet,
                    bonus_poang,
                    CASE 
                        WHEN op1 = :op_id THEN 'Tvättplats'
                        WHEN op2 = :op_id THEN 'Kontrollstation'
                        WHEN op3 = :op_id THEN 'Truckförare'
                    END as position
                FROM rebotling_ibc
                WHERE op1 = :op_id OR op2 = :op_id OR op3 = :op_id
                ORDER BY datum DESC
                LIMIT {$limit}
            ");
            
            $stmt->execute(['op_id' => $op_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess([
                'operator_id' => (int)$op_id,
                'count' => count($history),
                'history' => array_map(function($row) {
                    return [
                        'datum' => $row['datum'],
                        'lopnummer' => (int)($row['lopnummer'] ?? 0),
                        'shift' => (int)($row['skiftraknare'] ?? 0),
                        'position' => $row['position'],
                        'produkt' => (int)($row['produkt'] ?? 0),
                        'ibc_ok' => (int)($row['ibc_ok'] ?? 0),
                        'ibc_ej_ok' => (int)($row['ibc_ej_ok'] ?? 0),
                        'bur_ej_ok' => (int)($row['bur_ej_ok'] ?? 0),
                        'runtime' => (int)($row['runtime_plc'] ?? 0),
                        'kpis' => [
                            'effektivitet' => round($row['effektivitet'] ?? 0, 2),
                            'produktivitet' => round($row['produktivitet'] ?? 0, 2),
                            'kvalitet' => round($row['kvalitet'] ?? 0, 2),
                            'bonus' => round($row['bonus_poang'] ?? 0, 2)
                        ]
                    ];
                }, $history)
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Databasfel: ' . $e->getMessage());
        }
    }

    /**
     * GET /api.php?action=bonus&run=summary
     * 
     * Hämtar dagens sammanfattning
     */
    private function getDailySummary() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_cycles,
                    COUNT(DISTINCT skiftraknare) as shifts_today,
                    SUM(ibc_ok) as total_ibc_ok,
                    SUM(ibc_ej_ok) as total_ibc_ej_ok,
                    AVG(bonus_poang) as avg_bonus,
                    MAX(bonus_poang) as max_bonus,
                    COUNT(DISTINCT op1) as unique_op1,
                    COUNT(DISTINCT op2) as unique_op2,
                    COUNT(DISTINCT op3) as unique_op3
                FROM rebotling_ibc
                WHERE DATE(datum) = CURDATE()
            ");
            
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->sendSuccess([
                'date' => date('Y-m-d'),
                'total_cycles' => (int)($summary['total_cycles'] ?? 0),
                'shifts_today' => (int)($summary['shifts_today'] ?? 0),
                'total_ibc_ok' => (int)($summary['total_ibc_ok'] ?? 0),
                'total_ibc_ej_ok' => (int)($summary['total_ibc_ej_ok'] ?? 0),
                'avg_bonus' => round($summary['avg_bonus'] ?? 0, 2),
                'max_bonus' => round($summary['max_bonus'] ?? 0, 2),
                'unique_operators' => [
                    'tvattplats' => (int)($summary['unique_op1'] ?? 0),
                    'kontroll' => (int)($summary['unique_op2'] ?? 0),
                    'truck' => (int)($summary['unique_op3'] ?? 0)
                ]
            ]);
            
        } catch (PDOException $e) {
            $this->sendError('Databasfel: ' . $e->getMessage());
        }
    }

    // =============== HJÄLPFUNKTIONER ===============
    
    private function getDateFilter($period, $start = null, $end = null) {
        if ($start && $end) {
            return "DATE(datum) BETWEEN '{$start}' AND '{$end}'";
        }
        
        switch ($period) {
            case 'today':
                return "DATE(datum) = CURDATE()";
            case 'week':
                return "datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'year':
                return "datum >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            case 'all':
            default:
                return "1=1"; // Ingen filter
        }
    }
    
    private function getOperatorPosition($op_id, $stats) {
        if ($stats['op1'] == $op_id) return 'Tvättplats';
        if ($stats['op2'] == $op_id) return 'Kontrollstation';
        if ($stats['op3'] == $op_id) return 'Truckförare';
        return 'Okänd';
    }
    
    private function getPositionName($pos) {
        $positions = [
            1 => 'Tvättplats',
            2 => 'Kontrollstation',
            3 => 'Truckförare'
        ];
        return $positions[$pos] ?? 'Okänd';
    }
    
    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function sendError($message) {
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
