<?php

declare(strict_types=1);

class Rebotling {
    private WebhookProcessor $processor;
    public ModbusMaster $modbus;
    public PDO $db;

    public function __construct(WebhookProcessor $processor) {
        $this->processor = $processor;
        $this->db = $processor->db;
        
    }
    
    
    public function handleCycle(array $data): void {
        // Validera data
        if (!isset($_GET['count'])) {
            throw new InvalidArgumentException('Missing required fields for user.created');
        }

        // Hämta antal gjorda idag, rader i databasen
        $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM rebotling_ibc 
                WHERE DATE(datum) = CURDATE()
            ");
        $stmt->execute();
        $dbcount =  (int)$stmt->fetchColumn();
        
        if($dbcount < 1){
            // Om inga IBCer är registrerade idag, starta på noll
            $ibc_count = 1;
        }elseif ($dbcount > 0) {
            // Om vi har IBCer registrerade idag, hämta vad räknaren på pucken stod på vid IBC nr1
            $stmt = $this->db->prepare("
                SELECT * 
                FROM rebotling_ibc 
                WHERE DATE(datum) = CURDATE()
                AND ibc_count = 1
                LIMIT 1
            ");
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if($res["s_count"] < $_GET["count"]){
                // Allt ser normalt ut, räkna ut dagens IBCer
                $ibc_count = ($_GET["count"] - $res["s_count"]) + 1; // + 1 för att bli rätt med antalet räknade
            }elseif ($res["s_count"] >= $_GET["count"]) {
                $ibc_count = $dbcount + 1;
            }
        }
        
        // Hämta senaste skiftraknare från rebotling_onoff (total över alla dagar)
        $stmt = $this->db->prepare('
            SELECT skiftraknare
            FROM rebotling_onoff 
            WHERE skiftraknare IS NOT NULL
            ORDER BY datum DESC 
            LIMIT 1
        ');
        $stmt->execute();
        $skiftraknareResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $skiftraknare = $skiftraknareResult && isset($skiftraknareResult['skiftraknare']) 
            ? (int)$skiftraknareResult['skiftraknare'] 
            : 1; // Starta på 1 om inga rader finns
        
        // Beräkna produktion_procent för nuvarande tidpunkt
        $produktion_procent = 0;
        if ($skiftraknare !== null) {
            // Hämta produkt från nuvarande skift
            $hourlyTarget = 15; // Default värde
            $stmt = $this->db->prepare('
                SELECT produkt
                FROM rebotling_onoff 
                WHERE skiftraknare = ?
                AND produkt IS NOT NULL
                ORDER BY datum DESC 
                LIMIT 1
            ');
            $stmt->execute([$skiftraknare]);
            $produktResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($produktResult && isset($produktResult['produkt']) && $produktResult['produkt'] > 0) {
                $produktId = (int)$produktResult['produkt'];
                
                // Hämta cykeltid för produkten
                $stmt = $this->db->prepare('
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
            
            // Beräkna total runtime för nuvarande skift
            $totalRuntimeMinutes = 0;
            $stmt = $this->db->prepare('
                SELECT datum, running
                FROM rebotling_onoff 
                WHERE skiftraknare = ?
                ORDER BY datum ASC
            ');
            $stmt->execute([$skiftraknare]);
            $skiftEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($skiftEntries) > 0) {
                $lastRunningStart = null;
                $now = new DateTime();
                
                foreach ($skiftEntries as $entry) {
                    $entryTime = new DateTime($entry['datum']);
                    $isRunning = (bool)($entry['running'] ?? false);
                    
                    if ($isRunning && $lastRunningStart === null) {
                        $lastRunningStart = $entryTime;
                    } elseif (!$isRunning && $lastRunningStart !== null) {
                        $diff = $lastRunningStart->diff($entryTime);
                        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                        $totalRuntimeMinutes += $periodMinutes;
                        $lastRunningStart = null;
                    }
                }
                
                if ($lastRunningStart !== null) {
                    $lastEntryTime = new DateTime($skiftEntries[count($skiftEntries) - 1]['datum']);
                    $diff = $lastRunningStart->diff($lastEntryTime);
                    $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
                    $totalRuntimeMinutes += $periodMinutes;
                    
                    $diffSinceLast = $lastEntryTime->diff($now);
                    $minutesSinceLastUpdate = ($diffSinceLast->days * 24 * 60) + ($diffSinceLast->h * 60) + $diffSinceLast->i + ($diffSinceLast->s / 60);
                    $totalRuntimeMinutes += $minutesSinceLastUpdate;
                }
            }
            
            // Hämta antal IBCer för nuvarande skift (inklusive den som ska läggas till)
            $stmt = $this->db->prepare('
                SELECT COUNT(*) as ibc_count
                FROM rebotling_ibc 
                WHERE skiftraknare = ?
            ');
            $stmt->execute([$skiftraknare]);
            $ibcResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $ibcCount = $ibcResult ? (int)$ibcResult['ibc_count'] : 0;
            // Lägg till 1 för den IBC som ska läggas till
            $ibcCount += 1;
            
            // Beräkna produktionsprocent
            if ($totalRuntimeMinutes > 0 && $ibcCount > 0 && $hourlyTarget > 0) {
                $actualProductionPerHour = ($ibcCount * 60) / $totalRuntimeMinutes;
                $produktion_procent = round(($actualProductionPerHour / $hourlyTarget) * 100, 1);
            }
        }
        
        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_ibc (s_count, ibc_count, skiftraknare, produktion_procent)
            VALUES (:s_count, :ibc_count, :skiftraknare, :produktion_procent)
        ');
        
        $stmt->execute([
            's_count' => $_GET['count'],
            'ibc_count' => $ibc_count,
            'skiftraknare' => $skiftraknare,
            'produktion_procent' => $produktion_procent
        ]);
    }

    public function handleRunning(array $data): void {
        // Validera data
        if (!isset($_GET['high'], $_GET['low'])) {
            throw new InvalidArgumentException('Missing required fields high and low for handleRunning');
        }

        // Anropa Modbus!
        /*
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP"); // PLC IP


        // Återföring till PLC att data hämtats.
        $this->modbus->writeMultipleRegister(0, 199, array(0), array("INT"));
        sleep(1);
        // Hämta data från PLC
        
        $PLC_data = $this->modbus->readMultipleRegisters(0, 200, 7);
        // Gör om 8bitars värden till 16bitar PLC D register är 16bit
        $PLC_data16 = array();
        for($i=0;$i<(count($PLC_data) / 2);$i++){
            $PLC_data16[$i] = $PLC_data[$i*2] << 8;
            $PLC_data16[$i] += $PLC_data[$i*2+1];
        }    


        */
        /*
        D200 Program
        D201 Op1
        D202 Op2
        D203 Op3
        D204 Produkt
        D205 Antal
        D206 Runtime PLC
        */
        

        $high = (int)$_GET['high'];
        $low = (int)$_GET['low'];
        // Hantera running som kan vara "true", "1", eller 1
        $running_param = $_GET["running"] ?? "0";
        $is_running = ($running_param == "true" || $running_param == "1" || $running_param == 1) ? 1 : 0;
        $runtime_today = 0;
        // Hantera nyttskift som kan vara "1", 1, eller "true"
        $nyttskift = isset($_GET['nyttskift']) && ($_GET['nyttskift'] == "1" || $_GET['nyttskift'] == 1 || $_GET['nyttskift'] == "true");
        $skiftraknare = null;

        // Hämta senaste entry från idag för att jämföra state changes
        $stmt = $this->db->prepare('
            SELECT s_count_l, s_count_h, runtime_today, running, datum, skiftraknare, CURRENT_TIMESTAMP as tid
            FROM rebotling_onoff 
            WHERE DATE(datum) = CURDATE() 
            ORDER BY datum DESC 
            LIMIT 1
        ');
        $stmt->execute();
        $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        // Hämta dagens nuvarande runtime som bas
        $stmt = $this->db->prepare('
            SELECT COALESCE(MAX(runtime_today), 0) as current_runtime
            FROM rebotling_onoff 
            WHERE DATE(datum) = CURDATE()
        ');
        $stmt->execute();
        $currentRuntime = $stmt->fetch(PDO::FETCH_ASSOC);
        $runtime_today = $currentRuntime['current_runtime'];

        // Kontrollera om det är första gången running idag
        $is_first_running_today = false;
        if ($is_running == 1) {
            // Kontrollera om det finns några rader idag med running=1
            $stmt = $this->db->prepare('
                SELECT COUNT(*) as running_count
                FROM rebotling_onoff 
                WHERE DATE(datum) = CURDATE() 
                AND running = 1
            ');
            $stmt->execute();
            $runningCountResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $running_count_today = (int)($runningCountResult['running_count'] ?? 0);
            
            // Om det inte finns några running-rader idag, är detta första gången
            $is_first_running_today = ($running_count_today == 0);
        }

        // Kontrollera om det är en flankändring
        if ($lastEntry) {
            $prev_high = $lastEntry['s_count_h'];
            $prev_low = $lastEntry['s_count_l'];
            $prev_running = $lastEntry['running'];
            
            // Detektera hög flank (high har ökat) - maskinen startar
            $high_flank_detected = ($high > $prev_high);
            
            // Detektera låg flank (low har ökat) - maskinen stoppar
            $low_flank_detected = ($low > $prev_low);
            
            if ($low_flank_detected && $prev_running == 1) {
                // Låg flank detekterad och maskinen körde innan - beräkna runtime från senaste start
                $last_entry_time = new DateTime($lastEntry['datum']);
                $current_time = new DateTime($lastEntry['tid']);
                $interval = $last_entry_time->diff($current_time);
                
                // Beräkna runtime i minuter för denna period
                $runtime_period = ($interval->days * 24 * 60) + 
                                ($interval->h * 60) + 
                                $interval->i + 
                                round($interval->s / 60, 2); // Sekunder som bråkdel av minut
                
                // Lägg till denna period till dagens totala runtime
                $runtime_today += $runtime_period;
            }
            // Om det är en hög flank (start), behöver vi inte beräkna runtime än
            // Runtime beräknas när maskinen stoppar (låg flank)
            
        } else {
            // Första entry idag - ingen tidigare runtime att beräkna
            $runtime_today = 0;
        }

        // Hantera skiftraknare
        // Om det är första gången running idag, öka skifträknaren automatiskt
        if ($is_first_running_today) {
            // Hämta senaste skiftraknare från alla rader (total räknare)
            $stmt = $this->db->prepare('
                SELECT skiftraknare
                FROM rebotling_onoff 
                WHERE skiftraknare IS NOT NULL
                ORDER BY datum DESC 
                LIMIT 1
            ');
            $stmt->execute();
            $lastSkiftraknare = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastSkiftraknare && isset($lastSkiftraknare['skiftraknare'])) {
                // Räkna upp från senaste skiftraknare (total över alla dagar)
                $skiftraknare = (int)$lastSkiftraknare['skiftraknare'] + 1;
            } else {
                // Första skiftet någonsin, starta på 1
                $skiftraknare = 1;
            }
        } elseif ($nyttskift && $is_running == 1) {
            // Hantera skiftraknare om nyttskift=1 (oavsett running-status)
            // Hämta senaste skiftraknare från alla rader (total räknare)
            $stmt = $this->db->prepare('
                SELECT skiftraknare
                FROM rebotling_onoff 
                WHERE skiftraknare IS NOT NULL
                ORDER BY datum DESC 
                LIMIT 1
            ');
            $stmt->execute();
            $lastSkiftraknare = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastSkiftraknare && isset($lastSkiftraknare['skiftraknare'])) {
                // Räkna upp från senaste skiftraknare (total över alla dagar)
                $skiftraknare = (int)$lastSkiftraknare['skiftraknare'] + 1;
            } else {
                // Första skiftet någonsin, starta på 1
                $skiftraknare = 1;
            }
        } elseif ($lastEntry && isset($lastEntry['skiftraknare'])) {
            // Behåll samma skiftraknare som senaste entry om det inte är nytt skift
            $skiftraknare = $lastEntry['skiftraknare'];
        } else {
            // Om lastEntry inte har skiftraknare, hämta senaste från alla rader
            $stmt = $this->db->prepare('
                SELECT skiftraknare
                FROM rebotling_onoff 
                WHERE skiftraknare IS NOT NULL
                ORDER BY datum DESC 
                LIMIT 1
            ');
            $stmt->execute();
            $lastSkiftraknareAll = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastSkiftraknareAll && isset($lastSkiftraknareAll['skiftraknare'])) {
                $skiftraknare = (int)$lastSkiftraknareAll['skiftraknare'];
            } else {
                // Om inga rader finns med skiftraknare, starta på 1
                $skiftraknare = 1;
            }
        }

        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_onoff (s_count_h, s_count_l, runtime_today, running, program, op1, op2, op3, produkt, antal, runtime_plc, skiftraknare)
            VALUES (:s_count_h, :s_count_l, :runtime_today, :running, :program, :op1, :op2, :op3, :produkt, :antal, :runtime_plc, :skiftraknare)
        ');
        
        $stmt->execute([
            's_count_h' => $high,
            's_count_l' => $low,
            'runtime_today' => $runtime_today,
            'running' => $is_running,
            /*'program' => $PLC_data16[0],
            'op1' => $PLC_data16[1],
            'op2' => $PLC_data16[2],
            'op3' => $PLC_data16[3],
            'produkt' => $PLC_data16[4],
            'antal' => $PLC_data16[5],
            'runtime_plc' => $PLC_data16[6],*/
            'program' => 0,
            'op1' => 0,
            'op2' => 0,
            'op3' => 0,
            'produkt' => 1,
            'antal' => 0,
            'runtime_plc' => 0,
            'skiftraknare' => $skiftraknare
        ]);
    }

    public function handleSkiftrapport(array $data): void {
        // Anropa Modbus!
        /*
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP"); // PLC IP


        // Återföring för att PLC ska kunna larma om data inte hämtas.
        $this->modbus->writeMultipleRegister(0, 199, array(0), array("INT"));
        sleep(1);

        // Hämta data från PLC (D210-D216 = 7 registers, starting at address 210)
        $PLC_data = $this->modbus->readMultipleRegisters(0, 210, 7);
        
        // Gör om 8bitars värden till 16bitar PLC D register är 16bit
        $PLC_data16 = array();
        for($i=0;$i<(count($PLC_data) / 2);$i++){
            $PLC_data16[$i] = $PLC_data[$i*2] << 8;
            $PLC_data16[$i] += $PLC_data[$i*2+1];
        }    
        */
        /*
        D210 ibc_ok
        D211 bur_ej_ok
        D212 ibc_ej_ok
        D213 totalt
        D214 operator (user_id)
        D215 produkt (product_id)
        D216 drifttid
        */

        // Hämta nuvarande skifträknare från rebotling_onoff
        $stmt = $this->db->prepare('
            SELECT skiftraknare
            FROM rebotling_onoff 
            WHERE skiftraknare IS NOT NULL
            ORDER BY datum DESC 
            LIMIT 1
        ');
        $stmt->execute();
        $skiftraknareResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $skiftraknare = $skiftraknareResult && isset($skiftraknareResult['skiftraknare']) 
            ? (int)$skiftraknareResult['skiftraknare'] 
            : null; // Null om inga rader finns

        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_skiftrapport (datum, ibc_ok, bur_ej_ok, ibc_ej_ok, totalt, user_id, product_id, drifttid, skiftraknare)
            VALUES (CURDATE(), :ibc_ok, :bur_ej_ok, :ibc_ej_ok, :totalt, :operator, :produkt, :drifttid, :skiftraknare)
        ');
        
        $stmt->execute([
            'ibc_ok' => $PLC_data16[0],
            'bur_ej_ok' => $PLC_data16[1],
            'ibc_ej_ok' => $PLC_data16[2],
            'totalt' => $PLC_data16[3],
            'operator' => $PLC_data16[4],
            'produkt' => $PLC_data16[5],
            'drifttid' => $PLC_data16[6],
            'skiftraknare' => $skiftraknare
        ]);
    }

    public function handleRast(array $data): void {
        // Validera att rast-parameter finns
        if (!isset($_GET['rast'])) {
            throw new InvalidArgumentException('Missing required field: rast');
        }

        $rast_status = (int)$_GET['rast']; // 0 = arbetar, 1 = på rast

        // Hämta senaste status för att undvika duplicering
        $stmt = $this->db->prepare('
            SELECT rast_status 
            FROM rebotling_runtime 
            ORDER BY datum DESC 
            LIMIT 1
        ');
        $stmt->execute();
        $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastStatus = $lastEntry ? (int)$lastEntry['rast_status'] : -1;

        // Endast spara om status har ändrats
        if ($lastStatus !== $rast_status) {
            $stmt = $this->db->prepare('
                INSERT INTO rebotling_runtime (datum, rast_status) 
                VALUES (NOW(), :rast_status)
            ');
            
            $stmt->execute([
                'rast_status' => $rast_status
            ]);
        }
    }
    
}