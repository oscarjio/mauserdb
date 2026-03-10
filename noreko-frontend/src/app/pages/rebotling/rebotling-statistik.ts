import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import { RebotlingService, ChartAnnotation, ExecDashboardResponse } from '../../services/rebotling.service';
import { localToday, localDateStr } from '../../utils/date-utils';
import { exportChartAsPng } from '../../shared/chart-export.util';
import { StatistikHistogramComponent } from './statistik/statistik-histogram/statistik-histogram';
import { StatistikSpcComponent } from './statistik/statistik-spc/statistik-spc';
import { StatistikCykeltidOperatorComponent } from './statistik/statistik-cykeltid-operator/statistik-cykeltid-operator';
import { StatistikKvalitetstrendComponent } from './statistik/statistik-kvalitetstrend/statistik-kvalitetstrend';
import { StatistikWaterfallOeeComponent } from './statistik/statistik-waterfall-oee/statistik-waterfall-oee';
import { StatistikVeckodagComponent } from './statistik/statistik-veckodag/statistik-veckodag';
import { StatistikProduktionsrytmComponent } from './statistik/statistik-produktionsrytm/statistik-produktionsrytm';
import { StatistikParetoStoppComponent } from './statistik/statistik-pareto-stopp/statistik-pareto-stopp';
import { StatistikKassationParetoComponent } from './statistik/statistik-kassation-pareto/statistik-kassation-pareto';
import { StatistikOeeKomponenterComponent } from './statistik/statistik-oee-komponenter/statistik-oee-komponenter';
import { StatistikKvalitetsanalysComponent } from './statistik/statistik-kvalitetsanalys/statistik-kvalitetsanalys';
import { StatistikHandelserComponent } from './statistik/statistik-handelser/statistik-handelser';
import { StatistikVeckojamforelseComponent } from './statistik/statistik-veckojamforelse/statistik-veckojamforelse';
import { StatistikPrediktionComponent } from './statistik/statistik-prediktion/statistik-prediktion';
import { StatistikOeeDeepdiveComponent } from './statistik/statistik-oee-deepdive/statistik-oee-deepdive';
import { StatistikCykeltrendComponent } from './statistik/statistik-cykeltrend/statistik-cykeltrend';
import { StatistikSkiftrapportOperatorComponent } from './statistik/statistik-skiftrapport-operator/statistik-skiftrapport-operator';
import { StatistikKvalitetDeepdiveComponent } from './statistik/statistik-kvalitet-deepdive/statistik-kvalitet-deepdive';
import { StatistikAnnotationerComponent } from './statistik/statistik-annotationer/statistik-annotationer';


Chart.register(...registerables);

// Custom plugin: vertikala annotationslinjer i Chart.js-grafer
const annotationPlugin = {
  id: 'verticalAnnotations',
  afterDraw(chart: any, _args: any, options: any) {
    if (!options.annotations?.length) return;
    const ctx = chart.ctx;
    const xAxis = chart.scales['x'];
    const yAxis = chart.scales['y'];
    if (!xAxis || !yAxis) return;

    options.annotations.forEach((ann: ChartAnnotation) => {
      const xIndex = (chart.data.labels as string[])?.findIndex(
        (l: string) => l.includes(ann.dateShort)
      );
      if (xIndex === undefined || xIndex < 0) return;
      const x = xAxis.getPixelForValue(xIndex);

      const color = (ann as any).color
                  ? (ann as any).color
                  : ann.type === 'stopp' ? '#e53e3e'
                  : ann.type === 'low_production' ? '#dd6b20'
                  : '#48bb78';

      ctx.save();
      ctx.beginPath();
      ctx.moveTo(x, yAxis.top);
      ctx.lineTo(x, yAxis.bottom);
      ctx.strokeStyle = color;
      ctx.lineWidth = 2;
      ctx.setLineDash([4, 4]);
      ctx.stroke();

      // Etikett ovanpå linjen
      ctx.fillStyle = color;
      ctx.font = '10px sans-serif';
      ctx.setLineDash([]);
      ctx.fillText(ann.label.substring(0, 20), x + 3, yAxis.top + 12);
      ctx.restore();
    });
  }
};
Chart.register(annotationPlugin);

interface OeeComponentDay {
  datum: string;
  tillganglighet: number | null;
  kvalitet: number | null;
}

type ViewMode = 'year' | 'month' | 'day' | 'heatmap';

interface PeriodCell {
  label: string;
  value: number;
  date: Date;
  cyclesCount: number;
  avgCycleTime: number;
  efficiency: number;
  isSelected: boolean;
  hasData: boolean;
}

interface TableRow {
  period: string;
  date: Date;
  cycles: number;
  avgCycleTime: number;
  efficiency: number;
  runtime: number;
  clickable: boolean;
}

