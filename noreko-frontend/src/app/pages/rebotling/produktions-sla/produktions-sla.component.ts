import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  ProduktionsSlaService,
  SlaOverview,
  DailyProgress,
  WeeklyProgress,
  HistoryData,
  SlaGoal,
  SetGoalData,
} from '../../../services/produktions-sla.service';
import { localToday } from '../../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-produktions-sla',
  templateUrl: './produktions-sla.component.html',
  styleUrls: ['./produktions-sla.component.css'],
  imports: [CommonModule, FormsModule],
})
export class ProduktionsSlaPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview = false;
  loadingDaily = false;
  loadingWeekly = false;
  loadingHistory = false;
  loadingGoals = false;

  // Error states
  errorData = false;

  // Data
  overview: SlaOverview | null = null;
  daily: DailyProgress | null = null;
  weekly: WeeklyProgress | null = null;
  historyData: HistoryData | null = null;
  goals: SlaGoal[] = [];

  // Period
  historyPeriod: number = 30;

  // Goal form
  showGoalForm = false;
  goalForm: SetGoalData = {
    mal_typ: 'dagligt',
    target_ibc: 80,
    target_kassation_pct: 5,
    giltig_from: localToday(),
  };
  savingGoal = false;
  goalMessage = '';
  goalError = '';

  // Charts
  private gaugeChart: Chart | null = null;
  private weeklyChart: Chart | null = null;
  private historyChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private gaugeChartTimer: ReturnType<typeof setTimeout> | null = null;
  private weeklyChartTimer: ReturnType<typeof setTimeout> | null = null;
  private historyChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: ProduktionsSlaService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.gaugeChartTimer !== null) { clearTimeout(this.gaugeChartTimer); this.gaugeChartTimer = null; }
    if (this.weeklyChartTimer !== null) { clearTimeout(this.weeklyChartTimer); this.weeklyChartTimer = null; }
    if (this.historyChartTimer !== null) { clearTimeout(this.historyChartTimer); this.historyChartTimer = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    if (this.gaugeChart) { this.gaugeChart.destroy(); this.gaugeChart = null; }
    if (this.weeklyChart) { this.weeklyChart.destroy(); this.weeklyChart = null; }
    if (this.historyChart) { this.historyChart.destroy(); this.historyChart = null; }
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadOverview();
    this.loadDailyProgress();
    this.loadWeeklyProgress();
    this.loadHistory();
  }

  // ---- Overview ----

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview().pipe(timeout(15000), catchError(() => { this.errorData = true; return of(null); }), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) { this.overview = res.data; }
        else if (res !== null) { this.errorData = true; }
    });
  }

  // ---- Daily Progress ----

  loadDailyProgress(): void {
    this.loadingDaily = true;
    this.svc.getDailyProgress().pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingDaily = false;
        if (res?.success) {
          this.daily = res.data;
          if (this.gaugeChartTimer !== null) { clearTimeout(this.gaugeChartTimer); }
          this.gaugeChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderGaugeChart(); }, 100);
        }
    });
  }

  // ---- Weekly Progress ----

  loadWeeklyProgress(): void {
    this.loadingWeekly = true;
    this.svc.getWeeklyProgress().pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingWeekly = false;
        if (res?.success) {
          this.weekly = res.data;
          if (this.weeklyChartTimer !== null) { clearTimeout(this.weeklyChartTimer); }
          this.weeklyChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderWeeklyChart(); }, 100);
        }
    });
  }

  // ---- History ----

  loadHistory(): void {
    this.loadingHistory = true;
    this.svc.getHistory(this.historyPeriod).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingHistory = false;
        if (res?.success) {
          this.historyData = res.data;
          if (this.historyChartTimer !== null) { clearTimeout(this.historyChartTimer); }
          this.historyChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderHistoryChart(); }, 100);
        }
    });
  }

  onPeriodChange(): void {
    this.loadHistory();
  }

  // ---- Goals ----

  loadGoals(): void {
    this.loadingGoals = true;
    this.svc.getGoals().pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.loadingGoals = false;
        if (res?.success) {
          this.goals = res.goals;
        }
    });
  }

  toggleGoalForm(): void {
    this.showGoalForm = !this.showGoalForm;
    if (this.showGoalForm && this.goals.length === 0) {
      this.loadGoals();
    }
    this.goalMessage = '';
    this.goalError = '';
  }

  submitGoal(): void {
    if (this.goalForm.target_ibc < 1) {
      this.goalError = 'IBC-mal maste vara minst 1';
      return;
    }
    this.savingGoal = true;
    this.goalError = '';
    this.goalMessage = '';
    this.svc.setGoal(this.goalForm).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
        this.savingGoal = false;
        if (res?.success) {
          this.goalMessage = 'Mal sparat!';
          this.loadGoals();
          this.loadAll();
        } else {
          this.goalError = res?.error || 'Kunde inte spara mal';
        }
    });
  }

  // ---- Charts ----

  renderGaugeChart(): void {
    if (this.gaugeChart) { this.gaugeChart.destroy(); this.gaugeChart = null; }
    const canvas = document.getElementById('slaGaugeChart') as HTMLCanvasElement | null;
    if (!canvas || !this.daily) return;

    const pct = Math.min(this.daily.uppfyllnad_pct, 150);
    const remaining = Math.max(0, 100 - pct);
    const color = pct >= 100 ? '#48bb78' : pct >= 80 ? '#ecc94b' : '#e53e3e';

    this.gaugeChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Uppfyllt', 'Kvar'],
        datasets: [{
          data: [Math.min(pct, 100), remaining],
          backgroundColor: [color, '#4a5568'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        circumference: 180,
        rotation: -90,
        cutout: '75%',
        plugins: {
          legend: { display: false },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx) => `${ctx.label}: ${ctx.parsed}%`,
            }
          }
        },
      },
    });
  }

  renderWeeklyChart(): void {
    if (this.weeklyChart) { this.weeklyChart.destroy(); this.weeklyChart = null; }
    const canvas = document.getElementById('slaWeeklyChart') as HTMLCanvasElement | null;
    if (!canvas || !this.weekly) return;

    const days = this.weekly.days;
    const labels = days.map(d => d.dag_namn);
    const values = days.map(d => d.ibc_ok);
    const target = this.weekly.dagligt_target;
    const colors = days.map(d => d.over_mal ? '#48bb78' : '#e53e3e');

    this.weeklyChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Producerat (IBC)',
            data: values,
            backgroundColor: colors,
            borderRadius: 4,
          },
          {
            label: 'Dagligt mal',
            data: Array(7).fill(target),
            type: 'line' as any,
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [5, 5],
            pointRadius: 0,
            fill: false,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          tooltip: { intersect: false, mode: 'nearest' },
          legend: { labels: { color: '#e2e8f0' } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  renderHistoryChart(): void {
    if (this.historyChart) { this.historyChart.destroy(); this.historyChart = null; }
    const canvas = document.getElementById('slaHistoryChart') as HTMLCanvasElement | null;
    if (!canvas || !this.historyData) return;

    const hist = this.historyData.history;
    const labels = hist.map(h => h.date.substring(5)); // MM-DD
    const values = hist.map(h => h.uppfyllnad_pct);

    // Simple moving average (7 day)
    const trendLine: (number | null)[] = [];
    for (let i = 0; i < values.length; i++) {
      if (i < 6) { trendLine.push(null); continue; }
      let sum = 0;
      for (let j = i - 6; j <= i; j++) sum += values[j];
      trendLine.push(Math.round(sum / 7 * 10) / 10);
    }

    this.historyChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Uppfyllnad %',
            data: values,
            borderColor: '#4299e1',
            backgroundColor: '#4299e133',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
          },
          {
            label: '7-dagars snitt',
            data: trendLine,
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [5, 5],
            pointRadius: 0,
            fill: false,
            tension: 0.4,
          },
          {
            label: '100% mal',
            data: Array(labels.length).fill(100),
            borderColor: '#48bb7866',
            borderWidth: 1,
            borderDash: [3, 3],
            pointRadius: 0,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          tooltip: { intersect: false, mode: 'nearest' },
          legend: { labels: { color: '#e2e8f0' } },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 15 },
            grid: { color: '#4a556833' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', callback: (v: any) => v + '%' },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  progressBarClass(pct: number): string {
    if (pct >= 100) return 'bg-success';
    if (pct >= 80) return 'bg-warning';
    return 'bg-danger';
  }

  progressBarWidth(pct: number): number {
    return Math.min(pct, 100);
  }

  trendIcon(trend: string): string {
    switch (trend) {
      case 'uppat': return 'fas fa-arrow-up text-success';
      case 'nedat': return 'fas fa-arrow-down text-danger';
      default: return 'fas fa-minus text-muted';
    }
  }

  trendLabel(trend: string): string {
    switch (trend) {
      case 'uppat': return 'Uppåt';
      case 'nedat': return 'Nedåt';
      default: return 'Stabil';
    }
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
