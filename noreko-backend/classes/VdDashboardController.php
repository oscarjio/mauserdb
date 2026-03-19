<?php
/**
 * VdDashboardController.php
 * VD Executive Dashboard — alla kritiska KPI:er i ett anrop.
 *
 * Endpoints via ?action=vd-dashboard&run=XXX:
 *   run=oversikt        — OEE idag, total IBC, aktiva operatorer, mal vs faktiskt
 *   run=stopp-nu        — aktiva stopp just nu med station och orsak
 *   run=top-operatorer  — top 3 operatorer idag
 *   run=station-oee     — OEE per station idag
 *   run=veckotrend      — senaste 7 dagars OEE + IBC per dag
 *   run=skiftstatus     — aktuellt skift, kvarvarande tid, jamforelse mot forra
 *
 * Tabeller: rebotling_onoff, rebotling_ibc, rebotling_stationer,
 *           stopporsak_registreringar, users, produktionsmal
 */
class VdDashboardController {
    private $pdo;
    private const IDEAL_CYCLE_SEC = 120;

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
            case 'oversikt':        $this->oversikt();       break;
            case 'stopp-nu':        $this->stoppNu();        break;
            case 'top-operatorer':  $this->topOperatorer();  break;
            case 'station-oee':     $this->stationOee();     break;
            case 'veckotrend':      $this->veckotrend();     break;
            case 'skiftstatus':     $this->skiftstatus();    break;
            default:                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

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

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log('VdDashboardController::tableExists: ' . $e->getMessage());
            return false;
        }
    }

    private function getStationer(): array {
        try {
            $stmt = $this->pdo->query("SELECT id, namn FROM rebotling_stationer ORDER BY id");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) return $rows;
        } catch (\Exception $e) {
            error_log('VdDashboardController::getStationer: ' . $e->getMessage());
        }

        return [
            ['id' => 1, 'namn' => 'Station 1'],
            ['id' => 2, 'namn' => 'Station 2'],
            ['id' => 3, 'namn' => 'Station 3'],
            ['id' => 4, 'namn' => 'Station 4'],
            ['id' => 5, 'namn' => 'Station 5'],
        ];
    }

    /**
     * Beräkna drifttid i sekunder från rebotling_onoff (datum + running kolumner).
     */
    private function calcDrifttidSek(string $from, string $to): int {
        $stmt = $this->pdo->prepare("
            SELECT datum, running FROM rebotling_onoff
            WHERE datum BETWEEN :from_dt AND :to_dt ORDER BY datum ASC
        ");
        $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $sek = 0; $lastOn = null;
        foreach ($rows as $r) {
            $ts = strtotime($r['datum']);
            if ((int)$r['running'] === 1) { if ($lastOn === null) $lastOn = $ts; }
            else { if ($lastOn !== null) { $sek += max(0, $ts - $lastOn); $lastOn = null; } }
        }
        if ($lastOn !== null) $sek += max(0, min(time(), strtotime($to)) - $lastOn);
        return $sek;
    }

    /**
     * Berakna OEE for en given dag (totalt).
     */
    private function calcOeeForDay(string $date): array {
        $from = $date . ' 00:00:00';
        $to   = $date . ' 23:59:59';

        // Drifttid fran rebotling_onoff (datum + running kolumner)
        $drifttidSek = 0;
        try {
            $drifttidSek = $this->calcDrifttidSek($from, $to);
        } catch (\Exception $e) {
            error_log('VdDashboardController::calcOeeForDay (drifttid): ' . $e->getMessage());
        }

        $schemaSek = 8 * 3600;
        $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;

        // IBC via kumulativa PLC-fält
        $totalIbc = 0;
        $okIbc = 0;
        try {
            $sql = "
                SELECT COALESCE(SUM(shift_ok), 0) AS ok_ibc,
                       COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_ibc
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                      AND skiftraknare IS NOT NULL
                    GROUP BY skiftraknare
                ) sub
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':date' => $date]);
            $ibcRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $okIbc    = (int)($ibcRow['ok_ibc'] ?? 0);
            $totalIbc = $okIbc + (int)($ibcRow['ej_ok_ibc'] ?? 0);
        } catch (\Exception $e) {
            error_log('VdDashboardController::calcOeeForDay (ibc): ' . $e->getMessage());
        }

        $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
        $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
        $oee = $tillganglighet * $prestanda * $kvalitet;

        return [
            'oee'            => round($oee, 4),
            'tillganglighet' => round($tillganglighet, 4),
            'prestanda'      => round($prestanda, 4),
            'kvalitet'       => round($kvalitet, 4),
            'total_ibc'      => $totalIbc,
            'ok_ibc'         => $okIbc,
            'drifttid_sek'   => $drifttidSek,
        ];
    }

    // ================================================================
    // run=oversikt
    // ================================================================

    private function oversikt(): void {
        try {
            $today = date('Y-m-d');
            $oee = $this->calcOeeForDay($today);

            // Aktiva operatorer idag
            $aktivaOperatorer = 0;
            try {
                // rebotling_ibc has op1/op2/op3 (operator numbers), not user_id
                $sql = "
                    SELECT COUNT(DISTINCT op_id) AS cnt FROM (
                        SELECT op1 AS op_id FROM rebotling_ibc WHERE DATE(datum) = :today1 AND op1 IS NOT NULL AND op1 > 0
                        UNION
                        SELECT op2 AS op_id FROM rebotling_ibc WHERE DATE(datum) = :today2 AND op2 IS NOT NULL AND op2 > 0
                        UNION
                        SELECT op3 AS op_id FROM rebotling_ibc WHERE DATE(datum) = :today3 AND op3 IS NOT NULL AND op3 > 0
                    ) AS sub
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':today1' => $today, ':today2' => $today, ':today3' => $today]);
                $aktivaOperatorer = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                error_log('VdDashboardController::oversikt (aktiva op ibc): ' . $e->getMessage());
            }

            // Fallback: rebotling_data
            if ($aktivaOperatorer === 0 && $this->tableExists('rebotling_data')) {
                try {
                    $sql = "
                        SELECT COUNT(DISTINCT user_id) AS cnt
                        FROM rebotling_data
                        WHERE DATE(datum) = :today
                          AND user_id IS NOT NULL AND user_id > 0
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':today' => $today]);
                    $aktivaOperatorer = (int)$stmt->fetchColumn();
                } catch (\Exception $e) {
                    error_log('VdDashboardController::oversikt (aktiva op rebotling_data): ' . $e->getMessage());
                }
            }

            // Dagsmal
            $dagsmal = 0;
            if ($this->tableExists('produktionsmal')) {
                try {
                    $sql = "SELECT COALESCE(mal_antal, target_ibc) AS mal_antal
                            FROM produktionsmal
                            WHERE (datum = :today OR (giltig_from <= :today2 AND (giltig_tom IS NULL OR giltig_tom >= :today3)))
                            ORDER BY datum DESC, giltig_from DESC
                            LIMIT 1";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':today' => $today, ':today2' => $today, ':today3' => $today]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        $dagsmal = (int)$row['mal_antal'];
                    }
                } catch (\Exception $e) {
                    error_log('VdDashboardController::oversikt (produktionsmal): ' . $e->getMessage());
                }
            }

            // Fallback: berakna snitt fran senaste 30 dagarna
            if ($dagsmal === 0) {
                try {
                    $sql = "
                        SELECT ROUND(AVG(cnt)) AS avg_ibc FROM (
                            SELECT COUNT(*) AS cnt
                            FROM rebotling_ibc
                            WHERE DATE(datum) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                            GROUP BY DATE(datum)
                            HAVING cnt > 0
                        ) AS sub
                    ";
                    $stmt = $this->pdo->query($sql);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $dagsmal = (int)($row['avg_ibc'] ?? 0);
                } catch (\Exception $e) {
                    error_log('VdDashboardController::oversikt (snitt ibc fallback): ' . $e->getMessage());
                }
            }

            if ($dagsmal === 0) $dagsmal = 100; // Default

            $this->sendSuccess([
                'oee_pct'              => round($oee['oee'] * 100, 1),
                'tillganglighet_pct'   => round($oee['tillganglighet'] * 100, 1),
                'prestanda_pct'        => round($oee['prestanda'] * 100, 1),
                'kvalitet_pct'         => round($oee['kvalitet'] * 100, 1),
                'total_ibc'            => $oee['total_ibc'],
                'ok_ibc'               => $oee['ok_ibc'],
                'aktiva_operatorer'    => $aktivaOperatorer,
                'dagsmal'              => $dagsmal,
                'mal_procent'          => $dagsmal > 0 ? round(($oee['total_ibc'] / $dagsmal) * 100, 1) : 0,
                'datum'                => $today,
            ]);
        } catch (\Exception $e) {
            error_log('VdDashboardController::oversikt: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta oversikt', 500);
        }
    }

    // ================================================================
    // run=stopp-nu
    // ================================================================

    private function stoppNu(): void {
        try {
            $aktivaStopp = [];

            if ($this->tableExists('stopporsak_registreringar')) {
                try {
                    $sql = "
                        SELECT
                            sr.id,
                            COALESCE(sk.namn, 'Okand orsak') AS orsak,
                            sr.linje AS station_namn,
                            sr.start_time,
                            TIMESTAMPDIFF(MINUTE, sr.start_time, NOW()) AS varaktighet_min
                        FROM stopporsak_registreringar sr
                        LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                        WHERE sr.end_time IS NULL
                          AND sr.start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ORDER BY sr.start_time ASC
                    ";
                    $stmt = $this->pdo->query($sql);
                    $aktivaStopp = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    foreach ($aktivaStopp as &$s) {
                        $s['varaktighet_min'] = (int)$s['varaktighet_min'];
                    }
                    unset($s);
                } catch (\Exception $e) {
                    error_log('VdDashboardController::stoppNu (stopporsak_registreringar): ' . $e->getMessage());
                }
            }

            // Kolla om linjen ar stoppad via senaste rebotling_onoff-raden
            $stoppadeStationer = [];
            try {
                $stmt = $this->pdo->query("SELECT running, datum FROM rebotling_onoff ORDER BY datum DESC LIMIT 1");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !(int)$row['running']) {
                    $stoppadeStationer[] = ['station_id' => 0, 'senaste_stopp' => $row['datum']];
                }
            } catch (\Exception $e) {
                error_log('VdDashboardController::stoppNu (rebotling_onoff): ' . $e->getMessage());
            }

            $this->sendSuccess([
                'aktiva_stopp'       => $aktivaStopp,
                'antal_stopp'        => count($aktivaStopp),
                'stoppade_stationer' => $stoppadeStationer,
                'allt_kor'           => count($aktivaStopp) === 0,
            ]);
        } catch (\Exception $e) {
            error_log('VdDashboardController::stoppNu: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stoppstatus', 500);
        }
    }

    // ================================================================
    // run=top-operatorer
    // ================================================================

    private function topOperatorer(): void {
        try {
            $today = date('Y-m-d');
            $operators = [];

            // rebotling_ibc uses op1/op2/op3, not user_id
            try {
                $sql = "
                    SELECT
                        op_id AS user_id,
                        COALESCE(o.name, CONCAT('Operator ', op_id)) AS operator_namn,
                        SUM(cnt) AS total_ibc
                    FROM (
                        SELECT op1 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :today1 AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1
                        UNION ALL
                        SELECT op2 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :today2 AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2
                        UNION ALL
                        SELECT op3 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :today3 AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3
                    ) AS sub
                    LEFT JOIN operators o ON o.number = sub.op_id
                    GROUP BY op_id, o.name
                    ORDER BY total_ibc DESC
                    LIMIT 3
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':today1' => $today, ':today2' => $today, ':today3' => $today]);
                $operators = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                error_log('VdDashboardController::topOperatorer (ibc): ' . $e->getMessage());
            }

            // Fallback: rebotling_data
            if (empty($operators) && $this->tableExists('rebotling_data')) {
                try {
                    $sql = "
                        SELECT
                            r.user_id,
                            COALESCE(u.username, CONCAT('Operator ', r.user_id)) AS operator_namn,
                            SUM(COALESCE(r.antal, 1)) AS total_ibc
                        FROM rebotling_data r
                        LEFT JOIN users u ON r.user_id = u.id
                        WHERE DATE(r.datum) = :today
                          AND r.user_id IS NOT NULL AND r.user_id > 0
                        GROUP BY r.user_id, u.username
                        ORDER BY total_ibc DESC
                        LIMIT 3
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':today' => $today]);
                    $operators = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log('VdDashboardController::topOperatorer (rebotling_data): ' . $e->getMessage());
                }
            }

            // Lagg till rank
            foreach ($operators as $i => &$op) {
                $op['rank'] = $i + 1;
                $op['total_ibc'] = (int)$op['total_ibc'];
                $op['user_id'] = (int)$op['user_id'];
            }
            unset($op);

            $this->sendSuccess([
                'top_operatorer' => $operators,
                'datum'          => $today,
            ]);
        } catch (\Exception $e) {
            error_log('VdDashboardController::topOperatorer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta topp-operatorer', 500);
        }
    }

    // ================================================================
    // run=station-oee
    // ================================================================

    private function stationOee(): void {
        try {
            $today = date('Y-m-d');
            $stationer = $this->getStationer();
            $schemaSek = 8 * 3600;

            // Hamta IBC totalt (rebotling_ibc saknar station_id — fordela lika)
            $ibcByStation = [];
            try {
                $sql = "
                    SELECT
                        COALESCE(SUM(shift_ok), 0) AS ok_ibc,
                        COALESCE(SUM(shift_ej_ok), 0) AS ej_ok_ibc
                    FROM (
                        SELECT skiftraknare,
                               MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                               MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                        FROM rebotling_ibc
                        WHERE DATE(datum) = :today
                          AND skiftraknare IS NOT NULL
                        GROUP BY skiftraknare
                    ) sub
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':today' => $today]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalOkIbc = (int)($row['ok_ibc'] ?? 0);
                $totalEjOkIbc = (int)($row['ej_ok_ibc'] ?? 0);
                $totalAllIbc = $totalOkIbc + $totalEjOkIbc;
                // Fordela lika over stationer
                $sc = max(1, count($stationer));
                foreach ($stationer as $s) {
                    $sid = (int)$s['id'];
                    $ibcByStation[$sid] = [
                        'ok_ibc'    => (int)round($totalOkIbc / $sc),
                        'total_ibc' => (int)round($totalAllIbc / $sc),
                    ];
                }
            } catch (\Exception $e) {
                error_log('VdDashboardController::stationOee (ibc): ' . $e->getMessage());
            }

            // Hamta total drifttid (rebotling_onoff har datum + running, ej per station)
            $totalDrifttidSek = 0;
            try {
                $from = $today . ' 00:00:00';
                $to   = $today . ' 23:59:59';
                $totalDrifttidSek = $this->calcDrifttidSek($from, $to);
            } catch (\Exception $e) {
                error_log('VdDashboardController::stationOee (drifttid): ' . $e->getMessage());
            }
            // Dela drifttid lika mellan stationer (onoff saknar station_id)
            $driftByStation = [];
            $stationCount = max(1, count($stationer));
            foreach ($stationer as $s) {
                $driftByStation[(int)$s['id']] = (int)($totalDrifttidSek / $stationCount);
            }

            $results = [];
            foreach ($stationer as $s) {
                $sid = (int)$s['id'];
                $ibc = $ibcByStation[$sid] ?? null;
                $totalIbc = $ibc ? (int)$ibc['total_ibc'] : 0;
                $okIbc    = $ibc ? (int)$ibc['ok_ibc'] : 0;
                $drifttidSek = $driftByStation[$sid] ?? 0;

                $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;
                $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
                $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
                $oee = $tillganglighet * $prestanda * $kvalitet;

                $results[] = [
                    'station_id'   => $sid,
                    'station_namn' => $s['namn'],
                    'oee_pct'      => round($oee * 100, 1),
                    'total_ibc'    => $totalIbc,
                ];
            }

            // Sortera hogst OEE forst
            usort($results, fn($a, $b) => $b['oee_pct'] <=> $a['oee_pct']);

            $this->sendSuccess([
                'stationer' => $results,
                'datum'     => $today,
            ]);
        } catch (\Exception $e) {
            error_log('VdDashboardController::stationOee: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta station-OEE', 500);
        }
    }

    // ================================================================
    // run=veckotrend
    // ================================================================

    private function veckotrend(): void {
        try {
            $trend = [];
            for ($i = 6; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime("-{$i} days"));
                $oee = $this->calcOeeForDay($dag);
                $trend[] = [
                    'datum'     => $dag,
                    'dag_kort'  => $this->svenskDag($dag),
                    'oee_pct'   => round($oee['oee'] * 100, 1),
                    'total_ibc' => $oee['total_ibc'],
                ];
            }

            $this->sendSuccess([
                'trend' => $trend,
            ]);
        } catch (\Exception $e) {
            error_log('VdDashboardController::veckotrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta veckotrend', 500);
        }
    }

    private function svenskDag(string $date): string {
        $dow = (int)date('w', strtotime($date));
        $dagar = ['Son', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor'];
        return $dagar[$dow];
    }

    // ================================================================
    // run=skiftstatus
    // ================================================================

    private function skiftstatus(): void {
        try {
            $nu = new \DateTime('now', new \DateTimeZone('Europe/Stockholm'));
            $timme = (int)$nu->format('H');
            $minut = (int)$nu->format('i');

            // Bestam aktuellt skift
            if ($timme >= 6 && $timme < 14) {
                $skift = 'FM';
                $skiftStart = 6;
                $skiftSlut  = 14;
            } elseif ($timme >= 14 && $timme < 22) {
                $skift = 'EM';
                $skiftStart = 14;
                $skiftSlut  = 22;
            } else {
                $skift = 'Natt';
                $skiftStart = 22;
                $skiftSlut  = 6;
            }

            // Berakna kvarvarande tid
            if ($skift === 'Natt') {
                if ($timme >= 22) {
                    $kvarMin = (24 - $timme - 1) * 60 + (60 - $minut) + 6 * 60;
                } else {
                    $kvarMin = (6 - $timme - 1) * 60 + (60 - $minut);
                }
            } else {
                $kvarMin = ($skiftSlut - $timme - 1) * 60 + (60 - $minut);
            }
            $kvarTimmar = floor($kvarMin / 60);
            $kvarMinuter = $kvarMin % 60;

            // IBC for aktuellt skift
            $today = date('Y-m-d');
            $skiftFromTime = sprintf('%s %02d:00:00', $today, $skiftStart);
            if ($skift === 'Natt' && $timme < 6) {
                $skiftFromTime = sprintf('%s 22:00:00', date('Y-m-d', strtotime('-1 day')));
            }
            $skiftToTime = date('Y-m-d H:i:s');

            $ibcAktuellt = 0;
            try {
                $sql = "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE datum BETWEEN :from AND :to";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $skiftFromTime, ':to' => $skiftToTime]);
                $ibcAktuellt = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                error_log('VdDashboardController::skiftstatus (aktuellt ibc): ' . $e->getMessage());
            }

            // IBC for forra skiftet (samma typ, igår)
            $ibcForra = 0;
            try {
                if ($skift === 'FM') {
                    $fFrom = date('Y-m-d', strtotime('-1 day')) . ' 06:00:00';
                    $fTo   = date('Y-m-d', strtotime('-1 day')) . ' 14:00:00';
                } elseif ($skift === 'EM') {
                    $fFrom = date('Y-m-d', strtotime('-1 day')) . ' 14:00:00';
                    $fTo   = date('Y-m-d', strtotime('-1 day')) . ' 22:00:00';
                } else {
                    $fFrom = date('Y-m-d', strtotime('-2 days')) . ' 22:00:00';
                    $fTo   = date('Y-m-d', strtotime('-1 day')) . ' 06:00:00';
                }
                $sql = "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE datum BETWEEN :from AND :to";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $fFrom, ':to' => $fTo]);
                $ibcForra = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                error_log('VdDashboardController::skiftstatus (forra ibc): ' . $e->getMessage());
            }

            $this->sendSuccess([
                'skift'           => $skift,
                'skift_start'     => sprintf('%02d:00', $skiftStart),
                'skift_slut'      => sprintf('%02d:00', $skiftSlut),
                'kvar_timmar'     => (int)$kvarTimmar,
                'kvar_minuter'    => (int)$kvarMinuter,
                'ibc_aktuellt'    => $ibcAktuellt,
                'ibc_forra'       => $ibcForra,
                'jamforelse'      => $ibcForra > 0 ? round((($ibcAktuellt - $ibcForra) / $ibcForra) * 100, 1) : 0,
            ]);
        } catch (\Exception $e) {
            error_log('VdDashboardController::skiftstatus: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta skiftstatus', 500);
        }
    }
}
