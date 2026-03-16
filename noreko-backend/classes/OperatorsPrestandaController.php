<?php
/**
 * OperatorsPrestandaController.php
 * Operatörs-prestanda scatter-plot — hastighet vs kvalitet per operatör.
 * VD kan snabbt identifiera vem som är snabb och noggrann.
 *
 * Endpoints via ?action=operatorsprestanda&run=XXX:
 *
 *   run=scatter-data&period=7|30|90[&skift=dag|kvall|natt]
 *       Per operatör: antal IBC, kassationsgrad, medel_cykeltid, OEE, dagar_aktiv.
 *
 *   run=operator-detalj&operator_id=X
 *       Daglig produktion + kassation + cykeltid senaste 30d för en operatör.
 *
 *   run=ranking&sort_by=ibc|kassation|oee|cykeltid&period=30
 *       Ranking-lista sorterad efter valt KPI.
 *
 *   run=teamjamforelse
 *       Medelvärden per skift: dag/kväll/natt.
 *
 *   run=utveckling&operator_id=X
 *       Veckovis trend senaste 12 veckor för en operatör.
 *
 * Tabeller: rebotling_skiftrapport (op1/op2/op3, ibc_ok, ibc_ej_ok, drifttid, datum)
 *           operators (number, name, active)
 *
 * Skift: Dag 06-14, Kväll 14-22, Natt 22-06
 * OEE per operatör: (ibc * ideal_cykeltid) / drifttid_aktiv
 */
class OperatorsPrestandaController {
    private $pdo;

    private const IDEAL_CYCLE_SEC = 120; // sekunder per IBC (ideal cykeltid)

