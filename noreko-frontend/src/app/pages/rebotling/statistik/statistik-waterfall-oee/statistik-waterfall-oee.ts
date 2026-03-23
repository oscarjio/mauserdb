import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, OeeWaterfallResponse } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-waterfall-oee',
  templateUrl: './statistik-waterfall-oee.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikWaterfallOeeComponent implements OnInit, OnDestroy {
  oeeWaterfallDays: number = 30;
  oeeWaterfallLoaded: boolean = false;
  oeeWaterfallLoading: boolean = false;
  oeeWaterfallData: OeeWaterfallResponse | null = null;
  private oeeWaterfallChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}
  ngOnInit() { this.loadOeeWaterfall(); }
  ngOnDestroy() {
    try { this.oeeWaterfallChart?.destroy(); } catch (e) {}
    this.oeeWaterfallChart = null;
    this.destroy$.next(); this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  loadOeeWaterfall() {
    if (this.oeeWaterfallLoading) return;
    this.oeeWaterfallLoading = true;
    this.oeeWaterfallLoaded = false;
    this.rebotlingService.getOeeWaterfall(this.oeeWaterfallDays).pipe(
      timeout(10000), takeUntil(this.destroy$), catchError(() => of(null))
    ).subscribe((res: OeeWaterfallResponse | null) => {
      this.oeeWaterfallLoading = false;
      if (res?.success) {
        this.oeeWaterfallData = res;
        this.oeeWaterfallLoaded = true;
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderOeeWaterfallChart(); }, 100));
      } else { this.oeeWaterfallLoaded = true; }
    });
  }

  private renderOeeWaterfallChart() {
    try { this.oeeWaterfallChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('oeeWaterfallChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.oeeWaterfallData) return;
    const d = this.oeeWaterfallData;
    const avail = d.availability ?? 0; const perf = d.performance ?? 0;
    const qual = d.quality ?? 0; const oee = d.oee ?? 0;
    const labels = ['Tillganglighet', 'Prestanda', 'Kvalitet', 'OEE'];
    const achieved = [avail, perf, qual, oee];
    const losses = [100 - avail, 100 - perf, 100 - qual, 100 - oee];
    if (this.oeeWaterfallChart) { (this.oeeWaterfallChart as any).destroy(); }
    this.oeeWaterfallChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Uppnatt', data: achieved,
            backgroundColor: achieved.map(v => v >= 85 ? 'rgba(72,187,120,0.85)' : v >= 65 ? 'rgba(236,201,75,0.85)' : 'rgba(252,129,129,0.85)'),
            borderColor: achieved.map(v => v >= 85 ? 'rgba(72,187,120,1)' : v >= 65 ? 'rgba(236,201,75,1)' : 'rgba(252,129,129,1)'),
            borderWidth: 1, borderRadius: 4, stack: 'oee' },
          { label: 'Forlust', data: losses, backgroundColor: 'rgba(255,255,255,0.06)', borderColor: 'rgba(255,255,255,0.12)', borderWidth: 1, borderRadius: 4, stack: 'oee' }
        ]
      },
      options: {
        indexAxis: 'y' as any, responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: { backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0', borderColor: '#48bb78', borderWidth: 1,
            callbacks: { label: (ctx: any) => { const v = ctx.parsed.x; if (v == null) return ''; return ctx.datasetIndex === 0 ? `Uppnatt: ${v.toFixed(1)}%` : `Forlust: ${v.toFixed(1)}%`; } }
          }
        },
        scales: {
          x: { stacked: true, min: 0, max: 100, ticks: { color: '#718096', callback: (v: any) => v + '%' }, grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { stacked: true, ticks: { color: '#e2e8f0', font: { size: 13 } }, grid: { color: 'rgba(255,255,255,0.04)' } }
        }
      }
    });
  }
}
