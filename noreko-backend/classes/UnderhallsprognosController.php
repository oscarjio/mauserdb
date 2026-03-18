<?php
/**
 * UnderhallsprognosController.php
 * Underhållsprognos — förutser nästa underhåll baserat på scheman och historik.
 *
 * Endpoints via ?action=underhallsprognos&run=XXX:
 *   run=overview
 *       Oversiktskort: totalt antal komponenter, nästa underhåll, försenade.
 *
 *   run=schedule
 *       Fullständigt underhållsschema: komponent, senaste underhåll, intervall,
 *       nästa planerade, status (ok/snart/forsenat), dagar kvar, progress %.
 *
 *   run=history&days=30&limit=50
 *       Senaste underhållsinsatser från maintenance_log + underhallslogg.
 *
 * Auth: session krävs (401 om ej inloggad).
 *
 * Status-definitioner:
 *   ok      — mer än 7 dagar kvar
 *   snart   — 0-7 dagar kvar
 *   forsenat — försenat (negativa dagar kvar)
 *
 * Tabeller: underhall_scheman, underhall_komponenter, maintenance_log, underhallslogg
 */
class UnderhallsprognosController {
    private $pdo;

    // Antal dagar innan förfallodatum som räknas som "snart"
    private const VARNING_DAGAR = 7;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'overview':  $this->getOverview();  break;
            case 'schedule':  $this->getSchedule();  break;
            case 'history':   $this->getHistory();   break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
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

    /**
     * Beräknar nästa underhållsdatum baserat på senaste underhåll och intervall.
     * Returnerar null om senaste_underhall är null.
     */
    private function beraknaNextDatum(?string $senasteUnderhall, int $intervallDagar): ?string {
        if ($senasteUnderhall === null) {
            return null;
        }
        $ts = strtotime($senasteUnderhall);
        if ($ts === false) return null;
        return date('Y-m-d H:i:s', $ts + ($intervallDagar * 86400));
    }

