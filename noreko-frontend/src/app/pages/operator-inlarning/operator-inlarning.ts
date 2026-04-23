import { Component, OnInit, OnDestroy, AfterViewInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface ShiftPoint {
  shift_nr: number;
  datum: string;
  ibc_h: number;
  rolling_3: number;
}

interface PositionData {
  pos_num: number;
  pos_name: string;
  total_shifts: number;
  team_avg: number;
  reached_avg_at: number | null;
  current_rolling: number;
  trend: 'okar' | 'minskar' | 'stabil';
  shifts: ShiftPoint[];
}

interface OperatorData {
  op_num: number;
  name: string;
  positions: PositionData[];
}

type TableRow = PositionData & { name: string; op_num: number };

interface ApiResponse {
  success: boolean;
  team_avg: { 1: number; 2: number; 3: number };
  pos_names: { 1: string; 2: string; 3: string };
  operators: OperatorData[];
  max_shifts: number;
}

const COLORS = [
  '#68d391', '#63b3ed', '#f6ad55', '#fc8181',
  '#b794f4', '#76e4f7', '#fbd38d', '#9ae6b4',
  '#90cdf4', '#feb2b2',
];

@Component({
  standalone: true,
  selector: 'app-operator-inlarning',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-inlarning.html',
  styleUrl: './operator-inlarning.css',
})
export class OperatorInlarningPage implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('chartCanvas') chartCanvas!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;

  Math = Math;

  maxShifts = 30;
  selectedPos = 1;
  selectedOps: Set<number> = new Set();

  loading = false;
  error = '';

  operators: OperatorData[] = [];
  teamAvg: { [key: number]: number } = {};
  posNames: { [key: number]: string } = { 1: 'Tvättplats', 2: 'Kontrollstation', 3: 'Truckförare' };

  viewReady = false;

  constructor(private http: HttpClient) {}

  ngOnInit() { this.load(); }
  ngAfterViewInit() { this.viewReady = true; }
  ngOnDestroy() { this.destroy$.next(); this.destroy$.complete(); this.chart?.destroy(); }

  load() {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=operator-inlarning&max_shifts=${this.maxShifts}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (res?.success) {
          this.operators = res.operators;
          this.teamAvg = res.team_avg as unknown as { [key: number]: number };
          // Pre-select all operators with data for current position
          this.updateDefaultSelection();
          setTimeout(() => this.rebuildChart(), 50);
        } else {
          this.error = 'Kunde inte hämta inlärningsdata.';
        }
      });
  }

  onMaxShiftsChange() { this.load(); }

  onPosChange() {
    this.updateDefaultSelection();
    this.rebuildChart();
  }

  updateDefaultSelection() {
    this.selectedOps.clear();
    // Pre-select first 5 operators that have data for this position
    let count = 0;
    for (const op of this.operators) {
      if (op.positions.some(p => p.pos_num === this.selectedPos)) {
        this.selectedOps.add(op.op_num);
        count++;
        if (count >= 5) break;
      }
    }
  }

  toggleOp(opNum: number) {
    if (this.selectedOps.has(opNum)) {
      if (this.selectedOps.size > 1) this.selectedOps.delete(opNum);
    } else {
      if (this.selectedOps.size < 8) this.selectedOps.add(opNum);
    }
    this.rebuildChart();
  }

  get opsForPos(): OperatorData[] {
    return this.operators.filter(op => op.positions.some(p => p.pos_num === this.selectedPos));
  }

  get tableRows(): TableRow[] {
    const rows: TableRow[] = [];
    for (const op of this.operators) {
      const pos = op.positions.find(p => p.pos_num === this.selectedPos);
      if (pos) rows.push({ ...pos, name: op.name, op_num: op.op_num });
    }
    rows.sort((a, b) => (b.current_rolling || 0) - (a.current_rolling || 0));
    return rows;
  }

  rebuildChart() {
    if (!this.viewReady || !this.chartCanvas) return;
    this.chart?.destroy();

    const datasets: any[] = [];
    let colorIdx = 0;

    for (const op of this.operators) {
      if (!this.selectedOps.has(op.op_num)) continue;
      const pos = op.positions.find(p => p.pos_num === this.selectedPos);
      if (!pos) continue;

      const color = COLORS[colorIdx % COLORS.length];
      colorIdx++;

      datasets.push({
        label: op.name,
        data: pos.shifts.map(s => ({ x: s.shift_nr, y: s.rolling_3 })),
        borderColor: color,
        backgroundColor: color + '22',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6,
        tension: 0.3,
        fill: false,
      });
    }

    const avg = this.teamAvg[this.selectedPos] || 0;
    if (avg > 0) {
      const maxNr = Math.max(...datasets.map(d => d.data.length > 0 ? d.data[d.data.length - 1].x : 0), this.maxShifts);
      datasets.push({
        label: 'Teamsnitt',
        data: [{ x: 1, y: avg }, { x: maxNr, y: avg }],
        borderColor: '#718096',
        borderDash: [6, 4],
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
        tension: 0,
      });
    }

    this.chart = new Chart(this.chartCanvas.nativeElement, {
      type: 'line',
      data: { datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            type: 'linear',
            title: { display: true, text: 'Skiftnummer', color: '#a0aec0' },
            ticks: { color: '#a0aec0', stepSize: 5 },
            grid: { color: '#2d3748' },
          },
          y: {
            title: { display: true, text: 'IBC/h (3-skift snitt)', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: '#2d3748' },
            min: 0,
          },
        },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', boxWidth: 12 },
          },
          tooltip: {
            callbacks: {
              title: (items) => `Skift #${items[0].parsed.x}`,
              label: (item) => `${item.dataset.label}: ${(item.parsed.y ?? 0).toFixed(1)} IBC/h`,
            },
          },
        },
      },
    });
  }

  trendIcon(t: string) {
    if (t === 'okar') return '↑';
    if (t === 'minskar') return '↓';
    return '→';
  }

  trendClass(t: string) {
    if (t === 'okar') return 'trend-up';
    if (t === 'minskar') return 'trend-down';
    return 'trend-stable';
  }

  opColor(opNum: number): string {
    const ops = this.operators.filter(o => this.selectedOps.has(o.op_num));
    const idx = ops.findIndex(o => o.op_num === opNum);
    return idx >= 0 ? COLORS[idx % COLORS.length] : '#718096';
  }

  vsAvgClass(row: { current_rolling: number; team_avg: number }): string {
    const pct = row.team_avg > 0 ? (row.current_rolling / row.team_avg) * 100 : 0;
    if (pct >= 115) return 'elite';
    if (pct >= 100) return 'solid';
    if (pct >= 85)  return 'developing';
    return 'needs-attention';
  }

  reachedLabel(row: { reached_avg_at: number | null; total_shifts: number }): string {
    if (row.reached_avg_at === null) return 'Ej nått';
    if (row.reached_avg_at === 1) return 'Direkt';
    return `Skift ${row.reached_avg_at}`;
  }
}
