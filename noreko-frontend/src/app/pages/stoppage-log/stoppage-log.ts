import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { ActivatedRoute } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { StoppageService, StoppageReason, StoppageEntry, StoppageStats, StoppageWeeklySummary, ParetoData } from '../../services/stoppage.service';
import { RebotlingService, MonthlyStopSummaryItem } from '../../services/rebotling.service';
import { localToday, localDateStr } from '../../utils/date-utils';
import { Chart, registerables } from 'chart.js';
import QRCode from 'qrcode';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-stoppage-log',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './stoppage-log.html',
  styleUrl: './stoppage-log.css'
})
export class StoppageLogPage implements OnInit, OnDestroy {
  loggedIn = false;
  isAdmin = false;
  user: any = null;

  reasons: StoppageReason[] = [];
  stoppages: StoppageEntry[] = [];
  stats: StoppageStats | null = null;
  weeklySummary: StoppageWeeklySummary | null = null;

  // Pareto tab state
  paretoData: ParetoData | null = null;
  paretoDagar: number = 30;
  paretoLoading = false;
  paretoError = '';

  // Pattern analysis state
  patternData: any = null;
  patternLoading = false;
  patternOpen = false;
  private hourlyChart: Chart | null = null;

  selectedLine: string = 'rebotling';
  selectedPeriod: string = 'week';
  loading = false;
  showForm = false;
  activeTab: 'log' | 'stats' | 'pareto' = 'log';

  // Sorting & search
  searchQuery: string = '';
  private _debouncedSearchQuery: string = '';
  private searchTimer: any = null;
  sortColumn: string = 'start_time';
  sortDirection: 'asc' | 'desc' = 'desc';

  // Date range filter
  filterFromDate: string = '';
  filterToDate: string = '';
  filterCategory: string = '';

  // Submit guard
  submittingStoppage = false;

  // Inline editing
  editingId: number | null = null;
  editDuration: string = '';
  editComment: string = '';
  savingId: number | null = null;

  // QR code section
  qrSectionOpen = false;
  machines = ['Press 1', 'Press 2', 'Robotstation', 'Transportband', 'Ränna', 'Övrigt'];
  qrDataUrls: { [maskin: string]: string } = {};
  qrLoading = false;

  // Template globals
  window = window;
  Object = Object;
  Math = Math;

  // Cached computed properties (uppdateras vid datainlasning/filterandring)
  cachedAvgDuration = 0;
  cachedTotalDowntime = 0;
  cachedTotalDowntimeFiltered = 0;
  cachedUnplannedCount = 0;
  cachedMostCommonReason: { name: string; count: number } | null = null;
  cachedWeekDiffCount: number | null = null;
  cachedWeekDiffMinutes: number | null = null;
  cachedMonthLabel = '';

  get filteredStoppages(): StoppageEntry[] {
    let result = this.stoppages;

    // Text search (debounced)
    if (this._debouncedSearchQuery.trim()) {
      const q = this._debouncedSearchQuery.toLowerCase();
      result = result.filter(s =>
        (s.reason_name || '').toLowerCase().includes(q) ||
        (s.comment || '').toLowerCase().includes(q) ||
        (s.user_name || '').toLowerCase().includes(q) ||
        (s.category === 'planned' ? 'planerat' : 'oplanerat').includes(q)
      );
    }

    // Category filter
    if (this.filterCategory) {
      result = result.filter(s => s.category === this.filterCategory);
    }

    // Date range filter (client-side on loaded data)
    if (this.filterFromDate) {
      const from = new Date(this.filterFromDate + 'T00:00:00');
      result = result.filter(s => new Date(s.start_time) >= from);
    }
    if (this.filterToDate) {
      const to = new Date(this.filterToDate + 'T23:59:59');
      result = result.filter(s => new Date(s.start_time) <= to);
    }

    // Sort
    result = [...result].sort((a: any, b: any) => {
      let valA = a[this.sortColumn];
      let valB = b[this.sortColumn];
      if (valA == null) valA = '';
      if (valB == null) valB = '';
      if (typeof valA === 'string') valA = valA.toLowerCase();
      if (typeof valB === 'string') valB = valB.toLowerCase();
      const cmp = valA < valB ? -1 : valA > valB ? 1 : 0;
      return this.sortDirection === 'asc' ? cmp : -cmp;
    });

    return result;
  }

