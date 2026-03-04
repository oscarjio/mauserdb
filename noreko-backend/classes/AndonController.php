<?php
/**
 * AndonController.php
 * Hanterar API-anrop för Andon-tavlan (/rebotling/andon).
 * Publik endpoint — kräver ingen autentisering.
 *
 * Endpoints:
 *   api.php?action=andon&run=status
 *   api.php?action=andon&run=recent-stoppages
 *   api.php?action=andon&run=andon-notes
 */
class AndonController {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function handle(): void {
        $run = strtolower(trim($_GET['run'] ?? ''));

        if ($run === 'status') {
            $this->getStatus();
        } elseif ($run === 'recent-stoppages') {
            $this->recentStoppages();
        } elseif ($run === 'andon-notes') {
            $this->andonNotes();
        } elseif ($run === 'hourly-today') {
            $this->getHourlyToday();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Okänd metod']);
        }
    }

    /**
     * Returnerar aktuell andon-status för rebotling-linjen.
     */
    private function getStatus(): void {
        try {
            $nu    = new DateTimeImmutable('now');
            $datum = $nu->format('Y-m-d');
            $timme = (int)$nu->format('H');

            // Beräkna skift baserat på timme
            if ($timme >= 6 && $timme < 14) {
                $skift = 'Morgon';
            } elseif ($timme >= 14 && $timme < 22) {
                $skift = 'Eftermiddag';
            } else {
                $skift = 'Natt';
            }

            // ---- Dagsmål från rebotling_settings ----
            $malIdag = 100; // fallback
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT value FROM rebotling_settings WHERE setting = 'dagmal' LIMIT 1"
                );
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && is_numeric($row['value']) && (int)$row['value'] > 0) {
                    $malIdag = (int)$row['value'];
                }
            } catch (\Exception $e) {
                error_log('AndonController dagmal: ' . $e->getMessage());
            }

            // ---- Dagens IBC och runtime från rebotling_ibc ----
            // Hämta senaste skiftraknaren för idag och MAX(ibc_ok) per skiftraknare
            $stmt = $this->pdo->prepare("
                SELECT
                    MAX(ibc_ok)                                           AS ibc_idag,
                    MAX(runtime_plc)                                      AS runtime_sek,
                    MAX(rasttime)                                         AS rasttime_sek,
                    MIN(datum)                                            AS forsta_post,
                    MAX(datum)                                            AS senaste_ibc_tid,
                    TIMESTAMPDIFF(MINUTE, MIN(datum), NOW())              AS total_min
                FROM rebotling_ibc
                WHERE DATE(datum) = :datum
            ");
            $stmt->execute([':datum' => $datum]);
            $rad = $stmt->fetch(PDO::FETCH_ASSOC);

            $ibcIdag        = (int)($rad['ibc_idag']         ?? 0);
            $runtimeSek     = (int)($rad['runtime_sek']       ?? 0);
            $rasttimeSek    = (int)($rad['rasttime_sek']       ?? 0);
            $senasteTid     = $rad['senaste_ibc_tid']          ?? null;
            $totalMin       = max(1, (int)($rad['total_min']  ?? 1));

            $runtimeMin     = (int)round($runtimeSek / 60);
            $rasttimeMin    = (int)round($rasttimeSek / 60);

            // IBC per timme (baserat på total elapsed tid sedan första posten idag)
            $totalH  = $totalMin / 60;
            $ibcPerH = $totalH > 0 ? round($ibcIdag / $totalH, 1) : 0.0;

            // OEE: (faktisk output) / (teoretisk max output) * 100
            // Teoretisk max: drifttid (h) * taktmål (IBC/h)
            // Taktmål hämtas från rebotling_settings 'takt_mal', fallback 25
            $taktMal = 25;
            try {
                $stmtTakt = $this->pdo->prepare(
                    "SELECT value FROM rebotling_settings WHERE setting = 'takt_mal' LIMIT 1"
                );
                $stmtTakt->execute();
                $taktRad = $stmtTakt->fetch(PDO::FETCH_ASSOC);
                if ($taktRad && is_numeric($taktRad['value']) && (float)$taktRad['value'] > 0) {
                    $taktMal = (float)$taktRad['value'];
                }
            } catch (\Exception $e) {
                error_log('AndonController takt_mal: ' . $e->getMessage());
            }

            $runtimeH = $runtimeSek > 0 ? ($runtimeSek / 3600) : ($totalMin / 60);
            if ($runtimeH > 0 && $ibcIdag > 0) {
                $oeePct = round(($ibcIdag / ($runtimeH * $taktMal)) * 100, 1);
            } else {
                $oeePct = 0.0;
            }
            // Begränsa till 0–150%
            $oeePct = max(0.0, min(150.0, $oeePct));

            // Dagsmål-procent
            $malPct = $malIdag > 0 ? round(($ibcIdag / $malIdag) * 100, 1) : 0.0;

            // Minuter sedan senaste IBC
            $minuterSedanSenaste = 9999;
            if ($senasteTid) {
                $senaste = new DateTimeImmutable($senasteTid);
                $diff    = $nu->getTimestamp() - $senaste->getTimestamp();
                $minuterSedanSenaste = max(0, (int)round($diff / 60));
            }

            // Linjestatus
            if ($ibcIdag === 0 || $minuterSedanSenaste > 30) {
                $linjeStatus = 'stopp';
            } elseif ($minuterSedanSenaste >= 10) {
                $linjeStatus = 'väntar';
            } else {
                $linjeStatus = 'kör';
            }

            echo json_encode([
                'datum'                   => $datum,
                'skift'                   => $skift,
                'ibc_idag'                => $ibcIdag,
                'mal_idag'                => $malIdag,
                'mal_pct'                 => $malPct,
                'oee_pct'                 => $oeePct,
                'ibc_per_h'               => $ibcPerH,
                'runtime_min'             => $runtimeMin,
                'rasttime_min'            => $rasttimeMin,
                'senaste_ibc_tid'         => $senasteTid ?? '',
                'minuter_sedan_senaste_ibc' => $minuterSedanSenaste < 9999 ? $minuterSedanSenaste : null,
                'linje_status'            => $linjeStatus,
            ]);

        } catch (\Exception $e) {
            error_log('AndonController::getStatus fel: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel']);
        }
    }

    /**
     * Returnerar de 5 senaste stoppregistreringarna de senaste 24 timmarna.
     * Publik endpoint — ingen autentisering krävs.
     */
    private function recentStoppages(): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    sr.id,
                    sr.reason_id,
                    sr.duration_minutes,
                    sr.created_at,
                    sr.notes,
                    r.name  AS reason_name,
                    r.category
                FROM stoppage_log sr
                JOIN stoppage_reasons r ON sr.reason_id = r.id
                WHERE sr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY sr.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $result[] = [
                    'id'               => (int)$row['id'],
                    'reason_name'      => $row['reason_name'],
                    'category'         => $row['category'],
                    'duration_minutes' => (int)$row['duration_minutes'],
                    'created_at'       => $row['created_at'],
                    'notes'            => $row['notes'] ?? '',
                ];
            }

            echo json_encode(['success' => true, 'stoppages' => $result]);

        } catch (\Exception $e) {
            error_log('AndonController::recentStoppages fel: ' . $e->getMessage());
            // Tabellen kanske inte finns — returnera tom lista gracefully
            echo json_encode(['success' => true, 'stoppages' => []]);
        }
    }

    /**
     * Returnerar okvitterade skiftöverlämningsnoter för Andon-tavlan.
     * Publik endpoint — ingen autentisering krävs.
     * Hämtar noter riktade till 'alla' eller 'ansvarig', sorterat på prioritet.
     */
    private function andonNotes(): void {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    note,
                    priority,
                    op_name,
                    created_at
                FROM shift_handover
                WHERE acknowledged_at IS NULL
                  AND (audience = 'alla' OR audience = 'ansvarig')
                ORDER BY
                    FIELD(priority, 'urgent', 'important', 'normal'),
                    created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notes = [];
            foreach ($rows as $row) {
                $notes[] = [
                    'id'         => (int)$row['id'],
                    'note'       => $row['note'],
                    'priority'   => $row['priority'],
                    'op_name'    => $row['op_name'],
                    'created_at' => $row['created_at'],
                ];
            }

            echo json_encode([
                'success'      => true,
                'notes'        => $notes,
                'unread_count' => count($notes),
            ]);

        } catch (\Exception $e) {
            error_log('AndonController::andonNotes fel: ' . $e->getMessage());
            // Tabell kanske inte finns — returnera tomt gracefully
            echo json_encode(['success' => true, 'notes' => [], 'unread_count' => 0]);
        }
    }

    /**
     * Returnerar kumulativ IBC-produktion per timme för dagens datum (06–22).
     * Används för S-kurvan i Andon-tavlan.
     * GET api.php?action=andon&run=hourly-today
     */
    private function getHourlyToday(): void {
        try {
            $nu    = new DateTimeImmutable('now');
            $datum = $nu->format('Y-m-d');

            // Hämta MAX(ibc_ok) per skiftraknare och timme — kumulativa värden
            // Vi grupperar per timme och tar max ibc_ok för den timmen
            $stmt = $this->pdo->prepare("
                SELECT
                    HOUR(datum) AS timme,
                    MAX(ibc_ok) AS ibc_max_timme
                FROM rebotling_ibc
                WHERE DATE(datum) = :datum
                  AND HOUR(datum) BETWEEN 6 AND 22
                GROUP BY HOUR(datum)
                ORDER BY timme
            ");
            $stmt->execute([':datum' => $datum]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hämta dagsmål
            $malIdag = 100;
            try {
                $stmtMal = $this->pdo->prepare(
                    "SELECT value FROM rebotling_settings WHERE setting = 'dagmal' LIMIT 1"
                );
                $stmtMal->execute();
                $malRad = $stmtMal->fetch(PDO::FETCH_ASSOC);
                if ($malRad && is_numeric($malRad['value']) && (int)$malRad['value'] > 0) {
                    $malIdag = (int)$malRad['value'];
                }
            } catch (\Exception $e) {
                error_log('AndonController hourly-today dagmal: ' . $e->getMessage());
            }

            // Bygg upp kumulativ data per timme 6–22
            // ibc_ok är kumulativt i tabellen — MAX per timme ger oss värdet vid slutet av timmen
            $ibcPerTimme = [];
            foreach ($rows as $r) {
                $ibcPerTimme[(int)$r['timme']] = (int)$r['ibc_max_timme'];
            }

            $nuTimme = (int)$nu->format('H');
            $result  = [];
            $skiftDuration = 16; // 06:00–22:00 = 16 timmar

            for ($h = 6; $h <= 22; $h++) {
                // Planerat kumulativt värde vid slutet av timme h
                $planKumulativ = round($malIdag * (($h - 6 + 1) / $skiftDuration));

                // Faktisk kumulativ IBC: om timme har passerat, ta max känt värde
                $faktiskKumulativ = null;
                if ($h <= $nuTimme && isset($ibcPerTimme[$h])) {
                    $faktiskKumulativ = $ibcPerTimme[$h];
                } elseif ($h < $nuTimme) {
                    // Timme har passerat men saknar data — ta senaste kända värde
                    for ($bh = $h; $bh >= 6; $bh--) {
                        if (isset($ibcPerTimme[$bh])) {
                            $faktiskKumulativ = $ibcPerTimme[$bh];
                            break;
                        }
                    }
                }

                $result[] = [
                    'timme'            => $h,
                    'label'            => sprintf('%02d:00', $h),
                    'plan_kumulativ'   => $planKumulativ,
                    'faktisk_kumulativ'=> $faktiskKumulativ,
                ];
            }

            echo json_encode([
                'success'  => true,
                'datum'    => $datum,
                'mal_idag' => $malIdag,
                'data'     => $result,
            ]);

        } catch (\Exception $e) {
            error_log('AndonController::getHourlyToday fel: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internt serverfel']);
        }
    }
}
