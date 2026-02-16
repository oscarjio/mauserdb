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
            } elseif ($action === 'statistics') {
                $this->getStatistics();
            } elseif ($action === 'day-stats') {
                $this->getDayStats();
            } elseif ($action === 'oee') {
                $this->getOEE();
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
                    'utetemperatur' => $utetemperatur
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik: ' . $e->getMessage()
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

            echo json_encode([
                'success' => true,
                'data' => [
                    'running' => $isRunning,
                    'lastUpdate' => $lastUpdate
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta status: ' . $e->getMessage()
            ]);
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
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta admin-inställningar: ' . $e->getMessage()
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
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte spara inställningar: ' . $e->getMessage()
            ]);
        }
    }

    private function getStatistics() {
        try {
            $start = $_GET['start'] ?? date('Y-m-d');
            $end = $_GET['end'] ?? date('Y-m-d');

            // Hämta cykler för perioden med FAKTISK beräknad cykeltid och target från produkt
            $stmt = $this->pdo->prepare('
                SELECT 
                    i.datum,
                    i.ibc_count,
                    i.produktion_procent,
                    i.skiftraknare,
                    TIMESTAMPDIFF(MINUTE, 
                        LAG(i.datum) OVER (ORDER BY i.datum), 
                        i.datum
                    ) as cycle_time,
                    p.cycle_time_minutes as target_cycle_time
                FROM rebotling_ibc i
                LEFT JOIN rebotling_onoff o ON i.skiftraknare = o.skiftraknare
                LEFT JOIN rebotling_products p ON o.produkt = p.id
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
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta statistik: ' . $e->getMessage()
            ]);
        }
    }

    private function getDayStats() {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');

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

            echo json_encode([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'hourly_data' => $hourly_data,
                    'status_data' => $status_data
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta dagsstatistik: ' . $e->getMessage()
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
     */
    private function getOEE() {
        $period = $_GET['period'] ?? 'today';

        $dateFilter = match($period) {
            'today' => "DATE(r.datum) = CURDATE()",
            'week' => "r.datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "r.datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "DATE(r.datum) = CURDATE()"
        };

        try {
            // Hämta produktionsdata
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_cycles,
                    SUM(COALESCE(ibc_ok, 0)) as total_ibc_ok,
                    SUM(COALESCE(ibc_ej_ok, 0)) as total_ibc_ej_ok,
                    SUM(COALESCE(bur_ej_ok, 0)) as total_bur_ej_ok,
                    SUM(COALESCE(runtime_plc, 0)) as total_runtime_min,
                    SUM(COALESCE(rasttime, 0)) as total_rast_min
                FROM rebotling_ibc r
                WHERE $dateFilter
                  AND ibc_ok IS NOT NULL
            ");
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Hämta planerad produktionstid från on/off-data
            $stmt2 = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_events,
                    SUM(CASE WHEN running = 1 THEN 1 ELSE 0 END) as running_events,
                    MIN(datum) as first_event,
                    MAX(datum) as last_event
                FROM rebotling_onoff
                WHERE $dateFilter
            ");
            $stmt2->execute();
            $onoff = $stmt2->fetch(PDO::FETCH_ASSOC);

            $totalIBC = ($data['total_ibc_ok'] ?? 0) + ($data['total_ibc_ej_ok'] ?? 0);
            $goodIBC = $data['total_ibc_ok'] ?? 0;
            $runtimeMin = $data['total_runtime_min'] ?? 0;
            $rastMin = $data['total_rast_min'] ?? 0;
            $operatingMin = max($runtimeMin - $rastMin, 1);

            // Planerad tid: runtime (inkl. rast)
            $plannedMin = max($runtimeMin, 1);

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
                    'operating_hours' => round($operatingMin / 60, 1),
                    'cycles' => $data['total_cycles'] ?? 0,
                    'world_class_benchmark' => 85.0
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte beräkna OEE: ' . $e->getMessage()
            ]);
        }
    }
}