  /** Uppdatera alla cachade berakningar — anropas vid datainlasning, filterandring, sortering */
  private updateCachedComputations(): void {
    const filtered = this.filteredStoppages;

    // cachedAvgDuration
    const finished = filtered.filter(s => s.duration_minutes !== null && s.duration_minutes !== undefined);
    this.cachedAvgDuration = finished.length === 0 ? 0 :
      Math.round(finished.reduce((sum, s) => sum + (s.duration_minutes || 0), 0) / finished.length);

    // cachedTotalDowntime (all stoppages)
    this.cachedTotalDowntime = this.stoppages.reduce((sum, s) => sum + (s.duration_minutes || 0), 0);

    // cachedTotalDowntimeFiltered
    this.cachedTotalDowntimeFiltered = filtered.reduce((sum, s) => sum + (s.duration_minutes || 0), 0);

    // cachedUnplannedCount
    this.cachedUnplannedCount = this.stoppages.filter(s => s.category === 'unplanned').length;

    // cachedMostCommonReason
    if (filtered.length === 0) {
      this.cachedMostCommonReason = null;
    } else {
      const countMap: Record<string, number> = {};
      for (const s of filtered) {
        const key = s.reason_name || 'Okänd';
        countMap[key] = (countMap[key] || 0) + 1;
      }
      const sorted = Object.entries(countMap).sort((a, b) => b[1] - a[1]);
      this.cachedMostCommonReason = { name: sorted[0][0], count: sorted[0][1] };
    }

    // cachedWeekDiff
    this.cachedWeekDiffCount = this._calcWeekDiff('count');
    this.cachedWeekDiffMinutes = this._calcWeekDiff('total_minutes');

    // cachedMonthLabel
    this.cachedMonthLabel = this.getMonthLabel(this.monthlyStopMonth);
  }

  private _calcWeekDiff(field: 'count' | 'total_minutes'): number | null {
    if (!this.weeklySummary) return null;
    const cur  = Number(this.weeklySummary.this_week[field]);
    const prev = Number(this.weeklySummary.prev_week[field]);
    if (prev === 0) return null;
    return Math.round(((cur - prev) / prev) * 100);
  }

