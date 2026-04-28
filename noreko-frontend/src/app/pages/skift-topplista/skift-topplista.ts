import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ShiftRow {
  skiftraknare: number;
  datum: string;
  ibc_ok: number;
  ibc_per_h: number;
  drifttid_min: number;
  kassation_pct: number;
  stopp_pct: number;
  vs_team_pct: number;
  op1_name: string | null;
  op2_name: string | null;
  op3_name: string | null;
}

interface TopResponse {
  success: boolean;
  top: ShiftRow[];
  bottom: ShiftRow[];
  team_avg: number;
  total_shifts: number;
  from: string | null;
  to: string | null;
  days: string;
  limit: number;
}

@Component({
  standalone: true,
  selector: 'app-skift-topplista',
  imports: [CommonModule, FormsModule, RouterModule, DecimalPipe],
  templateUrl: './skift-topplista.html',
  styleUrl: './skift-topplista.css',
})
export class SkiftTopplista implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  view: 'top' | 'bottom' = 'top';
  days = '365';
  limit = 20;

  top: ShiftRow[] = [];
  bottom: ShiftRow[] = [];
  teamAvg = 0;
  totalShifts = 0;
  from: string | null = null;
  to: string | null = null;

  readonly daysOptions = [
    { value: '90',  label: '90 dagar' },
    { value: '180', label: '180 dagar' },
    { value: '365', label: '1 år' },
    { value: 'all', label: 'Allt' },
  ];

  Math = Math;

  get rows(): ShiftRow[] {
    return this.view === 'top' ? this.top : this.bottom;
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=skift-topplista&days=${this.days}&limit=${this.limit}`;
    this.http.get<TopResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (res?.success) {
          this.top          = res.top;
          this.bottom       = res.bottom;
          this.teamAvg      = res.team_avg;
          this.totalShifts  = res.total_shifts;
          this.from         = res.from;
          this.to           = res.to;
        } else {
          this.error = 'Kunde inte hämta skift-topplista.';
        }
      });
  }

  ibcHClass(vs: number): string {
    if (vs >= 15)  return 'tier-elite';
    if (vs >= 5)   return 'tier-solid';
    if (vs >= -5)  return 'tier-ok';
    if (vs >= -15) return 'tier-developing';
    return 'tier-low';
  }

  vsSign(pct: number): string {
    return pct >= 0 ? `+${pct}%` : `${pct}%`;
  }

  drifttidLabel(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  formatDate(d: string): string {
    if (!d) return '';
    const dt = new Date(d);
    const days = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${days[dt.getDay()]} ${d}`;
  }

  ops(row: ShiftRow): string {
    return [row.op1_name, row.op2_name, row.op3_name]
      .filter(Boolean)
      .join(', ');
  }
}
