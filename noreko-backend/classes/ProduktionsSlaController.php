<?php

/**
 * ProduktionsSlaController
 * Produktions-SLA / Måluppfyllnad — VD kan sätta dagliga/veckovisa produktionsmål
 * och se uppfyllnadsgrad i procent med historik.
 *
 * Endpoints via ?action=produktionssla&run=XXX:
 *
 *   GET  run=overview          — KPI:er: dagens/veckans måluppfyllnad%, streak, bästa vecka
 *   GET  run=daily-progress    (?date=YYYY-MM-DD) — dagens mål vs faktisk per timme
 *   GET  run=weekly-progress   (?week=YYYY-Wxx)   — veckans mål vs faktisk dag för dag
 *   GET  run=history           (?period=30|90)     — historik måluppfyllnad per dag
 *   GET  run=goals             — lista aktiva och historiska mål
 *   POST run=set-goal          — sätt nytt mål
 */
class ProduktionsSlaController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $run    = trim($_GET['run'] ?? '');

        if ($method === 'GET') {
            switch ($run) {
                case 'overview':         $this->getOverview();        break;
                case 'daily-progress':   $this->getDailyProgress();   break;
                case 'weekly-progress':  $this->getWeeklyProgress();  break;
                case 'history':          $this->getHistory();         break;
                case 'goals':            $this->getGoals();           break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
            }
            return;
        }

        if ($method === 'POST') {
            switch ($run) {
                case 'set-goal':
                    $this->requireAdmin();
                    $this->setGoal();
                    break;
                default:
                    $this->sendError('Okänd run-parameter', 400);
            }
            return;
        }

        $this->sendError('Ogiltig metod', 405);
    }

    // =========================================================================
    // Auth
    // =========================================================================

    private function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            $this->sendError('Sessionen har gått ut. Logga in igen.', 401);
            exit;
        }
    }

    private function requireAdmin(): void {
        $this->requireLogin();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $this->sendError('Kräver admin-behörighet', 403);
            exit;
        }
    }

    private function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sendSuccess(array $data): void {
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function ensureTables(): void {
        try {
            $check = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'produktions_mal'"
            )->fetchColumn();
            if (!$check) {
                $migrationPath = __DIR__ . '/../migrations/2026-03-12_produktions_sla.sql';
                $sql = file_get_contents($migrationPath);
                if ($sql === false) {
                    error_log('ProduktionsSlaController::ensureTables: kunde inte läsa migrationsfil: ' . $migrationPath);
                } elseif ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        } catch (\PDOException $e) {
            error_log('ProduktionsSlaController::ensureTables: ' . $e->getMessage());
        }
    }

    /**
     * Hämta aktivt mål för en viss typ och datum.
     */
    private function getActiveGoal(string $malTyp, string $date): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT id, mal_typ, target_ibc, target_kassation_pct, giltig_from, giltig_tom
             FROM produktions_mal
             WHERE mal_typ = ?
               AND giltig_from <= ?
               AND (giltig_tom IS NULL OR giltig_tom >= ?)
             ORDER BY giltig_from DESC
             LIMIT 1"
        );
        $stmt->execute([$malTyp, $date, $date]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Räkna IBC-produktion för ett datumintervall via rebotling_ibc.
     * Använder MAX(ibc_ok) per (datum, skiftraknare) — samma mönster som StatistikDashboardController.
     */
    private function getProductionForRange(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
            FROM (
                SELECT
                    DATE(datum) AS dag,
                    skiftraknare,
                    MAX(COALESCE(ibc_ok, 0))    AS shift_ok,
                    MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from_date AND :to_date
                  AND skiftraknare IS NOT NULL
                GROUP BY DATE(datum), skiftraknare
            ) AS per_shift
        ");
        $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $ok   = (int)($row['ibc_ok'] ?? 0);
        $ejOk = (int)($row['ibc_ej_ok'] ?? 0);
        $total = $ok + $ejOk;
        $kassPct = $total > 0 ? round($ejOk / $total * 100, 2) : 0.0;
        return ['ibc_ok' => $ok, 'ibc_ej_ok' => $ejOk, 'total' => $total, 'kassation_pct' => $kassPct];
    }

    /**
     * Hämta produktionsdata per dag för ett datumintervall.
     */
    private function getProductionPerDay(string $fromDate, string $toDate): array {
        $stmt = $this->pdo->prepare("
            SELECT
                dag,
                COALESCE(SUM(shift_ok), 0)    AS ibc_ok,
                COALESCE(SUM(shift_ej_ok), 0) AS ibc_ej_ok
            FROM (
                SELECT
                    DATE(datum) AS dag,
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
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Hämta produktion per timme för ett specifikt datum.
     */
    private function getProductionPerHour(string $date): array {
        $stmt = $this->pdo->prepare("
            SELECT
                HOUR(datum) AS timme,
                COUNT(*) AS antal_ibc
            FROM rebotling_ibc
            WHERE datum >= :date AND datum < DATE_ADD(:dateb, INTERVAL 1 DAY)
            GROUP BY HOUR(datum)
            ORDER BY timme ASC
        ");
        $stmt->execute([':date' => $date, ':dateb' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Beräkna veckonummer-gränser.
     */
    private function getWeekBounds(?string $weekStr = null): array {
        if ($weekStr && preg_match('/^(\d{4})-W(\d{2})$/', $weekStr, $m)) {
            $dt = new \DateTime();
            $dt->setISODate((int)$m[1], (int)$m[2], 1); // Måndag
            $monday = $dt->format('Y-m-d');
            $dt->setISODate((int)$m[1], (int)$m[2], 7); // Söndag
            $sunday = $dt->format('Y-m-d');
        } else {
            $now = new \DateTime();
            $dayOfWeek = (int)$now->format('N'); // 1=mån, 7=sön
            $monday = (clone $now)->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
            $sunday = (clone $now)->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');
        }
        return ['monday' => $monday, 'sunday' => $sunday];
    }

    // =========================================================================
    // GET run=overview
    // KPI:er: dagens måluppfyllnad%, veckans måluppfyllnad%, streak, bästa vecka
    // =========================================================================

    private function getOverview(): void {
        try {
            $today = date('Y-m-d');

            // Dagligt mål
            $dagligtMal = $this->getActiveGoal('dagligt', $today);
            $dagligtTarget = $dagligtMal ? (int)$dagligtMal['target_ibc'] : 80;
            $dagligtKassTarget = $dagligtMal ? (float)$dagligtMal['target_kassation_pct'] : 5.0;

            // Dagens produktion
            $dagProd = $this->getProductionForRange($today, $today);
            $dagPct = $dagligtTarget > 0 ? round(($dagProd['ibc_ok'] / $dagligtTarget) * 100, 1) : 0;

            // Veckomål
            $weekBounds = $this->getWeekBounds();
            $veckoMal = $this->getActiveGoal('veckovist', $today);
            $veckoTarget = $veckoMal ? (int)$veckoMal['target_ibc'] : 400;

            // Veckans produktion
            $veckoProd = $this->getProductionForRange($weekBounds['monday'], min($today, $weekBounds['sunday']));
            $veckoPct = $veckoTarget > 0 ? round(($veckoProd['ibc_ok'] / $veckoTarget) * 100, 1) : 0;

            // Streak — dagar i rad där daglig produktion >= mål (bakåt från igår)
            // Optimerad: hämta alla dagars produktion i EN query (max 90 dagar)
            $streak = 0;
            $streakFrom = (new \DateTime($today))->modify('-90 days')->format('Y-m-d');
            $streakTo   = (new \DateTime($today))->modify('-1 day')->format('Y-m-d');
            $perDayData = $this->getProductionPerDay($streakFrom, $streakTo);
            // Indexera per dag för snabb uppslag
            $prodByDay = [];
            foreach ($perDayData as $d) {
                $prodByDay[$d['dag']] = (int)$d['ibc_ok'];
            }
            $checkDate = new \DateTime($today);
            $checkDate->modify('-1 day');
            for ($i = 0; $i < 90; $i++) {
                $d = $checkDate->format('Y-m-d');
                $dayIbc = $prodByDay[$d] ?? 0;
                if ($dayIbc >= $dagligtTarget && $dayIbc > 0) {
                    $streak++;
                    $checkDate->modify('-1 day');
                } else {
                    break;
                }
            }

            // Bästa vecka senaste månaden
            $bastaVeckaPct = 0;
            $bastaVeckaLabel = '-';
            $monthAgo = (new \DateTime())->modify('-4 weeks');
            for ($w = 0; $w < 4; $w++) {
                $wMon = (clone $monthAgo)->modify('+' . ($w * 7) . ' days');
                $dayOfWeek = (int)$wMon->format('N');
                $wMon->modify('-' . ($dayOfWeek - 1) . ' days');
                $wSun = (clone $wMon)->modify('+6 days');

                $wProd = $this->getProductionForRange($wMon->format('Y-m-d'), $wSun->format('Y-m-d'));
                $wGoal = $this->getActiveGoal('veckovist', $wMon->format('Y-m-d'));
                $wTarget = $wGoal ? (int)$wGoal['target_ibc'] : $veckoTarget;
                $wPct = $wTarget > 0 ? round(($wProd['ibc_ok'] / $wTarget) * 100, 1) : 0;

                if ($wPct > $bastaVeckaPct) {
                    $bastaVeckaPct = $wPct;
                    $bastaVeckaLabel = 'v' . $wMon->format('W');
                }
            }

            $this->sendSuccess([
                'data' => [
                    'dag_pct'            => $dagPct,
                    'dag_producerat'     => $dagProd['ibc_ok'],
                    'dag_target'         => $dagligtTarget,
                    'dag_kassation_pct'  => $dagProd['kassation_pct'],
                    'dag_kass_target'    => $dagligtKassTarget,
                    'vecka_pct'          => $veckoPct,
                    'vecka_producerat'   => $veckoProd['ibc_ok'],
                    'vecka_target'       => $veckoTarget,
                    'vecka_kassation_pct'=> $veckoProd['kassation_pct'],
                    'streak'             => $streak,
                    'basta_vecka_pct'    => $bastaVeckaPct,
                    'basta_vecka_label'  => $bastaVeckaLabel,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsSlaController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översikt', 500);
        }
    }

    // =========================================================================
    // GET run=daily-progress (?date=YYYY-MM-DD)
    // Dagens mål vs faktisk produktion per timme
    // =========================================================================

    private function getDailyProgress(): void {
        try {
            $date = trim($_GET['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            $goal = $this->getActiveGoal('dagligt', $date);
            $target = $goal ? (int)$goal['target_ibc'] : 80;

            // Produktion per timme
            $hourlyData = $this->getProductionPerHour($date);
            $hourMap = [];
            foreach ($hourlyData as $h) {
                $hourMap[(int)$h['timme']] = (int)$h['antal_ibc'];
            }

            // Total produktion
            $dayProd = $this->getProductionForRange($date, $date);

            // Bygg timme-för-timme med kumulativt
            $hours = [];
            $cumulative = 0;
            for ($h = 6; $h <= 22; $h++) { // Typiskt arbetsdygn 06-22
                $count = $hourMap[$h] ?? 0;
                $cumulative += $count;
                $hours[] = [
                    'timme'       => $h,
                    'label'       => sprintf('%02d:00', $h),
                    'antal'       => $count,
                    'kumulativt'  => $cumulative,
                ];
            }

            // Måltakt per timme (jämnt fördelat över arbetstid)
            $workHours = 16; // 06-22
            $targetPerHour = $workHours > 0 ? round($target / $workHours, 1) : 0;

            $this->sendSuccess([
                'data' => [
                    'date'            => $date,
                    'target'          => $target,
                    'producerat'      => $dayProd['ibc_ok'],
                    'kassation_pct'   => $dayProd['kassation_pct'],
                    'uppfyllnad_pct'  => $target > 0 ? round(($dayProd['ibc_ok'] / $target) * 100, 1) : 0,
                    'target_per_hour' => $targetPerHour,
                    'hours'           => $hours,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsSlaController::getDailyProgress: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta daglig progress', 500);
        }
    }

    // =========================================================================
    // GET run=weekly-progress (?week=YYYY-Wxx)
    // Veckans mål vs faktisk, dag för dag
    // =========================================================================

    private function getWeeklyProgress(): void {
        try {
            $weekStr = trim($_GET['week'] ?? '');
            $bounds = $this->getWeekBounds($weekStr ?: null);

            $goal = $this->getActiveGoal('veckovist', $bounds['monday']);
            $veckoTarget = $goal ? (int)$goal['target_ibc'] : 400;

            $dagligtGoal = $this->getActiveGoal('dagligt', $bounds['monday']);
            $dagligtTarget = $dagligtGoal ? (int)$dagligtGoal['target_ibc'] : 80;

            // Per-dag produktion
            $perDag = $this->getProductionPerDay($bounds['monday'], $bounds['sunday']);
            $dagMap = [];
            foreach ($perDag as $d) {
                $dagMap[$d['dag']] = $d;
            }

            $dagNamn = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
            $days = [];
            $totalOk = 0;
            $dt = new \DateTime($bounds['monday']);
            for ($i = 0; $i < 7; $i++) {
                $d = $dt->format('Y-m-d');
                $ok = isset($dagMap[$d]) ? (int)$dagMap[$d]['ibc_ok'] : 0;
                $ejOk = isset($dagMap[$d]) ? (int)$dagMap[$d]['ibc_ej_ok'] : 0;
                $totalOk += $ok;
                $total = $ok + $ejOk;
                $kassPct = $total > 0 ? round($ejOk / $total * 100, 2) : 0;

                $days[] = [
                    'date'          => $d,
                    'dag_namn'      => $dagNamn[$i],
                    'ibc_ok'        => $ok,
                    'ibc_ej_ok'     => $ejOk,
                    'kassation_pct' => $kassPct,
                    'uppfyllnad_pct'=> $dagligtTarget > 0 ? round(($ok / $dagligtTarget) * 100, 1) : 0,
                    'over_mal'      => $ok >= $dagligtTarget,
                ];
                $dt->modify('+1 day');
            }

            $this->sendSuccess([
                'data' => [
                    'week_label'      => 'v' . (new \DateTime($bounds['monday']))->format('W'),
                    'monday'          => $bounds['monday'],
                    'sunday'          => $bounds['sunday'],
                    'vecko_target'    => $veckoTarget,
                    'dagligt_target'  => $dagligtTarget,
                    'total_producerat'=> $totalOk,
                    'uppfyllnad_pct'  => $veckoTarget > 0 ? round(($totalOk / $veckoTarget) * 100, 1) : 0,
                    'days'            => $days,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsSlaController::getWeeklyProgress: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta vecko-progress', 500);
        }
    }

    // =========================================================================
    // GET run=history (?period=30|90)
    // Historik över måluppfyllnad per dag med trend
    // =========================================================================

    private function getHistory(): void {
        try {
            $period = max(7, min(365, (int)($_GET['period'] ?? 30)));
            $today = date('Y-m-d');
            $fromDate = (new \DateTime())->modify("-{$period} days")->format('Y-m-d');

            $perDag = $this->getProductionPerDay($fromDate, $today);

            // Hämta dagligt mål (kan variera)
            $defaultGoal = $this->getActiveGoal('dagligt', $today);
            $defaultTarget = $defaultGoal ? (int)$defaultGoal['target_ibc'] : 80;

            $history = [];
            foreach ($perDag as $d) {
                $ok = (int)$d['ibc_ok'];
                $ejOk = (int)$d['ibc_ej_ok'];
                $total = $ok + $ejOk;
                $kassPct = $total > 0 ? round($ejOk / $total * 100, 2) : 0;

                // Mål för det datumet
                $dayGoal = $this->getActiveGoal('dagligt', $d['dag']);
                $dayTarget = $dayGoal ? (int)$dayGoal['target_ibc'] : $defaultTarget;

                $uppfyllnad = $dayTarget > 0 ? round(($ok / $dayTarget) * 100, 1) : 0;

                $history[] = [
                    'date'           => $d['dag'],
                    'ibc_ok'         => $ok,
                    'target'         => $dayTarget,
                    'uppfyllnad_pct' => $uppfyllnad,
                    'kassation_pct'  => $kassPct,
                    'over_mal'       => $ok >= $dayTarget,
                ];
            }

            // Snitt uppfyllnad
            $snitt = count($history) > 0
                ? round(array_sum(array_column($history, 'uppfyllnad_pct')) / count($history), 1)
                : 0;

            // Trend (enkel linjär — sista halva vs första halva)
            $half = intdiv(count($history), 2);
            $trend = 'stabil';
            if ($half > 0 && count($history) > 2) {
                $firstHalf = array_slice($history, 0, $half);
                $secondHalf = array_slice($history, $half);
                $avgFirst = array_sum(array_column($firstHalf, 'uppfyllnad_pct')) / count($firstHalf);
                $avgSecond = array_sum(array_column($secondHalf, 'uppfyllnad_pct')) / count($secondHalf);
                $diff = $avgSecond - $avgFirst;
                if ($diff > 5) $trend = 'uppat';
                elseif ($diff < -5) $trend = 'nedat';
            }

            $this->sendSuccess([
                'data' => [
                    'period'          => $period,
                    'from'            => $fromDate,
                    'to'              => $today,
                    'snitt_uppfyllnad'=> $snitt,
                    'trend'           => $trend,
                    'dagar_over_mal'  => count(array_filter($history, fn($h) => $h['over_mal'])),
                    'total_dagar'     => count($history),
                    'history'         => $history,
                ],
            ]);
        } catch (\PDOException $e) {
            error_log('ProduktionsSlaController::getHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta historik', 500);
        }
    }

    // =========================================================================
    // GET run=goals
    // Lista aktiva och historiska mål
    // =========================================================================

    private function getGoals(): void {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, mal_typ, target_ibc, target_kassation_pct, giltig_from, giltig_tom, created_by, created_at
                 FROM produktions_mal
                 ORDER BY giltig_from DESC, created_at DESC
                 LIMIT 50"
            );
            $goals = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            $result = [];
            foreach ($goals as $g) {
                $active = $g['giltig_from'] <= $today && ($g['giltig_tom'] === null || $g['giltig_tom'] >= $today);
                $result[] = [
                    'id'                    => (int)$g['id'],
                    'mal_typ'               => $g['mal_typ'],
                    'mal_typ_label'         => $g['mal_typ'] === 'dagligt' ? 'Dagligt' : 'Veckovist',
                    'target_ibc'            => (int)$g['target_ibc'],
                    'target_kassation_pct'  => (float)$g['target_kassation_pct'],
                    'giltig_from'           => $g['giltig_from'],
                    'giltig_tom'            => $g['giltig_tom'],
                    'active'                => $active,
                    'created_at'            => $g['created_at'],
                ];
            }

            $this->sendSuccess(['goals' => $result]);
        } catch (\PDOException $e) {
            error_log('ProduktionsSlaController::getGoals: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta mål', 500);
        }
    }

    // =========================================================================
    // POST run=set-goal
    // Body: { mal_typ, target_ibc, target_kassation_pct, giltig_from }
    // =========================================================================

    private function setGoal(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $malTyp = trim($data['mal_typ'] ?? '');
        if (!in_array($malTyp, ['dagligt', 'veckovist'], true)) {
            $this->sendError('mal_typ måste vara "dagligt" eller "veckovist"');
            return;
        }

        $targetIbc = max(1, min(99999, (int)($data['target_ibc'] ?? 0)));
        $targetKass = max(0, min(100, (float)($data['target_kassation_pct'] ?? 5)));
        $giltigFrom = trim($data['giltig_from'] ?? date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $giltigFrom)) {
            $this->sendError('giltig_from måste vara YYYY-MM-DD');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // Avsluta eventuellt tidigare aktivt mål av samma typ
            $this->pdo->prepare(
                "UPDATE produktions_mal
                 SET giltig_tom = ?
                 WHERE mal_typ = ?
                   AND giltig_tom IS NULL
                   AND giltig_from < ?"
            )->execute([
                (new \DateTime($giltigFrom))->modify('-1 day')->format('Y-m-d'),
                $malTyp,
                $giltigFrom,
            ]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO produktions_mal (mal_typ, target_ibc, target_kassation_pct, giltig_from, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$malTyp, $targetIbc, $targetKass, $giltigFrom, $this->currentUserId()]);
            $newId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

            $this->sendSuccess([
                'id'      => $newId,
                'message' => 'Mål sparat',
            ]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('ProduktionsSlaController::setGoal: ' . $e->getMessage());
            $this->sendError('Kunde inte spara mål', 500);
        }
    }
}
