import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface Overview {
  days: number;
  from_date: string;
  to_date: string;
  total_idag_min: number;
  antal_stopp_idag: number;
  snitt_per_stopp_min: number;
  flaskhals_maskin: string | null;
  flaskhals_maskin_min: number;
  period_total_min: number;
  prev_total_min: number;
  trend_diff_min: number;
  trend_direction: 'up' | 'down' | 'flat';
}

interface Maskin {
  maskin_id: number;
  maskin_namn: string;
  total_min: number;
  antal_stopp: number;
  snitt_min: number;
  max_stopp_min: number;
  senaste_stopp: string;
  andel_pct: number;
}

interface StoppEvent {
  id: number;
  maskin_id: number;
  maskin_namn: string;
  startad_at: string;
  avslutad_at: string | null;
  duration_min: number;
  orsak: string;
  orsak_kategori: string;
  operator_namn: string;
  kommentar: string | null;
}

interface Fordelning {
  maskin_id: number;
  maskin_namn: string;
  total_min: number;
  antal_stopp: number;
  andel_pct: number;
}

@Component({
  standalone: true,
  selector: 'app-maskinpark-analys',
  imports: [CommonModule, FormsModule],
  templateUrl: './maskinpark-analys.html',
  styleUrl: './maskinpark-analys.css',
})
export class MaskinparkAnalysPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  private barChart: Chart | null = null;
  private donutChart: Chart | null = null;

  @ViewChild('barCanvas') barCanvasRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('donutCanvas') donutCanvasRef!: ElementRef<HTMLCanvasElement>;

  days = 30;
  readonly daysOptions = [
    { value: 30,  label: '30 dagar' },
    { value: 60,  label: '60 dagar' },
    { value: 90,  label: '90 dagar' },
    { value: 180, label: '180 dagar' },
  ];

  loadingOverview   = false;
  loadingMaskiner   = false;
  loadingStopp      = false;
  errorOverview     = false;
  errorMaskiner     = false;
  errorStopp        = false;

  overview: Overview | null = null;
  maskiner: Maskin[]        = [];
  stopp: StoppEvent[]       = [];
  fordelning: Fordelning[]  = [];

  filterMaskinId = 0;
  sortCol: keyof Maskin = 'total_min';
  sortDesc = true;

  readonly KATEGORI_LABELS: Record<string, string> = {
    maskin:     'Maskinfel',
    material:   'Materialfel',
    'operatör': 'Operatörsorsak',
    planerat:   'Planerat',
    övrigt:     'Övrigt',
  };

  readonly KATEGORI_COLORS: Record<string, string> = {
    maskin:     '#fc8181',
    material:   '#f6ad55',
    'operatör': '#63b3ed',
    planerat:   '#68d391',
    övrigt:     '#a0aec0',
  };

  Math = Math;

  get sortedMaskiner(): Maskin[] {
    return [...this.maskiner].sort((a, b) => {
      const av = a[this.sortCol] as number;
      const bv = b[this.sortCol] as number;
      return this.sortDesc ? bv - av : av - bv;
    });
  }

  get filteredStopp(): StoppEvent[] {
    if (!this.filterMaskinId) return this.stopp;
    return this.stopp.filter(s => s.maskin_id === this.filterMaskinId);
  }

  get kategoriStats(): Array<{ kat: string; label: string; color: string; total_min: number; antal: number; pct: number }> {
    const totals: Record<string, { total_min: number; antal: number }> = {};
    for (const s of this.stopp) {
      const k = s.orsak_kategori;
      if (!totals[k]) totals[k] = { total_min: 0, antal: 0 };
      totals[k].total_min += s.duration_min;
      totals[k].antal++;
    }
    const total = Object.values(totals).reduce((a, b) => a + b.total_min, 0);
    return Object.entries(totals)
      .map(([k, v]) => ({
        kat: k,
        label: this.KATEGORI_LABELS[k] ?? k,
        color: this.KATEGORI_COLORS[k] ?? '#a0aec0',
        total_min: v.total_min,
        antal: v.antal,
        pct: total > 0 ? Math.round((v.total_min / total) * 100) : 0,
      }))
      .sort((a, b) => b.total_min - a.total_min);
  }

  get totalPeriodH(): string {
    return (this.overview ? (this.overview.period_total_min / 60) : 0).toFixed(1);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.barChart?.destroy();
    this.donutChart?.destroy();
  }

  setDays(d: number): void {
    this.days = d;
    this.loadAll();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadOverview();
    this.loadMaskiner();
    this.loadStopp();
  }

  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    const url = `${environment.apiUrl}?action=stopptidsanalys&run=overview&days=${this.days}`;
    this.http.get<{ success: boolean; data: Overview }>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success && res.data) {
          this.overview = res.data;
        } else {
          this.errorOverview = true;
        }
        this.checkDone();
      });
  }

  private loadMaskiner(): void {
    this.loadingMaskiner = true;
    this.errorMaskiner = false;
    const url = `${environment.apiUrl}?action=stopptidsanalys&run=per-maskin&days=${this.days}`;
    this.http.get<{ success: boolean; data: { maskiner: Maskin[] } }>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingMaskiner = false;
        if (res?.success && res.data) {
          this.maskiner = res.data.maskiner ?? [];
        } else {
          this.errorMaskiner = true;
        }
        this.checkDone();
      });
  }

  private loadStopp(): void {
    this.loadingStopp = true;
    this.errorStopp = false;
    const maskinParam = this.filterMaskinId ? `&maskin_id=${this.filterMaskinId}` : '';
    const url = `${environment.apiUrl}?action=stopptidsanalys&run=detaljtabell&days=${this.days}${maskinParam}`;
    this.http.get<{ success: boolean; data: { stopp: StoppEvent[] } }>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStopp = false;
        if (res?.success && res.data) {
          this.stopp = res.data.stopp ?? [];
        } else {
          this.errorStopp = true;
        }
        this.checkDone();
      });
  }

  private checkDone(): void {
    if (!this.loadingOverview && !this.loadingMaskiner && !this.loadingStopp) {
      this.isFetching = false;
      setTimeout(() => this.renderCharts(), 50);
    }
  }

  private renderCharts(): void {
    this.renderBarChart();
    this.renderDonutChart();
  }

  private renderBarChart(): void {
    const canvas = this.barCanvasRef?.nativeElement;
    if (!canvas || !this.maskiner.length) return;
    this.barChart?.destroy();
    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.maskiner.map(m => m.maskin_namn),
        datasets: [{
          label: 'Total stopptid (min)',
          data: this.maskiner.map(m => m.total_min),
          backgroundColor: this.maskiner.map((_, i) => `hsl(${(i * 45 + 0) % 360}, 70%, 55%)`),
          borderRadius: 4,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#2d3748' },
          },
          y: {
            ticks: { color: '#e2e8f0' },
            grid: { display: false },
          },
        },
      },
    });
  }

  private renderDonutChart(): void {
    const canvas = this.donutCanvasRef?.nativeElement;
    const stats = this.kategoriStats;
    if (!canvas || !stats.length) return;
    this.donutChart?.destroy();
    this.donutChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: stats.map(s => s.label),
        datasets: [{
          data: stats.map(s => s.total_min),
          backgroundColor: stats.map(s => s.color),
          borderWidth: 2,
          borderColor: '#1a202c',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#e2e8f0', padding: 12 },
          },
        },
      },
    });
  }

  setSort(col: keyof Maskin): void {
    if (this.sortCol === col) {
      this.sortDesc = !this.sortDesc;
    } else {
      this.sortCol = col;
      this.sortDesc = true;
    }
  }

  setMaskinFilter(id: number): void {
    this.filterMaskinId = id;
  }

  trendClass(): string {
    if (!this.overview) return '';
    if (this.overview.trend_direction === 'up') return 'trend-up';
    if (this.overview.trend_direction === 'down') return 'trend-down';
    return 'trend-flat';
  }

  trendIcon(): string {
    if (!this.overview) return '→';
    if (this.overview.trend_direction === 'up') return '↑';
    if (this.overview.trend_direction === 'down') return '↓';
    return '→';
  }

  kategoriLabel(k: string): string {
    return this.KATEGORI_LABELS[k] ?? k;
  }

  kategoriColor(k: string): string {
    return this.KATEGORI_COLORS[k] ?? '#a0aec0';
  }

  durationLabel(min: number): string {
    if (min < 60) return `${min.toFixed(1)} min`;
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }
}
