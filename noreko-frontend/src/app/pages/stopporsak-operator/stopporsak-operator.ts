import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  StopporsakOperatorService,
  OperatorRow,
  OverviewData,
  OperatorDetailData,
  OrsakDetail,
  ReasonsSummaryData,
} from '../../services/stopporsak-operator.service';

Chart.register(...registerables);

interface PeriodOption { val: number; label: string; }

@Component({
  standalone: true,
  selector: 'app-stopporsak-operator',
  templateUrl: './stopporsak-operator.html',
  styleUrl: './stopporsak-operator.css',
  imports: [CommonModule, FormsModule],
})
export class StopporsakOperatorPage implements OnInit, OnDestroy {
  Math = Math;

  // ---- Period ----
  period = 30;
  readonly periodOptions: PeriodOption[] = [
    { val: 7,  label: '7 dagar'  },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  // ---- Laddning ----
  loadingOverview = false;
  loadingDetail   = false;
  loadingReasons  = false;

  // ---- Fel ----
  errorOverview = false;
  errorDetail   = false;
  errorReasons  = false;

  // ---- Data ----
  overview: OverviewData | null           = null;
  operatorer: OperatorRow[]               = [];
  selectedOperator: OperatorRow | null    = null;
  detailData: OperatorDetailData | null   = null;
  reasonsSummary: ReasonsSummaryData | null = null;

  // ---- Charts ----
  private barChart: Chart | null    = null;
  private donutChart: Chart | null  = null;
  private detailChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private chartTimer: ReturnType<typeof setTimeout> | null = null;

  private readonly COLORS = [
    '#4299e1', '#48bb78', '#ecc94b', '#ed8936', '#e53e3e',
    '#9f7aea', '#38b2ac', '#f687b3', '#68d391', '#fc8181',
    '#667eea', '#f6ad55', '#76e4f7', '#b794f4', '#fbb6ce',
  ];

  constructor(private svc: StopporsakOperatorService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyAllCharts();
  }

  private destroyAllCharts(): void {
    try { this.barChart?.destroy(); }    catch (_) {}
    try { this.donutChart?.destroy(); }  catch (_) {}
    try { this.detailChart?.destroy(); } catch (_) {}
    this.barChart = this.donutChart = this.detailChart = null;
  }

  // ================================================================
  // Period
  // ================================================================

  onPeriodChange(p: number): void {
    this.period = p;
    this.selectedOperator = null;
    this.detailData = null;
    this.loadAll();
  }

  // ================================================================
  // Data loading
  // ================================================================

  loadAll(): void {
    this.loadOverview();
    this.loadReasonsSummary();
  }

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview   = false;
    this.destroyBarChart();

    this.svc.getOverview(this.period)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) {
          this.overview    = res.data;
          this.operatorer  = res.data.operatorer ?? [];
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) { this.buildBarChart(); }
          }, 0);
        } else {
          this.errorOverview = true;
          this.overview      = null;
          this.operatorer    = [];
        }
      });
  }

  loadReasonsSummary(): void {
    this.loadingReasons = true;
    this.errorReasons   = false;
    this.destroyDonutChart();

    this.svc.getReasonsSummary(this.period)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingReasons = false;
        if (res?.success) {
          this.reasonsSummary = res.data;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) { this.buildDonutChart(); }
          }, 0);
        } else {
          this.errorReasons   = true;
          this.reasonsSummary = null;
        }
      });
  }

  selectOperator(op: OperatorRow): void {
    if (this.selectedOperator?.user_id === op.user_id) {
      // Avmarkera — stäng detaljvy
      this.selectedOperator = null;
      this.detailData       = null;
      this.destroyDetailChart();
      return;
    }

    this.selectedOperator = op;
    this.detailData       = null;
    this.loadingDetail    = true;
    this.errorDetail      = false;
    this.destroyDetailChart();

    this.svc.getOperatorDetail(op.user_id, this.period)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDetail = false;
        if (res?.success) {
          this.detailData = res.data;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) { this.buildDetailChart(); }
          }, 0);
        } else {
          this.errorDetail = true;
          this.detailData  = null;
        }
      });
  }

  closeDetail(): void {
    this.selectedOperator = null;
    this.detailData       = null;
    this.destroyDetailChart();
  }

  // ================================================================
  // Chart.js — Horisontell stapel: stopptid per operatör
  // ================================================================

  private destroyBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;
  }

  private buildBarChart(): void {
    this.destroyBarChart();
    if (!this.operatorer.length) return;

    const canvas = document.getElementById('operatorBarChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    const labels = this.operatorer.map(o => o.namn);
    const values = this.operatorer.map(o => o.total_min);
    const snitt  = this.overview?.team_snitt_min ?? 0;
    const colors = this.operatorer.map(o =>
      o.hog_stopptid ? '#fc8181' : '#4299e1'
    );

    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Stopptid (min)',
            data: values,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 1,
            borderRadius: 4,
          },
          {
            label: 'Teamsnitt',
            data: labels.map(() => snitt),
            type: 'line' as any,
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 0,
            fill: false,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const val = ctx.parsed.x as number;
                return ` ${val.toFixed(1)} min`;
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
              text: 'Stopptid (minuter)',
              color: '#a0aec0',
            },
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 11 } },
            grid:  { color: '#374151' },
          },
        },
      },
    });
  }

  // ================================================================
  // Chart.js — Donut: stopporsaker totalt
  // ================================================================

  private destroyDonutChart(): void {
    try { this.donutChart?.destroy(); } catch (_) {}
    this.donutChart = null;
  }

  private buildDonutChart(): void {
    this.destroyDonutChart();
    if (!this.reasonsSummary?.orsaker.length) return;

    const canvas = document.getElementById('reasonsDonutChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    const top = this.reasonsSummary.orsaker.slice(0, 10);

    if (this.donutChart) { (this.donutChart as any).destroy(); }
    this.donutChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: top.map(o => o.orsak),
        datasets: [{
          data: top.map(o => o.total_min),
          backgroundColor: this.COLORS.slice(0, top.length),
          borderColor: '#2d3748',
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#a0aec0', font: { size: 11 }, padding: 12 },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const val = ctx.parsed as number;
                return ` ${val.toFixed(1)} min (${top[ctx.dataIndex]?.andel_pct ?? 0}%)`;
              },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // Chart.js — Donut: valda operatörens orsaker
  // ================================================================

  private destroyDetailChart(): void {
    try { this.detailChart?.destroy(); } catch (_) {}
    this.detailChart = null;
  }

  private buildDetailChart(): void {
    this.destroyDetailChart();
    if (!this.detailData?.orsaker.length) return;

    const canvas = document.getElementById('detailDonutChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    const orsaker = this.detailData.orsaker.slice(0, 10);

    if (this.detailChart) { (this.detailChart as any).destroy(); }
    this.detailChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: orsaker.map(o => o.orsak),
        datasets: [{
          data: orsaker.map(o => o.total_min > 0 ? o.total_min : o.antal),
          backgroundColor: this.COLORS.slice(0, orsaker.length),
          borderColor: '#2d3748',
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#a0aec0', font: { size: 11 }, padding: 10 },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const o = orsaker[ctx.dataIndex];
                return o ? ` ${o.antal} stopp — ${o.total_min.toFixed(1)} min` : '';
              },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // Template helpers
  // ================================================================

  get harOperatorer(): boolean { return this.operatorer.length > 0; }
  get harReasons(): boolean    { return (this.reasonsSummary?.orsaker?.length ?? 0) > 0; }
  get harDetail(): boolean     { return (this.detailData?.orsaker?.length ?? 0) > 0; }
  get hogAntalOperatorer(): number { return this.operatorer.filter(o => o.hog_stopptid).length; }

  formatMinuter(min: number): string {
    if (min <= 0) return '0 min';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}min` : `${m} min`;
  }

  orsakFarg(index: number): string {
    return this.COLORS[index % this.COLORS.length];
  }

  /** Andel av totalt stopptid för detaljvyn */
  orsakAndel(orsak: OrsakDetail): number {
    const tot = this.detailData?.total_min ?? 0;
    return tot > 0 ? Math.round((orsak.total_min / tot) * 100) : 0;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
