import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { of } from 'rxjs';
import { catchError, timeout } from 'rxjs/operators';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse } from '../services/rebotling.service';
import { TvattlinjeService, TvattlinjeLiveStatsResponse } from '../services/tvattlinje.service';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-news',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './news.html',
  styleUrl: './news.css'
})
export class News implements OnInit, OnDestroy {
  intervalId: any;
  loggedIn = false;
  isAdmin = false;

  // Rebotling data
  rebotlingStatus: boolean = false;
  rebotlingToday: number = 0;
  rebotlingTarget: number = 0;
  rebotlingPercentage: number = 0;

  // Tvättlinje data
  tvattlinjeStatus: boolean = false;
  tvattlinjeToday: number = 0;
  tvattlinjeTarget: number = 0;
  tvattlinjePercentage: number = 0;

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
    private tvattlinjeService: TvattlinjeService,
    private auth: AuthService
  ) {
    this.auth.loggedIn$.subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.subscribe((val: any) => {
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.fetchAllData();
    }, 5000);
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
    this.rebotlingService.getLiveStats().pipe(
      timeout(5000), catchError(() => of(null))
    ).subscribe((res: RebotlingLiveStatsResponse | null) => {
      if (res && res.success && res.data) {
        this.rebotlingToday = res.data.ibcToday || 0;
        this.rebotlingTarget = res.data.rebotlingTarget || 0;
        this.rebotlingPercentage = res.data.productionPercentage || 0;
      }
    });

    this.rebotlingService.getRunningStatus().pipe(
      timeout(5000), catchError(() => of(null))
    ).subscribe((res: LineStatusResponse | null) => {
      if (res && res.success && res.data) {
        this.rebotlingStatus = res.data.running;
      }
    });
  }

  private fetchTvattlinjeData() {
    this.tvattlinjeService.getLiveStats().pipe(
      timeout(5000), catchError(() => of(null))
    ).subscribe((res: TvattlinjeLiveStatsResponse | null) => {
      if (res && res.success && res.data) {
        this.tvattlinjeToday = res.data.ibcToday;
        this.tvattlinjeTarget = res.data.ibcTarget;
        this.tvattlinjePercentage = res.data.productionPercentage || 0;
      }
    });

    this.tvattlinjeService.getRunningStatus().pipe(
      timeout(5000), catchError(() => of(null))
    ).subscribe((res: LineStatusResponse | null) => {
      if (res && res.success && res.data) {
        this.tvattlinjeStatus = res.data.running;
      }
    });
  }

  getPercentageClass(pct: number): string {
    if (pct >= 100) return 'text-success';
    if (pct >= 60) return 'text-warning';
    return 'text-danger';
  }

  getProgressClass(pct: number): string {
    if (pct >= 100) return 'bg-success';
    if (pct >= 60) return 'bg-warning';
    return 'bg-danger';
  }
}
