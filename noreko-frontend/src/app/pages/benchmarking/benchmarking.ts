import { Component, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import { localToday, parseLocalDate } from '../../utils/date-utils';
import {
  RebotlingService,
  BenchmarkingResponse,
  BenchmarkingWeek,
  BenchmarkingTopWeek,
  BenchmarkingMonthly,
  BenchmarkingBestDay,
  PersonalBestOperator,
  PersonalBestsResponse,
  MonthlyLeaderEntry,
  MonthlyLeadersResponse,
  HallOfFameDayEntry,
  HallOfFameDaysResponse,
} from '../../services/rebotling.service';
import { AuthService } from '../../services/auth.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-benchmarking',
  templateUrl: './benchmarking.html',
  styleUrl: './benchmarking.css',
  imports: [CommonModule],
})
export class BenchmarkingPage implements OnInit, OnDestroy {
  Math = Math;

  loading = true;
  error = '';

  currentWeek: BenchmarkingWeek | null = null;
  bestWeekEver: BenchmarkingWeek | null = null;
  bestDayEver: BenchmarkingBestDay | null = null;
  topWeeks: BenchmarkingTopWeek[] = [];
  monthlyTotals: BenchmarkingMonthly[] = [];

  currentWeekLabel = '';
  bestWeekLabel = '';

  progressPct = 0;
  ibcDiffToRecord = 0;
  isNewRecord = false;

  personalBests: { operators: PersonalBestOperator[]; team_record_ibc_h: number; team_best_day: number; team_best_week: number; team_best_month: number } | null = null;
  personalBestsLoading = false;
  monthlyLeaders: MonthlyLeaderEntry[] = [];
  monthlyLeadersLoading = false;
  hallOfFame: HallOfFameDayEntry[] = [];
  hallOfFameLoading = false;

  activeTab: 'overview' | 'personbasta' | 'halloffame' = 'overview';

  @ViewChild('monthlyChart') monthlyChartRef!: ElementRef<HTMLCanvasElement>;
  private monthlyChartInstance: Chart | null = null;

  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(
    private rebotlingService: RebotlingService,
    private auth: AuthService,
  ) {}

