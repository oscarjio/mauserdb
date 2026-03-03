import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { BonusService, RankingEntry, ShiftStats } from '../../services/bonus.service';
import { RebotlingService, BestShift } from '../../services/rebotling.service';
import { catchError, of, timeout } from 'rxjs';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-production-analysis',
  templateUrl: './production-analysis.html',
  styleUrl: './production-analysis.css',
  imports: [CommonModule, FormsModule]
})
export class ProductionAnalysisPage implements OnInit, OnDestroy {
  Math = Math;

  loggedIn = false;
  user: any = null;
  isAdmin = false;

  loading = false;
  error = '';
  activeTab = 'operators';
  selectedPeriod = 'week';

  // Tab 1: Operatörsjämförelse
  overallRanking: RankingEntry[] = [];
  positionRankings: { [key: string]: RankingEntry[] } = {};
  positionFilter = 'all';
  radarSelected: number[] = [];
  sortColumn = 'bonus_avg';
  sortDirection = 'desc';

  // Tab 2: Dagsanalys
  shifts: ShiftStats[] = [];
  teamAggregate: any = null;
  dailyData: { date: string; bonus: number; ibcOk: number }[] = [];
  weekdayData: { day: string; avgBonus: number; avgIbc: number }[] = [];
  bestDay: any = null;
  worstDay: any = null;
  avgBonus = 0;
  totalIbc = 0;

  // Tab 3: Timanalys
  heatmapData: { date: string; hours: { [hour: number]: number } }[] = [];
  hourlyAvg: { hour: number; avg: number }[] = [];
  heatmapHours = Array.from({ length: 18 }, (_, i) => i + 5); // 05-22
  private heatmapMax = 1;

  // Tab 4: Skiftöversikt
  allShifts: ShiftStats[] = [];
  expandedShift: number | null = null;

  // Tab 5: Bästa skift (historik)
  bestShifts: BestShift[] = [];
  bestShiftsLoading = false;
  bestShiftsLimit = 10;
  private bestShiftsChart: Chart | null = null;

  // Tab 6: Stoppanalys
  // OBS: Stoppanalys är baserad på rast-data som proxy.
  // Riktig stoppanalys kräver utökad PLC-integration med separata stopp-events.
  stopAnalysisLoading = false;
  rastStatus: any = null;
  lineStatus: any = null;
  rastHistory14: { date: string; totalRastMinutes: number; rastCount: number }[] = [];
  private stopRastChart: Chart | null = null;

  // Charts
  private destroy$ = new Subject<void>();
  private rankingChart: Chart | null = null;
  private radarChart: Chart | null = null;
  private dailyTrendChart: Chart | null = null;
  private weekdayChart: Chart | null = null;
  private hourlyBarChart: Chart | null = null;
  private bubbleChart: Chart | null = null;

  // Timeout IDs
  private tabTimeout: any = null;
  private chartTimeout: any = null;
  private radarTimeout: any = null;

  constructor(
    private auth: AuthService,
    private bonusService: BonusService,
    private rebotlingService: RebotlingService
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadTabData();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    clearTimeout(this.tabTimeout);
    clearTimeout(this.chartTimeout);
    clearTimeout(this.radarTimeout);
    this.destroyAllCharts();
  }

  private destroyAllCharts() {
    [this.rankingChart, this.radarChart, this.dailyTrendChart,
     this.weekdayChart, this.hourlyBarChart, this.bubbleChart,
     this.bestShiftsChart, this.stopRastChart].forEach(c => c?.destroy());
    this.rankingChart = null;
    this.radarChart = null;
    this.dailyTrendChart = null;
    this.weekdayChart = null;
    this.hourlyBarChart = null;
    this.bubbleChart = null;
    this.bestShiftsChart = null;
    this.stopRastChart = null;
  }

  setTab(tab: string) {
    this.activeTab = tab;
    this.error = '';
    clearTimeout(this.tabTimeout);
    this.tabTimeout = setTimeout(() => this.loadTabData(), 50);
  }

