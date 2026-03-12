<?php
/**
 * ForstaTimmeAnalysController.php
 *
 * Analyserar hur forsta timmen efter varje skiftstart gar.
 * VD-vy: uppstartstid, ramp-up-kurva, jamforelse mot genomsnitt.
 *
 * Endpoints via ?action=forsta-timme-analys&run=XXX:
 *   - run=analysis&period=7|30|90  — Per-skiftstart-data + aggregerad genomsnitts-kurva
 *   - run=trend&period=30|90       — Daglig trend av "tid till forsta IBC"
 *
 * Tabeller:
 *   rebotling_ibc    (kolumn: timestamp eller datum)
 *   rebotling_onoff  (kolumner: on, timestamp — on=1 driftstart, on=0 driftstopp)
 *
 * Skiftstart-tider: dag 06:00, kväll 14:00, natt 22:00
 */
class ForstaTimmeAnalysController {
    private $pdo;

    // Skiftstarttider (HH:MM)
    private const SHIFT_STARTS = [
        'dag'    => '06:00',
        'kväll'  => '14:00',
        'natt'   => '22:00',
    ];

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
            case 'analysis': $this->getAnalysis(); break;
            case 'trend':    $this->getTrend();    break;
            default:         $this->sendError('Ogiltig run: ' . $run); break;
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getPeriodDays(): int {
        $period = intval($_GET['period'] ?? 30);
        if (!in_array($period, [7, 30, 90])) $period = 30;
        return $period;
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

    /**
     * Returnera kolumnnamnet for timestamp i rebotling_ibc.
     * Provar 'timestamp' forst, faller tillbaka pa 'datum'.
     */
    private function getIbcTimestampColumn(): string {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM rebotling_ibc")->fetchAll(PDO::FETCH_COLUMN);
            return in_array('timestamp', $cols) ? 'timestamp' : 'datum';
        } catch (\Exception $e) {
            return 'datum';
        }
    }

    /**
     * Generera alla skiftstarttider inom ett datumintervall.
     * Returnerar array av ['date' => 'YYYY-MM-DD', 'shift' => 'dag|kväll|natt', 'start' => 'YYYY-MM-DD HH:MM:SS', 'end' => 'YYYY-MM-DD HH:MM:SS']
     */
    private function generateShiftStarts(string $fromDate, string $toDate): array {
        $shifts = [];
        $current = new DateTime($fromDate);
        $end = new DateTime($toDate);
        $end->modify('+1 day');

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');

            foreach (self::SHIFT_STARTS as $shiftName => $startTime) {
                $shiftStart = new DateTime($dateStr . ' ' . $startTime . ':00');

                // Slutet av skiftet = 8 timmar senare
                $shiftEnd = clone $shiftStart;
                $shiftEnd->modify('+8 hours');

                // Forsta timme slutar 60 min efter skiftstart
                $firstHourEnd = clone $shiftStart;
                $firstHourEnd->modify('+60 minutes');

                $shifts[] = [
                    'date'           => $dateStr,
                    'shift'          => $shiftName,
                    'start'          => $shiftStart->format('Y-m-d H:i:s'),
                    'end'            => $shiftEnd->format('Y-m-d H:i:s'),
                    'first_hour_end' => $firstHourEnd->format('Y-m-d H:i:s'),
                ];
            }

            $current->modify('+1 day');
        }

