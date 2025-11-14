import { Component, OnInit, OnDestroy } from '@angular/core';
import { RouterModule, Router } from '@angular/router';
import { NgIf, CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-menu',
  standalone: true,
  imports: [RouterModule, NgIf, FormsModule, CommonModule],
  templateUrl: './menu.html',
  styleUrl: './menu.css'
})
export class Menu implements OnInit, OnDestroy {
  loggedIn = false;
  user: any = null;
  showMenu = false;
  selectedMenu: string = 'Älvängen';
  vpnConnectedCount: number = 0;
  profileForm = {
    email: '',
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  };
  profileMessage: string | null = null;
  profileError: string | null = null;
  savingProfile = false;
  private refreshInterval: any;

  constructor(
    private router: Router, 
    public auth: AuthService,
    private http: HttpClient
  ) {
    const saved = localStorage.getItem('selectedMenu');
    if (saved) this.selectedMenu = saved;
    this.auth.loggedIn$.subscribe(val => {
      this.loggedIn = val;
      if (val && this.user?.role === 'admin') {
        this.loadVpnStatus();
      } else {
        this.vpnConnectedCount = 0;
        this.clearRefreshInterval();
      }
    });
    this.auth.user$.subscribe(val => {
      this.user = val;
      if (val?.email) {
        this.profileForm.email = val.email;
      }
      if (val?.role === 'admin' && this.loggedIn) {
        this.loadVpnStatus();
      } else {
        this.vpnConnectedCount = 0;
        this.clearRefreshInterval();
      }
    });
  }

  ngOnInit() {
    if (this.loggedIn && this.user?.role === 'admin') {
      this.loadVpnStatus();
    }
  }

  ngOnDestroy() {
    this.clearRefreshInterval();
  }

  private clearRefreshInterval() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  loadVpnStatus() {
    if (!this.loggedIn || this.user?.role !== 'admin') {
      return;
    }

    // Ladda i bakgrunden utan att visa loading
    this.http.get<any>('/noreko-backend/api.php?action=vpn', { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
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
        },
        error: (error) => {
          // Tyst hantera fel - visa bara 0
          this.vpnConnectedCount = 0;
        }
      });
  }

  onMenuChange(event: Event) {
    localStorage.setItem('selectedMenu', this.selectedMenu);
  }

  updateProfile() {
    if (this.user?.role === 'admin') {
      return;
    }

    this.profileMessage = null;
    this.profileError = null;

    const trimmedEmail = this.profileForm.email?.trim() ?? '';
    if (!trimmedEmail) {
      this.profileError = 'E-postadress krävs.';
      return;
    }

    if (this.profileForm.newPassword || this.profileForm.confirmPassword) {
      if (this.profileForm.newPassword !== this.profileForm.confirmPassword) {
        this.profileError = 'Nya lösenord matchar inte.';
        return;
      }
      if (!this.profileForm.currentPassword) {
        this.profileError = 'Nuvarande lösenord krävs för att byta lösenord.';
        return;
      }
    }

    const payload: any = { email: trimmedEmail };
    if (this.profileForm.newPassword) {
      payload.currentPassword = this.profileForm.currentPassword;
      payload.newPassword = this.profileForm.newPassword;
    }

    this.savingProfile = true;
    this.http.post<any>('/noreko-backend/api.php?action=profile', payload, { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response?.success) {
            this.profileMessage = response.message || 'Konto uppdaterat.';
            this.profileForm.currentPassword = '';
            this.profileForm.newPassword = '';
            this.profileForm.confirmPassword = '';
            this.auth.fetchStatus();
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
    this.clearRefreshInterval();
    this.router.navigate(['/']);
  }
}
