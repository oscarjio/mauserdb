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
    // Grön om produktion är >= 100%, annars röd
    return this.productionPercentage >= 100;
  }

  constructor(private tvattlinjeService: TvattlinjeService) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
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
    // Använd samma produktionsprocent som visas från backend
    // Max 200% för speedometern
    const percentage = Math.min(Math.max(this.productionPercentage, 0), 200);
    
    // Convert percentage to needle rotation (-180 to 0 degrees)
    // -180 degrees är vänster (0%), 0 degrees är höger (200%)
    // Mappar 0-200% till -180 till 0 grader
    // Start position är -100 (ungefär 25% på speedometern)
    this.needleRotation = -100 + (percentage / 200) * 180;
  }
}
