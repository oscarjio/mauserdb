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
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
                    $this->deleteProduct();
                } else {
                    $this->createProduct();
                }
                break;
            case 'PUT':
                $this->updateProduct();
                break;
            case 'DELETE':
                $this->deleteProduct();
                break;
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Metod inte tillåten'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getProducts() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM rebotling_products ORDER BY id");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $products
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('RebotlingProductController::getProducts: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta produkter'
            ], JSON_UNESCAPED_UNICODE);
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

        $name = trim($data['name']);
        $cycleTime = (float)$data['cycle_time_minutes'];
        if ($name === '' || $cycleTime <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Namn får inte vara tomt och cykeltid måste vara > 0'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO rebotling_products (name, cycle_time_minutes) VALUES (?, ?)");
            $stmt->execute([$name, $cycleTime]);
            
            $productId = $this->pdo->lastInsertId();
            $safeName = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
            $safeCycle = (float)$data['cycle_time_minutes'];
            AuditLogger::log($this->pdo, 'product_create', 'rebotling_products', (int)$productId,
                "Skapad: name={$safeName}, cycle_time={$safeCycle}");

            echo json_encode([
                'success' => true,
                'message' => 'Produkt skapad',
                'data' => [
                    'id' => $productId,
                    'name' => $data['name'],
                    'cycle_time_minutes' => $data['cycle_time_minutes']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
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
        $name = trim($data['name']);
        $cycleTime = (float)$data['cycle_time_minutes'];
        if ($id <= 0 || $name === '' || $cycleTime <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ogiltigt ID, tomt namn eller ogiltig cykeltid'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE rebotling_products SET name = ?, cycle_time_minutes = ? WHERE id = ?");
            $stmt->execute([$name, $cycleTime, $id]);
            
            if ($stmt->rowCount() > 0) {
                $safeName = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
                $safeCycle = (float)$data['cycle_time_minutes'];
                AuditLogger::log($this->pdo, 'product_update', 'rebotling_products', (int)$data['id'],
                    "Uppdaterad: name={$safeName}, cycle_time={$safeCycle}");
                echo json_encode([
                    'success' => true,
                    'message' => 'Produkt uppdaterad',
                    'data' => [
                        'id' => $data['id'],
                        'name' => $data['name'],
                        'cycle_time_minutes' => $data['cycle_time_minutes']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Produkt hittades inte'
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            error_log('RebotlingProductController::updateProduct: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte uppdatera produkt'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function deleteProduct() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!isset($data['id']) || (int)$data['id'] <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Giltigt produkt-ID krävs'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = (int)$data['id'];

        try {
            // Hard delete - ta bort raden från databasen
            $stmt = $this->pdo->prepare("DELETE FROM rebotling_products WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                AuditLogger::log($this->pdo, 'product_delete', 'rebotling_products', $id,
                    'Produkt borttagen');
                echo json_encode([
                    'success' => true,
                    'message' => 'Produkt borttagen'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Produkt hittades inte'
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            error_log('RebotlingProductController::deleteProduct: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte ta bort produkt'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
