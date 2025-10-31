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
        
        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_ibc (s_count, ibc_count)
            VALUES (:s_count, :ibc_count)
        ');
        
        $stmt->execute([
            's_count' => $_GET['count'],
            'ibc_count' => $ibc_count
        ]);
    }

    public function handleRunning(array $data): void {
        // Validera data
        if (!isset($_GET['high'], $_GET['low'])) {
            throw new InvalidArgumentException('Missing required fields high and low for handleRunning');
        }

        // Anropa Modbus!
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP"); // PLC IP

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
        $is_running = $_GET["running"] == "true" ? 1 : 0;
        $runtime_today = 0;

        // Hämta senaste entry från idag för att jämföra state changes
        $stmt = $this->db->prepare('
            SELECT s_count_l, s_count_h, runtime_today, running, datum, CURRENT_TIMESTAMP as tid
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

        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_onoff (s_count_h, s_count_l, runtime_today, running, program, op1, op2, op3, produkt, antal, runtime_plc)
            VALUES (:s_count_h, :s_count_l, :runtime_today, :running, :program, :op1, :op2, :op3, :produkt, :antal, :runtime_plc)
        ');
        
        $stmt->execute([
            's_count_h' => $high,
            's_count_l' => $low,
            'runtime_today' => $runtime_today,
            'running' => $is_running,
            'program' => $PLC_data16[0],
            'op1' => $PLC_data16[1],
            'op2' => $PLC_data16[2],
            'op3' => $PLC_data16[3],
            'produkt' => $PLC_data16[4],
            'antal' => $PLC_data16[5],
            'runtime_plc' => $PLC_data16[6]
        ]);
    }

    public function handleSkiftrapport(array $data): void {
        // Anropa Modbus!
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP"); // PLC IP

        // Hämta data från PLC (D210-D216 = 7 registers, starting at address 210)
        $PLC_data = $this->modbus->readMultipleRegisters(0, 210, 7);
        
        // Gör om 8bitars värden till 16bitar PLC D register är 16bit
        $PLC_data16 = array();
        for($i=0;$i<(count($PLC_data) / 2);$i++){
            $PLC_data16[$i] = $PLC_data[$i*2] << 8;
            $PLC_data16[$i] += $PLC_data[$i*2+1];
        }    

        /*
        D210 ibc_ok
        D211 bur_ej_ok
        D212 ibc_ej_ok
        D213 totalt
        D214 operator (user_id)
        D215 produkt (product_id)
        D216 drifttid
        */

        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO rebotling_skiftrapport (datum, ibc_ok, bur_ej_ok, ibc_ej_ok, totalt, user_id, product_id, drifttid)
            VALUES (CURDATE(), :ibc_ok, :bur_ej_ok, :ibc_ej_ok, :totalt, :operator, :produkt, :drifttid)
        ');
        
        $stmt->execute([
            'ibc_ok' => $PLC_data16[0],
            'bur_ej_ok' => $PLC_data16[1],
            'ibc_ej_ok' => $PLC_data16[2],
            'totalt' => $PLC_data16[3],
            'operator' => $PLC_data16[4],
            'produkt' => $PLC_data16[5],
            'drifttid' => $PLC_data16[6]
        ]);
    }
    
}