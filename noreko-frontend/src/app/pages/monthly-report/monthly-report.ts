import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, forkJoin, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface MonthlySummary {
  ibc_total: number;
  ibc_goal: number;
  goal_pct: number;
  avg_ibc_per_day: number;
  active_days: number;
  production_days: number;
  avg_quality: number;
  avg_oee: number;
  total_runtime_hours: number;
  total_stoppage_hours: number;
  total_stopp_min: number;
}

interface DayEntry {
  date: string;
  ibc: number;
  quality: number;
  oee: number;
}

interface WeekEntry {
  week: string;
  ibc: number;
  avg_quality: number;
  avg_oee: number;
}

interface OperatorEntry {
  name: string;
  number: number;
  shifts: number;
  ibc_ok: number;
  avg_ibc_per_hour: number | null;
  avg_quality: number | null;
}

interface BestWorstDay {
  date: string;
  ibc: number;
  quality: number;
}

interface BestWorstWeek {
  week: string;
  ibc: number;
  avg_oee: number;
}

interface OeeTrendEntry {
  date: string;
  oee: number;
}

interface TopOperator {
  namn: string;
  ibc_total: number;
}

interface MonthlyReport {
  month: string;
  month_label: string;
  summary: MonthlySummary;
  best_day: BestWorstDay | null;
  worst_day: BestWorstDay | null;
  basta_vecka: BestWorstWeek | null;
  samsta_vecka: BestWorstWeek | null;
  oee_trend: OeeTrendEntry[];
  top_operatorer: TopOperator[];
  operator_ranking: OperatorEntry[];
  daily_production: DayEntry[];
  week_summary: WeekEntry[];
}

interface CompareMonthData {
  total_ibc: number;
  avg_ibc_per_day: number;
  avg_oee_pct: number;
  avg_quality_pct: number;
  working_days: number;
  month_goal: number;
}

interface CompareDiff {
  total_ibc_pct: number | null;
  avg_ibc_per_day_pct: number | null;
  avg_oee_pct_diff: number;
  avg_quality_pct_diff: number;
}

interface OperatorOfMonth {
  op_id: number;
  namn: string;
  initialer: string;
  total_ibc: number;
  avg_ibc_per_h: number;
  avg_quality_pct: number;
}

interface CompareDay {
  datum: string;
  ibc: number;
  target_pct: number;
}

interface MonthCompare {
  success: boolean;
  month: string;
  prev_month: string;
  this_month: CompareMonthData;
  prev_month_data: CompareMonthData;
  diff: CompareDiff;
  operator_of_month: OperatorOfMonth | null;
  best_day: CompareDay | null;
  worst_day: CompareDay | null;
}

@Component({
  standalone: true,
  selector: 'app-monthly-report',
  imports: [CommonModule, FormsModule],
  templateUrl: './monthly-report.html',
  styleUrl: './monthly-report.css'
})
export class MonthlyReportPage implements OnInit, OnDestroy, AfterViewChecked {
  @ViewChild('productionChart') productionChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('oeeChart') oeeChartRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;
  private oeeLineChart: Chart | null = null;
  private chartPendingRender = false;

  // Månadsväljare: standard = förra månaden
  selectedMonth: string = (() => {
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    return d.toISOString().slice(0, 7);
  })();

  isLoading = false;
  hasData = false;
  errorMsg = '';
  report: MonthlyReport | null = null;
  compareData: MonthCompare | null = null;

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {}

