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

interface MonthRow {
  month: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  ibc_kass_pct: number;
  bur_kass_pct: number;
}

interface ProductRow {
  product_id: number;
  product_name: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  skift_count: number;
  ibc_kass_pct: number;
  bur_kass_pct: number;
}

interface OperatorRow {
  number: number;
  name: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  skift_count: number;
  ibc_kass_pct: number;
  bur_kass_pct: number;
}

interface Kpi {
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  ibc_kass_pct: number;
  bur_kass_pct: number;
  combined_kass_pct: number;
  ibc_vs_bur_ratio: number | null;
}

interface ApiResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  kpi: Kpi;
  monthly: MonthRow[];
  by_product: ProductRow[];
  by_operator: OperatorRow[];
}

@Component({
  standalone: true,
  selector: 'app-kassationstyper',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './kassationstyper.html',
  styleUrl: './kassationstyper.css',
})
export class KassationstyperPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chartTrend: Chart | null = null;
  private chartProduct: Chart | null = null;
  @ViewChild('chartTrendRef') chartTrendRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('chartProductRef') chartProductRef!: ElementRef<HTMLCanvasElement>;

  Math = Math;
  days = 180;
  loading = false;
  error = '';

  kpi: Kpi | null = null;
  monthly: MonthRow[] = [];
  byProduct: ProductRow[] = [];
  byOperator: OperatorRow[] = [];
  from = '';
  to = '';

  activeTab: 'trend' | 'produkt' | 'operator' = 'trend';
  sortOp: 'combined' | 'ibc' | 'bur' | 'name' = 'combined';

  readonly daysOptions = [
    { value: 90, label: '90 dagar' },
    { value: 180, label: '6 månader' },
    { value: 365, label: '1 år' },
    { value: 730, label: '2 år' },
  ];

  get sortedOperators(): OperatorRow[] {
    const rows = [...this.byOperator];
    if (this.sortOp === 'ibc')  return rows.sort((a, b) => b.ibc_kass_pct - a.ibc_kass_pct);
    if (this.sortOp === 'bur')  return rows.sort((a, b) => b.bur_kass_pct - a.bur_kass_pct);
    if (this.sortOp === 'name') return rows.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    return rows.sort((a, b) => (b.ibc_kass_pct + b.bur_kass_pct) - (a.ibc_kass_pct + a.bur_kass_pct));
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chartTrend?.destroy();
    this.chartProduct?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=kassationstyper&days=${this.days}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda kassationsdata.'; return; }
        this.kpi        = res.kpi;
        this.monthly    = res.monthly;
        this.byProduct  = res.by_product;
        this.byOperator = res.by_operator;
        this.from       = res.from;
        this.to         = res.to;
        setTimeout(() => { this.buildTrendChart(); this.buildProductChart(); }, 50);
      });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setTab(t: 'trend' | 'produkt' | 'operator'): void {
    this.activeTab = t;
    if (t === 'trend') setTimeout(() => this.buildTrendChart(), 50);
    if (t === 'produkt') setTimeout(() => this.buildProductChart(), 50);
  }

  private buildTrendChart(): void {
    const el = this.chartTrendRef?.nativeElement;
    if (!el || !this.monthly.length) return;
    this.chartTrend?.destroy();

    const labels = this.monthly.map(m => m.month);
    const ibcPct = this.monthly.map(m => m.ibc_kass_pct);
    const burPct = this.monthly.map(m => m.bur_kass_pct);

    this.chartTrend = new Chart(el, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC kassation %',
            data: ibcPct,
            borderColor: '#fc8181',
            backgroundColor: 'rgba(252,129,129,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
          },
          {
            label: 'Bur kassation %',
            data: burPct,
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246,173,85,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            borderDash: [5, 3],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${(ctx.raw as number).toFixed(2)}%`,
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#a0aec0', callback: (v) => `${v}%` },
            grid: { color: '#2d3748' },
            min: 0,
          },
        },
      },
    });
  }

  private buildProductChart(): void {
    const el = this.chartProductRef?.nativeElement;
    if (!el || !this.byProduct.length) return;
    this.chartProduct?.destroy();

    const top8 = [...this.byProduct].slice(0, 8);
    const labels = top8.map(p => p.product_name);

    this.chartProduct = new Chart(el, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC kassation %',
            data: top8.map(p => p.ibc_kass_pct),
            backgroundColor: 'rgba(252,129,129,0.75)',
            borderColor: '#fc8181',
            borderWidth: 1,
          },
          {
            label: 'Bur kassation %',
            data: top8.map(p => p.bur_kass_pct),
            backgroundColor: 'rgba(246,173,85,0.75)',
            borderColor: '#f6ad55',
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${(ctx.raw as number).toFixed(2)}%`,
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#a0aec0', callback: (v) => `${v}%` },
            grid: { color: '#2d3748' },
            min: 0,
          },
        },
      },
    });
  }

  ibcColor(pct: number): string {
    if (pct >= 6)  return '#fc8181';
    if (pct >= 3)  return '#f6ad55';
    return '#68d391';
  }

  burColor(pct: number): string {
    if (pct >= 4)  return '#fc8181';
    if (pct >= 2)  return '#f6ad55';
    return '#68d391';
  }

  dominantType(row: OperatorRow | ProductRow): string {
    if (row.ibc_ej_ok === 0 && row.bur_ej_ok === 0) return 'Ingen';
    if (row.bur_ej_ok === 0) return 'IBC';
    if (row.ibc_ej_ok === 0) return 'Bur';
    return row.ibc_ej_ok >= row.bur_ej_ok ? 'IBC' : 'Bur';
  }
}
