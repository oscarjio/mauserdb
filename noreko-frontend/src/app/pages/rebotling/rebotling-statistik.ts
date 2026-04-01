import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { RebotlingService, ChartAnnotation, ExecDashboardResponse, DashboardWidgetEntry, DashboardAvailableWidget } from '../../services/rebotling.service';
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
import { StatistikOeeGaugeComponent } from './statistik/statistik-oee-gauge/statistik-oee-gauge';
import { StatistikProduktionsmalComponent } from './statistik/statistik-produktionsmal/statistik-produktionsmal';
import { StatistikSkiftjamforelseComponent } from './statistik/statistik-skiftjamforelse/statistik-skiftjamforelse';
import { StatistikBonusSimulatorComponent } from './statistik/statistik-bonus-simulator/statistik-bonus-simulator';
import { StatistikLeaderboardComponent } from './statistik/statistik-leaderboard/statistik-leaderboard';
import { StatistikUptidHeatmapComponent } from './statistik/statistik-uptid-heatmap/statistik-uptid-heatmap';
import { StatistikVeckotrendComponent } from './statistik/statistik-veckotrend/statistik-veckotrend';
import { StatistikKassationsanalysComponent } from './statistik/statistik-kassationsanalys/statistik-kassationsanalys';


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
  avgProdPct: number;
  ibcPerHour: number;
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
    StatistikAnnotationerComponent, StatistikOeeGaugeComponent, StatistikProduktionsmalComponent,
    StatistikSkiftjamforelseComponent, StatistikBonusSimulatorComponent,
    StatistikLeaderboardComponent, StatistikUptidHeatmapComponent,
    StatistikVeckotrendComponent, StatistikKassationsanalysComponent]
})
export class RebotlingStatistikPage implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('productionChart') productionChartRef!: ElementRef<HTMLCanvasElement>;

  viewMode: ViewMode = 'month';
  currentYear: number = new Date().getFullYear();
  currentMonth: number = new Date().getMonth();
  selectedPeriods: Date[] = [];

  // Flik-navigation
  activeTab: 'overview' | 'production' | 'quality' | 'operators' | 'analysis' = 'overview';
  private heatmapLoadedOnce = false;

  periodCells: PeriodCell[] = [];
  // Cachad filtrerad lista — uppdateras i generatePeriodCells/updatePeriodCellsData, undviker .filter() i template
  visiblePeriodCells: PeriodCell[] = [];
  monthNames = ['Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];

  totalCycles: number = 0;
  avgCycleTime: number = 0;
  avgEfficiency: number = 0;
  avgProdPct: number = 0;
  totalRuntimeHours: number = 0;
  totalIbcPerHour: number = 0;
  targetCycleTime: number = 0;

  productionChart: Chart | null = null;
  exportChartFeedback: boolean = false;
  tableData: TableRow[] = [];

  // Senaste hämtade statistik-data (används för zoom/val i grafen)
  private lastStatisticsData: any = null;
  // Markering i grafen (cykelindex i dag-vy, slot-index i övriga vyer)
  private chartSelectionStartIndex: number | null = null;
  private chartSelectionEndIndex: number | null = null;
  // Förhandsvisning medan man drar med musen
  private chartSelectionPreviewStartIndex: number | null = null;
  private chartSelectionPreviewEndIndex: number | null = null;

  loading: boolean = false;
  error: string | null = null;
  breadcrumb: string[] = [];

  totalRastMinutes: number = 0;
  timelineSegments: { startPct: number; widthPct: number; type: 'running' | 'rast' | 'stopped' | 'driftstopp'; startTime: string; endTime: string; duration: string }[] = [];
  timelineEndPct: number = 100;
  showTimelineDetail = false;
  shiftSummaries: { nr: number; ibcCount: number; avgCycleTime: number; rastMinutes: number }[] = [];

  // Dag-vy: längsta stopp (min) och utnyttjandegrad (%)
  dayLongestStopMinutes: number = 0;
  dayUtilizationPct: number = 0;

  // Per-cykel data för dag-vy (sorterade efter tid)
  private sortedDayCycles: any[] = [];

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

  private savedScrollY: number | null = null;

  private destroy$ = new Subject<void>();
  private chartUpdateTimer: any = null;
  private exportFeedbackTimer: any = null;

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
    this.loadDashboardLayout();
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
    clearTimeout(this.exportFeedbackTimer);
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
    this.savedScrollY = window.scrollY;
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
    this.savedScrollY = window.scrollY;
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
      this.currentYear = date.getFullYear();
      this.currentMonth = date.getMonth();
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
      this.currentYear = date.getFullYear();
      this.currentMonth = date.getMonth();
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
    this.updateVisiblePeriodCells();
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

  /** Uppdaterar cachad lista visiblePeriodCells — anropas efter att periodCells ändrats */
  private updateVisiblePeriodCells(): void {
    if (this.viewMode !== 'month' || !this.showOnlyDaysWithCycles) {
      this.visiblePeriodCells = this.periodCells;
      return;
    }
    const withData = this.periodCells.filter(cell => cell.hasData);
    this.visiblePeriodCells = withData.length > 0 ? withData : this.periodCells;
  }

  toggleShowOnlyDaysWithCycles(): void {
    this.showOnlyDaysWithCycles = !this.showOnlyDaysWithCycles;
    this.updateVisiblePeriodCells();
  }

  /** Behålls för bakåtkompatibilitet — använd visiblePeriodCells i template istället */
  getVisiblePeriodCells(): PeriodCell[] {
    return this.visiblePeriodCells;
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

      // Restore scroll position if saved (e.g. from month chart click)
      if (this.savedScrollY !== null) {
        const scrollTarget = this.savedScrollY;
        this.savedScrollY = null;
        setTimeout(() => window.scrollTo(0, scrollTarget), 50);
      }
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
    // Effektivitet:
    //  - Dag-vy: total_ibc * target / netto_drifttid (matchar skiftrapporten)
    //  - Månad/år-vy: target / avg_cykeltid (samma formel som stapeldiagrammet)
    const targetCt = data.summary.target_cycle_time || 3;
    let properEff: number;
    if (this.viewMode === 'day') {
      const netRtMin = data.summary.net_runtime_minutes || 0;
      const totalCyc = data.summary.total_cycles || 0;
      properEff = (netRtMin > 0 && totalCyc > 0)
        ? Math.round(totalCyc * targetCt / netRtMin * 100)
        : 0;
    } else {
      const avgCt = data.summary.avg_cycle_time || 0;
      properEff = avgCt > 0 ? Math.round((targetCt / avgCt) * 100) : 0;
    }
    this.avgEfficiency = properEff;
    this.avgProdPct = properEff;
    this.totalRuntimeHours = Math.round(data.summary.total_runtime_hours * 10) / 10;
    this.targetCycleTime = data.summary.target_cycle_time || 0;
    this.totalRastMinutes = data.summary.total_rast_minutes || 0;

    this.buildTimelineSegments(data);
    this.buildShiftSummaries(data.cycles || []);
    this.computeDayMetrics(data);

    // Spara rådata för modal
    this.rawCycles = data.cycles || [];
    this.sortRawCycles();

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

        const validTargets = periodCycles
          .map(c => parseFloat(c.target_cycle_time))
          .filter(t => !isNaN(t) && t > 0);
        const avgTarget = validTargets.length > 0
          ? validTargets.reduce((s, t) => s + t, 0) / validTargets.length
          : (this.targetCycleTime || 3);
        cell.avgCycleTime = Math.round(avgCycleTime * 10) / 10;
        cell.efficiency = avgCycleTime > 0 ? Math.min(150, Math.round((avgTarget / avgCycleTime) * 100)) : 0;
      }
    });

    // För dagvyn: Ta bort tomma intervall i början och slutet
    if (this.viewMode === 'day') {
      this.trimEmptyPeriods();
    }
    this.updateVisiblePeriodCells();
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

    // Dag-vy: per-cykel istället för 10-minutersintervall
    if (this.viewMode === 'day') {
      return this.preparePerCycleChartData(cycles, onoff, data);
    }

    const grouped = new Map<string, any>();

    // Initialize ALL periods first
    if (this.viewMode === 'month') {
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
        // Visa alltid ALLA dagar i månaden — en vald dag ska inte isolera stapeln i mitten
        const daysToShow = Array.from({ length: daysInMonth }, (_, i) => i + 1);
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

    // Add cycle data (månad/år-vy — dag-vy hanteras i preparePerCycleChartData)
    cycles.forEach((cycle: any) => {
      const date = new Date(cycle.datum);
      let key: string;

      if (this.viewMode === 'month') {
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

      if (this.viewMode === 'month') {
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

    // Månad/år-vy: visa alla entries
    const slicedEntries = entries;

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
      const key = `${d.getDate()}`;
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

    // Build efficiency and cycle count arrays for bar chart (month/year views)
    // Efficiency = target_cycle_time / avg_actual_cycle_time * 100
    const efficiencyArr: number[] = [];
    const cycleCountArr: number[] = [];
    slicedEntries.forEach(([, value]) => {
      const count = value.cycles.length;
      cycleCountArr.push(count);
      if (count > 0) {
        const validTimes = value.cycles
          .map((c: any) => c.cycle_time)
          .filter((t: any) => t !== null && t !== undefined && t > 0 && t <= 30);
        const avgTarget = value.cycles.reduce((s: number, c: any) => s + (c.target_cycle_time || 3), 0) / count;
        if (validTimes.length > 0) {
          const avgActual = validTimes.reduce((s: number, t: number) => s + t, 0) / validTimes.length;
          efficiencyArr.push(Math.min(150, Math.round((avgTarget / avgActual) * 100)));
        } else {
          efficiencyArr.push(0);
        }
      } else {
        efficiencyArr.push(0);
      }
    });

    return { labels, cycleTime, avgCycleTime: avgCycleTimeArr, targetCycleTime: targetCycleTimeArr, runningPeriods, rastPeriods, efficiencyArr, cycleCountArr };
  }

  /**
   * Per-cykel graf-data för dag-vy.
   * Varje IBC-cykel = en datapunkt (istället för 10-minutersintervall).
   */
  private preparePerCycleChartData(cycles: any[], onoff: any[], data: any) {
    // Sortera cykler kronologiskt
    const sorted = [...cycles].sort(
      (a, b) => new Date(a.datum).getTime() - new Date(b.datum).getTime()
    );
    this.sortedDayCycles = sorted;

    // Filtrera till cykler med giltig cykeltid (för grafen)
    const withTime = sorted.filter((c: any) => {
      const ct = parseFloat(c.cycle_time);
      return !isNaN(ct) && ct > 0 && ct <= 30;
    });

    // Tillämpa markering/zoom — arbetar direkt med cykelindex
    let displayCycles = withTime;
    if (
      this.chartSelectionStartIndex !== null &&
      this.chartSelectionEndIndex !== null
    ) {
      const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      displayCycles = withTime.slice(
        Math.max(0, minSel),
        Math.min(withTime.length, maxSel + 1)
      );
    }

    const labels: string[] = [];
    const cycleTime: number[] = [];
    const prodPct: number[] = [];
    const avgCycleTimeArr: number[] = [];
    const targetCycleTimeArr: number[] = [];
    const produktNamn: string[] = [];

    let totalCycleTime = 0;
    let totalProdPct = 0;

    // Spåra produktbyten för annotations
    let lastProduktId: any = null;
    const produktByten: { index: number; namn: string }[] = [];

    displayCycles.forEach((cycle: any, i: number) => {
      const d = new Date(cycle.datum);
      labels.push(
        `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}:${d.getSeconds().toString().padStart(2, '0')}`
      );
      const ct = parseFloat(cycle.cycle_time);
      cycleTime.push(Math.round(ct * 10) / 10);
      totalCycleTime += ct;

      // Per-cykel mål baserat på produktens cykeltid (stegar vid produktbyte)
      const target = parseFloat(cycle.target_cycle_time);

      // Beräkna riktig effektivitet: target_cycle_time / rolling_avg_cycle_time * 100
      // Använd glidande medelvärde av de senaste 5 cyklerna för jämnare kurva
      const effTargetVal = !isNaN(target) && target > 0 ? target : 3;
      const windowSize = 5;
      const startIdx = Math.max(0, i - windowSize + 1);
      let windowSum = 0;
      let windowCount = 0;
      for (let w = startIdx; w <= i; w++) {
        const wct = parseFloat(displayCycles[w].cycle_time);
        if (!isNaN(wct) && wct > 0 && wct <= 30) {
          windowSum += wct;
          windowCount++;
        }
      }
      const rollingAvg = windowCount > 0 ? windowSum / windowCount : 0;
      const pp = rollingAvg > 0 ? Math.min(150, Math.round((effTargetVal / rollingAvg) * 100)) : 0;
      prodPct.push(pp);
      totalProdPct += pp;
      targetCycleTimeArr.push(!isNaN(target) && target > 0 ? Math.round(target * 10) / 10 : 0);

      // Produktnamn per cykel (för tooltip)
      produktNamn.push(cycle.produkt_namn || '');

      // Detektera produktbyte (inkludera även första produkten vid index 0)
      if (cycle.produkt_id && cycle.produkt_id !== lastProduktId) {
        produktByten.push({ index: i, namn: cycle.produkt_namn || 'Ny produkt' });
        lastProduktId = cycle.produkt_id;
      }
    });

    const overallAvg = displayCycles.length > 0
      ? Math.round((totalCycleTime / displayCycles.length) * 10) / 10
      : 0;
    labels.forEach(() => {
      avgCycleTimeArr.push(overallAvg);
    });

    // Bygg kör/stopp-perioder från on/off-händelser, mappade till cykelindex
    const runningPeriods = this.buildRunningPeriodsForCycles(displayCycles, onoff);

    // Rast-perioder mappade till cykelindex
    const rast: any[] = data.rast_events || [];
    const rastPeriods = this.buildRastPeriodsForCycles(displayCycles, rast);

    const avgProdPctVal = displayCycles.length > 0
      ? Math.round(totalProdPct / displayCycles.length)
      : 0;
    const avgProdPctArr = labels.map(() => avgProdPctVal);

    return {
      labels, cycleTime, prodPct, avgProdPct: avgProdPctArr,
      avgCycleTime: avgCycleTimeArr,
      targetCycleTime: targetCycleTimeArr, runningPeriods, rastPeriods,
      produktByten, produktNamn
    };
  }

  /**
   * Bygg kör/stopp bakgrundsfält mappade till cykelindex.
   * Varje cykel har en timestamp; vi kollar on/off-händelser för att avgöra
   * om det finns stopp-perioder mellan cyklerna.
   */
  private buildRunningPeriodsForCycles(displayCycles: any[], onoff: any[]): any[] {
    if (displayCycles.length === 0) return [];

    // Bygg on/off tidslinje
    const offPeriods: { start: number; end: number }[] = [];
    let lastOff: Date | null = null;
    for (const ev of onoff) {
      const d = new Date(ev.datum);
      if (!ev.running) {
        lastOff = d;
      } else if (lastOff) {
        offPeriods.push({ start: lastOff.getTime(), end: d.getTime() });
        lastOff = null;
      }
    }

    // Markera hela grafområdet som "running" som bas
    const periods: any[] = [];
    let currentStart = 0;

    for (let i = 1; i < displayCycles.length; i++) {
      const prevTime = new Date(displayCycles[i - 1].datum).getTime();
      const currTime = new Date(displayCycles[i].datum).getTime();

      // Kolla om det finns en off-period som överlappar gapet mellan cyklerna
      const hasOff = offPeriods.some(p => p.start <= currTime && p.end >= prevTime);

      if (hasOff) {
        // Stäng köra-perioden
        if (i - 1 >= currentStart) {
          periods.push({ startIndex: currentStart, endIndex: i - 1, running: true });
        }
        // Stoppperiod (enbart gapet)
        periods.push({ startIndex: i - 1, endIndex: i, running: false });
        currentStart = i;
      }
    }

    // Stäng sista kör-perioden
    if (currentStart <= displayCycles.length - 1) {
      periods.push({ startIndex: currentStart, endIndex: displayCycles.length - 1, running: true });
    }

    return periods;
  }

  /**
   * Mappa rast-händelser till cykelindex i per-cykel-grafen.
   */
  private buildRastPeriodsForCycles(displayCycles: any[], rastEvents: any[]): { startIndex: number; endIndex: number }[] {
    if (!displayCycles.length || !rastEvents.length) return [];

    const periods: { startIndex: number; endIndex: number }[] = [];
    let rastStart: Date | null = null;

    for (const ev of rastEvents) {
      const d = new Date(ev.datum);
      if (ev.rast_status == 1) {
        rastStart = d;
      } else if (ev.rast_status == 0 && rastStart) {
        const startTime = rastStart.getTime();
        const endTime = d.getTime();

        // Hitta cykelindex som omger rast-perioden
        let si = -1;
        let ei = -1;
        for (let i = 0; i < displayCycles.length; i++) {
          const ct = new Date(displayCycles[i].datum).getTime();
          if (ct >= startTime && si === -1) si = Math.max(0, i - 1);
          if (ct >= endTime) { ei = i; break; }
        }
        if (si === -1) si = displayCycles.length - 1;
        if (ei === -1) ei = displayCycles.length - 1;
        if (si >= 0) periods.push({ startIndex: si, endIndex: ei });
        rastStart = null;
      }
    }

    return periods;
  }

  createChart(ctx: CanvasRenderingContext2D, chartData: any) {
    try {
      const isDayView = this.viewMode === 'day' && chartData.prodPct;

      if (!isDayView) {
        // Month/Year view: bar chart with efficiency % and IBC count labels
        this.createBarChart(ctx, chartData);
        return;
      }

      // Day view: line chart with efficiency %
      const datasets: any[] = [
        {
          label: 'Effektivitet %',
          data: chartData.prodPct,
          borderColor: '#00d4ff',
          backgroundColor: 'rgba(0, 212, 255, 0.1)',
          tension: 0.4,
          fill: true,
          yAxisID: 'y',
          pointRadius: 2,
          pointHoverRadius: 6,
          borderWidth: 2
        },
        {
          label: 'Snitt',
          data: chartData.avgProdPct,
          borderColor: '#ffc107',
          borderDash: [8, 4],
          tension: 0,
          fill: false,
          yAxisID: 'y',
          pointRadius: 0,
          borderWidth: 2
        }
      ];

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
              intersect: false, mode: 'nearest',
              backgroundColor: 'rgba(20, 20, 20, 0.95)',
              titleColor: '#fff',
              bodyColor: '#e0e0e0',
              borderColor: '#00d4ff',
              borderWidth: 1,
              padding: 12,
              displayColors: true,
              callbacks: {
                title: (items: any[]) => {
                  if (!items.length) return '';
                  return `Tid: ${items[0].label}`;
                },
                label: (ctx: any) => {
                  const val = ctx.parsed.y;
                  if (ctx.dataset.label === 'Effektivitet %') return `Effektivitet: ${val != null ? val.toFixed(1) : '—'}%`;
                  if (ctx.dataset.label === 'Snitt') return `Genomsnitt: ${val != null ? val.toFixed(1) : '—'}%`;
                  return `${ctx.dataset.label}: ${val != null ? val.toFixed(1) : '—'}`;
                },
                afterBody: (tooltipItems: any[]) => {
                  if (!tooltipItems.length || !chartData.produktNamn) return '';
                  const idx = tooltipItems[0].dataIndex;
                  const lines: string[] = [];
                  const namn = chartData.produktNamn[idx];
                  if (namn) lines.push(`Produkt: ${namn}`);
                  if (chartData.targetCycleTime?.[idx]) lines.push(`Målcykeltid: ${chartData.targetCycleTime[idx].toFixed(1)} min`);
                  if (chartData.cycleTime?.[idx]) lines.push(`Cykeltid: ${chartData.cycleTime[idx].toFixed(1)} min`);
                  return lines.length ? lines.join('\n') : '';
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 150,
              title: { display: true, text: 'Effektivitet %', color: '#e0e0e0', font: { size: 13 } },
              ticks: { color: '#a0a0a0' },
              grid: { color: 'rgba(255, 255, 255, 0.05)' }
            },
            x: {
              ticks: {
                color: '#a0a0a0',
                maxRotation: 45,
                minRotation: 0,
                autoSkip: true,
                maxTicksLimit: 24
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

            const { top, bottom, left, right } = chartArea;

            // 100% mål-linje
            const yScale = scales['y'];
            if (yScale) {
              const y100 = yScale.getPixelForValue(100);
              if (y100 >= top && y100 <= bottom) {
                ctx.save();
                ctx.beginPath();
                ctx.setLineDash([6, 4]);
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
                ctx.lineWidth = 1.5;
                ctx.moveTo(left, y100);
                ctx.lineTo(right, y100);
                ctx.stroke();
                ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
                ctx.font = '10px sans-serif';
                ctx.textAlign = 'right';
                ctx.textBaseline = 'bottom';
                ctx.fillText('Mål 100%', right - 4, y100 - 3);
                ctx.restore();
              }
            }

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
                ctx.strokeStyle = 'rgba(255, 193, 7, 0.85)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(xStart, top);
                ctx.lineTo(xEnd, top);
                ctx.stroke();
              } catch (e) {}
            });

            // Rita produktbyte-linjer (vertikala streckade linjer)
            (chartData.produktByten || []).forEach((pb: any) => {
              try {
                const xPos = scales.x.getPixelForValue(pb.index);
                ctx.save();

                // Visa vertikal linje bara vid produktbyten (inte vid index 0)
                if (pb.index > 0) {
                  ctx.strokeStyle = '#ff8800';
                  ctx.lineWidth = 2;
                  ctx.setLineDash([6, 4]);
                  ctx.beginPath();
                  ctx.moveTo(xPos, top);
                  ctx.lineTo(xPos, bottom);
                  ctx.stroke();
                  ctx.setLineDash([]);
                }

                ctx.fillStyle = '#ff8800';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = pb.index === 0 ? 'left' : 'center';
                ctx.fillText(pb.namn, xPos + (pb.index === 0 ? 2 : 0), top - 4);
                ctx.restore();
              } catch (e) {}
            });

            // Rita förhandsvisning av markerat intervall
            if (
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

                ctx.fillStyle = 'rgba(0, 153, 255, 0.18)';
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

  /**
   * Bar chart for month/year views: bars = efficiency %, colored by value,
   * IBC count as data label on top, click navigates to day/month.
   */
  private createBarChart(ctx: CanvasRenderingContext2D, chartData: any) {
    const effData: number[] = chartData.efficiencyArr || [];
    const countData: number[] = chartData.cycleCountArr || [];

    // Color each bar by efficiency value
    const barColors = effData.map((eff: number) => {
      if (eff >= 90) return 'rgba(39, 174, 96, 0.75)';
      if (eff >= 70) return 'rgba(255, 193, 7, 0.75)';
      return 'rgba(220, 53, 69, 0.65)';
    });

    const barBorderColors = effData.map((eff: number) => {
      if (eff >= 90) return '#27ae60';
      if (eff >= 70) return '#ffc107';
      return '#dc3545';
    });

    this.productionChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: chartData.labels,
        datasets: [{
          label: 'Effektivitet %',
          data: effData,
          backgroundColor: barColors,
          borderColor: barBorderColors,
          borderWidth: 1,
          borderRadius: 4,
          yAxisID: 'y'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onClick: (_event: any, elements: any[]) => {
          if (elements.length > 0) {
            const idx = elements[0].index;
            this.onBarChartClick(idx);
          }
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            intersect: false, mode: 'index',
            backgroundColor: 'rgba(20, 20, 20, 0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#00d4ff',
            borderWidth: 1,
            padding: 12,
            callbacks: {
              title: (items: any[]) => {
                if (!items.length) return '';
                const label = items[0].label;
                return this.viewMode === 'year' ? `Månad: ${label}` : `Dag: ${label}`;
              },
              label: (context: any) => {
                const idx = context.dataIndex;
                const eff = effData[idx] || 0;
                const count = countData[idx] || 0;
                const effStatus = eff >= 100 ? ' (over mal)' : eff >= 90 ? ' (nara mal)' : ' (under mal)';
                return [`Effektivitet: ${eff}%${effStatus}`, `Antal IBC: ${count} st`];
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            suggestedMax: 150,
            title: { display: true, text: 'Effektivitet %', color: '#e0e0e0', font: { size: 13 } },
            ticks: { color: '#a0a0a0' },
            grid: { color: 'rgba(255, 255, 255, 0.05)' }
          },
          x: {
            ticks: {
              color: '#a0a0a0',
              maxRotation: 45,
              minRotation: 0,
              autoSkip: true
            },
            grid: { color: 'rgba(255, 255, 255, 0.05)' }
          }
        }
      },
      plugins: [{
        id: 'ibcCountLabelsAndTargetLine',
        afterDatasetsDraw: (chart: any) => {
          const { ctx: c, chartArea, scales } = chart;
          if (!chartArea) return;

          // Draw 100% target line
          const yScale = scales['y'];
          if (yScale) {
            const y100 = yScale.getPixelForValue(100);
            if (y100 >= chartArea.top && y100 <= chartArea.bottom) {
              c.save();
              c.beginPath();
              c.setLineDash([6, 4]);
              c.strokeStyle = 'rgba(255, 255, 255, 0.4)';
              c.lineWidth = 1.5;
              c.moveTo(chartArea.left, y100);
              c.lineTo(chartArea.right, y100);
              c.stroke();
              // Label
              c.fillStyle = 'rgba(255, 255, 255, 0.5)';
              c.font = '10px sans-serif';
              c.textAlign = 'right';
              c.textBaseline = 'bottom';
              c.fillText('Mål 100%', chartArea.right - 4, y100 - 3);
              c.restore();
            }
          }

          // Draw IBC count labels on bars
          const dataset = chart.data.datasets[0];
          if (!dataset) return;
          const meta = chart.getDatasetMeta(0);
          if (!meta?.data) return;

          c.save();
          c.font = 'bold 11px sans-serif';
          c.textAlign = 'center';
          c.textBaseline = 'bottom';

          meta.data.forEach((bar: any, i: number) => {
            const count = countData[i];
            if (count > 0) {
              c.fillStyle = '#e2e8f0';
              c.fillText(`${count}`, bar.x, bar.y - 4);
            }
          });
          c.restore();
        }
      }]
    });
  }

  /** Handle click on bar chart bar — navigate to day or month */
  private onBarChartClick(index: number) {
    if (!this.productionChart) return;
    const labels = this.productionChart.data.labels as string[];
    if (!labels || index >= labels.length) return;

    if (this.viewMode === 'month') {
      // Label is the day number (e.g. "5") or "5/3 14" for hourly
      const label = labels[index];
      const dayNum = parseInt(label, 10);
      if (!isNaN(dayNum) && dayNum >= 1 && dayNum <= 31) {
        const date = new Date(this.currentYear, this.currentMonth, dayNum);
        this.navigateToDay(date);
      }
    } else if (this.viewMode === 'year') {
      // Index 0-11 corresponds to months
      const date = new Date(this.currentYear, index, 1);
      this.navigateToMonth(date);
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
    if (this.viewMode !== 'day') { this.timelineSegments = []; this.timelineEndPct = 100; return; }
    const segments: typeof this.timelineSegments = [];
    const onoff: any[] = data.onoff_events || [];
    const rast: any[] = data.rast_events || [];
    const driftstopp: any[] = data.driftstopp_events || [];

    // Determine if this is today (use local date to avoid UTC timezone shift)
    const now = new Date();
    const isToday = this.selectedPeriods.length === 1 &&
      this.selectedPeriods[0].getFullYear() === now.getFullYear() &&
      this.selectedPeriods[0].getMonth() === now.getMonth() &&
      this.selectedPeriods[0].getDate() === now.getDate();

    type EvType = 'run_start' | 'run_end' | 'rast_start' | 'rast_end' | 'ds_start' | 'ds_end';
    const events: { min: number; type: EvType }[] = [];
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
    driftstopp.forEach((e: any) => {
      const d = new Date(e.datum);
      const min = d.getHours() * 60 + d.getMinutes();
      events.push({ min, type: e.driftstopp_status == 1 ? 'ds_start' : 'ds_end' });
    });
    events.sort((a, b) => a.min - b.min);

    // Cap timeline at current time (today) or last event (past days)
    let capMin: number;
    if (isToday) {
      capMin = now.getHours() * 60 + now.getMinutes();
    } else if (events.length > 0) {
      capMin = events[events.length - 1].min;
    } else {
      capMin = 1440;
    }
    // Always show at least up to cap, and full 24h bar background
    this.timelineEndPct = Math.min(100, (capMin / 1440) * 100);

    const fmtTime = (min: number): string => {
      const h = Math.floor(min / 60);
      const m = Math.round(min % 60);
      return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    };
    const fmtDuration = (mins: number): string => {
      if (mins < 1) return '<1 min';
      const h = Math.floor(mins / 60);
      const m = Math.round(mins % 60);
      return h > 0 ? `${h}h ${m}min` : `${m} min`;
    };

    let running = false, onRast = false, onDs = false, lastMin = 0;
    const push = (end: number) => {
      if (end > lastMin) {
        const type: 'running' | 'rast' | 'stopped' | 'driftstopp' =
          onDs ? 'driftstopp' : onRast ? 'rast' : running ? 'running' : 'stopped';
        segments.push({
          startPct: (lastMin / 1440) * 100,
          widthPct: ((end - lastMin) / 1440) * 100,
          type,
          startTime: fmtTime(lastMin),
          endTime: fmtTime(end),
          duration: fmtDuration(end - lastMin)
        });
      }
    };
    for (const ev of events) {
      if (ev.min > capMin) break;
      push(ev.min);
      lastMin = ev.min;
      if (ev.type === 'run_start') running = true;
      else if (ev.type === 'run_end') running = false;
      else if (ev.type === 'rast_start') onRast = true;
      else if (ev.type === 'rast_end') onRast = false;
      else if (ev.type === 'ds_start') onDs = true;
      else if (ev.type === 'ds_end') onDs = false;
    }
    push(capMin);

    // Absorb very short stops (<2 min) into surrounding running periods (PLC noise)
    const MIN_STOP_MINUTES = 2;
    const cleaned: typeof segments = [];
    for (const seg of segments) {
      const segMins = parseFloat(seg.endTime.split(':')[0]) * 60 + parseFloat(seg.endTime.split(':')[1])
                    - (parseFloat(seg.startTime.split(':')[0]) * 60 + parseFloat(seg.startTime.split(':')[1]));
      if (seg.type === 'stopped' && segMins < MIN_STOP_MINUTES && cleaned.length > 0 && cleaned[cleaned.length - 1].type === 'running') {
        // Absorb short stop into previous running segment
        const prev = cleaned[cleaned.length - 1];
        prev.widthPct += seg.widthPct;
        prev.endTime = seg.endTime;
        const pStart = parseFloat(prev.startTime.split(':')[0]) * 60 + parseFloat(prev.startTime.split(':')[1]);
        const pEnd = parseFloat(seg.endTime.split(':')[0]) * 60 + parseFloat(seg.endTime.split(':')[1]);
        prev.duration = fmtDuration(pEnd - pStart);
      } else {
        cleaned.push({ ...seg });
      }
    }

    // Merge consecutive segments of the same type
    const merged: typeof segments = [];
    for (const seg of cleaned) {
      const prev = merged.length > 0 ? merged[merged.length - 1] : null;
      if (prev && prev.type === seg.type) {
        prev.widthPct += seg.widthPct;
        prev.endTime = seg.endTime;
        const startMin = parseFloat(prev.startTime.split(':')[0]) * 60 + parseFloat(prev.startTime.split(':')[1]);
        const endMin = parseFloat(seg.endTime.split(':')[0]) * 60 + parseFloat(seg.endTime.split(':')[1]);
        prev.duration = fmtDuration(endMin - startMin);
      } else {
        merged.push({ ...seg });
      }
    }
    this.timelineSegments = merged;
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
        // Dag-vy: gruppera per cykel med tidsstämpel.
        // Om markering aktiv: filtrera via sortedDayCycles tidsintervall.
        if (
          this.chartSelectionStartIndex !== null &&
          this.chartSelectionEndIndex !== null &&
          this.sortedDayCycles.length > 0
        ) {
          const withTime = this.sortedDayCycles.filter((c: any) => {
            const ct = parseFloat(c.cycle_time);
            return !isNaN(ct) && ct > 0 && ct <= 30;
          });
          const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          const startCycle = withTime[Math.max(0, minSel)];
          const endCycle = withTime[Math.min(withTime.length - 1, maxSel)];
          if (startCycle && endCycle) {
            const startTime = new Date(startCycle.datum).getTime();
            const endTime = new Date(endCycle.datum).getTime();
            const cycleTime = date.getTime();
            if (cycleTime < startTime || cycleTime > endTime) {
              return; // Utanför markerat intervall
            }
          }
        }

        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${hour}-${minute}`;
      }

      if (!grouped.has(key)) {
        grouped.set(key, []);
      }
      grouped.get(key)!.push(cycle);
    });

    grouped.forEach((cycles, _key) => {
      const date = new Date(cycles[0].datum);
      let period: string;

      if (this.viewMode === 'year') {
        period = this.monthNames[date.getMonth()];
      } else if (this.viewMode === 'month') {
        period = `${date.getDate()} ${this.monthNames[date.getMonth()].substring(0, 3)}`;
      } else {
        // Dag-vy: visa 10-min-intervall i tabellen för gruppering
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        const endHour = minute >= 50 ? hour + 1 : hour;
        const endMinute = (minute + 10) % 60;
        period = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}–${endHour.toString().padStart(2, '0')}:${endMinute.toString().padStart(2, '0')}`;
      }

      // Filtrera bort NULL cycle_time värden för korrekt genomsnitt
      const validCycleTimes = cycles
        .map(c => c.cycle_time)
        .filter(t => t !== null && t !== undefined && t > 0);

      const avgCycleTime = validCycleTimes.length > 0
        ? validCycleTimes.reduce((sum, t) => sum + t, 0) / validCycleTimes.length
        : 0;

      // Efficiency = target_cycle_time / avg_actual_cycle_time * 100
      const avgTarget = cycles.length > 0
        ? cycles.reduce((sum: number, c: any) => sum + (c.target_cycle_time || 3), 0) / cycles.length
        : 3;
      const avgEff = validCycleTimes.length > 0
        ? (avgTarget / (validCycleTimes.reduce((s: number, t: number) => s + t, 0) / validCycleTimes.length)) * 100
        : 0;

      const runtimeMinutes = cycles.length * avgCycleTime;
      const runtimeHours = runtimeMinutes / 60;
      const ibcPerHour = runtimeHours > 0 ? Math.round((cycles.length / runtimeHours) * 10) / 10 : 0;

      this.tableData.push({
        period: period,
        date: date,
        cycles: cycles.length,
        avgCycleTime: Math.round(avgCycleTime * 10) / 10,
        efficiency: Math.round(avgEff),
        avgProdPct: Math.round(avgEff),
        ibcPerHour: ibcPerHour,
        runtime: Math.round(runtimeMinutes * 10) / 10,
        clickable: this.viewMode !== 'day'
      });
    });

    this.tableData.sort((a, b) => a.date.getTime() - b.date.getTime());

    // Compute total IBC/h
    const totalRuntime = this.tableData.reduce((sum, r) => sum + r.runtime, 0);
    const totalRuntimeH = totalRuntime / 60;
    this.totalIbcPerHour = totalRuntimeH > 0 ? Math.round((this.totalCycles / totalRuntimeH) * 10) / 10 : 0;
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
    return 'Cykeldata';
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

  setTab(tab: 'overview' | 'production' | 'quality' | 'operators' | 'analysis') {
    this.activeTab = tab;
    if (tab === 'production' && !this.heatmapLoadedOnce) {
      this.heatmapLoadedOnce = true;
      this.loadHeatmap();
    }
  }

  onHeatmapDaysChange() {
    this.heatmapUseCustomRange = false;
    this.heatmapCustomFrom = '';
    this.heatmapCustomTo = '';
    this.loadHeatmap();
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
    clearTimeout(this.exportFeedbackTimer);
    this.exportFeedbackTimer = setTimeout(() => { if (!this.destroy$.closed) this.exportChartFeedback = false; }, 2000);
  }

  exportCSV() {
    if (this.tableData.length === 0) return;

    const header = ['Period', 'IBC OK', 'Cykeltid (min)', 'Effektivitet (%)', 'IBC/h'];
    const rows = this.tableData.map(row => [
      row.period,
      row.cycles,
      row.avgCycleTime,
      row.efficiency,
      row.ibcPerHour
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
        'IBC OK': row.cycles,
        'Cykeltid (min)': row.avgCycleTime,
        'Effektivitet (%)': row.efficiency,
        'IBC/h': row.ibcPerHour
      }));
      const ws = XLSX.utils.json_to_sheet(data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Statistik');
      XLSX.writeFile(wb, `rebotling-statistik-${this.breadcrumb.join('-')}.xlsx`);
    });
  }

  // Visibility toggles for on-demand panels
  showDetailTable: boolean = false;

  // Rådata modal
  rawCycles: any[] = [];
  rawCyclesSorted: any[] = [];
  rawSortColumn: string = 'datum';
  rawSortAsc: boolean = false;
  showRawDataModal: boolean = false;

  showWeekComparison: boolean = false;
  showPrediktion: boolean = false;
  showOeeDeepDive: boolean = false;
  showCycleTrend: boolean = false;

  // ---- Dashboard Layout ----
  dashboardLayout: DashboardWidgetEntry[] = [];
  availableWidgets: DashboardAvailableWidget[] = [];
  showLayoutConfig: boolean = false;
  layoutSaving: boolean = false;
  layoutLoaded: boolean = false;

  /** Ladda sparad dashboard-layout vid init */
  loadDashboardLayout(): void {
    this.rebotlingService.getDashboardLayout().pipe(
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res.success && res.layout) {
          this.dashboardLayout = res.layout;
        } else {
          this.dashboardLayout = this.getDefaultLayout();
        }
        this.layoutLoaded = true;
      },
      error: () => {
        this.dashboardLayout = this.getDefaultLayout();
        this.layoutLoaded = true;
      }
    });

    this.rebotlingService.getAvailableWidgets().pipe(
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (res.success && res.widgets) {
          this.availableWidgets = res.widgets;
        }
      }
    });
  }

  private getDefaultLayout(): DashboardWidgetEntry[] {
    const ids = ['produktionspuls', 'veckotrend', 'oee-gauge', 'bonus-simulator',
                 'leaderboard', 'kassationsanalys-sammanfattning', 'alerts-sammanfattning', 'produktionsmal'];
    return ids.map((id, i) => ({ id, visible: true, order: i }));
  }

  isWidgetVisible(widgetId: string): boolean {
    if (!this.layoutLoaded) return true; // Visa allt tills layout laddats
    const entry = this.dashboardLayout.find(w => w.id === widgetId);
    return entry ? entry.visible : true;
  }

  getWidgetName(widgetId: string): string {
    const w = this.availableWidgets.find(aw => aw.id === widgetId);
    return w ? w.namn : widgetId;
  }

  getWidgetDescription(widgetId: string): string {
    const w = this.availableWidgets.find(aw => aw.id === widgetId);
    return w ? w.beskrivning : '';
  }

  toggleWidgetVisibility(widgetId: string): void {
    const entry = this.dashboardLayout.find(w => w.id === widgetId);
    if (entry) {
      entry.visible = !entry.visible;
    }
  }

  moveWidgetUp(index: number): void {
    if (index <= 0) return;
    const temp = this.dashboardLayout[index];
    this.dashboardLayout[index] = this.dashboardLayout[index - 1];
    this.dashboardLayout[index - 1] = temp;
    this.reorderWidgets();
  }

  moveWidgetDown(index: number): void {
    if (index >= this.dashboardLayout.length - 1) return;
    const temp = this.dashboardLayout[index];
    this.dashboardLayout[index] = this.dashboardLayout[index + 1];
    this.dashboardLayout[index + 1] = temp;
    this.reorderWidgets();
  }

  private reorderWidgets(): void {
    this.dashboardLayout.forEach((w, i) => w.order = i);
  }

  saveDashboardLayout(): void {
    this.layoutSaving = true;
    this.rebotlingService.saveDashboardLayout(this.dashboardLayout).pipe(
      takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        this.layoutSaving = false;
        if (res.success) {
          this.showLayoutConfig = false;
        }
      },
      error: () => {
        this.layoutSaving = false;
      }
    });
  }

  resetDashboardLayout(): void {
    this.dashboardLayout = this.getDefaultLayout();
  }

  toggleLayoutConfig(): void {
    this.showLayoutConfig = !this.showLayoutConfig;
  }

  /** Sorterad lista av widgets baserat på order */
  get sortedLayout(): DashboardWidgetEntry[] {
    return [...this.dashboardLayout].sort((a, b) => a.order - b.order);
  }

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
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }

  // ---- Rådata modal ----

  async openRawDataModal(): Promise<void> {
    this.showRawDataModal = true;
    const { default: Modal } = await import('bootstrap/js/dist/modal');
    setTimeout(() => {
      const el = document.getElementById('rawDataModal');
      if (el) {
        const modal = new Modal(el);
        modal.show();
        el.addEventListener('hidden.bs.modal', () => {
          this.showRawDataModal = false;
        }, { once: true });
      }
    });
  }

  sortRawCycles(column?: string): void {
    if (column) {
      if (this.rawSortColumn === column) {
        this.rawSortAsc = !this.rawSortAsc;
      } else {
        this.rawSortColumn = column;
        this.rawSortAsc = true;
      }
    }
    const col = this.rawSortColumn;
    const asc = this.rawSortAsc;
    this.rawCyclesSorted = [...this.rawCycles].sort((a, b) => {
      let va = a[col];
      let vb = b[col];
      // Numeric columns
      if (col === 'cycle_time' || col === 'ibc_count' || col === 'ibc_ok' || col === 'ibc_ej_ok' ||
          col === 'skiftraknare' || col === 'produktion_procent' || col === 'bur_ej_ok' ||
          col === 'runtime_plc' || col === 'rasttime') {
        va = parseFloat(va) || 0;
        vb = parseFloat(vb) || 0;
      } else if (col === 'datum') {
        va = new Date(va).getTime();
        vb = new Date(vb).getTime();
      } else {
        va = String(va || '').toLowerCase();
        vb = String(vb || '').toLowerCase();
      }
      if (va < vb) return asc ? -1 : 1;
      if (va > vb) return asc ? 1 : -1;
      return 0;
    });
  }

  getRawSortIcon(col: string): string {
    if (this.rawSortColumn !== col) return 'fas fa-sort';
    return this.rawSortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }
}
