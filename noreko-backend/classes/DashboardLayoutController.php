<?php
/**
 * DashboardLayoutController.php
 * Hantera dashboard-widget-layout per user.
 *
 * Endpoints via ?action=dashboard-layout&run=XXX:
 *   GET  run=get-layout         — hämta sparad widgetlayout för inloggad user
 *   POST run=save-layout        — spara widgetordning + synlighet per user
 *   GET  run=available-widgets  — lista alla tillgängliga widgets
 */
class DashboardLayoutController {
    private $pdo;

    /** Tillgängliga widgets med id, namn, beskrivning */
    private const WIDGETS = [
        [
            'id'          => 'produktionspuls',
            'namn'        => 'Produktionspuls',
            'beskrivning' => 'Realtidsöversikt av dagens produktion med prognos och takt',
        ],
        [
            'id'          => 'veckotrend',
            'namn'        => 'Veckotrend',
            'beskrivning' => 'Sparklines med vecko-KPI:er (IBC, OEE, kvalitet)',
        ],
        [
            'id'          => 'oee-gauge',
            'namn'        => 'OEE-mätare',
            'beskrivning' => 'Gauge-diagram med aktuell OEE-nivå',
        ],
        [
            'id'          => 'bonus-simulator',
            'namn'        => 'Bonus-simulator',
            'beskrivning' => 'What-if-simulator för bonusberäkning',
        ],
        [
            'id'          => 'leaderboard',
            'namn'        => 'Leaderboard',
            'beskrivning' => 'Topp-5 operatörer rankade efter prestation',
        ],
        [
            'id'          => 'kassationsanalys-sammanfattning',
            'namn'        => 'Kassationsanalys',
            'beskrivning' => 'Sammanfattning av kassationsorsaker med Pareto-diagram',
        ],
        [
            'id'          => 'alerts-sammanfattning',
            'namn'        => 'Alerts/Varningar',
            'beskrivning' => 'Sammanfattning av aktiva realtidsvarningar',
        ],
        [
            'id'          => 'produktionsmal',
            'namn'        => 'Produktionsmål',
            'beskrivning' => 'Tracker för dagliga/vecko- och månadsmål',
        ],
    ];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'POST') {
                session_start();
            } else {
                session_start(['read_and_close' => true]);
            }
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'get-layout':
                $this->getLayout();
                break;
            case 'save-layout':
                if ($method !== 'POST') {
                    $this->sendError('Metod inte tillåten — använd POST', 405);
                    return;
                }
                $this->saveLayout();
                break;
            case 'available-widgets':
                $this->availableWidgets();
                break;
            default:
                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
                break;
        }
    }

    // ================================================================
    // ENDPOINT: get-layout
    // ================================================================
    private function getLayout(): void {
        $userId = (int)$_SESSION['user_id'];

        try {
            $stmt = $this->pdo->prepare(
                'SELECT layout_json, updated_at FROM dashboard_layouts WHERE user_id = :uid LIMIT 1'
            );
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $layout = json_decode($row['layout_json'], true);
                $this->sendSuccess([
                    'layout'     => is_array($layout) ? $layout : $this->defaultLayout(),
                    'updated_at' => $row['updated_at'],
                ]);
            } else {
                // Ingen sparad layout — returnera default
                $this->sendSuccess([
                    'layout'     => $this->defaultLayout(),
                    'updated_at' => null,
                ]);
            }
        } catch (\PDOException $e) {
            error_log('DashboardLayoutController::getLayout: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: save-layout (POST)
    // ================================================================
    private function saveLayout(): void {
        $userId = (int)$_SESSION['user_id'];

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || !isset($body['widgets']) || !is_array($body['widgets'])) {
            $this->sendError('Ogiltig JSON-payload — "widgets"-array krävs');
            return;
        }

        // Validera att varje widget-entry har id + visible
        $validIds = array_column(self::WIDGETS, 'id');
        $cleaned  = [];
        foreach ($body['widgets'] as $w) {
            if (!isset($w['id']) || !in_array($w['id'], $validIds, true)) {
                continue; // Hoppa över okända widgets
            }
            $cleaned[] = [
                'id'      => $w['id'],
                'visible' => !empty($w['visible']),
                'order'   => isset($w['order']) ? (int)$w['order'] : count($cleaned),
            ];
        }

        if (empty($cleaned)) {
            $this->sendError('Inga giltiga widgets i payload');
            return;
        }

        $layoutJson = json_encode($cleaned, JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO dashboard_layouts (user_id, layout_json, updated_at)
                 VALUES (:uid, :lj, NOW())
                 ON DUPLICATE KEY UPDATE layout_json = :lj2, updated_at = NOW()'
            );
            $stmt->execute([
                ':uid' => $userId,
                ':lj'  => $layoutJson,
                ':lj2' => $layoutJson,
            ]);

            $this->sendSuccess([
                'saved'  => true,
                'layout' => $cleaned,
            ]);
        } catch (\PDOException $e) {
            error_log('DashboardLayoutController::saveLayout: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: available-widgets
    // ================================================================
    private function availableWidgets(): void {
        $this->sendSuccess([
            'widgets' => self::WIDGETS,
        ]);
    }

    // ================================================================
    // HJÄLPMETODER
    // ================================================================

    /** Default layout: alla widgets synliga i standardordning */
    private function defaultLayout(): array {
        $layout = [];
        foreach (self::WIDGETS as $i => $w) {
            $layout[] = [
                'id'      => $w['id'],
                'visible' => true,
                'order'   => $i,
            ];
        }
        return $layout;
    }

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(
            ['success' => true, 'timestamp' => date('Y-m-d H:i:s')],
            $data
        ), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }
}
