import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, catchError, of, timeout } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService, AuthUser } from '../../services/auth.service';
import { BonusService, RankingEntry, ShiftStats, TeamStatsResponse } from '../../services/bonus.service';
import { RebotlingService, BestShift, StoppageDayEntry, StoppageCategoryEntry, StoppageReasonEntry, RastStatusResponse, LineStatusResponse, RastEvent } from '../../services/rebotling.service';
import { Chart, registerables, TooltipItem } from 'chart.js';
import { localToday, parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

interface DailyDataPoint {
  date: string;
  bonus: number;
  ibcOk: number;
}

interface WeekdayDataPoint {
  day: string;
  avgBonus: number;
  avgIbc: number;
}

interface ParetoItem {
  orsak: string;
  kategori: string;
  total_minuter: number;
  snitt_minuter: number;
  antal_stopp: number;
  pct_av_total: number;
  kumulativ_pct: number;
}

interface HeatmapApiResponse {
  success: boolean;
  data: { date: string; hour: number; count: number }[];
}

interface ParetoApiResponse {
  success: boolean;
  items?: ParetoItem[];
}

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
  user: AuthUser | null = null;
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
  teamAggregate: NonNullable<TeamStatsResponse['data']>['aggregate'] | null = null;
  dailyData: DailyDataPoint[] = [];
  weekdayData: WeekdayDataPoint[] = [];
  bestDay: DailyDataPoint | null = null;
  worstDay: DailyDataPoint | null = null;
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

  // Tab 6: Stoppanalys — riktig data från stoppage_log
  stopAnalysisLoading = false;
  stopDays = 30;
  stoppageEmpty = false;
  stoppageEmptyReason = '';
  stoppageByDay: StoppageDayEntry[] = [];
  stoppageByCategory: StoppageCategoryEntry[] = [];
  stoppageTopReasons: StoppageReasonEntry[] = [];
  stoppageTotalEvents = 0;
  stoppageTotalMinutes = 0;
  // Tidslinje-data (rast-proxy, behålls)
  rastStatus: RastStatusResponse['data'] | null = null;
  lineStatus: LineStatusResponse['data'] | null = null;
  rastHistory14: { date: string; totalRastMinutes: number; rastCount: number }[] = [];
  private stopRastChart: Chart | null = null;
  private stoppageDailyChart: Chart | null = null;

  // Tab 7: Pareto-analys
  paretoData: ParetoItem[] = [];
  paretoLoading = false;
  paretoPeriod = 30;
  private paretoChart: Chart | null = null;

  // Cachade template-värden (undviker funktionsanrop vid varje change detection)
  cachedFilteredRanking: RankingEntry[] = [];
  cachedTimelineBlocks: { type: 'running' | 'rast'; label: string; widthPct: number; tooltip: string }[] = [];
  cachedTimelinePercentages: { runPct: number; rastPct: number } = { runPct: 100, rastPct: 0 };
  cachedStopHoursMin = '';
  cachedAvgStopMinutes = 0;
  cachedWorstCategory = '-';
  cachedParetoTotalMinuter = 0;
  cachedParetoTotalStopp = 0;
  cachedParetoEightyPctGroup = 0;

  // Charts
  private destroy$ = new Subject<void>();
  private rankingChart: Chart | null = null;
  private radarChart: Chart | null = null;
  private dailyTrendChart: Chart | null = null;
  private weekdayChart: Chart | null = null;
  private hourlyBarChart: Chart | null = null;
  private bubbleChart: Chart | null = null;

  // Timeout IDs
  private tabTimeout: ReturnType<typeof setTimeout> | undefined;
  private chartTimeout: ReturnType<typeof setTimeout> | undefined;
  private radarTimeout: ReturnType<typeof setTimeout> | undefined;

  /** Versionsnummer — förhindrar att gamla HTTP-svar skriver över nya vid snabb period/tab-byte */
  private loadVersion = 0;

  constructor(
    private auth: AuthService,
    private bonusService: BonusService,
    private rebotlingService: RebotlingService
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val ?? null;
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
     this.bestShiftsChart, this.stopRastChart, this.stoppageDailyChart,
     this.paretoChart].forEach(c => { try { c?.destroy(); } catch (e) {} });
    this.rankingChart = null;
    this.radarChart = null;
    this.dailyTrendChart = null;
    this.weekdayChart = null;
    this.hourlyBarChart = null;
    this.bubbleChart = null;
    this.bestShiftsChart = null;
    this.stopRastChart = null;
    this.stoppageDailyChart = null;
    this.paretoChart = null;
  }

  setTab(tab: string) {
    this.activeTab = tab;
    this.error = '';
    clearTimeout(this.tabTimeout);
    this.tabTimeout = setTimeout(() => this.loadTabData(), 50);
  }

  onPeriodChange() {
    ++this.loadVersion;
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
      case 'pareto':       this.loadParetoData();    break;
    }
  }

  // ======== TAB 1: OPERATÖRSJÄMFÖRELSE ========

  loadOperatorData() {
    const version = this.loadVersion;
    this.loading = true;
    this.bonusService.getRanking(this.selectedPeriod, 20).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda rankingdata'; this.loading = false; } return of(null); })
    ).subscribe(res => {
      if (version !== this.loadVersion) return;
      if (res?.success && res.data) {
        this.overallRanking = res.data.rankings.overall || [];
        this.positionRankings = {
          'Tvättplats': res.data.rankings.position_1 || [],
          'Kontroll': res.data.rankings.position_2 || [],
          'Truck': res.data.rankings.position_3 || []
        };
        this.refreshFilteredRanking();
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

    return [...data].sort((a: RankingEntry, b: RankingEntry) => {
      const aVal = (a as unknown as Record<string, number>)[this.sortColumn] ?? 0;
      const bVal = (b as unknown as Record<string, number>)[this.sortColumn] ?? 0;
      return this.sortDirection === 'desc' ? bVal - aVal : aVal - bVal;
    });
  }

  /** Uppdatera cachad filteredRanking (anropas vid data-/filter-/sort-ändring) */
  private refreshFilteredRanking(): void {
    this.cachedFilteredRanking = this.getFilteredRanking();
  }

  toggleSort(col: string) {
    if (this.sortColumn === col) {
      this.sortDirection = this.sortDirection === 'desc' ? 'asc' : 'desc';
    } else {
      this.sortColumn = col;
      this.sortDirection = 'desc';
    }
    this.refreshFilteredRanking();
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
    this.refreshFilteredRanking();
    try { if (this.rankingChart) this.rankingChart.destroy(); } catch (e) {}
    this.rankingChart = null;
    const canvas = document.getElementById('rankingChart') as HTMLCanvasElement;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const data = this.getFilteredRanking().slice(0, 15);
    const labels = data.map((d: RankingEntry) => d.operator_name || ('Op ' + d.operator_id));
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
    try { if (this.radarChart) this.radarChart.destroy(); } catch (e) {}
    this.radarChart = null;
    const canvas = document.getElementById('radarCompareChart') as HTMLCanvasElement;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const radarColors = ['rgba(66,153,225,0.6)', 'rgba(72,187,120,0.6)', 'rgba(236,201,75,0.6)'];
    const radarBorders = ['rgb(66,153,225)', 'rgb(72,187,120)', 'rgb(236,201,75)'];

    const datasets = this.radarSelected.map((opId, i) => {
      const op = this.overallRanking.find(r => r.operator_id === opId);
      if (!op) return null;
      return {
        label: op.operator_name || ('Op ' + opId),
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
        datasets: datasets as NonNullable<typeof datasets[number]>[]
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
    const version = this.loadVersion;
    this.loading = true;
    this.bonusService.getTeamStats(this.selectedPeriod === 'week' ? 'week' : 'month').pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda dagsdata'; this.loading = false; } return of(null); })
    ).subscribe(res => {
      if (version !== this.loadVersion) return;
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
      } else if (!res) {
        // catchError redan hanterade felet
      } else {
        this.dailyData = [];
        this.weekdayData = [];
        this.bestDay = null;
        this.worstDay = null;
        this.avgBonus = 0;
        this.totalIbc = 0;
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
      const wd = parseLocalDate(d.date).getDay();
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
    try { if (this.dailyTrendChart) this.dailyTrendChart.destroy(); } catch (e) {}
    this.dailyTrendChart = null;
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
    try { if (this.weekdayChart) this.weekdayChart.destroy(); } catch (e) {}
    this.weekdayChart = null;
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
    const version = this.loadVersion;
    this.loading = true;
    const days = this.selectedPeriod === 'week' ? 7 : 30;

    // Använd den aggregerade heatmap-endpointen (1 anrop istället för N)
    this.rebotlingService.getHeatmap(days).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda timdata'; this.loading = false; } return of(null); })
    ).subscribe((res: HeatmapApiResponse | null) => {
      if (version !== this.loadVersion) return;
      if (!res?.success || !Array.isArray(res.data)) { this.loading = false; return; }

      const rows: { [date: string]: { [hour: number]: number } } = {};
      const hourTotals: { [hour: number]: { sum: number; count: number } } = {};

      res.data.forEach(({ date, hour, count }) => {
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
    try { if (this.hourlyBarChart) this.hourlyBarChart.destroy(); } catch (e) {}
    this.hourlyBarChart = null;
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
    const version = this.loadVersion;
    this.loading = true;
    this.bonusService.getTeamStats(this.selectedPeriod === 'week' ? 'week' : 'month').pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda skiftdata'; this.loading = false; } return of(null); })
    ).subscribe(res => {
      if (version !== this.loadVersion) return;
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
    try { if (this.bubbleChart) this.bubbleChart.destroy(); } catch (e) {}
    this.bubbleChart = null;
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
              label: (ctx: TooltipItem<'bubble'>) => {
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

  loadStopAnalysis() {
    const version = this.loadVersion;
    this.stopAnalysisLoading = true;
    this.error = '';

    // Hämta riktig stoppdata från stoppage_log
    this.rebotlingService.getStoppageAnalysis(this.stopDays).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda stoppdata'; this.stopAnalysisLoading = false; } return of(null); })
    ).subscribe(res => {
      if (version !== this.loadVersion) return;
      if (res?.success) {
        this.stoppageEmpty        = res.empty ?? false;
        this.stoppageEmptyReason  = res.reason ?? '';
        this.stoppageByDay        = res.by_day ?? [];
        this.stoppageByCategory   = res.by_category ?? [];
        this.stoppageTopReasons   = res.top_reasons ?? [];
        this.stoppageTotalEvents  = res.total_events ?? 0;
        this.stoppageTotalMinutes = res.total_minutes ?? 0;
        this.refreshStopKpis();
        clearTimeout(this.chartTimeout);
        this.chartTimeout = setTimeout(() => {
          if (!this.destroy$.closed) this.renderStoppageDailyChart();
        }, 150);
      }
      this.stopAnalysisLoading = false;
    });

    // Hämta rast-status för tidslinje (kompletterande data)
    this.rebotlingService.getRastStatus().pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(res => {
      if (res?.success) {
        this.rastStatus = res.data;
        this.refreshTimelineCache();
      }
    });

    // Hämta linjestatus
    this.rebotlingService.getRunningStatus().pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(res => {
      if (res?.success) this.lineStatus = res.data;
    });
  }

  onStopDaysChange() {
    this.loadStopAnalysis();
  }

  /** Stoppid i timmar och minuter */
  getStopHoursMin(): string {
    const h = Math.floor(this.stoppageTotalMinutes / 60);
    const m = Math.round(this.stoppageTotalMinutes % 60);
    if (h > 0) return `${h}h ${m}min`;
    return `${m} min`;
  }

  /** Snitt stopplängd per händelse */
  getAvgStopMinutes(): number {
    if (!this.stoppageTotalEvents) return 0;
    return Math.round((this.stoppageTotalMinutes / this.stoppageTotalEvents) * 10) / 10;
  }

  /** Kategorin med mest total stoppid */
  getWorstCategory(): string {
    if (!this.stoppageByCategory.length) return '-';
    const worst = this.stoppageByCategory[0];
    const labels: { [k: string]: string } = {
      maskin: 'Maskin', material: 'Material', 'operatör': 'Operatör', övrigt: 'Övrigt'
    };
    return labels[worst.category] ?? worst.category;
  }

  getCategoryLabel(cat: string): string {
    const labels: { [k: string]: string } = {
      maskin: 'Maskin', material: 'Material', 'operatör': 'Operatör', övrigt: 'Övrigt'
    };
    return labels[cat] ?? cat;
  }

  getCategoryBadgeClass(cat: string): string {
    const map: { [k: string]: string } = {
      maskin: 'badge-category-maskin',
      material: 'badge-category-material',
      'operatör': 'badge-category-operator',
      övrigt: 'badge-category-ovrigt'
    };
    return 'category-badge ' + (map[cat] ?? 'badge-category-ovrigt');
  }

  private getCategoryColor(cat: string, alpha = 0.75): string {
    const map: { [k: string]: string } = {
      maskin:     `rgba(229,62,62,${alpha})`,
      material:   `rgba(237,137,54,${alpha})`,
      'operatör': `rgba(66,153,225,${alpha})`,
      övrigt:     `rgba(160,174,192,${alpha})`
    };
    return map[cat] ?? `rgba(160,174,192,${alpha})`;
  }

  private renderStoppageDailyChart() {
    try { if (this.stoppageDailyChart) this.stoppageDailyChart.destroy(); } catch (e) {}
    this.stoppageDailyChart = null;
    const canvas = document.getElementById('stoppageDailyChart') as HTMLCanvasElement;
    if (!canvas || !this.stoppageByDay.length) return;

    // Sortera dagarna kronologiskt
    const sorted = [...this.stoppageByDay].sort((a, b) => a.dag.localeCompare(b.dag));
    const labels = sorted.map(d => d.dag.substring(5));

    // Kategorier
    const cats = ['maskin', 'material', 'operatör', 'övrigt'];
    const catLabels: { [k: string]: string } = {
      maskin: 'Maskin', material: 'Material', 'operatör': 'Operatör', övrigt: 'Övrigt'
    };

    const datasets = cats.map(cat => ({
      label: catLabels[cat] ?? cat,
      data: sorted.map(d => d.kategorier?.[cat] ?? 0),
      backgroundColor: this.getCategoryColor(cat, 0.75),
      borderColor: this.getCategoryColor(cat, 1),
      borderWidth: 1,
      borderRadius: 3,
      stack: 'dag'
    }));

    this.stoppageDailyChart = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.97)',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            callbacks: {
              label: (ctx: TooltipItem<'bar'>) => (ctx.raw as number) > 0 ? ` ${ctx.dataset.label}: ${ctx.raw} min` : ''
            }
          }
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#718096', maxRotation: 45, font: { size: 9 } },
            grid: { color: '#2d3748' }
          },
          y: {
            stacked: true,
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' },
            beginAtZero: true,
            title: { display: true, text: 'Stoppid (min)', color: '#718096', font: { size: 11 } }
          }
        }
      }
    });
  }

  /** Tidslinje-block för idag baserat på rast-events */
  getTimelineBlocks(): { type: 'running' | 'rast'; label: string; widthPct: number; tooltip: string }[] {
    if (!this.rastStatus?.events || this.rastStatus.events.length === 0) {
      return [];
    }

    const shiftStartH = 6;
    const shiftEndH   = 22;
    const totalMin = (shiftEndH - shiftStartH) * 60;

    const toMin = (dateStr: string): number => {
      const d = new Date(dateStr);
      return (d.getHours() - shiftStartH) * 60 + d.getMinutes();
    };

    const blocks: { type: 'running' | 'rast'; label: string; widthPct: number; tooltip: string }[] = [];
    const events = [...this.rastStatus.events].sort((a: RastEvent, b: RastEvent) =>
      new Date(a.datum).getTime() - new Date(b.datum).getTime()
    );

    let curMin = 0;

    events.forEach((ev: RastEvent) => {
      const evMin = Math.max(0, Math.min(toMin(ev.datum), totalMin));
      if (evMin <= curMin) return;

      const dur = evMin - curMin;
      const widthPct = (dur / totalMin) * 100;

      if (ev.rast_status === 1) {
        blocks.push({ type: 'running', label: '', widthPct, tooltip: `Kör ${dur} min` });
      } else {
        blocks.push({ type: 'rast', label: '', widthPct, tooltip: `Rast ${dur} min` });
      }
      curMin = evMin;
    });

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
    // Behålls för bakåtkompatibilitet men används ej längre i UI
    try { if (this.stopRastChart) this.stopRastChart.destroy(); } catch (e) {}
    this.stopRastChart = null;
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
    const version = this.loadVersion;
    this.bestShiftsLoading = true;
    this.rebotlingService.getBestShifts(this.bestShiftsLimit).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(err => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda bästa skift'; this.bestShiftsLoading = false; } return of(null); })
    ).subscribe(res => {
      if (version !== this.loadVersion) return;
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
    try { if (this.bestShiftsChart) this.bestShiftsChart.destroy(); } catch (e) {}
    this.bestShiftsChart = null;
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
          y1: { position: 'right', ticks: { color: '#718096', callback: (v: string | number) => v + '%' }, grid: { display: false }, min: 0, max: 100, title: { display: true, text: 'Kvalitet %', color: '#718096' } }
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
    const rows = data.map((r: RankingEntry) => [
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
    const csv = [header, ...rows].map(r => r.map((c: string | number) => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ranking-${this.selectedPeriod}-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }


  // ======== STOPPANALYS — EXPORT & STATISTIK ========

  /** Beräkna periodstatistik för stoppdata (alla laddade dagar) */
  get veckoStoppStats(): { total: number; snittMin: number; langstMin: number } {
    if (!this.stoppageByDay || this.stoppageByDay.length === 0) {
      return { total: 0, snittMin: 0, langstMin: 0 };
    }
    const total = this.stoppageByDay.reduce((s, d) => s + (d.antal ?? 0), 0);
    const totMin = this.stoppageByDay.reduce((s, d) => s + (d.total_minuter ?? 0), 0);
    const snittMin = total > 0 ? Math.round((totMin / total) * 10) / 10 : 0;
    const langstMin = this.stoppageByDay.length > 0
      ? Math.max(...this.stoppageByDay.map(d => d.total_minuter ?? 0))
      : 0;
    return { total, snittMin, langstMin };
  }

  /** Procent körtid vs rasttid för tidslinjens procent-bar */
  getTimelinePercentages(): { runPct: number; rastPct: number } {
    if (!this.rastStatus) return { runPct: 100, rastPct: 0 };
    const totalMin = 16 * 60; // 06:00–22:00 = 960 min
    const rastMin = Math.min(this.rastStatus.rast_minutes_today ?? 0, totalMin);
    const runMin = Math.max(0, totalMin - rastMin);
    return {
      runPct: Math.round((runMin / totalMin) * 100),
      rastPct: Math.round((rastMin / totalMin) * 100)
    };
  }

  /** Exportera stoppdata per dag som CSV */
  exportStopCSV(): void {
    if (!this.stoppageByDay || this.stoppageByDay.length === 0) return;

    const header = ['Datum', 'Antal stopp', 'Total stoppid (min)', 'Maskin (min)', 'Material (min)', 'Operatör (min)', 'Övrigt (min)'];
    const sorted = [...this.stoppageByDay].sort((a, b) => a.dag.localeCompare(b.dag));
    const rows = sorted.map(d => [
      d.dag,
      d.antal ?? 0,
      d.total_minuter ?? 0,
      d.kategorier?.['maskin'] ?? 0,
      d.kategorier?.['material'] ?? 0,
      d.kategorier?.['operatör'] ?? 0,
      d.kategorier?.['övrigt'] ?? 0
    ]);

    const csv = [header, ...rows]
      .map(r => r.map((c: string | number) => `"${c}"`).join(';'))
      .join('\n');

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `stoppdata-${this.stopDays}dagar-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ======== TAB 7: PARETO-ANALYS ========

  loadParetoData() {
    const version = this.loadVersion;
    this.paretoLoading = true;
    this.error = '';
    this.rebotlingService.getParetoStoppage(this.paretoPeriod).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => { if (version === this.loadVersion) { this.error = 'Kunde inte ladda paretodata'; this.paretoLoading = false; } return of(null); })
    ).subscribe(res => {
      if (version !== this.loadVersion) return;
      if (res?.success) {
        this.paretoData = res.items ?? [];
        this.refreshParetoKpis();
      }
      this.paretoLoading = false;
      clearTimeout(this.chartTimeout);
      this.chartTimeout = setTimeout(() => {
        if (!this.destroy$.closed) this.buildParetoChart();
      }, 150);
    });
  }

  onParetoPeriodChange(days: number) {
    this.paretoPeriod = days;
    this.loadParetoData();
  }

  getParetoTotalMinuter(): number {
    return this.paretoData.reduce((s, d) => s + (d.total_minuter ?? 0), 0);
  }

  getParetoTotalStopp(): number {
    return this.paretoData.reduce((s, d) => s + (d.antal_stopp ?? 0), 0);
  }

  getParetoEightyPctGroup(): number {
    let count = 0;
    for (const item of this.paretoData) {
      count++;
      if ((item.kumulativ_pct ?? 0) >= 80) break;
    }
    return count;
  }

  private getParetoBarColor(kategori: string, alpha = 0.8): string {
    const map: { [k: string]: string } = {
      maskin:     `rgba(229,62,62,${alpha})`,
      material:   `rgba(237,137,54,${alpha})`,
      'operatör': `rgba(66,153,225,${alpha})`,
      övrigt:     `rgba(160,174,192,${alpha})`
    };
    return map[kategori] ?? `rgba(160,174,192,${alpha})`;
  }

  buildParetoChart() {
    try { if (this.paretoChart) this.paretoChart.destroy(); } catch (e) {}
    this.paretoChart = null;
    const canvas = document.getElementById('paretoChart') as HTMLCanvasElement;
    if (!canvas || !this.paretoData.length) return;

    const labels = this.paretoData.map(d => d.orsak ?? 'Okänd');
    const minuterData = this.paretoData.map(d => d.total_minuter ?? 0);
    const kumulativData = this.paretoData.map(d => d.kumulativ_pct ?? 0);
    const barColors = this.paretoData.map(d => this.getParetoBarColor(d.kategori));

    // Referenslinje 80% som annotation-dataset (horisontell linje)
    const eightyLine = this.paretoData.map(() => 80);

    this.paretoChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            type: 'bar',
            label: 'Stopptid (min)',
            data: minuterData,
            backgroundColor: barColors,
            borderColor: barColors.map(c => c.replace('0.8)', '1)')),
            borderWidth: 1,
            borderRadius: 3,
            yAxisID: 'y',
            order: 2
          },
          {
            type: 'line',
            label: 'Kumulativt %',
            data: kumulativData,
            borderColor: '#ecc94b',
            backgroundColor: 'rgba(236,201,75,0.12)',
            pointBackgroundColor: '#ecc94b',
            pointRadius: 4,
            tension: 0.2,
            fill: false,
            yAxisID: 'y1',
            order: 1
          },
          {
            type: 'line',
            label: '80%-gräns',
            data: eightyLine,
            borderColor: 'rgba(229,62,62,0.85)',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            yAxisID: 'y1',
            order: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 } }
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.97)',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx: TooltipItem<'bar'>) => {
                const item = this.paretoData[ctx.dataIndex];
                if (!item) return '';
                if (ctx.datasetIndex === 0) {
                  return [
                    ` Stopptid: ${item.total_minuter} min`,
                    ` Antal stopp: ${item.antal_stopp}`,
                    ` % av total: ${item.pct_av_total}%`
                  ];
                }
                if (ctx.datasetIndex === 1) {
                  return ` Kumulativt: ${item.kumulativ_pct}%`;
                }
                return '';
              }
            }
          }
        },
        scales: {
          x: {
            ticks: {
              color: '#718096',
              maxRotation: 45,
              font: { size: 10 }
            },
            grid: { color: '#2d3748' }
          },
          y: {
            position: 'left',
            beginAtZero: true,
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'Stopptid (min)', color: '#718096', font: { size: 11 } }
          },
          y1: {
            position: 'right',
            min: 0,
            max: 100,
            ticks: {
              color: '#a0aec0',
              callback: (v: string | number) => v + '%'
            },
            grid: { display: false },
            title: { display: true, text: 'Kumulativt %', color: '#718096', font: { size: 11 } }
          }
        }
      }
    });
  }

  // ======== Cachade KPI-uppdateringar (undviker funktionsanrop i template) ========

  private refreshStopKpis(): void {
    this.cachedStopHoursMin = this.getStopHoursMin();
    this.cachedAvgStopMinutes = this.getAvgStopMinutes();
    this.cachedWorstCategory = this.getWorstCategory();
  }

  private refreshTimelineCache(): void {
    this.cachedTimelineBlocks = this.getTimelineBlocks();
    this.cachedTimelinePercentages = this.getTimelinePercentages();
  }

  private refreshParetoKpis(): void {
    this.cachedParetoTotalMinuter = this.getParetoTotalMinuter();
    this.cachedParetoTotalStopp = this.getParetoTotalStopp();
    this.cachedParetoEightyPctGroup = this.getParetoEightyPctGroup();
  }

  // ======== trackBy-funktioner (undviker DOM-omskrivning vid change detection) ========

  trackByOperatorId(index: number, op: RankingEntry): number {
    return op.operator_id;
  }

  trackByShiftNumber(index: number, shift: ShiftStats): number {
    return shift.shift_number;
  }

  trackByIndex(index: number): number {
    return index;
  }

  trackByRank(index: number, s: BestShift): number {
    return s.rank;
  }

  trackByCategory(index: number, cat: StoppageCategoryEntry): string {
    return cat.category;
  }

  trackByReasonName(index: number, r: StoppageReasonEntry): string {
    return r.name;
  }

  trackByParetoOrsak(index: number, item: ParetoItem): string {
    return item.orsak;
  }

  trackByDate(index: number, day: { date: string }): string {
    return day.date;
  }
}
