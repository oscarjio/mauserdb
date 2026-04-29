import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface HeatCell {
  dow_label: string;
  dow_idx: number;
  stopp_pct: number | null;
  snitt_stopp_min: number | null;
  pct_med_stopp: number | null;
  tot_stopp: number;
  tot_drift: number;
  antal_skift: number;
}

interface GridRow {
  skift_typ: string;
  label: string;
  cells: HeatCell[];
  row_avg: number;
}

interface BestWorst {
  typ: string;
  typ_label: string;
  dow_idx: number;
  dow_label: string;
  stopp_pct: number;
  antal: number;
}

interface TrendPoint {
  manad: string;
  stopp_pct: number;
  snitt_stopp_min: number;
  antal_skift: number;
  pct_med_stopp: number;
}

interface ApiResponse {
  success: boolean;
  grid: GridRow[];
  col_avgs: (number | null)[];
  dow_labels: string[];
  period_stopp_pct: number;
  worst_cell: BestWorst | null;
  best_cell: BestWorst | null;
  trend: TrendPoint[];
  from: string;
  to: string;
  days: number;
}

@Component({
  standalone: true,
  selector: 'app-stopptidsmonster',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './stopptidsmonster.html',
  styleUrl: './stopptidsmonster.css'
})
export class StopptidsmönsterPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private trendChart: Chart | null = null;

  @ViewChild('trendCanvas') trendCanvas!: ElementRef<HTMLCanvasElement>;

  Math = Math;

  loading = false;
  error = '';
  days = 180;

  grid: GridRow[] = [];
  colAvgs: (number | null)[] = [];
  dowLabels: string[] = [];
  periodStoppPct = 0;
  worstCell: BestWorst | null = null;
  bestCell: BestWorst | null = null;
  trend: TrendPoint[] = [];
  from = '';
  to = '';

  // tooltip
  hoveredCell: (HeatCell & { rowLabel: string }) | null = null;

  // Which metric to display in heatmap
  metric: 'stopp_pct' | 'snitt_stopp_min' | 'pct_med_stopp' = 'stopp_pct';

  readonly metricOptions = [
    { val: 'stopp_pct',       label: 'Stoppgrad (%drifttid)' },
    { val: 'snitt_stopp_min', label: 'Snitt stopp/skift (min)' },
    { val: 'pct_med_stopp',   label: '% skift med stopp' },
  ] as const;

  readonly SKIFT_COLORS: Record<string, string> = {
    dag:   '#63b3ed',
    kvall: '#f6ad55',
    natt:  '#a78bfa',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.trendChart?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=stopptidsmonster&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta stopptidsdata.';
        return;
      }
      this.grid             = res.grid;
      this.colAvgs          = res.col_avgs;
      this.dowLabels        = res.dow_labels;
      this.periodStoppPct   = res.period_stopp_pct;
      this.worstCell        = res.worst_cell;
      this.bestCell         = res.best_cell;
      this.trend            = res.trend;
      this.from             = res.from;
      this.to               = res.to;
      setTimeout(() => this.buildTrendChart(), 50);
    });
  }

  setDays(d: number): void { this.days = d; this.load(); }

  cellValue(cell: HeatCell): number | null {
    if (this.metric === 'stopp_pct')       return cell.stopp_pct;
    if (this.metric === 'snitt_stopp_min') return cell.snitt_stopp_min;
    return cell.pct_med_stopp;
  }

  // Color scale: 0% stopp = green, high % = red. Uses period avg as midpoint.
  cellColor(val: number | null): string {
    if (val === null) return '#2d3748';
    const ref = this.metric === 'snitt_stopp_min'
      ? this.avgSnittStopp()
      : this.periodStoppPct;
    if (ref <= 0) return '#a0aec0';
    const ratio = val / (ref * 2); // 0 = best, 1 = 2× avg (worst)
    const clamped = Math.min(1, Math.max(0, ratio));
    const r = Math.round(252 * clamped + 72 * (1 - clamped));
    const g = Math.round(129 * clamped + 211 * (1 - clamped));
    const b = Math.round(74  * clamped + 145 * (1 - clamped));
    return `rgb(${r},${g},${b})`;
  }

  textColor(val: number | null): string {
    if (val === null) return '#718096';
    const ref = this.metric === 'snitt_stopp_min'
      ? this.avgSnittStopp()
      : this.periodStoppPct;
    if (ref <= 0) return '#e2e8f0';
    const ratio = Math.min(1, val / (ref * 2));
    return ratio > 0.4 ? '#1a202c' : '#e2e8f0';
  }

  avgSnittStopp(): number {
    let tot = 0; let n = 0;
    for (const row of this.grid) {
      for (const c of row.cells) {
        if (c.snitt_stopp_min !== null) { tot += c.snitt_stopp_min; n++; }
      }
    }
    return n > 0 ? tot / n : 0;
  }

  rowMetricAvg(row: GridRow): number {
    if (this.metric === 'stopp_pct') return row.row_avg;
    let tot = 0; let n = 0;
    for (const c of row.cells) {
      const v = this.cellValue(c);
      if (v !== null) { tot += v; n++; }
    }
    return n > 0 ? +(tot / n).toFixed(1) : 0;
  }

  formatCell(val: number | null): string {
    if (val === null) return '–';
    if (this.metric === 'snitt_stopp_min') return val.toFixed(0) + ' min';
    return val.toFixed(1) + '%';
  }

  formatColAvg(v: number | null): string {
    if (v === null) return '–';
    return v.toFixed(1) + '%';
  }

  hover(cell: HeatCell, rowLabel: string): void {
    this.hoveredCell = { ...cell, rowLabel };
  }

  clearHover(): void { this.hoveredCell = null; }

  skiftColor(typ: string): string { return this.SKIFT_COLORS[typ] ?? '#a0aec0'; }

  private buildTrendChart(): void {
    this.trendChart?.destroy();
    const canvas = this.trendCanvas?.nativeElement;
    if (!canvas || this.trend.length === 0) return;

    const labels = this.trend.map(t => t.manad);
    const data   = this.trend.map(t => t.stopp_pct);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Stoppgrad %',
          data,
          borderColor: '#fc8181',
          backgroundColor: 'rgba(252,129,129,0.15)',
          fill: true,
          tension: 0.3,
          pointRadius: 4,
          pointBackgroundColor: '#fc8181',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const t = this.trend[ctx.dataIndex];
                return [
                  `Stoppgrad: ${t.stopp_pct.toFixed(1)}%`,
                  `Snitt stopp/skift: ${t.snitt_stopp_min.toFixed(0)} min`,
                  `Skift med stopp: ${t.pct_med_stopp.toFixed(0)}%`,
                  `Antal skift: ${t.antal_skift}`,
                ];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid:  { color: 'rgba(255,255,255,0.07)' },
            min: 0,
          },
        },
      },
    });
  }

  anyData(): boolean {
    return this.grid.some(row => row.cells.some(c => c.antal_skift > 0));
  }
}
