<?php

/**
 * OperatorsbonusController
 * Beräknar individuell bonus per operatör baserat på IBC/h, kvalitet, närvaro och team-mål.
 * Transparent modell som operatörer och VD kan se.
 *
 * Endpoints via ?action=operatorsbonus&run=XXX:
 *
 *   GET  run=overview           — Sammanfattning: snittbonus, högsta/lägsta, total utbetald, antal operatörer
 *   GET  run=per-operator       (?period=dag|vecka|manad) — Bonusberäkning per operatör
 *   GET  run=konfiguration      — Hämta bonuskonfiguration
 *   POST run=spara-konfiguration — Uppdatera bonusparametrar (admin)
 *   GET  run=historik           (?operator_id, ?from, ?to) — Tidigare utbetalningar
 *   GET  run=simulering         (?ibc_per_timme, ?kvalitet, ?narvaro, ?team_mal) — Vad-om-analys
 */
class OperatorsbonusController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id'])) {
            $this->sendError('Inloggning krävs', 401);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'overview':        $this->getOverview();        break;
                case 'per-operator':    $this->getPerOperator();     break;
                case 'konfiguration':   $this->getKonfiguration();   break;
                case 'historik':        $this->getHistorik();        break;
                case 'simulering':      $this->getSimulering();      break;
                default:
                    $this->sendError('Okänd run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'), 404);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'spara-konfiguration':
                    $this->requireAdmin();
                    $this->sparaKonfiguration();
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

    private function requireAdmin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            $this->sendError('Sessionen har gått ut. Logga in igen.', 401);
            exit;
        }
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $this->sendError('Admin-behörighet krävs.', 403);
            exit;
        }
    }

    private function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sendSuccess(array $data): void {
        echo json_encode([
            'success' => true,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
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
                   AND table_name = 'bonus_konfiguration'"
            )->fetchColumn();
            if (!$check) {
                $migrationPath = __DIR__ . '/../migrations/2026-03-12_operatorsbonus.sql';
                $sql = file_get_contents($migrationPath);
                if ($sql === false) {
                    error_log('OperatorsbonusController::ensureTables: kunde inte läsa migrationsfil: ' . $migrationPath);
                } elseif ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('OperatorsbonusController::ensureTables: ' . $e->getMessage());
        }
    }

    /**
     * Ladda bonuskonfiguration från DB.
     */
    private function loadKonfig(): array {
        $defaults = [
            'ibc_per_timme' => ['vikt' => 40, 'mal_varde' => 12, 'max_bonus_kr' => 500, 'beskrivning' => 'IBC per timme'],
            'kvalitet'      => ['vikt' => 30, 'mal_varde' => 98, 'max_bonus_kr' => 400, 'beskrivning' => 'Kvalitet %'],
            'narvaro'       => ['vikt' => 20, 'mal_varde' => 100, 'max_bonus_kr' => 200, 'beskrivning' => 'Närvaro %'],
            'team_bonus'    => ['vikt' => 10, 'mal_varde' => 95, 'max_bonus_kr' => 100, 'beskrivning' => 'Team-mål %'],
        ];

        try {
            $stmt = $this->pdo->query("SELECT faktor, vikt, mal_varde, max_bonus_kr, beskrivning FROM bonus_konfiguration");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $config = [];
                foreach ($rows as $row) {
                    $config[$row['faktor']] = [
                        'vikt'         => (float)$row['vikt'],
                        'mal_varde'    => (float)$row['mal_varde'],
                        'max_bonus_kr' => (float)$row['max_bonus_kr'],
                        'beskrivning'  => $row['beskrivning'],
                    ];
                }
                return $config;
            }
        } catch (\PDOException $e) {
            error_log('OperatorsbonusController::loadKonfig: ' . $e->getMessage());
        }

        return $defaults;
    }

    /**
     * Beräkna bonus per faktor.
     * Formel: bonus = min(verkligt / mål, 1.0) × max_bonus_kr
     */
    private function beraknaBonus(float $verkligt, float $mal, float $maxBonus): float {
        if ($mal <= 0) return 0;
        $ratio = $verkligt / $mal;
        $ratio = min($ratio, 1.0); // Cap vid 100%
        return round($ratio * $maxBonus, 2);
    }

    /**
     * Hämta datumgränser baserat på period.
     */
    private function getPeriodBounds(string $period): array {
        $today = date('Y-m-d');
        switch ($period) {
            case 'vecka':
                $dt = new \DateTime($today);
                $dayOfWeek = (int)$dt->format('N');
                $from = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
                return ['from' => $from, 'to' => $today];
            case 'manad':
                $dt = new \DateTime($today);
                $from = $dt->format('Y-m-01');
                return ['from' => $from, 'to' => $today];
            default: // dag
                return ['from' => $today, 'to' => $today];
        }
    }

    /**
     * Hämta operatörer med produktionsdata för en period.
     * Returnerar array av operatörer med IBC/h, kvalitet, närvaro.
     */
    private function getOperatorData(string $fromDate, string $toDate): array {
        $operators = [];

        // Hämta aktiva operatörer
        try {
            $stmt = $this->pdo->query("SELECT id, number, name AS namn FROM operators WHERE active = 1 ORDER BY name ASC");
            $opRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('OperatorsbonusController::getOperatorData: ' . $e->getMessage());
            return [];
        }

        // --- Batch-hämta all produktionsdata i EN query (eliminerar N+1) ---
        $batchData = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    op_id,
                    COALESCE(SUM(shift_ibc), 0) AS total_ibc,
                    COALESCE(SUM(shift_runtime), 0) AS total_runtime_min,
                    COALESCE(SUM(shift_ok), 0) AS total_ok,
                    COALESCE(SUM(shift_ok), 0) + COALESCE(SUM(shift_ej_ok), 0) AS total_all,
                    COUNT(DISTINCT dag) AS unika_dagar
                FROM (
                    SELECT
                        op_id,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ibc,
                        MAX(COALESCE(runtime_plc, 0)) AS shift_runtime,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok,
                        DATE(MAX(datum)) AS dag
                    FROM (
                        SELECT op1 AS op_id, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc, datum
                        FROM rebotling_ibc
                        WHERE op1 IS NOT NULL AND op1 > 0

                          AND datum >= :from1 AND datum < DATE_ADD(:to1, INTERVAL 1 DAY)
                        UNION ALL
                        SELECT op2, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc, datum
                        FROM rebotling_ibc
                        WHERE op2 IS NOT NULL AND op2 > 0

                          AND datum >= :from2 AND datum < DATE_ADD(:to2, INTERVAL 1 DAY)
                        UNION ALL
                        SELECT op3, skiftraknare, ibc_ok, ibc_ej_ok, runtime_plc, datum
                        FROM rebotling_ibc
                        WHERE op3 IS NOT NULL AND op3 > 0

                          AND datum >= :from3 AND datum < DATE_ADD(:to3, INTERVAL 1 DAY)
                    ) AS all_ops
                    GROUP BY op_id, skiftraknare
                ) AS per_shift
                GROUP BY op_id
            ");
            $stmt->execute([
                ':from1' => $fromDate, ':to1' => $toDate,
                ':from2' => $fromDate, ':to2' => $toDate,
                ':from3' => $fromDate, ':to3' => $toDate,
            ]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $batchData[(int)$row['op_id']] = $row;
            }
        } catch (\PDOException $e) {
            error_log('OperatorsbonusController::getOperatorData batch: ' . $e->getMessage());
        }

        $arbetsDagar = $this->countWorkDays($fromDate, $toDate);

        foreach ($opRows as $op) {
            $opId     = (int)$op['id'];
            $opNumber = (int)$op['number'];
            $opNamn   = $op['namn'];

            $data = $batchData[$opNumber] ?? null;

            if ($data) {
                $totalIbc    = (int)$data['total_ibc'];
                $runtimeMin  = (float)$data['total_runtime_min'];
                $timmar      = $runtimeMin / 60.0;
                $ibcPerTimme = $timmar > 0 ? round($totalIbc / $timmar, 2) : 0;

                $ok    = (int)$data['total_ok'];
                $total = (int)$data['total_all'];
                $kvalitet = $total > 0 ? round(($ok / $total) * 100, 1) : 0;

                $dagar  = (int)$data['unika_dagar'];
                $narvaro = ($arbetsDagar > 0 && $dagar > 0)
                    ? round(min(($dagar / $arbetsDagar) * 100, 100), 1)
                    : 0;
            } else {
                $ibcPerTimme = 0;
                $kvalitet    = 0;
                $narvaro     = 0;
            }

            $operators[] = [
                'operator_id'    => $opId,
                'operator_namn'  => $opNamn,
                'ibc_per_timme'  => $ibcPerTimme,
                'kvalitet'       => $kvalitet,
                'narvaro'        => $narvaro,
            ];
        }

        return $operators;
    }

    /**
     * Räkna arbetsdagar (mån-fre) mellan två datum.
     */
    private function countWorkDays(string $from, string $to): int {
        $start = new \DateTime($from);
        $end   = new \DateTime($to);
        $count = 0;
        while ($start <= $end) {
            $dayOfWeek = (int)$start->format('N');
            if ($dayOfWeek <= 5) $count++;
            $start->modify('+1 day');
        }
        return max(1, $count);
    }

    /**
     * Hämta team-måluppfyllnad (linjemål) för perioden.
     * Gemensamt för alla operatörer.
     */
    private function getTeamMalProcent(string $from, string $to): float {
        try {
            // Hämta produktionsmål
            $stmt = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings ORDER BY id DESC LIMIT 1");
            $goalRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $dailyGoal = (int)($goalRow['rebotling_target'] ?? 200);

            if ($dailyGoal <= 0) $dailyGoal = 200;

            // Hämta faktisk produktion per dag
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    COALESCE(SUM(shift_ok), 0) AS ibc_ok
                FROM (
                    SELECT DATE(datum) AS datum, skiftraknare, MAX(COALESCE(ibc_ok, 0)) AS shift_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)

                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
            ");
            $stmt->execute([':from_date' => $from, ':to_date' => $to]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) return 0;

            $daysHit = 0;
            $totalDays = count($rows);
            foreach ($rows as $row) {
                if ((int)$row['ibc_ok'] >= $dailyGoal) {
                    $daysHit++;
                }
            }

            return $totalDays > 0 ? round(($daysHit / $totalDays) * 100, 1) : 0;
        } catch (\PDOException $e) {
            error_log('OperatorsbonusController::getTeamMalProcent: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Beräkna bonus för alla operatörer.
     */
    private function beraknaAllaBonus(string $period = 'dag'): array {
        $bounds = $this->getPeriodBounds($period);
        $konfig = $this->loadKonfig();
        $operators = $this->getOperatorData($bounds['from'], $bounds['to']);
        $teamMal   = $this->getTeamMalProcent($bounds['from'], $bounds['to']);

        $result = [];
        foreach ($operators as $op) {
            $ibcBonus      = $this->beraknaBonus($op['ibc_per_timme'], $konfig['ibc_per_timme']['mal_varde'], $konfig['ibc_per_timme']['max_bonus_kr']);
            $kvalitetBonus = $this->beraknaBonus($op['kvalitet'],      $konfig['kvalitet']['mal_varde'],      $konfig['kvalitet']['max_bonus_kr']);
            $narvaroBonus  = $this->beraknaBonus($op['narvaro'],       $konfig['narvaro']['mal_varde'],       $konfig['narvaro']['max_bonus_kr']);
            $teamBonus     = $this->beraknaBonus($teamMal,             $konfig['team_bonus']['mal_varde'],    $konfig['team_bonus']['max_bonus_kr']);
            $totalBonus    = $ibcBonus + $kvalitetBonus + $narvaroBonus + $teamBonus;

            $result[] = [
                'operator_id'     => $op['operator_id'],
                'operator_namn'   => $op['operator_namn'],
                'ibc_per_timme'   => $op['ibc_per_timme'],
                'kvalitet'        => $op['kvalitet'],
                'narvaro'         => $op['narvaro'],
                'team_mal'        => $teamMal,
                'bonus_ibc'       => $ibcBonus,
                'bonus_kvalitet'  => $kvalitetBonus,
                'bonus_narvaro'   => $narvaroBonus,
                'bonus_team'      => $teamBonus,
                'total_bonus'     => round($totalBonus, 2),
                // Progress-procent per faktor (för progress bars)
                'pct_ibc'         => $konfig['ibc_per_timme']['mal_varde'] > 0 ? round(min($op['ibc_per_timme'] / $konfig['ibc_per_timme']['mal_varde'] * 100, 100), 1) : 0,
                'pct_kvalitet'    => $konfig['kvalitet']['mal_varde'] > 0      ? round(min($op['kvalitet'] / $konfig['kvalitet']['mal_varde'] * 100, 100), 1) : 0,
                'pct_narvaro'     => $konfig['narvaro']['mal_varde'] > 0       ? round(min($op['narvaro'] / $konfig['narvaro']['mal_varde'] * 100, 100), 1) : 0,
                'pct_team'        => $konfig['team_bonus']['mal_varde'] > 0    ? round(min($teamMal / $konfig['team_bonus']['mal_varde'] * 100, 100), 1) : 0,
            ];
        }

        // Sortera efter total bonus (högst först)
        usort($result, function ($a, $b) {
            return $b['total_bonus'] <=> $a['total_bonus'];
        });

        return $result;
    }

    // =========================================================================
    // GET run=overview
    // =========================================================================

    private function getOverview(): void {
        try {
            $period = trim($_GET['period'] ?? 'dag');
            $operatorer = $this->beraknaAllaBonus($period);

            $antalKvalificerade = 0;
            $totalBonus = 0;
            $hogstaBonus = 0;
            $hogstaNamn = '';
            $lagstaBonus = PHP_INT_MAX;
            $lagstaNamn = '';
            $sumBonus = 0;

            foreach ($operatorer as $op) {
                if ($op['total_bonus'] > 0) {
                    $antalKvalificerade++;
                    $totalBonus += $op['total_bonus'];
                }
                $sumBonus += $op['total_bonus'];

                if ($op['total_bonus'] > $hogstaBonus) {
                    $hogstaBonus = $op['total_bonus'];
                    $hogstaNamn  = $op['operator_namn'];
                }
                if ($op['total_bonus'] < $lagstaBonus) {
                    $lagstaBonus = $op['total_bonus'];
                    $lagstaNamn  = $op['operator_namn'];
                }
            }

            $antalOp = count($operatorer);
            $snittBonus = $antalOp > 0 ? round($sumBonus / $antalOp, 2) : 0;

            if ($antalOp === 0) {
                $lagstaBonus = 0;
                $lagstaNamn  = '';
            }

            $this->sendSuccess([
                'period'              => $period,
                'snitt_bonus'         => $snittBonus,
                'hogsta_bonus'        => round($hogstaBonus, 2),
                'hogsta_namn'         => $hogstaNamn,
                'lagsta_bonus'        => round($lagstaBonus, 2),
                'lagsta_namn'         => $lagstaNamn,
                'total_utbetald'      => round($totalBonus, 2),
                'antal_kvalificerade' => $antalKvalificerade,
                'antal_operatorer'    => $antalOp,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorsbonusController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // =========================================================================
    // GET run=per-operator
    // =========================================================================

    private function getPerOperator(): void {
        try {
            $period = trim($_GET['period'] ?? 'dag');
            $operatorer = $this->beraknaAllaBonus($period);
            $konfig     = $this->loadKonfig();
            $bounds     = $this->getPeriodBounds($period);

            $this->sendSuccess([
                'period'     => $period,
                'from'       => $bounds['from'],
                'to'         => $bounds['to'],
                'konfig'     => $konfig,
                'operatorer' => $operatorer,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorsbonusController::getPerOperator: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsdata', 500);
        }
    }

    // =========================================================================
    // GET run=konfiguration
    // =========================================================================

    private function getKonfiguration(): void {
        try {
            $konfig = $this->loadKonfig();
            $result = [];
            $faktorLabels = [
                'ibc_per_timme' => 'IBC per timme',
                'kvalitet'      => 'Kvalitet (%)',
                'narvaro'       => 'Närvaro (%)',
                'team_bonus'    => 'Team-mål (%)',
            ];

            foreach ($konfig as $faktor => $val) {
                $result[] = [
                    'faktor'       => $faktor,
                    'label'        => $faktorLabels[$faktor] ?? $faktor,
                    'vikt'         => $val['vikt'],
                    'mal_varde'    => $val['mal_varde'],
                    'max_bonus_kr' => $val['max_bonus_kr'],
                    'beskrivning'  => $val['beskrivning'],
                ];
            }

            $maxTotal = array_sum(array_column($result, 'max_bonus_kr'));

            $this->sendSuccess([
                'konfig'    => $result,
                'max_total' => $maxTotal,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorsbonusController::getKonfiguration: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta konfiguration', 500);
        }
    }

    // =========================================================================
    // POST run=spara-konfiguration
    // Body: [{ faktor, vikt, mal_varde, max_bonus_kr }]
    // =========================================================================

    private function sparaKonfiguration(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!is_array($data) || empty($data)) {
            $this->sendError('Ogiltig data — förväntade array av bonusparametrar');
            return;
        }

        $validFaktorer = ['ibc_per_timme', 'kvalitet', 'narvaro', 'team_bonus'];
        $userId = $this->currentUserId();

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE bonus_konfiguration
                 SET vikt = :vikt, mal_varde = :mal_varde, max_bonus_kr = :max_bonus_kr, updated_by = :updated_by
                 WHERE faktor = :faktor"
            );

            $updated = 0;
            foreach ($data as $item) {
                $faktor = trim($item['faktor'] ?? '');
                if (!in_array($faktor, $validFaktorer, true)) continue;

                $vikt     = max(0, min(100, (float)($item['vikt'] ?? 0)));
                $malVarde = max(0, (float)($item['mal_varde'] ?? 0));
                $maxBonus = max(0, (float)($item['max_bonus_kr'] ?? 0));

                $stmt->execute([
                    ':faktor'       => $faktor,
                    ':vikt'         => $vikt,
                    ':mal_varde'    => $malVarde,
                    ':max_bonus_kr' => $maxBonus,
                    ':updated_by'   => $userId,
                ]);
                $updated++;
            }

            $this->pdo->commit();
            $this->sendSuccess([
                'message' => "Konfiguration uppdaterad ({$updated} faktorer)",
                'updated' => $updated,
            ]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('OperatorsbonusController::sparaKonfiguration: ' . $e->getMessage());
            $this->sendError('Kunde inte spara konfiguration', 500);
        }
    }

    // =========================================================================
    // GET run=historik
    // =========================================================================

    private function getHistorik(): void {
        try {
            $opId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;
            $from = trim($_GET['from'] ?? '');
            $to   = trim($_GET['to']   ?? '');

            // Validera att from <= to, annars byt plats
            if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from > $to) {
                [$from, $to] = [$to, $from];
            }
            // Begränsa till max 365 dagar
            if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                try {
                    $diffDays = (int)(new \DateTime($from))->diff(new \DateTime($to))->days;
                    if ($diffDays > 365) {
                        $from = date('Y-m-d', strtotime($to . ' -365 days'));
                    }
                } catch (\Exception $e) {
                    error_log('OperatorsbonusController: datumberäkning fallback — ' . $e->getMessage());
                    $from = date('Y-m-d', strtotime('-30 days'));
                    $to   = date('Y-m-d');
                }
            }

            $where = '1=1';
            $params = [];

            if ($opId > 0) {
                $where .= ' AND operator_id = :op_id';
                $params[':op_id'] = $opId;
            }
            if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $where .= ' AND period_start >= :from_date';
                $params[':from_date'] = $from;
            }
            if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $where .= ' AND period_slut <= :to_date';
                $params[':to_date'] = $to;
            }

            $stmt = $this->pdo->prepare("
                SELECT id, operator_id, operator_namn, period_start, period_slut,
                       ibc_per_timme_snitt, kvalitet_procent, narvaro_procent, team_mal_procent,
                       total_bonus, skapad_at
                FROM bonus_utbetalning
                WHERE {$where}
                ORDER BY period_start DESC, operator_namn ASC
                LIMIT 500
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->sendSuccess([
                'utbetalningar' => $rows,
                'total'         => count($rows),
            ]);
        } catch (\PDOException $e) {
            error_log('OperatorsbonusController::getHistorik: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta historik', 500);
        }
    }

    // =========================================================================
    // GET run=simulering
    // =========================================================================

    private function getSimulering(): void {
        try {
            $konfig = $this->loadKonfig();

            $ibcPerTimme = isset($_GET['ibc_per_timme']) ? max(0, min(9999, (float)$_GET['ibc_per_timme'])) : $konfig['ibc_per_timme']['mal_varde'];
            $kvalitet    = isset($_GET['kvalitet'])      ? max(0, min(100, (float)$_GET['kvalitet']))      : $konfig['kvalitet']['mal_varde'];
            $narvaro     = isset($_GET['narvaro'])        ? max(0, min(100, (float)$_GET['narvaro']))       : $konfig['narvaro']['mal_varde'];
            $teamMal     = isset($_GET['team_mal'])       ? max(0, min(9999, (float)$_GET['team_mal']))      : $konfig['team_bonus']['mal_varde'];

            $bonusIbc      = $this->beraknaBonus($ibcPerTimme, $konfig['ibc_per_timme']['mal_varde'], $konfig['ibc_per_timme']['max_bonus_kr']);
            $bonusKvalitet = $this->beraknaBonus($kvalitet,    $konfig['kvalitet']['mal_varde'],      $konfig['kvalitet']['max_bonus_kr']);
            $bonusNarvaro  = $this->beraknaBonus($narvaro,     $konfig['narvaro']['mal_varde'],       $konfig['narvaro']['max_bonus_kr']);
            $bonusTeam     = $this->beraknaBonus($teamMal,     $konfig['team_bonus']['mal_varde'],    $konfig['team_bonus']['max_bonus_kr']);
            $total         = $bonusIbc + $bonusKvalitet + $bonusNarvaro + $bonusTeam;

            $maxTotal = $konfig['ibc_per_timme']['max_bonus_kr'] + $konfig['kvalitet']['max_bonus_kr'] + $konfig['narvaro']['max_bonus_kr'] + $konfig['team_bonus']['max_bonus_kr'];

            $this->sendSuccess([
                'input' => [
                    'ibc_per_timme' => $ibcPerTimme,
                    'kvalitet'      => $kvalitet,
                    'narvaro'       => $narvaro,
                    'team_mal'      => $teamMal,
                ],
                'bonus_ibc'       => $bonusIbc,
                'bonus_kvalitet'  => $bonusKvalitet,
                'bonus_narvaro'   => $bonusNarvaro,
                'bonus_team'      => $bonusTeam,
                'total_bonus'     => round($total, 2),
                'max_total'       => $maxTotal,
                'pct_av_max'      => $maxTotal > 0 ? round(($total / $maxTotal) * 100, 1) : 0,
                'konfig'          => $konfig,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorsbonusController::getSimulering: ' . $e->getMessage());
            $this->sendError('Kunde inte köra simulering', 500);
        }
    }
}