  onPeriodChange() {
    this.loadTabData();
  }

  loadTabData() {
    switch (this.activeTab) {
      case 'operators':   this.loadOperatorData();  break;
      case 'daily':       this.loadDailyData();     break;
      case 'hourly':      this.loadHourlyData();    break;
      case 'shifts':      this.loadShiftData();     break;
      case 'bestshifts':  this.loadBestShifts();    break;
      case 'stopanalysis': this.loadStopAnalysis();  break;
    }
  }

  // ======== TAB 1: OPERATÖRSJÄMFÖRELSE ========

  loadOperatorData() {
    this.loading = true;
    this.bonusService.getRanking(this.selectedPeriod, 20).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { this.error = 'Kunde inte ladda rankingdata'; this.loading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.overallRanking = res.data.rankings.overall || [];
        this.positionRankings = {
          'Tvättplats': res.data.rankings.position_1 || [],
          'Kontroll': res.data.rankings.position_2 || [],
          'Truck': res.data.rankings.position_3 || []
        };
        clearTimeout(this.chartTimeout);
        this.chartTimeout = setTimeout(() => {
          if (!this.destroy$.closed) this.renderRankingChart();
        }, 100);
      }
      this.loading = false;
    });
  }

  getFilteredRanking(): RankingEntry[] {
    let data = this.overallRanking;
    if (this.positionFilter === 'Tvättplats') data = this.positionRankings['Tvättplats'] || [];
    else if (this.positionFilter === 'Kontroll') data = this.positionRankings['Kontroll'] || [];
    else if (this.positionFilter === 'Truck') data = this.positionRankings['Truck'] || [];

    return [...data].sort((a: any, b: any) => {
      const aVal = a[this.sortColumn] ?? 0;
      const bVal = b[this.sortColumn] ?? 0;
      return this.sortDirection === 'desc' ? bVal - aVal : aVal - bVal;
    });
  }

  toggleSort(col: string) {
    if (this.sortColumn === col) {
      this.sortDirection = this.sortDirection === 'desc' ? 'asc' : 'desc';
    } else {
      this.sortColumn = col;
      this.sortDirection = 'desc';
    }
  }

  getSortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort';
    return this.sortDirection === 'desc' ? 'fas fa-sort-down' : 'fas fa-sort-up';
  }

  toggleRadarSelect(opId: number) {
    const idx = this.radarSelected.indexOf(opId);
    if (idx >= 0) {
      this.radarSelected.splice(idx, 1);
    } else if (this.radarSelected.length < 3) {
      this.radarSelected.push(opId);
    }
    if (this.radarSelected.length >= 2) {
      clearTimeout(this.radarTimeout);
      this.radarTimeout = setTimeout(() => {
        if (!this.destroy$.closed) this.renderRadarChart();
      }, 50);
    }
  }

  isRadarSelected(opId: number): boolean {
    return this.radarSelected.includes(opId);
  }

  renderRankingChart() {
    if (this.rankingChart) this.rankingChart.destroy();
    const canvas = document.getElementById('rankingChart') as HTMLCanvasElement;
    if (!canvas) return;

    const data = this.getFilteredRanking().slice(0, 15);
    const labels = data.map((d: any) => d.operator_name || ('Op ' + d.operator_id));
    const values = data.map(d => d.bonus_avg);
    const colors = values.map(v => v >= 90 ? 'rgba(72,187,120,0.8)' : v >= 70 ? 'rgba(236,201,75,0.8)' : 'rgba(229,62,62,0.8)');

    this.rankingChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Bonus (snitt)',
          data: values,
          backgroundColor: colors,
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, min: 0, max: 100 },
          y: { ticks: { color: '#a0aec0' }, grid: { display: false } }
        }
      }
    });
  }

  private renderRadarChart() {
    if (this.radarChart) this.radarChart.destroy();
    const canvas = document.getElementById('radarCompareChart') as HTMLCanvasElement;
    if (!canvas) return;

    const radarColors = ['rgba(66,153,225,0.6)', 'rgba(72,187,120,0.6)', 'rgba(236,201,75,0.6)'];
    const radarBorders = ['rgb(66,153,225)', 'rgb(72,187,120)', 'rgb(236,201,75)'];

    const datasets = this.radarSelected.map((opId, i) => {
      const op = this.overallRanking.find(r => r.operator_id === opId);
      if (!op) return null;
      return {
        label: (op as any).operator_name || ('Op ' + opId),
        data: [op.effektivitet, Math.min(op.produktivitet, 100), op.kvalitet],
        backgroundColor: radarColors[i],
        borderColor: radarBorders[i],
        pointBackgroundColor: radarBorders[i]
      };
    }).filter(Boolean);

    this.radarChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
        datasets: datasets as any
      },
      options: {
        scales: {
          r: {
            beginAtZero: true,
            max: 100,
            grid: { color: 'rgba(255,255,255,0.1)' },
            angleLines: { color: 'rgba(255,255,255,0.1)' },
            pointLabels: { color: '#e2e8f0', font: { size: 13 } },
            ticks: { color: '#a0aec0', backdropColor: 'transparent' }
          }
        },
        plugins: { legend: { labels: { color: '#e2e8f0' } } }
      }
    });
  }

  // ======== TAB 2: DAGSANALYS ========

  loadDailyData() {
    this.loading = true;
    this.bonusService.getTeamStats(this.selectedPeriod === 'week' ? 'week' : 'month').pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { this.error = 'Kunde inte ladda dagsdata'; this.loading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.teamAggregate = res.data.aggregate;
        this.shifts = res.data.shifts || [];
        this.aggregateDailyData();
        clearTimeout(this.chartTimeout);
        this.chartTimeout = setTimeout(() => {
          if (!this.destroy$.closed) {
            this.renderDailyTrendChart();
            this.renderWeekdayChart();
          }
        }, 100);
      }
      this.loading = false;
    });
  }

  private aggregateDailyData() {
    const dayMap: { [date: string]: { bonusSum: number; bonusCount: number; ibcOk: number } } = {};

    this.shifts.forEach(s => {
      const date = s.shift_start?.substring(0, 10) || '';
      if (!date) return;
      if (!dayMap[date]) dayMap[date] = { bonusSum: 0, bonusCount: 0, ibcOk: 0 };
      dayMap[date].bonusSum += s.kpis?.bonus_avg ?? 0;
      dayMap[date].bonusCount++;
      dayMap[date].ibcOk += s.total_ibc_ok;
    });

    this.dailyData = Object.keys(dayMap).sort().map(date => ({
      date,
      bonus: dayMap[date].bonusCount ? Math.round(dayMap[date].bonusSum / dayMap[date].bonusCount) : 0,
      ibcOk: dayMap[date].ibcOk
    }));

    // Weekday aggregation
    const weekdays = ['Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'];
    const wdMap: { [wd: number]: { bonusSum: number; ibcSum: number; count: number } } = {};
    this.dailyData.forEach(d => {
      const wd = new Date(d.date).getDay();
      if (!wdMap[wd]) wdMap[wd] = { bonusSum: 0, ibcSum: 0, count: 0 };
      wdMap[wd].bonusSum += d.bonus;
      wdMap[wd].ibcSum += d.ibcOk;
      wdMap[wd].count++;
    });
    this.weekdayData = [1, 2, 3, 4, 5].map(wd => ({
      day: weekdays[wd],
      avgBonus: wdMap[wd]?.count ? Math.round(wdMap[wd].bonusSum / wdMap[wd].count) : 0,
      avgIbc: wdMap[wd]?.count ? Math.round(wdMap[wd].ibcSum / wdMap[wd].count) : 0
    }));

    // Summary stats
    if (this.dailyData.length > 0) {
      this.bestDay = this.dailyData.reduce((a, b) => a.bonus > b.bonus ? a : b);
      this.worstDay = this.dailyData.reduce((a, b) => a.bonus < b.bonus ? a : b);
      this.avgBonus = Math.round(this.dailyData.reduce((s, d) => s + d.bonus, 0) / this.dailyData.length);
      this.totalIbc = this.dailyData.reduce((s, d) => s + d.ibcOk, 0);
    }
  }

  private renderDailyTrendChart() {
    if (this.dailyTrendChart) this.dailyTrendChart.destroy();
    const canvas = document.getElementById('dailyTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.dailyData.length) return;

    this.dailyTrendChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.dailyData.map(d => d.date.substring(5)),
        datasets: [
          {
            label: 'IBC OK',
            data: this.dailyData.map(d => d.ibcOk),
            backgroundColor: 'rgba(66,153,225,0.5)',
            borderRadius: 4,
            yAxisID: 'y1',
            order: 2
          },
          {
            label: 'Bonus (snitt)',
            data: this.dailyData.map(d => d.bonus),
            type: 'line',
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72,187,120,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#48bb78',
            yAxisID: 'y',
            order: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#a0aec0' } } },
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45 }, grid: { color: '#2d3748' } },
          y: { position: 'left', ticks: { color: '#718096' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Bonus', color: '#718096' }, min: 0, max: 100 },
          y1: { position: 'right', ticks: { color: '#718096' }, grid: { display: false }, title: { display: true, text: 'IBC OK', color: '#718096' }, min: 0 }
        }
      }
    });
  }

  private renderWeekdayChart() {
    if (this.weekdayChart) this.weekdayChart.destroy();
    const canvas = document.getElementById('weekdayChart') as HTMLCanvasElement;
    if (!canvas || !this.weekdayData.length) return;

    this.weekdayChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.weekdayData.map(d => d.day),
        datasets: [{
          label: 'Snitt IBC OK',
          data: this.weekdayData.map(d => d.avgIbc),
          backgroundColor: this.weekdayData.map(d => {
            const max = Math.max(...this.weekdayData.map(w => w.avgIbc));
            return d.avgIbc === max ? 'rgba(72,187,120,0.8)' : 'rgba(66,153,225,0.6)';
          }),
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { display: false } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, beginAtZero: true }
        }
      }
    });
  }

  // ======== TAB 3: TIMANALYS ========

  loadHourlyData() {
    this.loading = true;
    const days = this.selectedPeriod === 'week' ? 7 : 30;

    // Använd den aggregerade heatmap-endpointen (1 anrop istället för N)
    this.rebotlingService.getHeatmap(days).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => { this.error = 'Kunde inte ladda timdata'; this.loading = false; return of(null); })
    ).subscribe((res: any) => {
      if (!res?.success) { this.loading = false; return; }

      const rows: { [date: string]: { [hour: number]: number } } = {};
      const hourTotals: { [hour: number]: { sum: number; count: number } } = {};

      (res.data as { date: string; hour: number; count: number }[]).forEach(({ date, hour, count }) => {
        if (!rows[date]) rows[date] = {};
        rows[date][hour] = count;
      });

      this.heatmapData = Object.keys(rows).sort().map(date => ({ date, hours: rows[date] }));

      this.heatmapData.forEach(({ hours }) => {
        this.heatmapHours.forEach(h => {
          if (!hourTotals[h]) hourTotals[h] = { sum: 0, count: 0 };
          hourTotals[h].sum += hours[h] || 0;
          hourTotals[h].count++;
        });
      });

      this.updateHeatmapMax();

      this.hourlyAvg = this.heatmapHours.map(h => ({
        hour: h,
        avg: hourTotals[h]?.count ? Math.round(hourTotals[h].sum / hourTotals[h].count * 10) / 10 : 0
      }));

      clearTimeout(this.chartTimeout);
      this.chartTimeout = setTimeout(() => {
        if (!this.destroy$.closed) this.renderHourlyBarChart();
      }, 100);
      this.loading = false;
    });
  }

  private updateHeatmapMax() {
    let max = 0;
    this.heatmapData.forEach(d => {
      this.heatmapHours.forEach(h => {
        if ((d.hours[h] || 0) > max) max = d.hours[h];
      });
    });
    this.heatmapMax = max || 1;
  }

  getHeatmapColor(value: number): string {
    if (!value) return '#1a202c';
    if (this.heatmapMax === 0) return '#1a202c';
    const intensity = Math.min(value / this.heatmapMax, 1);
    if (intensity < 0.25) return '#1a365d';
    if (intensity < 0.5) return '#2b6cb0';
    if (intensity < 0.75) return '#3182ce';
    return '#4299e1';
  }

  private renderHourlyBarChart() {
    if (this.hourlyBarChart) this.hourlyBarChart.destroy();
    const canvas = document.getElementById('hourlyBarChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.hourlyBarChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.hourlyAvg.map(h => h.hour + ':00'),
        datasets: [{
          label: 'Snitt cykler/timme',
          data: this.hourlyAvg.map(h => h.avg),
          backgroundColor: this.hourlyAvg.map(h => {
            const max = Math.max(...this.hourlyAvg.map(x => x.avg));
            const ratio = max > 0 ? h.avg / max : 0;
            return ratio > 0.75 ? 'rgba(72,187,120,0.8)' : ratio > 0.5 ? 'rgba(66,153,225,0.7)' : ratio > 0.25 ? 'rgba(160,174,192,0.5)' : 'rgba(113,128,150,0.3)';
          }),
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { display: false } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, beginAtZero: true }
        }
      }
    });
  }

  // ======== TAB 4: SKIFTÖVERSIKT ========

  loadShiftData() {
    this.loading = true;
    this.bonusService.getTeamStats(this.selectedPeriod === 'week' ? 'week' : 'month').pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { this.error = 'Kunde inte ladda skiftdata'; this.loading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.allShifts = res.data.shifts || [];
        this.teamAggregate = res.data.aggregate;
        clearTimeout(this.chartTimeout);
        this.chartTimeout = setTimeout(() => {
          if (!this.destroy$.closed) this.renderBubbleChart();
        }, 100);
      }
      this.loading = false;
    });
  }

  toggleShiftExpand(shiftNum: number) {
    this.expandedShift = this.expandedShift === shiftNum ? null : shiftNum;
  }

  private renderBubbleChart() {
    if (this.bubbleChart) this.bubbleChart.destroy();
    const canvas = document.getElementById('bubbleChart') as HTMLCanvasElement;
    if (!canvas || !this.allShifts.length) return;

    const points = this.allShifts.map(s => ({
      x: s.kpis?.effektivitet ?? 0,
      y: s.kpis?.produktivitet ?? 0,
      r: Math.max(4, Math.min(20, (s.cycles || 1) / 2))
    }));

    const colors = this.allShifts.map(s => {
      const b = s.kpis?.bonus_avg ?? 0;
      return b >= 90 ? 'rgba(72,187,120,0.6)' : b >= 70 ? 'rgba(236,201,75,0.6)' : 'rgba(229,62,62,0.6)';
    });

    const shifts = this.allShifts; // Capture for closure
    this.bubbleChart = new Chart(canvas, {
      type: 'bubble',
      data: {
        datasets: [{
          label: 'Skift',
          data: points,
          backgroundColor: colors,
          borderColor: colors.map(c => c.replace('0.6', '1')),
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const s = shifts[ctx.dataIndex];
                if (!s) return 'Ingen data';
                return `Skift #${s.shift_number}: Eff ${s.kpis?.effektivitet}%, Prod ${s.kpis?.produktivitet}, Bonus ${s.kpis?.bonus_avg}`;
              }
            }
          }
        },
        scales: {
          x: { title: { display: true, text: 'Effektivitet (%)', color: '#718096' }, ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, min: 0, max: 100 },
          y: { title: { display: true, text: 'Produktivitet', color: '#718096' }, ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, beginAtZero: true }
        }
      }
    });
  }

  // ======== TAB 6: STOPPANALYS ========
  // OBS: Denna analys använder rast-data som proxy för stopp.
  // Riktig stoppanalys kräver utökad PLC-integration med stopp-events.

  loadStopAnalysis() {
    this.stopAnalysisLoading = true;
    this.error = '';

    // Hämta dagens rast-status (inkl. events för tidslinje)
    this.rebotlingService.getRastStatus().pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => { this.error = 'Kunde inte ladda raststatus'; this.stopAnalysisLoading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success) {
        this.rastStatus = res.data;
      }
    });

    // Hämta linjestatus
    this.rebotlingService.getRunningStatus().pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(res => {
      if (res?.success) {
        this.lineStatus = res.data;
      }
    });

    // Hämta statistik senaste 30 dagarna (för rast-per-dag-diagram, 14 dagar visas)
    const today = new Date();
    const startDate = new Date(today);
    startDate.setDate(startDate.getDate() - 29);
    const startStr = startDate.toISOString().split('T')[0];
    const endStr   = today.toISOString().split('T')[0];

    this.rebotlingService.getStatistics(startStr, endStr).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => { this.stopAnalysisLoading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        // Bygg rasttid per dag från onoff_events (om tillgänglig) eller skapa dummy-sammanfattning
        // Rast-data aggregeras per datum baserat på statistik-svaret
        this.buildRastHistoryFromStats(res.data);
        clearTimeout(this.chartTimeout);
        this.chartTimeout = setTimeout(() => {
          if (!this.destroy$.closed) this.renderStopRastChart();
        }, 150);
      }
      this.stopAnalysisLoading = false;
    });
  }

  /**
   * Aggregerar rasttid per dag från statistik-data.
   * Eftersom statistics-endpointen ger cyklar + runtime per dag, estimerar vi
   * rasttid som skillnaden mot total skifttid. Detta är en approximation.
   * OBS: För exakt rasttid per dag krävs rast-events per dag — inte tillgängligt i
   * den nuvarande statistik-endpointen. Vi visar totalt antal raster idag (från rast-endpoint).
   */
  private buildRastHistoryFromStats(data: any) {
    // Bygg daglig rastdata: cykeltid × antal cyklar används som körtids-proxy
    // Senaste 14 dagarna för diagrammet
    const cycleDays: { date: string; cycles: number; runtime: number }[] = [];

    (data.cycles || []).forEach((c: any) => {
      const date = c.datum?.substring(0, 10) || '';
      if (!date) return;
      const existing = cycleDays.find(d => d.date === date);
      if (existing) {
        existing.cycles++;
        existing.runtime += c.cycle_time ?? 0;
      } else {
        cycleDays.push({ date, cycles: 1, runtime: c.cycle_time ?? 0 });
      }
    });

    // Sortera och ta senaste 14 dagarna
    cycleDays.sort((a, b) => a.date.localeCompare(b.date));
    const recent14 = cycleDays.slice(-14);

    // Beräkna estimerad rasttid: (8h skift - körtid per dag)
    // OBS: Approximation — körtid från PLC runtime_plc fältet vore mer exakt
    const shiftHours = 8;
    this.rastHistory14 = recent14.map(d => {
      const runtimeH = d.runtime / 60;
      const rastEst  = Math.max(0, Math.round((shiftHours - runtimeH) * 60));
      return {
        date: d.date,
        totalRastMinutes: rastEst,
        rastCount: 0  // per-dag rastantal kräver rastevents per dag — ej tillgängligt här
      };
    });
  }

  /** Sammanfattning: totalt antal raster, total rasttid, snitt raster/dag (senaste 30 dagar) */
  getStopSummary30d(): { totalRaster: number; totalRastTid: number; snittPerDag: number } {
    if (!this.rastStatus) return { totalRaster: 0, totalRastTid: 0, snittPerDag: 0 };
    // Vi har idag-data från rast-endpointen
    const todayRaster  = this.rastStatus.rast_count_today ?? 0;
    const todayMinuter = this.rastStatus.rast_minutes_today ?? 0;
    return {
      totalRaster:  todayRaster,
      totalRastTid: todayMinuter,
      snittPerDag:  Math.round(todayMinuter)  // idag-data, inte 30-dagar (se OBS nedan)
    };
  }

  /** Tidslinje-block för idag baserat på rast-events */
  getTimelineBlocks(): { type: 'running' | 'rast'; label: string; widthPct: number; tooltip: string }[] {
    if (!this.rastStatus?.events || this.rastStatus.events.length === 0) {
      // Ingen event-data: visa bara status
      return [];
    }

    const shiftStartH = 6;  // Skift börjar 06:00
    const shiftEndH   = 22; // Skift slutar 22:00
    const totalMin = (shiftEndH - shiftStartH) * 60;

    const toMin = (dateStr: string): number => {
      const d = new Date(dateStr);
      return (d.getHours() - shiftStartH) * 60 + d.getMinutes();
    };

    const blocks: { type: 'running' | 'rast'; label: string; widthPct: number; tooltip: string }[] = [];
    const events = [...this.rastStatus.events].sort((a: any, b: any) =>
      new Date(a.datum).getTime() - new Date(b.datum).getTime()
    );

    let curMin = 0;

    events.forEach((ev: any) => {
      const evMin = Math.max(0, Math.min(toMin(ev.datum), totalMin));
      if (evMin <= curMin) return;

      const dur = evMin - curMin;
      const widthPct = (dur / totalMin) * 100;

      if (ev.rast_status === 1) {
        // Övergång till rast — lägg till körblock
        blocks.push({ type: 'running', label: '', widthPct, tooltip: `Kör ${dur} min` });
      } else {
        // Övergång till kör — lägg till rastblock
        blocks.push({ type: 'rast', label: '', widthPct, tooltip: `Rast ${dur} min` });
      }
      curMin = evMin;
    });

    // Fyll ut resten av dagen
    if (curMin < totalMin) {
      const dur = totalMin - curMin;
      const widthPct = (dur / totalMin) * 100;
      const lastIsRast = this.rastStatus.on_rast;
      blocks.push({
        type: lastIsRast ? 'rast' : 'running',
        label: '',
        widthPct,
        tooltip: `${lastIsRast ? 'Rast' : 'Kör'} ${dur} min (pågående)`
      });
    }

    return blocks;
  }

  private renderStopRastChart() {
    if (this.stopRastChart) this.stopRastChart.destroy();
    const canvas = document.getElementById('stopRastChart') as HTMLCanvasElement;
    if (!canvas || !this.rastHistory14.length) return;

    const labels = this.rastHistory14.map(d => d.date.substring(5));
    const values = this.rastHistory14.map(d => d.totalRastMinutes);

    this.stopRastChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Est. rasttid (min)',
          data: values,
          backgroundColor: values.map(v => {
            const max = Math.max(...values, 1);
            const ratio = v / max;
            return ratio > 0.75 ? 'rgba(237,137,54,0.8)' : ratio > 0.4 ? 'rgba(236,201,75,0.7)' : 'rgba(66,153,225,0.55)';
          }),
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            callbacks: {
              label: (ctx: any) => ` ${ctx.raw} min estimerad rasttid`
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, beginAtZero: true,
               title: { display: true, text: 'Minuter', color: '#718096', font: { size: 11 } } }
        }
      }
    });
  }

  // ======== HELPERS ========

  getBonusClass(bonus: number): string {
    if (bonus >= 80) return 'text-success';
    if (bonus >= 70) return 'text-warning';
    return 'text-danger';
  }

  getBonusBadge(bonus: number): string {
    if (bonus >= 90) return 'badge bg-success';
    if (bonus >= 70) return 'badge bg-warning text-dark';
    return 'badge bg-danger';
  }

  getPositionName(pos: string | undefined): string {
    if (!pos) return '-';
    const map: { [k: string]: string } = { '1': 'Tvättplats', '2': 'Kontroll', '3': 'Truck' };
    return map[pos] || pos;
  }

  // ======== TAB 5: BÄSTA SKIFT ========

  loadBestShifts() {
    this.bestShiftsLoading = true;
    this.rebotlingService.getBestShifts(this.bestShiftsLimit).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { this.error = 'Kunde inte ladda bästa skift'; this.bestShiftsLoading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.bestShifts = res.data;
        clearTimeout(this.chartTimeout);
        this.chartTimeout = setTimeout(() => {
          if (!this.destroy$.closed) this.renderBestShiftsChart();
        }, 100);
      }
      this.bestShiftsLoading = false;
    });
  }

  private renderBestShiftsChart() {
    if (this.bestShiftsChart) this.bestShiftsChart.destroy();
    const canvas = document.getElementById('bestShiftsChart') as HTMLCanvasElement;
    if (!canvas || !this.bestShifts.length) return;

    const labels = this.bestShifts.map(s => `#${s.skiftraknare} (${s.dag})`);
    const ibcValues = this.bestShifts.map(s => s.ibc_ok);
    const kvalValues = this.bestShifts.map(s => s.kvalitet_pct);

    this.bestShiftsChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC OK',
            data: ibcValues,
            backgroundColor: ibcValues.map((_, i) => i === 0 ? 'rgba(236,201,75,0.85)' : i <= 2 ? 'rgba(66,153,225,0.75)' : 'rgba(113,128,150,0.5)'),
            borderRadius: 4,
            yAxisID: 'y'
          },
          {
            label: 'Kvalitet %',
            data: kvalValues,
            type: 'line',
            borderColor: '#48bb78',
            backgroundColor: 'transparent',
            pointBackgroundColor: '#48bb78',
            pointRadius: 4,
            tension: 0.3,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0' } },
          tooltip: {
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#ecc94b',
            borderWidth: 1
          }
        },
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45 }, grid: { color: '#2d3748' } },
          y: { position: 'left', ticks: { color: '#718096' }, grid: { color: '#2d3748' }, beginAtZero: true, title: { display: true, text: 'IBC OK', color: '#718096' } },
          y1: { position: 'right', ticks: { color: '#718096', callback: (v: any) => v + '%' }, grid: { display: false }, min: 0, max: 100, title: { display: true, text: 'Kvalitet %', color: '#718096' } }
        }
      }
    });
  }

  getBestShiftMedal(rank: number): string {
    if (rank === 1) return 'fas fa-medal text-warning';
    if (rank === 2) return 'fas fa-medal text-secondary';
    if (rank === 3) return 'fas fa-medal text-danger';
    return 'fas fa-hashtag text-muted';
  }

  exportRankingCSV() {
    const data = this.positionFilter === 'all'
      ? this.overallRanking
      : (this.positionRankings[this.positionFilter] || []);
    if (data.length === 0) return;
    const header = ['Rank', 'Operatör', 'Position', 'Bonus Snitt', 'Effektivitet', 'Produktivitet', 'Kvalitet', 'IBC OK', 'Timmar'];
    const rows = data.map((r: any) => [
      r.rank,
      r.operator_name || ('Op ' + r.operator_id),
      r.position || '-',
      (r.bonus_avg ?? 0).toFixed(1),
      (r.effektivitet ?? 0).toFixed(1) + '%',
      (r.produktivitet ?? 0).toFixed(1),
      (r.kvalitet ?? 0).toFixed(1) + '%',
      r.total_ibc_ok ?? 0,
      (r.total_hours ?? 0).toFixed(1)
    ]);
    const csv = [header, ...rows].map(r => r.map((c: any) => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ranking-${this.selectedPeriod}-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
}
