import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';


interface Operator {
  op_id: number;
  namn: string;
  initialer: string;
  ibc_idag: number;
  ibc_per_h: number;
  kvalitet_pct: number | null;
  minuter_sedan: number | null;
  status: 'bra' | 'ok' | 'lag' | 'inaktiv';
}

interface DashboardData {
  success: boolean;
  datum: string;
  operatorer: Operator[];
  total_ibc: number;
  snitt_ibc_per_h: number;
  bast_namn: string | null;
  bast_ibc_per_h: number;
}

@Component({
  standalone: true,
  selector: 'app-operator-dashboard',
  imports: [CommonModule],
  template: `
    <div class="operator-dashboard-page" style="background:#1a202c;min-height:100vh;padding:24px 16px;">

      <!-- Rubrik -->
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
          <h2 style="color:#e2e8f0;margin:0;font-weight:700;">
            <i class="fas fa-users-cog me-2" style="color:#63b3ed;"></i>Operatörsdashboard
          </h2>
          <small style="color:#718096;">Uppdateras automatiskt var 60:e sekund &bull; {{ datum }}</small>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span *ngIf="isFetching" style="color:#63b3ed;font-size:13px;">
            <i class="fas fa-sync-alt fa-spin me-1"></i>Uppdaterar…
          </span>
          <span *ngIf="felmeddelande" style="color:#fc8181;font-size:13px;">
            <i class="fas fa-exclamation-triangle me-1"></i>{{ felmeddelande }}
          </span>
        </div>
      </div>

      <!-- Skeleton / Laddningsindikator -->
      <div *ngIf="laddar && operatorer.length === 0" class="text-center py-5">
        <div class="spinner-border" style="color:#63b3ed;" role="status">
          <span class="visually-hidden">Laddar…</span>
        </div>
        <p style="color:#718096;margin-top:12px;">Hämtar operatörsstatus…</p>
      </div>

      <!-- KPI-kort -->
      <div *ngIf="!laddar || operatorer.length > 0" class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="kpi-card" style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
            <div style="font-size:2rem;font-weight:700;color:#63b3ed;">{{ operatorer.length }}</div>
            <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
              <i class="fas fa-user me-1"></i>Aktiva idag
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="kpi-card" style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
            <div style="font-size:2rem;font-weight:700;color:#68d391;">{{ snittIbcPerH | number:'1.1-1' }}</div>
            <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
              <i class="fas fa-tachometer-alt me-1"></i>Snitt IBC/h
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="kpi-card" style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
            <div style="font-size:1.3rem;font-weight:700;color:#f6e05e;line-height:1.2;">{{ bastNamn || '—' }}</div>
            <div style="color:#a0aec0;font-size:12px;">{{ bastIbcPerH | number:'1.1-1' }} IBC/h</div>
            <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
              <i class="fas fa-trophy me-1"></i>Bäst idag
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="kpi-card" style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
            <div style="font-size:2rem;font-weight:700;color:#fc8181;">{{ totalIbc }}</div>
            <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
              <i class="fas fa-box me-1"></i>Totalt IBC idag
            </div>
          </div>
        </div>
      </div>

      <!-- Operatörstabell -->
      <div *ngIf="!laddar || operatorer.length > 0" style="background:#2d3748;border-radius:12px;overflow:hidden;border:1px solid #4a5568;">
        <div style="padding:16px 20px;border-bottom:1px solid #4a5568;">
          <h5 style="color:#e2e8f0;margin:0;font-size:16px;">
            <i class="fas fa-list me-2" style="color:#63b3ed;"></i>Operatörsstatus
          </h5>
        </div>

        <!-- Tom state -->
        <div *ngIf="operatorer.length === 0 && !laddar" class="text-center py-5">
          <i class="fas fa-inbox" style="font-size:2rem;color:#4a5568;"></i>
          <p style="color:#718096;margin-top:12px;">Inga operatörer registrerade idag</p>
        </div>

        <!-- Tabell för desktop -->
        <div *ngIf="operatorer.length > 0" class="table-responsive">
          <table class="table table-dark mb-0" style="--bs-table-bg:#2d3748;--bs-table-striped-bg:#283141;--bs-table-border-color:#4a5568;color:#e2e8f0;">
            <thead>
              <tr style="background:#1e2535;color:#a0aec0;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">
                <th style="padding:12px 16px;border-bottom:1px solid #4a5568;">Operatör</th>
                <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC idag</th>
                <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC/h</th>
                <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">Kvalitet%</th>
                <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">Senast aktiv</th>
                <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:center;">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let op of operatorer" style="border-bottom:1px solid #3d4a5c;transition:background .15s;">
                <!-- Avatar + Namn -->
                <td style="padding:14px 16px;vertical-align:middle;">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle"
                         [style.background]="getAvatarColor(op.namn)"
                         style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
                      {{ op.initialer }}
                    </div>
                    <div>
                      <div style="font-weight:600;color:#e2e8f0;">{{ op.namn }}</div>
                      <div style="font-size:11px;color:#718096;">Op. #{{ op.op_id }}</div>
                    </div>
                  </div>
                </td>
                <!-- IBC idag -->
                <td style="padding:14px 16px;vertical-align:middle;text-align:right;font-size:15px;font-weight:600;color:#e2e8f0;">
                  {{ op.ibc_idag }}
                </td>
                <!-- IBC/h -->
                <td style="padding:14px 16px;vertical-align:middle;text-align:right;">
                  <span style="font-size:15px;font-weight:700;"
                        [style.color]="getIbcColor(op.ibc_per_h, op.status)">
                    {{ op.ibc_per_h | number:'1.1-1' }}
                  </span>
                </td>
                <!-- Kvalitet% -->
                <td style="padding:14px 16px;vertical-align:middle;text-align:right;">
                  <span *ngIf="op.kvalitet_pct !== null"
                        [style.color]="op.kvalitet_pct >= 95 ? '#68d391' : op.kvalitet_pct >= 85 ? '#f6e05e' : '#fc8181'"
                        style="font-size:14px;">
                    {{ op.kvalitet_pct | number:'1.1-1' }}%
                  </span>
                  <span *ngIf="op.kvalitet_pct === null" style="color:#4a5568;">—</span>
                </td>
                <!-- Senast aktiv -->
                <td style="padding:14px 16px;vertical-align:middle;text-align:right;color:#a0aec0;font-size:13px;">
                  <span *ngIf="op.minuter_sedan !== null">
                    <span *ngIf="op.minuter_sedan < 60">{{ op.minuter_sedan }} min sedan</span>
                    <span *ngIf="op.minuter_sedan >= 60">{{ timmar(op.minuter_sedan) }} h {{ minuter(op.minuter_sedan) }} min sedan</span>
                  </span>
                  <span *ngIf="op.minuter_sedan === null" style="color:#4a5568;">Okänd</span>
                </td>
                <!-- Status-badge -->
                <td style="padding:14px 16px;vertical-align:middle;text-align:center;">
                  <span class="status-badge"
                        [style.background]="statusBgColor(op.status)"
                        [style.color]="statusTextColor(op.status)"
                        style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;">
                    {{ statusLabel(op.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Statuslegend -->
      <div class="d-flex flex-wrap gap-3 mt-3" style="font-size:12px;color:#718096;">
        <span><span style="display:inline-block;width:10px;height:10px;background:#276749;border-radius:50%;margin-right:4px;"></span>Bra (&gt;18 IBC/h)</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:#744210;border-radius:50%;margin-right:4px;"></span>OK (12–18 IBC/h)</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:#742a2a;border-radius:50%;margin-right:4px;"></span>Låg (&lt;12 IBC/h)</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:#2d3748;border:1px solid #4a5568;border-radius:50%;margin-right:4px;"></span>Inaktiv (&gt;30 min)</span>
      </div>

    </div>
  `
})
export class OperatorDashboardPage implements OnInit, OnDestroy {
  operatorer: Operator[] = [];
  datum = '';
  totalIbc = 0;
  snittIbcPerH = 0;
  bastNamn: string | null = null;
  bastIbcPerH = 0;

