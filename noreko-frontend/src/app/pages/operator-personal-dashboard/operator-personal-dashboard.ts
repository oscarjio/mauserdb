import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  OperatorPersonalDashboardService,
  OperatorItem,
  MinProduktionData,
  MittTempoData,
  MinBonusData,
  MinaStoppData,
  MinVeckotrendData,
} from '../../services/operator-personal-dashboard.service';
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

  // -- Operatörsval --
  operatorer: OperatorItem[] = [];
  selectedOp: number = 0;
  loadingOps = false;

  // -- Data --
  produktion: MinProduktionData | null = null;
  tempo: MittTempoData | null = null;
  bonus: MinBonusData | null = null;
  stopp: MinaStoppData | null = null;
  veckotrend: MinVeckotrendData | null = null;

  // -- Laddning --
  loadingProduktion = false;
  loadingTempo = false;
  loadingBonus = false;
  loadingStopp = false;
  loadingTrend = false;

  // -- Charts --
  private produktionChart: Chart | null = null;
  private veckotrendChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  Math = Math; // Expose Math to template

  constructor(
    private svc: OperatorPersonalDashboardService,
    private auth: AuthService,
  ) {}

  ngOnInit(): void {
    this.loadOperatorer();

    // Försök sätta operator från inloggad användare
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      if (user?.operator_id && this.selectedOp === 0) {
        this.selectedOp = user.operator_id;
        if (this.operatorer.length > 0) {
          this.loadAll();
        }
      }
    });

    // Auto-refresh var 60:e sekund
    this.refreshTimer = setInterval(() => {
      if (this.selectedOp > 0) this.loadAll();
    }, 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  private destroyCharts(): void {
    try { this.produktionChart?.destroy(); } catch (_) {}
    this.produktionChart = null;
    try { this.veckotrendChart?.destroy(); } catch (_) {}
    this.veckotrendChart = null;
  }

  // =================================================================
  // Operatörsval
  // =================================================================

  loadOperatorer(): void {
    this.loadingOps = true;
    this.svc.getOperatorer()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOps = false;
        if (res?.success) {
          this.operatorer = res.operatorer;
          // Autoselect om vi redan har operator_id
          if (this.selectedOp > 0 && this.operatorer.some(o => o.op_id === this.selectedOp)) {
            this.loadAll();
          }
        }
      });
  }

  onOperatorChange(): void {
    if (this.selectedOp > 0) {
      this.loadAll();
    }
  }

  // =================================================================
  // Ladda all data
  // =================================================================

  loadAll(): void {
    this.loadProduktion();
    this.loadTempo();
    this.loadBonus();
    this.loadStopp();
    this.loadVeckotrend();
  }

  loadProduktion(): void {
    this.loadingProduktion = true;
    this.svc.getMinProduktion(this.selectedOp)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingProduktion = false;
        this.produktion = res?.success ? res : null;
        setTimeout(() => { if (!this.destroy$.closed) this.buildProduktionChart(); }, 50);
      });
  }

  loadTempo(): void {
    this.loadingTempo = true;
    this.svc.getMittTempo(this.selectedOp)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTempo = false;
        this.tempo = res?.success ? res : null;
      });
  }

  loadBonus(): void {
    this.loadingBonus = true;
    this.svc.getMinBonus(this.selectedOp)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingBonus = false;
        this.bonus = res?.success ? res : null;
      });
  }

  loadStopp(): void {
    this.loadingStopp = true;
    this.svc.getMinaStopp(this.selectedOp)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStopp = false;
        this.stopp = res?.success ? res : null;
      });
  }

  loadVeckotrend(): void {
    this.loadingTrend = true;
    this.svc.getMinVeckotrend(this.selectedOp)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        this.veckotrend = res?.success ? res : null;
        setTimeout(() => { if (!this.destroy$.closed) this.buildVeckotrendChart(); }, 50);
      });
  }

  // =================================================================
  // Chart.js — Produktion per timme (stapeldiagram)
  // =================================================================

  private buildProduktionChart(): void {
    try { this.produktionChart?.destroy(); } catch (_) {}
    this.produktionChart = null;

    const canvas = document.getElementById('produktionTimmeChart') as HTMLCanvasElement;
    if (!canvas || !this.produktion) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.produktionChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: this.produktion.timmar,
        datasets: [{
          label: 'IBC',
          data: this.produktion.ibc_per_timme,
          backgroundColor: this.produktion.ibc_per_timme.map(v =>
            v > 0 ? 'rgba(99, 179, 237, 0.8)' : 'rgba(74, 85, 104, 0.3)'
          ),
          borderColor: 'rgba(99, 179, 237, 1)',
          borderWidth: 1,
          borderRadius: 4,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item: any) => `${item.raw} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 11 }, stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.08)' },
          },
        },
      },
    });
  }

  // =================================================================
  // Chart.js — Veckotrend (linjediagram)
  // =================================================================

  private buildVeckotrendChart(): void {
    try { this.veckotrendChart?.destroy(); } catch (_) {}
    this.veckotrendChart = null;

    const canvas = document.getElementById('veckotrendChart') as HTMLCanvasElement;
    if (!canvas || !this.veckotrend) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.veckotrend.dates.map(d => {
      const dt = new Date(d + 'T12:00:00');
      return dt.toLocaleDateString('sv-SE', { weekday: 'short', day: 'numeric', month: 'numeric' });
    });

    this.veckotrendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'IBC per dag',
          data: this.veckotrend.values,
          borderColor: '#63b3ed',
          backgroundColor: 'rgba(99, 179, 237, 0.15)',
          pointBackgroundColor: '#63b3ed',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 5,
          borderWidth: 3,
          tension: 0.3,
          fill: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item: any) => `${item.raw} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: {
              display: true,
              text: 'Antal IBC',
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

  get todayLabel(): string {
    return new Date().toLocaleDateString('sv-SE', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
  }

  get operatorNamn(): string {
    if (this.produktion?.operator_namn) return this.produktion.operator_namn;
    const op = this.operatorer.find(o => o.op_id === this.selectedOp);
    return op?.namn || 'Operatör';
  }

  get tempoColor(): string {
    if (!this.tempo) return '#e2e8f0';
    if (this.tempo.procent_vs_snitt >= 110) return '#68d391';
    if (this.tempo.procent_vs_snitt >= 90) return '#f6e05e';
    return '#fc8181';
  }

  get tempoLabel(): string {
    if (!this.tempo) return '';
    if (this.tempo.procent_vs_snitt >= 110) return 'Over snittet!';
    if (this.tempo.procent_vs_snitt >= 90) return 'Nara snittet';
    return 'Under snittet';
  }

  get gaugeRotation(): number {
    if (!this.tempo) return -90;
    const pct = Math.min(200, Math.max(0, this.tempo.procent_vs_snitt));
    return -90 + (pct / 200) * 180;
  }

  get bonusColor(): string {
    if (!this.bonus) return '#e2e8f0';
    if (this.bonus.total_poang >= 200) return '#68d391';
    if (this.bonus.total_poang >= 100) return '#f6e05e';
    return '#fc8181';
  }

  formatTime(sek: number): string {
    if (sek < 60) return `${sek}s`;
    const min = Math.floor(sek / 60);
    const s = sek % 60;
    if (min < 60) return `${min}m ${s}s`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${h}h ${m}m`;
  }

  get harProduktionData(): boolean {
    return !!(this.produktion && this.produktion.total_ibc > 0);
  }

  get harVeckotrendData(): boolean {
    return !!(this.veckotrend && this.veckotrend.values.some(v => v > 0));
  }
}
