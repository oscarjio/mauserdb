import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  RebotlingService,
  HeatmapVeckodag,
  HourlySummaryRow,
  PeakTimmeRow,
} from '../../../services/rebotling.service';

Chart.register(...registerables);

interface PeriodOption { val: number; label: string; }

@Component({
  standalone: true,
  selector: 'app-produktionseffektivitet',
  templateUrl: './produktionseffektivitet.html',
  styleUrl: './produktionseffektivitet.css',
  imports: [CommonModule, FormsModule],
})
export class ProduktionseffektivitetPage implements OnInit, OnDestroy {
  Math = Math;

  // ---- Period ----
  period = 30;
  readonly periodOptions: PeriodOption[] = [
    { val: 7,  label: '7 dagar'  },
    { val: 30, label: '30 dagar' },
    { val: 90, label: '90 dagar' },
  ];

  // ---- Laddning ----
  loadingHeatmap  = false;
  loadingSummary  = false;
  loadingPeak     = false;

  // ---- Fel ----
  errorHeatmap  = false;
  errorSummary  = false;
  errorPeak     = false;

  // ---- Data ----
  heatmapVeckodagar: HeatmapVeckodag[] = [];
  heatmapMaxVal = 0;
  timmar: number[] = [];
  summaryTimmar: HourlySummaryRow[] = [];
  topp3: PeakTimmeRow[] = [];
  botten3: PeakTimmeRow[] = [];
  skillnadPct: number | null = null;
  harData = false;

  // ---- KPI:er ----
  mostProductiveHour: HourlySummaryRow | null = null;
  leastProductiveHour: HourlySummaryRow | null = null;

  // ---- Chart ----
  private lineChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  private pollInterval: ReturnType<typeof setInterval> | null = null;

  // ---- Uppdateringstid ----
  lastUpdated: string | null = null;

  constructor(private svc: RebotlingService) {}

  ngOnInit(): void {
    this.loadAll();
    this.pollInterval = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
    this.destroyLineChart();
  }

  // ================================================================
  // Period
  // ================================================================

  onPeriodChange(p: number): void {
    this.period = p;
    this.loadAll();
  }

  // ================================================================
  // Data loading
  // ================================================================

  loadAll(): void {
    this.loadHeatmap();
    this.loadSummary();
    this.loadPeak();
  }

