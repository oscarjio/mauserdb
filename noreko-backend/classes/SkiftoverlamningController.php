<?php

/**
 * SkiftoverlamningController
 * Skiftöverlämningslogg — strukturerad digital överlämning mellan skift.
 *
 * Endpoints via ?action=skiftoverlamning&run=XXX:
 *
 *   GET  run=list          — Lista överlämningar (filtrerad per skift_typ, operator_id, from, to)
 *   GET  run=detail&id=N   — Fullständig vy av en överlämning
 *   GET  run=shift-kpis    — Auto-hämta KPI:er för aktuellt/senaste skift
 *   GET  run=summary       — Sammanfattnings-KPI:er (senaste överlämning, antal vecka, snitt, pågående problem)
 *   GET  run=operators     — Lista operatörer (för filter-dropdown)
 *   POST run=create        — Skapa ny överlämning
 */
class SkiftoverlamningController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'list':       $this->getList();      break;
                case 'detail':     $this->getDetail();    break;
                case 'shift-kpis': $this->getShiftKpis(); break;
                case 'summary':    $this->getSummaryKpis(); break;
                case 'operators':  $this->getOperators(); break;
                default:
                    $this->sendError('Okänd run-parameter', 404);
            }
            return;
        }

        if ($method === 'POST') {
            if ($run === 'create') {
                $this->requireLogin();
                $this->createHandover();
            } else {
                $this->sendError('Okänd run-parameter', 404);
            }
            return;
        }

        $this->sendError('Ogiltig metod', 405);
    }

    // =========================================================================
    // Auth
    // =========================================================================

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

    private function currentUsername(): ?string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return $_SESSION['username'] ?? null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data));
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }

    private function ensureTable(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'skiftoverlamning_logg'"
            )->fetchColumn();
            if (!$check) {
                $sql = file_get_contents(__DIR__ . '/../migrations/2026-03-12_skiftoverlamning.sql');
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController ensureTable: ' . $e->getMessage());
        }
    }

    /**
     * Bestäm skifttyp baserat på timme.
     * dag=06-14, kväll=14-22, natt=22-06
     */
    private function detectSkiftTyp(): string {
        $h = (int)date('G');
        if ($h >= 6 && $h < 14) return 'dag';
        if ($h >= 14 && $h < 22) return 'kvall';
        return 'natt';
    }

    // =========================================================================
    // GET run=list
    // Params: skift_typ, operator_id, from, to, limit, offset
    // =========================================================================

    private function getList(): void {
        try {
            $where  = [];
            $params = [];

            // Filter: skift_typ
            $skiftTyp = trim($_GET['skift_typ'] ?? '');
            if (in_array($skiftTyp, ['dag', 'kvall', 'natt'], true)) {
                $where[]  = 'l.skift_typ = ?';
                $params[] = $skiftTyp;
            }

            // Filter: operator_id
            $opId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;
            if ($opId > 0) {
                $where[]  = 'l.operator_id = ?';
                $params[] = $opId;
            }

            // Filter: datum from-to
            $from = trim($_GET['from'] ?? '');
            $to   = trim($_GET['to'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $where[]  = 'l.datum >= ?';
                $params[] = $from;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $where[]  = 'l.datum <= ?';
                $params[] = $to;
            }

            $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            // Hämta totalt antal
            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM skiftoverlamning_logg l {$whereSql}"
            );
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Hämta poster
            $stmt = $this->pdo->prepare(
                "SELECT l.id, l.operator_id, l.operator_namn, l.skift_typ, l.datum,
                        l.ibc_totalt, l.ibc_per_h, l.stopptid_min, l.kassationer,
                        l.problem_text, l.pagaende_arbete, l.instruktioner, l.kommentar,
                        l.har_pagaende_problem, l.skapad,
                        COALESCE(u.username, l.operator_namn) AS operatör
                 FROM skiftoverlamning_logg l
                 LEFT JOIN users u ON l.operator_id = u.id
                 {$whereSql}
                 ORDER BY l.skapad DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'id'                  => (int)$r['id'],
                    'operator_id'         => (int)$r['operator_id'],
                    'operator_namn'       => $r['operatör'] ?? $r['operator_namn'],
                    'skift_typ'           => $r['skift_typ'],
                    'skift_typ_label'     => $this->skiftTypLabel($r['skift_typ']),
                    'datum'               => $r['datum'],
                    'ibc_totalt'          => (int)$r['ibc_totalt'],
                    'ibc_per_h'           => (float)$r['ibc_per_h'],
                    'stopptid_min'        => (int)$r['stopptid_min'],
                    'kassationer'         => (int)$r['kassationer'],
                    'problem_text'        => $r['problem_text'],
                    'pagaende_arbete'     => $r['pagaende_arbete'],
                    'instruktioner'       => $r['instruktioner'],
                    'kommentar'           => $r['kommentar'],
                    'har_pagaende_problem'=> (bool)$r['har_pagaende_problem'],
                    'skapad'              => $r['skapad'],
                ];
            }

            $this->sendSuccess([
                'items' => $items,
                'total' => $total,
                'limit' => $limit,
                'offset'=> $offset,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getList: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta överlämningar', 500);
        }
    }

    // =========================================================================
    // GET run=detail&id=N
    // =========================================================================

    private function getDetail(): void {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->sendError('id krävs');
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT l.*, COALESCE(u.username, l.operator_namn) AS operatör
                 FROM skiftoverlamning_logg l
                 LEFT JOIN users u ON l.operator_id = u.id
                 WHERE l.id = ?"
            );
            $stmt->execute([$id]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$r) {
                $this->sendError('Överlämning hittades inte', 404);
                return;
            }

            $this->sendSuccess([
                'item' => [
                    'id'                  => (int)$r['id'],
                    'operator_id'         => (int)$r['operator_id'],
                    'operator_namn'       => $r['operatör'] ?? $r['operator_namn'],
                    'skift_typ'           => $r['skift_typ'],
                    'skift_typ_label'     => $this->skiftTypLabel($r['skift_typ']),
                    'datum'               => $r['datum'],
                    'ibc_totalt'          => (int)$r['ibc_totalt'],
                    'ibc_per_h'           => (float)$r['ibc_per_h'],
                    'stopptid_min'        => (int)$r['stopptid_min'],
                    'kassationer'         => (int)$r['kassationer'],
                    'problem_text'        => $r['problem_text'],
                    'pagaende_arbete'     => $r['pagaende_arbete'],
                    'instruktioner'       => $r['instruktioner'],
                    'kommentar'           => $r['kommentar'],
                    'har_pagaende_problem'=> (bool)$r['har_pagaende_problem'],
                    'skapad'              => $r['skapad'],
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta överlämning', 500);
        }
    }

    // =========================================================================
    // GET run=shift-kpis
    // Hämtar automatiska KPI:er för det senaste/aktuella skiftet från rebotling_ibc
    // =========================================================================

    private function getShiftKpis(): void {
        try {
            // Hämta senaste avslutade skiftet
            $stmt = $this->pdo->query(
                "SELECT skiftraknare,
                        MAX(ibc_ok) AS ibc_ok,
                        MAX(ibc_ej_ok) AS ibc_ej_ok,
                        MAX(runtime_plc) AS runtime_plc,
                        MIN(datum) AS skift_start,
                        MAX(datum) AS skift_slut,
                        DATE(MIN(datum)) AS skift_datum
                 FROM rebotling_ibc
                 GROUP BY skiftraknare
                 HAVING COUNT(*) > 1
                 ORDER BY skiftraknare DESC
                 LIMIT 1"
            );
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row || $row['ibc_ok'] === null) {
                $this->sendSuccess([
                    'kpis' => null,
                    'message' => 'Ingen produktionsdata tillgänglig',
                ]);
                return;
            }

            $ibcOk   = (int)$row['ibc_ok'];
            $ibcEjOk = (int)$row['ibc_ej_ok'];
            $ibcTotal = $ibcOk + $ibcEjOk;
            $runtime  = (int)$row['runtime_plc'];
            $skiftMin = 480;
            $stopptid = max(0, $skiftMin - $runtime);
            $ibcPerH  = $runtime > 0 ? round($ibcOk / ($runtime / 60), 1) : 0.0;

            // Bestäm skifttyp från starttid
            $startHour = (int)date('G', strtotime($row['skift_start']));
            if ($startHour >= 6 && $startHour < 14) $autoSkift = 'dag';
            elseif ($startHour >= 14 && $startHour < 22) $autoSkift = 'kvall';
            else $autoSkift = 'natt';

            $this->sendSuccess([
                'kpis' => [
                    'skiftraknare' => (int)$row['skiftraknare'],
                    'skift_datum'  => $row['skift_datum'],
                    'skift_start'  => $row['skift_start'],
                    'skift_slut'   => $row['skift_slut'],
                    'skift_typ'    => $autoSkift,
                    'ibc_totalt'   => $ibcTotal,
                    'ibc_ok'       => $ibcOk,
                    'ibc_per_h'    => $ibcPerH,
                    'stopptid_min' => $stopptid,
                    'kassationer'  => $ibcEjOk,
                    'drifttid_min' => $runtime,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getShiftKpis: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skift-KPI:er', 500);
        }
    }

    // =========================================================================
    // GET run=summary
    // Sammanfattnings-KPI:er: senaste överlämning, antal denna vecka,
    // genomsnittlig produktion (senaste 10), pågående problem
    // =========================================================================

    private function getSummaryKpis(): void {
        try {
            // 1. Senaste överlämningen
            $lastStmt = $this->pdo->query(
                "SELECT id, skapad, operator_namn, skift_typ, datum
                 FROM skiftoverlamning_logg
                 ORDER BY skapad DESC LIMIT 1"
            );
            $last = $lastStmt->fetch(\PDO::FETCH_ASSOC);

            // 2. Antal denna vecka (måndag–söndag)
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekStmt  = $this->pdo->prepare(
                "SELECT COUNT(*) FROM skiftoverlamning_logg WHERE datum >= ?"
            );
            $weekStmt->execute([$weekStart]);
            $weekCount = (int)$weekStmt->fetchColumn();

            // 3. Genomsnittlig produktion (senaste 10)
            $avgStmt = $this->pdo->query(
                "SELECT AVG(ibc_totalt) AS snitt
                 FROM (SELECT ibc_totalt FROM skiftoverlamning_logg ORDER BY skapad DESC LIMIT 10) sub"
            );
            $avgRow = $avgStmt->fetch(\PDO::FETCH_ASSOC);
            $avgProduction = $avgRow && $avgRow['snitt'] !== null ? round((float)$avgRow['snitt'], 1) : 0;

            // 4. Pågående problem (aktiva)
            $probStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM skiftoverlamning_logg WHERE har_pagaende_problem = 1"
            );
            $activeProblems = (int)$probStmt->fetchColumn();

            // 5. Senaste pågående problem-detaljer
            $probDetailStmt = $this->pdo->query(
                "SELECT id, datum, skift_typ, operator_namn, problem_text, pagaende_arbete
                 FROM skiftoverlamning_logg
                 WHERE har_pagaende_problem = 1
                 ORDER BY skapad DESC
                 LIMIT 5"
            );
            $activeItems = [];
            foreach ($probDetailStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $activeItems[] = [
                    'id'              => (int)$r['id'],
                    'datum'           => $r['datum'],
                    'skift_typ'       => $r['skift_typ'],
                    'skift_typ_label' => $this->skiftTypLabel($r['skift_typ']),
                    'operator_namn'   => $r['operator_namn'],
                    'problem_text'    => $r['problem_text'],
                    'pagaende_arbete' => $r['pagaende_arbete'],
                ];
            }

            $this->sendSuccess([
                'senaste_overlamning' => $last ? [
                    'id'            => (int)$last['id'],
                    'skapad'        => $last['skapad'],
                    'operator_namn' => $last['operator_namn'],
                    'skift_typ'     => $last['skift_typ'],
                    'datum'         => $last['datum'],
                ] : null,
                'antal_denna_vecka'       => $weekCount,
                'snitt_produktion_10'     => $avgProduction,
                'pagaende_problem_antal'  => $activeProblems,
                'pagaende_problem_lista'  => $activeItems,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getSummaryKpis: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // =========================================================================
    // GET run=operators
    // =========================================================================

    private function getOperators(): void {
        try {
            $stmt = $this->pdo->query(
                "SELECT DISTINCT l.operator_id, COALESCE(u.username, l.operator_namn) AS namn
                 FROM skiftoverlamning_logg l
                 LEFT JOIN users u ON l.operator_id = u.id
                 ORDER BY namn"
            );
            $operators = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $operators[] = [
                    'id'   => (int)$r['operator_id'],
                    'namn' => $r['namn'],
                ];
            }
            $this->sendSuccess(['operators' => $operators]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getOperators: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörer', 500);
        }
    }

    // =========================================================================
    // POST run=create
    // Body: { skift_typ, datum, ibc_totalt, ibc_per_h, stopptid_min, kassationer,
    //         problem_text, pagaende_arbete, instruktioner, kommentar, har_pagaende_problem }
    // =========================================================================

    private function createHandover(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId   = $this->currentUserId();
        $username = $this->currentUsername();

        // Om username inte finns i session, hämta från DB
        if (!$username && $userId) {
            try {
                $uStmt = $this->pdo->prepare('SELECT username FROM users WHERE id = ?');
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch(\PDO::FETCH_ASSOC);
                $username = $uRow['username'] ?? null;
            } catch (\PDOException $e) {
                // ignorera
            }
        }

        // Validering
        $skiftTyp = $data['skift_typ'] ?? '';
        if (!in_array($skiftTyp, ['dag', 'kvall', 'natt'], true)) {
            $skiftTyp = $this->detectSkiftTyp();
        }

        $datum = $data['datum'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            $datum = date('Y-m-d');
        }

        $ibcTotalt   = max(0, (int)($data['ibc_totalt'] ?? 0));
        $ibcPerH     = max(0, round((float)($data['ibc_per_h'] ?? 0), 1));
        $stopptidMin = max(0, (int)($data['stopptid_min'] ?? 0));
        $kassationer = max(0, (int)($data['kassationer'] ?? 0));

        $problemText    = isset($data['problem_text'])    ? strip_tags(trim($data['problem_text']))    : null;
        $pagaendeArbete = isset($data['pagaende_arbete']) ? strip_tags(trim($data['pagaende_arbete'])) : null;
        $instruktioner  = isset($data['instruktioner'])   ? strip_tags(trim($data['instruktioner']))   : null;
        $kommentar      = isset($data['kommentar'])       ? strip_tags(trim($data['kommentar']))       : null;

        $harPagaende = !empty($data['har_pagaende_problem']) ? 1 : 0;

        // Begränsa textlängder
        if ($problemText && mb_strlen($problemText) > 5000) $problemText = mb_substr($problemText, 0, 5000);
        if ($pagaendeArbete && mb_strlen($pagaendeArbete) > 5000) $pagaendeArbete = mb_substr($pagaendeArbete, 0, 5000);
        if ($instruktioner && mb_strlen($instruktioner) > 5000) $instruktioner = mb_substr($instruktioner, 0, 5000);
        if ($kommentar && mb_strlen($kommentar) > 5000) $kommentar = mb_substr($kommentar, 0, 5000);

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO skiftoverlamning_logg
                    (operator_id, operator_namn, skift_typ, datum,
                     ibc_totalt, ibc_per_h, stopptid_min, kassationer,
                     problem_text, pagaende_arbete, instruktioner, kommentar,
                     har_pagaende_problem, skapad)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                $username,
                $skiftTyp,
                $datum,
                $ibcTotalt,
                $ibcPerH,
                $stopptidMin,
                $kassationer,
                $problemText ?: null,
                $pagaendeArbete ?: null,
                $instruktioner ?: null,
                $kommentar ?: null,
                $harPagaende,
            ]);

            $newId = (int)$this->pdo->lastInsertId();

            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Skiftöverlämning sparad',
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController createHandover: ' . $e->getMessage());
            $this->sendError('Kunde inte spara överlämning', 500);
        }
    }

    // =========================================================================
    // Hjälp
    // =========================================================================

    private function skiftTypLabel(string $typ): string {
        switch ($typ) {
            case 'dag':   return 'Dag (06–14)';
            case 'kvall': return 'Kväll (14–22)';
            case 'natt':  return 'Natt (22–06)';
            default:      return $typ;
        }
    }
}
