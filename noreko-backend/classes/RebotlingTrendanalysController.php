<?php
class RebotlingTrendanalysController {
    private $pdo;
    private const CYKELTID = 120; // sekunder per IBC

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $run = $_GET['run'] ?? '';
        switch ($run) {
            case 'trender':
                $this->trender();
                break;
            case 'daglig-historik':
                $this->dagligHistorik();
                break;
            case 'veckosammanfattning':
                $this->veckosammanfattning();
                break;
            case 'anomalier':
                $this->anomalier();
                break;
            case 'prognos':
                $this->prognos();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Okänt run-kommando']);
                return;
        }
    }

    // ============================================================
    // Hjälpfunktioner
    // ============================================================

    /**
     * Beräkna daglig OEE, produktion och kassationsgrad.
     * Returnerar array av ['datum', 'oee', 'produktion', 'kassation']
     */
    private function hamtaDagligData(int $dagar): array {
        $sql = "
            SELECT
                DATE(i.datum) AS datum,
                COUNT(*) AS total_ibc,
                SUM(CASE WHEN i.lopnummer = 0 OR i.lopnummer >= 998 THEN 0 ELSE 1 END) AS godkanda,
                SUM(CASE WHEN i.lopnummer = 0 OR i.lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade,
                MIN(i.datum) AS forsta_cykel,
                MAX(i.datum) AS sista_cykel
            FROM rebotling_ibc i
            WHERE DATE(i.datum) >= DATE_SUB(CURDATE(), INTERVAL :dagar DAY)
              AND DATE(i.datum) <= CURDATE()
            GROUP BY DATE(i.datum)
            ORDER BY datum ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dagar' => $dagar]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $datum    = $row['datum'];
            $total    = (int)$row['total_ibc'];
            $godkanda = (int)$row['godkanda'];

            // Drifttid = tid mellan första och sista cykel (sekunder)
            $drifttid = 0;
            if ($row['forsta_cykel'] && $row['sista_cykel']) {
                $drifttid = strtotime($row['sista_cykel']) - strtotime($row['forsta_cykel']);
                if ($drifttid < 0) $drifttid = 0;
            }

            // Planerad tid: ett skift = 8h = 28800s, max 3 skift = 86400s
            // Uppskatta antal skift från drifttid
            $planerad_tid = max($drifttid, 1);
            if ($drifttid < 28800) {
                $planerad_tid = 28800; // minst ett skift
            } elseif ($drifttid < 57600) {
                $planerad_tid = 57600; // 2 skift
            } else {
                $planerad_tid = 86400; // 3 skift
            }

            // OEE-komponenter
            $T = $drifttid > 0 ? min($drifttid / $planerad_tid, 1.0) : 0;
            $P = ($drifttid > 0 && $total > 0)
                ? min(($total * self::CYKELTID) / $drifttid, 1.0)
                : 0;
            $K = $total > 0 ? $godkanda / $total : 0;
            $oee = round($T * $P * $K * 100, 2);

            // Kassationsgrad i procent
            $kassation = $total > 0 ? round((1 - $K) * 100, 2) : 0;

            $result[] = [
                'datum'      => $datum,
                'oee'        => $oee,
                'produktion' => $total,
                'kassation'  => $kassation,
            ];
        }
        return $result;
    }

    /**
     * Linjär regression: returnerar ['slope', 'intercept', 'r2']
     * $values = numerisk array med värden (senaste $n dagar)
     */
    private function linjarRegression(array $values): array {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0, 'r2' => 0];
        }

        $sumX  = 0; $sumY  = 0;
        $sumXY = 0; $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX  += $i;
            $sumY  += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if ($denom == 0) {
            return ['slope' => 0, 'intercept' => $sumY / $n, 'r2' => 0];
        }
        $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // R²
        $meanY = $sumY / $n;
        $ssTot = 0; $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $i + $intercept;
            $ssTot += ($values[$i] - $meanY) ** 2;
            $ssRes += ($values[$i] - $predicted) ** 2;
        }
        $r2 = $ssTot > 0 ? round(1 - $ssRes / $ssTot, 4) : 0;

        return [
            'slope'     => round($slope, 4),
            'intercept' => round($intercept, 4),
            'r2'        => $r2,
        ];
    }

    /**
     * Beräkna glidande medelvärde
     */
    private function glidandeMedel(array $values, int $fonster): array {
        $result = [];
        $n = count($values);
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $fonster + 1);
            $slice = array_slice($values, $start, $i - $start + 1);
            $result[] = count($slice) > 0 ? array_sum($slice) / count($slice) : null;
        }
        return $result;
    }

    /**
     * Beräkna alertnivå baserat på slope (% per dag)
     */
    private function alertNiva(float $slope): string {
        if ($slope < -1.0) return 'critical';
        if ($slope < -0.5) return 'warning';
        return 'ok';
    }

    /**
     * Trend-riktning
     */
    private function trendRiktning(float $slope): string {
        if ($slope > 0.1) return 'up';
        if ($slope < -0.1) return 'down';
        return 'stable';
    }

    // ============================================================
    // run=trender
    // ============================================================

    private function trender(): void {
        $dagdata = $this->hamtaDagligData(30);

        if (empty($dagdata)) {
            echo json_encode([
                'success' => true,
                'data'    => [
                    'oee'        => $this->tomTrendKort('OEE'),
                    'produktion' => $this->tomTrendKort('Produktion'),
                    'kassation'  => $this->tomTrendKort('Kassation'),
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $oeevarden   = array_column($dagdata, 'oee');
        $prodvarden  = array_column($dagdata, 'produktion');
        $kassvarden  = array_column($dagdata, 'kassation');
        $n = count($dagdata);

        // Regressioner
        $oeeReg  = $this->linjarRegression($oeevarden);
        $prodReg = $this->linjarRegression($prodvarden);
        $kassReg = $this->linjarRegression($kassvarden);

        // Nuvarande värde (senaste dag)
        $oeeCurrent  = round(end($oeevarden), 2);
        $prodCurrent = (int)end($prodvarden);
        $kassCurrent = round(end($kassvarden), 2);

        // 7d medel
        $oee7d  = $n >= 7  ? round(array_sum(array_slice($oeevarden, -7))  / 7,  2) : round(array_sum($oeevarden)  / $n, 2);
        $prod7d = $n >= 7  ? round(array_sum(array_slice($prodvarden, -7)) / 7,  0) : round(array_sum($prodvarden) / $n, 0);
        $kass7d = $n >= 7  ? round(array_sum(array_slice($kassvarden, -7)) / 7,  2) : round(array_sum($kassvarden) / $n, 2);

        // 30d medel
        $oee30d  = round(array_sum($oeevarden)  / $n, 2);
        $prod30d = round(array_sum($prodvarden) / $n, 0);
        $kass30d = round(array_sum($kassvarden) / $n, 2);

        // Sparkline: senaste 14 dagar
        $sparkSlice = array_slice($dagdata, -14);

        echo json_encode([
            'success' => true,
            'data'    => [
                'oee' => [
                    'nuvarande'    => $oeeCurrent,
                    'slope'        => $oeeReg['slope'],
                    'r2'           => $oeeReg['r2'],
                    'medel_7d'     => $oee7d,
                    'medel_30d'    => $oee30d,
                    'trend'        => $this->trendRiktning($oeeReg['slope']),
                    'alert'        => $this->alertNiva($oeeReg['slope']),
                    'sparkline'    => array_map(fn($d) => ['datum' => $d['datum'], 'varde' => $d['oee']], $sparkSlice),
                ],
                'produktion' => [
                    'nuvarande'    => $prodCurrent,
                    'slope'        => $prodReg['slope'],
                    'r2'           => $prodReg['r2'],
                    'medel_7d'     => (int)$prod7d,
                    'medel_30d'    => (int)$prod30d,
                    'trend'        => $this->trendRiktning($prodReg['slope']),
                    'alert'        => $this->alertNiva($prodReg['slope']),
                    'sparkline'    => array_map(fn($d) => ['datum' => $d['datum'], 'varde' => $d['produktion']], $sparkSlice),
                ],
                'kassation' => [
                    'nuvarande'    => $kassCurrent,
                    'slope'        => $kassReg['slope'],
                    'r2'           => $kassReg['r2'],
                    'medel_7d'     => $kass7d,
                    'medel_30d'    => $kass30d,
                    'trend'        => $this->trendRiktning($kassReg['slope']),
                    // För kassation: stigning är negativt, fallande är bra
                    'alert'        => $this->alertNiva(-$kassReg['slope']),
                    'sparkline'    => array_map(fn($d) => ['datum' => $d['datum'], 'varde' => $d['kassation']], $sparkSlice),
                ],
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private function tomTrendKort(string $namn): array {
        return [
            'nuvarande' => 0, 'slope' => 0, 'r2' => 0,
            'medel_7d'  => 0, 'medel_30d' => 0,
            'trend'     => 'stable', 'alert' => 'ok', 'sparkline' => [],
        ];
    }

    // ============================================================
    // run=daglig-historik
    // ============================================================

    private function dagligHistorik(): void {
        $dagar = 90;
        $dagdata = $this->hamtaDagligData($dagar);

        if (empty($dagdata)) {
            echo json_encode(['success' => true, 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
            return;
        }

        $oeevarden  = array_column($dagdata, 'oee');
        $prodvarden = array_column($dagdata, 'produktion');
        $kassvarden = array_column($dagdata, 'kassation');

        $oeeMA7  = $this->glidandeMedel($oeevarden,  7);
        $prodMA7 = $this->glidandeMedel($prodvarden, 7);
        $kassMA7 = $this->glidandeMedel($kassvarden, 7);

        $enriched = [];
        foreach ($dagdata as $i => $row) {
            $enriched[] = [
                'datum'       => $row['datum'],
                'oee'         => $row['oee'],
                'produktion'  => $row['produktion'],
                'kassation'   => $row['kassation'],
                'oee_ma7'     => $oeeMA7[$i]  !== null ? round($oeeMA7[$i],  2) : null,
                'prod_ma7'    => $prodMA7[$i] !== null ? round($prodMA7[$i], 1) : null,
                'kass_ma7'    => $kassMA7[$i] !== null ? round($kassMA7[$i], 2) : null,
            ];
        }

        echo json_encode([
            'success'   => true,
            'data'      => $enriched,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    // ============================================================
    // run=veckosammanfattning
    // ============================================================

    private function veckosammanfattning(): void {
        $sql = "
            SELECT
                YEAR(datum)       AS ar,
                WEEK(datum, 1)    AS vecka,
                MIN(DATE(datum))  AS from_datum,
                MAX(DATE(datum))  AS to_datum,
                COUNT(*)          AS total_ibc,
                SUM(CASE WHEN lopnummer = 0 OR lopnummer >= 998 THEN 1 ELSE 0 END) AS kasserade,
                MIN(datum)        AS forsta_cykel,
                MAX(datum)        AS sista_cykel
            FROM rebotling_ibc
            WHERE DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 84 DAY)
            GROUP BY YEAR(datum), WEEK(datum, 1)
            ORDER BY ar DESC, vecka DESC
            LIMIT 12
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Beräkna OEE per vecka
        $veckor = [];
        foreach ($rows as $row) {
            $total    = (int)$row['total_ibc'];
            $kasserade = (int)$row['kasserade'];
            $godkanda = $total - $kasserade;

            $drifttid = strtotime($row['sista_cykel']) - strtotime($row['forsta_cykel']);
            if ($drifttid < 0) $drifttid = 0;

            // Estimera planerad tid per vecka (max 5 * 2 * 8h = 80h)
            $planerad_tid = max($drifttid, 1);
            if ($drifttid < 28800) {
                $planerad_tid = 28800;
            } elseif ($drifttid < 57600) {
                $planerad_tid = 57600;
            } else {
                $planerad_tid = 86400 * 5; // 5 dagar
            }

            $T = $drifttid > 0 ? min($drifttid / $planerad_tid, 1.0) : 0;
            $P = ($drifttid > 0 && $total > 0) ? min(($total * self::CYKELTID) / $drifttid, 1.0) : 0;
            $K = $total > 0 ? $godkanda / $total : 0;
            $oee = round($T * $P * $K * 100, 2);
            $kassation = $total > 0 ? round($kasserade / $total * 100, 2) : 0;

            $veckor[] = [
                'ar'         => (int)$row['ar'],
                'vecka'      => (int)$row['vecka'],
                'from_datum' => $row['from_datum'],
                'to_datum'   => $row['to_datum'],
                'produktion' => $total,
                'oee'        => $oee,
                'kassation'  => $kassation,
            ];
        }

        // Sortera kronologiskt (äldst först) för jämförelse
        $veckor = array_reverse($veckor);

        // Beräkna jämförelse med föregående vecka
        $basta_prod_idx  = 0; $samsta_prod_idx  = 0;
        $basta_oee_idx   = 0; $samsta_oee_idx   = 0;

        foreach ($veckor as $i => &$v) {
            $prev = $i > 0 ? $veckor[$i - 1] : null;
            $v['prod_diff_pct'] = $prev && $prev['produktion'] > 0
                ? round(($v['produktion'] - $prev['produktion']) / $prev['produktion'] * 100, 1)
                : null;
            $v['oee_diff_pct'] = $prev && $prev['oee'] > 0
                ? round(($v['oee'] - $prev['oee']) / $prev['oee'] * 100, 1)
                : null;
            $v['kass_diff_pct'] = $prev && $prev['kassation'] >= 0
                ? round($v['kassation'] - $prev['kassation'], 2)
                : null;

            if ($v['produktion'] > $veckor[$basta_prod_idx]['produktion']) $basta_prod_idx = $i;
            if ($v['produktion'] < $veckor[$samsta_prod_idx]['produktion']) $samsta_prod_idx = $i;
            if ($v['oee'] > $veckor[$basta_oee_idx]['oee']) $basta_oee_idx = $i;
            if ($v['oee'] < $veckor[$samsta_oee_idx]['oee']) $samsta_oee_idx = $i;
        }
        unset($v);

        foreach ($veckor as $i => &$v) {
            $v['basta_produktion'] = ($i === $basta_prod_idx);
            $v['samsta_produktion'] = ($i === $samsta_prod_idx);
            $v['basta_oee'] = ($i === $basta_oee_idx);
            $v['samsta_oee'] = ($i === $samsta_oee_idx);
        }
        unset($v);

        echo json_encode([
            'success'    => true,
            'data'       => array_reverse($veckor), // Nyast först för display
            'timestamp'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ============================================================
    // run=anomalier
    // ============================================================

    private function anomalier(): void {
        $dagdata = $this->hamtaDagligData(30);
        if (count($dagdata) < 4) {
            echo json_encode(['success' => true, 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
            return;
        }

        $anomalier = [];
        $typer = [
            ['nyckel' => 'oee',        'etikett' => 'OEE',        'enhet' => '%'],
            ['nyckel' => 'produktion',  'etikett' => 'Produktion', 'enhet' => 'IBC'],
            ['nyckel' => 'kassation',   'etikett' => 'Kassation',  'enhet' => '%'],
        ];

        foreach ($typer as $typ) {
            $varden = array_column($dagdata, $typ['nyckel']);
            $n = count($varden);
            $medel = array_sum($varden) / $n;

            $varians = 0;
            foreach ($varden as $v) {
                $varians += ($v - $medel) ** 2;
            }
            $stdav = $n > 1 ? sqrt($varians / ($n - 1)) : 0;

            if ($stdav < 0.001) continue;

            foreach ($dagdata as $row) {
                $varde = (float)$row[$typ['nyckel']];
                $avvikelse = $stdav > 0 ? ($varde - $medel) / $stdav : 0;

                if (abs($avvikelse) >= 2.0) {
                    $positivt = ($typ['nyckel'] === 'kassation')
                        ? $avvikelse < 0  // Låg kassation = bra
                        : $avvikelse > 0; // Hög OEE/produktion = bra

                    $anomalier[] = [
                        'datum'     => $row['datum'],
                        'typ'       => $typ['etikett'],
                        'nyckel'    => $typ['nyckel'],
                        'varde'     => round($varde, 2),
                        'medel'     => round($medel, 2),
                        'stdav'     => round($stdav, 2),
                        'avvikelse' => round($avvikelse, 2),
                        'enhet'     => $typ['enhet'],
                        'positivt'  => $positivt,
                    ];
                }
            }
        }

        // Sortera: nyast datum först, sedan efter avvikelsestorlek
        usort($anomalier, function($a, $b) {
            $datumCmp = strcmp($b['datum'], $a['datum']);
            if ($datumCmp !== 0) return $datumCmp;
            return abs($b['avvikelse']) <=> abs($a['avvikelse']);
        });

        echo json_encode([
            'success'   => true,
            'data'      => $anomalier,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    // ============================================================
    // run=prognos
    // ============================================================

    private function prognos(): void {
        $dagdata = $this->hamtaDagligData(14);
        if (count($dagdata) < 3) {
            echo json_encode([
                'success' => true,
                'data'    => ['oee' => null, 'produktion' => null, 'kassation' => null, 'dagar' => []],
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $oeevarden  = array_column($dagdata, 'oee');
        $prodvarden = array_column($dagdata, 'produktion');
        $kassvarden = array_column($dagdata, 'kassation');

        $oeeReg  = $this->linjarRegression($oeevarden);
        $prodReg = $this->linjarRegression($prodvarden);
        $kassReg = $this->linjarRegression($kassvarden);

        $n = count($dagdata);

        // Prognos för kommande 7 dagar
        $prognosDagar = [];
        for ($d = 1; $d <= 7; $d++) {
            $idx  = $n - 1 + $d;
            $datum = date('Y-m-d', strtotime('+' . $d . ' days'));

            $oeeP  = round($oeeReg['slope']  * $idx + $oeeReg['intercept'],  2);
            $prodP = round($prodReg['slope'] * $idx + $prodReg['intercept'], 1);
            $kassP = round($kassReg['slope'] * $idx + $kassReg['intercept'], 2);

            // Klämma till rimliga värden
            $oeeP  = max(0, min(100, $oeeP));
            $prodP = max(0, $prodP);
            $kassP = max(0, min(100, $kassP));

            $prognosDagar[] = [
                'datum'      => $datum,
                'oee'        => $oeeP,
                'produktion' => $prodP,
                'kassation'  => $kassP,
            ];
        }

        // Prognostiserat värde om 7 dagar
        $sista = end($prognosDagar);

        echo json_encode([
            'success' => true,
            'data'    => [
                'oee'        => $sista['oee'],
                'produktion' => $sista['produktion'],
                'kassation'  => $sista['kassation'],
                'dagar'      => $prognosDagar,
                'oee_slope'  => $oeeReg['slope'],
                'prod_slope' => $prodReg['slope'],
                'kass_slope' => $kassReg['slope'],
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
