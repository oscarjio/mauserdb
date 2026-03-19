import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  LeveransplaneringService,
  LeveransOverviewData,
  OrdrarData,
  KapacitetData,
  KundorderItem,
} from '../../../services/leveransplanering.service';
import { localToday } from '../../../utils/date-utils';

Chart.register(...registerables);

type PeriodKey = 'alla' | 'vecka' | 'manad';

@Component({
  standalone: true,
  selector: 'app-leveransplanering',
  templateUrl: './leveransplanering.component.html',
  styleUrls: ['./leveransplanering.component.css'],
  imports: [CommonModule, FormsModule],
})
export class LeveransplaneringPage implements OnInit, OnDestroy {

  // Filters
  filterStatus = 'alla';
  filterPeriod: PeriodKey = 'alla';
  readonly periodOptions: { key: PeriodKey; label: string }[] = [
    { key: 'alla',  label: 'Alla' },
    { key: 'vecka', label: 'Vecka' },
    { key: 'manad', label: 'Manad' },
  ];

  // Loading
  loading = false;
  loadingOverview = false;
  loadingOrdrar   = false;
  loadingKapacitet = false;

  // Errors
  errorOverview = false;
  errorOrdrar   = false;
  errorKapacitet = false;

  // Data
  overview:      LeveransOverviewData | null = null;
  ordrarData:    OrdrarData | null           = null;
  kapacitetData: KapacitetData | null        = null;

  // Table sorting
  sortColumn: keyof KundorderItem = 'prioritet';
  sortAsc = true;

  // Cached sorted list
  cachedSortedOrdrar: KundorderItem[] = [];

  // New order modal
  showNewOrderModal = false;
  savingOrder = false;
  newOrderError = '';
  newOrder = {
    kundnamn: '',
    antal_ibc: 50,
    bestallningsdatum: this.todayStr(),
    onskat_leveransdatum: '',
    prioritet: 5,
    notering: '',
  };

  // Charts
  private ganttChart:     Chart | null = null;
  private kapacitetChart: Chart | null = null;

  // Lifecycle
  private destroy$ = new Subject<void>();
  private refreshTimer: ReturnType<typeof setInterval> | null = null;
  private kapacitetChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: LeveransplaneringService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshTimer = setInterval(() => this.loadAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyCharts();
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
    if (this.kapacitetChartTimer !== null) { clearTimeout(this.kapacitetChartTimer); this.kapacitetChartTimer = null; }
  }

  // ---- Helpers ----

  private todayStr(): string {
    return localToday();
  }

  private destroyCharts(): void {
    try { this.ganttChart?.destroy(); } catch (_) {}
    try { this.kapacitetChart?.destroy(); } catch (_) {}
    this.ganttChart = null;
    this.kapacitetChart = null;
  }

  // ---- Filter changes ----

  onFilterChange(): void {
    this.loadOrdrar();
  }

  setFilterPeriod(p: PeriodKey): void {
    this.filterPeriod = p;
    this.loadOrdrar();
  }

  // ---- Load data ----

