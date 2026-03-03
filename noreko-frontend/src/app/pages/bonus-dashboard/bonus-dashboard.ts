import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { BonusService, RankingEntry, ShiftStats } from '../../services/bonus.service';
import { BonusAdminService } from '../../services/bonus-admin.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-bonus-dashboard',
  templateUrl: './bonus-dashboard.html',
  styleUrl: './bonus-dashboard.css',
  imports: [CommonModule, FormsModule]
})
export class BonusDashboardPage implements OnInit, OnDestroy {
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  // State
  loading = false;
  error = '';

  // Period filter
  selectedPeriod = 'week';

  // Daily summary
  summary: any = null;

  // Rankings
  overallRanking: RankingEntry[] = [];
  positionRankings: { [key: string]: RankingEntry[] } = {};
  activeRankingTab = 'overall';

  // Previous-period ranking for trend arrows
  prevRanking: RankingEntry[] = [];
  prevLoading = false;

  // Team/shift stats
  teamAggregate: any = null;
  shifts: ShiftStats[] = [];
  showTeamView = false;

  // Weekly goal (from admin config)
  weeklyGoal = 80;

  // Operator search
  searchOperatorId = '';
  operatorData: any = null;
  operatorKPIData: any = null;

  // Charts
  private trendChart: Chart | null = null;
  private kpiRadarChart: Chart | null = null;
  private shiftCompareChart: Chart | null = null;

