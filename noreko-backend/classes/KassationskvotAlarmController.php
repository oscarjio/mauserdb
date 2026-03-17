<?php
/**
 * KassationskvotAlarmController.php
 * Kassationskvot-alarm — automatisk overvakning och varning nar kassationsgraden overstiger troskelvarden.
 *
 * Endpoints via ?action=kassationskvotalarm&run=XXX:
 *   GET  run=aktuell-kvot    — kassationsgrad senaste timmen, skiftet, dagen
 *   GET  run=alarm-historik  — perioder da kvoten oversteg troskelvarde
 *   GET  run=troskel-hamta   — hamta nuvarande installningar
 *   POST run=troskel-spara   — spara nya troskelvarden
 *   GET  run=timvis-trend    — kassationsgrad per timme senaste 24h
 *   GET  run=per-skift       — kassationsgrad per skift senaste 7 dagar
 *   GET  run=top-orsaker     — vanligaste kassationsorsaker vid alarm
 *
 * Tabeller:
 *   rebotling_ibc                 (ibc_ok, ibc_ej_ok, datum, skiftraknare)
 *   kassationsregistrering        (id, datum, skiftraknare, orsak_id, antal, created_at)
 *   kassationsorsak_typer         (id, namn, aktiv)
 *   rebotling_kassationsalarminst (id, varning_procent, alarm_procent, skapad_av, skapad_datum)
 *
 * Skiftdefinitioner: Dag 06-14, Kvall 14-22, Natt 22-06
 */
