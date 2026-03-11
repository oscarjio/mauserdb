import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import {
  ProduktionspulsService,
  PulsItem,
  HourData
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

  items: PulsItem[] = [];
  currentHour: HourData = { ibc_count: 0, godkanda: 0, kasserade: 0, snitt_cykeltid: null };
  previousHour: HourData = { ibc_count: 0, godkanda: 0, kasserade: 0, snitt_cykeltid: null };
  loading = true;
  paused = false;

  constructor(private pulsService: ProduktionspulsService) {}

  ngOnInit(): void {
    this.fetchAll();
    this.pollInterval = setInterval(() => this.fetchAll(), 15000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
    }
  }

  fetchAll(): void {
    this.pulsService.getLatest(50).pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success && Array.isArray(res.data)) {
        this.items = res.data;
        this.loading = false;
      }
    });

    this.pulsService.getHourlyStats().pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.currentHour = res.current;
        this.previousHour = res.previous;
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
}
