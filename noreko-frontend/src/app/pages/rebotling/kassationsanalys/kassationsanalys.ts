import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KassationsanalysService,
  SammanfattningData,
  OrsakRad,
  OrsakerTrendData,
  StationRad,
  OperatorRad,
  DetaljIbc,
} from '../../../services/kassationsanalys.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kassationsanalys',
  templateUrl: './kassationsanalys.html',
  styleUrls: ['./kassationsanalys.css'],
  imports: [CommonModule, FormsModule],
})
export class KassationsanalysPage implements OnInit, OnDestroy {

  // -- Period --
  days = 30;
  readonly dayOptions = [
    { value: 7,  label: '7d' },
    { value: 14, label: '14d' },
    { value: 30, label: '30d' },
    { value: 90, label: '90d' },
  ];
  trendGroup: 'day' | 'week' = 'day';

  // -- Laddning --
  loadingSammanfattning = false;
  loadingOrsaker = false;
  loadingTrend = false;
  loadingStationer = false;
  loadingOperatorer = false;
  loadingDetaljer = false;

  // -- Fel --
  errorSammanfattning = false;
  errorOrsaker = false;
  errorTrend = false;
  errorStationer = false;
  errorOperatorer = false;
  errorDetaljer = false;

  // -- Data --
  sammanfattning: SammanfattningData | null = null;
  orsaker: OrsakRad[] = [];
  orsakerTotal = 0;
  trendData: OrsakerTrendData | null = null;
  stationer: StationRad[] = [];
  operatorer: OperatorRad[] = [];
  detaljer: DetaljIbc[] = [];
  detaljerTotal = 0;

  // -- Detalj-expandering --
  expandedRows: Set<number> = new Set();

