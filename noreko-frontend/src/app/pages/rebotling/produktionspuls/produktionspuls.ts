import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import {
  ProduktionspulsService,
  PulsItem,
  HourData,
  PulseEvent,
  LiveKpiResponse
} from '../../../services/produktionspuls.service';

@Component({
  selector: 'app-produktionspuls',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './produktionspuls.html',
  styleUrl: './produktionspuls.css'
})
export class ProduktionspulsPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;

  // Legacy ticker data
  items: PulsItem[] = [];
  currentHour: HourData = { ibc_count: 0, godkanda: 0, kasserade: 0, snitt_cykeltid: null };
  previousHour: HourData = { ibc_count: 0, godkanda: 0, kasserade: 0, snitt_cykeltid: null };

  // New pulse data
  pulseEvents: PulseEvent[] = [];
  liveKpi: LiveKpiResponse | null = null;

  loading = true;
  paused = false;

  constructor(private pulsService: ProduktionspulsService) {}

  ngOnInit(): void {
    this.fetchAll();
    // Auto-refresh var 30:e sekund
    this.pollInterval = setInterval(() => this.fetchAll(), 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
    }
  }

  fetchAll(): void {
    // Legacy: senaste IBC:er for ticker
    this.pulsService.getLatest(50).pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success && Array.isArray(res.data)) {
        this.items = res.data;
        this.loading = false;
      }
    });

    // Legacy: timstatistik
    this.pulsService.getHourlyStats().pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.currentHour = res.current;
        this.previousHour = res.previous;
      }
    });

    // Ny: handelsefeed
    this.pulsService.getPulse(20).pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success && Array.isArray(res.data)) {
        this.pulseEvents = res.data;
      }
    });

    // Ny: live KPI:er
    this.pulsService.getLiveKpi().pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.liveKpi = res;
      }
    });
  }

  onTickerMouseEnter(): void {
    this.paused = true;
  }

  onTickerMouseLeave(): void {
    this.paused = false;
  }

  getItemClass(item: PulsItem): string {
    if (item.kasserad) return 'puls-item puls-kasserad';
    if (item.over_target) return 'puls-item puls-over-target';
    return 'puls-item puls-ok';
  }

  formatTime(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum);
    return d.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  formatDateTime(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum);
    return d.toLocaleString('sv-SE', {
      month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
  }

  get ibcTrend(): number {
    return this.currentHour.ibc_count - this.previousHour.ibc_count;
  }

  get cykeltidTrend(): number | null {
    if (this.currentHour.snitt_cykeltid === null || this.previousHour.snitt_cykeltid === null) return null;
    return +(this.currentHour.snitt_cykeltid - this.previousHour.snitt_cykeltid).toFixed(1);
  }

  get kvalitetPct(): number {
    if (this.currentHour.ibc_count === 0) return 0;
    return Math.round((this.currentHour.godkanda / this.currentHour.ibc_count) * 100);
  }

  get driftstatusText(): string {
    if (!this.liveKpi) return '--';
    return this.liveKpi.driftstatus.running ? 'KOR' : 'STOPP';
  }

  get driftstatusRunning(): boolean {
    return this.liveKpi?.driftstatus?.running ?? false;
  }

  get driftSedanText(): string {
    if (!this.liveKpi?.driftstatus?.sedan) return '';
    return 'sedan ' + this.formatTime(this.liveKpi.driftstatus.sedan);
  }

  get tidSedanStoppText(): string {
    if (!this.liveKpi?.tid_sedan_senaste_stopp?.minuter && this.liveKpi?.tid_sedan_senaste_stopp?.minuter !== 0) {
      return '--';
    }
    const min = this.liveKpi.tid_sedan_senaste_stopp.minuter;
    if (min === null) return '--';
    if (min < 60) return min + ' min';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h + 'h ' + m + 'min';
  }

  getEventBadgeClass(event: PulseEvent): string {
    const map: Record<string, string> = {
      success: 'bg-success',
      danger: 'bg-danger',
      warning: 'bg-warning text-dark',
      info: 'bg-info text-dark'
    };
    return 'badge ' + (map[event.color] || 'bg-secondary');
  }

  getEventTypeLabel(event: PulseEvent): string {
    const map: Record<string, string> = {
      ibc: 'IBC',
      onoff: 'Drift',
      stopp: 'Stopp'
    };
    return map[event.type] || event.type;
  }
}
