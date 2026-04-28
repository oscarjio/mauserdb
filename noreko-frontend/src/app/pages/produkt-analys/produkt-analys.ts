import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import Chart from 'chart.js/auto';

interface Produkt {
  product_id: number;
  name: string;
  cycle_time_minutes: number;
  expected_ibc_h: number | null;
  antal_skift: number;
  total_ibc_ok: number;
  total_ibc_ej: number;
  total_bur_ej: number;
  total_drifttid_h: number;
  total_stopptid_h: number;
  ibc_per_h: number;
  kassgrad: number;
  vs_avg_pct: number;
}

interface TrendPoint {
  yr: number;
  mo: number;
  antal_skift: number;
  ibc_per_h: number;
}

interface ProduktAnalysResponse {
  success: boolean;
  products: Produkt[];
  trend: Record<number, TrendPoint[]>;
  overall_ibc_h: number;
  from: string;
  to: string;
  days: number;
}

@Component({
  standalone: true,
  selector: 'app-produkt-analys',
  imports: [CommonModule],
  templateUrl: './produkt-analys.html',
  styleUrl: './produkt-analys.css'
})
export class ProduktAnalysPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private barChart: Chart | null = null;
  private trendChart: Chart | null = null;

  @ViewChild('barCanvas') barCanvas!: ElementRef<HTMLCanvasElement>;
  @ViewChild('trendCanvas') trendCanvas!: ElementRef<HTMLCanvasElement>;

  days = 90;
  loading = false;
  error = '';

  products: Produkt[] = [];
  trend: Record<number, TrendPoint[]> = {};
  overallIbcH = 0;
  from = '';
  to = '';

  sortBy: 'ibc_per_h' | 'kassgrad' | 'antal_skift' | 'total_ibc_ok' | 'name' = 'ibc_per_h';
  sortAsc = false;
  activeTab: 'tabell' | 'trend' = 'tabell';

  Math = Math;

  get sorted(): Produkt[] {
    return [...this.products].sort((a, b) => {
      let diff: number;
      if (this.sortBy === 'name') diff = a.name.localeCompare(b.name, 'sv');
      else diff = (a[this.sortBy] as number) - (b[this.sortBy] as number);
      return this.sortAsc ? diff : -diff;
    });
  }

  get bestProduct(): Produkt | null {
    return this.products.length ? [...this.products].sort((a, b) => b.ibc_per_h - a.ibc_per_h)[0] : null;
  }

  get worstKassProduct(): Produkt | null {
    return this.products.length ? [...this.products].sort((a, b) => b.kassgrad - a.kassgrad)[0] : null;
  }

  get totalIbcOk(): number {
    return this.products.reduce((s, p) => s + p.total_ibc_ok, 0);
  }

  get totalIbcEj(): number {
    return this.products.reduce((s, p) => s + p.total_ibc_ej, 0);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.barChart?.destroy();
    this.trendChart?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<ProduktAnalysResponse>(
      `${environment.apiUrl}?action=rebotling&run=produkt-analys&days=${this.days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta produktdata.';
        return;
      }
      this.products    = res.products;
      this.trend       = res.trend;
      this.overallIbcH = res.overall_ibc_h;
      this.from        = res.from;
      this.to          = res.to;
      setTimeout(() => {
        this.drawBarChart();
        if (this.activeTab === 'trend') this.drawTrendChart();
      }, 0);
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.barChart?.destroy();
    this.barChart = null;
    this.trendChart?.destroy();
    this.trendChart = null;
    this.load();
  }

  setSort(col: typeof this.sortBy): void {
    if (this.sortBy === col) this.sortAsc = !this.sortAsc;
    else { this.sortBy = col; this.sortAsc = col === 'name'; }
  }

  setTab(t: typeof this.activeTab): void {
    this.activeTab = t;
    if (t === 'trend') setTimeout(() => this.drawTrendChart(), 0);
  }

  private drawBarChart(): void {
    if (!this.barCanvas?.nativeElement) return;
    this.barChart?.destroy();
    const sorted = [...this.products].sort((a, b) => b.ibc_per_h - a.ibc_per_h);
    this.barChart = new Chart(this.barCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: sorted.map(p => p.name),
        datasets: [
          {
            label: 'IBC/h (faktisk)',
            data: sorted.map(p => p.ibc_per_h),
            backgroundColor: sorted.map(p => this.ibchColor(p.vs_avg_pct)),
            borderRadius: 4,
          },
          {
            label: 'IBC/h (förväntat)',
            data: sorted.map(p => p.expected_ibc_h ?? 0),
            backgroundColor: 'rgba(160,174,192,0.4)',
            borderColor: 'rgba(160,174,192,0.7)',
            borderWidth: 1,
            borderRadius: 4,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: ctx => {
                const p = sorted[ctx.dataIndex];
                if (ctx.datasetIndex === 0) return ` ${p.ibc_per_h} IBC/h (${p.vs_avg_pct > 0 ? '+' : ''}${p.vs_avg_pct}% vs snitt)`;
                return ` Förväntat: ${p.expected_ibc_h ?? '-'} IBC/h`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'IBC/h', color: '#a0aec0' } }
        }
      }
    });
  }

  private drawTrendChart(): void {
    if (!this.trendCanvas?.nativeElement) return;
    this.trendChart?.destroy();

    const COLORS = ['#63b3ed', '#68d391', '#f6ad55', '#fc8181', '#b794f4', '#76e4f7', '#fbd38d'];
    const monthLabels: string[] = [];
    const now = new Date();
    for (let i = 5; i >= 0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      monthLabels.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }

    const datasets = this.products.map((p, idx) => {
      const points = this.trend[p.product_id] ?? [];
      const dataMap: Record<string, number | null> = {};
      for (const pt of points) {
        dataMap[`${pt.yr}-${String(pt.mo).padStart(2, '0')}`] = pt.ibc_per_h;
      }
      return {
        label: p.name,
        data: monthLabels.map(m => dataMap[m] ?? null),
        borderColor: COLORS[idx % COLORS.length],
        backgroundColor: 'transparent',
        tension: 0.3,
        pointRadius: 4,
        spanGaps: false,
      };
    });

    this.trendChart = new Chart(this.trendCanvas.nativeElement, {
      type: 'line',
      data: { labels: monthLabels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'IBC/h', color: '#a0aec0' } }
        }
      }
    });
  }

  ibchColor(vsPct: number): string {
    if (vsPct >= 15)  return '#68d391';
    if (vsPct >= 5)   return '#9ae6b4';
    if (vsPct >= -5)  return '#a0aec0';
    if (vsPct >= -15) return '#fbd38d';
    return '#fc8181';
  }

  kassgradColor(grad: number): string {
    if (grad <= 1.5) return '#68d391';
    if (grad <= 3.5) return '#f6ad55';
    return '#fc8181';
  }

  vsLabel(pct: number): string {
    if (pct > 0.5)  return `+${pct.toFixed(1)}% över snitt`;
    if (pct < -0.5) return `${pct.toFixed(1)}% under snitt`;
    return 'I linje med snitt';
  }

  vsClass(pct: number): string {
    if (pct >= 5)   return 'text-success';
    if (pct <= -5)  return 'text-danger';
    return 'text-muted';
  }

  efficiencyPct(p: Produkt): number | null {
    if (!p.expected_ibc_h || p.expected_ibc_h <= 0) return null;
    return Math.round(p.ibc_per_h / p.expected_ibc_h * 100);
  }

  stopptidPct(p: Produkt): number {
    if (p.total_drifttid_h <= 0) return 0;
    return Math.round(p.total_stopptid_h / p.total_drifttid_h * 100);
  }

  maxIbcH(): number {
    return this.products.length ? Math.max(...this.products.map(p => Math.max(p.ibc_per_h, p.expected_ibc_h ?? 0))) : 1;
  }

  barPct(val: number): number {
    const max = this.maxIbcH() * 1.05;
    return Math.min(100, (val / max) * 100);
  }
}
