import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../utils/date-utils';
import {
  OeeTrendanalysService,
  SammanfattningData,
  PerStationData,
  TrendDataResult,
  FlaskhalserData,
  JamforelseData,
  PrediktionData,
  StationRow,
} from '../../services/oee-trendanalys.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-oee-trendanalys',
  templateUrl: './oee-trendanalys.component.html',
  styleUrls: ['./oee-trendanalys.component.css'],
  imports: [CommonModule, FormsModule],
})
export class OeeTrendanalysPage implements OnInit, OnDestroy {

  // Period
  period = 30;
  readonly periodOptions = [
    { days: 7,  label: '7d' },
    { days: 30, label: '30d' },
    { days: 90, label: '90d' },
  ];

  // Station filter
  selectedStation: number | null = null;
  stationsLista: StationRow[] = [];

  // Loading
  loadingSammanfattning = false;
  loadingStationer = false;
  loadingTrend = false;
  loadingFlaskhalsar = false;
  loadingJamforelse = false;
  loadingPrediktion = false;

  // Errors
  errorSammanfattning = false;
  errorStationer = false;
  errorTrend = false;
  errorFlaskhalsar = false;
  errorJamforelse = false;
  errorPrediktion = false;

  // Data
  sammanfattning: SammanfattningData | null = null;
  stationerData: PerStationData | null = null;
  trendData: TrendDataResult | null = null;
  flaskhalserData: FlaskhalserData | null = null;
  jamforelseData: JamforelseData | null = null;
  prediktionData: PrediktionData | null = null;

