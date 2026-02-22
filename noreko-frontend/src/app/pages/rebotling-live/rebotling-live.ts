import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe, DecimalPipe, NgClass, NgIf } from '@angular/common';
import { of } from 'rxjs';
import { catchError, finalize, timeout } from 'rxjs/operators';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse, OEEResponse, RastStatusResponse } from '../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-live',
  imports: [DatePipe, DecimalPipe, NgClass, NgIf],
  templateUrl: './rebotling-live.html',
  styleUrl: './rebotling-live.css'
})
export class RebotlingLivePage implements OnInit, OnDestroy {
  now = new Date();
  intervalId: any;
  private isFetchingLiveStats = false;
  private isFetchingLineStatus = false;

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
  
  // Rebotling data
  rebotlingToday: number = 0;
  rebotlingTarget: number = 0;
  rebotlingThisHour: number = 0;
  hourlyTarget: number = 0;
  ibcToday: number = 0;
  productionPercentage: number = 0;
  utetemperatur: number | null = null;
  
  // Speedometer properties
  needleRotation: number = -150; // Start position
  statusText: string = 'Bra produktion';
  statusBadgeClass: string = 'bg-success';
  
  // Line status
  isLineRunning: boolean = false;
  statusBarClass: string = 'status-bar-off';

  // Rast
  onRast: boolean = false;
  rastMinutesToday: number = 0;
  rastCountToday: number = 0;
  private isFetchingRast = false;
  private rastIntervalId: any;

