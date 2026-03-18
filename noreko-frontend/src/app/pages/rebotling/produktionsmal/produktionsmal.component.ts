import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../../utils/date-utils';

import {
  ProduktionsmalService,
  SammanfattningData,
  PerSkiftData,
  VeckodataResponse,
  HistorikRad,
  StationData,
} from '../../../services/produktionsmal.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-rebotling-produktionsmal',
  templateUrl: './produktionsmal.component.html',
  styleUrls: ['./produktionsmal.component.css'],
  imports: [CommonModule, FormsModule],
})
export class RebotlingProduktionsmalPage implements OnInit, OnDestroy {
  Math = Math;

  // Laddnings/fel-state
  sammanfattningLoading = false;
  sammanfattningError = false;
  skiftLoading = false;
  skiftError = false;
  veckoLoading = false;
  veckoError = false;
  historikLoading = false;
  historikError = false;
  stationLoading = false;
  stationError = false;
  sparLoading = false;
  sparMeddelande = '';
  sparFel = '';

  // Data
  sammanfattning: SammanfattningData | null = null;
  skiftData: PerSkiftData | null = null;
  veckodata: VeckodataResponse | null = null;
  historik: HistorikRad[] = [];
  stationer: StationData[] = [];
  dagMal = 0;
  dagUtfall = 0;

  // Formular
  formTyp: 'dag' | 'vecka' = 'dag';
  formAntal: number | null = null;

  // Charts
  private veckoChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(private service: ProduktionsmalService) {}

  ngOnInit(): void {
    this.laddaAllt();
    this.refreshTimer = setInterval(() => {
      if (!this.destroy$.closed) this.laddaAllt();
    }, 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    if (this.refreshTimer) { clearInterval(this.refreshTimer); this.refreshTimer = null; }
    try { this.veckoChart?.destroy(); } catch (_) {}
    this.veckoChart = null;
  }

  private laddaAllt(): void {
    this.laddaSammanfattning();
    this.laddaSkift();
    this.laddaVeckodata();
    this.laddaHistorik();
    this.laddaStationer();
  }

  // ================================================================
  // DATA
  // ================================================================

  laddaSammanfattning(): void {
    this.sammanfattningLoading = true;
    this.sammanfattningError = false;
    this.service.getSammanfattning()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.sammanfattningLoading = false;
        if (res?.success) {
          this.sammanfattning = res.data;
        } else {
          this.sammanfattningError = true;
        }
      });
  }

  laddaSkift(): void {
    this.skiftLoading = true;
    this.skiftError = false;
    this.service.getPerSkift()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.skiftLoading = false;
        if (res?.success) {
          this.skiftData = res.data;
        } else {
          this.skiftError = true;
        }
      });
  }

  laddaVeckodata(): void {
    this.veckoLoading = true;
    this.veckoError = false;
    this.service.getVeckodata(4)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.veckoLoading = false;
        if (res?.success) {
          this.veckodata = res.data;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderVeckoChart();
          }, 100);
        } else {
          this.veckoError = true;
        }
      });
  }

  laddaHistorik(): void {
    this.historikLoading = true;
    this.historikError = false;
    this.service.getHistorik(30)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.historikLoading = false;
        if (res?.success) {
          this.historik = res.data.historik;
        } else {
          this.historikError = true;
        }
      });
  }

  laddaStationer(): void {
    this.stationLoading = true;
    this.stationError = false;
    this.service.getPerStation()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.stationLoading = false;
        if (res?.success) {
          this.stationer = res.data.stationer;
          this.dagMal = res.data.dag_mal;
          this.dagUtfall = res.data.dag_utfall;
        } else {
          this.stationError = true;
        }
      });
  }

  sparaMal(): void {
    if (!this.formAntal || this.formAntal <= 0) {
      this.sparFel = 'Ange ett giltigt antal.';
      return;
    }
    this.sparLoading = true;
    this.sparFel = '';
    this.sparMeddelande = '';

    this.service.sparaMal(this.formTyp, this.formAntal)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.sparLoading = false;
        if (res?.success) {
          this.sparMeddelande = res.data.meddelande;
          this.sparFel = '';
          this.laddaAllt();
        } else {
          this.sparFel = res?.error || 'Kunde inte spara malet.';
        }
      });
  }

  // ================================================================
  // CHARTS
  // ================================================================

  private renderVeckoChart(): void {
    try { this.veckoChart?.destroy(); } catch (_) {}
    this.veckoChart = null;

    const canvas = document.getElementById('veckoOverviewChart') as HTMLCanvasElement;
    if (!canvas || !this.veckodata) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const data = this.veckodata;
    const labels = data.datum.map(d => {
      const dt = parseLocalDate(d);
      return dt.toLocaleDateString('sv-SE', { day: 'numeric', month: 'numeric' });
    });

    const barColors = data.utfall.map((u, i) => u >= data.mal[i] ? '#48bb78' : '#fc8181');

    this.veckoChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Utfall',
            data: data.utfall,
            backgroundColor: barColors,
            borderRadius: 4,
            barPercentage: 0.7,
          },
          {
            label: 'Mal',
            data: data.mal,
            type: 'line',
            borderColor: '#f6ad55',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 0,
            fill: false,
            tension: 0,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (item) => {
                const v = item.raw as number;
                return ` ${item.dataset.label}: ${v.toLocaleString('sv-SE')} IBC`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => val.toLocaleString('sv-SE'),
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'IBC',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJALPMETODER
  // ================================================================

  formatNumber(n: number | null | undefined): string {
    if (n === null || n === undefined) return '-';
    return n.toLocaleString('sv-SE');
  }

  formatDatum(datum: string): string {
    if (!datum) return '-';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { day: 'numeric', month: 'short' });
  }

  uppfyllnadFarg(pct: number): string {
    if (pct >= 90) return '#48bb78';
    if (pct >= 70) return '#ecc94b';
    return '#fc5c65';
  }

  trendIcon(trend: string): string {
    if (trend === 'upp') return 'fa-arrow-up';
    if (trend === 'ner') return 'fa-arrow-down';
    return 'fa-minus';
  }

  trendColor(trend: string): string {
    if (trend === 'upp') return '#48bb78';
    if (trend === 'ner') return '#fc5c65';
    return '#a0aec0';
  }

  progressBarWidth(pct: number): string {
    return Math.min(100, Math.max(0, pct)) + '%';
  }

  progressBarColor(farg: string): string {
    if (farg === 'gron') return '#48bb78';
    if (farg === 'gul') return '#ecc94b';
    return '#fc5c65';
  }

  stationBarWidth(station: StationData): string {
    if (!this.dagMal || this.dagMal <= 0) return '0%';
    const pct = Math.min(100, (station.antal / this.dagMal) * 100 * 8);
    return pct + '%';
  }

  stationBarColor(station: StationData): string {
    return station.antal > 0 ? '#4fd1c5' : '#4a5568';
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
