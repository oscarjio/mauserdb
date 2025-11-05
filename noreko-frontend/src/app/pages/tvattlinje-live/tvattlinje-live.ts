import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
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
  
  // Line status
  isLineRunning: boolean = false;
  statusBarClass: string = 'status-bar-off';
  
  // Live stats
  ibcToday: number = 0;
  ibcTarget: number = 0;
  utetemperatur: number | null = null;

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
    this.tvattlinjeService.getRunningStatus().subscribe((res: LineStatusResponse) => {
      if (res && res.success && res.data) {
        this.isLineRunning = res.data.running;
        this.statusBarClass = this.isLineRunning ? 'status-bar-on' : 'status-bar-off';
      }
    });
  }

  private fetchLiveStats() {
    this.tvattlinjeService.getLiveStats().subscribe((res: TvattlinjeLiveStatsResponse) => {
      if (res && res.success && res.data) {
        this.ibcToday = res.data.ibcToday;
        this.ibcTarget = res.data.ibcTarget;
        this.utetemperatur = res.data.utetemperatur;
      }
    });
  }
}
