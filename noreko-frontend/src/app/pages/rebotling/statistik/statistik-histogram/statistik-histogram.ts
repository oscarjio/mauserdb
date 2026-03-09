import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart } from 'chart.js';
import { RebotlingService, CycleHistogramResponse } from '../../../../services/rebotling.service';
import { localToday } from '../../../../utils/date-utils';

@Component({
  standalone: true,
  selector: 'app-statistik-histogram',
  templateUrl: './statistik-histogram.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikHistogramComponent implements OnInit, OnDestroy {
  histogramDate: string = localToday();
  histogramLoaded: boolean = false;
  histogramLoading: boolean = false;
  histogramBuckets: { label: string; count: number }[] = [];
  histogramStats: { n: number; snitt: number; p50: number; p90: number; p95: number } | null = null;
  private histogramChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.loadCycleHistogram();
  }

  ngOnDestroy() {
    try { this.histogramChart?.destroy(); } catch (e) {}
    this.histogramChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadCycleHistogram() {
    if (this.histogramLoading) return;
    this.histogramLoading = true;

    this.rebotlingService.getCycleHistogram(this.histogramDate).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: CycleHistogramResponse | null) => {
      this.histogramLoading = false;
      if (res?.success && res.data) {
        this.histogramBuckets = res.data.buckets;
        this.histogramStats = res.data.stats;
        this.histogramLoaded = true;
        setTimeout(() => this.renderHistogramChart(), 100);
      } else {
        this.histogramLoaded = true;
      }
    });
  }

  private renderHistogramChart() {
    try { this.histogramChart?.destroy(); } catch (e) {}
    this.histogramChart = null;
    const canvas = document.getElementById('cycleHistogramChart') as HTMLCanvasElement;
    if (!canvas || !this.histogramBuckets.length) return;

    const labels = this.histogramBuckets.map(b => b.label);
    const counts = this.histogramBuckets.map(b => b.count);

    this.histogramChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal skift',
            data: counts,
            backgroundColor: 'rgba(72, 187, 120, 0.75)',
            borderColor: '#48bb78',
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e2e8f0',
            borderColor: '#48bb78',
            borderWidth: 1,
            callbacks: {
              label: (ctx: any) => ` ${ctx.parsed.y} st`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Antal skift', color: '#a0aec0', font: { size: 12 } }
          }
        }
      }
    });
  }

  onHistogramDateChange() {
    this.histogramLoaded = false;
    this.loadCycleHistogram();
  }
}
