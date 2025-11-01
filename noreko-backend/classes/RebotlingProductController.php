<?php
class RebotlingProductController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['run'] ?? '';
        
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
                echo json_encode(['error' => 'Metod inte tillåten']);
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
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte hämta produkter: ' . $e->getMessage()
            ]);
        }
    }

    private function createProduct() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name']) || !isset($data['cycle_time_minutes'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Namn och cykeltid krävs'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO rebotling_products (name, cycle_time_minutes) VALUES (?, ?)");
            $stmt->execute([$data['name'], $data['cycle_time_minutes']]);
            
            $productId = $this->pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Produkt skapad',
                'data' => [
                    'id' => $productId,
                    'name' => $data['name'],
                    'cycle_time_minutes' => $data['cycle_time_minutes']
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte skapa produkt: ' . $e->getMessage()
            ]);
        }
    }

    private function updateProduct() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id']) || !isset($data['name']) || !isset($data['cycle_time_minutes'])) {
            echo json_encode([
                'success' => false,
                'error' => 'ID, namn och cykeltid krävs'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE rebotling_products SET name = ?, cycle_time_minutes = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['cycle_time_minutes'], $data['id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Produkt uppdaterad',
                    'data' => [
                        'id' => $data['id'],
                        'name' => $data['name'],
                        'cycle_time_minutes' => $data['cycle_time_minutes']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Produkt hittades inte'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte uppdatera produkt: ' . $e->getMessage()
            ]);
        }
    }

    private function deleteProduct() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Produkt ID krävs'
            ]);
            return;
        }

        try {
            // Hard delete - ta bort raden från databasen
            $stmt = $this->pdo->prepare("DELETE FROM rebotling_products WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Produkt borttagen'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Produkt hittades inte'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Kunde inte ta bort produkt: ' . $e->getMessage()
            ]);
        }
    }
}
