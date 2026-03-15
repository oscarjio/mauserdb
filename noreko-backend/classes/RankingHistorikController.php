<?php
/**
 * RankingHistorikController.php
 * Operatörsranking historik — leaderboard-trender vecka för vecka.
 *
 * Endpoints via ?action=ranking-historik&run=XXX:
 *   run=weekly-rankings  → rankningar per operatör per vecka, senaste 12 veckor
 *   run=ranking-changes  → placeringsändring senaste vecka vs veckan innan
 *   run=streak-data      → operatörer med pågående positiva/negativa trender
 *
 * Tabeller: rebotling_ibc (datum, op1, op2, op3, ibc_ok, ibc_ej_ok, skiftraknare), operators (number, name)
 */
class RankingHistorikController {
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
            case 'weekly-rankings': $this->getWeeklyRankings(); break;
            case 'ranking-changes': $this->getRankingChanges(); break;
            case 'streak-data':     $this->getStreakData();     break;
            default:                $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getWeeks(): int {
        return max(4, min(52, intval($_GET['weeks'] ?? 12)));
    }

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

    /**
     * Hämta alla kända operatörer (nummer → namn).
     * Returnerar [nummer => namn] map.
     */
    private function getOperatorMap(): array {
        try {
            $stmt = $this->pdo->query("SELECT number, name FROM operators ORDER BY number");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $map = [];
            foreach ($rows as $r) {
                $map[(int)$r['number']] = $r['name'];
            }
            return $map;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Beräkna antal OK IBC per operatör för ett givet veckonummer + år.
     * Operatörer räknas från op1, op2 och op3 (alla tre positioner bidrar).
     * Returnerar [ operator_nummer => antal_ok_ibc ]
     */
    private function calcWeekProduction(int $year, int $week): array {
        // rebotling_ibc uses cumulative ibc_ok per skiftraknare.
        // For operator ranking we count rows (each row = 1 IBC cycle) per operator.
        $sql = "
            SELECT op, SUM(cnt) AS total_ok
            FROM (
                SELECT op1 AS op, COUNT(*) AS cnt
                FROM rebotling_ibc
                WHERE YEAR(datum) = :y1 AND WEEK(datum, 1) = :w1
                  AND op1 IS NOT NULL AND op1 > 0
                GROUP BY op1

                UNION ALL

                SELECT op2 AS op, COUNT(*) AS cnt
                FROM rebotling_ibc
                WHERE YEAR(datum) = :y2 AND WEEK(datum, 1) = :w2
                  AND op2 IS NOT NULL AND op2 > 0
                GROUP BY op2

                UNION ALL

                SELECT op3 AS op, COUNT(*) AS cnt
                FROM rebotling_ibc
                WHERE YEAR(datum) = :y3 AND WEEK(datum, 1) = :w3
                  AND op3 IS NOT NULL AND op3 > 0
                GROUP BY op3
            ) AS combined
            GROUP BY op
            HAVING total_ok > 0
            ORDER BY total_ok DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':y1' => $year, ':w1' => $week,
            ':y2' => $year, ':w2' => $week,
            ':y3' => $year, ':w3' => $week,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['op']] = (int)$r['total_ok'];
        }
        return $result;
    }

    /**
     * Räkna ut placeringar från en produktionssorterad array.
     * Returnerar [ operator_nummer => placering ] (1-indexerad)
     */
    private function calcRankings(array $productionMap): array {
        arsort($productionMap); // Störst produktion = bäst placering
        $rankings = [];
        $rank = 1;
        foreach ($productionMap as $op => $count) {
            $rankings[(int)$op] = $rank;
            $rank++;
        }
        return $rankings;
    }

    /**
     * Returnera vecka + år för N veckor sedan.
     * Returnerar ['year' => int, 'week' => int, 'label' => 'V12']
     */
    private function getWeekInfo(int $weeksAgo): array {
        $ts   = strtotime("-{$weeksAgo} weeks", strtotime('monday this week'));
        $year = (int)date('o', $ts); // ISO year
        $week = (int)date('W', $ts); // ISO week
        return [
            'year'  => $year,
            'week'  => $week,
            'label' => 'V' . $week,
            'date_from' => date('Y-m-d', $ts),
            'date_to'   => date('Y-m-d', strtotime('+6 days', $ts)),
        ];
    }

    // ================================================================
    // run=weekly-rankings
    // Hämtar rankningar per vecka för senaste N veckor.
    // Returnerar veckorubriker + per-operatör ranking per vecka.
    // ================================================================

