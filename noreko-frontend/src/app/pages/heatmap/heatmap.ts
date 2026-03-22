import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  HeatmapService,
  HeatmapCell,
  HeatmapScale,
  HeatmapSummaryData,
} from '../../services/heatmap.service';

// Timmar att visa (06:00 – 22:00)
const DISPLAY_HOURS = [6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22];

@Component({
  standalone: true,
  selector: 'app-heatmap',
  templateUrl: './heatmap.html',
  styleUrls: ['./heatmap.css'],
  imports: [CommonModule],
})
export class HeatmapPage implements OnInit, OnDestroy {
  // -- Period --
  days = 30;
  readonly dayOptions = [7, 14, 30, 90];

  // -- Laddning --
  loadingHeatmap  = false;
  loadingSummary  = false;

  // -- Fel --
  errorHeatmap  = false;
  errorSummary  = false;

  // -- Data --
  summary: HeatmapSummaryData | null = null;
  scale: HeatmapScale = { min: 0, max: 0, avg: 0 };

  // -- Matris --
  readonly displayHours = DISPLAY_HOURS;
  dates: string[] = [];           // kolumner (datumstrangar)
  // cellMap[date][hour] = count (undefined = ingen data)
  cellMap: Record<string, Record<number, number>> = {};

  // -- Tooltip --
  tooltip: { visible: boolean; x: number; y: number; text: string } = {
    visible: false, x: 0, y: 0, text: '',
  };

  private destroy$ = new Subject<void>();

  constructor(private svc: HeatmapService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
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
    this.loadHeatmapData();
    this.loadSummary();
  }

  loadHeatmapData(): void {
    this.loadingHeatmap = true;
    this.errorHeatmap   = false;
    this.svc.getHeatmapData(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingHeatmap = false;
        if (res?.success) {
          this.scale = res.data.scale;
          this.buildMatrix(res.data.matrix, res.data.from_date, res.data.to_date);
        } else {
          this.errorHeatmap = true;
          this.dates   = [];
          this.cellMap = {};
        }
      });
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

  // =================================================================
  // Matris-byggnad
  // =================================================================

  private buildMatrix(matrix: HeatmapCell[], fromDate: string, toDate: string): void {
    // Generera alla datum i perioden
    const dates: string[] = [];
    let cur = new Date(fromDate);
    const end = new Date(toDate);
    while (cur <= end) {
      dates.push(this.toDateStr(cur));
      cur.setDate(cur.getDate() + 1);
    }
    this.dates = dates;

    // Bygg uppslagstabell
    const map: Record<string, Record<number, number>> = {};
    for (const cell of matrix) {
      if (!map[cell.date]) map[cell.date] = {};
      map[cell.date][cell.hour] = cell.count;
    }
    this.cellMap = map;
  }

  private toDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  // =================================================================
  // Fargberakning
  // =================================================================

  getCellCount(date: string, hour: number): number | null {
    const c = this.cellMap[date]?.[hour];
    return c !== undefined ? c : null;
  }

  getCellColor(count: number | null): string {
    if (count === null || count === 0) return '#1e2a3a'; // Ingen data — morkt
    if (this.scale.max === 0) return '#276749';
    const ratio = (count - this.scale.min) / (this.scale.max - this.scale.min || 1);
    const clamped = Math.max(0, Math.min(1, ratio));

    // Fargskala: morkt gront (lag) → intensivt gront (hog)
    // 0 → #276749 (mork gron), 1 → #48bb78 → #68d391 → #9ae6b4
    const r = Math.round(39  + clamped * (154 - 39));
    const g = Math.round(103 + clamped * (230 - 103));
    const b = Math.round(73  + clamped * (180 - 73));
    return `rgb(${r},${g},${b})`;
  }

  getCellTextColor(count: number | null): string {
    if (count === null || count === 0) return '#4a5568';
    if (this.scale.max === 0) return '#e2e8f0';
    const ratio = (count - this.scale.min) / (this.scale.max - this.scale.min || 1);
    return ratio > 0.5 ? '#1a202c' : '#e2e8f0';
  }

  // =================================================================
  // Tooltip
  // =================================================================

  showTooltip(event: MouseEvent, date: string, hour: number): void {
    const count = this.getCellCount(date, hour);
    const text = count !== null
      ? `${this.formatDate(date)} kl ${hour}:00\n${count} IBC`
      : `${this.formatDate(date)} kl ${hour}:00\nIngen data`;
    this.tooltip = {
      visible: true,
      x: event.clientX + 12,
      y: event.clientY - 40,
      text,
    };
  }

  hideTooltip(): void {
    this.tooltip = { ...this.tooltip, visible: false };
  }

  // =================================================================
  // Legend-steg
  // =================================================================

  getLegendSteps(): { color: string; label: string }[] {
    const steps = [0, 0.25, 0.5, 0.75, 1];
    return steps.map(ratio => {
      const fakeCount = this.scale.min + ratio * (this.scale.max - this.scale.min);
      return {
        color: this.getCellColor(fakeCount),
        label: ratio === 0 ? `${this.scale.min}` : ratio === 1 ? `${this.scale.max}` : '',
      };
    });
  }

  // =================================================================
  // Hjalpmetoder
  // =================================================================

  formatDate(d: string): string {
    if (!d) return '-';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return d;
  }

  formatHour(h: number): string {
    return `${String(h).padStart(2, '0')}:00`;
  }

  formatNumber(n: number | null): string {
    if (n === null || n === undefined) return '-';
    return n.toLocaleString('sv-SE');
  }

  get hasData(): boolean {
    return this.dates.length > 0 && Object.keys(this.cellMap).length > 0;
  }

  get dayLabel(): string {
    const map: Record<number, string> = { 7: '7 dagar', 14: '14 dagar', 30: '30 dagar', 90: '90 dagar' };
    return map[this.days] ?? `${this.days} dagar`;
  }

  // Visa bara var femte datum-etikett sa det inte klumpar ihop sig
  shouldShowDateLabel(index: number): boolean {
    if (this.dates.length <= 14) return true;
    if (this.dates.length <= 31) return index % 3 === 0;
    return index % 7 === 0;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
