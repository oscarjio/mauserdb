import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BonusService } from '../../services/bonus.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface DailySummary {
  date: string;
  total_cycles: number;
  shifts_today: number;
  total_ibc_ok: number;
  total_ibc_ej_ok: number;
  avg_bonus: number;
  max_bonus: number;
}

interface RankingEntry {
  rank: number;
  operator_id: number;
  cycles: number;
  bonus_avg: number;
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
  total_ibc_ok: number;
  total_hours: number;
  position?: string;
}

@Component({
  standalone: true,
  selector: 'app-bonus-dashboard',
  templateUrl: './bonus-dashboard.html',
  styleUrl: './bonus-dashboard.css',
  imports: [CommonModule, FormsModule]
})
export class BonusDashboardPage implements OnInit {
  // State signals
  loading = signal(false);
  error = signal<string | null>(null);

  // Data signals
  dailySummary = signal<DailySummary | null>(null);
  topOperators = signal<RankingEntry[]>([]);
  positionRankings = signal<{ [key: string]: RankingEntry[] }>({});

  // Filter state
  selectedPeriod = signal<string>('week');
  selectedPosition = signal<string>('all');
  searchOperatorId = signal<string>('');

  // Charts
  private kpiChart: Chart | null = null;
  private trendChart: Chart | null = null;

  constructor(private bonusService: BonusService) {}

  ngOnInit() {
    this.loadDashboardData();
  }

  loadDashboardData() {
    this.loading.set(true);
    this.error.set(null);

    // Load daily summary
    this.loadDailySummary();

    // Load rankings
    this.loadRankings();
  }

  loadDailySummary() {
    // Mock for now - will be implemented when API endpoint exists
    this.dailySummary.set({
      date: new Date().toISOString().split('T')[0],
      total_cycles: 0,
      shifts_today: 0,
      total_ibc_ok: 0,
      total_ibc_ej_ok: 0,
      avg_bonus: 0,
      max_bonus: 0
    });
  }

  loadRankings() {
    const period = this.selectedPeriod();

    this.bonusService.getRanking(
      this.getStartDate(period),
      new Date().toISOString().split('T')[0],
      this.selectedPosition() !== 'all' ? this.selectedPosition() : undefined,
      undefined,
      10
    ).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.topOperators.set(response.data.ranking);
          this.loading.set(false);
        } else {
          this.error.set(response.error || 'Failed to load rankings');
          this.loading.set(false);
        }
      },
      error: (err) => {
        this.error.set('Network error: ' + err.message);
        this.loading.set(false);
      }
    });
  }

  loadOperatorDetails(operatorId: string) {
    if (!operatorId || !operatorId.trim()) {
      this.error.set('Ange ett operatör-ID');
      return;
    }

    this.loading.set(true);
    const period = this.selectedPeriod();

    this.bonusService.getOperatorStats(
      operatorId,
      this.getStartDate(period),
      new Date().toISOString().split('T')[0]
    ).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.renderOperatorCharts(response.data);
          this.loading.set(false);
        } else {
          this.error.set(response.error || 'Ingen data hittades för operatör ' + operatorId);
          this.loading.set(false);
        }
      },
      error: (err) => {
        this.error.set('Fel vid hämtning av operatörsdata: ' + err.message);
        this.loading.set(false);
      }
    });
  }

  renderOperatorCharts(data: any) {
    // Destroy existing charts
    if (this.kpiChart) {
      this.kpiChart.destroy();
    }
    if (this.trendChart) {
      this.trendChart.destroy();
    }

    // KPI Chart (Radar/Spider chart)
    const kpiCanvas = document.getElementById('kpiChart') as HTMLCanvasElement;
    if (kpiCanvas) {
      this.kpiChart = new Chart(kpiCanvas, {
        type: 'radar',
        data: {
          labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
          datasets: [{
            label: 'KPI:er',
            data: [
              data.totalStats?.avg_effektivitet || 0,
              Math.min(data.totalStats?.avg_produktivitet || 0, 100),
              data.totalStats?.avg_kvalitet || 0
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgb(54, 162, 235)',
            pointBackgroundColor: 'rgb(54, 162, 235)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgb(54, 162, 235)'
          }]
        },
        options: {
          scales: {
            r: {
              beginAtZero: true,
              max: 100
            }
          }
        }
      });
    }

    // Trend Chart (Line chart)
    const trendCanvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (trendCanvas && data.dailyStats) {
      const dates = data.dailyStats.map((d: any) => d.datum);
      const bonusData = data.dailyStats.map((d: any) => d.avg_bonus);

      this.trendChart = new Chart(trendCanvas, {
        type: 'line',
        data: {
          labels: dates,
          datasets: [{
            label: 'Bonus Poäng',
            data: bonusData,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: true,
              position: 'top'
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 100
            }
          }
        }
      });
    }
  }

  getStartDate(period: string): string {
    const now = new Date();
    switch(period) {
      case 'today':
        return now.toISOString().split('T')[0];
      case 'week':
        now.setDate(now.getDate() - 7);
        return now.toISOString().split('T')[0];
      case 'month':
        now.setDate(now.getDate() - 30);
        return now.toISOString().split('T')[0];
      case 'year':
        now.setDate(now.getDate() - 365);
        return now.toISOString().split('T')[0];
      default:
        now.setDate(now.getDate() - 7);
        return now.toISOString().split('T')[0];
    }
  }

  getBonusColor(bonus: number): string {
    if (bonus >= 80) return 'success';
    if (bonus >= 70) return 'warning';
    return 'danger';
  }

  getKpiColor(kpi: number, type: 'effektivitet' | 'produktivitet' | 'kvalitet'): string {
    if (type === 'effektivitet') {
      if (kpi >= 95) return 'success';
      if (kpi >= 90) return 'warning';
      return 'danger';
    }
    if (type === 'produktivitet') {
      if (kpi >= 15) return 'success';
      if (kpi >= 10) return 'warning';
      return 'danger';
    }
    if (type === 'kvalitet') {
      if (kpi >= 98) return 'success';
      if (kpi >= 95) return 'warning';
      return 'danger';
    }
    return 'secondary';
  }

  onPeriodChange() {
    this.loadRankings();
  }

  onPositionChange() {
    this.loadRankings();
  }

  onSearchOperator() {
    this.loadOperatorDetails(this.searchOperatorId());
  }
}
