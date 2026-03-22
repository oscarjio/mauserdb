import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../utils/date-utils';
import {
  OperatorRankingService,
  SammanfattningData,
  RankingData,
  TopplistaData,
  PoangFordelningData,
  HistorikData,
  MvpData,
} from '../../services/operator-ranking.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-operator-ranking',
  templateUrl: './operator-ranking.component.html',
  styleUrls: ['./operator-ranking.component.css'],
  imports: [CommonModule],
})
export class OperatorRankingPage implements OnInit, OnDestroy {

  // Period
  period = '30d';
  readonly periodOptions = [
    { value: 'idag',  label: 'Idag' },
    { value: 'vecka', label: 'Denna vecka' },
    { value: 'manad', label: 'Denna manad' },
    { value: '30d',   label: '30 dagar' },
  ];

  // MVP toggle
  mvpTyp = 'vecka';

  // Loading states
  loadingSammanfattning = false;
  loadingTopplista = false;
  loadingRanking = false;
  loadingPoangfordelning = false;
  loadingHistorik = false;
  loadingMvp = false;

  // Error states
  errorSammanfattning = false;
  errorTopplista = false;
  errorRanking = false;
  errorPoangfordelning = false;
  errorHistorik = false;
  errorMvp = false;

  // Data
  sammanfattning: SammanfattningData | null = null;
  topplistaData: TopplistaData | null = null;
  rankingData: RankingData | null = null;
  poangfordelningData: PoangFordelningData | null = null;
  historikData: HistorikData | null = null;
  mvpData: MvpData | null = null;

