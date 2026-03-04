import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

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
export class TvattlinjeAdminPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;

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
    dagmal: 80,
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

  // ---- Systemstatus ----
  systemStatus: any = null;
  systemStatusLoading = false;
  systemStatusError = '';
  private systemStatusInterval: any = null;
  private isFetchingStatus = false;

  // ---- Feedback ----
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;

  // ---- Visibilitychange-guard ----
  private visibilityHandler = () => this.onVisibilityChange();

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    document.addEventListener('visibilitychange', this.visibilityHandler);
    this.loadSettings();
    this.loadNewSettings();
    this.loadWeekdayGoals();
    this.loadSystemStatus();
    this.startPollingTimers();
  }

  ngOnDestroy() {
    document.removeEventListener('visibilitychange', this.visibilityHandler);
    clearTimeout(this.successTimerId);
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
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=admin-settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
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
    this.http.post<any>('/noreko-backend/api.php?action=tvattlinje&run=admin-settings', this.settings, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
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
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
      .subscribe({
        next: (response) => {
          this.newSettingsLoading = false;
          if (response.success && response.data) {
            const d = response.data;
            this.newSettings = {
              dagmal:      parseInt(d['dagmal']      ?? '80',  10),
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
    this.http.post<any>('/noreko-backend/api.php?action=tvattlinje&run=settings', this.newSettings, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
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
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=weekday-goals', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
      .subscribe({
        next: (response) => {
          this.weekdayGoalsLoading = false;
          if (response.success && Array.isArray(response.data)) {
            this.weekdayGoals = response.data.map((item: any) => ({
              weekday: item.weekday,
              mal:     item.mal,
              label:   this.weekdayLabels[item.weekday] ?? `Dag ${item.weekday}`
            }));
          } else {
            // Fallback: bygg default-lista
            this.weekdayGoals = this.weekdayLabels.map((label, i) => ({
              weekday: i,
              mal:     i < 5 ? 80 : i === 5 ? 60 : 0,
              label
            }));
          }
        },
        error: () => { this.weekdayGoalsLoading = false; }
      });
  }

  saveWeekdayGoals() {
    this.weekdayGoalsSaving = true;
    this.weekdayGoalsError = '';
    const payload = { goals: this.weekdayGoals.map(g => ({ weekday: g.weekday, mal: g.mal })) };
    this.http.post<any>('/noreko-backend/api.php?action=tvattlinje&run=weekday-goals', payload, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
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

  // ---- Systemstatus ----

  loadSystemStatus(silent = false) {
    if (this.isFetchingStatus) return;
    this.isFetchingStatus = true;
    if (!silent) {
      this.systemStatusLoading = true;
      this.systemStatusError = '';
    }
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=system-status', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
      .subscribe({
        next: (response) => {
          this.isFetchingStatus = false;
          if (!silent) this.systemStatusLoading = false;
          if (response.success) {
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

  getDbStatusLabel(): string {
    return this.systemStatus?.db_status === 'ok' ? 'OK' : 'Fel';
  }

  // ---- Hjälpmetoder ----

  private showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3500);
  }
}
