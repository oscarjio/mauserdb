import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, ChartConfiguration, registerables } from 'chart.js';
Chart.register(...registerables);

interface WeekPoint {
  yw: number;
  kassgrad: number | null;
  total_ibc: number;
  total_ej: number;
  skifter: number;
}

interface OperatorTrend {
  number: number;
  name: string;
  weeks: WeekPoint[];
  trend: 'better' | 'stable' | 'worse';
  current_kassgrad: number | null;
  prev_kassgrad: number | null;
  overall_kassgrad: number | null;
  total_ibc: number;
  total_ej: number;
}

interface TeamWeek {
  yw: number;
  kassgrad: number | null;
}

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  weeks: number;
  week_labels: { yw: number; label: string }[];
  operators: OperatorTrend[];
  team_avg_by_week: TeamWeek[];
}

const COLORS = [
  '#63b3ed','#68d391','#f6ad55','#fc8181','#a78bfa',
  '#f6e05e','#76e4f7','#fbb6ce','#9ae6b4','#feb2b2',
];

@Component({
  standalone: true,
  selector: 'app-kassation-trend',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './kassation-trend.html',
  styleUrl: './kassation-trend.css',
})
export class KassationTrendPage implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('chartCanvas') chartCanvasRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;

  selectedWeeks = 12;
  weeksOptions = [4, 8, 12, 16, 26];

  isLoading = false;
  error = false;

  weekLabels: { yw: number; label: string }[] = [];
  operators: OperatorTrend[] = [];
  teamAvgByWeek: TeamWeek[] = [];
  from = '';
  to = '';

  private viewReady = false;
  private dataReady = false;

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (this.dataReady) this.buildChart();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
    this.chart = null;
  }

  load(): void {
    this.isLoading = true;
    this.error = false;
    this.dataReady = false;
    this.chart?.destroy();
    this.chart = null;

    this.http.get<ApiResponse>(
      `/noreko-backend/api.php?action=kassation-trend&weeks=${this.selectedWeeks}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isLoading = false;
      if (!res?.success) { this.error = true; return; }
      this.weekLabels    = res.week_labels;
      this.operators     = res.operators;
      this.teamAvgByWeek = res.team_avg_by_week;
      this.from          = res.from;
      this.to            = res.to;
      this.dataReady     = true;
      if (this.viewReady) this.buildChart();
    });
  }

  private buildChart(): void {
    this.chart?.destroy();
    this.chart = null;
    const canvas = this.chartCanvasRef?.nativeElement;
    if (!canvas) return;

    const labels = this.weekLabels.map(w => w.label);

    const datasets: ChartConfiguration<'line'>['data']['datasets'] = [];

    // Team average dashed line
    datasets.push({
      label: 'Lagsnitt',
      data: this.teamAvgByWeek.map(w => w.kassgrad),
      borderColor: 'rgba(255,255,255,0.4)',
      borderDash: [5, 3],
      borderWidth: 2,
      pointRadius: 0,
      fill: false,
      tension: 0.3,
    } as any);

    // Per-operator lines
    this.operators.forEach((op, i) => {
      const ywMap = new Map(op.weeks.map(w => [w.yw, w.kassgrad]));
      const data = this.weekLabels.map(wl => ywMap.get(wl.yw) ?? null);
      const color = COLORS[i % COLORS.length];
      datasets.push({
        label: op.name,
        data,
        borderColor: color,
        backgroundColor: color + '33',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        fill: false,
        tension: 0.3,
        spanGaps: true,
      } as any);
    });

    this.chart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0', boxWidth: 12 } },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.parsed.y;
                return v == null ? '' : ` ${ctx.dataset.label}: ${v.toFixed(1)}%`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            ticks: { color: '#a0aec0', callback: v => `${v}%` },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Kassationsgrad (%)', color: '#a0aec0' },
          }
        }
      }
    });
  }

  trendIcon(t: string): string {
    if (t === 'better') return '↓';
    if (t === 'worse')  return '↑';
    return '→';
  }

  trendClass(t: string): string {
    if (t === 'better') return 'trend-better';
    if (t === 'worse')  return 'trend-worse';
    return 'trend-stable';
  }

  trendLabel(t: string): string {
    if (t === 'better') return 'Förbättras';
    if (t === 'worse')  return 'Försämras';
    return 'Stabil';
  }

  get worsening(): number { return this.operators.filter(o => o.trend === 'worse').length; }
  get improving(): number { return this.operators.filter(o => o.trend === 'better').length; }

  delta(op: OperatorTrend): string {
    if (op.current_kassgrad == null || op.prev_kassgrad == null) return '—';
    const d = op.current_kassgrad - op.prev_kassgrad;
    return (d >= 0 ? '+' : '') + d.toFixed(1) + ' pp';
  }
}
