import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  MaskinOeeService,
  OeeOverviewData,
  OeePerMaskinData,
  OeeTrendData,
  OeeBenchmarkData,
  OeeDetaljData,
  OeeDetaljItem,
  Maskin,
} from '../../../services/maskin-oee.service';

Chart.register(...registerables);

const MASKIN_COLORS = [
  '#63b3ed', '#fc8181', '#68d391', '#f6ad55', '#9f7aea',
  '#4fd1c5', '#fbd38d', '#b794f4', '#f687b3', '#a0aec0',
];

type PeriodKey = 'dag' | 'vecka' | 'manad';

@Component({
  standalone: true,
  selector: 'app-maskin-oee',
  templateUrl: './maskin-oee.component.html',
  styleUrls: ['./maskin-oee.component.css'],
  imports: [CommonModule, FormsModule],
})
export class MaskinOeePage implements OnInit, OnDestroy {

  // Period-filter
  period: PeriodKey = 'manad';
  readonly periodOptions: { key: PeriodKey; label: string }[] = [
    { key: 'dag',   label: 'Idag' },
    { key: 'vecka', label: 'Vecka' },
    { key: 'manad', label: 'Månad (30d)' },
  ];

  // Maskin-filter
  maskinFilter = 0;
  allaMaskiner: Maskin[] = [];

  // OEE-mal
  oeeMal = 85;

  // Loading
  loadingOverview   = false;
  loadingPerMaskin  = false;
  loadingTrend      = false;
  loadingBenchmark  = false;
  loadingDetalj     = false;

  // Error
  errorOverview = false;
  errorMaskiner = false;

  // Data
  overview:       OeeOverviewData | null   = null;
  perMaskinData:  OeePerMaskinData | null  = null;
  trendData:      OeeTrendData | null      = null;
  benchmarkData:  OeeBenchmarkData | null  = null;
  detaljData:     OeeDetaljData | null     = null;

  // Trend checkboxar
  selectedTrendMaskiner = new Set<number>();

  // Sortering
  sortColumn: keyof OeeDetaljItem = 'datum';
  sortAsc = false;

  // Cached sorted list
  cachedSortedDetaljer: OeeDetaljItem[] = [];

  // Charts
  private barChart:   Chart | null = null;
  private trendChart: Chart | null = null;

