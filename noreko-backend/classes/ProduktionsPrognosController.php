<?php
/**
 * ProduktionsPrognosController.php
 *
 * Skiftbaserad produktionsprognos for VD-vy.
 * "Ni har gjort X IBC hittills, takten är Y IBC/h, beräknat Z IBC vid skiftslut"
 *
 * Endpoints via ?action=produktionsprognos&run=XXX:
 *   - run=forecast   — aktuellt skift, IBC hittills, takt (IBC/h), prognos till skiftslut, tid kvar
 *   - run=shift-history — senaste 10 skiftens faktiska resultat for jämförelse
 *
 * Skiftider: dag 06:00-14:00, kväll 14:00-22:00, natt 22:00-06:00
 * Auth: session kravs (401 om ej inloggad).
 */
class ProduktionsPrognosController {
    private $pdo;

    // Skiftstarttider HH:MM => sluttid HH:MM
    private const SHIFTS = [
        'dag'   => ['start' => '06:00', 'end' => '14:00'],
        'kväll' => ['start' => '14:00', 'end' => '22:00'],
        'natt'  => ['start' => '22:00', 'end' => '06:00'], // slut nasta dag
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
            case 'forecast':      $this->getForecast();     break;
            case 'shift-history': $this->getShiftHistory(); break;
            default:              $this->sendError('Ogiltig run: ' . htmlspecialchars($run)); break;
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
     * Returnera kolumnnamnet for timestamp i rebotling_ibc.
     */
    private function getIbcTimestampColumn(): string {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM rebotling_ibc")->fetchAll(\PDO::FETCH_COLUMN);
            return in_array('timestamp', $cols, true) ? 'timestamp' : 'datum';
        } catch (\Exception $e) {
            error_log('ProduktionsPrognosController::getIbcTimestampColumn: ' . $e->getMessage());
            return 'datum';
        }
    }

    /**
     * Beräkna aktuellt skift baserat pa nuvarande tid.
     * Returnerar: ['name' => 'dag|kväll|natt', 'start' => DateTime, 'end' => DateTime]
     */
    private function getCurrentShift(): array {
        $now = new \DateTime();
        $today = $now->format('Y-m-d');

        // dag  06:00-14:00
        $dagStart   = new \DateTime($today . ' 06:00:00');
        $dagEnd     = new \DateTime($today . ' 14:00:00');
        // kväll 14:00-22:00
        $kvallStart = new \DateTime($today . ' 14:00:00');
        $kvallEnd   = new \DateTime($today . ' 22:00:00');
        // natt  22:00-06:00 nasta dag
        $nattStart  = new \DateTime($today . ' 22:00:00');
        $nattEnd    = (clone $nattStart)->modify('+8 hours'); // 06:00 imorgon

        // Natt fran foregaende dag (22:00 igår - 06:00 idag)
        $prevNattStart = new \DateTime($today . ' 22:00:00');
        $prevNattStart->modify('-1 day');
        $prevNattEnd   = new \DateTime($today . ' 06:00:00');

        if ($now >= $prevNattStart && $now < $prevNattEnd) {
            return ['name' => 'natt',  'start' => $prevNattStart, 'end' => $prevNattEnd];
        } elseif ($now >= $dagStart && $now < $dagEnd) {
            return ['name' => 'dag',   'start' => $dagStart,  'end' => $dagEnd];
        } elseif ($now >= $kvallStart && $now < $kvallEnd) {
            return ['name' => 'kväll', 'start' => $kvallStart, 'end' => $kvallEnd];
        } else {
            // natt idag (22:00 - 06:00 imorgon)
            return ['name' => 'natt',  'start' => $nattStart,  'end' => $nattEnd];
        }
    }

    /**
     * Hämta dagsmål (IBC/dag). Försöker läsa från rebotling_settings + undantag.
     * Returnerar null om ingen tabell finns.
     */
    private function getDagsMal(): ?int {
        $mal = null;
        try {
            $sRow = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);
            if ($sRow) $mal = (int)$sRow['rebotling_target'];
        } catch (\Exception $e) {
            error_log('ProduktionsPrognosController::getDagsMal (settings): ' . $e->getMessage());
        }

        // Undantag for idag
        try {
            $stmtEx = $this->pdo->prepare('SELECT justerat_mal FROM produktionsmal_undantag WHERE datum = CURDATE()');
            $stmtEx->execute();
            $exRow = $stmtEx->fetch(\PDO::FETCH_ASSOC);
            if ($exRow) $mal = (int)$exRow['justerat_mal'];
        } catch (\Exception $e) {
            error_log('ProduktionsPrognosController::getDagsMal (undantag): ' . $e->getMessage());
        }

