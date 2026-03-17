<?php
/**
 * UnderhallsloggController - Rebotling underhallslogg per station
 *
 * Endpoints via ?action=underhallslogg&run=XXX:
 *   GET  run=categories          -> Lista underhallskategorier (bakat compat)
 *   GET  run=list                -> Lista underhallsposter (filtrerat days/type/category) [bakat compat]
 *   GET  run=stats               -> Sammanfattningsstatistik [bakat compat]
 *   POST run=log                 -> Logga underhall (gamla tabellen) [bakat compat]
 *   POST run=delete              -> Ta bort en post (admin-only) [bakat compat]
 *
 *   GET  run=lista               -> Lista rebotling-underhall (station, typ, period)
 *   GET  run=sammanfattning      -> KPI-kort: totalt denna manad, planerat/oplanerat ratio, snitt tid, station med mest
 *   GET  run=per-station         -> Underhall grupperat per station
 *   GET  run=manadschart         -> Planerat vs oplanerat per manad (senaste 6 man)
 *   POST run=skapa               -> Registrera nytt rebotling-underhall
 *   POST run=ta-bort             -> Ta bort rebotling-underhallspost
 */
class UnderhallsloggController {
    private $pdo;

    /** Rebotling-stationer */
    private const STATIONER = [
        1 => 'Station 1 - Avtappning',
        2 => 'Station 2 - Hoglyckspolning',
        3 => 'Station 3 - Invandlig tvatt',
        4 => 'Station 4 - Utvandlig tvatt',
        5 => 'Station 5 - Inspektion',
        6 => 'Station 6 - Montering',
        7 => 'Station 7 - Funktionstest',
        8 => 'Station 8 - Palletering',
    ];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTablesExist();
        $this->ensureRebotlingTable();
    }

    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }

        // Alla endpoints kraver inloggning
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Ej inloggad'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                // Bakatkompatiblitet (gamla tabellen)
                case 'categories': $this->getCategories(); return;
                case 'list':       $this->getList();       return;
                case 'stats':      $this->getStats();      return;

                // Nya Rebotling-specifika endpoints
                case 'lista':          $this->getLista();          return;
                case 'sammanfattning': $this->getSammanfattning(); return;
                case 'per-station':    $this->getPerStation();     return;
                case 'manadschart':    $this->getManadsChart();    return;
                case 'stationer':      $this->getStationer();      return;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Okand run-parameter'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'log':     $this->logUnderhall(); return;
                case 'delete':  $this->deleteEntry();  return;
                case 'skapa':   $this->skapa();        return;
                case 'ta-bort': $this->taBort();       return;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Okand run-parameter'], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Ogiltig metod'], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // Schema
    // =========================================================================

    private function ensureTablesExist() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `underhallslogg` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `kategori` VARCHAR(50) NOT NULL,
                `typ` ENUM('planerat','oplanerat') NOT NULL,
                `varaktighet_min` INT NOT NULL,
                `kommentar` TEXT,
                `maskin` VARCHAR(100) NOT NULL DEFAULT 'Rebotling',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_kategori` (`kategori`),
                KEY `idx_typ` (`typ`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `underhall_kategorier` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `namn` VARCHAR(50) NOT NULL,
                `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $count = $this->pdo->query("SELECT COUNT(*) FROM underhall_kategorier")->fetchColumn();
            if ((int)$count === 0) {
                $defaults = ['Mekaniskt', 'Elektriskt', 'Hydraulik', 'Pneumatik', 'Rengoring', 'Kalibrering', 'Annat'];
                $stmt = $this->pdo->prepare("INSERT INTO underhall_kategorier (namn) VALUES (?)");
                foreach ($defaults as $namn) {
                    $stmt->execute([$namn]);
                }
            }
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::ensureTablesExist: ' . $e->getMessage());
        }
    }

    private function ensureRebotlingTable() {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'rebotling_underhallslogg'"
            )->fetchColumn();
            if (!$check) {
                $sql = file_get_contents(__DIR__ . '/../migrations/2026-03-13_underhallslogg.sql');
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::ensureRebotlingTable: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function stationNamn(int $id): string {
        return self::STATIONER[$id] ?? "Station {$id}";
    }

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // GET run=stationer — lista rebotling-stationer
    // =========================================================================

    private function getStationer(): void {
        $list = [];
        foreach (self::STATIONER as $id => $namn) {
            $list[] = ['id' => $id, 'namn' => $namn];
        }
        $this->sendSuccess(['stationer' => $list]);
    }

    // =========================================================================
    // GET run=lista — lista rebotling-underhall
    // Params: station, typ, from, to, limit
    // =========================================================================

    private function getLista(): void {
        try {
            $where  = [];
            $params = [];

            $station = isset($_GET['station']) ? (int)$_GET['station'] : 0;
            if ($station > 0) {
                $where[]  = 'station_id = ?';
                $params[] = $station;
            }

            $typ = trim($_GET['typ'] ?? '');
            if (in_array($typ, ['planerat', 'oplanerat'], true)) {
                $where[]  = 'typ = ?';
                $params[] = $typ;
            }

            $from = trim($_GET['from'] ?? '');
            $to   = trim($_GET['to'] ?? '');
            // Validera att from <= to, annars byt plats
            if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from > $to) {
                [$from, $to] = [$to, $from];
            }
            // Begränsa till max 365 dagar
            if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $diffDays = (int)((strtotime($to) - strtotime($from)) / 86400);
                if ($diffDays > 365) {
                    $from = date('Y-m-d', strtotime($to . ' -365 days'));
                }
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $where[]  = 'datum >= ?';
                $params[] = $from . ' 00:00:00';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $where[]  = 'datum <= ?';
                $params[] = $to . ' 23:59:59';
            }

            $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
            $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

            $params[] = $limit;
            $stmt = $this->pdo->prepare(
                "SELECT id, station_id, typ, beskrivning, varaktighet_min, stopporsak,
                        utford_av, datum, skapad
                 FROM rebotling_underhallslogg
                 {$whereSql}
                 ORDER BY datum DESC
                 LIMIT ?"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'id'              => (int)$r['id'],
                    'station_id'      => (int)$r['station_id'],
                    'station_namn'    => $this->stationNamn((int)$r['station_id']),
                    'typ'             => $r['typ'],
                    'beskrivning'     => $r['beskrivning'],
                    'varaktighet_min' => (int)$r['varaktighet_min'],
                    'stopporsak'      => $r['stopporsak'],
                    'utford_av'       => $r['utford_av'],
                    'datum'           => $r['datum'],
                    'skapad'          => $r['skapad'],
                ];
            }

            $this->sendSuccess(['items' => $items, 'antal' => count($items)]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getLista: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta underhallslista', 500);
        }
    }

    // =========================================================================
    // GET run=sammanfattning — KPI-kort
    // =========================================================================

    private function getSammanfattning(): void {
        try {
            $monthStart = date('Y-m-01 00:00:00');

            // Totalt denna manad
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) AS totalt,
                        COALESCE(SUM(varaktighet_min), 0) AS total_tid,
                        COALESCE(SUM(CASE WHEN typ = 'planerat' THEN 1 ELSE 0 END), 0) AS planerat,
                        COALESCE(SUM(CASE WHEN typ = 'oplanerat' THEN 1 ELSE 0 END), 0) AS oplanerat
                 FROM rebotling_underhallslogg
                 WHERE datum >= ?"
            );
            $stmt->execute([$monthStart]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totalt   = (int)$row['totalt'];
            $totalTid = (int)$row['total_tid'];
            $planerat = (int)$row['planerat'];
            $oplanerat = (int)$row['oplanerat'];

            // Snitt tid per underhall
            $snittTid = $totalt > 0 ? round($totalTid / $totalt, 1) : 0;

            // Planerat vs oplanerat ratio
            $planeratPct  = $totalt > 0 ? round(($planerat / $totalt) * 100, 1) : 0;
            $oplaneratPct = $totalt > 0 ? round(($oplanerat / $totalt) * 100, 1) : 0;

            // Station med mest underhall denna manad
            $topStmt = $this->pdo->prepare(
                "SELECT station_id, COUNT(*) AS antal
                 FROM rebotling_underhallslogg
                 WHERE datum >= ?
                 GROUP BY station_id
                 ORDER BY antal DESC
                 LIMIT 1"
            );
            $topStmt->execute([$monthStart]);
            $topRow = $topStmt->fetch(\PDO::FETCH_ASSOC);

            $topStation = null;
            if ($topRow) {
                $topStation = [
                    'station_id'   => (int)$topRow['station_id'],
                    'station_namn' => $this->stationNamn((int)$topRow['station_id']),
                    'antal'        => (int)$topRow['antal'],
                ];
            }

            $this->sendSuccess([
                'totalt_denna_manad' => $totalt,
                'total_tid_min'      => $totalTid,
                'planerat_antal'     => $planerat,
                'oplanerat_antal'    => $oplanerat,
                'planerat_pct'       => $planeratPct,
                'oplanerat_pct'      => $oplaneratPct,
                'snitt_tid_min'      => $snittTid,
                'top_station'        => $topStation,
            ]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getSammanfattning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta sammanfattning', 500);
        }
    }

    // =========================================================================
    // GET run=per-station — underhall grupperat per station
    // =========================================================================

    private function getPerStation(): void {
        try {
            $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
            $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $stmt = $this->pdo->prepare(
                "SELECT station_id,
                        COUNT(*) AS antal,
                        COALESCE(SUM(varaktighet_min), 0) AS total_tid,
                        COALESCE(SUM(CASE WHEN typ = 'planerat' THEN 1 ELSE 0 END), 0) AS planerat,
                        COALESCE(SUM(CASE WHEN typ = 'oplanerat' THEN 1 ELSE 0 END), 0) AS oplanerat
                 FROM rebotling_underhallslogg
                 WHERE datum >= ?
                 GROUP BY station_id
                 ORDER BY antal DESC"
            );
            $stmt->execute([$since]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stationer = [];
            foreach ($rows as $r) {
                $stationer[] = [
                    'station_id'   => (int)$r['station_id'],
                    'station_namn' => $this->stationNamn((int)$r['station_id']),
                    'antal'        => (int)$r['antal'],
                    'total_tid'    => (int)$r['total_tid'],
                    'planerat'     => (int)$r['planerat'],
                    'oplanerat'    => (int)$r['oplanerat'],
                ];
            }

            $this->sendSuccess(['stationer' => $stationer, 'days' => $days]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getPerStation: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta per-station-data', 500);
        }
    }

    // =========================================================================
    // GET run=manadschart — planerat vs oplanerat per manad (senaste 6 man)
    // =========================================================================

    private function getManadsChart(): void {
        try {
            $months = max(1, min(12, (int)($_GET['months'] ?? 6)));
            $labels   = [];
            $planerat = [];
            $oplanerat = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $start = date('Y-m-01', strtotime("-{$i} months"));
                $end   = date('Y-m-t 23:59:59', strtotime($start));
                $label = date('Y-m', strtotime($start));

                $stmt = $this->pdo->prepare(
                    "SELECT
                        COALESCE(SUM(CASE WHEN typ = 'planerat' THEN 1 ELSE 0 END), 0) AS p,
                        COALESCE(SUM(CASE WHEN typ = 'oplanerat' THEN 1 ELSE 0 END), 0) AS o
                     FROM rebotling_underhallslogg
                     WHERE datum >= ? AND datum <= ?"
                );
                $stmt->execute([$start, $end]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                $labels[]    = $label;
                $planerat[]  = (int)$row['p'];
                $oplanerat[] = (int)$row['o'];
            }

            $this->sendSuccess([
                'labels'    => $labels,
                'planerat'  => $planerat,
                'oplanerat' => $oplanerat,
            ]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getManadsChart: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta manadsdata', 500);
        }
    }

    // =========================================================================
    // POST run=skapa — registrera nytt rebotling-underhall
    // =========================================================================

    private function skapa(): void {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                $this->sendError('Ogiltig JSON-data');
                return;
            }

            $stationId     = max(0, (int)($data['station_id'] ?? 0));
            $typ           = $data['typ'] ?? '';
            $beskrivning   = mb_substr(strip_tags(trim($data['beskrivning'] ?? '')), 0, 5000);
            $varaktighetMin = max(0, (int)($data['varaktighet_min'] ?? 0));
            $stopporsak    = mb_substr(strip_tags(trim($data['stopporsak'] ?? '')), 0, 255);
            $utfordAv      = mb_substr(strip_tags(trim($data['utford_av'] ?? '')), 0, 100);
            $datum         = trim($data['datum'] ?? '');

            // Fallback: hamta username fran session
            if (empty($utfordAv)) {
                $utfordAv = $_SESSION['username'] ?? 'Okand';
            }

            if (!in_array($typ, ['planerat', 'oplanerat'], true)) {
                $this->sendError('Typ maste vara planerat eller oplanerat');
                return;
            }

            if ($varaktighetMin <= 0) {
                $this->sendError('Varaktighet maste vara storre an 0');
                return;
            }

            // Parsear datum
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $datum)) {
                $datum = date('Y-m-d H:i:s', strtotime($datum));
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
                $datum = $datum . ' ' . date('H:i:s');
            } else {
                $datum = date('Y-m-d H:i:s');
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO rebotling_underhallslogg
                    (station_id, typ, beskrivning, varaktighet_min, stopporsak, utford_av, datum)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $stationId,
                $typ,
                $beskrivning ?: null,
                $varaktighetMin,
                $stopporsak ?: null,
                $utfordAv,
                $datum,
            ]);

            $newId = (int)$this->pdo->lastInsertId();

            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Underhall registrerat',
            ]);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::skapa: ' . $e->getMessage());
            $this->sendError('Kunde inte spara underhall', 500);
        }
    }

    // =========================================================================
    // POST run=ta-bort — ta bort rebotling-underhallspost
    // =========================================================================

    private function taBort(): void {
        try {
            // Kraver admin-roll (konsekvent med deleteEntry)
            $role = $_SESSION['role'] ?? '';
            if ($role !== 'admin') {
                $this->sendError('Atkomst nekad — kraver admin', 403);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                $this->sendError('Ogiltigt ID');
                return;
            }

            $check = $this->pdo->prepare("SELECT id FROM rebotling_underhallslogg WHERE id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) {
                $this->sendError('Post hittades inte', 404);
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM rebotling_underhallslogg WHERE id = ?");
            $stmt->execute([$id]);

            $this->sendSuccess(['message' => 'Post borttagen']);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::taBort: ' . $e->getMessage());
            $this->sendError('Kunde inte ta bort post', 500);
        }
    }

    // =========================================================================
    // Legacy endpoints (backward compatibility)
    // =========================================================================

    private function getCategories() {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, namn FROM underhall_kategorier WHERE aktiv = 1 ORDER BY id"
            );
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getCategories: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta kategorier'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function logUnderhall() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $kategori       = mb_substr(strip_tags(trim($data['kategori'] ?? '')), 0, 50);
            $typ            = $data['typ'] ?? '';
            $varaktighetMin = intval($data['varaktighet_min'] ?? 0);
            $kommentar      = mb_substr(strip_tags(trim($data['kommentar'] ?? '')), 0, 2000);
            $maskin         = mb_substr(strip_tags(trim($data['maskin'] ?? 'Rebotling')), 0, 100);

            if (empty($kategori)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Kategori saknas'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!in_array($typ, ['planerat', 'oplanerat'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig typ'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($varaktighetMin <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Varaktighet maste vara storre an 0'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $userId = intval($_SESSION['user_id']);

            $stmt = $this->pdo->prepare(
                "INSERT INTO underhallslogg (user_id, kategori, typ, varaktighet_min, kommentar, maskin)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $kategori, $typ, $varaktighetMin, $kommentar ?: null, $maskin ?: 'Rebotling']);
            $newId = (int)$this->pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Underhall loggat',
                'id'      => $newId,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::logUnderhall: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte logga underhall'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getList() {
        try {
            $days     = max(1, min(365, intval($_GET['days'] ?? 30)));
            $typ      = $_GET['type'] ?? 'all';
            $kategori = trim($_GET['category'] ?? 'all');

            $where = ["u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
            $params = [$days];

            if ($typ !== 'all' && in_array($typ, ['planerat', 'oplanerat'], true)) {
                $where[] = "u.typ = ?";
                $params[] = $typ;
            }

            if ($kategori !== 'all' && !empty($kategori)) {
                $where[] = "u.kategori = ?";
                $params[] = $kategori;
            }

            $whereClause = implode(' AND ', $where);

            $sql = "SELECT u.id, u.user_id, u.kategori, u.typ, u.varaktighet_min,
                           u.kommentar, u.maskin, u.created_at,
                           usr.username AS operator_namn
                    FROM underhallslogg u
                    LEFT JOIN users usr ON u.user_id = usr.id
                    WHERE {$whereClause}
                    ORDER BY u.created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getList: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta underhallslogg'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getStats() {
        try {
            $days = max(1, min(365, intval($_GET['days'] ?? 30)));

            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) AS totalt_antal,
                        COALESCE(SUM(varaktighet_min), 0) AS total_tid_min,
                        COALESCE(SUM(CASE WHEN typ = 'planerat' THEN 1 ELSE 0 END), 0) AS planerat_antal,
                        COALESCE(SUM(CASE WHEN typ = 'oplanerat' THEN 1 ELSE 0 END), 0) AS oplanerat_antal
                 FROM underhallslogg
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $stmt->execute([$days]);
            $totals = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totaltAntal    = (int)$totals['totalt_antal'];
            $totalTidMin    = (int)$totals['total_tid_min'];
            $planeratAntal  = (int)$totals['planerat_antal'];
            $oplaneratAntal = (int)$totals['oplanerat_antal'];

            $veckor = max(1, round($days / 7));
            $snittPerVecka = $totaltAntal > 0 ? round($totaltAntal / $veckor, 1) : 0;

            $planeratPct  = $totaltAntal > 0 ? round(($planeratAntal / $totaltAntal) * 100, 1) : 0;
            $oplaneratPct = $totaltAntal > 0 ? round(($oplaneratAntal / $totaltAntal) * 100, 1) : 0;

            $stmtKat = $this->pdo->prepare(
                "SELECT kategori, COUNT(*) AS antal, SUM(varaktighet_min) AS total_min
                 FROM underhallslogg
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY kategori
                 ORDER BY antal DESC
                 LIMIT 7"
            );
            $stmtKat->execute([$days]);
            $topKategorier = $stmtKat->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'totalt_antal'    => $totaltAntal,
                    'total_tid_min'   => $totalTidMin,
                    'snitt_per_vecka' => $snittPerVecka,
                    'planerat_antal'  => $planeratAntal,
                    'oplanerat_antal' => $oplaneratAntal,
                    'planerat_pct'    => $planeratPct,
                    'oplanerat_pct'   => $oplaneratPct,
                    'top_kategorier'  => $topKategorier,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::getStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hamta statistik'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function deleteEntry() {
        try {
            $role = $_SESSION['role'] ?? '';
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Atkomst nekad — kraver admin'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig JSON-data'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $id = intval($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltigt ID'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $check = $this->pdo->prepare("SELECT id FROM underhallslogg WHERE id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Post hittades inte'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = $this->pdo->prepare("DELETE FROM underhallslogg WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Post borttagen'], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('UnderhallsloggController::deleteEntry: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte ta bort post'], JSON_UNESCAPED_UNICODE);
        }
    }
}
