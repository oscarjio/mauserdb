import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { KlassificeringslinjeService } from '../../services/klassificeringslinje.service';
import { environment } from '../../../environments/environment';

interface WeekdayGoal {
  weekday: number;
  mal: number;
  label: string;
}

@Component({
  standalone: true,
  selector: 'app-klassificeringslinje-admin',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './klassificeringslinje-admin.html',
  styleUrl: './klassificeringslinje-admin.css'
})
export class KlassificeringslinjeAdminPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  // ---- Driftsinställningar (key-value settings) ----
  settings: any = {
    dagmal: 120,
    takt_mal: 20,
    skift_start: '06:00',
    skift_slut: '22:00'
  };
  settingsLoading = false;
  settingsSaving = false;
  settingsError = '';

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

  // ---- Today-snapshot ----
  todaySnapshot: any = null;
  todaySnapshotLoading = false;
  private todaySnapshotInterval: any = null;
  private isFetchingSnapshot = false;

  // ---- Feedback ----
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;

  // ---- Visibilitychange-guard ----
  private visibilityHandler = () => this.onVisibilityChange();

  constructor(private auth: AuthService, private klassService: KlassificeringslinjeService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    document.addEventListener('visibilitychange', this.visibilityHandler);
    this.loadSettings();
    this.loadWeekdayGoals();
    this.loadSystemStatus();
    this.loadTodaySnapshot();
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

  // ---- Driftsinställningar ----

  loadSettings() {
    this.settingsLoading = true;
    this.settingsError = '';
    this.klassService.getSettings()
      .pipe(takeUntil(this.destroy$), timeout(5000), catchError(() => of({ success: false })))
      .subscribe({
        next: (response) => {
          this.settingsLoading = false;
          if (response.success && response.data) {
            const d = response.data;
            this.settings = {
              dagmal:      parseInt(d['dagmal']      ?? '120', 10),
              takt_mal:    parseInt(d['takt_mal']    ?? '20',  10),
              skift_start: d['skift_start'] ?? '06:00',
              skift_slut:  d['skift_slut']  ?? '22:00',
            };
          } else {
            this.settingsError = 'Kunde inte ladda inställningar.';
          }
        },
        error: () => {
          this.settingsLoading = false;
          this.settingsError = 'Fel vid anslutning till servern.';
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
    this.settingsError = '';
    this.klassService.saveSettings(this.settings)
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
          this.settingsError = 'Fel vid sparande.';
        }
      });
  }

  // ---- Veckodagsmål ----

  loadWeekdayGoals() {
    this.weekdayGoalsLoading = true;
    this.weekdayGoalsError = '';
    this.klassService.getWeekdayGoals()
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
              mal:     i < 5 ? 120 : i === 5 ? 80 : 0,
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
    this.klassService.saveWeekdayGoals(payload)
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
    this.klassService.getSystemStatus()
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

  // ---- Today-snapshot ----

  loadTodaySnapshot() {
    if (this.isFetchingSnapshot) return;
    this.isFetchingSnapshot  = true;
    this.todaySnapshotLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=klassificeringslinje&run=today-snapshot`, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(8000), catchError(() => of(null)))
      .subscribe({
        next: (res) => {
          this.isFetchingSnapshot  = false;
          this.todaySnapshotLoading = false;
          if (res?.success) this.todaySnapshot = res.data;
        },
        error: () => {
          this.isFetchingSnapshot  = false;
          this.todaySnapshotLoading = false;
        }
      });
  }

  get snapshotStatusText(): string {
    if (!this.todaySnapshot) return '';
    if (this.todaySnapshot.empty) return 'Ej i drift';
    return this.todaySnapshot.is_running ? 'Kör' : 'Stoppad';
  }

  get snapshotColorClass(): string {
    if (!this.todaySnapshot || this.todaySnapshot.empty) return 'text-secondary';
    if (!this.todaySnapshot.is_running) return 'text-secondary';
    const pct = this.todaySnapshot.pct_of_goal ?? 0;
    if (pct >= 100) return 'text-success';
    if (pct >= 75)  return 'text-warning';
    return 'text-danger';
  }

  get snapshotDotClass(): string {
    if (!this.todaySnapshot || this.todaySnapshot.empty) return 'dot-off';
    return this.todaySnapshot.is_running ? 'dot-on' : 'dot-off';
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
  trackByIndex(index: number): number { return index; }
}
