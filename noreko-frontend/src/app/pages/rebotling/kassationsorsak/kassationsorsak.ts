import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KassationsorsakPerStationService,
  KassationOverview,
  PerStationData,
  TopOrsakerData,
  TrendData,
  DetaljerData,
} from '../../../services/kassationsorsak-per-station.service';

Chart.register(...registerables);

const STATION_COLORS: Record<string, string> = {
  inspektion:   '#fc8181',
  tvatt:        '#63b3ed',
  fyllning:     '#68d391',
  etikettering: '#f6ad55',
  slutkontroll: '#b794f4',
};

const STATION_COLORS_ARRAY = ['#fc8181', '#63b3ed', '#68d391', '#f6ad55', '#b794f4'];
const ORSAK_COLORS = ['#fc8181', '#f6ad55', '#ecc94b', '#68d391', '#63b3ed'];

@Component({
  standalone: true,
  selector: 'app-kassationsorsak',
  templateUrl: './kassationsorsak.html',
  styleUrls: ['./kassationsorsak.css'],
  imports: [CommonModule, FormsModule],
})
export class KassationsorsakPage implements OnInit, OnDestroy {

  // Periodselektor
  dagar = 30;
  readonly dagarAlternativ = [
    { varde: 1,  etikett: 'Idag' },
    { varde: 7,  etikett: '7 dagar' },
    { varde: 30, etikett: '30 dagar' },
    { varde: 90, etikett: '90 dagar' },
  ];

  // Stationsfilter
  valdStation = '';
  tillgangligaStationer: { id: string; namn: string }[] = [];

  // Loading
  loadingOverview    = false;
  loadingPerStation  = false;
  loadingTopOrsaker  = false;
  loadingTrend       = false;
  loadingDetaljer    = false;

  // Error
  errorOverview    = false;
  errorPerStation  = false;
  errorTopOrsaker  = false;
  errorTrend       = false;
  errorDetaljer    = false;

  // Data
  overview:    KassationOverview | null = null;
  perStation:  PerStationData | null    = null;
  topOrsaker:  TopOrsakerData | null    = null;
  trendData:   TrendData | null         = null;
  detaljer:    DetaljerData | null      = null;

