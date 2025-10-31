import { Component, OnInit, OnDestroy } from '@angular/core';
import { DatePipe } from '@angular/common';
import { TvattlinjeService, LineStatusResponse } from '../../services/tvattlinje.service';

@Component({
  standalone: true,
  selector: 'app-tvattlinje-live',
  imports: [DatePipe],
  templateUrl: './tvattlinje-live.html',
  styleUrl: './tvattlinje-live.css'
})
export class TvattlinjeLivePage implements OnInit, OnDestroy {
  now = new Date();
  intervalId: any;
  
  // Line status
  isLineRunning: boolean = false;
  statusBarClass: string = 'status-bar-off';

  constructor(private tvattlinjeService: TvattlinjeService) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.now = new Date();
      this.fetchLineStatus();
    }, 2000);
    this.fetchLineStatus();
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
}
