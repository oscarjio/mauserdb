import { Component, OnInit, OnDestroy, ElementRef, ViewChild, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface Summary {
  total_ibc: number;
  total_ibc_ej: number;
  ibc_per_h: number;
  kassation_pct: number;
  stoppgrad: number;
  antal_skift: number;
  produktionsdagar: number;
  total_timmar: number;
}

interface DayPoint {
  datum: string;
  dag: number;
  ibc_per_h: number;
  total_ibc: number;
}

interface OpRow {
  op_num: number;
  name: string;
  a_ibch: number | null;
  a_skift: number;
  b_ibch: number | null;
  b_skift: number;
  delta: number | null;
  delta_pct: number | null;
}

interface ApiResponse {
  success: boolean;
  month_a: string;
  month_b: string;
  summary_a: Summary;
  summary_b: Summary;
  daily_a: DayPoint[];
  daily_b: DayPoint[];
  op_comparison: OpRow[];
}

@Component({
  standalone: true,
  selector: 'app-manads-jamforelse',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './manads-jamforelse.html',
  styleUrl: './manads-jamforelse.css',
})
export class ManadsJamforelsePage implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('overlayChart') overlayChartRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;

  loading = false;
  error = '';

  monthA = '';
  monthB = '';

  summaryA: Summary | null = null;
  summaryB: Summary | null = null;
  dailyA: DayPoint[] = [];
  dailyB: DayPoint[] = [];
  opComparison: OpRow[] = [];

  opSort: 'delta' | 'a_ibch' | 'b_ibch' | 'name' = 'delta';
  opFilter: 'alla' | 'improved' | 'declined' = 'alla';

  Math = Math;

  get filteredOps(): OpRow[] {
    let list = [...this.opComparison];
    if (this.opFilter === 'improved') list = list.filter(o => (o.delta ?? 0) > 0);
    if (this.opFilter === 'declined') list = list.filter(o => (o.delta ?? 0) < 0);
    list.sort((a, b) => {
      if (this.opSort === 'name') return (a.name ?? '').localeCompare(b.name ?? '', 'sv');
      if (this.opSort === 'a_ibch') return (b.a_ibch ?? 0) - (a.a_ibch ?? 0);
      if (this.opSort === 'b_ibch') return (b.b_ibch ?? 0) - (a.b_ibch ?? 0);
      return Math.abs(b.delta ?? 0) - Math.abs(a.delta ?? 0);
    });
    return list;
  }

  get improvedCount(): number { return this.opComparison.filter(o => (o.delta ?? 0) > 0.2).length; }
  get declinedCount(): number { return this.opComparison.filter(o => (o.delta ?? 0) < -0.2).length; }
  get bothCount(): number { return this.opComparison.filter(o => o.a_ibch !== null && o.b_ibch !== null).length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    this.monthA = `${y}-${m}`;
    const lastYear = new Date(now);
    lastYear.setFullYear(lastYear.getFullYear() - 1);
    this.monthB = `${lastYear.getFullYear()}-${m}`;
    this.load();
  }

  ngAfterViewInit(): void {}

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

    const url = `${environment.apiUrl}?action=rebotling&run=manads-jamforelse&month_a=${this.monthA}&month_b=${this.monthB}`;
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta månadsdata.';
        return;
      }
      this.summaryA = res.summary_a;
      this.summaryB = res.summary_b;
      this.dailyA = res.daily_a;
      this.dailyB = res.daily_b;
      this.opComparison = res.op_comparison;
      setTimeout(() => this.buildChart(), 50);
    });
  }

  setQuickA(): void {
    const now = new Date();
    this.monthA = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    this.monthB = `${now.getFullYear() - 1}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    this.load();
  }

  setQuickPrev(): void {
    const now = new Date();
    now.setDate(1);
    now.setMonth(now.getMonth() - 1);
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    this.monthA = `${y}-${m}`;
    const prev = new Date(now);
    prev.setMonth(prev.getMonth() - 1);
    this.monthB = `${prev.getFullYear()}-${String(prev.getMonth() + 1).padStart(2, '0')}`;
    this.load();
  }

  private buildChart(): void {
    if (!this.overlayChartRef?.nativeElement) return;
    this.chart?.destroy();

    // Build day 1-31 arrays
    const days = Array.from({ length: 31 }, (_, i) => i + 1);
    const mapA: Record<number, number | null> = {};
    const mapB: Record<number, number | null> = {};
    for (const d of this.dailyA) mapA[d.dag] = d.ibc_per_h;
    for (const d of this.dailyB) mapB[d.dag] = d.ibc_per_h;

    const dataA = days.map(d => mapA[d] ?? null);
    const dataB = days.map(d => mapB[d] ?? null);

    const labelA = this.monthLabel(this.monthA);
    const labelB = this.monthLabel(this.monthB);

    this.chart = new Chart(this.overlayChartRef.nativeElement, {
      type: 'line',
      data: {
        labels: days.map(d => `Dag ${d}`),
        datasets: [
          {
            label: labelA,
            data: dataA,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.1)',
            borderWidth: 2,
            pointRadius: 3,
            spanGaps: true,
            tension: 0.3,
          },
          {
            label: labelB,
            data: dataB,
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246,173,85,0.1)',
            borderWidth: 2,
            pointRadius: 3,
            spanGaps: true,
            tension: 0.3,
            borderDash: [5, 3],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 12 } } },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(1) + ' IBC/h' : 'Ingen data'}`,
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0', font: { size: 10 } }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'IBC/h', color: '#a0aec0' } },
        },
      },
    });
  }

  monthLabel(ym: string): string {
    if (!ym) return '';
    const [y, m] = ym.split('-').map(Number);
    const months = ['Januari','Februari','Mars','April','Maj','Juni','Juli','Augusti','September','Oktober','November','December'];
    return `${months[m - 1]} ${y}`;
  }

  delta(a: number | null | undefined, b: number | null | undefined): number | null {
    if (a == null || b == null || b === 0) return null;
    return a - b;
  }

  deltaPct(a: number | null | undefined, b: number | null | undefined): number | null {
    if (a == null || b == null || b === 0) return null;
    return (a - b) / b * 100;
  }

  deltaClass(d: number | null): string {
    if (d === null) return 'delta-neutral';
    if (d > 0.2) return 'delta-up';
    if (d < -0.2) return 'delta-down';
    return 'delta-neutral';
  }

  deltaSign(d: number | null, decimals = 1): string {
    if (d === null) return '—';
    const fmt = Math.abs(d).toFixed(decimals);
    return d > 0 ? `+${fmt}` : `-${fmt}`;
  }

  opDeltaClass(row: OpRow): string {
    if (row.delta === null) return '';
    if (row.delta > 0.2) return 'op-improved';
    if (row.delta < -0.2) return 'op-declined';
    return 'op-neutral';
  }
}
