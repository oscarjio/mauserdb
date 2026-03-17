import { Component, OnInit, OnDestroy, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  DrifttidsTimelineService,
  TimelineSegment,
  TimelineData,
  TimelineSummaryData,
} from '../../services/drifttids-timeline.service';
import { localDateStr, parseLocalDate } from '../../utils/date-utils';

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

  // -- Cached computed properties (rebuilt on data change) --
  cachedTimelineHours: number[] = [];
  cachedVisibleSegments: TimelineSegment[] = [];
  cachedRunningCount = 0;
  cachedStoppedCount = 0;

  private destroy$ = new Subject<void>();

  constructor(private svc: DrifttidsTimelineService) {}

  ngOnInit(): void {
    this.rebuildTimelineHours();
    this.loadAll();
  }

  ngOnDestroy(): void {
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

  private updateIsToday(): void {
    this.isToday = this.selectedDate === this.todayStr();
  }

  formatDisplayDate(dateStr: string): string {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadSummary();
    this.loadTimeline();
  }

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getDaySummary(this.selectedDate)
      .pipe(takeUntil(this.destroy$))
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
      .pipe(takeUntil(this.destroy$))
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

  @HostListener('document:mousemove', ['$event'])
  onDocMouseMove(event: MouseEvent): void {
    if (this.tooltipSegment) {
      this.tooltipX = event.clientX + 12;
      this.tooltipY = event.clientY - 10;
    }
  }

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
    return '#718096';
  }

  utnyttjandegradFarg(pct: number): string {
    if (pct >= 75) return '#48bb78';
    if (pct >= 50) return '#ecc94b';
    return '#fc8181';
  }

  drifttidTimmar(min: number): string {
    return this.formatDuration(min);
  }

  // runningCount och stoppedCount ar nu cachedRunningCount / cachedStoppedCount (beraknas i rebuildCachedSegments)
  trackByIndex(index: number): number { return index; }
}
