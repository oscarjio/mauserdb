import { Component, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  ProduktTypEffektivitetService,
  ProduktTypSummaryItem,
  ProduktTypComparisonItem,
} from '../../services/produkttyp-effektivitet.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-statistik-produkttyp-effektivitet',
  templateUrl: './statistik-produkttyp-effektivitet.html',
  styleUrls: ['./statistik-produkttyp-effektivitet.css'],
  imports: [CommonModule, FormsModule],
})
export class StatistikProduktTypEffektivitetComponent implements OnInit, OnDestroy {
  // Periodselektor
  days = 30;
  readonly dayOptions = [7, 14, 30, 90];

  // Laddningsstatus
  loadingSummary = false;
  loadingTrend = false;
  loadingComparison = false;

  // Felstatus
  errorSummary = false;
  errorTrend = false;

  // Data
  produkter: ProduktTypSummaryItem[] = [];
  kvalitetRanking: ProduktTypSummaryItem[] = [];
  trendHarData = false;

  // Comparison
  compA = 0;
  compB = 0;
  comparison: {
    a: ProduktTypComparisonItem;
    b: ProduktTypComparisonItem;
    diff_pct: {
      snitt_cykeltid_sek: number | null;
      kvalitet_pct: number | null;
      ibc_per_timme: number | null;
      snitt_bonus: number | null;
    };
  } | null = null;

  // Chart refs
  @ViewChild('cykeltidChart') cykeltidChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('ibcPerTimmeChart') ibcPerTimmeChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('ibcTrendChart') ibcTrendChartRef!: ElementRef<HTMLCanvasElement>;

  private cykeltidChart: Chart | null = null;
  private ibcPerTimmeChart: Chart | null = null;
  private ibcTrendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  // Palette
  private readonly palette = [
    'rgba(99,179,237,0.85)',
    'rgba(104,211,145,0.85)',
    'rgba(246,173,85,0.85)',
    'rgba(183,148,246,0.85)',
    'rgba(252,129,129,0.85)',
    'rgba(129,230,217,0.85)',
    'rgba(237,211,104,0.85)',
    'rgba(237,122,122,0.75)',
  ];

  constructor(private service: ProduktTypEffektivitetService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.cykeltidChart?.destroy(); } catch (_) {}
    try { this.ibcPerTimmeChart?.destroy(); } catch (_) {}
    try { this.ibcTrendChart?.destroy(); } catch (_) {}
    this.cykeltidChart = null;
    this.ibcPerTimmeChart = null;
    this.ibcTrendChart = null;
  }

  onDaysChange(d: number): void {
    this.days = d;
    this.loadAll();
  }

  loadAll(): void {
    this.loadSummary();
    this.loadTrend();
    this.comparison = null;
  }

  // ---------------------------------------------------------------
  // Summary
  // ---------------------------------------------------------------

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary = false;
    this.service.getSummary(this.days)
      .pipe(
        catchError(() => { this.errorSummary = true; return of(null); }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success && res.data) {
          this.produkter = res.data.produkter;
          this.kvalitetRanking = [...this.produkter]
            .filter(p => p.kvalitet_pct !== null)
            .sort((a, b) => (b.kvalitet_pct ?? 0) - (a.kvalitet_pct ?? 0));

          // Init comparison selectors
          if (this.produkter.length >= 2 && this.compA === 0) {
            this.compA = this.produkter[0].produkt_id;
            this.compB = this.produkter[1].produkt_id;
            this.loadComparison();
          }

          // Build IBC/timme chart after view updates
          setTimeout(() => { if (!this.destroy$.closed) this.buildIbcPerTimmeChart(); }, 50);
        }
      });
  }

  // ---------------------------------------------------------------
  // Trend
  // ---------------------------------------------------------------

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.trendHarData = false;
    this.service.getTrend(this.days)
      .pipe(
        catchError(() => { this.errorTrend = true; return of(null); }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success && res.data?.har_data) {
          this.trendHarData = true;
          setTimeout(() => {
            if (this.destroy$.closed) return;
            this.buildCykeltidChart(res.data.labels, res.data.datasets_cykeltid);
            this.buildIbcTrendChart(res.data.labels, res.data.datasets_ibc);
          }, 50);
        }
      });
  }

  // ---------------------------------------------------------------
  // Comparison
  // ---------------------------------------------------------------

  loadComparison(): void {
    if (this.compA <= 0 || this.compB <= 0 || this.compA === this.compB) {
      this.comparison = null;
      return;
    }
    this.loadingComparison = true;
    this.service.getComparison(this.compA, this.compB, this.days)
      .pipe(
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loadingComparison = false;
        if (res?.success && res.data) {
          this.comparison = {
            a: res.data.a,
            b: res.data.b,
            diff_pct: res.data.diff_pct,
          };
        }
      });
  }

  // ---------------------------------------------------------------
  // Chart builders
  // ---------------------------------------------------------------

  private buildCykeltidChart(labels: string[], datasets: any[]): void {
    try { this.cykeltidChart?.destroy(); } catch (_) {}
    const el = this.cykeltidChartRef?.nativeElement;
    if (!el) return;
    this.cykeltidChart = new Chart(el, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Sekunder', color: '#a0aec0' },
          },
        },
      },
    });
  }

  private buildIbcPerTimmeChart(): void {
    try { this.ibcPerTimmeChart?.destroy(); } catch (_) {}
    const el = this.ibcPerTimmeChartRef?.nativeElement;
    if (!el || this.produkter.length === 0) return;

    const sorted = [...this.produkter]
      .filter(p => p.ibc_per_timme !== null)
      .sort((a, b) => (a.ibc_per_timme ?? 0) - (b.ibc_per_timme ?? 0));

    this.ibcPerTimmeChart = new Chart(el, {
      type: 'bar',
      data: {
        labels: sorted.map(p => p.produkt_namn),
        datasets: [{
          label: 'IBC/timme',
          data: sorted.map(p => p.ibc_per_timme ?? 0),
          backgroundColor: sorted.map((_, i) => this.palette[i % this.palette.length]),
          borderColor: sorted.map((_, i) => this.palette[i % this.palette.length].replace('0.85', '1').replace('0.75', '1')),
          borderWidth: 1,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'IBC/timme', color: '#a0aec0' },
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  private buildIbcTrendChart(labels: string[], datasets: any[]): void {
    try { this.ibcTrendChart?.destroy(); } catch (_) {}
    const el = this.ibcTrendChartRef?.nativeElement;
    if (!el) return;
    this.ibcTrendChart = new Chart(el, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal IBC', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ---------------------------------------------------------------
  // Hjälpmetoder — färger
  // ---------------------------------------------------------------

  kvalitetColor(pct: number | null): string {
    if (pct === null) return '#a0aec0';
    if (pct >= 98) return '#68d391';
    if (pct >= 95) return '#ecc94b';
    return '#fc8181';
  }

  ibcPerTimmeColor(val: number | null): string {
    if (val === null) return '#a0aec0';
    if (val >= 15) return '#68d391';
    if (val >= 10) return '#ecc94b';
    return '#fc8181';
  }

  kvalitetBarColor(pct: number | null): string {
    if (pct === null) return '#4a5568';
    if (pct >= 98) return 'rgba(104,211,145,0.85)';
    if (pct >= 95) return 'rgba(236,201,75,0.85)';
    return 'rgba(252,129,129,0.85)';
  }
  trackByIndex(index: number): number { return index; }
}
