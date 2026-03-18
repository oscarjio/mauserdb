import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  PrediktivtUnderhallService,
  HeatmapStation,
  MtbfStation,
  TrendStation,
  Rekommendation,
} from '../prediktivt-underhall.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-prediktivt-underhall',
  templateUrl: './prediktivt-underhall.component.html',
  styleUrls: ['./prediktivt-underhall.component.css'],
  imports: [CommonModule],
})
export class PrediktivtUnderhallPage implements OnInit, OnDestroy {
  // Loading
  loadingHeatmap = false;
  loadingMtbf = false;
  loadingTrender = false;
  loadingRekom = false;

  // Error
  errorHeatmap = false;
  errorMtbf = false;
  errorTrender = false;
  errorRekom = false;

  // Heatmap
  heatmapOrsaker: string[] = [];
  heatmapMatris: HeatmapStation[] = [];
  heatmapMaxAntal = 0;

  // MTBF
  mtbfStationer: MtbfStation[] = [];

  // Trender
  trendStationer: TrendStation[] = [];
  trendVeckonycklar: string[] = [];
  private trendChart: Chart | null = null;

  // Rekommendationer
  rekommendationer: Rekommendation[] = [];
  antalVarningar = 0;
  antalAtgarder = 0;
  antalOk = 0;