    /**
     * Beräknar dagar kvar till nästa underhåll.
     * Negativa värden = försenat.
     * null = aldrig underhållit.
     */
    private function dagarKvar(?string $nextDatum): ?int {
        if ($nextDatum === null) return null;
        try {
            $diff = (new \DateTime('today'))->diff(new \DateTime($nextDatum));
            return $diff->invert ? -$diff->days : $diff->days;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Statusbedömning baserat på dagar kvar.
     * null (aldrig underhållit) → forsenat
     */
    private function bedomStatus(?int $dagarKvar): string {
        if ($dagarKvar === null) return 'forsenat';
        if ($dagarKvar < 0)     return 'forsenat';
        if ($dagarKvar <= self::VARNING_DAGAR) return 'snart';
        return 'ok';
    }

    /**
     * Progress-procent (0-100) som visar hur stor andel av intervallet
     * som har förflutit sedan senaste underhåll.
     * 100 = exakt vid förfallodatum, >100 = försenat.
     */
    private function beraknaProgress(?string $senasteUnderhall, int $intervallDagar): float {
        if ($senasteUnderhall === null) return 100.0;
        try {
            $elapsed = (new \DateTime($senasteUnderhall))->diff(new \DateTime('today'))->days;
        } catch (\Exception $e) {
            return 100.0;
        }
        $pct = ($elapsed / max(1, $intervallDagar)) * 100;
        return min(round($pct, 1), 100.0);
    }

    // ================================================================
    // TABELLSKAPANDE
    // ================================================================

    private function ensureTablesExist(): void {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `underhall_komponenter` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `namn` VARCHAR(100) NOT NULL,
                `maskin` VARCHAR(100) NOT NULL DEFAULT 'Rebotling',
                `kategori` VARCHAR(50) NOT NULL DEFAULT 'Mekaniskt',
                `beskrivning` TEXT NULL,
                `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
                `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_maskin` (`maskin`),
                KEY `idx_aktiv` (`aktiv`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `underhall_scheman` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `komponent_id` INT NOT NULL,
                `intervall_dagar` INT NOT NULL DEFAULT 30,
                `senaste_underhall` DATETIME NULL,
                `nasta_planerat` DATETIME NULL,
                `ansvarig` VARCHAR(100) NULL,
                `noteringar` TEXT NULL,
                `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
                `skapad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `uppdaterad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_komponent_id` (`komponent_id`),
                KEY `idx_senaste_underhall` (`senaste_underhall`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Seed standarddata om tabellerna är tomma
            $countKomp = (int)$this->pdo->query("SELECT COUNT(*) FROM underhall_komponenter")->fetchColumn();
            if ($countKomp === 0) {
                $this->seedStandarddata();
            }
        } catch (\PDOException $e) {
            error_log('UnderhallsprognosController::ensureTablesExist: ' . $e->getMessage());
        }
    }

    private function seedStandarddata(): void {
        try {
            $komponenter = [
                [1,  'Huvud-pump',         'Rebotling',            'Hydraulik',    'Primär hydraulpump i rebotling-linjen'],
                [2,  'Transportband',      'Rebotling',            'Mekaniskt',    'Transportband för IBC-förflyttning'],
                [3,  'Hydraulsystem',      'Rebotling',            'Hydraulik',    'Hydraulsystem inkl filter och fluid'],
                [4,  'Elektromotor',       'Rebotling',            'Elektriskt',   'Drivmotor till rebotling-linjen'],
                [5,  'Luftfilter',         'Rebotling',            'Pneumatik',    'Pneumatiskt luftfilter'],
                [6,  'Säkerhetsventil',    'Rebotling',            'Pneumatik',    'Övertrycksventil i pneumatiksystemet'],
                [7,  'Smörjsystem',        'Rebotling',            'Mekaniskt',    'Automatiskt smörjsystem'],
                [8,  'PLC-backup',         'Rebotling',            'Elektriskt',   'Säkerhetskopiering av PLC-program'],
                [9,  'Högtryckstvätt',     'Tvättlinje',           'Rengöring',    'Högtrycksaggregat för IBC-tvätt'],
                [10, 'Rörledningar',       'Tvättlinje',           'Mekaniskt',    'Tvättlinjens rörsystem och kopplingar'],
                [11, 'Sagband',            'Såglinje',             'Mekaniskt',    'Sagblad och spanningsanordning'],
                [12, 'Kalibreringspunkt',  'Klassificeringslinje', 'Kalibrering',  'Vikt- och dimensionskalibrering'],
            ];
            $stmtK = $this->pdo->prepare(
                "INSERT IGNORE INTO underhall_komponenter (id, namn, maskin, kategori, beskrivning) VALUES (?,?,?,?,?)"
            );
            foreach ($komponenter as $k) {
                $stmtK->execute($k);
            }

            $scheman = [
                [1,  1,  90,  75],
                [2,  2,  30,  28],
                [3,  3,  180, 200],
                [4,  4,  365, 300],
                [5,  5,  14,  10],
                [6,  6,  30,  35],
                [7,  7,  7,   5],
                [8,  8,  90,  20],
                [9,  9,  30,  32],
                [10, 10, 180, 50],
                [11, 11, 14,  16],
                [12, 12, 90,  45],
            ];
            $stmtS = $this->pdo->prepare(
                "INSERT IGNORE INTO underhall_scheman
                 (id, komponent_id, intervall_dagar, senaste_underhall, ansvarig)
                 VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY), 'Tekniker')"
            );
            foreach ($scheman as $s) {
                $stmtS->execute($s);
            }
        } catch (\PDOException $e) {
            error_log('UnderhallsprognosController::seedStandarddata: ' . $e->getMessage());
        }
    }

    // ================================================================
    // run=overview
    // ================================================================

    private function getOverview(): void {
        try {
            // Hämta alla aktiva scheman
            $stmt = $this->pdo->query(
                "SELECT s.intervall_dagar,
                        COALESCE(s.nasta_planerat, DATE_ADD(COALESCE(s.senaste_underhall, NOW()), INTERVAL s.intervall_dagar DAY)) AS nasta_datum,
                        s.senaste_underhall
                 FROM underhall_scheman s
                 JOIN underhall_komponenter k ON s.komponent_id = k.id
                 WHERE s.aktiv = 1 AND k.aktiv = 1"
            );
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalt   = count($rader);
            $forsenat = 0;
            $snart    = 0;
            $ok       = 0;
            $nastaLista = [];

            foreach ($rader as $rad) {
                $nextDat  = $rad['nasta_datum'];
                $dKvar    = $this->dagarKvar($nextDat);
                $status   = $this->bedomStatus($dKvar);

                if ($status === 'forsenat') $forsenat++;
                elseif ($status === 'snart') $snart++;
                else $ok++;

                if ($nextDat !== null) {
                    $nastaLista[] = $nextDat;
                }
            }

            // Närmaste kommande underhåll
            $nastaDatum = null;
            if (!empty($nastaLista)) {
                sort($nastaLista);
                foreach ($nastaLista as $d) {
                    if (strtotime($d) >= strtotime('today')) {
                        $nastaDatum = $d;
                        break;
                    }
                }
                if ($nastaDatum === null) {
                    $nastaDatum = end($nastaLista);
                }
            }

            $this->sendSuccess([
                'totalt_komponenter'   => $totalt,
                'forsenade'            => $forsenat,
                'snart_forfaller'      => $snart,
                'ok'                   => $ok,
                'nasta_underhall_datum'=> $nastaDatum,
            ]);

        } catch (\Exception $e) {
            error_log('UnderhallsprognosController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // ================================================================
    // run=schedule
    // ================================================================

    private function getSchedule(): void {
        try {
            $stmt = $this->pdo->query(
                "SELECT k.id AS komponent_id,
                        k.namn AS komponent,
                        k.maskin,
                        k.kategori,
                        s.id AS schema_id,
                        s.intervall_dagar,
                        s.senaste_underhall,
                        s.nasta_planerat,
                        s.ansvarig,
                        s.noteringar
                 FROM underhall_scheman s
                 JOIN underhall_komponenter k ON s.komponent_id = k.id
                 WHERE s.aktiv = 1 AND k.aktiv = 1
                 ORDER BY
                   COALESCE(s.nasta_planerat,
                     DATE_ADD(COALESCE(s.senaste_underhall, '1970-01-01'), INTERVAL s.intervall_dagar DAY)
                   ) ASC"
            );
            $rader = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $schema = [];
            foreach ($rader as $rad) {
                $senasteUnderhall = $rad['senaste_underhall'];
                $intervallDagar   = (int)$rad['intervall_dagar'];

                // Nästa datum: manuell override > beräknat
                $nextDatum = $rad['nasta_planerat']
                    ?? $this->beraknaNextDatum($senasteUnderhall, $intervallDagar);

                $dKvar    = $this->dagarKvar($nextDatum);
                $status   = $this->bedomStatus($dKvar);
                $progress = $this->beraknaProgress($senasteUnderhall, $intervallDagar);

                $schema[] = [
                    'komponent_id'     => (int)$rad['komponent_id'],
                    'schema_id'        => (int)$rad['schema_id'],
                    'komponent'        => $rad['komponent'],
                    'maskin'           => $rad['maskin'],
                    'kategori'         => $rad['kategori'],
                    'intervall_dagar'  => $intervallDagar,
                    'senaste_underhall'=> $senasteUnderhall,
                    'nasta_datum'      => $nextDatum,
                    'dagar_kvar'       => $dKvar,
                    'status'           => $status,
                    'progress_pct'     => $progress,
                    'ansvarig'         => $rad['ansvarig'],
                    'noteringar'       => $rad['noteringar'],
                    'aldrig_underhalls'=> ($senasteUnderhall === null),
                ];
            }

            $this->sendSuccess([
                'schema'    => $schema,
                'totalt'    => count($schema),
                'forsenade' => count(array_filter($schema, fn($r) => $r['status'] === 'forsenat')),
                'snart'     => count(array_filter($schema, fn($r) => $r['status'] === 'snart')),
                'ok'        => count(array_filter($schema, fn($r) => $r['status'] === 'ok')),
            ]);

        } catch (\Exception $e) {
            error_log('UnderhallsprognosController::getSchedule: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta underhållsschema', 500);
        }
    }

    // ================================================================
    // run=history
    // ================================================================

    private function getHistory(): void {
        $days  = max(1, min(365, (int)($_GET['days'] ?? 90)));
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

        try {
            $poster = [];

            // --- Källa 1: maintenance_log ---
            try {
                $stmtML = $this->pdo->prepare(
                    "SELECT
                        ml.id,
                        ml.title AS titel,
                        ml.line AS maskin,
                        ml.maintenance_type AS typ,
                        ml.start_time AS datum,
                        ml.duration_minutes AS varaktighet_min,
                        ml.performed_by AS utforare,
                        ml.description AS noteringar,
                        ml.status,
                        'maintenance_log' AS kalla
                     FROM maintenance_log ml
                     WHERE ml.start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                       AND ml.status IN ('klart', 'planerat')
                     ORDER BY ml.start_time DESC
                     LIMIT ?"
                );
                $stmtML->execute([$days, $limit]);
                foreach ($stmtML->fetchAll(\PDO::FETCH_ASSOC) as $rad) {
                    $poster[] = [
                        'id'             => (int)$rad['id'],
                        'titel'          => $rad['titel'],
                        'maskin'         => $rad['maskin'],
                        'typ'            => $rad['typ'],
                        'datum'          => $rad['datum'],
                        'varaktighet_min'=> $rad['varaktighet_min'] !== null ? (int)$rad['varaktighet_min'] : null,
                        'utforare'       => $rad['utforare'],
                        'noteringar'     => $rad['noteringar'],
                        'status'         => $rad['status'],
                        'kalla'          => 'maintenance_log',
                    ];
                }
            } catch (\PDOException $e) {
                error_log('UnderhallsprognosController::history (maintenance_log): ' . $e->getMessage());
            }

            // --- Källa 2: underhallslogg ---
            try {
                $stmtUL = $this->pdo->prepare(
                    "SELECT
                        u.id,
                        u.kategori AS titel,
                        u.maskin,
                        u.typ,
                        u.created_at AS datum,
                        u.varaktighet_min,
                        usr.username AS utforare,
                        u.kommentar AS noteringar,
                        'klart' AS status,
                        'underhallslogg' AS kalla
                     FROM underhallslogg u
                     LEFT JOIN users usr ON u.user_id = usr.id
                     WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                     ORDER BY u.created_at DESC
                     LIMIT ?"
                );
                $stmtUL->execute([$days, $limit]);
                foreach ($stmtUL->fetchAll(\PDO::FETCH_ASSOC) as $rad) {
                    $poster[] = [
                        'id'             => (int)$rad['id'],
                        'titel'          => $rad['titel'],
                        'maskin'         => $rad['maskin'],
                        'typ'            => $rad['typ'],
                        'datum'          => $rad['datum'],
                        'varaktighet_min'=> (int)$rad['varaktighet_min'],
                        'utforare'       => $rad['utforare'],
                        'noteringar'     => $rad['noteringar'],
                        'status'         => $rad['status'],
                        'kalla'          => 'underhallslogg',
                    ];
                }
            } catch (\PDOException $e) {
                error_log('UnderhallsprognosController::history (underhallslogg): ' . $e->getMessage());
            }

            // Sortera kombinerat resultat nyast först
            usort($poster, function($a, $b) {
                return strcmp($b['datum'] ?? '', $a['datum'] ?? '');
            });

            // Begränsa totalt antal poster
            $poster = array_slice($poster, 0, $limit);

            $this->sendSuccess([
                'poster' => $poster,
                'totalt' => count($poster),
                'dagar'  => $days,
            ]);

        } catch (\Exception $e) {
            error_log('UnderhallsprognosController::getHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta historik', 500);
        }
    }
}