  laddar = false;
  isFetching = false;
  felmeddelande = '';

  private destroy$ = new Subject<void>();
  private pollingInterval: any = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.laddaData();
    this.pollingInterval = setInterval(() => this.laddaData(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
    }
  }

  laddaData(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    if (this.operatorer.length === 0) this.laddar = true;
    this.felmeddelande = '';

    this.http.get<DashboardData>('/noreko-backend/api.php?action=operator-dashboard&run=today')
      .pipe(
        timeout(5000),
        catchError(() => {
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.isFetching = false;
          this.laddar = false;
          if (!res) {
            this.felmeddelande = 'Kunde inte hämta data';
            return;
          }
          if (!res.success) {
            this.felmeddelande = 'Serverfel';
            return;
          }
          this.operatorer = res.operatorer || [];
          this.datum = this.formatDatum(res.datum);
          this.totalIbc = res.total_ibc ?? 0;
          this.snittIbcPerH = res.snitt_ibc_per_h ?? 0;
          this.bastNamn = res.bast_namn;
          this.bastIbcPerH = res.bast_ibc_per_h ?? 0;
        },
        error: () => {
          this.isFetching = false;
          this.laddar = false;
          this.felmeddelande = 'Kunde inte hämta data';
        }
      });
  }

  getAvatarColor(name: string): string {
    const colors = ['#e53e3e', '#dd6b20', '#d69e2e', '#38a169', '#3182ce', '#805ad5', '#d53f8c'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  getInitials(name: string): string {
    return name.split(' ').map((n: string) => n[0]).join('').toUpperCase().slice(0, 2);
  }

  getIbcColor(ibcPerH: number, status: string): string {
    if (status === 'inaktiv') return '#718096';
    if (ibcPerH >= 18) return '#68d391';
    if (ibcPerH >= 12) return '#f6e05e';
    return '#fc8181';
  }

  statusLabel(status: string): string {
    switch (status) {
      case 'bra': return 'Bra';
      case 'ok': return 'OK';
      case 'lag': return 'Låg';
      case 'inaktiv': return 'Inaktiv';
      default: return status;
    }
  }

  statusBgColor(status: string): string {
    switch (status) {
      case 'bra': return '#276749';
      case 'ok': return '#744210';
      case 'lag': return '#742a2a';
      case 'inaktiv': return '#2d3748';
      default: return '#2d3748';
    }
  }

  statusTextColor(status: string): string {
    switch (status) {
      case 'bra': return '#9ae6b4';
      case 'ok': return '#fbd38d';
      case 'lag': return '#feb2b2';
      case 'inaktiv': return '#718096';
      default: return '#a0aec0';
    }
  }

  timmar(min: number): number {
    return Math.floor(min / 60);
  }

  minuter(min: number): number {
    return min % 60;
  }

  private formatDatum(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }
}
