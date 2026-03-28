import { Component, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { Chart, registerables } from 'chart.js';
import { SkiftrapportService } from '../../services/skiftrapport.service';
import { AuthService } from '../../services/auth.service';
import { localToday, localDateStr } from '../../utils/date-utils';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

type SortField = 'datum' | 'product_name' | 'user_name' | 'ibc_ok' | 'bur_ej_ok' | 'ibc_ej_ok' | 'totalt' | 'kvalitet' | 'ibc_per_h' | 'effektivitet';
type SortDir   = 'asc' | 'desc';

@Component({
  standalone: true,
  selector: 'app-rebotling-skiftrapport',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './rebotling-skiftrapport.html',
  styleUrl: './rebotling-skiftrapport.css'
})
export class RebotlingSkiftrapportPage implements OnInit, OnDestroy {
  Math = Math;

  reports: any[] = [];
  products: any[] = [];
  selectedIds: Set<number> = new Set();
  expanded: { [id: number]: boolean } = {};
  expandedDays: { [dateKey: string]: boolean } = {};
  loading = false;
  errorMessage = '';
  successMessage = '';
  showSuccessMessage = false;
  isAdmin = false;
  user: any = null;
  showAddReportForm = false;
  loggedIn = false;

  // Date + shift filter
  filterFrom  = '';
  filterTo    = '';
  filterSkift = ''; // förmiddag | eftermiddag | natt | ''

  // Search
  searchText = '';
  private _debouncedSearchText: string = '';
  private searchTimer: any = null;

  // Operatörsfilter
  operators: any[] = [];
  selectedOperatorId: number | null = null;
  operatorsLoading = false;

  addingReport = false;

  // Sort
  sortField: SortField = 'datum';
  sortDir: SortDir     = 'desc';

  // Löpnummer lazy-load
  lopnummerMap: { [reportId: number]: string } = {};
  lopnummerLoading: { [reportId: number]: boolean } = {};
  skiftTiderMap: { [reportId: number]: { start: string | null; slut: string | null; cykel_datum?: string | null; fallback?: number } } = {};

  // Settings for bonus estimate
  private settings: any = { rebotlingTarget: 1000, shiftHours: 8.0 };

  // ---- Skicka skiftrapport via email ----
  showEmailConfirm = false;
  emailSending = false;
  emailReportDate = '';
  emailReportShift = 1;

  // ---- Skiftjämförelse ----
  compareDateA = '';
  compareDateB = '';
  compareLoading = false;
  compareError   = '';
  compareResult: { a: any; b: any } | null = null;

  // ---- Operatör-KPI-jämförelse (session #376) ----
  opKpiData: any[] = [];
  opKpiLoading = false;
  opKpiError = '';
  private opKpiChart: Chart | null = null;
  private opKpiBuildTimer: any = null;

  // ---- Skiftkommentar ----
  kommentarMap: { [reportId: number]: string } = {};
  kommentarLoading: { [reportId: number]: boolean } = {};
  redigerarKommentar: { [reportId: number]: boolean } = {};
  spararKommentar: { [reportId: number]: boolean } = {};
  editKommentar: { [reportId: number]: string } = {};

  // ---- Produktionstrendgraf ----
  @ViewChild('trendCanvas') trendCanvasRef!: ElementRef<HTMLCanvasElement>;
  selectedTrendReportId: number | null = null;
  trendData: any = null;
  trendLoading = false;
  trendError = '';
  private trendChart: Chart | null = null;
  private efficiencyChart: Chart | null = null;
  private effBuildTimer: any = null;

  // ---- Skift-navigation ----
  // skifts = filteredReports (computed getter), selectedSkift = skiftraknare
  selectedSkift: number | null = null;
  selectedSkiftIndex: number = -1;

  // ---- Skiftsammanfattning (print-view) ----
  shiftSummaryReportId: number | null = null;
  shiftSummaryData: any = null;
  shiftSummaryLoading = false;
  shiftSummaryError = '';

  private destroy$ = new Subject<void>();
  private fetchSub: Subscription | null = null;
  private updateInterval: any = null;
  private successTimerId: any = null;
  private trendBuildTimer: any = null;
  private scrollRestoreTimer: any = null;

  constructor(
    private skiftrapportService: SkiftrapportService,
    private auth: AuthService,
    private http: HttpClient
  ) {}

  newReport = {
    datum: localToday(),
    product_id: null as number | null,
    ibc_ok: 0,
    bur_ej_ok: 0,
    ibc_ej_ok: 0
  };

  toggleAddReportForm(): void {
    this.showAddReportForm = !this.showAddReportForm;
    if (this.showAddReportForm) {
      this.newReport = { datum: localToday(), product_id: null, ibc_ok: 0, bur_ej_ok: 0, ibc_ej_ok: 0 };
      this.errorMessage = '';
    }
  }

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(val => this.loggedIn = val);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      this.user = user;
      this.isAdmin = user?.role === 'admin';
    });
    this.fetchReports();
    this.fetchProducts();
    this.loadSettings();
    this.loadOperators();
    this.loadOpKpiJamforelse();

    // Uppdatera tabellen var 10:e sekund
    this.updateInterval = setInterval(() => {
      if (!this.destroy$.closed) this.fetchReports(true);
    }, 10000);
  }

  ngOnDestroy() {
    clearInterval(this.updateInterval);
    clearTimeout(this.successTimerId);
    clearTimeout(this.searchTimer);
    clearTimeout(this.trendBuildTimer);
    clearTimeout(this.effBuildTimer);
    clearTimeout(this.scrollRestoreTimer);
    clearTimeout(this.opKpiBuildTimer);
    this.fetchSub?.unsubscribe();
    try { this.trendChart?.destroy(); } catch (e) {}
    this.trendChart = null;
    try { this.efficiencyChart?.destroy(); } catch (e) {}
    this.efficiencyChart = null;
    try { this.opKpiChart?.destroy(); } catch (e) {}
    this.opKpiChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  private loadSettings() {
    this.http.get<any>(`${environment.apiUrl}?action=rebotling&run=admin-settings`, { withCredentials: true })
      .pipe(timeout(8000), catchError(err => { console.error('Fel vid laddning av inställningar:', err); return of(null); }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res?.success && res.data) {
            this.settings = res.data;
          }
        }
      });
  }

  // ========== Shift helper ==========
  private getShiftForReport(r: any): string {
    if (!r.datum) return '';
    const timeStr = (r.datum || '').substring(11, 16);
    if (!timeStr) return '';
    const [hh, mm] = timeStr.split(':').map(Number);
    const minutes = hh * 60 + mm;
    if (minutes >= 6 * 60 && minutes < 14 * 60)  return 'förmiddag';
    if (minutes >= 14 * 60 && minutes < 22 * 60) return 'eftermiddag';
    return 'natt';
  }

  // ========== Date filter ==========
  get filteredReports(): any[] {
    let result = this.reports.filter(r => {
      const d = (r.datum || '').substring(0, 10);
      if (this.filterFrom && d < this.filterFrom) return false;
      if (this.filterTo   && d > this.filterTo)   return false;
      if (this.filterSkift) {
        if (this.getShiftForReport(r) !== this.filterSkift) return false;
      }
      if (this._debouncedSearchText) {
        const q = this._debouncedSearchText.toLowerCase();
        const searchable = [
          r.datum || '',
          r.product_name || '',
          r.user_name || '',
          String(r.ibc_ok ?? ''),
          String(r.totalt ?? '')
        ].join(' ').toLowerCase();
        if (!searchable.includes(q)) return false;
      }
      // Operatörsfilter — matcha op1/op2/op3 nummer mot vald operatörs nummer
      if (this.selectedOperatorId !== null) {
        const op = this.operators.find(o => o.id === this.selectedOperatorId);
        if (op) {
          const num = Number(op.number);
          const op1 = Number(r.op1);
          const op2 = Number(r.op2);
          const op3 = Number(r.op3);
          if (op1 !== num && op2 !== num && op3 !== num) return false;
        }
      }
      return true;
    });

    // Sort
    result = [...result].sort((a, b) => {
      let aVal: any;
      let bVal: any;
      switch (this.sortField) {
        case 'datum':        aVal = a.datum ?? ''; bVal = b.datum ?? ''; break;
        case 'product_name': aVal = (a.product_name ?? '').toLowerCase(); bVal = (b.product_name ?? '').toLowerCase(); break;
        case 'user_name':    aVal = (a.user_name ?? '').toLowerCase(); bVal = (b.user_name ?? '').toLowerCase(); break;
        case 'ibc_ok':       aVal = a.ibc_ok ?? 0;     bVal = b.ibc_ok ?? 0; break;
        case 'bur_ej_ok':    aVal = a.bur_ej_ok ?? 0;  bVal = b.bur_ej_ok ?? 0; break;
        case 'ibc_ej_ok':    aVal = a.ibc_ej_ok ?? 0;  bVal = b.ibc_ej_ok ?? 0; break;
        case 'totalt':       aVal = a.totalt ?? 0;      bVal = b.totalt ?? 0; break;
        case 'kvalitet':     aVal = this.getQualityPct(a) ?? -1; bVal = this.getQualityPct(b) ?? -1; break;
        case 'ibc_per_h':    aVal = this.getIbcPerHour(a) ?? -1; bVal = this.getIbcPerHour(b) ?? -1; break;
        case 'effektivitet': aVal = this.getEfficiency(a) ?? -1; bVal = this.getEfficiency(b) ?? -1; break;
        default:             aVal = a.datum ?? ''; bVal = b.datum ?? '';
      }
      if (aVal < bVal) return this.sortDir === 'asc' ? -1 : 1;
      if (aVal > bVal) return this.sortDir === 'asc' ?  1 : -1;
      return 0;
    });

    return result;
  }

  // ========== Day-grouped view ==========
  get groupedDays(): Array<{
    date: string;
    reports: any[];
    totalIbc: number;
    totalDrift: number;
    products: string[];
    operators: string[];
    avgEfficiency: number | null;
  }> {
    const reports = this.filteredReports;
    const dayMap: { [date: string]: any[] } = {};
    reports.forEach(r => {
      const d = (r.datum || '').substring(0, 10);
      if (!dayMap[d]) dayMap[d] = [];
      dayMap[d].push(r);
    });

    const days = Object.entries(dayMap).map(([date, dayReports]) => {
      const totalIbc = dayReports.reduce((s, r) => s + (r.totalt || 0), 0);
      const totalDrift = dayReports.reduce((s, r) => s + (r.drifttid || 0), 0);

      const productSet = new Set<string>();
      dayReports.forEach(r => {
        if (r.product_name) productSet.add(r.product_name);
      });

      const opSet = new Set<string>();
      dayReports.forEach(r => {
        if (r.op2_name) opSet.add(r.op2_name);
        if (r.op1_name) opSet.add(r.op1_name);
        if (r.op3_name) opSet.add(r.op3_name);
      });

      const effReports = dayReports.filter(r => this.getEfficiency(r) != null);
      const avgEfficiency = effReports.length > 0
        ? Math.round(effReports.reduce((s, r) => s + (this.getEfficiency(r) ?? 0), 0) / effReports.length)
        : null;

      return {
        date,
        reports: dayReports,
        totalIbc,
        totalDrift,
        products: Array.from(productSet),
        operators: Array.from(opSet),
        avgEfficiency
      };
    });

    // Sort by date descending
    days.sort((a, b) => b.date.localeCompare(a.date));
    return days;
  }

  toggleDay(date: string) {
    this.expandedDays[date] = !this.expandedDays[date];
    // Load lopnummer and comments for all reports in the day
    if (this.expandedDays[date]) {
      const day = this.groupedDays.find(d => d.date === date);
      if (day) {
        day.reports.forEach(report => {
          if (report.skiftraknare && this.lopnummerMap[report.id] === undefined) {
            this.loadLopnummer(report);
          }
          if (this.kommentarMap[report.id] === undefined) {
            this.laddaKommentar(report);
          }
        });
      }
    }
  }

  isDayExpanded(date: string): boolean {
    return !!this.expandedDays[date];
  }

  getShiftTimeRange(report: any): { start: string; stop: string } {
    const datum = report.datum || '';
    const startTime = datum.substring(11, 16) || '–';
    const drifttid = report.drifttid || 0;
    const rasttime = report.rasttime || 0;
    let stopTime = '–';
    if (datum.length >= 16 && (drifttid > 0 || rasttime > 0)) {
      const totalMin = drifttid + rasttime;
      const startDate = new Date(datum);
      if (!isNaN(startDate.getTime())) {
        startDate.setMinutes(startDate.getMinutes() + totalMin);
        stopTime = String(startDate.getHours()).padStart(2, '0') + ':' + String(startDate.getMinutes()).padStart(2, '0');
      }
    }
    // Use skiftTiderMap if available
    const tider = this.skiftTiderMap[report.id];
    if (tider?.start) {
      const s = tider.start;
      return {
        start: s.substring(11, 16) || startTime,
        stop: tider.slut ? tider.slut.substring(11, 16) : stopTime
      };
    }
    return { start: startTime, stop: stopTime };
  }

  trackByDate(index: number, day: any): string {
    return day.date;
  }

  sortBy(field: SortField) {
    if (this.sortField === field) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortDir   = field === 'datum' ? 'desc' : 'asc';
    }
  }

  sortIcon(field: SortField): string {
    if (this.sortField !== field) return 'fa-sort';
    return this.sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
  }

  onSearchInput() {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      this._debouncedSearchText = this.searchText;
    }, 350);
  }

  clearFilter() {
    this.filterFrom         = '';
    this.filterTo           = '';
    this.filterSkift        = '';
    this.searchText         = '';
    this._debouncedSearchText = '';
    this.selectedOperatorId = null;
  }

  // ========== Summary KPIs (filtered set) ==========
  get summaryTotalIbc(): number {
    return this.filteredReports.reduce((s, r) => s + (r.ibc_ok || 0), 0);
  }

  get summaryAvgQuality(): number | null {
    const reports = this.filteredReports.filter(r => r.totalt > 0);
    if (!reports.length) return null;
    const totalOk  = reports.reduce((s, r) => s + (r.ibc_ok || 0), 0);
    const totalAll = reports.reduce((s, r) => s + (r.totalt || 0), 0);
    return totalAll > 0 ? Math.round((totalOk / totalAll) * 100) : null;
  }

  get summaryAvgOee(): number | null {
    const reports = this.filteredReports.filter(r => (r.drifttid || 0) + (r.rasttime || 0) > 0 && r.totalt > 0);
    if (!reports.length) return null;
    // OEE simplified: (quality * efficiency) average over reports with PLC data
    const oeeSum = reports.reduce((s, r) => {
      const q = r.totalt > 0 ? (r.ibc_ok / r.totalt) : 0;
      const planned = (r.drifttid || 0) + (r.rasttime || 0);
      const avail   = planned > 0 ? Math.min((r.drifttid || 0) / planned, 1) : 0;
      return s + q * avail * 100;
    }, 0);
    return Math.round(oeeSum / reports.length);
  }

  get summaryAvgIbcH(): number | null {
    const reports = this.filteredReports.filter(r => this.getIbcPerHour(r) != null);
    if (!reports.length) return null;
    return Math.round(reports.reduce((s, r) => s + (this.getIbcPerHour(r) ?? 0), 0) / reports.length * 10) / 10;
  }

  get summaryAvgEfficiency(): number | null {
    const reports = this.filteredReports.filter(r => this.getEfficiency(r) != null);
    if (!reports.length) return null;
    return Math.round(reports.reduce((s, r) => s + (this.getEfficiency(r) ?? 0), 0) / reports.length);
  }

  get summaryTotalRast(): number {
    return this.filteredReports.reduce((s, r) => s + (r.rasttime || 0), 0);
  }

  get summaryTotalDrift(): number {
    return this.filteredReports.reduce((s, r) => s + (r.drifttid || 0), 0);
  }

  get summaryDeltaVsPrev(): number | null {
    const all = this.filteredReports;
    if (all.length < 2) return null;
    const current  = all[0]?.totalt;
    const previous = all[1]?.totalt;
    if (current == null || previous == null) return null;
    return current - previous;
  }

  get summaryBonusEstimate(): number | null {
    if (!this.filteredReports.length) return null;
    const latestReport = this.filteredReports[0];
    return this.getBonusEstimate(latestReport);
  }

  // ========== Computed KPIs per row ==========
  getQualityPct(r: any): number | null {
    if (!r.totalt) return null;
    return Math.round((r.ibc_ok / r.totalt) * 100);
  }

  getEfficiencyPct(r: any): number | null {
    const total = (r.drifttid || 0) + (r.rasttime || 0);
    if (!total) return null;
    return Math.round((r.drifttid / total) * 100);
  }

  getIbcPerHour(r: any): number | null {
    if (!r.drifttid) return null;
    return Math.round((r.ibc_ok / (r.drifttid / 60)) * 10) / 10;
  }

  /** Effektivitet = actual IBC/h vs target IBC/h (baserat på produktens cykeltid) */
  getEfficiency(r: any): number | null {
    const ibcH = this.getIbcPerHour(r);
    if (ibcH == null) return null;
    // Hitta produktens cykeltid, default 3 min
    const product = this.products?.find((p: any) => p.id === r.product_id);
    const targetCycleMin = product?.cycle_time_minutes || 3;
    const targetIbcH = 60 / targetCycleMin;
    return Math.round((ibcH / targetIbcH) * 100);
  }

  getDefectPct(r: any): number | null {
    if (!r.totalt) return null;
    return Math.round(((r.bur_ej_ok + r.ibc_ej_ok) / r.totalt) * 100);
  }

  getAvgCycleTime(r: any): number | null {
    if (!r.drifttid || !r.ibc_ok) return null;
    return Math.round((r.drifttid / r.ibc_ok) * 10) / 10;
  }

  getStopCount(r: any): number {
    // PLC data not available per row, return 0 unless we have it
    return 0;
  }

  getBonusEstimate(r: any): number | null {
    if (!r || !r.totalt) return null;
    const target = this.settings?.rebotlingTarget || 1000;
    const quality = this.getQualityPct(r);
    if (quality == null) return null;
    // Simple bonus estimate: (totalt / target) * quality% * 100kr
    const pct = Math.min((r.totalt / target) * 100, 100);
    if (pct < 80) return 0;
    return Math.round((pct / 100) * (quality / 100) * 500);
  }

  getDeltaIbc(index: number): number | null {
    const reports = this.filteredReports;
    if (index >= reports.length - 1) return null;
    const current  = reports[index]?.totalt;
    const previous = reports[index + 1]?.totalt;
    if (current == null || previous == null) return null;
    return current - previous;
  }

  getOpLabel(r: any, field: 'op1' | 'op2' | 'op3'): string {
    const nameField = field + '_name';
    return r[nameField] || (r[field] ? String(r[field]) : '–');
  }

  // ========== Fetch ==========
  loadOperators() {
    this.operatorsLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=skiftrapport&run=operator-list`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.operatorsLoading = false;
        if (res?.success) this.operators = res.data ?? [];
      });
  }

  onOperatorFilterChange() {
    // Filtrering sker i filteredReports getter — ingen explicit fetch behövs
  }

  get filteredStats(): { total_skift: number; total_ibc: number; snitt_per_skift: number; avg_ibc_h: number; avg_kvalitet: number } | null {
    if (this.selectedOperatorId === null || !this.filteredReports?.length) return null;
    const reports = this.filteredReports;
    const avg = (arr: number[]) => arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0;
    const totalIbc = reports.reduce((sum: number, r: any) => sum + (r.ibc_ok ?? 0), 0);
    return {
      total_skift:    reports.length,
      total_ibc:      totalIbc,
      snitt_per_skift: reports.length > 0 ? Math.round((totalIbc / reports.length) * 10) / 10 : 0,
      avg_ibc_h:      Math.round(avg(reports.map((r: any) => this.getIbcPerHour(r) ?? 0)) * 10) / 10,
      avg_kvalitet:   Math.round(avg(reports.map((r: any) => this.getQualityPct(r) ?? 0)) * 10) / 10
    };
  }

  getSelectedOperatorName(): string {
    const op = this.operators.find(o => o.id === this.selectedOperatorId);
    return op ? op.name : '';
  }

  fetchProducts() {
    this.skiftrapportService.getProducts().pipe(timeout(8000), catchError(err => { console.error('Fel vid hämtning av produkter:', err); return of(null); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res?.success) {
          this.products = res.data || [];
        }
      }
    });
  }

  fetchReports(silent: boolean = false) {
    if (!silent) {
      this.loading = true;
    }
    this.errorMessage = '';

    const tableContainer = document.querySelector('.table-responsive');
    const scrollTop = tableContainer ? tableContainer.scrollTop : 0;

    this.fetchSub?.unsubscribe();
    this.fetchSub = this.skiftrapportService.getSkiftrapporter()
      .pipe(timeout(8000), catchError(err => { console.error('Fel vid hämtning av skiftrapporter:', err); return of({ success: false, message: 'Kunde inte hämta skiftrapporter', data: [] }); }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (!silent) {
            this.loading = false;
          }
          if (res.success) {
            const newReports = res.data || [];

            if (silent) {
              const expandedCopy    = { ...this.expanded };
              const selectedIdsCopy = new Set(this.selectedIds);
              this.reports    = newReports;
              this.clearOperatorRankingCache();
              this.expanded   = expandedCopy;
              this.selectedIds = new Set(
                Array.from(selectedIdsCopy).filter(id =>
                  newReports.some((r: any) => r.id === id)
                )
              );
              if (tableContainer) {
                clearTimeout(this.scrollRestoreTimer);
                this.scrollRestoreTimer = setTimeout(() => {
                  if (!this.destroy$.closed) tableContainer.scrollTop = scrollTop;
                }, 0);
              }
            } else {
              this.reports = newReports;
              this.clearOperatorRankingCache();
            }
          } else {
            this.errorMessage = res.error || 'Kunde inte hämta skiftrapporter';
          }
        },
        error: (error) => {
          if (!silent) {
            this.loading = false;
          }
          this.errorMessage = error.error?.error || 'Ett fel uppstod vid hämtning av skiftrapporter';
        }
      });
  }

  // ========== Selection ==========
  toggleSelect(id: number) {
    if (this.selectedIds.has(id)) {
      this.selectedIds.delete(id);
    } else {
      this.selectedIds.add(id);
    }
  }

  toggleSelectAll() {
    const visible = this.filteredReports;
    if (this.selectedIds.size === visible.length && visible.length > 0) {
      this.selectedIds.clear();
    } else {
      visible.forEach(r => this.selectedIds.add(r.id));
    }
  }

  isSelected(id: number): boolean {
    return this.selectedIds.has(id);
  }

  isOwner(report: any): boolean {
    return this.user && report.user_id === this.user.id;
  }

  canEdit(report: any): boolean {
    return this.isAdmin || this.isOwner(report);
  }

  // ========== Inlagd ==========
  toggleInlagd(report: any) {
    const newInlagd = !report.inlagd;
    this.skiftrapportService.updateInlagd(report.id, newInlagd).pipe(timeout(8000), catchError(err => { console.error('Fel vid uppdatering av inlagd-status:', err); return of({ success: false, error: 'Ett fel uppstod' }); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          report.inlagd = newInlagd ? 1 : 0;
          this.showSuccess('Status uppdaterad');
        } else {
          this.errorMessage = res.error || 'Kunde inte uppdatera status';
        }
      }
    });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (this.selectedIds.size === 0) {
      this.errorMessage = 'Inga rader valda';
      return;
    }

    const ids = Array.from(this.selectedIds);
    this.skiftrapportService.bulkUpdateInlagd(ids, inlagd).pipe(timeout(8000), catchError(err => { console.error('Fel vid massuppdatering av inlagd-status:', err); return of({ success: false, error: 'Ett fel uppstod' }); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports.forEach(r => {
            if (this.selectedIds.has(r.id)) {
              r.inlagd = inlagd ? 1 : 0;
            }
          });
          this.selectedIds.clear();
          this.showSuccess(res.message);
        } else {
          this.errorMessage = res.error || 'Kunde inte uppdatera status';
        }
      }
    });
  }

  // ========== CRUD ==========
  deleteReport(id: number) {
    if (!confirm('Är du säker på att du vill ta bort denna skiftrapport?')) {
      return;
    }

    this.skiftrapportService.deleteSkiftrapport(id).pipe(timeout(8000), catchError(err => { console.error('Fel vid borttagning av skiftrapport:', err); return of({ success: false, error: 'Ett fel uppstod' }); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports = this.reports.filter(r => r.id !== id);
          this.selectedIds.delete(id);
          this.showSuccess('Skiftrapport borttagen');
        } else {
          this.errorMessage = res.error || 'Kunde inte ta bort skiftrapport';
        }
      }
    });
  }

  bulkDelete() {
    if (this.selectedIds.size === 0) {
      this.errorMessage = 'Inga rader valda';
      return;
    }

    if (!confirm(`Är du säker på att du vill ta bort ${this.selectedIds.size} skiftrapport(er)?`)) {
      return;
    }

    const ids = Array.from(this.selectedIds);
    this.skiftrapportService.bulkDelete(ids).pipe(timeout(8000), catchError(err => { console.error('Fel vid massborttagning:', err); return of({ success: false, error: 'Ett fel uppstod' }); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports = this.reports.filter(r => !this.selectedIds.has(r.id));
          this.selectedIds.clear();
          this.showSuccess(res.message);
        } else {
          this.errorMessage = res.error || 'Kunde inte ta bort skiftrapporter';
        }
      }
    });
  }

  addReport() {
    this.errorMessage = '';

    if (!this.newReport.datum) {
      this.errorMessage = 'Datum är obligatoriskt';
      return;
    }

    if (!this.newReport.product_id) {
      this.errorMessage = 'Produkt måste väljas';
      return;
    }

    if (this.addingReport) return;

    const totalt = this.newReport.ibc_ok + this.newReport.bur_ej_ok + this.newReport.ibc_ej_ok;

    this.addingReport = true;
    this.loading = true;
    this.skiftrapportService.createSkiftrapport({
      datum:       this.newReport.datum,
      product_id:  this.newReport.product_id,
      ibc_ok:      this.newReport.ibc_ok,
      bur_ej_ok:   this.newReport.bur_ej_ok,
      ibc_ej_ok:   this.newReport.ibc_ej_ok,
      totalt:      totalt
    }).pipe(timeout(8000), catchError(err => { console.error('Fel vid skapande av skiftrapport:', err); this.loading = false; this.addingReport = false; return of({ success: false, error: 'Ett fel uppstod vid skapande av skiftrapport' }); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        this.loading = false;
        this.addingReport = false;
        if (res.success) {
          this.fetchReports();
          this.newReport = {
            datum:      localToday(),
            product_id: null,
            ibc_ok:     0,
            bur_ej_ok:  0,
            ibc_ej_ok:  0
          };
          this.showAddReportForm = false;
          this.showSuccess('Skiftrapport tillagd');
        } else {
          this.errorMessage = res.error || 'Kunde inte lägga till skiftrapport';
        }
      }
    });
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
    if (this.expanded[id]) {
      const report = this.reports.find(r => r.id === id);
      if (report && report.skiftraknare && this.lopnummerMap[id] === undefined) {
        this.loadLopnummer(report);
      }
      if (report && this.kommentarMap[id] === undefined) {
        this.laddaKommentar(report);
      }
    }
  }

  private loadLopnummer(report: any) {
    const id = report.id;
    this.lopnummerLoading[id] = true;
    this.skiftrapportService.getLopnummer(report.skiftraknare, report.datum || report.start_datum)
      .pipe(timeout(8000), catchError(err => { console.error('Fel vid laddning av löpnummer:', err); return of(null); }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.lopnummerLoading[id] = false;
          this.lopnummerMap[id] = res?.success ? res.ranges : '–';
          if (res?.success) {
            this.skiftTiderMap[id] = {
              start: res.skift_start || null,
              slut: res.skift_slut || null,
              cykel_datum: res.cykel_datum || null,
              fallback: res.fallback_skiftraknare || undefined
            };
          }
        }
      });
  }

  saveReport(report: any) {
    let datum = report.datum;
    if (datum instanceof Date) {
      datum = localDateStr(datum);
    } else if (typeof datum === 'string') {
      datum = datum.split(' ')[0];
    }

    this.skiftrapportService.updateSkiftrapport(report.id, {
      datum:      datum,
      product_id: report.product_id,
      ibc_ok:     parseInt(report.ibc_ok,     10) || 0,
      bur_ej_ok:  parseInt(report.bur_ej_ok,  10) || 0,
      ibc_ej_ok:  parseInt(report.ibc_ej_ok,  10) || 0
    }).pipe(timeout(8000), catchError(err => { console.error('Fel vid uppdatering av skiftrapport:', err); return of({ success: false, error: 'Ett fel uppstod vid uppdatering' }); }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          report.totalt = (parseInt(report.ibc_ok, 10) || 0)
                        + (parseInt(report.bur_ej_ok, 10) || 0)
                        + (parseInt(report.ibc_ej_ok, 10) || 0);
          report.datum = datum;
          this.expanded[report.id] = false;
          this.fetchReports();
          this.showSuccess('Skiftrapport uppdaterad');
        } else {
          this.errorMessage = res.error || 'Kunde inte uppdatera skiftrapport';
        }
      }
    });
  }

  // ========== Kommentarer ==========
  getShiftNr(r: any): number {
    const timeStr = (r.datum || '').substring(11, 16);
    if (!timeStr) return 1;
    const [hh] = timeStr.split(':').map(Number);
    const minutes = hh * 60 + (parseInt(timeStr.split(':')[1] || '0', 10));
    if (minutes >= 6 * 60 && minutes < 14 * 60)  return 1; // förmiddag
    if (minutes >= 14 * 60 && minutes < 22 * 60) return 2; // eftermiddag
    return 3; // natt
  }

  laddaKommentar(report: any) {
    const id = report.id;
    const datum = (report.datum || '').substring(0, 10);
    const skiftNr = this.getShiftNr(report);
    this.kommentarLoading[id] = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=skift-kommentar&datum=${datum}&skift_nr=${skiftNr}`,
      { withCredentials: true }
    )
    .pipe(timeout(8000), catchError(err => { console.error('Fel vid laddning av kommentar:', err); return of(null); }), takeUntil(this.destroy$))
    .subscribe({
      next: (res) => {
        this.kommentarLoading[id] = false;
        if (res?.success && res.data) {
          this.kommentarMap[id] = res.data.kommentar || '';
          this.editKommentar[id] = res.data.kommentar || '';
        } else {
          this.kommentarMap[id] = '';
          this.editKommentar[id] = '';
        }
      },
      error: () => {
        this.kommentarLoading[id] = false;
        this.kommentarMap[id] = '';
        this.editKommentar[id] = '';
      }
    });
  }

  sparaKommentar(report: any) {
    const id = report.id;
    const datum = (report.datum || '').substring(0, 10);
    const skiftNr = this.getShiftNr(report);
    const text = (this.editKommentar[id] || '').substring(0, 500);
    this.spararKommentar[id] = true;
    this.http.post<any>(
      `${environment.apiUrl}?action=rebotling&run=set-skift-kommentar`,
      { datum, skift_nr: skiftNr, kommentar: text },
      { withCredentials: true }
    )
    .pipe(timeout(8000), catchError(err => { console.error('Fel vid sparande av kommentar:', err); this.spararKommentar[id] = false; this.errorMessage = 'Serverfel vid sparande av kommentar'; return of(null); }), takeUntil(this.destroy$))
    .subscribe({
      next: (res) => {
        if (!res) return;
        this.spararKommentar[id] = false;
        if (res.success) {
          this.kommentarMap[id] = text;
          this.redigerarKommentar[id] = false;
          this.showSuccess('Kommentar sparad');
        } else {
          this.errorMessage = res.error || 'Kunde inte spara kommentar';
        }
      }
    });
  }

  // ========== Export ==========
  exportCSV() {
    if (this.filteredReports.length === 0) return;

    const header = ['ID', 'Datum', 'Produkt', 'Användare', 'IBC OK', 'Bur ej OK', 'IBC ej OK', 'Totalt', 'Kvalitet%', 'IBC/h', 'Drifttid(min)', 'Rasttid(min)', 'Inlagd'];
    const rows = this.filteredReports.map((r: any) => [
      r.id,
      r.datum,
      r.product_name || '-',
      r.user_name || '-',
      r.ibc_ok,
      r.bur_ej_ok,
      r.ibc_ej_ok,
      r.totalt,
      this.getQualityPct(r) ?? '',
      this.getIbcPerHour(r) ?? '',
      r.drifttid ?? '',
      r.rasttime ?? '',
      r.inlagd == 1 ? 'Ja' : 'Nej'
    ]);

    const csvContent = [header, ...rows]
      .map(row => row.map(cell => `"${cell}"`).join(';'))
      .join('\n');

    const BOM  = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href     = url;
    link.download = `skiftrapport-${localToday()}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (this.filteredReports.length === 0) return;
    import('xlsx').then(XLSX => {
      // ---- Sammanfattningsblad ----
      const summaryHeaders = [['Sammanfattning', 'Värde']];
      const summaryRows = [
        ['Total IBC',        this.summaryTotalIbc],
        ['Kvalitet %',       this.summaryAvgQuality ?? ''],
        ['OEE %',            this.summaryAvgOee ?? ''],
        ['Drifttid (min)',   this.summaryTotalDrift],
        ['Rasttid (min)',    this.summaryTotalRast],
      ];
      const wsSummary = XLSX.utils.aoa_to_sheet([...summaryHeaders, ...summaryRows]);
      wsSummary['!cols'] = [{ wch: 20 }, { wch: 14 }];
      // Frys header-rad i sammanfattning
      wsSummary['!freeze'] = { xSplit: 0, ySplit: 1 };

      // ---- Skiftrapporter-blad ----
      const dataHeaders = [
        'ID', 'Datum', 'Produkt', 'Användare',
        'IBC OK', 'Bur ej OK', 'IBC ej OK', 'Totalt',
        'Kvalitet %', 'Effektivitet %', 'IBC/timme', 'Kassation %',
        'Snitt cykeltid', 'Bonus-estimat',
        'Op1', 'Op2', 'Op3',
        'Drifttid (min)', 'Rasttid (min)', 'Löpnummer', 'Skifträknare', 'Inlagd'
      ];
      const dataRows = this.filteredReports.map(r => [
        r.id,
        r.datum,
        r.product_name || '-',
        r.user_name || '-',
        r.ibc_ok,
        r.bur_ej_ok,
        r.ibc_ej_ok,
        r.totalt,
        this.getQualityPct(r) ?? '',
        this.getEfficiencyPct(r) ?? '',
        this.getIbcPerHour(r) ?? '',
        this.getDefectPct(r) ?? '',
        this.getAvgCycleTime(r) ?? '',
        this.getBonusEstimate(r) ?? '',
        this.getOpLabel(r, 'op1'),
        this.getOpLabel(r, 'op2'),
        this.getOpLabel(r, 'op3'),
        r.drifttid ?? '',
        r.rasttime ?? '',
        r.lopnummer ?? '',
        r.skiftraknare ?? '',
        r.inlagd == 1 ? 'Ja' : 'Nej'
      ]);
      const wsData = XLSX.utils.aoa_to_sheet([dataHeaders, ...dataRows]);

      // Kolumnbredder
      wsData['!cols'] = [
        { wch: 6  },  // ID
        { wch: 12 },  // Datum
        { wch: 18 },  // Produkt
        { wch: 16 },  // Användare
        { wch: 8  },  // IBC OK
        { wch: 10 },  // Bur ej OK
        { wch: 10 },  // IBC ej OK
        { wch: 8  },  // Totalt
        { wch: 11 },  // Kvalitet %
        { wch: 14 },  // Effektivitet %
        { wch: 11 },  // IBC/timme
        { wch: 12 },  // Kassation %
        { wch: 14 },  // Snitt cykeltid
        { wch: 13 },  // Bonus-estimat
        { wch: 14 },  // Op1
        { wch: 14 },  // Op2
        { wch: 14 },  // Op3
        { wch: 14 },  // Drifttid
        { wch: 13 },  // Rasttid
        { wch: 12 },  // Löpnummer
        { wch: 13 },  // Skifträknare
        { wch: 8  },  // Inlagd
      ];

      // Frys header-rad (rad 1)
      wsData['!freeze'] = { xSplit: 0, ySplit: 1 };

      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, wsSummary, 'Sammanfattning');
      XLSX.utils.book_append_sheet(wb, wsData,    'Skiftrapporter');
      XLSX.writeFile(wb, `skiftrapporter-rebotling-${localToday()}.xlsx`);
    });
  }

  exportPDF(report: any) {
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake  = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs    = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const docDef   = this.buildPDFDocDef(report);
        pdfMake.createPdf(docDef).download(`skiftrapport-${report.datum}-${report.id}.pdf`);
      });
    });
  }

  exportHandoverPDF() {
    const reports = this.filteredReports;
    if (reports.length === 0) return;

    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake  = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs    = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const docDef   = this.buildHandoverPDFDocDef(reports);
        const dateStr  = localToday();
        pdfMake.createPdf(docDef).download(`skiftoverlamnning-rebotling-${dateStr}.pdf`);
      });
    });
  }

  private buildHandoverPDFDocDef(reports: any[]): any {
    const now      = new Date().toLocaleString('sv-SE');
    const target   = this.settings?.rebotlingTarget || 1000;
    const shiftsPerDay = 3;
    const shiftTarget  = Math.round(target / shiftsPerDay);

    // Period-rubrik baserat på filter
    let periodLabel = 'Alla skift';
    if (this.filterFrom && this.filterTo) {
      periodLabel = `${this.filterFrom} – ${this.filterTo}`;
    } else if (this.filterFrom) {
      periodLabel = `Från ${this.filterFrom}`;
    } else if (this.filterTo) {
      periodLabel = `Till ${this.filterTo}`;
    }
    if (this.filterSkift) {
      const skiftMap: { [k: string]: string } = {
        förmiddag: 'Förmiddag (06–14)',
        eftermiddag: 'Eftermiddag (14–22)',
        natt: 'Natt (22–06)'
      };
      periodLabel += ` | ${skiftMap[this.filterSkift] || this.filterSkift}`;
    }

    // Senaste skiftet för header-info
    const latestReport = reports[0];
    const latestDatum  = latestReport ? (latestReport.datum || '').substring(0, 10) : '';
    const latestSkift  = latestReport ? this.getShiftForReport(latestReport) : '';
    const skiftNamn: { [k: string]: string } = {
      förmiddag: 'Förmiddag', eftermiddag: 'Eftermiddag', natt: 'Natt'
    };

    // KPI-aggregat
    const totalIbc   = this.summaryTotalIbc;
    const avgQual    = this.summaryAvgQuality;
    const avgOee     = this.summaryAvgOee;
    const totalDrift = this.summaryTotalDrift;
    const totalRast  = this.summaryTotalRast;

    // Operatörer: samla unika + IBC/h per operatör från alla filtrerade skift
    const opMap: { [name: string]: { ibc_per_h_vals: number[]; ibc_ok_total: number; shift_count: number } } = {};
    reports.forEach(r => {
      ['op1', 'op2', 'op3'].forEach(opKey => {
        const name = this.getOpLabel(r, opKey as 'op1' | 'op2' | 'op3');
        if (name === '–') return;
        if (!opMap[name]) opMap[name] = { ibc_per_h_vals: [], ibc_ok_total: 0, shift_count: 0 };
        const ibc_h = this.getIbcPerHour(r);
        if (ibc_h != null) opMap[name].ibc_per_h_vals.push(ibc_h);
        opMap[name].ibc_ok_total += r.ibc_ok || 0;
        opMap[name].shift_count++;
      });
    });

    const operatorRows: any[][] = Object.entries(opMap)
      .sort((a, b) => {
        const avgA = a[1].ibc_per_h_vals.length
          ? a[1].ibc_per_h_vals.reduce((s, v) => s + v, 0) / a[1].ibc_per_h_vals.length : 0;
        const avgB = b[1].ibc_per_h_vals.length
          ? b[1].ibc_per_h_vals.reduce((s, v) => s + v, 0) / b[1].ibc_per_h_vals.length : 0;
        return avgB - avgA;
      })
      .map(([name, data]) => {
        const avgIbcH = data.ibc_per_h_vals.length
          ? Math.round(data.ibc_per_h_vals.reduce((s, v) => s + v, 0) / data.ibc_per_h_vals.length * 10) / 10
          : null;
        return [
          { text: name, bold: true },
          { text: data.shift_count.toString(), alignment: 'center' },
          { text: data.ibc_ok_total.toString(), alignment: 'center' },
          { text: avgIbcH != null ? avgIbcH + ' st/h' : '–', alignment: 'center' }
        ];
      });

    // Kommentarer (från kommentarMap) — hämta alla som är laddade
    const kommentarer: any[] = [];
    reports.forEach(r => {
      const kommentar = this.kommentarMap[r.id];
      if (kommentar && kommentar.trim()) {
        kommentarer.push({
          text: `${(r.datum || '').substring(0, 10)} (${skiftNamn[this.getShiftForReport(r)] || 'Okänt skift'}): ${kommentar}`,
          margin: [0, 2, 0, 2],
          fontSize: 10
        });
      }
    });

    // Skiftlista (senaste 5)
    const recentRows: any[][] = reports.slice(0, 5).map((r, i) => {
      const qualPct = this.getQualityPct(r);
      const ibcH    = this.getIbcPerHour(r);
      return [
        { text: (r.datum || '').substring(0, 10), fontSize: 9 },
        { text: skiftNamn[this.getShiftForReport(r)] || '–', fontSize: 9 },
        { text: r.product_name || '–', fontSize: 9 },
        { text: String(r.totalt ?? '–'), alignment: 'center', bold: i === 0, fontSize: 9 },
        { text: qualPct != null ? qualPct + '%' : '–', alignment: 'center',
          color: qualPct != null && qualPct >= 90 ? 'green' : (qualPct != null && qualPct < 70 ? 'red' : 'black'),
          fontSize: 9 },
        { text: ibcH != null ? ibcH + ' st/h' : '–', alignment: 'center', fontSize: 9 }
      ];
    });

    // Uppfyllnadsprocent
    const achievePct = target > 0 && totalIbc > 0 ? Math.round((totalIbc / target) * 100) : null;
    const achieveColor = achievePct != null
      ? (achievePct >= 100 ? 'green' : (achievePct >= 80 ? 'orange' : 'red'))
      : 'black';

    const driftH = totalDrift > 0 ? `${Math.floor(totalDrift / 60)}h ${Math.round(totalDrift % 60)}min` : '–';

    const content: any[] = [
      // ---- HEADER ----
      {
        columns: [
          {
            stack: [
              { text: 'SKIFTOVERLAMNNING', style: 'mainHeader' },
              { text: 'Rebotling — IBC-tvätteri', style: 'subHeader' },
              { text: `Period: ${periodLabel}`, style: 'meta', margin: [0, 4, 0, 0] }
            ]
          },
          {
            stack: [
              { text: 'NOREKO', style: 'logoText', alignment: 'right' },
              { text: latestDatum || now.substring(0, 10), style: 'meta', alignment: 'right' },
              {
                text: latestSkift ? skiftNamn[latestSkift] || latestSkift : '',
                style: 'meta',
                alignment: 'right'
              }
            ]
          }
        ]
      },
      { canvas: [{ type: 'line', x1: 0, y1: 5, x2: 515, y2: 5, lineWidth: 2, lineColor: '#2b6cb0' }], margin: [0, 8, 0, 12] },

      // ---- KPI-SAMMANFATTNING ----
      { text: 'KPI-SAMMANFATTNING', style: 'sectionHeader' },
      {
        table: {
          widths: ['*', '*', '*', '*', '*'],
          body: [
            [
              { text: 'Total IBC',   bold: true, fillColor: '#e8f4fd', alignment: 'center' },
              { text: 'Kvalitet',    bold: true, fillColor: '#e8f4fd', alignment: 'center' },
              { text: 'OEE',         bold: true, fillColor: '#e8f4fd', alignment: 'center' },
              { text: 'Drifttid',    bold: true, fillColor: '#e8f4fd', alignment: 'center' },
              { text: 'Rasttid',     bold: true, fillColor: '#e8f4fd', alignment: 'center' }
            ],
            [
              { text: String(totalIbc), bold: true, fontSize: 22, alignment: 'center', color: '#1a365d' },
              {
                text: avgQual != null ? avgQual + '%' : '–',
                bold: true, fontSize: 22, alignment: 'center',
                color: avgQual != null ? (avgQual >= 90 ? 'green' : (avgQual >= 70 ? 'orange' : 'red')) : 'gray'
              },
              {
                text: avgOee != null ? avgOee + '%' : '–',
                bold: true, fontSize: 22, alignment: 'center',
                color: avgOee != null ? (avgOee >= 75 ? 'green' : (avgOee >= 50 ? 'orange' : 'red')) : 'gray'
              },
              { text: driftH, bold: true, fontSize: 16, alignment: 'center', color: '#276749' },
              { text: totalRast > 0 ? totalRast + ' min' : '–', bold: true, fontSize: 16, alignment: 'center', color: '#744210' }
            ]
          ]
        },
        layout: {
          hLineWidth: () => 1,
          vLineWidth: () => 1,
          hLineColor: () => '#bee3f8',
          vLineColor: () => '#bee3f8',
          paddingTop: () => 10,
          paddingBottom: () => 10
        },
        margin: [0, 4, 0, 4]
      },

      // Uppfyllnadsprocent vs dagsmål
      {
        columns: [
          {
            text: [
              { text: 'Dagsmål: ', fontSize: 10, color: '#4a5568' },
              { text: String(target) + ' IBC', fontSize: 10, bold: true },
              { text: '   Uppfyllnad: ', fontSize: 10, color: '#4a5568' },
              {
                text: achievePct != null ? achievePct + '%' : '–',
                fontSize: 10, bold: true, color: achieveColor
              },
              { text: '   Nästa skifts mål: ', fontSize: 10, color: '#4a5568' },
              { text: String(shiftTarget) + ' IBC', fontSize: 10, bold: true, color: '#2b6cb0' }
            ],
            margin: [0, 4, 0, 12]
          }
        ]
      },

      // ---- OPERATÖRER ----
      { text: 'OPERATÖRER DETTA SKIFT', style: 'sectionHeader' },
    ];

    if (operatorRows.length > 0) {
      content.push({
        table: {
          widths: ['*', 'auto', 'auto', 'auto'],
          body: [
            [
              { text: 'Operatör',     bold: true, fillColor: '#e8f4fd' },
              { text: 'Antal skift',  bold: true, fillColor: '#e8f4fd', alignment: 'center' },
              { text: 'IBC OK totalt',bold: true, fillColor: '#e8f4fd', alignment: 'center' },
              { text: 'Snitt IBC/h',  bold: true, fillColor: '#e8f4fd', alignment: 'center' }
            ],
            ...operatorRows
          ]
        },
        layout: 'lightHorizontalLines',
        margin: [0, 4, 0, 12]
      } as any);
    } else {
      content.push({ text: 'Ingen operatörsdata tillgänglig.', style: 'meta', margin: [0, 4, 0, 12] });
    }

    // ---- SENASTE SKIFT ----
    content.push({ text: 'SENASTE SKIFT (max 5)', style: 'sectionHeader' });
    if (recentRows.length > 0) {
      content.push({
        table: {
          widths: ['auto', 'auto', '*', 'auto', 'auto', 'auto'],
          body: [
            [
              { text: 'Datum',    bold: true, fillColor: '#e8f4fd', fontSize: 9 },
              { text: 'Skift',    bold: true, fillColor: '#e8f4fd', fontSize: 9 },
              { text: 'Produkt',  bold: true, fillColor: '#e8f4fd', fontSize: 9 },
              { text: 'Totalt',   bold: true, fillColor: '#e8f4fd', alignment: 'center', fontSize: 9 },
              { text: 'Kvalitet', bold: true, fillColor: '#e8f4fd', alignment: 'center', fontSize: 9 },
              { text: 'IBC/h',    bold: true, fillColor: '#e8f4fd', alignment: 'center', fontSize: 9 }
            ],
            ...recentRows
          ]
        },
        layout: 'lightHorizontalLines',
        margin: [0, 4, 0, 12]
      } as any);
    }

    // ---- KOMMENTARER / NOTERINGAR ----
    content.push({ text: 'NOTERINGAR FRAN SKIFTEN', style: 'sectionHeader' });
    if (kommentarer.length > 0) {
      kommentarer.forEach(k => content.push(k));
      content.push({ text: '', margin: [0, 0, 0, 8] });
    } else {
      content.push({ text: 'Inga skiftkommentarer laddade. Expandera skift i gränssnittet för att ladda kommentarer.', style: 'meta', margin: [0, 4, 0, 12] });
    }

    // ---- NASTA SKIFTS MAL ----
    content.push({ text: 'NASTA SKIFTS MAL', style: 'sectionHeader' });
    content.push({
      table: {
        widths: ['*', '*', '*'],
        body: [
          [
            { text: 'Dagsmål (total)',  bold: true, fillColor: '#e8f4fd', alignment: 'center' },
            { text: 'Skift per dag',    bold: true, fillColor: '#e8f4fd', alignment: 'center' },
            { text: 'Mål per skift',    bold: true, fillColor: '#e8f4fd', alignment: 'center' }
          ],
          [
            { text: String(target) + ' IBC', alignment: 'center', bold: true, fontSize: 14 },
            { text: String(shiftsPerDay),     alignment: 'center', fontSize: 14 },
            { text: String(shiftTarget) + ' IBC', alignment: 'center', bold: true, fontSize: 14, color: '#2b6cb0' }
          ]
        ]
      },
      layout: {
        hLineWidth: () => 1,
        vLineWidth: () => 1,
        hLineColor: () => '#bee3f8',
        vLineColor: () => '#bee3f8',
        paddingTop: () => 8,
        paddingBottom: () => 8
      },
      margin: [0, 4, 0, 16]
    });

    // ---- ANTECKNINGSRUTA ----
    content.push({ text: 'ANTECKNINGAR', style: 'sectionHeader' });
    content.push({
      table: {
        widths: ['*'],
        body: [
          [{ text: '\n\n\n\n\n', fontSize: 11 }]
        ]
      },
      layout: {
        hLineWidth: () => 1,
        vLineWidth: () => 1,
        hLineColor: () => '#cbd5e0',
        vLineColor: () => '#cbd5e0'
      },
      margin: [0, 4, 0, 16]
    });

    // ---- FOOTER ----
    content.push({
      canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineWidth: 1, lineColor: '#cbd5e0' }],
      margin: [0, 0, 0, 6]
    });
    content.push({
      columns: [
        { text: 'Genererad: ' + now, fontSize: 9, color: '#8fa3b8' },
        { text: 'Noreko – Rebotling skiftöverlämning', fontSize: 9, color: '#8fa3b8', alignment: 'right' }
      ]
    });

    return {
      content,
      styles: {
        mainHeader: {
          fontSize: 20,
          bold: true,
          color: '#1a365d',
          margin: [0, 0, 0, 2]
        },
        subHeader: {
          fontSize: 11,
          color: '#4a5568',
          margin: [0, 0, 0, 2]
        },
        sectionHeader: {
          fontSize: 11,
          bold: true,
          color: '#2b6cb0',
          margin: [0, 8, 0, 4],
          decoration: 'underline'
        },
        meta: {
          fontSize: 9,
          color: '#8fa3b8'
        },
        logoText: {
          fontSize: 18,
          bold: true,
          color: '#2b6cb0'
        }
      },
      defaultStyle: { fontSize: 10, font: 'Roboto' },
      pageMargins: [40, 50, 40, 50],
      pageSize: 'A4'
    };
  }

  private buildPDFDocDef(r: any): any {
    const qualPct   = this.getQualityPct(r);
    const effPct    = this.getEfficiencyPct(r);
    const ibcH      = this.getIbcPerHour(r);
    const defPct    = this.getDefectPct(r);
    const avgCycle  = this.getAvgCycleTime(r);
    const bonus     = this.getBonusEstimate(r);
    const target    = this.settings?.rebotlingTarget || 1000;
    const achievePct = target > 0 ? Math.round((r.totalt / target) * 100) : null;

    return {
      content: [
        { text: 'Skiftrapport – Rebotling', style: 'header' },
        { text: `${r.datum}  |  ${r.product_name || '-'}  |  Skiftansvarig: ${r.user_name || '-'}`, style: 'subheader' },
        { text: '\n' },
        // --- Sammanfattningskort ---
        { text: 'Sammanfattning', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Total IBC',      bold: true, fillColor: '#eeeeee' },
                { text: 'Dagsmål',        bold: true, fillColor: '#eeeeee' },
                { text: 'Uppfyllnad',     bold: true, fillColor: '#eeeeee' },
                { text: 'Kvalitet',       bold: true, fillColor: '#eeeeee' },
                { text: 'Bonus-estimat',  bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: String(r.totalt),                                        bold: true,  alignment: 'center' },
                { text: String(target),                                          alignment: 'center' },
                { text: achievePct != null ? achievePct + '%' : '–',             alignment: 'center',
                  color: achievePct != null && achievePct >= 100 ? 'green' : (achievePct != null && achievePct >= 80 ? 'orange' : 'red') },
                { text: qualPct != null ? qualPct + '%' : '–',                   alignment: 'center',
                  color: qualPct != null && qualPct >= 90 ? 'green' : 'black' },
                { text: bonus != null ? bonus + ' kr' : '–',                     alignment: 'center', color: 'darkblue' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- Produktion ---
        { text: 'Produktion', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*'],
            body: [
              [
                { text: 'IBC OK',     bold: true, fillColor: '#eeeeee' },
                { text: 'Bur ej OK',  bold: true, fillColor: '#eeeeee' },
                { text: 'IBC ej OK',  bold: true, fillColor: '#eeeeee' },
                { text: 'Totalt',     bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: String(r.ibc_ok),     alignment: 'center' },
                { text: String(r.bur_ej_ok),  alignment: 'center' },
                { text: String(r.ibc_ej_ok),  alignment: 'center' },
                { text: String(r.totalt),     bold: true, alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- Nyckeltal ---
        { text: 'Nyckeltal', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Kvalitet',     bold: true, fillColor: '#eeeeee' },
                { text: 'Effektivitet', bold: true, fillColor: '#eeeeee' },
                { text: 'IBC/timme',    bold: true, fillColor: '#eeeeee' },
                { text: 'Kassation',    bold: true, fillColor: '#eeeeee' },
                { text: 'Snitt cykeltid', bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: qualPct  != null ? qualPct  + '%'    : '–', alignment: 'center', color: qualPct != null && qualPct >= 90 ? 'green' : 'black' },
                { text: effPct   != null ? effPct   + '%'    : '–', alignment: 'center' },
                { text: ibcH     != null ? ibcH     + ' st/h': '–', alignment: 'center' },
                { text: defPct   != null ? defPct   + '%'    : '–', alignment: 'center', color: defPct != null && defPct > 10 ? 'red' : 'black' },
                { text: avgCycle != null ? avgCycle + ' min' : '–', alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- Tider ---
        { text: 'Tider', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*', '*'],
            body: [
              [
                { text: 'Cykeldatum', bold: true, fillColor: '#eeeeee' },
                { text: 'Starttid',   bold: true, fillColor: '#eeeeee' },
                { text: 'Stopptid',   bold: true, fillColor: '#eeeeee' },
                { text: 'Drifttid',   bold: true, fillColor: '#eeeeee' },
                { text: 'Rasttid',    bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: this.skiftTiderMap[r.id]?.cykel_datum || '–', alignment: 'center' },
                { text: this.skiftTiderMap[r.id]?.start ? this.skiftTiderMap[r.id].start!.substring(0, 16).replace('T', ' ') : '–', alignment: 'center' },
                { text: this.skiftTiderMap[r.id]?.slut ? this.skiftTiderMap[r.id].slut!.substring(0, 16).replace('T', ' ') : '–', alignment: 'center' },
                { text: r.drifttid != null ? r.drifttid + ' min' : '–', alignment: 'center' },
                { text: r.rasttime != null ? r.rasttime + ' min' : '–', alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        // --- PLC-data ---
        { text: 'PLC-data', style: 'sectionHeader' },
        {
          table: {
            widths: ['*', '*', '*', '*'],
            body: [
              [
                { text: 'Tvättplats',      bold: true, fillColor: '#eeeeee' },
                { text: 'Kontrollstation',  bold: true, fillColor: '#eeeeee' },
                { text: 'Truckförare',     bold: true, fillColor: '#eeeeee' },
                { text: 'Löpnr (sista)',   bold: true, fillColor: '#eeeeee' }
              ],
              [
                { text: this.getOpLabel(r, 'op1'), alignment: 'center' },
                { text: this.getOpLabel(r, 'op2'), alignment: 'center' },
                { text: this.getOpLabel(r, 'op3'), alignment: 'center' },
                { text: String(r.lopnummer ?? '–'), alignment: 'center' }
              ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: '\n' },
        { text: 'Löpnummer detta skift: ' + (this.lopnummerMap[r.id] ?? '–'), style: 'meta' },
        { text: 'Skiftkommentar: ' + (this.kommentarMap[r.id] || '–'), style: 'meta' },
        { text: 'Skiftansvarig: ' + (r.user_name || '-'), style: 'meta' },
        { text: 'Inlagd i system: ' + (r.inlagd == 1 ? 'Ja' : 'Nej'), style: 'meta' },
        { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
      ],
      styles: {
        header:        { fontSize: 22, bold: true, margin: [0, 0, 0, 4] },
        subheader:     { fontSize: 11, color: '#555555', margin: [0, 0, 0, 10] },
        sectionHeader: { fontSize: 13, bold: true, margin: [0, 10, 0, 4] },
        meta:          { fontSize: 10, color: '#777777', margin: [0, 2, 0, 0] }
      },
      defaultStyle: { fontSize: 11 },
      pageMargins: [40, 50, 40, 50]
    };
  }

  // ========== Operatör-KPI-jämförelse ==========
  loadOpKpiJamforelse() {
    const from = this.filterFrom || new Date(Date.now() - 30 * 86400000).toISOString().substring(0, 10);
    const to   = this.filterTo   || new Date().toISOString().substring(0, 10);
    this.opKpiLoading = true;
    this.opKpiError = '';
    this.skiftrapportService.getOperatorKpiJamforelse(from, to)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.opKpiLoading = false;
        if (res?.success && res.data?.length) {
          this.opKpiData = res.data;
          clearTimeout(this.opKpiBuildTimer);
          this.opKpiBuildTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.buildOpKpiChart();
          }, 100);
        } else if (res && !res.success) {
          this.opKpiError = res.error || 'Kunde inte hamta operatorsjamforelse';
        } else {
          this.opKpiData = [];
        }
      });
  }

  private buildOpKpiChart() {
    try { this.opKpiChart?.destroy(); } catch (e) {}
    this.opKpiChart = null;

    if (!this.opKpiData?.length) return;
    const canvas = document.getElementById('opKpiCanvas') as HTMLCanvasElement | null;
    if (!canvas) return;

    const sorted = [...this.opKpiData].sort((a, b) => b.snitt_ibc_per_timme - a.snitt_ibc_per_timme);
    const labels   = sorted.map(d => d.operator_name);
    const ibcPerH  = sorted.map(d => d.snitt_ibc_per_timme);
    const oeePct   = sorted.map(d => d.snitt_oee_pct);
    const kassPct  = sorted.map(d => d.kassation_pct);

    this.opKpiChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Snitt IBC/h',
            data: ibcPerH,
            backgroundColor: 'rgba(99, 179, 237, 0.7)',
            borderColor: '#63b3ed',
            borderWidth: 1,
            borderRadius: 4,
            yAxisID: 'yLeft',
          },
          {
            label: 'OEE %',
            data: oeePct,
            backgroundColor: 'rgba(104, 211, 145, 0.7)',
            borderColor: '#68d391',
            borderWidth: 1,
            borderRadius: 4,
            yAxisID: 'yRight',
          },
          {
            label: 'Kassation %',
            data: kassPct,
            backgroundColor: 'rgba(252, 129, 129, 0.7)',
            borderColor: '#fc8181',
            borderWidth: 1,
            borderRadius: 4,
            yAxisID: 'yRight',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 12 } } },
          tooltip: {
            intersect: false,
            mode: 'nearest',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && idx < sorted.length) {
                  const d = sorted[idx];
                  return [
                    `Antal skift: ${d.antal_skift}`,
                    `Totalt IBC OK: ${d.totalt_ibc_ok}`,
                    `Total drifttid: ${d.total_drifttid_h}h`,
                  ];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45 },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
          yLeft: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'IBC/h', color: '#63b3ed' },
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            beginAtZero: true,
          },
          yRight: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: '%', color: '#68d391' },
            ticks: { color: '#a0aec0', callback: (val: any) => `${val}%` },
            grid:  { drawOnChartArea: false },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ========== Skiftjämförelse ==========
  compareShifts() {
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(this.compareDateA) || !dateRegex.test(this.compareDateB)) {
      this.compareError = 'Ange giltiga datum (ÅÅÅÅ-MM-DD) för båda fälten';
      return;
    }
    if (this.compareDateA === this.compareDateB) {
      this.compareError = 'Välj två olika datum för att jämföra';
      return;
    }
    this.compareError   = '';
    this.compareResult  = null;
    this.compareLoading = true;
    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=shift-compare&date_a=${this.compareDateA}&date_b=${this.compareDateB}`,
      { withCredentials: true }
    )
    .pipe(timeout(8000), catchError(err => { console.error('Fel vid skiftjämförelse:', err); this.compareLoading = false; this.compareError = 'Serverfel vid jämförelse'; return of(null); }), takeUntil(this.destroy$))
    .subscribe({
      next: (res) => {
        if (!res) return;
        this.compareLoading = false;
        if (res.success) {
          this.compareResult = { a: res.data.a, b: res.data.b };
        } else {
          this.compareError = res.error || 'Kunde inte hämta jämförelsedata';
        }
      }
    });
  }

  clearCompare() {
    this.compareResult  = null;
    this.compareError   = '';
    this.compareDateA   = '';
    this.compareDateB   = '';
  }

  compareDiff(fieldA: number | null, fieldB: number | null): number | null {
    if (fieldA == null || fieldB == null) return null;
    return Math.round((fieldB - fieldA) * 10) / 10;
  }

  compareIsImprovement(field: string, diff: number | null): boolean {
    if (diff == null) return false;
    // För rasttid är lägre bättre
    if (field === 'rasttime') return diff < 0;
    return diff > 0;
  }

  compareIsWorse(field: string, diff: number | null): boolean {
    if (diff == null) return false;
    if (field === 'rasttime') return diff > 0;
    return diff < 0;
  }

  formatMinutes(min: number | null): string {
    if (min == null || min === 0) return '–';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}min` : `${m}min`;
  }

  // ========== Produktionstrendgraf ==========

  /**
   * Öppna/stäng trendpanelen för ett skift.
   * Laddar data och bygger grafen om expanderad.
   */
  toggleTrend(report: any) {
    if (this.selectedTrendReportId === report.id) {
      // Stäng
      this.selectedTrendReportId = null;
      this.trendData = null;
      try { this.trendChart?.destroy(); } catch (e) {}
      this.trendChart = null;
      try { this.efficiencyChart?.destroy(); } catch (e) {}
      this.efficiencyChart = null;
      return;
    }

    this.selectedTrendReportId = report.id;
    this.trendData = null;
    this.trendError = '';
    try { this.trendChart?.destroy(); } catch (e) {}
    this.trendChart = null;
    try { this.efficiencyChart?.destroy(); } catch (e) {}
    this.efficiencyChart = null;

    if (!report.skiftraknare) {
      this.trendError = 'Ingen skifträknare kopplad till rapporten — trenddata kan inte hämtas.';
      return;
    }

    const datum = (report.datum || '').substring(0, 10);
    this.trendLoading = true;

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=shift-trend&datum=${datum}&skift=${report.skiftraknare}`,
      { withCredentials: true }
    )
    .pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    )
    .subscribe(res => {
      this.trendLoading = false;
      if (!res || !res.success) {
        this.trendError = res?.error || 'Kunde inte hämta trenddata';
        return;
      }
      this.trendData = res;
      // Bygg graferna efter att Angular renderat canvas-elementen
      clearTimeout(this.trendBuildTimer);
      this.trendBuildTimer = setTimeout(() => {
        if (!this.destroy$.closed) this.buildTrendChart();
      }, 100);
      clearTimeout(this.effBuildTimer);
      this.effBuildTimer = setTimeout(() => {
        if (!this.destroy$.closed) this.buildEfficiencyChart();
      }, 150);
    });
  }

  private buildTrendChart() {
    const canvasEl = document.getElementById('trendCanvas-' + this.selectedTrendReportId) as HTMLCanvasElement | null;
    if (!canvasEl || !this.trendData) return;

    try { this.trendChart?.destroy(); } catch (e) {}
    this.trendChart = null;

    const trend: any[]      = this.trendData.trend      || [];
    const avgProfile: any[] = this.trendData.avg_profile || [];

    // Bygg en unifierad timme-lista
    const allHours = Array.from(
      new Set([
        ...trend.map((t: any) => t.timme),
        ...avgProfile.map((a: any) => a.timme),
      ])
    ).sort((a, b) => a - b);

    const labels = allHours.map((h: number) => `${String(h).padStart(2, '0')}:00`);

    const trendMap: { [h: number]: number } = {};
    trend.forEach((t: any) => { trendMap[t.timme] = t.takt_ibc_per_h; });

    const avgMap: { [h: number]: number } = {};
    avgProfile.forEach((a: any) => { avgMap[a.timme] = a.snitt_ibc_timma; });

    const trendValues = allHours.map((h: number) => trendMap[h] ?? null);
    const avgValues   = allHours.map((h: number) => avgMap[h]   ?? null);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvasEl, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Faktisk takt (IBC/h)',
            data: trendValues,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.12)',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#63b3ed',
            tension: 0.3,
            fill: true,
            spanGaps: true,
          },
          {
            label: 'Genomsnittsprofil (IBC/h)',
            data: avgValues,
            borderColor: '#a0aec0',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 3,
            pointBackgroundColor: '#a0aec0',
            tension: 0.3,
            fill: false,
            spanGaps: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              afterBody: (items: any[]) => {
                const actual = items.find((i: any) => i.datasetIndex === 0)?.parsed?.y ?? null;
                const avg    = items.find((i: any) => i.datasetIndex === 1)?.parsed?.y ?? null;
                if (actual != null && avg != null && avg > 0) {
                  const diff = Math.round(((actual - avg) / avg) * 100);
                  return [`Diff vs snitt: ${diff > 0 ? '+' : ''}${diff}%`];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Timme', color: '#8fa3b8' },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'IBC / timme', color: '#8fa3b8' },
            beginAtZero: true,
          },
        },
      },
    });
  }

  // ========== Effektivitetsgraf ==========

  private buildEfficiencyChart() {
    const canvasEl = document.getElementById('effCanvas-' + this.selectedTrendReportId) as HTMLCanvasElement | null;
    if (!canvasEl || !this.trendData?.efficiency_timeline?.length) return;

    try { this.efficiencyChart?.destroy(); } catch (e) {}
    this.efficiencyChart = null;

    const timeline: any[] = this.trendData.efficiency_timeline;
    const eff = this.trendData.efficiency;

    const labels = timeline.map((t: any) => t.tid);
    const ibcPerHValues = timeline.map((t: any) => t.ibc_per_h);

    // Genomsnittslinje
    const avgIbcPerH = eff?.ibc_per_h ?? 0;

    // Bestäm barfärger
    const bgColors = timeline.map((t: any) => {
      if (t.is_rast) return 'rgba(236, 201, 75, 0.7)';
      if (t.is_stopp) return 'rgba(252, 129, 129, 0.7)';
      return 'rgba(72, 187, 120, 0.7)';
    });
    const borderColors = timeline.map((t: any) => {
      if (t.is_rast) return '#ecc94b';
      if (t.is_stopp) return '#fc8181';
      return '#48bb78';
    });

    this.efficiencyChart = new Chart(canvasEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBCer/h (30 min)',
            data: ibcPerHValues,
            backgroundColor: bgColors,
            borderColor: borderColors,
            borderWidth: 1,
            borderRadius: 3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            backgroundColor: '#1a202c',
            titleColor: '#e2e8f0',
            bodyColor: '#e2e8f0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              afterBody: (items: any[]) => {
                const idx = items[0]?.dataIndex ?? -1;
                if (idx >= 0 && idx < timeline.length) {
                  const t = timeline[idx];
                  const lines = [`Körtid: ${t.running_min} min`, `IBCer: ${t.ibc_count}`];
                  if (t.is_rast) lines.push('(Rastperiod)');
                  if (t.is_stopp) lines.push('(Stoppperiod)');
                  return lines;
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: '30-min intervall', color: '#8fa3b8' },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'IBCer / timme', color: '#8fa3b8' },
            beginAtZero: true,
          },
        },
      },
      plugins: [{
        id: 'avgLine',
        afterDraw: (chart: any) => {
          if (avgIbcPerH <= 0) return;
          const ctx = chart.ctx;
          const yScale = chart.scales['y'];
          const xScale = chart.scales['x'];
          const yPixel = yScale.getPixelForValue(avgIbcPerH);
          ctx.save();
          ctx.setLineDash([6, 4]);
          ctx.strokeStyle = '#e2e8f0';
          ctx.lineWidth = 1.5;
          ctx.beginPath();
          ctx.moveTo(xScale.left, yPixel);
          ctx.lineTo(xScale.right, yPixel);
          ctx.stroke();
          ctx.fillStyle = '#e2e8f0';
          ctx.font = '11px sans-serif';
          ctx.textAlign = 'right';
          ctx.fillText(`Snitt: ${avgIbcPerH} IBC/h`, xScale.right - 5, yPixel - 5);
          ctx.restore();
        },
      }],
    });
  }

  /** Format minuter till HH:MM */
  formatMinToHHMM(min: number | null): string {
    if (min == null || min <= 0) return '0:00';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return `${h}:${String(m).padStart(2, '0')}`;
  }

  /** Effektivitetsfärg baserad på mål */
  getEfficiencyColor(ibcPerH: number, targetPerH: number): string {
    if (targetPerH <= 0) return '#e2e8f0';
    const ratio = ibcPerH / targetPerH;
    if (ratio >= 0.8) return '#48bb78';
    if (ratio >= 0.6) return '#ecc94b';
    return '#fc8181';
  }

  // ========== Operatörsrankning per skift ==========

  /**
   * Bygg en rankad lista av operatörer i ett skift baserat på IBC/h.
   * Beräknas från befintliga data — op1/op2/op3 mappas mot IBC/h för hela skiftet.
   * Då vi bara har ett IBC/h per skiftrapport delar vi det lika mellan aktiva operatörer.
   */
  getOperatorRanking(report: any): Array<{ name: string; ibc_per_h: number | null; personal_avg: number | null; rank: number }> {
    const ibcH = this.getIbcPerHour(report);
    const ops: string[] = [];
    if (this.getOpLabel(report, 'op1') !== '–') ops.push(this.getOpLabel(report, 'op1'));
    if (this.getOpLabel(report, 'op2') !== '–') ops.push(this.getOpLabel(report, 'op2'));
    if (this.getOpLabel(report, 'op3') !== '–') ops.push(this.getOpLabel(report, 'op3'));
    if (ops.length === 0) return [];

    // Beräkna genomsnittlig IBC/h per operatör från alla skift de arbetat
    const result = ops.map(name => {
      // Hitta alla rapporter denna operatör deltog i
      const opReports = this.reports.filter(r =>
        this.getOpLabel(r, 'op1') === name ||
        this.getOpLabel(r, 'op2') === name ||
        this.getOpLabel(r, 'op3') === name
      );
      const validReports = opReports.filter(r => this.getIbcPerHour(r) != null);
      const personalAvg  = validReports.length > 0
        ? Math.round(validReports.reduce((s: number, r: any) => s + (this.getIbcPerHour(r) ?? 0), 0) / validReports.length * 10) / 10
        : null;
      return { name, ibc_per_h: ibcH, personal_avg: personalAvg, rank: 0 };
    });

    // Ranka efter personligt genomsnitt (högst = rank 1)
    const sorted = [...result].sort((a, b) => (b.personal_avg ?? 0) - (a.personal_avg ?? 0));
    sorted.forEach((op, i) => { op.rank = i + 1; });

    return sorted;
  }

  getRankIcon(rank: number): string {
    if (rank === 1) return '1';
    if (rank === 2) return '2';
    if (rank === 3) return '3';
    return String(rank);
  }

  getRankBadgeClass(rank: number): string {
    if (rank === 1) return 'rank-gold';
    if (rank === 2) return 'rank-silver';
    if (rank === 3) return 'rank-bronze';
    return 'rank-default';
  }

  getOperatorShiftCount(name: string): number {
    return this.reports.filter(r =>
      this.getOpLabel(r, 'op1') === name ||
      this.getOpLabel(r, 'op2') === name ||
      this.getOpLabel(r, 'op3') === name
    ).length;
  }

  // ========== Skift-navigation ==========

  selectSkift(reportId: number) {
    // Öppna trendpanelen för ett skift via ID
    const report = this.filteredReports.find(r => r.id === reportId);
    if (!report) return;
    this.selectedSkift = reportId;
    this.selectedSkiftIndex = this.filteredReports.findIndex(r => r.id === reportId);
  }

  prevSkift() {
    const reports = this.filteredReports;
    if (this.selectedTrendReportId == null) return;
    const currentIndex = reports.findIndex(r => r.id === this.selectedTrendReportId);
    if (currentIndex > 0) {
      this.toggleTrend(reports[currentIndex - 1]);
    }
  }

  nextSkift() {
    const reports = this.filteredReports;
    if (this.selectedTrendReportId == null) return;
    const currentIndex = reports.findIndex(r => r.id === this.selectedTrendReportId);
    if (currentIndex < reports.length - 1) {
      this.toggleTrend(reports[currentIndex + 1]);
    }
  }

  get canGoPrev(): boolean {
    if (this.selectedTrendReportId == null) return false;
    const idx = this.filteredReports.findIndex(r => r.id === this.selectedTrendReportId);
    return idx > 0;
  }

  get canGoNext(): boolean {
    if (this.selectedTrendReportId == null) return false;
    const idx = this.filteredReports.findIndex(r => r.id === this.selectedTrendReportId);
    return idx < this.filteredReports.length - 1;
  }

  // ========== Skiftsammanfattning (print-vy) ==========

  toggleShiftSummary(report: any) {
    if (this.shiftSummaryReportId === report.id) {
      // Stäng
      this.shiftSummaryReportId = null;
      this.shiftSummaryData = null;
      return;
    }

    this.shiftSummaryReportId = report.id;
    this.shiftSummaryData = null;
    this.shiftSummaryError = '';
    this.shiftSummaryLoading = true;

    const datum = (report.datum || '').substring(0, 10);
    const shiftNr = this.getShiftNr(report);

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=shift-summary&date=${datum}&shift=${shiftNr}`,
      { withCredentials: true }
    )
    .pipe(
      timeout(8000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    )
    .subscribe(res => {
      this.shiftSummaryLoading = false;
      if (!res || !res.success) {
        this.shiftSummaryError = res?.error || 'Kunde inte hämta skiftsammanfattning';
        return;
      }
      this.shiftSummaryData = res.data;
    });
  }

  printShiftSummary() {
    window.print();
  }

  /** Oppnar print-optimerad skiftsammanfattning i nytt fonster (backend-genererad HTML) */
  openShiftPdf(report: any) {
    const datum = (report.datum || '').substring(0, 10);
    const shiftNr = this.getShiftNr(report);
    const url = `${environment.apiUrl}?action=rebotling&run=shift-pdf-summary&date=${datum}&shift=${shiftNr}`;
    window.open(url, '_blank', 'width=900,height=700,scrollbars=yes');
  }

  formatDrifttid(min: number | null): string {
    if (min == null || min === 0) return '–';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}min` : `${m}min`;
  }

  // ========== Skicka skiftrapport via email ==========
  openEmailReport(date: string, shift: number) {
    this.emailReportDate  = date;
    this.emailReportShift = shift;
    this.showEmailConfirm = true;
  }

  cancelEmailReport() {
    this.showEmailConfirm = false;
  }

  confirmSendEmailReport() {
    this.emailSending = true;
    this.http.post<any>(`${environment.apiUrl}?action=rebotling&run=auto-shift-report`, {
      date: this.emailReportDate,
      shift: this.emailReportShift
    }, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) {
            const count = res.recipients?.length ?? 0;
            this.showSuccess(`Skiftrapport skickad till ${count} mottagare`);
          } else {
            this.errorMessage = res?.error || 'Kunde inte skicka skiftrapport';
          }
          this.emailSending     = false;
          this.showEmailConfirm = false;
        },
        error: () => {
          this.errorMessage     = 'Serverfel vid sändning av skiftrapport';
          this.emailSending     = false;
          this.showEmailConfirm = false;
        }
      });
  }

  // ========== Toast ==========
  showSuccess(message: string) {
    this.successMessage      = message;
    this.showSuccessMessage  = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }

  // ======== Cachad operatörsranking per rapport (undviker tung beräkning vid varje CD) ========
  private operatorRankingCache = new Map<number, Array<{ name: string; ibc_per_h: number | null; personal_avg: number | null; rank: number }>>();

  getCachedOperatorRanking(report: any): Array<{ name: string; ibc_per_h: number | null; personal_avg: number | null; rank: number }> {
    const id = report?.id;
    if (id != null && this.operatorRankingCache.has(id)) {
      return this.operatorRankingCache.get(id)!;
    }
    const result = this.getOperatorRanking(report);
    if (id != null) this.operatorRankingCache.set(id, result);
    return result;
  }

  /** Rensa cache vid ny dataladdning */
  clearOperatorRankingCache(): void {
    this.operatorRankingCache.clear();
  }

  // ======== trackBy-funktioner ========

  trackByReportId(index: number, report: any): number {
    return report.id;
  }

  trackByProductId(index: number, product: any): number {
    return product.id;
  }

  trackByIndex(index: number, item: any): any {
    return item?.id ?? index;
  }

  trackByOpName(index: number, op: { name: string }): string {
    return op.name;
  }

  // ======== Visa Avancerat / Rådata Modal ========

  showAdvanced = false;
  showRawDataModal = false;
  rawDataLoading = false;
  rawDataError = '';
  rawDataDate = '';
  rawDataActiveTab: 'onoff' | 'rast' | 'driftstopp' | 'skiftrapport' = 'onoff';
  rawDataOnoff: any[] = [];
  rawDataRast: any[] = [];
  rawDataDriftstopp: any[] = [];
  rawDataSkiftrapport: any[] = [];

  toggleAdvanced(): void {
    this.showAdvanced = !this.showAdvanced;
  }

  openRawDataModal(date: string): void {
    this.rawDataDate = date;
    this.rawDataActiveTab = 'onoff';
    this.rawDataOnoff = [];
    this.rawDataRast = [];
    this.rawDataDriftstopp = [];
    this.rawDataSkiftrapport = [];
    this.rawDataError = '';
    this.rawDataLoading = true;
    this.showRawDataModal = true;

    // Fetch raw data from backend
    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=day-raw-data&date=${date}`,
      { withCredentials: true }
    ).pipe(
      timeout(10000),
      catchError(err => {
        console.error('Rådata-hämtning misslyckades:', err);
        this.rawDataError = 'Kunde inte hämta rådata för ' + date;
        this.rawDataLoading = false;
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.rawDataLoading = false;
      if (res?.success && res.data) {
        this.rawDataOnoff = res.data.onoff_events || [];
        this.rawDataRast = res.data.rast_events || [];
        this.rawDataDriftstopp = res.data.driftstopp_events || [];
        this.rawDataSkiftrapport = res.data.skiftrapport_data || [];
      } else if (!this.rawDataError) {
        this.rawDataError = 'Tomt svar från servern';
      }
      // Open the Bootstrap modal
      this.openBootstrapRawModal();
    });
  }

  private async openBootstrapRawModal(): Promise<void> {
    const { default: Modal } = await import('bootstrap/js/dist/modal');
    setTimeout(() => {
      const el = document.getElementById('rawDataDayModal');
      if (el) {
        const modal = new Modal(el);
        modal.show();
        el.addEventListener('hidden.bs.modal', () => {
          this.showRawDataModal = false;
        }, { once: true });
      }
    });
  }

  setRawDataTab(tab: 'onoff' | 'rast' | 'driftstopp' | 'skiftrapport'): void {
    this.rawDataActiveTab = tab;
  }

  formatRawDatum(datum: string): string {
    if (!datum) return '–';
    return datum.substring(11, 19) || datum;
  }
}
