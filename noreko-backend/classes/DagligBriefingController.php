<?php
/**
 * DagligBriefingController.php
 * Daglig briefing — VD:ns morgonrapport.
 * Komplett sammanfattning av gardasgens resultat och dagens plan.
 *
 * Endpoints via ?action=daglig-briefing&run=XXX:
 *   run=sammanfattning   — gardasgens KPI:er + autogenererad textsummering
 *   run=stopporsaker     — top stopporsaker med minuter och procent
 *   run=stationsstatus   — station-tabell med OEE och status
 *   run=veckotrend       — 7 dagars produktion
 *   run=bemanning        — dagens operatorer
 *
 * Query-param: datum=YYYY-MM-DD (default = igars datum)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_stationer,
 *           stopporsak_registreringar, stopporsak_kategorier, users, produktionsmal
 */
class DagligBriefingController {
    private $pdo;
    private const IDEAL_CYCLE_SEC = 120;
    private const SCHEMA_SEK_PER_DAG = 28800; // 8h
    private const OEE_MAL = 65;
    private const KASSATION_TROSKEL = 3;

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
            case 'sammanfattning':  $this->sammanfattning();  break;
            case 'stopporsaker':    $this->stopporsaker();    break;
            case 'stationsstatus':  $this->stationsstatus();  break;
            case 'veckotrend':      $this->veckotrend();      break;
            case 'bemanning':       $this->bemanning();       break;
            default:                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function getDatum(): string {
        $d = trim($_GET['datum'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        return date('Y-m-d', strtotime('-1 day'));
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

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log('DagligBriefingController::tableExists: ' . $e->getMessage());
            return false;
        }
    }

    private function calcOeeForDay(string $date): array {
        $from = $date . ' 00:00:00';
        $to   = $date . ' 23:59:59';

        // rebotling_onoff: datum (DATETIME), running (BOOLEAN)
        $drifttidSek = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT datum, running FROM rebotling_onoff
                WHERE datum >= :from_dt AND datum <= :to_dt
                ORDER BY datum ASC
            ");
            $stmt->execute([':from_dt' => $from, ':to_dt' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $prevTime = null;
            $prevRunning = null;
            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                $running = (int)$row['running'];
                if ($prevTime !== null && $prevRunning === 1) {
                    $drifttidSek += ($ts - $prevTime);
                }
                $prevTime = $ts;
                $prevRunning = $running;
            }
            $drifttidSek = max(0, $drifttidSek);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::calcOeeForDay (onoff): ' . $e->getMessage());
        }

        $schemaSek = self::SCHEMA_SEK_PER_DAG;
        $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;

        // rebotling_ibc: MAX(ibc_ok) per skiftraknare, then SUM
        $totalIbc = 0;
        $okIbc = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ok_ibc,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ej_ok_ibc
                FROM (
                    SELECT skiftraknare, MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                    GROUP BY skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([':date' => $date]);
            $ibcRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $okIbc    = (int)($ibcRow['ok_ibc'] ?? 0);
            $totalIbc = $okIbc + (int)($ibcRow['ej_ok_ibc'] ?? 0);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::calcOeeForDay (ibc): ' . $e->getMessage());
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
    // run=sammanfattning
    // ================================================================

    private function sammanfattning(): void {
        try {
            $datum = $this->getDatum();
            $oee = $this->calcOeeForDay($datum);

            $totalIbc = $oee['total_ibc'];
            $okIbc = $oee['ok_ibc'];
            $oeePercent = round($oee['oee'] * 100, 1);

            // Kasserade
            $kasserade = 0;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade
                    FROM rebotling_ibc
                    WHERE DATE(datum) = :date
                ");
                $stmt->execute([':date' => $datum]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $kasserade = (int)($row['kasserade'] ?? 0);
            } catch (\Exception $e) {
                error_log('DagligBriefingController::sammanfattning (kasserade): ' . $e->getMessage());
            }

            $kassationsrate = $totalIbc > 0 ? round(($kasserade / $totalIbc) * 100, 2) : 0;

            // Stoppminuter
            $stoppMinuter = 0;
            if ($this->tableExists('stopporsak_registreringar')) {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT SUM(
                            TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, LEAST(NOW(), :to1)))
                        ) AS stopp_min
                        FROM stopporsak_registreringar
                        WHERE DATE(start_time) = :date
                          AND linje = 'rebotling'
                    ");
                    $stmt->execute([':date' => $datum, ':to1' => $datum . ' 23:59:59']);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $stoppMinuter = max(0, (int)($row['stopp_min'] ?? 0));
                } catch (\Exception $e) {
                    error_log('DagligBriefingController::sammanfattning (stoppminuter): ' . $e->getMessage());
                }
            }

            // Dagsmal
            $dagsmal = 0;
            if ($this->tableExists('produktionsmal')) {
                try {
                    $stmt = $this->pdo->prepare("SELECT mal_antal FROM produktionsmal WHERE datum = :date LIMIT 1");
                    $stmt->execute([':date' => $datum]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row) $dagsmal = (int)$row['mal_antal'];
                } catch (\Exception $e) {
                    error_log('DagligBriefingController::sammanfattning (produktionsmal): ' . $e->getMessage());
                }
            }

            if ($dagsmal === 0) {
                try {
                    $sql = "
                        SELECT ROUND(AVG(cnt)) AS avg_ibc FROM (
                            SELECT COUNT(*) AS cnt
                            FROM rebotling_ibc
                            WHERE DATE(datum) BETWEEN DATE_SUB(:date1, INTERVAL 30 DAY) AND DATE_SUB(:date2, INTERVAL 1 DAY)
                            GROUP BY DATE(datum)
                            HAVING cnt > 0
                        ) AS sub
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':date1' => $datum, ':date2' => $datum]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $dagsmal = (int)($row['avg_ibc'] ?? 0);
                } catch (\Exception $e) {
                    error_log('DagligBriefingController::sammanfattning (avg_ibc): ' . $e->getMessage());
                }
            }
            if ($dagsmal === 0) $dagsmal = 100;

            $malProcent = $dagsmal > 0 ? round(($totalIbc / $dagsmal) * 100, 1) : 0;

            // Basta operator (rebotling_ibc uses op1/op2/op3 columns)
            $bastaOperator = null;
            try {
                $sql = "
                    SELECT op, SUM(cnt) AS total_ibc, COALESCE(o.name, CONCAT('Operator ', op)) AS operator_namn
                    FROM (
                        SELECT op1 AS op, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :date1 AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1
                        UNION ALL
                        SELECT op2 AS op, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :date2 AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2
                        UNION ALL
                        SELECT op3 AS op, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :date3 AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3
                    ) AS sub
                    LEFT JOIN operators o ON o.number = sub.op
                    GROUP BY op, o.name
                    ORDER BY total_ibc DESC
                    LIMIT 1
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':date1' => $datum, ':date2' => $datum, ':date3' => $datum]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $bastaOperator = [
                        'namn'      => $row['operator_namn'],
                        'total_ibc' => (int)$row['total_ibc'],
                    ];
                }
            } catch (\Exception $e) {
                error_log('DagligBriefingController::sammanfattning (basta_operator): ' . $e->getMessage());
            }

            // Framsta stopporsak for textsummering
            $framstaOrsak = 'okand orsak';
            if ($this->tableExists('stopporsak_registreringar')) {
                try {
                    $sql = "
                        SELECT COALESCE(sk.namn, sr.kommentar, 'Okand') AS orsak,
                               SUM(TIMESTAMPDIFF(MINUTE, sr.start_time, COALESCE(sr.end_time, LEAST(NOW(), :to1)))) AS minuter
                        FROM stopporsak_registreringar sr
                        LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                        WHERE DATE(sr.start_time) = :date
                          AND sr.linje = 'rebotling'
                        GROUP BY COALESCE(sk.namn, sr.kommentar, 'Okand')
                        ORDER BY minuter DESC
                        LIMIT 1
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':date' => $datum, ':to1' => $datum . ' 23:59:59']);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && (int)$row['minuter'] > 0) {
                        $framstaOrsak = $row['orsak'];
                    }
                } catch (\Exception $e) {
                    error_log('DagligBriefingController::sammanfattning (framsta_orsak): ' . $e->getMessage());
                }
            }

            // Autogenererad textsummering
            $oeeOk = $oeePercent >= self::OEE_MAL;
            $kassOk = $kassationsrate <= self::KASSATION_TROSKEL;
            $malOk = $malProcent >= 90;

            if ($malOk && $oeeOk && $kassOk) {
                $bedomning = 'Gardasgen gick bra.';
            } elseif ($malProcent >= 70 && $oeePercent >= 50) {
                $bedomning = 'Gardasgen var acceptabel men kan forbattras.';
            } else {
                $bedomning = 'Gardasgen var under mal.';
            }

            $summering = $bedomning
                . ' Produktion: ' . $totalIbc . ' IBC (' . $malProcent . '% av mal).'
                . ' OEE: ' . $oeePercent . '%.'
                . ' Stopp: ' . $stoppMinuter . ' min'
                . ($stoppMinuter > 0 ? ', framst pga ' . $framstaOrsak . '.' : '.');

            $this->sendSuccess([
                'datum'           => $datum,
                'total_ibc'       => $totalIbc,
                'ok_ibc'          => $okIbc,
                'kasserade'       => $kasserade,
                'kassationsrate'  => $kassationsrate,
                'oee_pct'         => $oeePercent,
                'stopp_minuter'   => $stoppMinuter,
                'dagsmal'         => $dagsmal,
                'mal_procent'     => $malProcent,
                'basta_operator'  => $bastaOperator,
                'summering'       => $summering,
                'oee_mal'         => self::OEE_MAL,
                'kassation_troskel' => self::KASSATION_TROSKEL,
            ]);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::sammanfattning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=stopporsaker
    // ================================================================

    private function stopporsaker(): void {
        try {
            $datum = $this->getDatum();
            $orsaker = [];

            if ($this->tableExists('stopporsak_registreringar')) {
                try {
                    $sql = "
                        SELECT
                            COALESCE(sk.namn, sr.kommentar, 'Okand') AS orsak,
                            SUM(TIMESTAMPDIFF(MINUTE, sr.start_time, COALESCE(sr.end_time, LEAST(NOW(), :to1)))) AS minuter,
                            COUNT(*) AS antal
                        FROM stopporsak_registreringar sr
                        LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                        WHERE DATE(sr.start_time) = :date
                          AND sr.linje = 'rebotling'
                        GROUP BY COALESCE(sk.namn, sr.kommentar, 'Okand')
                        ORDER BY minuter DESC
                        LIMIT 5
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':date' => $datum, ':to1' => $datum . ' 23:59:59']);
                    $orsaker = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    error_log('DagligBriefingController::stopporsaker: ' . $e->getMessage());
                }
            }

            $totalMin = 0;
            foreach ($orsaker as &$o) {
                $o['minuter'] = max(0, (int)$o['minuter']);
                $o['antal'] = (int)$o['antal'];
                $totalMin += $o['minuter'];
            }
            unset($o);

            foreach ($orsaker as &$o) {
                $o['procent'] = $totalMin > 0 ? round(($o['minuter'] / $totalMin) * 100, 1) : 0;
            }
            unset($o);

            $this->sendSuccess([
                'datum'       => $datum,
                'orsaker'     => array_slice($orsaker, 0, 3),
                'total_min'   => $totalMin,
            ]);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::stopporsaker: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stopporsaker', 500);
        }
    }

    // ================================================================
    // run=stationsstatus
    // ================================================================

    private function stationsstatus(): void {
        try {
            $datum = $this->getDatum();
            $schemaSek = self::SCHEMA_SEK_PER_DAG;

            // rebotling_ibc has no station column — show single line
            $oee = $this->calcOeeForDay($datum);
            $totalIbc = $oee['total_ibc'];
            $okIbc = $oee['ok_ibc'];
            $drifttidSek = $oee['drifttid_sek'];

            $tillganglighet = $schemaSek > 0 ? min(1.0, $drifttidSek / $schemaSek) : 0.0;
            $prestanda = $drifttidSek > 0 ? min(1.0, ($totalIbc * self::IDEAL_CYCLE_SEC) / $drifttidSek) : 0.0;
            $kvalitet = $totalIbc > 0 ? ($okIbc / $totalIbc) : 0.0;
            $oeeVal = $tillganglighet * $prestanda * $kvalitet;
            $oeePct = round($oeeVal * 100, 1);

            if ($totalIbc === 0) {
                $status = 'Ingen data';
            } elseif ($oeePct >= self::OEE_MAL) {
                $status = 'OK';
            } elseif ($oeePct >= 40) {
                $status = 'Varning';
            } else {
                $status = 'Kritisk';
            }

            $results = [
                [
                    'station_id'   => 1,
                    'station_namn' => 'Rebotling',
                    'total_ibc'    => $totalIbc,
                    'oee_pct'      => $oeePct,
                    'status'       => $status,
                ],
            ];

            $this->sendSuccess([
                'datum'     => $datum,
                'stationer' => $results,
            ]);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::stationsstatus: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationsstatus', 500);
        }
    }

    // ================================================================
    // run=veckotrend
    // ================================================================

    private function veckotrend(): void {
        try {
            $datum = $this->getDatum();
            $trend = [];

            for ($i = 6; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime($datum . " -{$i} days"));
                $totalIbc = 0;
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT COALESCE(SUM(shift_ok + shift_ej_ok), 0) AS total_ibc
                        FROM (
                            SELECT skiftraknare,
                                   MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                                   MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                            FROM rebotling_ibc
                            WHERE DATE(datum) = :date
                              AND skiftraknare IS NOT NULL
                            GROUP BY skiftraknare
                        ) AS per_shift
                    ");
                    $stmt->execute([':date' => $dag]);
                    $totalIbc = (int)$stmt->fetchColumn();
                } catch (\Exception $e) {
                    error_log('DagligBriefingController::veckotrend: ' . $e->getMessage());
                }

                $dow = (int)date('w', strtotime($dag));
                $dagar = ['Son', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor'];

                $trend[] = [
                    'datum'     => $dag,
                    'dag_kort'  => $dagar[$dow],
                    'total_ibc' => $totalIbc,
                ];
            }

            $this->sendSuccess([
                'datum' => $datum,
                'trend' => $trend,
            ]);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::veckotrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta veckotrend', 500);
        }
    }

    // ================================================================
    // run=bemanning
    // ================================================================

    private function bemanning(): void {
        try {
            $today = date('Y-m-d');
            $operatorer = [];

            // Operatorer som producerat idag (rebotling_ibc uses op1/op2/op3)
            try {
                $sql = "
                    SELECT op, SUM(cnt) AS ibc_idag, COALESCE(o.name, CONCAT('Operator ', op)) AS namn
                    FROM (
                        SELECT op1 AS op, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :today1 AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY op1
                        UNION ALL
                        SELECT op2 AS op, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :today2 AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY op2
                        UNION ALL
                        SELECT op3 AS op, COUNT(*) AS cnt FROM rebotling_ibc
                        WHERE DATE(datum) = :today3 AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY op3
                    ) AS sub
                    LEFT JOIN operators o ON o.number = sub.op
                    GROUP BY op, o.name
                    ORDER BY ibc_idag DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':today1' => $today, ':today2' => $today, ':today3' => $today]);
                $operatorer = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($operatorer as &$op) {
                    $op['user_id'] = (int)$op['op'];
                    $op['ibc_idag'] = (int)$op['ibc_idag'];
                }
                unset($op);
            } catch (\Exception $e) {
                error_log('DagligBriefingController::bemanning: ' . $e->getMessage());
            }

            $this->sendSuccess([
                'datum'       => $today,
                'operatorer'  => $operatorer,
                'antal'       => count($operatorer),
            ]);
        } catch (\Exception $e) {
            error_log('DagligBriefingController::bemanning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta bemanning', 500);
        }
    }
}
