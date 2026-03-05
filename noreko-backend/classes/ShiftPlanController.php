<?php

class ShiftPlanController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run    = trim($_GET['run'] ?? '');
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $run === 'week') {
            $this->getWeek();
            return;
        }

        if ($method === 'GET' && $run === 'operators') {
            $this->getOperators();
            return;
        }

        if ($method === 'GET' && $run === 'week-view') {
            $this->getWeekView();
            return;
        }

        if ($method === 'GET' && $run === 'operators-list') {
            $this->getOperatorsList();
            return;
        }

        if ($method === 'GET' && $run === 'staffing-warning') {
            $this->getStaffingWarning();
            return;
        }

        if ($method === 'POST' && $run === 'assign') {
            $this->requireAdmin();
            $this->assign();
            return;
        }

        if ($method === 'POST' && $run === 'copy-week') {
            $this->requireAdmin();
            $this->copyWeek();
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
    // GET ?action=shift-plan&run=week-view&week_start=YYYY-MM-DD
    // Returnerar 7 dagar × 3 skift med planerade + faktiska operatörer.
    // Faktisk närvaro hämtas från rebotling_ibc (op1, op2, op3) per datum + skifttid.
    // Skift 1 = morgon 06–14, Skift 2 = eftermiddag 14–22, Skift 3 = natt 22–06
    // -----------------------------------------------------------------------

    private function getWeekView() {
        $weekStartParam = $_GET['week_start'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
            $weekStartParam = date('Y-m-d');
        }

        try {
            $dt = new DateTime($weekStartParam);
        } catch (Exception $e) {
            $dt = new DateTime();
        }

        // Säkerställ att vi utgår från måndag
        $dow = (int)$dt->format('N');
        $dt->modify('-' . ($dow - 1) . ' days');
        $weekStart = $dt->format('Y-m-d');

        // 7 dagar
        $days = [];
        $dagNamn = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
        for ($i = 0; $i < 7; $i++) {
            $d = clone $dt;
            $d->modify("+$i days");
            $days[] = $d->format('Y-m-d');
        }
        $weekEnd = end($days);

        try {
            // --- Planerade operatörer ---
            $stmtPlan = $this->pdo->prepare('
                SELECT
                    sp.datum,
                    sp.skift_nr,
                    sp.op_number,
                    o.name AS op_name
                FROM shift_plan sp
                LEFT JOIN operators o ON o.number = sp.op_number
                WHERE sp.datum BETWEEN :start AND :end
                ORDER BY sp.datum ASC, sp.skift_nr ASC, sp.op_number ASC
            ');
            $stmtPlan->execute([':start' => $weekStart, ':end' => $weekEnd]);
            $planRows = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

            // --- Faktiska operatörer från rebotling_ibc ---
            // Skift 1 (morgon 06–14): MIN(datum) timme 5–13
            // Skift 2 (eftermiddag 14–22): MIN(datum) timme 13–21
            // Skift 3 (natt 22–06): MIN(datum) timme 21–05 (dvs >= 21 ELLER < 5)
            // Vi UNION op1, op2, op3 och tar DISTINCT per dag+skift
            $stmtFaktisk = $this->pdo->prepare('
                SELECT
                    faktisk_datum,
                    skift_nr,
                    op_num,
                    o.name AS op_name
                FROM (
                    SELECT DISTINCT
                        DATE(datum) AS faktisk_datum,
                        CASE
                            WHEN HOUR(datum) >= 6  AND HOUR(datum) < 14 THEN 1
                            WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 2
                            ELSE 3
                        END AS skift_nr,
                        op_val AS op_num
                    FROM (
                        SELECT datum, op1 AS op_val FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :start1 AND :end1
                          AND op1 IS NOT NULL AND op1 > 0
                        UNION ALL
                        SELECT datum, op2 FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :start2 AND :end2
                          AND op2 IS NOT NULL AND op2 > 0
                        UNION ALL
                        SELECT datum, op3 FROM rebotling_ibc
                        WHERE DATE(datum) BETWEEN :start3 AND :end3
                          AND op3 IS NOT NULL AND op3 > 0
                    ) raw
                ) distinct_ops
                LEFT JOIN operators o ON o.number = distinct_ops.op_num
                WHERE faktisk_datum BETWEEN :start4 AND :end4
                ORDER BY faktisk_datum ASC, skift_nr ASC, op_num ASC
            ');
            $stmtFaktisk->execute([
                ':start1' => $weekStart, ':end1' => $weekEnd,
                ':start2' => $weekStart, ':end2' => $weekEnd,
                ':start3' => $weekStart, ':end3' => $weekEnd,
                ':start4' => $weekStart, ':end4' => $weekEnd,
            ]);
            $faktiskaRows = $stmtFaktisk->fetchAll(PDO::FETCH_ASSOC);

            // --- Bygg index för planerade ---
            $planIndex = []; // [datum][skift_nr] = [op_number => op_name, ...]
            foreach ($planRows as $row) {
                $d = $row['datum'];
                $s = (int)$row['skift_nr'];
                $opNum = (int)$row['op_number'];
                if (!isset($planIndex[$d])) $planIndex[$d] = [1 => [], 2 => [], 3 => []];
                $planIndex[$d][$s][$opNum] = $row['op_name'] ?? ('Op #' . $opNum);
            }

            // --- Bygg index för faktiska ---
            $faktiskIndex = []; // [datum][skift_nr] = [op_number => op_name, ...]
            foreach ($faktiskaRows as $row) {
                $d = $row['faktisk_datum'];
                $s = (int)$row['skift_nr'];
                $opNum = (int)$row['op_num'];
                if (!isset($faktiskIndex[$d])) $faktiskIndex[$d] = [1 => [], 2 => [], 3 => []];
                $faktiskIndex[$d][$s][$opNum] = $row['op_name'] ?? ('Op #' . $opNum);
            }

            // --- Bygg slots: 7 dagar × 3 skift = 21 slots ---
            $slots = [];
            $skiftLabels = [1 => 'Morgon', 2 => 'Eftermiddag', 3 => 'Natt'];
            $skiftTider  = [1 => '06–14',  2 => '14–22',       3 => '22–06'];

            foreach ($days as $i => $datum) {
                foreach ([1, 2, 3] as $skiftNr) {
                    $planOps    = $planIndex[$datum][$skiftNr]    ?? [];
                    $faktOps    = $faktiskIndex[$datum][$skiftNr] ?? [];

                    // Planerade operatörer
                    $planeradeLista = [];
                    foreach ($planOps as $opNum => $opNamn) {
                        $planeradeLista[] = [
                            'op_number' => $opNum,
                            'op_name'   => $opNamn,
                            'initialer' => $this->getInitials($opNamn),
                        ];
                    }

                    // Faktiska operatörer — med matchningsstatus
                    $faktiskaLista = [];
                    foreach ($faktOps as $opNum => $opNamn) {
                        $faktiskaLista[] = [
                            'op_number' => $opNum,
                            'op_name'   => $opNamn,
                            'initialer' => $this->getInitials($opNamn),
                            'planerad'  => isset($planOps[$opNum]), // grön bock om planerad, röd om oplanerad
                        ];
                    }

                    // Planerade som INTE dök upp (planerad men ej faktisk)
                    $uteblev = [];
                    foreach ($planOps as $opNum => $opNamn) {
                        if (!isset($faktOps[$opNum])) {
                            $uteblev[] = [
                                'op_number' => $opNum,
                                'op_name'   => $opNamn,
                                'initialer' => $this->getInitials($opNamn),
                            ];
                        }
                    }

                    $slots[] = [
                        'datum'          => $datum,
                        'skift_nr'       => $skiftNr,
                        'dag_namn'       => $dagNamn[$i],
                        'skift_label'    => $skiftLabels[$skiftNr],
                        'skift_tid'      => $skiftTider[$skiftNr],
                        'planerade_ops'  => $planeradeLista,
                        'faktiska_ops'   => $faktiskaLista,
                        'uteblev_ops'    => $uteblev,
                    ];
                }
            }

            echo json_encode([
                'success'    => true,
                'week_start' => $weekStart,
                'week_end'   => $weekEnd,
                'slots'      => $slots,
            ]);

        } catch (PDOException $e) {
            error_log('ShiftPlanController getWeekView: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta veckoöversikt']);
        }
    }

    // -----------------------------------------------------------------------
    // Hjälpfunktion: initialer från namn
    // -----------------------------------------------------------------------

    private function getInitials(string $name): string {
        if (!$name) return '?';
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return strtoupper(mb_substr($name, 0, 2));
    }

    // -----------------------------------------------------------------------
    // GET ?action=shift-plan&run=staffing-warning
    // Returnerar dagar de närmaste 7 dagarna med underbemanning.
    // -----------------------------------------------------------------------

    private function getStaffingWarning() {
        try {
            // Hämta min_operators från rebotling_settings (default 2)
            $minOps = 2;
            try {
                $sr = $this->pdo->query("SELECT min_operators FROM rebotling_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr && isset($sr['min_operators'])) {
                    $minOps = max(1, (int)$sr['min_operators']);
                }
            } catch (Exception $ignored) {
                // Kolumnen finns inte ännu
            }

            $today   = date('Y-m-d');
            $in7days = date('Y-m-d', strtotime('+6 days'));

            $stmt = $this->pdo->prepare("
                SELECT datum, skift_nr, COUNT(DISTINCT op_number) AS antal_ops
                FROM shift_plan
                WHERE datum BETWEEN :today AND :in7days
                GROUP BY datum, skift_nr
            ");
            $stmt->execute([':today' => $today, ':in7days' => $in7days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $planIndex = [];
            foreach ($rows as $row) {
                $planIndex[$row['datum']][(int)$row['skift_nr']] = (int)$row['antal_ops'];
            }

            $warnings = [];
            $dagNamn  = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
            for ($i = 0; $i < 7; $i++) {
                $datum = date('Y-m-d', strtotime("+{$i} days"));
                $underbemanning = [];
                for ($skift = 1; $skift <= 3; $skift++) {
                    $antal = $planIndex[$datum][$skift] ?? 0;
                    if ($antal < $minOps) {
                        $underbemanning[] = ['skift_nr' => $skift, 'antal_ops' => $antal];
                    }
                }
                if (!empty($underbemanning)) {
                    $dow = (int)date('N', strtotime($datum)) - 1;
                    $warnings[] = [
                        'datum'          => $datum,
                        'dag_namn'       => $dagNamn[$dow],
                        'underbemanning' => $underbemanning,
                    ];
                }
            }

            echo json_encode([
                'success'       => true,
                'min_operators' => $minOps,
                'warnings'      => $warnings,
            ]);
        } catch (PDOException $e) {
            error_log('ShiftPlanController getStaffingWarning: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta bemanningsvarning']);
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
    // GET ?action=shift-plan&run=operators-list
    // Alias för getOperators — exponerar initialer också.
    // -----------------------------------------------------------------------

    private function getOperatorsList() {
        try {
            $stmt = $this->pdo->query('
                SELECT number AS op_number, name AS op_name
                FROM operators
                WHERE active = 1
                ORDER BY name ASC
            ');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $operators = [];
            foreach ($rows as $row) {
                $operators[] = [
                    'op_number' => (int)$row['op_number'],
                    'op_name'   => $row['op_name'],
                    'initialer' => $this->getInitials($row['op_name']),
                ];
            }
            echo json_encode(['success' => true, 'operators' => $operators]);
        } catch (PDOException $e) {
            error_log('ShiftPlanController getOperatorsList: ' . $e->getMessage());
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
    // POST ?action=shift-plan&run=copy-week
    // Body: { target_week_start: "YYYY-MM-DD" }
    // Kopierar alla tilldelningar från föregående vecka till angiven vecka.
    // -----------------------------------------------------------------------

    private function copyWeek() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $targetStart = $data['target_week_start'] ?? '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetStart)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum för target_week_start']);
            return;
        }

        try {
            $dt = new DateTime($targetStart);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt datum']);
            return;
        }

        // Säkerställ att target_week_start är en måndag
        $dow = (int)$dt->format('N');
        $dt->modify('-' . ($dow - 1) . ' days');
        $targetMonday = $dt->format('Y-m-d');

        // Beräkna föregående veckas måndag och söndag
        $prevMonday = clone $dt;
        $prevMonday->modify('-7 days');
        $prevStart = $prevMonday->format('Y-m-d');
        $prevEnd = clone $prevMonday;
        $prevEnd->modify('+6 days');
        $prevEndStr = $prevEnd->format('Y-m-d');

        try {
            // Hämta alla tilldelningar från föregående vecka
            $stmt = $this->pdo->prepare('
                SELECT datum, skift_nr, op_number, note
                FROM shift_plan
                WHERE datum BETWEEN :start AND :end
                ORDER BY datum ASC, skift_nr ASC
            ');
            $stmt->execute([':start' => $prevStart, ':end' => $prevEndStr]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode(['success' => false, 'error' => 'Inga tilldelningar att kopiera från föregående vecka']);
                return;
            }

            // Kopiera varje rad med +7 dagar på datumet
            $insertStmt = $this->pdo->prepare('
                INSERT INTO shift_plan (datum, skift_nr, op_number, note)
                VALUES (:datum, :skift_nr, :op_number, :note)
                ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = CURRENT_TIMESTAMP
            ');

            $copied = 0;
            foreach ($rows as $row) {
                $origDate = new DateTime($row['datum']);
                $origDate->modify('+7 days');
                $newDatum = $origDate->format('Y-m-d');

                $insertStmt->execute([
                    ':datum'     => $newDatum,
                    ':skift_nr'  => (int)$row['skift_nr'],
                    ':op_number' => (int)$row['op_number'],
                    ':note'      => $row['note'],
                ]);
                $copied++;
            }

            echo json_encode([
                'success' => true,
                'message' => "Kopierade $copied tilldelning(ar) från v." . $prevMonday->format('W') . " till v." . $dt->format('W'),
                'copied'  => $copied,
            ]);

        } catch (PDOException $e) {
            error_log('ShiftPlanController copyWeek: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte kopiera schema']);
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
