import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { of } from 'rxjs';
import { catchError, finalize, timeout } from 'rxjs/operators';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse, OEEResponse } from '../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-live',
  imports: [DatePipe, DecimalPipe],
  templateUrl: './rebotling-live.html',
  styleUrl: './rebotling-live.css'
})
export class RebotlingLivePage implements OnInit, OnDestroy {
  now = new Date();
  intervalId: any;
  private isFetchingLiveStats = false;
  private isFetchingLineStatus = false;
  
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

  // OEE
  oee: number | null = null;
  oeeAvailability: number = 0;
  oeePerformance: number = 0;
  oeeQuality: number = 0;
  private isFetchingOEE = false;

  constructor(private rebotlingService: RebotlingService) {}

  private oeeIntervalId: any;

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.fetchLiveStats();
      this.fetchLineStatus();
    }, 2000);
    this.oeeIntervalId = setInterval(() => {
      this.fetchOEE();
    }, 30000);
    this.fetchLiveStats();
    this.fetchLineStatus();
    this.fetchOEE();
  }

  ngOnDestroy() {
    clearInterval(this.intervalId);
    clearInterval(this.oeeIntervalId);
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
          this.rebotlingToday = res.data.rebotlingToday;
          this.rebotlingTarget = res.data.rebotlingTarget;
          this.rebotlingThisHour = res.data.rebotlingThisHour;
          this.hourlyTarget = res.data.hourlyTarget;
          this.ibcToday = res.data.ibcToday || 0;

          // Använd produktionsprocent från backend (beräknad baserat på runtime och antal cykler)
          this.productionPercentage = res.data.productionPercentage || 0;

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