  // -- Charts --
  private paretoChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private svc: KassationsanalysService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    this.paretoChart = null;
    this.trendChart = null;
  }

  // =================================================================
  // Period
  // =================================================================

  onDaysChange(d: number): void {
    if (this.days === d) return;
    this.days = d;
    this.trendGroup = d <= 14 ? 'day' : (d <= 30 ? 'day' : 'week');
    this.loadAll();
  }

  onTrendGroupChange(g: 'day' | 'week'): void {
    this.trendGroup = g;
    this.loadTrend();
  }

  // =================================================================
  // Data loading
  // =================================================================

  loadAll(): void {
    this.loadSammanfattning();
    this.loadOrsaker();
    this.loadTrend();
    this.loadStationer();
    this.loadOperatorer();
    this.loadDetaljer();
  }

  loadSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning = false;
    this.svc.getSammanfattning()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSammanfattning = false;
        if (res?.success) {
          this.sammanfattning = res.data;
        } else {
          this.errorSammanfattning = true;
        }
      });
  }

  loadOrsaker(): void {
    this.loadingOrsaker = true;
    this.errorOrsaker = false;
    this.svc.getOrsaker(this.days)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOrsaker = false;
        if (res?.success) {
          this.orsaker = res.data.orsaker ?? [];
          this.orsakerTotal = res.data.total ?? 0;
          setTimeout(() => { if (!this.destroy$.closed) this.buildParetoChart(); }, 0);
        } else {
          this.errorOrsaker = true;
        }
      });
  }

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getOrsakerTrend(this.days, this.trendGroup)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 0);
        } else {
          this.errorTrend = true;
        }
      });
  }

  loadStationer(): void {
    this.loadingStationer = true;
    this.errorStationer = false;
    this.svc.getPerStation(this.days)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStationer = false;
        if (res?.success) {
          this.stationer = res.data.stationer ?? [];
        } else {
          this.errorStationer = true;
        }
      });
  }

  loadOperatorer(): void {
    this.loadingOperatorer = true;
    this.errorOperatorer = false;
    this.svc.getPerOperator(this.days)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOperatorer = false;
        if (res?.success) {
          this.operatorer = res.data.operatorer ?? [];
        } else {
          this.errorOperatorer = true;
        }
      });
  }

  loadDetaljer(): void {
    this.loadingDetaljer = true;
    this.errorDetaljer = false;
    this.expandedRows.clear();
    this.svc.getDetaljer(this.days, 200)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDetaljer = false;
        if (res?.success) {
          this.detaljer = res.data.ibc ?? [];
          this.detaljerTotal = res.data.total ?? 0;
        } else {
          this.errorDetaljer = true;
        }
      });
  }

  // =================================================================
  // Pareto-diagram (horisontella staplar + kumulativ linje)
  // =================================================================

  private buildParetoChart(): void {
    try { this.paretoChart?.destroy(); } catch (_) {}
    this.paretoChart = null;

    const canvas = document.getElementById('kasParetoChart') as HTMLCanvasElement;
    if (!canvas || this.orsaker.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const top = this.orsaker.filter(o => o.antal > 0).slice(0, 10);
    if (top.length === 0) return;

    const labels = top.map(o => o.namn);
    const values = top.map(o => o.antal);
    const cumPct = top.map(o => o.kumulativ_pct);

    if (this.paretoChart) { (this.paretoChart as any).destroy(); }
    this.paretoChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal kassationer',
            data: values,
            backgroundColor: values.map((_, i) => {
              const pct = cumPct[i] ?? 0;
              if (pct <= 80) return 'rgba(252,129,129,0.85)';
              return 'rgba(160,174,192,0.5)';
            }),
            borderColor: 'rgba(252,129,129,1)',
            borderWidth: 1,
            yAxisID: 'yAntal',
            order: 2,
          },
          {
            label: 'Kumulativ %',
            data: cumPct,
            type: 'line' as any,
            borderColor: '#63b3ed',
            backgroundColor: 'transparent',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#63b3ed',
            tension: 0.3,
            yAxisID: 'yPct',
            order: 1,
          },
        ],
      },
      options: {
        indexAxis: 'x',
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item: any) => {
                if (item.dataset.yAxisID === 'yPct') return ` Kumulativ: ${item.raw}%`;
                return ` Antal: ${item.raw} st`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          yAntal: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: { display: true, text: 'Antal', color: '#a0aec0', font: { size: 10 } },
          },
          yPct: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v: any) => v + '%' },
            grid: { display: false },
            title: { display: true, text: 'Kumulativ %', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Trendgraf (linjediagram per orsak)
  // =================================================================

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('kasTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData?.har_data) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { labels, datasets } = this.trendData;

    const chartDatasets = datasets.map(ds => ({
      label: ds.label,
      data: ds.data,
      borderColor: ds.borderColor,
      backgroundColor: 'transparent',
      borderWidth: 2,
      pointRadius: 2,
      pointHoverRadius: 4,
      tension: 0.3,
      fill: false,
    }));

    const shortLabels = labels.map(l => {
      if (l.length === 10) return l.slice(5);
      return l;
    });

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: { labels: shortLabels, datasets: chartDatasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        animation: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 10 }, stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: { display: true, text: 'Antal kassationer', color: '#a0aec0', font: { size: 10 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjalpmetoder
  // =================================================================

  get kpiAndel(): number {
    if (!this.sammanfattning) return 0;
    const p = this.sammanfattning.perioder[this.days] ?? this.sammanfattning.perioder[30];
    return p?.andel_pct ?? 0;
  }

  get kpiKasserade(): number {
    if (!this.sammanfattning) return 0;
    const p = this.sammanfattning.perioder[this.days] ?? this.sammanfattning.perioder[30];
    return p?.kasserade ?? 0;
  }

  get kpiTrendDiff(): number {
    if (!this.sammanfattning) return 0;
    const p = this.sammanfattning.perioder[this.days] ?? this.sammanfattning.perioder[30];
    return p?.diff_pct ?? 0;
  }

  get kpiTrend(): string {
    if (!this.sammanfattning) return 'stable';
    const p = this.sammanfattning.perioder[this.days] ?? this.sammanfattning.perioder[30];
    return p?.trend ?? 'stable';
  }

  get kpiVarstaStation(): string {
    return this.sammanfattning?.varsta_station?.station ?? '-';
  }

  get kpiVarstaStationPct(): number {
    return this.sammanfattning?.varsta_station?.andel_pct ?? 0;
  }

  trendIcon(trend: string): string {
    if (trend === 'up') return '\u25B2';
    if (trend === 'down') return '\u25BC';
    return '\u2014';
  }

  trendColor(trend: string, invertGood = false): string {
    if (trend === 'stable') return '#a0aec0';
    const up = invertGood ? '#fc8181' : '#68d391';
    const down = invertGood ? '#68d391' : '#fc8181';
    return trend === 'up' ? up : down;
  }

  rateColor(pct: number): string {
    if (pct > 10) return '#fc8181';
    if (pct > 5) return '#f6ad55';
    return '#68d391';
  }

  stationRowClass(pct: number): string {
    if (pct > 10) return 'kas-row-red';
    if (pct > 5) return 'kas-row-yellow';
    return 'kas-row-green';
  }

  formatDatum(d: string): string {
    if (!d) return '-';
    return d.replace('T', ' ').slice(0, 16);
  }

  toggleRow(id: number): void {
    if (this.expandedRows.has(id)) {
      this.expandedRows.delete(id);
    } else {
      this.expandedRows.add(id);
    }
  }

  isExpanded(id: number): boolean {
    return this.expandedRows.has(id);
  }

  trackByIndex(index: number, item: any): any {
    return item?.id ?? index;
  }

  trackById(index: number, item: any): number {
    return item.id ?? index;
  }
}