        return $shifts;
    }

    // ================================================================
    // run=analysis — Per-skiftstart-data + aggregerad genomsnitts-kurva
    // ================================================================

    private function getAnalysis(): void {
        $days     = $this->getPeriodDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $ibcCol = $this->getIbcTimestampColumn();

        // Hämta alla IBC:er i perioden
        $ibcRows = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT {$ibcCol} AS ts
                FROM rebotling_ibc
                WHERE DATE({$ibcCol}) BETWEEN :from_date AND :to_date
                ORDER BY {$ibcCol} ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $ibcRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('ForstaTimmeAnalysController::getAnalysis (ibc): ' . $e->getMessage());
        }

        // Indexera IBC-timestamps som Unix-timestamps for snabb sokning
        $ibcTimestamps = array_map(function ($row) {
            return strtotime($row['ts']);
        }, $ibcRows);
        $ibcTimestamps = array_filter($ibcTimestamps, fn($t) => $t !== false);
        $ibcTimestamps = array_values($ibcTimestamps);
        sort($ibcTimestamps);

        // Skiftstarttider
        $allShifts = $this->generateShiftStarts($fromDate, $toDate);

        $shiftResults  = [];
        $intervalSums  = array_fill(0, 6, 0); // 6 x 10-min-intervaller
        $intervalCount = array_fill(0, 6, 0);
        $tidTillForstaSum   = 0;
        $tidTillForstaCount = 0;
        $snabbaste = PHP_INT_MAX;
        $langsamma = -1;
        $totalIbcForstaTimme = 0;
        $totalShiftsWithIbc  = 0;

        foreach ($allShifts as $shift) {
            $startTs       = strtotime($shift['start']);
            $firstHourEnd  = strtotime($shift['first_hour_end']);

            // Hitta IBC:er inom forsta timmen
            $ibcInFirstHour = [];
            foreach ($ibcTimestamps as $ts) {
                if ($ts >= $startTs && $ts <= $firstHourEnd) {
                    $ibcInFirstHour[] = $ts;
                }
            }

            // Om inget IBC i forsta timmen — hoppa over (skiftet kan ha haft uppehall)
            if (empty($ibcInFirstHour)) {
                continue;
            }

            $totalShiftsWithIbc++;

            // Tid till forsta IBC (minuter)
            $forstaIbcTs  = $ibcInFirstHour[0];
            $tidMinuter   = round(($forstaIbcTs - $startTs) / 60);
            $tidMinuter   = max(0, (int)$tidMinuter);

            $tidTillForstaSum   += $tidMinuter;
            $tidTillForstaCount++;

            if ($tidMinuter < $snabbaste) $snabbaste = $tidMinuter;
            if ($tidMinuter > $langsamma) $langsamma = $tidMinuter;

            // Räkna IBC per 10-min-intervall
            $intervals = array_fill(0, 6, 0);
            foreach ($ibcInFirstHour as $ts) {
                $minOff = ($ts - $startTs) / 60;
                $idx    = (int)floor($minOff / 10);
                if ($idx >= 0 && $idx < 6) {
                    $intervals[$idx]++;
                }
            }

            // Ackumulera for genomsnitt
            for ($i = 0; $i < 6; $i++) {
                $intervalSums[$i]  += $intervals[$i];
                $intervalCount[$i] += 1;
            }

            $totalIbcForstaTimme += count($ibcInFirstHour);

            // Bedomning
            $bedomning = $this->bedomStart($tidMinuter);

            $shiftResults[] = [
                'date'                 => $shift['date'],
                'shift'                => $shift['shift'],
                'shift_start'          => $shift['start'],
                'tid_till_forsta_ibc'  => $tidMinuter,
                'ibc_forsta_timme'     => count($ibcInFirstHour),
                'intervals'            => $intervals,
                'bedomning'            => $bedomning,
            ];
        }

        // Genomsnitts-kurva (6 intervaller)
        $avgKurva = [];
        for ($i = 0; $i < 6; $i++) {
            $avgKurva[] = $intervalCount[$i] > 0
                ? round($intervalSums[$i] / $intervalCount[$i], 2)
                : 0;
        }

        // Genomsnittlig tid till forsta IBC
        $snittTid = $tidTillForstaCount > 0
            ? round($tidTillForstaSum / $tidTillForstaCount, 1)
            : null;

        // Ramp-up-hastighet: IBC under forsta 30 min jämfort med genomsnitt
        // Genomsnittlig IBC under forsta 30 min = sum av intervall 0+1+2 / antal skift
        $ibcFirst30Avg = array_sum(array_slice($avgKurva, 0, 3));
        $ibcFirst60Avg = array_sum($avgKurva);
        $rampupPct = ($ibcFirst60Avg > 0)
            ? round(($ibcFirst30Avg / $ibcFirst60Avg) * 100)
            : 0;

        // Sortera skift-resultat nyast forst, begränsa till 50
        usort($shiftResults, function ($a, $b) {
            return strcmp($b['shift_start'], $a['shift_start']);
        });
        $shiftResults = array_slice($shiftResults, 0, 50);

        $this->sendSuccess([
            'period'                  => $days,
            'from_date'               => $fromDate,
            'to_date'                 => $toDate,
            'snitt_tid_till_forsta'   => $snittTid,
            'snabbaste_start'         => ($snabbaste === PHP_INT_MAX) ? null : $snabbaste,
            'langsamma_start'         => ($langsamma === -1) ? null : $langsamma,
            'rampup_pct_30min'        => $rampupPct,
            'avg_kurva'               => $avgKurva,
            'interval_labels'         => ['0-10', '10-20', '20-30', '30-40', '40-50', '50-60'],
            'total_shifts_med_data'   => $totalShiftsWithIbc,
            'skift_starter'           => $shiftResults,
        ]);
    }

    /**
     * Bedom en start som snabb/normal/langssam baserat pa tid till forsta IBC
     */
    private function bedomStart(int $minuter): string {
        if ($minuter <= 10) return 'snabb';
        if ($minuter <= 25) return 'normal';
        return 'langssam';
    }

    // ================================================================
    // run=trend — Daglig trend av "tid till forsta IBC"
    // ================================================================

    private function getTrend(): void {
        $days     = $this->getPeriodDays();
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $ibcCol = $this->getIbcTimestampColumn();

        // Hämta IBC-timestamps per dag
        $ibcByDay = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE({$ibcCol}) AS dag, {$ibcCol} AS ts
                FROM rebotling_ibc
                WHERE DATE({$ibcCol}) BETWEEN :from_date AND :to_date
                ORDER BY {$ibcCol} ASC
            ");
            $stmt->execute([':from_date' => $fromDate, ':to_date' => $toDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $ibcByDay[$row['dag']][] = strtotime($row['ts']);
            }
        } catch (\PDOException $e) {
            error_log('ForstaTimmeAnalysController::getTrend (ibc): ' . $e->getMessage());
        }

        $trendData = [];

        // Iterera dagar i perioden
        $currentDate = new DateTime($fromDate);
        $endDate     = new DateTime($toDate);

        while ($currentDate <= $endDate) {
            $dateStr    = $currentDate->format('Y-m-d');
            $dayIbcTs   = isset($ibcByDay[$dateStr])
                ? array_values(array_filter(array_map('intval', $ibcByDay[$dateStr])))
                : [];

            if (!empty($dayIbcTs)) {
                sort($dayIbcTs);

                // Genomsnittlig tid till forsta IBC for alla skift denna dag
                $skiftTider = [];
                foreach (self::SHIFT_STARTS as $shiftName => $startTime) {
                    $startTs      = strtotime($dateStr . ' ' . $startTime . ':00');
                    $firstHourEnd = $startTs + 3600;

                    // Forsta IBC inom forsta timmen av detta skift
                    foreach ($dayIbcTs as $ts) {
                        if ($ts >= $startTs && $ts <= $firstHourEnd) {
                            $minOff = round(($ts - $startTs) / 60);
                            $skiftTider[] = max(0, (int)$minOff);
                            break;
                        }
                    }
                }

                if (!empty($skiftTider)) {
                    $trendData[] = [
                        'date'                   => $dateStr,
                        'snitt_tid_till_forsta'  => round(array_sum($skiftTider) / count($skiftTider), 1),
                        'min_tid'                => min($skiftTider),
                        'max_tid'                => max($skiftTider),
                        'antal_skift'            => count($skiftTider),
                    ];
                }
            }

            $currentDate->modify('+1 day');
        }

        $this->sendSuccess([
            'period'    => $days,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'trend'     => $trendData,
        ]);
    }
}
