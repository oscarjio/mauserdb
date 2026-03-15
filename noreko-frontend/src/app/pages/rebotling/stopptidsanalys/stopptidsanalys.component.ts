import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  StopptidsanalysService,
  OverviewData,
  PerMaskinData,
  TrendData,
  FordelningData,
  DetaljData,
  StoppEvent,
  Maskin,
} from '../../../services/stopptidsanalys.service';

Chart.register(...registerables);

const MASKIN_COLORS = [
  '#63b3ed', '#fc8181', '#68d391', '#f6ad55', '#9f7aea',
  '#4fd1c5', '#fbd38d', '#b794f4', '#fc8181', '#a0aec0',
];

type PeriodKey = 'dag' | 'vecka' | 'manad';

@Component({
  standalone: true,
  selector: 'app-stopptidsanalys',
  templateUrl: './stopptidsanalys.component.html',
  styleUrls: ['./stopptidsanalys.component.css'],
  imports: [CommonModule, FormsModule],
})
export class StopptidsanalysPage implements OnInit, OnDestroy {

  // Period-filter
  period: PeriodKey = 'manad';
  readonly periodOptions: { key: PeriodKey; label: string }[] = [
    { key: 'dag',   label: 'Idag' },
    { key: 'vecka', label: 'Vecka' },
    { key: 'manad', label: 'Manad (30d)' },
  ];

  // Maskin-filter (för tabell & trend)
  maskinFilter = 0;
  allaMaskiner: Maskin[] = [];

  // Loading
  loadingOverview    = false;
  loadingPerMaskin   = false;
  loadingTrend       = false;
  loadingFordelning  = false;
  loadingDetalj      = false;

  // Error
  errorOverview = false;

  // Data
  overview:      OverviewData | null    = null;
  perMaskinData: PerMaskinData | null   = null;
  trendData:     TrendData | null       = null;
  fordelningData: FordelningData | null = null;
  detaljData:    DetaljData | null      = null;

  // Trend-checkboxar: valda maskiner att visa i linjediagrammet
  selectedTrendMaskiner = new Set<number>();

  // Sortering i detajtabell
  sortColumn: keyof StoppEvent = 'startad_at';
  sortAsc = false;

