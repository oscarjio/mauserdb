import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  RankingHistorikService,
  WeeklyRankingsData,
  OperatorTrend,
  RankingChangesData,
  RankingAndring,
  StreakData,
  StreakItem,
} from '../../services/ranking-historik.service';

Chart.register(...registerables);

// Färger för upp till 12 operatörer i trendiagram
const CHART_COLORS = [
  'rgba(66, 153, 225, 0.9)',
  'rgba(72, 187, 120, 0.9)',
  'rgba(237, 137, 54, 0.9)',
  'rgba(245, 101, 101, 0.9)',
  'rgba(159, 122, 234, 0.9)',
  'rgba(56, 178, 172, 0.9)',
  'rgba(246, 173, 85, 0.9)',
  'rgba(252, 129, 74, 0.9)',
  'rgba(129, 230, 217, 0.9)',
  'rgba(198, 246, 213, 0.9)',
  'rgba(254, 215, 226, 0.9)',
  'rgba(190, 227, 248, 0.9)',
];

@Component({
  standalone: true,
  selector: 'app-ranking-historik',
  templateUrl: './ranking-historik.html',
  styleUrls: ['./ranking-historik.css'],
  imports: [CommonModule, FormsModule, HttpClientModule],
})
export class RankingHistorikComponent implements OnInit, OnDestroy, AfterViewInit {
  // Periodselektor
  weeks: number = 12;
  readonly periodOptions = [4, 8, 12];

  // Laddningstillstånd
  rankingsLoading = false;
  rankingsLoaded  = false;
  changesLoading  = false;
  changesLoaded   = false;
  streakLoading   = false;
  streakLoaded    = false;

  // Data
  rankingsData: WeeklyRankingsData | null = null;
  changesData:  RankingChangesData | null = null;
  streakData:   StreakData         | null = null;

  // Head-to-head
  h2hOp1Id: number | null = null;
  h2hOp2Id: number | null = null;

  // Charts
  private trendChart: Chart | null = null;
  private h2hChart:   Chart | null = null;

