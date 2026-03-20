import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  ProduktionskostnadService,
  KostnadOverview,
  KostnadBreakdown,
  KostnadTrend,
  DailyTable,
  ShiftComparison,
  KonfigFaktor,
} from '../../../services/produktionskostnad.service';
import { localToday, localDateStr } from '../../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-produktionskostnad',
  templateUrl: './produktionskostnad.component.html',
  styleUrls: ['./produktionskostnad.component.css'],
  imports: [CommonModule, FormsModule],
})
export class ProduktionskostnadPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview   = false;
  loadingBreakdown  = false;
  loadingTrend      = false;
  loadingTable      = false;
  loadingShift      = false;
  loadingConfig     = false;

  // Error states
  errorData = false;

  // Data
  overview:    KostnadOverview | null  = null;
  breakdown:   KostnadBreakdown | null = null;
  trendData:   KostnadTrend | null     = null;
  dailyTable:  DailyTable | null       = null;
  shiftComp:   ShiftComparison | null  = null;
  configItems: KonfigFaktor[]          = [];

  // Filter
  period:       string = 'dag';
  trendPeriod:  number = 30;
  tableFrom:    string = '';
  tableTo:      string = '';

  // Konfig-formulär
  showConfigForm  = false;
  configForm:     { faktor: string; varde: number }[] = [];
  savingConfig    = false;
  configMessage   = '';
  configError     = '';

  // Charts
  private doughnutChart: Chart | null = null;
  private trendChart:    Chart | null = null;
  private shiftChart:    Chart | null = null;

  private destroy$      = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private chartTimers: ReturnType<typeof setTimeout>[] = [];
  private isFetching = false;

  constructor(private svc: ProduktionskostnadService) {}

  ngOnInit(): void {
    const today = new Date();
    this.tableTo   = localToday();
    this.tableFrom = localDateStr(new Date(today.getTime() - 30 * 86400000));

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
    this.chartTimers.forEach(t => clearTimeout(t));
    this.chartTimers = [];
    this.destroyCharts();
  }

  private destroyCharts(): void {
    if (this.doughnutChart) { this.doughnutChart.destroy(); this.doughnutChart = null; }
    if (this.trendChart)    { this.trendChart.destroy();    this.trendChart = null; }
    if (this.shiftChart)    { this.shiftChart.destroy();    this.shiftChart = null; }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadBreakdown();
    this.loadTrend();
    this.loadTable();
    this.loadShift();
  }

  // ---- Overview ----

  loadOverview(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview().pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) { this.overview = res.data; }
        else if (res !== null) { this.errorData = true; }
    });
  }

  // ---- Breakdown ----

  loadBreakdown(): void {
    this.loadingBreakdown = true;
    this.svc.getBreakdown(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingBreakdown = false;
        if (res?.success) {
          this.breakdown = res.data;
          this.chartTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderDoughnutChart(); }, 100));
        }
    });
  }

  onPeriodChange(): void {
    this.loadBreakdown();
    this.loadShift();
  }

  // ---- Trend ----

  loadTrend(): void {
    this.loadingTrend = true;
    this.svc.getTrend(this.trendPeriod).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          this.chartTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 100));
        }
    });
  }

  onTrendPeriodChange(): void {
    this.loadTrend();
  }

  // ---- Daily Table ----

  loadTable(): void {
    this.loadingTable = true;
    this.svc.getDailyTable(this.tableFrom, this.tableTo).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingTable = false;
        if (res?.success) {
          this.dailyTable = res.data;
        }
    });
  }

  onTableFilterChange(): void {
    this.loadTable();
  }

  // ---- Shift Comparison ----

  loadShift(): void {
    this.loadingShift = true;
    this.svc.getShiftComparison(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingShift = false;
        if (res?.success) {
          this.shiftComp = res.data;
          this.chartTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderShiftChart(); }, 100));
        }
    });
  }

  // ---- Config ----

  loadConfig(): void {
    this.loadingConfig = true;
    this.svc.getConfig().pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingConfig = false;
        if (res?.success) {
          this.configItems = res.config;
          this.configForm = res.config.map(c => ({ faktor: c.faktor, varde: c.varde }));
        }
    });
  }

  toggleConfigForm(): void {
    this.showConfigForm = !this.showConfigForm;
    if (this.showConfigForm && this.configItems.length === 0) {
      this.loadConfig();
    }
    this.configMessage = '';
    this.configError   = '';
  }

  submitConfig(): void {
    this.savingConfig = true;
    this.configError  = '';
    this.configMessage= '';

    this.svc.updateConfig(this.configForm).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.savingConfig = false;
        if (res?.success) {
          this.configMessage = 'Konfiguration sparad!';
          this.loadAll();
          this.loadConfig();
        } else {
          this.configError = res?.error || 'Kunde inte spara konfiguration';
        }
    });
  }

  getConfigLabel(faktor: string): string {
    const found = this.configItems.find(c => c.faktor === faktor);
    return found?.label ?? faktor;
  }

  getConfigEnhet(faktor: string): string {
    const found = this.configItems.find(c => c.faktor === faktor);
    return found?.enhet ?? '';
  }

  // ---- Charts ----

  renderDoughnutChart(): void {
    if (this.doughnutChart) { this.doughnutChart.destroy(); this.doughnutChart = null; }
    const canvas = document.getElementById('kostnadDoughnutChart') as HTMLCanvasElement | null;
    if (!canvas || !this.breakdown) return;

    const bd = this.breakdown;
    const labels  = ['Energi', 'Bemanning', 'Material', 'Kassation', 'Overhead'];
    const values  = [bd.energi, bd.bemanning, bd.material, bd.kassation, bd.overhead];
    const colors  = ['#4299e1', '#48bb78', '#ecc94b', '#e53e3e', '#a0aec0'];

    this.doughnutChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: colors,
          borderWidth: 2,
          borderColor: '#2d3748',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              color: '#e2e8f0',
              padding: 12,
              font: { size: 12 },
            },
          },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const total = (ctx.dataset.data as number[]).reduce((a, b) => a + b, 0);
                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : '0';
                return `${ctx.label}: ${this.formatKr(ctx.parsed)} (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  renderTrendChart(): void {
    if (this.trendChart) { this.trendChart.destroy(); this.trendChart = null; }
    const canvas = document.getElementById('kostnadTrendChart') as HTMLCanvasElement | null;
    if (!canvas || !this.trendData) return;

    const td     = this.trendData;
    const labels = td.trend.map(d => d.date.substring(5));
    const values = td.trend.map(d => d.kostnad_per_ibc);
    const snitt  = td.snitt;

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Kostnad/IBC (kr)',
            data: values,
            borderColor: '#4299e1',
            backgroundColor: '#4299e122',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointHoverRadius: 5,
          },
          {
            label: `Snitt ${this.formatKr(snitt)}/IBC`,
            data: Array(labels.length).fill(snitt),
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
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${this.formatKr(ctx.parsed.y)}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 15 },
            grid: { color: '#4a556833' },
          },
          y: {
            beginAtZero: false,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => this.formatKr(v),
            },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  renderShiftChart(): void {
    if (this.shiftChart) { this.shiftChart.destroy(); this.shiftChart = null; }
    const canvas = document.getElementById('kostnadShiftChart') as HTMLCanvasElement | null;
    if (!canvas || !this.shiftComp || this.shiftComp.skift.length === 0) return;

    const skift  = this.shiftComp.skift;
    const labels = skift.map(s => s.label);
    const values = skift.map(s => s.kostnad_per_ibc);
    const colors = values.map((_, i) => {
      const palette = ['#4299e1', '#48bb78', '#ecc94b', '#e53e3e', '#a0aec0', '#9f7aea'];
      return palette[i % palette.length];
    });

    this.shiftChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Kostnad/IBC (kr)',
          data: values,
          backgroundColor: colors,
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx) => `Kostnad/IBC: ${this.formatKr(ctx.parsed.y)}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => this.formatKr(v),
            },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  formatKr(value: number | null | undefined): string {
    if (value == null) return '—';
    return value.toLocaleString('sv-SE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' kr';
  }

  formatKrDecimal(value: number | null | undefined): string {
    if (value == null) return '—';
    return value.toLocaleString('sv-SE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr';
  }

  trendIcon(riktning: string): string {
    switch (riktning) {
      case 'uppat': return 'fas fa-arrow-up text-danger';   // Dyrare = dåligt
      case 'nedat': return 'fas fa-arrow-down text-success'; // Billigare = bra
      default: return 'fas fa-minus text-muted';
    }
  }

  trendLabel(riktning: string, pct: number): string {
    const sign = pct > 0 ? '+' : '';
    switch (riktning) {
      case 'uppat': return `${sign}${pct}% dyrare vs förra veckan`;
      case 'nedat': return `${sign}${pct}% billigare vs förra veckan`;
      default: return 'Stabil kostnadsnivå';
    }
  }

  breakdownPct(value: number): number {
    if (!this.breakdown || this.breakdown.total === 0) return 0;
    return Math.round((value / this.breakdown.total) * 100);
  }

  periodLabel(p: string): string {
    switch (p) {
      case 'vecka': return 'Vecka';
      case 'manad': return 'Månad';
      default: return 'Idag';
    }
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
