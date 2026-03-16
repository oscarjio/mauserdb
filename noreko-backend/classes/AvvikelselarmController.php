<?php
/**
 * AvvikelselarmController.php
 * Automatiska avvikelselarm — larmsystem for produktionsavvikelser
 *
 * Endpoints via ?action=avvikelselarm&run=XXX:
 *   - run=overview          -> KPI:er: aktiva larm, kritiska, idag, snitt losningstid
 *   - run=aktiva            -> lista aktiva (ej kvitterade) larm
 *   - run=historik          -> alla larm med filter (typ, allvarlighetsgrad, period)
 *   - run=kvittera (POST)   -> kvittera ett larm med kommentar
 *   - run=regler            -> hamta larmregler
 *   - run=uppdatera-regel (POST) -> uppdatera troskelvarde/aktiv-status
 *   - run=trend             -> larmtrend per dag/vecka
 */
class AvvikelselarmController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'overview':         $this->getOverview();       break;
            case 'aktiva':           $this->getAktiva();         break;
            case 'historik':         $this->getHistorik();       break;
            case 'kvittera':         $this->kvittera();          break;
            case 'regler':           $this->getRegler();         break;
            case 'uppdatera-regel':  $this->uppdateraRegel();    break;
            case 'trend':            $this->getTrend();          break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        $period = trim($_GET['period'] ?? '');
        switch ($period) {
            case 'dag':    return 1;
            case 'vecka':  return 7;
            case 'manad':  return 30;
            default:       return max(1, min(365, (int)($_GET['days'] ?? 30)));
        }
    }

    private function getDateRange(int $days): array {
        $toDate   = date('Y-m-d');
        $fromDate = $days === 1
            ? $toDate
            : date('Y-m-d', strtotime("-{$days} days"));
        return [$fromDate, $toDate];
    }

    private function sendSuccess(array $data): void {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    // ================================================================
    // ensureTables — skapa tabeller + seed om de saknas
    // ================================================================

    private function ensureTables(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `avvikelselarm` (
                    `id`                    INT NOT NULL AUTO_INCREMENT,
                    `typ`                   ENUM('oee','kassation','produktionstakt','maskinstopp','produktionsmal') NOT NULL,
                    `allvarlighetsgrad`     ENUM('kritisk','varning','info') NOT NULL DEFAULT 'varning',
                    `meddelande`            VARCHAR(500) NOT NULL,
                    `varde_aktuellt`        DECIMAL(10,2) DEFAULT NULL,
                    `varde_grans`           DECIMAL(10,2) DEFAULT NULL,
                    `tidsstampel`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `kvitterad`             TINYINT(1) NOT NULL DEFAULT 0,
                    `kvitterad_av`          VARCHAR(100) DEFAULT NULL,
                    `kvitterad_datum`       DATETIME DEFAULT NULL,
                    `kvitterings_kommentar` TEXT DEFAULT NULL,
                    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_typ`               (`typ`),
                    KEY `idx_allvarlighetsgrad`  (`allvarlighetsgrad`),
                    KEY `idx_kvitterad`          (`kvitterad`),
                    KEY `idx_tidsstampel`        (`tidsstampel`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `larmregler` (
                    `id`                INT NOT NULL AUTO_INCREMENT,
                    `typ`               ENUM('oee','kassation','produktionstakt','maskinstopp','produktionsmal') NOT NULL,
                    `allvarlighetsgrad` ENUM('kritisk','varning','info') NOT NULL DEFAULT 'varning',
                    `grans_varde`       DECIMAL(10,2) NOT NULL,
                    `aktiv`             TINYINT(1) NOT NULL DEFAULT 1,
                    `beskrivning`       VARCHAR(300) NOT NULL,
                    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`        DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_typ` (`typ`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed larmregler om tom
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM larmregler")->fetchColumn();
            if ($count === 0) {
                $this->pdo->exec("
                    INSERT INTO larmregler (typ, allvarlighetsgrad, grans_varde, aktiv, beskrivning) VALUES
                    ('oee',             'varning', 65.00, 1, 'OEE under 65% — varning vid lag anlaggningseffektivitet'),
                    ('kassation',       'varning',  5.00, 1, 'Kassation over 5% — varning vid hog kassationsgrad'),
                    ('produktionstakt', 'varning', 10.00, 1, 'Produktionstakt under 10 IBC/h — varning vid lag takt'),
                    ('maskinstopp',     'kritisk', 30.00, 1, 'Maskinstopp langre an 30 minuter — kritiskt larm'),
                    ('produktionsmal',  'info',     0.00, 1, 'Produktionsmal ej uppnatt vid skiftslut — informationslarm')
                ");
            }

            // Seed avvikelselarm om tom
            $countLarm = (int)$this->pdo->query("SELECT COUNT(*) FROM avvikelselarm")->fetchColumn();
            if ($countLarm === 0) {
                $this->pdo->exec("
                    INSERT INTO avvikelselarm (typ, allvarlighetsgrad, meddelande, varde_aktuellt, varde_grans, tidsstampel, kvitterad, kvitterad_av, kvitterad_datum, kvitterings_kommentar) VALUES
                    ('maskinstopp','kritisk','Tvattmaskin stoppad i 55 minuter',55.00,30.00,DATE_SUB(NOW(),INTERVAL 1 DAY)+INTERVAL 6 HOUR,0,NULL,NULL,NULL),
                    ('maskinstopp','kritisk','Transportband stoppat i 42 minuter',42.00,30.00,DATE_SUB(NOW(),INTERVAL 2 DAY)+INTERVAL 9 HOUR,1,'Erik Lindqvist',DATE_SUB(NOW(),INTERVAL 2 DAY)+INTERVAL 10 HOUR,'Reparerat'),
                    ('maskinstopp','kritisk','Torkugn stoppad i 50 minuter',50.00,30.00,DATE_SUB(NOW(),INTERVAL 5 DAY)+INTERVAL 14 HOUR,1,'Anna Svensson',DATE_SUB(NOW(),INTERVAL 5 DAY)+INTERVAL 15 HOUR,'Termoelement bytt'),
                    ('maskinstopp','kritisk','Tvattmaskin stoppad i 60 minuter',60.00,30.00,DATE_SUB(NOW(),INTERVAL 8 DAY)+INTERVAL 7 HOUR,1,'Peter Olsson',DATE_SUB(NOW(),INTERVAL 8 DAY)+INTERVAL 8 HOUR,'Ventil bytt'),
                    ('oee','varning','OEE pa 58% — under gransvarde 65%',58.00,65.00,DATE_SUB(NOW(),INTERVAL 1 DAY)+INTERVAL 16 HOUR,0,NULL,NULL,NULL),
                    ('oee','varning','OEE pa 52% — under gransvarde 65%',52.00,65.00,DATE_SUB(NOW(),INTERVAL 3 DAY)+INTERVAL 15 HOUR,1,'Maria Johansson',DATE_SUB(NOW(),INTERVAL 3 DAY)+INTERVAL 16 HOUR,'Okat bemanning'),
                    ('oee','varning','OEE pa 61% — under gransvarde 65%',61.00,65.00,DATE_SUB(NOW(),INTERVAL 7 DAY)+INTERVAL 14 HOUR,1,'Erik Lindqvist',DATE_SUB(NOW(),INTERVAL 7 DAY)+INTERVAL 15 HOUR,'Justerat'),
                    ('oee','varning','OEE pa 48% — under gransvarde 65%',48.00,65.00,DATE_SUB(NOW(),INTERVAL 12 DAY)+INTERVAL 13 HOUR,1,'Anna Svensson',DATE_SUB(NOW(),INTERVAL 12 DAY)+INTERVAL 14 HOUR,'Personal sjuk'),
                    ('kassation','varning','Kassationsgrad 7.2% — over 5%',7.20,5.00,DATE_SUB(NOW(),INTERVAL 2 DAY)+INTERVAL 11 HOUR,0,NULL,NULL,NULL),
                    ('kassation','varning','Kassationsgrad 8.5% — over 5%',8.50,5.00,DATE_SUB(NOW(),INTERVAL 4 DAY)+INTERVAL 10 HOUR,1,'Peter Olsson',DATE_SUB(NOW(),INTERVAL 4 DAY)+INTERVAL 11 HOUR,'Dalig ravar'),
                    ('kassation','varning','Kassationsgrad 6.1% — over 5%',6.10,5.00,DATE_SUB(NOW(),INTERVAL 9 DAY)+INTERVAL 12 HOUR,1,'Maria Johansson',DATE_SUB(NOW(),INTERVAL 9 DAY)+INTERVAL 13 HOUR,'Justerat'),
                    ('kassation','varning','Kassationsgrad 9.3% — over 5%',9.30,5.00,DATE_SUB(NOW(),INTERVAL 15 DAY)+INTERVAL 8 HOUR,1,'Erik Lindqvist',DATE_SUB(NOW(),INTERVAL 15 DAY)+INTERVAL 9 HOUR,'Ny leverantor'),
                    ('produktionstakt','varning','Produktionstakt 7 IBC/h — under 10',7.00,10.00,DATE_SUB(NOW(),INTERVAL 1 DAY)+INTERVAL 8 HOUR,0,NULL,NULL,NULL),
                    ('produktionstakt','varning','Produktionstakt 5 IBC/h — under 10',5.00,10.00,DATE_SUB(NOW(),INTERVAL 6 DAY)+INTERVAL 9 HOUR,1,'Anna Svensson',DATE_SUB(NOW(),INTERVAL 6 DAY)+INTERVAL 10 HOUR,'Maskinstopp lost'),
                    ('produktionstakt','varning','Produktionstakt 8 IBC/h — under 10',8.00,10.00,DATE_SUB(NOW(),INTERVAL 11 DAY)+INTERVAL 7 HOUR,1,'Peter Olsson',DATE_SUB(NOW(),INTERVAL 11 DAY)+INTERVAL 8 HOUR,'Nyanstallda'),
                    ('produktionstakt','varning','Produktionstakt 6 IBC/h — under 10',6.00,10.00,DATE_SUB(NOW(),INTERVAL 20 DAY)+INTERVAL 10 HOUR,1,'Maria Johansson',DATE_SUB(NOW(),INTERVAL 20 DAY)+INTERVAL 11 HOUR,'Halvt skift'),
                    ('produktionsmal','info','Dagligt mal ej uppnatt: 85 av 100 IBC',85.00,100.00,DATE_SUB(NOW(),INTERVAL 1 DAY)+INTERVAL 17 HOUR,0,NULL,NULL,NULL),
                    ('produktionsmal','info','Dagligt mal ej uppnatt: 72 av 100 IBC',72.00,100.00,DATE_SUB(NOW(),INTERVAL 3 DAY)+INTERVAL 17 HOUR,1,'Erik Lindqvist',DATE_SUB(NOW(),INTERVAL 3 DAY)+INTERVAL 18 HOUR,'Maskinstopp'),
                    ('produktionsmal','info','Dagligt mal ej uppnatt: 90 av 100 IBC',90.00,100.00,DATE_SUB(NOW(),INTERVAL 10 DAY)+INTERVAL 17 HOUR,1,'Anna Svensson',DATE_SUB(NOW(),INTERVAL 10 DAY)+INTERVAL 18 HOUR,'Nara malet'),
                    ('produktionsmal','info','Dagligt mal ej uppnatt: 65 av 100 IBC',65.00,100.00,DATE_SUB(NOW(),INTERVAL 18 DAY)+INTERVAL 17 HOUR,1,'Peter Olsson',DATE_SUB(NOW(),INTERVAL 18 DAY)+INTERVAL 18 HOUR,'Stor stopp kl 10')
                ");
            }
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::ensureTables: ' . $e->getMessage());
        }
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        try {
            // Aktiva larm (ej kvitterade)
            $aktivaTotal = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM avvikelselarm WHERE kvitterad = 0"
            )->fetchColumn();

            // Kritiska aktiva larm
            $aktivaKritiska = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM avvikelselarm WHERE kvitterad = 0 AND allvarlighetsgrad = 'kritisk'"
            )->fetchColumn();

            // Larm idag
            $idag = date('Y-m-d');
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM avvikelselarm WHERE DATE(tidsstampel) = :idag"
            );
            $stmt->execute([':idag' => $idag]);
            $larmIdag = (int)$stmt->fetchColumn();

            // Snitt losningstid (minuter) for kvitterade larm
            $snittLosning = 0.0;
            $stmt = $this->pdo->query("
                SELECT AVG(TIMESTAMPDIFF(MINUTE, tidsstampel, kvitterad_datum)) AS snitt_min
                FROM avvikelselarm
                WHERE kvitterad = 1 AND kvitterad_datum IS NOT NULL
            ");
            $row = $stmt->fetch();
            if ($row && $row['snitt_min'] !== null) {
                $snittLosning = round((float)$row['snitt_min'], 1);
            }

            // Fordelning per typ (aktiva)
            $stmt = $this->pdo->query("
                SELECT typ, COUNT(*) AS antal
                FROM avvikelselarm
                WHERE kvitterad = 0
                GROUP BY typ
                ORDER BY antal DESC
            ");
            $perTyp = [];
            while ($row = $stmt->fetch()) {
                $perTyp[] = [
                    'typ'   => $row['typ'],
                    'antal' => (int)$row['antal'],
                ];
            }

            // Fordelning per allvarlighetsgrad (aktiva)
            $stmt = $this->pdo->query("
                SELECT allvarlighetsgrad, COUNT(*) AS antal
                FROM avvikelselarm
                WHERE kvitterad = 0
                GROUP BY allvarlighetsgrad
                ORDER BY FIELD(allvarlighetsgrad, 'kritisk', 'varning', 'info')
            ");
            $perGrad = [];
            while ($row = $stmt->fetch()) {
                $perGrad[] = [
                    'allvarlighetsgrad' => $row['allvarlighetsgrad'],
                    'antal'             => (int)$row['antal'],
                ];
            }

            $this->sendSuccess([
                'aktiva_totalt'     => $aktivaTotal,
                'aktiva_kritiska'   => $aktivaKritiska,
                'larm_idag'         => $larmIdag,
                'snitt_losningstid' => $snittLosning,
                'per_typ'           => $perTyp,
                'per_allvarlighetsgrad' => $perGrad,
            ]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::getOverview: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=aktiva — Lista aktiva (ej kvitterade) larm
    // ================================================================

    private function getAktiva(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, typ, allvarlighetsgrad, meddelande, varde_aktuellt, varde_grans,
                       tidsstampel, kvitterad
                FROM avvikelselarm
                WHERE kvitterad = 0
                ORDER BY FIELD(allvarlighetsgrad, 'kritisk', 'varning', 'info'), tidsstampel DESC
            ");
            $larm = [];
            while ($row = $stmt->fetch()) {
                $larm[] = [
                    'id'                => (int)$row['id'],
                    'typ'               => $row['typ'],
                    'allvarlighetsgrad' => $row['allvarlighetsgrad'],
                    'meddelande'        => $row['meddelande'],
                    'varde_aktuellt'    => $row['varde_aktuellt'] !== null ? (float)$row['varde_aktuellt'] : null,
                    'varde_grans'       => $row['varde_grans'] !== null ? (float)$row['varde_grans'] : null,
                    'tidsstampel'       => $row['tidsstampel'],
                ];
            }
            $this->sendSuccess(['larm' => $larm, 'antal' => count($larm)]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::getAktiva: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=historik — Alla larm med filter
    // ================================================================

    private function getHistorik(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        $typFilter  = trim($_GET['typ'] ?? '');
        $gradFilter = trim($_GET['allvarlighetsgrad'] ?? '');

        $where  = "WHERE DATE(tidsstampel) BETWEEN :from_date AND :to_date";
        $params = [':from_date' => $fromDate, ':to_date' => $toDate];

        if ($typFilter && in_array($typFilter, ['oee','kassation','produktionstakt','maskinstopp','produktionsmal'])) {
            $where .= " AND typ = :typ";
            $params[':typ'] = $typFilter;
        }
        if ($gradFilter && in_array($gradFilter, ['kritisk','varning','info'])) {
            $where .= " AND allvarlighetsgrad = :grad";
            $params[':grad'] = $gradFilter;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, typ, allvarlighetsgrad, meddelande, varde_aktuellt, varde_grans,
                       tidsstampel, kvitterad, kvitterad_av, kvitterad_datum, kvitterings_kommentar
                FROM avvikelselarm
                {$where}
                ORDER BY tidsstampel DESC
                LIMIT 500
            ");
            $stmt->execute($params);
            $larm = [];
            while ($row = $stmt->fetch()) {
                $larm[] = [
                    'id'                    => (int)$row['id'],
                    'typ'                   => $row['typ'],
                    'allvarlighetsgrad'     => $row['allvarlighetsgrad'],
                    'meddelande'            => $row['meddelande'],
                    'varde_aktuellt'        => $row['varde_aktuellt'] !== null ? (float)$row['varde_aktuellt'] : null,
                    'varde_grans'           => $row['varde_grans'] !== null ? (float)$row['varde_grans'] : null,
                    'tidsstampel'           => $row['tidsstampel'],
                    'kvitterad'             => (bool)$row['kvitterad'],
                    'kvitterad_av'          => $row['kvitterad_av'],
                    'kvitterad_datum'       => $row['kvitterad_datum'],
                    'kvitterings_kommentar' => $row['kvitterings_kommentar'],
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'larm'      => $larm,
                'total'     => count($larm),
            ]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::getHistorik: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=kvittera (POST) — Kvittera ett larm
    // ================================================================

    private function kvittera(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST kravs', 405);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $larmId    = (int)($body['larm_id'] ?? 0);
        $kommentar = trim($body['kommentar'] ?? '');
        $kvitteradAv = trim($body['kvitterad_av'] ?? '');

        if ($larmId < 1) {
            $this->sendError('larm_id kravs');
            return;
        }
        if (empty($kvitteradAv)) {
            $this->sendError('kvitterad_av kravs');
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                UPDATE avvikelselarm
                SET kvitterad = 1,
                    kvitterad_av = :kvitterad_av,
                    kvitterad_datum = NOW(),
                    kvitterings_kommentar = :kommentar
                WHERE id = :id AND kvitterad = 0
            ");
            $stmt->execute([
                ':kvitterad_av' => $kvitteradAv,
                ':kommentar'    => $kommentar ?: null,
                ':id'           => $larmId,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->sendError('Larmet hittades inte eller ar redan kvitterat');
                return;
            }

            $this->sendSuccess(['kvitterat' => true, 'larm_id' => $larmId]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::kvittera: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=regler — Hamta larmregler
    // ================================================================

    private function getRegler(): void {
        try {
            $stmt = $this->pdo->query("
                SELECT id, typ, allvarlighetsgrad, grans_varde, aktiv, beskrivning, updated_at
                FROM larmregler
                ORDER BY FIELD(typ, 'oee','kassation','produktionstakt','maskinstopp','produktionsmal')
            ");
            $regler = [];
            while ($row = $stmt->fetch()) {
                $regler[] = [
                    'id'                => (int)$row['id'],
                    'typ'               => $row['typ'],
                    'allvarlighetsgrad' => $row['allvarlighetsgrad'],
                    'grans_varde'       => (float)$row['grans_varde'],
                    'aktiv'             => (bool)$row['aktiv'],
                    'beskrivning'       => $row['beskrivning'],
                    'updated_at'        => $row['updated_at'],
                ];
            }
            $this->sendSuccess(['regler' => $regler]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::getRegler: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=uppdatera-regel (POST) — Uppdatera troskelvarde/aktiv-status
    // ================================================================

    private function uppdateraRegel(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST kravs', 405);
            return;
        }

        // Krav: admin-roll
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->sendError('Admin-behörighet kravs', 403);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $regelId   = (int)($body['regel_id'] ?? 0);
        $gransVarde = isset($body['grans_varde']) ? (float)$body['grans_varde'] : null;
        $aktiv      = isset($body['aktiv']) ? (bool)$body['aktiv'] : null;

        if ($regelId < 1) {
            $this->sendError('regel_id kravs');
            return;
        }

        try {
            $sets   = [];
            $params = [':id' => $regelId];

            if ($gransVarde !== null) {
                $sets[] = "grans_varde = :grans_varde";
                $params[':grans_varde'] = $gransVarde;
            }
            if ($aktiv !== null) {
                $sets[] = "aktiv = :aktiv";
                $params[':aktiv'] = $aktiv ? 1 : 0;
            }

            if (empty($sets)) {
                $this->sendError('Inget att uppdatera');
                return;
            }

            $setStr = implode(', ', $sets);
            $stmt = $this->pdo->prepare("UPDATE larmregler SET {$setStr} WHERE id = :id");
            $stmt->execute($params);

            $this->sendSuccess(['uppdaterad' => true, 'regel_id' => $regelId]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::uppdateraRegel: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=trend — Larmtrend per dag
    // ================================================================

    private function getTrend(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(tidsstampel) AS dag,
                       allvarlighetsgrad,
                       COUNT(*) AS antal
                FROM avvikelselarm
                WHERE DATE(tidsstampel) BETWEEN :from_date AND :to_date
                GROUP BY DATE(tidsstampel), allvarlighetsgrad
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            // Bygg datumsekvens
            $dates = [];
            $d   = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($d <= $end) {
                $dates[] = $d->format('Y-m-d');
                $d->modify('+1 day');
            }

            // Organisera per grad
            $dataByGrad = ['kritisk' => [], 'varning' => [], 'info' => []];
            foreach ($rows as $row) {
                $grad = $row['allvarlighetsgrad'];
                $dag  = $row['dag'];
                if (isset($dataByGrad[$grad])) {
                    $dataByGrad[$grad][$dag] = (int)$row['antal'];
                }
            }

            // Bygg serier
            $series = [];
            foreach (['kritisk', 'varning', 'info'] as $grad) {
                $values = [];
                foreach ($dates as $date) {
                    $values[] = $dataByGrad[$grad][$date] ?? 0;
                }
                $series[] = [
                    'allvarlighetsgrad' => $grad,
                    'values'            => $values,
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'dates'     => $dates,
                'series'    => $series,
            ]);
        } catch (\PDOException $e) {
            error_log('AvvikelselarmController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
