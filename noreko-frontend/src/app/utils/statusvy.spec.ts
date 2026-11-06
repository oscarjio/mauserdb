import type { StatusDag } from './statusvy';
import { perioder, summera, veckoserie, manadsserie, braDagar, isoWeek, rullande12Fran, mondayOf, dagserie } from './statusvy';

const d = (dag: string, ibc: number | null, drift: number | null, ideal: number | null = null, stopp: number | null = 0, kapad = false): StatusDag =>
  ({ dag, ibc, drifttidMin: drift, drifttidKapad: kapad, driftstoppMin: stopp, idealMin: ideal });

describe('Statusvy: perioder', () => {
  it('fredag 2026-11-06: ISO-vecka 45 från måndag 11-02, förra veckan 10-26–11-01', () => {
    const p = Object.fromEntries(perioder('2026-11-06').map(x => [x.key, x]));
    expect([p['vecka'].fran, p['vecka'].till, isoWeek('2026-11-06')]).toEqual(['2026-11-02', '2026-11-06', 45]);
    expect([p['forraVecka'].fran, p['forraVecka'].till]).toEqual(['2026-10-26', '2026-11-01']);
    expect([p['vecka'].jamfor!.fran, p['vecka'].jamfor!.till]).toEqual(['2026-10-26', '2026-10-30']);
  });

  it('månad t.o.m. idag jämförs med samma antal dagar förra månaden', () => {
    const p = Object.fromEntries(perioder('2026-11-06').map(x => [x.key, x]));
    expect([p['manad'].fran, p['forraManad'].fran, p['forraManad'].till]).toEqual(['2026-11-01', '2026-10-01', '2026-10-31']);
    expect([p['manad'].jamfor!.fran, p['manad'].jamfor!.till]).toEqual(['2026-10-01', '2026-10-06']);
  });

  it('31 mars jämförs med februari t.o.m. den 28 (inte utanför månaden)', () => {
    const p = Object.fromEntries(perioder('2026-03-31').map(x => [x.key, x]));
    expect([p['manad'].jamfor!.fran, p['manad'].jamfor!.till]).toEqual(['2026-02-01', '2026-02-28']);
  });

  it('januari: förra månaden är december föregående år', () => {
    const p = Object.fromEntries(perioder('2027-01-04').map(x => [x.key, x]));
    expect([p['forraManad'].fran, p['forraManad'].till]).toEqual(['2026-12-01', '2026-12-31']);
    expect(mondayOf('2027-01-04')).toEqual('2027-01-04');
    expect(isoWeek('2027-01-04')).toEqual(1);
    expect(isoWeek('2026-12-31')).toEqual(53);
  });

  it('rullande 12 mån: från datumet för ett år sedan + 1 dag, även över skottdag', () => {
    expect(rullande12Fran('2026-11-06')).toEqual('2025-11-07');
    expect(rullande12Fran('2028-02-29')).toEqual('2027-03-01');
    expect(rullande12Fran('2029-03-01')).toEqual('2028-03-02');
  });
});

describe('Statusvy: viktad summering', () => {
  const dagar = [d('2026-11-02', 100, 600, 660), d('2026-11-03', 10, 60, 30), d('2026-11-04', 50, null), d('2026-11-05', null, null)];

  it('takt = ΣIBC / Σdrifttid över dagar med båda, inte medel av dagstakter', () => {
    const s = summera(dagar, '2026-11-02', '2026-11-06', 'signerad');
    // (100 + 10) / ((600 + 60) / 60) = 10,0 — medel av dagstakterna (10 och 10) råkar bli lika,
    // så kontrollera även en skev fördelning nedan.
    expect(s.taktPerTimme).toEqual(10);
    expect([s.ibc, s.drifttidMin, s.dagarMedData, s.dagarMedDrift]).toEqual([160, 660, 3, 2]);
    const skev = summera([d('2026-01-01', 100, 600), d('2026-01-02', 30, 60)], '2026-01-01', '2026-01-02', 'kvot');
    expect(Math.round(skev.taktPerTimme! * 100) / 100).toEqual(11.82); // 130/11 h, inte (10+30)/2 = 20
  });

  it('IBC utan drifttid räknas i IBC men inte i takten', () => {
    const s = summera([d('2026-01-01', 100, 600), d('2026-01-02', 500, null)], '2026-01-01', '2026-01-02', 'kvot');
    expect([s.ibc, s.taktPerTimme]).toEqual([600, 10]);
  });

  it('effektivitet: tvättlinje signerad 1 − Σdrift/Σideal, rebotling kvot max 100 %', () => {
    const tv = summera([d('2026-01-01', 100, 600, 690), d('2026-01-02', 10, 60, 30)], '2026-01-01', '2026-01-02', 'signerad');
    expect(Math.round(tv.effektivitet! * 1000) / 1000).toEqual(Math.round((1 - 660 / 720) * 1000) / 1000);
    const rb = summera([d('2026-01-01', 300, 600, 900)], '2026-01-01', '2026-01-01', 'kvot');
    expect(rb.effektivitet).toEqual(1);
  });

  it('saknad data blir null, inte 0', () => {
    const s = summera([d('2026-01-01', null, null, null, null)], '2026-01-01', '2026-01-31', 'signerad');
    expect([s.ibc, s.drifttidMin, s.driftstoppMin, s.taktPerTimme, s.effektivitet, s.dagarMedData])
      .toEqual([null, null, null, null, null, 0]);
    const tom = summera([], '2026-01-01', '2026-01-31', 'kvot');
    expect([tom.ibc, tom.taktPerTimme]).toEqual([null, null]);
  });
});

describe('Statusvy: serier och bra dagar', () => {
  it('12 veckor och 12 månader, sista pågående och t.o.m. idag', () => {
    const v = veckoserie([], '2026-11-06', 'kvot');
    const m = manadsserie([], '2026-11-06', 'kvot');
    expect([v.length, v[11].fran, v[11].till, v[11].pagar, v[0].fran]).toEqual([12, '2026-11-02', '2026-11-06', true, '2026-08-17']);
    expect([m.length, m[11].fran, m[11].till, m[0].fran, m[0].till]).toEqual([12, '2026-11-01', '2026-11-06', '2025-12-01', '2025-12-31']);
  });

  it('dagserie: en post per kalenderdag, dagar utan data = null (lucka), inte 0', () => {
    const s = dagserie([d('2026-11-02', 100, 400), d('2026-11-04', null, null), d('2026-11-05', 0, 30)], '2026-11-02', '2026-11-06');
    expect(s.map(x => x.ibc)).toEqual([100, null, null, 0, null]);
    expect([s[0].dag, s[4].dag]).toEqual(['2026-11-02', '2026-11-06']);
  });

  it('bra dagar kräver full drifttid, utesluter kapad drifttid, idag och dagar äldre än 12 mån', () => {
    const b = braDagar([
      d('2026-11-06', 999, 500),            // idag — ej avslutad
      d('2026-11-05', 150, 450),
      d('2026-11-04', 200, 120),            // för kort drift
      d('2026-11-03', 300, 600, null, 0, true), // kapad drifttid
      d('2025-11-06', 400, 500),            // utanför 12 mån
      d('2026-10-01', 160, 400),
    ], '2026-11-06');
    expect(b.flestIbc.map(x => x.dag)).toEqual(['2026-10-01', '2026-11-05']);
    expect(b.hogstTakt.map(x => x.dag)).toEqual(['2026-10-01', '2026-11-05']);
    expect(b.antalKandidater).toEqual(2);
  });
});