  get rastTimeLabel(): string {
    const h = Math.floor(this.rastMinutesToday / 60);
    const m = Math.round(this.rastMinutesToday % 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m} min`;
  }

  // OEE
  oee: number | null = null;
  oeeAvailability: number = 0;
  oeePerformance: number = 0;
  oeeQuality: number = 0;
  oeeRastHours: number = 0;
  oeeRuntimeHours: number = 0;
  private isFetchingOEE = false;

  // Goal tracker
  dailyGoal: number = 120; // Will be calculated from hourlyTarget * shift hours
  shiftHours: number = 8;

  get ibcRemaining(): number {
    return Math.max(0, this.dailyGoal - this.ibcToday);
  }

  get goalProgress(): number {
    if (this.dailyGoal <= 0) return 0;
    return Math.min(100, Math.round((this.ibcToday / this.dailyGoal) * 100));
  }

  get estimatedCompletion(): string {
    if (this.ibcToday >= this.dailyGoal) return 'Mål uppnått!';
    if (this.hourlyTarget <= 0 || !this.isLineRunning) return '--';
    const hoursLeft = this.ibcRemaining / this.hourlyTarget;
    const completionTime = new Date(Date.now() + hoursLeft * 3600000);
    const h = completionTime.getHours().toString().padStart(2, '0');
    const m = completionTime.getMinutes().toString().padStart(2, '0');
    return `~${h}:${m}`;
  }

  get goalStatusClass(): string {
    if (this.goalProgress >= 100) return 'goal-complete';
    if (this.goalProgress >= 75) return 'goal-good';
    if (this.goalProgress >= 50) return 'goal-mid';
    return 'goal-low';
  }

  constructor(private rebotlingService: RebotlingService) {}

  private oeeIntervalId: any;

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.updateDataAge();
      this.fetchLiveStats();
      this.fetchLineStatus();
    }, 2000);
    this.oeeIntervalId = setInterval(() => {
      this.fetchOEE();
    }, 30000);
    this.rastIntervalId = setInterval(() => {
      this.fetchRastStatus();
    }, 10000);
    this.fetchLiveStats();
    this.fetchLineStatus();
    this.fetchOEE();
    this.fetchRastStatus();
  }

  ngOnDestroy() {
    clearInterval(this.intervalId);
    clearInterval(this.oeeIntervalId);
    clearInterval(this.rastIntervalId);
  }

  private updateDataAge() {
    if (this.lastDataUpdate) {
      this.dataAgeSec = Math.round((Date.now() - this.lastDataUpdate.getTime()) / 1000);
    }
  }

  private fetchLiveStats() {
    // Undvik att starta flera parallella anrop om backend slutar svara
    if (this.isFetchingLiveStats) {
      return;
    }
    this.isFetchingLiveStats = true;

    this.rebotlingService
      .getLiveStats()
      .pipe(
        // Sätt en timeout så anropet inte hänger för evigt om backend inte svarar
        timeout(5000),
        catchError((err) => {
          console.error('Fel vid hämtning av rebotling live stats:', err);
          // Fortsätt strömmen men utan att uppdatera data
          return of<RebotlingLiveStatsResponse | null>(null);
        }),
        finalize(() => {
          this.isFetchingLiveStats = false;
        })
      )
      .subscribe((res: RebotlingLiveStatsResponse | null) => {
        if (res && res.success && res.data) {
          this.lastDataUpdate = new Date();
          this.dataAgeSec = 0;
          this.rebotlingToday = res.data.rebotlingToday;
          this.rebotlingTarget = res.data.rebotlingTarget;
          this.rebotlingThisHour = res.data.rebotlingThisHour;
          this.hourlyTarget = res.data.hourlyTarget;
          this.ibcToday = res.data.ibcToday || 0;

          this.productionPercentage = res.data.productionPercentage || 0;

          // Calculate daily goal from hourly target * shift hours
          if (this.hourlyTarget > 0) {
            this.dailyGoal = Math.round(this.hourlyTarget * this.shiftHours);
          }

          // Hämta utetemperatur
          this.utetemperatur = res.data.utetemperatur ?? null;

          this.updateSpeedometer();
        }
      });
  }

  private fetchLineStatus() {
    // Undvik parallella status-anrop om backend inte svarar
    if (this.isFetchingLineStatus) {
      return;
    }
    this.isFetchingLineStatus = true;

    this.rebotlingService
      .getRunningStatus()
      .pipe(
        timeout(5000),
        catchError((err) => {
          console.error('Fel vid hämtning av rebotling linjestatus:', err);
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

  private fetchRastStatus() {
    if (this.isFetchingRast) return;
    this.isFetchingRast = true;
    this.rebotlingService
      .getRastStatus()
      .pipe(
        timeout(5000),
        catchError(() => of<RastStatusResponse | null>(null)),
        finalize(() => { this.isFetchingRast = false; })
      )
      .subscribe((res: RastStatusResponse | null) => {
        if (res && res.success && res.data) {
          this.onRast = res.data.on_rast;
          this.rastMinutesToday = res.data.rast_minutes_today;
          this.rastCountToday = res.data.rast_count_today;
        }
      });
  }

  private fetchOEE() {
    if (this.isFetchingOEE) {
      return;
    }
    this.isFetchingOEE = true;

    this.rebotlingService
      .getOEE('today')
      .pipe(
        timeout(5000),
        catchError((err) => {
          console.error('Fel vid hämtning av OEE:', err);
          return of<OEEResponse | null>(null);
        }),
        finalize(() => {
          this.isFetchingOEE = false;
        })
      )
      .subscribe((res: OEEResponse | null) => {
        if (res && res.success && res.data) {
          this.oee = res.data.oee;
          this.oeeAvailability = res.data.availability;
          this.oeePerformance = res.data.performance;
          this.oeeQuality = res.data.quality;
          this.oeeRastHours = res.data.rast_hours ?? 0;
          this.oeeRuntimeHours = res.data.runtime_hours ?? 0;
        }
      });
  }

  private updateSpeedometer() {
    // Använd samma produktionsprocent som visas i "Produktion"
    // Max 200% för speedometern
    const percentage = Math.min(Math.max(this.productionPercentage, 0), 200);
    
    // Convert percentage to needle rotation (-180 to 0 degrees)
    // -180 degrees är vänster (0%), 0 degrees är höger (200%)
    // Mappar 0-200% till -180 till 0 grader
    // Start position är -100 (ungefär 25% på speedometern)
    this.needleRotation = -100 + (percentage / 200) * 180;
    
    // Update status based on performance
    if (percentage >= 120) {
      this.statusText = 'Mycket bra produktion';
      this.statusBadgeClass = 'bg-success';
    } else if (percentage >= 100) {
      this.statusText = 'Bra produktion';
      this.statusBadgeClass = 'bg-success';
    } else if (percentage >= 60) {
      this.statusText = 'Produktion under målet';
      this.statusBadgeClass = 'bg-warning';
    } else {
      this.statusText = 'Låg produktion';
      this.statusBadgeClass = 'bg-danger';
    }
  }
}
