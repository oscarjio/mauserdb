<?php
/**
 * KvalitetstrendController.php
 * Kvalitetstrend per operatör — visar kvalitet%-trend över veckor/månader.
 * Hjälper VD att identifiera vilka operatörer som förbättras och vilka som försämras.
 *
 * Endpoints via ?action=kvalitetstrend&run=XXX:
 *   run=overview
 *       Översiktskort: genomsnittlig kvalitet%, bästa operatör,
 *       störst förbättring, störst nedgång. Utbildningslarm.
 *
 *   run=operators&period=4|12|26
 *       Alla operatörer med: senaste kvalitet%, förändring (pil+procent),
 *       trendstatus, utbildningslarm-flagga.
 *
 *   run=operator-detail&op_id=N&period=4|12|26
 *       Detaljvy för en operatör: veckovis kvalitet%, jämförelse mot teamsnitt,
 *       antal IBC per vecka.
 *
 * Auth: session_id krävs (401 om ej inloggad).
 *
 * Tabeller: rebotling_ibc, operators
 */
class KvalitetstrendController {
    private $pdo;

    /** Kvalitetsgräns för utbildningslarm (under denna = larm) */
    private const LARM_KVALITET_PCT = 85.0;

    /** Antal veckor i rad med nedgång för att utlösa larm */
    private const LARM_NEDGANG_VECKOR = 3;

    /** Minsta antal IBC för att en vecka ska räknas som giltig */
    private const MIN_IBC_PER_VECKA = 5;

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
            case 'overview':         $this->getOverview();        break;
            case 'operators':        $this->getOperators();       break;
            case 'operator-detail':  $this->getOperatorDetail();  break;
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

    private function buildLagCte(string $fromDate, string $toDate): string {
        $f = $this->pdo->quote($fromDate);
        $t = $this->pdo->quote($toDate);
        return "
            WITH lag_base AS (
                SELECT DATE(datum) AS dag, skiftraknare,
                       MAX(COALESCE(ibc_ok,    0)) AS ibc_end,
                       MAX(COALESCE(ibc_ej_ok, 0)) AS ibc_ej_end,
                       MAX(COALESCE(op1, 0))       AS op1,
                       MAX(COALESCE(op2, 0))       AS op2,
                       MAX(COALESCE(op3, 0))       AS op3
                FROM rebotling_ibc
                WHERE datum >= {$f} AND datum < DATE_ADD({$t}, INTERVAL 1 DAY)
                GROUP BY DATE(datum), skiftraknare
            ),
            lag_shifts AS (
                SELECT dag, skiftraknare,
                       GREATEST(0, ibc_end    - COALESCE(LAG(ibc_end)    OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc_ok,
                       GREATEST(0, ibc_ej_end - COALESCE(LAG(ibc_ej_end) OVER (PARTITION BY dag ORDER BY skiftraknare), 0)) AS shift_ibc_ej_ok,
                       op1, op2, op3
                FROM lag_base
            )
        ";
    }

    private function getPeriod(): int {
        $p = (int)($_GET['period'] ?? 12);
        if (!in_array($p, [4, 12, 26], true)) {
            return 12;
        }
        return $p;
    }

    /**
     * Hämta alla aktiva operatörer som number => name.
     */
    private function getOperatorNames(): array {
        $stmt = $this->pdo->query(
            "SELECT number, name FROM operators WHERE active = 1 ORDER BY name ASC"
        );
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['number']] = $row['name'];
        }
        return $map;
    }

    /**
     * Hämta aktiv operatör-id (pk) → name/number.
     */
    private function getOperatorById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, number FROM operators WHERE id = ? AND active = 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Beräkna veckovis kvalitet% per operatör för de senaste $weeks veckorna.
     * Returnerar:
     *   [
     *     'op_num' => [
     *       'vecka_key' => ['ibc_ok' => N, 'ibc_ej_ok' => N, 'kvalitet_pct' => X],
     *       ...
     *     ],
     *     ...
     *   ]
     *
     * Vi loopar op1, op2, op3 och summerar IBC OK/ej OK per operatörsnummer+vecka.
     */
    private function getVeckodataPerOperator(int $weeks): array {
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$weeks} weeks"));

