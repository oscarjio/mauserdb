<?php

/**
 * SkiftoverlamningController
 * Skiftöverlämningslogg — strukturerad digital överlämning mellan skift.
 *
 * Endpoints via ?action=skiftoverlamning&run=XXX:
 *
 *   GET  run=list              — Lista överlämningar (filtrerad per skift_typ, operator_id, from, to)
 *   GET  run=detail&id=N       — Fullständig vy av en överlämning
 *   GET  run=shift-kpis        — Auto-hämta KPI:er för aktuellt/senaste skift
 *   GET  run=summary           — Sammanfattnings-KPI:er (senaste överlämning, antal vecka, snitt, pågående problem)
 *   GET  run=operators         — Lista operatörer (för filter-dropdown)
 *   GET  run=aktuellt-skift    — Info om pågående skift: operatör, starttid, antal IBC, OEE, kasserade
 *   GET  run=skift-sammanfattning — Sammanfattning av förra skiftet: KPIer, händelser, avvikelser
 *   GET  run=oppna-problem     — Lista öppna/pågående problem som behöver överlämnas
 *   GET  run=checklista        — Hämta standard-checklistepunkter
 *   GET  run=historik          — Lista senaste 10 överlämningar med tidsstämpel och innehåll
 *   POST run=create            — Skapa ny överlämning
 *   POST run=skapa-overlamning — POST: spara en överlämning med fritext, checklista, mål
 */
class SkiftoverlamningController {
    private $pdo;

