import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import {
  SkiftrapportExportService,
  ReportData,
  DagSummary,
  MultiDaySumma,
} from '../../services/skiftrapport-export.service';
import { localDateStr, localToday } from '../../utils/date-utils';

// pdfmake är installerat via package.json
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
(pdfMake as any).vfs = (pdfFonts as any).vfs;

@Component({
  standalone: true,
  selector: 'app-skiftrapport-export',
  templateUrl: './skiftrapport-export.html',
  styleUrls: ['./skiftrapport-export.css'],
  imports: [CommonModule, FormsModule],
})
export class SkiftrapportExportComponent implements OnInit, OnDestroy {
  // Läge: 'dag' eller 'vecka'
  lage: 'dag' | 'vecka' = 'dag';

  // Datumväljare
  valtDatum: string = '';
  valtStartDatum: string = '';
  valtSlutDatum: string = '';

  // Dagrapport
  laddar = false;
  rapport: ReportData | null = null;
  fel: string | null = null;

  // Veckorapport
  laddarVecka = false;
  veckoDagar: DagSummary[] = [];
  veckoSumma: MultiDaySumma | null = null;
  veckoFel: string | null = null;

  // PDF-generering
  genererarPdf = false;

  // Används i template för [max]-binding (new Date() är inte tillåtet i Angular-template)
  readonly todayISO: string = localToday();

  private destroy$ = new Subject<void>();

  constructor(private service: SkiftrapportExportService) {}

  ngOnInit(): void {
    // Default: igår
    const igar = new Date();
    igar.setDate(igar.getDate() - 1);
    this.valtDatum = this.formatDatumISO(igar);

    // Default vecka: senaste 7 dagarna
    const idag = new Date();
    idag.setDate(idag.getDate() - 1);
    const forraVecka = new Date();
    forraVecka.setDate(forraVecka.getDate() - 7);
    this.valtSlutDatum = this.formatDatumISO(idag);
    this.valtStartDatum = this.formatDatumISO(forraVecka);

    this.hamtaDagrapport();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ================================================================
  // LÄGESSTYRD DATAHÄMTNING
  // ================================================================

  byttLage(): void {
    this.rapport = null;
    this.veckoDagar = [];
    this.veckoSumma = null;
    this.fel = null;
    this.veckoFel = null;

    if (this.lage === 'dag') {
      this.hamtaDagrapport();
    } else {
      this.hamtaVeckorapport();
    }
  }

  hamtaDagrapport(): void {
    if (!this.valtDatum) return;
    this.laddar = true;
    this.fel = null;
    this.rapport = null;

    this.service.getReportData(this.valtDatum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.laddar = false;
        if (!res) {
          this.fel = 'Nätverksfel — kunde inte hämta data.';
          return;
        }
        if (!res.success) {
          this.fel = res.error ?? 'Okänt fel';
          return;
        }
        this.rapport = res.data;
      });
  }

