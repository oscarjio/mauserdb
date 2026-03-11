<?php
/**
 * SkiftjamforelseController.php
 * Skiftjämförelse-dashboard — jämför dag/kväll/nattskift.
 * Hjälper VD att fördela resurser och identifiera svaga skift.
 *
 * Endpoints via ?action=skiftjamforelse&run=XXX:
 *   run=shift-comparison&period=7|30|90
 *       Aggregerar data per skift (dag/kväll/natt) för vald period.
 *       Returnerar per skift: totalt IBC OK, IBC/h, kvalitet%, total stopptid i min,
 *       antal skiftpass, OEE-snitt.
 *
 *   run=shift-trend&period=30
 *       Veckovis breakdown per skift de senaste veckorna (för trendgraf).
 *
 *   run=shift-operators&shift=dag|kvall|natt&period=30
 *       Topp-operatörer per skift.
 *
 * Auth: session_id krävs (401 om ej inloggad).
 *
 * Skiftdefinitioner:
 *   dag:   06:00–14:00
 *   kväll: 14:00–22:00
 *   natt:  22:00–06:00 (nästa dag)
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, stopporsak_registreringar, operators
 */
class SkiftjamforelseController {
    private $pdo;

    // Skiftdefinitioner (start-timme, slut-timme, 0-23)
    private const SKIFT = [
        'dag'   => ['label' => 'Dagskift',    'start' => 6,  'end' => 14],
        'kvall' => ['label' => 'Kvällsskift',  'start' => 14, 'end' => 22],
        'natt'  => ['label' => 'Nattskift',    'start' => 22, 'end' => 6],
    ];

