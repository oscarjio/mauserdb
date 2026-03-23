import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { RebotlingService } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-produktionsrytm',
  templateUrl: './statistik-produktionsrytm.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikProduktionsrytmComponent implements OnInit, OnDestroy {
  hourlyRhythm: any[] = [];
  hourlyRhythmLoading = false;
  private hourlyRhythmChart: Chart | null = null;
  hourlyRhythmDays = 30;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  constructor(private rebotlingService: RebotlingService) {}
  ngOnInit() { this.loadHourlyRhythm(); }
  ngOnDestroy() { try { this.hourlyRhythmChart?.destroy(); } catch (e) {} this.hourlyRhythmChart = null; this.destroy$.next(); this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t)); }
  getHourlyRhythmMax(): number { return this.hourlyRhythm.length ? Math.max(...this.hourlyRhythm.map(h => h.avg_ibc_h)) : 0; }
  loadHourlyRhythm(): void {
    this.hourlyRhythmLoading = true;
    this.rebotlingService.getHourlyRhythm(this.hourlyRhythmDays).pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe(res => { this.hourlyRhythmLoading = false; if (res?.success) { this.hourlyRhythm = res.data; this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.buildHourlyRhythmChart(); }, 100)); } });
  }
  private buildHourlyRhythmChart(): void {
    try { this.hourlyRhythmChart?.destroy(); } catch (e) {} this.hourlyRhythmChart = null;
    const canvas = document.getElementById('hourlyRhythmChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.hourlyRhythm.length) return;
    const ctx = canvas.getContext('2d'); if (!ctx) return;
    const labels = this.hourlyRhythm.map(h => h.label); const values = this.hourlyRhythm.map(h => h.avg_ibc_h); const maxVal = Math.max(...values);
    if (this.hourlyRhythmChart) { (this.hourlyRhythmChart as any).destroy(); }
    this.hourlyRhythmChart = new Chart(ctx, { type: 'bar', data: { labels, datasets: [{ label: 'Snitt IBC/h', data: values,
      backgroundColor: values.map((v: number) => { if (maxVal === 0) return 'rgba(74,85,104,0.6)'; const intensity = v / maxVal; if (intensity >= 0.85) return 'rgba(72,187,120,0.8)'; if (intensity >= 0.6) return 'rgba(237,137,54,0.8)'; return 'rgba(252,129,129,0.8)'; }),
      borderWidth: 0, borderRadius: 4 }] },
      options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx: any) => { const d = this.hourlyRhythm[ctx.dataIndex]; return ['IBC/h: '+d.avg_ibc_h, 'Kvalitet: '+d.avg_kvalitet+'%', 'Dagar: '+d.antal_dagar]; } } } },
        scales: { x: { ticks: { color: '#a0aec0' }, grid: { color: '#4a5568' } }, y: { beginAtZero: true, ticks: { color: '#a0aec0' }, grid: { color: '#4a5568' }, title: { display: true, text: 'Snitt IBC/h', color: '#a0aec0' } } } } });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
