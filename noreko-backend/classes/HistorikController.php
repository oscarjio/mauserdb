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
            echo json_encode(['success' => false, 'error' => 'Endast GET tillåtet']);
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
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')]);
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
                        DATE(datum) AS dag,
                        YEAR(datum) AS ar,
                        MONTH(datum) AS manad,
                        MAX(ibc_ok) AS daglig_ibc
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(NOW(), INTERVAL :manader MONTH)
                      AND ibc_ok > 0
                    GROUP BY DATE(datum)
                ) AS dagdata
                GROUP BY ar, manad
                ORDER BY ar, manad
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':manader', $manader, PDO::PARAM_INT);
            $stmt->execute();
            $monthly = $stmt->fetchAll();

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
            ]);

        } catch (PDOException $e) {
            error_log('HistorikController::getMonthly PDOException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av månadsdata']);
        } catch (Exception $e) {
            error_log('HistorikController::getMonthly Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel']);
        }
    }

    /**
     * År-mot-år veckojämförelse (ISO-veckor)
     * GET ?action=historik&run=yearly
     */
    private function getYearly() {
        try {
            $sql = "
                SELECT
                    ar,
                    vecka,
                    SUM(daglig_ibc) AS ibc_vecka
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        YEAR(datum) AS ar,
                        WEEK(datum, 1) AS vecka,
                        MAX(ibc_ok) AS daglig_ibc
                    FROM rebotling_ibc
                    WHERE datum >= DATE_SUB(NOW(), INTERVAL 3 YEAR)
                      AND ibc_ok > 0
                    GROUP BY DATE(datum)
                ) AS dagdata
                GROUP BY ar, vecka
                ORDER BY ar, vecka
            ";

            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll();

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
            ]);

        } catch (PDOException $e) {
            error_log('HistorikController::getYearly PDOException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Databasfel vid hämtning av årsdata']);
        } catch (Exception $e) {
            error_log('HistorikController::getYearly Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel']);
        }
    }
}
