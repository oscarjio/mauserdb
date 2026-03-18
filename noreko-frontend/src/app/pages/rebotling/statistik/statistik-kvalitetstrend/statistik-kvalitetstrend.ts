import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, QualityTrendDay, QualityTrendResponse } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-kvalitetstrend',
  templateUrl: './statistik-kvalitetstrend.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikKvalitetstrendComponent implements OnInit, OnDestroy {
  qualityTrendDays: number = 30;
  qualityTrendLoaded: boolean = false;
  qualityTrendLoading: boolean = false;
  qualityTrendData: QualityTrendDay[] = [];
  qualityTrendKpi: { avg: number | null; min: number | null; max: number | null; trend: 'up' | 'down' | 'stable' } = { avg: null, min: null, max: null, trend: 'stable' };
  private qualityTrendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() { this.loadQualityTrend(); }

  ngOnDestroy() {
    try { this.qualityTrendChart?.destroy(); } catch (e) {}
    this.qualityTrendChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadQualityTrend() {
    if (this.qualityTrendLoading) return;
    this.qualityTrendLoading = true;
    this.qualityTrendLoaded = false;
    this.rebotlingService.getQualityTrend(this.qualityTrendDays).pipe(
      timeout(10000), takeUntil(this.destroy$), catchError(() => of(null))
    ).subscribe((res: QualityTrendResponse | null) => {
      this.qualityTrendLoading = false;
      if (res?.success && res.days) {
        this.qualityTrendData = res.days;
        this.qualityTrendKpi = res.kpi ?? { avg: null, min: null, max: null, trend: 'stable' };
        this.qualityTrendLoaded = true;
        setTimeout(() => { if (!this.destroy$.closed) this.renderQualityTrendChart(); }, 100);
      } else {
        this.qualityTrendLoaded = true;
      }
    });
  }

  private renderQualityTrendChart() {
    try { this.qualityTrendChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('qualityTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.qualityTrendData.length) return;
    const labels = this.qualityTrendData.map(d => d.date.substring(5));
    const dailyData = this.qualityTrendData.map(d => d.quality_pct);
    const rollingData = this.qualityTrendData.map(d => d.rolling_avg);
    const targetLine = this.qualityTrendData.map(() => 90);
    if (this.qualityTrendChart) { (this.qualityTrendChart as any).destroy(); }
    this.qualityTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Daglig kvalitet %', data: dailyData, borderColor: 'rgba(236,201,75,0.9)', backgroundColor: 'rgba(236,201,75,0.12)', tension: 0.3, pointRadius: 3, borderWidth: 2, fill: true, spanGaps: true, yAxisID: 'y' },
          { label: '7-dagars rullande snitt', data: rollingData, borderColor: 'rgba(237,137,54,1)', backgroundColor: 'transparent', tension: 0.4, pointRadius: 1, borderWidth: 3, fill: false, spanGaps: true, yAxisID: 'y' },
          { label: 'Kvalitetsmal 90%', data: targetLine, borderColor: 'rgba(252,129,129,0.8)', backgroundColor: 'transparent', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false, spanGaps: true, yAxisID: 'y' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: { backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0', borderColor: '#ecc94b', borderWidth: 1,
            callbacks: { label: (ctx: any) => { const v = ctx.parsed.y; return v !== null ? `${ctx.dataset.label}: ${v}%` : ''; } }
          }
        },
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 20 }, grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { min: 0, max: 100, ticks: { color: '#718096', callback: (v: any) => v + '%' }, grid: { color: 'rgba(255,255,255,0.06)' }, title: { display: true, text: 'Kvalitet %', color: '#a0aec0', font: { size: 11 } } }
        }
      }
    });
  }
}
