import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  FeedbackAnalysService,
  FeedbackStatsData,
  FeedbackTrendData,
  TrendPunkt,
  OperatorSentimentItem,
  FeedbackListData,
} from '../../services/feedback-analys.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-feedback-analys',
  templateUrl: './feedback-analys.html',
  styleUrls: ['./feedback-analys.css'],
  imports: [CommonModule, FormsModule],
})
export class FeedbackAnalysComponent implements OnInit, OnDestroy, AfterViewInit {

  // Periodselektor
  days: number = 30;
  readonly periodOptions = [7, 14, 30, 90];

  // Filter för detaljlistan
  filterOperatorId: number | null = null;
  currentPage: number = 1;
  readonly perPage = 20;

  // Laddningstillstånd
  statsLoading     = false;
  statsLoaded      = false;
  trendLoading     = false;
  trendLoaded      = false;
  sentimentLoading = false;
  sentimentLoaded  = false;
  listLoading      = false;
  listLoaded       = false;

  // Data
  statsData:     FeedbackStatsData       | null = null;
  trendData:     FeedbackTrendData       | null = null;
  sentimentData: OperatorSentimentItem[] | null = null;
  listData:      FeedbackListData        | null = null;

  // Chart
  private trendChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private viewReady = false;
  private trendChartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: FeedbackAnalysService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (this.trendLoaded && this.trendData) {
      if (this.trendChartTimer) clearTimeout(this.trendChartTimer);
      this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 50);
    }
  }

  ngOnDestroy(): void {
    if (this.trendChartTimer) { clearTimeout(this.trendChartTimer); this.trendChartTimer = null; }
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    this.trendChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  onDaysChange(): void {
    this.statsLoaded     = false;
    this.trendLoaded     = false;
    this.sentimentLoaded = false;
    this.listLoaded      = false;
    this.currentPage     = 1;
    this.filterOperatorId = null;
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    this.trendChart = null;
    this.loadAll();
  }

  private loadAll(): void {
    this.loadStats();
    this.loadTrend();
    this.loadSentiment();
    this.loadList();
  }

  // ----------------------------------------------------------------
  // Laddning
  // ----------------------------------------------------------------

  private loadStats(): void {
    if (this.statsLoading) return;
    this.statsLoading = true;
    this.service.getFeedbackStats(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.statsLoading = false;
        this.statsData    = res?.success ? res.data : null;
        this.statsLoaded  = true;
      });
  }

  private loadTrend(): void {
    if (this.trendLoading) return;
    this.trendLoading = true;
    this.service.getFeedbackTrend(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.trendLoading = false;
        this.trendData    = res?.success ? res.data : null;
        this.trendLoaded  = true;
        if (this.viewReady && this.trendData) {
          if (this.trendChartTimer) clearTimeout(this.trendChartTimer);
          this.trendChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderTrendChart(); }, 50);
        }
      });
  }

  private loadSentiment(): void {
    if (this.sentimentLoading) return;
    this.sentimentLoading = true;
    this.service.getOperatorSentiment(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.sentimentLoading = false;
        this.sentimentData    = res?.success ? res.data.operatorer : null;
        this.sentimentLoaded  = true;
      });
  }

  loadList(page: number = 1): void {
    if (this.listLoading) return;
    this.listLoading  = true;
    this.currentPage  = page;
    this.service.getFeedbackList({
      days:        this.days,
      page:        this.currentPage,
      per_page:    this.perPage,
      operator_id: this.filterOperatorId,
    }).pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.listLoading = false;
        this.listData    = res?.success ? res.data : null;
        this.listLoaded  = true;
      });
  }

  onFilterChange(): void {
    this.listLoaded = false;
    this.loadList(1);
  }

  onPageChange(page: number): void {
    if (!this.listData) return;
    if (page < 1 || page > this.listData.pages) return;
    this.loadList(page);
  }

  // ----------------------------------------------------------------
  // Chart: Stämningstrend per vecka
  // ----------------------------------------------------------------

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('feedbackTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData || !this.trendData.trend.length) return;

    const labels      = this.trendData.trend.map((p: TrendPunkt) => this.formatVecka(p.vecka_start));
    const stamningar  = this.trendData.trend.map((p: TrendPunkt) => p.snitt_stamning);
    const avgLine     = this.trendData.trend.map(() => this.trendData!.avg_total ?? 0);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Genomsnittlig stämning',
            data: stamningar,
            borderColor: 'rgba(66, 153, 225, 0.9)',
            backgroundColor: 'rgba(66, 153, 225, 0.12)',
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: stamningar.map(v => this.getStamningColor(v)),
          },
          {
            label: 'Genomsnitt totalt',
            data: avgLine,
            borderColor: 'rgba(237, 137, 54, 0.6)',
            backgroundColor: 'transparent',
            fill: false,
            borderDash: [6, 4],
            pointRadius: 0,
            tension: 0,
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
                const v = item.raw as number;
                return `${item.dataset.label}: ${v.toFixed(2)} (${this.stamningTextFromSnitt(v)})`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 }, maxRotation: 45 },
            grid:  { color: 'rgba(255,255,255,0.04)' },
          },
          y: {
            min: 1,
            max: 4,
            ticks: {
              color: '#a0aec0',
              stepSize: 1,
              callback: (v: any) => ['', 'Dålig', 'Ok', 'Bra', 'Utmärkt'][Math.round(v)] ?? v,
            },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Stämning (1–4)', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ----------------------------------------------------------------
  // Hjälpmetoder
  // ----------------------------------------------------------------

  getStamningColor(snitt: number): string {
    if (snitt >= 3.5) return 'rgba(56, 178, 172, 0.9)';  // teal — utmärkt
    if (snitt >= 2.5) return 'rgba(72, 187, 120, 0.9)';  // grön — bra
    if (snitt >= 1.5) return 'rgba(237, 137, 54, 0.9)';  // gul  — ok
    return 'rgba(245, 101, 101, 0.9)';                    // röd  — dålig
  }

  getStamningColorFromInt(stamning: number): string {
    const colors = ['', 'rgba(245,101,101,0.9)', 'rgba(237,137,54,0.9)', 'rgba(72,187,120,0.9)', 'rgba(56,178,172,0.9)'];
    return colors[stamning] ?? '#718096';
  }

  getSentimentBadgeClass(color: string): string {
    const map: Record<string, string> = { green: 'badge-green', yellow: 'badge-yellow', red: 'badge-red' };
    return map[color] ?? 'badge-secondary';
  }

  getTrendIcon(trend: string): string {
    if (trend === 'bättre') return 'fas fa-arrow-up text-success';
    if (trend === 'sämre')  return 'fas fa-arrow-down text-danger';
    return 'fas fa-minus text-muted';
  }

  getTrendText(trend: string): string {
    if (trend === 'bättre') return 'Förbättring';
    if (trend === 'sämre')  return 'Försämring';
    return 'Stabil';
  }

  stamningTextFromSnitt(snitt: number): string {
    if (snitt >= 3.5) return 'Utmärkt';
    if (snitt >= 2.5) return 'Bra';
    if (snitt >= 1.5) return 'Ok';
    return 'Dålig';
  }

  stamningEmoji(stamning: number): string {
    return ['', '😔', '😐', '😊', '😄'][stamning] ?? '?';
  }

  formatDatum(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum + 'T00:00:00');
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatVecka(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum + 'T00:00:00');
    return 'v.' + this.getWeekNumber(d) + ' ' + d.getFullYear();
  }

  private getWeekNumber(d: Date): number {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
  }

  getPagesArray(): number[] {
    if (!this.listData) return [];
    const total = this.listData.pages;
    const curr  = this.listData.page;
    const result: number[] = [];
    const start = Math.max(1, curr - 2);
    const end   = Math.min(total, curr + 2);
    for (let i = start; i <= end; i++) result.push(i);
    return result;
  }

  getFordelningPct(key: string): number {
    if (!this.statsData || !this.statsData.total) return 0;
    return Math.round(((this.statsData.fordelning[key] ?? 0) / this.statsData.total) * 100);
  }

  getFordelningWidth(key: string): string {
    return this.getFordelningPct(key) + '%';
  }

  snittStarDisplay(snitt: number | null): string {
    if (snitt === null) return '–';
    return snitt.toFixed(2) + ' / 4';
  }
  trackByIndex(index: number): number { return index; }
}
