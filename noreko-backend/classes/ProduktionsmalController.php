<?php
/**
 * ProduktionsmalController.php
 * Produktionsmal-dashboard — VD kan satta vecko/manadsmal och se progress i realtid.
 *
 * Endpoints via ?action=produktionsmal&run=XXX:
 *   run=aktuellt-mal  -> Hamta aktivt mal (vecka eller manad)
 *   run=progress      -> Aktuell progress mot malet: producerade hittills, mal, procent, prognos
 *   run=satt-mal      -> Spara nytt mal (POST: typ, antal, startdatum)
 *   run=mal-historik   -> Historiska mal och om de uppnaddes
 *
 *   (Legacy, behalls for bakatkompabilitet)
 *   run=summary        -> Dag/vecka/manad sammanfattning
 *   run=daily          -> Daglig tidsserie
 *   run=weekly         -> Veckovis data
 *
 * Auth: session kravs (401 om ej inloggad).
 *
 * Tabeller: rebotling_ibc (ok=1 for godkanda), rebotling_produktionsmal, rebotling_weekday_goals
 */
class ProduktionsmalController {
    private $pdo;

    /** Fallback dagsmal om rebotling_weekday_goals saknas */
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
            $this->sendError('Inloggning kravs', 401);
            return;
        }

        $run = trim($_GET['run'] ?? '');

        switch ($run) {
            // Nya endpoints
            case 'aktuellt-mal':  $this->getAktuelltMal();  break;
            case 'progress':      $this->getProgress();      break;
            case 'satt-mal':      $this->sattMal();          break;
            case 'mal-historik':  $this->getMalHistorik();   break;

            // Legacy endpoints
            case 'summary': $this->getSummary(); break;
            case 'daily':   $this->getDaily();   break;
            case 'weekly':  $this->getWeekly();  break;

            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
        }
    }

    // ================================================================
    // NYA ENDPOINTS
    // ================================================================

    /**
     * run=aktuellt-mal — Hamta det aktiva malet (vecka eller manad)
     * Returnerar det mal vars period inkluderar dagens datum.
     */
    private function getAktuelltMal(): void {
        try {
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT id, typ, mal_antal, start_datum, slut_datum, skapad_av, skapad_datum
                FROM rebotling_produktionsmal
                WHERE start_datum <= :today AND slut_datum >= :today2
                ORDER BY skapad_datum DESC
                LIMIT 1
            ");
            $stmt->execute([':today' => $today, ':today2' => $today]);
            $mal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($mal) {
                $mal['mal_antal'] = (int)$mal['mal_antal'];
                $mal['id'] = (int)$mal['id'];
                $mal['skapad_av'] = $mal['skapad_av'] ? (int)$mal['skapad_av'] : null;
            }

            $this->sendSuccess([
                'mal' => $mal ?: null,
                'har_mal' => (bool)$mal,
            ]);
        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getAktuelltMal: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta aktivt mal', 500);
        }
    }

    /**
     * run=progress — Aktuell progress mot aktivt mal
     * Returnerar: producerade hittills, mal, procent, prognos, daglig produktion
     */
    private function getProgress(): void {
        try {
            $today = date('Y-m-d');

            // Hamta aktivt mal
            $stmt = $this->pdo->prepare("
                SELECT id, typ, mal_antal, start_datum, slut_datum
                FROM rebotling_produktionsmal
                WHERE start_datum <= :today AND slut_datum >= :today2
                ORDER BY skapad_datum DESC
                LIMIT 1
            ");
            $stmt->execute([':today' => $today, ':today2' => $today]);
            $mal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$mal) {
                $this->sendSuccess([
                    'har_mal' => false,
                    'meddelande' => 'Inget aktivt mal hittat. Satt ett mal forst.',
                ]);
                return;
            }

            $malAntal = (int)$mal['mal_antal'];
            $startDatum = $mal['start_datum'];
            $slutDatum = $mal['slut_datum'];

            // Hamta producerade IBC (ok=1) i perioden
            $ibcStmt = $this->pdo->prepare("
                SELECT COUNT(*) AS antal
                FROM rebotling_ibc
                WHERE ok = 1
                  AND DATE(datum) BETWEEN :start AND :slut
            ");
            $ibcStmt->execute([':start' => $startDatum, ':slut' => min($today, $slutDatum)]);
            $producerat = (int)($ibcStmt->fetchColumn() ?? 0);

            // Berakna arbetsdagar hittills och kvar
            $periodStart = new \DateTime($startDatum);
            $periodSlut = new \DateTime($slutDatum);
            $todayDt = new \DateTime($today);

            $arbetsdagarHittills = 0;
            $arbetsdagarTotalt = 0;
            $d = clone $periodStart;
            while ($d <= $periodSlut) {
                $dow = (int)$d->format('N');
                if ($dow <= 5) {
                    $arbetsdagarTotalt++;
                    if ($d <= $todayDt) {
                        $arbetsdagarHittills++;
                    }
                }
                $d->modify('+1 day');
            }

            $arbetsdagarKvar = $arbetsdagarTotalt - $arbetsdagarHittills;
            $aterstaar = max(0, $malAntal - $producerat);
            $procent = $malAntal > 0 ? round(($producerat / $malAntal) * 100, 1) : 0.0;

            // Prognos: snitt per arbetsdag hittills -> extrapolera
            $snittPerDag = $arbetsdagarHittills > 0 ? ($producerat / $arbetsdagarHittills) : 0;
            $prognosSlut = round($snittPerDag * $arbetsdagarTotalt);

            // Prognosmeddelande
            $prognosStatus = '';
            $prognosFarg = 'neutral';
            $behoverOkaMed = 0;

            if ($arbetsdagarHittills > 0) {
                if ($prognosSlut >= $malAntal) {
                    // Berakna nar malet nas
                    $ibcKvar = max(0, $malAntal - $producerat);
                    if ($ibcKvar <= 0) {
                        $prognosStatus = 'Malet ar redan uppnatt!';
                        $prognosFarg = 'gron';
                    } else {
                        $dagarTillMal = ceil($ibcKvar / $snittPerDag);
                        $malDatum = clone $todayDt;
                        $raknare = 0;
                        while ($raknare < $dagarTillMal) {
                            $malDatum->modify('+1 day');
                            $dow = (int)$malDatum->format('N');
                            if ($dow <= 5) $raknare++;
                        }
                        $prognosStatus = 'I nuvarande takt nar ni ' . number_format($prognosSlut) . ' IBC (mal: ' . number_format($malAntal) . ') — pa god vag!';
                        $prognosFarg = 'gron';
                    }
                } else {
                    // Behover oka takten
                    $kravPerDag = $arbetsdagarKvar > 0 ? ceil($aterstaar / $arbetsdagarKvar) : $aterstaar;
                    $snittAvrund = round($snittPerDag);
                    $okningPct = $snittPerDag > 0 ? round((($kravPerDag / $snittPerDag) - 1) * 100) : 100;
                    $prognosStatus = 'Behover oka fran ' . $snittAvrund . ' till ' . $kravPerDag . ' IBC/dag (' . $okningPct . '% okning)';
                    $prognosFarg = 'rod';
                    $behoverOkaMed = $okningPct;
                }
            }

            // Daglig produktion i perioden (for stapeldiagram)
            $dagligStmt = $this->pdo->prepare("
                SELECT DATE(datum) AS dag, COUNT(*) AS antal
                FROM rebotling_ibc
                WHERE ok = 1
                  AND DATE(datum) BETWEEN :start AND :slut
                GROUP BY DATE(datum)
                ORDER BY dag ASC
            ");
            $dagligStmt->execute([':start' => $startDatum, ':slut' => min($today, $slutDatum)]);
            $dagligRows = $dagligStmt->fetchAll(\PDO::FETCH_ASSOC);

            $dagligProduktion = [];
            foreach ($dagligRows as $row) {
                $dagligProduktion[] = [
                    'datum' => $row['dag'],
                    'antal' => (int)$row['antal'],
                ];
            }

            // Snitt per dag som kravs for att na malet (mallinje)
            $malPerDag = $arbetsdagarTotalt > 0 ? round($malAntal / $arbetsdagarTotalt) : 0;

            // Dagar kvar i perioden (ej bara arbetsdagar)
            $diff = $todayDt->diff($periodSlut);
            $dagarKvar = max(0, (int)$diff->days);

            $this->sendSuccess([
                'har_mal' => true,
                'mal' => [
                    'id' => (int)$mal['id'],
                    'typ' => $mal['typ'],
                    'mal_antal' => $malAntal,
                    'start_datum' => $startDatum,
                    'slut_datum' => $slutDatum,
                ],
                'producerat' => $producerat,
                'aterstaar' => $aterstaar,
                'procent' => $procent,
                'dagar_kvar' => $dagarKvar,
                'arbetsdagar_kvar' => $arbetsdagarKvar,
                'arbetsdagar_hittills' => $arbetsdagarHittills,
                'arbetsdagar_totalt' => $arbetsdagarTotalt,
                'snitt_per_dag' => round($snittPerDag, 1),
                'mal_per_dag' => $malPerDag,
                'prognos_slut' => $prognosSlut,
                'prognos_status' => $prognosStatus,
                'prognos_farg' => $prognosFarg,
                'behover_oka_med' => $behoverOkaMed,
                'daglig_produktion' => $dagligProduktion,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getProgress: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta progress', 500);
        }
    }

    /**
     * run=satt-mal — Spara nytt mal (POST)
     * POST-data: typ (vecka/manad), antal, startdatum
     */
    private function sattMal(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('POST kravs', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->sendError('Ogiltig JSON-data');
            return;
        }

        $typ = trim($input['typ'] ?? '');
        $antal = (int)($input['antal'] ?? 0);
        $startdatum = trim($input['startdatum'] ?? '');

        // Validering
        if (!in_array($typ, ['vecka', 'manad'])) {
            $this->sendError('Ogiltig typ. Anvand "vecka" eller "manad".');
            return;
        }
        if ($antal <= 0 || $antal > 99999) {
            $this->sendError('Antal maste vara mellan 1 och 99999.');
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startdatum)) {
            $this->sendError('Ogiltigt datumformat. Anvand YYYY-MM-DD.');
            return;
        }

        try {
            // Berakna slutdatum
            $startDt = new \DateTime($startdatum);
            if ($typ === 'vecka') {
                // Satt start till mandag i veckan
                $dow = (int)$startDt->format('N');
                if ($dow !== 1) {
                    $startDt->modify('monday this week');
                }
                $slutDt = clone $startDt;
                $slutDt->modify('+6 days'); // Sondag
            } else {
                // Satt start till forsta i manaden
                $startDt->modify('first day of this month');
                $slutDt = clone $startDt;
                $slutDt->modify('last day of this month');
            }

            $startStr = $startDt->format('Y-m-d');
            $slutStr = $slutDt->format('Y-m-d');
            $userId = $_SESSION['user_id'] ?? null;

            $stmt = $this->pdo->prepare("
                INSERT INTO rebotling_produktionsmal (typ, mal_antal, start_datum, slut_datum, skapad_av)
                VALUES (:typ, :antal, :start, :slut, :user)
            ");
            $stmt->execute([
                ':typ' => $typ,
                ':antal' => $antal,
                ':start' => $startStr,
                ':slut' => $slutStr,
                ':user' => $userId,
            ]);

            $this->sendSuccess([
                'meddelande' => 'Mal sparat!',
                'id' => (int)$this->pdo->lastInsertId(),
                'typ' => $typ,
                'mal_antal' => $antal,
                'start_datum' => $startStr,
                'slut_datum' => $slutStr,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsmalController::sattMal: ' . $e->getMessage());
            $this->sendError('Kunde inte spara malet', 500);
        }
    }

    /**
     * run=mal-historik — Historiska mal och utfall (senaste 12 perioder)
     */
    private function getMalHistorik(): void {
        try {
            $limit = max(5, min(50, (int)($_GET['limit'] ?? 12)));

            $stmt = $this->pdo->prepare("
                SELECT id, typ, mal_antal, start_datum, slut_datum, skapad_datum
                FROM rebotling_produktionsmal
                ORDER BY start_datum DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $malen = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            $historik = [];

            foreach ($malen as $m) {
                $malAntal = (int)$m['mal_antal'];
                $startDatum = $m['start_datum'];
                $slutDatum = $m['slut_datum'];

                // Berakna faktisk produktion
                $ibcStmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS antal
                    FROM rebotling_ibc
                    WHERE ok = 1
                      AND DATE(datum) BETWEEN :start AND :slut
                ");
                $effektivtSlut = min($today, $slutDatum);
                $ibcStmt->execute([':start' => $startDatum, ':slut' => $effektivtSlut]);
                $faktiskt = (int)($ibcStmt->fetchColumn() ?? 0);

                $avslutad = $slutDatum < $today;
                $uppnadd = $faktiskt >= $malAntal;
                $differens = $faktiskt - $malAntal;
                $procent = $malAntal > 0 ? round(($faktiskt / $malAntal) * 100, 1) : 0.0;

                $historik[] = [
                    'id' => (int)$m['id'],
                    'typ' => $m['typ'],
                    'mal_antal' => $malAntal,
                    'start_datum' => $startDatum,
                    'slut_datum' => $slutDatum,
                    'skapad_datum' => $m['skapad_datum'],
                    'faktiskt' => $faktiskt,
                    'procent' => $procent,
                    'uppnadd' => $uppnadd,
                    'avslutad' => $avslutad,
                    'differens' => $differens,
                ];
            }

            $this->sendSuccess([
                'antal' => count($historik),
                'historik' => $historik,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getMalHistorik: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta malhistorik', 500);
        }
    }

    // ================================================================
    // LEGACY ENDPOINTS (bakatkompabilitet)
    // ================================================================

    private function getSummary(): void {
        try {
            $today = date('Y-m-d');
            $weekdayGoals = $this->getWeekdayGoals();

            $dagMal = $this->getDailyGoal($today, $weekdayGoals);
            $dagFactual = $this->getFactualIbcByDate($today, $today);
            $dagIbc = $dagFactual[$today] ?? 0;
            $dagPct = $dagMal > 0 ? round(($dagIbc / $dagMal) * 100, 1) : 0.0;

            $nowHour = (int)date('G');
            $shiftStart = 6;
            $shiftEnd   = 22;
            $totalShiftHours = $shiftEnd - $shiftStart;
            $elapsedHours = max(0, min($totalShiftHours, $nowHour - $shiftStart));
            $dagPrognosIbc = $elapsedHours > 0 && $totalShiftHours > 0
                ? round($dagIbc / $elapsedHours * $totalShiftHours)
                : $dagIbc;
            $dagPrognosPct = $dagMal > 0 ? round(($dagPrognosIbc / $dagMal) * 100, 1) : 0.0;

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

            $fullWeekEnd = date('Y-m-d', strtotime('sunday this week'));
            $fullVeckoMal = 0;
            $cur2 = strtotime($weekStart);
            $end2 = strtotime($fullWeekEnd);
            while ($cur2 <= $end2) {
                $fullVeckoMal += $this->getDailyGoal(date('Y-m-d', $cur2), $weekdayGoals);
                $cur2 = strtotime('+1 day', $cur2);
            }

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
                    'datum' => $today, 'mal' => $dagMal, 'faktiskt' => $dagIbc,
                    'uppfyllnad' => $dagPct, 'prognos_ibc' => $dagPrognosIbc,
                    'prognos_pct' => $dagPrognosPct, 'status' => $this->getStatus($dagPrognosPct),
                    'elapsed_h' => $elapsedHours, 'total_h' => $totalShiftHours,
                ],
                'vecka' => [
                    'veckonr' => (int)date('W'), 'start' => $weekStart, 'slut' => $weekEnd,
                    'mal' => $veckoMal, 'full_mal' => $fullVeckoMal, 'faktiskt' => $veckoIbc,
                    'uppfyllnad' => $veckoPct, 'status' => $this->getStatus($veckoPct),
                ],
                'manad' => [
                    'manad' => date('Y-m'), 'start' => $monthStart, 'slut' => $monthEnd,
                    'mal' => $manadsMal, 'full_mal' => $fullManadsMal, 'faktiskt' => $manadsIbc,
                    'uppfyllnad' => $manadsPct, 'status' => $this->getStatus($manadsPct),
                ],
            ]);
        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta sammanfattning', 500);
        }
    }

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
                    'datum' => $dag, 'veckodag' => $this->getSwedishWeekday($dag),
                    'mal' => $mal, 'faktiskt' => $fakt, 'uppfyllnad' => $pct,
                    'kum_mal' => $kumMal, 'kum_faktiskt' => $kumFaktiskt,
                    'kum_pct' => $kumMal > 0 ? round(($kumFaktiskt / $kumMal) * 100, 1) : 0.0,
                ];
                $current = strtotime('+1 day', $current);
            }
            $this->sendSuccess(['days' => $days, 'daily' => $result]);
        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getDaily: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta daglig data', 500);
        }
    }

    private function getWeekly(): void {
        $weeks = max(4, min(52, (int)($_GET['weeks'] ?? 12)));
        try {
            $weekdayGoals = $this->getWeekdayGoals();
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
                $mal = 0; $fakt = 0;
                $cur = $weekStart;
                $endW = strtotime($wEnd);
                $today = strtotime(date('Y-m-d'));
                while ($cur <= $endW) {
                    $d = date('Y-m-d', $cur);
                    if ($cur <= $today) {
                        $mal  += $this->getDailyGoal($d, $weekdayGoals);
                        $fakt += $factual[$d] ?? 0;
                    }
                    $cur = strtotime('+1 day', $cur);
                }
                $pct = $mal > 0 ? round(($fakt / $mal) * 100, 1) : ($fakt > 0 ? 100.0 : 0.0);
                $result[] = [
                    'veckonr' => $veckonr, 'ar' => $ar, 'start' => $wStart, 'slut' => $wEnd,
                    'mal' => $mal, 'faktiskt' => $fakt, 'uppfyllnad' => $pct,
                    'status' => $this->getStatus($pct),
                ];
                $weekStart = strtotime('+7 days', $weekStart);
            }
            $this->sendSuccess(['weeks' => $weeks, 'weekly' => $result]);
        } catch (\Exception $e) {
            error_log('ProduktionsmalController::getWeekly: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta veckodata', 500);
        }
    }

    // ================================================================
    // HJALPFUNKTIONER
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
            return [];
        }
    }

    private function getDailyGoal(string $date, array $weekdayGoals): int {
        $wd = (int)date('N', strtotime($date));
        return $weekdayGoals[$wd] ?? self::DEFAULT_DAILY_GOAL;
    }

    private function getFactualIbcByDate(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare(
            "SELECT dag, SUM(max_ibc) AS ibc_count
             FROM (
                SELECT DATE(created_at) AS dag, skiftraknare, MAX(ibc_ok) AS max_ibc
                FROM rebotling_ibc
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at), skiftraknare
                HAVING COUNT(*) > 1
             ) sub
             GROUP BY dag ORDER BY dag ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $result[$r['dag']] = (int)$r['ibc_count'];
        }
        return $result;
    }

    private function getStatus(float $pct): string {
        if ($pct >= 90) return 'ahead';
        if ($pct >= 70) return 'on_track';
        return 'behind';
    }

    private function getSwedishWeekday(string $date): string {
        $days = ['', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor', 'Son'];
        $wd = (int)date('N', strtotime($date));
        return $days[$wd] ?? '';
    }
}
