import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { localToday } from '../../utils/date-utils';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface GoalException {
  datum: string;
  justerat_mal: number;
  orsak: string;
}

interface ServiceStatus {
  service_interval: number;
  last_service_at: string | null;
  last_service_note: string | null;
  ibc_total: number;
  ibc_sedan_service: number;
  ibc_kvar_till_service: number;
  pct_kvar: number;
  status: 'ok' | 'warning' | 'danger';
}

@Component({
  standalone: true,
  selector: 'app-rebotling-admin',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './rebotling-admin.html',
  styleUrl: './rebotling-admin.css'
})
export class RebotlingAdminPage implements OnInit, OnDestroy, AfterViewInit {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  // ---- Produkthantering ----
  products: any[] = [];
  newProduct: any = { name: '', cycle_time_minutes: null };
  loading = false;
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;
  showAddProductForm = false;

  // ---- Admin-inställningar (befintliga) ----
  settings = {
    rebotlingTarget: 1000,
    hourlyTarget: 50,
    shiftHours: 8.0,
    minOperators: 2,
    systemSettings: { autoStart: false, maintenanceMode: false, alertThreshold: 80 }
  };
  settingsLoading = false;
  settingsSaving  = false;
  settingsError   = '';
  settingsSaved   = false;

  // ---- Veckodagsmål ----
  weekdayGoals: { weekday: number; daily_goal: number; label: string }[] = [];
  weekdayLoading = false;
  weekdaySaving  = false;
  weekdayError   = '';
  weekdaySaved   = false;
  // Snabbval
  quickSetValue: number = 0;
  // ISO-veckodagsnummer idag (1=Måndag ... 7=Söndag)
  todayWeekday: number = new Date().getDay() === 0 ? 7 : new Date().getDay();

  // ---- Skifttider ----
  shiftTimes: { shift_name: string; start_time: string; end_time: string; enabled: boolean }[] = [];
  shiftTimesLoading = false;
  shiftTimesSaving  = false;
  shiftTimesError   = '';
  shiftTimesSaved   = false;

  // ---- Systemstatus ----
  systemStatus: any = null;
  systemStatusLoading = false;
  systemStatusError   = '';
  systemStatusLastUpdated: Date | null = null;
  private systemStatusInterval: any = null;

  // ---- Underhållsindikator ----
  maintenanceData: any = null;
  maintenanceLoading = false;
  maintenanceStatus: 'ok' | 'warning' | 'danger' | null = null;
  maintenanceChart: Chart | null = null;
  private maintenanceTimer: any = null;

  // ---- Logga underhåll ----
  showMaintenanceLogForm = false;
  maintenanceLogText = '';
  maintenanceLogSaving = false;
  maintenanceLogSaved = false;
  maintenanceLogError = '';

  // ---- Snabb produktionsöversikt idag ----
  todaySnapshot: any = null;
  todaySnapshotLoading = false;
  private todaySnapshotInterval: any = null;

  // ---- Alert-trösklar ----
  alertThresholds = {
    oee_warn:    80,
    oee_danger:  70,
    prod_warn:   80,
    prod_danger: 60,
    plc_max_min: 15,
    quality_warn: 95,
  };
  alertThresholdsLoading = false;
  alertThresholdsSaving  = false;
  alertThresholdsSaved   = false;
  alertThresholdsError   = '';
  showAlertPanel         = false;

  // ---- E-postnotifikationer ----
  notificationSettings: {
    notification_emails: string;
    config: {
      enabled: boolean;
      on_stopp: boolean;
      on_low_oee: boolean;
      on_cert_expiry: boolean;
      on_maintenance: boolean;
      on_shift_report: boolean;
    };
  } = {
    notification_emails: '',
    config: {
      enabled: false,
      on_stopp: true,
      on_low_oee: true,
      on_cert_expiry: false,
      on_maintenance: false,
      on_shift_report: true,
    }
  };
  notificationSettingsLoading = false;
  notificationSettingsSaving = false;
  notificationSettingsError = '';
  notificationSettingsSaved = false;
  showNotificationPanel = false;

  // ---- Automatisk skiftrapport via email ----
  showShiftReportPanel = false;
  shiftReportSending = false;
  shiftReportError = '';
  shiftReportSuccess = '';
  shiftReportTestDate = localToday();
  shiftReportTestShift = 1;

  // ---- Live Ranking TV-inställningar ----
  lrSettings = {
    lr_show_quality:  true,
    lr_show_progress: true,
    lr_show_motto:    true,
    lr_poll_interval: 30,
    lr_title:         'Live Ranking'
  };
  lrSettingsSaving = false;
  showLrPanel = false;

  // ---- Live Ranking Config (KPI-kolumner, sortering) ----
  lrConfig = {
    columns: {
      ibc_per_hour: true,
      quality_pct: true,
      bonus_level: false,
      goal_progress: true,
      ibc_today: true
    },
    sort_by: 'ibc_per_hour',
    refresh_interval: 30
  };
  lrConfigSaving = false;
  showLrConfigPanel = false;

