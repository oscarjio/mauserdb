<?php
/**
 * FeedbackAnalysController.php
 * Analysvy för operatörsfeedback — avsedd för VD/admin.
 *
 * Tabeller: operator_feedback (id, operator_id, skiftraknare, datum, stämning, kommentar, skapad_at)
 *           operators (id, name)
 *
 * Endpoints via ?action=feedback-analys&run=XXX:
 *   run=feedback-list     → lista feedback med paginering, filter per operatör och period
 *   run=feedback-stats    → sammanfattning: totalt, snitt, trend, mest aktiv operatör, betygsfördelning
 *   run=feedback-trend    → mood/betyg per vecka (för trendgraf)
 *   run=operator-sentiment → per operatör: snitt-mood, antal, senaste feedback
 */
class FeedbackAnalysController {
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
            case 'feedback-list':     $this->getFeedbackList();     break;
            case 'feedback-stats':    $this->getFeedbackStats();    break;
            case 'feedback-trend':    $this->getFeedbackTrend();    break;
            case 'operator-sentiment': $this->getOperatorSentiment(); break;
            default:
                $this->sendError('Ogiltig run: ' . htmlspecialchars($run));
                break;
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

    /** Stämning 1-4 → text */
    private function stamningText(int $s): string {
        return ['', 'Dålig', 'Ok', 'Bra', 'Utmärkt'][$s] ?? 'Okänd';
    }

    /** Stämning 1-4 → färgklass */
    private function stamningColor(int $s): string {
        return ['', 'red', 'yellow', 'green', 'teal'][$s] ?? 'secondary';
    }

    // ================================================================
    // run=feedback-list
    // ================================================================

    private function getFeedbackList(): void {
        try {
            $days      = $this->getDays();
            $page      = max(1, intval($_GET['page'] ?? 1));
            $perPage   = max(5, min(100, intval($_GET['per_page'] ?? 20)));
            $operatorId = isset($_GET['operator_id']) && $_GET['operator_id'] !== ''
                ? intval($_GET['operator_id']) : null;

            $fromDate = date('Y-m-d', strtotime("-{$days} days"));
            $offset   = ($page - 1) * $perPage;

            $where  = ['f.datum >= :from_date'];
            $params = [':from_date' => $fromDate];

            if ($operatorId !== null) {
                $where[]              = 'f.operator_id = :op_id';
                $params[':op_id']     = $operatorId;
            }

            $whereStr = implode(' AND ', $where);

            // Totalt antal för paginering
            $stmtCount = $this->pdo->prepare(
                "SELECT COUNT(*) AS tot
                 FROM operator_feedback f
                 WHERE {$whereStr}"
            );
            $stmtCount->execute($params);
            $total = (int)($stmtCount->fetchColumn() ?? 0);

            // Hämta sidan
            $stmtList = $this->pdo->prepare(
                "SELECT f.id, f.datum, f.operator_id,
                        COALESCE(o.name, CONCAT('Operatör #', f.operator_id)) AS operator_namn,
                        f.stämning AS stamning,
                        f.kommentar, f.skapad_at
                 FROM operator_feedback f
                 LEFT JOIN operators o ON o.id = f.operator_id
                 WHERE {$whereStr}
                 ORDER BY f.skapad_at DESC
                 LIMIT :lim OFFSET :off"
            );
            foreach ($params as $k => $v) {
                $stmtList->bindValue($k, $v);
            }
            $stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
            $stmtList->execute();
            $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

            // Komplettera med text och färg
            foreach ($rows as &$row) {
                $s = (int)$row['stamning'];
                $row['id']           = (int)$row['id'];
                $row['operator_id']  = (int)$row['operator_id'];
                $row['stamning']     = $s;
                $row['stamning_text']  = $this->stamningText($s);
                $row['stamning_color'] = $this->stamningColor($s);
            }
            unset($row);

            $this->sendSuccess([
                'items'     => $rows,
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'pages'     => max(1, (int)ceil($total / $perPage)),
                'days'      => $days,
                'from_date' => $fromDate,
            ]);

        } catch (Exception $e) {
            error_log('FeedbackAnalysController::getFeedbackList: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta feedback-lista', 500);
        }
    }

