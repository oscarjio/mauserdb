import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface DailyRow {
  datum: string;
  ibc_per_h: number | null;
  total_ibc: number;
  antal_skift: number;
  ma7: number | null;
}

interface MonthlyRow {
  yearmonth: string;
  total_ibc: number;
  total_hours: number;
  ibc_per_h: number | null;
  antal_skift: number;
}

interface Kpis {
  total_ibc: number;
  avg_ibc_h: number;
  prod_days: number;
  best_ibc_h: number;
  best_day: string | null;
  trend_arrow: 'up' | 'down' | 'flat';
  trend_pct: number;
  days_above: number;
}

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  daily: DailyRow[];
  monthly: MonthlyRow[];
  kpis: Kpis;
}

@Component({
  standalone: true,
  selector: 'app-produktions-tidsserie',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produktions-tidsserie.html',
  styleUrl: './produktions-tidsserie.css',
})
export class ProduktionsTidsseriePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private lineChart: Chart | null = null;
  private barChart: Chart | null = null;

  Math = Math;

  days = 180;
  loading = false;
  error = '';

  daily: DailyRow[] = [];
  monthly: MonthlyRow[] = [];
  kpis: Kpis | null = null;
  from = '';
  to = '';

  activeView: 'daglig' | 'manadsvis' = 'daglig';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.lineChart?.destroy(); } catch (_) {}
    try { this.barChart?.destroy(); } catch (_) {}
    this.lineChart = null;
    this.barChart = null;
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=produktions-tidsserie&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta produktionsdata.';
        return;
      }
      this.daily   = res.daily;
      this.monthly = res.monthly;
      this.kpis    = res.kpis;
      this.from    = res.from;
      this.to      = res.to;
      setTimeout(() => { this.buildCharts(); }, 80);
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setView(v: 'daglig' | 'manadsvis'): void {
    this.activeView = v;
    setTimeout(() => { this.buildCharts(); }, 80);
  }

  private buildCharts(): void {
    this.buildLineChart();
    this.buildBarChart();
  }

  private buildLineChart(): void {
    try { this.lineChart?.destroy(); } catch (_) {}
    this.lineChart = null;
    const canvas = document.getElementById('lineChart') as HTMLCanvasElement;
    if (!canvas || this.daily.length === 0) return;

    const labels  = this.daily.map(d => d.datum);
    const ibcH    = this.daily.map(d => d.ibc_per_h);
    const ma7     = this.daily.map(d => d.ma7);
    const avgLine = this.daily.map(() => this.kpis?.avg_ibc_h ?? null);

    this.lineChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Daglig IBC/h',
            data: ibcH,
            borderColor: 'rgba(99,179,237,0.6)',
            backgroundColor: 'rgba(99,179,237,0.06)',
            fill: false,
            tension: 0.2,
            pointRadius: this.daily.length > 120 ? 0 : 2,
            borderWidth: 1.5,
            spanGaps: true,
          },
          {
            label: '7-dagars snitt',
            data: ma7,
            borderColor: '#68d391',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.4,
            pointRadius: 0,
            borderWidth: 2.5,
            spanGaps: true,
          },
          {
            label: `Periodssnitt (${this.kpis?.avg_ibc_h?.toFixed(1)} IBC/h)`,
            data: avgLine,
            borderColor: '#f6ad55',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0,
            pointRadius: 0,
            borderWidth: 1.5,
            borderDash: [6, 4],
            spanGaps: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              title: ctx => ctx[0]?.label ?? '',
              label: ctx => {
                const v = ctx.parsed.y;
                if (v === null || v === undefined) return '';
                return ` ${ctx.dataset.label}: ${v.toFixed(1)} IBC/h`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              font: { size: 10 },
              maxTicksLimit: 16,
              maxRotation: 45,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: false,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'IBC / timme',
              color: '#8fa3b8',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  private buildBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;
    const canvas = document.getElementById('barChart') as HTMLCanvasElement;
    if (!canvas || this.monthly.length === 0) return;

    const labels = this.monthly.map(m => this.monthLabel(m.yearmonth));
    const ibcH   = this.monthly.map(m => m.ibc_per_h ?? 0);
    const avgH   = this.kpis?.avg_ibc_h ?? 0;
    const colors = ibcH.map(v => v >= avgH ? 'rgba(104,211,145,0.8)' : 'rgba(252,129,129,0.7)');

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h per månad',
            data: ibcH,
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('0.8', '1').replace('0.7', '1')),
            borderWidth: 1,
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: ctx => {
                const row = this.monthly[ctx.dataIndex];
                const y = ctx.parsed.y ?? 0;
                return [
                  ` IBC/h: ${y.toFixed(1)}`,
                  ` Total IBC: ${row.total_ibc.toLocaleString('sv-SE')}`,
                  ` Skift: ${row.antal_skift}`,
                ];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: false,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'IBC / timme',
              color: '#8fa3b8',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  monthLabel(ym: string): string {
    const [y, m] = ym.split('-');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
    return `${months[parseInt(m, 10) - 1]} ${y}`;
  }

  formatDate(d: string): string {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('sv-SE', {
      day: 'numeric', month: 'short', year: 'numeric',
    });
  }

  trendLabel(): string {
    if (!this.kpis) return '';
    const a = this.kpis.trend_arrow;
    const p = Math.abs(this.kpis.trend_pct).toFixed(1);
    if (a === 'up')   return `↑ +${p}% jämfört med periodens start`;
    if (a === 'down') return `↓ −${p}% jämfört med periodens start`;
    return `→ Stabil (${p}%)`;
  }

  trendClass(): string {
    if (!this.kpis) return '';
    if (this.kpis.trend_arrow === 'up')   return 'trend-up';
    if (this.kpis.trend_arrow === 'down') return 'trend-down';
    return 'trend-stable';
  }

  daysAbovePct(): number {
    if (!this.kpis || this.kpis.prod_days === 0) return 0;
    return Math.round(this.kpis.days_above / this.kpis.prod_days * 100);
  }
}