  // ---- Prediktivt underhåll — IBC-baserat serviceintervall ----
  serviceStatus: ServiceStatus | null = null;
  serviceStatusLoading = false;
  serviceInterval = 5000;
  serviceNote = '';
  savingServiceReset = false;
  serviceResetMsg = '';
  savingServiceInterval = false;
  serviceIntervalError = '';

  // ---- Korrelationsanalys underhåll vs stopp ----
  correlationData: any = null;
  correlationLoading = false;
  correlationError = '';
  private correlationChart: Chart | null = null;
  @ViewChild('correlationChartCanvas') correlationChartRef!: ElementRef;

  // ---- Dagsmål-historik ----
  goalHistory: any[] = [];
  goalHistoryLoading = false;
  goalHistoryPeriod = 180;  // 90 = 3 mån, 180 = 6 mån, 365 = 12 mån
  private goalHistoryChart: Chart | null = null;

  // ---- Anpassade dagsmål (datum-undantag) ----
  goalExceptions: GoalException[] = [];
  goalExceptionsLoading = false;
  newExceptionDatum: string = localToday();
  newExceptionMal: number = 0;
  newExceptionOrsak: string = '';
  savingException = false;
  exceptionSaveMsg = '';

  // ---- Visibilitychange-guard ----
  private visibilityHandler = () => this.onVisibilityChange();

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user    = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnDestroy() {
    document.removeEventListener('visibilitychange', this.visibilityHandler);
    clearTimeout(this.successTimerId);
    clearInterval(this.systemStatusInterval);
    clearInterval(this.maintenanceTimer);
    clearInterval(this.todaySnapshotInterval);
    try { this.maintenanceChart?.destroy(); } catch (e) {}
    this.maintenanceChart = null;
    try { this.goalHistoryChart?.destroy(); } catch (e) {}
    this.goalHistoryChart = null;
    try { this.correlationChart?.destroy(); } catch (e) {}
    this.correlationChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  ngOnInit() {
    document.addEventListener('visibilitychange', this.visibilityHandler);

    this.loadProducts();
    this.loadSettings();
    this.loadWeekdayGoals();
    this.loadShiftTimes();
    this.loadSystemStatus();
    this.loadTodaySnapshot();
    this.loadAlertThresholds();
    this.loadNotificationSettings();
    this.loadLrSettings();
    this.loadLrConfig();
    this.loadGoalHistory();
    this.loadGoalExceptions();
    this.loadServiceStatus();
    this.loadMaintenanceCorrelation();
    this.loadKassationTyper();
    this.loadKassationSenaste();

    this.startPollingTimers();
  }

  /** Starta alla polling-timers */
  private startPollingTimers() {
    // Systemstatus var 120:e sekund (admin-sida behöver inte 30s)
    this.systemStatusInterval = setInterval(() => {
      if (!this.destroy$.closed) this.loadSystemStatus();
    }, 120000);

    // Produktionsöversikt var 5:e minut
    this.todaySnapshotInterval = setInterval(() => {
      if (!this.destroy$.closed) this.loadTodaySnapshot();
    }, 300000);

    // Underhållsindikator var 5:e minut
    this.loadMaintenanceIndicator();
    this.maintenanceTimer = setInterval(() => {
      if (!this.destroy$.closed) this.loadMaintenanceIndicator();
    }, 300000);
  }

  /** Stoppa alla polling-timers */
  private stopPollingTimers() {
    clearInterval(this.systemStatusInterval);
    clearInterval(this.todaySnapshotInterval);
    clearInterval(this.maintenanceTimer);
    this.systemStatusInterval = null;
    this.todaySnapshotInterval = null;
    this.maintenanceTimer = null;
  }

  /** Pausa polling när tabben är dold, återuppta när synlig */
  private onVisibilityChange() {
    if (document.hidden) {
      this.stopPollingTimers();
    } else {
      // Hämta färsk data direkt vid återkomst
      this.loadSystemStatus();
      this.loadTodaySnapshot();
      this.loadMaintenanceIndicator();
      this.startPollingTimers();
    }
  }

  ngAfterViewInit() {
    // Rita om grafen om data redan är inläst
    if (this.maintenanceData) {
      this.renderMaintenanceChart();
    }
  }

  // ========== Inställningar ==========
  loadSettings() {
    this.settingsLoading = true;
    this.settingsError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=admin-settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.settings.rebotlingTarget = res.data.rebotlingTarget;
            this.settings.hourlyTarget    = res.data.hourlyTarget;
            this.settings.shiftHours      = res.data.shiftHours;
            this.settings.minOperators    = res.data.minOperators ?? 2;
            if (res.data.systemSettings) {
              this.settings.systemSettings = { ...res.data.systemSettings };
            }
          }
          this.settingsLoading = false;
        },
        error: () => {
          this.settingsError   = 'Kunde inte ladda inställningar';
          this.settingsLoading = false;
        }
      });
  }

  saveSettings() {
    this.settingsSaving = true;
    this.settingsError  = '';
    this.settingsSaved  = false;

    // Validera att nyckeltal inte är negativa/noll
    if (!this.settings.rebotlingTarget || this.settings.rebotlingTarget < 1) {
      this.settingsError = 'Dagsmål måste vara minst 1';
      this.settingsSaving = false;
      return;
    }
    if (!this.settings.hourlyTarget || this.settings.hourlyTarget < 1) {
      this.settingsError = 'Timmål måste vara minst 1';
      this.settingsSaving = false;
      return;
    }
    if (!this.settings.shiftHours || this.settings.shiftHours < 1 || this.settings.shiftHours > 24) {
      this.settingsError = 'Skiftlängd måste vara 1–24 timmar';
      this.settingsSaving = false;
      return;
    }

    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=admin-settings', this.settings, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.settingsSaved = true;
            this.showSuccess('Inställningar sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.settingsSaved = false; }, 3000);
          } else {
            this.settingsError = res.error || 'Kunde inte spara inställningar';
          }
          this.settingsSaving = false;
        },
        error: () => {
          this.settingsError  = 'Serverfel vid sparning';
          this.settingsSaving = false;
        }
      });
  }

  // ========== Veckodagsmål ==========
  loadWeekdayGoals() {
    this.weekdayLoading = true;
    this.weekdayError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=weekday-goals', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.weekdayGoals = res.data;
          }
          this.weekdayLoading = false;
        },
        error: () => {
          this.weekdayError   = 'Kunde inte ladda veckodagsmål';
          this.weekdayLoading = false;
        }
      });
  }

  saveWeekdayGoals() {
    this.weekdaySaving = true;
    this.weekdayError  = '';
    this.weekdaySaved  = false;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=weekday-goals',
      { goals: this.weekdayGoals }, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.weekdaySaved = true;
            this.showSuccess('Veckodagsmål sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.weekdaySaved = false; }, 3000);
          } else {
            this.weekdayError = res.error || 'Kunde inte spara';
          }
          this.weekdaySaving = false;
        },
        error: () => {
          this.weekdayError  = 'Serverfel vid sparning';
          this.weekdaySaving = false;
        }
      });
  }

  /** Kopiera mån-fre-snittet till helgen (lör+sön) */
  copyWeekdayToWeekend() {
    const weekdays = this.weekdayGoals.filter(g => g.weekday >= 1 && g.weekday <= 5);
    if (!weekdays.length) return;
    const avg = Math.round(weekdays.reduce((s, g) => s + g.daily_goal, 0) / weekdays.length);
    this.weekdayGoals.forEach(g => {
      if (g.weekday === 6 || g.weekday === 7) g.daily_goal = avg;
    });
  }

  /** Sätt alla veckodagsmål till quickSetValue */
  setAllWeekdayGoals() {
    const val = Math.max(0, Math.round(this.quickSetValue || 0));
    this.weekdayGoals.forEach(g => g.daily_goal = val);
  }

  /** Returnerar true om veckodagets weekday är idag */
  isToday(weekday: number): boolean {
    return weekday === this.todayWeekday;
  }

  /** Returnerar 'on-goal'|'below-goal'|null beroende på om snapshot finns */
  weekdayGoalStatus(goal: number): 'on-goal' | 'below-goal' | null {
    if (!this.todaySnapshot) return null;
    if (goal === 0) return null;
    const pct = this.todaySnapshot.pct_of_goal;
    return pct >= 100 ? 'on-goal' : 'below-goal';
  }

  // ========== Skifttider ==========
  loadShiftTimes() {
    this.shiftTimesLoading = true;
    this.shiftTimesError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=shift-times', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success && res.data) {
            this.shiftTimes = res.data.map((s: any) => ({
              ...s,
              enabled: s.enabled == 1 || s.enabled === true
            }));
          }
          this.shiftTimesLoading = false;
        },
        error: () => {
          this.shiftTimesError   = 'Kunde inte ladda skifttider';
          this.shiftTimesLoading = false;
        }
      });
  }

  saveShiftTimes() {
    this.shiftTimesSaving = true;
    this.shiftTimesError  = '';
    this.shiftTimesSaved  = false;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=shift-times',
      { shifts: this.shiftTimes }, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.shiftTimesSaved = true;
            this.showSuccess('Skifttider sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.shiftTimesSaved = false; }, 3000);
          } else {
            this.shiftTimesError = res.error || 'Kunde inte spara';
          }
          this.shiftTimesSaving = false;
        },
        error: () => {
          this.shiftTimesError  = 'Serverfel vid sparning';
          this.shiftTimesSaving = false;
        }
      });
  }

  getShiftLabel(name: string): string {
    const map: { [k: string]: string } = {
      'förmiddag': 'Förmiddag',
      'eftermiddag': 'Eftermiddag',
      'natt': 'Natt'
    };
    return map[name] || name;
  }

  // ========== Systemstatus ==========
  loadSystemStatus() {
    if (this.systemStatusLoading) return;
    this.systemStatusLoading = true;
    this.systemStatusError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=system-status', { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.systemStatus = res.data;
            this.systemStatusLastUpdated = new Date();
            this.systemStatusError = '';
          } else if (!this.systemStatus) {
            this.systemStatusError = res?.error || 'Kunde inte ladda systemstatus';
          }
          this.systemStatusLoading = false;
        },
        error: () => {
          this.systemStatusError   = 'Kunde inte nå servern';
          this.systemStatusLoading = false;
        }
      });
  }

  getPlcAge(): string {
    if (!this.systemStatus?.last_plc_ping) return 'Ingen data';
    const last = new Date(this.systemStatus.last_plc_ping);
    const now  = new Date();
    const diffSec = Math.floor((now.getTime() - last.getTime()) / 1000);
    if (diffSec < 60)       return `${diffSec} sek sedan`;
    if (diffSec < 3600)     return `${Math.floor(diffSec / 60)} min sedan`;
    if (diffSec < 86400)    return `${Math.floor(diffSec / 3600)} tim sedan`;
    return `${Math.floor(diffSec / 86400)} dag sedan`;
  }

  getPlcStatus(): 'ok' | 'warn' | 'err' {
    if (!this.systemStatus?.last_plc_ping) return 'err';
    const last    = new Date(this.systemStatus.last_plc_ping);
    const diffSec = (new Date().getTime() - last.getTime()) / 1000;
    if (diffSec < 300)  return 'ok';
    if (diffSec < 1800) return 'warn';
    return 'err';
  }

  /**
   * PLC-varningsnivå baserat på senaste ping-tid:
   * - 'none'  : < 5 min    (allt OK)
   * - 'warn'  : 5–15 min   (gul varning)
   * - 'danger': > 15 min   (röd varning)
   */
  get plcWarningLevel(): 'none' | 'warn' | 'danger' {
    if (!this.systemStatus?.last_plc_ping) return 'danger';
    const diffSec = (new Date().getTime() - new Date(this.systemStatus.last_plc_ping).getTime()) / 1000;
    if (diffSec < 300)  return 'none';
    if (diffSec < 900)  return 'warn';
    return 'danger';
  }

  get plcMinutesOld(): number {
    if (!this.systemStatus?.last_plc_ping) return 0;
    return Math.floor((new Date().getTime() - new Date(this.systemStatus.last_plc_ping).getTime()) / 60000);
  }

  // ========== Produkthantering ==========
  private loadProducts() {
    this.loading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotlingproduct', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.products = response.data.map((product: any) => ({
              ...product,
              editing: false,
              originalName: product.name,
              originalCycleTime: product.cycle_time_minutes
            }));
          } else {
            console.error('Kunde inte ladda produkter:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid laddning av produkter:', error);
          this.loading = false;
        }
      });
  }

  addProduct() {
    if (!this.newProduct.name || !this.newProduct.cycle_time_minutes) return;
    this.loading = true;
    this.http.post<any>('/noreko-backend/api.php?action=rebotlingproduct', this.newProduct, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.loadProducts();
            this.newProduct = { name: '', cycle_time_minutes: null };
            this.showAddProductForm = false;
            this.showSuccess('Produkt tillagd!');
          } else {
            console.error('Kunde inte lägga till produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid tillägg av produkt:', error);
          this.loading = false;
        }
      });
  }

  editProduct(product: any) {
    this.products.forEach(p => {
      if (p.id !== product.id) {
        p.editing  = false;
        p.name     = p.originalName;
        p.cycle_time_minutes = p.originalCycleTime;
      }
    });
    product.editing           = true;
    product.originalName      = product.name;
    product.originalCycleTime = product.cycle_time_minutes;
  }

  saveProduct(product: any) {
    if (!product.name || !product.cycle_time_minutes) return;
    this.loading = true;
    const updateData = { id: product.id, name: product.name, cycle_time_minutes: product.cycle_time_minutes };
    this.http.put<any>('/noreko-backend/api.php?action=rebotlingproduct', updateData, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            product.editing           = false;
            product.originalName      = product.name;
            product.originalCycleTime = product.cycle_time_minutes;
            this.showSuccess('Produkt uppdaterad!');
          } else {
            console.error('Kunde inte uppdatera produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid uppdatering av produkt:', error);
          this.loading = false;
        }
      });
  }

  cancelEdit(product: any) {
    product.editing              = false;
    product.name                 = product.originalName;
    product.cycle_time_minutes   = product.originalCycleTime;
  }

  deleteProduct(product: any) {
    if (!confirm(`Är du säker på att du vill ta bort produkten "${product.name}"?`)) return;
    this.loading = true;
    this.http.post<any>('/noreko-backend/api.php?action=rebotlingproduct&run=delete', { id: product.id }, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.loadProducts();
            this.showSuccess('Produkt borttagen!');
          } else {
            console.error('Kunde inte ta bort produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid borttagning av produkt:', error);
          this.loading = false;
        }
      });
  }

  trackByProductId(index: number, product: any): number {
    return product.id;
  }

  // ========== Underhållsindikator ==========
  loadMaintenanceIndicator() {
    if (this.maintenanceLoading) return;
    this.maintenanceLoading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=maintenance-indicator', { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of({ success: false, error: 'Timeout eller serverfel' })),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.maintenanceData   = res;
            this.maintenanceStatus = res.status as 'ok' | 'warning' | 'danger';
            setTimeout(() => this.renderMaintenanceChart(), 0);
          }
          this.maintenanceLoading = false;
        },
        error: () => {
          this.maintenanceLoading = false;
        }
      });
  }

  renderMaintenanceChart() {
    const canvas = document.getElementById('maintenanceChart') as HTMLCanvasElement | null;
    if (!canvas || !this.maintenanceData?.weeks?.length) return;

    try { this.maintenanceChart?.destroy(); } catch (e) {}

    const weeks     = this.maintenanceData.weeks as any[];
    const labels    = weeks.map((w: any) => w.week_label);
    const cycleTimes = weeks.map((w: any) => w.avg_cycle_time);
    const baseline  = this.maintenanceData.baseline_cycle_time;

    const baselineData = baseline != null ? weeks.map(() => baseline) : [];

    this.maintenanceChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Cykeltid (min/IBC)',
            data: cycleTimes,
            borderColor: '#ed8936',
            backgroundColor: 'rgba(237,137,54,0.15)',
            borderWidth: 2,
            pointRadius: 4,
            tension: 0.3,
            fill: true,
          },
          ...(baseline != null ? [{
            label: 'Baslinje',
            data: baselineData,
            borderColor: '#48bb78',
            borderDash: [6, 4],
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
          }] : []),
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            callbacks: {
              label: (ctx) => { const v = ctx.parsed.y; return v != null ? `${ctx.dataset.label}: ${v} min/IBC` : ''; }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.06)' },
          },
          y: {
            title: {
              display: true,
              text: 'Minuter per IBC',
              color: '#a0aec0',
            },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.06)' },
          },
        },
      },
    });
  }

  // ========== Logga underhåll ==========
  saveMaintenanceLog() {
    const text = (this.maintenanceLogText || '').trim();
    if (!text) {
      this.maintenanceLogError = 'Ange en beskrivning av åtgärden';
      return;
    }
    this.maintenanceLogSaving = true;
    this.maintenanceLogError  = '';
    this.maintenanceLogSaved  = false;
    this.http.post<any>(
      '/noreko-backend/api.php?action=rebotling&run=save-maintenance-log',
      { action_text: text },
      { withCredentials: true }
    )
    .pipe(
      timeout(8000),
      catchError(() => of({ success: false, error: 'Nätverksfel' })),
      takeUntil(this.destroy$)
    )
    .subscribe((res: any) => {
      this.maintenanceLogSaving = false;
      if (res?.success) {
        this.maintenanceLogSaved  = true;
        this.maintenanceLogText   = '';
        this.showMaintenanceLogForm = false;
        this.showSuccess('Underhållsåtgärd loggad!');
        setTimeout(() => { if (!this.destroy$.closed) this.maintenanceLogSaved = false; }, 4000);
      } else {
        this.maintenanceLogError = res?.error || 'Kunde inte spara underhållslogg';
      }
    });
  }

  // ========== Snabb produktionsöversikt idag ==========
  loadTodaySnapshot() {
    if (this.todaySnapshotLoading) return;
    this.todaySnapshotLoading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=today-snapshot', { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) this.todaySnapshot = res.data;
          this.todaySnapshotLoading = false;
        },
        error: () => { this.todaySnapshotLoading = false; }
      });
  }

  get snapshotColorClass(): string {
    if (!this.todaySnapshot) return 'text-secondary';
    const pct = this.todaySnapshot.pct_of_goal;
    if (pct >= 100) return 'text-success';
    if (pct >= 80)  return 'text-warning';
    return 'text-danger';
  }

  get snapshotBorderClass(): string {
    if (!this.todaySnapshot) return '';
    const pct = this.todaySnapshot.pct_of_goal;
    if (pct >= 100) return 'snapshot-green';
    if (pct >= 80)  return 'snapshot-orange';
    return 'snapshot-red';
  }

  // ========== Alert-trösklar ==========
  loadAlertThresholds() {
    this.alertThresholdsLoading = true;
    this.alertThresholdsError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=alert-thresholds', { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success && res.data) {
            this.alertThresholds = { ...this.alertThresholds, ...res.data };
          }
          this.alertThresholdsLoading = false;
        },
        error: () => { this.alertThresholdsLoading = false; }
      });
  }

  saveAlertThresholds() {
    this.alertThresholdsSaving = true;
    this.alertThresholdsSaved  = false;
    this.alertThresholdsError  = '';
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=save-alert-thresholds',
      this.alertThresholds, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.alertThresholdsSaved = true;
            this.showSuccess('Alert-trösklar sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.alertThresholdsSaved = false; }, 3000);
          } else {
            this.alertThresholdsError = res?.error || 'Kunde inte spara trösklar';
          }
          this.alertThresholdsSaving = false;
        },
        error: () => {
          this.alertThresholdsError = 'Serverfel vid sparning';
          this.alertThresholdsSaving = false;
        }
      });
  }

  // ========== E-postnotifikationer ==========
  loadNotificationSettings() {
    this.notificationSettingsLoading = true;
    this.notificationSettingsError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=notification-settings', { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success && res.data) {
            this.notificationSettings.notification_emails = res.data.notification_emails || '';
            if (res.data.config) {
              this.notificationSettings.config = { ...this.notificationSettings.config, ...res.data.config };
            }
          } else if (!res?.success) {
            this.notificationSettingsError = 'Kunde inte ladda notifikationsinställningar';
          }
          this.notificationSettingsLoading = false;
        },
        error: () => {
          this.notificationSettingsError   = 'Serverfel vid hämtning';
          this.notificationSettingsLoading = false;
        }
      });
  }

  saveNotificationSettings() {
    this.notificationSettingsSaving = true;
    this.notificationSettingsError  = '';
    this.notificationSettingsSaved  = false;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=save-notification-settings',
      this.notificationSettings, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.notificationSettingsSaved = true;
            this.showSuccess('Notifikationsinställningar sparade!');
            setTimeout(() => { if (!this.destroy$.closed) this.notificationSettingsSaved = false; }, 3000);
          } else {
            this.notificationSettingsError = res?.error || 'Kunde inte spara inställningar';
          }
          this.notificationSettingsSaving = false;
        },
        error: () => {
          this.notificationSettingsError  = 'Serverfel vid sparning';
          this.notificationSettingsSaving = false;
        }
      });
  }

  // ========== Dagsmål-historik ==========
  changeGoalHistoryPeriod(days: number) {
    this.goalHistoryPeriod = days;
    this.loadGoalHistory();
  }

  loadGoalHistory() {
    this.goalHistoryLoading = true;
    this.http.get<any>(`/noreko-backend/api.php?action=rebotling&run=goal-history&days=${this.goalHistoryPeriod}`, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe(res => {
        this.goalHistoryLoading = false;
        if (res?.success) {
          this.goalHistory = res.data;
          setTimeout(() => this.buildGoalHistoryChart(), 100);
        }
      });
  }

  buildGoalHistoryChart() {
    const canvas = document.getElementById('goalHistoryChart') as HTMLCanvasElement | null;
    if (!canvas || this.goalHistory.length < 1) return;

    try { this.goalHistoryChart?.destroy(); } catch (e) {}

    // Bygg steg-data: varje ändring gäller tills nästa ändring
    const labels = this.goalHistory.map((h: any) => {
      const d = new Date(h.changed_at);
      return d.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric', year: '2-digit' });
    });
    const values = this.goalHistory.map((h: any) => h.value);

    // Nuvarande mål = senaste värdet
    const currentGoal = values.length > 0 ? values[values.length - 1] : null;

    // Markera nuvarande mål med en horisontell linje (annotation via extra dataset)
    const datasets: any[] = [{
      label: 'Dagsmål (IBC/dag)',
      data: values,
      borderColor: '#f6ad55',
      backgroundColor: 'rgba(246,173,85,0.12)',
      borderWidth: 2,
      pointRadius: 5,
      pointBackgroundColor: '#f6ad55',
      stepped: true,
      fill: true,
    }];

    // Lägg till en horisontell referenslinje för nuvarande mål
    if (currentGoal !== null && values.length > 1) {
      datasets.push({
        label: `Nuvarande mål: ${currentGoal} IBC`,
        data: labels.map(() => currentGoal),
        borderColor: '#63b3ed',
        borderWidth: 1,
        borderDash: [6, 4],
        pointRadius: 0,
        fill: false,
      });
    }

    this.goalHistoryChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.parsed.y;
                if (v == null) return '';
                if (ctx.datasetIndex === 1) return `Nuvarande mål: ${v} IBC/dag`;
                return `Mål: ${v} IBC/dag`;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 10 },
            grid: { color: 'rgba(255,255,255,0.06)' }
          },
          y: {
            title: { display: true, text: 'IBC per dag', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.06)' },
            beginAtZero: false
          }
        }
      }
    });
  }

  // ========== Live Ranking TV-inställningar ==========
  toggleLrPanel() { this.showLrPanel = !this.showLrPanel; }

  loadLrSettings() {
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=live-ranking-settings', { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) this.lrSettings = res.data;
      });
  }

  saveLrSettings() {
    this.lrSettingsSaving = true;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=save-live-ranking-settings',
      this.lrSettings, { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        this.lrSettingsSaving = false;
        if (res?.success) this.showSuccess('TV-inställningar sparade!');
      });
  }

  // ========== Live Ranking Config (KPI-kolumner, sortering) ==========
  toggleLrConfigPanel() { this.showLrConfigPanel = !this.showLrConfigPanel; }

  loadLrConfig() {
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=live-ranking-config', { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) this.lrConfig = res.data;
      });
  }

  saveLrConfig() {
    this.lrConfigSaving = true;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=set-live-ranking-config',
      this.lrConfig, { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        this.lrConfigSaving = false;
        if (res?.success) this.showSuccess('Live Ranking-konfiguration sparad!');
      });
  }

  // ========== Rekordnyhet (manuell trigger) ==========
  recordNewsCreating = false;
  recordNewsResult: { success: boolean; message: string; ibc_idag?: number; rekord_ibc?: number } | null = null;

  createRecordNews(): void {
    this.recordNewsCreating = true;
    this.recordNewsResult = null;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=create-record-news', {},
      { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(10000),
        catchError(() => of({ success: false, error: 'Nätverksfel' }))
      )
      .subscribe((res: any) => {
        this.recordNewsCreating = false;
        if (res?.success) {
          this.recordNewsResult = {
            success: true,
            message: 'Rekordnyhet skapad! Idag: ' + res.ibc_idag + ' IBC (rekord: ' + res.rekord_ibc + ' IBC)',
            ibc_idag: res.ibc_idag,
            rekord_ibc: res.rekord_ibc
          };
          this.showSuccess('Rekordnyhet skapad!');
        } else {
          this.recordNewsResult = {
            success: false,
            message: res?.error || 'Kunde inte skapa rekordnyhet'
          };
        }
      });
  }

  // ========== Kassationsregistrering ==========
  kassationTyper: any[] = [];
  kassationSenaste: any[] = [];
  kassationLoading = false;
  kassationError   = '';
  kassationSaved   = false;
  kassationForm = {
    orsak_id:    0,
    antal:       1,
    datum:       localToday(),
    kommentar:   ''
  };

  loadKassationTyper() {
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=kassation-typer', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe((res: any) => {
        if (res?.success) {
          this.kassationTyper = res.data || [];
          if (this.kassationTyper.length > 0 && !this.kassationForm.orsak_id) {
            this.kassationForm.orsak_id = this.kassationTyper[0].id;
          }
        }
      });
  }

  loadKassationSenaste() {
    this.kassationLoading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=kassation-senaste&limit=10', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe((res: any) => {
        this.kassationLoading = false;
        if (res?.success) this.kassationSenaste = res.data || [];
      });
  }

  registerKassation() {
    if (!this.kassationForm.orsak_id || this.kassationForm.antal < 1) {
      this.kassationError = 'Fyll i orsak och antal';
      return;
    }
    this.kassationLoading = true;
    this.kassationError   = '';
    this.kassationSaved   = false;
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=kassation-register',
      this.kassationForm, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe((res: any) => {
        this.kassationLoading = false;
        if (res?.success) {
          this.kassationSaved    = true;
          this.kassationForm.antal     = 1;
          this.kassationForm.kommentar = '';
          this.kassationForm.datum     = localToday();
          this.showSuccess('Kassation registrerad!');
          this.loadKassationSenaste();
          setTimeout(() => { if (!this.destroy$.closed) this.kassationSaved = false; }, 3000);
        } else {
          this.kassationError = res?.error || 'Kunde inte registrera kassation';
        }
      });
  }

  // ========== Anpassade dagsmål (datum-undantag) ==========
  loadGoalExceptions() {
    this.goalExceptionsLoading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=goal-exceptions', { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        this.goalExceptionsLoading = false;
        if (res?.success) {
          this.goalExceptions = res.exceptions || [];
        }
      });
  }

  saveGoalException() {
    if (!this.newExceptionDatum || this.newExceptionMal <= 0) {
      this.exceptionSaveMsg = 'Fyll i datum och mål (> 0)';
      return;
    }
    this.savingException = true;
    this.exceptionSaveMsg = '';
    const body = {
      datum: this.newExceptionDatum,
      justerat_mal: this.newExceptionMal,
      orsak: this.newExceptionOrsak
    };
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=save-goal-exception', body, { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        this.savingException = false;
        if (res?.success) {
          this.exceptionSaveMsg = 'Undantag sparat!';
          this.newExceptionMal = 0;
          this.newExceptionOrsak = '';
          this.newExceptionDatum = localToday();
          this.loadGoalExceptions();
          this.showSuccess('Undantag sparat!');
          setTimeout(() => { if (!this.destroy$.closed) this.exceptionSaveMsg = ''; }, 3000);
        } else {
          this.exceptionSaveMsg = res?.error || 'Kunde inte spara undantag';
        }
      });
  }

  deleteGoalException(datum: string) {
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=delete-goal-exception',
      { datum }, { withCredentials: true })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        if (res?.success) {
          this.loadGoalExceptions();
          this.showSuccess('Undantag borttaget');
        }
      });
  }

  // ========== Prediktivt underhåll — Serviceintervall ==========
  loadServiceStatus(): void {
    this.serviceStatusLoading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=service-status', { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.serviceStatusLoading = false;
        if (res?.success) {
          this.serviceStatus = res as ServiceStatus;
          this.serviceInterval = res.service_interval || 5000;
        }
      });
  }

  resetService(): void {
    if (!confirm('Registrera service utförd? IBC-räknaren nollställs.')) return;
    this.savingServiceReset = true;
    this.serviceResetMsg = '';
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=reset-service',
      { note: this.serviceNote }, { withCredentials: true })
      .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.savingServiceReset = false;
        if (res?.success) {
          this.serviceResetMsg = 'Service registrerad!';
          this.serviceNote = '';
          this.loadServiceStatus();
          setTimeout(() => { if (!this.destroy$.closed) this.serviceResetMsg = ''; }, 3000);
        } else {
          this.serviceResetMsg = 'Fel vid registrering av service.';
        }
      });
  }

  saveServiceInterval(): void {
    this.savingServiceInterval = true;
    this.serviceIntervalError = '';
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=save-service-interval',
      { service_interval_ibc: this.serviceInterval }, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.savingServiceInterval = false;
        if (res?.success) {
          this.loadServiceStatus();
          this.showSuccess('Service-intervall sparat!');
        } else {
          this.serviceIntervalError = res?.error || 'Kunde inte spara intervall.';
        }
      });
  }

  // ========== Korrelationsanalys underhåll vs stopp ==========
  loadMaintenanceCorrelation() {
    this.correlationLoading = true;
    this.correlationError = '';
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=maintenance-correlation&weeks=12', { withCredentials: true })
      .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.correlationLoading = false;
        if (res?.success) {
          this.correlationData = res;
          setTimeout(() => this.renderCorrelationChart(), 100);
        } else {
          this.correlationError = res?.error || 'Kunde inte ladda korrelationsdata';
        }
      });
  }

  renderCorrelationChart() {
    const canvas = document.getElementById('correlationChart') as HTMLCanvasElement | null;
    if (!canvas || !this.correlationData?.series?.length) return;

    try { this.correlationChart?.destroy(); } catch (e) {}

    const series = this.correlationData.series as any[];
    const labels = series.map((s: any) => s.vecka);
    const stoppData = series.map((s: any) => s.antal_stopp);
    const underhallData = series.map((s: any) => s.antal_underhall);

    this.correlationChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Maskinstopp',
            data: stoppData,
            backgroundColor: 'rgba(229, 62, 62, 0.7)',
            borderColor: '#e53e3e',
            borderWidth: 1,
            yAxisID: 'y',
            order: 2,
          },
          {
            label: 'Underhåll',
            data: underhallData,
            type: 'line',
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66, 153, 225, 0.15)',
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#4299e1',
            tension: 0.3,
            fill: true,
            yAxisID: 'y1',
            order: 1,
          },
        ],
      },
      options: {
        responsive: true,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => { const v = ctx.parsed.y; return v != null ? `${ctx.dataset.label}: ${v}` : ''; }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.06)' },
          },
          y: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'Antal maskinstopp', color: '#e53e3e' },
            ticks: { color: '#e53e3e' },
            grid: { color: 'rgba(255,255,255,0.06)' },
            beginAtZero: true,
          },
          y1: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'Underhållshändelser', color: '#4299e1' },
            ticks: { color: '#4299e1' },
            grid: { drawOnChartArea: false },
            beginAtZero: true,
          },
        },
      },
    });
  }

  get correlationLabel(): string {
    if (!this.correlationData?.kpi) return '';
    const k = this.correlationData.kpi.korrelation;
    if (k === null) return 'Otillräcklig data';
    if (k < -0.5) return 'Stark negativ korrelation — underhåll minskar stopp';
    if (k < -0.2) return 'Svag negativ korrelation — underhåll verkar hjälpa';
    if (k > 0.5) return 'Stark positiv korrelation — mer underhåll sammanfaller med mer stopp (reaktivt underhåll?)';
    if (k > 0.2) return 'Svag positiv korrelation';
    return 'Ingen tydlig korrelation';
  }

  get correlationColor(): string {
    if (!this.correlationData?.kpi) return '#a0aec0';
    const k = this.correlationData.kpi.korrelation;
    if (k === null) return '#a0aec0';
    if (k < -0.3) return '#38a169';  // green — underhåll hjälper
    if (k > 0.3) return '#e53e3e';   // red — reactive pattern
    return '#d69e2e';                 // yellow — neutral
  }

  // ========== Automatisk skiftrapport via email ==========
  sendTestShiftReport() {
    this.shiftReportSending = true;
    this.shiftReportError   = '';
    this.shiftReportSuccess = '';
    this.http.post<any>('/noreko-backend/api.php?action=rebotling&run=auto-shift-report', {
      date: this.shiftReportTestDate,
      shift: this.shiftReportTestShift
    }, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            const count = res.recipients?.length ?? 0;
            this.shiftReportSuccess = `Skiftrapport skickad till ${count} mottagare (${res.shift_date}, skift ${res.shift_number})`;
            this.showSuccess(this.shiftReportSuccess);
          } else {
            this.shiftReportError = res?.error || 'Kunde inte skicka skiftrapport';
          }
          this.shiftReportSending = false;
        },
        error: () => {
          this.shiftReportError   = 'Serverfel vid sändning';
          this.shiftReportSending = false;
        }
      });
  }

  private showSuccess(message: string) {
    this.successMessage     = message;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
}
