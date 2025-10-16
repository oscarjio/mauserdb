import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RebotlingService, RebotlingLiveStatsResponse } from '../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-live',
  imports: [DatePipe],
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
  
  // Speedometer properties
  needleRotation: number = -150; // Start position
  statusText: string = 'Bra produktion';
  statusBadgeClass: string = 'bg-success';

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.fetchLiveStats();
    }, 2000);
    this.fetchLiveStats();
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
        this.updateSpeedometer();
      }
    });
  }

  private updateSpeedometer() {
    // Calculate percentage of hourly target achieved
    const percentage = Math.min((this.rebotlingThisHour / this.hourlyTarget) * 100, 100);
    
    // Convert percentage to needle rotation (-25 to 155 degrees)
    this.needleRotation = -100 + (percentage / 100) * 180;
    
    // Update status based on performance
    if (percentage >= 80) {
      this.statusText = 'Utmärkt produktion';
      this.statusBadgeClass = 'bg-success';
    } else if (percentage >= 60) {
      this.statusText = 'Bra produktion';
      this.statusBadgeClass = 'bg-success';
    } else if (percentage >= 40) {
      this.statusText = 'Acceptabel produktion';
      this.statusBadgeClass = 'bg-warning';
    } else {
      this.statusText = 'Låg produktion';
      this.statusBadgeClass = 'bg-danger';
    }
  }
}
