import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, CycleByOperatorEntry, CycleByOperatorResponse } from '../../../../services/rebotling.service';
import { localDateStr } from '../../../../utils/date-utils';
import { exportChartAsPng } from '../../../../shared/chart-export.util';

@Component({
  standalone: true,
  selector: 'app-statistik-cykeltid-operator',
  templateUrl: './statistik-cykeltid-operator.html',
  styleUrls: ['./statistik-cykeltid-operator.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikCykeltidOperatorComponent implements OnInit, OnDestroy {
  cycleByOpDays: number = 30;
  cycleByOpLoaded: boolean = false;
  cycleByOpLoading: boolean = false;
  cycleByOpData: CycleByOperatorEntry[] = [];
  rankedData: CycleByOperatorEntry[] = [];
  teamMedianAvg: number = 0;
  exportFeedback: boolean = false;
  private cycleByOpChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.loadCycleByOperator();
  }

  ngOnDestroy() {
    try { this.cycleByOpChart?.destroy(); } catch (_e) { /* ignore */ }
    this.cycleByOpChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  onCycleByOpDaysChange() {
    this.cycleByOpLoaded = false;
    this.loadCycleByOperator();
  }

  loadCycleByOperator() {
    if (this.cycleByOpLoading) return;
    this.cycleByOpLoading = true;

    const fmt = (d: Date) => localDateStr(d);
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - (this.cycleByOpDays - 1));

    this.rebotlingService.getCycleByOperator(fmt(startDate), fmt(endDate)).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: CycleByOperatorResponse | null) => {
      this.cycleByOpLoading = false;
      if (res?.success && res.data) {
        const medians = res.data.map(op => op.median_min ?? ((op.snitt_cykel_sek ?? 0) / 60));
        this.teamMedianAvg = medians.length > 0 ? medians.reduce((a, b) => a + b, 0) / medians.length : 0;
        this.cycleByOpData = res.data.map(op => ({
          ...op,
          vs_team_snitt: this.teamMedianAvg > 0
            ? Math.round(((op.median_min ?? (op.snitt_cykel_sek ?? 0) / 60) - this.teamMedianAvg) / this.teamMedianAvg * 100)
            : 0
        }));
        this.rankedData = [...this.cycleByOpData].sort((a, b) => (a.median_min ?? 999) - (b.median_min ?? 999));
        this.cycleByOpLoaded = true;
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderCycleByOpChart(); }, 100));
      } else {
        this.cycleByOpLoaded = true;
        this.cycleByOpData = [];
        this.rankedData = [];
      }
    });
  }

  private renderCycleByOpChart() {
    try { this.cycleByOpChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('cycleByOpChart') as HTMLCanvasElement;
    if (!canvas || !this.rankedData.length) return;

    const chartData = [...this.rankedData].reverse();
    const labels = chartData.map(op => op.initialer);

    const minValues = chartData.map(op => op.min_min ?? (op.bast_cykel_sek / 60));
    const medianValues = chartData.map(op => op.median_min ?? (op.snitt_cykel_sek / 60));
    const maxValues = chartData.map(op => op.max_min ?? (op.samst_cykel_sek / 60));

    const allValues = [...minValues, ...medianValues, ...maxValues];
    const maxVal = Math.max(...allValues);
    const xMax = maxVal * 1.15;

    const refLineValue = this.teamMedianAvg;
    const refLinePlugin = {
      id: 'cycleOpRefLine',
      afterDraw(chart: Chart) {
        if (refLineValue <= 0) return;
        const xScale = (chart as any).scales['x'];
        if (!xScale) return;
        const xPixel = xScale.getPixelForValue(refLineValue);
        const ctx = (chart as any).ctx as CanvasRenderingContext2D;
        const area = (chart as any).chartArea;
        ctx.save();
        ctx.beginPath();
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = 'rgba(159, 122, 234, 0.8)';
        ctx.lineWidth = 2;
        ctx.moveTo(xPixel, area.top);
        ctx.lineTo(xPixel, area.bottom);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = 'rgba(159, 122, 234, 0.85)';
        const labelText = 'Snitt median: ' + refLineValue.toFixed(2) + ' min';
        ctx.font = '10px sans-serif';
        const textWidth = ctx.measureText(labelText).width;
        const pad = 4;
        ctx.fillRect(xPixel - textWidth / 2 - pad, area.top - 16, textWidth + pad * 2, 14);
        ctx.fillStyle = '#fff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(labelText, xPixel, area.top - 9);
        ctx.restore();
      }
    };

    if (this.cycleByOpChart) { (this.cycleByOpChart as any).destroy(); }
    this.cycleByOpChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Min (min)',
            data: minValues,
            backgroundColor: 'rgba(72, 187, 120, 0.7)',
            borderColor: 'rgba(72, 187, 120, 1)',
            borderWidth: 1,
            borderRadius: 3
          },
          {
            label: 'Median (min)',
            data: medianValues,
            backgroundColor: 'rgba(66, 153, 225, 0.8)',
            borderColor: 'rgba(66, 153, 225, 1)',
            borderWidth: 1,
            borderRadius: 3
          },
          {
            label: 'Max (min)',
            data: maxValues,
            backgroundColor: 'rgba(252, 129, 129, 0.7)',
            borderColor: 'rgba(252, 129, 129, 1)',
            borderWidth: 1,
            borderRadius: 3
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 20 } },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 11 }, boxWidth: 14, padding: 16 }
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.96)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0].dataIndex;
                return chartData[idx].namn;
              },
              afterBody: (items: any[]) => {
                const idx = items[0].dataIndex;
                const op = chartData[idx];
                return [
                  'Antal skift: ' + op.antal_skift + ' st',
                  'Total IBC: ' + op.total_ibc,
                  'Stddev: ' + (op.stddev_min?.toFixed(2) ?? '-') + ' min'
                ];
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            max: xMax,
            ticks: { color: '#a0aec0', callback: (v: any) => v.toFixed(1) + ' min' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Cykeltid (minuter)', color: '#a0aec0', font: { size: 12 } }
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid: { color: 'rgba(255,255,255,0.04)' }
          }
        }
      },
      plugins: [refLinePlugin]
    });
  }

  exportChart(): void {
    const canvas = document.getElementById('cycleByOpChart') as HTMLCanvasElement;
    if (!canvas) return;
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - (this.cycleByOpDays - 1));
    const fmt = (d: Date) => localDateStr(d);
    exportChartAsPng(canvas, {
      chartName: 'Cykeltid per operator',
      startDate: fmt(startDate),
      endDate: fmt(endDate)
    });
    this.exportFeedback = true;
    this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.exportFeedback = false; }, 2000));
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