    private function getWeeklyRankings(): void {
        try {
            $weeks   = $this->getWeeks();
            $opMap   = $this->getOperatorMap();

            // Samla produktionsdata per vecka
            $veckor = [];
            // Alla operatörer som dyker upp under perioden
            $alleOp = [];

            for ($i = $weeks - 1; $i >= 0; $i--) {
                $wi   = $this->getWeekInfo($i);
                $prod = $this->calcWeekProduction($wi['year'], $wi['week']);
                $rank = $this->calcRankings($prod);

                foreach (array_keys($prod) as $op) {
                    $alleOp[$op] = true;
                }

                $veckor[] = [
                    'year'      => $wi['year'],
                    'week'      => $wi['week'],
                    'label'     => $wi['label'],
                    'date_from' => $wi['date_from'],
                    'date_to'   => $wi['date_to'],
                    'produktion' => $prod,
                    'rankningar' => $rank,
                ];
            }

            // Bygg operatör-trender (per operatör, en lista med placering per vecka)
            $opTrender = [];
            foreach (array_keys($alleOp) as $op) {
                $trendList = [];
                foreach ($veckor as $v) {
                    $trendList[] = [
                        'vecka'   => $v['label'],
                        'year'    => $v['year'],
                        'week'    => $v['week'],
                        'ibc'     => $v['produktion'][$op] ?? 0,
                        'rank'    => $v['rankningar'][$op] ?? null, // null = ingen produktion den veckan
                    ];
                }
                $opTrender[] = [
                    'operator_id'   => $op,
                    'operator_namn' => $opMap[$op] ?? "Op $op",
                    'trend'         => $trendList,
                ];
            }

            // Sortera operatörer efter senaste veckans ranking (bäst → sämst)
            $sistaVecka = end($veckor);
            usort($opTrender, function($a, $b) use ($sistaVecka) {
                $ra = $sistaVecka['rankningar'][$a['operator_id']] ?? 9999;
                $rb = $sistaVecka['rankningar'][$b['operator_id']] ?? 9999;
                return $ra - $rb;
            });

            // Vecko-etiketter för Chart.js x-axel
            $veckoLabels = array_map(fn($v) => $v['label'], $veckor);

            $this->sendSuccess([
                'veckor'      => $veckoLabels,
                'op_trender'  => $opTrender,
                'weeks'       => $weeks,
            ]);

        } catch (Exception $e) {
            error_log('RankingHistorikController::getWeeklyRankings: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta veckoplaceringarna', 500);
        }
    }

    // ================================================================
    // run=ranking-changes
    // Beräknar placeringsändring: senaste veckan vs veckan innan.
    // ================================================================

    private function getRankingChanges(): void {
        try {
            $opMap = $this->getOperatorMap();

            // Senaste veckan
            $wiNu     = $this->getWeekInfo(0);
            $prodNu   = $this->calcWeekProduction($wiNu['year'], $wiNu['week']);
            $rankNu   = $this->calcRankings($prodNu);

            // Veckan innan
            $wiForeg   = $this->getWeekInfo(1);
            $prodForeg = $this->calcWeekProduction($wiForeg['year'], $wiForeg['week']);
            $rankForeg = $this->calcRankings($prodForeg);

            // Kombinera alla operatörer
            $alleOp = array_unique(array_merge(
                array_keys($prodNu),
                array_keys($prodForeg)
            ));

            $andringar = [];
            foreach ($alleOp as $op) {
                $nuRank  = $rankNu[$op]   ?? null;
                $foRank  = $rankForeg[$op] ?? null;

                // Räkna ut förändring: positiv = klättrat (t.ex. 3→1 = +2)
                $andring = null;
                if ($nuRank !== null && $foRank !== null) {
                    $andring = $foRank - $nuRank; // positiv = förbättrat
                }

                $andringar[] = [
                    'operator_id'    => $op,
                    'operator_namn'  => $opMap[$op] ?? "Op $op",
                    'rank_nu'        => $nuRank,
                    'rank_foreg'     => $foRank,
                    'andring'        => $andring,
                    'ibc_nu'         => $prodNu[$op]   ?? 0,
                    'ibc_foreg'      => $prodForeg[$op] ?? 0,
                    'vecka_nu'       => $wiNu['label'],
                    'vecka_foreg'    => $wiForeg['label'],
                ];
            }

            // Sortera på aktuell ranking (bäst → sämst, null sist)
            usort($andringar, function($a, $b) {
                if ($a['rank_nu'] === null && $b['rank_nu'] === null) return 0;
                if ($a['rank_nu'] === null) return 1;
                if ($b['rank_nu'] === null) return -1;
                return $a['rank_nu'] - $b['rank_nu'];
            });

            // Hitta störste klättraren
            $klattrareLista = array_filter($andringar, fn($x) => $x['andring'] !== null && $x['andring'] > 0);
            usort($klattrareLista, fn($a, $b) => $b['andring'] - $a['andring']);
            $storstKlattare = !empty($klattrareLista) ? array_values($klattrareLista)[0] : null;

            $this->sendSuccess([
                'andringar'       => $andringar,
                'storst_klattare' => $storstKlattare,
            ]);

        } catch (Exception $e) {
            error_log('RankingHistorikController::getRankingChanges: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna placeringsändringar', 500);
        }
    }

