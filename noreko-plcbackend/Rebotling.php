<?php

declare(strict_types=1);

require_once __DIR__ . '/BonusCalculator.php';

class Rebotling {
    private WebhookProcessor $processor;
    public ModbusMaster $modbus;
    public PDO $db;
    private BonusCalculator $bonusCalculator;

    public function __construct(WebhookProcessor $processor) {
        $this->processor = $processor;
        $this->db = $processor->db;
        $this->bonusCalculator = new BonusCalculator();
    }
    
    
    public function handleCycle(array $data): void {
        // Validera data
        if (!isset($_GET['count'])) {
            throw new InvalidArgumentException('Missing required fields for user.created');
        }

        // === MODBUS TCP - LÄS FX5 D4000-D4009 ===
        try {
            $this->modbus = new ModbusMaster("192.168.0.200", "TCP");

            // Läs alla 10 register från D4000-D4009 i ett anrop
            $raw_data = $this->modbus->readMultipleRegisters(0, 4000, 10);
            $plc_data = $this->convert8to16bit($raw_data);

            // Extrahera värden enligt FX5-mappning
            $op1 = $plc_data[0];           // D4000 - Operatör 1
            $op2 = $plc_data[1];           // D4001 - Operatör 2
            $op3 = $plc_data[2];           // D4002 - Operatör 3
            $produkt = $plc_data[3];       // D4003 - Produkt
            $ibc_ok = $plc_data[4];        // D4004 - IBC OK
            $ibc_ej_ok = $plc_data[5];     // D4005 - IBC Ej OK
            $bur_ej_ok = $plc_data[6];     // D4006 - Bur Ej OK
            $runtime_plc = $plc_data[7];   // D4007 - Runtime
            $rasttime = $plc_data[8];      // D4008 - Rasttime
            $lopnummer = $plc_data[9];     // D4009 - Löpnummer

            // Validera PLC-data
            $this->validatePLCData($ibc_ok, $ibc_ej_ok, $bur_ej_ok, $runtime_plc, $produkt);

            // Beräkna KPI:er med förbättrad calculator
            $kpis = $this->bonusCalculator->calculateAdvancedKPIs([
                'ibc_ok' => $ibc_ok,
                'ibc_ej_ok' => $ibc_ej_ok,
                'bur_ej_ok' => $bur_ej_ok,
                'runtime_plc' => $runtime_plc
            ], $produkt);

        } catch (Exception $e) {
            error_log("ModbusTCP FX5 Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // Fallback: sätt default-värden om PLC inte svarar
            $op1 = $op2 = $op3 = $produkt = 0;
            $ibc_ok = $ibc_ej_ok = $bur_ej_ok = 0;
            $runtime_plc = $rasttime = $lopnummer = 0;
            $kpis = [
                'effektivitet' => 0,
                'produktivitet' => 0,
                'kvalitet' => 0,
                'bonus_poang' => 0
            ];
        }

        // Hämta antal gjorda idag, rader i databasen
        $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM rebotling_ibc 
                WHERE DATE(datum) = CURDATE()
            ");
        $stmt->execute();
        $dbcount =  (int)$stmt->fetchColumn();
        
        $shellyCount = (int)$_GET['count'];

        if ($dbcount < 1) {
            // Inga IBCer registrerade idag – starta på 1
            $ibc_count = 1;
        } else {
            // Hämta vad räknaren på pucken stod på vid IBC nr 1 idag
            $stmt = $this->db->prepare("
                SELECT s_count
                FROM rebotling_ibc
                WHERE DATE(datum) = CURDATE()
                AND ibc_count = 1
                LIMIT 1
            ");
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($res && (int)$res['s_count'] < $shellyCount) {
                // Normalt förlopp – räkna ut dagens IBCer
                $ibc_count = ($shellyCount - (int)$res['s_count']) + 1;
            } else {
                // Räknaren har resettat (PLC-omstart, nytt skift) eller $res saknas
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
        
        // Förbered och kör SQL-query med FX5-fält
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_ibc (
                s_count, ibc_count, skiftraknare, produktion_procent,
                op1, op2, op3, produkt, ibc_ok, ibc_ej_ok, bur_ej_ok,
                runtime_plc, rasttime, lopnummer,
                effektivitet, produktivitet, kvalitet, bonus_poang
            )
            VALUES (
                :s_count, :ibc_count, :skiftraknare, :produktion_procent,
                :op1, :op2, :op3, :produkt, :ibc_ok, :ibc_ej_ok, :bur_ej_ok,
                :runtime_plc, :rasttime, :lopnummer,
                :effektivitet, :produktivitet, :kvalitet, :bonus_poang
            )
        ');

        $stmt->execute([
            's_count' => $shellyCount,
            'ibc_count' => $ibc_count,
            'skiftraknare' => $skiftraknare,
            'produktion_procent' => $produktion_procent,
            'op1' => $op1,
            'op2' => $op2,
            'op3' => $op3,
            'produkt' => $produkt,
            'ibc_ok' => $ibc_ok,
            'ibc_ej_ok' => $ibc_ej_ok,
            'bur_ej_ok' => $bur_ej_ok,
            'runtime_plc' => $runtime_plc,
            'rasttime' => $rasttime,
            'lopnummer' => $lopnummer,
            'effektivitet' => $kpis['effektivitet'],
            'produktivitet' => $kpis['produktivitet'],
            'kvalitet' => $kpis['kvalitet'],
            'bonus_poang' => $kpis['bonus_poang']
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
        // Läs D4000-D4009 via Modbus – dessa innehåller skiftets kumulativa totaler
        // när PLC triggar skiftrapporten vid skiftets slut.
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP");
        $raw_data = $this->modbus->readMultipleRegisters(0, 4000, 10);
        $plc_data = $this->convert8to16bit($raw_data);

        $op1         = $plc_data[0]; // D4000 - Operatör Tvättplats
        $op2         = $plc_data[1]; // D4001 - Operatör Kontrollstation
        $op3         = $plc_data[2]; // D4002 - Operatör Truckförare
        $produkt     = $plc_data[3]; // D4003 - Produkt
        $ibc_ok      = $plc_data[4]; // D4004 - IBC OK
        $ibc_ej_ok   = $plc_data[5]; // D4005 - IBC Ej OK
        $bur_ej_ok   = $plc_data[6]; // D4006 - Bur Ej OK
        $drifttid    = $plc_data[7]; // D4007 - Runtime PLC (exkl rast)
        $rasttime    = $plc_data[8]; // D4008 - Rasttid PLC
        $lopnummer   = $plc_data[9]; // D4009 - Högsta löpnummer i skiftet

        $totalt = $ibc_ok + $ibc_ej_ok + $bur_ej_ok;

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
        $skiftraknare = $skiftraknareResult ? (int)$skiftraknareResult['skiftraknare'] : null;

        // Säkerställ att nya kolumner finns (migration 007)
        $this->ensureSkiftrapportColumns();

        $stmt = $this->db->prepare('
            INSERT INTO rebotling_skiftrapport
                (datum, ibc_ok, bur_ej_ok, ibc_ej_ok, totalt, product_id, user_id,
                 op1, op2, op3, drifttid, rasttime, lopnummer, skiftraknare)
            VALUES
                (CURDATE(), :ibc_ok, :bur_ej_ok, :ibc_ej_ok, :totalt, :product_id, :user_id,
                 :op1, :op2, :op3, :drifttid, :rasttime, :lopnummer, :skiftraknare)
        ');

        $stmt->execute([
            'ibc_ok'      => $ibc_ok,
            'bur_ej_ok'   => $bur_ej_ok,
            'ibc_ej_ok'   => $ibc_ej_ok,
            'totalt'      => $totalt,
            'product_id'  => $produkt,
            'user_id'     => $op1,   // Primäroperatör (Tvättplats) som user_id för bakåtkompatibilitet
            'op1'         => $op1,
            'op2'         => $op2,
            'op3'         => $op3,
            'drifttid'    => $drifttid,
            'rasttime'    => $rasttime,
            'lopnummer'   => $lopnummer,
            'skiftraknare' => $skiftraknare,
        ]);

        error_log("handleSkiftrapport: Skiftrapport sparad – ibc_ok=$ibc_ok, produkt=$produkt, skiftraknare=$skiftraknare");

        // Återföring till PLC – skriv 0 till D4014 för att kvittera att datan tagits emot
        $this->modbus->writeMultipleRegister(0, 4014, [0], ["INT"]);
    }

    /**
     * handleCommand – triggas av Y337 (Läs kommando).
     * Läser D4015 för att avgöra kommando:
     *   1 = Skicka skiftrapport (läser D4000-D4009 och sparar)
     *   2 = Ändra Löpnummer (läser D4010 och uppdaterar lopnummer)
     */
    public function handleCommand(array $data): void {
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP");

        // Läs kommandoregister D4015 (1 register)
        $raw_cmd = $this->modbus->readMultipleRegisters(0, 4015, 1);
        $cmd_data = $this->convert8to16bit($raw_cmd);
        $kommando = $cmd_data[0]; // D4015

        error_log("handleCommand: D4015 kommando=$kommando");

        switch ($kommando) {
            case 1:
                // Skicka skiftrapport – läs D4000-D4009 och spara
                $this->handleSkiftrapport($data);
                break;

            case 2:
                // Ändra Löpnummer – läs nytt nummer från D4010
                $raw_nr = $this->modbus->readMultipleRegisters(0, 4010, 1);
                $nr_data = $this->convert8to16bit($raw_nr);
                $nyttLopnummer = $nr_data[0]; // D4010

                // Logga löpnummerändringen i rebotling_ibc (uppdatera senaste raden idag)
                $stmt = $this->db->prepare('
                    UPDATE rebotling_ibc
                    SET lopnummer = :lopnummer
                    WHERE DATE(datum) = CURDATE()
                    ORDER BY datum DESC
                    LIMIT 1
                ');
                $stmt->execute(['lopnummer' => $nyttLopnummer]);

                error_log("handleCommand: Löpnummer ändrat till $nyttLopnummer");
                break;

            default:
                error_log("handleCommand: Okänt kommando D4015=$kommando, ignorerar.");
                break;
        }

        // Rensa D4015 så att nästa Y337-trigger inte kör samma kommando igen
        $this->modbus->writeMultipleRegister(0, 4015, [0], ["INT"]);
    }

    /**
     * Säkerställ att migration 007-kolumner finns i rebotling_skiftrapport.
     * Körs vid varje skiftrapport-insert – PDO SHOW COLUMNS är billigt.
     */
    private function ensureSkiftrapportColumns(): void {
        $columns = ['op1', 'op2', 'op3', 'drifttid', 'rasttime', 'lopnummer'];
        $existing = [];
        $stmt = $this->db->query("SHOW COLUMNS FROM rebotling_skiftrapport");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $existing[] = $col['Field'];
        }
        $definitions = [
            'op1'       => 'INT DEFAULT NULL',
            'op2'       => 'INT DEFAULT NULL',
            'op3'       => 'INT DEFAULT NULL',
            'drifttid'  => 'INT DEFAULT NULL',
            'rasttime'  => 'INT DEFAULT NULL',
            'lopnummer' => 'INT DEFAULT NULL',
        ];
        foreach ($columns as $col) {
            if (!in_array($col, $existing)) {
                $this->db->exec("ALTER TABLE rebotling_skiftrapport ADD COLUMN `$col` {$definitions[$col]}");
            }
        }
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

    // === FX5 HELPER FUNCTIONS ===

    /**
     * Konvertera 8-bit Modbus data till 16-bit register-värden
     * PHPModbus returnerar data som 8-bit bytes, men PLC D-register är 16-bit
     */
    private function convert8to16bit(array $data): array {
        $result = [];
        for ($i = 0; $i < (count($data) / 2); $i++) {
            $result[$i] = ($data[$i * 2] << 8) + $data[$i * 2 + 1];
        }
        return $result;
    }

    /**
     * Validera PLC-data för felaktiga värden
     * @throws InvalidArgumentException om data är ogiltig
     */
    private function validatePLCData(
        int $ibc_ok,
        int $ibc_ej_ok,
        int $bur_ej_ok,
        int $runtime_plc,
        int $produkt
    ): void {
        // 1. Negativa värden
        if ($ibc_ok < 0 || $ibc_ej_ok < 0 || $bur_ej_ok < 0 || $runtime_plc < 0) {
            throw new InvalidArgumentException(
                "PLC data validation failed: Negativa värden detekterade " .
                "(ibc_ok=$ibc_ok, ibc_ej_ok=$ibc_ej_ok, bur_ej_ok=$bur_ej_ok, runtime=$runtime_plc)"
            );
        }

        // 2. Runtime måste vara rimlig (minst 1 minut, max 8 timmar)
        if ($runtime_plc < 1 || $runtime_plc > 480) {
            error_log("WARNING: Ovanlig runtime: $runtime_plc minuter");
        }

        // 3. Bur kan inte vara fler än godkända IBC
        if ($bur_ej_ok > $ibc_ok) {
            error_log("WARNING: bur_ej_ok ($bur_ej_ok) > ibc_ok ($ibc_ok) - data verkar felaktig!");
            // Korrigera automatiskt
            $bur_ej_ok = min($bur_ej_ok, $ibc_ok);
        }

        // 4. Produkt ID måste vara giltig (1, 4, eller 5)
        $valid_products = [1, 4, 5];
        if (!in_array($produkt, $valid_products)) {
            error_log("WARNING: Ogiltig produkt-ID: $produkt (förväntat: 1, 4, eller 5)");
        }

        // 5. Sanity check på produktivitet (max 200 IBC/h är orimligt)
        if ($ibc_ok > 0 && $runtime_plc > 0) {
            $prod_check = ($ibc_ok * 60) / $runtime_plc;
            if ($prod_check > 200) {
                error_log("ALERT: Extremt hög produktivitet: $prod_check IBC/h - dubbelkolla PLC-data!");
            }
        }

        // 6. Total produktion sanity check (max 500 IBC per cykel är orimligt)
        $total = $ibc_ok + $ibc_ej_ok;
        if ($total > 500) {
            error_log("ALERT: Extremt hög total produktion: $total IBC - dubbelkolla PLC-data!");
        }
    }

    /**
     * DEPRECATED: Använd BonusCalculator istället
     * Beräkna KPI:er för bonussystem (Gammal version - endast för bakåtkompatibilitet)
     */
    private function calculateKPIs(array $data): array {
        // Redirect to new calculator
        return $this->bonusCalculator->calculateAdvancedKPIs($data, 1);
    }

}