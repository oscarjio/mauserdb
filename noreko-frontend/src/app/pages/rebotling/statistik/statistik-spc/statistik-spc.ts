import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, SPCResponse } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-spc',
  templateUrl: './statistik-spc.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikSpcComponent implements OnInit, OnDestroy {
  spcDays: number = 7;
  spcLoaded: boolean = false;
  spcLoading: boolean = false;
  spcMean: number = 0;
  spcStddev: number = 0;
  spcUCL: number = 0;
  spcLCL: number = 0;
  spcN: number = 0;
  private spcChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.loadSPC();
  }

  ngOnDestroy() {
    try { this.spcChart?.destroy(); } catch (e) {}
    this.spcChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  loadSPC() {
    if (this.spcLoading) return;
    this.spcLoading = true;

    this.rebotlingService.getSPC(this.spcDays).pipe(
      timeout(10000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res: SPCResponse | null) => {
      this.spcLoading = false;
      if (res?.success && res.data) {
        this.spcMean   = res.data.mean;
        this.spcStddev = res.data.stddev;
        this.spcUCL    = res.data.ucl;
        this.spcLCL    = res.data.lcl;
        this.spcN      = res.data.n;
        this.spcLoaded = true;
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderSPCChart(res.data!.points); }, 100));
      } else {
        this.spcLoaded = true;
      }
    });
  }

  private renderSPCChart(points: { label: string; ibc_per_hour: number }[]) {
    try { this.spcChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('spcChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !points.length) return;

    const labels  = points.map(p => p.label);
    const values  = points.map(p => p.ibc_per_hour);
    const uclArr  = points.map(() => this.spcUCL);
    const lclArr  = points.map(() => this.spcLCL);
    const meanArr = points.map(() => this.spcMean);

    if (this.spcChart) { (this.spcChart as any).destroy(); }
    this.spcChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data: values,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 7,
            borderWidth: 2,
            yAxisID: 'y'
          },
          {
            label: 'UCL (Ovre kontrollgrans)',
            data: uclArr,
            borderColor: '#fc8181',
            borderDash: [6, 3],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            yAxisID: 'y'
          },
          {
            label: 'LCL (Nedre kontrollgrans)',
            data: lclArr,
            borderColor: '#ed8936',
            borderDash: [6, 3],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            yAxisID: 'y'
          },
          {
            label: 'Medelvarde (X\u0305)',
            data: meanArr,
            borderColor: '#48bb78',
            borderDash: [4, 4],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            yAxisID: 'y'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1
          }
        },
        scales: {
          x: {
            ticks: { color: '#8fa3b8', maxRotation: 45, autoSkip: true, maxTicksLimit: 14 },
            grid: { color: 'rgba(255,255,255,0.04)' }
          },
          y: {
            beginAtZero: false,
            ticks: { color: '#8fa3b8', callback: (v: any) => v + ' IBC/h' },
            grid: { color: 'rgba(255,255,255,0.04)' },
            title: { display: true, text: 'IBC per timme', color: '#a0aec0', font: { size: 12 } }
          }
        }
      }
    });
  }

  onSPCDaysChange() {
    this.spcLoaded = false;
    this.loadSPC();
  }
}
