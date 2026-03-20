import { Component, OnInit, OnDestroy } from '@angular/core';
import { RouterModule, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService, AuthUser } from '../services/auth.service';
import { AlertsService } from '../services/alerts.service';
import { FeatureFlagService } from '../services/feature-flag.service';
import { forkJoin, catchError, of, timeout, Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

interface LineStatusApiResponse {
  success?: boolean;
  data?: { running: boolean };
}

interface VpnApiResponse {
  success?: boolean;
  total_connected?: number;
}

interface ProfileApiResponse {
  success?: boolean;
  message?: string;
}

@Component({
  selector: 'app-menu',
  standalone: true,
  imports: [RouterModule, FormsModule, CommonModule],
  templateUrl: './menu.html',
  styleUrl: './menu.css'
})
export class Menu implements OnInit, OnDestroy {
  loggedIn = false;
  user: AuthUser | null = null;
  showMenu = false;
  selectedMenu: string = 'Älvängen';
  vpnConnectedCount: number = 0;
  rebotlingRunning = false;
  tvattlinjeRunning = false;
  urgentNoteCount = 0;
  certExpiryCount = 0;
  activeAlertsCount = 0;
  profileForm = {
    email: '',
    operatorId: '',
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  };
  profileMessage: string | null = null;
  profileError: string | null = null;
  savingProfile = false;
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private lineStatusInterval: ReturnType<typeof setInterval> | null = null;
  private notifTimer: ReturnType<typeof setInterval> | null = null;
  private certExpiryInterval: ReturnType<typeof setInterval> | null = null;
  private alertsInterval: ReturnType<typeof setInterval> | null = null;

  constructor(
    private router: Router,
    public auth: AuthService,
    private http: HttpClient,
    private alertsService: AlertsService,
    public ff: FeatureFlagService
  ) {
    const saved = localStorage.getItem('selectedMenu');
    if (saved) this.selectedMenu = saved;
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.loggedIn = val;
      if (val && (this.user?.role === 'admin' || this.user?.role === 'developer')) {
        this.loadVpnStatus();
        this.loadCertExpiryCount();
        this.startAlertsPolling();
      } else {
        this.vpnConnectedCount = 0;
        this.certExpiryCount = 0;
        this.clearRefreshInterval();
        this.clearCertExpiryInterval();
        this.stopAlertsPolling();
      }
      if (val) {
        this.loadUrgentCount();
        if (!this.notifTimer) {
          this.notifTimer = setInterval(() => this.loadUrgentCount(), 60000);
        }
      } else {
        this.urgentNoteCount = 0;
        if (this.notifTimer) {
          clearInterval(this.notifTimer);
          this.notifTimer = null;
        }
      }
    });
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.user = val ?? null;
      if (val?.email) {
        this.profileForm.email = val.email;
      }
      if (val?.operator_id) {
        this.profileForm.operatorId = String(val.operator_id);
      }
      if ((val?.role === 'admin' || val?.role === 'developer') && this.loggedIn) {
        this.loadVpnStatus();
        this.loadCertExpiryCount();
        this.startAlertsPolling();
      } else {
        this.vpnConnectedCount = 0;
        this.certExpiryCount = 0;
        this.clearRefreshInterval();
        this.clearCertExpiryInterval();
        this.stopAlertsPolling();
      }
    });
  }

  ngOnInit() {
    // Lazy-load Bootstrap Dropdown JS (behövs för data-bs-toggle="dropdown")
    import('bootstrap/js/dist/dropdown');

    this.loadLineStatus();
    if (this.loggedIn && (this.user?.role === 'admin' || this.user?.role === 'developer')) {
      this.loadVpnStatus();
      this.loadCertExpiryCount();
    }
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    this.clearRefreshInterval();
    this.clearCertExpiryInterval();
    this.stopAlertsPolling();
    if (this.lineStatusInterval) {
      clearInterval(this.lineStatusInterval);
      this.lineStatusInterval = null;
    }
    if (this.notifTimer) {
      clearInterval(this.notifTimer);
      this.notifTimer = null;
    }
  }

  private clearRefreshInterval() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  private clearCertExpiryInterval() {
    if (this.certExpiryInterval) {
      clearInterval(this.certExpiryInterval);
      this.certExpiryInterval = null;
    }
  }

  private startAlertsPolling(): void {
    if (this.alertsInterval) return;
    this.loadAlertsCount();
    this.alertsInterval = setInterval(() => this.loadAlertsCount(), 60_000);
  }

  private stopAlertsPolling(): void {
    if (this.alertsInterval) {
      clearInterval(this.alertsInterval);
      this.alertsInterval = null;
    }
    this.activeAlertsCount = 0;
  }

  private loadAlertsCount(): void {
    if (!this.loggedIn || (this.user?.role !== 'admin' && this.user?.role !== 'developer')) return;
    this.http.get<any>('/noreko-backend/api.php?action=alerts&run=active', { withCredentials: true })
      .pipe(timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.activeAlertsCount = res.data?.count ?? 0;
        }
      });
  }

  loadLineStatus() {
    forkJoin({
      rebotling: this.http.get<LineStatusApiResponse>('/noreko-backend/api.php?action=rebotling&run=status', { withCredentials: true }).pipe(timeout(3000), catchError(() => of(null))),
      tvattlinje: this.http.get<LineStatusApiResponse>('/noreko-backend/api.php?action=tvattlinje&run=status', { withCredentials: true }).pipe(timeout(3000), catchError(() => of(null)))
    }).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.rebotlingRunning = res.rebotling?.data?.running ?? false;
      this.tvattlinjeRunning = res.tvattlinje?.data?.running ?? false;
    });

    if (!this.lineStatusInterval) {
      this.lineStatusInterval = setInterval(() => this.loadLineStatus(), 30000);
    }
  }

  loadUrgentCount(): void {
    this.http.get<{ antal?: number }>('/noreko-backend/api.php?action=shift-handover&run=unread-count', { withCredentials: true }).pipe(
      timeout(4000),
      catchError(() => of({ antal: 0 })),
      takeUntil(this.destroy$)
    ).subscribe(r => {
      this.urgentNoteCount = r.antal || 0;
    });
  }

  loadCertExpiryCount(): void {
    if (!this.loggedIn || (this.user?.role !== 'admin' && this.user?.role !== 'developer')) return;
    this.http.get<{ success?: boolean; count?: number }>('/noreko-backend/api.php?action=certification&run=expiry-count', { withCredentials: true })
      .pipe(timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) this.certExpiryCount = res.count ?? 0;
      });

    if (!this.certExpiryInterval) {
      this.certExpiryInterval = setInterval(() => this.loadCertExpiryCount(), 5 * 60 * 1000);
    }
  }

  loadVpnStatus() {
    if (!this.loggedIn || this.user?.role !== 'admin') {
      return;
    }

    // Ladda i bakgrunden utan att visa loading
    this.http.get<VpnApiResponse>('/noreko-backend/api.php?action=vpn', { withCredentials: true })
      .pipe(timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response?.success) {
            this.vpnConnectedCount = response.total_connected || 0;

            // Starta auto-refresh om det inte redan är igång
            if (!this.refreshInterval) {
              this.refreshInterval = setInterval(() => {
                this.loadVpnStatus();
              }, 30000); // Uppdatera var 30:e sekund
            }
          } else {
            this.vpnConnectedCount = 0;
          }
        }
      });
  }

  onMenuChange() {
    localStorage.setItem('selectedMenu', this.selectedMenu);
  }

  updateProfile() {
    this.profileMessage = null;
    this.profileError = null;

    const trimmedEmail = this.profileForm.email?.trim() ?? '';
    if (!trimmedEmail) {
      this.profileError = 'E-postadress krävs.';
      return;
    }

    if (this.profileForm.newPassword || this.profileForm.confirmPassword) {
      if (this.profileForm.newPassword.length < 8) {
        this.profileError = 'Nytt lösenord måste vara minst 8 tecken.';
        return;
      }
      if (!/[a-zA-Z]/.test(this.profileForm.newPassword) || !/[0-9]/.test(this.profileForm.newPassword)) {
        this.profileError = 'Nytt lösenord måste innehålla minst en bokstav och en siffra.';
        return;
      }
      if (this.profileForm.newPassword !== this.profileForm.confirmPassword) {
        this.profileError = 'Nya lösenord matchar inte.';
        return;
      }
      if (!this.profileForm.currentPassword) {
        this.profileError = 'Nuvarande lösenord krävs för att byta lösenord.';
        return;
      }
    }

    const payload: { email: string; operator_id: number | null; currentPassword?: string; newPassword?: string } = {
      email: trimmedEmail,
      operator_id: this.profileForm.operatorId ? parseInt(this.profileForm.operatorId, 10) : null
    };
    if (this.profileForm.newPassword) {
      payload.currentPassword = this.profileForm.currentPassword;
      payload.newPassword = this.profileForm.newPassword;
    }

    this.savingProfile = true;
    this.http.post<ProfileApiResponse>('/noreko-backend/api.php?action=profile', payload, { withCredentials: true })
      .pipe(
        timeout(10000),
        catchError((error) => {
          this.profileError = error?.error?.message || 'Ett fel inträffade.';
          this.savingProfile = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (response) => {
          if (response === null) return;
          if (response?.success) {
            this.profileMessage = response.message || 'Konto uppdaterat.';
            this.profileForm.currentPassword = '';
            this.profileForm.newPassword = '';
            this.profileForm.confirmPassword = '';
            this.auth.fetchStatus().pipe(takeUntil(this.destroy$)).subscribe();
          } else {
            this.profileError = response?.message || 'Kunde inte uppdatera kontot.';
          }
          this.savingProfile = false;
        },
        error: (error) => {
          this.profileError = error?.error?.message || 'Ett fel inträffade.';
          this.savingProfile = false;
        }
      });
  }

  logout() {
    this.auth.logout();
    this.showMenu = false;
    this.vpnConnectedCount = 0;
    this.certExpiryCount = 0;
    this.clearRefreshInterval();
    this.clearCertExpiryInterval();
    this.router.navigate(['/']);
  }
}
