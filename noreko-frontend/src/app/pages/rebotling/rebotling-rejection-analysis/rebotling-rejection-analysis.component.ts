import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService, RejectionAnalysisResponse, RejectionTrendDay, RejectionParetoItem } from '../../../services/rebotling.service';

@Component({
  standalone: true, selector: 'app-rebotling-rejection-analysis',
  templateUrl: './rebotling-rejection-analysis.component.html',
  imports: [CommonModule, FormsModule]
})
export class RebotlingRejectionAnalysisComponent implements OnInit, OnDestroy {
  rejectionAnalysisDays = 30;
  rejectionAnalysisLoading = false;
  rejectionAnalysisLoaded = false;
  rejectionKpi: {
    kvalitet_idag: number | null;
    kvalitet_vecka: number | null;
    kasserade_idag: number;
    trend_vs_forra_veckan: 'up' | 'down' | 'stable';
    trend_diff: number | null;
  } = { kvalitet_idag: null, kvalitet_vecka: null, kasserade_idag: 0, trend_vs_forra_veckan: 'stable', trend_diff: null };
  rejectionTrendData: RejectionTrendDay[] = [];
  rejectionParetoData: RejectionParetoItem[] = [];
  rejectionHasParetoData = false;
  rejectionTotalKassation = 0;
  private rejectionTrendChart: Chart | null = null;
  private rejectionParetoChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}
  ngOnInit() { this.loadRejectionAnalysis(); }
  ngOnDestroy() {
    try { this.rejectionTrendChart?.destroy(); } catch (e) {}
    try { this.rejectionParetoChart?.destroy(); } catch (e) {}
    this.rejectionTrendChart = null;
    this.rejectionParetoChart = null;
    this.destroy$.next(); this.destroy$.complete();
  }

  get rejectionTrendArrow(): string {
    const t = this.rejectionKpi.trend_vs_forra_veckan;
    return t === 'up' ? '\u2191' : t === 'down' ? '\u2193' : '\u2192';
  }

  get rejectionTrendColor(): string {
    const t = this.rejectionKpi.trend_vs_forra_veckan;
    return t === 'up' ? '#48bb78' : t === 'down' ? '#fc8181' : '#a0aec0';
  }

  get rejectionTrendLabel(): string {
    const t = this.rejectionKpi.trend_vs_forra_veckan;
    const diff = this.rejectionKpi.trend_diff;
    const diffStr = diff !== null ? (diff > 0 ? '+' + diff : '' + diff) + ' pp' : '';
    if (t === 'up') return `${this.rejectionTrendArrow} Stigande ${diffStr}`;
    if (t === 'down') return `${this.rejectionTrendArrow} Fallande ${diffStr}`;
    return `${this.rejectionTrendArrow} Stabil`;
  }

  onRejectionDaysChange(): void { this.loadRejectionAnalysis(); }

  loadRejectionAnalysis(): void {
    if (this.rejectionAnalysisLoading) return;
    this.rejectionAnalysisLoading = true;
    this.rejectionAnalysisLoaded = false;
    this.rebotlingService.getRejectionAnalysis(this.rejectionAnalysisDays).pipe(
      timeout(8000), takeUntil(this.destroy$), catchError(() => of(null))
    ).subscribe((res: RejectionAnalysisResponse | null) => {
      this.rejectionAnalysisLoading = false;
      this.rejectionAnalysisLoaded = true;
      if (res?.success) {
        this.rejectionKpi = res.kpi ?? {
          kvalitet_idag: null, kvalitet_vecka: null, kasserade_idag: 0,
          trend_vs_forra_veckan: 'stable', trend_diff: null
        };
        this.rejectionTrendData = res.trend ?? [];
        this.rejectionParetoData = res.pareto ?? [];
        this.rejectionHasParetoData = res.has_pareto_data ?? false;
        this.rejectionTotalKassation = res.total_kassation ?? 0;
        setTimeout(() => {
          this.renderRejectionTrendChart();
          this.renderRejectionParetoChart();
        }, 100);
      }
    });
  }

  exportRejectionCSV(): void {
    if (!this.rejectionTrendData.length) return;
    const headers = ['Datum', 'Kvalitet %', 'Glidande snitt %', 'IBC OK', 'IBC Kasserade', 'IBC Totalt'];
    const rows = this.rejectionTrendData.map(d => [
      d.datum,
      d.kvalitet_pct !== null ? d.kvalitet_pct.toFixed(1) : '',
      d.glidande_snitt !== null ? d.glidande_snitt.toFixed(1) : '',
      d.ibc_ok, d.ibc_kasserade, d.ibc_totalt
    ]);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `kvalitetsanalys-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click(); URL.revokeObjectURL(url);
  }

  private renderRejectionTrendChart(): void {
    try { this.rejectionTrendChart?.destroy(); } catch (e) {}
    this.rejectionTrendChart = null;
    const canvas = document.getElementById('rejectionTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.rejectionTrendData.length) return;

    const labels = this.rejectionTrendData.map(d => d.datum.substring(5));
    const dailyData = this.rejectionTrendData.map(d => d.kvalitet_pct);
    const rollingData = this.rejectionTrendData.map(d => d.glidande_snitt);
    const targetLine95 = this.rejectionTrendData.map(() => 95);
    const targetLine90 = this.rejectionTrendData.map(() => 90);

    this.rejectionTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Daglig kvalitet %', data: dailyData,
            borderColor: 'rgba(66, 153, 225, 0.9)', backgroundColor: 'rgba(66, 153, 225, 0.08)',
            tension: 0.3, pointRadius: 3, borderWidth: 2, fill: true, spanGaps: true, yAxisID: 'y' },
          { label: '7-dagars glidande snitt', data: rollingData,
            borderColor: 'rgba(72, 187, 120, 1)', backgroundColor: 'transparent',
            tension: 0.4, pointRadius: 1, borderWidth: 3, borderDash: [8, 4],
            fill: false, spanGaps: true, yAxisID: 'y' },
          { label: 'Mal 95%', data: targetLine95,
            borderColor: 'rgba(72, 187, 120, 0.6)', backgroundColor: 'transparent',
            borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false, spanGaps: true, yAxisID: 'y' },
          { label: 'Minimum 90%', data: targetLine90,
            borderColor: 'rgba(237, 137, 54, 0.7)', backgroundColor: 'transparent',
            borderDash: [4, 4], borderWidth: 1.5, pointRadius: 0, fill: false, spanGaps: true, yAxisID: 'y' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0',
            borderColor: '#4299e1', borderWidth: 1,
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.parsed.y;
                if (v === null) return '';
                const idx = ctx.dataIndex;
                if (ctx.datasetIndex === 0) {
                  const d = this.rejectionTrendData[idx];
                  return [`Kvalitet: ${v}%`, `OK: ${d?.ibc_ok ?? 0}  Kasserade: ${d?.ibc_kasserade ?? 0}`];
                }
                return `${ctx.dataset.label}: ${v}%`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 20 },
            grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { min: 0, max: 100, ticks: { color: '#718096', callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.06)' },
            title: { display: true, text: 'Kvalitet %', color: '#a0aec0', font: { size: 11 } } }
        }
      }
    });
  }

  private renderRejectionParetoChart(): void {
    try { this.rejectionParetoChart?.destroy(); } catch (e) {}
    this.rejectionParetoChart = null;
    const canvas = document.getElementById('rejectionParetoDeepChart') as HTMLCanvasElement;
    if (!canvas || !this.rejectionParetoData.length || !this.rejectionHasParetoData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.rejectionParetoData.map(p => p.namn);
    const values = this.rejectionParetoData.map(p => p.antal);
    const kumulativ = this.rejectionParetoData.map(p => p.kumulativ_pct);
    const maxVal = Math.max(...values, 1);

    this.rejectionParetoChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Antal kassationer', data: values,
            backgroundColor: values.map((v: number) => {
              const intensity = v / maxVal;
              if (intensity >= 0.8) return 'rgba(252, 129, 129, 0.85)';
              if (intensity >= 0.4) return 'rgba(237, 137, 54, 0.75)';
              return 'rgba(74, 85, 104, 0.7)';
            }),
            borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1, borderRadius: 4, yAxisID: 'y' },
          { label: 'Kumulativ %', data: kumulativ, type: 'line' as any,
            borderColor: '#ed8936', backgroundColor: 'transparent', borderWidth: 2,
            pointRadius: 4, pointBackgroundColor: '#ed8936', tension: 0.2, yAxisID: 'yRight' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                if (ctx.datasetIndex === 0) {
                  const item = this.rejectionParetoData[ctx.dataIndex];
                  const trendStr = item.trend === 'up' ? ' \u2191' : item.trend === 'down' ? ' \u2193' : '';
                  return [`Antal: ${item.antal}${trendStr}`, `Andel: ${item.pct}%`];
                }
                return `Kumulativ: ${ctx.parsed.x ?? ctx.parsed.y}%`;
              }
            }
          }
        },
        scales: {
          x: { beginAtZero: true, ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.06)' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.06)' } },
          yRight: { position: 'right' as const, min: 0, max: 100,
            ticks: { color: '#ed8936', callback: (v: any) => v + '%' },
            grid: { drawOnChartArea: false } }
        }
      }
    });
  }
}
