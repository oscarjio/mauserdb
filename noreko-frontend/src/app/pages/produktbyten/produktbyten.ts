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

interface ProductRow {
  product_id: number;
  product_name: string;
  changeover_ibc_h: number | null;
  continuation_ibc_h: number | null;
  delta: number | null;
  delta_pct: number | null;
  changeover_count: number;
  continuation_count: number;
}

interface Overall {
  total_shifts: number;
  total_changeovers: number;
  changeover_pct: number;
  changeover_ibc_h: number | null;
  continuation_ibc_h: number | null;
  delta: number | null;
  delta_pct: number | null;
}

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  products: ProductRow[];
  overall: Overall;
}

@Component({
  standalone: true,
  selector: 'app-produktbyten',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produktbyten.html',
  styleUrl: './produktbyten.css'
})
export class ProduktbytenPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private chart: Chart | null = null;
  Math = Math;

  days = 90;
  loading = false;
  error = '';

  products: ProductRow[] = [];
  overall: Overall | null = null;
  from = '';
  to = '';

  sortBy: 'count' | 'delta' | 'name' = 'count';

  get sortedProducts(): ProductRow[] {
    const p = [...this.products];
    if (this.sortBy === 'delta') return p.sort((a, b) => (a.delta ?? 0) - (b.delta ?? 0));
    if (this.sortBy === 'name') return p.sort((a, b) => a.product_name.localeCompare(b.product_name, 'sv'));
    return p.sort((a, b) => b.changeover_count - a.changeover_count);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.chart?.destroy(); } catch (_) {}
    this.chart = null;
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=produktbyten&days=${this.days}`,
      { withCredentials: true }
    ).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda data.'; return; }
        this.products = res.products;
        this.overall  = res.overall;
        this.from     = res.from;
        this.to       = res.to;
        setTimeout(() => { if (!this.destroy$.closed) this.buildChart(); }, 80);
      });
  }

  private buildChart(): void {
    try { this.chart?.destroy(); } catch (_) {}
    this.chart = null;

    const canvas = document.getElementById('produktbytenChart') as HTMLCanvasElement;
    if (!canvas || this.products.length === 0) return;

    const rows = this.sortedProducts.filter(
      p => p.changeover_ibc_h !== null && p.continuation_ibc_h !== null
    );
    if (rows.length === 0) return;

    const labels = rows.map(p => p.product_name);
    const changeoverData = rows.map(p => p.changeover_ibc_h ?? 0);
    const continuationData = rows.map(p => p.continuation_ibc_h ?? 0);

    this.chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Produktbyte (1:a skift)',
            data: changeoverData,
            backgroundColor: 'rgba(246,173,85,0.8)',
            borderColor: '#f6ad55',
            borderWidth: 1,
            borderRadius: 4,
          },
          {
            label: 'Fortsättning',
            data: continuationData,
            backgroundColor: 'rgba(104,211,145,0.8)',
            borderColor: '#68d391',
            borderWidth: 1,
            borderRadius: 4,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 }, usePointStyle: true }
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => ` ${ctx.dataset.label}: ${(ctx.parsed.y as number).toFixed(1)} IBC/h`
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.06)' } },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: { display: true, text: 'IBC / timme', color: '#8fa3b8', font: { size: 11 } }
          }
        }
      }
    });
  }

  deltaClass(delta: number | null): string {
    if (delta === null) return 'neutral';
    if (delta >= 0) return 'positive';
    return 'negative';
  }

  deltaPctLabel(pct: number | null): string {
    if (pct === null) return '–';
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
  }

  costLabel(delta: number | null): string {
    if (delta === null) return '–';
    const abs = Math.abs(delta).toFixed(1);
    return delta < 0 ? `-${abs} IBC/h` : `+${abs} IBC/h`;
  }
}
