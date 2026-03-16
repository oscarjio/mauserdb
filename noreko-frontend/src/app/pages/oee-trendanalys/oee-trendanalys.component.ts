import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
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
    if (orsak === 'tillganglighet') return 'Tillganglighet';
    if (orsak === 'prestanda') return 'Prestanda';
    if (orsak === 'kvalitet') return 'Kvalitet';
    return orsak;
  }

  // ---- Data loading ----

  private loadSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.svc.getSammanfattning().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingSammanfattning = false;
      if (res?.success) {
        this.sammanfattning = res.data;
      }
    });
  }

  private loadStationer(): void {
    this.loadingStationer = true;
    this.svc.getPerStation(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingStationer = false;
      if (res?.success) {
        this.stationerData = res.data;
        // Uppdatera stationsfilter
        if (res.data.stationer?.length && this.stationsLista.length === 0) {
          this.stationsLista = res.data.stationer;
        }
      }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.svc.getTrend(this.period, this.selectedStation ?? undefined).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success) {
        this.trendData = res.data;
        if (this.trendChartTimer) clearTimeout(this.trendChartTimer);
        this.trendChartTimer = setTimeout(() => this.buildTrendChart(), 100);
      }
    });
  }

  private loadFlaskhalsar(): void {
    this.loadingFlaskhalsar = true;
    this.svc.getFlaskhalsar(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingFlaskhalsar = false;
      if (res?.success) {
        this.flaskhalserData = res.data;
      }
    });
  }

  private loadJamforelse(): void {
    this.loadingJamforelse = true;
    this.svc.getJamforelse(this.period).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingJamforelse = false;
      if (res?.success) {
        this.jamforelseData = res.data;
      }
    });
  }

  private loadPrediktion(): void {
    this.loadingPrediktion = true;
    this.svc.getPrediktion().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPrediktion = false;
      if (res?.success) {
        this.prediktionData = res.data;
        if (this.prediktionChartTimer) clearTimeout(this.prediktionChartTimer);
        this.prediktionChartTimer = setTimeout(() => this.buildPrediktionChart(), 100);
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
}
