import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface DayInfo {
  ibc_per_h: number;
  ibc_ok: number;
  num_skift: number;
}

interface OperatorRow {
  number: number;
  name: string;
  shifts: Record<string, string>;
  shift_count: number;
  pos_counts: { op1: number; op2: number; op3: number };
}

interface KalenderResponse {
  success: boolean;
  year: number;
  month: number;
  days_in_month: number;
  daily: Record<string, DayInfo>;
  month_avg: number;
  total_ibc: number;
  production_days: number;
  operators: OperatorRow[];
}

const MONTH_NAMES = [
  '', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
  'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December',
];

const WEEKDAY_SHORT = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];

@Component({
  standalone: true,
  selector: 'app-personal-kalender',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './personal-kalender.html',
  styleUrl: './personal-kalender.css',
})
export class PersonalKalenderPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  year = new Date().getFullYear();
  month = new Date().getMonth() + 1;

  daysInMonth = 0;
  dayNumbers: number[] = [];
  dayDates: string[] = [];
  daily: Record<string, DayInfo> = {};
  monthAvg = 0;
  totalIbc = 0;
  productionDays = 0;
  operators: OperatorRow[] = [];

  Math = Math;

  get monthLabel(): string {
    return `${MONTH_NAMES[this.month]} ${this.year}`;
  }

  get activeOperators(): number {
    return this.operators.filter(o => o.shift_count > 0).length;
  }

  get totalPersonShifts(): number {
    return this.operators.reduce((s, o) => s + o.shift_count, 0);
  }

  get avgShiftsPerOp(): number {
    const active = this.operators.filter(o => o.shift_count > 0);
    if (!active.length) return 0;
    return this.totalPersonShifts / active.length;
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  prevMonth(): void {
    if (this.month === 1) { this.month = 12; this.year--; }
    else { this.month--; }
    this.load();
  }

  nextMonth(): void {
    if (this.month === 12) { this.month = 1; this.year++; }
    else { this.month++; }
    this.load();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=personal-kalender&year=${this.year}&month=${this.month}`;
    this.http.get<KalenderResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta personalkalender.';
          return;
        }
        this.daysInMonth    = res.days_in_month;
        this.daily          = res.daily;
        this.monthAvg       = res.month_avg;
        this.totalIbc       = res.total_ibc;
        this.productionDays = res.production_days;
        this.operators      = res.operators;
        this.buildDayArrays();
      });
  }

  private buildDayArrays(): void {
    this.dayNumbers = Array.from({ length: this.daysInMonth }, (_, i) => i + 1);
    const yy = String(this.year);
    const mm = String(this.month).padStart(2, '0');
    this.dayDates = this.dayNumbers.map(d => `${yy}-${mm}-${String(d).padStart(2, '0')}`);
  }

  weekdayLabel(date: string): string {
    return WEEKDAY_SHORT[new Date(date + 'T12:00:00').getDay()];
  }

  isWeekend(date: string): boolean {
    const d = new Date(date + 'T12:00:00').getDay();
    return d === 0 || d === 6;
  }

  getPos(op: OperatorRow, date: string): string | null {
    return op.shifts[date] ?? null;
  }

  posLabel(pos: string): string {
    if (pos === 'op1') return 'T';
    if (pos === 'op2') return 'K';
    return 'Tr';
  }

  posTooltip(pos: string): string {
    if (pos === 'op1') return 'Tvättplats';
    if (pos === 'op2') return 'Kontrollstation';
    return 'Truckförare';
  }

  posClass(pos: string): string {
    if (pos === 'op1') return 'pos-t';
    if (pos === 'op2') return 'pos-k';
    return 'pos-tr';
  }

  cellBg(date: string): string {
    const info = this.daily[date];
    if (!info || info.ibc_per_h <= 0 || this.monthAvg <= 0) return '';
    const ratio = info.ibc_per_h / this.monthAvg;
    if (ratio >= 1.1)  return 'day-great';
    if (ratio >= 0.95) return 'day-good';
    if (ratio >= 0.80) return 'day-avg';
    return 'day-weak';
  }

  dayIbcH(date: string): number {
    return this.daily[date]?.ibc_per_h ?? 0;
  }

  dayTooltip(date: string, op: OperatorRow): string {
    const pos = this.getPos(op, date);
    if (!pos) return '';
    const ibch = this.dayIbcH(date);
    return `${op.name} @ ${this.posTooltip(pos)} — lag ${ibch.toFixed(1)} IBC/h`;
  }

  posBarWidth(op: OperatorRow, pos: 'op1' | 'op2' | 'op3'): number {
    if (!op.shift_count) return 0;
    return Math.round((op.pos_counts[pos] / op.shift_count) * 100);
  }
}