    // OEE — teoretisk max IBC/h (60 = ett var 60:e sekund)
    private const TEORIETISK_MAX_IBC_H = 60.0;
    // Planerad skifttid i minuter (8h)
    private const PLANERAD_MIN = 480;

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
            case 'shift-comparison': $this->getShiftComparison(); break;
            case 'shift-trend':      $this->getShiftTrend();      break;
            case 'shift-operators':  $this->getShiftOperators();  break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
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
        ]);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getPeriod(): int {
        $p = (int)($_GET['period'] ?? 30);
        if (!in_array($p, [7, 30, 90], true)) {
            return 30;
        }
        return $p;
    }

    /**
     * Returnerar ett WHERE-villkor (utan WHERE-nyckelord) som filtrerar
     * en rad på ett timestamp-fält (t.ex. created_at) till ett visst skift.
     *
     * Dag:   HOUR(col) >= 6  AND HOUR(col) < 14
     * Kväll: HOUR(col) >= 14 AND HOUR(col) < 22
     * Natt:  HOUR(col) >= 22 OR  HOUR(col) < 6
     */
    private function skiftTimewhere(string $skift, string $col): string {
        $def = self::SKIFT[$skift];
        if ($skift === 'natt') {
            return "(HOUR({$col}) >= {$def['start']} OR HOUR({$col}) < {$def['end']})";
        }
        return "(HOUR({$col}) >= {$def['start']} AND HOUR({$col}) < {$def['end']})";
    }

    /**
     * Hämta produktionsdata (IBC OK, IBC ej OK, runtime_min) per skift för en period.
     * Använder MAX(ibc_ok) per skiftraknare (kumulativ räknare).
     */
    private function getProduktionPerSkift(string $fromDate, string $toDate): array {
        // Vi grupperar per datum+skiftraknare och tar MAX av kumulativa räknare.
        // Vi filtrerar på timme av created_at för att bestämma vilket skift det tillhör.
        $result = [];

        foreach (array_keys(self::SKIFT) as $skift) {
            $timeCond = $this->skiftTimewhere($skift, 'created_at');

            $stmt = $this->pdo->prepare(
                "SELECT
                    COUNT(DISTINCT skiftraknare) AS antal_pass,
                    COALESCE(SUM(max_ok),    0) AS ibc_ok,
                    COALESCE(SUM(max_ej_ok), 0) AS ibc_ej_ok,
                    COALESCE(SUM(max_runtime), 0) AS runtime_min
                 FROM (
                    SELECT
                        skiftraknare,
                        MAX(ibc_ok)      AS max_ok,
                        MAX(ibc_ej_ok)   AS max_ej_ok,
                        MAX(runtime_plc) AS max_runtime
                    FROM rebotling_ibc
                    WHERE DATE(created_at) BETWEEN ? AND ?
                      AND {$timeCond}
                    GROUP BY skiftraknare
                    HAVING COUNT(*) > 1
                 ) s"
            );
            $stmt->execute([$fromDate, $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $ibcOk    = (int)($row['ibc_ok']    ?? 0);
            $ibcEjOk  = (int)($row['ibc_ej_ok'] ?? 0);
            $runtime  = (int)($row['runtime_min'] ?? 0);
            $antalPass= (int)($row['antal_pass'] ?? 0);
            $ibcTotal = $ibcOk + $ibcEjOk;

            $ibcPerH   = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;
            $kvalitet  = $ibcTotal > 0 ? round(($ibcOk / $ibcTotal) * 100, 1) : 0.0;

            // OEE-beräkning
            $planMinTotal   = $antalPass * self::PLANERAD_MIN;
            $tillganglighet = $planMinTotal > 0 ? min(1.0, $runtime / $planMinTotal) : 0.0;
            $prestanda      = $runtime > 0
                ? min(1.0, ($ibcPerH / self::TEORIETISK_MAX_IBC_H))
                : 0.0;
            $kvalFaktor = $ibcTotal > 0 ? ($ibcOk / $ibcTotal) : 0.0;
            $oee = round($tillganglighet * $prestanda * $kvalFaktor * 100, 1);

            $result[$skift] = [
                'skift'         => $skift,
                'label'         => self::SKIFT[$skift]['label'],
                'antal_pass'    => $antalPass,
                'ibc_ok'        => $ibcOk,
                'ibc_ej_ok'     => $ibcEjOk,
                'ibc_total'     => $ibcTotal,
                'runtime_min'   => $runtime,
                'ibc_per_h'     => $ibcPerH,
                'kvalitet_pct'  => $kvalitet,
                'oee_pct'       => $oee,
                'tillganglighet_pct' => round($tillganglighet * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * Hämta total stopptid i minuter per skift för en period.
     */
    private function getStopptidPerSkift(string $fromDate, string $toDate): array {
        // Kontrollera att stopporsak_registreringar finns
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if (!$check || $check->rowCount() === 0) {
                return ['dag' => 0, 'kvall' => 0, 'natt' => 0];
            }
        } catch (\PDOException $e) {
            return ['dag' => 0, 'kvall' => 0, 'natt' => 0];
        }

        $stoppResult = [];
        foreach (array_keys(self::SKIFT) as $skift) {
            $timeCond = $this->skiftTimewhere($skift, 'start_time');

            $stmt = $this->pdo->prepare(
                "SELECT
                    COALESCE(ROUND(SUM(
                        TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))
                    ), 0), 0) AS total_min
                 FROM stopporsak_registreringar
                 WHERE linje = 'rebotling'
                   AND DATE(start_time) BETWEEN ? AND ?
                   AND {$timeCond}"
            );
            $stmt->execute([$fromDate, $toDate]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stoppResult[$skift] = (int)($row['total_min'] ?? 0);
        }

        return $stoppResult;
    }

    // ================================================================
    // run=shift-comparison
    // ================================================================

    private function getShiftComparison(): void {
        $period   = $this->getPeriod();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        try {
            $prod  = $this->getProduktionPerSkift($fromDate, $toDate);
            $stopp = $this->getStopptidPerSkift($fromDate, $toDate);

            // Slå ihop stopptid
            foreach (array_keys(self::SKIFT) as $skift) {
                $prod[$skift]['stopptid_min'] = $stopp[$skift] ?? 0;
            }

            // Beräkna vilket skift är bäst (IBC/h)
            $basta     = null;
            $bastaIbcH = -1;
            foreach ($prod as $skift => $data) {
                if ($data['ibc_per_h'] > $bastaIbcH) {
                    $bastaIbcH = $data['ibc_per_h'];
                    $basta = $skift;
                }
            }

            // Genomsnitt IBC/h
            $snittIbcH = 0;
            $antalMedData = 0;
            foreach ($prod as $data) {
                if ($data['ibc_per_h'] > 0) {
                    $snittIbcH += $data['ibc_per_h'];
                    $antalMedData++;
                }
            }
            $snittIbcH = $antalMedData > 0 ? round($snittIbcH / $antalMedData, 2) : 0;

            // Markera bästa skiftet och beräkna diff mot snitt
            foreach ($prod as $skift => &$data) {
                $data['ar_bast'] = ($skift === $basta);
                $data['diff_fran_snitt_pct'] = $snittIbcH > 0
                    ? round((($data['ibc_per_h'] - $snittIbcH) / $snittIbcH) * 100, 1)
                    : 0.0;
            }
            unset($data);

            // Auto-genererad sammanfattningstext
            $summText = $this->genereraSammanfattning($prod, $basta, $snittIbcH);

            $this->sendSuccess([
                'period'      => $period,
                'from_date'   => $fromDate,
                'to_date'     => $toDate,
                'skift'       => array_values($prod),
                'basta_skift' => $basta,
                'snitt_ibc_h' => $snittIbcH,
                'sammanfattning' => $summText,
            ]);

        } catch (\Exception $e) {
            error_log('SkiftjamforelseController::getShiftComparison: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skiftjämförelse', 500);
        }
    }

    /**
     * Genererar en auto-text-sammanfattning för VD.
     */
    private function genereraSammanfattning(array $prod, ?string $basta, float $snittIbcH): string {
        if ($basta === null || $snittIbcH <= 0) {
            return 'Otillräcklig data för att generera sammanfattning.';
        }

        $bastaLabel  = self::SKIFT[$basta]['label'];
        $bastaData   = $prod[$basta];
        $diffPct     = abs($bastaData['diff_fran_snitt_pct']);
        $bastaIbcH   = $bastaData['ibc_per_h'];

        $delar = [];
        $delar[] = "Bästa skiftet är {$bastaLabel} med {$bastaIbcH} IBC/h"
            . ($diffPct > 0 ? " ({$diffPct}% högre än genomsnittet {$snittIbcH} IBC/h)." : ".");

        // Sämsta skiftet
        $samstSkift    = null;
        $samstaIbcH    = PHP_INT_MAX;
        foreach ($prod as $skift => $data) {
            if ($data['antal_pass'] > 0 && $data['ibc_per_h'] < $samstaIbcH) {
                $samstaIbcH = $data['ibc_per_h'];
                $samstSkift = $skift;
            }
        }
        if ($samstSkift && $samstSkift !== $basta) {
            $samstLabel = self::SKIFT[$samstSkift]['label'];
            $delar[] = "{$samstLabel} presterar lägst med {$samstaIbcH} IBC/h — möjlighet till förbättring.";
        }

        // Kvalitetsnota
        $lagKvalitet = [];
        foreach ($prod as $skift => $data) {
            if ($data['antal_pass'] > 0 && $data['kvalitet_pct'] < 95) {
                $lagKvalitet[] = self::SKIFT[$skift]['label'] . ' (' . $data['kvalitet_pct'] . '%)';
            }
        }
        if (!empty($lagKvalitet)) {
            $delar[] = 'Kvalitet under 95%: ' . implode(', ', $lagKvalitet) . '.';
        }

        return implode(' ', $delar);
    }

    // ================================================================
    // run=shift-trend
    // ================================================================

    private function getShiftTrend(): void {
        $period   = $this->getPeriod();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        try {
            // Hämta veckovis IBC/h per skift
            // Veckonummer + år som label
            $veckor = [];

            // Bygg en lista med veckor i perioden
            $current = strtotime($fromDate);
            $end     = strtotime($toDate);

            while ($current <= $end) {
                $vecka = (int)date('W', $current);
                $ar    = (int)date('Y', $current);
                $key   = $ar . '-W' . str_pad($vecka, 2, '0', STR_PAD_LEFT);

                if (!isset($veckor[$key])) {
                    $veckor[$key] = [
                        'vecka'  => $key,
                        'label'  => 'V' . $vecka,
                        'dag'    => null,
                        'kvall'  => null,
                        'natt'   => null,
                    ];
                }
                $current = strtotime('+1 day', $current);
            }

            // Hämta data per vecka+skift
            foreach ($veckor as $vKey => &$vData) {
                // Extrahera vecka+år
                [$vAr, $vW] = explode('-W', $vKey);
                $vNum = (int)$vW;
                $vArNum = (int)$vAr;

                // Datum-intervall för veckan
                $vStart = date('Y-m-d', strtotime("{$vArNum}-W{$vW}-1"));
                $vEnd   = date('Y-m-d', strtotime("{$vArNum}-W{$vW}-7"));

                foreach (array_keys(self::SKIFT) as $skift) {
                    $timeCond = $this->skiftTimewhere($skift, 'created_at');

                    $stmt = $this->pdo->prepare(
                        "SELECT
                            COALESCE(SUM(max_ok), 0)    AS ibc_ok,
                            COALESCE(SUM(max_runtime), 0) AS runtime_min
                         FROM (
                            SELECT
                                MAX(ibc_ok)      AS max_ok,
                                MAX(runtime_plc) AS max_runtime
                            FROM rebotling_ibc
                            WHERE DATE(created_at) BETWEEN ? AND ?
                              AND {$timeCond}
                            GROUP BY skiftraknare
                            HAVING COUNT(*) > 1
                         ) s"
                    );
                    $stmt->execute([$vStart, $vEnd]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                    $ibcOk   = (int)($row['ibc_ok']    ?? 0);
                    $runtime = (int)($row['runtime_min'] ?? 0);
                    $ibcPerH = $runtime > 0 ? round($ibcOk / ($runtime / 60), 2) : 0.0;

                    $vData[$skift] = $ibcPerH > 0 ? $ibcPerH : null;
                }
            }
            unset($vData);

            // Filtrera bort veckor utan data
            $veckorMedData = array_filter($veckor, function($v) {
                return $v['dag'] !== null || $v['kvall'] !== null || $v['natt'] !== null;
            });

            $this->sendSuccess([
                'period'  => $period,
                'veckor'  => array_values($veckorMedData),
            ]);

        } catch (\Exception $e) {
            error_log('SkiftjamforelseController::getShiftTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta skifttrenddata', 500);
        }
    }

    // ================================================================
    // run=shift-operators
    // ================================================================

    private function getShiftOperators(): void {
        $skift  = trim($_GET['shift'] ?? 'dag');
        $period = $this->getPeriod();

        if (!isset(self::SKIFT[$skift])) {
            $this->sendError('Ogiltigt skift. Använd dag, kvall eller natt.');
            return;
        }

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));
        $timeCond = $this->skiftTimewhere($skift, 'created_at');

        try {
            // Hämta topp-operatörer baserat på antal IBC för valt skift och period
            $stmt = $this->pdo->prepare(
                "SELECT
                    op_num,
                    COUNT(*)                 AS antal_ibc,
                    ROUND(AVG(cycle_sek), 1) AS avg_cykeltid_sek
                 FROM (
                    SELECT op_num,
                        TIMESTAMPDIFF(SECOND,
                            LAG(datum) OVER (PARTITION BY skiftraknare ORDER BY datum),
                            datum
                        ) AS cycle_sek
                    FROM (
                        SELECT op1 AS op_num, datum, skiftraknare, created_at
                        FROM rebotling_ibc
                        WHERE DATE(created_at) BETWEEN :f1 AND :t1
                          AND {$timeCond}
                          AND op1 IS NOT NULL AND op1 > 0
                        UNION ALL
                        SELECT op2 AS op_num, datum, skiftraknare, created_at
                        FROM rebotling_ibc
                        WHERE DATE(created_at) BETWEEN :f2 AND :t2
                          AND {$timeCond}
                          AND op2 IS NOT NULL AND op2 > 0
                        UNION ALL
                        SELECT op3 AS op_num, datum, skiftraknare, created_at
                        FROM rebotling_ibc
                        WHERE DATE(created_at) BETWEEN :f3 AND :t3
                          AND {$timeCond}
                          AND op3 IS NOT NULL AND op3 > 0
                    ) ops
                 ) lagged
                 WHERE cycle_sek >= 30 AND cycle_sek <= 1800
                 GROUP BY op_num
                 ORDER BY antal_ibc DESC
                 LIMIT 5"
            );
            $stmt->execute([
                ':f1' => $fromDate, ':t1' => $toDate,
                ':f2' => $fromDate, ':t2' => $toDate,
                ':f3' => $fromDate, ':t3' => $toDate,
            ]);
            $opRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $operatorer = [];
            if (!empty($opRows)) {
                $opNums = array_column($opRows, 'op_num');
                $ph = implode(',', array_fill(0, count($opNums), '?'));
                $nameStmt = $this->pdo->prepare(
                    "SELECT number, name FROM operators WHERE number IN ({$ph})"
                );
                $nameStmt->execute($opNums);
                $names = [];
                foreach ($nameStmt->fetchAll(\PDO::FETCH_ASSOC) as $nr) {
                    $names[(int)$nr['number']] = $nr['name'];
                }

                foreach ($opRows as $i => $r) {
                    $num = (int)$r['op_num'];
                    $operatorer[] = [
                        'plats'            => $i + 1,
                        'operator_num'     => $num,
                        'operator_namn'    => $names[$num] ?? "Operatör #{$num}",
                        'antal_ibc'        => (int)$r['antal_ibc'],
                        'avg_cykeltid_sek' => (float)$r['avg_cykeltid_sek'],
                    ];
                }
            }

            $this->sendSuccess([
                'skift'       => $skift,
                'label'       => self::SKIFT[$skift]['label'],
                'period'      => $period,
                'from_date'   => $fromDate,
                'to_date'     => $toDate,
                'operatorer'  => $operatorer,
            ]);

        } catch (\Exception $e) {
            error_log('SkiftjamforelseController::getShiftOperators: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsdata', 500);
        }
    }
}
