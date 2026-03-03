import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { of } from 'rxjs';
import { catchError, finalize, timeout } from 'rxjs/operators';
import { TvattlinjeService, LineStatusResponse, TvattlinjeLiveStatsResponse } from '../../services/tvattlinje.service';

@Component({
  standalone: true,
  selector: 'app-tvattlinje-live',
  imports: [DatePipe, DecimalPipe],
  templateUrl: './tvattlinje-live.html',
  styleUrl: './tvattlinje-live.css'
})
export class TvattlinjeLivePage implements OnInit, OnDestroy {
  now = new Date();
  intervalId: any;

  private isFetchingLineStatus = false;
  private isFetchingLiveStats = false;

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
  
  // Speedometer properties
  needleRotation: number = -150; // Start position

  get isGoalAchieved(): boolean {
    return this.productionPercentage >= 100;
  }

  get statusText(): string {
    if (!this.isLineRunning) return 'Stoppad';
    if (this.productionPercentage >= 100) return 'Bra produktion';
    if (this.productionPercentage >= 60) return 'Under mål';
    return 'Låg produktion';
  }

  get statusBadgeClass(): string {
    if (!this.isLineRunning) return 'badge bg-secondary fs-3 w-100 text-center';
    if (this.productionPercentage >= 100) return 'badge bg-success fs-3 w-100 text-center';
    if (this.productionPercentage >= 60) return 'badge bg-warning text-dark fs-3 w-100 text-center';
    return 'badge bg-danger fs-3 w-100 text-center';
  }

  constructor(private tvattlinjeService: TvattlinjeService) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.updateDataAge();
      this.fetchLineStatus();
      this.fetchLiveStats();
    }, 2000);
    this.fetchLineStatus();
    this.fetchLiveStats();
  }

  ngOnDestroy() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  }

  private updateDataAge() {
    if (this.lastDataUpdate) {
      this.dataAgeSec = Math.round((Date.now() - this.lastDataUpdate.getTime()) / 1000);
    }
  }

  private fetchLineStatus() {
    // Undvik parallella status-anrop om backend inte svarar
    if (this.isFetchingLineStatus) {
      return;
    }
    this.isFetchingLineStatus = true;

    this.tvattlinjeService
      .getRunningStatus()
      .pipe(
        timeout(5000),
        catchError((err) => {
          console.error('Fel vid hämtning av tvättlinje linjestatus:', err);
          return of<LineStatusResponse | null>(null);
        }),
        finalize(() => {
          this.isFetchingLineStatus = false;
        })
      )
      .subscribe((res: LineStatusResponse | null) => {
        if (res && res.success && res.data) {
          this.isLineRunning = res.data.running;
          this.statusBarClass = this.isLineRunning ? 'status-bar-on' : 'status-bar-off';
        }
      });
  }

  private fetchLiveStats() {
    // Undvik att starta flera parallella anrop om backend slutar svara
    if (this.isFetchingLiveStats) {
      return;
    }
    this.isFetchingLiveStats = true;

    this.tvattlinjeService
      .getLiveStats()
      .pipe(
        timeout(5000),
        catchError((err) => {
          console.error('Fel vid hämtning av tvättlinje live stats:', err);
          // Fortsätt strömmen men utan att uppdatera data
          return of<TvattlinjeLiveStatsResponse | null>(null);
        }),
        finalize(() => {
          this.isFetchingLiveStats = false;
        })
      )
      .subscribe((res: TvattlinjeLiveStatsResponse | null) => {
        if (res && res.success && res.data) {
          this.lastDataUpdate = new Date();
          this.dataAgeSec = 0;
          this.ibcToday = res.data.ibcToday;
          this.ibcTarget = res.data.ibcTarget;
          this.utetemperatur = res.data.utetemperatur;
          // Använd produktionsprocent från backend (beräknad baserat på runtime och antal cykler)
          // Kontrollera om productionPercentage finns i response, annars sätt till 0
          this.productionPercentage =
            res.data.productionPercentage !== undefined && res.data.productionPercentage !== null
              ? res.data.productionPercentage
              : 0;

          this.updateSpeedometer();
        }
      });
  }

  private updateSpeedometer() {
    const percentage = Math.min(Math.max(this.productionPercentage, 0), 200);
    // 0% = -90° (vänster), 100% = 0° (topp/mitten = "i fas"), 200% = +90° (höger)
    this.needleRotation = -90 + (percentage / 200) * 180;
  }
}