  // Subscription tracking
  private pollingInterval: any = null;
  private shiftChartTimeout: any = null;
  private loadDataSub: Subscription | null = null;
  private searchSub: Subscription | null = null;
  private teamStatsSub: Subscription | null = null;
  private destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private bonusService: BonusService,
    private bonusAdminService: BonusAdminService
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadData();
    this.loadWeeklyGoal();
    this.pollingInterval = setInterval(() => this.loadData(), 30000);
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollingInterval) clearInterval(this.pollingInterval);
    clearTimeout(this.shiftChartTimeout);
    this.loadDataSub?.unsubscribe();
    this.searchSub?.unsubscribe();
    this.teamStatsSub?.unsubscribe();
    if (this.trendChart) this.trendChart.destroy();
    if (this.kpiRadarChart) this.kpiRadarChart.destroy();
    if (this.shiftCompareChart) this.shiftCompareChart.destroy();
  }

  private loadWeeklyGoal() {
    this.bonusAdminService.getConfig().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.weeklyGoal = (res.data as any).weekly_bonus_goal || 80;
        }
      },
      error: () => {}
    });
  }

  loadData() {
    if (this.destroy$.closed) return;
    this.loadDataSub?.unsubscribe();

    this.loading = true;
    this.error = '';

    // Ladda summary
    this.bonusService.getDailySummary().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.summary = res.data;
        }
      },
      error: () => {}
    });

    // Ladda ranking (controls loading flag) + previous period for trend arrows
    this.loadDataSub = this.bonusService.getRanking(this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.overallRanking = res.data.rankings.overall || [];
          this.positionRankings = {
            'Tvättplats': res.data.rankings.position_1 || [],
            'Kontrollstation': res.data.rankings.position_2 || [],
            'Truckförare': res.data.rankings.position_3 || []
          };
          this.loadPrevPeriodRanking();
        }
        this.loading = false;
      },
      error: (err) => {
        this.error = 'Kunde inte ladda ranking: ' + err.message;
        this.loading = false;
      }
    });
  }

  /** Load the previous comparable period for trend arrows */
  private loadPrevPeriodRanking() {
    const prevPeriod = this.getPreviousPeriod(this.selectedPeriod);
    if (!prevPeriod) return;
    this.prevLoading = true;
    this.bonusService.getRanking(
      undefined, 10,
      prevPeriod.start, prevPeriod.end
    ).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        this.prevRanking = res.data?.rankings.overall || [];
        this.prevLoading = false;
      },
      error: () => { this.prevLoading = false; }
    });
  }

  private getPreviousPeriod(period: string): { start: string; end: string } | null {
    const now = new Date();
    const fmt = (d: Date) => d.toISOString().split('T')[0];
    if (period === 'today') {
      const y = new Date(now); y.setDate(y.getDate() - 1);
      return { start: fmt(y), end: fmt(y) };
    }
    if (period === 'week') {
      const e = new Date(now); e.setDate(e.getDate() - 7);
      const s = new Date(e); s.setDate(s.getDate() - 7);
      return { start: fmt(s), end: fmt(e) };
    }
    if (period === 'month') {
      const e = new Date(now); e.setDate(e.getDate() - 30);
      const s = new Date(e); s.setDate(s.getDate() - 30);
      return { start: fmt(s), end: fmt(e) };
    }
    return null;
  }

  /** Get trend direction for an operator vs previous period */
  getOperatorTrend(op: RankingEntry): 'up' | 'down' | 'flat' {
    if (!this.prevRanking || this.prevRanking.length === 0) return 'flat';
    const prev = this.prevRanking.find(p => p.operator_id === op.operator_id);
    if (!prev) return 'flat';
    const diff = (op.bonus_avg ?? 0) - (prev.bonus_avg ?? 0);
    if (diff > 1) return 'up';
    if (diff < -1) return 'down';
    return 'flat';
  }

  /** Get previous period rank for an operator */
  getPrevRank(op: RankingEntry): number | null {
    const prev = this.prevRanking.find(p => p.operator_id === op.operator_id);
    return prev ? prev.rank : null;
  }

  /** Progress toward weekly goal as percentage */
  getGoalProgress(bonus: number): number {
    if (!this.weeklyGoal || this.weeklyGoal <= 0) return 0;
    return Math.min(Math.round((bonus / this.weeklyGoal) * 100), 100);
  }

  /** Team average bonus progress toward weekly goal */
  getTeamGoalProgress(): number {
    if (!this.summary?.avg_bonus || !this.weeklyGoal) return 0;
    return Math.min(Math.round((this.summary.avg_bonus / this.weeklyGoal) * 100), 100);
  }

  /** Quality percentage from summary */
  getQualityPct(): number {
    if (!this.summary) return 0;
    const total = (this.summary.total_ibc_ok || 0) + (this.summary.total_ibc_ej_ok || 0);
    if (total <= 0) return 0;
    return Math.round((this.summary.total_ibc_ok / total) * 100);
  }

  toggleTeamView() {
    this.showTeamView = !this.showTeamView;
    if (this.showTeamView) this.reloadTeamStats();
  }

  private reloadTeamStats() {
    this.teamStatsSub?.unsubscribe();

    this.loading = true;
    this.teamStatsSub = this.bonusService.getTeamStats(this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.teamAggregate = res.data.aggregate;
          this.shifts = res.data.shifts || [];
          clearTimeout(this.shiftChartTimeout);
          this.shiftChartTimeout = setTimeout(() => {
            if (!this.destroy$.closed) this.buildShiftCompareChart();
          }, 100);
        }
        this.loading = false;
      },
      error: (err) => {
        this.error = 'Kunde inte ladda skiftdata: ' + err.message;
        this.loading = false;
      }
    });
  }

  searchOperator() {
    if (!this.searchOperatorId.trim()) return;

    this.searchSub?.unsubscribe();

    this.loading = true;
    this.operatorData = null;
    this.operatorKPIData = null;

    let pending = 2;
    const done = () => { if (--pending === 0) this.loading = false; };

    this.searchSub = this.bonusService.getOperatorStats(this.searchOperatorId, this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.operatorData = res.data;
          this.loadOperatorCharts();
        } else {
          this.error = res.error || 'Ingen data hittades';
        }
        done();
      },
      error: (err) => {
        this.error = 'Kunde inte hämta operatörsdata: ' + err.message;
        done();
      }
    });

    this.bonusService.getKPIDetails(this.searchOperatorId, this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.operatorKPIData = res.data;
          this.renderTrendChart();
        }
        done();
      },
      error: () => { done(); }
    });
  }

  loadOperatorCharts() {
    if (!this.operatorData) return;
    this.renderRadarChart();
  }

  renderRadarChart() {
    if (this.kpiRadarChart) this.kpiRadarChart.destroy();

    const canvas = document.getElementById('kpiRadarChart') as HTMLCanvasElement;
    if (!canvas || !this.operatorData) return;

    const kpis = this.operatorData.kpis;
    this.kpiRadarChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
        datasets: [{
          label: 'KPI:er',
          data: [kpis.effektivitet, Math.min(kpis.produktivitet, 100), kpis.kvalitet],
          backgroundColor: 'rgba(54, 162, 235, 0.2)',
          borderColor: 'rgb(54, 162, 235)',
          pointBackgroundColor: 'rgb(54, 162, 235)'
        }]
      },
      options: {
        scales: {
          r: {
            beginAtZero: true,
            max: 100,
            grid: { color: 'rgba(255,255,255,0.1)' },
            angleLines: { color: 'rgba(255,255,255,0.1)' },
            pointLabels: { color: '#e2e8f0' },
            ticks: { color: '#a0aec0', backdropColor: 'transparent' }
          }
        },
        plugins: { legend: { labels: { color: '#e2e8f0' } } }
      }
    });
  }

  renderTrendChart() {
    if (this.trendChart) this.trendChart.destroy();

    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || !this.operatorKPIData?.chart_data) return;

    const chartData = this.operatorKPIData.chart_data;
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: chartData.labels,
        datasets: chartData.datasets.map((ds: any) => ({
          ...ds,
          tension: 0.3,
          fill: false
        }))
      },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } }
        },
        scales: {
          x: {
            grid: { color: 'rgba(255,255,255,0.05)' },
            ticks: { color: '#a0aec0' }
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(255,255,255,0.05)' },
            ticks: { color: '#a0aec0' }
          }
        }
      }
    });
  }

  onPeriodChange() {
    this.prevRanking = [];
    this.loadData();
    if (this.operatorData) this.searchOperator();
    if (this.showTeamView) this.reloadTeamStats();
  }

  setRankingTab(tab: string) {
    this.activeRankingTab = tab;
  }

  getActiveRanking(): RankingEntry[] {
    if (this.activeRankingTab === 'overall') return this.overallRanking;
    return this.positionRankings[this.activeRankingTab] || [];
  }

  clearOperatorSearch() {
    this.searchSub?.unsubscribe();
    this.operatorData = null;
    this.operatorKPIData = null;
    this.searchOperatorId = '';
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null; }
    if (this.kpiRadarChart) { this.kpiRadarChart.destroy(); this.kpiRadarChart = null; }
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 80) return 'text-success';
    if (bonus >= 70) return 'text-warning';
    return 'text-danger';
  }

  getRankBadge(rank: number): string {
    if (rank === 1) return 'badge bg-warning text-dark';
    if (rank === 2) return 'badge bg-secondary';
    if (rank === 3) return 'badge bg-danger';
    return 'badge bg-dark';
  }

  getProductName(id: number): string {
    const names: { [k: number]: string } = { 1: 'FoodGrade', 4: 'NonUN', 5: 'Tvättade' };
    return names[id] || 'Okänd';
  }

  private buildShiftCompareChart(): void {
    if (this.shiftCompareChart) this.shiftCompareChart.destroy();

    const canvas = document.getElementById('shiftCompareChart') as HTMLCanvasElement;
    if (!canvas || this.shifts.length === 0) return;

    const recent = this.shifts.slice(-12);
    const labels = recent.map(s => '#' + s.shift_number + ' (' + (s.shift_start?.substring(5, 10) || '') + ')');

    this.shiftCompareChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Bonus (snitt)',
            data: recent.map(s => s.kpis?.bonus_avg ?? 0),
            backgroundColor: recent.map(s => {
              const b = s.kpis?.bonus_avg ?? 0;
              return b >= 80 ? 'rgba(72,187,120,0.7)' : b >= 70 ? 'rgba(236,201,75,0.7)' : 'rgba(229,62,62,0.7)';
            }),
            borderRadius: 4,
            yAxisID: 'y'
          },
          {
            label: 'IBC OK',
            data: recent.map(s => s.total_ibc_ok),
            type: 'line',
            borderColor: '#63b3ed',
            backgroundColor: 'transparent',
            tension: 0.3,
            pointRadius: 4,
            yAxisID: 'y1'
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
          x: { ticks: { color: '#718096', maxRotation: 45 }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Bonus', color: '#718096' }, min: 0 },
          y1: { position: 'right', ticks: { color: '#718096' }, grid: { display: false }, title: { display: true, text: 'IBC OK', color: '#718096' }, min: 0 }
        }
      }
    });
  }

  exportRankingCSV() {
    const data = this.activeRankingTab === 'overall'
      ? this.overallRanking
      : (this.positionRankings[this.activeRankingTab] || []);
    if (data.length === 0) return;
    const header = ['Rank', 'Trend', 'Operatör', 'Bonus Snitt', 'Effektivitet', 'Produktivitet', 'Kvalitet', 'IBC OK', 'Timmar'];
    const rows = data.map((r: RankingEntry) => [
      r.rank,
      this.getOperatorTrend(r) === 'up' ? 'Uppat' : this.getOperatorTrend(r) === 'down' ? 'Nedåt' : 'Stabil',
      r.operator_name || ('Op ' + r.operator_id),
      (r.bonus_avg ?? 0).toFixed(1),
      (r.effektivitet ?? 0).toFixed(1) + '%',
      (r.produktivitet ?? 0).toFixed(1),
      (r.kvalitet ?? 0).toFixed(1) + '%',
      r.total_ibc_ok ?? 0,
      (r.total_hours ?? 0).toFixed(1)
    ]);
    const csv = [header, ...rows].map(r => r.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bonus-ranking-${this.selectedPeriod}-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
}
