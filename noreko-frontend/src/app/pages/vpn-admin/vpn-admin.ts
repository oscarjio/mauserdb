import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, filter, switchMap } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';

interface VpnClient {
  common_name: string;
  real_address: string;
  virtual_address: string;
  bytes_received: number;
  bytes_sent: number;
  connected_since: string;
  connected_since_timestamp: number;
  username: string;
  connected: boolean;
  last_seen: string;
}

@Component({
  standalone: true,
  selector: 'app-vpn-admin',
  imports: [CommonModule],
  templateUrl: './vpn-admin.html',
  styleUrl: './vpn-admin.css'
})
export class VpnAdminPage implements OnInit, OnDestroy {
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  clients: VpnClient[] = [];
  loading = false;
  isFetching = false;
  error: string | null = null;
  totalConnected = 0;
  totalClients = 0;
  disconnecting: Record<string, boolean> = {};
  disconnectMessage: string | null = null;
  disconnectError: string | null = null;

  private refreshInterval: any;
  private destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private http: HttpClient,
    private router: Router
  ) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
  }

  ngOnInit() {
    this.auth.initialized$.pipe(
      filter(init => init === true),
      switchMap(() => this.auth.user$),
      takeUntil(this.destroy$)
    ).subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';

      if (!this.isAdmin) {
        this.router.navigate(['/']);
        return;
      }

      // Ladda VPN-status forsta gang + starta polling
      if (!this.refreshInterval) {
        this.loadVpnStatus();
        this.refreshInterval = setInterval(() => {
          if (!this.destroy$.closed) this.loadVpnStatus();
        }, 30000);
      }
    });
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  }

  loadVpnStatus() {
    if (this.isFetching) return;
    this.isFetching = true;

    // Sätt loading endast första gången, annars uppdatera i bakgrunden
    if (this.clients.length === 0) {
      this.loading = true;
    }
    this.error = null;

    this.http.get<any>(`${environment.apiUrl}?action=vpn`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(response => {
        this.isFetching = false;
        this.loading = false;
        if (response === null) {
          this.error = 'Kunde inte ansluta till VPN-servern. Kontrollera att OpenVPN management interface är aktiverat.';
          this.clients = [];
          return;
        }
        if (response.success) {
          this.clients = response.clients || [];
          this.totalConnected = response.total_connected || 0;
          this.totalClients = response.total_clients || 0;
        } else {
          this.error = response.error || response.message || 'Kunde inte hämta VPN-status';
          this.clients = [];
        }
      });
  }

  disconnectClient(client: VpnClient) {
    if (!client?.common_name || !client.connected) {
      return;
    }

    const commonName = client.common_name;
    const confirmed = confirm(`Vill du koppla från ${commonName}?`);
    if (!confirmed) {
      return;
    }

    this.disconnectMessage = null;
    this.disconnectError = null;
    this.disconnecting[commonName] = true;

    this.http.post<any>(`${environment.apiUrl}?action=vpn`, {
      command: 'disconnect',
      commonName
    }, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(response => {
        delete this.disconnecting[commonName];
        if (response === null) {
          this.disconnectError = 'Kunde inte nå VPN-backend.';
          return;
        }
        if (response?.success) {
          this.disconnectMessage = response.message || `Anslutningen för ${commonName} har avslutats.`;
          this.loadVpnStatus();
        } else {
          this.disconnectError = response?.message || 'Kunde inte avbryta anslutningen.';
        }
      });
  }

  formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  }

  formatDate(dateString: string): string {
    if (!dateString) return '-';
    try {
      const date = new Date(dateString);
      return date.toLocaleString('sv-SE');
    } catch {
      return dateString;
    }
  }

  getStatusBadgeClass(connected: boolean): string {
    return connected ? 'badge bg-success' : 'badge bg-secondary';
  }

  getStatusText(connected: boolean): string {
    return connected ? 'Ansluten' : 'Frånkopplad';
  }
  trackByIndex(index: number): number { return index; }
}
