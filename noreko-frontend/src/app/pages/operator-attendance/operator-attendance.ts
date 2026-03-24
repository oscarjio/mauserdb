import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { localDateStr } from '../../utils/date-utils';
import { environment } from '../../../environments/environment';

@Component({
  standalone: true,
  selector: 'app-operator-attendance',
  imports: [CommonModule, FormsModule],
  templateUrl: './operator-attendance.html',
  styleUrl: './operator-attendance.css'
})
export class OperatorAttendancePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  selectedMonth = localDateStr(new Date()).slice(0, 7); // YYYY-MM
  loading = false;
  error = '';

  operators: any[] = [];
  calendar: Record<string, number[]> = {};
  calendarDays: { date: string; dayNum: number; weekday: number; operators: any[] }[] = [];
  startOffset: null[] = [];

  readonly weekdayHeaders = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];

  // Närvaro-statistik per operatör
  get attendanceStats(): { namn: string; initialer: string; days: number }[] {
    const stats: Record<number, { namn: string; initialer: string; days: number }> = {};
    for (const op of this.operators) {
      stats[op.id] = { namn: op.namn, initialer: op.initialer, days: 0 };
    }
    for (const opIds of Object.values(this.calendar)) {
      for (const opId of opIds) {
        if (stats[opId]) stats[opId].days++;
      }
    }
    return Object.values(stats).sort((a, b) => b.days - a.days);
  }

  get opsWithAttendance(): number {
    return this.attendanceStats.filter(s => s.days > 0).length;
  }

  get totalAttendanceDays(): number {
    return this.attendanceStats.reduce((sum, s) => sum + s.days, 0);
  }

  constructor(private auth: AuthService, private http: HttpClient) {}

  ngOnInit() { this.loadAttendance(); }
  ngOnDestroy() { this.destroy$.next(); this.destroy$.complete(); }

  loadAttendance() {
    this.loading = true;
    this.error = '';
    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=attendance&month=${this.selectedMonth}`,
      { withCredentials: true }
    )
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (res?.success) {
          this.operators = res.data.operators;
          this.calendar = res.data.calendar;
          this.buildCalendarDays();
        } else {
          this.error = 'Kunde inte hämta närvaro.';
        }
      });
  }

  buildCalendarDays() {
    this.calendarDays = [];
    const [year, month] = this.selectedMonth.split('-').map(Number);
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstWeekday = new Date(year, month - 1, 1).getDay(); // 0=sön
    this.startOffset = new Array(firstWeekday).fill(null);
    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${this.selectedMonth}-${String(d).padStart(2, '0')}`;
      const weekday = new Date(year, month - 1, d).getDay();
      const opIds = this.calendar[dateStr] || [];
      const ops = opIds.map((id: number) => this.operators.find((o: any) => o.id === id)).filter(Boolean);
      this.calendarDays.push({ date: dateStr, dayNum: d, weekday, operators: ops });
    }
  }

  prevMonth() {
    const [y, m] = this.selectedMonth.split('-').map(Number);
    const prev = new Date(y, m - 2, 1);
    this.selectedMonth = localDateStr(prev).slice(0, 7);
    this.loadAttendance();
  }

  nextMonth() {
    const [y, m] = this.selectedMonth.split('-').map(Number);
    const next = new Date(y, m, 1);
    this.selectedMonth = localDateStr(next).slice(0, 7);
    this.loadAttendance();
  }

  weekdayLabel(wd: number): string {
    return ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'][wd];
  }

  monthLabel(): string {
    const [y, m] = this.selectedMonth.split('-').map(Number);
    const months = [
      'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
      'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'
    ];
    return `${months[m - 1]} ${y}`;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
