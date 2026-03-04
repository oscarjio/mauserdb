import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import { RebotlingService, OEETrendDay, WeekComparisonDay, BestShift, CycleHistogramResponse, SPCResponse, ChartAnnotation, QualityTrendDay, QualityTrendResponse, OeeWaterfallResponse, WeekdayStatsEntry, ProductionEvent, CycleByOperatorEntry, CycleByOperatorResponse } from '../../services/rebotling.service';
import { AuthService } from '../../services/auth.service';

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
  runtime: number;
  clickable: boolean;
}

@Component({
  standalone: true,
  selector: 'app-rebotling-statistik',
  templateUrl: './rebotling-statistik.html',
  styleUrls: ['./rebotling-statistik.css'],
  imports: [CommonModule, FormsModule]
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
  todayStr: string = new Date().toISOString().slice(0, 10);
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

  // Veckojämförelse-panel
  weekComparisonLoaded: boolean = false;
  weekComparisonLoading: boolean = false;
  weekComparisonThisWeek: WeekComparisonDay[] = [];
  weekComparisonPrevWeek: WeekComparisonDay[] = [];
  private weekComparisonChart: Chart | null = null;

  // Skiftmålsprediktor
  prediktionLoaded: boolean = false;
  prediktionLoading: boolean = false;
  prediktionIBC: number = 0;
  prediktionMal: number = 0;
  prediktionPrognos: number = 0;
  prediktionPct: number = 0;
  prediktionRunningHours: number = 0;
  prediktionRemainingHours: number = 0;

  // OEE deep-dive
  oeeLoaded: boolean = false;
  oeeLoading: boolean = false;
  oeeData: any = null;
  oeeTrendDays: OEETrendDay[] = [];
  private oeeTrendChart: Chart | null = null;
  oeeGranularity: 'day' | 'shift' = 'day';

  // Veckojämförelse granularitet
  weekGranularity: 'day' | 'shift' = 'day';

  // Cykeltrend
  cycleTrendLoaded: boolean = false;
  cycleTrendLoading: boolean = false;
  cycleTrendDays: number = 30;
  cycleTrendData: any[] = [];
  cycleTrendGranularity: 'day' | 'shift' = 'day';
  private cycleTrendChart: Chart | null = null;

  // Cykeltids-histogram
  histogramDate: string = new Date().toISOString().split('T')[0];
  histogramLoaded: boolean = false;
  histogramLoading: boolean = false;
  histogramBuckets: { label: string; count: number }[] = [];
  histogramStats: { n: number; snitt: number; p50: number; p90: number; p95: number } | null = null;
  private histogramChart: Chart | null = null;

  // SPC-kontrollkort
  spcDays: number = 7;
  spcLoaded: boolean = false;
  spcLoading: boolean = false;
  spcMean: number = 0;
  spcStddev: number = 0;
  spcUCL: number = 0;
  spcLCL: number = 0;
  spcN: number = 0;
  private spcChart: Chart | null = null;

  // Cykeltid per operatör
  cycleByOpDays: number = 30;
  cycleByOpLoaded: boolean = false;
  cycleByOpLoading: boolean = false;
  cycleByOpData: CycleByOperatorEntry[] = [];
  private cycleByOpChart: Chart | null = null;

  // Annotations i OEE- och cykeltrend-grafer
  chartAnnotations: ChartAnnotation[] = [];

  // Kvalitetstrendkort
  qualityTrendDays: number = 30;
  qualityTrendLoaded: boolean = false;
  qualityTrendLoading: boolean = false;
  qualityTrendDays$ = [14, 30, 90];
  qualityTrendData: QualityTrendDay[] = [];
  qualityTrendKpi: { avg: number | null; min: number | null; max: number | null; trend: 'up' | 'down' | 'stable' } = { avg: null, min: null, max: null, trend: 'stable' };
  private qualityTrendChart: Chart | null = null;

  // Waterfalldiagram OEE
  oeeWaterfallDays: number = 30;
  oeeWaterfallLoaded: boolean = false;
  oeeWaterfallLoading: boolean = false;
  oeeWaterfallData: OeeWaterfallResponse | null = null;
  private oeeWaterfallChart: Chart | null = null;

  // Veckodag-analys
  weekdayData: WeekdayStatsEntry[] = [];
  weekdayLoading: boolean = false;
  weekdayDagar: number = 90;
  private weekdayChart: Chart | null = null;

  // Produktionsrytm per timme
  hourlyRhythm: any[] = [];
  hourlyRhythmLoading = false;
  private hourlyRhythmChart: Chart | null = null;
  hourlyRhythmDays = 30;

  // Produktionshändelse-annotationer
  productionEvents: ProductionEvent[] = [];
  productionEventsLoading: boolean = false;
  showEventsAdmin: boolean = false;
  isAdmin: boolean = false;
  newEvent: { event_date: string; title: string; event_type: string; description: string } = {
    event_date: '',
    title: '',
    event_type: 'ovrigt',
    description: ''
  };
  eventsAdminMessage: string = '';
  eventsAdminError: string = '';
  eventsAdminSaving: boolean = false;

  private destroy$ = new Subject<void>();
  private chartUpdateTimer: any = null;

  constructor(
    private rebotlingService: RebotlingService,
    private route: ActivatedRoute,
    private router: Router,
    private auth: AuthService
  ) {}

  @HostListener('document:mouseup')
  onDocumentMouseUp() {
    this.isDragging = false;
  }

  ngOnInit() {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(val => {
      this.isAdmin = val?.role === 'admin';
    });
    this.applyStateFromUrl();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
    this.loadCycleHistogram();
    this.loadSPC();
    this.loadCycleByOperator();
    this.loadQualityTrend();
    this.loadOeeWaterfall();
    this.loadWeekdayStats();
    this.loadProductionEvents();
    this.loadHourlyRhythm();
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
          const d = new Date(s);
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

  ngOnDestroy() {
    clearTimeout(this.chartUpdateTimer);
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
    this.weekComparisonChart?.destroy();
    this.weekComparisonChart = null;
    this.oeeTrendChart?.destroy();
    this.oeeTrendChart = null;
    this.cycleTrendChart?.destroy();
    this.cycleTrendChart = null;
    this.histogramChart?.destroy();
    this.histogramChart = null;
    this.spcChart?.destroy();
    this.spcChart = null;
    this.cycleByOpChart?.destroy();
    this.cycleByOpChart = null;
    this.qualityTrendChart?.destroy();
    this.qualityTrendChart = null;
    this.oeeWaterfallChart?.destroy();
    this.oeeWaterfallChart = null;
    this.weekdayChart?.destroy();
    this.weekdayChart = null;
    this.hourlyRhythmChart?.destroy();
    this.hourlyRhythmChart = null;
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
      catchError((err) => {
        console.error('Error loading statistics:', err);
        this.error = 'Kunde inte ladda statistik från backend';
        this.loading = false;
        this.loadMockData();
        return of({ success: false, data: null } as any);
      }),
      takeUntil(this.destroy$)
    ).subscribe(response => {
      if (!response || !response.success) return;
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

  loadMockData() {
    const mockData = this.generateMockData();
    this.lastStatisticsData = mockData;
    this.updateStatistics(mockData);
    this.updateChart(mockData);
    this.updateTable(mockData);
  }

  generateMockData() {
    const cycles: any[] = [];
    const onoff_events: any[] = [];

    const { start, end } = this.getDateRange();
    const startDate = new Date(start);
    const endDate = new Date(end);


    let currentDate = new Date(startDate);

    while (currentDate <= endDate) {
      const dayOfWeek = currentDate.getDay();

      // Skip weekends
      if (dayOfWeek !== 0 && dayOfWeek !== 6) {
        // Working hours 6-18
        for (let hour = 6; hour < 18; hour++) {
          const shouldRun = Math.random() > 0.2; // 80% chance running

          if (shouldRun) {
            // Generate 8-15 cycles per hour
            const numCycles = 8 + Math.floor(Math.random() * 8);

            for (let c = 0; c < numCycles; c++) {
              const minute = Math.floor(Math.random() * 60);
              const cycleDate = new Date(currentDate);
              cycleDate.setHours(hour, minute, 0, 0);

              const cycleTime = 8 + Math.random() * 4; // 8-12 minutes
              const targetCycleTime = 10; // Mock target

              cycles.push({
                datum: cycleDate.toISOString(),
                ibc_count: 1,
                produktion_procent: 85 + Math.random() * 15,
                skiftraknare: 1,
                cycle_time: cycleTime,
                target_cycle_time: targetCycleTime
              });
            }

            // Add running events - more detailed for day view
            if (this.viewMode === 'day') {
              // Start of hour
              const startDate = new Date(currentDate);
              startDate.setHours(hour, 2, 0, 0);
              onoff_events.push({
                datum: startDate.toISOString(),
                running: true
              });

              // Maybe stop mid-hour (rast)
              if (Math.random() > 0.8) {
                const stopDate = new Date(currentDate);
                stopDate.setHours(hour, 35, 0, 0);
                onoff_events.push({
                  datum: stopDate.toISOString(),
                  running: false
                });

                const resumeDate = new Date(currentDate);
                resumeDate.setHours(hour, 48, 0, 0);
                onoff_events.push({
                  datum: resumeDate.toISOString(),
                  running: true
                });
              }
            } else {
              // For year/month view: one event per hour
              const eventDate = new Date(currentDate);
              eventDate.setHours(hour, 0, 0, 0);
              onoff_events.push({
                datum: eventDate.toISOString(),
                running: true
              });
            }
          } else {
            // Not running - add stopped event
            const eventDate = new Date(currentDate);
            eventDate.setHours(hour, 0, 0, 0);
            onoff_events.push({
              datum: eventDate.toISOString(),
              running: false
            });
          }
        }
      }

      currentDate.setDate(currentDate.getDate() + 1);
    }

    const avgCycleTime = cycles.length > 0
      ? cycles.reduce((sum, c) => sum + c.cycle_time, 0) / cycles.length
      : 0;

    const avgProduction = cycles.length > 0
      ? cycles.reduce((sum, c) => sum + c.produktion_procent, 0) / cycles.length
      : 0;

    const targetCycleTime = 10; // Mock target

    return {
      cycles,
      onoff_events,
      summary: {
        total_cycles: cycles.length,
        avg_production_percent: avgProduction,
        avg_cycle_time: Math.round(avgCycleTime * 10) / 10,
        target_cycle_time: targetCycleTime,
        total_runtime_hours: onoff_events.filter(e => e.running).length * 0.9,
        days_with_production: Math.ceil((endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24))
      }
    };
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
    if (this.productionChart) {
      this.productionChart.destroy();
      this.productionChart = null;
    }

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
              } catch (e) {
                console.error('Background draw error:', e);
              }
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
              } catch (e) {
                console.error('Selection preview draw error:', e);
              }
            }
          }
        }]
      });

      // Aktivera interaktiv markering/zoom i grafen för dag-vy
      this.attachChartSelectionHandlers(this.productionChart);

    } catch (error) {
      console.error('❌ Chart creation error:', error);
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
    let lastMin = 0;
    let stopStart: number | null = null;

    for (const ev of events) {
      if (currentRunning && !ev.running) {
        // Maskinen stannar
        totalRunMinutes += ev.min - lastMin;
        stopStart = ev.min;
        currentRunning = false;
      } else if (!currentRunning && ev.running) {
        // Maskinen startar
        if (stopStart !== null) {
          const stopDur = ev.min - stopStart;
          if (stopDur > longestStop) { longestStop = stopDur; }
        }
        currentRunning = true;
      }
      lastMin = ev.min;
    }

    // Om maskinen fortfarande körde vid sista händelse: lägg till tid fram till sista händelse
    if (currentRunning) {
      totalRunMinutes += lastMin - (events.find((e: any) => e.running)?.min ?? lastMin);
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
    this.productionChart?.destroy();
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
      const dateStr = d.toISOString().split('T')[0];
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
      const dateStr = d.toISOString().split('T')[0];
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

  // ======== VECKOJÄMFÖRELSE ========

  setWeekGranularity(g: 'day' | 'shift') {
    this.weekGranularity = g;
    this.weekComparisonLoaded = false;
    this.loadWeekComparison();
  }

  loadWeekComparison() {
    if (this.weekComparisonLoading) return;
    this.weekComparisonLoading = true;
    this.rebotlingService.getWeekComparison(this.weekGranularity).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(res => {
      this.weekComparisonLoading = false;
      if (res?.success && res.data) {
        this.weekComparisonThisWeek = res.data.this_week;
        this.weekComparisonPrevWeek = res.data.prev_week;
        this.weekComparisonLoaded = true;
        setTimeout(() => this.renderWeekComparisonChart(), 100);
      }
    });
  }

  private renderWeekComparisonChart() {
    this.weekComparisonChart?.destroy();
    const canvas = document.getElementById('weekComparisonChart') as HTMLCanvasElement;
    if (!canvas) return;

    const weekdays = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
    const labels = this.weekComparisonThisWeek.map(d => {
      if (d.label) return d.label;
      const wd = new Date(d.date + 'T00:00:00').getDay();
      const wdIdx = wd === 0 ? 6 : wd - 1;
      return `${weekdays[wdIdx]} ${d.date.substring(5)}`;
    });

    this.weekComparisonChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Förra veckan',
            data: this.weekComparisonPrevWeek.map(d => d.ibc_ok),
            backgroundColor: 'rgba(113,128,150,0.5)',
            borderColor: 'rgba(160,174,192,0.8)',
            borderWidth: 1,
            borderRadius: 4
          },
          {
            label: 'Denna vecka',
            data: this.weekComparisonThisWeek.map(d => d.ibc_ok),
            backgroundColor: 'rgba(66,153,225,0.7)',
            borderColor: 'rgba(99,179,237,1)',
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 12 } } },
          tooltip: {
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              afterLabel: (ctx: any) => {
                const thisW = this.weekComparisonThisWeek[ctx.dataIndex]?.ibc_ok ?? 0;
                const prevW = this.weekComparisonPrevWeek[ctx.dataIndex]?.ibc_ok ?? 0;
                if (ctx.datasetIndex === 1 && prevW > 0) {
                  const diff = thisW - prevW;
                  const pct = Math.round((diff / prevW) * 100);
                  return `${diff >= 0 ? '+' : ''}${diff} IBC (${pct >= 0 ? '+' : ''}${pct}% vs förra)`;
                }
                return '';
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: { beginAtZero: true, ticks: { color: '#718096' }, grid: { color: 'rgba(255,255,255,0.05)' },
               title: { display: true, text: 'IBC OK', color: '#718096' } }
        }
      }
    });
  }

  getWeekComparisonTotal(week: WeekComparisonDay[]): number {
    return week.reduce((s, d) => s + d.ibc_ok, 0);
  }

  getWeekComparisonDiff(): number {
    return this.getWeekComparisonTotal(this.weekComparisonThisWeek) -
           this.getWeekComparisonTotal(this.weekComparisonPrevWeek);
  }

  getWeekComparisonDiffPct(): number {
    const prev = this.getWeekComparisonTotal(this.weekComparisonPrevWeek);
    if (prev === 0) return 0;
    return Math.round((this.getWeekComparisonDiff() / prev) * 100);
  }

  // ======== SKIFTMÅLSPREDIKTOR ========

  loadPrediktion() {
    if (this.prediktionLoading) return;
    this.prediktionLoading = true;

    // Hämta dagsmål från admin-settings
    this.rebotlingService.getLiveStats().pipe(
      timeout(6000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(liveRes => {
      if (liveRes?.success && liveRes.data) {
        this.prediktionIBC = liveRes.data.ibcToday;
        this.prediktionMal = liveRes.data.rebotlingTarget;
      }
      this.computePrediktion();
      this.prediktionLoading = false;
      this.prediktionLoaded = true;
    });
  }

  private computePrediktion() {
    const now = new Date();
    const dayStartHour = 6;   // Antag produktionsdagen börjar 06:00
    const dayEndHour = 22;    // och slutar 22:00

    const minutesInDay = (dayEndHour - dayStartHour) * 60;
    const minutesSinceStart = Math.max(0, (now.getHours() - dayStartHour) * 60 + now.getMinutes());
    const minutesRemaining = Math.max(0, minutesInDay - minutesSinceStart);

    this.prediktionRunningHours = Math.round(minutesSinceStart / 60 * 10) / 10;
    this.prediktionRemainingHours = Math.round(minutesRemaining / 60 * 10) / 10;

    if (minutesSinceStart > 0 && this.prediktionIBC > 0) {
      const ratePerMin = this.prediktionIBC / minutesSinceStart;
      this.prediktionPrognos = Math.round(this.prediktionIBC + ratePerMin * minutesRemaining);
    } else {
      this.prediktionPrognos = 0;
    }

    if (this.prediktionMal > 0) {
      this.prediktionPct = Math.min(150, Math.round((this.prediktionPrognos / this.prediktionMal) * 100));
    } else {
      this.prediktionPct = 0;
    }
  }

  getPrediktionClass(): string {
    if (this.prediktionPct >= 100) return 'bg-success';
    if (this.prediktionPct >= 75) return 'bg-warning';
    return 'bg-danger';
  }

  getPrediktionTextClass(): string {
    if (this.prediktionPct >= 100) return 'text-success';
    if (this.prediktionPct >= 75) return 'text-warning';
    return 'text-danger';
  }

  // ======== OEE DEEP-DIVE ========

  setOeeGranularity(g: 'day' | 'shift') {
    this.oeeGranularity = g;
    this.oeeLoaded = false;
    this.loadOEE();
  }

  loadOEE() {
    if (this.oeeLoading) return;
    this.oeeLoading = true;

    // Beräkna datumintervallet för 30 dagar (samma som OEE-trendgrafen)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 29);
    const fmt = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    this.loadAnnotations(fmt(startDate), fmt(endDate));

    this.rebotlingService.getOEE('month').pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(oeeRes => {
      if (oeeRes?.success && oeeRes.data) {
        this.oeeData = oeeRes.data;
      }

      this.rebotlingService.getOEETrend(30, this.oeeGranularity).pipe(
        timeout(8000),
        takeUntil(this.destroy$),
        catchError(() => of(null))
      ).subscribe(trendRes => {
        this.oeeLoading = false;
        if (trendRes?.success && trendRes.data) {
          this.oeeTrendDays = trendRes.data;
          this.oeeLoaded = true;
          setTimeout(() => this.renderOEETrendChart(), 100);
        } else {
          this.oeeLoaded = true;
        }
      });
    });
  }

  private renderOEETrendChart() {
    this.oeeTrendChart?.destroy();
    const canvas = document.getElementById('oeeTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.oeeTrendDays.length) return;

    const labels = this.oeeTrendDays.map(d => d.label ?? d.date.substring(5));
    // Bygg events-annotationers till verticalAnnotations-plugin format
    const productionEventAnnotations: ChartAnnotation[] = this.productionEvents
      .filter(e => {
        const shortDate = e.event_date.substring(5);
        return labels.some(l => l.includes(shortDate));
      })
      .map(e => ({
        date: e.event_date,
        dateShort: e.event_date.substring(5),
        type: 'audit' as const,
        label: e.title,
        eventType: e.event_type,
        color: this.eventColor(e.event_type)
      }));
    const combinedAnnotations = [...this.chartAnnotations, ...productionEventAnnotations];

    this.oeeTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: this.oeeTrendDays.map(d => d.oee),
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            borderWidth: 2,
            yAxisID: 'y'
          },
          {
            label: 'Tillgänglighet %',
            data: this.oeeTrendDays.map(d => d.availability),
            borderColor: '#48bb78',
            borderDash: [5, 3],
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 1.5,
            fill: false,
            yAxisID: 'y'
          },
          {
            label: 'Prestanda %',
            data: this.oeeTrendDays.map(d => d.performance),
            borderColor: '#ecc94b',
            borderDash: [5, 3],
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 1.5,
            fill: false,
            yAxisID: 'y'
          },
          {
            label: 'Kvalitet %',
            data: this.oeeTrendDays.map(d => d.quality),
            borderColor: '#fc8181',
            borderDash: [5, 3],
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 1.5,
            fill: false,
            yAxisID: 'y'
          },
          {
            label: 'WCM 85%',
            data: labels.map(() => 85),
            borderColor: '#48bb78',
            borderDash: [8, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
            tension: 0,
            type: 'line' as const
          },
          {
            label: 'Branschsnitt 70%',
            data: labels.map(() => 70),
            borderColor: '#ed8936',
            borderDash: [4, 4],
            borderWidth: 1,
            pointRadius: 0,
            fill: false,
            tension: 0,
            type: 'line' as const
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1
          },
          verticalAnnotations: {
            annotations: combinedAnnotations
          }
        } as any,
        scales: {
          x: { ticks: { color: '#718096', maxTicksLimit: 10, maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { beginAtZero: true, max: 100, ticks: { color: '#718096', callback: (v: any) => v + '%' }, grid: { color: 'rgba(255,255,255,0.04)' } }
        }
      }
    });
  }

  getOEEBarWidth(value: number): number {
    return Math.min(100, Math.max(0, value));
  }

  getOEEBarClass(value: number): string {
    if (value >= 75) return 'bg-success';
    if (value >= 50) return 'bg-warning';
    return 'bg-danger';
  }

  // ======== CYKELTIDS-HISTOGRAM ========

  loadCycleHistogram() {
    if (this.histogramLoading) return;
    this.histogramLoading = true;

    this.rebotlingService.getCycleHistogram(this.histogramDate).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: CycleHistogramResponse | null) => {
      this.histogramLoading = false;
      if (res?.success && res.data) {
        this.histogramBuckets = res.data.buckets;
        this.histogramStats = res.data.stats;
        this.histogramLoaded = true;
        setTimeout(() => this.renderHistogramChart(), 100);
      } else {
        this.histogramLoaded = true;
      }
    });
  }

  private renderHistogramChart() {
    this.histogramChart?.destroy();
    const canvas = document.getElementById('cycleHistogramChart') as HTMLCanvasElement;
    if (!canvas || !this.histogramBuckets.length) return;

    const labels = this.histogramBuckets.map(b => b.label);
    const counts = this.histogramBuckets.map(b => b.count);

    this.histogramChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal skift',
            data: counts,
            backgroundColor: 'rgba(72, 187, 120, 0.75)',
            borderColor: '#48bb78',
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(20,20,30,0.95)',
            titleColor: '#fff',
            bodyColor: '#e2e8f0',
            borderColor: '#48bb78',
            borderWidth: 1,
            callbacks: {
              label: (ctx: any) => ` ${ctx.parsed.y} st`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Antal skift', color: '#a0aec0', font: { size: 12 } }
          }
        }
      }
    });
  }

  onHistogramDateChange() {
    this.histogramLoaded = false;
    this.loadCycleHistogram();
  }

  // ======== SPC-KONTROLLKORT ========

  loadSPC() {
    if (this.spcLoading) return;
    this.spcLoading = true;

    this.rebotlingService.getSPC(this.spcDays).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: SPCResponse | null) => {
      this.spcLoading = false;
      if (res?.success && res.data) {
        this.spcMean   = res.data.mean;
        this.spcStddev = res.data.stddev;
        this.spcUCL    = res.data.ucl;
        this.spcLCL    = res.data.lcl;
        this.spcN      = res.data.n;
        this.spcLoaded = true;
        setTimeout(() => this.renderSPCChart(res.data!.points), 100);
      } else {
        this.spcLoaded = true;
      }
    });
  }

  private renderSPCChart(points: { label: string; ibc_per_hour: number }[]) {
    this.spcChart?.destroy();
    const canvas = document.getElementById('spcChart') as HTMLCanvasElement;
    if (!canvas || !points.length) return;

    const labels  = points.map(p => p.label);
    const values  = points.map(p => p.ibc_per_hour);
    const uclArr  = points.map(() => this.spcUCL);
    const lclArr  = points.map(() => this.spcLCL);
    const meanArr = points.map(() => this.spcMean);

    this.spcChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data: values,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 7,
            borderWidth: 2,
            yAxisID: 'y'
          },
          {
            label: 'UCL (Övre kontrollgräns)',
            data: uclArr,
            borderColor: '#fc8181',
            borderDash: [6, 3],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            yAxisID: 'y'
          },
          {
            label: 'LCL (Nedre kontrollgräns)',
            data: lclArr,
            borderColor: '#ed8936',
            borderDash: [6, 3],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            yAxisID: 'y'
          },
          {
            label: 'Medelvärde (X\u0305)',
            data: meanArr,
            borderColor: '#48bb78',
            borderDash: [4, 4],
            tension: 0,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            yAxisID: 'y'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 14 },
            grid: { color: 'rgba(255,255,255,0.04)' }
          },
          y: {
            beginAtZero: false,
            ticks: { color: '#718096', callback: (v: any) => v + ' IBC/h' },
            grid: { color: 'rgba(255,255,255,0.04)' },
            title: { display: true, text: 'IBC per timme', color: '#a0aec0', font: { size: 12 } }
          }
        }
      }
    });
  }

  onSPCDaysChange() {
    this.spcLoaded = false;
    this.loadSPC();
  }

  onCycleByOpDaysChange() {
    this.cycleByOpLoaded = false;
    this.loadCycleByOperator();
  }

  loadCycleByOperator() {
    if (this.cycleByOpLoading) return;
    this.cycleByOpLoading = true;

    const fmt = (d: Date) => d.toISOString().split('T')[0];
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - (this.cycleByOpDays - 1));

    this.rebotlingService.getCycleByOperator(fmt(startDate), fmt(endDate)).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: CycleByOperatorResponse | null) => {
      this.cycleByOpLoading = false;
      if (res?.success && res.data) {
        this.cycleByOpData = res.data;
        this.cycleByOpLoaded = true;
        setTimeout(() => this.renderCycleByOpChart(), 100);
      } else {
        this.cycleByOpLoaded = true;
        this.cycleByOpData = [];
      }
    });
  }

  private renderCycleByOpChart() {
    this.cycleByOpChart?.destroy();
    const canvas = document.getElementById('cycleByOpChart') as HTMLCanvasElement;
    if (!canvas || !this.cycleByOpData.length) return;

    const sorted = [...this.cycleByOpData].sort((a, b) => a.snitt_cykel_sek - b.snitt_cykel_sek);
    const labels = sorted.map(op => op.initialer);
    const values = sorted.map(op => op.snitt_cykel_sek);
    const bast   = sorted.map(op => op.bast_cykel_sek);
    const samst  = sorted.map(op => op.samst_cykel_sek);

    // Beräkna median för färgläggning
    const median = (() => {
      const v = [...values].sort((a, b) => a - b);
      const mid = Math.floor(v.length / 2);
      return v.length % 2 === 0 ? (v[mid - 1] + v[mid]) / 2 : v[mid];
    })();

    const colors = values.map(v => {
      if (v < median * 0.95) return 'rgba(72, 187, 120, 0.8)';   // grön — under median
      if (v > median * 1.05) return 'rgba(252, 129, 129, 0.8)';  // röd  — över median
      return 'rgba(66, 153, 225, 0.8)';                           // blå  — nära median
    });
    const borderColors = colors.map(c => c.replace('0.8)', '1)'));

    this.cycleByOpChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Snitt cykeltid (sek)',
            data: values,
            backgroundColor: colors,
            borderColor: borderColors,
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.96)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1,
            callbacks: {
              title: (items: any[]) => {
                const idx = items[0].dataIndex;
                return sorted[idx].namn;
              },
              label: (ctx: any) => {
                const op = sorted[ctx.dataIndex];
                return [
                  ` Snitt: ${op.snitt_cykel_sek} sek`,
                  ` Bäst:  ${op.bast_cykel_sek} sek`,
                  ` Sämst: ${op.samst_cykel_sek} sek`,
                  ` Skift: ${op.antal_skift} st`,
                  ` Total IBC: ${op.total_ibc}`,
                ];
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', callback: (v: any) => v + ' s' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Cykeltid (sekunder)', color: '#a0aec0', font: { size: 12 } }
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid: { color: 'rgba(255,255,255,0.04)' }
          }
        }
      }
    });
  }

  // ======== ANNOTATIONER ========

  /**
   * Hämtar annotationer för OEE-trend och cykeltrend.
   * Anropas med samma datumintervall (senaste 30 dagar) som de berörda graferna.
   * Fel ignoreras — annotationer är ett additivt lager, inte kritisk data.
   */
  loadAnnotations(startDate: string, endDate: string) {
    this.rebotlingService.getAnnotations(startDate, endDate).pipe(
      timeout(8000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(res => {
      if (res?.success && res.annotations) {
        this.chartAnnotations = res.annotations.map(ann => ({
          date: ann.date,
          // dateShort = MM-DD, t.ex. "02-14" — matchar det kortade datumet i graf-labels
          dateShort: ann.date.substring(5),
          type: ann.type as 'stopp' | 'low_production' | 'audit',
          label: ann.label
        }));
        // Rendera om graferna med annotationer om de redan är inladdade
        if (this.oeeLoaded && this.oeeTrendDays.length) {
          setTimeout(() => this.renderOEETrendChart(), 0);
        }
        if (this.cycleTrendLoaded && this.cycleTrendData.length) {
          setTimeout(() => this.renderCycleTrendChart(), 0);
        }
      }
    });
  }

  // ======== KVALITETSTRENDKORT ========

  loadQualityTrend() {
    if (this.qualityTrendLoading) return;
    this.qualityTrendLoading = true;
    this.qualityTrendLoaded = false;

    this.rebotlingService.getQualityTrend(this.qualityTrendDays).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: QualityTrendResponse | null) => {
      this.qualityTrendLoading = false;
      if (res?.success && res.days) {
        this.qualityTrendData = res.days;
        this.qualityTrendKpi = res.kpi ?? { avg: null, min: null, max: null, trend: 'stable' };
        this.qualityTrendLoaded = true;
        setTimeout(() => this.renderQualityTrendChart(), 100);
      } else {
        this.qualityTrendLoaded = true;
      }
    });
  }

  private renderQualityTrendChart() {
    this.qualityTrendChart?.destroy();
    const canvas = document.getElementById('qualityTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.qualityTrendData.length) return;

    const labels = this.qualityTrendData.map(d => d.date.substring(5));
    const dailyData = this.qualityTrendData.map(d => d.quality_pct);
    const rollingData = this.qualityTrendData.map(d => d.rolling_avg);

    // Mållinje vid 90%
    const targetLine = this.qualityTrendData.map(() => 90);

    this.qualityTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Daglig kvalitet %',
            data: dailyData,
            borderColor: 'rgba(236,201,75,0.9)',
            backgroundColor: 'rgba(236,201,75,0.12)',
            tension: 0.3,
            pointRadius: 3,
            borderWidth: 2,
            fill: true,
            spanGaps: true,
            yAxisID: 'y'
          },
          {
            label: '7-dagars rullande snitt',
            data: rollingData,
            borderColor: 'rgba(237,137,54,1)',
            backgroundColor: 'transparent',
            tension: 0.4,
            pointRadius: 1,
            borderWidth: 3,
            fill: false,
            spanGaps: true,
            yAxisID: 'y'
          },
          {
            label: 'Kvalitetsmål 90%',
            data: targetLine,
            borderColor: 'rgba(252,129,129,0.8)',
            backgroundColor: 'transparent',
            borderDash: [6, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
            spanGaps: true,
            yAxisID: 'y'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#ecc94b',
            borderWidth: 1,
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.parsed.y;
                return v !== null ? `${ctx.dataset.label}: ${v}%` : '';
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 20 },
            grid: { color: 'rgba(255,255,255,0.04)' }
          },
          y: {
            min: 0,
            max: 100,
            ticks: {
              color: '#718096',
              callback: (v: any) => v + '%'
            },
            grid: { color: 'rgba(255,255,255,0.06)' },
            title: { display: true, text: 'Kvalitet %', color: '#a0aec0', font: { size: 11 } }
          }
        }
      }
    });
  }

  // ======== WATERFALLDIAGRAM OEE ========

  loadOeeWaterfall() {
    if (this.oeeWaterfallLoading) return;
    this.oeeWaterfallLoading = true;
    this.oeeWaterfallLoaded = false;

    this.rebotlingService.getOeeWaterfall(this.oeeWaterfallDays).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: OeeWaterfallResponse | null) => {
      this.oeeWaterfallLoading = false;
      if (res?.success) {
        this.oeeWaterfallData = res;
        this.oeeWaterfallLoaded = true;
        setTimeout(() => this.renderOeeWaterfallChart(), 100);
      } else {
        this.oeeWaterfallLoaded = true;
      }
    });
  }

  private renderOeeWaterfallChart() {
    this.oeeWaterfallChart?.destroy();
    const canvas = document.getElementById('oeeWaterfallChart') as HTMLCanvasElement;
    if (!canvas || !this.oeeWaterfallData) return;

    const d = this.oeeWaterfallData;
    const avail = d.availability ?? 0;
    const perf = d.performance ?? 0;
    const qual = d.quality ?? 0;
    const oee = d.oee ?? 0;

    const labels = ['Tillgänglighet', 'Prestanda', 'Kvalitet', 'OEE'];
    const achieved = [avail, perf, qual, oee];
    const losses = [
      100 - avail,
      100 - perf,
      100 - qual,
      100 - oee
    ];

    this.oeeWaterfallChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Uppnått',
            data: achieved,
            backgroundColor: achieved.map(v =>
              v >= 85 ? 'rgba(72,187,120,0.85)'
              : v >= 65 ? 'rgba(236,201,75,0.85)'
              : 'rgba(252,129,129,0.85)'
            ),
            borderColor: achieved.map(v =>
              v >= 85 ? 'rgba(72,187,120,1)'
              : v >= 65 ? 'rgba(236,201,75,1)'
              : 'rgba(252,129,129,1)'
            ),
            borderWidth: 1,
            borderRadius: 4,
            stack: 'oee'
          },
          {
            label: 'Förlust',
            data: losses,
            backgroundColor: 'rgba(255,255,255,0.06)',
            borderColor: 'rgba(255,255,255,0.12)',
            borderWidth: 1,
            borderRadius: 4,
            stack: 'oee'
          }
        ]
      },
      options: {
        indexAxis: 'y' as any,
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#48bb78',
            borderWidth: 1,
            callbacks: {
              label: (ctx: any) => {
                const v = ctx.parsed.x;
                if (ctx.datasetIndex === 0) return `Uppnått: ${v.toFixed(1)}%`;
                return `Förlust: ${v.toFixed(1)}%`;
              }
            }
          }
        },
        scales: {
          x: {
            stacked: true,
            min: 0,
            max: 100,
            ticks: { color: '#718096', callback: (v: any) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.04)' }
          },
          y: {
            stacked: true,
            ticks: { color: '#e2e8f0', font: { size: 13 } },
            grid: { color: 'rgba(255,255,255,0.04)' }
          }
        }
      }
    });
  }

  // ======== CYKELTREND ========

  setCycleTrendGranularity(g: 'day' | 'shift') {
    this.cycleTrendGranularity = g;
    this.cycleTrendLoaded = false;
    this.loadCycleTrend();
  }

  loadCycleTrend() {
    if (this.cycleTrendLoading) return;
    this.cycleTrendLoading = true;

    // Hämta annotationer om de inte redan är inladdade
    if (this.chartAnnotations.length === 0) {
      const endDate = new Date();
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - (this.cycleTrendDays - 1));
      const fmt = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
      this.loadAnnotations(fmt(startDate), fmt(endDate));
    }

    this.rebotlingService.getCycleTrend(this.cycleTrendDays, this.cycleTrendGranularity).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe(res => {
      this.cycleTrendLoading = false;
      if (res?.success && res.data) {
        this.cycleTrendData = res.data.daily;
        this.cycleTrendLoaded = true;
        setTimeout(() => this.renderCycleTrendChart(), 100);
      } else {
        this.cycleTrendLoaded = true;
      }
    });
  }

  private renderCycleTrendChart() {
    this.cycleTrendChart?.destroy();
    const canvas = document.getElementById('cycleTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.cycleTrendData.length) return;

    const labels = this.cycleTrendData.map(d => d.label ?? d.dag.substring(5));
    const ibcData = this.cycleTrendData.map(d => d.total_ibc_ok);
    const ibcPerHour = this.cycleTrendData.map(d => d.avg_ibc_per_hour);

    // Kombinera auto-annotationer med manuella produktionshändelser
    const cycleProductionEventAnnotations: ChartAnnotation[] = this.productionEvents
      .filter(e => {
        const shortDate = e.event_date.substring(5);
        return labels.some(l => l.includes(shortDate));
      })
      .map(e => ({
        date: e.event_date,
        dateShort: e.event_date.substring(5),
        type: 'audit' as const,
        label: e.title,
        color: this.eventColor(e.event_type)
      } as any));
    const cycleAnnotations = [...this.chartAnnotations, ...cycleProductionEventAnnotations];

    this.cycleTrendChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC OK',
            data: ibcData,
            backgroundColor: 'rgba(66,153,225,0.6)',
            borderColor: 'rgba(99,179,237,1)',
            borderWidth: 1,
            borderRadius: 3,
            yAxisID: 'y'
          },
          {
            type: 'line' as any,
            label: 'IBC/h',
            data: ibcPerHour,
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72,187,120,0.1)',
            tension: 0.3,
            pointRadius: 2,
            borderWidth: 2,
            fill: false,
            yAxisID: 'y2'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.95)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1
          },
          verticalAnnotations: {
            annotations: cycleAnnotations
          }
        } as any,
        scales: {
          x: {
            ticks: { color: '#718096', maxRotation: 45, autoSkip: true, maxTicksLimit: 20 },
            grid: { color: 'rgba(255,255,255,0.04)' }
          },
          y: {
            beginAtZero: true,
            position: 'left',
            ticks: { color: '#718096' },
            grid: { color: 'rgba(255,255,255,0.04)' },
            title: { display: true, text: 'IBC OK', color: '#a0aec0', font: { size: 11 } }
          },
          y2: {
            beginAtZero: true,
            position: 'right',
            ticks: { color: '#48bb78', callback: (v: any) => v + '/h' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'IBC/h', color: '#48bb78', font: { size: 11 } }
          }
        }
      }
    });
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Veckodag-analys
  // ─────────────────────────────────────────────────────────────────────────────

  getWeekdayMaxIbc(): number {
    if (!this.weekdayData.length) return 0;
    return Math.max(...this.weekdayData.map(d => d.snitt_ibc));
  }

  getWeekdayMinIbc(): number {
    if (!this.weekdayData.length) return 0;
    return Math.min(...this.weekdayData.map(d => d.snitt_ibc));
  }

  loadWeekdayStats(): void {
    this.weekdayLoading = true;
    this.rebotlingService.getWeekdayStats(this.weekdayDagar).pipe(
      timeout(8000),
      catchError(() => of({ success: false, veckodagar: [] })),
      takeUntil(this.destroy$)
    ).subscribe(r => {
      this.weekdayData = r.veckodagar || [];
      this.weekdayLoading = false;
      setTimeout(() => this.buildWeekdayChart(), 50);
    });
  }

  // ======== PRODUKTIONSHÄNDELSE-ANNOTATIONER ========

  eventColor(type: string): string {
    const colors: Record<string, string> = {
      'underhall':    '#f97316',
      'ny_operator':  '#3b82f6',
      'mal_andring':  '#a855f7',
      'rekord':       '#eab308',
      'ovrigt':       '#6b7280',
    };
    return colors[type] ?? '#6b7280';
  }

  eventTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      'underhall':   'Underhåll',
      'ny_operator': 'Ny operatör',
      'mal_andring': 'Måländring',
      'rekord':      'Rekord',
      'ovrigt':      'Övrigt',
    };
    return labels[type] ?? 'Övrigt';
  }

  loadProductionEvents(): void {
    if (this.productionEventsLoading) return;
    this.productionEventsLoading = true;
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 89);
    const fmt = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    this.rebotlingService.getProductionEvents(fmt(startDate), fmt(endDate)).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.productionEventsLoading = false;
      if (res?.success && res.events) {
        this.productionEvents = res.events;
        // Rendera om grafer om de redan är laddade
        if (this.oeeLoaded && this.oeeTrendDays.length) {
          setTimeout(() => this.renderOEETrendChart(), 0);
        }
        if (this.cycleTrendLoaded && this.cycleTrendData.length) {
          setTimeout(() => this.renderCycleTrendChart(), 0);
        }
      }
    });
  }

  saveNewEvent(): void {
    if (!this.newEvent.event_date || !this.newEvent.title.trim()) {
      this.eventsAdminError = 'Datum och titel krävs.';
      return;
    }
    this.eventsAdminSaving = true;
    this.eventsAdminError = '';
    this.eventsAdminMessage = '';
    this.rebotlingService.addProductionEvent(this.newEvent).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.eventsAdminSaving = false;
      if (res?.success) {
        const added: ProductionEvent = {
          id: res.id,
          event_date: this.newEvent.event_date,
          title: this.newEvent.title,
          description: this.newEvent.description,
          event_type: this.newEvent.event_type as ProductionEvent['event_type']
        };
        this.productionEvents = [...this.productionEvents, added].sort((a, b) =>
          a.event_date.localeCompare(b.event_date));
        this.eventsAdminMessage = 'Händelsen sparades.';
        this.newEvent = { event_date: '', title: '', event_type: 'ovrigt', description: '' };
        if (this.oeeLoaded && this.oeeTrendDays.length) {
          setTimeout(() => this.renderOEETrendChart(), 0);
        }
        if (this.cycleTrendLoaded && this.cycleTrendData.length) {
          setTimeout(() => this.renderCycleTrendChart(), 0);
        }
      } else {
        this.eventsAdminError = 'Kunde inte spara händelsen.';
      }
    });
  }

  removeEvent(id: number): void {
    if (!confirm('Ta bort denna händelse?')) return;
    this.rebotlingService.deleteProductionEvent(id).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res?.success) {
        this.productionEvents = this.productionEvents.filter(e => e.id !== id);
        if (this.oeeLoaded && this.oeeTrendDays.length) {
          setTimeout(() => this.renderOEETrendChart(), 0);
        }
        if (this.cycleTrendLoaded && this.cycleTrendData.length) {
          setTimeout(() => this.renderCycleTrendChart(), 0);
        }
      }
    });
  }

  private buildWeekdayChart(): void {
    this.weekdayChart?.destroy();
    const canvas = document.getElementById('weekdayChart') as HTMLCanvasElement;
    if (!canvas || !this.weekdayData.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Sortera efter veckodag_nr (mån=2 ... lör=7, sön=1 visas sist)
    const sorted = [...this.weekdayData].sort((a, b) => {
      // Flytta söndag (1) till slutet
      const na = a.veckodag_nr === 1 ? 8 : a.veckodag_nr;
      const nb = b.veckodag_nr === 1 ? 8 : b.veckodag_nr;
      return na - nb;
    });

    const labels = sorted.map(d => d.namn);
    const ibcData = sorted.map(d => d.snitt_ibc);

    // Färg per stapel — bästa grön, sämsta röd, resten blå
    const maxIbc = Math.max(...ibcData);
    const minIbc = Math.min(...ibcData);
    const colors = ibcData.map(v =>
      v === maxIbc ? 'rgba(72, 187, 120, 0.85)' :
      v === minIbc ? 'rgba(245, 101, 101, 0.85)' :
      'rgba(66, 153, 225, 0.65)'
    );

    this.weekdayChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Snitt IBC/dag',
            data: ibcData,
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('0.85', '1').replace('0.65', '1')),
            borderWidth: 1,
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              afterBody: (items: any[]) => {
                const d = sorted[items[0].dataIndex];
                const lines: string[] = [];
                if (d.snitt_oee !== null) lines.push(`OEE: ${d.snitt_oee}%`);
                lines.push(`Max: ${d.max_ibc} IBC`, `Min: ${d.min_ibc} IBC`, `Antal dagar: ${d.antal_dagar}`);
                return lines;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a5568' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a5568' },
            title: { display: true, text: 'IBC/dag', color: '#a0aec0' }
          }
        }
      }
    });
  }

  // ======== PRODUKTIONSRYTM PER TIMME ========

  getHourlyRhythmMax(): number {
    if (!this.hourlyRhythm.length) return 0;
    return Math.max(...this.hourlyRhythm.map(h => h.avg_ibc_h));
  }

  loadHourlyRhythm(): void {
    this.hourlyRhythmLoading = true;
    this.rebotlingService.getHourlyRhythm(this.hourlyRhythmDays).pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.hourlyRhythmLoading = false;
      if (res?.success) {
        this.hourlyRhythm = res.data;
        setTimeout(() => this.buildHourlyRhythmChart(), 100);
      }
    });
  }

  private buildHourlyRhythmChart(): void {
    this.hourlyRhythmChart?.destroy();
    this.hourlyRhythmChart = null;
    const canvas = document.getElementById('hourlyRhythmChart') as HTMLCanvasElement;
    if (!canvas || !this.hourlyRhythm.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.hourlyRhythm.map(h => h.label);
    const values = this.hourlyRhythm.map(h => h.avg_ibc_h);
    const maxVal = Math.max(...values);

    this.hourlyRhythmChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Snitt IBC/h',
          data: values,
          backgroundColor: values.map((v: number) => {
            if (maxVal === 0) return 'rgba(74, 85, 104, 0.6)';
            const intensity = v / maxVal;
            if (intensity >= 0.85) return 'rgba(72, 187, 120, 0.8)';
            if (intensity >= 0.6) return 'rgba(237, 137, 54, 0.8)';
            return 'rgba(252, 129, 129, 0.8)';
          }),
          borderWidth: 0,
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const d = this.hourlyRhythm[ctx.dataIndex];
                return [`IBC/h: ${d.avg_ibc_h}`, `Kvalitet: ${d.avg_kvalitet}%`, `Dagar: ${d.antal_dagar}`];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a5568' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a5568' },
            title: { display: true, text: 'Snitt IBC/h', color: '#a0aec0' }
          }
        }
      }
    });
  }


}