  ngOnInit(): void {
    this.load();
    this.loadPersonalBests();
    this.loadMonthlyLeaders();
    this.loadHallOfFame();
    this.pollInterval = setInterval(() => this.load(), 60_000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
    if (this.chartTimer !== null) {
      clearTimeout(this.chartTimer);
      this.chartTimer = null;
    }
    try { this.monthlyChartInstance?.destroy(); } catch (e) {}
    this.monthlyChartInstance = null;
  }

  get currentUserName(): string | null {
    return this.auth.user$.getValue()?.name || null;
  }

  get isLoggedIn(): boolean {
    return this.auth.loggedIn$.getValue();
  }

  get kpiTotalVeckor(): number {
    return this.topWeeks.length;
  }

  get kpiRekordIBC(): number {
    if (!this.topWeeks.length) return 0;
    return this.topWeeks[0]?.ibc_total ?? 0;
  }

  get kpiSnittIBC(): number {
    if (!this.topWeeks.length) return 0;
    const sum = this.topWeeks.reduce((acc, w) => acc + w.ibc_total, 0);
    return Math.round(sum / this.topWeeks.length);
  }

  get kpiBastaOEE(): number {
    if (!this.topWeeks.length) return 0;
    const max = Math.max(...this.topWeeks.map(w => w.avg_oee ?? 0));
    return Math.round(max * 10) / 10;
  }

  get currentUserPersonalBest(): PersonalBestOperator | null {
    if (!this.currentUserName || !this.personalBests) return null;
    const name = this.currentUserName.toLowerCase();
    return (
      this.personalBests.operators.find(
        (op) => op.namn.toLowerCase() === name
      ) ?? null
    );
  }

  medalEmoji(index: number): string {
    if (index === 0) return '🥇';
    if (index === 1) return '🥈';
    if (index === 2) return '🥉';
    return '';
  }

  exportBenchmarkCSV(): void {
    if (!this.topWeeks || this.topWeeks.length === 0) return;
    const headers = ['Plats', 'Vecka', 'IBC Totalt', 'IBC/dag', 'Kvalitet%', 'OEE%', 'Aktiva dagar'];
    const rows = this.topWeeks.map((w: BenchmarkingTopWeek, i: number) => [
      i + 1,
      w.week_label ?? '',
      w.ibc_total ?? '',
      w.ibc_total && w.days_active ? (w.ibc_total / w.days_active).toFixed(1) : '',
      w.avg_quality != null ? w.avg_quality.toFixed(1) : '',
      w.avg_oee != null ? w.avg_oee.toFixed(1) : '',
      w.days_active ?? '',
    ]);
    const csv = [headers, ...rows].map((r) => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `benchmarking-topp10-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  private load(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.rebotlingService
      .getBenchmarking()
      .pipe(
        timeout(10_000),
        catchError(() => of({ success: false } as BenchmarkingResponse)),
        takeUntil(this.destroy$)
      )
      .subscribe((res) => {
        this.isFetching = false;
        if (!res.success) {
          this.error = 'Kunde inte hämta benchmarking-data.';
          this.loading = false;
          return;
        }
        this.error = '';
        this.currentWeek = res.current_week ?? null;
        this.bestWeekEver = res.best_week_ever ?? null;
        this.bestDayEver = res.best_day_ever ?? null;
        this.topWeeks = res.top_weeks ?? [];
        this.monthlyTotals = res.monthly_totals ?? [];

        this.currentWeekLabel = this.currentWeek?.week_label ?? '';
        this.bestWeekLabel = this.bestWeekEver?.week_label ?? '';

        if (this.currentWeek && this.bestWeekEver) {
          const cur = this.currentWeek.ibc_total;
          const best = this.bestWeekEver.ibc_total;
          this.ibcDiffToRecord = best - cur;
          this.isNewRecord = cur > best;
          this.progressPct = best > 0 ? Math.min(100, Math.round((cur / best) * 100)) : 0;
        }

        this.loading = false;
        if (this.chartTimer !== null) {
          clearTimeout(this.chartTimer);
        }
        this.chartTimer = setTimeout(() => {
          if (!this.destroy$.closed) this.buildMonthlyChart();
        }, 50);
      });
  }

  loadPersonalBests(): void {
    this.personalBestsLoading = true;
    this.rebotlingService.getPersonalBests()
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe((res: PersonalBestsResponse | null) => {
        this.personalBestsLoading = false;
        if (res?.success && res.data) this.personalBests = res.data;
      });
  }

  loadMonthlyLeaders(): void {
    this.monthlyLeadersLoading = true;
    this.rebotlingService.getMonthlyLeaders()
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe((res: MonthlyLeadersResponse | null) => {
        this.monthlyLeadersLoading = false;
        if (res?.success && res.data) this.monthlyLeaders = res.data;
      });
  }

  loadHallOfFame(): void {
    this.hallOfFameLoading = true;
    this.rebotlingService.getHallOfFameDays()
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe((res: HallOfFameDaysResponse | null) => {
        this.hallOfFameLoading = false;
        if (res?.success && res.data) this.hallOfFame = res.data;
      });
  }

  hofMedalClass(rank: number): string {
    if (rank === 1) return 'rank-gold';
    if (rank === 2) return 'rank-silver';
    if (rank === 3) return 'rank-bronze';
    return '';
  }

  hofMedalIcon(rank: number): string {
    if (rank === 1) return 'fas fa-trophy';
    if (rank === 2) return 'fas fa-medal';
    if (rank === 3) return 'fas fa-award';
    return '';
  }

  setTab(tab: 'overview' | 'personbasta' | 'halloffame'): void {
    this.activeTab = tab;
  }

  private buildMonthlyChart(): void {
    if (!this.monthlyChartRef) return;

    try { this.monthlyChartInstance?.destroy(); } catch (e) {}
    this.monthlyChartInstance = null;

    const months = this.monthlyTotals;
    if (!months.length) return;

    const labels = months.map((m) => this.formatMonth(m.month));
    const values = months.map((m) => m.ibc_total);

    const avg = values.length > 0 ? Math.round(values.reduce((a, b) => a + b, 0) / values.length) : 0;

    const now = new Date();
    const curMonthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    const maxVal = Math.max(...values);
    const maxIdx = values.indexOf(maxVal);
    const curIdx = months.findIndex((m) => m.month === curMonthStr);

    const bgColors = values.map((v, i) => {
      if (i === curIdx) return 'rgba(66, 153, 225, 0.85)';
      if (i === maxIdx) return 'rgba(246, 224, 94, 0.9)';
      return 'rgba(99, 179, 237, 0.45)';
    });

    const borderColors = values.map((v, i) => {
      if (i === curIdx) return 'rgba(66, 153, 225, 1)';
      if (i === maxIdx) return 'rgba(214, 158, 46, 1)';
      return 'rgba(99, 179, 237, 0.7)';
    });

    const ctx = this.monthlyChartRef.nativeElement.getContext('2d')!;

    this.monthlyChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC per månad',
            data: values,
            backgroundColor: bgColors,
            borderColor: borderColors,
            borderWidth: 2,
            borderRadius: 4,
          },
          {
            label: `Snitt (${avg} IBC)`,
            data: Array(values.length).fill(avg),
            type: 'line',
            borderColor: 'rgba(252, 129, 74, 0.9)',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              afterBody: (items) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && months[idx]) {
                  return [`Kvalitet: ${months[idx].avg_quality}%`];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
          },
        },
      },
    });
  }

  formatMonth(m: string): string {
    const parts = m.split('-');
    if (parts.length < 2) return m;
    const monthNames = [
      'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun',
      'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec',
    ];
    const idx = parseInt(parts[1], 10) - 1;
    return `${monthNames[idx] ?? parts[1]} ${parts[0].slice(2)}`;
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = parseLocalDate(dateStr);
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  rankLabel(i: number): string {
    if (i === 0) return '1';
    if (i === 1) return '2';
    if (i === 2) return '3';
    return String(i + 1);
  }

  recordDiffPct(ibc: number): number {
    if (!this.bestWeekEver || this.bestWeekEver.ibc_total === 0) return 0;
    return Math.round((ibc / this.bestWeekEver.ibc_total) * 100);
  }
}
