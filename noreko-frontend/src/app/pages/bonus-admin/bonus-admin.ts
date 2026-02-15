import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { BonusAdminService, BonusPeriod } from '../../services/bonus-admin.service';

@Component({
  standalone: true,
  selector: 'app-bonus-admin',
  templateUrl: './bonus-admin.html',
  styleUrl: './bonus-admin.css',
  imports: [CommonModule, FormsModule]
})
export class BonusAdminPage implements OnInit {
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  // State
  loading = false;
  successMessage = '';
  errorMessage = '';

  // System stats
  systemStats: any = null;

  // Config
  config: any = null;
  editingWeights: { [key: number]: boolean } = {};
  weightsForm: { [key: number]: { eff: number; prod: number; qual: number } } = {};

  // Targets
  targetsForm = { foodgrade: 12, nonun: 20, tvattade: 15 };
  editingTargets = false;

  // Periods
  periods: BonusPeriod[] = [];

  // Active tab
  activeTab = 'overview';

  constructor(
    private auth: AuthService,
    private bonusAdmin: BonusAdminService
  ) {
    this.auth.loggedIn$.subscribe(val => this.loggedIn = val);
    this.auth.user$.subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadSystemStats();
    this.loadConfig();
    this.loadPeriods();
  }

  // ========== System Stats ==========
  loadSystemStats() {
    this.bonusAdmin.getSystemStats().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.systemStats = res.data;
        }
      },
      error: (err) => this.showError('Kunde inte ladda systemstatistik')
    });
  }

  // ========== Config ==========
  loadConfig() {
    this.loading = true;
    this.bonusAdmin.getConfig().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.config = res.data;
          // Fyll i forms
          this.weightsForm[1] = { ...(res.data.weights_foodgrade || { eff: 0.3, prod: 0.3, qual: 0.4 }) };
          this.weightsForm[4] = { ...(res.data.weights_nonun || { eff: 0.35, prod: 0.45, qual: 0.2 }) };
          this.weightsForm[5] = { ...(res.data.weights_tvattade || { eff: 0.4, prod: 0.35, qual: 0.25 }) };
          this.targetsForm = {
            foodgrade: res.data.productivity_target_foodgrade || 12,
            nonun: res.data.productivity_target_nonun || 20,
            tvattade: res.data.productivity_target_tvattade || 15
          };
        }
        this.loading = false;
      },
      error: () => {
        this.showError('Kunde inte ladda konfiguration');
        this.loading = false;
      }
    });
  }

  startEditWeights(produkt: number) {
    this.editingWeights[produkt] = true;
  }

  cancelEditWeights(produkt: number) {
    this.editingWeights[produkt] = false;
    this.loadConfig(); // Reset
  }

  saveWeights(produkt: number) {
    const weights = this.weightsForm[produkt];
    const sum = weights.eff + weights.prod + weights.qual;
    if (Math.abs(sum - 1.0) > 0.01) {
      this.showError(`Viktningarna måste summera till 1.0 (nu: ${sum.toFixed(3)})`);
      return;
    }

    this.loading = true;
    this.bonusAdmin.updateWeights(produkt, weights).subscribe({
      next: (res) => {
        if (res.success) {
          this.showSuccess('Viktningar uppdaterade!');
          this.editingWeights[produkt] = false;
          this.loadConfig();
        } else {
          this.showError(res.error || 'Fel vid uppdatering');
        }
        this.loading = false;
      },
      error: () => {
        this.showError('Nätverksfel vid uppdatering');
        this.loading = false;
      }
    });
  }

  getWeightsSum(produkt: number): number {
    const w = this.weightsForm[produkt];
    return w ? +(w.eff + w.prod + w.qual).toFixed(3) : 0;
  }

  // ========== Targets ==========
  startEditTargets() {
    this.editingTargets = true;
  }

  cancelEditTargets() {
    this.editingTargets = false;
    this.loadConfig();
  }

  saveTargets() {
    this.loading = true;
    this.bonusAdmin.setTargets(this.targetsForm).subscribe({
      next: (res) => {
        if (res.success) {
          this.showSuccess('Produktivitetsmål uppdaterade!');
          this.editingTargets = false;
        } else {
          this.showError(res.error || 'Fel vid uppdatering');
        }
        this.loading = false;
      },
      error: () => {
        this.showError('Nätverksfel vid uppdatering');
        this.loading = false;
      }
    });
  }

  // ========== Periods ==========
  loadPeriods() {
    this.bonusAdmin.getPeriods().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.periods = res.data.periods || [];
        }
      },
      error: () => this.showError('Kunde inte ladda perioder')
    });
  }

  approvePeriod(period: string) {
    if (!confirm(`Godkänn alla bonusar för period ${period}?`)) return;

    this.loading = true;
    this.bonusAdmin.approveBonuses(period).subscribe({
      next: (res) => {
        if (res.success) {
          this.showSuccess(`Bonusar godkända för ${period}! (${res.data?.cycles_approved} cykler)`);
          this.loadPeriods();
        } else {
          this.showError(res.error || 'Fel vid godkännande');
        }
        this.loading = false;
      },
      error: () => {
        this.showError('Nätverksfel');
        this.loading = false;
      }
    });
  }

  exportPeriod(period: string) {
    this.bonusAdmin.exportReport(period, 'csv');
    this.showSuccess(`Exporterar ${period}...`);
  }

  // ========== Helpers ==========
  setTab(tab: string) {
    this.activeTab = tab;
  }

  getProductName(id: number): string {
    const names: { [k: number]: string } = { 1: 'FoodGrade', 4: 'NonUN', 5: 'Tvättade IBC' };
    return names[id] || 'Okänd';
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 80) return 'text-success';
    if (bonus >= 70) return 'text-warning';
    return 'text-danger';
  }

  getTrendIcon(trend: number): string {
    if (trend > 0) return 'fa-arrow-up text-success';
    if (trend < 0) return 'fa-arrow-down text-danger';
    return 'fa-minus text-muted';
  }

  private showSuccess(msg: string) {
    this.successMessage = msg;
    this.errorMessage = '';
    setTimeout(() => this.successMessage = '', 4000);
  }

  private showError(msg: string) {
    this.errorMessage = msg;
    this.successMessage = '';
    setTimeout(() => this.errorMessage = '', 6000);
  }
}