@Component({
  standalone: true,
  selector: 'app-rebotling-statistik',
  templateUrl: './rebotling-statistik.html',
  styleUrls: ['./rebotling-statistik.css'],
  imports: [CommonModule, FormsModule,
    StatistikHistogramComponent, StatistikSpcComponent, StatistikCykeltidOperatorComponent,
    StatistikKvalitetstrendComponent, StatistikWaterfallOeeComponent, StatistikVeckodagComponent,
    StatistikProduktionsrytmComponent, StatistikParetoStoppComponent, StatistikKassationParetoComponent,
    StatistikOeeKomponenterComponent, StatistikKvalitetsanalysComponent, StatistikHandelserComponent,
    StatistikVeckojamforelseComponent, StatistikPrediktionComponent, StatistikOeeDeepdiveComponent, StatistikCykeltrendComponent,
    StatistikSkiftrapportOperatorComponent, StatistikKvalitetDeepdiveComponent,
    StatistikAnnotationerComponent]
})
export class RebotlingStatistikPage implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('productionChart') productionChartRef!: ElementRef<HTMLCanvasElement>;

  viewMode: ViewMode = 'month';
  currentYear: number = new Date().getFullYear();
  currentMonth: number = new Date().getMonth();
  selectedPeriods: Date[] = [];

  periodCells: PeriodCell[] = [];
  monthNames = ['Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];

  totalCycles: number = 0;
  avgCycleTime: number = 0;
  avgEfficiency: number = 0;
  totalRuntimeHours: number = 0;
  targetCycleTime: number = 0;

  productionChart: Chart | null = null;
  exportChartFeedback: boolean = false;
  tableData: TableRow[] = [];

  // Senaste hämtade statistik-data (används för zoom/val i grafen)
  private lastStatisticsData: any = null;
  // Markering i dags-grafen (10-minutersintervall, index 0-143)
  private chartSelectionStartIndex: number | null = null;
  private chartSelectionEndIndex: number | null = null;
  // Förhandsvisning medan man drar med musen
  private chartSelectionPreviewStartIndex: number | null = null;
  private chartSelectionPreviewEndIndex: number | null = null;

  loading: boolean = false;
  error: string | null = null;
  breadcrumb: string[] = [];

  totalRastMinutes: number = 0;
  timelineSegments: { startPct: number; widthPct: number; type: 'running' | 'rast' | 'stopped' }[] = [];
  shiftSummaries: { nr: number; ibcCount: number; avgCycleTime: number; rastMinutes: number }[] = [];

  // Dag-vy: längsta stopp (min) och utnyttjandegrad (%)
  dayLongestStopMinutes: number = 0;
  dayUtilizationPct: number = 0;

  // Spårar vilket fönster (start-slot-index i 144-slots-arrayen) som visas i dag-grafen
  // Används för att korrekt mappa chartSelection-index till absoluta slot-index i tabellen
  private chartWindowFrom: number = 0;

  /** I månadsvy: false = visa alla dagar, true = visa bara dagar med cykler */
  showOnlyDaysWithCycles: boolean = true;

  // Heatmap-vy
  heatmapDays: number = 30;
  // Custom datumintervall för heatmap
  heatmapCustomFrom: string = '';
  heatmapCustomTo: string = '';
  todayStr: string = localToday();
  heatmapUseCustomRange: boolean = false;
  heatmapRows: { date: string; label: string; counts: number[]; qualityPct: number[] }[] = [];
  heatmapHours: number[] = Array.from({ length: 18 }, (_, i) => i + 5); // 05–22
  heatmapMax: number = 1;
  heatmapQualityMax: number = 100;
  private isLoadingHeatmap = false;

  // KPI-val för heatmappen
  heatmapKpi: 'ibc' | 'quality' | 'oee' = 'ibc';

  // Tooltip-state
  heatmapTooltip: {
    visible: boolean;
    x: number;
    y: number;
    date: string;
    hour: number;
    count: number;
    ibcH: number;
    qualityPct: number;
  } = { visible: false, x: 0, y: 0, date: '', hour: 0, count: 0, ibcH: 0, qualityPct: 0 };

  isDragging: boolean = false;

  // Produktionsoverblick (VD-vy)
  overviewLoading: boolean = false;
  overviewData: {
    todayIbc: number;
    todayTarget: number;
    todayPct: number;
    todayForecast: number;
    oeeToday: number;
    oeeYesterday: number;
    ratePerH: number;
    thisWeekIbc: number;
    prevWeekIbc: number;
    weekDiffPct: number;
    qualityPct: number;
    days7: { date: string; ibc: number; target: number }[];
  } | null = null;

  private destroy$ = new Subject<void>();
  private chartUpdateTimer: any = null;

  constructor(
    private rebotlingService: RebotlingService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  @HostListener('document:mouseup')
  onDocumentMouseUp() {
    this.isDragging = false;
  }

  ngOnInit() {
    this.applyStateFromUrl();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
    this.loadOverview();
  }

  /** Läs vy, år, månad och valda datum från URL query params. */
  private applyStateFromUrl() {
    const q = this.route.snapshot.queryParams;
    const view = (q['view'] || 'month') as ViewMode;
    if (view === 'year' || view === 'month' || view === 'day') {
      this.viewMode = view;
    }
    const year = parseInt(q['year'], 10);
    if (!isNaN(year) && year >= 2000 && year <= 2100) {
      this.currentYear = year;
    }
    const month = parseInt(q['month'], 10);
    if (!isNaN(month) && month >= 0 && month <= 11) {
      this.currentMonth = month;
    }
    const datesStr = q['dates'];
    if (datesStr && typeof datesStr === 'string') {
      const parts = datesStr.split(',').map((s: string) => s.trim()).filter(Boolean);
      this.selectedPeriods = parts
        .map((s: string) => {
          const d = new Date(s + 'T00:00:00');
          return isNaN(d.getTime()) ? null : d;
        })
        .filter((d: Date | null): d is Date => d !== null);
      if (this.selectedPeriods.length > 0 && (isNaN(year) || isNaN(month))) {
        const first = this.selectedPeriods[0];
        this.currentYear = first.getFullYear();
        this.currentMonth = first.getMonth();
      }
    }
  }

  /** Uppdatera URL med nuvarande vy, år, månad och valda datum (ersätter inte history). */
  private syncStateToUrl() {
    const params: Record<string, string> = {
      view: this.viewMode,
      year: String(this.currentYear),
      month: String(this.currentMonth)
    };
    if (this.selectedPeriods.length > 0) {
      params['dates'] = this.selectedPeriods
        .map(d => this.formatDate(d))
        .join(',');
    } else {
      delete params['dates'];
    }
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: params,
      queryParamsHandling: 'merge',
      replaceUrl: true
    });
  }

  ngAfterViewInit() {}

  /** Ladda produktionsoverblick (VD-vy) fran exec-dashboard endpoint */
  loadOverview() {
    this.overviewLoading = true;
    this.rebotlingService.getExecDashboard().pipe(
      timeout(10000),
      catchError(() => {
        this.overviewLoading = false;
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe((res: ExecDashboardResponse | null) => {
      this.overviewLoading = false;
      if (!res?.success || !res.data) return;
      const d = res.data;
      this.overviewData = {
        todayIbc: d.today.ibc,
        todayTarget: d.today.target,
        todayPct: d.today.pct,
        todayForecast: d.today.forecast,
        oeeToday: d.today.oee_today,
        oeeYesterday: d.today.oee_yesterday,
        ratePerH: d.today.rate_per_h,
        thisWeekIbc: d.week.this_week_ibc,
        prevWeekIbc: d.week.prev_week_ibc,
        weekDiffPct: d.week.week_diff_pct,
        qualityPct: d.week.quality_pct,
        days7: d.days7 || []
      };
    });
  }

  /** Hjalpmetod: returnera trendpilklass baserad pa procent-differens */
  getTrendClass(diffPct: number): string {
    if (diffPct > 5) return 'trend-up';
    if (diffPct < -5) return 'trend-down';
    return 'trend-flat';
  }

  /** Hjalpmetod: returnera trendpil-ikon */
  getTrendIcon(diffPct: number): string {
    if (diffPct > 5) return 'fa-arrow-up';
    if (diffPct < -5) return 'fa-arrow-down';
    return 'fa-minus';
  }

  /** Returnera OEE-differens (idag vs igar) */
  getOeeDiff(): number {
    if (!this.overviewData) return 0;
    return Math.round((this.overviewData.oeeToday - this.overviewData.oeeYesterday) * 10) / 10;
  }

  /** Sparkline: max IBC under senaste 7 dagarna (for att skala SVG) */
  getSparklineMax(): number {
    if (!this.overviewData?.days7?.length) return 1;
    return Math.max(...this.overviewData.days7.map(d => d.ibc), 1);
  }

  /** Sparkline: generera SVG polyline-punkter */
  getSparklinePoints(): string {
    if (!this.overviewData?.days7?.length) return '';
    const days = this.overviewData.days7;
    const max = this.getSparklineMax();
    const w = 120;
    const h = 32;
    const step = w / Math.max(days.length - 1, 1);
    return days.map((d, i) => {
      const x = Math.round(i * step);
      const y = Math.round(h - (d.ibc / max) * (h - 4) - 2);
      return `${x},${y}`;
    }).join(' ');
  }

  ngOnDestroy() {
    clearTimeout(this.chartUpdateTimer);
    try {
      if (this.productionChart) {
        const canvas = this.productionChart.canvas;
        if (canvas) {
          canvas.onmousedown = null;
          canvas.onmouseup = null;
          canvas.onmousemove = null;
          canvas.ondblclick = null;
        }
        this.productionChart.destroy();
        this.productionChart = null;
      }
    } catch (e) { this.productionChart = null; }
    this.destroy$.next();
    this.destroy$.complete();
  }

  updateBreadcrumb() {
    this.breadcrumb = [];
    if (this.viewMode === 'year') {
      this.breadcrumb.push(`${this.currentYear}`);
    } else if (this.viewMode === 'month') {
      this.breadcrumb.push(`${this.currentYear}`, this.monthNames[this.currentMonth]);
    } else if (this.viewMode === 'day' && this.selectedPeriods.length > 0) {
      const date = this.selectedPeriods[0];
      this.breadcrumb.push(`${date.getFullYear()}`, this.monthNames[date.getMonth()], `${date.getDate()}`);
    }
  }

  navigateToYear() {
    this.viewMode = 'year';
    this.selectedPeriods = [];
    this.resetChartSelection();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
  }

  navigateToMonth(date?: Date) {
    this.viewMode = 'month';
    if (date) {
      this.currentYear = date.getFullYear();
      this.currentMonth = date.getMonth();
    }
    this.selectedPeriods = [];
    this.resetChartSelection();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
  }

  navigateToDay(date: Date) {
    this.viewMode = 'day';
    this.selectedPeriods = [date];
    this.currentYear = date.getFullYear();
    this.currentMonth = date.getMonth();
    this.resetChartSelection();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
  }

  navigatePrevious() {
    if (this.viewMode === 'year') {
      this.currentYear--;
    } else if (this.viewMode === 'month') {
      this.currentMonth--;
      if (this.currentMonth < 0) {
        this.currentMonth = 11;
        this.currentYear--;
      }
    } else if (this.viewMode === 'day' && this.selectedPeriods.length > 0) {
      const date = new Date(this.selectedPeriods[0]);
      date.setDate(date.getDate() - 1);
      this.selectedPeriods = [date];
    }

    this.resetChartSelection();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
  }

  navigateNext() {
    if (this.viewMode === 'year') {
      this.currentYear++;
    } else if (this.viewMode === 'month') {
      this.currentMonth++;
      if (this.currentMonth > 11) {
        this.currentMonth = 0;
        this.currentYear++;
      }
    } else if (this.viewMode === 'day' && this.selectedPeriods.length > 0) {
      const date = new Date(this.selectedPeriods[0]);
      date.setDate(date.getDate() + 1);
      this.selectedPeriods = [date];
    }

    this.resetChartSelection();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
  }

  generatePeriodCells() {
    this.periodCells = [];

    if (this.viewMode === 'year') {
      for (let month = 0; month < 12; month++) {
        const date = new Date(this.currentYear, month, 1);
        this.periodCells.push({
          label: this.monthNames[month].substring(0, 3),
          value: month,
          date: date,
          cyclesCount: 0,
          avgCycleTime: 0,
          efficiency: 0,
          isSelected: this.isDateSelected(date),
          hasData: false
        });
      }
    } else if (this.viewMode === 'month') {
      const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();

      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(this.currentYear, this.currentMonth, day);
        this.periodCells.push({
          label: `${day}`,
          value: day,
          date: date,
          cyclesCount: 0,
          avgCycleTime: 0,
          efficiency: 0,
          isSelected: this.isDateSelected(date),
          hasData: false
        });
      }
    } else if (this.viewMode === 'day' && this.selectedPeriods.length > 0) {
      const date = this.selectedPeriods[0];
      // Generate 10-minute intervals (6 per hour, 144 total)
      for (let hour = 0; hour < 24; hour++) {
        for (let minute = 0; minute < 60; minute += 10) {
          const intervalDate = new Date(date);
          intervalDate.setHours(hour, minute, 0, 0);

          this.periodCells.push({
            label: `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`,
            value: hour * 6 + minute / 10,
            date: intervalDate,
            cyclesCount: 0,
            avgCycleTime: 0,
            efficiency: 0,
            isSelected: false,
            hasData: false
          });
        }
      }
    }
  }

  onCellMouseDown(cell: PeriodCell, event: MouseEvent) {
    event.preventDefault();
    if (this.viewMode === 'day') return; // No selection in day view

    this.isDragging = true;
    this.toggleCellSelection(cell);
  }

  onCellMouseEnter(cell: PeriodCell) {
    if (this.isDragging && this.viewMode !== 'day') {
      this.toggleCellSelection(cell);
    }
  }

  toggleCellSelection(cell: PeriodCell) {
    const index = this.selectedPeriods.findIndex(d => d.getTime() === cell.date.getTime());

    if (index >= 0) {
      this.selectedPeriods.splice(index, 1);
      cell.isSelected = false;
    } else {
      this.selectedPeriods.push(cell.date);
      cell.isSelected = true;
    }
    this.syncStateToUrl();
  }

  clearSelection() {
    this.selectedPeriods = [];
    this.periodCells.forEach(cell => cell.isSelected = false);
    this.generatePeriodCells();
    this.resetChartSelection();
    this.syncStateToUrl();
  }

  showStatistics() {
    if (this.viewMode === 'year' && this.selectedPeriods.length === 1) {
      this.navigateToMonth(this.selectedPeriods[0]);
      return;
    }
    if (this.viewMode === 'month' && this.selectedPeriods.length === 1) {
      this.navigateToDay(this.selectedPeriods[0]);
      return;
    }
    if (this.viewMode === 'month' && this.selectedPeriods.length > 0) {
      this.generatePeriodCells();
      this.syncStateToUrl();
    }
    this.loadStatistics();
  }

  /** Celler som ska visas i kalendern (filtrerar bort dagar utan data i månadsvy om showOnlyDaysWithCycles) */
  getVisiblePeriodCells(): PeriodCell[] {
    if (this.viewMode !== 'month' || !this.showOnlyDaysWithCycles) {
      return this.periodCells;
    }
    const withData = this.periodCells.filter(cell => cell.hasData);
    return withData.length > 0 ? withData : this.periodCells;
  }

  private resetChartSelection() {
    this.chartSelectionStartIndex = null;
    this.chartSelectionEndIndex = null;
    this.chartSelectionPreviewStartIndex = null;
    this.chartSelectionPreviewEndIndex = null;
  }

  chartHasSelection(): boolean {
    return this.chartSelectionStartIndex !== null && this.chartSelectionEndIndex !== null;
  }

  resetChartZoom() {
    if (!this.lastStatisticsData) {
      this.resetChartSelection();
      return;
    }
    this.resetChartSelection();
    this.updateChart(this.lastStatisticsData);
    this.updateTable(this.lastStatisticsData);
  }

  onCellDoubleClick(cell: PeriodCell) {
    if (this.viewMode === 'year') {
      this.navigateToMonth(cell.date);
    } else if (this.viewMode === 'month') {
      this.navigateToDay(cell.date);
    }
  }

  isDateSelected(date: Date): boolean {
    return this.selectedPeriods.some(d =>
      d.getFullYear() === date.getFullYear() &&
      d.getMonth() === date.getMonth() &&
      (this.viewMode === 'year' || d.getDate() === date.getDate())
    );
  }


  loadStatistics() {
    this.loading = true;
    this.error = null;

    const { start, end } = this.getDateRange();

    this.rebotlingService.getStatistics(start, end).pipe(
      timeout(15000),
      catchError(() => {
        this.error = 'Kunde inte ladda statistik från backend';
        this.loading = false;
        return of({ success: false, data: null } as any);
      }),
      takeUntil(this.destroy$)
    ).subscribe(response => {
      if (!response || !response.success) { this.loading = false; return; }
      // Spara senaste data så vi kan göra zoom/markering i grafen utan att hämta om
      this.lastStatisticsData = response.data;
      this.updateStatistics(response.data);
      this.updateChart(response.data);
      this.updateTable(response.data);
      this.loading = false;
    });
  }

  getDateRange(): { start: string; end: string } {
    let start: Date;
    let end: Date;

    if (this.selectedPeriods.length > 0) {
      const dates = [...this.selectedPeriods].sort((a, b) => a.getTime() - b.getTime());
      start = new Date(dates[0]);
      end = new Date(dates[dates.length - 1]);

      if (this.viewMode === 'year') {
        start.setDate(1);
        start.setHours(0, 0, 0, 0);
        end.setMonth(end.getMonth() + 1);
        end.setDate(0);
        end.setHours(23, 59, 59, 999);
      } else if (this.viewMode === 'month') {
        start.setHours(0, 0, 0, 0);
        end.setHours(23, 59, 59, 999);
      } else if (this.viewMode === 'day') {
        start.setHours(0, 0, 0, 0);
        end.setHours(23, 59, 59, 999);
      }
    } else {
      if (this.viewMode === 'year') {
        start = new Date(this.currentYear, 0, 1);
        end = new Date(this.currentYear, 11, 31, 23, 59, 59);
      } else if (this.viewMode === 'month') {
        start = new Date(this.currentYear, this.currentMonth, 1);
        end = new Date(this.currentYear, this.currentMonth + 1, 0, 23, 59, 59);
      } else {
        start = new Date();
        start.setHours(0, 0, 0, 0);
        end = new Date();
        end.setHours(23, 59, 59, 999);
      }
    }

    return { start: this.formatDate(start), end: this.formatDate(end) };
  }

  /** YYYY-MM-DD i lokal tidszon (inte UTC) så att dagvy inte får fel dag/antal. */
  formatDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  updateStatistics(data: any) {
    this.totalCycles = data.summary.total_cycles;
    this.avgCycleTime = Math.round((data.summary.avg_cycle_time || 0) * 10) / 10;
    this.avgEfficiency = Math.round(data.summary.avg_production_percent || 0);
    this.totalRuntimeHours = Math.round(data.summary.total_runtime_hours * 10) / 10;
    this.targetCycleTime = data.summary.target_cycle_time || 0;
    this.totalRastMinutes = data.summary.total_rast_minutes || 0;

    this.buildTimelineSegments(data);
    this.buildShiftSummaries(data.cycles || []);
    this.computeDayMetrics(data);

    this.updatePeriodCellsData(data.cycles);
  }

  updatePeriodCellsData(cycles: any[]) {
    const grouped = new Map<string, any[]>();

    cycles.forEach(cycle => {
      const date = new Date(cycle.datum);
      let key: string;

      if (this.viewMode === 'year') {
        key = `${date.getFullYear()}-${date.getMonth()}`;
      } else if (this.viewMode === 'month') {
        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
      } else {
        // Group by 10-minute intervals
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${hour}-${minute}`;
      }

      if (!grouped.has(key)) {
        grouped.set(key, []);
      }
      grouped.get(key)!.push(cycle);
    });

    this.periodCells.forEach(cell => {
      let key: string;
      if (this.viewMode === 'year') {
        key = `${cell.date.getFullYear()}-${cell.date.getMonth()}`;
      } else if (this.viewMode === 'month') {
        key = `${cell.date.getFullYear()}-${cell.date.getMonth()}-${cell.date.getDate()}`;
      } else {
        // Group by 10-minute intervals
        const hour = cell.date.getHours();
        const minute = cell.date.getMinutes();
        key = `${cell.date.getFullYear()}-${cell.date.getMonth()}-${cell.date.getDate()}-${hour}-${minute}`;
      }

      const periodCycles = grouped.get(key) || [];
      cell.hasData = periodCycles.length > 0;
      cell.cyclesCount = periodCycles.length;

      if (periodCycles.length > 0) {
        // Filtrera bort NULL och 0 värden när vi beräknar genomsnitt
        const validCycleTimes = periodCycles
          .map(c => c.cycle_time)
          .filter(t => t !== null && t !== undefined && t > 0);

        const avgCycleTime = validCycleTimes.length > 0
          ? validCycleTimes.reduce((sum, t) => sum + t, 0) / validCycleTimes.length
          : 0;

        const avgEff = periodCycles.reduce((sum, c) => sum + (c.produktion_procent || 0), 0) / periodCycles.length;
        cell.avgCycleTime = Math.round(avgCycleTime * 10) / 10;
        cell.efficiency = Math.round(avgEff);
      }
    });

    // För dagvyn: Ta bort tomma intervall i början och slutet
    if (this.viewMode === 'day') {
      this.trimEmptyPeriods();
    }
  }

  trimEmptyPeriods() {
    // Hitta första och sista indexet med data
    let firstIndex = this.periodCells.findIndex(cell => cell.hasData);
    let lastIndex = -1;

    for (let i = this.periodCells.length - 1; i >= 0; i--) {
      if (this.periodCells[i].hasData) {
        lastIndex = i;
        break;
      }
    }

    // Om ingen data finns, behåll alla celler
    if (firstIndex === -1 || lastIndex === -1) {
      return;
    }

    // Lägg till lite marginal (3 intervaller = 30 min före och efter)
    const margin = 3;
    firstIndex = Math.max(0, firstIndex - margin);
    lastIndex = Math.min(this.periodCells.length - 1, lastIndex + margin);

    // Filtrera bort celler utanför intervallet
    this.periodCells = this.periodCells.slice(firstIndex, lastIndex + 1);
  }

  updateChart(data: any) {
    try {
      if (this.productionChart) {
        this.productionChart.destroy();
        this.productionChart = null;
      }
    } catch (e) { this.productionChart = null; }

    clearTimeout(this.chartUpdateTimer);
    this.chartUpdateTimer = setTimeout(() => {
      if (this.destroy$.closed) return;
      if (!this.productionChartRef?.nativeElement) {
        return;
      }

      const ctx = this.productionChartRef.nativeElement.getContext('2d');
      if (!ctx) return;

      const chartData = this.prepareChartData(data);

      if (chartData.labels.length === 0) {
        return;
      }

      this.createChart(ctx, chartData);
    }, 150);
  }

  prepareChartData(data: any) {
    const cycles = data.cycles || [];
    const onoff = data.onoff_events || [];

    const grouped = new Map<string, any>();

    // Initialize ALL periods first
    if (this.viewMode === 'day') {
      // Generate 10-minute intervals for day view
      for (let h = 0; h < 24; h++) {
        for (let m = 0; m < 60; m += 10) {
          const label = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
          grouped.set(label, {
            cycles: [],
            cycleTime: [],
            running: false
          });
        }
      }
    } else if (this.viewMode === 'month') {
      const useHourlyChart = this.selectedPeriods.length >= 2;

      if (useHourlyChart) {
        // Flera dagar valda: visa per timme för mer detalj
        const sorted = [...this.selectedPeriods].sort((a, b) => a.getTime() - b.getTime());
        const start = new Date(sorted[0]);
        start.setHours(0, 0, 0, 0);
        const end = new Date(sorted[sorted.length - 1]);
        end.setHours(23, 0, 0, 0);
        for (let t = new Date(start); t <= end; t.setHours(t.getHours() + 1)) {
          const y = t.getFullYear();
          const m = t.getMonth();
          const d = t.getDate();
          const h = t.getHours();
          const key = `${y}-${m}-${d}-${h}`;
          const label = `${d}/${m + 1} ${h.toString().padStart(2, '0')}`;
          grouped.set(key, { cycles: [], cycleTime: [], running: false, label });
        }
      } else {
        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        let daysToShow: number[];
        if (this.selectedPeriods.length > 0) {
          const daySet = new Set<number>();
          this.selectedPeriods.forEach(d => {
            if (d.getFullYear() === this.currentYear && d.getMonth() === this.currentMonth) {
              daySet.add(d.getDate());
            }
          });
          daysToShow = Array.from(daySet).sort((a, b) => a - b);
        } else {
          daysToShow = Array.from({ length: daysInMonth }, (_, i) => i + 1);
        }
        daysToShow.forEach(d => {
          grouped.set(`${d}`, { cycles: [], cycleTime: [], running: false });
        });
      }
    } else {
      for (let m = 0; m < 12; m++) {
        grouped.set(this.monthNames[m].substring(0, 3), {
          cycles: [],
          cycleTime: [],
          running: false
        });
      }
    }

    const monthViewHourly = this.viewMode === 'month' && this.selectedPeriods.length >= 2;

    // Add cycle data
    cycles.forEach((cycle: any) => {
      const date = new Date(cycle.datum);
      let key: string;

      if (this.viewMode === 'day') {
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        key = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
      } else if (this.viewMode === 'month') {
        key = monthViewHourly
          ? `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${date.getHours()}`
          : `${date.getDate()}`;
      } else {
        key = this.monthNames[date.getMonth()].substring(0, 3);
      }

      if (grouped.has(key)) {
        const group = grouped.get(key);
        group.cycles.push(cycle);

        // Parse and validate cycle_time - filtrera bort NULL och onormalt långa värden
        const cycleTimeValue = parseFloat(cycle.cycle_time);

        if (!isNaN(cycleTimeValue) && cycleTimeValue > 0 && cycleTimeValue <= 30) {
          group.cycleTime.push(cycleTimeValue);
        }
      }
    });

    // Add running status
    onoff.forEach((event: any) => {
      const date = new Date(event.datum);
      let key: string;

      if (this.viewMode === 'day') {
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        key = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
      } else if (this.viewMode === 'month') {
        key = monthViewHourly
          ? `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${date.getHours()}`
          : `${date.getDate()}`;
      } else {
        key = this.monthNames[date.getMonth()].substring(0, 3);
      }

      if (grouped.has(key) && event.running) {
        grouped.get(key).running = true;
      }
    });

    // If has cycles, must have been running
    grouped.forEach((value) => {
      if (value.cycles.length > 0 && !value.running) {
        value.running = true;
      }
    });

    // Build arrays (med stöd för zoom/markering i dag-vy)
    const labels: string[] = [];
    const cycleTime: number[] = [];
    const avgCycleTimeArr: number[] = [];
    const targetCycleTimeArr: number[] = [];

    let totalCycleTime = 0;
    let totalCount = 0;

    const entries = Array.from(grouped.entries());

    // --- Smart tidsfönster för dag-vy ---
    // Istället för att visa alla 144 slots (00:00-23:50) beräknar vi ett snävare
    // fönster baserat på var faktisk data finns, med 1 timmes marginal på varje sida.
    // Minimiintervall är alltid 06:00-18:00 (slot-index 36-108).
    let windowFrom = 0;
    let windowTo = entries.length - 1;
    this.chartWindowFrom = 0; // Reset, sätts nedan för dag-vy

    if (this.viewMode === 'day' && this.chartSelectionStartIndex === null) {
      // Slot-index: 00:00 = 0, 06:00 = 36, 18:00 = 108, 23:50 = 143
      const MIN_FROM = 36;  // 06:00
      const MIN_TO   = 108; // 18:00
      const PADDING  = 6;   // 1 timme = 6 slots à 10 min

      let firstDataSlot = -1;
      let lastDataSlot  = -1;

      entries.forEach(([key, value], idx) => {
        if (value.cycles.length > 0 || value.running) {
          if (firstDataSlot === -1) { firstDataSlot = idx; }
          lastDataSlot = idx;
        }
      });

      if (firstDataSlot !== -1) {
        // Utvidga med padding och tillämpa minimitider
        const paddedFrom = Math.max(0, firstDataSlot - PADDING);
        const paddedTo   = Math.min(entries.length - 1, lastDataSlot + PADDING);
        windowFrom = Math.min(paddedFrom, MIN_FROM);
        windowTo   = Math.max(paddedTo, MIN_TO);
      } else {
        // Ingen data alls: visa standardfönstret 06:00-18:00
        windowFrom = MIN_FROM;
        windowTo   = MIN_TO;
      }
      // Spara för updateTable
      this.chartWindowFrom = windowFrom;
    }

    // Om vi är i dagsvy och har en markering i grafen, begränsa till valt intervall
    // (markeringen är relativ till det redan fönster-begränsade label-indexet)
    let fromIndex = windowFrom;
    let toIndex   = windowTo;

    if (
      this.viewMode === 'day' &&
      this.chartSelectionStartIndex !== null &&
      this.chartSelectionEndIndex !== null &&
      entries.length > 0
    ) {
      const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      // chartSelection är relativt det visade fönstret
      fromIndex = Math.max(windowFrom, windowFrom + minSel);
      toIndex   = Math.min(windowTo, windowFrom + maxSel);
    }

    const slicedEntries = entries.slice(fromIndex, toIndex + 1);

    slicedEntries.forEach(([key, value]) => {
      labels.push((value as any).label !== undefined ? (value as any).label : key);

      let avgTime = 0;
      if (value.cycleTime.length > 0) {
        const sum = value.cycleTime.reduce((a: number, b: number) => a + b, 0);
        avgTime = sum / value.cycleTime.length;
      }

      cycleTime.push(Math.round(avgTime * 10) / 10);

      if (avgTime > 0) {
        totalCycleTime += avgTime * value.cycles.length;
        totalCount += value.cycles.length;
      }
    });

    const overallAvg = totalCount > 0 ? Math.round((totalCycleTime / totalCount) * 10) / 10 : 0;
    labels.forEach(() => {
      avgCycleTimeArr.push(overallAvg);
      targetCycleTimeArr.push(this.targetCycleTime);
    });

    // Build running periods for background colors (anpassat till ev. urklippt intervall)
    const runningPeriods: any[] = [];
    let currentPeriod: any = null;

    const slicedValues = slicedEntries.map(([, value]) => value);

    slicedValues.forEach((value, index) => {
      if (value.running && (!currentPeriod || !currentPeriod.running)) {
        if (currentPeriod) runningPeriods.push(currentPeriod);
        currentPeriod = { startIndex: index, endIndex: index, running: true };
      } else if (value.running && currentPeriod && currentPeriod.running) {
        currentPeriod.endIndex = index;
      } else if (!value.running && (!currentPeriod || currentPeriod.running)) {
        if (currentPeriod) runningPeriods.push(currentPeriod);
        currentPeriod = { startIndex: index, endIndex: index, running: false };
      } else if (!value.running && currentPeriod && !currentPeriod.running) {
        currentPeriod.endIndex = index;
      }
    });

    if (currentPeriod) runningPeriods.push(currentPeriod);

    // Bygg rastPeriods (startIndex/endIndex i labels-arrayen)
    const rast: any[] = data.rast_events || [];
    const rastPeriods: { startIndex: number; endIndex: number }[] = [];
    let rastStartKey: string | null = null;

    rast.forEach((ev: any) => {
      const d = new Date(ev.datum);
      const key = this.viewMode === 'day'
        ? `${d.getHours().toString().padStart(2, '0')}:${(Math.floor(d.getMinutes() / 10) * 10).toString().padStart(2, '0')}`
        : `${d.getDate()}`;
      if (ev.rast_status == 1) { rastStartKey = key; }
      else if (ev.rast_status == 0 && rastStartKey !== null) {
        const si = labels.indexOf(rastStartKey);
        const ei = labels.indexOf(key);
        if (si >= 0) rastPeriods.push({ startIndex: si, endIndex: Math.max(ei >= 0 ? ei : si, si) });
        rastStartKey = null;
      }
    });
    if (rastStartKey !== null) {
      const si = labels.indexOf(rastStartKey);
      if (si >= 0) rastPeriods.push({ startIndex: si, endIndex: labels.length - 1 });
    }

    return { labels, cycleTime, avgCycleTime: avgCycleTimeArr, targetCycleTime: targetCycleTimeArr, runningPeriods, rastPeriods };
  }

  createChart(ctx: CanvasRenderingContext2D, chartData: any) {
    try {
      const datasets: any[] = [
        {
          label: 'Cykeltid (min)',
          data: chartData.cycleTime,
          borderColor: '#00d4ff',
          backgroundColor: 'rgba(0, 212, 255, 0.1)',
          tension: 0.4,
          fill: true,
          yAxisID: 'y',
          pointRadius: this.viewMode === 'day' ? 2 : 3,
          pointHoverRadius: 6,
          borderWidth: 2
        },
        {
          label: 'Snitt Cykeltid',
          data: chartData.avgCycleTime,
          borderColor: '#ffc107',
          borderDash: [8, 4],
          tension: 0,
          fill: false,
          yAxisID: 'y',
          pointRadius: 0,
          borderWidth: 2
        }
      ];

      // Add target line if target exists
      if (this.targetCycleTime > 0) {
        datasets.push({
          label: 'Mål Cykeltid',
          data: chartData.targetCycleTime,
          borderColor: '#ff8800',
          borderDash: [4, 4],
          tension: 0,
          fill: false,
          yAxisID: 'y',
          pointRadius: 0,
          borderWidth: 2.5
        });
      }

      this.productionChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: chartData.labels,
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              display: true,
              position: 'top',
              labels: { color: '#e0e0e0', font: { size: 13, weight: 'bold' } }
            },
            tooltip: {
              backgroundColor: 'rgba(20, 20, 20, 0.95)',
              titleColor: '#fff',
              bodyColor: '#e0e0e0',
              borderColor: '#00d4ff',
              borderWidth: 1,
              padding: 12,
              displayColors: true
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: 'Cykeltid (minuter)', color: '#e0e0e0', font: { size: 13 } },
              ticks: { color: '#a0a0a0' },
              grid: { color: 'rgba(255, 255, 255, 0.05)' }
            },
            x: {
              ticks: {
                color: '#a0a0a0',
                maxRotation: 45,
                minRotation: 0,
                autoSkip: true,
                maxTicksLimit: this.viewMode === 'day' ? 24 : undefined
              },
              grid: { color: 'rgba(255, 255, 255, 0.05)' }
            }
          }
        },
        plugins: [{
          id: 'backgroundColors',
          beforeDatasetsDraw: (chart: any) => {
            const { ctx, chartArea, scales } = chart;
            if (!chartArea || !scales.x) return;

            const { left, right, top, bottom } = chartArea;

            chartData.runningPeriods.forEach((period: any) => {
              try {
                const xStart = scales.x.getPixelForValue(period.startIndex);
                const xEnd = scales.x.getPixelForValue(period.endIndex + 1);

                ctx.fillStyle = period.running
                  ? 'rgba(34, 139, 34, 0.25)'
                  : 'rgba(220, 53, 69, 0.25)';
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
              } catch (e) {}
            });

            // Rita rast (gul) ovanpå kör/stopp-bakgrunden
            (chartData.rastPeriods || []).forEach((period: any) => {
              try {
                const xStart = scales.x.getPixelForValue(period.startIndex);
                const xEnd   = scales.x.getPixelForValue(period.endIndex + 1);
                ctx.fillStyle = 'rgba(255, 193, 7, 0.42)';
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
                // Övre kant-linje för synlighet
                ctx.strokeStyle = 'rgba(255, 193, 7, 0.85)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(xStart, top);
                ctx.lineTo(xEnd, top);
                ctx.stroke();
              } catch (e) {}
            });

            // Rita förhandsvisning av markerat intervall i ljusblått när man drar i dags-vy
            if (
              this.viewMode === 'day' &&
              this.chartSelectionPreviewStartIndex !== null &&
              this.chartSelectionPreviewEndIndex !== null
            ) {
              try {
                const minSel = Math.min(this.chartSelectionPreviewStartIndex, this.chartSelectionPreviewEndIndex);
                const maxSel = Math.max(this.chartSelectionPreviewStartIndex, this.chartSelectionPreviewEndIndex);
                const selStart = Math.max(0, minSel);
                const selEnd = maxSel + 1;

                const xStart = scales.x.getPixelForValue(selStart);
                const xEnd = scales.x.getPixelForValue(selEnd);

                ctx.fillStyle = 'rgba(0, 153, 255, 0.18)'; // ljusblå, transparent
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
              } catch (e) {}
            }
          }
        }]
      });

      // Aktivera interaktiv markering/zoom i grafen för dag-vy
      this.attachChartSelectionHandlers(this.productionChart);

    } catch (error) {
      // Silently handle chart creation error — UI will show empty chart area
    }
  }

  private attachChartSelectionHandlers(chart: Chart) {
    const canvas = chart.canvas as HTMLCanvasElement | null;
    if (!canvas) {
      return;
    }

    let isSelecting = false;
    let startIndex: number | null = null;

    const getIndexFromEvent = (event: MouseEvent): number | null => {
      const rect = canvas.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const xScale: any = chart.scales['x'];
      if (!xScale) return null;

      const value = xScale.getValueForPixel(x);
      if (typeof value === 'number') {
        return Math.round(value);
      }
      // För kategorisk skala kan value ibland vara label, då försöker vi slå upp indexet
      if (typeof value === 'string' && Array.isArray(chart.data.labels)) {
        const idx = (chart.data.labels as string[]).indexOf(value);
        return idx >= 0 ? idx : null;
      }
      return null;
    };

    canvas.onmousedown = (event: MouseEvent) => {
      if (this.viewMode !== 'day') return;
      if (!this.lastStatisticsData) return;

      const idx = getIndexFromEvent(event);
      if (idx === null) return;

      isSelecting = true;
      startIndex = idx;
      this.chartSelectionPreviewStartIndex = idx;
      this.chartSelectionPreviewEndIndex = idx;
      chart.update('none');
    };

    canvas.onmouseup = (event: MouseEvent) => {
      if (!isSelecting) return;
      isSelecting = false;
      if (this.viewMode !== 'day') return;
      if (!this.lastStatisticsData) return;

      const endIndex = getIndexFromEvent(event);
      if (startIndex === null || endIndex === null) return;

      this.chartSelectionStartIndex = startIndex;
      this.chartSelectionEndIndex = endIndex;
      this.chartSelectionPreviewStartIndex = null;
      this.chartSelectionPreviewEndIndex = null;

      // Rita om graf och tabell för endast valt intervall
      this.updateChart(this.lastStatisticsData);
      this.updateTable(this.lastStatisticsData);
    };

    // Dubbelklick på grafen nollställer markeringen (visar hela dagen igen)
    canvas.ondblclick = () => {
      if (!this.lastStatisticsData) return;
      this.resetChartSelection();
      this.updateChart(this.lastStatisticsData);
      this.updateTable(this.lastStatisticsData);
    };

    canvas.onmousemove = (event: MouseEvent) => {
      if (!isSelecting) return;
      if (this.viewMode !== 'day') return;
      if (!this.lastStatisticsData) return;

      const idx = getIndexFromEvent(event);
      if (idx === null) return;

      this.chartSelectionPreviewEndIndex = idx;
      chart.update('none');
    };
  }

  private buildTimelineSegments(data: any) {
    if (this.viewMode !== 'day') { this.timelineSegments = []; return; }
    const dayEnd = 1440;
    const segments: typeof this.timelineSegments = [];
    const onoff: any[] = data.onoff_events || [];
    const rast: any[] = data.rast_events || [];

    const events: { min: number; type: 'run_start' | 'run_end' | 'rast_start' | 'rast_end' }[] = [];
    onoff.forEach((e: any) => {
      const d = new Date(e.datum);
      const min = d.getHours() * 60 + d.getMinutes();
      events.push({ min, type: e.running ? 'run_start' : 'run_end' });
    });
    rast.forEach((e: any) => {
      const d = new Date(e.datum);
      const min = d.getHours() * 60 + d.getMinutes();
      events.push({ min, type: e.rast_status == 1 ? 'rast_start' : 'rast_end' });
    });
    events.sort((a, b) => a.min - b.min);

    let running = false, onRast = false, lastMin = 0;
    const push = (end: number, r: boolean, rs: boolean) => {
      if (end > lastMin) {
        const type: 'running' | 'rast' | 'stopped' = rs ? 'rast' : r ? 'running' : 'stopped';
        segments.push({ startPct: lastMin / 14.4, widthPct: (end - lastMin) / 14.4, type });
      }
    };
    for (const ev of events) {
      push(ev.min, running, onRast);
      lastMin = ev.min;
      if (ev.type === 'run_start') running = true;
      else if (ev.type === 'run_end') running = false;
      else if (ev.type === 'rast_start') onRast = true;
      else if (ev.type === 'rast_end') onRast = false;
    }
    push(dayEnd, running, onRast);
    this.timelineSegments = segments;
  }

  private buildShiftSummaries(cycles: any[]) {
    if (this.viewMode !== 'day') { this.shiftSummaries = []; return; }
    const map = new Map<number, { ibcCount: number; times: number[]; rastMin: number }>();
    cycles.forEach((c: any) => {
      if (!c.skiftraknare) return;
      if (!map.has(c.skiftraknare)) map.set(c.skiftraknare, { ibcCount: 0, times: [], rastMin: 0 });
      const s = map.get(c.skiftraknare)!;
      s.ibcCount += (c.ibc_count || 1);
      if (c.cycle_time != null && c.cycle_time > 0 && c.cycle_time <= 30) s.times.push(parseFloat(c.cycle_time));
    });
    this.shiftSummaries = Array.from(map.entries())
      .sort((a, b) => a[0] - b[0])
      .map(([nr, s]) => ({
        nr,
        ibcCount: s.ibcCount,
        avgCycleTime: s.times.length ? Math.round(s.times.reduce((a, b) => a + b, 0) / s.times.length * 10) / 10 : 0,
        rastMinutes: 0
      }));
  }

  /**
   * Beräknar dag-specifika nyckeltal: längsta stopp och utnyttjandegrad.
   * Körs bara i dag-vy; nollställer annars.
   */
  private computeDayMetrics(data: any) {
    if (this.viewMode !== 'day') {
      this.dayLongestStopMinutes = 0;
      this.dayUtilizationPct = 0;
      return;
    }

    const onoff: any[] = data.onoff_events || [];
    if (onoff.length === 0) {
      this.dayLongestStopMinutes = 0;
      this.dayUtilizationPct = 0;
      return;
    }

    // Sortera händelser kronologiskt
    const events = onoff
      .map((e: any) => ({ min: new Date(e.datum).getHours() * 60 + new Date(e.datum).getMinutes(), running: !!e.running }))
      .sort((a: any, b: any) => a.min - b.min);

    // Hitta tidsspannet som maskinen var aktiv (från första start till sista stopp/slut)
    let firstRunMin: number | null = null;
    let lastEventMin: number | null = null;
    for (const ev of events) {
      if (firstRunMin === null && ev.running) { firstRunMin = ev.min; }
      lastEventMin = ev.min;
    }

    if (firstRunMin === null) {
      this.dayLongestStopMinutes = 0;
      this.dayUtilizationPct = 0;
      return;
    }

    // Beräkna total körtid och längsta stopp
    let totalRunMinutes = 0;
    let longestStop = 0;
    let currentRunning = false;
    let runStartMin = 0;
    let stopStart: number | null = null;

    for (const ev of events) {
      if (currentRunning && !ev.running) {
        // Maskinen stannar
        totalRunMinutes += ev.min - runStartMin;
        stopStart = ev.min;
        currentRunning = false;
      } else if (!currentRunning && ev.running) {
        // Maskinen startar
        if (stopStart !== null) {
          const stopDur = ev.min - stopStart;
          if (stopDur > longestStop) { longestStop = stopDur; }
        }
        runStartMin = ev.min;
        currentRunning = true;
      }
    }

    // Om maskinen fortfarande körde vid sista händelse: lägg till tid fram till sista händelse
    if (currentRunning && lastEventMin !== null) {
      totalRunMinutes += lastEventMin - runStartMin;
    }

    // Utnyttjandegrad = körtid / (sista - första) händelse * 100
    const spanMin = (lastEventMin ?? 0) - firstRunMin;
    const utilization = spanMin > 0 ? Math.min(100, Math.round((totalRunMinutes / spanMin) * 100)) : 0;

    this.dayLongestStopMinutes = longestStop;
    this.dayUtilizationPct = utilization;
  }

  updateTable(data: any) {
    this.tableData = [];
    const grouped = new Map<string, any[]>();

    data.cycles.forEach((cycle: any) => {
      const date = new Date(cycle.datum);
      let key: string;

      if (this.viewMode === 'year') {
        key = `${date.getFullYear()}-${date.getMonth()}`;
      } else if (this.viewMode === 'month') {
        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
      } else {
        // Group by 10-minute intervals for day view
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;

        // Om användaren har markerat ett intervall i dag-vyn, filtrera bort cykler utanför.
        // chartSelectionStart/EndIndex är relativa till det visade fönstret (börjar vid chartWindowFrom).
        if (
          this.chartSelectionStartIndex !== null &&
          this.chartSelectionEndIndex !== null
        ) {
          const bucketIndex = hour * 6 + minute / 10;
          const minSel = this.chartWindowFrom + Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          const maxSel = this.chartWindowFrom + Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          if (bucketIndex < minSel || bucketIndex > maxSel) {
            return;
          }
        }

        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${hour}-${minute}`;
      }

      if (!grouped.has(key)) {
        grouped.set(key, []);
      }
      grouped.get(key)!.push(cycle);
    });

    grouped.forEach((cycles, key) => {
      const date = new Date(cycles[0].datum);
      let period: string;

      if (this.viewMode === 'year') {
        period = this.monthNames[date.getMonth()];
      } else if (this.viewMode === 'month') {
        period = `${date.getDate()} ${this.monthNames[date.getMonth()].substring(0, 3)}`;
      } else {
        // Show 10-minute intervals for day view
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        const endMinute = minute + 10;
        period = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')} - ${hour.toString().padStart(2, '0')}:${endMinute.toString().padStart(2, '0')}`;
      }

      // Filtrera bort NULL cycle_time värden för korrekt genomsnitt
      const validCycleTimes = cycles
        .map(c => c.cycle_time)
        .filter(t => t !== null && t !== undefined && t > 0);

      const avgCycleTime = validCycleTimes.length > 0
        ? validCycleTimes.reduce((sum, t) => sum + t, 0) / validCycleTimes.length
        : 0;

      const avgEff = cycles.reduce((sum, c) => sum + (c.produktion_procent || 0), 0) / cycles.length;

      this.tableData.push({
        period: period,
        date: date,
        cycles: cycles.length,
        avgCycleTime: Math.round(avgCycleTime * 10) / 10,
        efficiency: Math.round(avgEff),
        runtime: Math.round(cycles.length * avgCycleTime * 10) / 10,
        clickable: this.viewMode !== 'day'
      });
    });

    this.tableData.sort((a, b) => a.date.getTime() - b.date.getTime());
  }

  onTableRowClick(row: TableRow) {
    if (!row.clickable) return;

    if (this.viewMode === 'year') {
      this.navigateToMonth(row.date);
    } else if (this.viewMode === 'month') {
      this.navigateToDay(row.date);
    }
  }

  getViewModeLabel(): string {
    if (this.viewMode === 'year') return 'Månader';
    if (this.viewMode === 'month') return 'Dagar';
    if (this.viewMode === 'heatmap') {
      if (this.heatmapUseCustomRange && this.heatmapCustomFrom && this.heatmapCustomTo) {
        return `${this.heatmapCustomFrom} – ${this.heatmapCustomTo}`;
      }
      return `Senaste ${this.heatmapDays} dagarna`;
    }
    return '10-min intervall';
  }

  // ======== HEATMAP ========

  enterHeatmapMode() {
    this.viewMode = 'heatmap';
    this.resetChartSelection();
    try { this.productionChart?.destroy(); } catch (e) {}
    this.productionChart = null;
    this.loadHeatmap();
  }

  exitHeatmapMode() {
    // Rensa custom range vid exit
    this.heatmapUseCustomRange = false;
    this.heatmapCustomFrom = '';
    this.heatmapCustomTo = '';
    this.navigateToYear();
  }

  loadHeatmap() {
    if (this.isLoadingHeatmap) return;
    this.isLoadingHeatmap = true;
    this.loading = true;
    this.error = null;
    const obs = this.heatmapUseCustomRange && this.heatmapCustomFrom && this.heatmapCustomTo
      ? this.rebotlingService.getHeatmap(this.heatmapDays, this.heatmapCustomFrom, this.heatmapCustomTo)
      : this.rebotlingService.getHeatmap(this.heatmapDays);
    obs.pipe(
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res: any) => {
        this.isLoadingHeatmap = false;
        this.loading = false;
        if (res?.success && Array.isArray(res.data)) {
          if (this.heatmapUseCustomRange && this.heatmapCustomFrom && this.heatmapCustomTo) {
            this.buildHeatmapRowsForRange(res.data, this.heatmapCustomFrom, this.heatmapCustomTo);
          } else {
            this.buildHeatmapRows(res.data);
          }
        } else {
          this.error = 'Kunde inte ladda heatmap-data';
        }
      },
      error: () => {
        this.isLoadingHeatmap = false;
        this.loading = false;
        this.error = 'Nätverksfel vid hämtning av heatmap';
      }
    });
  }

  applyHeatmapCustomRange() {
    if (!this.heatmapCustomFrom || !this.heatmapCustomTo) return;
    this.heatmapUseCustomRange = true;
    this.loadHeatmap();
  }

  clearHeatmapCustomRange() {
    this.heatmapUseCustomRange = false;
    this.heatmapCustomFrom = '';
    this.heatmapCustomTo = '';
    this.loadHeatmap();
  }

  private buildHeatmapRows(data: { date: string; hour: number; count: number; quality_pct?: number }[]) {
    // Bygg en map: date → { hour → { count, quality_pct } }
    const map = new Map<string, Map<number, { count: number; quality_pct: number }>>();
    data.forEach(({ date, hour, count, quality_pct }) => {
      if (!map.has(date)) map.set(date, new Map());
      map.get(date)!.set(hour, { count, quality_pct: quality_pct ?? 0 });
    });

    // Skapa sorterad lista av unika datum (senaste N dagarna, nyast sist)
    const today = new Date();
    const rows: typeof this.heatmapRows = [];
    this.heatmapMax = 1;

    for (let i = this.heatmapDays - 1; i >= 0; i--) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      const dateStr = localDateStr(d);
      const dayMap = map.get(dateStr) || new Map();

      const counts = this.heatmapHours.map(h => dayMap.get(h)?.count || 0);
      const qualityPct = this.heatmapHours.map(h => dayMap.get(h)?.quality_pct || 0);
      const maxVal = Math.max(...counts);
      if (maxVal > this.heatmapMax) this.heatmapMax = maxVal;

      const weekdays = ['sön', 'mån', 'tis', 'ons', 'tor', 'fre', 'lör'];
      const label = `${weekdays[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1}`;
      rows.push({ date: dateStr, label, counts, qualityPct });
    }

    this.heatmapRows = rows;
  }

  private buildHeatmapRowsForRange(
    data: { date: string; hour: number; count: number; quality_pct?: number }[],
    fromDate: string,
    toDate: string
  ) {
    // Bygg map: date → { hour → { count, quality_pct } }
    const map = new Map<string, Map<number, { count: number; quality_pct: number }>>();
    data.forEach(({ date, hour, count, quality_pct }) => {
      if (!map.has(date)) map.set(date, new Map());
      map.get(date)!.set(hour, { count, quality_pct: quality_pct ?? 0 });
    });

    // Generera alla datum inom intervallet
    const rows: typeof this.heatmapRows = [];
    this.heatmapMax = 1;
    const start = new Date(fromDate + 'T00:00:00');
    const end = new Date(toDate + 'T00:00:00');
    const weekdays = ['sön', 'mån', 'tis', 'ons', 'tor', 'fre', 'lör'];

    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      const dateStr = localDateStr(d);
      const dayMap = map.get(dateStr) || new Map();
      const counts = this.heatmapHours.map(h => dayMap.get(h)?.count || 0);
      const qualityPct = this.heatmapHours.map(h => dayMap.get(h)?.quality_pct || 0);
      const maxVal = Math.max(...counts);
      if (maxVal > this.heatmapMax) this.heatmapMax = maxVal;
      const label = `${weekdays[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1}`;
      rows.push({ date: dateStr, label, counts, qualityPct });
    }

    this.heatmapRows = rows;
  }

  getHeatmapColor(rowIndex: number, hourIndex: number): string {
    const row = this.heatmapRows[rowIndex];
    if (!row) return '#2a2a3a';

    if (this.heatmapKpi === 'ibc') {
      const count = row.counts[hourIndex];
      if (count === 0) return '#2a2a3a'; // Mörk grå för noll-celler
      const ratio = Math.min(count / this.heatmapMax, 1);
      // Vit → mörkblå: interpolera rgb(230,245,255) → rgb(5,60,150)
      const r = Math.round(230 - ratio * 225);
      const g = Math.round(245 - ratio * 185);
      const b = Math.round(255 - ratio * 105);
      return `rgb(${r},${g},${b})`;
    } else if (this.heatmapKpi === 'quality') {
      const q = row.qualityPct[hourIndex];
      if (row.counts[hourIndex] === 0) return '#2a2a3a';
      if (q === 0) return '#2a2a3a';
      const ratio = Math.min(q / 100, 1);
      // Vit → mörkgrön: interpolera rgb(230,255,235) → rgb(10,90,30)
      const r = Math.round(230 - ratio * 220);
      const g = Math.round(255 - ratio * 165);
      const b = Math.round(235 - ratio * 205);
      return `rgb(${r},${g},${b})`;
    } else {
      // OEE: visa count som proxy (ingen OEE per timme i backend)
      const count = row.counts[hourIndex];
      if (count === 0) return '#2a2a3a';
      const ratio = Math.min(count / this.heatmapMax, 1);
      // Vit → mörkviolett: interpolera rgb(245,235,255) → rgb(80,20,140)
      const r = Math.round(245 - ratio * 165);
      const g = Math.round(235 - ratio * 215);
      const b = Math.round(255 - ratio * 115);
      return `rgb(${r},${g},${b})`;
    }
  }

  getHeatmapLegendGradient(): string {
    if (this.heatmapKpi === 'ibc') {
      return 'linear-gradient(to right, #e6f5ff, #053c96)';
    } else if (this.heatmapKpi === 'quality') {
      return 'linear-gradient(to right, #e6ffeb, #0a5a1e)';
    } else {
      return 'linear-gradient(to right, #f5ebff, #50148c)';
    }
  }

  getHeatmapLegendLow(): string {
    if (this.heatmapKpi === 'ibc') return '0 IBC';
    if (this.heatmapKpi === 'quality') return '0%';
    return 'Låg';
  }

  getHeatmapLegendHigh(): string {
    if (this.heatmapKpi === 'ibc') return `${this.heatmapMax} IBC/h`;
    if (this.heatmapKpi === 'quality') return '100%';
    return 'Hög';
  }

  getHeatmapKpiLabel(): string {
    if (this.heatmapKpi === 'ibc') return 'IBC/h';
    if (this.heatmapKpi === 'quality') return 'Kvalitet% (dagsnivå)';
    return 'OEE% (IBC-proxy)';
  }

  showHeatmapTooltip(event: MouseEvent, rowIndex: number, hourIndex: number) {
    const row = this.heatmapRows[rowIndex];
    if (!row) return;
    const count = row.counts[hourIndex];
    const hour = this.heatmapHours[hourIndex];
    const cellEl = event.currentTarget as HTMLElement;
    const containerEl = cellEl.closest('.heatmap-container') as HTMLElement | null;
    let x = 0;
    let y = 0;
    if (containerEl) {
      const cellRect = cellEl.getBoundingClientRect();
      const contRect = containerEl.getBoundingClientRect();
      // Positionera tooltip mitt ovanför cellen, relativt .heatmap-container
      x = cellRect.left - contRect.left + cellRect.width / 2 + containerEl.scrollLeft;
      y = cellRect.top - contRect.top;
    }
    this.heatmapTooltip = {
      visible: true,
      x,
      y,
      date: row.date,
      hour,
      count,
      ibcH: count,
      qualityPct: row.qualityPct[hourIndex]
    };
  }

  hideHeatmapTooltip() {
    this.heatmapTooltip = { ...this.heatmapTooltip, visible: false };
  }

  onHeatmapDaysChange() {
    // Rensa custom range när användaren väljer fast period
    this.heatmapUseCustomRange = false;
    this.heatmapCustomFrom = '';
    this.heatmapCustomTo = '';
    if (this.viewMode === 'heatmap') this.loadHeatmap();
  }

  getEfficiencyClass(efficiency: number): string {
    if (efficiency >= 90) return 'text-success';
    if (efficiency >= 70) return 'text-warning';
    return 'text-danger';
  }

  exportProductionChart(): void {
    const canvas = this.productionChartRef?.nativeElement;
    if (!canvas) return;
    const periodLabel = this.getViewModeLabel();
    exportChartAsPng(canvas, {
      chartName: 'Produktionsanalys - ' + periodLabel
    });
    this.exportChartFeedback = true;
    setTimeout(() => this.exportChartFeedback = false, 2000);
  }

  exportCSV() {
    if (this.tableData.length === 0) return;

    const header = ['Period', 'Cykler', 'Cykeltid (min)', 'Effektivitet (%)', 'Drifttid (min)'];
    const rows = this.tableData.map(row => [
      row.period,
      row.cycles,
      row.avgCycleTime,
      row.efficiency,
      row.runtime
    ]);

    const csvContent = [header, ...rows]
      .map(row => row.map(cell => `"${cell}"`).join(';'))
      .join('\n');

    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `rebotling-statistik-${this.breadcrumb.join('-')}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (this.tableData.length === 0) return;
    import('xlsx').then(XLSX => {
      const data = this.tableData.map((row: any) => ({
        'Period': row.period,
        'Cykler': row.cycles,
        'Cykeltid (min)': row.avgCycleTime,
        'Effektivitet (%)': row.efficiency,
        'Drifttid (min)': row.runtime
      }));
      const ws = XLSX.utils.json_to_sheet(data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Statistik');
      XLSX.writeFile(wb, `rebotling-statistik-${this.breadcrumb.join('-')}.xlsx`);
    });
  }

  // Visibility toggles for on-demand panels
  showWeekComparison: boolean = false;
  showPrediktion: boolean = false;
  showOeeDeepDive: boolean = false;
  showCycleTrend: boolean = false;

  exportHeatmapCSV(): void {
    if (!this.heatmapRows || this.heatmapRows.length === 0) return;
    const headers = ['Datum', 'Timme', 'IBC denna timme', 'Kvalitet %'];
    const rows: (string | number)[][] = [];
    this.heatmapRows.forEach(row => {
      this.heatmapHours.forEach((hour, hi) => {
        const count = row.counts[hi] || 0;
        const quality = row.qualityPct[hi] || 0;
        if (count > 0 || quality > 0) {
          rows.push([row.date, hour + ':00', count, quality > 0 ? quality.toFixed(1) + '%' : '']);
        }
      });
    });
    if (rows.length === 0) return;
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `heatmap-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
}
