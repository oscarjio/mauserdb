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
  selector: 'app-saglinje-admin',
  templateUrl: './saglinje-admin.html',
  styleUrl: './saglinje-admin.css',
  imports: [CommonModule, FormsModule, DatePipe],
})
export class SaglinjeAdminPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  // ---- Driftsinställningar (key-value settings) ----
  settings: any = {
    dagmal:      50,
    takt_mal:    10,
    skift_start: '06:00',
    skift_slut:  '22:00',
  };
  settingsLoading = false;
  settingsSaving  = false;
  settingsError   = '';

  // ---- Veckodagsmål ----
  weekdayGoals: WeekdayGoal[] = [];
  weekdayLabels = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
  weekdayGoalsLoading = false;
  weekdayGoalsSaving  = false;
  weekdayGoalsError   = '';

  // ---- Systemstatus ----
  systemStatus: any = null;
  systemStatusLoading = false;
  systemStatusError   = '';
  private systemStatusInterval: any = null;
  private isFetchingStatus = false;

  // ---- Feedback ----
  showSuccessMessage = false;
  successMessage     = '';
  private successTimerId: any = null;

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user   = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadSettings();
    this.loadWeekdayGoals();
    this.loadSystemStatus();
    this.systemStatusInterval = setInterval(() => {
      if (!this.destroy$.closed) this.loadSystemStatus(true);
    }, 30000);
  }

  ngOnDestroy() {
    clearTimeout(this.successTimerId);
    clearInterval(this.systemStatusInterval);
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ---- Inställningar ----

  loadSettings() {
    this.settingsLoading = true;
    this.settingsError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=saglinje&run=settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
      .subscribe({
        next: (response) => {
          this.settingsLoading = false;
          if (response.success && response.data) {
            const d = response.data;
            this.settings = {
              dagmal:      parseInt(d['dagmal']      ?? '50',  10),
              takt_mal:    parseInt(d['takt_mal']    ?? '10',  10),
              skift_start: d['skift_start'] ?? '06:00',
              skift_slut:  d['skift_slut']  ?? '22:00',
            };
          }
        },
        error: () => {
          this.settingsLoading = false;
          this.settingsError   = 'Fel vid anslutning till servern.';
        }
      });
  }

  saveSettings() {
    if (!this.settings.dagmal || this.settings.dagmal < 1) {
      this.settingsError = 'Dagsmål måste vara minst 1.';
      return;
    }
    if (!this.settings.takt_mal || this.settings.takt_mal < 1) {
      this.settingsError = 'Takt-mål måste vara minst 1.';
      return;
    }
    this.settingsSaving = true;
    this.settingsError  = '';
    this.http.post<any>('/noreko-backend/api.php?action=saglinje&run=settings', this.settings, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
      .subscribe({
        next: (response) => {
          this.settingsSaving = false;
          if (response.success) {
            this.showSuccess('Driftsinställningar sparade!');
          } else {
            this.settingsError = 'Kunde inte spara inställningar.';
          }
        },
        error: () => {
          this.settingsSaving = false;
          this.settingsError  = 'Fel vid sparande.';
        }
      });
  }

  // ---- Veckodagsmål ----

  loadWeekdayGoals() {
    this.weekdayGoalsLoading = true;
    this.weekdayGoalsError   = '';
    this.http.get<any>('/noreko-backend/api.php?action=saglinje&run=weekday-goals', { withCredentials: true })
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
              mal:     i < 5 ? 50 : i === 5 ? 30 : 0,
              label
            }));
          }
        },
        error: () => { this.weekdayGoalsLoading = false; }
      });
  }

  saveWeekdayGoals() {
    this.weekdayGoalsSaving = true;
    this.weekdayGoalsError  = '';
    const payload = { goals: this.weekdayGoals.map(g => ({ weekday: g.weekday, mal: g.mal })) };
    this.http.post<any>('/noreko-backend/api.php?action=saglinje&run=weekday-goals', payload, { withCredentials: true })
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
          this.weekdayGoalsError  = 'Fel vid sparande av veckodagsmål.';
        }
      });
  }

  // ---- Systemstatus ----

  loadSystemStatus(silent = false) {
    if (this.isFetchingStatus) return;
    this.isFetchingStatus = true;
    if (!silent) {
      this.systemStatusLoading = true;
      this.systemStatusError   = '';
    }
    this.http.get<any>('/noreko-backend/api.php?action=saglinje&run=system-status', { withCredentials: true })
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
            this.systemStatusError   = 'Fel vid hämtning av systemstatus.';
          }
        }
      });
  }

  getPlcAge(): string {
    if (this.systemStatus?.plc_age_minutes == null) return '—';
    const min = this.systemStatus.plc_age_minutes;
    if (min < 1)  return 'Just nu';
    if (min === 1) return '1 min sedan';
    return `${min} min sedan`;
  }

  getDbStatusLabel(): string {
    return this.systemStatus?.db_status === 'ok' ? 'OK' : 'Fel';
  }

  // ---- Hjälpmetoder ----

  private showSuccess(message: string) {
    this.successMessage    = message;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3500);
  }
}
