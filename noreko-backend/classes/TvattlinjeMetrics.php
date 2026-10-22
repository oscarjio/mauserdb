<?php
/**
 * TvattlinjeMetrics — EN räknekärna för tvättlinjens nyckeltal (Oscar 2026-10-20).
 *
 * Samtliga formler här, och ENDAST dessa, är de tillåtna definitionerna för tvättlinjens
 * KPI:er. Se CLAUDE.md "DOMÄNREGLER — DRIFTTID / RAST / DRIFTSTOPP" för bakgrund/bevis.
 * Rebotling berörs INTE av denna klass — den har egen (oförändrad) semantik.
 *
 * Grunddefinitioner:
 *   DRIFTTID    = D4007, redan netto (utan rast/driftstopp). Cappas till 600 min/skift.
 *   IBC (för P/EFF/snitt min-per-IBC) = ALLA TVÄTTADE = ok + ej_ok + omtvätt
 *                                       (varje omtvätt är en tvättcykel linjen utfört).
 *   DRIFTFAKTOR (Oscar 2026-10-20, ERSÄTTER "Tillgänglighet") = drifttid / (drifttid +
 *     driftstopp), driftstopp = D4011 (rast räknas ALDRIG in). Saknas driftstopp (=0) →
 *     driftfaktor 100%. Planerad skifttid (495/435) är INTE relevant för tvättlinjen —
 *     linjen körs när det passar — och används INTE längre någonstans i OEE-vägen.
 *     "Tillgänglighet" visas INTE längre som eget nyckeltal någonstans i UI:t — bara
 *     som en intern faktor i OEE.
 *   P (Prestanda)      = IBC × produktens måltakt (fallback 3,0 min) / drifttid, cap 100%.
 *   K (Kvalitet)        = ok / IBC (ok / alla tvättade).
 *   OEE                 = driftfaktor × P × K.
 *   EFFEKTIVITET         = (måltakt − drifttid/IBC) / måltakt — SIGNERAD, INGEN cap.
 *
 * Alla metoder returnerar ANDELAR (0.0–1.0), inte procent — anroparen multiplicerar med
 * 100 och avrundar enligt egen visningskonvention. Metoderna tar redan beräknad/cappad
 * drifttid och IBC-tal som indata; dedup, källprioritet (SR/PLC/onoff) och korrupt-dag-
 * hantering sker fortfarande i respektive controller (oförändrat) — den här klassen äger
 * bara SJÄLVA FORMELN, inte datainsamlingen.
 */
final class TvattlinjeMetrics {
    public const RUNTIME_CAP_MIN     = 600.0;
    public const FALLBACK_CYCLE_MIN  = 3.0;

    /** Cappar rå drifttid (D4007) till max 600 min/skift. Inga avdrag av rast/driftstopp. */
    public static function capDrifttid(float $rawDrifttidMin): float {
        return max(0.0, min($rawDrifttidMin, self::RUNTIME_CAP_MIN));
    }

    /**
     * Driftfaktor (Oscar 2026-10-20, ersätter "Tillgänglighet"/planerad-tid-formeln) =
     * drifttid / (drifttid + driftstopp). Driftstopp = D4011 (rast räknas ALDRIG in).
     * Saknas driftstopp (=0 eller ej registrerat) → driftfaktor 100% (ingen känd förlust
     * att straffa för). Detta är OEE:s första faktor — exponeras INTE som eget nyckeltal.
     */
    public static function driftfaktor(float $drifttidMin, float $driftstoppMin): float {
        if ($driftstoppMin <= 0) return 1.0;
        $denom = $drifttidMin + $driftstoppMin;
        if ($denom <= 0) return 1.0;
        return min(1.0, max(0.0, $drifttidMin / $denom));
    }

    /**
     * P = IBC (alla tvättade) × produktens måltakt / drifttid, cap 100%.
     * $totalt = ok + ej_ok + omtvätt. $cycleTimeMin = produktens måltakt (minuter/IBC).
     */
    public static function performance(float $totalt, float $cycleTimeMin, float $drifttidMin): float {
        if ($drifttidMin <= 0 || $cycleTimeMin <= 0 || $totalt <= 0) {
            return 0.0;
        }
        return min(1.0, max(0.0, ($totalt * $cycleTimeMin) / $drifttidMin));
    }

