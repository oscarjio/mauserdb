<?php
/**
 * WebSocketBroadcaster.php
 * Helper class to broadcast bonus updates to WebSocket server
 *
 * Usage in Rebotling.php:
 * require_once 'WebSocketBroadcaster.php';
 * WebSocketBroadcaster::broadcast($bonusData);
 */

class WebSocketBroadcaster {
    private static $wsHost = 'localhost';
    private static $wsPort = 8080;
    private static $enabled = true;

    /**
     * Broadcast bonus data to WebSocket server
     */
    public static function broadcast(array $data): bool {
        if (!self::$enabled) {
            return false;
        }

        try {
            // Create WebSocket client connection
            $client = @stream_socket_client(
                "tcp://" . self::$wsHost . ":" . self::$wsPort,
                $errno,
                $errstr,
                2,  // 2 second timeout
                STREAM_CLIENT_CONNECT
            );

            if (!$client) {
                error_log("WebSocket broadcast failed: $errstr ($errno)");
                return false;
            }

            // Send WebSocket handshake
            $key = base64_encode(random_bytes(16));
            $handshake = "GET / HTTP/1.1\r\n" .
                        "Host: " . self::$wsHost . ":" . self::$wsPort . "\r\n" .
                        "Upgrade: websocket\r\n" .
                        "Connection: Upgrade\r\n" .
                        "Sec-WebSocket-Key: $key\r\n" .
                        "Sec-WebSocket-Version: 13\r\n\r\n";

            fwrite($client, $handshake);

            // Read handshake response
            $response = fread($client, 1500);

            if (strpos($response, '101 Switching Protocols') === false) {
                fclose($client);
                return false;
            }

            // Prepare broadcast message
            $message = json_encode([
                'action' => 'broadcast',
                'type' => 'new_bonus',
                'data' => $data
            ]);

            // Encode as WebSocket frame
            $frame = self::encodeFrame($message);

            // Send frame
            fwrite($client, $frame);

            fclose($client);

            return true;

        } catch (Exception $e) {
            error_log("WebSocket broadcast error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Encode message as WebSocket frame
     */
    private static function encodeFrame(string $message): string {
        $length = strlen($message);
        $frame = chr(0x81);  // Text frame, FIN bit set

        if ($length <= 125) {
            $frame .= chr($length | 0x80);  // Mask bit set
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80) . pack('n', $length);
        } else {
            $frame .= chr(127 | 0x80) . pack('J', $length);
        }

        // Generate masking key
        $mask = random_bytes(4);
        $frame .= $mask;

        // Mask the message
        for ($i = 0; $i < $length; $i++) {
            $frame .= $message[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }

    /**
     * Enable/disable broadcasting
     */
    public static function setEnabled(bool $enabled): void {
        self::$enabled = $enabled;
    }

    /**
     * Set WebSocket server address
     */
    public static function setServer(string $host, int $port): void {
        self::$wsHost = $host;
        self::$wsPort = $port;
    }

    /**
     * Quick broadcast for bonus calculation
     */
    public static function broadcastBonus(
        int $operator_id,
        float $bonus_poang,
        array $kpis
    ): bool {
        return self::broadcast([
            'operator_id' => $operator_id,
            'bonus_poang' => $bonus_poang,
            'effektivitet' => $kpis['effektivitet'] ?? 0,
            'produktivitet' => $kpis['produktivitet'] ?? 0,
            'kvalitet' => $kpis['kvalitet'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
