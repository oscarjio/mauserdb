<?php
require_once __DIR__ . '/RebotlingAdminController.php';
require_once __DIR__ . '/RebotlingAnalyticsController.php';

class RebotlingController {
    private $pdo;
    private $adminController;
    private $analyticsController;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->adminController = new RebotlingAdminController($pdo);
        $this->analyticsController = new RebotlingAnalyticsController($pdo);
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
            ];
            if (in_array($action, $adminOnlyActions, true)) {
                if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
                if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
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

            // --- Live-data GET endpoints ---
            } elseif ($action === 'status') {
                $this->getRunningStatus();
            } elseif ($action === 'rast') {
                $this->getRastStatus();
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
                $this->getStoppageAnalysis();
            } elseif ($action === 'pareto-stoppage') {
                $this->getParetoStoppage();
            } elseif ($action === 'alert-thresholds') {
                $this->getAlertThresholds();
            } elseif ($action === 'today-snapshot') {
                $this->getTodaySnapshot();
            } elseif ($action === 'cycle-by-operator') {
                $this->getCycleByOperator();
            } elseif ($action === 'shift-trend') {
                $this->getShiftTrend();
            } elseif ($action === 'all-lines-status') {
                $this->getAllLinesStatus();
            } elseif ($action === 'notification-settings') {
                $this->getNotificationSettings();
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
                    echo json_encode(['success' => false, 'error' => 'Inloggning krävs.']);
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
                    echo json_encode(['success' => false, 'error' => 'Inloggning krävs.']);
                    return;
                }
                $this->registerKassation();
                return;
            }
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
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
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ogiltig action']);
            }
            return;
        }

        // Om ingen matchande metod finns
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
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


    private function getLiveStats() {
        try {
            // Hämta senaste skifträknaren
            $stmt = $this->pdo->prepare('
                SELECT skiftraknare
                FROM rebotling_onoff 
                WHERE skiftraknare IS NOT NULL
                ORDER BY datum DESC 
                LIMIT 1
            ');
            $stmt->execute();
            $skiftResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentSkift = $skiftResult && isset($skiftResult['skiftraknare']) ? (int)$skiftResult['skiftraknare'] : null;

            // Hämta totalt antal IBCer rebotlat idag (alla rader i rebotling_ibc för idag)
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) 
                FROM rebotling_ibc 
                WHERE DATE(datum) = CURDATE()
            ');
            $stmt->execute();
            $ibcToday = (int)$stmt->fetchColumn();

            // Hämta aktuellt löpnummer från PLC-tabellen (en rad som uppdateras av PLC-backend)
            $nextLopnummer = null;
            try {
                $stmt = $this->pdo->query('
                    SELECT lopnummer
                    FROM rebotling_lopnummer_current
                    WHERE id = 1
                    LIMIT 1
                ');
                $lopRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($lopRow && isset($lopRow['lopnummer'])) {
                    $nextLopnummer = (int)$lopRow['lopnummer'];
                }
            } catch (Exception $e) {
                // Tabellen kanske inte finns ännu – ignorera tyst i live-vyn
                error_log('RebotlingController getLiveStats: kunde inte läsa rebotling_lopnummer_current: ' . $e->getMessage());
            }

            // Hämta antal IBCer från senaste timmen för nuvarande skift
            $rebotlingThisHour = 0;
            if ($currentSkift !== null) {
                $stmt = $this->pdo->prepare('
                    SELECT COUNT(*) as ibc_count_hour
                    FROM rebotling_ibc 
                    WHERE skiftraknare = ?
                    AND datum >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ');
                $stmt->execute([$currentSkift]);
                $ibcHourResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $rebotlingThisHour = $ibcHourResult ? (int)$ibcHourResult['ibc_count_hour'] : 0;
            }

            // Hämta produkt från nuvarande skift
            $hourlyTarget = 15; // Default värde
            if ($currentSkift !== null) {
                $stmt = $this->pdo->prepare('
                    SELECT produkt
                    FROM rebotling_onoff 
                    WHERE skiftraknare = ?
                    AND produkt IS NOT NULL
                    ORDER BY datum DESC 
                    LIMIT 1
                ');
                $stmt->execute([$currentSkift]);
                $produktResult = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($produktResult && isset($produktResult['produkt']) && $produktResult['produkt'] > 0) {
                    $produktId = (int)$produktResult['produkt'];
                    
                    // Hämta cykeltid för produkten
                    $stmt = $this->pdo->prepare('
                        SELECT cycle_time_minutes
                        FROM rebotling_products 
                        WHERE id = ?
                    ');
                    $stmt->execute([$produktId]);
                    $productResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($productResult && isset($productResult['cycle_time_minutes']) && $productResult['cycle_time_minutes'] > 0) {
                        $cycleTime = (float)$productResult['cycle_time_minutes'];
                        // Räkna ut antal per timme: 60 minuter / cykeltid i minuter
                        $hourlyTarget = round(60 / $cycleTime, 1);
                    }
                }
            }

            // Beräkna total runtime för nuvarande skift
            // Runtime räknas som summan av alla perioder när maskinen var running
            $totalRuntimeMinutes = 0;
            if ($currentSkift !== null) {
                // Hämta alla rader för skiftet sorterade efter datum
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
                    $now = new DateTime();
                    
                    foreach ($skiftEntries as $entry) {
                        $entryTime = new DateTime($entry['datum']);
                        $isRunning = (bool)($entry['running'] ?? false);
                        
                        // Om maskinen startar (running=1) och vi inte redan räknar en period
                        if ($isRunning && $lastRunningStart === null) {
                            $lastRunningStart = $entryTime;
                        }
                        // Om maskinen stoppar (running=0) och vi räknar en period
                        elseif (!$isRunning && $lastRunningStart !== null) {
                            $diff = $lastRunningStart->diff($entryTime);
                            $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                            $totalRuntimeMinutes += $periodMinutes;
                            $lastRunningStart = null;
                        }
                    }
                    
                    // Om maskinen fortfarande kör (senaste entry är running=1)
                    if ($lastRunningStart !== null) {
                        $lastEntryTime = new DateTime($skiftEntries[count($skiftEntries) - 1]['datum']);
                        // Räkna från när maskinen startade till senaste entry
                        $diff = $lastRunningStart->diff($lastEntryTime);
                        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $totalRuntimeMinutes += $periodMinutes;
                        
                        // Lägg till tiden från senaste entry till nu
                        $diffSinceLast = $lastEntryTime->diff($now);
                        $minutesSinceLastUpdate = ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                        $totalRuntimeMinutes += $minutesSinceLastUpdate;
                    }
                }
            }

            // Beräkna produktionsprocent
            // Produktion = (antal cykler / total runtime i timmar) / hourlyTarget * 100
            // eller: (antal cykler * 60) / (total runtime i minuter) / hourlyTarget * 100
            $productionPercentage = 0;
            if ($totalRuntimeMinutes > 0 && $ibcToday > 0 && $hourlyTarget > 0) {
                // Beräkna faktisk produktion per timme: (antal cykler * 60) / runtime i minuter
                $actualProductionPerHour = ($ibcToday * 60) / $totalRuntimeMinutes;
                // Jämför med mål per timme för att få procent
                $productionPercentage = round(($actualProductionPerHour / $hourlyTarget) * 100, 1);
            }

            // Använd verklig IBC-räknare och hämta dagsmål från settings
            $rebotlingToday = $ibcToday;
            $rebotlingTarget = 1000; // fallback
            try {
                $this->ensureSettingsTable();
                $sRow = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sRow) $rebotlingTarget = (int)$sRow['rebotling_target'];
            } catch (Exception $e) {
                error_log('getLiveStats: kunde inte läsa rebotling_settings: ' . $e->getMessage());
            }

            // Kolla om undantag finns för idag
            try {
                $stmtEx = $this->pdo->prepare('SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE()');
                $stmtEx->execute();
                $exceptionRow = $stmtEx->fetch(PDO::FETCH_ASSOC);
                if ($exceptionRow) {
                    $rebotlingTarget = (int)$exceptionRow['justerat_mal'];
                }
            } catch (Exception $e) { /* tabell saknas ännu — ignorera */ }

            // Hämta senaste utetemperatur
            $utetemperatur = null;
            try {
                $stmt = $this->pdo->prepare('
                    SELECT utetemperatur, datum
                    FROM vader_data 
                    ORDER BY datum DESC 
                    LIMIT 1
                ');
                $stmt->execute();
                $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($weatherData) {
                    $utetemperatur = (float)$weatherData['utetemperatur'];
                }
            } catch (Exception $e) {
                // Ignorera fel vid hämtning av väderdata
                error_log('Kunde inte hämta väderdata: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'rebotlingToday' => $rebotlingToday,
                    'rebotlingTarget' => $rebotlingTarget,
                    'rebotlingThisHour' => $rebotlingThisHour,
                    'hourlyTarget' => $hourlyTarget,
                    'ibcToday' => $ibcToday,
                    'productionPercentage' => $productionPercentage,
                    'nextLopnummer' => $nextLopnummer,
                    'utetemperatur' => $utetemperatur
                ]
            ]);

            // Auto-kontroll: skapa rekordnyhet om klockan är efter 18:00 och det finns produktion
            $currentHour = (int)date('G');
            if ($currentHour >= 18 && $ibcToday > 0) {
                $this->checkAndCreateRecordNews();
            }

        } catch (Exception $e) {
            error_log('Kunde inte hämta statistik (getLiveStats): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ]);
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
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'running'    => $isRunning,
                    'on_rast'    => $onRast,
                    'lastUpdate' => $lastUpdate
                ]
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta status (getRunningStatus): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta status'
            ]);
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
                WHERE DATE(datum) = :today
                ORDER BY datum ASC
            ");
            $stmt->execute(['today' => $todayStr]);
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
            ]);
        } catch (Exception $e) {
            error_log('getRastStatus error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta raststatus']);
        }
    }


    private function getStatistics() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d');
            $end = $_GET['end'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = date('Y-m-d');

            // Hämta cykler för perioden med FAKTISK beräknad cykeltid och target från produkt.
            // OBS: Joina direkt på i.produkt → rebotling_products för att undvika
            // många-till-många-duplikat via rebotling_onoff (ett skift har många onoff-rader).
            $stmt = $this->pdo->prepare('
                SELECT
                    i.datum,
                    i.ibc_count,
                    i.produktion_procent,
                    i.skiftraknare,
                    TIMESTAMPDIFF(MINUTE,
                        LAG(i.datum) OVER (PARTITION BY i.skiftraknare ORDER BY i.datum),
                        i.datum
                    ) as cycle_time,
                    p.cycle_time_minutes as target_cycle_time
                FROM rebotling_ibc i
                LEFT JOIN rebotling_products p ON i.produkt = p.id
                WHERE DATE(i.datum) BETWEEN :start AND :end
                ORDER BY i.datum ASC
            ');
            $stmt->execute(['start' => $start, 'end' => $end]);
            $rawCycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filtrera bort felaktiga cykeltider
            // Om cykeltiden är NULL eller över 30 minuter (maskinen var troligen stoppad), sätt till NULL
            $cycles = [];
            foreach ($rawCycles as $cycle) {
                // Filtrera bort första cykeln (NULL cycle_time) eller onormalt långa cykeltider
                if ($cycle['cycle_time'] !== null && $cycle['cycle_time'] > 0 && $cycle['cycle_time'] <= 30) {
                    $cycles[] = $cycle;
                } else if ($cycle['cycle_time'] !== null && $cycle['cycle_time'] > 30) {
                    // Behåll cykeln men sätt cycle_time till NULL för långa pauser
                    $cycle['cycle_time'] = null;
                    $cycles[] = $cycle;
                }
            }

            // Hämta on/off events för perioden
            $stmt = $this->pdo->prepare('
                SELECT 
                    datum,
                    running,
                    runtime_today
                FROM rebotling_onoff 
                WHERE DATE(datum) BETWEEN :start AND :end
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
                     WHERE DATE(datum) BETWEEN :start AND :end
                     ORDER BY datum ASC'
                );
                $rastStmt->execute(['start' => $start, 'end' => $end]);
                $rast_events = $rastStmt->fetchAll(PDO::FETCH_ASSOC);

                // Beräkna total rasttid
                $rs = null;
                foreach ($rast_events as $ev) {
                    if ((int)$ev['rast_status'] === 1) {
                        $rs = new DateTime($ev['datum']);
                    } elseif ((int)$ev['rast_status'] === 0 && $rs !== null) {
                        $d = $rs->diff(new DateTime($ev['datum']));
                        $totalRastMinutes += ($d->days * 1440) + ($d->h * 60) + $d->i + ($d->s / 60);
                        $rs = null;
                    }
                }
            } catch (Exception $e) {
                // Tabellen saknas eller fel – ignorera
            }

            // Beräkna sammanfattning
            $total_cycles = count($cycles);
            $avg_production_percent = 0;
            $avg_cycle_time = 0;
            $total_runtime_hours = 0;
            $target_cycle_time = 0;
            
            if ($total_cycles > 0) {
                $sum_percent = array_sum(array_column($cycles, 'produktion_procent'));
                $avg_production_percent = $sum_percent / $total_cycles;
                
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
                    $eventTime = new DateTime($event['datum']);
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
                    $lastEventTime = new DateTime($onoff_events[count($onoff_events) - 1]['datum']);
                    $diff = $lastRunningStart->diff($lastEventTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                }
            }
            
            // Alternativ beräkning: Om vi inte fick runtime från events men har cykler,
            // uppskatta runtime från första till sista cykeln
            if ($totalRuntimeMinutes == 0 && $total_cycles > 0) {
                $firstCycle = new DateTime($cycles[0]['datum']);
                $lastCycle = new DateTime($cycles[count($cycles) - 1]['datum']);
                $diff = $firstCycle->diff($lastCycle);
                $totalRuntimeMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
            }
            
            $total_runtime_hours = $totalRuntimeMinutes / 60;

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
                    'summary' => [
                        'total_cycles' => $total_cycles,
                        'avg_production_percent' => round($avg_production_percent, 1),
                        'avg_cycle_time' => round($avg_cycle_time, 1),
                        'target_cycle_time' => round($target_cycle_time, 1),
                        'total_runtime_hours' => round($total_runtime_hours, 1),
                        'days_with_production' => $days_with_production,
                        'total_rast_minutes' => round($totalRastMinutes, 1)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta statistik (getStatistics): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik'
            ]);
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
                WHERE DATE(datum) = :date
                ORDER BY datum ASC
            ');
            $stmt->execute(['date' => $date]);
            $hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta on/off events för dagen
            $stmt = $this->pdo->prepare('
                SELECT
                    DATE_FORMAT(datum, "%H:%i") as time,
                    running
                FROM rebotling_onoff
                WHERE DATE(datum) = :date
                ORDER BY datum ASC
            ');
            $stmt->execute(['date' => $date]);
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
                    WHERE DATE(datum) = :date
                    ORDER BY datum ASC
                ');
                $rastStmt->execute(['date' => $date]);
                $rast_events = $rastStmt->fetchAll(PDO::FETCH_ASSOC);

                // Beräkna total rasttid för dagen
                $rastStart = null;
                foreach ($rast_events as $ev) {
                    if ((int)$ev['rast_status'] === 1 && $rastStart === null) {
                        $rastStart = new DateTime($ev['datum_full']);
                    } elseif ((int)$ev['rast_status'] === 0 && $rastStart !== null) {
                        $end = new DateTime($ev['datum_full']);
                        $diff = $rastStart->diff($end);
                        $rast_total_min += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $rastStart = null;
                    }
                }
                $rast_data = array_map(fn($e) => ['time' => $e['time'], 'rast_status' => (int)$e['rast_status']], $rast_events);
            } catch (Exception $e) {
                // Tabellen kanske saknas – ignorera tyst
            }

            // Hämta totalt rasttime från PLC (D4008) för dagen
            $plc_rast_min = 0;
            try {
                $plcRastStmt = $this->pdo->prepare('
                    SELECT MAX(COALESCE(rasttime, 0)) as total_rast_plc,
                           MAX(COALESCE(runtime_plc, 0)) as total_runtime_plc
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                ');
                $plcRastStmt->execute(['date' => $date]);
                $plcRast = $plcRastStmt->fetch(PDO::FETCH_ASSOC);
                $plc_rast_min = round($plcRast['total_rast_plc'] ?? 0, 1);
                $plc_runtime_min = round($plcRast['total_runtime_plc'] ?? 0, 1);
            } catch (Exception $e) {
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
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta dagsstatistik: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta dagsstatistik'
            ]);
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
        $period = $_GET['period'] ?? 'today';

        // Notera: alias "r" används i dateFilter och måste matcha yttre queryn
        $dateFilter = match($period) {
            'today' => "DATE(r.datum) = CURDATE()",
            'week'  => "r.datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "r.datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "DATE(r.datum) = CURDATE()"
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
                      AND skiftraknare IS NOT NULL
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
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte beräkna OEE: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte beräkna OEE'
            ]);
        }
    }

    /**
     * Cykeltids-trendanalys per dag (senaste 30 dagarna).
     * Returnerar snitt cykeltid, antal cykler, och trendindikator.
     */

    private function getCycleTrend() {
        try {
            $fromDate = $_GET['from_date'] ?? null;
            $toDate   = $_GET['to_date']   ?? null;
            if ($fromDate && $toDate) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                    echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
                    return;
                }
                $cycleStart = $fromDate;
                $cycleEnd   = $toDate;
            } else {
                $days = min(365, max(7, intval($_GET['days'] ?? 30)));
                $cycleStart = date('Y-m-d', strtotime("-{$days} days"));
                $cycleEnd   = date('Y-m-d');
            }
            $granularity = $_GET['granularity'] ?? 'day';

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
                      AND skiftraknare IS NOT NULL
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
                ]);
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
                      AND skiftraknare IS NOT NULL
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
                    'change_pct' => round((($recent - $older) / $older) * 100, 1),
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
            ]);
        } catch (Exception $e) {
            error_log('getCycleTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta trenddata']);
        }
    }


    private function getHeatmap() {
        $fromDate = $_GET['from_date'] ?? null;
        $toDate   = $_GET['to_date']   ?? null;

        if ($fromDate && $toDate) {
            // Validera datumformat
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
                return;
            }
            // Begränsa till max 365 dagar
            $startDt = new DateTime($fromDate);
            $endDt   = new DateTime($toDate);
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

            echo json_encode(['success' => true, 'data' => $data, 'start' => $start, 'end' => $end]);
        } catch (Exception $e) {
            error_log('RebotlingController getHeatmap: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta heatmap-data']);
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
            } catch (Exception $e) { /* ignorera */ }

            $today = date('Y-m-d');

            // Försök hämta data för idag. Om inga skiftrapporter finns idag — fall tillbaka på senaste 7 dagarna.
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM rebotling_skiftrapport WHERE datum = :today
            ");
            $stmt->execute(['today' => $today]);
            $countToday = (int)$stmt->fetchColumn();

            if ($countToday > 0) {
                $dateFilter = "s.datum = :dateFrom";
                $dateParam  = ['dateFrom' => $today];
                $periodLabel = $today;
            } else {
                $dateFilter = "s.datum >= :dateFrom";
                $dateParam  = ['dateFrom' => date('Y-m-d', strtotime('-7 days'))];
                $periodLabel = 'senaste 7 dagarna';
            }

            // Aggregera per operatör (op1/op2/op3 lagras som operator-nummer)
            // Varje skiftrapport kan ha upp till 3 operatörer.
            // Vi slår ihop dem via UNION och aggregerar sedan.
            $sql = "
                SELECT
                    o.number        AS op_number,
                    o.name          AS name,
                    SUM(sub.ibc_ok)   AS ibc_ok,
                    SUM(sub.totalt)   AS totalt,
                    SUM(sub.drifttid) AS drifttid,
                    COUNT(sub.skift_id) AS shifts_count
                FROM (
                    SELECT s.id AS skift_id, s.op1 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE {$dateFilter} AND s.op1 IS NOT NULL
                    UNION ALL
                    SELECT s.id AS skift_id, s.op2 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE {$dateFilter} AND s.op2 IS NOT NULL
                    UNION ALL
                    SELECT s.id AS skift_id, s.op3 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE {$dateFilter} AND s.op3 IS NOT NULL
                ) sub
                JOIN operators o ON o.number = sub.op_num
                GROUP BY o.number, o.name
                ORDER BY (SUM(sub.ibc_ok) / GREATEST(SUM(sub.drifttid)/60, 0.01)) DESC
                LIMIT 10
            ";

            // PDO named placeholders kan inte upprepas — bind manuellt med positional
            if ($countToday > 0) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':dateFrom'  => $today,
                    ':dateFrom'  => $today,   // op2
                    ':dateFrom'  => $today,   // op3 — PDO overwrites duplicates, so use positional below
                ]);
            }

            // Bygg om med positional placeholders för att undvika duplikat-parameter-problem
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

            // Summera ibc_idag_total från ranking-listan
            $ibcIdagTotal = 0;
            foreach ($ranking as $r) {
                $ibcIdagTotal += $r['ibc_ok'];
            }

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
                          AND DATE(datum) < CURDATE()
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
                error_log('getLiveRanking rekord: ' . $e->getMessage());
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
            ]);
        } catch (Exception $e) {
            error_log('getLiveRanking: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta live ranking']);
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
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum.']);
            return;
        }
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skift_nr (1-3).']);
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
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => true, 'data' => null]);
            }
        } catch (Exception $e) {
            error_log('getSkiftKommentar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel.']);
        }
    }


    private function setSkiftKommentar() {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig request-body.']);
            return;
        }

        $datum     = $body['datum']     ?? '';
        $skiftNr   = intval($body['skift_nr'] ?? 0);
        $kommentar = strip_tags($body['kommentar'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum.']);
            return;
        }
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skift_nr (1-3).']);
            return;
        }
        if (mb_strlen($kommentar) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Kommentaren får vara max 500 tecken.']);
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

            echo json_encode(['success' => true, 'message' => 'Kommentar sparad.']);
        } catch (Exception $e) {
            error_log('setSkiftKommentar: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid sparande.']);
        }
    }
    /**
     * GET ?action=rebotling&run=weekday-stats&dagar=90
     * Returnerar genomsnittlig IBC-produktion och OEE per veckodag.
     * Används av Veckodag-analys i rebotling-statistik.
     */

    private function getEvents(): void {
        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-90 days'));
        $end   = $_GET['end']   ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d', strtotime('-90 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, event_date, title, description, event_type
                 FROM production_events
                 WHERE event_date BETWEEN ? AND ?
                 ORDER BY event_date"
            );
            $stmt->execute([$start, $end]);
            echo json_encode(['success' => true, 'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            error_log('RebotlingController getEvents: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'events' => []]);
        }
    }

    // POST ?action=rebotling&run=add-event (kräver admin-session)

    private function addEvent(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Åtkomst nekad']);
            return;
        }
        $date  = trim($_POST['event_date']   ?? '');
        $title = strip_tags(trim($_POST['title']   ?? ''));
        $desc  = strip_tags(trim($_POST['description'] ?? ''));
        $type  = trim($_POST['event_type']   ?? 'ovrigt');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$title) {
            http_response_code(400);
            echo json_encode(['error' => 'Ogiltiga uppgifter']);
            return;
        }
        $allowed = ['underhall', 'ny_operator', 'mal_andring', 'rekord', 'ovrigt'];
        if (!in_array($type, $allowed)) $type = 'ovrigt';
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO production_events (event_date, title, description, event_type, created_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$date, $title, $desc, $type, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'id' => $this->pdo->lastInsertId()]);
        } catch (Exception $e) {
            error_log('RebotlingController addEvent: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Kunde inte spara händelsen']);
        }
    }

    // GET ?action=rebotling&run=stoppage-analysis&days=30
    // Returnerar stoppanalys från stoppage_log + stoppage_reasons.
    // Hanterar saknad tabell gracefully (returnerar empty=true).

    private function deleteEvent(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Åtkomst nekad']);
            return;
        }
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Saknar id']);
            return;
        }
        try {
            $this->pdo->prepare("DELETE FROM production_events WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log('RebotlingController deleteEvent: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Kunde inte ta bort händelsen']);
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
                    o.number AS op_number,
                    o.name   AS op_name,
                    MAX(CASE WHEN t.drifttid > 0 THEN ROUND(t.ibc_ok / (t.drifttid / 60.0), 2) ELSE NULL END) AS best_ibc_h,
                    MAX(CASE WHEN t.totalt   > 0 THEN ROUND(t.ibc_ok / t.totalt * 100, 1) ELSE NULL END)     AS best_kvalitet,
                    COUNT(DISTINCT t.skift_id) AS total_skift
                FROM (
                    SELECT s.id AS skift_id, s.op1 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s WHERE s.op1 IS NOT NULL AND s.ibc_ok > 0
                    UNION ALL
                    SELECT s.id AS skift_id, s.op2 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s WHERE s.op2 IS NOT NULL AND s.ibc_ok > 0
                    UNION ALL
                    SELECT s.id AS skift_id, s.op3 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s WHERE s.op3 IS NOT NULL AND s.ibc_ok > 0
                ) t
                JOIN operators o ON o.number = t.op_num
                GROUP BY o.number, o.name
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
            $sqlBestDay = "
                SELECT
                    t.op_num,
                    MAX(t.day_ibc) AS best_day_ibc,
                    (SELECT ps2.datum
                     FROM (
                         SELECT DATE(r2.datum) AS datum, r2.skiftraknare, COALESCE(MAX(r2.ibc_ok),0) AS shift_ibc
                         FROM rebotling_ibc r2
                         WHERE (r2.op1 = t.op_num OR r2.op2 = t.op_num OR r2.op3 = t.op_num)
                           AND r2.ibc_ok IS NOT NULL AND r2.skiftraknare IS NOT NULL
                         GROUP BY DATE(r2.datum), r2.skiftraknare
                     ) ps2
                     GROUP BY ps2.datum
                     ORDER BY SUM(ps2.shift_ibc) DESC
                     LIMIT 1
                    ) AS best_day_date
                FROM (
                    SELECT sub.op_num, DATE(sub.datum) AS dag, SUM(sub.shift_ibc) AS day_ibc
                    FROM (
                        SELECT r.op1 AS op_num, r.datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc r WHERE r.op1 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op1, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op2 AS op_num, r.datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc r WHERE r.op2 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op2, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op3 AS op_num, r.datum, r.skiftraknare, COALESCE(MAX(r.ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc r WHERE r.op3 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op3, DATE(r.datum), r.skiftraknare
                    ) sub
                    GROUP BY sub.op_num, DATE(sub.datum)
                ) t
                GROUP BY t.op_num
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
                        FROM rebotling_ibc r WHERE r.op1 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op1, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op2, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op2 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op2, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op3, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op3 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
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
                        FROM rebotling_ibc r WHERE r.op1 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op1, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op2, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op2 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
                        GROUP BY r.op2, DATE(r.datum), r.skiftraknare
                        UNION ALL
                        SELECT r.op3, DATE(r.datum), r.skiftraknare, COALESCE(MAX(r.ibc_ok),0)
                        FROM rebotling_ibc r WHERE r.op3 IS NOT NULL AND r.ibc_ok IS NOT NULL AND r.skiftraknare IS NOT NULL
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

            // Team-rekord dag/vecka/månad (alla operatörer sammanslagna)
            $stmtTeamDay = $pdo->query("
                SELECT MAX(day_ibc) AS best FROM (
                    SELECT DATE(datum) AS d, SUM(shift_ibc) AS day_ibc FROM (
                        SELECT DATE(datum) AS datum, skiftraknare, COALESCE(MAX(ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc WHERE skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) ps GROUP BY DATE(datum)
                ) td
            ");
            $teamBestDay = intval($stmtTeamDay->fetchColumn() ?: 0);

            $stmtTeamWeek = $pdo->query("
                SELECT MAX(week_ibc) AS best FROM (
                    SELECT YEAR(datum) AS yr, WEEK(datum,1) AS wk, SUM(shift_ibc) AS week_ibc FROM (
                        SELECT DATE(datum) AS datum, skiftraknare, COALESCE(MAX(ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc WHERE skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) ps GROUP BY YEAR(datum), WEEK(datum,1)
                ) tw
            ");
            $teamBestWeek = intval($stmtTeamWeek->fetchColumn() ?: 0);

            $stmtTeamMonth = $pdo->query("
                SELECT MAX(month_ibc) AS best FROM (
                    SELECT DATE_FORMAT(datum, '%Y-%m') AS mon, SUM(shift_ibc) AS month_ibc FROM (
                        SELECT DATE(datum) AS datum, skiftraknare, COALESCE(MAX(ibc_ok),0) AS shift_ibc
                        FROM rebotling_ibc WHERE skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) ps GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ) tm
            ");
            $teamBestMonth = intval($stmtTeamMonth->fetchColumn() ?: 0);

            $result = array_map(function($r) use ($teamRecord, $bestDayByOp, $bestWeekByOp, $bestMonthByOp) {
                $opNum = intval($r['op_number']);
                $bestIbcH = round(floatval($r['best_ibc_h'] ?? 0), 2);
                return [
                    'op_number'     => $opNum,
                    'namn'          => $r['op_name'],
                    'initialer'     => strtoupper(substr($r['op_name'] ?? '', 0, 3)),
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
            ]);
        } catch (\Exception $e) {
            error_log('getPersonalBests: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta personbästa-data']);
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
                    WHERE skiftraknare IS NOT NULL
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
            foreach ($topDays as $i => $day) {
                $date = $day['datum'];
                // Hämta unika operatörer den dagen
                $opSql = "
                    SELECT DISTINCT o.name
                    FROM rebotling_ibc r
                    JOIN operators o ON o.number IN (r.op1, r.op2, r.op3)
                    WHERE DATE(r.datum) = ?
                      AND r.ibc_ok IS NOT NULL
                    ORDER BY o.name
                ";
                $opStmt = $pdo->prepare($opSql);
                $opStmt->execute([$date]);
                $operators = $opStmt->fetchAll(\PDO::FETCH_COLUMN);

                $result[] = [
                    'rank'        => $i + 1,
                    'date'        => $date,
                    'ibc_total'   => intval($day['ibc_total']),
                    'avg_quality' => floatval($day['avg_quality']),
                    'operators'   => $operators,
                ];
            }

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            error_log('getHallOfFameDays: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta hall of fame-data']);
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

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            error_log('getMonthlyLeaders: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta månadsdata']);
        }
    }



    private function getAttendance() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad']);
            return;
        }

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            echo json_encode(['success' => false, 'error' => 'Ogiltigt månadsformat']);
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
                "SELECT number AS id, name AS namn, COALESCE(initialer, '') AS initialer FROM operators WHERE active=1 ORDER BY name"
            );
            $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            // Generera initialer från namn om kolumnen saknar värde
            foreach ($operators as &$op) {
                if ($op['initialer'] === '') {
                    $parts = explode(' ', trim($op['namn']));
                    $ini = '';
                    foreach ($parts as $p) {
                        if ($p !== '') $ini .= strtoupper(substr($p, 0, 1));
                    }
                    $op['initialer'] = substr($ini, 0, 3) ?: ('OP' . $op['id']);
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
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getAttendance: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
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
            $stmt = $this->pdo->prepare("SELECT `key`, `value` FROM rebotling_settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => [
                'lr_show_quality'  => ($rows['lr_show_quality']  ?? '1') === '1',
                'lr_show_progress' => ($rows['lr_show_progress'] ?? '1') === '1',
                'lr_show_motto'    => ($rows['lr_show_motto']    ?? '1') === '1',
                'lr_poll_interval' => intval($rows['lr_poll_interval'] ?? 30),
                'lr_title'         => $rows['lr_title'] ?? 'Live Ranking',
            ]]);
        } catch (Exception $e) {
            error_log('getLiveRankingSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    /**
     * POST ?action=rebotling&run=save-live-ranking-settings
     */
    private function saveLiveRankingSettings(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Ej behörig']);
            return;
        }
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $settings = [
                'lr_show_quality'  => isset($body['lr_show_quality'])  ? ($body['lr_show_quality']  ? '1' : '0') : '1',
                'lr_show_progress' => isset($body['lr_show_progress']) ? ($body['lr_show_progress'] ? '1' : '0') : '1',
                'lr_show_motto'    => isset($body['lr_show_motto'])    ? ($body['lr_show_motto']    ? '1' : '0') : '1',
                'lr_poll_interval' => strval(max(10, min(120, intval($body['lr_poll_interval'] ?? 30)))),
                'lr_title'         => substr(strip_tags($body['lr_title'] ?? 'Live Ranking'), 0, 80),
            ];
            $stmt = $this->pdo->prepare("INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log('saveLiveRankingSettings: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
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
                    ibc_ok - LAG(ibc_ok, 1, 0) OVER (PARTITION BY DATE(datum), skiftraknare ORDER BY datum) AS ibc_delta,
                    CASE WHEN (ibc_ok + ibc_ej_ok) > 0 THEN ibc_ok * 100.0 / (ibc_ok + ibc_ej_ok) ELSE NULL END AS kvalitet_pct
                FROM rebotling_ibc
                WHERE datum >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND skiftraknare IS NOT NULL
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

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            error_log('getHourlyRhythm: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fel vid hämtning av produktionsrytm']);
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
            echo json_encode(['success' => true, 'operators' => $operators]);
        } catch (\Exception $e) {
            error_log('getOperatorListForTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörslista']);
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
            echo json_encode(['success' => false, 'error' => 'Ogiltigt op_id']);
            return;
        }

        try {
            // Hämta operator-nummer från id
            $opStmt = $this->pdo->prepare("SELECT number, name FROM operators WHERE id = ? LIMIT 1");
            $opStmt->execute([$opId]);
            $op = $opStmt->fetch(PDO::FETCH_ASSOC);
            if (!$op) {
                echo json_encode(['success' => false, 'error' => 'Operatör hittades inte']);
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
                    CONCAT('V.', LPAD(WEEK(s.datum, 3), 2, '0'))           AS vecka_label,
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
                    CONCAT('V.', LPAD(WEEK(s.datum, 3), 2, '0'))           AS vecka_label,
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
            ]);

        } catch (\Exception $e) {
            error_log('getOperatorWeeklyTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta trenddata']);
        }
    }

    // =========================================================
    // Auto-kontrollera och skapa rekordnyhet
    // Anropas från getLiveStats() efter kl 18:00
    // Använder guard: max en rekordnyhet per dag
    // =========================================================

    private function checkAndCreateRecordNews(): void {
        try {
            // Kontrollera om en rekordnyhet redan skapats idag
            $stmtCheck = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM news
                WHERE DATE(created_at) = CURDATE()
                  AND category = 'rekord'
            ");
            $stmtCheck->execute();
            $checkRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($checkRow && (int)$checkRow['cnt'] > 0) {
                // Rekordnyhet redan skapad idag, skippa
                return;
            }

            // Hämta dagens IBC-total (alla skift idag, korrekta aggregerade värden)
            $stmtToday = $this->pdo->query("
                SELECT COALESCE(SUM(dag_ibc), 0) AS idag_total
                FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS dag_ibc
                    FROM rebotling_ibc
                    WHERE DATE(datum) = CURDATE()
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $todayRow = $stmtToday->fetch(PDO::FETCH_ASSOC);
            $ibcIdag = (int)($todayRow['idag_total'] ?? 0);

            if ($ibcIdag <= 0) return;

            // Hämta historiskt rekord (bästa dag före idag)
            $stmtRekord = $this->pdo->query("
                SELECT MAX(dag_total) AS rekord_ibc, datum_rekord
                FROM (
                    SELECT DATE(datum) AS datum_rekord, SUM(shift_ibc) AS dag_total
                    FROM (
                        SELECT DATE(datum) AS datum, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE DATE(datum) < CURDATE()
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY datum_rekord
                ) AS per_day
            ");
            $rekordRow = $stmtRekord->fetch(PDO::FETCH_ASSOC);
            $rekordIbc = (int)($rekordRow['rekord_ibc'] ?? 0);

            // Slå bara om idag är bättre än rekordet
            if ($ibcIdag <= $rekordIbc) return;

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
            error_log('checkAndCreateRecordNews: Rekordnyhet skapad! Idag=' . $ibcIdag . ', Rekord=' . $rekordIbc);

        } catch (\Exception $e) {
            error_log('checkAndCreateRecordNews: ' . $e->getMessage());
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
            echo json_encode(['success' => true, 'data' => $typer]);
        } catch (\Exception $e) {
            error_log('getKassationTyper: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kassationstyper']);
        }
    }

    /**
     * GET ?action=rebotling&run=kassation-pareto&days=30
     * Returnerar Pareto-data för kassationsorsaker under angiven period.
     * Inkluderar även KPI: totalkassation och % av produktion.
     */

    private function getKassationPareto() {
        $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 30;
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
                SELECT COALESCE(SUM(MAX(r.ibc_ok) + MAX(r.ibc_ej_ok) + MAX(r.bur_ej_ok)), 0) AS total_prod
                FROM rebotling_ibc r
                WHERE DATE(r.datum) BETWEEN :from_date AND :to_date
                  AND r.skiftraknare IS NOT NULL
                GROUP BY r.skiftraknare
            ");
            $stmtProd->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $prodRows = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
            $totalProduktion = array_sum(array_column($prodRows, 'total_prod'));

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
            ]);
        } catch (\Exception $e) {
            error_log('getKassationPareto: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta Pareto-data']);
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
                    t.namn AS orsak_namn,
                    u.username AS registrerad_av_namn
                FROM kassationsregistrering r
                JOIN kassationsorsak_typer t ON t.id = r.orsak_id
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
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            error_log('getKassationSenaste: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kassationsregistreringar']);
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
            echo json_encode(['success' => false, 'error' => 'Ogiltig orsak_id']);
            return;
        }
        if ($antal < 1 || $antal > 9999) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Antal måste vara mellan 1 och 9999']);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum']);
            return;
        }
        if ($kommentar && mb_strlen($kommentar) > 500) {
            $kommentar = mb_substr($kommentar, 0, 500);
        }

        try {
            // Verifiera att orsak_id finns
            $checkStmt = $this->pdo->prepare("SELECT id FROM kassationsorsak_typer WHERE id = ? AND aktiv = 1");
            $checkStmt->execute([$orsakId]);
            if (!$checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Kassationsorsak hittades inte']);
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
            echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId()]);
        } catch (\Exception $e) {
            error_log('registerKassation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara kassationsregistrering']);
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
            } catch (\Exception $ignored) {
                // Kolumnen finns inte än — använd default
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
            ]);
        } catch (\Exception $e) {
            error_log('getStaffingWarning: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta bemanningsvarning']);
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
                echo json_encode(['success' => false, 'error' => 'Ogiltig månadsparameter (YYYY-MM krävs)']);
                return;
            }

            // Kontrollera om tabellen finns
            try {
                $checkStmt = $this->pdo->query("SHOW TABLES LIKE 'rebotling_stopporsak'");
                if ($checkStmt->rowCount() === 0) {
                    echo json_encode(['success' => true, 'items' => [], 'fallback' => true]);
                    return;
                }
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'items' => [], 'fallback' => true]);
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

            echo json_encode(['success' => true, 'items' => $items]);
        } catch (\Exception $e) {
            error_log('getMonthlyStopSummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta stopporsaker']);
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
                        COALESCE(MAX(bur_ej_ok), 0)         AS shift_ibc_ej_ok,
                        COALESCE(MAX(runtime_plc), 0)       AS shift_runtime,
                        COALESCE(MAX(rasttime), 0)          AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
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
            ]);
        } catch (Exception $e) {
            error_log('getOeeComponents: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta OEE-komponentdata']);
        }
    }

    /**
     * GET ?action=rebotling&run=service-status  (publik — ingen auth)
     */

    private function getProductionRate(): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(AVG(CASE WHEN datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN ibc_total END), 0) as avg_ibc_per_day_7d,
                    COALESCE(AVG(CASE WHEN datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ibc_total END), 0) as avg_ibc_per_day_30d,
                    COALESCE(AVG(ibc_total), 0) as avg_ibc_per_day_90d
                FROM (
                    SELECT datum, SUM(ibc_ok) as ibc_total
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    GROUP BY datum
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
                error_log("getProductionRate: could not fetch rebotling_settings: " . $e2->getMessage());
            }

            echo json_encode([
                "success" => true,
                "data" => [
                    "avg_ibc_per_day_7d"  => round((float)($row["avg_ibc_per_day_7d"] ?? 0), 1),
                    "avg_ibc_per_day_30d" => round((float)($row["avg_ibc_per_day_30d"] ?? 0), 1),
                    "avg_ibc_per_day_90d" => round((float)($row["avg_ibc_per_day_90d"] ?? 0), 1),
                    "dag_mal"             => $dagMal
                ]
            ]);
        } catch (\Exception $e) {
            error_log("getProductionRate: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Serverfel"]);
        }
    }

    // =========================================================
    // Skiftrapport-lista — med valfritt operatörsfilter
    // GET ?action=rebotling&run=skiftrapport-list[&operator=X][&limit=50][&offset=0]
    // =========================================================

}
