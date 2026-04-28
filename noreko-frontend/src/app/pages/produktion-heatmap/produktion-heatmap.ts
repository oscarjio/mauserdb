import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface DayData {
  datum: string;
  ibc_per_h: number;
  skift_count: number;
  vs_avg: number;
}

interface HeatmapResponse {
  success: boolean;
  days: DayData[];
  team_avg: number;
  from: string;
  to: string;
  months: number;
}

interface WeekRow {
  weekLabel: string;
  cells: (DayData | null)[];
}

@Component({
  standalone: true,
  selector: 'app-produktion-heatmap',
  imports: [CommonModule, FormsModule],
  templateUrl: './produktion-heatmap.html',
  styleUrl: './produktion-heatmap.css',
})
export class ProduktionHeatmapPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  months = 6;
  loading = false;
  error = '';

  days: DayData[] = [];
  teamAvg = 0;
  from = '';
  to = '';

  weekRows: WeekRow[] = [];
  readonly weekdayLabels = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];

  hoveredDay: DayData | null = null;

  get totalDaysWithProduction(): number {
    return this.days.length;
  }

  get bestDay(): DayData | null {
    if (!this.days.length) return null;
    return this.days.reduce((best, d) => d.ibc_per_h > best.ibc_per_h ? d : best);
  }

  get worstDay(): DayData | null {
    if (!this.days.length) return null;
    return this.days.filter(d => d.ibc_per_h > 0).reduce((worst, d) => d.ibc_per_h < worst.ibc_per_h ? d : worst);
  }

  get daysAboveAvg(): number {
    return this.days.filter(d => d.vs_avg >= 0).length;
  }

  get top5Best(): DayData[] {
    return [...this.days].sort((a, b) => b.ibc_per_h - a.ibc_per_h).slice(0, 5);
  }

  get top5Worst(): DayData[] {
    return [...this.days].sort((a, b) => a.ibc_per_h - b.ibc_per_h).slice(0, 5);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<HeatmapResponse>(
      `${environment.apiUrl}?action=rebotling&run=produktion-heatmap&months=${this.months}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (res?.success) {
          this.days = res.days;
          this.teamAvg = res.team_avg;
          this.from = res.from;
          this.to = res.to;
          this.buildGrid();
        } else {
          this.error = 'Kunde inte hämta produktionsdata.';
        }
      });
  }

  private buildGrid(): void {
    if (!this.days.length) { this.weekRows = []; return; }

    const dayMap = new Map<string, DayData>();
    for (const d of this.days) dayMap.set(d.datum, d);

    const start = new Date(this.from);
    const end = new Date(this.to);

    // Align start to Monday of that week
    const dow = start.getDay(); // 0=Sun
    const mondayOffset = dow === 0 ? -6 : 1 - dow;
    start.setDate(start.getDate() + mondayOffset);

    const rows: WeekRow[] = [];
    let current = new Date(start);

    while (current <= end) {
      const cells: (DayData | null)[] = [];
      for (let i = 0; i < 7; i++) {
        const iso = this.toIso(current);
        const d = dayMap.get(iso) ?? null;
        // Only include days within our range
        cells.push(current >= new Date(this.from) && current <= end ? d : null);
        current.setDate(current.getDate() + 1);
      }
      const weekStart = new Date(current);
      weekStart.setDate(weekStart.getDate() - 7);
      rows.push({ weekLabel: this.weekLabel(weekStart), cells });
    }

    this.weekRows = rows;
  }

  private toIso(d: Date): string {
    return d.toISOString().slice(0, 10);
  }

  private weekLabel(monday: Date): string {
    const wn = this.isoWeekNumber(monday);
    return `v${wn}`;
  }

  private isoWeekNumber(date: Date): number {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + 4 - (d.getDay() || 7));
    const yearStart = new Date(d.getFullYear(), 0, 1);
    return Math.ceil((((d.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
  }

  cellColor(cell: DayData | null): string {
    if (!cell) return 'var(--cell-empty)';
    const v = cell.vs_avg;
    if (v >= 20)  return 'var(--cell-great)';
    if (v >= 10)  return 'var(--cell-good)';
    if (v >= 0)   return 'var(--cell-ok)';
    if (v >= -10) return 'var(--cell-weak)';
    if (v >= -20) return 'var(--cell-bad)';
    return 'var(--cell-worst)';
  }

  cellTitle(cell: DayData | null): string {
    if (!cell) return '';
    const sign = cell.vs_avg >= 0 ? '+' : '';
    return `${cell.datum} — ${cell.ibc_per_h} IBC/h (${sign}${cell.vs_avg}% vs snitt, ${cell.skift_count} skift)`;
  }

  formatDate(iso: string): string {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y.slice(2)}`;
  }

  trackByWeek(_: number, row: WeekRow): string { return row.weekLabel; }
}
