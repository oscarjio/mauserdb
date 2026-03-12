<?php
/**
 * KvalitetsTrendbrottController.php
 * Kvalitetsanalys — trendbrott-detektion
 *
 * Automatiskt flagga när kassationsgraden avviker markant från historiskt snitt.
 *
 * Endpoints via ?action=kvalitetstrendbrott&run=XXX:
 *   - run=overview&period=7|30|90   → daglig kassationsgrad (%) med rörligt medelvärde (7d),
 *                                      flagga dagar med >2σ avvikelse
 *   - run=alerts&period=30|90       → lista alla trendbrott sorterade efter allvarlighetsgrad
 *   - run=daily-detail&date=YYYY-MM-DD → drill-down för en specifik dag
 *
 * Tabeller:
 *   rebotling_ibc           (ibc_ok, ibc_ej_ok, datum, skiftraknare, op1, op2, op3)
 *   rebotling_skiftrapport  (datum, skiftraknare, op1, op2, op3, ibc_ok, ibc_ej_ok, drifttid)
 *   operators               (id, number, name)
 *   stoppage_log / stopporsak_registreringar
 */
class KvalitetsTrendbrottController {
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
            case 'overview':      $this->getOverview();     break;
            case 'alerts':        $this->getAlerts();       break;
            case 'daily-detail':  $this->getDailyDetail();  break;
            default:
                $this->sendError('Okänt run-värde: ' . $run);
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function sendJson(array $data): void {
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
        ]);
    }

    private function getPeriod(): int {
        $period = (int)($_GET['period'] ?? 30);
        if (!in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }
        return $period;
    }

    /**
     * Hämta daglig kassationsgrad (%) baserat på rebotling_ibc.
     * Returnerar array med [datum => ['ok' => X, 'ej_ok' => Y, 'total' => Z, 'kassation_pct' => W]]
     */
    private function getDailyKassation(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(datum) AS dag,
                COALESCE(SUM(shift_ok), 0) AS total_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS total_ej_ok
            FROM (
                SELECT
                    datum,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS per_shift
            GROUP BY dag
            ORDER BY dag ASC
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $ok    = (int)$row['total_ok'];
            $ejOk  = (int)$row['total_ej_ok'];
            $total = $ok + $ejOk;
            $pct   = $total > 0 ? round($ejOk / $total * 100, 2) : 0;
            $result[] = [
                'datum'         => $row['dag'],
                'ok'            => $ok,
                'ej_ok'         => $ejOk,
                'total'         => $total,
                'kassation_pct' => $pct,
            ];
        }
        return $result;
    }

    /**
     * Beräkna rörligt medelvärde (moving average) för en array av värden.
     */
    private function movingAverage(array $values, int $window = 7): array {
        $ma = [];
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            $start = max(0, $i - $window + 1);
            $slice = array_slice($values, $start, $i - $start + 1);
            $ma[] = count($slice) > 0 ? round(array_sum($slice) / count($slice), 2) : 0;
        }
        return $ma;
    }

    /**
     * Beräkna standardavvikelse.
     */
    private function stddev(array $values): float {
        $n = count($values);
        if ($n < 2) return 0;
        $mean = array_sum($values) / $n;
        $variance = 0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        return sqrt($variance / ($n - 1));
    }

    // ================================================================
    // ENDPOINT: overview
    // ================================================================

    private function getOverview(): void {
        $period   = $this->getPeriod();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        try {
            $dailyData = $this->getDailyKassation($fromDate, $toDate);

            if (empty($dailyData)) {
                $this->sendJson([
                    'daily'              => [],
                    'snitt_kassation'    => 0,
                    'stddev'             => 0,
                    'antal_avvikelser'   => 0,
                    'senaste_avvikelse'  => null,
                    'period'             => $period,
                    'trend'              => 'stable',
                ]);
                return;
            }

            $pctValues = array_column($dailyData, 'kassation_pct');
            $mean      = count($pctValues) > 0 ? array_sum($pctValues) / count($pctValues) : 0;
            $sd        = $this->stddev($pctValues);
            $ma        = $this->movingAverage($pctValues, 7);
            $upperBound = round($mean + 2 * $sd, 2);
            $lowerBound = round(max(0, $mean - 2 * $sd), 2);

            $avvikelser    = 0;
            $senasteAvv    = null;
            $enrichedDaily = [];

            for ($i = 0; $i < count($dailyData); $i++) {
                $item  = $dailyData[$i];
                $pct   = $item['kassation_pct'];
                $sigma = $sd > 0 ? round(abs($pct - $mean) / $sd, 2) : 0;
                $isAvvikelse = $sd > 0 && abs($pct - $mean) > 2 * $sd;
                $typ = null;

                if ($isAvvikelse) {
                    $avvikelser++;
                    $typ = $pct > $mean ? 'hög' : 'låg';
                    $senasteAvv = [
                        'datum'         => $item['datum'],
                        'kassation_pct' => $pct,
                        'typ'           => $typ,
                        'sigma'         => $sigma,
                    ];
                }

                $enrichedDaily[] = [
                    'datum'           => $item['datum'],
                    'kassation_pct'   => $pct,
                    'ok'              => $item['ok'],
                    'ej_ok'           => $item['ej_ok'],
                    'total'           => $item['total'],
                    'ma7'             => $ma[$i],
                    'avvikelse'       => $isAvvikelse,
                    'avvikelse_sigma' => $sigma,
                    'avvikelse_typ'   => $typ,
                ];
            }

            // Trend: jämför första och andra halvan
            $halfCount = (int)floor(count($pctValues) / 2);
            $trend = 'stable';
            if ($halfCount > 0) {
                $firstHalf  = array_slice($pctValues, 0, $halfCount);
                $secondHalf = array_slice($pctValues, $halfCount);
                $avgFirst   = array_sum($firstHalf) / count($firstHalf);
                $avgSecond  = array_sum($secondHalf) / count($secondHalf);
                if ($avgSecond > $avgFirst * 1.1) {
                    $trend = 'sämre';
                } elseif ($avgSecond < $avgFirst * 0.9) {
                    $trend = 'bättre';
                }
            }

            $this->sendJson([
                'daily'              => $enrichedDaily,
                'snitt_kassation'    => round($mean, 2),
                'stddev'             => round($sd, 2),
                'upper_bound'        => $upperBound,
                'lower_bound'        => $lowerBound,
                'antal_avvikelser'   => $avvikelser,
                'senaste_avvikelse'  => $senasteAvv,
                'period'             => $period,
                'trend'              => $trend,
            ]);
        } catch (\PDOException $e) {
            error_log('KvalitetsTrendbrottController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta överblick', 500);
        }
    }

    // ================================================================
    // ENDPOINT: alerts
    // ================================================================

    private function getAlerts(): void {
        $period   = $this->getPeriod();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));

        try {
            $dailyData = $this->getDailyKassation($fromDate, $toDate);
            if (empty($dailyData)) {
                $this->sendJson(['alerts' => [], 'period' => $period]);
                return;
            }

            $pctValues = array_column($dailyData, 'kassation_pct');
            $mean      = array_sum($pctValues) / count($pctValues);
            $sd        = $this->stddev($pctValues);

            // Hämta skiftdata för dagar med avvikelse
            $alerts = [];

            foreach ($dailyData as $item) {
                $pct = $item['kassation_pct'];
                if ($sd <= 0 || abs($pct - $mean) <= 2 * $sd) {
                    continue;
                }

                $sigma = round(abs($pct - $mean) / $sd, 2);
                $typ   = $pct > $mean ? 'hög' : 'låg';
                $datum = $item['datum'];

                // Hämta skift- och operatörsinfo för denna dag
                $shiftInfo = $this->getShiftInfoForDate($datum);

                $alerts[] = [
                    'datum'           => $datum,
                    'kassation_pct'   => $pct,
                    'avvikelse_sigma' => $sigma,
                    'typ'             => $typ,
                    'ok'              => $item['ok'],
                    'ej_ok'           => $item['ej_ok'],
                    'total'           => $item['total'],
                    'skift'           => $shiftInfo['skift'] ?? [],
                    'operators'       => $shiftInfo['operators'] ?? [],
                ];
            }

            // Sortera efter allvarlighetsgrad (sigma) fallande
            usort($alerts, function ($a, $b) {
                return $b['avvikelse_sigma'] <=> $a['avvikelse_sigma'];
            });

            $this->sendJson([
                'alerts'           => $alerts,
                'period'           => $period,
                'snitt_kassation'  => round($mean, 2),
                'stddev'           => round($sd, 2),
            ]);
        } catch (\PDOException $e) {
            error_log('KvalitetsTrendbrottController::getAlerts: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta varningar', 500);
        }
    }

    /**
     * Hämta skift- och operatörsinfo för en given dag.
     */
    private function getShiftInfoForDate(string $date): array {
        $result = ['skift' => [], 'operators' => []];

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    s.skiftraknare,
                    s.ibc_ok,
                    COALESCE(s.ibc_ej_ok, 0) AS ibc_ej_ok,
                    s.op1, s.op2, s.op3,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE s.datum = :datum
                ORDER BY s.skiftraknare ASC
            ");
            $stmt->execute([':datum' => $date]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $operatorSet = [];
            foreach ($rows as $row) {
                $total = (int)$row['ibc_ok'] + (int)$row['ibc_ej_ok'];
                $kass  = $total > 0 ? round((int)$row['ibc_ej_ok'] / $total * 100, 2) : 0;

                $result['skift'][] = [
                    'skiftraknare'  => (int)$row['skiftraknare'],
                    'ibc_ok'        => (int)$row['ibc_ok'],
                    'ibc_ej_ok'     => (int)$row['ibc_ej_ok'],
                    'total'         => $total,
                    'kassation_pct' => $kass,
                ];

                foreach (['op1', 'op2', 'op3'] as $opCol) {
                    $opNum  = $row[$opCol] ?? null;
                    $opName = $row[$opCol . '_name'] ?? null;
                    if ($opNum && !isset($operatorSet[$opNum])) {
                        $operatorSet[$opNum] = [
                            'id'   => (int)$opNum,
                            'name' => $opName ?? ('Operatör ' . $opNum),
                        ];
                    }
                }
            }
            $result['operators'] = array_values($operatorSet);
        } catch (\PDOException $e) {
            error_log('KvalitetsTrendbrottController::getShiftInfoForDate: ' . $e->getMessage());
        }

        return $result;
    }

    // ================================================================
    // ENDPOINT: daily-detail
    // ================================================================

    private function getDailyDetail(): void {
        $date = trim($_GET['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendError('Ogiltigt datum. Förväntat format: YYYY-MM-DD');
            return;
        }

        try {
            // 1. Daglig totalkassation
            $dailyData = $this->getDailyKassation($date, $date);
            $dayInfo = $dailyData[0] ?? ['ok' => 0, 'ej_ok' => 0, 'total' => 0, 'kassation_pct' => 0, 'datum' => $date];

            // 2. Beräkna om det är en avvikelse (jämför med senaste 30 dagars medel)
            $refFrom = date('Y-m-d', strtotime($date . ' -30 days'));
            $refTo   = date('Y-m-d', strtotime($date . ' -1 day'));
            $refData = $this->getDailyKassation($refFrom, $refTo);
            $refPcts = array_column($refData, 'kassation_pct');
            $refMean = count($refPcts) > 0 ? array_sum($refPcts) / count($refPcts) : 0;
            $refSd   = $this->stddev($refPcts);
            $sigma   = $refSd > 0 ? round(abs($dayInfo['kassation_pct'] - $refMean) / $refSd, 2) : 0;
            $isAvv   = $refSd > 0 && abs($dayInfo['kassation_pct'] - $refMean) > 2 * $refSd;

            // 3. Per-skift kassation
            $stmtSkift = $this->pdo->prepare("
                SELECT
                    s.skiftraknare,
                    s.ibc_ok,
                    COALESCE(s.ibc_ej_ok, 0) AS ibc_ej_ok,
                    COALESCE(s.drifttid, 0) AS drifttid,
                    s.op1, s.op2, s.op3,
                    o1.name AS op1_name,
                    o2.name AS op2_name,
                    o3.name AS op3_name
                FROM rebotling_skiftrapport s
                LEFT JOIN operators o1 ON o1.number = s.op1
                LEFT JOIN operators o2 ON o2.number = s.op2
                LEFT JOIN operators o3 ON o3.number = s.op3
                WHERE s.datum = :datum
                ORDER BY s.skiftraknare ASC
            ");
            $stmtSkift->execute([':datum' => $date]);
            $skiftRows = $stmtSkift->fetchAll(\PDO::FETCH_ASSOC);

            $skiftData = [];
            $operatorMap = []; // opNum => ['name' => X, 'ok' => Y, 'ej_ok' => Z]

            foreach ($skiftRows as $row) {
                $total = (int)$row['ibc_ok'] + (int)$row['ibc_ej_ok'];
                $kass  = $total > 0 ? round((int)$row['ibc_ej_ok'] / $total * 100, 2) : 0;

                $operators = [];
                foreach (['op1', 'op2', 'op3'] as $opCol) {
                    $opNum  = $row[$opCol] ?? null;
                    $opName = $row[$opCol . '_name'] ?? null;
                    if ($opNum) {
                        $operators[] = [
                            'id'   => (int)$opNum,
                            'name' => $opName ?? ('Operatör ' . $opNum),
                        ];
                        // Aggregera per operatör
                        if (!isset($operatorMap[$opNum])) {
                            $operatorMap[$opNum] = [
                                'id'    => (int)$opNum,
                                'name'  => $opName ?? ('Operatör ' . $opNum),
                                'ok'    => 0,
                                'ej_ok' => 0,
                            ];
                        }
                        $operatorMap[$opNum]['ok']    += (int)$row['ibc_ok'];
                        $operatorMap[$opNum]['ej_ok'] += (int)$row['ibc_ej_ok'];
                    }
                }

                $skiftData[] = [
                    'skiftraknare'  => (int)$row['skiftraknare'],
                    'ibc_ok'        => (int)$row['ibc_ok'],
                    'ibc_ej_ok'     => (int)$row['ibc_ej_ok'],
                    'total'         => $total,
                    'kassation_pct' => $kass,
                    'drifttid'      => (int)$row['drifttid'],
                    'operators'     => $operators,
                ];
            }

            // Beräkna kassation per operatör
            $operatorKassation = [];
            foreach ($operatorMap as $op) {
                $opTotal = $op['ok'] + $op['ej_ok'];
                $operatorKassation[] = [
                    'id'            => $op['id'],
                    'name'          => $op['name'],
                    'ok'            => $op['ok'],
                    'ej_ok'         => $op['ej_ok'],
                    'total'         => $opTotal,
                    'kassation_pct' => $opTotal > 0 ? round($op['ej_ok'] / $opTotal * 100, 2) : 0,
                ];
            }

            // 4. Stopporsaker den dagen
            $stopporsaker = $this->getStopReasons($date);

            $this->sendJson([
                'datum'              => $date,
                'kassation_pct'      => $dayInfo['kassation_pct'],
                'ok'                 => $dayInfo['ok'],
                'ej_ok'              => $dayInfo['ej_ok'],
                'total'              => $dayInfo['total'],
                'avvikelse'          => $isAvv,
                'avvikelse_sigma'    => $sigma,
                'ref_snitt'          => round($refMean, 2),
                'ref_stddev'         => round($refSd, 2),
                'skift'              => $skiftData,
                'per_operator'       => $operatorKassation,
                'stopporsaker'       => $stopporsaker,
            ]);
        } catch (\PDOException $e) {
            error_log('KvalitetsTrendbrottController::getDailyDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta daglig detalj', 500);
        }
    }

    /**
     * Hämta stopporsaker för en given dag.
     */
    private function getStopReasons(string $date): array {
        $reasons = [];

        // Försök stoppage_log
        try {
            $stmt = $this->pdo->prepare("
                SELECT orsak, COALESCE(SUM(duration_min), 0) AS total_min, COUNT(*) AS antal
                FROM stoppage_log
                WHERE DATE(start_time) = :datum
                GROUP BY orsak
                ORDER BY total_min DESC
            ");
            $stmt->execute([':datum' => $date]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $reasons[] = [
                    'orsak'     => $row['orsak'] ?? 'Okänd',
                    'antal'     => (int)$row['antal'],
                    'minuter'   => (int)$row['total_min'],
                    'källa'     => 'stoppage_log',
                ];
            }
        } catch (\PDOException $e) {
            // Tabellen kanske inte finns
        }

        // Försök stopporsak_registreringar
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(sk.namn, 'Okänd') AS orsak,
                        COUNT(*) AS antal,
                        COALESCE(SUM(sr.varaktighet_min), 0) AS total_min
                    FROM stopporsak_registreringar sr
                    LEFT JOIN stopporsak_kategorier sk ON sk.id = sr.kategori_id
                    WHERE DATE(sr.datum) = :datum
                    GROUP BY sk.namn
                    ORDER BY total_min DESC
                ");
                $stmt->execute([':datum' => $date]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $reasons[] = [
                        'orsak'     => $row['orsak'],
                        'antal'     => (int)$row['antal'],
                        'minuter'   => (int)$row['total_min'],
                        'källa'     => 'stopporsak_registreringar',
                    ];
                }
            }
        } catch (\PDOException $e) {
            // Tabellen kanske inte finns
        }

        return $reasons;
    }
}