  // Charts
  private barChart:       Chart | null = null;
  private trendChart:     Chart | null = null;
  private doughnutChart:  Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: StopptidsanalysService) {}

  ngOnInit(): void {
    this.svc.getMaskiner().pipe(takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.allaMaskiner = res.data.maskiner;
      }
    });
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  // ---- Period & filter ----

  setPeriod(p: PeriodKey): void {
    this.period = p;
    this.loadAll();
  }

  onMaskinFilterChange(): void {
    this.loadTrend();
    this.loadDetaljtabell();
  }

  loadAll(): void {
    this.loadOverview();
    this.loadPerMaskin();
    this.loadTrend();
    this.loadFordelning();
    this.loadDetaljtabell();
  }

  // ---- Data-loaders ----

  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview   = false;
    this.svc.getOverview(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overview = res.data;
      } else {
        this.errorOverview = true;
      }
    });
  }

  private loadPerMaskin(): void {
    this.loadingPerMaskin = true;
    this.svc.getPerMaskin(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPerMaskin = false;
      if (res?.success) {
        this.perMaskinData = res.data;
        setTimeout(() => this.buildBarChart(), 80);
      }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.svc.getTrend(this.period, this.maskinFilter).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        // Välj top-3 maskiner som standard
        this.selectedTrendMaskiner.clear();
        const top = res.data.series
          .map(s => ({ id: s.maskin_id, sum: s.values.reduce((a, b) => a + b, 0) }))
          .sort((a, b) => b.sum - a.sum)
          .slice(0, 3);
        top.forEach(t => this.selectedTrendMaskiner.add(t.id));
        setTimeout(() => this.buildTrendChart(), 80);
      }
    });
  }

  private loadFordelning(): void {
    this.loadingFordelning = true;
    this.svc.getFordelning(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingFordelning = false;
      if (res?.success) {
        this.fordelningData = res.data;
        setTimeout(() => this.buildDoughnutChart(), 80);
      }
    });
  }

  private loadDetaljtabell(): void {
    this.loadingDetalj = true;
    this.svc.getDetaljtabell(this.period, this.maskinFilter).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingDetalj = false;
      if (res?.success) {
        this.detaljData = res.data;
      }
    });
  }

  // ---- Trend checkbox ----

  toggleTrendMaskin(id: number): void {
    if (this.selectedTrendMaskiner.has(id)) {
      this.selectedTrendMaskiner.delete(id);
    } else {
      this.selectedTrendMaskiner.add(id);
    }
    this.buildTrendChart();
  }

  isTrendMaskinSelected(id: number): boolean {
    return this.selectedTrendMaskiner.has(id);
  }

  // ---- Tabellsortering ----

  sortBy(col: keyof StoppEvent): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = false;
    }
  }

  get sortedStopp(): StoppEvent[] {
    if (!this.detaljData?.stopp) return [];
    const arr = [...this.detaljData.stopp];
    arr.sort((a, b) => {
      const va = a[this.sortColumn];
      const vb = b[this.sortColumn];
      if (va === null || va === undefined) return 1;
      if (vb === null || vb === undefined) return -1;
      if (va < vb) return this.sortAsc ? -1 : 1;
      if (va > vb) return this.sortAsc ? 1 : -1;
      return 0;
    });
    return arr;
  }

  // ---- Charts ----

  private destroyCharts(): void {
    try { this.barChart?.destroy();      } catch (_) {}
    try { this.trendChart?.destroy();    } catch (_) {}
    try { this.doughnutChart?.destroy(); } catch (_) {}
    this.barChart      = null;
    this.trendChart    = null;
    this.doughnutChart = null;
  }

  private buildBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;

    if (!this.perMaskinData?.maskiner?.length) return;
    const canvas = document.getElementById('barChart') as HTMLCanvasElement;
    if (!canvas) return;

    const items  = [...this.perMaskinData.maskiner].sort((a, b) => b.total_min - a.total_min);
    const labels = items.map(m => m.maskin_namn);
    const values = items.map(m => m.total_min);
    const colors = items.map((_, i) => MASKIN_COLORS[i % MASKIN_COLORS.length]);

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Stopptid (min)',
          data: values,
          backgroundColor: colors,
          borderColor: colors,
          borderWidth: 1,
          borderRadius: 4,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const min = ctx.parsed.x;
                const item = items[ctx.dataIndex];
                return [
                  ` ${min.toFixed(1)} min`,
                  ` ${item.antal_stopp} stopp`,
                  ` Snitt: ${item.snitt_min.toFixed(1)} min/stopp`,
                ];
              },
            },
          },
        },
        scales: {
          x: {
            title: { display: true, text: 'Stopptid (minuter)', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 13 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    if (!this.trendData?.dates?.length) return;
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const dates = this.trendData.dates;
    const datasets = this.trendData.series
      .filter(s => this.selectedTrendMaskiner.has(s.maskin_id))
      .map((s, i) => ({
        label: s.maskin_namn,
        data: s.values,
        borderColor: MASKIN_COLORS[i % MASKIN_COLORS.length],
        backgroundColor: 'transparent',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6,
        tension: 0.3,
      }));

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels: dates, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => ` ${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)} min`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'Stopptid (min)', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  private buildDoughnutChart(): void {
    try { this.doughnutChart?.destroy(); } catch (_) {}
    this.doughnutChart = null;

    if (!this.fordelningData?.fordelning?.length) return;
    const canvas = document.getElementById('doughnutChart') as HTMLCanvasElement;
    if (!canvas) return;

    const items  = this.fordelningData.fordelning;
    const labels = items.map(m => m.maskin_namn);
    const values = items.map(m => m.total_min);
    const colors = items.map((_, i) => MASKIN_COLORS[i % MASKIN_COLORS.length]);

    this.doughnutChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: colors,
          borderColor: '#2d3748',
          borderWidth: 2,
          hoverOffset: 8,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#e2e8f0', font: { size: 12 }, padding: 16 },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const item = items[ctx.dataIndex];
                return [
                  ` ${item.total_min.toFixed(1)} min`,
                  ` ${item.andel_pct.toFixed(1)}%`,
                  ` ${item.antal_stopp} stopp`,
                ];
              },
            },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  getMaskinColor(index: number): string {
    return MASKIN_COLORS[index % MASKIN_COLORS.length];
  }

  getTrendIcon(): string {
    if (!this.overview) return '';
    switch (this.overview.trend_direction) {
      case 'down': return 'fas fa-arrow-down text-success';
      case 'up':   return 'fas fa-arrow-up text-danger';
      default:     return 'fas fa-minus text-muted';
    }
  }

  getTrendText(): string {
    if (!this.overview) return '';
    switch (this.overview.trend_direction) {
      case 'down': return 'Forbattras';
      case 'up':   return 'Forsamras';
      default:     return 'Stabil';
    }
  }

  formatMin(min: number): string {
    if (min >= 60) {
      const h = Math.floor(min / 60);
      const m = Math.round(min % 60);
      return `${h}h ${m}min`;
    }
    return `${min.toFixed(1)} min`;
  }

  getKategoriKlass(kat: string): string {
    switch (kat) {
      case 'maskin':   return 'badge bg-danger';
      case 'material': return 'badge bg-warning text-dark';
      case 'operatör': return 'badge bg-info text-dark';
      case 'planerat': return 'badge bg-secondary';
      default:         return 'badge bg-dark';
    }
  }

  getSortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted';
    return this.sortAsc ? 'fas fa-sort-up text-info' : 'fas fa-sort-down text-info';
  }

  getPeriodLabel(): string {
    const opt = this.periodOptions.find(p => p.key === this.period);
    return opt?.label ?? '';
  }

  getTotalAntalStopp(): number {
    if (!this.perMaskinData?.maskiner) return 0;
    return this.perMaskinData.maskiner.reduce((sum, m) => sum + m.antal_stopp, 0);
  }
  trackByIndex(index: number): number { return index; }
}
