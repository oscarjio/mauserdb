<?php
/**
 * StopporsakTrendController.php
 * Stopporsak-trendanalys — visar hur de vanligaste stopporsakerna utvecklas veckovis.
 * VD kan se om åtgärder mot specifika stopporsaker fungerar eller om problemen förvärras.
 *
 * Endpoints via ?action=stopporsak-trend&run=XXX:
 *   run=weekly&weeks=N (default 12)
 *       Veckovis stopporsaksdata. Per vecka + orsak: antal stopp + total stopptid.
 *       Returnerar: [{week, reasons:[{reason, count, total_minutes}]}]
 *
 *   run=summary&weeks=N (default 8)
 *       Top-5 stopporsaker med trend (ökar/minskar/stabil).
 *       Jämför senaste 4 veckor vs föregående 4 veckor. Beräknar %-förändring.
 *
 *   run=detail&reason=X&weeks=N (default 12)
 *       Detaljerad tidsserie för en specifik orsak.
 *
 * Auth: session_id krävs (401 om ej inloggad).
 *
 * Tabeller: stoppage_log, stoppage_reasons
 *           stopporsak_registreringar, stopporsak_kategorier
 */
class StopporsakTrendController {
    private $pdo;

    /** Tröskel för "stable" trend (inom ±10%) */
    private const STABLE_THRESHOLD_PCT = 10.0;

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
            case 'weekly':  $this->getWeekly();  break;
            case 'summary': $this->getSummary(); break;
            case 'detail':  $this->getDetail();  break;
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

    private function getWeeks(): int {
        $w = (int)($_GET['weeks'] ?? 12);
        if ($w < 1 || $w > 52) return 12;
        return $w;
    }

