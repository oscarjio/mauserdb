import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-saglinje-statistik',
  imports: [CommonModule, FormsModule],
  templateUrl: './saglinje-statistik.html',
  styleUrl: './saglinje-statistik.css'
})
export class SaglinjeStatistikPage implements OnInit, AfterViewInit, OnDestroy {
  readonly line: LineName = 'saglinje';
  readonly accentColor = '#ffc107';
  readonly accentRgb = '255,193,7';

  @ViewChild('qualityChart') qualityChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('monthlyChart') monthlyChartRef!: ElementRef<HTMLCanvasElement>;

  reports: any[] = [];
  loading = false;
  errorMessage = '';

  // Period selection
  period: '30' | '90' | '365' | 'all' = '90';

  // KPIs
  totalOk = 0;
  totalEjOk = 0;
  totalProduced = 0;
  avgQuality = 0;
  bestQualityDay = '';
  bestQualityPct = 0;
  reportsCount = 0;

  private qualityChart: Chart | null = null;
  private monthlyChart: Chart | null = null;
  private chartTimer: any = null;
  private destroy$ = new Subject<void>();

  constructor(private service: LineSkiftrapportService) {}

  ngOnInit() {
    this.loadReports();
  }

  ngAfterViewInit() {}

  ngOnDestroy() {
    clearTimeout(this.chartTimer);
    this.qualityChart?.destroy();
    this.monthlyChart?.destroy();
    this.destroy$.next();
    this.destroy$.complete();
  }

  onPeriodChange() {
    this.buildCharts();
  }

