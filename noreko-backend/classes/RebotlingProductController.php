<?php
require_once __DIR__ . '/AuditController.php';

class RebotlingProductController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = trim($_GET['run'] ?? '');

        // Session krävs för alla anrop
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Inloggning krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Write operations require admin
        if ($method !== 'GET') {
            if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','developer'], true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        switch ($method) {
            case 'GET':
                $this->getProducts();
                break;
            case 'POST':
                if ($action === 'delete') {
                    // N-bug (Oscar 2026-10-20): hård DELETE blockerad — produkter raderas
                    // ALDRIG, historisk data refererar produkt-ID. Använd dölj/visa i stället.
                    http_response_code(410);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Borttagning av produkter är inaktiverad (historisk data refererar produkt-ID). Använd dölj/visa (run=hide/run=show) i stället.'
                    ], JSON_UNESCAPED_UNICODE);
                } elseif ($action === 'hide') {
                    $this->setHidden(true);
                } elseif ($action === 'show') {
                    $this->setHidden(false);
                } else {
                    $this->createProduct();
                }
                break;
            case 'PUT':
                $this->updateProduct();
                break;
            case 'DELETE':
                http_response_code(410);
                echo json_encode([
                    'success' => false,
                    'error' => 'Borttagning av produkter är inaktiverad (historisk data refererar produkt-ID). Använd dölj/visa (run=hide/run=show) i stället.'
                ], JSON_UNESCAPED_UNICODE);
                break;
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Metod inte tillåten'], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Se TvattlinjeProductController för samma mönster/kommentarer. */
    private ?bool $hasDoldColumn = null;
    private function hasDoldColumn(): bool {
        if ($this->hasDoldColumn !== null) return $this->hasDoldColumn;
        try {
            $this->pdo->query("SELECT dold FROM rebotling_products LIMIT 1");
            $this->hasDoldColumn = true;
        } catch (\Throwable $e) {
            $this->hasDoldColumn = false;
        }
        return $this->hasDoldColumn;
    }

    private ?bool $hasAndradColumns = null;
    private function hasAndradColumns(): bool {
        if ($this->hasAndradColumns !== null) return $this->hasAndradColumns;
        try {
            $this->pdo->query("SELECT andrad_tid, andrad_av FROM rebotling_products LIMIT 1");
            $this->hasAndradColumns = true;
        } catch (\Throwable $e) {
            $this->hasAndradColumns = false;
        }
        return $this->hasAndradColumns;
    }

    private function touchAndrad(int $productId): void {
        if (!$this->hasAndradColumns()) return;
        try {
            $this->pdo->prepare("UPDATE rebotling_products SET andrad_tid = NOW(), andrad_av = ? WHERE id = ?")
                ->execute([$_SESSION['username'] ?? 'system', $productId]);
        } catch (\Throwable $e) {
            error_log('RebotlingProductController::touchAndrad: ' . $e->getMessage());
        }
    }

    private function getProducts() {
        try {
            // N-bug (Oscar 2026-10-22): cycle_time_minutes är FLOAT (Oscar ändrade den själv
            // i DB) — FLOAT lagrar inte 3,2 exakt (blir 3.2000000476…). Avrunda alltid till 2
            // decimaler vid läsning så admin/live/beräkningar aldrig ser float-bruset.
            $cols = "id, name, ROUND(cycle_time_minutes, 2) AS cycle_time_minutes, COALESCE(has_lopnummer, 1) AS has_lopnummer, created_at";
            if ($this->hasDoldColumn()) $cols .= ", dold";
            if ($this->hasAndradColumns()) $cols .= ", andrad_tid, andrad_av";
            $stmt = $this->pdo->prepare("SELECT $cols FROM rebotling_products ORDER BY id");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as &$p) {
                $p['dold'] = isset($p['dold']) ? (int)$p['dold'] : 0;
                // Extra bälte-och-hängslen mot float-brus (utöver SQL-ROUND ovan) —
                // PDO kan returnera FLOAT-kolumner som sträng beroende på drivrutin.
                if (isset($p['cycle_time_minutes'])) $p['cycle_time_minutes'] = round((float)$p['cycle_time_minutes'], 2);
            }
            unset($p);

            echo json_encode([
                'success' => true,
                'data' => $products
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('RebotlingProductController::getProducts: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta produkter'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Se TvattlinjeProductController::setHidden för samma mönster/kommentarer. */
    private function setHidden(bool $hidden): void {
        if (!$this->hasDoldColumn()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Dölj/visa kräver databasmigreringen (dold-kolumnen i rebotling_products) — inte körd ännu.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Giltigt produkt-ID krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }
        try {
            $stmt = $this->pdo->prepare("UPDATE rebotling_products SET dold = ? WHERE id = ?");
            $stmt->execute([$hidden ? 1 : 0, $id]);
            if ($stmt->rowCount() === 0) {
                $check = $this->pdo->prepare("SELECT id FROM rebotling_products WHERE id = ?");
                $check->execute([$id]);
                if (!$check->fetch()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Produkt hittades inte'], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
            $this->touchAndrad($id);
            AuditLogger::log($this->pdo, $hidden ? 'product_hide' : 'product_show', 'rebotling_products', $id,
                $hidden ? 'Produkt dold' : 'Produkt återaktiverad');
            echo json_encode(['success' => true, 'message' => $hidden ? 'Produkt dold' : 'Produkt återaktiverad'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('RebotlingProductController::setHidden: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte uppdatera produkten'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function createProduct() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!isset($data['name']) || !isset($data['cycle_time_minutes'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Namn och cykeltid krävs'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $name = strip_tags(trim($data['name']));
        // N-bug (Oscar 2026-10-22): avrunda till 2 decimaler vid SPARNING också — FLOAT-
        // kolumnen lagrar annars klientens råa inmatning (t.ex. 3.2000000476 om klienten
        // skickat ett redan brusigt float-värde) i stället för de avsedda 3,20.
        $cycleTime = round((float)$data['cycle_time_minutes'], 2);
        if ($name === '' || $cycleTime <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Namn får inte vara tomt och cykeltid måste vara > 0'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($name) > 100) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Produktnamn får vara max 100 tecken'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($cycleTime > 9999) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Cykeltid får vara max 9999 minuter'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $hasLopnummer = isset($data['has_lopnummer']) ? (int)(bool)$data['has_lopnummer'] : 1;
            $stmt = $this->pdo->prepare("INSERT INTO rebotling_products (name, cycle_time_minutes, has_lopnummer) VALUES (?, ?, ?)");
            $stmt->execute([$name, $cycleTime, $hasLopnummer]);

            $productId = $this->pdo->lastInsertId();
            $safeName = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
            AuditLogger::log($this->pdo, 'product_create', 'rebotling_products', (int)$productId,
                "Skapad: name={$safeName}, cycle_time={$cycleTime}");
            $this->pdo->commit();
            $this->touchAndrad((int)$productId);

            echo json_encode([
                'success' => true,
                'message' => 'Produkt skapad',
                'data' => [
                    'id' => $productId,
                    'name' => $data['name'],
                    'cycle_time_minutes' => $cycleTime
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RebotlingProductController::createProduct: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte skapa produkt'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function updateProduct() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!isset($data['id']) || !isset($data['name']) || !isset($data['cycle_time_minutes'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'ID, namn och cykeltid krävs'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = (int)$data['id'];
        $name = strip_tags(trim($data['name']));
        // N-bug (Oscar 2026-10-22): avrunda till 2 decimaler vid sparning, se createProduct.
        $cycleTime = round((float)$data['cycle_time_minutes'], 2);
        if ($id <= 0 || $name === '' || $cycleTime <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ogiltigt ID, tomt namn eller ogiltig cykeltid'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($name) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Produktnamn får vara max 100 tecken'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($cycleTime > 9999) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cykeltid får vara max 9999 minuter'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $hasLopnummer = isset($data['has_lopnummer']) ? (int)(bool)$data['has_lopnummer'] : 1;
            $stmt = $this->pdo->prepare("UPDATE rebotling_products SET name = ?, cycle_time_minutes = ?, has_lopnummer = ? WHERE id = ?");
            $stmt->execute([$name, $cycleTime, $hasLopnummer, $id]);

            if ($stmt->rowCount() > 0) {
                $safeName = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
                AuditLogger::log($this->pdo, 'product_update', 'rebotling_products', (int)$data['id'],
                    "Uppdaterad: name={$safeName}, cycle_time={$cycleTime}");
                $this->pdo->commit();
                $this->touchAndrad((int)$data['id']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Produkt uppdaterad',
                    'data' => [
                        'id' => $data['id'],
                        'name' => $data['name'],
                        'cycle_time_minutes' => $cycleTime
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                $this->pdo->rollBack();
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Produkt hittades inte'
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RebotlingProductController::updateProduct: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte uppdatera produkt'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
