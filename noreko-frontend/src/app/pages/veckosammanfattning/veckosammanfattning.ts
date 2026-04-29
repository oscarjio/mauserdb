import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface DayData {
  datum: string;
  dow: string;
  ibc_ok: number;
  ibc_per_h: number;
  kass_pct: number;
  stopp_pct: number;
  antal_skift: number;
}

interface OperatorRow {
  number: number;
  name: string;
  ibc_ok: number;
  ibc_per_h: number;
  antal_skift: number;
}

interface Kpi {
  ibc_ok: number;
  ibc_per_h: number;
  kass_pct: number;
  stopp_pct: number;
  antal_skift: number;
  timmar: number;
}

interface BestShift {
  datum: string;
  skiftnr: number;
  ibc_ok: number;
  ibc_per_h: number;
  operators: string;
}

interface ApiResponse {
  success: boolean;
  year: number;
  week: number;
  from: string;
  to: string;
  kpi: Kpi | null;
  prev_kpi: Kpi | null;
  days: DayData[];
  operators: OperatorRow[];
  best_shift: BestShift | null;
}

@Component({
  standalone: true,
  selector: 'app-veckosammanfattning',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './veckosammanfattning.html',
  styleUrl: './veckosammanfattning.css'
})
export class VeckosammanfattningPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;
  @ViewChild('barCanvas', { static: false }) barCanvas!: ElementRef<HTMLCanvasElement>;
  Math = Math;

  loading = false;
  error = '';

  year = new Date().getFullYear();
  week = this.isoWeek(new Date());

  data: ApiResponse | null = null;

  get weekRange(): string {
    if (!this.data) return '';
    return `${this.formatDate(this.data.from)} – ${this.formatDate(this.data.to)}`;
  }

  get prevWeek(): number { return this.week === 1 ? 52 : this.week - 1; }
  get prevYear(): number { return this.week === 1 ? this.year - 1 : this.year; }
  get nextWeek(): number { return this.week >= 52 ? 1 : this.week + 1; }
  get nextYear(): number { return this.week >= 52 ? this.year + 1 : this.year; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
  }

  isoWeek(d: Date): number {
    const date = new Date(d.getTime());
    date.setHours(0, 0, 0, 0);
    date.setDate(date.getDate() + 3 - (date.getDay() + 6) % 7);
    const week1 = new Date(date.getFullYear(), 0, 4);
    return 1 + Math.round(((date.getTime() - week1.getTime()) / 86400000 - 3 + (week1.getDay() + 6) % 7) / 7);
  }

  navigatePrev(): void {
    this.year = this.prevYear;
    this.week = this.prevWeek;
    this.load();
  }

  navigateNext(): void {
    this.year = this.nextYear;
    this.week = this.nextWeek;
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = '';
    this.chart?.destroy();
    this.chart = null;

    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=veckosammanfattning&year=${this.year}&week=${this.week}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda veckodata.'; return; }
        this.data = res;
        setTimeout(() => this.buildChart(), 50);
      });
  }

  private buildChart(): void {
    if (!this.barCanvas || !this.data?.days?.length) return;
    this.chart?.destroy();

    const days = this.data.days;
    const avgIbch = this.data.kpi?.ibc_per_h ?? 0;

    this.chart = new Chart(this.barCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: days.map(d => `${d.dow} ${d.datum.slice(5)}`),
        datasets: [{
          label: 'IBC/h',
          data: days.map(d => d.ibc_per_h),
          backgroundColor: days.map(d =>
            d.ibc_per_h >= avgIbch * 1.05 ? '#68d391' :
            d.ibc_per_h >= avgIbch * 0.95 ? '#76e4f7' :
            d.ibc_per_h >= avgIbch * 0.85 ? '#f6ad55' : '#fc8181'
          ),
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              afterBody: (items) => {
                const d = days[items[0].dataIndex];
                return [`IBC: ${d.ibc_ok}`, `Kassation: ${d.kass_pct}%`, `Stopp: ${d.stopp_pct}%`, `Skift: ${d.antal_skift}`];
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(255,255,255,0.05)' },
            ticks: { color: '#a0aec0' },
          },
          x: { ticks: { color: '#a0aec0' }, grid: { display: false } }
        }
      }
    });
  }

  delta(current: number | undefined, prev: number | undefined): number {
    if (current == null || prev == null || prev === 0) return 0;
    return Math.round((current - prev) / prev * 100 * 10) / 10;
  }

  deltaAbs(current: number | undefined, prev: number | undefined): number {
    if (current == null || prev == null) return 0;
    return Math.round((current - prev) * 10) / 10;
  }

  deltaColor(d: number): string {
    if (d > 1) return '#68d391';
    if (d < -1) return '#fc8181';
    return '#a0aec0';
  }

  deltaColorInverse(d: number): string {
    return this.deltaColor(-d);
  }

  formatDate(s: string): string {
    if (!s) return '';
    const [y, m, d] = s.split('-');
    return `${d}/${m}`;
  }

  weekDayColor(ibch: number): string {
    const avg = this.data?.kpi?.ibc_per_h ?? 0;
    if (!avg) return '#2d3748';
    const ratio = ibch / avg;
    if (ratio >= 1.05) return 'rgba(104,211,145,0.15)';
    if (ratio >= 0.95) return 'rgba(118,228,247,0.1)';
    if (ratio >= 0.85) return 'rgba(246,173,85,0.15)';
    return 'rgba(252,129,129,0.15)';
  }

  opRankColor(i: number): string {
    if (i === 0) return '#f6c90e';
    if (i === 1) return '#a0aec0';
    if (i === 2) return '#c69c6d';
    return '#4a5568';
  }
}
