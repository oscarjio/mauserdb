import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  RebotlingService,
  KassationsSummaryData,
  KassationOrsak,
  KassationsDailyStackedData,
  KassationsDrilldownData,
} from '../../../../services/rebotling.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-statistik-kassationsanalys',
  templateUrl: './statistik-kassationsanalys.html',
  styleUrls: ['./statistik-kassationsanalys.css'],
  imports: [CommonModule, FormsModule],
})
export class StatistikKassationsanalysComponent implements OnInit, OnDestroy {
  // Periodselektor
  days: number = 30;
  readonly dayOptions = [7, 14, 30, 90];

  // Laddningsstatus
  loadingSummary = false;
  loadingByCause = false;
  loadingStacked = false;
  loadingDrilldown = false;

  // Data
  summary: KassationsSummaryData | null = null;
  orsaker: KassationOrsak[] = [];
  stackedData: KassationsDailyStackedData | null = null;
  drilldown: KassationsDrilldownData | null = null;
  selectedCause: KassationOrsak | null = null;

  // Felstatus
  errorSummary = false;
  errorByCause = false;
  errorStacked = false;
  errorDrilldown = false;

  private stackedChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.stackedChart?.destroy(); } catch (_) {}
    this.stackedChart = null;
  }

  // ---------------------------------------------------------------
  // Datainläsning
  // ---------------------------------------------------------------

  loadAll(): void {
    this.loadSummary();
    this.loadByCause();
    this.loadStacked();
    // Rensa drilldown vid periodytte
    this.drilldown = null;
    this.selectedCause = null;
  }

  onDaysChange(d: number): void {
    this.days = d;
    this.loadAll();
  }

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary = false;
    this.rebotlingService.getKassationsSummary(this.days)
      .pipe(timeout(12000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success) {
          this.summary = res.data;
        } else {
          this.errorSummary = true;
        }
      });
  }

  loadByCause(): void {
    this.loadingByCause = true;
    this.errorByCause = false;
    this.rebotlingService.getKassationsByCause(this.days)
      .pipe(timeout(12000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingByCause = false;
        if (res?.success) {
          this.orsaker = res.data.orsaker ?? [];
        } else {
          this.errorByCause = true;
          this.orsaker = [];
        }
      });
  }

  loadStacked(): void {
    this.loadingStacked = true;
    this.errorStacked = false;
    this.rebotlingService.getKassationsDailyStacked(this.days)
      .pipe(timeout(12000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStacked = false;
        if (res?.success) {
          this.stackedData = res.data;
          setTimeout(() => {
            if (!this.destroy$.closed) this.buildStackedChart();
          }, 0);
        } else {
          this.errorStacked = true;
          this.stackedData = null;
        }
      });
  }

  // ---------------------------------------------------------------
  // Drilldown
  // ---------------------------------------------------------------

  selectCause(orsak: KassationOrsak): void {
    if (this.selectedCause?.id === orsak.id) {
      // Avval — dölj drilldown
      this.selectedCause = null;
      this.drilldown = null;
      return;
    }
    this.selectedCause = orsak;
    this.loadDrilldown(orsak.id);
  }

  loadDrilldown(causeId: number): void {
    this.loadingDrilldown = true;
    this.errorDrilldown = false;
    this.drilldown = null;
    this.rebotlingService.getKassationsDrilldown(causeId, this.days)
      .pipe(timeout(12000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDrilldown = false;
        if (res?.success) {
          this.drilldown = res.data;
        } else {
          this.errorDrilldown = true;
        }
      });
  }

  closeDrilldown(): void {
    this.selectedCause = null;
    this.drilldown = null;
  }

  // ---------------------------------------------------------------
  // Chart.js — stackad stapelgraf
  // ---------------------------------------------------------------

  private buildStackedChart(): void {
    try { this.stackedChart?.destroy(); } catch (_) {}
    this.stackedChart = null;

    const canvas = document.getElementById('kassationsStackedChart') as HTMLCanvasElement;
    if (!canvas || !this.stackedData?.har_data) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { labels, datasets } = this.stackedData;

    // Förkortade datumrubriker beroende på antalet punkter
    const displayLabels = labels.map(l => {
      const parts = l.split('-');
      return `${parts[2]}/${parts[1]}`;
    });

    this.stackedChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: displayLabels,
        datasets: datasets.map(ds => ({
          label: ds.label,
          data: ds.data,
          backgroundColor: ds.backgroundColor,
          borderColor: ds.borderColor,
          borderWidth: ds.borderWidth,
          stack: 'kassationer',
        })),
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
          tooltip: {
            callbacks: {
              title: (items) => {
                const idx = items[0]?.dataIndex ?? 0;
                return labels[idx] ?? '';
              },
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: {
              color: '#a0aec0',
              maxRotation: 45,
              autoSkip: true,
              maxTicksLimit: this.days <= 14 ? 14 : this.days <= 30 ? 15 : 20,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal kassationer', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // ---------------------------------------------------------------
  // Hjälpmetoder för template
  // ---------------------------------------------------------------

  trendIcon(trend: 'up' | 'down' | 'stable'): string {
    if (trend === 'up') return '▲';
    if (trend === 'down') return '▼';
    return '—';
  }

  trendColor(trend: 'up' | 'down' | 'stable', invertGood = false): string {
    // invertGood=true → upp är dåligt (kassationer), ned är bra
    if (trend === 'stable') return '#a0aec0';
    const up = invertGood ? '#fc8181' : '#68d391';
    const down = invertGood ? '#68d391' : '#fc8181';
    return trend === 'up' ? up : down;
  }

  rateColor(rate: number): string {
    if (rate > 5) return '#fc8181';
    if (rate > 2) return '#f6ad55';
    return '#68d391';
  }

  getOperatorerForSkift(skiftraknare: number | null): string {
    if (!skiftraknare || !this.drilldown?.operatorer) return '–';
    const entry = this.drilldown.operatorer.find(o => o.skiftraknare === skiftraknare);
    if (!entry || entry.operatorer.length === 0) return '–';
    return entry.operatorer.join(', ');
  }

  formatDatum(d: string): string {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return d;
  }
  trackByIndex(index: number): number { return index; }
}