  private destroy$ = new Subject<void>();
  private viewReady = false;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: RankingHistorikService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (this.rankingsLoaded && this.rankingsData) {
      if (this.chartTimer) clearTimeout(this.chartTimer);
      this.chartTimer = setTimeout(() => this.renderTrendChart(), 50);
    }
  }

  ngOnDestroy(): void {
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    try { this.h2hChart?.destroy();   } catch (_e) { /* ignore */ }
    this.trendChart = null;
    this.h2hChart   = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  onWeeksChange(): void {
    this.rankingsLoaded = false;
    this.changesLoaded  = false;
    this.streakLoaded   = false;
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    try { this.h2hChart?.destroy();   } catch (_e) { /* ignore */ }
    this.trendChart = null;
    this.h2hChart   = null;
    this.loadAll();
  }

  private loadAll(): void {
    this.loadRankings();
    this.loadChanges();
    this.loadStreaks();
  }

  // ----------------------------------------------------------------
  // Dataladdning
  // ----------------------------------------------------------------

  private loadRankings(): void {
    if (this.rankingsLoading) return;
    this.rankingsLoading = true;
    this.service.getWeeklyRankings(this.weeks)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.rankingsLoading = false;
        this.rankingsData = res?.success ? res.data : null;
        this.rankingsLoaded = true;
        if (this.viewReady && this.rankingsData) {
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => this.renderTrendChart(), 50);
        }
      });
  }

  private loadChanges(): void {
    if (this.changesLoading) return;
    this.changesLoading = true;
    this.service.getRankingChanges()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.changesLoading = false;
        this.changesData = res?.success ? res.data : null;
        this.changesLoaded = true;
      });
  }

  private loadStreaks(): void {
    if (this.streakLoading) return;
    this.streakLoading = true;
    this.service.getStreakData(this.weeks)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.streakLoading = false;
        this.streakData = res?.success ? res.data : null;
        this.streakLoaded = true;
      });
  }

  // ----------------------------------------------------------------
  // Chart: Rankningstrend-linjediagram (inverterad y-axel)
  // ----------------------------------------------------------------

  private renderTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('rankingTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.rankingsData) return;

    const labels = this.rankingsData.veckor;
    const datasets = this.rankingsData.op_trender.map((op, i) => {
      const color = CHART_COLORS[i % CHART_COLORS.length];
      return {
        label: op.operator_namn,
        data: op.trend.map(t => t.rank),
        borderColor: color,
        backgroundColor: color.replace('0.9)', '0.1)'),
        tension: 0.3,
        pointRadius: 4,
        pointHoverRadius: 7,
        fill: false,
        spanGaps: true,
      };
    });

    // Räkna max rank (antal aktiva operatörer)
    const maxRank = Math.max(...this.rankingsData.op_trender.map(o =>
      Math.max(...o.trend.map(t => t.rank ?? 0))
    ), 5);

    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 12 }, boxWidth: 14, padding: 14 },
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              label: (item: any) => {
                const rank = item.raw;
                return rank !== null ? `${item.dataset.label}: Plats ${rank}` : `${item.dataset.label}: —`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: 'rgba(255,255,255,0.04)' },
          },
          y: {
            reverse: true, // #1 högst = inverterad axel
            min: 0.5,
            max: maxRank + 0.5,
            ticks: {
              color: '#a0aec0',
              stepSize: 1,
              callback: (v: any) => Number.isInteger(v) ? `#${v}` : '',
            },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Placering (#1 = bäst)', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ----------------------------------------------------------------
  // Chart: Head-to-head
  // ----------------------------------------------------------------

  renderH2HChart(): void {
    try { this.h2hChart?.destroy(); } catch (_e) { /* ignore */ }
    const canvas = document.getElementById('h2hChart') as HTMLCanvasElement;
    if (!canvas || !this.rankingsData) return;
    if (this.h2hOp1Id === null || this.h2hOp2Id === null) return;

    const op1 = this.rankingsData.op_trender.find(o => o.operator_id === this.h2hOp1Id);
    const op2 = this.rankingsData.op_trender.find(o => o.operator_id === this.h2hOp2Id);
    if (!op1 || !op2) return;

    const labels = this.rankingsData.veckor;

    const maxRank = Math.max(
      ...op1.trend.map(t => t.rank ?? 0),
      ...op2.trend.map(t => t.rank ?? 0),
      5
    );

    this.h2hChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: op1.operator_namn,
            data: op1.trend.map(t => t.rank),
            borderColor: 'rgba(66, 153, 225, 0.9)',
            backgroundColor: 'rgba(66, 153, 225, 0.1)',
            tension: 0.3,
            pointRadius: 5,
            pointHoverRadius: 8,
            fill: false,
            spanGaps: true,
          },
          {
            label: op2.operator_namn,
            data: op2.trend.map(t => t.rank),
            borderColor: 'rgba(245, 101, 101, 0.9)',
            backgroundColor: 'rgba(245, 101, 101, 0.1)',
            tension: 0.3,
            pointRadius: 5,
            pointHoverRadius: 8,
            fill: false,
            spanGaps: true,
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
            labels: { color: '#a0aec0', font: { size: 13 }, boxWidth: 16, padding: 16 },
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              label: (item: any) => {
                const rank = item.raw;
                return rank !== null ? `${item.dataset.label}: Plats ${rank}` : `${item.dataset.label}: —`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: 'rgba(255,255,255,0.04)' },
          },
          y: {
            reverse: true,
            min: 0.5,
            max: maxRank + 0.5,
            ticks: {
              color: '#a0aec0',
              stepSize: 1,
              callback: (v: any) => Number.isInteger(v) ? `#${v}` : '',
            },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Placering (#1 = bäst)', color: '#a0aec0' },
          },
        },
      },
    });
  }

  onH2HChange(): void {
    if (this.h2hOp1Id !== null && this.h2hOp2Id !== null) {
      if (this.chartTimer) clearTimeout(this.chartTimer);
      this.chartTimer = setTimeout(() => this.renderH2HChart(), 50);
    }
  }

  // ----------------------------------------------------------------
  // Hjälpmetoder för template
  // ----------------------------------------------------------------

  /** Sammanfattning: Veckans #1 */
  getVeckansEtta(): string {
    if (!this.changesData) return '—';
    const etta = this.changesData.andringar.find(a => a.rank_nu === 1);
    return etta ? etta.operator_namn : '—';
  }

  getVeckansEttaIbc(): number {
    if (!this.changesData) return 0;
    const etta = this.changesData.andringar.find(a => a.rank_nu === 1);
    return etta ? etta.ibc_nu : 0;
  }

  /** Största klättrare (störst positiv andring) */
  getStorstKlattare(): string {
    if (!this.changesData) return '—';
    const klattrat = this.changesData.andringar.filter(a => a.andring !== null && a.andring > 0);
    if (!klattrat.length) return '—';
    klattrat.sort((a, b) => (b.andring ?? 0) - (a.andring ?? 0));
    return klattrat[0].operator_namn;
  }

  getStorstKlattareAndring(): number {
    if (!this.changesData) return 0;
    const klattrat = this.changesData.andringar.filter(a => a.andring !== null && a.andring > 0);
    if (!klattrat.length) return 0;
    klattrat.sort((a, b) => (b.andring ?? 0) - (a.andring ?? 0));
    return klattrat[0].andring ?? 0;
  }

  /** Längsta positiva streak */
  getLangstaPosStreak(): number {
    return this.streakData?.langsta_pos_streak ?? 0;
  }

  getLangstaPosStreakOp(): string {
    if (!this.streakData) return '—';
    const sorted = [...this.streakData.streaks].sort((a, b) => b.positiv_streak - a.positiv_streak);
    return sorted.length > 0 && sorted[0].positiv_streak > 0 ? sorted[0].operator_namn : '—';
  }

  /** Mest konsekvent operatör */
  getMestKonsekvent(): string {
    return this.streakData?.mest_konsekvent ?? '—';
  }

  /** Ändringsklass: grön om klättrat, röd om fallit */
  getAndringKlass(andring: number | null): string {
    if (andring === null) return 'text-muted';
    if (andring > 0) return 'text-success';
    if (andring < 0) return 'text-danger';
    return 'text-muted';
  }

  getAndringIkon(andring: number | null): string {
    if (andring === null) return 'fas fa-minus text-muted';
    if (andring > 0) return 'fas fa-arrow-up text-success';
    if (andring < 0) return 'fas fa-arrow-down text-danger';
    return 'fas fa-minus text-muted';
  }

  getAndringText(andring: number | null): string {
    if (andring === null) return '—';
    if (andring > 0) return `+${andring}`;
    if (andring < 0) return `${andring}`;
    return '=';
  }

  /** Klättrarbadge (2+ veckor i rad uppåt) */
  harKlattrarBadge(operatorId: number): boolean {
    if (!this.streakData) return false;
    const s = this.streakData.streaks.find(x => x.operator_id === operatorId);
    return s ? s.positiv_streak >= 2 : false;
  }

  getPosStreak(operatorId: number): number {
    if (!this.streakData) return 0;
    const s = this.streakData.streaks.find(x => x.operator_id === operatorId);
    return s ? s.positiv_streak : 0;
  }

  /** Rankningsplats-badge */
  getRankBadgeKlass(rank: number | null): string {
    if (rank === null) return 'badge-secondary';
    if (rank === 1) return 'badge-guld';
    if (rank === 2) return 'badge-silver';
    if (rank === 3) return 'badge-brons';
    return 'badge-rank';
  }

  /** Alla operatörer för head-to-head-dropdown */
  getAllaOperatorer(): OperatorTrend[] {
    return this.rankingsData?.op_trender ?? [];
  }

  /** Vecka-etikett för operatör */
  getVeckaEtikett(): string {
    if (!this.changesData?.andringar?.length) return '';
    return this.changesData.andringar[0].vecka_nu;
  }

  getForegVeckaEtikett(): string {
    if (!this.changesData?.andringar?.length) return '';
    return this.changesData.andringar[0].vecka_foreg;
  }
  trackByIndex(index: number): number { return index; }
}
