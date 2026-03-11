<?php
/**
 * ProduktionsmalController.php
 * Produktionsmål vs utfall — VD-dashboard.
 * Visar dag/vecka/månad mål vs faktisk produktion med statusindikator.
 *
 * Endpoints via ?action=produktionsmal&run=XXX:
 *   run=summary
 *       Aktuell dag/vecka/månad — mål vs faktisk IBC, %-uppfyllnad, status.
 *
 *   run=daily&days=N
 *       Daglig tidsserie med mål-IBC, faktisk-IBC, uppfyllnad-%, kumulativt.
 *
 *   run=weekly&weeks=N
 *       Veckovis — veckonummer, mål, faktiskt, uppfyllnad.
 *
 * Auth: session krävs (401 om ej inloggad).
 *
 * Beräkningsmodell:
 *   - Dagsmål: hämtas från rebotling_weekday_goals (per veckodag).
 *   - Faktisk IBC: SUM av MAX(ibc_ok) per skiftraknare per dag, från rebotling_ibc.
 *   - Veckomål/månadsmål: summa av dagsmål för resp. dagar i perioden.
 *
 * Tabeller: rebotling_ibc, rebotling_weekday_goals
 */
class ProduktionsmalController {
    private $pdo;

    /** Fallback dagsmål om rebotling_weekday_goals saknas */
    private const DEFAULT_DAILY_GOAL = 1000;

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
            case 'summary': $this->getSummary(); break;
            case 'daily':   $this->getDaily();   break;
            case 'weekly':  $this->getWeekly();  break;
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

    /**
     * Hämta veckodagsmål (1=Måndag..7=Söndag) som associativ array.
     */
    private function getWeekdayGoals(): array {
        try {
            $stmt = $this->pdo->query(
                "SELECT weekday, daily_goal FROM rebotling_weekday_goals ORDER BY weekday"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $goals = [];
            foreach ($rows as $r) {
                $goals[(int)$r['weekday']] = (int)$r['daily_goal'];
            }
            return $goals;
        } catch (\Exception $e) {
            // Fallback om tabellen inte finns
            return [];
        }
    }

    /**
     * Hämta dagsmål för ett visst datum.
     */
    private function getDailyGoal(string $date, array $weekdayGoals): int {
        $wd = (int)date('N', strtotime($date)); // 1=Mån..7=Sön (ISO)
        return $weekdayGoals[$wd] ?? self::DEFAULT_DAILY_GOAL;
    }

    /**
     * Hämta faktisk IBC-produktion per dag i ett datumintervall.
     * Returnerar ['Y-m-d' => ibcCount, ...]
     */
    private function getFactualIbcByDate(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare(
            "SELECT dag, SUM(max_ibc) AS ibc_count
             FROM (
                SELECT
                    DATE(created_at) AS dag,
                    skiftraknare,
                    MAX(ibc_ok) AS max_ibc
                FROM rebotling_ibc
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at), skiftraknare
                HAVING COUNT(*) > 1
             ) sub
             GROUP BY dag
             ORDER BY dag ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $result[$r['dag']] = (int)$r['ibc_count'];
        }
        return $result;
    }

    /**
     * Bestäm status baserat på uppfyllnadsgrad.
     */
    private function getStatus(float $pct): string {
        if ($pct >= 90) return 'ahead';
        if ($pct >= 70) return 'on_track';
        return 'behind';
    }

    // ================================================================
    // run=summary
    // ================================================================