  loadHeatmap(): void {
    if (this.loadingHeatmap) return;
    this.loadingHeatmap = true;
    this.errorHeatmap = false;

    this.svc.getHourlyHeatmap(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingHeatmap = false;
        if (res?.success && res.data) {
          this.heatmapVeckodagar = res.data.veckodagar;
          this.heatmapMaxVal = res.data.max_val;
          this.timmar = res.data.timmar;
          this.lastUpdated = new Date().toLocaleTimeString('sv-SE');
        } else {
          this.errorHeatmap = true;
        }
      });
  }

  loadSummary(): void {
    if (this.loadingSummary) return;
    this.loadingSummary = true;
    this.errorSummary = false;

    this.svc.getHourlySummary(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success && res.data) {
          this.summaryTimmar = res.data.timmar.filter((t: any) => t.snitt_ibc !== null);
          this.computeKpis();
          this._timers.push(setTimeout(() => {
            if (!this.destroy$.closed) { this.buildLineChart(); }
          }, 0));
        } else {
          this.errorSummary = true;
        }
      });
  }

  loadPeak(): void {
    if (this.loadingPeak) return;
    this.loadingPeak = true;
    this.errorPeak = false;

    this.svc.getPeakAnalysis(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingPeak = false;
        if (res?.success && res.data) {
          this.topp3       = res.data.topp3;
          this.botten3     = res.data.botten3;
          this.skillnadPct = res.data.skillnad_pct;
          this.harData     = res.data.har_data;
        } else {
          this.errorPeak = true;
        }
      });
  }

  // ================================================================
  // KPI-beräkning
  // ================================================================

  private computeKpis(): void {
    if (!this.summaryTimmar.length) {
      this.mostProductiveHour = null;
      this.leastProductiveHour = null;
      return;
    }
    const withData = this.summaryTimmar.filter(t => (t.snitt_ibc ?? 0) > 0);
    if (!withData.length) return;

    this.mostProductiveHour = withData.reduce((prev, cur) =>
      (cur.snitt_ibc ?? 0) > (prev.snitt_ibc ?? 0) ? cur : prev
    );
    this.leastProductiveHour = withData.reduce((prev, cur) =>
      (cur.snitt_ibc ?? 0) < (prev.snitt_ibc ?? 0) ? cur : prev
    );
  }

  // ================================================================
  // Heatmap-färg
  // ================================================================

  /** Interpolerar färg: röd (0) → gul (50%) → grön (100%) */
  cellColor(value: number | null): string {
    if (value === null || value === 0) {
      return '#1a202c'; // tom cell = bakgrundsfärg
    }
    if (this.heatmapMaxVal === 0) return '#1a202c';

    const ratio = Math.min(1, value / this.heatmapMaxVal);

    if (ratio <= 0.5) {
      // Röd → Gul
      const t = ratio * 2;
      const r = 220;
      const g = Math.round(50 + t * 176); // 50 → 226
      const b = 50;
      return `rgb(${r},${g},${b})`;
    } else {
      // Gul → Grön
      const t = (ratio - 0.5) * 2;
      const r = Math.round(220 - t * 146); // 220 → 74
      const g = Math.round(226 - t * 14);  // 226 → 212
      const b = 50;
      return `rgb(${r},${g},${b})`;
    }
  }

  /** Textfärg beroende på bakgrundsfärg (mörk/ljus) */
  cellTextColor(value: number | null): string {
    if (value === null || value === 0) return '#4a5568';
    if (this.heatmapMaxVal === 0) return '#e2e8f0';
    const ratio = value / this.heatmapMaxVal;
    return ratio > 0.15 ? '#1a202c' : '#a0aec0';
  }

  // ================================================================
  // Chart.js — Linjediagram: snitt IBC/h per timme (0-23)
  // ================================================================

  private destroyLineChart(): void {
    try { this.lineChart?.destroy(); } catch (_) {}
    this.lineChart = null;
  }

  private buildLineChart(): void {
    this.destroyLineChart();
    if (!this.summaryTimmar.length) return;

    const canvas = document.getElementById('hourlyLineChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    // Bygger fullständig array 0-23 med null om ingen data
    const allHours = Array.from({ length: 24 }, (_, h) => h);
    const dataMap = new Map(this.summaryTimmar.map(t => [t.timme, t.snitt_ibc ?? 0]));
    const data = allHours.map(h => dataMap.get(h) ?? null);

    // Färg per datapunkt baserat på värde
    const maxVal = Math.max(...(data.filter(v => v !== null) as number[]));
    const pointColors = data.map(v => {
      if (v === null) return '#4a5568';
      const ratio = maxVal > 0 ? v / maxVal : 0;
      if (ratio >= 0.7) return '#68d391';
      if (ratio >= 0.4) return '#f6ad55';
      return '#fc8181';
    });

    this.lineChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: allHours.map(h => `${String(h).padStart(2, '0')}:00`),
        datasets: [{
          label: 'Snitt IBC/h',
          data,
          borderColor: '#4299e1',
          backgroundColor: 'rgba(66,153,225,0.12)',
          pointBackgroundColor: pointColors,
          pointBorderColor: pointColors,
          pointRadius: 5,
          pointHoverRadius: 7,
          fill: true,
          tension: 0.4,
          spanGaps: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.parsed.y;
                return v !== null ? ` ${v.toFixed(1)} IBC` : ' Ingen data';
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: '#374151' },
            title: { display: true, text: 'Timme på dygnet', color: '#a0aec0' },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid:  { color: '#374151' },
            title: { display: true, text: 'Snitt IBC per timme', color: '#a0aec0' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ================================================================
  // Template helpers
  // ================================================================

  timmeLabel(h: number): string {
    return `${String(h).padStart(2, '0')}:00`;
  }

  formatTimme(h: number): string {
    return `${String(h).padStart(2, '0')}:00–${String((h + 1) % 24).padStart(2, '0')}:00`;
  }

  peakBarWidth(snitt: number, maxSnitt: number): number {
    return maxSnitt > 0 ? Math.round((snitt / maxSnitt) * 100) : 0;
  }

  get topp3MaxSnitt(): number {
    return this.topp3.length ? this.topp3[0].snitt_ibc : 1;
  }

  get botten3MaxSnitt(): number {
    if (!this.botten3.length) return 1;
    return Math.max(...this.botten3.map(b => b.snitt_ibc));
  }

  skillnadStr(pct: number | null): string {
    if (pct === null) return 'okänd';
    return `+${pct.toFixed(0)}%`;
  }

  get isLoading(): boolean {
    return this.loadingHeatmap || this.loadingSummary || this.loadingPeak;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
