import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  TvattlinjeOperatorService,
  TvattOpRank,
  TvattOpSammanfattning,
  TvattOpPoangFordelning,
} from '../../services/tvattlinje-operator.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-tvattlinje-operator-ranking',
  templateUrl: './tvattlinje-operator-ranking.html',
  styleUrls: ['./tvattlinje-operator-ranking.css'],
  imports: [CommonModule],
})
export class TvattlinjeOperatorRankingPage implements OnInit, OnDestroy {

  period = '30d';
  readonly periodOptions = [
    { value: 'idag',  label: 'Idag' },
    { value: 'vecka', label: 'Denna vecka' },
    { value: 'manad', label: 'Denna månad' },
    { value: '30d',   label: '30 dagar' },
  ];

  loadingSammanfattning = false;
  loadingTopplista      = false;
  loadingRanking        = false;
  loadingChart          = false;

  errorSammanfattning = false;
  errorTopplista      = false;
  errorRanking        = false;
  errorChart          = false;

  sammanfattning: TvattOpSammanfattning | null = null;
  topplista: TvattOpRank[] = [];
  ranking: TvattOpRank[]   = [];
  poang: TvattOpPoangFordelning | null = null;

  private poangChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: TvattlinjeOperatorService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyChart();
    if (this.refreshTimer) clearInterval(this.refreshTimer);
    if (this.chartTimer)   clearTimeout(this.chartTimer);
  }

  setPeriod(value: string): void {
    this.period = value;
    this.loadAll();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadSammanfattning();
    this.loadTopplista();
    this.loadRanking();
    this.loadPoang();
  }

  getInitials(namn: string): string {
    if (!namn) return '?';
    const parts = namn.trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[1][0]).toUpperCase()
      : namn.substring(0, 2).toUpperCase();
  }

  getMedalColor(rank: number): string {
    if (rank === 1) return '#FFD700';
    if (rank === 2) return '#C0C0C0';
    if (rank === 3) return '#CD7F32';
    return '#4a5568';
  }

  getMedalBg(rank: number): string {
    if (rank === 1) return 'rgba(255,215,0,0.15)';
    if (rank === 2) return 'rgba(192,192,192,0.15)';
    if (rank === 3) return 'rgba(205,127,50,0.15)';
    return 'rgba(74,85,104,0.15)';
  }

  trackByIndex(index: number): number { return index; }

  private loadSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning = false;
    this.svc.getSammanfattning(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSammanfattning = false;
        this.isFetching = false;
        if (res?.success) this.sammanfattning = res.data;
        else this.errorSammanfattning = true;
      });
  }

  private loadTopplista(): void {
    this.loadingTopplista = true;
    this.errorTopplista = false;
    this.svc.getTopplista(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTopplista = false;
        if (res?.success) this.topplista = res.data || [];
        else this.errorTopplista = true;
      });
  }

  private loadRanking(): void {
    this.loadingRanking = true;
    this.errorRanking = false;
    this.svc.getRanking(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingRanking = false;
        if (res?.success) this.ranking = res.data || [];
        else this.errorRanking = true;
      });
  }

  private loadPoang(): void {
    this.loadingChart = true;
    this.errorChart = false;
    this.svc.getPoangfordelning(this.period)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingChart = false;
        if (res?.success) {
          this.poang = res.data;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildChart(); }, 120);
        } else {
          this.errorChart = true;
        }
      });
  }

  private destroyChart(): void {
    try { this.poangChart?.destroy(); } catch (_) {}
    this.poangChart = null;
  }

  private buildChart(): void {
    this.destroyChart();
    const canvas = document.getElementById('tvattOpChart') as HTMLCanvasElement;
    if (!canvas || !this.poang?.labels?.length) return;

    this.poangChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this.poang.labels,
        datasets: [{
          label: 'IBC Producerade',
          data: this.poang.values,
          backgroundColor: this.poang.labels.map((_, i) =>
            i === 0 ? 'rgba(255,215,0,0.7)' : i === 1 ? 'rgba(192,192,192,0.7)' : i === 2 ? 'rgba(205,127,50,0.7)' : 'rgba(66,153,225,0.6)'
          ),
          borderColor: this.poang.labels.map((_, i) =>
            i === 0 ? '#FFD700' : i === 1 ? '#C0C0C0' : i === 2 ? '#CD7F32' : '#4299e1'
          ),
          borderWidth: 2,
          borderRadius: 4,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: { label: ctx => ` ${ctx.parsed.x} IBC` },
          },
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(74,85,104,0.3)' } },
          y: { ticks: { color: '#e2e8f0', font: { size: 12 } }, grid: { color: 'rgba(74,85,104,0.3)' } },
        },
      },
    });
  }
}
