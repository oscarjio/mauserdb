import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ShiftRow {
  skiftraknare: number;
  datum: string;
  ibc_ok: number;
  ibc_h: number;
  kass_pct: number;
  stopp_pct: number;
  drifttid: number;
  driftstopptime: number;
  op1: number | null;
  op2: number | null;
  op3: number | null;
  op1_name: string | null;
  op2_name: string | null;
  op3_name: string | null;
  product_id: number | null;
  product_name: string | null;
}

interface DayKpis {
  ibc_ok: number;
  ibc_h: number;
  kass_pct: number;
  stopp_pct: number;
  skift_count: number;
}

interface YesterdayData {
  ibc_ok: number;
  ibc_h: number;
  delta_ibc: number;
  delta_ibch: number;
}

interface Avg30d {
  ibc_h: number;
  vs_avg_pct: number;
}

interface WeekTrendItem {
  datum: string;
  ibc_ok: number;
}

interface IdagResponse {
  success: boolean;
  datum: string;
  shifts: ShiftRow[];
  kpis: DayKpis;
  yesterday: YesterdayData;
  avg_30d: Avg30d;
  week_trend: WeekTrendItem[];
}

@Component({
  standalone: true,
  selector: 'app-idag',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './idag.html',
  styleUrl: './idag.css',
})
export class IdagPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private pollTimer: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  Math = Math;

  loading = false;
  error = '';

  datum = new Date().toISOString().slice(0, 10);
  data: IdagResponse | null = null;

  ngOnInit(): void {
    this.load();
    this.pollTimer = setInterval(() => this.load(), 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
  }

  constructor(private http: HttpClient) {}

  goToday(): void {
    this.datum = new Date().toISOString().slice(0, 10);
    this.load();
  }

  changeDate(offset: number): void {
    const d = new Date(this.datum);
    d.setDate(d.getDate() + offset);
    this.datum = d.toISOString().slice(0, 10);
    this.load();
  }

  isToday(): boolean {
    return this.datum === new Date().toISOString().slice(0, 10);
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=idag&datum=${this.datum}`;
    this.http.get<IdagResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loading = false;
      this.isFetching = false;
      if (res?.success) {
        this.data = res;
      } else {
        this.error = 'Kunde inte hämta data. Kontrollera anslutningen.';
      }
    });
  }

  deltaClass(val: number): string {
    if (val > 0.5) return 'delta-pos';
    if (val < -0.5) return 'delta-neg';
    return 'delta-neu';
  }

  deltaSign(val: number): string {
    return val > 0 ? '+' : '';
  }

  ibchClass(ibch: number, avg: number): string {
    if (avg <= 0) return '';
    const pct = (ibch - avg) / avg * 100;
    if (pct >= 10) return 'cell-green';
    if (pct <= -10) return 'cell-red';
    return 'cell-neutral';
  }

  kassClass(pct: number): string {
    if (pct <= 3) return 'cell-green';
    if (pct <= 7) return 'cell-yellow';
    return 'cell-red';
  }

  stoppClass(pct: number): string {
    if (pct <= 5) return 'cell-green';
    if (pct <= 15) return 'cell-yellow';
    return 'cell-red';
  }

  weekBarHeight(val: number, max: number): number {
    return max > 0 ? Math.round((val / max) * 100) : 0;
  }

  weekMax(): number {
    if (!this.data?.week_trend?.length) return 1;
    return Math.max(...this.data.week_trend.map(t => t.ibc_ok), 1);
  }

  weekDayLabel(datum: string): string {
    const days = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    const d = new Date(datum + 'T12:00:00');
    return days[d.getDay()];
  }

  formatDate(d: string): string {
    if (!d) return '';
    const parts = d.split('-');
    return `${parts[2]}/${parts[1]}`;
  }
}
