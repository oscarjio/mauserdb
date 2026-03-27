<?php
/**
 * MalhistorikController.php
 * Målhistorik — visar hur produktionsmål har ändrats över tid och
 * vilken effekt måländringar har haft på faktisk prestation.
 *
 * Endpoints via ?action=malhistorik&run=XXX:
 *   run=goal-history
 *       Hämtar alla rader från rebotling_goal_history sorterade på changed_at.
 *       Inkluderar vem som ändrade, gammalt/nytt mål, procentuell ändring, tidpunkt.
 *
 *   run=goal-impact
 *       För varje måländring: snitt IBC/h och måluppfyllnad 7 dagar före och
 *       7 dagar efter ändringen. Returnerar impact-data med färgkodning.
 *
 * Auth: session_id krävs (401 om ej inloggad).
 * Tabeller: rebotling_goal_history, rebotling_ibc
 */
class MalhistorikController {
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
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            case 'goal-history': $this->getGoalHistory(); break;
            case 'goal-impact':  $this->getGoalImpact();  break;
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
     * Beräkna snitt IBC/h och måluppfyllnad för ett datumintervall.
     * Returnerar ibc_per_timme och malprocent.
     */
    private function calcIbcPerTimme(string $fromDate, string $toDate, int $mal): array {
        // Summera max IBC/skift per dag (samma mönster som DagligSammanfattningController)
        $stmt = $this->pdo->prepare(
            "SELECT
                DATE(datum) AS dag,
                SUM(max_ibc) AS dag_ibc,
                SUM(runtime_min) AS dag_runtime
             FROM (
                SELECT
                    DATE(datum) AS datum_dag,
                    skiftraknare,
                    MAX(ibc_ok) AS max_ibc,
                    MAX(runtime_plc) AS runtime_min
                FROM rebotling_ibc
                WHERE datum >= :from AND datum < DATE_ADD(:to, INTERVAL 1 DAY)
                GROUP BY DATE(datum), skiftraknare
                HAVING COUNT(*) > 1
             ) skiften
             GROUP BY dag
             HAVING dag_runtime > 0"
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'ibc_per_timme'  => 0.0,
                'malprocent'     => 0.0,
                'antal_dagar'    => 0,
                'total_ibc'      => 0,
                'total_runtime_min' => 0,
            ];
        }

        $totalIbc     = 0;
        $totalRuntime = 0;
        foreach ($rows as $r) {
            $totalIbc     += (int)$r['dag_ibc'];
            $totalRuntime += (int)$r['dag_runtime'];
        }

        $ibcPerTimme = $totalRuntime > 0
            ? round($totalIbc / ($totalRuntime / 60), 1)
            : 0.0;

        $malprocent = $mal > 0
            ? round(($ibcPerTimme / $mal) * 100, 1)
            : 0.0;

