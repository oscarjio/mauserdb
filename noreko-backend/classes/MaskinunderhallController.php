<?php

/**
 * MaskinunderhallController
 * Maskinunderhåll — serviceintervall och logg för IBC-rebotling-linje.
 *
 * Endpoints via ?action=maskinunderhall&run=XXX:
 *
 *   GET  run=overview        — KPI:er: antal maskiner, kommande service, försenade, snitt intervall
 *   GET  run=machines        — Lista maskiner med senaste service, nästa planerad, dagar kvar, status
 *   GET  run=machine-history — (?maskin_id=X) Servicehistorik för en specifik maskin
 *   GET  run=timeline        — Data för tidslinje-diagram
 *   POST run=add-service     — Registrera genomförd service
 *   POST run=add-machine     — Registrera ny maskin
 */
class MaskinunderhallController {
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
                case 'machines':        $this->getMachines();       break;
                case 'machine-history': $this->getMachineHistory(); break;
                case 'timeline':        $this->getTimeline();       break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'add-service':
                    $this->requireLogin();
                    $this->addService();
                    break;
                case 'add-machine':
                    $this->requireLogin();
                    $this->addMachine();
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

    private function ensureTables(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'maskin_register'"
            )->fetchColumn();
            if (!$check) {
                $migrationPath = __DIR__ . '/../migrations/2026-03-12_maskinunderhall.sql';
                $sql = file_get_contents($migrationPath);
                if ($sql === false) {
                    error_log('MaskinunderhallController::ensureTables: kunde inte läsa migrationsfil: ' . $migrationPath);
                } elseif ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::ensureTables: ' . $e->getMessage());
        }
    }

    /**
     * Beräkna status baserat på dagar kvar till nästa service.
     * grön = >7 dagar kvar, gul = 1-7 dagar, röd = försenat (<=0)
     */
    private function beraknaStatus(?int $dagarKvar): string {
        if ($dagarKvar === null) return 'gul';
        if ($dagarKvar <= 0) return 'rod';
        if ($dagarKvar <= 7) return 'gul';
        return 'gron';
    }

    // =========================================================================
    // GET run=overview
    // KPI:er: antal maskiner, kommande 7 dagar, försenade, snitt intervall
    // =========================================================================

    private function getOverview(): void {
        try {
            $today = date('Y-m-d');

            // Antal aktiva maskiner
            $antalStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM maskin_register WHERE aktiv = 1"
            );
            $antalMaskiner = (int)$antalStmt->fetchColumn();

            // Snitt serviceintervall
            $snittStmt = $this->pdo->query(
                "SELECT AVG(service_intervall_dagar) FROM maskin_register WHERE aktiv = 1"
            );
            $snittIntervall = round((float)($snittStmt->fetchColumn() ?? 0), 1);

            // Hämta alla maskiner med senaste service för att beräkna kommande/försenade
            $stmt = $this->pdo->query(
                "SELECT m.id, m.service_intervall_dagar,
                        sl.nasta_planerad_datum,
                        sl.service_datum AS senaste_service
                 FROM maskin_register m
                 LEFT JOIN maskin_service_logg sl ON sl.id = (
                     SELECT id FROM maskin_service_logg
                     WHERE maskin_id = m.id
                     ORDER BY service_datum DESC, id DESC
                     LIMIT 1
                 )
                 WHERE m.aktiv = 1"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $kommandeInomSju = 0;
            $forsenade = 0;

            foreach ($rows as $r) {
                $nastaDatum = $this->beraknaNastaDatum($r);
                if ($nastaDatum) {
                    $diff = (new \DateTime($today))->diff(new \DateTime($nastaDatum));
                    $dagarKvar = $diff->invert ? -$diff->days : $diff->days;
                    if ($dagarKvar <= 0) {
                        $forsenade++;
                    } elseif ($dagarKvar <= 7) {
                        $kommandeInomSju++;
                    }
                } else {
                    // Ingen service registrerad — behandla som försenat
                    $forsenade++;
                }
            }

            $this->sendSuccess([
                'data' => [
                    'antal_maskiner'        => $antalMaskiner,
                    'kommande_inom_sju'     => $kommandeInomSju,
                    'forsenade'             => $forsenade,
                    'snitt_intervall_dagar' => $snittIntervall,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // =========================================================================
    // GET run=machines
    // Lista alla maskiner med servicestatus
    // =========================================================================

    private function getMachines(): void {
        try {
            $today = date('Y-m-d');

            $stmt = $this->pdo->query(
                "SELECT m.id, m.namn, m.beskrivning, m.service_intervall_dagar, m.aktiv, m.created_at,
                        sl.service_datum AS senaste_service,
                        sl.nasta_planerad_datum,
                        sl.utfort_av AS senaste_utfort_av,
                        sl.service_typ AS senaste_typ
                 FROM maskin_register m
                 LEFT JOIN maskin_service_logg sl ON sl.id = (
                     SELECT id FROM maskin_service_logg
                     WHERE maskin_id = m.id
                     ORDER BY service_datum DESC, id DESC
                     LIMIT 1
                 )
                 WHERE m.aktiv = 1
                 ORDER BY m.namn ASC"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $maskiner = [];
            foreach ($rows as $r) {
                $nastaDatum = $this->beraknaNastaDatum($r);
                $dagarKvar = null;
                if ($nastaDatum) {
                    $diff = (new \DateTime($today))->diff(new \DateTime($nastaDatum));
                    $dagarKvar = $diff->invert ? -$diff->days : $diff->days;
                }
                $status = $this->beraknaStatus($dagarKvar);

                $maskiner[] = [
                    'id'                    => (int)$r['id'],
                    'namn'                  => $r['namn'],
                    'beskrivning'           => $r['beskrivning'],
                    'service_intervall_dagar' => (int)$r['service_intervall_dagar'],
                    'senaste_service'       => $r['senaste_service'],
                    'senaste_utfort_av'     => $r['senaste_utfort_av'],
                    'senaste_typ'           => $r['senaste_typ'],
                    'nasta_service'         => $nastaDatum,
                    'dagar_kvar'            => $dagarKvar,
                    'status'                => $status,
                ];
            }

            $this->sendSuccess(['maskiner' => $maskiner]);
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::getMachines: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta maskiner', 500);
        }
    }

    // =========================================================================
    // GET run=machine-history&maskin_id=X
    // Servicehistorik för en specifik maskin
    // =========================================================================

    private function getMachineHistory(): void {
        $maskinId = (int)($_GET['maskin_id'] ?? 0);
        if ($maskinId <= 0) {
            $this->sendError('maskin_id krävs');
            return;
        }

        try {
            // Kontrollera att maskinen finns
            $maskinStmt = $this->pdo->prepare(
                "SELECT id, namn, beskrivning, service_intervall_dagar FROM maskin_register WHERE id = ? AND aktiv = 1"
            );
            $maskinStmt->execute([$maskinId]);
            $maskin = $maskinStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$maskin) {
                $this->sendError('Maskin hittades inte', 404);
                return;
            }

            $stmt = $this->pdo->prepare(
                "SELECT id, maskin_id, service_datum, service_typ, beskrivning, utfort_av,
                        nasta_planerad_datum, created_at
                 FROM maskin_service_logg
                 WHERE maskin_id = ?
                 ORDER BY service_datum DESC, id DESC
                 LIMIT 50"
            );
            $stmt->execute([$maskinId]);
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $historik = [];
            foreach ($rader as $r) {
                $historik[] = [
                    'id'                  => (int)$r['id'],
                    'maskin_id'           => (int)$r['maskin_id'],
                    'service_datum'       => $r['service_datum'],
                    'service_typ'         => $r['service_typ'],
                    'service_typ_label'   => $this->servicetypLabel($r['service_typ']),
                    'beskrivning'         => $r['beskrivning'],
                    'utfort_av'           => $r['utfort_av'],
                    'nasta_planerad_datum'=> $r['nasta_planerad_datum'],
                    'created_at'          => $r['created_at'],
                ];
            }

            $this->sendSuccess([
                'maskin'  => [
                    'id'                    => (int)$maskin['id'],
                    'namn'                  => $maskin['namn'],
                    'beskrivning'           => $maskin['beskrivning'],
                    'service_intervall_dagar' => (int)$maskin['service_intervall_dagar'],
                ],
                'historik' => $historik,
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::getMachineHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta historik', 500);
        }
    }

    // =========================================================================
    // GET run=timeline
    // Data för tidslinje-diagram: dagar sedan service, intervall, status per maskin
    // =========================================================================

    private function getTimeline(): void {
        try {
            $today = date('Y-m-d');

            $stmt = $this->pdo->query(
                "SELECT m.id, m.namn, m.service_intervall_dagar,
                        sl.service_datum AS senaste_service,
                        sl.nasta_planerad_datum
                 FROM maskin_register m
                 LEFT JOIN maskin_service_logg sl ON sl.id = (
                     SELECT id FROM maskin_service_logg
                     WHERE maskin_id = m.id
                     ORDER BY service_datum DESC, id DESC
                     LIMIT 1
                 )
                 WHERE m.aktiv = 1
                 ORDER BY m.namn ASC"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $r) {
                $intervall = (int)$r['service_intervall_dagar'];
                $dagarSedan = null;
                $dagarKvar = null;
                $nastaDatum = $this->beraknaNastaDatum($r);

                if ($r['senaste_service']) {
                    $dagarSedan = (int)(new \DateTime($r['senaste_service']))->diff(new \DateTime($today))->days;
                }
                if ($nastaDatum) {
                    $diff = (new \DateTime($today))->diff(new \DateTime($nastaDatum));
                    $dagarKvar = $diff->invert ? -$diff->days : $diff->days;
                }

                $status = $this->beraknaStatus($dagarKvar);
                // Andel förbrukad av intervallet (0–100+)
                $forbrukadPct = $dagarSedan !== null && $intervall > 0
                    ? min(150, round($dagarSedan / $intervall * 100, 1))
                    : 100;

                $items[] = [
                    'maskin_id'       => (int)$r['id'],
                    'namn'            => $r['namn'],
                    'intervall'       => $intervall,
                    'dagar_sedan'     => $dagarSedan,
                    'dagar_kvar'      => $dagarKvar,
                    'senaste_service' => $r['senaste_service'],
                    'nasta_service'   => $nastaDatum,
                    'forbrukad_pct'   => $forbrukadPct,
                    'status'          => $status,
                ];
            }

            $this->sendSuccess(['items' => $items]);
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::getTimeline: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta tidslinje-data', 500);
        }
    }

    // =========================================================================
    // POST run=add-service
    // Body: { maskin_id, service_datum, service_typ, beskrivning, utfort_av, nasta_planerad_datum }
    // =========================================================================

    private function addService(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $maskinId = (int)($data['maskin_id'] ?? 0);
        if ($maskinId <= 0) {
            $this->sendError('maskin_id krävs');
            return;
        }

        $serviceDatum = trim($data['service_datum'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDatum)) {
            $serviceDatum = date('Y-m-d');
        }

        $serviceTyp = trim($data['service_typ'] ?? 'planerat');
        if (!in_array($serviceTyp, ['planerat', 'akut', 'inspektion'], true)) {
            $serviceTyp = 'planerat';
        }

        $beskrivning = isset($data['beskrivning']) ? mb_substr(strip_tags(trim($data['beskrivning'])), 0, 2000) : null;
        $utfortAv    = isset($data['utfort_av'])   ? mb_substr(strip_tags(trim($data['utfort_av'])), 0, 100)   : $this->currentUsername();

        $nastaPlanerad = trim($data['nasta_planerad_datum'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nastaPlanerad)) {
            $nastaPlanerad = null;
        }

        // Om ingen nästa datum angiven — beräkna automatiskt
        if (!$nastaPlanerad) {
            try {
                $intStmt = $this->pdo->prepare(
                    "SELECT service_intervall_dagar FROM maskin_register WHERE id = ?"
                );
                $intStmt->execute([$maskinId]);
                $intervall = (int)$intStmt->fetchColumn();
                if ($intervall > 0) {
                    $nastaPlanerad = date('Y-m-d', strtotime($serviceDatum . ' +' . $intervall . ' days'));
                }
            } catch (\PDOException $e) {
                error_log('MaskinunderhallController::addService intervall-lookup: ' . $e->getMessage());
            }
        }

        if ($beskrivning && mb_strlen($beskrivning) > 2000) {
            $beskrivning = mb_substr($beskrivning, 0, 2000);
        }
        if ($utfortAv && mb_strlen($utfortAv) > 100) {
            $utfortAv = mb_substr($utfortAv, 0, 100);
        }

        try {
            // Kontrollera att maskinen finns
            $checkStmt = $this->pdo->prepare(
                "SELECT id FROM maskin_register WHERE id = ? AND aktiv = 1"
            );
            $checkStmt->execute([$maskinId]);
            if (!$checkStmt->fetch()) {
                $this->sendError('Maskin hittades inte', 404);
                return;
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO maskin_service_logg
                    (maskin_id, service_datum, service_typ, beskrivning, utfort_av, nasta_planerad_datum)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $maskinId,
                $serviceDatum,
                $serviceTyp,
                $beskrivning ?: null,
                $utfortAv ?: null,
                $nastaPlanerad ?: null,
            ]);

            $newId = (int)$this->pdo->lastInsertId();

            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Service registrerad',
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::addService: ' . $e->getMessage());
            $this->sendError('Kunde inte spara service', 500);
        }
    }

    // =========================================================================
    // POST run=add-machine
    // Body: { namn, beskrivning, service_intervall_dagar }
    // =========================================================================

    private function addMachine(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $namn = strip_tags(trim($data['namn'] ?? ''));
        if (!$namn) {
            $this->sendError('namn krävs');
            return;
        }
        if (mb_strlen($namn) > 100) {
            $namn = mb_substr($namn, 0, 100);
        }

        $beskrivning = isset($data['beskrivning']) ? mb_substr(strip_tags(trim($data['beskrivning'])), 0, 2000) : null;
        $intervall   = max(1, min(3650, (int)($data['service_intervall_dagar'] ?? 90)));

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO maskin_register (namn, beskrivning, service_intervall_dagar, aktiv)
                 VALUES (?, ?, ?, 1)"
            );
            $stmt->execute([$namn, $beskrivning ?: null, $intervall]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Maskin registrerad',
            ]);
        } catch (\PDOException $e) {
            error_log('MaskinunderhallController::addMachine: ' . $e->getMessage());
            $this->sendError('Kunde inte spara maskin', 500);
        }
    }

    // =========================================================================
    // Hjälp
    // =========================================================================

    /**
     * Beräkna nästa planerade servicedatum:
     * Använd nasta_planerad_datum från loggposten om det finns,
     * annars beräkna från senaste_service + service_intervall_dagar.
     */
    private function beraknaNastaDatum(array $row): ?string {
        if (!empty($row['nasta_planerad_datum'])) {
            return $row['nasta_planerad_datum'];
        }
        if (!empty($row['senaste_service'])) {
            $intervall = (int)($row['service_intervall_dagar'] ?? 90);
            return date('Y-m-d', strtotime($row['senaste_service'] . ' +' . $intervall . ' days'));
        }
        return null;
    }

    private function servicetypLabel(string $typ): string {
        switch ($typ) {
            case 'planerat':   return 'Planerat';
            case 'akut':       return 'Akut';
            case 'inspektion': return 'Inspektion';
            default:           return $typ;
        }
    }
}
