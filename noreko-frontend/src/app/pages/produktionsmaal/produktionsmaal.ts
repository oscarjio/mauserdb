import { Component, OnInit, OnDestroy, ElementRef, ViewChild, AfterViewInit } from '@angular/core';
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
  ibc: number;
  cumulative: number;
  antal_skift: number;
}

interface MaalResponse {
  success: boolean;
  period: string;
  from: string;
  to: string;
  today: string;
  daily: DayData[];
  actual_ibc: number;
  projected_ibc: number;
  pace_ibc_day: number;
  days_elapsed: number;
  days_left: number;
  total_days: number;
}

@Component({
  standalone: true,
  selector: 'app-produktionsmaal',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produktionsmaal.html',
  styleUrl: './produktionsmaal.css'
})
export class ProduktionsmaalPage implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('chartCanvas') chartCanvas!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;

  loading = false;
  error = '';

  period: 'week' | 'month' | 'quarter' = 'month';
  targetInput = '';
  target = 0;

  from = '';
  to = '';
  today = '';
  daily: DayData[] = [];
  actualIbc = 0;
  projectedIbc = 0;
  paceIbcDay = 0;
  daysElapsed = 0;
  daysLeft = 0;
  totalDays = 0;
  loaded = false;

  Math = Math;

  private readonly STORAGE_KEYS: Record<string, string> = {
    week: 'produktionsmaal_vecka',
    month: 'produktionsmaal_manad',
    quarter: 'produktionsmaal_kvartal',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadTarget();
    this.fetch();
  }

  ngAfterViewInit(): void {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
  }

  private loadTarget(): void {
    const key = this.STORAGE_KEYS[this.period];
    const saved = localStorage.getItem(key);
    if (saved) {
      this.target = parseInt(saved, 10) || 0;
      this.targetInput = this.target > 0 ? String(this.target) : '';
    } else {
      this.target = 0;
      this.targetInput = '';
    }
  }

  private saveTarget(): void {
    const key = this.STORAGE_KEYS[this.period];
    if (this.target > 0) {
      localStorage.setItem(key, String(this.target));
    } else {
      localStorage.removeItem(key);
    }
  }

  setPeriod(p: 'week' | 'month' | 'quarter'): void {
    if (this.period === p) return;
    this.period = p;
    this.loadTarget();
    this.fetch();
  }

  applyTarget(): void {
    const v = parseInt(this.targetInput, 10);
    this.target = isNaN(v) || v <= 0 ? 0 : v;
    this.saveTarget();
    if (this.loaded) this.buildChart();
  }

  setPreset(n: number): void {
    this.target = n;
    this.targetInput = String(n);
    this.saveTarget();
    if (this.loaded) this.buildChart();
  }

  fetch(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.loaded = false;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=produktionsmaal&period=${this.period}`;
    this.http.get<MaalResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta produktionsdata.';
        return;
      }
      this.from         = res.from;
      this.to           = res.to;
      this.today        = res.today;
      this.daily        = res.daily;
      this.actualIbc    = res.actual_ibc;
      this.projectedIbc = res.projected_ibc;
      this.paceIbcDay   = res.pace_ibc_day;
      this.daysElapsed  = res.days_elapsed;
      this.daysLeft     = res.days_left;
      this.totalDays    = res.total_days;
      this.loaded = true;
      setTimeout(() => this.buildChart(), 50);
    });
  }

  get progressPct(): number {
    if (this.target <= 0) return 0;
    return Math.min(100, Math.round(this.actualIbc / this.target * 100));
  }

  get projectedPct(): number {
    if (this.target <= 0) return 0;
    return Math.round(this.projectedIbc / this.target * 100);
  }

  get onTrack(): boolean {
    return this.target > 0 && this.projectedIbc >= this.target;
  }

  get achieved(): boolean {
    return this.target > 0 && this.actualIbc >= this.target;
  }

  get progressColor(): string {
    if (this.achieved) return '#f6c90e';
    if (this.onTrack) return '#68d391';
    if (this.projectedPct >= 90) return '#f6ad55';
    return '#fc8181';
  }

  get neededPerDay(): number {
    if (this.target <= 0 || this.daysLeft <= 0) return 0;
    return Math.max(0, Math.ceil((this.target - this.actualIbc) / this.daysLeft));
  }

  get statusLabel(): string {
    if (this.target <= 0) return 'Inget mål satt';
    if (this.achieved) return 'Mål uppnått! ';
    if (this.onTrack) return 'I tid — prognos når målet';
    if (this.projectedPct >= 90) return 'Nära — risk för underskott';
    return 'Bakom — öka takten';
  }

  get statusClass(): string {
    if (this.achieved) return 'status-gold';
    if (this.onTrack) return 'status-green';
    if (this.projectedPct >= 90) return 'status-yellow';
    return 'status-red';
  }

  get periodLabel(): string {
    if (this.period === 'week') return 'vecka';
    if (this.period === 'quarter') return 'kvartal';
    return 'månad';
  }

  get periodLabelCap(): string {
    if (this.period === 'week') return 'Vecka';
    if (this.period === 'quarter') return 'Kvartal';
    return 'Månad';
  }

  private generateDateRange(from: string, to: string): string[] {
    const dates: string[] = [];
    const end = new Date(to);
    const cur = new Date(from);
    while (cur <= end) {
      dates.push(cur.toISOString().substring(0, 10));
      cur.setDate(cur.getDate() + 1);
    }
    return dates;
  }

  private buildChart(): void {
    if (!this.chartCanvas) return;

    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }

    const dates = this.generateDateRange(this.from, this.to);
    const actualMap = new Map<string, number>();
    for (const d of this.daily) {
      actualMap.set(d.datum, d.cumulative);
    }

    // Build actual cumulative — carry forward on days without production
    const actualData: (number | null)[] = [];
    let lastCum = 0;
    for (const d of dates) {
      if (d <= this.today) {
        const val = actualMap.get(d);
        if (val !== undefined) lastCum = val;
        actualData.push(lastCum);
      } else {
        actualData.push(null);
      }
    }

    // Build projected line — only future days, starting from today's actual
    const todayIdx = dates.indexOf(this.today);
    const projectedData: (number | null)[] = dates.map((d, i) => {
      if (i < todayIdx) return null;
      const daysFromToday = i - todayIdx;
      return Math.round(lastCum + this.paceIbcDay * daysFromToday);
    });

    // Target line
    const targetData: (number | null)[] = this.target > 0
      ? dates.map(() => this.target)
      : [];

    const labels = dates.map(d => {
      const parts = d.split('-');
      return `${parts[2]}/${parts[1]}`;
    });

    const datasets: any[] = [
      {
        label: 'Faktisk (kumulativ)',
        data: actualData,
        borderColor: '#63b3ed',
        backgroundColor: 'rgba(99,179,237,0.1)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        spanGaps: false,
      },
      {
        label: 'Prognos',
        data: projectedData,
        borderColor: '#f6ad55',
        borderDash: [6, 4],
        backgroundColor: 'transparent',
        tension: 0.3,
        pointRadius: 0,
        spanGaps: false,
      },
    ];

    if (this.target > 0) {
      datasets.push({
        label: 'Mål',
        data: targetData,
        borderColor: '#68d391',
        borderDash: [4, 4],
        backgroundColor: 'transparent',
        pointRadius: 0,
        borderWidth: 2,
      });
    }

    this.chart = new Chart(this.chartCanvas.nativeElement, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' },
          },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y?.toLocaleString('sv-SE')} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              maxRotation: 45,
              autoSkip: true,
              maxTicksLimit: 14,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: {
              color: '#a0aec0',
              callback: v => v?.toLocaleString('sv-SE'),
            },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Kumulativ IBC', color: '#a0aec0' },
          },
        },
      },
    });
  }
}
