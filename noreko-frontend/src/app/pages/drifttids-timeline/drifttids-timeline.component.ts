import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  DrifttidsTimelineService,
  TimelineSegment,
  TimelineData,
  TimelineSummaryData,
  OrsaksfordelningData,
  VeckotrendData,
} from '../../services/drifttids-timeline.service';
import { localDateStr, parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-drifttids-timeline',
  templateUrl: './drifttids-timeline.component.html',
  styleUrls: ['./drifttids-timeline.component.css'],
  imports: [CommonModule, FormsModule],
})
export class DrifttidsTimelineComponent implements OnInit, OnDestroy {
  // -- Datum --
  selectedDate: string = this.todayStr();

  // -- Laddning --
  loadingSummary  = false;
  loadingTimeline = false;

  // -- Fel --
  errorSummary  = false;
  errorTimeline = false;

  // -- Data --
  summary: TimelineSummaryData | null = null;
  timelineData: TimelineData | null   = null;

  // -- Valt segment (detaljer) --
  selectedSegment: TimelineSegment | null = null;

  // -- Tooltip --
  tooltipSegment: TimelineSegment | null = null;
  tooltipX = 0;
  tooltipY = 0;

  // -- Konstanter för tidslinje --
  readonly TIMELINE_START_H = 6;   // 06:00
  readonly TIMELINE_END_H   = 22;  // 22:00
  readonly TIMELINE_DURATION_MIN = (22 - 6) * 60; // 960 min

  // -- Orsaksfordelning (session #376) --
  orsaksfordelning: OrsaksfordelningData | null = null;
  loadingOrsak = false;
  errorOrsak = false;

  // -- Veckotrend (session #376) --
  veckotrend: VeckotrendData | null = null;
  loadingVeckotrend = false;
  errorVeckotrend = false;
  veckotrendDagar = 7;

  // -- Charts (session #376) --
  private orsakChart: Chart | null = null;
  private veckotrendChart: Chart | null = null;
  private chartTimers: ReturnType<typeof setTimeout>[] = [];

  // -- Cached computed properties (rebuilt on data change) --
  cachedTimelineHours: number[] = [];
  cachedVisibleSegments: TimelineSegment[] = [];
  cachedRunningCount = 0;
  cachedStoppedCount = 0;

  private destroy$ = new Subject<void>();

  constructor(private svc: DrifttidsTimelineService) {}

  ngOnInit(): void {
    this.cachedTodayStr = this.todayStr();
    this.rebuildTimelineHours();
    this.loadAll();
  }

  ngOnDestroy(): void {
    try { this.orsakChart?.destroy(); } catch (_) {}
    this.orsakChart = null;
    try { this.veckotrendChart?.destroy(); } catch (_) {}
    this.veckotrendChart = null;
    this.chartTimers.forEach(t => clearTimeout(t));
    this.chartTimers = [];
    this.destroy$.next();
    this.destroy$.complete();
  }

  // =================================================================
  // Datum
  // =================================================================

  todayStr(): string {
    return localDateStr(new Date());
  }

  onDateChange(): void {
    this.selectedSegment = null;
    this.tooltipSegment  = null;
    this.updateIsToday();
    this.loadAll();
  }

  prevDay(): void {
    const d = new Date(this.selectedDate + 'T00:00:00');
    d.setDate(d.getDate() - 1);
    this.selectedDate = localDateStr(d);
    this.onDateChange();
  }

