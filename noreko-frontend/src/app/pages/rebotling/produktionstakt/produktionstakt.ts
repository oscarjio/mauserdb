import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  ProduktionsTaktService,
  CurrentRateData,
  HourlyEntry
} from '../../../services/produktionstakt.service';
import { AuthService } from '../../../services/auth.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  selector: 'app-produktionstakt',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './produktionstakt.html',
  styleUrl: './produktionstakt.css'
})
export class ProduktionsTaktPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;
  private chart: any = null;

  @ViewChild('taktChart', { static: false }) chartRef!: ElementRef<HTMLCanvasElement>;

  // Data
  currentRate: CurrentRateData | null = null;
  hourlyHistory: HourlyEntry[] = [];
  target = 12;
  newTarget = 12;

  // UI state
  loading = true;
  loadingHistory = true;
  showTargetForm = false;
  savingTarget = false;
  isAdmin = false;
  alertDismissed = false;
  previousRate: number | null = null;
  rateAnimating = false;

  constructor(
    private taktService: ProduktionsTaktService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.authService.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      this.isAdmin = user?.role === 'admin';
    });

    this.fetchAll();
    this.pollInterval = setInterval(() => this.fetchAll(), 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
  }

  fetchAll(): void {
    this.taktService.getCurrentRate().pipe(
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success && res.data) {
        if (this.currentRate !== null) {
          this.previousRate = this.currentRate.current_rate;
        }
        this.currentRate = res.data;
        this.target = res.data.target;
        this.newTarget = res.data.target;
        this.loading = false;

        // Trigger animation
        if (this.previousRate !== null && this.previousRate !== res.data.current_rate) {
          this.rateAnimating = true;
          setTimeout(() => this.rateAnimating = false, 600);
        }
      }
    });

    this.taktService.getHourlyHistory().pipe(
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success && res.data) {
        this.hourlyHistory = res.data.history;
        this.loadingHistory = false;
        setTimeout(() => this.renderChart(), 100);
      }
    });
  }

  // ---- Chart ----

  renderChart(): void {
    if (!this.chartRef?.nativeElement || this.hourlyHistory.length === 0) return;

    const ctx = this.chartRef.nativeElement.getContext('2d');
    if (!ctx) return;

    const labels = this.hourlyHistory.map(h => h.hour_label);
    const data = this.hourlyHistory.map(h => h.ibc_count);
    const targetLine = this.hourlyHistory.map(h => h.target);

    if (this.chart) {
      this.chart.data.labels = labels;
      this.chart.data.datasets[0].data = data;
      this.chart.data.datasets[1].data = targetLine;
      this.chart.update();
      return;
    }

    this.chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66, 153, 225, 0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointBackgroundColor: '#4299e1',
            borderWidth: 2,
          },
          {
            label: 'Maltal',
            data: targetLine,
            borderColor: '#48bb78',
            borderDash: [8, 4],
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => `${ctx.dataset.label}: ${ctx.parsed.y} IBC`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' }
          }
        }
      }
    });
  }

  // ---- Target ----

  toggleTargetForm(): void {
    this.showTargetForm = !this.showTargetForm;
  }

  saveTarget(): void {
    if (this.newTarget <= 0 || this.newTarget > 100) return;
    this.savingTarget = true;
    this.taktService.setTarget(this.newTarget).pipe(
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.savingTarget = false;
      if (res?.success) {
        this.target = this.newTarget;
        this.showTargetForm = false;
        this.fetchAll();
      }
    });
  }

  dismissAlert(): void {
    this.alertDismissed = true;
  }

  // ---- Helpers ----

  get trendIcon(): string {
    if (!this.currentRate) return 'fa-minus';
    switch (this.currentRate.trend) {
      case 'up': return 'fa-arrow-up';
      case 'down': return 'fa-arrow-down';
      default: return 'fa-minus';
    }
  }

  get trendClass(): string {
    if (!this.currentRate) return 'text-muted';
    switch (this.currentRate.trend) {
      case 'up': return 'text-success';
      case 'down': return 'text-danger';
      default: return 'text-muted';
    }
  }

  get targetStatusClass(): string {
    if (!this.currentRate) return '';
    switch (this.currentRate.target_status) {
      case 'green': return 'status-green';
      case 'yellow': return 'status-yellow';
      case 'red': return 'status-red';
      default: return '';
    }
  }

  get targetStatusLabel(): string {
    if (!this.currentRate) return '';
    switch (this.currentRate.target_status) {
      case 'green': return 'Pa mal';
      case 'yellow': return 'Under mal';
      case 'red': return 'Kritiskt';
      default: return '';
    }
  }

  getRowStatusClass(entry: HourlyEntry): string {
    switch (entry.status) {
      case 'green': return 'table-row-green';
      case 'yellow': return 'table-row-yellow';
      case 'red': return 'table-row-red';
      default: return '';
    }
  }
  trackByIndex(index: number): number { return index; }
}
