import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart } from 'chart.js';
import { RebotlingService, WeekdayStatsEntry } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-veckodag',
  templateUrl: './statistik-veckodag.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikVeckodagComponent implements OnInit, OnDestroy {
  weekdayData: WeekdayStatsEntry[] = [];
  weekdayLoading: boolean = false;
  weekdayDagar: number = 90;
  private weekdayChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() { this.loadWeekdayStats(); }

  ngOnDestroy() {
    try { this.weekdayChart?.destroy(); } catch (e) {}
    this.weekdayChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  getWeekdayMaxIbc(): number {
    return this.weekdayData.length ? Math.max(...this.weekdayData.map(d => d.snitt_ibc)) : 0;
  }

  getWeekdayMinIbc(): number {
    return this.weekdayData.length ? Math.min(...this.weekdayData.map(d => d.snitt_ibc)) : 0;
  }

  loadWeekdayStats(): void {
    this.weekdayLoading = true;
    this.rebotlingService.getWeekdayStats(this.weekdayDagar).pipe(
      timeout(8000),
      catchError(() => of({ success: false, veckodagar: [] })),
      takeUntil(this.destroy$)
    ).subscribe((r: any) => {
      this.weekdayData = r.veckodagar || [];
      this.weekdayLoading = false;
      setTimeout(() => { if (!this.destroy$.closed) this.buildWeekdayChart(); }, 50);
    });
  }

  private buildWeekdayChart(): void {
    try { this.weekdayChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('weekdayChart') as HTMLCanvasElement;
    if (!canvas || !this.weekdayData.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const sorted = [...this.weekdayData].sort((a, b) => {
      const na = a.veckodag_nr === 1 ? 8 : a.veckodag_nr;
      const nb = b.veckodag_nr === 1 ? 8 : b.veckodag_nr;
      return na - nb;
    });
    const labels = sorted.map(d => d.namn);
    const ibcData = sorted.map(d => d.snitt_ibc);
    const maxIbc = Math.max(...ibcData);
    const minIbc = Math.min(...ibcData);
    const colors = ibcData.map(v =>
      v === maxIbc ? 'rgba(72,187,120,0.85)' :
      v === minIbc ? 'rgba(245,101,101,0.85)' :
      'rgba(66,153,225,0.65)'
    );

    this.weekdayChart = new Chart(ctx as any, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Snitt IBC/dag',
          data: ibcData,
          backgroundColor: colors,
          borderColor: colors.map(c => c.replace('0.85', '1').replace('0.65', '1')),
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              afterBody: (items: any[]) => {
                const d = sorted[items[0].dataIndex];
                const lines: string[] = [];
                if (d.snitt_oee !== null) lines.push('OEE: ' + d.snitt_oee + '%');
                lines.push('Max: ' + d.max_ibc + ' IBC', 'Min: ' + d.min_ibc + ' IBC', 'Antal dagar: ' + d.antal_dagar);
                return lines;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#4a5568' } },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a5568' },
            title: { display: true, text: 'IBC/dag', color: '#a0aec0' }
          }
        }
      }
    });
  }
  trackByIndex(index: number): number { return index; }
}
