import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface PeriodStats {
  antal_skift: number;
  ibc_ok: number;
  ibc_per_h: number;
  kassation_pct: number;
  tillganglighet_pct: number;
  drifttid_h: number;
  per_position: {
    [key: string]: { antal_skift: number; ibc_per_h: number } | undefined;
  };
}

interface TrendPoint {
  week: string;
  ibc_per_h: number;
  antal_skift: number;
}

interface OperatorData {
  number: number;
  name: string;
  period_a: PeriodStats | null;
  period_b: PeriodStats | null;
  trend: TrendPoint[];
}

interface ApiResponse {
  success: boolean;
  data: {
    period_a: { from: string; to: string };
    period_b: { from: string; to: string };
    operatorer: OperatorData[];
    avg_ibc_per_h: number;
  };
}

@Component({
  standalone: true,
  selector: 'app-operator-analys',
  imports: [CommonModule, FormsModule],
  templateUrl: './operator-analys.html',
  styleUrl: './operator-analys.css'
})
export class OperatorAnalysPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private rankingChart: Chart | null = null;
  private sparklineCharts: Map<number, Chart> = new Map();

  loading = false;
  error = '';
  operatorer: OperatorData[] = [];
  avgIbcPerH = 0;
  activePeriod: 'a' | 'b' = 'b';

  periodAFrom = '';
  periodATo = '';
  periodBFrom = '';
  periodBTo = '';

  responseA: { from: string; to: string } | null = null;
  responseB: { from: string; to: string } | null = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    // Default: period B = this month, period A = last month
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth() + 1;
    this.periodBFrom = `${y}-${String(m).padStart(2, '0')}-01`;
    this.periodBTo = this.today();

    const prevM = m === 1 ? 12 : m - 1;
    const prevY = m === 1 ? y - 1 : y;
    const lastDay = new Date(y, m - 1, 0).getDate();
    this.periodAFrom = `${prevY}-${String(prevM).padStart(2, '0')}-01`;
    this.periodATo = `${prevY}-${String(prevM).padStart(2, '0')}-${lastDay}`;

    this.fetchData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.rankingChart?.destroy();
    this.sparklineCharts.forEach(c => c.destroy());
    this.sparklineCharts.clear();
  }

  private today(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  setPreset(preset: '7' | '30' | '90'): void {
    const days = parseInt(preset);
    const to = new Date();
    const from = new Date(Date.now() - days * 86400000);
    this.periodBFrom = this.dateStr(from);
    this.periodBTo = this.dateStr(to);

    const prevTo = new Date(from.getTime() - 86400000);
    const prevFrom = new Date(prevTo.getTime() - days * 86400000);
    this.periodAFrom = this.dateStr(prevFrom);
    this.periodATo = this.dateStr(prevTo);

    this.fetchData();
  }

  private dateStr(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  fetchData(): void {
    if (this.loading) return;
    this.error = '';
    this.loading = true;

    // Destroy old sparklines before re-rendering
    this.sparklineCharts.forEach(c => c.destroy());
    this.sparklineCharts.clear();

    const url = `${environment.apiUrl}?action=rebotling&run=operator-analys`
      + `&period_a_from=${this.periodAFrom}&period_a_to=${this.periodATo}`
      + `&period_b_from=${this.periodBFrom}&period_b_to=${this.periodBTo}`;

    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.operatorer = res.data.operatorer;
        this.avgIbcPerH = res.data.avg_ibc_per_h;
        this.responseA = res.data.period_a;
        this.responseB = res.data.period_b;
        setTimeout(() => {
          this.buildRankingChart();
          this.operatorer.forEach(op => this.buildSparkline(op));
        }, 50);
      });
  }

  delta(op: OperatorData, field: 'ibc_per_h' | 'kassation_pct' | 'tillganglighet_pct'): number | null {
    const a = op.period_a?.[field];
    const b = op.period_b?.[field];
    if (a == null || b == null) return null;
    return Math.round((b - a) * 10) / 10;
  }

  deltaClass(op: OperatorData, field: 'ibc_per_h' | 'kassation_pct' | 'tillganglighet_pct'): string {
    const d = this.delta(op, field);
    if (d == null) return '';
    const positive = field === 'kassation_pct' ? d < 0 : d > 0;
    const negative = field === 'kassation_pct' ? d > 0 : d < 0;
    if (positive) return 'delta-up';
    if (negative) return 'delta-down';
    return 'delta-neutral';
  }

  deltaArrow(op: OperatorData, field: 'ibc_per_h' | 'kassation_pct' | 'tillganglighet_pct'): string {
    const d = this.delta(op, field);
    if (d == null || d === 0) return '';
    const positive = field === 'kassation_pct' ? d < 0 : d > 0;
    return positive ? '↑' : '↓';
  }

  positionLabel(pos: 'op1' | 'op2' | 'op3'): string {
    const map: Record<string, string> = { op1: 'Tvätt', op2: 'Kontroll', op3: 'Truck' };
    return map[pos] ?? pos;
  }

  positionBadgeClass(pos: 'op1' | 'op2' | 'op3'): string {
    const map: Record<string, string> = { op1: 'badge-tvätt', op2: 'badge-kontroll', op3: 'badge-truck' };
    return map[pos] ?? '';
  }

  getPositions(op: OperatorData): Array<'op1' | 'op2' | 'op3'> {
    const src = op.period_b ?? op.period_a;
    if (!src) return [];
    return (Object.keys(src.per_position) as Array<'op1' | 'op2' | 'op3'>)
      .filter(k => (src.per_position[k]?.antal_skift ?? 0) > 0);
  }

  private buildRankingChart(): void {
    this.rankingChart?.destroy();
    const canvas = document.getElementById('rankingChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    const ops = this.operatorer.filter(o => o.period_b?.ibc_per_h);
    const labels = ops.map(o => o.name);
    const values = ops.map(o => o.period_b!.ibc_per_h);
    const avg = this.avgIbcPerH;

    this.rankingChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'IBC/timme',
          data: values,
          backgroundColor: values.map(v => v >= avg ? 'rgba(99,179,237,0.7)' : 'rgba(99,179,237,0.35)'),
          borderColor: values.map(v => v >= avg ? '#63b3ed' : '#4299e1'),
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.parsed.x} IBC/h`,
              afterLabel: (ctx) => {
                const op = ops[ctx.dataIndex];
                const d = this.delta(op, 'ibc_per_h');
                return d != null ? ` vs föreg period: ${d > 0 ? '+' : ''}${d}` : '';
              }
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(255,255,255,0.05)' },
            ticks: { color: '#a0aec0', font: { size: 11 } },
          },
          y: {
            grid: { display: false },
            ticks: { color: '#e2e8f0', font: { size: 12 } },
          }
        }
      },
      plugins: [{
        id: 'avgLine',
        afterDraw: (chart) => {
          if (avg <= 0) return;
          const { ctx, chartArea, scales } = chart;
          const x = scales['x'].getPixelForValue(avg);
          ctx.save();
          ctx.beginPath();
          ctx.setLineDash([5, 4]);
          ctx.strokeStyle = '#f6ad55';
          ctx.lineWidth = 1.5;
          ctx.moveTo(x, chartArea.top);
          ctx.lineTo(x, chartArea.bottom);
          ctx.stroke();
          ctx.fillStyle = '#f6ad55';
          ctx.font = '10px sans-serif';
          ctx.fillText(`\u2300 ${avg}`, x + 4, chartArea.top + 12);
          ctx.restore();
        }
      }]
    });
  }

  private buildSparkline(op: OperatorData): void {
    if (!op.trend?.length) return;
    const canvas = document.getElementById(`spark-${op.number}`) as HTMLCanvasElement | null;
    if (!canvas) return;

    const existing = this.sparklineCharts.get(op.number);
    existing?.destroy();

    const labels = op.trend.map(t => t.week.slice(5)); // MM-DD
    const data = op.trend.map(t => t.ibc_per_h);

    const chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          data,
          borderColor: '#63b3ed',
          backgroundColor: 'rgba(99,179,237,0.1)',
          borderWidth: 1.5,
          pointRadius: 2,
          tension: 0.3,
          fill: true,
        }]
      },
      options: {
        responsive: false,
        animation: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
          x: { display: false },
          y: { display: false }
        }
      }
    });
    this.sparklineCharts.set(op.number, chart);
  }

  formatDate(s: string): string {
    if (!s) return '';
    const [, m, d] = s.split('-');
    return `${d}/${m}`;
  }
}
