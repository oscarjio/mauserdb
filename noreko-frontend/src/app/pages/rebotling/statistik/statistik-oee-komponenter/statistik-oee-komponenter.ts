import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { Chart } from 'chart.js';
import { localToday } from '../../../../utils/date-utils';
import { environment } from '../../../../../environments/environment';

interface OeeComponentDay { datum: string; tillganglighet: number | null; kvalitet: number | null; }

@Component({
  standalone: true,
  selector: 'app-statistik-oee-komponenter',
  templateUrl: './statistik-oee-komponenter.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikOeeKomponenterComponent implements OnInit, OnDestroy {
  oeeComponentsDays: number = 14;
  oeeComponentsLoading: boolean = false;
  oeeComponentsData: OeeComponentDay[] = [];
  private oeeComponentsChart: Chart | null = null;
  showTillganglighet: boolean = true;
  showKvalitet: boolean = true;
  private destroy$ = new Subject<void>();
  constructor(private http: HttpClient) {}
  ngOnInit() { this.loadOeeComponents(); }
  ngOnDestroy() { try { this.oeeComponentsChart?.destroy(); } catch (e) {} this.oeeComponentsChart = null; this.destroy$.next(); this.destroy$.complete(); }

  loadOeeComponents(): void {
    this.oeeComponentsLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=oee-components&days=`+this.oeeComponentsDays, { withCredentials: true })
    .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe(res => { this.oeeComponentsLoading = false; if (res?.success) { this.oeeComponentsData = res.data || []; this.buildOeeComponentsChart(); } });
  }

  buildOeeComponentsChart(): void {
    try { this.oeeComponentsChart?.destroy(); } catch (e) {}
    const canvas = document.getElementById('oeeComponentsChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.oeeComponentsData.length === 0) return;
    const ctx = canvas.getContext('2d'); if (!ctx) return;
    const labels = this.oeeComponentsData.map(d => { const p = d.datum.split('-'); return p[2]+'/'+p[1]; });
    if (this.oeeComponentsChart) { (this.oeeComponentsChart as any).destroy(); }
    this.oeeComponentsChart = new Chart(ctx, { type: 'line', data: { labels, datasets: [
      { label: 'Tillgänglighet %', data: this.oeeComponentsData.map(d => d.tillganglighet), borderColor: 'rgba(72,187,120,1)', backgroundColor: 'rgba(72,187,120,0.1)', borderWidth: 2, pointRadius: 3, fill: false, tension: 0.3, spanGaps: true },
      { label: 'Kvalitet %', data: this.oeeComponentsData.map(d => d.kvalitet), borderColor: 'rgba(99,179,237,1)', backgroundColor: 'rgba(99,179,237,0.1)', borderWidth: 2, pointRadius: 3, fill: false, tension: 0.3, spanGaps: true },
      { label: 'WCM 85%', data: this.oeeComponentsData.map(() => 85), borderColor: 'rgba(246,224,94,0.6)', borderWidth: 1.5, borderDash: [6,4], pointRadius: 0, fill: false, tension: 0 }
    ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#e2e8f0' } },
      tooltip: { backgroundColor: 'rgba(15,17,23,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0', borderColor: '#48bb78', borderWidth: 1, padding: 12,
        callbacks: { title: (items: any[]) => items.length ? `Datum: ${items[0].label}` : '', label: (item: any) => item.dataset.label+': '+(item.parsed.y != null ? item.parsed.y.toFixed(1) : '\u2014')+'%' } } },
      scales: { x: { ticks: { color: '#a0aec0', maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { min: 0, max: 100, ticks: { color: '#a0aec0', callback: (v) => v+'%' }, grid: { color: 'rgba(255,255,255,0.08)' } } } } });
  }

  toggleOeeDataset(type: 'tillganglighet' | 'kvalitet'): void {
    if (!this.oeeComponentsChart) return;
    const datasetIndex = type === 'tillganglighet' ? 0 : 1;
    const meta = this.oeeComponentsChart.getDatasetMeta(datasetIndex);
    meta.hidden = type === 'tillganglighet' ? !this.showTillganglighet : !this.showKvalitet;
    this.oeeComponentsChart.update();
  }

  exportOeeComponentsCSV(): void {
    if (!this.oeeComponentsData || this.oeeComponentsData.length === 0) return;
    const headers = ['Datum','Tillgänglighet %','Kvalitet %'];
    const rows = this.oeeComponentsData.map(d => [d.datum, d.tillganglighet != null ? d.tillganglighet.toFixed(1) : '', d.kvalitet != null ? d.kvalitet.toFixed(1) : '']);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF'+csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url;
    a.download = 'oee-komponenter-'+localToday()+'.csv'; a.click(); URL.revokeObjectURL(url);
  }
}