  ngAfterViewChecked(): void {
    if (this.chartPendingRender && this.productionChartRef && this.report) {
      this.chartPendingRender = false;
      this.renderChart();
      this.renderOeeChart();
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
    this.oeeLineChart?.destroy();
  }

  fetchReport(): void {
    if (!this.selectedMonth || this.isLoading) return;

    this.isLoading = true;
    this.hasData = false;
    this.errorMsg = '';
    this.report = null;
    this.compareData = null;
    this.chart?.destroy();
    this.chart = null;
    this.oeeLineChart?.destroy();
    this.oeeLineChart = null;

    const reportUrl  = `/noreko-backend/api.php?action=rebotling&run=monthly-report&month=${this.selectedMonth}`;
    const compareUrl = `/noreko-backend/api.php?action=rebotling&run=month-compare&month=${this.selectedMonth}`;

    const report$ = this.http.get<any>(reportUrl, { withCredentials: true }).pipe(
      timeout(20000),
      catchError(() => of({ success: false, error: 'Nätverksfel — kunde inte nå servern' }))
    );

    const compare$ = this.http.get<any>(compareUrl, { withCredentials: true }).pipe(
      timeout(20000),
      catchError(() => of(null))
    );

    forkJoin({ report: report$, compare: compare$ }).pipe(
      takeUntil(this.destroy$)
    ).subscribe(({ report, compare }) => {
      this.isLoading = false;
      if (report?.success && report.summary) {
        this.report = report as MonthlyReport;
        this.hasData = true;
        this.chartPendingRender = true;
      } else {
        this.errorMsg = report?.error || 'Ingen data hittades för vald månad';
      }
      if (compare?.success) {
        this.compareData = compare as MonthCompare;
      }
    });
  }

  goalClass(pct: number): string {
    if (pct >= 95) return 'text-success';
    if (pct >= 80) return 'text-warning';
    return 'text-danger';
  }

  goalBadgeClass(pct: number): string {
    if (pct >= 95) return 'badge-success';
    if (pct >= 80) return 'badge-warning';
    return 'badge-danger';
  }

  rankMedal(i: number): string {
    if (i === 0) return '🥇';
    if (i === 1) return '🥈';
    if (i === 2) return '🥉';
    return String(i + 1);
  }

  medalColor(i: number): string {
    if (i === 0) return '#f6e05e';
    if (i === 1) return '#a0aec0';
    return '#c05621';
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }

  diffClass(val: number | null): string {
    if (val === null || val === undefined) return 'diff-neutral';
    if (val > 0) return 'diff-positive';
    if (val < 0) return 'diff-negative';
    return 'diff-neutral';
  }

  diffArrow(val: number | null): string {
    if (val === null || val === undefined) return '—';
    if (val > 0) return '↑';
    if (val < 0) return '↓';
    return '→';
  }

  diffSign(val: number | null): string {
    if (val === null || val === undefined) return '';
    return val >= 0 ? '+' : '';
  }

  exportPDF(): void {
    window.print();
  }

  private renderChart(): void {
    if (!this.productionChartRef || !this.report) return;

    const canvas = this.productionChartRef.nativeElement;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chart?.destroy();

    const days = this.report.daily_production;
    const dailyGoal = this.report.summary.ibc_goal / Math.max(this.report.summary.production_days, 1);

    const labels = days.map(d => {
      const parts = d.date.split('-');
      return `${parts[2]}/${parts[1]}`;
    });

    const ibcColors = days.map(d => {
      const pct = dailyGoal > 0 ? (d.ibc / dailyGoal) * 100 : 0;
      if (pct >= 95) return 'rgba(72, 187, 120, 0.85)';
      if (pct >= 80) return 'rgba(237, 179, 57, 0.85)';
      return 'rgba(245, 101, 101, 0.85)';
    });

    this.chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC tvättade',
            data: days.map(d => d.ibc),
            backgroundColor: ibcColors,
            borderColor: ibcColors.map(c => c.replace('0.85', '1')),
            borderWidth: 1,
            yAxisID: 'y',
          },
          {
            label: 'Kvalitet %',
            data: days.map(d => d.quality),
            type: 'line',
            borderColor: 'rgba(99, 179, 237, 1)',
            backgroundColor: 'rgba(99, 179, 237, 0.15)',
            borderWidth: 2,
            pointRadius: 3,
            fill: false,
            tension: 0.3,
            yAxisID: 'y2',
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            callbacks: {
              afterBody: (items) => {
                const idx = items[0]?.dataIndex;
                if (idx !== undefined && days[idx]) {
                  return [`OEE: ${days[idx].oee}%`];
                }
                return [];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 60 },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            position: 'left',
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'IBC', color: '#a0aec0' }
          },
          y2: {
            position: 'right',
            min: 0,
            max: 100,
            ticks: { color: '#63b3ed', callback: (v) => v + '%' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kvalitet %', color: '#63b3ed' }
          }
        }
      }
    });
  }

  private renderOeeChart(): void {
    if (!this.oeeChartRef || !this.report) return;
    const canvas = this.oeeChartRef.nativeElement;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.oeeLineChart?.destroy();

    const trend = this.report.oee_trend ?? [];
    if (trend.length === 0) return;

    const labels = trend.map(d => {
      const parts = d.date.split('-');
      return `${parts[2]}/${parts[1]}`;
    });

    const wcmRef = trend.map(() => 85);

    this.oeeLineChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: trend.map(d => d.oee),
            borderColor: 'rgba(72, 187, 120, 1)',
            backgroundColor: 'rgba(72, 187, 120, 0.12)',
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: 'rgba(72, 187, 120, 1)',
            fill: true,
            tension: 0.3,
          },
          {
            label: 'WCM 85%',
            data: wcmRef,
            borderColor: 'rgba(246, 224, 94, 0.7)',
            borderWidth: 1.5,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            callbacks: {
              label: (item) => `${item.dataset.label}: ${item.parsed.y}%`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 60 },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'OEE %', color: '#a0aec0' }
          }
        }
      }
    });
  }
}
