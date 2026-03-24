<?php

/**
 * BatchSparningController
 * Batch-spårning — följ IBC-batchar genom produktionslinjen.
 *
 * Endpoints via ?action=batchsparning&run=XXX:
 *
 *   GET  run=overview        — KPI:er: aktiva batchar, snitt ledtid, snitt kassation, bästa batch
 *   GET  run=active-batches  — Lista aktiva batchar med progress
 *   GET  run=batch-detail    — (?batch_id=X) Detaljinfo inkl. operatörer, cykeltider, kassation
 *   GET  run=batch-history   — Avslutade batchar med KPI:er, stöd för period-filter
 *   POST run=create-batch    — Skapa ny batch
 *   POST run=complete-batch  — Markera batch som klar
 */
class BatchSparningController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'overview':        $this->getOverview();       break;
                case 'active-batches':  $this->getActiveBatches();  break;
                case 'batch-detail':    $this->getBatchDetail();    break;
                case 'batch-history':   $this->getBatchHistory();   break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'create-batch':
                    $this->requireLogin();
                    $this->createBatch();
                    break;
                case 'complete-batch':
                    $this->requireLogin();
                    $this->completeBatch();
                    break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
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
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
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

    private function ensureTables(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'batch_order'"
            )->fetchColumn();
            if (!$check) {
                $migrationPath = __DIR__ . '/../migrations/2026-03-12_batch_sparning.sql';
                $sql = file_get_contents($migrationPath);
                if ($sql === false) {
                    error_log('BatchSparningController::ensureTables: kunde inte läsa migrationsfil: ' . $migrationPath);
                } elseif ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('BatchSparningController::ensureTables: ' . $e->getMessage());
        }
    }

    private function statusLabel(string $status): string {
        switch ($status) {
            case 'pagaende': return 'Pågår';
            case 'klar':     return 'Klar';
            case 'pausad':   return 'Pausad';
            default:         return $status;
        }
    }

    // =========================================================================
    // GET run=overview
    // KPI:er: aktiva batchar, snitt ledtid, snitt kassation%, bästa batch
    // =========================================================================

    private function getOverview(): void {
        try {
            // Antal aktiva batchar (pågående + pausade)
            $aktivaStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM batch_order WHERE status IN ('pagaende','pausad')"
            );
            $aktivaBatchar = (int)$aktivaStmt->fetchColumn();

            // Snitt ledtid för klara batchar (timmar)
            $ledtidStmt = $this->pdo->query(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, skapad_datum, avslutad_datum))
                 FROM batch_order
                 WHERE status = 'klar' AND avslutad_datum IS NOT NULL"
            );
            $snittLedtid = round((float)($ledtidStmt->fetchColumn() ?? 0), 1);

            // Snitt kassation% per batch (över klara batchar)
            $kassStmt = $this->pdo->query(
                "SELECT
                    AVG(sub.kass_pct) AS snitt_kass
                 FROM (
                     SELECT bo.id,
                            CASE WHEN COUNT(bi.id) > 0
                                 THEN (SUM(bi.kasserad) / COUNT(bi.id)) * 100
                                 ELSE 0
                            END AS kass_pct
                     FROM batch_order bo
                     LEFT JOIN batch_ibc bi ON bi.batch_id = bo.id
                     WHERE bo.status = 'klar'
                     GROUP BY bo.id
                 ) sub"
            );
            $snittKassation = round((float)($kassStmt->fetchColumn() ?? 0), 1);

            // Bästa batch (lägst kassation bland klara med IBC:er)
            $bastaStmt = $this->pdo->query(
                "SELECT bo.batch_nummer,
                        CASE WHEN COUNT(bi.id) > 0
                             THEN (SUM(bi.kasserad) / COUNT(bi.id)) * 100
                             ELSE 0
                        END AS kass_pct
                 FROM batch_order bo
                 LEFT JOIN batch_ibc bi ON bi.batch_id = bo.id
                 WHERE bo.status = 'klar'
                 GROUP BY bo.id, bo.batch_nummer
                 HAVING COUNT(bi.id) > 0
                 ORDER BY kass_pct ASC, bo.avslutad_datum DESC
                 LIMIT 1"
            );
            $basta = $bastaStmt->fetch(\PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'data' => [
                    'aktiva_batchar'    => $aktivaBatchar,
                    'snitt_ledtid_h'    => $snittLedtid,
                    'snitt_kassation'   => $snittKassation,
                    'basta_batch'       => $basta ? $basta['batch_nummer'] : '-',
                    'basta_batch_kass'  => $basta ? round((float)$basta['kass_pct'], 1) : 0,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('BatchSparningController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // =========================================================================
    // GET run=active-batches
    // Lista aktiva batchar med progress
    // =========================================================================

    private function getActiveBatches(): void {
        try {
            $stmt = $this->pdo->query(
                "SELECT bo.id, bo.batch_nummer, bo.planerat_antal, bo.kommentar, bo.status,
                        bo.skapad_datum, bo.avslutad_datum,
                        COUNT(bi.id) AS antal_ibc,
                        COALESCE(SUM(CASE WHEN bi.klar IS NOT NULL THEN 1 ELSE 0 END), 0) AS antal_klara,
                        COALESCE(SUM(bi.kasserad), 0) AS antal_kasserade,
                        AVG(bi.cykeltid_sekunder) AS snitt_cykeltid
                 FROM batch_order bo
                 LEFT JOIN batch_ibc bi ON bi.batch_id = bo.id
                 WHERE bo.status IN ('pagaende','pausad')
                 GROUP BY bo.id
                 ORDER BY bo.skapad_datum DESC"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $batchar = [];
            foreach ($rows as $r) {
                $planerat = (int)$r['planerat_antal'];
                $klara = (int)$r['antal_klara'];
                $kvar = max(0, $planerat - $klara);

                // Uppskattad tid kvar baserat på snitt cykeltid
                $snittCykel = $r['snitt_cykeltid'] ? (float)$r['snitt_cykeltid'] : 0;
                $uppskattadMinKvar = $snittCykel > 0 ? round(($kvar * $snittCykel) / 60, 0) : null;

                $batchar[] = [
                    'id'               => (int)$r['id'],
                    'batch_nummer'     => $r['batch_nummer'],
                    'planerat_antal'   => $planerat,
                    'antal_klara'      => $klara,
                    'antal_kasserade'  => (int)$r['antal_kasserade'],
                    'status'           => $r['status'],
                    'status_label'     => $this->statusLabel($r['status']),
                    'skapad_datum'     => $r['skapad_datum'],
                    'kommentar'        => $r['kommentar'],
                    'snitt_cykeltid_s' => $snittCykel ? round($snittCykel, 0) : null,
                    'uppskattat_kvar_min' => $uppskattadMinKvar,
                ];
            }

            $this->sendSuccess(['batchar' => $batchar]);
        } catch (\PDOException $e) {
            error_log('BatchSparningController::getActiveBatches: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta aktiva batchar', 500);
        }
    }

    // =========================================================================
    // GET run=batch-detail&batch_id=X
    // Detaljinfo inkl. operatörer, cykeltider, kassation
    // =========================================================================

    private function getBatchDetail(): void {
        $batchId = (int)($_GET['batch_id'] ?? 0);
        if ($batchId <= 0) {
            $this->sendError('batch_id krävs');
            return;
        }

        try {
            // Hämta batch
            $batchStmt = $this->pdo->prepare(
                "SELECT id, batch_nummer, planerat_antal, kommentar, status,
                        skapad_av, skapad_datum, avslutad_datum
                 FROM batch_order WHERE id = ?"
            );
            $batchStmt->execute([$batchId]);
            $batch = $batchStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$batch) {
                $this->sendError('Batch hittades inte', 404);
                return;
            }

            // Hämta IBC:er
            $ibcStmt = $this->pdo->prepare(
                "SELECT bi.id, bi.ibc_nummer, bi.operator_id, bi.startad, bi.klar,
                        bi.kasserad, bi.cykeltid_sekunder
                 FROM batch_ibc bi
                 WHERE bi.batch_id = ?
                 ORDER BY bi.startad ASC"
            );
            $ibcStmt->execute([$batchId]);
            $ibcRows = $ibcStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Aggregera
            $antalKlara = 0;
            $antalKasserade = 0;
            $totalCykeltid = 0;
            $cykeltidCount = 0;
            $operatorer = [];

            foreach ($ibcRows as $ibc) {
                if ($ibc['klar'] !== null) $antalKlara++;
                if ((int)$ibc['kasserad']) $antalKasserade++;
                if ($ibc['cykeltid_sekunder']) {
                    $totalCykeltid += (int)$ibc['cykeltid_sekunder'];
                    $cykeltidCount++;
                }
                if ($ibc['operator_id']) {
                    $opId = (int)$ibc['operator_id'];
                    if (!isset($operatorer[$opId])) {
                        $operatorer[$opId] = ['id' => $opId, 'namn' => 'Operatör #' . $opId, 'antal' => 0];
                    }
                    $operatorer[$opId]['antal']++;
                }
            }

            // Försök hämta riktiga operatörsnamn
            if (!empty($operatorer)) {
                $ids = array_keys($operatorer);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                // Försök operators-tabellen (om den finns)
                try {
                    $opStmt = $this->pdo->prepare(
                        "SELECT id, name FROM operators WHERE id IN ($placeholders)"
                    );
                    $opStmt->execute($ids);
                    while ($op = $opStmt->fetch(\PDO::FETCH_ASSOC)) {
                        $opId = (int)$op['id'];
                        if (isset($operatorer[$opId])) {
                            $operatorer[$opId]['namn'] = $op['name'];
                        }
                    }
                } catch (\PDOException $e) {
                    error_log('BatchSparningController::getBatchDetail (operators): ' . $e->getMessage());
                }
            }

            $snittCykeltid = $cykeltidCount > 0 ? round($totalCykeltid / $cykeltidCount, 0) : null;

            // Tidsåtgång
            $startTid = $batch['skapad_datum'];
            $slutTid = $batch['avslutad_datum'] ?? date('Y-m-d H:i:s');
            $tidsatgangMin = round((strtotime($slutTid) - strtotime($startTid)) / 60, 0);

            $this->sendSuccess([
                'batch' => [
                    'id'              => (int)$batch['id'],
                    'batch_nummer'    => $batch['batch_nummer'],
                    'planerat_antal'  => (int)$batch['planerat_antal'],
                    'kommentar'       => $batch['kommentar'],
                    'status'          => $batch['status'],
                    'status_label'    => $this->statusLabel($batch['status']),
                    'skapad_datum'    => $batch['skapad_datum'],
                    'avslutad_datum'  => $batch['avslutad_datum'],
                ],
                'antal_klara'       => $antalKlara,
                'antal_kasserade'   => $antalKasserade,
                'snitt_cykeltid_s'  => $snittCykeltid,
                'tidsatgang_min'    => (int)$tidsatgangMin,
                'operatorer'        => array_values($operatorer),
                'ibcer'             => array_map(function ($ibc) {
                    return [
                        'id'                => (int)$ibc['id'],
                        'ibc_nummer'        => $ibc['ibc_nummer'],
                        'operator_id'       => $ibc['operator_id'] ? (int)$ibc['operator_id'] : null,
                        'startad'           => $ibc['startad'],
                        'klar'              => $ibc['klar'],
                        'kasserad'          => (bool)$ibc['kasserad'],
                        'cykeltid_sekunder' => $ibc['cykeltid_sekunder'] ? (int)$ibc['cykeltid_sekunder'] : null,
                    ];
                }, $ibcRows),
            ]);
        } catch (\PDOException $e) {
            error_log('BatchSparningController::getBatchDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta batch-detaljer', 500);
        }
    }

    // =========================================================================
    // GET run=batch-history
    // Avslutade batchar med KPI:er, stöd för period-filter
    // =========================================================================

    private function getBatchHistory(): void {
        try {
            $from = trim($_GET['from'] ?? '');
            $to   = trim($_GET['to'] ?? '');

            // Validera att from <= to, annars byt plats
            if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from > $to) {
                [$from, $to] = [$to, $from];
            }
            // Begränsa till max 365 dagar
            if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                try {
                    $diffDays = (int)(new \DateTime($from))->diff(new \DateTime($to))->days;
                    if ($diffDays > 365) {
                        $from = date('Y-m-d', strtotime($to . ' -365 days'));
                    }
                } catch (\Exception $e) {
                    error_log('BatchSparningController: datumberäkning fallback — ' . $e->getMessage());
                    $from = date('Y-m-d', strtotime('-30 days'));
                    $to   = date('Y-m-d');
                }
            }

            $where = "bo.status = 'klar'";
            $params = [];

            if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $where .= " AND bo.avslutad_datum >= ?";
                $params[] = $from . ' 00:00:00';
            }
            if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $where .= " AND bo.avslutad_datum < ?";
                $params[] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
            }

            $search = mb_substr(trim($_GET['search'] ?? ''), 0, 200);
            if ($search) {
                $where .= " AND (bo.batch_nummer LIKE ? OR bo.kommentar LIKE ?)";
                $escapedSearch = addcslashes($search, '%_\\');
                $params[] = '%' . $escapedSearch . '%';
                $params[] = '%' . $escapedSearch . '%';
            }

            $stmt = $this->pdo->prepare(
                "SELECT bo.id, bo.batch_nummer, bo.planerat_antal, bo.kommentar, bo.status,
                        bo.skapad_datum, bo.avslutad_datum,
                        COUNT(bi.id) AS antal_ibc,
                        COALESCE(SUM(CASE WHEN bi.klar IS NOT NULL THEN 1 ELSE 0 END), 0) AS antal_klara,
                        COALESCE(SUM(bi.kasserad), 0) AS antal_kasserade,
                        AVG(bi.cykeltid_sekunder) AS snitt_cykeltid,
                        TIMESTAMPDIFF(MINUTE, bo.skapad_datum, bo.avslutad_datum) AS ledtid_min
                 FROM batch_order bo
                 LEFT JOIN batch_ibc bi ON bi.batch_id = bo.id
                 WHERE $where
                 GROUP BY bo.id
                 ORDER BY bo.avslutad_datum DESC
                 LIMIT 100"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $batchar = [];
            foreach ($rows as $r) {
                $antalIbc = (int)$r['antal_ibc'];
                $kasserade = (int)$r['antal_kasserade'];
                $kassPct = $antalIbc > 0 ? round(($kasserade / $antalIbc) * 100, 1) : 0;

                $batchar[] = [
                    'id'               => (int)$r['id'],
                    'batch_nummer'     => $r['batch_nummer'],
                    'planerat_antal'   => (int)$r['planerat_antal'],
                    'antal_klara'      => (int)$r['antal_klara'],
                    'antal_kasserade'  => $kasserade,
                    'kassation_pct'    => $kassPct,
                    'snitt_cykeltid_s' => $r['snitt_cykeltid'] ? round((float)$r['snitt_cykeltid'], 0) : null,
                    'ledtid_min'       => $r['ledtid_min'] ? (int)$r['ledtid_min'] : null,
                    'skapad_datum'     => $r['skapad_datum'],
                    'avslutad_datum'   => $r['avslutad_datum'],
                    'kommentar'        => $r['kommentar'],
                    'status_label'     => $this->statusLabel($r['status']),
                ];
            }

            $this->sendSuccess(['batchar' => $batchar]);
        } catch (\PDOException $e) {
            error_log('BatchSparningController::getBatchHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta batch-historik', 500);
        }
    }

    // =========================================================================
    // POST run=create-batch
    // Body: { batch_nummer, planerat_antal, kommentar }
    // =========================================================================

    private function createBatch(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $batchNummer = htmlspecialchars(trim($data['batch_nummer'] ?? ''), ENT_QUOTES, 'UTF-8');
        if (!$batchNummer) {
            $this->sendError('batch_nummer krävs');
            return;
        }
        if (mb_strlen($batchNummer) > 100) {
            $batchNummer = mb_substr($batchNummer, 0, 100);
        }

        $planeratAntal = max(1, min(99999, (int)($data['planerat_antal'] ?? 0)));
        $kommentar = isset($data['kommentar']) ? htmlspecialchars(trim($data['kommentar']), ENT_QUOTES, 'UTF-8') : null;
        if ($kommentar && mb_strlen($kommentar) > 2000) {
            $kommentar = mb_substr($kommentar, 0, 2000);
        }

        $skapadAv = $this->currentUserId();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO batch_order (batch_nummer, planerat_antal, kommentar, status, skapad_av, skapad_datum)
                 VALUES (?, ?, ?, 'pagaende', ?, NOW())"
            );
            $stmt->execute([$batchNummer, $planeratAntal, $kommentar, $skapadAv]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Batch skapad',
            ]);
        } catch (\PDOException $e) {
            error_log('BatchSparningController::createBatch: ' . $e->getMessage());
            $this->sendError('Kunde inte skapa batch', 500);
        }
    }

    // =========================================================================
    // POST run=complete-batch
    // Body: { batch_id }
    // =========================================================================

    private function completeBatch(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $batchId = (int)($data['batch_id'] ?? 0);
        if ($batchId <= 0) {
            $this->sendError('batch_id krävs');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // Kontrollera att batchen finns och inte redan är klar (FOR UPDATE för att undvika race condition)
            $checkStmt = $this->pdo->prepare(
                "SELECT id, status FROM batch_order WHERE id = ? FOR UPDATE"
            );
            $checkStmt->execute([$batchId]);
            $batch = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$batch) {
                $this->pdo->rollBack();
                $this->sendError('Batch hittades inte', 404);
                return;
            }
            if ($batch['status'] === 'klar') {
                $this->pdo->rollBack();
                $this->sendError('Batchen är redan markerad som klar');
                return;
            }

            $stmt = $this->pdo->prepare(
                "UPDATE batch_order SET status = 'klar', avslutad_datum = NOW() WHERE id = ? AND status != 'klar'"
            );
            $stmt->execute([$batchId]);

            $this->pdo->commit();

            $this->sendSuccess(['message' => 'Batch markerad som klar']);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('BatchSparningController::completeBatch: ' . $e->getMessage());
            $this->sendError('Kunde inte avsluta batch', 500);
        }
    }
}
