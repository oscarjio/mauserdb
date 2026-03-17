<?php
/**
 * FavoriterController.php
 * Hanterar användarens sparade snabblänkar/favoriter.
 *
 * Endpoints via ?action=favoriter&run=XXX:
 *   run=list              — Hämta användarens sparade favoriter (sorterade)
 *   run=add    (POST)     — Lägg till en favorit (route, label, icon, color)
 *   run=remove (POST)     — Ta bort en favorit (id)
 *   run=reorder (POST)    — Ändra ordning (array av ids)
 */
class FavoriterController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'list':    $this->listFavoriter();   break;
            case 'add':     $this->addFavorit();      break;
            case 'remove':  $this->removeFavorit();   break;
            case 'reorder': $this->reorderFavoriter(); break;
            default:        $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // ENDPOINT: list
    // ================================================================
    private function listFavoriter(): void {
        $userId = (int) $_SESSION['user_id'];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, route, label, icon, color, sort_order
                 FROM user_favoriter
                 WHERE user_id = :user_id
                 ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute([':user_id' => $userId]);
            $rows = $stmt->fetchAll();

            $this->sendSuccess($rows);
        } catch (\PDOException $e) {
            error_log('FavoriterController::list: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta favoriter', 500);
        }
    }

    // ================================================================
    // ENDPOINT: add
    // ================================================================
    private function addFavorit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST krävs', 405);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $route = trim($body['route'] ?? '');
        $label = trim($body['label'] ?? '');
        $icon  = trim($body['icon']  ?? 'fas fa-star');
        $color = trim($body['color'] ?? '#4299e1');

        if (!$route || !$label) {
            $this->sendError('route och label krävs');
            return;
        }

        if (strlen($route) > 255 || strlen($label) > 100 || strlen($icon) > 50 || strlen($color) > 20) {
            $this->sendError('Fältvärde för långt');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // Hämta nästa sort_order (inom transaktion för att undvika race condition)
            $stmtMax = $this->pdo->prepare(
                "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order
                 FROM user_favoriter WHERE user_id = :user_id FOR UPDATE"
            );
            $stmtMax->execute([':user_id' => $userId]);
            $nextOrder = (int) $stmtMax->fetchColumn();

            $stmt = $this->pdo->prepare(
                "INSERT INTO user_favoriter (user_id, route, label, icon, color, sort_order)
                 VALUES (:user_id, :route, :label, :icon, :color, :sort_order)"
            );
            $stmt->execute([
                ':user_id'    => $userId,
                ':route'      => $route,
                ':label'      => $label,
                ':icon'       => $icon,
                ':color'      => $color,
                ':sort_order' => $nextOrder,
            ]);

            $newId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            $this->sendSuccess([
                'id'         => $newId,
                'route'      => $route,
                'label'      => $label,
                'icon'       => $icon,
                'color'      => $color,
                'sort_order' => $nextOrder,
            ]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ((string)$e->getCode() === '23000') {
                $this->sendError('Denna sida finns redan bland dina favoriter');
                return;
            }
            error_log('FavoriterController::add: ' . $e->getMessage());
            $this->sendError('Kunde inte lägga till favorit', 500);
        }
    }

    // ================================================================
    // ENDPOINT: remove
    // ================================================================
    private function removeFavorit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST krävs', 405);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            $this->sendError('Ogiltigt id');
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM user_favoriter WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([':id' => $id, ':user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Favoriten hittades inte', 404);
                return;
            }

            $this->sendSuccess(['removed' => $id]);
        } catch (\PDOException $e) {
            error_log('FavoriterController::remove: ' . $e->getMessage());
            $this->sendError('Kunde inte ta bort favorit', 500);
        }
    }

    // ================================================================
    // ENDPOINT: reorder
    // ================================================================
    private function reorderFavoriter(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST krävs', 405);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids = $body['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            $this->sendError('ids (array) krävs');
            return;
        }
        if (count($ids) > 50) {
            $this->sendError('Max 50 favoriter');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE user_favoriter SET sort_order = :sort_order
                 WHERE id = :id AND user_id = :user_id"
            );

            foreach ($ids as $index => $id) {
                $stmt->execute([
                    ':sort_order' => $index,
                    ':id'         => (int) $id,
                    ':user_id'    => $userId,
                ]);
            }

            $this->pdo->commit();

            $this->sendSuccess(['reordered' => true]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('FavoriterController::reorder: ' . $e->getMessage());
            $this->sendError('Kunde inte ändra ordning', 500);
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function sendSuccess($data): void {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
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