  // Timers
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;
  private barChartTimer: ReturnType<typeof setTimeout> | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private svc: MaskinOeeService) {}

  ngOnInit(): void {
    this.errorMaskiner = false;
    this.svc.getMaskiner().pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        if (res?.success) {
          this.allaMaskiner = res.data.maskiner;
        } else {
          this.errorMaskiner = true;
        }
      },
      error: () => { this.errorMaskiner = true; }
    });
    this.loadAll();
    // OEE-analys: uppdatera var 2:a minut
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    if (this.barChart) { this.barChart.destroy(); this.barChart = null as any; }
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null as any; }
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    if (this.trendChartTimer !== null) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
    if (this.barChartTimer !== null) { clearTimeout(this.barChartTimer); this.barChartTimer = null; }
  }

  // ---- Period & filter ----

  setPeriod(p: PeriodKey): void {
    this.period = p;
    this.loadAll();
  }

  onMaskinFilterChange(): void {
    this.loadTrend();
    this.loadDetalj();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadOverview();
    this.loadPerMaskin();
    this.loadTrend();
    this.loadBenchmark();
    this.loadDetalj();
  }

  // ---- Data-loaders ----

  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview   = false;
    this.svc.getOverview(this.period).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) {
          this.overview = res.data;
          this.oeeMal = res.data.oee_mal;
        } else {
          this.errorOverview = true;
        }
      },
      error: () => { this.loadingOverview = false; this.isFetching = false; this.errorOverview = true; }
    });
  }

  private loadPerMaskin(): void {
    this.loadingPerMaskin = true;
    this.svc.getPerMaskin(this.period).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingPerMaskin = false;
        if (res?.success) {
          this.perMaskinData = res.data;
        }
      },
      error: () => { this.loadingPerMaskin = false; }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.svc.getTrend(this.period, this.maskinFilter).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          this.oeeMal = res.data.oee_mal;
          // Valj top-3 maskiner som standard
          this.selectedTrendMaskiner.clear();
          const top = res.data.series
            .map(s => ({ id: s.maskin_id, avg: s.values.filter(v => v !== null).reduce((a, b) => a + (b || 0), 0) / Math.max(s.values.filter(v => v !== null).length, 1) }))
            .sort((a, b) => b.avg - a.avg)
            .slice(0, 3);
          top.forEach(t => this.selectedTrendMaskiner.add(t.id));
          if (this.trendChartTimer !== null) { clearTimeout(this.trendChartTimer); }
          this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 80);
        }
      },
      error: () => { this.loadingTrend = false; }
    });
  }

  private loadBenchmark(): void {
    this.loadingBenchmark = true;
    this.svc.getBenchmark(this.period).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingBenchmark = false;
        if (res?.success) {
          this.benchmarkData = res.data;
          if (this.barChartTimer !== null) { clearTimeout(this.barChartTimer); }
          this.barChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildBarChart(); }, 80);
        }
      },
      error: () => { this.loadingBenchmark = false; }
    });
  }

  private loadDetalj(): void {
    this.loadingDetalj = true;
    this.svc.getDetalj(this.period, this.maskinFilter).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingDetalj = false;
        if (res?.success) {
          this.detaljData = res.data;
          this.rebuildSortedDetaljer();
        }
      },
      error: () => { this.loadingDetalj = false; }
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

  sortBy(col: keyof OeeDetaljItem): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = false;
    }
    this.rebuildSortedDetaljer();
  }

  private rebuildSortedDetaljer(): void {
    if (!this.detaljData?.detaljer) { this.cachedSortedDetaljer = []; return; }
    const arr = [...this.detaljData.detaljer];
    arr.sort((a, b) => {
      const va = a[this.sortColumn];
      const vb = b[this.sortColumn];
      if (va === null || va === undefined) return 1;
      if (vb === null || vb === undefined) return -1;
      if (va < vb) return this.sortAsc ? -1 : 1;
      if (va > vb) return this.sortAsc ? 1 : -1;
      return 0;
    });
    this.cachedSortedDetaljer = arr;
  }

  // ---- Charts ----

  private destroyCharts(): void {
    try { this.barChart?.destroy();   } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    this.barChart   = null;
    this.trendChart = null;
  }

  private buildBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;

    if (!this.benchmarkData?.benchmark?.length) return;
    const canvas = document.getElementById('oeeBarChart') as HTMLCanvasElement;
    if (!canvas) return;

    const items  = this.benchmarkData.benchmark;
    const labels = items.map(m => m.maskin_namn);
    const tData  = items.map(m => m.avg_t);
    const pData  = items.map(m => m.avg_p);
    const kData  = items.map(m => m.avg_k);
    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Tillgänglighet',
            data: tData,
            backgroundColor: '#63b3ed',
            borderRadius: 2,
          },
          {
            label: 'Prestanda',
            data: pData,
            backgroundColor: '#68d391',
            borderRadius: 2,
          },
          {
            label: 'Kvalitet',
            data: kData,
            backgroundColor: '#f6ad55',
            borderRadius: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 11 } },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              afterBody: (ctx: any) => {
                const idx = ctx[0]?.dataIndex;
                if (idx !== undefined && items[idx]) {
                  return [`OEE: ${items[idx].avg_oee}%`, `Mal: ${items[idx].oee_mal}%`];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#e2e8f0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            max: 100,
            ticks: { color: '#a0aec0', callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    if (!this.trendData?.dates?.length) return;
    const canvas = document.getElementById('oeeTrendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const dates = this.trendData.dates;
    const datasets: any[] = this.trendData.series
      .filter(s => this.selectedTrendMaskiner.has(s.maskin_id))
      .map((s, i) => ({
        label: s.maskin_namn,
        data: s.values,
        borderColor: MASKIN_COLORS[i % MASKIN_COLORS.length],
        backgroundColor: 'transparent',
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 5,
        tension: 0.3,
        spanGaps: true,
      }));

    // Mål-linje
    const malValue = this.trendData.oee_mal || this.oeeMal;
    datasets.push({
      label: `Mål (${malValue}%)`,
      data: dates.map(() => malValue),
      borderColor: '#9f7aea',
      backgroundColor: 'transparent',
      borderWidth: 2,
      borderDash: [8, 4],
      pointRadius: 0,
      pointHoverRadius: 0,
    });

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels: dates, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 11 } },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx: any) => ` ${ctx.dataset.label}: ${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(1) + '%' : '-'}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'OEE (%)', color: '#a0aec0' },
            ticks: { color: '#a0aec0', callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            min: 0,
            max: 100,
          },
        },
      },
    });
  }

  // ---- Helpers ----

  getMaskinColor(index: number): string {
    return MASKIN_COLORS[index % MASKIN_COLORS.length];
  }

  getOeeColor(oee: number): string {
    if (oee >= 85) return '#68d391';
    if (oee >= 65) return '#f6ad55';
    return '#fc8181';
  }

  getTrendIcon(): string {
    if (!this.overview) return 'fas fa-minus text-muted';
    switch (this.overview.trend_direction) {
      case 'up':   return 'fas fa-arrow-up text-success';
      case 'down': return 'fas fa-arrow-down text-danger';
      default:     return 'fas fa-minus text-muted';
    }
  }

  getTrendColor(): string {
    if (!this.overview) return '#a0aec0';
    switch (this.overview.trend_direction) {
      case 'up':   return '#68d391';
      case 'down': return '#fc8181';
      default:     return '#a0aec0';
    }
  }

  getTrendText(): string {
    if (!this.overview) return '';
    switch (this.overview.trend_direction) {
      case 'up':   return 'Förbättras';
      case 'down': return 'Försämras';
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

  getSortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted';
    return this.sortAsc ? 'fas fa-sort-up text-info' : 'fas fa-sort-down text-info';
  }

  getPeriodLabel(): string {
    const opt = this.periodOptions.find(p => p.key === this.period);
    return opt?.label ?? '';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
  trackByMaskinId(index: number, item: any): any { return item?.maskin_id ?? item?.id ?? index; }
}
