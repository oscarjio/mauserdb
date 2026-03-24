<?php
/**
 * ProduktionsTaktController.php
 * Realtids-produktionstakt — IBC per timme med trend, alert och historik.
 *
 * Endpoints via ?action=produktionstakt&run=XXX:
 *   run=current-rate   Aktuell IBC/h (senaste timmen), trendpil, alert-status,
 *                      referenssnitt (4h, idag, vecka).
 *   run=hourly-history Timvis produktion senaste 24h.
 *   run=get-target     Hämta aktuellt måltal.
 *   run=set-target     Sätta måltal (POST, admin).
 *
 * Tabell: rebotling_ibc (kolumn datum).
 * Måltalstabell: produktionstakt_target (skapas via migration).
 */
class ProduktionsTaktController {
    private $pdo;

    /** Minuter under tröskelvärde innan alert utlöses */
    private const ALERT_THRESHOLD_MINUTES = 15;

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
            case 'current-rate':   $this->getCurrentRate();   break;
            case 'hourly-history': $this->getHourlyHistory(); break;
            case 'get-target':     $this->getTarget();        break;
            case 'set-target':     $this->setTarget();        break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
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

    /**
     * Räkna IBC:er i ett tidsintervall.
     */
    private function countIbcBetween(string $from, string $to): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE datum BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Hämta sparat måltal. Returnerar standardvärde om tabellen saknas.
     */
    private function getTargetValue(): float {
        try {
            $stmt = $this->pdo->query(
                "SELECT target_ibc_per_hour FROM produktionstakt_target ORDER BY id DESC LIMIT 1"
            );
            $val = $stmt->fetchColumn();
            return $val !== false ? (float)$val : 12.0;
        } catch (\Exception $e) {
            error_log('ProduktionsTaktController::getTargetValue: ' . $e->getMessage());
            return 12.0;
        }
    }

    // ================================================================
    // run=current-rate
    // ================================================================

