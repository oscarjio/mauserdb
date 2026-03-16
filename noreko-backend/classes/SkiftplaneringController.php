<?php

/**
 * SkiftplaneringController
 * Skiftplanering — bemanningsöversikt: vilka operatörer jobbar vilket skift,
 * kapacitetsplanering, varning vid underbemanning.
 *
 * Endpoints via ?action=skiftplanering&run=XXX:
 *
 *   GET  run=overview       — KPI:er: antal operatörer totalt, bemanningsgrad idag, underbemanning, nästa skiftbyte
 *   GET  run=schedule       — (?week=YYYY-Wxx) Veckoschema: operatörer per skift/dag
 *   GET  run=shift-detail   — (?shift=FM/EM/NATT&date=YYYY-MM-DD) Detalj per skift
 *   POST run=assign         — Tilldela operatör till skift/dag
 *   POST run=unassign       — Ta bort operatör från skift/dag
 *   GET  run=capacity       — Kapacitetsplanering baserat på historisk IBC/h
 *   GET  run=operators      — Lista tillgängliga operatörer (för dropdown)
 */
class SkiftplaneringController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        // Alla endpoints kräver inloggning
        $this->requireLogin();

        if ($method === 'GET') {
            switch ($run) {
                case 'overview':      $this->getOverview();     break;
                case 'schedule':      $this->getSchedule();     break;
                case 'shift-detail':  $this->getShiftDetail();  break;
                case 'capacity':      $this->getCapacity();     break;
                case 'operators':     $this->getOperators();    break;
                default:
                    $this->sendError('Okänd run-parameter', 404);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'assign':   $this->assignOperator();   break;
                case 'unassign': $this->unassignOperator(); break;
                default:
                    $this->sendError('Okänd run-parameter', 404);
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
            session_start(['read_and_close' => true]);
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sessionen har gått ut. Logga in igen.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
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
                   AND table_name = 'skift_konfiguration'"
            )->fetchColumn();
            if (!$check) {
                $sql = file_get_contents(__DIR__ . '/../migrations/2026-03-12_skiftplanering.sql');
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController ensureTables: ' . $e->getMessage());
        }
    }

    /**
     * Hämta alla skift-konfigurationer
     */
    private function getShiftConfigs(): array {
        $stmt = $this->pdo->query(
            "SELECT skift_typ, start_tid, slut_tid, min_bemanning, max_bemanning
             FROM skift_konfiguration
             ORDER BY FIELD(skift_typ, 'FM', 'EM', 'NATT')"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Hämta namn för operatör (från operators-tabellen om den finns)
     */
    private function getOperatorName(int $id): string {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) return $row['name'];
            // Fallback: try by id
            $stmt2 = $this->pdo->prepare("SELECT name FROM operators WHERE id = ?");
            $stmt2->execute([$id]);
            $row2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            return $row2 ? $row2['name'] : 'Operatör #' . $id;
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController getOperatorName: ' . $e->getMessage());
            return 'Operatör #' . $id;
        }
    }

    /**
     * Beräkna aktuellt skift baserat på klockan
     */
    private function getCurrentShift(): string {
        $hour = (int)date('H');
        if ($hour >= 6 && $hour < 14)  return 'FM';
        if ($hour >= 14 && $hour < 22) return 'EM';
        return 'NATT';
    }

    /**
     * Beräkna nästa skiftbyte
     */
    private function getNextShiftChange(): string {
        $hour = (int)date('H');
        $min  = (int)date('i');
        if ($hour >= 6 && $hour < 14) {
            $minutesLeft = (14 - $hour) * 60 - $min;
            return $this->formatMinutesLeft($minutesLeft) . ' (kl 14:00)';
        }
        if ($hour >= 14 && $hour < 22) {
            $minutesLeft = (22 - $hour) * 60 - $min;
            return $this->formatMinutesLeft($minutesLeft) . ' (kl 22:00)';
        }
        // NATT: nästa byte kl 06
        if ($hour >= 22) {
            $minutesLeft = (30 - $hour) * 60 - $min; // 30 = 24+6
        } else {
            $minutesLeft = (6 - $hour) * 60 - $min;
        }
        return $this->formatMinutesLeft($minutesLeft) . ' (kl 06:00)';
    }

    private function formatMinutesLeft(int $min): string {
        if ($min < 60) return $min . ' min';
        $h = floor($min / 60);
        $m = $min % 60;
        return $h . ' h ' . ($m > 0 ? $m . ' min' : '');
    }

    /**
     * Parsa veckoparameter (?week=2026-W11) och returnera [monday, sunday]
     */
    private function parseWeek(?string $weekParam): array {
        if ($weekParam && preg_match('/^(\d{4})-W(\d{2})$/', $weekParam, $m)) {
            $year = (int)$m[1];
            $week = (int)$m[2];
            $dt = new \DateTime();
            $dt->setISODate($year, $week);
            $monday = $dt->format('Y-m-d');
            $dt->modify('+6 days');
            $sunday = $dt->format('Y-m-d');
            return [$monday, $sunday];
        }
        // Default: aktuell vecka
        $dayOfWeek = (int)date('N') - 1; // 0=Mon, 6=Sun
        $monday = date('Y-m-d', strtotime("-{$dayOfWeek} days"));
        $sunday = date('Y-m-d', strtotime($monday . ' +6 days'));
        return [$monday, $sunday];
    }

    // =========================================================================
    // GET run=overview
    // KPI:er: operatörer totalt, bemanningsgrad idag, underbemanning, nästa skiftbyte
    // =========================================================================

    private function getOverview(): void {
        try {
            $today = date('Y-m-d');
            $configs = $this->getShiftConfigs();

            // Antal unika operatörer inplanerade denna vecka
            list($monday, $sunday) = $this->parseWeek(null);
            $totalStmt = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT operator_id) FROM skift_schema WHERE datum BETWEEN ? AND ?"
            );
            $totalStmt->execute([$monday, $sunday]);
            $operatorerTotalt = (int)$totalStmt->fetchColumn();

            // Bemanningsgrad idag: faktisk / optimal (sum of min_bemanning per skift)
            $todayStmt = $this->pdo->prepare(
                "SELECT skift_typ, COUNT(*) AS antal
                 FROM skift_schema WHERE datum = ?
                 GROUP BY skift_typ"
            );
            $todayStmt->execute([$today]);
            $todayRows = $todayStmt->fetchAll(\PDO::FETCH_ASSOC);
            $todayMap = [];
            foreach ($todayRows as $r) {
                $todayMap[$r['skift_typ']] = (int)$r['antal'];
            }

            $totalBemanning = 0;
            $totalMinBemanning = 0;
            $underbemanning = 0;

            foreach ($configs as $c) {
                $typ = $c['skift_typ'];
                $faktiskt = $todayMap[$typ] ?? 0;
                $minKrav = (int)$c['min_bemanning'];
                $totalBemanning += $faktiskt;
                $totalMinBemanning += $minKrav;
                if ($faktiskt < $minKrav) {
                    $underbemanning++;
                }
            }

            $bemanningsgrad = $totalMinBemanning > 0
                ? round(($totalBemanning / $totalMinBemanning) * 100, 0)
                : 0;

            $nastaSkiftbyte = $this->getNextShiftChange();

            $this->sendSuccess([
                'data' => [
                    'operatorer_totalt'  => $operatorerTotalt,
                    'bemanningsgrad'     => (int)$bemanningsgrad,
                    'underbemanning'     => $underbemanning,
                    'nasta_skiftbyte'    => $nastaSkiftbyte,
                    'aktivt_skift'       => $this->getCurrentShift(),
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // =========================================================================
    // GET run=schedule (?week=YYYY-Wxx)
    // Veckoschema: per skift och dag, vilka operatörer
    // =========================================================================

    private function getSchedule(): void {
        try {
            $weekParam = trim($_GET['week'] ?? '');
            list($monday, $sunday) = $this->parseWeek($weekParam ?: null);

            $configs = $this->getShiftConfigs();

            // Hämta alla tilldelningar för veckan
            $stmt = $this->pdo->prepare(
                "SELECT ss.id, ss.operator_id, ss.skift_typ, ss.datum
                 FROM skift_schema ss
                 WHERE ss.datum BETWEEN ? AND ?
                 ORDER BY ss.datum, FIELD(ss.skift_typ, 'FM', 'EM', 'NATT'), ss.operator_id"
            );
            $stmt->execute([$monday, $sunday]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Samla operatörsnamn
            $operatorIds = array_unique(array_column($rows, 'operator_id'));
            $operatorNames = [];
            if (!empty($operatorIds)) {
                $placeholders = implode(',', array_fill(0, count($operatorIds), '?'));
                try {
                    $opStmt = $this->pdo->prepare(
                        "SELECT id, number, name FROM operators WHERE number IN ($placeholders) OR id IN ($placeholders)"
                    );
                    $vals = array_values($operatorIds);
                    $opStmt->execute(array_merge($vals, $vals));
                    while ($op = $opStmt->fetch(\PDO::FETCH_ASSOC)) {
                        $operatorNames[(int)$op['id']] = $op['name'];
                        $operatorNames[(int)$op['number']] = $op['name'];
                    }
                } catch (\PDOException $e) {
                    error_log('SkiftplaneringController getSchedule operators: ' . $e->getMessage());
                }
            }

            // Bygg schema-struktur: { skiftTyp: { datum: [{id, operator_id, namn}] } }
            $schema = [];
            foreach ($configs as $c) {
                $schema[$c['skift_typ']] = [
                    'config' => $c,
                    'dagar'  => [],
                ];
            }

            // Generera alla dagar i veckan
            $dagar = [];
            $d = new \DateTime($monday);
            $end = new \DateTime($sunday);
            $end->modify('+1 day');
            $dagNamn = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
            while ($d < $end) {
                $dateStr = $d->format('Y-m-d');
                $dayIndex = (int)$d->format('N') - 1;
                $dagar[] = [
                    'datum'   => $dateStr,
                    'dag_namn' => $dagNamn[$dayIndex],
                ];
                // Initiera tomma arrayer
                foreach ($configs as $c) {
                    $schema[$c['skift_typ']]['dagar'][$dateStr] = [];
                }
                $d->modify('+1 day');
            }

            // Fyll i operatörer
            foreach ($rows as $r) {
                $typ = $r['skift_typ'];
                $datum = $r['datum'];
                $opId = (int)$r['operator_id'];
                if (isset($schema[$typ]['dagar'][$datum])) {
                    $schema[$typ]['dagar'][$datum][] = [
                        'id'          => (int)$r['id'],
                        'operator_id' => $opId,
                        'namn'        => $operatorNames[$opId] ?? ('Operatör #' . $opId),
                    ];
                }
            }

            // Beräkna status per cell (gron/gul/rod)
            $result = [];
            foreach ($schema as $typ => $data) {
                $minB = (int)$data['config']['min_bemanning'];
                $maxB = (int)$data['config']['max_bemanning'];
                $dagarResult = [];
                foreach ($data['dagar'] as $datum => $ops) {
                    $antal = count($ops);
                    $status = 'gron';
                    if ($antal < $minB) $status = 'rod';
                    elseif ($antal < ceil(($minB + $maxB) / 2)) $status = 'gul';

                    $dagarResult[] = [
                        'datum'      => $datum,
                        'operatorer' => $ops,
                        'antal'      => $antal,
                        'status'     => $status,
                    ];
                }
                $result[] = [
                    'skift_typ'      => $typ,
                    'start_tid'      => $data['config']['start_tid'],
                    'slut_tid'       => $data['config']['slut_tid'],
                    'min_bemanning'  => $minB,
                    'max_bemanning'  => $maxB,
                    'dagar'          => $dagarResult,
                ];
            }

            $this->sendSuccess([
                'vecka' => $weekParam ?: date('Y-\WW'),
                'monday' => $monday,
                'sunday' => $sunday,
                'dagar'  => $dagar,
                'schema' => $result,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController getSchedule: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta veckoschema', 500);
        }
    }

    // =========================================================================
    // GET run=shift-detail (?shift=FM/EM/NATT&date=YYYY-MM-DD)
    // =========================================================================

    private function getShiftDetail(): void {
        $shift = strtoupper(trim($_GET['shift'] ?? ''));
        $date  = trim($_GET['date'] ?? '');

        if (!in_array($shift, ['FM', 'EM', 'NATT'])) {
            $this->sendError('Ogiltig skifttyp (FM/EM/NATT)');
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendError('Ogiltigt datum (YYYY-MM-DD)');
            return;
        }

        try {
            // Hämta config
            $cfgStmt = $this->pdo->prepare(
                "SELECT * FROM skift_konfiguration WHERE skift_typ = ?"
            );
            $cfgStmt->execute([$shift]);
            $config = $cfgStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$config) {
                $this->sendError('Skifttyp hittades inte', 404);
                return;
            }

            // Hämta operatörer
            $opsStmt = $this->pdo->prepare(
                "SELECT ss.id, ss.operator_id
                 FROM skift_schema ss
                 WHERE ss.skift_typ = ? AND ss.datum = ?
                 ORDER BY ss.operator_id"
            );
            $opsStmt->execute([$shift, $date]);
            $opsRows = $opsStmt->fetchAll(\PDO::FETCH_ASSOC);

            $operatorer = [];
            foreach ($opsRows as $r) {
                $opId = (int)$r['operator_id'];
                $operatorer[] = [
                    'schema_id'   => (int)$r['id'],
                    'operator_id' => $opId,
                    'namn'        => $this->getOperatorName($opId),
                ];
            }

            $antal = count($operatorer);
            $minB  = (int)$config['min_bemanning'];
            $maxB  = (int)$config['max_bemanning'];
            $status = 'gron';
            if ($antal < $minB) $status = 'rod';
            elseif ($antal < ceil(($minB + $maxB) / 2)) $status = 'gul';

            // Planerad kapacitet (uppskattning: ~8 IBC/h per operatör baserat på historik)
            $planeradKapacitet = $antal * 8; // IBC/h

            // Faktisk produktion (om det finns data för detta datum/skift)
            $faktiskProduktion = null;
            try {
                // Försök hämta från rebotling_log för skiftets tider
                $startTid = $config['start_tid'];
                $slutTid  = $config['slut_tid'];

                if ($shift === 'NATT') {
                    // Nattskift: 22:00 aktuellt datum till 06:00 nästa dag
                    $fromDt = $date . ' ' . $startTid;
                    $toDt   = date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $slutTid;
                } else {
                    $fromDt = $date . ' ' . $startTid;
                    $toDt   = $date . ' ' . $slutTid;
                }

                $prodStmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM rebotling_log WHERE timestamp BETWEEN ? AND ?"
                );
                $prodStmt->execute([$fromDt, $toDt]);
                $faktiskProduktion = (int)$prodStmt->fetchColumn();
            } catch (\PDOException $e) {
                error_log('SkiftplaneringController getShiftDetail rebotling_log: ' . $e->getMessage());
            }

            $this->sendSuccess([
                'skift_typ'          => $shift,
                'datum'              => $date,
                'start_tid'          => $config['start_tid'],
                'slut_tid'           => $config['slut_tid'],
                'min_bemanning'      => $minB,
                'max_bemanning'      => $maxB,
                'operatorer'         => $operatorer,
                'antal_bemanning'    => $antal,
                'status'             => $status,
                'planerad_kapacitet' => $planeradKapacitet,
                'faktisk_produktion' => $faktiskProduktion,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController getShiftDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skiftdetaljer', 500);
        }
    }

    // =========================================================================
    // POST run=assign
    // Body: { operator_id, skift_typ, datum }
    // =========================================================================

    private function assignOperator(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $operatorId = (int)($data['operator_id'] ?? 0);
        $skiftTyp   = strtoupper(trim($data['skift_typ'] ?? ''));
        $datum      = trim($data['datum'] ?? '');

        if ($operatorId <= 0) {
            $this->sendError('operator_id krävs');
            return;
        }
        if (!in_array($skiftTyp, ['FM', 'EM', 'NATT'])) {
            $this->sendError('Ogiltig skifttyp (FM/EM/NATT)');
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            $this->sendError('Ogiltigt datum (YYYY-MM-DD)');
            return;
        }

        try {
            // Kontrollera att operatören inte redan är inplanerad denna dag
            $checkStmt = $this->pdo->prepare(
                "SELECT id FROM skift_schema WHERE operator_id = ? AND datum = ?"
            );
            $checkStmt->execute([$operatorId, $datum]);
            if ($checkStmt->fetch()) {
                $this->sendError('Operatören är redan inplanerad denna dag');
                return;
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO skift_schema (operator_id, skift_typ, datum) VALUES (?, ?, ?)"
            );
            $stmt->execute([$operatorId, $skiftTyp, $datum]);

            $this->sendSuccess([
                'id'      => (int)$this->pdo->lastInsertId(),
                'message' => 'Operatör tilldelad',
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController assignOperator: ' . $e->getMessage());
            $this->sendError('Kunde inte tilldela operatör', 500);
        }
    }

    // =========================================================================
    // POST run=unassign
    // Body: { schema_id } OR { operator_id, datum }
    // =========================================================================

    private function unassignOperator(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $schemaId   = (int)($data['schema_id'] ?? 0);
        $operatorId = (int)($data['operator_id'] ?? 0);
        $datum      = trim($data['datum'] ?? '');

        try {
            if ($schemaId > 0) {
                $stmt = $this->pdo->prepare("DELETE FROM skift_schema WHERE id = ?");
                $stmt->execute([$schemaId]);
            } elseif ($operatorId > 0 && $datum) {
                $stmt = $this->pdo->prepare(
                    "DELETE FROM skift_schema WHERE operator_id = ? AND datum = ?"
                );
                $stmt->execute([$operatorId, $datum]);
            } else {
                $this->sendError('schema_id eller (operator_id + datum) krävs');
                return;
            }

            $this->sendSuccess(['message' => 'Operatör borttagen från skift']);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController unassignOperator: ' . $e->getMessage());
            $this->sendError('Kunde inte ta bort operatör', 500);
        }
    }

    // =========================================================================
    // GET run=capacity
    // Kapacitetsplanering: optimal bemanning per skift baserat på historisk IBC/h
    // =========================================================================

    private function getCapacity(): void {
        try {
            $configs = $this->getShiftConfigs();
            list($monday, $sunday) = $this->parseWeek(null);

            // Bemanningsgrad per dag i veckan
            $stmt = $this->pdo->prepare(
                "SELECT datum, skift_typ, COUNT(*) AS antal
                 FROM skift_schema
                 WHERE datum BETWEEN ? AND ?
                 GROUP BY datum, skift_typ"
            );
            $stmt->execute([$monday, $sunday]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg daglig bemanningsgrad
            $configMap = [];
            $totalMinPerDay = 0;
            foreach ($configs as $c) {
                $configMap[$c['skift_typ']] = $c;
                $totalMinPerDay += (int)$c['min_bemanning'];
            }

            $dagMap = [];
            foreach ($rows as $r) {
                if (!isset($dagMap[$r['datum']])) {
                    $dagMap[$r['datum']] = 0;
                }
                $dagMap[$r['datum']] += (int)$r['antal'];
            }

            // Generera dagdata
            $dagData = [];
            $d = new \DateTime($monday);
            $end = new \DateTime($sunday);
            $end->modify('+1 day');
            $dagNamn = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
            while ($d < $end) {
                $dateStr = $d->format('Y-m-d');
                $dayIndex = (int)$d->format('N') - 1;
                $faktiskt = $dagMap[$dateStr] ?? 0;
                $grad = $totalMinPerDay > 0 ? round(($faktiskt / $totalMinPerDay) * 100, 0) : 0;
                $dagData[] = [
                    'datum'         => $dateStr,
                    'dag_namn'      => $dagNamn[$dayIndex],
                    'bemanning'     => $faktiskt,
                    'min_krav'      => $totalMinPerDay,
                    'bemanningsgrad' => (int)$grad,
                ];
                $d->modify('+1 day');
            }

            // Historisk IBC/h (genomsnitt senaste 30 dagarna)
            $ibcPerH = 8; // default
            try {
                $ibcStmt = $this->pdo->query(
                    "SELECT COUNT(*) / (GREATEST(TIMESTAMPDIFF(HOUR,
                        MIN(timestamp), MAX(timestamp)), 1))
                     FROM rebotling_log
                     WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
                );
                $val = (float)$ibcStmt->fetchColumn();
                if ($val > 0) $ibcPerH = round($val, 1);
            } catch (\PDOException $e) {
                error_log('SkiftplaneringController getCapacity rebotling_log: ' . $e->getMessage());
            }

            $this->sendSuccess([
                'dag_data'          => $dagData,
                'min_per_dag'       => $totalMinPerDay,
                'ibc_per_timme'     => $ibcPerH,
                'skift_konfiguration' => $configs,
            ]);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController getCapacity: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta kapacitetsdata', 500);
        }
    }

    // =========================================================================
    // GET run=operators
    // Lista alla tillgängliga operatörer
    // =========================================================================

    private function getOperators(): void {
        try {
            $operatorer = [];
            try {
                $stmt = $this->pdo->query(
                    "SELECT id, name AS namn FROM operators ORDER BY name ASC"
                );
                $operatorer = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log('SkiftplaneringController getOperators: ' . $e->getMessage());
            }

            $this->sendSuccess(['operatorer' => $operatorer]);
        } catch (\PDOException $e) {
            error_log('SkiftplaneringController getOperators: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörer', 500);
        }
    }
}
