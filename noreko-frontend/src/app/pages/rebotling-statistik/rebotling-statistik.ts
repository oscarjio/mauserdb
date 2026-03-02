import { Component, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { SkiftrapportService } from '../../services/skiftrapport.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-rebotling-statistik',
  imports: [CommonModule, FormsModule],
  templateUrl: './rebotling-statistik.html',
  styleUrl: './rebotling-statistik.css'
})
export class RebotlingStatistikPage implements OnInit, OnDestroy {
  @ViewChild('qualityChart') qualityChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('monthlyChart') monthlyChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('productChart') productChartRef!: ElementRef<HTMLCanvasElement>;

  reports: any[] = [];
  loading = false;
  errorMessage = '';

  period: '30' | '90' | '365' | 'all' = '90';

  // KPIs
  avgQuality = 0;
  totalIbcOk = 0;
  totalKassation = 0;
  totalProduced = 0;
  reportsCount = 0;
  bestQualityDay = '';
  bestQualityPct = 0;

  private qualityChart: Chart | null = null;
  private monthlyChart: Chart | null = null;
  private productChart: Chart | null = null;
  private chartTimer: any = null;
  private destroy$ = new Subject<void>();

  constructor(private service: SkiftrapportService) {}

  ngOnInit() {
    this.loadReports();
  }

  ngOnDestroy() {
    clearTimeout(this.chartTimer);
    this.qualityChart?.destroy();
    this.monthlyChart?.destroy();
    this.productChart?.destroy();
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
    this.service.getSkiftrapporter().pipe(takeUntil(this.destroy$)).subscribe({
      next: (res: any) => {
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
      this.buildProductChart();
    }, 100);
  }

  private computeKPIs() {
    const data = this.filteredReports;
    this.reportsCount = data.length;
    this.totalIbcOk = data.reduce((s: number, r: any) => s + (r.ibc_ok || 0), 0);
    this.totalKassation = data.reduce((s: number, r: any) => s + (r.bur_ej_ok || 0) + (r.ibc_ej_ok || 0), 0);
    this.totalProduced = this.totalIbcOk + this.totalKassation;
    this.avgQuality = this.totalProduced > 0
      ? Math.round((this.totalIbcOk / this.totalProduced) * 100) : 0;

    let best: any = null;
    for (const r of data) {
      if (r.totalt > 0) {
        const q = Math.round((r.ibc_ok / r.totalt) * 100);
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
    const labels = data.map((r: any) => (r.datum || '').substring(0, 10));
    const qualities = data.map((r: any) => r.totalt > 0 ? Math.round((r.ibc_ok / r.totalt) * 100) : 0);
    const ctx = this.qualityChartRef.nativeElement.getContext('2d');
    if (!ctx) return;
    this.qualityChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Kvalitet %',
          data: qualities,
          borderColor: '#38b2ac',
          backgroundColor: 'rgba(56,178,172,0.12)',
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
    const grouped = new Map<string, { ibcOk: number; burEjOk: number; ibcEjOk: number }>();
    for (const r of data) {
      const key = (r.datum || '').substring(0, 7);
      if (!grouped.has(key)) grouped.set(key, { ibcOk: 0, burEjOk: 0, ibcEjOk: 0 });
      const g = grouped.get(key)!;
      g.ibcOk += r.ibc_ok || 0;
      g.burEjOk += r.bur_ej_ok || 0;
      g.ibcEjOk += r.ibc_ej_ok || 0;
    }
    const sorted = Array.from(grouped.entries()).sort((a, b) => a[0].localeCompare(b[0]));
    const labels = sorted.map(([k]) => k);
    const ctx = this.monthlyChartRef.nativeElement.getContext('2d');
    if (!ctx) return;
    this.monthlyChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'IBC OK', data: sorted.map(([, v]) => v.ibcOk), backgroundColor: 'rgba(72,187,120,0.75)', borderColor: '#48bb78', borderWidth: 1 },
          { label: 'Bur ej OK', data: sorted.map(([, v]) => v.burEjOk), backgroundColor: 'rgba(236,201,75,0.75)', borderColor: '#ecc94b', borderWidth: 1 },
          { label: 'IBC ej OK', data: sorted.map(([, v]) => v.ibcEjOk), backgroundColor: 'rgba(229,62,62,0.75)', borderColor: '#e53e3e', borderWidth: 1 }
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
          x: { stacked: true, ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: { stacked: true, beginAtZero: true, ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
      }
    });
  }

  private buildProductChart() {
    if (!this.productChartRef?.nativeElement) return;
    this.productChart?.destroy();
    const data = this.filteredReports;
    const grouped = new Map<string, number>();
    for (const r of data) {
      const key = r.product_name || 'Okänd produkt';
      grouped.set(key, (grouped.get(key) || 0) + (r.totalt || 0));
    }
    if (grouped.size === 0) return;
    const labels = Array.from(grouped.keys());
    const values = Array.from(grouped.values());
    const colors = ['rgba(56,178,172,0.8)', 'rgba(66,153,225,0.8)', 'rgba(159,122,234,0.8)', 'rgba(236,201,75,0.8)', 'rgba(245,101,101,0.8)'];
    const ctx = this.productChartRef.nativeElement.getContext('2d');
    if (!ctx) return;
    this.productChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data: values, backgroundColor: colors.slice(0, labels.length), borderColor: '#2d3748', borderWidth: 2 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { color: '#e2e8f0', padding: 12 } },
          tooltip: { backgroundColor: 'rgba(20,20,20,0.95)', titleColor: '#fff', bodyColor: '#e0e0e0' }
        }
      }
    });
  }

  getQualityClass(pct: number): string {
    if (pct >= 95) return 'text-success';
    if (pct >= 80) return 'text-warning';
    return 'text-danger';
  }

  exportExcel() {
    const data = this.filteredReports;
    if (data.length === 0) return;
    import('xlsx').then(XLSX => {
      const rows = data.map((r: any) => ({
        'Datum': (r.datum || '').substring(0, 10),
        'Produkt': r.product_name || '',
        'IBC OK': r.ibc_ok || 0,
        'Bur ej OK': r.bur_ej_ok || 0,
        'IBC ej OK': r.ibc_ej_ok || 0,
        'Totalt': r.totalt || 0,
        'Kvalitet %': r.totalt > 0 ? Math.round((r.ibc_ok / r.totalt) * 100) : 0,
        'Användare': r.user_name || ''
      }));
      const ws = XLSX.utils.json_to_sheet(rows);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Statistik');
      XLSX.writeFile(wb, `rebotling-statistik-${new Date().toISOString().split('T')[0]}.xlsx`);
    });
  }

  exportCSV() {
    const data = this.filteredReports;
    if (data.length === 0) return;
    const header = ['Datum', 'Produkt', 'IBC OK', 'Bur ej OK', 'IBC ej OK', 'Totalt', 'Kvalitet %', 'Användare'];
    const rows = data.map((r: any) => [
      (r.datum || '').substring(0, 10),
      r.product_name || '',
      r.ibc_ok || 0,
      r.bur_ej_ok || 0,
      r.ibc_ej_ok || 0,
      r.totalt || 0,
      r.totalt > 0 ? Math.round((r.ibc_ok / r.totalt) * 100) : 0,
      r.user_name || ''
    ]);
    const csv = [header, ...rows]
      .map(row => row.map((c: any) => `"${String(c).replace(/"/g, '""')}"`).join(';'))
      .join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rebotling-statistik-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
}
