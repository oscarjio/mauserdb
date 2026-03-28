import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  HistoriskProduktionService,
  HistoriskOverview,
  ProduktionPerPeriod,
  Jamforelse,
  DetaljTabell,
  DagligHistorikData,
  DagligHistorikRow,
} from '../../../services/historisk-produktion.service';
import { OperatorsService } from '../../../services/operators.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';
import { localToday, localDateStr } from '../../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-historisk-produktion',
  templateUrl: './historisk-produktion.component.html',
  styleUrls: ['./historisk-produktion.component.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class HistoriskProduktionPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview = false;
  loadingGraph    = false;
  loadingCompare  = false;
  loadingTable    = false;

  // Error states
  errorData = false;

  // Data
  overview: HistoriskOverview | null     = null;
  periodData: ProduktionPerPeriod | null = null;
  jamforelse: Jamforelse | null          = null;
  tabell: DetaljTabell | null            = null;

  // Filter
  selectedDays = 30;
  customFrom   = '';
  customTo     = '';
  useCustom    = false;

  // Table sorting
  tableSort  = 'date';
  tableOrder = 'DESC';
  tablePage  = 1;

  // Daglig historik (session #378)
  activeTab: 'overview' | 'daglig' = 'overview';
  dagligData: DagligHistorikData | null = null;
  loadingDaglig = false;
  dagligFrom = '';
  dagligTo = '';
  dagligOperator = '';
  dagligSort = 'datum';
  dagligOrder = 'desc';
  dagligPage = 1;
  operatorer: { operator_number: number; name: string }[] = [];

  // Charts
  private productionChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private productionChartTimer: ReturnType<typeof setTimeout> | null = null;
  private isFetching = false;

  constructor(private svc: HistoriskProduktionService, private opSvc: OperatorsService) {}

  ngOnInit(): void {
    const today = new Date();
    this.customTo   = localToday();
    this.customFrom = localDateStr(new Date(today.getTime() - 29 * 86400000));
    this.dagligTo   = localToday();
    this.dagligFrom = localDateStr(new Date(today.getTime() - 29 * 86400000));

    this.loadAll();
    this.loadOperatorer();
    this.refreshInterval = setInterval(() => this.loadOverview(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.productionChartTimer !== null) { clearTimeout(this.productionChartTimer); this.productionChartTimer = null; }
    if (this.productionChart) {
      this.productionChart.destroy();
      this.productionChart = null;
    }
  }

  // ---- Period selectors ----

  selectPeriod(days: number): void {
    this.selectedDays = days;
    this.useCustom = false;
    this.loadAll();
  }

  applyCustom(): void {
    if (this.customFrom && this.customTo) {
      this.useCustom = true;
      this.loadAll();
    }
  }

  private getDaysParam(): number | undefined {
    return this.useCustom ? undefined : this.selectedDays;
  }

  private getFrom(): string | undefined {
    return this.useCustom ? this.customFrom : undefined;
  }

  private getTo(): string | undefined {
    return this.useCustom ? this.customTo : undefined;
  }

  // ---- Load ----

  loadAll(): void {
    this.tablePage = 1;
    this.loadOverview();
    this.loadGraph();
    this.loadCompare();
    this.loadTable();
  }

  loadOverview(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview(this.getDaysParam(), this.getFrom(), this.getTo())
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) { this.overview = res.data; }
        else { this.errorData = true; }
      });
  }

  loadGraph(): void {
    this.loadingGraph = true;
    this.svc.getProduktionPerPeriod(this.getDaysParam(), this.getFrom(), this.getTo())
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingGraph = false;
        if (res?.success) {
          this.periodData = res.data;
          if (this.productionChartTimer !== null) { clearTimeout(this.productionChartTimer); }
          this.productionChartTimer = setTimeout(() => { if (!this.destroy$.closed) this.renderProductionChart(); }, 100);
        }
      });
  }

  loadCompare(): void {
    this.loadingCompare = true;
    this.svc.getJamforelse(this.getDaysParam(), this.getFrom(), this.getTo())
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingCompare = false;
        if (res?.success) this.jamforelse = res.data;
      });
  }

  loadTable(): void {
    this.loadingTable = true;
    this.svc.getDetaljTabell({
      days: this.getDaysParam(),
      from: this.getFrom(),
      to: this.getTo(),
      page: this.tablePage,
      per_page: 50,
      sort: this.tableSort,
      order: this.tableOrder,
    }).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingTable = false;
      if (res?.success) this.tabell = res.data;
    });
  }

  // ---- Table sort/page ----

  sortBy(col: string): void {
    if (this.tableSort === col) {
      this.tableOrder = this.tableOrder === 'ASC' ? 'DESC' : 'ASC';
    } else {
      this.tableSort = col;
      this.tableOrder = col === 'date' ? 'DESC' : 'DESC';
    }
    this.tablePage = 1;
    this.loadTable();
  }

  sortIcon(col: string): string {
    if (this.tableSort !== col) return 'fas fa-sort text-muted';
    return this.tableOrder === 'ASC' ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  goPage(page: number): void {
    if (page < 1 || (this.tabell && page > this.tabell.total_pages)) return;
    this.tablePage = page;
    this.loadTable();
  }

  // ---- Tab ----

  switchTab(tab: 'overview' | 'daglig'): void {
    this.activeTab = tab;
    if (tab === 'daglig' && !this.dagligData) {
      this.loadDaglig();
    }
  }

  // ---- Daglig historik (session #378) ----

  loadOperatorer(): void {
    this.opSvc.getOperators().pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe((res: any) => {
        if (res?.success && res.operators) {
          this.operatorer = res.operators.map((o: any) => ({
            operator_number: o.operator_number ?? o.number,
            name: o.name ?? o.operator_name ?? `Op ${o.operator_number ?? o.number}`,
          }));
        }
      });
  }

  loadDaglig(): void {
    this.loadingDaglig = true;
    this.svc.getDagligHistorik({
      from: this.dagligFrom || undefined,
      to: this.dagligTo || undefined,
      operator: this.dagligOperator || undefined,
      sort: this.dagligSort,
      order: this.dagligOrder,
      page: this.dagligPage,
      per_page: 25,
    }).pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDaglig = false;
        if (res?.success) {
          this.dagligData = res.data;
        }
      });
  }

  applyDagligFilter(): void {
    this.dagligPage = 1;
    this.loadDaglig();
  }

  dagligSortBy(col: string): void {
    if (this.dagligSort === col) {
      this.dagligOrder = this.dagligOrder === 'asc' ? 'desc' : 'asc';
    } else {
      this.dagligSort = col;
      this.dagligOrder = 'desc';
    }
    this.dagligPage = 1;
    this.loadDaglig();
  }

  dagligSortIcon(col: string): string {
    if (this.dagligSort !== col) return 'fas fa-sort text-muted';
    return this.dagligOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  goDagligPage(page: number): void {
    if (!this.dagligData) return;
    if (page < 1 || page > this.dagligData.pagination.total_pages) return;
    this.dagligPage = page;
    this.loadDaglig();
  }

  // ---- Chart ----

  renderProductionChart(): void {
    if (this.productionChart) {
      this.productionChart.destroy();
      this.productionChart = null;
    }
    const canvas = document.getElementById('historiskProductionChart') as HTMLCanvasElement | null;
    if (!canvas || !this.periodData || this.periodData.series.length === 0) return;

    const series = this.periodData.series;
    const labels = series.map(s => {
      if (this.periodData!.granularity === 'dag') {
        return s.label.substring(5); // MM-DD
      }
      return s.label;
    });
    const okData    = series.map(s => s.ibc_ok);
    const ejOkData  = series.map(s => s.ibc_ej_ok);
    const totalData = series.map(s => s.total);

    this.productionChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Totalt producerade',
            data: totalData,
            borderColor: '#4299e1',
            backgroundColor: '#4299e122',
            fill: true,
            tension: 0.3,
            pointRadius: series.length > 60 ? 0 : 3,
            pointHoverRadius: 5,
            borderWidth: 2,
          },
          {
            label: 'Godkända',
            data: okData,
            borderColor: '#48bb78',
            backgroundColor: '#48bb7822',
            fill: false,
            tension: 0.3,
            pointRadius: series.length > 60 ? 0 : 2,
            pointHoverRadius: 4,
            borderWidth: 1.5,
            borderDash: [4, 2],
          },
          {
            label: 'Kasserade',
            data: ejOkData,
            borderColor: '#fc8181',
            backgroundColor: '#fc818122',
            fill: false,
            tension: 0.3,
            pointRadius: series.length > 60 ? 0 : 2,
            pointHoverRadius: 4,
            borderWidth: 1.5,
            borderDash: [4, 2],
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
            intersect: false, mode: 'index',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            padding: 10,
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? 0;
                const s = series[idx];
                return s ? s.label : '';
              },
              label: (ctx: any) => {
                if (ctx.datasetIndex === 0) return ` Totalt: ${ctx.parsed.y} IBC`;
                if (ctx.datasetIndex === 1) return ` Godkända: ${ctx.parsed.y} IBC`;
                return ` Kasserade: ${ctx.parsed.y} IBC`;
              },
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx < 0 || idx >= series.length) return [];
                const s = series[idx];
                const pct = s.total > 0 ? ((s.ibc_ej_ok / s.total) * 100).toFixed(1) : '0.0';
                return [`Kassation: ${pct}%`];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxTicksLimit: 20 },
            grid: { color: '#4a556833' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  granularityLabel(): string {
    if (!this.periodData) return '';
    switch (this.periodData.granularity) {
      case 'dag':   return 'per dag';
      case 'vecka': return 'per vecka';
      case 'manad': return 'per månad';
      default:      return '';
    }
  }

  trendIcon(): string {
    if (!this.jamforelse) return 'fas fa-minus text-muted';
    switch (this.jamforelse.trend_direction) {
      case 'upp': return 'fas fa-arrow-up text-success';
      case 'ner': return 'fas fa-arrow-down text-danger';
      default:    return 'fas fa-minus text-muted';
    }
  }

  trendColor(): string {
    if (!this.jamforelse) return '#a0aec0';
    switch (this.jamforelse.trend_direction) {
      case 'upp': return '#48bb78';
      case 'ner': return '#fc8181';
      default:    return '#a0aec0';
    }
  }

  trendLabel(): string {
    if (!this.jamforelse) return 'Ingen data';
    const d = this.jamforelse.diff;
    const sign = d.total_pct > 0 ? '+' : '';
    switch (this.jamforelse.trend_direction) {
      case 'upp': return `${sign}${d.total_pct}% fler IBC än föregående period`;
      case 'ner': return `${sign}${d.total_pct}% färre IBC än föregående period`;
      default:    return 'Stabil produktion';
    }
  }

  periodLabel(days: number): string {
    switch (days) {
      case 7:   return '7 dagar';
      case 30:  return '30 dagar';
      case 90:  return '90 dagar';
      case 365: return '365 dagar';
      default:  return `${days} dagar`;
    }
  }

  formatDate(d: string): string {
    if (!d) return '-';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return d;
  }

  formatPct(val: number | null | undefined): string {
    if (val == null) return '-';
    return val.toFixed(1) + '%';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
