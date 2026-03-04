<?php
class RebotlingController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['run'] ?? '';

        if ($method === 'GET') {
            // Skydda admin-only GET-endpoints med sessions-kontroll
            $adminOnlyActions = [
                'admin-settings', 'weekday-goals', 'shift-times', 'system-status',
                'alert-thresholds', 'today-snapshot', 'notification-settings',
                'all-lines-status', 'goal-exceptions',
            ];
            if (in_array($action, $adminOnlyActions, true)) {
                if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
                if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
                    return;
                }
            }

            if ($action === 'admin-settings') {
                $this->getAdminSettings();
            } elseif ($action === 'weekday-goals') {
                $this->getWeekdayGoals();
            } elseif ($action === 'shift-times') {
                $this->getShiftTimes();
            } elseif ($action === 'system-status') {
                $this->getSystemStatus();
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
            } elseif ($action === 'report') {
                $this->getProductionReport();
            } elseif ($action === 'heatmap') {
                $this->getHeatmap();
            } elseif ($action === 'week-comparison') {
                $this->getWeekComparison();
            } elseif ($action === 'oee-trend') {
                $this->getOEETrend();
            } elseif ($action === 'best-shifts') {
                $this->getBestShifts();
            } elseif ($action === 'exec-dashboard') {
                $this->getExecDashboard();
            } elseif ($action === 'shift-compare') {
                $this->getShiftCompare();
            } elseif ($action === 'live-ranking') {
                $this->getLiveRanking();
            } elseif ($action === 'cycle-histogram') {
                $this->getCycleHistogram();
            } elseif ($action === 'spc') {
                $this->getSPC();
            } elseif ($action === 'year-calendar') {
                $this->getYearCalendar();
            } elseif ($action === 'day-detail') {
                $this->getDayDetail();
            } elseif ($action === 'benchmarking') {
                $this->getBenchmarking();
            } elseif ($action === 'monthly-report') {
                $this->getMonthlyReport();
            } elseif ($action === 'month-compare') {
                $this->getMonthCompare();
            } elseif ($action === 'maintenance-indicator') {
                $this->getMaintenanceIndicator();
            } elseif ($action === 'annotations') {
                $this->getAnnotations();
            } elseif ($action === 'quality-trend') {
                $this->getQualityTrend();
            } elseif ($action === 'oee-waterfall') {
                $this->getOeeWaterfall();
            } elseif ($action === 'skift-kommentar') {
                $this->getSkiftKommentar();
            } elseif ($action === 'weekday-stats') {
                $this->getWeekdayStats();
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
            } elseif ($action === 'monthly-leaders') {
                $this->getMonthlyLeaders();
            } elseif ($action === 'attendance') {
                $this->getAttendance();
            } elseif ($action === 'goal-history') {
                $this->getGoalHistory();
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
            } elseif ($action === 'goal-exceptions') {
                $this->getGoalExceptions();
            } elseif ($action === 'oee-components') {
                $this->getOeeComponents();
            } elseif ($action === 'service-status') {
                $this->getServiceStatus();
            } elseif ($action === 'maintenance-correlation') {
                $this->getMaintenanceCorrelation();
            } elseif ($action === 'production-rate') {
                $this->getProductionRate();
            } elseif ($action === 'skiftrapport-list') {
                $this->getSkiftrapportList();
            } elseif ($action === 'skiftrapport-operators') {
                $this->getSkiftrapportOperators();
            } elseif ($action === 'shift-summary') {
                $this->getShiftSummary();
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
            if ($action === 'admin-settings') {
                $this->saveAdminSettings();
            } elseif ($action === 'weekday-goals') {
                $this->saveWeekdayGoals();
            } elseif ($action === 'shift-times') {
                $this->saveShiftTimes();
            } elseif ($action === 'save-alert-thresholds') {
                $this->saveAlertThresholds();
            } elseif ($action === 'save-notification-settings') {
                $this->saveNotificationSettings();
            } elseif ($action === 'save-live-ranking-settings') {
                $this->saveLiveRankingSettings();
            } elseif ($action === 'create-record-news') {
                $this->createRecordNewsManual();
            } elseif ($action === 'save-maintenance-log') {
                $this->saveMaintenanceLog();
            } elseif ($action === 'save-goal-exception') {
                $this->saveGoalException();
            } elseif ($action === 'delete-goal-exception') {
                $this->deleteGoalException();
            } elseif ($action === 'reset-service') {
                $this->resetService();
            } elseif ($action === 'save-service-interval') {
                $this->saveServiceInterval();
            } else {
                echo json_encode(['success' => false, 'message' => 'Ogiltig action']);
            }
            return;
        }

        // Om ingen matchande metod finns
        echo json_encode(['success' => false, 'message' => 'Ogiltig metod eller action']);
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
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta raststatus']);
        }
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

    private function getAdminSettings() {
        try {
            $this->ensureSettingsTable();
            $row = $this->pdo->query("SELECT * FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

            $settings = [
                'rebotlingTarget' => (int)($row['rebotling_target'] ?? 1000),
                'hourlyTarget'    => (int)($row['hourly_target']    ?? 50),
                'shiftHours'      => (float)($row['shift_hours']    ?? 8.0),
                'minOperators'    => (int)($row['min_operators']    ?? 2),
                'systemSettings'  => [
                    'autoStart'        => (bool)($row['auto_start']       ?? false),
                    'maintenanceMode'  => (bool)($row['maintenance_mode'] ?? false),
                    'alertThreshold'   => (int)($row['alert_threshold']   ?? 80)
                ]
            ];

            echo json_encode(['success' => true, 'data' => $settings]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta admin-inställningar: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta admin-inställningar']);
        }
    }

    private function saveAdminSettings() {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $this->ensureSettingsTable();

            $rebotlingTarget = isset($data['rebotlingTarget']) ? max(1, intval($data['rebotlingTarget'])) : null;
            $hourlyTarget    = isset($data['hourlyTarget'])    ? max(1, intval($data['hourlyTarget']))    : null;
            $shiftHours      = isset($data['shiftHours'])      ? max(1.0, min(24.0, floatval($data['shiftHours']))) : null;
            $sys             = $data['systemSettings'] ?? [];
            $autoStart       = isset($sys['autoStart'])       ? ($sys['autoStart']       ? 1 : 0) : null;
            $maintenanceMode = isset($sys['maintenanceMode']) ? ($sys['maintenanceMode'] ? 1 : 0) : null;
            $alertThreshold  = isset($sys['alertThreshold'])  ? max(0, min(100, intval($sys['alertThreshold']))) : null;
            $minOperators    = isset($data['minOperators'])   ? max(1, min(10, intval($data['minOperators']))) : null;

            $fields = [];
            $params = [];
            if ($rebotlingTarget !== null) { $fields[] = 'rebotling_target = ?'; $params[] = $rebotlingTarget; }
            if ($hourlyTarget    !== null) { $fields[] = 'hourly_target = ?';    $params[] = $hourlyTarget; }
            if ($shiftHours      !== null) { $fields[] = 'shift_hours = ?';      $params[] = $shiftHours; }
            if ($autoStart       !== null) { $fields[] = 'auto_start = ?';       $params[] = $autoStart; }
            if ($maintenanceMode !== null) { $fields[] = 'maintenance_mode = ?'; $params[] = $maintenanceMode; }
            if ($alertThreshold  !== null) { $fields[] = 'alert_threshold = ?';  $params[] = $alertThreshold; }
            // min_operators sparas om kolumnen finns
            if ($minOperators !== null) {
                try {
                    $fields[] = 'min_operators = ?';
                    $params[] = $minOperators;
                } catch (\Exception $ignored) {}
            }

            if (!empty($fields)) {
                $params[] = 1; // id
                $stmt = $this->pdo->prepare(
                    'UPDATE rebotling_settings SET ' . implode(', ', $fields) . ' WHERE id = ?'
                );
                $stmt->execute($params);
            }

            // Logga mål-ändringar i historiktoken
            if ($rebotlingTarget !== null) {
                $user = $_SESSION['username'] ?? ($_SESSION['user_login'] ?? 'system');
                try {
                    $this->pdo->exec("
                        CREATE TABLE IF NOT EXISTS rebotling_goal_history (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            goal_type VARCHAR(50) NOT NULL DEFAULT 'dagmal',
                            value INT NOT NULL,
                            changed_by VARCHAR(100),
                            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_type_time (goal_type, changed_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    $logStmt = $this->pdo->prepare(
                        "INSERT INTO rebotling_goal_history (goal_type, value, changed_by) VALUES ('dagmal', ?, ?)"
                    );
                    $logStmt->execute([intval($rebotlingTarget), $user]);
                } catch (Exception $logEx) {
                    error_log('Kunde inte logga mål-historik: ' . $logEx->getMessage());
                }
            }

            echo json_encode(['success' => true, 'message' => 'Inställningar sparade']);
        } catch (Exception $e) {
            error_log('Kunde inte spara inställningar: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara inställningar']);
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

    private function getProductionReport() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin-behörighet krävs']);
            return;
        }

        $period = $_GET['period'] ?? 'week';
        $format = $_GET['format'] ?? 'json';

        $days = match($period) {
            'today' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => 7
        };

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Dagliga produktionssiffror.
            // ibc_ok, ibc_ej_ok, bur_ej_ok och runtime_plc är KUMULATIVA per skift.
            // Aggregera korrekt: MAX() per skiftraknare (inner), sedan SUM() per dag (outer).
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(cykler)          AS cykler,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_bur_ej_ok) AS bur_ej_ok,
                    ROUND(AVG(avg_prod_pct), 1)  AS snitt_produktion_pct,
                    ROUND(AVG(avg_runtime_plc), 1) AS snitt_cykeltid,
                    SUM(shift_runtime)   AS kortid_minuter
                FROM (
                    SELECT
                        DATE(datum)          AS dag,
                        skiftraknare,
                        COUNT(*)             AS cykler,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(bur_ej_ok,  0)) AS shift_bur_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        AVG(produktion_procent)  AS avg_prod_pct,
                        AVG(runtime_plc)         AS avg_runtime_plc
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= ?
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag
            ");
            $stmt->execute([$startDate]);
            $daily = $stmt->fetchAll();

            // Starter/stopp-data från on/off-events
            $stmtRuntime = $this->pdo->prepare("
                SELECT
                    DATE(datum) as dag,
                    COUNT(CASE WHEN running = 1 THEN 1 END) as starter,
                    COUNT(CASE WHEN running = 0 THEN 1 END) as stopp
                FROM rebotling_onoff
                WHERE DATE(datum) >= ?
                GROUP BY DATE(datum)
                ORDER BY dag
            ");
            $stmtRuntime->execute([$startDate]);
            $runtime = $stmtRuntime->fetchAll();
            $runtimeMap = [];
            foreach ($runtime as $r) {
                $runtimeMap[$r['dag']] = $r;
            }

            // Sammanfattning
            $totalIbcOk = array_sum(array_column($daily, 'ibc_ok'));
            $totalIbcEjOk = array_sum(array_column($daily, 'ibc_ej_ok'));
            $totalCykler = array_sum(array_column($daily, 'cykler'));
            $daysWithProduction = count($daily);

            $report = [];
            foreach ($daily as $d) {
                $rt = $runtimeMap[$d['dag']] ?? null;
                $report[] = [
                    'datum' => $d['dag'],
                    'cykler' => (int)$d['cykler'],
                    'ibc_ok' => (int)$d['ibc_ok'],
                    'ibc_ej_ok' => (int)$d['ibc_ej_ok'],
                    'bur_ej_ok' => (int)$d['bur_ej_ok'],
                    'kvalitet_pct' => $d['ibc_ok'] > 0 ? round(($d['ibc_ok'] / ($d['ibc_ok'] + $d['ibc_ej_ok'])) * 100, 1) : 0,
                    'snitt_cykeltid' => (float)$d['snitt_cykeltid'],
                    'snitt_produktion_pct' => (float)$d['snitt_produktion_pct'],
                    'kortid_h' => round((float)$d['kortid_minuter'] / 60, 1),
                    'starter' => $rt ? (int)$rt['starter'] : 0,
                    'stopp' => $rt ? (int)$rt['stopp'] : 0,
                ];
            }

            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="produktionsrapport_rebotling_' . $startDate . '.csv"');
                // BOM for Excel
                echo "\xEF\xBB\xBF";
                echo "Datum;Cykler;IBC OK;IBC Ej OK;Bur Ej OK;Kvalitet %;Snitt cykeltid (min);Produktion %;Körtid (h);Starter;Stopp\n";
                foreach ($report as $r) {
                    echo implode(';', [
                        $r['datum'], $r['cykler'], $r['ibc_ok'], $r['ibc_ej_ok'],
                        $r['bur_ej_ok'], $r['kvalitet_pct'], $r['snitt_cykeltid'],
                        $r['snitt_produktion_pct'], $r['kortid_h'], $r['starter'], $r['stopp']
                    ]) . "\n";
                }
                echo "\n;Totalt;{$totalIbcOk};{$totalIbcEjOk};;;;;;;\n";
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'start_date' => $startDate,
                    'daily' => $report,
                    'summary' => [
                        'total_cykler' => $totalCykler,
                        'total_ibc_ok' => $totalIbcOk,
                        'total_ibc_ej_ok' => $totalIbcEjOk,
                        'dagar_med_produktion' => $daysWithProduction,
                        'snitt_cykler_per_dag' => $daysWithProduction > 0 ? round($totalCykler / $daysWithProduction, 1) : 0,
                        'kvalitet_pct' => ($totalIbcOk + $totalIbcEjOk) > 0 ? round(($totalIbcOk / ($totalIbcOk + $totalIbcEjOk)) * 100, 1) : 0,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            error_log('getProductionReport: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte generera rapport']);
        }
    }

    /**
     * GET ?action=rebotling&run=week-comparison
     * Returnerar IBC/dag för denna vecka (mån–idag) + förra veckan (14 dagar).
     * Används av Veckojämförelse-panelen i statistiksidan.
     */
    private function getWeekComparison() {
        try {
            $granularity = $_GET['granularity'] ?? 'day';

            if ($granularity === 'shift') {
                // Per-skift: hämta senaste 14 dagarna, varje skift = en datapunkt
                $weekdays = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        MIN(datum)            AS skift_start,
                        COUNT(*)              AS cykler,
                        MAX(COALESCE(ibc_ok,  0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt->execute();
                $shiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $allShifts = [];
                foreach ($shiftRows as $r) {
                    $ts = strtotime($r['dag']);
                    $wd = (int)date('w', $ts); // 0=Sun, 1=Mon ... 6=Sat
                    $label = $weekdays[$wd] . ' Skift ' . (int)$r['skiftraknare'];
                    $allShifts[] = [
                        'date'         => $r['dag'],
                        'label'        => $label,
                        'skiftraknare' => (int)$r['skiftraknare'],
                        'ibc_ok'       => (int)$r['shift_ibc_ok'],
                        'cykler'       => (int)$r['cykler']
                    ];
                }

                // Dela på mittpunkt 7 dagar
                $cutoff = date('Y-m-d', strtotime('-7 days'));
                $thisWeekShifts = array_values(array_filter($allShifts, fn($s) => $s['date'] >= $cutoff));
                $prevWeekShifts = array_values(array_filter($allShifts, fn($s) => $s['date'] < $cutoff));

                echo json_encode([
                    'success' => true,
                    'granularity' => 'shift',
                    'data' => [
                        'this_week' => $thisWeekShifts,
                        'prev_week' => $prevWeekShifts,
                        'all_days'  => $allShifts
                    ]
                ]);
                return;
            }

            // Hämta daglig IBC-räkning för de senaste 14 dagarna
            // ibc_ok är kumulativt per skift → MAX per skiftraknare per dag, sedan SUM per dag
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok) AS ibc_ok,
                    SUM(cykler)       AS cykler
                FROM (
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        COUNT(*)              AS cykler,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg karta dag -> data
            $map = [];
            foreach ($rows as $r) {
                $map[$r['dag']] = ['ibc_ok' => (int)$r['ibc_ok'], 'cykler' => (int)$r['cykler']];
            }

            // Generera fullständig 14-dagars lista (idag + 13 dagar bakåt)
            $days = [];
            for ($i = 13; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $days[] = [
                    'date'   => $date,
                    'ibc_ok' => $map[$date]['ibc_ok'] ?? 0,
                    'cykler' => $map[$date]['cykler'] ?? 0
                ];
            }

            // Dela upp i denna vecka (dag 7-13, index 7-13) och förra (dag 0-6, index 0-6)
            $prevWeek = array_slice($days, 0, 7);
            $thisWeek = array_slice($days, 7, 7);

            echo json_encode([
                'success'   => true,
                'data'      => [
                    'this_week' => $thisWeek,
                    'prev_week' => $prevWeek,
                    'all_days'  => $days
                ]
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getWeekComparison: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckojämförelsedata']);
        }
    }

    /**
     * GET ?action=rebotling&run=oee-trend&days=30[&granularity=shift]
     * OEE-trend senaste N dagarna (Availability, Performance, Quality, OEE per dag eller per skift).
     */
    private function getOEETrend() {
        $fromDate = $_GET['from_date'] ?? null;
        $toDate   = $_GET['to_date']   ?? null;
        if ($fromDate && $toDate) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
                return;
            }
            $oeeStart = $fromDate;
            $oeeEnd   = $toDate;
            $useDateRange = true;
        } else {
            $days = min(365, max(7, intval($_GET['days'] ?? 30)));
            $oeeStart = date('Y-m-d', strtotime("-{$days} days"));
            $oeeEnd   = date('Y-m-d');
            $useDateRange = false;
        }
        $granularity = $_GET['granularity'] ?? 'day';
        try {
            if ($granularity === 'shift') {
                // Per-skift: varje skift = en datapunkt med eget OEE-värde
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        MIN(datum)            AS skift_start,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= :oee_start AND datum <= :oee_end
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt->execute(['oee_start' => $oeeStart . ' 00:00:00', 'oee_end' => $oeeEnd . ' 23:59:59']);
                $shiftRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $idealRatePerMin = 15.0 / 60.0;
                $shiftData = [];
                foreach ($shiftRows as $r) {
                    $dagParts = explode('-', $r['dag']);
                    $label = sprintf('%s/%s Skift %d',
                        $dagParts[2], $dagParts[1], (int)$r['skiftraknare']
                    );

                    $ibcOk   = (float)$r['shift_ibc_ok'];
                    $ibcEjOk = (float)$r['shift_ibc_ej_ok'];
                    $totalIBC = $ibcOk + $ibcEjOk;
                    $opMin   = max((float)$r['shift_runtime'], 1);
                    $planMin = max($opMin + (float)$r['shift_rast'], 1);

                    $avail = min($opMin / $planMin, 1.0);
                    $perf  = min(($totalIBC / $opMin) / $idealRatePerMin, 1.0);
                    $qual  = $totalIBC > 0 ? $ibcOk / $totalIBC : 0;
                    $oee   = $avail * $perf * $qual;

                    $shiftData[] = [
                        'date'         => $r['dag'],
                        'label'        => $label,
                        'skiftraknare' => (int)$r['skiftraknare'],
                        'oee'          => round($oee   * 100, 1),
                        'availability' => round($avail  * 100, 1),
                        'performance'  => round($perf   * 100, 1),
                        'quality'      => round($qual   * 100, 1),
                        'ibc_ok'       => (int)$ibcOk
                    ];
                }

                echo json_encode(['success' => true, 'granularity' => 'shift', 'data' => $shiftData]);
                return;
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        DATE(datum)           AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= :oee_start AND datum <= :oee_end
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute(['oee_start' => $oeeStart . ' 00:00:00', 'oee_end' => $oeeEnd . ' 23:59:59']);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $idealRatePerMin = 15.0 / 60.0;
            $daily = [];
            foreach ($rows as $r) {
                $ibcOk    = (float)$r['ibc_ok'];
                $ibcEjOk  = (float)$r['ibc_ej_ok'];
                $totalIBC = $ibcOk + $ibcEjOk;
                $opMin    = max((float)$r['runtime_min'], 1);
                $planMin  = max($opMin + (float)$r['rast_min'], 1);

                $avail = min($opMin / $planMin, 1.0);
                $perf  = min(($totalIBC / $opMin) / $idealRatePerMin, 1.0);
                $qual  = $totalIBC > 0 ? $ibcOk / $totalIBC : 0;
                $oee   = $avail * $perf * $qual;

                $daily[] = [
                    'date'         => $r['dag'],
                    'oee'          => round($oee   * 100, 1),
                    'availability' => round($avail  * 100, 1),
                    'performance'  => round($perf   * 100, 1),
                    'quality'      => round($qual   * 100, 1),
                    'ibc_ok'       => (int)$ibcOk
                ];
            }

            echo json_encode(['success' => true, 'data' => $daily]);
        } catch (Exception $e) {
            error_log('RebotlingController getOEETrend: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta OEE-trend']);
        }
    }

    /**
     * GET ?action=rebotling&run=best-shifts&limit=10
     * De historiskt bästa skiften sorterade på ibc_ok DESC.
     */
    private function getBestShifts() {
        $limit = min(50, max(5, intval($_GET['limit'] ?? 10)));
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    skiftraknare,
                    DATE(MIN(datum)) AS dag,
                    COUNT(*)         AS cykler,
                    MAX(COALESCE(ibc_ok,   0)) AS ibc_ok,
                    MAX(COALESCE(ibc_ej_ok,0)) AS ibc_ej_ok,
                    MAX(COALESCE(runtime_plc,0)) AS runtime_min,
                    ROUND(AVG(NULLIF(produktion_procent,0)), 1) AS avg_kvalitet
                FROM rebotling_ibc
                WHERE skiftraknare IS NOT NULL
                  AND ibc_ok IS NOT NULL
                GROUP BY skiftraknare
                HAVING ibc_ok > 0
                ORDER BY ibc_ok DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $i => $r) {
                $ibcOk   = (int)$r['ibc_ok'];
                $ibcEjOk = (int)$r['ibc_ej_ok'];
                $total   = $ibcOk + $ibcEjOk;
                $result[] = [
                    'rank'        => $i + 1,
                    'skiftraknare' => (int)$r['skiftraknare'],
                    'dag'         => $r['dag'],
                    'cykler'      => (int)$r['cykler'],
                    'ibc_ok'      => $ibcOk,
                    'ibc_ej_ok'   => $ibcEjOk,
                    'kvalitet_pct'=> $total > 0 ? round($ibcOk / $total * 100, 1) : 0,
                    'runtime_h'   => round((float)$r['runtime_min'] / 60, 1),
                    'avg_kvalitet' => (float)$r['avg_kvalitet']
                ];
            }

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            error_log('RebotlingController getBestShifts: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta bästa skift']);
        }
    }

    /**
     * GET ?action=rebotling&run=heatmap&days=30
     * Returnerar produktionsintensitet per timme och dag som
     * { date: "YYYY-MM-DD", hour: 0-23, count: N }[]
     * Används av statistiksidans heatmap-vy.
     */
    private function getHeatmap() {
        $fromDate = $_GET['from_date'] ?? null;
        $toDate   = $_GET['to_date']   ?? null;

        if ($fromDate && $toDate) {
            // Validera datumformat
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
                return;
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

    private function ensureWeekdayGoalsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_weekday_goals` (
                `id`          INT         NOT NULL AUTO_INCREMENT,
                `weekday`     TINYINT     NOT NULL COMMENT '1=Måndag ... 7=Söndag (ISO)',
                `daily_goal`  INT         NOT NULL DEFAULT 1000,
                `label`       VARCHAR(20) NOT NULL DEFAULT '',
                `updated_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_weekday` (`weekday`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Standardvärden
        $defaults = [
            [1, 900,  'Måndag'],
            [2, 1000, 'Tisdag'],
            [3, 1000, 'Onsdag'],
            [4, 1000, 'Torsdag'],
            [5, 950,  'Fredag'],
            [6, 0,    'Lördag'],
            [7, 0,    'Söndag'],
        ];
        foreach ($defaults as [$wd, $goal, $lbl]) {
            $this->pdo->exec("INSERT IGNORE INTO rebotling_weekday_goals (weekday, daily_goal, label) VALUES ($wd, $goal, '$lbl')");
        }
    }

    private function getWeekdayGoals() {
        try {
            $this->ensureWeekdayGoalsTable();
            $rows = $this->pdo->query("SELECT weekday, daily_goal, label FROM rebotling_weekday_goals ORDER BY weekday")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            error_log('getWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodagsmål']);
        }
    }

    private function saveWeekdayGoals() {
        $data = json_decode(file_get_contents('php://input'), true);
        $goals = $data['goals'] ?? [];
        if (!is_array($goals)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data']);
            return;
        }
        try {
            $this->ensureWeekdayGoalsTable();
            $stmt = $this->pdo->prepare("UPDATE rebotling_weekday_goals SET daily_goal = ? WHERE weekday = ?");
            foreach ($goals as $item) {
                $wd   = intval($item['weekday'] ?? 0);
                $goal = max(0, intval($item['daily_goal'] ?? 0));
                if ($wd >= 1 && $wd <= 7) {
                    $stmt->execute([$goal, $wd]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Veckodagsmål sparade']);
        } catch (Exception $e) {
            error_log('saveWeekdayGoals: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara veckodagsmål']);
        }
    }

    // =========================================================
    // Alert-trösklar
    // =========================================================

    /**
     * Säkerställ att alert_thresholds-kolumnen finns i rebotling_settings.
     * Kolumnen lagrar ett JSON-objekt med tröskelvärden.
     */
    private function ensureAlertThresholdsColumn() {
        try {
            $this->ensureSettingsTable();
            // Kontrollera om kolumnen finns
            $col = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'alert_thresholds'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN alert_thresholds TEXT NULL DEFAULT NULL"
                );
            }
        } catch (Exception $e) {
            error_log('ensureAlertThresholdsColumn: ' . $e->getMessage());
        }
    }

    private function defaultAlertThresholds(): array {
        return [
            'oee_warn'     => 80,
            'oee_danger'   => 70,
            'prod_warn'    => 80,
            'prod_danger'  => 60,
            'plc_max_min'  => 15,
            'quality_warn' => 95,
        ];
    }

    private function getAlertThresholds() {
        try {
            $this->ensureAlertThresholdsColumn();
            $row = $this->pdo->query(
                "SELECT alert_thresholds FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            $defaults   = $this->defaultAlertThresholds();
            $thresholds = $defaults;

            if ($row && !empty($row['alert_thresholds'])) {
                $saved = json_decode($row['alert_thresholds'], true);
                if (is_array($saved)) {
                    $thresholds = array_merge($defaults, $saved);
                }
            }

            echo json_encode(['success' => true, 'data' => $thresholds]);
        } catch (Exception $e) {
            error_log('getAlertThresholds: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta alert-trösklar']);
        }
    }

    private function saveAlertThresholds() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data']);
            return;
        }
        try {
            $this->ensureAlertThresholdsColumn();

            $allowed = array_keys($this->defaultAlertThresholds());
            $cleaned = [];
            foreach ($allowed as $key) {
                if (isset($data[$key])) {
                    $cleaned[$key] = max(0, intval($data[$key]));
                }
            }

            $json = json_encode($cleaned);
            $stmt = $this->pdo->prepare(
                "UPDATE rebotling_settings SET alert_thresholds = ? WHERE id = 1"
            );
            $stmt->execute([$json]);

            echo json_encode(['success' => true, 'message' => 'Trösklar sparade']);
        } catch (Exception $e) {
            error_log('saveAlertThresholds: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara trösklar']);
        }
    }

    // =========================================================
    // Snabb produktionsöversikt — idag
    // =========================================================

    private function getTodaySnapshot() {
        try {
            $tz  = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // IBC idag
            $ibcToday = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()"
            )->fetchColumn();

            // Dagsmål — försök veckodagsmål annars settings
            $dailyTarget = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dailyTarget = (int)$sr['rebotling_target'];

                // Veckodagsmål: ISO weekday 1=Måndag
                $iso = (int)$now->format('N');
                try {
                    $wg = $this->pdo->prepare(
                        "SELECT daily_goal FROM rebotling_weekday_goals WHERE weekday = ?"
                    );
                    $wg->execute([$iso]);
                    $wgRow = $wg->fetch(PDO::FETCH_ASSOC);
                    if ($wgRow && (int)$wgRow['daily_goal'] > 0) {
                        $dailyTarget = (int)$wgRow['daily_goal'];
                    }
                } catch (Exception $e) { /* tabell saknas */ }

                // Kolla om undantag finns för idag
                try {
                    $stmtEx = $this->pdo->prepare('SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE()');
                    $stmtEx->execute();
                    $exceptionRow = $stmtEx->fetch(PDO::FETCH_ASSOC);
                    if ($exceptionRow) {
                        $dailyTarget = (int)$exceptionRow['justerat_mal'];
                    }
                } catch (Exception $e) { /* tabell saknas ännu — ignorera */ }
            } catch (Exception $e) { /* ignorera */ }

            // Linjen kör?
            $isRunning = false;
            try {
                $row = $this->pdo->query(
                    "SELECT running FROM rebotling_onoff ORDER BY datum DESC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                $isRunning = $row ? (bool)$row['running'] : false;
            } catch (Exception $e) { /* ignorera */ }

            // Takt: IBC per timme baserat på produktion senaste 2 timmar
            $ratePerHour = 0.0;
            try {
                $cnt = (int)$this->pdo->query(
                    "SELECT COUNT(*) FROM rebotling_ibc
                     WHERE datum >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
                )->fetchColumn();
                $ratePerHour = round($cnt / 2.0, 1);
            } catch (Exception $e) { /* ignorera */ }

            // Skiftlängd från settings
            $shiftHours = 8.0;
            try {
                $sh = $this->pdo->query(
                    "SELECT shift_hours FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sh) $shiftHours = (float)$sh['shift_hours'];
            } catch (Exception $e) { /* ignorera */ }

            // Prognos: skiftstart 06:00 lokal tid
            $shiftStart = new DateTime($now->format('Y-m-d') . ' 06:00:00', $tz);
            $minutesSinceStart = max(1, ($now->getTimestamp() - $shiftStart->getTimestamp()) / 60);
            $remainingMin = max(0, ($shiftHours * 60) - $minutesSinceStart);
            $forecast = (int)round($ibcToday + ($ratePerHour / 60.0) * $remainingMin);

            $pct = $dailyTarget > 0 ? round($ibcToday / $dailyTarget * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => [
                    'ibc_today'    => $ibcToday,
                    'daily_target' => $dailyTarget,
                    'pct_of_goal'  => $pct,
                    'rate_per_h'   => $ratePerHour,
                    'forecast'     => $forecast,
                    'is_running'   => $isRunning,
                    'server_time'  => $now->format('H:i:s'),
                ]
            ]);
        } catch (Exception $e) {
            error_log('getTodaySnapshot: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta dagens snapshot']);
        }
    }

    // =========================================================
    // Skifttider
    // =========================================================

    private function ensureShiftTimesTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `rebotling_shift_times` (
                `id`         INT         NOT NULL AUTO_INCREMENT,
                `shift_name` VARCHAR(50) NOT NULL,
                `start_time` TIME        NOT NULL DEFAULT '06:00:00',
                `end_time`   TIME        NOT NULL DEFAULT '14:00:00',
                `enabled`    TINYINT(1)  NOT NULL DEFAULT 1,
                `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_shift_name` (`shift_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $defaults = [
            ['förmiddag',   '06:00:00', '14:00:00', 1],
            ['eftermiddag', '14:00:00', '22:00:00', 1],
            ['natt',        '22:00:00', '06:00:00', 0],
        ];
        foreach ($defaults as [$name, $start, $end, $enabled]) {
            $this->pdo->exec("INSERT IGNORE INTO rebotling_shift_times (shift_name, start_time, end_time, enabled) VALUES ('$name', '$start', '$end', $enabled)");
        }
    }

    private function getShiftTimes() {
        try {
            $this->ensureShiftTimesTable();
            $rows = $this->pdo->query("SELECT shift_name, start_time, end_time, enabled FROM rebotling_shift_times ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            error_log('getShiftTimes: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skifttider']);
        }
    }

    private function saveShiftTimes() {
        $data = json_decode(file_get_contents('php://input'), true);
        $shifts = $data['shifts'] ?? [];
        if (!is_array($shifts)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data']);
            return;
        }
        try {
            $this->ensureShiftTimesTable();
            $stmt = $this->pdo->prepare("UPDATE rebotling_shift_times SET start_time = ?, end_time = ?, enabled = ? WHERE shift_name = ?");
            foreach ($shifts as $s) {
                $name    = $s['shift_name'] ?? '';
                $start   = $s['start_time'] ?? '06:00:00';
                $end     = $s['end_time']   ?? '14:00:00';
                $enabled = isset($s['enabled']) ? ($s['enabled'] ? 1 : 0) : 1;
                // Validera tidsformat
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start)) continue;
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end))   continue;
                if (in_array($name, ['förmiddag', 'eftermiddag', 'natt'])) {
                    $stmt->execute([$start, $end, $enabled, $name]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Skifttider sparade']);
        } catch (Exception $e) {
            error_log('saveShiftTimes: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara skifttider']);
        }
    }

    // =========================================================
    // Executive Dashboard — samlad endpoint för VD-vyn
    // =========================================================

    /**
     * GET ?action=rebotling&run=exec-dashboard
     *
     * Returnerar allt som executive dashboard behöver i ett anrop:
     *   - today: ibcToday, ibcTarget, pct, prognos, oee (idag vs igår)
     *   - week: total IBC denna vecka, förra veckan, diff, snitt kvalitet%, snitt OEE%, bästa operatör
     *   - days7: IBC per dag senaste 7 dagarna + dagsmål per rad
     *   - lastShiftOperators: aktiva operatörer senaste skiftet (namn, ibc/h, kvalitet%, bonus)
     */
    private function getExecDashboard() {
        try {
            $tz  = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // ---- Dagsmål (från rebotling_settings) ----
            $dailyTarget = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dailyTarget = (int)$sr['rebotling_target'];
            } catch (Exception $e) { /* ignorera */ }

            // Kolla om undantag finns för idag (getExecDashboard)
            try {
                $stmtEx = $this->pdo->prepare('SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE()');
                $stmtEx->execute();
                $exceptionRow = $stmtEx->fetch(PDO::FETCH_ASSOC);
                if ($exceptionRow) {
                    $dailyTarget = (int)$exceptionRow['justerat_mal'];
                }
            } catch (Exception $e) { /* tabell saknas ännu — ignorera */ }

            // ---- IBC idag ----
            $ibcToday = (int)$this->pdo->query("SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()")->fetchColumn();

            // ---- Skiftstart (används för prognos). Standard 06:00 ----
            $shiftStart = '06:00:00';
            try {
                $this->ensureShiftTimesTable();
                $st = $this->pdo->query("SELECT start_time FROM rebotling_shift_times WHERE shift_name='förmiddag' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($st) $shiftStart = $st['start_time'];
            } catch (Exception $e) { /* ignorera */ }

            $shiftStartDt = new DateTime($now->format('Y-m-d') . ' ' . $shiftStart, $tz);
            if ($shiftStartDt > $now) {
                // Kan hända om skiftet inte startat — räkna ändå från 06:00
                $shiftStartDt->modify('-1 day');
            }
            $minutesSinceShiftStart = max(1, ($now->getTimestamp() - $shiftStartDt->getTimestamp()) / 60);

            // Prognos: om vi producerat X IBC på Y minuter, hur många till skiftets slut (480 min)?
            $shiftLengthMin = 480;
            try {
                $st2 = $this->pdo->query("SELECT shift_hours FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($st2) $shiftLengthMin = (float)$st2['shift_hours'] * 60;
            } catch (Exception $e) { /* ignorera */ }

            $rate = $minutesSinceShiftStart > 0 ? $ibcToday / $minutesSinceShiftStart : 0;
            $remainingMin = max(0, $shiftLengthMin - $minutesSinceShiftStart);
            $forecast = (int)round($ibcToday + $rate * $remainingMin);

            // ---- OEE idag ----
            $oeeToday = 0;
            try {
                $oRow = $this->pdo->query("
                    SELECT
                        SUM(shift_ibc_ok)    AS ibc_ok,
                        SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                        SUM(shift_runtime)   AS runtime_min,
                        SUM(shift_rast)      AS rast_min
                    FROM (
                        SELECT
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                            MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                            MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                            MAX(COALESCE(rasttime,   0)) AS shift_rast
                        FROM rebotling_ibc
                        WHERE DATE(datum) = CURDATE()
                          AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY skiftraknare
                    ) AS ps
                ")->fetch(PDO::FETCH_ASSOC);
                if ($oRow && $oRow['runtime_min'] > 0) {
                    $ibcOk   = (float)$oRow['ibc_ok'];
                    $ibcEjOk = (float)$oRow['ibc_ej_ok'];
                    $total   = $ibcOk + $ibcEjOk;
                    $opMin   = max((float)$oRow['runtime_min'], 1);
                    $planMin = max($opMin + (float)$oRow['rast_min'], 1);
                    $avail   = min($opMin / $planMin, 1.0);
                    $perf    = min(($total / $opMin) / (15.0 / 60.0), 1.0);
                    $qual    = $total > 0 ? $ibcOk / $total : 0;
                    $oeeToday = round($avail * $perf * $qual * 100, 1);
                }
            } catch (Exception $e) { error_log('exec-dashboard OEE today: ' . $e->getMessage()); }

            // ---- OEE igår ----
            $oeeYesterday = 0;
            try {
                $oRow2 = $this->pdo->query("
                    SELECT
                        SUM(shift_ibc_ok)    AS ibc_ok,
                        SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                        SUM(shift_runtime)   AS runtime_min,
                        SUM(shift_rast)      AS rast_min
                    FROM (
                        SELECT
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                            MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                            MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                            MAX(COALESCE(rasttime,   0)) AS shift_rast
                        FROM rebotling_ibc
                        WHERE DATE(datum) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                          AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                        GROUP BY skiftraknare
                    ) AS ps
                ")->fetch(PDO::FETCH_ASSOC);
                if ($oRow2 && $oRow2['runtime_min'] > 0) {
                    $ibcOk2   = (float)$oRow2['ibc_ok'];
                    $ibcEjOk2 = (float)$oRow2['ibc_ej_ok'];
                    $total2   = $ibcOk2 + $ibcEjOk2;
                    $opMin2   = max((float)$oRow2['runtime_min'], 1);
                    $planMin2 = max($opMin2 + (float)$oRow2['rast_min'], 1);
                    $avail2   = min($opMin2 / $planMin2, 1.0);
                    $perf2    = min(($total2 / $opMin2) / (15.0 / 60.0), 1.0);
                    $qual2    = $total2 > 0 ? $ibcOk2 / $total2 : 0;
                    $oeeYesterday = round($avail2 * $perf2 * $qual2 * 100, 1);
                }
            } catch (Exception $e) { error_log('exec-dashboard OEE yesterday: ' . $e->getMessage()); }

            // ---- Senaste 7 dagarna (IBC/dag) ----
            $stmt7 = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok) AS ibc_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt7->execute();
            $rows7 = $stmt7->fetchAll(PDO::FETCH_ASSOC);

            // Fyll i tomma dagar (inga produktionsdagar ger 0)
            $map7 = [];
            foreach ($rows7 as $r) { $map7[$r['dag']] = (int)$r['ibc_ok']; }
            $days7 = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $days7[] = ['date' => $d, 'ibc' => $map7[$d] ?? 0, 'target' => $dailyTarget];
            }

            // ---- Veckototaler (mon–idag vs förra veckan mån–sön) ----
            // ISO vecka: måndag = weekday 1
            $stmt14 = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(shift_ibc_ok) AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt14->execute();
            $rows14 = $stmt14->fetchAll(PDO::FETCH_ASSOC);

            $map14 = [];
            foreach ($rows14 as $r) { $map14[$r['dag']] = ['ibc' => (int)$r['ibc_ok'], 'ej' => (int)$r['ibc_ej_ok']]; }

            $thisWeekIbc = 0; $prevWeekIbc = 0;
            $thisWeekOkSum = 0; $thisWeekEjSum = 0;
            for ($i = 13; $i >= 7; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $prevWeekIbc += $map14[$d]['ibc'] ?? 0;
            }
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $thisWeekIbc  += $map14[$d]['ibc'] ?? 0;
                $thisWeekOkSum += $map14[$d]['ibc'] ?? 0;
                $thisWeekEjSum += $map14[$d]['ej']  ?? 0;
            }
            $weekDiff = $prevWeekIbc > 0 ? round((($thisWeekIbc - $prevWeekIbc) / $prevWeekIbc) * 100, 1) : 0;
            $thisWeekQuality = ($thisWeekOkSum + $thisWeekEjSum) > 0
                ? round($thisWeekOkSum / ($thisWeekOkSum + $thisWeekEjSum) * 100, 1)
                : 0;

            // ---- OEE denna vecka (snitt per dag) ----
            $weekOeeRows = $this->pdo->query("
                SELECT
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                      AND skiftraknare IS NOT NULL AND ibc_ok IS NOT NULL
                    GROUP BY skiftraknare
                ) AS ps
            ")->fetch(PDO::FETCH_ASSOC);
            $weekOee = 0;
            if ($weekOeeRows && $weekOeeRows['runtime_min'] > 0) {
                $wOk   = (float)$weekOeeRows['ibc_ok'];
                $wEj   = (float)$weekOeeRows['ibc_ej_ok'];
                $wTot  = $wOk + $wEj;
                $wOp   = max((float)$weekOeeRows['runtime_min'], 1);
                $wPlan = max($wOp + (float)$weekOeeRows['rast_min'], 1);
                $wA    = min($wOp / $wPlan, 1.0);
                $wP    = min(($wTot / $wOp) / (15.0 / 60.0), 1.0);
                $wQ    = $wTot > 0 ? $wOk / $wTot : 0;
                $weekOee = round($wA * $wP * $wQ * 100, 1);
            }

            // ---- Bästa operatör denna vecka (IBC/h, position 1 = tvättplats) ----
            $bestOperator = null;
            try {
                $boStmt = $this->pdo->query("
                    SELECT
                        op1 AS operator_id,
                        SUM(shift_ibc_ok) AS ibc_ok,
                        SUM(shift_runtime) AS runtime_min
                    FROM (
                        SELECT
                            op1,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                            MAX(COALESCE(runtime_plc,0)) AS shift_runtime
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                          AND op1 IS NOT NULL AND op1 > 0
                          AND skiftraknare IS NOT NULL
                        GROUP BY op1, skiftraknare
                    ) AS ps
                    GROUP BY op1
                    HAVING runtime_min > 0
                    ORDER BY (ibc_ok * 60.0 / runtime_min) DESC
                    LIMIT 1
                ");
                $boRow = $boStmt->fetch(PDO::FETCH_ASSOC);
                if ($boRow) {
                    $opId = (int)$boRow['operator_id'];
                    $ibcH = $boRow['runtime_min'] > 0
                        ? round($boRow['ibc_ok'] * 60.0 / $boRow['runtime_min'], 1)
                        : 0;
                    // Hämta namn från users-tabellen
                    $nameRow = null;
                    try {
                        $ns = $this->pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
                        $ns->execute([$opId]);
                        $nameRow = $ns->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) { /* ignorera */ }
                    $bestOperator = [
                        'id'    => $opId,
                        'name'  => $nameRow ? ($nameRow['name'] ?? 'Okänd') : 'Op #' . $opId,
                        'ibc_h' => $ibcH
                    ];
                }
            } catch (Exception $e) { error_log('exec-dashboard bestOp: ' . $e->getMessage()); }

            // ---- Aktiva operatörer senaste skiftet ----
            $lastShiftOps = [];
            try {
                // Hitta senaste skiftraknare
                $lastShiftRow = $this->pdo->query("
                    SELECT skiftraknare FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                    ORDER BY datum DESC LIMIT 1
                ")->fetch(PDO::FETCH_ASSOC);

                if ($lastShiftRow) {
                    $lastShift = (int)$lastShiftRow['skiftraknare'];
                    // Hämta alla operatörer i skiftet (pos 1,2,3)
                    $opRows = $this->pdo->prepare("
                        SELECT
                            pos,
                            operator_id,
                            MAX(ibc_ok)      AS ibc_ok,
                            MAX(ibc_ej_ok)   AS ibc_ej_ok,
                            MAX(runtime_plc) AS runtime_min,
                            SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'),'|',1)+0 AS bonus
                        FROM (
                            SELECT 'op1' AS pos, op1 AS operator_id, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, datum
                            FROM rebotling_ibc
                            WHERE skiftraknare = ? AND op1 IS NOT NULL AND op1 > 0
                            UNION ALL
                            SELECT 'op2', op2, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, datum
                            FROM rebotling_ibc
                            WHERE skiftraknare = ? AND op2 IS NOT NULL AND op2 > 0
                            UNION ALL
                            SELECT 'op3', op3, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, datum
                            FROM rebotling_ibc
                            WHERE skiftraknare = ? AND op3 IS NOT NULL AND op3 > 0
                        ) AS all_ops
                        GROUP BY pos, operator_id
                    ");
                    $opRows->execute([$lastShift, $lastShift, $lastShift]);
                    $opData = $opRows->fetchAll(PDO::FETCH_ASSOC);

                    // Hämta namn för alla operatörer
                    $opIds = array_unique(array_column($opData, 'operator_id'));
                    $nameMap = [];
                    if (!empty($opIds)) {
                        $placeholders = implode(',', array_fill(0, count($opIds), '?'));
                        $ns2 = $this->pdo->prepare("SELECT id, name FROM users WHERE id IN ($placeholders)");
                        $ns2->execute($opIds);
                        foreach ($ns2->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                            $nameMap[(int)$nr['id']] = $nr['name'] ?? 'Okänd';
                        }
                    }

                    $posLabels = ['op1' => 'Tvätt', 'op2' => 'Kontroll', 'op3' => 'Truck'];
                    foreach ($opData as $op) {
                        $opId  = (int)$op['operator_id'];
                        $ok    = (float)$op['ibc_ok'];
                        $ej    = (float)$op['ibc_ej_ok'];
                        $rtMin = max((float)$op['runtime_min'], 1);
                        $ibcH  = round($ok * 60.0 / $rtMin, 1);
                        $qual  = ($ok + $ej) > 0 ? round($ok / ($ok + $ej) * 100, 1) : 0;
                        $lastShiftOps[] = [
                            'id'       => $opId,
                            'name'     => $nameMap[$opId] ?? 'Op #' . $opId,
                            'position' => $posLabels[$op['pos']] ?? $op['pos'],
                            'ibc_h'    => $ibcH,
                            'kvalitet' => $qual,
                            'bonus'    => round((float)$op['bonus'], 1)
                        ];
                    }
                }
            } catch (Exception $e) { error_log('exec-dashboard lastShiftOps: ' . $e->getMessage()); }

            // Produktionsprocent idag
            $pct = $dailyTarget > 0 ? round($ibcToday / $dailyTarget * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => [
                    'today' => [
                        'ibc'         => $ibcToday,
                        'target'      => $dailyTarget,
                        'pct'         => $pct,
                        'forecast'    => $forecast,
                        'oee_today'   => $oeeToday,
                        'oee_yesterday' => $oeeYesterday,
                        'rate_per_h'  => round($rate * 60, 1),
                        'shift_start' => $shiftStart
                    ],
                    'week' => [
                        'this_week_ibc'  => $thisWeekIbc,
                        'prev_week_ibc'  => $prevWeekIbc,
                        'week_diff_pct'  => $weekDiff,
                        'quality_pct'    => $thisWeekQuality,
                        'oee_pct'        => $weekOee,
                        'best_operator'  => $bestOperator
                    ],
                    'days7'              => $days7,
                    'last_shift_operators' => $lastShiftOps
                ]
            ]);
        } catch (Exception $e) {
            error_log('getExecDashboard: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta executive dashboard-data']);
        }
    }

    // =========================================================
    // Systemstatus
    // =========================================================

    private function getSystemStatus() {
        try {
            // Senaste PLC-ping (senaste raden i rebotling_ibc eller rebotling_onoff)
            $lastPlcPing  = null;
            $lastLopnummer = null;
            try {
                $row = $this->pdo->query("SELECT MAX(datum) as last_ping FROM rebotling_ibc")->fetch(PDO::FETCH_ASSOC);
                $lastPlcPing = $row ? $row['last_ping'] : null;
            } catch (Exception $e) { /* ignorera */ }

            try {
                $row = $this->pdo->query("SELECT lopnummer FROM rebotling_lopnummer_current WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $lastLopnummer = $row ? (int)$row['lopnummer'] : null;
            } catch (Exception $e) { /* ignorera */ }

            // Databas OK
            $dbOk = true;
            try {
                $this->pdo->query("SELECT 1");
            } catch (Exception $e) {
                $dbOk = false;
            }

            // Räkna skiftrapporter idag
            $reportsToday = 0;
            try {
                $row = $this->pdo->query("SELECT COUNT(*) FROM rebotling_skiftrapport WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                $reportsToday = (int)$row;
            } catch (Exception $e) { /* ignorera */ }

            // Totalt IBC idag från PLC
            $ibcToday = 0;
            try {
                $row = $this->pdo->query("SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()")->fetchColumn();
                $ibcToday = (int)$row;
            } catch (Exception $e) { /* ignorera */ }

            echo json_encode([
                'success' => true,
                'data' => [
                    'last_plc_ping'   => $lastPlcPing,
                    'last_lopnummer'  => $lastLopnummer,
                    'db_ok'           => $dbOk,
                    'reports_today'   => $reportsToday,
                    'ibc_today'       => $ibcToday,
                    'server_time'     => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (Exception $e) {
            error_log('getSystemStatus: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta systemstatus']);
        }
    }

    /**
     * Jämför aggregerad skiftdata för två datum.
     * GET ?action=rebotling&run=shift-compare&date_a=YYYY-MM-DD&date_b=YYYY-MM-DD
     */
    private function getShiftCompare() {
        $date_a = $_GET['date_a'] ?? '';
        $date_b = $_GET['date_b'] ?? '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_a) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_b)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
            return;
        }

        try {
            $result = [];
            foreach (['a' => $date_a, 'b' => $date_b] as $key => $date) {
                // Aggregerad sammanfattning per dag (summera alla rader för datumet)
                $stmt = $this->pdo->prepare("
                    SELECT
                        SUM(s.ibc_ok)    AS ibc_ok,
                        SUM(s.bur_ej_ok) AS bur_ej_ok,
                        SUM(s.ibc_ej_ok) AS ibc_ej_ok,
                        SUM(s.totalt)    AS totalt,
                        SUM(s.drifttid)  AS drifttid,
                        SUM(s.rasttime)  AS rasttime
                    FROM rebotling_skiftrapport s
                    WHERE s.datum = :date
                ");
                $stmt->execute(['date' => $date]);
                $agg = $stmt->fetch(PDO::FETCH_ASSOC);

                // Beräkna KPI:er
                $totalt   = (int)($agg['totalt']   ?? 0);
                $ibc_ok   = (int)($agg['ibc_ok']   ?? 0);
                $drifttid = (int)($agg['drifttid']  ?? 0);
                $rasttime = (int)($agg['rasttime']  ?? 0);

                $kvalitet = ($totalt > 0)
                    ? round(($ibc_ok / $totalt) * 100, 1)
                    : null;

                $planned = $drifttid + $rasttime;
                $avail   = ($planned > 0)
                    ? min($drifttid / $planned, 1)
                    : null;
                $quality_ratio = ($totalt > 0) ? ($ibc_ok / $totalt) : null;
                $oee = ($avail !== null && $quality_ratio !== null)
                    ? round($avail * $quality_ratio * 100, 1)
                    : null;

                $ibc_per_h = ($drifttid > 0)
                    ? round(($ibc_ok / ($drifttid / 60)), 1)
                    : null;

                // Operatörer som jobbade denna dag (från skiftrapporter)
                $opStmt = $this->pdo->prepare("
                    SELECT
                        u.username AS user_name,
                        SUM(s.ibc_ok)  AS ibc_ok,
                        SUM(s.totalt)  AS totalt,
                        SUM(s.drifttid) AS drifttid,
                        o1.name AS op1_name,
                        o2.name AS op2_name,
                        o3.name AS op3_name
                    FROM rebotling_skiftrapport s
                    LEFT JOIN users     u  ON s.user_id = u.id
                    LEFT JOIN operators o1 ON o1.number = s.op1
                    LEFT JOIN operators o2 ON o2.number = s.op2
                    LEFT JOIN operators o3 ON o3.number = s.op3
                    WHERE s.datum = :date
                    GROUP BY s.user_id, u.username, o1.name, o2.name, o3.name
                    ORDER BY ibc_ok DESC
                ");
                $opStmt->execute(['date' => $date]);
                $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

                // Lägg till IBC/h per operatör
                foreach ($operators as &$op) {
                    $op_drift = (int)($op['drifttid'] ?? 0);
                    $op_ibc   = (int)($op['ibc_ok']   ?? 0);
                    $op_tot   = (int)($op['totalt']    ?? 0);
                    $op['ibc_per_h'] = ($op_drift > 0)
                        ? round(($op_ibc / ($op_drift / 60)), 1)
                        : null;
                    $op['kvalitet'] = ($op_tot > 0)
                        ? round(($op_ibc / $op_tot) * 100, 1)
                        : null;
                }
                unset($op);

                $result[$key] = [
                    'date'      => $date,
                    'totalt'    => $totalt,
                    'ibc_ok'    => $ibc_ok,
                    'bur_ej_ok' => (int)($agg['bur_ej_ok'] ?? 0),
                    'ibc_ej_ok' => (int)($agg['ibc_ej_ok'] ?? 0),
                    'kvalitet'  => $kvalitet,
                    'oee'       => $oee,
                    'drifttid'  => $drifttid,
                    'rasttime'  => $rasttime,
                    'ibc_per_h' => $ibc_per_h,
                    'operators' => $operators,
                    'has_data'  => $totalt > 0,
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $result
            ]);
        } catch (Exception $e) {
            error_log('getShiftCompare: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte jämföra skift']);
        }
    }

    // =========================================================
    // Live Ranking — TV-skärm på fabriksgolvet
    // GET ?action=rebotling&run=live-ranking  (ingen auth krävs)
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
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta live ranking']);
        }
    }

    // =========================================================
    // Cykeltids-histogram
    // GET ?action=rebotling&run=cycle-histogram&date=YYYY-MM-DD
    // =========================================================
    private function getCycleHistogram() {
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        try {
            // Hämta ibc_ok och drifttid per skift för valt datum från rebotling_skiftrapport
            $stmt = $this->pdo->prepare("
                SELECT ibc_ok, drifttid
                FROM rebotling_skiftrapport
                WHERE datum = :date
                  AND ibc_ok > 0
                  AND drifttid > 0
            ");
            $stmt->execute(['date' => $date]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna cykeltid (min/IBC) per skift
            $cycleTimes = [];
            foreach ($shifts as $s) {
                $ibcOk    = (int)$s['ibc_ok'];
                $driftMin = (float)$s['drifttid'];
                if ($ibcOk > 0 && $driftMin > 0) {
                    $cycleTimes[] = $driftMin / $ibcOk;
                }
            }

            // Om inga skiftrapporter finns: hämta cykeltider per cykel från PLC-data
            if (empty($cycleTimes)) {
                $stmt2 = $this->pdo->prepare("
                    SELECT
                        skiftraknare,
                        datum,
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) / 60.0 AS cycle_time_min
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                      AND skiftraknare IS NOT NULL
                    ORDER BY skiftraknare, datum ASC
                ");
                $stmt2->execute(['date' => $date]);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows2 as $r) {
                    $ct = (float)($r['cycle_time_min'] ?? 0);
                    // Filtrera: 0.5 – 30 min
                    if ($ct >= 0.5 && $ct <= 30) {
                        $cycleTimes[] = $ct;
                    }
                }
            }

            // Bygg histogrambuckets
            $buckets = [
                '0-2 min'  => 0,
                '2-3 min'  => 0,
                '3-4 min'  => 0,
                '4-5 min'  => 0,
                '5-7 min'  => 0,
                '7+ min'   => 0,
            ];
            foreach ($cycleTimes as $ct) {
                if ($ct < 2)      $buckets['0-2 min']++;
                elseif ($ct < 3)  $buckets['2-3 min']++;
                elseif ($ct < 4)  $buckets['3-4 min']++;
                elseif ($ct < 5)  $buckets['4-5 min']++;
                elseif ($ct < 7)  $buckets['5-7 min']++;
                else              $buckets['7+ min']++;
            }

            // Statistik
            $n      = count($cycleTimes);
            $snitt  = $n > 0 ? array_sum($cycleTimes) / $n : 0;
            $p50 = $p90 = $p95 = 0;
            if ($n > 0) {
                sort($cycleTimes);
                $p50 = $cycleTimes[(int)floor(($n - 1) * 0.50)];
                $p90 = $cycleTimes[(int)floor(($n - 1) * 0.90)];
                $p95 = $cycleTimes[(int)floor(($n - 1) * 0.95)];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'date'    => $date,
                    'buckets' => array_map(function($label, $count) {
                        return ['label' => $label, 'count' => $count];
                    }, array_keys($buckets), array_values($buckets)),
                    'stats' => [
                        'n'      => $n,
                        'snitt'  => round($snitt, 2),
                        'p50'    => round($p50, 2),
                        'p90'    => round($p90, 2),
                        'p95'    => round($p95, 2),
                    ]
                ]
            ]);
        } catch (Exception $e) {
            error_log('getCycleHistogram: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta cykeltidsfordelning']);
        }
    }

    // =========================================================
    // SPC-kontrollkort
    // GET ?action=rebotling&run=spc&days=7
    // =========================================================
    private function getSPC() {
        $days = min(30, max(3, intval($_GET['days'] ?? 7)));

        try {
            // Hämta IBC/h per skift de senaste N dagarna från rebotling_skiftrapport
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    skift_nr,
                    ibc_ok,
                    drifttid
                FROM rebotling_skiftrapport
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  AND ibc_ok > 0
                  AND drifttid > 0
                ORDER BY datum ASC, skift_nr ASC
            ");
            $stmt->execute(['days' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $points = [];
            foreach ($rows as $r) {
                $ibcOk    = (int)$r['ibc_ok'];
                $driftMin = (float)$r['drifttid'];
                if ($driftMin > 0) {
                    $ibcPerH = round($ibcOk * 60.0 / $driftMin, 2);
                    $points[] = [
                        'label'        => $r['datum'] . ' S' . $r['skift_nr'],
                        'ibc_per_hour' => $ibcPerH,
                    ];
                }
            }

            // Fallback: PLC-data aggregerat per dag+skift om inga skiftrapporter
            if (empty($points)) {
                $stmt2 = $this->pdo->prepare("
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,    0)) AS shift_ibc_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                    HAVING shift_runtime > 0 AND shift_ibc_ok > 0
                    ORDER BY dag ASC, skiftraknare ASC
                ");
                $stmt2->execute(['days' => $days]);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows2 as $r) {
                    $ibcPerH = round($r['shift_ibc_ok'] * 60.0 / $r['shift_runtime'], 2);
                    $points[] = [
                        'label'        => $r['dag'] . ' #' . $r['skiftraknare'],
                        'ibc_per_hour' => $ibcPerH,
                    ];
                }
            }

            // Beräkna medelvärde (X̄) och standardavvikelse (σ)
            $n      = count($points);
            $mean   = 0;
            $stddev = 0;
            if ($n > 0) {
                $values = array_column($points, 'ibc_per_hour');
                $mean   = array_sum($values) / $n;
                if ($n > 1) {
                    $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($n - 1);
                    $stddev   = sqrt($variance);
                }
            }

            $ucl = round($mean + 2 * $stddev, 2);
            $lcl = round(max(0, $mean - 2 * $stddev), 2);

            echo json_encode([
                'success' => true,
                'data' => [
                    'points' => $points,
                    'mean'   => round($mean, 2),
                    'stddev' => round($stddev, 2),
                    'ucl'    => $ucl,
                    'lcl'    => $lcl,
                    'n'      => $n,
                    'days'   => $days,
                ]
            ]);
        } catch (Exception $e) {
            error_log('getSPC: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta SPC-data']);
        }
    }


    // =========================================================
    // Cykeltid per operatör
    // GET ?action=rebotling&run=cycle-by-operator&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
    // =========================================================
    private function getCycleByOperator() {
        $today = date('Y-m-d');
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days'));
        $endDate   = $_GET['end_date']   ?? $today;

        // Validera datumformat
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d', strtotime('-29 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $today;
        }

        try {
            // Hämta alla enskilda skift-rader per operatör för att kunna beräkna median och P90
            // Cykeltid (sek/IBC) = (drifttid minuter * 60) / ibc_ok
            $sqlRaw = "
                SELECT t.op_num, o.number AS op_id, o.name AS namn,
                       t.ibc_ok_shift, t.snitt_cykel_sek
                FROM (
                    SELECT s.op1 AS op_num,
                           s.ibc_ok AS ibc_ok_shift,
                           (COALESCE(s.drifttid, 0) * 60.0 / s.ibc_ok) AS snitt_cykel_sek
                    FROM rebotling_skiftrapport s
                    WHERE s.datum BETWEEN ? AND ?
                      AND s.op1 IS NOT NULL
                      AND s.ibc_ok > 0
                      AND s.drifttid > 0
                    UNION ALL
                    SELECT s.op2 AS op_num,
                           s.ibc_ok AS ibc_ok_shift,
                           (COALESCE(s.drifttid, 0) * 60.0 / s.ibc_ok) AS snitt_cykel_sek
                    FROM rebotling_skiftrapport s
                    WHERE s.datum BETWEEN ? AND ?
                      AND s.op2 IS NOT NULL
                      AND s.ibc_ok > 0
                      AND s.drifttid > 0
                    UNION ALL
                    SELECT s.op3 AS op_num,
                           s.ibc_ok AS ibc_ok_shift,
                           (COALESCE(s.drifttid, 0) * 60.0 / s.ibc_ok) AS snitt_cykel_sek
                    FROM rebotling_skiftrapport s
                    WHERE s.datum BETWEEN ? AND ?
                      AND s.op3 IS NOT NULL
                      AND s.ibc_ok > 0
                      AND s.drifttid > 0
                ) t
                JOIN operators o ON o.number = t.op_num
                WHERE t.snitt_cykel_sek BETWEEN 30 AND 600
                ORDER BY t.op_num, t.snitt_cykel_sek ASC
            ";

            $stmt = $this->pdo->prepare($sqlRaw);
            $stmt->execute([
                $startDate, $endDate,
                $startDate, $endDate,
                $startDate, $endDate,
            ]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Gruppera per operatör och beräkna statistik inklusive median och P90
            $grouped = [];
            foreach ($rawRows as $r) {
                $id = (int)$r['op_id'];
                if (!isset($grouped[$id])) {
                    $grouped[$id] = [
                        'op_id'  => $id,
                        'namn'   => $r['namn'],
                        'values' => [],
                        'total_ibc' => 0,
                    ];
                }
                $grouped[$id]['values'][] = (float)$r['snitt_cykel_sek'];
                $grouped[$id]['total_ibc'] += (int)$r['ibc_ok_shift'];
            }

            // Hjälpfunktion: percentil (linjär interpolation)
            $percentile = function(array $sorted, float $p): float {
                $n = count($sorted);
                if ($n === 0) return 0.0;
                if ($n === 1) return $sorted[0];
                $idx = $p / 100.0 * ($n - 1);
                $lo  = (int)floor($idx);
                $hi  = (int)ceil($idx);
                if ($lo === $hi) return $sorted[$lo];
                return $sorted[$lo] + ($idx - $lo) * ($sorted[$hi] - $sorted[$lo]);
            };

            $operators = [];
            foreach ($grouped as $id => $g) {
                $vals = $g['values']; // redan sorterat ASC från SQL
                sort($vals);
                $n = count($vals);
                $nameParts = array_filter(explode(' ', trim($g['namn'])));
                $initialer = '';
                foreach ($nameParts as $p) {
                    if ($p !== '') $initialer .= strtoupper(substr($p, 0, 1));
                }
                $initialer = substr($initialer, 0, 3) ?: ('OP' . $id);

                $median_sek = $percentile($vals, 50);
                $p90_sek    = $percentile($vals, 90);

                $operators[] = [
                    'op_id'          => $id,
                    'namn'           => $g['namn'],
                    'initialer'      => $initialer,
                    'antal_skift'    => $n,
                    'snitt_cykel_sek'=> round(array_sum($vals) / $n, 1),
                    'bast_cykel_sek' => round($vals[0], 1),
                    'samst_cykel_sek'=> round($vals[$n - 1], 1),
                    'median_min'     => round($median_sek / 60.0, 2),
                    'p90_min'        => round($p90_sek / 60.0, 2),
                    'total_ibc'      => (int)$g['total_ibc'],
                ];
            }

            // Sortera fallande på antal_skift (flest registrerade cykler överst)
            usort($operators, fn($a, $b) => $b['antal_skift'] - $a['antal_skift']);

            echo json_encode([
                'success'    => true,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'data'       => $operators,
            ]);
        } catch (Exception $e) {
            error_log('getCycleByOperator: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta cykeltid per operatör']);
        }
    }

    // =========================================================
    // Produktionskalender — GitHub-liknande heatmap per år
    // GET ?action=rebotling&run=year-calendar&year=YYYY
    // =========================================================
    private function getYearCalendar() {
        $year = intval($_GET['year'] ?? date('Y'));
        if ($year < 2020 || $year > 2100) {
            $year = (int)date('Y');
        }

        try {
            // Hämta dagsmål: försök veckodagsmål, annars rebotling_settings
            $weekdayGoals = [];
            try {
                $wgStmt = $this->pdo->query("SELECT weekday, daily_goal FROM rebotling_weekday_goals");
                foreach ($wgStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $weekdayGoals[(int)$row['weekday']] = (int)$row['daily_goal'];
                }
            } catch (Exception $e) { /* tabell saknas */ }

            $defaultGoal = 1000;
            try {
                $sgRow = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sgRow) $defaultGoal = (int)$sgRow['rebotling_target'];
            } catch (Exception $e) { /* ignorera */ }

            // Hämta produktion per dag för hela året från rebotling_skiftrapport
            // SUM(ibc_ok) per datum
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    SUM(ibc_ok) AS ibc_ok
                FROM rebotling_skiftrapport
                WHERE YEAR(datum) = :year
                  AND ibc_ok IS NOT NULL
                GROUP BY datum
                ORDER BY datum ASC
            ");
            $stmt->execute(['year' => $year]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback: om inga skiftrapporter — hämta från rebotling_ibc (PLC-data)
            if (empty($rows)) {
                $stmt2 = $this->pdo->prepare("
                    SELECT
                        DATE(datum) AS datum,
                        SUM(shift_ibc_ok) AS ibc_ok
                    FROM (
                        SELECT
                            datum,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                        FROM rebotling_ibc
                        WHERE YEAR(datum) = :year
                          AND skiftraknare IS NOT NULL
                        GROUP BY DATE(datum), skiftraknare
                    ) AS ps
                    GROUP BY DATE(datum)
                    ORDER BY datum ASC
                ");
                $stmt2->execute(['year' => $year]);
                $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }

            $days = [];
            foreach ($rows as $row) {
                $dateStr = $row['datum'];
                $ibc     = (int)$row['ibc_ok'];
                if ($ibc <= 0) continue; // hoppa över nolldagar

                // Bestäm dagsmål: ISO-veckodag 1=Måndag ... 7=Söndag
                $dt      = new DateTime($dateStr);
                $wday    = (int)$dt->format('N'); // 1=Mån, 7=Sön
                $goal    = isset($weekdayGoals[$wday]) ? $weekdayGoals[$wday] : $defaultGoal;

                // Om dagsmål är 0 (t.ex. lördag/söndag) men det ändå producerats — sätt mål till defaultGoal
                if ($goal <= 0) $goal = $defaultGoal;

                $pct = $goal > 0 ? round($ibc / $goal * 100, 2) : 0;

                $days[] = [
                    'date' => $dateStr,
                    'ibc'  => $ibc,
                    'goal' => $goal,
                    'pct'  => $pct,
                ];
            }

            echo json_encode([
                'success' => true,
                'year'    => $year,
                'days'    => $days,
            ]);
        } catch (Exception $e) {
            error_log('getYearCalendar: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kalenderdata']);
        }
    }


    // =========================================================
    // Dagdetalj — timvis nedbrytning för en vald dag
    // GET ?action=rebotling&run=day-detail&date=YYYY-MM-DD
    // =========================================================
    private function getDayDetail() {
        $date = $_GET['date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum']);
            return;
        }

        try {
            // Hämta timvis ackumulerad data från rebotling_ibc
            $stmt = $this->pdo->prepare("
                SELECT
                    HOUR(datum) AS timme,
                    MAX(ibc_ok)        AS ackumulerat_ibc,
                    MAX(ibc_ej_ok)     AS ej_ok_ackumulerat,
                    MAX(runtime_plc)   AS runtime_sek,
                    COUNT(DISTINCT skiftraknare) AS skift_count
                FROM rebotling_ibc
                WHERE DATE(datum) = ?
                  AND skiftraknare IS NOT NULL
                GROUP BY HOUR(datum)
                ORDER BY timme
            ");
            $stmt->execute([$date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna delta-IBC per timme (differens från föregående ackumulerat värde per skift)
            // Vi hämtar rådata per skift och timme för att kunna beräkna deltas korrekt
            $rawStmt = $this->pdo->prepare("
                SELECT
                    HOUR(datum)      AS timme,
                    skiftraknare,
                    MAX(ibc_ok)      AS acc_ibc,
                    MAX(ibc_ej_ok)   AS acc_ej_ok,
                    MAX(runtime_plc) AS runtime_sek
                FROM rebotling_ibc
                WHERE DATE(datum) = ?
                  AND skiftraknare IS NOT NULL
                GROUP BY skiftraknare, HOUR(datum)
                ORDER BY skiftraknare, timme
            ");
            $rawStmt->execute([$date]);
            $rawRows = $rawStmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg delta-beräkning per skift
            // key: skiftraknare → [timme → delta_ibc]
            $shiftPrevIbc   = [];
            $shiftPrevEjOk  = [];
            $deltaMap       = []; // timme → delta_ibc (summerat över alla skift)
            $deltaEjOkMap   = []; // timme → delta_ej_ok
            $runtimeMap     = []; // timme → runtime_sek (max över skift)

            foreach ($rawRows as $r) {
                $t    = (int)$r['timme'];
                $sk   = (int)$r['skiftraknare'];
                $acc  = (int)$r['acc_ibc'];
                $eo   = (int)$r['acc_ej_ok'];
                $rt   = (int)$r['runtime_sek'];

                // Delta IBC
                $prev = $shiftPrevIbc[$sk] ?? 0;
                $delta = max(0, $acc - $prev);
                $shiftPrevIbc[$sk] = $acc;

                // Delta ej_ok
                $prevEo = $shiftPrevEjOk[$sk] ?? 0;
                $deltaEo = max(0, $eo - $prevEo);
                $shiftPrevEjOk[$sk] = $eo;

                $deltaMap[$t] = ($deltaMap[$t] ?? 0) + $delta;
                $deltaEjOkMap[$t] = ($deltaEjOkMap[$t] ?? 0) + $deltaEo;
                $runtimeMap[$t] = max($runtimeMap[$t] ?? 0, $rt);
            }

            // Bestäm skift (1=06-13, 2=14-21, 3=22-05)
            $hourToSkift = function(int $h): int {
                if ($h >= 6 && $h <= 13) return 1;
                if ($h >= 14 && $h <= 21) return 2;
                return 3; // 22-05
            };

            // Bygg timvis array
            $hourly = [];
            $totalIbc    = 0;
            $totalEjOk   = 0;
            $skift1Ibc   = 0;
            $skift2Ibc   = 0;
            $skift3Ibc   = 0;
            $activeHours = 0;
            $ibcPerHList = [];

            foreach ($deltaMap as $timme => $deltaIbc) {
                $rt      = $runtimeMap[$timme] ?? 0;
                $rtMin   = round($rt / 60, 1);
                $deltaEo = $deltaEjOkMap[$timme] ?? 0;
                $skift   = $hourToSkift($timme);

                // IBC/h: ibc producerat under timmen / effektiv drifttid (eller per heltimme om rt=0)
                $ibcPerH = $rtMin > 0 ? round($deltaIbc / ($rtMin / 60), 1) : 0.0;

                if ($deltaIbc > 0) {
                    $activeHours++;
                    $ibcPerHList[] = $ibcPerH;
                }

                $totalIbc  += $deltaIbc;
                $totalEjOk += $deltaEo;
                if ($skift === 1) $skift1Ibc += $deltaIbc;
                elseif ($skift === 2) $skift2Ibc += $deltaIbc;
                else $skift3Ibc += $deltaIbc;

                $hourly[] = [
                    'timme'      => $timme,
                    'ibc'        => $deltaIbc,
                    'ibc_per_h'  => $ibcPerH,
                    'runtime_min'=> $rtMin,
                    'ej_ok'      => $deltaEo,
                    'skift'      => $skift,
                ];
            }

            // Sortera på timme
            usort($hourly, fn($a, $b) => $a['timme'] - $b['timme']);

            $avgIbcPerH  = count($ibcPerHList) > 0 ? round(array_sum($ibcPerHList) / count($ibcPerHList), 1) : 0.0;
            $totalProduced = $totalIbc + $totalEjOk;
            $qualityPct    = $totalProduced > 0 ? round($totalIbc / $totalProduced * 100, 1) : 0.0;

            // Hämta aktiva operatörer för denna dag
            $opStmt = $this->pdo->prepare("
                SELECT DISTINCT op_id, o.name AS op_name
                FROM (
                    SELECT op1 AS op_id FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT op2 FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT op3 FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op3 IS NOT NULL AND op3 > 0
                ) AS ops
                LEFT JOIN operators o ON o.number = op_id
                WHERE op_id IS NOT NULL
            ");
            $opStmt->execute([$date, $date, $date]);
            $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatera operatörer — initials + namn
            $opList = [];
            foreach ($operators as $op) {
                $name = $op['op_name'] ?? 'Op ' . $op['op_id'];
                $parts = explode(' ', trim($name));
                $initials = '';
                foreach ($parts as $p) {
                    if (strlen($p) > 0) $initials .= strtoupper($p[0]);
                }
                $opList[] = [
                    'id'       => (int)$op['op_id'],
                    'name'     => $name,
                    'initials' => $initials,
                ];
            }

            echo json_encode([
                'success' => true,
                'date'    => $date,
                'hourly'  => $hourly,
                'summary' => [
                    'total_ibc'     => $totalIbc,
                    'avg_ibc_per_h' => $avgIbcPerH,
                    'skift1_ibc'    => $skift1Ibc,
                    'skift2_ibc'    => $skift2Ibc,
                    'skift3_ibc'    => $skift3Ibc,
                    'total_ej_ok'   => $totalEjOk,
                    'quality_pct'   => $qualityPct,
                    'active_hours'  => $activeHours,
                ],
                'operators' => $opList,
            ]);
        } catch (Exception $e) {
            error_log('getDayDetail: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta dagdetalj']);
        }
    }

    // =========================================================
    // Benchmarking — denna vecka vs rekordveckan
    // =========================================================

    /**
     * GET ?action=rebotling&run=benchmarking
     *
     * Returnerar:
     *   - current_week: IBC totalt, IBC/dag, snitt kvalitet%, snitt OEE%, aktiva dagar för innevarande vecka
     *   - best_week_ever: rekordveckan (höst IBC totalt)
     *   - best_day_ever: dag med flest IBC
     *   - top_weeks: topp-10 veckor sorterade på ibc_total DESC
     *   - monthly_totals: IBC per månad senaste 12 månaderna
     */
    private function getBenchmarking() {
        try {
            $tz = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // ---- Ideal rate för OEE-beräkning ----
            $idealRatePerMin = 15.0 / 60.0;

            // ---- Hjälpfunktion: beräkna OEE% för en mängd skift-aggregat ----
            // Används inline nedan.

            // ================================================================
            // 1. Topp-10 veckor (aggregerat korrekt: MAX per skift → SUM per vecka)
            // ================================================================
            $stmtTop = $this->pdo->query("
                SELECT
                    YEAR(datum)     AS yr,
                    WEEK(datum, 1)  AS wk,
                    SUM(shift_ibc)  AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS avg_quality,
                    COUNT(DISTINCT DATE(datum))  AS days_active
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
                ) AS per_shift
                GROUP BY YEAR(datum), WEEK(datum, 1)
                ORDER BY ibc_total DESC
                LIMIT 10
            ");
            $topWeeksRaw = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            // ---- OEE per vecka (topp-10 veckor) ----
            // För OEE behöver vi runtime_plc och rasttime per skift per vecka.
            $stmtOeeWeeks = $this->pdo->query("
                SELECT
                    YEAR(datum)    AS yr,
                    WEEK(datum, 1) AS wk,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY YEAR(datum), WEEK(datum, 1)
            ");
            $oeeByWeek = [];
            foreach ($stmtOeeWeeks->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = $row['yr'] . '-' . $row['wk'];
                $totalIBC  = (float)$row['ibc_ok'] + (float)$row['ibc_ej_ok'];
                $goodIBC   = (float)$row['ibc_ok'];
                $runtimeM  = max((float)$row['runtime_min'], 1);
                $plannedM  = max((float)$row['runtime_min'] + (float)$row['rast_min'], 1);
                $avail     = min($runtimeM / $plannedM, 1.0);
                $perf      = min(($totalIBC / $runtimeM) / $idealRatePerMin, 1.0);
                $qual      = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;
                $oeeByWeek[$key] = round($avail * $perf * $qual * 100, 1);
            }

            // ---- Bygg topp-10-lista ----
            $topWeeks = [];
            $bestWeekYr = null;
            $bestWeekWk = null;
            foreach ($topWeeksRaw as $i => $row) {
                $yr  = (int)$row['yr'];
                $wk  = (int)$row['wk'];
                $key = $yr . '-' . $wk;
                $oee = $oeeByWeek[$key] ?? null;
                // ISO veckonummer → veckoetiketten
                $weekLabel = 'V' . $wk . ' ' . $yr;
                $entry = [
                    'week_label'  => $weekLabel,
                    'yr'          => $yr,
                    'wk'          => $wk,
                    'ibc_total'   => (int)$row['ibc_total'],
                    'avg_quality' => (float)$row['avg_quality'],
                    'avg_oee'     => $oee,
                    'days_active' => (int)$row['days_active'],
                ];
                if ($i === 0) {
                    $bestWeekYr = $yr;
                    $bestWeekWk = $wk;
                }
                $topWeeks[] = $entry;
            }

            // ================================================================
            // 2. Bästa veckan — första raden i topWeeks
            // ================================================================
            $bestWeekEver = null;
            if (!empty($topWeeks)) {
                $bw = $topWeeks[0];
                $ipcDay = $bw['days_active'] > 0 ? round($bw['ibc_total'] / $bw['days_active'], 1) : 0.0;
                $bestWeekEver = [
                    'week_label'  => $bw['week_label'],
                    'ibc_total'   => $bw['ibc_total'],
                    'ibc_per_day' => $ipcDay,
                    'avg_quality' => $bw['avg_quality'],
                    'avg_oee'     => $bw['avg_oee'],
                ];
            }

            // ================================================================
            // 3. Innevarande vecka
            // ================================================================
            $curYr = (int)$now->format('Y');
            $curWk = (int)$now->format('W'); // ISO 8601 veckonummer

            $stmtCur = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc)  AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS avg_quality,
                    COUNT(DISTINCT DATE(datum))   AS days_active
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
                    WHERE YEAR(datum) = ?
                      AND WEEK(datum, 1) = ?
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmtCur->execute([$curYr, $curWk]);
            $curRow = $stmtCur->fetch(PDO::FETCH_ASSOC);

            // OEE innevarande vecka
            $stmtCurOee = $this->pdo->prepare("
                SELECT
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE YEAR(datum) = ?
                      AND WEEK(datum, 1) = ?
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY skiftraknare
                ) AS ps
            ");
            $stmtCurOee->execute([$curYr, $curWk]);
            $curOeeRow = $stmtCurOee->fetch(PDO::FETCH_ASSOC);

            $curOee = null;
            if ($curOeeRow && $curOeeRow['runtime_min'] > 0) {
                $totalIBC = (float)$curOeeRow['ibc_ok'] + (float)$curOeeRow['ibc_ej_ok'];
                $goodIBC  = (float)$curOeeRow['ibc_ok'];
                $runtimeM = max((float)$curOeeRow['runtime_min'], 1);
                $plannedM = max((float)$curOeeRow['runtime_min'] + (float)$curOeeRow['rast_min'], 1);
                $avail    = min($runtimeM / $plannedM, 1.0);
                $perf     = min(($totalIBC / $runtimeM) / $idealRatePerMin, 1.0);
                $qual     = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;
                $curOee   = round($avail * $perf * $qual * 100, 1);
            }

            $curIbcTotal = (int)($curRow['ibc_total'] ?? 0);
            $curDaysActive = (int)($curRow['days_active'] ?? 0);
            $curIbcPerDay  = $curDaysActive > 0 ? round($curIbcTotal / $curDaysActive, 1) : 0.0;
            $weekLabel = 'V' . $curWk . ' ' . $curYr;

            $currentWeek = [
                'week_label'  => $weekLabel,
                'ibc_total'   => $curIbcTotal,
                'ibc_per_day' => $curIbcPerDay,
                'avg_quality' => (float)($curRow['avg_quality'] ?? 0),
                'avg_oee'     => $curOee,
                'days_active' => $curDaysActive,
            ];

            // ================================================================
            // 4. Bästa dagen någonsin
            // ================================================================
            $stmtBestDay = $this->pdo->query("
                SELECT
                    DATE(datum) AS datum,
                    SUM(shift_ibc) AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS quality
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
                ) AS per_shift
                GROUP BY DATE(datum)
                ORDER BY ibc_total DESC
                LIMIT 1
            ");
            $bestDayRow = $stmtBestDay->fetch(PDO::FETCH_ASSOC);
            $bestDayEver = $bestDayRow ? [
                'date'      => $bestDayRow['datum'],
                'ibc_total' => (int)$bestDayRow['ibc_total'],
                'quality'   => (float)$bestDayRow['quality'],
            ] : null;

            // ================================================================
            // 5. Månadsöversikt senaste 13 månader (innevar. månad + 12 bakåt)
            // ================================================================
            $stmtMonthly = $this->pdo->query("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m') AS month,
                    SUM(shift_ibc)  AS ibc_total,
                    ROUND(AVG(shift_quality), 1) AS avg_quality
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
                    WHERE datum >= DATE_SUB(LAST_DAY(NOW()), INTERVAL 13 MONTH)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
                ORDER BY month ASC
            ");
            $monthlyRaw = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

            // OEE per månad
            $stmtMonthlyOee = $this->pdo->query("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m') AS month,
                    SUM(shift_ibc_ok)    AS ibc_ok,
                    SUM(shift_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(shift_runtime)   AS runtime_min,
                    SUM(shift_rast)      AS rast_min
                FROM (
                    SELECT
                        DATE(datum) AS datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok,     0)) AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok,  0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc,0)) AS shift_runtime,
                        MAX(COALESCE(rasttime,   0)) AS shift_rast
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(LAST_DAY(NOW()), INTERVAL 13 MONTH)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS ps
                GROUP BY DATE_FORMAT(datum, '%Y-%m')
            ");
            $oeeByMonth = [];
            foreach ($stmtMonthlyOee->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $totalIBC = (float)$row['ibc_ok'] + (float)$row['ibc_ej_ok'];
                $goodIBC  = (float)$row['ibc_ok'];
                $runtimeM = max((float)$row['runtime_min'], 1);
                $plannedM = max((float)$row['runtime_min'] + (float)$row['rast_min'], 1);
                $avail    = min($runtimeM / $plannedM, 1.0);
                $perf     = min(($totalIBC / $runtimeM) / $idealRatePerMin, 1.0);
                $qual     = $totalIBC > 0 ? $goodIBC / $totalIBC : 0;
                $oeeByMonth[$row['month']] = round($avail * $perf * $qual * 100, 1);
            }

            $monthlyTotals = [];
            foreach ($monthlyRaw as $row) {
                $monthlyTotals[] = [
                    'month'       => $row['month'],
                    'ibc_total'   => (int)$row['ibc_total'],
                    'avg_quality' => (float)$row['avg_quality'],
                    'avg_oee'     => $oeeByMonth[$row['month']] ?? null,
                ];
            }

            // ================================================================
            // Bygg slutsvar
            // ================================================================
            echo json_encode([
                'success'       => true,
                'current_week'  => $currentWeek,
                'best_week_ever'=> $bestWeekEver,
                'best_day_ever' => $bestDayEver,
                'top_weeks'     => $topWeeks,
                'monthly_totals'=> $monthlyTotals,
            ]);
        } catch (Exception $e) {
            error_log('getBenchmarking: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta benchmarking-data']);
        }
    }

    // =========================================================
    // Månadsrapport — Jämförelse föregående månad
    // GET ?action=rebotling&run=month-compare&month=YYYY-MM
    // =========================================================
    private function getMonthCompare() {
        try {
            $monthParam = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
                $monthParam = date('Y-m');
            }

            [$year, $mon] = explode('-', $monthParam);
            $year = (int)$year;
            $mon  = (int)$mon;

            // Beräkna föregående månad
            $prevMon  = $mon - 1;
            $prevYear = $year;
            if ($prevMon < 1) {
                $prevMon  = 12;
                $prevYear = $year - 1;
            }
            $prevMonth = sprintf('%04d-%02d', $prevYear, $prevMon);

            // Dagsmål
            $dailyGoal = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['rebotling_target'])) {
                    $dailyGoal = (int)$sr['rebotling_target'];
                }
            } catch (Exception $e) {
                error_log('getMonthCompare: kunde ej läsa dagsmål: ' . $e->getMessage());
            }

            // Hjälpfunktion: hämta summering för en månad
            $fetchMonthData = function(string $m) use ($dailyGoal): array {
                // Räkna vardagar
                $daysInMonth = (int)date('t', strtotime($m . '-01'));
                $workdays = 0;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dow = (int)date('N', strtotime(sprintf('%s-%02d', $m, $d)));
                    if ($dow < 6) $workdays++;
                }
                $monthGoal = $dailyGoal * $workdays;

                $perShiftSQL = "
                    SELECT
                        DATE(datum)                                                               AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))                                                 AS shift_ibc,
                        MAX(COALESCE(ibc_ej_ok, 0))                                              AS shift_ej_ok,
                        ROUND(MAX(COALESCE(ibc_ok,0))*100.0 /
                            NULLIF(MAX(COALESCE(ibc_ok,0))+MAX(COALESCE(ibc_ej_ok,0)),0),1)     AS shift_quality,
                        MAX(COALESCE(runtime_plc, 0))                                            AS shift_runtime,
                        MAX(COALESCE(rasttime, 0))                                               AS shift_rast
                    FROM rebotling_ibc
                    WHERE DATE_FORMAT(datum,'%Y-%m') = ?
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ";

                $summarySQL = "
                    SELECT
                        COUNT(DISTINCT dag)                   AS production_days,
                        SUM(shift_ibc)                        AS ibc_total,
                        ROUND(AVG(shift_quality),1)           AS avg_quality,
                        ROUND(SUM(shift_runtime)/60.0,1)      AS total_runtime_hours,
                        ROUND(SUM(shift_rast)/60.0,1)         AS total_stoppage_hours
                    FROM ({$perShiftSQL}) AS per_shift
                ";
                $stmt = $this->pdo->prepare($summarySQL);
                $stmt->execute([$m]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                $ibcTotal       = (int)($row['ibc_total'] ?? 0);
                $prodDays       = (int)($row['production_days'] ?? 0);
                $avgQuality     = (float)($row['avg_quality'] ?? 0);
                $runtimeH       = (float)($row['total_runtime_hours'] ?? 0);
                $stoppageH      = (float)($row['total_stoppage_hours'] ?? 0);

                // OEE daglig för snittberäkning
                $oeeSQL = "
                    SELECT dag,
                           SUM(shift_ibc)     AS ibc_ok,
                           SUM(shift_ej_ok)   AS ibc_ej_ok,
                           SUM(shift_runtime) AS runtime_min,
                           SUM(shift_rast)    AS rast_min
                    FROM ({$perShiftSQL}) AS ps2
                    GROUP BY dag
                ";
                $stmt2 = $this->pdo->prepare($oeeSQL);
                $stmt2->execute([$m]);
                $oeeRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                $idealRatePerMin = 15.0 / 60.0;
                $oeeSum = 0.0;
                $oeeDays = 0;
                $bestDay  = null;
                $worstDay = null;
                foreach ($oeeRows as $r) {
                    $ibcOk   = (float)$r['ibc_ok'];
                    $ibcEjOk = (float)$r['ibc_ej_ok'];
                    $total   = $ibcOk + $ibcEjOk;
                    $opMin   = max((float)$r['runtime_min'], 1);
                    $planMin = max($opMin + (float)$r['rast_min'], 1);
                    $avail   = min($opMin / $planMin, 1.0);
                    $perf    = min(($total / $opMin) / $idealRatePerMin, 1.0);
                    $qual    = $total > 0 ? $ibcOk / $total : 0;
                    $oee     = round($avail * $perf * $qual * 100, 1);
                    if ($ibcOk > 0) {
                        $oeeSum += $oee;
                        $oeeDays++;
                        $targetPct = $dailyGoal > 0 ? round($ibcOk / $dailyGoal * 100, 1) : 0;
                        if ($bestDay === null || $ibcOk > $bestDay['ibc']) {
                            $bestDay = ['datum' => $r['dag'], 'ibc' => (int)$ibcOk, 'target_pct' => $targetPct];
                        }
                        if ($worstDay === null || $ibcOk < $worstDay['ibc']) {
                            $worstDay = ['datum' => $r['dag'], 'ibc' => (int)$ibcOk, 'target_pct' => $targetPct];
                        }
                    }
                }
                $avgOee = $oeeDays > 0 ? round($oeeSum / $oeeDays, 1) : 0;
                $avgIbcPerDay = $prodDays > 0 ? round($ibcTotal / $prodDays, 1) : 0;

                return [
                    'total_ibc'       => $ibcTotal,
                    'avg_ibc_per_day' => $avgIbcPerDay,
                    'avg_oee_pct'     => $avgOee,
                    'avg_quality_pct' => $avgQuality,
                    'working_days'    => $prodDays,
                    'month_goal'      => $monthGoal,
                    'best_day'        => $bestDay,
                    'worst_day'       => $worstDay,
                ];
            };

            $thisMonthData = $fetchMonthData($monthParam);
            $prevMonthData = $fetchMonthData($prevMonth);

            // Beräkna diff
            $diffIbcPct = null;
            if ($prevMonthData['total_ibc'] > 0) {
                $diffIbcPct = round(($thisMonthData['total_ibc'] - $prevMonthData['total_ibc']) / $prevMonthData['total_ibc'] * 100, 1);
            }
            $diffAvgIbcPerDayPct = null;
            if ($prevMonthData['avg_ibc_per_day'] > 0) {
                $diffAvgIbcPerDayPct = round(($thisMonthData['avg_ibc_per_day'] - $prevMonthData['avg_ibc_per_day']) / $prevMonthData['avg_ibc_per_day'] * 100, 1);
            }
            $diffOee     = round($thisMonthData['avg_oee_pct'] - $prevMonthData['avg_oee_pct'], 1);
            $diffQuality = round($thisMonthData['avg_quality_pct'] - $prevMonthData['avg_quality_pct'], 1);

            // Operatör av månaden — använder rebotling_ibc
            $opOfMonth = null;
            try {
                $firstDay = $monthParam . '-01';
                $lastDay  = date('Y-m-t', strtotime($firstDay));
                $opSQL = "
                    SELECT op_id, SUM(shift_ibc) AS total_ibc,
                           SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) AS avg_ibc_per_h,
                           SUM(shift_ok * 100.0) / NULLIF(SUM(shift_total), 0) AS avg_quality_pct
                    FROM (
                        SELECT op1 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1, skiftraknare
                        UNION ALL
                        SELECT op2 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2, skiftraknare
                        UNION ALL
                        SELECT op3 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3, skiftraknare
                    ) t
                    GROUP BY op_id
                    ORDER BY (SUM(shift_ibc) * 0.6 + SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) * 0.4) DESC
                    LIMIT 1
                ";
                $stmtOp = $this->pdo->prepare($opSQL);
                $stmtOp->execute([$firstDay, $lastDay, $firstDay, $lastDay, $firstDay, $lastDay]);
                $opRow = $stmtOp->fetch(PDO::FETCH_ASSOC);
                if ($opRow && $opRow['op_id']) {
                    // Hämta operatörens namn
                    $nameStmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ? LIMIT 1");
                    $nameStmt->execute([$opRow['op_id']]);
                    $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
                    $namn = $nameRow ? $nameRow['name'] : 'Okänd';
                    // Generera initialer från namn
                    $parts = explode(' ', trim($namn));
                    $initialer = '';
                    foreach ($parts as $p) {
                        if ($p !== '') $initialer .= strtoupper(substr($p, 0, 1));
                    }
                    $initialer = substr($initialer, 0, 3);
                    $opOfMonth = [
                        'op_id'           => (int)$opRow['op_id'],
                        'namn'            => $namn,
                        'initialer'       => $initialer,
                        'total_ibc'       => (int)($opRow['total_ibc'] ?? 0),
                        'avg_ibc_per_h'   => round((float)($opRow['avg_ibc_per_h'] ?? 0), 1),
                        'avg_quality_pct' => round((float)($opRow['avg_quality_pct'] ?? 0), 1),
                    ];
                }
            } catch (Exception $e) {
                error_log('getMonthCompare: operatör av månaden fel: ' . $e->getMessage());
            }

            // Full operatörsranking (topp 10) med poäng
            $operatorRanking = [];
            try {
                $firstDay = $monthParam . '-01';
                $lastDay  = date('Y-m-t', strtotime($firstDay));
                $rankSQL = "
                    SELECT op_id,
                           COUNT(DISTINCT skiftraknare) AS shifts,
                           SUM(shift_ibc) AS total_ibc,
                           SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) AS avg_ibc_per_h,
                           SUM(shift_ok * 100.0) / NULLIF(SUM(shift_total), 0) AS avg_quality_pct
                    FROM (
                        SELECT op1 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1, skiftraknare
                        UNION ALL
                        SELECT op2 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2, skiftraknare
                        UNION ALL
                        SELECT op3 AS op_id, skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ok, 0)) + MAX(COALESCE(ibc_ej_ok, 0)) AS shift_total,
                               MAX(COALESCE(runtime_plc, 0)) / 60.0 AS runtime_h
                        FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN ? AND ?
                          AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3, skiftraknare
                    ) t
                    GROUP BY op_id
                    ORDER BY (SUM(shift_ibc) * 0.6 + SUM(shift_ibc) / NULLIF(SUM(runtime_h), 0) * 0.4) DESC
                    LIMIT 10
                ";
                $stmtRank = $this->pdo->prepare($rankSQL);
                $stmtRank->execute([$firstDay, $lastDay, $firstDay, $lastDay, $firstDay, $lastDay]);
                $rankRows = $stmtRank->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rankRows as $rr) {
                    $nameStmt2 = $this->pdo->prepare("SELECT name FROM operators WHERE number = ? LIMIT 1");
                    $nameStmt2->execute([$rr['op_id']]);
                    $nameRow2 = $nameStmt2->fetch(PDO::FETCH_ASSOC);
                    $opNamn = $nameRow2 ? $nameRow2['name'] : 'Okänd';
                    $parts2 = explode(' ', trim($opNamn));
                    $init2 = '';
                    foreach ($parts2 as $p2) {
                        if ($p2 !== '') $init2 .= strtoupper(substr($p2, 0, 1));
                    }
                    $ibcH = (float)($rr['avg_ibc_per_h'] ?? 0);
                    $qualP = (float)($rr['avg_quality_pct'] ?? 0);
                    $totalIbc = (int)($rr['total_ibc'] ?? 0);
                    $score = round($totalIbc * 0.6 + $ibcH * 100 * 0.25 + $qualP * 0.15, 1);
                    $operatorRanking[] = [
                        'op_id'           => (int)$rr['op_id'],
                        'namn'            => $opNamn,
                        'initialer'       => substr($init2, 0, 3),
                        'shifts'          => (int)($rr['shifts'] ?? 0),
                        'total_ibc'       => $totalIbc,
                        'avg_ibc_per_h'   => round($ibcH, 1),
                        'avg_quality_pct' => round($qualP, 1),
                        'score'           => $score,
                    ];
                }
            } catch (Exception $e) {
                error_log('getMonthCompare: operatörsranking fel: ' . $e->getMessage());
            }

            // Antal operatörer på bästa dagen
            $bestDayData = $thisMonthData['best_day'];
            try {
                if ($bestDayData) {
                    $bdStmt = $this->pdo->prepare("
                        SELECT COUNT(DISTINCT op_id) AS op_count FROM (
                            SELECT DISTINCT op1 AS op_id FROM rebotling_ibc WHERE DATE(datum) = ? AND op1 IS NOT NULL AND op1 > 0
                            UNION SELECT DISTINCT op2 FROM rebotling_ibc WHERE DATE(datum) = ? AND op2 IS NOT NULL AND op2 > 0
                            UNION SELECT DISTINCT op3 FROM rebotling_ibc WHERE DATE(datum) = ? AND op3 IS NOT NULL AND op3 > 0
                        ) ops
                    ");
                    $bdDate = $bestDayData['datum'];
                    $bdStmt->execute([$bdDate, $bdDate, $bdDate]);
                    $bdRow = $bdStmt->fetch(PDO::FETCH_ASSOC);
                    $bestDayData['operator_count'] = (int)($bdRow['op_count'] ?? 0);
                }
            } catch (Exception $e) {
                error_log('getMonthCompare: best day operators fel: ' . $e->getMessage());
            }

            echo json_encode([
                'success'            => true,
                'month'              => $monthParam,
                'prev_month'         => $prevMonth,
                'this_month'         => $thisMonthData,
                'prev_month_data'    => $prevMonthData,
                'diff'               => [
                    'total_ibc_pct'          => $diffIbcPct,
                    'avg_ibc_per_day_pct'    => $diffAvgIbcPerDayPct,
                    'avg_oee_pct_diff'       => $diffOee,
                    'avg_quality_pct_diff'   => $diffQuality,
                ],
                'operator_of_month'  => $opOfMonth,
                'operator_ranking'   => $operatorRanking,
                'best_day'           => $bestDayData,
                'worst_day'          => $thisMonthData['worst_day'],
            ]);

        } catch (Exception $e) {
            error_log('getMonthCompare: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta jämförelsedata']);
        }
    }

    // =========================================================
    // Månadsrapport
    // GET ?action=rebotling&run=monthly-report&month=YYYY-MM
    // =========================================================
    private function getMonthlyReport() {
        try {
            $month = $_GET['month'] ?? date('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = date('Y-m');
            }

            // Bygg mennesklig månadsrubrik (svenska)
            $monthNames = [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Mars',
                '04' => 'April',   '05' => 'Maj',       '06' => 'Juni',
                '07' => 'Juli',    '08' => 'Augusti',   '09' => 'September',
                '10' => 'Oktober', '11' => 'November',  '12' => 'December',
            ];
            [$year, $mon] = explode('-', $month);
            $monthLabel = ($monthNames[$mon] ?? $mon) . ' ' . $year;

            // Dagsmål från rebotling_settings
            $dailyGoal = 1000;
            try {
                $this->ensureSettingsTable();
                $sr = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['rebotling_target'])) {
                    $dailyGoal = (int)$sr['rebotling_target'];
                }
            } catch (Exception $e) {
                error_log('getMonthlyReport: kunde inte läsa dagsmål: ' . $e->getMessage());
            }

            // Antal kalenderdagar i månaden
            $activeDays = (int)date('t', strtotime($month . '-01'));
            // Månadsmål baserat på dagsmål * antal produktionsdagar (alla dagar som inte är lördag/söndag)
            $workdays = 0;
            for ($d = 1; $d <= $activeDays; $d++) {
                $dow = (int)date('N', strtotime(sprintf('%s-%02d', $month, $d)));
                if ($dow < 6) $workdays++;
            }
            $monthGoal = $dailyGoal * $workdays;

            // ---- Per-skift-subquery som bas ----
            $perShiftSQL = "
                SELECT
                    DATE(datum)                                                             AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))                                               AS shift_ibc,
                    MAX(COALESCE(ibc_ej_ok, 0))                                            AS shift_ej_ok,
                    ROUND(MAX(COALESCE(ibc_ok,0))*100.0 /
                        NULLIF(MAX(COALESCE(ibc_ok,0))+MAX(COALESCE(ibc_ej_ok,0)),0),1)   AS shift_quality,
                    MAX(COALESCE(runtime_plc, 0))                                          AS shift_runtime,
                    MAX(COALESCE(rasttime, 0))                                             AS shift_rast
                FROM rebotling_ibc
                WHERE DATE_FORMAT(datum,'%Y-%m') = ?
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ";

            // ---- Summary ----
            $summarySQL = "
                SELECT
                    COUNT(DISTINCT dag)                   AS production_days,
                    SUM(shift_ibc)                        AS ibc_total,
                    ROUND(AVG(shift_quality),1)           AS avg_quality,
                    ROUND(SUM(shift_runtime)/60.0,1)      AS total_runtime_hours,
                    ROUND(SUM(shift_rast)/60.0,1)         AS total_stoppage_hours,
                    ROUND(AVG(shift_ibc),1)               AS avg_ibc_per_shift,
                    SUM(shift_ibc+shift_ej_ok)            AS total_ibc_all
                FROM ({$perShiftSQL}) AS per_shift
            ";
            $stmt = $this->pdo->prepare($summarySQL);
            $stmt->execute([$month]);
            $sumRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $ibcTotal          = (int)($sumRow['ibc_total'] ?? 0);
            $productionDays    = (int)($sumRow['production_days'] ?? 0);
            $avgQuality        = (float)($sumRow['avg_quality'] ?? 0);
            $totalRuntimeHours = (float)($sumRow['total_runtime_hours'] ?? 0);
            $totalStoppageHours= (float)($sumRow['total_stoppage_hours'] ?? 0);
            $goalPct           = $monthGoal > 0 ? round($ibcTotal * 100.0 / $monthGoal, 1) : 0;
            $avgIbcPerDay      = $productionDays > 0 ? round($ibcTotal / $productionDays, 1) : 0;

            // ---- OEE per dag (aggregerat) ----
            $oeeSQL = "
                SELECT
                    dag,
                    SUM(shift_ibc)      AS ibc_ok,
                    SUM(shift_ej_ok)    AS ibc_ej_ok,
                    SUM(shift_runtime)  AS runtime_min,
                    SUM(shift_rast)     AS rast_min
                FROM ({$perShiftSQL}) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ";
            $stmt = $this->pdo->prepare($oeeSQL);
            $stmt->execute([$month]);
            $oeeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $idealRatePerMin = 15.0 / 60.0;
            $dailyProduction = [];
            $oeeSum = 0;
            $oeeDays = 0;
            $bestDay  = null;
            $worstDay = null;

            foreach ($oeeRows as $r) {
                $ibcOk   = (float)$r['ibc_ok'];
                $ibcEjOk = (float)$r['ibc_ej_ok'];
                $total   = $ibcOk + $ibcEjOk;
                $opMin   = max((float)$r['runtime_min'], 1);
                $planMin = max($opMin + (float)$r['rast_min'], 1);

                $avail = min($opMin / $planMin, 1.0);
                $perf  = min(($total / $opMin) / $idealRatePerMin, 1.0);
                $qual  = $total > 0 ? $ibcOk / $total : 0;
                $oee   = round($avail * $perf * $qual * 100, 1);
                $qualPct = $total > 0 ? round($ibcOk / $total * 100, 1) : 0;

                $dailyProduction[] = [
                    'date'    => $r['dag'],
                    'ibc'     => (int)$ibcOk,
                    'quality' => $qualPct,
                    'oee'     => $oee,
                ];

                if ($ibcOk > 0) {
                    $oeeSum += $oee;
                    $oeeDays++;

                    if ($bestDay === null || $ibcOk > $bestDay['ibc']) {
                        $bestDay = ['date' => $r['dag'], 'ibc' => (int)$ibcOk, 'quality' => $qualPct];
                    }
                    if ($worstDay === null || $ibcOk < $worstDay['ibc']) {
                        $worstDay = ['date' => $r['dag'], 'ibc' => (int)$ibcOk, 'quality' => $qualPct];
                    }
                }
            }

            $avgOee = $oeeDays > 0 ? round($oeeSum / $oeeDays, 1) : 0;

            // ---- Veckosammanfattning ----
            $weekMap = [];
            foreach ($dailyProduction as $day) {
                $ts  = strtotime($day['date']);
                $wk  = 'V' . (int)date('W', $ts);
                if (!isset($weekMap[$wk])) {
                    $weekMap[$wk] = ['ibc' => 0, 'quality_sum' => 0, 'oee_sum' => 0, 'days' => 0];
                }
                $weekMap[$wk]['ibc']         += $day['ibc'];
                $weekMap[$wk]['quality_sum'] += $day['quality'];
                $weekMap[$wk]['oee_sum']     += $day['oee'];
                $weekMap[$wk]['days']++;
            }
            $weekSummary = [];
            foreach ($weekMap as $wk => $wd) {
                $days = max($wd['days'], 1);
                $weekSummary[] = [
                    'week'        => $wk,
                    'ibc'         => $wd['ibc'],
                    'avg_quality' => round($wd['quality_sum'] / $days, 1),
                    'avg_oee'     => round($wd['oee_sum'] / $days, 1),
                ];
            }

            // ---- Operatörsranking för månaden ----
            $opSQL = "
                SELECT
                    o.number        AS number,
                    o.name          AS name,
                    SUM(sub.ibc_ok)       AS ibc_ok,
                    SUM(sub.totalt)       AS totalt,
                    SUM(sub.drifttid)     AS drifttid,
                    COUNT(sub.skift_id)   AS shifts
                FROM (
                    SELECT s.id AS skift_id, s.op1 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE DATE_FORMAT(s.datum,'%Y-%m') = ?
                      AND s.op1 IS NOT NULL
                    UNION ALL
                    SELECT s.id AS skift_id, s.op2 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE DATE_FORMAT(s.datum,'%Y-%m') = ?
                      AND s.op2 IS NOT NULL
                    UNION ALL
                    SELECT s.id AS skift_id, s.op3 AS op_num, s.ibc_ok, s.totalt, COALESCE(s.drifttid,0) AS drifttid
                    FROM rebotling_skiftrapport s
                    WHERE DATE_FORMAT(s.datum,'%Y-%m') = ?
                      AND s.op3 IS NOT NULL
                ) sub
                JOIN operators o ON o.number = sub.op_num
                GROUP BY o.number, o.name
                ORDER BY (SUM(sub.ibc_ok) / GREATEST(SUM(sub.drifttid)/60.0, 0.01)) DESC
                LIMIT 20
            ";
            $stmt = $this->pdo->prepare($opSQL);
            $stmt->execute([$month, $month, $month]);
            $opRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $operatorRanking = [];
            foreach ($opRows as $row) {
                $ibcOk   = (int)($row['ibc_ok']  ?? 0);
                $totalt  = (int)($row['totalt']   ?? 0);
                $drMin   = (int)($row['drifttid'] ?? 0);
                $ibcPerH = $drMin > 0 ? round($ibcOk / ($drMin / 60.0), 1) : null;
                $qualPct = $totalt > 0 ? round($ibcOk / $totalt * 100, 1) : null;
                $operatorRanking[] = [
                    'name'             => $row['name'],
                    'number'           => (int)$row['number'],
                    'shifts'           => (int)$row['shifts'],
                    'ibc_ok'           => $ibcOk,
                    'avg_ibc_per_hour' => $ibcPerH,
                    'avg_quality'      => $qualPct,
                ];
            }

            // ---- Bästa & sämsta vecka (baserat på IBC) ----
            $bastaVecka  = null;
            $samstaVecka = null;
            foreach ($weekSummary as $wk) {
                if ($bastaVecka === null || $wk['ibc'] > $bastaVecka['ibc']) {
                    $bastaVecka = ['week' => $wk['week'], 'ibc' => $wk['ibc'], 'avg_oee' => $wk['avg_oee']];
                }
                if ($samstaVecka === null || $wk['ibc'] < $samstaVecka['ibc']) {
                    $samstaVecka = ['week' => $wk['week'], 'ibc' => $wk['ibc'], 'avg_oee' => $wk['avg_oee']];
                }
            }

            // ---- OEE-trend (daglig OEE% för linjegraf) ----
            $oeeTrend = array_map(function($d) {
                return ['date' => $d['date'], 'oee' => $d['oee']];
            }, $dailyProduction);

            // ---- Topp-3 operatörer ----
            $topOperatorer = array_map(function($op) {
                return [
                    'namn'      => $op['name'],
                    'ibc_total' => $op['ibc_ok'],
                ];
            }, array_slice($operatorRanking, 0, 3));

            // ---- Total stilleståndstid i minuter ----
            $totalStoppMin = (int)round($totalStoppageHours * 60);

            echo json_encode([
                'success'          => true,
                'month'            => $month,
                'month_label'      => $monthLabel,
                'summary'          => [
                    'ibc_total'            => $ibcTotal,
                    'ibc_goal'             => $monthGoal,
                    'goal_pct'             => $goalPct,
                    'avg_ibc_per_day'      => $avgIbcPerDay,
                    'active_days'          => $activeDays,
                    'production_days'      => $productionDays,
                    'avg_quality'          => $avgQuality,
                    'avg_oee'              => $avgOee,
                    'total_runtime_hours'  => $totalRuntimeHours,
                    'total_stoppage_hours' => $totalStoppageHours,
                    'total_stopp_min'      => $totalStoppMin,
                ],
                'best_day'         => $bestDay,
                'worst_day'        => $worstDay,
                'basta_vecka'      => $bastaVecka,
                'samsta_vecka'     => $samstaVecka,
                'oee_trend'        => $oeeTrend,
                'top_operatorer'   => $topOperatorer,
                'operator_ranking' => $operatorRanking,
                'daily_production' => $dailyProduction,
                'week_summary'     => $weekSummary,
            ]);
        } catch (Exception $e) {
            error_log('getMonthlyReport: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta månadsrapport']);
        }
    }

    // ========== Prediktiv underhållsindikator ==========
    private function getMaintenanceIndicator() {
        try {
            $sql = "
                SELECT
                    YEAR(datum) AS yr,
                    WEEK(datum, 1) AS wk,
                    MIN(DATE(datum)) AS week_start,
                    SUM(shift_ibc) AS week_ibc,
                    SUM(shift_runtime) AS week_runtime,
                    ROUND(SUM(shift_runtime) / NULLIF(SUM(shift_ibc), 0), 2) AS avg_cycle_time
                FROM (
                    SELECT DATE(datum) AS datum, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
                      AND skiftraknare IS NOT NULL
                      AND ibc_ok > 0
                      AND runtime_plc > 0
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY YEAR(datum), WEEK(datum, 1)
                ORDER BY yr ASC, wk ASC
            ";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode([
                    'success' => true,
                    'status'  => 'ok',
                    'message' => 'Inte tillräckligt med data för att beräkna underhållsindikator.',
                    'weeks'   => [],
                    'baseline_cycle_time' => null,
                    'current_cycle_time'  => null,
                    'trend_pct'           => null,
                ]);
                return;
            }

            // Bygg veckoarray med etiketter
            $weeks = [];
            foreach ($rows as $row) {
                $weeks[] = [
                    'week_label'     => 'V' . (int)$row['wk'],
                    'week_start'     => $row['week_start'],
                    'avg_cycle_time' => $row['avg_cycle_time'] !== null ? (float)$row['avg_cycle_time'] : null,
                    'week_ibc'       => (int)$row['week_ibc'],
                ];
            }

            // Filtrera bort veckor utan cykeltidsdata
            $validWeeks = array_filter($weeks, function($w) { return $w['avg_cycle_time'] !== null && $w['avg_cycle_time'] > 0; });
            $validWeeks = array_values($validWeeks);

            if (count($validWeeks) < 2) {
                echo json_encode([
                    'success' => true,
                    'status'  => 'ok',
                    'message' => 'Inte tillräckligt med data för trendbedömning.',
                    'weeks'   => $weeks,
                    'baseline_cycle_time' => null,
                    'current_cycle_time'  => null,
                    'trend_pct'           => null,
                ]);
                return;
            }

            // Baslinje = snitt av de 4 första veckorna (eller färre om data saknas)
            $baselineCount = min(4, count($validWeeks) - 1);
            $baselineSlice = array_slice($validWeeks, 0, $baselineCount);
            $baselineSum   = array_sum(array_column($baselineSlice, 'avg_cycle_time'));
            $baselineCycleTime = round($baselineSum / $baselineCount, 2);

            // Aktuell = senaste veckan
            $lastWeek = $validWeeks[count($validWeeks) - 1];
            $currentCycleTime = (float)$lastWeek['avg_cycle_time'];

            // Trend i procent
            $trendPct = $baselineCycleTime > 0
                ? round((($currentCycleTime - $baselineCycleTime) / $baselineCycleTime) * 100, 1)
                : 0;

            // Bestäm status
            if ($trendPct > 30) {
                $status  = 'danger';
                $message = 'Cykeltiden har ökat ' . abs($trendPct) . '% under de senaste veckorna — kontrollera maskinens slitage omgående (ventiler, pumpar, dubbar).';
            } elseif ($trendPct > 15) {
                $status  = 'warning';
                $message = 'Cykeltiden har ökat ' . abs($trendPct) . '% under de senaste veckorna — kontrollera maskinens slitage.';
            } else {
                $status  = 'ok';
                $message = 'Cykeltiden är stabil. Ingen ökande trend detekterad.';
            }

            echo json_encode([
                'success'             => true,
                'status'              => $status,
                'message'             => $message,
                'weeks'               => $weeks,
                'baseline_cycle_time' => $baselineCycleTime,
                'current_cycle_time'  => $currentCycleTime,
                'trend_pct'           => $trendPct,
            ]);
        } catch (Exception $e) {
            error_log('getMaintenanceIndicator: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta underhållsindikator']);
        }
    }

    /**
     * GET ?action=rebotling&run=annotations&start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * Returnerar händelseannotationer från tre källor:
     *  1. Lång stopptid (> 2 h per dag) — från rebotling_skiftrapport.rasttime
     *  2. Låg produktion (< 50 % av dagsmål) — från rebotling_skiftrapport
     *  3. Audit-log-händelser (om tabellen finns)
     *
     * Varje källa hanteras i ett eget try-catch, så övriga källor returneras
     * även om en källa misslyckas.
     */
    private function getAnnotations() {
        // Validera datumparametrar
        $start = $_GET['start'] ?? '';
        $end   = $_GET['end']   ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            echo json_encode(['success' => false, 'error' => 'Ogiltiga datumparametrar']);
            return;
        }

        $annotations = [];

        // ----------------------------------------------------------------
        // Källa 1: Dagar med total rasttime > 120 min i rebotling_skiftrapport
        // ----------------------------------------------------------------
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)    AS event_date,
                    SUM(rasttime)  AS total_rast_min
                FROM rebotling_skiftrapport
                WHERE DATE(datum) BETWEEN :start AND :end
                GROUP BY DATE(datum)
                HAVING SUM(rasttime) > 120
                ORDER BY event_date
            ");
            $stmt->execute([':start' => $start, ':end' => $end]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $hours = round($row['total_rast_min'] / 60, 1);
                $annotations[] = [
                    'date'      => $row['event_date'],
                    'type'      => 'stopp',
                    'label'     => 'Lång stopptid: ' . $hours . 'h',
                ];
            }
        } catch (Exception $e) {
            error_log('getAnnotations stopp-källa: ' . $e->getMessage());
        }

        // ----------------------------------------------------------------
        // Källa 2: Dagar med låg produktion (< 50 % av dagsmålet)
        // ----------------------------------------------------------------
        try {
            // Hämta dagsmål från rebotling_settings
            $halfGoal = 500; // fallback
            try {
                $sr = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['rebotling_target'])) {
                    $halfGoal = intval($sr['rebotling_target']) / 2;
                }
            } catch (Exception $e2) {
                error_log('getAnnotations: kunde inte läsa rebotling_settings: ' . $e2->getMessage());
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)   AS event_date,
                    SUM(ibc_ok)   AS total_ibc
                FROM rebotling_skiftrapport
                WHERE DATE(datum) BETWEEN :start AND :end
                GROUP BY DATE(datum)
                HAVING SUM(ibc_ok) < :half_goal AND SUM(ibc_ok) > 0
                ORDER BY event_date
            ");
            $stmt->execute([':start' => $start, ':end' => $end, ':half_goal' => $halfGoal]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                // Undvik dubbelregistrering om datumet redan finns som stopp
                $alreadyExists = false;
                foreach ($annotations as $ann) {
                    if ($ann['date'] === $row['event_date']) {
                        $alreadyExists = true;
                        break;
                    }
                }
                if (!$alreadyExists) {
                    $annotations[] = [
                        'date'  => $row['event_date'],
                        'type'  => 'low_production',
                        'label' => 'Låg prod: ' . intval($row['total_ibc']) . ' IBC',
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('getAnnotations low_production-källa: ' . $e->getMessage());
        }

        // ----------------------------------------------------------------
        // Källa 3: Audit-log (om tabellen finns)
        // ----------------------------------------------------------------
        try {
            // Kontrollera om tabellen existerar
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = 'audit_log'"
            )->fetchColumn();

            if ($check > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        DATE(created_at) AS event_date,
                        'audit'          AS event_type,
                        action           AS label
                    FROM audit_log
                    WHERE created_at BETWEEN :start AND :end
                      AND action IN ('create_operator', 'update_settings', 'approve_bonus')
                    ORDER BY created_at
                    LIMIT 5
                ");
                $stmt->execute([':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $auditLabels = [
                    'create_operator' => 'Ny operatör',
                    'update_settings' => 'Inställningar uppdaterade',
                    'approve_bonus'   => 'Bonus godkänd',
                ];
                foreach ($rows as $row) {
                    $annotations[] = [
                        'date'  => $row['event_date'],
                        'type'  => 'audit',
                        'label' => $auditLabels[$row['label']] ?? $row['label'],
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('getAnnotations audit-källa: ' . $e->getMessage());
        }

        // Sortera på datum
        usort($annotations, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        echo json_encode(['success' => true, 'annotations' => $annotations]);
    }

    // ----------------------------------------------------------------
    // Kvalitetstrendkort
    // GET ?action=rebotling&run=quality-trend&days=30
    // ----------------------------------------------------------------
    private function getQualityTrend() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        try {
            $stmt = $this->pdo->prepare("
                SELECT dag,
                       ROUND(SUM(ibc_ok) * 100.0 / NULLIF(SUM(ibc_totalt), 0), 1) AS quality_pct,
                       SUM(ibc_ok) AS ibc_ok,
                       SUM(ibc_totalt) AS ibc_totalt
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS ibc_ok,
                           MAX(COALESCE(ibc_ok, 0) + COALESCE(ibc_ej_ok, 0)) AS ibc_totalt
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':days' => $days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna 7-dagars rullande medelvärde
            $rolling = [];
            for ($i = 0; $i < count($rows); $i++) {
                $window = array_slice($rows, max(0, $i - 6), min(7, $i + 1));
                $validValues = array_filter(array_column($window, 'quality_pct'), function($v) { return $v !== null; });
                if (count($validValues) > 0) {
                    $avg = array_sum($validValues) / count($validValues);
                } else {
                    $avg = null;
                }
                $rolling[] = $avg !== null ? round($avg, 1) : null;
            }

            // Beräkna KPI-värden
            $validPcts = array_filter(array_column($rows, 'quality_pct'), function($v) { return $v !== null; });
            $avgQuality = count($validPcts) > 0 ? round(array_sum($validPcts) / count($validPcts), 1) : null;
            $minQuality = count($validPcts) > 0 ? round(min($validPcts), 1) : null;
            $maxQuality = count($validPcts) > 0 ? round(max($validPcts), 1) : null;

            // Trend: jämför snitt av sista 7 dagar mot snitt av perioden dessförinnan
            $trend = 'stable';
            if (count($rows) >= 14) {
                $last7 = array_slice($rows, -7);
                $prev7 = array_slice($rows, -14, 7);
                $last7vals = array_filter(array_column($last7, 'quality_pct'), function($v) { return $v !== null; });
                $prev7vals = array_filter(array_column($prev7, 'quality_pct'), function($v) { return $v !== null; });
                if (count($last7vals) > 0 && count($prev7vals) > 0) {
                    $lastAvg = array_sum($last7vals) / count($last7vals);
                    $prevAvg = array_sum($prev7vals) / count($prev7vals);
                    if ($lastAvg > $prevAvg + 0.5) $trend = 'up';
                    elseif ($lastAvg < $prevAvg - 0.5) $trend = 'down';
                }
            }

            // Bygg svar
            $days_data = [];
            foreach ($rows as $i => $row) {
                $days_data[] = [
                    'date'        => $row['dag'],
                    'quality_pct' => $row['quality_pct'] !== null ? (float)$row['quality_pct'] : null,
                    'rolling_avg' => $rolling[$i],
                    'ibc_ok'      => (int)$row['ibc_ok'],
                    'ibc_totalt'  => (int)$row['ibc_totalt'],
                ];
            }

            echo json_encode([
                'success' => true,
                'days'    => $days_data,
                'kpi'     => [
                    'avg'   => $avgQuality,
                    'min'   => $minQuality,
                    'max'   => $maxQuality,
                    'trend' => $trend,
                ],
            ]);
        } catch (Exception $e) {
            error_log('getQualityTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av kvalitetstrend.']);
        }
    }

    // ----------------------------------------------------------------
    // Waterfalldiagram OEE
    // GET ?action=rebotling&run=oee-waterfall&days=30
    // ----------------------------------------------------------------
    private function getOeeWaterfall() {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(shift_runtime) AS total_runtime,
                       SUM(shift_rast) AS total_rast,
                       SUM(shift_ibc_ok) AS ibc_ok,
                       SUM(shift_ibc_ej_ok) AS ibc_ej_ok
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(COALESCE(runtime_plc, 0)) AS shift_runtime,
                           MAX(COALESCE(rasttime, 0)) AS shift_rast,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':days' => $days]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $runtime     = (float)($row['total_runtime'] ?? 0);  // minuter
            $rast        = (float)($row['total_rast'] ?? 0);     // minuter
            $ibcOk       = (int)($row['ibc_ok'] ?? 0);
            $ibcEjOk     = (int)($row['ibc_ej_ok'] ?? 0);
            $ibcTotalt   = $ibcOk + $ibcEjOk;
            $available   = $runtime + $rast;                      // minuter

            // Tillgänglighet
            $availability = $available > 0 ? round($runtime / $available * 100, 1) : 0.0;

            // Kvalitet
            $quality = $ibcTotalt > 0 ? round($ibcOk / $ibcTotalt * 100, 1) : 0.0;

            // Prestanda: 15 IBC/h = standard => ideal_cycle = 60/15 = 4 min/IBC
            $idealCycleMin = 60.0 / 15.0; // 4 min per IBC
            $performance = 0.0;
            if ($runtime > 0) {
                $performance = round(($ibcOk * $idealCycleMin) / $runtime * 100, 1);
                if ($performance > 100) $performance = 100.0;
            }

            // OEE
            $oee = round($availability * $performance * $quality / 10000, 1);

            // Förluster
            $availabilityLoss = round(100 - $availability, 1);
            $performanceLoss  = round($availability - ($availability * $performance / 100), 1);
            $qualityLoss      = round(($availability * $performance / 100) - $oee, 1);

            echo json_encode([
                'success'           => true,
                'availability'      => $availability,
                'performance'       => $performance,
                'quality'           => $quality,
                'oee'               => $oee,
                'availability_loss' => $availabilityLoss,
                'performance_loss'  => $performanceLoss,
                'quality_loss'      => $qualityLoss,
                'runtime_h'         => round($runtime / 60, 1),
                'rast_h'            => round($rast / 60, 1),
                'ibc_ok'            => $ibcOk,
                'ibc_ej_ok'         => $ibcEjOk,
            ]);
        } catch (Exception $e) {
            error_log('getOeeWaterfall: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av OEE-waterfall.']);
        }
    }

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
        $kommentar = $body['kommentar'] ?? '';

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
    private function getWeekdayStats() {
        $dagar = min(365, max(7, intval($_GET['dagar'] ?? 90)));

        // Svenska veckodagsnamn (MySQL returnerar engelska)
        $dagNamn = [1 => 'Söndag', 2 => 'Måndag', 3 => 'Tisdag', 4 => 'Onsdag', 5 => 'Torsdag', 6 => 'Fredag', 7 => 'Lördag'];
        $idealRatePerMin = 15.0 / 60.0;

        try {
            // Aggregera per skift först, summera sedan per dag, gruppera slutligen per veckodag
            $stmt = $this->pdo->prepare("
                SELECT
                    DAYOFWEEK(dag)        AS veckodag_nr,
                    COUNT(DISTINCT dag)   AS antal_dagar,
                    ROUND(AVG(dag_ibc), 1)  AS snitt_ibc,
                    MAX(dag_ibc)            AS max_ibc,
                    MIN(dag_ibc)            AS min_ibc,
                    ROUND(AVG(dag_oee), 1)  AS snitt_oee
                FROM (
                    SELECT
                        dag,
                        SUM(shift_ibc_ok)    AS dag_ibc,
                        SUM(shift_runtime)   AS dag_runtime,
                        SUM(shift_rast)      AS dag_rast,
                        SUM(shift_ibc_ej_ok) AS dag_ibc_ej_ok
                    FROM (
                        SELECT
                            DATE(datum)                           AS dag,
                            skiftraknare,
                            MAX(COALESCE(ibc_ok,    0))           AS shift_ibc_ok,
                            MAX(COALESCE(ibc_ej_ok,  0))          AS shift_ibc_ej_ok,
                            MAX(COALESCE(runtime_plc,0))          AS shift_runtime,
                            MAX(COALESCE(rasttime,   0))          AS shift_rast
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                          AND skiftraknare IS NOT NULL
                          AND ibc_ok IS NOT NULL
                          AND ibc_ok > 0
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY dag
                ) AS per_dag
                CROSS JOIN (SELECT 0) AS dummy
                GROUP BY DAYOFWEEK(dag)
                ORDER BY veckodag_nr
            ");
            $stmt->execute([$dagar]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $veckodagar = [];
            foreach ($rows as $r) {
                $nr = (int)$r['veckodag_nr'];
                $namn = $dagNamn[$nr] ?? 'Okänd';

                // Beräkna OEE om det saknas (snitt_oee kan vara NULL om runtime är 0)
                $snittOee = $r['snitt_oee'] !== null ? (float)$r['snitt_oee'] : null;

                $veckodagar[] = [
                    'veckodag_nr' => $nr,
                    'namn'        => $namn,
                    'antal_dagar' => (int)$r['antal_dagar'],
                    'snitt_ibc'   => (float)$r['snitt_ibc'],
                    'snitt_oee'   => $snittOee,
                    'max_ibc'     => (int)$r['max_ibc'],
                    'min_ibc'     => (int)$r['min_ibc']
                ];
            }

            echo json_encode([
                'success'     => true,
                'veckodagar'  => $veckodagar,
                'dagar'       => $dagar
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getWeekdayStats: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckodag-statistik']);
        }
    }

    // ----------------------------------------------------------------
    // Produktionshändelse-annotationer
    // GET ?action=rebotling&run=events&start=YYYY-MM-DD&end=YYYY-MM-DD
    // ----------------------------------------------------------------

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
            echo json_encode(['success' => true, 'events' => []]);
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
        $date  = $_POST['event_date']   ?? '';
        $title = trim($_POST['title']   ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $type  = $_POST['event_type']   ?? 'ovrigt';
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
    private function getStoppageAnalysis(): void {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        // Kontrollera att tabellerna finns
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('stoppage_log','stoppage_reasons')"
            );
            $found = (int)$check->fetchColumn();
            if ($found < 2) {
                echo json_encode([
                    'success' => true,
                    'empty'   => true,
                    'reason'  => 'Tabellerna stoppage_log/stoppage_reasons finns inte än. Kör migreringsfil 2026-03-04_stoppage_log.sql.',
                    'by_day'  => [],
                    'by_category' => [],
                    'top_reasons' => [],
                    'total_events'   => 0,
                    'total_minutes'  => 0,
                    'days'    => $days
                ]);
                return;
            }
        } catch (Exception $e) {
            error_log('RebotlingController getStoppageAnalysis check: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'empty'   => true,
                'reason'  => 'Kunde inte kontrollera tabeller.',
                'by_day'  => [],
                'by_category' => [],
                'top_reasons' => [],
                'total_events'   => 0,
                'total_minutes'  => 0,
                'days'    => $days
            ]);
            return;
        }

        try {
            // Aggregering per dag och kategori
            $stmtDay = $this->pdo->prepare(
                "SELECT
                     DATE(sl.created_at)        AS dag,
                     sr.category,
                     sr.name                    AS reason_name,
                     COUNT(*)                   AS antal,
                     COALESCE(SUM(sl.duration_minutes), 0)  AS total_minuter,
                     COALESCE(AVG(sl.duration_minutes), 0)  AS snitt_minuter
                 FROM stoppage_log sl
                 JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE sl.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY DATE(sl.created_at), sr.category, sr.name
                 ORDER BY dag DESC, total_minuter DESC"
            );
            $stmtDay->execute([$days]);
            $byDay = $stmtDay->fetchAll(PDO::FETCH_ASSOC);

            // Aggregering per kategori
            $stmtCat = $this->pdo->prepare(
                "SELECT
                     sr.category,
                     COUNT(*)                              AS antal,
                     COALESCE(SUM(sl.duration_minutes), 0) AS total_min
                 FROM stoppage_log sl
                 JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE sl.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY sr.category
                 ORDER BY total_min DESC"
            );
            $stmtCat->execute([$days]);
            $byCategory = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            // Topplista per orsak (max 10)
            $stmtTop = $this->pdo->prepare(
                "SELECT
                     sr.name,
                     sr.category,
                     COUNT(*)                              AS antal,
                     COALESCE(SUM(sl.duration_minutes), 0) AS total_min,
                     COALESCE(AVG(sl.duration_minutes), 0) AS snitt_min
                 FROM stoppage_log sl
                 JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE sl.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY sr.name, sr.category
                 ORDER BY total_min DESC
                 LIMIT 10"
            );
            $stmtTop->execute([$days]);
            $topReasons = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            // Summering
            $stmtSum = $this->pdo->prepare(
                "SELECT COUNT(*) AS total_events,
                        COALESCE(SUM(duration_minutes), 0) AS total_minutes
                 FROM stoppage_log
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"
            );
            $stmtSum->execute([$days]);
            $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

            $totalEvents  = (int)($summary['total_events']  ?? 0);
            $totalMinutes = (float)($summary['total_minutes'] ?? 0);

            // Bygg daglig aggregering (total per dag, för diagram)
            $dagMap = [];
            foreach ($byDay as $row) {
                $dag = $row['dag'];
                if (!isset($dagMap[$dag])) {
                    $dagMap[$dag] = ['dag' => $dag, 'total_minuter' => 0, 'antal' => 0, 'kategorier' => []];
                }
                $dagMap[$dag]['total_minuter'] += (float)$row['total_minuter'];
                $dagMap[$dag]['antal']         += (int)$row['antal'];
                $cat = $row['category'];
                if (!isset($dagMap[$dag]['kategorier'][$cat])) $dagMap[$dag]['kategorier'][$cat] = 0;
                $dagMap[$dag]['kategorier'][$cat] += (float)$row['total_minuter'];
            }
            $dagList = array_values($dagMap);

            // Konvertera numeriska strängar
            foreach ($byCategory as &$r) {
                $r['antal']     = (int)$r['antal'];
                $r['total_min'] = (float)$r['total_min'];
            }
            unset($r);
            foreach ($topReasons as &$r) {
                $r['antal']     = (int)$r['antal'];
                $r['total_min'] = (float)$r['total_min'];
                $r['snitt_min'] = round((float)$r['snitt_min'], 1);
            }
            unset($r);
            foreach ($dagList as &$r) {
                $r['total_minuter'] = round($r['total_minuter'], 1);
            }
            unset($r);

            $empty = ($totalEvents === 0);

            echo json_encode([
                'success'      => true,
                'empty'        => $empty,
                'by_day'       => $dagList,
                'by_category'  => $byCategory,
                'top_reasons'  => $topReasons,
                'total_events' => $totalEvents,
                'total_minutes'=> round($totalMinutes, 1),
                'days'         => $days
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getStoppageAnalysis: ' . $e->getMessage());
            echo json_encode([
                'success'      => true,
                'empty'        => true,
                'reason'       => 'Databasfel vid hämtning av stoppdata.',
                'by_day'       => [],
                'by_category'  => [],
                'top_reasons'  => [],
                'total_events' => 0,
                'total_minutes'=> 0,
                'days'         => $days
            ]);
        }
    }

    // POST ?action=rebotling&run=delete-event (kräver admin)
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
    private function getShiftTrend() {
        $datum = $_GET['datum'] ?? '';
        $skift = intval($_GET['skift'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || $skift <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltiga parametrar']);
            return;
        }

        try {
            // Hämta rådata per timme för detta skift — kumulativa max/min-värden
            $stmt = $this->pdo->prepare('
                SELECT
                    HOUR(datum) AS timme,
                    MAX(ibc_ok)  AS ibc_max,
                    MIN(ibc_ok)  AS ibc_min,
                    MAX(runtime_plc) AS runtime_cumulative
                FROM rebotling_ibc
                WHERE DATE(datum) = ?
                  AND skiftraknare = ?
                GROUP BY HOUR(datum)
                ORDER BY timme
            ');
            $stmt->execute([$datum, $skift]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna delta per timme (kumulativa fält)
            $trend = [];
            $prevIbc = null;
            $accIbc  = 0;
            foreach ($rows as $r) {
                $delta = ($prevIbc === null) ? 0 : max(0, (int)$r['ibc_max'] - (int)$prevIbc);
                $accIbc += $delta;
                $trend[] = [
                    'timme'          => (int)$r['timme'],
                    'ibc_ok'         => $delta,
                    'ackumulerat_ibc'=> $accIbc,
                    'takt_ibc_per_h' => $delta, // delta IS takt over 1h
                    'runtime'        => (int)$r['runtime_cumulative'],
                ];
                $prevIbc = (int)$r['ibc_max'];
            }

            // Genomsnittsprofil: samma veckodag de senaste 28 dagarna, samma skift-nummer
            $stmt2 = $this->pdo->prepare('
                SELECT HOUR(datum) AS timme, AVG(delta_ok) AS snitt_ibc_timma
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        HOUR(datum) AS h,
                        skiftraknare,
                        MAX(ibc_ok) - MIN(ibc_ok) AS delta_ok
                    FROM rebotling_ibc
                    WHERE DAYOFWEEK(datum) = DAYOFWEEK(?)
                      AND skiftraknare = ?
                      AND datum >= DATE_SUB(?, INTERVAL 28 DAY)
                      AND DATE(datum) != ?
                    GROUP BY DATE(datum), HOUR(datum), skiftraknare
                ) x
                GROUP BY timme
                ORDER BY timme
            ');
            $stmt2->execute([$datum, $skift, $datum, $datum]);
            $avgRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $avgProfile = [];
            foreach ($avgRows as $ar) {
                $avgProfile[] = [
                    'timme'           => (int)$ar['timme'],
                    'snitt_ibc_timma' => round((float)$ar['snitt_ibc_timma'], 1),
                ];
            }

            // Beräkna totala KPI-värden
            $totalIbc   = $accIbc;
            $hours      = count($trend) > 0 ? count($trend) : 1;
            $snitttakt  = $hours > 0 ? round($totalIbc / $hours, 1) : 0;

            $avgTotal     = array_sum(array_column($avgProfile, 'snitt_ibc_timma'));
            $avgHours     = count($avgProfile) > 0 ? count($avgProfile) : 1;
            $snitttaktAvg = $avgHours > 0 ? round($avgTotal / $avgHours, 1) : 0;

            $diffPct = ($snitttaktAvg > 0)
                ? round((($snitttakt - $snitttaktAvg) / $snitttaktAvg) * 100, 1)
                : null;

            echo json_encode([
                'success'      => true,
                'trend'        => $trend,
                'avg_profile'  => $avgProfile,
                'kpi' => [
                    'snitt_ibc_per_h'     => $snitttakt,
                    'snitt_ibc_per_h_avg' => $snitttaktAvg,
                    'diff_pct'            => $diffPct,
                    'total_ibc'           => $totalIbc,
                ],
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getShiftTrend: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }


    // GET ?action=rebotling&run=pareto-stoppage&days=30
    // Returnerar Pareto-analys (80/20) av stopporsaker.
    private function getParetoStoppage(): void {
        $days = max(1, min(365, intval($_GET['days'] ?? 30)));

        // Kontrollera att tabellerna finns
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name IN ('stoppage_log','stoppage_reasons')"
            );
            $found = (int)$check->fetchColumn();
            if ($found < 2) {
                echo json_encode([
                    'success'       => true,
                    'empty'         => true,
                    'reason'        => 'Tabellerna stoppage_log/stoppage_reasons finns inte än.',
                    'items'         => [],
                    'period_days'   => $days,
                    'total_stopp'   => 0,
                    'total_minuter' => 0
                ]);
                return;
            }
        } catch (Exception $e) {
            error_log('RebotlingController getParetoStoppage check: ' . $e->getMessage());
            echo json_encode([
                'success'       => true,
                'empty'         => true,
                'reason'        => 'Kunde inte kontrollera tabeller.',
                'items'         => [],
                'period_days'   => $days,
                'total_stopp'   => 0,
                'total_minuter' => 0
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                   COALESCE(r.name, s.reason_free) AS orsak,
                   COALESCE(r.category, 'övrigt')  AS kategori,
                   COUNT(*)                         AS antal_stopp,
                   COALESCE(SUM(s.duration_minutes), 0) AS total_minuter,
                   COALESCE(AVG(s.duration_minutes), 0) AS snitt_minuter
                 FROM stoppage_log s
                 LEFT JOIN stoppage_reasons r ON s.reason_id = r.id
                 WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND s.deleted_at IS NULL
                 GROUP BY r.id, COALESCE(r.name, s.reason_free)
                 ORDER BY total_minuter DESC
                 LIMIT 20"
            );
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Summering
            $stmtSum = $this->pdo->prepare(
                "SELECT COUNT(*) AS total_stopp,
                        COALESCE(SUM(duration_minutes), 0) AS total_minuter
                 FROM stoppage_log
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                   AND deleted_at IS NULL"
            );
            $stmtSum->execute([$days]);
            $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

            $totalStopp   = (int)($summary['total_stopp']   ?? 0);
            $totalMinuter = (float)($summary['total_minuter'] ?? 0);

            // Beräkna kumulativ procent
            $items = [];
            $kumulativMin = 0.0;
            foreach ($rows as $row) {
                $min = (float)$row['total_minuter'];
                $kumulativMin += $min;
                $pctAvTotal   = $totalMinuter > 0 ? round($min / $totalMinuter * 100, 1) : 0;
                $kumulativPct = $totalMinuter > 0 ? round($kumulativMin / $totalMinuter * 100, 1) : 0;
                $items[] = [
                    'orsak'         => $row['orsak'] ?? 'Okänd',
                    'kategori'      => $row['kategori'],
                    'antal_stopp'   => (int)$row['antal_stopp'],
                    'total_minuter' => round($min, 1),
                    'snitt_minuter' => round((float)$row['snitt_minuter'], 1),
                    'pct_av_total'  => $pctAvTotal,
                    'kumulativ_pct' => $kumulativPct
                ];
            }

            $empty = ($totalStopp === 0);

            echo json_encode([
                'success'       => true,
                'empty'         => $empty,
                'items'         => $items,
                'period_days'   => $days,
                'total_stopp'   => $totalStopp,
                'total_minuter' => round($totalMinuter, 1)
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getParetoStoppage: ' . $e->getMessage());
            echo json_encode([
                'success'       => true,
                'empty'         => true,
                'reason'        => 'Databasfel vid hämtning av paretodata.',
                'items'         => [],
                'period_days'   => $days,
                'total_stopp'   => 0,
                'total_minuter' => 0
            ]);
        }
    }
    // ============================================================
    // GET ?action=rebotling&run=all-lines-status
    //
    // Returnerar live-status för alla 4 produktionslinjer.
    // Rebotling: hämtar senaste data från rebotling_ibc.
    // Övriga linjer: kontrollerar om settings-tabellen finns.
    // Kräver admin-session.
    // ============================================================
    private function getAllLinesStatus() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
            return;
        }

        $lines = [];

        // --- Rebotling ---
        try {
            $tz  = new DateTimeZone('Europe/Stockholm');
            $now = new DateTime('now', $tz);

            // Senaste raden i rebotling_ibc
            $stmt = $this->pdo->query(
                "SELECT datum FROM rebotling_ibc ORDER BY datum DESC LIMIT 1"
            );
            $latestRow = $stmt->fetch(PDO::FETCH_ASSOC);

            $senaste_data_min = null;
            $kor = false;
            if ($latestRow && !empty($latestRow['datum'])) {
                $latestDt = new DateTime($latestRow['datum'], $tz);
                $diffSec  = $now->getTimestamp() - $latestDt->getTimestamp();
                $senaste_data_min = round($diffSec / 60, 1);
                $kor = ($diffSec < 600); // kör om senaste data < 10 min
            }

            // IBC idag
            $ibcIdag = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rebotling_ibc WHERE DATE(datum) = CURDATE()"
            )->fetchColumn();

            // OEE idag (om tabellen stödjer det) — hämta via befintlig logik
            $oeePct   = null;
            $malPct   = null;
            $dagsMal  = 1000;
            try {
                $sr = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($sr) $dagsMal = (int)$sr['rebotling_target'];
            } catch (Exception $e) { /* ignorera */ }

            if ($dagsMal > 0) {
                $malPct = round($ibcIdag / $dagsMal * 100, 1);
            }

            // OEE idag: hämta senaste skiftets aggregat
            try {
                $oeeRow = $this->pdo->query(
                    "SELECT
                        SUM(max_ibc_ok)      AS ibc_ok,
                        SUM(max_runtime_plc) AS runtime,
                        SUM(max_rasttime)    AS rasttime
                     FROM (
                        SELECT
                            skiftraknare,
                            MAX(ibc_ok)      AS max_ibc_ok,
                            MAX(runtime_plc) AS max_runtime_plc,
                            MAX(rasttime)    AS max_rasttime
                        FROM rebotling_ibc
                        WHERE DATE(datum) = CURDATE()
                        GROUP BY skiftraknare
                     ) agg"
                )->fetch(PDO::FETCH_ASSOC);

                if ($oeeRow && $oeeRow['runtime'] > 0) {
                    $ibcOk      = (float)$oeeRow['ibc_ok'];
                    $runtimeSek = (float)$oeeRow['runtime'];
                    $rastSek    = (float)$oeeRow['rasttime'];
                    $prodTid    = $runtimeSek - $rastSek;
                    if ($prodTid > 0) {
                        // Genomsnittlig cykeltid ~60 sek (fallback)
                        $snittCykel = 60;
                        try {
                            $cRow = $this->pdo->query(
                                "SELECT AVG(cykel_tid) FROM rebotling_ibc WHERE DATE(datum) = CURDATE() AND cykel_tid > 0"
                            )->fetchColumn();
                            if ($cRow > 0) $snittCykel = (float)$cRow;
                        } catch (Exception $e) { /* ignorera */ }
                        $maxMojlig = $prodTid / $snittCykel;
                        if ($maxMojlig > 0) {
                            $oeePct = round(($ibcOk / $maxMojlig) * 100, 1);
                        }
                    }
                }
            } catch (Exception $e) { error_log('getAllLinesStatus OEE: ' . $e->getMessage()); }

            $lines[] = [
                'id'              => 'rebotling',
                'namn'            => 'Rebotling',
                'kor'             => $kor,
                'senaste_data_min'=> $senaste_data_min,
                'ibc_idag'        => $ibcIdag,
                'oee_pct'         => $oeePct,
                'mal_pct'         => $malPct,
                'ej_i_drift'      => false
            ];
        } catch (Exception $e) {
            error_log('getAllLinesStatus rebotling: ' . $e->getMessage());
            $lines[] = [
                'id'         => 'rebotling',
                'namn'       => 'Rebotling',
                'kor'        => false,
                'ej_i_drift' => false
            ];
        }

        // --- Tvättlinje ---
        $lines[] = $this->getOtherLineStatus('tvattlinje', 'Tvättlinje', 'tvattlinje_settings');

        // --- Såglinje ---
        $lines[] = $this->getOtherLineStatus('saglinje', 'Såglinje', 'saglinje_settings');

        // --- Klassificeringslinje ---
        $lines[] = $this->getOtherLineStatus('klassificeringslinje', 'Klassificeringslinje', 'klassificeringslinje_settings');

        echo json_encode(['success' => true, 'lines' => $lines]);
    }

    private function getOtherLineStatus(string $id, string $namn, string $settingsTable): array {
        try {
            $tables = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = " . $this->pdo->quote($settingsTable)
            )->fetchColumn();

            if ((int)$tables === 0) {
                return ['id' => $id, 'namn' => $namn, 'kor' => false, 'ej_i_drift' => true];
            }
            // Tabell finns — försök hämta aktiv-status
            return ['id' => $id, 'namn' => $namn, 'kor' => false, 'ej_i_drift' => true];
        } catch (Exception $e) {
            error_log("getAllLinesStatus $id: " . $e->getMessage());
            return ['id' => $id, 'namn' => $namn, 'kor' => false, 'ej_i_drift' => true];
        }
    }

    // =========================================================
    // E-postnotifikationer — inställningar
    // =========================================================

    /**
     * Säkerställ att notification_emails-kolumnen finns i rebotling_settings.
     */
    private function ensureNotificationEmailsColumn(): void {
        try {
            $this->ensureSettingsTable();
            $col = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'notification_emails'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                $this->pdo->exec(
                    "ALTER TABLE rebotling_settings
                     ADD COLUMN notification_emails TEXT NULL DEFAULT NULL"
                );
            }
        } catch (Exception $e) {
            error_log('ensureNotificationEmailsColumn: ' . $e->getMessage());
        }
    }

    /**
     * GET ?action=rebotling&run=notification-settings
     * Returnerar aktuella e-postadresser för notifikationer.
     */
    private function getNotificationSettings(): void {
        try {
            $this->ensureNotificationEmailsColumn();
            $row = $this->pdo->query(
                "SELECT notification_emails FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => [
                    'notification_emails' => $row['notification_emails'] ?? '',
                ],
            ]);
        } catch (Exception $e) {
            error_log('getNotificationSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta notifikationsinställningar']);
        }
    }

    /**
     * POST ?action=rebotling&run=save-notification-settings
     * Sparar e-postadresser (semikolonseparerade) för brådskande notifikationer.
     */
    private function saveNotificationSettings(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig data']);
            return;
        }
        try {
            $this->ensureNotificationEmailsColumn();

            $rawEmails = isset($data['notification_emails']) ? trim($data['notification_emails']) : '';
            // Tillåt komma eller semikolon som separator — normalisera till semikolon
            $rawEmails = str_replace(',', ';', $rawEmails);
            // Validera varje e-postadress
            $parts  = array_map('trim', explode(';', $rawEmails));
            $valid  = [];
            foreach ($parts as $email) {
                if ($email === '') continue;
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Ogiltig e-postadress: $email"]);
                    return;
                }
                $valid[] = $email;
            }
            $normalized = implode(';', $valid);

            $stmt = $this->pdo->prepare(
                "UPDATE rebotling_settings SET notification_emails = ? WHERE id = 1"
            );
            $stmt->execute([$normalized]);

            echo json_encode(['success' => true, 'message' => 'Notifikationsinställningar sparade']);
        } catch (Exception $e) {
            error_log('saveNotificationSettings: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara notifikationsinställningar']);
        }
    }

    /**
     * Hämta admin-e-postadresser från rebotling_settings.
     * Returnerar array med validerade e-postadresser.
     * Används av ShiftHandoverController vid brådskande notiser.
     */
    public function getAdminEmailsPublic(): array {
        try {
            $this->ensureNotificationEmailsColumn();
            $row = $this->pdo->query(
                "SELECT notification_emails FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            if (empty($row['notification_emails'])) {
                return [];
            }
            $parts  = array_map('trim', explode(';', $row['notification_emails']));
            $emails = [];
            foreach ($parts as $email) {
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
            return $emails;
        } catch (Exception $e) {
            error_log('RebotlingController getAdminEmailsPublic: ' . $e->getMessage());
            return [];
        }
    }


    // =========================================================
    // Personbästa per operatör vs teamrekord
    // GET ?action=rebotling&run=personal-bests
    // =========================================================
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

            $result = array_map(function($r) use ($teamRecord) {
                $bestIbcH = round(floatval($r['best_ibc_h'] ?? 0), 2);
                return [
                    'op_number'    => intval($r['op_number']),
                    'namn'         => $r['op_name'],
                    'initialer'    => strtoupper(substr($r['op_name'] ?? '', 0, 3)),
                    'best_ibc_h'   => $bestIbcH,
                    'best_kvalitet'=> round(floatval($r['best_kvalitet'] ?? 0), 1),
                    'pct_of_record'=> $teamRecord > 0 ? round($bestIbcH / $teamRecord * 100, 1) : 0,
                    'total_skift'  => intval($r['total_skift']),
                ];
            }, $rows);

            echo json_encode([
                'success' => true,
                'data'    => [
                    'operators'        => $result,
                    'team_record_ibc_h'=> round($teamRecord, 2),
                ],
            ]);
        } catch (\Exception $e) {
            error_log('getPersonalBests: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta personbästa-data']);
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
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta månadsdata']);
        }
    }


    private function getAttendance() {
        if (session_status() === PHP_SESSION_NONE) session_start();
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
     * GET ?action=rebotling&run=goal-history
     * Returnerar historik för dagsmål-ändringar
     */
    private function getGoalHistory() {
        if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
            return;
        }

        $days = intval($_GET['days'] ?? 180);
        if ($days < 1 || $days > 730) $days = 180;

        try {
            // Skapa tabellen om den inte finns
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rebotling_goal_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    goal_type VARCHAR(50) NOT NULL DEFAULT 'dagmal',
                    value INT NOT NULL,
                    changed_by VARCHAR(100),
                    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type_time (goal_type, changed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $stmt = $this->pdo->prepare("
                SELECT goal_type, value, changed_by, changed_at
                FROM rebotling_goal_history
                WHERE changed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY changed_at ASC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Om inga rader: returnera nuvarande värde som startpunkt
            if (empty($rows)) {
                $current = $this->pdo->query(
                    "SELECT rebotling_target FROM rebotling_settings WHERE id = 1 LIMIT 1"
                )->fetchColumn();
                if ($current !== false) {
                    $rows = [[
                        'goal_type'  => 'dagmal',
                        'value'      => (int)$current,
                        'changed_by' => 'system',
                        'changed_at' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))
                    ]];
                }
            }

            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            error_log('getGoalHistory: ' . $e->getMessage());
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
            $this->sendError('Fel vid hämtning av produktionsrytm');
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
    private function createRecordNewsManual(): void {
        try {
            // Hämta dagens IBC-total
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

            if ($ibcIdag <= 0) {
                echo json_encode(['success' => false, 'error' => 'Ingen IBC-data för idag']);
                return;
            }

            // Hämta historiskt rekord (bästa dag före idag)
            $stmtRekord = $this->pdo->query("
                SELECT MAX(dag_total) AS rekord_ibc
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
            $newId = (int)$this->pdo->lastInsertId();

            echo json_encode([
                'success'   => true,
                'id'        => $newId,
                'ibc_idag'  => $ibcIdag,
                'rekord_ibc'=> $rekordIbc,
                'message'   => 'Rekordnyhet skapad!'
            ]);

        } catch (\Exception $e) {
            error_log('createRecordNewsManual: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    // ========== Logga underhållsåtgärd ==========
    private function saveMaintenanceLog(): void {
        try {
            $body = json_decode(file_get_contents('php://input'), true);
            $actionText = trim($body['action_text'] ?? '');
            if (strlen($actionText) === 0 || strlen($actionText) > 1000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Åtgärdstext saknas eller är för lång (max 1000 tecken)']);
                return;
            }
            $userId = $_SESSION['user_id'] ?? null;

            // Försök att infoga i underhållslogg-tabellen om den finns
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO rebotling_maintenance_log (action_text, logged_by_user_id, logged_at)
                    VALUES (:action_text, :user_id, NOW())
                ");
                $stmt->execute([
                    ':action_text' => $actionText,
                    ':user_id'     => $userId,
                ]);
                echo json_encode(['success' => true, 'message' => 'Underhållsåtgärd sparad']);
            } catch (\Exception $tableErr) {
                // Tabellen finns inte ännu — returnera ändå success så frontend inte kraschar
                error_log('saveMaintenanceLog: rebotling_maintenance_log saknas: ' . $tableErr->getMessage());
                echo json_encode(['success' => true, 'message' => 'Noterat (logg-tabell ej konfigurerad)']);
            }
        } catch (\Exception $e) {
            error_log('saveMaintenanceLog: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    // =========================================================================
    // Kassationsorsaksanalys
    // =========================================================================

    /**
     * GET ?action=rebotling&run=kassation-typer
     * Returnerar alla aktiva kassationsorsakstyper.
     */
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
        $kommentar  = isset($data['kommentar'])   ? trim($data['kommentar'])   : null;
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
                echo json_encode(['success' => true, 'items' => [], 'fallback' => true]);
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
    private function getGoalExceptions() {
        $month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])
            ? $_GET['month']
            : null;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT datum, justerat_mal, orsak
                 FROM produktionsmal_undantag
                 WHERE (:month IS NULL OR DATE_FORMAT(datum, '%Y-%m') = :month)
                 ORDER BY datum ASC"
            );
            $stmt->execute([':month' => $month]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exceptions = array_map(function ($r) {
                return [
                    'datum'        => $r['datum'],
                    'justerat_mal' => (int)$r['justerat_mal'],
                    'orsak'        => $r['orsak'],
                ];
            }, $rows);
            echo json_encode(['success' => true, 'exceptions' => $exceptions]);
        } catch (Exception $e) {
            error_log('getGoalExceptions: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta undantag']);
        }
    }

    /**
     * POST ?action=rebotling&run=save-goal-exception
     * Body: {"datum":"YYYY-MM-DD","justerat_mal":40,"orsak":"..."}
     */
    private function saveGoalException() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig JSON']);
            return;
        }
        $datum = $data['datum'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat (YYYY-MM-DD krävs)']);
            return;
        }
        $mal = intval($data['justerat_mal'] ?? 0);
        if ($mal <= 0 || $mal >= 10000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Justerat mål måste vara mellan 1 och 9999']);
            return;
        }
        $orsak = isset($data['orsak']) ? mb_substr(trim((string)$data['orsak']), 0, 255) : null;
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $stmt = $this->pdo->prepare(
                'INSERT INTO produktionsmal_undantag (datum, justerat_mal, orsak, skapad_av)
                 VALUES (:datum, :mal, :orsak, :uid)
                 ON DUPLICATE KEY UPDATE
                   justerat_mal  = VALUES(justerat_mal),
                   orsak         = VALUES(orsak),
                   uppdaterad_at = NOW()'
            );
            $stmt->execute([
                ':datum' => $datum,
                ':mal'   => $mal,
                ':orsak' => $orsak,
                ':uid'   => $userId,
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log('saveGoalException: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara undantag']);
        }
    }

    /**
     * POST ?action=rebotling&run=delete-goal-exception
     * Body: {"datum":"YYYY-MM-DD"}
     */
    private function deleteGoalException() {
        $data = json_decode(file_get_contents('php://input'), true);
        $datum = $data['datum'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datumformat']);
            return;
        }
        try {
            $stmt = $this->pdo->prepare('DELETE FROM produktionsmal_undantag WHERE datum = :datum');
            $stmt->execute([':datum' => $datum]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log('deleteGoalException: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort undantag']);
        }
    }


    /**
     * GET ?action=rebotling&run=oee-components&days=14
     * Returnerar dagliga trendlinjer för OEE-komponenterna Tillgänglighet och Kvalitet.
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
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta OEE-komponentdata']);
        }
    }

    /**
     * GET ?action=rebotling&run=service-status  (publik — ingen auth)
     */
    private function getServiceStatus(): void {
        try {
            // Hämta inställningar från rebotling_settings (key-value)
            $keys = ['service_interval_ibc', 'last_service_ibc_total', 'last_service_at', 'last_service_note'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare("SELECT `key`, `value` FROM rebotling_settings WHERE `key` IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $serviceInterval     = max(1, intval($rows['service_interval_ibc']   ?? 5000));
            $lastServiceIbcTotal = intval($rows['last_service_ibc_total']         ?? 0);
            $lastServiceAt       = $rows['last_service_at']                       ?? null;
            $lastServiceNote     = $rows['last_service_note']                     ?? null;

            // Beräkna total IBC producerat (MAX per skift, summerat)
            $stmtIbc = $this->pdo->query(
                "SELECT COALESCE(SUM(shift_max), 0) AS total FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS shift_max
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) t"
            );
            $totalIbc = intval($stmtIbc->fetchColumn());

            $ibcSedanService = $totalIbc - $lastServiceIbcTotal;
            $ibcKvar         = $serviceInterval - $ibcSedanService;
            $pctKvar         = $serviceInterval > 0
                ? round(max(0, $ibcKvar) / $serviceInterval * 100, 1)
                : 0;

            if ($pctKvar > 25) {
                $status = 'ok';
            } elseif ($pctKvar > 10) {
                $status = 'warning';
            } else {
                $status = 'danger';
            }

            echo json_encode([
                'success'                => true,
                'service_interval'       => $serviceInterval,
                'last_service_at'        => $lastServiceAt,
                'last_service_note'      => $lastServiceNote,
                'ibc_total'              => $totalIbc,
                'ibc_sedan_service'      => $ibcSedanService,
                'ibc_kvar_till_service'  => $ibcKvar,
                'pct_kvar'               => $pctKvar,
                'status'                 => $status,
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController getServiceStatus: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    /**
     * POST ?action=rebotling&run=reset-service  (kräver admin)
     */
    private function resetService(): void {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $note = isset($body['note']) ? substr(strip_tags($body['note']), 0, 255) : '';

            // Hämta aktuell total IBC
            $stmtIbc = $this->pdo->query(
                "SELECT COALESCE(SUM(shift_max), 0) AS total FROM (
                    SELECT MAX(COALESCE(ibc_ok, 0)) AS shift_max
                    FROM rebotling_ibc
                    WHERE skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) t"
            );
            $totalIbc = intval($stmtIbc->fetchColumn());

            // Uppdatera last_service_ibc_total, last_service_at, last_service_note
            $settings = [
                'last_service_ibc_total' => strval($totalIbc),
                'last_service_at'        => date('Y-m-d H:i:s'),
                'last_service_note'      => $note,
            ];
            $stmt = $this->pdo->prepare(
                "INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
            );
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }

            echo json_encode([
                'success'            => true,
                'message'            => 'Service registrerad',
                'ibc_total_at_reset' => $totalIbc,
            ]);
        } catch (Exception $e) {
            error_log('RebotlingController resetService: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }

    /**
     * POST ?action=rebotling&run=save-service-interval  (kräver admin)
     */
    private function saveServiceInterval(): void {
        try {
            $body    = json_decode(file_get_contents('php://input'), true) ?? [];
            $interval = intval($body['service_interval_ibc'] ?? 5000);
            if ($interval < 100 || $interval > 50000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Intervall måste vara mellan 100 och 50 000 IBC.']);
                return;
            }
            $stmt = $this->pdo->prepare(
                "INSERT INTO rebotling_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
            );
            $stmt->execute(['service_interval_ibc', strval($interval)]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log('RebotlingController saveServiceInterval: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel']);
        }
    }


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
    private function getSkiftrapportList() {
        try {
            $operator = $_GET['operator'] ?? '';
            $limit  = max(1, min(500, (int)($_GET['limit'] ?? 100)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $where = '';
            $params = [];

            if ($operator !== '') {
                $where = 'WHERE (o1.name = :op1 OR o2.name = :op2 OR o3.name = :op3)';
                $params['op1'] = $operator;
                $params['op2'] = $operator;
                $params['op3'] = $operator;
            }

            // Räkna totalt antal rader (för pagination)
            $countSql = "
                SELECT COUNT(*) FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                {$where}
            ";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRows = (int)$countStmt->fetchColumn();

            // Hämta rader
            $sql = "
                SELECT
                    s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt,
                    s.drifttid, s.rasttime, s.lopnummer, s.skiftraknare,
                    s.op1, s.op2, s.op3,
                    s.created_at,
                    u.username AS user_name,
                    p.name AS product_name,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                {$where}
                ORDER BY s.datum DESC, s.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // KPI-sammanfattning
            $kpiSql = "
                SELECT
                    COALESCE(SUM(s.ibc_ok), 0)   AS total_ibc_ok,
                    COALESCE(SUM(s.totalt), 0)    AS total_totalt,
                    COALESCE(SUM(s.drifttid), 0)  AS total_drifttid,
                    COUNT(*)                       AS antal_skift
                FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                {$where}
            ";
            $kpiStmt = $this->pdo->prepare($kpiSql);
            $kpiStmt->execute($params);
            $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

            $antalSkift = (int)($kpi['antal_skift'] ?? 0);
            $totalIbc   = (int)($kpi['total_ibc_ok'] ?? 0);
            $snittPerSkift = $antalSkift > 0 ? round($totalIbc / $antalSkift, 1) : 0;

            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => $totalRows,
                'kpi' => [
                    'total_ibc'      => $totalIbc,
                    'snitt_per_skift' => $snittPerSkift,
                    'antal_skift'    => $antalSkift,
                ],
            ]);
        } catch (Exception $e) {
            error_log('getSkiftrapportList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftrapporter']);
        }
    }

    // =========================================================
    // Unika operatörer i skiftrapporter
    // GET ?action=rebotling&run=skiftrapport-operators
    // =========================================================
    private function getSkiftrapportOperators() {
        try {
            $sql = "
                SELECT DISTINCT name FROM (
                    SELECT o.name FROM rebotling_skiftrapport s
                    INNER JOIN operators o ON o.number = s.op1
                    WHERE s.op1 IS NOT NULL
                    UNION
                    SELECT o.name FROM rebotling_skiftrapport s
                    INNER JOIN operators o ON o.number = s.op2
                    WHERE s.op2 IS NOT NULL
                    UNION
                    SELECT o.name FROM rebotling_skiftrapport s
                    INNER JOIN operators o ON o.number = s.op3
                    WHERE s.op3 IS NOT NULL
                ) AS ops
                WHERE name IS NOT NULL AND name != ''
                ORDER BY name
            ";
            $stmt = $this->pdo->query($sql);
            $operators = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success' => true,
                'operators' => $operators,
            ]);
        } catch (Exception $e) {
            error_log('getSkiftrapportOperators: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörslista']);
        }
    }

    // =========================================================
    // Skiftsammanfattning — detaljvy per specifikt skift
    // GET ?action=rebotling&run=shift-summary&date=YYYY-MM-DD&shift=1|2|3
    // =========================================================
    private function getShiftSummary() {
        $date  = $_GET['date']  ?? '';
        $shift = (int)($_GET['shift'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $shift < 1 || $shift > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum eller skiftnummer (1-3)']);
            return;
        }

        try {
            // Hämta alla skiftrapporter för detta datum
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id, s.datum, s.ibc_ok, s.bur_ej_ok, s.ibc_ej_ok, s.totalt,
                    s.drifttid, s.rasttime, s.lopnummer, s.skiftraknare,
                    s.op1, s.op2, s.op3,
                    s.created_at,
                    u.username AS user_name,
                    p.name AS product_name,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN rebotling_products p ON s.product_id = p.id
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE s.datum = :date
                ORDER BY s.id
            ");
            $stmt->execute(['date' => $date]);
            $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Filtrera rader som tillhör det angivna skiftet baserat på timestamp
            // Skift 1 = förmiddag (06-14), skift 2 = eftermiddag (14-22), skift 3 = natt (22-06)
            $rows = [];
            foreach ($allRows as $row) {
                $timeStr = substr($row['datum'] ?? '', 11, 5);
                $rowShift = 1;
                if ($timeStr) {
                    $parts = explode(':', $timeStr);
                    $minutes = ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
                    if ($minutes >= 360 && $minutes < 840) $rowShift = 1;      // 06-14
                    elseif ($minutes >= 840 && $minutes < 1320) $rowShift = 2;  // 14-22
                    else $rowShift = 3;                                          // 22-06
                }
                if ($rowShift === $shift) {
                    $rows[] = $row;
                }
            }

            // Om vi inte matchade med timestamp, ta alla för datumet (fallback)
            if (count($rows) === 0 && count($allRows) > 0) {
                $rows = $allRows;
            }

            // Aggregera KPI:er
            $totalIbcOk  = 0;
            $totalBurEj  = 0;
            $totalIbcEj  = 0;
            $totalTotalt = 0;
            $totalDrift  = 0;
            $totalRast   = 0;
            $operatorNames = [];
            $products    = [];

            foreach ($rows as $r) {
                $totalIbcOk  += (int)($r['ibc_ok']    ?? 0);
                $totalBurEj  += (int)($r['bur_ej_ok']  ?? 0);
                $totalIbcEj  += (int)($r['ibc_ej_ok']  ?? 0);
                $totalTotalt += (int)($r['totalt']      ?? 0);
                $totalDrift  += (int)($r['drifttid']    ?? 0);
                $totalRast   += (int)($r['rasttime']    ?? 0);

                foreach (['op1_name', 'op2_name', 'op3_name'] as $opField) {
                    if (!empty($r[$opField])) {
                        $operatorNames[$r[$opField]] = true;
                    }
                }
                if (!empty($r['product_name'])) {
                    $products[$r['product_name']] = true;
                }
            }

            $kvalitet = $totalTotalt > 0
                ? round(($totalIbcOk / $totalTotalt) * 100, 1)
                : null;

            $planned = $totalDrift + $totalRast;
            $avail   = $planned > 0 ? min($totalDrift / $planned, 1) : null;
            $qualityRatio = $totalTotalt > 0 ? ($totalIbcOk / $totalTotalt) : null;
            $oee = ($avail !== null && $qualityRatio !== null)
                ? round($avail * $qualityRatio * 100, 1)
                : null;

            $ibcPerH = $totalDrift > 0
                ? round(($totalIbcOk / ($totalDrift / 60)), 1)
                : null;

            // Delta vs föregående skift — hämta föregående skifts totalt
            $prevShift = $shift - 1;
            $prevDate  = $date;
            if ($prevShift < 1) {
                $prevShift = 3;
                $prevDate  = date('Y-m-d', strtotime($date . ' -1 day'));
            }

            $prevStmt = $this->pdo->prepare("
                SELECT SUM(s.totalt) AS prev_totalt
                FROM rebotling_skiftrapport s
                WHERE s.datum = :date
            ");
            $prevStmt->execute(['date' => $prevDate]);
            $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);

            $prevTotalt = (int)($prevRow['prev_totalt'] ?? 0);
            $delta = $prevTotalt > 0 ? $totalTotalt - $prevTotalt : null;

            // Timvis produktion från PLC-data (om tillgänglig)
            $hourlyData = [];
            $skiftraknare = null;
            foreach ($rows as $r) {
                if (!empty($r['skiftraknare'])) {
                    $skiftraknare = (int)$r['skiftraknare'];
                    break;
                }
            }

            if ($skiftraknare) {
                $hourlyStmt = $this->pdo->prepare("
                    SELECT
                        HOUR(datum) AS timme,
                        COUNT(*)    AS antal,
                        MAX(COALESCE(ibc_ok, 0)) - MIN(COALESCE(ibc_ok, 0)) AS ibc_diff
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                      AND skiftraknare = :skift
                    GROUP BY HOUR(datum)
                    ORDER BY HOUR(datum)
                ");
                $hourlyStmt->execute(['date' => $date, 'skift' => $skiftraknare]);
                $hourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Skiftkommentar
            $kommentar = '';
            try {
                $komStmt = $this->pdo->prepare("
                    SELECT kommentar FROM rebotling_skift_kommentarer
                    WHERE datum = :datum AND skift_nr = :skift_nr
                    LIMIT 1
                ");
                $komStmt->execute(['datum' => $date, 'skift_nr' => $shift]);
                $komRow = $komStmt->fetch(PDO::FETCH_ASSOC);
                if ($komRow) $kommentar = $komRow['kommentar'] ?? '';
            } catch (Exception $e) {
                // Tabellen kanske inte finns — ignorera
            }

            $shiftNames = [1 => 'Förmiddag (06–14)', 2 => 'Eftermiddag (14–22)', 3 => 'Natt (22–06)'];

            echo json_encode([
                'success' => true,
                'data' => [
                    'date'       => $date,
                    'shift'      => $shift,
                    'shift_name' => $shiftNames[$shift] ?? "Skift $shift",
                    'total_ibc'  => $totalTotalt,
                    'ibc_ok'     => $totalIbcOk,
                    'bur_ej_ok'  => $totalBurEj,
                    'ibc_ej_ok'  => $totalIbcEj,
                    'kvalitet'   => $kvalitet,
                    'oee'        => $oee,
                    'ibc_per_h'  => $ibcPerH,
                    'drifttid'   => $totalDrift,
                    'rasttime'   => $totalRast,
                    'delta_vs_prev' => $delta,
                    'operators'  => array_keys($operatorNames),
                    'products'   => array_keys($products),
                    'reports'    => $rows,
                    'hourly'     => $hourlyData,
                    'kommentar'  => $kommentar,
                    'antal_rapporter' => count($rows),
                ]
            ]);
        } catch (Exception $e) {
            error_log('getShiftSummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftsammanfattning']);
        }
    }

    /**
     * Korrelationsanalys: underhållshändelser vs maskinstopp per vecka (senaste 12 veckorna).
     * Hämtar data från maintenance_log och stoppage_log, grupperat per ISO-vecka.
     */
    private function getMaintenanceCorrelation() {
        try {
            $weeks = intval($_GET['weeks'] ?? 12);
            if ($weeks < 4 || $weeks > 52) $weeks = 12;

            // Underhållshändelser per vecka (från maintenance_log)
            $stmtMaint = $this->pdo->prepare("
                SELECT
                    YEAR(start_time) AS yr,
                    WEEK(start_time, 1) AS wk,
                    CONCAT(YEAR(start_time), '-V', LPAD(WEEK(start_time, 1), 2, '0')) AS vecka,
                    COUNT(*) AS antal_underhall,
                    COALESCE(SUM(duration_minutes), 0) AS total_underhallstid
                FROM maintenance_log
                WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
                  AND status != 'avbokat'
                GROUP BY YEAR(start_time), WEEK(start_time, 1)
                ORDER BY yr ASC, wk ASC
            ");
            $stmtMaint->execute([':weeks' => $weeks]);
            $maintRows = $stmtMaint->fetchAll(PDO::FETCH_ASSOC);

            // Maskinstopp per vecka (från stoppage_log)
            $stmtStop = $this->pdo->prepare("
                SELECT
                    YEAR(start_time) AS yr,
                    WEEK(start_time, 1) AS wk,
                    CONCAT(YEAR(start_time), '-V', LPAD(WEEK(start_time, 1), 2, '0')) AS vecka,
                    COUNT(*) AS antal_stopp,
                    COALESCE(SUM(duration_minutes), 0) AS total_stopptid
                FROM stoppage_log
                WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
                  AND line = 'rebotling'
                GROUP BY YEAR(start_time), WEEK(start_time, 1)
                ORDER BY yr ASC, wk ASC
            ");
            $stmtStop->execute([':weeks' => $weeks]);
            $stopRows = $stmtStop->fetchAll(PDO::FETCH_ASSOC);

            // Bygg index per veckonyckel
            $maintIndex = [];
            foreach ($maintRows as $r) {
                $maintIndex[$r['vecka']] = $r;
            }
            $stopIndex = [];
            foreach ($stopRows as $r) {
                $stopIndex[$r['vecka']] = $r;
            }

            // Samla alla unika veckor
            $allKeys = array_unique(array_merge(array_keys($maintIndex), array_keys($stopIndex)));
            sort($allKeys);

            $series = [];
            foreach ($allKeys as $vecka) {
                $m = $maintIndex[$vecka] ?? null;
                $s = $stopIndex[$vecka] ?? null;
                $series[] = [
                    'vecka'               => $vecka,
                    'antal_underhall'      => $m ? (int)$m['antal_underhall'] : 0,
                    'total_underhallstid'  => $m ? (int)$m['total_underhallstid'] : 0,
                    'antal_stopp'          => $s ? (int)$s['antal_stopp'] : 0,
                    'total_stopptid'       => $s ? (int)$s['total_stopptid'] : 0,
                ];
            }

            // Beräkna KPI:er — jämför första halvan vs andra halvan
            $halfLen = max(1, intval(count($series) / 2));
            $firstHalf = array_slice($series, 0, $halfLen);
            $secondHalf = array_slice($series, $halfLen);

            $avgStoppForst = count($firstHalf) > 0
                ? round(array_sum(array_column($firstHalf, 'antal_stopp')) / count($firstHalf), 1)
                : 0;
            $avgStoppSenare = count($secondHalf) > 0
                ? round(array_sum(array_column($secondHalf, 'antal_stopp')) / count($secondHalf), 1)
                : 0;

            $avgUnderhallForst = count($firstHalf) > 0
                ? round(array_sum(array_column($firstHalf, 'antal_underhall')) / count($firstHalf), 1)
                : 0;
            $avgUnderhallSenare = count($secondHalf) > 0
                ? round(array_sum(array_column($secondHalf, 'antal_underhall')) / count($secondHalf), 1)
                : 0;

            // Förändring i stopp (negativ = förbättring)
            $stoppForandring = $avgStoppForst > 0
                ? round((($avgStoppSenare - $avgStoppForst) / $avgStoppForst) * 100, 1)
                : 0;

            // Beräkna enkel Pearson-korrelation mellan underhåll och stopp (vecka+1)
            $korrelation = null;
            if (count($series) >= 4) {
                $n = count($series) - 1;
                $xArr = []; // underhåll vecka i
                $yArr = []; // stopp vecka i+1
                for ($i = 0; $i < $n; $i++) {
                    $xArr[] = $series[$i]['antal_underhall'];
                    $yArr[] = $series[$i + 1]['antal_stopp'];
                }
                $xMean = array_sum($xArr) / $n;
                $yMean = array_sum($yArr) / $n;
                $num = 0; $denX = 0; $denY = 0;
                for ($i = 0; $i < $n; $i++) {
                    $dx = $xArr[$i] - $xMean;
                    $dy = $yArr[$i] - $yMean;
                    $num += $dx * $dy;
                    $denX += $dx * $dx;
                    $denY += $dy * $dy;
                }
                $den = sqrt($denX * $denY);
                $korrelation = $den > 0 ? round($num / $den, 2) : 0;
            }

            echo json_encode([
                'success'              => true,
                'series'               => $series,
                'kpi' => [
                    'avg_stopp_forsta_halvan'  => $avgStoppForst,
                    'avg_stopp_andra_halvan'   => $avgStoppSenare,
                    'avg_underh_forsta_halvan' => $avgUnderhallForst,
                    'avg_underh_andra_halvan'  => $avgUnderhallSenare,
                    'stopp_forandring_pct'     => $stoppForandring,
                    'korrelation'              => $korrelation,
                ],
                'weeks_param' => $weeks,
            ]);
        } catch (Exception $e) {
            error_log('getMaintenanceCorrelation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta korrelationsdata']);
        }
    }
}
