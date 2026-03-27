<?php
/**
 * RebotlingSammanfattningController.php
 * VD:ns landing page — sammanfattning av alla rebotling-KPI:er pa en sida.
 *
 * Endpoints via ?action=rebotling-sammanfattning&run=XXX:
 *   - run=overview       -> Alla KPI:er i ett anrop (dagens produktion, OEE, kassation, aktiva larm, drifttid)
 *   - run=produktion-7d  -> Senaste 7 dagars produktion (for stapeldiagram)
 *   - run=maskin-status   -> Status per maskin/station med OEE
 *
 * Tabeller: rebotling_ibc, maskin_oee_daglig, maskin_register, avvikelselarm (om de finns)
 */
class RebotlingSammanfattningController {
    private $pdo;

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
            case 'overview':       $this->getOverview();      break;
            case 'produktion-7d':  $this->getProduktion7d();  break;
            case 'maskin-status':  $this->getMaskinStatus();   break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // Helpers
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

    private function tableExists(string $tableName): bool {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE :tbl");
            $stmt->execute([':tbl' => $tableName]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            error_log("RebotlingSammanfattningController::tableExists({$tableName}): " . $e->getMessage());
            return false;
        }
    }

    // ================================================================
    // run=overview — Alla KPI:er i ett anrop
    // ================================================================

    private function getOverview(): void {
        try {
            $idag = date('Y-m-d');

            // 1) Dagens produktion fran rebotling_ibc
            $dagensProduktion = 0;
            $dagensOk = 0;
            $dagensEjOk = 0;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(shift_ok), 0) AS ibc_ok,
                        COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
                    FROM (
                        SELECT
                            skiftraknare,
                            MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                            MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                        FROM rebotling_ibc
                        WHERE datum >= :idag AND datum < DATE_ADD(:idagb, INTERVAL 1 DAY)
                          AND skiftraknare IS NOT NULL
                        GROUP BY skiftraknare
                    ) AS per_shift
                ");
                $stmt->execute([':idag' => $idag, ':idagb' => $idag]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $dagensOk    = (int)($row['ibc_ok'] ?? 0);
                    $dagensEjOk  = (int)($row['ibc_ej_ok'] ?? 0);
                    $dagensProduktion = $dagensOk + $dagensEjOk;
                }
            } catch (\PDOException $e) {
                error_log('RebotlingSammanfattningController::overview produktion: ' . $e->getMessage());
            }

            // 2) Kassation %
            $kassationPct = $dagensProduktion > 0
                ? round(($dagensEjOk / $dagensProduktion) * 100, 1)
                : 0;

