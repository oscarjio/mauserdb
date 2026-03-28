import { Component, OnInit, OnDestroy, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../../utils/date-utils';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

import {
  StatistikDashboardService,
  DashboardSummary,
  ProductionTrendData,
  ProductionTrendItem,
  DailyTableData,
  DailyTableRow,
  StatusIndicator,
} from '../../../services/statistik-dashboard.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-statistik-dashboard',
  templateUrl: './statistik-dashboard.component.html',
  styleUrls: ['./statistik-dashboard.component.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class StatistikDashboardPage implements OnInit, OnDestroy {

  // ---- Period ----
  trendPeriod = 30;
  readonly periodOptions = [
    { val: 1,   label: '1 dag'     },
    { val: 7,   label: '7 dagar'   },
    { val: 14,  label: '14 dagar'  },
    { val: 30,  label: '30 dagar'  },
    { val: 90,  label: '90 dagar'  },
    { val: 180, label: '180 dagar' },
    { val: 365, label: '365 dagar' },
  ];

  // ---- Laddning ----
  loadingSummary   = false;
  loadingTrend     = false;
  loadingTable     = false;
  loadingStatus    = false;

  // ---- Fel ----
  errorSummary   = false;
  errorTrend     = false;
  errorTable     = false;
  errorStatus    = false;

  // ---- Data ----
  summary:   DashboardSummary | null       = null;
  trendData: ProductionTrendData | null    = null;
  tableData: DailyTableData | null         = null;
  status:    StatusIndicator | null        = null;

  // ---- Senaste uppdatering ----
  lastUpdated: string | null = null;

  // ---- Chart ----
  private trendChart: Chart | null = null;

  // ---- Lifecycle ----
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private chartBuildTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: StatistikDashboardService) {}

  ngOnInit(): void {
    this.loadAll();
    // Statistik-dashboard: uppdatera var 2:a minut (data andras inte varje sekund)
    this.refreshInterval = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.chartBuildTimer) {
      clearTimeout(this.chartBuildTimer);
      this.chartBuildTimer = null;
    }
    this.destroyChart();
  }

  // ================================================================
  // LOAD
  // ================================================================

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadSummary();
    this.loadTrend();
    this.loadTable();
    this.loadStatus();
    this.lastUpdated = new Date().toLocaleTimeString('sv-SE');
  }

  onPeriodChange(): void {
    this.loadTrend();
  }

  private loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getSummary().pipe(
      timeout(15000),
      catchError(() => { this.errorSummary = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingSummary = false;
      this.isFetching = false;
      if (res?.success) {
        this.summary = res.data;
      } else if (res !== null) {
        this.errorSummary = true;
      }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.svc.getProductionTrend(this.trendPeriod).pipe(
      timeout(15000),
      catchError(() => { this.errorTrend = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        if (this.chartBuildTimer) clearTimeout(this.chartBuildTimer);
        this.chartBuildTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildChart(); }, 100);
      } else if (res !== null) {
        this.errorTrend = true;
      }
    });
  }

  private loadTable(): void {
    this.loadingTable = true;
    this.errorTable   = false;
    this.svc.getDailyTable().pipe(
      timeout(15000),
      catchError(() => { this.errorTable = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTable = false;
      if (res?.success) {
        this.tableData = res.data;
      } else if (res !== null) {
        this.errorTable = true;
      }
    });
  }

  private loadStatus(): void {
    this.loadingStatus = true;
    this.errorStatus   = false;
    this.svc.getStatusIndicator().pipe(
      timeout(15000),
      catchError(() => { this.errorStatus = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingStatus = false;
      if (res?.success) {
        this.status = res.data;
      } else if (res !== null) {
        this.errorStatus = true;
      }
    });
  }

  // ================================================================
  // CHART
  // ================================================================

  private destroyChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  private buildChart(): void {
    this.destroyChart();
    if (!this.trendData?.daily?.length) return;

    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const daily     = this.trendData.daily;

    // Adaptiv granularitet: aggregera data baserat pa tidsspann
    const aggregated = this.aggregateByGranularity(daily);
    const labels    = aggregated.map(d => d.label);
    const ibcData   = aggregated.map(d => d.total);
    const kassData  = aggregated.map(d => d.kassation_pct);
    const snittLine = aggregated.map(() => this.trendData!.snitt_ibc_dag);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC totalt',
            data: ibcData,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.15)',
            borderWidth: 2,
            fill: true,
            pointRadius: aggregated.length > 60 ? 0 : aggregated.length > 30 ? 2 : 4,
            pointHoverRadius: 7,
            tension: 0.3,
            yAxisID: 'yLeft',
          },
          {
            label: 'Snitt IBC/dag',
            data: snittLine,
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [6, 4],
            fill: false,
            pointRadius: 0,
            yAxisID: 'yLeft',
          },
          {
            label: 'Kassation %',
            data: kassData,
            borderColor: '#fc8181',
            backgroundColor: 'rgba(252,129,129,0.08)',
            borderWidth: 2,
            fill: false,
            pointRadius: 3,
            pointHoverRadius: 6,
            tension: 0.3,
            yAxisID: 'yRight',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onClick: (_event: any, elements: any[]) => {
          if (elements.length > 0) {
            const idx   = elements[0].index;
            const item  = aggregated[idx];
            if (item) this.showTrendTooltip(item as any);
          }
        },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            intersect: false, mode: 'index',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            padding: 10,
            titleFont: { weight: 'bold' as const, size: 13 },
            bodyFont: { size: 12 },
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? 0;
                const item = aggregated[idx];
                if (!item) return '';
                return `${this.formatDatum(item.datum)}`;
              },
              label: (ctx: any) => {
                if (ctx.datasetIndex === 0) return ` IBC totalt: ${ctx.parsed.y} st`;
                if (ctx.datasetIndex === 1) return ` Snitt IBC/dag: ${ctx.parsed.y}`;
                if (ctx.datasetIndex === 2) return ` Kassation: ${ctx.parsed.y}%`;
                return ` ${ctx.dataset.label}: ${ctx.parsed.y}`;
              },
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx < 0 || idx >= aggregated.length) return [];
                const item = aggregated[idx];
                const lines: string[] = [];
                if (item.drifttid_h != null) lines.push(`Drifttid: ${item.drifttid_h.toFixed(1)}h`);
                if (item.ibc_per_h != null) lines.push(`IBC/h: ${item.ibc_per_h.toFixed(1)}`);
                if (item.ibc_ok != null) lines.push(`Godkända: ${item.ibc_ok} | Kasserade: ${item.ibc_ej_ok}`);
                return lines;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              maxRotation: 45,
              maxTicksLimit: this.getAdaptiveTickLimit(),
            },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'IBC (antal)', color: '#63b3ed', font: { size: 12 } },
            ticks: {
              color: '#a0aec0',
              callback: (val: any) => val >= 1000 ? (val / 1000).toFixed(1) + 'k' : val,
            },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          yRight: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'Kassation %', color: '#fc8181' },
            ticks: {
              color: '#fc8181',
              callback: (val: any) => `${val}%`,
            },
            grid: { drawOnChartArea: false },
            beginAtZero: true,
            min: 0,
          },
        },
      },
    });
  }

  // ================================================================
  // ADAPTIV GRANULARITET
  // ================================================================

  /** Returnera adaptiv maxTicksLimit baserat pa period */
  private getAdaptiveTickLimit(): number {
    if (this.trendPeriod <= 7) return 7;
    if (this.trendPeriod <= 14) return 14;
    if (this.trendPeriod <= 30) return 15;
    if (this.trendPeriod <= 90) return 12;
    if (this.trendPeriod <= 180) return 12;
    return 12; // 365 dagar -> visa 12 etiketter (manadsvis)
  }

  /** Granularitetsetikett for UI */
  getGranularityLabel(): string {
    if (this.trendPeriod <= 1) return 'per timme';
    if (this.trendPeriod <= 14) return 'per dag';
    if (this.trendPeriod <= 30) return 'per dag';
    if (this.trendPeriod <= 90) return 'per vecka';
    return 'per vecka';
  }

  /** Aggregera daglig data till lamplig granularitet baserat pa tidsspann.
   *  <= 14 dagar: visa per dag (ingen aggregering).
   *  > 14 dagar: aggregera per vecka.
   */
  private aggregateByGranularity(daily: ProductionTrendItem[]): {
    label: string;
    datum: string;
    total: number;
    ibc_ok: number;
    ibc_ej_ok: number;
    kassation_pct: number;
    drifttid_h: number;
    ibc_per_h: number;
  }[] {
    if (this.trendPeriod <= 30) {
      // Per dag — visa rakt av
      return daily.map(d => ({
        label: d.datum,
        datum: d.datum,
        total: d.total,
        ibc_ok: d.ibc_ok,
        ibc_ej_ok: d.ibc_ej_ok,
        kassation_pct: d.kassation_pct,
        drifttid_h: d.drifttid_h,
        ibc_per_h: d.ibc_per_h,
      }));
    }

    // >= 90 dagar -> aggregera per vecka
    const weeks = new Map<string, ProductionTrendItem[]>();
    daily.forEach(d => {
      const dt = parseLocalDate(d.datum);
      // ISO vecka: man-son
      const dayOfWeek = dt.getDay() || 7; // son=7
      const monday = new Date(dt);
      monday.setDate(dt.getDate() - dayOfWeek + 1);
      const key = `${monday.getFullYear()}-${String(monday.getMonth() + 1).padStart(2, '0')}-${String(monday.getDate()).padStart(2, '0')}`;
      if (!weeks.has(key)) weeks.set(key, []);
      weeks.get(key)!.push(d);
    });

    const result: any[] = [];
    weeks.forEach((items, key) => {
      const totalIbc = items.reduce((s, i) => s + i.total, 0);
      const totalOk = items.reduce((s, i) => s + i.ibc_ok, 0);
      const totalEjOk = items.reduce((s, i) => s + i.ibc_ej_ok, 0);
      const avgKass = totalIbc > 0 ? +(totalEjOk / totalIbc * 100).toFixed(1) : 0;
      const totalDrift = items.reduce((s, i) => s + i.drifttid_h, 0);
      const avgIbcH = totalDrift > 0 ? +(totalIbc / totalDrift).toFixed(1) : 0;
      result.push({
        label: `V${key.slice(5)}`,
        datum: key,
        total: totalIbc,
        ibc_ok: totalOk,
        ibc_ej_ok: totalEjOk,
        kassation_pct: avgKass,
        drifttid_h: +totalDrift.toFixed(1),
        ibc_per_h: avgIbcH,
      });
    });
    return result;
  }

  // ================================================================
  // HELPERS
  // ================================================================

  tooltipItem: ProductionTrendItem | null = null;

  @HostListener('document:keydown.escape')
  onEscapeKey(): void {
    if (this.tooltipItem) { this.closeTrendTooltip(); }
  }

  showTrendTooltip(item: ProductionTrendItem): void {
    this.tooltipItem = item;
  }

  closeTrendTooltip(): void {
    this.tooltipItem = null;
  }

  getStatusClass(): string {
    if (!this.status) return 'status-grön';
    return 'status-' + this.status.status;
  }

  getStatusBgClass(): string {
    if (!this.status) return 'bg-success';
    switch (this.status.status) {
      case 'röd':  return 'bg-danger';
      case 'gul':  return 'bg-warning';
      default:     return 'bg-success';
    }
  }

  getDiffClass(current: number | undefined | null, previous: number | undefined | null, invertiert = false): string {
    const c = current ?? 0;
    const p = previous ?? 0;
    if (c === p) return 'text-muted';
    const better = invertiert ? c < p : c > p;
    return better ? 'text-success' : 'text-danger';
  }

  getDiffIcon(current: number | undefined | null, previous: number | undefined | null, invertiert = false): string {
    const c = current ?? 0;
    const p = previous ?? 0;
    if (c === p) return 'fas fa-minus';
    const better = invertiert ? c < p : c > p;
    return better ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
  }

  getDiffValue(current: number | undefined | null, previous: number | undefined | null): string {
    const c = current ?? 0;
    const p = previous ?? 0;
    const diff = c - p;
    return diff >= 0 ? `+${diff}` : `${diff}`;
  }

  getDiffPct(current: number | undefined | null, previous: number | undefined | null): string {
    const c = current ?? 0;
    const p = previous ?? 0;
    if (p === 0) return '';
    const diff = ((c - p) / p * 100);
    return diff >= 0 ? `+${diff.toFixed(1)}%` : `${diff.toFixed(1)}%`;
  }

  getRowClass(row: DailyTableRow): string {
    switch (row.fargklass) {
      case 'röd':  return 'table-danger-row';
      case 'gul':  return 'table-warning-row';
      default:     return 'table-success-row';
    }
  }

  getKassationBadgeClass(pct: number): string {
    if (pct < 5)   return 'badge-grön';
    if (pct <= 10) return 'badge-gul';
    return 'badge-röd';
  }

  ibcPerHVsMal(ibcH: number): string {
    if (!this.summary) return 'text-muted';
    const mal = this.summary.mal_ibc_per_h;
    if (ibcH >= mal)           return 'text-success';
    if (ibcH >= mal * 0.85)    return 'text-warning';
    return 'text-danger';
  }

  formatDatum(datum: string): string {
    if (!datum) return '';
    const d = parseLocalDate(datum);
    const dagar = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${dagar[d.getDay()]} ${datum}`;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
  trackByDatum(index: number, item: any): any { return item?.datum ?? index; }

  // ================================================================
  // CSV-EXPORT
  // ================================================================

  exportCsv(): void {
    if (!this.tableData?.rows?.length) return;

    const sep = ';';
    const headers = ['Datum', 'IBC totalt', 'IBC OK', 'IBC ej OK', 'Kassation %', 'Drifttid (h)', 'Drifttid %', 'IBC/h', 'Färgklass', 'Bästa operatör', 'Bästa op. IBC'];
    const lines: string[] = [headers.join(sep)];

    for (const row of this.tableData.rows) {
      lines.push([
        row.datum,
        row.total,
        row.ibc_ok,
        row.ibc_ej_ok,
        row.kassation_pct,
        row.drifttid_h,
        row.drifttid_h && this.summary ? ((row.drifttid_h / this.summary.planerad_drift_h) * 100).toFixed(1) : '',
        row.ibc_per_h,
        row.fargklass,
        row.basta_operator?.operator_name ?? '',
        row.basta_operator?.ibc_ok ?? '',
      ].join(sep));
    }

    if (this.tableData.veckosnitt) {
      const vs = this.tableData.veckosnitt;
      lines.push([
        'Veckosnitt/Total',
        vs.total,
        vs.ibc_ok,
        vs.ibc_ej_ok,
        vs.kassation_pct,
        vs.drifttid_h,
        '',
        vs.ibc_per_h,
        '',
        '',
        '',
      ].join(sep));
    }

    // KPI-sammanfattning
    if (this.summary) {
      lines.push('');
      lines.push('KPI-sammanfattning');
      lines.push(`IBC idag${sep}${this.summary.idag.total}`);
      lines.push(`IBC OK idag${sep}${this.summary.idag.ibc_ok}`);
      lines.push(`IBC ej OK idag${sep}${this.summary.idag.ibc_ej_ok}`);
      lines.push(`IBC denna vecka${sep}${this.summary.denna_vecka.total}`);
      lines.push(`IBC förra veckan${sep}${this.summary.forra_veckan.total}`);
      lines.push(`Kassationsgrad idag${sep}${this.summary.idag.kassation_pct}%`);
      lines.push(`Kassationsgrad igår${sep}${this.summary.igar.kassation_pct}%`);
      lines.push(`Mål kassation${sep}<${this.summary.mal_kassation}%`);
      lines.push(`Drifttid idag${sep}${this.summary.idag.drifttid_h}h`);
      lines.push(`Planerad drifttid${sep}${this.summary.planerad_drift_h}h`);
      lines.push(`Snitt IBC/h (7d)${sep}${this.summary.snitt_ibc_per_h}`);
      lines.push(`Mål IBC/h${sep}${this.summary.mal_ibc_per_h}`);
      if (this.summary.aktiv_operator) {
        lines.push(`Aktiv operatör${sep}${this.summary.aktiv_operator.operator_name}`);
      }
    }

    // Statusindikator
    if (this.status) {
      lines.push('');
      lines.push('Statusindikator');
      lines.push(`Status${sep}${this.status.status_text}`);
      lines.push(`Kassation idag${sep}${this.status.kassation_idag}%`);
      lines.push(`IBC/h idag${sep}${this.status.ibc_per_h_idag}`);
      lines.push(`Stopptid idag${sep}${this.status.stopp_min_idag} min`);
      if (this.status.problem.length > 0) {
        lines.push(`Problem${sep}${this.status.problem.join(', ')}`);
      }
      if (this.status.varning.length > 0) {
        lines.push(`Varningar${sep}${this.status.varning.join(', ')}`);
      }
    }

    // Månads-/kvartalsjämförelse
    if (this.summary?.denna_manad && this.summary?.forra_manaden) {
      lines.push('');
      lines.push('Månadsjämförelse');
      lines.push(`Denna månad IBC${sep}${this.summary.denna_manad.total}`);
      lines.push(`Förra månaden IBC${sep}${this.summary.forra_manaden.total}`);
      lines.push(`Denna månad kassation${sep}${this.summary.denna_manad.kassation_pct}%`);
      lines.push(`Denna månad drifttid${sep}${this.summary.denna_manad.drifttid_h}h`);
    }

    if (this.summary?.detta_kvartal && this.summary?.forra_kvartalet) {
      lines.push('');
      lines.push('Kvartalsjämförelse');
      lines.push(`Detta kvartal IBC${sep}${this.summary.detta_kvartal.total}`);
      lines.push(`Förra kvartalet IBC${sep}${this.summary.forra_kvartalet.total}`);
      lines.push(`Detta kvartal kassation${sep}${this.summary.detta_kvartal.kassation_pct}%`);
      lines.push(`Detta kvartal drifttid${sep}${this.summary.detta_kvartal.drifttid_h}h`);
    }

    // Trenddata
    if (this.trendData?.daily?.length) {
      lines.push('');
      lines.push(`Produktionstrend (${this.trendPeriod} dagar)`);
      lines.push(['Datum', 'IBC totalt', 'IBC OK', 'IBC ej OK', 'Kassation %', 'Drifttid (h)', 'IBC/h'].join(sep));
      for (const d of this.trendData.daily) {
        lines.push([d.datum, d.total, d.ibc_ok, d.ibc_ej_ok, d.kassation_pct, d.drifttid_h, d.ibc_per_h].join(sep));
      }
      lines.push(`Snitt IBC/dag${sep}${this.trendData.snitt_ibc_dag}`);
      lines.push(`Snitt IBC/h${sep}${this.trendData.snitt_ibc_h}`);
      lines.push(`Mål IBC/h${sep}${this.trendData.mal_ibc_per_h}`);
    }

    const bom = '\uFEFF';
    const blob = new Blob([bom + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const datum = new Date().toISOString().slice(0, 10);
    a.download = `statistik-dashboard-${datum}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}
