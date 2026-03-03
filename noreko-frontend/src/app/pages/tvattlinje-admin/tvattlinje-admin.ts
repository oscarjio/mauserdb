import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

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

  settings: any = {
    antal_per_dag: 150,
    timtakt: 20,
    skiftlangd: 8.0
  };
  loading = false;
  settingsSaving = false;
  settingsError = '';
  showSuccessMessage = false;
  successMessage = '';
  private successTimerId: any = null;

  // ---- Systemstatus ----
  systemStatus: any = null;
  systemStatusLoading = false;
  systemStatusError = '';
  private systemStatusInterval: any = null;
  private isFetchingStatus = false;

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnDestroy() {
    clearTimeout(this.successTimerId);
    clearInterval(this.systemStatusInterval);
    this.destroy$.next();
    this.destroy$.complete();
  }

  ngOnInit() {
    this.loadSettings();
    this.loadSystemStatus();
    this.systemStatusInterval = setInterval(() => {
      if (!this.destroy$.closed) this.loadSystemStatus(true);
    }, 30000);
  }

  loadSettings() {
    this.loading = true;
    this.settingsError = '';
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=admin-settings', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
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
    if (!this.settings.timtakt || this.settings.timtakt < 1) {
      this.settingsError = 'Timtakt måste vara minst 1.';
      return;
    }
    if (!this.settings.skiftlangd || this.settings.skiftlangd < 0.5) {
      this.settingsError = 'Skiftlängd måste vara minst 0,5 timmar.';
      return;
    }

    this.settingsSaving = true;
    this.settingsError = '';
    this.http.post<any>('/noreko-backend/api.php?action=tvattlinje&run=admin-settings', this.settings, { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.showSuccess('Inställningar sparade!');
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

  loadSystemStatus(silent = false) {
    if (this.isFetchingStatus) return;
    this.isFetchingStatus = true;
    if (!silent) {
      this.systemStatusLoading = true;
      this.systemStatusError = '';
    }
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=status', { withCredentials: true })
      .pipe(takeUntil(this.destroy$))
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

  getStatusAge(): string {
    if (!this.systemStatus?.lastUpdate) return 'Ingen data';
    const last = new Date(this.systemStatus.lastUpdate);
    const now = new Date();
    const diffMin = Math.floor((now.getTime() - last.getTime()) / 60000);
    if (diffMin < 1) return 'Just nu';
    if (diffMin === 1) return '1 min sedan';
    return `${diffMin} min sedan`;
  }

  getStatusAgeMinutes(): number {
    if (!this.systemStatus?.lastUpdate) return 9999;
    const last = new Date(this.systemStatus.lastUpdate);
    const now = new Date();
    return Math.floor((now.getTime() - last.getTime()) / 60000);
  }

  getStatusLevel(): 'ok' | 'warn' | 'err' {
    const minutes = this.getStatusAgeMinutes();
    if (minutes < 5) return 'ok';
    if (minutes < 15) return 'warn';
    return 'err';
  }

  private showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
}
