import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { RebotlingService, OEEResponse, CycleTrendResponse } from '../../services/rebotling.service';
import { TvattlinjeService } from '../../services/tvattlinje.service';
import { BonusService, BonusSummaryResponse, TeamStatsResponse } from '../../services/bonus.service';
import { forkJoin, catchError, of, timeout } from 'rxjs';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface LineStatus {
  name: string;
  icon: string;
  running: boolean;
  lastUpdate: string | null;
  production: number;
  target: number;
  percentage: number;
  route: string;
}

@Component({
  selector: 'app-executive-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './executive-dashboard.html',
  styleUrls: ['./executive-dashboard.css']
})
export class ExecutiveDashboardPage implements OnInit, OnDestroy {
  Math = Math;
  loggedIn = false;
  isAdmin = false;
  loading = true;

  lines: LineStatus[] = [];
  bonusSummary: any = null;
  teamStats: any = null;
  oeeData: any = null;
  cycleTrend: any = null;
  cycleTrendAlert: any = null;
  lastRefresh: Date = new Date();

  private pollInterval: any;
  private trendChart: Chart | null = null;
  private cycleTrendChart: Chart | null = null;

  constructor(
    private auth: AuthService,
    private rebotlingService: RebotlingService,
    private tvattlinjeService: TvattlinjeService,
    private bonusService: BonusService
  ) {
    this.auth.loggedIn$.subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.subscribe((val: any) => {
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit(): void {
    this.loadData();
    this.pollInterval = setInterval(() => this.loadData(), 15000);
  }

  ngOnDestroy(): void {
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.trendChart) this.trendChart.destroy();
    if (this.cycleTrendChart) this.cycleTrendChart.destroy();
  }

  loadData(): void {
    forkJoin({
      rebotlingLive: this.rebotlingService.getLiveStats().pipe(timeout(5000), catchError(() => of(null))),
      rebotlingStatus: this.rebotlingService.getRunningStatus().pipe(timeout(5000), catchError(() => of(null))),
      tvattlinjeLive: this.tvattlinjeService.getLiveStats().pipe(timeout(5000), catchError(() => of(null))),
      tvattlinjeStatus: this.tvattlinjeService.getRunningStatus().pipe(timeout(5000), catchError(() => of(null))),
      bonusSummary: this.bonusService.getDailySummary().pipe(timeout(5000), catchError(() => of(null))),
      teamStats: this.bonusService.getTeamStats('week').pipe(timeout(5000), catchError(() => of(null))),
      oee: this.rebotlingService.getOEE('today').pipe(timeout(5000), catchError(() => of(null))),
      cycleTrend: this.rebotlingService.getCycleTrend(30).pipe(timeout(8000), catchError(() => of(null)))
    }).subscribe(results => {
      this.lines = [];

      // Rebotling
      const rebLive = results.rebotlingLive as any;
      const rebStatus = results.rebotlingStatus as any;
      this.lines.push({
        name: 'Rebotling',
        icon: 'fa-recycle',
        running: rebStatus?.data?.running ?? false,
        lastUpdate: rebStatus?.data?.lastUpdate ?? null,
        production: rebLive?.data?.rebotlingToday ?? 0,
        target: rebLive?.data?.rebotlingTarget ?? 0,
        percentage: rebLive?.data?.productionPercentage ?? 0,
        route: '/rebotling/live'
      });

      // Tvattlinje
      const tvLive = results.tvattlinjeLive as any;
      const tvStatus = results.tvattlinjeStatus as any;
      this.lines.push({
        name: 'Tvattlinje',
        icon: 'fa-shower',
        running: tvStatus?.data?.running ?? false,
        lastUpdate: tvStatus?.data?.lastUpdate ?? null,
        production: tvLive?.data?.ibcToday ?? 0,
        target: tvLive?.data?.ibcTarget ?? 0,
        percentage: tvLive?.data?.productionPercentage ?? 0,
        route: '/tvattlinje/live'
      });

      // Bonus
      const bonus = results.bonusSummary as BonusSummaryResponse;
      if (bonus?.success && bonus.data) {
        this.bonusSummary = bonus.data;
      }

      const team = results.teamStats as TeamStatsResponse;
      if (team?.success && team.data) {
        this.teamStats = team.data;
        this.buildTrendChart(team.data.shifts || []);
      }

      const oee = results.oee as OEEResponse;
      if (oee?.success && oee.data) {
        this.oeeData = oee.data;
      }

      const ct = results.cycleTrend as CycleTrendResponse;
      if (ct?.success && ct.data) {
        this.cycleTrend = ct.data;
        this.cycleTrendAlert = ct.data.alert;
        setTimeout(() => this.buildCycleTrendChart(ct.data!), 100);
      }

      this.lastRefresh = new Date();
      this.loading = false;
    });
  }

  getTotalProduction(): number {
    return this.lines.reduce((sum, l) => sum + l.production, 0);
  }

  getTotalTarget(): number {
    return this.lines.reduce((sum, l) => sum + l.target, 0);
  }

  getOverallPercentage(): number {
    const target = this.getTotalTarget();
    if (target === 0) return 0;
    return Math.round((this.getTotalProduction() / target) * 100);
  }

  getRunningCount(): number {
    return this.lines.filter(l => l.running).length;
  }

  getStatusClass(percentage: number): string {
    if (percentage >= 100) return 'text-success';
    if (percentage >= 60) return 'text-warning';
    return 'text-danger';
  }

  getStatusText(line: LineStatus): string {
    if (!line.running) return 'Stoppad';
    if (line.percentage >= 100) return 'Bra produktion';
    if (line.percentage >= 60) return 'Under mål';
    return 'Låg produktion';
  }

  getStatusBadgeClass(line: LineStatus): string {
    if (!line.running) return 'bg-secondary';
    if (line.percentage >= 100) return 'bg-success';
    if (line.percentage >= 60) return 'bg-warning text-dark';
    return 'bg-danger';
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 90) return 'text-success';
    if (bonus >= 70) return 'text-info';
    if (bonus >= 50) return 'text-warning';
    return 'text-danger';
  }

  getOEEClass(oee: number): string {
    if (oee >= 85) return 'text-success';  // World class
    if (oee >= 60) return 'text-warning';
    return 'text-danger';
  }

  getOEELabel(oee: number): string {
    if (oee >= 85) return 'World Class';
    if (oee >= 60) return 'Acceptabel';
    if (oee >= 40) return 'Förbättring krävs';
    return 'Kritiskt låg';
  }

  getTimeAgo(dateStr: string | null): string {
    if (!dateStr) return 'Ingen data';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Just nu';
    if (mins < 60) return mins + ' min sedan';
    const hours = Math.floor(mins / 60);
    return hours + ' h sedan';
  }

  private buildTrendChart(shifts: any[]): void {
    if (this.trendChart) this.trendChart.destroy();

    const canvas = document.getElementById('execTrendChart') as HTMLCanvasElement;
    if (!canvas || shifts.length === 0) return;

    const labels = shifts.slice(-10).map((s: any, i: number) => 'Skift ' + (i + 1));
    const bonusData = shifts.slice(-10).map((s: any) => s.kpis?.bonus_avg ?? 0);
    const effData = shifts.slice(-10).map((s: any) => s.kpis?.effektivitet ?? 0);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Bonus (snitt)',
            data: bonusData,
            borderColor: '#38b2ac',
            backgroundColor: 'rgba(56, 178, 172, 0.1)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'Effektivitet',
            data: effData,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0' } }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, min: 0, max: 120 }
        }
      }
    });
  }

  getTrendIcon(): string {
    if (!this.cycleTrend) return 'fa-minus';
    if (this.cycleTrend.trend === 'increasing') return 'fa-arrow-up';
    if (this.cycleTrend.trend === 'decreasing') return 'fa-arrow-down';
    return 'fa-minus';
  }

  getTrendClass(): string {
    if (!this.cycleTrend) return 'text-muted';
    if (this.cycleTrend.trend === 'increasing') return 'text-danger';
    if (this.cycleTrend.trend === 'decreasing') return 'text-success';
    return 'text-info';
  }

  getTrendLabel(): string {
    if (!this.cycleTrend) return 'Ingen data';
    if (this.cycleTrend.trend === 'increasing') return 'Ökande (kontrollera utrustning)';
    if (this.cycleTrend.trend === 'decreasing') return 'Minskande (förbättring)';
    return 'Stabil';
  }

  private buildCycleTrendChart(data: any): void {
    if (this.cycleTrendChart) this.cycleTrendChart.destroy();
    const canvas = document.getElementById('cycleTrendChart') as HTMLCanvasElement;
    if (!canvas || !data.daily || data.daily.length === 0) return;

    const labels = data.daily.map((d: any) => d.dag.substring(5)); // MM-DD
    const runtimeData = data.daily.map((d: any) => d.avg_runtime);
    const ibcPerHour = data.daily.map((d: any) => d.avg_ibc_per_hour);

    const maLabels = data.moving_average?.map((d: any) => d.dag.substring(5)) || [];
    const maData = data.moving_average?.map((d: any) => d.moving_avg) || [];

    // Pad moving average to align with daily data
    const padCount = labels.length - maLabels.length;
    const paddedMA = new Array(padCount).fill(null).concat(maData);

    this.cycleTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Snitt cykeltid (min)',
            data: runtimeData,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.3,
            fill: true,
            pointRadius: 2
          },
          {
            label: '7-dagars glidande medel',
            data: paddedMA,
            borderColor: '#e74c3c',
            borderWidth: 2,
            borderDash: [5, 5],
            tension: 0.3,
            fill: false,
            pointRadius: 0
          },
          {
            label: 'IBC/timme',
            data: ibcPerHour,
            borderColor: '#38b2ac',
            backgroundColor: 'rgba(56, 178, 172, 0.1)',
            tension: 0.3,
            fill: false,
            pointRadius: 2,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0' } },
          title: { display: true, text: 'Cykeltidstrend (senaste 30 dagarna)', color: '#e2e8f0' }
        },
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45 }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Minuter', color: '#718096' } },
          y1: { position: 'right', ticks: { color: '#718096' }, grid: { display: false }, title: { display: true, text: 'IBC/timme', color: '#718096' } }
        }
      }
    });
  }

  printDashboard(): void {
    window.print();
  }
}
