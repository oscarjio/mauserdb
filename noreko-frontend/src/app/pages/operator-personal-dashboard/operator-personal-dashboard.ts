import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  MyStatsService,
  MyStatsData,
  MyTrendData,
  MyAchievementsData,
} from '../../services/my-stats.service';
import { AuthService } from '../../services/auth.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-operator-personal-dashboard',
  templateUrl: './operator-personal-dashboard.html',
  styleUrls: ['./operator-personal-dashboard.css'],
  imports: [CommonModule, FormsModule],
})
export class OperatorPersonalDashboardPage implements OnInit, OnDestroy {

  // -- Period --
  period: 7 | 30 | 90 = 30;
  readonly periodOptions: { value: 7 | 30 | 90; label: string }[] = [
    { value: 7,  label: '7 dagar' },
    { value: 30, label: '30 dagar' },
    { value: 90, label: '90 dagar' },
  ];

  // -- Laddning --
  loadingStats        = false;
  loadingTrend        = false;
  loadingAchievements = false;

  // -- Fel --
  errorStats        = false;
  errorTrend        = false;
  errorAchievements = false;
  errorStatsMsg     = '';

  // -- Data --
  stats: MyStatsData | null = null;
  trend: MyTrendData | null = null;
  achievements: MyAchievementsData | null = null;

  // -- Dagens datum --
  get todayLabel(): string {
    return new Date().toLocaleDateString('sv-SE', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
  }

  // -- Inloggad användare --
  operatorNamn = '';

  // -- Chart --
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(
    private svc: MyStatsService,
    private auth: AuthService,
  ) {}

  ngOnInit(): void {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      this.operatorNamn = user?.name || user?.username || 'Operatör';
    });
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  // =================================================================
  // Periodväljare
  // =================================================================

  onPeriodChange(p: 7 | 30 | 90): void {
    this.period = p;
    this.loadAll();
  }

  // =================================================================
  // Data-laddning
  // =================================================================

  loadAll(): void {
    this.loadStats();
    this.loadTrend();
    this.loadAchievements();
  }

  loadStats(): void {
    this.loadingStats = true;
    this.errorStats   = false;
    this.errorStatsMsg = '';

    this.svc.getMyStats(this.period)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStats = false;
        if (res?.success) {
          this.stats = res.data;
          if (this.stats.operator_namn) {
            this.operatorNamn = this.stats.operator_namn;
          }
        } else {
          this.errorStats = true;
          if (!res) {
            this.errorStatsMsg = 'Kunde inte nå servern.';
          } else {
            this.errorStatsMsg = 'Inget operatörsnummer kopplat — gå till inställningarna och koppla ditt operator-ID.';
          }
          this.stats = null;
        }
      });
  }

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.destroyChart();

    const p: 30 | 90 = this.period === 7 ? 30 : (this.period as 30 | 90);
    this.svc.getMyTrend(p)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trend = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 0);
        } else {
          this.errorTrend = true;
          this.trend = null;
        }
      });
  }

  loadAchievements(): void {
    this.loadingAchievements = true;
    this.errorAchievements   = false;

    this.svc.getMyAchievements()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingAchievements = false;
        if (res?.success) {
          this.achievements = res.data;
        } else {
          this.errorAchievements = true;
          this.achievements = null;
        }
      });
  }

  // =================================================================
  // Chart.js — IBC/h trend (din linje + teamsnitt)
  // =================================================================

  private buildTrendChart(): void {
    this.destroyChart();
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas || !this.trend) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.trend.dates.map(d => {
      const dt = new Date(d + 'T12:00:00');
      return dt.toLocaleDateString('sv-SE', { day: 'numeric', month: 'numeric' });
    });

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Din IBC/h',
            data: this.trend.my_ibc_per_h,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99,179,237,0.12)',
            pointBackgroundColor: '#63b3ed',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            borderWidth: 2,
            tension: 0.3,
            fill: true,
          },
          {
            label: 'Teamsnitt IBC/h',
            data: this.trend.team_ibc_per_h,
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246,173,85,0.06)',
            pointBackgroundColor: '#f6ad55',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 3,
            borderWidth: 2,
            borderDash: [5, 4],
            tension: 0.3,
            fill: false,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              color: '#e2e8f0',
              boxWidth: 12,
              padding: 16,
              font: { size: 12 },
            },
          },
          tooltip: {
            callbacks: {
              label: (item: any) => ` ${item.dataset.label}: ${(item.raw as number).toFixed(1)} IBC/h`,
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              maxRotation: 45,
              font: { size: 10 },
              maxTicksLimit: 20,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (v: any) => `${v}`,
            },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: {
              display: true,
              text: 'IBC / timme',
              color: '#a0aec0',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjälpmetoder — visning
  // =================================================================

  get ibcPerHColor(): string {
    if (!this.stats) return '#e2e8f0';
    const v = this.stats.snitt_ibc_per_h;
    if (v >= 18) return '#68d391';
    if (v >= 12) return '#f6e05e';
    return '#fc8181';
  }

  get kvalitetColor(): string {
    if (!this.stats || this.stats.kvalitet_pct === null) return '#a0aec0';
    const v = this.stats.kvalitet_pct;
    if (v >= 95) return '#68d391';
    if (v >= 85) return '#f6e05e';
    return '#fc8181';
  }

  /** Jämförelseprocentandel din IBC/h vs teamsnitt (0–200%) för progressbar */
  get myIbcBarPct(): number {
    if (!this.stats || this.stats.team_snitt_ibc_per_h === 0) return 0;
    return Math.min(200, Math.round(this.stats.snitt_ibc_per_h / this.stats.team_snitt_ibc_per_h * 100));
  }

  get teamIbcBarPct(): number { return 100; }

  get myKvalitetBarPct(): number {
    if (!this.stats || this.stats.kvalitet_pct === null) return 0;
    return Math.min(100, Math.round(this.stats.kvalitet_pct));
  }

  get teamKvalitetBarPct(): number {
    if (!this.stats || this.stats.team_snitt_kvalitet === null) return 0;
    return Math.min(100, Math.round(this.stats.team_snitt_kvalitet));
  }

  get forbattringColor(): string {
    if (!this.achievements) return '#a0aec0';
    switch (this.achievements.forbattring_direction) {
      case 'upp':   return '#68d391';
      case 'ner':   return '#fc8181';
      default:      return '#a0aec0';
    }
  }

  get forbattringIcon(): string {
    if (!this.achievements) return 'fa-minus';
    switch (this.achievements.forbattring_direction) {
      case 'upp':   return 'fa-arrow-up';
      case 'ner':   return 'fa-arrow-down';
      default:      return 'fa-minus';
    }
  }

  get forbattringText(): string {
    if (!this.achievements) return '—';
    const pct = this.achievements.forbattring_pct;
    const sign = pct > 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
  }

  formatDate(d: string | null): string {
    if (!d) return '—';
    const dt = new Date(d + 'T12:00:00');
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString('sv-SE', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  rankingLabel(rank: number, total: number): string {
    if (total === 0) return '—';
    return `#${rank} av ${total}`;
  }

  get rankingColor(): string {
    if (!this.stats || this.stats.total_ops === 0) return '#a0aec0';
    const pct = this.stats.ranking / this.stats.total_ops;
    if (pct <= 0.25) return '#f6e05e';   // topp 25% = guld
    if (pct <= 0.5)  return '#68d391';   // topp 50% = grön
    return '#a0aec0';
  }

  get harTrendData(): boolean {
    return !!(this.trend && this.trend.my_ibc_per_h.some(v => v > 0));
  }
}
