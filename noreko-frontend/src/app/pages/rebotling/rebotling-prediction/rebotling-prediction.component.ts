import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { RebotlingService } from '../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-prediction',
  templateUrl: './rebotling-prediction.component.html',
  imports: [CommonModule]
})
export class RebotlingPredictionComponent implements OnInit, OnDestroy {
  prediktionLoaded = false;
  prediktionLoading = false;
  prediktionIBC = 0;
  prediktionMal = 0;
  prediktionPrognos = 0;
  prediktionPct = 0;
  prediktionRunningHours = 0;
  prediktionRemainingHours = 0;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.loadPrediktion();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadPrediktion() {
    if (this.prediktionLoading) return;
    this.prediktionLoading = true;
    this.rebotlingService.getLiveStats().pipe(
      timeout(6000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(liveRes => {
      if (liveRes?.success && liveRes.data) {
        this.prediktionIBC = liveRes.data.ibcToday;
        this.prediktionMal = liveRes.data.rebotlingTarget;
      }
      this.computePrediktion();
      this.prediktionLoading = false;
      this.prediktionLoaded = true;
    });
  }

  private computePrediktion() {
    const now = new Date();
    const dayStartHour = 6;
    const dayEndHour = 22;
    const minutesInDay = (dayEndHour - dayStartHour) * 60;
    const minutesSinceStart = Math.max(0, (now.getHours() - dayStartHour) * 60 + now.getMinutes());
    const minutesRemaining = Math.max(0, minutesInDay - minutesSinceStart);

    this.prediktionRunningHours = Math.round(minutesSinceStart / 60 * 10) / 10;
    this.prediktionRemainingHours = Math.round(minutesRemaining / 60 * 10) / 10;

    if (minutesSinceStart > 0 && this.prediktionIBC > 0) {
      const ratePerMin = this.prediktionIBC / minutesSinceStart;
      this.prediktionPrognos = Math.round(this.prediktionIBC + ratePerMin * minutesRemaining);
    } else {
      this.prediktionPrognos = 0;
    }

    if (this.prediktionMal > 0) {
      this.prediktionPct = Math.min(150, Math.round((this.prediktionPrognos / this.prediktionMal) * 100));
    } else {
      this.prediktionPct = 0;
    }
  }

  getPrediktionClass(): string {
    if (this.prediktionPct >= 100) return 'bg-success';
    if (this.prediktionPct >= 75) return 'bg-warning';
    return 'bg-danger';
  }

  getPrediktionTextClass(): string {
    if (this.prediktionPct >= 100) return 'text-success';
    if (this.prediktionPct >= 75) return 'text-warning';
    return 'text-danger';
  }
}
