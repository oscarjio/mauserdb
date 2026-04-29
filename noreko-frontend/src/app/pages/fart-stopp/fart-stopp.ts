import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);
Chart.defaults.color = '#e2e8f0';

interface ShiftPoint {
  skiftraknare: number;
  datum: string;
  ibc_h: number;
  stopp_pct: number;
  product_id: number;
  product_name: string;
  shift_type: 'Dag' | 'Kväll' | 'Natt';
  dow: number;
  dow_label: string;
  op1: number; op2: number; op3: number;
}

interface Stats {
  count: number;
  mean_ibc_h: number;
  mean_stopp_pct: number;
  median_ibc_h: number;
  median_stopp_pct: number;
  correlation: number;
  corr_strength: string;
}

interface Product {
  id: number;
  name: string;
  count: number;
}

interface ApiResponse {
  success: boolean;
  shifts: ShiftPoint[];
  stats: Stats;
  quadrants: { optimal: number; resilient: number; coasting: number; problem: number };
  products: Product[];
  from: string;
  to: string;
  days: number;
}

const SHIFT_COLORS: Record<string, string> = {
  Dag:   '#68d391',
  Kväll: '#f6ad55',
  Natt:  '#a78bfa',
};

const DOW_COLORS = ['#63b3ed', '#68d391', '#f6ad55', '#fc8181', '#a78bfa'];
const PROD_COLORS = ['#68d391', '#63b3ed', '#f6ad55', '#fc8181', '#a78bfa', '#76e4f7', '#f6c90e', '#e2e8f0'];
const WORK_DAYS = [2, 3, 4, 5, 6];
const DOW_NAMES: Record<number, string> = { 2: 'Måndag', 3: 'Tisdag', 4: 'Onsdag', 5: 'Torsdag', 6: 'Fredag' };

@Component({
  standalone: true,
  selector: 'app-fart-stopp',
  imports: [CommonModule, FormsModule],
  templateUrl: './fart-stopp.html',
  styleUrl: './fart-stopp.css',
})
export class FartStoppPage implements OnInit, OnDestroy {
  @ViewChild('chartCanvas', { static: false }) chartCanvas!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;
  private isFetching = false;

  loading = false;
  error = '';
  days = 90;
  colorBy: 'shift_type' | 'product' | 'dow' = 'shift_type';

  shifts: ShiftPoint[] = [];
  stats: Stats | null = null;
  quadrants: { optimal: number; resilient: number; coasting: number; problem: number } | null = null;
  products: Product[] = [];
  from = '';
  to = '';

