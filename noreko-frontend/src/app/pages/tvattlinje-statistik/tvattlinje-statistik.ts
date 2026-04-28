import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit, HostListener } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { TvattlinjeService, OeeTrendDay, OeeTrendSummary } from '../../services/tvattlinje.service';

Chart.register(...registerables);

type ViewMode = 'year' | 'month' | 'day';

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
  selector: 'app-tvattlinje-statistik',
  templateUrl: './tvattlinje-statistik.html',
  styleUrls: ['./tvattlinje-statistik.css'],
  imports: [CommonModule, FormsModule, DecimalPipe]
})
export class TvattlinjeStatistikPage implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('productionChart') productionChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('oeeTrendChart') oeeTrendChartRef!: ElementRef<HTMLCanvasElement>;

  viewMode: ViewMode = 'month';
  currentYear: number = new Date().getFullYear();
  currentMonth: number = new Date().getMonth();
  selectedPeriods: Date[] = [];

  periodCells: PeriodCell[] = [];
  // Cachad filtrerad lista — uppdateras i generatePeriodCells/updatePeriodCellsData, undviker .filter() i template
  visiblePeriodCells: PeriodCell[] = [];
  monthNames = ['Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];

  totalCycles: number = 0;
  missedWebhooks: number = 0;
  avgCycleTime: number = 0;
  avgEfficiency: number = 0;
  totalRuntimeHours: number = 0;
  targetCycleTime: number = 0;

  productionChart: Chart | null = null;
  tableData: TableRow[] = [];

  // Senaste hämtade statistik-data (för zoom/markering i grafen)
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

  /** I månadsvy: false = visa alla dagar, true = visa bara dagar med cykler */
  showOnlyDaysWithCycles: boolean = true;

  // OEE Trend panel
  oeeTrendLoading: boolean = false;
  oeeTrendLoaded: boolean = false;
  oeeTrendEmpty: boolean = false;
  oeeTrendMessage: string = '';
  oeeTrendDagar: number = 30;
  oeeTrendData: OeeTrendDay[] = [];
  oeeTrendSummary: OeeTrendSummary = {
    total_ibc: 0, snitt_per_dag: 0, snitt_oee_pct: 0,
    basta_dag: null, basta_ibc: 0
  };
  private oeeTrendChart: Chart | null = null;

  // "Bästa dag" KPI (fylls av OEE-trend)
  bastaDagLabel: string = '–';
  bastaDagIbc: number = 0;

  isDragging: boolean = false;

  // ---- Tabs ----
  activeTab: 'overview' | 'produktion' | 'analys' | 'avancerat' | 'plc-diag' = 'overview';
  showDetailTable = false;

  // ---- Avancerat (rådata) ----
  rawCycles: any[] = [];
  rawCyclesSorted: any[] = [];
  rawCyclesSortCol: string = 'datum';
  rawCyclesSortDir: 1 | -1 = 1;

  // ---- PLC Diagnostik ----
  plcDiagLoading: boolean = false;
  plcDiagData: any = null;
  plcDiagError: string | null = null;
  plcDiagRefreshInterval: any = null;

  // ---- Timeline (dag-vy) ----
  timelineSegments: { startPct: number; widthPct: number; type: 'running' | 'rast' | 'stopped'; startTime: string; endTime: string; duration: string }[] = [];
  timelineEndPct: number = 100;
  showTimelineDetail: boolean = false;

  // ---- Skiftsammanfattning (dag-vy) ----
  shiftSummaries: { nr: number; ibcCount: number; avgCycleTime: number }[] = [];

  // ---- Dag-metrics ----
  dayLongestStopMinutes: number = 0;
  dayUtilizationPct: number = 0;

  // ---- Skiftrapport statistik (produktions-tab) ----
  skiftStatLoading: boolean = false;
  skiftStatData: any[] = [];
  skiftStatSummary: { total_ibc: number; total_ok: number; total_ej_ok: number; total_omtvaatt: number; total_drifttid: number; skift_count: number } | null = null;
  skiftStatFrom: string = '';
  skiftStatTo: string = '';
  private skiftStatChartRef: Chart | null = null;
  @ViewChild('skiftStatChart') skiftStatChartElement!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();
  private chartUpdateTimer: any = null;
  private oeeTrendChartTimer: any = null;

  constructor(
    private tvattlinjeService: TvattlinjeService,
    private router: Router,
    private route: ActivatedRoute
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
    this.loadOeeTrend();
    // Sätt standardintervall för skiftrapport-statistik (senaste 30 dagarna)
    const today = new Date().toISOString().split('T')[0];
    this.skiftStatTo = today;
    const d = new Date(today);
    d.setDate(d.getDate() - 30);
    this.skiftStatFrom = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
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

  ngOnDestroy() {
    clearTimeout(this.chartUpdateTimer);
    clearTimeout(this.oeeTrendChartTimer);
    clearInterval(this.plcDiagRefreshInterval);
    try { this.productionChart?.destroy(); } catch (e) {}
    this.productionChart = null;
    try { this.oeeTrendChart?.destroy(); } catch (e) {}
    this.oeeTrendChart = null;
    try { this.skiftStatChartRef?.destroy(); } catch (e) {}
    this.skiftStatChartRef = null;
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

  toggleShowOnlyDaysWithCycles(): void {
    this.showOnlyDaysWithCycles = !this.showOnlyDaysWithCycles;
    this.updateVisiblePeriodCells();
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

  /** Uppdaterar cachadlistan visiblePeriodCells — anropas efter att periodCells ändrats */
  private updateVisiblePeriodCells(): void {
    if (this.viewMode !== 'month' || !this.showOnlyDaysWithCycles) {
      this.visiblePeriodCells = this.periodCells;
      return;
    }
    const withData = this.periodCells.filter(cell => cell.hasData);
    this.visiblePeriodCells = withData.length > 0 ? withData : this.periodCells;
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

    this.tvattlinjeService.getStatistics(start, end).pipe(
      timeout(15000),
      catchError(() => {
        this.error = 'Kunde inte ladda statistik från backend';
        this.loading = false;
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (response) => {
        if (!response) return;
        if (response.success) {
          // Spara senaste data så vi kan zooma/markera i grafen
          this.lastStatisticsData = response.data;
          this.updateStatistics(response.data);
          this.updateChart(response.data);
          this.updateTable(response.data);
        }
        this.loading = false;
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
    this.rawCycles = data.cycles || [];
    this.rawCyclesSorted = [...this.rawCycles].reverse();
    this.totalCycles = data.summary.total_cycles;
    this.missedWebhooks = data.summary.missed_webhooks || 0;
    this.avgCycleTime = Math.round((data.summary.avg_cycle_time || 0) * 10) / 10;

    // Effektivitet: target / snitt-cykeltid * 100 (konsekvent med stapeldiagrammet)
    const target = data.summary.target_cycle_time || 3;
    const avgActual = data.summary.avg_cycle_time || 0;
    if (avgActual > 0 && target > 0) {
      this.avgEfficiency = Math.round((target / avgActual) * 100);
    } else {
      this.avgEfficiency = Math.round(data.summary.avg_production_percent || 0);
    }

    this.totalRuntimeHours = Math.round(data.summary.total_runtime_hours * 10) / 10;
    this.targetCycleTime = data.summary.target_cycle_time || 0;

    this.buildTimelineSegments(data);
    this.buildShiftSummaries(data.cycles || []);
    this.computeDayMetrics(data);

    this.updatePeriodCellsData(data.cycles);
  }

  // =========================================================
  // Timeline — dag-vy tidslinje (port från rebotling-statistik)
  // =========================================================

  private buildTimelineSegments(data: any) {
    if (this.viewMode !== 'day') { this.timelineSegments = []; this.timelineEndPct = 100; return; }
    const segments: typeof this.timelineSegments = [];
    const onoff: any[] = data.onoff_events || [];
    const rast: any[] = data.rast_events || [];

    const now = new Date();
    const isToday = this.selectedPeriods.length === 1 &&
      this.selectedPeriods[0].getFullYear() === now.getFullYear() &&
      this.selectedPeriods[0].getMonth() === now.getMonth() &&
      this.selectedPeriods[0].getDate() === now.getDate();

    type EvType = 'run_start' | 'run_end' | 'rast_start' | 'rast_end';
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
    events.sort((a, b) => a.min - b.min);

    let capMin: number;
    if (isToday) {
      capMin = now.getHours() * 60 + now.getMinutes();
    } else if (events.length > 0) {
      capMin = events[events.length - 1].min;
    } else {
      capMin = 1440;
    }
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

    let running = false, onRast = false, lastMin = 0;
    const push = (end: number) => {
      if (end > lastMin) {
        const type: 'running' | 'rast' | 'stopped' =
          onRast ? 'rast' : running ? 'running' : 'stopped';
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
    }
    push(capMin);

    // Absorb very short stops (<2 min) into surrounding running
    const MIN_STOP = 2;
    const cleaned: typeof segments = [];
    for (const seg of segments) {
      const pStart = parseFloat(seg.startTime.split(':')[0]) * 60 + parseFloat(seg.startTime.split(':')[1]);
      const pEnd = parseFloat(seg.endTime.split(':')[0]) * 60 + parseFloat(seg.endTime.split(':')[1]);
      const segMins = pEnd - pStart;
      if (seg.type === 'stopped' && segMins < MIN_STOP && cleaned.length > 0 && cleaned[cleaned.length - 1].type === 'running') {
        const prev = cleaned[cleaned.length - 1];
        prev.widthPct += seg.widthPct;
        prev.endTime = seg.endTime;
        const ps = parseFloat(prev.startTime.split(':')[0]) * 60 + parseFloat(prev.startTime.split(':')[1]);
        const pe = parseFloat(seg.endTime.split(':')[0]) * 60 + parseFloat(seg.endTime.split(':')[1]);
        prev.duration = fmtDuration(pe - ps);
      } else {
        cleaned.push({ ...seg });
      }
    }

    // Merge consecutive same-type segments
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
    const map = new Map<number, { ibcCount: number; times: number[] }>();
    cycles.forEach((c: any) => {
      if (!c.skiftraknare) return;
      if (!map.has(c.skiftraknare)) map.set(c.skiftraknare, { ibcCount: 0, times: [] });
      const s = map.get(c.skiftraknare)!;
      s.ibcCount += (c.ibc_count || 1);
      if (c.cycle_time != null && c.cycle_time > 0 && c.cycle_time <= 30) s.times.push(parseFloat(c.cycle_time));
    });
    this.shiftSummaries = Array.from(map.entries())
      .sort((a, b) => a[0] - b[0])
      .map(([nr, s]) => ({
        nr,
        ibcCount: s.ibcCount,
        avgCycleTime: s.times.length ? Math.round(s.times.reduce((a, b) => a + b, 0) / s.times.length * 10) / 10 : 0
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
    let runStartMin = 0;
    let stopStart: number | null = null;
    for (const ev of events) {
      if (currentRunning && !ev.running) {
        totalRunMinutes += ev.min - runStartMin;
        stopStart = ev.min;
        currentRunning = false;
      } else if (!currentRunning && ev.running) {
        if (stopStart !== null) {
          const stopDur = ev.min - stopStart;
          if (stopDur > longestStop) longestStop = stopDur;
        }
        runStartMin = ev.min;
        currentRunning = true;
      }
    }
    if (currentRunning && lastEventMin !== null) totalRunMinutes += lastEventMin - runStartMin;

    const totalSpan = lastEventMin! - firstRunMin;
    this.dayLongestStopMinutes = longestStop;
    this.dayUtilizationPct = totalSpan > 0 ? Math.round((totalRunMinutes / totalSpan) * 100) : 0;
  }

  // =========================================================
  // Skiftrapport statistik — produktions-tab
  // =========================================================

  setTab(tab: 'overview' | 'produktion' | 'analys' | 'avancerat' | 'plc-diag') {
    this.activeTab = tab;
    if (tab === 'produktion' && this.skiftStatData.length === 0 && !this.skiftStatLoading) {
      this.loadSkiftrapportStatistik();
    }
    if (tab === 'plc-diag') {
      this.loadPlcDiagnostics();
      clearInterval(this.plcDiagRefreshInterval);
      this.plcDiagRefreshInterval = setInterval(() => {
        if (this.activeTab === 'plc-diag') this.loadPlcDiagnostics();
      }, 30000);
    } else {
      clearInterval(this.plcDiagRefreshInterval);
    }
  }

  sortRawCycles(col: string) {
    if (this.rawCyclesSortCol === col) {
      this.rawCyclesSortDir = this.rawCyclesSortDir === 1 ? -1 : 1;
    } else {
      this.rawCyclesSortCol = col;
      this.rawCyclesSortDir = col === 'datum' ? -1 : 1;
    }
    const dir = this.rawCyclesSortDir;
    this.rawCyclesSorted = [...this.rawCycles].sort((a, b) => {
      const av = a[col] ?? '';
      const bv = b[col] ?? '';
      if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
      return String(av).localeCompare(String(bv)) * dir;
    });
  }

  loadPlcDiagnostics() {
    if (this.plcDiagLoading) return;
    this.plcDiagLoading = true;
    this.plcDiagError = null;
    const { start, end } = this.getDateRange();
    this.tvattlinjeService.getPlcDiagnostics(start, end).pipe(
      timeout(20000),
      catchError(() => {
        this.plcDiagLoading = false;
        this.plcDiagError = 'Kunde inte hämta PLC-diagnostik';
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe((res: any) => {
      this.plcDiagLoading = false;
      if (res?.success) {
        this.plcDiagData = res.data;
      } else if (res !== null) {
        this.plcDiagError = 'Fel vid hämtning av PLC-diagnostik';
      }
    });
  }

  formatDateTime(dateStr: string): string {
    if (!dateStr) return '–';
    const d = new Date(dateStr);
    return `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}:${d.getSeconds().toString().padStart(2,'0')}`;
  }

  loadSkiftrapportStatistik() {
    if (this.skiftStatLoading) return;
    this.skiftStatLoading = true;
    this.tvattlinjeService.getSkiftrapportStatistik(this.skiftStatFrom, this.skiftStatTo)
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.skiftStatLoading = false;
          if (res?.success) {
            this.skiftStatData = res.data || [];
            this.skiftStatSummary = res.summary || null;
            setTimeout(() => this.renderSkiftStatChart(), 150);
          }
        }
      });
  }

  renderSkiftStatChart() {
    if (!this.skiftStatChartElement?.nativeElement) return;
    try { this.skiftStatChartRef?.destroy(); } catch (e) {}
    this.skiftStatChartRef = null;
    if (this.skiftStatData.length === 0) return;

    // Gruppera per dag
    const dagMap = new Map<string, { ok: number; ejOk: number }>();
    this.skiftStatData.forEach((r: any) => {
      const dag = (r.datum || '').substring(0, 10);
      if (!dag) return;
      if (!dagMap.has(dag)) dagMap.set(dag, { ok: 0, ejOk: 0 });
      const d = dagMap.get(dag)!;
      d.ok += r.antal_ok || 0;
      d.ejOk += r.antal_ej_ok || 0;
    });

    const labels = Array.from(dagMap.keys()).sort();
    const okData = labels.map(d => dagMap.get(d)!.ok);
    const ejOkData = labels.map(d => dagMap.get(d)!.ejOk);

    this.skiftStatChartRef = new Chart(this.skiftStatChartElement.nativeElement, {
      type: 'bar',
      data: {
        labels: labels.map(d => d.substring(5)), // MM-DD
        datasets: [
          { label: 'Godkända IBC', data: okData, backgroundColor: 'rgba(104,211,145,0.7)', borderColor: '#68d391', borderWidth: 1 },
          { label: 'Ej godkända IBC', data: ejOkData, backgroundColor: 'rgba(229,62,62,0.6)', borderColor: '#e53e3e', borderWidth: 1 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          x: { stacked: true, ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.06)' } },
          y: { stacked: true, ticks: { color: '#a0aec0' }, grid: { color: 'rgba(255,255,255,0.06)' } }
        }
      }
    });
  }

  /** Bygg pausperioder (rast + ev. stopp) för rullande effektivitetsberäkning */
  private buildPausePeriods(rast_events: any[]): Array<{start: number; end: number}> {
    const periods: Array<{start: number; end: number}> = [];
    let rastStart: number | null = null;
    for (const evt of rast_events) {
      const t = new Date(evt.datum).getTime();
      if ((evt.rast_status === 1 || evt.rast_status === '1') && rastStart === null) {
        rastStart = t;
      } else if ((evt.rast_status === 0 || evt.rast_status === '0') && rastStart !== null) {
        periods.push({ start: rastStart, end: t });
        rastStart = null;
      }
    }
    return periods;
  }

  /** Beräkna rullande 30-min effektivitet per cykel */
  private calcRollingEfficiency(cycles: any[], rast_events: any[], targetMin: number): number[] {
    if (cycles.length === 0) return [];
    const WINDOW_MS = 30 * 60 * 1000;
    const WINDOW_MINUTES = 30;
    const pausePeriods = this.buildPausePeriods(rast_events || []);
    const result: number[] = [];

    for (let i = 0; i < cycles.length; i++) {
      const cycleMs = new Date(cycles[i].datum).getTime();
      const windowStart = cycleMs - WINDOW_MS;

      // Räkna giltiga cykler i fönstret
      let windowCount = 0;
      for (let w = i; w >= 0; w--) {
        const wMs = new Date(cycles[w].datum).getTime();
        if (wMs < windowStart) break;
        const wct = parseFloat(cycles[w].cycle_time);
        if (!isNaN(wct) && wct > 0 && wct <= 30) windowCount++;
      }

      // Beräkna pausminuter i fönstret
      let pauseMinInWindow = 0;
      for (const p of pausePeriods) {
        const overlapStart = Math.max(p.start, windowStart);
        const overlapEnd   = Math.min(p.end, cycleMs);
        if (overlapEnd > overlapStart) {
          pauseMinInWindow += (overlapEnd - overlapStart) / 60000;
        }
      }

      const netWindowMin = Math.max(1, WINDOW_MINUTES - pauseMinInWindow);
      const pp = windowCount > 0
        ? Math.round((windowCount * targetMin / netWindowMin) * 100)
        : 0;
      result.push(pp);
    }
    return result;
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
    try { this.productionChart?.destroy(); } catch (e) {}
    this.productionChart = null;

    clearTimeout(this.chartUpdateTimer);
    this.chartUpdateTimer = setTimeout(() => {
      if (this.destroy$.closed) return;
      if (!this.productionChartRef?.nativeElement) {
        return;
      }

      const ctx = this.productionChartRef.nativeElement.getContext('2d');
      if (!ctx) return;

      // Dag-vy: använd per-cykel data med rullande effektivitet
      const chartData = this.viewMode === 'day'
        ? this.preparePerCycleChartData(data)
        : this.prepareChartData(data);

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

    // Om vi är i dagsvy och har en markering i grafen, begränsa till valt intervall
    let fromIndex = 0;
    let toIndex = entries.length - 1;
    if (
      this.viewMode === 'day' &&
      this.chartSelectionStartIndex !== null &&
      this.chartSelectionEndIndex !== null &&
      entries.length > 0
    ) {
      const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      fromIndex = Math.max(0, minSel);
      toIndex = Math.min(entries.length - 1, maxSel);
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

    // Bygg effektivitets- och IBC-räknar-arrayer för stapeldiagram (månad/år-vy)
    const efficiencyArr: number[] = [];
    const cycleCountArr: number[] = [];
    const target = this.targetCycleTime || 3;
    slicedEntries.forEach(([, value]) => {
      const count = value.cycles.length;
      cycleCountArr.push(count);
      if (count > 0) {
        const validTimes: number[] = value.cycleTime;
        if (validTimes.length > 0) {
          const avgActual = validTimes.reduce((s: number, t: number) => s + t, 0) / validTimes.length;
          efficiencyArr.push(Math.round((target / avgActual) * 100));
        } else {
          efficiencyArr.push(0);
        }
      } else {
        efficiencyArr.push(0);
      }
    });

    return { labels, cycleTime, avgCycleTime: avgCycleTimeArr, targetCycleTime: targetCycleTimeArr, runningPeriods, efficiencyArr, cycleCountArr };
  }

  /** Förbereder dag-vy chart med per-cykel-data och rullande effektivitet */
  private preparePerCycleChartData(data: any): any {
    const cycles = (data.cycles || []).filter((c: any) => {
      const ct = parseFloat(c.cycle_time);
      return !isNaN(ct) && ct > 0 && ct <= 30;
    });
    const rast_events = data.rast_events || [];
    const target = this.targetCycleTime || 3;

    // Tillämpa ev. graf-markering
    let displayCycles = cycles;
    if (this.chartSelectionStartIndex !== null && this.chartSelectionEndIndex !== null) {
      const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
      displayCycles = cycles.slice(minSel, maxSel + 1);
    }

    const labels: string[] = displayCycles.map((c: any) => {
      const d = new Date(c.datum);
      return `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
    });

    const cycleTimeData: number[] = displayCycles.map((c: any) => parseFloat(c.cycle_time));
    const targetLine: number[] = displayCycles.map(() => target);
    const effData = this.calcRollingEfficiency(displayCycles, rast_events, target);

    // Körperioder för bakgrundsfärg
    const pausePeriods = this.buildPausePeriods(rast_events);
    const runningPeriods: any[] = [];
    let cur: any = null;
    displayCycles.forEach((c: any, idx: number) => {
      const ms = new Date(c.datum).getTime();
      const onRast = pausePeriods.some(p => ms >= p.start && ms <= p.end);
      const running = !onRast;
      if (!cur || cur.running !== running) {
        if (cur) runningPeriods.push(cur);
        cur = { startIndex: idx, endIndex: idx, running };
      } else {
        cur.endIndex = idx;
      }
    });
    if (cur) runningPeriods.push(cur);

    return { labels, cycleTime: cycleTimeData, targetCycleTime: targetLine, efficiency: effData, runningPeriods, isPerCycle: true };
  }

  createChart(ctx: CanvasRenderingContext2D, chartData: any) {
    try {
      const isDay = this.viewMode === 'day';
      const isPerCycle = chartData.isPerCycle === true;

      // Månad/år-vy: stapeldiagram med effektivitet (som rebotling)
      if (!isPerCycle) {
        this.createBarChart(ctx, chartData);
        return;
      }

      const datasets: any[] = [
        {
          label: 'Cykeltid (min)',
          data: chartData.cycleTime,
          borderColor: '#00d4ff',
          backgroundColor: 'rgba(0, 212, 255, 0.1)',
          tension: 0.4,
          fill: true,
          yAxisID: 'y',
          pointRadius: isDay ? 2 : 3,
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

      // Mål-cykeltid
      if (this.targetCycleTime > 0 && chartData.targetCycleTime) {
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

      // Rullande effektivitet — visas i dag-vy
      const hasEfficiency = isPerCycle && chartData.efficiency && chartData.efficiency.length > 0;
      if (hasEfficiency) {
        datasets.push({
          label: 'Effektivitet % (30 min)',
          data: chartData.efficiency,
          borderColor: '#68d391',
          backgroundColor: 'rgba(104,211,145,0.08)',
          tension: 0.3,
          fill: false,
          yAxisID: 'yEff',
          pointRadius: 1,
          pointHoverRadius: 5,
          borderWidth: 2,
          borderDash: [],
        });
      }

      if (this.productionChart) { (this.productionChart as any).destroy(); }

      const scales: any = {
        y: {
          beginAtZero: true,
          title: { display: true, text: 'Cykeltid (minuter)', color: '#e0e0e0', font: { size: 13 } },
          ticks: { color: '#a0a0a0' },
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          position: 'left',
        },
        x: {
          ticks: {
            color: '#a0a0a0',
            maxRotation: 45,
            minRotation: 0,
            autoSkip: true,
            maxTicksLimit: isDay ? 24 : undefined
          },
          grid: { color: 'rgba(255, 255, 255, 0.05)' }
        }
      };

      if (hasEfficiency) {
        scales['yEff'] = {
          beginAtZero: true,
          suggestedMin: 0,
          position: 'right',
          title: { display: true, text: 'Effektivitet (%)', color: '#68d391', font: { size: 12 } },
          ticks: { color: '#68d391', callback: (v: number) => v + '%' },
          grid: { drawOnChartArea: false },
        };
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
              intersect: false, mode: 'nearest',
              backgroundColor: 'rgba(20, 20, 20, 0.95)',
              titleColor: '#fff',
              bodyColor: '#e0e0e0',
              borderColor: '#00d4ff',
              borderWidth: 1,
              padding: 12,
              displayColors: true
            }
          },
          scales
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

                ctx.fillStyle = period.running
                  ? 'rgba(34, 139, 34, 0.25)'
                  : 'rgba(220, 53, 69, 0.25)';
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
              } catch (e) {
                console.error('Fel vid bakgrundsritning:', e);
              }
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
                console.error('Fel vid förhandsvisning av markering:', e);
              }
            }
          }
        }]
      });

      // Aktivera interaktiv markering/zoom i grafen för dag-vy
      this.attachChartSelectionHandlers(this.productionChart);

    } catch (error) {
      console.error('Fel vid skapande av diagram:', error);
    }
  }

  private createBarChart(ctx: CanvasRenderingContext2D, chartData: any) {
    const effData: number[] = chartData.efficiencyArr || [];
    const countData: number[] = chartData.cycleCountArr || [];

    const barColors = effData.map((eff: number) => {
      if (eff >= 90) return 'rgba(39, 174, 96, 0.75)';
      if (eff >= 70) return 'rgba(255, 193, 7, 0.75)';
      return eff > 0 ? 'rgba(220, 53, 69, 0.65)' : 'rgba(100, 100, 100, 0.3)';
    });
    const barBorderColors = effData.map((eff: number) => {
      if (eff >= 90) return '#27ae60';
      if (eff >= 70) return '#ffc107';
      return eff > 0 ? '#dc3545' : '#555';
    });

    const maxEff = Math.max(...effData.filter(v => v > 0), 0);
    const yMax = Math.max(maxEff + 18, 115);

    if (this.productionChart) { try { this.productionChart.destroy(); } catch (e) {} }

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
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onClick: (_event: any, elements: any[]) => {
          if (elements.length > 0) {
            const idx = elements[0].index;
            this.onBarChartClick(idx, chartData);
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            intersect: false, mode: 'index',
            backgroundColor: 'rgba(20, 20, 20, 0.95)',
            titleColor: '#fff', bodyColor: '#e0e0e0',
            borderColor: '#4fd1c5', borderWidth: 1, padding: 12,
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
                const effStatus = eff >= 100 ? ' (över mål)' : eff >= 90 ? ' (nära mål)' : ' (under mål)';
                return [`Effektivitet: ${eff}%${effStatus}`, `Antal IBC: ${count} st`];
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            suggestedMin: 0,
            suggestedMax: yMax,
            title: { display: true, text: 'Effektivitet %', color: '#e0e0e0', font: { size: 13 } },
            ticks: { color: '#a0a0a0', callback: (v: string | number) => v + '%' },
            grid: { color: 'rgba(255, 255, 255, 0.05)' }
          },
          x: {
            ticks: { color: '#a0a0a0', maxRotation: 45, minRotation: 0, autoSkip: true },
            grid: { color: 'rgba(255, 255, 255, 0.05)' }
          }
        }
      },
      plugins: [{
        id: 'ibcCountLabels',
        afterDatasetsDraw: (chart: any) => {
          const { ctx: c, chartArea, scales } = chart;
          if (!chartArea) return;
          // 100%-referenslinje
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
              c.fillStyle = 'rgba(255, 255, 255, 0.5)';
              c.font = '10px sans-serif';
              c.textAlign = 'right';
              c.textBaseline = 'bottom';
              c.fillText('Mål 100%', chartArea.right - 4, y100 - 3);
              c.restore();
            }
          }
          // IBC-antal ovanpå varje stapel
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

  private onBarChartClick(index: number, chartData: any) {
    if (!chartData?.labels) return;
    const label = chartData.labels[index];
    if (!label) return;

    if (this.viewMode === 'month') {
      const day = parseInt(label, 10);
      if (isNaN(day)) return;
      const date = new Date(this.currentYear, this.currentMonth, day);
      this.navigateToDay(date);
    } else if (this.viewMode === 'year') {
      const monthIdx = this.monthNames.findIndex(m => m.substring(0, 3) === label);
      if (monthIdx < 0) return;
      this.currentMonth = monthIdx;
      this.navigateToMonth(new Date(this.currentYear, monthIdx, 1));
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

        // Om användaren har markerat ett intervall i dag-vyn, filtrera bort cykler utanför
        if (
          this.chartSelectionStartIndex !== null &&
          this.chartSelectionEndIndex !== null
        ) {
          const bucketIndex = hour * 6 + minute / 10;
          const minSel = Math.min(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
          const maxSel = Math.max(this.chartSelectionStartIndex, this.chartSelectionEndIndex);
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

    grouped.forEach((cycles, _key) => {
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

      const taktMal = this.targetCycleTime || 3;
      const efficiency = avgCycleTime > 0 ? Math.round((taktMal / avgCycleTime) * 100) : 0;

      this.tableData.push({
        period: period,
        date: date,
        cycles: cycles.length,
        avgCycleTime: Math.round(avgCycleTime * 10) / 10,
        efficiency: efficiency,
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
    return '10-min intervall';
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
    link.download = `tvattlinje-statistik-${this.breadcrumb.join('-')}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (this.tableData.length === 0) return;
    import('xlsx').then(XLSX => {
      const data = this.tableData.map(row => ({
        'Period': row.period,
        'Cykler': row.cycles,
        'Cykeltid (min)': row.avgCycleTime,
        'Effektivitet (%)': row.efficiency,
        'Drifttid (min)': row.runtime
      }));
      const ws = XLSX.utils.json_to_sheet(data);
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Statistik');
      XLSX.writeFile(wb, `tvattlinje-statistik-${this.breadcrumb.join('-')}.xlsx`);
    });
  }
  // =========================================================
  // OEE Trend — hämta och rendera 30-dagars OEE-graf
  // =========================================================

  loadOeeTrend() {
    this.oeeTrendLoading = true;
    this.tvattlinjeService.getOeeTrend(this.oeeTrendDagar)
      .pipe(
        timeout(15000),
        catchError(() => of({ success: true, empty: true, message: 'Linjen ej i drift', data: [], summary: { total_ibc: 0, snitt_per_dag: 0, snitt_oee_pct: 0, basta_dag: null, basta_ibc: 0 } } as any)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.oeeTrendLoading = false;
          this.oeeTrendLoaded = true;
          if (!res.success || res.empty) {
            this.oeeTrendEmpty = true;
            this.oeeTrendMessage = res.message || 'Linjen ej i drift';
            this.oeeTrendData = [];
            this.oeeTrendSummary = { total_ibc: 0, snitt_per_dag: 0, snitt_oee_pct: 0, basta_dag: null, basta_ibc: 0 };
          } else {
            this.oeeTrendEmpty = false;
            this.oeeTrendData = res.data || [];
            this.oeeTrendSummary = res.summary || { total_ibc: 0, snitt_per_dag: 0, snitt_oee_pct: 0, basta_dag: null, basta_ibc: 0 };
            if (this.oeeTrendSummary.basta_dag) {
              this.bastaDagLabel = this.oeeTrendSummary.basta_dag;
              this.bastaDagIbc = this.oeeTrendSummary.basta_ibc;
            }
            clearTimeout(this.oeeTrendChartTimer);
            this.oeeTrendChartTimer = setTimeout(() => {
              if (this.destroy$.closed) return;
              this.renderOeeTrendChart();
            }, 100);
          }
        },
        error: () => {
          this.oeeTrendLoading = false;
          this.oeeTrendEmpty = true;
          this.oeeTrendMessage = 'Linjen ej i drift';
        }
      });
  }

  renderOeeTrendChart() {
    if (!this.oeeTrendChartRef?.nativeElement) return;
    try { this.oeeTrendChart?.destroy(); } catch (e) {}
    this.oeeTrendChart = null;
    const labels = this.oeeTrendData.map(d => d.dag.substring(5)); // MM-DD
    const oeeValues = this.oeeTrendData.map(d => d.oee_pct);
    const ibcValues = this.oeeTrendData.map(d => d.total_ibc);

    this.oeeTrendChart = new Chart(this.oeeTrendChartRef.nativeElement, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Kvalitet % (OEE-proxy)',
            data: oeeValues,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            yAxisID: 'yOee',
          },
          {
            label: 'Totalt IBC',
            data: ibcValues,
            borderColor: '#68d391',
            backgroundColor: 'rgba(104,211,145,0.0)',
            fill: false,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 3],
            yAxisID: 'yIbc',
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.06)' }
          },
          yOee: {
            type: 'linear',
            position: 'left',
            min: 0,
            max: 100,
            title: { display: true, text: 'Kvalitet %', color: '#4299e1' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.06)' },
            // WCM 85% referenslinje via annotation workaround (afterDraw)
          },
          yIbc: {
            type: 'linear',
            position: 'right',
            min: 0,
            title: { display: true, text: 'IBC', color: '#68d391' },
            ticks: { color: '#a0aec0' },
            grid: { display: false }
          }
        }
      },
      plugins: [{
        id: 'wcmLine',
        afterDraw(chart: any) {
          const yAxis = chart.scales['yOee'];
          const xAxis = chart.scales['x'];
          if (!yAxis || !xAxis) return;
          const y = yAxis.getPixelForValue(85);
          const ctx = chart.ctx;
          ctx.save();
          ctx.beginPath();
          ctx.moveTo(xAxis.left, y);
          ctx.lineTo(xAxis.right, y);
          ctx.strokeStyle = '#f6ad55';
          ctx.lineWidth = 1.5;
          ctx.setLineDash([6, 4]);
          ctx.stroke();
          ctx.setLineDash([]);
          ctx.fillStyle = '#f6ad55';
          ctx.font = '10px sans-serif';
          ctx.fillText('WCM 85%', xAxis.right - 55, y - 4);
          ctx.restore();
        }
      }]
    });
  }

  onOeeTrendDagarChange() {
    this.oeeTrendLoaded = false;
    this.loadOeeTrend();
  }

  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}