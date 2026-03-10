import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart } from 'chart.js';
import { RebotlingService, ChartAnnotation, ProductionEvent, ManualAnnotation } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-cykeltrend',
  templateUrl: './statistik-cykeltrend.html',
  imports: [CommonModule]
})
export class StatistikCykeltrendComponent implements OnInit, OnDestroy {
  cycleTrendLoaded: boolean = false;
  cycleTrendLoading: boolean = false;
  cycleTrendDays: number = 30;
  cycleTrendData: any[] = [];
  cycleTrendGranularity: 'day' | 'shift' = 'day';
  private cycleTrendChart: Chart | null = null;
  private chartAnnotations: ChartAnnotation[] = [];
  private manualAnnotations: ManualAnnotation[] = [];
  private productionEvents: ProductionEvent[] = [];
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() { this.loadCycleTrend(); }

  ngOnDestroy() {
    try { this.cycleTrendChart?.destroy(); } catch (e) {}
    this.cycleTrendChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  setCycleTrendGranularity(g: 'day' | 'shift') {
    this.cycleTrendGranularity = g;
    this.cycleTrendLoaded = false;
    this.loadCycleTrend();
  }

  loadCycleTrend() {
    if (this.cycleTrendLoading) return;
    this.cycleTrendLoading = true;

    if (this.chartAnnotations.length === 0) {
      const endDate = new Date();
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - (this.cycleTrendDays - 1));
      const fmt = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
      this.loadAnnotations(fmt(startDate), fmt(endDate));
      this.loadManualAnnotations(fmt(startDate), fmt(endDate));
    }

    this.rebotlingService.getCycleTrend(this.cycleTrendDays, this.cycleTrendGranularity).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: any) => {
      this.cycleTrendLoading = false;
      if (res?.success && res.data) {
        this.cycleTrendData = res.data.daily;
        this.cycleTrendLoaded = true;
        setTimeout(() => { if (!this.destroy$.closed) this.renderCycleTrendChart(); }, 100);
      } else {
        this.cycleTrendLoaded = true;
      }
    });
  }

  private loadAnnotations(startDate: string, endDate: string) {
    this.rebotlingService.getAnnotations(startDate, endDate).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: any) => {
      if (res?.success && res.annotations) {
        this.chartAnnotations = res.annotations.map((ann: any) => ({
          date: ann.date,
          dateShort: ann.date.substring(5),
          type: ann.type as 'stopp' | 'low_production' | 'audit',
          label: ann.label
        }));
        if (this.cycleTrendLoaded && this.cycleTrendData.length) {
          setTimeout(() => { if (!this.destroy$.closed) this.renderCycleTrendChart(); }, 0);
        }
      }
    });
  }

  private loadManualAnnotations(startDate: string, endDate: string) {
    this.rebotlingService.getManualAnnotations(startDate, endDate).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: any) => {
      if (res?.success && res.annotations) {
        this.manualAnnotations = res.annotations;
        if (this.cycleTrendLoaded && this.cycleTrendData.length) {
          setTimeout(() => { if (!this.destroy$.closed) this.renderCycleTrendChart(); }, 0);
        }
      }
    });
  }

  private manualAnnotationColor(typ: string): string {
    const colors: Record<string, string> = {
      driftstopp: '#e53e3e',
      helgdag: '#4299e1',
      handelse: '#48bb78',
      ovrigt: '#a0aec0'
    };
    return colors[typ] || '#a0aec0';
  }

  private eventColor(type: string): string {
    const colors: Record<string, string> = {
      'underhall': '#f97316', 'ny_operator': '#3b82f6',
      'mal_andring': '#a855f7', 'rekord': '#eab308', 'ovrigt': '#6b7280',
    };
    return colors[type] ?? '#6b7280';
  }

  private renderCycleTrendChart() {
    try { this.cycleTrendChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('cycleTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.cycleTrendData.length) return;

    const labels = this.cycleTrendData.map((d: any) => d.label ?? d.dag.substring(5));
    const ibcData = this.cycleTrendData.map((d: any) => d.total_ibc_ok);
    const ibcPerHour = this.cycleTrendData.map((d: any) => d.avg_ibc_per_hour);

    const cycleProductionEventAnnotations: ChartAnnotation[] = this.productionEvents
      .filter((e: any) => {
        const shortDate = e.event_date.substring(5);
        return labels.some((l: string) => l.includes(shortDate));
      })
      .map((e: any) => ({
        date: e.event_date,
        dateShort: e.event_date.substring(5),
        type: 'audit' as const,
        label: e.title,
        color: this.eventColor(e.event_type)
      } as any));
    // Manuella annotationer (driftstopp, helgdagar etc.)
    const manualChartAnnotations: ChartAnnotation[] = this.manualAnnotations
      .filter((m: ManualAnnotation) => {
        const shortDate = m.datum.substring(5);
        return labels.some((l: string) => l.includes(shortDate));
      })
      .map((m: ManualAnnotation) => ({
        date: m.datum,
        dateShort: m.datum.substring(5),
        type: 'audit' as const,
        label: m.titel,
        color: this.manualAnnotationColor(m.typ)
      } as any));

    const cycleAnnotations = [...this.chartAnnotations, ...cycleProductionEventAnnotations, ...manualChartAnnotations];

    this.cycleTrendChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'IBC OK', data: ibcData, backgroundColor: 'rgba(66,153,225,0.6)', borderColor: 'rgba(99,179,237,1)', borderWidth: 1, borderRadius: 3, yAxisID: 'y' },
          { type: 'line' as any, label: 'IBC/h', data: ibcPerHour, borderColor: '#48bb78', backgroundColor: 'rgba(72,187,120,0.1)', tension: 0.3, pointRadius: 2, borderWidth: 2, fill: false, yAxisID: 'y2' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: { backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0', borderColor: '#4299e1', borderWidth: 1 },
          verticalAnnotations: { annotations: cycleAnnotations }
        } as any,
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 20 }, grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { beginAtZero: true, position: 'left', ticks: { color: '#718096' }, grid: { color: 'rgba(255,255,255,0.04)' }, title: { display: true, text: 'IBC OK', color: '#a0aec0', font: { size: 11 } } },
          y2: { beginAtZero: true, position: 'right', ticks: { color: '#48bb78', callback: (v: any) => v + '/h' }, grid: { drawOnChartArea: false }, title: { display: true, text: 'IBC/h', color: '#48bb78', font: { size: 11 } } }
        }
      }
    });
  }
}
