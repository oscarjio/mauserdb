import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  CykeltidHeatmapService,
  HeatmapOperator,
  HeatmapCell,
  DayPatternItem,
  DayPatternSummary,
  HourAvgItem,
  DagMatrixRow,
} from '../../services/cykeltid-heatmap.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-cykeltid-heatmap',
  templateUrl: './cykeltid-heatmap.html',
  styleUrls: ['./cykeltid-heatmap.css'],
  imports: [CommonModule, FormsModule],
})
export class CykeltidHeatmapComponent implements OnInit, OnDestroy, AfterViewInit {
  // Periodselektor
  days: number = 30;
  readonly periodOptions = [7, 14, 30, 90];

  // Heatmap
  heatmapLoading = false;
  heatmapLoaded = false;
  operators: HeatmapOperator[] = [];
  hours: number[] = [];
  matrix: HeatmapCell[][] = [];
  globalMin: number | null = null;
  globalMax: number | null = null;
  globalAvg: number | null = null;

  // Expanderad operatör (drilldown)
  expandedOperatorId: number | null = null;
  detailLoading = false;
  detailHours: number[] = [];
  detailDagMatrix: DagMatrixRow[] = [];
  detailHourAvg: HourAvgItem[] = [];
  detailOperatorNamn = '';
  detailMin: number | null = null;
  detailMax: number | null = null;

  // Dygnsmönster
  patternLoading = false;
  patternLoaded = false;
  dayPattern: DayPatternItem[] = [];
  patternSummary: DayPatternSummary | null = null;
  private patternChart: Chart | null = null;

  // Sammanfattning
  snabbastTimme: number | null = null;
  langsammastTimme: number | null = null;
  bastOperator: string | null = null;
  mestKonsekvent: string | null = null;

