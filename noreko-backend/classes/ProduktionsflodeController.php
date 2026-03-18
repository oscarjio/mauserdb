<?php

/**
 * ProduktionsflodeController
 * Produktionsflode (Sankey-diagram) — visar IBC-flodet genom rebotling-linjen.
 *
 * Endpoints via ?action=produktionsflode&run=XXX:
 *
 *   GET  run=overview          (?days=1|7|30|90)  — KPI: totalt inkommande, godkanda, kasserade, genomstromning%, flaskhals
 *   GET  run=flode-data        (?days=1|7|30|90)  — Sankey-data: noder + floden med volymer
 *   GET  run=station-detaljer  (?days=1|7|30|90)  — Tabell: per station detaljer
 *
 * Tabeller: rebotling_ibc (datum, ibc_ok, ibc_ej_ok, skiftraknare, runtime_plc)
 *
 * Stationerna ar logiska steg i rebotling-processen:
 *   1. Inspektion
 *   2. Tvatt
 *   3. Fyllning
 *   4. Etikettering
 *   5. Slutkontroll
 * Kassation kan ske vid varje station; vi fordelar kassation proportionellt.
 */
class ProduktionsflodeController {
    private $pdo;

    // Logiska stationer i rebotling-processen
    private $stations = [
        ['id' => 'inspektion',   'name' => 'Inspektion',   'order' => 1, 'kass_andel' => 0.30],
        ['id' => 'tvatt',        'name' => 'Tvatt',        'order' => 2, 'kass_andel' => 0.10],
        ['id' => 'fyllning',     'name' => 'Fyllning',     'order' => 3, 'kass_andel' => 0.25],
        ['id' => 'etikettering', 'name' => 'Etikettering', 'order' => 4, 'kass_andel' => 0.15],
        ['id' => 'slutkontroll', 'name' => 'Slutkontroll', 'order' => 5, 'kass_andel' => 0.20],
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
            case 'overview':         $this->getOverview();        break;
            case 'flode-data':       $this->getFlodeData();       break;
            case 'station-detaljer': $this->getStationDetaljer(); break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // Helpers
    // ================================================================

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve date range from days parameter.
     * days=1 -> idag, days=7 -> senaste 7d, etc.
     */
    private function resolveDateRange(): array {
        $days = max(1, min(365, (int)($_GET['days'] ?? 7)));
        $toDate   = date('Y-m-d');
        if ($days === 1) {
            $fromDate = $toDate;
        } else {
            $fromDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        }
        return [$fromDate, $toDate, $days];
    }

    /**
     * Hamta aggregerad produktion for datumintervall.
     * Anvander samma MAX-per-skift-logik som HistoriskProduktionController.
     */
    private function getProductionTotals(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok,
                COALESCE(SUM(shift_runtime), 0) AS total_runtime,
                COUNT(DISTINCT dag)             AS dagar_med_data,
                COUNT(*)                        AS antal_skift
            FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))      AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0))   AS shift_ej_ok,
                    MAX(COALESCE(runtime_plc, 0))  AS shift_runtime
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS per_shift
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ok      = (int)($row['ibc_ok'] ?? 0);
        $ejOk    = (int)($row['ibc_ej_ok'] ?? 0);
        $runtime = (float)($row['total_runtime'] ?? 0);
        $dagar   = (int)($row['dagar_med_data'] ?? 0);
        $skift   = (int)($row['antal_skift'] ?? 0);

        return [
            'ibc_ok'         => $ok,
            'ibc_ej_ok'      => $ejOk,
            'total'          => $ok + $ejOk,
            'total_runtime'  => round($runtime, 1),
            'dagar_med_data' => $dagar,
            'antal_skift'    => $skift,
        ];
    }

    /**
     * Forda kassation pa stationer och berakna flodesdata.
     */
    private function buildStationData(int $totalIncoming, int $totalKassation): array {
        $stationData = [];
        $remaining = $totalIncoming;

        foreach ($this->stations as $s) {
            $kassHar = (int)round($totalKassation * $s['kass_andel']);
            // Sista stationen tar resterande kassation for att summorna ska stamma
            if ($s['id'] === 'slutkontroll') {
                $kassHar = $totalKassation - array_sum(array_column($stationData, 'kasserade'));
                if ($kassHar < 0) $kassHar = 0;
            }

            $inkommande = $remaining;
            $kasserade  = min($kassHar, $remaining);
            $godkanda   = $remaining - $kasserade;
            $remaining  = $godkanda;

            $genomstromningPct = $inkommande > 0
                ? round(($godkanda / $inkommande) * 100, 1)
                : 0;

            $stationData[] = [
                'id'                 => $s['id'],
                'name'               => $s['name'],
                'order'              => $s['order'],
                'inkommande'         => $inkommande,
                'godkanda'           => $godkanda,
                'kasserade'          => $kasserade,
                'genomstromning_pct' => $genomstromningPct,
            ];
        }

        return $stationData;
    }

    /**
     * Hitta flaskhals-station (lagst genomstromning).
     */
    private function findBottleneck(array $stationData): string {
        $worst     = null;
        $worstPct  = 100.1;

        foreach ($stationData as $s) {
            if ($s['inkommande'] > 0 && $s['genomstromning_pct'] < $worstPct) {
                $worstPct = $s['genomstromning_pct'];
                $worst    = $s['name'];
            }
        }

        return $worst ?? 'Ingen';
    }

    // ================================================================
    // run=overview — KPI:er
    // ================================================================

    private function getOverview(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();
            $totals = $this->getProductionTotals($fromDate, $toDate);

            $total          = $totals['total'];
            $ok             = $totals['ibc_ok'];
            $ejOk           = $totals['ibc_ej_ok'];
            $genomstromning = $total > 0 ? round(($ok / $total) * 100, 1) : 0;

            $stationData = $this->buildStationData($total, $ejOk);
            $flaskhals   = $this->findBottleneck($stationData);

            $this->sendSuccess([
                'data' => [
                    'totalt_inkommande'  => $total,
                    'godkanda'           => $ok,
                    'kasserade'          => $ejOk,
                    'genomstromning_pct' => $genomstromning,
                    'flaskhals_station'  => $flaskhals,
                    'dagar_med_data'     => $totals['dagar_med_data'],
                    'antal_skift'        => $totals['antal_skift'],
                    'from'               => $fromDate,
                    'to'                 => $toDate,
                    'days'               => $days,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsflodeController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta oversikt', 500);
        }
    }

    // ================================================================
    // run=flode-data — Sankey-noder + floden
    // ================================================================

    private function getFlodeData(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();
            $totals = $this->getProductionTotals($fromDate, $toDate);

            $total = $totals['total'];
            $ok    = $totals['ibc_ok'];
            $ejOk  = $totals['ibc_ej_ok'];

            $stationData = $this->buildStationData($total, $ejOk);

            // Bygg noder
            $nodes = [
                ['id' => 'inkommande', 'label' => 'Inkommande IBC', 'type' => 'source'],
            ];
            foreach ($stationData as $s) {
                $nodes[] = [
                    'id'    => $s['id'],
                    'label' => $s['name'],
                    'type'  => 'station',
                ];
            }
            $nodes[] = ['id' => 'godkand',  'label' => 'Godkand',  'type' => 'output_ok'];
            $nodes[] = ['id' => 'kassation', 'label' => 'Kassation', 'type' => 'output_fail'];

            // Bygg floden (links)
            $links = [];

            // Inkommande -> forsta station
            if ($total > 0 && count($stationData) > 0) {
                $links[] = [
                    'from'  => 'inkommande',
                    'to'    => $stationData[0]['id'],
                    'value' => $total,
                    'type'  => 'flow',
                ];
            }

            // Station -> nasta station (godkanda vidare)
            for ($i = 0; $i < count($stationData); $i++) {
                $s = $stationData[$i];

                // Kassation fran denna station
                if ($s['kasserade'] > 0) {
                    $links[] = [
                        'from'  => $s['id'],
                        'to'    => 'kassation',
                        'value' => $s['kasserade'],
                        'type'  => 'kassation',
                    ];
                }

                // Godkanda vidare till nasta station eller slutresultat
                if ($i < count($stationData) - 1) {
                    if ($s['godkanda'] > 0) {
                        $links[] = [
                            'from'  => $s['id'],
                            'to'    => $stationData[$i + 1]['id'],
                            'value' => $s['godkanda'],
                            'type'  => 'flow',
                        ];
                    }
                } else {
                    // Sista stationens godkanda -> Godkand
                    if ($s['godkanda'] > 0) {
                        $links[] = [
                            'from'  => $s['id'],
                            'to'    => 'godkand',
                            'value' => $s['godkanda'],
                            'type'  => 'flow',
                        ];
                    }
                }
            }

            $this->sendSuccess([
                'data' => [
                    'nodes'    => $nodes,
                    'links'    => $links,
                    'stations' => $stationData,
                    'summary'  => [
                        'total'          => $total,
                        'godkanda'       => $ok,
                        'kasserade'      => $ejOk,
                        'genomstromning' => $total > 0 ? round(($ok / $total) * 100, 1) : 0,
                    ],
                    'from' => $fromDate,
                    'to'   => $toDate,
                    'days' => $days,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsflodeController::getFlodeData: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta flodesdata', 500);
        }
    }

    // ================================================================
    // run=station-detaljer — tabelldata per station
    // ================================================================

    private function getStationDetaljer(): void {
        try {
            [$fromDate, $toDate, $days] = $this->resolveDateRange();
            $totals = $this->getProductionTotals($fromDate, $toDate);

            $total = $totals['total'];
            $ejOk  = $totals['ibc_ej_ok'];

            $stationData = $this->buildStationData($total, $ejOk);

            // Berakna genomstromningstid baserat pa runtime
            $totalRuntimeMin = $totals['total_runtime'];
            $antalStationer  = count($stationData);

            $rows = [];
            foreach ($stationData as $s) {
                // Uppskattad tid per station (fordelat pa antal stationer)
                $stationTidMin = $antalStationer > 0 && $total > 0
                    ? round(($totalRuntimeMin / $antalStationer) / max(1, $s['inkommande']) * 60, 1)
                    : 0;

                $isBottleneck = false;
                $worstPct = 100.1;
                foreach ($stationData as $cs) {
                    if ($cs['inkommande'] > 0 && $cs['genomstromning_pct'] < $worstPct) {
                        $worstPct = $cs['genomstromning_pct'];
                    }
                }
                if ($s['inkommande'] > 0 && $s['genomstromning_pct'] <= $worstPct) {
                    $isBottleneck = true;
                }

                $rows[] = [
                    'station'            => $s['name'],
                    'station_id'         => $s['id'],
                    'order'              => $s['order'],
                    'inkommande'         => $s['inkommande'],
                    'godkanda'           => $s['godkanda'],
                    'kasserade'          => $s['kasserade'],
                    'genomstromning_pct' => $s['genomstromning_pct'],
                    'tid_per_ibc_sek'    => $stationTidMin,
                    'flaskhals'          => $isBottleneck,
                ];
            }

            $this->sendSuccess([
                'data' => [
                    'rows'            => $rows,
                    'from'            => $fromDate,
                    'to'              => $toDate,
                    'days'            => $days,
                    'total_runtime'   => round($totalRuntimeMin, 1),
                    'totalt'          => $total,
                    'totalt_godkanda' => $totals['ibc_ok'],
                    'totalt_kasserade'=> $totals['ibc_ej_ok'],
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsflodeController::getStationDetaljer: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta stationsdetaljer', 500);
        }
    }
}
