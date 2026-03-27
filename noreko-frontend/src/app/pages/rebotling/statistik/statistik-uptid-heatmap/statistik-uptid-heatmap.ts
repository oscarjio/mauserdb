import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import {
  RebotlingService,
  UptimeHeatmapCell,
  UptimeHeatmapResponse,
} from '../../../../services/rebotling.service';

interface HeatmapDayRow {
  date: string;
  dayLabel: string;
  dateLabel: string;
  cells: UptimeHeatmapCell[];
}

interface SummaryStats {
  totalHours: number;
  runningHours: number;
  stoppedHours: number;
  idleHours: number;
  uptimePct: number;
  longestStopHours: number;
  bestDayLabel: string;
  bestDayPct: number;
}

@Component({
  standalone: true,
  selector: 'app-statistik-uptid-heatmap',
  templateUrl: './statistik-uptid-heatmap.html',
  styleUrls: ['./statistik-uptid-heatmap.css'],
  imports: [CommonModule, FormsModule],
})
export class StatistikUptidHeatmapComponent implements OnInit, OnDestroy {
  loading = false;
  error: string | null = null;

  days = 7;
  hours = Array.from({ length: 24 }, (_, i) => i);

  rows: HeatmapDayRow[] = [];
  summary: SummaryStats | null = null;

  // Tooltip state
  tooltip: {
    visible: boolean;
    cell: UptimeHeatmapCell | null;
    x: number;
    y: number;
  } = { visible: false, cell: null, x: 0, y: 0 };

  // Selected cell (click)
  selectedCell: UptimeHeatmapCell | null = null;

  // Max IBC for gradient scaling
  private maxIbc = 1;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;

