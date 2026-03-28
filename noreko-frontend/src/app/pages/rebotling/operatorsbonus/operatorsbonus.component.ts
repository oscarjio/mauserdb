import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  OperatorsbonusService,
  BonusOverviewData,
  OperatorBonus,
  PerOperatorData,
  KonfigItem,
  SimuleringData,
  TrendDagItem,
} from '../../../services/operatorsbonus.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-operatorsbonus',
  templateUrl: './operatorsbonus.component.html',
  styleUrls: ['./operatorsbonus.component.css'],
  imports: [CommonModule, FormsModule],
})
export class OperatorsbonusPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview    = false;
  loadingOperatorer  = false;
  loadingKonfig      = false;
  loadingSimulering  = false;

  // Error states
  errorData = false;
  errorOperatorer = false;
  errorKonfig = false;
  errorSimulering = false;

  // Data
  overview:    BonusOverviewData | null = null;
  perOpData:   PerOperatorData | null   = null;
  operatorer:  OperatorBonus[]          = [];
  konfigItems: KonfigItem[]             = [];
  maxTotal     = 1200;
  simulering:  SimuleringData | null    = null;

  // Filter / state
  period          = 'dag';
  sortColumn      = 'total_bonus';
  sortAsc         = false;
  showKonfig      = false;
  savingKonfig    = false;
  konfigMessage   = '';
  konfigError     = '';
  konfigForm: { faktor: string; vikt: number; mal_varde: number; max_bonus_kr: number }[] = [];

  // Simulator inputs
  simIbcPerTimme  = 12;
  simKvalitet     = 98;
  simNarvaro      = 100;
  simTeamMal      = 95;

  // Selected operator for radar
  selectedOperator: OperatorBonus | null = null;

  // Drilldown (session #378)
  expandedOperatorId: number | null = null;
  drilldownLoading = false;
  drilldownData: any = null;

  // Trendgraf (session #379)
  trendDays = 30;
  trendData: TrendDagItem[] = [];
  trendOperatorNamn = '';
  trendSnittBonus = 0;
  loadingTrend = false;

  // Charts
  private radarChart: Chart | null     = null;
  private barChart: Chart | null       = null;
  private simChart: Chart | null       = null;
  private trendChart: Chart | null     = null;

  private destroy$         = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private operatorerChartTimer: ReturnType<typeof setTimeout> | null = null;
  private radarChartTimer: ReturnType<typeof setTimeout> | null = null;
  private simChartTimer: ReturnType<typeof setTimeout> | null = null;
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: OperatorsbonusService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadOverview(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.operatorerChartTimer !== null) { clearTimeout(this.operatorerChartTimer); this.operatorerChartTimer = null; }
    if (this.radarChartTimer !== null) { clearTimeout(this.radarChartTimer); this.radarChartTimer = null; }
    if (this.simChartTimer !== null) { clearTimeout(this.simChartTimer); this.simChartTimer = null; }
    if (this.trendChartTimer !== null) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    if (this.radarChart) { this.radarChart.destroy(); this.radarChart = null; }
    if (this.barChart)   { this.barChart.destroy();   this.barChart = null; }
    if (this.simChart)   { this.simChart.destroy();   this.simChart = null; }
    if (this.trendChart) { this.trendChart.destroy();  this.trendChart = null; }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadOperatorer();
  }

  // ---- Overview ----

  loadOverview(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview(this.period).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) { this.overview = res.data; }
        else { this.errorData = true; }
      },
      error: () => { this.loadingOverview = false; this.isFetching = false; this.errorData = true; }
    });
  }

  // ---- Per Operator ----

  loadOperatorer(): void {
    this.loadingOperatorer = true;
    this.errorOperatorer = false;
    this.svc.getPerOperator(this.period).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingOperatorer = false;
        if (res?.success) {
          this.perOpData  = res.data;
          this.operatorer = res.data.operatorer;
          this.sortOperatorer();
          if (this.operatorer.length > 0 && !this.selectedOperator) {
            this.selectedOperator = this.operatorer[0];
          }
          if (this.operatorerChartTimer !== null) { clearTimeout(this.operatorerChartTimer); }
          this.operatorerChartTimer = setTimeout(() => {
            if (!this.destroy$.closed) {
              this.renderBarChart();
              this.renderRadarChart();
            }
          }, 150);
        } else {
          this.errorOperatorer = true;
        }
      },
      error: () => { this.loadingOperatorer = false; this.errorOperatorer = true; }
    });
  }

  onPeriodChange(): void {
    this.loadAll();
  }

  // ---- Sorting ----

  sortBy(col: string): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = false;
    }
    this.sortOperatorer();
  }

  private sortOperatorer(): void {
    const col = this.sortColumn;
    const dir = this.sortAsc ? 1 : -1;
    this.operatorer.sort((a: any, b: any) => {
      const av = a[col] ?? 0;
      const bv = b[col] ?? 0;
      if (typeof av === 'string') return av.localeCompare(bv) * dir;
      return (av - bv) * dir;
    });
  }

  sortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted';
    return this.sortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  // ---- Select operator for radar ----

  selectOperator(op: OperatorBonus): void {
    this.selectedOperator = op;
    if (this.radarChartTimer !== null) { clearTimeout(this.radarChartTimer); }
    this.radarChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderRadarChart(); }, 100);
    this.loadTrend();
  }

  // ---- Konfig ----

  toggleKonfig(): void {
    this.showKonfig = !this.showKonfig;
    if (this.showKonfig && this.konfigItems.length === 0) {
      this.loadKonfig();
    }
    this.konfigMessage = '';
    this.konfigError   = '';
  }

  loadKonfig(): void {
    this.loadingKonfig = true;
    this.errorKonfig = false;
    this.svc.getKonfiguration().pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingKonfig = false;
        if (res?.success) {
          this.konfigItems = res.konfig;
          this.maxTotal    = res.max_total;
          this.konfigForm  = res.konfig.map(k => ({
            faktor: k.faktor,
            vikt: k.vikt,
            mal_varde: k.mal_varde,
            max_bonus_kr: k.max_bonus_kr,
          }));
        } else {
          this.errorKonfig = true;
        }
      },
      error: () => { this.loadingKonfig = false; this.errorKonfig = true; }
    });
  }

  submitKonfig(): void {
    this.savingKonfig = true;
    this.konfigError  = '';
    this.konfigMessage = '';

    this.svc.sparaKonfiguration(this.konfigForm).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.savingKonfig = false;
        if (res?.success) {
          this.konfigMessage = 'Konfiguration sparad!';
          this.loadAll();
          this.loadKonfig();
        } else {
          this.konfigError = res?.error || 'Kunde inte spara konfiguration';
        }
      },
      error: () => { this.savingKonfig = false; this.konfigError = 'Kunde inte spara konfiguration'; }
    });
  }

  // ---- Simulator ----

  runSimulering(): void {
    this.loadingSimulering = true;
    this.errorSimulering = false;
    this.svc.getSimulering(this.simIbcPerTimme, this.simKvalitet, this.simNarvaro, this.simTeamMal)
      .pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
        next: res => {
          this.loadingSimulering = false;
          if (res?.success) {
            this.simulering = res.data;
            if (this.simChartTimer !== null) { clearTimeout(this.simChartTimer); }
            this.simChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderSimChart(); }, 100);
          } else {
            this.errorSimulering = true;
          }
        },
        error: () => { this.loadingSimulering = false; this.errorSimulering = true; }
      });
  }

  // ---- Drilldown (session #378) ----

  toggleDrilldown(op: OperatorBonus): void {
    if (this.expandedOperatorId === op.operator_id) {
      this.expandedOperatorId = null;
      this.drilldownData = null;
      return;
    }
    this.expandedOperatorId = op.operator_id;
    this.selectedOperator = op;
    this.drilldownData = null;
    this.drilldownLoading = true;
    // Build drilldown from existing operator data + historik
    this.svc.getHistorik(op.operator_id, undefined, undefined, 30)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.drilldownLoading = false;
        this.drilldownData = {
          operator: op,
          historik: res?.success ? res.data?.utbetalningar ?? [] : [],
        };
        // Refresh radar
        if (this.radarChartTimer !== null) { clearTimeout(this.radarChartTimer); }
        this.radarChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderRadarChart(); }, 100);
      });
  }

  getDrilldownAvg(field: string): number {
    if (!this.operatorer.length) return 0;
    const sum = this.operatorer.reduce((s, o: any) => s + (o[field] ?? 0), 0);
    return +(sum / this.operatorer.length).toFixed(1);
  }

  getDrilldownCompareClass(val: number, avg: number, invertiert = false): string {
    if (val === avg) return 'text-muted';
    const better = invertiert ? val < avg : val > avg;
    return better ? 'text-success' : 'text-danger';
  }

  getDrilldownCompareIcon(val: number, avg: number, invertiert = false): string {
    if (val === avg) return 'fas fa-minus';
    const better = invertiert ? val < avg : val > avg;
    return better ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
  }

  // ---- Trendgraf (session #379) ----

  loadTrend(): void {
    if (!this.selectedOperator) return;
    this.loadingTrend = true;
    this.svc.getTrend(this.selectedOperator.operator_id, this.trendDays)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success && res.data) {
          this.trendData = res.data.trend;
          this.trendOperatorNamn = res.data.operator_namn;
          this.trendSnittBonus = res.data.snitt_bonus;
          if (this.trendChartTimer !== null) { clearTimeout(this.trendChartTimer); }
          this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 100);
        }
      });
  }

  onTrendDaysChange(): void {
    this.loadTrend();
  }

  renderTrendChart(): void {
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null; }
    const canvas = document.getElementById('bonusTrendChart') as HTMLCanvasElement | null;
    if (!canvas || !this.trendData.length) return;

    const labels = this.trendData.map(d => {
      const parts = d.datum.split('-');
      return parts.length >= 3 ? `${parts[1]}-${parts[2]}` : d.datum;
    });
    const bonusData = this.trendData.map(d => d.bonus);
    const ibcData = this.trendData.map(d => d.ibc_per_h);
    const kvalData = this.trendData.map(d => d.kvalitet);
    const narvData = this.trendData.map(d => d.narvaro);
    const snittLine = this.trendData.map(() => this.trendSnittBonus);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Bonus (kr)',
            data: bonusData,
            borderColor: '#ecc94b',
            backgroundColor: 'rgba(236,201,75,0.12)',
            borderWidth: 2.5,
            fill: true,
            pointRadius: this.trendData.length > 60 ? 0 : 3,
            pointHoverRadius: 6,
            tension: 0.3,
            yAxisID: 'yLeft',
          },
          {
            label: 'Snittbonus',
            data: snittLine,
            borderColor: '#a0aec0',
            borderWidth: 1.5,
            borderDash: [6, 4],
            fill: false,
            pointRadius: 0,
            yAxisID: 'yLeft',
          },
          {
            label: 'IBC/h',
            data: ibcData,
            borderColor: '#4299e1',
            borderWidth: 1.5,
            fill: false,
            pointRadius: this.trendData.length > 60 ? 0 : 2,
            pointHoverRadius: 5,
            tension: 0.3,
            yAxisID: 'yRight',
          },
          {
            label: 'Kvalitet %',
            data: kvalData,
            borderColor: '#48bb78',
            borderWidth: 1.5,
            borderDash: [3, 2],
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.3,
            yAxisID: 'yRight',
          },
          {
            label: 'Närvaro %',
            data: narvData,
            borderColor: '#9f7aea',
            borderWidth: 1.5,
            borderDash: [3, 2],
            fill: false,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.3,
            yAxisID: 'yRight',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
          tooltip: {
            intersect: false, mode: 'index',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? 0;
                return this.trendData[idx]?.datum || '';
              },
              label: (ctx: any) => {
                if (ctx.datasetIndex === 0) return ` Bonus: ${this.formatKr(ctx.parsed.y)}`;
                if (ctx.datasetIndex === 1) return ` Snitt: ${this.formatKr(ctx.parsed.y)}`;
                if (ctx.datasetIndex === 2) return ` IBC/h: ${ctx.parsed.y.toFixed(1)}`;
                if (ctx.datasetIndex === 3) return ` Kvalitet: ${ctx.parsed.y.toFixed(1)}%`;
                return ` Närvaro: ${ctx.parsed.y.toFixed(1)}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'Bonus (kr)', color: '#ecc94b' },
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => v + ' kr',
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          yRight: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'KPI-värden', color: '#63b3ed' },
            ticks: { color: '#a0aec0' },
            grid: { drawOnChartArea: false },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ---- Charts ----

  renderRadarChart(): void {
    if (this.radarChart) { this.radarChart.destroy(); this.radarChart = null; }
    const canvas = document.getElementById('bonusRadarChart') as HTMLCanvasElement | null;
    if (!canvas || !this.selectedOperator) return;

    const op = this.selectedOperator;
    this.radarChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['IBC/h', 'Kvalitet', 'Närvaro', 'Team'],
        datasets: [{
          label: op.operator_namn,
          data: [op.pct_ibc, op.pct_kvalitet, op.pct_narvaro, op.pct_team],
          backgroundColor: '#4299e133',
          borderColor: '#4299e1',
          borderWidth: 2,
          pointBackgroundColor: '#4299e1',
          pointRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx) => `${ctx.label}: ${ctx.parsed.r.toFixed(1)}%`,
            },
          },
        },
        scales: {
          r: {
            min: 0,
            max: 100,
            ticks: {
              stepSize: 25,
              color: '#a0aec0',
              backdropColor: 'transparent',
            },
            grid: { color: '#4a556855' },
            angleLines: { color: '#4a556855' },
            pointLabels: { color: '#e2e8f0', font: { size: 13 } },
          },
        },
      },
    });
  }

  renderBarChart(): void {
    if (this.barChart) { this.barChart.destroy(); this.barChart = null; }
    const canvas = document.getElementById('bonusBarChart') as HTMLCanvasElement | null;
    if (!canvas || this.operatorer.length === 0) return;

    const labels = this.operatorer.map(o => o.operator_namn);
    const ibcData  = this.operatorer.map(o => o.bonus_ibc);
    const kvalData = this.operatorer.map(o => o.bonus_kvalitet);
    const narvData = this.operatorer.map(o => o.bonus_narvaro);
    const teamData = this.operatorer.map(o => o.bonus_team);

    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'IBC/h',    data: ibcData,  backgroundColor: '#4299e1', borderRadius: 3 },
          { label: 'Kvalitet', data: kvalData, backgroundColor: '#48bb78', borderRadius: 3 },
          { label: 'Närvaro',  data: narvData, backgroundColor: '#ecc94b', borderRadius: 3 },
          { label: 'Team',     data: teamData, backgroundColor: '#9f7aea', borderRadius: 3 },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${this.formatKr(ctx.parsed.y)}`,
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45 },
            grid: { color: '#4a556833' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => v + ' kr',
            },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  renderSimChart(): void {
    if (this.simChart) { this.simChart.destroy(); this.simChart = null; }
    const canvas = document.getElementById('simResultChart') as HTMLCanvasElement | null;
    if (!canvas || !this.simulering) return;

    const sim = this.simulering;
    this.simChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['IBC/h', 'Kvalitet', 'Närvaro', 'Team'],
        datasets: [{
          data: [sim.bonus_ibc, sim.bonus_kvalitet, sim.bonus_narvaro, sim.bonus_team],
          backgroundColor: ['#4299e1', '#48bb78', '#ecc94b', '#9f7aea'],
          borderWidth: 2,
          borderColor: '#2d3748',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: '#e2e8f0', padding: 12, font: { size: 12 } },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx) => `${ctx.label}: ${this.formatKr(ctx.parsed)}`,
            },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  formatKr(value: number | null | undefined): string {
    if (value == null) return '\u2014';
    return value.toLocaleString('sv-SE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' kr';
  }

  formatKrDecimal(value: number | null | undefined): string {
    if (value == null) return '\u2014';
    return value.toLocaleString('sv-SE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr';
  }

  formatPct(value: number | null | undefined): string {
    if (value == null) return '\u2014';
    return value.toFixed(1) + '%';
  }

  progressBarColor(pct: number): string {
    if (pct >= 90) return '#48bb78';
    if (pct >= 70) return '#ecc94b';
    if (pct >= 50) return '#ed8936';
    return '#e53e3e';
  }

  periodLabel(p: string): string {
    switch (p) {
      case 'vecka': return 'Vecka';
      case 'manad': return 'Månad';
      default: return 'Idag';
    }
  }

  faktorLabel(faktor: string): string {
    const labels: Record<string, string> = {
      'ibc_per_timme': 'IBC per timme',
      'kvalitet':      'Kvalitet (%)',
      'narvaro':       'Närvaro (%)',
      'team_bonus':    'Team-mål (%)',
    };
    return labels[faktor] ?? faktor;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
  trackByOperatorId(index: number, item: any): any { return item?.operator_id ?? item?.id ?? index; }

  // ---- Status-hjälpare ----

  /** Returnerar statusetikett baserat på total bonus som % av max */
  bonusStatusLabel(totalBonus: number): string {
    const pct = this.maxTotal > 0 ? (totalBonus / this.maxTotal) * 100 : 0;
    if (pct >= 85) return 'Utmärkt';
    if (pct >= 65) return 'Bra';
    if (pct >= 40) return 'Medel';
    return 'Låg';
  }

  bonusStatusClass(totalBonus: number): string {
    const pct = this.maxTotal > 0 ? (totalBonus / this.maxTotal) * 100 : 0;
    if (pct >= 85) return 'status-excellent';
    if (pct >= 65) return 'status-good';
    if (pct >= 40) return 'status-medium';
    return 'status-low';
  }

  bonusStatusIcon(totalBonus: number): string {
    const pct = this.maxTotal > 0 ? (totalBonus / this.maxTotal) * 100 : 0;
    if (pct >= 85) return 'fas fa-star';
    if (pct >= 65) return 'fas fa-thumbs-up';
    if (pct >= 40) return 'fas fa-minus-circle';
    return 'fas fa-arrow-down';
  }

  bonusPctOfMax(totalBonus: number): string {
    const pct = this.maxTotal > 0 ? (totalBonus / this.maxTotal) * 100 : 0;
    return pct.toFixed(0) + '%';
  }

  /** Tooltip-text för bonus-beräkning per operatör */
  bonusFormelTooltip(op: any): string {
    return `IBC/h: ${op.ibc_per_timme?.toFixed(1)} → ${this.formatKr(op.bonus_ibc)} | ` +
           `Kvalitet: ${op.kvalitet?.toFixed(1)}% → ${this.formatKr(op.bonus_kvalitet)} | ` +
           `Närvaro: ${op.narvaro?.toFixed(1)}% → ${this.formatKr(op.bonus_narvaro)} | ` +
           `Team: ${this.formatKr(op.bonus_team)}`;
  }
}
