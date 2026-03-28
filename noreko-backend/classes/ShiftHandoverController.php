<?php
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/AuditController.php';

class ShiftHandoverController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run    = trim($_GET['run'] ?? '');
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $run === 'recent') {
            $this->getRecent();
            return;
        }

        if ($method === 'GET' && $run === 'unread-count') {
            $this->unreadCount();
            return;
        }

        if ($method === 'POST' && $run === 'add') {
            $this->requireLogin();
            $this->addNote();
            return;
        }

        if ($method === 'POST' && $run === 'acknowledge') {
            $this->requireLogin();
            $this->acknowledge();
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

        if ($method === 'GET' && $run === '') {
            echo json_encode(['success' => true, 'endpoints' => ['recent', 'unread-count']], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte'], JSON_UNESCAPED_UNICODE);
    }

    // -------------------------------------------------------------------------
    // Auth-hjälpare
    // -------------------------------------------------------------------------

    private function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Kontrollera session-timeout (inaktivitet)
        if (!AuthHelper::checkSessionTimeout()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
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
        $tz      = new DateTimeZone('Europe/Stockholm');
        $now     = new DateTime('now', $tz);
        try {
            $created = new DateTime($createdAt, $tz);
        } catch (Exception $e) {
            error_log('ShiftHandoverController::timeAgo — ogiltigt datum: ' . $e->getMessage());
            return 'Okänt datum';
        }
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
        $nowMidnight     = new DateTime($now->format('Y-m-d'), $tz);
        $createdMidnight = new DateTime($created->format('Y-m-d'), $tz);
        $dayDiff         = (int)$nowMidnight->diff($createdMidnight)->days;

        if ($dayDiff === 1) {
            return 'Igår';
        }
        return $dayDiff . ' dagar sedan';
    }

    // -------------------------------------------------------------------------
    // GET ?action=shift-handover&run=unread-count
    // Returnerar antal urgenta notat från de senaste 12 timmarna.
    // Kräver inloggad session.
    // -------------------------------------------------------------------------

    private function unreadCount(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'antal' => 0], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) AS antal
                FROM shift_handover
                WHERE priority = \'urgent\'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
                  AND acknowledged_at IS NULL
            ');
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'antal' => (int)($row['antal'] ?? 0)], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController::unreadCount: ' . $e->getMessage());
            echo json_encode(['success' => true, 'antal' => 0], JSON_UNESCAPED_UNICODE);
        }
    }

    // -------------------------------------------------------------------------
    // GET ?action=shift-handover&run=recent
    // Returnerar senaste 3 dagars anteckningar (max 30 st), nyast först.
    // Inkluderar kvittensinfo.
    // -------------------------------------------------------------------------

    private function getRecent(): void {
        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    sh.id,
                    sh.datum,
                    sh.skift_nr,
                    sh.note,
                    sh.priority,
                    sh.audience,
                    sh.op_number,
                    sh.op_name,
                    sh.created_by_user_id,
                    sh.created_at,
                    sh.acknowledged_by,
                    sh.acknowledged_at,
                    u.username AS acknowledged_by_name
                FROM shift_handover sh
                LEFT JOIN users u ON u.id = sh.acknowledged_by
                WHERE sh.datum >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
                ORDER BY sh.created_at DESC
                LIMIT 30
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
                    'id'                   => (int)$row['id'],
                    'datum'                => $row['datum'],
                    'skift_nr'             => $skiftNr,
                    'skift_label'          => "Skift $skiftNr — $skiftLabelText",
                    'note'                 => $row['note'],
                    'priority'             => $row['priority'],
                    'audience'             => $row['audience'] ?? 'alla',
                    'op_number'            => $row['op_number'] !== null ? (int)$row['op_number'] : null,
                    'op_name'              => $row['op_name'],
                    'created_by_user_id'   => $row['created_by_user_id'] !== null ? (int)$row['created_by_user_id'] : null,
                    'created_at'           => $row['created_at'],
                    'time_ago'             => $this->timeAgo($row['created_at']),
                    'acknowledged_by'      => $row['acknowledged_by'] !== null ? (int)$row['acknowledged_by'] : null,
                    'acknowledged_at'      => $row['acknowledged_at'],
                    'acknowledged_by_name' => $row['acknowledged_by_name'],
                    'acknowledged_time_ago'=> $row['acknowledged_at'] ? $this->timeAgo($row['acknowledged_at']) : null,
                ];
            }

            echo json_encode(['success' => true, 'notes' => $notes], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController::getRecent: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta anteckningar'], JSON_UNESCAPED_UNICODE);
        }
    }

    // -------------------------------------------------------------------------
    // POST ?action=shift-handover&run=add
    // Body: { skift_nr, note, priority, op_number?, audience? }
    // -------------------------------------------------------------------------

    private function addNote(): void {
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $skiftNr  = isset($data['skift_nr'])  ? intval($data['skift_nr'])  : 0;
        $note     = isset($data['note'])       ? htmlspecialchars(trim($data['note']), ENT_QUOTES, 'UTF-8')        : '';
        $priority = isset($data['priority'])   ? trim($data['priority'])    : 'normal';
        $opNumber = isset($data['op_number'])  ? intval($data['op_number']) : null;
        $audience = isset($data['audience'])   ? trim($data['audience'])    : 'alla';

        // Validering
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'skift_nr måste vara 1, 2 eller 3'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (empty($note)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Anteckningstext krävs'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($note) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Anteckning får inte vara längre än 500 tecken'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $allowedPriorities = ['normal', 'important', 'urgent'];
        if (!in_array($priority, $allowedPriorities, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig prioritet'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $allowedAudiences = ['alla', 'ansvarig', 'teknik'];
        if (!in_array($audience, $allowedAudiences, true)) {
            $audience = 'alla';
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
                error_log('ShiftHandoverController::addNote op lookup: ' . $e->getMessage());
            }
        }

        $userId = $this->currentUserId();
        $datum  = date('Y-m-d');

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO shift_handover (datum, skift_nr, note, priority, audience, op_number, op_name, created_by_user_id)
                VALUES (:datum, :skift_nr, :note, :priority, :audience, :op_number, :op_name, :user_id)
            ');
            $stmt->execute([
                ':datum'    => $datum,
                ':skift_nr' => $skiftNr,
                ':note'     => $note,
                ':priority' => $priority,
                ':audience' => $audience,
                ':op_number'=> $opNumber ?: null,
                ':op_name'  => $opName,
                ':user_id'  => $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            // Skicka e-post vid brådskande anteckning
            if ($priority === 'urgent') {
                $this->sendUrgentNotification($note);
            }

            $skiftLabels = [1 => 'Morgon', 2 => 'Eftermiddag', 3 => 'Natt'];
            $skiftLabel  = "Skift $skiftNr — " . ($skiftLabels[$skiftNr] ?? "Skift $skiftNr");

            echo json_encode([
                'success' => true,
                'message' => 'Anteckning sparad',
                'note' => [
                    'id'                   => $newId,
                    'datum'                => $datum,
                    'skift_nr'             => $skiftNr,
                    'skift_label'          => $skiftLabel,
                    'note'                 => $note,
                    'priority'             => $priority,
                    'audience'             => $audience,
                    'op_number'            => $opNumber ?: null,
                    'op_name'              => $opName,
                    'created_by_user_id'   => $userId,
                    'created_at'           => date('Y-m-d H:i:s'),
                    'time_ago'             => 'Just nu',
                    'acknowledged_by'      => null,
                    'acknowledged_at'      => null,
                    'acknowledged_by_name' => null,
                    'acknowledged_time_ago'=> null,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController::addNote: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara anteckning'], JSON_UNESCAPED_UNICODE);
        }
    }

    // -------------------------------------------------------------------------
    // POST ?action=shift-handover&run=acknowledge
    // Body: { id }
    // Markerar en anteckning som sedd av inloggad användare.
    // -------------------------------------------------------------------------

    private function acknowledge(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = intval($data['id'] ?? $_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Saknar id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE shift_handover SET acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ? AND acknowledged_at IS NULL'
            );
            $stmt->execute([$_SESSION['user_id'], $id]);

            // Hämta kvittensinfo för svar
            $sel = $this->pdo->prepare(
                'SELECT sh.acknowledged_at, u.username AS acknowledged_by_name
                 FROM shift_handover sh
                 LEFT JOIN users u ON u.id = sh.acknowledged_by
                 WHERE sh.id = ?'
            );
            $sel->execute([$id]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'              => true,
                'acknowledged_at'      => $row['acknowledged_at'] ?? null,
                'acknowledged_by_name' => $row['acknowledged_by_name'] ?? null,
                'acknowledged_time_ago'=> 'Just nu',
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('ShiftHandoverController::acknowledge: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte kvittera anteckning'], JSON_UNESCAPED_UNICODE);
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
            echo json_encode(['success' => false, 'error' => 'Ogiltigt id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Transaktion + FOR UPDATE forhindrar TOCTOU: raden kan inte andras/tas bort
            // mellan agarkontroll och DELETE.
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT id, created_by_user_id FROM shift_handover WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Anteckning hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $userId  = $this->currentUserId();
            $isAdmin = $this->isAdmin();
            $ownNote = ($row['created_by_user_id'] !== null && (int)$row['created_by_user_id'] === $userId);

            if (!$isAdmin && !$ownNote) {
                $this->pdo->rollBack();
                error_log('ShiftHandoverController::deleteNote: Obehörig borttagning, user_id=' . $userId . ', note_id=' . $id);
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Du har inte behörighet att ta bort denna anteckning'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $delStmt = $this->pdo->prepare('DELETE FROM shift_handover WHERE id = ?');
            $delStmt->execute([$id]);

            AuditLogger::log($this->pdo, 'delete_shift_handover', 'shift_handover', $id,
                "Tog bort skiftanteckning (ID: $id)");
            $this->pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Anteckning borttagen'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ShiftHandoverController::deleteNote: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort anteckning'], JSON_UNESCAPED_UNICODE);
        }
    }
    // -------------------------------------------------------------------------
    // E-postnotifikation vid brådskande anteckning
    // -------------------------------------------------------------------------

    private function getAdminEmails(): array {
        try {
            // Kontrollera att notification_emails-kolumnen finns, annars returnera tom array
            $col = $this->pdo->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'rebotling_settings'
                   AND COLUMN_NAME  = 'notification_emails'"
            )->fetch(PDO::FETCH_ASSOC);

            if (!$col) {
                return [];
            }

            $row = $this->pdo->query(
                "SELECT notification_emails FROM rebotling_settings WHERE id = 1"
            )->fetch(PDO::FETCH_ASSOC);

            if (empty($row['notification_emails'])) {
                return [];
            }

            $parts  = array_map('trim', explode(';', $row['notification_emails']));
            $emails = [];
            foreach ($parts as $email) {
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
            return $emails;
        } catch (Exception $e) {
            error_log('ShiftHandoverController::getAdminEmails: ' . $e->getMessage());
            return [];
        }
    }

    private function sendUrgentNotification(string $noteText): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        $adminEmails = $this->getAdminEmails();
        if (empty($adminEmails)) {
            return;
        }

        $username = $_SESSION['username'] ?? 'Okänd användare';
        $subject  = "BRÅDSKANDE: Ny skiftnotering - " . date('Y-m-d H:i');
        $message  = "En ny brådskande notering har skapats i skiftöverlämningen.

";
        $message .= "Notis: " . $noteText . "
";
        $message .= "Av: " . $username . "
";
        $message .= "Tid: " . date('Y-m-d H:i:s') . "
";
        $headers  = "From: noreply@noreko.se\r\nContent-Type: text/plain; charset=UTF-8";

        foreach ($adminEmails as $email) {
            if (!@mail($email, $subject, $message, $headers)) {
                error_log("ShiftHandoverController::sendUrgentNotification: Kunde inte skicka e-post till mottagare");
            }
        }
    }


}