    // ================================================================
    // run=streak-data
    // Hitta operatörer med pågående positiva/negativa trender.
    // Streak = antal veckor i rad med förbättrad/försämrad placering.
    // ================================================================

    private function getStreakData(): void {
        try {
            $weeks = max(8, $this->getWeeks());
            $opMap = $this->getOperatorMap();

            // Samla rankningar för de senaste N veckorna
            $rankPerVecka = [];
            for ($i = $weeks - 1; $i >= 0; $i--) {
                $wi   = $this->getWeekInfo($i);
                $prod = $this->calcWeekProduction($wi['year'], $wi['week']);
                $rank = $this->calcRankings($prod);
                $rankPerVecka[] = $rank;
            }

            // Hitta alla operatörer
            $alleOp = [];
            foreach ($rankPerVecka as $rw) {
                foreach (array_keys($rw) as $op) {
                    $alleOp[$op] = true;
                }
            }

            $streaks = [];
            foreach (array_keys($alleOp) as $op) {
                // Bygg placerings-sekvens (äldst → nyast)
                $seq = [];
                foreach ($rankPerVecka as $rw) {
                    $seq[] = $rw[$op] ?? null;
                }

                // Räkna pågående positiv streak (klättrande placering) från slutet
                $posStreak = 0;
                for ($j = count($seq) - 1; $j > 0; $j--) {
                    if ($seq[$j] === null || $seq[$j - 1] === null) break;
                    if ($seq[$j] < $seq[$j - 1]) { // lägre placering = bättre
                        $posStreak++;
                    } else {
                        break;
                    }
                }

                // Räkna pågående negativ streak (fallande placering) från slutet
                $negStreak = 0;
                for ($j = count($seq) - 1; $j > 0; $j--) {
                    if ($seq[$j] === null || $seq[$j - 1] === null) break;
                    if ($seq[$j] > $seq[$j - 1]) { // högre placering = sämre
                        $negStreak++;
                    } else {
                        break;
                    }
                }

                $sistaRank = $seq[count($seq) - 1];

                $streaks[] = [
                    'operator_id'   => $op,
                    'operator_namn' => $opMap[$op] ?? "Op $op",
                    'rank_nu'       => $sistaRank,
                    'positiv_streak'=> $posStreak,
                    'negativ_streak'=> $negStreak,
                    'rankningssekvens' => $seq,
                ];
            }

            // Sortera efter nuvarande ranking
            usort($streaks, function($a, $b) {
                if ($a['rank_nu'] === null && $b['rank_nu'] === null) return 0;
                if ($a['rank_nu'] === null) return 1;
                if ($b['rank_nu'] === null) return -1;
                return $a['rank_nu'] - $b['rank_nu'];
            });

            // Sammanfattning
            $langstaPosStreak = !empty($streaks)
                ? max(array_column($streaks, 'positiv_streak')) : 0;

            $mestKonsekvent = null;
            // Mest konsekvent = minst varians i rankningssekvensen
            $minVarians = PHP_INT_MAX;
            foreach ($streaks as $s) {
                $vals = array_filter($s['rankningssekvens'], fn($v) => $v !== null);
                if (count($vals) < 3) continue;
                $mean   = array_sum($vals) / count($vals);
                $varians = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / count($vals);
                if ($varians < $minVarians) {
                    $minVarians     = $varians;
                    $mestKonsekvent = $s['operator_namn'];
                }
            }

            $this->sendSuccess([
                'streaks'               => $streaks,
                'langsta_pos_streak'    => $langstaPosStreak,
                'mest_konsekvent'       => $mestKonsekvent,
                'weeks'                 => $weeks,
            ]);

        } catch (Exception $e) {
            error_log('RankingHistorikController::getStreakData: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna streak-data', 500);
        }
    }
}
