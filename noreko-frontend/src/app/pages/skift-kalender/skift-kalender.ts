import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface KalenderSkift {
  skiftraknare: number;
  op1_num: number;
  op2_num: number;
  op3_num: number;
  op1_name: string;
  op2_name: string;
  op3_name: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  drifttid: number;
  driftstopptime: number;
  product_id: number;
  ibc_per_h: number;
}

interface KalenderDag {
  date: string;
  day: number;
  shifts: KalenderSkift[];
  day_ibc_ok: number;
  day_drifttid: number;
  day_ibc_per_h: number;
  vs_avg: number | null;
  rating: 'great' | 'good' | 'avg' | 'weak' | 'poor' | 'tom';
}

interface KalenderResponse {
  success: boolean;
  year: number;
  month: number;
  month_name: string;
  days_in_month: number;
  first_weekday: number;
  month_avg_ibc_h: number;
  total_ibc_ok: number;
  total_skift: number;
  days: KalenderDag[];
}

@Component({
  standalone: true,
  selector: 'app-skift-kalender',
  imports: [CommonModule, RouterModule],
  templateUrl: './skift-kalender.html',
  styleUrl: './skift-kalender.css'
})
export class SkiftKalenderPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';
  Math = Math;

  year  = new Date().getFullYear();
  month = new Date().getMonth() + 1;

  monthName     = '';
  days: KalenderDag[] = [];
  firstWeekday  = 1;
  monthAvgIbcH  = 0;
  totalIbcOk    = 0;
  totalSkift    = 0;

  expandedDate: string | null = null;

  readonly weekdays = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
  readonly monthNames = ['', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                        'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];

  get calendarRows(): (KalenderDag | null)[][] {
    const cells: (KalenderDag | null)[] = [];
    const offset = this.firstWeekday - 1; // Mon=0 offset
    for (let i = 0; i < offset; i++) cells.push(null);
    for (const d of this.days) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);
    const rows: (KalenderDag | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) rows.push(cells.slice(i, i + 7));
    return rows;
  }

  get expandedDay(): KalenderDag | null {
    if (!this.expandedDate) return null;
    return this.days.find(d => d.date === this.expandedDate) ?? null;
  }

  get monthStats() {
    const active    = this.days.filter(d => d.shifts.length > 0).length;
    const greatDays = this.days.filter(d => d.rating === 'great').length;
    const weakDays  = this.days.filter(d => d.rating === 'poor' || d.rating === 'weak').length;
    return { active, greatDays, weakDays };
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading    = true;
    this.error      = '';
    this.expandedDate = null;

    const url = `${environment.apiUrl}?action=rebotling&run=skift-kalender&year=${this.year}&month=${this.month}`;
    this.http.get<KalenderResponse>(url, { withCredentials: true }).pipe(
      timeout(5000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading    = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta kalenderdata.';
        return;
      }
      this.monthName    = res.month_name;
      this.days         = res.days;
      this.firstWeekday = res.first_weekday;
      this.monthAvgIbcH = res.month_avg_ibc_h;
      this.totalIbcOk   = res.total_ibc_ok;
      this.totalSkift   = res.total_skift;
    });
  }

  prevMonth(): void {
    this.month--;
    if (this.month < 1) { this.month = 12; this.year--; }
    this.load();
  }

  nextMonth(): void {
    this.month++;
    if (this.month > 12) { this.month = 1; this.year++; }
    this.load();
  }

  toggleExpand(date: string): void {
    this.expandedDate = this.expandedDate === date ? null : date;
  }

  isToday(date: string): boolean {
    return date === new Date().toISOString().slice(0, 10);
  }

  ratingLabel(rating: string): string {
    switch (rating) {
      case 'great': return 'Topp';
      case 'good':  return 'Bra';
      case 'avg':   return 'OK';
      case 'weak':  return 'Svag';
      case 'poor':  return 'Låg';
      default:      return '';
    }
  }

  driftH(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
  }

  opChips(s: KalenderSkift): string[] {
    return [s.op1_name, s.op2_name, s.op3_name].filter(n => n);
  }
}
