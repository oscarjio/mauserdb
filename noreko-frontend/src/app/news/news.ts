import { Component, OnInit, OnDestroy } from '@angular/core';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse } from '../services/rebotling.service';
import { TvattlinjeService, TvattlinjeLiveStatsResponse } from '../services/tvattlinje.service';

@Component({
  selector: 'app-news',
  standalone: true,
  templateUrl: './news.html',
  styleUrl: './news.css'
})
export class News implements OnInit, OnDestroy {
  intervalId: any;

  // Rebotling data
  rebotlingStatus: boolean = false;
  rebotlingToday: number = 0;
  rebotlingTarget: number = 0;
  rebotlingThisHour: number = 0;
  hourlyTarget: number = 0;
  rebotlingNeedleRotation: number = -100;
  rebotlingBadgeClass: string = 'bg-success';

  // Tvättlinje data
  tvattlinjeStatus: boolean = false;
  tvattlinjeToday: number = 0;
  tvattlinjeTarget: number = 0;
  tvattlinjeThisHour: number = 0;
  tvattlinjeHourlyTarget: number = 0;
  tvattlinjeNeedleRotation: number = -100;
  tvattlinjeBadgeClass: string = 'bg-success';

  // Saglinje data (placeholder)
  saglinjeStatus: boolean = false;
  saglinjeToday: number = 0;
  saglinjeTarget: number = 0;

  // Klassificeringslinje data (placeholder)
  klassificeringslinjeStatus: boolean = false;
  klassificeringslinjeToday: number = 0;
  klassificeringslinjeTarget: number = 0;

  constructor(
    private rebotlingService: RebotlingService,
    private tvattlinjeService: TvattlinjeService
  ) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.fetchAllData();
    }, 5000); // Update every 5 seconds
    this.fetchAllData();
  }

  ngOnDestroy() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  }

  private fetchAllData() {
    this.fetchRebotlingData();
    this.fetchTvattlinjeData();
  }

  private fetchRebotlingData() {
    // Fetch live stats
    this.rebotlingService.getLiveStats().subscribe((res: RebotlingLiveStatsResponse) => {
      if (res && res.success && res.data) {
        this.rebotlingToday = res.data.rebotlingToday;
        this.rebotlingTarget = res.data.rebotlingTarget;
        this.rebotlingThisHour = res.data.rebotlingThisHour;
        this.hourlyTarget = res.data.hourlyTarget;
        this.updateRebotlingSpeedometer();
      }
    });

    // Fetch status
    this.rebotlingService.getRunningStatus().subscribe((res: LineStatusResponse) => {
      if (res && res.success && res.data) {
        this.rebotlingStatus = res.data.running;
      }
    });
  }

  private fetchTvattlinjeData() {
    // Fetch live stats
    this.tvattlinjeService.getLiveStats().subscribe((res: TvattlinjeLiveStatsResponse) => {
      if (res && res.success && res.data) {
        this.tvattlinjeToday = res.data.ibcToday;
        this.tvattlinjeTarget = res.data.ibcTarget;
      }
    });

    // Fetch status
    this.tvattlinjeService.getRunningStatus().subscribe((res: LineStatusResponse) => {
      if (res && res.success && res.data) {
        this.tvattlinjeStatus = res.data.running;
      }
    });
  }

  private updateRebotlingSpeedometer() {
    const percentage = Math.min((this.rebotlingThisHour / this.hourlyTarget) * 100, 100);
    this.rebotlingNeedleRotation = -100 + (percentage / 100) * 180;
    
    if (percentage >= 80) {
      this.rebotlingBadgeClass = 'bg-success';
    } else if (percentage >= 60) {
      this.rebotlingBadgeClass = 'bg-success';
    } else if (percentage >= 40) {
      this.rebotlingBadgeClass = 'bg-warning';
    } else {
      this.rebotlingBadgeClass = 'bg-danger';
    }
  }
}