    // ================================================================
    // run=feedback-stats
    // ================================================================

    private function getFeedbackStats(): void {
        try {
            $days     = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            // Aktuell period — totalt + snitt
            $stmtCurr = $this->pdo->prepare(
                "SELECT COUNT(*) AS total,
                        ROUND(AVG(stämning), 2) AS snitt_stamning,
                        MAX(datum) AS senaste_datum
                 FROM operator_feedback
                 WHERE datum >= :from"
            );
            $stmtCurr->execute([':from' => $fromDate]);
            $curr = $stmtCurr->fetch(PDO::FETCH_ASSOC);

            // Föregående lika lång period — för trend
            $prevFrom = date('Y-m-d', strtotime("-{$days} days", strtotime($fromDate)));
            $stmtPrev = $this->pdo->prepare(
                "SELECT COUNT(*) AS total,
                        ROUND(AVG(stämning), 2) AS snitt_stamning
                 FROM operator_feedback
                 WHERE datum >= :pfrom AND datum < :pto"
            );
            $stmtPrev->execute([':pfrom' => $prevFrom, ':pto' => $fromDate]);
            $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

            // Betygsfördelning 1-4
            $stmtDist = $this->pdo->prepare(
                "SELECT stämning AS stamning, COUNT(*) AS antal
                 FROM operator_feedback
                 WHERE datum >= :from
                 GROUP BY stämning
                 ORDER BY stämning"
            );
            $stmtDist->execute([':from' => $fromDate]);
            $distRows = $stmtDist->fetchAll(PDO::FETCH_ASSOC);

            $fordelning = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            foreach ($distRows as $dr) {
                $fordelning[(int)$dr['stamning']] = (int)$dr['antal'];
            }

            // Mest aktiv operatör
            $stmtTop = $this->pdo->prepare(
                "SELECT f.operator_id,
                        COALESCE(o.name, CONCAT('Operatör #', f.operator_id)) AS namn,
                        COUNT(*) AS antal
                 FROM operator_feedback f
                 LEFT JOIN operators o ON o.id = f.operator_id
                 WHERE f.datum >= :from
                 GROUP BY f.operator_id
                 ORDER BY antal DESC
                 LIMIT 1"
            );
            $stmtTop->execute([':from' => $fromDate]);
            $topOp = $stmtTop->fetch(PDO::FETCH_ASSOC);

            // Trend: bättre/sämre/stabil
            $currSnitt = $curr['snitt_stamning'] !== null ? (float)$curr['snitt_stamning'] : null;
            $prevSnitt = $prev['snitt_stamning'] !== null ? (float)$prev['snitt_stamning'] : null;
            $trend = 'stabil';
            if ($currSnitt !== null && $prevSnitt !== null) {
                $diff = $currSnitt - $prevSnitt;
                if ($diff > 0.05)       $trend = 'bättre';
                elseif ($diff < -0.05)  $trend = 'sämre';
            }

            $this->sendSuccess([
                'total'            => (int)$curr['total'],
                'snitt_stamning'   => $currSnitt,
                'prev_snitt'       => $prevSnitt,
                'trend'            => $trend,
                'senaste_datum'    => $curr['senaste_datum'],
                'fordelning'       => $fordelning,
                'mest_aktiv'       => $topOp ?: null,
                'days'             => $days,
                'from_date'        => $fromDate,
            ]);

        } catch (Exception $e) {
            error_log('FeedbackAnalysController::getFeedbackStats: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta statistik', 500);
        }
    }

    // ================================================================
    // run=feedback-trend — mood per vecka
    // ================================================================

