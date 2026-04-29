<?php
require_once __DIR__ . '/AuditController.php';

class CertificationController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run = trim($_GET['run'] ?? '');
        $method = $_SERVER['REQUEST_METHOD'];

        // GET-endpoints som inte behöver session alls
        if ($method === 'GET' && $run === 'all') {
            $this->getAll();
            return;
        }

        if ($method === 'GET' && $run === 'matrix') {
            $this->getMatrix();
            return;
        }

        // Endpoints som behöver session — använd read_and_close för GET (läser bara session)
        if ($method === 'GET') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start(['read_and_close' => true]);
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }

        if ($method === 'GET' && $run === 'expiry-count') {
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                error_log('CertificationController::handle: Obehörig åtkomst till expiry-count, user_id=' . ($_SESSION['user_id'] ?? 'none'));
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->getExpiryCount();
            return;
        }

        // POST-endpoints kräver admin
        if ($method === 'POST') {
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                error_log('CertificationController::handle: Obehörig POST-åtkomst, user_id=' . ($_SESSION['user_id'] ?? 'none'));
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($run === 'add') {
                $this->addCertification();
                return;
            }

            if ($run === 'revoke') {
                $this->revokeCertification();
                return;
            }
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Okänd åtgärd'], JSON_UNESCAPED_UNICODE);
    }

    private function getExpiryCount() {
        try {
            // Kontrollera om tabellen finns
            $tableCheck = $this->pdo->query("
                SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'operator_certifications'
            ");
            if ((int)$tableCheck->fetchColumn() === 0) {
                echo json_encode(['success' => true, 'count' => 0, 'urgent_count' => 0], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Certifikat som löper ut inom 30 dagar
            $stmt = $this->pdo->query("
                SELECT COUNT(*) AS count
                FROM operator_certifications
                WHERE expires_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND expires_date IS NOT NULL
                AND active = 1
            ");
            $count = (int)$stmt->fetchColumn();

            // Certifikat som löper ut inom 7 dagar (urgent)
            $urgentStmt = $this->pdo->query("
                SELECT COUNT(*) AS count
                FROM operator_certifications
                WHERE expires_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND expires_date IS NOT NULL
                AND active = 1
            ");
            $urgentCount = (int)$urgentStmt->fetchColumn();

            echo json_encode([
                'success'       => true,
                'count'         => $count,
                'urgent_count'  => $urgentCount,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('CertificationController::getExpiryCount: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta certifieringsdata', 'count' => 0, 'urgent_count' => 0], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getAll() {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    c.id,
                    c.op_number,
                    c.line,
                    c.certified_date,
                    c.expires_date,
                    c.notes,
                    c.active,
                    c.created_at,
                    o.name AS op_name
                FROM operator_certifications c
                LEFT JOIN operators o ON o.number = c.op_number
                ORDER BY o.name ASC, c.line ASC, c.certified_date DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Beräkna days_until_expiry och gruppera per operatör
            $grouped = [];
            foreach ($rows as $row) {
                $opNum = (int)$row['op_number'];
                $daysUntil = null;
                if ($row['expires_date']) {
                    try {
                        $diff = (new \DateTime('today'))->diff(new \DateTime($row['expires_date']));
                        $daysUntil = $diff->invert ? -$diff->days : $diff->days;
                    } catch (\Throwable $e) {
                        error_log('CertificationController::getAll: ' . $e->getMessage());
                        // Ogiltigt datum — ignorera
                    }
                }

                if (!isset($grouped[$opNum])) {
                    $grouped[$opNum] = [
                        'op_number'      => $opNum,
                        'name'           => $row['op_name'] ?? ('Operatör #' . $opNum),
                        'certifications' => []
                    ];
                }

                $grouped[$opNum]['certifications'][] = [
                    'id'                => (int)$row['id'],
                    'line'              => $row['line'],
                    'certified_date'    => $row['certified_date'],
                    'expires_date'      => $row['expires_date'],
                    'notes'             => $row['notes'],
                    'active'            => (int)$row['active'],
                    'days_until_expiry' => $daysUntil,
                    'created_at'        => $row['created_at'],
                ];
            }

            echo json_encode([
                'success'      => true,
                'operators'    => array_values($grouped)
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('CertificationController::getAll: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta certifieringar'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getMatrix() {
        try {
            $lines = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje'];
            $lineLabels = [
                'rebotling'           => 'Rebotling',
                'tvattlinje'          => 'Tvättlinje',
                'saglinje'            => 'Såglinje',
                'klassificeringslinje'=> 'Klassificeringslinje',
            ];

            // Hämta aktiva operatörer
            $opStmt = $this->pdo->query("
                SELECT id, name, number
                FROM operators
                WHERE active = 1
                ORDER BY name ASC
            ");
            $operatorRows = $opStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($operatorRows)) {
                echo json_encode([
                    'success'   => true,
                    'operators' => [],
                    'lines'     => [],
                    'matrix'    => (object)[]
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Hämta alla aktiva certifieringar
            $certStmt = $this->pdo->query("
                SELECT
                    c.op_number,
                    c.line,
                    c.certified_date,
                    c.expires_date,
                    c.active
                FROM operator_certifications c
                WHERE c.active = 1
                ORDER BY c.certified_date DESC
            ");
            $certRows = $certStmt->fetchAll(PDO::FETCH_ASSOC);

            // Indexera certifieringar per op_number+line (ta den senaste, d.v.s. första pga DESC)
            $certIndex = [];
            foreach ($certRows as $cert) {
                $key = $cert['op_number'] . '_' . $cert['line'];
                if (!isset($certIndex[$key])) {
                    $daysUntil = null;
                    if ($cert['expires_date']) {
                        try {
                            $diff = (new \DateTime('today'))->diff(new \DateTime($cert['expires_date']));
                            $daysUntil = $diff->invert ? -$diff->days : $diff->days;
                        } catch (\Throwable $e) {
                            error_log('CertificationController::getMatrix: ' . $e->getMessage());
                            // Ogiltigt datum — ignorera
                        }
                    }

                    // Bestäm status
                    if ($daysUntil !== null && $daysUntil < 0) {
                        $status = 'expired';
                    } elseif ($daysUntil !== null && $daysUntil <= 30) {
                        $status = 'expiring';
                    } else {
                        $status = 'valid';
                    }

                    $certIndex[$key] = [
                        'status'           => $status,
                        'certified_date'   => $cert['certified_date'],
                        'expires_date'     => $cert['expires_date'],
                        'days_left'        => $daysUntil,
                    ];
                }
            }

            // Bygg matris
            $matrix = [];
            foreach ($operatorRows as $op) {
                $opNum = (int)$op['number'];
                $matrix[$opNum] = [];
                foreach ($lines as $line) {
                    $key = $opNum . '_' . $line;
                    $matrix[$opNum][$line] = isset($certIndex[$key]) ? $certIndex[$key] : null;
                }
            }

            // Bygg linjer-array
            $linesOut = [];
            foreach ($lines as $line) {
                $linesOut[] = ['key' => $line, 'label' => $lineLabels[$line]];
            }

            // Bygg operatörer-array
            $operatorsOut = [];
            foreach ($operatorRows as $op) {
                $operatorsOut[] = [
                    'id'     => (int)$op['id'],
                    'number' => (int)$op['number'],
                    'name'   => $op['name'],
                ];
            }

            echo json_encode([
                'success'   => true,
                'operators' => $operatorsOut,
                'lines'     => $linesOut,
                'matrix'    => $matrix,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('CertificationController::getMatrix: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta kompetensmatris'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function addCertification() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $opNumber    = isset($data['op_number'])     ? intval($data['op_number'])     : null;
        $line        = trim($data['line'] ?? '');
        $certDate    = trim($data['certified_date']  ?? '');
        $expiresDate = isset($data['expires_date'])  && $data['expires_date'] !== ''
                           ? trim($data['expires_date']) : null;
        $notes       = isset($data['notes']) ? mb_substr(strip_tags(trim($data['notes'])), 0, 1000) : null;

        $allowedLines = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje'];

        if (!$opNumber || $opNumber <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt operatörsnummer'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!in_array($line, $allowedLines, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltig linje'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $certDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt certifieringsdatum'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($expiresDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt utgångsdatum'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($expiresDate !== null && $expiresDate <= $certDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Utgångsdatum måste vara efter certifieringsdatum'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $certifiedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        try {
            $this->pdo->beginTransaction();

            // Deaktivera eventuell befintlig aktiv certifiering för samma operatör+linje
            // FOR UPDATE förhindrar race condition vid concurrent requests
            $existStmt = $this->pdo->prepare(
                "SELECT id FROM operator_certifications WHERE op_number = ? AND line = ? AND active = 1 FOR UPDATE"
            );
            $existStmt->execute([$opNumber, $line]);
            $existing = $existStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($existing)) {
                $placeholders = implode(',', array_fill(0, count($existing), '?'));
                $deactivateStmt = $this->pdo->prepare(
                    "UPDATE operator_certifications SET active = 0 WHERE id IN ($placeholders)"
                );
                $deactivateStmt->execute($existing);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO operator_certifications
                    (op_number, line, certified_by, certified_date, expires_date, notes, active)
                VALUES
                    (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$opNumber, $line, $certifiedBy, $certDate, $expiresDate, $notes ?: null]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

            AuditLogger::log($this->pdo, 'add_certification', 'operator_certifications', $newId,
                "Certifiering tillagd: operatör #$opNumber, linje: $line",
                null, ['op_number' => $opNumber, 'line' => $line, 'certified_date' => $certDate]);
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Certifiering tillagd'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('CertificationController::addCertification: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte lägga till certifiering'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function revokeCertification() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = isset($data['id']) ? intval($data['id']) : null;

        if (!$id || $id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE operator_certifications SET active = 0 WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Certifiering hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }

            AuditLogger::log($this->pdo, 'revoke_certification', 'operator_certifications', $id,
                "Certifiering återkallad (ID: $id)");
            echo json_encode(['success' => true, 'message' => 'Certifiering återkallad'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('CertificationController::revokeCertification: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte återkalla certifiering'], JSON_UNESCAPED_UNICODE);
        }
    }
}
