import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, interval, of } from 'rxjs';
import { takeUntil, catchError, timeout, startWith } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, RealtimeOeeResponse, RealtimeOeeData } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-oee-gauge',
  templateUrl: './statistik-oee-gauge.html',
  imports: [CommonModule]
})
export class StatistikOeeGaugeComponent implements OnInit, AfterViewInit, OnDestroy {
  selectedPeriod: 'today' | '7d' | '30d' = 'today';
  loading = false;
  data: RealtimeOeeData | null = null;
  error: string | null = null;

  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  private gaugeChart: Chart | null = null;

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    // Poll var 60:e sekund + initial load
    interval(60000).pipe(
      startWith(0),
      takeUntil(this.destroy$)
    ).subscribe(() => this.loadOee());
  }

  ngAfterViewInit(): void {
    // Chart renderas efter data laddats
  }

  ngOnDestroy(): void {
    try { this.gaugeChart?.destroy(); } catch (e) {}
    this.gaugeChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  selectPeriod(period: 'today' | '7d' | '30d'): void {
    this.selectedPeriod = period;
    this.loadOee();
  }

  loadOee(): void {
    this.loading = true;
    this.error = null;
    this.rebotlingService.getRealtimeOee(this.selectedPeriod).pipe(
      timeout(15000),
      catchError((_err) => {
        this.error = 'Kunde inte hämta OEE-data';
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe((res: RealtimeOeeResponse | null) => {
      this.loading = false;
      if (res?.success && res.data) {
        this.data = res.data;
        this._timers.push(setTimeout(() => {
          if (!this.destroy$.closed) this.renderGauge();
        }, 50));
      } else if (!this.error) {
        this.error = res?.error || 'Ingen data tillgänglig';
      }
    });
  }

  getOeeColor(value: number): string {
    if (value >= 85) return '#48bb78'; // green
    if (value >= 60) return '#ecc94b'; // yellow
    return '#fc8181'; // red
  }

  getOeeLabel(value: number): string {
    if (value >= 85) return 'Utmärkt';
    if (value >= 70) return 'Bra';
    if (value >= 60) return 'Godkänt';
    return 'Kritiskt';
  }

  private renderGauge(): void {
    try { this.gaugeChart?.destroy(); } catch (e) {}
    this.gaugeChart = null;

    const canvas = document.getElementById('oeeGaugeCanvas') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.data) return;

    const oee = this.data.oee_percent;
    const color = this.getOeeColor(oee);
    const remaining = Math.max(100 - oee, 0);

    if (this.gaugeChart) { (this.gaugeChart as any).destroy(); }
    this.gaugeChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [oee, remaining],
          backgroundColor: [color, '#2d3748'],
          borderWidth: 0,
          circumference: 270,
          rotation: 225
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '78%',
        plugins: {
          legend: { display: false },
          tooltip: { intersect: false, mode: 'nearest', enabled: false }
        }
      },
      plugins: [{
        id: 'oeeGaugeCenterText',
        afterDraw: (chart: any) => {
          const { ctx, chartArea } = chart;
          if (!chartArea) return;
          const centerX = (chartArea.left + chartArea.right) / 2;
          const centerY = (chartArea.top + chartArea.bottom) / 2 + 10;

          ctx.save();
          // Large OEE number
          ctx.font = 'bold 48px sans-serif';
          ctx.fillStyle = color;
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(`${oee.toFixed(1)}%`, centerX, centerY - 10);

          // Label
          ctx.font = '16px sans-serif';
          ctx.fillStyle = '#a0aec0';
          ctx.fillText('OEE', centerX, centerY + 28);
          ctx.restore();
        }
      }]
    });
  }
}