  nextDay(): void {
    const d = new Date(this.selectedDate + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    const today = this.todayStr();
    if (localDateStr(d) <= today) {
      this.selectedDate = localDateStr(d);
      this.onDateChange();
    }
  }

  // isToday ar nu en cached property, se updateIsToday()

  isToday = true;
  cachedTodayStr = '';

  private updateIsToday(): void {
    this.cachedTodayStr = this.todayStr();
    this.isToday = this.selectedDate === this.cachedTodayStr;
  }

  formatDisplayDate(dateStr: string): string {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.chartTimers.forEach(t => clearTimeout(t));
    this.chartTimers = [];
    this.loadSummary();
    this.loadTimeline();
    this.loadOrsaksfordelning();
    this.loadVeckotrend();
  }

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getDaySummary(this.selectedDate)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success) {
          this.summary = res.data;
        } else {
          this.errorSummary = true;
          this.summary = null;
        }
      });
  }

  loadTimeline(): void {
    this.loadingTimeline = true;
    this.errorTimeline   = false;
    this.selectedSegment = null;
    this.svc.getDayTimeline(this.selectedDate)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTimeline = false;
        if (res?.success) {
          this.timelineData = res.data;
          this.rebuildCachedSegments();
        } else {
          this.errorTimeline = true;
          this.timelineData  = null;
          this.rebuildCachedSegments();
        }
      });
  }

  // =================================================================
  // Tidslinje-beräkningar
  // =================================================================

  /**
   * Beräkna vänster-position (%) för ett segment på tidslinjen.
   */
  segmentLeft(seg: TimelineSegment): number {
    const segStart = parseLocalDate(seg.start);
    const startMin = segStart.getHours() * 60 + segStart.getMinutes();
    const clampedMin = Math.max(startMin, this.TIMELINE_START_H * 60);
    const offsetMin = clampedMin - this.TIMELINE_START_H * 60;
    return Math.max(0, Math.min(100, (offsetMin / this.TIMELINE_DURATION_MIN) * 100));
  }

  /**
   * Beräkna bredd (%) för ett segment på tidslinjen.
   */
  segmentWidth(seg: TimelineSegment): number {
    const segStart = parseLocalDate(seg.start);
    const segEnd   = parseLocalDate(seg.end);
    const startMin = segStart.getHours() * 60 + segStart.getMinutes();
    const endMin   = segEnd.getHours() * 60 + segEnd.getMinutes();

    const clampedStart = Math.max(startMin, this.TIMELINE_START_H * 60);
    const clampedEnd   = Math.min(endMin, this.TIMELINE_END_H * 60);
    const dur = Math.max(0, clampedEnd - clampedStart);
    return Math.max(0, Math.min(100, (dur / this.TIMELINE_DURATION_MIN) * 100));
  }

  /**
   * Generera timrubriker för tidslinjen (06, 07, ..., 22).
   * Byggs en gang vid init (konstant).
   */
  private rebuildTimelineHours(): void {
    const hours = [];
    for (let h = this.TIMELINE_START_H; h <= this.TIMELINE_END_H; h++) {
      hours.push(h);
    }
    this.cachedTimelineHours = hours;
  }

  hourLeft(hour: number): number {
    return ((hour - this.TIMELINE_START_H) / (this.TIMELINE_END_H - this.TIMELINE_START_H)) * 100;
  }

  /**
   * Filtrera bort segment utanför synlig tidszon och med noll bredd.
   * Byggs om nar timelineData andras.
   */
  private rebuildCachedSegments(): void {
    if (!this.timelineData?.segments) {
      this.cachedVisibleSegments = [];
      this.cachedRunningCount = 0;
      this.cachedStoppedCount = 0;
      return;
    }
    this.cachedVisibleSegments = this.timelineData.segments.filter(seg => this.segmentWidth(seg) > 0);
    this.cachedRunningCount = this.timelineData.segments.filter(s => s.type === 'running').length;
    this.cachedStoppedCount = this.timelineData.segments.filter(s => s.type === 'stopped').length;
  }

  // =================================================================
  // Interaktion
  // =================================================================

  onSegmentClick(seg: TimelineSegment): void {
    if (this.selectedSegment === seg) {
      this.selectedSegment = null;
    } else {
      this.selectedSegment = seg;
    }
  }

  onSegmentMouseEnter(seg: TimelineSegment, event: MouseEvent): void {
    this.tooltipSegment = seg;
    this.updateTooltipPos(event);
  }

  onSegmentMouseMove(event: MouseEvent): void {
    if (this.tooltipSegment) {
      this.updateTooltipPos(event);
    }
  }

  onSegmentMouseLeave(): void {
    this.tooltipSegment = null;
  }

  private updateTooltipPos(event: MouseEvent): void {
    this.tooltipX = event.clientX + 12;
    this.tooltipY = event.clientY - 10;
  }

  // Removed @HostListener('document:mousemove') — onSegmentMouseMove already handles tooltip
  // position when hovering over segments. The global listener was triggering unnecessary
  // change detection on every mouse move anywhere on the page.

  // =================================================================
  // Hjälpmetoder — formatering
  // =================================================================

  formatTime(datetimeStr: string): string {
    const d = parseLocalDate(datetimeStr);
    return d.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
  }

  formatDuration(minutes: number): string {
    if (minutes <= 0) return '0 min';
    const h   = Math.floor(minutes / 60);
    const min = Math.round(minutes % 60);
    if (h === 0) return `${min} min`;
    if (min === 0) return `${h}h`;
    return `${h}h ${min}min`;
  }

  segmentTypeLabel(type: string): string {
    if (type === 'running')   return 'Körning';
    if (type === 'stopped')   return 'Stopp';
    if (type === 'unplanned') return 'Ej planerat';
    return type;
  }

  segmentColor(type: string): string {
    if (type === 'running')   return '#48bb78';
    if (type === 'stopped')   return '#fc8181';
    if (type === 'unplanned') return '#4a5568';
    return '#8fa3b8';
  }

  utnyttjandegradFarg(pct: number): string {
    if (pct >= 75) return '#48bb78';
    if (pct >= 50) return '#ecc94b';
    return '#fc8181';
  }

  drifttidTimmar(min: number): string {
    return this.formatDuration(min);
  }

  // =================================================================
  // Orsaksfordelning (session #376)
  // =================================================================

  loadOrsaksfordelning(): void {
    this.loadingOrsak = true;
    this.errorOrsak   = false;
    this.svc.getOrsaksfordelning(this.selectedDate)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOrsak = false;
        if (res?.success) {
          this.orsaksfordelning = res.data;
          this.chartTimers.push(setTimeout(() => {
            if (!this.destroy$.closed) this.buildOrsakChart();
          }, 80));
        } else {
          this.errorOrsak = true;
          this.orsaksfordelning = null;
        }
      });
  }

  private buildOrsakChart(): void {
    try { this.orsakChart?.destroy(); } catch (_) {}
    this.orsakChart = null;

    if (!this.orsaksfordelning?.orsaker?.length) return;
    const canvas = document.getElementById('orsakChart') as HTMLCanvasElement;
    if (!canvas) return;

    const data = this.orsaksfordelning.orsaker;
    const colors = [
      '#fc8181', '#f6ad55', '#68d391', '#63b3ed', '#9f7aea',
      '#4fd1c5', '#fbd38d', '#b794f4', '#ed8936', '#a0aec0',
    ];

    // Lagg till okanda stopp om de finns
    const labels = data.map(d => d.orsak);
    const values = data.map(d => d.total_min);
    if (this.orsaksfordelning.okand_stopp_min > 0) {
      labels.push('Okand orsak');
      values.push(this.orsaksfordelning.okand_stopp_min);
    }

    this.orsakChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: labels.map((_, i) => colors[i % colors.length]),
          borderColor: '#1a202c',
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#e2e8f0', font: { size: 12 }, padding: 12 },
          },
          tooltip: {
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx: any) => {
                const min = ctx.parsed;
                const total = values.reduce((a, b) => a + b, 0);
                const pct = total > 0 ? Math.round((min / total) * 100) : 0;
                return ` ${ctx.label}: ${min.toFixed(1)} min (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  // =================================================================
  // Veckotrend (session #376)
  // =================================================================

  loadVeckotrend(): void {
    this.loadingVeckotrend = true;
    this.errorVeckotrend   = false;
    this.svc.getVeckotrend(this.veckotrendDagar)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingVeckotrend = false;
        if (res?.success) {
          this.veckotrend = res.data;
          this.chartTimers.push(setTimeout(() => {
            if (!this.destroy$.closed) this.buildVeckotrendChart();
          }, 80));
        } else {
          this.errorVeckotrend = true;
          this.veckotrend = null;
        }
      });
  }

  onVeckotrendDaysChange(): void {
    this.loadVeckotrend();
  }

  private buildVeckotrendChart(): void {
    try { this.veckotrendChart?.destroy(); } catch (_) {}
    this.veckotrendChart = null;

    if (!this.veckotrend?.trend?.length) return;
    const canvas = document.getElementById('veckotrendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const trend = this.veckotrend.trend;
    const labels   = trend.map(d => d.datum);
    const drifttid = trend.map(d => d.drifttid_min);
    const stopptid = trend.map(d => d.stopptid_min);
    const utnyttj  = trend.map(d => d.utnyttjandegrad_pct);

    this.veckotrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Drifttid (min)',
            data: drifttid,
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72, 187, 120, 0.1)',
            borderWidth: 2,
            fill: true,
            pointRadius: 4,
            tension: 0.3,
            yAxisID: 'yLeft',
          },
          {
            label: 'Stopptid (min)',
            data: stopptid,
            borderColor: '#fc8181',
            backgroundColor: 'rgba(252, 129, 129, 0.1)',
            borderWidth: 2,
            fill: true,
            pointRadius: 4,
            tension: 0.3,
            yAxisID: 'yLeft',
          },
          {
            label: 'Utnyttjandegrad %',
            data: utnyttj,
            borderColor: '#63b3ed',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 3,
            tension: 0.3,
            fill: false,
            yAxisID: 'yRight',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 12 } } },
          tooltip: {
            intersect: false,
            mode: 'nearest',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && idx < trend.length) {
                  return [`Antal stopp: ${trend[idx].antal_stopp}`];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45 },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'Minuter', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          yRight: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'Utnyttjandegrad %', color: '#63b3ed' },
            ticks: { color: '#63b3ed', callback: (val: any) => `${val}%` },
            grid:  { drawOnChartArea: false },
            beginAtZero: true,
            max: 100,
          },
        },
      },
    });
  }

  // runningCount och stoppedCount ar nu cachedRunningCount / cachedStoppedCount (beraknas i rebuildCachedSegments)
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
