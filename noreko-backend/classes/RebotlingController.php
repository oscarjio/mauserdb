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
            } elseif ($action === 'rekord-statistik') {
                $this->getRekordsStatistik();
            } elseif ($action === 'skiftlag-historik') {
                $this->getSkiftlagHistorik();
            } elseif ($action === 'operator-momentum') {
                $this->getMomentum();
            } elseif ($action === 'operator-konsistens') {
                $this->getOperatorKonsistens();
            } elseif ($action === 'produkt-analys') {
                $this->getProduktAnalys();
            } elseif ($action === 'veckans-topplista') {
                $this->getVeckansTopplista();
            } elseif ($action === 'operator-avsaknad') {
                $this->getOperatorAvsaknad();
            } elseif ($action === 'kassations-karta') {
                $this->getKassationsKarta();
            } elseif ($action === 'kassation-trend') {
                $this->getKassationTrend();
            } elseif ($action === 'kassationsorsak-per-operator') {
                $this->getKassationsorsakPerOperator();
            } elseif ($action === 'coach-view') {
                $this->getCoachView();
            } elseif ($action === 'produktions-tidsserie') {
                $this->getProduktionsTidsserie();
            } elseif ($action === 'skift-insikt') {
                $this->getSkiftInsikt();
            } elseif ($action === 'rast-analys') {
                $this->getRastAnalys();
            } elseif ($action === 'sasongsanalys') {
                $this->getSasongsanalys();
            } elseif ($action === 'produktionsrytm') {
                $this->getProduktionsrytm();
            } elseif ($action === 'fart-kvalitet') {
                $this->getSpeedQualityCorrelation();
            } elseif ($action === 'stopptidsmonster') {
                $this->getStopptidsmonster();
            } elseif ($action === 'fart-produkt-matris') {
                $this->getSnabbhetProduktMatris();
            } elseif ($action === 'fart-stopp') {
                $this->getFartStoppKorrelation();
            } elseif ($action === 'kassation-stopp') {
                $this->getKassationStoppKorrelation();
            } elseif ($action === 'produktbyten') {
                $this->getProduktbytesanalys();
            } elseif ($action === 'tacknings-analys') {
                $this->getTackningsanalys();
            } elseif ($action === 'vader-produktion') {
                $this->getVaderProduktion();
            } elseif ($action === 'belastningsbalans') {
                $this->getBelastningsbalans();
            } elseif ($action === 'personal-kalender') {
                $this->getPersonalKalender();
            } elseif ($action === 'skift-sekvens') {
                $this->getSkiftSekvens();
            } elseif ($action === 'produktionsmaal') {
                $this->getProduktionsmaal();
            } elseif ($action === 'operator-utveckling') {
                $this->getOperatorUtveckling();
            } elseif ($action === 'veckosammanfattning') {
                $this->getVeckosammanfattning();
            } elseif ($action === 'oee-dashboard') {
                $this->getOEEDashboard();
            } elseif ($action === 'operator-rotation') {
                $this->getRotationsanalys();
            } elseif ($action === 'manads-jamforelse') {
                $this->getManadsJamforelse();
            } elseif ($action === 'skifttyps-matris') {
                $this->getSkifttypsMatris();
            } elseif ($action === 'kassationstyper') {
                $this->getKassationstyper();
            } elseif ($action === 'positions-specialisering') {
                $this->getPositionsSpecialisering();
            } elseif ($action === 'korstraning') {
                $this->getKorstraningsplan();
            } elseif ($action === 'skift-logg') {
                $this->getSkiftLogg();
            } elseif ($action === 'skift-avvikelser') {
                $this->getSkiftAvvikelser();
            } elseif ($action === 'stjarnoperatorer') {
                $this->getStjarnoperatorer();
            } elseif ($action === 'produkt-kvalitetstrender') {
                $this->getProduktKvalitetstrender();
            } elseif ($action === 'milstolpar') {
                $this->getMilstolpar();
            } elseif ($action === 'produkt-normaliserad') {
                $this->getProduktNormaliseradPrestanda();
            } elseif ($action === 'ars-kurva') {
                $this->getArsKurva();
            } elseif ($action === 'schema-rekommendationer') {
                $this->getSchemaRekommendationer();
            } elseif ($action === 'kassationsorsak-per-produkt') {
                $this->getKassationsorsakPerProdukt();
            } elseif ($action === 'idag') {
                $this->getIdag();
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
        } catch (\Throwable $e) {
            error_log('RebotlingController::getCachedSettingsAndWeather: ' . $e->getMessage());
            try {
                $this->ensureSettingsTable();
                $sRow = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sRow) $result['rebotlingTarget'] = (int)$sRow['rebotling_target'];
            } catch (\Throwable $e2) {
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

        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) { error_log('RebotlingController::getLiveRanking dagsMal: ' . $e->getMessage()); }

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

            // ibc_ok is a PLC daily running counter — MAX across all rows for the day
            // equals the day's total IBC (counter resets at midnight).
            // SUM(MAX per shift) over-counts multi-shift days (shift2 carries cumulative total).
            $sqlTotal = str_replace(':dateFrom', '?',
                "SELECT COALESCE(MAX(ibc_ok), 0)
                    FROM rebotling_skiftrapport
                    WHERE {$dateFilter}");
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        if (!in_array($weeks, [8, 16, 26, 52], true)) $weeks = 8;
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

            // --- Operatörens veckovisa IBC/h (dedup per skiftraknare) ---
            $sql = "
                SELECT
                    YEAR(datum)                                              AS yr,
                    WEEK(datum, 3)                                           AS wk,
                    MIN(CONCAT('V.', LPAD(WEEK(datum, 3), 2, '0')))       AS vecka_label,
                    SUM(ibc_ok)                                              AS total_ibc,
                    SUM(drifttid_min)                                        AS total_drifttid_min,
                    ROUND(
                        SUM(ibc_ok) / NULLIF(SUM(drifttid_min) / 60.0, 0),
                        2
                    )                                                        AS ibc_per_h,
                    ROUND(
                        SUM(ibc_ok) / NULLIF(SUM(totalt), 0) * 100,
                        1
                    )                                                        AS kvalitet_pct,
                    COUNT(*)                                                  AS antal_skift
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(ibc_ok)                AS ibc_ok,
                        MAX(COALESCE(totalt,  0))  AS totalt,
                        MAX(COALESCE(drifttid,0))  AS drifttid_min
                    FROM rebotling_skiftrapport
                    WHERE datum >= ?
                      AND (op1 = ? OR op2 = ? OR op3 = ?)
                      AND ibc_ok > 0
                    GROUP BY datum, skiftraknare
                    HAVING MAX(COALESCE(drifttid,0)) >= 30
                ) AS deduped
                GROUP BY yr, wk
                ORDER BY yr ASC, wk ASC
            ";

            $opStmt2 = $this->pdo->prepare($sql);
            $opStmt2->execute([$startDate, $opNumber, $opNumber, $opNumber]);
            $opRows = $opStmt2->fetchAll(PDO::FETCH_ASSOC);

            // --- Lagsnitt per vecka (alla skift, dedup per skiftraknare) ---
            $teamSql = "
                SELECT
                    YEAR(datum)                                              AS yr,
                    WEEK(datum, 3)                                           AS wk,
                    MIN(CONCAT('V.', LPAD(WEEK(datum, 3), 2, '0')))       AS vecka_label,
                    ROUND(
                        SUM(ibc_ok) / NULLIF(SUM(drifttid_min) / 60.0, 0),
                        2
                    )                                                        AS team_ibc_per_h,
                    COUNT(*)                                                  AS team_skift
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(ibc_ok)                AS ibc_ok,
                        MAX(COALESCE(drifttid,0))  AS drifttid_min
                    FROM rebotling_skiftrapport
                    WHERE datum >= ?
                      AND ibc_ok > 0
                    GROUP BY datum, skiftraknare
                    HAVING MAX(COALESCE(drifttid,0)) >= 30
                ) AS deduped
                GROUP BY yr, wk
                ORDER BY yr ASC, wk ASC
            ";
            $teamStmt = $this->pdo->prepare($teamSql);
            $teamStmt->execute([$startDate]);
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e2) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
            error_log('RebotlingController::getOperatorAnalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsanalys'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function queryOperatorPeriodStats(string $from, string $to): array {
        // ibc_ok (and ibc_ej_ok, bur_ej_ok) are PLC daily running counters.
        // LAG() computes per-shift deltas before expanding by operator position,
        // so later shifts on the same day are not over-credited.
        $sql = "
            SELECT op_nr, pos,
                   COUNT(*)         AS antal_skift,
                   SUM(shift_ibc)   AS ibc_ok,
                   SUM(shift_ej)    AS ibc_ej_ok,
                   SUM(shift_bur)   AS bur_ej_ok,
                   SUM(shift_tot)   AS totalt,
                   SUM(shift_drif)  AS drifttid_min,
                   SUM(shift_rast)  AS rasttime_min
            FROM (
                SELECT CASE p.pos WHEN 'op1' THEN op1 WHEN 'op2' THEN op2 ELSE op3 END AS op_nr,
                       p.pos,
                       shift_ibc, shift_ej, shift_bur, shift_tot, shift_drif, shift_rast
                FROM (
                    SELECT op1, op2, op3,
                           shift_drif, shift_bur, shift_tot, shift_rast,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc,
                           GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ej,
                           GREATEST(0, bur_end - COALESCE(LAG(bur_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_bur
                    FROM (
                        SELECT skiftraknare, DATE(MAX(datum)) AS dag,
                               MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                               MAX(ibc_ok)                  AS ibc_end,
                               MAX(COALESCE(ibc_ej_ok,  0)) AS ej_end,
                               MAX(COALESCE(bur_ej_ok,  0)) AS bur_end,
                               MAX(COALESCE(totalt,     0)) AS shift_tot,
                               MAX(COALESCE(drifttid,   0)) AS shift_drif,
                               MAX(COALESCE(rasttime,   0)) AS shift_rast
                        FROM rebotling_skiftrapport
                        WHERE datum BETWEEN :from1 AND :to1
                        GROUP BY skiftraknare
                        HAVING MAX(drifttid) >= 30
                    ) raw
                ) lag_vals
                JOIN (SELECT 'op1' AS pos UNION ALL SELECT 'op2' UNION ALL SELECT 'op3') p
                WHERE (p.pos = 'op1' AND op1 IS NOT NULL AND op1 > 0)
                   OR (p.pos = 'op2' AND op2 IS NOT NULL AND op2 > 0)
                   OR (p.pos = 'op3' AND op3 IS NOT NULL AND op3 > 0)
            ) combined
            GROUP BY op_nr, pos
            HAVING antal_skift > 0
            ORDER BY op_nr, pos
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from1' => $from, ':to1' => $to]);
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
        // LAG() must see ALL shifts of each day (not just this operator's) so the cumulative
        // ibc_ok delta is computed correctly. Operator filter applied after window function.
        $sql = "
            SELECT YEARWEEK(dag, 1) AS yw,
                   MIN(dag)         AS week_start,
                   COUNT(*)         AS antal_skift,
                   SUM(shift_ibc)   AS ibc_ok,
                   SUM(shift_drif)  AS drifttid_min
            FROM (
                SELECT dag, shift_drif, op1, op2, op3,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum))  AS dag,
                           MAX(op1)          AS op1,
                           MAX(op2)          AS op2,
                           MAX(op3)          AS op3,
                           MAX(ibc_ok)       AS ibc_end,
                           MAX(drifttid)     AS shift_drif
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to AND drifttid > 0
                    GROUP BY skiftraknare
                    HAVING MAX(drifttid) >= 30
                ) raw
            ) lag_vals
            WHERE :op1 IN (op1, op2, op3)
            GROUP BY yw
            ORDER BY yw ASC
            LIMIT 12
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':from' => $from, ':to' => $to,
            ':op1' => $opNr,
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

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta
            // so later shifts on the same day are not over-credited.
            $stmtShifts = $this->pdo->prepare("
                SELECT skiftraknare, dag AS datum,
                       op1, op2, op3, drifttid,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum))  AS dag,
                           MAX(op1)          AS op1,
                           MAX(op2)          AS op2,
                           MAX(op3)          AS op3,
                           MAX(ibc_ok)       AS ibc_end,
                           MAX(drifttid)     AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to AND drifttid > 0
                    GROUP BY skiftraknare
                ) raw
                ORDER BY dag ASC, skiftraknare ASC
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta
            // so later shifts are not over-credited with the full cumulative day total.
            $stmt = $this->pdo->prepare("
                SELECT op1, op2, op3, drifttid,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum))  AS dag,
                           MAX(op1)          AS op1,
                           MAX(op2)          AS op2,
                           MAX(op3)          AS op3,
                           MAX(ibc_ok)       AS ibc_end,
                           MAX(drifttid)     AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to AND drifttid > 0
                    GROUP BY skiftraknare
                ) raw
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Accumulate per operator per position (SUM/SUM, not AVG-of-rates)
            $opPosAcc = [];
            $teamAcc  = ['op1' => ['ibc'=>0,'min'=>0], 'op2' => ['ibc'=>0,'min'=>0], 'op3' => ['ibc'=>0,'min'=>0]];

            foreach ($shifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $num = (int)$s[$pos];
                    if ($num <= 0 || !isset($opNames[$num])) continue;

                    if (!isset($opPosAcc[$num][$pos])) {
                        $opPosAcc[$num][$pos] = ['ibc' => 0, 'min' => 0, 'skift' => 0];
                    }
                    $opPosAcc[$num][$pos]['ibc']   += $ibc;
                    $opPosAcc[$num][$pos]['min']   += $min;
                    $opPosAcc[$num][$pos]['skift'] += 1;

                    $teamAcc[$pos]['ibc'] += $ibc;
                    $teamAcc[$pos]['min'] += $min;
                }
            }

            // Team averages: SUM(total_ibc) / SUM(total_hours) per position
            $teamAvg = [];
            foreach ($teamAcc as $pos => $t) {
                $teamAvg[$pos] = $t['min'] > 0 ? $t['ibc'] / ($t['min'] / 60.0) : 0;
            }

            // Build per-operator per-position data
            $opPosData = [];
            foreach ($opPosAcc as $num => $posData) {
                foreach ($posData as $pos => $acc) {
                    $rate = $acc['min'] > 0 ? $acc['ibc'] / ($acc['min'] / 60.0) : 0;
                    $opPosData[$num][$pos] = [
                        'ibc_per_h'   => round($rate, 1),
                        'antal_skift' => $acc['skift'],
                    ];
                }
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

        } catch (\Throwable $e) {
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

            $opWhere    = $opFilter > 0 ? "AND (op1 = :op1 OR op2 = :op2 OR op3 = :op3)" : '';
            $opWhereCnt = $opFilter > 0 ? "AND (op1 = :opc1 OR op2 = :opc2 OR op3 = :opc3)" : '';

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
            if ($opFilter > 0) {
                $stmt->bindValue(':op1', $opFilter, \PDO::PARAM_INT);
                $stmt->bindValue(':op2', $opFilter, \PDO::PARAM_INT);
                $stmt->bindValue(':op3', $opFilter, \PDO::PARAM_INT);
            }
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
            if ($opFilter > 0) {
                $stmtCnt->bindValue(':opc1', $opFilter, \PDO::PARAM_INT);
                $stmtCnt->bindValue(':opc2', $opFilter, \PDO::PARAM_INT);
                $stmtCnt->bindValue(':opc3', $opFilter, \PDO::PARAM_INT);
            }
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter — LAG() over ALL shifts of the day gives
            // correct per-shift delta. Operator filter applied after LAG so the window sees
            // every shift on the day (not just shifts for this operator).
            $stmtShifts = $this->pdo->prepare("
                SELECT skiftraknare, dag AS datum, op1, op2, op3, drifttid, driftstopptime,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum))     AS dag,
                           MAX(op1)             AS op1,
                           MAX(op2)             AS op2,
                           MAX(op3)             AS op3,
                           MAX(ibc_ok)          AS ibc_end,
                           MAX(drifttid)        AS drifttid,
                           MAX(driftstopptime)  AS driftstopptime
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1 AND drifttid > 0
                    GROUP BY skiftraknare
                ) raw
                ORDER BY dag ASC, skiftraknare ASC
            ");
            $stmtShifts->execute([':from1' => $from, ':to1' => $to]);
            // Filter to only shifts where this operator appeared (done in PHP below)
            $opRows = array_values(array_filter(
                $stmtShifts->fetchAll(\PDO::FETCH_ASSOC),
                fn($r) => (int)$r['op1'] === $opNumber || (int)$r['op2'] === $opNumber || (int)$r['op3'] === $opNumber
            ));

            // Team data for position averages and "effect on team"
            $stmtTeam = $this->pdo->prepare("
                SELECT skiftraknare, dag AS datum, op1, op2, op3, drifttid,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum))  AS dag,
                           MAX(op1)          AS op1,
                           MAX(op2)          AS op2,
                           MAX(op3)          AS op3,
                           MAX(ibc_ok)       AS ibc_end,
                           MAX(drifttid)     AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2 AND drifttid > 0
                    GROUP BY skiftraknare
                ) raw
                ORDER BY dag ASC, skiftraknare ASC
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta so later
            // shifts in the same day are not over-credited with the cumulative total.
            $shiftSql = "
                SELECT skiftraknare, dag, op1, op2, op3, drifttid,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(drifttid)    AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to AND drifttid > 0
                    GROUP BY skiftraknare
                ) base
            ";
            $stmt = $this->pdo->prepare($shiftSql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // PHP attribution: build per-operator per-week accumulator.
            // Team accumulates once per shift (SUM/SUM, not AVG-of-ratios).
            $byOpRaw    = []; // [op_nr => [week_monday => ['ibc'=>int,'min'=>int,'skift'=>int]]]
            $teamWeekRaw = []; // [week_monday => ['ibc'=>int,'min'=>int]]

            foreach ($allShifts as $s) {
                $dag = $s['dag'];
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                // ISO week Monday
                $dt  = new \DateTime($dag);
                $dow = (int)$dt->format('N') - 1; // 0=Mon
                $dt->modify("-{$dow} days");
                $wm  = $dt->format('Y-m-d');

                if (!isset($teamWeekRaw[$wm])) $teamWeekRaw[$wm] = ['ibc' => 0, 'min' => 0];
                $teamWeekRaw[$wm]['ibc'] += $ibc;
                $teamWeekRaw[$wm]['min'] += $min;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0) continue;
                    if (!isset($byOpRaw[$opNum][$wm])) $byOpRaw[$opNum][$wm] = ['ibc' => 0, 'min' => 0, 'skift' => 0];
                    $byOpRaw[$opNum][$wm]['ibc']   += $ibc;
                    $byOpRaw[$opNum][$wm]['min']   += $min;
                    $byOpRaw[$opNum][$wm]['skift'] += 1;
                }
            }

            // Build $byOp in format the rest of the code expects
            $byOp = [];
            foreach ($byOpRaw as $num => $weeks) {
                foreach ($weeks as $wm => $d) {
                    $iph = $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 1) : 0.0;
                    $byOp[$num][$wm] = ['ibc_per_h' => $iph, 'antal_skift' => $d['skift']];
                }
            }

            // Team average IBC/h per week (SUM/SUM — not AVG-of-ratios)
            $teamAvgByWeek = [];
            foreach ($teamWeekRaw as $wm => $d) {
                $teamAvgByWeek[$wm] = $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 1) : null;
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta.
            // Closure gets shift-level data and expands to per-position rows (same shape as old UNION ALL).
            $buildLagShifts = function(string $fromD, string $toD): array {
                $sql = "
                    SELECT skiftraknare, dag, op1, op2, op3, drifttid,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                    FROM (
                        SELECT skiftraknare,
                               DATE(MAX(datum)) AS dag,
                               MAX(op1)         AS op1,
                               MAX(op2)         AS op2,
                               MAX(op3)         AS op3,
                               MAX(ibc_ok)      AS ibc_end,
                               MAX(drifttid)    AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE datum BETWEEN :from AND :to
                        GROUP BY skiftraknare
                        HAVING MAX(drifttid) >= 30
                    ) base
                ";
                $st = $this->pdo->prepare($sql);
                $st->execute([':from' => $fromD, ':to' => $toD]);
                $shifts = $st->fetchAll(\PDO::FETCH_ASSOC);

                // Expand to per-position rows (same shape as old UNION ALL output)
                $rows = [];
                foreach ($shifts as $s) {
                    $ibc = max(0, (int)$s['ibc_ok']);
                    $min = max(0, (int)$s['drifttid']);
                    foreach (['op1', 'op2', 'op3'] as $pos) {
                        $opNum = (int)$s[$pos];
                        if ($opNum <= 0) continue;
                        $rows[] = ['op_nr' => $opNum, 'pos' => $pos, 'ibc_ok' => $ibc, 'drifttid' => $min];
                    }
                }
                return $rows;
            };

            // Current month
            $curShifts = $buildLagShifts($from, $to);

            // Previous month
            $prevShifts = $buildLagShifts($prevFrom, $prevTo);


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

            $allTeamIbc = array_sum(array_column($posTeamRaw, 'ibc'));
            $allTeamMin = array_sum(array_column($posTeamRaw, 'min'));
            $summary = [
                'total_ibc'        => array_sum(array_column($results, 'ibc_totalt')),
                'team_avg_ibc_h'   => $allTeamMin > 0 ? round($allTeamIbc / ($allTeamMin / 60.0), 2) : null,
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter (does not reset per shift).
            // LAG() computes the per-shift delta so later shifts are not over-credited.
            $stmt = $this->pdo->prepare("
                SELECT op1, op2, op3, drifttid, dag AS datum,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(drifttid)    AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from AND datum <= :to
                      AND drifttid > 0
                    GROUP BY skiftraknare
                ) s
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

        } catch (\Throwable $e) {
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

            // ibc_ok/ibc_ej_ok are PLC daily running counters (do not reset per shift).
            // LAG() computes per-shift deltas so later shifts are not over-credited.
            $stmt = $this->pdo->prepare("
                SELECT op1, op2, op3, drifttid,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok,
                       GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ej_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(ibc_ej_ok)   AS ej_end,
                           MAX(drifttid)    AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from AND datum <= :to
                      AND drifttid > 0
                    GROUP BY skiftraknare
                ) s
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter (does not reset per shift).
            // LAG() computes the per-shift delta so later shifts are not over-credited.
            $stmt = $this->pdo->prepare("
                SELECT op1, op2, op3, drifttid, dag AS datum,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(drifttid)    AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from AND datum <= :to AND drifttid > 0
                    GROUP BY skiftraknare
                ) s
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) { /* table may not exist */ }

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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

            // Per-operator per-weekday — SUM/SUM aggregation to match team query
            $opDowStmt = $pdo->prepare("
                SELECT op_num, dow,
                       SUM(ibc_ok) / NULLIF(SUM(drifttid) / 60.0, 0) AS avg_ibc_h,
                       COUNT(*) AS shifts
                FROM (
                    SELECT op1 AS op_num, DAYOFWEEK(MAX(datum)) AS dow,
                           MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT op2, DAYOFWEEK(MAX(datum)),
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                    UNION ALL
                    SELECT op3, DAYOFWEEK(MAX(datum)),
                           MAX(ibc_ok), MAX(drifttid)
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

        } catch (\Throwable $e) {
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
            $mid  = date('Y-m-d', strtotime("-" . (int)($days / 2) . " days"));

            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            // ibc_ok, ibc_ej_ok, bur_ej_ok are PLC daily running counters — LAG() computes
            // per-shift deltas so later shifts are not over-credited with cumulative totals.
            $stmt = $pdo->prepare("
                SELECT skiftraknare, datum, op1, op2, op3,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY datum ORDER BY skiftraknare), 0)) AS ibc_ok,
                       GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY datum ORDER BY skiftraknare), 0)) AS ibc_ej_ok,
                       GREATEST(0, bur_end - COALESCE(LAG(bur_end) OVER (PARTITION BY datum ORDER BY skiftraknare), 0)) AS bur_ej_ok
                FROM (
                    SELECT skiftraknare,
                           MAX(datum)       AS datum,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(ibc_ej_ok)   AS ej_end,
                           MAX(bur_ej_ok)   AS bur_end
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid > 0
                    GROUP BY skiftraknare
                ) base
                ORDER BY datum, skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // PHP-side attribution: each operator (op1/op2/op3) in a shift gets credit for
            // the actual per-shift kassation delta. Trend split tracked by mid-date.
            $byOp = []; // [op_num => ['ibc'=>0,'ej'=>0,'bur'=>0,'skift'=>0,'early_ej'=>0,'early_prod'=>0,'late_ej'=>0,'late_prod'=>0]]

            foreach ($shifts as $s) {
                $ibc  = max(0, (int)$s['ibc_ok']);
                $ej   = max(0, (int)$s['ibc_ej_ok']);
                $bur  = max(0, (int)$s['bur_ej_ok']);
                $dag  = $s['datum'];
                $early = $dag < $mid;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($opMap[$opNum])) continue;
                    if (!isset($byOp[$opNum])) {
                        $byOp[$opNum] = ['ibc' => 0, 'ej' => 0, 'bur' => 0, 'skift' => 0,
                                         'early_ej' => 0, 'early_prod' => 0,
                                         'late_ej'  => 0, 'late_prod'  => 0];
                    }
                    $byOp[$opNum]['ibc']   += $ibc;
                    $byOp[$opNum]['ej']    += $ej;
                    $byOp[$opNum]['bur']   += $bur;
                    $byOp[$opNum]['skift'] += 1;
                    if ($early) {
                        $byOp[$opNum]['early_ej']   += $ej;
                        $byOp[$opNum]['early_prod']  += $ibc + $ej;
                    } else {
                        $byOp[$opNum]['late_ej']    += $ej;
                        $byOp[$opNum]['late_prod']   += $ibc + $ej;
                    }
                }
            }

            $operators   = [];
            $totalIbcOk  = 0;
            $totalIbcEj  = 0;
            $totalBurEj  = 0;

            foreach ($byOp as $num => $d) {
                if ($d['skift'] < 3) continue;

                $ibcOk = $d['ibc'];
                $ibcEj = $d['ej'];
                $burEj = $d['bur'];
                $prod  = $ibcOk + $ibcEj;
                $kassGrad = $prod > 0 ? round($ibcEj / $prod * 100, 2) : 0.0;

                $earlyKass = $d['early_prod'] > 0 ? $d['early_ej'] / $d['early_prod'] * 100 : null;
                $lateKass  = $d['late_prod']  > 0 ? $d['late_ej']  / $d['late_prod']  * 100 : null;
                $trend = 'stable';
                if ($earlyKass !== null && $lateKass !== null) {
                    $delta = $lateKass - $earlyKass;
                    if ($delta > 1.0)      $trend = 'worse';
                    elseif ($delta < -1.0) $trend = 'better';
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
                    'total_shifts'   => $d['skift'],
                    'kassationsgrad' => $kassGrad,
                    'trend'          => $trend,
                ];
            }

            $totalProd = $totalIbcOk + $totalIbcEj;
            $teamKass  = $totalProd > 0 ? round($totalIbcEj / $totalProd * 100, 2) : 0.0;

            foreach ($operators as &$op) {
                $op['vs_team'] = round($op['kassationsgrad'] - $teamKass, 2);
                $k = $op['kassationsgrad'];
                if ($k <= max(1.0, $teamKass * 0.6))  $op['status'] = 'bra';
                elseif ($k <= $teamKass * 1.3)         $op['status'] = 'normal';
                else                                    $op['status'] = 'hog';
            }
            unset($op);

            usort($operators, fn($a, $b) => $a['kassationsgrad'] <=> $b['kassationsgrad']);

            $best  = count($operators) ? $operators[0]['kassationsgrad'] : 0.0;
            $worst = count($operators) ? $operators[count($operators)-1]['kassationsgrad'] : 0.0;

            echo json_encode([
                'success'        => true,
                'days'           => $days,
                'from'           => $from,
                'to'             => $to,
                'operators'      => $operators,
                'team_kassgrad'  => $teamKass,
                'total_ibc_ok'   => $totalIbcOk,
                'total_ibc_ej'   => $totalIbcEj,
                'total_bur_ej'   => $totalBurEj,
                'best_kassgrad'  => $best,
                'worst_kassgrad' => $worst,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

            // SUM/SUM accumulators — avoids the AVG-of-ratios bias
            $opTotals   = [];   // opNum => ['ibc'=>int, 'min'=>int, 'count'=>int]
            $pairTotals = [];   // 'a-b'  => ['ibc'=>int, 'min'=>int, 'count'=>int]

            foreach ($shifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min <= 0 || $ibc <= 0) continue;

                $ops = [];
                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $n = (int)($s[$pos] ?? 0);
                    if ($n > 0 && isset($opNames[$n])) $ops[] = $n;
                }
                $ops = array_values(array_unique($ops));

                foreach ($ops as $op) {
                    if (!isset($opTotals[$op])) $opTotals[$op] = ['ibc' => 0, 'min' => 0, 'count' => 0];
                    $opTotals[$op]['ibc']   += $ibc;
                    $opTotals[$op]['min']   += $min;
                    $opTotals[$op]['count'] += 1;
                }

                sort($ops);
                for ($i = 0; $i < count($ops); $i++) {
                    for ($j = $i + 1; $j < count($ops); $j++) {
                        $key = $ops[$i] . '-' . $ops[$j];
                        if (!isset($pairTotals[$key])) $pairTotals[$key] = ['ibc' => 0, 'min' => 0, 'count' => 0];
                        $pairTotals[$key]['ibc']   += $ibc;
                        $pairTotals[$key]['min']   += $min;
                        $pairTotals[$key]['count'] += 1;
                    }
                }
            }

            $opAvg = [];
            foreach ($opTotals as $opNum => $t) {
                if ($t['count'] >= 3 && $t['min'] > 0) {
                    $opAvg[$opNum] = $t['ibc'] / ($t['min'] / 60.0);
                }
            }

            $pairs = [];
            foreach ($pairTotals as $key => $t) {
                if ($t['count'] < 3) continue;
                [$aStr, $bStr] = explode('-', $key);
                $opA = (int)$aStr;
                $opB = (int)$bStr;
                if (!isset($opAvg[$opA], $opAvg[$opB])) continue;

                $togetherIbcH = $t['min'] > 0 ? $t['ibc'] / ($t['min'] / 60.0) : 0;
                $avgBaseline  = ($opAvg[$opA] + $opAvg[$opB]) / 2.0;
                $synergy      = $togetherIbcH - $avgBaseline;
                $synergyPct   = $avgBaseline > 0 ? $synergy / $avgBaseline * 100.0 : 0.0;

                $pairs[] = [
                    'op_a'            => $opA,
                    'name_a'          => $opNames[$opA],
                    'op_b'            => $opB,
                    'name_b'          => $opNames[$opB],
                    'together_shifts' => $t['count'],
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
                    'total_shifts' => $opTotals[$opNum]['count'],
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
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

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta so later
            // shifts in the same day are not over-credited with the cumulative total.
            $shiftSql = "
                SELECT skiftraknare, dag, op1, op2, op3, drifttid, skift_typ,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(drifttid)    AS drifttid,
                           CASE
                               WHEN HOUR(MIN(created_at)) >=  6 AND HOUR(MIN(created_at)) < 14 THEN 'dag'
                               WHEN HOUR(MIN(created_at)) >= 14 AND HOUR(MIN(created_at)) < 22 THEN 'kvall'
                               ELSE 'natt'
                           END AS skift_typ
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to AND drifttid > 0 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) base
            ";
            $stmt = $pdo->prepare($shiftSql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // PHP-side attribution: each operator (op1/op2/op3) in a shift gets credit for
            // the actual per-shift ibc_ok delta. Team totals computed in the same loop.
            $byOp      = []; // [op_num => [skift_typ => ['ibc'=>int,'min'=>int,'skift'=>int]]]
            $teamByTyp = []; // [skift_typ => ['ibc'=>int,'min'=>int,'skift'=>int]]

            foreach ($shifts as $s) {
                $typ = $s['skift_typ'];
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;

                if (!isset($teamByTyp[$typ])) $teamByTyp[$typ] = ['ibc' => 0, 'min' => 0, 'skift' => 0];
                $teamByTyp[$typ]['ibc']   += $ibc;
                $teamByTyp[$typ]['min']   += $min;
                $teamByTyp[$typ]['skift'] += 1;

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($opMap[$opNum])) continue;
                    if (!isset($byOp[$opNum][$typ])) $byOp[$opNum][$typ] = ['ibc' => 0, 'min' => 0, 'skift' => 0];
                    $byOp[$opNum][$typ]['ibc']   += $ibc;
                    $byOp[$opNum][$typ]['min']   += $min;
                    $byOp[$opNum][$typ]['skift'] += 1;
                }
            }

            // Build operator array with overall IBC/h and per-type breakdown
            $operators  = [];
            $skifttyper = ['dag', 'kvall', 'natt'];
            foreach ($byOp as $num => $typer) {
                $totIbc = 0; $totMin = 0;
                foreach ($skifttyper as $typ) {
                    if (isset($typer[$typ])) { $totIbc += $typer[$typ]['ibc']; $totMin += $typer[$typ]['min']; }
                }
                $overallIbcH = $totMin > 0 ? round($totIbc / ($totMin / 60.0), 2) : 0.0;

                $enriched = []; $bestTyp = null; $bestDelta = null;
                foreach ($skifttyper as $typ) {
                    if (!isset($typer[$typ]) || $typer[$typ]['skift'] < 2) { $enriched[$typ] = null; continue; }
                    $d     = $typer[$typ];
                    $ibcH  = $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 2) : 0.0;
                    $vsOvr = $overallIbcH > 0 ? round($ibcH - $overallIbcH, 2) : 0.0;
                    $enriched[$typ] = ['ibc_per_h' => $ibcH, 'antal_skift' => $d['skift'], 'vs_overall' => $vsOvr];
                    if ($bestDelta === null || $vsOvr > $bestDelta) { $bestDelta = $vsOvr; $bestTyp = $typ; }
                }
                if ($bestTyp === null) continue; // no type with >= 2 shifts

                $operators[] = [
                    'number'        => $num,
                    'name'          => $opMap[$num],
                    'overall_ibc_h' => $overallIbcH,
                    'best_skifttyp' => $bestTyp,
                    'skifttyper'    => $enriched,
                ];
            }

            usort($operators, fn($a, $b) => strcmp($a['name'], $b['name']));

            // Team per-skifttyp from the same attribution loop (SUM/SUM, LAG-corrected)
            $teamBySkifttyp = [];
            foreach ($teamByTyp as $typ => $d) {
                $teamBySkifttyp[$typ] = [
                    'ibc_per_h'   => $d['min'] > 0 ? round($d['ibc'] / ($d['min'] / 60.0), 2) : 0.0,
                    'antal_skift' => $d['skift'],
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

        } catch (\Throwable $e) {
            error_log('RebotlingController::getOperatorSkifttyp: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skifttyp-analys'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // rekord-statistik — All-time records, career stats, monthly champions
    // ─────────────────────────────────────────────────────────────────────────
    private function getRekordsStatistik(): void {
        try {
            $pdo = $this->pdo;

            // ── 1. All-time top-10 shifts by IBC/h ───────────────────────────
            $bestSql = "
                SELECT
                    t.skiftraknare,
                    t.datum,
                    t.ibc_ok,
                    t.drifttid,
                    t.ibc_per_h,
                    t.op1, t.op2, t.op3,
                    COALESCE(o1.name, CONCAT('#', t.op1)) AS op1_name,
                    COALESCE(o2.name, CONCAT('#', t.op2)) AS op2_name,
                    COALESCE(o3.name, CONCAT('#', t.op3)) AS op3_name
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum)    AS datum,
                        MAX(ibc_ok)   AS ibc_ok,
                        MAX(drifttid) AS drifttid,
                        MAX(op1)      AS op1,
                        MAX(op2)      AS op2,
                        MAX(op3)      AS op3,
                        MAX(ibc_ok) / (MAX(drifttid) / 60.0) AS ibc_per_h
                    FROM rebotling_skiftrapport
                    WHERE drifttid >= 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                    HAVING MAX(drifttid) >= 30 AND MAX(ibc_ok) > 0
                    ORDER BY ibc_per_h DESC
                    LIMIT 10
                ) t
                LEFT JOIN operators o1 ON o1.number = t.op1
                LEFT JOIN operators o2 ON o2.number = t.op2
                LEFT JOIN operators o3 ON o3.number = t.op3
                ORDER BY t.ibc_per_h DESC
            ";
            $bestStmt = $pdo->prepare($bestSql);
            $bestStmt->execute();
            $bestRaw = $bestStmt->fetchAll(\PDO::FETCH_ASSOC);

            $bestShifts = [];
            foreach ($bestRaw as $i => $r) {
                $bestShifts[] = [
                    'rank'      => $i + 1,
                    'datum'     => $r['datum'],
                    'ibc_ok'    => (int)$r['ibc_ok'],
                    'drifttid'  => (int)$r['drifttid'],
                    'ibc_per_h' => round((float)$r['ibc_per_h'], 1),
                    'op1'       => (int)$r['op1'],
                    'op2'       => (int)$r['op2'],
                    'op3'       => (int)$r['op3'],
                    'op1_name'  => $r['op1_name'],
                    'op2_name'  => $r['op2_name'],
                    'op3_name'  => $r['op3_name'],
                ];
            }

            // ── 2. Career stats per operator (all-time) ──────────────────────
            // Each operator credited for every shift they appeared in (any position).
            // ibc_ok is team-shared; we use MAX per skiftraknare per position-session.
            $careerSql = "
                SELECT
                    src.op_num,
                    COALESCE(o.name, CONCAT('#', src.op_num)) AS op_name,
                    COUNT(DISTINCT src.skiftraknare)           AS career_shifts,
                    SUM(src.ibc_ok)                            AS career_ibc,
                    SUM(src.drifttid_h)                        AS career_hours,
                    CASE WHEN SUM(src.drifttid_h) > 0
                         THEN SUM(src.ibc_ok) / SUM(src.drifttid_h)
                         ELSE 0 END                            AS career_ibc_h,
                    MAX(src.shift_ibc_h)                       AS best_ibc_h
                FROM (
                    SELECT op1 AS op_num, skiftraknare,
                           MAX(ibc_ok)                                            AS ibc_ok,
                           MAX(drifttid) / 60.0                                   AS drifttid_h,
                           MAX(ibc_ok) / NULLIF(MAX(drifttid) / 60.0, 0)         AS shift_ibc_h
                    FROM rebotling_skiftrapport
                    WHERE op1 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2 AS op_num, skiftraknare,
                           MAX(ibc_ok)                                            AS ibc_ok,
                           MAX(drifttid) / 60.0                                   AS drifttid_h,
                           MAX(ibc_ok) / NULLIF(MAX(drifttid) / 60.0, 0)         AS shift_ibc_h
                    FROM rebotling_skiftrapport
                    WHERE op2 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3 AS op_num, skiftraknare,
                           MAX(ibc_ok)                                            AS ibc_ok,
                           MAX(drifttid) / 60.0                                   AS drifttid_h,
                           MAX(ibc_ok) / NULLIF(MAX(drifttid) / 60.0, 0)         AS shift_ibc_h
                    FROM rebotling_skiftrapport
                    WHERE op3 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare
                ) src
                LEFT JOIN operators o ON o.number = src.op_num
                GROUP BY src.op_num, o.name
                ORDER BY career_ibc DESC
            ";
            $careerStmt = $pdo->prepare($careerSql);
            $careerStmt->execute();
            $careerRaw = $careerStmt->fetchAll(\PDO::FETCH_ASSOC);

            $careerStats = [];
            foreach ($careerRaw as $idx => $r) {
                $careerStats[] = [
                    'rank'          => $idx + 1,
                    'number'        => (int)$r['op_num'],
                    'name'          => $r['op_name'],
                    'career_shifts' => (int)$r['career_shifts'],
                    'career_ibc'    => (int)$r['career_ibc'],
                    'career_hours'  => round((float)$r['career_hours'], 1),
                    'career_ibc_h'  => round((float)$r['career_ibc_h'], 2),
                    'best_ibc_h'    => round((float)$r['best_ibc_h'], 1),
                ];
            }

            // ── 3. Monthly champions (last 12 months) ────────────────────────
            $monthFrom = date('Y-m-01', strtotime('-11 months'));

            $monthlySql = "
                SELECT
                    src.month_str,
                    src.op_num,
                    COALESCE(o.name, CONCAT('#', src.op_num)) AS op_name,
                    SUM(src.ibc_ok)    AS month_ibc,
                    SUM(src.drifttid_h) AS month_hours,
                    CASE WHEN SUM(src.drifttid_h) > 0
                         THEN SUM(src.ibc_ok) / SUM(src.drifttid_h)
                         ELSE 0 END    AS month_ibc_h,
                    COUNT(DISTINCT src.skiftraknare) AS month_shifts
                FROM (
                    SELECT DATE_FORMAT(datum, '%Y-%m') AS month_str,
                           op1 AS op_num, skiftraknare,
                           MAX(ibc_ok)   AS ibc_ok,
                           MAX(drifttid) / 60.0 AS drifttid_h
                    FROM rebotling_skiftrapport
                    WHERE op1 > 0 AND datum >= :mfrom1 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY DATE_FORMAT(datum, '%Y-%m'), op1, skiftraknare
                    UNION ALL
                    SELECT DATE_FORMAT(datum, '%Y-%m') AS month_str,
                           op2 AS op_num, skiftraknare,
                           MAX(ibc_ok)   AS ibc_ok,
                           MAX(drifttid) / 60.0 AS drifttid_h
                    FROM rebotling_skiftrapport
                    WHERE op2 > 0 AND datum >= :mfrom2 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY DATE_FORMAT(datum, '%Y-%m'), op2, skiftraknare
                    UNION ALL
                    SELECT DATE_FORMAT(datum, '%Y-%m') AS month_str,
                           op3 AS op_num, skiftraknare,
                           MAX(ibc_ok)   AS ibc_ok,
                           MAX(drifttid) / 60.0 AS drifttid_h
                    FROM rebotling_skiftrapport
                    WHERE op3 > 0 AND datum >= :mfrom3 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY DATE_FORMAT(datum, '%Y-%m'), op3, skiftraknare
                ) src
                LEFT JOIN operators o ON o.number = src.op_num
                GROUP BY src.month_str, src.op_num, o.name
                HAVING month_shifts >= 3
                ORDER BY src.month_str ASC, month_ibc_h DESC
            ";
            $monthlyStmt = $pdo->prepare($monthlySql);
            $monthlyStmt->execute([':mfrom1' => $monthFrom, ':mfrom2' => $monthFrom, ':mfrom3' => $monthFrom]);
            $monthlyRaw = $monthlyStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Pick champion per month (first row = highest IBC/h due to ORDER BY)
            $byMonth = [];
            foreach ($monthlyRaw as $r) {
                $m = $r['month_str'];
                if (!isset($byMonth[$m])) {
                    $byMonth[$m] = [
                        'month'          => $m,
                        'champion_num'   => (int)$r['op_num'],
                        'champion_name'  => $r['op_name'],
                        'ibc_h'          => round((float)$r['month_ibc_h'], 2),
                        'month_shifts'   => (int)$r['month_shifts'],
                        'month_ibc'      => (int)$r['month_ibc'],
                    ];
                }
            }

            // Build full 12-month array (oldest → newest)
            $monthlyChampions = [];
            for ($i = 11; $i >= 0; $i--) {
                $m = date('Y-m', strtotime("-{$i} months"));
                if (isset($byMonth[$m])) {
                    $monthlyChampions[] = $byMonth[$m];
                } else {
                    $monthlyChampions[] = [
                        'month'         => $m,
                        'champion_num'  => null,
                        'champion_name' => null,
                        'ibc_h'         => null,
                        'month_shifts'  => 0,
                        'month_ibc'     => 0,
                    ];
                }
            }

            echo json_encode([
                'success'           => true,
                'best_shifts'       => $bestShifts,
                'career_stats'      => $careerStats,
                'monthly_champions' => $monthlyChampions,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getRekordsStatistik: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid rekord-statistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // skiftlag-historik — Historical 3-person team composition performance
    // ─────────────────────────────────────────────────────────────────────────
    private function getSkiftlagHistorik(): void {
        try {
            $pdo  = $this->pdo;
            $days = (int)($_GET['days'] ?? 365);
            $days = in_array($days, [90, 180, 365, 730]) ? $days : 365;

            // Deduplicate by skiftraknare, then canonically sort the 3 operators
            // so team {3,5,7} and {5,3,7} are treated as the same team.
            $sql = "
                SELECT
                    d.min_op, d.mid_op, d.max_op,
                    SUM(d.ibc_ok) / NULLIF(SUM(d.drifttid / 60.0), 0) AS avg_ibc_h,
                    SUM(d.ibc_ok)            AS total_ibc,
                    SUM(d.drifttid / 60.0)   AS total_hours,
                    COUNT(*)                 AS total_shifts,
                    MIN(d.datum)             AS first_shift,
                    MAX(d.datum)             AS last_shift
                FROM (
                    SELECT
                        skiftraknare,
                        MIN(datum)    AS datum,
                        MAX(ibc_ok)   AS ibc_ok,
                        MAX(drifttid) AS drifttid,
                        LEAST(MAX(op1), MAX(op2), MAX(op3))       AS min_op,
                        CASE
                            WHEN MAX(op1) NOT IN (LEAST(MAX(op1),MAX(op2),MAX(op3)), GREATEST(MAX(op1),MAX(op2),MAX(op3))) THEN MAX(op1)
                            WHEN MAX(op2) NOT IN (LEAST(MAX(op1),MAX(op2),MAX(op3)), GREATEST(MAX(op1),MAX(op2),MAX(op3))) THEN MAX(op2)
                            ELSE MAX(op3)
                        END                                        AS mid_op,
                        GREATEST(MAX(op1), MAX(op2), MAX(op3))    AS max_op
                    FROM rebotling_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND drifttid >= 30
                      AND ibc_ok  >  0
                      AND op1     >  0 AND op2 > 0 AND op3 > 0
                      AND op1    <> op2 AND op2 <> op3 AND op1 <> op3
                    GROUP BY skiftraknare
                ) d
                GROUP BY d.min_op, d.mid_op, d.max_op
                HAVING total_shifts >= 2
                ORDER BY avg_ibc_h DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Collect all operator numbers to look up names
            $nums = [];
            foreach ($rows as $r) {
                $nums[] = (int)$r['min_op'];
                $nums[] = (int)$r['mid_op'];
                $nums[] = (int)$r['max_op'];
            }
            $nums = array_values(array_unique($nums));
            $opNames = [];
            if (!empty($nums)) {
                $ph    = implode(',', array_fill(0, count($nums), '?'));
                $nStmt = $pdo->prepare("SELECT number, name FROM operators WHERE number IN ($ph)");
                $nStmt->execute($nums);
                foreach ($nStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $opNames[(int)$r['number']] = $r['name'];
                }
            }

            // Global average IBC/h for the same period (for vs_team %)
            $gStmt = $pdo->prepare("
                SELECT SUM(ibc_ok) / NULLIF(SUM(drifttid / 60.0), 0) AS global_avg
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :gdays DAY)
                      AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) g
            ");
            $gStmt->bindValue(':gdays', $days, \PDO::PARAM_INT);
            $gStmt->execute();
            $globalAvg = (float)($gStmt->fetchColumn() ?? 0);

            $teams = [];
            foreach ($rows as $r) {
                $minOp    = (int)$r['min_op'];
                $midOp    = (int)$r['mid_op'];
                $maxOp    = (int)$r['max_op'];
                $avgIbcH  = round((float)$r['avg_ibc_h'], 2);
                $vsTeam   = $globalAvg > 0 ? round(($avgIbcH - $globalAvg) / $globalAvg * 100, 1) : 0.0;
                $teams[]  = [
                    'min_op'       => $minOp,
                    'mid_op'       => $midOp,
                    'max_op'       => $maxOp,
                    'min_name'     => $opNames[$minOp] ?? "#$minOp",
                    'mid_name'     => $opNames[$midOp] ?? "#$midOp",
                    'max_name'     => $opNames[$maxOp] ?? "#$maxOp",
                    'avg_ibc_h'    => $avgIbcH,
                    'total_ibc'    => (int)$r['total_ibc'],
                    'total_hours'  => round((float)$r['total_hours'], 1),
                    'total_shifts' => (int)$r['total_shifts'],
                    'first_shift'  => $r['first_shift'],
                    'last_shift'   => $r['last_shift'],
                    'vs_team'      => $vsTeam,
                ];
            }

            echo json_encode([
                'success'     => true,
                'teams'       => $teams,
                'global_avg'  => round($globalAvg, 2),
                'days'        => $days,
                'from'        => date('Y-m-d', strtotime("-{$days} days")),
                'to'          => date('Y-m-d'),
                'total_teams' => count($teams),
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSkiftlagHistorik: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skiftlag-historik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Skiftmomentum — streak analysis per operator
    // GET ?action=rebotling&run=operator-momentum&days=90
    // Returns current streak, max streak, last-20-shifts array per operator
    // =========================================================
    private function getMomentum(): void {
        try {
            $days = max(30, min(365, (int)($_GET['days'] ?? 90)));
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            // Active operator names
            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $operatorNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $operatorNames[(int)$op['number']] = $op['name'];
            }

            // Team avg IBC/h for the period (SUM/SUM on deduplicated skiftraknare)
            $ts = $this->pdo->prepare("
                SELECT SUM(ibc_ok) AS total_ibc, SUM(drifttid) AS total_min
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from AND datum <= :to AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) t
            ");
            $ts->execute([':from' => $from, ':to' => $to]);
            $teamRow = $ts->fetch(\PDO::FETCH_ASSOC);
            $teamAvg = ($teamRow && (float)$teamRow['total_min'] > 0)
                ? (float)$teamRow['total_ibc'] / ((float)$teamRow['total_min'] / 60.0) : 0.0;

            if ($teamAvg <= 0) {
                echo json_encode(['success' => true, 'operators' => [], 'team_avg' => 0, 'days' => $days], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Per-operator, per-shift data (chronological) — unique named params for HY093 safety
            $stmt = $this->pdo->prepare("
                SELECT op_num, skiftraknare, datum, ibc_ok, drifttid
                FROM (
                    SELECT op1 AS op_num, skiftraknare, MIN(datum) AS datum,
                           MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from1 AND datum <= :to1
                      AND op1 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2, skiftraknare, MIN(datum),
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from2 AND datum <= :to2
                      AND op2 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3, skiftraknare, MIN(datum),
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from3 AND datum <= :to3
                      AND op3 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare
                ) sub
                ORDER BY op_num, datum
            ");
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Group shifts by operator
            $byOp = [];
            foreach ($rows as $r) {
                $opNum = (int)$r['op_num'];
                if (!isset($operatorNames[$opNum])) continue;
                $min = (float)$r['drifttid'];
                $ibcH = $min > 0 ? round((float)$r['ibc_ok'] / ($min / 60.0), 2) : 0.0;
                $byOp[$opNum][] = [
                    'datum'      => substr($r['datum'], 0, 10),
                    'ibc_per_h'  => $ibcH,
                    'above_avg'  => $ibcH >= $teamAvg,
                ];
            }

            // Calculate streaks per operator
            $operators = [];
            foreach ($byOp as $opNum => $shifts) {
                $n = count($shifts);
                if ($n < 2) continue;

                // Current streak — count backwards from last shift
                $currentDir = $shifts[$n - 1]['above_avg'];
                $current = 0;
                for ($i = $n - 1; $i >= 0; $i--) {
                    if ($shifts[$i]['above_avg'] === $currentDir) $current++;
                    else break;
                }

                // Max above-avg streak
                $maxStreak = 0;
                $runLen = 0;
                foreach ($shifts as $s) {
                    if ($s['above_avg']) { $runLen++; $maxStreak = max($maxStreak, $runLen); }
                    else { $runLen = 0; }
                }

                // Hit rate (% shifts above avg)
                $totalAbove = 0;
                foreach ($shifts as $s) { if ($s['above_avg']) $totalAbove++; }
                $hitRate = (int)round($totalAbove / $n * 100);

                // Last 20 shifts as 0/1 array (oldest to newest)
                $last20 = array_slice($shifts, -20);
                $recent = array_map(fn($s) => $s['above_avg'] ? 1 : 0, $last20);

                // Status badge
                if ($current >= 5 && $currentDir)  $status = 'het';
                elseif ($current >= 2 && $currentDir)  $status = 'varm';
                elseif ($current >= 5 && !$currentDir) $status = 'kall';
                elseif ($current >= 2 && !$currentDir) $status = 'sval';
                else $status = 'neutral';

                $operators[] = [
                    'number'         => $opNum,
                    'name'           => $operatorNames[$opNum],
                    'antal_skift'    => $n,
                    'current_streak' => $current,
                    'current_above'  => $currentDir,
                    'max_streak'     => $maxStreak,
                    'hit_rate'       => $hitRate,
                    'recent'         => $recent,
                    'last_datum'     => $shifts[$n - 1]['datum'],
                    'status'         => $status,
                ];
            }

            // Sort: hot/cold first (by status priority), then by streak length
            $prio = ['het' => 0, 'kall' => 1, 'varm' => 2, 'sval' => 3, 'neutral' => 4];
            usort($operators, function ($a, $b) use ($prio) {
                $pa = $prio[$a['status']] ?? 4;
                $pb = $prio[$b['status']] ?? 4;
                if ($pa !== $pb) return $pa - $pb;
                return $b['current_streak'] - $a['current_streak'];
            });

            echo json_encode([
                'success'   => true,
                'operators' => $operators,
                'team_avg'  => round($teamAvg, 2),
                'days'      => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getMomentum: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid momentumanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getOperatorKonsistens(): void
    {
        try {
            $days = max(30, min(365, (int)($_GET['days'] ?? 90)));
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $opNames[(int)$op['number']] = $op['name'];
            }

            // Team avg IBC/h (SUM/SUM on deduplicated skiftraknare)
            $ts = $this->pdo->prepare("
                SELECT SUM(ibc_ok) AS total_ibc, SUM(drifttid) AS total_min
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from AND datum <= :to AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) t
            ");
            $ts->execute([':from' => $from, ':to' => $to]);
            $teamRow = $ts->fetch(\PDO::FETCH_ASSOC);
            $teamAvgIbch = ($teamRow && (float)$teamRow['total_min'] > 0)
                ? (float)$teamRow['total_ibc'] / ((float)$teamRow['total_min'] / 60.0)
                : 0.0;

            // Per-operator per-shift IBC/h (unique named params: HY093-safe)
            $stmt = $this->pdo->prepare("
                SELECT op_num, ibc_ok, drifttid
                FROM (
                    SELECT op1 AS op_num, skiftraknare,
                           MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from1 AND datum <= :to1
                      AND op1 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2, skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from2 AND datum <= :to2
                      AND op2 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3, skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from3 AND datum <= :to3
                      AND op3 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare
                ) sub
            ");
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Collect per-shift IBC/h values per operator
            $opVals = [];
            foreach ($rows as $r) {
                $opNum = (int)$r['op_num'];
                $dt    = (float)$r['drifttid'];
                if ($dt <= 0 || !isset($opNames[$opNum])) continue;
                $opVals[$opNum][] = (float)$r['ibc_ok'] / ($dt / 60.0);
            }

            $operators = [];
            foreach ($opVals as $opNum => $vals) {
                if (count($vals) < 3) continue;

                $n    = count($vals);
                $mean = array_sum($vals) / $n;
                $var  = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / $n;
                $std  = sqrt($var);
                $cv   = $mean > 0 ? ($std / $mean * 100.0) : 0.0;

                $sorted = $vals;
                sort($sorted);
                $minVal = $sorted[0];
                $maxVal = $sorted[$n - 1];

                if ($cv <= 15)      $badge = 'mycket_konsekvent';
                elseif ($cv <= 25)  $badge = 'konsekvent';
                elseif ($cv <= 35)  $badge = 'variabel';
                else                $badge = 'oforutsagbar';

                $operators[] = [
                    'number'    => $opNum,
                    'name'      => $opNames[$opNum],
                    'avg_ibc_h' => round($mean, 2),
                    'stddev'    => round($std, 2),
                    'cv'        => round($cv, 1),
                    'min_ibc_h' => round($minVal, 2),
                    'max_ibc_h' => round($maxVal, 2),
                    'range'     => round($maxVal - $minVal, 2),
                    'shifts'    => $n,
                    'badge'     => $badge,
                    'vs_team'   => $teamAvgIbch > 0 ? round(($mean - $teamAvgIbch) / $teamAvgIbch * 100.0, 1) : 0.0,
                ];
            }

            usort($operators, fn($a, $b) => $a['cv'] <=> $b['cv']);

            $teamCv = count($operators) > 0
                ? array_sum(array_column($operators, 'cv')) / count($operators)
                : 0.0;

            echo json_encode([
                'success'      => true,
                'from'         => $from,
                'to'           => $to,
                'days'         => $days,
                'team_avg_ibch'=> round($teamAvgIbch, 2),
                'team_avg_cv'  => round($teamCv, 1),
                'operators'    => $operators,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getOperatorKonsistens: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid konsistensanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getProduktAnalys(): void {
        try {
            $days = max(30, min(730, (int)($_GET['days'] ?? 90)));
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Deduplicate by skiftraknare, then aggregate per product
            $stmt = $this->pdo->prepare("
                SELECT
                    d.product_id,
                    COALESCE(p.name, CONCAT('Produkt ', d.product_id))         AS product_name,
                    COALESCE(p.cycle_time_minutes, 0)                           AS cycle_time_minutes,
                    COUNT(*)                                                    AS antal_skift,
                    SUM(d.ibc_ok)                                               AS total_ibc_ok,
                    SUM(d.ibc_ej_ok)                                            AS total_ibc_ej,
                    SUM(d.bur_ej_ok)                                            AS total_bur_ej,
                    SUM(d.drifttid)                                             AS total_drifttid_min,
                    SUM(d.driftstopptime)                                       AS total_stopptid_min,
                    SUM(d.ibc_ok) / NULLIF(SUM(d.drifttid) / 60.0, 0)         AS ibc_per_h,
                    CASE WHEN SUM(d.ibc_ok) + SUM(d.ibc_ej_ok) > 0
                         THEN SUM(d.ibc_ej_ok) * 100.0 / (SUM(d.ibc_ok) + SUM(d.ibc_ej_ok))
                         ELSE 0 END                                             AS kassgrad
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(product_id)      AS product_id,
                        MAX(ibc_ok)          AS ibc_ok,
                        MAX(ibc_ej_ok)       AS ibc_ej_ok,
                        MAX(bur_ej_ok)       AS bur_ej_ok,
                        MAX(drifttid)        AS drifttid,
                        MAX(driftstopptime)  AS driftstopptime
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from AND datum <= :to
                      AND drifttid >= 30
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare
                ) d
                LEFT JOIN rebotling_products p ON p.id = d.product_id
                GROUP BY d.product_id, p.name, p.cycle_time_minutes
                HAVING antal_skift >= 1
                ORDER BY ibc_per_h DESC
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode([
                    'success' => true, 'products' => [], 'trend' => [],
                    'overall_ibc_h' => 0, 'from' => $from, 'to' => $to, 'days' => $days,
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Overall IBC/h across all products for vs-comparison
            $totalIbc = array_sum(array_column($rows, 'total_ibc_ok'));
            $totalMin = array_sum(array_column($rows, 'total_drifttid_min'));
            $overallIbcH = $totalMin > 0 ? $totalIbc / ($totalMin / 60.0) : 0.0;

            $products = [];
            foreach ($rows as $r) {
                $ibcH    = $r['total_drifttid_min'] > 0 ? (float)$r['ibc_per_h'] : 0.0;
                $vsAvg   = $overallIbcH > 0 ? round(($ibcH / $overallIbcH - 1) * 100, 1) : 0.0;
                $cycleM  = (float)$r['cycle_time_minutes'];
                $expectedH = $cycleM > 0 ? round(60.0 / $cycleM, 2) : null;
                $products[] = [
                    'product_id'         => (int)$r['product_id'],
                    'name'               => $r['product_name'],
                    'cycle_time_minutes' => $cycleM,
                    'expected_ibc_h'     => $expectedH,
                    'antal_skift'        => (int)$r['antal_skift'],
                    'total_ibc_ok'       => (int)$r['total_ibc_ok'],
                    'total_ibc_ej'       => (int)$r['total_ibc_ej'],
                    'total_bur_ej'       => (int)$r['total_bur_ej'],
                    'total_drifttid_h'   => round((float)$r['total_drifttid_min'] / 60.0, 1),
                    'total_stopptid_h'   => round((float)$r['total_stopptid_min'] / 60.0, 1),
                    'ibc_per_h'          => round($ibcH, 2),
                    'kassgrad'           => round((float)$r['kassgrad'], 2),
                    'vs_avg_pct'         => $vsAvg,
                ];
            }

            // Monthly trend (last 6 months, fixed window regardless of $days)
            $from6 = date('Y-m-d', strtotime('-180 days'));
            $tStmt = $this->pdo->prepare("
                SELECT
                    d.product_id,
                    YEAR(d.datum)  AS yr,
                    MONTH(d.datum) AS mo,
                    COUNT(*)       AS antal_skift,
                    SUM(d.ibc_ok) / NULLIF(SUM(d.drifttid) / 60.0, 0) AS ibc_per_h
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(datum)        AS datum,
                        MAX(product_id)   AS product_id,
                        MAX(ibc_ok)       AS ibc_ok,
                        MAX(drifttid)     AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= :from6 AND datum <= :to6
                      AND drifttid >= 30
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare
                ) d
                GROUP BY d.product_id, yr, mo
                ORDER BY d.product_id, yr, mo
            ");
            $tStmt->execute([':from6' => $from6, ':to6' => $to]);

            $trend = [];
            foreach ($tStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $pid = (int)$r['product_id'];
                if (!isset($trend[$pid])) $trend[$pid] = [];
                $trend[$pid][] = [
                    'yr'          => (int)$r['yr'],
                    'mo'          => (int)$r['mo'],
                    'antal_skift' => (int)$r['antal_skift'],
                    'ibc_per_h'   => round((float)$r['ibc_per_h'], 2),
                ];
            }

            echo json_encode([
                'success'       => true,
                'products'      => $products,
                'trend'         => $trend,
                'overall_ibc_h' => round($overallIbcH, 2),
                'from'          => $from,
                'to'            => $to,
                'days'          => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktAnalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getVeckansTopplista(): void
    {
        try {
            $weeks = isset($_GET['weeks']) ? max(4, min(26, (int)$_GET['weeks'])) : 12;

            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$weeks} weeks Monday", strtotime('Monday this week')));

            // Fetch operator names
            $opRows  = $this->pdo->query("SELECT number, name FROM operators ORDER BY number")->fetchAll(\PDO::FETCH_ASSOC);
            $opNames = [];
            foreach ($opRows as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // One row per unique shift (deduped by skiftraknare)
            $stmt = $this->pdo->prepare("
                SELECT
                    YEARWEEK(datum, 1)  AS yw,
                    datum               AS week_start,
                    MAX(op1)            AS op1,
                    MAX(op2)            AS op2,
                    MAX(op3)            AS op3,
                    MAX(ibc_ok)         AS ibc_ok,
                    MAX(drifttid)       AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from AND :to
                  AND drifttid >= 30
                GROUP BY skiftraknare
                ORDER BY datum ASC
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Accumulate per-week per-operator: sum IBC and hours
            $weekInfo  = [];  // yw => ['week_start' => ..., 'ops' => [opNum => ['ibc'=>sum,'min'=>sum]]]
            $teamByWeek = []; // yw => ['ibc'=>sum,'min'=>sum]

            foreach ($rows as $r) {
                $yw  = $r['yw'];
                $ibc = max(0, (int)$r['ibc_ok']);
                $min = max(0, (int)$r['drifttid']);
                if ($min <= 0 || $ibc <= 0) continue;

                if (!isset($weekInfo[$yw])) {
                    $weekInfo[$yw]   = ['yw' => $yw, 'week_start' => $r['week_start'], 'ops' => []];
                    $teamByWeek[$yw] = ['ibc' => 0, 'min' => 0];
                }

                $teamByWeek[$yw]['ibc'] += $ibc;
                $teamByWeek[$yw]['min'] += $min;

                $seen = [];
                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $n = (int)($r[$pos] ?? 0);
                    if ($n <= 0 || !isset($opNames[$n]) || in_array($n, $seen, true)) continue;
                    $seen[] = $n;
                    if (!isset($weekInfo[$yw]['ops'][$n])) {
                        $weekInfo[$yw]['ops'][$n] = ['ibc' => 0, 'min' => 0];
                    }
                    $weekInfo[$yw]['ops'][$n]['ibc'] += $ibc;
                    $weekInfo[$yw]['ops'][$n]['min'] += $min;
                }
            }

            // Build per-week result
            $weekResults = [];
            $winCounts   = [];
            $allOpIbc    = [];  // opNum => ['ibc'=>sum,'min'=>sum] across all weeks
            $totalIbc    = 0;
            $totalMin    = 0;

            foreach ($weekInfo as $yw => $wi) {
                $teamIbc = $teamByWeek[$yw]['ibc'];
                $teamMin = $teamByWeek[$yw]['min'];
                $teamIbcH = $teamMin > 0 ? $teamIbc / ($teamMin / 60.0) : 0.0;

                $totalIbc += $teamIbc;
                $totalMin += $teamMin;

                $opResults = [];
                foreach ($wi['ops'] as $opNum => $agg) {
                    if ($agg['min'] < 30) continue;
                    $ibcH = $agg['ibc'] / ($agg['min'] / 60.0);
                    $opResults[] = [
                        'number'   => $opNum,
                        'name'     => $opNames[$opNum],
                        'ibc_ok'   => $agg['ibc'],
                        'ibc_per_h' => round($ibcH, 1),
                        'min'      => $agg['min'],
                        'vs_team'  => $teamIbcH > 0 ? round(($ibcH / $teamIbcH - 1) * 100, 1) : 0.0,
                    ];

                    if (!isset($allOpIbc[$opNum])) $allOpIbc[$opNum] = ['ibc' => 0, 'min' => 0];
                    $allOpIbc[$opNum]['ibc'] += $agg['ibc'];
                    $allOpIbc[$opNum]['min'] += $agg['min'];
                }

                if (empty($opResults)) continue;

                usort($opResults, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);
                $winner = $opResults[0];

                if (!isset($winCounts[$winner['number']])) {
                    $winCounts[$winner['number']] = ['number' => $winner['number'], 'name' => $winner['name'], 'wins' => 0];
                }
                $winCounts[$winner['number']]['wins']++;

                // ISO week number from YEARWEEK(...,1): last 2 digits
                $isoWeek  = (int)substr((string)$yw, 4, 2);
                $isoYear  = (int)substr((string)$yw, 0, 4);
                $weekLabel = "v{$isoWeek} {$isoYear}";

                $weekResults[] = [
                    'yw'         => $yw,
                    'week_label' => $weekLabel,
                    'week_start' => $wi['week_start'],
                    'winner'     => $winner,
                    'runners_up' => array_slice($opResults, 1, 2),
                    'team_ibc_h' => round($teamIbcH, 1),
                    'team_ibc'   => $teamIbc,
                ];
            }

            // Sort weeks newest first
            usort($weekResults, fn($a, $b) => $b['yw'] <=> $a['yw']);

            // Sort win-counts highest first
            usort($winCounts, fn($a, $b) => $b['wins'] <=> $a['wins']);

            // Overall per-operator IBC/h for the period
            $opOverall = [];
            foreach ($allOpIbc as $opNum => $agg) {
                if ($agg['min'] < 30) continue;
                $opOverall[] = [
                    'number'    => $opNum,
                    'name'      => $opNames[$opNum],
                    'ibc_per_h' => round($agg['ibc'] / ($agg['min'] / 60.0), 1),
                ];
            }
            usort($opOverall, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

            $overallTeamIbcH = $totalMin > 0 ? round($totalIbc / ($totalMin / 60.0), 1) : 0.0;

            echo json_encode([
                'success'          => true,
                'weeks'            => $weeks,
                'from'             => $from,
                'to'               => $to,
                'week_results'     => $weekResults,
                'win_counts'       => array_values($winCounts),
                'op_overall'       => $opOverall,
                'overall_team_ibch' => $overallTeamIbcH,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getVeckansTopplista: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid veckans topplista'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE: Operator Absence Impact ────────────────────────────────────
    private function getOperatorAvsaknad(): void {
        try {
            $days = isset($_GET['days']) ? max(30, min(365, (int)$_GET['days'])) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Active operators
            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            // Deduplicated shifts for the period
            $stmt = $this->pdo->prepare("
                SELECT MAX(op1) AS op1, MAX(op2) AS op2, MAX(op3) AS op3,
                       MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum BETWEEN :from AND :to
                  AND drifttid >= 30 AND ibc_ok > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalShifts = count($shifts);

            // For each operator: partition shifts into with/without
            $results = [];
            foreach ($opMap as $num => $name) {
                $wIbc = 0; $wMin = 0; $wCnt = 0;
                $nIbc = 0; $nMin = 0; $nCnt = 0;

                foreach ($shifts as $s) {
                    $ibc = (int)$s['ibc_ok'];
                    $min = (int)$s['drifttid'];
                    $ops = [(int)($s['op1'] ?? 0), (int)($s['op2'] ?? 0), (int)($s['op3'] ?? 0)];

                    if (in_array($num, $ops, true)) {
                        $wIbc += $ibc; $wMin += $min; $wCnt++;
                    } else {
                        $nIbc += $ibc; $nMin += $min; $nCnt++;
                    }
                }

                if ($wCnt < 3) continue; // Need minimum presence to be meaningful

                $withIbcH    = $wMin > 0 ? round($wIbc / ($wMin / 60.0), 2) : 0.0;
                $withoutIbcH = $nMin > 0 ? round($nIbc / ($nMin / 60.0), 2) : 0.0;
                $impact      = round($withIbcH - $withoutIbcH, 2);
                $attendance  = $totalShifts > 0 ? round($wCnt / $totalShifts * 100.0, 1) : 0.0;

                $results[] = [
                    'number'       => $num,
                    'name'         => $name,
                    'with_shifts'  => $wCnt,
                    'with_ibc_h'   => $withIbcH,
                    'without_shifts' => $nCnt,
                    'without_ibc_h'  => $withoutIbcH > 0 ? $withoutIbcH : null,
                    'impact'       => $impact,
                    'attendance'   => $attendance,
                ];
            }

            // Sort highest impact first
            usort($results, fn($a, $b) => $b['impact'] <=> $a['impact']);

            // Period team average (all shifts)
            $totIbc = 0; $totMin = 0;
            foreach ($shifts as $s) { $totIbc += (int)$s['ibc_ok']; $totMin += (int)$s['drifttid']; }
            $teamAvg = $totMin > 0 ? round($totIbc / ($totMin / 60.0), 2) : 0.0;

            echo json_encode([
                'success'       => true,
                'from'          => $from,
                'to'            => $to,
                'days'          => $days,
                'total_shifts'  => $totalShifts,
                'team_avg'      => $teamAvg,
                'operators'     => $results,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getOperatorAvsaknad: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid frånvaroanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getKassationsKarta(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            $pStmt = $pdo->query("SELECT id, name FROM rebotling_products ORDER BY name");
            $prodMap = [];
            foreach ($pStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $prodMap[(int)$r['id']] = $r['name'];
            }

            // Dedup per (op, skiftraknare, product), then aggregate
            $sql = "
                SELECT u.op_num,
                       u.product_id,
                       SUM(u.ibc_ej)    AS total_ej,
                       SUM(u.total_ibc) AS total_ibc,
                       COUNT(*)         AS skift_count
                FROM (
                    SELECT op1                                                         AS op_num,
                           MAX(COALESCE(product_id, 0))                               AS product_id,
                           MAX(COALESCE(ibc_ej_ok, 0))                                AS ibc_ej,
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))         AS total_ibc
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare, op1

                    UNION ALL

                    SELECT op2,
                           MAX(COALESCE(product_id, 0)),
                           MAX(COALESCE(ibc_ej_ok, 0)),
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare, op2

                    UNION ALL

                    SELECT op3,
                           MAX(COALESCE(product_id, 0)),
                           MAX(COALESCE(ibc_ej_ok, 0)),
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare, op3
                ) u
                WHERE u.product_id > 0
                GROUP BY u.op_num, u.product_id
                HAVING total_ibc >= 5
                ORDER BY u.op_num
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $cells    = [];
            $usedOps  = [];
            $usedProds = [];
            $totalEj  = 0;
            $totalIbc = 0;

            foreach ($rows as $r) {
                $opNum  = (int)$r['op_num'];
                $prodId = (int)$r['product_id'];
                if (!isset($opMap[$opNum]) || !isset($prodMap[$prodId])) continue;

                $ej       = (int)$r['total_ej'];
                $total    = (int)$r['total_ibc'];
                $kassation = $total > 0 ? round($ej / $total * 100, 2) : 0.0;

                $cells[] = [
                    'op_num'        => $opNum,
                    'product_id'    => $prodId,
                    'kassation_pct' => $kassation,
                    'skift_count'   => (int)$r['skift_count'],
                    'total_ibc'     => $total,
                    'total_ibc_ej'  => $ej,
                ];

                $usedOps[$opNum]    = $opMap[$opNum];
                $usedProds[$prodId] = $prodMap[$prodId];
                $totalEj  += $ej;
                $totalIbc += $total;
            }

            asort($usedOps);
            asort($usedProds);

            $teamKass = $totalIbc > 0 ? round($totalEj / $totalIbc * 100, 2) : 0.0;

            $operators = array_map(
                fn($num, $name) => ['number' => $num, 'name' => $name],
                array_keys($usedOps), array_values($usedOps)
            );
            $products  = array_map(
                fn($id, $name) => ['id' => $id, 'name' => $name],
                array_keys($usedProds), array_values($usedProds)
            );

            echo json_encode([
                'success'       => true,
                'from'          => $from,
                'to'            => $to,
                'days'          => $days,
                'operators'     => $operators,
                'products'      => $products,
                'cells'         => $cells,
                'team_kassgrad' => $teamKass,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKassationsKarta: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassationskarta'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE: Kassation Trend (weekly rejection rate per operator) ──────────
    private function getKassationTrend(): void {
        try {
            $weeks = isset($_GET['weeks']) ? max(4, min(26, (int)$_GET['weeks'])) : 12;
            $to    = date('Y-m-d');
            $from  = date('Y-m-d', strtotime("-{$weeks} weeks"));

            $opStmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            // Per-op per-week kassation (dedup per skiftraknare)
            $sql = "
                SELECT u.op_num,
                       YEARWEEK(u.datum, 1)  AS yw,
                       SUM(u.ibc_ej)         AS total_ej,
                       SUM(u.total_ibc)      AS total_ibc,
                       COUNT(*)              AS skift_count
                FROM (
                    SELECT op1                                                          AS op_num,
                           datum,
                           MAX(COALESCE(ibc_ej_ok, 0))                                AS ibc_ej,
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))         AS total_ibc
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1 AND op1 IS NOT NULL
                    GROUP BY skiftraknare, op1

                    UNION ALL

                    SELECT op2,
                           datum,
                           MAX(COALESCE(ibc_ej_ok, 0)),
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2 AND op2 IS NOT NULL
                    GROUP BY skiftraknare, op2

                    UNION ALL

                    SELECT op3,
                           datum,
                           MAX(COALESCE(ibc_ej_ok, 0)),
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3 AND op3 IS NOT NULL
                    GROUP BY skiftraknare, op3
                ) u
                GROUP BY u.op_num, YEARWEEK(u.datum, 1)
                HAVING total_ibc >= 5
                ORDER BY u.op_num, yw
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rawRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Team-level per-week (aggregate all ops)
            $teamByYw = [];
            $opData   = [];  // opNum => [yw => [ej, total, shifts]]

            foreach ($rawRows as $r) {
                $yw    = (int)$r['yw'];
                $opNum = (int)$r['op_num'];
                $ej    = (int)$r['total_ej'];
                $total = (int)$r['total_ibc'];
                if (!isset($opMap[$opNum])) continue;

                $opData[$opNum][$yw] = [
                    'total_ej'  => $ej,
                    'total_ibc' => $total,
                    'skifter'   => (int)$r['skift_count'],
                ];

                if (!isset($teamByYw[$yw])) $teamByYw[$yw] = ['ej' => 0, 'total' => 0];
                $teamByYw[$yw]['ej']    += $ej;
                $teamByYw[$yw]['total'] += $total;
            }

            // Build sorted week labels
            $allYws = array_unique(array_merge(
                array_keys($teamByYw),
                ...array_map('array_keys', $opData)
            ));
            sort($allYws);

            $weekLabels = [];
            foreach ($allYws as $yw) {
                $isoYear = (int)substr((string)$yw, 0, 4);
                $isoWeek = (int)substr((string)$yw, 4, 2);
                $weekLabels[] = ['yw' => $yw, 'label' => "v{$isoWeek}"];
            }

            // Team avg per week
            $teamAvgByWeek = [];
            foreach ($allYws as $yw) {
                $t = $teamByYw[$yw] ?? ['ej' => 0, 'total' => 0];
                $teamAvgByWeek[] = [
                    'yw'       => $yw,
                    'kassgrad' => $t['total'] > 0 ? round($t['ej'] / $t['total'] * 100.0, 2) : null,
                ];
            }

            // Per-operator results
            $operators = [];
            foreach ($opData as $opNum => $weekMap) {
                $weekSeries = [];
                $earlyEj = 0; $earlyTotal = 0; $lateEj = 0; $lateTotal = 0;
                $midIdx  = (int)floor(count($allYws) / 2);

                foreach ($allYws as $idx => $yw) {
                    $w = $weekMap[$yw] ?? null;
                    $kassgrad = ($w && $w['total_ibc'] > 0)
                        ? round($w['total_ej'] / $w['total_ibc'] * 100.0, 2)
                        : null;

                    $weekSeries[] = [
                        'yw'        => $yw,
                        'kassgrad'  => $kassgrad,
                        'total_ibc' => $w ? (int)$w['total_ibc'] : 0,
                        'total_ej'  => $w ? (int)$w['total_ej'] : 0,
                        'skifter'   => $w ? (int)$w['skifter'] : 0,
                    ];

                    if ($w) {
                        if ($idx < $midIdx) { $earlyEj += $w['total_ej']; $earlyTotal += $w['total_ibc']; }
                        else                { $lateEj  += $w['total_ej']; $lateTotal  += $w['total_ibc']; }
                    }
                }

                $earlyRate = $earlyTotal > 0 ? round($earlyEj / $earlyTotal * 100.0, 2) : null;
                $lateRate  = $lateTotal  > 0 ? round($lateEj  / $lateTotal  * 100.0, 2) : null;

                $trend = 'stable';
                if ($earlyRate !== null && $lateRate !== null) {
                    $diff = $lateRate - $earlyRate;
                    if ($diff < -1.0)     $trend = 'better';
                    elseif ($diff > 1.0)  $trend = 'worse';
                }

                $totalEj    = array_sum(array_column(array_filter($weekSeries, fn($w) => $w['total_ej'] !== null), 'total_ej'));
                $totalIbc   = array_sum(array_column(array_filter($weekSeries, fn($w) => $w['total_ibc'] !== null), 'total_ibc'));
                $overallKass = $totalIbc > 0 ? round($totalEj / $totalIbc * 100.0, 2) : null;

                $operators[] = [
                    'number'          => $opNum,
                    'name'            => $opMap[$opNum],
                    'weeks'           => $weekSeries,
                    'trend'           => $trend,
                    'current_kassgrad' => $lateRate,
                    'prev_kassgrad'    => $earlyRate,
                    'overall_kassgrad' => $overallKass,
                    'total_ibc'        => $totalIbc,
                    'total_ej'         => $totalEj,
                ];
            }

            usort($operators, fn($a, $b) => ($a['overall_kassgrad'] ?? 999) <=> ($b['overall_kassgrad'] ?? 999));

            echo json_encode([
                'success'        => true,
                'from'           => $from,
                'to'             => $to,
                'weeks'          => $weeks,
                'week_labels'    => $weekLabels,
                'operators'      => $operators,
                'team_avg_by_week' => $teamAvgByWeek,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKassationTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassationstrender'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getKassationsorsakPerOperator(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Graceful fallback if kassationsregistrering table doesn't exist
            $check = $pdo->query("SHOW TABLES LIKE 'kassationsregistrering'");
            if (!$check->fetch()) {
                echo json_encode([
                    'success'      => true,
                    'operators'    => [],
                    'all_causes'   => [],
                    'total_events' => 0,
                    'from'         => $from,
                    'to'           => $to,
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Active operators
            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            // Kassation cause names
            $causeStmt = $pdo->query("SELECT id, namn FROM kassationsorsak_typer WHERE aktiv = 1 ORDER BY namn");
            $causeMap  = [];
            foreach ($causeStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $causeMap[(int)$r['id']] = $r['namn'];
            }

            // Join kassationsregistrering → rebotling_skiftrapport → operators (UNION ALL per position)
            // Each kassation event is attributed to every operator who worked that shift (correlation view).
            // DISTINCT within each branch prevents double-counting from multi-row skiftrapport entries.
            $sql = "
                SELECT ops.op_num, kr.orsak_id, SUM(kr.antal) AS antal
                FROM kassationsregistrering kr
                JOIN (
                    SELECT DISTINCT skiftraknare, op1 AS op_num
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1 AND op1 IS NOT NULL

                    UNION ALL

                    SELECT DISTINCT skiftraknare, op2
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2 AND op2 IS NOT NULL

                    UNION ALL

                    SELECT DISTINCT skiftraknare, op3
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3 AND op3 IS NOT NULL
                ) ops ON ops.skiftraknare = kr.skiftraknare
                WHERE kr.datum BETWEEN :from4 AND :to4
                GROUP BY ops.op_num, kr.orsak_id
                ORDER BY ops.op_num, antal DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
                ':from4' => $from, ':to4' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build per-operator aggregation
            $opData      = [];
            $totalEvents = 0;

            foreach ($rows as $r) {
                $num = (int)$r['op_num'];
                if (!isset($opMap[$num])) continue;
                if (!isset($opData[$num])) {
                    $opData[$num] = [
                        'number'          => $num,
                        'name'            => $opMap[$num],
                        'total_kassation' => 0,
                        'causes'          => [],
                    ];
                }
                $antal    = (int)$r['antal'];
                $orsakId  = (int)$r['orsak_id'];
                $opData[$num]['total_kassation'] += $antal;
                $opData[$num]['causes'][]         = [
                    'orsak_id' => $orsakId,
                    'namn'     => $causeMap[$orsakId] ?? 'Okänd',
                    'antal'    => $antal,
                    'pct'      => 0.0,
                ];
            }

            // Add per-cause percentage and top_cause
            foreach ($opData as &$op) {
                $tot = $op['total_kassation'];
                foreach ($op['causes'] as &$c) {
                    $c['pct'] = $tot > 0 ? round($c['antal'] / $tot * 100, 1) : 0.0;
                }
                unset($c);
                $op['top_cause'] = count($op['causes']) > 0 ? $op['causes'][0]['namn'] : '';
                $totalEvents += $tot;
            }
            unset($op);

            // Sort operators: highest total kassation first
            usort($opData, fn($a, $b) => $b['total_kassation'] - $a['total_kassation']);

            // All causes list for legend
            $allCauses = [];
            foreach ($causeMap as $id => $namn) {
                $allCauses[] = ['id' => $id, 'namn' => $namn];
            }

            echo json_encode([
                'success'      => true,
                'operators'    => array_values($opData),
                'all_causes'   => $allCauses,
                'total_events' => $totalEvents,
                'from'         => $from,
                'to'           => $to,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKassationsorsakPerOperator: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassationsorsak-analys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getCoachView(): void {
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

            // Deduplicated shifts last 90 days, ordered ASC for streak calculation
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(op1)       AS op1,
                    MAX(op2)       AS op2,
                    MAX(op3)       AS op3,
                    MAX(ibc_ok)    AS ibc_ok,
                    MAX(ibc_ej_ok) AS ibc_ej_ok,
                    MAX(drifttid)  AS drifttid,
                    MAX(datum)     AS datum
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to AND drifttid > 0
                GROUP BY skiftraknare
                ORDER BY MAX(datum) ASC
            ");
            $stmt->execute([':from' => $base90, ':to' => $today]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Team IBC/h over 90d (SUM/SUM on deduplicated shifts)
            $teamIbc = 0;
            $teamMin = 0;
            foreach ($allShifts as $s) {
                $teamIbc += max(0, (int)$s['ibc_ok']);
                $teamMin += max(0, (int)$s['drifttid']);
            }
            $teamIbcH = $teamMin > 0 ? $teamIbc / ($teamMin / 60.0) : 0;

            // Accumulate per-operator: recent (0–14d) vs baseline (15–90d)
            $opData = [];
            foreach ($allShifts as $s) {
                $ibc   = max(0, (int)$s['ibc_ok']);
                $ibcEj = max(0, (int)$s['ibc_ej_ok']);
                $min   = max(0, (int)$s['drifttid']);
                if ($min <= 0) continue;

                $ibcH     = $ibc / ($min / 60.0);
                $isRecent = ($s['datum'] >= $recent14);

                foreach (['op1', 'op2', 'op3'] as $pos) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0 || !isset($operatorNames[$opNum])) continue;

                    if (!isset($opData[$opNum])) {
                        $opData[$opNum] = ['recent' => [], 'baseline' => [], 'shifts' => []];
                    }
                    $entry = ['ibc' => $ibc, 'min' => $min, 'ej_ok' => $ibcEj, 'ibc_h' => round($ibcH, 2)];
                    $opData[$opNum]['shifts'][] = $entry;
                    if ($isRecent) {
                        $opData[$opNum]['recent'][] = $entry;
                    } else {
                        $opData[$opNum]['baseline'][] = $entry;
                    }
                }
            }

            $results = [];
            foreach ($opData as $opNum => $data) {
                $baselineN = count($data['baseline']);
                $recentN   = count($data['recent']);
                if ($baselineN < 3 || $recentN < 1) continue;

                // Performance delta (SUM/SUM)
                $baseIbc  = array_sum(array_column($data['baseline'], 'ibc'));
                $baseMin  = array_sum(array_column($data['baseline'], 'min'));
                $baseIbcH = $baseMin > 0 ? $baseIbc / ($baseMin / 60.0) : 0;
                if ($baseIbcH <= 0) continue;

                $recIbc  = array_sum(array_column($data['recent'], 'ibc'));
                $recMin  = array_sum(array_column($data['recent'], 'min'));
                $recIbcH = $recMin > 0 ? $recIbc / ($recMin / 60.0) : 0;

                $deltaPct = ($recIbcH - $baseIbcH) / $baseIbcH * 100;

                // Overall 90d IBC/h and vs-team
                $allIbc  = array_sum(array_column($data['shifts'], 'ibc'));
                $allMin  = array_sum(array_column($data['shifts'], 'min'));
                $ibcH90  = $allMin > 0 ? $allIbc / ($allMin / 60.0) : 0;
                $vsTeam  = $teamIbcH > 0 ? ($ibcH90 - $teamIbcH) / $teamIbcH * 100 : 0;

                // Kassation trend (pp change)
                $baseKassIbc  = array_sum(array_column($data['baseline'], 'ibc'));
                $baseKassEj   = array_sum(array_column($data['baseline'], 'ej_ok'));
                $baseKassTotal = $baseKassIbc + $baseKassEj;
                $baseKassPct  = $baseKassTotal > 0 ? $baseKassEj / $baseKassTotal * 100 : 0;

                $recKassIbc  = array_sum(array_column($data['recent'], 'ibc'));
                $recKassEj   = array_sum(array_column($data['recent'], 'ej_ok'));
                $recKassTotal = $recKassIbc + $recKassEj;
                $recKassPct  = $recKassTotal > 0 ? $recKassEj / $recKassTotal * 100 : 0;
                $kassDelta   = $recKassPct - $baseKassPct;

                // Streak (consecutive shifts over/under own 90d avg, ASC order)
                $shiftIbcH = array_column($data['shifts'], 'ibc_h');
                $n = count($shiftIbcH);
                $avg90 = $n > 0 ? array_sum($shiftIbcH) / $n : 0;
                $streak = 0;
                $streakDir = 'ingen';
                if ($n > 0 && $avg90 > 0) {
                    $lastIsOver = ($shiftIbcH[$n - 1] >= $avg90);
                    $streakDir  = $lastIsOver ? 'over' : 'under';
                    for ($i = $n - 1; $i >= 0; $i--) {
                        if (($shiftIbcH[$i] >= $avg90) === $lastIsOver) { $streak++; } else { break; }
                    }
                }

                // Priority score (positive = needs intervention, negative = deserves praise)
                $scorePerf   = 0;
                $scoreStreak = 0;
                $scoreKass   = 0;

                if     ($deltaPct <= -20) $scorePerf = 45;
                elseif ($deltaPct <= -10) $scorePerf = 28;
                elseif ($deltaPct <=  -5) $scorePerf = 14;
                elseif ($deltaPct >=  15) $scorePerf = -25;
                elseif ($deltaPct >=   8) $scorePerf = -14;

                if     ($streakDir === 'under' && $streak >= 4) $scoreStreak = 25;
                elseif ($streakDir === 'under' && $streak >= 2) $scoreStreak = 12;
                elseif ($streakDir === 'over'  && $streak >= 4) $scoreStreak = -20;
                elseif ($streakDir === 'over'  && $streak >= 2) $scoreStreak = -10;

                if     ($kassDelta >=  5) $scoreKass =  25;
                elseif ($kassDelta >=  2) $scoreKass =  12;
                elseif ($kassDelta <= -3) $scoreKass = -10;

                $priorityScore = $scorePerf + $scoreStreak + $scoreKass;

                // Action type and Swedish recommendation
                $reasons = [];
                if ($priorityScore >= 45) {
                    $actionType = 'behöver_stöd';
                    if ($deltaPct <= -10) $reasons[] = 'prestandan fallit ' . abs(round($deltaPct)) . '% mot baseline';
                    if ($kassDelta >= 2)  $reasons[] = 'kassation steg ' . round($kassDelta, 1) . ' pp';
                    if ($streakDir === 'under' && $streak >= 3) $reasons[] = $streak . ' skift i rad under snitt';
                    $rek = 'Prioriterat samtal rekommenderas: ' . implode(', ', $reasons ?: ['kontrollera operatörens status']) . '.';
                } elseif ($priorityScore >= 15) {
                    $actionType = 'bevaka';
                    if ($deltaPct < -5)  $reasons[] = 'viss prestationsnedgång (' . round($deltaPct, 1) . '%)';
                    if ($kassDelta >= 2) $reasons[] = 'kassation stiger';
                    if ($streakDir === 'under' && $streak >= 2) $reasons[] = $streak . ' skift under snitt';
                    $rek = 'Bevaka nästa 2–3 veckor: ' . implode(', ', $reasons ?: ['inga alarmerande signaler']) . '.';
                } elseif ($priorityScore <= -20) {
                    $actionType = 'erkänn_framgång';
                    if ($deltaPct >= 10)  $reasons[] = 'prestandan stigit ' . round($deltaPct) . '% mot baseline';
                    if ($streakDir === 'over' && $streak >= 3) $reasons[] = $streak . ' skift i rad över snitt';
                    if ($kassDelta <= -2) $reasons[] = 'kassationen sjunkit ' . abs(round($kassDelta, 1)) . ' pp';
                    $rek = 'Bra tillfälle att ge positiv feedback: ' . implode(', ', $reasons ?: ['generellt god form']) . '.';
                } else {
                    $actionType = 'stabil';
                    $rek = 'Prestandan är stabil. Ingen åtgärd krävs för tillfället.';
                }

                $results[] = [
                    'op_number'          => $opNum,
                    'name'               => $operatorNames[$opNum],
                    'action_type'        => $actionType,
                    'rekommendation'     => $rek,
                    'ibc_h_90d'          => round($ibcH90, 2),
                    'ibc_h_recent'       => round($recIbcH, 2),
                    'ibc_h_baseline'     => round($baseIbcH, 2),
                    'delta_pct'          => round($deltaPct, 1),
                    'vs_team_pct'        => round($vsTeam, 1),
                    'kass_pct_recent'    => round($recKassPct, 1),
                    'kass_pct_baseline'  => round($baseKassPct, 1),
                    'kass_delta_pp'      => round($kassDelta, 1),
                    'streak'             => $streak,
                    'streak_dir'         => $streakDir,
                    'recent_skift'       => $recentN,
                    'baseline_skift'     => $baselineN,
                    'priority_score'     => $priorityScore,
                ];
            }

            // Sort: behöver_stöd → bevaka → stabil → erkänn_framgång
            $order = ['behöver_stöd' => 0, 'bevaka' => 1, 'stabil' => 2, 'erkänn_framgång' => 3];
            usort($results, function ($a, $b) use ($order) {
                $ao = $order[$a['action_type']] ?? 99;
                $bo = $order[$b['action_type']] ?? 99;
                if ($ao !== $bo) return $ao - $bo;
                return $b['priority_score'] <=> $a['priority_score'];
            });

            $counts = ['behöver_stöd' => 0, 'bevaka' => 0, 'stabil' => 0, 'erkänn_framgång' => 0];
            foreach ($results as $r) {
                if (isset($counts[$r['action_type']])) $counts[$r['action_type']]++;
            }

            echo json_encode([
                'success'    => true,
                'operators'  => $results,
                'counts'     => $counts,
                'team_ibc_h' => round($teamIbcH, 2),
                'from'       => $base90,
                'to'         => $today,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getCoachView: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid tränarvy'], \JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=produktions-tidsserie&days=365
     * Facility-wide production time series — daily IBC/h, monthly aggregation, KPIs.
     * SUM/SUM on shifts deduplicated by skiftraknare.
     */
    private function getProduktionsTidsserie(): void {
        try {
            $days = max(30, min(730, (int)($_GET['days'] ?? 365)));
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Daily totals — inner GROUP BY skiftraknare deduplicates same-shift data
            $sqlDaily = "
                SELECT
                    datum,
                    SUM(ibc_ok)            AS total_ibc,
                    SUM(hours)             AS total_hours,
                    ROUND(SUM(ibc_ok) / NULLIF(SUM(hours), 0), 2) AS ibc_per_h,
                    COUNT(*)               AS antal_skift
                FROM (
                    SELECT datum,
                           skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))       AS ibc_ok,
                           MAX(COALESCE(drifttid, 0))/60.0 AS hours
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) dedup
                GROUP BY datum
                ORDER BY datum
            ";
            $stmtD = $this->pdo->prepare($sqlDaily);
            $stmtD->execute([':from1' => $from, ':to1' => $to]);
            $daily = $stmtD->fetchAll(\PDO::FETCH_ASSOC);

            // Monthly aggregation
            $sqlMonthly = "
                SELECT
                    DATE_FORMAT(datum, '%Y-%m')   AS yearmonth,
                    SUM(ibc_ok)                    AS total_ibc,
                    SUM(hours)                     AS total_hours,
                    ROUND(SUM(ibc_ok) / NULLIF(SUM(hours), 0), 2) AS ibc_per_h,
                    COUNT(*)                       AS antal_skift
                FROM (
                    SELECT datum,
                           skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))       AS ibc_ok,
                           MAX(COALESCE(drifttid, 0))/60.0 AS hours
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) dedup
                GROUP BY yearmonth
                ORDER BY yearmonth
            ";
            $stmtM = $this->pdo->prepare($sqlMonthly);
            $stmtM->execute([':from2' => $from, ':to2' => $to]);
            $monthly = $stmtM->fetchAll(\PDO::FETCH_ASSOC);

            // Compute 7-day moving average on PHP side
            $dailyFinal = [];
            $window = [];
            foreach ($daily as $row) {
                $val = (float)$row['ibc_per_h'];
                $window[] = $val;
                if (count($window) > 7) array_shift($window);
                $ma7 = count($window) > 0 ? round(array_sum($window) / count($window), 2) : null;
                $dailyFinal[] = [
                    'datum'      => $row['datum'],
                    'ibc_per_h'  => $val > 0 ? $val : null,
                    'total_ibc'  => (int)$row['total_ibc'],
                    'antal_skift'=> (int)$row['antal_skift'],
                    'ma7'        => $ma7,
                ];
            }

            // KPIs
            $totalIbc   = array_sum(array_column($daily, 'total_ibc'));
            $totalHours = array_sum(array_column($daily, 'total_hours'));
            $avgIbcH    = $totalHours > 0 ? round($totalIbc / $totalHours, 2) : 0;
            $prodDays   = count($daily);

            $bestDay    = null;
            $bestIbcH   = 0;
            foreach ($dailyFinal as $d) {
                if ($d['ibc_per_h'] !== null && $d['ibc_per_h'] > $bestIbcH) {
                    $bestIbcH = $d['ibc_per_h'];
                    $bestDay  = $d['datum'];
                }
            }

            // Trend: compare first half vs second half avg IBC/h
            $trendArrow = 'flat';
            if (count($dailyFinal) >= 10) {
                $half = (int)(count($dailyFinal) / 2);
                $firstHalf  = array_slice($dailyFinal, 0, $half);
                $secondHalf = array_slice($dailyFinal, -$half);
                $avgFirst   = array_sum(array_map(fn($d) => $d['ibc_per_h'] ?? 0, $firstHalf))
                              / max(1, count(array_filter($firstHalf, fn($d) => $d['ibc_per_h'] !== null)));
                $avgSecond  = array_sum(array_map(fn($d) => $d['ibc_per_h'] ?? 0, $secondHalf))
                              / max(1, count(array_filter($secondHalf, fn($d) => $d['ibc_per_h'] !== null)));
                $deltaPct   = $avgFirst > 0 ? round(($avgSecond - $avgFirst) / $avgFirst * 100, 1) : 0;
                if ($deltaPct >= 3)       $trendArrow = 'up';
                elseif ($deltaPct <= -3)  $trendArrow = 'down';
                else                      $trendArrow = 'flat';
            } else {
                $deltaPct = 0;
            }

            // Days above/below period average
            $daysAbove = count(array_filter($dailyFinal, fn($d) => $d['ibc_per_h'] !== null && $d['ibc_per_h'] > $avgIbcH));

            echo json_encode([
                'success'     => true,
                'from'        => $from,
                'to'          => $to,
                'days'        => $days,
                'daily'       => $dailyFinal,
                'monthly'     => array_values($monthly),
                'kpis'        => [
                    'total_ibc'   => (int)$totalIbc,
                    'avg_ibc_h'   => $avgIbcH,
                    'prod_days'   => $prodDays,
                    'best_ibc_h'  => round($bestIbcH, 2),
                    'best_day'    => $bestDay,
                    'trend_arrow' => $trendArrow,
                    'trend_pct'   => $deltaPct,
                    'days_above'  => $daysAbove,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktionsTidsserie: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktionspuls'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getSkiftInsikt(): void {
        try {
            $skiftraknare = isset($_GET['skiftraknare']) ? (int)$_GET['skiftraknare'] : 0;
            $datum        = isset($_GET['datum']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['datum'])
                            ? $_GET['datum'] : null;

            // List all shifts on a given date
            if ($datum && !$skiftraknare) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        s.skiftraknare,
                        MAX(s.datum)                     AS datum,
                        MAX(COALESCE(s.ibc_ok, 0))       AS ibc_ok,
                        MAX(COALESCE(s.drifttid, 0))     AS drifttid,
                        MAX(s.op1)                       AS op1,
                        MAX(s.op2)                       AS op2,
                        MAX(s.op3)                       AS op3,
                        MAX(o1.name)                     AS op1_name,
                        MAX(o2.name)                     AS op2_name,
                        MAX(o3.name)                     AS op3_name
                    FROM rebotling_skiftrapport s
                    LEFT JOIN operators o1 ON o1.number = s.op1
                    LEFT JOIN operators o2 ON o2.number = s.op2
                    LEFT JOIN operators o3 ON o3.number = s.op3
                    WHERE s.datum = :datum AND s.drifttid >= 30
                    GROUP BY s.skiftraknare
                    ORDER BY s.skiftraknare
                ");
                $stmt->execute([':datum' => $datum]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $shifts = [];
                foreach ($rows as $r) {
                    $dt = (int)$r['drifttid'];
                    $shifts[] = [
                        'skiftraknare' => (int)$r['skiftraknare'],
                        'datum'        => $r['datum'],
                        'ibc_ok'       => (int)$r['ibc_ok'],
                        'drifttid_min' => $dt,
                        'ibc_per_h'    => $dt > 0 ? round($r['ibc_ok'] / ($dt / 60.0), 2) : 0,
                        'op1'          => $r['op1'] ? (int)$r['op1'] : null,
                        'op2'          => $r['op2'] ? (int)$r['op2'] : null,
                        'op3'          => $r['op3'] ? (int)$r['op3'] : null,
                        'op1_name'     => $r['op1_name'],
                        'op2_name'     => $r['op2_name'],
                        'op3_name'     => $r['op3_name'],
                    ];
                }
                echo json_encode(['success' => true, 'mode' => 'list', 'datum' => $datum, 'shifts' => $shifts], \JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!$skiftraknare) {
                echo json_encode(['success' => false, 'error' => 'Ange skiftraknare eller datum'], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Fetch the specific shift (dedup via GROUP BY skiftraknare)
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(datum)                         AS datum,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))           AS ibc_ok,
                    MAX(COALESCE(ibc_ej_ok, 0))        AS ibc_ej_ok,
                    MAX(COALESCE(bur_ej_ok, 0))        AS bur_ej_ok,
                    MAX(COALESCE(drifttid, 0))         AS drifttid,
                    MAX(COALESCE(driftstopptime, 0))   AS driftstopptime,
                    MAX(product_id)                    AS product_id,
                    MAX(op1)                           AS op1,
                    MAX(op2)                           AS op2,
                    MAX(op3)                           AS op3
                FROM rebotling_skiftrapport
                WHERE skiftraknare = :sk
                GROUP BY skiftraknare
            ");
            $stmt->execute([':sk' => $skiftraknare]);
            $shift = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$shift) {
                echo json_encode(['success' => false, 'error' => 'Skift #' . $skiftraknare . ' hittades inte'], \JSON_UNESCAPED_UNICODE);
                return;
            }

            $datum       = $shift['datum'];
            $drifttimmar = $shift['drifttid'] / 60.0;
            $ibcH        = $drifttimmar > 0 ? round($shift['ibc_ok'] / $drifttimmar, 2) : 0;
            $totIbc      = (int)$shift['ibc_ok'] + (int)$shift['ibc_ej_ok'];
            $kassgrad    = $totIbc > 0 ? round($shift['ibc_ej_ok'] / $totIbc * 100, 1) : 0.0;
            $stoppgrad   = $shift['drifttid'] > 0
                           ? round($shift['driftstopptime'] / $shift['drifttid'] * 100, 1)
                           : 0.0;

            // Operator names
            $opNums  = array_values(array_filter([(int)$shift['op1'], (int)$shift['op2'], (int)$shift['op3']]));
            $opNames = [];
            if (!empty($opNums)) {
                $ph = implode(',', array_fill(0, count($opNums), '?'));
                $stmtO = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ($ph)");
                $stmtO->execute($opNums);
                foreach ($stmtO->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                    $opNames[$op['number']] = $op['name'];
                }
            }

            // Product name
            $productName = null;
            if ($shift['product_id']) {
                try {
                    $stmtP = $this->pdo->prepare("SELECT name FROM rebotling_products WHERE id = :id");
                    $stmtP->execute([':id' => $shift['product_id']]);
                    $prod = $stmtP->fetch(\PDO::FETCH_ASSOC);
                    if ($prod) $productName = $prod['name'];
                } catch (\Throwable $e) {}
            }

            // 90-day context window ending on shift date
            $fromCtx = date('Y-m-d', strtotime($datum . ' -90 days'));
            $toCtx   = $datum;

            // Team average over context window
            $stmtTeam = $this->pdo->prepare("
                SELECT
                    ROUND(SUM(ibc_ok) / NULLIF(SUM(drifttid) / 60.0, 0), 2)        AS team_avg_ibc_h,
                    ROUND(SUM(ibc_ej_ok) / NULLIF(SUM(ibc_ok) + SUM(ibc_ej_ok), 0) * 100, 1) AS team_avg_kass
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0))    AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_ok,
                           MAX(COALESCE(drifttid, 0))  AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :fromCtx AND :toCtx
                      AND drifttid >= 30
                    GROUP BY skiftraknare
                ) dedup
            ");
            $stmtTeam->execute([':fromCtx' => $fromCtx, ':toCtx' => $toCtx]);
            $teamRow = $stmtTeam->fetch(\PDO::FETCH_ASSOC);
            $teamAvgIbcH = $teamRow['team_avg_ibc_h'] ? (float)$teamRow['team_avg_ibc_h'] : null;
            $teamAvgKass = $teamRow['team_avg_kass']  ? (float)$teamRow['team_avg_kass']  : null;
            $vsTeam      = ($teamAvgIbcH && $teamAvgIbcH > 0)
                           ? round(($ibcH - $teamAvgIbcH) / $teamAvgIbcH * 100, 1)
                           : null;

            // Per-operator personal average at their position (90d context)
            $positions  = [
                ['field' => 'op1', 'label' => 'Tvättplats'],
                ['field' => 'op2', 'label' => 'Kontrollstation'],
                ['field' => 'op3', 'label' => 'Truckförare'],
            ];
            $opDetails = [];
            foreach ($positions as $pos) {
                $opNum = (int)$shift[$pos['field']];
                if (!$opNum) continue;

                $stmtPers = $this->pdo->prepare("
                    SELECT
                        ROUND(SUM(ibc_ok) / NULLIF(SUM(drifttid) / 60.0, 0), 2) AS personal_avg,
                        COUNT(*) AS antal_skift
                    FROM (
                        SELECT skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))   AS ibc_ok,
                               MAX(COALESCE(drifttid, 0)) AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE {$pos['field']} = :opNum
                          AND datum BETWEEN :fromCtx AND :toCtx
                          AND drifttid >= 30
                        GROUP BY skiftraknare
                    ) d
                ");
                $stmtPers->execute([':opNum' => $opNum, ':fromCtx' => $fromCtx, ':toCtx' => $toCtx]);
                $pers = $stmtPers->fetch(\PDO::FETCH_ASSOC);

                $personalAvg = $pers['personal_avg'] ? (float)$pers['personal_avg'] : null;
                $vsPers      = ($personalAvg && $personalAvg > 0)
                               ? round(($ibcH - $personalAvg) / $personalAvg * 100, 1)
                               : null;

                $opDetails[] = [
                    'position'           => $pos['field'],
                    'label'              => $pos['label'],
                    'op_number'          => $opNum,
                    'op_name'            => $opNames[$opNum] ?? 'Okänd',
                    'personal_avg_ibc_h' => $personalAvg,
                    'vs_personal'        => $vsPers,
                    'antal_skift_ctx'    => (int)($pers['antal_skift'] ?? 0),
                ];
            }

            echo json_encode([
                'success'             => true,
                'mode'                => 'detail',
                'skiftraknare'        => $skiftraknare,
                'datum'               => $datum,
                'ibc_ok'              => (int)$shift['ibc_ok'],
                'ibc_ej_ok'           => (int)$shift['ibc_ej_ok'],
                'bur_ej_ok'           => (int)$shift['bur_ej_ok'],
                'drifttid_min'        => (int)$shift['drifttid'],
                'driftstopptime_min'  => (int)$shift['driftstopptime'],
                'ibc_per_h'           => $ibcH,
                'kassationsgrad'      => $kassgrad,
                'stoppgrad'           => $stoppgrad,
                'product_id'          => $shift['product_id'] ? (int)$shift['product_id'] : null,
                'product_name'        => $productName,
                'operators'           => $opDetails,
                'context'             => [
                    'from'            => $fromCtx,
                    'to'              => $toCtx,
                    'team_avg_ibc_h'  => $teamAvgIbcH,
                    'team_avg_kass'   => $teamAvgKass,
                    'vs_team_pct'     => $vsTeam,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSkiftInsikt: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skift-insikt'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=rast-analys&days=90
    // Returns break-time statistics per shift: scatter data, distribution, weekly trend
    // =========================================================
    private function getRastAnalys(): void {
        try {
            $days = max(30, min(365, (int)($_GET['days'] ?? 90)));
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            // One row per unique shift — MAX dedup for all columns
            $stmt = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    MIN(datum)                          AS datum,
                    MAX(COALESCE(ibc_ok, 0))            AS ibc_ok,
                    MAX(COALESCE(drifttid, 0))          AS drifttid,
                    MAX(COALESCE(rasttime, 0))          AS rasttime,
                    MAX(COALESCE(driftstopptime, 0))    AS driftstopptime
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to
                  AND drifttid >= 30
                  AND ibc_ok > 0
                GROUP BY skiftraknare
                ORDER BY datum
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($shifts)) {
                echo json_encode([
                    'success' => true, 'days' => $days,
                    'team' => null, 'distribution' => [], 'scatter' => [], 'trend' => []
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            $scatter      = [];
            $rawPoints    = []; // raw ibc/drift/rast for SUM/SUM comparison
            $totalRast    = 0;
            $totalDrift   = 0;
            $totalIbc     = 0;
            $withRast     = 0;
            $buckets      = ['0-15' => 0, '15-30' => 0, '30-45' => 0, '45-60' => 0, '60+' => 0];
            $weeklyTrend  = []; // isoWeekKey => [label, rast_sum, count]

            foreach ($shifts as $s) {
                $rast    = (int)$s['rasttime'];
                $drift   = (int)$s['drifttid'];
                $ibc     = (int)$s['ibc_ok'];
                $stopp   = (int)$s['driftstopptime'];
                $ibcH    = $drift > 0 ? round($ibc / ($drift / 60.0), 2) : 0.0;
                $rastPct = $drift > 0 ? round($rast / $drift * 100.0, 1) : 0.0;

                $scatter[] = [
                    'skiftraknare' => (int)$s['skiftraknare'],
                    'datum'        => $s['datum'],
                    'rasttime'     => $rast,
                    'rastpct'      => $rastPct,
                    'ibc_per_h'    => $ibcH,
                    'drifttid'     => $drift,
                    'driftstopp'   => $stopp,
                ];
                $rawPoints[] = ['rast' => $rast, 'ibc' => $ibc, 'drift' => $drift];

                $totalRast  += $rast;
                $totalDrift += $drift;
                $totalIbc   += $ibc;
                if ($rast > 0) $withRast++;

                // Distribution buckets
                if      ($rast <= 15) $buckets['0-15']++;
                elseif  ($rast <= 30) $buckets['15-30']++;
                elseif  ($rast <= 45) $buckets['30-45']++;
                elseif  ($rast <= 60) $buckets['45-60']++;
                else                  $buckets['60+']++;

                // ISO-week key for trend
                $ts   = strtotime($s['datum']);
                $key  = date('oW', $ts);
                if (!isset($weeklyTrend[$key])) {
                    $weeklyTrend[$key] = [
                        'label'    => 'V' . ltrim(date('W', $ts), '0'),
                        'rast_sum' => 0,
                        'count'    => 0,
                    ];
                }
                $weeklyTrend[$key]['rast_sum'] += $rast;
                $weeklyTrend[$key]['count']++;
            }

            $n       = count($scatter);
            $avgRast = $n > 0 ? round($totalRast / $n, 1) : 0.0;
            $avgDrift= $n > 0 ? round($totalDrift / $n, 1) : 0.0;
            $teamIbcH= $totalDrift > 0 ? round($totalIbc / ($totalDrift / 60.0), 2) : 0.0;
            $avgRastPct = $avgDrift > 0 ? round($avgRast / $avgDrift * 100.0, 1) : 0.0;

            // IBC/h comparison: short-break vs long-break shifts — SUM/SUM (not AVG-of-ratios)
            $shortIbc = 0; $shortMin = 0; $longIbc = 0; $longMin = 0;
            foreach ($rawPoints as $r) {
                if ($r['rast'] < $avgRast) { $shortIbc += $r['ibc']; $shortMin += $r['drift']; }
                else                        { $longIbc  += $r['ibc']; $longMin  += $r['drift']; }
            }
            $ibcHShort = $shortMin > 0 ? round($shortIbc / ($shortMin / 60.0), 2) : null;
            $ibcHLong  = $longMin  > 0 ? round($longIbc  / ($longMin  / 60.0), 2) : null;

            ksort($weeklyTrend);
            $trend = [];
            foreach ($weeklyTrend as $data) {
                $trend[] = [
                    'label'    => $data['label'],
                    'avg_rast' => round($data['rast_sum'] / $data['count'], 1),
                    'count'    => $data['count'],
                ];
            }

            echo json_encode([
                'success'      => true,
                'days'         => $days,
                'team' => [
                    'total_skift'      => $n,
                    'skift_med_rast'   => $withRast,
                    'avg_rast_min'     => $avgRast,
                    'avg_drifttid_min' => $avgDrift,
                    'avg_rast_pct'     => $avgRastPct,
                    'avg_ibc_h'        => $teamIbcH,
                    'ibc_h_kort_rast'  => $ibcHShort,
                    'ibc_h_lang_rast'  => $ibcHLong,
                ],
                'distribution' => $buckets,
                'scatter'      => $scatter,
                'trend'        => $trend,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getRastAnalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid rastanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET ?action=rebotling&run=sasongsanalys
     * Säsongsmönster: IBC/h per månad (1–12) samt år-för-år-jämförelse.
     * Deduplicerar per skiftraknare med MAX(ibc_ok)/MAX(drifttid).
     * SUM/SUM-aggregering — ej average-of-ratios.
     */
    private function getSasongsanalys(): void {
        try {
            // Per year+month
            $sqlYearMonth = "
                SELECT
                    YEAR(datum)  AS ar,
                    MONTH(datum) AS man,
                    SUM(ibc_ok) / NULLIF(SUM(drifttid) / 60.0, 0) AS ibc_per_h,
                    COUNT(*)                                        AS skift_count,
                    SUM(ibc_ok)                                     AS total_ibc
                FROM (
                    SELECT datum, skiftraknare,
                           MAX(ibc_ok)               AS ibc_ok,
                           MAX(COALESCE(drifttid,0)) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE drifttid > 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) AS deduped
                GROUP BY YEAR(datum), MONTH(datum)
                ORDER BY ar, man
            ";
            $yearMonthRows = $this->pdo->query($sqlYearMonth)->fetchAll(\PDO::FETCH_ASSOC);

            // Per month-of-year only (seasonal average across all years, correct SUM/SUM)
            $sqlMonthly = "
                SELECT
                    MONTH(datum) AS man,
                    SUM(ibc_ok) / NULLIF(SUM(drifttid) / 60.0, 0) AS ibc_per_h,
                    COUNT(*)                                        AS skift_count,
                    SUM(ibc_ok)                                     AS total_ibc
                FROM (
                    SELECT datum, skiftraknare,
                           MAX(ibc_ok)               AS ibc_ok,
                           MAX(COALESCE(drifttid,0)) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE drifttid > 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) AS deduped
                GROUP BY MONTH(datum)
                ORDER BY man
            ";
            $monthlyRows = $this->pdo->query($sqlMonthly)->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($monthlyRows)) {
                echo json_encode([
                    'success'     => true,
                    'monthly_avg' => [],
                    'by_year'     => [],
                    'years'       => [],
                    'period_avg'  => 0,
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Build monthly_avg: indexed by month 1..12
            $monthlyAvg = [];
            foreach ($monthlyRows as $r) {
                $monthlyAvg[(int)$r['man']] = [
                    'ibc_per_h'   => round((float)$r['ibc_per_h'], 2),
                    'skift_count' => (int)$r['skift_count'],
                    'total_ibc'   => (int)$r['total_ibc'],
                ];
            }

            // Build by_year: [year => [month => ibc_per_h]]
            $byYear = [];
            $yearSet = [];
            foreach ($yearMonthRows as $r) {
                $ar  = (int)$r['ar'];
                $man = (int)$r['man'];
                $yearSet[$ar] = true;
                $byYear[$ar][$man] = [
                    'ibc_per_h'   => round((float)$r['ibc_per_h'], 2),
                    'skift_count' => (int)$r['skift_count'],
                    'total_ibc'   => (int)$r['total_ibc'],
                ];
            }
            $years = array_keys($yearSet);
            sort($years);

            // Overall period average (SUM/SUM across all data)
            $sqlAvg = "
                SELECT SUM(ibc_ok) / NULLIF(SUM(drifttid) / 60.0, 0) AS period_avg
                FROM (
                    SELECT MAX(ibc_ok) AS ibc_ok, MAX(COALESCE(drifttid,0)) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE drifttid > 30 AND ibc_ok > 0
                    GROUP BY skiftraknare
                ) AS d
            ";
            $periodAvg = (float)($this->pdo->query($sqlAvg)->fetchColumn() ?? 0);

            echo json_encode([
                'success'     => true,
                'monthly_avg' => $monthlyAvg,
                'by_year'     => $byYear,
                'years'       => $years,
                'period_avg'  => round($periodAvg, 2),
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSasongsanalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid säsongsanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // =========================================================
    // Produktionsrytm — 3×7 heatmap (skifttyp × veckodag)
    // GET ?action=rebotling&run=produktionsrytm&days=90|180|365
    // =========================================================
    private function getProduktionsrytm(): void {
        try {
            $days = isset($_GET['days']) ? max(30, min(730, (int)$_GET['days'])) : 90;
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            $sql = "
                SELECT dow, skift_typ,
                       SUM(max_ibc)    AS tot_ibc,
                       SUM(max_min)    AS tot_min,
                       COUNT(*)        AS antal_skift
                FROM (
                    SELECT
                        DAYOFWEEK(datum)                                AS dow,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))                       AS max_ibc,
                        MAX(COALESCE(drifttid, 0))                     AS max_min,
                        CASE
                            WHEN HOUR(MIN(created_at)) >=  6 AND HOUR(MIN(created_at)) < 14 THEN 'dag'
                            WHEN HOUR(MIN(created_at)) >= 14 AND HOUR(MIN(created_at)) < 22 THEN 'kvall'
                            ELSE 'natt'
                        END AS skift_typ
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND ibc_ok > 0
                      AND drifttid >= 30
                    GROUP BY DAYOFWEEK(datum), skiftraknare
                ) deduped
                GROUP BY dow, skift_typ
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Compute IBC/h for each cell and build indexed grid
            $cells = [];
            $totalIbc = 0;
            $totalMin = 0;
            foreach ($rows as $r) {
                $dow  = (int)$r['dow'];  // 1=Sun,2=Mon,...,7=Sat
                $typ  = $r['skift_typ'];
                $ibch = $r['tot_min'] > 0
                    ? round($r['tot_ibc'] * 60.0 / $r['tot_min'], 2)
                    : 0;
                $cells[$typ][$dow] = [
                    'ibc_per_h'   => $ibch,
                    'tot_ibc'     => (int)$r['tot_ibc'],
                    'tot_min'     => (int)$r['tot_min'],
                    'antal_skift' => (int)$r['antal_skift'],
                ];
                $totalIbc += (int)$r['tot_ibc'];
                $totalMin += (int)$r['tot_min'];
            }

            // Period average IBC/h — SUM/SUM across all cells (not AVG-of-cell-rates)
            $periodAvg = $totalMin > 0
                ? round($totalIbc * 60.0 / $totalMin, 2)
                : 0;

            // Build structured grid: rows = [dag, kvall, natt], cols = Mon-Sun (dow 2..7,1)
            // MySQL DAYOFWEEK: 1=Sun, 2=Mon, ..., 7=Sat → we want Mon=1..Sun=7 for display
            $dowOrder    = [2, 3, 4, 5, 6, 7, 1]; // Mon→Sun in MySQL DAYOFWEEK
            $dowLabels   = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
            $skiftTypar  = ['dag', 'kvall', 'natt'];
            $skiftLabels = ['Dagskift (06–14)', 'Kvällsskift (14–22)', 'Nattskift (22–06)'];

            $grid = [];
            foreach ($skiftTypar as $idx => $typ) {
                $row = [
                    'skift_typ'   => $typ,
                    'label'       => $skiftLabels[$idx],
                    'cells'       => [],
                    'row_avg'     => 0,
                ];
                $rowIbc = 0;
                $rowMin = 0;
                foreach ($dowOrder as $di => $dow) {
                    $cell = $cells[$typ][$dow] ?? null;
                    $ibch = $cell ? $cell['ibc_per_h'] : null;
                    $row['cells'][] = [
                        'dag_namn'    => $dowLabels[$di],
                        'dow'         => $dow,
                        'ibc_per_h'   => $ibch,
                        'tot_ibc'     => $cell ? $cell['tot_ibc'] : 0,
                        'antal_skift' => $cell ? $cell['antal_skift'] : 0,
                        'vs_avg_pct'  => ($ibch !== null && $periodAvg > 0)
                            ? round(($ibch / $periodAvg - 1) * 100, 1)
                            : null,
                    ];
                    if ($cell) {
                        $rowIbc += $cell['tot_ibc'];
                        $rowMin += $cell['tot_min'];
                    }
                }
                // row_avg: SUM/SUM across all weekdays in this shift type
                $row['row_avg'] = $rowMin > 0
                    ? round($rowIbc * 60.0 / $rowMin, 2)
                    : 0;
                $grid[] = $row;
            }

            // Column averages (per weekday across all shift types) — SUM/SUM
            $colAvgs = [];
            foreach ($dowOrder as $di => $dow) {
                $colIbc = 0;
                $colMin = 0;
                foreach ($skiftTypar as $typ) {
                    $cell = $cells[$typ][$dow] ?? null;
                    if ($cell) {
                        $colIbc += $cell['tot_ibc'];
                        $colMin += $cell['tot_min'];
                    }
                }
                $colAvgs[] = [
                    'dag_namn' => $dowLabels[$di],
                    'avg'      => $colMin > 0 ? round($colIbc * 60.0 / $colMin, 2) : 0,
                ];
            }

            // Best and worst cells
            $best  = null;
            $worst = null;
            foreach ($grid as $row) {
                foreach ($row['cells'] as $cell) {
                    if ($cell['ibc_per_h'] === null || $cell['antal_skift'] < 2) continue;
                    if ($best  === null || $cell['ibc_per_h'] > $best['ibc_per_h'])
                        $best  = ['label' => $row['label'] . ' — ' . $cell['dag_namn'], 'ibc_per_h' => $cell['ibc_per_h'], 'antal_skift' => $cell['antal_skift']];
                    if ($worst === null || $cell['ibc_per_h'] < $worst['ibc_per_h'])
                        $worst = ['label' => $row['label'] . ' — ' . $cell['dag_namn'], 'ibc_per_h' => $cell['ibc_per_h'], 'antal_skift' => $cell['antal_skift']];
                }
            }

            echo json_encode([
                'success'    => true,
                'days'       => $days,
                'from'       => $from,
                'to'         => $to,
                'period_avg' => $periodAvg,
                'grid'       => $grid,
                'col_avgs'   => $colAvgs,
                'best'       => $best,
                'worst'      => $worst,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktionsrytm: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktionsrytm'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getSpeedQualityCorrelation(): void {
        $days = (int)($_GET['days'] ?? 90);
        $days = in_array($days, [30, 90, 180, 365]) ? $days : 90;
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $sql = "
                SELECT
                    d.skiftraknare,
                    d.datum,
                    DAYOFWEEK(d.datum) AS dow,
                    d.op1, d.op2, d.op3,
                    d.product_id,
                    COALESCE(p.name, CONCAT('Produkt ', d.product_id)) AS product_name,
                    d.start_hour,
                    d.ibc_ok / (d.drifttid / 60.0)                                AS ibc_h,
                    d.ibc_ej_ok / (d.ibc_ok + d.ibc_ej_ok) * 100                  AS kass_pct
                FROM (
                    SELECT
                        skiftraknare,
                        datum,
                        MAX(ibc_ok)         AS ibc_ok,
                        MAX(ibc_ej_ok)      AS ibc_ej_ok,
                        MAX(drifttid)       AS drifttid,
                        MAX(op1)            AS op1,
                        MAX(op2)            AS op2,
                        MAX(op3)            AS op3,
                        MAX(product_id)     AS product_id,
                        HOUR(MIN(created_at)) AS start_hour
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) d
                LEFT JOIN rebotling_products p ON d.product_id = p.id
                WHERE d.drifttid > 0
                  AND d.ibc_ok + d.ibc_ej_ok > 0
                  AND d.ibc_ok > 0
                ORDER BY d.datum
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $DOW_LABELS = ['', 'Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'];

            $shifts      = [];
            $ibchVals    = [];
            $kassVals    = [];

            foreach ($rows as $row) {
                $ibcH    = (float)$row['ibc_h'];
                $kassPct = (float)$row['kass_pct'];

                if ($ibcH <= 0 || $ibcH > 150) continue;
                if ($kassPct < 0 || $kassPct > 100) continue;

                $h = (int)$row['start_hour'];
                if ($h >= 6 && $h < 14)       $skiftTyp = 'Dag';
                elseif ($h >= 14 && $h < 22)   $skiftTyp = 'Kväll';
                else                            $skiftTyp = 'Natt';

                $shifts[] = [
                    'skiftraknare' => (int)$row['skiftraknare'],
                    'datum'        => $row['datum'],
                    'ibc_h'        => round($ibcH, 2),
                    'kass_pct'     => round($kassPct, 2),
                    'product_id'   => (int)$row['product_id'],
                    'product_name' => $row['product_name'],
                    'shift_type'   => $skiftTyp,
                    'dow'          => (int)$row['dow'],
                    'dow_label'    => $DOW_LABELS[(int)$row['dow']] ?? '',
                    'op1'          => (int)$row['op1'],
                    'op2'          => (int)$row['op2'],
                    'op3'          => (int)$row['op3'],
                ];
                $ibchVals[] = $ibcH;
                $kassVals[] = $kassPct;
            }

            $count = count($shifts);
            $meanIbcH    = $count > 0 ? array_sum($ibchVals) / $count : 0;
            $meanKassPct = $count > 0 ? array_sum($kassVals)  / $count : 0;

            $sortedIbc  = $ibchVals; sort($sortedIbc);
            $sortedKass = $kassVals;  sort($sortedKass);
            $mid = (int)floor($count / 2);
            $medianIbcH    = $count > 0 ? (($count % 2 === 0) ? ($sortedIbc[$mid-1]  + $sortedIbc[$mid])  / 2 : $sortedIbc[$mid])  : 0;
            $medianKassPct = $count > 0 ? (($count % 2 === 0) ? ($sortedKass[$mid-1] + $sortedKass[$mid]) / 2 : $sortedKass[$mid]) : 0;

            // Pearson correlation
            $corr = 0;
            if ($count > 1) {
                $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0; $sumY2 = 0;
                foreach ($shifts as $s) {
                    $x = $s['ibc_h']; $y = $s['kass_pct'];
                    $sumX  += $x;  $sumY  += $y;
                    $sumXY += $x * $y;
                    $sumX2 += $x * $x;
                    $sumY2 += $y * $y;
                }
                $n = $count;
                $denom = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
                if ($denom > 0) $corr = ($n * $sumXY - $sumX * $sumY) / $denom;
            }

            $abs = abs($corr);
            if ($abs >= 0.7)      $corrStr = $corr > 0 ? 'stark positiv' : 'stark negativ';
            elseif ($abs >= 0.4)  $corrStr = $corr > 0 ? 'måttlig positiv' : 'måttlig negativ';
            elseif ($abs >= 0.2)  $corrStr = $corr > 0 ? 'svag positiv' : 'svag negativ';
            else                  $corrStr = 'ingen korrelation';

            // Quadrant counts (relative to medians)
            $q = ['elite' => 0, 'snabb' => 0, 'noggrann' => 0, 'svag' => 0];
            foreach ($shifts as $s) {
                $hi = $s['ibc_h']    >= $medianIbcH;
                $lo = $s['kass_pct'] <= $medianKassPct;
                if ($hi && $lo)  $q['elite']++;
                elseif ($hi)     $q['snabb']++;
                elseif ($lo)     $q['noggrann']++;
                else             $q['svag']++;
            }

            // Product summary
            $prodCounts = [];
            foreach ($shifts as $s) {
                $pid = $s['product_id'];
                if (!isset($prodCounts[$pid])) {
                    $prodCounts[$pid] = ['id' => $pid, 'name' => $s['product_name'], 'count' => 0];
                }
                $prodCounts[$pid]['count']++;
            }
            usort($prodCounts, fn($a, $b) => $b['count'] - $a['count']);

            echo json_encode([
                'success'   => true,
                'shifts'    => $shifts,
                'stats'     => [
                    'count'           => $count,
                    'mean_ibc_h'      => round($meanIbcH, 2),
                    'mean_kass_pct'   => round($meanKassPct, 2),
                    'median_ibc_h'    => round($medianIbcH, 2),
                    'median_kass_pct' => round($medianKassPct, 2),
                    'correlation'     => round($corr, 3),
                    'corr_strength'   => $corrStr,
                ],
                'quadrants' => $q,
                'products'  => array_values($prodCounts),
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSpeedQualityCorrelation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid fart-kvalitet-analys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getStopptidsmonster(): void {
        try {
            $days = isset($_GET['days']) ? max(30, min(730, (int)$_GET['days'])) : 180;
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            // Query 1: heatmap — stoppage rate by shift-type × weekday
            $sql1 = "
                SELECT dow, skift_typ,
                       SUM(max_stopp)                                   AS tot_stopp,
                       SUM(max_drift)                                   AS tot_drift,
                       SUM(CASE WHEN max_stopp > 0 THEN 1 ELSE 0 END)  AS skift_med_stopp,
                       COUNT(*)                                         AS antal_skift
                FROM (
                    SELECT
                        DAYOFWEEK(datum)                               AS dow,
                        skiftraknare,
                        MAX(COALESCE(driftstopptime, 0))               AS max_stopp,
                        MAX(COALESCE(drifttid, 0))                     AS max_drift,
                        CASE
                            WHEN HOUR(MIN(created_at)) >=  6 AND HOUR(MIN(created_at)) < 14 THEN 'dag'
                            WHEN HOUR(MIN(created_at)) >= 14 AND HOUR(MIN(created_at)) < 22 THEN 'kvall'
                            ELSE 'natt'
                        END AS skift_typ
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND drifttid >= 30
                    GROUP BY DAYOFWEEK(datum), skiftraknare
                ) deduped
                GROUP BY dow, skift_typ
            ";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute([':from1' => $from, ':to1' => $to]);
            $rows1 = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

            $cells    = [];
            $allStopp = 0;
            $allDrift = 0;
            foreach ($rows1 as $r) {
                $dow = (int)$r['dow'];
                $typ = $r['skift_typ'];
                $stoppPct = $r['tot_drift'] > 0
                    ? round($r['tot_stopp'] / $r['tot_drift'] * 100, 2)
                    : 0;
                $snittMin = $r['antal_skift'] > 0
                    ? round($r['tot_stopp'] / $r['antal_skift'], 1)
                    : 0;
                $pctMed = $r['antal_skift'] > 0
                    ? round($r['skift_med_stopp'] / $r['antal_skift'] * 100, 1)
                    : 0;
                $cells[$typ][$dow] = [
                    'stopp_pct'       => $stoppPct,
                    'snitt_stopp_min' => $snittMin,
                    'pct_med_stopp'   => $pctMed,
                    'tot_stopp'       => (int)$r['tot_stopp'],
                    'tot_drift'       => (int)$r['tot_drift'],
                    'antal_skift'     => (int)$r['antal_skift'],
                ];
                $allStopp += (int)$r['tot_stopp'];
                $allDrift += (int)$r['tot_drift'];
            }

            $periodStoppPct = $allDrift > 0
                ? round($allStopp / $allDrift * 100, 2)
                : 0;

            $dowOrder    = [2, 3, 4, 5, 6, 7, 1];
            $dowLabels   = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
            $skiftTypar  = ['dag', 'kvall', 'natt'];
            $skiftLabels = ['Dagskift (06–14)', 'Kvällsskift (14–22)', 'Nattskift (22–06)'];

            $grid     = [];
            $allCells = [];
            foreach ($skiftTypar as $idx => $typ) {
                $row = [
                    'skift_typ' => $typ,
                    'label'     => $skiftLabels[$idx],
                    'cells'     => [],
                    'row_avg'   => 0,
                ];
                $rowStopp = 0;
                $rowDrift = 0;
                foreach ($dowOrder as $di => $dow) {
                    $cell = $cells[$typ][$dow] ?? null;
                    $row['cells'][] = $cell
                        ? array_merge(['dow_label' => $dowLabels[$di], 'dow_idx' => $di], $cell)
                        : ['dow_label' => $dowLabels[$di], 'dow_idx' => $di,
                           'stopp_pct' => null, 'snitt_stopp_min' => null,
                           'pct_med_stopp' => null, 'tot_stopp' => 0,
                           'tot_drift' => 0, 'antal_skift' => 0];
                    if ($cell) {
                        $rowStopp += $cell['tot_stopp'];
                        $rowDrift += $cell['tot_drift'];
                        $allCells[] = [
                            'typ'       => $typ,
                            'typ_label' => $skiftLabels[$idx],
                            'dow_idx'   => $di,
                            'dow_label' => $dowLabels[$di],
                            'stopp_pct' => $cell['stopp_pct'],
                            'antal'     => $cell['antal_skift'],
                        ];
                    }
                }
                $row['row_avg'] = $rowDrift > 0 ? round($rowStopp / $rowDrift * 100, 2) : 0;
                $grid[] = $row;
            }

            // Column averages (per weekday, all shift types combined)
            $colAvgs = [];
            foreach ($dowOrder as $di => $dow) {
                $cStopp = 0;
                $cDrift = 0;
                foreach ($skiftTypar as $typ) {
                    $c = $cells[$typ][$dow] ?? null;
                    if ($c) {
                        $cStopp += $c['tot_stopp'];
                        $cDrift += $c['tot_drift'];
                    }
                }
                $colAvgs[] = $cDrift > 0 ? round($cStopp / $cDrift * 100, 2) : null;
            }

            // Best / worst cells (min 2 shifts)
            $worst = null;
            $best  = null;
            foreach ($allCells as $c) {
                if ($c['antal'] < 2) continue;
                if ($worst === null || $c['stopp_pct'] > $worst['stopp_pct']) $worst = $c;
                if ($best  === null || $c['stopp_pct'] < $best['stopp_pct'])  $best  = $c;
            }

            // Query 2: monthly trend (last 12 months, fixed range)
            $from2 = date('Y-m-d', strtotime('-12 months'));
            $sql2 = "
                SELECT DATE_FORMAT(datum, '%Y-%m')                  AS manad,
                       SUM(max_stopp)                               AS tot_stopp,
                       SUM(max_drift)                               AS tot_drift,
                       SUM(CASE WHEN max_stopp > 0 THEN 1 ELSE 0 END) AS skift_med_stopp,
                       COUNT(*)                                     AS antal_skift
                FROM (
                    SELECT datum,
                           MAX(COALESCE(driftstopptime, 0)) AS max_stopp,
                           MAX(COALESCE(drifttid, 0))       AS max_drift
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND drifttid >= 30
                    GROUP BY datum, skiftraknare
                ) deduped2
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ORDER BY manad
            ";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute([':from2' => $from2, ':to2' => $to]);
            $rows2 = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

            $trend = array_map(fn($r) => [
                'manad'           => $r['manad'],
                'stopp_pct'       => $r['tot_drift'] > 0
                    ? round($r['tot_stopp'] / $r['tot_drift'] * 100, 2)
                    : 0,
                'snitt_stopp_min' => $r['antal_skift'] > 0
                    ? round($r['tot_stopp'] / $r['antal_skift'], 1)
                    : 0,
                'antal_skift'     => (int)$r['antal_skift'],
                'pct_med_stopp'   => $r['antal_skift'] > 0
                    ? round($r['skift_med_stopp'] / $r['antal_skift'] * 100, 1)
                    : 0,
            ], $rows2);

            echo json_encode([
                'success'          => true,
                'grid'             => $grid,
                'col_avgs'         => $colAvgs,
                'dow_labels'       => $dowLabels,
                'period_stopp_pct' => $periodStoppPct,
                'worst_cell'       => $worst,
                'best_cell'        => $best,
                'trend'            => $trend,
                'from'             => $from,
                'to'               => $to,
                'days'             => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getStopptidsmonster: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid stopptidsmönster'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── FEATURE: Fart-Produkt-Matris — IBC/h per operator × product ─────────
    // Heatmap-matris: rader=operatörer, kolumner=produkter, cell=IBC/h.
    // Färgkodas mot produktens teamsnitt.
    // Kräver minst 2 skift per (operatör, produkt).
    private function getSnabbhetProduktMatris(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            $opStmt = $pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            $opMap  = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }

            $pStmt = $pdo->query("SELECT id, name FROM rebotling_products ORDER BY name");
            $prodMap = [];
            foreach ($pStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $prodMap[(int)$r['id']] = $r['name'];
            }

            // Dedup per (op, skiftraknare, product), aggregate ibc_ok + drifttid
            $sql = "
                SELECT u.op_num,
                       u.product_id,
                       SUM(u.ibc)       AS total_ibc,
                       SUM(u.min)       AS total_min,
                       COUNT(*)         AS skift_count
                FROM (
                    SELECT op1                                          AS op_num,
                           MAX(COALESCE(product_id, 0))                AS product_id,
                           MAX(COALESCE(ibc_ok, 0))                    AS ibc,
                           MAX(COALESCE(drifttid, 0))                  AS min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL
                      AND product_id IS NOT NULL AND product_id > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op1

                    UNION ALL

                    SELECT op2,
                           MAX(COALESCE(product_id, 0)),
                           MAX(COALESCE(ibc_ok, 0)),
                           MAX(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL
                      AND product_id IS NOT NULL AND product_id > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op2

                    UNION ALL

                    SELECT op3,
                           MAX(COALESCE(product_id, 0)),
                           MAX(COALESCE(ibc_ok, 0)),
                           MAX(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL
                      AND product_id IS NOT NULL AND product_id > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op3
                ) u
                WHERE u.product_id > 0
                GROUP BY u.op_num, u.product_id
                HAVING total_min >= 60
                ORDER BY u.op_num
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build cells + per-product totals for team avg
            $cells      = [];
            $prodTotals = []; // [prodId => [ibc, min]]
            $usedOps    = [];
            $usedProds  = [];

            foreach ($rows as $r) {
                $opNum  = (int)$r['op_num'];
                $prodId = (int)$r['product_id'];
                if (!isset($opMap[$opNum]) || !isset($prodMap[$prodId])) continue;
                if ((int)$r['skift_count'] < 2) continue;

                $ibc = (float)$r['total_ibc'];
                $min = (float)$r['total_min'];
                $ibch = $min > 0 ? round($ibc / ($min / 60.0), 2) : 0.0;

                $cells[] = [
                    'op_num'      => $opNum,
                    'product_id'  => $prodId,
                    'ibc_per_h'   => $ibch,
                    'skift_count' => (int)$r['skift_count'],
                    'total_ibc'   => (int)$ibc,
                ];

                $usedOps[$opNum]   = $opMap[$opNum];
                $usedProds[$prodId] = $prodMap[$prodId];

                if (!isset($prodTotals[$prodId])) {
                    $prodTotals[$prodId] = ['ibc' => 0.0, 'min' => 0.0];
                }
                $prodTotals[$prodId]['ibc'] += $ibc;
                $prodTotals[$prodId]['min'] += $min;
            }

            // Team IBC/h per product
            $prodTeamAvg = [];
            foreach ($prodTotals as $pid => $t) {
                $prodTeamAvg[$pid] = $t['min'] > 0 ? round($t['ibc'] / ($t['min'] / 60.0), 2) : 0.0;
            }

            // Overall team avg (across all products in view)
            $allIbc = array_sum(array_column($cells, 'total_ibc'));
            $allMin = 0.0;
            foreach ($prodTotals as $t) { $allMin += $t['min']; }
            $teamAvgOverall = $allMin > 0 ? round($allIbc / ($allMin / 60.0), 2) : 0.0;

            asort($usedOps);
            asort($usedProds);

            $operators = array_map(
                fn($num, $name) => ['number' => $num, 'name' => $name],
                array_keys($usedOps), array_values($usedOps)
            );
            $products = array_map(
                fn($id, $name) => ['id' => $id, 'name' => $name],
                array_keys($usedProds), array_values($usedProds)
            );

            // Reformat prodTeamAvg with string keys for JSON
            $prodAvgArr = [];
            foreach ($prodTeamAvg as $pid => $avg) {
                $prodAvgArr[] = ['product_id' => $pid, 'team_avg_ibc_h' => $avg];
            }

            echo json_encode([
                'success'          => true,
                'from'             => $from,
                'to'               => $to,
                'days'             => $days,
                'operators'        => $operators,
                'products'         => $products,
                'cells'            => $cells,
                'prod_team_avg'    => $prodAvgArr,
                'team_avg_overall' => $teamAvgOverall,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSnabbhetProduktMatris: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid fart-produkt-matris'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=fart-stopp&days=90
    private function getFartStoppKorrelation(): void {
        $days = (int)($_GET['days'] ?? 90);
        $days = in_array($days, [30, 90, 180, 365]) ? $days : 90;
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $sql = "
                SELECT
                    d.skiftraknare,
                    d.datum,
                    DAYOFWEEK(d.datum)                                               AS dow,
                    d.op1, d.op2, d.op3,
                    d.product_id,
                    COALESCE(p.name, CONCAT('Produkt ', d.product_id))               AS product_name,
                    d.start_hour,
                    d.ibc_ok / (d.drifttid / 60.0)                                   AS ibc_h,
                    d.driftstopptime / d.drifttid * 100                               AS stopp_pct
                FROM (
                    SELECT
                        skiftraknare,
                        datum,
                        MAX(ibc_ok)            AS ibc_ok,
                        MAX(drifttid)          AS drifttid,
                        MAX(driftstopptime)    AS driftstopptime,
                        MAX(op1)               AS op1,
                        MAX(op2)               AS op2,
                        MAX(op3)               AS op3,
                        MAX(product_id)        AS product_id,
                        HOUR(MIN(created_at))  AS start_hour
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) d
                LEFT JOIN rebotling_products p ON d.product_id = p.id
                WHERE d.drifttid > 0
                  AND d.ibc_ok > 0
                ORDER BY d.datum
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $DOW_LABELS = ['', 'Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'];

            $shifts    = [];
            $ibchVals  = [];
            $stoppVals = [];

            foreach ($rows as $row) {
                $ibcH    = (float)$row['ibc_h'];
                $stoppPct = (float)$row['stopp_pct'];

                if ($ibcH <= 0 || $ibcH > 150) continue;
                if ($stoppPct < 0 || $stoppPct > 100) continue;

                $h = (int)$row['start_hour'];
                if ($h >= 6 && $h < 14)      $skiftTyp = 'Dag';
                elseif ($h >= 14 && $h < 22)  $skiftTyp = 'Kväll';
                else                          $skiftTyp = 'Natt';

                $shifts[] = [
                    'skiftraknare' => (int)$row['skiftraknare'],
                    'datum'        => $row['datum'],
                    'ibc_h'        => round($ibcH, 2),
                    'stopp_pct'    => round($stoppPct, 2),
                    'product_id'   => (int)$row['product_id'],
                    'product_name' => $row['product_name'],
                    'shift_type'   => $skiftTyp,
                    'dow'          => (int)$row['dow'],
                    'dow_label'    => $DOW_LABELS[(int)$row['dow']] ?? '',
                    'op1'          => (int)$row['op1'],
                    'op2'          => (int)$row['op2'],
                    'op3'          => (int)$row['op3'],
                ];
                $ibchVals[]  = $ibcH;
                $stoppVals[] = $stoppPct;
            }

            $count       = count($shifts);
            $meanIbcH    = $count > 0 ? array_sum($ibchVals)  / $count : 0;
            $meanStoppPct = $count > 0 ? array_sum($stoppVals) / $count : 0;

            $sortedIbc  = $ibchVals;  sort($sortedIbc);
            $sortedStopp = $stoppVals; sort($sortedStopp);
            $mid = (int)floor($count / 2);
            $medianIbcH    = $count > 0 ? (($count % 2 === 0) ? ($sortedIbc[$mid-1]  + $sortedIbc[$mid])  / 2 : $sortedIbc[$mid])  : 0;
            $medianStoppPct = $count > 0 ? (($count % 2 === 0) ? ($sortedStopp[$mid-1] + $sortedStopp[$mid]) / 2 : $sortedStopp[$mid]) : 0;

            // Pearson correlation (X=stopp_pct, Y=ibc_h)
            $corr = 0;
            if ($count > 1) {
                $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0; $sumY2 = 0;
                foreach ($shifts as $s) {
                    $x = $s['stopp_pct']; $y = $s['ibc_h'];
                    $sumX  += $x;  $sumY  += $y;
                    $sumXY += $x * $y;
                    $sumX2 += $x * $x;
                    $sumY2 += $y * $y;
                }
                $n = $count;
                $denom = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
                if ($denom > 0) $corr = ($n * $sumXY - $sumX * $sumY) / $denom;
            }

            $abs = abs($corr);
            if ($abs >= 0.7)      $corrStr = $corr > 0 ? 'stark positiv' : 'stark negativ';
            elseif ($abs >= 0.4)  $corrStr = $corr > 0 ? 'måttlig positiv' : 'måttlig negativ';
            elseif ($abs >= 0.2)  $corrStr = $corr > 0 ? 'svag positiv' : 'svag negativ';
            else                  $corrStr = 'ingen korrelation';

            // Quadrant counts (stopp_pct <= median → low stopp; ibc_h >= median → high ibc)
            $q = ['optimal' => 0, 'resilient' => 0, 'coasting' => 0, 'problem' => 0];
            foreach ($shifts as $s) {
                $lowStopp = $s['stopp_pct'] <= $medianStoppPct;
                $highIbc  = $s['ibc_h']    >= $medianIbcH;
                if ($lowStopp && $highIbc)  $q['optimal']++;
                elseif (!$lowStopp && $highIbc) $q['resilient']++;
                elseif ($lowStopp && !$highIbc) $q['coasting']++;
                else                            $q['problem']++;
            }

            // Product summary
            $prodCounts = [];
            foreach ($shifts as $s) {
                $pid = $s['product_id'];
                if (!isset($prodCounts[$pid])) {
                    $prodCounts[$pid] = ['id' => $pid, 'name' => $s['product_name'], 'count' => 0];
                }
                $prodCounts[$pid]['count']++;
            }
            usort($prodCounts, fn($a, $b) => $b['count'] - $a['count']);

            echo json_encode([
                'success'   => true,
                'shifts'    => $shifts,
                'stats'     => [
                    'count'            => $count,
                    'mean_ibc_h'       => round($meanIbcH, 2),
                    'mean_stopp_pct'   => round($meanStoppPct, 2),
                    'median_ibc_h'     => round($medianIbcH, 2),
                    'median_stopp_pct' => round($medianStoppPct, 2),
                    'correlation'      => round($corr, 3),
                    'corr_strength'    => $corrStr,
                ],
                'quadrants' => $q,
                'products'  => array_values($prodCounts),
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getFartStoppKorrelation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid fart-stopp-analys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getKassationStoppKorrelation(): void {
        $days = (int)($_GET['days'] ?? 90);
        $days = in_array($days, [30, 90, 180, 365]) ? $days : 90;
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $sql = "
                SELECT
                    d.skiftraknare,
                    d.datum,
                    DAYOFWEEK(d.datum)                                               AS dow,
                    d.op1, d.op2, d.op3,
                    d.product_id,
                    COALESCE(p.name, CONCAT('Produkt ', d.product_id))               AS product_name,
                    d.start_hour,
                    d.driftstopptime / d.drifttid * 100                               AS stopp_pct,
                    d.ibc_ej_ok / (d.ibc_ok + d.ibc_ej_ok) * 100                     AS kass_pct
                FROM (
                    SELECT
                        skiftraknare,
                        datum,
                        MAX(ibc_ok)            AS ibc_ok,
                        MAX(ibc_ej_ok)         AS ibc_ej_ok,
                        MAX(drifttid)          AS drifttid,
                        MAX(driftstopptime)    AS driftstopptime,
                        MAX(op1)               AS op1,
                        MAX(op2)               AS op2,
                        MAX(op3)               AS op3,
                        MAX(product_id)        AS product_id,
                        HOUR(MIN(created_at))  AS start_hour
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) d
                LEFT JOIN rebotling_products p ON d.product_id = p.id
                WHERE d.drifttid > 0
                  AND (d.ibc_ok + d.ibc_ej_ok) > 0
                ORDER BY d.datum
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $DOW_LABELS = ['', 'Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'];

            $shifts    = [];
            $kassVals  = [];
            $stoppVals = [];

            foreach ($rows as $row) {
                $kassPct  = (float)$row['kass_pct'];
                $stoppPct = (float)$row['stopp_pct'];

                if ($kassPct < 0 || $kassPct > 100) continue;
                if ($stoppPct < 0 || $stoppPct > 100) continue;

                $h = (int)$row['start_hour'];
                if ($h >= 6 && $h < 14)      $skiftTyp = 'Dag';
                elseif ($h >= 14 && $h < 22)  $skiftTyp = 'Kväll';
                else                          $skiftTyp = 'Natt';

                $shifts[] = [
                    'skiftraknare' => (int)$row['skiftraknare'],
                    'datum'        => $row['datum'],
                    'stopp_pct'    => round($stoppPct, 2),
                    'kass_pct'     => round($kassPct, 2),
                    'product_id'   => (int)$row['product_id'],
                    'product_name' => $row['product_name'],
                    'shift_type'   => $skiftTyp,
                    'dow'          => (int)$row['dow'],
                    'dow_label'    => $DOW_LABELS[(int)$row['dow']] ?? '',
                    'op1'          => (int)$row['op1'],
                    'op2'          => (int)$row['op2'],
                    'op3'          => (int)$row['op3'],
                ];
                $kassVals[]  = $kassPct;
                $stoppVals[] = $stoppPct;
            }

            $count        = count($shifts);
            $meanKassPct  = $count > 0 ? array_sum($kassVals)  / $count : 0;
            $meanStoppPct = $count > 0 ? array_sum($stoppVals) / $count : 0;

            $sortedKass  = $kassVals;  sort($sortedKass);
            $sortedStopp = $stoppVals; sort($sortedStopp);
            $mid = (int)floor($count / 2);
            $medianKassPct  = $count > 0 ? (($count % 2 === 0) ? ($sortedKass[$mid-1]  + $sortedKass[$mid])  / 2 : $sortedKass[$mid])  : 0;
            $medianStoppPct = $count > 0 ? (($count % 2 === 0) ? ($sortedStopp[$mid-1] + $sortedStopp[$mid]) / 2 : $sortedStopp[$mid]) : 0;

            // Pearson correlation (X=stopp_pct, Y=kass_pct)
            $corr = 0;
            if ($count > 1) {
                $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0; $sumY2 = 0;
                foreach ($shifts as $s) {
                    $x = $s['stopp_pct']; $y = $s['kass_pct'];
                    $sumX  += $x;  $sumY  += $y;
                    $sumXY += $x * $y;
                    $sumX2 += $x * $x;
                    $sumY2 += $y * $y;
                }
                $n = $count;
                $denom = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
                if ($denom > 0) $corr = ($n * $sumXY - $sumX * $sumY) / $denom;
            }

            $abs = abs($corr);
            if ($abs >= 0.7)      $corrStr = $corr > 0 ? 'stark positiv' : 'stark negativ';
            elseif ($abs >= 0.4)  $corrStr = $corr > 0 ? 'måttlig positiv' : 'måttlig negativ';
            elseif ($abs >= 0.2)  $corrStr = $corr > 0 ? 'svag positiv' : 'svag negativ';
            else                  $corrStr = 'ingen korrelation';

            // Quadrant counts (low stopp + low kassation = optimal)
            $q = ['optimal' => 0, 'resilient' => 0, 'slarvig' => 0, 'dubbelproblem' => 0];
            foreach ($shifts as $s) {
                $lowStopp = $s['stopp_pct'] <= $medianStoppPct;
                $lowKass  = $s['kass_pct']  <= $medianKassPct;
                if ($lowStopp && $lowKass)   $q['optimal']++;
                elseif (!$lowStopp && $lowKass) $q['resilient']++;
                elseif ($lowStopp && !$lowKass) $q['slarvig']++;
                else                            $q['dubbelproblem']++;
            }

            // Product summary
            $prodCounts = [];
            foreach ($shifts as $s) {
                $pid = $s['product_id'];
                if (!isset($prodCounts[$pid])) {
                    $prodCounts[$pid] = ['id' => $pid, 'name' => $s['product_name'], 'count' => 0];
                }
                $prodCounts[$pid]['count']++;
            }
            usort($prodCounts, fn($a, $b) => $b['count'] - $a['count']);

            echo json_encode([
                'success'   => true,
                'shifts'    => $shifts,
                'stats'     => [
                    'count'           => $count,
                    'mean_kass_pct'   => round($meanKassPct, 2),
                    'mean_stopp_pct'  => round($meanStoppPct, 2),
                    'median_kass_pct' => round($medianKassPct, 2),
                    'median_stopp_pct'=> round($medianStoppPct, 2),
                    'correlation'     => round($corr, 3),
                    'corr_strength'   => $corrStr,
                ],
                'quadrants' => $q,
                'products'  => array_values($prodCounts),
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKassationStoppKorrelation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassation-stopp-analys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getProduktbytesanalys(): void {
        $days = (int)($_GET['days'] ?? 90);
        $days = in_array($days, [30, 90, 180, 365]) ? $days : 90;
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Detect product changeovers using LAG() over chronologically ordered deduplicated shifts
            $sql = "
                SELECT
                    f.skiftraknare,
                    f.datum,
                    f.product_id,
                    COALESCE(p.name, CONCAT('Produkt ', f.product_id)) AS product_name,
                    f.ibc_ok,
                    f.drifttid,
                    f.is_changeover
                FROM (
                    SELECT *,
                        CASE
                            WHEN LAG(product_id) OVER (ORDER BY datum, skiftraknare) IS NOT NULL
                              AND product_id != LAG(product_id) OVER (ORDER BY datum, skiftraknare)
                            THEN 1
                            ELSE 0
                        END AS is_changeover
                    FROM (
                        SELECT
                            skiftraknare,
                            datum,
                            MAX(product_id)  AS product_id,
                            MAX(ibc_ok)      AS ibc_ok,
                            MAX(drifttid)    AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE datum BETWEEN :from AND :to
                          AND drifttid >= 30
                          AND ibc_ok > 0
                          AND product_id IS NOT NULL
                        GROUP BY skiftraknare
                    ) dedup
                ) f
                LEFT JOIN rebotling_products p ON p.id = f.product_id
                ORDER BY f.datum, f.skiftraknare
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Aggregate per-product
            $prodMap = [];
            $totalChangeoversIbc   = 0.0;
            $totalChangeoversHours = 0.0;
            $totalContIbc          = 0.0;
            $totalContHours        = 0.0;
            $totalChangeovers      = 0;
            $totalShifts           = 0;

            foreach ($rows as $row) {
                $pid  = (int)$row['product_id'];
                $name = $row['product_name'];
                $ibc  = (int)$row['ibc_ok'];
                $h    = (float)$row['drifttid'] / 60.0;
                $isC  = (int)$row['is_changeover'];

                if (!isset($prodMap[$pid])) {
                    $prodMap[$pid] = [
                        'product_id'          => $pid,
                        'product_name'        => $name,
                        'changeover_ibc'      => 0.0,
                        'changeover_hours'    => 0.0,
                        'continuation_ibc'   => 0.0,
                        'continuation_hours' => 0.0,
                        'changeover_count'   => 0,
                        'continuation_count' => 0,
                    ];
                }

                $totalShifts++;
                if ($isC === 1) {
                    $prodMap[$pid]['changeover_ibc']    += $ibc;
                    $prodMap[$pid]['changeover_hours']  += $h;
                    $prodMap[$pid]['changeover_count']++;
                    $totalChangeoversIbc   += $ibc;
                    $totalChangeoversHours += $h;
                    $totalChangeovers++;
                } else {
                    $prodMap[$pid]['continuation_ibc']   += $ibc;
                    $prodMap[$pid]['continuation_hours'] += $h;
                    $prodMap[$pid]['continuation_count']++;
                    $totalContIbc   += $ibc;
                    $totalContHours += $h;
                }
            }

            // Compute IBC/h and delta for each product
            $products = [];
            foreach ($prodMap as $entry) {
                $cIbch = $entry['changeover_hours'] > 0
                    ? $entry['changeover_ibc'] / $entry['changeover_hours']
                    : null;
                $kIbch = $entry['continuation_hours'] > 0
                    ? $entry['continuation_ibc'] / $entry['continuation_hours']
                    : null;

                $delta    = ($cIbch !== null && $kIbch !== null) ? $cIbch - $kIbch : null;
                $deltaPct = ($delta !== null && $kIbch > 0) ? ($delta / $kIbch) * 100 : null;

                if ($entry['changeover_count'] < 1) continue;

                $products[] = [
                    'product_id'         => $entry['product_id'],
                    'product_name'       => $entry['product_name'],
                    'changeover_ibc_h'   => $cIbch !== null ? round($cIbch, 2) : null,
                    'continuation_ibc_h' => $kIbch !== null ? round($kIbch, 2) : null,
                    'delta'              => $delta !== null ? round($delta, 2) : null,
                    'delta_pct'          => $deltaPct !== null ? round($deltaPct, 1) : null,
                    'changeover_count'   => $entry['changeover_count'],
                    'continuation_count' => $entry['continuation_count'],
                ];
            }

            usort($products, fn($a, $b) => $b['changeover_count'] - $a['changeover_count']);

            // Overall stats
            $overallCIbch = $totalChangeoversHours > 0 ? $totalChangeoversIbc / $totalChangeoversHours : null;
            $overallKIbch = $totalContHours        > 0 ? $totalContIbc        / $totalContHours        : null;
            $overallDelta = ($overallCIbch !== null && $overallKIbch !== null) ? $overallCIbch - $overallKIbch : null;
            $overallDeltaPct = ($overallDelta !== null && $overallKIbch > 0) ? ($overallDelta / $overallKIbch) * 100 : null;

            echo json_encode([
                'success'  => true,
                'from'     => $from,
                'to'       => $to,
                'days'     => $days,
                'products' => $products,
                'overall'  => [
                    'total_shifts'       => $totalShifts,
                    'total_changeovers'  => $totalChangeovers,
                    'changeover_pct'     => $totalShifts > 0 ? round($totalChangeovers / $totalShifts * 100, 1) : 0,
                    'changeover_ibc_h'   => $overallCIbch   !== null ? round($overallCIbch,   2) : null,
                    'continuation_ibc_h' => $overallKIbch   !== null ? round($overallKIbch,   2) : null,
                    'delta'              => $overallDelta    !== null ? round($overallDelta,    2) : null,
                    'delta_pct'          => $overallDeltaPct !== null ? round($overallDeltaPct, 1) : null,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktbytesanalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktbytesanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=tacknings-analys&days=90
    private function getTackningsanalys(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            $posLabels = [
                'op1' => 'Tvättplats',
                'op2' => 'Kontrollstation',
                'op3' => 'Truckförare',
            ];

            // Per operator+position: deduplicate per skiftraknare, then aggregate
            $sql = "
                SELECT u.op_num, u.pos,
                       COALESCE(o.name, CONCAT('Op ', u.op_num)) AS op_name,
                       SUM(u.ibc)   AS total_ibc,
                       SUM(u.min)   AS total_min,
                       COUNT(*)     AS skift_count
                FROM (
                    SELECT op1                       AS op_num,
                           'op1'                     AS pos,
                           MAX(COALESCE(ibc_ok,  0)) AS ibc,
                           MAX(COALESCE(drifttid,0)) AS min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND op1 > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op1

                    UNION ALL

                    SELECT op2,
                           'op2',
                           MAX(COALESCE(ibc_ok,  0)),
                           MAX(COALESCE(drifttid,0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND op2 > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op2

                    UNION ALL

                    SELECT op3,
                           'op3',
                           MAX(COALESCE(ibc_ok,  0)),
                           MAX(COALESCE(drifttid,0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND op3 > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op3
                ) u
                LEFT JOIN operators o ON o.number = u.op_num
                GROUP BY u.op_num, u.pos
                HAVING skift_count >= 2
                ORDER BY op_name, u.pos
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build per-operator structure
            $opData = [];
            foreach ($rows as $r) {
                $num  = (int)$r['op_num'];
                $pos  = $r['pos'];
                $ibc  = (float)$r['total_ibc'];
                $min  = (float)$r['total_min'];
                $ibch = $min > 0 ? round($ibc * 60.0 / $min, 2) : 0.0;

                if (!isset($opData[$num])) {
                    $opData[$num] = [
                        'number'       => $num,
                        'name'         => $r['op_name'],
                        'total_skift'  => 0,
                        'positions'    => [],
                        'primary_pos'  => null,
                    ];
                }
                $opData[$num]['positions'][$pos] = [
                    'pos'         => $pos,
                    'label'       => $posLabels[$pos] ?? $pos,
                    'ibc_per_h'   => $ibch,
                    'skift_count' => (int)$r['skift_count'],
                    'total_ibc'   => (int)$ibc,
                ];
                $opData[$num]['total_skift'] += (int)$r['skift_count'];
            }

            // Determine primary position (most skift) per operator
            foreach ($opData as &$op) {
                $best = null; $bestSkift = 0;
                foreach ($op['positions'] as $pos => $pd) {
                    if ($pd['skift_count'] > $bestSkift) {
                        $bestSkift = $pd['skift_count'];
                        $best = $pos;
                    }
                }
                $op['primary_pos']   = $best;
                $op['primary_label'] = $best ? ($posLabels[$best] ?? $best) : '-';
                $op['positions']     = array_values($op['positions']);
            }
            unset($op);

            // Coverage summary per position: ranked list of qualified operators
            $coverage = [];
            foreach (['op1', 'op2', 'op3'] as $pos) {
                $qualified = [];
                foreach ($opData as $op) {
                    foreach ($op['positions'] as $pd) {
                        if ($pd['pos'] === $pos) {
                            $qualified[] = [
                                'number'      => $op['number'],
                                'name'        => $op['name'],
                                'ibc_per_h'   => $pd['ibc_per_h'],
                                'skift_count' => $pd['skift_count'],
                                'is_primary'  => $op['primary_pos'] === $pos,
                            ];
                        }
                    }
                }
                usort($qualified, fn($a, $b) => $b['ibc_per_h'] <=> $a['ibc_per_h']);

                // Team avg for this position (SUM/SUM)
                $totIbc = array_sum(array_map(fn($q) => $q['ibc_per_h'] * $q['skift_count'], $qualified));
                $totSkift = array_sum(array_column($qualified, 'skift_count'));

                $coverage[$pos] = [
                    'pos'          => $pos,
                    'label'        => $posLabels[$pos],
                    'count'        => count($qualified),
                    'risk'         => count($qualified) < 2 ? 'high' : (count($qualified) < 3 ? 'medium' : 'low'),
                    'operators'    => $qualified,
                ];
            }

            // Backup matrix: for each operator, best backup at their primary position
            $backupMatrix = [];
            foreach ($opData as $op) {
                if (!$op['primary_pos']) continue;
                $pos = $op['primary_pos'];
                $backups = array_filter(
                    $coverage[$pos]['operators'] ?? [],
                    fn($q) => $q['number'] !== $op['number']
                );
                $backups = array_values($backups); // re-index after filter
                $backupMatrix[] = [
                    'op_number'    => $op['number'],
                    'op_name'      => $op['name'],
                    'primary_pos'  => $pos,
                    'primary_label'=> $posLabels[$pos] ?? $pos,
                    'best_backup'  => count($backups) > 0 ? $backups[0] : null,
                    'backup_count' => count($backups),
                ];
            }
            usort($backupMatrix, fn($a, $b) => $a['backup_count'] <=> $b['backup_count']);

            echo json_encode([
                'success'       => true,
                'days'          => $days,
                'period'        => ['from' => $from, 'to' => $to],
                'operators'     => array_values($opData),
                'coverage'      => array_values($coverage),
                'backup_matrix' => $backupMatrix,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getTackningsanalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid täckningsanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=vader-produktion&days=365
    private function getVaderProduktion(): void {
        $days = intval($_GET['days'] ?? 365);
        if ($days < 30 || $days > 1825) $days = 365;

        $sql = "
            WITH daily_prod AS (
                SELECT
                    datum AS dag,
                    SUM(ibc_ok) / NULLIF(SUM(drifttid / 60.0), 0) AS ibc_per_h,
                    COUNT(DISTINCT skiftraknare) AS antal_skift,
                    SUM(drifttid) / 60.0 AS total_timmar
                FROM (
                    SELECT datum, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok,
                           MAX(COALESCE(drifttid, 0)) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days1 DAY)
                      AND drifttid >= 30
                    GROUP BY datum, skiftraknare
                ) dedup
                GROUP BY datum
                HAVING total_timmar >= 1
            ),
            daily_temp AS (
                SELECT
                    DATE(datum) AS dag,
                    AVG(utetemperatur) AS avg_temp
                FROM vader_data
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days2 DAY)
                GROUP BY DATE(datum)
            )
            SELECT
                dp.dag,
                ROUND(dp.ibc_per_h, 2) AS ibc_per_h,
                dp.antal_skift,
                ROUND(dp.total_timmar, 1) AS total_timmar,
                ROUND(dt.avg_temp, 1) AS avg_temp
            FROM daily_prod dp
            INNER JOIN daily_temp dt ON dp.dag = dt.dag
            ORDER BY dp.dag
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':days1' => $days, ':days2' => $days]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) < 3) {
                echo json_encode(['success' => true, 'data' => [], 'bins' => [], 'kpi' => null, 'korrelation' => null], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Pearson r (temperature vs IBC/h)
            $n = count($rows);
            $xArr = array_column($rows, 'avg_temp');
            $yArr = array_column($rows, 'ibc_per_h');
            $xMean = array_sum($xArr) / $n;
            $yMean = array_sum($yArr) / $n;
            $num = 0; $denX = 0; $denY = 0;
            foreach ($rows as $i => $r) {
                $dx = (float)$r['avg_temp'] - $xMean;
                $dy = (float)$r['ibc_per_h'] - $yMean;
                $num += $dx * $dy;
                $denX += $dx * $dx;
                $denY += $dy * $dy;
            }
            $den = sqrt($denX * $denY);
            $korrelation = $den > 0 ? round($num / $den, 3) : 0;

            // Temperaturbin: 5-graders intervall
            $binData = [];
            foreach ($rows as $r) {
                $temp = (float)$r['avg_temp'];
                $binFloor = (int)(floor($temp / 5) * 5);
                $key = $binFloor;
                if (!isset($binData[$key])) {
                    $binData[$key] = ['ibc_sum' => 0, 'timmar_sum' => 0, 'count' => 0];
                }
                $binData[$key]['ibc_sum'] += (float)$r['ibc_per_h'] * (float)$r['total_timmar'];
                $binData[$key]['timmar_sum'] += (float)$r['total_timmar'];
                $binData[$key]['count']++;
            }
            ksort($binData);
            $bins = [];
            foreach ($binData as $floor => $b) {
                $bins[] = [
                    'label'    => sprintf('%d till %d°C', $floor, $floor + 5),
                    'floor'    => $floor,
                    'ibc_per_h' => $b['timmar_sum'] > 0 ? round($b['ibc_sum'] / $b['timmar_sum'], 2) : 0,
                    'antal_dagar' => $b['count'],
                ];
            }

            // KPI
            $allTemps  = array_map('floatval', array_column($rows, 'avg_temp'));
            $allIbch   = array_map('floatval', array_column($rows, 'ibc_per_h'));
            $allTimmar = array_map('floatval', array_column($rows, 'total_timmar'));
            $ttSum = array_sum($allTimmar);
            $ibchWeighted = array_sum(array_map(fn($ibch, $tt) => $ibch * $tt, $allIbch, $allTimmar));
            $kpi = [
                'antal_dagar'   => $n,
                'min_temp'      => round(min($allTemps), 1),
                'max_temp'      => round(max($allTemps), 1),
                'avg_temp'      => round(array_sum($allTemps) / $n, 1),
                'avg_ibc_per_h' => $ttSum > 0 ? round($ibchWeighted / $ttSum, 2) : 0.0,
            ];

            echo json_encode([
                'success'     => true,
                'data'        => $rows,
                'bins'        => $bins,
                'kpi'         => $kpi,
                'korrelation' => $korrelation,
                'days'        => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getVaderProduktion: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid väder-produktion'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=belastningsbalans&days=90
    // Returns per-operator shift count, operating hours, position breakdown for workload fairness analysis.
    private function getBelastningsbalans(): void {
        try {
            $days = max(30, min(730, (int)($_GET['days'] ?? 90)));
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // UNION ALL op1/op2/op3 — unique named params to avoid HY093
            $stmt = $this->pdo->prepare("
                SELECT
                    u.op_num                                    AS number,
                    COALESCE(o.name, CONCAT('Op ', u.op_num))  AS name,
                    COUNT(*)                                    AS antal_skift,
                    SUM(u.drifttid_min)                        AS total_drifttid_min,
                    SUM(u.driftstopptime_min)                  AS total_stoppmin,
                    SUM(CASE WHEN u.pos='op1' THEN 1 ELSE 0 END) AS cnt_op1,
                    SUM(CASE WHEN u.pos='op2' THEN 1 ELSE 0 END) AS cnt_op2,
                    SUM(CASE WHEN u.pos='op3' THEN 1 ELSE 0 END) AS cnt_op3
                FROM (
                    SELECT op1 AS op_num, 'op1' AS pos, skiftraknare,
                           MAX(drifttid) AS drifttid_min, MAX(driftstopptime) AS driftstopptime_min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND op1 > 0
                      AND drifttid >= 30
                    GROUP BY op1, skiftraknare

                    UNION ALL

                    SELECT op2 AS op_num, 'op2' AS pos, skiftraknare,
                           MAX(drifttid) AS drifttid_min, MAX(driftstopptime) AS driftstopptime_min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND op2 > 0
                      AND drifttid >= 30
                    GROUP BY op2, skiftraknare

                    UNION ALL

                    SELECT op3 AS op_num, 'op3' AS pos, skiftraknare,
                           MAX(drifttid) AS drifttid_min, MAX(driftstopptime) AS driftstopptime_min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND op3 > 0
                      AND drifttid >= 30
                    GROUP BY op3, skiftraknare
                ) u
                LEFT JOIN operators o ON o.number = u.op_num
                WHERE o.active = 1 OR o.active IS NULL
                GROUP BY u.op_num, o.name
                HAVING antal_skift >= 1
                ORDER BY antal_skift DESC
            ");
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode([
                    'success'    => true,
                    'operators'  => [],
                    'kpi'        => ['total_skift' => 0, 'snitt_skift' => 0, 'snitt_h' => 0, 'antal_op' => 0, 'gini' => 0],
                    'from'       => $from,
                    'to'         => $to,
                    'days'       => $days,
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            $counts    = array_column($rows, 'antal_skift');
            $totalSkift = array_sum($counts);
            $n         = count($rows);
            $snittSkift = $n > 0 ? round($totalSkift / $n, 1) : 0;
            $totalMin  = array_sum(array_column($rows, 'total_drifttid_min'));
            $snittH    = $n > 0 ? round(($totalMin / $n) / 60.0, 1) : 0;

            // Gini coefficient for shift distribution (0=perfect equal, 1=all to one)
            sort($counts);
            $gini = 0.0;
            if ($n > 0 && $totalSkift > 0) {
                $sumAbsDiff = 0;
                foreach ($counts as $ci) {
                    foreach ($counts as $cj) {
                        $sumAbsDiff += abs($ci - $cj);
                    }
                }
                $gini = round($sumAbsDiff / (2 * $n * $totalSkift), 3);
            }

            $operators = [];
            foreach ($rows as $r) {
                $skift    = (int)$r['antal_skift'];
                $totMin   = (float)$r['total_drifttid_min'];
                $totH     = round($totMin / 60.0, 1);
                $avgH     = $skift > 0 ? round($totMin / $skift / 60.0, 1) : 0;
                $vsSnitt  = $snittSkift > 0 ? round(($skift / $snittSkift - 1) * 100, 1) : 0;
                $c1 = (int)$r['cnt_op1'];
                $c2 = (int)$r['cnt_op2'];
                $c3 = (int)$r['cnt_op3'];
                $operators[] = [
                    'number'       => (int)$r['number'],
                    'name'         => $r['name'],
                    'antal_skift'  => $skift,
                    'total_h'      => $totH,
                    'avg_h'        => $avgH,
                    'vs_snitt'     => $vsSnitt,
                    'cnt_op1'      => $c1,
                    'cnt_op2'      => $c2,
                    'cnt_op3'      => $c3,
                    'pct_op1'      => $skift > 0 ? round($c1 / $skift * 100) : 0,
                    'pct_op2'      => $skift > 0 ? round($c2 / $skift * 100) : 0,
                    'pct_op3'      => $skift > 0 ? round($c3 / $skift * 100) : 0,
                ];
            }

            echo json_encode([
                'success'   => true,
                'operators' => $operators,
                'kpi'       => [
                    'antal_op'   => $n,
                    'total_skift'=> $totalSkift,
                    'snitt_skift'=> $snittSkift,
                    'snitt_h'    => $snittH,
                    'gini'       => $gini,
                ],
                'snitt_skift' => $snittSkift,
                'from'        => $from,
                'to'          => $to,
                'days'        => $days,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getBelastningsbalans: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid belastningsbalans'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=personal-kalender&year=2026&month=4
    private function getPersonalKalender(): void {
        $year  = (int) ($_GET['year']  ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('n'));
        if ($year < 2020 || $year > 2100) $year  = (int) date('Y');
        if ($month < 1   || $month > 12)  $month = (int) date('n');

        $yearmonth   = sprintf('%04d-%02d', $year, $month);
        $daysInMonth = cal_days_in_month(\CAL_GREGORIAN, $month, $year);

        try {
            // 1. Daily IBC/h: dedup per skiftraknare, then sum
            $sql = "
                SELECT datum,
                       SUM(ibc_ok) / NULLIF(SUM(drifttid_h), 0) AS ibc_per_h,
                       SUM(ibc_ok)    AS ibc_ok,
                       SUM(drifttid_h) AS total_h,
                       COUNT(*)       AS num_skift
                FROM (
                    SELECT datum, skiftraknare,
                           MAX(ibc_ok)         AS ibc_ok,
                           MAX(drifttid) / 60.0 AS drifttid_h
                    FROM rebotling_skiftrapport
                    WHERE DATE_FORMAT(datum, '%Y-%m') = :ym1
                    GROUP BY datum, skiftraknare
                ) dedup
                GROUP BY datum
                ORDER BY datum
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':ym1' => $yearmonth]);
            $dailyRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $dailyIbch   = [];
            $totalIbc    = 0;
            $totalHours  = 0.0;
            foreach ($dailyRows as $row) {
                $dailyIbch[$row['datum']] = [
                    'ibc_per_h' => round((float)$row['ibc_per_h'], 2),
                    'ibc_ok'    => (int)$row['ibc_ok'],
                    'num_skift' => (int)$row['num_skift'],
                ];
                $totalIbc   += (int)$row['ibc_ok'];
                $totalHours += (float)$row['total_h'];
            }
            $monthAvg = $totalHours > 0 ? round($totalIbc / $totalHours, 2) : 0;

            // 2. Active operators
            $opStmt = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            );
            $operators = $opStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Attendance: which days each operator worked and at which position
            // UNION (not UNION ALL) deduplicates same-day appearances
            $atSql = "
                SELECT datum, op1 AS op_num, 'op1' AS pos
                FROM rebotling_skiftrapport
                WHERE DATE_FORMAT(datum, '%Y-%m') = :ym2 AND op1 IS NOT NULL
                UNION
                SELECT datum, op2, 'op2'
                FROM rebotling_skiftrapport
                WHERE DATE_FORMAT(datum, '%Y-%m') = :ym3 AND op2 IS NOT NULL
                UNION
                SELECT datum, op3, 'op3'
                FROM rebotling_skiftrapport
                WHERE DATE_FORMAT(datum, '%Y-%m') = :ym4 AND op3 IS NOT NULL
                ORDER BY datum, op_num
            ";
            $atStmt = $this->pdo->prepare($atSql);
            $atStmt->execute([':ym2' => $yearmonth, ':ym3' => $yearmonth, ':ym4' => $yearmonth]);
            $atRows = $atStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build attendance map [op_num][datum] = pos
            // If operator worked multiple positions on same day, keep lowest (op1 < op2 < op3)
            $attendance = [];
            foreach ($atRows as $row) {
                $opNum = (int)$row['op_num'];
                $datum = $row['datum'];
                if (!isset($attendance[$opNum][$datum]) ||
                    $row['pos'] < $attendance[$opNum][$datum]) {
                    $attendance[$opNum][$datum] = $row['pos'];
                }
            }

            // 4. Build per-operator result, sorted by shift count desc then name
            $opResults = [];
            foreach ($operators as $op) {
                $num    = (int)$op['number'];
                $shifts = $attendance[$num] ?? [];
                $posCounts = ['op1' => 0, 'op2' => 0, 'op3' => 0];
                foreach ($shifts as $pos) {
                    if (isset($posCounts[$pos])) $posCounts[$pos]++;
                }
                $opResults[] = [
                    'number'      => $num,
                    'name'        => $op['name'],
                    'shifts'      => $shifts,
                    'shift_count' => count($shifts),
                    'pos_counts'  => $posCounts,
                ];
            }
            usort($opResults, function ($a, $b) {
                if ($b['shift_count'] !== $a['shift_count']) return $b['shift_count'] - $a['shift_count'];
                return strcmp($a['name'], $b['name']);
            });

            echo json_encode([
                'success'         => true,
                'year'            => $year,
                'month'           => $month,
                'days_in_month'   => $daysInMonth,
                'daily'           => $dailyIbch,
                'month_avg'       => round($monthAvg, 2),
                'total_ibc'       => $totalIbc,
                'production_days' => count($dailyIbch),
                'operators'       => $opResults,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getPersonalKalender: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid personal-kalender'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=skift-sekvens&days=365
    // Analyses team IBC/h split by operator rest state:
    //   fresh      = first shift after ≥2 days off
    //   consecutive = shift immediately after yesterday's shift (1-day gap)
    private function getSkiftSekvens(): void {
        $days = max(30, min(730, (int)($_GET['days'] ?? 365)));
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Aggregate fresh vs consecutive performance per operator using a CTE + window function.
            // UNION ALL brings together each operator's shifts from each position;
            // LAG gives previous work date per operator; conditional sums split the totals.
            $sql = "
                WITH base_shifts AS (
                    SELECT op1 AS op_num, datum, skiftraknare,
                           MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND drifttid >= 30
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2, datum, skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND drifttid >= 30
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3, datum, skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND drifttid >= 30
                    GROUP BY op3, skiftraknare
                ),
                with_lag AS (
                    SELECT op_num, datum, ibc_ok, drifttid,
                           LAG(datum) OVER (PARTITION BY op_num ORDER BY datum, skiftraknare) AS prev_datum
                    FROM base_shifts
                ),
                agg AS (
                    SELECT op_num,
                        SUM(ibc_ok)          AS total_ibc,
                        SUM(drifttid) / 60.0 AS total_h,
                        COUNT(*)             AS total_n,
                        SUM(CASE WHEN prev_datum IS NULL OR DATEDIFF(datum, prev_datum) >= 2
                                 THEN ibc_ok ELSE 0 END)          AS fresh_ibc,
                        SUM(CASE WHEN prev_datum IS NULL OR DATEDIFF(datum, prev_datum) >= 2
                                 THEN drifttid / 60.0 ELSE 0 END) AS fresh_h,
                        SUM(CASE WHEN prev_datum IS NULL OR DATEDIFF(datum, prev_datum) >= 2
                                 THEN 1 ELSE 0 END)                AS fresh_n,
                        SUM(CASE WHEN prev_datum IS NOT NULL AND DATEDIFF(datum, prev_datum) = 1
                                 THEN ibc_ok ELSE 0 END)          AS consec_ibc,
                        SUM(CASE WHEN prev_datum IS NOT NULL AND DATEDIFF(datum, prev_datum) = 1
                                 THEN drifttid / 60.0 ELSE 0 END) AS consec_h,
                        SUM(CASE WHEN prev_datum IS NOT NULL AND DATEDIFF(datum, prev_datum) = 1
                                 THEN 1 ELSE 0 END)                AS consec_n
                    FROM with_lag
                    GROUP BY op_num
                    HAVING fresh_n >= 3 AND consec_n >= 3
                )
                SELECT a.op_num, o.name,
                       a.total_ibc, a.total_h, a.total_n,
                       a.fresh_ibc,  a.fresh_h,  a.fresh_n,
                       a.consec_ibc, a.consec_h, a.consec_n
                FROM agg a
                JOIN operators o ON o.number = a.op_num
                WHERE o.active = 1
                ORDER BY o.name
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $operators = [];
            $freshIbchSum  = 0;
            $consecIbchSum = 0;
            $count = 0;

            foreach ($rows as $row) {
                $freshIbch  = (float)$row['fresh_h']  > 0 ? (float)$row['fresh_ibc']  / (float)$row['fresh_h']  : 0;
                $consecIbch = (float)$row['consec_h'] > 0 ? (float)$row['consec_ibc'] / (float)$row['consec_h'] : 0;
                $deltaPct   = $freshIbch > 0 ? round(($consecIbch / $freshIbch - 1) * 100, 1) : 0;

                // badge: positive delta = improves on consecutive days; negative = better when rested
                if ($deltaPct >= 10)       $badge = 'eldig';       // improves when running
                elseif ($deltaPct <= -10)  $badge = 'behover_vila'; // better when rested
                else                       $badge = 'konsistent';

                $operators[] = [
                    'number'      => (int)$row['op_num'],
                    'name'        => $row['name'],
                    'total_skift' => (int)$row['total_n'],
                    'fresh_ibch'  => round($freshIbch, 2),
                    'fresh_n'     => (int)$row['fresh_n'],
                    'consec_ibch' => round($consecIbch, 2),
                    'consec_n'    => (int)$row['consec_n'],
                    'delta_pct'   => $deltaPct,
                    'badge'       => $badge,
                ];

                $freshIbchSum  += $freshIbch;
                $consecIbchSum += $consecIbch;
                $count++;
            }

            // Sort by absolute delta descending (most interesting first)
            usort($operators, fn($a, $b) => abs($b['delta_pct']) <=> abs($a['delta_pct']));

            $avgFreshIbch  = $count > 0 ? round($freshIbchSum  / $count, 2) : 0;
            $avgConsecIbch = $count > 0 ? round($consecIbchSum / $count, 2) : 0;
            $avgDelta      = $avgFreshIbch > 0
                ? round(($avgConsecIbch / $avgFreshIbch - 1) * 100, 1)
                : 0;

            echo json_encode([
                'success'        => true,
                'from'           => $from,
                'to'             => $to,
                'days'           => $days,
                'avg_fresh_ibch' => $avgFreshIbch,
                'avg_consec_ibch'=> $avgConsecIbch,
                'avg_delta_pct'  => $avgDelta,
                'operators'      => $operators,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSkiftSekvens: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skift-sekvens'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getProduktionsmaal(): void {
        try {
            $period = $_GET['period'] ?? 'month';
            $today  = date('Y-m-d');

            switch ($period) {
                case 'week':
                    $from = date('Y-m-d', strtotime('monday this week'));
                    $to   = date('Y-m-d', strtotime('sunday this week'));
                    break;
                case 'quarter':
                    $m      = (int)date('n');
                    $qStart = ((int)(($m - 1) / 3)) * 3 + 1;
                    $from   = date('Y-') . str_pad($qStart, 2, '0', STR_PAD_LEFT) . '-01';
                    $qEnd   = $qStart + 2;
                    $to     = date('Y-') . str_pad($qEnd, 2, '0', STR_PAD_LEFT)
                              . '-' . date('t', mktime(0, 0, 0, $qEnd, 1, (int)date('Y')));
                    break;
                default: // month
                    $from = date('Y-m-01');
                    $to   = date('Y-m-t');
            }

            // Daily totals for the period up to today
            $actualTo = $today < $to ? $today : $to;
            $sqlDaily = "
                SELECT
                    datum,
                    SUM(ibc_ok)    AS ibc,
                    COUNT(*)       AS antal_skift
                FROM (
                    SELECT datum,
                           skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) dedup
                GROUP BY datum
                ORDER BY datum
            ";
            $stmt = $this->pdo->prepare($sqlDaily);
            $stmt->execute([':from1' => $from, ':to1' => $actualTo]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build cumulative series
            $daily       = [];
            $runningSum  = 0;
            foreach ($rows as $r) {
                $runningSum += (int)$r['ibc'];
                $daily[] = [
                    'datum'       => $r['datum'],
                    'ibc'         => (int)$r['ibc'],
                    'cumulative'  => $runningSum,
                    'antal_skift' => (int)$r['antal_skift'],
                ];
            }

            // Recent 14-day pace for projection
            $pace14From = date('Y-m-d', strtotime('-14 days'));
            $sqlPace = "
                SELECT SUM(ibc_ok)            AS total_ibc,
                       COUNT(DISTINCT datum)  AS prod_days
                FROM (
                    SELECT datum,
                           skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) dedup
            ";
            $stmtP = $this->pdo->prepare($sqlPace);
            $stmtP->execute([':from2' => $pace14From, ':to2' => $today]);
            $paceRow     = $stmtP->fetch(\PDO::FETCH_ASSOC);
            $paceIbcDay  = ($paceRow && $paceRow['prod_days'] > 0)
                           ? round((float)$paceRow['total_ibc'] / (float)$paceRow['prod_days'], 1)
                           : 0;

            $totalDays   = max(1, (int)(round((strtotime($to) - strtotime($from)) / 86400)) + 1);
            $daysElapsed = max(1, min($totalDays, (int)(round((strtotime($today) - strtotime($from)) / 86400)) + 1));
            $daysLeft    = max(0, $totalDays - $daysElapsed);
            $actualIbc   = $runningSum;
            $projected   = (int)round($actualIbc + $paceIbcDay * $daysLeft);

            echo json_encode([
                'success'       => true,
                'period'        => $period,
                'from'          => $from,
                'to'            => $to,
                'today'         => $today,
                'daily'         => $daily,
                'actual_ibc'    => $actualIbc,
                'projected_ibc' => $projected,
                'pace_ibc_day'  => $paceIbcDay,
                'days_elapsed'  => $daysElapsed,
                'days_left'     => $daysLeft,
                'total_days'    => $totalDays,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktionsmaal: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktionsmål'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=operator-utveckling&months=12
    private function getOperatorUtveckling(): void {
        try {
            $months = max(6, min(24, (int)($_GET['months'] ?? 12)));
            $today  = date('Y-m-d');
            $from   = date('Y-m-d', strtotime("-{$months} months"));

            // Active operators
            $opStmt = $this->pdo->query(
                "SELECT number, name FROM operators WHERE active = 1 ORDER BY name"
            );
            $opNames = [];
            foreach ($opStmt->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                $opNames[(int)$op['number']] = $op['name'];
            }

            // All deduplicated shifts in period (one row per skiftraknare)
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(MAX(datum), '%Y-%m') AS ym,
                    MAX(op1)      AS op1,
                    MAX(op2)      AS op2,
                    MAX(op3)      AS op3,
                    MAX(ibc_ok)   AS ibc_ok,
                    MAX(drifttid) AS drifttid
                FROM rebotling_skiftrapport
                WHERE datum >= :from AND datum <= :to
                  AND drifttid >= 30 AND ibc_ok > 0
                GROUP BY skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $today]);
            $allShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build ordered month labels (oldest → newest)
            $monthLabels = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $monthLabels[] = date('Y-m', strtotime("-{$i} months"));
            }

            // Accumulate IBC + minutes per operator per month, and team totals per month
            $opData        = []; // $opData[$opNum][$ym] = ['ibc'=>0,'min'=>0,'shifts'=>0]
            $teamByMonth   = []; // $teamByMonth[$ym] = ['ibc'=>0,'min'=>0]

            foreach ($allShifts as $s) {
                $ym  = $s['ym'];
                $ibc = (float)$s['ibc_ok'];
                $min = (float)$s['drifttid'];

                if (!isset($teamByMonth[$ym])) {
                    $teamByMonth[$ym] = ['ibc' => 0.0, 'min' => 0.0];
                }
                $teamByMonth[$ym]['ibc'] += $ibc;
                $teamByMonth[$ym]['min'] += $min;

                foreach ([$s['op1'], $s['op2'], $s['op3']] as $raw) {
                    $opNum = (int)$raw;
                    if ($opNum <= 0) continue;
                    if (!isset($opData[$opNum][$ym])) {
                        $opData[$opNum][$ym] = ['ibc' => 0.0, 'min' => 0.0, 'shifts' => 0];
                    }
                    $opData[$opNum][$ym]['ibc']    += $ibc;
                    $opData[$opNum][$ym]['min']    += $min;
                    $opData[$opNum][$ym]['shifts'] += 1;
                }
            }

            // Team avg IBC/h per month
            $teamAvgByMonth = [];
            foreach ($monthLabels as $ym) {
                $d = $teamByMonth[$ym] ?? null;
                $teamAvgByMonth[$ym] = ($d && $d['min'] > 0)
                    ? round($d['ibc'] / ($d['min'] / 60.0), 2)
                    : 0.0;
            }

            // Build per-operator result
            $result = [];
            foreach ($opNames as $opNum => $opName) {
                if (empty($opData[$opNum])) continue;

                $monthly     = [];
                $totalIbc    = 0.0;
                $totalMin    = 0.0;
                $totalShifts = 0;

                foreach ($monthLabels as $ym) {
                    $d    = $opData[$opNum][$ym] ?? null;
                    $ibch = ($d && $d['min'] > 0)
                        ? round($d['ibc'] / ($d['min'] / 60.0), 2)
                        : 0.0;
                    $monthly[] = [
                        'ym'       => $ym,
                        'ibch'     => $ibch,
                        'shifts'   => $d ? (int)$d['shifts'] : 0,
                        'team_avg' => $teamAvgByMonth[$ym] ?? 0.0,
                    ];
                    if ($d) {
                        $totalIbc    += $d['ibc'];
                        $totalMin    += $d['min'];
                        $totalShifts += $d['shifts'];
                    }
                }

                // Trend: compare first-3 active months vs last-3 active months
                $active = array_values(array_filter($monthly, fn($m) => $m['ibch'] > 0));
                $n      = count($active);

                if ($n >= 4) {
                    $first3    = array_slice($active, 0, 3);
                    $last3     = array_slice($active, -3);
                    $first3Avg = array_sum(array_column($first3, 'ibch')) / 3.0;
                    $last3Avg  = array_sum(array_column($last3,  'ibch')) / 3.0;
                    $deltaPct  = $first3Avg > 0
                        ? round(($last3Avg - $first3Avg) / $first3Avg * 100.0, 1)
                        : 0.0;
                } else {
                    $first3Avg = 0.0;
                    $last3Avg  = $n > 0
                        ? round(array_sum(array_column($active, 'ibch')) / $n, 2)
                        : 0.0;
                    $deltaPct  = 0.0;
                }

                $overallIbch = ($totalMin > 0)
                    ? round($totalIbc / ($totalMin / 60.0), 2)
                    : 0.0;

                if ($overallIbch <= 0) continue;

                if ($deltaPct >= 5.0)       $trend = 'forbattras';
                elseif ($deltaPct <= -5.0)  $trend = 'forsamras';
                else                         $trend = 'stabil';

                $result[] = [
                    'number'       => $opNum,
                    'name'         => $opName,
                    'overall_ibch' => $overallIbch,
                    'total_shifts' => $totalShifts,
                    'first3_avg'   => round($first3Avg, 2),
                    'last3_avg'    => round($last3Avg, 2),
                    'delta_pct'    => $deltaPct,
                    'trend'        => $trend,
                    'monthly'      => $monthly,
                ];
            }

            usort($result, fn($a, $b) => $b['overall_ibch'] <=> $a['overall_ibch']);

            echo json_encode([
                'success'      => true,
                'months'       => $months,
                'from'         => $from,
                'to'           => $today,
                'month_labels' => $monthLabels,
                'team_avg'     => $teamAvgByMonth,
                'operators'    => $result,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getOperatorUtveckling: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid operatörsutveckling'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=veckosammanfattning&year=2026&week=18
    // Returns a full weekly digest: daily breakdown, operator ranking, KPIs vs prev week.
    private function getVeckosammanfattning(): void {
        $year = (int)($_GET['year'] ?? date('Y'));
        $week = (int)($_GET['week'] ?? date('W'));
        $week = max(1, min(53, $week));
        $year = max(2020, min(2030, $year));

        try {
            // Compute Monday and Sunday of requested week (ISO week)
            $monday = new \DateTimeImmutable();
            $monday = $monday->setISODate($year, $week, 1);
            $sunday = $monday->modify('+6 days');
            $from  = $monday->format('Y-m-d');
            $to    = $sunday->format('Y-m-d');

            // Previous week
            $prevMonday = $monday->modify('-7 days');
            $prevSunday = $sunday->modify('-7 days');
            $prevFrom   = $prevMonday->format('Y-m-d');
            $prevTo     = $prevSunday->format('Y-m-d');

            // ── Query 1: daily breakdown for the selected week ──
            $sqlDay = "
                SELECT
                    datum,
                    DAYOFWEEK(datum) AS dow,
                    SUM(ibc_ok)     AS ibc_ok,
                    SUM(drifttid)   AS drifttid_min,
                    SUM(driftstopptime) AS stopp_min,
                    SUM(ibc_ok + COALESCE(ibc_ej_ok,0) + COALESCE(bur_ej_ok,0)) AS tot_ibc,
                    COUNT(*)        AS antal_skift
                FROM (
                    SELECT datum, DAYOFWEEK(datum) AS dow2,
                           MAX(ibc_ok) AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                           MAX(COALESCE(bur_ej_ok,0)) AS bur_ej_ok,
                           MAX(drifttid) AS drifttid,
                           MAX(COALESCE(driftstopptime,0)) AS driftstopptime
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) d
                GROUP BY datum
                ORDER BY datum ASC
            ";
            $stDay = $this->pdo->prepare($sqlDay);
            $stDay->execute([':from1' => $from, ':to1' => $to]);
            $dayRows = $stDay->fetchAll(\PDO::FETCH_ASSOC);

            // ── Query 2: operator ranking for the selected week ──
            $sqlOp = "
                SELECT op_num, op_name,
                       SUM(ibc_ok) AS ibc_ok,
                       SUM(drifttid_min) AS drifttid_min,
                       COUNT(*) AS antal_skift
                FROM (
                    SELECT
                        CASE pos WHEN 1 THEN op1 WHEN 2 THEN op2 ELSE op3 END AS op_num,
                        CASE pos WHEN 1 THEN n1  WHEN 2 THEN n2  ELSE n3  END AS op_name,
                        MAX(ibc_ok)   AS ibc_ok,
                        MAX(drifttid) AS drifttid_min
                    FROM (
                        SELECT r.skiftraknare, r.op1, r.op2, r.op3, r.ibc_ok, r.drifttid,
                               o1.name AS n1, o2.name AS n2, o3.name AS n3
                        FROM rebotling_skiftrapport r
                        LEFT JOIN operators o1 ON o1.number = r.op1
                        LEFT JOIN operators o2 ON o2.number = r.op2
                        LEFT JOIN operators o3 ON o3.number = r.op3
                        WHERE r.datum BETWEEN :from2 AND :to2
                          AND r.drifttid >= 30
                    ) base
                    JOIN (SELECT 1 AS pos UNION ALL SELECT 2 UNION ALL SELECT 3) positions
                    GROUP BY pos, op_num, skiftraknare
                    HAVING op_num IS NOT NULL AND op_num > 0
                ) shifted
                GROUP BY op_num, op_name
                HAVING drifttid_min > 0
                ORDER BY (ibc_ok / (drifttid_min / 60)) DESC
            ";
            $stOp = $this->pdo->prepare($sqlOp);
            $stOp->execute([':from2' => $from, ':to2' => $to]);
            $opRows = $stOp->fetchAll(\PDO::FETCH_ASSOC);

            // ── Query 3: aggregated KPIs for this week and prev week ──
            $sqlKpi = "
                SELECT
                    SUM(ibc_ok) AS ibc_ok,
                    SUM(drifttid_min) AS drifttid_min,
                    SUM(stopp_min) AS stopp_min,
                    SUM(tot) AS tot_ibc,
                    COUNT(*) AS antal_skift
                FROM (
                    SELECT MAX(ibc_ok) AS ibc_ok,
                           MAX(drifttid) AS drifttid_min,
                           MAX(COALESCE(driftstopptime,0)) AS stopp_min,
                           MAX(ibc_ok + COALESCE(ibc_ej_ok,0) + COALESCE(bur_ej_ok,0)) AS tot
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND drifttid >= 30
                    GROUP BY skiftraknare
                ) s
            ";
            $stKpi = $this->pdo->prepare($sqlKpi);
            $stKpi->execute([':from3' => $from, ':to3' => $to]);
            $kpiRow = $stKpi->fetch(\PDO::FETCH_ASSOC);

            $stKpiP = $this->pdo->prepare($sqlKpi);
            $stKpiP->execute([':from3' => $prevFrom, ':to3' => $prevTo]);
            $prevKpiRow = $stKpiP->fetch(\PDO::FETCH_ASSOC);

            // ── Query 4: best single shift this week ──
            $sqlBest = "
                SELECT r.datum, r.skiftraknare,
                       MAX(r.ibc_ok) AS ibc_ok,
                       MAX(r.drifttid) AS drifttid_min,
                       GROUP_CONCAT(DISTINCT COALESCE(o1.name, r.op1) ORDER BY 1 SEPARATOR ', ') AS operators
                FROM rebotling_skiftrapport r
                LEFT JOIN operators o1 ON (o1.number = r.op1 OR o1.number = r.op2 OR o1.number = r.op3)
                WHERE r.datum BETWEEN :from4 AND :to4
                  AND r.drifttid >= 30
                GROUP BY r.skiftraknare
                ORDER BY (MAX(r.ibc_ok) / (MAX(r.drifttid) / 60)) DESC
                LIMIT 1
            ";
            $stBest = $this->pdo->prepare($sqlBest);
            $stBest->execute([':from4' => $from, ':to4' => $to]);
            $bestShift = $stBest->fetch(\PDO::FETCH_ASSOC);

            // Build response
            $days = [];
            $dowNames = ['', 'Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
            foreach ($dayRows as $r) {
                $h = $r['drifttid_min'] > 0 ? round($r['ibc_ok'] / ($r['drifttid_min'] / 60), 2) : 0;
                $kass = $r['tot_ibc'] > 0 ? round(($r['tot_ibc'] - $r['ibc_ok']) / $r['tot_ibc'] * 100, 1) : 0;
                $stoppgrad = $r['drifttid_min'] > 0 ? round($r['stopp_min'] / $r['drifttid_min'] * 100, 1) : 0;
                $days[] = [
                    'datum'      => $r['datum'],
                    'dow'        => $dowNames[(int)$r['dow']] ?? '',
                    'ibc_ok'     => (int)$r['ibc_ok'],
                    'ibc_per_h'  => $h,
                    'kass_pct'   => $kass,
                    'stopp_pct'  => $stoppgrad,
                    'antal_skift'=> (int)$r['antal_skift'],
                ];
            }

            $operators = [];
            foreach ($opRows as $r) {
                $h = $r['drifttid_min'] > 0 ? round($r['ibc_ok'] / ($r['drifttid_min'] / 60), 2) : 0;
                $operators[] = [
                    'number'     => (int)$r['op_num'],
                    'name'       => $r['op_name'] ?? 'Op '.$r['op_num'],
                    'ibc_ok'     => (int)$r['ibc_ok'],
                    'ibc_per_h'  => $h,
                    'antal_skift'=> (int)$r['antal_skift'],
                ];
            }

            $buildKpi = function($row) {
                if (!$row || !$row['drifttid_min']) return null;
                $h = round($row['ibc_ok'] / ($row['drifttid_min'] / 60), 2);
                $kass = $row['tot_ibc'] > 0 ? round(($row['tot_ibc'] - $row['ibc_ok']) / $row['tot_ibc'] * 100, 1) : 0;
                $stopp = $row['drifttid_min'] > 0 ? round($row['stopp_min'] / $row['drifttid_min'] * 100, 1) : 0;
                return [
                    'ibc_ok'     => (int)$row['ibc_ok'],
                    'ibc_per_h'  => $h,
                    'kass_pct'   => $kass,
                    'stopp_pct'  => $stopp,
                    'antal_skift'=> (int)$row['antal_skift'],
                    'timmar'     => round($row['drifttid_min'] / 60, 1),
                ];
            };

            $kpi     = $buildKpi($kpiRow);
            $prevKpi = $buildKpi($prevKpiRow);

            $best = null;
            if ($bestShift) {
                $bh = $bestShift['drifttid_min'] > 0
                    ? round($bestShift['ibc_ok'] / ($bestShift['drifttid_min'] / 60), 2) : 0;
                $best = [
                    'datum'      => $bestShift['datum'],
                    'skiftnr'    => (int)$bestShift['skiftraknare'],
                    'ibc_ok'     => (int)$bestShift['ibc_ok'],
                    'ibc_per_h'  => $bh,
                    'operators'  => $bestShift['operators'] ?? '',
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'   => true,
                'year'      => $year,
                'week'      => $week,
                'from'      => $from,
                'to'        => $to,
                'kpi'       => $kpi,
                'prev_kpi'  => $prevKpi,
                'days'      => $days,
                'operators' => $operators,
                'best_shift'=> $best,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getVeckosammanfattning: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid veckosammanfattning'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=oee-dashboard&days=90
    private function getOEEDashboard(): void {
        $days = (int)($_GET['days'] ?? 90);
        $days = in_array($days, [30, 90, 180, 365]) ? $days : 90;
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Per-shift OEE components with product cycle time
            $sql = "
                SELECT
                    d.skiftraknare,
                    d.datum,
                    d.ibc_ok,
                    d.ibc_ej_ok,
                    d.totalt,
                    d.drifttid,
                    d.driftstopptime,
                    d.product_id,
                    COALESCE(p.name, CONCAT('Produkt ', d.product_id)) AS product_name,
                    COALESCE(p.cycle_time_minutes, 0)                  AS cycle_time_minutes
                FROM (
                    SELECT
                        skiftraknare,
                        datum,
                        MAX(ibc_ok)          AS ibc_ok,
                        MAX(ibc_ej_ok)       AS ibc_ej_ok,
                        MAX(totalt)          AS totalt,
                        MAX(drifttid)        AS drifttid,
                        MAX(driftstopptime)  AS driftstopptime,
                        MAX(product_id)      AS product_id
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND drifttid >= 30
                    GROUP BY skiftraknare, datum
                ) d
                LEFT JOIN rebotling_products p ON p.id = d.product_id
                WHERE d.drifttid > 0
                ORDER BY d.datum ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from1' => $from, ':to1' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $shifts     = [];
            $monthBuckets = [];
            $totalIbc = $totalHours = 0.0;
            $totalIbcEjOk = 0;
            $totalStop = $totalScheduled = 0.0;
            $totalProcessedP = $totalTheoreticalP = 0.0;

            foreach ($rows as $row) {
                $ibc_ok    = (int)$row['ibc_ok'];
                $ibc_ej_ok = (int)$row['ibc_ej_ok'];
                $drifttid  = (float)$row['drifttid'];  // minutes
                $stopptime = (float)$row['driftstopptime'];
                $ct        = (float)$row['cycle_time_minutes'];

                // Availability: run time / (run time + stop time)
                $scheduled = $drifttid + $stopptime;
                $avail = $scheduled > 0 ? $drifttid / $scheduled : null;

                // Performance: actual processed / theoretical max (needs cycle time)
                $processed = $ibc_ok + $ibc_ej_ok;
                $perf = null;
                if ($ct > 0 && $drifttid > 0) {
                    $theoretical = $drifttid / $ct;
                    $perf = $theoretical > 0 ? min($processed / $theoretical, 1.5) : null;
                }

                // Quality: good / total processed
                $qual = $processed > 0 ? $ibc_ok / $processed : null;

                // OEE requires all three components
                $oee = null;
                if ($avail !== null && $perf !== null && $qual !== null) {
                    $oee = $avail * $perf * $qual;
                }

                $mon = substr($row['datum'], 0, 7);
                if (!isset($monthBuckets[$mon])) {
                    $monthBuckets[$mon] = [
                        'drifttid'    => 0.0,
                        'scheduled'   => 0.0,
                        'good'        => 0,
                        'processed'   => 0,
                        'processedP'  => 0.0,
                        'theoretical' => 0.0,
                        'ibc'         => 0,
                        'hours'       => 0.0,
                    ];
                }
                $b = &$monthBuckets[$mon];
                $b['drifttid']  += $drifttid;
                $b['scheduled'] += $scheduled;
                $b['good']      += $ibc_ok;
                $b['processed'] += $processed;
                if ($ct > 0 && $drifttid > 0) {
                    $th = $drifttid / $ct;
                    $b['processedP']   += (float)$processed;
                    $b['theoretical']  += $th;
                    $totalProcessedP   += (float)$processed;
                    $totalTheoreticalP += $th;
                }
                $b['ibc']   += $ibc_ok;
                $b['hours'] += $drifttid / 60.0;

                $totalIbc     += $ibc_ok;
                $totalHours   += $drifttid / 60.0;
                $totalIbcEjOk += $ibc_ej_ok;
                $totalStop    += $stopptime;
                $totalScheduled += $scheduled;

                $shifts[] = [
                    'skiftraknare'  => (int)$row['skiftraknare'],
                    'datum'         => $row['datum'],
                    'ibc_ok'        => $ibc_ok,
                    'ibc_ej_ok'     => $ibc_ej_ok,
                    'drifttid'      => (int)$drifttid,
                    'driftstopptime'=> (int)$stopptime,
                    'product_name'  => $row['product_name'],
                    'cycle_time'    => $ct > 0 ? round($ct, 2) : null,
                    'avail'         => $avail !== null ? round($avail * 100, 1) : null,
                    'perf'          => $perf  !== null ? round($perf  * 100, 1) : null,
                    'qual'          => $qual  !== null ? round($qual  * 100, 1) : null,
                    'oee'           => $oee   !== null ? round($oee   * 100, 1) : null,
                ];
            }
            unset($b);

            // Build monthly trend — SUM/SUM aggregation per month
            ksort($monthBuckets);
            $monthly = [];
            foreach ($monthBuckets as $mon => $b) {
                $mAvail = $b['scheduled']   > 0 ? round($b['drifttid']   / $b['scheduled']   * 100, 1) : null;
                $mQual  = $b['processed']   > 0 ? round($b['good']       / $b['processed']   * 100, 1) : null;
                $mPerf  = $b['theoretical'] > 0 ? round(min($b['processedP'] / $b['theoretical'], 1.5) * 100, 1) : null;
                $mOEE   = ($mAvail !== null && $mPerf !== null && $mQual !== null)
                          ? round($mAvail/100 * $mPerf/100 * $mQual/100 * 100, 1) : null;
                $monthly[] = [
                    'month' => $mon,
                    'avail' => $mAvail,
                    'perf'  => $mPerf,
                    'qual'  => $mQual,
                    'oee'   => $mOEE,
                    'ibc'   => $b['ibc'],
                    'hours' => round($b['hours'], 1),
                ];
            }

            // Overall KPIs — SUM/SUM aggregation
            $kpiAvail = $totalScheduled   > 0 ? round(($totalHours * 60.0) / $totalScheduled * 100, 1) : null;
            $kpiQual  = ($totalIbc + $totalIbcEjOk) > 0 ? round($totalIbc / ($totalIbc + $totalIbcEjOk) * 100, 1) : null;
            $kpiPerf  = $totalTheoreticalP > 0 ? round(min($totalProcessedP / $totalTheoreticalP, 1.5) * 100, 1) : null;
            $kpiOEE   = ($kpiAvail !== null && $kpiPerf !== null && $kpiQual !== null)
                        ? round($kpiAvail/100 * $kpiPerf/100 * $kpiQual/100 * 100, 1) : null;
            $kpiIbcH  = $totalHours > 0 ? round($totalIbc / $totalHours, 2) : null;
            $kpiKass  = ($totalIbc + $totalIbcEjOk) > 0
                        ? round($totalIbcEjOk / ($totalIbc + $totalIbcEjOk) * 100, 1) : null;

            // Product breakdown: avg OEE per product (only where perf calculable)
            $prodMap = [];
            foreach ($shifts as $s) {
                if ($s['oee'] === null) continue;
                $pn = $s['product_name'];
                if (!isset($prodMap[$pn])) $prodMap[$pn] = ['sumOEE' => 0.0, 'cnt' => 0, 'ibc' => 0];
                $prodMap[$pn]['sumOEE'] += $s['oee'];
                $prodMap[$pn]['cnt']++;
                $prodMap[$pn]['ibc'] += $s['ibc_ok'];
            }
            $products = [];
            foreach ($prodMap as $pn => $pd) {
                $products[] = [
                    'name'     => $pn,
                    'avg_oee'  => round($pd['sumOEE'] / $pd['cnt'], 1),
                    'shifts'   => $pd['cnt'],
                    'total_ibc'=> $pd['ibc'],
                ];
            }
            usort($products, fn($a, $b) => $b['avg_oee'] <=> $a['avg_oee']);

            echo json_encode([
                'success'  => true,
                'from'     => $from,
                'to'       => $to,
                'days'     => $days,
                'kpi'      => [
                    'oee'   => $kpiOEE,
                    'avail' => $kpiAvail,
                    'qual'  => $kpiQual,
                    'ibc_h' => $kpiIbcH,
                    'kass'  => $kpiKass,
                    'shifts'=> count($shifts),
                ],
                'monthly'  => $monthly,
                'products' => $products,
                'shifts'   => $shifts,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getOEEDashboard: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid OEE-dashboard'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=operator-rotation&days=90
    // Rotationsanalys: jämför IBC/h när en operatör byter position mot när hen stannar på samma position
    private function getRotationsanalys(): void {
        try {
            $days = max(7, min(730, (int)($_GET['days'] ?? 90)));
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            $sql = "
                WITH combined AS (
                    SELECT op1 AS op_num, skiftraknare, ibc_ok, drifttid, 'op1' AS pos
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1 AND op1 > 0 AND drifttid >= 30
                    UNION ALL
                    SELECT op2, skiftraknare, ibc_ok, drifttid, 'op2'
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2 AND op2 > 0 AND drifttid >= 30
                    UNION ALL
                    SELECT op3, skiftraknare, ibc_ok, drifttid, 'op3'
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3 AND op3 > 0 AND drifttid >= 30
                ),
                with_lag AS (
                    SELECT *,
                        LAG(pos) OVER (PARTITION BY op_num ORDER BY skiftraknare) AS prev_pos
                    FROM combined
                ),
                flagged AS (
                    SELECT *,
                        CASE WHEN prev_pos IS NOT NULL AND pos != prev_pos THEN 1 ELSE 0 END AS is_rot
                    FROM with_lag
                )
                SELECT
                    f.op_num,
                    COALESCE(o.name, CONCAT('Op ', f.op_num)) AS op_name,
                    COUNT(*)                      AS total_skift,
                    SUM(f.is_rot)                 AS rotation_skift,
                    COUNT(*) - SUM(f.is_rot)      AS spec_skift,
                    ROUND(100.0 * SUM(f.is_rot) / COUNT(*), 1) AS rotation_pct,
                    ROUND(SUM(CASE WHEN f.is_rot = 1 THEN f.ibc_ok ELSE 0 END) /
                          NULLIF(SUM(CASE WHEN f.is_rot = 1 THEN f.drifttid / 60.0 ELSE 0 END), 0), 2) AS rot_ibch,
                    ROUND(SUM(CASE WHEN f.is_rot = 0 THEN f.ibc_ok ELSE 0 END) /
                          NULLIF(SUM(CASE WHEN f.is_rot = 0 THEN f.drifttid / 60.0 ELSE 0 END), 0), 2) AS spec_ibch,
                    ROUND(SUM(f.ibc_ok) / NULLIF(SUM(f.drifttid / 60.0), 0), 2)                       AS overall_ibch,
                    SUM(CASE WHEN f.pos = 'op1' THEN 1 ELSE 0 END) AS op1_skift,
                    SUM(CASE WHEN f.pos = 'op2' THEN 1 ELSE 0 END) AS op2_skift,
                    SUM(CASE WHEN f.pos = 'op3' THEN 1 ELSE 0 END) AS op3_skift
                FROM flagged f
                LEFT JOIN operators o ON o.number = f.op_num
                GROUP BY f.op_num, op_name
                HAVING total_skift >= 5
                ORDER BY ABS(COALESCE(rot_ibch, 0) - COALESCE(spec_ibch, 0)) DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $operators   = [];
            $totalRotSkift = 0;
            $totalSkift    = 0;
            $deltas        = [];

            foreach ($rows as $r) {
                $rotSkift  = (int)$r['rotation_skift'];
                $specSkift = (int)$r['spec_skift'];
                if ($rotSkift < 2 || $specSkift < 2) continue;

                $rotIbch  = $r['rot_ibch']  !== null ? (float)$r['rot_ibch']  : null;
                $specIbch = $r['spec_ibch'] !== null ? (float)$r['spec_ibch'] : null;
                $delta    = ($rotIbch !== null && $specIbch !== null) ? round($rotIbch - $specIbch, 2) : null;
                if ($delta !== null) $deltas[] = $delta;

                $operators[] = [
                    'number'         => (int)$r['op_num'],
                    'name'           => $r['op_name'],
                    'total_skift'    => (int)$r['total_skift'],
                    'rotation_skift' => $rotSkift,
                    'spec_skift'     => $specSkift,
                    'rotation_pct'   => (float)$r['rotation_pct'],
                    'rot_ibch'       => $rotIbch,
                    'spec_ibch'      => $specIbch,
                    'overall_ibch'   => (float)$r['overall_ibch'],
                    'delta_ibch'     => $delta,
                    'op1_skift'      => (int)$r['op1_skift'],
                    'op2_skift'      => (int)$r['op2_skift'],
                    'op3_skift'      => (int)$r['op3_skift'],
                ];

                $totalRotSkift += $rotSkift;
                $totalSkift    += (int)$r['total_skift'];
            }

            $avgDelta = count($deltas) > 0 ? round(array_sum($deltas) / count($deltas), 2) : null;

            echo json_encode([
                'success'   => true,
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
                'operators' => $operators,
                'kpi'       => [
                    'antal_op'      => count($operators),
                    'total_skift'   => $totalSkift,
                    'rotation_rate' => $totalSkift > 0 ? round(100.0 * $totalRotSkift / $totalSkift, 1) : 0.0,
                    'avg_delta'     => $avgDelta,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getRotationsanalys: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid rotationsanalys'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=manads-jamforelse&month_a=YYYY-MM&month_b=YYYY-MM
    private function getManadsJamforelse(): void {
        $rawA = preg_replace('/[^0-9\-]/', '', $_GET['month_a'] ?? '');
        $rawB = preg_replace('/[^0-9\-]/', '', $_GET['month_b'] ?? '');

        // Default: current month vs same month last year
        if (empty($rawA)) $rawA = date('Y-m');
        if (empty($rawB)) {
            $rawB = date('Y-m', strtotime('-12 months'));
        }

        // Validate YYYY-MM format
        if (!preg_match('/^\d{4}-\d{2}$/', $rawA) || !preg_match('/^\d{4}-\d{2}$/', $rawB)) {
            echo json_encode(['success' => false, 'error' => 'Ogiltigt månadsformat'], \JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $months = ['a' => $rawA, 'b' => $rawB];
            $result = [];

            foreach ($months as $key => $month) {
                // Summary stats (dedup by skiftraknare first)
                $sqlSum = "
                    SELECT
                        SUM(ibc_ok)  AS total_ibc,
                        SUM(ibc_ej_ok) AS total_ibc_ej,
                        SUM(ibc_ok) / NULLIF(SUM(drifttid / 60.0), 0) AS ibc_per_h,
                        SUM(ibc_ej_ok) / NULLIF(SUM(ibc_ok + ibc_ej_ok), 0) * 100 AS kassation_pct,
                        SUM(driftstopptime) / NULLIF(SUM(drifttid + driftstopptime), 0) * 100 AS stoppgrad,
                        COUNT(DISTINCT skiftraknare) AS antal_skift,
                        COUNT(DISTINCT datum) AS produktionsdagar,
                        ROUND(SUM(drifttid) / 60.0, 1) AS total_timmar
                    FROM (
                        SELECT datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))          AS ibc_ok,
                               MAX(COALESCE(ibc_ej_ok, 0))       AS ibc_ej_ok,
                               MAX(COALESCE(drifttid, 0))        AS drifttid,
                               MAX(COALESCE(driftstopptime, 0))  AS driftstopptime
                        FROM rebotling_skiftrapport
                        WHERE DATE_FORMAT(datum, '%Y-%m') = :month
                          AND drifttid >= 30
                        GROUP BY datum, skiftraknare
                    ) dedup
                ";
                $stmtSum = $this->pdo->prepare($sqlSum);
                $stmtSum->execute([':month' => $month]);
                $sum = $stmtSum->fetch(\PDO::FETCH_ASSOC);

                // Per-day data for overlay chart
                $sqlDay = "
                    SELECT
                        datum,
                        DAY(datum)  AS dag,
                        ROUND(SUM(ibc_ok) / NULLIF(SUM(drifttid / 60.0), 0), 2) AS ibc_per_h,
                        SUM(ibc_ok) AS total_ibc
                    FROM (
                        SELECT datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0))   AS ibc_ok,
                               MAX(COALESCE(drifttid, 0)) AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE DATE_FORMAT(datum, '%Y-%m') = :month
                          AND drifttid >= 30
                        GROUP BY datum, skiftraknare
                    ) dedup
                    GROUP BY datum
                    ORDER BY datum
                ";
                $stmtDay = $this->pdo->prepare($sqlDay);
                $stmtDay->execute([':month' => $month]);
                $days = $stmtDay->fetchAll(\PDO::FETCH_ASSOC);

                // Per-operator IBC/h for this month
                $sqlOp = "
                    SELECT
                        op_num,
                        o.name,
                        ROUND(SUM(ibc_ok) / NULLIF(SUM(drifttid / 60.0), 0), 2) AS ibc_per_h,
                        COUNT(DISTINCT skiftraknare) AS antal_skift,
                        SUM(ibc_ok) AS total_ibc
                    FROM (
                        SELECT skiftraknare, op1 AS op_num,
                               MAX(COALESCE(ibc_ok, 0))   AS ibc_ok,
                               MAX(COALESCE(drifttid, 0)) AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE DATE_FORMAT(datum, '%Y-%m') = :montha
                          AND op1 IS NOT NULL AND drifttid >= 30
                        GROUP BY skiftraknare, op1
                        UNION ALL
                        SELECT skiftraknare, op2 AS op_num,
                               MAX(COALESCE(ibc_ok, 0))   AS ibc_ok,
                               MAX(COALESCE(drifttid, 0)) AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE DATE_FORMAT(datum, '%Y-%m') = :monthb
                          AND op2 IS NOT NULL AND drifttid >= 30
                        GROUP BY skiftraknare, op2
                        UNION ALL
                        SELECT skiftraknare, op3 AS op_num,
                               MAX(COALESCE(ibc_ok, 0))   AS ibc_ok,
                               MAX(COALESCE(drifttid, 0)) AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE DATE_FORMAT(datum, '%Y-%m') = :monthc
                          AND op3 IS NOT NULL AND drifttid >= 30
                        GROUP BY skiftraknare, op3
                    ) combined
                    LEFT JOIN operators o ON o.number = combined.op_num
                    GROUP BY op_num, o.name
                    HAVING antal_skift >= 2
                    ORDER BY ibc_per_h DESC
                ";
                $stmtOp = $this->pdo->prepare($sqlOp);
                $stmtOp->execute([':montha' => $month, ':monthb' => $month, ':monthc' => $month]);
                $operators = $stmtOp->fetchAll(\PDO::FETCH_ASSOC);

                $result[$key] = [
                    'month'       => $month,
                    'summary'     => $sum,
                    'daily'       => $days,
                    'operators'   => $operators,
                ];
            }

            // Build operator comparison: merge month A and B operators by op_num
            $opMap = [];
            foreach ($result['a']['operators'] as $op) {
                $opMap[$op['op_num']]['name']       = $op['name'] ?? 'Okänd';
                $opMap[$op['op_num']]['op_num']     = $op['op_num'];
                $opMap[$op['op_num']]['a_ibch']     = (float)$op['ibc_per_h'];
                $opMap[$op['op_num']]['a_skift']    = (int)$op['antal_skift'];
            }
            foreach ($result['b']['operators'] as $op) {
                $opMap[$op['op_num']]['b_ibch']     = (float)$op['ibc_per_h'];
                $opMap[$op['op_num']]['b_skift']    = (int)$op['antal_skift'];
                if (!isset($opMap[$op['op_num']]['name'])) {
                    $opMap[$op['op_num']]['name']   = $op['name'] ?? 'Okänd';
                    $opMap[$op['op_num']]['op_num'] = $op['op_num'];
                }
            }

            $opComparison = [];
            foreach ($opMap as $entry) {
                $aIbch = $entry['a_ibch'] ?? null;
                $bIbch = $entry['b_ibch'] ?? null;
                $delta = ($aIbch !== null && $bIbch !== null && $bIbch > 0)
                    ? round($aIbch - $bIbch, 2) : null;
                $deltaPct = ($aIbch !== null && $bIbch !== null && $bIbch > 0)
                    ? round(($aIbch - $bIbch) / $bIbch * 100, 1) : null;
                $opComparison[] = [
                    'op_num'    => $entry['op_num'],
                    'name'      => $entry['name'],
                    'a_ibch'    => $aIbch,
                    'a_skift'   => $entry['a_skift'] ?? 0,
                    'b_ibch'    => $bIbch,
                    'b_skift'   => $entry['b_skift'] ?? 0,
                    'delta'     => $delta,
                    'delta_pct' => $deltaPct,
                ];
            }
            // Sort by absolute delta descending (biggest change first)
            usort($opComparison, function ($a, $b) {
                $aa = abs($a['delta'] ?? 0);
                $bb = abs($b['delta'] ?? 0);
                return $bb <=> $aa;
            });

            echo json_encode([
                'success'         => true,
                'month_a'         => $rawA,
                'month_b'         => $rawB,
                'summary_a'       => $result['a']['summary'],
                'summary_b'       => $result['b']['summary'],
                'daily_a'         => $result['a']['daily'],
                'daily_b'         => $result['b']['daily'],
                'op_comparison'   => $opComparison,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getManadsJamforelse: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid månads-jämförelse'], \JSON_UNESCAPED_UNICODE);
        }
    }

    private function getSkifttypsMatris(): void {
        $year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
        $month = max(1, min(12, $month));
        $year  = max(2020, min(2030, $year));

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        try {
            $opRows = $this->pdo->query("SELECT number, name FROM operators ORDER BY number")->fetchAll(\PDO::FETCH_ASSOC);
            $opMap  = [];
            foreach ($opRows as $r) $opMap[(int)$r['number']] = $r['name'];

            // ibc_ok and ibc_ej_ok are PLC daily running counters (D4004/D4005) — they do not
            // reset between shifts on the same day. LAG() computes per-shift deltas so later
            // shifts in the same day are not over-credited with the cumulative day total.
            $stmt = $this->pdo->prepare("
                SELECT skiftraknare, datum,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY datum ORDER BY skiftraknare), 0)) AS ibc_ok,
                       GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY datum ORDER BY skiftraknare), 0)) AS ibc_ej_ok,
                       drifttid, driftstopptime, op1, op2, op3, first_created
                FROM (
                    SELECT skiftraknare,
                           MAX(datum)          AS datum,
                           MAX(ibc_ok)         AS ibc_end,
                           MAX(ibc_ej_ok)      AS ej_end,
                           MAX(drifttid)       AS drifttid,
                           MAX(driftstopptime) AS driftstopptime,
                           MAX(op1)            AS op1,
                           MAX(op2)            AS op2,
                           MAX(op3)            AS op3,
                           MIN(created_at)     AS first_created
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid >= 30
                    GROUP BY skiftraknare
                ) base
                ORDER BY datum, skiftraknare
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $shifts     = [];
            $totalIbc   = 0;
            $totalDrift = 0;
            $byType     = [
                'dag'   => ['ibc' => 0, 'drift' => 0, 'count' => 0],
                'kvall' => ['ibc' => 0, 'drift' => 0, 'count' => 0],
                'natt'  => ['ibc' => 0, 'drift' => 0, 'count' => 0],
            ];

            foreach ($rows as $r) {
                $hour = (int)date('G', strtotime($r['first_created']));
                if ($hour >= 6 && $hour < 14) {
                    $shiftType = 'dag';
                } elseif ($hour >= 14 && $hour < 22) {
                    $shiftType = 'kvall';
                } else {
                    $shiftType = 'natt';
                }

                $ibc   = (int)($r['ibc_ok']    ?? 0);
                $drft  = (int)($r['drifttid']   ?? 0);
                $ibcEj = (int)($r['ibc_ej_ok']  ?? 0);
                $stop  = (int)($r['driftstopptime'] ?? 0);
                $ibcH  = $drft > 0 ? round($ibc / ($drft / 60.0), 2) : 0.0;
                $kass  = ($ibc + $ibcEj) > 0 ? round(100.0 * $ibcEj / ($ibc + $ibcEj), 1) : 0.0;
                $stoppPct = ($drft + $stop) > 0 ? round(100.0 * $stop / ($drft + $stop), 1) : 0.0;
                $dag   = (int)date('j', strtotime($r['datum']));

                $totalIbc   += $ibc;
                $totalDrift += $drft;
                $byType[$shiftType]['ibc']   += $ibc;
                $byType[$shiftType]['drift'] += $drft;
                $byType[$shiftType]['count']++;

                $shifts[] = [
                    'skiftraknare' => (int)$r['skiftraknare'],
                    'datum'        => $r['datum'],
                    'dag'          => $dag,
                    'shift_type'   => $shiftType,
                    'ibc_h'        => $ibcH,
                    'ibc_ok'       => $ibc,
                    'kassation_pct'=> $kass,
                    'stopp_pct'    => $stoppPct,
                    'drifttid'     => $drft,
                    'op1_name'     => $r['op1'] ? ($opMap[(int)$r['op1']] ?? '') : '',
                    'op2_name'     => $r['op2'] ? ($opMap[(int)$r['op2']] ?? '') : '',
                    'op3_name'     => $r['op3'] ? ($opMap[(int)$r['op3']] ?? '') : '',
                    'op1_num'      => (int)($r['op1'] ?? 0),
                    'op2_num'      => (int)($r['op2'] ?? 0),
                    'op3_num'      => (int)($r['op3'] ?? 0),
                ];
            }

            $periodAvg = $totalDrift > 0 ? round($totalIbc / ($totalDrift / 60.0), 2) : 0.0;

            $typeAvg = [];
            foreach ($byType as $t => $v) {
                $typeAvg[$t] = [
                    'ibc_h' => $v['drift'] > 0 ? round($v['ibc'] / ($v['drift'] / 60.0), 2) : 0.0,
                    'count' => $v['count'],
                ];
            }

            $daysInMonth = (int)date('t', strtotime($from));
            $monthNames  = ['', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                            'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];
            $weekdayNames = ['', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];

            // Build day metadata (weekday name for each day)
            $dayMeta = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $dayMeta[] = [
                    'dag'    => $d,
                    'datum'  => $dateStr,
                    'veckodag' => $weekdayNames[(int)date('N', strtotime($dateStr))],
                    'is_weekend' => in_array((int)date('N', strtotime($dateStr)), [6, 7]),
                ];
            }

            echo json_encode([
                'success'     => true,
                'year'        => $year,
                'month'       => $month,
                'month_name'  => $monthNames[$month],
                'days'        => $daysInMonth,
                'period_avg'  => $periodAvg,
                'type_avg'    => $typeAvg,
                'shifts'      => $shifts,
                'day_meta'    => $dayMeta,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSkifttypsMatris: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skifttypsmatris'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=kassationstyper&days=180
    private function getKassationstyper(): void {
        try {
            $days = max(30, min(730, (int)($_GET['days'] ?? 180)));
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Monthly aggregation: IBC kassation vs Bur kassation
            // Dedup per skiftraknare using MAX(), then SUM per month
            $sqlMonthly = "
                SELECT
                    DATE_FORMAT(datum, '%Y-%m') AS month,
                    SUM(shift_ibc_ok)     AS ibc_ok,
                    SUM(shift_ibc_ej_ok)  AS ibc_ej_ok,
                    SUM(shift_bur_ej_ok)  AS bur_ej_ok,
                    SUM(shift_totalt)     AS totalt
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok, 0)) AS shift_bur_ej_ok,
                        MAX(COALESCE(totalt,    0)) AS shift_totalt
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                    GROUP BY datum, skiftraknare
                ) dedup
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ORDER BY month ASC
            ";
            $stmtM = $this->pdo->prepare($sqlMonthly);
            $stmtM->execute([':from1' => $from, ':to1' => $to]);
            $monthlyRows = $stmtM->fetchAll(\PDO::FETCH_ASSOC);

            $monthly = [];
            foreach ($monthlyRows as $r) {
                $ibcTotal = (int)$r['ibc_ok'] + (int)$r['ibc_ej_ok'];
                $allTotal = $ibcTotal + (int)$r['bur_ej_ok'];
                $monthly[] = [
                    'month'        => $r['month'],
                    'ibc_ok'       => (int)$r['ibc_ok'],
                    'ibc_ej_ok'    => (int)$r['ibc_ej_ok'],
                    'bur_ej_ok'    => (int)$r['bur_ej_ok'],
                    'ibc_kass_pct' => $ibcTotal > 0
                        ? round((int)$r['ibc_ej_ok'] / $ibcTotal * 100, 2) : 0.0,
                    'bur_kass_pct' => $allTotal > 0
                        ? round((int)$r['bur_ej_ok'] / $allTotal * 100, 2) : 0.0,
                ];
            }

            // Per-product breakdown
            $sqlProd = "
                SELECT
                    COALESCE(p.name, CONCAT('Produkt ', r.product_id)) AS product_name,
                    r.product_id,
                    SUM(r.shift_ibc_ok)     AS ibc_ok,
                    SUM(r.shift_ibc_ej_ok)  AS ibc_ej_ok,
                    SUM(r.shift_bur_ej_ok)  AS bur_ej_ok,
                    COUNT(*)                AS skift_count
                FROM (
                    SELECT
                        product_id,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok, 0)) AS shift_bur_ej_ok
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND product_id IS NOT NULL
                    GROUP BY product_id, skiftraknare
                ) r
                LEFT JOIN rebotling_products p ON p.id = r.product_id
                GROUP BY r.product_id, p.name
                HAVING skift_count >= 3
                ORDER BY (ibc_ej_ok + bur_ej_ok) DESC
            ";
            $stmtP = $this->pdo->prepare($sqlProd);
            $stmtP->execute([':from2' => $from, ':to2' => $to]);
            $prodRows = $stmtP->fetchAll(\PDO::FETCH_ASSOC);

            $byProduct = [];
            foreach ($prodRows as $r) {
                $ibcTotal = (int)$r['ibc_ok'] + (int)$r['ibc_ej_ok'];
                $allTotal = $ibcTotal + (int)$r['bur_ej_ok'];
                $byProduct[] = [
                    'product_id'   => (int)$r['product_id'],
                    'product_name' => $r['product_name'],
                    'ibc_ok'       => (int)$r['ibc_ok'],
                    'ibc_ej_ok'    => (int)$r['ibc_ej_ok'],
                    'bur_ej_ok'    => (int)$r['bur_ej_ok'],
                    'skift_count'  => (int)$r['skift_count'],
                    'ibc_kass_pct' => $ibcTotal > 0
                        ? round((int)$r['ibc_ej_ok'] / $ibcTotal * 100, 2) : 0.0,
                    'bur_kass_pct' => $allTotal > 0
                        ? round((int)$r['bur_ej_ok'] / $allTotal * 100, 2) : 0.0,
                ];
            }

            // Per-operator breakdown: UNION ALL op1/op2/op3, dedup per (op_num, skiftraknare)
            $sqlOp = "
                SELECT
                    op_num,
                    o.name AS op_name,
                    SUM(ibc_ok)     AS ibc_ok,
                    SUM(ibc_ej_ok)  AS ibc_ej_ok,
                    SUM(bur_ej_ok)  AS bur_ej_ok,
                    COUNT(*)        AS skift_count
                FROM (
                    SELECT op1 AS op_num, skiftraknare,
                           MAX(COALESCE(ibc_ok,0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                           MAX(COALESCE(bur_ej_ok,0)) AS bur_ej_ok
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op1 IS NOT NULL
                    GROUP BY op1, skiftraknare

                    UNION ALL

                    SELECT op2 AS op_num, skiftraknare,
                           MAX(COALESCE(ibc_ok,0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                           MAX(COALESCE(bur_ej_ok,0)) AS bur_ej_ok
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from4 AND :to4
                      AND op2 IS NOT NULL
                    GROUP BY op2, skiftraknare

                    UNION ALL

                    SELECT op3 AS op_num, skiftraknare,
                           MAX(COALESCE(ibc_ok,0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                           MAX(COALESCE(bur_ej_ok,0)) AS bur_ej_ok
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from5 AND :to5
                      AND op3 IS NOT NULL
                    GROUP BY op3, skiftraknare
                ) t
                LEFT JOIN operators o ON o.number = t.op_num
                GROUP BY op_num, o.name
                HAVING skift_count >= 3
                ORDER BY (ibc_ej_ok + bur_ej_ok) DESC
            ";
            $stmtO = $this->pdo->prepare($sqlOp);
            $stmtO->execute([
                ':from3' => $from, ':to3' => $to,
                ':from4' => $from, ':to4' => $to,
                ':from5' => $from, ':to5' => $to,
            ]);
            $opRows = $stmtO->fetchAll(\PDO::FETCH_ASSOC);

            $byOperator = [];
            foreach ($opRows as $r) {
                $ibcTotal = (int)$r['ibc_ok'] + (int)$r['ibc_ej_ok'];
                $allTotal = $ibcTotal + (int)$r['bur_ej_ok'];
                $byOperator[] = [
                    'number'       => (int)$r['op_num'],
                    'name'         => $r['op_name'] ?? "Op {$r['op_num']}",
                    'ibc_ok'       => (int)$r['ibc_ok'],
                    'ibc_ej_ok'    => (int)$r['ibc_ej_ok'],
                    'bur_ej_ok'    => (int)$r['bur_ej_ok'],
                    'skift_count'  => (int)$r['skift_count'],
                    'ibc_kass_pct' => $ibcTotal > 0
                        ? round((int)$r['ibc_ej_ok'] / $ibcTotal * 100, 2) : 0.0,
                    'bur_kass_pct' => $allTotal > 0
                        ? round((int)$r['bur_ej_ok'] / $allTotal * 100, 2) : 0.0,
                ];
            }

            // Overall KPIs for the period
            $totalIbcOk  = array_sum(array_column($monthly, 'ibc_ok'));
            $totalIbcEj  = array_sum(array_column($monthly, 'ibc_ej_ok'));
            $totalBurEj  = array_sum(array_column($monthly, 'bur_ej_ok'));
            $totalIbcAll = $totalIbcOk + $totalIbcEj;
            $totalAll    = $totalIbcAll + $totalBurEj;

            $kpi = [
                'ibc_ok'              => $totalIbcOk,
                'ibc_ej_ok'           => $totalIbcEj,
                'bur_ej_ok'           => $totalBurEj,
                'ibc_kass_pct'        => $totalIbcAll > 0
                    ? round($totalIbcEj / $totalIbcAll * 100, 2) : 0.0,
                'bur_kass_pct'        => $totalAll > 0
                    ? round($totalBurEj / $totalAll * 100, 2) : 0.0,
                'combined_kass_pct'   => $totalAll > 0
                    ? round(($totalIbcEj + $totalBurEj) / $totalAll * 100, 2) : 0.0,
                'ibc_vs_bur_ratio'    => $totalBurEj > 0
                    ? round($totalIbcEj / $totalBurEj, 1) : null,
            ];

            echo json_encode([
                'success'     => true,
                'days'        => $days,
                'from'        => $from,
                'to'          => $to,
                'kpi'         => $kpi,
                'monthly'     => $monthly,
                'by_product'  => $byProduct,
                'by_operator' => $byOperator,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKassationstyper: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassationstyper'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=positions-specialisering&days=90&min_skift=3
    private function getPositionsSpecialisering(): void {
        try {
            $days     = max(30, min(730, (int)($_GET['days'] ?? 90)));
            $minSkift = max(1, min(20, (int)($_GET['min_skift'] ?? 3)));
            $to       = date('Y-m-d');
            $from     = date('Y-m-d', strtotime("-{$days} days"));

            // Per operator per position: SUM(ibc) / SUM(hours) — unique named params, dedup by skiftraknare
            $stmt = $this->pdo->prepare("
                SELECT
                    u.op_num,
                    u.pos,
                    COALESCE(o.name, CONCAT('Op ', u.op_num)) AS op_name,
                    COUNT(*)         AS antal_skift,
                    SUM(u.ibc)       AS total_ibc,
                    SUM(u.hours)     AS total_hours
                FROM (
                    SELECT op1 AS op_num, 'op1' AS pos, skiftraknare,
                           MAX(ibc_ok) AS ibc, MAX(drifttid) / 60.0 AS hours
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 IS NOT NULL AND op1 > 0 AND drifttid >= 30
                    GROUP BY op1, skiftraknare

                    UNION ALL

                    SELECT op2, 'op2', skiftraknare,
                           MAX(ibc_ok), MAX(drifttid) / 60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 IS NOT NULL AND op2 > 0 AND drifttid >= 30
                    GROUP BY op2, skiftraknare

                    UNION ALL

                    SELECT op3, 'op3', skiftraknare,
                           MAX(ibc_ok), MAX(drifttid) / 60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 IS NOT NULL AND op3 > 0 AND drifttid >= 30
                    GROUP BY op3, skiftraknare
                ) u
                LEFT JOIN operators o ON o.number = u.op_num
                GROUP BY u.op_num, u.pos, op_name
                ORDER BY u.op_num, u.pos
            ");
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Group by operator
            $byOp = [];
            foreach ($rows as $r) {
                $opNum = (int)$r['op_num'];
                if (!isset($byOp[$opNum])) {
                    $byOp[$opNum] = [
                        'number' => $opNum,
                        'name'   => $r['op_name'],
                        'pos'    => [],
                    ];
                }
                $byOp[$opNum]['pos'][$r['pos']] = [
                    'antal_skift' => (int)$r['antal_skift'],
                    'total_ibc'   => (float)$r['total_ibc'],
                    'total_hours' => (float)$r['total_hours'],
                ];
            }

            $operators    = [];
            $spreadValues = [];
            $specialists  = 0;
            $generalister = 0;

            foreach ($byOp as $opNum => $op) {
                // Overall IBC/h across all positions (deduplicated — each shift counted once per operator)
                $overallIbc   = 0.0;
                $overallHours = 0.0;
                $totalSkift   = 0;
                foreach ($op['pos'] as $p) {
                    $overallIbc   += $p['total_ibc'];
                    $overallHours += $p['total_hours'];
                    $totalSkift   += $p['antal_skift'];
                }
                if ($overallHours < 0.5) continue;
                $overallIbch = round($overallIbc / $overallHours, 2);

                // Per position specialization index (only positions with >= minSkift shifts)
                $positions   = [];
                $validIndices = [];
                foreach (['op1', 'op2', 'op3'] as $posKey) {
                    if (!isset($op['pos'][$posKey])) {
                        $positions[$posKey] = null;
                        continue;
                    }
                    $p = $op['pos'][$posKey];
                    if ($p['antal_skift'] < $minSkift) {
                        $positions[$posKey] = null;
                        continue;
                    }
                    $posHours = $p['total_hours'];
                    if ($posHours < 0.5) {
                        $positions[$posKey] = null;
                        continue;
                    }
                    $posIbch   = round($p['total_ibc'] / $posHours, 2);
                    $specIndex = $overallIbch > 0 ? round($posIbch / $overallIbch * 100, 1) : 100.0;
                    $positions[$posKey] = [
                        'antal_skift' => $p['antal_skift'],
                        'ibch'        => $posIbch,
                        'spec_index'  => $specIndex,
                    ];
                    $validIndices[] = $specIndex;
                }

                // Need data at ≥ 2 positions to calculate specialization spread
                if (count($validIndices) < 2) continue;

                $spread = round(max($validIndices) - min($validIndices), 1);
                $spreadValues[] = $spread;

                // Best position = highest spec_index
                $bestPos   = null;
                $bestIndex = -1;
                foreach ($positions as $pk => $pd) {
                    if ($pd !== null && $pd['spec_index'] > $bestIndex) {
                        $bestIndex = $pd['spec_index'];
                        $bestPos   = $pk;
                    }
                }

                // Badge: Specialist if spread > 15%, Generalist if < 8%
                if ($spread > 15) {
                    $badge = 'specialist';
                    $specialists++;
                } elseif ($spread < 8) {
                    $badge = 'generalist';
                    $generalister++;
                } else {
                    $badge = 'flexibel';
                }

                $operators[] = [
                    'number'       => $opNum,
                    'name'         => $op['name'],
                    'total_skift'  => $totalSkift,
                    'overall_ibch' => $overallIbch,
                    'positions'    => $positions,
                    'best_pos'     => $bestPos,
                    'spread'       => $spread,
                    'badge'        => $badge,
                ];
            }

            // Sort by spread descending
            usort($operators, fn($a, $b) => $b['spread'] <=> $a['spread']);

            $avgSpread = count($spreadValues) > 0
                ? round(array_sum($spreadValues) / count($spreadValues), 1)
                : 0.0;

            echo json_encode([
                'success'   => true,
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
                'operators' => $operators,
                'kpi' => [
                    'antal_op'    => count($operators),
                    'specialists' => $specialists,
                    'generalister'=> $generalister,
                    'avg_spread'  => $avgSpread,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getPositionsSpecialisering: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid positionsspecialisering'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=skift-logg&from=YYYY-MM-DD&to=YYYY-MM-DD[&op=N][&product=N][&sort=datum][&dir=desc]
    private function getSkiftLogg(): void {
        $from    = $_GET['from']    ?? date('Y-m-d', strtotime('-90 days'));
        $to      = $_GET['to']      ?? date('Y-m-d');
        $op      = isset($_GET['op'])      ? (int)$_GET['op']      : 0;
        $prod    = isset($_GET['product']) ? (int)$_GET['product'] : 0;
        $sort    = $_GET['sort']    ?? 'datum';
        $dir     = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $allowed = ['datum', 'skiftraknare', 'ibc_ok', 'ibc_per_h', 'kassation_pct', 'stopp_pct'];
        if (!in_array($sort, $allowed, true)) $sort = 'datum';

        try {
            $params = [':from' => $from, ':to' => $to];

            $outerParts = [];
            if ($op > 0) {
                $outerParts[] = '(s.op1 = :op_a OR s.op2 = :op_b OR s.op3 = :op_c)';
                $params[':op_a'] = $op;
                $params[':op_b'] = $op;
                $params[':op_c'] = $op;
            }
            if ($prod > 0) {
                $outerParts[] = 's.product_id = :prod';
                $params[':prod'] = $prod;
            }
            $outerWhere = $outerParts ? 'WHERE ' . implode(' AND ', $outerParts) : '';

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta so later
            // shifts in the same day are not over-credited with earlier shifts' cumulative total.
            $sql = "
                SELECT s.skiftraknare, s.datum, s.ibc_ok, s.ibc_ej_ok, s.bur_ej_ok,
                       s.op1, s.op2, s.op3, s.product_id, s.drifttid, s.driftstopptime,
                       COALESCE(p.name, CONCAT('Produkt #', s.product_id)) AS product_name,
                       ROUND(s.ibc_ok / NULLIF(s.drifttid / 60.0, 0), 2)                          AS ibc_per_h,
                       ROUND(s.ibc_ej_ok / NULLIF(s.ibc_ok + s.ibc_ej_ok, 0) * 100, 1)           AS kassation_pct,
                       ROUND(s.driftstopptime / NULLIF(s.drifttid, 0) * 100, 1)                   AS stopp_pct,
                       COALESCE(o1.name, '-') AS op1_name,
                       COALESCE(o2.name, '-') AS op2_name,
                       COALESCE(o3.name, '-') AS op3_name
                FROM (
                    SELECT skiftraknare, dag AS datum, op1, op2, op3, product_id, drifttid, driftstopptime,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok,
                           GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ej_ok,
                           GREATEST(0, bur_end - COALESCE(LAG(bur_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS bur_ej_ok
                    FROM (
                        SELECT skiftraknare,
                               DATE(MAX(datum))     AS dag,
                               MAX(op1)             AS op1,
                               MAX(op2)             AS op2,
                               MAX(op3)             AS op3,
                               MAX(product_id)      AS product_id,
                               MAX(ibc_ok)          AS ibc_end,
                               MAX(ibc_ej_ok)       AS ej_end,
                               MAX(bur_ej_ok)       AS bur_end,
                               MAX(drifttid)        AS drifttid,
                               MAX(driftstopptime)  AS driftstopptime
                        FROM rebotling_skiftrapport
                        WHERE datum BETWEEN :from AND :to
                        GROUP BY skiftraknare
                    ) base
                ) s
                LEFT JOIN rebotling_products p  ON p.id        = s.product_id
                LEFT JOIN operators          o1 ON o1.number   = s.op1
                LEFT JOIN operators          o2 ON o2.number   = s.op2
                LEFT JOIN operators          o3 ON o3.number   = s.op3
                $outerWhere
                ORDER BY $sort $dir
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Summary KPIs (SUM/SUM aggregation)
            $sumParams = [':from2' => $from, ':to2' => $to];
            $sumOuter  = [];
            if ($op > 0) { $sumOuter[] = '(s.op1 = :op2a OR s.op2 = :op2b OR s.op3 = :op2c)'; $sumParams[':op2a'] = $op; $sumParams[':op2b'] = $op; $sumParams[':op2c'] = $op; }
            if ($prod > 0) { $sumOuter[] = 's.product_id = :prod2';                           $sumParams[':prod2'] = $prod; }
            $sumOuterWhere = $sumOuter ? 'WHERE ' . implode(' AND ', $sumOuter) : '';

            $sumSql = "
                SELECT COUNT(*) AS total_shifts,
                       SUM(s.ibc_ok)                                                          AS total_ibc,
                       ROUND(SUM(s.ibc_ok) / NULLIF(SUM(s.drifttid) / 60.0, 0), 2)           AS avg_ibch,
                       ROUND(SUM(s.ibc_ej_ok) / NULLIF(SUM(s.ibc_ok)+SUM(s.ibc_ej_ok),0)*100, 1) AS avg_kass_pct,
                       ROUND(SUM(s.driftstopptime) / NULLIF(SUM(s.drifttid), 0) * 100, 1)    AS avg_stopp_pct
                FROM (
                    SELECT skiftraknare, op1, op2, op3, product_id, drifttid, driftstopptime,
                           GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok,
                           GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ej_ok
                    FROM (
                        SELECT skiftraknare,
                               DATE(MAX(datum))    AS dag,
                               MAX(op1)            AS op1,
                               MAX(op2)            AS op2,
                               MAX(op3)            AS op3,
                               MAX(product_id)     AS product_id,
                               MAX(ibc_ok)         AS ibc_end,
                               MAX(ibc_ej_ok)      AS ej_end,
                               MAX(drifttid)       AS drifttid,
                               MAX(driftstopptime) AS driftstopptime
                        FROM rebotling_skiftrapport
                        WHERE datum BETWEEN :from2 AND :to2
                        GROUP BY skiftraknare
                    ) base2
                ) s
                $sumOuterWhere
            ";
            $sumStmt = $this->pdo->prepare($sumSql);
            $sumStmt->execute($sumParams);
            $kpi = $sumStmt->fetch(\PDO::FETCH_ASSOC);

            // Operators list (for filter dropdown)
            $ops = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name")
                             ->fetchAll(\PDO::FETCH_ASSOC);

            // Products list (for filter dropdown)
            $prods = $this->pdo->query("SELECT id, name FROM rebotling_products ORDER BY name")
                               ->fetchAll(\PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success'   => true,
                'from'      => $from,
                'to'        => $to,
                'shifts'    => $rows,
                'kpi'       => $kpi,
                'operators' => $ops,
                'products'  => $prods,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSkiftLogg: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skiftlogg'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=skift-avvikelser&days=180
    private function getSkiftAvvikelser(): void {
        $days = max(30, min(730, (int)($_GET['days'] ?? 180)));
        $to   = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // 1. Fetch all deduplicated shifts in period
            $sql = "
                SELECT
                    r.skiftraknare,
                    MIN(r.datum)         AS datum,
                    SUM(r.ibc_ok)        AS ibc_ok,
                    SUM(r.ibc_ej_ok)     AS ibc_ej_ok,
                    SUM(r.drifttid)      AS drifttid,
                    SUM(r.driftstopptime) AS driftstopptime,
                    MAX(r.op1)           AS op1,
                    MAX(r.op2)           AS op2,
                    MAX(r.op3)           AS op3,
                    MAX(r.product_id)    AS product_id
                FROM rebotling_skiftrapport r
                WHERE r.datum BETWEEN :from AND :to
                GROUP BY r.skiftraknare
                HAVING SUM(r.drifttid) >= 30
                ORDER BY MIN(r.datum) DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($raw)) {
                echo json_encode([
                    'success' => true,
                    'period'  => ['from' => $from, 'to' => $to, 'days' => $days, 'total_shifts' => 0, 'anomaly_count' => 0, 'anomaly_rate' => 0],
                    'stats'   => [],
                    'anomalies' => ['low_prod' => [], 'high_prod' => [], 'high_kass' => [], 'high_stopp' => []],
                    'operator_correlation' => [],
                    'product_correlation'  => [],
                    'monthly_trend'        => [],
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Resolve operator names
            $opNums = [];
            foreach ($raw as $r) {
                foreach (['op1','op2','op3'] as $k) {
                    if (!empty($r[$k])) $opNums[] = (int)$r[$k];
                }
            }
            $opNums  = array_values(array_unique(array_filter($opNums)));
            $opNames = [];
            if (!empty($opNums)) {
                $ph = implode(',', array_fill(0, count($opNums), '?'));
                $s2 = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ($ph)");
                $s2->execute($opNums);
                foreach ($s2->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                    $opNames[(int)$op['number']] = $op['name'];
                }
            }

            // Resolve product names
            $prodIds  = array_values(array_unique(array_filter(array_column($raw, 'product_id'))));
            $prodNames = [];
            if (!empty($prodIds)) {
                $ph2 = implode(',', array_fill(0, count($prodIds), '?'));
                $s3  = $this->pdo->prepare("SELECT id, name FROM rebotling_products WHERE id IN ($ph2)");
                $s3->execute($prodIds);
                foreach ($s3->fetchAll(\PDO::FETCH_ASSOC) as $pr) {
                    $prodNames[(int)$pr['id']] = $pr['name'];
                }
            }

            // 2. Build per-shift metrics
            $shifts = [];
            foreach ($raw as $r) {
                $hours = $r['drifttid'] / 60.0;
                if ($hours <= 0) continue;
                $ibc_h    = $r['ibc_ok'] / $hours;
                $totalt   = $r['ibc_ok'] + $r['ibc_ej_ok'];
                $kass_pct = $totalt > 0 ? ($r['ibc_ej_ok'] / $totalt) * 100 : 0.0;
                $sched    = $r['drifttid'] + $r['driftstopptime'];
                $stopp_pct = $sched > 0 ? ($r['driftstopptime'] / $sched) * 100 : 0.0;

                $op1 = $r['op1'] ? (int)$r['op1'] : null;
                $op2 = $r['op2'] ? (int)$r['op2'] : null;
                $op3 = $r['op3'] ? (int)$r['op3'] : null;
                $pid = $r['product_id'] ? (int)$r['product_id'] : null;

                $shifts[] = [
                    'skiftraknare' => (int)$r['skiftraknare'],
                    'datum'        => $r['datum'],
                    'ibc_h'        => round($ibc_h, 2),
                    'ibc_ok'       => (int)$r['ibc_ok'],
                    'kass_pct'     => round($kass_pct, 2),
                    'stopp_pct'    => round($stopp_pct, 1),
                    'drifttid'     => (int)$r['drifttid'],
                    'op1'          => $op1,
                    'op2'          => $op2,
                    'op3'          => $op3,
                    'op1_name'     => $op1 ? ($opNames[$op1] ?? "Op $op1") : null,
                    'op2_name'     => $op2 ? ($opNames[$op2] ?? "Op $op2") : null,
                    'op3_name'     => $op3 ? ($opNames[$op3] ?? "Op $op3") : null,
                    'product_id'   => $pid,
                    'product_name' => $pid ? ($prodNames[$pid] ?? "Produkt $pid") : null,
                    'z_ibc'        => 0.0,
                    'z_kass'       => 0.0,
                    'z_stopp'      => 0.0,
                ];
            }

            $n = count($shifts);
            if ($n === 0) {
                echo json_encode(['success' => true, 'period' => ['from' => $from, 'to' => $to, 'days' => $days,
                    'total_shifts' => 0, 'anomaly_count' => 0, 'anomaly_rate' => 0],
                    'stats' => [], 'anomalies' => [], 'operator_correlation' => [], 'product_correlation' => [], 'monthly_trend' => []], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // 3. Calculate mean + stddev for each metric
            $sumH = array_sum(array_column($shifts, 'ibc_h'));
            $sumK = array_sum(array_column($shifts, 'kass_pct'));
            $sumS = array_sum(array_column($shifts, 'stopp_pct'));
            $avgH = $sumH / $n;
            $avgK = $sumK / $n;
            $avgS = $sumS / $n;

            $varH = array_sum(array_map(fn($s) => pow($s['ibc_h']    - $avgH, 2), $shifts)) / $n;
            $varK = array_sum(array_map(fn($s) => pow($s['kass_pct'] - $avgK, 2), $shifts)) / $n;
            $varS = array_sum(array_map(fn($s) => pow($s['stopp_pct']- $avgS, 2), $shifts)) / $n;
            $stdH = sqrt($varH);
            $stdK = sqrt($varK);
            $stdS = sqrt($varS);

            $threshold = 1.5;

            // 4. Calculate Z-scores and classify anomalies
            $lowProd   = [];
            $highProd  = [];
            $highKass  = [];
            $highStopp = [];
            $anomalySet = [];

            foreach ($shifts as &$s) {
                $s['z_ibc']   = $stdH > 0 ? round(($s['ibc_h']    - $avgH) / $stdH, 2) : 0.0;
                $s['z_kass']  = $stdK > 0 ? round(($s['kass_pct'] - $avgK) / $stdK, 2) : 0.0;
                $s['z_stopp'] = $stdS > 0 ? round(($s['stopp_pct']- $avgS) / $stdS, 2) : 0.0;

                if ($s['z_ibc'] < -$threshold) { $lowProd[]   = $s; $anomalySet[$s['skiftraknare']] = true; }
                if ($s['z_ibc'] >  $threshold) { $highProd[]  = $s; $anomalySet[$s['skiftraknare']] = true; }
                if ($s['z_kass']>  $threshold) { $highKass[]  = $s; $anomalySet[$s['skiftraknare']] = true; }
                if ($s['z_stopp']> $threshold) { $highStopp[] = $s; $anomalySet[$s['skiftraknare']] = true; }
            }
            unset($s);

            usort($lowProd,   fn($a,$b) => $a['ibc_h']    <=> $b['ibc_h']);
            usort($highProd,  fn($a,$b) => $b['ibc_h']    <=> $a['ibc_h']);
            usort($highKass,  fn($a,$b) => $b['kass_pct'] <=> $a['kass_pct']);
            usort($highStopp, fn($a,$b) => $b['stopp_pct']<=> $a['stopp_pct']);

            $anomalyCount = count($anomalySet);
            $anomalyRate  = round($anomalyCount / $n * 100, 1);

            // 5. Operator correlation
            $opStats = [];
            foreach ($shifts as $s) {
                foreach (['op1','op2','op3'] as $pos) {
                    $num = $s[$pos];
                    if (!$num) continue;
                    if (!isset($opStats[$num])) {
                        $opStats[$num] = [
                            'op_number'   => $num,
                            'name'        => $s[$pos.'_name'] ?? "Op $num",
                            'total'       => 0,
                            'low_prod'    => 0,
                            'high_kass'   => 0,
                            'high_stopp'  => 0,
                        ];
                    }
                    $opStats[$num]['total']++;
                    if ($s['z_ibc']  < -$threshold) $opStats[$num]['low_prod']++;
                    if ($s['z_kass'] >  $threshold)  $opStats[$num]['high_kass']++;
                    if ($s['z_stopp']>  $threshold)  $opStats[$num]['high_stopp']++;
                }
            }
            $opCorr = [];
            foreach ($opStats as $o) {
                if ($o['total'] < 3) continue;
                $o['low_prod_pct']   = round($o['low_prod']   / $o['total'] * 100, 1);
                $o['high_kass_pct']  = round($o['high_kass']  / $o['total'] * 100, 1);
                $o['high_stopp_pct'] = round($o['high_stopp'] / $o['total'] * 100, 1);
                $opCorr[] = $o;
            }
            usort($opCorr, fn($a,$b) => ($b['low_prod_pct'] + $b['high_kass_pct']) <=> ($a['low_prod_pct'] + $a['high_kass_pct']));

            // 6. Product correlation
            $prodStats = [];
            foreach ($shifts as $s) {
                $pid = $s['product_id'];
                if (!$pid) continue;
                if (!isset($prodStats[$pid])) {
                    $prodStats[$pid] = [
                        'product_id' => $pid,
                        'name'       => $s['product_name'] ?? "Produkt $pid",
                        'total'      => 0, 'low_prod' => 0, 'high_kass' => 0,
                    ];
                }
                $prodStats[$pid]['total']++;
                if ($s['z_ibc']  < -$threshold) $prodStats[$pid]['low_prod']++;
                if ($s['z_kass'] >  $threshold)  $prodStats[$pid]['high_kass']++;
            }
            $prodCorr = [];
            foreach ($prodStats as $p) {
                if ($p['total'] < 3) continue;
                $p['low_prod_pct']  = round($p['low_prod']  / $p['total'] * 100, 1);
                $p['high_kass_pct'] = round($p['high_kass'] / $p['total'] * 100, 1);
                $prodCorr[] = $p;
            }
            usort($prodCorr, fn($a,$b) => $b['low_prod_pct'] <=> $a['low_prod_pct']);

            // 7. Monthly anomaly trend
            $monthStats = [];
            foreach ($shifts as $s) {
                $month = substr($s['datum'], 0, 7);
                if (!isset($monthStats[$month])) {
                    $monthStats[$month] = ['month' => $month, 'total' => 0, 'anomaly' => 0];
                }
                $monthStats[$month]['total']++;
                if (isset($anomalySet[$s['skiftraknare']])) $monthStats[$month]['anomaly']++;
            }
            ksort($monthStats);
            $monthTrend = array_values(array_map(function($m) {
                $m['anomaly_rate'] = $m['total'] > 0 ? round($m['anomaly'] / $m['total'] * 100, 1) : 0.0;
                return $m;
            }, $monthStats));

            echo json_encode([
                'success' => true,
                'period'  => [
                    'from'          => $from,
                    'to'            => $to,
                    'days'          => $days,
                    'total_shifts'  => $n,
                    'anomaly_count' => $anomalyCount,
                    'anomaly_rate'  => $anomalyRate,
                ],
                'stats' => [
                    'avg_ibc_h'       => round($avgH, 2),
                    'std_ibc_h'       => round($stdH, 2),
                    'avg_kass'        => round($avgK, 2),
                    'std_kass'        => round($stdK, 2),
                    'avg_stopp'       => round($avgS, 1),
                    'std_stopp'       => round($stdS, 1),
                    'threshold_sigma' => $threshold,
                ],
                'anomalies' => [
                    'low_prod'   => array_slice($lowProd, 0, 25),
                    'high_prod'  => array_slice($highProd, 0, 10),
                    'high_kass'  => array_slice($highKass, 0, 25),
                    'high_stopp' => array_slice($highStopp, 0, 25),
                ],
                'operator_correlation' => $opCorr,
                'product_correlation'  => $prodCorr,
                'monthly_trend'        => $monthTrend,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSkiftAvvikelser: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid skiftavvikelser'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // Stjärnoperatörer — composite performance score per operator
    // ================================================================
    private function getStjarnoperatorer(): void {
        try {
            $days    = max(30, min(365, intval($_GET['period'] ?? 90)));
            $toDate  = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));
            $from30  = date('Y-m-d', strtotime('-30 days'));

            // ibc_ok is a PLC daily running counter — LAG() computes per-shift delta so later
            // shifts in the same day are not over-credited with the cumulative total.
            // Operator names fetched separately; no UNION ALL needed.
            $opNames = [];
            foreach ($this->pdo->query("SELECT number, name FROM operators")->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            $shiftSql = "
                SELECT skiftraknare, dag, op1, op2, op3, drifttid, totalt,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok,
                       GREATEST(0, ej_end  - COALESCE(LAG(ej_end)  OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ej_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(ibc_ej_ok)   AS ej_end,
                           MAX(totalt)      AS totalt,
                           MAX(drifttid)    AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :fromDate AND :toDate AND drifttid >= 30
                    GROUP BY skiftraknare
                ) base
            ";
            $stShift = $this->pdo->prepare($shiftSql);
            $stShift->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
            $allShifts = $stShift->fetchAll(\PDO::FETCH_ASSOC);

            // PHP attribution: each of op1/op2/op3 gets credit for the shift's ibc_ok delta.
            // Team accumulates once per shift (not per position).
            $byOp    = [];
            $teamAcc = ['ibc'=>0,'min'=>0,'kassEj'=>0,'totalt'=>0,'rIbc'=>0,'rMin'=>0];

            foreach ($allShifts as $s) {
                $dag    = $s['dag'];
                $ibc    = max(0, (int)$s['ibc_ok']);
                $min    = max(0, (int)$s['drifttid']);
                $kassEj = max(0, (int)$s['ibc_ej_ok']);
                $tot    = max(0, (int)$s['totalt']);
                if ($min === 0) continue;

                $isRecent = ($dag >= $from30);
                $teamAcc['ibc']    += $ibc;
                $teamAcc['min']    += $min;
                $teamAcc['kassEj'] += $kassEj;
                $teamAcc['totalt'] += $tot;
                if ($isRecent) { $teamAcc['rIbc'] += $ibc; $teamAcc['rMin'] += $min; }

                foreach ([ ['op1',1], ['op2',2], ['op3',3] ] as [$pos, $posIdx]) {
                    $opNum = (int)$s[$pos];
                    if ($opNum <= 0) continue;
                    if (!isset($byOp[$opNum])) {
                        $byOp[$opNum] = ['p_ibc'=>0,'p_min'=>0,'p_kass_ej'=>0,'p_totalt'=>0,'p_shifts'=>0,
                                         'r_ibc'=>0,'r_min'=>0,'r_shifts'=>0,'pos1'=>0,'pos2'=>0,'pos3'=>0];
                    }
                    $byOp[$opNum]['p_ibc']     += $ibc;
                    $byOp[$opNum]['p_min']     += $min;
                    $byOp[$opNum]['p_kass_ej'] += $kassEj;
                    $byOp[$opNum]['p_totalt']  += $tot;
                    $byOp[$opNum]['p_shifts']  += 1;
                    $byOp[$opNum]["pos{$posIdx}"] += 1;
                    if ($isRecent) {
                        $byOp[$opNum]['r_ibc']    += $ibc;
                        $byOp[$opNum]['r_min']    += $min;
                        $byOp[$opNum]['r_shifts'] += 1;
                    }
                }
            }

            // Build $opRows in same shape the build-block below expects, filter p_shifts>=3
            $opRows = [];
            foreach ($byOp as $opNum => $acc) {
                if ($acc['p_shifts'] < 3) continue;
                $opRows[] = array_merge($acc, [
                    'op_num' => $opNum,
                    'naam'   => $opNames[$opNum] ?? "Op {$opNum}",
                ]);
            }

            // Team averages (LAG-corrected, SUM/SUM)
            $teamIbcH  = $teamAcc['min']  > 0 ? $teamAcc['ibc']    / ($teamAcc['min']  / 60.0) : 0.0;
            $teamRIbcH = $teamAcc['rMin'] > 0 ? $teamAcc['rIbc']   / ($teamAcc['rMin'] / 60.0) : $teamIbcH;
            $teamKassR = $teamAcc['totalt'] > 0 ? $teamAcc['kassEj'] / $teamAcc['totalt'] * 100.0 : 0.0;


            // ---- Build per-operator result ----
            $operators = [];
            foreach ($opRows as $r) {
                $pIbcH = ($r['p_min'] > 0) ? round($r['p_ibc'] / ($r['p_min'] / 60.0), 2) : 0.0;
                $rIbcH = ($r['r_min'] > 0) ? round($r['r_ibc'] / ($r['r_min'] / 60.0), 2) : null;
                $kassR = ($r['p_totalt'] > 0) ? round($r['p_kass_ej'] / $r['p_totalt'] * 100.0, 2) : 0.0;

                // vs-team (period)
                $vsTeamP = ($teamIbcH > 0) ? round(($pIbcH / $teamIbcH - 1.0) * 100, 1) : 0.0;
                // vs-team (recent 30d)
                $vsTeamR = ($rIbcH !== null && $teamRIbcH > 0)
                    ? round(($rIbcH / $teamRIbcH - 1.0) * 100, 1)
                    : $vsTeamP;

                // Trend: how much has recent changed vs period (%)
                $trendPct = ($pIbcH > 0 && $rIbcH !== null)
                    ? round(($rIbcH / $pIbcH - 1.0) * 100, 1)
                    : 0.0;

                // Versatility: how many positions with ≥2 shifts
                $posUsed = 0;
                if ($r['pos1'] >= 2) $posUsed++;
                if ($r['pos2'] >= 2) $posUsed++;
                if ($r['pos3'] >= 2) $posUsed++;

                // --- Composite star score (0-100) ---
                // Speed (0-60 pts): capped at ±30% vs team (recent)
                $speedScore = max(0.0, min(60.0, 30.0 + $vsTeamR));

                // Trend (0-20 pts)
                $trendScore = 0.0;
                if ($trendPct >= 10.0)     $trendScore = 20.0;
                elseif ($trendPct >= 5.0)  $trendScore = 12.0;
                elseif ($trendPct >= 0.0)  $trendScore = 5.0;

                // Versatility (0-10 pts)
                $versScore = ($posUsed >= 3) ? 10.0 : (($posUsed >= 2) ? 5.0 : 0.0);

                // Experience (0-5 pts)
                $expScore = ($r['p_shifts'] >= 20) ? 5.0 : (($r['p_shifts'] >= 10) ? 3.0 : 0.0);

                // Kassation quality (0-5 pts)
                $kassScore = 0.0;
                if ($teamKassR > 0) {
                    if ($kassR <= $teamKassR)        $kassScore = 5.0;
                    elseif ($kassR <= $teamKassR * 1.5) $kassScore = 2.0;
                }

                $totalScore = round($speedScore + $trendScore + $versScore + $expScore + $kassScore, 1);

                // Star level
                if ($totalScore >= 75)      $level = 'stjarna';
                elseif ($totalScore >= 55)  $level = 'nyckel';
                elseif ($totalScore >= 35)  $level = 'solid';
                elseif ($totalScore >= 15)  $level = 'potential';
                else                        $level = 'stod';

                // Trend direction label
                if ($trendPct >= 5.0)       $trendLabel = 'foerbattras';
                elseif ($trendPct <= -5.0)  $trendLabel = 'forsamras';
                else                        $trendLabel = 'stabil';

                // Strengths array
                $strengths = [];
                if ($vsTeamR >= 15.0)       $strengths[] = 'Hög fart';
                elseif ($vsTeamR >= 5.0)    $strengths[] = 'Bra fart';
                if ($trendPct >= 5.0)       $strengths[] = 'Förbättrande trend';
                elseif ($trendPct >= 10.0)  $strengths[] = 'Stark förbättring';
                if ($posUsed >= 3)          $strengths[] = 'Mångsidig (3 pos)';
                elseif ($posUsed >= 2)      $strengths[] = 'Flexibel (2 pos)';
                if ($kassR < $teamKassR && $teamKassR > 0) $strengths[] = 'Låg kassation';
                if ($r['p_shifts'] >= 20)   $strengths[] = 'Erfaren';
                if (empty($strengths))      $strengths[] = 'Stabilt bidrag';

                $operators[] = [
                    'op_num'        => (int)$r['op_num'],
                    'name'          => $r['naam'],
                    'period_ibc_h'  => $pIbcH,
                    'recent_ibc_h'  => $rIbcH,
                    'vs_team_period' => $vsTeamP,
                    'vs_team_recent' => $vsTeamR,
                    'trend_pct'     => $trendPct,
                    'trend_label'   => $trendLabel,
                    'kassation_pct' => $kassR,
                    'team_kass_pct' => round($teamKassR, 2),
                    'p_shifts'      => (int)$r['p_shifts'],
                    'r_shifts'      => (int)$r['r_shifts'],
                    'pos1'          => (int)$r['pos1'],
                    'pos2'          => (int)$r['pos2'],
                    'pos3'          => (int)$r['pos3'],
                    'pos_used'      => $posUsed,
                    'score'         => $totalScore,
                    'level'         => $level,
                    'strengths'     => $strengths,
                    'speed_score'   => round($speedScore, 1),
                    'trend_score'   => $trendScore,
                    'vers_score'    => $versScore,
                    'exp_score'     => $expScore,
                    'kass_score'    => $kassScore,
                ];
            }

            usort($operators, fn($a, $b) => $b['score'] <=> $a['score']);

            // KPI counts
            $counts = ['stjarna' => 0, 'nyckel' => 0, 'solid' => 0, 'potential' => 0, 'stod' => 0];
            $improving = 0;
            foreach ($operators as $op) {
                $counts[$op['level']]++;
                if ($op['trend_label'] === 'foerbattras') $improving++;
            }

            echo json_encode([
                'success'       => true,
                'period'        => $days,
                'from_date'     => $fromDate,
                'to_date'       => $toDate,
                'team_ibc_h'    => round($teamIbcH, 2),
                'team_kass_pct' => round($teamKassR, 2),
                'counts'        => $counts,
                'improving'     => $improving,
                'operators'     => $operators,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getStjarnoperatorer: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid stjärnoperatörer'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=produkt-kvalitetstrender&months=12
    private function getProduktKvalitetstrender(): void {
        try {
            $pdo    = $this->pdo;
            $months = isset($_GET['months']) ? max(3, min(24, (int)$_GET['months'])) : 12;
            $to     = date('Y-m-d');
            $from   = date('Y-m-d', strtotime("-{$months} months"));

            // Deduplicate per skiftraknare, then aggregate by month + product
            $sql = "
                SELECT s.yr, s.mo, s.product_id,
                       MAX(COALESCE(p.name, CONCAT('Produkt ', s.product_id))) AS product_name,
                       SUM(s.ibc_ej) AS total_ibc_ej,
                       SUM(s.bur_ej) AS total_bur_ej,
                       SUM(s.tot)    AS total_tot,
                       SUM(s.ibc_ok_val) AS total_ibc_ok,
                       COUNT(*)          AS num_shifts
                FROM (
                    SELECT YEAR(datum)                AS yr,
                           MONTH(datum)               AS mo,
                           product_id,
                           skiftraknare,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej,
                           MAX(COALESCE(bur_ej_ok, 0)) AS bur_ej,
                           MAX(COALESCE(totalt,    0)) AS tot,
                           MAX(COALESCE(ibc_ok,    0)) AS ibc_ok_val
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to
                      AND drifttid >= 30
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare, YEAR(datum), MONTH(datum), product_id
                ) s
                LEFT JOIN rebotling_products p ON p.id = s.product_id
                GROUP BY s.yr, s.mo, s.product_id
                ORDER BY s.yr, s.mo, s.product_id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build per-product monthly data
            $productData = [];
            $monthKeys   = [];

            foreach ($rows as $r) {
                $pid    = (int)$r['product_id'];
                $yr     = (int)$r['yr'];
                $mo     = (int)$r['mo'];
                $mKey   = sprintf('%04d-%02d', $yr, $mo);
                $tot    = (float)$r['total_tot'];
                $ej     = (float)$r['total_ibc_ej'];
                $bur    = (float)$r['total_bur_ej'];
                $shifts = (int)$r['num_shifts'];

                // Combined kassation% = (ibc_ej_ok + bur_ej_ok) / totalt * 100
                $kass_pct = $tot > 0 ? round(($ej + $bur) / $tot * 100, 2) : 0.0;

                $monthKeys[$mKey] = true;

                if (!isset($productData[$pid])) {
                    $productData[$pid] = [
                        'id'        => $pid,
                        'name'      => $r['product_name'],
                        'total_ej'  => 0,
                        'total_tot' => 0,
                        'monthly'   => [],
                    ];
                }
                $productData[$pid]['total_ej']  += ($ej + $bur);
                $productData[$pid]['total_tot'] += $tot;
                $productData[$pid]['monthly'][$mKey] = [
                    'month'      => $mKey,
                    'yr'         => $yr,
                    'mo'         => $mo,
                    'kass_pct'   => $kass_pct,
                    'total_tot'  => (int)$tot,
                    'total_ej'   => (int)($ej + $bur),
                    'num_shifts' => $shifts,
                ];
            }

            // Sorted month list (x-axis labels)
            $allMonths = array_keys($monthKeys);
            sort($allMonths);

            // Filter: min 3 months data, min 10 totalt IBC; sort by volume desc; top 10
            $filtered = array_values(array_filter($productData, function ($p) {
                return count($p['monthly']) >= 3 && $p['total_tot'] >= 10;
            }));
            usort($filtered, fn($a, $b) => $b['total_tot'] <=> $a['total_tot']);
            $topProducts = array_slice($filtered, 0, 10);

            // Build result with full monthly series + trend
            $result = [];
            foreach ($topProducts as $p) {
                $avgKass = $p['total_tot'] > 0
                    ? round($p['total_ej'] / $p['total_tot'] * 100, 2) : 0.0;

                // Align monthly series to allMonths
                $series = [];
                foreach ($allMonths as $mKey) {
                    if (isset($p['monthly'][$mKey])) {
                        $series[] = $p['monthly'][$mKey];
                    } else {
                        $series[] = [
                            'month'      => $mKey,
                            'yr'         => (int)substr($mKey, 0, 4),
                            'mo'         => (int)substr($mKey, 5, 2),
                            'kass_pct'   => null,
                            'total_tot'  => 0,
                            'total_ej'   => 0,
                            'num_shifts' => 0,
                        ];
                    }
                }

                // Trend: compare SUM/SUM of first half vs second half of months with data
                $withData   = array_values(array_filter($series, fn($s) => $s['kass_pct'] !== null));
                $n          = count($withData);
                $trend      = 'stabil';
                $delta_pp   = 0.0;
                if ($n >= 4) {
                    $half       = intdiv($n, 2);
                    $first      = array_slice($withData, 0, $half);
                    $last       = array_slice($withData, $n - $half);
                    $firstTot   = array_sum(array_column($first, 'total_tot'));
                    $firstEj    = array_sum(array_column($first, 'total_ej'));
                    $lastTot    = array_sum(array_column($last,  'total_tot'));
                    $lastEj     = array_sum(array_column($last,  'total_ej'));
                    $firstAvg   = $firstTot > 0 ? $firstEj / $firstTot * 100 : 0.0;
                    $lastAvg    = $lastTot  > 0 ? $lastEj  / $lastTot  * 100 : 0.0;
                    $delta_pp   = round($lastAvg - $firstAvg, 2);
                    if      ($delta_pp >  1.5) $trend = 'försämras';
                    elseif  ($delta_pp < -1.5) $trend = 'förbättras';
                }

                $result[] = [
                    'id'          => $p['id'],
                    'name'        => $p['name'],
                    'avg_kass_pct'=> $avgKass,
                    'total_tot'   => (int)$p['total_tot'],
                    'total_ej'    => (int)$p['total_ej'],
                    'trend'       => $trend,
                    'delta_pp'    => $delta_pp,
                    'monthly'     => $series,
                ];
            }

            // Team-wide monthly cassation (all products)
            $teamMonthly = [];
            foreach ($allMonths as $mKey) {
                $totAll = 0; $ejAll = 0; $shiftsAll = 0;
                foreach ($productData as $p) {
                    if (isset($p['monthly'][$mKey])) {
                        $totAll    += $p['monthly'][$mKey]['total_tot'];
                        $ejAll     += $p['monthly'][$mKey]['total_ej'];
                        $shiftsAll += $p['monthly'][$mKey]['num_shifts'];
                    }
                }
                $teamMonthly[] = [
                    'month'      => $mKey,
                    'kass_pct'   => $totAll > 0 ? round($ejAll / $totAll * 100, 2) : null,
                    'num_shifts' => $shiftsAll,
                ];
            }

            // KPI
            $allEj  = array_sum(array_column($productData, 'total_ej'));
            $allTot = array_sum(array_column($productData, 'total_tot'));
            $overallKass = $allTot > 0 ? round($allEj / $allTot * 100, 2) : 0.0;

            $bestProduct  = null; $worstProduct = null;
            $mostImproved = null; $mostDegraded = null;
            foreach ($result as $p) {
                if ($bestProduct  === null || $p['avg_kass_pct'] < $bestProduct['avg_kass_pct'])  $bestProduct  = $p;
                if ($worstProduct === null || $p['avg_kass_pct'] > $worstProduct['avg_kass_pct']) $worstProduct = $p;
                if ($mostImproved === null || $p['delta_pp']    < $mostImproved['delta_pp'])       $mostImproved = $p;
                if ($mostDegraded === null || $p['delta_pp']    > $mostDegraded['delta_pp'])       $mostDegraded = $p;
            }

            echo json_encode([
                'success'      => true,
                'months'       => $months,
                'from'         => $from,
                'to'           => $to,
                'month_labels' => $allMonths,
                'products'     => $result,
                'team_monthly' => $teamMonthly,
                'kpi'          => [
                    'overall_kass_pct' => $overallKass,
                    'num_products'     => count($result),
                    'best_product'     => $bestProduct  ? ['name' => $bestProduct['name'],  'kass_pct' => $bestProduct['avg_kass_pct']]  : null,
                    'worst_product'    => $worstProduct ? ['name' => $worstProduct['name'], 'kass_pct' => $worstProduct['avg_kass_pct']] : null,
                    'most_improved'    => $mostImproved ? ['name' => $mostImproved['name'], 'delta_pp' => $mostImproved['delta_pp']]     : null,
                    'most_degraded'    => $mostDegraded ? ['name' => $mostDegraded['name'], 'delta_pp' => $mostDegraded['delta_pp']]     : null,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktKvalitetstrender: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produktkvalitetstrender'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getMilstolpar — Career milestones, personal records, progress
    // GET ?action=rebotling&run=milstolpar
    // ─────────────────────────────────────────────────────────────────────────
    private function getMilstolpar(): void {
        try {
            $pdo   = $this->pdo;
            $today = date('Y-m-d');
            $thisMonth = date('Y-m');

            // ── 1. Career totals per operator (all-time, no date filter) ──────
            // UNION ALL op1/op2/op3, dedup per (op_num, skiftraknare), then aggregate.
            $careerSql = "
                SELECT
                    src.op_num,
                    COALESCE(o.name, CONCAT('#', src.op_num)) AS op_name,
                    COALESCE(o.active, 1)                      AS op_active,
                    COUNT(DISTINCT src.skiftraknare)            AS career_shifts,
                    SUM(src.ibc_ok)                             AS career_ibc,
                    SUM(src.drifttid_h)                         AS career_hours,
                    CASE WHEN SUM(src.drifttid_h) > 0
                         THEN SUM(src.ibc_ok) / SUM(src.drifttid_h)
                         ELSE 0 END                             AS career_ibc_h,
                    MAX(src.shift_ibc_h)                        AS best_shift_ibc_h,
                    MIN(src.datum)                              AS first_shift_date,
                    MAX(src.datum)                              AS last_shift_date
                FROM (
                    SELECT op1 AS op_num, skiftraknare,
                           MIN(datum)                                         AS datum,
                           MAX(ibc_ok)                                        AS ibc_ok,
                           MAX(drifttid) / 60.0                               AS drifttid_h,
                           CASE WHEN MAX(drifttid) > 0
                                THEN MAX(ibc_ok) / (MAX(drifttid) / 60.0)
                                ELSE 0 END                                    AS shift_ibc_h
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND drifttid >= 30
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2 AS op_num, skiftraknare,
                           MIN(datum)                                         AS datum,
                           MAX(ibc_ok)                                        AS ibc_ok,
                           MAX(drifttid) / 60.0                               AS drifttid_h,
                           CASE WHEN MAX(drifttid) > 0
                                THEN MAX(ibc_ok) / (MAX(drifttid) / 60.0)
                                ELSE 0 END                                    AS shift_ibc_h
                    FROM rebotling_skiftrapport
                    WHERE op2 IS NOT NULL AND drifttid >= 30
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3 AS op_num, skiftraknare,
                           MIN(datum)                                         AS datum,
                           MAX(ibc_ok)                                        AS ibc_ok,
                           MAX(drifttid) / 60.0                               AS drifttid_h,
                           CASE WHEN MAX(drifttid) > 0
                                THEN MAX(ibc_ok) / (MAX(drifttid) / 60.0)
                                ELSE 0 END                                    AS shift_ibc_h
                    FROM rebotling_skiftrapport
                    WHERE op3 IS NOT NULL AND drifttid >= 30
                    GROUP BY op3, skiftraknare
                ) src
                LEFT JOIN operators o ON o.number = src.op_num
                GROUP BY src.op_num, o.name, o.active
                HAVING career_shifts >= 1
                ORDER BY career_ibc DESC
            ";
            $careerStmt = $pdo->prepare($careerSql);
            $careerStmt->execute();
            $careerRows = $careerStmt->fetchAll(\PDO::FETCH_ASSOC);

            // ── 2. Best month + this-month IBC per operator ───────────────────
            $monthSql = "
                SELECT
                    src.op_num,
                    DATE_FORMAT(src.datum, '%Y-%m') AS ym,
                    SUM(src.ibc_ok)                 AS month_ibc,
                    COUNT(DISTINCT src.skiftraknare) AS month_shifts
                FROM (
                    SELECT op1 AS op_num, skiftraknare, MIN(datum) AS datum, MAX(ibc_ok) AS ibc_ok
                    FROM rebotling_skiftrapport
                    WHERE op1 IS NOT NULL AND drifttid >= 30
                    GROUP BY op1, skiftraknare
                    UNION ALL
                    SELECT op2 AS op_num, skiftraknare, MIN(datum) AS datum, MAX(ibc_ok) AS ibc_ok
                    FROM rebotling_skiftrapport
                    WHERE op2 IS NOT NULL AND drifttid >= 30
                    GROUP BY op2, skiftraknare
                    UNION ALL
                    SELECT op3 AS op_num, skiftraknare, MIN(datum) AS datum, MAX(ibc_ok) AS ibc_ok
                    FROM rebotling_skiftrapport
                    WHERE op3 IS NOT NULL AND drifttid >= 30
                    GROUP BY op3, skiftraknare
                ) src
                GROUP BY src.op_num, ym
            ";
            $monthStmt = $pdo->prepare($monthSql);
            $monthStmt->execute();
            $monthRows = $monthStmt->fetchAll(\PDO::FETCH_ASSOC);

            $bestMonths    = [];
            $thisMonthData = [];
            foreach ($monthRows as $r) {
                $op  = (int)$r['op_num'];
                $ibc = (int)$r['month_ibc'];
                if (!isset($bestMonths[$op]) || $ibc > $bestMonths[$op]['ibc']) {
                    $bestMonths[$op] = [
                        'ym'     => $r['ym'],
                        'ibc'    => $ibc,
                        'shifts' => (int)$r['month_shifts'],
                    ];
                }
                if ($r['ym'] === $thisMonth) {
                    $thisMonthData[$op] = ['ibc' => $ibc, 'shifts' => (int)$r['month_shifts']];
                }
            }

            // ── 3. Milestone ladder ───────────────────────────────────────────
            $milestones = [100, 500, 1000, 2500, 5000, 10000, 25000, 50000];
            $milestoneLabels = [
                0     => 'Ny',
                100   => 'Lärling',
                500   => 'Utövare',
                1000  => 'Veteran',
                2500  => 'Mästare',
                5000  => 'Expert',
                10000 => 'Platina',
                25000 => 'Guld',
                50000 => 'Hall of Fame',
            ];

            // ── 4. Build operator array ───────────────────────────────────────
            $operators     = [];
            $totalCareerIbc = 0;

            foreach ($careerRows as $r) {
                $opNum     = (int)$r['op_num'];
                $careerIbc = (int)$r['career_ibc'];
                $totalCareerIbc += $careerIbc;

                // Current milestone and next
                $currentMs = 0;
                $nextMs    = $milestones[0];
                foreach ($milestones as $m) {
                    if ($careerIbc >= $m) {
                        $currentMs = $m;
                    } else {
                        $nextMs = $m;
                        break;
                    }
                }
                if ($careerIbc >= end($milestones)) {
                    $nextMs = null;
                }

                $label = $milestoneLabels[$currentMs] ?? 'Ny';

                // Progress toward next milestone
                if ($nextMs !== null) {
                    $span        = $nextMs - $currentMs;
                    $done        = $careerIbc - $currentMs;
                    $progressPct = $span > 0 ? round($done / $span * 100, 1) : 100.0;
                    $ibcToNext   = $nextMs - $careerIbc;
                } else {
                    $progressPct = 100.0;
                    $ibcToNext   = 0;
                }

                // Career duration
                $firstDate  = $r['first_shift_date'] ?? $today;
                $daysSince  = (strtotime($today) - strtotime($firstDate)) / 86400;
                $yearsSince = (int)floor($daysSince / 365);
                $monthsSince = (int)floor($daysSince / 30.44);

                $operators[] = [
                    'number'          => $opNum,
                    'name'            => $r['op_name'],
                    'active'          => (int)($r['op_active'] ?? 1) === 1,
                    'career_ibc'      => $careerIbc,
                    'career_shifts'   => (int)$r['career_shifts'],
                    'career_hours'    => round((float)$r['career_hours'], 1),
                    'career_ibc_h'    => round((float)$r['career_ibc_h'], 2),
                    'best_shift_ibch' => round((float)$r['best_shift_ibc_h'], 1),
                    'first_shift'     => $firstDate,
                    'last_shift'      => $r['last_shift_date'] ?? $today,
                    'years_since'     => $yearsSince,
                    'months_since'    => $monthsSince,
                    'best_month'      => $bestMonths[$opNum] ?? null,
                    'this_month'      => $thisMonthData[$opNum] ?? null,
                    'current_ms'      => $currentMs,
                    'next_ms'         => $nextMs,
                    'ms_label'        => $label,
                    'progress_pct'    => $progressPct,
                    'ibc_to_next'     => $ibcToNext,
                ];
            }

            $activeCount = count(array_filter($operators, fn($o) => $o['active']));
            $avgCareer   = count($operators) > 0
                ? (int)round($totalCareerIbc / count($operators))
                : 0;

            echo json_encode([
                'success'    => true,
                'today'      => $today,
                'this_month' => $thisMonth,
                'operators'  => $operators,
                'kpi' => [
                    'total_operators'  => count($operators),
                    'active_operators' => $activeCount,
                    'total_career_ibc' => $totalCareerIbc,
                    'avg_career_ibc'   => $avgCareer,
                ],
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getMilstolpar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid milstolpar'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ─── Produktnormaliserad Prestanda ───────────────────────────────────────
    // GET ?action=rebotling&run=produkt-normaliserad&days=90&min_shifts=3
    // Computes each operator's IBC/h normalised for product difficulty:
    //   normalized_index = actual_ibc / expected_ibc
    //   expected_ibc = sum(team_ibc_h_for_product × operator_shift_hours)
    // Index 1.0 = exactly team-average after adjusting for product mix.
    private function getProduktNormaliseradPrestanda(): void {
        try {
            $pdo       = $this->pdo;
            $days      = isset($_GET['days'])       ? max(1, (int)$_GET['days'])       : 90;
            $minShifts = isset($_GET['min_shifts']) ? max(1, (int)$_GET['min_shifts']) : 3;
            $to        = date('Y-m-d');
            $from      = date('Y-m-d', strtotime("-{$days} days"));

            // Per-product team IBC/h (dedup by skiftraknare)
            $teamSql = "
                SELECT product_id,
                       SUM(ibc)   AS team_ibc,
                       SUM(min)   AS team_min,
                       COUNT(*)   AS team_shifts
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(product_id, 0))  AS product_id,
                           MAX(COALESCE(ibc_ok, 0))      AS ibc,
                           MAX(COALESCE(drifttid, 0))    AS min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND drifttid >= 30
                      AND product_id IS NOT NULL AND product_id > 0
                    GROUP BY skiftraknare
                ) d
                GROUP BY product_id
                HAVING team_min >= 60
            ";
            $stmt = $pdo->prepare($teamSql);
            $stmt->execute([':from1' => $from, ':to1' => $to]);
            $teamByProduct = [];
            $overallTeamIbc = 0.0;
            $overallTeamMin = 0.0;
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $pid   = (int)$r['product_id'];
                $tIbc  = (float)$r['team_ibc'];
                $tMin  = (float)$r['team_min'];
                $teamByProduct[$pid] = [
                    'team_ibc_h'  => $tMin > 0 ? $tIbc / ($tMin / 60) : 0.0,
                    'team_shifts' => (int)$r['team_shifts'],
                    'team_ibc'    => $tIbc,
                    'team_min'    => $tMin,
                ];
                $overallTeamIbc += $tIbc;
                $overallTeamMin += $tMin;
            }
            $overallTeamIbcH = $overallTeamMin > 0 ? $overallTeamIbc / ($overallTeamMin / 60) : 0.0;

            // Per-operator per-product aggregates (UNION ALL op1/op2/op3, dedup per op+skiftraknare)
            $opShiftSql = "
                SELECT op_num, product_id,
                       SUM(ibc)   AS ibc,
                       SUM(min)   AS min,
                       COUNT(*)   AS skift_count
                FROM (
                    SELECT op1                                     AS op_num,
                           MAX(COALESCE(product_id, 0))            AS product_id,
                           MAX(COALESCE(ibc_ok, 0))                AS ibc,
                           MAX(COALESCE(drifttid, 0))              AS min
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op1 IS NOT NULL AND op1 > 0
                      AND product_id IS NOT NULL AND product_id > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op1

                    UNION ALL

                    SELECT op2,
                           MAX(COALESCE(product_id, 0)),
                           MAX(COALESCE(ibc_ok, 0)),
                           MAX(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op2 IS NOT NULL AND op2 > 0
                      AND product_id IS NOT NULL AND product_id > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op2

                    UNION ALL

                    SELECT op3,
                           MAX(COALESCE(product_id, 0)),
                           MAX(COALESCE(ibc_ok, 0)),
                           MAX(COALESCE(drifttid, 0))
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from4 AND :to4
                      AND op3 IS NOT NULL AND op3 > 0
                      AND product_id IS NOT NULL AND product_id > 0
                      AND drifttid >= 30
                    GROUP BY skiftraknare, op3
                ) u
                WHERE u.product_id > 0
                GROUP BY op_num, product_id
            ";
            $stmt2 = $pdo->prepare($opShiftSql);
            $stmt2->execute([
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
                ':from4' => $from, ':to4' => $to,
            ]);

            $opMap = [];
            foreach ($pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name") as $r) {
                $opMap[(int)$r['number']] = $r['name'];
            }
            $prodMap = [];
            foreach ($pdo->query("SELECT id, name FROM rebotling_products ORDER BY name") as $r) {
                $prodMap[(int)$r['id']] = $r['name'];
            }

            // Accumulate per-operator totals
            $opData = [];
            foreach ($stmt2->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNum  = (int)$r['op_num'];
                $pid    = (int)$r['product_id'];
                $ibc    = (float)$r['ibc'];
                $min    = (float)$r['min'];
                $nShift = (int)$r['skift_count'];
                if (!isset($opMap[$opNum]))          continue;
                if (!isset($teamByProduct[$pid]))     continue;
                $teamH = $teamByProduct[$pid]['team_ibc_h'];
                if ($teamH <= 0 || $min <= 0)        continue;

                $hours       = $min / 60.0;
                $opIbcH      = $ibc / $hours;
                $expectedIbc = $teamH * $hours;

                if (!isset($opData[$opNum])) {
                    $opData[$opNum] = ['raw_ibc' => 0.0, 'raw_min' => 0.0, 'expected_ibc' => 0.0, 'skift_count' => 0, 'products' => []];
                }
                $opData[$opNum]['raw_ibc']      += $ibc;
                $opData[$opNum]['raw_min']       += $min;
                $opData[$opNum]['expected_ibc']  += $expectedIbc;
                $opData[$opNum]['skift_count']   += $nShift;
                $opData[$opNum]['products'][]     = [
                    'product_id'   => $pid,
                    'product_name' => $prodMap[$pid] ?? "Produkt $pid",
                    'op_ibc_h'     => round($opIbcH, 2),
                    'team_ibc_h'   => round($teamH, 2),
                    'relative_eff' => $teamH > 0 ? round($opIbcH / $teamH, 3) : 0,
                    'skift_count'  => $nShift,
                    'total_ibc'    => (int)$ibc,
                    'hours'        => round($hours, 1),
                ];
            }

            // Build result array
            $results = [];
            foreach ($opData as $opNum => $d) {
                if ($d['skift_count'] < $minShifts) continue;
                $rawH    = $d['raw_min'] > 0 ? $d['raw_ibc'] / ($d['raw_min'] / 60) : 0.0;
                $normIdx = $d['expected_ibc'] > 0 ? $d['raw_ibc'] / $d['expected_ibc'] : 0.0;
                $vsRaw   = $overallTeamIbcH > 0 ? ($rawH / $overallTeamIbcH - 1) * 100 : 0.0;
                usort($d['products'], fn($a, $b) => $b['skift_count'] - $a['skift_count']);
                $results[] = [
                    'op_num'           => $opNum,
                    'name'             => $opMap[$opNum],
                    'raw_ibc_h'        => round($rawH, 2),
                    'normalized_index' => round($normIdx, 3),
                    'vs_team_raw'      => round($vsRaw, 1),
                    'skift_count'      => $d['skift_count'],
                    'products'         => $d['products'],
                ];
            }

            // Raw ranking (by raw_ibc_h desc)
            usort($results, fn($a, $b) => $b['raw_ibc_h'] <=> $a['raw_ibc_h']);
            $rank = 1;
            foreach ($results as &$r) { $r['raw_rank'] = $rank++; }
            unset($r);

            // Normalized ranking (by normalized_index desc)
            usort($results, fn($a, $b) => $b['normalized_index'] <=> $a['normalized_index']);
            $rank = 1;
            foreach ($results as &$r) {
                $r['norm_rank']   = $rank++;
                $r['rank_delta']  = $r['raw_rank'] - $r['norm_rank']; // >0 = rose, <0 = dropped
            }
            unset($r);

            echo json_encode([
                'success'      => true,
                'from'         => $from,
                'to'           => $to,
                'days'         => $days,
                'min_shifts'   => $minShifts,
                'team_ibc_h'   => round($overallTeamIbcH, 2),
                'operators'    => $results,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getProduktNormaliseradPrestanda: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid produkt-normaliserad'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=ars-kurva
    private function getArsKurva(): void {
        try {
            // Dedup shifts then aggregate per year+dayofyear
            $stmt = $this->pdo->prepare("
                SELECT
                    YEAR(datum)          AS ar,
                    DAYOFYEAR(datum)     AS dag,
                    datum                AS datum,
                    SUM(ibc_ok)          AS ibc_ok,
                    SUM(drifttid)        AS drifttid
                FROM (
                    SELECT skiftraknare, MAX(datum) AS datum, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE drifttid >= 30
                    GROUP BY skiftraknare
                ) AS dedup
                GROUP BY YEAR(datum), DAYOFYEAR(datum), datum
                ORDER BY ar, dag
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build per-year daily series with cumulative IBC
            $byYear = [];
            foreach ($rows as $r) {
                $ar  = (int)$r['ar'];
                $dag = (int)$r['dag'];
                if (!isset($byYear[$ar])) {
                    $byYear[$ar] = [];
                }
                $byYear[$ar][$dag] = [
                    'dag'      => $dag,
                    'datum'    => $r['datum'],
                    'ibc_ok'   => (int)$r['ibc_ok'],
                    'drifttid' => (int)$r['drifttid'],
                ];
            }

            $years = array_keys($byYear);
            sort($years);

            // Build cumulative series per year
            $seriesData = [];
            $yearSummary = [];

            foreach ($years as $ar) {
                $days = $byYear[$ar];
                ksort($days);

                $cumIbc    = 0;
                $cumDrift  = 0;
                $totalIbc  = 0;
                $totalDrift = 0;
                $skiftAntal = count($days);
                $series     = [];

                foreach ($days as $dag => $d) {
                    $cumIbc   += $d['ibc_ok'];
                    $cumDrift += $d['drifttid'];
                    $totalIbc  = $cumIbc;
                    $totalDrift = $cumDrift;
                    $series[] = [
                        'dag'         => $dag,
                        'datum'       => $d['datum'],
                        'cum_ibc'     => $cumIbc,
                        'daily_ibc'   => $d['ibc_ok'],
                        'daily_ibch'  => $d['drifttid'] > 0
                            ? round($d['ibc_ok'] / ($d['drifttid'] / 60.0), 2)
                            : 0.0,
                    ];
                }

                $ibcH = $totalDrift > 0 ? round($totalIbc / ($totalDrift / 60.0), 2) : 0.0;
                $seriesData[$ar] = $series;
                $yearSummary[$ar] = [
                    'ar'          => $ar,
                    'total_ibc'   => $totalIbc,
                    'ibc_h'       => $ibcH,
                    'antal_dagar' => $skiftAntal,
                ];
            }

            // Year-over-year delta
            $prevIbc = null;
            foreach ($years as $ar) {
                if ($prevIbc !== null && $prevIbc > 0) {
                    $yearSummary[$ar]['delta_pct'] = round(
                        ($yearSummary[$ar]['total_ibc'] - $prevIbc) / $prevIbc * 100, 1
                    );
                } else {
                    $yearSummary[$ar]['delta_pct'] = null;
                }
                $prevIbc = $yearSummary[$ar]['total_ibc'];
            }

            // "At same day" comparison: for each prior year, find cumulative IBC at same day-of-year as latest data point
            $curYear = max($years);
            $curSeries = $seriesData[$curYear] ?? [];
            $curLastDag = count($curSeries) > 0 ? end($curSeries)['dag'] : 0;

            $atSameDay = [];
            foreach ($years as $ar) {
                if ($ar === $curYear) continue;
                $s = $seriesData[$ar] ?? [];
                // Find entry with dag <= curLastDag
                $found = null;
                foreach (array_reverse($s) as $pt) {
                    if ($pt['dag'] <= $curLastDag) { $found = $pt; break; }
                }
                $atSameDay[$ar] = $found ? $found['cum_ibc'] : null;
            }

            $curAtSameDay = count($curSeries) > 0 ? end($curSeries)['cum_ibc'] : 0;

            // All-time total IBC
            $allTimeIbc = array_sum(array_column(array_values($yearSummary), 'total_ibc'));

            echo json_encode([
                'success'      => true,
                'years'        => $years,
                'cur_year'     => $curYear,
                'cur_last_dag' => $curLastDag,
                'series'       => $seriesData,
                'year_summary' => array_values($yearSummary),
                'at_same_day'  => $atSameDay,
                'cur_at_same_day' => $curAtSameDay,
                'all_time_ibc' => $allTimeIbc,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getArsKurva: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid ars-kurva'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=schema-rekommendationer[&days=90]
    // Returns per-operator scheduling recommendations: best position, best shift type, tier, trend, recommendation text.
    // Three focused queries (position perf / shift-type perf / overall 30d+90d trend) merged in PHP.
    private function getSchemaRekommendationer(): void {
        try {
            $days   = max(30, min(365, (int)($_GET['days'] ?? 90)));
            $to     = date('Y-m-d');
            $from   = date('Y-m-d', strtotime("-{$days} days"));
            $from30 = date('Y-m-d', strtotime('-30 days'));

            $posNames  = ['op1' => 'Tvättplats', 'op2' => 'Kontroll', 'op3' => 'Truck'];
            $typeNames = ['dag' => 'Dagskift', 'kvall' => 'Kvällsskift', 'natt' => 'Nattskift'];

            // --- Query 1: per-operator per-position IBC/h (SUM/SUM, skiftraknare-dedup) ---
            $stmt1 = $this->pdo->prepare("
                SELECT
                    u.op_num, u.pos,
                    COALESCE(o.name, CONCAT('Op ', u.op_num)) AS op_name,
                    COUNT(*)       AS antal_skift,
                    SUM(u.ibc)     AS total_ibc,
                    SUM(u.hours)   AS total_hours
                FROM (
                    SELECT op1 AS op_num, 'op1' AS pos, skiftraknare,
                           MAX(ibc_ok) AS ibc, MAX(drifttid)/60.0 AS hours
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                      AND op1 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare

                    UNION ALL

                    SELECT op2, 'op2', skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)/60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from2 AND :to2
                      AND op2 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare

                    UNION ALL

                    SELECT op3, 'op3', skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)/60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from3 AND :to3
                      AND op3 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare
                ) u
                LEFT JOIN operators o ON o.number = u.op_num
                GROUP BY u.op_num, u.pos, op_name
                ORDER BY u.op_num, u.pos
            ");
            $stmt1->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);

            $posByOp = [];
            foreach ($stmt1->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNum = (int)$r['op_num'];
                if (!isset($posByOp[$opNum])) {
                    $posByOp[$opNum] = ['name' => $r['op_name'], 'positions' => []];
                }
                $h   = (float)$r['total_hours'];
                $ibc = (float)$r['total_ibc'];
                $posByOp[$opNum]['positions'][$r['pos']] = [
                    'antal_skift' => (int)$r['antal_skift'],
                    'ibc_h'       => $h > 0 ? round($ibc / $h, 2) : 0.0,
                ];
            }

            // --- Query 2: per-operator per-shift-type IBC/h (SUM/SUM, skiftraknare-dedup) ---
            $stmt2 = $this->pdo->prepare("
                SELECT op_num, skift_typ,
                       COUNT(*) AS antal_skift,
                       SUM(ibc) AS total_ibc, SUM(hours) AS total_hours
                FROM (
                    SELECT op1 AS op_num, skiftraknare,
                           CASE WHEN HOUR(MIN(created_at)) >=  6 AND HOUR(MIN(created_at)) < 14 THEN 'dag'
                                WHEN HOUR(MIN(created_at)) >= 14 AND HOUR(MIN(created_at)) < 22 THEN 'kvall'
                                ELSE 'natt' END AS skift_typ,
                           MAX(ibc_ok) AS ibc, MAX(drifttid)/60.0 AS hours
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from4 AND :to4
                      AND op1 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare

                    UNION ALL

                    SELECT op2, skiftraknare,
                           CASE WHEN HOUR(MIN(created_at)) >=  6 AND HOUR(MIN(created_at)) < 14 THEN 'dag'
                                WHEN HOUR(MIN(created_at)) >= 14 AND HOUR(MIN(created_at)) < 22 THEN 'kvall'
                                ELSE 'natt' END,
                           MAX(ibc_ok), MAX(drifttid)/60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from5 AND :to5
                      AND op2 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare

                    UNION ALL

                    SELECT op3, skiftraknare,
                           CASE WHEN HOUR(MIN(created_at)) >=  6 AND HOUR(MIN(created_at)) < 14 THEN 'dag'
                                WHEN HOUR(MIN(created_at)) >= 14 AND HOUR(MIN(created_at)) < 22 THEN 'kvall'
                                ELSE 'natt' END,
                           MAX(ibc_ok), MAX(drifttid)/60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from6 AND :to6
                      AND op3 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare
                ) u
                WHERE op_num IS NOT NULL
                GROUP BY op_num, skift_typ
                ORDER BY op_num, skift_typ
            ");
            $stmt2->execute([
                ':from4' => $from, ':to4' => $to,
                ':from5' => $from, ':to5' => $to,
                ':from6' => $from, ':to6' => $to,
            ]);

            $typByOp = [];
            foreach ($stmt2->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNum = (int)$r['op_num'];
                if (!isset($typByOp[$opNum])) $typByOp[$opNum] = [];
                $h   = (float)$r['total_hours'];
                $ibc = (float)$r['total_ibc'];
                $typByOp[$opNum][$r['skift_typ']] = [
                    'antal_skift' => (int)$r['antal_skift'],
                    'ibc_h'       => $h > 0 ? round($ibc / $h, 2) : 0.0,
                ];
            }

            // --- Query 3: per-operator overall + 30d-window trend (single UNION ALL, SUM/SUM) ---
            $stmt3 = $this->pdo->prepare("
                SELECT op_num,
                       SUM(ibc)                                                AS total_ibc,
                       SUM(hours)                                              AS total_hours,
                       COUNT(*)                                                AS total_skift,
                       SUM(CASE WHEN datum >= :from30a THEN ibc   ELSE 0 END) AS ibc_30d,
                       SUM(CASE WHEN datum >= :from30b THEN hours ELSE 0 END) AS hours_30d,
                       SUM(CASE WHEN datum <  :from30c THEN ibc   ELSE 0 END) AS ibc_base,
                       SUM(CASE WHEN datum <  :from30d THEN hours ELSE 0 END) AS hours_base
                FROM (
                    SELECT op1 AS op_num, datum, skiftraknare,
                           MAX(ibc_ok) AS ibc, MAX(drifttid)/60.0 AS hours
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from7 AND :to7
                      AND op1 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op1, skiftraknare, datum

                    UNION ALL

                    SELECT op2, datum, skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)/60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from8 AND :to8
                      AND op2 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op2, skiftraknare, datum

                    UNION ALL

                    SELECT op3, datum, skiftraknare,
                           MAX(ibc_ok), MAX(drifttid)/60.0
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from9 AND :to9
                      AND op3 > 0 AND drifttid >= 30 AND ibc_ok > 0
                    GROUP BY op3, skiftraknare, datum
                ) u
                GROUP BY op_num
            ");
            $stmt3->execute([
                ':from30a' => $from30, ':from30b' => $from30,
                ':from30c' => $from30, ':from30d' => $from30,
                ':from7'   => $from,   ':to7'     => $to,
                ':from8'   => $from,   ':to8'     => $to,
                ':from9'   => $from,   ':to9'     => $to,
            ]);

            $overallByOp   = [];
            $teamTotalIbc  = 0.0;
            $teamTotalHours = 0.0;
            foreach ($stmt3->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNum   = (int)$r['op_num'];
                $h       = (float)$r['total_hours'];
                $ibc     = (float)$r['total_ibc'];
                $h30     = (float)$r['hours_30d'];
                $i30     = (float)$r['ibc_30d'];
                $hBase   = (float)$r['hours_base'];
                $iBase   = (float)$r['ibc_base'];
                $overallByOp[$opNum] = [
                    'ibc_h'       => $h    > 0 ? round($ibc   / $h,    2) : 0.0,
                    'ibc_h_30d'   => $h30  > 0 ? round($i30   / $h30,  2) : 0.0,
                    'ibc_h_base'  => $hBase > 0 ? round($iBase / $hBase, 2) : 0.0,
                    'total_skift' => (int)$r['total_skift'],
                    'hours_30d'   => $h30,
                ];
                $teamTotalIbc   += $ibc;
                $teamTotalHours += $h;
            }

            $teamAvgIbch = $teamTotalHours > 0 ? round($teamTotalIbc / $teamTotalHours, 2) : 0.0;

            // --- Build recommendations ---
            $operators = [];
            $allOpNums = array_unique(array_merge(array_keys($posByOp), array_keys($overallByOp)));

            foreach ($allOpNums as $opNum) {
                if (!isset($overallByOp[$opNum])) continue;
                $ov   = $overallByOp[$opNum];
                $name = $posByOp[$opNum]['name'] ?? "Op {$opNum}";
                if ($ov['total_skift'] < 3) continue;

                $ibch     = $ov['ibc_h'];
                $ibch30   = $ov['ibc_h_30d'];
                $ibchBase = $ov['ibc_h_base'];

                // Tier vs team
                $vsTeam = $teamAvgIbch > 0 ? round(($ibch / $teamAvgIbch - 1) * 100, 1) : 0.0;
                if ($vsTeam >= 15)       $tier = 'elite';
                elseif ($vsTeam >= 0)    $tier = 'solid';
                elseif ($vsTeam >= -15)  $tier = 'developing';
                else                     $tier = 'needs_support';

                // Trend: compare last 30d IBC/h vs earlier baseline
                $trend = 'stable';
                if ($ov['hours_30d'] > 0.5 && $ibchBase > 0) {
                    $trendPct = ($ibch30 - $ibchBase) / $ibchBase * 100;
                    if ($trendPct >= 8)       $trend = 'rising';
                    elseif ($trendPct <= -8)  $trend = 'falling';
                }

                // Best position (≥2 shifts required)
                $bestPos     = null;
                $bestPosIbch = 0.0;
                foreach (($posByOp[$opNum]['positions'] ?? []) as $pos => $pd) {
                    if ($pd['antal_skift'] >= 2 && $pd['ibc_h'] > $bestPosIbch) {
                        $bestPosIbch = $pd['ibc_h'];
                        $bestPos     = $pos;
                    }
                }

                // Best shift type (≥2 shifts required)
                $bestType     = null;
                $bestTypeIbch = 0.0;
                foreach (($typByOp[$opNum] ?? []) as $typ => $td) {
                    if ($td['antal_skift'] >= 2 && $td['ibc_h'] > $bestTypeIbch) {
                        $bestTypeIbch = $td['ibc_h'];
                        $bestType     = $typ;
                    }
                }

                $rec = $this->buildSchemaRec($tier, $trend, $bestPos, $bestType, $vsTeam, $posNames, $typeNames);

                // Enrich position and shift-type details (all 3 slots, null if no data)
                $posDetails  = [];
                foreach (['op1', 'op2', 'op3'] as $p) {
                    $posDetails[$p] = isset($posByOp[$opNum]['positions'][$p])
                        ? $posByOp[$opNum]['positions'][$p]
                        : null;
                }
                $typeDetails = [];
                foreach (['dag', 'kvall', 'natt'] as $t) {
                    $typeDetails[$t] = isset($typByOp[$opNum][$t]) ? $typByOp[$opNum][$t] : null;
                }

                $operators[] = [
                    'number'               => $opNum,
                    'name'                 => $name,
                    'ibc_h'                => $ibch,
                    'ibc_h_30d'            => $ibch30,
                    'vs_team'              => $vsTeam,
                    'tier'                 => $tier,
                    'trend'                => $trend,
                    'best_position'        => $bestPos,
                    'best_position_name'   => $bestPos ? $posNames[$bestPos] : null,
                    'best_position_ibc_h'  => $bestPos ? round($bestPosIbch, 2) : null,
                    'best_shift_type'      => $bestType,
                    'best_shift_type_name' => $bestType ? $typeNames[$bestType] : null,
                    'best_shift_type_ibc_h'=> $bestType ? round($bestTypeIbch, 2) : null,
                    'total_skift'          => $ov['total_skift'],
                    'recommendation'       => $rec,
                    'positions'            => $posDetails,
                    'shift_types'          => $typeDetails,
                ];
            }

            // Sort: Elite first, then by vs_team desc
            $tierOrder = ['elite' => 0, 'solid' => 1, 'developing' => 2, 'needs_support' => 3];
            usort($operators, function ($a, $b) use ($tierOrder) {
                $ta = $tierOrder[$a['tier']] ?? 4;
                $tb = $tierOrder[$b['tier']] ?? 4;
                if ($ta !== $tb) return $ta - $tb;
                return $b['vs_team'] <=> $a['vs_team'];
            });

            echo json_encode([
                'success'        => true,
                'from'           => $from,
                'to'             => $to,
                'days'           => $days,
                'team_avg_ibc_h' => $teamAvgIbch,
                'operators'      => $operators,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getSchemaRekommendationer: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid schemaläggningsassistenten'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // Builds a Swedish recommendation string based on tier, trend, best position and best shift type.
    private function buildSchemaRec(
        string $tier, string $trend, ?string $bestPos, ?string $bestType,
        float $vsTeam, array $posNames, array $typeNames
    ): string {
        $posStr  = $bestPos  ? $posNames[$bestPos]   : null;
        $typStr  = $bestType ? $typeNames[$bestType]  : null;

        if ($tier === 'needs_support') {
            if ($trend === 'falling') return 'Behöver akut stöd — prestandan sjunker och är under lagsnitt. Prioritera aktiv coaching och stöttande placering.';
            if ($trend === 'rising')  return 'Prestanda förbättras från låg nivå — ge positiv förstärkning. Under lagsnitt men på rätt väg.';
            return 'Under lagsnitt — sätt upp individuella mål, boka uppföljning och schemalägg på stöttande position.';
        }

        if ($tier === 'elite') {
            $parts = [];
            if ($posStr)  $parts[] = "Starkast på {$posStr}";
            if ($typStr)  $parts[] = "bäst lämpad för {$typStr}";
            $base = $parts ? implode(', ', $parts) . '.' : 'Toppprestanda.';
            if ($trend === 'rising')  return "{$base} Prestandan stiger — nyckelspelare, fortsätt nyttja styrkan fullt ut.";
            if ($trend === 'falling') return "{$base} OBS: nedgång senaste 30 dagarna — checka in om allt är ok.";
            return "{$base} Nyckelspelare — prioritera placering på bästa position och skifttyp.";
        }

        if ($tier === 'solid') {
            $parts = [];
            if ($posStr)  $parts[] = "Bra på {$posStr}";
            if ($typStr)  $parts[] = "föredrar {$typStr}";
            $base = $parts ? implode(', ', $parts) . '.' : 'Pålitlig prestanda.';
            if ($trend === 'rising')  return "{$base} Förbättras stadigt — kan nå Elite-nivå. Ge mer ansvar och variera positioner.";
            if ($trend === 'falling') return "{$base} Håll koll — prestandan kan sjunka. Kontrollera schemaläggning och välmående.";
            return "{$base} Stabil och pålitlig — håll nuvarande schemaläggning och nyttja styrkeposition.";
        }

        // developing
        $parts = [];
        if ($posStr)  $parts[] = "Bäst på {$posStr}";
        if ($typStr)  $parts[] = "gynnas av {$typStr}";
        $base = $parts ? implode(', ', $parts) . '.' : 'Under genomsnitt men med potential.';
        if ($trend === 'rising')  return "{$base} Prestanda ökar — fortsätt med rotationsstöd och coaching. På rätt väg.";
        if ($trend === 'falling') return "{$base} Behöver stöd — boka uppföljning. Placera på stärkande position.";
        return "{$base} Fokusera schemaläggning på starkaste position och skifttyp för att bygga självförtroende.";
    }

    // GET ?action=rebotling&run=kassationsorsak-per-produkt&days=90
    private function getKassationsorsakPerProdukt(): void {
        try {
            $pdo  = $this->pdo;
            $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 90;
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$days} days"));

            // Graceful fallback if kassationsregistrering table doesn't exist
            $check = $pdo->query("SHOW TABLES LIKE 'kassationsregistrering'");
            if (!$check->fetch()) {
                echo json_encode([
                    'success'      => true,
                    'products'     => [],
                    'all_causes'   => [],
                    'total_events' => 0,
                    'from'         => $from,
                    'to'           => $to,
                ], \JSON_UNESCAPED_UNICODE);
                return;
            }

            // Active cause names
            $causeStmt = $pdo->query("SELECT id, namn FROM kassationsorsak_typer WHERE aktiv = 1 ORDER BY namn");
            $causeMap  = [];
            foreach ($causeStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $causeMap[(int)$r['id']] = $r['namn'];
            }

            // Join kassationsregistrering → deduplicated shift → product
            // Use MAX(product_id) per skiftraknare to collapse multiple rows per shift.
            $sql = "
                SELECT
                    s.product_id,
                    COALESCE(p.name, CONCAT('Produkt #', s.product_id)) AS product_name,
                    kr.orsak_id,
                    SUM(kr.antal) AS antal
                FROM kassationsregistrering kr
                JOIN (
                    SELECT skiftraknare, MAX(product_id) AS product_id
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from1 AND :to1
                    GROUP BY skiftraknare
                ) s ON s.skiftraknare = kr.skiftraknare
                LEFT JOIN rebotling_products p ON p.id = s.product_id
                WHERE kr.datum BETWEEN :from2 AND :to2
                GROUP BY s.product_id, p.name, kr.orsak_id
                ORDER BY s.product_id, antal DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build per-product aggregation
            $prodData    = [];
            $totalEvents = 0;

            foreach ($rows as $r) {
                $pid   = (int)$r['product_id'];
                $pname = $r['product_name'];
                if (!isset($prodData[$pid])) {
                    $prodData[$pid] = [
                        'product_id'      => $pid,
                        'name'            => $pname,
                        'total_kassation' => 0,
                        'top_cause'       => '',
                        'causes'          => [],
                    ];
                }
                $antal   = (int)$r['antal'];
                $orsakId = (int)$r['orsak_id'];
                $prodData[$pid]['total_kassation'] += $antal;
                $prodData[$pid]['causes'][]         = [
                    'orsak_id' => $orsakId,
                    'namn'     => $causeMap[$orsakId] ?? 'Okänd',
                    'antal'    => $antal,
                    'pct'      => 0.0,
                ];
            }

            // Compute percentages and top_cause
            foreach ($prodData as &$prod) {
                $tot = $prod['total_kassation'];
                foreach ($prod['causes'] as &$c) {
                    $c['pct'] = $tot > 0 ? round($c['antal'] / $tot * 100, 1) : 0.0;
                }
                unset($c);
                $prod['top_cause'] = !empty($prod['causes']) ? $prod['causes'][0]['namn'] : '';
                $totalEvents += $tot;
            }
            unset($prod);

            // Sort products by total kassation descending
            usort($prodData, static fn($a, $b) => $b['total_kassation'] - $a['total_kassation']);

            // All active causes for legend/matrix
            $allCauses = [];
            foreach ($causeMap as $id => $namn) {
                $allCauses[] = ['id' => $id, 'namn' => $namn];
            }

            echo json_encode([
                'success'      => true,
                'products'     => array_values($prodData),
                'all_causes'   => $allCauses,
                'total_events' => $totalEvents,
                'from'         => $from,
                'to'           => $to,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKassationsorsakPerProdukt: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Serverfel vid kassationsorsak-per-produkt'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // GET ?action=rebotling&run=korstraning&days=180&min_skift=3
    private function getKorstraningsplan(): void {
        try {
            $days     = max(30, min(730, (int)($_GET['days']      ?? 180)));
            $minSkift = max(1,  min(20,  (int)($_GET['min_skift'] ?? 3)));
            $to       = date('Y-m-d');
            $from     = date('Y-m-d', strtotime("-{$days} days"));

            $stmtOps = $this->pdo->query("SELECT number, name FROM operators WHERE active=1 ORDER BY name");
            $opNames = [];
            foreach ($stmtOps->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $opNames[(int)$r['number']] = $r['name'];
            }

            // ibc_ok is a PLC daily running counter (does not reset per shift).
            // LAG() computes the per-shift delta so later shifts are not over-credited.
            $stmt = $this->pdo->prepare("
                SELECT skiftraknare, op1, op2, op3, drifttid,
                       GREATEST(0, ibc_end - COALESCE(LAG(ibc_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS ibc_ok
                FROM (
                    SELECT skiftraknare,
                           DATE(MAX(datum)) AS dag,
                           MAX(op1)         AS op1,
                           MAX(op2)         AS op2,
                           MAX(op3)         AS op3,
                           MAX(ibc_ok)      AS ibc_end,
                           MAX(drifttid)    AS drifttid
                    FROM rebotling_skiftrapport
                    WHERE datum BETWEEN :from AND :to AND drifttid >= 30
                    GROUP BY skiftraknare
                ) s
            ");
            $stmt->execute([':from' => $from, ':to' => $to]);
            $shifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $opTotals    = [];
            $opPosTotals = [];
            $teamPosTotals = ['op1'=>['ibc'=>0,'min'=>0],'op2'=>['ibc'=>0,'min'=>0],'op3'=>['ibc'=>0,'min'=>0]];
            $teamTotalIbc = 0;
            $teamTotalMin = 0;

            foreach ($shifts as $s) {
                $ibc = max(0, (int)$s['ibc_ok']);
                $min = max(0, (int)$s['drifttid']);
                if ($min === 0) continue;
                $teamTotalIbc += $ibc;
                $teamTotalMin += $min;
                foreach (['op1','op2','op3'] as $pos) {
                    $num = (int)$s[$pos];
                    if ($num <= 0) continue;
                    $opTotals[$num]['ibc']   = ($opTotals[$num]['ibc']   ?? 0) + $ibc;
                    $opTotals[$num]['min']   = ($opTotals[$num]['min']   ?? 0) + $min;
                    $opTotals[$num]['count'] = ($opTotals[$num]['count'] ?? 0) + 1;
                    $opPosTotals[$num][$pos]['ibc']   = ($opPosTotals[$num][$pos]['ibc']   ?? 0) + $ibc;
                    $opPosTotals[$num][$pos]['min']   = ($opPosTotals[$num][$pos]['min']   ?? 0) + $min;
                    $opPosTotals[$num][$pos]['count'] = ($opPosTotals[$num][$pos]['count'] ?? 0) + 1;
                    $teamPosTotals[$pos]['ibc'] += $ibc;
                    $teamPosTotals[$pos]['min'] += $min;
                }
            }

            $teamAvgPerPos = [];
            foreach ($teamPosTotals as $pos => $t) {
                $teamAvgPerPos[$pos] = $t['min'] > 0 ? round($t['ibc'] / ($t['min'] / 60.0), 1) : 0;
            }
            $teamTotal = $teamTotalMin > 0 ? round($teamTotalIbc / ($teamTotalMin / 60.0), 1) : 0;

            // Count operators qualified (≥ minSkift) per position
            $qualifiedPerPos = ['op1'=>0,'op2'=>0,'op3'=>0];
            foreach ($opNames as $num => $name) {
                if (!isset($opTotals[$num]) || $opTotals[$num]['count'] < 3) continue;
                foreach (['op1','op2','op3'] as $pos) {
                    if (($opPosTotals[$num][$pos]['count'] ?? 0) >= $minSkift) {
                        $qualifiedPerPos[$pos]++;
                    }
                }
            }

            $posLabels = ['op1'=>'Tvättplats','op2'=>'Kontrollstation','op3'=>'Truckförare'];

            // Build training recommendations
            $recommendations = [];
            foreach ($opNames as $num => $name) {
                if (!isset($opTotals[$num]) || $opTotals[$num]['count'] < 3) continue;
                $tot         = $opTotals[$num];
                $overallIbcH = $tot['min'] > 0 ? round($tot['ibc'] / ($tot['min'] / 60.0), 1) : 0;
                $overallVs   = $teamTotal > 0 ? round(($overallIbcH / $teamTotal - 1) * 100) : 0;

                foreach (['op1','op2','op3'] as $pos) {
                    $posShifts = $opPosTotals[$num][$pos]['count'] ?? 0;
                    $posMin    = $opPosTotals[$num][$pos]['min']   ?? 0;
                    $posIbc    = $opPosTotals[$num][$pos]['ibc']   ?? 0;
                    $posIbcH   = $posMin > 0 ? round($posIbc / ($posMin / 60.0), 1) : 0;
                    $teamPos   = $teamAvgPerPos[$pos] ?? 0;

                    $isUntrained = $posShifts < $minSkift;
                    $isWeak      = !$isUntrained && $teamPos > 0 && $posIbcH < $teamPos * 0.82;

                    if (!$isUntrained && !$isWeak) continue;

                    // Team urgency: fewer qualified operators = higher urgency (0–5)
                    $otherQ       = $qualifiedPerPos[$pos] - ($isUntrained ? 0 : 1);
                    $teamUrgency  = max(0, min(5, 5 - $otherQ));
                    // Operator potential based on overall vs team (-5 to +5 range mapped 0–5)
                    $opPotential  = min(5, max(0, round($overallVs / 10 + 2.5)));
                    $prioScore    = $teamUrgency * 2 + $opPotential;
                    $prio         = $prioScore >= 7 ? 'Hög' : ($prioScore >= 4 ? 'Medel' : 'Låg');
                    // Projected IBC/h after training ≈ 85% of overall avg (conservative estimate)
                    $projectedIbcH = round($overallIbcH * 0.85, 1);
                    $ibcHGap       = round($projectedIbcH - $teamPos, 1);

                    $recommendations[] = [
                        'op_num'          => $num,
                        'op_name'         => $name,
                        'position'        => $pos,
                        'pos_label'       => $posLabels[$pos],
                        'type'            => $isUntrained ? 'untrained' : 'weak',
                        'pos_shifts'      => $posShifts,
                        'pos_ibch'        => $posIbcH,
                        'team_pos_avg'    => $teamPos,
                        'overall_ibch'    => $overallIbcH,
                        'overall_vs_team' => $overallVs,
                        'projected_ibch'  => $projectedIbcH,
                        'ibc_gap'         => $ibcHGap,
                        'priority_score'  => $prioScore,
                        'priority'        => $prio,
                        'other_qualified' => $otherQ,
                    ];
                }
            }

            usort($recommendations, function($a, $b) {
                if ($b['priority_score'] !== $a['priority_score']) return $b['priority_score'] <=> $a['priority_score'];
                if ($a['type'] !== $b['type']) return strcmp($a['type'], $b['type']); // untrained first
                return strcmp($a['op_name'], $b['op_name']);
            });

            // Per-operator coverage summary
            $operatorSummaries = [];
            foreach ($opNames as $num => $name) {
                if (!isset($opTotals[$num]) || $opTotals[$num]['count'] < 3) continue;
                $coveredPos = 0;
                $posData    = [];
                foreach (['op1','op2','op3'] as $pos) {
                    $ps  = $opPosTotals[$num][$pos]['count'] ?? 0;
                    $pm  = $opPosTotals[$num][$pos]['min']   ?? 0;
                    $pi  = $opPosTotals[$num][$pos]['ibc']   ?? 0;
                    $ph  = $pm > 0 ? round($pi / ($pm / 60.0), 1) : 0;
                    $tpa = $teamAvgPerPos[$pos] ?? 0;
                    $qual = $ps >= $minSkift;
                    if ($qual) $coveredPos++;
                    $posData[$pos] = [
                        'shifts'    => $ps,
                        'qualified' => $qual,
                        'ibch'      => $ph,
                        'vs_avg'    => $tpa > 0 ? round(($ph / $tpa - 1) * 100) : null,
                    ];
                }
                $tot         = $opTotals[$num];
                $overallIbcH = $tot['min'] > 0 ? round($tot['ibc'] / ($tot['min'] / 60.0), 1) : 0;
                $operatorSummaries[] = [
                    'number'           => $num,
                    'name'             => $name,
                    'total_skift'      => $tot['count'],
                    'overall_ibch'     => $overallIbcH,
                    'overall_vs_team'  => $teamTotal > 0 ? round(($overallIbcH / $teamTotal - 1) * 100) : 0,
                    'covered_positions'=> $coveredPos,
                    'positions'        => $posData,
                ];
            }
            usort($operatorSummaries, fn($a,$b) => $b['covered_positions'] <=> $a['covered_positions'] ?: strcmp($a['name'],$b['name']));

            echo json_encode([
                'success'           => true,
                'from'              => $from,
                'to'                => $to,
                'days'              => $days,
                'min_skift'         => $minSkift,
                'team_avg'          => $teamTotal,
                'team_avg_per_pos'  => $teamAvgPerPos,
                'qualified_per_pos' => $qualifiedPerPos,
                'recommendations'   => $recommendations,
                'operator_summaries'=> $operatorSummaries,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getKorstraningsplan: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid korsträningsplan'], \JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // GET ?action=rebotling&run=idag[&datum=YYYY-MM-DD]
    // Daglig produktionsöversikt — today's shifts with KPIs + delta vs yesterday + 30d avg
    // ================================================================
    private function getIdag(): void {
        $datum = trim($_GET['datum'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || strtotime($datum) === false) {
            $datum = date('Y-m-d');
        }
        $yesterday = date('Y-m-d', strtotime($datum . ' -1 day'));
        $from30    = date('Y-m-d', strtotime($datum . ' -31 days'));
        $to30      = date('Y-m-d', strtotime($datum . ' -1 day'));

        try {
            // ----- 1. Today's shifts (dedup by skiftraknare) -----
            // ibc_ok, ibc_ej_ok, bur_ej_ok, totalt are PLC daily running counters
            // (do not reset between shifts). LAG() computes per-shift deltas.
            // drifttid/driftstopptime reset per shift — MAX suffices.
            $stmt = $this->pdo->prepare("
                SELECT
                    s.skiftraknare,
                    s.datum,
                    GREATEST(0, s.ibc_end - COALESCE(LAG(s.ibc_end) OVER (ORDER BY s.skiftraknare), 0)) AS ibc_ok,
                    GREATEST(0, s.ej_end  - COALESCE(LAG(s.ej_end)  OVER (ORDER BY s.skiftraknare), 0)) AS ibc_ej_ok,
                    GREATEST(0, s.bur_end - COALESCE(LAG(s.bur_end) OVER (ORDER BY s.skiftraknare), 0)) AS bur_ej_ok,
                    GREATEST(0, s.tot_end - COALESCE(LAG(s.tot_end) OVER (ORDER BY s.skiftraknare), 0)) AS totalt,
                    s.drifttid,
                    s.driftstopptime,
                    s.op1,
                    s.op2,
                    s.op3,
                    s.product_id
                FROM (
                    SELECT
                        r.skiftraknare,
                        MIN(r.datum)          AS datum,
                        MAX(r.ibc_ok)         AS ibc_end,
                        MAX(r.ibc_ej_ok)      AS ej_end,
                        MAX(r.bur_ej_ok)      AS bur_end,
                        MAX(r.totalt)         AS tot_end,
                        MAX(r.drifttid)       AS drifttid,
                        MAX(r.driftstopptime) AS driftstopptime,
                        MAX(r.op1)            AS op1,
                        MAX(r.op2)            AS op2,
                        MAX(r.op3)            AS op3,
                        MAX(r.product_id)     AS product_id
                    FROM rebotling_skiftrapport r
                    WHERE r.datum = :datum
                    GROUP BY r.skiftraknare
                ) s
                ORDER BY s.skiftraknare ASC
            ");
            $stmt->execute([':datum' => $datum]);
            $raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // ----- 2. Resolve operator + product names -----
            $opNums = [];
            foreach ($raw as $r) {
                foreach (['op1','op2','op3'] as $k) {
                    if (!empty($r[$k])) $opNums[] = (int)$r[$k];
                }
            }
            $opNums  = array_values(array_unique(array_filter($opNums)));
            $opNames = [];
            if (!empty($opNums)) {
                $ph = implode(',', array_fill(0, count($opNums), '?'));
                $s2 = $this->pdo->prepare("SELECT number, name FROM operators WHERE number IN ($ph)");
                $s2->execute($opNums);
                foreach ($s2->fetchAll(\PDO::FETCH_ASSOC) as $op) {
                    $opNames[(int)$op['number']] = $op['name'];
                }
            }

            $prodIds  = array_values(array_unique(array_filter(array_column($raw, 'product_id'))));
            $prodNames = [];
            if (!empty($prodIds)) {
                $ph2 = implode(',', array_fill(0, count($prodIds), '?'));
                $s3  = $this->pdo->prepare("SELECT id, name FROM rebotling_products WHERE id IN ($ph2)");
                $s3->execute($prodIds);
                foreach ($s3->fetchAll(\PDO::FETCH_ASSOC) as $pr) {
                    $prodNames[(int)$pr['id']] = $pr['name'];
                }
            }

            // ----- 3. Build shift rows -----
            $shifts = [];
            $totalIbc      = 0;
            $totalDrifttid = 0;
            $totalStopp    = 0;
            $totalEjOk     = 0;
            $totalTotalt   = 0;

            foreach ($raw as $r) {
                $ibc_ok   = (int)$r['ibc_ok'];
                $ibc_ej   = (int)$r['ibc_ej_ok'] + (int)$r['bur_ej_ok'];
                $totalt   = (int)$r['totalt'] ?: ($ibc_ok + $ibc_ej);
                $drift    = (int)$r['drifttid'];
                $stopp    = (int)$r['driftstopptime'];
                $hours    = $drift / 60.0;
                $ibc_h    = $hours > 0 ? round($ibc_ok / $hours, 2) : 0.0;
                $kass_pct = $totalt > 0 ? round($ibc_ej / $totalt * 100, 2) : 0.0;
                $sched    = $drift + $stopp;
                $stopp_pct= $sched > 0 ? round($stopp / $sched * 100, 1) : 0.0;

                $op1 = $r['op1'] ? (int)$r['op1'] : null;
                $op2 = $r['op2'] ? (int)$r['op2'] : null;
                $op3 = $r['op3'] ? (int)$r['op3'] : null;
                $pid = $r['product_id'] ? (int)$r['product_id'] : null;

                $shifts[] = [
                    'skiftraknare'  => (int)$r['skiftraknare'],
                    'datum'         => $r['datum'],
                    'ibc_ok'        => $ibc_ok,
                    'ibc_h'         => $ibc_h,
                    'kass_pct'      => $kass_pct,
                    'stopp_pct'     => $stopp_pct,
                    'drifttid'      => $drift,
                    'driftstopptime'=> $stopp,
                    'op1'           => $op1,
                    'op2'           => $op2,
                    'op3'           => $op3,
                    'op1_name'      => $op1 ? ($opNames[$op1] ?? "Op $op1") : null,
                    'op2_name'      => $op2 ? ($opNames[$op2] ?? "Op $op2") : null,
                    'op3_name'      => $op3 ? ($opNames[$op3] ?? "Op $op3") : null,
                    'product_id'    => $pid,
                    'product_name'  => $pid ? ($prodNames[$pid] ?? "Produkt $pid") : null,
                ];

                $totalIbc      += $ibc_ok;
                $totalDrifttid += $drift;
                $totalStopp    += $stopp;
                $totalEjOk     += $ibc_ej;
                $totalTotalt   += $totalt;
            }

            // Day-level KPIs using SUM/SUM
            $totalHours    = $totalDrifttid / 60.0;
            $dayIbcH       = $totalHours > 0 ? round($totalIbc / $totalHours, 2) : 0.0;
            $dayKassPct    = $totalTotalt > 0 ? round($totalEjOk / $totalTotalt * 100, 2) : 0.0;
            $daySched      = $totalDrifttid + $totalStopp;
            $dayStoppPct   = $daySched > 0 ? round($totalStopp / $daySched * 100, 1) : 0.0;

            // ----- 4. Yesterday totals -----
            // ibc_ok is a daily running counter — day total = MAX across all shift MAXes.
            // drifttid resets per shift — SUM of per-shift MAXes gives day total.
            $yIbc = 0; $yHours = 0.0; $yIbcH = 0.0;
            try {
                $stmt2 = $this->pdo->prepare("
                    SELECT COALESCE(MAX(s.ibc_ok), 0) AS ibc_ok,
                           COALESCE(SUM(s.drifttid), 0) AS drifttid
                    FROM (
                        SELECT skiftraknare, MAX(ibc_ok) AS ibc_ok, MAX(drifttid) AS drifttid
                        FROM rebotling_skiftrapport
                        WHERE datum = :yday
                        GROUP BY skiftraknare
                    ) s
                ");
                $stmt2->execute([':yday' => $yesterday]);
                $yRow  = $stmt2->fetch(\PDO::FETCH_ASSOC);
                $yIbc  = (int)($yRow['ibc_ok']  ?? 0);
                $yMins = (int)($yRow['drifttid'] ?? 0);
                $yHours = $yMins / 60.0;
                $yIbcH  = $yHours > 0 ? round($yIbc / $yHours, 2) : 0.0;
            } catch (\Throwable $e) {
                error_log('RebotlingController::getIdag (yesterday): ' . $e->getMessage());
            }

            // ----- 5. 30-day average IBC/h -----
            // Per day: MAX(shift ibc_end) = day total (daily counter). SUM across 30 days.
            $avg30IbcH = 0.0;
            try {
                $stmt3 = $this->pdo->prepare("
                    SELECT SUM(day_ibc) AS tot_ibc, SUM(day_drift) AS tot_drift
                    FROM (
                        SELECT MAX(s.ibc_ok)      AS day_ibc,
                               SUM(s.drifttid)    AS day_drift
                        FROM (
                            SELECT DATE(MAX(datum)) AS dag,
                                   MAX(ibc_ok)      AS ibc_ok,
                                   MAX(drifttid)    AS drifttid
                            FROM rebotling_skiftrapport
                            WHERE datum BETWEEN :from30 AND :to30
                            GROUP BY skiftraknare
                            HAVING MAX(drifttid) >= 30
                        ) s
                        GROUP BY s.dag
                    ) days
                ");
                $stmt3->execute([':from30' => $from30, ':to30' => $to30]);
                $row30  = $stmt3->fetch(\PDO::FETCH_ASSOC);
                $tot30  = (float)($row30['tot_ibc']   ?? 0);
                $hrs30  = (float)($row30['tot_drift']  ?? 0) / 60.0;
                $avg30IbcH = $hrs30 > 0 ? round($tot30 / $hrs30, 2) : 0.0;
            } catch (\Throwable $e) {
                error_log('RebotlingController::getIdag (30d avg): ' . $e->getMessage());
            }

            // ----- 6. Per-week trend: last 7 days IBC totals (for sparkline) -----
            // Per day IBC = MAX(ibc_ok) across all shift-level MAXes for that day.
            $weekTrend = [];
            try {
                $stmt4 = $this->pdo->prepare("
                    SELECT d.dag, COALESCE(dy.day_ibc, 0) AS ibc_ok
                    FROM (
                        SELECT DATE_SUB(:ref, INTERVAL seq DAY) AS dag
                        FROM (SELECT 0 seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                              UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) nums
                    ) d
                    LEFT JOIN (
                        SELECT dag, MAX(ibc_ok) AS day_ibc
                        FROM (
                            SELECT DATE(MAX(datum)) AS dag, MAX(ibc_ok) AS ibc_ok
                            FROM rebotling_skiftrapport
                            WHERE datum BETWEEN DATE_SUB(:ref2, INTERVAL 6 DAY) AND :ref3
                            GROUP BY skiftraknare
                        ) shifts
                        GROUP BY dag
                    ) dy ON dy.dag = d.dag
                    ORDER BY d.dag ASC
                ");
                $stmt4->execute([':ref' => $datum, ':ref2' => $datum, ':ref3' => $datum]);
                foreach ($stmt4->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $weekTrend[] = [
                        'datum'   => $row['dag'],
                        'ibc_ok'  => (int)$row['ibc_ok'],
                    ];
                }
            } catch (\Throwable $e) {
                error_log('RebotlingController::getIdag (week_trend): ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'datum'   => $datum,
                'shifts'  => $shifts,
                'kpis'    => [
                    'ibc_ok'    => $totalIbc,
                    'ibc_h'     => $dayIbcH,
                    'kass_pct'  => $dayKassPct,
                    'stopp_pct' => $dayStoppPct,
                    'skift_count' => count($shifts),
                ],
                'yesterday' => [
                    'ibc_ok' => $yIbc,
                    'ibc_h'  => $yIbcH,
                    'delta_ibc'  => $totalIbc - $yIbc,
                    'delta_ibch' => round($dayIbcH - $yIbcH, 2),
                ],
                'avg_30d' => [
                    'ibc_h'      => $avg30IbcH,
                    'vs_avg_pct' => $avg30IbcH > 0 ? round(($dayIbcH - $avg30IbcH) / $avg30IbcH * 100, 1) : 0.0,
                ],
                'week_trend' => $weekTrend,
            ], \JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            error_log('RebotlingController::getIdag: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid daglig produktionsöversikt'], \JSON_UNESCAPED_UNICODE);
        }
    }
}