    /**
     * K = ok / alla tvättade (ok + ej_ok + omtvätt). Null = ingen kvalitetsdata (INTE 0%
     * — en rapport utan ok/ej_ok-data är okänd kvalitet, inte 100% kassation).
     */
    public static function quality(float $ok, float $totalt): ?float {
        if ($totalt <= 0) return null;
        return min(1.0, max(0.0, $ok / $totalt));
    }

    /** OEE = driftfaktor × P × K. Null om kvalitet saknas (ingen OEE går att beräkna). */
    public static function oee(float $driftfaktor, float $performance, ?float $quality): ?float {
        if ($quality === null) return null;
        return $driftfaktor * $performance * $quality;
    }

    /**
     * Signerad effektivitet mot mål: (måltakt − faktisk cykeltid) / måltakt. INGEN cap.
     * + = snabbare än mål, − = långsammare. $totalt = alla tvättade (samma som P/K-nämnaren).
     */
    public static function efficiency(float $cycleTimeMin, float $totalt, float $drifttidMin): ?float {
        if ($totalt <= 0 || $cycleTimeMin <= 0) return null;
        $actualCycle = $drifttidMin / $totalt;
        if ($actualCycle <= 0) return null;
        return ($cycleTimeMin - $actualCycle) / $cycleTimeMin;
    }

    /** Snitt faktisk cykeltid (minuter/IBC) = drifttid / alla tvättade. */
    public static function avgCycleMin(float $drifttidMin, float $totalt): ?float {
        if ($totalt <= 0) return null;
        return $drifttidMin / $totalt;
    }

    /** ISO-veckodag (1=mån..7=sön) från ett 'Y-m-d'-datum. */
    public static function isoWeekday(string $yyyyMmDd): int {
        return (int)date('N', strtotime($yyyyMmDd));
    }

    // ==================================================================
    // DUBBELSKIFT / SEN SKIFTRAPPORT (Oscar 2026-10-21/22 BESLUT)
    //
    // Problem: en skiftrapport vars cykler (samma skiftraknare) sträcker sig över flera
    // kalenderdagar attribuerades tidigare HELA sin produktion/drifttid till
    // INLÄMNINGSDAGEN (DATE(sr.datum)), medan den dag cyklerna faktiskt kördes PÅ men som
    // saknade inskickad rapport räknades separat via PLC-fallbacken → samma produktion
    // dubbelräknad (en gång som "PLC-dag", en gång inbakad i nästa dags inskickade rapport).
    //
    // Lösning: läs ALLTID tvattlinje_ibc-cyklerna för skiftraknaret och dela upp
    // produktionen per den kalenderdag cyklerna faktiskt registrerades på — ALDRIG
    // inlämningsdagen. Rent läs-tid, INGA databasskrivningar (se multiDaySplitPerDag).
    // ==================================================================

