import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  RebotlingSammanfattningService,
  SammanfattningOverview,
  Produktion7dData,
  MaskinStatusData,
} from '../../../services/rebotling-sammanfattning.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-rebotling-sammanfattning',
  templateUrl: './rebotling-sammanfattning.component.html',
  styleUrls: ['./rebotling-sammanfattning.component.css'],
  imports: [CommonModule, RouterModule, PdfExportButtonComponent],
})
export class RebotlingSammanfattningPage implements OnInit, OnDestroy {

  // Loading / fetching guards
  loadingOverview = false;
  loadingGraph    = false;
  loadingMaskiner = false;
  private isFetchingOverview = false;
  private isFetchingGraph    = false;
  private isFetchingMaskiner = false;

  // Error states
  errorOverview = false;
  errorGraph    = false;
  errorMaskiner = false;

  // Data
  overview: SammanfattningOverview | null = null;
  produktion7d: Produktion7dData | null   = null;
  maskinStatus: MaskinStatusData | null   = null;

  // Charts
  private productionChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private chartTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: RebotlingSammanfattningService) {}

  ngOnInit(): void {
    this.loadAll();
    // Auto-refresh var 60:e sekund
    this.refreshInterval = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.chartTimer !== null) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    if (this.productionChart) {
      this.productionChart.destroy();
      this.productionChart = null;
    }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadProduktion7d();
    this.loadMaskinStatus();
  }

  loadOverview(): void {
    if (this.isFetchingOverview) return;
    this.isFetchingOverview = true;
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        this.isFetchingOverview = false;
        if (res?.success) {
          this.overview = res.data;
        } else {
          this.errorOverview = true;
        }
      });
  }

  loadProduktion7d(): void {
    if (this.isFetchingGraph) return;
    this.isFetchingGraph = true;
    this.loadingGraph = true;
    this.errorGraph = false;
    this.svc.getProduktion7d()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingGraph = false;
        this.isFetchingGraph = false;
        if (res?.success) {
          this.produktion7d = res.data;
          if (this.chartTimer !== null) { clearTimeout(this.chartTimer); }
          this.chartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderChart(); }, 100);
        } else {
          this.errorGraph = true;
        }
      });
  }

  loadMaskinStatus(): void {
    if (this.isFetchingMaskiner) return;
    this.isFetchingMaskiner = true;
    this.loadingMaskiner = true;
    this.errorMaskiner = false;
    this.svc.getMaskinStatus()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingMaskiner = false;
        this.isFetchingMaskiner = false;
        if (res?.success) {
          this.maskinStatus = res.data;
        } else {
          this.errorMaskiner = true;
        }
      });
  }

  // ---- Chart ----

  renderChart(): void {
    if (this.productionChart) {
      this.productionChart.destroy();
      this.productionChart = null;
    }
    const canvas = document.getElementById('sammanfattningChart') as HTMLCanvasElement | null;
    if (!canvas || !this.produktion7d || this.produktion7d.series.length === 0) return;

    const series = this.produktion7d.series;
    const labels   = series.map(s => s.label);
    const okData   = series.map(s => s.ibc_ok);
    const ejOkData = series.map(s => s.ibc_ej_ok);

    this.productionChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Godkanda',
            data: okData,
            backgroundColor: '#48bb78',
            borderRadius: 4,
            borderSkipped: false,
          },
          {
            label: 'Kasserade',
            data: ejOkData,
            backgroundColor: '#fc8181',
            borderRadius: 4,
            borderSkipped: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', padding: 12 },
          },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} IBC`,
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  statusColor(status: string): string {
    switch (status) {
      case 'gron': return '#48bb78';
      case 'gul':  return '#ecc94b';
      case 'rod':  return '#fc8181';
      default:     return '#a0aec0';
    }
  }

  statusLabel(status: string): string {
    switch (status) {
      case 'gron': return 'OK';
      case 'gul':  return 'Varning';
      case 'rod':  return 'Kritisk';
      default:     return 'Okänd';
    }
  }

  larmIcon(grad: string): string {
    switch (grad) {
      case 'kritisk': return 'fas fa-exclamation-circle';
      case 'varning': return 'fas fa-exclamation-triangle';
      case 'info':    return 'fas fa-info-circle';
      default:        return 'fas fa-bell';
    }
  }

  larmColor(grad: string): string {
    switch (grad) {
      case 'kritisk': return '#fc8181';
      case 'varning': return '#ecc94b';
      case 'info':    return '#63b3ed';
      default:        return '#a0aec0';
    }
  }

  formatTime(ts: string): string {
    if (!ts) return '-';
    // "2026-03-12 14:30:00" -> "14:30"
    const parts = ts.split(' ');
    if (parts.length >= 2) {
      return parts[1].substring(0, 5);
    }
    return ts;
  }

  formatDate(ts: string): string {
    if (!ts) return '-';
    const parts = ts.split(' ')[0].split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return ts;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
