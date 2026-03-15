import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  HistoriskProduktionService,
  HistoriskOverview,
  ProduktionPerPeriod,
  Jamforelse,
  DetaljTabell,
} from '../../../services/historisk-produktion.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

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

  // Charts
  private productionChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: HistoriskProduktionService) {}

  ngOnInit(): void {
    const today = new Date();
    this.customTo   = today.toISOString().substring(0, 10);
    this.customFrom = new Date(today.getTime() - 29 * 86400000).toISOString().substring(0, 10);

    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadOverview(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
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
    this.loadingOverview = true;
    this.svc.getOverview(this.getDaysParam(), this.getFrom(), this.getTo())
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) this.overview = res.data;
      });
  }

  loadGraph(): void {
    this.loadingGraph = true;
    this.svc.getProduktionPerPeriod(this.getDaysParam(), this.getFrom(), this.getTo())
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingGraph = false;
        if (res?.success) {
          this.periodData = res.data;
          setTimeout(() => this.renderProductionChart(), 100);
        }
      });
  }

  loadCompare(): void {
    this.loadingCompare = true;
    this.svc.getJamforelse(this.getDaysParam(), this.getFrom(), this.getTo())
      .pipe(takeUntil(this.destroy$))
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
    }).pipe(takeUntil(this.destroy$)).subscribe(res => {
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
            label: 'Godkanda',
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
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} IBC`,
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
      case 'manad': return 'per manad';
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
      case 'upp': return `${sign}${d.total_pct}% fler IBC an foregaende period`;
      case 'ner': return `${sign}${d.total_pct}% farre IBC an foregaende period`;
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
  trackByIndex(index: number): number { return index; }
}
