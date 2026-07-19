import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { environment } from '../../../environments/environment';
import { ComponentCanDeactivate } from '../../guards/pending-changes.guard';

interface WeekdayGoal {
  weekday: number;
  mal: number;
  label: string;
}

@Component({
  standalone: true,
  selector: 'app-tvattlinje-admin',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './tvattlinje-admin.html',
  styleUrl: './tvattlinje-admin.css'
})
export class TvattlinjeAdminPage implements OnInit, OnDestroy, ComponentCanDeactivate {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;
  formDirty = false;

  canDeactivate(): boolean {
    return !this.formDirty;
  }

  // ---- Gamla settings (bakåtkompatibilitet med admin-settings endpoint) ----
  settings: any = {
    antal_per_dag: 150,
    timtakt: 20,
    skiftlangd: 8.0
  };
  loading = false;
  settingsSaving = false;
  settingsError = '';

  // ---- Nya key-value settings (tvattlinje_settings tabell) ----
  newSettings: any = {
    dagmal: 140,
    takt_mal: 15,
    skift_start: '06:00',
    skift_slut: '22:00'
  };
  newSettingsLoading = false;
  newSettingsSaving = false;
  newSettingsError = '';

  // ---- Veckodagsmål ----
  weekdayGoals: WeekdayGoal[] = [];
  weekdayLabels = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
  weekdayGoalsLoading = false;
  weekdayGoalsSaving = false;
  weekdayGoalsError = '';
  goalsLoadError = false; // true när weekday-goals-API:t felade och fallback-mål används
  snabbvarde = 140;

  // ---- Systemstatus ----
  systemStatus: any = null;
  systemStatusLoading = false;
  systemStatusError = '';
  private systemStatusInterval: any = null;
  private isFetchingStatus = false;

  // ---- Today-snapshot ----
  todaySnapshot: any = null;
  todaySnapshotLoading = false;
  private isFetchingSnapshot = false;

  // ---- Alert-trösklar ----
  alertThresholds = {
    kvalitet_warn:   90,
    plc_max_min:     15,
    dagmal_warn_pct: 80,
  };
  alertThresholdsLoading = false;
  alertThresholdsSaving  = false;
  alertThresholdsSaved   = false;
  alertThresholdsError   = '';
  showAlertPanel         = false;

  // ---- Produkthantering ----
  products: any[] = [];
  productsLoading = false;
  newProduct = { name: '', cycle_time_minutes: null as number | null };
  showAddProductForm = false;

  // ---- Feedback ----
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;
  private alertFeedbackTimerId: any = null;

