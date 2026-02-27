import { Component, OnInit, ViewChild, ElementRef, AfterViewInit, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { Chart, registerables } from 'chart.js';
import { TvattlinjeService } from '../../services/tvattlinje.service';

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
  imports: [CommonModule, FormsModule]
})
export class TvattlinjeStatistikPage implements OnInit, AfterViewInit {
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

  isDragging: boolean = false;

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

    this.tvattlinjeService.getStatistics(start, end).subscribe({
      next: (response) => {
        if (response.success) {
          // Spara senaste data så vi kan zooma/markera i grafen
          this.lastStatisticsData = response.data;
          this.updateStatistics(response.data);
          this.updateChart(response.data);
          this.updateTable(response.data);
        }
        this.loading = false;
      },
      error: (err) => {
        console.error('Error loading statistics:', err);
        this.error = 'Kunde inte ladda statistik från backend';
        this.loading = false;
        this.loadMockData();
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
            // Generate 20-30 cycles per hour for tvättlinje (faster than rebotling)
            const numCycles = 20 + Math.floor(Math.random() * 11);

            for (let c = 0; c < numCycles; c++) {
              const minute = Math.floor(Math.random() * 60);
              const cycleDate = new Date(currentDate);
              cycleDate.setHours(hour, minute, 0, 0);

              const cycleTime = 2 + Math.random() * 2; // 2-4 minutes (faster than rebotling)
              const targetCycleTime = 3; // Mock target

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

    const targetCycleTime = 3; // Mock target

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

    setTimeout(() => {
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

    return { labels, cycleTime, avgCycleTime: avgCycleTimeArr, targetCycleTime: targetCycleTimeArr, runningPeriods };
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
}