    private function getCurrentRate(): void {
        try {
            $now = date('Y-m-d H:i:s');

            // Senaste timmen
            $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $currentCount = $this->countIbcBetween($oneHourAgo, $now);
            $currentRate = (float)$currentCount; // IBC per 1 timme = count

            // Föregående timme (för trend)
            $twoHoursAgo = date('Y-m-d H:i:s', strtotime('-2 hours'));
            $prevCount = $this->countIbcBetween($twoHoursAgo, $oneHourAgo);
            $prevRate = (float)$prevCount;

            // Trend
            $diff = $currentRate - $prevRate;
            if ($diff > 1) {
                $trend = 'up';
            } elseif ($diff < -1) {
                $trend = 'down';
            } else {
                $trend = 'stable';
            }

            // Senaste 4h snitt
            $fourHoursAgo = date('Y-m-d H:i:s', strtotime('-4 hours'));
            $count4h = $this->countIbcBetween($fourHoursAgo, $now);
            $avg4h = round($count4h / 4.0, 1);

            // Dagens snitt
            // Bugfix: anvand DateTime-diff for DST-korrekt timberakning
            $nowDt = new \DateTime('now', new \DateTimeZone('Europe/Stockholm'));
            $dayStart = date('Y-m-d 00:00:00');
            $dayStartDt = new \DateTime($dayStart, new \DateTimeZone('Europe/Stockholm'));
            $hoursToday = max(1, ($nowDt->getTimestamp() - $dayStartDt->getTimestamp()) / 3600);
            $countToday = $this->countIbcBetween($dayStart, $now);
            $avgToday = round($countToday / $hoursToday, 1);

            // Veckans snitt
            // Bugfix: anvand DateTime-diff for timberakning istallet for (time() - strtotime()) / 3600.
            // time() - strtotime() ar av med 1 timme nar veckan spanner DST-overgangen (mars/oktober).
            // Bugfix #285: strtotime('monday this week') ger nasta mandag pa sondagar
            $dow = (int)date('N'); // 1=man..7=son
            $weekStart = date('Y-m-d 00:00:00', strtotime('-' . ($dow - 1) . ' days'));
            $weekStartDt = new \DateTime($weekStart, new \DateTimeZone('Europe/Stockholm'));
            $hoursThisWeek = max(1, ($nowDt->getTimestamp() - $weekStartDt->getTimestamp()) / 3600);
            $countWeek = $this->countIbcBetween($weekStart, $now);
            $avgWeek = round($countWeek / $hoursThisWeek, 1);

            // Måltal
            $target = $this->getTargetValue();

            // Måltal-status
            $ratio = $target > 0 ? ($currentRate / $target) : 1;
            if ($ratio >= 0.9) {
                $targetStatus = 'green';
            } elseif ($ratio >= 0.7) {
                $targetStatus = 'yellow';
            } else {
                $targetStatus = 'red';
            }

            // Alert: kolla om takten har varit under 70% av mål i >15 min
            // Vi kollar senaste 15 min
            $fifteenMinAgo = date('Y-m-d H:i:s', strtotime('-' . self::ALERT_THRESHOLD_MINUTES . ' minutes'));
            $count15m = $this->countIbcBetween($fifteenMinAgo, $now);
            $rate15m = $count15m * (60 / self::ALERT_THRESHOLD_MINUTES); // Extrapolera till per timme
            $alertActive = ($target > 0 && $rate15m < ($target * 0.7));

            $this->sendSuccess([
                'current_rate'    => $currentRate,
                'current_count'   => $currentCount,
                'prev_rate'       => $prevRate,
                'trend'           => $trend,
                'diff'            => round($diff, 1),
                'avg_4h'          => $avg4h,
                'avg_today'       => $avgToday,
                'avg_week'        => $avgWeek,
                'count_today'     => $countToday,
                'target'          => $target,
                'target_status'   => $targetStatus,
                'target_ratio'    => round($ratio * 100, 1),
                'alert_active'    => $alertActive,
                'alert_message'   => $alertActive
                    ? 'Takten har legat under 70% av maltal i mer an ' . self::ALERT_THRESHOLD_MINUTES . ' minuter'
                    : null,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsTaktController::getCurrentRate: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta aktuell takt', 500);
        }
    }

    // ================================================================
    // run=hourly-history
    // ================================================================

    private function getHourlyHistory(): void {
        try {
            $now = date('Y-m-d H:i:s');
            $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));

            $stmt = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(datum, '%Y-%m-%d %H:00:00') AS timme,
                    COUNT(*) AS ibc_count
                FROM rebotling_ibc
                WHERE datum BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(datum, '%Y-%m-%d %H:00:00')
                ORDER BY timme ASC
            ");
            $stmt->execute([$twentyFourHoursAgo, $now]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Bygg komplett 24h-tidslinje (fyll i 0 för tomma timmar)
            $target = $this->getTargetValue();
            $hourlyMap = [];
            foreach ($rows as $row) {
                $hourlyMap[$row['timme']] = (int)$row['ibc_count'];
            }

            $history = [];
            for ($i = 24; $i >= 0; $i--) {
                $hourStart = date('Y-m-d H:00:00', strtotime("-{$i} hours"));
                $count = $hourlyMap[$hourStart] ?? 0;

                // Status baserat på måltal
                $ratio = $target > 0 ? ($count / $target) : 1;
                if ($ratio >= 0.9) {
                    $status = 'green';
                } elseif ($ratio >= 0.7) {
                    $status = 'yellow';
                } else {
                    $status = 'red';
                }

                $history[] = [
                    'hour'       => $hourStart,
                    'hour_label' => date('H:00', strtotime($hourStart)),
                    'ibc_count'  => $count,
                    'rate'       => (float)$count,
                    'target'     => $target,
                    'status'     => $status,
                ];
            }

            $this->sendSuccess([
                'history' => $history,
                'target'  => $target,
            ]);

        } catch (\Exception $e) {
            error_log('ProduktionsTaktController::getHourlyHistory: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta timhistorik', 500);
        }
    }

    // ================================================================
    // run=get-target
    // ================================================================

    private function getTarget(): void {
        try {
            $target = $this->getTargetValue();
            $this->sendSuccess(['target' => $target]);
        } catch (\Exception $e) {
            error_log('ProduktionsTaktController::getTarget: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta maltal', 500);
        }
    }

    // ================================================================
    // run=set-target (POST, admin only)
    // ================================================================

    private function setTarget(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Endast POST tillatet', 405);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start(['read_and_close' => true]);
        }

        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->sendError('Adminbehorighet kravs', 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $newTarget = isset($input['target']) ? (float)$input['target'] : null;

        if ($newTarget === null || $newTarget <= 0 || $newTarget > 100) {
            $this->sendError('Ogiltigt maltal (maste vara 1-100)');
            return;
        }

        try {
            // Skapa tabellen om den inte finns
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS produktionstakt_target (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    target_ibc_per_hour DECIMAL(6,1) NOT NULL,
                    set_by INT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $stmt = $this->pdo->prepare(
                "INSERT INTO produktionstakt_target (target_ibc_per_hour, set_by) VALUES (?, ?)"
            );
            $stmt->execute([$newTarget, $_SESSION['user_id']]);

            $this->sendSuccess(['target' => $newTarget]);

        } catch (\Exception $e) {
            error_log('ProduktionsTaktController::setTarget: ' . $e->getMessage());
            $this->sendError('Kunde inte spara maltal', 500);
        }
    }
}
