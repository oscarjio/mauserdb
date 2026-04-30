import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import Chart from 'chart.js/auto';

interface ProductSummary {
  id: number;
  name: string;
  antal_skift: number;
  ibc_per_h: number;
}

interface TrendPoint {
  yr: number;
  mo: number;
  antal: number;
  ibc_per_h: number;
  kassgrad: number;
}

interface TopOp {
  number: number;
  name: string;
  antal_skift: number;
}

interface ProductDetail {
  id: number;
  name: string;
  ibc_per_h: number;
  kassgrad: number;
  stoppgrad: number;
  antal_skift: number;
  total_ibc: number;
  total_timmar: number;
  cycle_time: number;
  expected_ibch: number | null;
  effektivitet: number | null;
  trend: TrendPoint[];
  top_ops: TopOp[];
}

interface ApiResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  products: ProductSummary[];
  comparison: { a: ProductDetail; b: ProductDetail } | null;
}

@Component({
  standalone: true,
  selector: 'app-produkt-jamforelse',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produkt-jamforelse.html',
  styleUrl: './produkt-jamforelse.css',
})
export class ProduktJamforelsePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private trendChart: Chart | null = null;

  @ViewChild('trendCanvas') trendCanvas!: ElementRef<HTMLCanvasElement>;

  Math = Math;

  days = 90;
  loading = false;
  error = '';

  products: ProductSummary[] = [];
  prodA = 0;
  prodB = 0;

  comparison: { a: ProductDetail; b: ProductDetail } | null = null;
  from = '';
  to = '';

  readonly daysOptions = [
    { value: 30, label: '30 dagar' },
    { value: 60, label: '60 dagar' },
    { value: 90, label: '90 dagar' },
    { value: 180, label: '6 mån' },
    { value: 365, label: '1 år' },
  ];

  ngOnInit(): void {
    this.loadProducts();
  }

  ngOnDestroy(): void {
    this.trendChart?.destroy();
    this.destroy$.next();
    this.destroy$.complete();
  }

  private apiUrl(extra = ''): string {
    return `${environment.apiUrl}?action=rebotling&run=produkt-jamforelse&days=${this.days}${extra}`;
  }

  loadProducts(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.comparison = null;

    this.http.get<ApiResponse>(this.apiUrl(), { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta produktlista.';
        return;
      }
      this.products = res.products;
      if (this.products.length >= 2 && !this.prodA && !this.prodB) {
        this.prodA = this.products[0].id;
        this.prodB = this.products[1].id;
      }
    });
  }

  compare(): void {
    if (!this.prodA || !this.prodB || this.prodA === this.prodB) return;
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.comparison = null;

    const url = this.apiUrl(`&prodA=${this.prodA}&prodB=${this.prodB}`);
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success || !res.comparison) {
        this.error = 'Kunde inte jämföra produkterna.';
        return;
      }
      this.products  = res.products;
      this.from      = res.from;
      this.to        = res.to;
      this.comparison = res.comparison;
      setTimeout(() => this.drawTrendChart(), 50);
    });
  }

  setDays(d: number): void {
    this.days = d;
    if (this.comparison) {
      this.compare();
    } else {
      this.loadProducts();
    }
  }

  winner(metricA: number, metricB: number, lowerIsBetter = false): 'a' | 'b' | 'tie' {
    const diff = lowerIsBetter ? metricB - metricA : metricA - metricB;
    if (Math.abs(diff) < 0.001) return 'tie';
    return diff > 0 ? 'a' : 'b';
  }

  deltaSign(a: number, b: number): string {
    const d = a - b;
    if (Math.abs(d) < 0.01) return '—';
    return (d > 0 ? '+' : '') + d.toFixed(1);
  }

  pctDelta(a: number, b: number): string {
    if (!b) return '—';
    const p = ((a - b) / b) * 100;
    return (p >= 0 ? '+' : '') + p.toFixed(1) + '%';
  }

  monthLabel(yr: number, mo: number): string {
    const names = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    return `${names[mo - 1]} ${yr}`;
  }

  private drawTrendChart(): void {
    if (!this.trendCanvas || !this.comparison) return;

    this.trendChart?.destroy();

    const a = this.comparison.a;
    const b = this.comparison.b;

    // Merge all month keys
    const keySet = new Set<string>();
    [...a.trend, ...b.trend].forEach(t => keySet.add(`${t.yr}-${String(t.mo).padStart(2,'0')}`));
    const keys = [...keySet].sort();

    const mapA = new Map(a.trend.map(t => [`${t.yr}-${String(t.mo).padStart(2,'0')}`, t.ibc_per_h]));
    const mapB = new Map(b.trend.map(t => [`${t.yr}-${String(t.mo).padStart(2,'0')}`, t.ibc_per_h]));

    const labels = keys.map(k => {
      const [yr, mo] = k.split('-');
      return this.monthLabel(parseInt(yr), parseInt(mo));
    });

    this.trendChart = new Chart(this.trendCanvas.nativeElement, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: a.name,
            data: keys.map(k => mapA.get(k) ?? null),
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.1)',
            tension: 0.3,
            spanGaps: true,
            pointRadius: 4,
            borderWidth: 2,
          },
          {
            label: b.name,
            data: keys.map(k => mapB.get(k) ?? null),
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246,173,85,0.1)',
            tension: 0.3,
            spanGaps: true,
            pointRadius: 4,
            borderWidth: 2,
            borderDash: [5, 3],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${(ctx.raw as number)?.toFixed(1)} IBC/h`,
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'IBC/h', color: '#a0aec0' },
          },
        },
      },
    });
  }

  constructor(private http: HttpClient) {}
}