  hamtaVeckorapport(): void {
    if (!this.valtStartDatum || !this.valtSlutDatum) return;
    this.laddarVecka = true;
    this.veckoFel = null;
    this.veckoDagar = [];
    this.veckoSumma = null;

    this.service.getMultiDayData(this.valtStartDatum, this.valtSlutDatum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.laddarVecka = false;
        if (!res) {
          this.veckoFel = 'Nätverksfel — kunde inte hämta veckodata.';
          return;
        }
        if (!res.success) {
          this.veckoFel = res.error ?? 'Okänt fel';
          return;
        }
        this.veckoDagar = res.data.dagar;
        this.veckoSumma = res.data.summa;
      });
  }

  // ================================================================
  // PDF-GENERERING (pdfmake)
  // ================================================================

  laddaNerPdf(): void {
    if (this.lage === 'dag') {
      this.genereraDagPdf();
    } else {
      this.genereraVeckoPdf();
    }
  }

  private genereraDagPdf(): void {
    if (!this.rapport) return;
    this.genererarPdf = true;

    const r = this.rapport;
    const datum = r.datum;
    const nu = new Date().toLocaleString('sv-SE');

    const docDef: any = {
      pageSize: 'A4',
      pageMargins: [40, 60, 40, 60],
      content: [],
      styles: this.pdfStilar(),
      defaultStyle: { font: 'Roboto' },
    };

    // Header
    docDef.content.push(
      { text: 'MauserDB Produktionsrapport', style: 'brandText' },
      { text: `Skiftrapport — ${this.formatDatumSv(datum)}`, style: 'rubrik' },
      { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 1, lineColor: '#4299e1' }] },
      { text: '', margin: [0, 8, 0, 0] }
    );

    if (!r.har_data || !r.produktion) {
      docDef.content.push({ text: 'Ingen produktionsdata hittades för valt datum.', style: 'normalText' });
      this.exporteraPdf(docDef, `skiftrapport_${datum}.pdf`);
      return;
    }

    const p = r.produktion;
    const c = r.cykeltider;
    const d = r.drifttid;
    const oee = r.oee;

    // Sammanfattningstabell
    docDef.content.push(
      { text: 'Produktionssammanfattning', style: 'sektionRubrik' },
      {
        table: {
          widths: ['*', '*'],
          body: [
            [{ text: 'Nyckeltal', style: 'tabellHeader', fillColor: '#2d3748' }, { text: 'Värde', style: 'tabellHeader', fillColor: '#2d3748' }],
            ['IBC OK', { text: `${p.ibc_ok} st`, bold: true }],
            ['IBC Ej OK', `${p.ibc_ej_ok} st`],
            ['Totalt IBC', `${p.ibc_total} st`],
            ['Kvalitet', { text: `${p.kvalitet_pct} %`, bold: true, color: this.kvalitetFarg(p.kvalitet_pct) }],
            ['IBC / timme', { text: `${p.ibc_per_timme}`, bold: true }],
            ['Antal skiften', `${p.antal_skiften}`],
            ['Skiftstart', p.skift_start ?? '—'],
            ['Skiftslut', p.skift_slut ?? '—'],
            ['Löpnummer', this.getLopnummerRange(r) || '—'],
          ],
        },
        layout: 'lightHorizontalLines',
        margin: [0, 4, 0, 12],
      }
    );

    // Cykeltider
    if (c) {
      docDef.content.push(
        { text: 'Cykeltider', style: 'sektionRubrik' },
        {
          table: {
            widths: ['*', '*'],
            body: [
              [{ text: 'Mätvärde', style: 'tabellHeader', fillColor: '#2d3748' }, { text: 'Värde', style: 'tabellHeader', fillColor: '#2d3748' }],
              ['Snitt cykeltid', c.avg_sek !== null ? `${this.formatSek(c.avg_sek)} (${c.avg_sek} sek)` : '—'],
              ['Kortaste cykel', c.min_sek !== null ? `${this.formatSek(c.min_sek)}` : '—'],
              ['Längsta cykel', c.max_sek !== null ? `${this.formatSek(c.max_sek)}` : '—'],
              ['Antal uppmätta cykler', `${c.antal_cykler}`],
            ],
          },
          layout: 'lightHorizontalLines',
          margin: [0, 4, 0, 12],
        }
      );
    }

    // Drifttid/stopptid
    if (d) {
      docDef.content.push(
        { text: 'Drifttid & Stopptid', style: 'sektionRubrik' },
        {
          table: {
            widths: ['*', '*'],
            body: [
              [{ text: 'Tid', style: 'tabellHeader', fillColor: '#2d3748' }, { text: 'Värde', style: 'tabellHeader', fillColor: '#2d3748' }],
              ['Drifttid', `${d.drifttid_min} min (${d.drifttid_pct} %)`],
              ['Stopptid', `${d.stopptid_min} min (${d.stopptid_pct} %)`],
              ['Rasttid', `${d.rast_min} min`],
              ['Planerad tid', `${d.planerad_min} min`],
            ],
          },
          layout: 'lightHorizontalLines',
          margin: [0, 4, 0, 12],
        }
      );
    }

    // OEE
    if (oee) {
      docDef.content.push(
        { text: 'OEE-approximation', style: 'sektionRubrik' },
        {
          table: {
            widths: ['*', '*'],
            body: [
              [{ text: 'Faktor', style: 'tabellHeader', fillColor: '#2d3748' }, { text: 'Värde', style: 'tabellHeader', fillColor: '#2d3748' }],
              ['OEE (totalt)', { text: `${oee.oee_pct} %`, bold: true, fontSize: 13, color: this.oeeFarg(oee.oee_pct) }],
              ['Tillgänglighet', `${oee.tillganglighet} %`],
              ['Prestanda', `${oee.prestanda} %`],
              ['Kvalitet', `${oee.kvalitet} %`],
              ['Teoret. max IBC/h', `${oee.teoretisk_max_ibc_per_h}`],
            ],
          },
          layout: 'lightHorizontalLines',
          margin: [0, 4, 0, 12],
        }
      );
    }

    // Operatörer
    if (r.operatorer.length > 0) {
      const opBody: any[][] = [
        [
          { text: 'Operatör', style: 'tabellHeader', fillColor: '#2d3748' },
          { text: 'Antal IBC', style: 'tabellHeader', fillColor: '#2d3748' },
          { text: 'Snitt cykeltid', style: 'tabellHeader', fillColor: '#2d3748' },
        ],
        ...r.operatorer.map(op => [
          op.namn,
          { text: `${op.antal_ibc}`, alignment: 'center' },
          { text: this.formatSek(op.avg_cykeltid), alignment: 'center' },
        ]),
      ];

      docDef.content.push(
        { text: 'Top-operatörer', style: 'sektionRubrik' },
        {
          table: { widths: ['*', 'auto', 'auto'], body: opBody },
          layout: 'lightHorizontalLines',
          margin: [0, 4, 0, 12],
        }
      );
    }

    // Trender
    if (r.trender) {
      const t = r.trender;
      docDef.content.push(
        { text: `Trender — jämförelse mot ${this.formatDatumSv(t.prev_datum)}`, style: 'sektionRubrik' },
        {
          table: {
            widths: ['*', 'auto', 'auto', 'auto'],
            body: [
              [
                { text: 'Mätvärde', style: 'tabellHeader', fillColor: '#2d3748' },
                { text: 'Förra veckan', style: 'tabellHeader', fillColor: '#2d3748' },
                { text: 'Idag', style: 'tabellHeader', fillColor: '#2d3748' },
                { text: 'Förändring', style: 'tabellHeader', fillColor: '#2d3748' },
              ],
              [
                'IBC OK',
                `${t.prev_ibc_ok}`,
                `${p.ibc_ok}`,
                t.diff_ibc_ok_pct !== null ? { text: this.trendText(t.diff_ibc_ok_pct, '%'), color: this.trendFarg(t.diff_ibc_ok_pct) } : '—',
              ],
              [
                'Kvalitet %',
                `${t.prev_kvalitet} %`,
                `${p.kvalitet_pct} %`,
                t.diff_kvalitet !== null ? { text: this.trendText(t.diff_kvalitet, 'pp'), color: this.trendFarg(t.diff_kvalitet) } : '—',
              ],
              [
                'IBC / timme',
                `${t.prev_ibc_per_h}`,
                `${p.ibc_per_timme}`,
                t.diff_ibc_per_h_pct !== null ? { text: this.trendText(t.diff_ibc_per_h_pct, '%'), color: this.trendFarg(t.diff_ibc_per_h_pct) } : '—',
              ],
            ],
          },
          layout: 'lightHorizontalLines',
          margin: [0, 4, 0, 16],
        }
      );
    }

    // Footer
    docDef.content.push(
      { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 0.5, lineColor: '#4a5568' }] },
      { text: `Genererad: ${nu}   |   MauserDB Produktionsrapport`, style: 'footer' }
    );

    this.exporteraPdf(docDef, `skiftrapport_${datum}.pdf`);
  }

  private genereraVeckoPdf(): void {
    if (!this.veckoDagar.length) return;
    this.genererarPdf = true;

    const nu = new Date().toLocaleString('sv-SE');
    const period = `${this.formatDatumSv(this.valtStartDatum)} – ${this.formatDatumSv(this.valtSlutDatum)}`;

    const docDef: any = {
      pageSize: 'A4',
      pageOrientation: 'landscape',
      pageMargins: [40, 60, 40, 60],
      content: [],
      styles: this.pdfStilar(),
      defaultStyle: { font: 'Roboto' },
    };

    docDef.content.push(
      { text: 'MauserDB Produktionsrapport', style: 'brandText' },
      { text: `Veckorapport — ${period}`, style: 'rubrik' },
      { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 760, y2: 0, lineWidth: 1, lineColor: '#4299e1' }] },
      { text: '', margin: [0, 8, 0, 0] }
    );

    // Dagstabell
    const dagBody: any[][] = [
      [
        { text: 'Datum', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'IBC OK', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'IBC Ej OK', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'Kvalitet %', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'IBC/h', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'Drifttid %', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'OEE %', style: 'tabellHeader', fillColor: '#2d3748' },
        { text: 'Skiften', style: 'tabellHeader', fillColor: '#2d3748' },
      ],
      ...this.veckoDagar.map(dag => [
        this.formatDatumSv(dag.dag),
        { text: `${dag.ibc_ok}`, alignment: 'center' },
        { text: `${dag.ibc_ej_ok}`, alignment: 'center' },
        { text: `${dag.kvalitet_pct} %`, alignment: 'center', color: this.kvalitetFarg(dag.kvalitet_pct) },
        { text: `${dag.ibc_per_timme}`, alignment: 'center' },
        { text: `${dag.drifttid_pct} %`, alignment: 'center' },
        { text: `${dag.oee_pct} %`, alignment: 'center', color: this.oeeFarg(dag.oee_pct) },
        { text: `${dag.antal_skiften}`, alignment: 'center' },
      ]),
    ];

    if (this.veckoSumma) {
      const s = this.veckoSumma;
      dagBody.push([
        { text: 'SUMMA', bold: true, fillColor: '#1a365d', color: '#bee3f8' },
        { text: `${s.ibc_ok}`, alignment: 'center', bold: true, fillColor: '#1a365d', color: '#bee3f8' },
        { text: `${s.ibc_total - s.ibc_ok}`, alignment: 'center', fillColor: '#1a365d', color: '#bee3f8' },
        { text: `${s.kvalitet_pct} %`, alignment: 'center', bold: true, fillColor: '#1a365d', color: '#bee3f8' },
        { text: `${s.ibc_per_timme}`, alignment: 'center', fillColor: '#1a365d', color: '#bee3f8' },
        { text: '—', alignment: 'center', fillColor: '#1a365d', color: '#bee3f8' },
        { text: '—', alignment: 'center', fillColor: '#1a365d', color: '#bee3f8' },
        { text: '—', alignment: 'center', fillColor: '#1a365d', color: '#bee3f8' },
      ]);
    }

    docDef.content.push(
      { text: 'Daglig sammanfattning', style: 'sektionRubrik' },
      {
        table: {
          widths: ['*', 'auto', 'auto', 'auto', 'auto', 'auto', 'auto', 'auto'],
          body: dagBody,
        },
        layout: 'lightHorizontalLines',
        margin: [0, 4, 0, 16],
      }
    );

    if (this.veckoSumma) {
      const s = this.veckoSumma;
      docDef.content.push(
        { text: 'Periodssammanfattning', style: 'sektionRubrik' },
        {
          columns: [
            { text: `Totalt IBC OK: ${s.ibc_ok}`, style: 'sammanfattningKort' },
            { text: `Totalt IBC: ${s.ibc_total}`, style: 'sammanfattningKort' },
            { text: `Snittkvalitet: ${s.kvalitet_pct} %`, style: 'sammanfattningKort' },
            { text: `Snitt IBC/h: ${s.ibc_per_timme}`, style: 'sammanfattningKort' },
            { text: `Snitt IBC/dag: ${s.snitt_ibc_per_dag}`, style: 'sammanfattningKort' },
          ],
          margin: [0, 4, 0, 16],
        }
      );
    }

    docDef.content.push(
      { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 760, y2: 0, lineWidth: 0.5, lineColor: '#4a5568' }] },
      { text: `Genererad: ${nu}   |   MauserDB Produktionsrapport`, style: 'footer' }
    );

    this.exporteraPdf(docDef, `veckorapport_${this.valtStartDatum}_${this.valtSlutDatum}.pdf`);
  }

  pdfFel: string | null = null;

  private exporteraPdf(docDef: any, filnamn: string): void {
    this.pdfFel = null;
    try {
      pdfMake.createPdf(docDef).download(filnamn);
    } catch (err) {
      console.error('PDF-generering misslyckades:', err);
      this.pdfFel = 'PDF-generering misslyckades. Försök igen.';
    } finally {
      this.genererarPdf = false;
    }
  }

  // ================================================================
  // UTSKRIFT
  // ================================================================

  skrivUt(): void {
    window.print();
  }

  // ================================================================
  // PDF-STILAR
  // ================================================================

  private pdfStilar(): any {
    return {
      brandText:       { fontSize: 10, color: '#718096', margin: [0, 0, 0, 4] },
      rubrik:          { fontSize: 20, bold: true, color: '#2b6cb0', margin: [0, 0, 0, 8] },
      sektionRubrik:   { fontSize: 13, bold: true, color: '#2b6cb0', margin: [0, 8, 0, 4] },
      tabellHeader:    { bold: true, color: '#bee3f8', fontSize: 10 },
      normalText:      { fontSize: 11, color: '#4a5568' },
      footer:          { fontSize: 9, color: '#718096', margin: [0, 8, 0, 0], alignment: 'center' },
      sammanfattningKort: { fontSize: 11, bold: true, color: '#2b6cb0', margin: [0, 4, 0, 4] },
    };
  }

  // ================================================================
  // HJÄLPMETODER
  // ================================================================

  formatDatumISO(d: Date): string {
    return localDateStr(d);
  }

  formatDatumSv(iso: string): string {
    if (!iso) return '—';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  formatSek(sek: number | null): string {
    if (sek === null) return '—';
    const m = Math.floor(sek / 60);
    const s = Math.round(sek % 60);
    if (m === 0) return `${s} sek`;
    return `${m} min ${s.toString().padStart(2, '0')} sek`;
  }

  formatMin(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h === 0) return `${m} min`;
    return `${h} h ${m.toString().padStart(2, '0')} min`;
  }

  trendText(diff: number, enhet: string): string {
    const sign = diff > 0 ? '+' : '';
    return `${sign}${diff} ${enhet}`;
  }

  trendFarg(diff: number): string {
    if (diff > 0) return '#68d391';
    if (diff < 0) return '#fc8181';
    return '#a0aec0';
  }

  trendIkon(diff: number | null): string {
    if (diff === null) return '';
    if (diff > 0) return '▲';
    if (diff < 0) return '▼';
    return '=';
  }

  kvalitetFarg(pct: number): string {
    if (pct >= 95) return '#68d391';
    if (pct >= 85) return '#f6e05e';
    return '#fc8181';
  }

  oeeFarg(pct: number): string {
    if (pct >= 65) return '#68d391';
    if (pct >= 45) return '#f6e05e';
    return '#fc8181';
  }

  getLopnummerRange(rapport: ReportData): string {
    if (!rapport?.skiften?.length) return '';
    const ranges = rapport.skiften
      .filter((s: any) => s.lopnummer_range && s.lopnummer_range !== '–')
      .map((s: any) => s.lopnummer_range);
    return ranges.join(', ') || '';
  }

  drifttidBredd(pct: number): string {
    return `${Math.min(100, Math.max(0, pct))}%`;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