        return $mal;
    }

    // ================================================================
    // run=forecast — Prognos for aktuellt skift
    // ================================================================

    private function getForecast(): void {
        $now   = new \DateTime();
        $shift = $this->getCurrentShift();
        $ibcCol = $this->getIbcTimestampColumn();

        $shiftStartStr = $shift['start']->format('Y-m-d H:i:s');
        $shiftEndStr   = $shift['end']->format('Y-m-d H:i:s');
        $nowStr        = $now->format('Y-m-d H:i:s');

        // -- IBC hittills i skiftet --
        $ibcHittills = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM rebotling_ibc
                WHERE {$ibcCol} >= :shift_start
                  AND {$ibcCol} <= :now_dt
            ");
            $stmt->execute([':shift_start' => $shiftStartStr, ':now_dt' => $nowStr]);
            $ibcHittills = (int)($stmt->fetchColumn() ?: 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsPrognosController::getForecast (ibc_hittills): ' . $e->getMessage());
        }

        // -- Tid gangen i skiftet (sekunder) --
        $shiftStartTs  = $shift['start']->getTimestamp();
        $shiftEndTs    = $shift['end']->getTimestamp();
        $nowTs         = $now->getTimestamp();
        $shiftDuration = $shiftEndTs - $shiftStartTs; // 8 h = 28800 s
        $elapsed       = max(1, $nowTs - $shiftStartTs);
        $tidKvarSek    = max(0, $shiftEndTs - $nowTs);

        // -- Takt: IBC/h baserat pa faktisk tid gangen --
        $elapsedHours = $elapsed / 3600.0;
        $taktPerTimme = ($elapsedHours > 0) ? round($ibcHittills / $elapsedHours, 1) : 0.0;

        // -- Prognos: IBC vid skiftslut --
        $shiftDurationHours = $shiftDuration / 3600.0;
        $prognos = ($taktPerTimme > 0)
            ? (int)round($taktPerTimme * $shiftDurationHours)
            : $ibcHittills;

        // -- Historisk snitttakt for detta skiftnamn (senaste 14 dagar, exkl. idag) --
        $snittTakt = null;
        try {
            $snittTakt = $this->getHistoricalAvgRate($shift['name'], 14, $ibcCol);
        } catch (\Exception $e) {
            error_log('ProduktionsPrognosController::getForecast (snittTakt): ' . $e->getMessage());
        }

        // -- Trendindikator: jamfor aktuell takt mot historiskt snitt --
        $trendStatus = 'okant'; // 'bättre' | 'sämre' | 'i snitt' | 'okant'
        $trendPct    = null;
        if ($snittTakt !== null && $snittTakt > 0 && $taktPerTimme > 0) {
            $diff = (($taktPerTimme - $snittTakt) / $snittTakt) * 100;
            $trendPct = round($diff, 1);
            if ($diff > 5)       $trendStatus = 'bättre';
            elseif ($diff < -5)  $trendStatus = 'sämre';
            else                 $trendStatus = 'i snitt';
        }

        // -- Dagsmål --
        $dagsMal = $this->getDagsMal();

        // -- Progress mot dagsmål (hela dagen) --
        $ibcIdag = 0;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM rebotling_ibc
                WHERE DATE({$ibcCol}) = CURDATE()
            ");
            $stmt->execute();
            $ibcIdag = (int)($stmt->fetchColumn() ?: 0);
        } catch (\PDOException $e) {
            error_log('ProduktionsPrognosController::getForecast (ibc_idag): ' . $e->getMessage());
        }

        $progressPct = null;
        if ($dagsMal !== null && $dagsMal > 0) {
            $progressPct = min(100, round(($ibcIdag / $dagsMal) * 100, 1));
        }

        // -- Formateade tider --
        $tidKvarMin = (int)floor($tidKvarSek / 60);
        $tidKvarH   = (int)floor($tidKvarMin / 60);
        $tidKvarMod = $tidKvarMin % 60;

        // -- Elapsed progress (for progress bar i skiftet) --
        $shiftElapsedPct = min(100, round(($elapsed / $shiftDuration) * 100, 1));

        $this->sendSuccess([
            'skift_namn'         => $shift['name'],
            'skift_start'        => $shiftStartStr,
            'skift_slut'         => $shiftEndStr,
            'ibc_hittills'       => $ibcHittills,
            'ibc_idag'           => $ibcIdag,
            'takt_per_timme'     => $taktPerTimme,
            'snitt_takt'         => $snittTakt,
            'trend_status'       => $trendStatus,
            'trend_pct'          => $trendPct,
            'prognos_vid_slut'   => $prognos,
            'dags_mal'           => $dagsMal,
            'progress_pct'       => $progressPct,
            'tid_kvar_sek'       => $tidKvarSek,
            'tid_kvar_h'         => $tidKvarH,
            'tid_kvar_min'       => $tidKvarMod,
            'shift_elapsed_pct'  => $shiftElapsedPct,
            'nu'                 => $nowStr,
        ]);
    }

    /**
     * Beräkna historisk genomsnittstakt (IBC/h) for ett visst skift-namn
     * over de senaste $days dagarna (exkl. idag).
     */
    private function getHistoricalAvgRate(string $shiftName, int $days, string $ibcCol): ?float {
        $shiftDef = self::SHIFTS[$shiftName] ?? null;
        if (!$shiftDef) return null;

        $today    = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        // Bygg lista med skiftfönster for perioden
        $windows = [];
        $cur = new \DateTime($fromDate);
        $end = new \DateTime($today); // exkl. idag

        while ($cur < $end) {
            $dateStr = $cur->format('Y-m-d');
            $start = new \DateTime($dateStr . ' ' . $shiftDef['start'] . ':00');
            if ($shiftName === 'natt') {
                $shiftEnd = (clone $start)->modify('+8 hours');
            } else {
                $shiftEnd = new \DateTime($dateStr . ' ' . $shiftDef['end'] . ':00');
            }
            $windows[] = [$start->format('Y-m-d H:i:s'), $shiftEnd->format('Y-m-d H:i:s')];
            $cur->modify('+1 day');
        }

        if (empty($windows)) return null;

        $totalIbc  = 0;
        $totalShifts = 0;

        foreach ($windows as [$wStart, $wEnd]) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM rebotling_ibc
                    WHERE {$ibcCol} >= :ws AND {$ibcCol} < :we
                ");
                $stmt->execute([':ws' => $wStart, ':we' => $wEnd]);
                $cnt = (int)($stmt->fetchColumn() ?: 0);
                if ($cnt > 0) {
                    $totalIbc    += $cnt;
                    $totalShifts++;
                }
            } catch (\PDOException $e) {
                error_log('ProduktionsPrognosController::getHistoricalAvgRate: ' . $e->getMessage());
            }
        }

        if ($totalShifts === 0) return null;
        // Genomsnittligt antal IBC per skift / 8 h = IBC/h
        return round(($totalIbc / $totalShifts) / 8.0, 1);
    }

    // ================================================================
    // run=shift-history — Senaste 10 skiften faktiska resultat
    // ================================================================

    private function getShiftHistory(): void {
        $ibcCol  = $this->getIbcTimestampColumn();
        $history = [];

        // Gå bakåt i tid, hitta de senaste 10 fullständiga skiften
        $now   = new \DateTime();
        $limit = 10;
        $found = 0;
        $maxDays = 30; // sök max 30 dagar bakåt

        // Generera alla skiftfönster bakåt i tid
        $allWindows = [];
        $startSearch = (clone $now)->modify("-{$maxDays} days");
        $day = clone $now;

        while ($day >= $startSearch && count($allWindows) < 100) {
            $dateStr = $day->format('Y-m-d');

            // Skift-ordning: natt, kväll, dag (nyast forst inom varje dag)
            $windows = [
                ['natt',  $dateStr . ' 22:00:00', (clone (new \DateTime($dateStr . ' 22:00:00')))->modify('+8 hours')->format('Y-m-d H:i:s')],
                ['kväll', $dateStr . ' 14:00:00', $dateStr . ' 22:00:00'],
                ['dag',   $dateStr . ' 06:00:00', $dateStr . ' 14:00:00'],
            ];

            foreach ($windows as $w) {
                $wEnd = new \DateTime($w[2]);
                // Ta bara skift som har slutat
                if ($wEnd < $now) {
                    $allWindows[] = [
                        'namn'  => $w[0],
                        'start' => $w[1],
                        'end'   => $w[2],
                        'date'  => $dateStr,
                    ];
                }
            }

            $day->modify('-1 day');
        }

        // Hämta IBC-räkningar for varje fönster tills vi har $limit
        foreach ($allWindows as $w) {
            if ($found >= $limit) break;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM rebotling_ibc
                    WHERE {$ibcCol} >= :ws AND {$ibcCol} < :we
                ");
                $stmt->execute([':ws' => $w['start'], ':we' => $w['end']]);
                $cnt = (int)($stmt->fetchColumn() ?: 0);

                // Ta med alla skift (även tomma — kan visa att linjen stod)
                $taktPerTimme = round($cnt / 8.0, 1);
                $history[] = [
                    'skift_namn'     => $w['namn'],
                    'skift_start'    => $w['start'],
                    'skift_slut'     => $w['end'],
                    'datum'          => $w['date'],
                    'ibc_totalt'     => $cnt,
                    'takt_per_timme' => $taktPerTimme,
                ];
                $found++;
            } catch (\PDOException $e) {
                error_log('ProduktionsPrognosController::getShiftHistory: ' . $e->getMessage());
            }
        }

        // Beräkna snitt
        $snittIbc  = 0;
        $snittTakt = 0.0;
        $nonEmpty  = array_filter($history, fn($h) => $h['ibc_totalt'] > 0);
        if (!empty($nonEmpty)) {
            $snittIbc  = (int)round(array_sum(array_column($nonEmpty, 'ibc_totalt')) / count($nonEmpty));
            $snittTakt = round(array_sum(array_column($nonEmpty, 'takt_per_timme')) / count($nonEmpty), 1);
        }

        $this->sendSuccess([
            'skift_historik' => $history,
            'snitt_ibc'      => $snittIbc,
            'snitt_takt'     => $snittTakt,
            'antal_skift'    => count($history),
        ]);
    }
}
