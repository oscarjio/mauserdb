<?php
/**
 * DrifttidsTimelineController.php
 * Drifttids-timeline — visuell tidslinje per dag för rebotling-linjen.
 *
 * Visar gröna (körning), röda (stopp) och grå (ej planerat) segment under en dag.
 *
 * Endpoints via ?action=drifttids-timeline&run=XXX:
 *   - run=timeline-data&date=YYYY-MM-DD → tidssegment för en dag
 *   - run=summary&date=YYYY-MM-DD       → KPI:er för dagen
 *
 * Tabeller: rebotling_onoff, stoppage_log, stopporsak_registreringar
 * Auth: session krävs (401 om ej inloggad).
 */
class DrifttidsTimelineController {
    private $pdo;

    /** Planerat skift: 06:00–22:00 (16 timmar) */
    private const SKIFT_START = '06:00:00';
    private const SKIFT_SLUT  = '22:00:00';

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
            case 'timeline-data':    $this->getTimelineData();    break;
            case 'summary':          $this->getSummary();         break;
            case 'orsaksfordelning': $this->getOrsaksfordelning(); break;
            case 'veckotrend':       $this->getVeckotrend();      break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
        }
    }

    // ================================================================
    // HJÄLPFUNKTIONER
    // ================================================================

    private function getDate(): string {
        $date = trim($_GET['date'] ?? '');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        return $date;
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
     * Hämta alla on/off-händelser från rebotling_onoff för en dag.
     * Returnerar array av {start_ts, stop_ts} (unix timestamps).
     */
    private function getOnOffPeriods(string $date): array {
        $periods = [];

        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'rebotling_onoff'");
            if (!$check || $check->rowCount() === 0) {
                return [];
            }

            $dayStart = $date . ' 00:00:00';
            $nextDay  = (new \DateTime($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            $dayEnd   = $nextDay;

            // rebotling_onoff har datum + running (boolean), inte start_time/stop_time
            $stmt = $this->pdo->prepare("
                SELECT datum, running
                FROM rebotling_onoff
                WHERE datum >= :day_start AND datum < :day_end
                ORDER BY datum ASC
            ");
            $stmt->execute([':day_start' => $dayStart, ':day_end' => $dayEnd]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dayStartTs = strtotime($dayStart);
            $dayEndTs   = strtotime($nextDay);

            // Bygg ON-perioder fran running-data
            $lastOnTs = null;
            foreach ($rows as $row) {
                $ts = strtotime($row['datum']);
                if ((int)$row['running'] === 1) {
                    if ($lastOnTs === null) {
                        $lastOnTs = max($ts, $dayStartTs);
                    }
                } else {
                    if ($lastOnTs !== null) {
                        $stopTs = min($ts, $dayEndTs);
                        if ($stopTs > $lastOnTs) {
                            $periods[] = ['start_ts' => $lastOnTs, 'stop_ts' => $stopTs];
                        }
                        $lastOnTs = null;
                    }
                }
            }
            // Om linjen fortfarande kor vid dagens slut
            if ($lastOnTs !== null) {
                $stopTs = min(time(), $dayEndTs);
                if ($stopTs > $lastOnTs) {
                    $periods[] = ['start_ts' => $lastOnTs, 'stop_ts' => $stopTs];
                }
            }
        } catch (\PDOException $e) {
            error_log('DrifttidsTimelineController::getOnOffPeriods: ' . $e->getMessage());
        }

        return $periods;
    }

    /**
     * Hämta stopporsaker för en dag från stoppage_log + stopporsak_registreringar.
     * Returnerar array av {start_ts, end_ts, reason, operator}.
     */
    private function getStopReasons(string $date): array {
        $stops = [];

        // Från stoppage_log
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stoppage_log'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        sl.start_time,
                        sl.end_time,
                        sl.duration_minutes,
                        sr.name AS reason,
                        sl.user_id AS operator_name
                    FROM stoppage_log sl
                    LEFT JOIN stoppage_reasons sr ON sr.id = sl.reason_id
                    WHERE sl.start_time >= :date AND sl.start_time < DATE_ADD(:dateb, INTERVAL 1 DAY)
                      AND sl.duration_minutes > 0
                    ORDER BY sl.start_time ASC
                ");
                $stmt->execute([':date' => $date, ':dateb' => $date]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $startTs = strtotime($row['start_time']);
                    $endTs   = $row['end_time']
                        ? strtotime($row['end_time'])
                        : ($startTs + (int)$row['duration_minutes'] * 60);

                    $stops[] = [
                        'start_ts' => $startTs,
                        'end_ts'   => $endTs,
                        'reason'   => $row['reason'] ?? null,
                        'operator' => $row['operator_name'] ?? null,
                        'source'   => 'stoppage_log',
                    ];
                }
            }
        } catch (\PDOException $e) {
            error_log('DrifttidsTimelineController::getStopReasons (stoppage_log): ' . $e->getMessage());
        }

        // Från stopporsak_registreringar
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'stopporsak_registreringar'");
            if ($check && $check->rowCount() > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        sr.start_time,
                        sr.end_time,
                        sk.namn AS orsak,
                        sr.kommentar,
                        sr.user_id
                    FROM stopporsak_registreringar sr
                    LEFT JOIN stopporsak_kategorier sk ON sk.id = sr.kategori_id
                    WHERE sr.start_time >= :date AND sr.start_time < DATE_ADD(:dateb, INTERVAL 1 DAY)
                      AND sr.end_time IS NOT NULL
                      AND TIMESTAMPDIFF(MINUTE, sr.start_time, sr.end_time) > 0
                    ORDER BY sr.start_time ASC
                ");
                $stmt->execute([':date' => $date, ':dateb' => $date]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $startTs = strtotime($row['start_time']);
                    $endTs   = strtotime($row['end_time']);

                    if ($endTs > $startTs) {
                        $reason = $row['orsak'] ?? null;
                        if ($row['kommentar']) {
                            $reason = $reason ? ($reason . ': ' . $row['kommentar']) : $row['kommentar'];
                        }
                        $stops[] = [
                            'start_ts' => $startTs,
                            'end_ts'   => $endTs,
                            'reason'   => $reason,
                            'operator' => $row['user_id'] ? 'Op #' . $row['user_id'] : null,
                            'source'   => 'stopporsak_registreringar',
                        ];
                    }
                }
            }
        } catch (\PDOException $e) {
            error_log('DrifttidsTimelineController::getStopReasons (stopporsak_registreringar): ' . $e->getMessage());
        }

        // Sortera på start_ts
        usort($stops, fn($a, $b) => $a['start_ts'] <=> $b['start_ts']);

        return $stops;
    }

    /**
     * Bygg en lista av segment från on/off-perioder och stopporsaker.
     * Segment-typer: 'running' | 'stopped' | 'unplanned'
     */
    private function buildSegments(string $date, array $onOffPeriods, array $stopReasons): array {
        $skiftStartTs = strtotime($date . ' ' . self::SKIFT_START);
        $skiftSlutTs  = strtotime($date . ' ' . self::SKIFT_SLUT);
        $dayStartTs   = strtotime($date . ' 00:00:00');
        $dayEndTs     = strtotime((new \DateTime($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00');

        $segments = [];

        // Bygg en "master timeline" med minutupplösning
        // Vi arbetar med hela dagen som unix-tidsstämplar
        // Och skapar segment baserat på on/off + stopp

        // Om det inte finns on/off-data, bygg baserat på stopporsaker
        if (empty($onOffPeriods)) {
            // Ingen on/off-data: hela skiftet = ej planerat eller okänt
            // Kolla om vi har stopporsaker
            if (!empty($stopReasons)) {
                // Lägg till stopp-segment och gör resten okänt (unplanned)
                $cursor = $skiftStartTs;
                foreach ($stopReasons as $stop) {
                    $sStart = max($stop['start_ts'], $skiftStartTs);
                    $sEnd   = min($stop['end_ts'], $skiftSlutTs);

                    if ($sStart > $cursor) {
                        // Gap: vi vet inte om det är körning eller ej, markera unplanned
                        $segments[] = $this->makeSegment('unplanned', $cursor, $sStart, null, null);
                    }
                    if ($sEnd > $sStart) {
                        $segments[] = $this->makeSegment('stopped', $sStart, $sEnd, $stop['reason'], $stop['operator']);
                    }
                    $cursor = max($cursor, $sEnd);
                }
                if ($cursor < $skiftSlutTs) {
                    $segments[] = $this->makeSegment('unplanned', $cursor, $skiftSlutTs, null, null);
                }
            } else {
                // Ingenting känt — hela skiftet unplanned
                $segments[] = $this->makeSegment('unplanned', $skiftStartTs, $skiftSlutTs, null, null);
            }

            // Före skift
            if ($skiftStartTs > $dayStartTs) {
                array_unshift($segments, $this->makeSegment('unplanned', $dayStartTs, $skiftStartTs, null, null));
            }
            // Efter skift
            if ($skiftSlutTs < $dayEndTs) {
                $segments[] = $this->makeSegment('unplanned', $skiftSlutTs, $dayEndTs, null, null);
            }

            return $segments;
        }

        // Med on/off-data: bygg tidslinje
        // Skapa en lista av "running" intervals
        // och fylla mellanrummen med "stopped" (med stopporsak om möjligt)

        $cursor = $dayStartTs;

        // Lägg till segment för hela dagen
        foreach ($onOffPeriods as $period) {
            $pStart = $period['start_ts'];
            $pStop  = $period['stop_ts'];

            // Gap innan denna körperiod
            if ($pStart > $cursor) {
                $gapStart = $cursor;
                $gapEnd   = $pStart;

                // Är gapet utanför skifttid?
                $withinSkift = $gapEnd > $skiftStartTs && $gapStart < $skiftSlutTs;
                if ($withinSkift) {
                    // Del utanför skift (grå)
                    if ($gapStart < $skiftStartTs) {
                        $segments[] = $this->makeSegment('unplanned', $gapStart, $skiftStartTs, null, null);
                        $gapStart = $skiftStartTs;
                    }
                    // Stoppsegment inom skiftet — hitta stopporsaker
                    $this->addStopSegments($segments, $gapStart, min($gapEnd, $skiftSlutTs), $stopReasons);
                    // Del utanför skift efter (grå)
                    if ($gapEnd > $skiftSlutTs) {
                        $segments[] = $this->makeSegment('unplanned', $skiftSlutTs, $gapEnd, null, null);
                    }
                } else {
                    $segments[] = $this->makeSegment('unplanned', $gapStart, $gapEnd, null, null);
                }
            }

            // Körperiod
            if ($pStop > $pStart) {
                $segments[] = $this->makeSegment('running', $pStart, $pStop, null, null);
            }

            $cursor = max($cursor, $pStop);
        }

        // Resterande tid efter sista körperiod
        if ($cursor < $dayEndTs) {
            $gapStart = $cursor;
            $gapEnd   = $dayEndTs;
            $withinSkift = $gapEnd > $skiftStartTs && $gapStart < $skiftSlutTs;

            if ($withinSkift) {
                if ($gapStart < $skiftStartTs) {
                    $segments[] = $this->makeSegment('unplanned', $gapStart, $skiftStartTs, null, null);
                    $gapStart = $skiftStartTs;
                }
                $this->addStopSegments($segments, $gapStart, min($gapEnd, $skiftSlutTs), $stopReasons);
                if ($gapEnd > $skiftSlutTs) {
                    $segments[] = $this->makeSegment('unplanned', $skiftSlutTs, $gapEnd, null, null);
                }
            } else {
                $segments[] = $this->makeSegment('unplanned', $gapStart, $gapEnd, null, null);
            }
        }

        // Sortera på start-tid
        usort($segments, fn($a, $b) => $a['start_ts'] <=> $b['start_ts']);

        return $segments;
    }

    /**
     * Lägg till stopp-segment (med orsaker) för ett tidsintervall.
     */
    private function addStopSegments(array &$segments, int $gapStart, int $gapEnd, array $stopReasons): void {
        // Hitta relevanta stopporsaker för detta gap
        $relevantStops = array_filter($stopReasons, function($stop) use ($gapStart, $gapEnd) {
            return $stop['start_ts'] < $gapEnd && $stop['end_ts'] > $gapStart;
        });

        if (empty($relevantStops)) {
            // Inga kända stopporsaker — markera som stopp utan orsak
            $segments[] = $this->makeSegment('stopped', $gapStart, $gapEnd, null, null);
            return;
        }

        $cursor = $gapStart;
        foreach ($relevantStops as $stop) {
            $sStart = max($stop['start_ts'], $gapStart);
            $sEnd   = min($stop['end_ts'], $gapEnd);

            if ($sStart > $cursor) {
                // Mellanrum utan känd orsak
                $segments[] = $this->makeSegment('stopped', $cursor, $sStart, null, null);
            }
            if ($sEnd > $sStart) {
                $segments[] = $this->makeSegment('stopped', $sStart, $sEnd, $stop['reason'], $stop['operator']);
            }
            $cursor = max($cursor, $sEnd);
        }

        if ($cursor < $gapEnd) {
            $segments[] = $this->makeSegment('stopped', $cursor, $gapEnd, null, null);
        }
    }

    /**
     * Skapa ett segment-objekt.
     */
    private function makeSegment(
        string $type,
        int $startTs,
        int $endTs,
        ?string $reason,
        ?string $operator
    ): array {
        $durationMin = round(($endTs - $startTs) / 60, 1);
        return [
            'type'         => $type,
            'start'        => date('Y-m-d H:i:s', $startTs),
            'end'          => date('Y-m-d H:i:s', $endTs),
            'start_ts'     => $startTs,
            'end_ts'       => $endTs,
            'duration_min' => $durationMin,
            'stop_reason'  => $reason,
            'operator'     => $operator,
        ];
    }

    // ================================================================
    // ENDPOINT: timeline-data
    // ================================================================

    /**
     * GET ?action=drifttids-timeline&run=timeline-data&date=YYYY-MM-DD
     * Returnerar tidssegment för en dag: {type, start, end, duration_min, stop_reason?, operator?}
     */
    private function getTimelineData(): void {
        $date = $this->getDate();

        try {
            $onOffPeriods = $this->getOnOffPeriods($date);
            $stopReasons  = $this->getStopReasons($date);
            $segments     = $this->buildSegments($date, $onOffPeriods, $stopReasons);

            // Beräkna totaler
            $runningMin  = 0;
            $stoppedMin  = 0;
            $unplannedMin = 0;
            foreach ($segments as $seg) {
                if ($seg['type'] === 'running')    $runningMin  += $seg['duration_min'];
                if ($seg['type'] === 'stopped')    $stoppedMin  += $seg['duration_min'];
                if ($seg['type'] === 'unplanned')  $unplannedMin += $seg['duration_min'];
            }

            $this->sendSuccess([
                'date'          => $date,
                'segments'      => $segments,
                'skift_start'   => self::SKIFT_START,
                'skift_slut'    => self::SKIFT_SLUT,
                'running_min'   => round($runningMin, 1),
                'stopped_min'   => round($stoppedMin, 1),
                'unplanned_min' => round($unplannedMin, 1),
            ]);
        } catch (\Exception $e) {
            error_log('DrifttidsTimelineController::getTimelineData: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta timeline-data', 500);
        }
    }

    // ================================================================
    // ENDPOINT: summary
    // ================================================================

    /**
     * GET ?action=drifttids-timeline&run=summary&date=YYYY-MM-DD
     * KPI:er: total drifttid, total stopptid, antal stopp, längsta körperiod, utnyttjandegrad
     */
    private function getSummary(): void {
        $date = $this->getDate();

        try {
            $onOffPeriods = $this->getOnOffPeriods($date);
            $stopReasons  = $this->getStopReasons($date);
            $segments     = $this->buildSegments($date, $onOffPeriods, $stopReasons);

            // Planerad skifttid i minuter
            $skiftStartTs    = strtotime($date . ' ' . self::SKIFT_START);
            $skiftSlutTs     = strtotime($date . ' ' . self::SKIFT_SLUT);
            $plannadTidMin   = ($skiftSlutTs - $skiftStartTs) / 60; // 960 min (16h)

            $runningMin      = 0;
            $stoppedMin      = 0;
            $antalStopp      = 0;
            $langstaKorning  = 0;
            $langstaStopp    = 0;

            foreach ($segments as $seg) {
                if ($seg['type'] === 'running') {
                    $runningMin += $seg['duration_min'];
                    if ($seg['duration_min'] > $langstaKorning) {
                        $langstaKorning = $seg['duration_min'];
                    }
                }
                if ($seg['type'] === 'stopped') {
                    $stoppedMin += $seg['duration_min'];
                    $antalStopp++;
                    if ($seg['duration_min'] > $langstaStopp) {
                        $langstaStopp = $seg['duration_min'];
                    }
                }
            }

            $utnyttjandegrad = $plannadTidMin > 0
                ? round(($runningMin / $plannadTidMin) * 100, 1)
                : 0.0;

            $snittStoppMin = $antalStopp > 0 ? round($stoppedMin / $antalStopp, 1) : 0.0;

            $this->sendSuccess([
                'date'                => $date,
                'drifttid_min'        => round($runningMin, 1),
                'stopptid_min'        => round($stoppedMin, 1),
                'antal_stopp'         => $antalStopp,
                'langsta_korning_min' => round($langstaKorning, 1),
                'langsta_stopp_min'   => round($langstaStopp, 1),
                'snitt_stopp_min'     => $snittStoppMin,
                'utnyttjandegrad_pct' => $utnyttjandegrad,
                'plannad_tid_min'     => $plannadTidMin,
            ]);
        } catch (\Exception $e) {
            error_log('DrifttidsTimelineController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna summary', 500);
        }
    }

    // ================================================================
    // ENDPOINT: orsaksfordelning
    // ================================================================

    /**
     * GET ?action=drifttids-timeline&run=orsaksfordelning&date=YYYY-MM-DD
     * Fordelning av stopporsaker for en dag — for doughnut/pie-diagram.
     * Returnerar: [{orsak, total_min, andel_pct, antal_stopp, kalla}]
     */
    private function getOrsaksfordelning(): void {
        $date = $this->getDate();

        try {
            $stopReasons = $this->getStopReasons($date);

            // Aggregera per orsak
            $orsakMap = []; // [orsak => {total_min, antal, kalla}]
            foreach ($stopReasons as $stop) {
                $orsak = $stop['reason'] ?? 'Okand orsak';
                $durationMin = ($stop['end_ts'] - $stop['start_ts']) / 60;
                if ($durationMin <= 0) continue;

                if (!isset($orsakMap[$orsak])) {
                    $orsakMap[$orsak] = ['total_min' => 0, 'antal' => 0, 'kalla' => $stop['source'] ?? '-'];
                }
                $orsakMap[$orsak]['total_min'] += $durationMin;
                $orsakMap[$orsak]['antal']++;
            }

            // Sortera storst forst
            arsort($orsakMap);
            $totalMin = array_sum(array_column(array_values($orsakMap), 'total_min'));

            $result = [];
            foreach ($orsakMap as $orsak => $d) {
                $result[] = [
                    'orsak'      => $orsak,
                    'total_min'  => round($d['total_min'], 1),
                    'andel_pct'  => $totalMin > 0 ? round(($d['total_min'] / $totalMin) * 100, 1) : 0,
                    'antal_stopp' => $d['antal'],
                    'kalla'      => $d['kalla'],
                ];
            }

            // Lagg till segment utan orsak fran timeline
            $onOffPeriods = $this->getOnOffPeriods($date);
            $segments     = $this->buildSegments($date, $onOffPeriods, $stopReasons);
            $okandStoppMin = 0;
            $okandAntal = 0;
            foreach ($segments as $seg) {
                if ($seg['type'] === 'stopped' && empty($seg['stop_reason'])) {
                    $okandStoppMin += $seg['duration_min'];
                    $okandAntal++;
                }
            }

            $this->sendSuccess([
                'date'             => $date,
                'total_stopp_min'  => round($totalMin, 1),
                'orsaker'          => $result,
                'okand_stopp_min'  => round($okandStoppMin, 1),
                'okand_stopp_antal' => $okandAntal,
            ]);
        } catch (\Exception $e) {
            error_log('DrifttidsTimelineController::getOrsaksfordelning: ' . $e->getMessage());
            $this->sendError('Kunde inte berakna orsaksfordelning', 500);
        }
    }

    // ================================================================
    // ENDPOINT: veckotrend
    // ================================================================

    /**
     * GET ?action=drifttids-timeline&run=veckotrend&days=7
     * Drifttid/stopptid/utnyttjandegrad per dag, senaste N dagar (default 7).
     * For linjediagram med trender.
     */
    private function getVeckotrend(): void {
        $days = max(1, min(90, intval($_GET['days'] ?? 7)));

        try {
            $result = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));

                $onOffPeriods = $this->getOnOffPeriods($date);
                $segments     = $this->buildSegments($date, $onOffPeriods, []);

                $skiftStartTs  = strtotime($date . ' ' . self::SKIFT_START);
                $skiftSlutTs   = strtotime($date . ' ' . self::SKIFT_SLUT);
                $plannadTidMin = ($skiftSlutTs - $skiftStartTs) / 60;

                $runningMin = 0;
                $stoppedMin = 0;
                $antalStopp = 0;
                foreach ($segments as $seg) {
                    if ($seg['type'] === 'running')  $runningMin += $seg['duration_min'];
                    if ($seg['type'] === 'stopped') {
                        $stoppedMin += $seg['duration_min'];
                        $antalStopp++;
                    }
                }

                $utnyttjandegrad = $plannadTidMin > 0
                    ? round(($runningMin / $plannadTidMin) * 100, 1)
                    : 0.0;

                $result[] = [
                    'datum'               => $date,
                    'drifttid_min'        => round($runningMin, 1),
                    'stopptid_min'        => round($stoppedMin, 1),
                    'antal_stopp'         => $antalStopp,
                    'utnyttjandegrad_pct' => $utnyttjandegrad,
                ];
            }

            $this->sendSuccess([
                'days' => $days,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            error_log('DrifttidsTimelineController::getVeckotrend: ' . $e->getMessage());
            $this->sendError('Kunde inte berakna veckotrend', 500);
        }
    }
}
