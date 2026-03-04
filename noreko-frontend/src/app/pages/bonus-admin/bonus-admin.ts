import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { BonusAdminService, BonusPeriod, OperatorForecastResponse } from '../../services/bonus-admin.service';

@Component({
  standalone: true,
  selector: 'app-bonus-admin',
  templateUrl: './bonus-admin.html',
  styleUrl: './bonus-admin.css',
  imports: [CommonModule, FormsModule]
})
export class BonusAdminPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
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

  // Weekly goal
  weeklyGoalForm = 80;
  editingWeeklyGoal = false;

  // Periods
  periods: BonusPeriod[] = [];

  // Operator forecast
  forecastOperatorId = '';
  operatorForecast: OperatorForecastResponse['data'] | null = null;
  forecastLoading = false;
  forecastError = '';

  // Active tab
  activeTab = 'overview';

  // ========== What-if Simulator ==========
  simPeriodStart = '';
  simPeriodEnd = '';
  simIbcGoal = 45;
  simTiers = [
    { label: 'Brons',    min_ibc_per_hour: 4.0, bonus_sek: 500  },
    { label: 'Silver',   min_ibc_per_hour: 5.0, bonus_sek: 1000 },
    { label: 'Guld',     min_ibc_per_hour: 6.0, bonus_sek: 1800 },
    { label: 'Platinum', min_ibc_per_hour: 7.0, bonus_sek: 2800 },
  ];
  simLoading = false;
  simResult: any = null;
  simError = '';

  // ========== Bonusnivåer i kr ==========
  amountsForm = { brons: 500, silver: 1000, guld: 2000, platina: 3500 };
  amountsLoading = false;
  amountsSaving = false;
  amountsLastUpdated: string | null = null;
  amountsLastUpdatedBy: string | null = null;


  // ========== Utbetalningar ==========
  payouts: any[] = [];
  payoutSummary: any[] = [];
  payoutsLoading = false;
  showPayoutForm = false;
  payoutForm = {
    op_id: null as number | null,
    period_start: '',
    period_end: '',
    amount_sek: null as number | null,
    ibc_count: 0,
    avg_ibc_per_h: 0,
    avg_quality_pct: 0,
    notes: ''
  };
  payoutSaving = false;
  payoutMessage = '';
  payoutError = '';
  payoutYearFilter = new Date().getFullYear();
  payoutOpFilter = '';
  availableOperators: any[] = [];
  payoutSummaryYear = new Date().getFullYear();

  // ========== Utbetalningshistorik (ny flik) ==========
  payoutHistory: any[] = [];
  payoutHistoryLoading = false;
  payoutHistoryYear = new Date().getFullYear();
  payoutHistoryStatusFilter = '';
  payoutHistoryYears = [
    new Date().getFullYear(),
    new Date().getFullYear() - 1,
    new Date().getFullYear() - 2
  ];

  // Toast timer IDs
  private successTimerId: any = null;
  private errorTimerId: any = null;

  constructor(
    private auth: AuthService,
    private bonusAdmin: BonusAdminService,
    private http: HttpClient
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });

    // Default: förra månaden
    const now = new Date();
    const firstThisMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const firstLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const lastLastMonth  = new Date(firstThisMonth.getTime() - 86400000);
    const pad = (n: number) => String(n).padStart(2, '0');
    this.simPeriodStart = firstLastMonth.getFullYear() + '-' + pad(firstLastMonth.getMonth() + 1) + '-01';
    this.simPeriodEnd   = lastLastMonth.getFullYear()  + '-' + pad(lastLastMonth.getMonth()  + 1) + '-' + pad(lastLastMonth.getDate());
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    clearTimeout(this.successTimerId);
    clearTimeout(this.errorTimerId);
  }

  ngOnInit() {
    this.loadSystemStats();
    this.loadConfig();
    this.loadPeriods();
    this.loadAmounts();
  }

  // ========== System Stats ==========
  loadSystemStats() {
    this.bonusAdmin.getSystemStats().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.systemStats = res.data;
        }
      },
      error: () => this.showError('Kunde inte ladda systemstatistik')
    });
  }

  // ========== Config ==========
  loadConfig() {
    this.loading = true;
    this.bonusAdmin.getConfig().pipe(takeUntil(this.destroy$)).subscribe({
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
          // Ladda veckobonusmål
          if ((res.data as any).weekly_bonus_goal) {
            this.weeklyGoalForm = (res.data as any).weekly_bonus_goal;
          }
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
    this.bonusAdmin.updateWeights(produkt, weights).pipe(takeUntil(this.destroy$)).subscribe({
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
    this.bonusAdmin.setTargets(this.targetsForm).pipe(takeUntil(this.destroy$)).subscribe({
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

  // ========== Weekly Goal ==========
  startEditWeeklyGoal() {
    this.editingWeeklyGoal = true;
  }

  cancelEditWeeklyGoal() {
    this.editingWeeklyGoal = false;
    this.loadConfig();
  }

  saveWeeklyGoal() {
    if (this.weeklyGoalForm <= 0 || this.weeklyGoalForm > 200) {
      this.showError('Veckobonusmålet måste vara mellan 1 och 200 poäng');
      return;
    }
    this.loading = true;
    this.bonusAdmin.setWeeklyGoal(this.weeklyGoalForm).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.showSuccess('Veckobonusmål sparat!');
          this.editingWeeklyGoal = false;
        } else {
          this.showError(res.error || 'Fel vid sparning');
        }
        this.loading = false;
      },
      error: () => {
        this.showError('Nätverksfel vid sparning');
        this.loading = false;
      }
    });
  }

  getWeeklyGoalTierName(): string {
    const g = this.weeklyGoalForm;
    if (g >= 95) return 'Outstanding';
    if (g >= 90) return 'Excellent';
    if (g >= 80) return 'God prestanda';
    if (g >= 70) return 'Basbonus';
    return 'Under basbonus';
  }

  // ========== Operator Forecast ==========
  loadOperatorForecast() {
    const id = parseInt(this.forecastOperatorId.trim(), 10);
    if (!id || id <= 0) {
      this.forecastError = 'Ange ett giltigt operatör-ID';
      return;
    }
    this.forecastLoading = true;
    this.forecastError = '';
    this.operatorForecast = null;

    this.bonusAdmin.getOperatorForecast(id).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.operatorForecast = res.data;
        } else {
          this.forecastError = res.error || 'Ingen data hittades';
        }
        this.forecastLoading = false;
      },
      error: () => {
        this.forecastError = 'Nätverksfel vid hämtning av prognos';
        this.forecastLoading = false;
      }
    });
  }

  getForecastTierName(bonus: number): string {
    if (bonus >= 95) return 'Outstanding (x2.0)';
    if (bonus >= 90) return 'Excellent (x1.5)';
    if (bonus >= 80) return 'God prestanda (x1.25)';
    if (bonus >= 70) return 'Basbonus (x1.0)';
    return 'Under förväntan (x0.75)';
  }

  // ========== Periods ==========
  loadPeriods() {
    this.bonusAdmin.getPeriods().pipe(takeUntil(this.destroy$)).subscribe({
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
    this.bonusAdmin.approveBonuses(period).pipe(takeUntil(this.destroy$)).subscribe({
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

  // ========== What-if Simulator ==========
  runSimulation() {
    this.simLoading = true;
    this.simResult  = null;
    this.simError   = '';

    const payload = {
      period_start:       this.simPeriodStart,
      period_end:         this.simPeriodEnd,
      ibc_goal_per_shift: this.simIbcGoal,
      bonus_tiers:        this.simTiers,
    };

    this.http.post<any>(
      '/noreko-backend/api.php?action=bonus&run=simulate',
      payload,
      { withCredentials: true }
    ).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.simResult = res.data;
        } else {
          this.simError = res.error || 'Okänt fel vid simulering';
        }
        this.simLoading = false;
      },
      error: () => {
        this.simError   = 'Nätverksfel vid simulering';
        this.simLoading = false;
      }
    });
  }

  getSimTierColor(tier: string): string {
    switch (tier?.toLowerCase()) {
      case 'brons':    return '#cd7f32';
      case 'silver':   return '#a8a9ad';
      case 'guld':     return '#ffd700';
      case 'platinum': return '#e5e4e2';
      default:         return '#6c757d';
    }
  }

  // ========== Bonusnivåer i kr ==========
  loadAmounts() {
    this.amountsLoading = true;
    this.http.get<any>(
      '/noreko-backend/api.php?action=bonusadmin&run=getAmounts',
      { withCredentials: true }
    ).pipe(
      timeout(5000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.data?.amounts) {
          const a = res.data.amounts;
          this.amountsForm = {
            brons:   a.brons   ?? 500,
            silver:  a.silver  ?? 1000,
            guld:    a.guld    ?? 2000,
            platina: a.platina ?? 3500
          };
          this.amountsLastUpdated    = res.data.last_updated   ?? null;
          this.amountsLastUpdatedBy  = res.data.last_updated_by ?? null;
        }
        this.amountsLoading = false;
      },
      error: () => { this.amountsLoading = false; }
    });
  }

  saveAmounts() {
    const { brons, silver, guld, platina } = this.amountsForm;

    // Validering: inga negativa värden
    if (brons < 0 || silver < 0 || guld < 0 || platina < 0) {
      this.showError('Bonusbelopp får inte vara negativa.');
      return;
    }

    // Validering: max 100 000 SEK per nivå
    if (brons > 100000 || silver > 100000 || guld > 100000 || platina > 100000) {
      this.showError('Bonusbelopp får inte överstiga 100 000 SEK per nivå.');
      return;
    }

    // Validering: stigande ordning brons < silver < guld < platina
    if (brons >= silver || silver >= guld || guld >= platina) {
      this.showError('Bonusbeloppen måste vara i stigande ordning: Brons < Silver < Guld < Platina.');
      return;
    }

    this.amountsSaving = true;
    this.http.post<any>(
      '/noreko-backend/api.php?action=bonusadmin&run=setAmounts',
      this.amountsForm,
      { withCredentials: true }
    ).pipe(
      timeout(5000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.showSuccess('Bonusbelopp sparade!');
          this.loadAmounts();
        } else {
          this.showError(res?.error || 'Fel vid sparning av belopp');
        }
        this.amountsSaving = false;
      },
      error: () => {
        this.showError('Nätverksfel vid sparning av belopp');
        this.amountsSaving = false;
      }
    });
  }

  // ========== Helpers ==========
  setTab(tab: string) {
    this.activeTab = tab;
    if (tab === 'payouts') {
      this.onPayoutsTabActivated();
    }
    if (tab === 'payout-history') {
      this.loadPayoutHistory();
    }
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


  // ========== Utbetalningar ==========
  loadOperators() {
    this.http.get<any>(
      '/noreko-backend/api.php?action=bonusadmin&run=list-operators',
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success && res.data?.operators) {
          this.availableOperators = res.data.operators;
        }
      }
    });
  }

  loadPayouts() {
    this.payoutsLoading = true;
    const params = new URLSearchParams();
    params.set('year', String(this.payoutYearFilter));
    const opId = parseInt(this.payoutOpFilter, 10);
    if (opId > 0) params.set('op_id', String(opId));

    this.http.get<any>(
      `/noreko-backend/api.php?action=bonusadmin&run=list-payouts&${params.toString()}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.payouts = res.data?.payouts ?? [];
        }
        this.payoutsLoading = false;
      },
      error: () => { this.payoutsLoading = false; }
    });
  }

  loadPayoutSummary() {
    this.http.get<any>(
      `/noreko-backend/api.php?action=bonusadmin&run=payout-summary&year=${this.payoutSummaryYear}`,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.payoutSummary = res.data?.summary ?? [];
        }
      }
    });
  }

  recordPayout() {
    this.payoutMessage = '';
    this.payoutError = '';

    if (!this.payoutForm.op_id || this.payoutForm.op_id <= 0) {
      this.payoutError = 'Välj en operatör';
      return;
    }
    if (!this.payoutForm.period_start || !this.payoutForm.period_end) {
      this.payoutError = 'Ange period start och slut';
      return;
    }
    if (this.payoutForm.period_start > this.payoutForm.period_end) {
      this.payoutError = 'Periodens startdatum måste vara före eller lika med slutdatumet';
      return;
    }
    if (!this.payoutForm.amount_sek || this.payoutForm.amount_sek <= 0) {
      this.payoutError = 'Ange ett belopp större än 0 kr';
      return;
    }

    this.payoutSaving = true;
    this.http.post<any>(
      '/noreko-backend/api.php?action=bonusadmin&run=record-payout',
      this.payoutForm,
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.payoutMessage = 'Utbetalning registrerad!';
          this.showPayoutForm = false;
          this.resetPayoutForm();
          this.loadPayouts();
          this.loadPayoutSummary();
        } else {
          this.payoutError = res?.error || 'Fel vid sparning';
        }
        this.payoutSaving = false;
      },
      error: () => {
        this.payoutError = 'Nätverksfel vid sparning';
        this.payoutSaving = false;
      }
    });
  }

  deletePayout(id: number) {
    if (!confirm('Ta bort denna utbetalning?')) return;
    this.http.post<any>(
      '/noreko-backend/api.php?action=bonusadmin&run=delete-payout',
      { id },
      { withCredentials: true }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.payoutMessage = 'Utbetalning borttagen';
          this.loadPayouts();
          this.loadPayoutSummary();
        } else {
          this.payoutError = res?.error || 'Fel vid borttagning';
        }
      },
      error: () => {
        this.payoutError = 'Nätverksfel vid borttagning';
      }
    });
  }

  resetPayoutForm() {
    this.payoutForm = {
      op_id: null,
      period_start: '',
      period_end: '',
      amount_sek: null,
      ibc_count: 0,
      avg_ibc_per_h: 0,
      avg_quality_pct: 0,
      notes: ''
    };
  }

  onPayoutsTabActivated() {
    if (this.availableOperators.length === 0) this.loadOperators();
    this.loadPayouts();
    this.loadPayoutSummary();
  }

  // ========== Utbetalningshistorik ==========
  loadPayoutHistory(): void {
    this.payoutHistoryLoading = true;
    let url = `/noreko-backend/api.php?action=bonusadmin&run=list-payouts&year=${this.payoutHistoryYear}`;
    if (this.payoutHistoryStatusFilter) {
      url += `&status=${encodeURIComponent(this.payoutHistoryStatusFilter)}`;
    }
    this.http.get<any>(url, { withCredentials: true }).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.payoutHistory = res.data?.payouts ?? [];
        } else {
          this.payoutHistory = [];
        }
        this.payoutHistoryLoading = false;
      },
      error: () => { this.payoutHistoryLoading = false; }
    });
  }

  updatePayoutStatus(id: number, status: string): void {
    this.http.post<any>(
      '/noreko-backend/api.php?action=bonusadmin&run=update-payout-status',
      JSON.stringify({ id, status }),
      { withCredentials: true, headers: { 'Content-Type': 'application/json' } }
    ).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res?.success) {
          this.showSuccess('Status uppdaterad!');
          this.loadPayoutHistory();
        } else {
          this.showError(res?.error || 'Fel vid statusuppdatering');
        }
      },
      error: () => { this.showError('Nätverksfel vid statusuppdatering'); }
    });
  }

  get payoutHistoryTotal(): number {
    return this.payoutHistory.reduce((sum, p) => sum + (p.amount_sek || 0), 0);
  }

  getPayoutStatusLabel(status: string): string {
    switch (status) {
      case 'pending':  return 'Väntande';
      case 'approved': return 'Godkänd';
      case 'paid':     return 'Utbetald';
      default: return status;
    }
  }

  getPayoutStatusClass(status: string): string {
    switch (status) {
      case 'pending':  return 'bg-warning text-dark';
      case 'approved': return 'bg-primary';
      case 'paid':     return 'bg-success';
      default: return 'bg-secondary';
    }
  }

  getBonusLevelLabel(level: string): string {
    switch (level?.toLowerCase()) {
      case 'bronze': return 'Brons';
      case 'silver': return 'Silver';
      case 'gold':   return 'Guld';
      case 'platinum': return 'Platina';
      default: return '—';
    }
  }

  getBonusLevelStyle(level: string): string {
    switch (level?.toLowerCase()) {
      case 'bronze':   return 'color:#cd7f32; border-color:#cd7f32;';
      case 'silver':   return 'color:#a8a9ad; border-color:#a8a9ad;';
      case 'gold':     return 'color:#ffd700; border-color:#ffd700;';
      case 'platinum': return 'color:#90caf9; border-color:#90caf9;';
      default: return 'color:#6c757d; border-color:#6c757d;';
    }
  }

  exportPayoutHistoryCSV(): void {
    if (!this.payoutHistory.length) return;
    const headers = ['Operatör', 'Period', 'Bonusnivå', 'Belopp (SEK)', 'Status'];
    const rows = this.payoutHistory.map(p => [
      p.namn || '',
      (p.period_label || (p.period_start + ' – ' + p.period_end)),
      this.getBonusLevelLabel(p.bonus_level),
      String(p.amount_sek || 0),
      this.getPayoutStatusLabel(p.status)
    ]);
    const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `utbetalningshistorik-${this.payoutHistoryYear}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  formatSek(amount: number): string {
    if (amount == null) return '0 kr';
    return new Intl.NumberFormat('sv-SE', { style: 'currency', currency: 'SEK', maximumFractionDigits: 0 }).format(amount);
  }

  getPayoutsYears(): number[] {
    const cur = new Date().getFullYear();
    return [cur, cur - 1, cur - 2];
  }

  private showSuccess(msg: string) {
    this.successMessage = msg;
    this.errorMessage = '';
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => this.successMessage = '', 4000);
  }

  private showError(msg: string) {
    this.errorMessage = msg;
    this.successMessage = '';
    clearTimeout(this.errorTimerId);
    this.errorTimerId = setTimeout(() => this.errorMessage = '', 6000);
  }
}