    /**
     * Delar upp ETT skiftraknares cykler per kalenderdag (Regel 1-4, Oscar 2026-10-21).
     *
     * $cycles: kronologiskt ordnade (ASC datum) rader från tvattlinje_ibc för EXAKT ett
     *   skiftraknare: ['datum'=>, 'ibc_ok'=>, 'ibc_ej_ok'=>, 'omtvaatt'=>, 'runtime_plc'=>?,
     *   'rasttime'=>?, 'driftstopptime'=>?] — samtliga ibc_ok/ibc_ej_ok/omtvaatt/runtime_plc/
     *   rasttime/driftstopptime är KUMULATIVA PLC-räknare för detta skift (nollställs vid
     *   skiftstart, inte per dag).
     * $targetCycleMin: produktens måltakt — används som "+1 cykeltid"-korrigering för
     *   drifttid (Regel 2) så att tiden som ledde fram till dagens FÖRSTA cykel inte tappas.
     * $reportTotalt: rapportens egen IBC-total (antal_ok+antal_ej_ok+omtvätt) — Regel 4.
     *
     * Regel 1: produktionen hör till cyklernas kalenderdag(ar), inte inlämningsdagen —
     *   detta är HELA poängen med funktionen; anroparen använder ALDRIG sr.datum för en
     *   flerdagars-rapport, bara raderna denna metod returnerar.
     * Regel 2 (IBC): dagens IBC = skillnaden mellan dagens sista cykel och föregående dags
     *   sista cykel (teleskoperande summa av per-cykel-delta — matematiskt samma sak).
     * Regel 2 (drifttid/rast/driftstopp): dagens sista cykel minus dagens FÖRSTA cykel,
     *   plus en cykeltid (bara för drifttid) så natt-hoppet mot föregående dags sista
     *   cykel (linjen "lämnad på" över natten) aldrig räknas in.
     * Regel 3 (fallback, saknas giltig runtime_plc): summan av glapp mellan cyklerna under
     *   dagen där glappet är <30 min. (Rast-/driftstopp-spann från tvattlinje_rast/
     *   tvattlinje_driftstopp dras INTE av här — kräver en join anroparen inte alltid har;
     *   om det behövs läggs det till av anroparen. Finns inga cykelrader alls för en dag
     *   mitt i spannet — extremt osannolikt, varje cykel postar sin egen PLC-webhook —
     *   täcks inte; flaggas i så fall som avvikelse via Regel 4 i stället.)
     * Regel 4: avvikelse > 2 mellan $reportTotalt och antal cykelrader i hela skiftet
     *   flaggas med en extra anledningstext.
     *
     * Returnerar en lista (en rad per kalenderdag cyklerna spänner över):
     *   ['dag'=>'Y-m-d', 'ibc_ok'=>int, 'ibc_ej_ok'=>int, 'omtvaatt'=>int, 'totalt'=>int,
     *    'drifttid'=>float, 'rast'=>float, 'driftstopp'=>float, 'estimated'=>bool,
     *    'flagged'=>true, 'flagReason'=>string]
     */
    public static function splitMultiDaySkift(array $cycles, float $targetCycleMin, int $reportTotalt): array {
        if (empty($cycles)) return [];

        $byDay = [];
        foreach ($cycles as $i => $c) {
            $dag = substr($c['datum'], 0, 10);
            $byDay[$dag][] = $i;
        }
        $days = array_keys($byDay); // redan kronologisk ordning ($cycles är ASC)

        $hasValidRuntime = true;
        foreach ($cycles as $c) {
            if ($c['runtime_plc'] === null) { $hasValidRuntime = false; break; }
        }

        $out = [];
        $prevOk = 0; $prevEjOk = 0; $prevOm = 0;
        foreach ($days as $dag) {
            $idxs = $byDay[$dag];
            $first = $cycles[$idxs[0]];
            $last  = $cycles[$idxs[count($idxs) - 1]];

            // Regel 2 — IBC: dagens sista cykel minus föregående dags sista cykel.
            $ibcOk   = max(0, (int)$last['ibc_ok']    - $prevOk);
            $ibcEjOk = max(0, (int)$last['ibc_ej_ok'] - $prevEjOk);
            $omtv    = max(0, (int)$last['omtvaatt']  - $prevOm);
            $prevOk   = (int)$last['ibc_ok'];
            $prevEjOk = (int)$last['ibc_ej_ok'];
            $prevOm   = (int)$last['omtvaatt'];

            $estimated  = false;
            $flagReason = 'Inskickad i efterhand — fördelad per dag från PLC-cykler';

            if ($hasValidRuntime) {
                // Regel 2 — drifttid/rast/driftstopp: sista−första (+1 cykeltid för drifttid
                // enbart) — exkluderar automatiskt natt-glappet mot föregående dag.
                $drifttid   = max(0.0, (float)$last['runtime_plc'] - (float)$first['runtime_plc']) + $targetCycleMin;
                $rast       = max(0.0, (float)($last['rasttime']       ?? 0) - (float)($first['rasttime']       ?? 0));
                $driftstopp = max(0.0, (float)($last['driftstopptime'] ?? 0) - (float)($first['driftstopptime'] ?? 0));
            } else {
                // Regel 3 — fallback: summan av glapp <30 min mellan cyklerna den dagen.
                $estimated  = true;
                $flagReason = 'Drifttid uppskattad från cykeldata';
                $sumGaps = 0.0;
                for ($k = 1; $k < count($idxs); $k++) {
                    $tPrev = strtotime($cycles[$idxs[$k - 1]]['datum']);
                    $tCur  = strtotime($cycles[$idxs[$k]]['datum']);
                    $gapMin = max(0.0, ($tCur - $tPrev) / 60.0);
                    if ($gapMin < 30) $sumGaps += $gapMin;
                }
                $drifttid   = $sumGaps;
                $rast       = 0.0;
                $driftstopp = 0.0;
            }

            $out[] = [
                'dag'         => $dag,
                'ibc_ok'      => $ibcOk, 'ibc_ej_ok' => $ibcEjOk, 'omtvaatt' => $omtv,
                'totalt'      => $ibcOk + $ibcEjOk + $omtv,
                'drifttid'    => round(min($drifttid, self::RUNTIME_CAP_MIN), 1),
                'rast'        => round($rast, 1),
                'driftstopp'  => round($driftstopp, 1),
                'estimated'   => $estimated,
                'flagged'     => true,
                'flagReason'  => $flagReason,
            ];
        }

        // Regel 4: avvikelse > 2 mellan rapportens IBC-total och antal cykelrader i skiftet.
        $totalCyclesInShift = count($cycles);
        if (abs($reportTotalt - $totalCyclesInShift) > 2) {
            foreach ($out as &$row) {
                $row['flagReason'] .= "; avvikelse: rapport {$reportTotalt} vs {$totalCyclesInShift} PLC-cykler";
            }
            unset($row);
        }

        return $out;
    }