  // Charts
  private stapelChart:   Chart | null = null;
  private orsakChart:    Chart | null = null;
  private trendChart:    Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private svc: KassationsorsakPerStationService) {}

  ngOnInit(): void {
    this.laddaAllt();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyaCharts();
  }

  private destroyaCharts(): void {
    try { this.stapelChart?.destroy(); }  catch (_) {}
    try { this.orsakChart?.destroy(); }   catch (_) {}
    try { this.trendChart?.destroy(); }   catch (_) {}
    this.stapelChart  = null;
    this.orsakChart   = null;
    this.trendChart   = null;
  }

  // =============================================================
  // Period / Filter
  // =============================================================

  byttPeriod(d: number): void {
    this.dagar = d;
    this.laddaAllt();
  }

  byttStation(): void {
    this.laddaTopOrsaker();
  }

  laddaAllt(): void {
    this.laddaOverview();
    this.laddaPerStation();
    this.laddaTopOrsaker();
    this.laddaTrend();
    this.laddaDetaljer();
  }

  // =============================================================
  // Data
  // =============================================================

  laddaOverview(): void {
    this.loadingOverview = true;
    this.errorOverview   = false;
    this.svc.getOverview()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) {
          this.overview = res.data;
        } else {
          this.errorOverview = true;
        }
      });
  }

  laddaPerStation(): void {
    this.loadingPerStation = true;
    this.errorPerStation   = false;
    this.svc.getPerStation(this.dagar)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingPerStation = false;
        if (res?.success) {
          this.perStation = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggStapelChart(); }, 0);
        } else {
          this.errorPerStation = true;
          this.perStation = null;
        }
      });
  }

  laddaTopOrsaker(): void {
    this.loadingTopOrsaker = true;
    this.errorTopOrsaker   = false;
    const station = this.valdStation || undefined;
    this.svc.getTopOrsaker(this.dagar, station)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTopOrsaker = false;
        if (res?.success) {
          this.topOrsaker = res.data;
          this.tillgangligaStationer = res.data.stationer ?? [];
          setTimeout(() => { if (!this.destroy$.closed) this.byggOrsakChart(); }, 0);
        } else {
          this.errorTopOrsaker = true;
          this.topOrsaker = null;
        }
      });
  }

  laddaTrend(): void {
    this.loadingTrend = true;
    this.errorTrend   = false;
    this.svc.getTrend(this.dagar)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggTrendChart(); }, 0);
        } else {
          this.errorTrend = true;
          this.trendData = null;
        }
      });
  }

  laddaDetaljer(): void {
    this.loadingDetaljer = true;
    this.errorDetaljer   = false;
    this.svc.getDetaljer(this.dagar)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDetaljer = false;
        if (res?.success) {
          this.detaljer = res.data;
        } else {
          this.errorDetaljer = true;
          this.detaljer = null;
        }
      });
  }

  // =============================================================
  // Chart — Stapeldiagram per station
  // =============================================================

  private byggStapelChart(): void {
    try { this.stapelChart?.destroy(); } catch (_) {}
    this.stapelChart = null;

    const canvas = document.getElementById('kpsStapelChart') as HTMLCanvasElement;
    if (!canvas || !this.perStation) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { stationer, snitt_per_station } = this.perStation;
    const labels   = stationer.map(s => s.namn);
    const values   = stationer.map(s => s.kasserade);
    const colors   = stationer.map(s => STATION_COLORS[s.id] ?? '#a0aec0');
    const snittArr = stationer.map(() => snitt_per_station);

    this.stapelChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Kasserade IBC',
            data: values,
            backgroundColor: colors.map(c => c + 'cc'),
            borderColor: colors,
            borderWidth: 1,
            order: 2,
          } as any,
          {
            label: 'Genomsnitt',
            data: snittArr,
            type: 'line',
            borderColor: '#e2e8f0',
            borderDash: [6, 3],
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
            tension: 0,
            order: 1,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid:  { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal kasserade', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =============================================================
  // Chart — Horisontellt stapeldiagram (top-5 orsaker)
  // =============================================================

  private byggOrsakChart(): void {
    try { this.orsakChart?.destroy(); } catch (_) {}
    this.orsakChart = null;

    const canvas = document.getElementById('kpsOrsakChart') as HTMLCanvasElement;
    if (!canvas || !this.topOrsaker) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const orsaker = this.topOrsaker.orsaker.filter(o => o.antal > 0);
    if (orsaker.length === 0) return;

    this.orsakChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: orsaker.map(o => o.orsak),
        datasets: [{
          label: 'Antal kassationer',
          data: orsaker.map(o => o.antal),
          backgroundColor: orsaker.map((_, i) => ORSAK_COLORS[i % ORSAK_COLORS.length] + 'cc'),
          borderColor: orsaker.map((_, i) => ORSAK_COLORS[i % ORSAK_COLORS.length]),
          borderWidth: 1,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.raw} st (${orsaker[item.dataIndex]?.procent ?? 0}%)`,
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid:  { color: 'rgba(255,255,255,0.08)' },
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  // =============================================================
  // Chart — Trendgraf (linje per station)
  // =============================================================

  private byggTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('kpsTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { labels, series } = this.trendData;
    const harData = series.some(s => s.values.some(v => v !== null && v > 0));
    if (!harData) return;

    const datasets = series.map((s, i) => ({
      label: s.station_namn,
      data: s.values,
      borderColor: STATION_COLORS_ARRAY[i % STATION_COLORS_ARRAY.length],
      backgroundColor: 'transparent',
      tension: 0.3,
      pointRadius: 2,
      borderWidth: 2,
      spanGaps: true,
    }));

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels.map(l => l.substring(5)), // MM-DD
        datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 10, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => {
                const v = item.raw as number | null;
                return ` ${item.dataset.label}: ${v !== null ? v + '%' : 'ingen data'}`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, maxTicksLimit: 15 },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid:  { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Kassation (%)', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =============================================================
  // Hjälpmetoder
  // =============================================================

  trendIkon(trend: string): string {
    if (trend === 'up')   return '\u25B2';
    if (trend === 'down') return '\u25BC';
    return '\u2014';
  }

  trendFarg(trend: string): string {
    if (trend === 'up')   return '#fc8181';  // sämre = rött
    if (trend === 'down') return '#68d391';  // bättre = grönt
    return '#a0aec0';
  }

  kassationFarg(pct: number): string {
    if (pct > 5) return '#fc8181';
    if (pct > 2) return '#f6ad55';
    return '#68d391';
  }

  stationFarg(id: string): string {
    return STATION_COLORS[id] ?? '#a0aec0';
  }

  formatDatum(d: string): string {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return d;
  }
}
