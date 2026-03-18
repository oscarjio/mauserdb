<?php
/**
 * ProduktionskalenderController.php
 * Produktionskalender — månadsvy med per-dag KPI:er och detaljer.
 *
 * Endpoints via ?action=produktionskalender&run=XXX:
 *   run=month-data   &year=YYYY&month=MM → per-dag-data för hela månaden
 *   run=day-detail   &date=YYYY-MM-DD    → detaljerad data för specifik dag
 *
 * Tabeller: rebotling_ibc (datum, ibc_ok, ibc_ej_ok, op1, op2, op3, skiftraknare, lopnummer)
 *           rebotling_onoff (datum, running)
 *           rebotling_stopp (datum, orsak, sekunder)
 *           operators (number, name)
 */
class ProduktionskalenderController {
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
            case 'month-data':  $this->getMonthData();  break;
            case 'day-detail':  $this->getDayDetail();  break;
            default:
                $this->sendError('Ogiltig run: ' . htmlspecialchars($run, ENT_QUOTES, 'UTF-8'));
                break;
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
     * Hämta operatörsnamn-map: [nummer => namn]
     */
    private function getOperatorMap(): array {
        try {
            $stmt = $this->pdo->query("SELECT number, name FROM operators ORDER BY number");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $map = [];
            foreach ($rows as $r) {
                $map[(int)$r['number']] = $r['name'];
            }
            return $map;
        } catch (\Exception $e) {
            error_log('ProduktionskalenderController::getOperatorMap: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Beräkna kvalitet % = ok / (ok + ej_ok) * 100
     */
    private function kvalitet(int $ok, int $ejOk): float {
        $total = $ok + $ejOk;
        if ($total === 0) return 0.0;
        return round($ok / $total * 100, 1);
    }

    /**
     * Färgklass baserat på kvalitet % och om mål uppnåtts.
     * Mål: minst 1000 IBC OK per dag (kan justeras via rebotling_settings om tabellen finns).
     */
    private function fargklass(float $kvalPct, int $ibcOk, int $mål): string {
        if ($ibcOk === 0) return 'ingen';
        if ($kvalPct >= 90 && $ibcOk >= $mål) return 'gron';
        if ($kvalPct >= 70) return 'gul';
        return 'rod';
    }

    /**
     * Hämta daglig drifttid och stopptid (sekunder) från rebotling_onoff.
     * Drifttid = summa av intervall där running=1.
     */
    private function getDrifttid(string $date): array {
        $drifttid  = 0;
        $stopptid  = 0;

        try {
            $stmt = $this->pdo->prepare(
                "SELECT datum, running FROM rebotling_onoff
                 WHERE DATE(datum) = ?
                 ORDER BY datum ASC"
            );
            $stmt->execute([$date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $prevTime    = null;
            $prevRunning = null;

            foreach ($rows as $row) {
                $ts      = strtotime($row['datum']);
                $running = (int)$row['running'];

                if ($prevTime !== null) {
                    $diff = $ts - $prevTime;
                    if ($prevRunning === 1) {
                        $drifttid += $diff;
                    } else {
                        $stopptid += $diff;
                    }
                }

                $prevTime    = $ts;
                $prevRunning = $running;
            }
        } catch (\Exception $e) {
            error_log('ProduktionskalenderController::getDrifttid: ' . $e->getMessage());
        }

        return ['drifttid' => $drifttid, 'stopptid' => $stopptid];
    }

    // ================================================================
    // ENDPOINT: month-data
    // ================================================================

    private function getMonthData(): void {
        $year  = (int)($_GET['year']  ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));

        if ($year < 2020 || $year > 2030 || $month < 1 || $month > 12) {
            $this->sendError('Ogiltigt år/månad');
            return;
        }

        // Hämta daglig sammanfattning för hela månaden
        $fromDate = sprintf('%04d-%02d-01', $year, $month);
        $toDate   = date('Y-m-t', strtotime($fromDate)); // sista dagen i månaden

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(max_ibc_ok) AS ibc_ok,
                    SUM(max_ibc_ej_ok) AS ibc_ej_ok,
                    SUM(max_ibc_ok) + SUM(max_ibc_ej_ok) AS ibc_total,
                    MIN(forsta) AS forsta_cykel,
                    MAX(sista) AS sista_cykel
                FROM (
                    SELECT
                        DATE(datum) AS dag,
                        skiftraknare,
                        MAX(ibc_ok) AS max_ibc_ok,
                        MAX(ibc_ej_ok) AS max_ibc_ej_ok,
                        MIN(datum) AS forsta,
                        MAX(datum) AS sista
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ?
                      AND lopnummer > 0 AND lopnummer < 998
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
                ORDER BY dag ASC
            ");
            $stmt->execute([$fromDate, $toDate]);
            $dagRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('ProduktionskalenderController::getMonthData: ' . $e->getMessage());
            $this->sendError('Databasfel vid hämtning av månadsdata');
            return;
        }

        // Hämta mål från rebotling_settings om möjligt
        $mål = 1000;
        try {
            $mStmt = $this->pdo->query("SELECT rebotling_target FROM rebotling_settings WHERE id = 1");
            $mRow  = $mStmt->fetch(PDO::FETCH_ASSOC);
            if ($mRow) $mål = (int)$mRow['rebotling_target'];
        } catch (\Exception $e) {
            error_log('ProduktionskalenderController::getMonthData (settings): ' . $e->getMessage());
        }

        // Bygg per-dag-data
        $dagData = [];
        foreach ($dagRows as $row) {
            $ibcOk   = (int)$row['ibc_ok'];
            $ibcEjOk = (int)$row['ibc_ej_ok'];
            $kval    = $this->kvalitet($ibcOk, $ibcEjOk);
            $farg    = $this->fargklass($kval, $ibcOk, $mål);

            // Beräkna IBC/h baserat på cykeltid
            $ibcH = null;
            if ($row['forsta_cykel'] && $row['sista_cykel'] && $ibcOk > 0) {
                $diffSek = strtotime($row['sista_cykel']) - strtotime($row['forsta_cykel']);
                if ($diffSek > 60) {
                    $ibcH = round($ibcOk / ($diffSek / 3600), 1);
                }
            }

            $dagData[$row['dag']] = [
                'datum'    => $row['dag'],
                'ibc_ok'   => $ibcOk,
                'ibc_ej_ok' => $ibcEjOk,
                'ibc_total' => (int)$row['ibc_total'],
                'kvalitet'  => $kval,
                'farg'      => $farg,
                'ibc_h'     => $ibcH,
                'har_data'  => true,
            ];
        }

        // Månadssammanfattning
        $summary = $this->buildMonthlySummary($dagData);

        // Veckodata (snitt + trend)
        $veckoData = $this->buildVeckoData($dagData, $year, $month);

        $this->sendSuccess([
            'year'      => $year,
            'month'     => $month,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'mal'       => $mål,
            'dagar'     => $dagData,
            'summary'   => $summary,
            'veckor'    => $veckoData,
        ]);
    }

    private function buildMonthlySummary(array $dagData): array {
        if (empty($dagData)) {
            return [
                'totalt_ibc'     => 0,
                'snitt_kvalitet' => 0,
                'basta_dag'      => null,
                'samsta_dag'     => null,
                'grona_dagar'    => 0,
                'gula_dagar'     => 0,
                'roda_dagar'     => 0,
                'dagar_med_data' => 0,
            ];
        }

        $totalt = 0;
        $kvalSum = 0;
        $basta  = null;
        $samsta = null;
        $grona  = 0;
        $gula   = 0;
        $roda   = 0;

        foreach ($dagData as $dag => $d) {
            $totalt  += $d['ibc_ok'];
            $kvalSum += $d['kvalitet'];

            if ($basta === null || $d['ibc_ok'] > $dagData[$basta]['ibc_ok']) $basta = $dag;
            if ($samsta === null || $d['ibc_ok'] < $dagData[$samsta]['ibc_ok']) $samsta = $dag;

            if ($d['farg'] === 'gron') $grona++;
            elseif ($d['farg'] === 'gul') $gula++;
            elseif ($d['farg'] === 'rod') $roda++;
        }

        $antal = count($dagData);

        return [
            'totalt_ibc'     => $totalt,
            'snitt_kvalitet' => $antal > 0 ? round($kvalSum / $antal, 1) : 0,
            'basta_dag'      => $basta,
            'samsta_dag'     => $samsta,
            'grona_dagar'    => $grona,
            'gula_dagar'     => $gula,
            'roda_dagar'     => $roda,
            'dagar_med_data' => $antal,
        ];
    }

    private function buildVeckoData(array $dagData, int $year, int $month): array {
        // Hämta föregående månads veckosnitt för trend-jämförelse
        $prevMonth = $month - 1;
        $prevYear  = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevFrom = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
        $prevTo   = date('Y-m-t', strtotime($prevFrom));

        $prevDagData = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    dag,
                    SUM(max_ibc_ok) AS ibc_ok,
                    SUM(max_ibc_ej_ok) AS ibc_ej_ok
                FROM (
                    SELECT DATE(datum) AS dag, skiftraknare,
                           MAX(ibc_ok) AS max_ibc_ok, MAX(ibc_ej_ok) AS max_ibc_ej_ok
                    FROM rebotling_ibc
                    WHERE DATE(datum) BETWEEN ? AND ?
                      AND lopnummer > 0 AND lopnummer < 998
                    GROUP BY DATE(datum), skiftraknare
                ) AS per_skift
                GROUP BY dag
            ");
            $stmt->execute([$prevFrom, $prevTo]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $ibcOk   = (int)$r['ibc_ok'];
                $ibcEjOk = (int)$r['ibc_ej_ok'];
                $prevDagData[$r['dag']] = [
                    'ibc_ok'  => $ibcOk,
                    'kvalitet' => $this->kvalitet($ibcOk, $ibcEjOk),
                ];
            }
        } catch (\Exception $e) {
            error_log('ProduktionskalenderController::buildVeckoData (prev): ' . $e->getMessage());
        }

        // Gruppera dagar per ISO-vecka
        $veckor = [];
        foreach ($dagData as $dag => $d) {
            $ts   = strtotime($dag);
            $vecka = (int)date('W', $ts);
            if (!isset($veckor[$vecka])) {
                $veckor[$vecka] = ['vecka' => $vecka, 'dagar' => [], 'ibc_sum' => 0, 'kval_sum' => 0];
            }
            $veckor[$vecka]['dagar'][]  = $dag;
            $veckor[$vecka]['ibc_sum']  += $d['ibc_ok'];
            $veckor[$vecka]['kval_sum'] += $d['kvalitet'];
        }

        // Beräkna veckosnitt och trend mot föregående vecka
        $result = [];
        $prevVeckaSnitt = null;
        foreach (array_values($veckor) as $v) {
            $antal = count($v['dagar']);
            $snittIbc  = $antal > 0 ? round($v['ibc_sum'] / $antal, 1) : 0;
            $snittKval = $antal > 0 ? round($v['kval_sum'] / $antal, 1) : 0;

            // Föregående veckas data (från föregående månad)
            $foregatSnitt = null;
            $trend        = null;
            if ($prevVeckaSnitt !== null) {
                $diff  = $snittIbc - $prevVeckaSnitt;
                $trend = $diff > 0 ? 'upp' : ($diff < 0 ? 'ner' : 'stabil');
                $foregatSnitt = $prevVeckaSnitt;
            }
            $prevVeckaSnitt = $snittIbc;

            $result[] = [
                'vecka'          => $v['vecka'],
                'dagar'          => $v['dagar'],
                'snitt_ibc'      => $snittIbc,
                'snitt_kval'     => $snittKval,
                'foreg_snitt'    => $foregatSnitt,
                'trend'          => $trend,
            ];
        }

        return $result;
    }

    // ================================================================
    // ENDPOINT: day-detail
    // ================================================================

    private function getDayDetail(): void {
        $date = trim($_GET['date'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendError('Ogiltigt datum. Använd formatet YYYY-MM-DD');
            return;
        }

        $opMap = $this->getOperatorMap();

        // Grundläggande IBC-data (MAX per skiftraknare, then SUM)
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(max_ibc_ok), 0) AS ibc_ok,
                    COALESCE(SUM(max_ibc_ej_ok), 0) AS ibc_ej_ok,
                    COALESCE(SUM(max_ibc_ok) + SUM(max_ibc_ej_ok), 0) AS ibc_total,
                    MIN(forsta) AS forsta_cykel,
                    MAX(sista) AS sista_cykel
                FROM (
                    SELECT skiftraknare,
                           MAX(ibc_ok) AS max_ibc_ok,
                           MAX(ibc_ej_ok) AS max_ibc_ej_ok,
                           MIN(datum) AS forsta,
                           MAX(datum) AS sista
                    FROM rebotling_ibc
                    WHERE DATE(datum) = ?
                      AND lopnummer > 0 AND lopnummer < 998
                    GROUP BY skiftraknare
                ) AS per_skift
            ");
            $stmt->execute([$date]);
            $base = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('ProduktionskalenderController::getDayDetail: ' . $e->getMessage());
            $this->sendError('Databasfel vid hämtning av dagdetaljer');
            return;
        }

        $ibcOk   = (int)($base['ibc_ok'] ?? 0);
        $ibcEjOk = (int)($base['ibc_ej_ok'] ?? 0);
        $kval    = $this->kvalitet($ibcOk, $ibcEjOk);

        // IBC/h
        $ibcH = null;
        if ($base['forsta_cykel'] && $base['sista_cykel'] && $ibcOk > 0) {
            $diffSek = strtotime($base['sista_cykel']) - strtotime($base['forsta_cykel']);
            if ($diffSek > 60) {
                $ibcH = round($ibcOk / ($diffSek / 3600), 1);
            }
        }

        // Drifttid / stopptid från rebotling_onoff
        $drift = $this->getDrifttid($date);

        // OEE-beräkning: Tillgänglighet × Prestanda × Kvalitet
        $oee = null;
        $totalTid = $drift['drifttid'] + $drift['stopptid'];
        if ($totalTid > 0 && $ibcH !== null) {
            $tillgänglighet = $drift['drifttid'] / $totalTid;
            $prestanda      = min(1.0, $ibcH / 60); // anta mål 60 IBC/h
            $kvalitetsFaktor = ($ibcOk + $ibcEjOk) > 0 ? $ibcOk / ($ibcOk + $ibcEjOk) : 0;
            $oee = round($tillgänglighet * $prestanda * $kvalitetsFaktor * 100, 1);
        }

        // Top 5 operatörer
        $topp5 = $this->getTop5Operatorer($date, $opMap);

        // Stopporsaker
        $stopporsaker = $this->getStopporsaker($date);

        $this->sendSuccess([
            'datum'      => $date,
            'ibc_ok'     => $ibcOk,
            'ibc_ej_ok'  => $ibcEjOk,
            'ibc_total'  => $ibcOk + $ibcEjOk,
            'kvalitet'   => $kval,
            'ibc_h'      => $ibcH,
            'drifttid'   => $drift['drifttid'],
            'stopptid'   => $drift['stopptid'],
            'oee'        => $oee,
            'topp5'      => $topp5,
            'stopporsaker' => $stopporsaker,
        ]);
    }

    private function getTop5Operatorer(string $date, array $opMap): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT op, SUM(cnt) AS ibc_ok
                FROM (
                    SELECT op1 AS op, COUNT(*) AS cnt
                    FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op1 IS NOT NULL AND op1 > 0
                      AND lopnummer > 0 AND lopnummer < 998
                    GROUP BY op1

                    UNION ALL

                    SELECT op2 AS op, COUNT(*) AS cnt
                    FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op2 IS NOT NULL AND op2 > 0
                      AND lopnummer > 0 AND lopnummer < 998
                    GROUP BY op2

                    UNION ALL

                    SELECT op3 AS op, COUNT(*) AS cnt
                    FROM rebotling_ibc
                    WHERE DATE(datum) = ? AND op3 IS NOT NULL AND op3 > 0
                      AND lopnummer > 0 AND lopnummer < 998
                    GROUP BY op3
                ) AS sub
                GROUP BY op
                ORDER BY ibc_ok DESC
                LIMIT 5
            ");
            $stmt->execute([$date, $date, $date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $i => $row) {
                $opNr = (int)$row['op'];
                $result[] = [
                    'rank'       => $i + 1,
                    'operator_id' => $opNr,
                    'namn'       => $opMap[$opNr] ?? ('Operatör #' . $opNr),
                    'ibc_ok'     => (int)$row['ibc_ok'],
                ];
            }
            return $result;
        } catch (\Exception $e) {
            error_log('ProduktionskalenderController::getTop5Operatorer: ' . $e->getMessage());
            return [];
        }
    }

    private function getStopporsaker(string $date): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT orsak, SUM(sekunder) AS total_sek, COUNT(*) AS antal
                FROM rebotling_stopp
                WHERE DATE(datum) = ?
                GROUP BY orsak
                ORDER BY total_sek DESC
                LIMIT 10
            ");
            $stmt->execute([$date]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $result[] = [
                    'orsak'     => $row['orsak'] ?? 'Okänd',
                    'sekunder'  => (int)$row['total_sek'],
                    'minuter'   => round((int)$row['total_sek'] / 60, 1),
                    'antal'     => (int)$row['antal'],
                ];
            }
            return $result;
        } catch (\Exception $e) {
            error_log('ProduktionskalenderController::getStopporsaker: ' . $e->getMessage());
            return [];
        }
    }
}
