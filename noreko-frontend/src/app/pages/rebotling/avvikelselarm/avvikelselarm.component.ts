import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  AvvikelselarmService,
  OverviewData,
  LarmItem,
  HistorikData,
  Regel,
} from '../../../services/avvikelselarm.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

Chart.register(...registerables);

type PeriodKey = 'dag' | 'vecka' | 'manad';

const TYP_LABELS: Record<string, string> = {
  oee: 'OEE',
  kassation: 'Kassation',
  produktionstakt: 'Produktionstakt',
  maskinstopp: 'Maskinstopp',
  produktionsmal: 'Produktionsmal',
};

const GRAD_COLORS: Record<string, string> = {
  kritisk: '#fc8181',
  varning: '#f6ad55',
  info: '#63b3ed',
};

@Component({
  standalone: true,
  selector: 'app-avvikelselarm',
  templateUrl: './avvikelselarm.component.html',
  styleUrls: ['./avvikelselarm.component.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class AvvikelselarmPage implements OnInit, OnDestroy {

  // Tabs
  activeTab: 'dashboard' | 'historik' | 'regler' = 'dashboard';

  // Period
  period: PeriodKey = 'manad';
  readonly periodOptions: { key: PeriodKey; label: string }[] = [
    { key: 'dag',   label: 'Idag' },
    { key: 'vecka', label: 'Vecka' },
    { key: 'manad', label: 'Manad (30d)' },
  ];

  // Loading
  loadingOverview = false;
  loadingAktiva   = false;
  loadingHistorik = false;
  loadingRegler   = false;
  loadingTrend    = false;

  // Error states
  errorData = false;
  errorAktiva = false;
  errorHistorik = false;
  errorRegler = false;
  errorTrend = false;
  kvitteraError = '';

  // Data
  overview: OverviewData | null = null;
  aktivaLarm: LarmItem[] = [];
  historikData: HistorikData | null = null;
  regler: Regel[] = [];

  // Historik-filter
  filterTyp  = '';
  filterGrad = '';

  // Sortering
  sortColumn: keyof LarmItem = 'tidsstampel';
  sortAsc = false;

  // Cached sorted list (rebuilt on data/sort change)
  cachedSortedHistorik: LarmItem[] = [];

  // Kvittera dialog
  kvitteraLarm: LarmItem | null = null;
  kvitteraNamn = '';
  kvitteraKommentar = '';
  savingKvittera = false;

  // Charts
  private trendChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private svc: AvvikelselarmService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  setPeriod(p: PeriodKey): void {
    this.period = p;
    this.loadAll();
  }

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadOverview();
    this.loadAktiva();
    this.loadHistorik();
    this.loadRegler();
    this.loadTrend();
  }

  // ---- Data loaders ----

  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview().pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) { this.overview = res.data; }
        else { this.errorData = true; }
      },
      error: () => { this.loadingOverview = false; this.isFetching = false; this.errorData = true; }
    });
  }

  private loadAktiva(): void {
    this.loadingAktiva = true;
    this.errorAktiva = false;
    this.svc.getAktiva().pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingAktiva = false;
        if (res?.success) {
          this.aktivaLarm = res.data.larm;
        } else {
          this.errorAktiva = true;
        }
      },
      error: () => { this.loadingAktiva = false; this.errorAktiva = true; }
    });
  }

  loadHistorik(): void {
    this.loadingHistorik = true;
    this.errorHistorik = false;
    this.svc.getHistorik(this.period, this.filterTyp, this.filterGrad)
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingHistorik = false;
          if (res?.success) {
            this.historikData = res.data;
            this.rebuildSortedHistorik();
          } else {
            this.errorHistorik = true;
          }
        },
        error: () => { this.loadingHistorik = false; this.errorHistorik = true; }
      });
  }

  private loadRegler(): void {
    this.loadingRegler = true;
    this.errorRegler = false;
    this.svc.getRegler().pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingRegler = false;
        if (res?.success) {
          this.regler = res.data.regler;
        } else {
          this.errorRegler = true;
        }
      },
      error: () => { this.loadingRegler = false; this.errorRegler = true; }
    });
  }

  private loadTrend(): void {
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getTrend(this.period).pipe(timeout(15000), takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.loadingTrend = false;
        if (res?.success) {
          setTimeout(() => { if (!this.destroy$.closed) this.buildTrendChart(res.data.dates, res.data.series); }, 80);
        } else {
          this.errorTrend = true;
        }
      },
      error: () => { this.loadingTrend = false; this.errorTrend = true; }
    });
  }

  // ---- Chart ----

  private buildTrendChart(dates: string[], series: { allvarlighetsgrad: string; values: number[] }[]): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;

    const datasets = series.map(s => ({
      label: s.allvarlighetsgrad.charAt(0).toUpperCase() + s.allvarlighetsgrad.slice(1),
      data: s.values,
      backgroundColor: GRAD_COLORS[s.allvarlighetsgrad] || '#a0aec0',
      borderColor: GRAD_COLORS[s.allvarlighetsgrad] || '#a0aec0',
      borderWidth: 1,
      borderRadius: 3,
    }));

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'bar',
      data: { labels: dates, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => ` ${ctx.dataset.label}: ${ctx.parsed.y} larm`,
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            title: { display: true, text: 'Antal larm', color: '#a0aec0' },
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ---- Kvittera ----

  openKvittera(larm: LarmItem): void {
    this.kvitteraLarm = larm;
    this.kvitteraNamn = '';
    this.kvitteraKommentar = '';
  }

  closeKvittera(): void {
    this.kvitteraLarm = null;
  }

  submitKvittera(): void {
    if (!this.kvitteraLarm || !this.kvitteraNamn.trim()) return;
    this.savingKvittera = true;
    this.kvitteraError = '';
    this.svc.kvittera(this.kvitteraLarm.id, this.kvitteraNamn.trim(), this.kvitteraKommentar.trim())
      .pipe(timeout(15000), takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.savingKvittera = false;
          if (res?.success) {
            this.kvitteraLarm = null;
            this.kvitteraError = '';
            this.loadOverview();
            this.loadAktiva();
            this.loadHistorik();
          } else {
            this.kvitteraError = 'Kunde inte kvittera larmet';
          }
        },
        error: () => { this.savingKvittera = false; this.kvitteraError = 'Kunde inte kvittera larmet'; }
      });
  }

  // ---- Regler ----

  toggleRegel(regel: Regel): void {
    this.svc.uppdateraRegel(regel.id, undefined, !regel.aktiv)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          regel.aktiv = !regel.aktiv;
        }
      });
  }

  updateGrans(regel: Regel, event: Event): void {
    const val = parseFloat((event.target as HTMLInputElement).value);
    if (isNaN(val)) return;
    this.svc.uppdateraRegel(regel.id, val)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          regel.grans_varde = val;
        }
      });
  }

  // ---- Sortering ----

  sortBy(col: keyof LarmItem): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = false;
    }
    this.rebuildSortedHistorik();
  }

  private rebuildSortedHistorik(): void {
    if (!this.historikData?.larm) { this.cachedSortedHistorik = []; return; }
    const arr = [...this.historikData.larm];
    arr.sort((a, b) => {
      const va = a[this.sortColumn];
      const vb = b[this.sortColumn];
      if (va === null || va === undefined) return 1;
      if (vb === null || vb === undefined) return -1;
      if (va < vb) return this.sortAsc ? -1 : 1;
      if (va > vb) return this.sortAsc ? 1 : -1;
      return 0;
    });
    this.cachedSortedHistorik = arr;
  }

  // ---- Helpers ----

  getBadgeClass(grad: string): string {
    switch (grad) {
      case 'kritisk': return 'badge-kritisk';
      case 'varning': return 'badge-varning';
      case 'info':    return 'badge-info';
      default:        return 'bg-secondary';
    }
  }

  getTypLabel(typ: string): string {
    return TYP_LABELS[typ] || typ;
  }

  getEnhet(typ: string): string {
    switch (typ) {
      case 'oee':             return '%';
      case 'kassation':       return '%';
      case 'produktionstakt': return 'IBC/h';
      case 'maskinstopp':     return 'min';
      case 'produktionsmal':  return 'IBC';
      default:                return '';
    }
  }

  formatMinutes(min: number): string {
    if (min >= 60) {
      const h = Math.floor(min / 60);
      const m = Math.round(min % 60);
      return `${h}h ${m}min`;
    }
    return `${min.toFixed(0)} min`;
  }

  getSortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted';
    return this.sortAsc ? 'fas fa-sort-up text-info' : 'fas fa-sort-down text-info';
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
