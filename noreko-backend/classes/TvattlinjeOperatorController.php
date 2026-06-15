<?php
/**
 * TvattlinjeOperatorController.php
 * Operatörsranking och prestandastatistik för tvättlinjen.
 *
 * Tabellstruktur (tvattlinje_skiftrapport):
 *   id, datum, antal_ok, antal_ej_ok, totalt, op1, op2, op3,
 *   omtvaatt, drifttid, rasttime, lopnummer, skiftraknare, product_id
 *
 * Skillnad mot rebotling: totalt = direkt antal per rad (inget löpande räkneverk).
 * IBC-delning: om op1=2, op2=5, op3=null → varje aktiv operatör får totalt/2 st.
 *
 * Endpoints via ?action=tvattlinje-operator&run=XXX:
 *   run=ranking         — fullständig rankinglista, sorterad på total_ibc DESC
 *   run=sammanfattning  — KPI-kort: total IBC, aktiva op, snitt ibc/h, bästa op
 *   run=topplista       — top 3 (podium-visning)
 *   run=poangfordelning — IBC per operatör (chart-data)
 *
 * Perioder: ?period=idag|vecka|manad|30d
 */
class TvattlinjeOperatorController {
    private $pdo;

    /** Cache TTL i sekunder */
    private const CACHE_TTL = 30;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    // ================================================================
    // ENTRY POINT
    // ================================================================

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
            case 'ranking':         $this->ranking();         break;
            case 'sammanfattning':  $this->sammanfattning();  break;
            case 'topplista':       $this->topplista();       break;
            case 'poangfordelning': $this->poangfordelning(); break;
            case 'mvp':             $this->mvp();             break;
            default:
                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
                break;
        }
    }

    // ================================================================
    // ENDPOINTS
    // ================================================================

    /**
     * Fullständig rankinglista med alla KPI:er per operatör.
     */
    private function ranking(): void {
        [$from, $to, $period] = $this->getDateRange();

        $cacheKey = "tvatt_ranking_{$period}_{$from}_{$to}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->getRankingRows($from, $to);

        $result = [
            'success' => true,
            'data'    => $rows,
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
        ];
        $this->cacheSet($cacheKey, $result);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * KPI-kort: total IBC, aktiva operatörer, snitt IBC/h, bästa operatör.
     */
    private function sammanfattning(): void {
        [$from, $to, $period] = $this->getDateRange();

        $cacheKey = "tvatt_sammanfattning_{$period}_{$from}_{$to}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->getRankingRows($from, $to);

        $totalIbc       = array_sum(array_column($rows, 'total_ibc'));
        $aktivaOp       = count($rows);
        $ibcPerHValues  = array_filter(array_column($rows, 'ibc_per_h'), fn($v) => $v > 0);
        $snittIbcH      = $aktivaOp > 0 && count($ibcPerHValues) > 0
            ? round(array_sum($ibcPerHValues) / count($ibcPerHValues), 1)
            : 0;
        $poangValues    = array_column($rows, 'total_poang');
        $snittPoang     = $aktivaOp > 0 ? round(array_sum($poangValues) / $aktivaOp, 1) : 0;
        $hogstaPoang    = !empty($poangValues) ? max($poangValues) : 0;
        // Rows already sorted by total_poang DESC
        $bastaOp = !empty($rows) ? $rows[0] : null;

        $result = [
            'success' => true,
            'data'    => [
                'total_ibc'           => $totalIbc,
                'aktiva_operatorer'   => $aktivaOp,
                'snitt_ibc_per_h'     => $snittIbcH,
                'snitt_poang'         => $snittPoang,
                'hogsta_poang'        => $hogstaPoang,
                'basta_operator'      => $bastaOp
                    ? [
                        'namn'        => $bastaOp['operator_namn'],
                        'ibc_per_h'   => $bastaOp['ibc_per_h'],
                        'total_poang' => $bastaOp['total_poang'],
                    ]
                    : null,
            ],
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
        ];
        $this->cacheSet($cacheKey, $result);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Top 3 operatörer för podium-visning.
     */
    private function topplista(): void {
        [$from, $to, $period] = $this->getDateRange();

        $cacheKey = "tvatt_topplista_{$period}_{$from}_{$to}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = array_slice($this->getRankingRows($from, $to), 0, 3);

        $result = [
            'success' => true,
            'data'    => $rows,
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
        ];
        $this->cacheSet($cacheKey, $result);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * IBC per operatör som chart-data.
     */
    private function poangfordelning(): void {
        [$from, $to, $period] = $this->getDateRange();

        $cacheKey = "tvatt_poang_{$period}_{$from}_{$to}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->getRankingRows($from, $to);

        $chartData = [];
        foreach ($rows as $op) {
            $chartData[] = [
                'operator_namn'     => $op['operator_namn'],
                'produktions_poang' => $op['produktions_poang'],
                'kvalitets_bonus'   => $op['kvalitets_bonus'],
                'tempo_bonus'       => $op['tempo_bonus'],
                'stopp_bonus'       => $op['stopp_bonus'],
                'streak_bonus'      => $op['streak_bonus'] ?? 0,
                'total_poang'       => $op['total_poang'],
            ];
        }

        $result = [
            'success' => true,
            'data'    => [
                'chart_data' => $chartData,
                'period'     => $period,
                'from_date'  => $from,
                'to_date'    => $to,
            ],
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
        ];
        $this->cacheSet($cacheKey, $result);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * MVP för perioden — bästa operatör enligt total_poang.
     * Returnerar samma struktur som OperatorRankingController::mvp() — data.mvp.
     */
    private function mvp(): void {
        [$from, $to, $period] = $this->getDateRange();

        $cacheKey = "tvatt_mvp_{$period}_{$from}_{$to}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->getRankingRows($from, $to);
        $mvp  = !empty($rows) ? $rows[0] : null;

        // Mappa op_id → user_id för att matcha MvpData/OperatorRank-interfacet i frontend
        if ($mvp !== null) {
            $mvp['user_id'] = $mvp['op_id'];
        }

        $result = [
            'success' => true,
            'data'    => [
                'mvp'       => $mvp,
                'typ'       => $period,
                'from_date' => $from,
                'to_date'   => $to,
            ],
            'period'  => $period,
            'from'    => $from,
            'to'      => $to,
        ];
        $this->cacheSet($cacheKey, $result);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    // ================================================================
    // CORE DATA LOGIC
    // ================================================================

    /**
     * Hämtar och beräknar ranking-rader för perioden.
     *
     * Logik:
     *  - Varje rad i tvattlinje_skiftrapport = ett separat skift (totalt är direkt antal).
     *  - Räkna aktiva operatörer per skift (op1/op2/op3 IS NOT NULL AND > 0).
     *  - Dela totalt jämnt mellan aktiva operatörer (IBC-delning).
     *  - Summera drifttid (i sekunder) per operatör för IBC/h-beräkning.
     *    drifttid i tabellen kan vara minuter eller sekunder — kontrollera skala via
     *    typisk drifttid: vi antar minuter (som tvattlinje_skiftrapport brukar vara).
     *
     * @return array  Sorterad array med operatörsdata, bäst först.
     */
    private function getRankingRows(string $from, string $to): array {
        if (!$this->tableExists('tvattlinje_skiftrapport')) {
            return [];
        }

        try {
            // Hämta alla skift för perioden
            $sql = "
                SELECT
                    id, datum, totalt, antal_ok, antal_ej_ok,
                    op1, op2, op3,
                    drifttid, driftstopptime, rasttime, skiftraknare
                FROM tvattlinje_skiftrapport
                WHERE datum >= :from AND datum <= :to
                  AND totalt > 0
                ORDER BY datum ASC, skiftraknare ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from, ':to' => $to]);
            $skift = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('TvattlinjeOperatorController::getRankingRows query: ' . $e->getMessage());
            return [];
        }

        // Samla data per operatörs-ID (PLC-nummer)
        $opData = [];

        foreach ($skift as $s) {
            $aktiva = [];
            foreach (['op1', 'op2', 'op3'] as $opField) {
                $opId = (int)($s[$opField] ?? 0);
                if ($opId > 0) {
                    $aktiva[] = $opId;
                }
            }
            if (empty($aktiva)) {
                continue;
            }

            $antalAktiva  = count($aktiva);
            $totalt       = (float)($s['totalt'] ?? 0);
            $antalOk      = (float)($s['antal_ok'] ?? 0);
            $ibcPerOp     = $antalAktiva > 0 ? $totalt / $antalAktiva : 0;
            $okPerOp      = $antalAktiva > 0 ? $antalOk / $antalAktiva : 0;
            $stoppMin     = (float)($s['driftstopptime'] ?? 0);
            $hadStopp     = $stoppMin > 0 ? 1 : 0;

            // drifttid antas vara i minuter (standard för tvattlinje)
            $drifttidMin = (float)($s['drifttid'] ?? 0);
            $rastMin     = (float)($s['rasttime'] ?? 0);
            $nettoMin    = max(0, $drifttidMin - $rastMin);
            $nettotimMin = $nettoMin / $antalAktiva; // tid per operatör

            foreach ($aktiva as $opId) {
                if (!isset($opData[$opId])) {
                    $opData[$opId] = [
                        'op_id'         => $opId,
                        'total_ibc'     => 0.0,
                        'ok_ibc'        => 0.0,
                        'skift_count'   => 0,
                        'total_min'     => 0.0,
                        'stopp_min'     => 0.0,
                        'antal_stopp'   => 0,
                    ];
                }
                $opData[$opId]['total_ibc']   += $ibcPerOp;
                $opData[$opId]['ok_ibc']      += $okPerOp;
                $opData[$opId]['skift_count'] += 1;
                $opData[$opId]['total_min']   += $nettotimMin;
                $opData[$opId]['stopp_min']   += $stoppMin;
                $opData[$opId]['antal_stopp'] += $hadStopp;
            }
        }

        if (empty($opData)) {
            return [];
        }

        // Slå upp operatörsnamn från operators-tabellen
        $opIds = array_keys($opData);
        $placeholders = implode(',', array_fill(0, count($opIds), '?'));
        $namn = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT number, name FROM operators WHERE number IN ($placeholders)"
            );
            $stmt->execute($opIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $namn[(int)$row['number']] = $row['name'];
            }
        } catch (\PDOException $e) {
            error_log('TvattlinjeOperatorController::getRankingRows namn-lookup: ' . $e->getMessage());
        }

        // Beräkna snitt IBC/h för tempobonus
        $totalIbcAll = 0.0;
        $totalTimmAll = 0.0;
        foreach ($opData as $d) {
            $totalIbcAll  += $d['total_ibc'];
            $totalTimmAll += $d['total_min'] / 60.0;
        }
        $avgIbcPerH = $totalTimmAll > 0 ? ($totalIbcAll / $totalTimmAll) : 1.0;

        // Bygg slutlig lista med poäng/bonus (samma formel som Rebotling)
        $result = [];
        foreach ($opData as $opId => $d) {
            $totalIbc       = (float)$d['total_ibc'];
            $okIbc          = (float)$d['ok_ibc'];
            $skiftCount     = $d['skift_count'];
            $avgIbcPerSkift = $skiftCount > 0 ? round($totalIbc / $skiftCount, 1) : 0;
            $totalTimmar    = $d['total_min'] / 60.0;
            $ibcPerH        = $totalTimmar > 0 ? round($totalIbc / $totalTimmar, 1) : 0.0;
            $antalStopp     = $d['antal_stopp'];
            $stoppMin       = $d['stopp_min'];
            $stoppSek       = $stoppMin * 60;
            $skiftSek       = $skiftCount * 8 * 3600; // 8h per skift

            // Produktionspoäng: 10 per IBC
            $produktionsPoang = $totalIbc * 10;

            // Kvalitetsbonus: (% godkända - 90) × 5, max 50
            $okPct        = $totalIbc > 0 ? ($okIbc / $totalIbc * 100) : 0;
            $kvalBonus    = max(0.0, min(50.0, ($okPct - 90) * 5));

            // Tempobonus: om IBC/h > snitt
            $tempoBonus = 0.0;
            if ($ibcPerH > $avgIbcPerH && $avgIbcPerH > 0 && $totalIbc >= 10 && $totalTimmar >= 1.0) {
                $tempoBonus = round(($ibcPerH - $avgIbcPerH) * 20, 1);
            }

            // Stoppbonus
            $stoppBonus = 0;
            if ($antalStopp === 0 && $totalIbc > 0) {
                $stoppBonus = 50;
            } elseif ($skiftSek > 0 && ($stoppSek / $skiftSek) < 0.10) {
                $stoppBonus = 30;
            }

            $totalBonus  = round($kvalBonus + $tempoBonus + $stoppBonus, 1);
            $totalPoang  = round($produktionsPoang + $totalBonus, 1);

            $result[] = [
                'op_id'             => $opId,
                'operator_namn'     => $namn[$opId] ?? ('Operator ' . $opId),
                'total_ibc'         => (int)round($totalIbc),
                'ok_ibc'            => (int)round($okIbc),
                'ok_pct'            => round($okPct, 1),
                'skift_count'       => $skiftCount,
                'avg_ibc_per_skift' => $avgIbcPerSkift,
                'ibc_per_h'         => $ibcPerH,
                'produktions_poang' => (int)round($produktionsPoang),
                'kvalitets_bonus'   => round($kvalBonus, 1),
                'tempo_bonus'       => round($tempoBonus, 1),
                'stopp_bonus'       => $stoppBonus,
                'total_bonus'       => $totalBonus,
                'total_poang'       => $totalPoang,
                'antal_stopp'       => $antalStopp,
                'streak'            => 0,
            ];
        }

        // Sortera på total_poang DESC (samma som Rebotling)
        usort($result, fn($a, $b) => $b['total_poang'] <=> $a['total_poang']);

        // Beräkna streaks (dagar i rad med produktion över snitt)
        $this->calcStreaks($result);

        return $result;
    }

    /**
     * Streak: dagar i rad där operatörens dagliga IBC × 10 >= genomsnittsgräns.
     * Anpassad för tvattlinje_skiftrapport (direkt antal per rad, ingen LAG).
     */
    private function calcStreaks(array &$ranking): void {
        if (empty($ranking)) return;

        $avgPoang = array_sum(array_column($ranking, 'total_poang')) / count($ranking);
        $dagGrans = max(1, $avgPoang / 30);

        $opIds = array_column($ranking, 'op_id');
        if (empty($opIds)) return;

        $placeholders = implode(',', array_fill(0, count($opIds), '?'));
        $allDagData = [];

        try {
            // Hämta daglig IBC per operatör senaste 30 dagar (delat mellan aktiva)
            $sql = "
                SELECT
                    op_col AS op_id,
                    DATE(datum) AS dag,
                    SUM(totalt / GREATEST(1,
                        (CASE WHEN op1 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op2 > 0 THEN 1 ELSE 0 END +
                         CASE WHEN op3 > 0 THEN 1 ELSE 0 END)
                    )) AS ibc_count
                FROM (
                    SELECT datum, totalt, op1, op2, op3, op1 AS op_col
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      AND totalt > 0 AND op1 IN ($placeholders)
                    UNION ALL
                    SELECT datum, totalt, op1, op2, op3, op2 AS op_col
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      AND totalt > 0 AND op2 IN ($placeholders)
                    UNION ALL
                    SELECT datum, totalt, op1, op2, op3, op3 AS op_col
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      AND totalt > 0 AND op3 IN ($placeholders)
                ) sub
                GROUP BY op_col, DATE(datum)
                ORDER BY op_col, dag DESC
            ";
            $params = array_merge($opIds, $opIds, $opIds);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $allDagData[(int)$row['op_id']][] = [
                    'dag'       => $row['dag'],
                    'ibc_count' => (float)$row['ibc_count'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('TvattlinjeOperatorController::calcStreaks: ' . $e->getMessage());
            return;
        }

        foreach ($ranking as &$op) {
            $streak  = 0;
            $dagData = $allDagData[$op['op_id']] ?? [];

            foreach ($dagData as $d) {
                if (($d['ibc_count'] * 10) >= $dagGrans) {
                    $streak++;
                } else {
                    break;
                }
            }

            $streakBonus          = $streak * 5;
            $op['streak']         = $streak;
            $op['streak_bonus']   = $streakBonus;
            $op['total_poang']    = round($op['total_poang'] + $streakBonus, 1);
        }
        unset($op);

        // Re-sortera efter uppdaterade poäng
        usort($ranking, fn($a, $b) => $b['total_poang'] <=> $a['total_poang']);
        foreach ($ranking as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        unset($r);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Returnerar [from, to, period] baserat på ?period=idag|vecka|manad|30d.
     */
    private function getDateRange(): array {
        $period = trim($_GET['period'] ?? '30d');
        $today  = date('Y-m-d');

        switch ($period) {
            case 'idag':
                return [$today, $today, 'idag'];
            case 'vecka':
                // Beräkna senaste måndag (undviker söndagsbugg)
                $dagIVecka = (int)date('N'); // 1=mån, 7=sön
                $monday    = date('Y-m-d', strtotime('-' . ($dagIVecka - 1) . ' days'));
                return [$monday, $today, 'vecka'];
            case 'manad':
                return [date('Y-m-01'), $today, 'manad'];
            case '30d':
            default:
                return [date('Y-m-d', strtotime('-29 days')), $today, '30d'];
        }
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log('TvattlinjeOperatorController::tableExists: ' . $e->getMessage());
            return false;
        }
    }

    private function cacheGet(string $key): ?array {
        $cacheDir = dirname(__DIR__) . '/cache';
        $file     = $cacheDir . '/tvatt_op_' . md5($key) . '.json';
        if (file_exists($file) && (time() - filemtime($file)) < self::CACHE_TTL) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        return null;
    }

    private function cacheSet(string $key, array $data): void {
        $cacheDir = dirname(__DIR__) . '/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $file = $cacheDir . '/tvatt_op_' . md5($key) . '.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => false,
            'error'     => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }
}