    private function getFeedbackTrend(): void {
        try {
            $days     = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            $stmtTrend = $this->pdo->prepare(
                "SELECT
                    YEARWEEK(datum, 1) AS arsvecka,
                    MIN(datum)         AS vecka_start,
                    ROUND(AVG(stämning), 2) AS snitt_stamning,
                    COUNT(*)                AS antal
                 FROM operator_feedback
                 WHERE datum >= :from
                 GROUP BY YEARWEEK(datum, 1)
                 ORDER BY arsvecka ASC"
            );
            $stmtTrend->execute([':from' => $fromDate]);
            $rows = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

            $punkter = [];
            foreach ($rows as $r) {
                $punkter[] = [
                    'arsvecka'       => $r['arsvecka'],
                    'vecka_start'    => $r['vecka_start'],
                    'snitt_stamning' => (float)$r['snitt_stamning'],
                    'antal'          => (int)$r['antal'],
                ];
            }

            $stamningar = array_column($punkter, 'snitt_stamning');
            $avgTotal   = !empty($stamningar) ? round(array_sum($stamningar) / count($stamningar), 2) : null;

            $this->sendSuccess([
                'trend'     => $punkter,
                'avg_total' => $avgTotal,
                'days'      => $days,
                'from_date' => $fromDate,
            ]);

        } catch (Exception $e) {
            error_log('FeedbackAnalysController::getFeedbackTrend: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta trenddata', 500);
        }
    }

    // ================================================================
    // run=operator-sentiment — per operatör
    // ================================================================

    private function getOperatorSentiment(): void {
        try {
            $days     = $this->getDays();
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            $stmtSent = $this->pdo->prepare(
                "SELECT f.operator_id,
                        COALESCE(o.name, CONCAT('Operatör #', f.operator_id)) AS operator_namn,
                        COUNT(*)                         AS antal,
                        ROUND(AVG(f.stämning), 2)       AS snitt_stamning,
                        MAX(f.datum)                     AS senaste_datum,
                        (SELECT f2.kommentar
                         FROM operator_feedback f2
                         WHERE f2.operator_id = f.operator_id
                           AND f2.datum >= :from2
                         ORDER BY f2.skapad_at DESC LIMIT 1
                        ) AS senaste_kommentar
                 FROM operator_feedback f
                 LEFT JOIN operators o ON o.id = f.operator_id
                 WHERE f.datum >= :from
                 GROUP BY f.operator_id
                 ORDER BY snitt_stamning DESC"
            );
            $stmtSent->execute([':from' => $fromDate, ':from2' => $fromDate]);
            $rows = $stmtSent->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $r) {
                $snitt = (float)$r['snitt_stamning'];
                $result[] = [
                    'operator_id'       => (int)$r['operator_id'],
                    'operator_namn'     => $r['operator_namn'],
                    'antal'             => (int)$r['antal'],
                    'snitt_stamning'    => $snitt,
                    'senaste_datum'     => $r['senaste_datum'],
                    'senaste_kommentar' => $r['senaste_kommentar'],
                    'sentiment_color'   => $this->sentimentColor($snitt),
                    'sentiment_label'   => $this->sentimentLabel($snitt),
                ];
            }

            $this->sendSuccess([
                'operatorer' => $result,
                'days'       => $days,
                'from_date'  => $fromDate,
            ]);

        } catch (Exception $e) {
            error_log('FeedbackAnalysController::getOperatorSentiment: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörs-sentiment', 500);
        }
    }

    // ================================================================
    // UTIL
    // ================================================================

    private function sentimentColor(float $snitt): string {
        if ($snitt >= 3.0) return 'green';
        if ($snitt >= 2.0) return 'yellow';
        return 'red';
    }

    private function sentimentLabel(float $snitt): string {
        if ($snitt >= 3.5) return 'Utmärkt';
        if ($snitt >= 2.5) return 'Bra';
        if ($snitt >= 1.5) return 'Ok';
        return 'Låg';
    }
}