        // Bygg veckolista
        $veckonycklar = [];
        $current = strtotime($fromDate);
        $end     = strtotime($toDate);
        while ($current <= $end) {
            $y = (int)date('Y', $current);
            $w = (int)date('W', $current);
            $key = sprintf('%04d-W%02d', $y, $w);
            if (!in_array($key, $veckonycklar, true)) {
                $veckonycklar[] = $key;
            }
            $current = strtotime('+1 day', $current);
        }

        $lagCte = $this->buildLagCte($fromDate, $toDate);
        $rows = $this->pdo->query("
            {$lagCte}
            SELECT
                sub.op_num,
                CONCAT(YEAR(sub.dag), '-W', LPAD(WEEK(sub.dag, 3), 2, '0')) AS vecka_key,
                SUM(sub.shift_ibc_ok)    AS ibc_ok,
                SUM(sub.shift_ibc_ej_ok) AS ibc_ej_ok
            FROM (
                SELECT op1 AS op_num, dag, shift_ibc_ok, shift_ibc_ej_ok FROM lag_shifts WHERE op1 > 0
                UNION ALL
                SELECT op2 AS op_num, dag, shift_ibc_ok, shift_ibc_ej_ok FROM lag_shifts WHERE op2 > 0
                UNION ALL
                SELECT op3 AS op_num, dag, shift_ibc_ok, shift_ibc_ej_ok FROM lag_shifts WHERE op3 > 0
            ) sub
            GROUP BY sub.op_num, vecka_key
            ORDER BY sub.op_num, vecka_key
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $num   = (int)$row['op_num'];
            $key   = $row['vecka_key'];
            $ok    = (int)$row['ibc_ok'];
            $ejOk  = (int)$row['ibc_ej_ok'];
            $tot   = $ok + $ejOk;
            $kval  = $tot >= self::MIN_IBC_PER_VECKA
                ? round($ok / $tot * 100, 1)
                : null;

            if (!isset($result[$num])) {
                $result[$num] = [];
            }
            $result[$num][$key] = [
                'ibc_ok'      => $ok,
                'ibc_ej_ok'   => $ejOk,
                'ibc_total'   => $tot,
                'kvalitet_pct' => $kval,
            ];
        }

