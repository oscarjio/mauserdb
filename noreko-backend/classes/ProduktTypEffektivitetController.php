<?php
/**
 * ProduktTypEffektivitetController.php
 * Effektivitet per produkttyp — jämför IBC-produkttyper (FoodGrade, NonUN, etc.)
 *
 * Endpoints via ?action=produkttyp-effektivitet&run=XXX:
 *   - run=summary    → sammanfattning per produkttyp: antal IBC, snittcykeltid, kvalitet%, IBC/h, snittbonus
 *   - run=trend      → daglig trend per produkttyp (cykeltid + antal) för Chart.js
 *   - run=comparison → head-to-head jämförelse av 2 valda produkttyper
 *
 * Tabeller:
 *   rebotling_ibc       (produkt, ibc_ok, ibc_ej_ok, runtime_plc, bonus_poang, kvalitet, datum, skiftraknare)
 *   rebotling_products  (id, name, cycle_time_minutes)
 *
 * Kumulativa PLC-värden: MAX() per skiftraknare+produkt, sedan SUM()/AVG() över skift.
 */
class ProduktTypEffektivitetController {
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
            case 'summary':    $this->getSummary();    break;
            case 'trend':      $this->getTrend();      break;
            case 'comparison': $this->getComparison(); break;
            default:           $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
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
    // ENDPOINT: summary
    // ================================================================