    /**
     * Hittar alla skiftraknare med inskickad (deduplicerad, senaste) skiftrapport som
     * överlappar [$start,$end] och vars cykler (tvattlinje_ibc) spänner över MER ÄN en
     * kalenderdag, och delar upp dem via splitMultiDaySkift(). Rent läs-tid — anropas av
     * varje konsument (statistik, OEE-trend, Excel, skiftrapport-vyn) som annars skulle
     * attribuera flerdagars-produktion till fel dag/dubbelräkna den. INGA DB-skrivningar.
     *
     * Returnerar ['bySkift' => [skiftraknare => ['days'=>[...], 'submissionDag'=>'Y-m-d']],
     *             'byDag'   => ['Y-m-d' => <en splitMultiDaySkift-rad> + 'skiftraknare']].
     * byDag innehåller EN rad per kalenderdag en flerdagars-skiftrapport berör — anroparen
     * ersätter (inte adderar till) sin egen dag-siffra med dessa värden, filtrerat mot sitt
     * eget [start,end]-fönster (en flerdagars-rapport kan sträcka sig utanför fönstret).
     */
    public static function multiDaySplitPerDag(\PDO $pdo, string $start, string $end): array {
        $out = ['bySkift' => [], 'byDag' => []];
        try {
            $stmt = $pdo->prepare("
                SELECT sr.skiftraknare, sr.datum, sr.totalt, sr.antal_ok, sr.antal_ej_ok, sr.omtvaatt
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                WHERE sr.skiftraknare IS NOT NULL AND sr.skiftraknare > 0
            ");
            $stmt->execute(['s' => $start, 'e' => $end]);
            $reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($reports)) return $out;

            $cycleStmt = $pdo->prepare("
                SELECT datum, ibc_ok, ibc_ej_ok, omtvaatt, runtime_plc, rasttime, driftstopptime
                FROM tvattlinje_ibc
                WHERE skiftraknare = :sk
                ORDER BY datum ASC, id ASC
            ");

            foreach ($reports as $rep) {
                $sk = (int)$rep['skiftraknare'];
                if (isset($out['bySkift'][$sk])) continue; // redan behandlat

                $cycleStmt->execute(['sk' => $sk]);
                $cycles = $cycleStmt->fetchAll(\PDO::FETCH_ASSOC);
                if (count($cycles) < 2) continue;

                $firstDag = substr($cycles[0]['datum'], 0, 10);
                $lastDag  = substr($cycles[count($cycles) - 1]['datum'], 0, 10);
                if ($firstDag === $lastDag) continue; // enkeldags-skift — ingen särbehandling

                $targetCycleMin = self::weightedTargetCycleMin($pdo, $firstDag, $lastDag);
                $reportTotalt   = (int)($rep['totalt'] ?? ((int)$rep['antal_ok'] + (int)$rep['antal_ej_ok'] + (int)$rep['omtvaatt']));
                $days = self::splitMultiDaySkift($cycles, $targetCycleMin, $reportTotalt);

                $out['bySkift'][$sk] = ['days' => $days, 'submissionDag' => substr($rep['datum'], 0, 10)];
                foreach ($days as $d) {
                    $out['byDag'][$d['dag']] = $d + ['skiftraknare' => $sk];
                }
            }
        } catch (\Throwable $e) {
            error_log('TvattlinjeMetrics::multiDaySplitPerDag: ' . $e->getMessage());
        }
        return $out;
    }

    // ==================================================================
    // MÅLTAKT — produktens cycle_time_minutes. Hör till kärnan eftersom P och EFF
    // bygger på den: fel måltakt ger fel prestanda/effektivitet på varje yta.
    // ==================================================================

    /** [produkt-id => cycle_time_minutes] för alla produkter med registrerad takt. */
    public static function cycleTimeById(\PDO $pdo): array {
        $map = [];
        try {
            foreach ($pdo->query("SELECT id, cycle_time_minutes FROM tvattlinje_products")->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                if ($p['cycle_time_minutes'] !== null && (float)$p['cycle_time_minutes'] > 0) {
                    $map[(int)$p['id']] = (float)$p['cycle_time_minutes'];
                }
            }
        } catch (\Throwable $e) {
            error_log('TvattlinjeMetrics::cycleTimeById: ' . $e->getMessage());
        }
        return $map;
    }

    /**
     * IBC-viktad måltakt (min/IBC) för en period. Prioritet:
     *   1) Inskickade skiftrapporter (deduplicerat, senaste post per dag+skiftraknare)
     *   2) PLC-rader i tvattlinje_ibc (pågående dag utan inskickad rapport)
     *   3) Senast körda produktens takt
     *   4) $fallback (default FALLBACK_CYCLE_MIN = 3,0)
     * OBS: ALDRIG ett fast tak som 20 IBC/h (= 3,0 min/IBC) — produkternas verkliga
     * takt skiljer sig (t.ex. Vanlig Tvätt/Peroxid 3,40; Olja/Credin 4,00).
     */
    public static function weightedTargetCycleMin(\PDO $pdo, string $start, string $end, ?float $fallback = null): float {
        $fallback = ($fallback !== null && $fallback > 0) ? $fallback : self::FALLBACK_CYCLE_MIN;
        $cycleById = self::cycleTimeById($pdo);
        if (empty($cycleById)) return $fallback;

        try {
            $stmt = $pdo->prepare("
                SELECT sr.product_id, COALESCE(SUM(sr.totalt), 0) AS ibc
                FROM tvattlinje_skiftrapport sr
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM tvattlinje_skiftrapport
                    WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                    GROUP BY DATE(datum), COALESCE(skiftraknare, 0)
                ) latest ON sr.id = latest.max_id
                GROUP BY sr.product_id
            ");
            $stmt->execute(['s' => $start, 'e' => $end]);
            $weighted = 0.0; $total = 0;
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $ibc = (int)$r['ibc'];
                if ($ibc <= 0) continue;
                $cycle = $cycleById[(int)$r['product_id']] ?? $fallback;
                if ($cycle <= 0) continue;
                $weighted += $ibc * $cycle;
                $total    += $ibc;
            }
            if ($total > 0) return $weighted / $total;

            // Ingen skiftrapport i perioden → vikta mot PLC-radernas produkt.
            $plc = $pdo->prepare("
                SELECT produkt, COALESCE(SUM(shift_ibc), 0) AS ibc
                FROM (
                    SELECT COALESCE(skiftraknare, 0) AS sk, produkt, MAX(ibc_ok) AS shift_ibc
                    FROM tvattlinje_ibc
                    WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY)
                      AND produkt IS NOT NULL
                    GROUP BY COALESCE(skiftraknare, 0), produkt
                ) t
                GROUP BY produkt
            ");
            $plc->execute(['s' => $start, 'e' => $end]);
            $weighted = 0.0; $total = 0;
            foreach ($plc->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $ibc   = (int)$r['ibc'];
                $cycle = $cycleById[(int)$r['produkt']] ?? null;
                if ($ibc <= 0 || $cycle === null || $cycle <= 0) continue;
                $weighted += $ibc * $cycle;
                $total    += $ibc;
            }
            if ($total > 0) return $weighted / $total;

            // Ingen producerad IBC ännu → senast körda produktens takt.
            $latest = $pdo->prepare("
                SELECT produkt FROM tvattlinje_ibc
                WHERE datum >= :s AND datum < DATE_ADD(:e, INTERVAL 1 DAY) AND produkt IS NOT NULL
                ORDER BY datum DESC, id DESC LIMIT 1
            ");
            $latest->execute(['s' => $start, 'e' => $end]);
            $pid = $latest->fetchColumn();
            if ($pid !== false && $pid !== null && isset($cycleById[(int)$pid])) {
                return $cycleById[(int)$pid];
            }
        } catch (\Throwable $e) {
            error_log('TvattlinjeMetrics::weightedTargetCycleMin: ' . $e->getMessage());
        }
        return $fallback;
    }
}
