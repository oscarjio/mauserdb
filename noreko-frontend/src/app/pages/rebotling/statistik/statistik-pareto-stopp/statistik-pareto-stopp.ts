import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, ChartConfiguration } from 'chart.js';
import { RebotlingService } from '../../../../services/rebotling.service';
import { localToday, localDateStr } from '../../../../utils/date-utils';
import { exportChartAsPng } from '../../../../shared/chart-export.util';

interface ParetoItem {
  orsak: string;
  kategori: string;
  antal_stopp: number;
  total_minuter: number;
  snitt_minuter: number;
  pct_av_total: number;
  kumulativ_pct: number;
}

interface DrilldownSummary {
  total_minutes: number;
  total_hours: number;
  count: number;
  avg_minutes: number;
}

interface DrilldownOperator {
  operator: string;
  count: number;
  total_minutes: number;
}

interface DrilldownDay {
  date: string;
  count: number;
  minutes: number;
}

interface DrilldownStop {
  id: number;
  date: string;
  time: string;
  end_time: string | null;
  minutes: number;
  operator: string;
  comment: string;
}

@Component({
  standalone: true,
  selector: 'app-statistik-pareto-stopp',
  templateUrl: './statistik-pareto-stopp.html',
  styleUrls: ['./statistik-pareto-stopp.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikParetoStoppComponent implements OnInit, OnDestroy {
  paretoDays: number = 30;
  paretoLoading: boolean = false;
  paretoEmpty: boolean = false;
  paretoEmptyReason: string = '';
  paretoItems: ParetoItem[] = [];
  paretoTotalMinuter: number = 0;
  paretoTotalStopp: number = 0;
  private paretoChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  exportFeedback: boolean = false;

  // Drill-down state
  drilldownOpen: boolean = false;
  drilldownLoading: boolean = false;
  drilldownCause: string = '';
  drilldownSummary: DrilldownSummary | null = null;
  drilldownOperators: DrilldownOperator[] = [];
  drilldownDays: DrilldownDay[] = [];
  drilldownStops: DrilldownStop[] = [];

  @ViewChild('paretoCanvas', { static: false }) paretoCanvasRef!: ElementRef<HTMLCanvasElement>;

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.loadStoppagePareto();
  }

  ngOnDestroy() {
    try { this.paretoChart?.destroy(); } catch (e) { /* ignore */ }
    this.paretoChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  get paretoTotalTimmar(): number {
    return Math.round(this.paretoTotalMinuter / 60 * 10) / 10;
  }

  get paretoVitalFewCount(): number {
    return this.paretoItems.filter(i => i.kumulativ_pct <= 80).length || (this.paretoItems.length > 0 ? 1 : 0);
  }

  get paretoBastaForbattring(): string {
    return this.paretoItems[0]?.orsak ?? '\u2013';
  }

  /** Dynamic chart height: 40px per item, min 280px, max 800px */
  get chartHeight(): number {
    const h = Math.max(280, Math.min(800, this.paretoItems.length * 40 + 80));
    return h;
  }

  loadStoppagePareto(): void {
    this.paretoLoading = true;
    this.rebotlingService.getParetoStoppage(this.paretoDays)
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.paretoLoading = false;
        if (!res) return;
        if (res.success) {
          this.paretoItems = res.items || [];
          this.paretoTotalMinuter = res.total_minuter || 0;
          this.paretoTotalStopp = res.total_stopp || 0;
          this.paretoEmpty = !!res.empty;
          this.paretoEmptyReason = res.reason || '';
          if (!this.paretoEmpty && this.paretoItems.length > 0) {
            setTimeout(() => {
              if (!this.destroy$.closed) this.buildParetoChart();
            }, 100);
          }
        }
      });
  }

  /** Open drill-down for a specific stop cause */
  openDrilldown(cause: string): void {
    this.drilldownCause = cause;
    this.drilldownOpen = true;
    this.drilldownLoading = true;
    this.drilldownSummary = null;
    this.drilldownOperators = [];
    this.drilldownDays = [];
    this.drilldownStops = [];

    this.rebotlingService.getStopCauseDrilldown(cause, this.paretoDays)
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.drilldownLoading = false;
        if (!res || !res.success) return;
        this.drilldownSummary = res.summary || null;
        this.drilldownOperators = res.by_operator || [];
        this.drilldownDays = res.by_day || [];
        this.drilldownStops = res.stops || [];
      });
  }

  /** Close drill-down modal */
  closeDrilldown(): void {
    this.drilldownOpen = false;
    this.drilldownCause = '';
    this.drilldownSummary = null;
    this.drilldownOperators = [];
    this.drilldownDays = [];
    this.drilldownStops = [];
  }

  /** Handle click on table row to open drill-down */
  onTableRowClick(item: ParetoItem): void {
    this.openDrilldown(item.orsak);
  }

  exportParetoCSV(): void {
    if (!this.paretoItems || this.paretoItems.length === 0) return;
    const headers = ['Stopporsak', 'Kategori', 'Antal stopp', 'Total tid (min)', 'Total tid (h)', 'Snitt (min)', 'Andel %', 'Kumulativ %'];
    const rows = this.paretoItems.map(item => [
      item.orsak,
      item.kategori || '',
      item.antal_stopp,
      item.total_minuter,
      ((item.total_minuter ?? 0) / 60).toFixed(1),
      (item.snitt_minuter ?? 0).toFixed(1),
      (item.pct_av_total ?? 0).toFixed(1) + '%',
      (item.kumulativ_pct ?? 0).toFixed(1) + '%'
    ]);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'pareto-stopporsaker-' + localToday() + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  private buildParetoChart(): void {
    try { this.paretoChart?.destroy(); } catch (e) { /* ignore */ }
    this.paretoChart = null;

    const canvas = this.paretoCanvasRef?.nativeElement;
    if (!canvas || !this.paretoItems.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.paretoItems.map(i => this.truncateLabel(i.orsak, 30));
    const minutes = this.paretoItems.map(i => i.total_minuter);
    const cumPcts = this.paretoItems.map(i => i.kumulativ_pct);
    const maxMinutes = Math.max(...minutes);

    // Vital few (<=80%) get orange/red, the rest get grey
    const barColors = this.paretoItems.map(i =>
      i.kumulativ_pct <= 80 ? 'rgba(237, 137, 54, 0.85)' : 'rgba(74, 85, 104, 0.5)'
    );
    const barBorderColors = this.paretoItems.map(i =>
      i.kumulativ_pct <= 80 ? 'rgba(237, 137, 54, 1)' : 'rgba(74, 85, 104, 0.7)'
    );

    const items = this.paretoItems;
    const component = this;

    const config: ChartConfiguration = {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            type: 'bar' as const,
            label: 'Stopptid (min)',
            data: minutes,
            backgroundColor: barColors,
            borderColor: barBorderColors,
            borderWidth: 1,
            borderRadius: 3,
            barPercentage: 0.75,
            categoryPercentage: 0.85,
            xAxisID: 'xBottom',
            order: 2
          },
          {
            type: 'line' as const,
            label: 'Kumulativ %',
            data: cumPcts,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.1)',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#63b3ed',
            pointBorderColor: '#1a202c',
            pointBorderWidth: 1.5,
            fill: false,
            xAxisID: 'xTop',
            order: 1
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { top: 10, right: 16, bottom: 10, left: 8 }
        },
        onClick: (_event: any, elements: any[]) => {
          if (elements.length > 0) {
            const idx = elements[0].index;
            if (idx !== undefined && items[idx]) {
              component.openDrilldown(items[idx].orsak);
            }
          }
        },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              color: '#a0aec0',
              boxWidth: 14,
              padding: 16,
              font: { size: 12 }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(26, 32, 44, 0.95)',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            cornerRadius: 6,
            padding: 10,
            callbacks: {
              title: (tooltipItems: any[]) => {
                const idx = tooltipItems[0]?.dataIndex;
                if (idx !== undefined && items[idx]) {
                  return items[idx].orsak;
                }
                return '';
              },
              label: (ctx: any) => {
                const idx = ctx.dataIndex;
                const item = items[idx];
                if (!item) return '';
                if (ctx.datasetIndex === 0) {
                  return [
                    `Stopptid: ${item.total_minuter} min (${(item.total_minuter / 60).toFixed(1)} h)`,
                    `Antal stopp: ${item.antal_stopp}`,
                    `Andel av total: ${item.pct_av_total.toFixed(1)}%`
                  ];
                } else {
                  return `Kumulativt: ${item.kumulativ_pct.toFixed(1)}%`;
                }
              }
            }
          }
        },
        scales: {
          y: {
            type: 'category' as const,
            position: 'left' as const,
            ticks: {
              color: '#e2e8f0',
              font: { size: 12 },
              crossAlign: 'far' as const
            },
            grid: {
              color: 'rgba(74, 85, 104, 0.3)',
              drawOnChartArea: true
            }
          },
          xBottom: {
            type: 'linear' as const,
            position: 'bottom' as const,
            beginAtZero: true,
            suggestedMax: maxMinutes * 1.1,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 }
            },
            grid: {
              color: 'rgba(74, 85, 104, 0.25)'
            },
            title: {
              display: true,
              text: 'Stopptid (minuter)',
              color: '#a0aec0',
              font: { size: 12 }
            }
          },
          xTop: {
            type: 'linear' as const,
            position: 'top' as const,
            min: 0,
            max: 100,
            ticks: {
              color: '#63b3ed',
              font: { size: 11 },
              callback: (v: any) => v + '%'
            },
            grid: {
              drawOnChartArea: false
            },
            title: {
              display: true,
              text: 'Kumulativ %',
              color: '#63b3ed',
              font: { size: 12 }
            }
          }
        },
        onHover: (event: any, elements: any[]) => {
          const target = event?.native?.target as HTMLElement;
          if (target) {
            target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
          }
        }
      },
      plugins: [{
        id: 'pareto80Line',
        afterDraw(chart: any) {
          const xTop = chart.scales['xTop'];
          const yAxis = chart.scales['y'];
          if (!xTop || !yAxis) return;
          const x80 = xTop.getPixelForValue(80);
          const ctx2 = chart.ctx;
          ctx2.save();
          // Vertical dashed line at 80%
          ctx2.beginPath();
          ctx2.moveTo(x80, yAxis.top);
          ctx2.lineTo(x80, yAxis.bottom);
          ctx2.strokeStyle = '#e53e3e';
          ctx2.lineWidth = 1.5;
          ctx2.setLineDash([6, 4]);
          ctx2.stroke();
          ctx2.setLineDash([]);
          // Label
          ctx2.fillStyle = '#e53e3e';
          ctx2.font = 'bold 11px sans-serif';
          ctx2.textAlign = 'center';
          ctx2.fillText('80%', x80, yAxis.bottom + 14);
          ctx2.restore();
        }
      }]
    };

    if (this.paretoChart) { (this.paretoChart as any).destroy(); }
    this.paretoChart = new Chart(ctx, config);
  }

  exportChart(): void {
    const canvas = this.paretoCanvasRef?.nativeElement;
    if (!canvas) return;
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - (this.paretoDays - 1));
    const fmt = (d: Date) => localDateStr(d);
    exportChartAsPng(canvas, {
      chartName: 'Stopporsaksanalys - Pareto',
      startDate: fmt(startDate),
      endDate: fmt(endDate)
    });
    this.exportFeedback = true;
    setTimeout(() => { if (!this.destroy$.closed) this.exportFeedback = false; }, 2000);
  }

  private truncateLabel(label: string, maxLen: number): string {
    if (label.length <= maxLen) return label;
    return label.substring(0, maxLen - 1) + '\u2026';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