    /**
     * Bygg veckolista (nycklar) för de senaste $weeks veckorna.
     * Returnerar array av "YYYY-WNN"-strängar, äldst först.
     */
    private function byggVeckonycklar(int $weeks): array {
        $keys = [];
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$weeks} weeks"));
        $current  = strtotime($fromDate);
        $end      = strtotime($toDate);
        while ($current <= $end) {
            $y   = (int)date('Y', $current);
            $w   = (int)date('W', $current);
            $key = sprintf('%04d-W%02d', $y, $w);
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
            $current = strtotime('+1 day', $current);
        }
        sort($keys);
        return $keys;
    }

    /**
     * Hämta kombinerad stoppdata per vecka och orsak från båda källorna.
     * Returnerar: [vecka_key][reason_label] => ['count' => N, 'total_minutes' => F]
     */
    private function hämtaStoppdata(int $weeks): array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$weeks} weeks"));
        $result   = [];

        // --- Källa 1: stoppage_log + stoppage_reasons ---
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    CONCAT(YEAR(sl.start_time), '-W', LPAD(WEEK(sl.start_time, 3), 2, '0')) AS vecka_key,
                    COALESCE(sr.name, 'Okänd orsak') AS reason,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(sl.duration_minutes), 0) AS total_min
                 FROM stoppage_log sl
                 LEFT JOIN stoppage_reasons sr ON sl.reason_id = sr.id
                 WHERE DATE(sl.start_time) BETWEEN ? AND ?
                 GROUP BY vecka_key, sr.name
                 ORDER BY vecka_key, cnt DESC"
            );
            $stmt->execute([$fromDate, $toDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $vk  = $row['vecka_key'];
                $rsn = $row['reason'];
                if (!isset($result[$vk][$rsn])) {
                    $result[$vk][$rsn] = ['count' => 0, 'total_minutes' => 0.0];
                }
                $result[$vk][$rsn]['count']         += (int)$row['cnt'];
                $result[$vk][$rsn]['total_minutes']  += (float)$row['total_min'];
            }
        } catch (\PDOException $e) {
            error_log('StopporsakTrendController::hämtaStoppdata (stoppage_log): ' . $e->getMessage());
        }

        // --- Källa 2: stopporsak_registreringar + stopporsak_kategorier ---
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    CONCAT(YEAR(sr.start_time), '-W', LPAD(WEEK(sr.start_time, 3), 2, '0')) AS vecka_key,
                    COALESCE(sk.namn, 'Okänd kategori') AS reason,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(
                        CASE WHEN sr.end_time IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, sr.start_time, sr.end_time)
                             ELSE 0 END
                    ), 0) AS total_min
                 FROM stopporsak_registreringar sr
                 LEFT JOIN stopporsak_kategorier sk ON sr.kategori_id = sk.id
                 WHERE DATE(sr.start_time) BETWEEN ? AND ?
                 GROUP BY vecka_key, sk.namn
                 ORDER BY vecka_key, cnt DESC"
            );
            $stmt->execute([$fromDate, $toDate]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $vk  = $row['vecka_key'];
                $rsn = $row['reason'];
                if (!isset($result[$vk][$rsn])) {
                    $result[$vk][$rsn] = ['count' => 0, 'total_minutes' => 0.0];
                }
                $result[$vk][$rsn]['count']         += (int)$row['cnt'];
                $result[$vk][$rsn]['total_minutes']  += (float)$row['total_min'];
            }
        } catch (\PDOException $e) {
            error_log('StopporsakTrendController::hämtaStoppdata (stopporsak_registreringar): ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Beräkna top-N orsaker baserat på totalt antal stopp.
     */
    private function topOrsaker(array $stoppdata, int $n = 7): array {
        $totaler = [];
        foreach ($stoppdata as $veckaRsn) {
            foreach ($veckaRsn as $rsn => $vals) {
                $totaler[$rsn] = ($totaler[$rsn] ?? 0) + $vals['count'];
            }
        }
        arsort($totaler);
        return array_slice(array_keys($totaler), 0, $n);
    }

    // ================================================================
    // run=weekly
    // ================================================================

    private function getWeekly(): void {
        $weeks = $this->getWeeks();

        try {
            $veckonycklar = $this->byggVeckonycklar($weeks);
            $stoppdata    = $this->hämtaStoppdata($weeks);
            $topRsn       = $this->topOrsaker($stoppdata, 5);

            // Beräkna totalt antal stopp och stopptid senaste veckan
            $sistaVecka = !empty($veckonycklar) ? end($veckonycklar) : null;
            $sistaVeckaData = $sistaVecka ? ($stoppdata[$sistaVecka] ?? []) : [];
            $totalStoppSenaste = array_sum(array_column($sistaVeckaData, 'count'));
            $totalMinSenaste   = array_sum(array_column($sistaVeckaData, 'total_minutes'));

            // Bygg veckovis data
            $veckor = [];
            foreach ($veckonycklar as $vk) {
                $veckaRsn = $stoppdata[$vk] ?? [];
                $reasons  = [];
                foreach ($topRsn as $rsn) {
                    $reasons[] = [
                        'reason'        => $rsn,
                        'count'         => (int)($veckaRsn[$rsn]['count'] ?? 0),
                        'total_minutes' => round((float)($veckaRsn[$rsn]['total_minutes'] ?? 0), 1),
                    ];
                }
                // Summa per vecka
                $totalCount = array_sum(array_column(array_values($veckaRsn), 'count'));
                $totalMin   = array_sum(array_column(array_values($veckaRsn), 'total_minutes'));

                $veckor[] = [
                    'week'          => $vk,
                    'week_label'    => $this->veckLabel($vk),
                    'reasons'       => $reasons,
                    'total_count'   => (int)$totalCount,
                    'total_minutes' => round((float)$totalMin, 1),
                ];
            }

            $this->sendSuccess([
                'weeks'                    => $weeks,
                'veckonycklar'             => $veckonycklar,
                'top_reasons'              => $topRsn,
                'veckor'                   => $veckor,
                'total_stopp_senaste_vecka'=> $totalStoppSenaste,
                'total_min_senaste_vecka'  => round((float)$totalMinSenaste, 1),
            ]);

        } catch (\Throwable $e) {
            error_log('StopporsakTrendController::getWeekly: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta veckodata', 500);
        }
    }

    // ================================================================
    // run=summary
    // ================================================================

    private function getSummary(): void {
        $weeks = $this->getWeeks();

        try {
            // Hämta data för dubbla perioden (senaste + föregående 4 veckor)
            $totalWeeks   = max($weeks, 8);
            $stoppdata    = $this->hämtaStoppdata($totalWeeks);
            $veckonycklar = $this->byggVeckonycklar($totalWeeks);

            // Dela upp i senaste 4 veckor vs föregående 4 veckor
            sort($veckonycklar);
            $cnt   = count($veckonycklar);
            $half  = max(1, (int)floor($cnt / 2));
            $senaste    = array_slice($veckonycklar, $cnt - $half);   // nyare
            $foregaende = array_slice($veckonycklar, 0, $half);       // äldre

            // Aggregera antal stopp per orsak för varje halva
            $senasteTotals     = [];
            $foregaendeTotals  = [];

            foreach ($senaste as $vk) {
                foreach ($stoppdata[$vk] ?? [] as $rsn => $vals) {
                    $senasteTotals[$rsn] = ($senasteTotals[$rsn] ?? 0) + $vals['count'];
                }
            }
            foreach ($foregaende as $vk) {
                foreach ($stoppdata[$vk] ?? [] as $rsn => $vals) {
                    $foregaendeTotals[$rsn] = ($foregaendeTotals[$rsn] ?? 0) + $vals['count'];
                }
            }

            // Samla alla orsaker, beräkna genomsnitt per vecka
            $alleRsn = array_unique(array_merge(
                array_keys($senasteTotals),
                array_keys($foregaendeTotals)
            ));

            $summaries = [];
            foreach ($alleRsn as $rsn) {
                $currentAvg  = ($senasteTotals[$rsn]    ?? 0) / max(1, count($senaste));
                $previousAvg = ($foregaendeTotals[$rsn] ?? 0) / max(1, count($foregaende));

                if ($previousAvg > 0) {
                    $changePct = round(($currentAvg - $previousAvg) / $previousAvg * 100, 1);
                } elseif ($currentAvg > 0) {
                    $changePct = 100.0;
                } else {
                    $changePct = 0.0;
                }

                if ($changePct > self::STABLE_THRESHOLD_PCT) {
                    $trend = 'increasing';
                } elseif ($changePct < -self::STABLE_THRESHOLD_PCT) {
                    $trend = 'decreasing';
                } else {
                    $trend = 'stable';
                }

                $summaries[] = [
                    'reason'       => $rsn,
                    'current_avg'  => round($currentAvg, 2),
                    'previous_avg' => round($previousAvg, 2),
                    'change_pct'   => $changePct,
                    'trend'        => $trend,
                    'total_current'=> (int)($senasteTotals[$rsn] ?? 0),
                ];
            }

            // Sortera: flest stopp senaste period
            usort($summaries, fn($a, $b) => $b['total_current'] <=> $a['total_current']);
            $summaries = array_slice($summaries, 0, 5);

            // Mest förbättrad = störst negativ change_pct (minskning)
            $mostImproved = null;
            foreach ($summaries as $s) {
                if ($s['trend'] === 'decreasing') {
                    if ($mostImproved === null || $s['change_pct'] < $mostImproved['change_pct']) {
                        $mostImproved = $s;
                    }
                }
            }

            // Vanligaste orsaken senaste veckan
            $sistaVecka = !empty($veckonycklar) ? end($veckonycklar) : null;
            $sistaVeckaData = $sistaVecka ? ($stoppdata[$sistaVecka] ?? []) : [];
            $vanligasteSenaste = null;
            $maxCount = 0;
            foreach ($sistaVeckaData as $rsn => $vals) {
                if ($vals['count'] > $maxCount) {
                    $maxCount = $vals['count'];
                    $vanligasteSenaste = $rsn;
                }
            }

            $this->sendSuccess([
                'summaries'       => $summaries,
                'most_improved'   => $mostImproved ? $mostImproved['reason'] : null,
                'vanligaste_orsak'=> $vanligasteSenaste,
                'senaste_veckor'  => $senaste,
                'foregaende_veckor' => $foregaende,
            ]);

        } catch (\Throwable $e) {
            error_log('StopporsakTrendController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta sammanfattning', 500);
        }
    }

    // ================================================================
    // run=detail
    // ================================================================

    private function getDetail(): void {
        $reason = mb_substr(trim($_GET['reason'] ?? ''), 0, 200);
        $weeks  = $this->getWeeks();

        if ($reason === '') {
            $this->sendError('Parametern reason saknas');
            return;
        }

        try {
            $veckonycklar = $this->byggVeckonycklar($weeks);
            $stoppdata    = $this->hämtaStoppdata($weeks);

            // Extrahera tidsserie för denna orsak
            $tidslinje = [];
            $totalCount = 0;
            $totalMin   = 0.0;

            foreach ($veckonycklar as $vk) {
                $vals   = $stoppdata[$vk][$reason] ?? null;
                $count  = $vals ? (int)$vals['count'] : 0;
                $min    = $vals ? (float)$vals['total_minutes'] : 0.0;
                $totalCount += $count;
                $totalMin   += $min;

                $tidslinje[] = [
                    'week'          => $vk,
                    'week_label'    => $this->veckLabel($vk),
                    'count'         => $count,
                    'total_minutes' => round($min, 1),
                ];
            }

            // Jämför senaste 4 vs föregående 4
            $cnt    = count($tidslinje);
            $half   = max(1, (int)floor($cnt / 2));
            $senaste    = array_slice($tidslinje, $cnt - $half);
            $foregaende = array_slice($tidslinje, 0, $half);

            $avgSenaste    = array_sum(array_column($senaste, 'count'))    / max(1, count($senaste));
            $avgForegaende = array_sum(array_column($foregaende, 'count')) / max(1, count($foregaende));

            if ($avgForegaende > 0) {
                $changePct = round(($avgSenaste - $avgForegaende) / $avgForegaende * 100, 1);
            } elseif ($avgSenaste > 0) {
                $changePct = 100.0;
            } else {
                $changePct = 0.0;
            }

            if ($changePct > self::STABLE_THRESHOLD_PCT) {
                $trend = 'increasing';
            } elseif ($changePct < -self::STABLE_THRESHOLD_PCT) {
                $trend = 'decreasing';
            } else {
                $trend = 'stable';
            }

            $this->sendSuccess([
                'reason'        => $reason,
                'weeks'         => $weeks,
                'tidslinje'     => $tidslinje,
                'total_count'   => $totalCount,
                'total_minutes' => round($totalMin, 1),
                'avg_per_week'  => round($totalCount / max(1, count($veckonycklar)), 2),
                'change_pct'    => $changePct,
                'trend'         => $trend,
            ]);

        } catch (\Throwable $e) {
            error_log('StopporsakTrendController::getDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta detaljdata', 500);
        }
    }

    // ================================================================
    // HJÄLP: veckoetikettformatering
    // ================================================================

    private function veckLabel(string $veckaKey): string {
        // "2026-W10" -> "V10"
        if (preg_match('/\d{4}-W(\d+)/', $veckaKey, $m)) {
            return 'V' . (int)$m[1];
        }
        return $veckaKey;
    }
}
