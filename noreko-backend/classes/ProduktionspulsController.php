<?php
/**
 * ProduktionspulsController
 *
 * Realtids-ticker for VD — visar vad som hander NU pa rebotling-linjen.
 *
 * Endpoints via ?action=produktionspuls&run=XXX:
 *   - run=latest&limit=50   — senaste IBC:er (bakatkompat)
 *   - run=hourly-stats       — timstatistik + trend (bakatkompat)
 *   - run=pulse&limit=20     — senaste N handelser (IBC + stopp + on/off), kronologiskt, med typ/farg/ikon
 *   - run=live-kpi           — realtids-KPI:er: IBC idag, IBC/h, driftstatus, tid sedan senaste stopp
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, stoppage_log, stopporsak_registreringar
 * Auth: session kravs for pulse/live-kpi (401 om ej inloggad).
 */
class ProduktionspulsController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'latest':
                $this->getLatest();
                break;
            case 'hourly-stats':
                $this->getHourlyStats();
                break;
            case 'pulse':
                $this->requireAuth();
                $this->getPulse();
                break;
            case 'live-kpi':
                $this->requireAuth();
                $this->getLiveKpi();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig run-parameter'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================================================
    // AUTH
    // ================================================================

    private function requireAuth(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Inloggning kravs'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ================================================================
    // ENDPOINT: pulse — kronologisk handelsefeed
    // ================================================================

    /**
     * GET ?action=produktionspuls&run=pulse&limit=20
     * Returnerar senaste N handelser (IBC + stopp + on/off), sorterat kronologiskt.
     * Varje handelse har: type, time, label, detail, color, icon
     */
    private function getPulse(): void {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $events = [];

        // 1. Senaste IBC-registreringar
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    i.id,
                    i.datum,
                    i.ibc_ok,
                    i.ibc_ej_ok,
                    i.bur_ej_ok,
                    o1.name AS operator_namn,
                    p.name AS produkt_namn
                FROM rebotling_ibc i
                LEFT JOIN operators o1 ON i.op1 = o1.number
                LEFT JOIN rebotling_products p ON i.produkt = p.id
                ORDER BY i.datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit * 2, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $kasserad = ((int)($row['ibc_ej_ok'] ?? 0) > 0) || ((int)($row['bur_ej_ok'] ?? 0) > 0);
                $events[] = [
                    'type'   => 'ibc',
                    'time'   => $row['datum'],
                    'label'  => $kasserad ? 'IBC kasserad' : 'IBC producerad',
                    'detail' => ($row['produkt_namn'] ?? 'Okand') . ' — ' . ($row['operator_namn'] ?? 'Okand opertor'),
                    'color'  => $kasserad ? 'danger' : 'success',
                    'icon'   => $kasserad ? 'fas fa-times-circle' : 'fas fa-check-circle',
                ];
            }
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getPulse (ibc): ' . $e->getMessage());
        }

        // 2. Senaste on/off-handelser
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_onoff'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT datum, running
                    FROM rebotling_onoff
                    ORDER BY datum DESC
                    LIMIT :lim
                ");
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $isRunning = (int)($row['running'] ?? 0);
                    if ($isRunning) {
                        $events[] = [
                            'type'   => 'onoff',
                            'time'   => $row['datum'],
                            'label'  => 'Linjen startad',
                            'detail' => 'Rebotling-linjen satt i drift',
                            'color'  => 'success',
                            'icon'   => 'fas fa-play-circle',
                        ];
                    } else {
                        $events[] = [
                            'type'   => 'onoff',
                            'time'   => $row['datum'],
                            'label'  => 'Linjen stoppad',
                            'detail' => 'Rebotling-linjen stannad',
                            'color'  => 'warning',
                            'icon'   => 'fas fa-stop-circle',
                        ];
                    }
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getPulse (onoff): ' . $e->getMessage());
        }

        // 3. Senaste stopporsaker
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT sr.start_time, sr.end_time, COALESCE(sk.namn, 'Okand orsak') AS orsak, sr.kommentar
                    FROM stopporsak_registreringar sr
                    LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                    ORDER BY sr.start_time DESC
                    LIMIT :lim
                ");
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $reason = $row['orsak'] ?? 'Okand orsak';
                    if ($row['kommentar']) {
                        $reason .= ': ' . $row['kommentar'];
                    }
                    $duration = '';
                    if ($row['start_time'] && $row['end_time']) {
                        $mins = round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 60);
                        $duration = " ({$mins} min)";
                    }

                    $events[] = [
                        'type'   => 'stopp',
                        'time'   => $row['start_time'],
                        'label'  => 'Stopp registrerat',
                        'detail' => $reason . $duration,
                        'color'  => 'danger',
                        'icon'   => 'fas fa-exclamation-triangle',
                    ];
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getPulse (stopporsak): ' . $e->getMessage());
        }

        // 4. Fran stoppage_log ocksa
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stoppage_log'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT sl.start_time, sl.end_time, COALESCE(sr.name, 'Okand orsak') AS reason, sl.duration_minutes
                    FROM stoppage_log sl
                    LEFT JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                    WHERE sl.duration_minutes > 0
                    ORDER BY sl.start_time DESC
                    LIMIT :lim
                ");
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $events[] = [
                        'type'   => 'stopp',
                        'time'   => $row['start_time'],
                        'label'  => 'Driftstopp',
                        'detail' => ($row['reason'] ?? 'Okand orsak') . ' (' . (int)$row['duration_minutes'] . ' min)',
                        'color'  => 'danger',
                        'icon'   => 'fas fa-exclamation-triangle',
                    ];
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getPulse (stoppage_log): ' . $e->getMessage());
        }

        // Sortera kronologiskt (nyast forst) och begransar
        usort($events, function ($a, $b) {
            return strcmp($b['time'] ?? '', $a['time'] ?? '');
        });
        $events = array_slice($events, 0, $limit);

        echo json_encode([
            'success'   => true,
            'data'      => array_values($events),
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    // ================================================================
    // ENDPOINT: live-kpi — realtids-KPI:er
    // ================================================================

    /**
     * GET ?action=produktionspuls&run=live-kpi
     * Returnerar:
     *   - ibc_idag: antal IBC producerade idag
     *   - ibc_per_timme: IBC/h senaste timmen
     *   - driftstatus: { running: bool, sedan: datetime|null }
     *   - tid_sedan_senaste_stopp: { minuter: int, senaste_stopp: datetime|null }
     */
    private function getLiveKpi(): void {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

        // 1. IBC idag
        $ibcIdag = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(MAX(ibc_ok), 0) AS cnt FROM rebotling_ibc WHERE datum >= :today AND datum < DATE_ADD(:today2, INTERVAL 1 DAY)
            ");
            $stmt->execute([':today' => $today, ':today2' => $today]);
            $ibcIdag = (int)($stmt->fetchColumn() ?: 0);
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getLiveKpi (ibc_idag): ' . $e->getMessage());
        }

        // 2. IBC/h (senaste timmen) — använd MAX(ibc_ok)-MIN(ibc_ok) per skift istället för COUNT(*)
        $ibcPerTimme = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(GREATEST(max_ok - min_ok, 0)), 0) AS cnt
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS max_ok, MIN(ibc_ok) AS min_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_dt AND ibc_ok IS NOT NULL
                    GROUP BY skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_dt' => $oneHourAgo]);
            $ibcPerTimme = (int)($stmt->fetchColumn() ?: 0);
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getLiveKpi (ibc_per_timme): ' . $e->getMessage());
        }

        // 3+4. Driftstatus + tid sedan senaste stopp (en SHOW TABLES-check istallet for tva)
        $driftstatus = ['running' => false, 'sedan' => null];
        $senasteStopp = ['minuter' => null, 'senaste_stopp' => null];
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_onoff'");
            if ($check && $check->rowCount() > 0) {
                // 3. Driftstatus (senaste on/off-post)
                $stmt = $this->pdo->query("
                    SELECT datum, running
                    FROM rebotling_onoff
                    ORDER BY datum DESC
                    LIMIT 1
                ");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $isRunning = (int)($row['running'] ?? 0);
                    $driftstatus = [
                        'running' => (bool)$isRunning,
                        'sedan'   => $row['datum'],
                    ];
                }

                // 4. Tid sedan senaste stopp
                $stmt = $this->pdo->query("
                    SELECT datum
                    FROM rebotling_onoff
                    WHERE running = 0
                    ORDER BY datum DESC
                    LIMIT 1
                ");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['datum']) {
                    $minuter = round((time() - strtotime($row['datum'])) / 60);
                    $senasteStopp = [
                        'minuter'       => max(0, (int)$minuter),
                        'senaste_stopp' => $row['datum'],
                    ];
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getLiveKpi (driftstatus/senaste_stopp): ' . $e->getMessage());
        }

        echo json_encode([
            'success'                  => true,
            'ibc_idag'                 => $ibcIdag,
            'ibc_per_timme'            => $ibcPerTimme,
            'driftstatus'              => $driftstatus,
            'tid_sedan_senaste_stopp'  => $senasteStopp,
            'timestamp'                => $now,
        ], JSON_UNESCAPED_UNICODE);
    }

    // ================================================================
    // LEGACY ENDPOINTS (bakatkompat)
    // ================================================================

    /**
     * Senaste X IBC:er med operatorsnamn, produktnamn, cykeltid, status
     */
    private function getLatest() {
        try {
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

            $stmt = $this->pdo->prepare("
                SELECT
                    i.id,
                    i.datum,
                    i.ibc_count,
                    i.skiftraknare,
                    i.ibc_ok,
                    i.ibc_ej_ok,
                    i.bur_ej_ok,
                    i.op1,
                    i.produkt,
                    TIMESTAMPDIFF(SECOND,
                        LAG(i.datum) OVER (PARTITION BY i.skiftraknare ORDER BY i.datum),
                        i.datum
                    ) AS cycle_time_seconds,
                    o1.name AS operator_namn,
                    p.name AS produkt_namn,
                    p.cycle_time_minutes AS target_cycle_minutes
                FROM rebotling_ibc i
                LEFT JOIN operators o1 ON i.op1 = o1.number
                LEFT JOIN rebotling_products p ON i.produkt = p.id
                ORDER BY i.datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $row) {
                $cycleSeconds = $row['cycle_time_seconds'] !== null ? (int)$row['cycle_time_seconds'] : null;
                $cycleMinutes = $cycleSeconds !== null ? round($cycleSeconds / 60, 1) : null;
                $targetMinutes = $row['target_cycle_minutes'] !== null ? (float)$row['target_cycle_minutes'] : null;

                if ($cycleMinutes !== null && $cycleMinutes > 30) {
                    $cycleMinutes = null;
                }

                $kasserad = ((int)($row['ibc_ej_ok'] ?? 0) > 0) || ((int)($row['bur_ej_ok'] ?? 0) > 0);

                $overTarget = false;
                if ($cycleMinutes !== null && $targetMinutes !== null && $targetMinutes > 0) {
                    $overTarget = $cycleMinutes > $targetMinutes;
                }

                $items[] = [
                    'id'              => (int)$row['id'],
                    'datum'           => $row['datum'],
                    'operator'        => $row['operator_namn'] ?? ('Op ' . ($row['op1'] ?? '?')),
                    'produkt'         => $row['produkt_namn'] ?? 'Okand',
                    'cykeltid'        => $cycleMinutes,
                    'target_cykeltid' => $targetMinutes,
                    'kasserad'        => $kasserad,
                    'over_target'     => $overTarget,
                    'ibc_nr'          => (int)($row['ibc_count'] ?? 0),
                    'skift'           => (int)($row['skiftraknare'] ?? 0),
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $items,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getLatest: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Timstatistik: IBC/h, snittcykeltid, godkanda/kasserade — senaste + foregaende timme
     */
    private function getHourlyStats() {
        try {
            $now = date('Y-m-d H:i:s');
            $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $twoHoursAgo = date('Y-m-d H:i:s', strtotime('-2 hours'));

            $current = $this->getHourData($oneHourAgo, $now);
            $previous = $this->getHourData($twoHoursAgo, $oneHourAgo);

            echo json_encode([
                'success'  => true,
                'current'  => $current,
                'previous' => $previous,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\PDOException $e) {
            error_log('ProduktionspulsController::getHourlyStats: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function getHourData(string $from, string $to): array {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN (ibc_ej_ok > 0 OR bur_ej_ok > 0) THEN 1 ELSE 0 END) AS kasserade
            FROM rebotling_ibc
            WHERE datum BETWEEN :from_dt AND :to_dt
        ");
        $stmt->execute(['from_dt' => $from, 'to_dt' => $to]);
        $counts = $stmt->fetch(\PDO::FETCH_ASSOC);

        $total = (int)($counts['total'] ?? 0);
        $kasserade = (int)($counts['kasserade'] ?? 0);
        $godkanda = $total - $kasserade;

        $stmt2 = $this->pdo->prepare("
            SELECT AVG(diff_sec) AS avg_cycle_seconds
            FROM (
                SELECT TIMESTAMPDIFF(SECOND,
                    LAG(i.datum) OVER (PARTITION BY i.skiftraknare ORDER BY i.datum),
                    i.datum
                ) AS diff_sec
                FROM rebotling_ibc i
                WHERE i.datum BETWEEN :from_dt AND :to_dt
            ) sub
            WHERE diff_sec > 0 AND diff_sec <= 1800
        ");
        $stmt2->execute(['from_dt' => $from, 'to_dt' => $to]);
        $avgRow = $stmt2->fetch(\PDO::FETCH_ASSOC);
        $avgCycleMinutes = $avgRow['avg_cycle_seconds'] !== null
            ? round((float)$avgRow['avg_cycle_seconds'] / 60, 1)
            : null;

        return [
            'ibc_count'       => $total,
            'godkanda'        => $godkanda,
            'kasserade'       => $kasserade,
            'snitt_cykeltid'  => $avgCycleMinutes,
        ];
    }
}
