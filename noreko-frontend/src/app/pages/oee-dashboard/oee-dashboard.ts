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

interface ShiftRow {
  skiftraknare: number;
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  drifttid: number;
  driftstopptime: number;
  product_name: string;
  cycle_time: number | null;
  avail: number | null;
  perf: number | null;
  qual: number | null;
  oee: number | null;
}

interface MonthRow {
  month: string;
  avail: number | null;
  perf: number | null;
  qual: number | null;
  oee: number | null;
  ibc: number;
  hours: number;
}

interface ProductRow {
  name: string;
  avg_oee: number;
  shifts: number;
  total_ibc: number;
}

interface KPI {
  oee: number | null;
  avail: number | null;
  qual: number | null;
  ibc_h: number | null;
  kass: number | null;
  shifts: number;
}

@Component({
  selector: 'app-oee-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './oee-dashboard.html',
  styleUrls: ['./oee-dashboard.css'],
})
export class OEEDashboardPage implements OnInit, OnDestroy {
  @ViewChild('trendChart') trendChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('gaugeCanvas') gaugeCanvasRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private trendChart: Chart | null = null;

  days = 90;
  dayOptions = [30, 90, 180, 365];
  isFetching = false;
  error = '';

  kpi: KPI = { oee: null, avail: null, qual: null, ibc_h: null, kass: null, shifts: 0 };
  monthly: MonthRow[] = [];
  products: ProductRow[] = [];
  shifts: ShiftRow[] = [];
  filteredShifts: ShiftRow[] = [];

  sortField: keyof ShiftRow = 'datum';
  sortDir: 1 | -1 = -1;
  filterProduct = '';
  productNames: string[] = [];

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.trendChart?.destroy();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.error = '';

    this.http
      .get<any>(`${environment.apiUrl}?action=rebotling&run=oee-dashboard&days=${this.days}`, {
        withCredentials: true,
      })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe((res) => {
        this.isFetching = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta OEE-data.';
          return;
        }
        this.kpi = res.kpi;
        this.monthly = res.monthly;
        this.products = res.products;
        this.shifts = res.shifts;
        this.productNames = [...new Set(this.shifts.map((s: ShiftRow) => s.product_name))].sort();
        this.applyFilter();
        setTimeout(() => this.drawTrendChart(), 50);
      });
  }

  applyFilter(): void {
    this.filteredShifts = this.filterProduct
      ? this.shifts.filter((s) => s.product_name === this.filterProduct)
      : [...this.shifts];
    this.sortShifts();
  }

  sortBy(field: keyof ShiftRow): void {
    if (this.sortField === field) {
      this.sortDir = this.sortDir === 1 ? -1 : 1;
    } else {
      this.sortField = field;
      this.sortDir = -1;
    }
    this.sortShifts();
  }

  private sortShifts(): void {
    const field = this.sortField;
    const dir = this.sortDir;
    this.filteredShifts.sort((a, b) => {
      const av = a[field] ?? -999;
      const bv = b[field] ?? -999;
      if (av < bv) return -dir;
      if (av > bv) return dir;
      return 0;
    });
  }

  oeeColor(val: number | null): string {
    if (val === null) return '#718096';
    if (val >= 85) return '#68d391';
    if (val >= 65) return '#f6ad55';
    if (val >= 45) return '#fc8181';
    return '#fc8181';
  }

  oeeLabel(val: number | null): string {
    if (val === null) return '–';
    if (val >= 85) return 'Världsklass';
    if (val >= 65) return 'Bra';
    if (val >= 45) return 'Genomsnitt';
    return 'Förbättra';
  }

  pctColor(val: number | null): string {
    if (val === null) return '#718096';
    if (val >= 90) return '#68d391';
    if (val >= 75) return '#f6ad55';
    return '#fc8181';
  }

  private drawTrendChart(): void {
    const canvas = this.trendChartRef?.nativeElement;
    if (!canvas || this.monthly.length === 0) return;
    this.trendChart?.destroy();

    const labels = this.monthly.map((m) => m.month);
    const oeeData = this.monthly.map((m) => m.oee);
    const availData = this.monthly.map((m) => m.avail);
    const qualData = this.monthly.map((m) => m.qual);
    const perfData = this.monthly.map((m) => m.perf);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: oeeData,
            borderColor: '#a78bfa',
            backgroundColor: 'rgba(167,139,250,0.15)',
            borderWidth: 3,
            pointRadius: 5,
            tension: 0.3,
            fill: true,
          },
          {
            label: 'Tillgänglighet %',
            data: availData,
            borderColor: '#63b3ed',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.3,
            fill: false,
          },
          {
            label: 'Prestanda %',
            data: perfData,
            borderColor: '#f6ad55',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.3,
            fill: false,
          },
          {
            label: 'Kvalitet %',
            data: qualData,
            borderColor: '#68d391',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.3,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { color: '#e2e8f0', boxWidth: 14 } },
          tooltip: {
            callbacks: {
              label: (ctx) =>
                ctx.dataset.label + ': ' + (ctx.parsed.y !== null ? ctx.parsed.y.toFixed(1) + ' %' : 'n/a'),
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }
}
