import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

interface CalendarDay {
  date: string;
  ibc: number;
  goal: number;
  pct: number;
}

interface CalendarResponse {
  success: boolean;
  year: number;
  days: CalendarDay[];
}

interface WeekRow {
  weekLabel: string;
  cells: (CalendarDay | null)[];
}

interface MonthBlock {
  month: number;        // 0–11
  monthName: string;
  weeks: WeekRow[];
}

@Component({
  selector: 'app-production-calendar',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './production-calendar.html',
  styleUrls: ['./production-calendar.css']
})
export class ProductionCalendarPage implements OnInit, OnDestroy {
  Math = Math;

  selectedYear: number = new Date().getFullYear();
  availableYears: number[] = [];

  loading = false;
  error = '';

  days: CalendarDay[] = [];
  dayMap: Map<string, CalendarDay> = new Map();

  // KPI
  totalIbc = 0;
  avgIbcPerDay = 0;
  bestDayIbc = 0;
  daysOnTarget = 0;
  totalProductionDays = 0;
  daysOnTargetPct = 0;
  bestDate = '';

  // Grid: 12 months each with a grid of weeks
  monthBlocks: MonthBlock[] = [];

  private destroy$ = new Subject<void>();

  private readonly MONTH_NAMES_SV = [
    'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun',
    'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'
  ];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    // Erbjud år 2023 till aktuellt år
    for (let y = 2023; y <= currentYear; y++) {
      this.availableYears.push(y);
    }
    this.loadCalendar();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onYearChange(event: Event): void {
    const val = (event.target as HTMLSelectElement).value;
    this.selectedYear = parseInt(val, 10);
    this.loadCalendar();
  }

  prevYear(): void {
    if (this.selectedYear > 2023) {
      this.selectedYear--;
      this.loadCalendar();
    }
  }

  nextYear(): void {
    if (this.selectedYear < new Date().getFullYear()) {
      this.selectedYear++;
      this.loadCalendar();
    }
  }

