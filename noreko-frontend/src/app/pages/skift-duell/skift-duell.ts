import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface OpItem { number: string; name: string; }

interface ShiftPoint {
  dag: string;
  sk: number;
  ibc_h: number | null;
  together: boolean;
}

interface OpData {
  number: number;
  name: string;
  shifts: ShiftPoint[];
  avg_ibc_h: number | null;
  median_ibc_h: number | null;
  best_ibc_h: number | null;
  shifts_count: number;
  together_avg: number | null;
  alone_avg: number | null;
  together_count: number;
}

interface DuellData {
  a: OpData;
  b: OpData;
  team_avg: number | null;
  from: string;
  to: string;
  days: number;
}

@Component({
  selector: 'app-skift-duell',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './skift-duell.html',
  styleUrls: ['./skift-duell.css'],
})
export class SkiftDuellPage implements OnInit, OnDestroy {
  Math = Math;

  operators: OpItem[] = [];
  selectedA = 0;
  selectedB = 0;
  days = 90;
  daysOptions = [30, 60, 90, 180, 365];

  loading = false;
  error = '';
  data: DuellData | null = null;

  private chart: Chart | null = null;
  private destroy$ = new Subject<void>();

  get canCompare(): boolean {
    return this.selectedA > 0 && this.selectedB > 0 && +this.selectedA !== +this.selectedB;
  }
  get availableForA(): OpItem[] { return this.operators.filter(o => +o.number !== +this.selectedB); }
  get availableForB(): OpItem[] { return this.operators.filter(o => +o.number !== +this.selectedA); }

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.http
      .get<any>(`${environment.apiUrl}?action=rebotling&run=skift-duell`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => { if (res?.success) this.operators = res.operators ?? []; });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chart) { this.chart.destroy(); this.chart = null; }
  }

  compare(): void {
    if (!this.canCompare || this.loading) return;
    this.loading = true;
    this.error = '';
    this.data = null;
    if (this.chart) { this.chart.destroy(); this.chart = null; }

    const url = `${environment.apiUrl}?action=rebotling&run=skift-duell&op_a=${this.selectedA}&op_b=${this.selectedB}&days=${this.days}`;
    this.http
      .get<any>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) {
          this.error = res?.error ?? 'Serverfel — kontrollera logg';
          return;
        }
        this.data = res.data as DuellData;
        setTimeout(() => this.buildChart(), 50);
      });
  }

  private rollingAvg(shifts: ShiftPoint[], window: number): { x: number; y: number }[] {
    const sorted = [...shifts]
      .filter(s => s.ibc_h !== null)
      .sort((a, b) => Date.parse(a.dag) - Date.parse(b.dag));
    return sorted.map((s, i) => {
      const start = Math.max(0, i - window + 1);
      const slice = sorted.slice(start, i + 1);
      const avg = slice.reduce((acc, p) => acc + (p.ibc_h ?? 0), 0) / slice.length;
      return { x: Date.parse(s.dag), y: Math.round(avg * 10) / 10 };
    });
  }

  private buildChart(): void {
    if (!this.data) return;
    const canvas = document.getElementById('duellChart') as HTMLCanvasElement | null;
    if (!canvas) return;
    if (this.chart) this.chart.destroy();

    const { a, b, team_avg, from, to } = this.data;
    const colorA = '#4299e1';
    const colorAT = '#bee3f8'; // together: lighter to mark shared shifts
    const colorB = '#f6ad55';
    const colorBT = '#feebc8';

    const pts = (shifts: ShiftPoint[], tog: boolean) =>
      shifts
        .filter(s => s.ibc_h !== null && s.together === tog)
        .map(s => ({ x: Date.parse(s.dag), y: s.ibc_h as number, dag: s.dag, sk: s.sk }));

    const raA = this.rollingAvg(a.shifts, 7);
    const raB = this.rollingAvg(b.shifts, 7);
    const minMs = Date.parse(from);
    const maxMs = Date.parse(to) + 86400000;

    const teamLine = team_avg != null
      ? [{ x: minMs, y: team_avg }, { x: maxMs, y: team_avg }]
      : [];

    const fmtDate = (ms: number): string => {
      const d = new Date(ms);
      return d.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
    };

    this.chart = new Chart(canvas, {
      type: 'scatter',
      data: {
        datasets: [
          {
            label: a.name + ' (ensam)',
            data: pts(a.shifts, false) as any,
            backgroundColor: colorA + 'bb',
            borderColor: colorA,
            pointRadius: 5,
            pointHoverRadius: 7,
          },
          {
            label: a.name + ' (med ' + b.name + ')',
            data: pts(a.shifts, true) as any,
            backgroundColor: colorAT,
            borderColor: colorA,
            pointRadius: 7,
            pointStyle: 'triangle',
            pointHoverRadius: 9,
          },
          {
            label: b.name + ' (ensam)',
            data: pts(b.shifts, false) as any,
            backgroundColor: colorB + 'bb',
            borderColor: colorB,
            pointRadius: 5,
            pointHoverRadius: 7,
          },
          {
            label: b.name + ' (med ' + a.name + ')',
            data: pts(b.shifts, true) as any,
            backgroundColor: colorBT,
            borderColor: colorB,
            pointRadius: 7,
            pointStyle: 'triangle',
            pointHoverRadius: 9,
          },
          {
            label: a.name + ' (7-skift snitt)',
            data: raA as any,
            type: 'line' as any,
            borderColor: colorA,
            backgroundColor: 'transparent',
            pointRadius: 0,
            borderWidth: 2,
            tension: 0.3,
          },
          {
            label: b.name + ' (7-skift snitt)',
            data: raB as any,
            type: 'line' as any,
            borderColor: colorB,
            backgroundColor: 'transparent',
            pointRadius: 0,
            borderWidth: 2,
            tension: 0.3,
          },
          ...(teamLine.length
            ? [{
                label: 'Lagsnitt',
                data: teamLine as any,
                type: 'line' as any,
                borderColor: '#718096',
                backgroundColor: 'transparent',
                pointRadius: 0,
                borderWidth: 1,
                borderDash: [6, 4],
              }]
            : []),
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 11 }, boxWidth: 12 },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#90cdf4',
            bodyColor: '#e2e8f0',
            callbacks: {
              label: (ctx: any) => {
                const raw = ctx.raw as any;
                if (raw?.dag) return ` ${ctx.dataset.label}: ${raw.y} IBC/h (${raw.dag}, #${raw.sk})`;
                return ` ${ctx.dataset.label}: ${ctx.parsed.y} IBC/h`;
              },
            },
          },
        },
        scales: {
          x: {
            type: 'linear',
            min: minMs,
            max: maxMs,
            ticks: {
              color: '#e2e8f0',
              maxTicksLimit: 10,
              callback: (val: any) => fmtDate(val),
            },
            grid: { color: '#2d374855' },
          },
          y: {
            ticks: { color: '#e2e8f0' },
            grid: { color: '#2d374855' },
            title: { display: true, text: 'IBC/h', color: '#a0aec0' },
          },
        },
      },
    });
  }

  kpiClass(val: number | null, ref: number | null): string {
    if (val == null || ref == null) return '';
    const pct = ((val - ref) / ref) * 100;
    if (pct >= 10) return 'kpi-good';
    if (pct <= -10) return 'kpi-bad';
    return 'kpi-neutral';
  }

  synergyDelta(): number | null {
    if (!this.data) return null;
    const { a, b } = this.data;
    if (a.together_avg == null || a.alone_avg == null) return null;
    return Math.round((a.together_avg - a.alone_avg) * 10) / 10;
  }

  synergyDeltaB(): number | null {
    if (!this.data) return null;
    const { b } = this.data;
    if (b.together_avg == null || b.alone_avg == null) return null;
    return Math.round((b.together_avg - b.alone_avg) * 10) / 10;
  }

  synergyClass(delta: number | null): string {
    if (delta == null) return '';
    if (delta > 0) return 'kpi-good';
    if (delta < 0) return 'kpi-bad';
    return 'kpi-neutral';
  }

  fmt(v: number | null): string {
    return v != null ? v.toFixed(1) : '—';
  }
}
