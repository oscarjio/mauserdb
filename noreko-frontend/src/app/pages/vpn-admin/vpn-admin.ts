import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';

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
  imports: [CommonModule, DatePipe],
  templateUrl: './vpn-admin.html',
  styleUrl: './vpn-admin.css'
})
export class VpnAdminPage implements OnInit, OnDestroy {
  loggedIn = false;
  user: any = null;
  isAdmin = false;
  
  clients: VpnClient[] = [];
  loading = false;
  error: string | null = null;
  totalConnected = 0;
  totalClients = 0;
  disconnecting: Record<string, boolean> = {};
  disconnectMessage: string | null = null;
  disconnectError: string | null = null;
  
  private refreshInterval: any;

  constructor(
    private auth: AuthService, 
    private http: HttpClient,
    private router: Router
  ) {
    this.auth.loggedIn$.subscribe(val => this.loggedIn = val);
    this.auth.user$.subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
      
      if (!this.isAdmin && val) {
        this.router.navigate(['/']);
      }
    });
  }

  ngOnInit() {
    if (!this.isAdmin) {
      return;
    }
    
    this.loadVpnStatus();
    // Uppdatera var 30:e sekund
    this.refreshInterval = setInterval(() => {
      this.loadVpnStatus();
    }, 30000);
  }

  ngOnDestroy() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  }

  loadVpnStatus() {
    // Sätt loading endast första gången, annars uppdatera i bakgrunden
    if (this.clients.length === 0) {
      this.loading = true;
    }
    this.error = null;
    
    // Kör i bakgrunden - använd catchError för att hantera fel utan att blockera
    this.http.get<any>('/noreko-backend/api.php?action=vpn', { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.clients = response.clients || [];
            this.totalConnected = response.total_connected || 0;
            this.totalClients = response.total_clients || 0;
            
          } else {
            this.error = response.error || response.message || 'Kunde inte hämta VPN-status';
            this.clients = [];
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid hämtning av VPN-status:', error);
          this.error = 'Kunde inte ansluta till VPN-servern. Kontrollera att OpenVPN management interface är aktiverat.';
          this.clients = [];
          this.loading = false;
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

    const request = this.http.post<any>('/noreko-backend/api.php?action=vpn', {
      command: 'disconnect',
      commonName
    }, { withCredentials: true }).subscribe({
      next: (response) => {
        if (response?.success) {
          this.disconnectMessage = response.message || `Anslutningen för ${commonName} har avslutats.`;
          this.loadVpnStatus();
        } else {
          this.disconnectError = response?.message || 'Kunde inte avbryta anslutningen.';
        }
      },
      error: (error) => {
        this.disconnectError = error?.error?.message || 'Kunde inte nå VPN-backend.';
      }
    });

    request.add(() => {
      delete this.disconnecting[commonName];
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
}