  loadCalendar(): void {
    this.loading = true;
    this.error = '';
    this.days = [];
    this.dayMap = new Map();
    this.monthBlocks = [];

    const url = `/noreko-backend/api.php?action=rebotling&run=year-calendar&year=${this.selectedYear}`;

    this.http.get<CalendarResponse>(url)
      .pipe(
        timeout(10000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.loading = false;
          if (res?.success && res.days) {
            this.days = res.days;
            res.days.forEach(d => this.dayMap.set(d.date, d));
            this.computeKpis();
            this.buildGrid();
          } else {
            this.error = 'Ingen kalenderdata tillgänglig.';
            this.buildGrid(); // visa tom grid
          }
        },
        error: () => {
          this.loading = false;
          this.error = 'Kunde inte hämta data från servern.';
          this.buildGrid();
        }
      });
  }

  private computeKpis(): void {
    if (this.days.length === 0) {
      this.totalIbc = 0;
      this.avgIbcPerDay = 0;
      this.bestDayIbc = 0;
      this.daysOnTarget = 0;
      this.totalProductionDays = 0;
      this.daysOnTargetPct = 0;
      this.bestDate = '';
      return;
    }

    this.totalIbc = this.days.reduce((sum, d) => sum + d.ibc, 0);
    this.totalProductionDays = this.days.length;
    this.avgIbcPerDay = Math.round(this.totalIbc / this.totalProductionDays);

    const bestDay = this.days.reduce((best, d) => d.ibc > best.ibc ? d : best, this.days[0]);
    this.bestDayIbc = bestDay.ibc;
    this.bestDate = bestDay.date;

    this.daysOnTarget = this.days.filter(d => d.pct >= 95).length;
    this.daysOnTargetPct = Math.round((this.daysOnTarget / this.totalProductionDays) * 100);
  }

  private buildGrid(): void {
    this.monthBlocks = [];

    // Bygg en fullständig lista av alla dagar i valt år, organiserat per månad → per vecka (mån–sön)
    for (let m = 0; m < 12; m++) {
      const firstDay = new Date(this.selectedYear, m, 1);
      const lastDay  = new Date(this.selectedYear, m + 1, 0);

      // ISO-veckodag: 0=sön, 1=mån, ... 6=lör → vi vill ha mån som first col
      // getDay() returns 0=Sun, 1=Mon, ..., 6=Sat
      // Vi vill: 0=Mån,1=Tis,...,6=Sön
      const firstDow = (firstDay.getDay() + 6) % 7; // Mån=0
      const totalDays = lastDay.getDate();

      const cells: (CalendarDay | null)[] = [];

      // Fyll ledande tomma celler (förra månadens dagar)
      for (let i = 0; i < firstDow; i++) {
        cells.push(null);
      }

      // Fyll faktiska dagar
      for (let d = 1; d <= totalDays; d++) {
        const dateStr = this.formatDateStr(this.selectedYear, m + 1, d);
        const dayData = this.dayMap.get(dateStr) ?? null;
        if (dayData) {
          cells.push(dayData);
        } else {
          // Tom cell med bara datum
          cells.push(null);
        }
      }

      // Fyll avslutande tomma celler så varje månad har hela veckor
      while (cells.length % 7 !== 0) {
        cells.push(null);
      }

      // Dela upp i veckor
      const weeks: WeekRow[] = [];
      for (let wi = 0; wi < cells.length; wi += 7) {
        const weekCells = cells.slice(wi, wi + 7);
        // Hitta första icke-null cellen för att beräkna veckonummer
        weeks.push({
          weekLabel: '',
          cells: weekCells
        });
      }

      this.monthBlocks.push({
        month: m,
        monthName: this.MONTH_NAMES_SV[m],
        weeks
      });
    }
  }

  private formatDateStr(year: number, month: number, day: number): string {
    const mm = String(month).padStart(2, '0');
    const dd = String(day).padStart(2, '0');
    return `${year}-${mm}-${dd}`;
  }

  // Returnera dag-nummer från datumsträngen "YYYY-MM-DD"
  getDayNum(dateStr: string | null): number {
    if (!dateStr) return 0;
    return parseInt(dateStr.split('-')[2], 10);
  }

  // Returnera datumsträngen för en given cell-position
  getCellDate(month: number, weekIndex: number, dayIndex: number): string | null {
    const block = this.monthBlocks[month];
    if (!block) return null;
    const week = block.weeks[weekIndex];
    if (!week) return null;
    // Vi lagrar CalendarDay|null, men vi behöver datum för null-celler också.
    // Vi beräknar datumet från position istället.
    const firstDay  = new Date(this.selectedYear, month, 1);
    const firstDow  = (firstDay.getDay() + 6) % 7;
    const cellIdx   = weekIndex * 7 + dayIndex - firstDow;
    if (cellIdx < 0 || cellIdx >= new Date(this.selectedYear, month + 1, 0).getDate()) return null;
    return this.formatDateStr(this.selectedYear, month + 1, cellIdx + 1);
  }

  getCellColorClass(cell: CalendarDay | null): string {
    if (!cell) return 'cell-empty';
    if (cell.pct >= 110) return 'cell-superday';
    if (cell.pct >= 95)  return 'cell-green';
    if (cell.pct >= 80)  return 'cell-yellow';
    if (cell.pct >= 60)  return 'cell-orange';
    return 'cell-red';
  }

  getTooltip(cell: CalendarDay | null, month: number, weekIndex: number, dayIndex: number): string {
    const dateStr = this.getCellDate(month, weekIndex, dayIndex);
    if (!cell) {
      return dateStr ? this.formatSwedishDate(dateStr) + ' — Ingen produktion' : '';
    }
    return `${this.formatSwedishDate(cell.date)}\n${cell.ibc} IBC av ${cell.goal} planerade\n${cell.pct.toFixed(0)}% av dagsmål`;
  }

  private formatSwedishDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const days = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${days[d.getDay()]} ${d.getDate()} ${this.MONTH_NAMES_SV[d.getMonth()]}`;
  }

  formatBestDate(): string {
    return this.bestDate ? this.formatSwedishDate(this.bestDate) : '—';
  }
}
