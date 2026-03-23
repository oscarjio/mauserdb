<?php
class VpnController {
    private $pdo;
    private $managementHost = '127.0.0.1';
    private $managementPort = 7505;
    private $managementUser = '';
    private $managementPassword = '';

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;

        // Load VPN config from app_config.php
        $configFile = __DIR__ . '/../app_config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            if (isset($config['vpn'])) {
                $this->managementHost = $config['vpn']['host'] ?? '127.0.0.1';
                $this->managementPort = $config['vpn']['port'] ?? 7505;
                $this->managementUser = $config['vpn']['user'] ?? '';
                $this->managementPassword = $config['vpn']['password'] ?? '';
            }
        }
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Släpp session-låset innan nätverks-I/O (fsockopen till VPN management interface).
        // Session-data (user_id, role) finns kvar i minnet via $_SESSION.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($method === 'GET') {
            $this->getVpnStatus();
        } elseif ($method === 'POST') {
            $this->handlePost();
        } else {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Ogiltig metod'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function handlePost() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $command = $input['command'] ?? $input['action'] ?? '';

        if ($command === 'disconnect') {
            $commonName = isset($input['commonName']) ? trim($input['commonName']) : '';

            if ($commonName === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Common Name krävs.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (preg_match('/[\r\n]/', $commonName) || !preg_match('/^[\w\.\-@]+$/u', $commonName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt Common Name-format.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $result = $this->disconnectClient($commonName);
            if (!($result['success'] ?? false)) {
                http_response_code(502);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ogiltigt kommando.'], JSON_UNESCAPED_UNICODE);
    }

    private function disconnectClient($commonName) {
        $socket = @fsockopen($this->managementHost, $this->managementPort, $errno, $errstr, 5);

        if (!$socket) {
            error_log("VpnController::disconnectClient: fsockopen misslyckades — $errstr ($errno)");
            return [
                'success' => false,
                'error' => 'Kunde inte ansluta till management interface.'
            ];
        }

        stream_set_timeout($socket, 2);

        // Läs välkomstmeddelande innan vi skickar kommando
        $this->readUntilPrompt($socket, 1);

        $written = @fwrite($socket, "kill {$commonName}\n");
        if ($written === false) {
            @fclose($socket);
            error_log('VpnController::disconnectClient: fwrite misslyckades');
            return [
                'success' => false,
                'error' => 'Kunde inte skicka kommando till management interface.'
            ];
        }
        $response = $this->readUntilPrompt($socket, 2);
        fclose($socket);

        if (stripos($response, 'SUCCESS') !== false) {
            return [
                'success' => true,
                'message' => "Anslutningen för {$commonName} har avslutats."
            ];
        }

        $cleanResponse = trim($response);
        if ($cleanResponse === '') {
            $cleanResponse = 'Kunde inte koppla bort klienten.';
        }
        error_log('VpnController::disconnectClient: misslyckades — ' . $cleanResponse);

        return [
            'success' => false,
            'error' => 'Kunde inte koppla bort VPN-klienten.'
        ];
    }

    private function getVpnStatus() {
        $timings = [];
        $startTime = microtime(true);
        $socket = null;

        try {
            // Anslut till OpenVPN management interface
            $connectStart = microtime(true);
            $socket = @fsockopen($this->managementHost, $this->managementPort, $errno, $errstr, 5);
            $timings['connect'] = round((microtime(true) - $connectStart) * 1000, 2);
            
            if (!$socket) {
                error_log("VpnController::getVpnStatus: fsockopen misslyckades — $errstr ($errno)");
                http_response_code(502);
                echo json_encode([
                    'success' => false,
                    'error' => 'Kunde inte ansluta till OpenVPN management interface',
                    'timings' => $timings
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Sätt timeout för socket
            stream_set_timeout($socket, 2);

            // Läs välkomstmeddelandet (hoppa över autentisering)
            $welcomeStart = microtime(true);
            $welcome = $this->readUntilPrompt($socket, 1);
            $timings['welcome'] = round((microtime(true) - $welcomeStart) * 1000, 2);
            
            // Skicka status-kommando (status ger all information)
            $statusStart = microtime(true);
            $written = @fwrite($socket, "status\n");
            if ($written === false) {
                @fclose($socket);
                http_response_code(502);
                echo json_encode([
                    'success' => false,
                    'error' => 'Kunde inte skicka status-kommando till management interface',
                    'timings' => $timings
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            // Läs hela status-svaret - optimerad timeout
            $fullOutput = $this->readUntilPrompt($socket, 3);
            $timings['status_read'] = round((microtime(true) - $statusStart) * 1000, 2);
            
            // Om vi inte fick CLIENT_LIST, prova att läsa mer
            if (strpos($fullOutput, 'CLIENT_LIST') === false && strpos($fullOutput, 'ROUTING_TABLE') === false) {
                $additionalStart = microtime(true);
                usleep(100000); // 100ms istället för 500ms
                $additional = $this->readUntilPrompt($socket, 2);
                $fullOutput .= $additional;
                $timings['additional_read'] = round((microtime(true) - $additionalStart) * 1000, 2);
            }
            
            fclose($socket);

            // Parse status output
            $parseStart = microtime(true);
            $clients = $this->parseStatusOutput($fullOutput);
            $timings['parse'] = round((microtime(true) - $parseStart) * 1000, 2);
            
            // Lägg till debug-info om inga klienter hittades
            $debug = null;
            if (empty($clients)) {
                $debug = [
                    'raw_output_length' => strlen($fullOutput),
                    'has_client_list' => strpos($fullOutput, 'CLIENT_LIST') !== false,
                    'has_routing_table' => strpos($fullOutput, 'ROUTING_TABLE') !== false,
                    'lines_count' => substr_count($fullOutput, "\n"),
                ];
                // Logga rå-output till server-loggen (inte till klienten) för felsökning
                error_log('VpnController::getVpnStatus: inga klienter hittades — raw_output_length=' . strlen($fullOutput));
            }
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            $timings['total'] = $totalTime;
            
            $response = [
                'success' => true,
                'clients' => $clients,
                'total_connected' => count(array_filter($clients, function($c) { return $c['connected']; })),
                'total_clients' => count($clients),
                'timings' => $timings
            ];
            
            if ($debug !== null) {
                $response['debug'] = $debug;
            }
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            if (is_resource($socket)) {
                @fclose($socket);
            }
            error_log('VpnController::vid hämtning av VPN-status: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Fel vid hämtning av VPN-status'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function readUntilPrompt($socket, $timeout = 3) {
        $output = '';
        $startTime = microtime(true);
        $endFound = false;
        $lastDataTime = microtime(true);
        $emptyReads = 0;
        $consecutiveEnds = 0;
        
        while ((microtime(true) - $startTime) < $timeout && !$endFound) {
            $line = fgets($socket, 4096);
            if ($line === false) {
                $emptyReads++;
                // Om vi har fått flera tomma läsningar i rad, kontrollera timeout
                if ($emptyReads > 10) {
                    $meta = stream_get_meta_data($socket);
                    if ($meta['timed_out'] || ((microtime(true) - $lastDataTime) > 0.5)) {
                        break;
                    }
                }
                usleep(50000); // Vänta 50ms istället för 100ms
                continue;
            }
            
            $emptyReads = 0;
            $lastDataTime = microtime(true);
            $output .= $line;
            
            // OpenVPN skickar "END" när status är klar, eller prompt ">"
            $trimmedLine = trim($line);
            if ($trimmedLine === 'END') {
                $consecutiveEnds++;
                // Om vi får END, vänta kort för att se om det kommer mer data
                if ($consecutiveEnds >= 1) {
                    usleep(100000); // 100ms istället för 200ms
                    // Kolla om det finns mer data
                    $meta = stream_get_meta_data($socket);
                    if ((int)$meta['unread_bytes'] === 0) {
                        $endFound = true;
                        break;
                    }
                }
            } else if ($trimmedLine === '>') {
                $endFound = true;
                break;
            } else {
                $consecutiveEnds = 0;
            }
        }
        
        return $output;
    }

    private function parseStatusOutput($output) {
        $clients = [];
        $lines = explode("\n", $output);
        
        $inClientList = false;
        $inRoutingTable = false;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            $trimmedLine = trim($line);

            // Skip empty lines and command prompts
            if (empty($trimmedLine) || $trimmedLine === '>') {
                continue;
            }

            // Identifiera sektioner - OpenVPN använder CSV-format (komma-separerat)
            if (strpos($trimmedLine, 'HEADER,CLIENT_LIST') === 0) {
                $inClientList = true;
                $inRoutingTable = false;
                continue;
            }

            if (strpos($trimmedLine, 'HEADER,ROUTING_TABLE') === 0) {
                $inRoutingTable = true;
                $inClientList = false;
                continue;
            }

            // Hoppa över HEADER-rader
            if (strpos($trimmedLine, 'HEADER,') === 0) {
                continue;
            }

            if ($trimmedLine === 'END' || strpos($trimmedLine, 'GLOBAL_STATS') === 0 ||
                strpos($trimmedLine, 'TITLE,') === 0 || strpos($trimmedLine, 'TIME,') === 0) {
                $inClientList = false;
                $inRoutingTable = false;
                continue;
            }
            
            // Parse CLIENT_LIST (CSV-format)
            // Format: CLIENT_LIST,Common Name,Real Address,Virtual Address,Virtual IPv6 Address,Bytes Received,Bytes Sent,Connected Since,Connected Since (time_t),Username,Client ID,Peer ID,Data Channel Cipher
            // Exempel: CLIENT_LIST,client1,127.0.0.1:42554,10.8.0.2,,9635,4340,2025-11-13 07:48:21,1763016501,UNDEF,1,0,AES-256-GCM
            if ($inClientList && strpos($trimmedLine, 'CLIENT_LIST,') === 0) {
                $parts = explode(',', $trimmedLine);
                // Ta bort första elementet (CLIENT_LIST)
                array_shift($parts);
                
                if (count($parts) >= 3) {
                    $commonName = trim($parts[0] ?? '');
                    $realAddress = trim($parts[1] ?? '');
                    $virtualAddress = trim($parts[2] ?? '');
                    $bytesReceived = isset($parts[5]) && is_numeric($parts[5]) ? (int)$parts[5] : 0;
                    $bytesSent = isset($parts[6]) && is_numeric($parts[6]) ? (int)$parts[6] : 0;
                    $connectedSince = isset($parts[7]) ? trim($parts[7]) : '';
                    $connectedSinceTimestamp = isset($parts[8]) && is_numeric($parts[8]) ? (int)$parts[8] : 0;
                    $username = isset($parts[9]) ? trim($parts[9]) : '';
                    
                    // Acceptera alla rader med data som inte är header
                    if (!empty($commonName) && 
                        $commonName !== 'Common Name' && 
                        $commonName !== 'UNDEF' &&
                        ($realAddress !== '' || $virtualAddress !== '')) {
                        $clients[$commonName] = [
                            'common_name' => $commonName,
                            'real_address' => $realAddress,
                            'virtual_address' => $virtualAddress,
                            'bytes_received' => $bytesReceived,
                            'bytes_sent' => $bytesSent,
                            'connected_since' => $connectedSince,
                            'connected_since_timestamp' => $connectedSinceTimestamp,
                            'username' => $username,
                            'connected' => true,
                            'last_seen' => $connectedSinceTimestamp > 0 ? date('Y-m-d H:i:s', $connectedSinceTimestamp) : ($connectedSince ?: date('Y-m-d H:i:s'))
                        ];
                    }
                }
            }
            
            // Parse ROUTING_TABLE (CSV-format)
            // Format: ROUTING_TABLE,Virtual Address,Common Name,Real Address,Last Ref,Last Ref (time_t)
            // Exempel: ROUTING_TABLE,10.8.0.2,client1,127.0.0.1:42554,2025-11-13 07:48:27,1763016507
            if ($inRoutingTable && strpos($trimmedLine, 'ROUTING_TABLE,') === 0) {
                $parts = explode(',', $trimmedLine);
                // Ta bort första elementet (ROUTING_TABLE)
                array_shift($parts);
                
                if (count($parts) >= 3) {
                    $virtualAddress = trim($parts[0] ?? '');
                    $commonName = trim($parts[1] ?? '');
                    $realAddress = trim($parts[2] ?? '');
                    $lastRef = isset($parts[3]) ? trim($parts[3]) : '';
                    
                    if (!empty($commonName) && 
                        $commonName !== 'Common Name' && 
                        $commonName !== 'UNDEF') {
                        // Uppdatera eller skapa client entry
                        if (!isset($clients[$commonName])) {
                            $clients[$commonName] = [
                                'common_name' => $commonName,
                                'real_address' => $realAddress,
                                'virtual_address' => $virtualAddress,
                                'bytes_received' => 0,
                                'bytes_sent' => 0,
                                'connected' => true,
                                'last_seen' => $lastRef ?: date('Y-m-d H:i:s')
                            ];
                        } else {
                            // Uppdatera med routing info
                            if (!empty($virtualAddress)) {
                                $clients[$commonName]['virtual_address'] = $virtualAddress;
                            }
                            if (!empty($realAddress)) {
                                $clients[$commonName]['real_address'] = $realAddress;
                            }
                            if ($lastRef) {
                                $clients[$commonName]['last_seen'] = $lastRef;
                            }
                        }
                    }
                }
            }
        }
        
        // Konvertera till array och sortera efter senaste anslutning
        $clientsArray = array_values($clients);
        
        // Sortera: anslutna först, sedan efter senaste anslutning
        usort($clientsArray, function($a, $b) {
            if ($a['connected'] !== $b['connected']) {
                return $b['connected'] ? 1 : -1;
            }
            
            $timeA = isset($a['connected_since_timestamp']) && $a['connected_since_timestamp'] > 0 
                ? $a['connected_since_timestamp'] 
                : (isset($a['last_seen']) ? strtotime($a['last_seen']) : 0);
            $timeB = isset($b['connected_since_timestamp']) && $b['connected_since_timestamp'] > 0 
                ? $b['connected_since_timestamp'] 
                : (isset($b['last_seen']) ? strtotime($b['last_seen']) : 0);
            
            return $timeB <=> $timeA;
        });
        
        return $clientsArray;
    }
}