  toggleSort(column: string) {
    if (this.sortColumn === column) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortColumn = column;
      this.sortDirection = column === 'start_time' ? 'desc' : 'asc';
    }
  }

  getSortIcon(column: string): string {
    if (this.sortColumn !== column) return 'fas fa-sort';
    return this.sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  onSearchInput() {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      this._debouncedSearchQuery = this.searchQuery;
      this.updateCachedComputations();
    }, 350);
  }

  newEntry = {
    line: 'rebotling',
    reason_id: 0,
    start_time: '',
    end_time: '',
    comment: ''
  };

  successMessage = '';
  errorMessage = '';
  private destroy$ = new Subject<void>();
  private refreshInterval: any;
  private successTimerId: any = null;
  private chartTimerId: any = null;
  private paretoDetailChart: Chart | null = null;
  private dailyChart: Chart | null = null;
  private weekly14Chart: Chart | null = null;
  // Legacy ref kept for stats-tab simple chart
  private paretoChart: Chart | null = null;

  // Monthly stop summary
  monthlyStopItems: MonthlyStopSummaryItem[] = [];
  monthlyStopMonth: string = '';
  monthlyStopLoading = false;
  monthlyStopError = '';
  private monthlyStopChart: Chart | null = null;

  constructor(
    private auth: AuthService,
    private stoppageService: StoppageService,
    private rebotlingService: RebotlingService,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe((val: any) => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });

    // Read query params for pre-fill (from QR code scan)
    const validLines = ['rebotling', 'tvattlinje', 'saglinje', 'klassificeringslinje'];
    this.route.queryParams.pipe(takeUntil(this.destroy$)).subscribe(params => {
      if (params['maskin']) {
        // Open the form and pre-fill comment with machine name
        this.showForm = true;
        const maskinNamn = decodeURIComponent(params['maskin']).substring(0, 100);
        this.newEntry.comment = maskinNamn;
        // If a line param is provided, validate against allowed values
        if (params['linje'] && validLines.includes(params['linje'])) {
          this.selectedLine = params['linje'];
          this.newEntry.line = params['linje'];
        }
      }
    });

    // Set default times
    const now = new Date();
    this.newEntry.start_time = this.formatDateTime(now);
    this.newEntry.end_time = this.formatDateTime(now);

    // Set default monthly stop month to current month
    this.monthlyStopMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');

    this.loadReasons();
    this.loadStoppages();
    this.loadWeeklySummary();
    this.loadMonthlyStopSummary();

    this.refreshInterval = setInterval(() => this.loadStoppages(), 30000);
  }

  ngOnDestroy() {
    clearTimeout(this.successTimerId);
    clearTimeout(this.chartTimerId);
    clearTimeout(this.searchTimer);
    if (this.refreshInterval) clearInterval(this.refreshInterval);
    try { this.paretoDetailChart?.destroy(); } catch (e) {}
    this.paretoDetailChart = null;
    try { this.paretoChart?.destroy(); } catch (e) {}
    this.paretoChart = null;
    try { this.dailyChart?.destroy(); } catch (e) {}
    this.dailyChart = null;
    try { this.weekly14Chart?.destroy(); } catch (e) {}
    this.weekly14Chart = null;
    try { this.hourlyChart?.destroy(); } catch (e) {}
    this.hourlyChart = null;
    try { this.monthlyStopChart?.destroy(); } catch (e) {}
    this.monthlyStopChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadReasons() {
    this.stoppageService.getReasons().pipe(timeout(8000), catchError(() => of({ success: false, data: [] })), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res?.success) this.reasons = res.data;
      }
    });
  }

  loadStoppages() {
    this.stoppageService.getStoppages(this.selectedLine, this.selectedPeriod).pipe(timeout(8000), catchError(() => of({ success: false, data: [] })), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res?.success) this.stoppages = res.data;
        this.loading = false;
        this.updateCachedComputations();
      },
      error: () => this.loading = false
    });
  }

  loadWeeklySummary() {
    this.stoppageService.getWeeklySummary(this.selectedLine).pipe(timeout(8000), catchError(() => of({ success: false, data: null })), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res?.success) {
          this.weeklySummary = res.data;
          this.updateCachedComputations();
          clearTimeout(this.chartTimerId);
          this.chartTimerId = setTimeout(() => {
            if (!this.destroy$.closed) this.buildWeekly14Chart();
          }, 150);
        }
      },
      error: () => {}
    });
  }

  loadStats() {
    this.stoppageService.getStats(this.selectedLine, this.selectedPeriod).pipe(timeout(8000), catchError(() => of({ success: false, data: null })), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res?.success) {
          this.stats = res.data;
          clearTimeout(this.chartTimerId);
          this.chartTimerId = setTimeout(() => {
            if (!this.destroy$.closed) {
              this.buildParetoChart();
              this.buildDailyChart();
            }
          }, 100);
        }
      }
    });
  }

  loadPareto() {
    this.paretoLoading = true;
    this.paretoError = '';
    this.stoppageService.getPareto(this.selectedLine, this.paretoDagar)
      .pipe(
        takeUntil(this.destroy$),
        timeout(5000),
        catchError(() => of({ success: false, orsaker: [], total_minuter: 0, dagar: this.paretoDagar }))
      )
      .subscribe({
        next: (res: any) => {
          this.paretoLoading = false;
          if (res.success) {
            this.paretoData = { orsaker: res.orsaker, total_minuter: res.total_minuter, dagar: res.dagar };
            clearTimeout(this.chartTimerId);
            this.chartTimerId = setTimeout(() => {
              if (!this.destroy$.closed) this.buildParetoDetailChart();
            }, 50);
          } else {
            this.paretoError = 'Kunde inte hämta Pareto-data';
          }
        },
        error: () => {
          this.paretoLoading = false;
          this.paretoError = 'Timeout — kontrollera anslutningen';
        }
      });
  }

  onParetoDagarChange() {
    this.loadPareto();
  }

  /** How many causes make up >= 80% of downtime */
  getParetoCount80(): number {
    if (!this.paretoData) return 0;
    const idx = this.paretoData.orsaker.findIndex(o => o.kumulativ_pct >= 80);
    return idx >= 0 ? idx + 1 : this.paretoData.orsaker.length;
  }

  getAvgDuration(): number {
    const finished = this.filteredStoppages.filter(s => s.duration_minutes !== null && s.duration_minutes !== undefined);
    if (finished.length === 0) return 0;
    return Math.round(finished.reduce((sum, s) => sum + (s.duration_minutes || 0), 0) / finished.length);
  }

  getWeekDiff(field: 'count' | 'total_minutes'): number | null {
    if (!this.weeklySummary) return null;
    const cur  = Number(this.weeklySummary.this_week[field]);
    const prev = Number(this.weeklySummary.prev_week[field]);
    if (prev === 0) return null;
    return Math.round(((cur - prev) / prev) * 100);
  }

  getTotalDowntimeFiltered(): number {
    return this.filteredStoppages.reduce((sum, s) => sum + (s.duration_minutes || 0), 0);
  }

  getMostCommonReason(): { name: string; count: number } | null {
    if (this.filteredStoppages.length === 0) return null;
    const countMap: Record<string, number> = {};
    for (const s of this.filteredStoppages) {
      const key = s.reason_name || 'Okänd';
      countMap[key] = (countMap[key] || 0) + 1;
    }
    const sorted = Object.entries(countMap).sort((a, b) => b[1] - a[1]);
    return { name: sorted[0][0], count: sorted[0][1] };
  }

  getDurationBadge(minutes: number | null): { label: string; cls: string } {
    if (minutes === null || minutes === undefined) return { label: 'Pågår', cls: 'badge-running' };
    if (minutes < 5) return { label: 'Kort', cls: 'badge-short' };
    if (minutes <= 15) return { label: 'Medel', cls: 'badge-medium' };
    return { label: 'Långt', cls: 'badge-long' };
  }

  switchTab(tab: 'log' | 'stats' | 'pareto') {
    this.activeTab = tab;
    if (tab === 'stats') this.loadStats();
    if (tab === 'pareto') this.loadPareto();
  }

  onFilterChange() {
    this.loading = true;
    this.newEntry.line = this.selectedLine;
    this.loadStoppages();
    this.loadWeeklySummary();
    if (this.activeTab === 'stats') this.loadStats();
    if (this.activeTab === 'pareto') this.loadPareto();
  }

  setQuickPeriod(period: string) {
    this.selectedPeriod = period;
    this.filterFromDate = '';
    this.filterToDate = '';
    this.onFilterChange();
  }

  /** Uppdatera cachade berakningar nar lokala filter (kategori, datum) andras */
  onLocalFilterChange() {
    this.updateCachedComputations();
  }

  clearDateFilter() {
    this.filterFromDate = '';
    this.filterToDate = '';
    this.updateCachedComputations();
  }

  addStoppage() {
    this.errorMessage = '';
    if (!this.newEntry.reason_id) {
      this.errorMessage = 'Välj en stopporsak';
      return;
    }
    if (!this.newEntry.start_time) {
      this.errorMessage = 'Starttid krävs';
      return;
    }
    if (this.submittingStoppage) return;

    this.submittingStoppage = true;
    this.stoppageService.create(this.newEntry).pipe(timeout(8000), catchError((err) => of({ success: false, message: err?.error?.message || 'Ett fel uppstod' })), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        this.submittingStoppage = false;
        if (res.success) {
          this.showSuccess('Stoppost registrerad');
          this.showForm = false;
          this.loadStoppages();
          // Reset form
          const now = new Date();
          this.newEntry.reason_id = 0;
          this.newEntry.start_time = this.formatDateTime(now);
          this.newEntry.end_time = this.formatDateTime(now);
          this.newEntry.comment = '';
        } else {
          this.errorMessage = res.message || 'Kunde inte registrera';
        }
      }
    });
  }

  deleteStoppage(id: number) {
    if (!confirm('Är du säker på att du vill ta bort denna stoppost?')) return;
    this.stoppageService.delete(id).pipe(timeout(8000), catchError(() => of({ success: false })), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.stoppages = this.stoppages.filter(s => s.id !== id);
          this.updateCachedComputations();
          this.showSuccess('Stoppost borttagen');
        }
      }
    });
  }

  canEdit(entry: StoppageEntry): boolean {
    return this.isAdmin || (this.user && entry.user_id === this.user.id);
  }

  // Inline editing
  startEdit(entry: StoppageEntry) {
    this.editingId = entry.id;
    this.editDuration = entry.duration_minutes !== null && entry.duration_minutes !== undefined
      ? String(entry.duration_minutes)
      : '';
    this.editComment = entry.comment || '';
  }

  cancelEdit() {
    this.editingId = null;
    this.editDuration = '';
    this.editComment = '';
  }

  saveEdit(entry: StoppageEntry) {
    if (this.savingId) return;
    const parsed = parseInt(this.editDuration, 10);
    const duration = this.editDuration.trim() === '' ? null : (isNaN(parsed) ? null : parsed);
    if (duration !== null && (duration < 0 || duration > 14400)) {
      this.errorMessage = 'Varaktighet måste vara 0–14400 minuter';
      return;
    }
    this.savingId = entry.id;
    const comment = this.editComment;

    this.stoppageService.update(entry.id, { duration_minutes: duration, comment })
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of({ success: false, message: 'Timeout' }))
      )
      .subscribe({
        next: (res: any) => {
          this.savingId = null;
          if (res.success) {
            // Update locally
            const idx = this.stoppages.findIndex(s => s.id === entry.id);
            if (idx >= 0) {
              this.stoppages[idx] = {
                ...this.stoppages[idx],
                duration_minutes: duration,
                comment: comment
              };
            }
            this.editingId = null;
            this.editDuration = '';
            this.editComment = '';
            this.updateCachedComputations();
            this.showSuccess('Stoppost uppdaterad');
          } else {
            this.errorMessage = res.message || 'Kunde inte spara';
          }
        },
        error: () => {
          this.savingId = null;
          this.errorMessage = 'Fel vid sparning';
        }
      });
  }

  get stopSummaryStats(): { total: number; totalMin: number; avgMin: number } {
    const stops = this.filteredStoppages || this.stoppages || [];
    const totalMin = stops.reduce((sum: number, s: any) => sum + (s.duration_minutes || 0), 0);
    return { total: stops.length, totalMin, avgMin: stops.length > 0 ? Math.round(totalMin / stops.length) : 0 };
  }

  formatMinutes(min: number): string {
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    if (h === 0) return `${m} min`;
    return `${h}h ${m}min`;
  }

  calcDuration(stopp: any): number {
    if (stopp.duration_minutes !== null && stopp.duration_minutes !== undefined) return stopp.duration_minutes;
    if (stopp.start_time && stopp.end_time) {
      return Math.round((new Date(stopp.end_time).getTime() - new Date(stopp.start_time).getTime()) / 60000);
    }
    return 0;
  }

  formatDuration(minutes: number | null): string {
    if (minutes === null || minutes === undefined) return 'Pågår';
    if (minutes < 60) return Math.round(minutes) + ' min';
    const h = Math.floor(minutes / 60);
    const m = Math.round(minutes % 60);
    return h + ' h ' + (m > 0 ? m + ' min' : '');
  }

  getTotalDowntime(): number {
    return this.stoppages.reduce((sum, s) => sum + (s.duration_minutes || 0), 0);
  }

  getUnplannedCount(): number {
    return this.stoppages.filter(s => s.category === 'unplanned').length;
  }

  exportCSV() {
    const data = this.filteredStoppages;
    if (data.length === 0) return;
    const header = ['ID', 'Linje', 'Orsak', 'Kategori', 'Start', 'Slut', 'Varaktighet (min)', 'Kommentar', 'Användare'];
    const rows = data.map(s => [
      s.id, s.line, s.reason_name, s.category === 'planned' ? 'Planerat' : 'Oplanerat',
      s.start_time, s.end_time || 'Pågår', s.duration_minutes ?? '', s.comment, s.user_name
    ]);
    const csv = [header, ...rows].map(r => r.map(c => `"${String(c ?? '').replace(/"/g, '""')}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `stopporsaker-${this.selectedLine}-${this.selectedPeriod}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    const data = this.filteredStoppages;
    if (data.length === 0) return;
    import('xlsx').then(XLSX => {
      const rows = data.map(s => ({
        'ID': s.id,
        'Linje': s.line,
        'Orsak': s.reason_name,
        'Kategori': s.category === 'planned' ? 'Planerat' : 'Oplanerat',
        'Start': s.start_time,
        'Slut': s.end_time || 'Pågår',
        'Varaktighet (min)': s.duration_minutes ?? '',
        'Kommentar': s.comment || '',
        'Användare': s.user_name || ''
      }));
      const ws = XLSX.utils.json_to_sheet(rows);
      // Set column widths
      ws['!cols'] = [
        { wch: 6 }, { wch: 16 }, { wch: 24 }, { wch: 12 },
        { wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 30 }, { wch: 16 }
      ];
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Stopporsaker');
      const dateStr = localToday();
      XLSX.writeFile(wb, `stopporsaker-${this.selectedLine}-${dateStr}.xlsx`);
    });
  }

  togglePatternSection() {
    this.patternOpen = !this.patternOpen;
    if (this.patternOpen && !this.patternData && !this.patternLoading) {
      this.loadPatternAnalysis();
    } else if (this.patternOpen && this.patternData) {
      // Rebuild chart when opening
      clearTimeout(this.chartTimerId);
      this.chartTimerId = setTimeout(() => {
        if (!this.destroy$.closed) this.buildHourlyChart();
      }, 50);
    }
  }

  loadPatternAnalysis() {
    this.patternLoading = true;
    this.stoppageService.getPatternAnalysis(this.selectedLine, 30)
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of(null))
      )
      .subscribe({
        next: (res: any) => {
          this.patternLoading = false;
          if (res && res.success) {
            this.patternData = res;
            clearTimeout(this.chartTimerId);
            this.chartTimerId = setTimeout(() => {
              if (!this.destroy$.closed) this.buildHourlyChart();
            }, 50);
          }
        },
        error: () => {
          this.patternLoading = false;
        }
      });
  }

  getTopHours(n: number = 3): number[] {
    if (!this.patternData?.hourly_distribution) return [];
    const sorted = [...this.patternData.hourly_distribution]
      .sort((a: any, b: any) => b.antal - a.antal)
      .slice(0, n)
      .map((h: any) => h.timme);
    return sorted;
  }

  getCostlyBarWidth(totalMin: number, maxMin: number): number {
    if (maxMin === 0) return 0;
    return Math.round((totalMin / maxMin) * 100);
  }

  getMaxCostlyMin(): number {
    if (!this.patternData?.costly_reasons?.length) return 0;
    return Math.max(...this.patternData.costly_reasons.map((r: any) => r.total_min));
  }

  // QR Code methods
  toggleQRSection() {
    this.qrSectionOpen = !this.qrSectionOpen;
  }

  async generateQRCodes(): Promise<void> {
    this.qrLoading = true;
    const baseUrl = window.location.origin + window.location.pathname.replace(/[^/]*$/, '') + '#/stopporsaker';
    for (const maskin of this.machines) {
      const url = `${baseUrl}?maskin=${encodeURIComponent(maskin)}`;
      try {
        this.qrDataUrls[maskin] = await QRCode.toDataURL(url, {
          width: 200,
          margin: 1,
          color: { dark: '#000000', light: '#FFFFFF' }
        });
      } catch (e) {
        this.errorMessage = 'QR-generering misslyckades för ' + maskin;
      }
    }
    this.qrLoading = false;
  }

  printQRCodes() {
    window.print();
  }

  private buildHourlyChart() {
    try { this.hourlyChart?.destroy(); } catch (e) {}
    this.hourlyChart = null;
    const canvas = document.getElementById('hourlyChart') as HTMLCanvasElement;
    if (!canvas || !this.patternData?.hourly_distribution) return;

    const distribution = this.patternData.hourly_distribution;
    const topHours = this.getTopHours(3);
    const labels = distribution.map((h: any) => h.timme.toString().padStart(2, '0') + ':00');
    const data = distribution.map((h: any) => h.antal);
    const colors = distribution.map((h: any) =>
      topHours.includes(h.timme) ? 'rgba(239,68,68,0.75)' : 'rgba(100,116,139,0.5)'
    );
    const borderColors = distribution.map((h: any) =>
      topHours.includes(h.timme) ? '#ef4444' : 'rgba(100,116,139,0.8)'
    );

    if (this.hourlyChart) { (this.hourlyChart as any).destroy(); }
    this.hourlyChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Antal stopp',
          data,
          backgroundColor: colors,
          borderColor: borderColors,
          borderWidth: 1,
          borderRadius: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              title: (items) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && distribution[idx]) {
                  const timme = distribution[idx].timme;
                  return `Kl ${String(timme).padStart(2, '0')}:00–${String(timme).padStart(2, '0')}:59`;
                }
                return '';
              },
              label: (ctx: any) => {
                const h = distribution[ctx.dataIndex];
                if (!h) return `  Antal stopp: ${ctx.parsed.y ?? 0}`;
                const parts = [`  Antal stopp: ${ctx.parsed.y ?? 0}`];
                if (h.snitt_min) {
                  const sm = h.snitt_min;
                  const sh = Math.floor(sm / 60);
                  const smin = Math.round(sm % 60);
                  parts.push(`  Snitt varaktighet: ${sh > 0 ? sh + 'h ' + smin + 'min' : smin + ' min'}`);
                }
                if (topHours.includes(h.timme)) parts.push('  \u26a0 Peak-stoptid');
                return parts;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#94a3b8', maxRotation: 45, font: { size: 10 } },
            grid: { color: 'rgba(45,55,72,0.8)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#94a3b8', stepSize: 1 },
            grid: { color: 'rgba(45,55,72,0.8)' }
          }
        }
      }
    });
  }

  private formatDateTime(d: Date): string {
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  private showSuccess(msg: string) {
    this.successMessage = msg;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.successMessage = '';
    }, 3000);
  }

  // ---- Månadssammanfattning stopp ----

  loadMonthlyStopSummary(): void {
    if (this.monthlyStopLoading) return;
    this.monthlyStopLoading = true;
    this.monthlyStopError = '';

    this.rebotlingService.getMonthlyStopSummary(this.monthlyStopMonth)
      .pipe(
        takeUntil(this.destroy$),
        timeout(8000),
        catchError(() => of({ success: false, items: [], error: 'Timeout' }))
      )
      .subscribe({
        next: (res: any) => {
          this.monthlyStopLoading = false;
          if (res.success) {
            this.monthlyStopItems = res.items ?? [];
            clearTimeout(this.chartTimerId);
            this.chartTimerId = setTimeout(() => {
              if (!this.destroy$.closed) this.buildMonthlyStopChart();
            }, 100);
          } else {
            this.monthlyStopError = res.error || 'Kunde inte hämta data';
          }
        },
        error: () => {
          this.monthlyStopLoading = false;
          this.monthlyStopError = 'Anslutningsfel';
        }
      });
  }

  onMonthlyStopMonthChange(): void {
    this.cachedMonthLabel = this.getMonthLabel(this.monthlyStopMonth);
    this.loadMonthlyStopSummary();
  }

  getMonthLabel(monthStr: string): string {
    const manadNamn = ['Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                       'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];
    const parts = monthStr.split('-');
    if (parts.length !== 2) return monthStr;
    const idx = parseInt(parts[1], 10) - 1;
    return (manadNamn[idx] ?? parts[1]) + ' ' + parts[0];
  }

  private buildMonthlyStopChart(): void {
    try { this.monthlyStopChart?.destroy(); } catch (e) {}
    this.monthlyStopChart = null;
    const canvas = document.getElementById('monthlyStopChart') as HTMLCanvasElement;
    if (!canvas || this.monthlyStopItems.length === 0) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const items = this.monthlyStopItems;
    const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6'];

    if (this.monthlyStopChart) { (this.monthlyStopChart as any).destroy(); }
    this.monthlyStopChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: items.map(i => i.orsak),
        datasets: [{
          label: 'Stopptid (min)',
          data: items.map(i => i.total_min),
          backgroundColor: items.map((_, idx) => (colors[idx] || '#6b7280') + 'cc'),
          borderColor: items.map((_, idx) => colors[idx] || '#6b7280'),
          borderWidth: 1,
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (tooltipCtx: any) => {
                const item = items[tooltipCtx.dataIndex];
                if (!item) return '';
                const min = item.total_min;
                const h = Math.floor(min / 60);
                const m = Math.round(min % 60);
                const formatted = h > 0 ? `${h}h ${m}min` : `${m} min`;
                return [`  Stopptid: ${formatted}`, `  Antal: ${item.antal} stopp`, `  Andel: ${item.pct}%`];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'Minuter', color: '#a0aec0' }
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid: { color: '#2d3748' }
          }
        }
      }
    });
  }

  private buildParetoChart() {
    try { this.paretoChart?.destroy(); } catch (e) {}
    this.paretoChart = null;
    const canvas = document.getElementById('paretoChart') as HTMLCanvasElement;
    if (!canvas || !this.stats) return;

    const reasons = this.stats.reasons;
    this.paretoChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: reasons.map(r => r.name),
        datasets: [{
          label: 'Stopptid (min)',
          data: reasons.map(r => r.total_minutes),
          backgroundColor: reasons.map(r => r.color + 'cc'),
          borderColor: reasons.map(r => r.color),
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          title: { display: true, text: 'Stopptid per orsak (Pareto)', color: '#e2e8f0' },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => {
                const min = ctx.parsed.y ?? 0;
                const h = Math.floor(min / 60);
                const m = Math.round(min % 60);
                const formatted = h > 0 ? `${h}h ${m}min` : `${m} min`;
                const r = reasons[ctx.dataIndex];
                const pct = r && this.stats ? Math.round((r.total_minutes / this.stats.reasons.reduce((s, x) => s + x.total_minutes, 0)) * 100) : 0;
                return [`  Stopptid: ${formatted}`, `  Andel: ${pct}%`, `  Antal: ${r?.count ?? 0} stopp`];
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Minuter', color: '#a0aec0' } }
        }
      }
    });
  }

  private buildWeekly14Chart() {
    try { this.weekly14Chart?.destroy(); } catch (e) {}
    this.weekly14Chart = null;
    const canvas = document.getElementById('weekly14Chart') as HTMLCanvasElement;
    if (!canvas || !this.weeklySummary) return;

    const daily = this.weeklySummary.daily_14;

    // Fill in all 14 days (including zeros)
    const labels: string[] = [];
    const countData: number[] = [];
    const minuteData: number[] = [];
    for (let i = 13; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      const dateStr = localDateStr(d);
      const found = daily.find(x => x.dag === dateStr);
      labels.push(dateStr.substring(5)); // MM-DD
      countData.push(found ? Number(found.count) : 0);
      minuteData.push(found ? Number(found.total_minutes) : 0);
    }

    if (this.weekly14Chart) { (this.weekly14Chart as any).destroy(); }
    this.weekly14Chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Antal stopp/dag',
          data: countData,
          backgroundColor: countData.map(v => v === 0 ? 'rgba(107,114,128,0.2)' : 'rgba(239,68,68,0.55)'),
          borderColor: countData.map(v => v === 0 ? 'rgba(107,114,128,0.4)' : '#ef4444'),
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          title: { display: true, text: 'Antal stopp per dag — senaste 14 dagar', color: '#e2e8f0' },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              title: (items) => {
                const idx = items[0]?.dataIndex ?? -1;
                return idx >= 0 ? labels[idx] : '';
              },
              label: (ctx) => {
                const count = ctx.parsed.y ?? 0;
                return `  Antal stopp: ${count}`;
              },
              afterLabel: (ctx) => {
                const min = minuteData[ctx.dataIndex];
                if (min > 0) {
                  const h = Math.floor(min / 60);
                  const m = Math.round(min % 60);
                  return `  Stopptid: ${h > 0 ? h + 'h ' + m + 'min' : m + ' min'}`;
                }
                return '';
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0', maxRotation: 45 }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: '#2d3748' },
            beginAtZero: true,
            title: { display: true, text: 'Antal', color: '#a0aec0' }
          }
        }
      }
    });
  }

  private buildDailyChart() {
    try { this.dailyChart?.destroy(); } catch (e) {}
    this.dailyChart = null;
    const canvas = document.getElementById('dailyChart') as HTMLCanvasElement;
    if (!canvas || !this.stats) return;

    const daily = this.stats.daily;
    this.dailyChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: daily.map(d => d.dag),
        datasets: [{
          label: 'Stopptid (min/dag)',
          data: daily.map(d => d.total_minutes),
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239, 68, 68, 0.1)',
          fill: true,
          tension: 0.3
        }, {
          label: 'Antal stopp/dag',
          data: daily.map(d => d.count),
          borderColor: '#f97316',
          backgroundColor: 'rgba(249, 115, 22, 0.1)',
          fill: false,
          tension: 0.3,
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: { display: true, text: 'Stopptid per dag', color: '#e2e8f0' },
          legend: { labels: { color: '#a0aec0' } },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              title: (items) => {
                const idx = items[0]?.dataIndex ?? -1;
                return idx >= 0 && daily[idx] ? daily[idx].dag : '';
              },
              label: (ctx) => {
                if (ctx.datasetIndex === 0) {
                  const min = ctx.parsed.y ?? 0;
                  const h = Math.floor(min / 60);
                  const m = Math.round(min % 60);
                  return `  Stopptid: ${h > 0 ? h + 'h ' + m + 'min' : m + ' min'}`;
                }
                return `  Antal stopp: ${ctx.parsed.y}`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Minuter', color: '#a0aec0' } },
          y1: { position: 'right', ticks: { color: '#a0aec0' }, grid: { display: false }, title: { display: true, text: 'Antal', color: '#a0aec0' } }
        }
      }
    });
  }

  private buildParetoDetailChart() {
    try { this.paretoDetailChart?.destroy(); } catch (e) {}
    this.paretoDetailChart = null;
    const canvas = document.getElementById('paretoDetailChart') as HTMLCanvasElement;
    if (!canvas || !this.paretoData || this.paretoData.orsaker.length === 0) return;

    const orsaker = this.paretoData.orsaker;

    // Custom plugin: draw a dashed horizontal reference line at 80% on y1 axis
    const refLine80Plugin = {
      id: 'refLine80',
      afterDraw(chart: any) {
        const y1 = chart.scales['y1'];
        if (!y1) return;
        const y80 = y1.getPixelForValue(80);
        const ctx80 = chart.ctx;
        const left = chart.chartArea.left;
        const right = chart.chartArea.right;
        ctx80.save();
        ctx80.beginPath();
        ctx80.setLineDash([6, 4]);
        ctx80.strokeStyle = 'rgba(245, 158, 11, 0.75)';
        ctx80.lineWidth = 2;
        ctx80.moveTo(left, y80);
        ctx80.lineTo(right, y80);
        ctx80.stroke();
        // Label
        ctx80.setLineDash([]);
        ctx80.fillStyle = 'rgba(245, 158, 11, 0.9)';
        ctx80.font = 'bold 11px sans-serif';
        ctx80.fillText('80%', right + 4, y80 + 4);
        ctx80.restore();
      }
    };

    if (this.paretoDetailChart) { (this.paretoDetailChart as any).destroy(); }
    this.paretoDetailChart = new Chart(canvas, {
      type: 'bar',
      plugins: [refLine80Plugin],
      data: {
        labels: orsaker.map(o => o.orsak),
        datasets: [
          {
            type: 'bar' as const,
            label: 'Stillestånd (min)',
            data: orsaker.map(o => o.total_minuter),
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderColor: '#ef4444',
            borderWidth: 1,
            borderRadius: 4,
            yAxisID: 'y'
          },
          {
            type: 'line' as const,
            label: 'Kumulativ %',
            data: orsaker.map(o => o.kumulativ_pct),
            borderColor: '#f59e0b',
            backgroundColor: 'transparent',
            pointBackgroundColor: '#f59e0b',
            pointRadius: 4,
            borderWidth: 2,
            tension: 0.1,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' }
          },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed.y;
                if (v == null) return '';
                if (ctx.datasetIndex === 0) {
                  return ` ${v} min (${orsaker[ctx.dataIndex]?.pct ?? 0}%)`;
                }
                return ` Kumulativ: ${v}%`;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 40 },
            grid: { color: '#2d3748' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'Minuter stillestånd', color: '#a0aec0' }
          },
          y1: {
            position: 'right',
            min: 0,
            max: 100,
            ticks: { color: '#f59e0b', callback: (v) => v + '%' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Kumulativ %', color: '#f59e0b' }
          }
        }
      }
    });
  }
  trackByIndex(index: number): number { return index; }
}