    /**
     * GET ?action=produkttyp-effektivitet&run=summary&days=30
     * Returnerar per produkttyp:
     *   - produkt_id, produkt_namn
     *   - antal_ibc (godkända)
     *   - antal_skift
     *   - snitt_cykeltid_sek
     *   - kvalitet_pct
     *   - ibc_per_timme
     *   - snitt_bonus
     */
    private function getSummary(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    agg.produkt_id,
                    COALESCE(p.name, CONCAT('Produkt #', agg.produkt_id)) AS produkt_namn,
                    COUNT(DISTINCT agg.skiftraknare)                       AS antal_skift,
                    SUM(agg.shift_ibc_ok)                                  AS antal_ibc,
                    SUM(agg.shift_ibc_ej_ok)                               AS antal_ej_ok,
                    ROUND(AVG(agg.shift_runtime_min), 1)                   AS snitt_runtime_min,
                    ROUND(AVG(agg.last_bonus), 2)                          AS snitt_bonus,
                    ROUND(AVG(agg.last_kvalitet), 1)                       AS snitt_kvalitet,
                    ROUND(
                        SUM(agg.shift_ibc_ok) /
                        NULLIF(SUM(agg.shift_runtime_min) / 60.0, 0),
                    1)                                                     AS ibc_per_timme,
                    ROUND(
                        SUM(agg.shift_ibc_ok) * 100.0 /
                        NULLIF(SUM(agg.shift_ibc_ok) + SUM(agg.shift_ibc_ej_ok), 0),
                    1)                                                     AS kvalitet_pct,
                    ROUND(
                        CASE WHEN SUM(agg.shift_ibc_ok) > 0
                             THEN SUM(agg.shift_runtime_min) * 60.0 / SUM(agg.shift_ibc_ok)
                             ELSE NULL END,
                    1)                                                     AS snitt_cykeltid_sek
                FROM (
                    SELECT
                        produkt                     AS produkt_id,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))    AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc, 0)) AS shift_runtime_min,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'), '|', 1) + 0 AS last_bonus,
                        SUBSTRING_INDEX(GROUP_CONCAT(kvalitet    ORDER BY datum DESC SEPARATOR '|'), '|', 1) + 0 AS last_kvalitet
                    FROM rebotling_ibc
                    WHERE produkt IS NOT NULL
                      AND produkt > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY produkt, skiftraknare
                ) AS agg
                LEFT JOIN rebotling_products p ON p.id = agg.produkt_id
                GROUP BY agg.produkt_id, p.name
                HAVING antal_ibc > 0
                ORDER BY antal_ibc DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Cast numeriska värden
            $produkter = [];
            foreach ($rows as $r) {
                $produkter[] = [
                    'produkt_id'        => (int)$r['produkt_id'],
                    'produkt_namn'      => $r['produkt_namn'],
                    'antal_skift'       => (int)$r['antal_skift'],
                    'antal_ibc'         => (int)$r['antal_ibc'],
                    'antal_ej_ok'       => (int)$r['antal_ej_ok'],
                    'snitt_cykeltid_sek'=> $r['snitt_cykeltid_sek'] !== null ? round((float)$r['snitt_cykeltid_sek'], 1) : null,
                    'kvalitet_pct'      => $r['kvalitet_pct'] !== null ? round((float)$r['kvalitet_pct'], 1) : null,
                    'ibc_per_timme'     => $r['ibc_per_timme'] !== null ? round((float)$r['ibc_per_timme'], 1) : null,
                    'snitt_bonus'       => round((float)($r['snitt_bonus'] ?? 0), 2),
                ];
            }

            // Totaler
            $totalIbc   = array_sum(array_column($produkter, 'antal_ibc'));
            $totalEjOk  = array_sum(array_column($produkter, 'antal_ej_ok'));

            $this->sendSuccess([
                'days'       => $days,
                'from'       => $fromDate,
                'to'         => $toDate,
                'produkter'  => $produkter,
                'total_ibc'  => $totalIbc,
                'total_ej_ok'=> $totalEjOk,
                'har_data'   => !empty($produkter),
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktTypEffektivitetController::getSummary: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: trend
    // ================================================================

    /**
     * GET ?action=produkttyp-effektivitet&run=trend&days=30
     * Returnerar daglig trend per produkttyp — antal IBC + snittcykeltid.
     * Format för Chart.js grouped/stacked bar.
     */
    private function getTrend(): void {
        $days     = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            // Hämta top-produkttyper (max 6)
            $stmtTop = $this->pdo->prepare("
                SELECT produkt AS produkt_id,
                       COALESCE(p.name, CONCAT('Produkt #', produkt)) AS produkt_namn,
                       COALESCE(SUM(sub.shift_ibc_ok), 0) AS total_ibc
                FROM (
                    SELECT produkt, skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ibc_ok
                    FROM rebotling_ibc
                    WHERE produkt IS NOT NULL AND produkt > 0
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY produkt, skiftraknare
                ) AS sub
                LEFT JOIN rebotling_products p ON p.id = sub.produkt
                GROUP BY produkt, p.name
                HAVING total_ibc > 0
                ORDER BY total_ibc DESC
                LIMIT 6
            ");
            $stmtTop->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $topProdukter = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($topProdukter)) {
                $this->sendSuccess([
                    'days'     => $days,
                    'from'     => $fromDate,
                    'to'       => $toDate,
                    'labels'   => [],
                    'datasets_ibc'      => [],
                    'datasets_cykeltid' => [],
                    'har_data' => false,
                ]);
                return;
            }

            $topIds = array_column($topProdukter, 'produkt_id');
            $placeholders = implode(',', array_fill(0, count($topIds), '?'));

            // Daglig data per produkt
            $stmtDaily = $this->pdo->prepare("
                SELECT
                    DATE(datum) AS dag,
                    produkt AS produkt_id,
                    COALESCE(SUM(shift_ok), 0) AS dag_ibc,
                    ROUND(
                        CASE WHEN COALESCE(SUM(shift_ok), 0) > 0
                             THEN COALESCE(SUM(shift_runtime), 0) * 60.0 / SUM(shift_ok)
                             ELSE NULL END,
                    1) AS dag_cykeltid_sek
                FROM (
                    SELECT
                        datum,
                        produkt,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))      AS shift_ok,
                        MAX(COALESCE(runtime_plc, 0))  AS shift_runtime
                    FROM rebotling_ibc
                    WHERE produkt IN ({$placeholders})
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN ? AND ?
                    GROUP BY DATE(datum), produkt, skiftraknare
                ) AS per_shift
                GROUP BY DATE(datum), produkt
                ORDER BY dag ASC
            ");
            $params = array_merge($topIds, [$fromDate, $toDate]);
            $stmtDaily->execute($params);
            $dailyRows = $stmtDaily->fetchAll(\PDO::FETCH_ASSOC);

            // Indexera
            $dataByDate = [];
            foreach ($dailyRows as $row) {
                $d   = $row['dag'];
                $pid = (int)$row['produkt_id'];
                if (!isset($dataByDate[$d])) $dataByDate[$d] = [];
                $dataByDate[$d][$pid] = [
                    'ibc'      => (int)$row['dag_ibc'],
                    'cykeltid' => $row['dag_cykeltid_sek'] !== null ? (float)$row['dag_cykeltid_sek'] : null,
                ];
            }

            // Alla datum i perioden
            $labels  = [];
            $current = new \DateTime($fromDate);
            $end     = new \DateTime($toDate);
            while ($current <= $end) {
                $labels[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }

            // Palettfärger
            $palette = [
                'rgba(99,179,237,0.85)',   // blå
                'rgba(104,211,145,0.85)',  // grön
                'rgba(246,173,85,0.85)',   // orange
                'rgba(183,148,246,0.85)',  // lila
                'rgba(252,129,129,0.85)',  // röd
                'rgba(129,230,217,0.85)',  // teal
            ];

            $datasetsIbc      = [];
            $datasetsCykeltid = [];

            foreach ($topProdukter as $i => $prod) {
                $pid = (int)$prod['produkt_id'];
                $ibcData  = [];
                $cycData  = [];
                foreach ($labels as $datum) {
                    $ibcData[]  = $dataByDate[$datum][$pid]['ibc'] ?? 0;
                    $cycData[]  = $dataByDate[$datum][$pid]['cykeltid'] ?? null;
                }
                $color = $palette[$i % count($palette)];
                $borderColor = str_replace('0.85', '1', $color);

                $datasetsIbc[] = [
                    'label'           => $prod['produkt_namn'],
                    'produktId'       => $pid,
                    'data'            => $ibcData,
                    'backgroundColor' => $color,
                    'borderColor'     => $borderColor,
                    'borderWidth'     => 1,
                ];

                $datasetsCykeltid[] = [
                    'label'           => $prod['produkt_namn'],
                    'produktId'       => $pid,
                    'data'            => $cycData,
                    'borderColor'     => $borderColor,
                    'backgroundColor' => str_replace('0.85', '0.15', $color),
                    'borderWidth'     => 2,
                    'fill'            => false,
                    'tension'         => 0.3,
                    'spanGaps'        => true,
                ];
            }

            $this->sendSuccess([
                'days'              => $days,
                'from'              => $fromDate,
                'to'                => $toDate,
                'labels'            => $labels,
                'datasets_ibc'      => $datasetsIbc,
                'datasets_cykeltid' => $datasetsCykeltid,
                'har_data'          => true,
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktTypEffektivitetController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }

    // ================================================================
    // ENDPOINT: comparison
    // ================================================================

    /**
     * GET ?action=produkttyp-effektivitet&run=comparison&a=1&b=4&days=30
     * Head-to-head jämförelse av 2 produkttyper med procentuella skillnader.
     */
    private function getComparison(): void {
        $days = $this->getDays();
        $idA  = intval($_GET['a'] ?? 0);
        $idB  = intval($_GET['b'] ?? 0);

        if ($idA <= 0 || $idB <= 0) {
            $this->sendError('Parametrarna a och b krävs (produkttyp-ID)');
            return;
        }

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $result = [];
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT agg.skiftraknare)     AS antal_skift,
                    SUM(agg.shift_ibc_ok)                AS antal_ibc,
                    SUM(agg.shift_ibc_ej_ok)             AS antal_ej_ok,
                    ROUND(AVG(agg.last_bonus), 2)        AS snitt_bonus,
                    ROUND(
                        SUM(agg.shift_ibc_ok) /
                        NULLIF(SUM(agg.shift_runtime_min) / 60.0, 0),
                    1)                                   AS ibc_per_timme,
                    ROUND(
                        SUM(agg.shift_ibc_ok) * 100.0 /
                        NULLIF(SUM(agg.shift_ibc_ok) + SUM(agg.shift_ibc_ej_ok), 0),
                    1)                                   AS kvalitet_pct,
                    ROUND(
                        CASE WHEN SUM(agg.shift_ibc_ok) > 0
                             THEN SUM(agg.shift_runtime_min) * 60.0 / SUM(agg.shift_ibc_ok)
                             ELSE NULL END,
                    1)                                   AS snitt_cykeltid_sek
                FROM (
                    SELECT
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0))      AS shift_ibc_ok,
                        MAX(COALESCE(ibc_ej_ok, 0))   AS shift_ibc_ej_ok,
                        MAX(COALESCE(runtime_plc, 0))  AS shift_runtime_min,
                        SUBSTRING_INDEX(GROUP_CONCAT(bonus_poang ORDER BY datum DESC SEPARATOR '|'), '|', 1) + 0 AS last_bonus
                    FROM rebotling_ibc
                    WHERE produkt = :produkt_id
                      AND skiftraknare IS NOT NULL
                      AND DATE(datum) BETWEEN :from_date AND :to_date
                    GROUP BY skiftraknare
                ) AS agg
            ");
            $stmtName = $this->pdo->prepare("SELECT name FROM rebotling_products WHERE id = ? LIMIT 1");
            foreach ([$idA, $idB] as $pid) {
                $stmt->execute([
                    ':produkt_id' => $pid,
                    ':from_date'  => $fromDate,
                    ':to_date'    => $toDate,
                ]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                // Produktnamn
                $stmtName->execute([$pid]);
                $nameRow = $stmtName->fetch(\PDO::FETCH_ASSOC);

                $result[$pid] = [
                    'produkt_id'        => $pid,
                    'produkt_namn'      => $nameRow['name'] ?? ('Produkt #' . $pid),
                    'antal_skift'       => (int)($row['antal_skift'] ?? 0),
                    'antal_ibc'         => (int)($row['antal_ibc'] ?? 0),
                    'antal_ej_ok'       => (int)($row['antal_ej_ok'] ?? 0),
                    'snitt_cykeltid_sek'=> $row['snitt_cykeltid_sek'] !== null ? round((float)$row['snitt_cykeltid_sek'], 1) : null,
                    'kvalitet_pct'      => $row['kvalitet_pct'] !== null ? round((float)$row['kvalitet_pct'], 1) : null,
                    'ibc_per_timme'     => $row['ibc_per_timme'] !== null ? round((float)$row['ibc_per_timme'], 1) : null,
                    'snitt_bonus'       => round((float)($row['snitt_bonus'] ?? 0), 2),
                ];
            }

            $a = $result[$idA];
            $b = $result[$idB];

            // Procentuella skillnader (A relativt B)
            $diff = [];
            foreach (['snitt_cykeltid_sek', 'kvalitet_pct', 'ibc_per_timme', 'snitt_bonus'] as $metric) {
                $va = $a[$metric];
                $vb = $b[$metric];
                if ($va !== null && $vb !== null && $vb !== 0) {
                    $diff[$metric] = round(($va - $vb) / abs($vb) * 100, 1);
                } else {
                    $diff[$metric] = null;
                }
            }

            $this->sendSuccess([
                'days'       => $days,
                'from'       => $fromDate,
                'to'         => $toDate,
                'a'          => $a,
                'b'          => $b,
                'diff_pct'   => $diff,
                'har_data'   => ($a['antal_ibc'] > 0 || $b['antal_ibc'] > 0),
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktTypEffektivitetController::getComparison: ' . $e->getMessage());
            $this->sendError('Databasfel', 500);
        }
    }
}