  loadAll(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.loadOverview();
    this.loadOrdrar();
    this.loadKapacitet();
  }

  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview().pipe(timeout(15000), catchError(() => { this.errorOverview = true; return of(null); }), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOverview = false;
      this.loading = false;
      this.isFetching = false;
      if (res?.success) {
        this.overview = res.data;
      } else {
        this.errorOverview = true;
      }
    });
  }

  private loadOrdrar(): void {
    this.loadingOrdrar = true;
    this.errorOrdrar = false;
    this.svc.getOrdrar(this.filterStatus, this.filterPeriod).pipe(timeout(15000), catchError(() => { this.errorOrdrar = true; return of(null); }), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOrdrar = false;
      if (res?.success) {
        this.ordrarData = res.data;
        this.rebuildSortedOrdrar();
      } else {
        this.errorOrdrar = true;
      }
    });
  }

  private loadKapacitet(): void {
    this.loadingKapacitet = true;
    this.errorKapacitet = false;
    this.svc.getKapacitet(30).pipe(timeout(15000), catchError(() => { this.errorKapacitet = true; return of(null); }), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingKapacitet = false;
      if (res?.success) {
        this.kapacitetData = res.data;
        if (this.kapacitetChartTimer !== null) { clearTimeout(this.kapacitetChartTimer); }
        this.kapacitetChartTimer = setTimeout(() => {
          if (!this.destroy$.closed) {
            this.buildGanttChart();
            this.buildKapacitetChart();
          }
        }, 80);
      } else {
        this.errorKapacitet = true;
      }
    });
  }

  // ---- Table sorting ----

  sortBy(col: keyof KundorderItem): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = true;
    }
    this.rebuildSortedOrdrar();
  }

  private rebuildSortedOrdrar(): void {
    if (!this.ordrarData?.ordrar) { this.cachedSortedOrdrar = []; return; }
    const arr = [...this.ordrarData.ordrar];
    arr.sort((a, b) => {
      const va = a[this.sortColumn];
      const vb = b[this.sortColumn];
      if (va === null || va === undefined) return 1;
      if (vb === null || vb === undefined) return -1;
      if (va < vb) return this.sortAsc ? -1 : 1;
      if (va > vb) return this.sortAsc ? 1 : -1;
      return 0;
    });
    this.cachedSortedOrdrar = arr;
  }

  getSortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted';
    return this.sortAsc ? 'fas fa-sort-up text-info' : 'fas fa-sort-down text-info';
  }

  // ---- Status helpers ----

  getStatusLabel(status: string): string {
    switch (status) {
      case 'planerad':       return 'Planerad';
      case 'i_produktion':   return 'I produktion';
      case 'levererad':      return 'Levererad';
      case 'forsenad':       return 'Forsenad';
      default:               return status;
    }
  }

  getPrioClass(prio: number): string {
    if (prio <= 2) return 'prio-high';
    if (prio <= 4) return 'prio-medium';
    return 'prio-low';
  }

  getLeveransgradColor(pct: number): string {
    if (pct >= 90) return '#68d391';
    if (pct >= 70) return '#f6ad55';
    return '#fc8181';
  }

  getKapacitetColor(pct: number): string {
    if (pct <= 70) return '#68d391';
    if (pct <= 90) return '#f6ad55';
    return '#fc8181';
  }

  // ---- Update order status ----

  updateStatus(id: number, status: string): void {
    this.svc.uppdateraOrder(id, status).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.loadAll();
      }
    });
  }

  // ---- New order ----

  submitNewOrder(): void {
    this.newOrderError = '';
    if (!this.newOrder.kundnamn.trim()) {
      this.newOrderError = 'Kundnamn kravs';
      return;
    }
    if (!this.newOrder.onskat_leveransdatum) {
      this.newOrderError = 'Onskat leveransdatum kravs';
      return;
    }

    this.savingOrder = true;
    this.svc.skapaOrder(this.newOrder).pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.savingOrder = false;
      if (res?.success) {
        this.showNewOrderModal = false;
        this.newOrder = {
          kundnamn: '',
          antal_ibc: 50,
          bestallningsdatum: this.todayStr(),
          onskat_leveransdatum: '',
          prioritet: 5,
          notering: '',
        };
        this.loadAll();
      } else {
        this.newOrderError = 'Kunde inte skapa order';
      }
    });
  }

  // ---- Charts ----

  private buildGanttChart(): void {
    try { this.ganttChart?.destroy(); } catch (_) {}
    this.ganttChart = null;

    if (!this.kapacitetData?.gantt?.length) return;
    const canvas = document.getElementById('ganttChart') as HTMLCanvasElement;
    if (!canvas) return;

    const gantt = this.kapacitetData.gantt;
    const labels = gantt.map(g => g.kundnamn);
    const today = new Date();
    const todayMs = today.getTime();

    // Bygg horisontella staplar: langd = antal dagar fran idag till beraknat leveransdatum
    const barData = gantt.map(g => {
      const slutMs = new Date(g.slut).getTime();
      const dagar = Math.max(1, Math.ceil((slutMs - todayMs) / 86400000));
      return dagar;
    });

    const deadlineData = gantt.map(g => {
      const deadMs = new Date(g.deadline).getTime();
      const dagar = Math.max(1, Math.ceil((deadMs - todayMs) / 86400000));
      return dagar;
    });

    const barColors = gantt.map(g =>
      g.forsenad ? 'rgba(252, 129, 129, 0.7)' : 'rgba(99, 179, 237, 0.7)'
    );
    const barBorders = gantt.map(g =>
      g.forsenad ? '#fc8181' : '#63b3ed'
    );

    this.ganttChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Beraknad leverans (dagar)',
            data: barData,
            backgroundColor: barColors,
            borderColor: barBorders,
            borderWidth: 1,
            borderRadius: 3,
          },
          {
            label: 'Deadline (dagar)',
            data: deadlineData,
            backgroundColor: 'rgba(160, 174, 192, 0.3)',
            borderColor: '#a0aec0',
            borderWidth: 1,
            borderRadius: 3,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              afterLabel: (ctx: any) => {
                const idx = ctx.dataIndex;
                if (gantt[idx]) {
                  return `${gantt[idx].antal_ibc} IBC | Prio: ${gantt[idx].prioritet}`;
                }
                return '';
              },
            },
          },
        },
        scales: {
          x: {
            title: { display: true, text: 'Dagar fran idag', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  private buildKapacitetChart(): void {
    try { this.kapacitetChart?.destroy(); } catch (_) {}
    this.kapacitetChart = null;

    if (!this.kapacitetData?.dates?.length) return;
    const canvas = document.getElementById('kapacitetChart') as HTMLCanvasElement;
    if (!canvas) return;

    const dates = this.kapacitetData.dates;
    const shortDates = dates.map(d => d.substring(5)); // MM-DD

    this.kapacitetChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: shortDates,
        datasets: [
          {
            label: 'Tillganglig kapacitet',
            data: this.kapacitetData.tillganglig,
            borderColor: '#68d391',
            backgroundColor: 'rgba(104, 211, 145, 0.1)',
            fill: true,
            borderWidth: 2,
            pointRadius: 1,
            pointHoverRadius: 4,
            tension: 0.2,
          },
          {
            label: 'Planerad produktion',
            data: this.kapacitetData.planerad,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.1)',
            fill: true,
            borderWidth: 2,
            pointRadius: 1,
            pointHoverRadius: 4,
            tension: 0.2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              title: (ctx: any) => {
                const idx = ctx[0]?.dataIndex;
                return idx !== undefined ? dates[idx] : '';
              },
              label: (ctx: any) => ` ${ctx.dataset.label}: ${ctx.parsed.y} IBC`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, maxTicksLimit: 15 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            title: { display: true, text: 'IBC / dag', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
        },
      },
    });
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