    /** Standard-checklista som används vid nya överlämningar */
    private const DEFAULT_CHECKLISTA = [
        ['key' => 'stationer_kontrollerade', 'label' => 'Alla stationer kontrollerade',     'checked' => false],
        ['key' => 'inga_stopp',              'label' => 'Inga pågående stopp',               'checked' => false],
        ['key' => 'verktyg_inventerat',      'label' => 'Verktyg/material inventerat',        'checked' => false],
        ['key' => 'avvikelser_noterade',     'label' => 'Avvikelser noterade',                'checked' => false],
        ['key' => 'mal_satta',               'label' => 'Mål för nästa skift satta',          'checked' => false],
        ['key' => 'rengoring_utford',        'label' => 'Rengöring utförd',                   'checked' => false],
        ['key' => 'sakerhet_ok',             'label' => 'Säkerhetskontroll genomförd',         'checked' => false],
    ];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTable();
        $this->ensureNewColumns();
        $this->ensureProtokollTable();
    }

    public function handle(): void {
        $this->requireLogin();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'list':                $this->getList();              break;
                case 'detail':              $this->getDetail();            break;
                case 'shift-kpis':          $this->getShiftKpis();         break;
                case 'summary':             $this->getSummaryKpis();       break;
                case 'operators':           $this->getOperators();         break;
                case 'aktuellt-skift':      $this->getAktuelltSkift();     break;
                case 'skift-sammanfattning':$this->getSkiftSammanfattning(); break;
                case 'oppna-problem':       $this->getOppnaProblem();      break;
                case 'checklista':          $this->getChecklista();        break;
                case 'historik':            $this->getHistorik();          break;
                case 'skiftdata':           $this->getSkiftdata();         break;
                case 'protokoll-historik':   $this->getProtokollHistorik(); break;
                case 'protokoll-detalj':    $this->getProtokollDetalj();   break;
                default:
                    $this->sendError('Okänd run-parameter', 404);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'create':
                case 'skapa-overlamning':
                    $this->requireLogin();
                    $this->createHandover();
                    break;
                case 'spara':
                    $this->requireLogin();
                    $this->sparaProtokoll();
                    break;
                default:
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
            session_start(['read_and_close' => true]);
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
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
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

    private function ensureNewColumns(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'skiftoverlamning_logg'
                   AND column_name = 'checklista_json'"
            )->fetchColumn();
            if (!$check) {
                $sql = file_get_contents(__DIR__ . '/../migrations/2026-03-13_skiftoverlamning_checklista.sql');
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController ensureNewColumns: ' . $e->getMessage());
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

    /**
     * Beräkna skiftets start- och sluttid baserat på skifttyp och datum.
     */
    private function skiftTider(string $typ, string $datum = null): array {
        $datum = $datum ?: date('Y-m-d');
        switch ($typ) {
            case 'dag':
                return ['start' => $datum . ' 06:00:00', 'slut' => $datum . ' 14:00:00'];
            case 'kvall':
                return ['start' => $datum . ' 14:00:00', 'slut' => $datum . ' 22:00:00'];
            case 'natt':
                $nextDay = date('Y-m-d', strtotime($datum . ' +1 day'));
                return ['start' => $datum . ' 22:00:00', 'slut' => $nextDay . ' 06:00:00'];
            default:
                return ['start' => $datum . ' 06:00:00', 'slut' => $datum . ' 14:00:00'];
        }
    }

    /**
     * Beräkna drifttid i sekunder från rebotling_onoff (datum + running kolumner).
     * rebotling_onoff har en rad per statusändring med datum (DATETIME) och running (BOOLEAN).
     * Itererar över raderna och summerar tid mellan running=1 och running=0.
     */
    private function calcDrifttidSek(string $from, string $to): int {
        $stmt = $this->pdo->prepare("
            SELECT datum, running
            FROM rebotling_onoff
            WHERE datum BETWEEN :from_dt AND :to_dt
            ORDER BY datum ASC
        ");
        $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $drifttidSek = 0;
        $lastOnTime = null;
        foreach ($rows as $row) {
            $ts = strtotime($row['datum']);
            if ((int)$row['running'] === 1) {
                if ($lastOnTime === null) {
                    $lastOnTime = $ts;
                }
            } else {
                if ($lastOnTime !== null) {
                    $drifttidSek += max(0, $ts - $lastOnTime);
                    $lastOnTime = null;
                }
            }
        }
        // Om linjen fortfarande kör vid periodens slut
        if ($lastOnTime !== null) {
            $endTs = min(time(), strtotime($to));
            $drifttidSek += max(0, $endTs - $lastOnTime);
        }
        return $drifttidSek;
    }

    // =========================================================================
    // GET run=aktuellt-skift
    // Info om pågående skift: operatör, starttid, antal IBC, OEE, kasserade
    // =========================================================================

    private function getAktuelltSkift(): void {
        try {
            $skiftTyp = $this->detectSkiftTyp();
            $tider = $this->skiftTider($skiftTyp);

            // Hämta IBC-data för aktuellt skift via kumulativa PLC-fält
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(shift_ok), 0) AS ok_antal,
                    COALESCE(SUM(shift_ej_ok), 0) AS kasserade
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum BETWEEN :from_dt AND :to_dt
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $stmt->execute([':from_dt' => $tider['start'], ':to_dt' => $tider['slut']]);
            $ibc = $stmt->fetch(\PDO::FETCH_ASSOC);

            $okAntal   = (int)($ibc['ok_antal'] ?? 0);
            $kasserade = (int)($ibc['kasserade'] ?? 0);
            $total     = $okAntal + $kasserade;

            // Beräkna tid som gått sedan skift-start
            $startTs = strtotime($tider['start']);
            $nu = time();
            $tidGattMin = max(0, ($nu - $startTs) / 60);
            $tidKvarMin = max(0, (strtotime($tider['slut']) - $nu) / 60);
            $ibcPerH = $tidGattMin > 0 ? round($okAntal / ($tidGattMin / 60), 1) : 0.0;

            // Drifttid från rebotling_onoff (datum + running kolumner)
            $drifttidSek = 0;
            try {
                $drifttidSek = $this->calcDrifttidSek($tider['start'], $tider['slut']);
            } catch (\PDOException $e) {
                error_log('getAktuelltSkift drifttid: ' . $e->getMessage());
            }

            // OEE-beräkning
            $planeradSek = 8 * 3600;
            $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0;
            $prestanda = $drifttidSek > 0 ? min(1.0, ($total * 120) / $drifttidSek) : 0;
            $kvalitet = $total > 0 ? ($okAntal / $total) : 0;
            $oee = $tillganglighet * $prestanda * $kvalitet;

            // Kolla om linjen körs just nu
            $aktivNu = false;
            try {
                $aStmt = $this->pdo->query("SELECT running FROM rebotling_onoff ORDER BY datum DESC LIMIT 1");
                $row = $aStmt->fetch(\PDO::FETCH_ASSOC);
                $aktivNu = $row ? (bool)$row['running'] : false;
            } catch (\PDOException) {}

            $this->sendSuccess([
                'skift_typ'       => $skiftTyp,
                'skift_typ_label' => $this->skiftTypLabel($skiftTyp),
                'skift_start'     => $tider['start'],
                'skift_slut'      => $tider['slut'],
                'tid_gatt_min'    => round($tidGattMin),
                'tid_kvar_min'    => round($tidKvarMin),
                'ibc_totalt'      => $total,
                'ibc_ok'          => $okAntal,
                'kasserade'       => $kasserade,
                'ibc_per_h'       => $ibcPerH,
                'oee_pct'         => round($oee * 100, 1),
                'drifttid_min'    => round($drifttidSek / 60),
                'aktiv_nu'        => $aktivNu,
                'operator'        => $this->currentUsername(),
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getAktuelltSkift: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta aktuellt skift', 500);
        }
    }

    // =========================================================================
    // GET run=skift-sammanfattning
    // Sammanfattning av förra skiftet: KPIer, händelser, avvikelser
    // =========================================================================

    private function getSkiftSammanfattning(): void {
        try {
            // Förra skiftet
            $nuTyp = $this->detectSkiftTyp();
            $idag = date('Y-m-d');
            switch ($nuTyp) {
                case 'dag':
                    $forraTyp = 'natt';
                    $forraDatum = date('Y-m-d', strtotime('-1 day'));
                    break;
                case 'kvall':
                    $forraTyp = 'dag';
                    $forraDatum = $idag;
                    break;
                case 'natt':
                    $forraTyp = 'kvall';
                    $forraDatum = $idag;
                    break;
                default:
                    $forraTyp = 'dag';
                    $forraDatum = $idag;
            }

            $tider = $this->skiftTider($forraTyp, $forraDatum);

            // IBC-data via kumulativa PLC-fält
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok), 0) AS ok_antal,
                    COALESCE(SUM(shift_ej_ok), 0) AS kasserade
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum BETWEEN :from_dt AND :to_dt
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $stmt->execute([':from_dt' => $tider['start'], ':to_dt' => $tider['slut']]);
            $ibc = $stmt->fetch(\PDO::FETCH_ASSOC);

            $okAntal   = (int)($ibc['ok_antal'] ?? 0);
            $kasserade = (int)($ibc['kasserade'] ?? 0);
            $total     = $okAntal + $kasserade;
            $ibcPerH   = round($okAntal / 8, 1); // 8h skift

            // Drifttid
            $drifttidSek = 0;
            try {
                $drifttidSek = $this->calcDrifttidSek($tider['start'], $tider['slut']);
            } catch (\PDOException $e) {
                error_log('getSkiftSammanfattning drifttid: ' . $e->getMessage());
            }

            // OEE
            $planeradSek = 8 * 3600;
            $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0;
            $prestanda = $drifttidSek > 0 ? min(1.0, ($total * 120) / $drifttidSek) : 0;
            $kvalitet = $total > 0 ? ($okAntal / $total) : 0;
            $oee = $tillganglighet * $prestanda * $kvalitet;

            $kassationsgrad = $total > 0 ? round(($kasserade / $total) * 100, 1) : 0;
            $drifttidH = round($drifttidSek / 3600, 1);
            $drifttidPct = round(($drifttidSek / $planeradSek) * 100, 1);

            // Hämta ev. överlämning som gjordes för detta skift
            $overlamning = null;
            $olStmt = $this->pdo->prepare("
                SELECT id, operator_namn, problem_text, pagaende_arbete, instruktioner, kommentar,
                       har_pagaende_problem, checklista_json, mal_nasta_skift, skapad
                FROM skiftoverlamning_logg
                WHERE skift_typ = :typ AND datum = :datum
                ORDER BY skapad DESC LIMIT 1
            ");
            $olStmt->execute([':typ' => $forraTyp, ':datum' => $forraDatum]);
            $olRow = $olStmt->fetch(\PDO::FETCH_ASSOC);
            if ($olRow) {
                $overlamning = [
                    'id'                   => (int)$olRow['id'],
                    'operator_namn'        => $olRow['operator_namn'],
                    'problem_text'         => $olRow['problem_text'],
                    'pagaende_arbete'      => $olRow['pagaende_arbete'],
                    'instruktioner'        => $olRow['instruktioner'],
                    'kommentar'            => $olRow['kommentar'],
                    'har_pagaende_problem' => (bool)$olRow['har_pagaende_problem'],
                    'mal_nasta_skift'      => $olRow['mal_nasta_skift'],
                    'skapad'               => $olRow['skapad'],
                ];
            }

            // Mål (statiska målvärden)
            $mal = [
                'oee_mal' => 75.0,
                'ibc_mal' => 200,
                'kassation_mal' => 3.0,
                'drifttid_mal' => 90.0,
            ];

            $this->sendSuccess([
                'forra_skift_typ'       => $forraTyp,
                'forra_skift_typ_label' => $this->skiftTypLabel($forraTyp),
                'forra_datum'           => $forraDatum,
                'tider'                 => $tider,
                'ibc_totalt'            => $total,
                'ibc_ok'                => $okAntal,
                'kasserade'             => $kasserade,
                'kassationsgrad_pct'    => $kassationsgrad,
                'ibc_per_h'             => $ibcPerH,
                'oee_pct'               => round($oee * 100, 1),
                'drifttid_h'            => $drifttidH,
                'drifttid_pct'          => $drifttidPct,
                'mal'                   => $mal,
                'overlamning'           => $overlamning,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getSkiftSammanfattning: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skift-sammanfattning', 500);
        }
    }

    // =========================================================================
    // GET run=oppna-problem
    // Lista öppna/pågående problem som behöver överlämnas
    // =========================================================================

    private function getOppnaProblem(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, datum, skift_typ, operator_namn, problem_text, pagaende_arbete,
                       instruktioner, allvarlighetsgrad, skapad
                FROM skiftoverlamning_logg
                WHERE har_pagaende_problem = 1
                ORDER BY
                    FIELD(allvarlighetsgrad, 'kritisk', 'hog', 'medel', 'lag') ASC,
                    skapad DESC
                LIMIT 20
            ");
            $items = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'id'                => (int)$r['id'],
                    'datum'             => $r['datum'],
                    'skift_typ'         => $r['skift_typ'],
                    'skift_typ_label'   => $this->skiftTypLabel($r['skift_typ']),
                    'operator_namn'     => $r['operator_namn'],
                    'problem_text'      => $r['problem_text'],
                    'pagaende_arbete'   => $r['pagaende_arbete'],
                    'instruktioner'     => $r['instruktioner'],
                    'allvarlighetsgrad' => $r['allvarlighetsgrad'] ?? 'medel',
                    'skapad'            => $r['skapad'],
                ];
            }

            $this->sendSuccess(['problem' => $items, 'antal' => count($items)]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getOppnaProblem: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta öppna problem', 500);
        }
    }

    // =========================================================================
    // GET run=checklista
    // Hämta standard-checklistepunkter
    // =========================================================================

    private function getChecklista(): void {
        $this->sendSuccess(['checklista' => self::DEFAULT_CHECKLISTA]);
    }

    // =========================================================================
    // GET run=historik
    // Lista senaste 10 överlämningar med tidsstämpel och innehåll
    // =========================================================================

    private function getHistorik(): void {
        try {
            $limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));

            $stmt = $this->pdo->prepare("
                SELECT l.id, l.operator_id, l.operator_namn, l.skift_typ, l.datum,
                       l.ibc_totalt, l.ibc_per_h, l.stopptid_min, l.kassationer,
                       l.problem_text, l.pagaende_arbete, l.instruktioner, l.kommentar,
                       l.har_pagaende_problem, l.checklista_json, l.mal_nasta_skift,
                       l.allvarlighetsgrad, l.skapad,
                       COALESCE(u.username, l.operator_namn) AS operatör
                FROM skiftoverlamning_logg l
                LEFT JOIN users u ON l.operator_id = u.id
                ORDER BY l.skapad DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $r) {
                $checklista = null;
                if (!empty($r['checklista_json'])) {
                    $checklista = json_decode($r['checklista_json'], true);
                }

                $items[] = [
                    'id'                   => (int)$r['id'],
                    'operator_id'          => (int)$r['operator_id'],
                    'operator_namn'        => $r['operatör'] ?? $r['operator_namn'],
                    'skift_typ'            => $r['skift_typ'],
                    'skift_typ_label'      => $this->skiftTypLabel($r['skift_typ']),
                    'datum'                => $r['datum'],
                    'ibc_totalt'           => (int)$r['ibc_totalt'],
                    'ibc_per_h'            => (float)$r['ibc_per_h'],
                    'stopptid_min'         => (int)$r['stopptid_min'],
                    'kassationer'          => (int)$r['kassationer'],
                    'problem_text'         => $r['problem_text'],
                    'pagaende_arbete'      => $r['pagaende_arbete'],
                    'instruktioner'        => $r['instruktioner'],
                    'kommentar'            => $r['kommentar'],
                    'har_pagaende_problem' => (bool)$r['har_pagaende_problem'],
                    'checklista'           => $checklista,
                    'mal_nasta_skift'      => $r['mal_nasta_skift'],
                    'allvarlighetsgrad'    => $r['allvarlighetsgrad'] ?? 'medel',
                    'skapad'               => $r['skapad'],
                ];
            }

            $this->sendSuccess(['items' => $items]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getHistorik: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta historik', 500);
        }
    }

    // =========================================================================
    // GET run=list
    // Params: skift_typ, operator_id, from, to, limit, offset
    // =========================================================================

    private function getList(): void {
        try {
            $where  = [];
            $params = [];

            $skiftTyp = trim($_GET['skift_typ'] ?? '');
            if (in_array($skiftTyp, ['dag', 'kvall', 'natt'], true)) {
                $where[]  = 'l.skift_typ = ?';
                $params[] = $skiftTyp;
            }

            $opId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;
            if ($opId > 0) {
                $where[]  = 'l.operator_id = ?';
                $params[] = $opId;
            }

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

            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM skiftoverlamning_logg l {$whereSql}"
            );
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                "SELECT l.id, l.operator_id, l.operator_namn, l.skift_typ, l.datum,
                        l.ibc_totalt, l.ibc_per_h, l.stopptid_min, l.kassationer,
                        l.problem_text, l.pagaende_arbete, l.instruktioner, l.kommentar,
                        l.har_pagaende_problem, l.checklista_json, l.mal_nasta_skift,
                        l.allvarlighetsgrad, l.skapad,
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
                $checklista = null;
                if (!empty($r['checklista_json'])) {
                    $checklista = json_decode($r['checklista_json'], true);
                }

                $items[] = [
                    'id'                   => (int)$r['id'],
                    'operator_id'          => (int)$r['operator_id'],
                    'operator_namn'        => $r['operatör'] ?? $r['operator_namn'],
                    'skift_typ'            => $r['skift_typ'],
                    'skift_typ_label'      => $this->skiftTypLabel($r['skift_typ']),
                    'datum'                => $r['datum'],
                    'ibc_totalt'           => (int)$r['ibc_totalt'],
                    'ibc_per_h'            => (float)$r['ibc_per_h'],
                    'stopptid_min'         => (int)$r['stopptid_min'],
                    'kassationer'          => (int)$r['kassationer'],
                    'problem_text'         => $r['problem_text'],
                    'pagaende_arbete'      => $r['pagaende_arbete'],
                    'instruktioner'        => $r['instruktioner'],
                    'kommentar'            => $r['kommentar'],
                    'har_pagaende_problem' => (bool)$r['har_pagaende_problem'],
                    'checklista'           => $checklista,
                    'mal_nasta_skift'      => $r['mal_nasta_skift'],
                    'allvarlighetsgrad'    => $r['allvarlighetsgrad'] ?? 'medel',
                    'skapad'               => $r['skapad'],
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

            $checklista = null;
            if (!empty($r['checklista_json'])) {
                $checklista = json_decode($r['checklista_json'], true);
            }

            $this->sendSuccess([
                'item' => [
                    'id'                   => (int)$r['id'],
                    'operator_id'          => (int)$r['operator_id'],
                    'operator_namn'        => $r['operatör'] ?? $r['operator_namn'],
                    'skift_typ'            => $r['skift_typ'],
                    'skift_typ_label'      => $this->skiftTypLabel($r['skift_typ']),
                    'datum'                => $r['datum'],
                    'ibc_totalt'           => (int)$r['ibc_totalt'],
                    'ibc_per_h'            => (float)$r['ibc_per_h'],
                    'stopptid_min'         => (int)$r['stopptid_min'],
                    'kassationer'          => (int)$r['kassationer'],
                    'problem_text'         => $r['problem_text'],
                    'pagaende_arbete'      => $r['pagaende_arbete'],
                    'instruktioner'        => $r['instruktioner'],
                    'kommentar'            => $r['kommentar'],
                    'har_pagaende_problem' => (bool)$r['har_pagaende_problem'],
                    'checklista'           => $checklista,
                    'mal_nasta_skift'      => $r['mal_nasta_skift'],
                    'allvarlighetsgrad'    => $r['allvarlighetsgrad'] ?? 'medel',
                    'skapad'               => $r['skapad'],
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta överlämning', 500);
        }
    }

    // =========================================================================
    // GET run=shift-kpis
    // =========================================================================

    private function getShiftKpis(): void {
        try {
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
    // =========================================================================

    private function getSummaryKpis(): void {
        try {
            $lastStmt = $this->pdo->query(
                "SELECT id, skapad, operator_namn, skift_typ, datum
                 FROM skiftoverlamning_logg
                 ORDER BY skapad DESC LIMIT 1"
            );
            $last = $lastStmt->fetch(\PDO::FETCH_ASSOC);

            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekStmt  = $this->pdo->prepare(
                "SELECT COUNT(*) FROM skiftoverlamning_logg WHERE datum >= ?"
            );
            $weekStmt->execute([$weekStart]);
            $weekCount = (int)$weekStmt->fetchColumn();

            $avgStmt = $this->pdo->query(
                "SELECT AVG(ibc_totalt) AS snitt
                 FROM (SELECT ibc_totalt FROM skiftoverlamning_logg ORDER BY skapad DESC LIMIT 10) sub"
            );
            $avgRow = $avgStmt->fetch(\PDO::FETCH_ASSOC);
            $avgProduction = $avgRow && $avgRow['snitt'] !== null ? round((float)$avgRow['snitt'], 1) : 0;

            $probStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM skiftoverlamning_logg WHERE har_pagaende_problem = 1"
            );
            $activeProblems = (int)$probStmt->fetchColumn();

            $probDetailStmt = $this->pdo->query(
                "SELECT id, datum, skift_typ, operator_namn, problem_text, pagaende_arbete, allvarlighetsgrad
                 FROM skiftoverlamning_logg
                 WHERE har_pagaende_problem = 1
                 ORDER BY FIELD(allvarlighetsgrad, 'kritisk', 'hog', 'medel', 'lag') ASC, skapad DESC
                 LIMIT 5"
            );
            $activeItems = [];
            foreach ($probDetailStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $activeItems[] = [
                    'id'                => (int)$r['id'],
                    'datum'             => $r['datum'],
                    'skift_typ'         => $r['skift_typ'],
                    'skift_typ_label'   => $this->skiftTypLabel($r['skift_typ']),
                    'operator_namn'     => $r['operator_namn'],
                    'problem_text'      => $r['problem_text'],
                    'pagaende_arbete'   => $r['pagaende_arbete'],
                    'allvarlighetsgrad' => $r['allvarlighetsgrad'] ?? 'medel',
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
    // POST run=create / run=skapa-overlamning
    // Body: { skift_typ, datum, ibc_totalt, ibc_per_h, stopptid_min, kassationer,
    //         problem_text, pagaende_arbete, instruktioner, kommentar,
    //         har_pagaende_problem, allvarlighetsgrad,
    //         checklista_json, mal_nasta_skift }
    // =========================================================================

    private function createHandover(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId   = $this->currentUserId();
        $username = $this->currentUsername();

        if (!$username && $userId) {
            try {
                $uStmt = $this->pdo->prepare('SELECT username FROM users WHERE id = ?');
                $uStmt->execute([$userId]);
                $uRow = $uStmt->fetch(\PDO::FETCH_ASSOC);
                $username = $uRow['username'] ?? null;
            } catch (\PDOException) {}
        }

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
        $malNastaSkift  = isset($data['mal_nasta_skift']) ? strip_tags(trim($data['mal_nasta_skift'])) : null;

        $harPagaende = !empty($data['har_pagaende_problem']) ? 1 : 0;

        $allvarlighetsgrad = $data['allvarlighetsgrad'] ?? 'medel';
        if (!in_array($allvarlighetsgrad, ['lag', 'medel', 'hog', 'kritisk'], true)) {
            $allvarlighetsgrad = 'medel';
        }

        // Checklista JSON
        $checklistaJson = null;
        if (isset($data['checklista']) && is_array($data['checklista'])) {
            $checklistaJson = json_encode($data['checklista'], JSON_UNESCAPED_UNICODE);
        }

        // Begränsa textlängder
        foreach (['problemText', 'pagaendeArbete', 'instruktioner', 'kommentar', 'malNastaSkift'] as $var) {
            if ($$var && mb_strlen($$var) > 5000) $$var = mb_substr($$var, 0, 5000);
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO skiftoverlamning_logg
                    (operator_id, operator_namn, skift_typ, datum,
                     ibc_totalt, ibc_per_h, stopptid_min, kassationer,
                     problem_text, pagaende_arbete, instruktioner, kommentar,
                     checklista_json, mal_nasta_skift,
                     har_pagaende_problem, allvarlighetsgrad, skapad)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
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
                $checklistaJson,
                $malNastaSkift ?: null,
                $harPagaende,
                $allvarlighetsgrad,
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
            case 'dag':   return 'Dag (06-14)';
            case 'kvall': return 'Kvall (14-22)';
            case 'natt':  return 'Natt (22-06)';
            default:      return $typ;
        }
    }

    // =========================================================================
    // Rebotling skiftoverlamningsprotokoll (ny tabell: rebotling_skiftoverlamning)
    // =========================================================================

    private function ensureProtokollTable(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'rebotling_skiftoverlamning'"
            )->fetchColumn();
            if (!$check) {
                $sql = file_get_contents(__DIR__ . '/../migrations/2026-03-13_skiftoverlamning.sql');
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController ensureProtokollTable: ' . $e->getMessage());
        }
    }

    /**
     * GET run=skiftdata
     * Auto-hamta produktionsdata for aktuellt skift fran rebotling_ibc och rebotling_onoff
     */
    private function getSkiftdata(): void {
        try {
            $skiftTyp = $this->detectSkiftTyp();
            $tider = $this->skiftTider($skiftTyp);

            // IBC-data for aktuellt skift via kumulativa PLC-fält
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok), 0) AS ok_antal,
                    COALESCE(SUM(shift_ej_ok), 0) AS kasserade
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum BETWEEN :from_dt AND :to_dt
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ");
            $stmt->execute([':from_dt' => $tider['start'], ':to_dt' => $tider['slut']]);
            $ibc = $stmt->fetch(\PDO::FETCH_ASSOC);

            $okAntal   = (int)($ibc['ok_antal'] ?? 0);
            $kasserade = (int)($ibc['kasserade'] ?? 0);
            $total     = $okAntal + $kasserade;
            $kassationPct = $total > 0 ? round(($kasserade / $total) * 100, 2) : 0;

            // Drifttid fran rebotling_onoff (datum + running kolumner)
            $drifttidSek = 0;
            try {
                $drifttidSek = $this->calcDrifttidSek($tider['start'], $tider['slut']);
            } catch (\PDOException $e) {
                error_log('getSkiftdata drifttid: ' . $e->getMessage());
            }

            // OEE-berakning
            $planeradSek = 8 * 3600;
            $tillganglighet = $planeradSek > 0 ? ($drifttidSek / $planeradSek) : 0;
            $prestanda = $drifttidSek > 0 ? min(1.0, ($total * 120) / $drifttidSek) : 0;
            $kvalitet = $total > 0 ? ($okAntal / $total) : 0;
            $oee = round($tillganglighet * $prestanda * $kvalitet * 100, 2);

            // Rakning av stopp (perioder dar linjen var av)
            $stoppAntal = 0;
            $stoppMinuter = 0;
            try {
                // Räkna stopp-perioder från running-data (running=0 rader)
                $sStmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS antal
                    FROM rebotling_onoff
                    WHERE datum BETWEEN :from3 AND :to5
                      AND running = 0
                ");
                $sStmt->execute([
                    ':from3' => $tider['start'],
                    ':to5' => $tider['slut'],
                ]);
                $stoppRow = $sStmt->fetch(\PDO::FETCH_ASSOC);
                $stoppAntal = (int)($stoppRow['antal'] ?? 0);
                // Stopptid = planerad tid minus drifttid
                $stoppMinuter = max(0, round(($planeradSek - $drifttidSek) / 60));
            } catch (\PDOException) {
                // Enkel fallback: total schema minus drifttid
                $stoppMinuter = max(0, round((($planeradSek - $drifttidSek) / 60)));
            }

            $this->sendSuccess([
                'skift_datum'      => date('Y-m-d'),
                'skift_typ'        => $skiftTyp,
                'skift_typ_label'  => $this->skiftTypLabel($skiftTyp),
                'skift_start'      => $tider['start'],
                'skift_slut'       => $tider['slut'],
                'produktion_antal' => $total,
                'oee_procent'      => $oee,
                'stopp_antal'      => $stoppAntal,
                'stopp_minuter'    => $stoppMinuter,
                'kassation_procent' => $kassationPct,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getSkiftdata: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta skiftdata', 500);
        }
    }

    /**
     * POST run=spara
     * Spara nytt skiftoverlamningsprotokoll i rebotling_skiftoverlamning
     */
    private function sparaProtokoll(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId = $this->currentUserId();

        $skiftDatum = $data['skift_datum'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $skiftDatum)) {
            $skiftDatum = date('Y-m-d');
        }

        $skiftTyp = $data['skift_typ'] ?? '';
        if (!in_array($skiftTyp, ['dag', 'kvall', 'natt'], true)) {
            $skiftTyp = $this->detectSkiftTyp();
        }

        $produktionAntal  = max(0, (int)($data['produktion_antal'] ?? 0));
        $oeeProcent       = max(0, min(100, round((float)($data['oee_procent'] ?? 0), 2)));
        $stoppAntal       = max(0, (int)($data['stopp_antal'] ?? 0));
        $stoppMinuter     = max(0, (int)($data['stopp_minuter'] ?? 0));
        $kassationProcent = max(0, min(100, round((float)($data['kassation_procent'] ?? 0), 2)));

        $checkRengoring   = !empty($data['checklista_rengoring']) ? 1 : 0;
        $checkVerktyg     = !empty($data['checklista_verktyg']) ? 1 : 0;
        $checkKemikalier  = !empty($data['checklista_kemikalier']) ? 1 : 0;
        $checkAvvikelser  = !empty($data['checklista_avvikelser']) ? 1 : 0;
        $checkSakerhet    = !empty($data['checklista_sakerhet']) ? 1 : 0;
        $checkMaterial    = !empty($data['checklista_material']) ? 1 : 0;

        $kommentarHande   = isset($data['kommentar_hande'])   ? strip_tags(trim(mb_substr($data['kommentar_hande'], 0, 5000)))   : null;
        $kommentarAtgarda = isset($data['kommentar_atgarda']) ? strip_tags(trim(mb_substr($data['kommentar_atgarda'], 0, 5000))) : null;
        $kommentarOvrigt  = isset($data['kommentar_ovrigt'])  ? strip_tags(trim(mb_substr($data['kommentar_ovrigt'], 0, 5000)))  : null;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rebotling_skiftoverlamning
                    (skift_datum, skift_typ, operator_id,
                     produktion_antal, oee_procent, stopp_antal, stopp_minuter, kassation_procent,
                     checklista_rengoring, checklista_verktyg, checklista_kemikalier,
                     checklista_avvikelser, checklista_sakerhet, checklista_material,
                     kommentar_hande, kommentar_atgarda, kommentar_ovrigt, skapad)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $skiftDatum, $skiftTyp, $userId,
                $produktionAntal, $oeeProcent, $stoppAntal, $stoppMinuter, $kassationProcent,
                $checkRengoring, $checkVerktyg, $checkKemikalier,
                $checkAvvikelser, $checkSakerhet, $checkMaterial,
                $kommentarHande ?: null, $kommentarAtgarda ?: null, $kommentarOvrigt ?: null,
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Skiftoverlamningsprotokoll sparat',
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController sparaProtokoll: ' . $e->getMessage());
            $this->sendError('Kunde inte spara protokoll', 500);
        }
    }

    /**
     * GET run=protokoll-historik
     * Lista senaste 10 skiftoverlamningsprotokoll fran rebotling_skiftoverlamning
     */
    private function getProtokollHistorik(): void {
        try {
            $limit = max(1, min(20, (int)($_GET['limit'] ?? 10)));

            $stmt = $this->pdo->prepare("
                SELECT s.*, COALESCE(u.username, CONCAT('Operator #', s.operator_id)) AS operator_namn
                FROM rebotling_skiftoverlamning s
                LEFT JOIN users u ON s.operator_id = u.id
                ORDER BY s.skapad DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $r) {
                $items[] = $this->formatProtokollRow($r);
            }

            $this->sendSuccess(['items' => $items]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getProtokollHistorik: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta protokollhistorik', 500);
        }
    }

    /**
     * GET run=protokoll-detalj&id=N
     * Hamta ett specifikt skiftoverlamningsprotokoll
     */
    private function getProtokollDetalj(): void {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->sendError('id kravs');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, COALESCE(u.username, CONCAT('Operator #', s.operator_id)) AS operator_namn
                FROM rebotling_skiftoverlamning s
                LEFT JOIN users u ON s.operator_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$r) {
                $this->sendError('Protokoll hittades inte', 404);
                return;
            }

            $this->sendSuccess(['item' => $this->formatProtokollRow($r)]);
        } catch (\PDOException $e) {
            error_log('SkiftoverlamningController getProtokollDetalj: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta protokoll', 500);
        }
    }

    private function formatProtokollRow(array $r): array {
        return [
            'id'                    => (int)$r['id'],
            'skift_datum'           => $r['skift_datum'],
            'skift_typ'             => $r['skift_typ'],
            'skift_typ_label'       => $this->skiftTypLabel($r['skift_typ']),
            'operator_id'           => (int)($r['operator_id'] ?? 0),
            'operator_namn'         => $r['operator_namn'] ?? '--',
            'produktion_antal'      => (int)$r['produktion_antal'],
            'oee_procent'           => (float)$r['oee_procent'],
            'stopp_antal'           => (int)$r['stopp_antal'],
            'stopp_minuter'         => (int)$r['stopp_minuter'],
            'kassation_procent'     => (float)$r['kassation_procent'],
            'checklista_rengoring'  => (bool)$r['checklista_rengoring'],
            'checklista_verktyg'    => (bool)$r['checklista_verktyg'],
            'checklista_kemikalier' => (bool)$r['checklista_kemikalier'],
            'checklista_avvikelser' => (bool)$r['checklista_avvikelser'],
            'checklista_sakerhet'   => (bool)$r['checklista_sakerhet'],
            'checklista_material'   => (bool)$r['checklista_material'],
            'kommentar_hande'       => $r['kommentar_hande'],
            'kommentar_atgarda'     => $r['kommentar_atgarda'],
            'kommentar_ovrigt'      => $r['kommentar_ovrigt'],
            'skapad'                => $r['skapad'],
        ];
    }
}
