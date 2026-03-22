import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, OEETrendDay, ChartAnnotation, ProductionEvent } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-oee-deepdive',
  templateUrl: './statistik-oee-deepdive.html',
  imports: [CommonModule]
})
export class StatistikOeeDeepdiveComponent implements OnInit, OnDestroy {
  oeeLoaded: boolean = false;
  oeeLoading: boolean = false;
  oeeData: any = null;
  oeeTrendDays: OEETrendDay[] = [];
  oeeGranularity: 'day' | 'shift' = 'day';
  private oeeTrendChart: Chart | null = null;
  private chartAnnotations: ChartAnnotation[] = [];
  private productionEvents: ProductionEvent[] = [];
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() { this.loadOEE(); }

  ngOnDestroy() {
    try { this.oeeTrendChart?.destroy(); } catch (e) {}
    this.oeeTrendChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  setOeeGranularity(g: 'day' | 'shift') {
    this.oeeGranularity = g;
    this.oeeLoaded = false;
    this.loadOEE();
  }

  loadOEE() {
    if (this.oeeLoading) return;
    this.oeeLoading = true;
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 29);
    const fmt = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    this.loadAnnotations(fmt(startDate), fmt(endDate));

    this.rebotlingService.getOEE('month').pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((oeeRes: any) => {
      if (oeeRes?.success && oeeRes.data) {
        this.oeeData = oeeRes.data;
      }
      this.rebotlingService.getOEETrend(30, this.oeeGranularity).pipe(
        timeout(8000),
        takeUntil(this.destroy$),
        catchError(() => of(null))
      ).subscribe((trendRes: any) => {
        this.oeeLoading = false;
        if (trendRes?.success && trendRes.data) {
          this.oeeTrendDays = trendRes.data;
          this.oeeLoaded = true;
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderOEETrendChart(); }, 100));
        } else {
          this.oeeLoaded = true;
        }
      });
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
        if (this.oeeLoaded && this.oeeTrendDays.length) {
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderOEETrendChart(); }, 0));
        }
      }
    });
  }

  private eventColor(type: string): string {
    const colors: Record<string, string> = {
      'underhall': '#f97316', 'ny_operator': '#3b82f6',
      'mal_andring': '#a855f7', 'rekord': '#eab308', 'ovrigt': '#6b7280',
    };
    return colors[type] ?? '#6b7280';
  }

  private renderOEETrendChart() {
    try { this.oeeTrendChart?.destroy(); } catch (e) {}
    this.oeeTrendChart = null;
    const canvas = document.getElementById('oeeTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.oeeTrendDays.length) return;

    const labels = this.oeeTrendDays.map(d => d.label ?? d.date.substring(5));
    const productionEventAnnotations: ChartAnnotation[] = this.productionEvents
      .filter((e: any) => {
        const shortDate = e.event_date.substring(5);
        return labels.some(l => l.includes(shortDate));
      })
      .map((e: any) => ({
        date: e.event_date,
        dateShort: e.event_date.substring(5),
        type: 'audit' as const,
        label: e.title,
        eventType: e.event_type,
        color: this.eventColor(e.event_type)
      }));
    const combinedAnnotations = [...this.chartAnnotations, ...productionEventAnnotations];

    if (this.oeeTrendChart) { (this.oeeTrendChart as any).destroy(); }
    this.oeeTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'OEE %', data: this.oeeTrendDays.map(d => d.oee), borderColor: '#4299e1', backgroundColor: 'rgba(66,153,225,0.1)', fill: true, tension: 0.3, pointRadius: 3, borderWidth: 2, yAxisID: 'y' },
          { label: 'Tillganglighet %', data: this.oeeTrendDays.map(d => d.availability), borderColor: '#48bb78', borderDash: [5, 3], tension: 0.3, pointRadius: 2, borderWidth: 1.5, fill: false, yAxisID: 'y' },
          { label: 'Prestanda %', data: this.oeeTrendDays.map(d => d.performance), borderColor: '#ecc94b', borderDash: [5, 3], tension: 0.3, pointRadius: 2, borderWidth: 1.5, fill: false, yAxisID: 'y' },
          { label: 'Kvalitet %', data: this.oeeTrendDays.map(d => d.quality), borderColor: '#fc8181', borderDash: [5, 3], tension: 0.3, pointRadius: 2, borderWidth: 1.5, fill: false, yAxisID: 'y' },
          { label: 'WCM 85%', data: labels.map(() => 85), borderColor: '#48bb78', borderDash: [8, 4], borderWidth: 1.5, pointRadius: 0, fill: false, tension: 0, type: 'line' as const },
          { label: 'Branschsnitt 70%', data: labels.map(() => 70), borderColor: '#ed8936', borderDash: [4, 4], borderWidth: 1, pointRadius: 0, fill: false, tension: 0, type: 'line' as const }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: { backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0', borderColor: '#4299e1', borderWidth: 1 },
          verticalAnnotations: { annotations: combinedAnnotations }
        } as any,
        scales: {
          x: { ticks: { color: '#718096', maxTicksLimit: 10, maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { beginAtZero: true, max: 100, ticks: { color: '#718096', callback: (v: any) => v + '%' }, grid: { color: 'rgba(255,255,255,0.04)' } }
        }
      }
    });
  }

  getOEEBarWidth(value: number): number {
    return Math.min(100, Math.max(0, value));
  }

  getOEEBarClass(value: number): string {
    if (value >= 75) return 'bg-success';
    if (value >= 50) return 'bg-warning';
    return 'bg-danger';
  }
}