  // Aktiv flik
  aktivFlik: 'heatmap' | 'mtbf' | 'trender' | 'rekom' = 'mtbf';

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private svc: PrediktivtUnderhallService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadHeatmap();
    this.loadMtbf();
    this.loadTrender();
    this.loadRekommendationer();
  }

  bytFlik(flik: 'heatmap' | 'mtbf' | 'trender' | 'rekom'): void {
    this.aktivFlik = flik;
    if (flik === 'trender') {
      setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 150);
    }
  }

  // ---- Heatmap ----

  private loadHeatmap(): void {
    this.loadingHeatmap = true;
    this.errorHeatmap = false;
    this.svc.getHeatmap(4).pipe(
      timeout(15000),
      catchError(() => { this.errorHeatmap = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingHeatmap = false;
      this.isFetching = false;
      if (res?.success) {
        this.heatmapOrsaker = res.data.orsaker;
        this.heatmapMatris = res.data.matris;
        this.heatmapMaxAntal = res.data.max_antal;
      } else if (res !== null) {
        this.errorHeatmap = true;
      }
    });
  }

  getHeatmapColor(antal: number): string {
    if (this.heatmapMaxAntal === 0 || antal === 0) return 'rgba(72, 187, 120, 0.15)';
    const ratio = antal / this.heatmapMaxAntal;
    if (ratio >= 0.7) return 'rgba(245, 101, 101, 0.7)';
    if (ratio >= 0.4) return 'rgba(236, 201, 75, 0.5)';
    if (ratio >= 0.15) return 'rgba(236, 201, 75, 0.25)';
    return 'rgba(72, 187, 120, 0.2)';
  }

  getHeatmapTextColor(antal: number): string {
    if (this.heatmapMaxAntal === 0 || antal === 0) return '#a0aec0';
    const ratio = antal / this.heatmapMaxAntal;
    if (ratio >= 0.7) return '#fff';
    if (ratio >= 0.4) return '#1a202c';
    return '#e2e8f0';
  }

  // ---- MTBF ----

  private loadMtbf(): void {
    this.loadingMtbf = true;
    this.errorMtbf = false;
    this.svc.getMtbf().pipe(
      timeout(15000),
      catchError(() => { this.errorMtbf = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingMtbf = false;
      if (res?.success) {
        this.mtbfStationer = res.data.stationer;
      } else if (res !== null) {
        this.errorMtbf = true;
      }
    });
  }

  getRiskBadgeClass(risk: string): string {
    switch (risk) {
      case 'kritisk': return 'badge-kritisk';
      case 'hog': return 'badge-hog';
      case 'medel': return 'badge-medel';
      default: return 'badge-lag';
    }
  }

  getRiskLabel(risk: string): string {
    switch (risk) {
      case 'kritisk': return 'Kritisk';
      case 'hog': return 'Hog';
      case 'medel': return 'Medel';
      default: return 'Lag';
    }
  }

  getRiskCardBorder(risk: string): string {
    switch (risk) {
      case 'kritisk': return '2px solid #fc8181';
      case 'hog': return '2px solid #f6ad55';
      case 'medel': return '2px solid #ecc94b';
      default: return '2px solid #48bb78';
    }
  }

  getMtbfTrendIcon(trend: string): string {
    switch (trend) {
      case 'sjunkande': return 'fas fa-arrow-down text-danger';
      case 'okande': return 'fas fa-arrow-up text-success';
      default: return 'fas fa-minus text-muted';
    }
  }

  getMtbfTrendLabel(trend: string): string {
    switch (trend) {
      case 'sjunkande': return 'MTBF sjunker (fler stopp)';
      case 'okande': return 'MTBF okar (farre stopp)';
      default: return 'Stabil';
    }
  }

  getRiskProgressWidth(station: MtbfStation): number {
    return Math.min(100, station.risk_kvot * 100);
  }

  getRiskProgressColor(station: MtbfStation): string {
    if (station.risk_kvot >= 1.2) return '#fc8181';
    if (station.risk_kvot >= 0.8) return '#f6ad55';
    if (station.risk_kvot >= 0.5) return '#ecc94b';
    return '#48bb78';
  }

  // ---- Trender ----

  private loadTrender(): void {
    this.loadingTrender = true;
    this.errorTrender = false;
    this.svc.getTrender(12).pipe(
      timeout(15000),
      catchError(() => { this.errorTrender = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTrender = false;
      if (res?.success) {
        this.trendStationer = res.data.trender;
        this.trendVeckonycklar = res.data.veckonycklar;
        if (this.aktivFlik === 'trender') {
          setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 150);
        }
      } else if (res !== null) {
        this.errorTrender = true;
      }
    });
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    const canvas = document.getElementById('prediktivtTrendChart') as HTMLCanvasElement;
    if (!canvas || this.trendStationer.length === 0) return;

    const colors = [
      '#4fd1c5', '#4299e1', '#fc8181', '#f6ad55',
      '#68d391', '#9f7aea', '#ecc94b', '#ed64a6',
    ];

    // Bygg labels fran forsta stationens veckodata
    const labels = this.trendStationer[0]?.veckodata.map(v => v.label) || [];

    const datasets = this.trendStationer.slice(0, 8).map((station, i) => ({
      label: station.station_namn.replace(/Station \d+ - /, 'S' + station.station_id + ' '),
      data: station.veckodata.map(v => v.antal),
      borderColor: colors[i % colors.length],
      backgroundColor: colors[i % colors.length] + '33',
      borderWidth: 2,
      pointRadius: 3,
      tension: 0.3,
      fill: false,
    }));

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#a0aec0', font: { size: 11 }, boxWidth: 12, padding: 12 },
          },
          tooltip: {
            mode: 'index',
            intersect: false,
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 10 }, stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
            title: { display: true, text: 'Antal stopp', color: '#a0aec0' },
          },
        },
        interaction: {
          mode: 'nearest',
          axis: 'x',
          intersect: false,
        },
      },
    });
  }

  // ---- Rekommendationer ----

  private loadRekommendationer(): void {
    this.loadingRekom = true;
    this.errorRekom = false;
    this.svc.getRekommendationer().pipe(
      timeout(15000),
      catchError(() => { this.errorRekom = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingRekom = false;
      if (res?.success) {
        this.rekommendationer = res.data.rekommendationer;
        this.antalVarningar = res.data.antal_varningar;
        this.antalAtgarder = res.data.antal_atgarder;
        this.antalOk = res.data.antal_ok;
      } else if (res !== null) {
        this.errorRekom = true;
      }
    });
  }

  getRekomIcon(typ: string): string {
    switch (typ) {
      case 'varning': return 'fas fa-exclamation-triangle';
      case 'atgard': return 'fas fa-wrench';
      case 'ok': return 'fas fa-check-circle';
      default: return 'fas fa-info-circle';
    }
  }

  getRekomIconColor(typ: string): string {
    switch (typ) {
      case 'varning': return '#fc8181';
      case 'atgard': return '#f6ad55';
      case 'ok': return '#48bb78';
      default: return '#4299e1';
    }
  }

  getRekomBorderColor(typ: string): string {
    switch (typ) {
      case 'varning': return '#fc8181';
      case 'atgard': return '#f6ad55';
      case 'ok': return '#48bb78';
      default: return '#4a5568';
    }
  }
  trackByIndex(index: number): number { return index; }
}
