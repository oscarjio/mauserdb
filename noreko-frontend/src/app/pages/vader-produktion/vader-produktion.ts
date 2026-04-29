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

interface DayData {
  dag: string;
  ibc_per_h: number;
  antal_skift: number;
  total_timmar: number;
  avg_temp: number;
}

interface TempBin {
  label: string;
  floor: number;
  ibc_per_h: number;
  antal_dagar: number;
}

interface Kpi {
  antal_dagar: number;
  min_temp: number;
  max_temp: number;
  avg_temp: number;
  avg_ibc_per_h: number;
}

interface ApiResponse {
  success: boolean;
  data: DayData[];
  bins: TempBin[];
  kpi: Kpi | null;
  korrelation: number | null;
  days: number;
}

@Component({
  standalone: true,
  selector: 'app-vader-produktion',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './vader-produktion.html',
  styleUrl: './vader-produktion.css',
})
export class VaderProduktionPage implements OnInit, OnDestroy {
  @ViewChild('scatterCanvas') scatterCanvas!: ElementRef<HTMLCanvasElement>;
  @ViewChild('binCanvas') binCanvas!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private isFetching = false;
  private scatterChart: Chart | null = null;
  private binChart: Chart | null = null;

  loading = false;
  error = '';
  days = 365;

  data: DayData[] = [];
  bins: TempBin[] = [];
  kpi: Kpi | null = null;
  korrelation: number | null = null;

  korrelationText = '';
  korrelationColor = '#a0aec0';

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.scatterChart?.destroy();
    this.binChart?.destroy();
    this.destroy$.next();
    this.destroy$.complete();
  }

  constructor(private http: HttpClient) {}

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=vader-produktion&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda data.'; return; }
        this.data = res.data ?? [];
        this.bins = res.bins ?? [];
        this.kpi = res.kpi ?? null;
        this.korrelation = res.korrelation ?? null;
        this.korrelationText = this.buildKorrText(res.korrelation);
        this.korrelationColor = this.korrColor(res.korrelation);
        setTimeout(() => { this.buildScatter(); this.buildBin(); }, 0);
      });
  }

  private buildKorrText(r: number | null): string {
    if (r === null) return 'Otillräcklig data';
    const abs = Math.abs(r);
    const dir = r > 0 ? 'positiv' : 'negativ';
    if (abs >= 0.5) return `Stark ${dir} korrelation`;
    if (abs >= 0.3) return `Måttlig ${dir} korrelation`;
    if (abs >= 0.1) return `Svag ${dir} korrelation`;
    return 'Ingen tydlig korrelation';
  }

  private korrColor(r: number | null): string {
    if (r === null) return '#a0aec0';
    const abs = Math.abs(r);
    if (abs >= 0.5) return r < 0 ? '#fc8181' : '#68d391';
    if (abs >= 0.3) return r < 0 ? '#fbd38d' : '#9ae6b4';
    return '#a0aec0';
  }

  private buildScatter(): void {
    this.scatterChart?.destroy();
    const canvas = this.scatterCanvas?.nativeElement;
    if (!canvas || this.data.length === 0) return;

    const points = this.data.map(d => ({ x: d.avg_temp, y: d.ibc_per_h }));

    // Regression line
    const n = points.length;
    const xMean = points.reduce((s, p) => s + p.x, 0) / n;
    const yMean = points.reduce((s, p) => s + p.y, 0) / n;
    let num = 0, den = 0;
    points.forEach(p => { num += (p.x - xMean) * (p.y - yMean); den += (p.x - xMean) ** 2; });
    const slope = den !== 0 ? num / den : 0;
    const intercept = yMean - slope * xMean;
    const xMin = Math.min(...points.map(p => p.x));
    const xMax = Math.max(...points.map(p => p.x));
    const regrLine = [
      { x: xMin, y: Math.round((slope * xMin + intercept) * 10) / 10 },
      { x: xMax, y: Math.round((slope * xMax + intercept) * 10) / 10 },
    ];

    // Color each point by temperature
    const colors = points.map(p => {
      const t = p.x;
      if (t <= 0) return 'rgba(99,179,237,0.6)';
      if (t <= 10) return 'rgba(154,230,180,0.6)';
      if (t <= 20) return 'rgba(246,173,85,0.6)';
      return 'rgba(252,129,129,0.6)';
    });

    this.scatterChart = new Chart(canvas, {
      type: 'scatter',
      data: {
        datasets: [
          {
            label: 'Dag (IBC/h vs temp)',
            data: points,
            backgroundColor: colors,
            pointRadius: 5,
            pointHoverRadius: 7,
          },
          {
            label: 'Trendlinje',
            data: regrLine,
            type: 'line',
            borderColor: 'rgba(246,201,14,0.8)',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const d = ctx.raw as { x: number; y: number };
                return `Temp: ${d.x}°C  IBC/h: ${d.y}`;
              },
            },
          },
        },
        scales: {
          x: {
            title: { display: true, text: 'Utetemperatur (°C)', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'IBC/h', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  private buildBin(): void {
    this.binChart?.destroy();
    const canvas = this.binCanvas?.nativeElement;
    if (!canvas || this.bins.length === 0) return;

    const avgIbch = this.kpi?.avg_ibc_per_h ?? 0;
    const bgColors = this.bins.map(b =>
      b.ibc_per_h >= avgIbch * 1.05 ? 'rgba(104,211,145,0.8)'
      : b.ibc_per_h >= avgIbch * 0.95 ? 'rgba(160,174,192,0.7)'
      : 'rgba(252,129,129,0.8)'
    );

    this.binChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.bins.map(b => b.label),
        datasets: [
          {
            label: 'IBC/h',
            data: this.bins.map(b => b.ibc_per_h),
            backgroundColor: bgColors,
            borderRadius: 6,
          },
          {
            label: 'Periodssnitt',
            data: this.bins.map(() => avgIbch),
            type: 'line',
            borderColor: 'rgba(246,201,14,0.7)',
            borderWidth: 2,
            borderDash: [5, 3],
            pointRadius: 0,
            fill: false,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              afterLabel: (ctx) => {
                const bin = this.bins[ctx.dataIndex];
                return bin ? `${bin.antal_dagar} dagar` : '';
              },
            },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            title: { display: true, text: 'IBC/h', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  get sortedData(): DayData[] {
    return [...this.data].sort((a, b) => b.ibc_per_h - a.ibc_per_h).slice(0, 20);
  }
}
