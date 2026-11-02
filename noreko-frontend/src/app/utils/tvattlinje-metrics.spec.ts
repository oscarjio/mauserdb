import { cyclesForReport, cycleFetchRange } from './tvattlinje-metrics';

const c = (datum: string, skiftraknare: number | null, lopnummer: number | null) => ({ datum, skiftraknare, lopnummer });
const lop = (rows: Array<{ lopnummer: number | null }>) => rows.map(r => r.lopnummer);

describe('cyclesForReport (Excel-exportens löpnummerspann)', () => {
  // Riktig data: rapport 90 (2026-11-02). PLC:n bytte skiftraknare 63 -> 64 kl 08:28 utan
  // egen rapport för 63. Rapporten täcker båda: 81 IBC = 31 + 50, löpnummer 2–77.
  const report90 = {
    skiftraknare: 64,
    period_start: '2026-11-02 07:05:12',
    period_end: '2026-11-02 11:41:13',
  };
  const cycles = [
    c('2026-11-02 07:05:12', 63, 2),
    c('2026-11-02 08:27:13', 63, 30),
    c('2026-11-02 08:29:20', 64, 31),
    c('2026-11-02 11:41:13', 64, 77),
  ];

  it('tar med orapporterad räknare inom rapportens period', () => {
    expect(lop(cyclesForReport(report90, cycles, new Set([64])))).toEqual([2, 30, 31, 77]);
  });

  it('tar INTE med räknare som har en egen rapport', () => {
    expect(lop(cyclesForReport(report90, cycles, new Set([63, 64])))).toEqual([31, 77]);
  });

  it('tar inte med orapporterad räknare utanför perioden', () => {
    const rows = [...cycles, c('2026-11-02 11:41:14', 65, 78), c('2026-11-02 07:05:11', 62, 1)];
    expect(lop(cyclesForReport(report90, rows, new Set([64])))).toEqual([2, 30, 31, 77]);
  });

  it('faller tillbaka på egen skiftraknare när perioden saknas (äldre rapporter)', () => {
    const r = { skiftraknare: 64, period_start: null, period_end: null };
    expect(lop(cyclesForReport(r, cycles, new Set([64])))).toEqual([31, 77]);
  });

  it('räknar aldrig in rader utan skiftraknare', () => {
    const rows = [c('2026-11-02 08:00:00', null, 15), c('2026-11-02 08:00:01', 0, 16)];
    expect(cyclesForReport(report90, rows, new Set([64]))).toEqual([]);
  });

  it('rapport utan egna cykler (spökräknare) får periodens orapporterade cykler', () => {
    const r = { skiftraknare: 39, period_start: '2026-09-28 07:02:40', period_end: '2026-09-28 15:39:27' };
    const rows = [c('2026-09-28 07:07:54', 38, 2), c('2026-09-28 15:39:27', 38, 165)];
    expect(lop(cyclesForReport(r, rows, new Set([39])))).toEqual([2, 165]);
  });
});

describe('cycleFetchRange (datumspann för cykelhämtning)', () => {
  it('börjar på period_start när skiftet startade före rapportdatumet', () => {
    const reports = [
      { datum: '2026-10-20', period_start: '2026-10-19 07:01:32', period_end: '2026-10-20 15:39:38' },
      { datum: '2026-10-21', period_start: null, period_end: null },
    ];
    expect(cycleFetchRange(reports)).toEqual({ start: '2026-10-19', end: '2026-10-21' });
  });

  it('använder datum när perioden saknas', () => {
    expect(cycleFetchRange([{ datum: '2026-06-04 00:00:00', period_start: null, period_end: null }]))
      .toEqual({ start: '2026-06-04', end: '2026-06-04' });
  });

  it('ingen rapport ger inget spann', () => {
    expect(cycleFetchRange([])).toBeNull();
  });
});
