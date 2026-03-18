<?php
/**
 * LeveransplaneringController.php
 * Leveransplanering — kundorder vs produktionskapacitet i rebotling-linjen.
 *
 * Endpoints via ?action=leveransplanering&run=XXX:
 *   - run=overview        -> KPI:er: aktiva ordrar, leveransgrad%, forsenade, kapacitetsutnyttjande%
 *   - run=ordrar          -> lista ordrar med filter (status, period)
 *   - run=kapacitet       -> kapacitetsdata per dag (tillganglig vs planerad)
 *   - run=prognos         -> leveransprognos baserat pa aktuell kapacitet och orderkö
 *   - run=konfiguration   -> hamta/uppdatera kapacitetskonfiguration
 *   - run=skapa-order     -> POST — skapa ny order
 *   - run=uppdatera-order -> POST — uppdatera orderstatus
 */
class LeveransplaneringController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'POST') {
                session_start();
            } else {
                session_start(['read_and_close' => true]);
            }
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'overview':         $this->getOverview();        break;
            case 'ordrar':           $this->getOrdrar();          break;
            case 'kapacitet':        $this->getKapacitet();       break;
            case 'prognos':          $this->getPrognos();         break;
            case 'konfiguration':    $this->handleKonfiguration(); break;
            case 'skapa-order':      $this->skapaOrder();         break;
            case 'uppdatera-order':  $this->uppdateraOrder();     break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
    // ================================================================

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

    private function getConfig(): array {
        $row = $this->pdo->query("SELECT * FROM produktionskapacitet_config ORDER BY id ASC LIMIT 1")->fetch();
        if (!$row) {
            return ['kapacitet_per_dag' => 80, 'planerade_underhallsdagar' => [], 'buffer_procent' => 10];
        }
        return [
            'kapacitet_per_dag'        => (int)$row['kapacitet_per_dag'],
            'planerade_underhallsdagar' => json_decode($row['planerade_underhallsdagar'] ?: '[]', true) ?: [],
            'buffer_procent'           => (int)$row['buffer_procent'],
        ];
    }

    private function isWorkday(string $date, array $underhallsdagar): bool {
        $dow = (int)date('N', strtotime($date)); // 1=mon, 7=sun
        if ($dow >= 6) return false; // helg
        if (in_array($date, $underhallsdagar, true)) return false;
        return true;
    }

    /**
     * Rakna ut beraknat leveransdatum baserat pa antal IBC, kapacitet och orderko
     */
    private function beraknaLeveransdatum(int $antalIbc, string $startDatum, array $config): string {
        $kapPerDag = max(1, $config['kapacitet_per_dag']);
        $buffer = max(0, $config['buffer_procent']);
        $effektivKap = (int)floor($kapPerDag * (1 - $buffer / 100));
        if ($effektivKap < 1) $effektivKap = 1;

        $dagarBehovs = (int)ceil($antalIbc / $effektivKap);
        $underhall = $config['planerade_underhallsdagar'];

        $current = $startDatum;
        $arbdagar = 0;
        while ($arbdagar < $dagarBehovs) {
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
            if ($this->isWorkday($current, $underhall)) {
                $arbdagar++;
            }
        }
        return $current;
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        try {
            $config = $this->getConfig();

            // Aktiva ordrar (planerad + i_produktion + forsenad)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM kundordrar WHERE status IN ('planerad','i_produktion','forsenad')
            ");
            $stmt->execute();
            $aktivaOrdrar = (int)$stmt->fetchColumn();

            // Totalt antal ordrar
            $totalOrdrar = (int)$this->pdo->query("SELECT COUNT(*) FROM kundordrar")->fetchColumn();

            // Levererade ordrar
            $levererade = (int)$this->pdo->query("SELECT COUNT(*) FROM kundordrar WHERE status = 'levererad'")->fetchColumn();

            // Leveransgrad = (levererade + i_tid) / totalt
            // Rakna ordrar som levererats ELLER ar i tid (beraknat <= onskat)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM kundordrar
                WHERE status = 'levererad'
                   OR (status IN ('planerad','i_produktion') AND beraknat_leveransdatum <= onskat_leveransdatum)
            ");
            $stmt->execute();
            $iTid = (int)$stmt->fetchColumn();
            $leveransgrad = $totalOrdrar > 0 ? round(($iTid / $totalOrdrar) * 100, 1) : 0;

            // Forsenade ordrar
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM kundordrar WHERE status = 'forsenad'
            ");
            $stmt->execute();
            $forsenade = (int)$stmt->fetchColumn();

            // Kapacitetsutnyttjande — planerad produktion vs tillganglig kapacitet (kommande 30 dagar)
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(antal_ibc), 0)
                FROM kundordrar
                WHERE status IN ('planerad','i_produktion','forsenad')
            ");
            $stmt->execute();
            $planeradProduktion = (int)$stmt->fetchColumn();

            // Tillganglig kapacitet 30 dagar
            $tillgangligKap = 0;
            $kapPerDag = $config['kapacitet_per_dag'];
            $buffer = $config['buffer_procent'];
            $effKap = (int)floor($kapPerDag * (1 - $buffer / 100));
            $underhall = $config['planerade_underhallsdagar'];
            for ($d = 0; $d < 30; $d++) {
                $datum = date('Y-m-d', strtotime("+{$d} days"));
                if ($this->isWorkday($datum, $underhall)) {
                    $tillgangligKap += $effKap;
                }
            }

            $kapacitetsutnyttjande = $tillgangligKap > 0
                ? round(($planeradProduktion / $tillgangligKap) * 100, 1)
                : 0;

            $this->sendSuccess([
                'aktiva_ordrar'         => $aktivaOrdrar,
                'leveransgrad'          => $leveransgrad,
                'forsenade_ordrar'      => $forsenade,
                'kapacitetsutnyttjande' => min($kapacitetsutnyttjande, 100),
                'totalt_ordrar'         => $totalOrdrar,
                'levererade'            => $levererade,
                'planerad_produktion'   => $planeradProduktion,
                'tillganglig_kapacitet' => $tillgangligKap,
            ]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::getOverview: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=ordrar — lista ordrar med filter
    // ================================================================

    private function getOrdrar(): void {
        try {
            $status = trim($_GET['status'] ?? 'alla');
            $period = trim($_GET['period'] ?? 'alla');

            $where = [];
            $params = [];

            if ($status !== 'alla' && in_array($status, ['planerad','i_produktion','levererad','forsenad'], true)) {
                $where[] = "status = :status";
                $params[':status'] = $status;
            }

            if ($period === 'vecka') {
                $where[] = "onskat_leveransdatum BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            } elseif ($period === 'manad') {
                $where[] = "onskat_leveransdatum BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            }

            $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $this->pdo->prepare("
                SELECT * FROM kundordrar
                {$whereClause}
                ORDER BY prioritet ASC, onskat_leveransdatum ASC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $ordrar = [];
            foreach ($rows as $row) {
                $displayStatus = $row['status'];
                // Berakna om i tid eller forsenad dynamiskt
                if (in_array($row['status'], ['planerad', 'i_produktion'], true)) {
                    if ($row['beraknat_leveransdatum'] && $row['beraknat_leveransdatum'] > $row['onskat_leveransdatum']) {
                        $displayStatus = 'forsenad';
                    }
                }

                $ordrar[] = [
                    'id'                     => (int)$row['id'],
                    'kundnamn'               => $row['kundnamn'],
                    'antal_ibc'              => (int)$row['antal_ibc'],
                    'bestallningsdatum'      => $row['bestallningsdatum'],
                    'onskat_leveransdatum'   => $row['onskat_leveransdatum'],
                    'beraknat_leveransdatum' => $row['beraknat_leveransdatum'],
                    'status'                 => $row['status'],
                    'display_status'         => $displayStatus,
                    'prioritet'              => (int)$row['prioritet'],
                    'notering'               => $row['notering'],
                ];
            }

            $this->sendSuccess([
                'ordrar'  => $ordrar,
                'total'   => count($ordrar),
                'filter'  => ['status' => $status, 'period' => $period],
            ]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::getOrdrar: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=kapacitet — kapacitetsdata per dag (Gantt + kapacitetsprognos)
    // ================================================================

    private function getKapacitet(): void {
        try {
            $config = $this->getConfig();
            $dagar = max(1, min(60, (int)($_GET['days'] ?? 30)));
            $kapPerDag = $config['kapacitet_per_dag'];
            $buffer = $config['buffer_procent'];
            $effKap = (int)floor($kapPerDag * (1 - $buffer / 100));
            $underhall = $config['planerade_underhallsdagar'];

            // Hamta ordrar med beraknade datum
            $stmt = $this->pdo->prepare("
                SELECT id, kundnamn, antal_ibc, beraknat_leveransdatum, onskat_leveransdatum, status, prioritet
                FROM kundordrar
                WHERE status IN ('planerad','i_produktion','forsenad')
                ORDER BY prioritet ASC, onskat_leveransdatum ASC
            ");
            $stmt->execute();
            $ordrar = $stmt->fetchAll();

            $dates = [];
            $tillganglig = [];
            $planerad = [];

            // Bygg daglig kapacitet
            for ($d = 0; $d < $dagar; $d++) {
                $datum = date('Y-m-d', strtotime("+{$d} days"));
                $dates[] = $datum;
                $isWork = $this->isWorkday($datum, $underhall);
                $tillganglig[] = $isWork ? $effKap : 0;
                $planerad[] = 0; // fylls i nedan
            }

            // Fordela ordrar over dagar (enkel FIFO-fordelning)
            foreach ($ordrar as $order) {
                $remaining = (int)$order['antal_ibc'];
                foreach ($dates as $i => $datum) {
                    if ($remaining <= 0) break;
                    if ($tillganglig[$i] <= 0) continue;
                    $ledig = $tillganglig[$i] - $planerad[$i];
                    if ($ledig <= 0) continue;
                    $allokerat = min($remaining, $ledig);
                    $planerad[$i] += $allokerat;
                    $remaining -= $allokerat;
                }
            }

            // Gantt-data per order
            $ganttItems = [];
            foreach ($ordrar as $order) {
                $ganttItems[] = [
                    'id'        => (int)$order['id'],
                    'kundnamn'  => $order['kundnamn'],
                    'antal_ibc' => (int)$order['antal_ibc'],
                    'start'     => date('Y-m-d'), // approximation
                    'slut'      => $order['beraknat_leveransdatum'],
                    'deadline'  => $order['onskat_leveransdatum'],
                    'status'    => $order['status'],
                    'prioritet' => (int)$order['prioritet'],
                    'forsenad'  => $order['beraknat_leveransdatum'] > $order['onskat_leveransdatum'],
                ];
            }

            $this->sendSuccess([
                'dates'       => $dates,
                'tillganglig' => $tillganglig,
                'planerad'    => $planerad,
                'gantt'       => $ganttItems,
                'config'      => $config,
            ]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::getKapacitet: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=prognos — leveransprognos
    // ================================================================

    private function getPrognos(): void {
        try {
            $config = $this->getConfig();

            $stmt = $this->pdo->prepare("
                SELECT * FROM kundordrar
                WHERE status IN ('planerad','i_produktion','forsenad')
                ORDER BY prioritet ASC, onskat_leveransdatum ASC
                LIMIT 500
            ");
            $stmt->execute();
            $ordrar = $stmt->fetchAll();

            $prognos = [];
            foreach ($ordrar as $order) {
                $beraknat = $this->beraknaLeveransdatum(
                    (int)$order['antal_ibc'],
                    date('Y-m-d'),
                    $config
                );
                $forsenad = $beraknat > $order['onskat_leveransdatum'];
                $dagarKvar = max(0, (int)((strtotime($order['onskat_leveransdatum']) - strtotime(date('Y-m-d'))) / 86400));

                $prognos[] = [
                    'id'                     => (int)$order['id'],
                    'kundnamn'               => $order['kundnamn'],
                    'antal_ibc'              => (int)$order['antal_ibc'],
                    'onskat_leveransdatum'   => $order['onskat_leveransdatum'],
                    'beraknat_leveransdatum' => $beraknat,
                    'dagar_kvar'             => $dagarKvar,
                    'forsenad'               => $forsenad,
                    'dagar_forsenad'         => $forsenad
                        ? max(0, (int)((strtotime($beraknat) - strtotime($order['onskat_leveransdatum'])) / 86400))
                        : 0,
                    'prioritet'              => (int)$order['prioritet'],
                ];
            }

            $this->sendSuccess([
                'prognos'   => $prognos,
                'config'    => $config,
                'beraknad_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::getPrognos: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=konfiguration — hamta/uppdatera kapacitetskonfiguration
    // ================================================================

    private function handleKonfiguration(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->uppdateraKonfiguration();
        } else {
            $this->hamtaKonfiguration();
        }
    }

    private function hamtaKonfiguration(): void {
        try {
            $config = $this->getConfig();
            $this->sendSuccess(['config' => $config]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::hamtaKonfiguration: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    private function uppdateraKonfiguration(): void {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->sendError('Ogiltig JSON-data');
                return;
            }

            $kapPerDag = max(1, (int)($input['kapacitet_per_dag'] ?? 80));
            $buffer    = max(0, min(50, (int)($input['buffer_procent'] ?? 10)));
            $underhall = $input['planerade_underhallsdagar'] ?? [];
            if (!is_array($underhall)) $underhall = [];

            $stmt = $this->pdo->prepare("
                UPDATE produktionskapacitet_config
                SET kapacitet_per_dag = :kap,
                    buffer_procent = :buffer,
                    planerade_underhallsdagar = :underhall
                WHERE id = 1
            ");
            $stmt->execute([
                ':kap'      => $kapPerDag,
                ':buffer'   => $buffer,
                ':underhall' => json_encode($underhall, JSON_UNESCAPED_UNICODE),
            ]);

            $this->sendSuccess(['updated' => true, 'config' => $this->getConfig()]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::uppdateraKonfiguration: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=skapa-order (POST)
    // ================================================================

    private function skapaOrder(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST kravs');
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->sendError('Ogiltig JSON-data');
                return;
            }

            $kundnamn   = trim($input['kundnamn'] ?? '');
            $antalIbc   = max(1, (int)($input['antal_ibc'] ?? 0));
            $bestDatum  = $input['bestallningsdatum'] ?? date('Y-m-d');
            $onskDatum  = $input['onskat_leveransdatum'] ?? '';
            $prioritet  = max(1, min(10, (int)($input['prioritet'] ?? 5)));
            $notering   = trim($input['notering'] ?? '');

            if (!$kundnamn || !$onskDatum) {
                $this->sendError('Kundnamn och onskat leveransdatum kravs');
                return;
            }

            // Berakna leveransdatum
            $config = $this->getConfig();
            $beraknat = $this->beraknaLeveransdatum($antalIbc, date('Y-m-d'), $config);

            $stmt = $this->pdo->prepare("
                INSERT INTO kundordrar
                (kundnamn, antal_ibc, bestallningsdatum, onskat_leveransdatum, beraknat_leveransdatum, status, prioritet, notering)
                VALUES (:kund, :antal, :best, :onsk, :berakn, :status, :prio, :not)
            ");

            $status = $beraknat > $onskDatum ? 'forsenad' : 'planerad';

            $stmt->execute([
                ':kund'    => $kundnamn,
                ':antal'   => $antalIbc,
                ':best'    => $bestDatum,
                ':onsk'    => $onskDatum,
                ':berakn'  => $beraknat,
                ':status'  => $status,
                ':prio'    => $prioritet,
                ':not'     => $notering ?: null,
            ]);

            $this->sendSuccess([
                'id'                     => (int)$this->pdo->lastInsertId(),
                'beraknat_leveransdatum' => $beraknat,
                'status'                 => $status,
            ]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::skapaOrder: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=uppdatera-order (POST)
    // ================================================================

    private function uppdateraOrder(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST kravs');
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->sendError('Ogiltig JSON-data');
                return;
            }

            $id     = (int)($input['id'] ?? 0);
            $status = trim($input['status'] ?? '');

            if (!$id || !in_array($status, ['planerad','i_produktion','levererad','forsenad'], true)) {
                $this->sendError('Ogiltigt id eller status');
                return;
            }

            $stmt = $this->pdo->prepare("
                UPDATE kundordrar SET status = :status WHERE id = :id
            ");
            $stmt->execute([':status' => $status, ':id' => $id]);

            $this->sendSuccess(['updated' => $stmt->rowCount() > 0]);
        } catch (\PDOException $e) {
            error_log('LeveransplaneringController::uppdateraOrder: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ensureTables — skapa tabeller om de saknas
    // ================================================================

    private function ensureTables(): void {
        try {
            $this->pdo->beginTransaction();

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `kundordrar` (
                    `id`                     INT AUTO_INCREMENT PRIMARY KEY,
                    `kundnamn`               VARCHAR(255) NOT NULL,
                    `antal_ibc`              INT NOT NULL DEFAULT 0,
                    `bestallningsdatum`      DATE NOT NULL,
                    `onskat_leveransdatum`   DATE NOT NULL,
                    `beraknat_leveransdatum` DATE DEFAULT NULL,
                    `status`                 ENUM('planerad','i_produktion','levererad','forsenad') NOT NULL DEFAULT 'planerad',
                    `prioritet`              INT NOT NULL DEFAULT 5,
                    `notering`               TEXT DEFAULT NULL,
                    `skapad_datum`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `uppdaterad_datum`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `produktionskapacitet_config` (
                    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
                    `kapacitet_per_dag`         INT NOT NULL DEFAULT 80,
                    `planerade_underhallsdagar`  TEXT DEFAULT NULL,
                    `buffer_procent`            INT NOT NULL DEFAULT 10,
                    `uppdaterad_datum`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed config om tom
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM produktionskapacitet_config")->fetchColumn();
            if ($count === 0) {
                $this->pdo->exec("
                    INSERT INTO produktionskapacitet_config (kapacitet_per_dag, planerade_underhallsdagar, buffer_procent)
                    VALUES (80, '[\"2026-03-20\",\"2026-04-03\",\"2026-04-17\"]', 10)
                ");
            }

            // Seed ordrar om tom
            $orderCount = (int)$this->pdo->query("SELECT COUNT(*) FROM kundordrar")->fetchColumn();
            if ($orderCount === 0) {
                $this->pdo->exec("
                    INSERT INTO kundordrar (kundnamn, antal_ibc, bestallningsdatum, onskat_leveransdatum, beraknat_leveransdatum, status, prioritet, notering) VALUES
                    ('BASF Ludwigshafen',     120, '2026-02-15', '2026-03-20', '2026-03-18', 'i_produktion', 1, 'Prioriterad kund, express'),
                    ('Brenntag Nordic',        80, '2026-02-20', '2026-03-25', '2026-03-24', 'i_produktion', 3, NULL),
                    ('Perstorp Specialty',     60, '2026-03-01', '2026-03-28', '2026-03-27', 'planerad',      5, NULL),
                    ('AkzoNobel Stenungsund', 150, '2026-03-02', '2026-04-01', '2026-04-05', 'forsenad',      2, 'Stor order, kapacitetsbrist'),
                    ('Borealis AB',            45, '2026-03-05', '2026-03-30', '2026-03-29', 'planerad',      4, NULL),
                    ('Nouryon Gothenburg',     90, '2026-03-08', '2026-04-05', '2026-04-04', 'planerad',      3, NULL),
                    ('Clariant Nordics',       70, '2026-02-10', '2026-03-10', '2026-03-10', 'levererad',     5, 'Levererad i tid'),
                    ('Evonik Industries',     200, '2026-03-10', '2026-04-15', '2026-04-20', 'forsenad',      1, 'Mycket stor order, kan bli sen'),
                    ('Kemira OY',              55, '2026-03-01', '2026-03-22', '2026-03-22', 'levererad',     4, NULL),
                    ('Solvay Belgium',        100, '2026-03-09', '2026-04-10', '2026-04-09', 'planerad',      2, 'Ny kund, viktig forsta leverans')
                ");
            }

            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('LeveransplaneringController::ensureTables: ' . $e->getMessage());
        }
    }
}
