<?php

class ShiftPlanController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run    = $_GET['run'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $run === 'week') {
            $this->getWeek();
            return;
        }

        if ($method === 'GET' && $run === 'operators') {
            $this->getOperators();
            return;
        }

        if ($method === 'POST' && $run === 'assign') {
            $this->requireAdmin();
            $this->assign();
            return;
        }

        if (($method === 'DELETE' || $method === 'POST') && $run === 'remove') {
            $this->requireAdmin();
            $this->remove();
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint hittades inte']);
    }

    // -----------------------------------------------------------------------
    // Auth-kontroll
    // -----------------------------------------------------------------------

    private function requireAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Endast admin har behörighet.']);
            exit;
        }
    }

    // -----------------------------------------------------------------------
    // GET ?action=shift-plan&run=week&date=YYYY-MM-DD
    // Returnerar veckodata för veckan som innehåller det givna datumet.
    // -----------------------------------------------------------------------

    private function getWeek() {
        $dateParam = $_GET['date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
            $dateParam = date('Y-m-d');
        }

        try {
            $dt = new DateTime($dateParam);
        } catch (Exception $e) {
            $dt = new DateTime();
        }

        // Beräkna veckans måndag (ISO-vecka: måndag = 1)
        $dow = (int)$dt->format('N'); // 1=mån, 7=sön
        $dt->modify('-' . ($dow - 1) . ' days');
        $weekStart = $dt->format('Y-m-d');

        // Bygg array med 7 dagar
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = clone $dt;
            $d->modify("+$i days");
            $days[] = $d->format('Y-m-d');
        }
        $weekEnd = end($days);

        try {
            $stmt = $this->pdo->prepare('
                SELECT
                    sp.datum,
                    sp.skift_nr,
                    sp.op_number,
                    sp.note,
                    o.name AS op_name
                FROM shift_plan sp
                LEFT JOIN operators o ON o.number = sp.op_number
                WHERE sp.datum BETWEEN :start AND :end
                ORDER BY sp.datum ASC, sp.skift_nr ASC, sp.op_number ASC
            ');
            $stmt->execute([':start' => $weekStart, ':end' => $weekEnd]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Strukturera om till { "YYYY-MM-DD": { "1": [...], "2": [...], "3": [...] } }
            $result = [];
            foreach ($days as $day) {
                $result[$day] = ['1' => [], '2' => [], '3' => []];
            }
            foreach ($rows as $row) {
                $d = $row['datum'];
                $s = (string)$row['skift_nr'];
                if (isset($result[$d][$s])) {
                    $result[$d][$s][] = [
                        'op_number' => (int)$row['op_number'],
                        'op_name'   => $row['op_name'] ?? ('Operatör #' . $row['op_number']),
                        'note'      => $row['note'],
                    ];
                }
            }

            echo json_encode([
                'success'    => true,
                'week_start' => $weekStart,
                'days'       => $result,
            ]);
        } catch (PDOException $e) {
            error_log('ShiftPlanController getWeek: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftplan']);
        }
    }

    // -----------------------------------------------------------------------
    // GET ?action=shift-plan&run=operators
    // Returnerar lista med aktiva operatörer.
    // -----------------------------------------------------------------------

    private function getOperators() {
        try {
            $stmt = $this->pdo->query('
                SELECT number AS op_number, name AS op_name
                FROM operators
                WHERE active = 1
                ORDER BY number ASC
            ');
            $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($operators as &$op) {
                $op['op_number'] = (int)$op['op_number'];
            }
            unset($op);
            echo json_encode(['success' => true, 'operators' => $operators]);
        } catch (PDOException $e) {
            error_log('ShiftPlanController getOperators: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörer']);
        }
    }

    // -----------------------------------------------------------------------
    // POST ?action=shift-plan&run=assign
    // Body: { datum, skift_nr, op_number, note }
    // -----------------------------------------------------------------------

    private function assign() {
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $datum    = $data['datum']    ?? '';
        $skiftNr  = isset($data['skift_nr'])   ? intval($data['skift_nr'])   : 0;
        $opNumber = isset($data['op_number'])  ? intval($data['op_number'])  : 0;
        $note     = isset($data['note'])       ? trim($data['note'])         : null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum']);
            return;
        }
        if ($skiftNr < 1 || $skiftNr > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'skift_nr måste vara 1, 2 eller 3']);
            return;
        }
        if ($opNumber <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt op_number']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO shift_plan (datum, skift_nr, op_number, note)
                VALUES (:datum, :skift_nr, :op_number, :note)
                ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([
                ':datum'     => $datum,
                ':skift_nr'  => $skiftNr,
                ':op_number' => $opNumber,
                ':note'      => $note ?: null,
            ]);
            echo json_encode(['success' => true, 'message' => 'Operatör tillagd i skift']);
        } catch (PDOException $e) {
            error_log('ShiftPlanController assign: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte lägga till operatör']);
        }
    }

    // -----------------------------------------------------------------------
    // DELETE/POST ?action=shift-plan&run=remove
    // Body: { datum, skift_nr, op_number }
    // -----------------------------------------------------------------------

    private function remove() {
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $datum    = $data['datum']    ?? '';
        $skiftNr  = isset($data['skift_nr'])   ? intval($data['skift_nr'])   : 0;
        $opNumber = isset($data['op_number'])  ? intval($data['op_number'])  : 0;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || $skiftNr < 1 || $skiftNr > 3 || $opNumber <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltiga parametrar']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM shift_plan
                WHERE datum = :datum AND skift_nr = :skift_nr AND op_number = :op_number
            ');
            $stmt->execute([
                ':datum'     => $datum,
                ':skift_nr'  => $skiftNr,
                ':op_number' => $opNumber,
            ]);
            echo json_encode(['success' => true, 'message' => 'Operatör borttagen från skift']);
        } catch (PDOException $e) {
            error_log('ShiftPlanController remove: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort operatör']);
        }
    }
}
