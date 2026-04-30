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

interface DayData {
  datum: string;
  ibc_ej: number;
  bur_ej: number;
  kassation: number;
  totalt: number;
  kass_pct: number | null;
  cum_kass_pct: number | null;
}

interface ProductRow {
  product_id: number;
  product_name: string;
  kassation: number;
  totalt: number;
  ibc_ej: number;
  bur_ej: number;
  kass_pct: number | null;
}

interface OperatorRow {
  op_num: number;
  name: string;
  kassation: number;
  totalt: number;
  antal_skift: number;
  kass_pct: number | null;
}

interface MonthOption {
  year: number;
  month: number;
  label: string;
}

interface Kpi {
  totalt: number;
  kassation: number;
  kass_pct: number | null;
  ibc_kass_pct: number | null;
  bur_kass_pct: number | null;
  projected: number | null;
}

interface ApiResponse {
  success: boolean;
  year: number;
  month: number;
  from: string;
  to: string;
  days_in_month: number;
  days_elapsed: number;
  kpi: Kpi;
  daily: DayData[];
  by_product: ProductRow[];
  by_operator: OperatorRow[];
  available_months: MonthOption[];
}

@Component({
  standalone: true,
  selector: 'app-kassationsbudget',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './kassationsbudget.html',
  styleUrl: './kassationsbudget.css',
})
export class KassationsbudgetPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;
  @ViewChild('lineCanvas', { static: false }) lineCanvas!: ElementRef<HTMLCanvasElement>;

  loading = false;
  error = '';

  year  = new Date().getFullYear();
  month = new Date().getMonth() + 1;

  budgetInput = '';
  budget: number | null = null;

  data: ApiResponse | null = null;
  availableMonths: MonthOption[] = [];

  readonly BUDGET_KEY_PREFIX = 'kassbudget_';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadBudget();
    this.fetchData();
  }

  ngOnDestroy(): void {
    this.chart?.destroy();
    this.destroy$.next();
    this.destroy$.complete();
  }

  private budgetKey(): string {
    return `${this.BUDGET_KEY_PREFIX}${this.year}_${this.month}`;
  }

  loadBudget(): void {
    const saved = localStorage.getItem(this.budgetKey());
    if (saved !== null) {
      const n = parseFloat(saved);
      if (!isNaN(n) && n >= 0) {
        this.budget = n;
        this.budgetInput = String(n);
        return;
      }
    }
    this.budget = null;
    this.budgetInput = '';
  }

  saveBudget(): void {
    const n = parseFloat(this.budgetInput);
    if (!isNaN(n) && n >= 0) {
      this.budget = n;
      localStorage.setItem(this.budgetKey(), String(n));
      this.buildChart();
    }
  }

  clearBudget(): void {
    this.budget = null;
    this.budgetInput = '';
    localStorage.removeItem(this.budgetKey());
    this.buildChart();
  }

  onMonthChange(year: number, month: number): void {
    this.year  = year;
    this.month = month;
    this.loadBudget();
    this.fetchData();
  }

  fetchData(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=kassationsbudget&year=${this.year}&month=${this.month}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.data = res;
        if (res.available_months.length) this.availableMonths = res.available_months;
        setTimeout(() => this.buildChart(), 0);
      });
  }

  buildChart(): void {
    if (!this.lineCanvas || !this.data?.daily.length) return;
    this.chart?.destroy();

    const labels  = this.data.daily.map(d => d.datum.slice(5)); // MM-DD
    const cumData = this.data.daily.map(d => d.cum_kass_pct);

    const datasets: any[] = [
      {
        label: 'Kumulativ kassation%',
        data: cumData,
        borderColor: '#fc8181',
        backgroundColor: 'rgba(252,129,129,0.1)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        borderWidth: 2,
      },
    ];

    if (this.budget !== null) {
      datasets.push({
        label: `Budget ${this.budget}%`,
        data: labels.map(() => this.budget),
        borderColor: '#68d391',
        borderDash: [6, 4],
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
      });
    }

    this.chart = new Chart(this.lineCanvas.nativeElement, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(2) + '%' : '–'}`,
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0', maxTicksLimit: 15 }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#a0aec0', callback: v => v + '%' },
            grid: { color: '#2d3748' },
            min: 0,
          },
        },
      },
    });
  }

  get statusClass(): string {
    if (!this.data || this.data.kpi.kass_pct === null) return 'status-neutral';
    if (this.budget === null) return 'status-neutral';
    return this.data.kpi.kass_pct <= this.budget ? 'status-ok' : 'status-over';
  }

  get statusText(): string {
    if (!this.data || this.data.kpi.kass_pct === null) return 'Inga data';
    if (this.budget === null) return 'Sätt ett budgetmål ovan';
    const pct = this.data.kpi.kass_pct;
    const diff = +(pct - this.budget).toFixed(2);
    if (diff <= 0) return `Kassation under budget — ${Math.abs(diff).toFixed(2)} pp marginal`;
    return `Kassation ${diff.toFixed(2)} pp ÖVER budget`;
  }

  get daysRemaining(): number {
    if (!this.data) return 0;
    return this.data.days_in_month - this.data.days_elapsed;
  }

  get progressPct(): number {
    if (!this.data || !this.data.days_in_month) return 0;
    return Math.round(this.data.days_elapsed / this.data.days_in_month * 100);
  }

  kassCellClass(pct: number | null, budget?: number | null): string {
    if (pct === null) return '';
    const b = budget ?? this.budget;
    if (b === null) return '';
    if (pct <= b * 0.8) return 'cell-great';
    if (pct <= b) return 'cell-ok';
    if (pct <= b * 1.3) return 'cell-warn';
    return 'cell-bad';
  }

  fmtPct(v: number | null): string {
    return v !== null ? v.toFixed(2) + '%' : '–';
  }

  monthLabel(y: number, m: number): string {
    return new Date(y, m - 1, 1).toLocaleDateString('sv-SE', { month: 'long', year: 'numeric' });
  }
}
