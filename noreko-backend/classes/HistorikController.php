<?php
/**
 * HistorikController.php
 * Historisk jämförelse av rebotling-produktion — månads- och årsöversikt
 *
 * Endpoints (alla publika — kräver ej inloggning):
 *   GET ?action=historik&run=monthly&manader=24   → Månadsöversikt + KPI-sammandrag
 *   GET ?action=historik&run=yearly               → År-mot-år veckojämförelse
 */
class HistorikController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Endast GET tillåtet'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $run = trim($_GET['run'] ?? 'monthly');

        switch ($run) {
            case 'monthly':
                $this->getMonthly();
                break;
            case 'yearly':
                $this->getYearly();
                break;
            case 'daglig':
                $this->getDaglig();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Månadsöversikt + KPI-sammandrag
     * GET ?action=historik&run=monthly&manader=24
     */
    private function getMonthly() {
        $manader = isset($_GET['manader']) ? max(1, min(60, (int)$_GET['manader'])) : 24;

        try {
            // Månadsaggregering i två steg:
            // Steg 1: Daglig aggregering (MAX av kumulativa fält)
            // Steg 2: Månadsaggregering (SUM/AVG/MAX på dagvärden)
            // Korrekt aggregering: MAX(ibc_ok) per skifträknare per dag, sedan SUM per dag.
            // rebotling_ibc har kumulativa räkneverk — varje skifträknare har sin egen räknare.
            $sql = "
                SELECT
                    ar,
                    manad,
                    DATE_FORMAT(CONCAT(ar, '-', LPAD(manad, 2, '0'), '-01'), '%Y-%m') AS period,
                    COUNT(DISTINCT dag) AS antal_dagar,
                    SUM(daglig_ibc) AS total_ibc,
                    ROUND(AVG(daglig_ibc), 1) AS snitt_per_dag,
                    MAX(daglig_ibc) AS basta_dag_ibc
                FROM (
                    SELECT
                        dag,
                        YEAR(dag) AS ar,
                        MONTH(dag) AS manad,
                        SUM(shift_ibc) AS daglig_ibc
                    FROM (
                        SELECT
                            DATE(datum) AS dag,
                            skiftraknare,
                            MAX(ibc_ok) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(NOW(), INTERVAL :manader MONTH)
                          AND ibc_ok > 0
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY dag
                ) AS dagdata
                GROUP BY ar, manad
                ORDER BY ar, manad
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':manader', $manader, PDO::PARAM_INT);
            $stmt->execute();
            $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Konvertera numeriska strängar till rätt typ
            foreach ($monthly as &$row) {
                $row['ar']           = (int)$row['ar'];
                $row['manad']        = (int)$row['manad'];
                $row['antal_dagar']  = (int)$row['antal_dagar'];
                $row['total_ibc']    = (int)$row['total_ibc'];
                $row['snitt_per_dag'] = (float)$row['snitt_per_dag'];
                $row['basta_dag_ibc'] = (int)$row['basta_dag_ibc'];
            }
            unset($row);

            // KPI-beräkningar
            $arNu = (int)date('Y');
            $totalIbcAr = 0;
            $manaderMedData = [];
            $bastaManad = null;
            $bastaManadIbc = 0;

            foreach ($monthly as $m) {
                if ($m['ar'] === $arNu) {
                    $totalIbcAr += $m['total_ibc'];
                }
                $manaderMedData[] = $m['total_ibc'];
                if ($m['total_ibc'] > $bastaManadIbc) {
                    $bastaManadIbc = $m['total_ibc'];
                    $bastaManad = ['period' => $m['period'], 'total_ibc' => $m['total_ibc']];
                }
            }

            $snittPerManad = count($manaderMedData) > 0
                ? (int)round(array_sum($manaderMedData) / count($manaderMedData))
                : 0;

            echo json_encode([
                'success'        => true,
                'monthly'        => $monthly,
                'total_ibc_ar'   => $totalIbcAr,
                'snitt_per_manad' => $snittPerManad,
                'basta_manad'    => $bastaManad,
            ], JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            error_log('HistorikController::getMonthly PDOException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av månadsdata'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('HistorikController::getMonthly Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Daglig historik med filter/sortering/paginering
     * GET ?action=historik&run=daglig
     *   &from=YYYY-MM-DD   (default: -30 dagar)
     *   &to=YYYY-MM-DD     (default: idag)
     *   &operator=NNN       (operator number, 0 = alla)
     *   &sort=datum|ibc|kassation  (default: datum)
     *   &order=asc|desc     (default: desc)
     *   &page=1             (default: 1)
     *   &per_page=30        (default: 30, max 100)
     */
    private function getDaglig() {
        try {
            $fromDate   = trim($_GET['from'] ?? '');
            $toDate     = trim($_GET['to'] ?? '');
            $operatorNr = isset($_GET['operator']) ? (int)$_GET['operator'] : 0;
            $sortField  = trim($_GET['sort'] ?? 'datum');
            $sortOrder  = strtolower(trim($_GET['order'] ?? 'desc'));
            $page       = max(1, (int)($_GET['page'] ?? 1));
            $perPage    = max(1, min(100, (int)($_GET['per_page'] ?? 30)));

            // Validera datum
            if (!$fromDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
                $fromDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$toDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                $toDate = date('Y-m-d');
            }
            if ($fromDate > $toDate) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
            // Max 365 dagar
            $diff = (int)(new \DateTime($fromDate))->diff(new \DateTime($toDate))->days;
            if ($diff > 365) {
                $fromDate = date('Y-m-d', strtotime($toDate . ' -365 days'));
            }

            // Validera sortering
            $allowedSorts = ['datum', 'ibc', 'kassation'];
            if (!in_array($sortField, $allowedSorts, true)) {
                $sortField = 'datum';
            }
            if (!in_array($sortOrder, ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            // Operator-filter
            $opWhere = '';
            $params = [':from_date' => $fromDate, ':to_date' => $toDate];
            if ($operatorNr > 0) {
                $opWhere = " AND (op1 = :op_nr OR op2 = :op_nr2 OR op3 = :op_nr3)";
                $params[':op_nr']  = $operatorNr;
                $params[':op_nr2'] = $operatorNr;
                $params[':op_nr3'] = $operatorNr;
            }

            // SQL-sortering
            $orderBy = match ($sortField) {
                'ibc'        => "total_ibc {$sortOrder}",
                'kassation'  => "kassation_pct {$sortOrder}",
                default      => "dag {$sortOrder}",
            };

            // Räkna totalt antal rader (för paginering)
            $countSql = "
                SELECT COUNT(DISTINCT DATE(datum)) AS total_rows
                FROM rebotling_ibc
                WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                  AND ibc_ok IS NOT NULL
                  {$opWhere}
            ";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalRows = (int)$countStmt->fetchColumn();

            $totalPages = max(1, (int)ceil($totalRows / $perPage));
            $offset = ($page - 1) * $perPage;

            // Hämta daglig data
            $sql = "
                SELECT
                    dag,
                    SUM(shift_ok) AS total_ibc,
                    SUM(shift_ej_ok) AS total_ej_ok,
                    SUM(shift_ok) + SUM(shift_ej_ok) AS total_all,
                    CASE WHEN (SUM(shift_ok) + SUM(shift_ej_ok)) > 0
                         THEN ROUND(SUM(shift_ej_ok) / (SUM(shift_ok) + SUM(shift_ej_ok)) * 100, 1)
                         ELSE 0 END AS kassation_pct,
                    COUNT(DISTINCT skiftraknare) AS antal_skift
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                        MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE datum >= :from_date AND datum < DATE_ADD(:to_date, INTERVAL 1 DAY)
                      AND ibc_ok IS NOT NULL
                      {$opWhere}
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
                GROUP BY dag
                ORDER BY {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Konvertera typer
            foreach ($rows as &$row) {
                $row['total_ibc']     = (int)$row['total_ibc'];
                $row['total_ej_ok']   = (int)$row['total_ej_ok'];
                $row['total_all']     = (int)$row['total_all'];
                $row['kassation_pct'] = (float)$row['kassation_pct'];
                $row['antal_skift']   = (int)$row['antal_skift'];
            }
            unset($row);

            echo json_encode([
                'success'    => true,
                'daglig'     => $rows,
                'pagination' => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total_rows'  => $totalRows,
                    'total_pages' => $totalPages,
                ],
                'filter' => [
                    'from'     => $fromDate,
                    'to'       => $toDate,
                    'operator' => $operatorNr,
                    'sort'     => $sortField,
                    'order'    => $sortOrder,
                ],
            ], JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            error_log('HistorikController::getDaglig PDOException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av daglig historik'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('HistorikController::getDaglig Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * År-mot-år veckojämförelse (ISO-veckor)
     * GET ?action=historik&run=yearly
     */
    private function getYearly() {
        try {
            // Korrekt aggregering: MAX(ibc_ok) per skifträknare per dag, sedan SUM per dag.
            $sql = "
                SELECT
                    ar,
                    vecka,
                    SUM(daglig_ibc) AS ibc_vecka
                FROM (
                    SELECT
                        dag,
                        YEAR(dag) AS ar,
                        WEEK(dag, 1) AS vecka,
                        SUM(shift_ibc) AS daglig_ibc
                    FROM (
                        SELECT
                            DATE(datum) AS dag,
                            skiftraknare,
                            MAX(ibc_ok) AS shift_ibc
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(NOW(), INTERVAL 3 YEAR)
                          AND ibc_ok > 0
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_shift
                    GROUP BY dag
                ) AS dagdata
                GROUP BY ar, vecka
                ORDER BY ar, vecka
            ";

            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Strukturera som { "2024": [{vecka:1, ibc:123}, ...], "2025": [...] }
            $yearly = [];
            foreach ($rows as $row) {
                $ar    = (string)(int)$row['ar'];
                $vecka = (int)$row['vecka'];
                $ibc   = (int)$row['ibc_vecka'];

                if (!isset($yearly[$ar])) {
                    $yearly[$ar] = [];
                }
                $yearly[$ar][] = ['vecka' => $vecka, 'ibc_vecka' => $ibc];
            }

            echo json_encode([
                'success' => true,
                'yearly'  => $yearly,
            ], JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            error_log('HistorikController::getYearly PDOException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av årsdata'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log('HistorikController::getYearly Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel'], JSON_UNESCAPED_UNICODE);
        }
    }
}
