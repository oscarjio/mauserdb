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
            case 'timeline-data': $this->getTimelineData(); break;
            case 'summary':       $this->getSummary();      break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
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
            $dayEnd   = $date . ' 23:59:59';

            // rebotling_onoff har datum + running (boolean), inte start_time/stop_time
            $stmt = $this->pdo->prepare("
                SELECT datum, running
                FROM rebotling_onoff
                WHERE datum BETWEEN :day_start AND :day_end
                ORDER BY datum ASC
            ");
            $stmt->execute([':day_start' => $dayStart, ':day_end' => $dayEnd]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dayStartTs = strtotime($dayStart);
            $dayEndTs   = strtotime($dayEnd);

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
                        start_time,
                        end_time,
                        duration_minutes,
                        reason,
                        operator_name
                    FROM stoppage_log
                    WHERE DATE(start_time) = :date
                      AND duration_minutes > 0
                    ORDER BY start_time ASC
                ");
                $stmt->execute([':date' => $date]);
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
                        start_time,
                        end_time,
                        orsak,
                        kommentar,
                        operator_id
                    FROM stopporsak_registreringar
                    WHERE DATE(start_time) = :date
                      AND end_time IS NOT NULL
                      AND TIMESTAMPDIFF(MINUTE, start_time, end_time) > 0
                    ORDER BY start_time ASC
                ");
                $stmt->execute([':date' => $date]);
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
                            'operator' => $row['operator_id'] ? 'Op #' . $row['operator_id'] : null,
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
        $dayEndTs     = strtotime($date . ' 23:59:59');

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
            $this->sendError('Kunde inte hämta timeline-data');
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
                }
            }

            $utnyttjandegrad = $plannadTidMin > 0
                ? round(($runningMin / $plannadTidMin) * 100, 1)
                : 0.0;

            $this->sendSuccess([
                'date'              => $date,
                'drifttid_min'      => round($runningMin, 1),
                'stopptid_min'      => round($stoppedMin, 1),
                'antal_stopp'       => $antalStopp,
                'langsta_korning_min' => round($langstaKorning, 1),
                'utnyttjandegrad_pct' => $utnyttjandegrad,
                'plannad_tid_min'   => $plannadTidMin,
            ]);
        } catch (\Exception $e) {
            error_log('DrifttidsTimelineController::getSummary: ' . $e->getMessage());
            $this->sendError('Kunde inte beräkna summary');
        }
    }
}