  // ---- Visibilitychange-guard ----
  private visibilityHandler = () => this.onVisibilityChange();

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin' || val?.role === 'developer';
    });
  }

  ngOnInit() {
    document.addEventListener('visibilitychange', this.visibilityHandler);
    this.loadSettings();
    this.loadNewSettings();
    this.loadWeekdayGoals();
    this.loadSystemStatus();
    this.loadTodaySnapshot();
    this.loadAlertThresholds();
    this.loadProducts();
    this.startPollingTimers();
  }

  ngOnDestroy() {
    document.removeEventListener('visibilitychange', this.visibilityHandler);
    clearTimeout(this.successTimerId);
    clearTimeout(this.alertFeedbackTimerId);
    this.stopPollingTimers();
    this.destroy$.next();
    this.destroy$.complete();
  }

  /** Starta polling-timers */
  private startPollingTimers() {
    this.systemStatusInterval = setInterval(() => {
      if (!this.destroy$.closed) this.loadSystemStatus(true);
    }, 120000);
  }

  /** Stoppa polling-timers */
  private stopPollingTimers() {
    clearInterval(this.systemStatusInterval);
    this.systemStatusInterval = null;
  }

  /** Pausa polling när tabben är dold, återuppta när synlig */
  private onVisibilityChange() {
    if (document.hidden) {
      this.stopPollingTimers();
    } else {
      this.loadSystemStatus(true);
      this.startPollingTimers();
    }
  }

  // ---- Gamla settings ----

  loadSettings() {
    this.loading = true;
    this.settingsError = '';
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinje&run=admin-settings`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of({ success: false })), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.settings = {
              antal_per_dag: response.data.antal_per_dag ?? 150,
              timtakt: response.data.timtakt ?? 20,
              skiftlangd: response.data.skiftlangd ?? 8.0
            };
          } else {
            this.settingsError = 'Kunde inte ladda inställningar.';
          }
          this.loading = false;
        },
        error: () => {
          this.settingsError = 'Fel vid anslutning till servern.';
          this.loading = false;
        }
      });
  }

  saveSettings() {
    if (!this.settings.antal_per_dag || this.settings.antal_per_dag < 1) {
      this.settingsError = 'Dagsmål måste vara minst 1.';
      return;
    }
    this.settingsSaving = true;
    this.settingsError = '';
    this.http.post<any>(`${environment.apiUrl}?action=tvattlinje&run=admin-settings`, this.settings, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of({ success: false })), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.showSuccess('Produktionsinställningar sparade!');
            if (response.data) {
              this.settings = {
                antal_per_dag: response.data.antal_per_dag ?? this.settings.antal_per_dag,
                timtakt: response.data.timtakt ?? this.settings.timtakt,
                skiftlangd: response.data.skiftlangd ?? this.settings.skiftlangd
              };
            }
          } else {
            this.settingsError = 'Kunde inte spara inställningar.';
          }
          this.settingsSaving = false;
        },
        error: () => {
          this.settingsError = 'Fel vid sparande av inställningar.';
          this.settingsSaving = false;
        }
      });
  }

  // ---- Nya key-value settings ----

  loadNewSettings() {
    this.newSettingsLoading = true;
    this.newSettingsError = '';
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinje&run=settings`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of({ success: false })), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.newSettingsLoading = false;
          if (response.success && response.data) {
            const d = response.data;
            this.newSettings = {
              dagmal:      parseInt(d['dagmal']      ?? '140', 10),
              takt_mal:    parseInt(d['takt_mal']    ?? '15',  10),
              skift_start: d['skift_start'] ?? '06:00',
              skift_slut:  d['skift_slut']  ?? '22:00',
            };
          }
        },
        error: () => { this.newSettingsLoading = false; }
      });
  }

  saveNewSettings() {
    if (!this.newSettings.dagmal || this.newSettings.dagmal < 1) {
      this.newSettingsError = 'Dagsmål måste vara minst 1.';
      return;
    }
    if (!this.newSettings.takt_mal || this.newSettings.takt_mal < 1) {
      this.newSettingsError = 'Takt-mål måste vara minst 1.';
      return;
    }
    this.newSettingsSaving = true;
    this.newSettingsError = '';
    this.http.post<any>(`${environment.apiUrl}?action=tvattlinje&run=settings`, this.newSettings, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of({ success: false })), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.newSettingsSaving = false;
          if (response.success) {
            this.showSuccess('Driftsinställningar sparade!');
          } else {
            this.newSettingsError = 'Kunde inte spara inställningar.';
          }
        },
        error: () => {
          this.newSettingsSaving = false;
          this.newSettingsError = 'Fel vid sparande.';
        }
      });
  }

  // ---- Veckodagsmål ----

  loadWeekdayGoals() {
    this.weekdayGoalsLoading = true;
    this.weekdayGoalsError = '';
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinje&run=weekday-goals`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of({ success: false })), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.weekdayGoalsLoading = false;
          if (response.success && Array.isArray(response.data)) {
            this.goalsLoadError = false;
            this.weekdayGoals = response.data.map((item: any) => ({
              weekday: item.weekday,
              mal:     item.mal,
              label:   this.weekdayLabels[item.weekday] ?? `Dag ${item.weekday}`
            }));
          } else {
            // Fallback: API:t felade → använd säkra standardmål (vardag 140, helg 0).
            // Flagga läget så att spara-knappen disablas och en banner visas — annars
            // riskerar admin att skriva över korrekta mål med fallback-värden.
            this.goalsLoadError = true;
            this.weekdayGoals = this.weekdayLabels.map((label, i) => ({
              weekday: i,
              mal:     i < 5 ? 140 : 0,
              label
            }));
          }
        },
        error: () => {
          this.weekdayGoalsLoading = false;
          this.goalsLoadError = true;
          this.weekdayGoals = this.weekdayLabels.map((label, i) => ({
            weekday: i,
            mal:     i < 5 ? 140 : 0,
            label
          }));
        }
      });
  }

  saveWeekdayGoals() {
    this.weekdayGoalsSaving = true;
    this.weekdayGoalsError = '';
    const payload = { goals: this.weekdayGoals.map(g => ({ weekday: g.weekday, mal: g.mal })) };
    this.http.post<any>(`${environment.apiUrl}?action=tvattlinje&run=weekday-goals`, payload, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of({ success: false })), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.weekdayGoalsSaving = false;
          if (response.success) {
            this.showSuccess('Veckodagsmål sparade!');
          } else {
            this.weekdayGoalsError = 'Kunde inte spara veckodagsmål.';
          }
        },
        error: () => {
          this.weekdayGoalsSaving = false;
          this.weekdayGoalsError = 'Fel vid sparande av veckodagsmål.';
        }
      });
  }

  settAllaVeckodagsmal() {
    if (!this.snabbvarde || this.snabbvarde < 0) return;
    this.weekdayGoals = this.weekdayGoals.map(g => ({ ...g, mal: this.snabbvarde }));
  }

  // ---- Systemstatus ----

  loadSystemStatus(silent = false) {
    if (this.isFetchingStatus) return;
    this.isFetchingStatus = true;
    if (!silent) {
      this.systemStatusLoading = true;
      this.systemStatusError = '';
    }
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinje&run=system-status`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.isFetchingStatus = false;
          if (!silent) this.systemStatusLoading = false;
          if (response?.success) {
            this.systemStatus = response.data;
          } else {
            if (!silent) this.systemStatusError = 'Kunde inte hämta systemstatus.';
          }
        },
        error: () => {
          this.isFetchingStatus = false;
          if (!silent) {
            this.systemStatusLoading = false;
            this.systemStatusError = 'Fel vid hämtning av systemstatus.';
          }
        }
      });
  }

  getPlcAge(): string {
    if (this.systemStatus?.plc_age_minutes == null) return '—';
    const min = this.systemStatus.plc_age_minutes;
    if (min < 1) return 'Just nu';
    if (min === 1) return '1 min sedan';
    return `${min} min sedan`;
  }

  getPlcStatus(): 'ok' | 'warn' | 'err' | 'unknown' {
    if (this.systemStatus?.plc_age_minutes == null) return 'unknown';
    const min = this.systemStatus.plc_age_minutes;
    if (min < 15)  return 'ok';
    if (min < 60)  return 'warn';
    return 'err';
  }

  getDbStatusLabel(): string {
    return this.systemStatus?.db_status === 'ok' ? 'OK' : 'Fel';
  }

  // ---- Today-snapshot ----

  loadTodaySnapshot(silent = false) {
    if (this.isFetchingSnapshot) return;
    this.isFetchingSnapshot = true;
    if (!silent) this.todaySnapshotLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinje&run=today-snapshot`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.isFetchingSnapshot = false;
          if (!silent) this.todaySnapshotLoading = false;
          if (res?.success) this.todaySnapshot = res.data;
        },
        error: () => {
          this.isFetchingSnapshot = false;
          if (!silent) this.todaySnapshotLoading = false;
        }
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

  // ---- Alert-trösklar ----

  loadAlertThresholds() {
    this.alertThresholdsLoading = true;
    this.alertThresholdsError   = '';
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinje&run=alert-thresholds`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
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
    this.http.post<any>(`${environment.apiUrl}?action=tvattlinje&run=save-alert-thresholds`,
      this.alertThresholds, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success) {
            this.alertThresholdsSaved = true;
            clearTimeout(this.alertFeedbackTimerId);
            this.alertFeedbackTimerId = setTimeout(() => { if (!this.destroy$.closed) this.alertThresholdsSaved = false; }, 3000);
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

  // ---- Produkthantering ----

  loadProducts() {
    this.productsLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=tvattlinjeproduct`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.productsLoading = false;
          if (response?.success) {
            this.products = response.data.map((p: any) => ({
              ...p,
              editing: false,
              originalName: p.name,
              originalCycleTime: p.cycle_time_minutes
            }));
          }
        },
        error: () => { this.productsLoading = false; }
      });
  }

  addProduct() {
    if (!this.newProduct.name || !this.newProduct.cycle_time_minutes) return;
    this.productsLoading = true;
    this.http.post<any>(`${environment.apiUrl}?action=tvattlinjeproduct`, this.newProduct, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.productsLoading = false;
          if (response?.success) {
            this.loadProducts();
            this.newProduct = { name: '', cycle_time_minutes: null };
            this.showAddProductForm = false;
            this.showSuccess('Produkt tillagd!');
          }
        },
        error: () => { this.productsLoading = false; }
      });
  }

  editProduct(product: any) {
    this.products.forEach(p => {
      if (p.id !== product.id) {
        p.editing = false;
        p.name = p.originalName;
        p.cycle_time_minutes = p.originalCycleTime;
        p.new_id = p.originalId;
      }
    });
    product.editing = true;
    product.originalName = product.name;
    product.originalCycleTime = product.cycle_time_minutes;
    product.originalId = product.id;
    product.new_id = product.id;
  }

  saveProduct(product: any) {
    if (!product.name || !product.cycle_time_minutes) return;
    this.productsLoading = true;
    const updateData = {
      id: product.id,
      new_id: product.new_id ?? product.id,
      name: product.name,
      cycle_time_minutes: product.cycle_time_minutes
    };
    this.http.put<any>(`${environment.apiUrl}?action=tvattlinjeproduct`, updateData, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.productsLoading = false;
          if (response?.success) {
            product.id = product.new_id ?? product.id;
            product.editing = false;
            product.originalName = product.name;
            product.originalCycleTime = product.cycle_time_minutes;
            product.originalId = product.id;
            this.showSuccess('Produkt uppdaterad!');
          }
        },
        error: () => { this.productsLoading = false; }
      });
  }

  cancelEditProduct(product: any) {
    product.editing = false;
    product.id = product.originalId;
    product.new_id = product.originalId;
    product.name = product.originalName;
    product.cycle_time_minutes = product.originalCycleTime;
  }

  deleteProduct(product: any) {
    if (!confirm(`Är du säker på att du vill ta bort produkten "${product.name}"?`)) return;
    this.productsLoading = true;
    this.http.post<any>(`${environment.apiUrl}?action=tvattlinjeproduct&run=delete`, { id: product.id }, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.productsLoading = false;
          if (response?.success) {
            this.loadProducts();
            this.showSuccess('Produkt borttagen!');
          }
        },
        error: () => { this.productsLoading = false; }
      });
  }

  trackByProductId(_index: number, product: any): number {
    return product.id;
  }

  // ---- Hjälpmetoder ----

  private showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    this.formDirty = false;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3500);
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
