/**
 * Gemensam Excel-layout för linjernas skiftrapport-export (tvättlinje + rebotling).
 *
 * Båda linjerna bygger sina blad härifrån så att design, talformat, frysta rubriker och
 * autofilter alltid är identiska. Varje linje anger bara sina kolumner (rubrik, bredd,
 * format) och sina färdigberäknade rader — beräkningarna stannar i respektive komponent.
 */

/** En kolumn i ett exportblad. `fmt` sätts på numeriska celler, `wch` är kolumnbredden. */
export interface ExcelColumn {
  header: string;
  wch: number;
  fmt?: string;
}

/** Talformat som delas av alla linjer. */
export const XL_FMT = {
  int: '0',
  dec1: '0.0',
  dec2: '0.00',
  pct: '0.0%',
  pctSigned: '+0.0%;-0.0%',
} as const;

/**
 * Bygger ett blad från kolumndefinitioner + rader: talformat per kolumn på numeriska
 * celler, kolumnbredder, fryst rubrikrad och autofilter över hela området.
 */
export function buildExportSheet(XLSX: any, columns: ExcelColumn[], rows: any[][]): any {
  const ws = XLSX.utils.aoa_to_sheet([columns.map(c => c.header), ...rows]);
  const range = XLSX.utils.decode_range(ws['!ref']!);
  columns.forEach((col, C) => {
    if (!col.fmt) return;
    for (let R = 1; R <= range.e.r; R++) {
      const cell = ws[XLSX.utils.encode_cell({ r: R, c: C })];
      if (cell && typeof cell.v === 'number') cell.z = col.fmt;
    }
  });
  ws['!cols'] = columns.map(c => ({ wch: c.wch }));
  ws['!freeze'] = { xSplit: 0, ySplit: 1 };
  ws['!autofilter'] = { ref: ws['!ref']! };
  return ws;
}

/** Rubriker/bredder för bladet "Löpnummer" — en rad per IBC-cykel. */
export function lopnummerSheetColumns(opHeaders: [string, string, string]): ExcelColumn[] {
  return [
    { header: 'Datum', wch: 12 },
    { header: 'Tid', wch: 10 },
    { header: 'Löpnummer', wch: 11, fmt: XL_FMT.int },
    { header: 'Produkt', wch: 16 },
    { header: 'Resultat', wch: 13 },
    { header: opHeaders[0], wch: 18 },
    { header: opHeaders[1], wch: 18 },
    { header: opHeaders[2], wch: 18 },
  ];
}

/**
 * Löpnummer-intervall MED explicit flaggning av luckor/dubbletter (Oscar 2026-10-21).
 * Luckor och dubbletter räknas ut explicit och läggs på som ⚠-suffix så de aldrig kan
 * missas i genomläsning.
 */
export function buildLopnummerRangeFlagged(nums: number[]): string {
  if (!nums.length) return '–';
  const counts = new Map<number, number>();
  for (const n of nums) counts.set(n, (counts.get(n) || 0) + 1);
  const duplicates = [...counts.values()].filter(c => c > 1).length;
  const distinct = [...counts.keys()].sort((a, b) => a - b);
  const ranges: string[] = [];
  let start = distinct[0], prev = distinct[0];
  for (let i = 1; i < distinct.length; i++) {
    if (distinct[i] === prev + 1) { prev = distinct[i]; continue; }
    ranges.push(start === prev ? String(start) : `${start}–${prev}`);
    start = prev = distinct[i];
  }
  ranges.push(start === prev ? String(start) : `${start}–${prev}`);
  const gaps = ranges.length - 1;
  let out = ranges.join(', ');
  const flags: string[] = [];
  if (gaps > 0) flags.push(`${gaps} ${gaps > 1 ? 'luckor' : 'lucka'}`);
  if (duplicates > 0) flags.push(`${duplicates} dubblett${duplicates > 1 ? 'er' : ''}`);
  if (flags.length) out += ` ⚠ ${flags.join(', ')}`;
  return out;
}
