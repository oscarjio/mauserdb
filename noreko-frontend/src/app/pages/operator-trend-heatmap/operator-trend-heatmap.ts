import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface HeatCell {
  week: string;
  ibc_per_h: number | null;
  vs_team_pct: number | null;
  antal_skift: number;
}

interface TeamAvgCell {
  week: string;
  ibc_per_h: number | null;
}

interface OperatorRow {
  number: number;
  name: string;
  cells: HeatCell[];
  trend_dir: number | null;
  recent_avg: number;
}

interface ApiResponse {
  success: boolean;
  data: {
    weeks: string[];
    operators: OperatorRow[];
    team_avg_cells: TeamAvgCell[];
    period: { from: string; to: string };
  };
}

@Component({
  standalone: true,
  selector: 'app-operator-trend-heatmap',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-trend-heatmap.html',
  styleUrl: './operator-trend-heatmap.css',
})
export class OperatorTrendHeatmapPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  loading = false;
  error = '';
  weeks = 12;
  operators: OperatorRow[] = [];
  teamAvgCells: TeamAvgCell[] = [];
  weekLabels: string[] = [];
  period: { from: string; to: string } | null = null;
  tooltip: { visible: boolean; text: string; x: number; y: number } = { visible: false, text: '', x: 0, y: 0 };

  readonly weekOptions = [8, 12, 16, 24];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.fetchData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  fetchData(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=operator-trend-heatmap&weeks=${this.weeks}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.operators     = res.data.operators;
        this.teamAvgCells  = res.data.team_avg_cells;
        this.weekLabels    = res.data.weeks.map(w => this.shortWeekLabel(w));
        this.period        = res.data.period;
      });
  }

  cellBg(pct: number | null): string {
    if (pct === null) return '#1a202c';
    if (pct >= 25)  return '#276749';
    if (pct >= 12)  return '#38a169';
    if (pct >= 4)   return '#48bb7877';
    if (pct >= -4)  return '#4a5568';
    if (pct >= -12) return '#c0562188';
    if (pct >= -25) return '#c05621';
    return '#9b2c2c';
  }

  teamBg(iph: number | null): string {
    return iph !== null ? '#2d3748' : '#1a202c';
  }

  trendIcon(dir: number | null): string {
    if (dir === null) return '';
    if (dir >= 5)  return '↑';
    if (dir <= -5) return '↓';
    return '→';
  }

  trendClass(dir: number | null): string {
    if (dir === null) return 'text-secondary';
    if (dir >= 5)  return 'text-success';
    if (dir <= -5) return 'text-danger';
    return 'text-warning';
  }

  showTooltip(event: MouseEvent, cell: HeatCell, opName: string): void {
    const label = this.shortWeekLabel(cell.week);
    if (cell.ibc_per_h === null) {
      this.tooltip = { visible: true, text: `${opName} — v. ${label}: Inga skift`, x: event.clientX + 12, y: event.clientY - 8 };
      return;
    }
    const vs = cell.vs_team_pct !== null
      ? (cell.vs_team_pct >= 0 ? `+${cell.vs_team_pct}%` : `${cell.vs_team_pct}%`)
      : '';
    this.tooltip = {
      visible: true,
      text: `${opName} — v. ${label}: ${cell.ibc_per_h} IBC/h (${vs} vs snitt, ${cell.antal_skift} skift)`,
      x: event.clientX + 12,
      y: event.clientY - 8,
    };
  }

  hideTooltip(): void {
    this.tooltip.visible = false;
  }

  private shortWeekLabel(dateStr: string): string {
    const d = new Date(dateStr);
    const jan4 = new Date(d.getFullYear(), 0, 4);
    const startOfWeek1 = new Date(jan4);
    startOfWeek1.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7));
    const weekNum = Math.round((d.getTime() - startOfWeek1.getTime()) / 604800000) + 1;
    return `v${weekNum}`;
  }
}