        return $result;
    }

    /**
     * Beräkna genomsnittlig kvalitet% för en operatör (alla veckor med data).
     */
    private function snittKvalitet(array $veckodata): ?float {
        $values = array_filter(
            array_column($veckodata, 'kvalitet_pct'),
            fn($v) => $v !== null
        );
        if (count($values) === 0) return null;
        return round(array_sum($values) / count($values), 1);
    }

    /**
     * Beräkna förändring: senaste 2 veckorna vs 2 veckorna innan.
     * Returnerar ['pct' => X, 'arrow' => 'up'|'down'|'flat'].
     */
    private function beraknaForandring(array $veckodata, array $veckonycklar): array {
        // Sortera veckonycklar
        sort($veckonycklar);

        // Ta de senaste 4 veckorna med data
        $vals = [];
        foreach ($veckonycklar as $key) {
            $kval = $veckodata[$key]['kvalitet_pct'] ?? null;
            if ($kval !== null) {
                $vals[] = $kval;
            }
        }

        if (count($vals) < 2) {
            return ['pct' => null, 'arrow' => 'flat'];
        }

        $cnt = count($vals);
        $half = max(1, (int)floor($cnt / 2));
        $recent = array_slice($vals, $cnt - $half);
        $older  = array_slice($vals, 0, $half);

        $avgRecent = array_sum($recent) / count($recent);
        $avgOlder  = array_sum($older)  / count($older);

        if (abs($avgOlder) < 0.0001) {
            return ['pct' => null, 'arrow' => 'flat'];
        }

        $diffPct = round(($avgRecent - $avgOlder) / abs($avgOlder) * 100, 1);

        if ($diffPct > 1.0)  return ['pct' => $diffPct, 'arrow' => 'up'];
        if ($diffPct < -1.0) return ['pct' => $diffPct, 'arrow' => 'down'];
        return ['pct' => $diffPct, 'arrow' => 'flat'];
    }

    /**
     * Kontrollera om operatör har haft nedgång 3+ veckor i rad.
     */
    private function harKonsekventNedgang(array $veckodata, array $veckonycklar): bool {
        sort($veckonycklar);
        $vals = [];
        foreach ($veckonycklar as $key) {
            $kval = $veckodata[$key]['kvalitet_pct'] ?? null;
            if ($kval !== null) {
                $vals[] = $kval;
            }
        }

        $n = count($vals);
        if ($n < self::LARM_NEDGANG_VECKOR) return false;

        $streak = 1;
        for ($i = $n - 1; $i >= 1; $i--) {
            if ($vals[$i] < $vals[$i - 1]) {
                $streak++;
                if ($streak >= self::LARM_NEDGANG_VECKOR) return true;
            } else {
                break;
            }
        }
        return false;
    }

    /**
     * Bygger veckolista (nycklar) för de senaste $weeks veckorna.
     */
    private function byggVeckonycklar(int $weeks): array {
        $keys = [];
        $toDate   = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime("-{$weeks} weeks"));
        $current = strtotime($fromDate);
        $end     = strtotime($toDate);
        while ($current <= $end) {
            $y = (int)date('Y', $current);
            $w = (int)date('W', $current);
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
     * Hämta senaste kvalitet% för en operatör (senaste veckan med data).
     */
    private function senastKvalitet(array $veckodata, array $veckonycklar): ?float {
        $sorted = $veckonycklar;
        rsort($sorted);
        foreach ($sorted as $key) {
            $kval = $veckodata[$key]['kvalitet_pct'] ?? null;
            if ($kval !== null) return $kval;
        }
        return null;
    }

    // ================================================================
    // run=overview
    // ================================================================

    private function getOverview(): void {
        $period = $this->getPeriod();
        $veckonycklar = $this->byggVeckonycklar($period);

        try {
            $opNames  = $this->getOperatorNames();
            $opData   = $this->getVeckodataPerOperator($period);

            if (empty($opData)) {
                $this->sendSuccess([
                    'period'            => $period,
                    'snitt_kvalitet_pct' => null,
                    'basta_operator'    => null,
                    'storst_forbattring' => null,
                    'storst_nedgang'    => null,
                    'utbildningslarm'   => [],
                    'antal_operatorer'  => 0,
                ]);
                return;
            }

            $snittTotalt   = [];
            $bastaOp       = null;
            $bastaKval     = -1;
            $storForbOp    = null;
            $storForbPct   = -PHP_FLOAT_MAX;
            $storNedOp     = null;
            $storNedPct    = PHP_FLOAT_MAX;
            $utbildningslarm = [];

            foreach ($opData as $num => $veckodata) {
                $snitt = $this->snittKvalitet($veckodata);
                if ($snitt === null) continue;

                $snittTotalt[] = $snitt;
                $namn = $opNames[$num] ?? "Operatör #{$num}";

                // Bästa operatör
                if ($snitt > $bastaKval) {
                    $bastaKval = $snitt;
                    $bastaOp   = ['nummer' => $num, 'namn' => $namn, 'kvalitet_pct' => $snitt];
                }

                // Förändring
                $fordr = $this->beraknaForandring($veckodata, $veckonycklar);
                $pct   = $fordr['pct'];

                if ($pct !== null) {
                    if ($pct > $storForbPct) {
                        $storForbPct = $pct;
                        $storForbOp  = ['nummer' => $num, 'namn' => $namn, 'forändring_pct' => $pct];
                    }
                    if ($pct < $storNedPct) {
                        $storNedPct = $pct;
                        $storNedOp  = ['nummer' => $num, 'namn' => $namn, 'forändring_pct' => $pct];
                    }
                }

                // Utbildningslarm: under 85% ELLER trend nedåt 3+ veckor i rad
                $senast = $this->senastKvalitet($veckodata, $veckonycklar);
                $nedgang = $this->harKonsekventNedgang($veckodata, $veckonycklar);
                $lagKvalitet = $senast !== null && $senast < self::LARM_KVALITET_PCT;

                if ($lagKvalitet || $nedgang) {
                    $utbildningslarm[] = [
                        'nummer'        => $num,
                        'namn'          => $namn,
                        'senast_kval'   => $senast,
                        'lag_kvalitet'  => $lagKvalitet,
                        'konsekvent_nedgang' => $nedgang,
                        'orsak'         => $lagKvalitet
                            ? ($nedgang ? 'Låg kvalitet + nedgångstrend' : 'Låg kvalitet')
                            : 'Nedgångstrend (3+ veckor)',
                    ];
                }
            }

            $snittGenom = count($snittTotalt) > 0
                ? round(array_sum($snittTotalt) / count($snittTotalt), 1)
                : null;

            // Nollställ om inga förändringar hittades
            if ($storForbPct === -PHP_FLOAT_MAX) $storForbOp = null;
            if ($storNedPct  ===  PHP_FLOAT_MAX) $storNedOp  = null;

            $this->sendSuccess([
                'period'             => $period,
                'snitt_kvalitet_pct' => $snittGenom,
                'basta_operator'     => $bastaOp,
                'storst_forbattring' => $storForbOp,
                'storst_nedgang'     => $storNedOp,
                'utbildningslarm'    => $utbildningslarm,
                'antal_operatorer'   => count($opData),
            ]);

        } catch (\Throwable $e) {
            error_log('KvalitetstrendController::getOverview: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta översiktsdata', 500);
        }
    }

    // ================================================================
    // run=operators
    // ================================================================

    private function getOperators(): void {
        $period = $this->getPeriod();
        $veckonycklar = $this->byggVeckonycklar($period);

        try {
            $opNames = $this->getOperatorNames();
            $opData  = $this->getVeckodataPerOperator($period);

            // Teamets genomsnitt per vecka
            $teamSnittPerVecka = [];
            foreach ($veckonycklar as $vKey) {
                $vals = [];
                foreach ($opData as $num => $veckodata) {
                    $kval = $veckodata[$vKey]['kvalitet_pct'] ?? null;
                    if ($kval !== null) $vals[] = $kval;
                }
                $teamSnittPerVecka[$vKey] = count($vals) > 0
                    ? round(array_sum($vals) / count($vals), 1)
                    : null;
            }

            $operatorer = [];
            foreach ($opData as $num => $veckodata) {
                $namn    = $opNames[$num] ?? "Operatör #{$num}";
                $snitt   = $this->snittKvalitet($veckodata);
                $senast  = $this->senastKvalitet($veckodata, $veckonycklar);
                $fordr   = $this->beraknaForandring($veckodata, $veckonycklar);
                $nedgang = $this->harKonsekventNedgang($veckodata, $veckonycklar);
                $lagKval = $senast !== null && $senast < self::LARM_KVALITET_PCT;

                // Trendstatus
                if ($fordr['arrow'] === 'up') {
                    $trendStatus = 'förbättras';
                } elseif ($fordr['arrow'] === 'down') {
                    $trendStatus = 'försämras';
                } else {
                    $trendStatus = 'stabil';
                }

                // Sparkline-data (senaste 6 veckorna med data)
                $sparkVeckor = array_slice($veckonycklar, -6);
                $sparkdata = [];
                foreach ($sparkVeckor as $vKey) {
                    $sparkdata[] = $veckodata[$vKey]['kvalitet_pct'] ?? null;
                }

                // Antal IBC totalt
                $ibcTotalt = array_sum(array_column(array_values($veckodata), 'ibc_total'));

                $operatorer[] = [
                    'nummer'           => $num,
                    'namn'             => $namn,
                    'senast_kval_pct'  => $senast,
                    'snitt_kval_pct'   => $snitt,
                    'forandring_pct'   => $fordr['pct'],
                    'forandring_pil'   => $fordr['arrow'],
                    'trend_status'     => $trendStatus,
                    'utbildningslarm'  => $lagKval || $nedgang,
                    'lag_kvalitet'     => $lagKval,
                    'konsekvent_nedgang' => $nedgang,
                    'sparkdata'        => $sparkdata,
                    'ibc_totalt'       => $ibcTotalt,
                ];
            }

            // Sortera: larm först, sedan efter senaste kvalitet (stigande = sämst överst i larm-sektionen)
            usort($operatorer, function($a, $b) {
                if ($a['utbildningslarm'] !== $b['utbildningslarm']) {
                    return $b['utbildningslarm'] <=> $a['utbildningslarm'];
                }
                // Inom larm: sämst överst
                if ($a['utbildningslarm']) {
                    $aKval = $a['senast_kval_pct'] ?? 100;
                    $bKval = $b['senast_kval_pct'] ?? 100;
                    return $aKval <=> $bKval;
                }
                // Utan larm: bäst överst
                $aKval = $a['senast_kval_pct'] ?? 0;
                $bKval = $b['senast_kval_pct'] ?? 0;
                return $bKval <=> $aKval;
            });

            $this->sendSuccess([
                'period'              => $period,
                'veckonycklar'        => $veckonycklar,
                'team_snitt_per_vecka' => $teamSnittPerVecka,
                'operatorer'          => $operatorer,
            ]);

        } catch (\Throwable $e) {
            error_log('KvalitetstrendController::getOperators: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsdata', 500);
        }
    }

    // ================================================================
    // run=operator-detail
    // ================================================================

    private function getOperatorDetail(): void {
        $opId  = (int)($_GET['op_id']  ?? 0);
        $period = $this->getPeriod();

        if ($opId <= 0) {
            $this->sendError('op_id saknas eller ogiltig');
            return;
        }

        $op = $this->getOperatorById($opId);
        if (!$op) {
            $this->sendError('Operatör hittades inte', 404);
            return;
        }

        $opNum = (int)$op['number'];
        $veckonycklar = $this->byggVeckonycklar($period);

        try {
            $toDate   = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime("-{$period} weeks"));

            // Hämta veckodata för DENNA operatör (op1, op2 eller op3)
            $lagCte    = $this->buildLagCte($fromDate, $toDate);
            $opNumSafe = (int)$opNum;
            $rows = $this->pdo->query("
                {$lagCte}
                SELECT
                    CONCAT(YEAR(sub.dag), '-W', LPAD(WEEK(sub.dag, 3), 2, '0')) AS vecka_key,
                    SUM(sub.shift_ibc_ok)    AS ibc_ok,
                    SUM(sub.shift_ibc_ej_ok) AS ibc_ej_ok
                FROM (
                    SELECT dag, shift_ibc_ok, shift_ibc_ej_ok FROM lag_shifts WHERE op1 = {$opNumSafe}
                    UNION ALL
                    SELECT dag, shift_ibc_ok, shift_ibc_ej_ok FROM lag_shifts WHERE op2 = {$opNumSafe}
                    UNION ALL
                    SELECT dag, shift_ibc_ok, shift_ibc_ej_ok FROM lag_shifts WHERE op3 = {$opNumSafe}
                ) sub
                GROUP BY vecka_key
                ORDER BY vecka_key
            ")->fetchAll(\PDO::FETCH_ASSOC);

            // Indexera per vecka
            $opVeckodata = [];
            foreach ($rows as $row) {
                $ok   = (int)$row['ibc_ok'];
                $ejOk = (int)$row['ibc_ej_ok'];
                $tot  = $ok + $ejOk;
                $opVeckodata[$row['vecka_key']] = [
                    'ibc_ok'       => $ok,
                    'ibc_ej_ok'    => $ejOk,
                    'ibc_total'    => $tot,
                    'kvalitet_pct' => $tot >= self::MIN_IBC_PER_VECKA
                        ? round($ok / $tot * 100, 1)
                        : null,
                ];
            }

            // Hämta teamsnitt per vecka (alla operatörer)
            $allOpData = $this->getVeckodataPerOperator($period);
            $teamSnitt = [];
            foreach ($veckonycklar as $vKey) {
                $vals = [];
                foreach ($allOpData as $vd) {
                    $kval = $vd[$vKey]['kvalitet_pct'] ?? null;
                    if ($kval !== null) $vals[] = $kval;
                }
                $teamSnitt[$vKey] = count($vals) > 0
                    ? round(array_sum($vals) / count($vals), 1)
                    : null;
            }

            // Bygg tidslinje per vecka (alla veckor, null om ingen data)
            $tidslinje = [];
            foreach ($veckonycklar as $vKey) {
                $opRow   = $opVeckodata[$vKey] ?? null;
                $teamKval = $teamSnitt[$vKey] ?? null;
                $opKval   = $opRow['kvalitet_pct'] ?? null;

                // Veckonummer-label t.ex. "V10"
                [, $wPart] = explode('-W', $vKey);
                $label = 'V' . ltrim($wPart, '0') ?: 'V' . $wPart;

                $tidslinje[] = [
                    'vecka_key'       => $vKey,
                    'vecka_label'     => $label,
                    'ibc_ok'          => $opRow['ibc_ok']    ?? 0,
                    'ibc_ej_ok'       => $opRow['ibc_ej_ok'] ?? 0,
                    'ibc_total'       => $opRow['ibc_total'] ?? 0,
                    'kvalitet_pct'    => $opKval,
                    'team_kvalitet'   => $teamKval,
                    'vs_team'         => ($opKval !== null && $teamKval !== null)
                        ? round($opKval - $teamKval, 1)
                        : null,
                ];
            }

            // Statistik
            $fordr   = $this->beraknaForandring($opVeckodata, $veckonycklar);
            $snitt   = $this->snittKvalitet($opVeckodata);
            $senast  = $this->senastKvalitet($opVeckodata, $veckonycklar);
            $nedgang = $this->harKonsekventNedgang($opVeckodata, $veckonycklar);
            $lagKval = $senast !== null && $senast < self::LARM_KVALITET_PCT;

            $this->sendSuccess([
                'op_id'            => $opId,
                'op_nummer'        => $opNum,
                'op_namn'          => $op['name'],
                'period'           => $period,
                'tidslinje'        => $tidslinje,
                'snitt_kval_pct'   => $snitt,
                'senast_kval_pct'  => $senast,
                'forandring_pct'   => $fordr['pct'],
                'forandring_pil'   => $fordr['arrow'],
                'utbildningslarm'  => $lagKval || $nedgang,
                'lag_kvalitet'     => $lagKval,
                'konsekvent_nedgang' => $nedgang,
                'ibc_totalt'       => array_sum(array_column(array_values($opVeckodata), 'ibc_total')),
            ]);

        } catch (\Throwable $e) {
            error_log('KvalitetstrendController::getOperatorDetail: ' . $e->getMessage());
            $this->sendError('Kunde inte hämta operatörsdetaljdata', 500);
        }
    }
}
