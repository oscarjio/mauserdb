import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface DailyPoint {
  dag: number;
  datum: string;
  cum_ibc: number;
  daily_ibc: number;
  daily_ibch: number;
}

interface YearSummary {
  ar: number;
  total_ibc: number;
  ibc_h: number;
  antal_dagar: number;
  delta_pct: number | null;
}

interface ApiResponse {
  success: boolean;
  years: number[];
  cur_year: number;
  cur_last_dag: number;
  series: Record<string, DailyPoint[]>;
  year_summary: YearSummary[];
  at_same_day: Record<string, number | null>;
  cur_at_same_day: number;
  all_time_ibc: number;
}

const YEAR_COLORS: string[] = [
  'rgba(99,179,237,0.85)',
  'rgba(154,230,180,0.85)',
  'rgba(246,173,85,0.85)',
  'rgba(252,129,129,0.85)',
  'rgba(167,139,250,0.85)',
  'rgba(246,201,14,0.85)',
];

@Component({
  standalone: true,
  selector: 'app-ars-kurva',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './ars-kurva.html',
  styleUrl: './ars-kurva.css',
})
export class ArsKurvaPage implements OnInit, OnDestroy {
  @ViewChild('curveCanvas') curveCanvas!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;

  loading = false;
  error = '';
  Math = Math;

  years: number[] = [];
  curYear = 0;
  curLastDag = 0;
  series: Record<string, DailyPoint[]> = {};
  yearSummary: YearSummary[] = [];
  atSameDay: Record<string, number | null> = {};
  curAtSameDay = 0;
  allTimeIbc = 0;

  visibleYears: Set<number> = new Set();

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

    const url = `${environment.apiUrl}?action=rebotling&run=ars-kurva`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda data.'; return; }
        this.years = res.years;
        this.curYear = res.cur_year;
        this.curLastDag = res.cur_last_dag;
        this.series = res.series;
        this.yearSummary = res.year_summary;
        this.atSameDay = res.at_same_day;
        this.curAtSameDay = res.cur_at_same_day;
        this.allTimeIbc = res.all_time_ibc;
        this.visibleYears = new Set(this.years);
        setTimeout(() => this.buildChart(), 0);
      });
  }

  toggleYear(ar: number): void {
    if (this.visibleYears.has(ar)) {
      if (this.visibleYears.size > 1) this.visibleYears.delete(ar);
    } else {
      this.visibleYears.add(ar);
    }
    this.rebuildChart();
  }

  private rebuildChart(): void {
    this.chart?.destroy();
    this.chart = null;
    setTimeout(() => this.buildChart(), 0);
  }

  private buildChart(): void {
    const canvas = this.curveCanvas?.nativeElement;
    if (!canvas) return;
    this.chart?.destroy();

    const datasets = this.years
      .filter(ar => this.visibleYears.has(ar))
      .map((ar, i) => {
        const pts = this.series[ar] ?? [];
        const color = YEAR_COLORS[i % YEAR_COLORS.length];
        const isCur = ar === this.curYear;
        return {
          label: String(ar),
          data: pts.map(p => ({ x: p.dag, y: p.cum_ibc })),
          borderColor: color,
          backgroundColor: 'transparent',
          borderWidth: isCur ? 3 : 1.5,
          borderDash: isCur ? [] : [5, 3],
          pointRadius: 0,
          pointHoverRadius: 5,
          tension: 0.3,
        };
      });

    this.chart = new Chart(canvas, {
      type: 'line',
      data: { datasets } as any,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: (items) => `Dag ${items[0]?.parsed?.x ?? ''}`,
              label: (ctx) => ` ${ctx.dataset.label}: ${(ctx.parsed.y as number).toLocaleString('sv-SE')} IBC`,
            },
          },
        },
        scales: {
          x: {
            type: 'linear',
            title: { display: true, text: 'Dag på året (1–366)', color: '#a0aec0' },
            ticks: { color: '#a0aec0', stepSize: 30 },
            grid: { color: 'rgba(255,255,255,0.05)' },
            min: 1,
            max: 366,
          },
          y: {
            title: { display: true, text: 'Kumulativ IBC', color: '#a0aec0' },
            ticks: {
              color: '#a0aec0',
              callback: (v) => (v as number).toLocaleString('sv-SE'),
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  yearColor(ar: number): string {
    const idx = this.years.indexOf(ar);
    return YEAR_COLORS[idx % YEAR_COLORS.length];
  }

  atSameDayDelta(ar: number): number | null {
    if (ar === this.curYear) return null;
    const prev = this.atSameDay[ar];
    if (prev == null || prev === 0) return null;
    return Math.round((this.curAtSameDay - prev) / prev * 100);
  }

  formatIbc(n: number): string {
    return n.toLocaleString('sv-SE');
  }

  deltaClass(pct: number | null): string {
    if (pct == null) return '';
    if (pct >= 5) return 'pos';
    if (pct <= -5) return 'neg';
    return 'neu';
  }

  deltaLabel(pct: number | null): string {
    if (pct == null) return '–';
    return (pct >= 0 ? '+' : '') + pct + '%';
  }
}
