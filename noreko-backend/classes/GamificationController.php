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
            default:             $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
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

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
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
                return [date('Y-m-d', strtotime('monday this week')), $today, 'vecka'];
            case 'manad':
                return [date('Y-m-01'), $today, 'manad'];
            default:
                return [date('Y-m-d', strtotime('monday this week')), $today, 'vecka'];
        }
    }

    /**
     * Hamta IBC-data per operator for en period.
     */
    private function getOperatorIbcData(string $from, string $to): array {
        $operators = [];

        try {
            $sql = "
                SELECT
                    COALESCE(ri.user_id, 0) AS user_id,
                    COALESCE(u.username, CONCAT('Operator ', ri.user_id)) AS operator_namn,
                    COUNT(*) AS total_ibc,
                    SUM(CASE WHEN ri.ok = 1 THEN 1 ELSE 0 END) AS ok_ibc
                FROM rebotling_ibc ri
                LEFT JOIN users u ON ri.user_id = u.id
                WHERE DATE(ri.datum) BETWEEN :from AND :to
                  AND ri.user_id IS NOT NULL
                  AND ri.user_id > 0
                GROUP BY ri.user_id, u.username
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
            // kolumnen user_id kanske saknas
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
                // ignorera
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
                  AND sr.start_time <= :to
                  AND sr.user_id IS NOT NULL
                  AND sr.user_id > 0
                GROUP BY sr.user_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid = (int)$row['user_id'];
                $result[$uid] = [
                    'total_stopp_sek' => max(0, (int)$row['total_stopp_sek']),
                    'antal_stopp'     => (int)$row['antal_stopp'],
                ];
            }
        } catch (\PDOException $e) {
            // Ignorera
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

        $dagCount = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
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
     */
    private function calcStreaks(array &$ranking): void {
        foreach ($ranking as &$op) {
            $streak = 0;
            try {
                $sql = "
                    SELECT DATE(datum) AS dag, COUNT(*) AS ibc_count
                    FROM rebotling_ibc
                    WHERE user_id = :uid
                      AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(datum)
                    ORDER BY dag DESC
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':uid' => $op['user_id']]);
                $dagData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($dagData as $d) {
                    if ((int)$d['ibc_count'] > 0) {
                        $streak++;
                    } else {
                        break;
                    }
                }
            } catch (\PDOException $e) {
                // Ignorera
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
        try {
            $sql = "
                SELECT DATE(datum) AS dag, COUNT(*) AS cnt
                FROM rebotling_ibc
                WHERE user_id = :uid
                GROUP BY DATE(datum)
                HAVING cnt >= 100
                ORDER BY dag DESC
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
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
        } catch (\PDOException $e) {}

        // ---- Perfektionist: 0% kassation pa ett skift (minst 10 IBC) ----
        try {
            $sql = "
                SELECT DATE(datum) AS dag, COUNT(*) AS total,
                       SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) AS ok_count
                FROM rebotling_ibc
                WHERE user_id = :uid
                GROUP BY DATE(datum)
                HAVING total >= 10 AND ok_count = total
                ORDER BY dag DESC
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
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
        } catch (\PDOException $e) {}

        // ---- Maratonlopare: 5 dagar i rad med produktion ----
        try {
            $sql = "
                SELECT DATE(datum) AS dag
                FROM rebotling_ibc
                WHERE user_id = :uid
                  AND DATE(datum) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                GROUP BY DATE(datum)
                ORDER BY dag DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $maxStreak = 0;
            $currentStreak = 0;
            $prevDate = null;

            foreach ($rows as $r) {
                $d = $r['dag'];
                if ($prevDate === null) {
                    $currentStreak = 1;
                } else {
                    $diff = (strtotime($prevDate) - strtotime($d)) / 86400;
                    if ($diff == 1) {
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
        } catch (\PDOException $e) {}

        // ---- Stoppjagare: minst stopp denna vecka ----
        if ($this->tableExists('stopporsak_registreringar')) {
            try {
                $mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
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
            } catch (\PDOException $e) {}
        }

        // ---- Teamspelare: basta skiftet sammanlagt (vecka) ----
        try {
            $mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
            $today = date('Y-m-d');

            // Hamta total IBC per user for veckan, kolla om denna user ar topp
            $sql = "
                SELECT user_id, COUNT(*) AS total_ibc
                FROM rebotling_ibc
                WHERE DATE(datum) BETWEEN :from AND :to
                  AND user_id IS NOT NULL AND user_id > 0
                GROUP BY user_id
                ORDER BY total_ibc DESC
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':from' => $mondayThisWeek, ':to' => $today]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && (int)$row['user_id'] === $userId) {
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
        } catch (\PDOException $e) {}

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
                $isEarned = in_array($b['id'], $earnedIds);
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
            } catch (\PDOException $e) {}

            // Hamta rank fran vecko-leaderboard
            $from = date('Y-m-d', strtotime('monday this week'));
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
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rebotling_ibc WHERE user_id = ?");
            $stmt->execute([$userId]);
            $totalIbc = (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {}

        $result = [];
        foreach ($milstolpar as $m) {
            $uppnadd = $totalIbc >= $m['krav'];
            $progress = min(100, round(($totalIbc / $m['krav']) * 100, 1));
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
            // Kolla om admin
            $role = $_SESSION['role'] ?? '';
            // Tillat aven vanliga anvandare att se overview

            $from = date('Y-m-d', strtotime('monday this week'));
            $to = date('Y-m-d');
            $leaderboard = $this->calcLeaderboard($from, $to);

            $totalOperatorer = count($leaderboard);
            $totalPoang = array_sum(array_column($leaderboard, 'total_poang'));
            $totalIbc = array_sum(array_column($leaderboard, 'total_ibc'));
            $avgPoang = $totalOperatorer > 0 ? round($totalPoang / $totalOperatorer, 1) : 0;

            // Badge-statistik
            $badgeCount = 0;
            foreach ($leaderboard as $op) {
                $badges = $this->getBadges($op['user_id']);
                $badgeCount += count($badges);
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
