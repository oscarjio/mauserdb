<?php
/**
 * BonusAdminController.php
 * Admin-panel för bonussystem-konfiguration
 *
 * Endpoints:
 * - ?action=bonusadmin&run=get_config          → Hämta bonuskonfiguration
 * - ?action=bonusadmin&run=update_weights      → Uppdatera viktningar
 * - ?action=bonusadmin&run=set_targets         → Sätt produktivitetsmål
 * - ?action=bonusadmin&run=set_weekly_goal     → Sätt veckobonusmål (poäng)
 * - ?action=bonusadmin&run=get_periods         → Hämta bonusperioder
 * - ?action=bonusadmin&run=export_report       → Exportera rapport
 * - ?action=bonusadmin&run=approve_bonuses     → Godkänn bonusar
 * - ?action=bonusadmin&run=get_stats           → Systemstatistik
 * - ?action=bonusadmin&run=operator_forecast   → Prognos för specifik operatör
 */

class BonusAdminController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'POST') {
                session_start();
            } else {
                session_start(['read_and_close' => true]);
            }
        }
        $run = trim($_GET['run'] ?? '');

        // Admin-kontroll via session
        if (!$this->isAdmin()) {
            $this->sendError('Admin-behörighet krävs', 403);
            return;
        }

        switch ($run) {
            case 'get_config':
                $this->getConfig();
                break;
            case 'update_weights':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->updateWeights();
                break;
            case 'set_targets':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->setTargets();
                break;
            case 'get_periods':
                $this->getBonusPeriods();
                break;
            case 'export_report':
                $this->exportReport();
                break;
            case 'approve_bonuses':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->approveBonuses();
                break;
            case 'get_stats':
                $this->getSystemStats();
                break;
            case 'set_weekly_goal':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->setWeeklyGoal();
                break;
            case 'operator_forecast':
                $this->getOperatorForecast();
                break;
            case 'getAmounts':
                $this->getAmounts();
                break;
            case 'setAmounts':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->setAmounts();
                break;
            case 'list-payouts':
                $this->listPayouts();
                break;
            case 'record-payout':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->recordPayout();
                break;
            case 'delete-payout':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->deletePayout();
                break;
            case 'payout-summary':
                $this->getPayoutSummary();
                break;
            case 'list-operators':
                $this->listOperators();
                break;
            case 'update-payout-status':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->updatePayoutStatus();
                break;
            case 'fairness':
                $this->getFairnessAudit();
                break;
            case 'bonus-simulator':
                $this->getBonusSimulator();
                break;
            case 'save-simulator-params':
                if ($method !== 'POST') {
                    $this->sendError('POST-metod krävs');
                    return;
                }
                $this->saveSimulatorParams();
                break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    /**
     * Hämta nuvarande bonuskonfiguration
     */
    private function getConfig() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM bonus_config
                WHERE id = 1
            ");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                // Return default config if not exists
                $config = [
                    'weights_foodgrade' => json_encode(['eff' => 0.30, 'prod' => 0.30, 'qual' => 0.40], JSON_UNESCAPED_UNICODE),
                    'weights_nonun' => json_encode(['eff' => 0.35, 'prod' => 0.45, 'qual' => 0.20], JSON_UNESCAPED_UNICODE),
                    'weights_tvattade' => json_encode(['eff' => 0.40, 'prod' => 0.35, 'qual' => 0.25], JSON_UNESCAPED_UNICODE),
                    'productivity_target_foodgrade' => 12.0,
                    'productivity_target_nonun' => 20.0,
                    'productivity_target_tvattade' => 15.0,
                    'tier_multipliers' => json_encode([
                        ['threshold' => 95, 'multiplier' => 2.0, 'name' => 'Outstanding'],
                        ['threshold' => 90, 'multiplier' => 1.5, 'name' => 'Excellent'],
                        ['threshold' => 80, 'multiplier' => 1.25, 'name' => 'God prestanda'],
                        ['threshold' => 70, 'multiplier' => 1.0, 'name' => 'Basbonus']
                    ], JSON_UNESCAPED_UNICODE),
                    'max_bonus' => 200,
                    'team_bonus_enabled' => false,
                    'safety_bonus_enabled' => false
                ];
            } else {
                // Decode JSON fields
                $wfgDec = json_decode($config['weights_foodgrade'], true);
                $config['weights_foodgrade'] = is_array($wfgDec) ? $wfgDec : [];
                $wnuDec = json_decode($config['weights_nonun'], true);
                $config['weights_nonun'] = is_array($wnuDec) ? $wnuDec : [];
                $wtvDec = json_decode($config['weights_tvattade'], true);
                $config['weights_tvattade'] = is_array($wtvDec) ? $wtvDec : [];
                $tmDec = json_decode($config['tier_multipliers'], true);
                $config['tier_multipliers'] = is_array($tmDec) ? $tmDec : [];
            }

            $this->sendSuccess($config);

        } catch (PDOException $e) {
            error_log('BonusAdminController::getConfig: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Uppdatera viktningar för produkt
     */
    private function updateWeights() {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Validate JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('BonusAdminController: Ogiltigt JSON-format: ' . json_last_error_msg());
                $this->sendError('Ogiltigt JSON-format');
                return;
            }

            if (!isset($input['produkt']) || !isset($input['weights'])) {
                $this->sendError('Obligatoriska fält saknas: produkt, weights');
                return;
            }

            $produkt = filter_var($input['produkt'], FILTER_VALIDATE_INT);
            if ($produkt === false) {
                $this->sendError('Ogiltigt produkt-ID-format');
                return;
            }

            $weights = $input['weights'];

            // Validate weights structure
            if (!isset($weights['eff']) || !isset($weights['prod']) || !isset($weights['qual'])) {
                $this->sendError('Viktkomponenter saknas (eff, prod, qual)');
                return;
            }

            // Validate weights are numeric and in range
            $eff = filter_var($weights['eff'], FILTER_VALIDATE_FLOAT);
            $prod = filter_var($weights['prod'], FILTER_VALIDATE_FLOAT);
            $qual = filter_var($weights['qual'], FILTER_VALIDATE_FLOAT);

            if ($eff === false || $prod === false || $qual === false) {
                $this->sendError('Vikter måste vara numeriska värden');
                return;
            }

            if ($eff < 0 || $eff > 1 || $prod < 0 || $prod > 1 || $qual < 0 || $qual > 1) {
                $this->sendError('Vikter måste vara mellan 0 och 1');
                return;
            }

            // Validate weights sum to 1.0
            $sum = $eff + $prod + $qual;
            if (abs($sum - 1.0) > 0.001) {
                $this->sendError('Vikterna måste summera till 1.0 (nuvarande: ' . round($sum, 3) . ')');
                return;
            }

            // Determine column name (whitelist protection)
            $column_map = [
                1 => 'weights_foodgrade',
                4 => 'weights_nonun',
                5 => 'weights_tvattade'
            ];

            if (!isset($column_map[$produkt])) {
                $this->sendError('Ogiltigt produkt-ID (måste vara 1, 4 eller 5)');
                return;
            }

            $column = $column_map[$produkt];
            $weights_normalized = [
                'eff' => round($eff, 3),
                'prod' => round($prod, 3),
                'qual' => round($qual, 3)
            ];
            $weights_json = json_encode($weights_normalized, JSON_UNESCAPED_UNICODE);

            // Build query dynamically with safe column name
            $query = "
                INSERT INTO bonus_config (id, {$column})
                VALUES (1, :weights)
                ON DUPLICATE KEY UPDATE
                {$column} = :weights
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['weights' => $weights_json]);

            // Log to audit trail
            $this->logAudit('update_weights', 'config', 1, null, [
                'produkt' => $produkt,
                'weights' => $weights_normalized
            ]);

            $this->sendSuccess([
                'message' => 'Vikter uppdaterade',
                'produkt' => $produkt,
                'weights' => $weights_normalized
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::updateWeights: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Sätt produktivitetsmål
     */
    private function setTargets() {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Validate JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('BonusAdminController: Ogiltigt JSON-format: ' . json_last_error_msg());
                $this->sendError('Ogiltigt JSON-format');
                return;
            }

            if (!isset($input['targets'])) {
                $this->sendError('Fältet targets saknas');
                return;
            }

            $targets = $input['targets'];

            // Validate and sanitize targets
            $foodgrade = filter_var($targets['foodgrade'] ?? 12.0, FILTER_VALIDATE_FLOAT);
            $nonun = filter_var($targets['nonun'] ?? 20.0, FILTER_VALIDATE_FLOAT);
            $tvattade = filter_var($targets['tvattade'] ?? 15.0, FILTER_VALIDATE_FLOAT);

            if ($foodgrade === false || $nonun === false || $tvattade === false) {
                $this->sendError('Målvärden måste vara numeriska');
                return;
            }

            // Validate reasonable ranges (1-100 IBC per hour)
            if ($foodgrade < 1 || $foodgrade > 100 ||
                $nonun < 1 || $nonun > 100 ||
                $tvattade < 1 || $tvattade > 100) {
                $this->sendError('Målvärden måste vara mellan 1 och 100 IBC/timme');
                return;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO bonus_config (
                    id,
                    productivity_target_foodgrade,
                    productivity_target_nonun,
                    productivity_target_tvattade,
                    updated_by
                )
                VALUES (1, :foodgrade, :nonun, :tvattade, :updated_by)
                ON DUPLICATE KEY UPDATE
                productivity_target_foodgrade = :foodgrade,
                productivity_target_nonun = :nonun,
                productivity_target_tvattade = :tvattade,
                updated_by = :updated_by
            ");

            $stmt->execute([
                'foodgrade' => round($foodgrade, 2),
                'nonun' => round($nonun, 2),
                'tvattade' => round($tvattade, 2),
                'updated_by' => $_SESSION['username'] ?? 'admin'
            ]);

            // Log to audit trail
            $this->logAudit('set_targets', 'config', 1, null, [
                'foodgrade' => round($foodgrade, 2),
                'nonun' => round($nonun, 2),
                'tvattade' => round($tvattade, 2)
            ]);

            $this->sendSuccess([
                'message' => 'Produktivitetsmål uppdaterade',
                'targets' => [
                    'foodgrade' => round($foodgrade, 2),
                    'nonun' => round($nonun, 2),
                    'tvattade' => round($tvattade, 2)
                ]
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::setTargets: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Hämta bonusperioder
     */
    private function getBonusPeriods() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m') as period,
                    COUNT(*) as total_cycles,
                    COUNT(DISTINCT COALESCE(op1, op2, op3)) as unique_operators,
                    AVG(bonus_poang) as avg_bonus,
                    SUM(ibc_ok) as total_ibc_ok,
                    SUM(CASE WHEN bonus_poang >= 80 THEN 1 ELSE 0 END) as cycles_above_80
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND bonus_poang IS NOT NULL
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ORDER BY period DESC
            ");

            $stmt->execute();
            $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'periods' => array_map(function($p) {
                    $total_cycles = (int)$p['total_cycles'];
                    $cycles_above_80 = (int)$p['cycles_above_80'];

                    return [
                        'period' => $p['period'],
                        'total_cycles' => $total_cycles,
                        'unique_operators' => (int)$p['unique_operators'],
                        'avg_bonus' => round((float)$p['avg_bonus'], 2),
                        'total_ibc_ok' => (int)$p['total_ibc_ok'],
                        'success_rate' => $total_cycles > 0
                            ? round(($cycles_above_80 / $total_cycles) * 100, 2)
                            : 0
                    ];
                }, $periods)
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::getBonusPeriods: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Exportera bonusrapport (CSV)
     */
    private function exportReport() {
        $period = trim($_GET['period'] ?? date('Y-m'));
        $format = trim($_GET['format'] ?? 'csv');

        // Validate period format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->sendError('Ogiltigt periodformat (förväntat: YYYY-MM)');
            return;
        }

        // Validate format
        $allowed_formats = ['csv', 'json'];
        if (!in_array($format, $allowed_formats, true)) {
            $this->sendError('Ogiltigt format (tillåtna: csv, json)');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    COALESCE(op1, op2, op3) as operator_id,
                    CASE
                        WHEN op1 IS NOT NULL THEN 'Tvättplats'
                        WHEN op2 IS NOT NULL THEN 'Kontrollstation'
                        WHEN op3 IS NOT NULL THEN 'Truckförare'
                    END as position,
                    produkt,
                    ibc_ok,
                    ibc_ej_ok,
                    bur_ej_ok,
                    runtime_plc,
                    effektivitet,
                    produktivitet,
                    kvalitet,
                    bonus_poang
                FROM rebotling_ibc
                WHERE DATE_FORMAT(datum, '%Y-%m') = :period
                AND bonus_poang IS NOT NULL
                ORDER BY datum DESC
                LIMIT 50000
            ");

            $stmt->execute(['period' => $period]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($data)) {
                $this->sendError('Ingen data hittades för period: ' . htmlspecialchars($period, ENT_QUOTES, 'UTF-8'), 404);
                return;
            }

            if ($format === 'csv') {
                // Sanitize filename
                $safe_period = preg_replace('/[^0-9-]/', '', $period);
                $this->exportCSV($data, "bonus_report_{$safe_period}.csv");
            } else {
                $this->sendSuccess(['data' => $data, 'count' => count($data)]);
            }

        } catch (PDOException $e) {
            error_log('BonusAdminController::exportReport: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Godkänn bonusar för period
     */
    private function approveBonuses() {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Validate JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('BonusAdminController: Ogiltigt JSON-format: ' . json_last_error_msg());
                $this->sendError('Ogiltigt JSON-format');
                return;
            }

            if (!isset($input['period'])) {
                $this->sendError('Fältet period saknas');
                return;
            }

            $period = $input['period'];

            // Validate period format (YYYY-MM)
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                $this->sendError('Ogiltigt periodformat (förväntat: YYYY-MM)');
                return;
            }

            // Sanitize approved_by (strip tags + limit length)
            $approved_by = strip_tags((string)($input['approved_by'] ?? $_SESSION['username'] ?? 'admin'));
            $approved_by = mb_substr($approved_by, 0, 100); // Limit length

            // Mark bonuses as approved in database
            $stmt = $this->pdo->prepare("
                UPDATE rebotling_ibc
                SET bonus_approved = 1,
                    bonus_approved_by = :approved_by,
                    bonus_approved_at = NOW()
                WHERE DATE_FORMAT(datum, '%Y-%m') = :period
                AND bonus_poang IS NOT NULL
                AND bonus_approved = 0
            ");

            $stmt->execute([
                'period' => $period,
                'approved_by' => $approved_by
            ]);

            $affected = $stmt->rowCount();

            if ($affected === 0) {
                $this->sendError('Inga ej godkända bonusar hittades för period: ' . htmlspecialchars($period, ENT_QUOTES, 'UTF-8'), 404);
                return;
            }

            // Log to audit trail
            $this->logAudit('approve_bonuses', 'period', null, null, [
                'period' => $period,
                'cycles_approved' => $affected,
                'approved_by' => $approved_by
            ]);

            $this->sendSuccess([
                'message' => 'Bonusar godkända',
                'period' => $period,
                'cycles_approved' => $affected
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::approveBonuses: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Systemstatistik för dashboard
     */
    private function getSystemStats() {
        try {
            // Overall stats
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_cycles,
                    COUNT(DISTINCT COALESCE(op1, op2, op3)) as unique_operators,
                    AVG(bonus_poang) as avg_bonus,
                    MAX(bonus_poang) as max_bonus,
                    MIN(bonus_poang) as min_bonus,
                    SUM(CASE WHEN bonus_poang >= 80 THEN 1 ELSE 0 END) as high_performers
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND bonus_poang IS NOT NULL
            ");

            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$stats) {
                $stats = ['total_cycles' => 0, 'unique_operators' => 0, 'avg_bonus' => 0, 'max_bonus' => 0, 'min_bonus' => 0, 'high_performers' => 0];
            }

            // Trend (compare last 30 days vs previous 30 days)
            $stmt = $this->pdo->prepare("
                SELECT
                    AVG(CASE
                        WHEN datum >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN bonus_poang
                        ELSE NULL
                    END) as recent_avg,
                    AVG(CASE
                        WHEN datum < DATE_SUB(NOW(), INTERVAL 30 DAY)
                        AND datum >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                        THEN bonus_poang
                        ELSE NULL
                    END) as previous_avg
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND bonus_poang IS NOT NULL
            ");

            $stmt->execute();
            $trend = $stmt->fetch(PDO::FETCH_ASSOC);

            $trend_pct = ($trend && (float)($trend['previous_avg'] ?? 0) > 0)
                ? round((((float)($trend['recent_avg'] ?? 0) - (float)$trend['previous_avg']) / (float)$trend['previous_avg']) * 100, 2)
                : 0;

            $total_cycles = (int)$stats['total_cycles'];
            $high_performers = (int)$stats['high_performers'];

            $this->sendSuccess([
                'total_cycles' => $total_cycles,
                'unique_operators' => (int)$stats['unique_operators'],
                'avg_bonus' => round((float)$stats['avg_bonus'], 2),
                'max_bonus' => round((float)$stats['max_bonus'], 2),
                'min_bonus' => round((float)$stats['min_bonus'], 2),
                'high_performers_pct' => $total_cycles > 0
                    ? round(($high_performers / $total_cycles) * 100, 2)
                    : 0,
                'trend' => $trend_pct
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::getSystemStats: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Sätt veckobonusmål (poäng) — sparas i bonus_config
     */
    private function setWeeklyGoal() {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Ogiltigt JSON-format');
                return;
            }
            $goal = filter_var($input['weekly_goal'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($goal === false || $goal < 0 || $goal > 200) {
                $this->sendError('weekly_goal måste vara ett tal 0–200');
                return;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO bonus_config (id, weekly_bonus_goal)
                VALUES (1, :goal)
                ON DUPLICATE KEY UPDATE weekly_bonus_goal = :goal
            ");
            $stmt->execute(['goal' => round($goal, 2)]);

            $this->logAudit('set_weekly_goal', 'config', 1, null, ['weekly_bonus_goal' => round($goal, 2)]);
            $this->sendSuccess(['weekly_bonus_goal' => round($goal, 2)]);
        } catch (PDOException $e) {
            error_log('BonusAdminController::setWeeklyGoal: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Prognos för en specifik operatör: baserat på snitt senaste 7 dagarna
     * GET ?action=bonusadmin&run=operator_forecast&id=<op_id>
     */
    private function getOperatorForecast() {
        $op_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$op_id || $op_id <= 0) {
            $this->sendError('Operatör-ID saknas (id)');
            return;
        }
        try {
            // Hämta operatörens namn
            $nameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $nameStmt->execute([$op_id]);
            $opName = $nameStmt->fetchColumn() ?: 'Operatör ' . $op_id;

            // Per-skift-aggregering senaste 7 dagarna
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*)          AS total_shifts,
                    AVG(last_bonus)   AS avg_bonus,
                    AVG(last_prod)    AS avg_produktivitet,
                    SUM(shift_runtime) AS total_runtime
                FROM (
                    SELECT
                        skiftraknare,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang   ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus,
                        SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_prod,
                        MAX(runtime_plc) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE (op1 = :op_id OR op2 = :op_id OR op3 = :op_id)
                      AND skiftraknare IS NOT NULL
                      AND datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute(['op_id' => $op_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $shifts = (int)($row['total_shifts'] ?? 0);
            if ($shifts === 0) {
                $this->sendError('Ingen data senaste 7 dagarna för operatör ' . $op_id);
                return;
            }

            $avg_bonus    = round($row['avg_bonus']        ?? 0, 2);
            $avg_prod     = round($row['avg_produktivitet'] ?? 0, 2);
            $total_hours  = round(($row['total_runtime']   ?? 0) / 60, 1);
            $hours_per_shift = $shifts > 0 ? round($total_hours / $shifts, 2) : 8;

            // Tier-multiplikator
            $multiplier = 0.75;
            if ($avg_bonus >= 95) $multiplier = 2.0;
            elseif ($avg_bonus >= 90) $multiplier = 1.5;
            elseif ($avg_bonus >= 80) $multiplier = 1.25;
            elseif ($avg_bonus >= 70) $multiplier = 1.0;

            // Hämta veckobonusmål
            $goalStmt = $this->pdo->prepare("SELECT weekly_bonus_goal FROM bonus_config WHERE id = 1");
            $goalStmt->execute();
            $weeklyGoal = (float)($goalStmt->fetchColumn() ?: 80);

            // Prognos: antag 5 skift/vecka
            $projected_weekly_bonus  = min(round($avg_bonus * $multiplier, 1), 200);
            $pct_of_goal = $weeklyGoal > 0 ? round(($avg_bonus / $weeklyGoal) * 100, 1) : 0;

            $this->sendSuccess([
                'operator_id'            => $op_id,
                'operator_name'          => $opName,
                'shifts_last_7days'      => $shifts,
                'avg_bonus_last_7days'   => $avg_bonus,
                'avg_produktivitet'      => $avg_prod,
                'hours_per_shift'        => $hours_per_shift,
                'tier_multiplier'        => $multiplier,
                'projected_bonus'        => $projected_weekly_bonus,
                'weekly_goal'            => $weeklyGoal,
                'pct_of_goal'            => $pct_of_goal,
            ]);
        } catch (PDOException $e) {
            error_log('BonusAdminController::getOperatorForecast: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Hamta bonusniva-belopp i SEK
     */
    private function getAmounts() {
        try {
            $stmt = $this->pdo->query("
                SELECT level_name, amount_sek, updated_at, updated_by
                FROM bonus_level_amounts
                ORDER BY FIELD(level_name, 'brons', 'silver', 'guld', 'platina')
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $amounts = [];
            $lastUpdated = null;
            $lastUpdatedBy = null;
            foreach ($rows as $row) {
                $amounts[$row['level_name']] = (int)$row['amount_sek'];
                if (!$lastUpdated || $row['updated_at'] > $lastUpdated) {
                    $lastUpdated = $row['updated_at'];
                    $lastUpdatedBy = $row['updated_by'];
                }
            }

            $this->sendSuccess([
                'amounts'         => $amounts,
                'last_updated'    => $lastUpdated,
                'last_updated_by' => $lastUpdatedBy
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::getAmounts: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * Spara bonusniva-belopp i SEK (admin-skyddad)
     */
    private function setAmounts() {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Ogiltig JSON-data');
                return;
            }

            $allowed = ['brons', 'silver', 'guld', 'platina'];
            $updatedBy = mb_substr(strip_tags((string)($_SESSION['username'] ?? 'admin')), 0, 100);

            $stmt = $this->pdo->prepare("
                INSERT INTO bonus_level_amounts (level_name, amount_sek, updated_by)
                VALUES (:level, :amount, :updated_by)
                ON DUPLICATE KEY UPDATE
                    amount_sek = :amount,
                    updated_by = :updated_by
            ");

            $this->pdo->beginTransaction();
            try {
            $saved = [];
            foreach ($allowed as $level) {
                if (!isset($input[$level])) {
                    continue;
                }
                $amount = filter_var($input[$level], FILTER_VALIDATE_INT);
                if ($amount === false || $amount < 0 || $amount > 100000) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    $this->sendError("Ogiltigt belopp for niva: $level (0-100 000 kr)");
                    return;
                }
                $stmt->execute([
                    'level'      => $level,
                    'amount'     => $amount,
                    'updated_by' => $updatedBy
                ]);
                $saved[$level] = $amount;
            }

            if (empty($saved)) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->sendError('Inga giltiga belopp skickades');
                return;
            }

            $this->pdo->commit();
            } catch (Exception $txEx) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $txEx;
            }

            $this->logAudit('set_amounts', 'bonus_level_amounts', null, null, $saved);
            $this->sendSuccess([
                'message' => 'Bonusbelopp sparade',
                'saved'   => $saved
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::setAmounts: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }


    // ============ UTBETALNINGAR ============

    /**
     * GET ?action=bonusadmin&run=list-operators
     * Lista aktiva operatörer (id, name, initialer)
     */
    private function listOperators(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, number
                FROM operators
                WHERE active = 1
                ORDER BY name ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $operators = [];
            foreach ($rows as $r) {
                $words = preg_split('/\s+/', trim($r['name']));
                $initials = '';
                foreach ($words as $w) {
                    if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                }
                $operators[] = [
                    'id'       => (int)$r['id'],
                    'name'     => $r['name'],
                    'number'   => (int)$r['number'],
                    'initialer'=> $initials,
                ];
            }
            $this->sendSuccess(['operators' => $operators]);
        } catch (PDOException $e) {
            error_log('BonusAdminController::listOperators: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET ?action=bonusadmin&run=list-payouts[&op_id=X][&year=YYYY][&status=pending|approved|paid]
     */
    private function listPayouts(): void {
        try {
            $year   = isset($_GET['year'])  ? max(2020, min(2099, intval($_GET['year'])))  : intval(date('Y'));
            $op_id  = isset($_GET['op_id']) ? max(0, intval($_GET['op_id'])) : 0;
            $status = trim($_GET['status'] ?? '');
            $allowedStatuses = ['pending', 'approved', 'paid'];

            $where  = "WHERE YEAR(bp.period_start) = :year";
            $params = ['year' => $year];
            if ($op_id > 0) {
                $where .= " AND bp.op_id = :op_id";
                $params['op_id'] = $op_id;
            }
            if ($status !== '' && in_array($status, $allowedStatuses, true)) {
                $where .= " AND bp.status = :status";
                $params['status'] = $status;
            }

            $sql = "
                SELECT
                    bp.id,
                    bp.op_id,
                    o.name          AS namn,
                    o.number        AS op_number,
                    bp.period_start,
                    bp.period_end,
                    COALESCE(bp.period_label, '') AS period_label,
                    COALESCE(bp.bonus_level, 'none') AS bonus_level,
                    COALESCE(bp.status, 'pending') AS status,
                    bp.amount_sek,
                    bp.ibc_count,
                    bp.avg_ibc_per_h,
                    bp.avg_quality_pct,
                    bp.notes,
                    bp.approved_by,
                    bp.approved_at,
                    bp.paid_at,
                    bp.created_at
                FROM bonus_payouts bp
                LEFT JOIN operators o ON o.id = bp.op_id
                $where
                ORDER BY bp.period_start DESC, bp.created_at DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna initialer och typkonvertering
            foreach ($rows as &$r) {
                $words = preg_split('/\s+/', trim($r['namn'] ?? ''));
                $initials = '';
                foreach ($words as $w) {
                    if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                }
                $r['initialer']       = $initials;
                $r['op_id']           = (int)$r['op_id'];
                $r['ibc_count']       = (int)($r['ibc_count'] ?? 0);
                $r['amount_sek']      = (float)$r['amount_sek'];
                $r['avg_ibc_per_h']   = (float)($r['avg_ibc_per_h'] ?? 0);
                $r['avg_quality_pct'] = (float)($r['avg_quality_pct'] ?? 0);
            }
            unset($r);

            // Summera totalt belopp för filtret
            $total_sek = array_sum(array_column($rows, 'amount_sek'));

            $this->sendSuccess([
                'payouts'   => $rows,
                'year'      => $year,
                'total_sek' => round($total_sek, 2),
            ]);
        } catch (PDOException $e) {
            error_log('BonusAdminController::listPayouts: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * POST ?action=bonusadmin&run=update-payout-status
     * Body: { id, status: 'pending'|'approved'|'paid' }
     */
    private function updatePayoutStatus(): void {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = isset($body['id'])     ? intval($body['id'])     : 0;
        $status = trim($body['status'] ?? '');

        $allowedStatuses = ['pending', 'approved', 'paid'];
        if ($id <= 0) {
            $this->sendError('Ogiltigt ID');
            return;
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $this->sendError('Ogiltig status (tillåtna: pending, approved, paid)');
            return;
        }

        try {
            $adminId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $now     = date('Y-m-d H:i:s');

            if ($status === 'approved') {
                $stmt = $this->pdo->prepare("
                    UPDATE bonus_payouts
                    SET status = 'approved', approved_by = :admin_id, approved_at = :now
                    WHERE id = :id
                ");
                $stmt->execute(['admin_id' => $adminId, 'now' => $now, 'id' => $id]);
            } elseif ($status === 'paid') {
                $stmt = $this->pdo->prepare("
                    UPDATE bonus_payouts
                    SET status = 'paid', paid_at = :now
                    WHERE id = :id
                ");
                $stmt->execute(['now' => $now, 'id' => $id]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE bonus_payouts
                    SET status = 'pending', approved_by = NULL, approved_at = NULL, paid_at = NULL
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $id]);
            }

            if ($stmt->rowCount() === 0) {
                $this->sendError('Posten hittades inte', 404);
                return;
            }

            try {
                $this->logAudit('update_status', 'bonus_payout', $id, null, ['status' => $status]);
            } catch (Exception $ae) {
                error_log('BonusAdminController::updatePayoutStatus audit: ' . $ae->getMessage());
            }

            $this->sendSuccess(['id' => $id, 'status' => $status, 'message' => 'Status uppdaterad']);
        } catch (PDOException $e) {
            error_log('BonusAdminController::updatePayoutStatus: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * POST ?action=bonusadmin&run=record-payout
     * Body: { op_id, period_start, period_end, amount_sek, ibc_count, avg_ibc_per_h, avg_quality_pct, notes, period_label, bonus_level }
     */
    private function recordPayout(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $op_id          = isset($body['op_id'])          ? intval($body['op_id'])          : 0;
        $period_start   = trim($body['period_start']   ?? '');
        $period_end     = trim($body['period_end']     ?? '');
        $amount_sek_raw = isset($body['amount_sek'])     ? floatval($body['amount_sek'])     : 0.0;
        $amount_sek     = is_finite($amount_sek_raw) ? min(9999999.99, max(0, $amount_sek_raw)) : 0;
        $ibc_count      = isset($body['ibc_count'])      ? min(9999999, max(0, intval($body['ibc_count'])))           : 0;
        $avg_ibc_raw    = isset($body['avg_ibc_per_h'])  ? floatval($body['avg_ibc_per_h'])     : 0.0;
        $avg_ibc_per_h  = is_finite($avg_ibc_raw) ? min(9999.99, max(0, $avg_ibc_raw)) : 0;
        $avg_qual_raw   = isset($body['avg_quality_pct'])? floatval($body['avg_quality_pct'])   : 0.0;
        $avg_quality_pct= is_finite($avg_qual_raw) ? min(100.0, max(0, $avg_qual_raw)) : 0;
        $notes          = mb_substr(strip_tags(trim($body['notes'] ?? '')), 0, 2000);
        $period_label   = mb_substr(strip_tags(trim($body['period_label'] ?? '')), 0, 50);
        $bonus_level_raw = trim($body['bonus_level'] ?? 'none');
        $allowed_levels  = ['none', 'bronze', 'silver', 'gold', 'platinum'];
        $bonus_level     = in_array($bonus_level_raw, $allowed_levels, true) ? $bonus_level_raw : 'none';

        // Validering
        if ($op_id <= 0) {
            $this->sendError('Ogiltigt operatör-ID');
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_end)) {
            $this->sendError('Ogiltigt datumformat (YYYY-MM-DD krävs)');
            return;
        }
        if ($period_start > $period_end) {
            $this->sendError('period_start får inte vara efter period_end');
            return;
        }
        if ($amount_sek <= 0) {
            $this->sendError('Belopp måste vara större än 0');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bonus_payouts
                    (op_id, period_start, period_end, period_label, bonus_level, status, amount_sek, ibc_count, avg_ibc_per_h, avg_quality_pct, notes, created_by)
                VALUES
                    (:op_id, :period_start, :period_end, :period_label, :bonus_level, 'pending', :amount_sek, :ibc_count, :avg_ibc_per_h, :avg_quality_pct, :notes, :created_by)
            ");
            $stmt->execute([
                'op_id'          => $op_id,
                'period_start'   => $period_start,
                'period_end'     => $period_end,
                'period_label'   => $period_label ?: null,
                'bonus_level'    => $bonus_level,
                'amount_sek'     => $amount_sek,
                'ibc_count'      => $ibc_count,
                'avg_ibc_per_h'  => $avg_ibc_per_h,
                'avg_quality_pct'=> $avg_quality_pct,
                'notes'          => $notes ?: null,
                'created_by'     => $_SESSION['user_id'] ?? null,
            ]);
            $newId = $this->pdo->lastInsertId();

            // Försök logga audit
            try {
                $this->logAudit('create', 'bonus_payout', (int)$newId, null, [
                    'op_id' => $op_id, 'amount_sek' => $amount_sek, 'period' => "{$period_start}–{$period_end}"
                ]);
            } catch (Exception $ae) {
                error_log('BonusAdminController::recordPayout audit: ' . $ae->getMessage());
            }

            $this->sendSuccess(['id' => (int)$newId, 'message' => 'Utbetalning registrerad']);
        } catch (PDOException $e) {
            error_log('BonusAdminController::recordPayout: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * POST ?action=bonusadmin&run=delete-payout
     * Body: { id }
     */
    private function deletePayout(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = isset($body['id']) ? intval($body['id']) : 0;

        if ($id <= 0) {
            $this->sendError('Ogiltigt ID');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM bonus_payouts WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Posten hittades inte', 404);
                return;
            }

            // Försök logga audit
            try {
                $this->logAudit('delete', 'bonus_payout', $id, ['id' => $id], null);
            } catch (Exception $ae) {
                error_log('BonusAdminController::deletePayout audit: ' . $ae->getMessage());
            }

            $this->sendSuccess(['message' => 'Utbetalning borttagen']);
        } catch (PDOException $e) {
            error_log('BonusAdminController::deletePayout: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * GET ?action=bonusadmin&run=payout-summary[&year=YYYY]
     * Statistik per operatör för given år
     */
    private function getPayoutSummary(): void {
        try {
            $year = isset($_GET['year']) ? max(2020, min(2099, intval($_GET['year']))) : intval(date('Y'));

            $stmt = $this->pdo->prepare("
                SELECT
                    bp.op_id,
                    o.name                          AS namn,
                    o.number                        AS op_number,
                    SUM(bp.amount_sek)              AS total_utbetalat,
                    COUNT(*)                        AS antal_utbetalningar,
                    AVG(bp.amount_sek)              AS snitt_per_utbetalning,
                    MAX(bp.period_end)              AS senaste_utbetalning
                FROM bonus_payouts bp
                LEFT JOIN operators o ON o.id = bp.op_id
                WHERE YEAR(bp.period_start) = :year
                GROUP BY bp.op_id, o.name, o.number
                ORDER BY total_utbetalat DESC
            ");
            $stmt->execute(['year' => $year]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                $words = preg_split('/\s+/', trim($r['namn'] ?? ''));
                $initials = '';
                foreach ($words as $w) {
                    if ($w !== '') $initials .= mb_strtoupper(mb_substr($w, 0, 1));
                }
                $r['initialer']            = $initials;
                $r['op_id']                = (int)$r['op_id'];
                $r['total_utbetalat']      = (float)$r['total_utbetalat'];
                $r['antal_utbetalningar']  = (int)$r['antal_utbetalningar'];
                $r['snitt_per_utbetalning']= (float)$r['snitt_per_utbetalning'];
            }
            unset($r);

            $this->sendSuccess(['summary' => $rows, 'year' => $year]);
        } catch (PDOException $e) {
            error_log('BonusAdminController::getPayoutSummary: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ============ HELPER FUNCTIONS ============

    /**
     * GET ?action=bonusadmin&run=fairness&period=YYYY-MM
     *
     * Counterfactual rapport: "Om maskin X inte hade stoppat hade
     * operatör Y tjänat Z kr mer i bonus."
     *
     * 1. Hämta alla skiftrapporter för perioden med operatör-data (op1, op2, op3)
     * 2. Hämta stopptid per skift från stoppage_log + rebotling_ibc rasttime/runtime_plc
     * 3. Beräkna "förlorad produktion" per stopp
     * 4. Simulera ny IBC-total per operatör om stoppen inte hänt
     * 5. Beräkna bonus-diff baserat på bonus_level_amounts
     */
    private function getFairnessAudit(): void {
        $period = trim($_GET['period'] ?? '');

        // Validera YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->sendError('Ogiltigt periodformat. Använd YYYY-MM.');
            return;
        }

        $yearMonth  = $period;
        $startDate  = $yearMonth . '-01';
        $endDate    = date('Y-m-t', strtotime($startDate));

        try {
            // 1. Hämta bonus-belopp per tier
            $tierAmounts = ['brons' => 500, 'silver' => 1000, 'guld' => 2000, 'platina' => 3500];
            try {
                $stmt = $this->pdo->query("SELECT level_name, amount_sek FROM bonus_level_amounts");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $tierAmounts[$row['level_name']] = (int)$row['amount_sek'];
                }
            } catch (PDOException $e) {
                error_log('BonusAdminController::exportReport tier amounts: ' . $e->getMessage());
                // Använd defaults
            }

            // Bonus-tiers (bonus_poang -> tier_key)
            $bonusTiers = [
                ['min' => 95, 'key' => 'platina', 'label' => 'Platina'],
                ['min' => 90, 'key' => 'guld',    'label' => 'Guld'],
                ['min' => 80, 'key' => 'silver',  'label' => 'Silver'],
                ['min' => 70, 'key' => 'brons',   'label' => 'Brons'],
                ['min' => 0,  'key' => 'ingen',   'label' => 'Ingen'],
            ];

            $matchTier = function (float $avgBonus) use ($bonusTiers, $tierAmounts): array {
                foreach ($bonusTiers as $t) {
                    if ($avgBonus >= $t['min']) {
                        return [
                            'key'   => $t['key'],
                            'label' => $t['label'],
                            'sek'   => $tierAmounts[$t['key']] ?? 0,
                        ];
                    }
                }
                return ['key' => 'ingen', 'label' => 'Ingen', 'sek' => 0];
            };

            // 2. Hämta operatörsnamn
            $opNames = $this->pdo->query("SELECT number, name FROM operators")->fetchAll(PDO::FETCH_KEY_PAIR);

            // 3. Per-skift per operatör: IBC, runtime, rasttime, bonus_poang
            $perShiftRows = [];
            for ($pos = 1; $pos <= 3; $pos++) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        op{$pos}       AS operator_id,
                        skiftraknare,
                        MAX(ibc_ok)         AS shift_ibc_ok,
                        MAX(runtime_plc)    AS shift_runtime,
                        MAX(rasttime)       AS shift_rasttime,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE op{$pos} IS NOT NULL AND op{$pos} > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :start AND :end
                    GROUP BY op{$pos}, skiftraknare
                ");
                $stmt->execute(['start' => $startDate, 'end' => $endDate]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $key = $row['operator_id'] . '_' . $row['skiftraknare'] . '_' . $pos;
                    $perShiftRows[$key] = $row;
                }
            }

            // 4. Aggregera per operatör
            $opData = [];
            foreach ($perShiftRows as $row) {
                $opId = (int)$row['operator_id'];
                if (!isset($opData[$opId])) {
                    $opData[$opId] = [
                        'shifts'         => 0,
                        'total_ibc_ok'   => 0,
                        'total_runtime'  => 0,
                        'total_rasttime' => 0,
                        'bonus_sum'      => 0,
                    ];
                }
                $opData[$opId]['shifts']++;
                $opData[$opId]['total_ibc_ok']   += (int)$row['shift_ibc_ok'];
                $opData[$opId]['total_runtime']   += (int)$row['shift_runtime'];
                $opData[$opId]['total_rasttime']  += (int)$row['shift_rasttime'];
                $opData[$opId]['bonus_sum']       += (float)$row['last_bonus'];
            }

            if (empty($opData)) {
                $this->sendSuccess([
                    'period'    => $yearMonth,
                    'operators' => [],
                    'summary'   => [
                        'total_lost_bonus_kr'  => 0,
                        'most_affected'        => null,
                        'longest_stop_hours'   => 0,
                    ],
                ]);
                return;
            }

            // 5. Hämta stopploggar för perioden
            $stoppStmt = $this->pdo->prepare("
                SELECT
                    s.start_time,
                    s.end_time,
                    s.duration_minutes,
                    COALESCE(r.name, 'Okänd orsak') AS reason_name,
                    r.category
                FROM stoppage_log s
                LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                WHERE s.line = 'rebotling'
                  AND s.start_time >= :start
                  AND s.start_time < :end_ts
                ORDER BY s.duration_minutes DESC
            ");
            $stoppStmt->execute([
                'start'  => $startDate . ' 00:00:00',
                'end_ts' => date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00',
            ]);
            $stoppages = $stoppStmt->fetchAll(PDO::FETCH_ASSOC);

            // Totalt stopptid i minuter
            $totalStopMinutes = 0;
            $stopReasonTotals = [];
            foreach ($stoppages as $s) {
                $dur = (int)($s['duration_minutes'] ?? 0);
                $totalStopMinutes += $dur;
                $reason = $s['reason_name'] ?? 'Okänd';
                if (!isset($stopReasonTotals[$reason])) {
                    $stopReasonTotals[$reason] = 0;
                }
                $stopReasonTotals[$reason] += $dur;
            }
            arsort($stopReasonTotals);
            $topStopReasons = array_slice(array_keys($stopReasonTotals), 0, 3);

            // Längsta enskilda stopp i timmar
            $longestStopMinutes = 0;
            foreach ($stoppages as $s) {
                $dur = (int)($s['duration_minutes'] ?? 0);
                if ($dur > $longestStopMinutes) $longestStopMinutes = $dur;
            }
            $longestStopHours = round($longestStopMinutes / 60, 1);

            // 6. Beräkna per operatör: snitt IBC/h, förlorad produktion, simulerad IBC
            // Fördela stopptid lika — varje operatör förlorar sin andel
            // (alla operatörer som jobbar vid stopp påverkas lika)
            $results = [];
            $totalLostBonusKr = 0;
            $mostAffected = null;
            $maxBonusDiff = 0;

            foreach ($opData as $opId => $data) {
                $shifts      = $data['shifts'];
                $totalIbc    = $data['total_ibc_ok'];
                $totalRuntime = $data['total_runtime']; // minuter
                $avgBonus    = $shifts > 0 ? $data['bonus_sum'] / $shifts : 0;

                $totalHours  = $totalRuntime > 0 ? $totalRuntime / 60.0 : 0;
                $ibcPerHour  = $totalHours > 0 ? $totalIbc / $totalHours : 0;

                // Förlorad tid per operatör = total stopptid fördelat proportionellt
                // per skiftandel (operatörens skift / totalt antal skift)
                $totalShiftsAll = array_sum(array_column($opData, 'shifts'));
                $shiftShare     = $totalShiftsAll > 0 ? $shifts / $totalShiftsAll : 0;
                $lostMinutes    = $totalStopMinutes * $shiftShare;
                $lostHours      = $lostMinutes / 60.0;

                // Simulerad produktion: faktisk + (förlorad tid * snitt IBC/h)
                $lostIbc         = $ibcPerHour * $lostHours;
                $simulatedIbc    = $totalIbc + $lostIbc;

                // Simulerad bonus_poang (proportionell ökning baserad på IBC)
                $ibcRatio = $totalIbc > 0 ? $simulatedIbc / $totalIbc : 1;
                $simulatedBonus = min(100, $avgBonus * $ibcRatio);

                // Matcha tier
                $actualTier    = $matchTier($avgBonus);
                $simulatedTier = $matchTier($simulatedBonus);

                $bonusDiffKr = ($simulatedTier['sek'] - $actualTier['sek']);
                $totalLostBonusKr += $bonusDiffKr;

                if ($bonusDiffKr > $maxBonusDiff) {
                    $maxBonusDiff = $bonusDiffKr;
                    $mostAffected = $opNames[$opId] ?? 'Operatör ' . $opId;
                }

                $results[] = [
                    'operator_id'         => $opId,
                    'name'                => $opNames[$opId] ?? 'Operatör ' . $opId,
                    'actual_ibc'          => (int)$totalIbc,
                    'simulated_ibc'       => (int)round($simulatedIbc),
                    'ibc_diff'            => (int)round($lostIbc),
                    'actual_bonus_poang'  => round($avgBonus, 1),
                    'simulated_bonus_poang' => round($simulatedBonus, 1),
                    'actual_bonus_tier'   => $actualTier['label'],
                    'simulated_bonus_tier' => $simulatedTier['label'],
                    'actual_bonus_kr'     => $actualTier['sek'],
                    'simulated_bonus_kr'  => $simulatedTier['sek'],
                    'bonus_diff_kr'       => $bonusDiffKr,
                    'lost_hours'          => round($lostHours, 2),
                    'top_stop_reasons'    => $topStopReasons,
                    'shifts'              => $shifts,
                    'avg_ibc_per_hour'    => round($ibcPerHour, 2),
                ];
            }

            // Sortera: mest drabbad operatör först
            usort($results, fn($a, $b) => $b['bonus_diff_kr'] <=> $a['bonus_diff_kr']);

            $this->sendSuccess([
                'period'    => $yearMonth,
                'operators' => $results,
                'summary'   => [
                    'total_lost_bonus_kr'  => $totalLostBonusKr,
                    'most_affected'        => $mostAffected,
                    'longest_stop_hours'   => $longestStopHours,
                    'total_stop_hours'     => round($totalStopMinutes / 60, 1),
                    'total_stoppages'      => count($stoppages),
                    'top_stop_reasons'     => $topStopReasons,
                ],
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::getFairnessAudit: ' . $e->getMessage());
            $this->sendError('Databasfel vid rättviseaudit', 500);
        }
    }

    /**
     * GET ?action=bonusadmin&run=bonus-simulator
     *
     * Hämtar aktuell bonusdata per operatör (senaste 30 dagarna) samt konfiguration.
     * Kan ta query-parametrar för simulering:
     *   - eff_w_1, prod_w_1, qual_w_1  (viktningar FoodGrade, summerar till 1.0)
     *   - eff_w_4, prod_w_4, qual_w_4  (viktningar NonUN)
     *   - eff_w_5, prod_w_5, qual_w_5  (viktningar Tvättade)
     *   - target_1, target_4, target_5 (produktivitetsmål IBC/h)
     *   - max_bonus                     (maxtak, standard 200)
     *   - tier_95, tier_90, tier_80, tier_70 (tier-multiplikatorer)
     */
    private function getBonusSimulator(): void {
        try {
            $days = max(1, min(90, (int)($_GET['days'] ?? 30)));
            $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
            $dateTo   = date('Y-m-d');

            // --- Läs simulerings-parametrar från query-string ---
            $simWeights = [
                1 => [
                    'eff'  => $this->clampWeight((float)($_GET['eff_w_1']  ?? 0.30)),
                    'prod' => $this->clampWeight((float)($_GET['prod_w_1'] ?? 0.30)),
                    'qual' => $this->clampWeight((float)($_GET['qual_w_1'] ?? 0.40)),
                ],
                4 => [
                    'eff'  => $this->clampWeight((float)($_GET['eff_w_4']  ?? 0.35)),
                    'prod' => $this->clampWeight((float)($_GET['prod_w_4'] ?? 0.45)),
                    'qual' => $this->clampWeight((float)($_GET['qual_w_4'] ?? 0.20)),
                ],
                5 => [
                    'eff'  => $this->clampWeight((float)($_GET['eff_w_5']  ?? 0.40)),
                    'prod' => $this->clampWeight((float)($_GET['prod_w_5'] ?? 0.35)),
                    'qual' => $this->clampWeight((float)($_GET['qual_w_5'] ?? 0.25)),
                ],
            ];

            $simTargets = [
                1 => max(1.0, min(100.0, (float)($_GET['target_1'] ?? 12.0))),
                4 => max(1.0, min(100.0, (float)($_GET['target_4'] ?? 20.0))),
                5 => max(1.0, min(100.0, (float)($_GET['target_5'] ?? 15.0))),
            ];

            $simMaxBonus = max(100, min(500, (int)($_GET['max_bonus'] ?? 200)));

            $simTiers = [
                95 => max(1.0, min(5.0, (float)($_GET['tier_95'] ?? 2.00))),
                90 => max(1.0, min(5.0, (float)($_GET['tier_90'] ?? 1.50))),
                80 => max(1.0, min(5.0, (float)($_GET['tier_80'] ?? 1.25))),
                70 => max(0.5, min(5.0, (float)($_GET['tier_70'] ?? 1.00))),
                0  => max(0.0, min(2.0, (float)($_GET['tier_0']  ?? 0.75))),
            ];

            // --- Standardkonfiguration (från DB eller defaults) ---
            $defaultWeights = [
                1 => ['eff' => 0.30, 'prod' => 0.30, 'qual' => 0.40],
                4 => ['eff' => 0.35, 'prod' => 0.45, 'qual' => 0.20],
                5 => ['eff' => 0.40, 'prod' => 0.35, 'qual' => 0.25],
            ];
            $defaultTargets = [1 => 12.0, 4 => 20.0, 5 => 15.0];
            $defaultTiers   = [95 => 2.00, 90 => 1.50, 80 => 1.25, 70 => 1.00, 0 => 0.75];
            $defaultMaxBonus = 200;

            // Hämta från DB om finns
            try {
                $cfgStmt = $this->pdo->query("SELECT * FROM bonus_config WHERE id = 1");
                $cfg = $cfgStmt ? $cfgStmt->fetch(PDO::FETCH_ASSOC) : false;
                if ($cfg) {
                    $wfg = json_decode($cfg['weights_foodgrade'] ?? '{}', true);
                    $wnu = json_decode($cfg['weights_nonun'] ?? '{}', true);
                    $wtv = json_decode($cfg['weights_tvattade'] ?? '{}', true);
                    if (is_array($wfg) && !empty($wfg)) $defaultWeights[1] = $wfg;
                    if (is_array($wnu) && !empty($wnu)) $defaultWeights[4] = $wnu;
                    if (is_array($wtv) && !empty($wtv)) $defaultWeights[5] = $wtv;
                    if (!empty($cfg['productivity_target_foodgrade'])) $defaultTargets[1] = (float)$cfg['productivity_target_foodgrade'];
                    if (!empty($cfg['productivity_target_nonun']))     $defaultTargets[4] = (float)$cfg['productivity_target_nonun'];
                    if (!empty($cfg['productivity_target_tvattade']))  $defaultTargets[5] = (float)$cfg['productivity_target_tvattade'];
                    if (!empty($cfg['max_bonus'])) $defaultMaxBonus = (int)$cfg['max_bonus'];
                }
            } catch (PDOException $e) {
                error_log('BonusAdminController::operatorForecast config: ' . $e->getMessage());
                // Fortsätt med defaults
            }

            // --- Hämta rådata per operatör (senaste $days dagar, per skift) ---
            $stmt = $this->pdo->prepare("
                SELECT
                    op_id,
                    skiftraknare,
                    MAX(ibc_ok)     AS shift_ibc_ok,
                    MAX(ibc_ej_ok)  AS shift_ibc_ej_ok,
                    MAX(bur_ej_ok)  AS shift_bur_ej_ok,
                    MAX(runtime_plc) AS shift_runtime,
                    SUBSTRING_INDEX(GROUP_CONCAT(produkt ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS produkt,
                    SUBSTRING_INDEX(GROUP_CONCAT(effektivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_eff,
                    SUBSTRING_INDEX(GROUP_CONCAT(produktivitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_prod,
                    SUBSTRING_INDEX(GROUP_CONCAT(kvalitet ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_qual,
                    SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS last_bonus
                FROM (
                    SELECT op1 AS op_id, skiftraknare, ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, produkt, effektivitet, produktivitet, kvalitet, bonus_poang, datum
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND op1 > 0 AND skiftraknare IS NOT NULL
                    UNION ALL
                    SELECT op2, skiftraknare, ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, produkt, effektivitet, produktivitet, kvalitet, bonus_poang, datum
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND op2 > 0 AND skiftraknare IS NOT NULL
                    UNION ALL
                    SELECT op3, skiftraknare, ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, produkt, effektivitet, produktivitet, kvalitet, bonus_poang, datum
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND op3 > 0 AND skiftraknare IS NOT NULL
                ) AS all_ops
                GROUP BY op_id, skiftraknare
            ");
            $stmt->execute([
                'from1' => $dateFrom, 'to1' => $dateTo,
                'from2' => $dateFrom, 'to2' => $dateTo,
                'from3' => $dateFrom, 'to3' => $dateTo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta operatörsnamn
            $opNames = $this->pdo->query("SELECT number, name FROM operators")->fetchAll(PDO::FETCH_KEY_PAIR);

            // --- Aggregera per operatör: beräkna nuvarande och simulerad bonus ---
            $operatorer = [];
            foreach ($rows as $row) {
                $opId = (int)$row['op_id'];
                $produkt = (int)($row['produkt'] ?: 1);
                if (!in_array($produkt, [1, 4, 5], true)) $produkt = 1;

                $ibcOk    = (int)$row['shift_ibc_ok'];
                $ibcEjOk  = (int)$row['shift_ibc_ej_ok'];
                $burEjOk  = (int)$row['shift_bur_ej_ok'];
                $runtime  = max(1, (int)$row['shift_runtime']);

                // Beräkna KPI:er
                $total    = $ibcOk + $ibcEjOk;
                $eff      = $total > 0 ? ($ibcOk / $total) * 100 : 0;
                $prodActual = ($ibcOk * 60.0) / $runtime;
                $qual     = $ibcOk > 0 ? (($ibcOk - min($burEjOk, $ibcOk)) / $ibcOk) * 100 : 0;

                // --- Aktuell bonus (med standardvikter) ---
                $curW      = $defaultWeights[$produkt] ?? ['eff'=>0.40,'prod'=>0.40,'qual'=>0.20];
                $curTarget = $defaultTargets[$produkt] ?? 15.0;
                $curProdNorm = min(($prodActual / $curTarget) * 100, 120);
                $curBase   = ($eff * $curW['eff']) + ($curProdNorm * $curW['prod']) + ($qual * $curW['qual']);
                $curTierM  = $this->getTierMultiplierValue($curBase, $defaultTiers);
                $curFinal  = min(round($curBase * $curTierM, 2), $defaultMaxBonus);
                $curTierName = $this->getTierName($curBase, $defaultTiers);

                // --- Simulerad bonus (med justerade parametrar) ---
                $simW      = $simWeights[$produkt] ?? $simWeights[1];
                $simTarget = $simTargets[$produkt] ?? 15.0;
                $simProdNorm = min(($prodActual / $simTarget) * 100, 120);
                $simBase   = ($eff * $simW['eff']) + ($simProdNorm * $simW['prod']) + ($qual * $simW['qual']);
                $simTierM  = $this->getTierMultiplierValue($simBase, $simTiers);
                $simFinal  = min(round($simBase * $simTierM, 2), $simMaxBonus);
                $simTierName = $this->getTierName($simBase, $simTiers);

                if (!isset($operatorer[$opId])) {
                    $operatorer[$opId] = [
                        'operator_id'      => $opId,
                        'operator_namn'    => $opNames[$opId] ?? ('Operatör ' . $opId),
                        'antal_skift'      => 0,
                        'total_ibc_ok'     => 0,
                        'aktuell_eff_sum'  => 0.0,
                        'aktuell_prod_sum' => 0.0,
                        'aktuell_qual_sum' => 0.0,
                        'aktuell_bonus_sum'  => 0.0,
                        'simulerad_bonus_sum'=> 0.0,
                        'produkt'          => $produkt,
                    ];
                }

                $operatorer[$opId]['antal_skift']++;
                $operatorer[$opId]['total_ibc_ok']      += $ibcOk;
                $operatorer[$opId]['aktuell_eff_sum']   += $eff;
                $operatorer[$opId]['aktuell_prod_sum']  += $prodActual;
                $operatorer[$opId]['aktuell_qual_sum']  += $qual;
                $operatorer[$opId]['aktuell_bonus_sum'] += $curFinal;
                $operatorer[$opId]['simulerad_bonus_sum'] += $simFinal;
                $operatorer[$opId]['aktuell_tier'] = $curTierName;
                $operatorer[$opId]['simulerad_tier'] = $simTierName;
            }

            // Beräkna snitt och diff
            $result = [];
            foreach ($operatorer as $opId => $op) {
                $n = max(1, $op['antal_skift']);
                $curAvg = round($op['aktuell_bonus_sum'] / $n, 1);
                $simAvg = round($op['simulerad_bonus_sum'] / $n, 1);
                $result[] = [
                    'operator_id'         => $op['operator_id'],
                    'operator_namn'       => $op['operator_namn'],
                    'antal_skift'         => $op['antal_skift'],
                    'total_ibc_ok'        => $op['total_ibc_ok'],
                    'snitt_effektivitet'  => round($op['aktuell_eff_sum'] / $n, 1),
                    'snitt_produktivitet' => round($op['aktuell_prod_sum'] / $n, 1),
                    'snitt_kvalitet'      => round($op['aktuell_qual_sum'] / $n, 1),
                    'aktuell_bonus'       => $curAvg,
                    'simulerad_bonus'     => $simAvg,
                    'bonus_diff'          => round($simAvg - $curAvg, 1),
                    'aktuell_tier'        => $op['aktuell_tier'] ?? '—',
                    'simulerad_tier'      => $op['simulerad_tier'] ?? '—',
                    'produkt'             => $op['produkt'],
                ];
            }

            // Sortera: flest skift först
            usort($result, fn($a, $b) => $b['antal_skift'] <=> $a['antal_skift']);

            $this->sendSuccess([
                'period_from'  => $dateFrom,
                'period_to'    => $dateTo,
                'days'         => $days,
                'operatorer'   => $result,
                'aktuella_parametrar' => [
                    'vikter'  => $defaultWeights,
                    'mal'     => $defaultTargets,
                    'tiers'   => $defaultTiers,
                    'max_bonus' => $defaultMaxBonus,
                ],
                'simulerade_parametrar' => [
                    'vikter'  => $simWeights,
                    'mal'     => $simTargets,
                    'tiers'   => $simTiers,
                    'max_bonus' => $simMaxBonus,
                ],
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdminController::getBonusSimulator: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    /**
     * POST ?action=bonusadmin&run=save-simulator-params
     * Sparar simulerade parametrar som nya bonus_config-värden.
     */
    private function saveSimulatorParams(): void {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Ogiltigt JSON-format');
                return;
            }

            $vikter  = $input['vikter']    ?? null;
            $mal     = $input['mal']       ?? null;
            $maxBonus = isset($input['max_bonus']) ? (int)$input['max_bonus'] : null;

            if (!$vikter && !$mal && !$maxBonus) {
                $this->sendError('Inga parametrar att spara');
                return;
            }

            $updates = [];
            $params  = ['id' => 1];

            if ($vikter) {
                $map = [1 => 'weights_foodgrade', 4 => 'weights_nonun', 5 => 'weights_tvattade'];
                foreach ($map as $prodId => $col) {
                    if (isset($vikter[$prodId])) {
                        $w = $vikter[$prodId];
                        $eff  = (float)($w['eff']  ?? 0);
                        $prod = (float)($w['prod'] ?? 0);
                        $qual = (float)($w['qual'] ?? 0);
                        $sum  = $eff + $prod + $qual;
                        if (abs($sum - 1.0) > 0.01) {
                            $this->sendError("Viktarna för produkt $prodId summerar inte till 1.0 (summa: $sum)");
                            return;
                        }
                        $updates[] = "$col = :$col";
                        $params[$col] = json_encode(['eff' => round($eff,3), 'prod' => round($prod,3), 'qual' => round($qual,3)], JSON_UNESCAPED_UNICODE);
                    }
                }
            }

            if ($mal) {
                $malMap = [1 => 'productivity_target_foodgrade', 4 => 'productivity_target_nonun', 5 => 'productivity_target_tvattade'];
                foreach ($malMap as $prodId => $col) {
                    if (isset($mal[$prodId])) {
                        $v = max(1.0, min(100.0, (float)$mal[$prodId]));
                        $updates[] = "$col = :$col";
                        $params[$col] = round($v, 2);
                    }
                }
            }

            if ($maxBonus) {
                $maxBonus = max(100, min(500, $maxBonus));
                $updates[] = 'max_bonus = :max_bonus';
                $params['max_bonus'] = $maxBonus;
            }

            if (empty($updates)) {
                $this->sendError('Inga giltiga parametrar att spara');
                return;
            }

            $updates[] = 'updated_by = :updated_by';
            $params['updated_by'] = $_SESSION['username'] ?? 'admin';

            // Wrappa INSERT IGNORE + UPDATE i transaktion
            $this->pdo->beginTransaction();

            $this->pdo->prepare("INSERT IGNORE INTO bonus_config (id) VALUES (1)")->execute();
            $updateKeys = array_map(fn($k) => "$k = :$k", array_keys(array_diff_key($params, ['id' => null])));
            $updateClause = implode(', ', $updateKeys);
            $stmt = $this->pdo->prepare("UPDATE bonus_config SET $updateClause WHERE id = 1");
            $stmt->execute(array_diff_key($params, ['id' => null]));

            $this->pdo->commit();

            $this->logAudit('save_simulator_params', 'config', 1, null, $input);

            $this->sendSuccess(['message' => 'Parametrar sparade', 'uppdaterat' => count($updates) - 1]);

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('BonusAdminController::saveSimulatorParams: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    private function clampWeight(float $v): float {
        return max(0.0, min(1.0, round($v, 3)));
    }

    private function getTierMultiplierValue(float $base, array $tiers): float {
        krsort($tiers);
        foreach ($tiers as $threshold => $multiplier) {
            if ($base >= $threshold) return (float)$multiplier;
        }
        return 0.75;
    }

    private function getTierName(float $base, array $tiers): string {
        $names = [95 => 'Outstanding', 90 => 'Excellent', 80 => 'God prestanda', 70 => 'Basbonus', 0 => 'Under förväntan'];
        krsort($tiers);
        foreach ($tiers as $threshold => $_) {
            if ($base >= $threshold) return $names[$threshold] ?? 'Tier ' . $threshold;
        }
        return 'Under förväntan';
    }

    private function isAdmin(): bool {
        return !empty($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'developer'], true);
    }

    private function exportCSV(array $data, string $filename) {
        // Sanitize filename to prevent header injection
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // BOM for Excel UTF-8
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        if ($output === false) {
            error_log('BonusAdminController::exportCSV: fopen(php://output) misslyckades');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte skapa CSV-export'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Headers
        if (count($data) > 0) {
            fputcsv($output, array_keys($data[0]), ';');
        }

        // Data
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Log audit trail for admin actions
     */
    private function logAudit(
        string $action,
        string $entity_type,
        ?int $entity_id,
        ?array $old_value,
        ?array $new_value
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bonus_audit_log (
                    action,
                    entity_type,
                    entity_id,
                    old_value,
                    new_value,
                    user,
                    ip_address
                )
                VALUES (
                    :action,
                    :entity_type,
                    :entity_id,
                    :old_value,
                    :new_value,
                    :user,
                    :ip_address
                )
            ");

            $stmt->execute([
                'action' => $action,
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'old_value' => $old_value ? json_encode($old_value, JSON_UNESCAPED_UNICODE) : null,
                'new_value' => $new_value ? json_encode($new_value, JSON_UNESCAPED_UNICODE) : null,
                'user' => $_SESSION['username'] ?? 'admin',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            // Don't fail the main operation if audit logging fails
            error_log('BonusAdminController::log — audit logging misslyckades: ' . $e->getMessage());
        }
    }
}