        return [
            'ibc_per_timme'     => $ibcPerTimme,
            'malprocent'        => $malprocent,
            'antal_dagar'       => count($rows),
            'total_ibc'         => $totalIbc,
            'total_runtime_min' => $totalRuntime,
        ];
    }

    // ================================================================
    // run=goal-history
    // ================================================================

    private function getGoalHistory(): void {
        try {
            // Kontrollera att tabellen finns
            $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_goal_history'");
            if (!$check || $check->rowCount() === 0) {
                $this->sendSuccess([
                    'andringar'       => [],
                    'aktuellt_mal'    => null,
                    'antal_andringar' => 0,
                    'senaste_andring' => null,
                ]);
                return;
            }

            $stmt = $this->pdo->query(
                "SELECT
                    id,
                    goal_type,
                    value,
                    changed_by,
                    changed_at
                 FROM rebotling_goal_history
                 ORDER BY changed_at ASC
                 LIMIT 1000"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Berika med gammalt mål och procentuell ändring
            $andringar = [];
            $prevValue = null;

            foreach ($rows as $r) {
                $nytt  = (int)$r['value'];
                $gammalt = $prevValue;

                $procAndring = null;
                if ($gammalt !== null && $gammalt > 0) {
                    $procAndring = round((($nytt - $gammalt) / $gammalt) * 100, 1);
                }

                $andringar[] = [
                    'id'            => (int)$r['id'],
                    'goal_type'     => $r['goal_type'],
                    'nytt_mal'      => $nytt,
                    'gammalt_mal'   => $gammalt,
                    'proc_andring'  => $procAndring,
                    'andrad_av'     => $r['changed_by'] ?? 'Okänd',
                    'andrad_vid'    => $r['changed_at'],
                    'riktning'      => $procAndring === null ? 'foerst'
                                     : ($procAndring > 0 ? 'upp'
                                     : ($procAndring < 0 ? 'ner' : 'oforandrad')),
                ];

                $prevValue = $nytt;
            }

            // Aktuellt mål = senaste raden
            $aktuellt = !empty($andringar) ? end($andringar)['nytt_mal'] : null;
            $senaste  = !empty($andringar) ? end($andringar)['andrad_vid'] : null;

            $this->sendSuccess([
                'andringar'       => $andringar,
                'aktuellt_mal'    => $aktuellt,
                'antal_andringar' => count($andringar),
                'senaste_andring' => $senaste,
            ]);

        } catch (\Exception $e) {
            error_log('MalhistorikController::getGoalHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta målhistorik', 500);
        }
    }

    // ================================================================
    // run=goal-impact
    // ================================================================

    private function getGoalImpact(): void {
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_goal_history'");
            if (!$check || $check->rowCount() === 0) {
                $this->sendSuccess(['impact' => []]);
                return;
            }

            $stmt = $this->pdo->query(
                "SELECT id, goal_type, value, changed_by, changed_at
                 FROM rebotling_goal_history
                 ORDER BY changed_at ASC
                 LIMIT 1000"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->sendSuccess(['impact' => []]);
                return;
            }

            $impact  = [];
            $prevVal = null;

            foreach ($rows as $r) {
                $andringsDatum = date('Y-m-d', strtotime($r['changed_at']));
                $nyttMal       = (int)$r['value'];

                // 7 dagar före ändringen (använd föregående mål som referens)
                $foreDatum = date('Y-m-d', strtotime($andringsDatum . ' -7 days'));
                $foreTom   = date('Y-m-d', strtotime($andringsDatum . ' -1 day'));

                // 7 dagar efter ändringen
                $efterFran = date('Y-m-d', strtotime($andringsDatum . ' +1 day'));
                $efterTom  = date('Y-m-d', strtotime($andringsDatum . ' +7 days'));

                // Referensmål för "före"-perioden = det gamla målet, om inget finns = det nya
                $malFore  = $prevVal ?? $nyttMal;
                $malEfter = $nyttMal;

                $foreData  = $this->calcIbcPerTimme($foreDatum, $foreTom, $malFore);
                $efterData = $this->calcIbcPerTimme($efterFran, $efterTom, $malEfter);

                // Förändring i IBC/h
                $diffIbcH    = null;
                $diffProc    = null;
                $effekt      = 'ingen-data';

                if ($foreData['ibc_per_timme'] > 0 && $efterData['ibc_per_timme'] > 0) {
                    $diffIbcH = round($efterData['ibc_per_timme'] - $foreData['ibc_per_timme'], 1);
                    $diffProc = round((($efterData['ibc_per_timme'] - $foreData['ibc_per_timme'])
                                      / $foreData['ibc_per_timme']) * 100, 1);
                    if ($diffIbcH > 0.5)       $effekt = 'forbattring';
                    elseif ($diffIbcH < -0.5)  $effekt = 'forsämring';
                    else                        $effekt = 'oforandrad';
                } elseif ($efterData['ibc_per_timme'] > 0) {
                    $effekt = 'ny-start';
                }

                $procAndring = null;
                if ($prevVal !== null && $prevVal > 0) {
                    $procAndring = round((($nyttMal - $prevVal) / $prevVal) * 100, 1);
                }

                $impact[] = [
                    'id'             => (int)$r['id'],
                    'goal_type'      => $r['goal_type'],
                    'gammalt_mal'    => $prevVal,
                    'nytt_mal'       => $nyttMal,
                    'proc_malAndring' => $procAndring,
                    'andrad_av'      => $r['changed_by'] ?? 'Okänd',
                    'andrad_vid'     => $r['changed_at'],
                    'fore' => [
                        'period_fran'    => $foreDatum,
                        'period_tom'     => $foreTom,
                        'ibc_per_timme'  => $foreData['ibc_per_timme'],
                        'malprocent'     => $foreData['malprocent'],
                        'antal_dagar'    => $foreData['antal_dagar'],
                    ],
                    'efter' => [
                        'period_fran'    => $efterFran,
                        'period_tom'     => $efterTom,
                        'ibc_per_timme'  => $efterData['ibc_per_timme'],
                        'malprocent'     => $efterData['malprocent'],
                        'antal_dagar'    => $efterData['antal_dagar'],
                    ],
                    'diff_ibc_per_h' => $diffIbcH,
                    'diff_proc'      => $diffProc,
                    'effekt'         => $effekt,
                ];

                $prevVal = $nyttMal;
            }

            $this->sendSuccess(['impact' => $impact]);

        } catch (\Exception $e) {
            error_log('MalhistorikController::getGoalImpact: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna impact-data', 500);
        }
    }
}
