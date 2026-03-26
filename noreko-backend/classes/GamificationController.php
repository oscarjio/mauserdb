<?php
/**
 * GamificationController.php
 * Gamification-system for operatorer med poang, badges, milstolpar och leaderboard.
 *
 * Endpoints via ?action=gamification&run=XXX:
 *   run=leaderboard   — ranking per period (dag|vecka|manad)
 *   run=badges         — badges for en specifik operator
 *   run=min-profil     — inloggad operators profil (poang, badges, rank, streak)
 *   run=overview       — VD:ns engagemangsoversikt
 *
 * Poangsystem:
 *   IBC producerade x kvalitetsfaktor (1 - kassationsrate) x bonus for laga stopp
 *
 * Badges:
 *   Centurion        — 100 IBC pa en dag
 *   Perfektionist    — 0% kassation pa ett skift
 *   Maratonlopare    — 5 dagar i rad utan franvaro
 *   Stoppjagare      — minst stopp pa veckan
 *   Teamspelare      — basta skiftet sammanlagt
 */
class GamificationController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Returnerar mandagens datum (Y-m-d) for aktuell vecka.
     * strtotime('monday this week') ar oppalitligt pa sondagar i PHP
     * (kan returnera NASTA mandag). Denna metod ger ratt resultat alla veckodagar.
     */
    private function getMondayThisWeek(): string {
        $dayOfWeek = (int)date('N'); // 1=man ... 7=son
        return date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days'));
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
            case 'leaderboard':  $this->leaderboard();  break;
            case 'badges':       $this->badges();       break;
            case 'min-profil':   $this->minProfil();    break;
            case 'overview':     $this->overview();     break;
            default:             $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8')); break;
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
        } catch (\PDOException $e) {
            error_log('GamificationController::tableExists: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hamta datumintervall baserat pa period-parameter.
     */
    private function getDateRange(): array {
        $period = trim($_GET['period'] ?? 'vecka');
        $today = date('Y-m-d');

        switch ($period) {
            case 'dag':
                return [$today, $today, 'dag'];
            case 'vecka':
                return [$this->getMondayThisWeek(), $today, 'vecka'];
            case 'manad':
                return [date('Y-m-01'), $today, 'manad'];
            default:
                return [$this->getMondayThisWeek(), $today, 'vecka'];
        }
    }

    /**
     * Hamta IBC-data per operator for en period.
     */
    private function getOperatorIbcData(string $from, string $to): array {
        $operators = [];

        // rebotling_ibc uses op1/op2/op3, not user_id
        // Aggregate IBC counts per operator from all 3 op columns
        try {
            $sql = "
                SELECT op_id, COALESCE(o.name, CONCAT('Operator ', op_id)) AS operator_namn,
                       SUM(cnt) AS total_ibc
                FROM (
                    SELECT op1 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from1 AND :to1 AND op1 IS NOT NULL AND op1 > 0
                    GROUP BY op1
                    UNION ALL
                    SELECT op2 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from2 AND :to2 AND op2 IS NOT NULL AND op2 > 0
                    GROUP BY op2
                    UNION ALL
                    SELECT op3 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
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
                    'ok_ibc'        => (int)$row['total_ibc'], // all counted as ok here
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::getOperatorIbcData(rebotling_ibc): ' . $e->getMessage());
        }

        // Fallback: rebotling_data
        if (empty($operators) && $this->tableExists('rebotling_data')) {
            try {
                $sql = "
                    SELECT
                        r.user_id,
                        COALESCE(u.username, CONCAT('Operator ', r.user_id)) AS operator_namn,
                        SUM(COALESCE(r.antal, 1)) AS total_ibc,
                        SUM(COALESCE(r.antal, 1)) AS ok_ibc
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
                    ];
                }
            } catch (\PDOException $e) {
                error_log('GamificationController::getOperatorIbcData(rebotling_data fallback): ' . $e->getMessage());
            }
        }

        return $operators;
    }

    /**
     * Hamta stopptid per operator for perioden.
     */
    private function getOperatorStopptid(string $from, string $to): array {
        $result = [];

        if (!$this->tableExists('stopporsak_registreringar')) {
            return $result;
        }

        try {
            $sql = "
                SELECT
                    sr.user_id,
                    SUM(
                        TIMESTAMPDIFF(SECOND,
                            sr.start_time,
                            COALESCE(sr.end_time, NOW())
                        )
                    ) AS total_stopp_sek,
                    COUNT(*) AS antal_stopp
                FROM stopporsak_registreringar sr
                WHERE sr.start_time >= :from
                  AND sr.start_time < :to
                  AND sr.user_id IS NOT NULL
                  AND sr.user_id > 0
                GROUP BY sr.user_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from . ' 00:00:00', ':to' => date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid = (int)$row['user_id'];
                $result[$uid] = [
                    'total_stopp_sek' => max(0, (int)$row['total_stopp_sek']),
                    'antal_stopp'     => (int)$row['antal_stopp'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::getOperatorStopptid: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Berakna poang for alla operatorer i perioden.
     * Formel: IBC * kvalitetsfaktor * stoppbonus-multiplikator
     */
    private function calcLeaderboard(string $from, string $to): array {
        $operators = $this->getOperatorIbcData($from, $to);
        $stoppData = $this->getOperatorStopptid($from, $to);

        if (empty($operators)) {
            return [];
        }

        $dagCount = max(1, (int)(new \DateTime($from))->diff(new \DateTime($to))->days + 1);
        $skiftSek = $dagCount * 8 * 3600;

        $result = [];
        foreach ($operators as $uid => $op) {
            $totalIbc = $op['total_ibc'];
            $okIbc = $op['ok_ibc'];

            // Kassationsrate
            $kassationsRate = $totalIbc > 0 ? (($totalIbc - $okIbc) / $totalIbc) : 0;
            $kvalitetsFaktor = 1 - $kassationsRate;

            // Stoppbonus-multiplikator
            $stopp = $stoppData[$uid] ?? null;
            $stopptidSek = $stopp ? $stopp['total_stopp_sek'] : 0;
            $antalStopp = $stopp ? $stopp['antal_stopp'] : 0;

            $stoppMultiplikator = 1.0;
            if ($antalStopp === 0 && $totalIbc > 0) {
                $stoppMultiplikator = 1.5; // 50% bonus for 0 stopp
            } elseif ($skiftSek > 0 && ($stopptidSek / $skiftSek) < 0.10) {
                $stoppMultiplikator = 1.2; // 20% bonus for laga stopp
            }

            // Total poang: IBC x kvalitetsfaktor x stoppbonus
            $totalPoang = round($totalIbc * $kvalitetsFaktor * $stoppMultiplikator, 1);

            $result[] = [
                'user_id'             => $uid,
                'operator_namn'       => $op['operator_namn'],
                'total_ibc'           => $totalIbc,
                'ok_ibc'              => $okIbc,
                'kassations_rate'     => round($kassationsRate * 100, 1),
                'kvalitets_faktor'    => round($kvalitetsFaktor, 3),
                'antal_stopp'         => $antalStopp,
                'stopptid_sek'        => $stopptidSek,
                'stopp_multiplikator' => $stoppMultiplikator,
                'total_poang'         => $totalPoang,
                'streak'              => 0,
            ];
        }

        // Sortera
        usort($result, fn($a, $b) => $b['total_poang'] <=> $a['total_poang']);

        // Rank
        foreach ($result as $i => &$r) {
            $r['rank'] = $i + 1;
        }
        unset($r);

        // Berakna streaks
        $this->calcStreaks($result);

        return $result;
    }

    /**
     * Berakna streak (dagar i rad med produktion).
     * Optimerad: en enda query for alla operatorer istallet for N+1.
     */
    private function calcStreaks(array &$ranking): void {
        if (empty($ranking)) return;

        $userIds = array_column($ranking, 'user_id');
        if (empty($userIds)) return;

        // Batch-hamta daglig IBC per operator senaste 30 dagar i EN query
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $allDagData = []; // op_id => [{dag, ibc_count}, ...]

        try {
            $sql = "
                SELECT op_id, dag, SUM(cnt) AS ibc_count FROM (
                    SELECT op1 AS op_id, DATE(datum) AS dag, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE op1 IN ({$placeholders}) AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY op1, DATE(datum)
                    UNION ALL
                    SELECT op2 AS op_id, DATE(datum) AS dag, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE op2 IN ({$placeholders}) AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY op2, DATE(datum)
                    UNION ALL
                    SELECT op3 AS op_id, DATE(datum) AS dag, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE op3 IN ({$placeholders}) AND datum >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY op3, DATE(datum)
                ) AS sub
                GROUP BY op_id, dag
                ORDER BY op_id, dag DESC
            ";
            $params = array_merge($userIds, $userIds, $userIds);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $opId = (int)$row['op_id'];
                $allDagData[$opId][] = [
                    'dag'       => $row['dag'],
                    'ibc_count' => (int)$row['ibc_count'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::calcStreaks: ' . $e->getMessage());
        }

        // Berakna streak per operator fran batch-data
        foreach ($ranking as &$op) {
            $streak = 0;
            $dagData = $allDagData[$op['user_id']] ?? [];
            $prevDate = null;

            foreach ($dagData as $d) {
                $currentDate = $d['dag'];
                if ($d['ibc_count'] <= 0) {
                    break;
                }
                if ($prevDate === null) {
                    $daysDiff = (int)(new \DateTime($currentDate))->diff(new \DateTime(date('Y-m-d')))->days;
                    if ($daysDiff > 1) {
                        break;
                    }
                    $streak = 1;
                } else {
                    $gap = (int)(new \DateTime($currentDate))->diff(new \DateTime($prevDate))->days;
                    if ($gap === 1) {
                        $streak++;
                    } else {
                        break;
                    }
                }
                $prevDate = $currentDate;
            }

            $op['streak'] = $streak;
        }
        unset($op);
    }

    /**
     * Hamta badges for en operator.
     */
    private function getBadges(int $userId): array {
        $badges = [];

        // ---- Centurion: 100 IBC pa en dag ----
        // rebotling_ibc uses op1/op2/op3, not user_id
        try {
            $sql = "
                SELECT dag, SUM(cnt) AS total_cnt FROM (
                    SELECT DATE(datum) AS dag, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE op1 = :uid1 AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY DATE(datum)
                    UNION ALL
                    SELECT DATE(datum) AS dag, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE op2 = :uid2 AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY DATE(datum)
                    UNION ALL
                    SELECT DATE(datum) AS dag, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE op3 = :uid3 AND datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY DATE(datum)
                ) AS sub
                GROUP BY dag HAVING total_cnt >= 100
                ORDER BY dag DESC LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $badges[] = [
                    'id'          => 'centurion',
                    'namn'        => 'Centurion',
                    'beskrivning' => '100 IBC pa en dag',
                    'ikon'        => 'fas fa-shield-alt',
                    'farg'        => '#FFD700',
                    'tilldelad'   => $row['dag'],
                    'antal'       => 1,
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::getBadges(centurion): ' . $e->getMessage());
        }

        // ---- Perfektionist: badge based on overall quality (simplified) ----
        // With cumulative ibc_ok/ibc_ej_ok, we check days with 0 ej_ok
        try {
            $sql = "
                SELECT dag FROM (
                    SELECT d AS dag,
                           SUM(max_ok) AS total_ok, SUM(max_ej_ok) AS total_ej_ok
                    FROM (
                        SELECT DATE(datum) AS d, skiftraknare,
                               MAX(ibc_ok) AS max_ok, MAX(ibc_ej_ok) AS max_ej_ok
                        FROM rebotling_ibc
                        WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                          AND (op1 = :uid1 OR op2 = :uid2 OR op3 = :uid3)
                        GROUP BY DATE(datum), skiftraknare
                    ) AS per_skift
                    GROUP BY d
                    HAVING total_ok >= 10 AND total_ej_ok = 0
                ) AS days_ok
                ORDER BY dag DESC LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $badges[] = [
                    'id'          => 'perfektionist',
                    'namn'        => 'Perfektionist',
                    'beskrivning' => '0% kassation pa ett skift',
                    'ikon'        => 'fas fa-gem',
                    'farg'        => '#4fd1c5',
                    'tilldelad'   => $row['dag'],
                    'antal'       => 1,
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::getBadges(perfektionist): ' . $e->getMessage());
        }

        // ---- Maratonlopare: 5 dagar i rad med produktion ----
        try {
            $sql = "
                SELECT dag FROM (
                    SELECT DATE(datum) AS dag FROM rebotling_ibc
                    WHERE (op1 = :uid1 OR op2 = :uid2 OR op3 = :uid3)
                      AND datum >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                    GROUP BY DATE(datum)
                ) AS sub
                ORDER BY dag DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $maxStreak = 0;
            $currentStreak = 0;
            $prevDate = null;

            foreach ($rows as $r) {
                $d = $r['dag'];
                if ($prevDate === null) {
                    $currentStreak = 1;
                } else {
                    $diff = (new \DateTime($d))->diff(new \DateTime($prevDate))->days;
                    if ($diff === 1) {
                        $currentStreak++;
                    } else {
                        $currentStreak = 1;
                    }
                }
                $maxStreak = max($maxStreak, $currentStreak);
                $prevDate = $d;
            }

            if ($maxStreak >= 5) {
                $badges[] = [
                    'id'          => 'maratonlopare',
                    'namn'        => 'Maratonlopare',
                    'beskrivning' => '5 dagar i rad utan franvaro',
                    'ikon'        => 'fas fa-running',
                    'farg'        => '#68d391',
                    'tilldelad'   => date('Y-m-d'),
                    'antal'       => 1,
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::getBadges(maratonlopare): ' . $e->getMessage());
        }

        // ---- Stoppjagare: minst stopp denna vecka ----
        if ($this->tableExists('stopporsak_registreringar')) {
            try {
                $mondayThisWeek = $this->getMondayThisWeek();
                $today = date('Y-m-d');

                $sql = "
                    SELECT sr.user_id, COUNT(*) AS antal_stopp
                    FROM stopporsak_registreringar sr
                    WHERE DATE(sr.start_time) BETWEEN :from AND :to
                      AND sr.user_id IS NOT NULL AND sr.user_id > 0
                    GROUP BY sr.user_id
                    ORDER BY antal_stopp ASC
                    LIMIT 1
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':from' => $mondayThisWeek, ':to' => $today]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($row && (int)$row['user_id'] === $userId) {
                    $badges[] = [
                        'id'          => 'stoppjagare',
                        'namn'        => 'Stoppjagare',
                        'beskrivning' => 'Minst stopp pa veckan',
                        'ikon'        => 'fas fa-crosshairs',
                        'farg'        => '#b794f4',
                        'tilldelad'   => $today,
                        'antal'       => 1,
                    ];
                }
            } catch (\PDOException $e) {
                error_log('GamificationController::getBadges(stoppjagare): ' . $e->getMessage());
            }
        }

        // ---- Teamspelare: basta operatoren sammanlagt (vecka) ----
        try {
            $mondayThisWeek = $this->getMondayThisWeek();
            $today = date('Y-m-d');

            $sql = "
                SELECT op_id, SUM(cnt) AS total_ibc FROM (
                    SELECT op1 AS op_id, COUNT(*) AS cnt FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from1 AND :to1 AND op1 IS NOT NULL AND op1 > 0 GROUP BY op1
                    UNION ALL
                    SELECT op2, COUNT(*) FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from2 AND :to2 AND op2 IS NOT NULL AND op2 > 0 GROUP BY op2
                    UNION ALL
                    SELECT op3, COUNT(*) FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN :from3 AND :to3 AND op3 IS NOT NULL AND op3 > 0 GROUP BY op3
                ) AS sub
                GROUP BY op_id ORDER BY total_ibc DESC LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':from1' => $mondayThisWeek, ':to1' => $today,
                ':from2' => $mondayThisWeek, ':to2' => $today,
                ':from3' => $mondayThisWeek, ':to3' => $today,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && (int)$row['op_id'] === $userId) {
                $badges[] = [
                    'id'          => 'teamspelare',
                    'namn'        => 'Teamspelare',
                    'beskrivning' => 'Basta skiftet sammanlagt',
                    'ikon'        => 'fas fa-users',
                    'farg'        => '#4299e1',
                    'tilldelad'   => $today,
                    'antal'       => 1,
                ];
            }
        } catch (\PDOException $e) {
            error_log('GamificationController::getBadges(teamspelare): ' . $e->getMessage());
        }

        return $badges;
    }

    /**
     * Alla mojliga badges (for att visa olasta ocksa).
     */
    private function getAllPossibleBadges(): array {
        return [
            [
                'id'          => 'centurion',
                'namn'        => 'Centurion',
                'beskrivning' => '100 IBC pa en dag',
                'ikon'        => 'fas fa-shield-alt',
                'farg'        => '#FFD700',
            ],
            [
                'id'          => 'perfektionist',
                'namn'        => 'Perfektionist',
                'beskrivning' => '0% kassation pa ett skift',
                'ikon'        => 'fas fa-gem',
                'farg'        => '#4fd1c5',
            ],
            [
                'id'          => 'maratonlopare',
                'namn'        => 'Maratonlopare',
                'beskrivning' => '5 dagar i rad utan franvaro',
                'ikon'        => 'fas fa-running',
                'farg'        => '#68d391',
            ],
            [
                'id'          => 'stoppjagare',
                'namn'        => 'Stoppjagare',
                'beskrivning' => 'Minst stopp pa veckan',
                'ikon'        => 'fas fa-crosshairs',
                'farg'        => '#b794f4',
            ],
            [
                'id'          => 'teamspelare',
                'namn'        => 'Teamspelare',
                'beskrivning' => 'Basta skiftet sammanlagt',
                'ikon'        => 'fas fa-users',
                'farg'        => '#4299e1',
            ],
        ];
    }

    // ================================================================
    // run=leaderboard
    // ================================================================

    private function leaderboard(): void {
        try {
            [$from, $to, $period] = $this->getDateRange();
            $leaderboard = $this->calcLeaderboard($from, $to);

            $this->sendSuccess([
                'leaderboard' => $leaderboard,
                'period'      => $period,
                'from_date'   => $from,
                'to_date'     => $to,
            ]);
        } catch (\Exception $e) {
            error_log('GamificationController::leaderboard: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta leaderboard', 500);
        }
    }

    // ================================================================
    // run=badges
    // ================================================================

    private function badges(): void {
        try {
            $operatorId = (int)($_GET['operator_id'] ?? 0);
            if ($operatorId <= 0) {
                $operatorId = (int)($_SESSION['user_id'] ?? 0);
            }
            if ($operatorId <= 0) {
                $this->sendError('operator_id kravs');
                return;
            }

            $earned = $this->getBadges($operatorId);
            $allBadges = $this->getAllPossibleBadges();

            $earnedIds = array_column($earned, 'id');
            $badgesWithStatus = [];
            foreach ($allBadges as $b) {
                $isEarned = in_array($b['id'], $earnedIds, true);
                $earnedData = null;
                if ($isEarned) {
                    foreach ($earned as $e) {
                        if ($e['id'] === $b['id']) {
                            $earnedData = $e;
                            break;
                        }
                    }
                }
                $badgesWithStatus[] = [
                    'id'          => $b['id'],
                    'namn'        => $b['namn'],
                    'beskrivning' => $b['beskrivning'],
                    'ikon'        => $b['ikon'],
                    'farg'        => $b['farg'],
                    'uppnadd'     => $isEarned,
                    'tilldelad'   => $earnedData['tilldelad'] ?? null,
                ];
            }

            $this->sendSuccess([
                'operator_id' => $operatorId,
                'badges'      => $badgesWithStatus,
                'antal_badges' => count($earned),
                'total_badges' => count($allBadges),
            ]);
        } catch (\Exception $e) {
            error_log('GamificationController::badges: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta badges', 500);
        }
    }

    // ================================================================
    // run=min-profil
    // ================================================================

    private function minProfil(): void {
        try {
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                $this->sendError('Inloggning kravs', 401);
                return;
            }

            // Hamta operatorsnamn
            $operatorNamn = 'Okand';
            try {
                $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) $operatorNamn = $row['username'];
            } catch (\PDOException $e) {
                error_log('GamificationController::minProfil(username): ' . $e->getMessage());
            }

            // Hamta rank fran vecko-leaderboard
            $from = $this->getMondayThisWeek();
            $to = date('Y-m-d');
            $leaderboard = $this->calcLeaderboard($from, $to);

            $myRank = null;
            $myPoang = 0;
            $myIbc = 0;
            $myStreak = 0;
            $totalOperatorer = count($leaderboard);
            foreach ($leaderboard as $entry) {
                if ($entry['user_id'] === $userId) {
                    $myRank = $entry['rank'];
                    $myPoang = $entry['total_poang'];
                    $myIbc = $entry['total_ibc'];
                    $myStreak = $entry['streak'];
                    break;
                }
            }

            // Badges
            $badges = $this->getBadges($userId);

            // Milstolpar
            $milstolpar = $this->getMilstolpar($userId);

            $this->sendSuccess([
                'user_id'          => $userId,
                'operator_namn'    => $operatorNamn,
                'rank'             => $myRank,
                'total_operatorer' => $totalOperatorer,
                'total_poang'      => $myPoang,
                'total_ibc'        => $myIbc,
                'streak'           => $myStreak,
                'badges'           => $badges,
                'antal_badges'     => count($badges),
                'milstolpar'       => $milstolpar,
                'period'           => 'vecka',
            ]);
        } catch (\Exception $e) {
            error_log('GamificationController::minProfil: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta profil', 500);
        }
    }

    /**
     * Milstolpar for en operator (total IBC-progression).
     */
    private function getMilstolpar(int $userId): array {
        $milstolpar = [
            ['namn' => 'Nyborjare',    'krav' => 100,  'ikon' => 'fas fa-seedling',   'farg' => '#68d391'],
            ['namn' => 'Erfaren',      'krav' => 500,  'ikon' => 'fas fa-hammer',     'farg' => '#4299e1'],
            ['namn' => 'Expert',       'krav' => 1000, 'ikon' => 'fas fa-star',       'farg' => '#ecc94b'],
            ['namn' => 'Master',       'krav' => 2500, 'ikon' => 'fas fa-crown',      'farg' => '#FFD700'],
            ['namn' => 'Legend',       'krav' => 5000, 'ikon' => 'fas fa-dragon',     'farg' => '#b794f4'],
            ['namn' => 'Mytisk',       'krav' => 10000,'ikon' => 'fas fa-meteor',     'farg' => '#fc8181'],
        ];

        $totalIbc = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(cnt) AS total FROM (
                    SELECT COUNT(*) AS cnt FROM rebotling_ibc WHERE op1 = ?
                    UNION ALL
                    SELECT COUNT(*) FROM rebotling_ibc WHERE op2 = ?
                    UNION ALL
                    SELECT COUNT(*) FROM rebotling_ibc WHERE op3 = ?
                ) AS sub
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $totalIbc = (int)($stmt->fetchColumn() ?? 0);
        } catch (\PDOException $e) {
            error_log('GamificationController::getMilstolpar: ' . $e->getMessage());
        }

        $result = [];
        foreach ($milstolpar as $m) {
            $uppnadd = $totalIbc >= $m['krav'];
            $progress = $m['krav'] > 0 ? min(100, round(($totalIbc / $m['krav']) * 100, 1)) : 0;
            $result[] = [
                'namn'     => $m['namn'],
                'krav'     => $m['krav'],
                'ikon'     => $m['ikon'],
                'farg'     => $m['farg'],
                'uppnadd'  => $uppnadd,
                'progress' => $progress,
                'nuvarande'=> $totalIbc,
            ];
        }

        return $result;
    }

    // ================================================================
    // run=overview (VD-vy)
    // ================================================================

    private function overview(): void {
        try {
            $from = $this->getMondayThisWeek();
            $to = date('Y-m-d');
            $leaderboard = $this->calcLeaderboard($from, $to);

            $totalOperatorer = count($leaderboard);
            $totalPoang = array_sum(array_column($leaderboard, 'total_poang'));
            $totalIbc = array_sum(array_column($leaderboard, 'total_ibc'));
            $avgPoang = $totalOperatorer > 0 ? round($totalPoang / $totalOperatorer, 1) : 0;

            // Badge-statistik — estimera baserat pa top 3 for att undvika N+1
            // (getBadges gor flera DB-queries per operator, alltfor dyrt for alla)
            $badgeCount = 0;
            $badgeSample = array_slice($leaderboard, 0, 3);
            foreach ($badgeSample as $op) {
                $badges = $this->getBadges($op['user_id']);
                $badgeCount += count($badges);
            }
            // Extrapolera for resten
            if (count($badgeSample) > 0 && $totalOperatorer > count($badgeSample)) {
                $avgBadges = $badgeCount / count($badgeSample);
                $badgeCount = (int)round($avgBadges * $totalOperatorer);
            }

            // Streak-statistik
            $streaks = array_column($leaderboard, 'streak');
            $avgStreak = count($streaks) > 0 ? round(array_sum($streaks) / count($streaks), 1) : 0;
            $maxStreak = count($streaks) > 0 ? max($streaks) : 0;

            $this->sendSuccess([
                'total_operatorer'     => $totalOperatorer,
                'total_poang'          => round($totalPoang, 1),
                'total_ibc'            => $totalIbc,
                'avg_poang'            => $avgPoang,
                'total_badges_utdelade' => $badgeCount,
                'avg_streak'           => $avgStreak,
                'max_streak'           => $maxStreak,
                'top3'                 => array_slice($leaderboard, 0, 3),
                'period'               => 'vecka',
                'from_date'            => $from,
                'to_date'              => $to,
            ]);
        } catch (\Exception $e) {
            error_log('GamificationController::overview: ' . $e->getMessage());
            $this->sendError('Kunde inte hamta oversikt', 500);
        }
    }
}