  private readonly SWEDISH_DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  private readonly SWEDISH_DAY_NAMES: Record<string, string> = {
    Mon: 'Mån',
    Tue: 'Tis',
    Wed: 'Ons',
    Thu: 'Tor',
    Fri: 'Fre',
    Sat: 'Lör',
    Sun: 'Sön',
  };

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    this.load();
    this.refreshInterval = setInterval(() => this.load(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval !== null) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  onDaysChange(): void {
    this.load();
  }

  load(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = null;

    this.rebotlingService
      .getMachineUptimeHeatmap(this.days)
      .pipe(
        catchError(() => {
          this.error = 'Kunde inte hämta maskinupptid-data.';
          this.loading = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe((resp: UptimeHeatmapResponse | null) => {
        this.loading = false;
        if (!resp || !resp.success || !resp.cells) {
          if (!this.error) this.error = resp?.error ?? 'Okänt fel';
          return;
        }
        this.maxIbc = Math.max(1, ...resp.cells.map(c => c.ibc_count || 0));
        this.buildRows(resp.cells);
        this.buildSummary(resp.cells);
      });
  }

  private buildRows(cells: UptimeHeatmapCell[]): void {
    // Group by date
    const byDate: Record<string, UptimeHeatmapCell[]> = {};
    for (const cell of cells) {
      if (!byDate[cell.date]) byDate[cell.date] = [];
      byDate[cell.date].push(cell);
    }

    // Sort dates ascending
    const dates = Object.keys(byDate).sort();

    this.rows = dates.map((date) => {
      const dayOfWeek = new Date(date + 'T12:00:00').toLocaleDateString('en-US', {
        weekday: 'short',
      });
      const dayName = this.SWEDISH_DAY_NAMES[dayOfWeek] ?? dayOfWeek;
      const dateShort = new Date(date + 'T12:00:00').toLocaleDateString('sv-SE', {
        day: 'numeric',
        month: 'short',
      });

      // Ensure all 24 hours present
      const cellMap: Record<number, UptimeHeatmapCell> = {};
      for (const c of byDate[date]) {
        cellMap[c.hour] = c;
      }
      const allCells: UptimeHeatmapCell[] = [];
      for (let h = 0; h < 24; h++) {
        allCells.push(
          cellMap[h] ?? { date, hour: h, status: 'idle', ibc_count: 0, stop_minutes: 0 }
        );
      }

      return { date, dayLabel: dayName, dateLabel: dateShort, cells: allCells };
    });
  }

  private buildSummary(cells: UptimeHeatmapCell[]): void {
    const total = cells.length;
    const running = cells.filter((c) => c.status === 'running').length;
    const stopped = cells.filter((c) => c.status === 'stopped').length;
    const idle = total - running - stopped;

    // Longest consecutive stopped sequence
    let maxStop = 0;
    let curStop = 0;
    for (const c of cells) {
      if (c.status === 'stopped') {
        curStop++;
        maxStop = Math.max(maxStop, curStop);
      } else {
        curStop = 0;
      }
    }

    // Best day by running hour %
    const byDate: Record<string, { run: number; total: number; label: string }> = {};
    for (const c of cells) {
      if (!byDate[c.date]) byDate[c.date] = { run: 0, total: 0, label: c.date };
      byDate[c.date].total++;
      if (c.status === 'running') byDate[c.date].run++;
    }
    let bestPct = 0;
    let bestLabel = '-';
    for (const [date, v] of Object.entries(byDate)) {
      const pct = v.total > 0 ? (v.run / v.total) * 100 : 0;
      if (pct > bestPct) {
        bestPct = pct;
        const d = new Date(date + 'T12:00:00');
        const dayOfWeek = d.toLocaleDateString('en-US', { weekday: 'short' });
        const dayName = this.SWEDISH_DAY_NAMES[dayOfWeek] ?? dayOfWeek;
        bestLabel =
          dayName +
          ' ' +
          d.toLocaleDateString('sv-SE', { day: 'numeric', month: 'short' });
      }
    }

    this.summary = {
      totalHours: total,
      runningHours: running,
      stoppedHours: stopped,
      idleHours: idle,
      uptimePct: total > 0 ? Math.round((running / total) * 100) : 0,
      longestStopHours: maxStop,
      bestDayLabel: bestLabel,
      bestDayPct: Math.round(bestPct),
    };
  }

  getCellClass(cell: UptimeHeatmapCell): string {
    if (cell.status === 'running') return ''; // Background set via getCellBackground
    return `cell-${cell.status}`;
  }

  /** Gradient background for running cells based on IBC count */
  getCellBackground(cell: UptimeHeatmapCell): string {
    if (cell.status !== 'running') return '';
    const ratio = Math.min((cell.ibc_count || 0) / this.maxIbc, 1);
    // Green gradient: dark green (low) -> bright green (high)
    const r = Math.round(40 + (32 - 40) * ratio);
    const g = Math.round(120 + (187 - 120) * ratio);
    const b = Math.round(80 + (120 - 80) * ratio);
    return `rgb(${r}, ${g}, ${b})`;
  }

  showTooltip(event: MouseEvent, cell: UptimeHeatmapCell): void {
    const rect = (event.currentTarget as HTMLElement).closest('.uptid-heatmap-card')?.getBoundingClientRect();
    const cellRect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    if (rect) {
      this.tooltip = {
        visible: true,
        cell,
        x: cellRect.left - rect.left + cellRect.width / 2,
        y: cellRect.top - rect.top - 8,
      };
    } else {
      this.tooltip = {
        visible: true,
        cell,
        x: (event as MouseEvent).offsetX + 12,
        y: (event as MouseEvent).offsetY + 12,
      };
    }
  }

  hideTooltip(): void {
    this.tooltip = { visible: false, cell: null, x: 0, y: 0 };
  }

  onCellClick(cell: UptimeHeatmapCell): void {
    this.selectedCell = this.selectedCell === cell ? null : cell;
  }

  /** Total IBC for a day row */
  getDayTotal(row: HeatmapDayRow): number {
    return row.cells.reduce((sum, c) => sum + (c.ibc_count || 0), 0);
  }

  /** Total IBC for a given hour across all days */
  getHourTotal(hour: number): number {
    return this.rows.reduce((sum, row) => {
      const cell = row.cells[hour];
      return sum + (cell?.ibc_count || 0);
    }, 0);
  }

  formatHour(h: number): string {
    return h.toString().padStart(2, '0');
  }

  getStatusLabel(status: string): string {
    if (status === 'running') return 'Drift';
    if (status === 'stopped') return 'Stopp';
    return 'Ingen data';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
