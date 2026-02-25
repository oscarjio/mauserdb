import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { BonusService, OperatorStatsResponse, KPIDetailsResponse, OperatorHistoryResponse } from '../../services/bonus.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  selector: 'app-my-bonus',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './my-bonus.html',
  styleUrls: ['./my-bonus.css']
})
export class MyBonusPage implements OnInit, OnDestroy {
  loggedIn = false;
  operatorId = '';
  savedOperatorId = '';
  loading = false;
  error = '';
  // Om operatör-ID är kopplat till kontot (kan inte manuellt ändras av användaren)
  operatorIdFromAccount = false;

  stats: any = null;
  history: any[] = [];
  selectedPeriod = 'week';
  showFormula = false;

  private kpiChart: Chart | null = null;
  private historyChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private auth: AuthService, private bonusService: BonusService) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
  }

  ngOnInit(): void {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe((user: any) => {
      if (user?.operator_id) {
        // Operatör-ID är kopplat till kontot – använd det automatiskt
        this.operatorId = String(user.operator_id);
        this.savedOperatorId = String(user.operator_id);
        this.operatorIdFromAccount = true;
        this.loadStats();
      } else {
        // Fallback: använd localStorage
        this.operatorIdFromAccount = false;
        const saved = localStorage.getItem('myOperatorId');
        if (saved) {
          this.operatorId = saved;
          this.savedOperatorId = saved;
          this.loadStats();
        }
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.kpiChart) this.kpiChart.destroy();
    if (this.historyChart) this.historyChart.destroy();
  }

  saveAndLoad(): void {
    if (!this.operatorId.trim()) return;
    localStorage.setItem('myOperatorId', this.operatorId.trim());
    this.savedOperatorId = this.operatorId.trim();
    this.loadStats();
  }

  clearOperator(): void {
    localStorage.removeItem('myOperatorId');
    this.operatorId = '';
    this.savedOperatorId = '';
    this.stats = null;
    this.history = [];
    if (this.kpiChart) { this.kpiChart.destroy(); this.kpiChart = null; }
    if (this.historyChart) { this.historyChart.destroy(); this.historyChart = null; }
  }

  changePeriod(period: string): void {
    this.selectedPeriod = period;
    this.loadStats();
  }

  loadStats(): void {
    if (!this.savedOperatorId) return;
    this.loading = true;
    this.error = '';

    this.bonusService.getOperatorStats(this.savedOperatorId, this.selectedPeriod).subscribe({
      next: (res: OperatorStatsResponse) => {
        if (res.success && res.data) {
          this.stats = res.data;
          this.buildKPIChart(res.data);
        } else {
          this.error = res.error || 'Ingen data hittades för detta operatörs-ID.';
          this.stats = null;
        }
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta data. Försök igen.';
        this.loading = false;
      }
    });

    this.bonusService.getOperatorHistory(this.savedOperatorId, 20).subscribe({
      next: (res: OperatorHistoryResponse) => {
        if (res.success && res.data) {
          this.history = res.data.history || [];
          this.buildHistoryChart(this.history);
        }
      },
      error: () => {}
    });
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 90) return 'text-success';
    if (bonus >= 70) return 'text-info';
    if (bonus >= 50) return 'text-warning';
    return 'text-danger';
  }

  getBonusTier(bonus: number): string {
    if (bonus >= 95) return 'Outstanding (x2.0)';
    if (bonus >= 90) return 'Excellent (x1.5)';
    if (bonus >= 80) return 'God prestanda (x1.25)';
    if (bonus >= 70) return 'Basbonus (x1.0)';
    return 'Under förväntan (x0.75)';
  }

  getProductName(id: number): string {
    switch (id) {
      case 1: return 'FoodGrade';
      case 4: return 'NonUN';
      case 5: return 'Tvättade';
      default: return 'Produkt ' + id;
    }
  }

  getNextTierInfo(bonus: number): { name: string; pointsNeeded: number } | null {
    const tiers = [
      { name: 'Outstanding (x2.0)', threshold: 95 },
      { name: 'Excellent (x1.5)', threshold: 90 },
      { name: 'God prestanda (x1.25)', threshold: 80 },
      { name: 'Basbonus (x1.0)', threshold: 70 }
    ];
    for (const tier of tiers) {
      if (bonus < tier.threshold) {
        return { name: tier.name, pointsNeeded: tier.threshold - bonus };
      }
    }
    return null; // Already at top tier
  }

  getProjectedBonus(): { weekly: number; monthly: number } | null {
    if (!this.history || this.history.length < 3) return null;
    const recent = this.history.slice(0, 7);
    const avg = recent.reduce((sum: number, h: any) => sum + (h.kpis?.bonus ?? 0), 0) / recent.length;
    return {
      weekly: Math.round(avg * 10) / 10,
      monthly: Math.round(avg * 4 * 10) / 10
    };
  }

  getTrendDirection(): 'up' | 'down' | 'flat' {
    if (!this.history || this.history.length < 6) return 'flat';
    const recent3 = this.history.slice(0, 3).reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / 3;
    const prev3 = this.history.slice(3, 6).reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / 3;
    const diff = recent3 - prev3;
    if (diff > 2) return 'up';
    if (diff < -2) return 'down';
    return 'flat';
  }

  exportBonusCSV(): void {
    if (!this.stats?.daily_breakdown?.length) return;
    const header = ['Datum', 'Cykler', 'IBC OK', 'IBC Ej OK', 'Effektivitet', 'Produktivitet', 'Kvalitet', 'Bonus'];
    const rows = this.stats.daily_breakdown.map((d: any) => [
      d.date, d.cycles, d.ibc_ok, d.ibc_ej_ok,
      (d.effektivitet ?? 0).toFixed(1) + '%',
      (d.produktivitet ?? 0).toFixed(1),
      (d.kvalitet ?? 0).toFixed(1) + '%',
      (d.bonus_poang ?? 0).toFixed(1)
    ]);
    const csv = [header, ...rows].map(r => r.map((c: any) => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `min-bonus-${this.savedOperatorId}-${this.selectedPeriod}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  getPositionName(pos: string): string {
    switch (pos) {
      case 'position_1': return 'Tvättplats';
      case 'position_2': return 'Kontrollstation';
      case 'position_3': return 'Truckförare';
      default: return pos;
    }
  }

  private buildKPIChart(data: any): void {
    if (this.kpiChart) this.kpiChart.destroy();

    const canvas = document.getElementById('myKpiChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.kpiChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
        datasets: [{
          label: 'Dina KPI:er',
          data: [
            data.kpis?.effektivitet ?? 0,
            data.kpis?.produktivitet ?? 0,
            data.kpis?.kvalitet ?? 0
          ],
          borderColor: '#38b2ac',
          backgroundColor: 'rgba(56, 178, 172, 0.2)',
          pointBackgroundColor: '#38b2ac'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          r: {
            beginAtZero: true,
            max: 120,
            ticks: { color: '#a0aec0', backdropColor: 'transparent' },
            grid: { color: '#4a5568' },
            angleLines: { color: '#4a5568' },
            pointLabels: { color: '#e2e8f0', font: { size: 12 } }
          }
        },
        plugins: {
          legend: { labels: { color: '#a0aec0' } }
        }
      }
    });
  }

  private buildHistoryChart(history: any[]): void {
    if (this.historyChart) this.historyChart.destroy();

    const canvas = document.getElementById('myHistoryChart') as HTMLCanvasElement;
    if (!canvas || history.length === 0) return;

    const recent = history.slice(0, 15).reverse();
    const labels = recent.map((h: any) => h.datum?.substring(5) || '');
    const bonusData = recent.map((h: any) => h.kpis?.bonus ?? 0);

    this.historyChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Bonus',
          data: bonusData,
          backgroundColor: bonusData.map((b: number) =>
            b >= 90 ? 'rgba(72, 187, 120, 0.7)' :
            b >= 70 ? 'rgba(56, 178, 172, 0.7)' :
            b >= 50 ? 'rgba(236, 201, 75, 0.7)' :
            'rgba(229, 62, 62, 0.7)'
          ),
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, min: 0, max: 200 }
        }
      }
    });
  }
}
