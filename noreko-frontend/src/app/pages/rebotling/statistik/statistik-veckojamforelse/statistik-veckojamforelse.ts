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
      timeout(15000),
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

    // Use weekday names as labels so that both datasets (prev/this week)
    // align to the same weekday column. Show weekday name + this week's date.
    const weekdays = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
    const n = Math.max(this.weekComparisonThisWeek.length, this.weekComparisonPrevWeek.length);
    const labels: string[] = [];
    for (let i = 0; i < n; i++) {
      const entry = this.weekComparisonThisWeek[i] ?? this.weekComparisonPrevWeek[i];
      if (entry?.label) {
        labels.push(entry.label);
      } else if (entry?.date) {
        const wd = new Date(entry.date + 'T00:00:00').getDay();
        const wdIdx = wd === 0 ? 6 : wd - 1;
        labels.push(weekdays[wdIdx]);
      } else {
        labels.push(`Dag ${i + 1}`);
      }
    }

    // Pad shorter array to match length so bars align correctly
    const thisData = Array.from({ length: n }, (_, i) => this.weekComparisonThisWeek[i]?.ibc_ok ?? 0);
    const prevData = Array.from({ length: n }, (_, i) => this.weekComparisonPrevWeek[i]?.ibc_ok ?? 0);

    if (this.weekComparisonChart) { (this.weekComparisonChart as any).destroy(); }
    this.weekComparisonChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Förra veckan',
            data: prevData,
            backgroundColor: 'rgba(113,128,150,0.5)',
            borderColor: 'rgba(160,174,192,0.8)',
            borderWidth: 1,
            borderRadius: 4
          },
          {
            label: 'Denna vecka',
            data: thisData,
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
            intersect: false, mode: 'index',
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? 0;
                const thisDate = this.weekComparisonThisWeek[idx]?.date ?? '';
                const prevDate = this.weekComparisonPrevWeek[idx]?.date ?? '';
                return `${labels[idx]}${thisDate ? ' (' + thisDate.substring(5) + ' vs ' + prevDate.substring(5) + ')' : ''}`;
              },
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? 0;
                const thisW = thisData[idx] ?? 0;
                const prevW = prevData[idx] ?? 0;
                if (prevW > 0) {
                  const diff = thisW - prevW;
                  const pct = Math.round((diff / prevW) * 100);
                  return [`Skillnad: ${diff >= 0 ? '+' : ''}${diff} IBC (${pct >= 0 ? '+' : ''}${pct}%)`];
                }
                return [];
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
