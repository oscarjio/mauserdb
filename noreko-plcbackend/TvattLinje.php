<?php

declare(strict_types=1);

class TvattLinje {
    private WebhookProcessor $processor;
    public ModbusMaster $modbus;
    public PDO $db;

    public function __construct(WebhookProcessor $processor) {
        $this->processor = $processor;
        $this->db = $processor->db;
        /*
        $this->modbus = new ModbusMaster;
        $this->modbus->socket_protocol = "TCP"; 
        $this->modbus->host = "192.168.0.250"; // PLC Adress. Tvättlinje
        */
    }
    
    
    public function handleCycle(array $data): void {
        // Validera data
        if (!isset($_GET['count'])) {
            throw new InvalidArgumentException('Missing required fields for user.created');
        }

        // Hämta antal gjorda idag, rader i databasen
        $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM tvattlinje_ibc 
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
                FROM tvattlinje_ibc
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
                // Räknaren har resettat eller $res saknas
                $ibc_count = $dbcount + 1;
            }
        }
        
    /*    
        $this->modbus->writeMultipleRegister(0, 10, array(1,8,7,6), array("INT","INT","INT","INT"));
        
        sleep(1);
        // Hämta data från PLC
        $PLC_data = $this->modbus->readMultipleRegisters(0, 0, 20);

        // Gör om 8bitars värden till 16bitar
        $PLC_data16 = array();
        for($i=0;$i<(count($PLC_data) / 2);$i++){
            $PLC_data16[$i] = $PLC_data[$i*2] << 8;
            $PLC_data16[$i] += $PLC_data[$i*2+1];
        }    
        
        print_r($PLC_data16);
        die();
        
      */  
        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_ibc (s_count, ibc_count)
            VALUES (:s_count, :ibc_count)
        ');
        
        $stmt->execute([
            's_count' => $shellyCount,
            'ibc_count' => $ibc_count
        ]);
    }

    public function handleRunning(array $data): void {
        // Validera data
        if (!isset($_GET['high'], $_GET['low'])) {
            throw new InvalidArgumentException('Missing required fields high and low for handleRunning');
        }

        $high = (int)$_GET['high'];
        $low = (int)$_GET['low'];
        $running_param = $_GET["running"] ?? "0";
        $is_running = ($running_param == "true" || $running_param == "1" || $running_param == 1) ? 1 : 0;
        $runtime_today = 0;

        // Hämta senaste entry från idag för att jämföra state changes
        $stmt = $this->db->prepare('
            SELECT s_count_l, s_count_h, runtime_today, running, datum, CURRENT_TIMESTAMP as tid
            FROM tvattlinje_onoff 
            WHERE DATE(datum) = CURDATE() 
            ORDER BY datum DESC 
            LIMIT 1
        ');
        $stmt->execute();
        $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        // Hämta dagens nuvarande runtime som bas
        $stmt = $this->db->prepare('
            SELECT COALESCE(MAX(runtime_today), 0) as current_runtime
            FROM tvattlinje_onoff 
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
            INSERT INTO tvattlinje_onoff (s_count_h, s_count_l, runtime_today, running)
            VALUES (:s_count_h, :s_count_l, :runtime_today, :running)
        ');
        
        $stmt->execute([
            's_count_h' => $high,
            's_count_l' => $low,
            'runtime_today' => $runtime_today,
            'running' => $is_running
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
            FROM tvattlinje_runtime 
            ORDER BY datum DESC 
            LIMIT 1
        ');
        $stmt->execute();
        $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastStatus = $lastEntry ? (int)$lastEntry['rast_status'] : -1;

        // Endast spara om status har ändrats
        if ($lastStatus !== $rast_status) {
            $stmt = $this->db->prepare('
                INSERT INTO tvattlinje_runtime (datum, rast_status) 
                VALUES (NOW(), :rast_status)
            ');
            
            $stmt->execute([
                'rast_status' => $rast_status
            ]);
        }
    }
    
}