  private destroy$ = new Subject<void>();
  private viewReady = false;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: CykeltidHeatmapService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (this.patternLoaded && this.dayPattern.length > 0) {
      if (this.chartTimer) clearTimeout(this.chartTimer);
      this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderPatternChart(); }, 50);
    }
  }

  ngOnDestroy(): void {
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    try { this.patternChart?.destroy(); } catch (_e) { /* ignore */ }
    this.patternChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  onDaysChange(): void {
    this.heatmapLoaded = false;
    this.patternLoaded = false;
    this.expandedOperatorId = null;
    this.loadAll();
  }

  private loadAll(): void {
    this.loadHeatmap();
    this.loadDayPattern();
  }

  private loadHeatmap(): void {
    if (this.heatmapLoading) return;
    this.heatmapLoading = true;

    this.service.getHeatmapData(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.heatmapLoading = false;
        if (res?.success && res.data) {
          const d = res.data;
          this.operators = d.operators;
          this.hours = d.hours;
          this.matrix = d.matrix;
          this.globalMin = d.globalMin;
          this.globalMax = d.globalMax;
          this.globalAvg = d.globalAvg;
          this.computeSummary();
        } else {
          this.operators = [];
          this.hours = [];
          this.matrix = [];
        }
        this.heatmapLoaded = true;
      });
  }

  private loadDayPattern(): void {
    if (this.patternLoading) return;
    this.patternLoading = true;

    this.service.getDayPattern(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.patternLoading = false;
        if (res?.success && res.data) {
          this.dayPattern = res.data.pattern;
          this.patternSummary = res.data.summary;
          if (this.patternSummary) {
            this.snabbastTimme = this.patternSummary.snabbaste_timme;
            this.langsammastTimme = this.patternSummary.langsammaste_timme;
          }
        } else {
          this.dayPattern = [];
          this.patternSummary = null;
        }
        this.patternLoaded = true;
        if (this.viewReady) {
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderPatternChart(); }, 50);
        }
      });
  }

  private computeSummary(): void {
    if (!this.operators.length || !this.matrix.length) return;

    // Bäst operatör = lägst genomsnittlig cykeltid (exklusive null-celler)
    let bestAvg = Infinity;
    let bestIdx = -1;
    for (let i = 0; i < this.matrix.length; i++) {
      const vals = this.matrix[i].filter(c => c.avg_sek !== null).map(c => c.avg_sek as number);
      if (vals.length === 0) continue;
      const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
      if (avg < bestAvg) { bestAvg = avg; bestIdx = i; }
    }
    this.bastOperator = bestIdx >= 0 ? this.operators[bestIdx]?.namn ?? null : null;

    // Mest konsekvent = lägst stddev
    let lowestStddev = Infinity;
    let consistentIdx = -1;
    for (let i = 0; i < this.matrix.length; i++) {
      const vals = this.matrix[i].filter(c => c.avg_sek !== null).map(c => c.avg_sek as number);
      if (vals.length < 3) continue;
      const mean = vals.reduce((a, b) => a + b, 0) / vals.length;
      const std = Math.sqrt(vals.reduce((s, v) => s + (v - mean) ** 2, 0) / (vals.length - 1));
      if (std < lowestStddev) { lowestStddev = std; consistentIdx = i; }
    }
    this.mestKonsekvent = consistentIdx >= 0 ? this.operators[consistentIdx]?.namn ?? null : null;
  }

  // ================================================================
  // HEATMAP FÄRGSKALA
  // ================================================================

  getCellColor(cell: HeatmapCell): string {
    if (cell.avg_sek === null || this.globalMin === null || this.globalMax === null) {
      return 'transparent';
    }
    const range = this.globalMax - this.globalMin;
    if (range === 0) return 'rgba(72, 187, 120, 0.7)';

    // 0 = snabb (grön), 1 = långsam (röd)
    const t = Math.min(1, Math.max(0, (cell.avg_sek - this.globalMin) / range));

    if (t < 0.5) {
      // Grön → Gul
      const t2 = t * 2;
      const r = Math.round(72 + (255 - 72) * t2);
      const g = Math.round(187 + (200 - 187) * t2);
      const b = Math.round(120 * (1 - t2));
      return `rgba(${r}, ${g}, ${b}, 0.85)`;
    } else {
      // Gul → Röd
      const t2 = (t - 0.5) * 2;
      const r = 255;
      const g = Math.round(200 * (1 - t2));
      const b = 0;
      return `rgba(${r}, ${g}, ${b}, 0.85)`;
    }
  }

  getCellTextColor(cell: HeatmapCell): string {
    if (cell.avg_sek === null) return '#666';
    if (this.globalMin === null || this.globalMax === null) return '#fff';
    const range = this.globalMax - this.globalMin;
    if (range === 0) return '#fff';
    const t = (cell.avg_sek - this.globalMin) / range;
    return t > 0.3 && t < 0.7 ? '#1a202c' : '#fff';
  }

  formatSek(sek: number | null): string {
    if (sek === null) return '';
    const m = Math.floor(sek / 60);
    const s = Math.round(sek % 60);
    if (m === 0) return `${s}s`;
    return `${m}:${s.toString().padStart(2, '0')}`;
  }

  formatHour(h: number): string {
    return `${h.toString().padStart(2, '0')}:00`;
  }

  getRowAvg(rowCells: HeatmapCell[]): number | null {
    const vals = rowCells.filter(c => c.avg_sek !== null).map(c => c.avg_sek as number);
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / vals.length;
  }

  // ================================================================
  // DRILLDOWN
  // ================================================================

  toggleOperatorDetail(op: HeatmapOperator): void {
    if (this.expandedOperatorId === op.id) {
      this.expandedOperatorId = null;
      return;
    }
    this.expandedOperatorId = op.id;
    this.detailLoading = true;
    this.detailDagMatrix = [];
    this.detailHourAvg = [];

    this.service.getOperatorDetail(op.id, this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.detailLoading = false;
        if (res?.success && res.data) {
          const d = res.data;
          this.detailHours = d.hours;
          this.detailDagMatrix = d.dag_matrix;
          this.detailHourAvg = d.hour_avg;
          this.detailOperatorNamn = d.operator_namn;

          const allVals = d.dag_matrix
            .flatMap(r => r.celler)
            .filter(c => c.avg_sek !== null)
            .map(c => c.avg_sek as number);
          this.detailMin = allVals.length ? Math.min(...allVals) : null;
          this.detailMax = allVals.length ? Math.max(...allVals) : null;
        }
      });
  }

  getDetailCellColor(sek: number | null): string {
    if (sek === null || this.detailMin === null || this.detailMax === null) return 'transparent';
    const range = this.detailMax - this.detailMin;
    if (range === 0) return 'rgba(72, 187, 120, 0.7)';
    const t = Math.min(1, Math.max(0, (sek - this.detailMin) / range));
    if (t < 0.5) {
      const t2 = t * 2;
      const r = Math.round(72 + (255 - 72) * t2);
      const g = Math.round(187 + (200 - 187) * t2);
      const b = Math.round(120 * (1 - t2));
      return `rgba(${r}, ${g}, ${b}, 0.75)`;
    } else {
      const t2 = (t - 0.5) * 2;
      return `rgba(255, ${Math.round(200 * (1 - t2))}, 0, 0.75)`;
    }
  }

  formatDag(dag: string): string {
    const d = new Date(dag + 'T00:00:00');
    return d.toLocaleDateString('sv-SE', { weekday: 'short', month: 'short', day: 'numeric' });
  }

  // ================================================================
  // DYGNSMÖNSTER CHART
  // ================================================================

  private renderPatternChart(): void {
    try { this.patternChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('dayPatternChart') as HTMLCanvasElement;
    if (!canvas || !this.dayPattern.length) return;

    const labels = this.dayPattern.map(p => this.formatHour(p.hour));
    const avgSek = this.dayPattern.map(p => p.avg_sek);
    const antal = this.dayPattern.map(p => p.antal);

    if (this.patternChart) { (this.patternChart as any).destroy(); }
    this.patternChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Snitt cykeltid (sek)',
            data: avgSek,
            borderColor: 'rgba(66, 153, 225, 0.9)',
            backgroundColor: 'rgba(66, 153, 225, 0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            yAxisID: 'y',
          },
          {
            label: 'Antal IBC',
            data: antal,
            borderColor: 'rgba(72, 187, 120, 0.8)',
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.3,
            pointRadius: 3,
            borderDash: [5, 3],
            yAxisID: 'y1',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 12 }, boxWidth: 16, padding: 16 },
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              label: (item: any) => {
                if (item.datasetIndex === 0) {
                  return `Cykeltid: ${this.formatSek(item.raw)} (${item.raw.toFixed(0)} sek)`;
                }
                return `Antal IBC: ${item.raw}`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.04)' },
          },
          y: {
            position: 'left',
            ticks: {
              color: '#a0aec0',
              callback: (v: any) => this.formatSek(v),
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Cykeltid', color: '#a0aec0' },
          },
          y1: {
            position: 'right',
            ticks: { color: '#68d391', font: { size: 11 } },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Antal IBC', color: '#68d391' },
          },
        },
      },
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