            // 3) OEE fran maskin_oee_daglig (snittet idag)
            $oee = null;
            if ($this->tableExists('maskin_oee_daglig')) {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT AVG(oee_pct) AS avg_oee
                        FROM maskin_oee_daglig
                        WHERE datum = :idag
                    ");
                    $stmt->execute([':idag' => $idag]);
                    $val = $stmt->fetchColumn();
                    if ($val !== false && $val !== null) {
                        $oee = round((float)$val, 1);
                    }
                } catch (\PDOException $e) {
                    error_log('RebotlingSammanfattningController::overview OEE: ' . $e->getMessage());
                }
            }

            // 4) Drifttid % fran maskin_oee_daglig
            $drifttidPct = null;
            if ($this->tableExists('maskin_oee_daglig')) {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT
                            COALESCE(SUM(drifttid_min), 0)     AS total_drift,
                            COALESCE(SUM(planerad_tid_min), 0) AS total_planerad
                        FROM maskin_oee_daglig
                        WHERE datum = :idag
                    ");
                    $stmt->execute([':idag' => $idag]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && (float)$row['total_planerad'] > 0) {
                        $drifttidPct = round(((float)$row['total_drift'] / (float)$row['total_planerad']) * 100, 1);
                    }
                } catch (\PDOException $e) {
                    error_log('RebotlingSammanfattningController::overview drifttid: ' . $e->getMessage());
                }
            }

            // 5) Aktiva larm fran avvikelselarm
            $aktivaLarm = 0;
            $senasteLarm = [];
            if ($this->tableExists('avvikelselarm')) {
                try {
                    $aktivaLarm = (int)$this->pdo->query(
                        "SELECT COUNT(*) FROM avvikelselarm WHERE kvitterad = 0"
                    )->fetchColumn();

                    $stmt = $this->pdo->query("
                        SELECT id, typ, allvarlighetsgrad, meddelande, tidsstampel
                        FROM avvikelselarm
                        WHERE kvitterad = 0
                        ORDER BY FIELD(allvarlighetsgrad, 'kritisk', 'varning', 'info'), tidsstampel DESC
                        LIMIT 5
                    ");
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $senasteLarm[] = [
                            'id'                => (int)$row['id'],
                            'typ'               => $row['typ'],
                            'allvarlighetsgrad' => $row['allvarlighetsgrad'],
                            'meddelande'        => $row['meddelande'],
                            'tidsstampel'       => $row['tidsstampel'],
                        ];
                    }
                } catch (\PDOException $e) {
                    error_log('RebotlingSammanfattningController::overview larm: ' . $e->getMessage());
                }
            }

            $this->sendSuccess([
                'datum'              => $idag,
                'dagens_produktion'  => $dagensProduktion,
                'dagens_ok'          => $dagensOk,
                'dagens_ej_ok'       => $dagensEjOk,
                'kassation_pct'      => $kassationPct,
                'oee_pct'            => $oee,
                'drifttid_pct'       => $drifttidPct,
                'aktiva_larm'        => $aktivaLarm,
                'senaste_larm'       => $senasteLarm,
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingSammanfattningController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta oversikt', 500);
        }
    }

    // ================================================================
    // run=produktion-7d — Senaste 7 dagars produktion
    // ================================================================

    private function getProduktion7d(): void {
        try {
            $toDate   = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime('-6 days'));

            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg komplett 7-dagars sekvens (inkludera dagar utan data)
            $dataMap = [];
            foreach ($rows as $r) {
                $dataMap[$r['dag']] = [
                    'ibc_ok'    => (int)$r['ibc_ok'],
                    'ibc_ej_ok' => (int)$r['ibc_ej_ok'],
                ];
            }

            $series = [];
            for ($i = 6; $i >= 0; $i--) {
                $dag = date('Y-m-d', strtotime("-{$i} days"));
                $ok    = $dataMap[$dag]['ibc_ok'] ?? 0;
                $ejOk  = $dataMap[$dag]['ibc_ej_ok'] ?? 0;
                $series[] = [
                    'datum'     => $dag,
                    'label'     => substr($dag, 5), // MM-DD
                    'ibc_ok'    => $ok,
                    'ibc_ej_ok' => $ejOk,
                    'total'     => $ok + $ejOk,
                ];
            }

            $this->sendSuccess([
                'from'   => $fromDate,
                'to'     => $toDate,
                'series' => $series,
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingSammanfattningController::getProduktion7d: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta produktionsdata', 500);
        }
    }

    // ================================================================
    // run=maskin-status — Status per maskin/station med OEE
    // ================================================================

    private function getMaskinStatus(): void {
        try {
            $idag = date('Y-m-d');
            $maskiner = [];

            if ($this->tableExists('maskin_oee_daglig') && $this->tableExists('maskin_register')) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        mr.id            AS maskin_id,
                        mr.namn          AS maskin_namn,
                        d.oee_pct,
                        d.tillganglighet_pct,
                        d.drifttid_min,
                        d.planerad_tid_min,
                        d.stopptid_min,
                        d.total_output,
                        d.kassation
                    FROM maskin_register mr
                    LEFT JOIN maskin_oee_daglig d ON d.maskin_id = mr.id AND d.datum = :idag
                    WHERE mr.aktiv = 1
                    ORDER BY mr.namn ASC
                ");
                $stmt->execute([':idag' => $idag]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $oee = $row['oee_pct'] !== null ? round((float)$row['oee_pct'], 1) : null;

                    // Status: gron = OEE >= 70, gul = 50-69, rod = < 50 eller ingen data
                    $status = 'rod';
                    if ($oee !== null) {
                        if ($oee >= 70) $status = 'gron';
                        elseif ($oee >= 50) $status = 'gul';
                    }

                    $maskiner[] = [
                        'maskin_id'        => (int)$row['maskin_id'],
                        'maskin_namn'      => $row['maskin_namn'],
                        'oee'              => $oee,
                        'tillganglighet'   => $row['tillganglighet_pct'] !== null ? round((float)$row['tillganglighet_pct'], 1) : null,
                        'drifttid_min'     => $row['drifttid_min'] !== null ? round((float)$row['drifttid_min'], 1) : null,
                        'planerad_tid_min' => $row['planerad_tid_min'] !== null ? round((float)$row['planerad_tid_min'], 1) : null,
                        'stopptid_min'     => $row['stopptid_min'] !== null ? round((float)$row['stopptid_min'], 1) : null,
                        'total_output'     => $row['total_output'] !== null ? (int)$row['total_output'] : null,
                        'kassation'        => $row['kassation'] !== null ? (int)$row['kassation'] : null,
                        'status'           => $status,
                    ];
                }
            }

            $this->sendSuccess([
                'datum'    => $idag,
                'maskiner' => $maskiner,
            ]);
        } catch (\PDOException $e) {
            error_log('RebotlingSammanfattningController::getMaskinStatus: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta maskinstatus', 500);
        }
    }
}
