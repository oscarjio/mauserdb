import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

interface MonthData {
  ibc_per_h: number;
  skift_count: number;
  total_ibc: number;
}

interface ApiResponse {
  success: boolean;
  monthly_avg: { [month: number]: MonthData };
  by_year: { [year: number]: { [month: number]: MonthData } };
  years: number[];
  period_avg: number;
  error?: string;
}

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];

@Component({
  standalone: true,
  selector: 'app-sasongsanalys',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './sasongsanalys.html',
  styleUrl: './sasongsanalys.css',
})
export class SasongsanalysPage implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('barChartRef') barChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('lineChartRef') lineChartRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private isFetching = false;
  private barChart: Chart | null = null;
  private lineChart: Chart | null = null;

  loading = false;
  error = '';
  viewReady = false;

  monthlyAvg: { [month: number]: MonthData } = {};
  byYear: { [year: number]: { [month: number]: MonthData } } = {};
  years: number[] = [];
  periodAvg = 0;

  readonly MONTHS = MONTH_NAMES;
  readonly MONTH_NUMS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
  Object = Object;

  get bestMonth(): { name: string; ibch: number } | null {
    let best: { man: number; ibch: number } | null = null;
    for (const [k, v] of Object.entries(this.monthlyAvg)) {
      if (!best || v.ibc_per_h > best.ibch) best = { man: +k, ibch: v.ibc_per_h };
    }
    return best ? { name: MONTH_NAMES[best.man - 1], ibch: best.ibch } : null;
  }

  get worstMonth(): { name: string; ibch: number } | null {
    let worst: { man: number; ibch: number } | null = null;
    for (const [k, v] of Object.entries(this.monthlyAvg)) {
      if (!worst || v.ibc_per_h < worst.ibch) worst = { man: +k, ibch: v.ibc_per_h };
    }
    return worst ? { name: MONTH_NAMES[worst.man - 1], ibch: worst.ibch } : null;
  }

  get seasonalSpread(): number {
    if (!this.bestMonth || !this.worstMonth) return 0;
    return +(this.bestMonth.ibch - this.worstMonth.ibch).toFixed(2);
  }

  get dataSpan(): string {
    if (!this.years.length) return '—';
    if (this.years.length === 1) return String(this.years[0]);
    return `${this.years[0]}–${this.years[this.years.length - 1]}`;
  }

  heatColor(ibch: number | undefined): string {
    if (ibch === undefined || ibch === 0) return '#2d3748';
    const delta = (ibch - this.periodAvg) / Math.max(this.periodAvg, 1) * 100;
    if (delta >= 20) return '#22543d';
    if (delta >= 10) return '#276749';
    if (delta >= 3) return '#2f855a';
    if (delta >= -3) return '#4a5568';
    if (delta >= -10) return '#744210';
    if (delta >= -20) return '#7b341e';
    return '#63171b';
  }

  heatTextColor(ibch: number | undefined): string {
    if (ibch === undefined || ibch === 0) return '#718096';
    const delta = (ibch - this.periodAvg) / Math.max(this.periodAvg, 1) * 100;
    return Math.abs(delta) >= 3 ? '#e2e8f0' : '#a0aec0';
  }

  barColor(ibch: number): string {
    const delta = (ibch - this.periodAvg) / Math.max(this.periodAvg, 1) * 100;
    if (delta >= 5) return '#48bb78';
    if (delta >= -5) return '#63b3ed';
    return '#fc8181';
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (Object.keys(this.monthlyAvg).length) this.renderCharts();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.barChart?.destroy();
    this.lineChart?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=sasongsanalys`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = res?.error ?? 'Kunde inte ladda säsongsdata.';
          return;
        }
        this.monthlyAvg = res.monthly_avg ?? {};
        this.byYear = res.by_year ?? {};
        this.years = res.years ?? [];
        this.periodAvg = res.period_avg ?? 0;
        if (this.viewReady) this.renderCharts();
      });
  }

  private renderCharts(): void {
    this.renderBarChart();
    this.renderLineChart();
  }

  private renderBarChart(): void {
    this.barChart?.destroy();
    const canvas = this.barChartRef?.nativeElement;
    if (!canvas) return;
    const labels = MONTH_NAMES;
    const data = this.MONTH_NUMS.map(m => this.monthlyAvg[m]?.ibc_per_h ?? 0);
    const colors = data.map(v => this.barColor(v));
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data,
            backgroundColor: colors,
            borderRadius: 4,
          },
          {
            label: 'Periodsnitt',
            data: Array(12).fill(this.periodAvg),
            type: 'line',
            borderColor: '#a0aec0',
            borderDash: [6, 3],
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
          } as any,
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              afterLabel: (ctx) => {
                const m = ctx.dataIndex + 1;
                const d = this.monthlyAvg[m];
                return d ? `${d.skift_count} skift · ${d.total_ibc} IBC` : '';
              },
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
        },
      },
    });
  }

  private renderLineChart(): void {
    this.lineChart?.destroy();
    const canvas = this.lineChartRef?.nativeElement;
    if (!canvas || !this.years.length) return;

    const YEAR_COLORS = ['#63b3ed', '#68d391', '#f6ad55', '#fc8181', '#b794f4', '#76e4f7'];
    const datasets = this.years.map((yr, i) => ({
      label: String(yr),
      data: this.MONTH_NUMS.map(m => this.byYear[yr]?.[m]?.ibc_per_h ?? null),
      borderColor: YEAR_COLORS[i % YEAR_COLORS.length],
      backgroundColor: 'transparent',
      borderWidth: 2,
      pointRadius: 3,
      spanGaps: true,
    }));

    this.lineChart = new Chart(canvas, {
      type: 'line',
      data: { labels: MONTH_NAMES, datasets },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
        },
      },
    });
  }
}
