<?php

/**
 * SkiftoverlamningController
 * Hanterar skiftöverlämningsmall — auto-genererad sammanfattning av senaste
 * avslutade skiftet baserat på rebotling_ibc-data samt fritext-noteringar.
 *
 * Endpoints:
 *   GET  ?action=skiftoverlamning&run=summary              — senaste avslutade skiftet
 *   GET  ?action=skiftoverlamning&run=summary&skiftraknare=N — specifikt skift
 *   GET  ?action=skiftoverlamning&run=notes&skiftraknare=N — noteringar för skift
 *   GET  ?action=skiftoverlamning&run=history&days=N        — historik N dagar
 *   POST ?action=skiftoverlamning&run=add-note              — lägg till notering
 */
class SkiftoverlamningController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            if ($run === 'summary') {
                $this->getSummary();
            } elseif ($run === 'notes') {
                $this->getNotes();
            } elseif ($run === 'history') {
                $this->getHistory();
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Okänd run-parameter']);
            }
            return;
        }

        if ($method === 'POST') {
            if ($run === 'add-note') {
                $this->requireLogin();
                $this->addNote();
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Okänd run-parameter']);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod']);
    }

    // -------------------------------------------------------------------------
    // Auth-hjälpare
    // -------------------------------------------------------------------------

    private function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.']);
            exit;
        }
    }

    private function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    // -------------------------------------------------------------------------
    // GET run=summary
    // Hämtar produktionsdata för ett skift från rebotling_ibc.
    // Aggregering: MAX() per skiftraknare (kumulativa PLC-fält), sedan SUM().
    // -------------------------------------------------------------------------

    private function getSummary(): void {
        $specificSkift = isset($_GET['skiftraknare']) ? intval($_GET['skiftraknare']) : null;

        try {
            // Hitta skiftraknare att sammanfatta
            if ($specificSkift !== null && $specificSkift > 0) {
                $skiftraknare = $specificSkift;
            } else {
                // Hämta senaste avslutade skiftet (inte pågående):
                // senaste skiftraknare som har fler än en rad (avslutad)
                $latestStmt = $this->pdo->query(
                    "SELECT skiftraknare
                     FROM rebotling_ibc
                     GROUP BY skiftraknare
                     HAVING COUNT(*) > 1
                     ORDER BY skiftraknare DESC
                     LIMIT 1"
                );
                $latestRow = $latestStmt->fetch(PDO::FETCH_ASSOC);
                if (!$latestRow) {
                    echo json_encode(['success' => false, 'error' => 'Ingen produktionsdata hittades']);
                    return;
                }
                $skiftraknare = (int)$latestRow['skiftraknare'];
            }

            // Aggregera IBC-data: MAX per PLC-rad, sedan summera
            $stmt = $this->pdo->prepare(
                "SELECT
                    MAX(ibc_ok)        AS ibc_ok,
                    MAX(ibc_ej_ok)     AS ibc_ej_ok,
                    MAX(bur_ej_ok)     AS bur_ej_ok,
                    MAX(runtime_plc)   AS runtime_plc,
                    MAX(rasttime)      AS rasttime,
                    MIN(time_of_day)   AS skift_start,
                    MAX(time_of_day)   AS skift_slut,
                    DATE(MIN(created_at)) AS skift_datum
                 FROM rebotling_ibc
                 WHERE skiftraknare = ?"
            );
            $stmt->execute([$skiftraknare]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || $row['ibc_ok'] === null) {
                echo json_encode(['success' => false, 'error' => 'Ingen data för skiftet']);
                return;
            }

            $ibcOk      = (int)$row['ibc_ok'];
            $ibcEjOk    = (int)$row['ibc_ej_ok'];
            $burEjOk    = (int)$row['bur_ej_ok'];
            $runtimeMin = (int)$row['runtime_plc'];   // minuter
            $rastMin    = (int)$row['rasttime'];       // minuter

            $ibcTotal  = $ibcOk + $ibcEjOk;
            $kvalitet  = $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;

            // Skifttid i minuter (max 8h = 480 min)
            $skiftMin  = 480;
            $stopptid  = max(0, $skiftMin - $runtimeMin);
            $drifttid  = $runtimeMin;

            // IBC per timme
            $ibcPerH   = $drifttid > 0 ? round($ibcOk / ($drifttid / 60), 1) : 0.0;

            // Cykeltid snitt (sekunder per IBC)
            $cykeltid  = $ibcOk > 0 ? round(($drifttid * 60) / $ibcOk, 1) : 0.0;

            // Stopptid som andel av skifttid
            $stopptidPct = $skiftMin > 0 ? round(($stopptid / $skiftMin) * 100, 1) : 0.0;

            // Hämta noteringar för detta skift
            $notes = $this->fetchNotes($skiftraknare, 'rebotling');

            // Hämta angränsande skiftrakare för historik-navigering
            $prevStmt = $this->pdo->prepare(
                "SELECT skiftraknare FROM rebotling_ibc
                 WHERE skiftraknare < ?
                 GROUP BY skiftraknare HAVING COUNT(*) > 1
                 ORDER BY skiftraknare DESC LIMIT 1"
            );
            $prevStmt->execute([$skiftraknare]);
            $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);

            $nextStmt = $this->pdo->prepare(
                "SELECT skiftraknare FROM rebotling_ibc
                 WHERE skiftraknare > ?
                 GROUP BY skiftraknare HAVING COUNT(*) > 1
                 ORDER BY skiftraknare ASC LIMIT 1"
            );
            $nextStmt->execute([$skiftraknare]);
            $nextRow = $nextStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'       => true,
                'skiftraknare'  => $skiftraknare,
                'skift_datum'   => $row['skift_datum'],
                'skift_start'   => $row['skift_start'],
                'skift_slut'    => $row['skift_slut'],
                'ibc_ok'        => $ibcOk,
                'ibc_ej_ok'     => $ibcEjOk,
                'bur_ej_ok'     => $burEjOk,
                'ibc_total'     => $ibcTotal,
                'kvalitet_pct'  => $kvalitet,
                'ibc_per_timme' => $ibcPerH,
                'cykeltid_sek'  => $cykeltid,
                'drifttid_min'  => $drifttid,
                'stopptid_min'  => $stopptid,
                'stopptid_pct'  => $stopptidPct,
                'rast_min'      => $rastMin,
                'notes'         => $notes,
                'prev_skift'    => $prevRow ? (int)$prevRow['skiftraknare'] : null,
                'next_skift'    => $nextRow ? (int)$nextRow['skiftraknare'] : null,
            ]);
        } catch (PDOException $e) {
            error_log('SkiftoverlamningController getSummary: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta skiftdata']);
        }
    }

    // -------------------------------------------------------------------------
    // GET run=notes&skiftraknare=N
    // -------------------------------------------------------------------------

    private function getNotes(): void {
        $skiftraknare = isset($_GET['skiftraknare']) ? intval($_GET['skiftraknare']) : 0;
        $linje        = preg_match('/^[a-z_-]{1,50}$/', $_GET['linje'] ?? '') ? $_GET['linje'] : 'rebotling';

        if ($skiftraknare <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'skiftraknare krävs']);
            return;
        }

        try {
            $notes = $this->fetchNotes($skiftraknare, $linje);
            echo json_encode(['success' => true, 'notes' => $notes]);
        } catch (PDOException $e) {
            error_log('SkiftoverlamningController getNotes: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta noteringar']);
        }
    }

    // -------------------------------------------------------------------------
    // GET run=history&days=N
    // Hämtar en sammanfattning per skift för de senaste N dagarna.
    // -------------------------------------------------------------------------

    private function getHistory(): void {
        $days = max(1, min(30, intval($_GET['days'] ?? 7)));

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    skiftraknare,
                    MAX(ibc_ok)      AS ibc_ok,
                    MAX(ibc_ej_ok)   AS ibc_ej_ok,
                    MAX(runtime_plc) AS runtime_plc,
                    DATE(MIN(created_at)) AS skift_datum,
                    MIN(time_of_day) AS skift_start,
                    MAX(time_of_day) AS skift_slut
                 FROM rebotling_ibc
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY skiftraknare
                 HAVING COUNT(*) > 1
                 ORDER BY skiftraknare DESC
                 LIMIT 50"
            );
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $history = [];
            foreach ($rows as $r) {
                $ibcOk    = (int)$r['ibc_ok'];
                $ibcEjOk  = (int)$r['ibc_ej_ok'];
                $ibcTotal = $ibcOk + $ibcEjOk;
                $runtime  = (int)$r['runtime_plc'];
                $kvalitet = $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;
                $ibcPerH  = $runtime > 0 ? round($ibcOk / ($runtime / 60), 1) : 0.0;

                $history[] = [
                    'skiftraknare'  => (int)$r['skiftraknare'],
                    'skift_datum'   => $r['skift_datum'],
                    'skift_start'   => $r['skift_start'],
                    'skift_slut'    => $r['skift_slut'],
                    'ibc_ok'        => $ibcOk,
                    'ibc_ej_ok'     => $ibcEjOk,
                    'ibc_total'     => $ibcTotal,
                    'kvalitet_pct'  => $kvalitet,
                    'ibc_per_timme' => $ibcPerH,
                    'drifttid_min'  => $runtime,
                ];
            }

            echo json_encode(['success' => true, 'history' => $history]);
        } catch (PDOException $e) {
            error_log('SkiftoverlamningController getHistory: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta historik']);
        }
    }

    // -------------------------------------------------------------------------
    // POST run=add-note
    // Body: { skiftraknare, note_text, linje? }
    // -------------------------------------------------------------------------

    private function addNote(): void {
        $data         = json_decode(file_get_contents('php://input'), true) ?? [];
        $skiftraknare = isset($data['skiftraknare']) ? intval($data['skiftraknare']) : 0;
        $noteText     = isset($data['note_text']) ? strip_tags(trim($data['note_text'])) : '';
        $linje        = preg_match('/^[a-z_-]{1,50}$/', $data['linje'] ?? '') ? $data['linje'] : 'rebotling';
        $userId       = $this->currentUserId();

        if ($skiftraknare <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ogiltigt skiftraknare']);
            return;
        }
        if (empty($noteText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Noteringstext krävs']);
            return;
        }
        if (mb_strlen($noteText) > 1000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Notering får inte vara längre än 1000 tecken']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO skiftoverlamning_notes (skiftraknare, linje, note_text, user_id, created_at)
                 VALUES (:skiftraknare, :linje, :note_text, :user_id, NOW())"
            );
            $stmt->execute([
                ':skiftraknare' => $skiftraknare,
                ':linje'        => $linje,
                ':note_text'    => $noteText,
                ':user_id'      => $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            // Hämta användarnamn för svar
            $username = null;
            if ($userId) {
                $uStmt = $this->pdo->prepare('SELECT username FROM users WHERE id = ?');
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                $username = $uRow['username'] ?? null;
            }

            echo json_encode([
                'success' => true,
                'note' => [
                    'id'           => $newId,
                    'skiftraknare' => $skiftraknare,
                    'linje'        => $linje,
                    'note_text'    => $noteText,
                    'user_id'      => $userId,
                    'username'     => $username,
                    'created_at'   => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (PDOException $e) {
            error_log('SkiftoverlamningController addNote: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte spara notering']);
        }
    }

    // -------------------------------------------------------------------------
    // Intern hjälpmetod: hämta noteringar för ett skift
    // -------------------------------------------------------------------------

    private function fetchNotes(int $skiftraknare, string $linje): array {
        // Kontrollera att tabellen finns (kan vara ny installation)
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'skiftoverlamning_notes'"
            )->fetchColumn();
            if (!$check) return [];
        } catch (PDOException $e) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT n.id, n.skiftraknare, n.linje, n.note_text, n.user_id, n.created_at,
                    u.username
             FROM skiftoverlamning_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.skiftraknare = ? AND n.linje = ?
             ORDER BY n.created_at ASC"
        );
        $stmt->execute([$skiftraknare, $linje]);
        $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $notes = [];
        foreach ($rows as $r) {
            $notes[] = [
                'id'           => (int)$r['id'],
                'skiftraknare' => (int)$r['skiftraknare'],
                'linje'        => $r['linje'],
                'note_text'    => $r['note_text'],
                'user_id'      => $r['user_id'] !== null ? (int)$r['user_id'] : null,
                'username'     => $r['username'],
                'created_at'   => $r['created_at'],
            ];
        }
        return $notes;
    }
}
