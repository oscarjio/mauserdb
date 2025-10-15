import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe } from '@angular/common';

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
  rebotlingToday: number = 75;
  rebotlingTarget: number = 120;
  rebotlingThisHour: number = 8;
  hourlyTarget: number = 15;
  
  // Speedometer properties
  needleRotation: number = -25; // Start position
  statusText: string = 'Bra produktion';
  statusBadgeClass: string = 'bg-success';

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.updateRebotlingData();
      this.updateSpeedometer();
    }, 1000);
    
    // Initial data update
    this.updateRebotlingData();
    this.updateSpeedometer();
  }

  ngOnDestroy() {
    clearInterval(this.intervalId);
  }

  private updateRebotlingData() {
    // Simulate real-time data updates
    // In a real application, this would fetch data from an API
    const currentHour = this.now.getHours();
    
    // Simulate hourly production based on time of day
    if (currentHour >= 6 && currentHour <= 18) {
      // Working hours - higher production
      this.rebotlingThisHour = Math.floor(Math.random() * 5) + 12;
    } else {
      // Non-working hours - lower production
      this.rebotlingThisHour = Math.floor(Math.random() * 3) + 2;
    }
    
    // Update daily total (simulate cumulative)
    this.rebotlingToday = Math.floor(Math.random() * 10) + 70;
  }

  private updateSpeedometer() {
    // Calculate percentage of hourly target achieved
    const percentage = Math.min((this.rebotlingThisHour / this.hourlyTarget) * 100, 100);
    
    // Convert percentage to needle rotation (-25 to 155 degrees)
    this.needleRotation = -25 + (percentage / 100) * 180;
    
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
