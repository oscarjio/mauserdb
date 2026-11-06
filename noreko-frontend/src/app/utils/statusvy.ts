/**
 * Statusvyns periodlogik — rena funktioner, testade i statusvy.spec.ts.
 *
 * Indata är backendens dagssummor (run=statusvy-dagar) per linje. Alla perioder räknas
 * från serverns "idag" (svensk lokal tid), inte från skärmens klocka. Veckor är ISO-veckor
 * (måndag–söndag). Rullande 12 månader = från samma datum för ett år sedan + 1 dag t.o.m. idag.
 *
 * Viktning: inga medelvärden av procentsatser eller takter. Takt = ΣIBC / Σdrifttid över dagar
 * som har BÅDE IBC och drifttid; effektivitet ur Σdrifttid och ΣidealMin (IBC × måltakt) enligt
 * linjens egen definition. Saknad data blir null, aldrig 0.
 */

export interface StatusDag {
  dag: string;               // YYYY-MM-DD
  ibc: number | null;
  drifttidMin: number | null;
  drifttidKapad: boolean;
  driftstoppMin: number | null;
  idealMin: number | null;
}

/** 'signerad' = tvättlinje: 1 − drifttid/ideal (+ = snabbare än måltakt).
 *  'kvot'     = rebotling:  ideal/drifttid, max 1. */
export type EffektivitetTyp = 'signerad' | 'kvot';

export interface PeriodSumma {
  dagarMedData: number;      // dagar med IBC enligt linjens definition
  dagarMedDrift: number;     // dagar som ingår i takt (IBC + drifttid)
  ibc: number | null;
  drifttidMin: number | null;
  driftstoppMin: number | null;
  taktPerTimme: number | null;
  effektivitet: number | null;
}

export interface Period {
  key: 'idag' | 'vecka' | 'forraVecka' | 'manad' | 'forraManad' | 'rullande12';
  label: string;
  fran: string;
  till: string;
  pagar: boolean;
  /** Jämförelse med motsvarande förfluten tid (bara för pågående vecka/månad). */
  jamfor?: { fran: string; till: string; label: string };
}

// ---------- datum (UTC-säker aritmetik på YYYY-MM-DD) ----------
const toDate = (s: string) => new Date(`${s}T00:00:00Z`);
const fmt = (d: Date) => d.toISOString().slice(0, 10);
export const addDays = (s: string, n: number) => { const d = toDate(s); d.setUTCDate(d.getUTCDate() + n); return fmt(d); };
export const mondayOf = (s: string) => { const d = toDate(s); const wd = (d.getUTCDay() + 6) % 7; return addDays(s, -wd); };
export const firstOfMonth = (s: string) => s.slice(0, 8) + '01';
export const lastOfMonth = (s: string) => {
  const d = toDate(firstOfMonth(s)); d.setUTCMonth(d.getUTCMonth() + 1); d.setUTCDate(0); return fmt(d);
};
export const addMonths = (s: string, n: number) => {
  // Första i månaden n månader bort (används för månadsserier och föregående månad).
  const d = toDate(firstOfMonth(s)); d.setUTCMonth(d.getUTCMonth() + n); return fmt(d);
};
export const isoWeek = (s: string) => {
  const d = toDate(s);
  d.setUTCDate(d.getUTCDate() + 3 - ((d.getUTCDay() + 6) % 7));
  const week1 = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
  return 1 + Math.round(((d.getTime() - week1.getTime()) / 86400000 - 3 + ((week1.getUTCDay() + 6) % 7)) / 7);
};
export const rullande12Fran = (idag: string) => {
  const d = toDate(idag);
  const y = d.getUTCFullYear() - 1, m = d.getUTCMonth(), day = d.getUTCDate();
  // Samma datum förra året (29 feb -> 28 feb), + 1 dag.
  const last = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
  return addDays(fmt(new Date(Date.UTC(y, m, Math.min(day, last)))), 1);
};

const MANADER = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
export const manadNamn = (s: string) => MANADER[Number(s.slice(5, 7)) - 1];

/** Statusvyns fasta perioder, räknade från serverns datum. */
export function perioder(idag: string): Period[] {
  const man = mondayOf(idag);
  const forstaDenna = firstOfMonth(idag);
  const forstaForra = addMonths(idag, -1);
  const sistaForra = lastOfMonth(forstaForra);
  const dagIManad = Number(idag.slice(8, 10));
  const jamforManadTill = addDays(forstaForra, Math.min(dagIManad, Number(sistaForra.slice(8, 10))) - 1);
  return [
    { key: 'idag', label: 'Idag', fran: idag, till: idag, pagar: true },
    { key: 'vecka', label: `Denna vecka (v. ${isoWeek(idag)})`, fran: man, till: idag, pagar: true,
      jamfor: { fran: addDays(man, -7), till: addDays(idag, -7), label: 'samma dagar förra veckan' } },
    { key: 'forraVecka', label: `Förra veckan (v. ${isoWeek(addDays(man, -7))})`, fran: addDays(man, -7), till: addDays(man, -1), pagar: false },
    { key: 'manad', label: `Denna månad (${manadNamn(idag)})`, fran: forstaDenna, till: idag, pagar: true,
      jamfor: { fran: forstaForra, till: jamforManadTill, label: 'samma dagar förra månaden' } },
    { key: 'forraManad', label: `Förra månaden (${manadNamn(forstaForra)})`, fran: forstaForra, till: sistaForra, pagar: false },
    { key: 'rullande12', label: 'Rullande 12 månader', fran: rullande12Fran(idag), till: idag, pagar: false },
  ];
}

