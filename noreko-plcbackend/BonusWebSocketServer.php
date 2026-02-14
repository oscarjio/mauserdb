<?php
/**
 * BonusWebSocketServer.php
 * Real-time bonus tracking via WebSockets
 *
 * Installation:
 * composer require cboden/ratchet
 *
 * Start server:
 * php BonusWebSocketServer.php
 *
 * Connect from browser:
 * ws://localhost:8080
 */

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

class BonusTracker implements MessageComponentInterface {
    protected $clients;
    protected $pdo;
    protected $lastBonusData = [];

    public function __construct($pdo) {
        $this->clients = new \SplObjectStorage;
        $this->pdo = $pdo;
        echo "WebSocket Server started!\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";

        // Send initial data
        $conn->send(json_encode([
            'type' => 'welcome',
            'message' => 'Connected to Bonus Tracking Server',
            'timestamp' => date('Y-m-d H:i:s')
        ]));

        // Send current stats
        $this->sendCurrentStats($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if (!$data) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Invalid JSON']));
            return;
        }

        switch ($data['action'] ?? '') {
            case 'subscribe':
                $this->handleSubscribe($from, $data);
                break;

            case 'get_stats':
                $this->sendCurrentStats($from);
                break;

            case 'get_leaderboard':
                $this->sendLeaderboard($from, $data['period'] ?? date('Y-m'));
                break;

            case 'get_operator_live':
                $this->sendOperatorLive($from, $data['operator_id'] ?? null);
                break;

            default:
                $from->send(json_encode(['type' => 'error', 'message' => 'Unknown action']));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Handle subscription to specific data streams
     */
    private function handleSubscribe(ConnectionInterface $conn, array $data): void {
        $channel = $data['channel'] ?? 'all';

        // Store subscription info (you could use SplObjectStorage with metadata)
        $conn->send(json_encode([
            'type' => 'subscribed',
            'channel' => $channel,
            'message' => "Subscribed to channel: $channel"
        ]));
    }

    /**
     * Send current system stats
     */
    private function sendCurrentStats(ConnectionInterface $conn): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as cycles_today,
                    COUNT(DISTINCT COALESCE(op1, op2, op3)) as operators_active,
                    AVG(bonus_poang) as avg_bonus,
                    SUM(ibc_ok) as total_ibc_ok,
                    MAX(bonus_poang) as max_bonus
                FROM rebotling_ibc
                WHERE DATE(datum) = CURDATE()
                AND bonus_poang IS NOT NULL
            ");

            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $conn->send(json_encode([
                'type' => 'current_stats',
                'data' => [
                    'cycles_today' => (int)$stats['cycles_today'],
                    'operators_active' => (int)$stats['operators_active'],
                    'avg_bonus' => round((float)$stats['avg_bonus'], 2),
                    'total_ibc_ok' => (int)$stats['total_ibc_ok'],
                    'max_bonus' => round((float)$stats['max_bonus'], 2)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]));

        } catch (PDOException $e) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]));
        }
    }

    /**
     * Send leaderboard for period
     */
    private function sendLeaderboard(ConnectionInterface $conn, string $period): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(op1, op2, op3) as operator_id,
                    COUNT(*) as cycles,
                    AVG(bonus_poang) as avg_bonus,
                    SUM(bonus_poang) as total_bonus,
                    AVG(effektivitet) as avg_eff,
                    AVG(produktivitet) as avg_prod,
                    AVG(kvalitet) as avg_qual
                FROM rebotling_ibc
                WHERE DATE_FORMAT(datum, '%Y-%m') = :period
                AND bonus_poang IS NOT NULL
                GROUP BY COALESCE(op1, op2, op3)
                ORDER BY total_bonus DESC
                LIMIT 10
            ");

            $stmt->execute(['period' => $period]);
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format data
            $formatted = array_map(function($row) {
                return [
                    'operator_id' => (int)$row['operator_id'],
                    'cycles' => (int)$row['cycles'],
                    'avg_bonus' => round((float)$row['avg_bonus'], 2),
                    'total_bonus' => round((float)$row['total_bonus'], 2),
                    'avg_eff' => round((float)$row['avg_eff'], 2),
                    'avg_prod' => round((float)$row['avg_prod'], 2),
                    'avg_qual' => round((float)$row['avg_qual'], 2)
                ];
            }, $leaderboard);

            $conn->send(json_encode([
                'type' => 'leaderboard',
                'period' => $period,
                'data' => $formatted,
                'timestamp' => date('Y-m-d H:i:s')
            ]));

        } catch (PDOException $e) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]));
        }
    }

    /**
     * Send live operator data
     */
    private function sendOperatorLive(ConnectionInterface $conn, ?int $operator_id): void {
        if (!$operator_id) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'operator_id required']));
            return;
        }

        try {
            // Get latest cycle
            $stmt = $this->pdo->prepare("
                SELECT
                    datum,
                    produkt,
                    ibc_ok,
                    ibc_ej_ok,
                    bur_ej_ok,
                    runtime_plc,
                    effektivitet,
                    produktivitet,
                    kvalitet,
                    bonus_poang
                FROM rebotling_ibc
                WHERE (op1 = :operator_id OR op2 = :operator_id OR op3 = :operator_id)
                AND bonus_poang IS NOT NULL
                ORDER BY datum DESC
                LIMIT 1
            ");

            $stmt->execute(['operator_id' => $operator_id]);
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$latest) {
                $conn->send(json_encode([
                    'type' => 'operator_live',
                    'operator_id' => $operator_id,
                    'data' => null,
                    'message' => 'No data found'
                ]));
                return;
            }

            // Get today's stats
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as cycles_today,
                    AVG(bonus_poang) as avg_bonus_today,
                    SUM(ibc_ok) as ibc_ok_today
                FROM rebotling_ibc
                WHERE DATE(datum) = CURDATE()
                AND (op1 = :operator_id OR op2 = :operator_id OR op3 = :operator_id)
                AND bonus_poang IS NOT NULL
            ");

            $stmt->execute(['operator_id' => $operator_id]);
            $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $conn->send(json_encode([
                'type' => 'operator_live',
                'operator_id' => $operator_id,
                'latest_cycle' => $latest,
                'today_stats' => [
                    'cycles' => (int)$today_stats['cycles_today'],
                    'avg_bonus' => round((float)$today_stats['avg_bonus_today'], 2),
                    'ibc_ok' => (int)$today_stats['ibc_ok_today']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]));

        } catch (PDOException $e) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]));
        }
    }

    /**
     * Broadcast new bonus data to all clients
     * Call this when new data is inserted
     */
    public function broadcastNewBonus(array $bonusData): void {
        $message = json_encode([
            'type' => 'new_bonus',
            'data' => $bonusData,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        foreach ($this->clients as $client) {
            $client->send($message);
        }

        echo "Broadcasted new bonus data to " . count($this->clients) . " clients\n";
    }

    /**
     * Start periodic stats broadcast (call from timer)
     */
    public function broadcastStats(): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as cycles_today,
                    COUNT(DISTINCT COALESCE(op1, op2, op3)) as operators_active,
                    AVG(bonus_poang) as avg_bonus,
                    SUM(ibc_ok) as total_ibc_ok,
                    MAX(bonus_poang) as max_bonus
                FROM rebotling_ibc
                WHERE DATE(datum) = CURDATE()
                AND bonus_poang IS NOT NULL
            ");

            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $message = json_encode([
                'type' => 'stats_update',
                'data' => [
                    'cycles_today' => (int)$stats['cycles_today'],
                    'operators_active' => (int)$stats['operators_active'],
                    'avg_bonus' => round((float)$stats['avg_bonus'], 2),
                    'total_ibc_ok' => (int)$stats['total_ibc_ok'],
                    'max_bonus' => round((float)$stats['max_bonus'], 2)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            foreach ($this->clients as $client) {
                $client->send($message);
            }

        } catch (PDOException $e) {
            echo "Error broadcasting stats: " . $e->getMessage() . "\n";
        }
    }
}

// Start WebSocket server
if (php_sapi_name() === 'cli') {
    $tracker = new BonusTracker($pdo);

    $server = IoServer::factory(
        new HttpServer(
            new WsServer($tracker)
        ),
        8080
    );

    // Setup periodic stats broadcast (every 10 seconds)
    $server->loop->addPeriodicTimer(10, function() use ($tracker) {
        $tracker->broadcastStats();
    });

    echo "WebSocket server running on port 8080\n";
    echo "Connect via: ws://localhost:8080\n";

    $server->run();
}
