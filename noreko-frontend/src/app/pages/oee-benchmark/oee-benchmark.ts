import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  OeeBenchmarkService,
  CurrentOeeData,
  BenchmarkData,
  BenchmarkItem,
  TrendData,
  TrendPoint,
  BreakdownData,
  FaktorItem,
} from '../../services/oee-benchmark.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-oee-benchmark',
  templateUrl: './oee-benchmark.html',
  styleUrls: ['./oee-benchmark.css'],
  imports: [CommonModule, FormsModule],
})
export class OeeBenchmarkComponent implements OnInit, OnDestroy, AfterViewInit {
  // Periodselektor
  days: number = 30;
  readonly periodOptions = [7, 14, 30, 90];

  // Laddningstillstånd
  oeeLoading     = false;
  oeeLoaded      = false;
  benchLoading   = false;
  benchLoaded    = false;
  trendLoading   = false;
  trendLoaded    = false;
  breakLoading   = false;
  breakLoaded    = false;

  // Data
  oeeData:   CurrentOeeData | null = null;
  benchData: BenchmarkData  | null = null;
  trendData: TrendData      | null = null;
  breakData: BreakdownData  | null = null;

  // Charts
  private gaugeChart: Chart | null = null;
  private trendChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private viewReady = false;
  private gaugeTimer: ReturnType<typeof setTimeout> | null = null;
  private trendTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: OeeBenchmarkService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (this.oeeLoaded && this.oeeData) {
      if (this.gaugeTimer) clearTimeout(this.gaugeTimer);
      this.gaugeTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderGauge(); }, 50);
    }
    if (this.trendLoaded && this.trendData) {
      if (this.trendTimer) clearTimeout(this.trendTimer);
      this.trendTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 50);
    }
  }

  ngOnDestroy(): void {
    if (this.gaugeTimer) { clearTimeout(this.gaugeTimer); this.gaugeTimer = null; }
    if (this.trendTimer) { clearTimeout(this.trendTimer); this.trendTimer = null; }
    try { this.gaugeChart?.destroy(); } catch (_e) { /* ignore */ }
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    this.gaugeChart = null;
    this.trendChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  onDaysChange(): void {
    this.oeeLoaded   = false;
    this.benchLoaded = false;
    this.trendLoaded = false;
    this.breakLoaded = false;
    try { this.gaugeChart?.destroy(); } catch (_e) { /* ignore */ }
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    this.gaugeChart = null;
    this.trendChart = null;
    this.loadAll();
  }

  private loadAll(): void {
    this.loadOee();
    this.loadBenchmark();
    this.loadTrend();
    this.loadBreakdown();
  }

  // ----------------------------------------------------------------
  // Laddning
  // ----------------------------------------------------------------

  private loadOee(): void {
    if (this.oeeLoading) return;
    this.oeeLoading = true;
    this.service.getCurrentOee(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.oeeLoading = false;
        this.oeeData = res?.success ? res.data : null;
        this.oeeLoaded = true;
        if (this.viewReady && this.oeeData) {
          if (this.gaugeTimer) clearTimeout(this.gaugeTimer);
          this.gaugeTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderGauge(); }, 50);
        }
      });
  }

  private loadBenchmark(): void {
    if (this.benchLoading) return;
    this.benchLoading = true;
    this.service.getBenchmark(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.benchLoading = false;
        this.benchData = res?.success ? res.data : null;
        this.benchLoaded = true;
      });
  }

  private loadTrend(): void {
    if (this.trendLoading) return;
    this.trendLoading = true;
    this.service.getTrend(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.trendLoading = false;
        this.trendData = res?.success ? res.data : null;
        this.trendLoaded = true;
        if (this.viewReady && this.trendData) {
          if (this.trendTimer) clearTimeout(this.trendTimer);
          this.trendTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 50);
        }
      });
  }

  private loadBreakdown(): void {
    if (this.breakLoading) return;
    this.breakLoading = true;
    this.service.getBreakdown(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.breakLoading = false;
        this.breakData = res?.success ? res.data : null;
        this.breakLoaded = true;
      });
  }

  // ----------------------------------------------------------------
  // Chart: OEE Gauge (Doughnut)
  // ----------------------------------------------------------------

  private renderGauge(): void {
    try { this.gaugeChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('oeeGaugeChart') as HTMLCanvasElement;
    if (!canvas || !this.oeeData) return;

    const pct   = this.oeeData.oee_pct;
    const rest  = Math.max(0, 100 - pct);
    const color = this.getGaugeColor(pct);

    if (this.gaugeChart) { (this.gaugeChart as any).destroy(); }
    this.gaugeChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [pct, rest],
          backgroundColor: [color, 'rgba(45,55,72,0.6)'],
          borderWidth: 0,
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        rotation: -90,
        circumference: 180,
        plugins: {
          legend:  { display: false },
          tooltip: { enabled: false },
        },
      },
    });
  }

  // ----------------------------------------------------------------
  // Chart: Trend-linjediagram
  // ----------------------------------------------------------------

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('oeeTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData) return;

    const labels = this.trendData.trend.map((p: TrendPoint) => this.formatDatum(p.datum));
    const oeeVarden = this.trendData.trend.map((p: TrendPoint) => p.oee_pct);
    const wcLine    = this.trendData.trend.map(() => this.trendData!.world_class_pct);
    const typLine   = this.trendData.trend.map(() => this.trendData!.typical_pct);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE (%)',
            data: oeeVarden,
            borderColor: 'rgba(66, 153, 225, 0.9)',
            backgroundColor: 'rgba(66, 153, 225, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: this.days <= 14 ? 4 : 2,
            pointHoverRadius: 6,
          },
          {
            label: 'World Class (85%)',
            data: wcLine,
            borderColor: 'rgba(72, 187, 120, 0.7)',
            backgroundColor: 'transparent',
            fill: false,
            borderDash: [6, 4],
            pointRadius: 0,
            tension: 0,
          },
          {
            label: 'Branschsnitt (60%)',
            data: typLine,
            borderColor: 'rgba(237, 137, 54, 0.7)',
            backgroundColor: 'transparent',
            fill: false,
            borderDash: [4, 4],
            pointRadius: 0,
            tension: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 12 }, boxWidth: 16, padding: 16 },
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              label: (item: any) => `${item.dataset.label}: ${item.raw}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 }, maxRotation: 45 },
            grid:  { color: 'rgba(255,255,255,0.04)' },
          },
          y: {
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v: any) => `${v}%` },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'OEE (%)', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ----------------------------------------------------------------
  // Hjälpmetoder
  // ----------------------------------------------------------------

  getGaugeColor(pct: number): string {
    if (pct >= 85) return 'rgba(56, 178, 172, 0.9)';   // teal  — world class
    if (pct >= 60) return 'rgba(72, 187, 120, 0.9)';   // grön  — bra
    if (pct >= 40) return 'rgba(237, 137, 54, 0.9)';   // gul   — typiskt
    return 'rgba(245, 101, 101, 0.9)';                  // röd   — lågt
  }

  getOeeStatusText(status: string): string {
    const map: Record<string, string> = {
      'world-class': 'World Class',
      'bra':         'Bra',
      'typiskt':     'Under branschsnitt',
      'lågt':        'Kritiskt lågt',
    };
    return map[status] ?? status;
  }

  getStatusBadgeClass(status: string): string {
    const map: Record<string, string> = {
      'world-class': 'badge-teal',
      'bra':         'badge-green',
      'typiskt':     'badge-yellow',
      'lågt':        'badge-red',
    };
    return map[status] ?? 'badge-secondary';
  }

  getProgressColor(pct: number): string {
    if (pct >= 85) return '#38b2ac';
    if (pct >= 60) return '#48bb78';
    if (pct >= 40) return '#ed8936';
    return '#f56565';
  }

  getTrendIcon(trend: string): string {
    if (trend === 'up')   return 'fas fa-arrow-up text-success';
    if (trend === 'down') return 'fas fa-arrow-down text-danger';
    return 'fas fa-minus text-muted';
  }

  getTrendText(current: number, prev: number): string {
    const diff = current - prev;
    if (Math.abs(diff) < 0.1) return 'Oförändrat';
    const sign = diff > 0 ? '+' : '';
    return `${sign}${diff.toFixed(1)}% vs föreg. period`;
  }

  getFaktorIcon(faktor: FaktorItem): string {
    return `fas ${faktor.icon}`;
  }

  getFaktorColorClass(color: string): string {
    const map: Record<string, string> = {
      blue:   'text-info',
      purple: 'text-primary',
      green:  'text-success',
      teal:   'text-teal',
    };
    return map[color] ?? 'text-muted';
  }

  getGapLabel(gap: number): string {
    if (gap >= 0) return `+${gap.toFixed(1)}% över mål`;
    return `${gap.toFixed(1)}% under mål`;
  }

  getGapClass(gap: number): string {
    return gap >= 0 ? 'text-success' : 'text-danger';
  }

  getGapIcon(overTarget: boolean): string {
    return overTarget ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
  }

  formatDatum(datum: string): string {
    const d = new Date(datum + 'T00:00:00');
    return d.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
  }

  formatH(h: number): string {
    return h.toFixed(1) + ' h';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
