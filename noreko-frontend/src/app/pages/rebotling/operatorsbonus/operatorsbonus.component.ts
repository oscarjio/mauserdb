import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  OperatorsbonusService,
  BonusOverviewData,
  OperatorBonus,
  PerOperatorData,
  KonfigItem,
  SimuleringData,
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

  // Charts
  private radarChart: Chart | null     = null;
  private barChart: Chart | null       = null;
  private simChart: Chart | null       = null;

  private destroy$         = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;

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
    this.destroyCharts();
  }

  private destroyCharts(): void {
    if (this.radarChart) { this.radarChart.destroy(); this.radarChart = null; }
    if (this.barChart)   { this.barChart.destroy();   this.barChart = null; }
    if (this.simChart)   { this.simChart.destroy();   this.simChart = null; }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadOperatorer();
  }

  // ---- Overview ----

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview(this.period).pipe(takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingOverview = false;
        if (res?.success) { this.overview = res.data; }
        else { this.errorData = true; }
      },
      error: () => { this.loadingOverview = false; this.errorData = true; }
    });
  }

  // ---- Per Operator ----

  loadOperatorer(): void {
    this.loadingOperatorer = true;
    this.svc.getPerOperator(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOperatorer = false;
      if (res?.success) {
        this.perOpData  = res.data;
        this.operatorer = res.data.operatorer;
        this.sortOperatorer();
        if (this.operatorer.length > 0 && !this.selectedOperator) {
          this.selectedOperator = this.operatorer[0];
        }
        setTimeout(() => {
          this.renderBarChart();
          this.renderRadarChart();
        }, 150);
      }
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
    setTimeout(() => { if (!this.destroy$.closed) this.renderRadarChart(); }, 100);
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
    this.svc.getKonfiguration().pipe(takeUntil(this.destroy$)).subscribe(res => {
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
      }
    });
  }

  submitKonfig(): void {
    this.savingKonfig = true;
    this.konfigError  = '';
    this.konfigMessage = '';

    this.svc.sparaKonfiguration(this.konfigForm).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.savingKonfig = false;
      if (res?.success) {
        this.konfigMessage = 'Konfiguration sparad!';
        this.loadAll();
        this.loadKonfig();
      } else {
        this.konfigError = res?.error || 'Kunde inte spara konfiguration';
      }
    });
  }

  // ---- Simulator ----

  runSimulering(): void {
    this.loadingSimulering = true;
    this.svc.getSimulering(this.simIbcPerTimme, this.simKvalitet, this.simNarvaro, this.simTeamMal)
      .pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingSimulering = false;
        if (res?.success) {
          this.simulering = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.renderSimChart(); }, 100);
        }
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
        labels: ['IBC/h', 'Kvalitet', 'Narvaro', 'Team'],
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

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'IBC/h',    data: ibcData,  backgroundColor: '#4299e1', borderRadius: 3 },
          { label: 'Kvalitet', data: kvalData, backgroundColor: '#48bb78', borderRadius: 3 },
          { label: 'Narvaro',  data: narvData, backgroundColor: '#ecc94b', borderRadius: 3 },
          { label: 'Team',     data: teamData, backgroundColor: '#9f7aea', borderRadius: 3 },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
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
        labels: ['IBC/h', 'Kvalitet', 'Narvaro', 'Team'],
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
      case 'manad': return 'Manad';
      default: return 'Idag';
    }
  }

  faktorLabel(faktor: string): string {
    const labels: Record<string, string> = {
      'ibc_per_timme': 'IBC per timme',
      'kvalitet':      'Kvalitet (%)',
      'narvaro':       'Narvaro (%)',
      'team_bonus':    'Team-mal (%)',
    };
    return labels[faktor] ?? faktor;
  }
  trackByIndex(index: number): number { return index; }
}