    private function getSummary(): void {
        try {
            $today = date('Y-m-d');
            $weekdayGoals = $this->getWeekdayGoals();

            // --- DAG ---
            $dagMal = $this->getDailyGoal($today, $weekdayGoals);
            $dagFactual = $this->getFactualIbcByDate($today, $today);
            $dagIbc = $dagFactual[$today] ?? 0;
            $dagPct = $dagMal > 0 ? round(($dagIbc / $dagMal) * 100, 1) : 0.0;

            // Tidsbaserad justering: hur långt in i dagen är vi?
            // Hämta antal aktiva skifttimmar (06-22 = 16h om 2 skift)
            $nowHour = (int)date('G');
            $shiftStart = 6;
            $shiftEnd   = 22;
            $totalShiftHours = $shiftEnd - $shiftStart;
            $elapsedHours = max(0, min($totalShiftHours, $nowHour - $shiftStart));
            $dagPrognosIbc = $elapsedHours > 0 && $totalShiftHours > 0
                ? round($dagIbc / $elapsedHours * $totalShiftHours)
                : $dagIbc;
            $dagPrognosPct = $dagMal > 0 ? round(($dagPrognosIbc / $dagMal) * 100, 1) : 0.0;

            // --- VECKA ---
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd   = $today;
            $weekFactual = $this->getFactualIbcByDate($weekStart, $weekEnd);

            $veckoMal = 0;
            $veckoIbc = 0;
            $current = strtotime($weekStart);
            $endTs   = strtotime($weekEnd);
            while ($current <= $endTs) {
                $d = date('Y-m-d', $current);
                $veckoMal += $this->getDailyGoal($d, $weekdayGoals);
                $veckoIbc += $weekFactual[$d] ?? 0;
                $current = strtotime('+1 day', $current);
            }
            $veckoPct = $veckoMal > 0 ? round(($veckoIbc / $veckoMal) * 100, 1) : 0.0;

            // Fullt veckomål (mån-sön)
            $fullWeekEnd = date('Y-m-d', strtotime('sunday this week'));
            $fullVeckoMal = 0;
            $cur2 = strtotime($weekStart);
            $end2 = strtotime($fullWeekEnd);
            while ($cur2 <= $end2) {
                $fullVeckoMal += $this->getDailyGoal(date('Y-m-d', $cur2), $weekdayGoals);
                $cur2 = strtotime('+1 day', $cur2);
            }

            // --- MÅNAD ---
            $monthStart = date('Y-m-01');
            $monthEnd   = $today;
            $monthFactual = $this->getFactualIbcByDate($monthStart, $monthEnd);

            $manadsMal = 0;
            $manadsIbc = 0;
            $cur3 = strtotime($monthStart);
            $end3 = strtotime($monthEnd);
            while ($cur3 <= $end3) {
                $d = date('Y-m-d', $cur3);
                $manadsMal += $this->getDailyGoal($d, $weekdayGoals);
                $manadsIbc += $monthFactual[$d] ?? 0;
                $cur3 = strtotime('+1 day', $cur3);
            }
            $manadsPct = $manadsMal > 0 ? round(($manadsIbc / $manadsMal) * 100, 1) : 0.0;

            // Fullt månadsmål
            $fullMonthEnd = date('Y-m-t');
            $fullManadsMal = 0;
            $cur4 = strtotime($monthStart);
            $end4 = strtotime($fullMonthEnd);
            while ($cur4 <= $end4) {
                $fullManadsMal += $this->getDailyGoal(date('Y-m-d', $cur4), $weekdayGoals);
                $cur4 = strtotime('+1 day', $cur4);
            }

            $this->sendSuccess([
                'dag' => [
                    'datum'      => $today,
                    'mal'        => $dagMal,
                    'faktiskt'   => $dagIbc,
                    'uppfyllnad' => $dagPct,
                    'prognos_ibc' => $dagPrognosIbc,
                    'prognos_pct' => $dagPrognosPct,
                    'status'     => $this->getStatus($dagPrognosPct),
                    'elapsed_h'  => $elapsedHours,
                    'total_h'    => $totalShiftHours,
                ],
                'vecka' => [
                    'veckonr'    => (int)date('W'),
                    'start'      => $weekStart,
                    'slut'       => $weekEnd,
                    'mal'        => $veckoMal,
                    'full_mal'   => $fullVeckoMal,
                    'faktiskt'   => $veckoIbc,
                    'uppfyllnad' => $veckoPct,
                    'status'     => $this->getStatus($veckoPct),
                ],
                'manad' => [
                    'manad'      => date('Y-m'),
                    'start'      => $monthStart,
                    'slut'       => $monthEnd,
                    'mal'        => $manadsMal,
                    'full_mal'   => $fullManadsMal,
                    'faktiskt'   => $manadsIbc,
                    'uppfyllnad' => $manadsPct,
                    'status'     => $this->getStatus($manadsPct),
                ],
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=daily
    // ================================================================

    private function getDaily(): void {
        $days = max(7, min(365, (int)($_GET['days'] ?? 30)));

        try {
            $toDate   = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            $weekdayGoals = $this->getWeekdayGoals();
            $factual = $this->getFactualIbcByDate($fromDate, $toDate);

            $result = [];
            $kumMal = 0;
            $kumFaktiskt = 0;

            $current = strtotime($fromDate);
            $end     = strtotime($toDate);
            while ($current <= $end) {
                $dag = date('Y-m-d', $current);
                $mal = $this->getDailyGoal($dag, $weekdayGoals);
                $fakt = $factual[$dag] ?? 0;

                $kumMal      += $mal;
                $kumFaktiskt += $fakt;

                $pct = $mal > 0 ? round(($fakt / $mal) * 100, 1) : ($fakt > 0 ? 100.0 : 0.0);

                $result[] = [
                    'datum'        => $dag,
                    'veckodag'     => $this->getSwedishWeekday($dag),
                    'mal'          => $mal,
                    'faktiskt'     => $fakt,
                    'uppfyllnad'   => $pct,
                    'kum_mal'      => $kumMal,
                    'kum_faktiskt' => $kumFaktiskt,
                    'kum_pct'      => $kumMal > 0 ? round(($kumFaktiskt / $kumMal) * 100, 1) : 0.0,
                ];

                $current = strtotime('+1 day', $current);
            }

            $this->sendSuccess([
                'days' => $days,
                'daily' => $result,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getDaily: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta daglig data', 500);
        }
    }

    // ================================================================
    // run=weekly
    // ================================================================

    private function getWeekly(): void {
        $weeks = max(4, min(52, (int)($_GET['weeks'] ?? 12)));

        try {
            $weekdayGoals = $this->getWeekdayGoals();

            // Beräkna from-datum (N veckor bakåt, börja på måndag)
            $toDate = date('Y-m-d', strtotime('sunday this week'));
            $fromDate = date('Y-m-d', strtotime("-{$weeks} weeks monday"));

            $factual = $this->getFactualIbcByDate($fromDate, date('Y-m-d'));

            $result = [];
            $weekStart = strtotime($fromDate);

            for ($w = 0; $w < $weeks; $w++) {
                $wStart = date('Y-m-d', $weekStart);
                $wEnd   = date('Y-m-d', strtotime('+6 days', $weekStart));
                $veckonr = (int)date('W', $weekStart);
                $ar      = (int)date('o', $weekStart);

                $mal  = 0;
                $fakt = 0;
                $cur  = $weekStart;
                $endW = strtotime($wEnd);
                $today = strtotime(date('Y-m-d'));

                while ($cur <= $endW) {
                    $d = date('Y-m-d', $cur);
                    // Bara räkna mål för dagar som passerat (eller idag)
                    if ($cur <= $today) {
                        $mal  += $this->getDailyGoal($d, $weekdayGoals);
                        $fakt += $factual[$d] ?? 0;
                    }
                    $cur = strtotime('+1 day', $cur);
                }

                $pct = $mal > 0 ? round(($fakt / $mal) * 100, 1) : ($fakt > 0 ? 100.0 : 0.0);

                $result[] = [
                    'veckonr'    => $veckonr,
                    'ar'         => $ar,
                    'start'      => $wStart,
                    'slut'       => $wEnd,
                    'mal'        => $mal,
                    'faktiskt'   => $fakt,
                    'uppfyllnad' => $pct,
                    'status'     => $this->getStatus($pct),
                ];

                $weekStart = strtotime('+7 days', $weekStart);
            }

            $this->sendSuccess([
                'weeks'  => $weeks,
                'weekly' => $result,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getWeekly: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta veckodata', 500);
        }
    }

    // ================================================================
    // HJÄLPMETOD
    // ================================================================

    private function getSwedishWeekday(string $date): string {
        $days = ['', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
        $wd = (int)date('N', strtotime($date));
        return $days[$wd] ?? '';
    }
}