  Math = Math;
  readonly dayOptions = [30, 90, 180, 365];

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=fart-stopp&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta data.';
        return;
      }
      this.shifts    = res.shifts;
      this.stats     = res.stats;
      this.quadrants = res.quadrants;
      this.products  = res.products;
      this.from      = res.from;
      this.to        = res.to;
      setTimeout(() => this.buildChart(), 0);
    });
  }

  onDaysChange(): void { this.load(); }

  onColorByChange(): void {
    if (this.shifts.length) setTimeout(() => this.buildChart(), 0);
  }

  buildChart(): void {
    this.chart?.destroy();
    if (!this.chartCanvas || !this.shifts.length || !this.stats) return;

    const medianX = this.stats.median_stopp_pct;
    const medianY = this.stats.median_ibc_h;

    let datasets: any[];

    if (this.colorBy === 'shift_type') {
      const types: Array<'Dag' | 'Kväll' | 'Natt'> = ['Dag', 'Kväll', 'Natt'];
      datasets = types.map(t => ({
        label: t,
        data: this.shifts.filter(s => s.shift_type === t)
          .map(s => ({ x: s.stopp_pct, y: s.ibc_h, shift: s })),
        backgroundColor: SHIFT_COLORS[t] + 'aa',
        borderColor: SHIFT_COLORS[t],
        borderWidth: 1,
        pointRadius: 5,
        pointHoverRadius: 8,
      }));
    } else if (this.colorBy === 'product') {
      datasets = this.products.slice(0, 8).map((p, i) => ({
        label: p.name,
        data: this.shifts.filter(s => s.product_id === p.id)
          .map(s => ({ x: s.stopp_pct, y: s.ibc_h, shift: s })),
        backgroundColor: PROD_COLORS[i % PROD_COLORS.length] + 'aa',
        borderColor: PROD_COLORS[i % PROD_COLORS.length],
        borderWidth: 1,
        pointRadius: 5,
        pointHoverRadius: 8,
      }));
    } else {
      datasets = WORK_DAYS.map((dow, i) => ({
        label: DOW_NAMES[dow],
        data: this.shifts.filter(s => s.dow === dow)
          .map(s => ({ x: s.stopp_pct, y: s.ibc_h, shift: s })),
        backgroundColor: DOW_COLORS[i] + 'aa',
        borderColor: DOW_COLORS[i],
        borderWidth: 1,
        pointRadius: 5,
        pointHoverRadius: 8,
      }));
    }

    this.chart = new Chart(this.chartCanvas.nativeElement, {
      type: 'scatter',
      data: { datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#90cdf4',
            bodyColor: '#e2e8f0',
            callbacks: {
              label: (ctx: any) => {
                const s: ShiftPoint = ctx.raw.shift;
                return [
                  ` ${s.datum} — skift #${s.skiftraknare}`,
                  ` IBC/h: ${s.ibc_h.toFixed(1)}`,
                  ` Stoppgrad: ${s.stopp_pct.toFixed(1)}%`,
                  ` ${s.product_name} · ${s.shift_type} · ${s.dow_label}`,
                ];
              },
            },
          },
        },
        scales: {
          x: {
            title: { display: true, text: 'Stoppgrad (% av drifttid)', color: '#a0aec0', font: { size: 12 } },
            grid: { color: '#2d374866' },
            ticks: { color: '#e2e8f0' },
            min: 0,
          },
          y: {
            title: { display: true, text: 'IBC/h (produktionstakt)', color: '#a0aec0', font: { size: 12 } },
            grid: { color: '#2d374866' },
            ticks: { color: '#e2e8f0' },
          },
        },
      },
      plugins: [
        {
          id: 'medianLines',
          afterDraw: (chart: Chart) => {
            const ctx2 = chart.ctx;
            const xScale = chart.scales['x'];
            const yScale = chart.scales['y'];
            const xPx = xScale.getPixelForValue(medianX);
            const yPx = yScale.getPixelForValue(medianY);
            ctx2.save();
            ctx2.setLineDash([5, 4]);
            ctx2.strokeStyle = '#718096';
            ctx2.lineWidth = 1;
            ctx2.beginPath();
            ctx2.moveTo(xPx, chart.chartArea.top);
            ctx2.lineTo(xPx, chart.chartArea.bottom);
            ctx2.stroke();
            ctx2.beginPath();
            ctx2.moveTo(chart.chartArea.left, yPx);
            ctx2.lineTo(chart.chartArea.right, yPx);
            ctx2.stroke();
            ctx2.setLineDash([]);
            ctx2.fillStyle = '#4a5568';
            ctx2.font = '11px sans-serif';
            const pad = 6;
            ctx2.textAlign = 'left';
            ctx2.fillText('Optimal', chart.chartArea.left + pad, yPx - pad);
            ctx2.fillText('Problem', chart.chartArea.left + pad, yPx + 14);
            ctx2.textAlign = 'right';
            ctx2.fillText('Resilient', chart.chartArea.right - pad, yPx - pad);
            ctx2.fillText('Kustar', chart.chartArea.right - pad, yPx + 14);
            ctx2.textAlign = 'left';
            ctx2.restore();
          },
        },
      ],
    });
  }

  get corrClass(): string {
    const s = this.stats?.corr_strength ?? '';
    if (s.includes('negativ')) return 'text-success';
    if (s.includes('positiv')) return 'text-danger';
    return 'text-muted';
  }

  get corrIcon(): string {
    const s = this.stats?.corr_strength ?? '';
    if (s.includes('stark negativ'))   return 'fas fa-arrow-down';
    if (s.includes('måttlig negativ')) return 'fas fa-arrow-down';
    if (s.includes('svag negativ'))    return 'fas fa-minus';
    if (s.includes('positiv'))         return 'fas fa-arrow-up';
    return 'fas fa-minus';
  }

  corrLabel(): string {
    const s = this.stats?.corr_strength ?? '';
    const map: Record<string, string> = {
      'stark negativ':     'Stark negativ',
      'måttlig negativ':   'Måttlig negativ',
      'svag negativ':      'Svag negativ',
      'stark positiv':     'Stark positiv',
      'måttlig positiv':   'Måttlig positiv',
      'svag positiv':      'Svag positiv',
      'ingen korrelation': 'Ingen',
    };
    return map[s] ?? s;
  }

  corrExplanation(): string {
    const s = this.stats?.corr_strength ?? '';
    if (s.includes('stark negativ'))   return 'Tydligt samband: skift med mycket maskinstopp producerar markant färre IBC/h. Underhåll och stoppreducering är lönsamt.';
    if (s.includes('måttlig negativ')) return 'Maskinstopp dämpar produktionstakten. Operatörerna kompenserar delvis men stoppgrad har mätbar påverkan.';
    if (s.includes('svag negativ'))    return 'Svag tendens: mer maskinstopp ger något lägre IBC/h. Övriga faktorer (operatör, produkt) spelar stor roll.';
    if (s.includes('positiv'))         return 'Oväntat mönster: hög stoppgrad samvarierar med hög IBC/h. Möjlig förklaring: korta stopp registreras vid intensiva skift.';
    return 'Ingen tydlig koppling mellan maskinstoppgrad och produktionstakt — operatörerna verkar kompensera effektivt.';
  }

  sign(n: number): string { return n > 0 ? '+' : ''; }
}
