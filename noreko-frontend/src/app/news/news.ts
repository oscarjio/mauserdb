import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit } from '@angular/core';
import { RebotlingService, RebotlingLiveStatsResponse, LineStatusResponse, StatisticsResponse } from '../services/rebotling.service';
import { TvattlinjeService, TvattlinjeLiveStatsResponse, StatisticsResponse as TvattlinjeStatisticsResponse } from '../services/tvattlinje.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  selector: 'app-news',
  standalone: true,
  templateUrl: './news.html',
  styleUrl: './news.css'
})
export class News implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('rebotlingChart') rebotlingChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('tvattlinjeChart') tvattlinjeChartRef!: ElementRef<HTMLCanvasElement>;

  intervalId: any;
  chartIntervalId: any;

  // Rebotling data
  rebotlingStatus: boolean = false;
  rebotlingToday: number = 0;
  rebotlingChart: Chart | null = null;
  rebotlingTargetCycleTime: number = 10;

  // Tvättlinje data
  tvattlinjeStatus: boolean = false;
  tvattlinjeToday: number = 0;
  tvattlinjeTarget: number = 0;
  tvattlinjeThisHour: number = 0;
  tvattlinjeHourlyTarget: number = 0;
  tvattlinjeNeedleRotation: number = -100;
  tvattlinjeBadgeClass: string = 'bg-success';
  tvattlinjeChart: Chart | null = null;
  tvattlinjeTargetCycleTime: number = 3;

  // Saglinje data (placeholder)
  saglinjeStatus: boolean = false;
  saglinjeToday: number = 0;
  saglinjeTarget: number = 0;

  // Klassificeringslinje data (placeholder)
  klassificeringslinjeStatus: boolean = false;
  klassificeringslinjeToday: number = 0;
  klassificeringslinjeTarget: number = 0;

  constructor(
    private rebotlingService: RebotlingService,
    private tvattlinjeService: TvattlinjeService
  ) {}

  ngOnInit() {
    this.intervalId = setInterval(() => {
      this.fetchAllData();
    }, 5000); // Update every 5 seconds
    this.fetchAllData();

    // Uppdatera graferna var 60:e sekund
    this.chartIntervalId = setInterval(() => {
      this.fetchChartData();
    }, 60000);
  }

  ngAfterViewInit() {
    // Vänta lite så att canvas hinner renderas
    setTimeout(() => {
      this.fetchChartData();
    }, 500);
  }

  ngOnDestroy() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
    if (this.chartIntervalId) {
      clearInterval(this.chartIntervalId);
    }
    if (this.rebotlingChart) {
      this.rebotlingChart.destroy();
    }
    if (this.tvattlinjeChart) {
      this.tvattlinjeChart.destroy();
    }
  }

  private fetchAllData() {
    this.fetchRebotlingData();
    this.fetchTvattlinjeData();
  }

  private fetchRebotlingData() {
    // Fetch live stats
    this.rebotlingService.getLiveStats().subscribe((res: RebotlingLiveStatsResponse) => {
      if (res && res.success && res.data) {
        // Använd ibcToday (antal rader från rebotling_ibc idag) istället för rebotlingToday
        this.rebotlingToday = res.data.ibcToday || 0;
      }
    });

    // Fetch status
    this.rebotlingService.getRunningStatus().subscribe((res: LineStatusResponse) => {
      if (res && res.success && res.data) {
        this.rebotlingStatus = res.data.running;
      }
    });
  }

  private fetchTvattlinjeData() {
    // Fetch live stats
    this.tvattlinjeService.getLiveStats().subscribe((res: TvattlinjeLiveStatsResponse) => {
      if (res && res.success && res.data) {
        this.tvattlinjeToday = res.data.ibcToday;
        this.tvattlinjeTarget = res.data.ibcTarget;
      }
    });

    // Fetch status
    this.tvattlinjeService.getRunningStatus().subscribe((res: LineStatusResponse) => {
      if (res && res.success && res.data) {
        this.tvattlinjeStatus = res.data.running;
      }
    });
  }

  private fetchChartData() {
    const today = new Date();
    const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    // Hämta rebotling statistik
    this.rebotlingService.getStatistics(dateStr, dateStr).subscribe((res: StatisticsResponse) => {
      if (res && res.success && res.data) {
        this.rebotlingTargetCycleTime = res.data.summary?.target_cycle_time || 10;
        this.updateRebotlingChart(res.data);
      }
    });

    // Hämta tvättlinje statistik
    this.tvattlinjeService.getStatistics(dateStr, dateStr).subscribe((res: TvattlinjeStatisticsResponse) => {
      if (res && res.success && res.data) {
        this.tvattlinjeTargetCycleTime = res.data.summary?.target_cycle_time || 3;
        this.updateTvattlinjeChart(res.data);
      }
    });
  }

  private prepareMiniChartData(data: any, maxCycleTime: number, targetCycleTime: number) {
    const cycles = data.cycles || [];

    // Skapa 24-timmars intervall (per timme)
    const grouped = new Map<number, number[]>();
    for (let h = 0; h < 24; h++) {
      grouped.set(h, []);
    }

    // Gruppera cykler per timme
    cycles.forEach((cycle: any) => {
      const date = new Date(cycle.datum);
      const hour = date.getHours();
      const cycleTimeValue = parseFloat(cycle.cycle_time);

      if (!isNaN(cycleTimeValue) && cycleTimeValue > 0 && cycleTimeValue <= maxCycleTime) {
        grouped.get(hour)?.push(cycleTimeValue);
      }
    });

    // Bygg arrayer
    const labels: string[] = [];
    const cycleTime: (number | null)[] = [];
    let totalCycleTime = 0;
    let totalCount = 0;

    for (let h = 0; h < 24; h++) {
      labels.push(`${h.toString().padStart(2, '0')}`);
      const times = grouped.get(h) || [];

      if (times.length > 0) {
        const avg = times.reduce((a, b) => a + b, 0) / times.length;
        cycleTime.push(Math.round(avg * 10) / 10);
        totalCycleTime += avg * times.length;
        totalCount += times.length;
      } else {
        cycleTime.push(null);
      }
    }

    const overallAvg = totalCount > 0 ? Math.round((totalCycleTime / totalCount) * 10) / 10 : 0;
    const avgCycleTime = labels.map(() => overallAvg > 0 ? overallAvg : null);
    const targetCycleTimeArr = labels.map(() => targetCycleTime);

    return { labels, cycleTime, avgCycleTime, targetCycleTime: targetCycleTimeArr };
  }

  private updateRebotlingChart(data: any) {
    if (!this.rebotlingChartRef?.nativeElement) {
      return;
    }

    const chartData = this.prepareMiniChartData(data, 30, this.rebotlingTargetCycleTime);

    if (this.rebotlingChart) {
      this.rebotlingChart.data.labels = chartData.labels;
      this.rebotlingChart.data.datasets[0].data = chartData.cycleTime;
      this.rebotlingChart.data.datasets[1].data = chartData.avgCycleTime;
      this.rebotlingChart.data.datasets[2].data = chartData.targetCycleTime;
      this.rebotlingChart.update('none');
    } else {
      const ctx = this.rebotlingChartRef.nativeElement.getContext('2d');
      if (!ctx) return;
      this.rebotlingChart = this.createMiniChart(ctx, chartData);
    }
  }

  private updateTvattlinjeChart(data: any) {
    if (!this.tvattlinjeChartRef?.nativeElement) {
      return;
    }

    const chartData = this.prepareMiniChartData(data, 15, this.tvattlinjeTargetCycleTime);

    if (this.tvattlinjeChart) {
      this.tvattlinjeChart.data.labels = chartData.labels;
      this.tvattlinjeChart.data.datasets[0].data = chartData.cycleTime;
      this.tvattlinjeChart.data.datasets[1].data = chartData.avgCycleTime;
      this.tvattlinjeChart.data.datasets[2].data = chartData.targetCycleTime;
      this.tvattlinjeChart.update('none');
    } else {
      const ctx = this.tvattlinjeChartRef.nativeElement.getContext('2d');
      if (!ctx) return;
      this.tvattlinjeChart = this.createMiniChart(ctx, chartData);
    }
  }

  private createMiniChart(ctx: CanvasRenderingContext2D, chartData: any): Chart {
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels: chartData.labels,
        datasets: [
          {
            label: 'Cykeltid',
            data: chartData.cycleTime,
            borderColor: '#00d4ff',
            backgroundColor: 'rgba(0, 212, 255, 0.15)',
            tension: 0.4,
            fill: true,
            pointRadius: 0,
            borderWidth: 2,
            spanGaps: true
          },
          {
            label: 'Snitt',
            data: chartData.avgCycleTime,
            borderColor: '#ffc107',
            borderDash: [4, 2],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            spanGaps: true
          },
          {
            label: 'Mål',
            data: chartData.targetCycleTime,
            borderColor: '#ff8800',
            borderDash: [2, 2],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            enabled: false
          }
        },
        scales: {
          x: {
            display: true,
            grid: {
              display: false
            },
            ticks: {
              color: 'rgba(255, 255, 255, 0.5)',
              font: { size: 8 },
              maxTicksLimit: 6,
              callback: function(value, index) {
                return index % 4 === 0 ? this.getLabelForValue(index) : '';
              }
            }
          },
          y: {
            display: true,
            beginAtZero: true,
            grid: {
              color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
              color: 'rgba(255, 255, 255, 0.5)',
              font: { size: 8 },
              maxTicksLimit: 4
            }
          }
        },
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
  }
}