class KassationskvotAlarmController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (session_status() === PHP_SESSION_NONE) {
            if ($method === 'GET') {
                session_start(['read_and_close' => true]);
            } else {
                session_start();
            }
        }

        $run = trim($_GET['run'] ?? '');

        if ($method === 'POST') {
            if ($run === 'troskel-spara') {
                $this->sparaTroskel();
                return;
            }
            $this->sendError('Ogiltig POST-endpoint');
            return;
        }

        switch ($run) {
            case 'aktuell-kvot':   $this->getAktuellKvot();   break;
            case 'alarm-historik': $this->getAlarmHistorik();  break;
            case 'troskel-hamta':  $this->getTroskel();        break;
            case 'timvis-trend':   $this->getTimvisTrend();    break;
            case 'per-skift':      $this->getPerSkift();       break;
            case 'top-orsaker':    $this->getTopOrsaker();     break;
            default:               $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
    // ================================================================

    private function ensureTableExists(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `rebotling_kassationsalarminst` (
                  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `varning_procent` DECIMAL(5,2) NOT NULL DEFAULT 3.00,
                  `alarm_procent`   DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                  `skapad_av`       INT UNSIGNED NULL,
                  `skapad_datum`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  INDEX `idx_skapad_datum` (`skapad_datum`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            // Satt in standardinstallning om tabellen ar tom
            $cnt = (int)$this->pdo->query("SELECT COUNT(*) FROM `rebotling_kassationsalarminst`")->fetchColumn();
            if ($cnt === 0) {
                $this->pdo->exec("INSERT INTO `rebotling_kassationsalarminst` (`varning_procent`, `alarm_procent`) VALUES (3.00, 5.00)");
            }
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::ensureTableExists: ' . $e->getMessage());
        }
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

    /**
     * Hamta senaste troskelinstallning
     */
    private function getTroskelInstallning(): array {
        try {
            $stmt = $this->pdo->query(
                "SELECT varning_procent, alarm_procent FROM rebotling_kassationsalarminst ORDER BY id DESC LIMIT 1"
            );
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'varning_procent' => (float)$row['varning_procent'],
                    'alarm_procent'   => (float)$row['alarm_procent'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::getTroskelInstallning: ' . $e->getMessage());
        }
        return ['varning_procent' => 3.0, 'alarm_procent' => 5.0];
    }

    /**
     * Berakna kassationsgrad (%) fran rebotling_ibc for given WHERE-klausul
     * Anvander MAX() per skiftraknare (kumulativa PLC-varden).
     */
    private function beraknaKvot(string $where, array $params): array {
        try {
            $sql = "
                SELECT
                    COALESCE(SUM(shift_ok), 0)    AS totalt_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS totalt_ej_ok
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE $where
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            $ok    = (int)($row['totalt_ok']    ?? 0);
            $ejOk  = (int)($row['totalt_ej_ok'] ?? 0);
            $totalt = $ok + $ejOk;
            $kvot  = $totalt > 0 ? round($ejOk / $totalt * 100, 2) : 0.0;
            return [
                'godkanda'  => $ok,
                'kasserade' => $ejOk,
                'totalt'    => $totalt,
                'kvot_pct'  => $kvot,
            ];
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::beraknaKvot: ' . $e->getMessage());
            return ['godkanda' => 0, 'kasserade' => 0, 'totalt' => 0, 'kvot_pct' => 0.0];
        }
    }

    /**
     * Bestam skiftnamn (dag/kvall/natt) fran klockslag
     */
    private function getSkiftNamn(int $hour): string {
        if ($hour >= 6 && $hour < 14)  return 'dag';
        if ($hour >= 14 && $hour < 22) return 'kvall';
        return 'natt';
    }

    // ================================================================
    // ENDPOINT: aktuell-kvot
    // ================================================================

    private function getAktuellKvot(): void {
        $troskel = $this->getTroskelInstallning();
        $now     = new \DateTime();
        $hour    = (int)$now->format('H');
        $today   = $now->format('Y-m-d');

        // --- Senaste timmen (sista 60 minuter) ---
        $senastTimmeFrom = (clone $now)->modify('-1 hour')->format('Y-m-d H:i:s');
        $timme = $this->beraknaKvot(
            "datum >= :from_dt AND datum <= :to_dt",
            [':from_dt' => $senastTimmeFrom, ':to_dt' => $now->format('Y-m-d H:i:s')]
        );

        // --- Aktuellt skift ---
        $skiftNamn = $this->getSkiftNamn($hour);
        if ($skiftNamn === 'dag') {
            $skiftFrom = $today . ' 06:00:00';
            $skiftTo   = $today . ' 13:59:59';
        } elseif ($skiftNamn === 'kvall') {
            $skiftFrom = $today . ' 14:00:00';
            $skiftTo   = $today . ' 21:59:59';
        } else {
            // Natt: 22:00 igår – 05:59 idag, eller 22:00 idag – nu om timmen >= 22
            if ($hour < 6) {
                $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
                $skiftFrom = $yesterday . ' 22:00:00';
                $skiftTo   = $today . ' 05:59:59';
            } else {
                $skiftFrom = $today . ' 22:00:00';
                $skiftTo   = $today . ' 23:59:59';
            }
        }
        $skift = $this->beraknaKvot(
            "datum >= :from_dt AND datum <= :to_dt",
            [':from_dt' => $skiftFrom, ':to_dt' => $skiftTo]
        );

        // --- Idag (00:00 – nu) ---
        $dag = $this->beraknaKvot(
            "DATE(datum) = :today",
            [':today' => $today]
        );

        // Statusfarg
        $fargFn = function(float $kvot) use ($troskel): string {
            if ($kvot >= $troskel['alarm_procent'])   return 'rod';
            if ($kvot >= $troskel['varning_procent']) return 'gul';
            return 'gron';
        };

        $this->sendSuccess([
            'troskel'      => $troskel,
            'senaste_timme' => array_merge($timme, [
                'period' => 'Senaste timmen',
                'farg'   => $fargFn($timme['kvot_pct']),
            ]),
            'aktuellt_skift' => array_merge($skift, [
                'period'     => 'Aktuellt skift (' . $skiftNamn . ')',
                'skift_namn' => $skiftNamn,
                'fran'       => $skiftFrom,
                'till'       => $skiftTo,
                'farg'       => $fargFn($skift['kvot_pct']),
            ]),
            'idag' => array_merge($dag, [
                'period' => 'Idag (' . $today . ')',
                'farg'   => $fargFn($dag['kvot_pct']),
            ]),
        ]);
    }

    // ================================================================
    // ENDPOINT: alarm-historik
    // ================================================================

    private function getAlarmHistorik(): void {
        $dagar   = max(1, min(90, intval($_GET['dagar'] ?? 30)));
        $troskel = $this->getTroskelInstallning();

        try {
            // Hamta kassationsgrad per dag och skift
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)      AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok,
                    MIN(datum)       AS skift_start,
                    MAX(datum)       AS skift_slut
                FROM rebotling_ibc
                WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
                ORDER BY dag DESC, skiftraknare DESC
            ");
            $stmt->execute([':dagar' => $dagar]);
            $rader = $stmt->fetchAll();

            $historik = [];
            foreach ($rader as $rad) {
                $ok    = (int)$rad['shift_ok'];
                $ejOk  = (int)$rad['shift_ej_ok'];
                $totalt = $ok + $ejOk;
                if ($totalt === 0) continue;
                $kvot = round($ejOk / $totalt * 100, 2);
                if ($kvot < $troskel['varning_procent']) continue;

                // Bestam skift fran starttid
                $startHour = (int)date('H', strtotime($rad['skift_start']));
                $skiftNamn = $this->getSkiftNamn($startHour);
                $status    = $kvot >= $troskel['alarm_procent'] ? 'alarm' : 'varning';

                $historik[] = [
                    'datum'        => $rad['dag'],
                    'skiftraknare' => $rad['skiftraknare'],
                    'skift_namn'   => $skiftNamn,
                    'kvot_pct'     => $kvot,
                    'kasserade'    => $ejOk,
                    'totalt'       => $totalt,
                    'troskel_pct'  => $status === 'alarm' ? $troskel['alarm_procent'] : $troskel['varning_procent'],
                    'status'       => $status,
                    'skift_start'  => $rad['skift_start'],
                    'skift_slut'   => $rad['skift_slut'],
                ];
            }

            $this->sendSuccess([
                'troskel'  => $troskel,
                'historik' => $historik,
                'antal'    => count($historik),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::getAlarmHistorik: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: troskel-hamta
    // ================================================================

    private function getTroskel(): void {
        $t = $this->getTroskelInstallning();
        $this->sendSuccess($t);
    }

    // ================================================================
    // ENDPOINT: troskel-spara (POST)
    // ================================================================

    private function sparaTroskel(): void {
        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $varning = isset($body['varning_procent']) ? (float)$body['varning_procent'] : null;
        $alarm   = isset($body['alarm_procent'])   ? (float)$body['alarm_procent']   : null;

        if ($varning === null || $alarm === null) {
            $this->sendError('varning_procent och alarm_procent kravs');
            return;
        }
        if ($varning <= 0 || $varning >= 100 || $alarm <= 0 || $alarm >= 100) {
            $this->sendError('Procent maste vara mellan 0 och 100');
            return;
        }
        if ($varning >= $alarm) {
            $this->sendError('Varningstroskeln maste vara lagre an alarmtroskeln');
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO rebotling_kassationsalarminst (varning_procent, alarm_procent, skapad_av) VALUES (:v, :a, :uid)"
            );
            $stmt->execute([
                ':v'   => $varning,
                ':a'   => $alarm,
                ':uid' => (int)$_SESSION['user_id'],
            ]);
            $this->sendSuccess(['varning_procent' => $varning, 'alarm_procent' => $alarm]);
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::sparaTroskel: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: timvis-trend
    // ================================================================

    private function getTimvisTrend(): void {
        $troskel = $this->getTroskelInstallning();

        try {
            // Aggregera per timme (kumulativa varden — ta MAX per skiftraknare per timme)
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m-%d %H:00') AS timme,
                    SUM(shift_ok)    AS totalt_ok,
                    SUM(shift_ej_ok) AS totalt_ej_ok
                FROM (
                    SELECT
                        datum,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= NOW() - INTERVAL 24 HOUR
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE_FORMAT(datum, '%Y-%m-%d %H'), skiftraknare
                ) AS per_timme_skift
                GROUP BY DATE_FORMAT(datum, '%Y-%m-%d %H:00')
                ORDER BY timme ASC
            ");
            $stmt->execute();
            $rader = $stmt->fetchAll();

            $trend = [];
            foreach ($rader as $rad) {
                $ok    = (int)$rad['totalt_ok'];
                $ejOk  = (int)$rad['totalt_ej_ok'];
                $totalt = $ok + $ejOk;
                $kvot   = $totalt > 0 ? round($ejOk / $totalt * 100, 2) : 0.0;
                $farg   = 'gron';
                if ($kvot >= $troskel['alarm_procent'])   $farg = 'rod';
                elseif ($kvot >= $troskel['varning_procent']) $farg = 'gul';

                $trend[] = [
                    'timme'     => $rad['timme'],
                    'kvot_pct'  => $kvot,
                    'kasserade' => $ejOk,
                    'totalt'    => $totalt,
                    'farg'      => $farg,
                ];
            }

            $this->sendSuccess([
                'troskel' => $troskel,
                'trend'   => $trend,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::getTimvisTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: per-skift
    // ================================================================

    private function getPerSkift(): void {
        $troskel = $this->getTroskelInstallning();

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum)      AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok,
                    MIN(datum)       AS skift_start
                FROM rebotling_ibc
                WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
                ORDER BY dag ASC, skiftraknare ASC
            ");
            $stmt->execute();
            $rader = $stmt->fetchAll();

            // Gruppera per dag
            $dagData = [];
            foreach ($rader as $rad) {
                $dag      = $rad['dag'];
                $ok       = (int)$rad['shift_ok'];
                $ejOk     = (int)$rad['shift_ej_ok'];
                $totalt   = $ok + $ejOk;
                $kvot     = $totalt > 0 ? round($ejOk / $totalt * 100, 2) : 0.0;
                $startHour = (int)date('H', strtotime($rad['skift_start']));
                $skiftNamn = $this->getSkiftNamn($startHour);
                $farg = 'gron';
                if ($kvot >= $troskel['alarm_procent'])   $farg = 'rod';
                elseif ($kvot >= $troskel['varning_procent']) $farg = 'gul';

                if (!isset($dagData[$dag])) {
                    $dagData[$dag] = [
                        'datum' => $dag,
                        'dag'   => null,
                        'kvall' => null,
                        'natt'  => null,
                    ];
                }
                $dagData[$dag][$skiftNamn] = [
                    'kvot_pct'  => $kvot,
                    'kasserade' => $ejOk,
                    'totalt'    => $totalt,
                    'farg'      => $farg,
                ];
            }

            $this->sendSuccess([
                'troskel' => $troskel,
                'dagar'   => array_values($dagData),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::getPerSkift: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: top-orsaker
    // ================================================================

    private function getTopOrsaker(): void {
        $troskel = $this->getTroskelInstallning();
        $dagar   = max(1, min(90, intval($_GET['dagar'] ?? 30)));

        try {
            // Hamta kassationsregistreringar kopplade till alarm-perioder
            // (skiftraknare dar kvoten overstiger varningstroskeln)
            $stmtAlarmSkift = $this->pdo->prepare("
                SELECT skiftraknare, DATE(datum) AS dag
                FROM (
                    SELECT
                        skiftraknare,
                        datum,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                WHERE (shift_ej_ok / NULLIF(shift_ok + shift_ej_ok, 0) * 100) >= :troskel
            ");
            $stmtAlarmSkift->execute([
                ':dagar'   => $dagar,
                ':troskel' => $troskel['varning_procent'],
            ]);
            $alarmSkift = $stmtAlarmSkift->fetchAll();

            if (empty($alarmSkift)) {
                $this->sendSuccess(['orsaker' => [], 'troskel' => $troskel]);
                return;
            }

            // Bygg IN-lista av skiftraknare
            $skiftIds = array_unique(array_column($alarmSkift, 'skiftraknare'));
            $placeholders = implode(',', array_fill(0, count($skiftIds), '?'));

            $stmtOrsaker = $this->pdo->prepare("
                SELECT
                    COALESCE(kot.namn, 'Okand') AS orsak,
                    SUM(kr.antal)               AS antal
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kot ON kot.id = kr.orsak_id
                WHERE kr.skiftraknare IN ($placeholders)
                  AND DATE(kr.datum) >= DATE_SUB(CURDATE(), INTERVAL {$dagar} DAY)
                GROUP BY kr.orsak_id, kot.namn
                ORDER BY antal DESC
                LIMIT 5
            ");
            $stmtOrsaker->execute($skiftIds);
            $orsaker = $stmtOrsaker->fetchAll();

            $this->sendSuccess([
                'troskel'        => $troskel,
                'alarm_skift_antal' => count($alarmSkift),
                'orsaker'        => array_map(fn($r) => [
                    'orsak' => $r['orsak'],
                    'antal' => (int)$r['antal'],
                ], $orsaker),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationskvotAlarmController::getTopOrsaker: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
