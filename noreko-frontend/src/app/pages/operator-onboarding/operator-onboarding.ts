import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  OperatorOnboardingService,
  OnboardingOperator,
  OverviewData,
  OperatorCurveData,
} from '../../services/operator-onboarding.service';

Chart.register(...registerables);

interface MonthOption { val: number; label: string; }

@Component({
  standalone: true,
  selector: 'app-operator-onboarding',
  templateUrl: './operator-onboarding.html',
  styleUrl: './operator-onboarding.css',
  imports: [CommonModule, FormsModule],
})
export class OperatorOnboardingPage implements OnInit, OnDestroy {
  Math = Math;

  // ---- Period ----
  months = 6;
  readonly monthOptions: MonthOption[] = [
    { val: 3,  label: '3 manader'  },
    { val: 6,  label: '6 manader'  },
    { val: 12, label: '12 manader' },
  ];

  // ---- Laddning ----
  loadingOverview = false;
  loadingCurve    = false;

  // ---- Fel ----
  errorOverview = false;
  errorCurve    = false;

  // ---- Data ----
  overview: OverviewData | null              = null;
  operatorer: OnboardingOperator[]           = [];
  selectedOperator: OnboardingOperator | null = null;
  curveData: OperatorCurveData | null        = null;

  // ---- Charts ----
  private curveChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private svc: OperatorOnboardingService) {}

  ngOnInit(): void {
    this.loadOverview();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    this.destroyCurveChart();
  }

  // ================================================================
  // Period
  // ================================================================

  onMonthsChange(m: number): void {
    this.months = m;
    this.selectedOperator = null;
    this.curveData = null;
    this.destroyCurveChart();
    this.loadOverview();
  }

  // ================================================================
  // Data loading
  // ================================================================

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview   = false;

    this.svc.getOverview(this.months)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) {
          this.overview   = res.data;
          this.operatorer = res.data.operatorer ?? [];
        } else {
          this.errorOverview = true;
          this.overview      = null;
          this.operatorer    = [];
        }
      });
  }

  selectOperator(op: OnboardingOperator): void {
    if (this.selectedOperator?.operator_number === op.operator_number) {
      this.selectedOperator = null;
      this.curveData = null;
      this.destroyCurveChart();
      return;
    }

    this.selectedOperator = op;
    this.curveData        = null;
    this.loadingCurve     = true;
    this.errorCurve       = false;
    this.destroyCurveChart();

    this.svc.getOperatorCurve(op.operator_number)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingCurve = false;
        if (res?.success) {
          this.curveData = res.data;
          this._timers.push(setTimeout(() => {
            if (!this.destroy$.closed) { this.buildCurveChart(); }
          }, 0));
        } else {
          this.errorCurve = true;
          this.curveData  = null;
        }
      });
  }

  closeDetail(): void {
    this.selectedOperator = null;
    this.curveData = null;
    this.destroyCurveChart();
  }

  // ================================================================
  // Chart.js — Linjediagram: lärlingskurva
  // ================================================================

  private destroyCurveChart(): void {
    try { this.curveChart?.destroy(); } catch (_) {}
    this.curveChart = null;
  }

  private buildCurveChart(): void {
    this.destroyCurveChart();
    if (!this.curveData?.weeks?.length) return;

    const canvas = document.getElementById('onboardingCurveChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    const weeks = this.curveData.weeks;
    const labels = Array.from({ length: 12 }, (_, i) => `V${i + 1}`);
    const dataPoints: (number | null)[] = new Array(12).fill(null);

    for (const w of weeks) {
      if (w.week >= 1 && w.week <= 12) {
        dataPoints[w.week - 1] = w.ibc_h;
      }
    }

    const teamSnitt = this.curveData.team_snitt_ibc_h;

    if (this.curveChart) { (this.curveChart as any).destroy(); }
    this.curveChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: `${this.curveData.operator_namn} (IBC/h)`,
            data: dataPoints,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66, 153, 225, 0.1)',
            borderWidth: 3,
            pointRadius: 5,
            pointBackgroundColor: '#4299e1',
            fill: true,
            tension: 0.3,
            spanGaps: true,
          },
          {
            label: `Teamsnitt (${teamSnitt} IBC/h)`,
            data: new Array(12).fill(teamSnitt),
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [6, 3],
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
            labels: { color: '#a0aec0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const val = ctx.parsed.y as number;
                return val !== null ? ` ${val.toFixed(1)} IBC/h` : ' Ingen data';
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid:  { color: '#374151' },
            title: {
              display: true,
              text: 'Vecka sedan start',
              color: '#a0aec0',
            },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid:  { color: '#374151' },
            title: {
              display: true,
              text: 'IBC/h',
              color: '#a0aec0',
            },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ================================================================
  // Template helpers
  // ================================================================

  get harOperatorer(): boolean { return this.operatorer.length > 0; }
  get nyaOperatorer(): OnboardingOperator[] { return this.operatorer.filter(o => o.is_ny); }
  get harCurve(): boolean { return (this.curveData?.weeks?.length ?? 0) > 0; }

  statusBadgeClass(status: string): string {
    switch (status) {
      case 'gron': return 'badge-gron';
      case 'gul':  return 'badge-gul';
      case 'rod':  return 'badge-rod';
      default:     return 'badge-rod';
    }
  }

  statusLabel(status: string): string {
    switch (status) {
      case 'gron': return 'På mål';
      case 'gul':  return 'Närmar sig';
      case 'rod':  return 'Behöver stöd';
      default:     return 'Okänd';
    }
  }

  statusIcon(status: string): string {
    switch (status) {
      case 'gron': return 'fa-check-circle';
      case 'gul':  return 'fa-exclamation-circle';
      case 'rod':  return 'fa-times-circle';
      default:     return 'fa-question-circle';
    }
  }

  pctBarColor(pct: number): string {
    if (pct >= 90) return '#48bb78';
    if (pct >= 70) return '#ecc94b';
    return '#fc8181';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
