import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  RebotlingService,
  OperatorJamforelseItem,
  OperatorJamforelseKpi,
  OperatorJamforelseTrendRow,
} from '../../../services/rebotling.service';

Chart.register(...registerables);

interface PeriodOption { val: number; label: string; }

@Component({
  standalone: true,
  selector: 'app-operator-jamforelse',
  templateUrl: './operator-jamforelse.html',
  styleUrl: './operator-jamforelse.css',
  imports: [CommonModule, FormsModule],
})
export class OperatorJamforelsePage implements OnInit, OnDestroy {
  Math = Math;

  // ---- Period ----
  period = 30;
  readonly periodOptions: PeriodOption[] = [
    { val: 7,  label: '7 dagar'  },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  // ---- Operatörslista ----
  alleOperatorer: OperatorJamforelseItem[] = [];
  valdaIds: Set<number> = new Set();
  dropdownOpen = false;

  // ---- Laddning ----
  loadingOperators = false;
  loadingCompare   = false;
  loadingTrend     = false;

  // ---- Fel ----
  errorOperators = false;
  errorCompare   = false;
  errorTrend     = false;

  // ---- Data ----
  compareData: OperatorJamforelseKpi[]     = [];
  trendData:   OperatorJamforelseTrendRow[] = [];
  // Cached KPI row values — key: "opIndex_kpi" -> formatted string
  cachedKpiValues: Map<string, string> = new Map();
  // Cached best operator per KPI
  cachedBestOp: Map<string, number> = new Map();

  // ---- Charts ----
  private lineChart:  Chart | null = null;
  private radarChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private isFetchingCompare = false;
  private isFetchingTrend   = false;

  readonly COLORS = [
    '#4299e1', '#48bb78', '#ecc94b',
    '#ed8936', '#9f7aea', '#38b2ac',
  ];

  constructor(private svc: RebotlingService) {}

  ngOnInit(): void {
    this.loadOperatorsList();
    this.refreshTimer = setInterval(() => this.autoRefresh(), 120_000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyAllCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  private destroyAllCharts(): void {
    try { this.lineChart?.destroy();  } catch (_) {}
    try { this.radarChart?.destroy(); } catch (_) {}
    this.lineChart  = null;
    this.radarChart = null;
  }

  private autoRefresh(): void {
    if (this.valdaIds.size > 0) {
      this.loadCompare();
      this.loadTrend();
    }
  }

  // ================================================================
  // Operatörslista
  // ================================================================

  loadOperatorsList(): void {
    this.loadingOperators = true;
    this.errorOperators   = false;

    this.svc.getOperatorsForCompare()
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOperators = false;
        if (res?.success) {
          this.alleOperatorer = res.data?.operatorer ?? [];
        } else {
          this.errorOperators = true;
          this.alleOperatorer = [];
        }
      });
  }

  // ================================================================
  // Dropdown-val
  // ================================================================

  toggleDropdown(): void {
    this.dropdownOpen = !this.dropdownOpen;
  }

  closeDropdown(): void {
    this.dropdownOpen = false;
  }

  isVald(id: number): boolean {
    return this.valdaIds.has(id);
  }

  toggleOperator(id: number): void {
    if (this.valdaIds.has(id)) {
      this.valdaIds.delete(id);
    } else {
      if (this.valdaIds.size >= 3) return; // Max 3
      this.valdaIds.add(id);
    }
    this.onSelectionChange();
  }

  clearSelection(): void {
    this.valdaIds.clear();
    this.compareData = [];
    this.trendData   = [];
    this.destroyAllCharts();
  }

  get valdaNamn(): string {
    if (this.valdaIds.size === 0) return 'Valj operatorer...';
    const valda = this.alleOperatorer.filter(o => this.valdaIds.has(o.id));
    return valda.map(o => o.namn).join(', ');
  }

  get valdaLista(): OperatorJamforelseItem[] {
    return this.alleOperatorer.filter(o => this.valdaIds.has(o.id));
  }

  private onSelectionChange(): void {
    if (this.valdaIds.size > 0) {
      this.loadCompare();
      this.loadTrend();
    } else {
      this.compareData = [];
      this.trendData   = [];
      this.destroyAllCharts();
    }
  }

  // ================================================================
  // Period
  // ================================================================

  onPeriodChange(p: number): void {
    this.period = p;
    if (this.valdaIds.size > 0) {
      this.loadCompare();
      this.loadTrend();
    }
  }

  // ================================================================
  // Data loading
  // ================================================================

  loadCompare(): void {
    if (this.isFetchingCompare) return;
    this.isFetchingCompare = true;
    this.loadingCompare    = true;
    this.errorCompare      = false;

    const ids = Array.from(this.valdaIds);
    this.svc.compareOperators(ids, this.period)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.isFetchingCompare = false;
          this.loadingCompare    = false;
          if (res?.success) {
            this.compareData = res.data?.operatorer ?? [];
            this.rebuildKpiCache();
            setTimeout(() => {
              if (!this.destroy$.closed) { this.buildRadarChart(); }
            }, 0);
          } else {
            this.errorCompare = true;
            this.compareData  = [];
          }
        },
        error: () => {
          this.isFetchingCompare = false;
          this.loadingCompare    = false;
          this.errorCompare      = true;
        },
      });
  }

  loadTrend(): void {
    if (this.isFetchingTrend) return;
    this.isFetchingTrend = true;
    this.loadingTrend    = true;
    this.errorTrend      = false;

    const ids = Array.from(this.valdaIds);
    this.svc.compareOperatorsTrend(ids, this.period)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.isFetchingTrend = false;
          this.loadingTrend    = false;
          if (res?.success) {
            this.trendData = res.data?.operatorer ?? [];
            setTimeout(() => {
              if (!this.destroy$.closed) { this.buildLineChart(); }
            }, 0);
          } else {
            this.errorTrend = true;
            this.trendData  = [];
          }
        },
        error: () => {
          this.isFetchingTrend = false;
          this.loadingTrend    = false;
          this.errorTrend      = true;
        },
      });
  }

  // ================================================================
  // Chart.js — Linjediagram: IBC/dag per operatör
  // ================================================================

  private buildLineChart(): void {
    try { this.lineChart?.destroy(); } catch (_) {}
    this.lineChart = null;

    if (!this.trendData.length) return;

    const canvas = document.getElementById('jamforelseLineChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    // Samla alla datum (union)
    const datumSet = new Set<string>();
    for (const op of this.trendData) {
      for (const t of op.trend) { datumSet.add(t.datum); }
    }
    const labels = Array.from(datumSet).sort();

    const datasets = this.trendData.map((op, i) => {
      const byDatum = new Map(op.trend.map(t => [t.datum, t.ibc_count]));
      const data    = labels.map(d => byDatum.get(d) ?? null);
      const color   = this.COLORS[i % this.COLORS.length];
      return {
        label: op.namn,
        data,
        borderColor: color,
        backgroundColor: color + '22',
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: color,
        fill: false,
        tension: 0.3,
        spanGaps: true,
      };
    });

    this.lineChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 12 } } },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const val = ctx.parsed.y;
                return val !== null ? ` ${val} IBC` : ' Ingen data';
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 15 },
            grid:  { color: '#374151' },
            title: { display: true, text: 'Datum', color: '#a0aec0' },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid:  { color: '#374151' },
            title: { display: true, text: 'IBC/dag', color: '#a0aec0' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ================================================================
  // Chart.js — Radardiagram: normaliserade KPI:er
  // ================================================================

  private buildRadarChart(): void {
    try { this.radarChart?.destroy(); } catch (_) {}
    this.radarChart = null;

    if (!this.compareData.length) return;

    const canvas = document.getElementById('jamforelseRadarChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    // Normalisera KPI:er 0-100 baserat på max-värde bland valda operatörer
    const maxIbc   = Math.max(...this.compareData.map(o => o.totalt_ibc    || 0), 1);
    const maxIbcH  = Math.max(...this.compareData.map(o => o.ibc_per_h     || 0), 1);
    const maxTimme = Math.max(...this.compareData.map(o => o.aktiva_timmar || 0), 1);
    // Stopptid — lägre = bättre → inverteras
    const maxStopp = Math.max(...this.compareData.map(o => o.total_stopptid_min || 0), 1);

    const kpiLabels = ['Totalt IBC', 'IBC/h', 'Kvalitet %', 'Aktiva timmar', 'Liten stopptid'];

    const datasets = this.compareData.map((op, i) => {
      const color = this.COLORS[i % this.COLORS.length];
      const kvalitet = op.kvalitetsgrad ?? 0;
      const stoppScore = maxStopp > 0
        ? Math.max(0, 100 - ((op.total_stopptid_min / maxStopp) * 100))
        : 100;
      const data = [
        Math.round((op.totalt_ibc    / maxIbc)   * 100),
        Math.round((op.ibc_per_h     / maxIbcH)  * 100),
        Math.round(kvalitet),
        Math.round((op.aktiva_timmar / maxTimme)  * 100),
        Math.round(stoppScore),
      ];
      return {
        label: op.namn,
        data,
        borderColor: color,
        backgroundColor: color + '33',
        borderWidth: 2,
        pointBackgroundColor: color,
        pointRadius: 4,
      };
    });

    this.radarChart = new Chart(canvas, {
      type: 'radar',
      data: { labels: kpiLabels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 12 } } },
          tooltip: {
            callbacks: {
              label: (ctx: any) => ` ${ctx.dataset.label}: ${ctx.parsed.r}`,
            },
          },
        },
        scales: {
          r: {
            min: 0,
            max: 100,
            ticks: {
              color: '#718096',
              backdropColor: 'transparent',
              stepSize: 20,
            },
            pointLabels: { color: '#a0aec0', font: { size: 12 } },
            grid:  { color: '#374151' },
            angleLines: { color: '#374151' },
          },
        },
      },
    });
  }

  /** Rebuild cached KPI values and best-operator lookups */
  private rebuildKpiCache(): void {
    this.cachedKpiValues.clear();
    this.cachedBestOp.clear();

    const kpis = ['totalt_ibc', 'ibc_per_h', 'kvalitetsgrad', 'antal_stopp', 'total_stopptid_min', 'aktiva_timmar'];
    for (const kpi of kpis) {
      this.cachedBestOp.set(kpi, this.bestOperatorFor(kpi));
    }
    for (let i = 0; i < this.compareData.length; i++) {
      const op = this.compareData[i];
      for (const kpi of kpis) {
        this.cachedKpiValues.set(`${i}_${kpi}`, this.kpiRowValue(op, kpi));
      }
    }
  }

  // ================================================================
  // Template helpers
  // ================================================================

  get harData(): boolean { return this.compareData.length > 0; }
  get harTrend(): boolean { return this.trendData.some(o => o.trend.length > 0); }

  operatorFarg(index: number): string {
    return this.COLORS[index % this.COLORS.length];
  }

  kpiRowValue(op: OperatorJamforelseKpi, kpi: string): string {
    switch (kpi) {
      case 'totalt_ibc':
        return op.totalt_ibc.toLocaleString('sv-SE');
      case 'ibc_per_h':
        return op.ibc_per_h.toFixed(2);
      case 'kvalitetsgrad':
        return op.kvalitetsgrad !== null ? op.kvalitetsgrad.toFixed(1) + ' %' : '—';
      case 'antal_stopp':
        return op.antal_stopp.toString();
      case 'total_stopptid_min':
        return this.formatMinuter(op.total_stopptid_min);
      case 'aktiva_timmar':
        return op.aktiva_timmar.toFixed(1) + ' h';
      default:
        return '—';
    }
  }

  formatMinuter(min: number): string {
    if (min <= 0) return '0 min';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}min` : `${m} min`;
  }

  bestOperatorFor(kpi: string): number {
    if (!this.compareData.length) return -1;
    let best = -1;
    let bestVal = -Infinity;
    for (let i = 0; i < this.compareData.length; i++) {
      const op  = this.compareData[i];
      let val: number;
      switch (kpi) {
        case 'totalt_ibc':        val = op.totalt_ibc;          break;
        case 'ibc_per_h':         val = op.ibc_per_h;           break;
        case 'kvalitetsgrad':     val = op.kvalitetsgrad ?? -1;  break;
        case 'antal_stopp':       val = -op.antal_stopp;         break; // lägre = bättre
        case 'total_stopptid_min':val = -op.total_stopptid_min;  break; // lägre = bättre
        case 'aktiva_timmar':     val = op.aktiva_timmar;        break;
        default:                  val = -Infinity;
      }
      if (val > bestVal) { bestVal = val; best = i; }
    }
    return best;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
