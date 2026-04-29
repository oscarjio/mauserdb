import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface MonthEntry {
  month: string;
  yr: number;
  mo: number;
  kass_pct: number | null;
  total_tot: number;
  total_ej: number;
  num_shifts: number;
}

interface ProductEntry {
  id: number;
  name: string;
  avg_kass_pct: number;
  total_tot: number;
  total_ej: number;
  trend: string;
  delta_pp: number;
  monthly: MonthEntry[];
  visible: boolean;
}

interface TeamMonthly {
  month: string;
  kass_pct: number | null;
  num_shifts: number;
}

interface KpiData {
  overall_kass_pct: number;
  num_products: number;
  best_product: { name: string; kass_pct: number } | null;
  worst_product: { name: string; kass_pct: number } | null;
  most_improved: { name: string; delta_pp: number } | null;
  most_degraded: { name: string; delta_pp: number } | null;
}

interface ApiResponse {
  success: boolean;
  months: number;
  from: string;
  to: string;
  month_labels: string[];
  products: ProductEntry[];
  team_monthly: TeamMonthly[];
  kpi: KpiData;
}

const COLORS = [
  '#4fd1c5', '#f6ad55', '#fc8181', '#68d391', '#76e4f7',
  '#b794f4', '#fbb6ce', '#90cdf4', '#faf089', '#a0aec0',
];

@Component({
  standalone: true,
  selector: 'app-produkt-kvalitetstrender',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produkt-kvalitetstrender.html',
  styleUrl: './produkt-kvalitetstrender.css',
})
export class ProduktKvalitetstrender implements OnInit, OnDestroy {
  @ViewChild('chartCanvas', { static: false }) chartCanvas!: ElementRef<HTMLCanvasElement>;
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;

  loading = false;
  error = '';
  months = 12;

  monthLabels: string[] = [];
  products: ProductEntry[] = [];
  teamMonthly: TeamMonthly[] = [];
  kpi: KpiData | null = null;

  showTeamAvg = true;
  sortBy: 'kass' | 'name' | 'trend' = 'kass';

  readonly monthsOptions = [
    { value: 6,  label: '6 månader' },
    { value: 12, label: '12 månader' },
    { value: 18, label: '18 månader' },
    { value: 24, label: '24 månader' },
  ];

  Math = Math;

  get sortedProducts(): ProductEntry[] {
    const r = [...this.products];
    if (this.sortBy === 'name')  return r.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    if (this.sortBy === 'trend') return r.sort((a, b) => a.delta_pp - b.delta_pp);
    return r.sort((a, b) => a.avg_kass_pct - b.avg_kass_pct);
  }

  get visibleCount(): number { return this.products.filter(p => p.visible).length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
    this.chart = null;
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=produkt-kvalitetstrender&months=${this.months}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte hämta produktkvalitetsdata.'; return; }

        this.monthLabels = res.month_labels;
        this.teamMonthly = res.team_monthly;
        this.kpi         = res.kpi;
        this.products    = res.products.map((p, i) => ({ ...p, visible: true }));

        setTimeout(() => this.buildChart(), 50);
      });
  }

  setMonths(m: number): void {
    this.months = m;
    this.chart?.destroy();
    this.chart = null;
    this.load();
  }

  toggleProduct(p: ProductEntry): void {
    p.visible = !p.visible;
    this.updateChart();
  }

  toggleAll(show: boolean): void {
    this.products.forEach(p => p.visible = show);
    this.updateChart();
  }

  toggleTeamAvg(): void {
    this.updateChart();
  }

  private buildChart(): void {
    const canvas = this.chartCanvas?.nativeElement;
    if (!canvas) return;
    this.chart?.destroy();

    const xLabels = this.monthLabels.map(m => this.fmtMonth(m));

    const datasets: any[] = [];

    // One line per product
    this.products.forEach((p, i) => {
      if (!p.visible) return;
      const color = COLORS[i % COLORS.length];
      datasets.push({
        label: p.name,
        data: p.monthly.map(m => m.kass_pct),
        borderColor: color,
        backgroundColor: color + '22',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.3,
        spanGaps: true,
      });
    });

    // Team average dashed line
    if (this.showTeamAvg) {
      datasets.push({
        label: 'Lagssnitt',
        data: this.teamMonthly.map(m => m.kass_pct),
        borderColor: '#e2e8f0',
        borderDash: [6, 4],
        borderWidth: 1.5,
        pointRadius: 0,
        tension: 0.3,
        spanGaps: true,
      });
    }

    this.chart = new Chart(canvas, {
      type: 'line',
      data: { labels: xLabels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: '#e2e8f0', font: { size: 11 }, padding: 12 },
          },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y?.toFixed(1) ?? '-'}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: '#2d3748' },
          },
          y: {
            title: { display: true, text: 'Kassationsgrad (%)', color: '#a0aec0', font: { size: 11 } },
            min: 0,
            ticks: { color: '#a0aec0', callback: (v) => `${v}%` },
            grid: { color: '#2d3748' },
          },
        },
      },
    });
  }

  private updateChart(): void {
    if (!this.chart) { this.buildChart(); return; }

    const newDatasets: any[] = [];
    this.products.forEach((p, i) => {
      if (!p.visible) return;
      const color = COLORS[i % COLORS.length];
      newDatasets.push({
        label: p.name,
        data: p.monthly.map(m => m.kass_pct),
        borderColor: color,
        backgroundColor: color + '22',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.3,
        spanGaps: true,
      });
    });

    if (this.showTeamAvg) {
      newDatasets.push({
        label: 'Lagssnitt',
        data: this.teamMonthly.map(m => m.kass_pct),
        borderColor: '#e2e8f0',
        borderDash: [6, 4],
        borderWidth: 1.5,
        pointRadius: 0,
        tension: 0.3,
        spanGaps: true,
      });
    }

    this.chart.data.datasets = newDatasets;
    this.chart.update('none');
  }

  fmtMonth(m: string): string {
    if (!m) return '';
    const [yr, mo] = m.split('-').map(Number);
    const names = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    return `${names[mo - 1]} ${yr}`;
  }

  trendLabel(t: string): string {
    if (t === 'förbättras') return '↓ Förbättras';
    if (t === 'försämras')  return '↑ Försämras';
    return '→ Stabil';
  }

  trendClass(t: string): string {
    if (t === 'förbättras') return 'trend-better';
    if (t === 'försämras')  return 'trend-worse';
    return 'trend-stable';
  }

  deltaSign(d: number): string {
    return d >= 0 ? `+${d.toFixed(1)} pp` : `${d.toFixed(1)} pp`;
  }

  productColor(i: number): string {
    return COLORS[i % COLORS.length];
  }

  cellBg(kassMonth: number, avgKass: number): string {
    const diff = kassMonth - avgKass;
    if (diff < -2)  return 'rgba(72,187,120,0.25)';  // better than avg
    if (diff > 2)   return 'rgba(252,129,129,0.25)';  // worse than avg
    return 'rgba(45,55,72,0.6)';
  }
}
