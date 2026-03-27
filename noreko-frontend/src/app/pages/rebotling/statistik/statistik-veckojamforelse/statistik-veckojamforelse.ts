import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, WeekComparisonDay } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-veckojamforelse',
  templateUrl: './statistik-veckojamforelse.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikVeckojamforelseComponent implements OnInit, OnDestroy {
  weekComparisonLoaded: boolean = false;
  weekComparisonLoading: boolean = false;
  weekComparisonError: boolean = false;
  weekComparisonThisWeek: WeekComparisonDay[] = [];
  weekComparisonPrevWeek: WeekComparisonDay[] = [];
  weekGranularity: 'day' | 'shift' = 'day';
  private weekComparisonChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() { this.loadWeekComparison(); }

  ngOnDestroy() {
    try { this.weekComparisonChart?.destroy(); } catch (e) {}
    this.weekComparisonChart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  setWeekGranularity(g: 'day' | 'shift') {
    this.weekGranularity = g;
    this.weekComparisonLoaded = false;
    this.loadWeekComparison();
  }

  loadWeekComparison() {
    if (this.weekComparisonLoading) return;
    this.weekComparisonLoading = true;
    this.weekComparisonError = false;
    this.rebotlingService.getWeekComparison(this.weekGranularity).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe((res: any) => {
      this.weekComparisonLoading = false;
      if (res?.success && res.data) {
        this.weekComparisonThisWeek = res.data.this_week;
        this.weekComparisonPrevWeek = res.data.prev_week;
        this.weekComparisonLoaded = true;
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.renderWeekComparisonChart(); }, 100));
      } else {
        this.weekComparisonError = true;
        this.weekComparisonLoaded = true;
      }
    });
  }

  private renderWeekComparisonChart() {
    try { this.weekComparisonChart?.destroy(); } catch (e) {}
    this.weekComparisonChart = null;
    const canvas = document.getElementById('weekComparisonChart') as HTMLCanvasElement;
    if (!canvas) return;

    const weekdays = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
    const labels = this.weekComparisonThisWeek.map(d => {
      if (d.label) return d.label;
      const wd = new Date(d.date + 'T00:00:00').getDay();
      const wdIdx = wd === 0 ? 6 : wd - 1;
      return `${weekdays[wdIdx]} ${d.date.substring(5)}`;
    });

    if (this.weekComparisonChart) { (this.weekComparisonChart as any).destroy(); }
    this.weekComparisonChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Förra veckan',
            data: this.weekComparisonPrevWeek.map(d => d.ibc_ok),
            backgroundColor: 'rgba(113,128,150,0.5)',
            borderColor: 'rgba(160,174,192,0.8)',
            borderWidth: 1,
            borderRadius: 4
          },
          {
            label: 'Denna vecka',
            data: this.weekComparisonThisWeek.map(d => d.ibc_ok),
            backgroundColor: 'rgba(66,153,225,0.7)',
            borderColor: 'rgba(99,179,237,1)',
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 12 } } },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              afterLabel: (ctx: any) => {
                const thisW = this.weekComparisonThisWeek[ctx.dataIndex]?.ibc_ok ?? 0;
                const prevW = this.weekComparisonPrevWeek[ctx.dataIndex]?.ibc_ok ?? 0;
                if (ctx.datasetIndex === 1 && prevW > 0) {
                  const diff = thisW - prevW;
                  const pct = Math.round((diff / prevW) * 100);
                  return `${diff >= 0 ? '+' : ''}${diff} IBC (${pct >= 0 ? '+' : ''}${pct}% vs förra)`;
                }
                return '';
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#8fa3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: { beginAtZero: true, ticks: { color: '#8fa3b8' }, grid: { color: 'rgba(255,255,255,0.05)' },
               title: { display: true, text: 'IBC OK', color: '#8fa3b8' } }
        }
      }
    });
  }

  getWeekComparisonTotal(week: WeekComparisonDay[]): number {
    return week.reduce((s, d) => s + d.ibc_ok, 0);
  }

  getWeekComparisonDiff(): number {
    return this.getWeekComparisonTotal(this.weekComparisonThisWeek) -
           this.getWeekComparisonTotal(this.weekComparisonPrevWeek);
  }

  getWeekComparisonDiffPct(): number {
    const prev = this.getWeekComparisonTotal(this.weekComparisonPrevWeek);
    if (prev === 0) return 0;
    return Math.round((this.getWeekComparisonDiff() / prev) * 100);
  }
}
