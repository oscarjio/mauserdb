<?php
require_once __DIR__ . '/AuthHelper.php';

/**
 * FeedbackController.php
 * Hanterar operatörsfeedback efter skift
 *
 * Endpoints:
 * - GET  ?action=feedback&run=my-history  → Inloggad operatörs senaste 10 feedbacks
 * - GET  ?action=feedback&run=summary     → Aggregering senaste 30 dagar (kräver admin)
 * - POST ?action=feedback&run=submit      → Lämna feedback (max 1 per skiftraknare)
 */

class FeedbackController {
    private $pdo;

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
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Kontrollera session-timeout (inaktivitet)
        if (!AuthHelper::checkSessionTimeout()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run    = trim($_GET['run'] ?? '');

        if ($method === 'POST') {
            switch ($run) {
                case 'submit': $this->submit(); break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Ogiltig POST-action'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metod ej tillåten'], JSON_UNESCAPED_UNICODE);
            return;
        }

        switch ($run) {
            case 'my-history': $this->myHistory(); break;
            case 'summary':    $this->summary();   break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig action: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // GET: my-history — inloggad operatörs senaste 10 feedbacks
    // ================================================================
    private function myHistory(): void {
        $userId = (int) $_SESSION['user_id'];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, datum, stämning, kommentar, skapad_at
                 FROM operator_feedback
                 WHERE operator_id = :uid
                 ORDER BY skapad_at DESC
                 LIMIT 10"
            );
            $stmt->execute([':uid' => $userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Konvertera stämning till int
            foreach ($items as &$item) {
                $item['stämning'] = (int) $item['stämning'];
                $item['id']       = (int) $item['id'];
            }
            unset($item);

            echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('FeedbackController::myHistory: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av historik'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // GET: summary — aggregering senaste 30 dagar (kräver admin)
    // ================================================================
    private function summary(): void {
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            error_log('FeedbackController::summary: Obehörig åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none') . ', role=' . ($_SESSION['role'] ?? 'none'));
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Åtkomst nekad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Totalt snitt och antal
            $stmt = $this->pdo->query(
                "SELECT ROUND(AVG(stämning), 2) AS avg_stamning, COUNT(*) AS total
                 FROM operator_feedback
                 WHERE datum >= CURDATE() - INTERVAL 30 DAY"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Per dag
            $stmt2 = $this->pdo->query(
                "SELECT datum, ROUND(AVG(stämning), 2) AS snitt, COUNT(*) AS antal
                 FROM operator_feedback
                 WHERE datum >= CURDATE() - INTERVAL 30 DAY
                 GROUP BY datum
                 ORDER BY datum DESC"
            );
            $perDag = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'      => true,
                'avg_stamning' => $row['avg_stamning'] !== null ? (float) $row['avg_stamning'] : null,
                'total'        => (int) $row['total'],
                'per_dag'      => $perDag,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log('FeedbackController::summary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid hämtning av sammanfattning'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // POST: submit — lämna feedback (max 1 per skiftraknare)
    // ================================================================
    private function submit(): void {
        $userId = (int) $_SESSION['user_id'];

        // Läs JSON-body
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig request-body (förväntar JSON)'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Validera stämning
        $stamning = isset($data['stamning']) ? (int) $data['stamning'] : 0;
        if ($stamning < 1 || $stamning > 4) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Stämning måste vara 1–4'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Sanitera kommentar
        $kommentar = null;
        if (isset($data['kommentar']) && is_string($data['kommentar'])) {
            $kommentar = mb_substr(strip_tags(trim($data['kommentar'])), 0, 280);
            if ($kommentar === '') $kommentar = null;
        }

        try {
            // Hämta aktuell skiftraknare från senaste rebotling_ibc idag
            $stmtSkift = $this->pdo->prepare(
                "SELECT skiftraknare
                 FROM rebotling_ibc
                 WHERE DATE(datum) = CURDATE()
                 ORDER BY datum DESC
                 LIMIT 1"
            );
            $stmtSkift->execute();
            $skiftRow      = $stmtSkift->fetch(PDO::FETCH_ASSOC);
            $skiftraknare  = $skiftRow ? (int) $skiftRow['skiftraknare'] : null;

            // Kolla och infoga inom transaktion för att undvika double-submit race condition
            $this->pdo->beginTransaction();

            if ($skiftraknare !== null) {
                $stmtCheck = $this->pdo->prepare(
                    "SELECT id FROM operator_feedback
                     WHERE operator_id = :uid AND skiftraknare = :skift
                     LIMIT 1
                     FOR UPDATE"
                );
                $stmtCheck->execute([':uid' => $userId, ':skift' => $skiftraknare]);
            } else {
                $stmtCheck = $this->pdo->prepare(
                    "SELECT id FROM operator_feedback
                     WHERE operator_id = :uid AND datum = CURDATE()
                     LIMIT 1
                     FOR UPDATE"
                );
                $stmtCheck->execute([':uid' => $userId]);
            }

            if ($stmtCheck->fetch()) {
                $this->pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Feedback redan inlämnad för detta skift'], JSON_UNESCAPED_UNICODE);
                return;
            }

            // INSERT
            $stmtIns = $this->pdo->prepare(
                "INSERT INTO operator_feedback (operator_id, skiftraknare, datum, stämning, kommentar)
                 VALUES (:uid, :skift, CURDATE(), :stamning, :kommentar)"
            );
            $stmtIns->execute([
                ':uid'       => $userId,
                ':skift'     => $skiftraknare,
                ':stamning'  => $stamning,
                ':kommentar' => $kommentar,
            ]);

            $this->pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Tack för din feedback!'], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('FeedbackController::submit: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Serverfel vid sparning av feedback'], JSON_UNESCAPED_UNICODE);
        }
    }
}