  get filteredReports(): any[] {
    if (this.period === 'all') return [...this.reports];
    const days = parseInt(this.period, 10);
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - days);
    const cutoffStr = cutoff.toISOString().split('T')[0];
    return this.reports.filter(r => (r.datum || '').substring(0, 10) >= cutoffStr);
  }

  private loadReports() {
    this.loading = true;
    this.service.getReports(this.line).pipe(takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.reports = (res.data || []).sort((a: any, b: any) =>
            (a.datum || '').localeCompare(b.datum || '')
          );
          this.buildCharts();
        } else {
          this.errorMessage = res.message || 'Kunde inte ladda statistik';
        }
      },
      error: () => {
        this.loading = false;
        this.errorMessage = 'Serverfel vid hämtning av statistik';
      }
    });
  }

  private buildCharts() {
    this.computeKPIs();
    clearTimeout(this.chartTimer);
    this.chartTimer = setTimeout(() => {
      if (this.destroy$.closed) return;
      this.buildQualityChart();
      this.buildMonthlyChart();
    }, 100);
  }

  private computeKPIs() {
    const data = this.filteredReports;
    this.reportsCount = data.length;
    this.totalOk = data.reduce((s, r) => s + (r.antal_ok || 0), 0);
    this.totalEjOk = data.reduce((s, r) => s + (r.antal_ej_ok || 0), 0);
    this.totalProduced = this.totalOk + this.totalEjOk;
    this.avgQuality = this.totalProduced > 0
      ? Math.round((this.totalOk / this.totalProduced) * 100) : 0;

    let best: any = null;
    for (const r of data) {
      if (r.totalt > 0) {
        const q = Math.round((r.antal_ok / r.totalt) * 100);
        if (best === null || q > best.q) best = { q, datum: (r.datum || '').substring(0, 10) };
      }
    }
    this.bestQualityPct = best?.q ?? 0;
    this.bestQualityDay = best?.datum ?? '';
  }

  private buildQualityChart() {
    if (!this.qualityChartRef?.nativeElement) return;
    this.qualityChart?.destroy();
    const data = this.filteredReports;
    const labels = data.map(r => (r.datum || '').substring(0, 10));
    const qualities = data.map(r => r.totalt > 0 ? Math.round((r.antal_ok / r.totalt) * 100) : 0);
    const ctx = this.qualityChartRef.nativeElement.getContext('2d');
    if (!ctx) return;
    this.qualityChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Kvalitet %',
          data: qualities,
          borderColor: this.accentColor,
          backgroundColor: `rgba(${this.accentRgb},0.12)`,
          tension: 0.3,
          fill: true,
          pointRadius: data.length > 60 ? 0 : 3,
          borderWidth: 2
        }, {
          label: 'Mål 95%',
          data: labels.map(() => 95),
          borderColor: 'rgba(72,187,120,0.6)',
          borderDash: [6, 4],
          borderWidth: 1.5,
          pointRadius: 0,
          fill: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: { backgroundColor: 'rgba(20,20,20,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0' }
        },
        scales: {
          y: { min: 0, max: 100, ticks: { color: '#a0aec0', callback: (v: any) => v + '%' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          x: { ticks: { color: '#a0aec0', maxTicksLimit: 12, autoSkip: true }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
      }
    });
  }

  private buildMonthlyChart() {
    if (!this.monthlyChartRef?.nativeElement) return;
    this.monthlyChart?.destroy();
    const data = this.filteredReports;
    const grouped = new Map<string, { ok: number; ejOk: number }>();
    for (const r of data) {
      const key = (r.datum || '').substring(0, 7);
      if (!grouped.has(key)) grouped.set(key, { ok: 0, ejOk: 0 });
      const g = grouped.get(key)!;
      g.ok += r.antal_ok || 0;
      g.ejOk += r.antal_ej_ok || 0;
    }
    const sorted = Array.from(grouped.entries()).sort((a, b) => a[0].localeCompare(b[0]));
    const labels = sorted.map(([k]) => k);
    const okData = sorted.map(([, v]) => v.ok);
    const ejOkData = sorted.map(([, v]) => v.ejOk);
    const ctx = this.monthlyChartRef.nativeElement.getContext('2d');
    if (!ctx) return;
    this.monthlyChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Antal OK', data: okData, backgroundColor: 'rgba(72,187,120,0.7)', borderColor: '#48bb78', borderWidth: 1 },
          { label: 'Antal ej OK', data: ejOkData, backgroundColor: 'rgba(229,62,62,0.7)', borderColor: '#e53e3e', borderWidth: 1 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: { backgroundColor: 'rgba(20,20,20,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0' }
        },
        scales: {
          y: { beginAtZero: true, ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          x: { ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
      }
    });
  }

  getQualityClass(pct: number): string {
    if (pct >= 95) return 'text-success';
    if (pct >= 80) return 'text-warning';
    return 'text-danger';
  }

  exportCSV() {
    const data = this.filteredReports;
    if (data.length === 0) return;
    const header = ['Datum', 'Antal OK', 'Antal ej OK', 'Totalt', 'Kvalitet %', 'Kommentar', 'Användare'];
    const rows = data.map((r: any) => [
      (r.datum || '').substring(0, 10),
      r.antal_ok || 0,
      r.antal_ej_ok || 0,
      r.totalt || 0,
      r.totalt > 0 ? Math.round((r.antal_ok / r.totalt) * 100) : 0,
      r.kommentar || '',
      r.user_name || ''
    ]);
    const csvContent = [header, ...rows]
      .map(row => row.map((cell: any) => `"${String(cell).replace(/"/g, '""')}"`).join(';'))
      .join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `saglinje-statistik-${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    const data = this.filteredReports;
    if (data.length === 0) return;
    import('xlsx').then(XLSX => {
      const rows = data.map((r: any) => ({
        'Datum': (r.datum || '').substring(0, 10),
        'Antal OK': r.antal_ok || 0,
        'Antal ej OK': r.antal_ej_ok || 0,
        'Totalt': r.totalt || 0,
        'Kvalitet %': r.totalt > 0 ? Math.round((r.antal_ok / r.totalt) * 100) : 0,
        'Kommentar': r.kommentar || '',
        'Användare': r.user_name || ''
      }));
      const ws = XLSX.utils.json_to_sheet(rows);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Statistik');
      XLSX.writeFile(wb, `saglinje-statistik-${new Date().toISOString().split('T')[0]}.xlsx`);
    });
  }
}
