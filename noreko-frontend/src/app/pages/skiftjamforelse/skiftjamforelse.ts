import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  SkiftjamforelseService,
  SammanfattningData,
  JamforelseData,
  TrendData,
  BestPracticesData,
  DetaljerData,
  SkiftRow,
  DetaljRow,
} from '../../services/skiftjamforelse.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-skiftjamforelse',
  templateUrl: './skiftjamforelse.html',
  styleUrls: ['./skiftjamforelse.css'],
  imports: [CommonModule, FormsModule],
})
export class SkiftjamforelseComponent implements OnInit, OnDestroy {

  selectedPeriod: number = 30;
  perioder: number[] = [7, 30, 90];

  loading = true;
  error = false;

  sammanfattning: SammanfattningData | null = null;
  jamforelse: JamforelseData | null = null;
  trendData: TrendData | null = null;
  bestPractices: BestPracticesData | null = null;
  detaljer: DetaljerData | null = null;

  // Detaljtabell sortering
  detaljSort: string = 'datum';
  detaljSortDir: number = -1;

  lastRefreshed: Date | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private radarChart: Chart | null = null;
  private trendChart: Chart | null = null;

  constructor(private service: SkiftjamforelseService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.radarChart?.destroy(); } catch (_) {}
    try { this.trendChart?.destroy(); } catch (_) {}
    this.radarChart = null;
    this.trendChart = null;
  }

  setPeriod(period: number): void {
    if (this.selectedPeriod === period) return;
    this.selectedPeriod = period;
    this.sammanfattning = null;
    this.jamforelse = null;
    this.trendData = null;
    this.bestPractices = null;
    this.detaljer = null;
    this.loading = true;
    this.loadAll();
  }

  private loadAll(): void {
    this.error = false;
    const d = this.selectedPeriod;

    this.service.getSammanfattning(d)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.sammanfattning = res?.success ? res.data : null;
        this.lastRefreshed = new Date();
      });

    this.service.getJamforelse(d)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.jamforelse = res?.success ? res.data : null;
        this.loading = false;
        if (!this.jamforelse) this.error = true;
        if (this.jamforelse) {
          setTimeout(() => { if (!this.destroy$.closed) this.renderRadarChart(); }, 150);
        }
      });

    this.service.getTrend(d)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.trendData = res?.success ? res.data : null;
        if (this.trendData) {
          setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 150);
        }
      });

    this.service.getBestPractices(d)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.bestPractices = res?.success ? res.data : null;
      });

    this.service.getDetaljer(d)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.detaljer = res?.success ? res.data : null;
      });
  }

  // ================================================================
  // RADAR CHART
  // ================================================================

  private renderRadarChart(): void {
    try { this.radarChart?.destroy(); } catch (_) {}
    this.radarChart = null;

    const canvas = document.getElementById('skiftRadarChart') as HTMLCanvasElement;
    if (!canvas || !this.jamforelse?.radar) return;

    const radar = this.jamforelse.radar;
    const labels = ['Tillganglighet', 'Prestanda', 'Kvalitet', 'Volym', 'Stabilitet'];

    const toArr = (r: any) => [r.tillganglighet, r.prestanda, r.kvalitet, r.volym, r.stabilitet];

    this.radarChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels,
        datasets: [
          {
            label: 'FM',
            data: radar.FM ? toArr(radar.FM) : [0,0,0,0,0],
            borderColor: '#3182ce',
            backgroundColor: 'rgba(49, 130, 206, 0.15)',
            pointBackgroundColor: '#3182ce',
            borderWidth: 2,
          },
          {
            label: 'EM',
            data: radar.EM ? toArr(radar.EM) : [0,0,0,0,0],
            borderColor: '#ed8936',
            backgroundColor: 'rgba(237, 137, 54, 0.15)',
            pointBackgroundColor: '#ed8936',
            borderWidth: 2,
          },
          {
            label: 'Natt',
            data: radar.Natt ? toArr(radar.Natt) : [0,0,0,0,0],
            borderColor: '#b794f4',
            backgroundColor: 'rgba(183, 148, 244, 0.15)',
            pointBackgroundColor: '#b794f4',
            borderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
        },
        scales: {
          r: {
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0', backdropColor: 'transparent', stepSize: 20 },
            grid: { color: 'rgba(255,255,255,0.08)' },
            pointLabels: { color: '#e2e8f0', font: { size: 12 } },
            angleLines: { color: 'rgba(255,255,255,0.08)' },
          },
        },
      },
    });
  }

  // ================================================================
  // TREND CHART
  // ================================================================

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('skiftTrendOeeChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData?.trend) return;

    const points = this.trendData.trend;
    const labels = points.map(p => {
      const d = new Date(p.datum);
      return `${d.getDate()}/${d.getMonth() + 1}`;
    });

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'FM',
            data: points.map(p => p.FM),
            borderColor: '#3182ce',
            backgroundColor: 'rgba(49, 130, 206, 0.1)',
            pointBackgroundColor: '#3182ce',
            pointRadius: 2,
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: 'EM',
            data: points.map(p => p.EM),
            borderColor: '#ed8936',
            backgroundColor: 'rgba(237, 137, 54, 0.1)',
            pointBackgroundColor: '#ed8936',
            pointRadius: 2,
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: 'Natt',
            data: points.map(p => p.Natt),
            borderColor: '#b794f4',
            backgroundColor: 'rgba(183, 148, 244, 0.1)',
            pointBackgroundColor: '#b794f4',
            pointRadius: 2,
            tension: 0.3,
            spanGaps: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y ?? '-'}% OEE`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
            max: 100,
            title: { display: true, text: 'OEE %', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ================================================================
  // TABLE HELPERS
  // ================================================================

  getBestValue(field: string): number {
    if (!this.jamforelse?.skift) return 0;
    const vals = this.jamforelse.skift.map((s: SkiftRow) => (s as any)[field] ?? 0);
    if (field === 'stopptid_min' || field === 'avg_cykeltid_sek') return Math.min(...vals);
    return Math.max(...vals);
  }

  getWorstValue(field: string): number {
    if (!this.jamforelse?.skift) return 0;
    const vals = this.jamforelse.skift.map((s: SkiftRow) => (s as any)[field] ?? 0);
    if (field === 'stopptid_min' || field === 'avg_cykeltid_sek') return Math.max(...vals);
    return Math.min(...vals);
  }

  getCellClass(value: number, field: string): string {
    const best = this.getBestValue(field);
    const worst = this.getWorstValue(field);
    if (value === best && best !== worst) return 'cell-best';
    if (value === worst && best !== worst) return 'cell-worst';
    return '';
  }

  getSkiftColor(skift: string): string {
    if (skift === 'FM') return '#3182ce';
    if (skift === 'EM') return '#ed8936';
    return '#b794f4';
  }

  getSkiftIcon(skift: string): string {
    if (skift === 'FM') return 'fas fa-sun';
    if (skift === 'EM') return 'fas fa-cloud-sun';
    return 'fas fa-moon';
  }

  getOeeKlass(pct: number): string {
    if (pct >= 85) return 'text-success';
    if (pct >= 60) return 'text-info';
    if (pct >= 40) return 'text-warning';
    return 'text-danger';
  }

  formatMinuter(min: number): string {
    if (!min || min <= 0) return '0 min';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  getPeriodLabel(): string {
    if (this.selectedPeriod === 7) return 'Senaste 7 dagarna';
    if (this.selectedPeriod === 30) return 'Senaste 30 dagarna';
    return 'Senaste 90 dagarna';
  }

  // Detaljtabell sortering
  sortDetaljer(col: string): void {
    if (this.detaljSort === col) {
      this.detaljSortDir *= -1;
    } else {
      this.detaljSort = col;
      this.detaljSortDir = -1;
    }
  }

  getSortedDetaljer(): DetaljRow[] {
    if (!this.detaljer?.detaljer) return [];
    const rows = [...this.detaljer.detaljer];
    const col = this.detaljSort;
    const dir = this.detaljSortDir;
    rows.sort((a: any, b: any) => {
      if (a[col] < b[col]) return -1 * dir;
      if (a[col] > b[col]) return 1 * dir;
      return 0;
    });
    return rows;
  }

  getSortIcon(col: string): string {
    if (this.detaljSort !== col) return 'fas fa-sort';
    return this.detaljSortDir > 0 ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }
}
