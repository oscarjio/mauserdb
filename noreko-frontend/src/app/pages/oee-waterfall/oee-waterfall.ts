import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  OeeWaterfallService,
  WaterfallSegment,
  OeeSummaryData,
} from '../../services/oee-waterfall.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-oee-waterfall',
  templateUrl: './oee-waterfall.html',
  styleUrls: ['./oee-waterfall.css'],
  imports: [CommonModule, FormsModule],
})
export class OeeWaterfallPage implements OnInit, OnDestroy {
  // -- Period --
  days = 7;
  readonly dayOptions = [
    { value: 7,  label: '7 dagar' },
    { value: 14, label: '14 dagar' },
    { value: 30, label: '30 dagar' },
    { value: 90, label: '90 dagar' },
  ];

  // -- Laddning --
  loadingSummary   = false;
  loadingWaterfall = false;

  // -- Fel --
  errorSummary   = false;
  errorWaterfall = false;

  // -- Data --
  summary: OeeSummaryData | null = null;
  segments: WaterfallSegment[] = [];
  totalTimmar = 0;

  // -- Chart --
  private waterfallChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private svc: OeeWaterfallService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.waterfallChart?.destroy(); } catch (_) {}
    this.waterfallChart = null;
  }

  // =================================================================
  // Period
  // =================================================================

  onDaysChange(d: number): void {
    this.days = d;
    this.loadAll();
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadSummary();
    this.loadWaterfallData();
  }

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getSummary(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success) {
          this.summary = res.data;
        } else {
          this.errorSummary = true;
          this.summary = null;
        }
      });
  }

  loadWaterfallData(): void {
    this.loadingWaterfall = true;
    this.errorWaterfall   = false;
    this.destroyChart();
    this.svc.getWaterfallData(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingWaterfall = false;
        if (res?.success) {
          this.segments    = res.data.segments ?? [];
          this.totalTimmar = res.data.total_timmar ?? 0;
          setTimeout(() => { if (!this.destroy$.closed) this.buildWaterfallChart(); }, 0);
        } else {
          this.errorWaterfall = true;
          this.segments    = [];
          this.totalTimmar = 0;
        }
      });
  }

  // =================================================================
  // Chart.js — Waterfall floating bar chart
  // =================================================================

  private buildWaterfallChart(): void {
    this.destroyChart();
    const canvas = document.getElementById('waterfallChart') as HTMLCanvasElement;
    if (!canvas || this.segments.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Waterfall: floating bars [bar_start, bar_slut]
    const labels = this.segments.map(s => s.label);
    const data   = this.segments.map(s => [s.bar_start, s.bar_slut]);
    const colors = this.segments.map(s => s.farg + 'cc'); // lite transparens
    const borders = this.segments.map(s => s.farg);

    if (this.waterfallChart) { (this.waterfallChart as any).destroy(); }
    this.waterfallChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Timmar',
            data: data as any,
            backgroundColor: colors,
            borderColor: borders,
            borderWidth: 2,
            borderRadius: 4,
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
              label: (item: any) => {
                const seg = this.segments[item.dataIndex];
                if (!seg) return '';
                const h = seg.timmar.toFixed(1);
                const p = seg.procent.toFixed(1);
                if (seg.typ === 'total') return ` Total: ${h}h (100%)`;
                if (seg.typ === 'effektiv') return ` Effektiv tid: ${h}h (${p}% av total)`;
                return ` Förlust: ${h}h (${p}% av total)`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              maxRotation: 20,
              font: { size: 11 },
              callback: (_val: any, idx: number) => {
                const lbl = labels[idx] ?? '';
                return lbl.length > 20 ? lbl.substring(0, 18) + '…' : lbl;
              },
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => `${v}h`,
            },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: {
              display: true,
              text: 'Timmar',
              color: '#a0aec0',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjälpmetoder
  // =================================================================

  oeeKlassFarg(klass: string | undefined): string {
    if (!klass) return '#a0aec0';
    if (klass === 'world-class') return '#48bb78';
    if (klass === 'bra') return '#ecc94b';
    return '#fc8181';
  }

  oeeKlassText(klass: string | undefined): string {
    if (!klass) return '';
    if (klass === 'world-class') return 'Världsklass ≥85%';
    if (klass === 'bra') return 'Bra 60–85%';
    return 'Lågt <60%';
  }

  faktorfarg(pct: number): string {
    if (pct >= 85) return '#48bb78';
    if (pct >= 60) return '#ecc94b';
    return '#fc8181';
  }

  trendPil(trend: number): string {
    if (trend > 0.5) return '▲';
    if (trend < -0.5) return '▼';
    return '—';
  }

  trendFarg(trend: number): string {
    if (trend > 0.5) return '#68d391';
    if (trend < -0.5) return '#fc8181';
    return '#a0aec0';
  }

  get harData(): boolean {
    return this.segments.length > 0 && this.totalTimmar > 0;
  }

  formatTimmar(h: number): string {
    if (!h || h <= 0) return '0h';
    const helH = Math.floor(h);
    const min  = Math.round((h - helH) * 60);
    if (min === 0) return `${helH}h`;
    return `${helH}h ${min}min`;
  }

  forlustSegments(): WaterfallSegment[] {
    return this.segments.filter(s => s.typ === 'forlust');
  }
  trackByIndex(index: number): number { return index; }
}
