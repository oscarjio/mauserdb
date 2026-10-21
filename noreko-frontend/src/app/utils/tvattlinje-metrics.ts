/**
 * TvattlinjeMetrics — TS-spegel av noreko-backend/classes/TvattlinjeMetrics.php (Oscar
 * 2026-10-20). Dessa formler, och ENDAST dessa, är de tillåtna definitionerna för
 * tvättlinjens KPI:er. Håll denna fil och PHP-klassen identiska — det är hela poängen
 * med "en räknekärna". Rebotling berörs INTE av den här filen.
 *
 * Grunddefinitioner (se CLAUDE.md "DOMÄNREGLER — DRIFTTID / RAST / DRIFTSTOPP"):
 *   DRIFTTID    = D4007, redan netto (utan rast/driftstopp). Cappas till 600 min/skift.
 *   IBC (för P/EFF/snitt min-per-IBC) = ALLA TVÄTTADE = ok + ej_ok + omtvätt.
 *   DRIFTFAKTOR (Oscar 2026-10-20, ersätter "Tillgänglighet"/planerad-tid-formeln) =
 *     drifttid / (drifttid + driftstopp). Driftstopp = D4011 (rast räknas ALDRIG in).
 *     Saknas driftstopp (=0) → driftfaktor 100%. Planerad skifttid (495/435) används
 *     INTE längre — linjen körs när det passar. "Tillgänglighet" visas INTE som eget
 *     nyckeltal någonstans — bara som intern faktor i OEE.
 *   P (Prestanda)      = IBC × produktens måltakt (fallback 3,0 min) / drifttid, cap 100%.
 *   K (Kvalitet)        = ok / IBC (ok / alla tvättade).
 *   OEE                 = driftfaktor × P × K.
 *   EFFEKTIVITET         = (måltakt − drifttid/IBC) / måltakt — SIGNERAD, INGEN cap.
 *
 * Alla funktioner returnerar ANDELAR (0.0–1.0) utom isoWeekday. Anroparen multiplicerar
 * med 100 och avrundar enligt egen visningskonvention.
 */

export const RUNTIME_CAP_MIN = 600.0;
export const FALLBACK_CYCLE_MIN = 3.0;

/** Cappar rå drifttid (D4007) till max 600 min/skift. Inga avdrag av rast/driftstopp. */
export function capDrifttid(rawDrifttidMin: number): number {
  return Math.max(0, Math.min(rawDrifttidMin || 0, RUNTIME_CAP_MIN));
}

/**
 * ISO-veckodag (1=mån..7=sön) från ett datum/datumsträng. Spegel av PHP date('N').
 * JS Date.getDay() är 0=sön..6=lör — räknas om här så anroparen aldrig behöver minnas det.
 */
export function isoWeekday(dateOrStr: string | Date): number {
  const d = typeof dateOrStr === 'string'
    ? new Date(dateOrStr.substring(0, 10) + 'T00:00:00')
    : dateOrStr;
  if (isNaN(d.getTime())) return 1; // okänt datum -> behandla som vardag
  const js = d.getDay(); // 0=sön..6=lör
  return js === 0 ? 7 : js;
}

/**
 * Driftfaktor (Oscar 2026-10-20, ersätter "Tillgänglighet"/planerad-tid-formeln) =
 * drifttid / (drifttid + driftstopp). Driftstopp = D4011 (rast räknas ALDRIG in).
 * Saknas driftstopp (=0 eller ej registrerat) → driftfaktor 100%. Detta är OEE:s
 * första faktor — exponeras INTE som eget nyckeltal.
 */
export function driftfaktor(drifttidMin: number, driftstoppMin: number): number {
  const ds = driftstoppMin || 0;
  if (ds <= 0) return 1.0;
  const denom = drifttidMin + ds;
  if (denom <= 0) return 1.0;
  return Math.min(1.0, Math.max(0.0, drifttidMin / denom));
}

/**
 * P = IBC (alla tvättade) × produktens måltakt / drifttid, cap 100%.
 * $totalt = ok + ej_ok + omtvätt. $cycleTimeMin = produktens måltakt (minuter/IBC).
 */
export function performance(totalt: number, cycleTimeMin: number, drifttidMin: number): number {
  if (drifttidMin <= 0 || cycleTimeMin <= 0 || totalt <= 0) return 0.0;
  return Math.min(1.0, Math.max(0.0, (totalt * cycleTimeMin) / drifttidMin));
}

/**
 * K = ok / alla tvättade (ok + ej_ok + omtvätt). Null = ingen kvalitetsdata (INTE 0%
 * — en rapport utan ok/ej_ok-data är okänd kvalitet, inte 100% kassation).
 */
export function quality(ok: number, totalt: number): number | null {
  if (totalt <= 0) return null;
  return Math.min(1.0, Math.max(0.0, ok / totalt));
}

/** OEE = A × P × K. Null om kvalitet saknas. */
export function oee(avail: number, perf: number, qual: number | null): number | null {
  if (qual === null) return null;
  return avail * perf * qual;
}

/**
 * Signerad effektivitet mot mål: (måltakt − faktisk cykeltid) / måltakt. INGEN cap.
 * + = snabbare än mål, − = långsammare. $totalt = alla tvättade (samma som P/K-nämnaren).
 */
export function efficiency(cycleTimeMin: number, totalt: number, drifttidMin: number): number | null {
  if (totalt <= 0 || cycleTimeMin <= 0) return null;
  const actualCycle = drifttidMin / totalt;
  if (actualCycle <= 0) return null;
  return (cycleTimeMin - actualCycle) / cycleTimeMin;
}

/** Snitt faktisk cykeltid (minuter/IBC) = drifttid / alla tvättade. */
export function avgCycleMin(drifttidMin: number, totalt: number): number | null {
  if (totalt <= 0) return null;
  return drifttidMin / totalt;
}
