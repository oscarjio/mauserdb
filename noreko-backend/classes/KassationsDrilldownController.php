<?php
/**
 * KassationsDrilldownController.php
 * Kassationsorsak-drill-down — hierarkisk vy av kassationsorsaker
 *
 * Endpoints via ?action=kassations-drilldown&run=XXX:
 *   - run=overview        -> totalt kasserade, kassationsgrad, trend, per-orsak-aggregering
 *   - run=reason-detail   -> enskilda kassationshändelser för en viss orsak (reason=X)
 *   - run=trend           -> daglig kassationstrend
 *
 * Tabeller:
 *   kassationsregistrering  (id, datum, skiftraknare, orsak_id, antal, kommentar, registrerad_av, created_at)
 *   kassationsorsak_typer   (id, namn, aktiv)
 *   rebotling_ibc           (ibc_ok, ibc_ej_ok, datum, skiftraknare, op1, op2, op3)
 */
class KassationsDrilldownController {
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
            case 'overview':       $this->getOverview();      break;
            case 'reason-detail':  $this->getReasonDetail();  break;
            case 'trend':          $this->getTrend();         break;
            default:               $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDays(): int {
        return max(1, min(365, intval($_GET['days'] ?? 30)));
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
     * Hämta totalt producerade IBC (ok + ej ok) från rebotling_ibc för perioden.
     * Aggregeringslogik: MAX() per skifträknare (kumulativa PLC-värden), sedan SUM().
     */
    private function getTotalProducerade(string $fromDate, string $toDate): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(shift_ok), 0) AS totalt_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS totalt_ej_ok
                FROM (
                    SELECT skiftraknare,
                           MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
                           MAX(COALESCE(ibc_ej_ok, 0)) AS shift_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from_date AND :to_date
                      AND skiftraknare IS NOT NULL
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_shift
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $row = $stmt->fetch();
            return [
                'ok'     => (int)($row['totalt_ok'] ?? 0),
                'ej_ok'  => (int)($row['totalt_ej_ok'] ?? 0),
            ];
        } catch (\PDOException $e) {
            error_log('KassationsDrilldownController::getTotalProducerade: ' . $e->getMessage());
            return ['ok' => 0, 'ej_ok' => 0];
        }
    }

    // ================================================================
    // run=overview — KPI:er + per-orsak-aggregering
    // ================================================================

    private function getOverview(): void {
        $days = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        // Föregående period (lika lång)
        $prevTo   = date('Y-m-d', strtotime("-" . ($days + 1) . " days"));
        $prevFrom = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        // Nuvarande period
        $current = $this->getTotalProducerade($fromDate, $toDate);
        $totalKasserade = $current['ej_ok'];
        $totalProducerade = $current['ok'] + $current['ej_ok'];
        $kassationsgrad = $totalProducerade > 0 ? round(($totalKasserade / $totalProducerade) * 100, 2) : 0;

        // Föregående period
        $prev = $this->getTotalProducerade($prevFrom, $prevTo);
        $prevKasserade = $prev['ej_ok'];
        $prevProducerade = $prev['ok'] + $prev['ej_ok'];
        $prevKassationsgrad = $prevProducerade > 0 ? round(($prevKasserade / $prevProducerade) * 100, 2) : 0;

        // Trend
        $trendDiff = $kassationsgrad - $prevKassationsgrad;
        if ($trendDiff < -0.01) {
            $trendDirection = 'down';   // bättre
        } elseif ($trendDiff > 0.01) {
            $trendDirection = 'up';     // sämre
        } else {
            $trendDirection = 'flat';
        }

        // Per orsak — från kassationsregistrering
        $reasons = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(kt.namn, 'Okänd') AS reason,
                    kt.id AS reason_id,
                    SUM(kr.antal) AS total_antal,
                    COUNT(*) AS registreringar
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE DATE(kr.datum) BETWEEN :from_date AND :to_date
                GROUP BY kt.id, kt.namn
                ORDER BY total_antal DESC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll();

            $sumAntal = array_sum(array_column($rows, 'total_antal'));
            foreach ($rows as $row) {
                $reasons[] = [
                    'reason'        => $row['reason'],
                    'reason_id'     => (int)$row['reason_id'],
                    'antal'         => (int)$row['total_antal'],
                    'registreringar' => (int)$row['registreringar'],
                    'andel'         => $sumAntal > 0 ? round(($row['total_antal'] / $sumAntal) * 100, 1) : 0,
                ];
            }
        } catch (\PDOException $e) {
            error_log('KassationsDrilldownController::getOverview reasons: ' . $e->getMessage());
        }

        // Vanligaste orsaken
        $topReason = !empty($reasons) ? $reasons[0]['reason'] : null;

        $this->sendSuccess([
            'days'              => $days,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
            'total_kasserade'   => $totalKasserade,
            'total_producerade' => $totalProducerade,
            'kassationsgrad'    => $kassationsgrad,
            'prev_kassationsgrad' => $prevKassationsgrad,
            'trend_diff'        => round($trendDiff, 2),
            'trend_direction'   => $trendDirection,
            'top_reason'        => $topReason,
            'reasons'           => $reasons,
        ]);
    }

    // ================================================================
    // run=reason-detail — Enskilda händelser för en specifik orsak
    // ================================================================

    private function getReasonDetail(): void {
        $days = $this->getDays();
        $reasonId = intval($_GET['reason'] ?? 0);

        if ($reasonId <= 0) {
            $this->sendError('Orsak (reason) krävs');
            return;
        }

        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    kr.id,
                    kr.datum,
                    kr.skiftraknare,
                    kr.antal,
                    kr.kommentar,
                    kr.registrerad_av,
                    kr.created_at,
                    COALESCE(kt.namn, 'Okänd') AS reason
                FROM kassationsregistrering kr
                LEFT JOIN kassationsorsak_typer kt ON kr.orsak_id = kt.id
                WHERE kr.orsak_id = :reason_id
                  AND DATE(kr.datum) BETWEEN :from_date AND :to_date
                ORDER BY kr.datum DESC, kr.created_at DESC
            ");
            $stmt->execute([
                ':reason_id' => $reasonId,
                ':from_date' => $fromDate,
                ':to_date'   => $toDate,
            ]);
            $events = $stmt->fetchAll();

            // Formatera
            $formatted = [];
            foreach ($events as $ev) {
                $formatted[] = [
                    'id'              => (int)$ev['id'],
                    'datum'           => $ev['datum'],
                    'skiftraknare'    => $ev['skiftraknare'],
                    'antal'           => (int)$ev['antal'],
                    'kommentar'       => $ev['kommentar'],
                    'registrerad_av'  => $ev['registrerad_av'],
                    'created_at'      => $ev['created_at'],
                    'reason'          => $ev['reason'],
                ];
            }

            $this->sendSuccess([
                'days'       => $days,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
                'reason_id'  => $reasonId,
                'events'     => $formatted,
                'total'      => count($formatted),
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsDrilldownController::getReasonDetail: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }

    // ================================================================
    // run=trend — Daglig kassationstrend
    // ================================================================

    private function getTrend(): void {
        $days = $this->getDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    COALESCE(SUM(shift_ok), 0) AS dag_ok,
                    COALESCE(SUM(shift_ej_ok), 0) AS dag_ej_ok
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(COALESCE(ibc_ok, 0)) AS shift_ok,
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
            $rows = $stmt->fetchAll();

            $trendData = [];
            foreach ($rows as $row) {
                $total = (int)$row['dag_ok'] + (int)$row['dag_ej_ok'];
                $ejOk  = (int)$row['dag_ej_ok'];
                $trendData[] = [
                    'date'            => $row['dag'],
                    'kasserade'       => $ejOk,
                    'producerade'     => $total,
                    'kassationsgrad'  => $total > 0 ? round(($ejOk / $total) * 100, 2) : 0,
                ];
            }

            $this->sendSuccess([
                'days'      => $days,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'trend'     => $trendData,
            ]);
        } catch (\PDOException $e) {
            error_log('KassationsDrilldownController::getTrend: ' . $e->getMessage());
            $this->sendError('Databasfel');
        }
    }
}
