<?php
require_once __DIR__ . '/RebotlingAdminController.php';
require_once __DIR__ . '/RebotlingAnalyticsController.php';
require_once __DIR__ . '/VeckotrendController.php';

class RebotlingController {
    private $pdo;
    private $adminController;
    private $analyticsController;
    private $veckotrendController;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->adminController = new RebotlingAdminController($pdo);
        $this->analyticsController = new RebotlingAnalyticsController($pdo);
        $this->veckotrendController = new VeckotrendController($pdo);
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            // Skydda admin-only GET-endpoints med sessions-kontroll
            $adminOnlyActions = [
                'admin-settings', 'weekday-goals', 'shift-times', 'system-status',
                'alert-thresholds', 'today-snapshot', 'notification-settings',
                'all-lines-status', 'goal-exceptions', 'weekly-summary-email',
                'live-ranking-settings', 'live-ranking-config', 'goal-history',
                'service-status', 'plc-diagnostik',
            ];
            if (in_array($action, $adminOnlyActions, true)) {
                if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
                if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }

            // --- Admin GET endpoints ---
            if ($action === 'admin-settings') {
                $this->adminController->getAdminSettings();
            } elseif ($action === 'weekday-goals') {
                $this->adminController->getWeekdayGoals();
            } elseif ($action === 'shift-times') {
                $this->adminController->getShiftTimes();
            } elseif ($action === 'system-status') {
                $this->adminController->getSystemStatus();
            } elseif ($action === 'alert-thresholds') {
                $this->adminController->getAlertThresholds();
            } elseif ($action === 'today-snapshot') {
                $this->adminController->getTodaySnapshot();
            } elseif ($action === 'all-lines-status') {
                $this->adminController->getAllLinesStatus();
            } elseif ($action === 'notification-settings') {
                $this->adminController->getNotificationSettings();
            } elseif ($action === 'live-ranking-settings') {
                $this->adminController->getLiveRankingSettings();
            } elseif ($action === 'live-ranking-config') {
                $this->adminController->getLiveRankingConfig();
            } elseif ($action === 'goal-history') {
                $this->adminController->getGoalHistory();
            } elseif ($action === 'goal-exceptions') {
                $this->adminController->getGoalExceptions();
            } elseif ($action === 'service-status') {
                $this->adminController->getServiceStatus();

            // --- Analytics GET endpoints ---
            } elseif ($action === 'report') {
                $this->analyticsController->getProductionReport();
            } elseif ($action === 'week-comparison') {
                $this->analyticsController->getWeekComparison();
            } elseif ($action === 'oee-trend') {
                $this->analyticsController->getOEETrend();
            } elseif ($action === 'best-shifts') {
                $this->analyticsController->getBestShifts();
            } elseif ($action === 'exec-dashboard') {
                $this->analyticsController->getExecDashboard();
            } elseif ($action === 'shift-compare') {
                $this->analyticsController->getShiftCompare();
            } elseif ($action === 'cycle-histogram') {
                $this->analyticsController->getCycleHistogram();
            } elseif ($action === 'spc') {
                $this->analyticsController->getSPC();
            } elseif ($action === 'cycle-by-operator') {
                $this->analyticsController->getCycleByOperator();
            } elseif ($action === 'year-calendar') {
                $this->analyticsController->getYearCalendar();
            } elseif ($action === 'day-detail') {
                $this->analyticsController->getDayDetail();
            } elseif ($action === 'benchmarking') {
                $this->analyticsController->getBenchmarking();
            } elseif ($action === 'month-compare') {
                $this->analyticsController->getMonthCompare();
            } elseif ($action === 'monthly-report') {
                $this->analyticsController->getMonthlyReport();
            } elseif ($action === 'maintenance-indicator') {
                $this->analyticsController->getMaintenanceIndicator();
            } elseif ($action === 'annotations') {
                $this->analyticsController->getAnnotations();
            } elseif ($action === 'annotations-list') {
                $this->analyticsController->getAnnotationsList();
            } elseif ($action === 'quality-trend') {
                $this->analyticsController->getQualityTrend();
            } elseif ($action === 'oee-waterfall') {
                $this->analyticsController->getOeeWaterfall();
            } elseif ($action === 'weekday-stats') {
                $this->analyticsController->getWeekdayStats();
            } elseif ($action === 'stoppage-analysis') {
                $this->analyticsController->getStoppageAnalysis();
            } elseif ($action === 'shift-trend') {
                $this->analyticsController->getShiftTrend();
            } elseif ($action === 'pareto-stoppage') {
                $this->analyticsController->getParetoStoppage();
            } elseif ($action === 'stop-cause-drilldown') {
                $this->analyticsController->getStopCauseDrilldown();
            } elseif ($action === 'skiftrapport-list') {
                $this->analyticsController->getSkiftrapportList();
            } elseif ($action === 'skiftrapport-operators') {
                $this->analyticsController->getSkiftrapportOperators();
            } elseif ($action === 'shift-summary') {
                $this->analyticsController->getShiftSummary();
            } elseif ($action === 'maintenance-correlation') {
                $this->analyticsController->getMaintenanceCorrelation();
            } elseif ($action === 'rejection-analysis') {
                $this->analyticsController->getRejectionAnalysis();
            } elseif ($action === 'quality-rejection-breakdown') {
                $this->analyticsController->getQualityRejectionBreakdown();
            } elseif ($action === 'quality-rejection-trend') {
                $this->analyticsController->getQualityRejectionTrend();
            } elseif ($action === 'shift-pdf-summary') {
                $this->analyticsController->getShiftPdfSummary();
            } elseif ($action === 'weekly-summary-email') {
                $this->analyticsController->getWeeklySummaryEmail();
            } elseif ($action === 'realtime-oee') {
                $this->analyticsController->getRealtimeOee();
            } elseif ($action === 'production-goal-progress') {
                $this->analyticsController->getProductionGoalProgress();
            } elseif ($action === 'shift-day-night') {
                $this->analyticsController->getShiftDayNightComparison();
            } elseif ($action === 'top-operators-leaderboard') {
                $this->analyticsController->getTopOperatorsLeaderboard();
            } elseif ($action === 'machine-uptime-heatmap') {
                $this->analyticsController->getMachineUptimeHeatmap();

            // --- Live-data GET endpoints ---
            } elseif ($action === 'status') {
                $this->getRunningStatus();
            } elseif ($action === 'rast') {
                $this->getRastStatus();
            } elseif ($action === 'driftstopp') {
                $this->getDriftstoppStatus();
            } elseif ($action === 'statistics') {
                $this->getStatistics();
            } elseif ($action === 'day-stats') {
                $this->getDayStats();
            } elseif ($action === 'oee') {
                $this->getOEE();
            } elseif ($action === 'cycle-trend') {
                $this->getCycleTrend();
            } elseif ($action === 'heatmap') {
                $this->getHeatmap();
            } elseif ($action === 'live-ranking') {
                $this->getLiveRanking();
            } elseif ($action === 'skift-kommentar') {
                $this->getSkiftKommentar();
            } elseif ($action === 'events') {
                $this->getEvents();
            } elseif ($action === 'stoppage-analysis') {
                $this->analyticsController->getStoppageAnalysis();
            } elseif ($action === 'pareto-stoppage') {
                $this->analyticsController->getParetoStoppage();
            } elseif ($action === 'alert-thresholds') {
                $this->adminController->getAlertThresholds();
            } elseif ($action === 'today-snapshot') {
                $this->adminController->getTodaySnapshot();
            } elseif ($action === 'cycle-by-operator') {
                $this->analyticsController->getCycleByOperator();
            } elseif ($action === 'shift-trend') {
                $this->analyticsController->getShiftTrend();
            } elseif ($action === 'all-lines-status') {
                $this->adminController->getAllLinesStatus();
            } elseif ($action === 'notification-settings') {
                $this->adminController->getNotificationSettings();
            } elseif ($action === 'live-ranking-settings') {
                $this->getLiveRankingSettings();
            } elseif ($action === 'personal-bests') {
                $this->getPersonalBests();
            } elseif ($action === 'hall-of-fame') {
                $this->getHallOfFameDays();
            } elseif ($action === 'monthly-leaders') {
                $this->getMonthlyLeaders();
            } elseif ($action === 'attendance') {
                $this->getAttendance();
            } elseif ($action === 'hourly-rhythm') {
                $this->getHourlyRhythm();
            } elseif ($action === 'operator-weekly-trend') {
                $this->getOperatorWeeklyTrend();
            } elseif ($action === 'operator-list-trend') {
                $this->getOperatorListForTrend();
            } elseif ($action === 'kassation-pareto') {
                $this->getKassationPareto();
            } elseif ($action === 'kassation-typer') {
                $this->getKassationTyper();
            } elseif ($action === 'kassation-senaste') {
                $this->getKassationSenaste();
            } elseif ($action === 'staffing-warning') {
                $this->getStaffingWarning();
            } elseif ($action === 'monthly-stop-summary') {
                $this->getMonthlyStopSummary();
            } elseif ($action === 'oee-components') {
                $this->getOeeComponents();
            } elseif ($action === 'production-rate') {
                $this->getProductionRate();
            } elseif ($action === 'day-raw-data') {
                $this->getDayRawData();
            } elseif ($action === 'weekly-kpis') {
                $this->veckotrendController->handle();
            } elseif ($action === 'plc-diagnostik') {
                $this->getPlcDiagnostik();
            } elseif ($action === 'operator-analys') {
                $this->getOperatorAnalys();
            } elseif ($action === 'operator-scores') {
                $this->getOperatorScores();
            } elseif ($action === 'operator-matcher') {
                $this->getOperatorMatcher();
            } elseif ($action === 'shift-dna') {
                $this->getShiftDna();
            } elseif ($action === 'operator-profile') {
                $this->getOperatorProfile();
            } elseif ($action === 'operator-trend-heatmap') {
                $this->getOperatorTrendHeatmap();
            } elseif ($action === 'operator-monthly-report') {
                $this->getOperatorMonthlyReport();
            } elseif ($action === 'operator-kvartal') {
                $this->getOperatorKvartalReport();
            } elseif ($action === 'operator-performance-map') {
                $this->getOperatorPerformanceMap();
            } elseif ($action === 'operator-aktivitet') {
                $this->getOperatorAktivitet();
            } elseif ($action === 'operator-compare') {
                $this->getOperatorCompare();
            } elseif ($action === 'bonus-kalkylator') {
                $this->getBonusKalkylator();
            } elseif ($action === 'operator-inlarning') {
                $this->getOperatorInlarning();
            } elseif ($action === 'operator-varning') {
                $this->getOperatorVarning();
            } elseif ($action === 'operator-produkt') {
                $this->getOperatorProdukt();
            } elseif ($action === 'operator-stopptid') {
                $this->getOperatorStopptid();
            } elseif ($action === 'skift-kalender') {
                $this->getSkiftKalender();
            } elseif ($action === 'operator-veckodag') {
                $this->getOperatorVeckodag();
            } elseif ($action === 'operator-kassation') {
                $this->getOperatorKassation();
            } elseif ($action === 'skift-prognos') {
                $this->getSkiftPrognos();
            } elseif ($action === 'ibc-forlust') {
                $this->getIbcForlust();
            } elseif ($action === 'operator-synergy') {
                $this->getOperatorSynergy();
            } elseif ($action === 'skift-topplista') {
                $this->getSkiftTopplista();
            } elseif ($action === 'produktion-heatmap') {
                $this->getProduktionHeatmap();
            } elseif ($action === 'operator-skifttyp') {
                $this->getOperatorSkifttyp();
            } else {
                $this->getLiveStats();
            }
            return;
        }

        if ($method === 'POST') {
            if (session_status() === PHP_SESSION_NONE) session_start();
            // Kommentar-endpoint kräver enbart inloggning, inte admin
            if ($action === 'set-skift-kommentar') {
                if (!isset($_SESSION['user_id'])) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Inloggning krävs.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->setSkiftKommentar();
                return;
            }
            if ($action === 'add-event') {
                $this->addEvent();
                return;
            }
            if ($action === 'delete-event') {
                $this->deleteEvent();
                return;
            }
            // kassation-register kräver inloggning (ej admin)
            if ($action === 'kassation-register') {
                if (!isset($_SESSION['user_id'])) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Inloggning krävs.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $this->registerKassation();
                return;
            }
            if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'developer')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // --- PLC Simulering (admin/developer) ---
            if ($action === 'plc-simulate') {
                $this->plcSimulate();
                return;
            }

            // --- Admin POST endpoints ---
            if ($action === 'admin-settings') {
                $this->adminController->saveAdminSettings();
            } elseif ($action === 'weekday-goals') {
                $this->adminController->saveWeekdayGoals();
            } elseif ($action === 'shift-times') {
                $this->adminController->saveShiftTimes();
            } elseif ($action === 'save-alert-thresholds') {
                $this->adminController->saveAlertThresholds();
            } elseif ($action === 'save-notification-settings') {
                $this->adminController->saveNotificationSettings();
            } elseif ($action === 'save-live-ranking-settings') {
                $this->adminController->saveLiveRankingSettings();
            } elseif ($action === 'set-live-ranking-config') {
                $this->adminController->setLiveRankingConfig();
            } elseif ($action === 'create-record-news') {
                $this->adminController->createRecordNewsManual();
            } elseif ($action === 'save-maintenance-log') {
                $this->adminController->saveMaintenanceLog();
            } elseif ($action === 'save-goal-exception') {
                $this->adminController->saveGoalException();
            } elseif ($action === 'delete-goal-exception') {
                $this->adminController->deleteGoalException();
            } elseif ($action === 'reset-service') {
                $this->adminController->resetService();
            } elseif ($action === 'save-service-interval') {
                $this->adminController->saveServiceInterval();

            // --- Annotation POST endpoints ---
            } elseif ($action === 'annotation-create') {
                $this->analyticsController->createAnnotation();
            } elseif ($action === 'annotation-delete') {
                $this->analyticsController->deleteAnnotation();

            // --- Analytics POST endpoints ---
            } elseif ($action === 'auto-shift-report') {
                $this->analyticsController->sendAutoShiftReport();
            } elseif ($action === 'send-weekly-summary') {
                $this->analyticsController->sendWeeklySummaryEmail();
            } elseif ($action === 'set-production-goal') {
                $this->analyticsController->setProductionGoal();
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig action'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        // Om ingen matchande metod finns
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod eller action'], JSON_UNESCAPED_UNICODE);
    }


    private function ensureSettingsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_settings` (
                `id`               INT          NOT NULL DEFAULT 1,
                `rebotling_target` INT          NOT NULL DEFAULT 1000,
                `hourly_target`    INT          NOT NULL DEFAULT 50,
                `auto_start`       TINYINT(1)   NOT NULL DEFAULT 0,
                `maintenance_mode` TINYINT(1)   NOT NULL DEFAULT 0,
                `alert_threshold`  INT          NOT NULL DEFAULT 80,
                `shift_hours`      DECIMAL(4,1) NOT NULL DEFAULT 8.0,
                `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec(
            "INSERT IGNORE INTO `rebotling_settings` (id) VALUES (1)"
        );
    }


    /**
     * Hämtar cachad settings+väder-data (ändras sällan, 30s TTL).
     * Sparar 1 DB-roundtrip (~120ms) per getLiveStats-anrop.
     */
    private function getCachedSettingsAndWeather(): array {
        $cacheFile = sys_get_temp_dir() . '/mauserdb_livestats_settings.json';
        $cacheTtl = 30; // sekunder

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = [
            'rebotlingTarget' => 1000,
            'utetemperatur' => null,
        ];

        try {
            $stmt = $this->pdo->query("
                SELECT
                    (SELECT rebotling_target FROM rebotling_settings WHERE id = 1) AS settings_target,
                    (SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE() LIMIT 1) AS undantag_mal,
                    (SELECT utetemperatur FROM vader_data ORDER BY datum DESC LIMIT 1) AS utetemperatur
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($row['undantag_mal'] !== null) {
                    $result['rebotlingTarget'] = (int)$row['undantag_mal'];
                } elseif ($row['settings_target'] !== null) {
                    $result['rebotlingTarget'] = (int)$row['settings_target'];
                }
                if ($row['utetemperatur'] !== null) {
                    $result['utetemperatur'] = (float)$row['utetemperatur'];
                }
            }
        } catch (Exception $e) {
            error_log('RebotlingController::getCachedSettingsAndWeather: ' . $e->getMessage());
            try {
                $this->ensureSettingsTable();
                $sRow = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sRow) $result['rebotlingTarget'] = (int)$sRow['rebotling_target'];
            } catch (Exception $e2) {
                error_log('RebotlingController::getCachedSettingsAndWeather fallback: ' . $e2->getMessage());
            }
        }

        file_put_contents($cacheFile, json_encode($result), LOCK_EX);
        return $result;
    }

    private function getLiveStats() {
        // Filcache 5s TTL — getLiveStats anropas ofta och data ändras sällan
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
        $cacheFile = $cacheDir . '/livestats_result.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false) {
                header('X-Cache: HIT');
                echo $cached;
                return;
            }
        }

        try {
            // ULTRA MEGA-QUERY: Allt i EN ENDA roundtrip med CTE
            // Sparar 2 DB-roundtrips (~240ms) jämfört med 3 separata queries
            // FIX: Använd MAX(ibc_count) för ibc_today.
            // ibc_count är en sekventiell räknare (1,2,3...) som startar på 1 varje dag.
            // Tidigare bugg: SUM(MAX(ibc_ok)) per skifträknare gav fel (158 ist f 123)
            // eftersom ibc_ok inte nollställs korrekt vid nya skifträknare.
            $stmt = $this->pdo->prepare("
                WITH skift AS (
                    SELECT skiftraknare AS sk FROM rebotling_onoff
                    WHERE skiftraknare IS NOT NULL ORDER BY datum DESC LIMIT 1
                )
                SELECT
                    (SELECT sk FROM skift) AS current_skift,
                    (SELECT COALESCE(MAX(ibc_count), 0) FROM rebotling_ibc
                     WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY) AS ibc_today,
                    (SELECT lopnummer FROM rebotling_lopnummer_current WHERE id = 1 LIMIT 1) AS lopnummer,
                    (SELECT COUNT(*) FROM rebotling_ibc
                     WHERE skiftraknare = (SELECT sk FROM skift)
                       AND datum >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS ibc_hour,
                    (SELECT MAX(COALESCE(ibc_ok, 0)) FROM rebotling_ibc
                     WHERE skiftraknare = (SELECT sk FROM skift)) AS ibc_shift,
                    COALESCE(
                        (SELECT p.cycle_time_minutes
                         FROM rebotling_onoff o
                         LEFT JOIN rebotling_products p ON p.id = o.produkt
                         WHERE o.skiftraknare = (SELECT sk FROM skift) AND o.produkt IS NOT NULL AND o.produkt > 0
                         ORDER BY o.datum DESC LIMIT 1),
                        (SELECT p.cycle_time_minutes
                         FROM rebotling_ibc i
                         LEFT JOIN rebotling_products p ON p.id = i.produkt
                         WHERE i.skiftraknare = (SELECT sk FROM skift) AND i.produkt IS NOT NULL AND i.produkt > 0
                         ORDER BY i.datum DESC LIMIT 1)
                    ) AS cycle_time
            ");
            $stmt->execute();
            $combo = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentSkift = $combo && $combo['current_skift'] !== null ? (int)$combo['current_skift'] : null;
            $ibcToday = (int)($combo['ibc_today'] ?? 0);
            $nextLopnummer = $combo['lopnummer'] !== null ? (int)$combo['lopnummer'] : null;

            $rebotlingThisHour = (int)($combo['ibc_hour'] ?? 0);
            $ibcCurrentShift = (int)($combo['ibc_shift'] ?? 0);
            $hourlyTarget = 15; // Default
            $totalRuntimeMinutes = 0;

            $ct = (float)($combo['cycle_time'] ?? 0);
            if ($ct > 0) {
                $hourlyTarget = round(60 / $ct, 1);
            }

            // On/off-data for runtime -- behöver fortfarande PHP-loop för edge cases
            if ($currentSkift !== null) {
                $stmt = $this->pdo->prepare('
                    SELECT datum, running
                    FROM rebotling_onoff
                    WHERE skiftraknare = ?
                    ORDER BY datum ASC
                ');
                $stmt->execute([$currentSkift]);
                $skiftEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($skiftEntries) > 0) {
                    $lastRunningStart = null;
                    $tz = new DateTimeZone('Europe/Stockholm');
                    $now = new DateTime('now', $tz);

                    foreach ($skiftEntries as $entry) {
                        $entryTime = new DateTime($entry['datum'], $tz);
                        $isRunning = (bool)($entry['running'] ?? false);

                        if ($isRunning && $lastRunningStart === null) {
                            $lastRunningStart = $entryTime;
                        } elseif (!$isRunning && $lastRunningStart !== null) {
                            $diff = $lastRunningStart->diff($entryTime);
                            $totalRuntimeMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                            $lastRunningStart = null;
                        }
                    }

                    if ($lastRunningStart !== null) {
                        $lastEntryTime = new DateTime($skiftEntries[count($skiftEntries) - 1]['datum'], $tz);
                        $diff = $lastRunningStart->diff($lastEntryTime);
                        $totalRuntimeMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $diffSinceLast = $lastEntryTime->diff($now);
                        $totalRuntimeMinutes += ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                    }
                }
            }

            // Beräkna produktionsprocent — VIKTIGT: använd ibcCurrentShift (inte ibcToday)
            // för att matcha runtime som bara gäller nuvarande skift.
            // Tidigare bug: ibcToday inkluderade alla skift men runtime bara nuvarande skift
            // vilket gav felaktig (kumulativ-liknande) procent vid fleraskifts-dagar.
            $productionPercentage = 0;
            if ($totalRuntimeMinutes > 0 && $ibcCurrentShift > 0 && $hourlyTarget > 0) {
                $actualProductionPerHour = ($ibcCurrentShift * 60) / $totalRuntimeMinutes;
                $productionPercentage = round(($actualProductionPerHour / $hourlyTarget) * 100, 1);
            }

            // Settings + väder: hämta från filcache (30s TTL) — sparar 1 DB-roundtrip
            $settingsCache = $this->getCachedSettingsAndWeather();
            $rebotlingTarget = $settingsCache['rebotlingTarget'];
            $utetemperatur = $settingsCache['utetemperatur'];

            $jsonResult = json_encode([
                'success' => true,
                'data' => [
                    'rebotlingToday' => $ibcToday,
                    'rebotlingTarget' => $rebotlingTarget,
                    'rebotlingThisHour' => $rebotlingThisHour,
                    'hourlyTarget' => $hourlyTarget,
                    'ibcToday' => $ibcToday,
                    'productionPercentage' => $productionPercentage,
                    'nextLopnummer' => $nextLopnummer,
                    'utetemperatur' => $utetemperatur
                ]
            ], JSON_UNESCAPED_UNICODE);

            // Spara till filcache (5s TTL)
            $written = file_put_contents($cacheFile, $jsonResult, LOCK_EX);
            if ($written === false) {
                error_log("getLiveStats cache write FAILED: $cacheFile");
            }
            header('X-Cache: MISS');
            echo $jsonResult;

            // Auto-kontroll: skapa rekordnyhet om klockan är efter 18:00 och det finns produktion
            // Kör bara ibland (ca 1 av 10 anrop) för att inte belasta varje request
            $currentHour = (int)(new DateTime('now', new DateTimeZone('Europe/Stockholm')))->format('G');
            if ($currentHour >= 18 && $ibcToday > 0 && mt_rand(1, 10) === 1) {
                $this->checkAndCreateRecordNews();
            }

        } catch (Exception $e) {
            error_log('RebotlingController::getLiveStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    private function getRunningStatus() {
        try {
            // Hämta senaste running status för rebotling
            $stmt = $this->pdo->prepare('
                SELECT running, datum
                FROM rebotling_onoff
                ORDER BY datum DESC
                LIMIT 1
            ');
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $isRunning = $result && isset($result['running']) ? (bool)$result['running'] : false;
            $lastUpdate = $result && isset($result['datum']) ? $result['datum'] : null;

            // Hämta aktuell raststatus
            $onRast = false;
            try {
                $rastStmt = $this->pdo->query("
                    SELECT rast_status FROM rebotling_runtime
                    ORDER BY datum DESC LIMIT 1
                ");
                $rastRow = $rastStmt->fetch(PDO::FETCH_ASSOC);
                $onRast = $rastRow ? (bool)$rastRow['rast_status'] : false;
            } catch (Exception $e) {
                // Tabellen kanske inte finns ännu
                error_log('RebotlingController::getRunningStatus rast-check: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'running'    => $isRunning,
                    'on_rast'    => $onRast,
                    'lastUpdate' => $lastUpdate
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getRunningStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta status'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api.php?action=rebotling&run=rast
     *
     * Hämtar aktuell raststatus och beräknar total rasttid idag.
     * Tabellen rebotling_runtime innehåller rader med (datum, rast_status)
     * där rast_status=1 = rast börjar, rast_status=0 = rast slutar.
     */

    private function getRastStatus() {
        try {
            // Säkerställ att tabellen finns
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `rebotling_runtime` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `datum` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `rast_status` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_datum` (`datum`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Tidszon för jämförbara tider (samma som vid insättning från plc-backend)
            $tz = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);
            $todayStr = $now->format('Y-m-d');

            // Hämta dagens alla rast-events (använd samma "idag" som PHP så klockan stämmer)
            $stmt = $this->pdo->prepare("
                SELECT id, datum, rast_status
                FROM rebotling_runtime
                WHERE datum >= :today AND datum < DATE_ADD(:today2, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ");
            $stmt->execute(['today' => $todayStr, 'today2' => $todayStr]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalRastMinutes = 0;
            $rastStart = null;
            $currentlyOnRast = false;

            foreach ($events as $event) {
                if ((int)$event['rast_status'] === 1 && $rastStart === null) {
                    $rastStart = new DateTime($event['datum'], $tz);
                    $currentlyOnRast = true;
                } elseif ((int)$event['rast_status'] === 0 && $rastStart !== null) {
                    $end = new DateTime($event['datum'], $tz);
                    $diff = $rastStart->diff($end);
                    $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRastMinutes += max(0, (int)round($minutes));
                    $rastStart = null;
                    $currentlyOnRast = false;
                }
            }

            // Om rast pågår just nu, räkna in till nu – ignorera bara om rast=1 är uppenbart gammal
            if ($rastStart !== null) {
                $diff = $rastStart->diff($now);
                $minutesOpen = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $minutesOpen = max(0, (int)round($minutesOpen)); // undvik negativ tid vid klockavvikelse
                if ($minutesOpen <= 480) { // 8 timmar – endast ignorerar kvarvarande från föregående skift/dag
                    $currentlyOnRast = true;
                    $totalRastMinutes += $minutesOpen;
                } else {
                    $currentlyOnRast = false;
                }
            }

            // Hämta senaste event för tidsstämpel
            $latestEvent = !empty($events) ? end($events) : null;

            echo json_encode([
                'success' => true,
                'data' => [
                    'on_rast'           => $currentlyOnRast,
                    'rast_minutes_today' => round($totalRastMinutes, 1),
                    'rast_count_today'   => count(array_filter($events, fn($e) => (int)$e['rast_status'] === 1)),
                    'last_event'         => $latestEvent ? $latestEvent['datum'] : null,
                    'events'             => array_map(fn($e) => [
                        'datum'       => $e['datum'],
                        'rast_status' => (int)$e['rast_status']
                    ], $events)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getRastStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta raststatus'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api.php?action=rebotling&run=driftstopp
     *
     * Hämtar aktuell driftstoppstatus och beräknar total driftstopptid idag.
     * Speglar getRastStatus() men läser från rebotling_driftstopp.
     * Driftstopptid ska INTE räknas som produktionstid.
     */
    private function getDriftstoppStatus() {
        try {
            // Kontrollera att tabellen finns (snabb information_schema-check istället för CREATE TABLE IF NOT EXISTS varje gång)
            static $driftstoppTableChecked = false;
            if (!$driftstoppTableChecked) {
                $check = $this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name = 'rebotling_driftstopp'"
                )->fetchColumn();
                if (!$check) {
                    $this->pdo->exec("
                        CREATE TABLE IF NOT EXISTS `rebotling_driftstopp` (
                            `id` INT NOT NULL AUTO_INCREMENT,
                            `datum` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `driftstopp_status` TINYINT(1) NOT NULL DEFAULT 0,
                            `skiftraknare` INT DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            KEY `idx_datum` (`datum`),
                            KEY `idx_skiftraknare` (`skiftraknare`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                }
                $driftstoppTableChecked = true;
            }

            $tz = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);
            $todayStr = $now->format('Y-m-d');

            $stmt = $this->pdo->prepare("
                SELECT id, datum, driftstopp_status, skiftraknare
                FROM rebotling_driftstopp
                WHERE datum >= :today AND datum < DATE_ADD(:today2, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ");
            $stmt->execute(['today' => $todayStr, 'today2' => $todayStr]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalMinutes = 0;
            $stoppStart = null;
            $currentlyOnStopp = false;

            foreach ($events as $event) {
                if ((int)$event['driftstopp_status'] === 1 && $stoppStart === null) {
                    $stoppStart = new DateTime($event['datum'], $tz);
                    $currentlyOnStopp = true;
                } elseif ((int)$event['driftstopp_status'] === 0 && $stoppStart !== null) {
                    $end = new DateTime($event['datum'], $tz);
                    $diff = $stoppStart->diff($end);
                    $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalMinutes += max(0, (int)round($minutes));
                    $stoppStart = null;
                    $currentlyOnStopp = false;
                }
            }

            if ($stoppStart !== null) {
                $diff = $stoppStart->diff($now);
                $minutesOpen = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                $minutesOpen = max(0, (int)round($minutesOpen));
                if ($minutesOpen <= 480) {
                    $currentlyOnStopp = true;
                    $totalMinutes += $minutesOpen;
                } else {
                    $currentlyOnStopp = false;
                }
            }

            $latestEvent = !empty($events) ? end($events) : null;

            echo json_encode([
                'success' => true,
                'data' => [
                    'on_driftstopp'            => $currentlyOnStopp,
                    'driftstopp_minutes_today'  => round($totalMinutes, 1),
                    'driftstopp_count_today'    => count(array_filter($events, fn($e) => (int)$e['driftstopp_status'] === 1)),
                    'last_event'               => $latestEvent ? $latestEvent['datum'] : null,
                    'events'                   => array_map(fn($e) => [
                        'datum'              => $e['datum'],
                        'driftstopp_status'  => (int)$e['driftstopp_status']
                    ], $events)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getDriftstoppStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta driftstoppstatus'], JSON_UNESCAPED_UNICODE);
        }
    }


    private function getStatistics() {
        try {
            $start = trim($_GET['start'] ?? date('Y-m-d'));
            $end = trim($_GET['end'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = date('Y-m-d');
            // Validera att start <= end
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            // Hämta cykler för perioden med FAKTISK beräknad cykeltid och target från produkt.
            // OBS: Joina direkt på i.produkt → rebotling_products för att undvika
            // många-till-många-duplikat via rebotling_onoff (ett skift har många onoff-rader).
            $stmt = $this->pdo->prepare('
                SELECT
                    i.datum,
                    i.ibc_count,
                    i.produktion_procent,
                    i.skiftraknare,
                    i.produkt as produkt_id,
                    COALESCE(p.name, CONCAT("Produkt ", i.produkt)) as produkt_namn,
                    ROUND(TIMESTAMPDIFF(SECOND,
                        LAG(i.datum) OVER (PARTITION BY i.skiftraknare ORDER BY i.datum),
                        i.datum
                    ) / 60.0, 2) as cycle_time,
                    p.cycle_time_minutes as target_cycle_time
                FROM rebotling_ibc i
                LEFT JOIN rebotling_products p ON i.produkt = p.id
                WHERE i.datum >= :start AND i.datum < DATE_ADD(:end, INTERVAL 1 DAY)
                ORDER BY i.datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $rawCycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Behåll ALLA cykler för korrekt antal.
            // Cykeltid sätts till NULL om den är orimlig (stopp-gap eller PLC-dubblett).
            // Tröskel: target * 3 (t.ex. 9 min för 3-min-produkt) — stopp-gap EXKLUDERAS
            // ur effektivitetsberäkning precis som i skiftrapporten (drifttid exkl. stopp).
            $cycles = [];
            foreach ($rawCycles as $cycle) {
                $ct = $cycle['cycle_time'] !== null ? (float)$cycle['cycle_time'] : null;
                if ($ct !== null) {
                    $target = (float)($cycle['target_cycle_time'] ?? 0);
                    $maxCt  = $target > 0 ? $target * 3 : 15.0;
                    if ($ct > $maxCt) {
                        // Driftstopp-gap — behåll cykeln men nollställ cykeltiden
                        $cycle['cycle_time'] = null;
                    } elseif ($ct < 0.5) {
                        // PLC-dubblettrigger (< 30 sek) — nollställ cykeltiden
                        $cycle['cycle_time'] = null;
                    }
                }
                $cycles[] = $cycle;
            }

            // Hämta on/off events för perioden
            // Använd datum >= / < istället för DATE(datum) BETWEEN för index-användning
            $stmt = $this->pdo->prepare('
                SELECT
                    datum,
                    running,
                    runtime_today
                FROM rebotling_onoff
                WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $onoff_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta rast events för perioden
            $rast_events = [];
            $totalRastMinutes = 0;
            try {
                $rastStmt = $this->pdo->prepare(
                    'SELECT datum, rast_status FROM rebotling_runtime
                     WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                     ORDER BY datum ASC'
                );
                $rastStmt->execute(['start' => $start, 'end' => $end]);
                $rast_events = $rastStmt->fetchAll(PDO::FETCH_ASSOC);

                // Beräkna total rasttid
                $rs = null;
                foreach ($rast_events as $ev) {
                    if ((int)$ev['rast_status'] === 1) {
                        $rs = new DateTime($ev['datum'], new DateTimeZone('Europe/Stockholm'));
                    } elseif ((int)$ev['rast_status'] === 0 && $rs !== null) {
                        $d = $rs->diff(new DateTime($ev['datum'], new DateTimeZone('Europe/Stockholm')));
                        $totalRastMinutes += ($d->days * 1440) + ($d->h * 60) + $d->i + ($d->s / 60);
                        $rs = null;
                    }
                }
            } catch (Exception $e) {
                error_log('RebotlingController rast-events query: ' . $e->getMessage());
            }

            // Hämta driftstopp events för perioden
            $driftstopp_events = [];
            $totalDriftstoppMinutes = 0;
            try {
                $dsStmt = $this->pdo->prepare(
                    'SELECT datum, driftstopp_status FROM rebotling_driftstopp
                     WHERE datum >= :start AND datum < DATE_ADD(:end, INTERVAL 1 DAY)
                     ORDER BY datum ASC'
                );
                $dsStmt->execute(['start' => $start, 'end' => $end]);
                $driftstopp_events = $dsStmt->fetchAll(PDO::FETCH_ASSOC);

                // Beräkna total driftstopptid
                $ds = null;
                foreach ($driftstopp_events as $ev) {
                    if ((int)$ev['driftstopp_status'] === 1) {
                        $ds = new DateTime($ev['datum'], new DateTimeZone('Europe/Stockholm'));
                    } elseif ((int)$ev['driftstopp_status'] === 0 && $ds !== null) {
                        $d = $ds->diff(new DateTime($ev['datum'], new DateTimeZone('Europe/Stockholm')));
                        $totalDriftstoppMinutes += ($d->days * 1440) + ($d->h * 60) + $d->i + ($d->s / 60);
                        $ds = null;
                    }
                }
            } catch (Exception $e) {
                error_log('RebotlingController driftstopp-events query: ' . $e->getMessage());
            }

            // Beräkna sammanfattning
            $total_cycles = count($cycles);
            $avg_production_percent = 0;
            $avg_cycle_time = 0;
            $total_runtime_hours = 0;
            $target_cycle_time = 0;
            
            if ($total_cycles > 0) {
                // produktion_procent ar en momentan takt-procent fran PLC-backend:
                // (faktisk_per_timme / mal_per_timme) * 100.
                // Tidiga cykler i ett skift kan ge varden >100% pga kort runtime.
                // Filtrera bort orimliga varden (>200%) och anvand ravaarden direkt.
                $validPercents = [];
                foreach ($cycles as $c) {
                    $pct = (float)($c['produktion_procent'] ?? 0);
                    // Filtrera bort nollor och orimligt hoga varden (ramp-up-artefakter)
                    if ($pct > 0 && $pct <= 200) {
                        $validPercents[] = min($pct, 100);
                    }
                }
                $avg_production_percent = count($validPercents) > 0
                    ? array_sum($validPercents) / count($validPercents)
                    : 0;
                
                // Beräkna genomsnittlig cykeltid
                $cycle_times = array_filter(array_column($cycles, 'cycle_time'), function($val) {
                    return $val !== null && $val > 0;
                });
                
                if (count($cycle_times) > 0) {
                    $avg_cycle_time = array_sum($cycle_times) / count($cycle_times);
                }
                
                // Hämta mål cykeltid från produkten (ta första icke-null värdet)
                $target_values = array_filter(array_column($cycles, 'target_cycle_time'), function($val) {
                    return $val !== null && $val > 0;
                });
                
                if (count($target_values) > 0) {
                    // Ta medelvärdet av alla målvärden (kan variera om olika produkter används)
                    $target_cycle_time = array_sum($target_values) / count($target_values);
                }
            }

            // Beräkna total runtime från on/off events
            $totalRuntimeMinutes = 0;
            
            if (count($onoff_events) > 0) {
                $lastRunningStart = null;
                
                foreach ($onoff_events as $event) {
                    $eventTime = new DateTime($event['datum'], new DateTimeZone('Europe/Stockholm'));
                    $isRunning = (bool)($event['running'] ?? false);
                    
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $eventTime;
                    } elseif (!$isRunning && $lastRunningStart !== null) {
                        $diff = $lastRunningStart->diff($eventTime);
                        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $totalRuntimeMinutes += $periodMinutes;
                        $lastRunningStart = null;
                    }
                }
                
                // Om maskinen fortfarande kör vid slutet av perioden
                if ($lastRunningStart !== null) {
                    $lastEventTime = new DateTime($onoff_events[count($onoff_events) - 1]['datum'], new DateTimeZone('Europe/Stockholm'));
                    $diff = $lastRunningStart->diff($lastEventTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                }
            }
            
            // Alternativ beräkning: Om vi inte fick runtime från events men har cykler,
            // uppskatta runtime från första till sista cykeln
            if ((float)$totalRuntimeMinutes < 0.001 && $total_cycles > 0) {
                $firstCycle = new DateTime($cycles[0]['datum'], new DateTimeZone('Europe/Stockholm'));
                $lastCycle = new DateTime($cycles[count($cycles) - 1]['datum'], new DateTimeZone('Europe/Stockholm'));
                $diff = $firstCycle->diff($lastCycle);
                $totalRuntimeMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
            }
            
            $total_runtime_hours = $totalRuntimeMinutes / 60;

            // Netto-drifttid: total on-tid minus rast och driftstopp
            // = tid linjen faktiskt producerade (matchar PLC:ns drifttid-räknare)
            $netRuntimeMinutes = max(0.0, $totalRuntimeMinutes - $totalRastMinutes - $totalDriftstoppMinutes);

            // Räkna dagar med produktion
            $unique_dates = array_unique(array_map(function($cycle) {
                return date('Y-m-d', strtotime($cycle['datum']));
            }, $cycles));
            $days_with_production = count($unique_dates);

            echo json_encode([
                'success' => true,
                'data' => [
                    'cycles' => $cycles,
                    'onoff_events' => $onoff_events,
                    'rast_events' => $rast_events,
                    'driftstopp_events' => $driftstopp_events,
                    'summary' => [
                        'total_cycles' => $total_cycles,
                        'avg_production_percent' => round($avg_production_percent, 1),
                        'avg_cycle_time' => round($avg_cycle_time, 1),
                        'target_cycle_time' => round($target_cycle_time, 1),
                        'total_runtime_hours' => round($total_runtime_hours, 1),
                        'days_with_production' => $days_with_production,
                        'total_rast_minutes' => round($totalRastMinutes, 1),
                        'total_driftstopp_minutes' => round($totalDriftstoppMinutes, 1),
                        'net_runtime_minutes' => round($netRuntimeMinutes, 1)
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getStatistics: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    private function getDayStats() {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            // Hämta detaljerad statistik för en specifik dag
            $stmt = $this->pdo->prepare('
                SELECT 
                    DATE_FORMAT(datum, "%H:%i") as time,
                    ibc_count,
                    produktion_procent,
                    skiftraknare
                FROM rebotling_ibc
                WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ');
            $stmt->execute(['date' => $date, 'dateb' => $date]);
            $hourly_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // produktion_procent ar en momentan takt-procent (faktisk/mal * 100).
            // Tidiga cykler i ett skift kan ge >100% pga kort runtime — cap till 100.
            // Orimligt hoga varden (>200%) ar ramp-up-artefakter — satt till 0.
            $hourly_data = [];
            foreach ($hourly_data_raw as $row) {
                $pct = (float)($row['produktion_procent'] ?? 0);
                if ($pct > 200) {
                    $row['produktion_procent'] = 0;
                } elseif ($pct > 100) {
                    $row['produktion_procent'] = 100;
                }
                $hourly_data[] = $row;
            }

            // Hämta on/off events för dagen
            $stmt = $this->pdo->prepare('
                SELECT
                    DATE_FORMAT(datum, "%H:%i") as time,
                    running
                FROM rebotling_onoff
                WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ');
            $stmt->execute(['date' => $date, 'dateb' => $date]);
            $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta rast-events för dagen (från Shelly-puck / PLC)
            $rast_data = [];
            $rast_total_min = 0;
            try {
                $rastStmt = $this->pdo->prepare('
                    SELECT
                        DATE_FORMAT(datum, "%H:%i") as time,
                        datum as datum_full,
                        rast_status
                    FROM rebotling_runtime
                    WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                    ORDER BY datum ASC
                ');
                $rastStmt->execute(['date' => $date, 'dateb' => $date]);
                $rast_events = $rastStmt->fetchAll(PDO::FETCH_ASSOC);

                // Beräkna total rasttid för dagen
                $rastStart = null;
                foreach ($rast_events as $ev) {
                    if ((int)$ev['rast_status'] === 1 && $rastStart === null) {
                        $rastStart = new DateTime($ev['datum_full'], new DateTimeZone('Europe/Stockholm'));
                    } elseif ((int)$ev['rast_status'] === 0 && $rastStart !== null) {
                        $end = new DateTime($ev['datum_full'], new DateTimeZone('Europe/Stockholm'));
                        $diff = $rastStart->diff($end);
                        $rast_total_min += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $rastStart = null;
                    }
                }
                $rast_data = array_map(fn($e) => ['time' => $e['time'], 'rast_status' => (int)$e['rast_status']], $rast_events);
            } catch (Exception $e) {
                error_log('RebotlingController rast-data query: ' . $e->getMessage());
            }

            // Hämta totalt rasttime från PLC (D4008) för dagen
            $plc_rast_min = 0;
            try {
                $plcRastStmt = $this->pdo->prepare('
                    SELECT MAX(COALESCE(rasttime, 0)) as total_rast_plc,
                           MAX(COALESCE(runtime_plc, 0)) as total_runtime_plc
                    FROM rebotling_ibc
                    WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                ');
                $plcRastStmt->execute(['date' => $date, 'dateb' => $date]);
                $plcRast = $plcRastStmt->fetch(PDO::FETCH_ASSOC);
                $plc_rast_min = round($plcRast['total_rast_plc'] ?? 0, 1);
                $plc_runtime_min = round($plcRast['total_runtime_plc'] ?? 0, 1);
            } catch (Exception $e) {
                error_log('RebotlingController plc-rast query: ' . $e->getMessage());
                $plc_rast_min = 0;
                $plc_runtime_min = 0;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'hourly_data' => $hourly_data,
                    'status_data' => $status_data,
                    'rast_events' => $rast_data,
                    'rast_summary' => [
                        'total_rast_min_shelly' => round($rast_total_min, 1),
                        'total_rast_min_plc'    => $plc_rast_min,
                        'total_runtime_min_plc' => $plc_runtime_min
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getDayStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta dagsstatistik'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=day-raw-data&date=YYYY-MM-DD
     *
     * Returnerar rådata för ett specifikt datum:
     *   - on/off events (rebotling_onoff)
     *   - rast events (rebotling_runtime)
     *   - driftstopp events (rebotling_driftstopp)
     *   - skiftrapportdata (rebotling_ibc aggregerad per skifträknare)
     */
    private function getDayRawData() {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            // 1. On/Off events
            $stmt = $this->pdo->prepare('
                SELECT datum, running, skiftraknare
                FROM rebotling_onoff
                WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                ORDER BY datum ASC
            ');
            $stmt->execute(['date' => $date, 'dateb' => $date]);
            $onoff_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Rast events
            $rast_events = [];
            try {
                $stmt = $this->pdo->prepare('
                    SELECT datum, rast_status
                    FROM rebotling_runtime
                    WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                    ORDER BY datum ASC
                ');
                $stmt->execute(['date' => $date, 'dateb' => $date]);
                $rast_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('getDayRawData rast: ' . $e->getMessage());
            }

            // 3. Driftstopp events
            $driftstopp_events = [];
            try {
                $check = $this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name = 'rebotling_driftstopp'"
                )->fetchColumn();
                if ($check) {
                    $stmt = $this->pdo->prepare('
                        SELECT datum, driftstopp_status, skiftraknare
                        FROM rebotling_driftstopp
                        WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                        ORDER BY datum ASC
                    ');
                    $stmt->execute(['date' => $date, 'dateb' => $date]);
                    $driftstopp_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log('getDayRawData driftstopp: ' . $e->getMessage());
            }

            // 4. Skiftrapport data (IBC per skifträknare)
            $stmt = $this->pdo->prepare('
                SELECT
                    skiftraknare,
                    MIN(datum) AS first_datum,
                    MAX(datum) AS last_datum,
                    MAX(ibc_ok) AS ibc_ok,
                    MAX(ibc_ej_ok) AS ibc_ej_ok,
                    MAX(bur_ej_ok) AS bur_ej_ok,
                    MAX(ibc_count) AS ibc_count,
                    MAX(runtime_plc) AS runtime_plc,
                    MAX(rasttime) AS rasttime,
                    MAX(produktion_procent) AS produktion_procent,
                    MAX(effektivitet) AS effektivitet,
                    MAX(produkt) AS produkt
                FROM rebotling_ibc
                WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
                GROUP BY skiftraknare
                ORDER BY skiftraknare ASC
            ');
            $stmt->execute(['date' => $date, 'dateb' => $date]);
            $skiftrapport_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'onoff_events' => array_map(fn($e) => [
                        'datum' => $e['datum'],
                        'running' => (int)$e['running'],
                        'skiftraknare' => $e['skiftraknare'] !== null ? (int)$e['skiftraknare'] : null
                    ], $onoff_events),
                    'rast_events' => array_map(fn($e) => [
                        'datum' => $e['datum'],
                        'rast_status' => (int)$e['rast_status']
                    ], $rast_events),
                    'driftstopp_events' => array_map(fn($e) => [
                        'datum' => $e['datum'],
                        'driftstopp_status' => (int)$e['driftstopp_status'],
                        'skiftraknare' => $e['skiftraknare'] !== null ? (int)$e['skiftraknare'] : null
                    ], $driftstopp_events),
                    'skiftrapport_data' => $skiftrapport_data
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getDayRawData: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta rådata för dagen'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Beräkna OEE (Overall Equipment Effectiveness) för rebotling-linjen
     * OEE = Availability × Performance × Quality
     *
     * Availability = Operating Time / Planned Production Time
     * Performance = (Total IBC / Operating Time) / Ideal Rate
     * Quality = Good IBC / Total IBC
     *
     * OBS: ibc_ok, runtime_plc m.fl. är kumulativa PLC-värden per skift.
     * Korrekt aggregering: MAX() per skiftraknare, sedan SUM() över skift.
     */

    private function getOEE() {
        $period = trim($_GET['period'] ?? 'today');
        // Whitelist-validering av period
        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'today';
        }

        // Notera: alias "r" används i dateFilter och måste matcha yttre queryn
        $dateFilter = match($period) {
            'today' => "r.datum >= CURDATE() AND r.datum < CURDATE() + INTERVAL 1 DAY",
            'week'  => "r.datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "r.datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "r.datum >= CURDATE() AND r.datum < CURDATE() + INTERVAL 1 DAY"
        };

        try {
            // Steg 1: per skiftraknare MAX (kumulativa värden)
            // Steg 2: summera korrekt över skift
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_cycles,
                    SUM(shift_ibc_ok)    as total_ibc_ok,
                    SUM(shift_ibc_ej_ok) as total_ibc_ej_ok,
                    SUM(shift_bur_ej_ok) as total_bur_ej_ok,
                    SUM(shift_runtime)   as total_runtime_min,
                    SUM(shift_rast)      as total_rast_min
                FROM (
                    SELECT
                        skiftraknare,
                        COUNT(*)              AS total_cycles,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok,  0)) AS shift_bur_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc r
                    WHERE $dateFilter
                      AND ibc_ok IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Rå cykelräkning (ej aggregerad per skift)
            $stmtCycles = $this->pdo->prepare("SELECT COUNT(*) FROM rebotling_ibc r WHERE $dateFilter");
            $stmtCycles->execute();
            $rawCycles = (int)$stmtCycles->fetchColumn();

            $totalIBC = ($data['total_ibc_ok'] ?? 0) + ($data['total_ibc_ej_ok'] ?? 0);
            $goodIBC = $data['total_ibc_ok'] ?? 0;
            $runtimeMin = $data['total_runtime_min'] ?? 0;  // Ren produktionstid (exkl. rast)
            $rastMin = $data['total_rast_min'] ?? 0;        // Rasttid från PLC D4008

            // runtime_plc exkluderar redan rast – det är den faktiska driftstiden
            $operatingMin = max($runtimeMin, 1);

            // Planerad tid = driftstid + rasttid (total tid operatörerna var på plats)
            $plannedMin = max($runtimeMin + $rastMin, 1);

            // Ideal rate: 15 IBC/timme (snitt av alla produkter)
            $idealRatePerMin = 15.0 / 60.0;

            // Availability = Operating Time / Planned Time
            $availability = min($operatingMin / $plannedMin, 1.0);

            // Performance = Actual Rate / Ideal Rate
            $actualRate = $totalIBC / max($operatingMin, 1);
            $performance = min($actualRate / $idealRatePerMin, 1.0);

            // Quality = Good Count / Total Count
            $quality = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;

            // OEE = A × P × Q
            $oee = $availability * $performance * $quality;

            echo json_encode([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'oee' => round($oee * 100, 1),
                    'availability' => round($availability * 100, 1),
                    'performance' => round($performance * 100, 1),
                    'quality' => round($quality * 100, 1),
                    'total_ibc' => $totalIBC,
                    'good_ibc' => $goodIBC,
                    'rejected_ibc' => $data['total_ibc_ej_ok'] ?? 0,
                    'runtime_hours' => round($runtimeMin / 60, 1),
                    'rast_hours' => round($rastMin / 60, 1),
                    'operating_hours' => round($operatingMin / 60, 1),
                    'planned_hours' => round($plannedMin / 60, 1),
                    'cycles' => $rawCycles,
                    'world_class_benchmark' => 85.0
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::calcOee: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte beräkna OEE'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Cykeltids-trendanalys per dag (senaste 30 dagarna).
     * Returnerar snitt cykeltid, antal cykler, och trendindikator.
     */

    private function getCycleTrend() {
        try {
            $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;
            $toDate   = isset($_GET['to_date'])   ? trim($_GET['to_date'])   : null;
            if ($fromDate && $toDate) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                // Validera att from <= to
                if ($fromDate > $toDate) {
                    [$fromDate, $toDate] = [$toDate, $fromDate];
                }
                $cycleStart = $fromDate;
                $cycleEnd   = $toDate;
            } else {
                $days = min(365, max(7, intval($_GET['days'] ?? 30)));
                $cycleStart = date('Y-m-d', strtotime("-{$days} days"));
                $cycleEnd   = date('Y-m-d');
            }
            $granularity = $_GET['granularity'] ?? 'day';
            if (!in_array($granularity, ['day', 'shift'], true)) {
                $granularity = 'day';
            }

            if ($granularity === 'shift') {
                // Per-skift granularitet: varje skift är en datapunkt
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)          AS dag,
                        skiftraknare,
                        MIN(datum)           AS skift_start,
                        COUNT(*)             AS cykler,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok,  0)) AS shift_bur_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE datum >= :cycle_start AND datum <= :cycle_end
                    GROUP BY DATE(datum), skiftraknare
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt->execute(['cycle_start' => $cycleStart . ' 00:00:00', 'cycle_end' => $cycleEnd . ' 23:59:59']);
                $shiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $shiftData = [];
                foreach ($shiftRows as $r) {
                    $dagParts = explode('-', $r['dag']);
                    $label = sprintf('%s/%s Skift %d',
                        $dagParts[2], $dagParts[1], (int)$r['skiftraknare']
                    );
                    $runtime = (float)$r['shift_runtime'];
                    $ibcPerHour = $runtime > 0 ? round((float)$r['shift_ibc_ok'] * 60.0 / $runtime, 1) : 0.0;
                    $shiftData[] = [
                        'dag'             => $r['dag'],
                        'label'           => $label,
                        'skiftraknare'    => (int)$r['skiftraknare'],
                        'cycles'          => (int)$r['cykler'],
                        'avg_runtime'     => $runtime,
                        'avg_ibc_per_hour'=> $ibcPerHour,
                        'total_ibc_ok'    => (int)$r['shift_ibc_ok'],
                        'total_ibc_ej_ok' => (int)$r['shift_ibc_ej_ok'],
                        'total_bur_ej_ok' => (int)$r['shift_bur_ej_ok']
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'granularity' => 'shift',
                    'data' => [
                        'daily'          => $shiftData,
                        'moving_average' => [],
                        'trend'          => 'stable',
                        'avg_runtime'    => 0,
                        'total_cycles'   => array_sum(array_column($shiftData, 'cycles')),
                        'alert'          => null
                    ]
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Daglig statistik. ibc_ok/ej_ok/bur_ej_ok är kumulativa PLC-värden per skift –
            // aggregera korrekt med per-skift-subquery (MAX per skiftraknare → SUM per dag).
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(cykler)          AS cycles,
                    ROUND(AVG(avg_runtime), 1) AS avg_runtime,
                    ROUND(SUM(shift_ibc_ok) * 60.0 / NULLIF(SUM(shift_runtime), 0), 1) AS avg_ibc_per_hour,
                    SUM(shift_ibc_ok)    AS total_ibc_ok,
                    SUM(shift_ibc_ej_ok) AS total_ibc_ej_ok,
                    SUM(shift_bur_ej_ok) AS total_bur_ej_ok
                FROM (
                    SELECT
                        DATE(datum)          AS dag,
                        skiftraknare,
                        COUNT(*)             AS cykler,
                        AVG(runtime_plc)     AS avg_runtime,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok,  0)) AS shift_bur_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE datum >= :cycle_start AND datum <= :cycle_end
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag
            ");
            $stmt->execute(['cycle_start' => $cycleStart . ' 00:00:00', 'cycle_end' => $cycleEnd . ' 23:59:59']);
            $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate 7-day moving average and trend
            $movingAvg = [];
            $trend = 'stable';
            if (count($daily) >= 7) {
                for ($i = 6; $i < count($daily); $i++) {
                    $window = array_slice($daily, $i - 6, 7);
                    $avg = array_sum(array_column($window, 'avg_runtime')) / 7;
                    $movingAvg[] = [
                        'dag' => $daily[$i]['dag'],
                        'moving_avg' => round($avg, 1)
                    ];
                }

                // Detect trend: compare last 7 days avg vs previous 7 days avg
                if (count($movingAvg) >= 2) {
                    $recent = end($movingAvg)['moving_avg'];
                    $older = $movingAvg[max(0, count($movingAvg) - 8)]['moving_avg'];
                    $changePct = $older > 0 ? (($recent - $older) / $older) * 100 : 0;

                    if ($changePct > 5) {
                        $trend = 'increasing'; // cycle time going up = degradation
                    } elseif ($changePct < -5) {
                        $trend = 'decreasing'; // cycle time going down = improvement
                    }
                }
            }

            // Overall stats
            $totalCycles = array_sum(array_column($daily, 'cycles'));
            $avgRuntime = $totalCycles > 0
                ? round(array_sum(array_map(fn($d) => $d['avg_runtime'] * $d['cycles'], $daily)) / $totalCycles, 1)
                : 0;

            // Alert if cycle time is trending up significantly
            $alert = null;
            if ($trend === 'increasing' && count($movingAvg) >= 2) {
                $recent = end($movingAvg)['moving_avg'];
                $older = $movingAvg[max(0, count($movingAvg) - 8)]['moving_avg'];
                $alert = [
                    'type' => 'cycle_time_increase',
                    'message' => 'Cykeltiden ökar - kontrollera utrustningen',
                    'change_pct' => $older > 0 ? round((($recent - $older) / $older) * 100, 1) : 0.0,
                    'current_avg' => $recent,
                    'previous_avg' => $older
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'daily' => $daily,
                    'moving_average' => $movingAvg,
                    'trend' => $trend,
                    'avg_runtime' => $avgRuntime,
                    'total_cycles' => $totalCycles,
                    'alert' => $alert
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getCycleTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta trenddata'], JSON_UNESCAPED_UNICODE);
        }
    }


    private function getHeatmap() {
        $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;
        $toDate   = isset($_GET['to_date'])   ? trim($_GET['to_date'])   : null;

        if ($fromDate && $toDate) {
            // Validera datumformat
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                return;
            }
            // Validera att from <= to
            if ($fromDate > $toDate) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
            // Begränsa till max 365 dagar
            try {
                $startDt = new DateTime($fromDate, new DateTimeZone('Europe/Stockholm'));
                $endDt   = new DateTime($toDate, new DateTimeZone('Europe/Stockholm'));
            } catch (Exception $e) {
                error_log('RebotlingController::getPeriodicData — ogiltigt datumvärde: ' . $e->getMessage());
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumvärde'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $diffDays = (int)$startDt->diff($endDt)->days;
            if ($diffDays > 365) {
                $fromDate = (clone $endDt)->modify('-365 days')->format('Y-m-d');
            }
            $start = $fromDate;
            $end   = $toDate;
        } else {
            $days  = isset($_GET['days']) ? max(7, min(365, intval($_GET['days']))) : 30;
            $end   = date('Y-m-d');
            $start = date('Y-m-d', strtotime("-{$days} days"));
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT DATE(datum) AS date, HOUR(datum) AS hour, COUNT(*) AS count
                 FROM rebotling_ibc
                 WHERE datum >= :start AND datum <= :end
                 GROUP BY DATE(datum), HOUR(datum)
                 ORDER BY date ASC, hour ASC'
            );
            $stmt->execute([
                'start' => $start . ' 00:00:00',
                'end'   => $end   . ' 23:59:59'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = array_map(function($r) {
                return ['date' => $r['date'], 'hour' => (int)$r['hour'], 'count' => (int)$r['count']];
            }, $rows);

            echo json_encode(['success' => true, 'data' => $data, 'start' => $start, 'end' => $end], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getHeatmap: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta heatmap-data'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Veckodagsmål
    // =========================================================


    private function getLiveRanking() {
        try {
            // Dagsmål från settings
            $dailyGoal = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dailyGoal = (int)$sr['rebotling_target'];
            } catch (Exception $e) { error_log('RebotlingController::getLiveRanking dagsMal: ' . $e->getMessage()); }

            $today = date('Y-m-d');

            // Försök hämta data för idag. Om inga skiftrapporter finns idag — fall tillbaka på senaste 7 dagarna.
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM rebotling_skiftrapport WHERE datum = :today
            ");
            $stmt->execute(['today' => $today]);
            $countToday = (int)$stmt->fetchColumn();

            if ($countToday > 0) {
                $dateFilter  = "s.datum = :dateFrom";
                $dateParam   = ['dateFrom' => $today];
                $periodLabel = $today;
                $isHistorik  = false;
            } else {
                $dateFilter  = "s.datum >= :dateFrom";
                $dateParam   = ['dateFrom' => date('Y-m-d', strtotime('-7 days'))];
                $periodLabel = 'Historik — senaste 7 dagarna (inga skiftrapporter idag)';
                $isHistorik  = true;
            }

            // Aggregera per operatör (op1/op2/op3 lagras som operator-nummer)
            // Varje skiftrapport kan ha upp till 3 operatörer.
            // Vi slår ihop dem via UNION och aggregerar sedan.
            $sql = "
                SELECT
                    sub.op_num                                        AS op_number,
                    COALESCE(o.name, CONCAT('Operatör ', sub.op_num)) AS name,
                    SUM(sub.ibc_ok)   AS ibc_ok,
                    SUM(sub.totalt)   AS totalt,
                    SUM(sub.drifttid) AS drifttid,
                    COUNT(sub.skift_id) AS shifts_count
                FROM (
                    SELECT s.id AS skift_id, s.op1 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE {$dateFilter} AND s.op1 IS NOT NULL AND s.op1 > 0
                    UNION ALL
                    SELECT s.id AS skift_id, s.op2 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE {$dateFilter} AND s.op2 IS NOT NULL AND s.op2 > 0
                    UNION ALL
                    SELECT s.id AS skift_id, s.op3 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE {$dateFilter} AND s.op3 IS NOT NULL AND s.op3 > 0
                ) sub
                LEFT JOIN operators o ON o.number = sub.op_num
                GROUP BY sub.op_num, o.name
                ORDER BY (SUM(sub.ibc_ok) / GREATEST(SUM(sub.drifttid)/60, 0.01)) DESC
                LIMIT 10
            ";

            // PDO named placeholders kan inte upprepas — bygg om med positional
            $sqlPos = str_replace(':dateFrom', '?', $sql);
            $stmt2  = $this->pdo->prepare($sqlPos);
            $d      = $dateParam['dateFrom'];
            $stmt2->execute([$d, $d, $d]);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $ranking = [];
            foreach ($rows as $row) {
                $ibc_ok   = (int)($row['ibc_ok']   ?? 0);
                $totalt   = (int)($row['totalt']    ?? 0);
                $drifttid = (int)($row['drifttid']  ?? 0); // i minuter
                $ibc_per_hour = $drifttid > 0
                    ? round($ibc_ok / ($drifttid / 60), 1)
                    : null;
                $quality_pct = $totalt > 0
                    ? round($ibc_ok / $totalt * 100, 1)
                    : null;

                $ranking[] = [
                    'op_number'    => (int)$row['op_number'],
                    'name'         => $row['name'],
                    'ibc_ok'       => $ibc_ok,
                    'ibc_per_hour' => $ibc_per_hour,
                    'quality_pct'  => $quality_pct,
                    'shifts_today' => (int)($row['shifts_count'] ?? 0),
                ];
            }

            // Räkna ibcIdagTotal från UNIKA skift (GROUP BY skiftraknare).
            // Får INTE summeras från ranking-listan — op1/op2/op3 delar samma ibc_ok,
            // summering per operatör ger 3x det verkliga värdet.
            $sqlTotal = str_replace(':dateFrom', '?',
                "SELECT COALESCE(SUM(s2.ibc_ok),0) FROM (
                    SELECT MAX(ibc_ok) AS ibc_ok
                    FROM rebotling_skiftrapport
                    WHERE {$dateFilter}
                    GROUP BY skiftraknare
                ) s2");
            $stmtTotal = $this->pdo->prepare($sqlTotal);
            $stmtTotal->execute([$d]);
            $ibcIdagTotal = (int)$stmtTotal->fetchColumn();

            // Hämta historiskt rekord (bästa dag senaste 365 dagar, exkl idag)
            $rekordIbc = 0;
            $rekordDatum = null;
            try {
                $stmtRecord = $this->pdo->prepare("
                    SELECT MAX(dag_total) AS rekord_ibc, datum_rekord
                    FROM (
                        SELECT DATE(datum) AS datum_rekord, SUM(ibc_ok) AS dag_total
                        FROM rebotling_skiftrapport
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                          AND datum < CURDATE()
                        GROUP BY DATE(datum)
                    ) y
                ");
                $stmtRecord->execute();
                $recordRow = $stmtRecord->fetch(PDO::FETCH_ASSOC);
                if ($recordRow && $recordRow['rekord_ibc'] !== null) {
                    $rekordIbc   = (int)$recordRow['rekord_ibc'];
                    $rekordDatum = $recordRow['datum_rekord'];
                }
            } catch (Exception $e) {
                error_log('RebotlingController::getLiveRanking rekord: ' . $e->getMessage());
            }

            echo json_encode([
                'success'         => true,
                'ranking'         => $ranking,
                'date'            => $today,
                'period'          => $periodLabel,
                'goal'            => $dailyGoal,
                'ibc_idag_total'  => $ibcIdagTotal,
                'rekord_ibc'      => $rekordIbc,
                'rekord_datum'    => $rekordDatum,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getLiveRanking: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta live ranking'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Cykeltids-histogram
    // GET ?action=rebotling&run=cycle-histogram&date=YYYY-MM-DD
    // =========================================================

    private function getSkiftKommentar() {
        $datum    = $_GET['datum']   ?? '';
        $skiftNr  = intval($_GET['skift_nr'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skift_nr (1-3).'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT datum, skift_nr, kommentar, skapad_av, skapad_tid
                 FROM rebotling_skift_kommentar
                 WHERE datum = ? AND skift_nr = ?'
            );
            $stmt->execute([$datum, $skiftNr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => true, 'data' => null], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            error_log('RebotlingController::getSkiftKommentar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel.'], JSON_UNESCAPED_UNICODE);
        }
    }


    private function setSkiftKommentar() {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig request-body.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $datum     = $body['datum']     ?? '';
        $skiftNr   = intval($body['skift_nr'] ?? 0);
        $kommentar = mb_substr(strip_tags(trim($body['kommentar'] ?? '')), 0, 500);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skift_nr (1-3).'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($kommentar) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Kommentaren får vara max 500 tecken.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $skapadAv = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Okänd';

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rebotling_skift_kommentar (datum, skift_nr, kommentar, skapad_av)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE kommentar = VALUES(kommentar), skapad_av = VALUES(skapad_av)'
            );
            $stmt->execute([$datum, $skiftNr, $kommentar, $skapadAv]);

            echo json_encode(['success' => true, 'message' => 'Kommentar sparad.'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::setSkiftKommentar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid sparande.'], JSON_UNESCAPED_UNICODE);
        }
    }
    /**
     * GET ?action=rebotling&run=weekday-stats&dagar=90
     * Returnerar genomsnittlig IBC-produktion och OEE per veckodag.
     * Används av Veckodag-analys i rebotling-statistik.
     */

    private function getEvents(): void {
        $start = trim($_GET['start'] ?? date('Y-m-d', strtotime('-90 days')));
        $end   = trim($_GET['end']   ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d', strtotime('-90 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');
        // Validera att start <= end
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        try {
            // Säkerställ att tabellen finns
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `production_events` (
                    `id`          INT          NOT NULL AUTO_INCREMENT,
                    `event_date`  DATE         NOT NULL,
                    `title`       VARCHAR(200) NOT NULL,
                    `description` TEXT         NULL DEFAULT NULL,
                    `event_type`  VARCHAR(50)  NOT NULL DEFAULT 'ovrigt',
                    `created_by`  INT          NULL DEFAULT NULL,
                    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `idx_event_date` (`event_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmt = $this->pdo->prepare(
                "SELECT id, event_date, title, description, event_type
                 FROM production_events
                 WHERE event_date BETWEEN ? AND ?
                 ORDER BY event_date"
            );
            $stmt->execute([$start, $end]);
            echo json_encode(['success' => true, 'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getEvents: ' . $e->getMessage());
            // Returnera tom lista istället för 500 om tabellen saknas
            echo json_encode(['success' => true, 'events' => []], JSON_UNESCAPED_UNICODE);
        }
    }

    // POST ?action=rebotling&run=add-event (kräver admin-session)

    private function addEvent(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // Stöd både JSON och form-urlencoded (Angular skickar form-urlencoded)
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data)) {
            parse_str($raw, $data);
        }
        if (!is_array($data)) $data = [];
        $date  = trim($data['event_date']   ?? '');
        $title = strip_tags(trim($data['title']   ?? ''));
        $desc  = strip_tags(trim($data['description'] ?? ''));
        $type  = trim($data['event_type']   ?? 'ovrigt');
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }
        if (mb_strlen($desc) > 2000) {
            $desc = mb_substr($desc, 0, 2000);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$title) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltiga uppgifter'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $allowed = ['underhall', 'ny_operator', 'mal_andring', 'rekord', 'ovrigt'];
        if (!in_array($type, $allowed, true)) $type = 'ovrigt';
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO production_events (event_date, title, description, event_type, created_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$date, $title, $desc, $type, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'id' => $this->pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::addEvent: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara händelsen'], JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=stoppage-analysis&days=30
    // Returnerar stoppanalys från stoppage_log + stoppage_reasons.
    // Hanterar saknad tabell gracefully (returnerar empty=true).

    private function deleteEvent(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
            return;
        }
        // Stöd både JSON och form-urlencoded (Angular skickar form-urlencoded)
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data)) {
            parse_str($raw, $data);
        }
        if (!is_array($data)) $data = [];
        $id = intval($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Saknar id'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $this->pdo->prepare("DELETE FROM production_events WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::deleteEvent: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort händelsen'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=shift-trend&datum=YYYY-MM-DD&skift=N
     * Returnerar timupplöst produktionsprofil för ett skift samt genomsnittsprofil
     * för samma veckodag de senaste 4 veckorna.
     */

    private function getPersonalBests() {
        try {
            $pdo = $this->pdo;
            // Beräkna IBC/h och kvalitet per skift per operatör
            // rebotling_skiftrapport: op1/op2/op3 = operator number, ibc_ok, totalt, drifttid
            $sql = "
                SELECT
                    t.op_num AS op_number,
                    COALESCE(o.name, CONCAT('Operatör ', t.op_num)) AS op_name,
                    MAX(CASE WHEN t.drifttid > 0 THEN ROUND(t.ibc_ok / (t.drifttid / 60.0), 2) ELSE NULL END) AS best_ibc_h,
                    MAX(CASE WHEN t.totalt   > 0 THEN ROUND(t.ibc_ok / t.totalt * 100, 1) ELSE NULL END)     AS best_kvalitet,
                    COUNT(DISTINCT t.skift_id) AS total_skift
                FROM (
                    SELECT s.id AS skift_id, s.op1 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s WHERE s.op1 IS NOT NULL AND s.op1 > 0 AND s.ibc_ok > 0
                    UNION ALL
                    SELECT s.id AS skift_id, s.op2 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s WHERE s.op2 IS NOT NULL AND s.op2 > 0 AND s.ibc_ok > 0
                    UNION ALL
                    SELECT s.id AS skift_id, s.op3 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s WHERE s.op3 IS NOT NULL AND s.op3 > 0 AND s.ibc_ok > 0
                ) t
                LEFT JOIN operators o ON o.number = t.op_num
                GROUP BY t.op_num, o.name
                ORDER BY best_ibc_h DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Beräkna team-rekord
            $teamRecord = 0.0;
            foreach ($rows as $r) {
                $val = floatval($r['best_ibc_h'] ?? 0);
                if ($val > $teamRecord) $teamRecord = $val;
            }

            // Bästa dag per operatör (ibc_ok från rebotling_ibc, summerat per dag)
            // Använder ROW_NUMBER() istället för korrelerad subquery för bästa dag-datum
            $sqlBestDay = "
                SELECT ranked.op_num,
                       ranked.day_ibc AS best_day_ibc,
                       ranked.dag     AS best_day_date
                FROM (
                    SELECT t.op_num, t.dag, t.day_ibc,
                           ROW_NUMBER() OVER (PARTITION BY t.op_num ORDER BY t.day_ibc DESC) AS rn
                    FROM (
                        SELECT sub.op_num, DATE(sub.datum) AS dag, SUM(sub.shift_ibc) AS day_ibc
                        FROM (
                            SELECT r.op1 AS op_num, r.datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                            FROM rebotling_ibc r WHERE r.op1 IS NOT NULL AND r.ibc_ok IS NOT NULL
                            GROUP BY r.op1, DATE(r.datum), r.skiftraknare
                            UNION ALL
                            SELECT r.op2 AS op_num, r.datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                            FROM rebotling_ibc r WHERE r.op2 IS NOT NULL AND r.ibc_ok IS NOT NULL
                            GROUP BY r.op2, DATE(r.datum), r.skiftraknare
                            UNION ALL
                            SELECT r.op3 AS op_num, r.datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                            FROM rebotling_ibc r WHERE r.op3 IS NOT NULL AND r.ibc_ok IS NOT NULL
                            GROUP BY r.op3, DATE(r.datum), r.skiftraknare
                        ) sub
                        GROUP BY sub.op_num, DATE(sub.datum)
                    ) t
                ) ranked
                WHERE ranked.rn = 1
            ";
            $stmtBD = $pdo->query($sqlBestDay);
            $bestDayByOp = [];
            foreach ($stmtBD->fetchAll(\PDO::FETCH_ASSOC) as $bd) {
                $bestDayByOp[intval($bd['op_num'])] = [
                    'ibc' => intval($bd['best_day_ibc']),
                    'date' => $bd['best_day_date'],
                ];
            }

            // Bästa vecka per operatör
            $sqlBestWeek = "
                SELECT t.op_num, MAX(t.week_ibc) AS best_week_ibc
                FROM (
                    SELECT sub.op_num, YEAR(sub.datum) AS yr, WEEK(sub.datum, 1) AS wk, SUM(sub.shift_ibc) AS week_ibc
                    FROM (
                        SELECT r.op1 AS op_num, DATE(r.datum) AS datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc r WHERE r.op1 IS NOT NULL AND r.ibc_ok IS NOT NULL
                        GROUP BY r.op1, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op2, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op2 IS NOT NULL AND r.ibc_ok IS NOT NULL
                        GROUP BY r.op2, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op3, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op3 IS NOT NULL AND r.ibc_ok IS NOT NULL
                        GROUP BY r.op3, DATE(r.datum), r.skiftraknare
                    ) sub
                    GROUP BY sub.op_num, YEAR(sub.datum), WEEK(sub.datum, 1)
                ) t
                GROUP BY t.op_num
            ";
            $stmtBW = $pdo->query($sqlBestWeek);
            $bestWeekByOp = [];
            foreach ($stmtBW->fetchAll(\PDO::FETCH_ASSOC) as $bw) {
                $bestWeekByOp[intval($bw['op_num'])] = intval($bw['best_week_ibc']);
            }

            // Bästa månad per operatör
            $sqlBestMonth = "
                SELECT t.op_num, MAX(t.month_ibc) AS best_month_ibc
                FROM (
                    SELECT sub.op_num, DATE_FORMAT(sub.datum, '%Y-%m') AS mon, SUM(sub.shift_ibc) AS month_ibc
                    FROM (
                        SELECT r.op1 AS op_num, DATE(r.datum) AS datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc r WHERE r.op1 IS NOT NULL AND r.ibc_ok IS NOT NULL
                        GROUP BY r.op1, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op2, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op2 IS NOT NULL AND r.ibc_ok IS NOT NULL
                        GROUP BY r.op2, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op3, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op3 IS NOT NULL AND r.ibc_ok IS NOT NULL
                        GROUP BY r.op3, DATE(r.datum), r.skiftraknare
                    ) sub
                    GROUP BY sub.op_num, DATE_FORMAT(sub.datum, '%Y-%m')
                ) t
                GROUP BY t.op_num
            ";
            $stmtBM = $pdo->query($sqlBestMonth);
            $bestMonthByOp = [];
            foreach ($stmtBM->fetchAll(\PDO::FETCH_ASSOC) as $bm) {
                $bestMonthByOp[intval($bm['op_num'])] = intval($bm['best_month_ibc']);
            }

            // Team-rekord dag/vecka/månad (alla operatörer sammanslagna) — en enda query
            $stmtTeamAll = $pdo->query("
                SELECT
                    (SELECT MAX(day_ibc) FROM (
                        SELECT SUM(shift_ibc) AS day_ibc FROM (
                            SELECT DATE(datum) AS datum, skiftraknare, COALESCE(MAX(ibc_ok),0) AS shift_ibc
                            FROM rebotling_ibc WHERE 1=1 AND ibc_ok IS NOT NULL
                            GROUP BY DATE(datum), skiftraknare
                        ) ps GROUP BY DATE(datum)
                    ) td) AS best_day,
                    (SELECT MAX(week_ibc) FROM (
                        SELECT SUM(shift_ibc) AS week_ibc FROM (
                            SELECT DATE(datum) AS datum, skiftraknare, COALESCE(MAX(ibc_ok),0) AS shift_ibc
                            FROM rebotling_ibc WHERE 1=1 AND ibc_ok IS NOT NULL
                            GROUP BY DATE(datum), skiftraknare
                        ) ps GROUP BY YEAR(datum), WEEK(datum,1)
                    ) tw) AS best_week,
                    (SELECT MAX(month_ibc) FROM (
                        SELECT SUM(shift_ibc) AS month_ibc FROM (
                            SELECT DATE(datum) AS datum, skiftraknare, COALESCE(MAX(ibc_ok),0) AS shift_ibc
                            FROM rebotling_ibc WHERE 1=1 AND ibc_ok IS NOT NULL
                            GROUP BY DATE(datum), skiftraknare
                        ) ps GROUP BY DATE_FORMAT(datum, '%Y-%m')
                    ) tm) AS best_month
            ");
            $teamRow = $stmtTeamAll->fetch(\PDO::FETCH_ASSOC);
            $teamBestDay   = intval($teamRow['best_day']   ?? 0);
            $teamBestWeek  = intval($teamRow['best_week']  ?? 0);
            $teamBestMonth = intval($teamRow['best_month'] ?? 0);

            $result = array_map(function($r) use ($teamRecord, $bestDayByOp, $bestWeekByOp, $bestMonthByOp) {
                $opNum = intval($r['op_number']);
                $bestIbcH = round(floatval($r['best_ibc_h'] ?? 0), 2);
                return [
                    'op_number'     => $opNum,
                    'namn'          => $r['op_name'],
                    'initialer'     => mb_strtoupper(mb_substr($r['op_name'] ?? '', 0, 3)),
                    'best_ibc_h'    => $bestIbcH,
                    'best_kvalitet' => round(floatval($r['best_kvalitet'] ?? 0), 1),
                    'pct_of_record' => $teamRecord > 0 ? round($bestIbcH / $teamRecord * 100, 1) : 0,
                    'total_skift'   => intval($r['total_skift']),
                    'best_day_ibc'  => $bestDayByOp[$opNum]['ibc'] ?? 0,
                    'best_day_date' => $bestDayByOp[$opNum]['date'] ?? null,
                    'best_week_ibc' => $bestWeekByOp[$opNum] ?? 0,
                    'best_month_ibc'=> $bestMonthByOp[$opNum] ?? 0,
                ];
            }, $rows);

            echo json_encode([
                'success' => true,
                'data'    => [
                    'operators'         => $result,
                    'team_record_ibc_h' => round($teamRecord, 2),
                    'team_best_day'     => $teamBestDay,
                    'team_best_week'    => $teamBestWeek,
                    'team_best_month'   => $teamBestMonth,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getPersonalBests: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta personbästa-data'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Hall of Fame — topp 5 bästa enskilda dagar
    // GET ?action=rebotling&run=hall-of-fame
    // =========================================================

    private function getHallOfFameDays() {
        try {
            $pdo = $this->pdo;

            // Topp 5 dagar — summa IBC per dag (alla skift sammanslagna)
            // Vi hämtar även vilka operatörer som jobbade den dagen
            $sql = "
                SELECT
                    ps.datum,
                    SUM(ps.shift_ibc) AS ibc_total,
                    ROUND(AVG(ps.shift_quality), 1) AS avg_quality
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        COALESCE(MAX(ibc_ok), 0) AS shift_ibc,
                        ROUND(
                            COALESCE(MAX(ibc_ok), 0) * 100.0
                            / NULLIF(COALESCE(MAX(ibc_ok), 0) + COALESCE(MAX(ibc_ej_ok), 0), 0),
                        1) AS shift_quality
                    FROM rebotling_ibc
                    WHERE 1=1
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY ps.datum
                ORDER BY ibc_total DESC
                LIMIT 5
            ";
            $stmt = $pdo->query($sql);
            $topDays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Hämta operatörsnamn per dag
            $result = [];
            $opStmt = $pdo->prepare("
                SELECT DISTINCT o.name
                FROM rebotling_ibc r
                JOIN operators o ON o.number IN (r.op1, r.op2, r.op3)
                WHERE r.datum >= ? AND r.datum < DATE_ADD(?, INTERVAL 1 DAY)
                  AND r.ibc_ok IS NOT NULL
                ORDER BY o.name
            ");
            foreach ($topDays as $i => $day) {
                $date = $day['datum'];
                // Hämta unika operatörer den dagen
                $opStmt->execute([$date, $date]);
                $operators = $opStmt->fetchAll(\PDO::FETCH_COLUMN);

                $result[] = [
                    'rank'        => $i + 1,
                    'date'        => $date,
                    'ibc_total'   => intval($day['ibc_total']),
                    'avg_quality' => floatval($day['avg_quality']),
                    'operators'   => $operators,
                ];
            }

            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getHallOfFameDays: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta hall of fame-data'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Månatliga resultat (senaste N månader)
    // GET ?action=rebotling&run=monthly-leaders&months=12
    // =========================================================

    private function getMonthlyLeaders() {
        try {
            $months = max(1, min(24, intval($_GET['months'] ?? 12)));
            $pdo = $this->pdo;

            $idealRatePerMin = 15.0 / 60.0;

            // rebotling_skiftrapport: varje rad = ett skift
            $sql = "
                SELECT
                    DATE_FORMAT(datum, '%Y-%m')                              AS manad,
                    SUM(COALESCE(ibc_ok, 0))                                AS total_ibc,
                    SUM(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))      AS total_all,
                    SUM(COALESCE(drifttid, 0))                              AS runtime_min,
                    SUM(COALESCE(rasttime, 0))                              AS rast_min,
                    MAX(COALESCE(ibc_ok, 0) / GREATEST(COALESCE(drifttid, 0) / 60.0, 0.01)) AS top_ibc_h
                FROM rebotling_skiftrapport
                WHERE datum >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                  AND ibc_ok IS NOT NULL
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ORDER BY manad DESC
                LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$months, $months]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = array_map(function($r) use ($idealRatePerMin) {
                $totalAll  = floatval($r['total_all']  ?? 0);
                $totalOk   = floatval($r['total_ibc']  ?? 0);
                $runtimeM  = max(floatval($r['runtime_min'] ?? 0), 1);
                $plannedM  = max($runtimeM + floatval($r['rast_min'] ?? 0), 1);
                $avail     = min($runtimeM / $plannedM, 1.0);
                $perf      = $totalAll > 0 ? min(($totalAll / $runtimeM) / $idealRatePerMin, 1.0) : 0;
                $qual      = $totalAll > 0 ? $totalOk / $totalAll : 0;
                $oee       = round($avail * $perf * $qual * 100, 1);

                return [
                    'manad'    => $r['manad'],
                    'total_ibc'=> intval($r['total_ibc']),
                    'avg_oee'  => $oee,
                    'top_ibc_h'=> round(floatval($r['top_ibc_h'] ?? 0), 1),
                ];
            }, $rows);

            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getMonthlyLeaders: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta månadsdata'], JSON_UNESCAPED_UNICODE);
        }
    }



    private function getAttendance() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt månadsformat'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta alla dagar i månaden där operatörer jobbade (op1, op2, op3 = operator number)
            $sql = "
                SELECT DATE(datum) AS dag, op1 AS op_num FROM rebotling_ibc
                WHERE DATE_FORMAT(datum, '%Y-%m') = ? AND op1 IS NOT NULL
                UNION
                SELECT DATE(datum), op2 FROM rebotling_ibc
                WHERE DATE_FORMAT(datum, '%Y-%m') = ? AND op2 IS NOT NULL
                UNION
                SELECT DATE(datum), op3 FROM rebotling_ibc
                WHERE DATE_FORMAT(datum, '%Y-%m') = ? AND op3 IS NOT NULL
                ORDER BY dag, op_num
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$month, $month, $month]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta alla aktiva operatörer
            $opStmt = $this->pdo->query(
                "SELECT number AS id, name AS namn, '' AS initialer FROM operators WHERE active=1 ORDER BY name"
            );
            $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            // Generera initialer från namn om kolumnen saknar värde
            foreach ($operators as &$op) {
                if ($op['initialer'] === '') {
                    $parts = explode(' ', trim($op['namn']));
                    $ini = '';
                    foreach ($parts as $p) {
                        if ($p !== '') $ini .= mb_strtoupper(mb_substr($p, 0, 1));
                    }
                    $op['initialer'] = mb_substr($ini, 0, 3) ?: ('OP' . $op['id']);
                }
                $op['id'] = (int)$op['id'];
            }
            unset($op);

            // Bygg kalenderstruktur: dag -> [op_id, ...]
            $calendar = [];
            foreach ($rows as $r) {
                $dag = $r['dag'];
                if (!isset($calendar[$dag])) $calendar[$dag] = [];
                $calendar[$dag][] = (int)$r['op_num'];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'month'     => $month,
                    'calendar'  => $calendar,
                    'operators' => $operators
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getAttendance: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }
    /**
     * GET ?action=rebotling&run=live-ranking-settings
     */
    private function getLiveRankingSettings(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        try {
            $keys = ['lr_show_quality', 'lr_show_progress', 'lr_show_motto', 'lr_poll_interval', 'lr_title'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare("SELECT `key`, `value` FROM rebotling_kv_settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => [
                'lr_show_quality'  => ($rows['lr_show_quality']  ?? '1') === '1',
                'lr_show_progress' => ($rows['lr_show_progress'] ?? '1') === '1',
                'lr_show_motto'    => ($rows['lr_show_motto']    ?? '1') === '1',
                'lr_poll_interval' => intval($rows['lr_poll_interval'] ?? 30),
                'lr_title'         => $rows['lr_title'] ?? 'Live Ranking',
            ]], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getLiveRankingSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=goal-history
     * Returnerar historik för dagsmål-ändringar
     */

    private function getHourlyRhythm(): void {
        $days = intval($_GET['days'] ?? 30);
        if ($days < 1 || $days > 365) $days = 30;

        // MySQL 8.0+ approach med LAG() — ger korrekt delta per timme inom samma skift
        // Fallback till enklare GROUP BY om LAG() inte stöds
        $sql = "
            SELECT
                HOUR(datum) AS timme,
                COUNT(DISTINCT DATE(datum)) AS antal_dagar,
                SUM(ibc_delta) AS total_ibc,
                AVG(kvalitet_pct) AS avg_kvalitet
            FROM (
                SELECT
                    datum,
                    skiftraknare,
                    HOUR(datum) AS timme_h,
                    COALESCE(ibc_ok, 0) - COALESCE(LAG(COALESCE(ibc_ok, 0)) OVER (PARTITION BY DATE(datum), skiftraknare ORDER BY datum), 0) AS ibc_delta,
                    CASE WHEN (ibc_ok + ibc_ej_ok) > 0 THEN ibc_ok * 100.0 / (ibc_ok + ibc_ej_ok) ELSE NULL END AS kvalitet_pct
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ) sub
            WHERE ibc_delta BETWEEN 0 AND 20
            GROUP BY timme
            ORDER BY timme
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Fyll alla timmar 6–22 (produktionsdag)
            $result = [];
            for ($h = 6; $h <= 22; $h++) {
                $found = null;
                foreach ($rows as $r) {
                    if (intval($r['timme']) === $h) {
                        $found = $r;
                        break;
                    }
                }
                $avgIbcH = ($found && floatval($found['antal_dagar']) > 0)
                    ? round(floatval($found['total_ibc']) / floatval($found['antal_dagar']), 2)
                    : 0;
                $result[] = [
                    'timme'       => $h,
                    'label'       => sprintf('%02d:00', $h),
                    'avg_ibc_h'   => $avgIbcH,
                    'avg_kvalitet' => $found ? round(floatval($found['avg_kvalitet'] ?? 0), 1) : 0,
                    'antal_dagar' => $found ? intval($found['antal_dagar']) : 0,
                ];
            }

            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getHourlyRhythm: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fel vid hämtning av produktionsrytm'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Operatörslista för trendvy
    // GET ?action=rebotling&run=operator-list-trend
    // Returnerar alla aktiva operatörer med namn + nummer
    // =========================================================

    private function getOperatorListForTrend(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, number
                FROM operators
                WHERE active = 1
                ORDER BY name ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $operators = array_map(function($r) {
                return [
                    'id'     => (int)$r['id'],
                    'name'   => $r['name'],
                    'number' => (int)$r['number'],
                ];
            }, $rows);
            echo json_encode(['success' => true, 'operators' => $operators], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorListForTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörslista'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Operatörsprestanda-trend per vecka
    // GET ?action=rebotling&run=operator-weekly-trend&op_id=<id>&weeks=8|16|26
    // Returnerar IBC/h per vecka för operatören + lagsnittet
    // =========================================================

    private function getOperatorWeeklyTrend(): void {
        $opId  = intval($_GET['op_id']  ?? 0);
        $weeks = intval($_GET['weeks']  ?? 8);

        // Tillåtna veckovärden
        if (!in_array($weeks, [8, 16, 26], true)) $weeks = 8;
        if ($opId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt op_id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Hämta operator-nummer från id
            $opStmt = $this->pdo->prepare("SELECT number, name FROM operators WHERE id = ? LIMIT 1");
            $opStmt->execute([$opId]);
            $op = $opStmt->fetch(PDO::FETCH_ASSOC);
            if (!$op) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Operatör hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $opNumber = (int)$op['number'];
            $opName   = $op['name'];

            // Datumgräns: N veckor bakåt (måndag i startveckan)
            $startDate = date('Y-m-d', strtotime("-{$weeks} weeks"));

            // --- Operatörens veckovisa IBC/h ---
            // rebotling_skiftrapport: ibc_ok (godk), totalt, drifttid (minuter), datum, op1/op2/op3
            $sql = "
                SELECT
                    YEAR(s.datum)                                           AS yr,
                    WEEK(s.datum, 3)                                        AS wk,
                    MIN(CONCAT('V.', LPAD(WEEK(s.datum, 3), 2, '0')))    AS vecka_label,
                    SUM(s.ibc_ok)                                           AS total_ibc,
                    SUM(COALESCE(s.drifttid, 0))                            AS total_drifttid_min,
                    ROUND(
                        SUM(s.ibc_ok) / NULLIF(SUM(COALESCE(s.drifttid,0)) / 60.0, 0),
                        2
                    )                                                       AS ibc_per_h,
                    ROUND(
                        SUM(s.ibc_ok) / NULLIF(SUM(s.totalt), 0) * 100,
                        1
                    )                                                       AS kvalitet_pct,
                    COUNT(DISTINCT s.id)                                    AS antal_skift
                FROM rebotling_skiftrapport s
                WHERE s.datum >= ?
                  AND (s.op1 = ? OR s.op2 = ? OR s.op3 = ?)
                  AND s.ibc_ok > 0
                GROUP BY yr, wk
                ORDER BY yr ASC, wk ASC
            ";

            $opStmt2 = $this->pdo->prepare($sql);
            $opStmt2->execute([$startDate, $opNumber, $opNumber, $opNumber]);
            $opRows = $opStmt2->fetchAll(PDO::FETCH_ASSOC);

            // --- Lagsnitt per vecka (alla operatörer UNION, exkl. denna) ---
            $teamSql = "
                SELECT
                    YEAR(s.datum)                                           AS yr,
                    WEEK(s.datum, 3)                                        AS wk,
                    MIN(CONCAT('V.', LPAD(WEEK(s.datum, 3), 2, '0')))    AS vecka_label,
                    ROUND(
                        SUM(t.ibc_ok) / NULLIF(SUM(COALESCE(t.drifttid_min, 0)) / 60.0, 0),
                        2
                    )                                                       AS team_ibc_per_h,
                    COUNT(DISTINCT s.id)                                    AS team_skift
                FROM rebotling_skiftrapport s
                JOIN (
                    SELECT rs.id AS skift_id,
                           rs.ibc_ok,
                           COALESCE(rs.drifttid, 0) AS drifttid_min
                    FROM rebotling_skiftrapport rs
                    WHERE rs.datum >= ?
                      AND rs.ibc_ok > 0
                ) t ON t.skift_id = s.id
                WHERE s.datum >= ?
                  AND s.ibc_ok > 0
                GROUP BY yr, wk
                ORDER BY yr ASC, wk ASC
            ";
            $teamStmt = $this->pdo->prepare($teamSql);
            $teamStmt->execute([$startDate, $startDate]);
            $teamRows = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexera lagsnitt per vecka-nyckel
            $teamMap = [];
            foreach ($teamRows as $tr) {
                $key = $tr['yr'] . '-' . $tr['wk'];
                $teamMap[$key] = (float)$tr['team_ibc_per_h'];
            }

            // Bygg svar
            $trend = [];
            foreach ($opRows as $r) {
                $key       = $r['yr'] . '-' . $r['wk'];
                $ibcH      = $r['ibc_per_h'] !== null ? (float)$r['ibc_per_h'] : null;
                $teamIbcH  = $teamMap[$key] ?? null;
                $trend[] = [
                    'year'         => (int)$r['yr'],
                    'week_num'     => (int)$r['wk'],
                    'vecka_label'  => $r['vecka_label'],
                    'ibc_per_h'    => $ibcH,
                    'kvalitet_pct' => $r['kvalitet_pct'] !== null ? (float)$r['kvalitet_pct'] : null,
                    'antal_skift'  => (int)$r['antal_skift'],
                    'team_ibc_per_h' => $teamIbcH,
                    'vs_lag'       => ($ibcH !== null && $teamIbcH !== null)
                                        ? round($ibcH - $teamIbcH, 2)
                                        : null,
                ];
            }

            // Beräkna trendpil: jämför snitt senaste 4 veckorna mot de 4 dessförinnan
            $trendArrow   = null; // 'up' | 'down' | 'flat'
            $trendPct     = null;
            $n = count($trend);
            if ($n >= 4) {
                $last4  = array_slice($trend, -4);
                $prev4  = $n >= 8 ? array_slice($trend, -8, 4) : array_slice($trend, 0, max(1, $n - 4));
                $avgLast = array_sum(array_column(array_filter($last4,  fn($t) => $t['ibc_per_h'] !== null), 'ibc_per_h'))
                         / max(1, count(array_filter($last4, fn($t) => $t['ibc_per_h'] !== null)));
                $avgPrev = array_sum(array_column(array_filter($prev4, fn($t) => $t['ibc_per_h'] !== null), 'ibc_per_h'))
                         / max(1, count(array_filter($prev4, fn($t) => $t['ibc_per_h'] !== null)));
                if ($avgPrev > 0) {
                    $trendPct = round(($avgLast - $avgPrev) / $avgPrev * 100, 1);
                    if ($trendPct > 1)       $trendArrow = 'up';
                    elseif ($trendPct < -1)  $trendArrow = 'down';
                    else                     $trendArrow = 'flat';
                }
            }

            echo json_encode([
                'success'       => true,
                'op_id'         => $opId,
                'op_name'       => $opName,
                'op_number'     => $opNumber,
                'weeks'         => $weeks,
                'trend'         => $trend,
                'trend_arrow'   => $trendArrow,
                'trend_pct'     => $trendPct,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorWeeklyTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta trenddata'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Auto-kontrollera och skapa rekordnyhet
    // Anropas från getLiveStats() efter kl 18:00
    // Använder guard: max en rekordnyhet per dag
    // =========================================================

    private function checkAndCreateRecordNews(): void {
        try {
            $this->pdo->beginTransaction();

            // Kontrollera om en rekordnyhet redan skapats idag (FOR UPDATE för att undvika dubbletter vid parallella requests)
            $stmtCheck = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM news
                WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                  AND category = 'rekord'
                FOR UPDATE
            ");
            $stmtCheck->execute();
            $checkRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($checkRow && (int)$checkRow['cnt'] > 0) {
                // Rekordnyhet redan skapad idag, skippa
                $this->pdo->rollBack();
                return;
            }

            // Hämta dagens IBC-total (alla skift idag, korrekta aggregerade värden)
            $stmtToday = $this->pdo->query("
                SELECT COALESCE(SUM(dag_ibc), 0) AS idag_total
                FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS dag_ibc
                    FROM rebotling_ibc
                    WHERE datum >= CURDATE() AND datum < CURDATE() + INTERVAL 1 DAY
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $todayRow = $stmtToday->fetch(PDO::FETCH_ASSOC);
            $ibcIdag = (int)($todayRow['idag_total'] ?? 0);

            if ($ibcIdag <= 0) {
                $this->pdo->rollBack();
                return;
            }

            // Hämta historiskt rekord (bästa dag före idag)
            $stmtRekord = $this->pdo->query("
                SELECT MAX(dag_total) AS rekord_ibc, datum_rekord
                FROM (
                    SELECT DATE(datum) AS datum_rekord, SUM(shift_ibc) AS dag_total
                    FROM (
                        SELECT DATE(datum) AS datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE datum < CURDATE()
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY datum_rekord
                ) AS per_day
            ");
            $rekordRow = $stmtRekord->fetch(PDO::FETCH_ASSOC);
            $rekordIbc = (int)($rekordRow['rekord_ibc'] ?? 0);

            // Slå bara om idag är bättre än rekordet
            if ($ibcIdag <= $rekordIbc) {
                $this->pdo->rollBack();
                return;
            }

            // Skapa rekordnyhet
            $titel = 'Ny rekordag!';
            $body = 'Idag producerades ' . $ibcIdag . ' IBC — ett nytt rekord! '
                  . ($rekordIbc > 0 ? 'Förra rekordet var ' . $rekordIbc . ' IBC.' : 'Det är det bästa resultatet hittills!');

            $stmtInsert = $this->pdo->prepare("
                INSERT INTO news (title, body, category, pinned, published, priority, created_at, updated_at)
                VALUES (:title, :body, 'rekord', 1, 1, 5, NOW(), NOW())
            ");
            $stmtInsert->execute([
                ':title' => $titel,
                ':body'  => $body,
            ]);

            $this->pdo->commit();
            error_log('RebotlingController::checkAndCreateRecordNews: Rekordnyhet skapad! Idag=' . $ibcIdag . ', Rekord=' . $rekordIbc);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RebotlingController::checkAndCreateRecordNews: ' . $e->getMessage());
        }
    }

    // =========================================================
    // Manuell admin-trigger: Skapa rekordnyhet för idag
    // POST ?action=rebotling&run=create-record-news
    // Kräver admin-session
    // =========================================================

    private function getKassationTyper() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, namn, beskrivning, sortorder
                FROM kassationsorsak_typer
                WHERE aktiv = 1
                ORDER BY sortorder ASC, namn ASC
            ");
            $typer = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($typer as &$t) {
                $t['id'] = (int)$t['id'];
                $t['sortorder'] = (int)$t['sortorder'];
            }
            unset($t);
            echo json_encode(['success' => true, 'data' => $typer], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getKassationTyper: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kassationstyper'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=kassation-pareto&days=30
     * Returnerar Pareto-data för kassationsorsaker under angiven period.
     * Inkluderar även KPI: totalkassation och % av produktion.
     */

    private function getKassationPareto() {
        $days = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 30;
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        $toDate   = date('Y-m-d');

        try {
            // Pareto per orsak
            $stmt = $this->pdo->prepare("
                SELECT
                    t.id,
                    t.namn,
                    t.sortorder,
                    COALESCE(SUM(r.antal), 0) AS total_antal
                FROM kassationsorsak_typer t
                LEFT JOIN kassationsregistrering r
                    ON r.orsak_id = t.id
                    AND r.datum BETWEEN :from_date AND :to_date
                WHERE t.aktiv = 1
                GROUP BY t.id, t.namn, t.sortorder
                ORDER BY total_antal DESC, t.sortorder ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalKassation = array_sum(array_column($rows, 'total_antal'));

            // Beräkna kumulativ %
            $cumulative = 0;
            $pareto = [];
            foreach ($rows as $row) {
                $antal = (int)$row['total_antal'];
                $pct = $totalKassation > 0 ? round($antal / $totalKassation * 100, 1) : 0;
                $cumulative += $pct;
                $pareto[] = [
                    'id'             => (int)$row['id'],
                    'namn'           => $row['namn'],
                    'antal'          => $antal,
                    'pct'            => $pct,
                    'kumulativ_pct'  => round($cumulative, 1),
                ];
            }

            // KPI: total produktion (IBC) under perioden för att beräkna kassation%
            $stmtProd = $this->pdo->prepare("
                SELECT COALESCE(SUM(shift_total), 0) AS total_prod
                FROM (
                    SELECT
                        r.skiftraknare,
                        MAX(COALESCE(r.ibc_ok, 0)) + MAX(COALESCE(r.ibc_ej_ok, 0)) + MAX(COALESCE(r.bur_ej_ok, 0)) AS shift_total
                    FROM rebotling_ibc r
                    WHERE r.datum >= :from_date AND r.datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                     
                    GROUP BY DATE(r.datum), r.skiftraknare
                ) AS per_shift
            ");
            $stmtProd->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $prodRow = $stmtProd->fetch(PDO::FETCH_ASSOC);
            $totalProduktion = (int)($prodRow['total_prod'] ?? 0);

            $kassationPct = $totalProduktion > 0
                ? round($totalKassation / $totalProduktion * 100, 2)
                : 0;

            echo json_encode([
                'success'          => true,
                'days'             => $days,
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
                'pareto'           => $pareto,
                'total_kassation'  => (int)$totalKassation,
                'total_produktion' => (int)$totalProduktion,
                'kassation_pct'    => $kassationPct,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getKassationPareto: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta Pareto-data'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=kassation-senaste&limit=10
     * Returnerar de senaste kassationsregistreringarna.
     */

    private function getKassationSenaste() {
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    r.id,
                    r.datum,
                    r.skiftraknare,
                    r.antal,
                    r.kommentar,
                    r.created_at,
                    COALESCE(t.namn, 'Okänd') AS orsak_namn,
                    u.username AS registrerad_av_namn
                FROM kassationsregistrering r
                LEFT JOIN kassationsorsak_typer t ON t.id = r.orsak_id
                LEFT JOIN users u ON u.id = r.registrerad_av
                ORDER BY r.created_at DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['id']          = (int)$row['id'];
                $row['antal']       = (int)$row['antal'];
                $row['skiftraknare'] = $row['skiftraknare'] !== null ? (int)$row['skiftraknare'] : null;
            }
            unset($row);
            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getKassationSenaste: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kassationsregistreringar'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=kassation-register
     * Body: { orsak_id, antal, datum, skiftraknare?, kommentar? }
     * Kräver inloggning (ej nödvändigtvis admin).
     */

    private function registerKassation() {
        $data       = json_decode(file_get_contents('php://input'), true) ?? [];
        $orsakId    = isset($data['orsak_id'])    ? (int)$data['orsak_id']    : 0;
        $antal      = isset($data['antal'])       ? (int)$data['antal']       : 1;
        $datum      = $data['datum']      ?? date('Y-m-d');
        $skiftnr    = isset($data['skiftraknare']) ? (int)$data['skiftraknare'] : null;
        $kommentar  = isset($data['kommentar'])   ? strip_tags(trim($data['kommentar']))   : null;
        $userId     = $_SESSION['user_id'] ?? null;

        // Validering
        if ($orsakId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig orsak_id'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($antal < 1 || $antal > 9999) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Antal måste vara mellan 1 och 9999'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($kommentar && mb_strlen($kommentar) > 500) {
            $kommentar = mb_substr($kommentar, 0, 500);
        }

        try {
            // Verifiera att orsak_id finns
            $checkStmt = $this->pdo->prepare("SELECT id FROM kassationsorsak_typer WHERE id = ? AND aktiv = 1");
            $checkStmt->execute([$orsakId]);
            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Kassationsorsak hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO kassationsregistrering
                    (datum, skiftraknare, orsak_id, antal, kommentar, registrerad_av)
                VALUES
                    (:datum, :skiftraknare, :orsak_id, :antal, :kommentar, :registrerad_av)
            ");
            $stmt->execute([
                ':datum'           => $datum,
                ':skiftraknare'    => $skiftnr,
                ':orsak_id'        => $orsakId,
                ':antal'           => $antal,
                ':kommentar'       => $kommentar ?: null,
                ':registrerad_av'  => $userId,
            ]);
            echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::registerKassation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara kassationsregistrering'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================================
    // Bemanningsvarning
    // =========================================================================

    /**
     * GET ?action=rebotling&run=staffing-warning
     * Returnerar dagar de närmaste 7 dagarna där antal schemalagda operatörer < min_operators.
     */

    private function getStaffingWarning() {
        try {
            // Hämta min_operators från rebotling_settings (default 2)
            $minOps = 2;
            try {
                $sr = $this->pdo->query("SELECT min_operators FROM rebotling_settings WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);
                if ($sr && isset($sr['min_operators'])) {
                    $minOps = max(1, (int)$sr['min_operators']);
                }
            } catch (\Exception $e) {
                error_log('RebotlingController::getStaffingWarning min_operators: ' . $e->getMessage());
            }

            $today    = date('Y-m-d');
            $in7days  = date('Y-m-d', strtotime('+6 days'));

            // Hämta antal unika operatörer per dag och skift de närmaste 7 dagarna
            $stmt = $this->pdo->prepare("
                SELECT datum, skift_nr, COUNT(DISTINCT op_number) AS antal_ops
                FROM shift_plan
                WHERE datum BETWEEN :today AND :in7days
                GROUP BY datum, skift_nr
            ");
            $stmt->execute([':today' => $today, ':in7days' => $in7days]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg index: [datum][skift_nr] => antal_ops
            $planIndex = [];
            foreach ($rows as $row) {
                $planIndex[$row['datum']][(int)$row['skift_nr']] = (int)$row['antal_ops'];
            }

            // Hitta dagar med minst ett skift som har för få operatörer
            $warnings = [];
            $dagNamn  = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
            for ($i = 0; $i < 7; $i++) {
                $datum = date('Y-m-d', strtotime("+{$i} days"));
                $underbemanning = [];
                for ($skift = 1; $skift <= 3; $skift++) {
                    $antal = $planIndex[$datum][$skift] ?? 0;
                    if ($antal < $minOps) {
                        $underbemanning[] = [
                            'skift_nr'    => $skift,
                            'antal_ops'   => $antal,
                        ];
                    }
                }
                if (!empty($underbemanning)) {
                    $dow = (int)date('N', strtotime($datum)) - 1; // 0=mån
                    $warnings[] = [
                        'datum'          => $datum,
                        'dag_namn'       => $dagNamn[$dow],
                        'underbemanning' => $underbemanning,
                    ];
                }
            }

            echo json_encode([
                'success'       => true,
                'min_operators' => $minOps,
                'warnings'      => $warnings,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getStaffingWarning: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta bemanningsvarning'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================================
    // Månadsrapport: Topp-5 stopporsaker
    // =========================================================================

    /**
     * GET ?action=rebotling&run=monthly-stop-summary&month=YYYY-MM
     * Returnerar topp-5 stopporsaker för angiven månad.
     */

    private function getMonthlyStopSummary() {
        try {
            $month = $_GET['month'] ?? '';
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig månadsparameter (YYYY-MM krävs)'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Kontrollera om tabellen finns
            try {
                $checkStmt = $this->pdo->query("SHOW TABLES LIKE 'rebotling_stopporsak'");
                if ($checkStmt->rowCount() === 0) {
                    echo json_encode(['success' => true, 'items' => [], 'fallback' => true], JSON_UNESCAPED_UNICODE);
                    return;
                }
            } catch (\Exception $e) {
                error_log('RebotlingController::getTopStopp table check: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av stopporsaker', 'items' => [], 'fallback' => true], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->pdo->prepare("
                SELECT orsak,
                       COUNT(*) AS antal,
                       SUM(minuter) AS total_min
                FROM rebotling_stopporsak
                WHERE DATE_FORMAT(start_tid, '%Y-%m') = :month
                GROUP BY orsak
                ORDER BY total_min DESC
                LIMIT 5
            ");
            $stmt->execute([':month' => $month]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Beräkna totalminuter för procentuell andel
            $totalMin = 0;
            foreach ($rows as $row) {
                $totalMin += (float)$row['total_min'];
            }

            $items = [];
            foreach ($rows as $row) {
                $pct = $totalMin > 0 ? round(((float)$row['total_min'] / $totalMin) * 100, 1) : 0;
                $items[] = [
                    'orsak'     => $row['orsak'],
                    'antal'     => (int)$row['antal'],
                    'total_min' => (int)$row['total_min'],
                    'pct'       => $pct,
                ];
            }

            echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('RebotlingController::getMonthlyStopSummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta stopporsaker'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Flexibla dagsmål per datum (undantag)
    // =========================================================

    /**
     * GET ?action=rebotling&run=goal-exceptions[&month=YYYY-MM]
     * Admin-only. Returnerar alla undantag, optionellt filtrerat per månad.
     */

    private function getOeeComponents() {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 14;
        if ($days < 1) $days = 1;
        if ($days > 90) $days = 90;

        try {
            $sql = "
                SELECT
                    datum_day AS dag,
                    SUM(shift_ibc_ok)     AS daily_ibc_ok,
                    SUM(shift_ibc_ej_ok)  AS daily_ibc_ej_ok,
                    SUM(shift_runtime)    AS daily_runtime_min,
                    SUM(shift_rast)       AS daily_rast_min
                FROM (
                    SELECT
                        DATE(datum)                         AS datum_day,
                        skiftraknare,
                        COALESCE(MAX(ibc_ok), 0)            AS shift_ibc_ok,
                        COALESCE(MAX(ibc_ej_ok), 0)         AS shift_ibc_ej_ok,
                        COALESCE(MAX(runtime_plc), 0)       AS shift_runtime,
                        COALESCE(MAX(rasttime), 0)          AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    GROUP BY DATE(datum), skiftraknare
                ) per_shift
                GROUP BY datum_day
                ORDER BY datum_day ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':days' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $row) {
                $runtime = (float)$row['daily_runtime_min'];
                $rast    = (float)$row['daily_rast_min'];
                $ibcOk   = (float)$row['daily_ibc_ok'];
                $ibcEj   = (float)$row['daily_ibc_ej_ok'];

                $tillganglighet = null;
                if (($runtime + $rast) > 0) {
                    $tillganglighet = round($runtime / ($runtime + $rast) * 100, 2);
                }

                $kvalitet = null;
                if (($ibcOk + $ibcEj) > 0) {
                    $kvalitet = round($ibcOk / ($ibcOk + $ibcEj) * 100, 2);
                }

                $data[] = [
                    'datum'          => $row['dag'],
                    'tillganglighet' => $tillganglighet,
                    'kvalitet'       => $kvalitet,
                ];
            }

            echo json_encode([
                'success' => true,
                'days'    => $days,
                'data'    => $data,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingController::getOeeComponents: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta OEE-komponentdata'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=service-status  (publik — ingen auth)
     */

    private function getProductionRate(): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(AVG(CASE WHEN dag >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN ibc_total END), 0) as avg_ibc_per_day_7d,
                    COALESCE(AVG(CASE WHEN dag >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ibc_total END), 0) as avg_ibc_per_day_30d,
                    COALESCE(AVG(ibc_total), 0) as avg_ibc_per_day_90d
                FROM (
                    SELECT dag, SUM(max_ok) as ibc_total
                    FROM (
                        SELECT DATE(datum) AS dag, skiftraknare, COALESCE(MAX(ibc_ok), 0) AS max_ok
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                        GROUP BY DATE(datum), skiftraknare
                    ) per_shift
                    GROUP BY dag
                    HAVING ibc_total > 0
                ) t
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $dagMal = 100;
            try {
                $s = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($s) $dagMal = (int)$s["rebotling_target"];
            } catch (\Exception $e2) {
                error_log("RebotlingController::getProductionRate: could not fetch rebotling_settings: " . $e2->getMessage());
            }

            echo json_encode([
                "success" => true,
                "data" => [
                    "avg_ibc_per_day_7d"  => round((float)($row["avg_ibc_per_day_7d"] ?? 0), 1),
                    "avg_ibc_per_day_30d" => round((float)($row["avg_ibc_per_day_30d"] ?? 0), 1),
                    "avg_ibc_per_day_90d" => round((float)($row["avg_ibc_per_day_90d"] ?? 0), 1),
                    "dag_mal"             => $dagMal
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("RebotlingController::getProductionRate: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Serverfel"], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skiftrapport-lista — med valfritt operatörsfilter
    // GET ?action=rebotling&run=skiftrapport-list[&operator=X][&limit=50][&offset=0]
    // =========================================================

    // =========================================================
    // PLC Diagnostik — combined event feed from all rebotling tables
    // GET ?action=rebotling&run=plc-diagnostik[&date=YYYY-MM-DD][&since_id=N][&limit=100]
    // =========================================================
    private function getPlcDiagnostik(): void {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            $sinceId = isset($_GET['since_id']) ? intval($_GET['since_id']) : 0;
            $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 500) : 100;
            $typeFilter = $_GET['type'] ?? ''; // onoff, ibc, rast, driftstopp or empty for all

            $events = [];

            // --- rebotling_onoff ---
            if (!$typeFilter || $typeFilter === 'onoff') {
                $sql = "SELECT id, s_count_h, s_count_l, datum, running, runtime_today, program, op1, op2, op3, produkt, antal, runtime_plc, skiftraknare
                        FROM rebotling_onoff
                        WHERE DATE(datum) = :date";
                $params = [':date' => $date];
                if ($sinceId > 0) {
                    $sql .= " AND id > :since_id";
                    $params[':since_id'] = $sinceId;
                }
                $sql .= " ORDER BY datum DESC LIMIT " . $limit;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $row['source'] = 'onoff';
                    $row['event_type'] = intval($row['running']) === 1 ? 'ON' : 'OFF';
                    $events[] = $row;
                }
            }

            // --- rebotling_ibc ---
            if (!$typeFilter || $typeFilter === 'ibc') {
                $sql = "SELECT id, s_count, ibc_count, datum, other, skiftraknare, produktion_procent, ibc_ok, ibc_ej_ok, bur_ej_ok, runtime_plc, rasttime, op1, op2, op3, effektivitet, produktivitet, kvalitet, bonus_poang, produkt, lopnummer, runtime, nyttlopnummer
                        FROM rebotling_ibc
                        WHERE DATE(datum) = :date";
                $params = [':date' => $date];
                if ($sinceId > 0) {
                    $sql .= " AND id > :since_id";
                    $params[':since_id'] = $sinceId;
                }
                $sql .= " ORDER BY datum DESC LIMIT " . $limit;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $row['source'] = 'ibc';
                    $row['event_type'] = 'IBC';
                    $events[] = $row;
                }
            }

            // --- rebotling_runtime (rast) ---
            if (!$typeFilter || $typeFilter === 'rast') {
                $sql = "SELECT id, datum, rast_status
                        FROM rebotling_runtime
                        WHERE DATE(datum) = :date";
                $params = [':date' => $date];
                if ($sinceId > 0) {
                    $sql .= " AND id > :since_id";
                    $params[':since_id'] = $sinceId;
                }
                $sql .= " ORDER BY datum DESC LIMIT " . $limit;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $row['source'] = 'rast';
                    $row['event_type'] = intval($row['rast_status']) === 1 ? 'RAST_START' : 'RAST_END';
                    $events[] = $row;
                }
            }

            // --- rebotling_driftstopp ---
            if (!$typeFilter || $typeFilter === 'driftstopp') {
                $sql = "SELECT id, datum, driftstopp_status, skiftraknare
                        FROM rebotling_driftstopp
                        WHERE DATE(datum) = :date";
                $params = [':date' => $date];
                if ($sinceId > 0) {
                    $sql .= " AND id > :since_id";
                    $params[':since_id'] = $sinceId;
                }
                $sql .= " ORDER BY datum DESC LIMIT " . $limit;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $row['source'] = 'driftstopp';
                    $row['event_type'] = intval($row['driftstopp_status']) === 1 ? 'STOPP_START' : 'STOPP_END';
                    $events[] = $row;
                }
            }

            // Sort all events by datum descending, then by id descending
            usort($events, function ($a, $b) {
                $cmp = strcmp($b['datum'], $a['datum']);
                if ($cmp !== 0) return $cmp;
                return intval($b['id']) - intval($a['id']);
            });

            // Trim to limit
            $events = array_slice($events, 0, $limit);

            // Compute max_id for polling
            $maxId = 0;
            foreach ($events as $e) {
                $eid = intval($e['id']);
                if ($eid > $maxId) $maxId = $eid;
            }

            // --- rebotling_skiftrapport ---
            $stmtSkift = $this->pdo->prepare(
                "SELECT id, datum, ibc_ok, ibc_ej_ok, bur_ej_ok, totalt, drifttid, rasttime, driftstopptime,
                        op1, op2, op3, product_id, skiftraknare, lopnummer, inlagd, created_at, updated_at
                 FROM rebotling_skiftrapport
                 WHERE datum = :date
                 ORDER BY id ASC"
            );
            $stmtSkift->execute([':date' => $date]);
            $skiftrapporter = $stmtSkift->fetchAll(\PDO::FETCH_ASSOC);

            // Quick stats: always show CURRENT status (today), not historical date
            $today = date('Y-m-d');
            $stmtStatus = $this->pdo->query("SELECT running, skiftraknare, datum FROM rebotling_onoff ORDER BY id DESC LIMIT 1");
            $latestOnoff = $stmtStatus->fetch(\PDO::FETCH_ASSOC);

            $stmtIbc = $this->pdo->prepare("SELECT COALESCE(MAX(ibc_count), 0) as ibc_today FROM rebotling_ibc WHERE datum >= :today AND datum < DATE_ADD(:today2, INTERVAL 1 DAY)");
            $stmtIbc->execute([':today' => $today, ':today2' => $today]);
            $ibcRow = $stmtIbc->fetch(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'max_id' => $maxId,
                    'stats' => [
                        'running' => $latestOnoff ? intval($latestOnoff['running']) === 1 : false,
                        'skiftraknare' => $latestOnoff ? intval($latestOnoff['skiftraknare']) : 0,
                        'last_event' => $latestOnoff['datum'] ?? null,
                        'ibc_today' => intval($ibcRow['ibc_today'] ?? 0),
                    ],
                    'date' => $date,
                    'event_count' => count($events),
                    'skiftrapporter' => $skiftrapporter,
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("RebotlingController::getPlcDiagnostik: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av PLC-diagnostik.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST ?action=rebotling&run=plc-simulate
     * Simulerar PLC-signaler för testning.
     * Body JSON: { "command": "onoff", "value": "on"|"off" }
     *            { "command": "rast", "value": "on"|"off" }
     *            { "command": "driftstopp", "value": "on"|"off" }
     */
    private function plcSimulate(): void {
        try {
            $body = json_decode(file_get_contents('php://input'), true);
            $command = $body['command'] ?? '';
            $value = $body['value'] ?? '';

            if (!in_array($command, ['onoff', 'rast', 'driftstopp'])) {
                echo json_encode(['success' => false, 'error' => "Okänt kommando: $command. Tillgängliga: onoff, rast, driftstopp"], JSON_UNESCAPED_UNICODE);
                return;
            }
            if (!in_array($value, ['on', 'off'])) {
                echo json_encode(['success' => false, 'error' => "Ogiltigt värde: $value. Ange on eller off"], JSON_UNESCAPED_UNICODE);
                return;
            }

            $isOn = $value === 'on';

            switch ($command) {
                case 'onoff':
                    // Hämta senaste skifträknare
                    $lastRow = $this->pdo->query("SELECT skiftraknare, runtime_today FROM rebotling_onoff ORDER BY datum DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                    $sk = $lastRow ? intval($lastRow['skiftraknare']) : 1;
                    $rt = $lastRow ? intval($lastRow['runtime_today']) : 0;
                    if ($isOn && $lastRow) $sk++; // nytt skift vid start

                    $stmt = $this->pdo->prepare("INSERT INTO rebotling_onoff (s_count_h, s_count_l, datum, running, runtime_today, program, op1, op2, op3, produkt, antal, runtime_plc, skiftraknare) VALUES (0, 0, NOW(), :running, :rt, 0, 0, 0, 0, 0, 0, 0, :sk)");
                    $stmt->execute(['running' => $isOn ? 1 : 0, 'rt' => $rt, 'sk' => $sk]);
                    $msg = $isOn ? "Linje STARTAD (skift $sk)" : "Linje STOPPAD (skift $sk)";
                    break;

                case 'rast':
                    $stmt = $this->pdo->prepare("INSERT INTO rebotling_runtime (datum, rast_status) VALUES (NOW(), :status)");
                    $stmt->execute(['status' => $isOn ? 1 : 0]);
                    $msg = $isOn ? "Rast STARTAD" : "Rast AVSLUTAD";
                    break;

                case 'driftstopp':
                    $lastSk = $this->pdo->query("SELECT skiftraknare FROM rebotling_onoff ORDER BY datum DESC LIMIT 1")->fetchColumn();
                    $stmt = $this->pdo->prepare("INSERT INTO rebotling_driftstopp (datum, driftstopp_status, skiftraknare) VALUES (NOW(), :status, :sk)");
                    $stmt->execute(['status' => $isOn ? 1 : 0, 'sk' => intval($lastSk ?: 0)]);
                    $msg = $isOn ? "Driftstopp AKTIVERAT" : "Driftstopp AVSLUTAT";
                    break;

                default:
                    $msg = '';
            }

            echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("RebotlingController::plcSimulate: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Simulering misslyckades: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorAnalys(): void {
        try {
            $aFrom = $_GET['period_a_from'] ?? date('Y-m-01', strtotime('-1 month'));
            $aTo   = $_GET['period_a_to']   ?? date('Y-m-t', strtotime('-1 month'));
            $bFrom = $_GET['period_b_from'] ?? date('Y-m-01');
            $bTo   = $_GET['period_b_to']   ?? date('Y-m-d');

            foreach ([$aFrom, $aTo, $bFrom, $bTo] as $d) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }

            // Operator names
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            $dataA = $this->queryOperatorPeriodStats($aFrom, $aTo);
            $dataB = $this->queryOperatorPeriodStats($bFrom, $bTo);

            $allNums = array_unique(array_merge(array_keys($dataA), array_keys($dataB)));
            $result = [];

            foreach ($opNames as $num => $name) {
                if (!in_array($num, $allNums)) continue;
                $trendFrom = date('Y-m-d', strtotime($bTo . ' -12 weeks'));
                $trend = $this->queryOperatorWeeklyTrend($num, $trendFrom, $bTo);
                $result[] = [
                    'number'   => $num,
                    'name'     => $name,
                    'period_a' => $dataA[$num] ?? null,
                    'period_b' => $dataB[$num] ?? null,
                    'trend'    => $trend,
                ];
            }

            usort($result, function($a, $b) {
                return ($b['period_b']['ibc_per_h'] ?? 0) <=> ($a['period_b']['ibc_per_h'] ?? 0);
            });

            // Global averages for period_b (for normalization reference)
            $allIbcPerH = array_filter(array_map(fn($r) => $r['period_b']['ibc_per_h'] ?? null, $result));
            $avgIbcPerH = count($allIbcPerH) > 0 ? round(array_sum($allIbcPerH) / count($allIbcPerH), 1) : 0;

            echo json_encode([
                'success' => true,
                'data'    => [
                    'period_a'   => ['from' => $aFrom, 'to' => $aTo],
                    'period_b'   => ['from' => $bFrom, 'to' => $bTo],
                    'operatorer' => $result,
                    'avg_ibc_per_h' => $avgIbcPerH,
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorAnalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsanalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function queryOperatorPeriodStats(string $from, string $to): array {
        // GROUP BY skiftraknare in each branch deduplicates PLC multi-rows per shift.
        // HAVING MAX(drifttid) >= 30 filters out test/bogus entries.
        $sql = "
            SELECT op_nr, pos,
                   COUNT(*)                            AS antal_skift,
                   SUM(ibc_ok)                         AS ibc_ok,
                   SUM(ibc_ej_ok)                      AS ibc_ej_ok,
                   SUM(bur_ej_ok)                      AS bur_ej_ok,
                   SUM(totalt)                         AS totalt,
                   SUM(drifttid)                       AS drifttid_min,
                   SUM(rasttime)                       AS rasttime_min
            FROM (
                SELECT op1 AS op_nr, 'op1' AS pos,
                       MAX(ibc_ok) AS ibc_ok, MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                       MAX(COALESCE(bur_ej_ok,0)) AS bur_ej_ok, MAX(COALESCE(totalt,0)) AS totalt,
                       MAX(COALESCE(drifttid,0)) AS drifttid, MAX(COALESCE(rasttime,0)) AS rasttime
                FROM rebotling_skiftrapport
                WHERE op1 IS NOT NULL AND op1 > 0 AND datum BETWEEN :from1 AND :to1
                GROUP BY skiftraknare, op1
                HAVING MAX(drifttid) >= 30
                UNION ALL
                SELECT op2, 'op2',
                       MAX(ibc_ok), MAX(COALESCE(ibc_ej_ok,0)),
                       MAX(COALESCE(bur_ej_ok,0)), MAX(COALESCE(totalt,0)),
                       MAX(COALESCE(drifttid,0)), MAX(COALESCE(rasttime,0))
                FROM rebotling_skiftrapport
                WHERE op2 IS NOT NULL AND op2 > 0 AND datum BETWEEN :from2 AND :to2
                GROUP BY skiftraknare, op2
                HAVING MAX(drifttid) >= 30
                UNION ALL
                SELECT op3, 'op3',
                       MAX(ibc_ok), MAX(COALESCE(ibc_ej_ok,0)),
                       MAX(COALESCE(bur_ej_ok,0)), MAX(COALESCE(totalt,0)),
                       MAX(COALESCE(drifttid,0)), MAX(COALESCE(rasttime,0))
                FROM rebotling_skiftrapport
                WHERE op3 IS NOT NULL AND op3 > 0 AND datum BETWEEN :from3 AND :to3
                GROUP BY skiftraknare, op3
                HAVING MAX(drifttid) >= 30
            ) combined
            GROUP BY op_nr, pos
            HAVING antal_skift > 0
            ORDER BY op_nr, pos
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from1' => $from, ':to1' => $to, ':from2' => $from, ':to2' => $to, ':from3' => $from, ':to3' => $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $byOp = [];
        foreach ($rows as $row) {
            $num = (int)$row['op_nr'];
            if (!isset($byOp[$num])) {
                $byOp[$num] = [
                    'antal_skift' => 0, 'ibc_ok' => 0, 'ibc_ej_ok' => 0,
                    'bur_ej_ok' => 0, 'totalt' => 0,
                    'drifttid_min' => 0, 'rasttime_min' => 0,
                    'per_position' => [],
                ];
            }
            $byOp[$num]['antal_skift']  += (int)$row['antal_skift'];
            $byOp[$num]['ibc_ok']       += (int)$row['ibc_ok'];
            $byOp[$num]['ibc_ej_ok']    += (int)$row['ibc_ej_ok'];
            $byOp[$num]['bur_ej_ok']    += (int)$row['bur_ej_ok'];
            $byOp[$num]['totalt']       += (int)$row['totalt'];
            $byOp[$num]['drifttid_min'] += (int)$row['drifttid_min'];
            $byOp[$num]['rasttime_min'] += (int)$row['rasttime_min'];

            $d = (int)$row['drifttid_min'];
            $ibc = (int)$row['ibc_ok'];
            $byOp[$num]['per_position'][$row['pos']] = [
                'antal_skift' => (int)$row['antal_skift'],
                'ibc_ok'      => $ibc,
                'drifttid_min'=> $d,
                'ibc_per_h'   => $d > 0 ? round($ibc / ($d / 60.0), 1) : 0,
            ];
        }

        foreach ($byOp as $num => &$op) {
            $d = $op['drifttid_min'];
            $r = $op['rasttime_min'];
            $ibc = $op['ibc_ok'];
            $tot = $op['totalt'];
            $kass = $op['ibc_ej_ok'] + $op['bur_ej_ok'];
            $op['ibc_per_h']          = $d > 0 ? round($ibc / ($d / 60.0), 1) : 0;
            $op['kassation_pct']      = $tot > 0 ? round($kass / $tot * 100, 1) : 0;
            $op['tillganglighet_pct'] = ($d + $r) > 0 ? round($d / ($d + $r) * 100, 1) : 0;
            $op['drifttid_h']         = round($d / 60.0, 1);
            unset($op['drifttid_min'], $op['rasttime_min']);
        }
        unset($op);

        return $byOp;
    }

    private function queryOperatorWeeklyTrend(int $opNr, string $from, string $to): array {
        // GROUP BY skiftraknare deduplicates PLC multi-rows; HAVING >= 30 filters test records.
        $sql = "
            SELECT YEARWEEK(datum, 1) AS yw,
                   MIN(datum)         AS week_start,
                   COUNT(*)           AS antal_skift,
                   SUM(ibc_ok)        AS ibc_ok,
                   SUM(drifttid)      AS drifttid_min
            FROM (
                SELECT datum, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE op1 = :op AND datum BETWEEN :from AND :to
                GROUP BY skiftraknare, datum
                HAVING MAX(drifttid) >= 30
                UNION ALL
                SELECT datum, MAX(ibc_ok), MAX(drifttid)
                FROM rebotling_skiftrapport
                WHERE op2 = :op2 AND datum BETWEEN :from2 AND :to2
                GROUP BY skiftraknare, datum
                HAVING MAX(drifttid) >= 30
                UNION ALL
                SELECT datum, MAX(ibc_ok), MAX(drifttid)
                FROM rebotling_skiftrapport
                WHERE op3 = :op3 AND datum BETWEEN :from3 AND :to3
                GROUP BY skiftraknare, datum
                HAVING MAX(drifttid) >= 30
            ) combined
            GROUP BY yw
            ORDER BY yw ASC
            LIMIT 12
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':op' => $opNr, ':from' => $from, ':to' => $to,
            ':op2' => $opNr, ':from2' => $from, ':to2' => $to,
            ':op3' => $opNr, ':from3' => $from, ':to3' => $to,
        ]);
        return array_map(function($r) {
            $d = (int)$r['drifttid_min'];
            return [
                'week'        => $r['week_start'],
                'ibc_per_h'   => $d > 0 ? round((int)$r['ibc_ok'] / ($d / 60.0), 1) : 0,
                'antal_skift' => (int)$r['antal_skift'],
            ];
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // ─── FEATURE 1: Operator Reliability Scores ───────────────────────────────

    private function getOperatorScores(): void {
        try {
            $to   = $_GET['to']   ?? date('Y-m-d');
            $from = $_GET['from'] ?? date('Y-m-d', strtotime($to . ' -90 days'));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Operator names
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // Unika skift (en rad per skift via GROUP BY skiftraknare).
            // Linjen är EN linje: op1+op2+op3 jobbar simultant. IBC/h är linjens output,
            // inte summan av positionernas output. Korrekt: SUM(ibc)/SUM(tid), inte avg(kvoter).
            $stmtShifts = $this->pdo->prepare("
                SELECT skiftraknare, datum,
                       MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                       MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from AND :to AND drifttid > 0
                GROUP BY skiftraknare
                ORDER BY datum ASC
            ");
            $stmtShifts->execute([':from' => $from, ':to' => $to]);
            $allShifts = $stmtShifts->fetchAll(\PDO::FETCH_ASSOC);

            $teamTotalIbc = 0;
            $teamTotalMin = 0;
            $opTotals    = []; // num => ['ibc'=>int, 'min'=>int, 'count'=>int]
            $opPosTotals = []; // num => pos => ['ibc'=>int, 'min'=>int, 'count'=>int]
            $opWeekly    = []; // num => week_key => ['ibc'=>int, 'min'=>int]
            $opBestWorst = []; // num => [per-shift ibc_per_h for min/max]
            $teamPosTotals = ['op1'=>['ibc'=>0,'min'=>0], 'op2'=>['ibc'=>0,'min'=>0], 'op3'=>['ibc'=>0,'min'=>0]];

            foreach ($allShifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                $teamTotalIbc += $ibc;
                $teamTotalMin += $min;

                $shiftIbcH = round($ibc / ($min / 60.0), 2);
                $yw        = date('oW', strtotime($s['datum']));

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $num = (int)$s[$pos];
                    if ($num <= 0) continue;
                    if (!isset($opNames[$num])) { $opNames[$num] = "Operatör $num"; }

                    $opTotals[$num]['ibc']   = ($opTotals[$num]['ibc']   ?? 0) + $ibc;
                    $opTotals[$num]['min']   = ($opTotals[$num]['min']   ?? 0) + $min;
                    $opTotals[$num]['count'] = ($opTotals[$num]['count'] ?? 0) + 1;

                    $opPosTotals[$num][$pos]['ibc']   = ($opPosTotals[$num][$pos]['ibc']   ?? 0) + $ibc;
                    $opPosTotals[$num][$pos]['min']   = ($opPosTotals[$num][$pos]['min']   ?? 0) + $min;
                    $opPosTotals[$num][$pos]['count'] = ($opPosTotals[$num][$pos]['count'] ?? 0) + 1;

                    $opWeekly[$num][$yw]['ibc'] = ($opWeekly[$num][$yw]['ibc'] ?? 0) + $ibc;
                    $opWeekly[$num][$yw]['min'] = ($opWeekly[$num][$yw]['min'] ?? 0) + $min;

                    $opBestWorst[$num][] = $shiftIbcH;
                    $teamPosTotals[$pos]['ibc'] += $ibc;
                    $teamPosTotals[$pos]['min'] += $min;
                }
            }

            $teamAvgPerPos = [];
            foreach ($teamPosTotals as $pos => $t) {
                $teamAvgPerPos[$pos] = $t['min'] > 0 ? $t['ibc'] / ($t['min'] / 60.0) : 0;
            }
            $teamTotal = $teamTotalMin > 0 ? $teamTotalIbc / ($teamTotalMin / 60.0) : 1;

            $results = [];

            foreach ($opNames as $num => $name) {
                if (!isset($opTotals[$num])) continue;
                $tot = $opTotals[$num];
                if ($tot['count'] < 3) continue;

                $ibc_per_h = round($tot['ibc'] / ($tot['min'] / 60.0), 1);

                $posStats = [];
                foreach (($opPosTotals[$num] ?? []) as $pos => $pt) {
                    $posIbcH = $pt['min'] > 0 ? round($pt['ibc'] / ($pt['min'] / 60.0), 1) : 0;
                    $tAvg    = $teamAvgPerPos[$pos] ?? 0;
                    $posStats[$pos] = [
                        'ibc_per_h'   => $posIbcH,
                        'team_avg'    => round($tAvg, 1),
                        'antal_skift' => $pt['count'],
                        'vs_avg_pct'  => $tAvg > 0 ? round(($posIbcH / $tAvg - 1) * 100) : 0,
                    ];
                }

                // Position-weighted team baseline (same baseline as per-position stats)
                $wMin = 0; $wAvg = 0.0;
                foreach (array_keys($posStats) as $pos) {
                    $pm = $opPosTotals[$num][$pos]['min'] ?? 0;
                    $wAvg += ($teamAvgPerPos[$pos] ?? 0) * $pm;
                    $wMin += $pm;
                }
                $posWeightedTeamAvg = $wMin > 0 ? $wAvg / $wMin : $teamTotal;
                $vsAvgPct = $posWeightedTeamAvg > 0 ? round(($ibc_per_h / $posWeightedTeamAvg - 1) * 100) : 0;
                $score    = max(0, min(100, round(50 + ($ibc_per_h - $posWeightedTeamAvg) * 5)));
                $rating   = $vsAvgPct >= 15  ? 'Elite'
                          : ($vsAvgPct >= 0   ? 'Solid'
                          : ($vsAvgPct >= -15  ? 'Developing'
                          : 'Needs attention'));

                ksort($opWeekly[$num]);
                $weeklyVals = [];
                foreach ($opWeekly[$num] as $bucket) {
                    $weeklyVals[] = $bucket['min'] > 0 ? round($bucket['ibc'] / ($bucket['min'] / 60.0), 1) : 0;
                }
                $weeklyVals = array_slice($weeklyVals, -8);

                $shiftVals = $opBestWorst[$num] ?? [0];

                $results[] = [
                    'number'       => $num,
                    'name'         => $name,
                    'ibc_per_h'    => $ibc_per_h,
                    'team_avg'     => round($teamTotal, 1),
                    'vs_avg_pct'   => $vsAvgPct,
                    'score'        => (int)$score,
                    'rating'       => $rating,
                    'antal_skift'  => $tot['count'],
                    'best_shift'   => round(max($shiftVals), 1),
                    'worst_shift'  => round(min($shiftVals), 1),
                    'per_position' => $posStats,
                    'trend_weeks'  => $weeklyVals,
                ];
            }

            usort($results, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

            echo json_encode([
                'success' => true,
                'data' => [
                    'from'       => $from,
                    'to'         => $to,
                    'operatorer' => $results,
                    'team_avg_per_pos' => [
                        'op1' => round($teamAvgPerPos['op1'] ?? 0, 1),
                        'op2' => round($teamAvgPerPos['op2'] ?? 0, 1),
                        'op3' => round($teamAvgPerPos['op3'] ?? 0, 1),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorScores: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörspoäng'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE 4: Operator Matcher (scheduling matrix) ──────────────────────

    private function getOperatorMatcher(): void {
        try {
            $days = max(7, min(180, (int)($_GET['days'] ?? 30)));
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Operator names
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // Per-shift data per operator per position
            $sql = "
                SELECT op_nr, pos,
                       COUNT(*)       AS antal_skift,
                       SUM(ibc_ok)    AS ibc_ok,
                       SUM(drifttid)  AS drifttid_min
                FROM (
                    SELECT op1 AS op_nr, 'op1' AS pos, ibc_ok, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND op1 > 0 AND datum BETWEEN :from1 AND :to1 AND drifttid > 0
                    UNION ALL
                    SELECT op2, 'op2', ibc_ok, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op2 IS NOT NULL AND op2 > 0 AND datum BETWEEN :from2 AND :to2 AND drifttid > 0
                    UNION ALL
                    SELECT op3, 'op3', ibc_ok, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op3 IS NOT NULL AND op3 > 0 AND datum BETWEEN :from3 AND :to3 AND drifttid > 0
                ) s
                GROUP BY op_nr, pos
                HAVING antal_skift >= 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Collect all IBC/h per position for team averages
            $allPosData = ['op1' => [], 'op2' => [], 'op3' => []];
            $opPosData  = [];
            foreach ($rows as $row) {
                $num = (int)$row['op_nr'];
                $pos = $row['pos'];
                $d   = (int)$row['drifttid_min'];
                $ibc = (int)$row['ibc_ok'];
                $rate = $d > 0 ? $ibc / ($d / 60.0) : 0;
                $opPosData[$num][$pos] = [
                    'ibc_per_h'   => round($rate, 1),
                    'antal_skift' => (int)$row['antal_skift'],
                ];
                $allPosData[$pos][] = $rate;
            }

            // Team averages per position
            $teamAvg = [];
            foreach ($allPosData as $pos => $vals) {
                $teamAvg[$pos] = count($vals) > 0 ? array_sum($vals) / count($vals) : 0;
            }

            // Build result — for each operator, rating per position
            $results = [];
            foreach ($opNames as $num => $name) {
                if (!isset($opPosData[$num])) continue;
                $positions = [];
                foreach (['op1', 'op2', 'op3'] as $pos) {
                    if (!isset($opPosData[$num][$pos])) {
                        $positions[$pos] = null; // no data
                        continue;
                    }
                    $rate = $opPosData[$num][$pos]['ibc_per_h'];
                    $avg  = $teamAvg[$pos];
                    // rating: green = top 33% (>= 110% of avg), red = bottom 33% (< 90% of avg), else yellow
                    if ($avg > 0) {
                        $ratio = $rate / $avg;
                        $rating = $ratio >= 1.10 ? 'green' : ($ratio < 0.90 ? 'red' : 'yellow');
                    } else {
                        $rating = 'yellow';
                    }
                    $positions[$pos] = [
                        'ibc_per_h'   => $rate,
                        'antal_skift' => $opPosData[$num][$pos]['antal_skift'],
                        'team_avg'    => round($avg, 1),
                        'vs_avg_pct'  => $avg > 0 ? round(($rate / $avg - 1) * 100) : 0,
                        'rating'      => $rating,
                    ];
                }
                $results[] = [
                    'number'    => $num,
                    'name'      => $name,
                    'positions' => $positions,
                ];
            }

            // Sortera på totalt vägt IBC/h — det enda som räknas
            usort($results, function($a, $b) {
                $calcAvg = function($op) {
                    $sum = 0; $n = 0;
                    foreach ($op['positions'] as $p) {
                        if ($p) { $sum += $p['ibc_per_h'] * $p['antal_skift']; $n += $p['antal_skift']; }
                    }
                    return $n > 0 ? $sum / $n : 0;
                };
                return $calcAvg($b) <=> $calcAvg($a);
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'from'       => $from,
                    'to'         => $to,
                    'days'       => $days,
                    'operatorer' => $results,
                    'team_avg'   => [
                        'op1' => round($teamAvg['op1'], 1),
                        'op2' => round($teamAvg['op2'], 1),
                        'op3' => round($teamAvg['op3'], 1),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorMatcher: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsmatchning'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE 5: Shift DNA (skift-fingeravtryck flöde) ─────────────────────

    private function getShiftDna(): void {
        try {
            $limit    = max(10, min(200, (int)($_GET['limit']    ?? 50)));
            $offset   = max(0,          (int)($_GET['offset']   ?? 0));
            $opFilter = isset($_GET['operator']) && $_GET['operator'] !== '' ? (int)$_GET['operator'] : 0;

            // Operator names map
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // Team average IBC/h over last 90 days — baseline for relative comparison
            $stmtAvg = $this->pdo->query("
                SELECT SUM(ibc_ok) / NULLIF(SUM(drifttid / 60.0), 0) AS team_avg
                FROM rebotling_skiftrapport
                WHERE drifttid > 0 AND ibc_ok > 0
                  AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            ");
            $teamAvg = (float)($stmtAvg->fetchColumn() ?? 0);

            $opWhere    = $opFilter > 0 ? "AND (op1 = :op OR op2 = :op OR op3 = :op)" : '';
            $opWhereCnt = $opFilter > 0 ? "AND (op1 = :op OR op2 = :op OR op3 = :op)" : '';

            $sql = "
                SELECT skiftraknare, datum, op1, op2, op3, product_id,
                       ibc_ok, ibc_ej_ok, bur_ej_ok, totalt,
                       drifttid, rasttime, driftstopptime, created_at
                FROM rebotling_skiftrapport
                WHERE drifttid > 0
                $opWhere
                ORDER BY datum DESC, skiftraknare DESC
                LIMIT :lim OFFSET :off
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit,  \PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
            if ($opFilter > 0) $stmt->bindValue(':op', $opFilter, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $shifts = [];
            foreach ($rows as $row) {
                $dt  = (int)$row['drifttid'];
                $ibc = (int)$row['ibc_ok'];
                $ibcPerH = $dt > 0 ? round($ibc / ($dt / 60.0), 1) : 0;
                $vsAvg   = $teamAvg > 0 ? round(($ibcPerH / $teamAvg - 1) * 100) : 0;

                if ($teamAvg > 0 && $ibcPerH > 0) {
                    $ratio  = $ibcPerH / $teamAvg;
                    $rating = $ratio >= 1.20 ? 'great'
                            : ($ratio >= 1.05 ? 'good'
                            : ($ratio >= 0.90 ? 'avg'
                            : ($ratio >= 0.70 ? 'weak'
                            : 'poor')));
                } else {
                    $rating = $ibc > 0 ? 'avg' : 'poor';
                }

                $ops = [];
                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $num = (int)$row[$pos];
                    if ($num > 0) {
                        $ops[] = [
                            'number' => $num,
                            'name'   => $opNames[$num] ?? "Op $num",
                            'pos'    => $pos,
                        ];
                    }
                }

                $shifts[] = [
                    'skiftraknare'   => (int)$row['skiftraknare'],
                    'datum'          => $row['datum'],
                    'ops'            => $ops,
                    'product_id'     => (int)$row['product_id'],
                    'ibc_ok'         => $ibc,
                    'ibc_ej_ok'      => (int)$row['ibc_ej_ok'],
                    'bur_ej_ok'      => (int)$row['bur_ej_ok'],
                    'totalt'         => (int)$row['totalt'],
                    'ibc_per_h'      => $ibcPerH,
                    'vs_avg_pct'     => $vsAvg,
                    'drifttid'       => $dt,
                    'rasttime'       => (int)$row['rasttime'],
                    'driftstopptime' => (int)$row['driftstopptime'],
                    'rating'         => $rating,
                    'created_at'     => $row['created_at'],
                ];
            }

            // Total count for pagination
            $countSql = "SELECT COUNT(*) FROM rebotling_skiftrapport WHERE drifttid > 0 $opWhereCnt";
            $stmtCnt  = $this->pdo->prepare($countSql);
            if ($opFilter > 0) $stmtCnt->bindValue(':op', $opFilter, \PDO::PARAM_INT);
            $stmtCnt->execute();
            $total = (int)$stmtCnt->fetchColumn();

            // Operator list for filter dropdown
            $operators = [];
            foreach ($opNames as $num => $name) {
                $operators[] = ['number' => $num, 'name' => $name];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'shifts'    => $shifts,
                    'total'     => $total,
                    'limit'     => $limit,
                    'offset'    => $offset,
                    'team_avg'  => round($teamAvg, 1),
                    'operators' => $operators,
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getShiftDna: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid shift-dna'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorProfile(): void
    {
        try {
            $opNumber = (int)($_GET['op'] ?? 0);
            if ($opNumber <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt operatörsnummer'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $from = date('Y-m-d', strtotime('-6 months'));
            $to   = date('Y-m-d');

            // Operator info
            $stmtOp = $this->pdo->prepare("SELECT number, name FROM operators WHERE number = :num");
            $stmtOp->execute([':num' => $opNumber]);
            $opRow = $stmtOp->fetch(\PDO::FETCH_ASSOC);
            if (!$opRow) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Operatör hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // All shifts for this operator in period
            $stmtShifts = $this->pdo->prepare("
                SELECT skiftraknare, datum, op1, op2, op3,
                       ibc_ok, drifttid, driftstopptime
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from1 AND :to1
                  AND drifttid > 0
                  AND (op1 = :op1 OR op2 = :op2 OR op3 = :op3)
                ORDER BY datum ASC, skiftraknare ASC
            ");
            $stmtShifts->execute([
                ':from1' => $from, ':to1' => $to,
                ':op1' => $opNumber, ':op2' => $opNumber, ':op3' => $opNumber,
            ]);
            $opRows = $stmtShifts->fetchAll(\PDO::FETCH_ASSOC);

            // Team data for position averages and "effect on team"
            $stmtTeam = $this->pdo->prepare("
                SELECT datum, skiftraknare, op1, op2, op3, ibc_ok, drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from2 AND :to2
                  AND drifttid > 0
                ORDER BY datum ASC
            ");
            $stmtTeam->execute([':from2' => $from, ':to2' => $to]);
            $teamRows = $stmtTeam->fetchAll(\PDO::FETCH_ASSOC);

            // Compute team avg per position
            $posVals = ['op1' => [], 'op2' => [], 'op3' => []];
            foreach ($teamRows as $tr) {
                $dt = (int)$tr['drifttid'];
                if ($dt <= 0) continue;
                $ibcH = (int)$tr['ibc_ok'] / ($dt / 60.0);
                foreach (['op1', 'op2', 'op3'] as $pos) {
                    if ((int)$tr[$pos] > 0) $posVals[$pos][] = $ibcH;
                }
            }
            $teamAvg = [];
            foreach ($posVals as $pos => $vals) {
                $teamAvg[$pos] = count($vals) > 0 ? array_sum($vals) / count($vals) : 0;
            }

            // Process operator's shifts
            $shifts      = [];
            $posShiftVals = ['op1' => [], 'op2' => [], 'op3' => []];
            $opDates     = [];

            foreach ($opRows as $row) {
                $dt  = (int)$row['drifttid'];
                $ibc = (int)$row['ibc_ok'];
                if ($dt <= 0) continue;
                $ibcH = round($ibc / ($dt / 60.0), 1);

                $pos = null;
                if ((int)$row['op1'] === $opNumber) $pos = 'op1';
                elseif ((int)$row['op2'] === $opNumber) $pos = 'op2';
                elseif ((int)$row['op3'] === $opNumber) $pos = 'op3';
                if (!$pos) continue;

                $tAvg  = $teamAvg[$pos] ?? 0;
                $vsAvg = $tAvg > 0 ? (int)round(($ibcH / $tAvg - 1) * 100) : 0;

                $shifts[]           = [
                    'skiftraknare'   => (int)$row['skiftraknare'],
                    'datum'          => $row['datum'],
                    'pos'            => $pos,
                    'ibc_ok'         => $ibc,
                    'ibc_per_h'      => $ibcH,
                    'vs_team_avg'    => $vsAvg,
                    'drifttid'       => $dt,
                    'driftstopptime' => (int)$row['driftstopptime'],
                ];
                $posShiftVals[$pos][] = $ibcH;
                $opDates[$row['datum']] = true;
            }

            $opDates = array_keys($opDates);

            // Summary stats
            $allIbcH  = array_column($shifts, 'ibc_per_h');
            $avgIbcH  = count($allIbcH) > 0 ? round(array_sum($allIbcH) / count($allIbcH), 1) : 0;
            $bestShift  = count($allIbcH) > 0 ? round(max($allIbcH), 1) : 0;
            $worstShift = count($allIbcH) > 0 ? round(min($allIbcH), 1) : 0;

            $posCounts = array_map('count', $posShiftVals);
            arsort($posCounts);
            $mostCommonPos = (string)key($posCounts) ?? 'op1';

            // Position breakdown
            $posBreakdown = [];
            foreach (['op1', 'op2', 'op3'] as $pos) {
                $vals = $posShiftVals[$pos];
                if (count($vals) === 0) {
                    $posBreakdown[$pos] = null;
                    continue;
                }
                $posBreakdown[$pos] = [
                    'antal_skift'   => count($vals),
                    'avg_ibc_per_h' => round(array_sum($vals) / count($vals), 1),
                    'best'          => round(max($vals), 1),
                    'worst'         => round(min($vals), 1),
                    'team_avg'      => round($teamAvg[$pos], 1),
                ];
            }

            // Effect on team: team avg IBC/h on days operator worked vs did not
            $withOp    = [];
            $withoutOp = [];
            foreach ($teamRows as $tr) {
                $dt = (int)$tr['drifttid'];
                if ($dt <= 0) continue;
                $ibcH = (int)$tr['ibc_ok'] / ($dt / 60.0);
                if (in_array($tr['datum'], $opDates)) {
                    $withOp[] = $ibcH;
                } else {
                    $withoutOp[] = $ibcH;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'operator' => ['number' => (int)$opRow['number'], 'name' => $opRow['name']],
                    'period'   => ['from' => $from, 'to' => $to],
                    'summary'  => [
                        'antal_skift'     => count($shifts),
                        'attendance_days' => count($opDates),
                        'avg_ibc_per_h'   => $avgIbcH,
                        'best_shift'      => $bestShift,
                        'worst_shift'     => $worstShift,
                        'most_common_pos' => $mostCommonPos,
                    ],
                    'shifts'           => $shifts,
                    'pos_breakdown'    => $posBreakdown,
                    'team_avg_per_pos' => array_map(fn($v) => round($v, 1), $teamAvg),
                    'effect_on_team'   => [
                        'team_avg_with_op'    => count($withOp)    > 0 ? round(array_sum($withOp)    / count($withOp), 1)    : 0,
                        'team_avg_without_op' => count($withoutOp) > 0 ? round(array_sum($withoutOp) / count($withoutOp), 1) : 0,
                        'shift_count_with'    => count($withOp),
                        'shift_count_without' => count($withoutOp),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorProfile: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsprofil'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE 5: Operator Trend Heatmap ───────────────────────────────────

    private function getOperatorTrendHeatmap(): void
    {
        try {
            $weeks = min(24, max(4, (int)($_GET['weeks'] ?? 12)));

            // Build week-start dates (ISO Mondays), oldest first
            $weekStarts = [];
            $monday = new \DateTime();
            $dow = (int)$monday->format('N'); // 1=Mon … 7=Sun
            $monday->modify('-' . ($dow - 1) . ' days');
            $monday->modify('-' . ($weeks - 1) . ' weeks');
            for ($i = 0; $i < $weeks; $i++) {
                $weekStarts[] = $monday->format('Y-m-d');
                $monday->modify('+1 week');
            }

            $from = $weekStarts[0];
            $to   = date('Y-m-d');

            // Active operators
            $ops = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active=1 ORDER BY name"
            )->fetchAll(\PDO::FETCH_ASSOC);

            // All shifts in period — group by operator + week Monday
            $sql = "
                SELECT op_nr,
                       DATE(DATE_SUB(datum, INTERVAL (WEEKDAY(datum)) DAY)) AS week_monday,
                       COUNT(*)       AS antal_skift,
                       SUM(ibc_ok)    AS total_ibc,
                       SUM(drifttid)  AS total_drifttid
                FROM (
                    SELECT op1 AS op_nr, datum, ibc_ok, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 > 0 AND datum BETWEEN :from1 AND :to1 AND drifttid > 0
                    UNION ALL
                    SELECT op2, datum, ibc_ok, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op2 > 0 AND datum BETWEEN :from2 AND :to2 AND drifttid > 0
                    UNION ALL
                    SELECT op3, datum, ibc_ok, drifttid
                    FROM rebotling_skiftrapport
                    WHERE op3 > 0 AND datum BETWEEN :from3 AND :to3 AND drifttid > 0
                ) s
                GROUP BY op_nr, week_monday
                ORDER BY op_nr, week_monday
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Index by op_nr → week_monday
            $byOp = [];
            $teamByWeek = []; // week_monday => [ibc_per_h, ...]
            foreach ($rows as $r) {
                $num  = (int)$r['op_nr'];
                $wm   = $r['week_monday'];
                $d    = (int)$r['total_drifttid'];
                $iph  = $d > 0 ? round((int)$r['total_ibc'] / ($d / 60.0), 1) : 0.0;
                $byOp[$num][$wm] = [
                    'ibc_per_h'   => $iph,
                    'antal_skift' => (int)$r['antal_skift'],
                ];
                $teamByWeek[$wm][] = $iph;
            }

            // Team average IBC/h per week (across all operator slots)
            $teamAvgByWeek = [];
            foreach ($teamByWeek as $wm => $vals) {
                $teamAvgByWeek[$wm] = count($vals) > 0
                    ? round(array_sum($vals) / count($vals), 1)
                    : null;
            }

            // Build operator rows
            $opResults = [];
            foreach ($ops as $op) {
                $num = (int)$op['number'];
                if (!isset($byOp[$num])) continue;

                $cells   = [];
                $vsVals  = [];
                foreach ($weekStarts as $ws) {
                    $cell = $byOp[$num][$ws] ?? null;
                    if ($cell !== null) {
                        $ta  = $teamAvgByWeek[$ws] ?? null;
                        $pct = ($ta !== null && $ta > 0)
                            ? round(($cell['ibc_per_h'] - $ta) / $ta * 100, 1)
                            : null;
                        $cells[] = [
                            'week'        => $ws,
                            'ibc_per_h'   => $cell['ibc_per_h'],
                            'vs_team_pct' => $pct,
                            'antal_skift' => $cell['antal_skift'],
                        ];
                        if ($pct !== null) $vsVals[] = $pct;
                    } else {
                        $cells[] = [
                            'week'        => $ws,
                            'ibc_per_h'   => null,
                            'vs_team_pct' => null,
                            'antal_skift' => 0,
                        ];
                    }
                }

                // Trend: avg(last 4 active weeks) − avg(first 4 active weeks)
                $trendDir = null;
                $recent4  = array_filter(
                    array_slice(array_values(array_filter($vsVals, fn($v) => $v !== null)), -4),
                    fn($v) => true
                );
                $early4   = array_slice(array_values(array_filter($vsVals, fn($v) => $v !== null)), 0, 4);
                if (count($recent4) >= 2 && count($early4) >= 2) {
                    $trendDir = round(
                        array_sum($recent4) / count($recent4) -
                        array_sum($early4)  / count($early4),
                        1
                    );
                }

                // Recent avg IBC/h for sorting
                $recentCells = array_filter(
                    array_slice($cells, -4),
                    fn($c) => $c['ibc_per_h'] !== null
                );
                $recentAvg = count($recentCells) > 0
                    ? array_sum(array_column($recentCells, 'ibc_per_h')) / count($recentCells)
                    : -1;

                $opResults[] = [
                    'number'     => $num,
                    'name'       => $op['name'],
                    'cells'      => $cells,
                    'trend_dir'  => $trendDir,
                    'recent_avg' => round($recentAvg, 1),
                ];
            }

            // Sort by recent avg IBC/h descending
            usort($opResults, fn($a, $b) => $b['recent_avg'] <=> $a['recent_avg']);

            // Team avg row
            $teamAvgCells = [];
            foreach ($weekStarts as $ws) {
                $teamAvgCells[] = [
                    'week'      => $ws,
                    'ibc_per_h' => $teamAvgByWeek[$ws] ?? null,
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'weeks'          => $weekStarts,
                    'operators'      => $opResults,
                    'team_avg_cells' => $teamAvgCells,
                    'period'         => ['from' => $from, 'to' => $to],
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorTrendHeatmap: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid trendkarta'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorMonthlyReport(): void {
        try {
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt månadsformat'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $from    = $month . '-01';
            $to      = date('Y-m-t', strtotime($from));
            $prevFrom = date('Y-m-01', strtotime($from . ' -1 month'));
            $prevTo   = date('Y-m-t', strtotime($prevFrom));

            // Active operators
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // GROUP BY skiftraknare + HAVING drifttid >= 30 prevents bogus entries (test records
            // with drifttid=1-10 min) from inflating IBC/h and causing vs_team_pct > 1000%.
            $shiftSql = "
                SELECT op_nr, pos, ibc_ok, drifttid
                FROM (
                    SELECT op1 AS op_nr, 'op1' AS pos, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND op1 > 0 AND datum BETWEEN :from1 AND :to1
                    GROUP BY skiftraknare, op1
                    HAVING MAX(drifttid) >= 30
                    UNION ALL
                    SELECT op2 AS op_nr, 'op2' AS pos, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op2 IS NOT NULL AND op2 > 0 AND datum BETWEEN :from2 AND :to2
                    GROUP BY skiftraknare, op2
                    HAVING MAX(drifttid) >= 30
                    UNION ALL
                    SELECT op3 AS op_nr, 'op3' AS pos, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE op3 IS NOT NULL AND op3 > 0 AND datum BETWEEN :from3 AND :to3
                    GROUP BY skiftraknare, op3
                    HAVING MAX(drifttid) >= 30
                ) s
            ";

            // Current month
            $stmtCur = $this->pdo->prepare($shiftSql);
            $stmtCur->execute([':from1'=>$from,':to1'=>$to,':from2'=>$from,':to2'=>$to,':from3'=>$from,':to3'=>$to]);
            $curShifts = $stmtCur->fetchAll(\PDO::FETCH_ASSOC);

            // Previous month
            $stmtPrev = $this->pdo->prepare($shiftSql);
            $stmtPrev->execute([':from1'=>$prevFrom,':to1'=>$prevTo,':from2'=>$prevFrom,':to2'=>$prevTo,':from3'=>$prevFrom,':to3'=>$prevTo]);
            $prevShifts = $stmtPrev->fetchAll(\PDO::FETCH_ASSOC);

            // Process current month per operator
            // Track raw ibc/min per position for SUM/SUM aggregation (not AVG-of-ratios)
            $opData    = [];
            $posTeamRaw = ['op1'=>['ibc'=>0,'min'=>0],'op2'=>['ibc'=>0,'min'=>0],'op3'=>['ibc'=>0,'min'=>0]];
            foreach ($curShifts as $row) {
                $num = (int)$row['op_nr'];
                $ibc = (int)$row['ibc_ok'];
                $min = (int)$row['drifttid'];
                $h   = $min / 60.0;
                $iph = $h > 0 ? $ibc / $h : 0;
                $pos = $row['pos'];
                if (!isset($opData[$num])) {
                    $opData[$num] = [
                        'shifts'    => [],
                        'positions' => ['op1'=>['ibc'=>0,'min'=>0,'count'=>0],'op2'=>['ibc'=>0,'min'=>0,'count'=>0],'op3'=>['ibc'=>0,'min'=>0,'count'=>0]],
                    ];
                }
                $opData[$num]['shifts'][]              = ['iph' => $iph, 'ibc' => $ibc, 'h' => $h];
                $opData[$num]['positions'][$pos]['ibc']   += $ibc;
                $opData[$num]['positions'][$pos]['min']   += $min;
                $opData[$num]['positions'][$pos]['count'] += 1;
                $posTeamRaw[$pos]['ibc'] += $ibc;
                $posTeamRaw[$pos]['min'] += $min;
            }

            // Team averages per position (SUM/SUM — not AVG-of-ratios)
            $teamAvgByPos = [];
            foreach ($posTeamRaw as $pos => $raw) {
                $teamAvgByPos[$pos] = $raw['min'] > 0 ? round($raw['ibc'] / ($raw['min'] / 60.0), 2) : null;
            }

            // Process previous month (totals only)
            $prevData = [];
            foreach ($prevShifts as $row) {
                $num = (int)$row['op_nr'];
                $h   = (int)$row['drifttid'] / 60.0;
                if (!isset($prevData[$num])) { $prevData[$num] = ['ibc'=>0,'h'=>0]; }
                $prevData[$num]['ibc'] += (int)$row['ibc_ok'];
                $prevData[$num]['h']   += $h;
            }

            // Build result rows
            $results = [];
            foreach ($opData as $num => $d) {
                if (count($d['shifts']) === 0) continue;

                $name       = $opNames[$num] ?? "Op $num";
                $totalIbc   = array_sum(array_column($d['shifts'], 'ibc'));
                $totalH     = array_sum(array_column($d['shifts'], 'h'));
                $ibcPerH    = $totalH > 0 ? round($totalIbc / $totalH, 2) : 0;
                $antalSkift = count($d['shifts']);
                $allIph     = array_column($d['shifts'], 'iph');
                $bestShift  = count($allIph) > 0 ? round(max($allIph), 2) : null;
                $worstShift = count($allIph) > 0 ? round(min($allIph), 2) : null;

                // Per-position breakdown (SUM/SUM)
                $posBreakdown = [];
                $primaryPos   = null;
                $maxPosShifts = 0;
                foreach ($d['positions'] as $pos => $p) {
                    if ($p['count'] > 0) {
                        $posBreakdown[$pos] = [
                            'shifts'    => $p['count'],
                            'ibc_per_h' => $p['min'] > 0 ? round($p['ibc'] / ($p['min'] / 60.0), 2) : 0,
                        ];
                        if ($p['count'] > $maxPosShifts) {
                            $maxPosShifts = $p['count'];
                            $primaryPos   = $pos;
                        }
                    }
                }

                // vs team: SUM/SUM per position, weighted avg across positions
                $vsVals = [];
                foreach ($d['positions'] as $pos => $p) {
                    $ta = $teamAvgByPos[$pos] ?? null;
                    if ($p['count'] > 0 && $ta !== null && $ta > 0 && $p['min'] > 0) {
                        $opPosH = $p['ibc'] / ($p['min'] / 60.0);
                        $vsVals[] = $opPosH / $ta * 100;
                    }
                }
                $vsTeam = count($vsVals) > 0 ? round(array_sum($vsVals) / count($vsVals), 1) : null;

                // Tier
                $tier = null;
                if ($vsTeam !== null) {
                    if ($vsTeam >= 115)     $tier = 'Elite';
                    elseif ($vsTeam >= 100) $tier = 'Solid';
                    elseif ($vsTeam >= 85)  $tier = 'Developing';
                    else                    $tier = 'Behöver stöd';
                }

                // Previous month delta
                $prevIbcPerH = null;
                $delta       = null;
                if (isset($prevData[$num]) && $prevData[$num]['h'] > 0) {
                    $prevIbcPerH = round($prevData[$num]['ibc'] / $prevData[$num]['h'], 2);
                    $delta       = round($ibcPerH - $prevIbcPerH, 2);
                }

                $results[] = [
                    'number'          => $num,
                    'name'            => $name,
                    'antal_skift'     => $antalSkift,
                    'ibc_totalt'      => $totalIbc,
                    'ibc_per_h'       => $ibcPerH,
                    'vs_team_pct'     => $vsTeam,
                    'tier'            => $tier,
                    'primary_pos'     => $primaryPos,
                    'pos_breakdown'   => $posBreakdown,
                    'best_shift_ibc_h'  => $bestShift,
                    'worst_shift_ibc_h' => $worstShift,
                    'prev_ibc_per_h'  => $prevIbcPerH,
                    'delta_ibc_per_h' => $delta,
                ];
            }

            usort($results, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

            $allIbcPerH = array_filter(array_column($results, 'ibc_per_h'), fn($v) => $v > 0);
            $summary = [
                'total_ibc'        => array_sum(array_column($results, 'ibc_totalt')),
                'team_avg_ibc_h'   => count($allIbcPerH) > 0 ? round(array_sum($allIbcPerH) / count($allIbcPerH), 2) : null,
                'total_skift'      => array_sum(array_column($results, 'antal_skift')),
                'antal_operatorer' => count($results),
                'top_performer'    => count($results) > 0 ? $results[0]['name'] : null,
            ];

            echo json_encode([
                'success'   => true,
                'data'      => [
                    'month'           => $month,
                    'prev_month'      => date('Y-m', strtotime($from . ' -1 month')),
                    'operators'       => $results,
                    'summary'         => $summary,
                    'team_avg_by_pos' => $teamAvgByPos,
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorMonthlyReport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid månadsrapport'], JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=operator-kvartal&quarter=2026Q2
    private function getOperatorKvartalReport(): void {
        try {
            // Parse quarter param: "2026Q2"
            $qParam = $_GET['quarter'] ?? '';
            if (!preg_match('/^(\d{4})Q([1-4])$/', $qParam, $m)) {
                // Default to current quarter
                $year = (int)date('Y');
                $qNum = (int)ceil((int)date('n') / 3);
            } else {
                $year = (int)$m[1];
                $qNum = (int)$m[2];
            }

            $quarterBounds = [
                1 => ['start' => '01-01', 'end' => '03-31', 'months' => [1,2,3]],
                2 => ['start' => '04-01', 'end' => '06-30', 'months' => [4,5,6]],
                3 => ['start' => '07-01', 'end' => '09-30', 'months' => [7,8,9]],
                4 => ['start' => '10-01', 'end' => '12-31', 'months' => [10,11,12]],
            ];

            $bounds  = $quarterBounds[$qNum];
            $from    = "{$year}-{$bounds['start']}";
            $to      = "{$year}-{$bounds['end']}";
            $months  = $bounds['months'];

            // All active operators
            $opRows = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $operatorNames = [];
            foreach ($opRows as $r) {
                $operatorNames[(int)$r['number']] = $r['name'];
            }

            // All shifts in quarter (one row per skiftraknare via MAX/GROUP BY)
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)      AS op1,
                    MAX(op2)      AS op2,
                    MAX(op3)      AS op3,
                    MAX(ibc_ok)   AS ibc_ok,
                    MAX(drifttid) AS drifttid,
                    datum
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to
                  AND drifttid > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Team totals (for overall team avg IBC/h)
            $teamTotalIbc = 0;
            $teamTotalMin = 0;
            foreach ($shifts as $s) {
                $teamTotalIbc += max(0, (int)$s['ibc_ok']);
                $teamTotalMin += max(0, (int)$s['drifttid']);
            }
            $teamAvgIbcH = $teamTotalMin > 0
                ? round($teamTotalIbc / ($teamTotalMin / 60.0), 2)
                : null;

            // Build per-operator per-month data
            // $opData[opNum][mo] = ['ibc'=>int, 'min'=>int, 'skift'=>int]
            $opData = [];
            foreach ($shifts as $s) {
                $mo  = (int)date('n', strtotime($s['datum']));
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;
                    if (!isset($opData[$opNum][$mo])) {
                        $opData[$opNum][$mo] = ['ibc' => 0, 'min' => 0, 'skift' => 0];
                    }
                    $opData[$opNum][$mo]['ibc']   += $ibc;
                    $opData[$opNum][$mo]['min']   += $min;
                    $opData[$opNum][$mo]['skift'] += 1;
                }
            }

            $results = [];
            foreach ($opData as $opNum => $moData) {
                $totalIbc   = 0;
                $totalMin   = 0;
                $totalSkift = 0;
                $monthBreakdown = [];

                foreach ($months as $mo) {
                    if (isset($moData[$mo])) {
                        $d = $moData[$mo];
                        $ibcH = $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 2) : null;
                        $monthBreakdown[$mo] = ['ibc_per_h' => $ibcH, 'skift' => $d['skift']];
                        $totalIbc   += $d['ibc'];
                        $totalMin   += $d['min'];
                        $totalSkift += $d['skift'];
                    } else {
                        $monthBreakdown[$mo] = ['ibc_per_h' => null, 'skift' => 0];
                    }
                }

                if ($totalSkift < 3) continue;

                $ibcPerH = $totalMin > 0 ? round($totalIbc / ($totalMin / 60.0), 2) : null;
                $vsTeam  = ($ibcPerH !== null && $teamAvgIbcH !== null && $teamAvgIbcH > 0)
                    ? round($ibcPerH / $teamAvgIbcH * 100, 1)
                    : null;

                $tier = null;
                if ($vsTeam !== null) {
                    if ($vsTeam >= 115)     $tier = 'Elite';
                    elseif ($vsTeam >= 100) $tier = 'Solid';
                    elseif ($vsTeam >= 85)  $tier = 'Developing';
                    else                    $tier = 'Behöver stöd';
                }

                // Trend: compare last vs first month of quarter
                $m1val = $monthBreakdown[$months[0]]['ibc_per_h'];
                $m3val = $monthBreakdown[$months[2]]['ibc_per_h'];
                $trend = null;
                if ($m1val !== null && $m3val !== null && $m1val > 0) {
                    $delta = ($m3val - $m1val) / $m1val * 100;
                    if ($delta >= 5)      $trend = 'improving';
                    elseif ($delta <= -5) $trend = 'declining';
                    else                  $trend = 'stable';
                } elseif ($m1val === null && $m3val !== null) {
                    $trend = 'improving';
                } elseif ($m1val !== null && $m3val === null) {
                    $trend = 'declining';
                }

                $bonus = match ($tier) {
                    'Elite'        => 'Bonusnivå A',
                    'Solid'        => 'Bonusnivå B',
                    'Developing'   => 'Bonusnivå C',
                    'Behöver stöd' => 'Ingen bonus',
                    default        => null,
                };

                $results[] = [
                    'number'          => $opNum,
                    'name'            => $operatorNames[$opNum],
                    'antal_skift'     => $totalSkift,
                    'ibc_per_h'       => $ibcPerH,
                    'vs_team_pct'     => $vsTeam,
                    'tier'            => $tier,
                    'trend'           => $trend,
                    'bonus'           => $bonus,
                    'month_breakdown' => $monthBreakdown,
                ];
            }

            usort($results, fn($a, $b) => ($b['ibc_per_h'] ?? 0) <=> ($a['ibc_per_h'] ?? 0));

            // Summary
            $allIbcH = array_filter(array_column($results, 'ibc_per_h'));
            $summary = [
                'antal_operatorer' => count($results),
                'team_avg_ibc_h'   => $teamAvgIbcH,
                'top_performer'    => count($results) > 0 ? $results[0]['name'] : null,
                'total_skift'      => array_sum(array_column($results, 'antal_skift')),
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'quarter'        => "Q{$qNum} {$year}",
                    'quarter_code'   => "{$year}Q{$qNum}",
                    'months'         => $months,
                    'from'           => $from,
                    'to'             => $to,
                    'operators'      => $results,
                    'team_avg_ibc_h' => $teamAvgIbcH,
                    'summary'        => $summary,
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorKvartalReport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kvartalsutvärdering'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Prestandakarta: scatter-diagram IBC/h vs kassationsgrad per operatör.
     * Delar in operatörer i 4 kvadranter: Stjärna/Snabb/Noggrann/Utmanad.
     * GET ?action=rebotling&run=operator-performance-map&days=90
     */
    private function getOperatorPerformanceMap(): void {
        try {
            $days = max(14, min(365, (int)($_GET['days'] ?? 90)));
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            // Active operators
            $opRows = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1"
            )->fetchAll(\PDO::FETCH_ASSOC);
            $operatorNames = [];
            foreach ($opRows as $r) {
                $operatorNames[(int)$r['number']] = $r['name'];
            }

            // One row per skiftraknare (deduplicated)
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)       AS op1,
                    MAX(op2)       AS op2,
                    MAX(op3)       AS op3,
                    MAX(ibc_ok)    AS ibc_ok,
                    MAX(ibc_ej_ok) AS ibc_ej_ok,
                    MAX(drifttid)  AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to
                  AND drifttid > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $opStats  = [];
            $teamIbc  = 0;
            $teamEjOk = 0;
            $teamMin  = 0;

            foreach ($shifts as $s) {
                $ibc  = max(0, (int)$s['ibc_ok']);
                $ejOk = max(0, (int)$s['ibc_ej_ok']);
                $min  = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                $teamIbc  += $ibc;
                $teamEjOk += $ejOk;
                $teamMin  += $min;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;
                    if (!isset($opStats[$opNum])) {
                        $opStats[$opNum] = ['ibc' => 0, 'ej_ok' => 0, 'min' => 0, 'skift' => 0];
                    }
                    $opStats[$opNum]['ibc']   += $ibc;
                    $opStats[$opNum]['ej_ok'] += $ejOk;
                    $opStats[$opNum]['min']   += $min;
                    $opStats[$opNum]['skift'] += 1;
                }
            }

            $teamIbcH      = $teamMin > 0 ? $teamIbc / ($teamMin / 60.0) : 0;
            $teamTotal     = $teamIbc + $teamEjOk;
            $teamRejectRate = $teamTotal > 0 ? round($teamEjOk / $teamTotal * 100, 2) : 0;

            $results = [];
            foreach ($opStats as $opNum => $d) {
                if ($d['skift'] < 3) continue;

                $ibcH    = $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 2) : null;
                $total   = $d['ibc'] + $d['ej_ok'];
                $rejectRate = $total > 0 ? round($d['ej_ok'] / $total * 100, 2) : 0.0;

                $vsTeam = ($ibcH !== null && $teamIbcH > 0)
                    ? round($ibcH / $teamIbcH * 100, 1)
                    : null;

                $isHighSpeed = $vsTeam !== null && $vsTeam >= 100.0;
                $isLowReject = $rejectRate <= $teamRejectRate;
                if ($isHighSpeed && $isLowReject)   $quadrant = 'stjarna';
                elseif ($isHighSpeed)               $quadrant = 'snabb';
                elseif ($isLowReject)               $quadrant = 'noggrann';
                else                                $quadrant = 'utmanad';

                $results[] = [
                    'op_number'   => $opNum,
                    'name'        => $operatorNames[$opNum],
                    'ibc_per_h'   => $ibcH,
                    'vs_team'     => $vsTeam,
                    'reject_rate' => $rejectRate,
                    'antal_skift' => $d['skift'],
                    'quadrant'    => $quadrant,
                ];
            }

            usort($results, fn($a, $b) => strcmp($a['name'], $b['name']));

            echo json_encode([
                'success'          => true,
                'operators'        => $results,
                'team_ibc_per_h'   => round($teamIbcH, 2),
                'team_reject_rate' => $teamRejectRate,
                'period_days'      => $days,
                'from'             => $from,
                'to'               => $to,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorPerformanceMap: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid prestandakarta'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorAktivitet(): void {
        try {
            $weeks = isset($_GET['weeks']) ? max(4, min(24, (int)$_GET['weeks'])) : 12;
            $fromTs = strtotime("-{$weeks} weeks");
            $from   = date('Y-m-d', $fromTs);
            $to     = date('Y-m-d');

            // Active operators
            $opStmt = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            );
            $operatorNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $operatorNames[(int)$op['number']] = $op['name'];
            }

            // Deduplicated shifts
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)       AS op1,
                    MAX(op2)       AS op2,
                    MAX(op3)       AS op3,
                    MAX(ibc_ok)    AS ibc_ok,
                    MAX(drifttid)  AS drifttid,
                    MAX(datum)     AS datum
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to AND drifttid > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build list of ISO week keys (YYYYWW) for the period
            $allWeeks = [];
            $cur = $fromTs;
            while ($cur <= strtotime($to)) {
                $wk = date('o', $cur) . date('W', $cur);
                if (!in_array($wk, $allWeeks)) $allWeeks[] = $wk;
                $cur = strtotime('+1 week', $cur);
            }
            $wkLast = date('o', strtotime($to)) . date('W', strtotime($to));
            if (!in_array($wkLast, $allWeeks)) $allWeeks[] = $wkLast;
            sort($allWeeks);
            $totalWeeks = count($allWeeks);
            $weekIndex  = array_flip($allWeeks); // weekKey => index

            // Per-operator accumulation
            $opWeekShifts = []; // [opNum][weekIdx] => shift count
            $opTotals     = []; // [opNum] => [shifts, ibc, min]

            foreach ($shifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                $ts   = strtotime($s['datum']);
                $wKey = date('o', $ts) . date('W', $ts);
                $wIdx = $weekIndex[$wKey] ?? null;
                if ($wIdx === null) continue;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;

                    if (!isset($opWeekShifts[$opNum])) {
                        $opWeekShifts[$opNum] = array_fill(0, $totalWeeks, 0);
                        $opTotals[$opNum]     = ['shifts' => 0, 'ibc' => 0, 'min' => 0];
                    }
                    $opWeekShifts[$opNum][$wIdx]++;
                    $opTotals[$opNum]['shifts']++;
                    $opTotals[$opNum]['ibc'] += $ibc;
                    $opTotals[$opNum]['min'] += $min;
                }
            }

            $results = [];
            foreach ($opTotals as $opNum => $tot) {
                if ($tot['shifts'] < 1) continue;

                $weekly      = $opWeekShifts[$opNum];
                $activeWeeks = count(array_filter($weekly, fn($c) => $c > 0));
                $reliability = $totalWeeks > 0 ? round($activeWeeks / $totalWeeks * 100, 1) : 0;
                $ibcH        = $tot['min'] > 0 ? round($tot['ibc'] / ($tot['min'] / 60.0), 2) : null;

                // Trend: compare avg shifts/week in first vs second half
                $half       = (int)ceil($totalWeeks / 2);
                $firstHalf  = array_slice($weekly, 0, $half);
                $secondHalf = array_slice($weekly, $half);
                $avgFirst   = $half > 0 ? array_sum($firstHalf)  / $half : 0;
                $avgSecond  = ($totalWeeks - $half) > 0 ? array_sum($secondHalf) / ($totalWeeks - $half) : 0;

                if ($avgFirst > 0 && $avgSecond > $avgFirst * 1.15)       $trend = 'okar';
                elseif ($avgFirst > 0 && $avgSecond < $avgFirst * 0.85)   $trend = 'minskar';
                elseif ($avgFirst == 0 && $avgSecond > 0)                  $trend = 'okar';
                else                                                        $trend = 'stabil';

                // Activity badge
                $avgShiftsPerWeek = $tot['shifts'] / $totalWeeks;
                if ($avgShiftsPerWeek >= 3.5)       $badge = 'flitig';
                elseif ($avgShiftsPerWeek >= 1.5)   $badge = 'normal';
                else                                 $badge = 'sallan';

                $results[] = [
                    'op_number'    => $opNum,
                    'name'         => $operatorNames[$opNum],
                    'total_shifts' => $tot['shifts'],
                    'active_weeks' => $activeWeeks,
                    'reliability'  => $reliability,
                    'ibc_per_h'    => $ibcH,
                    'trend'        => $trend,
                    'badge'        => $badge,
                    'weekly'       => $weekly,
                ];
            }

            usort($results, fn($a, $b) => $b['total_shifts'] - $a['total_shifts']);

            echo json_encode([
                'success'      => true,
                'operators'    => $results,
                'weeks'        => $allWeeks,
                'total_weeks'  => $totalWeeks,
                'period_weeks' => $weeks,
                'from'         => $from,
                'to'           => $to,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorAktivitet: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsaktivitet'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE 11: Operator Head-to-Head Comparison ────────────────────────

    private function getOperatorCompare(): void
    {
        try {
            $opA  = (int)($_GET['op_a'] ?? 0);
            $opB  = (int)($_GET['op_b'] ?? 0);
            $days = max(14, min(365, (int)($_GET['days'] ?? 90)));

            $to     = date('Y-m-d');
            $from   = date('Y-m-d', strtotime("-{$days} days"));
            $from6m = date('Y-m-d', strtotime('-6 months'));

            // Operator list (always returned for the dropdowns)
            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opRows  = $stmtOps->fetchAll(\PDO::FETCH_ASSOC);
            $opNames = [];
            foreach ($opRows as $r) $opNames[(int)$r['number']] = $r['name'];

            if ($opA <= 0 || $opB <= 0 || $opA === $opB) {
                echo json_encode(['success' => true, 'operators' => $opRows, 'op_a' => null, 'op_b' => null], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Main period: unique shifts via GROUP BY skiftraknare
            $stmt = $this->pdo->prepare("
                SELECT skiftraknare, datum,
                       MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                       MAX(ibc_ok) AS ibc_ok, MAX(ibc_ej_ok) AS ibc_ej_ok,
                       MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from AND :to AND drifttid > 0
                GROUP BY skiftraknare
                ORDER BY datum ASC
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 6-month window for monthly trend charts
            $stmt6m = $this->pdo->prepare("
                SELECT skiftraknare, datum,
                       MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                       MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from6m AND :to6m AND drifttid > 0
                GROUP BY skiftraknare
                ORDER BY datum ASC
            ");
            $stmt6m->execute([':from6m' => $from6m, ':to6m' => $to]);
            $shifts6m = $stmt6m->fetchAll(\PDO::FETCH_ASSOC);

            // Team averages (main period)
            $tPosIbc = ['op1' => 0, 'op2' => 0, 'op3' => 0];
            $tPosMin = ['op1' => 0, 'op2' => 0, 'op3' => 0];
            $tIbc = 0; $tMin = 0;
            foreach ($allShifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']); $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;
                $tIbc += $ibc; $tMin += $min;
                foreach (['op1', 'op2', 'op3'] as $p) {
                    if ((int)$s[$p] > 0) { $tPosIbc[$p] += $ibc; $tPosMin[$p] += $min; }
                }
            }
            $teamAvgPerPos = [];
            foreach (['op1', 'op2', 'op3'] as $p) {
                $teamAvgPerPos[$p] = $tPosMin[$p] > 0 ? round($tPosIbc[$p] / ($tPosMin[$p] / 60.0), 1) : 0;
            }
            $teamAvgIbcH = $tMin > 0 ? round($tIbc / ($tMin / 60.0), 1) : 0;

            // Build stats for a single operator from shift data
            $buildStats = function (int $opNum, array $shifts, array $teamAvgPerPos, float $teamAvgIbcH, int $days): ?array {
                $totIbc = 0; $totMin = 0; $totKas = 0; $totTot = 0;
                $perShiftH = [];
                $posIbc = []; $posMin = []; $posCount = [];
                $weekData = []; $activeDates = [];

                foreach ($shifts as $s) {
                    $ibc = max(0, (int)$s['ibc_ok']); $min = max(0, (int)$s['drifttid']);
                    if ($min === 0) continue;
                    foreach (['op1', 'op2', 'op3'] as $p) {
                        if ((int)$s[$p] !== $opNum) continue;
                        $ibcH = $ibc / ($min / 60.0);
                        $totIbc += $ibc; $totMin += $min;
                        $totKas += max(0, (int)($s['ibc_ej_ok'] ?? 0));
                        $totTot += $ibc + max(0, (int)($s['ibc_ej_ok'] ?? 0));
                        $perShiftH[] = $ibcH;
                        $posIbc[$p]   = ($posIbc[$p]   ?? 0) + $ibc;
                        $posMin[$p]   = ($posMin[$p]   ?? 0) + $min;
                        $posCount[$p] = ($posCount[$p] ?? 0) + 1;
                        $activeDates[$s['datum']] = true;
                        $wk = date('oW', strtotime($s['datum']));
                        $weekData[$wk]['ibc'] = ($weekData[$wk]['ibc'] ?? 0) + $ibc;
                        $weekData[$wk]['min'] = ($weekData[$wk]['min'] ?? 0) + $min;
                    }
                }

                if (count($perShiftH) < 1) return null;

                $ibcPerH = $totMin > 0 ? round($totIbc / ($totMin / 60.0), 1) : 0;
                $vsTeam  = $teamAvgIbcH > 0 ? round(($ibcPerH / $teamAvgIbcH - 1) * 100, 1) : 0;
                $tier    = $vsTeam >= 15 ? 'Elite' : ($vsTeam >= 0 ? 'Solid' : ($vsTeam >= -15 ? 'Developing' : 'Behöver stöd'));

                // Consistency via coefficient of variation
                $mean = array_sum($perShiftH) / count($perShiftH);
                $var  = 0;
                foreach ($perShiftH as $v) $var += ($v - $mean) ** 2;
                $stddev = count($perShiftH) > 1 ? sqrt($var / count($perShiftH)) : 0;
                $cv     = $mean > 0 ? $stddev / $mean : 0;
                $consistency = (int)round(max(0, min(100, (1 - $cv) * 100)));

                // Trend: first-half vs second-half weekly IBC/h
                ksort($weekData);
                $weekVals = [];
                foreach ($weekData as $b) {
                    if ($b['min'] > 0) $weekVals[] = round($b['ibc'] / ($b['min'] / 60.0), 1);
                }
                $nw = count($weekVals);
                $avgFirst = $avgSecond = 0;
                if ($nw >= 2) {
                    $half = (int)ceil($nw / 2);
                    $fh = array_slice($weekVals, 0, $half); $sh = array_slice($weekVals, $half);
                    $avgFirst  = $half > 0 ? array_sum($fh) / $half : 0;
                    $avgSecond = count($sh) > 0 ? array_sum($sh) / count($sh) : 0;
                }
                $trendSlope = $avgFirst > 0 ? round(($avgSecond - $avgFirst) / $avgFirst * 100, 1) : 0;
                $trendDir   = $trendSlope >= 5 ? 'okar' : ($trendSlope <= -5 ? 'minskar' : 'stabil');

                // Per-position breakdown
                $posBreakdown = [];
                foreach (['op1', 'op2', 'op3'] as $p) {
                    if (empty($posCount[$p])) { $posBreakdown[$p] = null; continue; }
                    $pIbcH = $posMin[$p] > 0 ? round($posIbc[$p] / ($posMin[$p] / 60.0), 1) : 0;
                    $tAvg  = $teamAvgPerPos[$p] ?? 0;
                    $posBreakdown[$p] = [
                        'ibc_per_h'   => $pIbcH,
                        'team_avg'    => round($tAvg, 1),
                        'antal_skift' => $posCount[$p],
                        'vs_avg_pct'  => $tAvg > 0 ? round(($pIbcH / $tAvg - 1) * 100, 1) : 0,
                    ];
                }

                // Radar scores (all 0–100)
                $totalShifts   = count($perShiftH);
                $shiftsPerWeek = max(1.0, $days / 7.0);
                $narvaro       = (int)round(min(100, $totalShifts / $shiftsPerWeek * 20)); // 5/week = 100

                return [
                    'ibc_per_h'       => $ibcPerH,
                    'vs_team_pct'     => $vsTeam,
                    'tier'            => $tier,
                    'total_shifts'    => $totalShifts,
                    'active_days'     => count($activeDates),
                    'consistency'     => $consistency,
                    'trend_direction' => $trendDir,
                    'trend_slope'     => $trendSlope,
                    'best_shift'      => round(max($perShiftH), 1),
                    'worst_shift'     => round(min($perShiftH), 1),
                    'kassation_pct'   => $totTot > 0 ? round($totKas / $totTot * 100, 1) : null,
                    'per_position'    => $posBreakdown,
                    'weekly_vals'     => array_slice($weekVals, -12),
                    'radar'           => [
                        'fart'       => (int)round(max(0, min(100, 50 + $vsTeam))),
                        'konsistens' => $consistency,
                        'trend'      => (int)round(max(0, min(100, 50 + $trendSlope))),
                        'narvaro'    => $narvaro,
                    ],
                ];
            };

            // Build monthly IBC/h series (6 months) for trend chart
            $buildMonthly = function (int $opNum, array $shifts): array {
                $byMonth = [];
                foreach ($shifts as $s) {
                    $ibc = max(0, (int)$s['ibc_ok']); $min = max(0, (int)$s['drifttid']);
                    if ($min === 0) continue;
                    foreach (['op1', 'op2', 'op3'] as $p) {
                        if ((int)$s[$p] !== $opNum) continue;
                        $m = substr($s['datum'], 0, 7);
                        $byMonth[$m]['ibc']   = ($byMonth[$m]['ibc']   ?? 0) + $ibc;
                        $byMonth[$m]['min']   = ($byMonth[$m]['min']   ?? 0) + $min;
                        $byMonth[$m]['count'] = ($byMonth[$m]['count'] ?? 0) + 1;
                    }
                }
                ksort($byMonth);
                $result = [];
                foreach ($byMonth as $m => $d) {
                    $result[] = [
                        'month'     => $m,
                        'ibc_per_h' => $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 1) : 0,
                        'shifts'    => $d['count'],
                    ];
                }
                return $result;
            };

            $statsA = $buildStats($opA, $allShifts, $teamAvgPerPos, $teamAvgIbcH, $days);
            $statsB = $buildStats($opB, $allShifts, $teamAvgPerPos, $teamAvgIbcH, $days);

            if ($statsA) {
                $statsA['name']    = $opNames[$opA] ?? "Op $opA";
                $statsA['number']  = $opA;
                $statsA['monthly'] = $buildMonthly($opA, $shifts6m);
            }
            if ($statsB) {
                $statsB['name']    = $opNames[$opB] ?? "Op $opB";
                $statsB['number']  = $opB;
                $statsB['monthly'] = $buildMonthly($opB, $shifts6m);
            }

            echo json_encode([
                'success'          => true,
                'operators'        => $opRows,
                'op_a'             => $statsA,
                'op_b'             => $statsB,
                'team_avg_ibc_h'   => $teamAvgIbcH,
                'team_avg_per_pos' => $teamAvgPerPos,
                'days'             => $days,
                'from'             => $from,
                'to'               => $to,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorCompare: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsjämförelse'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getBonusKalkylator(): void
    {
        try {
            $pdo  = $this->pdo;
            $from = $_GET['from'] ?? date('Y-m-01');
            $to   = $_GET['to']   ?? date('Y-m-t');

            // Unique shifts in period
            $stmt = $pdo->prepare(
                "SELECT skiftraknare,
                        MAX(datum) as datum,
                        MAX(op1) as op1,
                        MAX(op2) as op2,
                        MAX(op3) as op3,
                        MAX(COALESCE(ibc_ok,0)) as ibc_ok,
                        MAX(COALESCE(drifttid,0)) as drifttid
                 FROM rebotling_skiftrapport
                 WHERE datum BETWEEN :from AND :to
                 GROUP BY skiftraknare"
            );
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Active operators
            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // Team totals + per-operator aggregation
            $teamIbc = 0;
            $teamMin = 0;
            $perOp   = [];

            foreach ($shifts as $s) {
                $ibc = (int)$s['ibc_ok'];
                $min = (int)$s['drifttid'];
                if ($min <= 0) continue;

                $teamIbc += $ibc;
                $teamMin += $min;

                $seenInShift = [];
                foreach (['op1', 'op2', 'op3'] as $p) {
                    $opNum = (int)$s[$p];
                    if ($opNum <= 0 || isset($seenInShift[$opNum])) continue;
                    $seenInShift[$opNum] = true;

                    if (!isset($perOp[$opNum])) {
                        $perOp[$opNum] = ['ibc' => 0, 'min' => 0, 'shifts' => 0, 'dates' => []];
                    }
                    $perOp[$opNum]['ibc']    += $ibc;
                    $perOp[$opNum]['min']    += $min;
                    $perOp[$opNum]['shifts'] += 1;
                    $perOp[$opNum]['dates'][$s['datum']] = true;
                }
            }

            $teamAvg = $teamMin > 0 ? $teamIbc / ($teamMin / 60.0) : 0;
            $summary = ['elite' => 0, 'solid' => 0, 'developing' => 0, 'behoever_stod' => 0];
            $result  = [];

            foreach ($perOp as $opNum => $d) {
                if ($d['shifts'] < 3) continue;

                $ibcH   = $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 1) : 0;
                $vsTeam = $teamAvg > 0 ? round(($ibcH / $teamAvg - 1) * 100, 1) : 0;

                if ($vsTeam >= 15)       { $tier = 'Elite';        $level = 'A'; $sk = 'elite'; }
                elseif ($vsTeam >= 5)    { $tier = 'Solid';        $level = 'B'; $sk = 'solid'; }
                elseif ($vsTeam >= 0)    { $tier = 'Solid';        $level = 'C'; $sk = 'solid'; }
                elseif ($vsTeam >= -15)  { $tier = 'Developing';   $level = 'Ingen'; $sk = 'developing'; }
                else                     { $tier = 'Behöver stöd'; $level = 'Ingen'; $sk = 'behoever_stod'; }

                $summary[$sk]++;

                $result[] = [
                    'number'       => $opNum,
                    'name'         => $opNames[$opNum] ?? "Op $opNum",
                    'total_shifts' => $d['shifts'],
                    'active_days'  => count($d['dates']),
                    'ibc_per_h'    => $ibcH,
                    'vs_team_pct'  => $vsTeam,
                    'tier'         => $tier,
                    'bonus_level'  => $level,
                ];
            }

            usort($result, fn($a, $b) => $b['vs_team_pct'] <=> $a['vs_team_pct']);

            echo json_encode([
                'success'        => true,
                'from'           => $from,
                'to'             => $to,
                'team_avg_ibc_h' => round($teamAvg, 1),
                'total_shifts'   => count($shifts),
                'operators'      => $result,
                'summary'        => $summary,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getBonusKalkylator: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid bonuskalkylator'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorInlarning(): void {
        try {
            $maxShifts = max(10, min(50, (int)($_GET['max_shifts'] ?? 30)));
            // Cap lookback to 2 years — sufficient for any learning curve
            $fromDate = date('Y-m-d', strtotime('-2 years'));

            $opRows = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $opNames = [];
            foreach ($opRows as $r) $opNames[(int)$r['number']] = $r['name'];

            // Team avg per position via SQL (SUM/SUM on unique shifts) — avoids PHP triple-loop
            $teamAvgStmt = $this->pdo->prepare("
                SELECT pos, SUM(ibc_ok) / GREATEST(SUM(drifttid) / 60.0, 0.01) AS avg_ibc_h
                FROM (
                    SELECT 1 AS pos, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE drifttid > 30 AND datum >= :from1 AND op1 IS NOT NULL
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT 2, MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE drifttid > 30 AND datum >= :from2 AND op2 IS NOT NULL
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT 3, MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE drifttid > 30 AND datum >= :from3 AND op3 IS NOT NULL
                    GROUP BY skiftraknare
                ) t
                GROUP BY pos
            ");
            $teamAvgStmt->execute([':from1' => $fromDate, ':from2' => $fromDate, ':from3' => $fromDate]);
            $teamAvg = [1 => 0.0, 2 => 0.0, 3 => 0.0];
            foreach ($teamAvgStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $teamAvg[(int)$r['pos']] = round((float)$r['avg_ibc_h'], 1);
            }

            // Fetch shifts in chronological order — date-capped to avoid full table scan
            $stmt = $this->pdo->prepare("
                SELECT datum, skiftraknare,
                       MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                       MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE drifttid > 30 AND datum >= :fromDate
                GROUP BY skiftraknare
                ORDER BY datum ASC, skiftraknare ASC
                LIMIT 5000
            ");
            $stmt->execute([':fromDate' => $fromDate]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Group into (op_num, pos_num) buckets; stop collecting once maxShifts reached per bucket
            $byOpPos = [];
            foreach ($allShifts as $r) {
                $ibcH = $r['drifttid'] > 0 ? (float)$r['ibc_ok'] / ((float)$r['drifttid'] / 60.0) : 0;
                foreach ([1, 2, 3] as $p) {
                    $opNum = (int)$r["op$p"];
                    if ($opNum <= 0) continue;
                    if (!isset($byOpPos[$opNum][$p])) $byOpPos[$opNum][$p] = [];
                    if (count($byOpPos[$opNum][$p]) >= $maxShifts) continue;
                    $byOpPos[$opNum][$p][] = [
                        'datum'  => $r['datum'],
                        'ibc_h'  => round($ibcH, 2),
                    ];
                }
            }

            $posNames = [1 => 'Tvättplats', 2 => 'Kontrollstation', 3 => 'Truckförare'];
            $result   = [];

            foreach ($byOpPos as $opNum => $positions) {
                if (!isset($opNames[$opNum])) continue;
                $opEntry = ['op_num' => $opNum, 'name' => $opNames[$opNum], 'positions' => []];

                foreach ($positions as $posNum => $shifts) {
                    $n = count($shifts);
                    if ($n < 3) continue;

                    $withRolling = [];
                    foreach ($shifts as $i => $s) {
                        $window = array_slice($shifts, max(0, $i - 2), min(3, $i + 1));
                        $vals   = array_filter(array_column($window, 'ibc_h'), fn($v) => $v > 0);
                        $rolling = count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : 0.0;
                        $withRolling[] = [
                            'shift_nr'  => $i + 1,
                            'datum'     => $s['datum'],
                            'ibc_h'     => $s['ibc_h'],
                            'rolling_3' => $rolling,
                        ];
                    }

                    $avg = $teamAvg[$posNum];
                    $reachedAt = null;
                    foreach ($withRolling as $s) {
                        if ($s['rolling_3'] >= $avg && $reachedAt === null) {
                            $reachedAt = $s['shift_nr'];
                        }
                    }

                    // Trend: first third vs last third
                    $third  = max(1, (int)floor($n / 3));
                    $first3 = array_column(array_slice($withRolling, 0, $third), 'ibc_h');
                    $last3  = array_column(array_slice($withRolling, -$third), 'ibc_h');
                    $fAvg   = count($first3) > 0 ? array_sum($first3) / count($first3) : 0;
                    $lAvg   = count($last3)  > 0 ? array_sum($last3)  / count($last3)  : 0;
                    $trend  = 'stabil';
                    if ($fAvg > 0) {
                        if ($lAvg > $fAvg * 1.08) $trend = 'okar';
                        elseif ($lAvg < $fAvg * 0.92) $trend = 'minskar';
                    }

                    $opEntry['positions'][] = [
                        'pos_num'       => $posNum,
                        'pos_name'      => $posNames[$posNum],
                        'total_shifts'  => $n,
                        'team_avg'      => $avg,
                        'reached_avg_at' => $reachedAt,
                        'current_rolling' => $withRolling[$n - 1]['rolling_3'] ?? 0,
                        'trend'         => $trend,
                        'shifts'        => $withRolling,
                    ];
                }

                if (!empty($opEntry['positions'])) {
                    $result[] = $opEntry;
                }
            }

            usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

            echo json_encode([
                'success'   => true,
                'team_avg'  => $teamAvg,
                'pos_names' => $posNames,
                'operators' => $result,
                'max_shifts' => $maxShifts,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorInlarning: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid inlärningsanalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE 14: Operator Performance Alert ──────────────────────────────
    private function getOperatorVarning(): void {
        try {
            $today    = date('Y-m-d');
            $recent14 = date('Y-m-d', strtotime('-14 days'));
            $base90   = date('Y-m-d', strtotime('-90 days'));

            $opStmt = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            );
            $operatorNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $operatorNames[(int)$op['number']] = $op['name'];
            }

            // All deduplicated shifts in last 90 days, ordered oldest first (for streak)
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)      AS op1,
                    MAX(op2)      AS op2,
                    MAX(op3)      AS op3,
                    MAX(ibc_ok)   AS ibc_ok,
                    MAX(drifttid) AS drifttid,
                    MAX(datum)    AS datum
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to AND drifttid > 0 AND ibc_ok > 0
                GROUP BY skiftraknare
                ORDER BY MAX(datum) ASC
            ");
            $stmt->execute([':from' => $base90, ':to' => $today]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Accumulate per-operator: recent (0–14d) vs baseline (15–90d) + per-shift history
            $opData = [];
            foreach ($allShifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min <= 0) continue;

                $ibcH     = $ibc / ($min / 60.0);
                $isRecent = ($s['datum'] >= $recent14);

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;

                    if (!isset($opData[$opNum])) {
                        $opData[$opNum] = ['recent' => [], 'baseline' => [], 'shifts' => []];
                    }
                    $opData[$opNum]['shifts'][] = round($ibcH, 2);
                    if ($isRecent) {
                        $opData[$opNum]['recent'][]   = ['ibc' => $ibc, 'min' => $min];
                    } else {
                        $opData[$opNum]['baseline'][] = ['ibc' => $ibc, 'min' => $min];
                    }
                }
            }

            $results = [];
            foreach ($opData as $opNum => $data) {
                $baselineN = count($data['baseline']);
                $recentN   = count($data['recent']);
                if ($baselineN < 5 || $recentN < 2) continue;

                $baseIbc  = array_sum(array_column($data['baseline'], 'ibc'));
                $baseMin  = array_sum(array_column($data['baseline'], 'min'));
                $baseIbcH = $baseMin > 0 ? $baseIbc / ($baseMin / 60.0) : 0;
                if ($baseIbcH <= 0) continue;

                $recIbc  = array_sum(array_column($data['recent'], 'ibc'));
                $recMin  = array_sum(array_column($data['recent'], 'min'));
                $recIbcH = $recMin > 0 ? $recIbc / ($recMin / 60.0) : 0;

                $delta = ($recIbcH - $baseIbcH) / $baseIbcH * 100;

                if ($delta <= -15)       { $kategori = 'forsämring';       $sortKey = 0; }
                elseif ($delta <= -5)    { $kategori = 'lätt_försämring';  $sortKey = 1; }
                elseif ($delta >= 10)    { $kategori = 'förbättring';      $sortKey = 3; }
                else                     { $kategori = 'stabil';           $sortKey = 2; }

                // Streak: consecutive shifts above/below overall 90d IBC/h avg
                $allShiftIbcH = $data['shifts']; // ASC order
                $n = count($allShiftIbcH);
                $avg90 = $n > 0 ? array_sum($allShiftIbcH) / $n : 0;
                $streak = 0;
                $streakDir = 'ingen';
                if ($n > 0 && $avg90 > 0) {
                    $lastIsOver = ($allShiftIbcH[$n - 1] >= $avg90);
                    $streakDir  = $lastIsOver ? 'over' : 'under';
                    for ($i = $n - 1; $i >= 0; $i--) {
                        $over = ($allShiftIbcH[$i] >= $avg90);
                        if ($over === $lastIsOver) { $streak++; } else { break; }
                    }
                }

                $results[] = [
                    'op_number'      => $opNum,
                    'name'           => $operatorNames[$opNum],
                    'baseline_ibc_h' => round($baseIbcH, 2),
                    'recent_ibc_h'   => round($recIbcH, 2),
                    'delta_pct'      => round($delta, 1),
                    'kategori'       => $kategori,
                    'baseline_skift' => $baselineN,
                    'recent_skift'   => $recentN,
                    'streak'         => $streak,
                    'streak_dir'     => $streakDir,
                    '_sort'          => $sortKey,
                ];
            }

            usort($results, function($a, $b) {
                if ($a['_sort'] !== $b['_sort']) return $a['_sort'] - $b['_sort'];
                return $a['delta_pct'] <=> $b['delta_pct'];
            });

            foreach ($results as &$r) { unset($r['_sort']); }
            unset($r);

            $counts = [
                'forsämring'      => 0,
                'lätt_försämring' => 0,
                'stabil'          => 0,
                'förbättring'     => 0,
            ];
            foreach ($results as $r) {
                if (isset($counts[$r['kategori']])) $counts[$r['kategori']]++;
            }

            echo json_encode([
                'success'      => true,
                'operators'    => $results,
                'counts'       => $counts,
                'from'         => $base90,
                'to'           => $today,
                'recent_from'  => $recent14,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorVarning: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid prestandavarning'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorProdukt(): void {
        try {
            $days = max(14, min(365, (int)($_GET['days'] ?? 90)));
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            // Operator names
            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $operatorNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $operatorNames[(int)$op['number']] = $op['name'];
            }

            // Product names
            $productNames = [];
            try {
                $pStmt = $this->pdo->query("SELECT id, name FROM rebotling_products ORDER BY id");
                foreach ($pStmt->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                    $productNames[(int)$p['id']] = $p['name'];
                }
            } catch (\Exception $e) { /* table may not exist */ }

            // Deduplicated shifts with product_id
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)        AS op1,
                    MAX(op2)        AS op2,
                    MAX(op3)        AS op3,
                    MAX(ibc_ok)     AS ibc_ok,
                    MAX(drifttid)   AS drifttid,
                    MAX(product_id) AS product_id
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to
                  AND drifttid > 0 AND ibc_ok > 0
                  AND product_id IS NOT NULL AND product_id > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build matrix: opNum → prodId → [ibc, min, count]
            $matrix     = [];
            $productSet = [];

            foreach ($shifts as $s) {
                $prodId = (int)$s['product_id'];
                $ibc    = max(0, (int)$s['ibc_ok']);
                $min    = max(0, (int)$s['drifttid']);
                if ($min <= 0 || $prodId <= 0) continue;

                $productSet[$prodId] = true;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;

                    if (!isset($matrix[$opNum][$prodId])) {
                        $matrix[$opNum][$prodId] = ['ibc' => 0, 'min' => 0, 'count' => 0];
                    }
                    $matrix[$opNum][$prodId]['ibc']   += $ibc;
                    $matrix[$opNum][$prodId]['min']   += $min;
                    $matrix[$opNum][$prodId]['count'] += 1;
                }
            }

            if (empty($productSet)) {
                echo json_encode([
                    'success' => true, 'operators' => [], 'products' => [],
                    'best_per_product' => [], 'from' => $from, 'to' => $to, 'days' => $days,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $productIds = array_keys($productSet);
            sort($productIds);

            // Team avg IBC/h per product (all operators combined)
            $teamTotals = [];
            foreach ($matrix as $prodData) {
                foreach ($prodData as $prodId => $d) {
                    if (!isset($teamTotals[$prodId])) $teamTotals[$prodId] = ['ibc' => 0, 'min' => 0];
                    $teamTotals[$prodId]['ibc'] += $d['ibc'];
                    $teamTotals[$prodId]['min'] += $d['min'];
                }
            }
            $teamAvg = [];
            foreach ($teamTotals as $prodId => $t) {
                $teamAvg[$prodId] = $t['min'] > 0 ? $t['ibc'] / ($t['min'] / 60.0) : 0;
            }

            // Build operator rows (min 3 shifts per cell to show data)
            $operators = [];
            foreach ($matrix as $opNum => $prodData) {
                $row = ['op_number' => $opNum, 'name' => $operatorNames[$opNum], 'products' => []];
                foreach ($productIds as $prodId) {
                    if (!isset($prodData[$prodId]) || $prodData[$prodId]['count'] < 3) {
                        $row['products'][$prodId] = null;
                        continue;
                    }
                    $d    = $prodData[$prodId];
                    $ibcH = $d['min'] > 0 ? $d['ibc'] / ($d['min'] / 60.0) : 0;
                    $tavg = $teamAvg[$prodId] ?? 0;
                    $row['products'][$prodId] = [
                        'ibc_per_h'   => round($ibcH, 2),
                        'antal_skift' => $d['count'],
                        'vs_team'     => $tavg > 0 ? round(($ibcH / $tavg - 1) * 100, 1) : 0,
                    ];
                }
                $operators[] = $row;
            }

            usort($operators, fn($a, $b) => strcmp($a['name'], $b['name']));

            // Best operator per product
            $bestPerProduct = [];
            foreach ($productIds as $prodId) {
                $best = null; $bestIbcH = 0;
                foreach ($operators as $op) {
                    $cell = $op['products'][$prodId] ?? null;
                    if ($cell && $cell['ibc_per_h'] > $bestIbcH) {
                        $bestIbcH = $cell['ibc_per_h'];
                        $best = ['op_number' => $op['op_number'], 'name' => $op['name'], 'ibc_per_h' => $bestIbcH];
                    }
                }
                $bestPerProduct[$prodId] = $best;
            }

            $products = [];
            foreach ($productIds as $prodId) {
                $products[] = [
                    'id'       => $prodId,
                    'name'     => $productNames[$prodId] ?? "Produkt {$prodId}",
                    'team_avg' => round($teamAvg[$prodId] ?? 0, 2),
                ];
            }

            echo json_encode([
                'success'          => true,
                'operators'        => $operators,
                'products'         => $products,
                'best_per_product' => $bestPerProduct,
                'from'             => $from,
                'to'               => $to,
                'days'             => $days,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorProdukt: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktanalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE: Operator Stopptidsanalys ───────────────────────────────────

    private function getOperatorStopptid(): void {
        try {
            $days = isset($_GET['days']) ? max(14, min(365, (int)$_GET['days'])) : 90;
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            $opStmt = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            );
            $operatorNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $operatorNames[(int)$op['number']] = $op['name'];
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)             AS op1,
                    MAX(op2)             AS op2,
                    MAX(op3)             AS op3,
                    MAX(drifttid)        AS drifttid,
                    MAX(driftstopptime)  AS driftstopptime
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to AND drifttid > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Per-operator accumulation
            $opData = []; // [opNum] => [shifts, stopp_shifts, total_stopp, total_drifttid]

            foreach ($shifts as $s) {
                $stoppMin  = max(0, (int)$s['driftstopptime']);
                $drifttid  = max(1, (int)$s['drifttid']);
                $hasStop   = $stoppMin > 0 ? 1 : 0;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;

                    if (!isset($opData[$opNum])) {
                        $opData[$opNum] = [
                            'shifts'       => 0,
                            'stopp_shifts' => 0,
                            'total_stopp'  => 0,
                            'total_drift'  => 0,
                        ];
                    }
                    $opData[$opNum]['shifts']++;
                    $opData[$opNum]['stopp_shifts'] += $hasStop;
                    $opData[$opNum]['total_stopp']  += $stoppMin;
                    $opData[$opNum]['total_drift']  += $drifttid;
                }
            }

            // Team average stoppgrad (weighted)
            $teamTotalStopp = 0;
            $teamTotalDrift = 0;
            foreach ($opData as $d) {
                if ($d['shifts'] < 3) continue;
                $teamTotalStopp += $d['total_stopp'];
                $teamTotalDrift += $d['total_drift'];
            }
            $teamStoppgrad = $teamTotalDrift > 0
                ? round($teamTotalStopp / $teamTotalDrift * 100, 1)
                : 0;

            $results = [];
            foreach ($opData as $opNum => $d) {
                if ($d['shifts'] < 3) continue;

                $snittStoppMin  = round($d['total_stopp'] / $d['shifts'], 1);
                $stoppProcent   = round($d['stopp_shifts'] / $d['shifts'] * 100, 1);
                $stoppgrad      = $d['total_drift'] > 0
                    ? round($d['total_stopp'] / $d['total_drift'] * 100, 1)
                    : 0.0;
                $vsSnitt        = $teamStoppgrad > 0
                    ? round($stoppgrad - $teamStoppgrad, 1)
                    : null;

                // Status: green = well below avg, yellow = near avg, red = above avg
                if ($vsSnitt !== null) {
                    if ($vsSnitt <= -2)        $status = 'bra';
                    elseif ($vsSnitt <= 1)     $status = 'normal';
                    else                       $status = 'hog';
                } else {
                    $status = 'normal';
                }

                $results[] = [
                    'op_number'      => $opNum,
                    'name'           => $operatorNames[$opNum],
                    'total_shifts'   => $d['shifts'],
                    'stopp_shifts'   => $d['stopp_shifts'],
                    'stopp_procent'  => $stoppProcent,
                    'snitt_stopp_min'=> $snittStoppMin,
                    'total_stopp_min'=> $d['total_stopp'],
                    'stoppgrad'      => $stoppgrad,
                    'vs_snitt'       => $vsSnitt,
                    'status'         => $status,
                ];
            }

            // Sort: lowest stoppgrad first (best operators)
            usort($results, fn($a, $b) => $a['stoppgrad'] <=> $b['stoppgrad']);

            echo json_encode([
                'success'       => true,
                'operators'     => $results,
                'team_stoppgrad'=> $teamStoppgrad,
                'days'          => $days,
                'from'          => $from,
                'to'            => $to,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorStopptid: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid stopptidsanalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getSkiftKalender(): void {
        $year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $month = max(1, min(12, $month));
        $year  = max(2020, min(2030, $year));

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        try {
            $pdo = $this->pdo;

            $opRows = $pdo->query("SELECT number, name FROM operators ORDER BY number")->fetchAll(\PDO::FETCH_ASSOC);
            $opMap  = [];
            foreach ($opRows as $r) $opMap[(int)$r['number']] = $r['name'];

            $stmt = $pdo->prepare("
                SELECT skiftraknare,
                       MAX(datum)          AS datum,
                       MAX(op1)            AS op1,
                       MAX(op2)            AS op2,
                       MAX(op3)            AS op3,
                       MAX(ibc_ok)         AS ibc_ok,
                       MAX(ibc_ej_ok)      AS ibc_ej_ok,
                       MAX(drifttid)       AS drifttid,
                       MAX(driftstopptime) AS driftstopptime,
                       MAX(product_id)     AS product_id
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from AND :to
                GROUP BY skiftraknare
                ORDER BY datum, skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $byDay      = [];
            $totalIbc   = 0;
            $totalDrift = 0;

            foreach ($rows as $r) {
                $d    = $r['datum'];
                $ibc  = (int)($r['ibc_ok']   ?? 0);
                $drft = (int)($r['drifttid']  ?? 0);
                $ibcH = $drft > 0 ? round($ibc / ($drft / 60.0), 2) : 0.0;

                if (!isset($byDay[$d])) $byDay[$d] = [];
                $byDay[$d][] = [
                    'skiftraknare'   => (int)$r['skiftraknare'],
                    'op1_num'        => $r['op1'] ? (int)$r['op1'] : 0,
                    'op2_num'        => $r['op2'] ? (int)$r['op2'] : 0,
                    'op3_num'        => $r['op3'] ? (int)$r['op3'] : 0,
                    'op1_name'       => $r['op1'] ? ($opMap[(int)$r['op1']] ?? '') : '',
                    'op2_name'       => $r['op2'] ? ($opMap[(int)$r['op2']] ?? '') : '',
                    'op3_name'       => $r['op3'] ? ($opMap[(int)$r['op3']] ?? '') : '',
                    'ibc_ok'         => $ibc,
                    'ibc_ej_ok'      => (int)($r['ibc_ej_ok']      ?? 0),
                    'drifttid'       => $drft,
                    'driftstopptime' => (int)($r['driftstopptime']  ?? 0),
                    'product_id'     => (int)($r['product_id']      ?? 0),
                    'ibc_per_h'      => $ibcH,
                ];
                $totalIbc   += $ibc;
                $totalDrift += $drft;
            }

            $monthAvg     = $totalDrift > 0 ? round($totalIbc / ($totalDrift / 60.0), 2) : 0.0;
            $daysInMonth  = (int)date('t', strtotime($from));
            $firstWeekday = (int)date('N', strtotime($from));
            $monthNames   = ['', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                             'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];

            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $shifts  = $byDay[$dateStr] ?? [];
                $dayIbc  = array_sum(array_column($shifts, 'ibc_ok'));
                $dayDrft = array_sum(array_column($shifts, 'drifttid'));
                $dayIbcH = $dayDrft > 0 ? round($dayIbc / ($dayDrft / 60.0), 2) : 0.0;
                $vsAvg   = ($monthAvg > 0 && count($shifts) > 0) ? round($dayIbcH / $monthAvg * 100, 1) : null;

                $rating = 'tom';
                if (count($shifts) > 0) {
                    if ($vsAvg === null)    $rating = 'avg';
                    elseif ($vsAvg >= 120) $rating = 'great';
                    elseif ($vsAvg >= 105) $rating = 'good';
                    elseif ($vsAvg >= 90)  $rating = 'avg';
                    elseif ($vsAvg >= 70)  $rating = 'weak';
                    else                   $rating = 'poor';
                }

                $days[] = [
                    'date'          => $dateStr,
                    'day'           => $d,
                    'shifts'        => $shifts,
                    'day_ibc_ok'    => $dayIbc,
                    'day_drifttid'  => $dayDrft,
                    'day_ibc_per_h' => $dayIbcH,
                    'vs_avg'        => $vsAvg,
                    'rating'        => $rating,
                ];
            }

            echo json_encode([
                'success'         => true,
                'year'            => $year,
                'month'           => $month,
                'month_name'      => $monthNames[$month],
                'days_in_month'   => $daysInMonth,
                'first_weekday'   => $firstWeekday,
                'month_avg_ibc_h' => $monthAvg,
                'total_ibc_ok'    => $totalIbc,
                'total_skift'     => count($rows),
                'days'            => $days,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getSkiftKalender: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kalenderdata'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorVeckodag(): void {
        $days = max(14, min(365, (int)($_GET['days'] ?? 90)));
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $pdo = $this->pdo;

            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            $dowNames = [1=>'Söndag',2=>'Måndag',3=>'Tisdag',4=>'Onsdag',5=>'Torsdag',6=>'Fredag',7=>'Lördag'];

            // Team average per weekday (SUM/SUM with skiftraknare dedup)
            $teamStmt = $pdo->prepare("
                SELECT dow,
                       SUM(ibc_ok) / (SUM(drifttid) / 60.0) AS team_avg,
                       COUNT(*) AS shifts
                FROM (
                    SELECT DAYOFWEEK(MAX(datum)) AS dow,
                           MAX(ibc_ok) AS ibc_ok,
                           MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) deduped
                GROUP BY dow
                ORDER BY dow
            ");
            $teamStmt->execute([':from' => $from, ':to' => $to]);

            $teamByDow = [];
            $teamWeightedSum = 0.0;
            $teamShiftsTotal = 0;
            foreach ($teamStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $dow = (int)$r['dow'];
                $avg = round((float)$r['team_avg'], 2);
                $cnt = (int)$r['shifts'];
                $teamByDow[$dow] = ['dow' => $dow, 'name' => $dowNames[$dow] ?? '', 'team_avg' => $avg, 'team_shifts' => $cnt];
                $teamWeightedSum += $avg * $cnt;
                $teamShiftsTotal += $cnt;
            }
            $teamAvgOverall = $teamShiftsTotal > 0 ? round($teamWeightedSum / $teamShiftsTotal, 2) : 0.0;

            // Per-operator per-weekday (avg IBC/h of shifts they worked)
            // Dedup by skiftraknare first to avoid counting PLC partial-shift rows
            $opDowStmt = $pdo->prepare("
                SELECT op_num, dow,
                       AVG(ibc_per_h) AS avg_ibc_h,
                       COUNT(*) AS shifts
                FROM (
                    SELECT op1 AS op_num, DAYOFWEEK(MAX(datum)) AS dow,
                           MAX(ibc_ok) / (MAX(drifttid) / 60.0) AS ibc_per_h
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT op2, DAYOFWEEK(MAX(datum)),
                           MAX(ibc_ok) / (MAX(drifttid) / 60.0)
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT op3, DAYOFWEEK(MAX(datum)),
                           MAX(ibc_ok) / (MAX(drifttid) / 60.0)
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) combined
                GROUP BY op_num, dow
                HAVING shifts >= 2
                ORDER BY op_num, dow
            ");
            $opDowStmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);

            $byOp = [];
            foreach ($opDowStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $num = (int)$r['op_num'];
                if (!isset($opMap[$num])) continue;
                $dow     = (int)$r['dow'];
                $avgIbcH = round((float)$r['avg_ibc_h'], 2);
                $vsTeam  = (isset($teamByDow[$dow]) && $teamByDow[$dow]['team_avg'] > 0)
                    ? round($avgIbcH / $teamByDow[$dow]['team_avg'] * 100, 1)
                    : null;
                if (!isset($byOp[$num])) $byOp[$num] = [];
                $byOp[$num][$dow] = ['avg_ibc_h' => $avgIbcH, 'shifts' => (int)$r['shifts'], 'vs_team' => $vsTeam];
            }

            // Total unique shifts per operator (dedup by skiftraknare)
            $totStmt = $pdo->prepare("
                SELECT op_num, COUNT(*) AS total
                FROM (
                    SELECT op1 AS op_num
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1 AND op1 IS NOT NULL AND drifttid > 0
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT op2
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2 AND op2 IS NOT NULL AND drifttid > 0
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT op3
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3 AND op3 IS NOT NULL AND drifttid > 0
                    GROUP BY skiftraknare
                ) t
                GROUP BY op_num
            ");
            $totStmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $totByOp = [];
            foreach ($totStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $totByOp[(int)$r['op_num']] = (int)$r['total'];
            }

            $operators = [];
            foreach ($byOp as $num => $dowMap) {
                if (count($dowMap) < 2) continue;

                $bestDow = null;
                $bestAvg = -1.0;
                foreach ($dowMap as $dow => $data) {
                    if ($data['avg_ibc_h'] > $bestAvg) {
                        $bestAvg = $data['avg_ibc_h'];
                        $bestDow = $dow;
                    }
                }

                $operators[] = [
                    'number'        => $num,
                    'name'          => $opMap[$num],
                    'total_shifts'  => $totByOp[$num] ?? array_sum(array_column($dowMap, 'shifts')),
                    'best_dow'      => $bestDow,
                    'best_dow_name' => $bestDow !== null ? ($dowNames[$bestDow] ?? '') : '',
                    'best_avg'      => round($bestAvg, 2),
                    'by_dow'        => $dowMap,
                ];
            }

            usort($operators, fn($a, $b) => strcmp($a['name'], $b['name']));

            // Best operator per weekday (Mon–Fri)
            $recommendations = [];
            foreach ([2, 3, 4, 5, 6] as $dow) {
                if (!isset($teamByDow[$dow]) || $teamByDow[$dow]['team_shifts'] < 3) continue;
                $bestName  = '';
                $bestIbcH  = 0.0;
                foreach ($operators as $op) {
                    if (isset($op['by_dow'][$dow]) && $op['by_dow'][$dow]['avg_ibc_h'] > $bestIbcH) {
                        $bestIbcH = $op['by_dow'][$dow]['avg_ibc_h'];
                        $bestName = $op['name'];
                    }
                }
                if ($bestName) {
                    $recommendations[] = ['dow' => $dow, 'name' => $dowNames[$dow], 'best_op_name' => $bestName, 'best_op_ibc_h' => round($bestIbcH, 2)];
                }
            }

            // Weekdays ordered Mon–Sun
            $weekdays = [];
            foreach ([2, 3, 4, 5, 6, 7, 1] as $dow) {
                $weekdays[] = $teamByDow[$dow] ?? ['dow' => $dow, 'name' => $dowNames[$dow] ?? '', 'team_avg' => 0.0, 'team_shifts' => 0];
            }

            echo json_encode([
                'success'          => true,
                'days'             => $days,
                'from'             => $from,
                'to'               => $to,
                'weekdays'         => $weekdays,
                'operators'        => $operators,
                'team_avg_overall' => $teamAvgOverall,
                'recommendations'  => $recommendations,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorVeckodag: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid veckodag-analys'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorKassation(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Half-period split for trend calculation
            $mid     = date('Y-m-d', strtotime("-" . (int)($days / 2) . " days"));

            // Fetch all operators
            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            // Aggregate kassation per operator (union op1/op2/op3)
            $sql = "
                SELECT op_num,
                       SUM(ibc_ok_val)     AS ibc_ok,
                       SUM(ibc_ej_val)     AS ibc_ej_ok,
                       SUM(bur_ej_val)     AS bur_ej_ok,
                       COUNT(*)            AS total_shifts,
                       SUM(CASE WHEN datum < :mid1 THEN ibc_ok_val  ELSE 0 END) AS early_ibc_ok,
                       SUM(CASE WHEN datum < :mid2 THEN ibc_ej_val  ELSE 0 END) AS early_ibc_ej,
                       SUM(CASE WHEN datum >= :mid3 THEN ibc_ok_val ELSE 0 END) AS late_ibc_ok,
                       SUM(CASE WHEN datum >= :mid4 THEN ibc_ej_val ELSE 0 END) AS late_ibc_ej
                FROM (
                    SELECT op1    AS op_num,
                           MAX(COALESCE(ibc_ok,0))      AS ibc_ok_val,
                           MAX(COALESCE(ibc_ej_ok,0))   AS ibc_ej_val,
                           MAX(COALESCE(bur_ej_ok,0))   AS bur_ej_val,
                           datum
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL
                    GROUP BY skiftraknare, datum, op1

                    UNION ALL

                    SELECT op2,
                           MAX(COALESCE(ibc_ok,0)),
                           MAX(COALESCE(ibc_ej_ok,0)),
                           MAX(COALESCE(bur_ej_ok,0)),
                           datum
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL
                    GROUP BY skiftraknare, datum, op2

                    UNION ALL

                    SELECT op3,
                           MAX(COALESCE(ibc_ok,0)),
                           MAX(COALESCE(ibc_ej_ok,0)),
                           MAX(COALESCE(bur_ej_ok,0)),
                           datum
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL
                    GROUP BY skiftraknare, datum, op3
                ) u
                GROUP BY op_num
                HAVING total_shifts >= 3
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
                ':mid1'  => $mid,  ':mid2' => $mid,
                ':mid3'  => $mid,  ':mid4' => $mid,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $operators = [];
            $totalIbcOk  = 0;
            $totalIbcEj  = 0;
            $totalBurEj  = 0;

            foreach ($rows as $r) {
                $num     = (int)$r['op_num'];
                if (!isset($opMap[$num])) continue;

                $ibcOk  = (int)$r['ibc_ok'];
                $ibcEj  = (int)$r['ibc_ej_ok'];
                $burEj  = (int)$r['bur_ej_ok'];
                $total  = $ibcOk + $ibcEj;
                $kassGrad = $total > 0 ? round($ibcEj / $total * 100, 2) : 0.0;

                // Trend: early half vs late half
                $earlyOk  = (int)$r['early_ibc_ok'];
                $earlyEj  = (int)$r['early_ibc_ej'];
                $lateOk   = (int)$r['late_ibc_ok'];
                $lateEj   = (int)$r['late_ibc_ej'];
                $earlyTotal = $earlyOk + $earlyEj;
                $lateTotal  = $lateOk  + $lateEj;
                $earlyKass  = $earlyTotal > 0 ? $earlyEj / $earlyTotal * 100 : null;
                $lateKass   = $lateTotal  > 0 ? $lateEj  / $lateTotal  * 100 : null;

                $trend = 'stable';
                if ($earlyKass !== null && $lateKass !== null) {
                    $delta = $lateKass - $earlyKass;
                    if ($delta > 1.0)       $trend = 'worse';
                    elseif ($delta < -1.0)  $trend = 'better';
                }

                $totalIbcOk += $ibcOk;
                $totalIbcEj += $ibcEj;
                $totalBurEj += $burEj;

                $operators[] = [
                    'number'         => $num,
                    'name'           => $opMap[$num],
                    'ibc_ok'         => $ibcOk,
                    'ibc_ej_ok'      => $ibcEj,
                    'bur_ej_ok'      => $burEj,
                    'total_shifts'   => (int)$r['total_shifts'],
                    'kassationsgrad' => $kassGrad,
                    'trend'          => $trend,
                ];
            }

            // Team average kassationsgrad
            $totalProd = $totalIbcOk + $totalIbcEj;
            $teamKass  = $totalProd > 0 ? round($totalIbcEj / $totalProd * 100, 2) : 0.0;

            // Compute vs_team for each operator
            foreach ($operators as &$op) {
                $op['vs_team'] = $teamKass > 0
                    ? round($op['kassationsgrad'] - $teamKass, 2)
                    : 0.0;
                // quality status
                $k = $op['kassationsgrad'];
                if ($k <= max(1.0, $teamKass * 0.6))      $op['status'] = 'bra';
                elseif ($k <= $teamKass * 1.3)            $op['status'] = 'normal';
                else                                       $op['status'] = 'hog';
            }
            unset($op);

            usort($operators, fn($a, $b) => $a['kassationsgrad'] <=> $b['kassationsgrad']);

            $best  = count($operators) ? $operators[0]['kassationsgrad'] : 0.0;
            $worst = count($operators) ? $operators[count($operators)-1]['kassationsgrad'] : 0.0;

            echo json_encode([
                'success'         => true,
                'days'            => $days,
                'from'            => $from,
                'to'              => $to,
                'operators'       => $operators,
                'team_kassgrad'   => $teamKass,
                'total_ibc_ok'    => $totalIbcOk,
                'total_ibc_ej'    => $totalIbcEj,
                'total_bur_ej'    => $totalBurEj,
                'best_kassgrad'   => $best,
                'worst_kassgrad'  => $worst,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorKassation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassationsanalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ── Skift-prognos ──────────────────────────────────────────────────────────
    // GET ?action=rebotling&run=skift-prognos[&op1=N&op2=N&op3=N]
    // Without op params → returns active operator list for dropdowns.
    // With op params    → returns per-position performance forecast.
    private function getSkiftPrognos(): void
    {
        try {
            $pdo  = $this->pdo;
            $days = 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Fetch all active operators
            $stmtOps = $pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opRows  = $stmtOps->fetchAll(\PDO::FETCH_ASSOC);
            $opMap   = [];
            foreach ($opRows as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            $op1 = isset($_GET['op1']) && $_GET['op1'] !== '' ? (int)$_GET['op1'] : null;
            $op2 = isset($_GET['op2']) && $_GET['op2'] !== '' ? (int)$_GET['op2'] : null;
            $op3 = isset($_GET['op3']) && $_GET['op3'] !== '' ? (int)$_GET['op3'] : null;

            // Return operator list only if no team selected
            if ($op1 === null && $op2 === null && $op3 === null) {
                echo json_encode([
                    'success'   => true,
                    'operators' => $opRows,
                    'forecast'  => null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // For each position: compute team avg IBC/h over 90d, and per-operator stats
            // We aggregate per unique skiftraknare to avoid double-counting multi-row shifts.
            $positions = [
                ['col' => 'op1', 'label' => 'Tvättplats',      'op' => $op1],
                ['col' => 'op2', 'label' => 'Kontrollstation', 'op' => $op2],
                ['col' => 'op3', 'label' => 'Truckförare',      'op' => $op3],
            ];

            $posResults = [];

            foreach ($positions as $idx => $pos) {
                $col    = $pos['col'];
                $opNum  = $pos['op'];
                $suffix = (string)($idx + 1);

                // Team avg at this position (all operators, last 90d)
                // Aggregate per skiftraknare first, then average
                $teamSql = "
                    SELECT AVG(shift_ibc_h) AS team_avg_ibc_h,
                           COUNT(*)          AS team_shifts
                    FROM (
                        SELECT skiftraknare,
                               SUM(COALESCE(ibc_ok,0))                              AS ibc_ok_sum,
                               SUM(COALESCE(drifttid,0))                            AS drifttid_sum,
                               CASE WHEN SUM(COALESCE(drifttid,0)) > 0
                                    THEN SUM(COALESCE(ibc_ok,0)) / (SUM(COALESCE(drifttid,0))/60.0)
                                    ELSE 0 END                                      AS shift_ibc_h
                        FROM rebotling_skiftrapport
                        WHERE datum BETWEEN :from{$suffix} AND :to{$suffix}
                          AND {$col} IS NOT NULL
                          AND drifttid > 0
                        GROUP BY skiftraknare
                    ) t
                    WHERE shift_ibc_h > 0
                ";
                $stmtTeam = $pdo->prepare($teamSql);
                $stmtTeam->execute([":from{$suffix}" => $from, ":to{$suffix}" => $to]);
                $teamRow = $stmtTeam->fetch(\PDO::FETCH_ASSOC);

                $teamAvg    = $teamRow ? round((float)$teamRow['team_avg_ibc_h'], 2) : 0.0;
                $teamShifts = $teamRow ? (int)$teamRow['team_shifts'] : 0;

                $opData = null;
                if ($opNum !== null && isset($opMap[$opNum])) {
                    // Per-shift IBC/h for this operator at this position
                    $opSuffix = "op{$suffix}";
                    $opSql = "
                        SELECT shift_ibc_h
                        FROM (
                            SELECT skiftraknare,
                                   CASE WHEN SUM(COALESCE(drifttid,0)) > 0
                                        THEN SUM(COALESCE(ibc_ok,0)) / (SUM(COALESCE(drifttid,0))/60.0)
                                        ELSE 0 END AS shift_ibc_h
                            FROM rebotling_skiftrapport
                            WHERE datum BETWEEN :{$opSuffix}_from AND :{$opSuffix}_to
                              AND {$col} = :{$opSuffix}_num
                              AND drifttid > 0
                            GROUP BY skiftraknare
                        ) t
                        WHERE shift_ibc_h > 0
                    ";
                    $stmtOp = $pdo->prepare($opSql);
                    $stmtOp->execute([
                        ":{$opSuffix}_from" => $from,
                        ":{$opSuffix}_to"   => $to,
                        ":{$opSuffix}_num"  => $opNum,
                    ]);
                    $shiftRows = $stmtOp->fetchAll(\PDO::FETCH_COLUMN);
                    $shiftRows = array_map('floatval', $shiftRows);

                    $n = count($shiftRows);
                    if ($n >= 1) {
                        $avg = array_sum($shiftRows) / $n;

                        // Stddev for consistency (population stddev)
                        $variance = 0.0;
                        foreach ($shiftRows as $v) {
                            $variance += ($v - $avg) ** 2;
                        }
                        $stddev = $n > 1 ? sqrt($variance / $n) : 0.0;
                        $consistency = $avg > 0 ? max(0, min(100, round(100 - ($stddev / $avg * 100), 1))) : 0.0;

                        $vsTeam = $teamAvg > 0 ? round(($avg / $teamAvg - 1) * 100, 1) : 0.0;

                        // Rating
                        if ($vsTeam >= 10)        $rating = 'topp';
                        elseif ($vsTeam >= -10)   $rating = 'snitt';
                        else                       $rating = 'under';

                        $opData = [
                            'number'       => $opNum,
                            'name'         => $opMap[$opNum],
                            'avg_ibc_h'    => round($avg, 2),
                            'antal_skift'  => $n,
                            'consistency'  => $consistency,
                            'vs_team'      => $vsTeam,
                            'rating'       => $rating,
                            'shifts'       => array_map(fn($v) => round($v, 2), $shiftRows),
                        ];
                    } else {
                        $opData = [
                            'number'      => $opNum,
                            'name'        => $opMap[$opNum],
                            'avg_ibc_h'   => 0.0,
                            'antal_skift' => 0,
                            'consistency' => 0.0,
                            'vs_team'     => 0.0,
                            'rating'      => 'ingen',
                            'shifts'      => [],
                        ];
                    }
                }

                $posResults[] = [
                    'position'    => $pos['label'],
                    'col'         => $col,
                    'team_avg'    => $teamAvg,
                    'team_shifts' => $teamShifts,
                    'operator'    => $opData,
                ];
            }

            // Predicted team IBC/h = average of positions that have data (simple mean)
            $predicted = [];
            foreach ($posResults as $p) {
                if ($p['operator'] && $p['operator']['avg_ibc_h'] > 0) {
                    $predicted[] = $p['operator']['avg_ibc_h'];
                }
            }
            $predictedTeamIbcH  = count($predicted) > 0 ? round(array_sum($predicted) / count($predicted), 2) : 0.0;
            $teamAvgAll = array_filter(array_column($posResults, 'team_avg'));
            $teamAvgOverall = count($teamAvgAll) > 0 ? round(array_sum($teamAvgAll) / count($teamAvgAll), 2) : 0.0;
            $vsTeamOverall = $teamAvgOverall > 0 ? round(($predictedTeamIbcH / $teamAvgOverall - 1) * 100, 1) : 0.0;

            echo json_encode([
                'success'            => true,
                'operators'          => $opRows,
                'days'               => $days,
                'from'               => $from,
                'to'                 => $to,
                'forecast'           => [
                    'positions'          => $posResults,
                    'predicted_team_ibc_h' => $predictedTeamIbcH,
                    'team_avg_overall'   => $teamAvgOverall,
                    'vs_team_overall'    => $vsTeamOverall,
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getSkiftPrognos: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skiftprognos'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ── IBC-förlustkalkyl ──────────────────────────────────────────────────────
    // GET ?action=rebotling&run=ibc-forlust[&days=90]
    // For each operator+position: actual IBC/h vs team avg → IBC impact over period.
    private function getIbcForlust(): void
    {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(7, min(365, (int)$_GET['days'])) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // All unique shifts in period (one row per shift via GROUP BY skiftraknare)
            $stmt = $pdo->prepare("
                SELECT skiftraknare,
                       MAX(op1)      AS op1,
                       MAX(op2)      AS op2,
                       MAX(op3)      AS op3,
                       MAX(ibc_ok)   AS ibc_ok,
                       MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from AND :to AND drifttid > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Operator names
            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active=1");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            // Accumulate per-operator+position and per-position totals
            $opStats  = []; // [num][pos] = ['ibc'=>0,'min'=>0,'shifts'=>0]
            $posStats = ['op1' => ['ibc' => 0, 'min' => 0], 'op2' => ['ibc' => 0, 'min' => 0], 'op3' => ['ibc' => 0, 'min' => 0]];

            foreach ($allShifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $num = (int)$s[$pos];
                    if ($num <= 0) continue;

                    $opStats[$num][$pos]['ibc']    = ($opStats[$num][$pos]['ibc']    ?? 0) + $ibc;
                    $opStats[$num][$pos]['min']    = ($opStats[$num][$pos]['min']    ?? 0) + $min;
                    $opStats[$num][$pos]['shifts'] = ($opStats[$num][$pos]['shifts'] ?? 0) + 1;

                    $posStats[$pos]['ibc'] += $ibc;
                    $posStats[$pos]['min'] += $min;
                }
            }

            // Team average IBC/h per position
            $posLabels = ['op1' => 'Tvättplats', 'op2' => 'Kontrollstation', 'op3' => 'Truckförare'];
            $teamAvg   = [];
            foreach (['op1', 'op2', 'op3'] as $pos) {
                $teamAvg[$pos] = $posStats[$pos]['min'] > 0
                    ? round($posStats[$pos]['ibc'] / ($posStats[$pos]['min'] / 60.0), 2)
                    : 0.0;
            }

            // Build per-operator result
            $operators = [];
            foreach ($opStats as $num => $positions) {
                $totalGain   = 0.0;
                $totalLoss   = 0.0;
                $totalShifts = 0;
                $posResults  = [];

                foreach ($positions as $pos => $stats) {
                    if ($stats['shifts'] < 3) continue;
                    $avg = $teamAvg[$pos];
                    if ($avg <= 0) continue;

                    $opIbcH  = $stats['ibc'] / ($stats['min'] / 60.0);
                    $hours   = $stats['min'] / 60.0;
                    $impact  = round(($opIbcH - $avg) * $hours, 1);
                    $vsPct   = round(($opIbcH / $avg - 1) * 100, 1);

                    $posResults[] = [
                        'pos'            => $pos,
                        'label'          => $posLabels[$pos],
                        'antal_skift'    => $stats['shifts'],
                        'avg_ibc_h'      => round($opIbcH, 2),
                        'team_avg_ibc_h' => $avg,
                        'vs_team_pct'    => $vsPct,
                        'hours_worked'   => round($hours, 1),
                        'ibc_impact'     => $impact,
                    ];

                    if ($impact > 0) $totalGain += $impact;
                    else             $totalLoss += $impact;
                    $totalShifts += $stats['shifts'];
                }

                if (empty($posResults)) continue;

                $operators[] = [
                    'op_num'      => $num,
                    'name'        => $opMap[$num] ?? "Op $num",
                    'positions'   => $posResults,
                    'total_gain'  => round($totalGain, 1),
                    'total_loss'  => round($totalLoss, 1),
                    'net_impact'  => round($totalGain + $totalLoss, 1),
                    'total_skift' => $totalShifts,
                ];
            }

            usort($operators, fn($a, $b) => $a['net_impact'] <=> $b['net_impact']);

            $totalLoss     = round((float)array_sum(array_map(fn($o) => min(0.0, $o['net_impact']), $operators)), 1);
            $totalGain     = round((float)array_sum(array_map(fn($o) => max(0.0, $o['net_impact']), $operators)), 1);
            $projectedGain = round(abs($totalLoss), 1);

            echo json_encode([
                'success'         => true,
                'days'            => $days,
                'from'            => $from,
                'to'              => $to,
                'operators'       => $operators,
                'team_avg_by_pos' => $teamAvg,
                'total_loss'      => $totalLoss,
                'total_gain'      => $totalGain,
                'projected_gain'  => $projectedGain,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getIbcForlust: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid IBC-förlustkalkyl'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE: Teamkemi — vilka operatörer presterar bättre ihop? ────────────

    private function getOperatorSynergy(): void
    {
        try {
            $days = isset($_GET['days']) ? max(30, min(365, (int)$_GET['days'])) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            $stmt = $this->pdo->prepare("
                SELECT MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                       MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to AND drifttid > 0 AND ibc_ok > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $opShifts     = [];
            $pairTogether = [];

            foreach ($shifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min <= 0 || $ibc <= 0) continue;

                $ibcH = $ibc / ($min / 60.0);

                $ops = [];
                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $n = (int)($s[$pos] ?? 0);
                    if ($n > 0 && isset($opNames[$n])) $ops[] = $n;
                }
                $ops = array_values(array_unique($ops));

                foreach ($ops as $op) {
                    $opShifts[$op][] = $ibcH;
                }

                sort($ops);
                for ($i = 0; $i < count($ops); $i++) {
                    for ($j = $i + 1; $j < count($ops); $j++) {
                        $key = $ops[$i] . '-' . $ops[$j];
                        $pairTogether[$key][] = $ibcH;
                    }
                }
            }

            $opAvg = [];
            foreach ($opShifts as $opNum => $vals) {
                if (count($vals) >= 3) {
                    $opAvg[$opNum] = array_sum($vals) / count($vals);
                }
            }

            $pairs = [];
            foreach ($pairTogether as $key => $vals) {
                if (count($vals) < 3) continue;
                [$aStr, $bStr] = explode('-', $key);
                $opA = (int)$aStr;
                $opB = (int)$bStr;
                if (!isset($opAvg[$opA], $opAvg[$opB])) continue;

                $togetherIbcH = array_sum($vals) / count($vals);
                $avgBaseline  = ($opAvg[$opA] + $opAvg[$opB]) / 2.0;
                $synergy      = $togetherIbcH - $avgBaseline;
                $synergyPct   = $avgBaseline > 0 ? $synergy / $avgBaseline * 100.0 : 0.0;

                $pairs[] = [
                    'op_a'            => $opA,
                    'name_a'          => $opNames[$opA],
                    'op_b'            => $opB,
                    'name_b'          => $opNames[$opB],
                    'together_shifts' => count($vals),
                    'together_ibc_h'  => round($togetherIbcH, 2),
                    'a_avg_ibc_h'     => round($opAvg[$opA], 2),
                    'b_avg_ibc_h'     => round($opAvg[$opB], 2),
                    'avg_baseline'    => round($avgBaseline, 2),
                    'synergy'         => round($synergy, 2),
                    'synergy_pct'     => round($synergyPct, 1),
                ];
            }

            usort($pairs, fn($a, $b) => $b['synergy_pct'] <=> $a['synergy_pct']);

            $bestPartner = [];
            foreach ($pairs as $p) {
                $a = $p['op_a']; $b = $p['op_b'];
                if (!isset($bestPartner[$a]) || $p['synergy_pct'] > $bestPartner[$a]['synergy_pct']) {
                    $bestPartner[$a] = [
                        'partner_num'     => $b,
                        'partner_name'    => $opNames[$b],
                        'synergy_pct'     => $p['synergy_pct'],
                        'together_ibc_h'  => $p['together_ibc_h'],
                        'together_shifts' => $p['together_shifts'],
                    ];
                }
                if (!isset($bestPartner[$b]) || $p['synergy_pct'] > $bestPartner[$b]['synergy_pct']) {
                    $bestPartner[$b] = [
                        'partner_num'     => $a,
                        'partner_name'    => $opNames[$a],
                        'synergy_pct'     => $p['synergy_pct'],
                        'together_ibc_h'  => $p['together_ibc_h'],
                        'together_shifts' => $p['together_shifts'],
                    ];
                }
            }

            $operators = [];
            foreach ($opAvg as $opNum => $avg) {
                $bp = $bestPartner[$opNum] ?? null;
                $operators[] = [
                    'number'       => $opNum,
                    'name'         => $opNames[$opNum],
                    'total_shifts' => count($opShifts[$opNum]),
                    'avg_ibc_h'    => round($avg, 2),
                    'best_partner' => $bp,
                ];
            }
            usort($operators, fn($a, $b) => $b['avg_ibc_h'] <=> $a['avg_ibc_h']);

            echo json_encode([
                'success'   => true,
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
                'operators' => $operators,
                'pairs'     => $pairs,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorSynergy: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid teamkemi'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE: Skift-topplista — best/worst shifts leaderboard ────────────

    private function getSkiftTopplista(): void {
        try {
            $daysParam  = $_GET['days']  ?? '365';
            $limit      = max(5, min(50, (int)($_GET['limit'] ?? 20)));
            $minDrifttid = 30;

            if ($daysParam === 'all') {
                $dateFilter = '';
                $bindParams = [];
                $from = null;
                $to   = null;
            } else {
                $days = max(30, min(730, (int)$daysParam));
                $from = date('Y-m-d', strtotime("-{$days} days"));
                $to   = date('Y-m-d');
                $dateFilter = 'AND datum BETWEEN :from AND :to';
                $bindParams = [':from' => $from, ':to' => $to];
            }

            // Operator names
            $opRows  = $this->pdo->query("SELECT number, name FROM operators ORDER BY number")->fetchAll(\PDO::FETCH_ASSOC);
            $opNames = [];
            foreach ($opRows as $r) $opNames[(int)$r['number']] = $r['name'];

            // Deduplicated shifts, ordered best→worst
            $sql = "
                SELECT
                    skiftraknare,
                    MAX(datum)              AS datum,
                    MAX(op1)                AS op1,
                    MAX(op2)                AS op2,
                    MAX(op3)                AS op3,
                    MAX(ibc_ok)             AS ibc_ok,
                    MAX(ibc_ej_ok)          AS ibc_ej_ok,
                    MAX(drifttid)           AS drifttid,
                    MAX(driftstopptime)     AS driftstopptime
                FROM rebotling_skiftrapport
                WHERE drifttid >= :mindt {$dateFilter}
                GROUP BY skiftraknare
                HAVING MAX(ibc_ok) >= 1
                ORDER BY MAX(ibc_ok) / (MAX(drifttid) / 60.0) DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':mindt', $minDrifttid, \PDO::PARAM_INT);
            foreach ($bindParams as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build rows with computed fields
            $rows = [];
            foreach ($allShifts as $s) {
                $ibc = (int)$s['ibc_ok'];
                $min = (int)$s['drifttid'];
                if ($min <= 0) continue;
                $kas = max(0, (int)($s['ibc_ej_ok'] ?? 0));
                $tot = $ibc + $kas;
                $stopp = max(0, (int)($s['driftstopptime'] ?? 0));
                $rows[] = [
                    'skiftraknare'  => (int)$s['skiftraknare'],
                    'datum'         => $s['datum'],
                    'ibc_ok'        => $ibc,
                    'ibc_per_h'     => round($ibc / ($min / 60.0), 1),
                    'drifttid_min'  => $min,
                    'kassation_pct' => $tot > 0 ? round($kas / $tot * 100, 1) : 0.0,
                    'stopp_pct'     => round($stopp / $min * 100, 1),
                    'op1_name'      => $opNames[(int)$s['op1']] ?? null,
                    'op2_name'      => $opNames[(int)$s['op2']] ?? null,
                    'op3_name'      => $opNames[(int)$s['op3']] ?? null,
                ];
            }

            // Team average IBC/h for the period
            $totalIbc = 0; $totalMin = 0;
            foreach ($rows as $r) { $totalIbc += $r['ibc_ok']; $totalMin += $r['drifttid_min']; }
            $teamAvg = $totalMin > 0 ? round($totalIbc / ($totalMin / 60.0), 1) : 0.0;

            // Annotate vs team
            foreach ($rows as &$r) {
                $r['vs_team_pct'] = $teamAvg > 0 ? round(($r['ibc_per_h'] / $teamAvg - 1) * 100, 1) : 0.0;
            }
            unset($r);

            // Top N (already sorted best→worst), Bottom N (worst first)
            $top    = array_slice($rows, 0, $limit);
            $bottom = array_slice(array_reverse($rows), 0, $limit);

            echo json_encode([
                'success'       => true,
                'top'           => $top,
                'bottom'        => $bottom,
                'team_avg'      => $teamAvg,
                'total_shifts'  => count($rows),
                'from'          => $from,
                'to'            => $to,
                'days'          => $daysParam,
                'limit'         => $limit,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getSkiftTopplista: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skift-topplista'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getProduktionHeatmap(): void
    {
        try {
            $months = max(3, min(12, (int)($_GET['months'] ?? 6)));
            $from   = date('Y-m-d', strtotime("-{$months} months"));
            $to     = date('Y-m-d');

            // Aggregate per day using deduped skiftraknare
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    SUM(ibc_ok)   AS day_ibc,
                    SUM(drifttid) AS day_min,
                    COUNT(*)      AS skift_count
                FROM (
                    SELECT
                        datum,
                        MAX(ibc_ok)   AS ibc_ok,
                        MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid > 0
                    GROUP BY skiftraknare
                ) AS uniq
                GROUP BY datum
                ORDER BY datum ASC
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Overall team average IBC/h for the period
            $totalIbc = 0;
            $totalMin = 0;
            foreach ($rows as $r) {
                $totalIbc += (float)$r['day_ibc'];
                $totalMin += (float)$r['day_min'];
            }
            $teamAvg = $totalMin > 0 ? round($totalIbc / ($totalMin / 60.0), 1) : 0;

            $days = [];
            foreach ($rows as $r) {
                $ibcH   = (float)$r['day_min'] > 0
                    ? round((float)$r['day_ibc'] / ((float)$r['day_min'] / 60.0), 1)
                    : 0;
                $vsAvg  = $teamAvg > 0 ? (int)round(($ibcH / $teamAvg - 1) * 100) : 0;
                $days[] = [
                    'datum'       => $r['datum'],
                    'ibc_per_h'   => $ibcH,
                    'skift_count' => (int)$r['skift_count'],
                    'vs_avg'      => $vsAvg,
                ];
            }

            echo json_encode([
                'success'  => true,
                'days'     => $days,
                'team_avg' => $teamAvg,
                'from'     => $from,
                'to'       => $to,
                'months'   => $months,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getProduktionHeatmap: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktionsheatmap'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ── Skifttyp-analys per operatör ─────────────────────────────────────────
    // GET ?action=rebotling&run=operator-skifttyp[&days=90]
    // Returns per-operator IBC/h broken down by dag/kvall/natt shift type.
    // Uses SUM/SUM on unique shifts (GROUP BY skiftraknare) per position.
    private function getOperatorSkifttyp(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(14, min(365, (int)$_GET['days'])) : 90;
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            // Active operator names keyed by number
            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            if (empty($opMap)) {
                echo json_encode([
                    'success' => true, 'days' => $days,
                    'from' => $from, 'to' => $to,
                    'operators' => [], 'team_by_skifttyp' => [],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Aggregate per-operator per-shift-type, deduplicating by skiftraknare within each position.
            // ibc_ok is shared across all 3 positions in a shift — each operator gets credit for the full shift output.
            $sql = "
                SELECT
                    op_num,
                    skift_typ,
                    SUM(ibc_ok)                                     AS tot_ibc,
                    SUM(drifttid_min)                               AS tot_min,
                    SUM(ibc_ok) / NULLIF(SUM(drifttid_min) / 60.0, 0) AS ibc_per_h,
                    COUNT(*)                                        AS antal_skift
                FROM (
                    SELECT op1 AS op_num, skiftraknare,
                           CASE
                               WHEN HOUR(datum) >=  6 AND HOUR(datum) < 14 THEN 'dag'
                               WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                               ELSE 'natt'
                           END AS skift_typ,
                           MAX(COALESCE(ibc_ok, 0))    AS ibc_ok,
                           MAX(COALESCE(drifttid, 0))  AS drifttid_min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare,
                             CASE WHEN HOUR(datum) >= 6 AND HOUR(datum) < 14 THEN 'dag'
                                  WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                                  ELSE 'natt' END

                    UNION ALL

                    SELECT op2, skiftraknare,
                           CASE
                               WHEN HOUR(datum) >=  6 AND HOUR(datum) < 14 THEN 'dag'
                               WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                               ELSE 'natt'
                           END,
                           MAX(COALESCE(ibc_ok, 0)), MAX(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare,
                             CASE WHEN HOUR(datum) >= 6 AND HOUR(datum) < 14 THEN 'dag'
                                  WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                                  ELSE 'natt' END

                    UNION ALL

                    SELECT op3, skiftraknare,
                           CASE
                               WHEN HOUR(datum) >=  6 AND HOUR(datum) < 14 THEN 'dag'
                               WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                               ELSE 'natt'
                           END,
                           MAX(COALESCE(ibc_ok, 0)), MAX(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare,
                             CASE WHEN HOUR(datum) >= 6 AND HOUR(datum) < 14 THEN 'dag'
                                  WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                                  ELSE 'natt' END
                ) u
                WHERE op_num IS NOT NULL
                GROUP BY op_num, skift_typ
                HAVING antal_skift >= 2
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Structure: [op_num => [skift_typ => {ibc_per_h, antal_skift, tot_ibc, tot_min}]]
            $byOp = [];
            foreach ($rows as $r) {
                $num = (int)$r['op_num'];
                if (!isset($opMap[$num])) continue;
                if (!isset($byOp[$num])) $byOp[$num] = [];
                $byOp[$num][$r['skift_typ']] = [
                    'ibc_per_h'  => $r['ibc_per_h'] !== null ? round((float)$r['ibc_per_h'], 2) : 0.0,
                    'antal_skift'=> (int)$r['antal_skift'],
                    'tot_ibc'    => (int)$r['tot_ibc'],
                    'tot_min'    => (int)$r['tot_min'],
                ];
            }

            // Build operator array with overall IBC/h and per-type breakdown
            $operators = [];
            $skifttyper = ['dag', 'kvall', 'natt'];
            foreach ($byOp as $num => $typer) {
                // Overall: SUM across all shift types
                $totIbc = array_sum(array_column($typer, 'tot_ibc'));
                $totMin = array_sum(array_column($typer, 'tot_min'));
                $overallIbcH = $totMin > 0 ? round($totIbc / ($totMin / 60.0), 2) : 0.0;

                // Per-type enriched with vs_overall
                $enriched = [];
                $bestTyp = null;
                $bestDelta = null;
                foreach ($skifttyper as $typ) {
                    if (!isset($typer[$typ])) {
                        $enriched[$typ] = null;
                        continue;
                    }
                    $d = $typer[$typ];
                    $vsOverall = $overallIbcH > 0
                        ? round($d['ibc_per_h'] - $overallIbcH, 2)
                        : 0.0;
                    $enriched[$typ] = [
                        'ibc_per_h'   => $d['ibc_per_h'],
                        'antal_skift' => $d['antal_skift'],
                        'vs_overall'  => $vsOverall,
                    ];
                    if ($bestDelta === null || $vsOverall > $bestDelta) {
                        $bestDelta = $vsOverall;
                        $bestTyp   = $typ;
                    }
                }

                $operators[] = [
                    'number'       => $num,
                    'name'         => $opMap[$num],
                    'overall_ibc_h'=> $overallIbcH,
                    'best_skifttyp'=> $bestTyp,
                    'skifttyper'   => $enriched,
                ];
            }

            // Sort by name
            usort($operators, fn($a, $b) => strcmp($a['name'], $b['name']));

            // Team-level: per shift type across all operators (deduplicated shifts)
            $teamSql = "
                SELECT
                    skift_typ,
                    SUM(ibc_ok) / NULLIF(SUM(drifttid_min) / 60.0, 0) AS ibc_per_h,
                    COUNT(DISTINCT skiftraknare) AS antal_skift
                FROM (
                    SELECT skiftraknare,
                           CASE
                               WHEN HOUR(datum) >=  6 AND HOUR(datum) < 14 THEN 'dag'
                               WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                               ELSE 'natt'
                           END AS skift_typ,
                           MAX(COALESCE(ibc_ok, 0))   AS ibc_ok,
                           MAX(COALESCE(drifttid, 0)) AS drifttid_min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from4 AND :to4
                      AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare,
                             CASE WHEN HOUR(datum) >= 6 AND HOUR(datum) < 14 THEN 'dag'
                                  WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 'kvall'
                                  ELSE 'natt' END
                ) u
                GROUP BY skift_typ
            ";
            $tStmt = $pdo->prepare($teamSql);
            $tStmt->execute([':from4' => $from, ':to4' => $to]);
            $teamRows = $tStmt->fetchAll(\PDO::FETCH_ASSOC);

            $teamBySkifttyp = [];
            foreach ($teamRows as $r) {
                $teamBySkifttyp[$r['skift_typ']] = [
                    'ibc_per_h'  => $r['ibc_per_h'] !== null ? round((float)$r['ibc_per_h'], 2) : 0.0,
                    'antal_skift'=> (int)$r['antal_skift'],
                ];
            }

            echo json_encode([
                'success'          => true,
                'days'             => $days,
                'from'             => $from,
                'to'               => $to,
                'operators'        => $operators,
                'team_by_skifttyp' => $teamBySkifttyp,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            error_log('RebotlingController::getOperatorSkifttyp: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skifttyp-analys'], JSON_UNESCAPED_UNICODE);
        }
    }

}
