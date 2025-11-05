import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse } from '../../services/rebotling.service';

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
  
  // Rebotling data
  rebotlingToday: number = 0;
  rebotlingTarget: number = 0;
  rebotlingThisHour: number = 0;
  hourlyTarget: number = 0;
  ibcToday: number = 0;
  productionPercentage: number = 0;
  
  // Speedometer properties
  needleRotation: number = -150; // Start position
  statusText: string = 'Bra produktion';
  statusBadgeClass: string = 'bg-success';
  
  // Line status
  isLineRunning: boolean = false;
  statusBarClass: string = 'status-bar-off';

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.fetchLiveStats();
      this.fetchLineStatus();
    }, 2000);
    this.fetchLiveStats();
    this.fetchLineStatus();
  }

  ngOnDestroy() {
    clearInterval(this.intervalId);
  }

  private fetchLiveStats() {
    this.rebotlingService.getLiveStats().subscribe((res: RebotlingLiveStatsResponse) => {
      if (res && res.success && res.data) {
        this.rebotlingToday = res.data.rebotlingToday;
        this.rebotlingTarget = res.data.rebotlingTarget;
        this.rebotlingThisHour = res.data.rebotlingThisHour;
        this.hourlyTarget = res.data.hourlyTarget;
        this.ibcToday = res.data.ibcToday || 0;
        
        // Beräkna produktionsprocent baserat på mål per timme
        if (this.hourlyTarget > 0) {
          this.productionPercentage = Math.round((this.rebotlingThisHour / this.hourlyTarget) * 100);
        } else {
          this.productionPercentage = 0;
        }
        
        this.updateSpeedometer();
      }
    });
  }

  private fetchLineStatus() {
    this.rebotlingService.getRunningStatus().subscribe((res: LineStatusResponse) => {
      if (res && res.success && res.data) {
        this.isLineRunning = res.data.running;
        this.statusBarClass = this.isLineRunning ? 'status-bar-on' : 'status-bar-off';
      }
    });
  }

  private updateSpeedometer() {
    // Använd samma produktionsprocent som visas i "Produktion"
    // Max 200% för speedometern
    const percentage = Math.min(this.productionPercentage, 200);
    
    // Convert percentage to needle rotation (-180 to 0 degrees)
    // -180 degrees är vänster (0%), 0 degrees är höger (200%)
    // Mappar 0-200% till -180 till 0 grader
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
