import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, interval, of } from 'rxjs';
import { takeUntil, catchError, timeout, startWith } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, ProductionGoalProgressResponse } from '../../../../services/rebotling.service';
import { AuthService } from '../../../../services/auth.service';

interface GoalData {
  target: number;
  actual: number;
  percentage: number;
  remaining: number;
  time_remaining_seconds: number;
  streak: number;
  period_label: string;
}

@Component({
  standalone: true,
  selector: 'app-statistik-produktionsmal',
  templateUrl: './statistik-produktionsmal.html',
  styleUrls: ['./statistik-produktionsmal.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikProduktionsmalComponent implements OnInit, AfterViewInit, OnDestroy {
  todayData: GoalData | null = null;
  weekData: GoalData | null = null;
  loadingToday = false;
  loadingWeek  = false;
  error: string | null = null;

  isAdmin = false;
  editingDaily  = false;
  editingWeekly = false;
  newDailyTarget  = 200;
  newWeeklyTarget = 1000;
  saving = false;
  saveMsg: string | null = null;

  private destroy$   = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  private dayChart:  Chart | null = null;
  private weekChart: Chart | null = null;

  constructor(
    private rebotlingService: RebotlingService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    // Kontrollera admin-status
    this.authService.user$.pipe(
      takeUntil(this.destroy$)
    ).subscribe((user) => {
      this.isAdmin = user?.role === 'admin';
    });

    // Auto-refresh var 60:e sekund
    interval(60000).pipe(
      startWith(0),
      takeUntil(this.destroy$)
    ).subscribe(() => {
      this.loadToday();
      this.loadWeek();
    });
  }

  ngAfterViewInit(): void {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    try { this.dayChart?.destroy(); }  catch (e) {}
    try { this.weekChart?.destroy(); } catch (e) {}
    this.dayChart  = null;
    this.weekChart = null;
  }

  loadToday(): void {
    this.loadingToday = true;
    this.rebotlingService.getProductionGoalProgress('today').pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res: ProductionGoalProgressResponse | null) => {
      this.loadingToday = false;
      if (res?.success) {
        this.todayData = {
          target:                res.target ?? 0,
          actual:                res.actual ?? 0,
          percentage:            res.percentage ?? 0,
          remaining:             res.remaining ?? 0,
          time_remaining_seconds: res.time_remaining_seconds ?? 0,
          streak:                res.streak ?? 0,
          period_label:          res.period_label ?? 'Idag',
        };
        this.newDailyTarget = this.todayData.target;
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderChart('day'); }, 60));
      } else {
        this.error = res?.error ?? 'Kunde inte hämta dagsmål';
      }
    });
  }

  loadWeek(): void {
    this.loadingWeek = true;
    this.rebotlingService.getProductionGoalProgress('week').pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res: ProductionGoalProgressResponse | null) => {
      this.loadingWeek = false;
      if (res?.success) {
        this.weekData = {
          target:                res.target ?? 0,
          actual:                res.actual ?? 0,
          percentage:            res.percentage ?? 0,
          remaining:             res.remaining ?? 0,
          time_remaining_seconds: res.time_remaining_seconds ?? 0,
          streak:                res.streak ?? 0,
          period_label:          res.period_label ?? 'Denna vecka',
        };
        this.newWeeklyTarget = this.weekData.target;
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderChart('week'); }, 60));
      } else if (!this.error) {
        this.error = res?.error ?? 'Kunde inte hämta veckamål';
      }
    });
  }

  private renderChart(type: 'day' | 'week'): void {
    const canvasId = type === 'day' ? 'malDayCanvas' : 'malWeekCanvas';
    const canvas = document.getElementById(canvasId) as HTMLCanvasElement | null;
    if (!canvas) return;

    const data = type === 'day' ? this.todayData : this.weekData;
    if (!data) return;

    const pct  = Math.min(data.percentage, 100);
    const rest = Math.max(100 - pct, 0);
    const color = this.getProgressColor(pct);

    if (type === 'day') {
      try { this.dayChart?.destroy(); } catch (e) {}
      this.dayChart = null;
    } else {
      try { this.weekChart?.destroy(); } catch (e) {}
      this.weekChart = null;
    }

    const chart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [pct, rest],
          backgroundColor: [color, '#2d3748'],
          borderWidth: 0,
          circumference: 270,
          rotation: 225
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '78%',
        plugins: {
          legend:  { display: false },
          tooltip: { intersect: false, mode: 'nearest', enabled: false }
        }
      },
      plugins: [{
        id: `malCenter_${type}`,
        afterDraw: (ch: any) => {
          const { ctx, chartArea } = ch;
          if (!chartArea) return;
          const cx = (chartArea.left + chartArea.right)  / 2;
          const cy = (chartArea.top  + chartArea.bottom) / 2 + 10;
          ctx.save();
          ctx.font = 'bold 38px sans-serif';
          ctx.fillStyle = color;
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(`${Math.round(pct)}%`, cx, cy - 8);
          ctx.font = '13px sans-serif';
          ctx.fillStyle = '#a0aec0';
          ctx.fillText(`${data.actual} / ${data.target}`, cx, cy + 22);
          ctx.restore();
        }
      }]
    });

    if (type === 'day') {
      this.dayChart = chart;
    } else {
      this.weekChart = chart;
    }
  }

  getProgressColor(pct: number): string {
    if (pct >= 100) return '#48bb78'; // grön
    if (pct >= 75)  return '#ecc94b'; // gul
    if (pct >= 50)  return '#ed8936'; // orange
    return '#fc8181';                  // röd
  }

  formatTimeRemaining(secs: number): string {
    if (secs <= 0) return 'Perioden avslutad';
    const h   = Math.floor(secs / 3600);
    const m   = Math.floor((secs % 3600) / 60);
    if (h > 0) return `${h} tim ${m} min kvar`;
    return `${m} min kvar`;
  }

  getStreakLabel(streak: number, type: 'day' | 'week'): string {
    if (streak === 0) return '';
    const unit = type === 'day' ? 'dagar' : 'veckor';
    return `${streak} ${unit} i rad!`;
  }

  // ---- Admin: redigera mål ----

  startEditDaily(): void {
    this.editingDaily  = true;
    this.editingWeekly = false;
    this.saveMsg = null;
  }

  startEditWeekly(): void {
    this.editingWeekly = true;
    this.editingDaily  = false;
    this.saveMsg = null;
  }

  cancelEdit(): void {
    this.editingDaily  = false;
    this.editingWeekly = false;
    this.saveMsg = null;
  }

  saveGoal(type: 'daily' | 'weekly'): void {
    if (this.saving) return;
    const target = type === 'daily' ? this.newDailyTarget : this.newWeeklyTarget;
    if (!target || target <= 0) return;

    this.saving = true;
    this.saveMsg = null;
    this.rebotlingService.setProductionGoal(type, target).pipe(
      timeout(10000),
      catchError(() => of({ success: false, error: 'Nätverksfel' })),
      takeUntil(this.destroy$)
    ).subscribe((res: any) => {
      this.saving = false;
      if (res?.success) {
        this.saveMsg = res.message ?? 'Sparat!';
        this.editingDaily  = false;
        this.editingWeekly = false;
        // Ladda om data
        this.loadToday();
        this.loadWeek();
      } else {
        this.saveMsg = res?.error ?? 'Kunde inte spara';
      }
    });
  }
}
