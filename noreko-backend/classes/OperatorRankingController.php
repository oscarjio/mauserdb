<?php
/**
 * OperatorRankingController.php
 * Gamifierad operatorsranking med poang, bonus och motiverande element.
 *
 * Poangsystem:
 *   Produktionspoang: 10 poang per producerad IBC
 *   Kvalitetsbonus:   (procent godkanda - 90) x 5 poang (max 50 bonus)
 *   Tempo-bonus:      Om IBC/h > snitt -> (IBC/h - snitt) x 20 poang
 *   Stopp-bonus:      Stopptid < 10% av skiftet -> 30p. 0 stopp -> 50p
 *   Streak:           +5 poang per dag i rad med poang over snittet
 *
 * Endpoints via ?action=operator-ranking&run=XXX:
 *   run=sammanfattning  — KPI-kort data
 *   run=ranking         — fullstandig rankinglista med alla poangkategorier
 *   run=topplista       — top 3 for podium
 *   run=poangfordelning — chart-data per operator
 *   run=historik        — poang per operator per dag (top 5)
 *   run=mvp             — veckans/manadens MVP
 *
 * Tabeller: rebotling_ibc, rebotling_onoff, rebotling_data, skift_log,
 *           stopporsak_registreringar, users
 */
class OperatorRankingController {
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
            case 'sammanfattning':  $this->sammanfattning();  break;
            case 'ranking':         $this->ranking();         break;
            case 'topplista':       $this->topplista();       break;
            case 'poangfordelning': $this->poangfordelning(); break;
            case 'historik':        $this->historik();        break;
            case 'mvp':             $this->mvp();             break;
            default:                $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

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
     * Hamta datumintervall baserat pa period-parameter.
     * period: idag | vecka | manad | 30d (default)
     */
    private function getDateRange(): array {
        $period = trim($_GET['period'] ?? '30d');
        $today = date('Y-m-d');

        switch ($period) {
            case 'idag':
                return [$today, $today, $period];
            case 'vecka':
                return [date('Y-m-d', strtotime('monday this week')), $today, $period];
            case 'manad':
                return [date('Y-m-01'), $today, $period];
            case '30d':
            default:
                return [date('Y-m-d', strtotime('-29 days')), $today, '30d'];
        }
    }

