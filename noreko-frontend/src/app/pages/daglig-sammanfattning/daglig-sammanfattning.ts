import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';

import {
  DagligSammanfattningService,
  DailySummaryData,
  ComparisonData,
} from '../../services/daglig-sammanfattning.service';
import { localToday } from '../../utils/date-utils';

@Component({
  standalone: true,
  selector: 'app-daglig-sammanfattning',
  templateUrl: './daglig-sammanfattning.html',
  styleUrls: ['./daglig-sammanfattning.css'],
  imports: [CommonModule, FormsModule],
})
export class DagligSammanfattningComponent implements OnInit, OnDestroy {

  // Datumväljare (default: idag)
  selectedDate: string = localToday();

  // Laddningstillstånd
  summaryLoading  = false;
  summaryLoaded   = false;
  summaryError    = false;
  compLoading     = false;
  compLoaded      = false;

  // Data
  summary: DailySummaryData | null = null;
  comp: ComparisonData | null = null;

  // Auto-refresh
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  lastRefreshed: Date | null = null;
  nextRefreshIn  = 60;
  private countdownInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private service: DagligSammanfattningService) {}

  ngOnInit(): void {
    this.loadAll();
    this.startAutoRefresh();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
      this.countdownInterval = null;
    }
  }

  onDateChange(): void {
    this.summaryLoaded = false;
    this.compLoaded    = false;
    this.summary = null;
    this.comp    = null;
    this.loadAll();
  }

  setToday(): void {
    this.selectedDate = localToday();
    this.onDateChange();
  }

  private loadAll(): void {
    this.loadSummary();
    this.loadComparison();
  }

  private loadSummary(): void {
    if (this.summaryLoading) return;
    this.summaryLoading = true;
    this.summaryError   = false;

    this.service.getDailySummary(this.selectedDate)
      .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.summaryLoading = false;
        if (res?.success) {
          this.summary      = res.data;
          this.summaryError = false;
        } else {
          this.summary      = null;
          this.summaryError = true;
        }
        this.summaryLoaded  = true;
        this.lastRefreshed  = new Date();
      });
  }

  private loadComparison(): void {
    if (this.compLoading) return;
    this.compLoading = true;

    this.service.getComparison(this.selectedDate)
      .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.compLoading = false;
        this.comp        = res?.success ? res.data : null;
        this.compLoaded  = true;
      });
  }

  private startAutoRefresh(): void {
    this.nextRefreshIn = 60;

    this.refreshInterval = setInterval(() => {
      this.loadAll();
      this.nextRefreshIn = 60;
    }, 60000);

    this.countdownInterval = setInterval(() => {
      if (this.nextRefreshIn > 0) {
        this.nextRefreshIn--;
      }
    }, 1000);
  }

  // ----------------------------------------------------------------
  // Hjälpmetoder för template
  // ----------------------------------------------------------------

  /** OEE-Bootstrap-klassen */
  getOeeClass(): string {
    const pct = this.summary?.oee?.oee_pct ?? 0;
    if (pct >= 85) return 'oee-world-class';
    if (pct >= 60) return 'oee-bra';
    if (pct >= 40) return 'oee-ok';
    return 'oee-lag';
  }

  getOeeBgColor(): string {
    const color = this.summary?.oee?.color ?? 'danger';
    const map: Record<string, string> = {
      success: '#38a169',
      info:    '#3182ce',
      warning: '#d69e2e',
      danger:  '#e53e3e',
    };
    return map[color] ?? '#e53e3e';
  }

  /** Trendikon */
  getTrendIkon(trend: 'up' | 'down' | 'flat'): string {
    if (trend === 'up')   return 'fas fa-arrow-trend-up text-success';
    if (trend === 'down') return 'fas fa-arrow-trend-down text-danger';
    return 'fas fa-minus text-muted';
  }

  getTrendText(trend: 'up' | 'down' | 'flat', diffPct: number): string {
    const abs = Math.abs(diffPct).toFixed(1);
    if (trend === 'up')   return `+${abs}% vs forra veckan`;
    if (trend === 'down') return `-${abs}% vs forra veckan`;
    return 'I linje med forra veckan';
  }

  getTrendKlass(trend: 'up' | 'down' | 'flat'): string {
    if (trend === 'up')   return 'text-success';
    if (trend === 'down') return 'text-danger';
    return 'text-muted';
  }

  /** Jämförelseikon */
  getDiffIkon(diff: number): string {
    if (diff > 2)  return 'fas fa-arrow-up text-success';
    if (diff < -2) return 'fas fa-arrow-down text-danger';
    return 'fas fa-minus text-muted';
  }

  getDiffKlass(diff: number): string {
    if (diff > 2)  return 'text-success';
    if (diff < -2) return 'text-danger';
    return 'text-muted';
  }

  getDiffText(diff: number): string {
    if (diff > 0) return `+${diff.toFixed(1)}%`;
    return `${diff.toFixed(1)}%`;
  }

  /** OEE-faktor färg */
  getFaktorKlass(pct: number): string {
    if (pct >= 85) return 'text-success';
    if (pct >= 60) return 'text-info';
    if (pct >= 40) return 'text-warning';
    return 'text-danger';
  }

  /** Stopptid formatering */
  formatMinuter(min: number): string {
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  /** Plats-medalj */
  getPlatsBadge(plats: number): string {
    if (plats === 1) return 'badge-guld';
    if (plats === 2) return 'badge-silver';
    if (plats === 3) return 'badge-brons';
    return 'badge-rank';
  }

  getPlatsIkon(plats: number): string {
    if (plats === 1) return 'fas fa-trophy text-warning';
    if (plats === 2) return 'fas fa-medal';
    if (plats === 3) return 'fas fa-award text-warning';
    return 'fas fa-user';
  }

  /** Kvalitetsfärgsklass */
  getKvalitetKlass(pct: number): string {
    if (pct >= 98) return 'text-success';
    if (pct >= 90) return 'text-warning';
    return 'text-danger';
  }

  /** IBC/h färg */
  getIbcPerHKlass(ibcPerH: number, mal: number): string {
    if (ibcPerH >= mal)        return 'text-success';
    if (ibcPerH >= mal * 0.7)  return 'text-warning';
    return 'text-danger';
  }

  /** Status-ikon baserat på OEE */
  getStatusIkon(): string {
    const pct = this.summary?.oee?.oee_pct ?? 0;
    if (pct >= 85) return 'fas fa-check-circle text-success';
    if (pct >= 60) return 'fas fa-info-circle text-info';
    if (pct >= 40) return 'fas fa-exclamation-circle text-warning';
    return 'fas fa-times-circle text-danger';
  }

  isToday(): boolean {
    return this.selectedDate === localToday();
  }

  getFormattedDate(): string {
    const d = new Date(this.selectedDate + 'T00:00:00');
    return d.toLocaleDateString('sv-SE', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
