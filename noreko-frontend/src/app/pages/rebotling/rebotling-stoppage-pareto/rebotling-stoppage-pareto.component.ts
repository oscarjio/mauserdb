import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService } from '../../../services/rebotling.service';

interface ParetoItem {
  orsak: string;
  kategori: string;
  antal_stopp: number;
  total_minuter: number;
  snitt_minuter: number;
  pct_av_total: number;
  kumulativ_pct: number;
}

@Component({
  standalone: true, selector: 'app-rebotling-stoppage-pareto',
  templateUrl: './rebotling-stoppage-pareto.component.html',
  imports: [CommonModule]
})
export class RebotlingStoppageParetoComponent implements OnInit, OnDestroy {
  paretoDays = 30;
  paretoLoading = false;
  paretoEmpty = false;
  paretoEmptyReason = '';
  paretoItems: ParetoItem[] = [];
  paretoTotalMinuter = 0;
  paretoTotalStopp = 0;
  private paretoChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  get paretoTotalTimmar(): number { return Math.round(this.paretoTotalMinuter / 60 * 10) / 10; }
  get paretoVitalFewCount(): number { return this.paretoItems.filter(i => i.kumulativ_pct <= 80).length || (this.paretoItems.length > 0 ? 1 : 0); }
  get paretoBastaForbattring(): string { return this.paretoItems[0]?.orsak ?? '\u2013'; }

  constructor(private rebotlingService: RebotlingService) {}
  ngOnInit() { this.loadStoppagePareto(); }
  ngOnDestroy() {
    try { this.paretoChart?.destroy(); } catch (e) {}
    this.paretoChart = null;
    this.destroy$.next(); this.destroy$.complete();
  }

  loadStoppagePareto(): void {
    this.paretoLoading = true;
    this.rebotlingService.getParetoStoppage(this.paretoDays).pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.paretoLoading = false;
      if (!res) return;
      if (res.success) {
        this.paretoItems = res.items || [];
        this.paretoTotalMinuter = res.total_minuter || 0;
        this.paretoTotalStopp = res.total_stopp || 0;
        this.paretoEmpty = !!res.empty;
        this.paretoEmptyReason = res.reason || '';
        if (!this.paretoEmpty && this.paretoItems.length > 0) {
          setTimeout(() => this.buildParetoChart(), 100);
        }
      }
    });
  }

  exportParetoCSV(): void {
    if (!this.paretoItems.length) return;
    const headers = ['Stopporsak', 'Kategori', 'Antal stopp', 'Total tid (min)', 'Total tid (h)', 'Snitt (min)', 'Andel %', 'Kumulativ %'];
    const rows = this.paretoItems.map(item => [
      item.orsak, item.kategori || '', item.antal_stopp, item.total_minuter,
      (item.total_minuter / 60).toFixed(1), item.snitt_minuter.toFixed(1),
      item.pct_av_total.toFixed(1) + '%', item.kumulativ_pct.toFixed(1) + '%'
    ]);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `pareto-stopporsaker-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click(); URL.revokeObjectURL(url);
  }

  private buildParetoChart(): void {
    try { this.paretoChart?.destroy(); } catch (e) {}
    this.paretoChart = null;
    const canvas = document.getElementById('stoppagePareto') as HTMLCanvasElement;
    if (!canvas || !this.paretoItems.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.paretoItems.map(i => i.orsak);
    const minutes = this.paretoItems.map(i => i.total_minuter);
    const cumPcts = this.paretoItems.map(i => i.kumulativ_pct);
    const barColors = this.paretoItems.map(i =>
      i.kumulativ_pct <= 80 ? 'rgba(66, 153, 225, 0.85)' : 'rgba(74, 85, 104, 0.6)'
    );

    this.paretoChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { type: 'bar' as const, label: 'Minuter', data: minutes, backgroundColor: barColors,
            borderWidth: 0, borderRadius: 4, yAxisID: 'yLeft', order: 2 },
          { type: 'line' as const, label: 'Kumulativ %', data: cumPcts,
            borderColor: '#ed8936', backgroundColor: 'rgba(237, 137, 54, 0.15)',
            borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#ed8936',
            fill: false, yAxisID: 'yRight', order: 1 }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: true, labels: { color: '#a0aec0', boxWidth: 14 } },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                if (ctx.datasetIndex === 0) {
                  const item = this.paretoItems[ctx.dataIndex];
                  return [`Minuter: ${item.total_minuter}`, `Stopp: ${item.antal_stopp}`, `Andel: ${item.pct_av_total}%`];
                }
                return `Kumulativt: ${ctx.raw}%`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0', maxRotation: 35, minRotation: 0 }, grid: { color: '#4a5568' } },
          yLeft: { type: 'linear' as const, position: 'left' as const, beginAtZero: true,
            ticks: { color: '#a0aec0' }, grid: { color: '#4a5568' },
            title: { display: true, text: 'Minuter', color: '#a0aec0' } },
          yRight: { type: 'linear' as const, position: 'right' as const, min: 0, max: 100,
            ticks: { color: '#ed8936', callback: (v: any) => v + '%' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kumulativ %', color: '#ed8936' } }
        }
      },
      plugins: [{
        id: 'pareto80Line',
        afterDraw(chart: any) {
          const yR = chart.scales['yRight'];
          const xAxis = chart.scales['x'];
          if (!yR || !xAxis) return;
          const y80 = yR.getPixelForValue(80);
          const ctx2 = chart.ctx;
          ctx2.save(); ctx2.beginPath();
          ctx2.moveTo(xAxis.left, y80); ctx2.lineTo(xAxis.right, y80);
          ctx2.strokeStyle = '#e53e3e'; ctx2.lineWidth = 1.5; ctx2.setLineDash([6, 4]);
          ctx2.stroke(); ctx2.setLineDash([]);
          ctx2.fillStyle = '#e53e3e'; ctx2.font = '11px sans-serif';
          ctx2.fillText('80%', xAxis.right - 32, y80 - 5);
          ctx2.restore();
        }
      }]
    });
  }
}
