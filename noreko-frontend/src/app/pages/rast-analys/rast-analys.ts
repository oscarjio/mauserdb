import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface ShiftPoint {
  skiftraknare: number;
  datum: string;
  rasttime: number;
  rastpct: number;
  ibc_per_h: number;
  drifttid: number;
  driftstopp: number;
}

interface TeamStats {
  total_skift: number;
  skift_med_rast: number;
  avg_rast_min: number;
  avg_drifttid_min: number;
  avg_rast_pct: number;
  avg_ibc_h: number;
  ibc_h_kort_rast: number | null;
  ibc_h_lang_rast: number | null;
}

interface TrendPoint {
  label: string;
  avg_rast: number;
  count: number;
}

interface RastResponse {
  success: boolean;
  days: number;
  team: TeamStats | null;
  distribution: Record<string, number>;
  scatter: ShiftPoint[];
  trend: TrendPoint[];
  error?: string;
}

@Component({
  standalone: true,
  selector: 'app-rast-analys',
  imports: [CommonModule, FormsModule],
  templateUrl: './rast-analys.html',
  styleUrl: './rast-analys.css'
})
export class RastAnalysPage implements OnInit, OnDestroy {
  Math = Math;

  days: 30 | 60 | 90 | 180 = 90;
  readonly dayOptions: (30 | 60 | 90 | 180)[] = [30, 60, 90, 180];

  loading = false;
  error = '';

  team: TeamStats | null = null;
  distribution: Record<string, number> = {};
  scatter: ShiftPoint[] = [];
  trend: TrendPoint[] = [];

  readonly BUCKETS = ['0-15', '15-30', '30-45', '45-60', '60+'];
  readonly BUCKET_LABELS: Record<string, string> = {
    '0-15': '0–15 min',
    '15-30': '15–30 min',
    '30-45': '30–45 min',
    '45-60': '45–60 min',
    '60+': '60+ min',
  };

  private scatterChart: Chart | null = null;
  private trendChart: Chart | null = null;
  private chartTimer: any = null;
  private destroy$ = new Subject<void>();
  private isFetching = false;

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    try { this.scatterChart?.destroy(); } catch (e) {}
    try { this.trendChart?.destroy(); }   catch (e) {}
    this.scatterChart = null;
    this.trendChart   = null;
  }

  setDays(d: 30 | 60 | 90 | 180): void {
    this.days = d;
    this.load();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading    = true;
    this.error      = '';

    this.http.get<RastResponse>(
      `${environment.apiUrl}?action=rebotling&run=rast-analys&days=${this.days}`,
      { withCredentials: true }
    )
    .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe(res => {
      this.isFetching = false;
      this.loading    = false;
      if (!res?.success) {
        this.error = res?.error || 'Kunde inte ladda rastdata.';
        return;
      }
      this.team         = res.team;
      this.distribution = res.distribution ?? {};
      this.scatter      = res.scatter ?? [];
      this.trend        = res.trend ?? [];

      if (this.chartTimer) clearTimeout(this.chartTimer);
      this.chartTimer = setTimeout(() => {
        if (!this.destroy$.closed) {
          this.buildScatterChart();
          this.buildTrendChart();
        }
      }, 100);
    });
  }

  get bucketTotal(): number {
    return Object.values(this.distribution).reduce<number>((a, b) => a + (b ?? 0), 0);
  }

  bucketPct(key: string): number {
    const total = this.bucketTotal;
    if (total === 0) return 0;
    return Math.round(((this.distribution[key] ?? 0) / total) * 100);
  }

  bucketBarWidth(key: string): string {
    const vals = Object.values(this.distribution).map(v => v ?? 0);
    const max = vals.length > 0 ? Math.max(...vals) : 0;
    if (max === 0) return '0%';
    return Math.round(((this.distribution[key] ?? 0) / max) * 100) + '%';
  }

  ibcDelta(): number | null {
    if (!this.team?.ibc_h_kort_rast || !this.team?.ibc_h_lang_rast) return null;
    return +(this.team.ibc_h_kort_rast - this.team.ibc_h_lang_rast).toFixed(2);
  }

  ibcDeltaClass(): string {
    const d = this.ibcDelta();
    if (d === null) return '';
    if (d > 0.5) return 'kpi-good';
    if (d < -0.5) return 'kpi-bad';
    return 'kpi-neutral';
  }

  private buildScatterChart(): void {
    try { this.scatterChart?.destroy(); } catch (e) {}
    this.scatterChart = null;

    const canvas = document.getElementById('rastScatterChart') as HTMLCanvasElement;
    if (!canvas || this.scatter.length === 0) return;

    const points = this.scatter.map(p => ({ x: p.rasttime, y: p.ibc_per_h }));

    this.scatterChart = new Chart(canvas, {
      type: 'scatter',
      data: {
        datasets: [{
          label: 'Skift (rast vs IBC/h)',
          data: points,
          backgroundColor: 'rgba(66,153,225,0.45)',
          borderColor: '#4299e1',
          borderWidth: 1,
          pointRadius: 5,
          pointHoverRadius: 7,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0' } },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => {
                const raw = ctx.raw as { x: number; y: number };
                const p   = this.scatter[ctx.dataIndex];
                return [
                  ` Rast: ${raw.x} min`,
                  ` IBC/h: ${raw.y.toFixed(1)}`,
                  p ? ` Datum: ${p.datum}` : '',
                ].filter(Boolean);
              }
            }
          }
        },
        scales: {
          x: {
            title: { display: true, text: 'Rasttid (min)', color: '#8fa3b8', font: { size: 11 } },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.06)' },
          },
          y: {
            beginAtZero: true,
            title: { display: true, text: 'IBC / timme', color: '#8fa3b8', font: { size: 11 } },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.07)' },
          }
        }
      }
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (e) {}
    this.trendChart = null;

    const canvas = document.getElementById('rastTrendChart') as HTMLCanvasElement;
    if (!canvas || this.trend.length === 0) return;

    const labels = this.trend.map(t => t.label);
    const values = this.trend.map(t => t.avg_rast);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Snitt rasttid (min)',
          data: values,
          borderColor: '#f6ad55',
          backgroundColor: 'rgba(246,173,85,0.15)',
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointBackgroundColor: '#f6ad55',
          borderWidth: 2.5,
          spanGaps: true,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0' } },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => ` Snitt: ${ctx.parsed.y != null ? ctx.parsed.y.toFixed(1) : '–'} min`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid:  { color: 'rgba(255,255,255,0.06)' },
          },
          y: {
            beginAtZero: true,
            title: { display: true, text: 'Rasttid (min)', color: '#8fa3b8', font: { size: 11 } },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.07)' },
          }
        }
      }
    });
  }

  trackByIndex(index: number): number { return index; }
}