  // Charts
  private poangChart: Chart | null = null;
  private historikChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private poangChartTimer: ReturnType<typeof setTimeout> | null = null;
  private historikChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: OperatorRankingService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    if (this.poangChartTimer) { clearTimeout(this.poangChartTimer); this.poangChartTimer = null; }
    if (this.historikChartTimer) { clearTimeout(this.historikChartTimer); this.historikChartTimer = null; }
  }

  setPeriod(value: string): void {
    this.period = value;
    this.loadAll();
  }

  setMvpTyp(typ: string): void {
    this.mvpTyp = typ;
    this.loadMvp();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadSammanfattning();
    this.loadTopplista();
    this.loadRanking();
    this.loadPoangfordelning();
    this.loadHistorik();
    this.loadMvp();
  }

  // ---- Helpers ----

  getInitials(namn: string): string {
    if (!namn) return '?';
    const parts = namn.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return namn.substring(0, 2).toUpperCase();
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

  getMedalLabel(rank: number): string {
    if (rank === 1) return 'Guld';
    if (rank === 2) return 'Silver';
    if (rank === 3) return 'Brons';
    return '#' + rank;
  }

  getStreakEmoji(streak: number): string {
    if (streak >= 7) return 'fas fa-fire';
    if (streak >= 3) return 'fas fa-bolt';
    if (streak >= 1) return 'fas fa-check';
    return '';
  }

  getStreakColor(streak: number): string {
    if (streak >= 7) return '#fc8181';
    if (streak >= 3) return '#ecc94b';
    if (streak >= 1) return '#68d391';
    return '#718096';
  }

  // ---- Data loading ----

  private loadSammanfattning(): void {
    this.loadingSammanfattning = true;
    this.errorSammanfattning = false;
    this.svc.getSammanfattning(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingSammanfattning = false;
      this.isFetching = false;
      if (res?.success) {
        this.sammanfattning = res.data;
      } else {
        this.errorSammanfattning = true;
      }
    });
  }

  private loadTopplista(): void {
    this.loadingTopplista = true;
    this.errorTopplista = false;
    this.svc.getTopplista(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTopplista = false;
      if (res?.success) {
        this.topplistaData = res.data;
      } else {
        this.errorTopplista = true;
      }
    });
  }

  private loadRanking(): void {
    this.loadingRanking = true;
    this.errorRanking = false;
    this.svc.getRanking(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingRanking = false;
      if (res?.success) {
        this.rankingData = res.data;
      } else {
        this.errorRanking = true;
      }
    });
  }

  private loadPoangfordelning(): void {
    this.loadingPoangfordelning = true;
    this.errorPoangfordelning = false;
    this.svc.getPoangfordelning(this.period).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingPoangfordelning = false;
      if (res?.success) {
        this.poangfordelningData = res.data;
        if (this.poangChartTimer) clearTimeout(this.poangChartTimer);
        this.poangChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildPoangChart(); }, 100);
      } else {
        this.errorPoangfordelning = true;
      }
    });
  }

  private loadHistorik(): void {
    this.loadingHistorik = true;
    this.errorHistorik = false;
    this.svc.getHistorik().pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingHistorik = false;
      if (res?.success) {
        this.historikData = res.data;
        if (this.historikChartTimer) clearTimeout(this.historikChartTimer);
        this.historikChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildHistorikChart(); }, 100);
      } else {
        this.errorHistorik = true;
      }
    });
  }

  private loadMvp(): void {
    this.loadingMvp = true;
    this.errorMvp = false;
    this.svc.getMvp(this.mvpTyp).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingMvp = false;
      if (res?.success) {
        this.mvpData = res.data;
      } else {
        this.errorMvp = true;
      }
    });
  }

  // ---- Chart builders ----

  private destroyCharts(): void {
    try { this.poangChart?.destroy(); } catch (_) {}
    try { this.historikChart?.destroy(); } catch (_) {}
    this.poangChart = null;
    this.historikChart = null;
  }

  private buildPoangChart(): void {
    try { this.poangChart?.destroy(); } catch (_) {}
    this.poangChart = null;

    const canvas = document.getElementById('poangFordelningChart') as HTMLCanvasElement;
    if (!canvas || !this.poangfordelningData?.chart_data?.length) return;

    const items = this.poangfordelningData.chart_data;
    const labels = items.map(i => i.operator_namn);

    if (this.poangChart) { (this.poangChart as any).destroy(); }
    this.poangChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Produktion',
            data: items.map(i => i.produktions_poang),
            backgroundColor: '#4299e1',
          },
          {
            label: 'Kvalitet',
            data: items.map(i => i.kvalitets_bonus),
            backgroundColor: '#68d391',
          },
          {
            label: 'Tempo',
            data: items.map(i => i.tempo_bonus),
            backgroundColor: '#ecc94b',
          },
          {
            label: 'Stopp',
            data: items.map(i => i.stopp_bonus),
            backgroundColor: '#b794f4',
          },
          {
            label: 'Svit',
            data: items.map(i => i.streak_bonus),
            backgroundColor: '#fc8181',
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.x} poang`,
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            stacked: true,
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
        },
      },
    });
  }

  private buildHistorikChart(): void {
    try { this.historikChart?.destroy(); } catch (_) {}
    this.historikChart = null;

    const canvas = document.getElementById('historikChart') as HTMLCanvasElement;
    if (!canvas || !this.historikData?.datasets?.length) return;

    const colors = ['#4fd1c5', '#4299e1', '#ecc94b', '#b794f4', '#fc8181'];

    const labels = this.historikData.dates.map(d => {
      const dt = parseLocalDate(d);
      return dt.toLocaleDateString('sv-SE', { month: 'short', day: 'numeric' });
    });

    const datasets = this.historikData.datasets.map((ds, i) => ({
      label: ds.operator_namn,
      data: ds.data,
      borderColor: colors[i % colors.length],
      backgroundColor: 'transparent',
      tension: 0.3,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2,
    }));

    if (this.historikChart) { (this.historikChart as any).destroy(); }
    this.historikChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} poang`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxTicksLimit: 15, maxRotation: 45 },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            min: 0,
            ticks: { color: '#a0aec0', font: { size: 10 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
        },
      },
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? item?.user_id ?? index; }
}
