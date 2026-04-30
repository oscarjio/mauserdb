import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import Chart from 'chart.js/auto';

interface ProductStopp {
  product_id: number;
  product_name: string;
  antal_skift: number;
  stoppgrad: number;
  avg_stopp_per_shift: number;
  pct_skift_med_stopp: number;
  total_stopptid_h: number;
  tot_stopp_min: number;
  tot_drift_min: number;
  vs_snitt_pp: number;
}

interface MonthlyTrend {
  yearmonth: string;
  stoppgrad: number;
  antal_skift: number;
}

interface ApiResponse {
  success: boolean;
  period_days: number;
  period_from: string;
  period_to: string;
  period_stoppgrad: number;
  period_antal_skift: number;
  products: ProductStopp[];
  monthly_trend: MonthlyTrend[];
}

type SortField = 'stoppgrad' | 'avg_stopp_per_shift' | 'pct_skift_med_stopp' | 'antal_skift' | 'product_name';

@Component({
  standalone: true,
  selector: 'app-stopp-per-produkt',
  imports: [CommonModule, FormsModule],
  templateUrl: './stopp-per-produkt.html',
  styleUrl: './stopp-per-produkt.css',
})
export class StoppPerProduktPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private barChart: Chart | null = null;
  private trendChart: Chart | null = null;

  Math = Math;

  loading = false;
  error = '';

  days = 90;
  daysOptions = [30, 90, 180, 365];

  data: ApiResponse | null = null;
  sortedProducts: ProductStopp[] = [];

  sortField: SortField = 'stoppgrad';
  sortAsc = false;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.barChart?.destroy();
    this.trendChart?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http
      .get<ApiResponse>(
        `${environment.apiUrl}?action=rebotling&run=stopp-per-produkt&days=${this.days}`,
        { withCredentials: true }
      )
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res || !res.success) {
          this.error = 'Kunde inte hämta data.';
          return;
        }
        this.data = res;
        this.sortProducts();
        setTimeout(() => {
          this.renderBarChart();
          this.renderTrendChart();
        }, 0);
      });
  }

  sortBy(field: SortField): void {
    if (this.sortField === field) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortField = field;
      this.sortAsc = field === 'product_name';
    }
    this.sortProducts();
  }

  private sortProducts(): void {
    if (!this.data) return;
    const list = [...this.data.products];
    list.sort((a, b) => {
      const aVal = a[this.sortField];
      const bVal = b[this.sortField];
      if (typeof aVal === 'string' && typeof bVal === 'string') {
        return this.sortAsc ? aVal.localeCompare(bVal, 'sv') : bVal.localeCompare(aVal, 'sv');
      }
      return this.sortAsc
        ? (aVal as number) - (bVal as number)
        : (bVal as number) - (aVal as number);
    });
    this.sortedProducts = list;
    setTimeout(() => this.renderBarChart(), 0);
  }

  worstProduct(): ProductStopp | null {
    if (!this.data?.products.length) return null;
    return [...this.data.products].sort((a, b) => b.stoppgrad - a.stoppgrad)[0];
  }

  bestProduct(): ProductStopp | null {
    if (!this.data?.products.length) return null;
    return [...this.data.products].sort((a, b) => a.stoppgrad - b.stoppgrad)[0];
  }

  stoppColor(stopp: number, avg: number): string {
    const diff = stopp - avg;
    if (diff >= 5) return '#fc8181';
    if (diff >= 2) return '#f6ad55';
    if (diff <= -2) return '#68d391';
    return '#63b3ed';
  }

  badgeClass(vs: number): string {
    if (vs >= 5) return 'badge-danger';
    if (vs >= 2) return 'badge-warn';
    if (vs <= -2) return 'badge-good';
    return 'badge-neutral';
  }

  badgeLabel(vs: number): string {
    const sign = vs > 0 ? '+' : '';
    return `${sign}${vs.toFixed(1)} pp`;
  }

  private renderBarChart(): void {
    const canvas = document.getElementById('barChart') as HTMLCanvasElement;
    if (!canvas || !this.data || !this.sortedProducts.length) return;
    this.barChart?.destroy();

    const avg = this.data.period_stoppgrad;
    const labels = this.sortedProducts.map(p => p.product_name);
    const values = this.sortedProducts.map(p => p.stoppgrad);
    const colors = this.sortedProducts.map(p => this.stoppColor(p.stoppgrad, avg));

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Stoppgrad %',
            data: values,
            backgroundColor: colors,
            borderRadius: 4,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ` ${(ctx.raw as number).toFixed(1)}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#e2e8f0', callback: v => `${v}%` },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#e2e8f0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
      plugins: [
        {
          id: 'avgLine',
          afterDraw: (chart) => {
            const xAxis = chart.scales['x'];
            const ctx2 = chart.ctx;
            const xPos = xAxis.getPixelForValue(avg);
            ctx2.save();
            ctx2.beginPath();
            ctx2.moveTo(xPos, chart.chartArea.top);
            ctx2.lineTo(xPos, chart.chartArea.bottom);
            ctx2.strokeStyle = 'rgba(250,240,137,0.8)';
            ctx2.lineWidth = 2;
            ctx2.setLineDash([5, 4]);
            ctx2.stroke();
            ctx2.fillStyle = '#faf089';
            ctx2.font = '11px sans-serif';
            ctx2.fillText(`Snitt ${avg.toFixed(1)}%`, xPos + 4, chart.chartArea.top + 14);
            ctx2.restore();
          },
        },
      ],
    });
  }

  private renderTrendChart(): void {
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || !this.data?.monthly_trend.length) return;
    this.trendChart?.destroy();

    const trend = this.data.monthly_trend;
    const labels = trend.map(t => t.yearmonth);
    const values = trend.map(t => t.stoppgrad);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Stoppgrad %',
            data: values,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ` ${(ctx.raw as number).toFixed(1)}%`,
            },
          },
        },
        scales: {
          x: { ticks: { color: '#e2e8f0' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            ticks: { color: '#e2e8f0', callback: v => `${v}%` },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }
}
