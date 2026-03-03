<?php

class ShiftHandoverController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run    = $_GET['run'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $run === 'recent') {
            $this->getRecent();
            return;
        }

        if ($method === 'POST' && $run === 'add') {
            $this->requireLogin();
            $this->addNote();
            return;
        }

        if ($method === 'DELETE' && $run === 'delete') {
            $this->requireLogin();
            $this->deleteNote();
            return;
        }

        // Stöd POST för delete också (för klienter som inte stödjer DELETE)
        if ($method === 'POST' && $run === 'delete') {
            $this->requireLogin();
            $this->deleteNote();
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte']);
    }

    // -------------------------------------------------------------------------
    // Auth-hjälpare
    // -------------------------------------------------------------------------

    private function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Inloggning krävs.']);
            exit;
        }
    }

    private function isAdmin(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    private function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    // -------------------------------------------------------------------------
    // Beräkna time_ago på svenska
    // -------------------------------------------------------------------------

    private function timeAgo(string $createdAt): string {
        $now     = new DateTime();
        $created = new DateTime($createdAt);
        $diff    = $now->getTimestamp() - $created->getTimestamp();

        if ($diff < 60) {
            return 'Just nu';
        }
        if ($diff < 3600) {
            $mins = (int)floor($diff / 60);
            return $mins . ' ' . ($mins === 1 ? 'minut' : 'minuter') . ' sedan';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' ' . ($hours === 1 ? 'timme' : 'timmar') . ' sedan';
        }

        // Jämför kalenderdag
        $nowMidnight     = new DateTime($now->format('Y-m-d'));
        $createdMidnight = new DateTime($created->format('Y-m-d'));
        $dayDiff         = (int)$nowMidnight->diff($createdMidnight)->days;

        if ($dayDiff === 1) {
            return 'Igår';
        }
        return $dayDiff . ' dagar sedan';
    }

    // -------------------------------------------------------------------------
    // GET ?action=shift-handover&run=recent
    // Returnerar senaste 3 dagars anteckningar (max 10 st), nyast först.
    // -------------------------------------------------------------------------

    private function getRecent(): void {
        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    id,
                    datum,
                    skift_nr,
                    note,
                    priority,
                    op_number,
                    op_name,
                    created_by_user_id,
                    created_at
                FROM shift_handover
                WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
                ORDER BY created_at DESC
                LIMIT 10
            ');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $skiftLabels = [
                1 => 'Morgon',
                2 => 'Eftermiddag',
                3 => 'Natt',
            ];

            $notes = [];
            foreach ($rows as $row) {
                $skiftNr        = (int)$row['skift_nr'];
                $skiftLabelText = $skiftLabels[$skiftNr] ?? "Skift $skiftNr";
                $notes[] = [
                    'id'          => (int)$row['id'],
                    'datum'       => $row['datum'],
                    'skift_nr'    => $skiftNr,
                    'skift_label' => "Skift $skiftNr — $skiftLabelText",
                    'note'        => $row['note'],
                    'priority'    => $row['priority'],
                    'op_number'   => $row['op_number'] !== null ? (int)$row['op_number'] : null,
                    'op_name'     => $row['op_name'],
                    'created_by_user_id' => $row['created_by_user_id'] !== null ? (int)$row['created_by_user_id'] : null,
                    'created_at'  => $row['created_at'],
                    'time_ago'    => $this->timeAgo($row['created_at']),
                ];
            }

            echo json_encode(['success' => true, 'notes' => $notes]);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController getRecent: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta anteckningar']);
        }
    }

    // -------------------------------------------------------------------------
    // POST ?action=shift-handover&run=add
    // Body: { skift_nr, note, priority, op_number? }
    // -------------------------------------------------------------------------

    private function addNote(): void {
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $skiftNr  = isset($data['skift_nr'])  ? intval($data['skift_nr'])  : 0;
        $note     = isset($data['note'])       ? trim($data['note'])        : '';
        $priority = isset($data['priority'])   ? trim($data['priority'])    : 'normal';
        $opNumber = isset($data['op_number'])  ? intval($data['op_number']) : null;

        // Validering
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'skift_nr måste vara 1, 2 eller 3']);
            return;
        }
        if (empty($note)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Anteckningstext krävs']);
            return;
        }
        if (mb_strlen($note) > 1000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Anteckning får inte vara längre än 1000 tecken']);
            return;
        }
        $allowedPriorities = ['normal', 'important', 'urgent'];
        if (!in_array($priority, $allowedPriorities, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig prioritet']);
            return;
        }

        // Hämta op_name om op_number angivits
        $opName = null;
        if ($opNumber && $opNumber > 0) {
            try {
                $opStmt = $this->pdo->prepare('SELECT name FROM operators WHERE number = ?');
                $opStmt->execute([$opNumber]);
                $op     = $opStmt->fetch(PDO::FETCH_ASSOC);
                $opName = $op ? $op['name'] : null;
            } catch (PDOException $e) {
                // Op-namn är icke-kritiskt — fortsätt utan det
                error_log('ShiftHandoverController addNote op lookup: ' . $e->getMessage());
            }
        }

        $userId = $this->currentUserId();
        $datum  = date('Y-m-d');

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO shift_handover (datum, skift_nr, note, priority, op_number, op_name, created_by_user_id)
                VALUES (:datum, :skift_nr, :note, :priority, :op_number, :op_name, :user_id)
            ');
            $stmt->execute([
                ':datum'    => $datum,
                ':skift_nr' => $skiftNr,
                ':note'     => $note,
                ':priority' => $priority,
                ':op_number'=> $opNumber ?: null,
                ':op_name'  => $opName,
                ':user_id'  => $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            $skiftLabels = [1 => 'Morgon', 2 => 'Eftermiddag', 3 => 'Natt'];
            $skiftLabel  = "Skift $skiftNr — " . ($skiftLabels[$skiftNr] ?? "Skift $skiftNr");

            echo json_encode([
                'success' => true,
                'message' => 'Anteckning sparad',
                'note' => [
                    'id'          => $newId,
                    'datum'       => $datum,
                    'skift_nr'    => $skiftNr,
                    'skift_label' => $skiftLabel,
                    'note'        => $note,
                    'priority'    => $priority,
                    'op_number'   => $opNumber ?: null,
                    'op_name'     => $opName,
                    'created_by_user_id' => $userId,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'time_ago'    => 'Just nu',
                ],
            ]);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController addNote: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara anteckning']);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE/POST ?action=shift-handover&run=delete&id=N
    // Kräver admin ELLER att created_by_user_id matchar inloggad användare.
    // -------------------------------------------------------------------------

    private function deleteNote(): void {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt id']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, created_by_user_id FROM shift_handover WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anteckning hittades inte']);
                return;
            }

            $userId  = $this->currentUserId();
            $isAdmin = $this->isAdmin();
            $ownNote = ($row['created_by_user_id'] !== null && (int)$row['created_by_user_id'] === $userId);

            if (!$isAdmin && !$ownNote) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Du har inte behörighet att ta bort denna anteckning']);
                return;
            }

            $delStmt = $this->pdo->prepare('DELETE FROM shift_handover WHERE id = ?');
            $delStmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Anteckning borttagen']);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController deleteNote: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort anteckning']);
        }
    }
}
