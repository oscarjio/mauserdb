import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OpDetail {
  position: string;
  label: string;
  op_number: number;
  op_name: string;
  personal_avg_ibc_h: number | null;
  vs_personal: number | null;
  antal_skift_ctx: number;
}

interface ShiftContext {
  from: string;
  to: string;
  team_avg_ibc_h: number | null;
  team_avg_kass: number | null;
  vs_team_pct: number | null;
}

interface ShiftDetail {
  success: boolean;
  mode: 'detail';
  skiftraknare: number;
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  drifttid_min: number;
  driftstopptime_min: number;
  ibc_per_h: number;
  kassationsgrad: number;
  stoppgrad: number;
  product_id: number | null;
  product_name: string | null;
  operators: OpDetail[];
  context: ShiftContext;
  error?: string;
}

interface ShiftListItem {
  skiftraknare: number;
  datum: string;
  ibc_ok: number;
  drifttid_min: number;
  ibc_per_h: number;
  op1: number | null;
  op2: number | null;
  op3: number | null;
  op1_name: string | null;
  op2_name: string | null;
  op3_name: string | null;
}

interface ShiftListResponse {
  success: boolean;
  mode: 'list';
  datum: string;
  shifts: ShiftListItem[];
  error?: string;
}

@Component({
  standalone: true,
  selector: 'app-skift-insikt',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './skift-insikt.html',
  styleUrl: './skift-insikt.css',
})
export class SkiftInsiktPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  Math = Math;
  listLoading = false;
  detailLoading = false;
  listError = '';
  detailError = '';

  skiftNrInput = '';
  selectedDate = new Date().toISOString().split('T')[0];

  shiftList: ShiftListItem[] = [];
  detail: ShiftDetail | null = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadByDate();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadByDate(): void {
    if (!this.selectedDate) return;
    this.listLoading = true;
    this.listError = '';
    this.shiftList = [];
    this.detail = null;
    this.detailError = '';

    const url = `${environment.apiUrl}?action=rebotling&run=skift-insikt&datum=${this.selectedDate}`;
    this.http.get<ShiftListResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.listLoading = false;
        if (res?.success) {
          this.shiftList = res.shifts;
        } else {
          this.listError = res?.error || 'Kunde inte hämta skift';
        }
      });
  }

  loadByNr(): void {
    const nr = parseInt(this.skiftNrInput, 10);
    if (!nr || nr <= 0) return;
    this.loadDetail(nr);
  }

  loadDetail(sk: number): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.detailLoading = true;
    this.detail = null;
    this.detailError = '';

    const url = `${environment.apiUrl}?action=rebotling&run=skift-insikt&skiftraknare=${sk}`;
    this.http.get<ShiftDetail>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.detailLoading = false;
        if (res?.success) {
          this.detail = res;
        } else {
          this.detailError = res?.error || 'Kunde inte hämta skiftdata';
        }
      });
  }

  formatDate(d: string): string {
    if (!d) return '';
    const dt = new Date(d + 'T12:00:00');
    return dt.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  formatDateShort(d: string): string {
    if (!d) return '';
    const dt = new Date(d + 'T12:00:00');
    return dt.toLocaleDateString('sv-SE', { weekday: 'short', month: 'short', day: 'numeric' });
  }

  drifttidLabel(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  vsColor(pct: number | null): string {
    if (pct === null) return '#a0aec0';
    if (pct >= 15) return '#68d391';
    if (pct >= 5)  return '#9ae6b4';
    if (pct >= -5) return '#ecc94b';
    if (pct >= -15) return '#fc8181';
    return '#f56565';
  }

  vsBg(pct: number | null): string {
    if (pct === null) return '#2d3748';
    if (pct >= 15) return 'rgba(104,211,145,0.15)';
    if (pct >= 5)  return 'rgba(154,230,180,0.10)';
    if (pct >= -5) return 'rgba(236,201,75,0.10)';
    if (pct >= -15) return 'rgba(252,129,129,0.12)';
    return 'rgba(245,101,101,0.15)';
  }

  kassColor(pct: number): string {
    if (pct === 0) return '#68d391';
    if (pct < 3)   return '#9ae6b4';
    if (pct < 7)   return '#ecc94b';
    return '#fc8181';
  }

  stoppColor(pct: number): string {
    if (pct === 0) return '#68d391';
    if (pct < 10)  return '#9ae6b4';
    if (pct < 25)  return '#ecc94b';
    return '#fc8181';
  }

  listIbcHClass(item: ShiftListItem): string {
    if (!item.ibc_per_h) return '';
    return 'ibc-badge';
  }

  ops(item: ShiftListItem): string {
    return [item.op1_name, item.op2_name, item.op3_name].filter(Boolean).join(' · ');
  }
}
