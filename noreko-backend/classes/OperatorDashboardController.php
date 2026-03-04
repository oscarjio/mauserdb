<?php
/**
 * OperatorDashboardController
 * GET ?action=operator-dashboard&run=today
 *
 * Returnerar alla operatörer som jobbat idag (baserat på rebotling_skiftrapport)
 * med aggregerade IBC/h, kvalitet%, senaste aktivitet (i minuter sedan skiftslut),
 * samt övergripande KPI:er.
 *
 * Kräver INGEN session — publik GET.
 */
class OperatorDashboardController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle() {
        $run = $_GET['run'] ?? '';

        if ($run === 'today') {
            $this->getToday();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Okänd metod']);
        }
    }

    /**
     * Hämtar operatörsstatus för idag.
     * Aggregerar rebotling_skiftrapport per operator-nummer.
     * Beräknar IBC/h baserat på total ibc_ok / total drifttid (minuter).
     * "Senaste aktivitet" = antalet minuter sedan senaste skiftet avslutades idag.
     */
    private function getToday() {
        try {
            $today = date('Y-m-d');

            // Hämta alla skift för idag, unioner op1/op2/op3 → per operator-nummer
            $sql = "
                SELECT
                    op_num,
                    COUNT(DISTINCT skift_id)            AS antal_skift,
                    SUM(ibc_ok)                         AS tot_ibc_ok,
                    SUM(tot_totalt)                     AS tot_totalt,
                    SUM(drifttid_min)                   AS tot_drifttid,
                    MAX(updated_at)                     AS senaste_aktivitet
                FROM (
                    SELECT id AS skift_id, op1 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE DATE(datum) = :today AND op1 IS NOT NULL AND op1 > 0
                    UNION ALL
                    SELECT id AS skift_id, op2 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE DATE(datum) = :today AND op2 IS NOT NULL AND op2 > 0
                    UNION ALL
                    SELECT id AS skift_id, op3 AS op_num, COALESCE(ibc_ok,0) AS ibc_ok,
                           COALESCE(totalt,0) AS tot_totalt, COALESCE(drifttid,0) AS drifttid_min,
                           updated_at
                    FROM rebotling_skiftrapport
                    WHERE DATE(datum) = :today AND op3 IS NOT NULL AND op3 > 0
                ) AS alla
                GROUP BY op_num
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':today' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode([
                    'success'       => true,
                    'datum'         => $today,
                    'operatorer'    => [],
                    'total_ibc'     => 0,
                    'snitt_ibc_per_h' => 0,
                    'bast_namn'     => null,
                    'bast_ibc_per_h'=> 0,
                ]);
                return;
            }

            // Hämta alla operatörsnamn för berörda nummer
            $nums = array_column($rows, 'op_num');
            $nums = array_map('intval', array_unique($nums));
            $placeholders = implode(',', array_fill(0, count($nums), '?'));
            $nameStmt = $this->pdo->prepare(
                "SELECT number, name FROM operators WHERE number IN ($placeholders)"
            );
            $nameStmt->execute($nums);
            $nameMap = [];
            foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $op) {
                $nameMap[(int)$op['number']] = $op['name'];
            }

            $now = time();
            $operatorer = [];
            $totalIbc = 0;
            $sumIbcPerH = 0.0;
            $countWithRate = 0;
            $bastNamn = '';
            $bastRate = 0.0;

            foreach ($rows as $r) {
                $opNum    = (int)$r['op_num'];
                $namn     = $nameMap[$opNum] ?? ('Operatör #' . $opNum);
                $ibcIdag  = (int)$r['tot_ibc_ok'];
                $totalt   = (int)$r['tot_totalt'];
                $drifttid = (float)$r['tot_drifttid']; // minuter

                // IBC per timme
                $ibcPerH = ($drifttid > 0) ? round($ibcIdag / ($drifttid / 60.0), 1) : 0;

                // Kvalitet%
                $kvalitet = ($totalt > 0) ? round($ibcIdag * 100.0 / $totalt, 1) : null;

                // Minuter sedan senaste aktivitet
                $senasteTid = $r['senaste_aktivitet'];
                $minuterSedan = null;
                if ($senasteTid) {
                    $ts = strtotime($senasteTid);
                    if ($ts !== false) {
                        $minuterSedan = (int)max(0, round(($now - $ts) / 60));
                    }
                }

                // Status-bedömning
                if ($minuterSedan === null || $minuterSedan > 30) {
                    $status = 'inaktiv';
                } elseif ($ibcPerH >= 18) {
                    $status = 'bra';
                } elseif ($ibcPerH >= 12) {
                    $status = 'ok';
                } else {
                    $status = 'lag';
                }

                // Initialer (max 2 tecken)
                $delar = explode(' ', trim($namn));
                $initialer = '';
                foreach ($delar as $d) {
                    if ($d !== '') $initialer .= strtoupper(mb_substr($d, 0, 1));
                }
                $initialer = mb_substr($initialer, 0, 2);

                $operatorer[] = [
                    'op_id'        => $opNum,
                    'namn'         => $namn,
                    'initialer'    => $initialer,
                    'ibc_idag'     => $ibcIdag,
                    'ibc_per_h'    => $ibcPerH,
                    'kvalitet_pct' => $kvalitet,
                    'minuter_sedan'=> $minuterSedan,
                    'status'       => $status,
                ];

                $totalIbc += $ibcIdag;
                if ($ibcPerH > 0) {
                    $sumIbcPerH += $ibcPerH;
                    $countWithRate++;
                }
                if ($ibcPerH > $bastRate) {
                    $bastRate = $ibcPerH;
                    $bastNamn = $namn;
                }
            }

            // Sortera: aktiva överst (status != inaktiv), sedan efter ibc_per_h desc
            usort($operatorer, function($a, $b) {
                $aInaktiv = ($a['status'] === 'inaktiv') ? 1 : 0;
                $bInaktiv = ($b['status'] === 'inaktiv') ? 1 : 0;
                if ($aInaktiv !== $bInaktiv) return $aInaktiv - $bInaktiv;
                return $b['ibc_per_h'] <=> $a['ibc_per_h'];
            });

            $snittIbcPerH = ($countWithRate > 0)
                ? round($sumIbcPerH / $countWithRate, 1)
                : 0;

            echo json_encode([
                'success'         => true,
                'datum'           => $today,
                'operatorer'      => $operatorer,
                'total_ibc'       => $totalIbc,
                'snitt_ibc_per_h' => $snittIbcPerH,
                'bast_namn'       => $bastNamn ?: null,
                'bast_ibc_per_h'  => $bastRate,
            ]);
        } catch (Exception $e) {
            error_log('OperatorDashboardController getToday: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Kunde inte hämta operatörsstatus']);
        }
    }
}
