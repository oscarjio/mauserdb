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
            if ($action === 'admin-settings') {
                $this->getAdminSettings();
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
            } else {
                $this->getLiveStats();
            }
            return;
        }

        if ($method === 'POST' && $action === 'admin-settings') {
            $this->saveAdminSettings();
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

            // TODO: Hämta verklig data från PLC/DB
            // Placeholdervärden för nu
            /*
            $nowHour = (int)date('G');
            $working = $nowHour >= 6 && $nowHour <= 18;
            $rebotlingToday = rand(40, 85);
            $rebotlingTarget = 120;
            */
            $rebotlingToday = 50;
            $rebotlingTarget = 120;

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

            // Hämta dagens alla rast-events sorterade stigande
            $stmt = $this->pdo->prepare("
                SELECT id, datum, rast_status
                FROM rebotling_runtime
                WHERE DATE(datum) = CURDATE()
                ORDER BY datum ASC
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna total rasttid genom att para ihop start(1) och slut(0)
            $totalRastMinutes = 0;
            $rastStart = null;
            $now = new DateTime();
            $currentlyOnRast = false;

            foreach ($events as $event) {
                if ((int)$event['rast_status'] === 1 && $rastStart === null) {
                    $rastStart = new DateTime($event['datum']);
                    $currentlyOnRast = true;
                } elseif ((int)$event['rast_status'] === 0 && $rastStart !== null) {
                    $end = new DateTime($event['datum']);
                    $diff = $rastStart->diff($end);
                    $totalRastMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $rastStart = null;
                    $currentlyOnRast = false;
                }
            }

            // Om rast pågår just nu, räkna in till nu
            if ($rastStart !== null) {
                $currentlyOnRast = true;
                $diff = $rastStart->diff($now);
                $totalRastMinutes += ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
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

    private function getAdminSettings() {
        try {
            // Hämta admin-inställningar från databasen eller returnera standardvärden
            $settings = [
                'rebotlingTarget' => 1000,
                'hourlyTarget' => 50,
                'systemSettings' => [
                    'autoStart' => false,
                    'maintenanceMode' => false,
                    'alertThreshold' => 80
                ]
            ];

            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte hämta admin-inställningar: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta admin-inställningar'
            ]);
        }
    }

    private function saveAdminSettings() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // TODO: Spara inställningar i databasen
            // För nu bara returnera success
            
            echo json_encode([
                'success' => true,
                'message' => 'Inställningar sparade',
                'data' => $data
            ]);
        } catch (Exception $e) {
            error_log('Kunde inte spara inställningar: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte spara inställningar'
            ]);
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
                    'summary' => [
                        'total_cycles' => $total_cycles,
                        'avg_production_percent' => round($avg_production_percent, 1),
                        'avg_cycle_time' => round($avg_cycle_time, 1),
                        'target_cycle_time' => round($target_cycle_time, 1),
                        'total_runtime_hours' => round($total_runtime_hours, 1),
                        'days_with_production' => $days_with_production
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
            $days = min(90, max(7, intval($_GET['days'] ?? 30)));

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
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag
            ");
            $stmt->execute([$days]);
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
        session_start();
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
}
