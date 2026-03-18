<?php
/**
 * StopporsakOperatorController.php
 * Stopporsak per operatör — analys av vilka operatörer som har mest stopp,
 * vilka stopporsaker de har, och identifiering av utbildningsbehov.
 *
 * Endpoints via ?action=stopporsak-operator&run=XXX:
 *
 *   run=overview&period=7|30|90
 *       Alla operatörer med total stopptid, antal stopp, teamsnitt.
 *       Flaggar operatörer med >150% av teamsnitt som "hög".
 *       Returnerar: { operatorer: [...], team_snitt_min, team_snitt_stopp, period }
 *
 *   run=operator-detail&operator_id=X&period=7|30|90
 *       En operatörs stopporsaker i detalj.
 *       Returnerar: { operator, orsaker: [{orsak, antal, total_min, senaste}] }
 *
 *   run=reasons-summary&period=7|30|90
 *       Aggregerade stopporsaker (för pie/donut-chart).
 *       Returnerar: { orsaker: [{orsak, antal, total_min, andel_pct}] }
 *
 * Auth: session_id krävs (401 om ej inloggad).
 *
 * Tabeller: stopporsak_registreringar, stopporsak_kategorier, users
 *           stoppage_log, stoppage_reasons
 */
class StopporsakOperatorController {
    private $pdo;

    /** Tröskel för "hög" stopptid: >150% av teamsnitt */
    private const HOG_TRÖSKEL_PCT = 150.0;

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
            case 'overview':        $this->getOverview();       break;
            case 'operator-detail': $this->getOperatorDetail(); break;
            case 'reasons-summary': $this->getReasonsSummary(); break;
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

    private function getPeriod(): int {
        $p = (int)($_GET['period'] ?? 30);
        if (!in_array($p, [7, 30, 90], true)) return 30;
        return $p;
    }