    // Skiftdefinitioner: start (inkl) → slut (exkl)
    private const SKIFT = [
        'dag'   => ['label' => 'Dagskift',   'start' => 6,  'end' => 14],
        'kvall' => ['label' => 'Kvällsskift', 'start' => 14, 'end' => 22],
        'natt'  => ['label' => 'Nattskift',   'start' => 22, 'end' => 6],
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
            case 'scatter-data':    $this->getScatterData();    break;
            case 'operator-detalj': $this->getOperatorDetalj(); break;
            case 'ranking':         $this->getRanking();         break;
            case 'teamjamforelse':  $this->getTeamjamforelse();  break;
            case 'utveckling':      $this->getUtveckling();      break;
            default:
                $this->sendError('Ogiltig run-parameter: ' . htmlspecialchars($run));
        }
    }

    // ================================================================
    // Helpers
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
        return in_array($p, [7, 30, 90], true) ? $p : 30;
    }

    private function getValidSkift(): ?string {
        $s = strtolower(trim($_GET['skift'] ?? ''));
        return in_array($s, ['dag', 'kvall', 'natt'], true) ? $s : null;
    }

    /**
     * WHERE-fragment för skiftfiltrering på datum-kolumn.
     */
    private function skiftWhere(string $skift, string $col): string {
        if ($skift === 'natt') {
            return "(HOUR({$col}) >= 22 OR HOUR({$col}) < 6)";
        }
        $def = self::SKIFT[$skift];
        return "(HOUR({$col}) >= {$def['start']} AND HOUR({$col}) < {$def['end']})";
    }

    /**
     * Hämta alla aktiva operatörer: number => name
     */
    private function getOperatorNames(): array {
        $result = [];
        try {
            $stmt = $this->pdo->query("SELECT number, name FROM operators WHERE active = 1 ORDER BY name");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['number']] = $row['name'];
            }
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::getOperatorNames: ' . $e->getMessage());
        }
        return $result;
    }


    /**
     * Räkna IBC OK + EJ OK + drifttid per operatör från rebotling_skiftrapport.
     * Hanterar att en operatör kan sitta på op1/op2/op3.
     * Returnerar array: [opId => ['ibc_ok' => N, 'ibc_ej_ok' => N, 'drifttid' => M, 'dagar' => D]]
     */
    private function aggregeraSkiftdata(array $opIds, string $cutoff, ?string $skift): array {
        if (empty($opIds)) return [];

        $result = [];
        foreach ($opIds as $opId) {
            $result[$opId] = ['ibc_ok' => 0, 'ibc_ej_ok' => 0, 'drifttid' => 0, 'dagar' => 0];
        }

        // Bygg skiftfilter
        $skiftCond = $skift ? ' AND ' . $this->skiftWhere($skift, 'datum') : '';

        // Vi aggregerar via UNION ALL för op1/op2/op3
        try {
            $placeholders = implode(',', array_fill(0, count($opIds), '?'));

            // Bygg en query som aggregerar per operatör (kan sitta i op1, op2 eller op3)
            $sql = "
                SELECT
                    op_id,
                    COALESCE(SUM(ibc_ok_val), 0)    AS total_ok,
                    COALESCE(SUM(ibc_ej_ok_val), 0) AS total_ej_ok,
                    COALESCE(SUM(drifttid_val), 0)  AS total_drifttid,
                    COUNT(DISTINCT DATE(datum_val))  AS antal_dagar
                FROM (
                    SELECT op1 AS op_id,
                           ibc_ok AS ibc_ok_val,
                           COALESCE(ibc_ej_ok, 0) AS ibc_ej_ok_val,
                           COALESCE(drifttid, 0) AS drifttid_val,
                           datum AS datum_val
                    FROM rebotling_skiftrapport
                    WHERE op1 IN ({$placeholders}) AND datum >= ?" . $skiftCond . "
                    UNION ALL
                    SELECT op2 AS op_id,
                           ibc_ok AS ibc_ok_val,
                           COALESCE(ibc_ej_ok, 0) AS ibc_ej_ok_val,
                           COALESCE(drifttid, 0) AS drifttid_val,
                           datum AS datum_val
                    FROM rebotling_skiftrapport
                    WHERE op2 IN ({$placeholders}) AND datum >= ?" . $skiftCond . "
                    UNION ALL
                    SELECT op3 AS op_id,
                           ibc_ok AS ibc_ok_val,
                           COALESCE(ibc_ej_ok, 0) AS ibc_ej_ok_val,
                           COALESCE(drifttid, 0) AS drifttid_val,
                           datum AS datum_val
                    FROM rebotling_skiftrapport
                    WHERE op3 IN ({$placeholders}) AND datum >= ?" . $skiftCond . "
                ) AS sub
                WHERE op_id IN ({$placeholders})
                GROUP BY op_id
            ";

            $params = array_merge(
                $opIds, [$cutoff],
                $opIds, [$cutoff],
                $opIds, [$cutoff],
                $opIds
            );
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $id = (int)$row['op_id'];
                if (isset($result[$id])) {
                    $result[$id] = [
                        'ibc_ok'    => (int)$row['total_ok'],
                        'ibc_ej_ok' => (int)$row['total_ej_ok'],
                        'drifttid'  => (int)$row['total_drifttid'],
                        'dagar'     => (int)$row['antal_dagar'],
                    ];
                }
            }
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::aggregeraSkiftdata: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Beräkna OEE för en operatör baserat på skiftdata.
     * OEE = (ibc_ok * ideal_cykeltid) / drifttid_sek
     * Drifttid är i minuter i skiftrapport → konvertera till sekunder.
     */
    private function calcOee(int $ibcOk, int $drifttidMin): float {
        if ($drifttidMin <= 0) return 0.0;
        $drifttidSek = $drifttidMin * 60;
        $oee = ($ibcOk * self::IDEAL_CYCLE_SEC) / $drifttidSek;
        return round(min(1.0, $oee) * 100, 1);
    }

    /**
     * Beräkna medel-cykeltid i sekunder:
     * cykeltid = drifttid_sek / antal_ibc (om > 0)
     */
    private function calcMedelCykeltid(int $ibcTotalt, int $drifttidMin): float {
        if ($ibcTotalt <= 0 || $drifttidMin <= 0) return 0.0;
        $drifttidSek = $drifttidMin * 60;
        return round($drifttidSek / $ibcTotalt, 1);
    }

    // ================================================================
    // run=scatter-data
    // ================================================================

    private function getScatterData(): void {
        $period = $this->getPeriod();
        $skift  = $this->getValidSkift();
        $cutoff = date('Y-m-d', strtotime("-{$period} days"));

        $opNames = $this->getOperatorNames();
        $opIds   = array_keys($opNames);

        if (empty($opIds)) {
            $this->sendSuccess(['operatorer' => [], 'period' => $period]);
            return;
        }

        $data = $this->aggregeraSkiftdata($opIds, $cutoff, $skift);

        $operatorer = [];
        foreach ($opIds as $opId) {
            $d = $data[$opId] ?? ['ibc_ok' => 0, 'ibc_ej_ok' => 0, 'drifttid' => 0, 'dagar' => 0];
            $totalIbc = $d['ibc_ok'] + $d['ibc_ej_ok'];
            if ($totalIbc === 0) continue; // Hoppa över inaktiva

            $kassationsgrad = $totalIbc > 0
                ? round($d['ibc_ej_ok'] / $totalIbc * 100, 2)
                : 0.0;
            $medelCykeltid = $this->calcMedelCykeltid($totalIbc, $d['drifttid']);
            $oee = $this->calcOee($d['ibc_ok'], $d['drifttid']);

            // Bestäm skift för operatören baserat på flest arbetstimmar
            $skiftTyp = $this->getOperatorSkiftTyp($opId, $cutoff);

            $operatorer[] = [
                'operator_id'       => $opId,
                'operator_namn'     => $opNames[$opId],
                'antal_ibc'         => $totalIbc,
                'kassationsgrad'    => $kassationsgrad,
                'medel_cykeltid'    => $medelCykeltid,
                'oee'               => $oee,
                'antal_dagar_aktiv' => $d['dagar'],
                'skift_typ'         => $skiftTyp,
            ];
        }

        // Beräkna medelvärden för referenslinjer
        $medCykeltid = count($operatorer) > 0
            ? round(array_sum(array_column($operatorer, 'medel_cykeltid')) / count($operatorer), 1)
            : 0.0;
        $medKvalitet = count($operatorer) > 0
            ? round(100 - array_sum(array_column($operatorer, 'kassationsgrad')) / count($operatorer), 1)
            : 0.0;

        $this->sendSuccess([
            'operatorer'         => $operatorer,
            'medel_cykeltid'     => $medCykeltid,
            'medel_kvalitet_pct' => $medKvalitet,
            'period'             => $period,
            'skift_filter'       => $skift,
        ]);
    }

    /**
     * Avgör vilket skift en operatör primärt jobbar genom att räkna rader per skift.
     */
    private function getOperatorSkiftTyp(int $opId, string $cutoff): string {
        try {
            $sql = "
                SELECT
                    SUM(CASE WHEN HOUR(datum) >= 6  AND HOUR(datum) < 14 THEN 1 ELSE 0 END) AS dag,
                    SUM(CASE WHEN HOUR(datum) >= 14 AND HOUR(datum) < 22 THEN 1 ELSE 0 END) AS kvall,
                    SUM(CASE WHEN HOUR(datum) >= 22 OR  HOUR(datum) < 6  THEN 1 ELSE 0 END) AS natt
                FROM (
                    SELECT datum FROM rebotling_skiftrapport WHERE op1 = ? AND datum >= ?
                    UNION ALL
                    SELECT datum FROM rebotling_skiftrapport WHERE op2 = ? AND datum >= ?
                    UNION ALL
                    SELECT datum FROM rebotling_skiftrapport WHERE op3 = ? AND datum >= ?
                ) sub
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$opId, $cutoff, $opId, $cutoff, $opId, $cutoff]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $dag   = (int)($row['dag']   ?? 0);
            $kvall = (int)($row['kvall'] ?? 0);
            $natt  = (int)($row['natt']  ?? 0);
            if ($dag >= $kvall && $dag >= $natt) return 'dag';
            if ($kvall >= $natt) return 'kvall';
            return 'natt';
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::getOperatorSkiftTyp: ' . $e->getMessage());
            return 'dag';
        }
    }

    // ================================================================
    // run=operator-detalj
    // ================================================================

    private function getOperatorDetalj(): void {
        $opId = (int)($_GET['operator_id'] ?? 0);
        if ($opId <= 0) {
            $this->sendError('Ogiltigt operator_id');
            return;
        }

        $cutoff = date('Y-m-d', strtotime('-30 days'));
        $namn   = '';
        try {
            $s = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $s->execute([$opId]);
            $namn = (string)($s->fetchColumn() ?? "Operatör #{$opId}");
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::getOperatorDetalj name: ' . $e->getMessage());
            $namn = "Operatör #{$opId}";
        }

        // Daglig data: summa ibc ok/ej_ok + drifttid per dag
        $daglig = [];
        try {
            $sql = "
                SELECT
                    DATE(datum) AS dag,
                    SUM(ibc_ok) AS ibc_ok,
                    SUM(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_ok,
                    SUM(COALESCE(drifttid, 0)) AS drifttid_min
                FROM (
                    SELECT datum, ibc_ok, ibc_ej_ok, drifttid
                    FROM rebotling_skiftrapport WHERE op1 = ? AND datum >= ?
                    UNION ALL
                    SELECT datum, ibc_ok, ibc_ej_ok, drifttid
                    FROM rebotling_skiftrapport WHERE op2 = ? AND datum >= ?
                    UNION ALL
                    SELECT datum, ibc_ok, ibc_ej_ok, drifttid
                    FROM rebotling_skiftrapport WHERE op3 = ? AND datum >= ?
                ) sub
                GROUP BY DATE(datum)
                ORDER BY dag ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$opId, $cutoff, $opId, $cutoff, $opId, $cutoff]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $totalIbc = (int)$row['ibc_ok'] + (int)$row['ibc_ej_ok'];
                $kassGrad = $totalIbc > 0 ? round((int)$row['ibc_ej_ok'] / $totalIbc * 100, 1) : 0.0;
                $cykeltid = $this->calcMedelCykeltid($totalIbc, (int)$row['drifttid_min']);
                $daglig[] = [
                    'datum'          => $row['dag'],
                    'ibc_ok'         => (int)$row['ibc_ok'],
                    'ibc_ej_ok'      => (int)$row['ibc_ej_ok'],
                    'total_ibc'      => $totalIbc,
                    'kassationsgrad' => $kassGrad,
                    'cykeltid_sek'   => $cykeltid,
                    'drifttid_min'   => (int)$row['drifttid_min'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::getOperatorDetalj daglig: ' . $e->getMessage());
        }

        // Streak: antal dagar i rad med produktion
        $streak = 0;
        $sorted = array_reverse($daglig); // nyast först
        foreach ($sorted as $d) {
            if ($d['total_ibc'] > 0) $streak++;
            else break;
        }

        // Bästa/sämsta dag
        $bastaDag  = null;
        $sammstaDag = null;
        if (!empty($daglig)) {
            usort($daglig, fn($a, $b) => $b['total_ibc'] <=> $a['total_ibc']);
            $bastaDag   = $daglig[0];
            $sammstaDag = end($daglig);
            // Återställ sortering efter datum
            usort($daglig, fn($a, $b) => strcmp($a['datum'], $b['datum']));
        }

        $this->sendSuccess([
            'operator_id'   => $opId,
            'operator_namn' => $namn,
            'daglig'        => $daglig,
            'streak'        => $streak,
            'basta_dag'     => $bastaDag,
            'sammsta_dag'   => $sammstaDag,
        ]);
    }

    // ================================================================
    // run=ranking
    // ================================================================

    private function getRanking(): void {
        $period  = $this->getPeriod();
        $sortBy  = trim($_GET['sort_by'] ?? 'ibc');
        $validSort = ['ibc', 'kassation', 'oee', 'cykeltid'];
        if (!in_array($sortBy, $validSort, true)) $sortBy = 'ibc';

        $cutoff  = date('Y-m-d', strtotime("-{$period} days"));
        $opNames = $this->getOperatorNames();
        $opIds   = array_keys($opNames);

        if (empty($opIds)) {
            $this->sendSuccess(['ranking' => [], 'sort_by' => $sortBy, 'period' => $period]);
            return;
        }

        $data = $this->aggregeraSkiftdata($opIds, $cutoff, null);

        $ranking = [];
        foreach ($opIds as $opId) {
            $d = $data[$opId] ?? ['ibc_ok' => 0, 'ibc_ej_ok' => 0, 'drifttid' => 0, 'dagar' => 0];
            $totalIbc = $d['ibc_ok'] + $d['ibc_ej_ok'];
            if ($totalIbc === 0) continue;

            $kassationsgrad = $totalIbc > 0
                ? round($d['ibc_ej_ok'] / $totalIbc * 100, 2) : 0.0;
            $medelCykeltid  = $this->calcMedelCykeltid($totalIbc, $d['drifttid']);
            $oee            = $this->calcOee($d['ibc_ok'], $d['drifttid']);

            $ranking[] = [
                'operator_id'       => $opId,
                'operator_namn'     => $opNames[$opId],
                'antal_ibc'         => $totalIbc,
                'kassationsgrad'    => $kassationsgrad,
                'medel_cykeltid'    => $medelCykeltid,
                'oee'               => $oee,
                'antal_dagar_aktiv' => $d['dagar'],
            ];
        }

        // Sortera
        usort($ranking, function ($a, $b) use ($sortBy) {
            switch ($sortBy) {
                case 'ibc':       return $b['antal_ibc']      <=> $a['antal_ibc'];
                case 'kassation': return $a['kassationsgrad']  <=> $b['kassationsgrad']; // lägre = bättre
                case 'oee':       return $b['oee']             <=> $a['oee'];
                case 'cykeltid':  // lägre = bättre, men 0 = ingen data → sist
                    if ((float)$a['medel_cykeltid'] === 0.0 && (float)$b['medel_cykeltid'] === 0.0) return 0;
                    if ((float)$a['medel_cykeltid'] === 0.0) return 1;
                    if ((float)$b['medel_cykeltid'] === 0.0) return -1;
                    return $a['medel_cykeltid'] <=> $b['medel_cykeltid'];
                default: return $b['antal_ibc'] <=> $a['antal_ibc'];
            }
        });

        // Lägg till rank
        foreach ($ranking as $i => &$op) {
            $op['rank'] = $i + 1;
        }
        unset($op);

        $this->sendSuccess([
            'ranking'   => $ranking,
            'sort_by'   => $sortBy,
            'period'    => $period,
            'totalt'    => count($ranking),
        ]);
    }

    // ================================================================
    // run=teamjamforelse
    // ================================================================

    private function getTeamjamforelse(): void {
        $period = $this->getPeriod();
        $cutoff = date('Y-m-d', strtotime("-{$period} days"));
        $opNames = $this->getOperatorNames();

        $skiftResult = [];

        foreach (self::SKIFT as $skiftKey => $skiftDef) {
            $skiftCond = $this->skiftWhere($skiftKey, 'datum');
            try {
                $sql = "
                    SELECT
                        COALESCE(SUM(ibc_ok), 0)            AS total_ok,
                        COALESCE(SUM(ibc_ej_ok), 0)         AS total_ej_ok,
                        COALESCE(SUM(drifttid), 0)          AS total_drifttid,
                        COUNT(DISTINCT DATE(datum))          AS antal_dagar,
                        COUNT(*)                             AS antal_rader
                    FROM rebotling_skiftrapport
                    WHERE datum >= ? AND {$skiftCond}
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$cutoff]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log('OperatorsPrestandaController::getTeamjamforelse: ' . $e->getMessage());
                $row = ['total_ok' => 0, 'total_ej_ok' => 0, 'total_drifttid' => 0, 'antal_dagar' => 0, 'antal_rader' => 0];
            }

            $totalOk   = (int)($row['total_ok']       ?? 0);
            $totalEjOk = (int)($row['total_ej_ok']    ?? 0);
            $totalDrift = (int)($row['total_drifttid'] ?? 0);
            $antalDagar = (int)($row['antal_dagar']    ?? 0);
            $totalIbc  = $totalOk + $totalEjOk;

            $kassGrad  = $totalIbc > 0 ? round($totalEjOk / $totalIbc * 100, 1) : 0.0;
            $cykeltid  = $this->calcMedelCykeltid($totalIbc, $totalDrift);
            $oee       = $this->calcOee($totalOk, $totalDrift);
            $medPerDag = $antalDagar > 0 ? round($totalIbc / $antalDagar, 1) : 0.0;

            // Bäste operatör i skiftet
            $bastaOp = $this->getBastaOperatorPerSkift($skiftKey, $cutoff, $opNames);

            $skiftResult[$skiftKey] = [
                'skift'          => $skiftKey,
                'label'          => $skiftDef['label'],
                'total_ibc'      => $totalIbc,
                'kassationsgrad' => $kassGrad,
                'medel_cykeltid' => $cykeltid,
                'oee'            => $oee,
                'medel_per_dag'  => $medPerDag,
                'antal_dagar'    => $antalDagar,
                'basta_operator' => $bastaOp,
            ];
        }

        $this->sendSuccess([
            'skift'   => array_values($skiftResult),
            'period'  => $period,
        ]);
    }

    /**
     * Bäste operatör per skift (flest IBC OK)
     */
    private function getBastaOperatorPerSkift(string $skift, string $cutoff, array $opNames): ?array {
        $skiftCond = $this->skiftWhere($skift, 'datum');
        try {
            $sql = "
                SELECT op_id, SUM(ibc_ok_val) AS tot_ok
                FROM (
                    SELECT op1 AS op_id, ibc_ok AS ibc_ok_val
                    FROM rebotling_skiftrapport
                    WHERE datum >= ? AND op1 IS NOT NULL AND {$skiftCond}
                    UNION ALL
                    SELECT op2 AS op_id, ibc_ok AS ibc_ok_val
                    FROM rebotling_skiftrapport
                    WHERE datum >= ? AND op2 IS NOT NULL AND {$skiftCond}
                    UNION ALL
                    SELECT op3 AS op_id, ibc_ok AS ibc_ok_val
                    FROM rebotling_skiftrapport
                    WHERE datum >= ? AND op3 IS NOT NULL AND {$skiftCond}
                ) sub
                WHERE op_id > 0
                GROUP BY op_id
                ORDER BY tot_ok DESC
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$cutoff, $cutoff, $cutoff]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $id = (int)$row['op_id'];
                return [
                    'operator_id'   => $id,
                    'operator_namn' => $opNames[$id] ?? "Operatör #{$id}",
                    'antal_ibc_ok'  => (int)$row['tot_ok'],
                ];
            }
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::getBastaOperatorPerSkift: ' . $e->getMessage());
        }
        return null;
    }

    // ================================================================
    // run=utveckling
    // ================================================================

    private function getUtveckling(): void {
        $opId = (int)($_GET['operator_id'] ?? 0);
        if ($opId <= 0) {
            $this->sendError('Ogiltigt operator_id');
            return;
        }

        $namn = '';
        try {
            $s = $this->pdo->prepare("SELECT name FROM operators WHERE number = ?");
            $s->execute([$opId]);
            $namn = (string)($s->fetchColumn() ?? "Operatör #{$opId}");
        } catch (\PDOException $e) {
            error_log('OperatorsPrestandaController::getUtveckling name: ' . $e->getMessage());
            $namn = "Operatör #{$opId}";
        }

        // Veckovis data: senaste 12 veckor
        $veckor = [];
        for ($i = 11; $i >= 0; $i--) {
            $veckoStart = date('Y-m-d', strtotime("monday -{$i} weeks"));
            $veckoSlut  = date('Y-m-d', strtotime("sunday -{$i} weeks"));
            $veckoNr    = (int)date('W', strtotime($veckoStart));
            $ar         = (int)date('Y', strtotime($veckoStart));

            try {
                $sql = "
                    SELECT
                        COALESCE(SUM(ibc_ok), 0)            AS total_ok,
                        COALESCE(SUM(ibc_ej_ok), 0)         AS total_ej_ok,
                        COALESCE(SUM(drifttid), 0)          AS total_drifttid
                    FROM (
                        SELECT ibc_ok, ibc_ej_ok, drifttid
                        FROM rebotling_skiftrapport WHERE op1 = ? AND DATE(datum) BETWEEN ? AND ?
                        UNION ALL
                        SELECT ibc_ok, ibc_ej_ok, drifttid
                        FROM rebotling_skiftrapport WHERE op2 = ? AND DATE(datum) BETWEEN ? AND ?
                        UNION ALL
                        SELECT ibc_ok, ibc_ej_ok, drifttid
                        FROM rebotling_skiftrapport WHERE op3 = ? AND DATE(datum) BETWEEN ? AND ?
                    ) sub
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $opId, $veckoStart, $veckoSlut,
                    $opId, $veckoStart, $veckoSlut,
                    $opId, $veckoStart, $veckoSlut,
                ]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                error_log('OperatorsPrestandaController::getUtveckling: ' . $e->getMessage());
                $row = ['total_ok' => 0, 'total_ej_ok' => 0, 'total_drifttid' => 0];
            }

            $totalOk   = (int)($row['total_ok']       ?? 0);
            $totalEjOk = (int)($row['total_ej_ok']    ?? 0);
            $totalDrift = (int)($row['total_drifttid'] ?? 0);
            $totalIbc  = $totalOk + $totalEjOk;

            $kassGrad  = $totalIbc > 0 ? round($totalEjOk / $totalIbc * 100, 1) : 0.0;
            $cykeltid  = $this->calcMedelCykeltid($totalIbc, $totalDrift);
            $oee       = $this->calcOee($totalOk, $totalDrift);

            $veckor[] = [
                'vecka'          => $veckoNr,
                'ar'             => $ar,
                'label'          => "V{$veckoNr}",
                'vecko_start'    => $veckoStart,
                'vecko_slut'     => $veckoSlut,
                'total_ibc'      => $totalIbc,
                'ibc_ok'         => $totalOk,
                'kassationsgrad' => $kassGrad,
                'medel_cykeltid' => $cykeltid,
                'oee'            => $oee,
                'har_data'       => $totalIbc > 0,
            ];
        }

        // Trend: förbättras eller försämras? (jämför sista 4 vs första 4 veckor med data)
        $veckorMedData = array_values(array_filter($veckor, fn($v) => $v['har_data']));
        $trend = 'neutral';
        if (count($veckorMedData) >= 4) {
            $forsta  = array_slice($veckorMedData, 0, 2);
            $sista   = array_slice($veckorMedData, -2);
            $avgForsta = array_sum(array_column($forsta, 'total_ibc')) / count($forsta);
            $avgSista  = array_sum(array_column($sista,  'total_ibc')) / count($sista);
            if ($avgSista > $avgForsta * 1.05)      $trend = 'forbattras';
            elseif ($avgSista < $avgForsta * 0.95)  $trend = 'forsamras';
        }

        $this->sendSuccess([
            'operator_id'   => $opId,
            'operator_namn' => $namn,
            'veckor'        => $veckor,
            'trend'         => $trend,
        ]);
    }
}
