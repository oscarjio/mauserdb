<?php
/**
 * TidrapportController.php
 * Operatörs-tidrapport — automatisk generering av tidrapporter baserat på skiftschema.
 *
 * Endpoints via ?action=tidrapport&run=XXX:
 *   run=sammanfattning  -> KPI: total arbetstid vecka/manad, antal skift, snitt/skift, mest aktiv operator
 *   run=per-operator    -> lista operatorer med skift-statistik och fordelning fm/em/natt
 *   run=veckodata       -> arbetstimmar per dag senaste 4 veckorna, per operator (Chart.js)
 *   run=detaljer        -> detaljerad lista av alla skiftregistreringar
 *   run=export-csv      -> samma som detaljer fast som CSV-nedladdning
 *
 * Tabeller:
 *   rebotling_data  (datum, start, slut, user_id, station, antal m.m.)
 *   skift_log       (om finns)
 *   users           (id, username, role)
 */
class TidrapportController {
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
            case 'sammanfattning':  $this->getSammanfattning();  break;
            case 'per-operator':    $this->getPerOperator();     break;
            case 'veckodata':       $this->getVeckodata();       break;
            case 'detaljer':        $this->getDetaljer();        break;
            case 'export-csv':      $this->getExportCsv();       break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Hämta period-intervall baserat på query-parametrar.
     * Stöd för: period=vecka|manad|30d|anpassat, from/to för anpassat
     */
    private function getDateRange(): array {
        $period = trim($_GET['period'] ?? '30d');
        $today = date('Y-m-d');

        switch ($period) {
            case 'vecka':
                $fromDate = date('Y-m-d', strtotime('monday this week'));
                $toDate = $today;
                break;
            case 'manad':
                $fromDate = date('Y-m-01');
                $toDate = $today;
                break;
            case '30d':
                $fromDate = date('Y-m-d', strtotime('-30 days'));
                $toDate = $today;
                break;
            case 'anpassat':
                $fromDate = trim($_GET['from'] ?? '');
                $toDate = trim($_GET['to'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ||
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                    $fromDate = date('Y-m-d', strtotime('-30 days'));
                    $toDate = $today;
                }
                // Validera att from <= to, annars byt plats
                if ($fromDate > $toDate) {
                    [$fromDate, $toDate] = [$toDate, $fromDate];
                }
                // Begränsa till max 365 dagar
                try {
                    $diffDays = (int)(new \DateTime($fromDate))->diff(new \DateTime($toDate))->days;
                    if ($diffDays > 365) {
                        $fromDate = date('Y-m-d', strtotime($toDate . ' -365 days'));
                    }
                } catch (\Exception $e) {
                    $fromDate = date('Y-m-d', strtotime('-30 days'));
                    $toDate   = $today;
                }
                break;
            default:
                $fromDate = date('Y-m-d', strtotime('-30 days'));
                $toDate = $today;
        }

        return [$fromDate, $toDate, $period];
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Hämta skift-data från bästa tillgängliga tabell.
     * Returnerar array av rader med: operator_namn, user_id, datum, start_time, end_time, station, antal
     */
    private function fetchSkiftData(string $fromDate, string $toDate, ?int $operatorFilter = null): array {
        $rows = [];

        // Försök rebotling_data först (mest sannolikt befintlig)
        if ($this->tableExists('rebotling_data')) {
            $rows = $this->fetchFromRebotlingData($fromDate, $toDate, $operatorFilter);
        }

        // Om skift_log finns, använd den som komplement
        if (empty($rows) && $this->tableExists('skift_log')) {
            $rows = $this->fetchFromSkiftLog($fromDate, $toDate, $operatorFilter);
        }

        // Fallback: stopporsak_registreringar har user_id + start/end
        if (empty($rows) && $this->tableExists('stopporsak_registreringar')) {
            $rows = $this->fetchFromStopporsak($fromDate, $toDate, $operatorFilter);
        }

        return $rows;
    }

    private function fetchFromRebotlingData(string $from, string $to, ?int $opFilter): array {
        try {
            $sql = "
                SELECT
                    r.id,
                    COALESCE(u.username, CONCAT('Operator ', r.user_id)) AS operator_namn,
                    r.user_id,
                    DATE(r.datum) AS datum,
                    r.datum AS start_time,
                    CASE
                        WHEN r.slut IS NOT NULL THEN r.slut
                        ELSE DATE_ADD(r.datum, INTERVAL 8 HOUR)
                    END AS end_time,
                    COALESCE(r.station, '-') AS station,
                    COALESCE(r.antal, 0) AS antal
                FROM rebotling_data r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE DATE(r.datum) BETWEEN :from_date AND :to_date
            ";
            $params = [':from_date' => $from, ':to_date' => $to];

            if ($opFilter !== null) {
                $sql .= " AND r.user_id = :op_id";
                $params[':op_id'] = $opFilter;
            }

            $sql .= " ORDER BY r.datum DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('TidrapportController::fetchFromRebotlingData: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchFromSkiftLog(string $from, string $to, ?int $opFilter): array {
        try {
            $sql = "
                SELECT
                    s.id,
                    COALESCE(u.username, CONCAT('Operator ', s.user_id)) AS operator_namn,
                    s.user_id,
                    s.datum AS datum,
                    s.start_tid AS start_time,
                    s.slut_tid AS end_time,
                    COALESCE(s.skift_typ, '-') AS station,
                    0 AS antal
                FROM skift_log s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.datum BETWEEN :from_date AND :to_date
            ";
            $params = [':from_date' => $from, ':to_date' => $to];

            if ($opFilter !== null) {
                $sql .= " AND s.user_id = :op_id";
                $params[':op_id'] = $opFilter;
            }

            $sql .= " ORDER BY s.start_tid DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('TidrapportController::fetchFromSkiftLog: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchFromStopporsak(string $from, string $to, ?int $opFilter): array {
        try {
            $sql = "
                SELECT
                    r.id,
                    COALESCE(u.username, CONCAT('Operator ', r.user_id)) AS operator_namn,
                    r.user_id,
                    DATE(r.start_time) AS datum,
                    r.start_time,
                    r.end_time,
                    COALESCE(r.linje, 'rebotling') AS station,
                    0 AS antal
                FROM stopporsak_registreringar r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE DATE(r.start_time) BETWEEN :from_date AND :to_date
            ";
            $params = [':from_date' => $from, ':to_date' => $to];

            if ($opFilter !== null) {
                $sql .= " AND r.user_id = :op_id";
                $params[':op_id'] = $opFilter;
            }

            $sql .= " ORDER BY r.start_time DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log('TidrapportController::fetchFromStopporsak: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Beräkna arbetstimmar för en rad
     */
    private function calcTimmar(array $row): float {
        if (!empty($row['end_time']) && !empty($row['start_time'])) {
            $start = strtotime($row['start_time']);
            $end   = strtotime($row['end_time']);
            if ($start && $end && $end > $start) {
                return round(($end - $start) / 3600, 2);
            }
        }
        return 8.0; // Standardskift 8 timmar
    }

    /**
     * Bestäm skifttyp baserat på starttid
     */
    private function skiftTyp(string $startTime): string {
        $hour = (int)date('H', strtotime($startTime));
        if ($hour >= 6 && $hour < 14) return 'formiddag';
        if ($hour >= 14 && $hour < 22) return 'eftermiddag';
        return 'natt';
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

    // ================================================================
    // run=sammanfattning — KPI
    // ================================================================

    private function getSammanfattning(): void {
        [$fromDate, $toDate, $period] = $this->getDateRange();

        try {
            $rows = $this->fetchSkiftData($fromDate, $toDate);

            $totalTimmar = 0;
            $antalSkift = count($rows);
            $operatorTimmar = [];

            foreach ($rows as $row) {
                $timmar = $this->calcTimmar($row);
                $totalTimmar += $timmar;
                $opNamn = $row['operator_namn'] ?? 'Okand';
                if (!isset($operatorTimmar[$opNamn])) $operatorTimmar[$opNamn] = 0;
                $operatorTimmar[$opNamn] += $timmar;
            }

            $snittPerSkift = $antalSkift > 0 ? round($totalTimmar / $antalSkift, 1) : 0;

            // Mest aktiv operatör
            $mestAktiv = null;
            $mestTimmar = 0;
            foreach ($operatorTimmar as $namn => $tim) {
                if ($tim > $mestTimmar) {
                    $mestAktiv = $namn;
                    $mestTimmar = $tim;
                }
            }

            // Beräkna vecko/månads-total
            $veckoStart = date('Y-m-d', strtotime('monday this week'));
            $manadStart = date('Y-m-01');
            $veckoTimmar = 0;
            $manadTimmar = 0;
            foreach ($rows as $row) {
                $timmar = $this->calcTimmar($row);
                $datum = $row['datum'] ?? '';
                if ($datum >= $veckoStart) $veckoTimmar += $timmar;
                if ($datum >= $manadStart) $manadTimmar += $timmar;
            }

            $this->sendSuccess([
                'period'          => $period,
                'from_date'       => $fromDate,
                'to_date'         => $toDate,
                'total_timmar'    => round($totalTimmar, 1),
                'vecko_timmar'    => round($veckoTimmar, 1),
                'manad_timmar'    => round($manadTimmar, 1),
                'antal_skift'     => $antalSkift,
                'snitt_per_skift' => $snittPerSkift,
                'mest_aktiv'      => $mestAktiv,
                'mest_aktiv_timmar' => round($mestTimmar, 1),
                'antal_operatorer'  => count($operatorTimmar),
            ]);
        } catch (\PDOException $e) {
            error_log('TidrapportController::getSammanfattning: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=per-operator — Operatörsöversikt
    // ================================================================

    private function getPerOperator(): void {
        [$fromDate, $toDate, $period] = $this->getDateRange();

        try {
            $rows = $this->fetchSkiftData($fromDate, $toDate);

            $operators = [];

            foreach ($rows as $row) {
                $opNamn = $row['operator_namn'] ?? 'Okand';
                $userId = (int)($row['user_id'] ?? 0);
                $timmar = $this->calcTimmar($row);
                $typ = $this->skiftTyp($row['start_time'] ?? '08:00:00');

                if (!isset($operators[$userId])) {
                    $operators[$userId] = [
                        'user_id'      => $userId,
                        'namn'         => $opNamn,
                        'antal_skift'  => 0,
                        'total_timmar' => 0,
                        'senaste_skift' => null,
                        'formiddag'    => 0,
                        'eftermiddag'  => 0,
                        'natt'         => 0,
                    ];
                }

                $operators[$userId]['antal_skift']++;
                $operators[$userId]['total_timmar'] += $timmar;
                $operators[$userId][$typ]++;

                $datum = $row['datum'] ?? $row['start_time'] ?? '';
                if (!$operators[$userId]['senaste_skift'] || $datum > $operators[$userId]['senaste_skift']) {
                    $operators[$userId]['senaste_skift'] = $datum;
                }
            }

            $result = [];
            foreach ($operators as $op) {
                $op['total_timmar'] = round($op['total_timmar'], 1);
                $op['snitt_per_skift'] = $op['antal_skift'] > 0
                    ? round($op['total_timmar'] / $op['antal_skift'], 1)
                    : 0;
                $totalSkift = $op['formiddag'] + $op['eftermiddag'] + $op['natt'];
                $op['formiddag_pct'] = $totalSkift > 0 ? round(($op['formiddag'] / $totalSkift) * 100) : 0;
                $op['eftermiddag_pct'] = $totalSkift > 0 ? round(($op['eftermiddag'] / $totalSkift) * 100) : 0;
                $op['natt_pct'] = $totalSkift > 0 ? round(($op['natt'] / $totalSkift) * 100) : 0;
                $result[] = $op;
            }

            // Sortera efter total_timmar desc
            usort($result, function ($a, $b) {
                return $b['total_timmar'] <=> $a['total_timmar'];
            });

            $this->sendSuccess([
                'period'     => $period,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'operatorer' => $result,
                'total'      => count($result),
            ]);
        } catch (\PDOException $e) {
            error_log('TidrapportController::getPerOperator: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=veckodata — Timmar per dag, per operator (Chart.js)
    // ================================================================

    private function getVeckodata(): void {
        $veckor = max(1, min(12, intval($_GET['veckor'] ?? 4)));
        $fromDate = date('Y-m-d', strtotime("-{$veckor} weeks"));
        $toDate = date('Y-m-d');

        try {
            $rows = $this->fetchSkiftData($fromDate, $toDate);

            // Bygga datum-sekvens
            $dates = [];
            $d = new \DateTime($fromDate);
            $end = new \DateTime($toDate);
            while ($d <= $end) {
                $dates[] = $d->format('Y-m-d');
                $d->modify('+1 day');
            }

            // Samla data per operatör per dag
            $operatorDag = [];
            $operatorNames = [];
            foreach ($rows as $row) {
                $opNamn = $row['operator_namn'] ?? 'Okand';
                $userId = (int)($row['user_id'] ?? 0);
                $datum = $row['datum'] ?? '';
                $timmar = $this->calcTimmar($row);

                if (!isset($operatorNames[$userId])) {
                    $operatorNames[$userId] = $opNamn;
                    $operatorDag[$userId] = [];
                }

                if (!isset($operatorDag[$userId][$datum])) {
                    $operatorDag[$userId][$datum] = 0;
                }
                $operatorDag[$userId][$datum] += $timmar;
            }

            // Bygg datasets
            $colors = [
                '#63b3ed', '#48bb78', '#fc8181', '#f6ad55', '#ecc94b',
                '#9f7aea', '#f687b3', '#4fd1c5', '#fbd38d', '#b794f4',
            ];

            $datasets = [];
            $i = 0;
            foreach ($operatorNames as $userId => $namn) {
                $data = [];
                foreach ($dates as $dt) {
                    $data[] = round($operatorDag[$userId][$dt] ?? 0, 1);
                }
                $datasets[] = [
                    'label' => $namn,
                    'data'  => $data,
                    'color' => $colors[$i % count($colors)],
                ];
                $i++;
            }

            $this->sendSuccess([
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'veckor'    => $veckor,
                'dates'     => $dates,
                'datasets'  => $datasets,
            ]);
        } catch (\PDOException $e) {
            error_log('TidrapportController::getVeckodata: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=detaljer — Detaljerad skiftlista
    // ================================================================

    private function getDetaljer(): void {
        [$fromDate, $toDate, $period] = $this->getDateRange();
        $operatorFilter = isset($_GET['operator_id']) ? intval($_GET['operator_id']) : null;

        try {
            $rows = $this->fetchSkiftData($fromDate, $toDate, $operatorFilter);

            $detaljer = [];
            foreach ($rows as $row) {
                $timmar = $this->calcTimmar($row);
                $detaljer[] = [
                    'id'           => (int)($row['id'] ?? 0),
                    'operator_namn' => $row['operator_namn'] ?? 'Okand',
                    'user_id'      => (int)($row['user_id'] ?? 0),
                    'datum'        => $row['datum'] ?? '',
                    'start_time'   => $row['start_time'] ?? '',
                    'end_time'     => $row['end_time'] ?? '',
                    'station'      => $row['station'] ?? '-',
                    'antal'        => (int)($row['antal'] ?? 0),
                    'timmar'       => round($timmar, 2),
                    'skift_typ'    => $this->skiftTyp($row['start_time'] ?? '08:00:00'),
                ];
            }

            $result = [
                'period'     => $period,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'detaljer'   => $detaljer,
                'total'      => count($detaljer),
            ];

            $this->sendSuccess($result);
        } catch (\PDOException $e) {
            error_log('TidrapportController::getDetaljer: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // run=export-csv — CSV-nedladdning
    // ================================================================

    private function getExportCsv(): void {
        [$fromDate, $toDate, $period] = $this->getDateRange();
        $operatorFilter = isset($_GET['operator_id']) ? intval($_GET['operator_id']) : null;

        try {
            $rows = $this->fetchSkiftData($fromDate, $toDate, $operatorFilter);

            // Ändra content-type för CSV — sanitera datumdelar i filnamnet
            $safeFrom = preg_replace('/[^0-9-]/', '', $fromDate);
            $safeTo   = preg_replace('/[^0-9-]/', '', $toDate);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="tidrapport_' . $safeFrom . '_' . $safeTo . '.csv"');
            header('Cache-Control: no-cache, no-store, must-revalidate');

            $output = fopen('php://output', 'w');

            // BOM för Excel-kompatibilitet
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($output, [
                'Datum', 'Operator', 'Start', 'Slut', 'Station',
                'Antal producerade', 'Arbetstid (h)', 'Skifttyp',
            ], ';');

            foreach ($rows as $row) {
                $timmar = $this->calcTimmar($row);
                fputcsv($output, [
                    $row['datum'] ?? '',
                    $row['operator_namn'] ?? 'Okand',
                    $row['start_time'] ?? '',
                    $row['end_time'] ?? '',
                    $row['station'] ?? '-',
                    (int)($row['antal'] ?? 0),
                    round($timmar, 2),
                    $this->skiftTyp($row['start_time'] ?? '08:00:00'),
                ], ';');
            }

            fclose($output);
            exit;
        } catch (\PDOException $e) {
            error_log('TidrapportController::getExportCsv: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