    private function getDatumIntervall(int $period): array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$period} days"));
        return [$fromDate, $toDate];
    }

    /**
     * Hämta stopptid och antal stopp per user_id från stopporsak_registreringar.
     * Returnerar: [user_id => ['total_min' => F, 'antal' => N, 'orsaker' => [orsak => [antal, min]]]]
     */
    private function hämtaStoppPerUser(string $fromDate, string $toDate): array {
        $result = [];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    sr.user_id,
                    COALESCE(u.username, CONCAT('Operatör #', sr.user_id)) AS operatör_namn,
                    sk.namn AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(
                        CASE WHEN sr.end_time IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, sr.start_time, sr.end_time)
                             ELSE 0 END
                    ), 0) AS total_min
                 FROM stopporsak_registreringar sr
                 JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                 LEFT JOIN users u ON sr.user_id = u.id
                 WHERE DATE(sr.start_time) BETWEEN ? AND ?
                   AND sr.user_id IS NOT NULL
                 GROUP BY sr.user_id, u.username, sk.namn
                 ORDER BY sr.user_id, total_min DESC"
            );
            $stmt->execute([$fromDate, $toDate]);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid  = (int)$row['user_id'];
                $namn = $row['operatör_namn'];
                $orsak = $row['orsak'];

                if (!isset($result[$uid])) {
                    $result[$uid] = [
                        'user_id'   => $uid,
                        'namn'      => $namn,
                        'total_min' => 0.0,
                        'antal'     => 0,
                        'orsaker'   => [],
                    ];
                }

                $result[$uid]['total_min'] += (float)$row['total_min'];
                $result[$uid]['antal']     += (int)$row['antal'];
                $result[$uid]['orsaker'][$orsak] = [
                    'antal'     => (int)$row['antal'],
                    'total_min' => (float)$row['total_min'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('StopporsakOperatorController::hämtaStoppPerUser (stopporsak_registreringar): ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Hämta stopptid från stoppage_log per operator-namn (username-kolumn saknas — använd user_id via session).
     * Slår ihop med users-tabellen för att matcha user_id.
     */
    private function hämtaStoppPerUserFrånStoppageLog(string $fromDate, string $toDate): array {
        $result = [];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    sl.user_id,
                    COALESCE(u.username, CONCAT('Operatör #', sl.user_id)) AS operatör_namn,
                    COALESCE(sr.name, 'Okänd') AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(sl.duration_minutes), 0) AS total_min
                 FROM stoppage_log sl
                 LEFT JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 LEFT JOIN users u ON sl.user_id = u.id
                 WHERE DATE(sl.created_at) BETWEEN ? AND ?
                   AND sl.user_id IS NOT NULL
                 GROUP BY sl.user_id, u.username, sr.name
                 ORDER BY sl.user_id, total_min DESC"
            );
            $stmt->execute([$fromDate, $toDate]);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid  = (int)$row['user_id'];
                $namn = $row['operatör_namn'];
                $orsak = $row['orsak'];

                if (!isset($result[$uid])) {
                    $result[$uid] = [
                        'user_id'   => $uid,
                        'namn'      => $namn,
                        'total_min' => 0.0,
                        'antal'     => 0,
                        'orsaker'   => [],
                    ];
                }

                $result[$uid]['total_min'] += (float)$row['total_min'];
                $result[$uid]['antal']     += (int)$row['antal'];

                if (!isset($result[$uid]['orsaker'][$orsak])) {
                    $result[$uid]['orsaker'][$orsak] = ['antal' => 0, 'total_min' => 0.0];
                }
                $result[$uid]['orsaker'][$orsak]['antal']     += (int)$row['antal'];
                $result[$uid]['orsaker'][$orsak]['total_min'] += (float)$row['total_min'];
            }
        } catch (\PDOException $e) {
            error_log('StopporsakOperatorController::hämtaStoppPerUserFrånStoppageLog: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Slår ihop stoppdata från båda källorna per user_id.
     */
    private function hämtaKombineradStoppdata(string $fromDate, string $toDate): array {
        $källa1 = $this->hämtaStoppPerUser($fromDate, $toDate);
        $källa2 = $this->hämtaStoppPerUserFrånStoppageLog($fromDate, $toDate);

        // Slå ihop
        foreach ($källa2 as $uid => $data) {
            if (!isset($källa1[$uid])) {
                $källa1[$uid] = $data;
            } else {
                $källa1[$uid]['total_min'] += $data['total_min'];
                $källa1[$uid]['antal']     += $data['antal'];
                foreach ($data['orsaker'] as $orsak => $vals) {
                    if (!isset($källa1[$uid]['orsaker'][$orsak])) {
                        $källa1[$uid]['orsaker'][$orsak] = $vals;
                    } else {
                        $källa1[$uid]['orsaker'][$orsak]['antal']     += $vals['antal'];
                        $källa1[$uid]['orsaker'][$orsak]['total_min'] += $vals['total_min'];
                    }
                }
            }
        }

        return $källa1;
    }

    // ================================================================
    // run=overview
    // ================================================================

    private function getOverview(): void {
        $period = $this->getPeriod();
        [$fromDate, $toDate] = $this->getDatumIntervall($period);

        try {
            $stoppdata = $this->hämtaKombineradStoppdata($fromDate, $toDate);

            if (empty($stoppdata)) {
                $this->sendSuccess([
                    'period'           => $period,
                    'from_date'        => $fromDate,
                    'to_date'          => $toDate,
                    'operatorer'       => [],
                    'team_snitt_min'   => 0.0,
                    'team_snitt_stopp' => 0.0,
                    'total_stopp'      => 0,
                    'total_min'        => 0.0,
                ]);
                return;
            }

            // Räkna ut teamsnitt
            $totaltMin   = array_sum(array_column($stoppdata, 'total_min'));
            $totaltAntal = array_sum(array_column($stoppdata, 'antal'));
            $antalOps    = count($stoppdata);
            $snittMin    = $antalOps > 0 ? $totaltMin / $antalOps : 0.0;
            $snittAntal  = $antalOps > 0 ? $totaltAntal / $antalOps : 0.0;

            // Bygg operatörslista, sortera efter total stopptid (desc)
            $operatorer = [];
            foreach ($stoppdata as $uid => $data) {
                $totMin  = round($data['total_min'], 1);
                $vanligastOrsak = null;
                $maxAntal = 0;
                foreach ($data['orsaker'] as $orsak => $vals) {
                    if ($vals['antal'] > $maxAntal) {
                        $maxAntal = $vals['antal'];
                        $vanligastOrsak = $orsak;
                    }
                }

                $hogFlag = $snittMin > 0
                    ? ($totMin / $snittMin * 100) >= self::HOG_TRÖSKEL_PCT
                    : false;

                $operatorer[] = [
                    'user_id'          => $uid,
                    'namn'             => $data['namn'],
                    'total_min'        => $totMin,
                    'antal_stopp'      => (int)$data['antal'],
                    'vanligast_orsak'  => $vanligastOrsak,
                    'hog_stopptid'     => $hogFlag,
                    'pct_av_snitt'     => $snittMin > 0
                        ? round($totMin / $snittMin * 100, 1)
                        : 0.0,
                ];
            }

            // Sortera: mest stopptid först
            usort($operatorer, fn($a, $b) => $b['total_min'] <=> $a['total_min']);

            $this->sendSuccess([
                'period'           => $period,
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
                'operatorer'       => $operatorer,
                'team_snitt_min'   => round($snittMin, 1),
                'team_snitt_stopp' => round($snittAntal, 1),
                'total_stopp'      => (int)$totaltAntal,
                'total_min'        => round($totaltMin, 1),
            ]);

        } catch (\Exception $e) {
            error_log('StopporsakOperatorController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översiktsdata', 500);
        }
    }

    // ================================================================
    // run=operator-detail
    // ================================================================

    private function getOperatorDetail(): void {
        $period     = $this->getPeriod();
        $operatorId = (int)($_GET['operator_id'] ?? 0);

        if ($operatorId <= 0) {
            $this->sendError('operator_id krävs');
            return;
        }

        [$fromDate, $toDate] = $this->getDatumIntervall($period);

        $operatörNamn = null;
        $orsaker = [];

        // --- Källa 1: stopporsak_registreringar ---
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    COALESCE(u.username, CONCAT('Operatör #', sr.user_id)) AS namn,
                    sk.namn AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(
                        CASE WHEN sr.end_time IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, sr.start_time, sr.end_time)
                             ELSE 0 END
                    ), 0) AS total_min,
                    MAX(sr.start_time) AS senaste
                 FROM stopporsak_registreringar sr
                 JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                 LEFT JOIN users u ON sr.user_id = u.id
                 WHERE sr.user_id = ?
                   AND DATE(sr.start_time) BETWEEN ? AND ?
                 GROUP BY sk.namn
                 ORDER BY total_min DESC"
            );
            $stmt->execute([$operatorId, $fromDate, $toDate]);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if ($operatörNamn === null) {
                    $operatörNamn = $row['namn'];
                }
                $k = $row['orsak'];
                if (!isset($orsaker[$k])) {
                    $orsaker[$k] = ['antal' => 0, 'total_min' => 0.0, 'senaste' => null];
                }
                $orsaker[$k]['antal']     += (int)$row['antal'];
                $orsaker[$k]['total_min'] += (float)$row['total_min'];
                if ($orsaker[$k]['senaste'] === null || $row['senaste'] > $orsaker[$k]['senaste']) {
                    $orsaker[$k]['senaste'] = $row['senaste'];
                }
            }
        } catch (\PDOException $e) {
            error_log('StopporsakOperatorController::getOperatorDetail (reg): ' . $e->getMessage());
        }

        // --- Källa 2: stoppage_log ---
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    COALESCE(u.username, CONCAT('Operatör #', sl.user_id)) AS namn,
                    COALESCE(sr.name, 'Okänd') AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(sl.duration_minutes), 0) AS total_min,
                    MAX(sl.created_at) AS senaste
                 FROM stoppage_log sl
                 LEFT JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 LEFT JOIN users u ON sl.user_id = u.id
                 WHERE sl.user_id = ?
                   AND DATE(sl.created_at) BETWEEN ? AND ?
                 GROUP BY sr.name
                 ORDER BY total_min DESC"
            );
            $stmt->execute([$operatorId, $fromDate, $toDate]);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if ($operatörNamn === null) {
                    $operatörNamn = $row['namn'];
                }
                $k = $row['orsak'];
                if (!isset($orsaker[$k])) {
                    $orsaker[$k] = ['antal' => 0, 'total_min' => 0.0, 'senaste' => null];
                }
                $orsaker[$k]['antal']     += (int)$row['antal'];
                $orsaker[$k]['total_min'] += (float)$row['total_min'];
                if ($orsaker[$k]['senaste'] === null || $row['senaste'] > $orsaker[$k]['senaste']) {
                    $orsaker[$k]['senaste'] = $row['senaste'];
                }
            }
        } catch (\PDOException $e) {
            error_log('StopporsakOperatorController::getOperatorDetail (stoppage_log): ' . $e->getMessage());
        }

        // Om operatörnamn fortfarande saknas, hämta från users
        if ($operatörNamn === null) {
            try {
                $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$operatorId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $operatörNamn = $row ? $row['username'] : "Operatör #{$operatorId}";
            } catch (\PDOException $e) {
                error_log('StopporsakOperatorController::getOperatorDetail username: ' . $e->getMessage());
                $operatörNamn = "Operatör #{$operatorId}";
            }
        }

        // Bygg orsakslista, sortera på total_min desc
        $orsakLista = [];
        $totalMin   = 0.0;
        $totalAntal = 0;
        foreach ($orsaker as $orsak => $vals) {
            $orsakLista[] = [
                'orsak'     => $orsak,
                'antal'     => (int)$vals['antal'],
                'total_min' => round((float)$vals['total_min'], 1),
                'senaste'   => $vals['senaste'],
            ];
            $totalMin   += (float)$vals['total_min'];
            $totalAntal += (int)$vals['antal'];
        }
        usort($orsakLista, fn($a, $b) => $b['total_min'] <=> $a['total_min']);

        $this->sendSuccess([
            'operator_id'  => $operatorId,
            'operator_namn' => $operatörNamn,
            'period'       => $period,
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
            'orsaker'      => $orsakLista,
            'total_min'    => round($totalMin, 1),
            'total_antal'  => (int)$totalAntal,
        ]);
    }

    // ================================================================
    // run=reasons-summary
    // ================================================================

    private function getReasonsSummary(): void {
        $period = $this->getPeriod();
        [$fromDate, $toDate] = $this->getDatumIntervall($period);

        $orsaker = [];

        // --- Källa 1: stopporsak_registreringar ---
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    sk.namn AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(
                        CASE WHEN sr.end_time IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, sr.start_time, sr.end_time)
                             ELSE 0 END
                    ), 0) AS total_min
                 FROM stopporsak_registreringar sr
                 JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                 WHERE DATE(sr.start_time) BETWEEN ? AND ?
                 GROUP BY sk.namn
                 ORDER BY total_min DESC"
            );
            $stmt->execute([$fromDate, $toDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $k = $row['orsak'];
                if (!isset($orsaker[$k])) {
                    $orsaker[$k] = ['antal' => 0, 'total_min' => 0.0];
                }
                $orsaker[$k]['antal']     += (int)$row['antal'];
                $orsaker[$k]['total_min'] += (float)$row['total_min'];
            }
        } catch (\PDOException $e) {
            error_log('StopporsakOperatorController::getReasonsSummary (reg): ' . $e->getMessage());
        }

        // --- Källa 2: stoppage_log ---
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    COALESCE(sr.name, 'Okänd') AS orsak,
                    COUNT(*) AS antal,
                    COALESCE(SUM(sl.duration_minutes), 0) AS total_min
                 FROM stoppage_log sl
                 LEFT JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE DATE(sl.created_at) BETWEEN ? AND ?
                 GROUP BY sr.name
                 ORDER BY total_min DESC"
            );
            $stmt->execute([$fromDate, $toDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $k = $row['orsak'];
                if (!isset($orsaker[$k])) {
                    $orsaker[$k] = ['antal' => 0, 'total_min' => 0.0];
                }
                $orsaker[$k]['antal']     += (int)$row['antal'];
                $orsaker[$k]['total_min'] += (float)$row['total_min'];
            }
        } catch (\PDOException $e) {
            error_log('StopporsakOperatorController::getReasonsSummary (stoppage_log): ' . $e->getMessage());
        }

        // Bygg lista + beräkna andelar
        $totalMin = array_sum(array_column($orsaker, 'total_min'));
        $orsakLista = [];
        foreach ($orsaker as $orsak => $vals) {
            $orsakLista[] = [
                'orsak'     => $orsak,
                'antal'     => (int)$vals['antal'],
                'total_min' => round((float)$vals['total_min'], 1),
                'andel_pct' => $totalMin > 0
                    ? round((float)$vals['total_min'] / $totalMin * 100, 1)
                    : 0.0,
            ];
        }
        usort($orsakLista, fn($a, $b) => $b['total_min'] <=> $a['total_min']);

        $this->sendSuccess([
            'period'    => $period,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'orsaker'   => $orsakLista,
            'total_min' => round($totalMin, 1),
        ]);
    }
}
