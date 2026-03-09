import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { Chart } from 'chart.js';
import { localToday } from '../../../../utils/date-utils';

@Component({
  standalone: true,
  selector: 'app-statistik-kassation-pareto',
  templateUrl: './statistik-kassation-pareto.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikKassationParetoComponent implements OnInit, OnDestroy {
  kassationDays: number = 30;
  kassationLoading: boolean = false;
  kassationPareto: { id: number; namn: string; antal: number; pct: number; kumulativ_pct: number; }[] = [];
  kassationTotalAntal: number = 0;
  kassationTotalProduktion: number = 0;
  kassationPct: number = 0;
  private kassationParetoChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  constructor(private http: HttpClient) {}
  ngOnInit() { this.loadKassationPareto(); }
  ngOnDestroy() { try { this.kassationParetoChart?.destroy(); } catch (e) {} this.kassationParetoChart = null; this.destroy$.next(); this.destroy$.complete(); }

  loadKassationPareto(): void {
    this.kassationLoading = true;
    this.http.get<any>('/noreko-backend/api.php?action=rebotling&run=kassation-pareto&days='+this.kassationDays, { withCredentials: true })
    .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe((res: any) => {
      this.kassationLoading = false;
      if (res?.success) { this.kassationPareto = res.pareto || []; this.kassationTotalAntal = res.total_kassation ?? 0; this.kassationTotalProduktion = res.total_produktion ?? 0; this.kassationPct = res.kassation_pct ?? 0;
        setTimeout(() => this.buildKassationParetoChart(), 0); }
    });
  }
  onKassationDaysChange(): void { this.loadKassationPareto(); }

  exportKassationCSV(): void {
    if (!this.kassationPareto || this.kassationPareto.length === 0) return;
    const headers = ['Orsak','Antal kassationer','Andel %','Kumulativ %'];
    const rows: any[] = this.kassationPareto.map(item => [item.namn, item.antal, item.pct.toFixed(1)+'%', item.kumulativ_pct.toFixed(1)+'%']);
    rows.push(['TOTALT', this.kassationTotalAntal, this.kassationPct.toFixed(1)+'%', '100.0%']);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF'+csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url;
    a.download = 'kassationsanalys-'+localToday()+'.csv'; a.click(); URL.revokeObjectURL(url);
  }

  private buildKassationParetoChart(): void {
    try { this.kassationParetoChart?.destroy(); } catch (e) {} this.kassationParetoChart = null;
    const canvas = document.getElementById('kassationParetoChart') as HTMLCanvasElement;
    if (!canvas || !this.kassationPareto.length) return;
    const ctx = canvas.getContext('2d'); if (!ctx) return;
    const labels = this.kassationPareto.map(p => p.namn); const values = this.kassationPareto.map(p => p.antal); const kumulativ = this.kassationPareto.map(p => p.kumulativ_pct); const maxVal = Math.max(...values, 1);
    this.kassationParetoChart = new Chart(ctx, { type: 'bar', data: { labels, datasets: [
      { label: 'Antal kassationer', data: values, backgroundColor: values.map((v: number) => { const intensity = v/maxVal; if (intensity >= 0.8) return 'rgba(252,129,129,0.85)'; if (intensity >= 0.4) return 'rgba(237,137,54,0.75)'; return 'rgba(74,85,104,0.7)'; }),
        borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1, borderRadius: 4, yAxisID: 'y' },
      { label: 'Kumulativ %', data: kumulativ, type: 'line' as any, borderColor: '#ed8936', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#ed8936', tension: 0.2, yAxisID: 'yRight' }
    ] }, options: { responsive: true, interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#e2e8f0' } }, tooltip: { callbacks: { label: (ctx: any) => { if (ctx.datasetIndex === 0) { const item = this.kassationPareto[ctx.dataIndex]; return ['Antal: '+item.antal, 'Andel: '+item.pct+'%']; } return 'Kumulativ: '+ctx.parsed.y+'%'; } } } },
      scales: { x: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.06)' } }, y: { beginAtZero: true, ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.06)' }, title: { display: true, text: 'Antal', color: '#a0aec0' } },
        yRight: { position: 'right', min: 0, max: 100, ticks: { color: '#ed8936', callback: (v: any) => v+'%' }, grid: { drawOnChartArea: false }, title: { display: true, text: 'Kumulativ %', color: '#ed8936' } } } },
    plugins: [{ id: 'kassation80Line', afterDraw(chart: any) { const yR = chart.scales['yRight']; const xAx = chart.scales['x']; if (!yR || !xAx) return; const y80 = yR.getPixelForValue(80); const c2 = chart.ctx; c2.save(); c2.beginPath(); c2.moveTo(xAx.left, y80); c2.lineTo(xAx.right, y80); c2.strokeStyle = '#e53e3e'; c2.lineWidth = 1.5; c2.setLineDash([6,4]); c2.stroke(); c2.setLineDash([]); c2.fillStyle = '#e53e3e'; c2.font = '11px sans-serif'; c2.fillText('80%', xAx.right-32, y80-5); c2.restore(); } }] });
  }
}
