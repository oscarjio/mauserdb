import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { ComponentCanDeactivate } from '../../guards/pending-changes.guard';
import { localToday, parseLocalDate } from '../../utils/date-utils';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

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
export class RebotlingAdminPage implements OnInit, OnDestroy, AfterViewInit, ComponentCanDeactivate {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;
  formDirty = false;

  canDeactivate(): boolean {
    return !this.formDirty;
  }

  // ---- Produkthantering ----
  products: any[] = [];
  newProduct: any = { name: '', cycle_time_minutes: null, has_lopnummer: true };
  loading = false;
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;
  private _feedbackTimers: any[] = [];
  showAddProductForm = false;

  // ---- Admin-inställningar (befintliga) ----
  settings = {
    hourlyTarget: 50,
    shiftHours: 8.0,
    minOperators: 2,
    systemSettings: { autoStart: false, maintenanceMode: false, alertThreshold: 80 }
  };
  settingsLoading = false;
  settingsSaving  = false;
  settingsError   = '';
  settingsSaved   = false;

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

  // ---- Visibilitychange-guard ----
  private visibilityHandler = () => this.onVisibilityChange();

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user    = val;
      this.isAdmin = val?.role === 'admin' || val?.role === 'developer';
    });
  }

  ngOnDestroy() {
    document.removeEventListener('visibilitychange', this.visibilityHandler);
    clearTimeout(this.successTimerId);
    this._feedbackTimers.forEach(t => clearTimeout(t));
    this._feedbackTimers = [];
    clearInterval(this.systemStatusInterval);
    clearInterval(this.maintenanceTimer);
    clearInterval(this.todaySnapshotInterval);
    try { this.maintenanceChart?.destroy(); } catch (e) {}
    this.maintenanceChart = null;
    try { this.correlationChart?.destroy(); } catch (e) {}
    this.correlationChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  ngOnInit() {
    document.addEventListener('visibilitychange', this.visibilityHandler);

    this.loadProducts();
    this.loadSettings();
    this.loadShiftTimes();
    this.loadSystemStatus();
    this.loadTodaySnapshot();
    this.loadAlertThresholds();
    this.loadNotificationSettings();
    this.loadLrSettings();
    this.loadLrConfig();
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
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=admin-settings`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success && res.data) {
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

    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=admin-settings`, this.settings, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.settingsSaved = true;
            this.showSuccess('Inställningar sparade!');
            this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.settingsSaved = false; }, 3000));
          } else {
            this.settingsError = res?.error || 'Kunde inte spara inställningar';
          }
          this.settingsSaving = false;
        },
        error: () => {
          this.settingsError  = 'Serverfel vid sparning';
          this.settingsSaving = false;
        }
      });
  }

  // ========== Skifttider ==========
  loadShiftTimes() {
    this.shiftTimesLoading = true;
    this.shiftTimesError   = '';
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=shift-times`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success && res.data) {
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=shift-times`,
      { shifts: this.shiftTimes }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.shiftTimesSaved = true;
            this.showSuccess('Skifttider sparade!');
            this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.shiftTimesSaved = false; }, 3000));
          } else {
            this.shiftTimesError = res?.error || 'Kunde inte spara';
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
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=system-status`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
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
    const last = parseLocalDate(this.systemStatus.last_plc_ping);
    const now  = new Date();
    const diffSec = Math.floor((now.getTime() - last.getTime()) / 1000);
    if (diffSec < 60)       return `${diffSec} sek sedan`;
    if (diffSec < 3600)     return `${Math.floor(diffSec / 60)} min sedan`;
    if (diffSec < 86400)    return `${Math.floor(diffSec / 3600)} tim sedan`;
    return `${Math.floor(diffSec / 86400)} dag sedan`;
  }

  getPlcStatus(): 'ok' | 'warn' | 'err' {
    if (!this.systemStatus?.last_plc_ping) return 'err';
    const last    = parseLocalDate(this.systemStatus.last_plc_ping);
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
    const diffSec = (new Date().getTime() - parseLocalDate(this.systemStatus.last_plc_ping).getTime()) / 1000;
    if (diffSec < 300)  return 'none';
    if (diffSec < 900)  return 'warn';
    return 'danger';
  }

  get plcMinutesOld(): number {
    if (!this.systemStatus?.last_plc_ping) return 0;
    return Math.floor((new Date().getTime() - parseLocalDate(this.systemStatus.last_plc_ping).getTime()) / 60000);
  }

  // ========== Produkthantering ==========
  private loadProducts() {
    this.loading = true;
    this.http.get<any>(`${environment.apiUrl}?action=rebotlingproduct`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response?.success) {
            this.products = response.data.map((product: any) => ({
              ...product,
              has_lopnummer: product.has_lopnummer == 1 || product.has_lopnummer === true,
              editing: false,
              originalName: product.name,
              originalCycleTime: product.cycle_time_minutes,
              originalHasLopnummer: product.has_lopnummer == 1 || product.has_lopnummer === true
            }));
          }
          this.loading = false;
        },
        error: () => {
          this.loading = false;
        }
      });
  }

  /**
   * N-bug (Oscar 2026-10-22): cykeltidsfältet är nu type="text" (en number-input
   * accepterar inte svenskt decimalkomma tillförlitligt) — normalisera till ett rent
   * tal med 2 decimaler innan det skickas till backend. Accepterar både "3,40" och
   * "3.40". rebotling_products.cycle_time_minutes är FLOAT (Oscar ändrat i DB) —
   * backend avrundar också vid läsning/sparning (ROUND i SQL + round() i PHP), men
   * normaliseringen görs redan här så aldrig ett obehandlat float-brusigt värde
   * (t.ex. 3.2000000476) skickas över huvud taget.
   */
  private parseCykeltid(v: any): number {
    const s = String(v ?? '').trim().replace(',', '.');
    const n = parseFloat(s);
    return isFinite(n) ? Math.round(n * 100) / 100 : 0;
  }

  addProduct() {
    if (!this.newProduct.name || !this.newProduct.cycle_time_minutes) return;
    this.newProduct.cycle_time_minutes = this.parseCykeltid(this.newProduct.cycle_time_minutes);
    this.loading = true;
    this.http.post<any>(`${environment.apiUrl}?action=rebotlingproduct`, this.newProduct, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response?.success) {
            this.loadProducts();
            this.newProduct = { name: '', cycle_time_minutes: null, has_lopnummer: true };
            this.showAddProductForm = false;
            this.showSuccess('Produkt tillagd!');
          }
          this.loading = false;
        },
        error: () => {
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
    product.cycle_time_minutes = this.parseCykeltid(product.cycle_time_minutes);
    this.loading = true;
    const updateData = { id: product.id, name: product.name, cycle_time_minutes: product.cycle_time_minutes, has_lopnummer: product.has_lopnummer ? 1 : 0 };
    this.http.put<any>(`${environment.apiUrl}?action=rebotlingproduct`, updateData, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response?.success) {
            product.editing           = false;
            product.originalName      = product.name;
            product.originalCycleTime = product.cycle_time_minutes;
            this.showSuccess('Produkt uppdaterad!');
          }
          this.loading = false;
        },
        error: () => {
          this.loading = false;
        }
      });
  }

  cancelEdit(product: any) {
    product.editing              = false;
    product.name                 = product.originalName;
    product.cycle_time_minutes   = product.originalCycleTime;
    product.has_lopnummer        = product.originalHasLopnummer;
  }

  /**
   * N-bug (Oscar 2026-10-20): produkter raderas ALDRIG (historisk data refererar
   * produkt-ID) — dölj/visa i stället för hård borttagning. Kräver migrationen
   * (dold-kolumnen); ger ett 409-fel med tydlig text om den inte körts ännu.
   */
  hideProduct(product: any) {
    if (!confirm(`Dölj produkten "${product.name}"? Den försvinner ur val för nya skift men historiska rader påverkas inte. Kan återaktiveras när som helst.`)) return;
    this.setProductHidden(product, true, 'Produkt dold');
  }

  showProduct(product: any) {
    this.setProductHidden(product, false, 'Produkt återaktiverad');
  }

  private setProductHidden(product: any, hidden: boolean, successMsg: string) {
    this.loading = true;
    const run = hidden ? 'hide' : 'show';
    this.http.post<any>(`${environment.apiUrl}?action=rebotlingproduct&run=${run}`, { id: product.id }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response?.success) {
            this.loadProducts();
            this.showSuccess(successMsg);
          } else if (response?.error) {
            alert(response.error);
          }
          this.loading = false;
        },
        error: () => {
          this.loading = false;
        }
      });
  }

  /** Aktiva produkter (dold=0 eller kolumnen saknas än). */
  get activeProducts(): any[] {
    return this.products.filter((p: any) => !p.dold);
  }

  /** Dolda produkter — egen lista under de aktiva, kan återaktiveras. */
  get hiddenProducts(): any[] {
    return this.products.filter((p: any) => !!p.dold);
  }

  trackByProductId(index: number, product: any): number {
    return product.id;
  }

  // ========== Underhållsindikator ==========
  loadMaintenanceIndicator() {
    if (this.maintenanceLoading) return;
    this.maintenanceLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=maintenance-indicator`, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of({ success: false, error: 'Timeout eller serverfel' })),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.maintenanceData   = res;
            this.maintenanceStatus = res.status as 'ok' | 'warning' | 'danger';
            this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderMaintenanceChart(); }, 0));
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

    if (this.maintenanceChart) { (this.maintenanceChart as any).destroy(); }
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
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
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
      `${environment.apiUrl}?action=rebotling&run=save-maintenance-log`,
      { action_text: text },
      { withCredentials: true }
    )
    .pipe(
      timeout(15000),
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
        this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.maintenanceLogSaved = false; }, 4000));
      } else {
        this.maintenanceLogError = res?.error || 'Kunde inte spara underhållslogg';
      }
    });
  }

  // ========== Snabb produktionsöversikt idag ==========
  loadTodaySnapshot() {
    if (this.todaySnapshotLoading) return;
    this.todaySnapshotLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=today-snapshot`, { withCredentials: true })
      .pipe(
        timeout(15000),
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

  // ========== Alert-trösklar ==========
  loadAlertThresholds() {
    this.alertThresholdsLoading = true;
    this.alertThresholdsError   = '';
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=alert-thresholds`, { withCredentials: true })
      .pipe(
        timeout(15000),
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=save-alert-thresholds`,
      this.alertThresholds, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.alertThresholdsSaved = true;
            this.showSuccess('Alert-trösklar sparade!');
            this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.alertThresholdsSaved = false; }, 3000));
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
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=notification-settings`, { withCredentials: true })
      .pipe(
        timeout(15000),
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=save-notification-settings`,
      this.notificationSettings, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.notificationSettingsSaved = true;
            this.showSuccess('Notifikationsinställningar sparade!');
            this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.notificationSettingsSaved = false; }, 3000));
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

  // ========== Live Ranking TV-inställningar ==========
  toggleLrPanel() { this.showLrPanel = !this.showLrPanel; }

  loadLrSettings() {
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=live-ranking-settings`, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)), takeUntil(this.destroy$)
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) this.lrSettings = res.data;
      });
  }

  saveLrSettings() {
    this.lrSettingsSaving = true;
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=save-live-ranking-settings`,
      this.lrSettings, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)), takeUntil(this.destroy$)
      )
      .subscribe((res: any) => {
        this.lrSettingsSaving = false;
        if (res?.success) this.showSuccess('TV-inställningar sparade!');
      });
  }

  // ========== Live Ranking Config (KPI-kolumner, sortering) ==========
  toggleLrConfigPanel() { this.showLrConfigPanel = !this.showLrConfigPanel; }

  loadLrConfig() {
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=live-ranking-config`, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)), takeUntil(this.destroy$)
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) this.lrConfig = res.data;
      });
  }

  saveLrConfig() {
    this.lrConfigSaving = true;
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=set-live-ranking-config`,
      this.lrConfig, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)), takeUntil(this.destroy$)
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=create-record-news`, {},
      { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of({ success: false, error: 'Nätverksfel' })), takeUntil(this.destroy$)
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
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=kassation-typer`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
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
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=kassation-senaste&limit=10`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=kassation-register`,
      this.kassationForm, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe((res: any) => {
        this.kassationLoading = false;
        if (res?.success) {
          this.kassationSaved    = true;
          this.kassationForm.antal     = 1;
          this.kassationForm.kommentar = '';
          this.kassationForm.datum     = localToday();
          this.showSuccess('Kassation registrerad!');
          this.loadKassationSenaste();
          this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.kassationSaved = false; }, 3000));
        } else {
          this.kassationError = res?.error || 'Kunde inte registrera kassation';
        }
      });
  }

  // ========== Prediktivt underhåll — Serviceintervall ==========
  loadServiceStatus(): void {
    this.serviceStatusLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=service-status`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=reset-service`,
      { note: this.serviceNote }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.savingServiceReset = false;
        if (res?.success) {
          this.serviceResetMsg = 'Service registrerad!';
          this.serviceNote = '';
          this.loadServiceStatus();
          this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.serviceResetMsg = ''; }, 3000));
        } else {
          this.serviceResetMsg = 'Fel vid registrering av service.';
        }
      });
  }

  saveServiceInterval(): void {
    this.savingServiceInterval = true;
    this.serviceIntervalError = '';
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=save-service-interval`,
      { service_interval_ibc: this.serviceInterval }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
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
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=maintenance-correlation&weeks=12`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.correlationLoading = false;
        if (res?.success) {
          this.correlationData = res;
          this._feedbackTimers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderCorrelationChart(); }, 100));
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
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
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
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=auto-shift-report`, {
      date: this.shiftReportTestDate,
      shift: this.shiftReportTestShift
    }, { withCredentials: true })
      .pipe(
        timeout(15000),
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
    this.formDirty = false;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
