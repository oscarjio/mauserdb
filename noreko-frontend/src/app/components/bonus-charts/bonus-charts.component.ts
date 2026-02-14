import { Component, Input, OnInit, AfterViewInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Chart, registerables, ChartConfiguration } from 'chart.js';

Chart.register(...registerables);

interface KPIData {
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
  bonus: number;
}

interface DailyData {
  date: string;
  bonus: number;
  effektivitet: number;
  produktivitet: number;
  kvalitet: number;
}

@Component({
  standalone: true,
  selector: 'app-bonus-charts',
  templateUrl: './bonus-charts.component.html',
  styleUrl: './bonus-charts.component.css',
  imports: [CommonModule]
})
export class BonusChartsComponent implements OnInit, AfterViewInit, OnChanges {
  @Input() dailyData: DailyData[] = [];
  @Input() currentKPIs: KPIData | null = null;
  @Input() teamAverage: number = 0;

  private heatmapChart: Chart | null = null;
  private gaugeCharts: { [key: string]: Chart | null } = {
    effektivitet: null,
    produktivitet: null,
    kvalitet: null
  };
  private trendChart: Chart | null = null;
  private distributionChart: Chart | null = null;

  ngOnInit() {}

  ngAfterViewInit() {
    this.initializeCharts();
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes['dailyData'] || changes['currentKPIs']) {
      setTimeout(() => this.updateAllCharts(), 100);
    }
  }

  private initializeCharts() {
    this.createHeatmap();
    this.createGauges();
    this.createMultiLineTrend();
    this.createDistribution();
  }

  private updateAllCharts() {
    this.updateHeatmap();
    this.updateGauges();
    this.updateMultiLineTrend();
    this.updateDistribution();
  }

  // ============ HEATMAP ============
  private createHeatmap() {
    const canvas = document.getElementById('heatmapChart') as HTMLCanvasElement;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Destroy existing chart
    if (this.heatmapChart) {
      this.heatmapChart.destroy();
    }

    // Create heatmap using matrix data
    const heatmapData = this.prepareHeatmapData();

    this.heatmapChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: heatmapData.labels,
        datasets: [{
          label: 'Bonus per Dag',
          data: heatmapData.values,
          backgroundColor: heatmapData.colors,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: (context) => {
                return `Bonus: ${context.parsed.y.toFixed(1)} poäng`;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: {
              callback: (value) => `${value}`
            }
          }
        }
      }
    });
  }

  private prepareHeatmapData() {
    const labels: string[] = [];
    const values: number[] = [];
    const colors: string[] = [];

    this.dailyData.forEach(day => {
      labels.push(day.date);
      values.push(day.bonus);

      // Color based on bonus value
      if (day.bonus >= 80) {
        colors.push('rgba(75, 192, 192, 0.8)');
      } else if (day.bonus >= 70) {
        colors.push('rgba(255, 206, 86, 0.8)');
      } else {
        colors.push('rgba(255, 99, 132, 0.8)');
      }
    });

    return { labels, values, colors };
  }

  private updateHeatmap() {
    if (!this.heatmapChart) {
      this.createHeatmap();
      return;
    }

    const heatmapData = this.prepareHeatmapData();
    this.heatmapChart.data.labels = heatmapData.labels;
    this.heatmapChart.data.datasets[0].data = heatmapData.values;
    this.heatmapChart.data.datasets[0].backgroundColor = heatmapData.colors;
    this.heatmapChart.update();
  }

  // ============ GAUGE CHARTS ============
  private createGauges() {
    if (!this.currentKPIs) return;

    this.createGauge('effektivitet', this.currentKPIs.effektivitet, 'Effektivitet');
    this.createGauge('produktivitet', this.currentKPIs.produktivitet, 'Produktivitet');
    this.createGauge('kvalitet', this.currentKPIs.kvalitet, 'Kvalitet');
  }

  private createGauge(id: string, value: number, label: string) {
    const canvas = document.getElementById(`gauge_${id}`) as HTMLCanvasElement;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Destroy existing
    if (this.gaugeCharts[id]) {
      this.gaugeCharts[id]?.destroy();
    }

    // Normalize value to 0-100 range
    const normalizedValue = Math.min(Math.max(value, 0), 100);

    this.gaugeCharts[id] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [normalizedValue, 100 - normalizedValue],
          backgroundColor: [
            this.getGaugeColor(normalizedValue),
            'rgba(200, 200, 200, 0.2)'
          ],
          borderWidth: 0,
          circumference: 180,
          rotation: 270
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            enabled: false
          }
        }
      },
      plugins: [{
        id: 'centerText',
        afterDraw: (chart) => {
          const ctx = chart.ctx;
          ctx.save();
          const centerX = chart.width / 2;
          const centerY = chart.height / 2 + 20;

          ctx.font = 'bold 32px Arial';
          ctx.fillStyle = this.getGaugeColor(normalizedValue);
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(`${normalizedValue.toFixed(1)}`, centerX, centerY);

          ctx.font = '14px Arial';
          ctx.fillStyle = '#666';
          ctx.fillText(label, centerX, centerY + 30);

          ctx.restore();
        }
      }]
    });
  }

  private getGaugeColor(value: number): string {
    if (value >= 80) return '#10b981'; // Green
    if (value >= 70) return '#f59e0b'; // Yellow
    return '#ef4444'; // Red
  }

  private updateGauges() {
    if (!this.currentKPIs) return;

    this.updateGauge('effektivitet', this.currentKPIs.effektivitet);
    this.updateGauge('produktivitet', this.currentKPIs.produktivitet);
    this.updateGauge('kvalitet', this.currentKPIs.kvalitet);
  }

  private updateGauge(id: string, value: number) {
    const chart = this.gaugeCharts[id];
    if (!chart) {
      this.createGauges();
      return;
    }

    const normalizedValue = Math.min(Math.max(value, 0), 100);
    chart.data.datasets[0].data = [normalizedValue, 100 - normalizedValue];
    chart.data.datasets[0].backgroundColor = [
      this.getGaugeColor(normalizedValue),
      'rgba(200, 200, 200, 0.2)'
    ];
    chart.update();
  }

  // ============ MULTI-LINE TREND ============
  private createMultiLineTrend() {
    const canvas = document.getElementById('multiTrendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    if (this.trendChart) {
      this.trendChart.destroy();
    }

    const dates = this.dailyData.map(d => d.date);

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: dates,
        datasets: [
          {
            label: 'Effektivitet',
            data: this.dailyData.map(d => d.effektivitet),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.4,
            fill: true
          },
          {
            label: 'Produktivitet (norm)',
            data: this.dailyData.map(d => Math.min(d.produktivitet, 100)),
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.4,
            fill: true
          },
          {
            label: 'Kvalitet',
            data: this.dailyData.map(d => d.kvalitet),
            borderColor: 'rgb(255, 206, 86)',
            backgroundColor: 'rgba(255, 206, 86, 0.1)',
            tension: 0.4,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
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

  private updateMultiLineTrend() {
    if (!this.trendChart) {
      this.createMultiLineTrend();
      return;
    }

    const dates = this.dailyData.map(d => d.date);
    this.trendChart.data.labels = dates;
    this.trendChart.data.datasets[0].data = this.dailyData.map(d => d.effektivitet);
    this.trendChart.data.datasets[1].data = this.dailyData.map(d => Math.min(d.produktivitet, 100));
    this.trendChart.data.datasets[2].data = this.dailyData.map(d => d.kvalitet);
    this.trendChart.update();
  }

  // ============ DISTRIBUTION CHART ============
  private createDistribution() {
    const canvas = document.getElementById('distributionChart') as HTMLCanvasElement;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    if (this.distributionChart) {
      this.distributionChart.destroy();
    }

    const distribution = this.calculateDistribution();

    this.distributionChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: distribution.labels,
        datasets: [{
          label: 'Antal dagar',
          data: distribution.counts,
          backgroundColor: [
            'rgba(239, 68, 68, 0.8)',   // <70
            'rgba(245, 158, 11, 0.8)',  // 70-79
            'rgba(59, 130, 246, 0.8)',  // 80-89
            'rgba(16, 185, 129, 0.8)'   // 90+
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          title: {
            display: true,
            text: 'Bonuspoäng Distribution'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        }
      }
    });
  }

  private calculateDistribution() {
    const ranges = {
      '<70': 0,
      '70-79': 0,
      '80-89': 0,
      '90+': 0
    };

    this.dailyData.forEach(day => {
      const bonus = day.bonus;
      if (bonus < 70) ranges['<70']++;
      else if (bonus < 80) ranges['70-79']++;
      else if (bonus < 90) ranges['80-89']++;
      else ranges['90+']++;
    });

    return {
      labels: Object.keys(ranges),
      counts: Object.values(ranges)
    };
  }

  private updateDistribution() {
    if (!this.distributionChart) {
      this.createDistribution();
      return;
    }

    const distribution = this.calculateDistribution();
    this.distributionChart.data.labels = distribution.labels;
    this.distributionChart.data.datasets[0].data = distribution.counts;
    this.distributionChart.update();
  }

  // ============ HELPER METHODS FOR TEMPLATE ============
  getCurrentBonus(): number {
    if (!this.currentKPIs) return 0;
    return this.currentKPIs.bonus;
  }

  getCurrentEffectiveness(): number {
    if (!this.currentKPIs) return 0;
    return this.currentKPIs.effektivitet;
  }

  getCurrentProductivity(): number {
    if (!this.currentKPIs) return 0;
    return this.currentKPIs.produktivitet;
  }

  isTrendPositive(kpi: string): boolean {
    if (this.dailyData.length < 2) return true;

    const recent = this.dailyData.slice(-7); // Last 7 days
    const values = recent.map(d => {
      switch(kpi) {
        case 'bonus': return d.bonus;
        case 'effektivitet': return d.effektivitet;
        case 'produktivitet': return d.produktivitet;
        default: return 0;
      }
    });

    const firstHalf = values.slice(0, Math.floor(values.length / 2));
    const secondHalf = values.slice(Math.floor(values.length / 2));

    const avg1 = firstHalf.reduce((a, b) => a + b, 0) / firstHalf.length;
    const avg2 = secondHalf.reduce((a, b) => a + b, 0) / secondHalf.length;

    return avg2 > avg1;
  }

  getTrendPercentage(kpi: string): string {
    if (this.dailyData.length < 2) return '0.0';

    const recent = this.dailyData.slice(-7);
    const values = recent.map(d => {
      switch(kpi) {
        case 'bonus': return d.bonus;
        case 'effektivitet': return d.effektivitet;
        case 'produktivitet': return d.produktivitet;
        default: return 0;
      }
    });

    const firstHalf = values.slice(0, Math.floor(values.length / 2));
    const secondHalf = values.slice(Math.floor(values.length / 2));

    const avg1 = firstHalf.reduce((a, b) => a + b, 0) / firstHalf.length;
    const avg2 = secondHalf.reduce((a, b) => a + b, 0) / secondHalf.length;

    const change = ((avg2 - avg1) / avg1) * 100;
    return Math.abs(change).toFixed(1);
  }

  getStrongestKPI(): string {
    if (!this.currentKPIs) return 'N/A';

    const kpis = {
      'Effektivitet': this.currentKPIs.effektivitet,
      'Produktivitet': this.currentKPIs.produktivitet,
      'Kvalitet': this.currentKPIs.kvalitet
    };

    const strongest = Object.entries(kpis).reduce((a, b) => a[1] > b[1] ? a : b);
    return `${strongest[0]} (${strongest[1].toFixed(1)}%)`;
  }

  getBestImprovement(): string {
    if (this.dailyData.length < 7) return 'Behöver mer data';

    const improvements = {
      'Effektivitet': this.calculateImprovement('effektivitet'),
      'Produktivitet': this.calculateImprovement('produktivitet'),
      'Kvalitet': this.calculateImprovement('kvalitet')
    };

    const best = Object.entries(improvements).reduce((a, b) => a[1] > b[1] ? a : b);
    return `${best[0]} (+${best[1].toFixed(1)}%)`;
  }

  getImprovementArea(): string {
    if (!this.currentKPIs) return 'N/A';

    const kpis = {
      'Effektivitet': this.currentKPIs.effektivitet,
      'Produktivitet': this.currentKPIs.produktivitet,
      'Kvalitet': this.currentKPIs.kvalitet
    };

    const weakest = Object.entries(kpis).reduce((a, b) => a[1] < b[1] ? a : b);
    const target = weakest[0] === 'Effektivitet' ? 95 :
                   weakest[0] === 'Produktivitet' ? 15 : 98;
    const gap = target - weakest[1];

    if (gap <= 0) {
      return `Alla KPI:er är starka! 🎉`;
    }

    return `${weakest[0]} (${gap.toFixed(1)}% till mål)`;
  }

  private calculateImprovement(kpi: string): number {
    const recent = this.dailyData.slice(-14); // Last 2 weeks
    if (recent.length < 7) return 0;

    const week1 = recent.slice(0, 7).map(d => {
      switch(kpi) {
        case 'effektivitet': return d.effektivitet;
        case 'produktivitet': return d.produktivitet;
        case 'kvalitet': return d.kvalitet;
        default: return 0;
      }
    });

    const week2 = recent.slice(7, 14).map(d => {
      switch(kpi) {
        case 'effektivitet': return d.effektivitet;
        case 'produktivitet': return d.produktivitet;
        case 'kvalitet': return d.kvalitet;
        default: return 0;
      }
    });

    const avg1 = week1.reduce((a, b) => a + b, 0) / week1.length;
    const avg2 = week2.reduce((a, b) => a + b, 0) / week2.length;

    return ((avg2 - avg1) / avg1) * 100;
  }
}