    /**
     * Hamta IBC-data per operator for en period.
     * Returnerar array indexed by user_id med total_ibc, ok_ibc, timmar, operator_namn.
     */
    private function getOperatorIbcData(string $from, string $to): array {
        $operators = [];

        // rebotling_ibc uses op1/op2/op3 (not user_id) and ibc_ok/ibc_ej_ok (not ok)
        try {
            $sql = "
                SELECT op_id,
                       COALESCE(o.name, CONCAT('Operator ', op_id)) AS operator_namn,
                       SUM(cnt) AS total_ibc,
                       MIN(first_dt) AS first_ibc,
                       MAX(last_dt) AS last_ibc
                FROM (
                    SELECT op1 AS op_id, COUNT(*) AS cnt, MIN(datum) AS first_dt, MAX(datum) AS last_dt
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from1 AND :to1 AND op1 IS NOT NULL AND op1 > 0
                    GROUP BY op1
                    UNION ALL
                    SELECT op2 AS op_id, COUNT(*) AS cnt, MIN(datum), MAX(datum)
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from2 AND :to2 AND op2 IS NOT NULL AND op2 > 0
                    GROUP BY op2
                    UNION ALL
                    SELECT op3 AS op_id, COUNT(*) AS cnt, MIN(datum), MAX(datum)
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from3 AND :to3 AND op3 IS NOT NULL AND op3 > 0
                    GROUP BY op3
                ) AS sub
                LEFT JOIN operators o ON o.number = sub.op_id
                GROUP BY op_id, o.name
                ORDER BY total_ibc DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $from, ':to1' => $to,
                ':from2' => $from, ':to2' => $to,
                ':from3' => $from, ':to3' => $to,
            ]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid = (int)$row['op_id'];
                $operators[$uid] = [
                    'user_id'       => $uid,
                    'operator_namn' => $row['operator_namn'],
                    'total_ibc'     => (int)$row['total_ibc'],
                    'ok_ibc'        => (int)$row['total_ibc'], // each row = 1 IBC cycle
                    'first_ibc'     => $row['first_ibc'],
                    'last_ibc'      => $row['last_ibc'],
                ];
            }
        } catch (\PDOException) {
            // op columns might not exist
        }

        // Fallback: rebotling_data
        if (empty($operators) && $this->tableExists('rebotling_data')) {
            try {
                $sql = "
                    SELECT
                        r.user_id,
                        COALESCE(u.username, CONCAT('Operator ', r.user_id)) AS operator_namn,
                        SUM(COALESCE(r.antal, 1)) AS total_ibc,
                        SUM(COALESCE(r.antal, 1)) AS ok_ibc,
                        MIN(r.datum) AS first_ibc,
                        MAX(r.datum) AS last_ibc
                    FROM rebotling_data r
                    LEFT JOIN users u ON r.user_id = u.id
                    WHERE DATE(r.datum) BETWEEN :from AND :to
                      AND r.user_id IS NOT NULL
                      AND r.user_id > 0
                    GROUP BY r.user_id, u.username
                    ORDER BY total_ibc DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $from, ':to' => $to]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $uid = (int)$row['user_id'];
                    $operators[$uid] = [
                        'user_id'       => $uid,
                        'operator_namn' => $row['operator_namn'],
                        'total_ibc'     => (int)$row['total_ibc'],
                        'ok_ibc'        => (int)$row['ok_ibc'],
                        'first_ibc'     => $row['first_ibc'],
                        'last_ibc'      => $row['last_ibc'],
                    ];
                }
            } catch (\PDOException) {
                // tabellen saknar nagra kolumner
            }
        }

        return $operators;
    }

    /**
     * Hamta stopptid per operator for perioden.
     * Returnerar array indexed by user_id med total_stopp_sek.
     */
    private function getOperatorStopptid(string $from, string $to): array {
        $result = [];

        if (!$this->tableExists('stopporsak_registreringar')) {
            return $result;
        }

        try {
            // sr.user_id = users.id, men ranking indexeras pa operators.number (= users.operator_id).
            // Vi joinar med users for att mappa user_id -> operator_id (= operators.number).
            $sql = "
                SELECT
                    u.operator_id AS op_number,
                    SUM(
                        TIMESTAMPDIFF(SECOND,
                            sr.start_time,
                            COALESCE(sr.end_time, NOW())
                        )
                    ) AS total_stopp_sek,
                    COUNT(*) AS antal_stopp
                FROM stopporsak_registreringar sr
                JOIN users u ON sr.user_id = u.id
                WHERE sr.start_time >= :from
                  AND sr.start_time <= :to
                  AND u.operator_id IS NOT NULL
                  AND u.operator_id > 0
                GROUP BY u.operator_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid = (int)$row['op_number'];
                $result[$uid] = [
                    'total_stopp_sek' => max(0, (int)$row['total_stopp_sek']),
                    'antal_stopp'     => (int)$row['antal_stopp'],
                ];
            }
        } catch (\PDOException) {
            // Ignorera
        }

        return $result;
    }

    /**
     * Berakna drifttimmar for en operator under perioden.
     * Estimeras fran forsta till sista IBC, men max 8h/dag.
     */
    private function estimateArbetsTimmar(array $opData, string $from, string $to): float {
        $dagCount = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
        // Anta 8 timmars skift. Om vi har IBC-data, estimera antal aktiva dagar.
        $ibcPerDag = $opData['total_ibc'] / $dagCount;
        // Om operatoren producerar IBC antas hen vara aktiv. Begr ca antal dagar.
        $aktivaDagar = min($dagCount, max(1, ceil($opData['total_ibc'] / max(1, $ibcPerDag))));
        return $aktivaDagar * 8.0;
    }

    /**
     * Berakna ranking-poang for alla operatorer.
     */
    private function calcRanking(string $from, string $to): array {
        $operators = $this->getOperatorIbcData($from, $to);
        $stoppData = $this->getOperatorStopptid($from, $to);

        if (empty($operators)) {
            return [];
        }

        $dagCount = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
        $skiftSek = $dagCount * 8 * 3600; // 8h per dag

        // Berakna snitt IBC/h for alla
        $totalIbcAll = 0;
        $totalTimmarAll = 0;
        foreach ($operators as $op) {
            $totalIbcAll += $op['total_ibc'];
            $totalTimmarAll += $this->estimateArbetsTimmar($op, $from, $to);
        }
        $avgIbcPerH = $totalTimmarAll > 0 ? ($totalIbcAll / $totalTimmarAll) : 1;

        // Berakna poang for varje operator
        $result = [];
        foreach ($operators as $uid => $op) {
            $totalIbc = $op['total_ibc'];
            $okIbc = $op['ok_ibc'];
            $timmar = $this->estimateArbetsTimmar($op, $from, $to);

            // Produktionspoang: 10 per IBC
            $produktionsPoang = $totalIbc * 10;

            // Kvalitetsbonus: (% godkanda - 90) x 5, max 50
            $okPct = $totalIbc > 0 ? ($okIbc / $totalIbc * 100) : 0;
            $kvalitetsBonus = max(0, min(50, ($okPct - 90) * 5));

            // Tempo-bonus: Om IBC/h > snitt
            $ibcPerH = $timmar > 0 ? ($totalIbc / $timmar) : 0;
            $tempoBonus = 0;
            if ($ibcPerH > $avgIbcPerH && $avgIbcPerH > 0) {
                $tempoBonus = round(($ibcPerH - $avgIbcPerH) * 20, 1);
            }

            // Stopp-bonus
            $stopp = $stoppData[$uid] ?? null;
            $stopptidSek = $stopp ? $stopp['total_stopp_sek'] : 0;
            $antalStopp = $stopp ? $stopp['antal_stopp'] : 0;
            $stoppBonus = 0;
            if ($antalStopp === 0 && $totalIbc > 0) {
                $stoppBonus = 50;
            } elseif ($skiftSek > 0 && ($stopptidSek / $skiftSek) < 0.10) {
                $stoppBonus = 30;
            }

            $totalBonus = round($kvalitetsBonus + $tempoBonus + $stoppBonus, 1);
            $totalPoang = round($produktionsPoang + $totalBonus, 1);

            $result[] = [
                'user_id'            => $uid,
                'operator_namn'      => $op['operator_namn'],
                'total_ibc'          => $totalIbc,
                'ok_ibc'             => $okIbc,
                'ok_pct'             => round($okPct, 1),
                'ibc_per_h'          => round($ibcPerH, 2),
                'produktions_poang'  => $produktionsPoang,
                'kvalitets_bonus'    => round($kvalitetsBonus, 1),
                'tempo_bonus'        => round($tempoBonus, 1),
                'stopp_bonus'        => $stoppBonus,
                'total_bonus'        => $totalBonus,
                'total_poang'        => $totalPoang,
                'antal_stopp'        => $antalStopp,
                'stopptid_sek'       => $stopptidSek,
                'streak'             => 0, // beraknas separat
            ];
        }

        // Sortera efter total poang
        usort($result, fn($a, $b) => $b['total_poang'] <=> $a['total_poang']);

        // Lagg till rank
        foreach ($result as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        unset($r);

        // Berakna streaks
        $this->calcStreaks($result);

        return $result;
    }

    /**
     * Berakna streak (dagar i rad med poang over snittet) for operatorer.
     */
    private function calcStreaks(array &$ranking): void {
        if (empty($ranking)) return;

        $avgPoang = array_sum(array_column($ranking, 'total_poang')) / count($ranking);

        foreach ($ranking as &$op) {
            // Hamta daglig IBC for denna operator senaste 30 dagar
            // Operatorer identifieras via op1/op2/op3-kolumner, inte user_id
            $streak = 0;
            try {
                $sql = "
                    SELECT dag, SUM(cnt) AS ibc_count
                    FROM (
                        SELECT DATE(datum) AS dag, COUNT(*) AS cnt
                        FROM rebotling_ibc
                        WHERE op1 = :uid1 AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(datum)
                        UNION ALL
                        SELECT DATE(datum) AS dag, COUNT(*) AS cnt
                        FROM rebotling_ibc
                        WHERE op2 = :uid2 AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(datum)
                        UNION ALL
                        SELECT DATE(datum) AS dag, COUNT(*) AS cnt
                        FROM rebotling_ibc
                        WHERE op3 = :uid3 AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(datum)
                    ) AS combined
                    GROUP BY dag
                    ORDER BY dag DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':uid1' => $op['user_id'], ':uid2' => $op['user_id'], ':uid3' => $op['user_id']]);
                $dagData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Daglig poang-grans = snittet / antal dagar normaliserat
                $dagGrans = max(1, $avgPoang / 30);

                foreach ($dagData as $d) {
                    $dagPoang = (int)$d['ibc_count'] * 10;
                    if ($dagPoang >= $dagGrans) {
                        $streak++;
                    } else {
                        break;
                    }
                }
            } catch (\PDOException) {
                // Ignorera
            }

            $op['streak'] = $streak;
            $op['streak_bonus'] = $streak * 5;
            $op['total_poang'] += $streak * 5;
        }
        unset($op);

        // Re-sortera efter uppdaterade poang
        usort($ranking, fn($a, $b) => $b['total_poang'] <=> $a['total_poang']);
        foreach ($ranking as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        unset($r);
    }

    // ================================================================
    // run=sammanfattning
    // ================================================================

    private function sammanfattning(): void {
        try {
            [$from, $to, $period] = $this->getDateRange();
            $ranking = $this->calcRanking($from, $to);

            $totalIbc = array_sum(array_column($ranking, 'total_ibc'));
            $hogstaPoang = !empty($ranking) ? $ranking[0]['total_poang'] : 0;
            $antalOperatorer = count($ranking);
            $avgPoang = $antalOperatorer > 0 ? round(array_sum(array_column($ranking, 'total_poang')) / $antalOperatorer, 1) : 0;

            $this->sendSuccess([
                'total_ibc'        => $totalIbc,
                'hogsta_poang'     => $hogstaPoang,
                'antal_operatorer' => $antalOperatorer,
                'avg_poang'        => $avgPoang,
                'period'           => $period,
                'from_date'        => $from,
                'to_date'          => $to,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorRankingController::sammanfattning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=ranking
    // ================================================================

    private function ranking(): void {
        try {
            [$from, $to, $period] = $this->getDateRange();
            $ranking = $this->calcRanking($from, $to);

            $this->sendSuccess([
                'ranking'   => $ranking,
                'period'    => $period,
                'from_date' => $from,
                'to_date'   => $to,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorRankingController::ranking: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta ranking', 500);
        }
    }

    // ================================================================
    // run=topplista
    // ================================================================

    private function topplista(): void {
        try {
            [$from, $to, $period] = $this->getDateRange();
            $ranking = $this->calcRanking($from, $to);
            $top3 = array_slice($ranking, 0, 3);

            $this->sendSuccess([
                'topplista' => $top3,
                'period'    => $period,
                'from_date' => $from,
                'to_date'   => $to,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorRankingController::topplista: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta topplista', 500);
        }
    }

    // ================================================================
    // run=poangfordelning
    // ================================================================

    private function poangfordelning(): void {
        try {
            [$from, $to, $period] = $this->getDateRange();
            $ranking = $this->calcRanking($from, $to);

            $chartData = [];
            foreach ($ranking as $op) {
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

            $this->sendSuccess([
                'chart_data' => $chartData,
                'period'     => $period,
                'from_date'  => $from,
                'to_date'    => $to,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorRankingController::poangfordelning: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta poangfordelning', 500);
        }
    }

    // ================================================================
    // run=historik
    // ================================================================

    private function historik(): void {
        try {
            // Hamta top 5 operatorer fran 30d
            $from30 = date('Y-m-d', strtotime('-29 days'));
            $to = date('Y-m-d');
            $ranking = $this->calcRanking($from30, $to);
            $top5 = array_slice($ranking, 0, 5);

            if (empty($top5)) {
                $this->sendSuccess(['historik' => [], 'operatorer' => []]);
                return;
            }

            $userIds = array_column($top5, 'user_id');
            $operatorNames = [];
            foreach ($top5 as $op) {
                $operatorNames[$op['user_id']] = $op['operator_namn'];
            }

            // Hamta daglig IBC per operator via op1/op2/op3
            $historik = [];
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));

            try {
                $sql = "
                    SELECT dag, op_id, SUM(cnt) AS total_ibc
                    FROM (
                        SELECT DATE(datum) AS dag, op1 AS op_id, COUNT(*) AS cnt
                        FROM rebotling_ibc
                        WHERE op1 IN ({$placeholders})
                          AND DATE(datum) BETWEEN ? AND ?
                          AND op1 IS NOT NULL AND op1 > 0
                        GROUP BY DATE(datum), op1

                        UNION ALL

                        SELECT DATE(datum) AS dag, op2 AS op_id, COUNT(*) AS cnt
                        FROM rebotling_ibc
                        WHERE op2 IN ({$placeholders})
                          AND DATE(datum) BETWEEN ? AND ?
                          AND op2 IS NOT NULL AND op2 > 0
                        GROUP BY DATE(datum), op2

                        UNION ALL

                        SELECT DATE(datum) AS dag, op3 AS op_id, COUNT(*) AS cnt
                        FROM rebotling_ibc
                        WHERE op3 IN ({$placeholders})
                          AND DATE(datum) BETWEEN ? AND ?
                          AND op3 IS NOT NULL AND op3 > 0
                        GROUP BY DATE(datum), op3
                    ) AS combined
                    GROUP BY dag, op_id
                    ORDER BY dag ASC
                ";
                $params = array_merge(
                    $userIds, [$from30, $to],
                    $userIds, [$from30, $to],
                    $userIds, [$from30, $to]
                );
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);

                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $dag = $row['dag'];
                    $uid = (int)$row['op_id'];
                    if (!isset($historik[$dag])) {
                        $historik[$dag] = [];
                    }
                    $historik[$dag][$uid] = (int)$row['total_ibc'] * 10; // Poang
                }
            } catch (\PDOException) {
                // Fallback: rebotling_data
                if ($this->tableExists('rebotling_data')) {
                    $sql = "
                        SELECT
                            DATE(datum) AS dag,
                            user_id,
                            SUM(COALESCE(antal, 1)) AS total_ibc
                        FROM rebotling_data
                        WHERE user_id IN ({$placeholders})
                          AND DATE(datum) BETWEEN ? AND ?
                        GROUP BY DATE(datum), user_id
                        ORDER BY dag ASC
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute(array_merge($userIds, [$from30, $to]));
                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $dag = $row['dag'];
                        $uid = (int)$row['user_id'];
                        if (!isset($historik[$dag])) {
                            $historik[$dag] = [];
                        }
                        $historik[$dag][$uid] = (int)$row['total_ibc'] * 10;
                    }
                }
            }

            // Bygg datumserier
            $dates = [];
            for ($i = 29; $i >= 0; $i--) {
                $dates[] = date('Y-m-d', strtotime("-{$i} days"));
            }

            $datasets = [];
            foreach ($userIds as $uid) {
                $data = [];
                foreach ($dates as $d) {
                    $data[] = $historik[$d][$uid] ?? 0;
                }
                $datasets[] = [
                    'user_id'       => $uid,
                    'operator_namn' => $operatorNames[$uid] ?? "Operator $uid",
                    'data'          => $data,
                ];
            }

            $this->sendSuccess([
                'dates'      => $dates,
                'datasets'   => $datasets,
                'operatorer' => array_values($operatorNames),
            ]);
        } catch (\Exception $e) {
            error_log('OperatorRankingController::historik: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta historikdata', 500);
        }
    }

    // ================================================================
    // run=mvp
    // ================================================================

    private function mvp(): void {
        try {
            $typ = trim($_GET['typ'] ?? 'vecka');

            if ($typ === 'manad') {
                $from = date('Y-m-01');
            } else {
                $from = date('Y-m-d', strtotime('monday this week'));
            }
            $to = date('Y-m-d');

            $ranking = $this->calcRanking($from, $to);

            $mvp = !empty($ranking) ? $ranking[0] : null;

            $this->sendSuccess([
                'mvp'       => $mvp,
                'typ'       => $typ,
                'from_date' => $from,
                'to_date'   => $to,
            ]);
        } catch (\Exception $e) {
            error_log('OperatorRankingController::mvp: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta MVP', 500);
        }
    }
}
