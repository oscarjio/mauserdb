import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.service';
import { BonusService, RankingEntry, ShiftStats } from '../../services/bonus.service';
import { RebotlingService } from '../../services/rebotling.service';
import { forkJoin, catchError, of, timeout } from 'rxjs';
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
  heatmapHours = Array.from({ length: 17 }, (_, i) => i + 6); // 06-22

  // Tab 4: Skiftöversikt
  allShifts: ShiftStats[] = [];
  expandedShift: number | null = null;

  // Charts
  private rankingChart: Chart | null = null;
  private radarChart: Chart | null = null;
  private dailyTrendChart: Chart | null = null;
  private weekdayChart: Chart | null = null;
  private hourlyBarChart: Chart | null = null;
  private bubbleChart: Chart | null = null;

  constructor(
    private auth: AuthService,
    private bonusService: BonusService,
    private rebotlingService: RebotlingService
  ) {
    this.auth.loggedIn$.subscribe(val => this.loggedIn = val);
    this.auth.user$.subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadTabData();
  }

  ngOnDestroy() {
    this.destroyAllCharts();
  }

  private destroyAllCharts() {
    [this.rankingChart, this.radarChart, this.dailyTrendChart,
     this.weekdayChart, this.hourlyBarChart, this.bubbleChart].forEach(c => c?.destroy());
    this.rankingChart = null;
    this.radarChart = null;
    this.dailyTrendChart = null;
    this.weekdayChart = null;
    this.hourlyBarChart = null;
    this.bubbleChart = null;
  }

  setTab(tab: string) {
    this.activeTab = tab;
    this.error = '';
    setTimeout(() => this.loadTabData(), 50);
  }

  onPeriodChange() {
    this.loadTabData();
  }

  loadTabData() {
    switch (this.activeTab) {
      case 'operators': this.loadOperatorData(); break;
      case 'daily': this.loadDailyData(); break;
      case 'hourly': this.loadHourlyData(); break;
      case 'shifts': this.loadShiftData(); break;
    }
  }

  // ======== TAB 1: OPERATÖRSJÄMFÖRELSE ========

  loadOperatorData() {
    this.loading = true;
    this.bonusService.getRanking(this.selectedPeriod, 20).pipe(
      timeout(8000),
      catchError(err => { this.error = 'Kunde inte ladda rankingdata'; this.loading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.overallRanking = res.data.rankings.overall || [];
        this.positionRankings = {
          'Tvättplats': res.data.rankings.position_1 || [],
          'Kontroll': res.data.rankings.position_2 || [],
          'Truck': res.data.rankings.position_3 || []
        };
        setTimeout(() => this.renderRankingChart(), 100);
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
      setTimeout(() => this.renderRadarChart(), 50);
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
    const labels = data.map(d => 'Op ' + d.operator_id);
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
        label: 'Op ' + opId,
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
      catchError(err => { this.error = 'Kunde inte ladda dagsdata'; this.loading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.teamAggregate = res.data.aggregate;
        this.shifts = res.data.shifts || [];
        this.aggregateDailyData();
        setTimeout(() => {
          this.renderDailyTrendChart();
          this.renderWeekdayChart();
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
    const today = new Date();
    const days = this.selectedPeriod === 'week' ? 7 : 30;
    const requests: { [key: string]: any } = {};

    for (let i = 0; i < days; i++) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      const dateStr = d.toISOString().substring(0, 10);
      requests[dateStr] = this.rebotlingService.getDayStats(dateStr).pipe(
        timeout(8000),
        catchError(() => of(null))
      );
    }

    forkJoin(requests).subscribe((results: any) => {
      this.heatmapData = [];
      const hourTotals: { [hour: number]: { sum: number; count: number } } = {};

      Object.keys(results).sort().forEach(date => {
        const res = results[date];
        const hourData: { [hour: number]: number } = {};

        if (res?.success && res.data) {
          // day-stats returns cycles/events for that day
          const cycles = res.data.cycles || res.data || [];
          if (Array.isArray(cycles)) {
            cycles.forEach((c: any) => {
              const ts = c.datum || c.timestamp || '';
              if (ts) {
                const hour = new Date(ts).getHours();
                hourData[hour] = (hourData[hour] || 0) + 1;
              }
            });
          }
        }

        this.heatmapData.push({ date, hours: hourData });

        // Aggregate hourly
        this.heatmapHours.forEach(h => {
          if (!hourTotals[h]) hourTotals[h] = { sum: 0, count: 0 };
          hourTotals[h].sum += hourData[h] || 0;
          hourTotals[h].count++;
        });
      });

      this.hourlyAvg = this.heatmapHours.map(h => ({
        hour: h,
        avg: hourTotals[h]?.count ? Math.round(hourTotals[h].sum / hourTotals[h].count * 10) / 10 : 0
      }));

      setTimeout(() => this.renderHourlyBarChart(), 100);
      this.loading = false;
    });
  }

  getHeatmapColor(value: number): string {
    if (!value) return '#1a202c';
    const maxVal = this.getHeatmapMax();
    if (maxVal === 0) return '#1a202c';
    const intensity = Math.min(value / maxVal, 1);
    if (intensity < 0.25) return '#1a365d';
    if (intensity < 0.5) return '#2b6cb0';
    if (intensity < 0.75) return '#3182ce';
    return '#4299e1';
  }

  getHeatmapMax(): number {
    let max = 0;
    this.heatmapData.forEach(d => {
      this.heatmapHours.forEach(h => {
        if ((d.hours[h] || 0) > max) max = d.hours[h];
      });
    });
    return max || 1;
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
      catchError(err => { this.error = 'Kunde inte ladda skiftdata'; this.loading = false; return of(null); })
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.allShifts = res.data.shifts || [];
        this.teamAggregate = res.data.aggregate;
        setTimeout(() => this.renderBubbleChart(), 100);
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
                const s = this.allShifts[ctx.dataIndex];
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
}