/** Viktad summering av en period. */
export function summera(dagar: StatusDag[], fran: string, till: string, typ: EffektivitetTyp): PeriodSumma {
  const inom = dagar.filter(d => d.dag >= fran && d.dag <= till);
  const medIbc = inom.filter(d => d.ibc !== null);
  const medDrift = inom.filter(d => d.drifttidMin !== null);
  const medTakt = inom.filter(d => d.ibc !== null && d.drifttidMin !== null && d.drifttidMin > 0);
  const medEff = inom.filter(d => d.idealMin !== null && d.idealMin > 0 && d.drifttidMin !== null && d.drifttidMin > 0);
  const medStopp = inom.filter(d => d.driftstoppMin !== null);
  const sum = (arr: StatusDag[], f: (d: StatusDag) => number) => arr.reduce((s, d) => s + f(d), 0);

  const taktIbc = sum(medTakt, d => d.ibc!);
  const taktMin = sum(medTakt, d => d.drifttidMin!);
  const effIdeal = sum(medEff, d => d.idealMin!);
  const effDrift = sum(medEff, d => d.drifttidMin!);
  let eff: number | null = null;
  if (medEff.length && effIdeal > 0 && effDrift > 0) {
    eff = typ === 'signerad' ? 1 - effDrift / effIdeal : Math.min(1, effIdeal / effDrift);
  }
  return {
    dagarMedData: medIbc.length,
    dagarMedDrift: medTakt.length,
    ibc: medIbc.length ? sum(medIbc, d => d.ibc!) : null,
    drifttidMin: medDrift.length ? sum(medDrift, d => d.drifttidMin!) : null,
    driftstoppMin: medStopp.length ? sum(medStopp, d => d.driftstoppMin!) : null,
    taktPerTimme: taktMin > 0 ? taktIbc / (taktMin / 60) : null,
    effektivitet: eff,
  };
}

export interface SerieTidpunkt { label: string; fran: string; till: string; pagar: boolean; summa: PeriodSumma; }

/** De senaste n ISO-veckorna (äldst först), innevarande vecka sist och markerad pågående. */
export function veckoserie(dagar: StatusDag[], idag: string, typ: EffektivitetTyp, n = 12): SerieTidpunkt[] {
  const man = mondayOf(idag);
  const ut: SerieTidpunkt[] = [];
  for (let i = n - 1; i >= 0; i--) {
    const fran = addDays(man, -7 * i);
    const till = i === 0 ? idag : addDays(fran, 6);
    ut.push({ label: `v. ${isoWeek(fran)}`, fran, till, pagar: i === 0, summa: summera(dagar, fran, till, typ) });
  }
  return ut;
}

/** De senaste n kalendermånaderna (äldst först), innevarande månad sist och markerad pågående. */
export function manadsserie(dagar: StatusDag[], idag: string, typ: EffektivitetTyp, n = 12): SerieTidpunkt[] {
  const ut: SerieTidpunkt[] = [];
  for (let i = n - 1; i >= 0; i--) {
    const fran = addMonths(idag, -i);
    const till = i === 0 ? idag : lastOfMonth(fran);
    ut.push({ label: `${manadNamn(fran).slice(0, 3)} ${fran.slice(2, 4)}`, fran, till, pagar: i === 0, summa: summera(dagar, fran, till, typ) });
  }
  return ut;
}

/**
 * IBC per kalenderdag inom [fran, till] till enkla stapelgrafer. Dagar utan data blir null
 * (lucka i grafen), aldrig 0.
 */
export function dagserie(dagar: StatusDag[], fran: string, till: string): { dag: string; ibc: number | null }[] {
  const per = new Map(dagar.map(d => [d.dag, d.ibc]));
  const ut: { dag: string; ibc: number | null }[] = [];
  for (let dag = fran; dag <= till; dag = addDays(dag, 1)) ut.push({ dag, ibc: per.get(dag) ?? null });
  return ut;
}

export interface BraDag { dag: string; ibc: number; drifttidMin: number; taktPerTimme: number; }

/**
 * Bra dagar inom rullande 12 månader. För att en dag ska räknas krävs full datatäckning:
 * IBC och drifttid finns, minst `minDrifttid` minuter drift (en kort dag ger orimlig takt)
 * och ingen kapad (orimlig) drifttid. Dagen idag räknas inte (den är inte avslutad).
 */
export function braDagar(dagar: StatusDag[], idag: string, minDrifttid = 240, antal = 5) {
  const fran = rullande12Fran(idag);
  const kandidater: BraDag[] = dagar
    .filter(d => d.dag >= fran && d.dag < idag && d.ibc !== null && d.drifttidMin !== null
      && d.drifttidMin >= minDrifttid && !d.drifttidKapad)
    .map(d => ({ dag: d.dag, ibc: d.ibc!, drifttidMin: d.drifttidMin!, taktPerTimme: d.ibc! / (d.drifttidMin! / 60) }));
  return {
    kriterium: { fran, minDrifttid },
    antalKandidater: kandidater.length,
    flestIbc: [...kandidater].sort((a, b) => b.ibc - a.ibc || a.dag.localeCompare(b.dag)).slice(0, antal),
    hogstTakt: [...kandidater].sort((a, b) => b.taktPerTimme - a.taktPerTimme || a.dag.localeCompare(b.dag)).slice(0, antal),
  };
}
