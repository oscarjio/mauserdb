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
 *   PLANERAD TID = 495 min mån–tor, 435 fre, helg = planerad = körd tid (drifttid).
 *   IBC (för P/EFF/snitt min-per-IBC) = ALLA TVÄTTADE = ok + ej_ok + omtvätt
 *                                       (varje omtvätt är en tvättcykel linjen utfört).
 *   A (Tillgänglighet) = drifttid / planerad tid, cap 100%.
 *   P (Prestanda)      = IBC × produktens måltakt (fallback 3,0 min) / drifttid, cap 100%.
 *   K (Kvalitet)        = ok / IBC (ok / alla tvättade).
 *   OEE                 = A × P × K.
 *   EFFEKTIVITET         = (måltakt − drifttid/IBC) / måltakt — SIGNERAD, INGEN cap.
 *
 * Alla metoder returnerar ANDELAR (0.0–1.0), inte procent — anroparen multiplicerar med
 * 100 och avrundar enligt egen visningskonvention. Metoderna tar redan beräknad/cappad
 * drifttid och IBC-tal som indata; dedup, källprioritet (SR/PLC/onoff) och korrupt-dag-
 * hantering sker fortfarande i respektive controller (oförändrat) — den här klassen äger
 * bara SJÄLVA FORMELN, inte datainsamlingen.
 */
final class TvattlinjeMetrics {
    public const PLANNED_MIN_MON_THU = 495.0;
    public const PLANNED_MIN_FRI     = 435.0;
    public const RUNTIME_CAP_MIN     = 600.0;
    public const FALLBACK_CYCLE_MIN  = 3.0;

    /** Cappar rå drifttid (D4007) till max 600 min/skift. Inga avdrag av rast/driftstopp. */
    public static function capDrifttid(float $rawDrifttidMin): float {
        return max(0.0, min($rawDrifttidMin, self::RUNTIME_CAP_MIN));
    }

    /**
     * Planerad skifttid för en dag. $dayOfWeekIso: 1=mån ... 7=sön (PHP date('N') eller
     * JS getDay() omräknat: dayN = getDay()===0 ? 7 : getDay()).
     * Helg = planerad = körd tid (drifttidMin) => tillgänglighet blir 100% om körd, 0% annars.
     */
    public static function plannedMinutes(int $dayOfWeekIso, float $drifttidMin): float {
        if ($dayOfWeekIso >= 6) {
            return max(0.0, $drifttidMin);
        }
        return $dayOfWeekIso === 5 ? self::PLANNED_MIN_FRI : self::PLANNED_MIN_MON_THU;
    }

    /** A = drifttid / planerad tid, cap 100%. */
    public static function availability(float $drifttidMin, float $plannedMin): float {
        if ($plannedMin <= 0) {
            return $drifttidMin > 0 ? 1.0 : 0.0;
        }
        return min(1.0, max(0.0, $drifttidMin / $plannedMin));
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

    /** OEE = A × P × K. Null om kvalitet saknas (ingen OEE går att beräkna). */
    public static function oee(float $availability, float $performance, ?float $quality): ?float {
        if ($quality === null) return null;
        return $availability * $performance * $quality;
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
