import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart } from 'chart.js';
import { RebotlingService, CycleByOperatorEntry, CycleByOperatorResponse } from '../../../../services/rebotling.service';
import { localDateStr } from '../../../../utils/date-utils';

@Component({
  standalone: true,
  selector: 'app-statistik-cykeltid-operator',
  templateUrl: './statistik-cykeltid-operator.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikCykeltidOperatorComponent implements OnInit, OnDestroy {
  cycleByOpDays: number = 30;
  cycleByOpLoaded: boolean = false;
  cycleByOpLoading: boolean = false;
  cycleByOpData: CycleByOperatorEntry[] = [];
  private cycleByOpChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    this.loadCycleByOperator();
  }

  ngOnDestroy() {
    try { this.cycleByOpChart?.destroy(); } catch (e) {}
    this.cycleByOpChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  onCycleByOpDaysChange() {
    this.cycleByOpLoaded = false;
    this.loadCycleByOperator();
  }

  loadCycleByOperator() {
    if (this.cycleByOpLoading) return;
    this.cycleByOpLoading = true;

    const fmt = (d: Date) => localDateStr(d);
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - (this.cycleByOpDays - 1));

    this.rebotlingService.getCycleByOperator(fmt(startDate), fmt(endDate)).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: CycleByOperatorResponse | null) => {
      this.cycleByOpLoading = false;
      if (res?.success && res.data) {
        const medians = res.data.map(op => op.median_min ?? ((op.snitt_cykel_sek ?? 0) / 60));
        const teamSnitt = medians.length > 0 ? medians.reduce((a, b) => a + b, 0) / medians.length : 0;
        this.cycleByOpData = res.data
          .map(op => ({
            ...op,
            vs_team_snitt: teamSnitt > 0
              ? Math.round(((op.median_min ?? (op.snitt_cykel_sek ?? 0) / 60) - teamSnitt) / teamSnitt * 100)
              : 0
          }))
          .sort((a, b) => b.antal_skift - a.antal_skift);
        this.cycleByOpLoaded = true;
        setTimeout(() => { if (!this.destroy$.closed) this.renderCycleByOpChart(); }, 100);
      } else {
        this.cycleByOpLoaded = true;
        this.cycleByOpData = [];
      }
    });
  }

  private renderCycleByOpChart() {
    try { this.cycleByOpChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('cycleByOpChart') as HTMLCanvasElement;
    if (!canvas || !this.cycleByOpData.length) return;

    const sorted = [...this.cycleByOpData];
    const chartData = [...sorted].reverse();
    const labels = chartData.map(op => op.initialer);
    const values = chartData.map(op => op.median_min ?? (op.snitt_cykel_sek / 60));

    const teamSnitt = values.reduce((a, b) => a + b, 0) / values.length;
    const maxVal = Math.max(...values);
    const xMax = maxVal * 1.2;

    const colors = chartData.map(op => {
      const v = op.median_min ?? (op.snitt_cykel_sek / 60);
      if (v <= teamSnitt) return 'rgba(72, 187, 120, 0.8)';
      return 'rgba(252, 129, 129, 0.8)';
    });
    const borderColors = colors.map(c => c.replace('0.8)', '1)'));

    this.cycleByOpChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Median cykeltid (min)',
            data: values,
            backgroundColor: colors,
            borderColor: borderColors,
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.96)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0].dataIndex;
                return chartData[idx].namn;
              },
              label: (ctx: any) => {
                const op = chartData[ctx.dataIndex];
                const vsSign = (op.vs_team_snitt ?? 0) >= 0 ? '+' : '';
                return [
                  ` Median: ${op.median_min?.toFixed(2) ?? '–'} min`,
                  ` P90: ${op.p90_min?.toFixed(2) ?? '–'} min`,
                  ` vs Teamsnitt: ${vsSign}${op.vs_team_snitt ?? 0}%`,
                  ` Antal skift: ${op.antal_skift} st`,
                  ` Total IBC: ${op.total_ibc}`,
                ];
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            max: xMax,
            ticks: { color: '#a0aec0', callback: (v: any) => v.toFixed(1) + ' min' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Median cykeltid (minuter)', color: '#a0aec0', font: { size: 12 } }
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid: { color: 'rgba(255,255,255,0.04)' }
          }
        }
      }
    });
  }
}