  // Charts
  private trendChart: Chart | null = null;
  private prediktionChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;
  private prediktionChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: OeeTrendanalysService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.trendChartTimer) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
    if (this.prediktionChartTimer) { clearTimeout(this.prediktionChartTimer); this.prediktionChartTimer = null; }
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  setPeriod(days: number): void {
    this.period = days;
    this.loadAll();
  }

  onStationChange(): void {
    this.loadTrend();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadSammanfattning();
    this.loadStationer();
    this.loadTrend();
    this.loadFlaskhalsar();
    this.loadJamforelse();
    this.loadPrediktion();
  }

  // ---- Helpers ----

  getTrendIcon(trend: string): string {
    if (trend === 'up') return 'fas fa-arrow-up';
    if (trend === 'down') return 'fas fa-arrow-down';
    return 'fas fa-minus';
  }

  getTrendText(trend: string): string {
    if (trend === 'up') return 'Uppat';
    if (trend === 'down') return 'Nedat';
    return 'Stabil';
  }

  getOeeColor(pct: number): string {
    if (pct >= 85) return '#4fd1c5';
    if (pct >= 60) return '#68d391';
    if (pct >= 40) return '#ecc94b';
    return '#fc8181';
  }

  getOrsakLabel(orsak: string): string {
    if (orsak === 'tillganglighet') return 'Tillgänglighet';
    if (orsak === 'prestanda') return 'Prestanda';
    if (orsak === 'kvalitet') return 'Kvalitet';
    return orsak;
  }

  // ---- Data loading ----

  private loadSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning = false;
    this.svc.getSammanfattning().pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingSammanfattning = false;
      this.isFetching = false;
      if (res?.success) {
        this.sammanfattning = res.data;
      } else {
        this.errorSammanfattning = true;
      }
    });
  }

  private loadStationer(): void {
    this.loadingStationer = true;
    this.errorStationer = false;
    this.svc.getPerStation(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingStationer = false;
      if (res?.success) {
        this.stationerData = res.data;
        // Uppdatera stationsfilter
        if (res.data.stationer?.length && this.stationsLista.length === 0) {
          this.stationsLista = res.data.stationer;
        }
      } else {
        this.errorStationer = true;
      }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getTrend(this.period, this.selectedStation ?? undefined).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        if (this.trendChartTimer) clearTimeout(this.trendChartTimer);
        this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(); }, 100);
      } else {
        this.errorTrend = true;
      }
    });
  }

  private loadFlaskhalsar(): void {
    this.loadingFlaskhalsar = true;
    this.errorFlaskhalsar = false;
    this.svc.getFlaskhalsar(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingFlaskhalsar = false;
      if (res?.success) {
        this.flaskhalserData = res.data;
      } else {
        this.errorFlaskhalsar = true;
      }
    });
  }

  private loadJamforelse(): void {
    this.loadingJamforelse = true;
    this.errorJamforelse = false;
    this.svc.getJamforelse(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingJamforelse = false;
      if (res?.success) {
        this.jamforelseData = res.data;
      } else {
        this.errorJamforelse = true;
      }
    });
  }

  private loadPrediktion(): void {
    this.loadingPrediktion = true;
    this.errorPrediktion = false;
    this.svc.getPrediktion().pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPrediktion = false;
      if (res?.success) {
        this.prediktionData = res.data;
        if (this.prediktionChartTimer) clearTimeout(this.prediktionChartTimer);
        this.prediktionChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildPrediktionChart(); }, 100);
      } else {
        this.errorPrediktion = true;
      }
    });
  }

  // ---- Chart builders ----

  private destroyCharts(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    try { this.prediktionChart?.destroy(); } catch (_) {}
    this.trendChart = null;
    this.prediktionChart = null;
  }

  private buildTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('oeeTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData?.trend?.length) return;

    const items = this.trendData.trend;
    const labels = items.map(i => {
      const dt = parseLocalDate(i.datum);
      return dt.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
    });

    const datasets: any[] = [
      {
        label: 'OEE %',
        data: items.map(i => i.oee_pct),
        borderColor: '#4fd1c5',
        backgroundColor: 'rgba(79,209,197,0.1)',
        fill: true,
        tension: 0.3,
        pointRadius: 2,
        pointHoverRadius: 5,
        borderWidth: 2,
      },
      {
        label: 'Rullande 7d-snitt',
        data: items.map(i => i.oee_ma7),
        borderColor: '#ecc94b',
        backgroundColor: 'transparent',
        tension: 0.4,
        pointRadius: 0,
        borderWidth: 2,
        borderDash: [5, 3],
      },
    ];

    // Referenslinjer
    if (this.trendData.world_class_pct) {
      datasets.push({
        label: 'World Class (85%)',
        data: items.map(() => this.trendData!.world_class_pct),
        borderColor: 'rgba(104,211,145,0.4)',
        backgroundColor: 'transparent',
        borderWidth: 1,
        borderDash: [10, 5],
        pointRadius: 0,
        pointHoverRadius: 0,
      });
    }

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxTicksLimit: 15, maxRotation: 45 },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v) => v + '%' },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
        },
      },
    });
  }

  private buildPrediktionChart(): void {
    try { this.prediktionChart?.destroy(); } catch (_) {}
    this.prediktionChart = null;

    const canvas = document.getElementById('prediktionChart') as HTMLCanvasElement;
    if (!canvas || !this.prediktionData) return;

    const hist = this.prediktionData.historisk || [];
    const pred = this.prediktionData.prediktion || [];
    if (hist.length === 0) return;

    // Visa bara senaste 14 dagar historik + 7 dagar prediktion
    const recentHist = hist.slice(-14);
    const allPoints = [...recentHist, ...pred];
    const labels = allPoints.map(p => {
      const dt = parseLocalDate(p.datum);
      return dt.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
    });

    const historiskData: (number | null)[] = recentHist.map(p => p.oee_pct);
    const prediktionChartData: (number | null)[] = new Array(recentHist.length).fill(null);
    // Overlapp: sista historiska punkt = forsta prediktionspunkt
    prediktionChartData[recentHist.length - 1] = recentHist[recentHist.length - 1].oee_pct;
    pred.forEach(p => prediktionChartData.push(p.oee_pct));

    const ma7Data: (number | null)[] = recentHist.map(p => p.oee_ma7 ?? null);
    pred.forEach(() => ma7Data.push(null));

    // Fullfyll historiskData med null for prediktionsdagar
    pred.forEach(() => historiskData.push(null));

    if (this.prediktionChart) { (this.prediktionChart as any).destroy(); }
    this.prediktionChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Historisk OEE',
            data: historiskData,
            borderColor: '#4fd1c5',
            backgroundColor: 'rgba(79,209,197,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 2,
          },
          {
            label: 'Prediktion',
            data: prediktionChartData,
            borderColor: '#b794f4',
            backgroundColor: 'rgba(183,148,244,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            borderWidth: 2,
            borderDash: [8, 4],
          },
          {
            label: 'Rullande 7d-snitt',
            data: ma7Data,
            borderColor: '#ecc94b',
            backgroundColor: 'transparent',
            tension: 0.4,
            pointRadius: 0,
            borderWidth: 2,
            borderDash: [5, 3],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                if (ctx.parsed.y === null) return '';
                return `${ctx.dataset.label}: ${ctx.parsed.y}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxTicksLimit: 15, maxRotation: 45 },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            min: 0,
            max: 100,
            ticks: { color: '#a0aec0', font: { size: 10 }, callback: (v) => v + '%' },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
        },
      },
    });
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? item?.station_id ?? index; }
}
