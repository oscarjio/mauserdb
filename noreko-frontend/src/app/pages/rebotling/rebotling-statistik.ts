import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import { RebotlingService, OEETrendDay, WeekComparisonDay, ChartAnnotation, ProductionEvent } from '../../services/rebotling.service';
import { AuthService } from '../../services/auth.service';

// Child components (lazy-loaded via @defer i template)
import { RebotlingHistogramComponent } from './rebotling-histogram/rebotling-histogram.component';
import { RebotlingSpcComponent } from './rebotling-spc/rebotling-spc.component';
import { RebotlingCycleByOperatorComponent } from './rebotling-cycle-by-operator/rebotling-cycle-by-operator.component';
import { RebotlingQualityTrendComponent } from './rebotling-quality-trend/rebotling-quality-trend.component';
import { RebotlingOeeWaterfallComponent } from './rebotling-oee-waterfall/rebotling-oee-waterfall.component';
import { RebotlingWeekdayStatsComponent } from './rebotling-weekday-stats/rebotling-weekday-stats.component';
import { RebotlingHourlyRhythmComponent } from './rebotling-hourly-rhythm/rebotling-hourly-rhythm.component';
import { RebotlingStoppageParetoComponent } from './rebotling-stoppage-pareto/rebotling-stoppage-pareto.component';
import { RebotlingEventsAdminComponent } from './rebotling-events-admin/rebotling-events-admin.component';
import { RebotlingKassationParetoComponent } from './rebotling-kassation-pareto/rebotling-kassation-pareto.component';
import { RebotlingRejectionAnalysisComponent } from './rebotling-rejection-analysis/rebotling-rejection-analysis.component';
import { RebotlingOeeComponentsComponent } from './rebotling-oee-components/rebotling-oee-components.component';
import { RebotlingWeekComparisonComponent } from './rebotling-week-comparison/rebotling-week-comparison.component';
import { RebotlingPredictionComponent } from './rebotling-prediction/rebotling-prediction.component';
import { RebotlingOeeDeepdiveComponent } from './rebotling-oee-deepdive/rebotling-oee-deepdive.component';
import { RebotlingCycleTrendComponent } from './rebotling-cycle-trend/rebotling-cycle-trend.component';

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
  imports: [
    CommonModule, FormsModule,
    RebotlingHistogramComponent, RebotlingSpcComponent, RebotlingCycleByOperatorComponent,
    RebotlingQualityTrendComponent, RebotlingOeeWaterfallComponent, RebotlingWeekdayStatsComponent,
    RebotlingHourlyRhythmComponent, RebotlingStoppageParetoComponent, RebotlingEventsAdminComponent,
    RebotlingKassationParetoComponent, RebotlingRejectionAnalysisComponent, RebotlingOeeComponentsComponent,
    RebotlingWeekComparisonComponent, RebotlingPredictionComponent, RebotlingOeeDeepdiveComponent,
    RebotlingCycleTrendComponent
  ]
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

  private lastStatisticsData: any = null;
  private chartSelectionStartIndex: number | null = null;
  private chartSelectionEndIndex: number | null = null;
  private chartSelectionPreviewStartIndex: number | null = null;
  private chartSelectionPreviewEndIndex: number | null = null;

  loading: boolean = false;
  error: string | null = null;
  breadcrumb: string[] = [];

  totalRastMinutes: number = 0;
  timelineSegments: { startPct: number; widthPct: number; type: 'running' | 'rast' | 'stopped' }[] = [];
  shiftSummaries: { nr: number; ibcCount: number; avgCycleTime: number; rastMinutes: number }[] = [];

  dayLongestStopMinutes: number = 0;
  dayUtilizationPct: number = 0;

  private chartWindowFrom: number = 0;

  showOnlyDaysWithCycles: boolean = true;

  // Heatmap
  heatmapDays: number = 30;
  heatmapCustomFrom: string = '';
  heatmapCustomTo: string = '';
  todayStr: string = new Date().toISOString().slice(0, 10);
  heatmapUseCustomRange: boolean = false;
  heatmapRows: { date: string; label: string; counts: number[]; qualityPct: number[] }[] = [];
  heatmapHours: number[] = Array.from({ length: 18 }, (_, i) => i + 5);
  heatmapMax: number = 1;
  heatmapQualityMax: number = 100;
  private isLoadingHeatmap = false;
  heatmapKpi: 'ibc' | 'quality' | 'oee' = 'ibc';
  heatmapTooltip: {
    visible: boolean; x: number; y: number; date: string;
    hour: number; count: number; ibcH: number; qualityPct: number;
  } = { visible: false, x: 0, y: 0, date: '', hour: 0, count: 0, ibcH: 0, qualityPct: 0 };

  isDragging: boolean = false;

  // Produktionshändelser (används av OEE-trend och cykeltrend-graferna)
  productionEvents: ProductionEvent[] = [];
  productionEventsLoading: boolean = false;

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
    this.applyStateFromUrl();
    this.updateBreadcrumb();
    this.generatePeriodCells();
    this.syncStateToUrl();
    this.loadStatistics();
    this.loadProductionEvents();
  }

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
        .map((s: string) => { const d = new Date(s); return isNaN(d.getTime()) ? null : d; })
        .filter((d: Date | null): d is Date => d !== null);
      if (this.selectedPeriods.length > 0 && (isNaN(year) || isNaN(month))) {
        const first = this.selectedPeriods[0];
        this.currentYear = first.getFullYear();
        this.currentMonth = first.getMonth();
      }
    }
  }

  private syncStateToUrl() {
    const params: Record<string, string> = {
      view: this.viewMode,
      year: String(this.currentYear),
      month: String(this.currentMonth)
    };
    if (this.selectedPeriods.length > 0) {
      params['dates'] = this.selectedPeriods.map(d => this.formatDate(d)).join(',');
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

  // ======== NAVIGATION ========

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
      if (this.currentMonth < 0) { this.currentMonth = 11; this.currentYear--; }
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
      if (this.currentMonth > 11) { this.currentMonth = 0; this.currentYear++; }
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

  // ======== PERIOD CELLS / CALENDAR ========

  generatePeriodCells() {
    this.periodCells = [];
    if (this.viewMode === 'year') {
      for (let month = 0; month < 12; month++) {
        const date = new Date(this.currentYear, month, 1);
        this.periodCells.push({
          label: this.monthNames[month].substring(0, 3), value: month, date,
          cyclesCount: 0, avgCycleTime: 0, efficiency: 0,
          isSelected: this.isDateSelected(date), hasData: false
        });
      }
    } else if (this.viewMode === 'month') {
      const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(this.currentYear, this.currentMonth, day);
        this.periodCells.push({
          label: `${day}`, value: day, date,
          cyclesCount: 0, avgCycleTime: 0, efficiency: 0,
          isSelected: this.isDateSelected(date), hasData: false
        });
      }
    } else if (this.viewMode === 'day' && this.selectedPeriods.length > 0) {
      const date = this.selectedPeriods[0];
      for (let hour = 0; hour < 24; hour++) {
        for (let minute = 0; minute < 60; minute += 10) {
          const intervalDate = new Date(date);
          intervalDate.setHours(hour, minute, 0, 0);
          this.periodCells.push({
            label: `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`,
            value: hour * 6 + minute / 10, date: intervalDate,
            cyclesCount: 0, avgCycleTime: 0, efficiency: 0,
            isSelected: false, hasData: false
          });
        }
      }
    }
  }

  onCellMouseDown(cell: PeriodCell, event: MouseEvent) {
    event.preventDefault();
    if (this.viewMode === 'day') return;
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
    if (!this.lastStatisticsData) { this.resetChartSelection(); return; }
    this.resetChartSelection();
    this.updateChart(this.lastStatisticsData);
    this.updateTable(this.lastStatisticsData);
  }

  onCellDoubleClick(cell: PeriodCell) {
    if (this.viewMode === 'year') this.navigateToMonth(cell.date);
    else if (this.viewMode === 'month') this.navigateToDay(cell.date);
  }

  isDateSelected(date: Date): boolean {
    return this.selectedPeriods.some(d =>
      d.getFullYear() === date.getFullYear() &&
      d.getMonth() === date.getMonth() &&
      (this.viewMode === 'year' || d.getDate() === date.getDate())
    );
  }

  // ======== STATISTICS LOADING ========

  loadStatistics() {
    this.loading = true;
    this.error = null;
    const { start, end } = this.getDateRange();
    this.rebotlingService.getStatistics(start, end).pipe(
      timeout(15000),
      catchError((err) => {
        console.error('Error loading statistics:', err);
        this.error = 'Kunde inte ladda statistik fran backend';
        this.loading = false;
        this.loadMockData();
        return of({ success: false, data: null } as any);
      }),
      takeUntil(this.destroy$)
    ).subscribe(response => {
      if (!response || !response.success) return;
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
        start.setDate(1); start.setHours(0, 0, 0, 0);
        end.setMonth(end.getMonth() + 1); end.setDate(0); end.setHours(23, 59, 59, 999);
      } else if (this.viewMode === 'month') {
        start.setHours(0, 0, 0, 0); end.setHours(23, 59, 59, 999);
      } else if (this.viewMode === 'day') {
        start.setHours(0, 0, 0, 0); end.setHours(23, 59, 59, 999);
      }
    } else {
      if (this.viewMode === 'year') {
        start = new Date(this.currentYear, 0, 1);
        end = new Date(this.currentYear, 11, 31, 23, 59, 59);
      } else if (this.viewMode === 'month') {
        start = new Date(this.currentYear, this.currentMonth, 1);
        end = new Date(this.currentYear, this.currentMonth + 1, 0, 23, 59, 59);
      } else {
        start = new Date(); start.setHours(0, 0, 0, 0);
        end = new Date(); end.setHours(23, 59, 59, 999);
      }
    }
    return { start: this.formatDate(start), end: this.formatDate(end) };
  }

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
      if (dayOfWeek !== 0 && dayOfWeek !== 6) {
        for (let hour = 6; hour < 18; hour++) {
          const shouldRun = Math.random() > 0.2;
          if (shouldRun) {
            const numCycles = 8 + Math.floor(Math.random() * 8);
            for (let c = 0; c < numCycles; c++) {
              const minute = Math.floor(Math.random() * 60);
              const cycleDate = new Date(currentDate);
              cycleDate.setHours(hour, minute, 0, 0);
              cycles.push({
                datum: cycleDate.toISOString(), ibc_count: 1,
                produktion_procent: 85 + Math.random() * 15, skiftraknare: 1,
                cycle_time: 8 + Math.random() * 4, target_cycle_time: 10
              });
            }
            if (this.viewMode === 'day') {
              const sd = new Date(currentDate); sd.setHours(hour, 2, 0, 0);
              onoff_events.push({ datum: sd.toISOString(), running: true });
              if (Math.random() > 0.8) {
                const stopDate = new Date(currentDate); stopDate.setHours(hour, 35, 0, 0);
                onoff_events.push({ datum: stopDate.toISOString(), running: false });
                const resumeDate = new Date(currentDate); resumeDate.setHours(hour, 48, 0, 0);
                onoff_events.push({ datum: resumeDate.toISOString(), running: true });
              }
            } else {
              const eventDate = new Date(currentDate); eventDate.setHours(hour, 0, 0, 0);
              onoff_events.push({ datum: eventDate.toISOString(), running: true });
            }
          } else {
            const eventDate = new Date(currentDate); eventDate.setHours(hour, 0, 0, 0);
            onoff_events.push({ datum: eventDate.toISOString(), running: false });
          }
        }
      }
      currentDate.setDate(currentDate.getDate() + 1);
    }
    const avgCycleTime = cycles.length > 0
      ? cycles.reduce((sum, c) => sum + c.cycle_time, 0) / cycles.length : 0;
    const avgProduction = cycles.length > 0
      ? cycles.reduce((sum, c) => sum + c.produktion_procent, 0) / cycles.length : 0;
    return {
      cycles, onoff_events,
      summary: {
        total_cycles: cycles.length, avg_production_percent: avgProduction,
        avg_cycle_time: Math.round(avgCycleTime * 10) / 10, target_cycle_time: 10,
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
      if (this.viewMode === 'year') key = `${date.getFullYear()}-${date.getMonth()}`;
      else if (this.viewMode === 'month') key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
      else {
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${hour}-${minute}`;
      }
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key)!.push(cycle);
    });

    this.periodCells.forEach(cell => {
      let key: string;
      if (this.viewMode === 'year') key = `${cell.date.getFullYear()}-${cell.date.getMonth()}`;
      else if (this.viewMode === 'month') key = `${cell.date.getFullYear()}-${cell.date.getMonth()}-${cell.date.getDate()}`;
      else {
        const hour = cell.date.getHours();
        const minute = cell.date.getMinutes();
        key = `${cell.date.getFullYear()}-${cell.date.getMonth()}-${cell.date.getDate()}-${hour}-${minute}`;
      }
      const periodCycles = grouped.get(key) || [];
      cell.hasData = periodCycles.length > 0;
      cell.cyclesCount = periodCycles.length;
      if (periodCycles.length > 0) {
        const validCycleTimes = periodCycles
          .map(c => c.cycle_time)
          .filter(t => t !== null && t !== undefined && t > 0);
        const avgCycleTime = validCycleTimes.length > 0
          ? validCycleTimes.reduce((sum, t) => sum + t, 0) / validCycleTimes.length : 0;
        const avgEff = periodCycles.reduce((sum, c) => sum + (c.produktion_procent || 0), 0) / periodCycles.length;
        cell.avgCycleTime = Math.round(avgCycleTime * 10) / 10;
        cell.efficiency = Math.round(avgEff);
      }
    });
    if (this.viewMode === 'day') this.trimEmptyPeriods();
  }

  trimEmptyPeriods() {
    let firstIndex = this.periodCells.findIndex(cell => cell.hasData);
    let lastIndex = -1;
    for (let i = this.periodCells.length - 1; i >= 0; i--) {
      if (this.periodCells[i].hasData) { lastIndex = i; break; }
    }
    if (firstIndex === -1 || lastIndex === -1) return;
    const margin = 3;
    firstIndex = Math.max(0, firstIndex - margin);
    lastIndex = Math.min(this.periodCells.length - 1, lastIndex + margin);
    this.periodCells = this.periodCells.slice(firstIndex, lastIndex + 1);
  }

  // ======== CHART ========

  updateChart(data: any) {
    try {
      if (this.productionChart) { this.productionChart.destroy(); this.productionChart = null; }
    } catch (e) { this.productionChart = null; }
    clearTimeout(this.chartUpdateTimer);
    this.chartUpdateTimer = setTimeout(() => {
      if (this.destroy$.closed) return;
      if (!this.productionChartRef?.nativeElement) return;
      const ctx = this.productionChartRef.nativeElement.getContext('2d');
      if (!ctx) return;
      const chartData = this.prepareChartData(data);
      if (chartData.labels.length === 0) return;
      this.createChart(ctx, chartData);
    }, 150);
  }

  prepareChartData(data: any) {
    const cycles = data.cycles || [];
    const onoff = data.onoff_events || [];
    const grouped = new Map<string, any>();

    if (this.viewMode === 'day') {
      for (let h = 0; h < 24; h++) {
        for (let m = 0; m < 60; m += 10) {
          const label = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
          grouped.set(label, { cycles: [], cycleTime: [], running: false });
        }
      }
    } else if (this.viewMode === 'month') {
      const useHourlyChart = this.selectedPeriods.length >= 2;
      if (useHourlyChart) {
        const sorted = [...this.selectedPeriods].sort((a, b) => a.getTime() - b.getTime());
        const start = new Date(sorted[0]); start.setHours(0, 0, 0, 0);
        const end = new Date(sorted[sorted.length - 1]); end.setHours(23, 0, 0, 0);
        for (let t = new Date(start); t <= end; t.setHours(t.getHours() + 1)) {
          const key = `${t.getFullYear()}-${t.getMonth()}-${t.getDate()}-${t.getHours()}`;
          const label = `${t.getDate()}/${t.getMonth() + 1} ${t.getHours().toString().padStart(2, '0')}`;
          grouped.set(key, { cycles: [], cycleTime: [], running: false, label });
        }
      } else {
        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        let daysToShow: number[];
        if (this.selectedPeriods.length > 0) {
          const daySet = new Set<number>();
          this.selectedPeriods.forEach(d => {
            if (d.getFullYear() === this.currentYear && d.getMonth() === this.currentMonth) daySet.add(d.getDate());
          });
          daysToShow = Array.from(daySet).sort((a, b) => a - b);
        } else {
          daysToShow = Array.from({ length: daysInMonth }, (_, i) => i + 1);
        }
        daysToShow.forEach(d => { grouped.set(`${d}`, { cycles: [], cycleTime: [], running: false }); });
      }
    } else {
      for (let m = 0; m < 12; m++) {
        grouped.set(this.monthNames[m].substring(0, 3), { cycles: [], cycleTime: [], running: false });
      }
    }

    const monthViewHourly = this.viewMode === 'month' && this.selectedPeriods.length >= 2;

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
        const cycleTimeValue = parseFloat(cycle.cycle_time);
        if (!isNaN(cycleTimeValue) && cycleTimeValue > 0 && cycleTimeValue <= 30) {
          group.cycleTime.push(cycleTimeValue);
        }
      }
    });

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
      if (grouped.has(key) && event.running) grouped.get(key).running = true;
    });

    grouped.forEach((value) => {
      if (value.cycles.length > 0 && !value.running) value.running = true;
    });

    const labels: string[] = [];
    const cycleTime: number[] = [];
    const avgCycleTimeArr: number[] = [];
    const targetCycleTimeArr: number[] = [];
    let totalCycleTime = 0;
    let totalCount = 0;
    const entries = Array.from(grouped.entries());

    let windowFrom = 0;
    let windowTo = entries.length - 1;
    this.chartWindowFrom = 0;

    if (this.viewMode === 'day' && this.chartSelectionStartIndex === null) {
      const MIN_FROM = 36; const MIN_TO = 108; const PADDING = 6;
      let firstDataSlot = -1; let lastDataSlot = -1;
      entries.forEach(([, value], idx) => {
        if (value.cycles.length > 0 || value.running) {
          if (firstDataSlot === -1) firstDataSlot = idx;
          lastDataSlot = idx;
        }
      });
      if (firstDataSlot !== -1) {
        windowFrom = Math.min(Math.max(0, firstDataSlot - PADDING), MIN_FROM);
        windowTo = Math.max(Math.min(entries.length - 1, lastDataSlot + PADDING), MIN_TO);
      } else {
        windowFrom = MIN_FROM; windowTo = MIN_TO;
      }
      this.chartWindowFrom = windowFrom;
    }

    let fromIndex = windowFrom;
    let toIndex = windowTo;
    if (this.viewMode === 'day' && this.chartSelectionStartIndex !== null && this.chartSelectionEndIndex !== null && entries.length > 0) {
      const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      fromIndex = Math.max(windowFrom, windowFrom + minSel);
      toIndex = Math.min(windowTo, windowFrom + maxSel);
    }

    const slicedEntries = entries.slice(fromIndex, toIndex + 1);
    slicedEntries.forEach(([key, value]) => {
      labels.push((value as any).label !== undefined ? (value as any).label : key);
      let avgTime = 0;
      if (value.cycleTime.length > 0) {
        avgTime = value.cycleTime.reduce((a: number, b: number) => a + b, 0) / value.cycleTime.length;
      }
      cycleTime.push(Math.round(avgTime * 10) / 10);
      if (avgTime > 0) { totalCycleTime += avgTime * value.cycles.length; totalCount += value.cycles.length; }
    });

    const overallAvg = totalCount > 0 ? Math.round((totalCycleTime / totalCount) * 10) / 10 : 0;
    labels.forEach(() => { avgCycleTimeArr.push(overallAvg); targetCycleTimeArr.push(this.targetCycleTime); });

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

    const rast: any[] = data.rast_events || [];
    const rastPeriods: { startIndex: number; endIndex: number }[] = [];
    let rastStartKey: string | null = null;
    rast.forEach((ev: any) => {
      const d = new Date(ev.datum);
      const key = this.viewMode === 'day'
        ? `${d.getHours().toString().padStart(2, '0')}:${(Math.floor(d.getMinutes() / 10) * 10).toString().padStart(2, '0')}`
        : `${d.getDate()}`;
      if (ev.rast_status == 1) rastStartKey = key;
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
          label: 'Cykeltid (min)', data: chartData.cycleTime,
          borderColor: '#00d4ff', backgroundColor: 'rgba(0, 212, 255, 0.1)',
          tension: 0.4, fill: true, yAxisID: 'y',
          pointRadius: this.viewMode === 'day' ? 2 : 3, pointHoverRadius: 6, borderWidth: 2
        },
        {
          label: 'Snitt Cykeltid', data: chartData.avgCycleTime,
          borderColor: '#ffc107', borderDash: [8, 4], tension: 0, fill: false,
          yAxisID: 'y', pointRadius: 0, borderWidth: 2
        }
      ];
      if (this.targetCycleTime > 0) {
        datasets.push({
          label: 'Mal Cykeltid', data: chartData.targetCycleTime,
          borderColor: '#ff8800', borderDash: [4, 4], tension: 0, fill: false,
          yAxisID: 'y', pointRadius: 0, borderWidth: 2.5
        });
      }

      this.productionChart = new Chart(ctx, {
        type: 'line',
        data: { labels: chartData.labels, datasets },
        options: {
          responsive: true, maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: true, position: 'top', labels: { color: '#e0e0e0', font: { size: 13, weight: 'bold' } } },
            tooltip: {
              backgroundColor: 'rgba(20, 20, 20, 0.95)', titleColor: '#fff',
              bodyColor: '#e0e0e0', borderColor: '#00d4ff', borderWidth: 1, padding: 12, displayColors: true
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: 'Cykeltid (minuter)', color: '#e0e0e0', font: { size: 13 } },
              ticks: { color: '#a0a0a0' }, grid: { color: 'rgba(255, 255, 255, 0.05)' }
            },
            x: {
              ticks: { color: '#a0a0a0', maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: this.viewMode === 'day' ? 24 : undefined },
              grid: { color: 'rgba(255, 255, 255, 0.05)' }
            }
          }
        },
        plugins: [{
          id: 'backgroundColors',
          beforeDatasetsDraw: (chart: any) => {
            const { ctx, chartArea, scales } = chart;
            if (!chartArea || !scales.x) return;
            const { top, bottom } = chartArea;

            chartData.runningPeriods.forEach((period: any) => {
              try {
                const xStart = scales.x.getPixelForValue(period.startIndex);
                const xEnd = scales.x.getPixelForValue(period.endIndex + 1);
                ctx.fillStyle = period.running ? 'rgba(34, 139, 34, 0.25)' : 'rgba(220, 53, 69, 0.25)';
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
              } catch (e) {}
            });

            (chartData.rastPeriods || []).forEach((period: any) => {
              try {
                const xStart = scales.x.getPixelForValue(period.startIndex);
                const xEnd = scales.x.getPixelForValue(period.endIndex + 1);
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

            if (this.viewMode === 'day' && this.chartSelectionPreviewStartIndex !== null && this.chartSelectionPreviewEndIndex !== null) {
              try {
                const minSel = Math.min(this.chartSelectionPreviewStartIndex, this.chartSelectionPreviewEndIndex);
                const maxSel = Math.max(this.chartSelectionPreviewStartIndex, this.chartSelectionPreviewEndIndex);
                const xStart = scales.x.getPixelForValue(Math.max(0, minSel));
                const xEnd = scales.x.getPixelForValue(maxSel + 1);
                ctx.fillStyle = 'rgba(0, 153, 255, 0.18)';
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
              } catch (e) {}
            }
          }
        }]
      });
      this.attachChartSelectionHandlers(this.productionChart);
    } catch (error) {
      console.error('Chart creation error:', error);
    }
  }

  private attachChartSelectionHandlers(chart: Chart) {
    const canvas = chart.canvas as HTMLCanvasElement | null;
    if (!canvas) return;
    let isSelecting = false;
    let startIndex: number | null = null;

    const getIndexFromEvent = (event: MouseEvent): number | null => {
      const rect = canvas.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const xScale: any = chart.scales['x'];
      if (!xScale) return null;
      const value = xScale.getValueForPixel(x);
      if (typeof value === 'number') return Math.round(value);
      if (typeof value === 'string' && Array.isArray(chart.data.labels)) {
        const idx = (chart.data.labels as string[]).indexOf(value);
        return idx >= 0 ? idx : null;
      }
      return null;
    };

    canvas.onmousedown = (event: MouseEvent) => {
      if (this.viewMode !== 'day' || !this.lastStatisticsData) return;
      const idx = getIndexFromEvent(event);
      if (idx === null) return;
      isSelecting = true; startIndex = idx;
      this.chartSelectionPreviewStartIndex = idx;
      this.chartSelectionPreviewEndIndex = idx;
      chart.update('none');
    };

    canvas.onmouseup = (event: MouseEvent) => {
      if (!isSelecting) return;
      isSelecting = false;
      if (this.viewMode !== 'day' || !this.lastStatisticsData) return;
      const endIndex = getIndexFromEvent(event);
      if (startIndex === null || endIndex === null) return;
      this.chartSelectionStartIndex = startIndex;
      this.chartSelectionEndIndex = endIndex;
      this.chartSelectionPreviewStartIndex = null;
      this.chartSelectionPreviewEndIndex = null;
      this.updateChart(this.lastStatisticsData);
      this.updateTable(this.lastStatisticsData);
    };

    canvas.ondblclick = () => {
      if (!this.lastStatisticsData) return;
      this.resetChartSelection();
      this.updateChart(this.lastStatisticsData);
      this.updateTable(this.lastStatisticsData);
    };

    canvas.onmousemove = (event: MouseEvent) => {
      if (!isSelecting || this.viewMode !== 'day' || !this.lastStatisticsData) return;
      const idx = getIndexFromEvent(event);
      if (idx === null) return;
      this.chartSelectionPreviewEndIndex = idx;
      chart.update('none');
    };
  }

  // ======== TIMELINE & SHIFT SUMMARIES ========

  private buildTimelineSegments(data: any) {
    if (this.viewMode !== 'day') { this.timelineSegments = []; return; }
    const dayEnd = 1440;
    const segments: typeof this.timelineSegments = [];
    const onoff: any[] = data.onoff_events || [];
    const rast: any[] = data.rast_events || [];
    const events: { min: number; type: 'run_start' | 'run_end' | 'rast_start' | 'rast_end' }[] = [];
    onoff.forEach((e: any) => {
      const d = new Date(e.datum);
      events.push({ min: d.getHours() * 60 + d.getMinutes(), type: e.running ? 'run_start' : 'run_end' });
    });
    rast.forEach((e: any) => {
      const d = new Date(e.datum);
      events.push({ min: d.getHours() * 60 + d.getMinutes(), type: e.rast_status == 1 ? 'rast_start' : 'rast_end' });
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
        nr, ibcCount: s.ibcCount,
        avgCycleTime: s.times.length ? Math.round(s.times.reduce((a, b) => a + b, 0) / s.times.length * 10) / 10 : 0,
        rastMinutes: 0
      }));
  }

  private computeDayMetrics(data: any) {
    if (this.viewMode !== 'day') { this.dayLongestStopMinutes = 0; this.dayUtilizationPct = 0; return; }
    const onoff: any[] = data.onoff_events || [];
    if (onoff.length === 0) { this.dayLongestStopMinutes = 0; this.dayUtilizationPct = 0; return; }
    const events = onoff
      .map((e: any) => ({ min: new Date(e.datum).getHours() * 60 + new Date(e.datum).getMinutes(), running: !!e.running }))
      .sort((a: any, b: any) => a.min - b.min);
    let firstRunMin: number | null = null;
    let lastEventMin: number | null = null;
    for (const ev of events) {
      if (firstRunMin === null && ev.running) firstRunMin = ev.min;
      lastEventMin = ev.min;
    }
    if (firstRunMin === null) { this.dayLongestStopMinutes = 0; this.dayUtilizationPct = 0; return; }
    let totalRunMinutes = 0;
    let longestStop = 0;
    let currentRunning = false;
    let lastMin = 0;
    let stopStart: number | null = null;
    for (const ev of events) {
      if (currentRunning && !ev.running) {
        totalRunMinutes += ev.min - lastMin;
        stopStart = ev.min;
        currentRunning = false;
      } else if (!currentRunning && ev.running) {
        if (stopStart !== null) {
          const stopDur = ev.min - stopStart;
          if (stopDur > longestStop) longestStop = stopDur;
        }
        currentRunning = true;
      }
      lastMin = ev.min;
    }
    if (currentRunning) {
      totalRunMinutes += lastMin - (events.find((e: any) => e.running)?.min ?? lastMin);
    }
    const spanMin = (lastEventMin ?? 0) - firstRunMin;
    this.dayLongestStopMinutes = longestStop;
    this.dayUtilizationPct = spanMin > 0 ? Math.min(100, Math.round((totalRunMinutes / spanMin) * 100)) : 0;
  }

  // ======== TABLE ========

  updateTable(data: any) {
    this.tableData = [];
    const grouped = new Map<string, any[]>();
    data.cycles.forEach((cycle: any) => {
      const date = new Date(cycle.datum);
      let key: string;
      if (this.viewMode === 'year') key = `${date.getFullYear()}-${date.getMonth()}`;
      else if (this.viewMode === 'month') key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
      else {
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        if (this.chartSelectionStartIndex !== null && this.chartSelectionEndIndex !== null) {
          const bucketIndex = hour * 6 + minute / 10;
          const minSel = this.chartWindowFrom + Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          const maxSel = this.chartWindowFrom + Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          if (bucketIndex < minSel || bucketIndex > maxSel) return;
        }
        key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}-${hour}-${minute}`;
      }
      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key)!.push(cycle);
    });

    grouped.forEach((cycles) => {
      const date = new Date(cycles[0].datum);
      let period: string;
      if (this.viewMode === 'year') period = this.monthNames[date.getMonth()];
      else if (this.viewMode === 'month') period = `${date.getDate()} ${this.monthNames[date.getMonth()].substring(0, 3)}`;
      else {
        const hour = date.getHours();
        const minute = Math.floor(date.getMinutes() / 10) * 10;
        period = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')} - ${hour.toString().padStart(2, '0')}:${(minute + 10).toString().padStart(2, '0')}`;
      }
      const validCycleTimes = cycles.map(c => c.cycle_time).filter(t => t !== null && t !== undefined && t > 0);
      const avgCycleTime = validCycleTimes.length > 0
        ? validCycleTimes.reduce((sum, t) => sum + t, 0) / validCycleTimes.length : 0;
      const avgEff = cycles.reduce((sum, c) => sum + (c.produktion_procent || 0), 0) / cycles.length;
      this.tableData.push({
        period, date, cycles: cycles.length,
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
    if (this.viewMode === 'year') this.navigateToMonth(row.date);
    else if (this.viewMode === 'month') this.navigateToDay(row.date);
  }

  getViewModeLabel(): string {
    if (this.viewMode === 'year') return 'Manader';
    if (this.viewMode === 'month') return 'Dagar';
    if (this.viewMode === 'heatmap') {
      if (this.heatmapUseCustomRange && this.heatmapCustomFrom && this.heatmapCustomTo) {
        return `${this.heatmapCustomFrom} - ${this.heatmapCustomTo}`;
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
    obs.pipe(takeUntil(this.destroy$)).subscribe({
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
        this.error = 'Natverksfel vid hamtning av heatmap';
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
    const map = new Map<string, Map<number, { count: number; quality_pct: number }>>();
    data.forEach(({ date, hour, count, quality_pct }) => {
      if (!map.has(date)) map.set(date, new Map());
      map.get(date)!.set(hour, { count, quality_pct: quality_pct ?? 0 });
    });
    const today = new Date();
    const rows: typeof this.heatmapRows = [];
    this.heatmapMax = 1;
    for (let i = this.heatmapDays - 1; i >= 0; i--) {
      const d = new Date(today); d.setDate(d.getDate() - i);
      const dateStr = d.toISOString().split('T')[0];
      const dayMap = map.get(dateStr) || new Map();
      const counts = this.heatmapHours.map(h => dayMap.get(h)?.count || 0);
      const qualityPct = this.heatmapHours.map(h => dayMap.get(h)?.quality_pct || 0);
      const maxVal = Math.max(...counts);
      if (maxVal > this.heatmapMax) this.heatmapMax = maxVal;
      const weekdays = ['son', 'man', 'tis', 'ons', 'tor', 'fre', 'lor'];
      const label = `${weekdays[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1}`;
      rows.push({ date: dateStr, label, counts, qualityPct });
    }
    this.heatmapRows = rows;
  }

  private buildHeatmapRowsForRange(
    data: { date: string; hour: number; count: number; quality_pct?: number }[],
    fromDate: string, toDate: string
  ) {
    const map = new Map<string, Map<number, { count: number; quality_pct: number }>>();
    data.forEach(({ date, hour, count, quality_pct }) => {
      if (!map.has(date)) map.set(date, new Map());
      map.get(date)!.set(hour, { count, quality_pct: quality_pct ?? 0 });
    });
    const rows: typeof this.heatmapRows = [];
    this.heatmapMax = 1;
    const start = new Date(fromDate + 'T00:00:00');
    const end = new Date(toDate + 'T00:00:00');
    const weekdays = ['son', 'man', 'tis', 'ons', 'tor', 'fre', 'lor'];
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
      if (count === 0) return '#2a2a3a';
      const ratio = Math.min(count / this.heatmapMax, 1);
      return `rgb(${Math.round(230 - ratio * 225)},${Math.round(245 - ratio * 185)},${Math.round(255 - ratio * 105)})`;
    } else if (this.heatmapKpi === 'quality') {
      if (row.counts[hourIndex] === 0) return '#2a2a3a';
      const q = row.qualityPct[hourIndex];
      if (q === 0) return '#2a2a3a';
      const ratio = Math.min(q / 100, 1);
      return `rgb(${Math.round(230 - ratio * 220)},${Math.round(255 - ratio * 165)},${Math.round(235 - ratio * 205)})`;
    } else {
      const count = row.counts[hourIndex];
      if (count === 0) return '#2a2a3a';
      const ratio = Math.min(count / this.heatmapMax, 1);
      return `rgb(${Math.round(245 - ratio * 165)},${Math.round(235 - ratio * 215)},${Math.round(255 - ratio * 115)})`;
    }
  }

  getHeatmapLegendGradient(): string {
    if (this.heatmapKpi === 'ibc') return 'linear-gradient(to right, #e6f5ff, #053c96)';
    if (this.heatmapKpi === 'quality') return 'linear-gradient(to right, #e6ffeb, #0a5a1e)';
    return 'linear-gradient(to right, #f5ebff, #50148c)';
  }

  getHeatmapLegendLow(): string {
    if (this.heatmapKpi === 'ibc') return '0 IBC';
    if (this.heatmapKpi === 'quality') return '0%';
    return 'Lag';
  }

  getHeatmapLegendHigh(): string {
    if (this.heatmapKpi === 'ibc') return `${this.heatmapMax} IBC/h`;
    if (this.heatmapKpi === 'quality') return '100%';
    return 'Hog';
  }

  getHeatmapKpiLabel(): string {
    if (this.heatmapKpi === 'ibc') return 'IBC/h';
    if (this.heatmapKpi === 'quality') return 'Kvalitet% (dagsniva)';
    return 'OEE% (IBC-proxy)';
  }

  showHeatmapTooltip(event: MouseEvent, rowIndex: number, hourIndex: number) {
    const row = this.heatmapRows[rowIndex];
    if (!row) return;
    const count = row.counts[hourIndex];
    const hour = this.heatmapHours[hourIndex];
    const cellEl = event.currentTarget as HTMLElement;
    const containerEl = cellEl.closest('.heatmap-container') as HTMLElement | null;
    let x = 0, y = 0;
    if (containerEl) {
      const cellRect = cellEl.getBoundingClientRect();
      const contRect = containerEl.getBoundingClientRect();
      x = cellRect.left - contRect.left + cellRect.width / 2 + containerEl.scrollLeft;
      y = cellRect.top - contRect.top;
    }
    this.heatmapTooltip = { visible: true, x, y, date: row.date, hour, count, ibcH: count, qualityPct: row.qualityPct[hourIndex] };
  }

  hideHeatmapTooltip() {
    this.heatmapTooltip = { ...this.heatmapTooltip, visible: false };
  }

  onHeatmapDaysChange() {
    this.heatmapUseCustomRange = false;
    this.heatmapCustomFrom = '';
    this.heatmapCustomTo = '';
    if (this.viewMode === 'heatmap') this.loadHeatmap();
  }

  // ======== UTILITY ========

  getEfficiencyClass(efficiency: number): string {
    if (efficiency >= 90) return 'text-success';
    if (efficiency >= 70) return 'text-warning';
    return 'text-danger';
  }

  exportCSV() {
    if (this.tableData.length === 0) return;
    const header = ['Period', 'Cykler', 'Cykeltid (min)', 'Effektivitet (%)', 'Drifttid (min)'];
    const rows = this.tableData.map(row => [row.period, row.cycles, row.avgCycleTime, row.efficiency, row.runtime]);
    const csvContent = [header, ...rows].map(row => row.map(cell => `"${cell}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
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
        'Period': row.period, 'Cykler': row.cycles, 'Cykeltid (min)': row.avgCycleTime,
        'Effektivitet (%)': row.efficiency, 'Drifttid (min)': row.runtime
      }));
      const ws = XLSX.utils.json_to_sheet(data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Statistik');
      XLSX.writeFile(wb, `rebotling-statistik-${this.breadcrumb.join('-')}.xlsx`);
    });
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
    a.href = url; a.download = `heatmap-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click(); URL.revokeObjectURL(url);
  }

  // ======== PRODUCTION EVENTS (for annotation integration) ========

  loadProductionEvents(): void {
    if (this.productionEventsLoading) return;
    this.productionEventsLoading = true;
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 89);
    const fmt = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    this.rebotlingService.getProductionEvents(fmt(startDate), fmt(endDate)).pipe(
      timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe(res => {
      this.productionEventsLoading = false;
      if (res?.success && res.events) {
        this.productionEvents = res.events;
      }
    });
  }

  /** Hantera events-changed fran child-komponent */
  onEventsChanged(events: ProductionEvent[]): void {
    this.productionEvents = events;
  }
}
