import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  TidrapportService,
  SammanfattningData,
  PerOperatorData,
  VeckodataData,
  DetaljerData,
} from '../../services/tidrapport.service';
import { parseLocalDate, localDateStr } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-tidrapport',
  templateUrl: './tidrapport.component.html',
  styleUrls: ['./tidrapport.component.css'],
  imports: [CommonModule, FormsModule],
})
export class TidrapportPage implements OnInit, OnDestroy {

  // Period
  period = '30d';
  readonly periodOptions = [
    { value: 'vecka',  label: 'Denna vecka' },
    { value: 'manad',  label: 'Denna månad' },
    { value: '30d',    label: 'Senaste 30d' },
  ];
  customFrom = '';
  customTo = '';

  // Loading
  sammanfattningLoading = false;
  sammanfattningError = false;
  operatorLoading = false;
  operatorError = false;
  veckoLoading = false;
  veckoError = false;
  detaljerLoading = false;
  detaljerError = false;

  // Data
  sammanfattning: SammanfattningData | null = null;
  operatorData: PerOperatorData | null = null;
  veckodata: VeckodataData | null = null;
  detaljerData: DetaljerData | null = null;

  // Filter
  filterOperatorId = 0;

  // Charts
  private veckoChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private svc: TidrapportService) {}

  ngOnInit(): void {
    // Initiera anpassade datum
    const today = new Date();
    const thirtyAgo = new Date(today);
    thirtyAgo.setDate(thirtyAgo.getDate() - 30);
    this.customTo = localDateStr(today);
    this.customFrom = localDateStr(thirtyAgo);

    this.loadAll();
    this.refreshTimer = setInterval(() => {
      if (!this.destroy$.closed) this.loadAll();
    }, 300000);
  }

  ngOnDestroy(): void {
    if (this.veckoChart) { this.veckoChart.destroy(); this.veckoChart = null as any; }
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    if (this.refreshTimer) { clearInterval(this.refreshTimer); this.refreshTimer = null; }
    try { this.veckoChart?.destroy(); } catch (_) {}
    this.veckoChart = null;
  }

  setPeriod(value: string): void {
    this.period = value;
    this.loadAll();
  }

  toggleAnpassat(): void {
    this.period = 'anpassat';
    this.loadAll();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadSammanfattning();
    this.loadPerOperator();
    this.loadVeckodata();
    this.loadDetaljer();
  }

  exportCsv(): void {
    const opId = this.filterOperatorId > 0 ? this.filterOperatorId : undefined;
    const from = this.period === 'anpassat' ? this.customFrom : undefined;
    const to = this.period === 'anpassat' ? this.customTo : undefined;
    const url = this.svc.getExportCsvUrl(this.period, from, to, opId);
    window.open(url, '_blank');
  }

  // ---- Formatering ----

  formatDatum(datum: string | null): string {
    if (!datum) return '-';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { day: 'numeric', month: 'short' });
  }

  formatTid(dt: string | null): string {
    if (!dt) return '-';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
  }

  skiftBadgeClass(typ: string): string {
    if (typ === 'formiddag') return 'badge-fm';
    if (typ === 'eftermiddag') return 'badge-em';
    return 'badge-natt';
  }

  skiftLabel(typ: string): string {
    if (typ === 'formiddag') return 'FM';
    if (typ === 'eftermiddag') return 'EM';
    return 'Natt';
  }

  // ---- Data-laddning ----

  private loadSammanfattning(): void {
    this.sammanfattningLoading = true;
    this.sammanfattningError = false;
    const from = this.period === 'anpassat' ? this.customFrom : undefined;
    const to = this.period === 'anpassat' ? this.customTo : undefined;

    this.svc.getSammanfattning(this.period, from, to)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.sammanfattningLoading = false;
        this.isFetching = false;
        if (res?.success) {
          this.sammanfattning = res.data;
        } else {
          this.sammanfattningError = true;
        }
      });
  }

  private loadPerOperator(): void {
    this.operatorLoading = true;
    this.operatorError = false;
    const from = this.period === 'anpassat' ? this.customFrom : undefined;
    const to = this.period === 'anpassat' ? this.customTo : undefined;

    this.svc.getPerOperator(this.period, from, to)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.operatorLoading = false;
        if (res?.success) {
          this.operatorData = res.data;
        } else {
          this.operatorError = true;
        }
      });
  }

  private loadVeckodata(): void {
    this.veckoLoading = true;
    this.veckoError = false;
    this.svc.getVeckodata(4)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.veckoLoading = false;
        if (res?.success) {
          this.veckodata = res.data;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderVeckoChart();
          }, 100);
        } else {
          this.veckoError = true;
        }
      });
  }

  loadDetaljer(): void {
    this.detaljerLoading = true;
    this.detaljerError = false;
    const from = this.period === 'anpassat' ? this.customFrom : undefined;
    const to = this.period === 'anpassat' ? this.customTo : undefined;
    const opId = this.filterOperatorId > 0 ? this.filterOperatorId : undefined;

    this.svc.getDetaljer(this.period, from, to, opId)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.detaljerLoading = false;
        if (res?.success) {
          this.detaljerData = res.data;
        } else {
          this.detaljerError = true;
        }
      });
  }

  // ---- Chart ----

  private renderVeckoChart(): void {
    try { this.veckoChart?.destroy(); } catch (_) {}
    this.veckoChart = null;

    const canvas = document.getElementById('tidrapportVeckoChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.veckodata) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const data = this.veckodata;
    const labels = data.dates.map(d => {
      const dt = parseLocalDate(d);
      return dt.toLocaleDateString('sv-SE', { day: 'numeric', month: 'numeric' });
    });

    const datasets = data.datasets.map(ds => ({
      label: ds.label,
      data: ds.data,
      backgroundColor: ds.color,
      borderRadius: 3,
      barPercentage: 0.8,
    }));

    if (this.veckoChart) { (this.veckoChart as any).destroy(); }
    this.veckoChart = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 }, usePointStyle: true },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (item) => {
                const v = item.raw as number;
                return ` ${item.dataset.label}: ${v}h`;
              },
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => val + 'h',
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'Timmar',
              color: '#8fa3b8',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? item?.user_id ?? index; }
}
