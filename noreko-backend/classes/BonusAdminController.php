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
        if (session_status() === PHP_SESSION_NONE) session_start();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run = $_GET['run'] ?? '';

        // Admin-kontroll via session
        if (!$this->isAdmin()) {
            $this->sendError('Unauthorized - Admin access required', 403);
            return;
        }

        switch ($run) {
            case 'get_config':
                $this->getConfig();
                break;
            case 'update_weights':
                if ($method !== 'POST') {
                    $this->sendError('POST required');
                    return;
                }
                $this->updateWeights();
                break;
            case 'set_targets':
                if ($method !== 'POST') {
                    $this->sendError('POST required');
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
                    $this->sendError('POST required');
                    return;
                }
                $this->approveBonuses();
                break;
            case 'get_stats':
                $this->getSystemStats();
                break;
            case 'set_weekly_goal':
                if ($method !== 'POST') {
                    $this->sendError('POST required');
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
                    $this->sendError('POST required');
                    return;
                }
                $this->setAmounts();
                break;
            default:
                $this->sendError('Invalid action: ' . $run);
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
                    'weights_foodgrade' => json_encode(['eff' => 0.30, 'prod' => 0.30, 'qual' => 0.40]),
                    'weights_nonun' => json_encode(['eff' => 0.35, 'prod' => 0.45, 'qual' => 0.20]),
                    'weights_tvattade' => json_encode(['eff' => 0.40, 'prod' => 0.35, 'qual' => 0.25]),
                    'productivity_target_foodgrade' => 12.0,
                    'productivity_target_nonun' => 20.0,
                    'productivity_target_tvattade' => 15.0,
                    'tier_multipliers' => json_encode([
                        ['threshold' => 95, 'multiplier' => 2.0, 'name' => 'Outstanding'],
                        ['threshold' => 90, 'multiplier' => 1.5, 'name' => 'Excellent'],
                        ['threshold' => 80, 'multiplier' => 1.25, 'name' => 'God prestanda'],
                        ['threshold' => 70, 'multiplier' => 1.0, 'name' => 'Basbonus']
                    ]),
                    'max_bonus' => 200,
                    'team_bonus_enabled' => false,
                    'safety_bonus_enabled' => false
                ];
            } else {
                // Decode JSON fields
                $config['weights_foodgrade'] = json_decode($config['weights_foodgrade'], true);
                $config['weights_nonun'] = json_decode($config['weights_nonun'], true);
                $config['weights_tvattade'] = json_decode($config['weights_tvattade'], true);
                $config['tier_multipliers'] = json_decode($config['tier_multipliers'], true);
            }

            $this->sendSuccess($config);

        } catch (PDOException $e) {
            error_log('BonusAdmin getConfig error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * Uppdatera viktningar för produkt
     */
    private function updateWeights() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Invalid JSON input: ' . json_last_error_msg());
                return;
            }

            if (!isset($input['produkt']) || !isset($input['weights'])) {
                $this->sendError('Missing required fields: produkt, weights');
                return;
            }

            $produkt = filter_var($input['produkt'], FILTER_VALIDATE_INT);
            if ($produkt === false) {
                $this->sendError('Invalid product ID format');
                return;
            }

            $weights = $input['weights'];

            // Validate weights structure
            if (!isset($weights['eff']) || !isset($weights['prod']) || !isset($weights['qual'])) {
                $this->sendError('Missing weight components (eff, prod, qual)');
                return;
            }

            // Validate weights are numeric and in range
            $eff = filter_var($weights['eff'], FILTER_VALIDATE_FLOAT);
            $prod = filter_var($weights['prod'], FILTER_VALIDATE_FLOAT);
            $qual = filter_var($weights['qual'], FILTER_VALIDATE_FLOAT);

            if ($eff === false || $prod === false || $qual === false) {
                $this->sendError('Weights must be numeric values');
                return;
            }

            if ($eff < 0 || $eff > 1 || $prod < 0 || $prod > 1 || $qual < 0 || $qual > 1) {
                $this->sendError('Weights must be between 0 and 1');
                return;
            }

            // Validate weights sum to 1.0
            $sum = $eff + $prod + $qual;
            if (abs($sum - 1.0) > 0.001) {
                $this->sendError('Weights must sum to 1.0 (currently: ' . round($sum, 3) . ')');
                return;
            }

            // Determine column name (whitelist protection)
            $column_map = [
                1 => 'weights_foodgrade',
                4 => 'weights_nonun',
                5 => 'weights_tvattade'
            ];

            if (!isset($column_map[$produkt])) {
                $this->sendError('Invalid product ID (must be 1, 4, or 5)');
                return;
            }

            $column = $column_map[$produkt];
            $weights_normalized = [
                'eff' => round($eff, 3),
                'prod' => round($prod, 3),
                'qual' => round($qual, 3)
            ];
            $weights_json = json_encode($weights_normalized);

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
                'message' => 'Weights updated successfully',
                'produkt' => $produkt,
                'weights' => $weights_normalized
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdmin updateWeights error: ' . $e->getMessage());
            $this->sendError('Database operation failed');
        }
    }

    /**
     * Sätt produktivitetsmål
     */
    private function setTargets() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Invalid JSON input: ' . json_last_error_msg());
                return;
            }

            if (!isset($input['targets'])) {
                $this->sendError('Missing targets field');
                return;
            }

            $targets = $input['targets'];

            // Validate and sanitize targets
            $foodgrade = filter_var($targets['foodgrade'] ?? 12.0, FILTER_VALIDATE_FLOAT);
            $nonun = filter_var($targets['nonun'] ?? 20.0, FILTER_VALIDATE_FLOAT);
            $tvattade = filter_var($targets['tvattade'] ?? 15.0, FILTER_VALIDATE_FLOAT);

            if ($foodgrade === false || $nonun === false || $tvattade === false) {
                $this->sendError('Targets must be numeric values');
                return;
            }

            // Validate reasonable ranges (1-100 IBC per hour)
            if ($foodgrade < 1 || $foodgrade > 100 ||
                $nonun < 1 || $nonun > 100 ||
                $tvattade < 1 || $tvattade > 100) {
                $this->sendError('Targets must be between 1 and 100 IBC/hour');
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
                'message' => 'Productivity targets updated',
                'targets' => [
                    'foodgrade' => round($foodgrade, 2),
                    'nonun' => round($nonun, 2),
                    'tvattade' => round($tvattade, 2)
                ]
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdmin setTargets error: ' . $e->getMessage());
            $this->sendError('Database operation failed');
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
            error_log('BonusAdmin getBonusPeriods error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * Exportera bonusrapport (CSV)
     */
    private function exportReport() {
        $period = $_GET['period'] ?? date('Y-m');
        $format = $_GET['format'] ?? 'csv';

        // Validate period format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->sendError('Invalid period format (expected: YYYY-MM)');
            return;
        }

        // Validate format
        $allowed_formats = ['csv', 'json'];
        if (!in_array($format, $allowed_formats, true)) {
            $this->sendError('Invalid format (allowed: csv, json)');
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
            ");

            $stmt->execute(['period' => $period]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($data)) {
                $this->sendError('No data found for period: ' . $period, 404);
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
            error_log('BonusAdmin exportReport error: ' . $e->getMessage());
            $this->sendError('Database operation failed');
        }
    }

    /**
     * Godkänn bonusar för period
     */
    private function approveBonuses() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Invalid JSON input: ' . json_last_error_msg());
                return;
            }

            if (!isset($input['period'])) {
                $this->sendError('Missing period field');
                return;
            }

            $period = $input['period'];

            // Validate period format (YYYY-MM)
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                $this->sendError('Invalid period format (expected: YYYY-MM)');
                return;
            }

            // Sanitize approved_by (strip tags + limit length)
            $approved_by = strip_tags((string)($input['approved_by'] ?? $_SESSION['username'] ?? 'admin'));
            $approved_by = substr($approved_by, 0, 100); // Limit length

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
                $this->sendError('No unapproved bonuses found for period: ' . $period, 404);
                return;
            }

            // Log to audit trail
            $this->logAudit('approve_bonuses', 'period', null, null, [
                'period' => $period,
                'cycles_approved' => $affected,
                'approved_by' => $approved_by
            ]);

            $this->sendSuccess([
                'message' => 'Bonuses approved',
                'period' => $period,
                'cycles_approved' => $affected
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdmin approveBonuses error: ' . $e->getMessage());
            $this->sendError('Database operation failed');
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

            $trend_pct = $trend['previous_avg'] > 0
                ? round((($trend['recent_avg'] - $trend['previous_avg']) / $trend['previous_avg']) * 100, 2)
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
            error_log('BonusAdmin getSystemStats error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * Sätt veckobonusmål (poäng) — sparas i bonus_config
     */
    private function setWeeklyGoal() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Invalid JSON input');
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
            error_log('BonusAdmin setWeeklyGoal error: ' . $e->getMessage());
            $this->sendError('Databasfel');
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
            $nameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE id = ?");
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
            $projected_shifts_week   = 5; // normalt en vecka
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
            error_log('BonusAdmin getOperatorForecast error: ' . $e->getMessage());
            $this->sendError('Databasfel');
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
            error_log('BonusAdmin getAmounts error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    /**
     * Spara bonusniva-belopp i SEK (admin-skyddad)
     */
    private function setAmounts() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('Ogiltig JSON-data');
                return;
            }

            $allowed = ['brons', 'silver', 'guld', 'platina'];
            $updatedBy = substr(strip_tags((string)($_SESSION['username'] ?? 'admin')), 0, 100);

            $stmt = $this->pdo->prepare("
                INSERT INTO bonus_level_amounts (level_name, amount_sek, updated_by)
                VALUES (:level, :amount, :updated_by)
                ON DUPLICATE KEY UPDATE
                    amount_sek = :amount,
                    updated_by = :updated_by
            ");

            $saved = [];
            foreach ($allowed as $level) {
                if (!isset($input[$level])) {
                    continue;
                }
                $amount = filter_var($input[$level], FILTER_VALIDATE_INT);
                if ($amount === false || $amount < 0 || $amount > 100000) {
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
                $this->sendError('Inga giltiga belopp skickades');
                return;
            }

            $this->logAudit('set_amounts', 'bonus_level_amounts', null, null, $saved);
            $this->sendSuccess([
                'message' => 'Bonusbelopp sparade',
                'saved'   => $saved
            ]);

        } catch (PDOException $e) {
            error_log('BonusAdmin setAmounts error: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ============ HELPER FUNCTIONS ============

    private function isAdmin(): bool {
        return !empty($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    private function exportCSV(array $data, string $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Headers
        if (count($data) > 0) {
            fputcsv($output, array_keys($data[0]));
        }

        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
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
                'old_value' => $old_value ? json_encode($old_value) : null,
                'new_value' => $new_value ? json_encode($new_value) : null,
                'user' => $_SESSION['username'] ?? 'admin',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            // Don't fail the main operation if audit logging fails
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
