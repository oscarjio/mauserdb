import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, SPCResponse } from '../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-spc',
  templateUrl: './rebotling-spc.component.html',
  imports: [CommonModule, FormsModule]
})
export class RebotlingSpcComponent implements OnInit, OnDestroy {
  spcDays = 7;
  spcLoaded = false;
  spcLoading = false;
  spcMean = 0; spcStddev = 0; spcUCL = 0; spcLCL = 0; spcN = 0;
  private spcChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}
  ngOnInit() { this.loadSPC(); }
  ngOnDestroy() {
    try { this.spcChart?.destroy(); } catch (e) {}
    this.spcChart = null;
    this.destroy$.next(); this.destroy$.complete();
  }

  loadSPC() {
    if (this.spcLoading) return;
    this.spcLoading = true;
    this.rebotlingService.getSPC(this.spcDays).pipe(
      timeout(10000), takeUntil(this.destroy$), catchError(() => of(null))
    ).subscribe((res: SPCResponse | null) => {
      this.spcLoading = false;
      if (res?.success && res.data) {
        this.spcMean = res.data.mean; this.spcStddev = res.data.stddev;
        this.spcUCL = res.data.ucl; this.spcLCL = res.data.lcl; this.spcN = res.data.n;
        this.spcLoaded = true;
        setTimeout(() => this.renderSPCChart(res.data!.points), 100);
      } else { this.spcLoaded = true; }
    });
  }

  private renderSPCChart(points: { label: string; ibc_per_hour: number }[]) {
    try { this.spcChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('spcChart') as HTMLCanvasElement;
    if (!canvas || !points.length) return;
    const labels = points.map(p => p.label);
    const values = points.map(p => p.ibc_per_hour);
    this.spcChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'IBC/h', data: values, borderColor: '#4299e1', backgroundColor: 'rgba(66,153,225,0.15)',
            fill: true, tension: 0.3, pointRadius: 4, pointHoverRadius: 7, borderWidth: 2, yAxisID: 'y' },
          { label: 'UCL (Övre kontrollgräns)', data: points.map(() => this.spcUCL), borderColor: '#fc8181',
            borderDash: [6, 3], tension: 0, fill: false, pointRadius: 0, borderWidth: 1.5, yAxisID: 'y' },
          { label: 'LCL (Nedre kontrollgräns)', data: points.map(() => this.spcLCL), borderColor: '#ed8936',
            borderDash: [6, 3], tension: 0, fill: false, pointRadius: 0, borderWidth: 1.5, yAxisID: 'y' },
          { label: 'Medelvärde (X\u0305)', data: points.map(() => this.spcMean), borderColor: '#48bb78',
            borderDash: [4, 4], tension: 0, fill: false, pointRadius: 0, borderWidth: 1.5, yAxisID: 'y' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: { backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0', borderColor: '#4299e1', borderWidth: 1 }
        },
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 14 }, grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { beginAtZero: false, ticks: { color: '#718096', callback: (v: any) => v + ' IBC/h' }, grid: { color: 'rgba(255,255,255,0.04)' },
               title: { display: true, text: 'IBC per timme', color: '#a0aec0', font: { size: 12 } } }
        }
      }
    });
  }

  onSPCDaysChange() { this.spcLoaded = false; this.loadSPC(); }
}
