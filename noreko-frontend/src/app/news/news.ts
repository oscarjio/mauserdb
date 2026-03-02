import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { of, Subject } from 'rxjs';
import { catchError, timeout, takeUntil } from 'rxjs/operators';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse } from '../services/rebotling.service';
import { TvattlinjeService, TvattlinjeLiveStatsResponse } from '../services/tvattlinje.service';
import { LineSkiftrapportService } from '../services/line-skiftrapport.service';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-news',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './news.html',
  styleUrl: './news.css'
})
export class News implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
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

  // Saglinje data
  saglinjeStatus: boolean = false;
  saglinjeToday: number = 0;
  saglinjeTarget: number = 0;
  saglinjeKvalitetPct: number = 0;
  saglinjeSkiftCount: number = 0;

  // Klassificeringslinje data
  klassificeringslinjeStatus: boolean = false;
  klassificeringslinjeToday: number = 0;
  klassificeringslinjeTarget: number = 0;
  klassificeringslinjeKvalitetPct: number = 0;
  klassificeringslinjeSkiftCount: number = 0;

  constructor(
    private rebotlingService: RebotlingService,
    private tvattlinjeService: TvattlinjeService,
    private lineSkiftrapportService: LineSkiftrapportService,
    private auth: AuthService
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe((val: any) => {
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
    this.destroy$.next();
    this.destroy$.complete();
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
  }

  private fetchAllData() {
    this.fetchRebotlingData();
    this.fetchTvattlinjeData();
    this.fetchSaglinjeData();
    this.fetchKlassificeringslinjeData();
  }

  private fetchRebotlingData() {
    this.rebotlingService.getLiveStats().pipe(
      timeout(5000), catchError(() => of(null))
    ).subscribe((res: RebotlingLiveStatsResponse | null) => {
      if (res && res.success && res.data) {
        this.rebotlingToday = res.data.ibcToday || 0;
        this.rebotlingTarget = res.data.rebotlingTarget || 0;
        // Visa daglig måluppfyllnad (idag vs dagsmål), inte timeffektivitet
        this.rebotlingPercentage = this.rebotlingTarget > 0
          ? Math.round((this.rebotlingToday / this.rebotlingTarget) * 100) : 0;
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

  private fetchSaglinjeData() {
    this.lineSkiftrapportService.getReports('saglinje').pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: any) => {
      if (res?.success && res.data) {
        const today = new Date().toISOString().split('T')[0];
        const reps = (res.data as any[]).filter((r: any) => (r.datum || '').substring(0, 10) === today);
        this.saglinjeSkiftCount = reps.length;
        this.saglinjeToday = reps.reduce((s: number, r: any) => s + (r.antal_ok || 0), 0);
        this.saglinjeTarget = reps.reduce((s: number, r: any) => s + (r.antal_ej_ok || 0), 0) + this.saglinjeToday;
        this.saglinjeKvalitetPct = this.saglinjeTarget > 0
          ? Math.round((this.saglinjeToday / this.saglinjeTarget) * 100) : 0;
        this.saglinjeStatus = reps.length > 0;
      }
    });
  }

  private fetchKlassificeringslinjeData() {
    this.lineSkiftrapportService.getReports('klassificeringslinje').pipe(
      timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe((res: any) => {
      if (res?.success && res.data) {
        const today = new Date().toISOString().split('T')[0];
        const reps = (res.data as any[]).filter((r: any) => (r.datum || '').substring(0, 10) === today);
        this.klassificeringslinjeSkiftCount = reps.length;
        this.klassificeringslinjeToday = reps.reduce((s: number, r: any) => s + (r.antal_ok || 0), 0);
        this.klassificeringslinjeTarget = reps.reduce((s: number, r: any) => s + (r.antal_ej_ok || 0), 0) + this.klassificeringslinjeToday;
        this.klassificeringslinjeKvalitetPct = this.klassificeringslinjeTarget > 0
          ? Math.round((this.klassificeringslinjeToday / this.klassificeringslinjeTarget) * 100) : 0;
        this.klassificeringslinjeStatus = reps.length > 0;
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
