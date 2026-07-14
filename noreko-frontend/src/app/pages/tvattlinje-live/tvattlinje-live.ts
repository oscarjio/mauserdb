import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Subject, of } from 'rxjs';
import { catchError, finalize, takeUntil, timeout } from 'rxjs/operators';
import { TvattlinjeService, LineStatusResponse, TvattlinjeLiveStatsResponse, RastStatusResponse, DriftstoppStatusResponse } from '../../services/tvattlinje.service';

@Component({
  standalone: true,
  selector: 'app-tvattlinje-live',
  imports: [DatePipe, DecimalPipe],
  templateUrl: './tvattlinje-live.html',
  styleUrl: './tvattlinje-live.css'
})
export class TvattlinjeLivePage implements OnInit, OnDestroy {
  now = new Date();

  private destroy$ = new Subject<void>();
  private intervalId: any;

  private isFetchingLineStatus = false;
  private isFetchingLiveStats  = false;
  private isFetchingRast       = false;
  private isFetchingDriftstopp = false;

  // Rast/driftstopp poll slower than clock (every 5s instead of 2s)
  private slowTickCount = 0;

  // Data freshness
  lastDataUpdate: Date | null = null;
  dataAgeSec: number = 0;

  get freshnessClass(): string {
    if (this.lastDataUpdate === null) return 'freshness-unknown';
    if (this.dataAgeSec > 60) return 'freshness-stale';
    if (this.dataAgeSec > 15) return 'freshness-warning';
    return 'freshness-ok';
  }

  get freshnessLabel(): string {
    if (this.lastDataUpdate === null) return 'Väntar på data...';
    if (this.dataAgeSec > 60) return `Ingen data på ${this.dataAgeSec}s`;
    if (this.dataAgeSec > 15) return `Uppdaterad ${this.dataAgeSec}s sedan`;
    return 'Live';
  }

  // Line status
  isLineRunning: boolean = false;
  statusBarClass: string = 'status-bar-off';

  // Live stats
  ibcToday: number = 0;
  ibcTarget: number = 0;
  utetemperatur: number | null = null;
  productionPercentage: number = 0;
  taktPercentage: number = 0;

  // Rast status
  onRast: boolean = false;
  rastMinutesToday: number = 0;
  rastCountToday: number = 0;

  // Driftstopp
  onDriftstopp: boolean = false;
  driftstoppMinutesToday: number = 0;
  driftstoppCountToday: number = 0;

  // Speedometer
  needleRotation: number = -90;

  get rastTimeLabel(): string {
    const h = Math.floor(this.rastMinutesToday / 60);
    const m = Math.round(this.rastMinutesToday % 60);
    return h > 0 ? `${h}h ${m}m rast` : `${m}m rast`;
  }

  get driftstoppTimeLabel(): string {
    const h = Math.floor(this.driftstoppMinutesToday / 60);
    const m = Math.round(this.driftstoppMinutesToday % 60);
    return h > 0 ? `${h}h ${m}m driftstopp` : `${m}m driftstopp`;
  }

  constructor(private tvattlinjeService: TvattlinjeService) {}

  ngOnInit() {
    // Single interval drives all polling — avoids multiple competing timers
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.updateDataAge();
      this.fetchLineStatus();
      this.fetchLiveStats();
      // Rast + driftstopp every ~6s (every 3rd tick of the 2s interval)
      this.slowTickCount++;
      if (this.slowTickCount >= 3) {
        this.slowTickCount = 0;
        this.fetchRastStatus();
        this.fetchDriftstoppStatus();
      }
    }, 2000);

    // Initial fetches — stagger slightly to avoid hitting all 4 at once on load
    this.fetchLineStatus();
    this.fetchLiveStats();
    setTimeout(() => { this.fetchRastStatus(); this.fetchDriftstoppStatus(); }, 500);
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    clearInterval(this.intervalId);
  }

  private updateStatusBar() {
    this.statusBarClass = this.onDriftstopp
      ? 'status-bar-driftstopp'
      : this.onRast
        ? 'status-bar-rast'
        : this.isLineRunning
          ? 'status-bar-on'
          : 'status-bar-off';
  }

  private updateDataAge() {
    if (this.lastDataUpdate) {
      this.dataAgeSec = Math.round((Date.now() - this.lastDataUpdate.getTime()) / 1000);
    }
  }

  private fetchLineStatus() {
    if (this.isFetchingLineStatus) return;
    this.isFetchingLineStatus = true;
    this.tvattlinjeService
      .getRunningStatus()
      .pipe(
        timeout(8000),
        catchError(() => of<LineStatusResponse | null>(null)),
        finalize(() => { this.isFetchingLineStatus = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe((res: LineStatusResponse | null) => {
        if (res?.success && res.data) {
          this.isLineRunning = ((res.data.running as any) == 1 || res.data.running === true);
          if (res.data['lastUpdate']) {
            this.lastDataUpdate = new Date(String(res.data['lastUpdate']).replace(' ', 'T'));
            this.dataAgeSec = Math.round((Date.now() - this.lastDataUpdate.getTime()) / 1000);
          }
          this.updateStatusBar();
        }
      });
  }

  private fetchLiveStats() {
    if (this.isFetchingLiveStats) return;
    this.isFetchingLiveStats = true;
    this.tvattlinjeService
      .getLiveStats()
      .pipe(
        timeout(8000),
        catchError(() => of<TvattlinjeLiveStatsResponse | null>(null)),
        finalize(() => { this.isFetchingLiveStats = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe((res: TvattlinjeLiveStatsResponse | null) => {
        if (res?.success && res.data) {
          this.ibcToday = res.data.ibcToday;
          this.ibcTarget = res.data.ibcTarget;
          this.utetemperatur = res.data.utetemperatur;
          this.productionPercentage = res.data.productionPercentage ?? 0;
          this.taktPercentage = res.data.taktPercentage ?? 0;
          this.updateSpeedometer();
        }
      });
  }

  private fetchRastStatus() {
    if (this.isFetchingRast) return;
    this.isFetchingRast = true;
    this.tvattlinjeService
      .getRastStatus()
      .pipe(
        timeout(8000),
        catchError(() => of<RastStatusResponse | null>(null)),
        finalize(() => { this.isFetchingRast = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe((res: RastStatusResponse | null) => {
        if (res?.success && res.data) {
          this.onRast = res.data.on_rast;
          this.rastMinutesToday = res.data.rast_minutes_today;
          this.rastCountToday = res.data.rast_count_today;
          this.updateStatusBar();
        }
      });
  }

  private fetchDriftstoppStatus() {
    if (this.isFetchingDriftstopp) return;
    this.isFetchingDriftstopp = true;
    this.tvattlinjeService
      .getDriftstoppStatus()
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        finalize(() => { this.isFetchingDriftstopp = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe((res: DriftstoppStatusResponse | null) => {
        if (res?.success && res.data) {
          this.onDriftstopp = res.data.on_driftstopp;
          this.driftstoppMinutesToday = res.data.driftstopp_minutes_today;
          this.driftstoppCountToday = res.data.driftstopp_count_today;
          this.updateStatusBar();
        }
      });
  }

  private updateSpeedometer() {
    const pct = Math.min(Math.max(this.taktPercentage, 0), 200);
    this.needleRotation = -90 + (pct / 200) * 180;
  }
}
