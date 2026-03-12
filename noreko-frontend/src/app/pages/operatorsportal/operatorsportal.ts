import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  OperatorsportalService,
  MyStatsData,
  MyTrendData,
  MyBonusData,
} from '../../services/operatorsportal.service';

Chart.register(...registerables);

@Component({
  selector: 'app-operatorsportal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './operatorsportal.html',
  styleUrls: ['./operatorsportal.css'],
})
export class OperatorsportalPage implements OnInit, OnDestroy {
  @ViewChild('trendChart') trendChartRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private chart: Chart | null = null;
  private chartTimer: any = null;

  // Data
  stats: MyStatsData | null = null;
  trend: MyTrendData | null = null;
  bonus: MyBonusData | null = null;

  // UI state
  loadingStats = true;
  loadingTrend = true;
  loadingBonus = true;
  errorStats = '';
  errorTrend = '';
  errorBonus = '';

  trendDays = 30;

  constructor(private service: OperatorsportalService) {}

  ngOnInit(): void {
    this.loadStats();
    this.loadTrend();
    this.loadBonus();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) clearTimeout(this.chartTimer);
    this.chart?.destroy();
    this.chart = null;
  }

  // ---- Dataladdning ----

  loadStats(): void {
    this.loadingStats = true;
    this.errorStats = '';
    this.service.getMyStats().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingStats = false;
      if (res?.success && res.data) {
        this.stats = res.data;
      } else {
        this.errorStats = 'Kunde inte ladda din statistik. Kontrollera att du är inloggad och kopplad till ett operatörskonto.';
      }
    });
  }

  loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = '';
    this.service.getMyTrend(this.trendDays).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTrend = false;
      if (res?.success && res.data) {
        this.trend = res.data;
        this.chartTimer = setTimeout(() => this.buildChart(), 100);
      } else {
        this.errorTrend = 'Kunde inte ladda trenddata.';
      }
    });
  }

  loadBonus(): void {
    this.loadingBonus = true;
    this.errorBonus = '';
    this.service.getMyBonus().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingBonus = false;
      if (res?.success && res.data) {
        this.bonus = res.data;
      } else {
        this.errorBonus = 'Kunde inte ladda bonusdata.';
      }
    });
  }

  onDaysChange(days: number): void {
    this.trendDays = days;
    this.loadTrend();
  }

  // ---- Chart.js ----

  private buildChart(): void {
    if (!this.trendChartRef || !this.trend) return;

    this.chart?.destroy();
    this.chart = null;

    const ctx = this.trendChartRef.nativeElement.getContext('2d');
    if (!ctx) return;

    const labels = this.trend.labels.map(d => {
      const parts = d.split('-');
      return `${parts[2]}/${parts[1]}`;
    });

    this.chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Min IBC/dag',
            data: this.trend.my_ibc,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.12)',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,
            fill: true,
          },
          {
            label: 'Teamsnitt IBC/dag',
            data: this.trend.team_snitt,
            borderColor: '#68d391',
            backgroundColor: 'rgba(104,211,145,0.08)',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 2,
            pointHoverRadius: 4,
            tension: 0.3,
            fill: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: '#a0aec0',
              maxTicksLimit: 10,
              font: { size: 11 },
            },
            grid: { color: 'rgba(74,85,104,0.3)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
            beginAtZero: true,
            title: {
              display: true,
              text: 'IBC',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ---- Hjälpfunktioner ----

  get halsningText(): string {
    const h = new Date().getHours();
    if (h < 10) return 'God morgon';
    if (h < 13) return 'God förmiddag';
    if (h < 17) return 'God eftermiddag';
    return 'God kväll';
  }

  diffClass(myVal: number, teamVal: number): string {
    if (myVal > teamVal) return 'text-success';
    if (myVal < teamVal) return 'text-danger';
    return 'text-muted';
  }

  diffText(myVal: number, teamVal: number): string {
    const diff = myVal - teamVal;
    if (diff > 0) return `+${diff.toFixed(1)} vs snitt`;
    if (diff < 0) return `${diff.toFixed(1)} vs snitt`;
    return 'Lika med snitt';
  }

  diffArrow(myVal: number, teamVal: number): string {
    if (myVal > teamVal) return '\u25B2';
    if (myVal < teamVal) return '\u25BC';
    return '\u2014';
  }

  bonusBarWidth(pct: number): string {
    return `${Math.max(0, Math.min(100, pct))}%`;
  }

  bonusBarClass(pct: number): string {
    if (pct >= 80) return 'bar-green';
    if (pct >= 50) return 'bar-yellow';
    return 'bar-red';
  }

  formatDate(d: string | null): string {
    if (!d) return '-';
    const parts = d.split(' ');
    if (parts.length >= 1) {
      const dateParts = parts[0].split('-');
      if (dateParts.length === 3) {
        return `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
      }
    }
    return d;
  }
}
