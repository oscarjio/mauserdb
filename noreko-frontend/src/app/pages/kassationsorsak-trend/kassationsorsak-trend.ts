import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface Cause {
  id: number;
  namn: string;
  color: string | null;
  total: number;
  trend: 'ökande' | 'minskande' | 'stabil';
  delta_pct: number;
  monthly: number[];
}

interface MonthEntry {
  month: string;
  label: string;
  total: number;
}

interface Kpi {
  total: number;
  top_cause: string | null;
  unique_causes: number;
  months_with_data: number;
  okande: number;
  minskande: number;
}

interface ApiResponse {
  success: boolean;
  months: string[];
  labels: string[];
  causes: Cause[];
  monthly: MonthEntry[];
  kpi: Kpi;
  from: string;
  to: string;
}

const PALETTE = [
  '#fc8181', '#f6ad55', '#fbd38d', '#68d391', '#63b3ed',
  '#b794f4', '#76e4f7', '#9ae6b4', '#90cdf4', '#feb2b2',
  '#d6bcfa', '#faf089', '#81e6d9', '#e9d8fd', '#bee3f8',
];

@Component({
  standalone: true,
  selector: 'app-kassationsorsak-trend',
  imports: [CommonModule, FormsModule],
  templateUrl: './kassationsorsak-trend.html',
  styleUrl: './kassationsorsak-trend.css',
})
export class KassationsorsakTrendPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;

  loading = false;
  error = '';
  months = 12;
  readonly monthOptions = [6, 12, 18, 24];

  causes: Cause[] = [];
  monthly: MonthEntry[] = [];
  kpi: Kpi | null = null;
  labels: string[] = [];
  from = '';
  to = '';

  hiddenCauses = new Set<number>();
  sortBy: 'total' | 'trend' | 'namn' = 'total';

  noData = false;

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.chart?.destroy();
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.noData = false;

    const url = `${environment.apiUrl}?action=rebotling&run=kassationsorsak-trend&months=${this.months}`;
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta kassationsorsakstrender.';
        return;
      }
      this.causes  = res.causes;
      this.monthly = res.monthly;
      this.kpi     = res.kpi;
      this.labels  = res.labels;
      this.from    = res.from;
      this.to      = res.to;
      this.noData  = res.causes.length === 0;
      this.hiddenCauses.clear();
      setTimeout(() => this.buildChart(), 50);
    });
  }

  onMonthsChange(m: number): void {
    this.months = m;
    this.load();
  }

  toggleCause(id: number): void {
    if (this.hiddenCauses.has(id)) {
      this.hiddenCauses.delete(id);
    } else {
      this.hiddenCauses.add(id);
    }
    this.buildChart();
  }

  isCauseVisible(id: number): boolean {
    return !this.hiddenCauses.has(id);
  }

  get sortedCauses(): Cause[] {
    const c = [...this.causes];
    if (this.sortBy === 'total')  return c.sort((a, b) => b.total - a.total);
    if (this.sortBy === 'trend')  return c.sort((a, b) => b.delta_pct - a.delta_pct);
    return c.sort((a, b) => a.namn.localeCompare(b.namn));
  }

  maxMonthTotal(): number {
    return this.monthly.reduce((m, x) => x.total > m ? x.total : m, 1);
  }

  maxCauseMonthly(c: Cause): number {
    return c.monthly.reduce((m, v) => v > m ? v : m, 1);
  }

  trendIcon(t: string): string {
    if (t === 'ökande')    return '↑';
    if (t === 'minskande') return '↓';
    return '→';
  }

  trendClass(t: string): string {
    if (t === 'ökande')    return 'text-danger';
    if (t === 'minskande') return 'text-success';
    return 'text-warning';
  }

  causeColor(cause: Cause, idx: number): string {
    return cause.color ?? PALETTE[idx % PALETTE.length];
  }

  private buildChart(): void {
    this.chart?.destroy();
    this.chart = null;

    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || this.causes.length === 0) return;

    const datasets = this.causes
      .filter(c => !this.hiddenCauses.has(c.id))
      .map((c, i) => ({
        label: c.namn,
        data: c.monthly,
        borderColor: this.causeColor(c, i),
        backgroundColor: this.causeColor(c, i) + '22',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6,
        tension: 0.3,
        fill: false,
      }));

    this.chart = new Chart(canvas, {
      type: 'line',
      data: { labels: this.labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0', boxWidth: 12, font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y} händelser`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 14 },
            grid: { color: '#2d3748' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', precision: 0 },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'Antal händelser', color: '#a0aec0' },
          },
        },
      },
    });
  }
}
