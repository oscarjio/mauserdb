<?php

declare(strict_types=1);

class TvattLinje {
    private WebhookProcessor $processor;
    public ModbusMaster $modbus;
    public PDO $db;

    public function __construct(WebhookProcessor $processor) {
        $this->processor = $processor;
        $this->db = $processor->db;
        $this->modbus = new ModbusMaster;
        $this->modbus->socket_protocol = "TCP"; 
        $this->modbus->host = "192.168.0.250"; // PLC Adress. Tvättlinje
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
        
        if($dbcount < 1){
            // Om inga IBCer är registrerade idag, starta på noll
            $ibc_count = 1;
        }elseif ($dbcount > 0) {
            // Om vi har IBCer registrerade idag, hämta vad räknaren på pucken stod på vid IBC nr1
            $stmt = $this->db->prepare("
                SELECT * 
                FROM tvattlinje_ibc 
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
        
        
        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_ibc (s_count, ibc_count)
            VALUES (:s_count, :ibc_count)
        ');
        
        $stmt->execute([
            's_count' => $_GET['count'],
            'ibc_count' => $ibc_count
        ]);
    }

    public function handleRunning(array $data): void {
        // Validera data
        if (!isset($data['order_id'], $data['amount'])) {
            throw new InvalidArgumentException('Missing required fields for order.placed');
        }

        // Förbered och kör SQL-query
        $stmt = $this->db->prepare('
            INSERT INTO orders (order_id, amount, created_at)
            VALUES (:order_id, :amount, NOW())
        ');
        
        $stmt->execute([
            'order_id' => $data['order_id'],
            'amount' => $data['amount']
        ]);
    }
    
}