<?php
/**
 * StopporsakController.php
 * Stopporsak-dashboard — visuell oversikt av alla produktionsstopp (Rebotling).
 *
 * Endpoints via ?action=stopporsak-dashboard&run=XXX:
 *   run=sammanfattning  -> KPI: totalt stopp, total tid, snitt tid, vanligaste orsak
 *   run=pareto          -> top-10 orsaker med antal, andel%, kumulativ% (Pareto-chart)
 *   run=per-station     -> stopptid grupperat per station (fran underhallslogg)
 *   run=trend           -> antal stopp per dag senaste X dagar
 *   run=orsaker-tabell  -> alla orsaker med antal, tid, snitt, andel, trend-jamforelse
 *   run=detaljer        -> senaste 50 stopp med all info
 *
 * Tabeller:
 *   stopporsak_registreringar (id, kategori_id, linje, kommentar, user_id, start_time, end_time)
 *   stopporsak_kategorier     (id, namn, ikon, sort_order, active)
 *   rebotling_underhallslogg  (id, station_id, typ, beskrivning, varaktighet_min, stopporsak, datum)
 */
class StopporsakController {
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
            case 'sammanfattning':  $this->getSammanfattning();  break;
            case 'pareto':          $this->getPareto();          break;
            case 'per-station':     $this->getPerStation();      break;
            case 'trend':           $this->getTrend();           break;
            case 'orsaker-tabell':  $this->getOrsakerTabell();   break;
            case 'detaljer':        $this->getDetaljer();        break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
    }

    private function getDateRange(int $days): array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));
        return [$fromDate, $toDate];
    }

    private function getPrevDateRange(int $days): array {
        $prevTo   = date('Y-m-d', strtotime("-" . ($days + 1) . " days"));
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
        return [$prevFrom, $prevTo];
    }

    private function sendSuccess(array $data): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function stationNamn(int $id): string {
        return self::STATIONER[$id] ?? "Station {$id}";
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log('StopporsakController::tableExists: ' . $e->getMessage());
            return false;
        }
    }

    // ================================================================
    // run=sammanfattning — KPI
    // ================================================================

    private function getSammanfattning(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            // Antal stopp + total tid
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS antal_stopp,
                    COALESCE(SUM(
                        CASE WHEN end_time IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, start_time, end_time)
                            ELSE 0
                        END
                    ), 0) AS total_sek
                FROM stopporsak_registreringar
                WHERE DATE(start_time) BETWEEN :from_date AND :to_date
                  AND linje = 'rebotling'
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $antalStopp = (int)($row['antal_stopp'] ?? 0);
            $totalSek   = (int)($row['total_sek'] ?? 0);
            $totalTimmar = round($totalSek / 3600, 1);
            $snittMin    = $antalStopp > 0 ? round(($totalSek / 60) / $antalStopp, 1) : 0;

            // Vanligaste orsak
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(k.namn, 'Okänd kategori') AS orsak, COUNT(*) AS antal
                FROM stopporsak_registreringar r
                LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                WHERE DATE(r.start_time) BETWEEN :from_date AND :to_date
                  AND r.linje = 'rebotling'
                GROUP BY k.id, k.namn
                ORDER BY antal DESC
                LIMIT 1
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $topRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $vanligasteOrsak = ($topRow && isset($topRow['orsak'])) ? $topRow['orsak'] : null;

            // Forega period for jamforelse
            [$prevFrom, $prevTo] = $this->getPrevDateRange($days);
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS antal_stopp
                FROM stopporsak_registreringar
                WHERE DATE(start_time) BETWEEN :from_date AND :to_date
                  AND linje = 'rebotling'
            ");
            $stmt->execute([':from_date' => $prevFrom, ':to_date' => $prevTo]);
            $prevAntal = (int)$stmt->fetchColumn();

            $trendPct = $prevAntal > 0
                ? round((($antalStopp - $prevAntal) / $prevAntal) * 100, 1)
                : 0;

            $this->sendSuccess([
                'days'             => $days,
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
                'antal_stopp'      => $antalStopp,
                'total_timmar'     => $totalTimmar,
                'snitt_min'        => $snittMin,
                'vanligaste_orsak' => $vanligasteOrsak,
                'prev_antal'       => $prevAntal,
                'trend_pct'        => $trendPct,
            ]);
        } catch (\PDOException $e) {
            error_log('StopporsakController::getSammanfattning: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=pareto — top-10 Pareto
    // ================================================================

    private function getPareto(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    k.id AS kategori_id,
                    COALESCE(k.namn, 'Okänd kategori') AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(
                        CASE WHEN r.end_time IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, r.start_time, r.end_time)
                            ELSE 0
                        END
                    ), 0) AS total_sek
                FROM stopporsak_registreringar r
                LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                WHERE DATE(r.start_time) BETWEEN :from_date AND :to_date
                  AND r.linje = 'rebotling'
                GROUP BY k.id, k.namn
                ORDER BY antal DESC
                LIMIT 10
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalAntal = array_sum(array_column($rows, 'antal'));
            $cumulative = 0;
            $pareto = [];

            foreach ($rows as $row) {
                $antal   = (int)$row['antal'];
                $procent = $totalAntal > 0 ? round(($antal / $totalAntal) * 100, 1) : 0;
                $cumulative += $procent;
                $pareto[] = [
                    'kategori_id'   => (int)$row['kategori_id'],
                    'orsak'         => $row['orsak'],
                    'antal'         => $antal,
                    'total_min'     => round((int)$row['total_sek'] / 60, 1),
                    'procent'       => $procent,
                    'kumulativ_pct' => round($cumulative, 1),
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'total'     => $totalAntal,
                'pareto'    => $pareto,
            ]);
        } catch (\PDOException $e) {
            error_log('StopporsakController::getPareto: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=per-station — Stopptid per station
    // ================================================================

    private function getPerStation(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        $stationer = [];

        // Prova rebotling_underhallslogg for stations-data
        if ($this->tableExists('rebotling_underhallslogg')) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        station_id,
                        COUNT(*) AS antal,
                        COALESCE(SUM(varaktighet_min), 0) AS total_min
                    FROM rebotling_underhallslogg
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND typ = 'oplanerat'
                    GROUP BY station_id
                    ORDER BY total_min DESC
                ");
                $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $sid = (int)$row['station_id'];
                    $stationer[] = [
                        'station_id'   => $sid,
                        'station_namn' => $this->stationNamn($sid),
                        'antal'        => (int)$row['antal'],
                        'total_min'    => round((float)$row['total_min'], 1),
                    ];
                }
            } catch (\PDOException $e) {
                error_log('StopporsakController::getPerStation underhallslogg: ' . $e->getMessage());
            }
        }

        // Om inga stations-data, ge sammanfattning fran stopporsak_registreringar per kategori
        if (empty($stationer)) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(k.namn, 'Okänd kategori') AS station_namn,
                        COUNT(*) AS antal,
                        COALESCE(SUM(
                            CASE WHEN r.end_time IS NOT NULL
                                THEN TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)
                                ELSE 0
                            END
                        ), 0) AS total_min
                    FROM stopporsak_registreringar r
                    LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                    WHERE DATE(r.start_time) BETWEEN :from_date AND :to_date
                      AND r.linje = 'rebotling'
                    GROUP BY k.id, k.namn
                    ORDER BY total_min DESC
                ");
                $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $i => $row) {
                    $stationer[] = [
                        'station_id'   => $i + 1,
                        'station_namn' => $row['station_namn'],
                        'antal'        => (int)$row['antal'],
                        'total_min'    => round((float)$row['total_min'], 1),
                    ];
                }
            } catch (\PDOException $e) {
                error_log('StopporsakController::getPerStation fallback: ' . $e->getMessage());
            }
        }

        $this->sendSuccess([
            'days'      => $days,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'stationer' => $stationer,
        ]);
    }

    // ================================================================
    // run=trend — Antal stopp per dag
    // ================================================================

    private function getTrend(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(start_time) AS dag,
                    COUNT(*) AS antal,
                    COALESCE(SUM(
                        CASE WHEN end_time IS NOT NULL
                            THEN TIMESTAMPDIFF(MINUTE, start_time, end_time)
                            ELSE 0
                        END
                    ), 0) AS total_min
                FROM stopporsak_registreringar
                WHERE DATE(start_time) BETWEEN :from_date AND :to_date
                  AND linje = 'rebotling'
                GROUP BY DATE(start_time)
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Map for snabb lookup
            $dayMap = [];
            foreach ($dbRows as $row) {
                $dayMap[$row['dag']] = [
                    'antal'     => (int)$row['antal'],
                    'total_min' => round((float)$row['total_min'], 1),
                ];
            }

            // Bygg fullstandig datumsekvens
            $dates = [];
            $antal = [];
            $minuter = [];
            $d = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($d <= $end) {
                $ds = $d->format('Y-m-d');
                $dates[] = $ds;
                $antal[]   = $dayMap[$ds]['antal'] ?? 0;
                $minuter[] = $dayMap[$ds]['total_min'] ?? 0;
                $d->modify('+1 day');
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'dates'     => $dates,
                'antal'     => $antal,
                'minuter'   => $minuter,
            ]);
        } catch (\PDOException $e) {
            error_log('StopporsakController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=orsaker-tabell — Alla orsaker med statistik + trend-jamforelse
    // ================================================================

    private function getOrsakerTabell(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);
        [$prevFrom, $prevTo] = $this->getPrevDateRange($days);

        try {
            // Nuvarande period
            $stmt = $this->pdo->prepare("
                SELECT
                    k.id AS kategori_id,
                    COALESCE(k.namn, 'Okänd kategori') AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(
                        CASE WHEN r.end_time IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, r.start_time, r.end_time)
                            ELSE 0
                        END
                    ), 0) AS total_sek
                FROM stopporsak_registreringar r
                LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                WHERE DATE(r.start_time) BETWEEN :from_date AND :to_date
                  AND r.linje = 'rebotling'
                GROUP BY k.id, k.namn
                ORDER BY antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $currentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Forega period
            $stmt->execute([':from_date' => $prevFrom, ':to_date' => $prevTo]);
            $prevRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Bygg lookup for prev
            $prevMap = [];
            foreach ($prevRows as $row) {
                $prevMap[(int)$row['kategori_id']] = (int)$row['antal'];
            }

            $totalAntal = array_sum(array_column($currentRows, 'antal'));

            $orsaker = [];
            foreach ($currentRows as $row) {
                $id    = (int)$row['kategori_id'];
                $antal = (int)$row['antal'];
                $sek   = (int)$row['total_sek'];
                $prevAntal = $prevMap[$id] ?? 0;

                $trendPct = $prevAntal > 0
                    ? round((($antal - $prevAntal) / $prevAntal) * 100, 1)
                    : ($antal > 0 ? 100 : 0);

                $orsaker[] = [
                    'kategori_id'  => $id,
                    'orsak'        => $row['orsak'],
                    'antal'        => $antal,
                    'total_min'    => round($sek / 60, 1),
                    'snitt_min'    => $antal > 0 ? round(($sek / 60) / $antal, 1) : 0,
                    'andel_pct'    => $totalAntal > 0 ? round(($antal / $totalAntal) * 100, 1) : 0,
                    'prev_antal'   => $prevAntal,
                    'trend_pct'    => $trendPct,
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'orsaker'   => $orsaker,
                'total'     => $totalAntal,
            ]);
        } catch (\PDOException $e) {
            error_log('StopporsakController::getOrsakerTabell: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=detaljer — Senaste 50 stopp
    // ================================================================

    private function getDetaljer(): void {
        $days = $this->getDays();
        [$fromDate, $toDate] = $this->getDateRange($days);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    r.id,
                    r.start_time,
                    r.end_time,
                    r.kommentar,
                    COALESCE(k.namn, 'Okänd kategori') AS orsak,
                    k.ikon,
                    u.username AS operator_namn,
                    CASE WHEN r.end_time IS NOT NULL
                        THEN TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)
                        ELSE NULL
                    END AS varaktighet_min
                FROM stopporsak_registreringar r
                LEFT JOIN stopporsak_kategorier k ON r.kategori_id = k.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE DATE(r.start_time) BETWEEN :from_date AND :to_date
                  AND r.linje = 'rebotling'
                ORDER BY r.start_time DESC
                LIMIT 50
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Kolla om det finns koppling till underhallslogg
            $hasUnderhall = $this->tableExists('rebotling_underhallslogg');
            $underhallMap = [];

            if ($hasUnderhall && !empty($rows)) {
                // Hamta underhall for samma period
                try {
                    $uStmt = $this->pdo->prepare("
                        SELECT id, station_id, stopporsak, datum, varaktighet_min, beskrivning
                        FROM rebotling_underhallslogg
                        WHERE DATE(datum) BETWEEN :from_date AND :to_date
                        ORDER BY datum DESC
                        LIMIT 500
                    ");
                    $uStmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
                    $uRows = $uStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($uRows as $u) {
                        $uDatum = date('Y-m-d', strtotime($u['datum']));
                        $key = mb_strtolower(trim($u['stopporsak'] ?? ''));
                        if ($key) {
                            $underhallMap[$uDatum . '|' . $key] = [
                                'underhall_id'  => (int)$u['id'],
                                'station_namn'  => $this->stationNamn((int)$u['station_id']),
                                'beskrivning'   => $u['beskrivning'],
                            ];
                        }
                    }
                } catch (\PDOException $e) {
                    error_log('StopporsakController::getDetaljer underhall: ' . $e->getMessage());
                }
            }

            $detaljer = [];
            foreach ($rows as $row) {
                $datum = date('Y-m-d', strtotime($row['start_time']));
                $orsakKey = mb_strtolower(trim($row['orsak']));
                $underhall = $underhallMap[$datum . '|' . $orsakKey] ?? null;

                $detaljer[] = [
                    'id'              => (int)$row['id'],
                    'start_time'      => $row['start_time'],
                    'end_time'        => $row['end_time'],
                    'orsak'           => $row['orsak'],
                    'ikon'            => $row['ikon'] ?? '',
                    'varaktighet_min' => $row['varaktighet_min'] !== null ? (int)$row['varaktighet_min'] : null,
                    'kommentar'       => $row['kommentar'],
                    'operator_namn'   => $row['operator_namn'] ?? 'Okand',
                    'underhall'       => $underhall,
                ];
            }

            $this->sendSuccess([
                'days'     => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'detaljer'  => $detaljer,
                'total'     => count($detaljer),
            ]);
        } catch (\PDOException $e) {
            error_log('StopporsakController::getDetaljer: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
