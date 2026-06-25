import { Component, OnInit, OnDestroy, Input, NgZone } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';
import { AuthService } from '../../services/auth.service';
import { localToday, localDateStr } from '../../utils/date-utils';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

/**
 * Konfiguration per linje — skickas in via wrapper-komponenten.
 */
export interface LineSkiftrapportConfig {
  line: LineName;
  lineName: string;            // Visningsnamn, t.ex. "Tvättlinje"
  liveUrl: string | null;      // URL till live-sida, t.ex. "/tvattlinje/live"
  themeColor: string;          // Bootstrap-färgklass: "primary" | "warning" | "success"
  accentHex: string;           // Hex-accentfärg för CSS-variabler
  emptyText: string;           // Text vid tom-tillstånd
}

@Component({
  standalone: true,
  selector: 'app-shared-skiftrapport',
  imports: [CommonModule, FormsModule, DecimalPipe],
  templateUrl: './shared-skiftrapport.html',
  styleUrl: './shared-skiftrapport.css'
})
export class SharedSkiftrapportComponent implements OnInit, OnDestroy {
  @Input() config!: LineSkiftrapportConfig;

  reports: any[] = [];
  selectedIds: Set<number> = new Set();
  expanded: { [id: number]: boolean } = {};
  lopnummerMap: { [id: number]: string } = {};
  lopnummerLoading: { [id: number]: boolean } = {};
  subShiftsMap: { [reportId: number]: any[] } = {};
  subShiftsLoading: { [reportId: number]: boolean } = {};
  subShiftsShowAll: { [reportId: number]: boolean } = {};
  showRawSubShifts: { [reportId: number]: boolean } = {};
  fallbackCycleMin = 3.0;
  dagligBreakdownMap: { [reportId: number]: any[] } = {};
  dagligBreakdownLoading: { [reportId: number]: boolean } = {};
  readonly PRELIMINARY_ID = -1;
  preliminaryReport: any | null = null;
  unreportedPasses: any[] = [];
  readonly SUB_PAGE = 30;
  activeTab: { [id: number]: string } = {};
  expandedDays: { [date: string]: boolean } = {};
  loading = false;
  errorMessage = '';
  fetchFailed = false;
  successMessage = '';
  showSuccessMessage = false;
  isAdmin = false;
  user: any = null;
  loggedIn = false;
  showAddForm = false;
  addingReport = false;

  filterFrom = '';
  filterTo = '';
  operators: any[] = [];
  products: any[] = [];
  searchText = '';
  selectedOperatorId: number | null = null;
  showAdvanced = false;

  // Cachade KPI-värden — beräknas en gång per datahändelse, inte per change-detection-cykel
  cachedFilteredReports: any[] = [];
  cachedTotalIbc = 0;
  cachedTotalOk = 0;
  cachedTotalEjOk = 0;
  cachedTotalOmtvaatt = 0;
  cachedAvgQuality = 0;
  cachedAvgIbcPerSkift = 0;

  newReport: { datum: string; antal_ok: number; antal_ej_ok: number; kommentar: string; op1: number | null; op2: number | null; op3: number | null; product_id: number | null } = {
    datum: localToday(),
    antal_ok: 0,
    antal_ej_ok: 0,
    kommentar: '',
    op1: null,
    op2: null,
    op3: null,
    product_id: null
  };

  private destroy$ = new Subject<void>();
  private fetchSub: Subscription | null = null;
  private updateInterval: any = null;
  private successTimerId: any = null;
  private plcCharts = new Map<number, { hourly: Chart }>();
  // Namn-lookup-tabeller (O(1)) — byggs om när operators/products laddas
  private opNameMap = new Map<number, string>();
  private productNameMap = new Map<number, string>();
  // Per-rapport beräknade värden — byggs om i recomputeKpis, aldrig i template
  private reportCache = new Map<number, {
    effPct: number | null;
    ibcH: number | null;
    qualPct: number | null;
    oeePct: number | null;
    minIbc: string;
    tid: string;
  }>();
  // Beräknas EN gång i loadSubShifts — aldrig i template
  plcStatsCache = new Map<number, {
    kpi: { totalCycles: number; medianCycleMin: number; totalStopMin: number; avgCycleMin: number };
    stops: Array<{ start: string; end: string; durationMin: number }>;
    hourlyBuckets: Array<{ hour: number; count: number }>;
    hourlyMax: number;
    cycleStats: { avg: number | null; median: number | null; min: number | null; max: number | null };
    anomalyCount: number;
    firstCycleTime: number | null;
    lastCycleTime: number | null;
    sentInskickad: boolean;
  }>();

  constructor(
    private service: LineSkiftrapportService,
    private auth: AuthService,
    private zone: NgZone
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(u => {
      this.user = u;
      this.isAdmin = u?.role === 'admin' || u?.role === 'developer';
    });
    this.fetchReports();
    this.loadOperatorsAndProducts();
    this.service.getLineSettings(this.config.line)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        const takt = parseFloat(res?.data?.takt_mal ?? '');
        if (isFinite(takt) && takt > 0) this.fallbackCycleMin = takt;
      });
    this.updateInterval = setInterval(() => {
      if (!this.destroy$.closed) this.fetchReports(true);
    }, 15000);
  }

  ngOnDestroy() {
    clearInterval(this.updateInterval);
    clearTimeout(this.successTimerId);
    this.fetchSub?.unsubscribe();
    this.plcCharts.forEach(c => { try { c.hourly.destroy(); } catch (_e) { /* ignore */ } });
    this.plcCharts.clear();
    this.destroy$.next();
    this.destroy$.complete();
  }

  get filteredReports(): any[] {
    return this.reports.filter(r => {
      // Filtrera bort tomma rader
      if (!(r.antal_ok || 0) && !(r.antal_ej_ok || 0) && !(r.omtvaatt || 0)) return false;
      // Datumfilter
      const d = (r.datum || '').substring(0, 10);
      if (this.filterFrom && d < this.filterFrom) return false;
      if (this.filterTo   && d > this.filterTo)   return false;
      // Söktext
      if (this.searchText) {
        const s = this.searchText.toLowerCase();
        const productMatch = this.getProductName(r.product_id).toLowerCase().includes(s);
        const userMatch = (r.user_name || '').toLowerCase().includes(s);
        const opMatch = [r.op1, r.op2, r.op3].some(n => n && this.getOpName(n).toLowerCase().includes(s));
        if (!productMatch && !userMatch && !opMatch) return false;
      }
      // Operatörsfilter — selectedOperatorId håller op.number (inte op.id)
      if (this.selectedOperatorId !== null) {
        const opNum = Number(this.selectedOperatorId);
        if (Number(r.op1) !== opNum && Number(r.op2) !== opNum && Number(r.op3) !== opNum) return false;
      }
      return true;
    });
  }

  /** Beräknar KPI-värden baserat på filteredReports-gettern — kallas efter datahändelser. */
  private recomputeKpis(): void {
    const filtered = this.filteredReports;
    this.cachedFilteredReports = filtered;
    this.cachedTotalOmtvaatt = filtered.reduce((s, r) => s + (r.omtvaatt || 0), 0);
    this.cachedTotalOk = filtered.reduce((s, r) => s + (r.antal_ok || 0), 0);
    this.cachedTotalEjOk = filtered.reduce((s, r) => s + (r.antal_ej_ok || 0), 0);
    const totalIbc = filtered.reduce((s, r) => s + (r.totalt || ((r.antal_ok || 0) + (r.antal_ej_ok || 0))), 0);
    this.cachedTotalIbc = totalIbc;
    this.cachedAvgQuality = totalIbc === 0 ? 0 : Math.round((this.cachedTotalOk / totalIbc) * 1000) / 10;
    this.cachedAvgIbcPerSkift = filtered.length === 0 ? 0 : Math.round((totalIbc / filtered.length) * 10) / 10;
    this.rebuildReportCache();
    // Expandera dagens grupp automatiskt
    const today = localToday();
    if (this.expandedDays[today] === undefined) this.expandedDays[today] = true;
    // Daglig-fördelning laddas enbart vid explicit expand (ingen auto-load)
  }

  /** Bygger om cachen med per-rapport beräknade värden — kallas EN gång per datahändelse. */
  private rebuildReportCache(): void {
    this.reportCache.clear();
    const synthRows = [
      ...(this.preliminaryReport ? [this.preliminaryReport] : []),
      ...this.unreportedPasses
    ];
    const allReports = synthRows.length > 0 ? [...this.reports, ...synthRows] : this.reports;
    for (const r of allReports) {
      this.reportCache.set(r.id, {
        effPct:  this._computeEfficiencyPct(r),
        ibcH:    this._computeIbcPerHour(r),
        qualPct: this._computeQualityPct(r),
        oeePct:  this._computeOeePct(r),
        minIbc:  this._computeMinPerIbc(r),
        tid:     this._computeShiftTid(r),
      });
    }
  }

  // ===== Privata compute-metoder (kallas ALDRIG från template) =====

  private _computeEfficiencyPct(r: any): number | null {
    const totalt   = r.totalt || ((r.antal_ok || 0) + (r.antal_ej_ok || 0) + (r.omtvaatt || 0));
    const netMin   = Math.max(0, Math.min(r.drifttid || 0, 600) - (r.rasttime || 0));
    if (totalt <= 0 || netMin <= 0) return null;
    const actualCycle = netMin / totalt;
    const product     = this.products.find((p: any) => p.id === (r.product_id ?? null));
    const targetCycle = product?.cycle_time_minutes ?? this.fallbackCycleMin;
    if (!(targetCycle > 0) || !(actualCycle > 0)) return null;
    const v = Math.round((targetCycle / actualCycle) * 100);
    return isFinite(v) ? Math.min(v, 100) : null;
  }

  private _computeIbcPerHour(r: any): number | null {
    const netMin = Math.max(0, Math.min(r.drifttid || 0, 600) - (r.rasttime || 0));
    const totalt = r.totalt || ((r.antal_ok || 0) + (r.antal_ej_ok || 0));
    if (!(netMin > 0) || !(totalt > 0)) return null;
    const v = Math.round(netMin / totalt * 10) / 10;
    return isFinite(v) ? v : null;
  }

  private _computeQualityPct(r: any): number | null {
    if (!r.totalt) return null;
    const v = Math.round((r.antal_ok / r.totalt) * 1000) / 10;
    return isFinite(v) ? v : null;
  }

  private _computeOeePct(r: any): number | null {
    try {
      const totalIbc   = r.totalt ?? 0;
      const okIbc      = r.antal_ok ?? 0;
      if (totalIbc <= 0 || okIbc <= 0) return null;

      // Kvalitet: godkända / totalt (kassationsförlust)
      const kvalitet = okIbc / totalIbc;

      // Tillgänglighet: drifttid / (drifttid + rast + driftstopp)
      // Planerad tid = drifttid + rasttime; driftstopp är oplanerade stopp
      const drifttidMin  = Math.min(r.drifttid ?? 0, 600);
      const rasttime     = r.rasttime ?? 0;
      const driftstopp   = r.driftstopptime ?? 0;
      const plannadTid   = drifttidMin + rasttime + driftstopp;
      if (plannadTid <= 0) return null;
      const tillganglighet = Math.min(drifttidMin / plannadTid, 1);

      // Prestanda: faktisk IBC/h (okIbc) / mål IBC/h (baserat på cykeltid)
      // Drifttid är nettotid inkl. rast; använd drifttidMin som bas för IBC/h
      const product = this.productNameMap.size > 0
        ? this.products.find(p => p.id === (r.product_id ?? null))
        : null;
      const targetCycleMin = product?.cycle_time_minutes ?? this.fallbackCycleMin;
      const targetIbcH     = 60 / targetCycleMin;
      const ibcH           = drifttidMin > 0 ? (okIbc / (drifttidMin / 60)) : 0;
      const prestanda      = targetIbcH > 0 ? Math.min(ibcH / targetIbcH, 1) : 0;

      const v = Math.round(tillganglighet * prestanda * kvalitet * 100);
      return isFinite(v) ? v : null;
    } catch { return null; }
  }

  private _computeMinPerIbc(r: any): string {
    const ok = r.antal_ok ?? 0;
    const dt = Math.min(r.drifttid ?? 0, 600);
    if (ok <= 0 || dt <= 0) return '–';
    const v = dt / ok;
    return isFinite(v) ? v.toFixed(1) : '–';
  }

  private _computeShiftTid(report: any): string {
    const fmt = (d: Date) => d.toTimeString().substring(0, 5);
    const parseDt = (s: any) => {
      const d = new Date(String(s).replace(' ', 'T'));
      return isNaN(d.getTime()) ? null : d;
    };
    const fmtWithDate = (d: Date) => `${d.getDate()}/${d.getMonth() + 1} ${fmt(d)}`;
    try {
      // Daglig slice: visa perioden från förälderrapport
      if (report?._isDagligSlice && report?._parentReport?.period_start && report?._parentReport?.period_end) {
        const s = parseDt(report._parentReport.period_start);
        const e = parseDt(report._parentReport.period_end);
        if (s && e) return `${fmtWithDate(s)} – ${fmtWithDate(e)}`;
      }
      // Flerdagarsrapport: visa period med datum istället för bara klockslag
      if (report?.flerdagars == 1 && report?.period_start && report?.period_end) {
        const s = parseDt(report.period_start);
        const e = parseDt(report.period_end);
        if (s && e) return `${fmtWithDate(s)} – ${fmtWithDate(e)}`;
      }
      // Primär: backend-beräknade plc_start/plc_end (korrekt fönster)
      if (report?.plc_start && report?.plc_end) {
        const s = parseDt(report.plc_start);
        const e = parseDt(report.plc_end);
        const MIN_TS = new Date('2020-01-01').getTime();
        if (s && e && s.getTime() >= MIN_TS && e.getTime() <= Date.now() + 60000 && s < e) {
          return `${fmt(s)}→${fmt(e)}`;
        }
      }
      // Fallback: datum med tidkomponent
      if (report?.datum) {
        const raw = String(report.datum);
        if (raw.length > 10) {
          const d = parseDt(raw);
          if (d) {
            const s = fmt(d);
            const drifttidMs = (report.drifttid ?? 0) * 60 * 1000;
            if (drifttidMs > 0) {
              const end = new Date(d.getTime() + drifttidMs);
              if (isFinite(end.getTime())) return `${s}→${fmt(end)}`;
            }
            return s;
          }
        }
      }
      // Sista utväg: created_at
      if (report?.created_at) {
        const d = parseDt(report.created_at);
        if (d) return fmt(d);
      }
    } catch { /* ignore */ }
    return '–';
  }

  toggleAdvanced(): void {
    this.showAdvanced = !this.showAdvanced;
  }

  toggleAddForm(): void {
    this.showAddForm = !this.showAddForm;
    if (this.showAddForm) {
      this.newReport = { datum: localToday(), antal_ok: 0, antal_ej_ok: 0, kommentar: '', op1: null, op2: null, op3: null, product_id: null };
      this.errorMessage = '';
    }
  }

  openAddForm(): void {
    this.newReport = { datum: localToday(), antal_ok: 0, antal_ej_ok: 0, kommentar: '', op1: null, op2: null, op3: null, product_id: null };
    this.errorMessage = '';
    this.showAddForm = true;
  }

  clearFilter(): void {
    this.filterFrom = '';
    this.filterTo = '';
    this.searchText = '';
    this.selectedOperatorId = null;
    this.recomputeKpis();
  }

  applyFilter(): void {
    this.recomputeKpis();
  }

  onSearchInput(): void { this.recomputeKpis(); }
  onOperatorFilterChange(): void { this.recomputeKpis(); }

  getSelectedOperatorName(): string {
    if (this.selectedOperatorId == null) return '';
    return this.operators.find(o => Number(o.number) === Number(this.selectedOperatorId))?.name || '';
  }

  // ========== KPI getters (computed per change-detection) ==========

  get summaryTotalIbc(): number {
    return this.filteredReports.reduce(
      (s, r) => s + (r.totalt || ((r.antal_ok || 0) + (r.antal_ej_ok || 0))), 0
    );
  }

  get summaryAvgIbcH(): number | null {
    const totalDrift = this.filteredReports.reduce((s, r) => s + Math.min(r.drifttid || 0, 600), 0);
    const totalIbc = this.filteredReports.reduce((s, r) => s + (r.totalt || ((r.antal_ok || 0) + (r.antal_ej_ok || 0))), 0);
    if (totalDrift <= 0 || totalIbc <= 0) return null;
    return Math.round(totalDrift / totalIbc * 10) / 10;
  }

  get summaryAvgEfficiency(): number | null {
    const totalDrift    = this.filteredReports.reduce((s, r) => s + Math.min(r.drifttid || 0, 600), 0);
    const totalDriftstopp = this.filteredReports.reduce((s, r) => s + (r.driftstopptime || 0), 0);
    if (totalDrift <= 0) return null;
    const schema = totalDrift + totalDriftstopp;
    return totalDriftstopp > 0 ? Math.round((totalDrift / schema) * 100) : 100;
  }

  get summaryAvgOee(): number | null {
    const reports = this.filteredReports;
    if (!reports.length) return null;
    // Cap drifttid at 600 min (~10h) per report — one shift per day, never 24h
    const cappedDrift     = (r: any) => Math.min(r.drifttid || 0, 600);
    const netDrift        = (r: any) => Math.max(0, cappedDrift(r) - (r.rasttime || 0));
    const totalDrift      = reports.reduce((s, r) => s + cappedDrift(r), 0);
    const totalDriftstopp = reports.reduce((s, r) => s + (r.driftstopptime || 0), 0);
    if (totalDrift <= 0) return null;
    const totalIbc = this.cachedTotalIbc;
    const totalOk  = this.cachedTotalOk;
    if (totalIbc <= 0) return null;
    const schema         = totalDrift + totalDriftstopp;
    const tillganglighet = totalDriftstopp > 0 ? totalDrift / schema : 1.0;
    const kvalitet       = totalOk / totalIbc;
    const totalNettoDriftSek = reports.reduce((s, r) => s + netDrift(r), 0) * 60;
    let totalIdealSek = 0;
    for (const r of reports) {
      const rIbc     = (r.antal_ok || 0) + (r.antal_ej_ok || 0);
      const product  = this.products.find((p: any) => p.id === (r.product_id ?? null));
      const cycleSek = (product?.cycle_time_minutes ?? this.fallbackCycleMin) * 60;
      totalIdealSek += rIbc * cycleSek;
    }
    const prestanda = totalNettoDriftSek > 0 ? Math.min(totalIdealSek / totalNettoDriftSek, 1) : 1.0;
    const v = Math.round(tillganglighet * prestanda * kvalitet * 100);
    return isFinite(v) ? v : null;
  }

  get summaryTotalDrift(): number {
    // Cap per skift 600 min (~10h) för att förhindra att en felaktig DB-rad blåser upp totalen
    return this.groupedDays.reduce((s, d) => s + d.totalDrift, 0);
  }

  get summaryAvgEff(): number | null {
    const reports = this.filteredReports;
    let totalIdealMin = 0;
    let totalNettoMin = 0;
    for (const r of reports) {
      const totalt   = (r.totalt || 0) || ((r.antal_ok || 0) + (r.antal_ej_ok || 0) + (r.omtvaatt || 0));
      const netMin   = Math.max(0, Math.min(r.drifttid || 0, 600) - (r.rasttime || 0));
      if (totalt <= 0 || netMin <= 0) continue;
      const product     = this.products.find((p: any) => p.id === (r.product_id ?? null));
      const targetCycle = product?.cycle_time_minutes ?? this.fallbackCycleMin;
      totalIdealMin += totalt * targetCycle;
      totalNettoMin += netMin;
    }
    if (totalNettoMin <= 0) return null;
    const v = Math.round((totalIdealMin / totalNettoMin) * 100);
    return isFinite(v) ? Math.min(v, 100) : null;
  }

  // ========== Per-rad helpers ==========

  getIbcPerHour(r: any): number | null {
    if (!r) return null;
    const c = this.reportCache.get(r.id);
    return c !== undefined ? c.ibcH : this._computeIbcPerHour(r);
  }

  getEfficiencyPct(r: any): number | null {
    if (!r) return null;
    const c = this.reportCache.get(r.id);
    return c !== undefined ? c.effPct : this._computeEfficiencyPct(r);
  }

  getOeePct(r: any): number | null {
    if (!r) return null;
    const c = this.reportCache.get(r.id);
    return c !== undefined ? c.oeePct : this._computeOeePct(r);
  }

  getCappedDrifttid(r: any): number {
    return Math.min(r?.drifttid || 0, 600);
  }

  getCappedSubRuntime(sub: any): number {
    return Math.min(sub?.runtime_plc ?? 0, 600);
  }

  formatDrifttid(min: number): string {
    if (!min || min <= 0) return '–';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  getProductName(productId: number | null): string {
    if (!productId) return '';
    return this.productNameMap.get(Number(productId)) ?? this.products.find(p => p.id === productId)?.name ?? `#${productId}`;
  }

  getOpName(num: number | null): string {
    if (!num) return '';
    const n = Number(num);
    return this.opNameMap.get(n) ?? this.operators.find(o => Number(o.number) === n)?.name ?? `Okänd(#${num})`;
  }

  private loadOperatorsAndProducts(): void {
    this.service.getOperators(this.config.line)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.operators = res.data || [];
          this.opNameMap.clear();
          this.operators.forEach((o: any) => this.opNameMap.set(Number(o.number), o.name));
          this.rebuildReportCache();
        }
      });
    this.service.getProducts(this.config.line)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.products = res.data || [];
          this.productNameMap.clear();
          this.products.forEach((p: any) => this.productNameMap.set(Number(p.id), p.name));
          this.rebuildReportCache();
        }
      });
  }

  getQualityPct(r: any): number | null {
    if (!r) return null;
    const c = this.reportCache.get(r.id);
    return c !== undefined ? c.qualPct : this._computeQualityPct(r);
  }

  /** Säker visning: returnerar '–' för NaN, Infinity, null, negativa tal. */
  safeDisplay(v: any, decimals = 0): string {
    if (v == null) return '–';
    const n = Number(v);
    if (!isFinite(n) || isNaN(n) || n < 0) return '–';
    return decimals > 0 ? n.toFixed(decimals) : String(Math.round(n));
  }

  getAnomalyCount(reportId: number): number {
    return this.plcStatsCache.get(reportId)?.anomalyCount ?? 0;
  }

  /** Formaterar ett datum-fält till HH:MM med fallback '–' (säker mot ogiltiga timestamps). */
  formatSubTime(datum: any): string {
    if (!datum) return '–';
    try {
      const d = new Date(String(datum).replace(' ', 'T'));
      return isNaN(d.getTime()) ? '–' : d.toTimeString().substring(0, 5);
    } catch { return '–'; }
  }

  minPerIbc(r: any): string {
    if (!r) return '–';
    const c = this.reportCache.get(r.id);
    return c !== undefined ? c.minIbc : this._computeMinPerIbc(r);
  }

  /** @deprecated Använd cachedTotalIbc direkt i templaten */
  getTotalIbc(): number { return this.cachedTotalIbc; }
  /** @deprecated Använd cachedTotalOk direkt i templaten */
  getTotalOk(): number { return this.cachedTotalOk; }
  /** @deprecated Använd cachedTotalEjOk direkt i templaten */
  getTotalEjOk(): number { return this.cachedTotalEjOk; }
  /** @deprecated Använd cachedAvgQuality direkt i templaten */
  getAvgQuality(): number { return this.cachedAvgQuality; }
  /** @deprecated Använd cachedAvgIbcPerSkift direkt i templaten */
  getAvgIbcPerSkift(): number { return this.cachedAvgIbcPerSkift; }

  toggleSelect(id: number) {
    this.selectedIds.has(id) ? this.selectedIds.delete(id) : this.selectedIds.add(id);
  }

  toggleSelectAll() {
    const v = this.filteredReports;
    if (this.selectedIds.size === v.length && v.length > 0) this.selectedIds.clear();
    else v.forEach(r => this.selectedIds.add(r.id));
  }

  isSelected(id: number) { return this.selectedIds.has(id); }
  isOwner(r: any) { return this.user && r.user_id === this.user.id; }
  canEdit(r: any) { return this.isAdmin || this.isOwner(r); }
  toggleExpand(id: number) {
    const wasExpanded = !!this.expanded[id];
    this.expanded[id] = !wasExpanded;
    if (this.expanded[id]) {
      // Syntetiska rader (id < 0): preliminary + ej inskickade pass
      if (id < 0) {
        const synth = id === this.PRELIMINARY_ID
          ? this.preliminaryReport
          : this.unreportedPasses.find(u => u.id === id);
        if (!this.plcStatsCache.has(id) && synth) this.computePlcStats(id);
        if (synth?.plc_start && this.lopnummerMap[id] === undefined) this.loadLopnummer(synth);
        setTimeout(() => this.renderHourlyChart(id, synth), 50);
        return;
      }
      const report = this.reports.find(r => r.id === id);
      if (report?.skiftraknare && this.lopnummerMap[id] === undefined) {
        this.loadLopnummer(report);
      }
      // Ladda per-dag-fördelning om rapporten är flerdagars
      if (report?.flerdagars == 1 && this.dagligBreakdownMap[id] === undefined) {
        this.loadDagligBreakdown(id);
      }
      // Ladda PLC-data om created_at finns (fönster kan beräknas)
      if (report?.created_at && this.subShiftsMap[id] === undefined) {
        this.loadSubShifts(report, () => this.renderHourlyChart(id, report));
      } else if (this.subShiftsMap[id]?.length > 0) {
        if (!this.plcStatsCache.has(id)) this.computePlcStats(id);
        setTimeout(() => this.renderHourlyChart(id, report), 50);
      }
    } else {
      const existing = this.plcCharts.get(id);
      if (existing) {
        try { existing.hourly.destroy(); } catch (_e) { /* ignore */ }
        this.plcCharts.delete(id);
      }
    }
  }

  private loadSubShifts(report: any, onLoaded?: () => void): void {
    const id = report.id;
    this.subShiftsLoading[id] = true;

    // Fönsterlogik (spec C): start = max(prev_created_at, created_at − 12h) så natt aldrig läcker
    const createdAtMs = report.created_at
      ? new Date(String(report.created_at).replace(' ', 'T')).getTime() : null;
    const prevCAtMs = report.prev_created_at
      ? new Date(String(report.prev_created_at).replace(' ', 'T')).getTime() : null;

    let from: string;
    let to: string;
    if (createdAtMs && !isNaN(createdAtMs)) {
      const cap12h = createdAtMs - 12 * 3600000;
      const fromMs = (prevCAtMs && !isNaN(prevCAtMs)) ? Math.max(prevCAtMs, cap12h) : cap12h;
      from = new Date(fromMs).toISOString().replace('T', ' ').substring(0, 19);
      to   = new Date(createdAtMs).toISOString().replace('T', ' ').substring(0, 19);
    } else {
      from = String(report.prev_created_at ?? '');
      to   = String(report.created_at ?? '');
    }

    this.service.getSubShifts(this.config.line, from, to)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.subShiftsLoading[id] = false;
        this.subShiftsMap[id] = res?.success ? (res.data || []) : [];
        this.computePlcStats(id);
        if (onLoaded) setTimeout(onLoaded, 50);
      });
  }

  formatPeriodDate(dt: string | null): string {
    if (!dt) return '?';
    const d = new Date(String(dt).replace(' ', 'T'));
    return isNaN(d.getTime()) ? dt.substring(0, 10) : `${d.getDate()}/${d.getMonth() + 1}`;
  }

  loadDagligBreakdown(id: number): void {
    this.dagligBreakdownLoading[id] = true;
    this.service.getDagligBreakdown(this.config.line, id)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.dagligBreakdownLoading[id] = false;
        this.dagligBreakdownMap[id] = res?.data || [];
      });
  }

  private loadPreliminaryShift(): void {
    // Gräns = senaste rapport med innehåll (antal_ok > 0), annars senaste rapport, annars dagens midnatt
    let from: string;
    const contentful = this.reports.filter((r: any) => (r.antal_ok || 0) > 0 || (r.antal_ej_ok || 0) > 0);
    const base = contentful.length ? contentful : this.reports;
    if (base.length) {
      const latest = base.reduce((best: any, r: any) =>
        (!best || new Date(String(r.created_at).replace(' ', 'T')) > new Date(String(best.created_at).replace(' ', 'T'))) ? r : best
      , null as any);
      from = String(latest.created_at);
    } else {
      const midnight = new Date();
      midnight.setHours(0, 0, 0, 0);
      from = midnight.toISOString().replace('T', ' ').substring(0, 19);
    }

    this.service.getUnreportedCycles(this.config.line, from)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        try {
          const subs: any[] = res?.success ? (res.data || []) : [];

          // Rensa gamla syntetiska rader
          const existingChart = this.plcCharts.get(this.PRELIMINARY_ID);
          if (existingChart) { try { existingChart.hourly.destroy(); } catch (_e) { /* ignore */ } this.plcCharts.delete(this.PRELIMINARY_ID); }
          this.plcStatsCache.delete(this.PRELIMINARY_ID);
          for (const up of this.unreportedPasses) {
            const c = this.plcCharts.get(up.id);
            if (c) { try { c.hourly.destroy(); } catch (_e) { /* ignore */ } this.plcCharts.delete(up.id); }
            this.plcStatsCache.delete(up.id);
          }
          this.unreportedPasses = [];

          if (!subs.length) {
            this.preliminaryReport = null;
            this.subShiftsMap[this.PRELIMINARY_ID] = [];
            this.rebuildReportCache();
            return;
          }

          // Validera och sortera tidsstämplar
          const now = Date.now();
          const MIN_TS = new Date('2020-01-01T00:00:00').getTime();
          const validSubs = subs.filter((s: any) => {
            if (!s.datum) return false;
            const t = new Date(String(s.datum).replace(' ', 'T')).getTime();
            return !isNaN(t) && t >= MIN_TS && t <= now + 60000;
          }).sort((a: any, b: any) =>
            new Date(String(a.datum).replace(' ', 'T')).getTime() -
            new Date(String(b.datum).replace(' ', 'T')).getTime()
          );

          if (!validSubs.length) {
            this.preliminaryReport = null;
            this.subShiftsMap[this.PRELIMINARY_ID] = [];
            this.rebuildReportCache();
            return;
          }

          // Gruppera cykler i pass via 60-min gap
          const PASS_GAP_MIN = 60;
          const passes: Array<{ subs: any[]; times: number[] }> = [];
          let curSubs: any[] = [validSubs[0]];
          let curTimes: number[] = [new Date(String(validSubs[0].datum).replace(' ', 'T')).getTime()];

          for (let i = 1; i < validSubs.length; i++) {
            const t = new Date(String(validSubs[i].datum).replace(' ', 'T')).getTime();
            const gapMin = (t - curTimes[curTimes.length - 1]) / 60000;
            if (gapMin > PASS_GAP_MIN) {
              passes.push({ subs: curSubs, times: curTimes });
              curSubs = [validSubs[i]];
              curTimes = [t];
            } else {
              curSubs.push(validSubs[i]);
              curTimes.push(t);
            }
          }
          passes.push({ subs: curSubs, times: curTimes });

          // Klassificera pass: senaste cykel < 60 min sen = pågående, annars = ej inskickad
          const ACTIVE_MIN = 60;
          const cumulDelta = (last: any, first: any): number => {
            const l = +(last ?? 0); const f = +(first ?? 0);
            return (!isFinite(l) || !isFinite(f)) ? 0 : (l >= f ? l - f : 0);
          };
          const buildSynth = (pass: { subs: any[]; times: number[] }, id: number, isPreliminary: boolean): any => {
            const fs = pass.subs[0]; const ls = pass.subs[pass.subs.length - 1];
            const ibcEjOk  = cumulDelta(ls?.ibc_ej_ok,  fs?.ibc_ej_ok);
            const drifttid = cumulDelta(ls?.runtime_plc, fs?.runtime_plc);
            const rasttime = cumulDelta(ls?.rasttime,    fs?.rasttime);

            // IBC-beräkning — 3 prioritetsnivåer:
            // P1: ibc_ok kumulativ delta (rebotling — kolumnen har data)
            // P2: summa positiva deltan av ibc_count, 0 < d < 50 (tvattlinje — ibc_ok är NULL)
            // P3: unika lopnummer (sista utväg — kan underskatta, märks med ~)
            const ibcOkRaw = cumulDelta(ls?.ibc_ok, fs?.ibc_ok);
            let ibcOk = 0;
            let ibcEstimated = false;
            if (ibcOkRaw > 0) {
              ibcOk = ibcOkRaw;                        // P1
            } else {
              let countSum = 0;
              for (let i = 1; i < pass.subs.length; i++) {
                const prev = +(pass.subs[i - 1]?.ibc_count ?? NaN);
                const curr = +(pass.subs[i]?.ibc_count ?? NaN);
                if (isFinite(prev) && isFinite(curr)) { const d = curr - prev; if (d > 0 && d < 50) countSum += d; }
              }
              if (countSum > 0) { ibcOk = countSum; ibcEstimated = true; }  // P2
              else {
                const lopSet = new Set(pass.subs.filter((s: any) => s.lopnummer > 0 && s.lopnummer < 9998).map((s: any) => s.lopnummer));
                ibcOk = lopSet.size > 0 ? lopSet.size : pass.subs.length;
                ibcEstimated = true;                   // P3
              }
            }
            const firstT = pass.times[0];
            const lastT  = pass.times[pass.times.length - 1];
            // Formatera i LOKAL tid — toISOString() ger UTC (-2h i CEST) → fel visad tid + fel daggrupp
            const localDt = (ms: number): string => {
              const d = new Date(ms);
              return localDateStr(d) + ' ' +
                String(d.getHours()).padStart(2, '0') + ':' +
                String(d.getMinutes()).padStart(2, '0') + ':' +
                String(d.getSeconds()).padStart(2, '0');
            };
            const nowStr   = localDt(Date.now());
            const plcStart = localDt(firstT);
            const plcEnd   = localDt(lastT);
            // Operatörer: räkna frekvens per op-nummer i cykeldatan, ta topp 3
            const opFreq = new Map<number, number>();
            pass.subs.forEach((s: any) => {
              for (const n of [s.op1, s.op2, s.op3]) {
                const num = parseInt(n, 10); if (num > 0) opFreq.set(num, (opFreq.get(num) || 0) + 1);
              }
            });
            const sortedOps = Array.from(opFreq.entries()).sort((a, b) => b[1] - a[1]).map(e => e[0]);
            const [sOp1 = null, sOp2 = null, sOp3 = null] = sortedOps;
            const opNameFrom = (num: number | null): string | null => {
              if (!num) return null;
              for (const s of pass.subs) {
                if (+s.op1 === num && s.op1_name) return s.op1_name;
                if (+s.op2 === num && s.op2_name) return s.op2_name;
                if (+s.op3 === num && s.op3_name) return s.op3_name;
              }
              return null;
            };
            return {
              id, datum: localDateStr(new Date(firstT)),
              antal_ok: ibcOk, antal_ej_ok: ibcEjOk, totalt: ibcOk + ibcEjOk,
              ibcEstimated,
              drifttid, rasttime,
              isPreliminary, isUnreported: !isPreliminary,
              prev_created_at: from,
              created_at: isPreliminary ? nowStr : plcEnd,
              plc_start: plcStart, plc_end: plcEnd,
              _firstTime: firstT, _lastTime: lastT,
              skiftraknare: fs?.skiftraknare ?? null,
              product_id: fs?.produkt ?? null,
              op1: sOp1, op2: sOp2, op3: sOp3,
              op1_name: opNameFrom(sOp1), op2_name: opNameFrom(sOp2), op3_name: opNameFrom(sOp3),
            };
          };

          let prelimIdx = -1;
          const unreportedIdxs: number[] = [];
          for (let i = passes.length - 1; i >= 0; i--) {
            const lastT = passes[i].times[passes[i].times.length - 1];
            if ((now - lastT) / 60000 < ACTIVE_MIN && prelimIdx === -1) {
              prelimIdx = i;
            } else {
              unreportedIdxs.push(i);
            }
          }

          // Bygg pågående pass
          if (prelimIdx >= 0) {
            this.preliminaryReport = buildSynth(passes[prelimIdx], this.PRELIMINARY_ID, true);
            this.subShiftsMap[this.PRELIMINARY_ID] = passes[prelimIdx].subs;
            this.computePlcStats(this.PRELIMINARY_ID);
          } else {
            this.preliminaryReport = null;
            this.subShiftsMap[this.PRELIMINARY_ID] = [];
          }

          // Bygg ej inskickade pass (id -2, -3, ...)
          let nextId = -2;
          this.unreportedPasses = unreportedIdxs.map(idx => {
            const id = nextId--;
            const synth = buildSynth(passes[idx], id, false);
            this.subShiftsMap[id] = passes[idx].subs;
            this.computePlcStats(id);
            return synth;
          });

          this.rebuildReportCache();
          if (this.expanded[this.PRELIMINARY_ID] && this.preliminaryReport) {
            setTimeout(() => this.renderHourlyChart(this.PRELIMINARY_ID, this.preliminaryReport), 50);
          }
          for (const up of this.unreportedPasses) {
            if (this.expanded[up.id]) setTimeout(() => this.renderHourlyChart(up.id, up), 50);
          }
        } catch (e) {
          console.error('loadPreliminaryShift misslyckades', e);
          this.preliminaryReport = null;
          this.unreportedPasses = [];
        }
      });
  }

  private computePlcStats(reportId: number): void {
    const emptyResult = {
      kpi: { totalCycles: 0, medianCycleMin: 0, totalStopMin: 0, avgCycleMin: 0 },
      stops: [] as Array<{ start: string; end: string; durationMin: number }>,
      hourlyBuckets: [] as Array<{ hour: number; count: number }>,
      hourlyMax: 1,
      cycleStats: { avg: null, median: null, min: null, max: null } as { avg: number | null; median: number | null; min: number | null; max: number | null },
      anomalyCount: 0,
      firstCycleTime: null as number | null,
      lastCycleTime: null as number | null,
      sentInskickad: false,
    };
    try {
      const subs = this.subShiftsMap[reportId] || [];
      const report = reportId === this.PRELIMINARY_ID
        ? this.preliminaryReport
        : reportId < 0
          ? this.unreportedPasses.find(u => u.id === reportId)
          : this.reports.find(r => r.id === reportId);

      // Fönstergränser: spec C — max(prev_created_at, created_at−12h) → created_at
      const createdAtMs = report?.created_at
        ? new Date(String(report.created_at).replace(' ', 'T')).getTime() : null;
      const prevCAtMs = report?.prev_created_at
        ? new Date(String(report.prev_created_at).replace(' ', 'T')).getTime() : null;
      const wsMs = (createdAtMs && !isNaN(createdAtMs))
        ? ((prevCAtMs && !isNaN(prevCAtMs)) ? Math.max(prevCAtMs, createdAtMs - 12 * 3600000) : createdAtMs - 12 * 3600000)
        : null;
      const weMs = (createdAtMs && !isNaN(createdAtMs)) ? createdAtMs : null;

      const windowSubs = (wsMs && weMs)
        ? subs.filter((s: any) => { const t = new Date(String(s.datum).replace(' ', 'T')).getTime(); return !isNaN(t) && t > wsMs && t <= weMs; })
        : subs;

      // Validera tidsstämplar — exkludera framtida, uråldriga och ogiltiga
      const now = Date.now();
      const MIN_VALID_TS = new Date('2020-01-01T00:00:00').getTime();
      let anomalyCount = 0;
      const allTimes: number[] = [];
      for (const s of windowSubs) {
        if (!s.datum) { anomalyCount++; continue; }
        const t = new Date(String(s.datum).replace(' ', 'T')).getTime();
        if (isNaN(t) || t < MIN_VALID_TS || t > now + 60000) { anomalyCount++; continue; }
        allTimes.push(t);
      }

      // Spec C: gap-baserad skiftdetektering — isolera det senaste skiftet i fönstret
      // Stopp > 60 min eller dygngräns = nytt pass, börja från slutet bakåt
      const SHIFT_GAP_MIN = 60;
      let shiftStartIdx = 0;
      for (let i = allTimes.length - 2; i >= 0; i--) {
        const gapMin = (allTimes[i + 1] - allTimes[i]) / 60000;
        if (gapMin > SHIFT_GAP_MIN) { shiftStartIdx = i + 1; break; }
      }
      const times = allTimes.slice(shiftStartIdx);

      const firstCycleTime = times.length > 0 ? times[0] : null;
      const lastCycleTime  = times.length > 0 ? times[times.length - 1] : null;

      // Spec D: Sent inskickad — sista cykeln och created_at skiljer sig mer än 2h
      const sentInskickad = !!(lastCycleTime && createdAtMs && (createdAtMs - lastCycleTime) > 2 * 3600000);

      // Gaps för nuvarande skift (times — redan isolerat)
      const gaps: number[] = [];
      for (let i = 1; i < times.length; i++) {
        const g = (times[i] - times[i - 1]) / 60000;
        if (g > 0) gaps.push(g);
      }

      const sorted = [...gaps].sort((a, b) => a - b);
      const median = sorted.length ? sorted[Math.floor(sorted.length / 2)] : 0;
      // Spec B: stopp = gap > max(2×median, 10 min)
      const threshold = Math.max(median * 2, 10);
      const stopGaps = gaps.filter(g => g > threshold);
      const cycleGaps = gaps.filter(g => g <= threshold);

      const kpi = {
        totalCycles: times.length,
        medianCycleMin: Math.round(median * 10) / 10,
        totalStopMin: Math.round(stopGaps.reduce((s, g) => s + g, 0)),
        avgCycleMin: cycleGaps.length
          ? Math.round(cycleGaps.reduce((s, g) => s + g, 0) / cycleGaps.length * 10) / 10 : 0
      };

      const stops: Array<{ start: string; end: string; durationMin: number }> = [];
      for (let i = 1; i < times.length; i++) {
        const gapMin = (times[i] - times[i - 1]) / 60000;
        if (gapMin > threshold) {
          stops.push({
            start: new Date(times[i - 1]).toTimeString().substring(0, 5),
            end: new Date(times[i]).toTimeString().substring(0, 5),
            durationMin: Math.round(gapMin)
          });
        }
      }

      const bucketMap: Record<number, number> = {};
      for (const t of times) {
        const h = new Date(t).getHours();
        if (h >= 0 && h <= 23) bucketMap[h] = (bucketMap[h] || 0) + 1;
      }
      const hourlyBuckets = Object.entries(bucketMap)
        .map(([h, c]) => ({ hour: +h, count: c as number }))
        .sort((a, b) => a.hour - b.hour);
      const hourlyMax = hourlyBuckets.reduce((m, b) => b.count > m ? b.count : m, 1);

      const filteredGaps = cycleGaps.filter(g => g > 0);
      let cycleStats: { avg: number | null; median: number | null; min: number | null; max: number | null } =
        { avg: null, median: null, min: null, max: null };
      if (filteredGaps.length) {
        const fs = [...filteredGaps].sort((a, b) => a - b);
        const mid = Math.floor(fs.length / 2);
        cycleStats = {
          avg: Math.round(filteredGaps.reduce((s, v) => s + v, 0) / filteredGaps.length * 10) / 10,
          median: fs.length % 2 === 0
            ? Math.round((fs[mid - 1] + fs[mid]) / 2 * 10) / 10
            : Math.round(fs[mid] * 10) / 10,
          min: Math.round(fs[0] * 10) / 10,
          max: Math.round(fs[fs.length - 1] * 10) / 10
        };
      }

      this.plcStatsCache.set(reportId, { kpi, stops, hourlyBuckets, hourlyMax, cycleStats, anomalyCount, firstCycleTime, lastCycleTime, sentInskickad });
    } catch (e) {
      console.error('computePlcStats misslyckades', reportId, e);
      this.plcStatsCache.set(reportId, emptyResult);
    }
  }

  private loadLopnummer(report: any): void {
    const id = report.id;
    this.lopnummerLoading[id] = true;
    // Syntetiska rader har plc_start/plc_end — använd from/to istf skiftraknare
    const obs = report.plc_start
      ? this.service.getLopnummer(this.config.line, 0, report.plc_start, report.plc_end || '')
      : this.service.getLopnummer(this.config.line, report.skiftraknare);
    obs.pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.lopnummerLoading[id] = false;
        this.lopnummerMap[id] = res?.success ? res.ranges : '–';
      });
  }

  fetchReports(silent = false) {
    if (!silent) { this.loading = true; this.errorMessage = ''; this.fetchFailed = false; }
    this.fetchSub?.unsubscribe();
    this.fetchSub = this.service.getReports(this.config.line)
      .pipe(
        timeout(15000),
        catchError(err => {
          console.error('Fel vid hämtning av rapporter:', err);
          return of({ success: false, error: 'Kunde inte hämta rapporter', data: [] });
        }),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (!silent) this.loading = false;
          if (res.success) {
            this.fetchFailed = false;
            const nr = res.data || [];
            if (silent) {
              const ec = { ...this.expanded };
              const sc = new Set(this.selectedIds);
              this.reports = nr;
              this.expanded = ec;
              this.selectedIds = new Set(Array.from(sc).filter(id => nr.some((r: any) => r.id === id)));
            } else {
              this.reports = nr;
            }
            this.recomputeKpis();
            this.loadPreliminaryShift();
          } else {
            this.fetchFailed = true;
            this.errorMessage = res.error || 'Kunde inte hämta rapporter';
          }
        }
      });
  }

  addReport() {
    this.errorMessage = '';
    if (!this.newReport.datum) { this.errorMessage = 'Datum krävs'; return; }
    if (this.addingReport) return;
    this.addingReport = true;
    this.loading = true;
    this.service.createReport(this.config.line, this.newReport)
      .pipe(timeout(15000), catchError(err => {
        console.error('Fel vid skapande av rapport:', err);
        return of({ success: false, error: 'Kunde inte skapa rapport' });
      }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.loading = false;
          this.addingReport = false;
          if (res.success) {
            this.fetchReports();
            this.newReport = { datum: localToday(), antal_ok: 0, antal_ej_ok: 0, kommentar: '', op1: null, op2: null, op3: null, product_id: null };
            this.showAddForm = false;
            this.showSuccess('Rapport tillagd');
          } else {
            this.errorMessage = res.error || 'Kunde inte lägga till';
          }
        }
      });
  }

  saveReport(report: any) {
    const datum = (report.datum || '').split(' ')[0];
    this.service.updateReport(this.config.line, report.id, {
      datum,
      antal_ok: parseInt(report.antal_ok, 10) || 0,
      antal_ej_ok: parseInt(report.antal_ej_ok, 10) || 0,
      kommentar: report.kommentar || ''
    }).pipe(timeout(15000), catchError(err => {
      console.error('Fel vid uppdatering av rapport:', err);
      return of({ success: false, error: 'Kunde inte uppdatera rapport' });
    }), takeUntil(this.destroy$)).subscribe({
      next: (res) => {
        if (res.success) {
          report.totalt = (parseInt(report.antal_ok, 10) || 0) + (parseInt(report.antal_ej_ok, 10) || 0);
          report.datum = datum;
          this.expanded[report.id] = false;
          this.fetchReports();
          this.showSuccess('Rapport uppdaterad');
        } else {
          this.errorMessage = res.error || 'Kunde inte uppdatera';
        }
      }
    });
  }

  deleteReport(id: number) {
    if (!confirm('Ta bort rapport?')) return;
    this.service.deleteReport(this.config.line, id)
      .pipe(timeout(15000), catchError(err => {
        console.error('Fel vid borttagning av rapport:', err);
        return of({ success: false, error: 'Kunde inte ta bort rapport' });
      }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.reports = this.reports.filter(r => r.id !== id);
            this.selectedIds.delete(id);
            this.recomputeKpis();
            this.showSuccess('Rapport borttagen');
          } else {
            this.errorMessage = res.error || 'Kunde inte ta bort';
          }
        }
      });
  }

  bulkDelete() {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    if (!confirm(`Ta bort ${this.selectedIds.size} rapport(er)?`)) return;
    this.service.bulkDelete(this.config.line, Array.from(this.selectedIds))
      .pipe(timeout(15000), catchError(err => {
        console.error('Fel vid massborttagning:', err);
        return of({ success: false, error: 'Kunde inte ta bort rapporter' });
      }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.reports = this.reports.filter(r => !this.selectedIds.has(r.id));
            this.selectedIds.clear();
            this.recomputeKpis();
            this.showSuccess(res.message);
          } else {
            this.errorMessage = res.error || 'Fel';
          }
        }
      });
  }

  toggleInlagd(report: any) {
    const v = !report.inlagd;
    this.service.updateInlagd(this.config.line, report.id, v)
      .pipe(timeout(15000), catchError(err => {
        console.error('Fel vid uppdatering av inlagd-status:', err);
        return of({ success: false });
      }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) { report.inlagd = v ? 1 : 0; this.showSuccess('Status uppdaterad'); }
        }
      });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (!this.selectedIds.size) { this.errorMessage = 'Inga rader valda'; return; }
    this.service.bulkUpdateInlagd(this.config.line, Array.from(this.selectedIds), inlagd)
      .pipe(timeout(15000), catchError(err => {
        console.error('Fel vid massuppdatering av inlagd-status:', err);
        return of({ success: false });
      }), takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.reports.forEach(r => { if (this.selectedIds.has(r.id)) r.inlagd = inlagd ? 1 : 0; });
            this.selectedIds.clear();
            this.showSuccess(res.message);
          }
        }
      });
  }

  exportCSV() {
    if (!this.filteredReports.length) return;
    const h = ['ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt', 'Kvalitet %', 'Kommentar', 'Användare', 'Inlagd'];
    const rows = this.filteredReports.map(r => [
      r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt,
      this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '',
      r.inlagd == 1 ? 'Ja' : 'Nej'
    ]);
    const csv = [h, ...rows].map(row => row.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${this.config.line}-skiftrapport-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportExcel() {
    if (!this.filteredReports.length) return;
    import('xlsx').then(XLSX => {
      const headers = [
        'ID', 'Datum', 'Antal OK', 'Antal ej OK', 'Totalt',
        'Kvalitet %', 'Kommentar', 'Användare', 'Inlagd'
      ];
      const rows = this.filteredReports.map(r => [
        r.id, r.datum, r.antal_ok, r.antal_ej_ok, r.totalt,
        this.getQualityPct(r) ?? '', r.kommentar || '', r.user_name || '',
        r.inlagd == 1 ? 'Ja' : 'Nej'
      ]);
      const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
      ws['!cols'] = [
        { wch: 6 }, { wch: 12 }, { wch: 10 }, { wch: 12 },
        { wch: 8 }, { wch: 11 }, { wch: 40 }, { wch: 16 }, { wch: 8 }
      ];
      ws['!freeze'] = { xSplit: 0, ySplit: 1 };
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Skiftrapporter');
      XLSX.writeFile(wb, `${this.config.line}-skiftrapport-${localToday()}.xlsx`);
    });
  }

  exportPDF(report: any) {
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;

        // ── Palett ──────────────────────────────────────────────────────────
        const C_PRIMARY = '#0d3b66';
        const C_GREEN   = '#10b981';
        const C_RED     = '#ef4444';
        const C_YELLOW  = '#f59e0b';
        const C_TEAL    = '#14b8c4';
        const C_BLUE    = '#0d6efd';
        const C_PURPLE  = '#8b5cf6';
        const C_LIGHTBG = '#f1f5f9';
        const C_BORDER  = '#e2e8f0';
        const C_DARK    = '#1e293b';
        const C_MUTED   = '#64748b';
        const C_WHITE   = '#ffffff';

        // ── Basdata ─────────────────────────────────────────────────────────
        const datum       = (report.datum || '').substring(0, 10);
        const totalt      = report.totalt ?? ((report.antal_ok || 0) + (report.antal_ej_ok || 0));
        const antalOk     = report.antal_ok ?? 0;
        const antalEjOk   = report.antal_ej_ok ?? 0;
        const omtvaatt    = report.omtvaatt ?? 0;
        const drifttidRaw = Math.min(report.drifttid || 0, 600);
        const driftstopp  = report.driftstopptime ?? 0;
        const rasttime    = report.rasttime ?? 0;
        const nettoMin    = Math.max(0, drifttidRaw - rasttime);
        const produkt     = this.getProductName(report.product_id) || '\u2013';
        const lopnr       = this.lopnummerMap[report.id] || '\u2013';
        const kommentar   = report.kommentar || 'Ingen kommentar l\u00e4mnad';

        // ── Operatörer ───────────────────────────────────────────────────────
        const opNums  = [report.op1, report.op2, report.op3].filter(Boolean);
        const opNames: string[] = opNums.map((n: number) => this.getOpName(n)).filter(Boolean);
        const activeOps = opNames.length;

        // ── Datum-formatering ────────────────────────────────────────────────
        const veckodag = (() => {
          const dagar = ['S\u00f6ndag', 'M\u00e5ndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'L\u00f6rdag'];
          const manader = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
          try {
            const d = new Date(datum + 'T00:00:00');
            if (isNaN(d.getTime())) return datum;
            return `${dagar[d.getDay()]} ${d.getDate()} ${manader[d.getMonth()]} ${d.getFullYear()}`;
          } catch { return datum; }
        })();

        // ── Inskickad-tid ────────────────────────────────────────────────────
        const inskickadStr = (() => {
          const src = report.created_at || report.datum || '';
          try {
            const d = new Date(String(src).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '\u2013';
            return d.toTimeString().substring(0, 5);
          } catch { return '\u2013'; }
        })();

        // ── KPI-ber\u00e4kningar ──────────────────────────────────────────────
        // Tillg\u00e4nglighet: drifttid / (drifttid + driftstopp)
        const plannadTid = drifttidRaw + driftstopp;
        const tillgPct = (plannadTid > 0)
          ? Math.min(100, Math.max(0, Math.round((drifttidRaw / plannadTid) * 100)))
          : 0;

        // Effektivitet: via _computeEfficiencyPct
        const effRaw  = this._computeEfficiencyPct(report);
        const effPct  = effRaw != null ? Math.min(100, Math.max(0, Math.round(effRaw))) : 0;

        // Kvalitet: antal_ok / totalt
        const kvalRaw = this._computeQualityPct(report);
        const kvalPct = kvalRaw != null ? Math.min(100, Math.max(0, Math.round(kvalRaw))) : 0;

        // OEE: via _computeOeePct
        const oeeRaw  = this._computeOeePct(report);
        const oeePct  = oeeRaw != null ? Math.min(100, Math.max(0, Math.round(oeeRaw))) : 0;

        // ── M\u00e5l-ber\u00e4kning (baserat p\u00e5 cykeltid x drifttid) ──────────────────────
        const product        = this.products.find((p: any) => p.id === (report.product_id ?? null));
        const targetCycleMin = product?.cycle_time_minutes ?? this.fallbackCycleMin;
        const malIbc         = (drifttidRaw > 0 && targetCycleMin > 0)
          ? Math.round(drifttidRaw / targetCycleMin)
          : 0;
        const malUppnatt  = malIbc > 0 && totalt >= malIbc;
        const malPct      = malIbc > 0 ? Math.min(100, Math.round((totalt / malIbc) * 100)) : 0;

        // ── IBC/h och min/IBC ────────────────────────────────────────────────
        const ibcH = (nettoMin > 0 && totalt > 0)
          ? Math.round((totalt / (nettoMin / 60)) * 10) / 10
          : null;
        const minPerIbc    = (totalt > 0 && nettoMin > 0)
          ? (nettoMin / totalt).toFixed(1)
          : '\u2013';
        const malMinPerIbc = targetCycleMin > 0 ? targetCycleMin.toFixed(1) : '\u2013';

        // ── PLC-signaler ─────────────────────────────────────────────────────
        const plcKpi       = this.getPlcKpi(report.id);
        const totalCycles  = plcKpi.totalCycles > 0 ? String(plcKpi.totalCycles) : '\u2013';

        // ── Stopporsaker ─────────────────────────────────────────────────────
        const detectedStops = this.getDetectedStops(report.id);

        // ── SVG-donut-hj\u00e4lpfunktion ──────────────────────────────────────────
        const buildDonutSvg = (pct: number, color: string, label: string): string => {
          const circumference = 326.726;
          const filled = Math.max(0, Math.min(pct, 100)) * circumference / 100;
          return `<svg xmlns="http://www.w3.org/2000/svg" width="120" height="130" viewBox="0 0 120 130">` +
            `<circle cx="60" cy="60" r="52" fill="none" stroke="#eef2f6" stroke-width="13"/>` +
            `<circle cx="60" cy="60" r="52" fill="none" stroke="${color}" stroke-width="13"` +
            ` stroke-dasharray="${filled.toFixed(2)} ${circumference.toFixed(2)}"` +
            ` stroke-linecap="round" transform="rotate(-90 60 60)"/>` +
            `<text x="60" y="66" text-anchor="middle" font-size="18" font-weight="bold" fill="${color}" font-family="Helvetica">${pct}%</text>` +
            `<text x="60" y="118" text-anchor="middle" font-size="9" fill="#64748b" font-family="Helvetica">${label}</text>` +
            `</svg>`;
        };

        // ── Progressbar-hj\u00e4lpfunktion ────────────────────────────────────────
        const buildProgressBar = (pct: number, barWidth: number): any[] => {
          const filled = Math.min(pct, 100) / 100 * barWidth;
          return [
            {
              canvas: [
                { type: 'rect' as const, x: 0, y: 0, w: barWidth, h: 8, r: 4, color: C_BORDER },
                ...(filled > 0 ? [{ type: 'rect' as const, x: 0, y: 0, w: filled, h: 8, r: 4, color: C_GREEN }] : [])
              ],
              margin: [0, 6, 0, 0] as [number, number, number, number]
            }
          ];
        };

        // ── Operatörs-chip ───────────────────────────────────────────────────
        const buildOpChips = (): any => {
          if (!opNames.length) {
            return { text: 'Inga operat\u00f6rer registrerade', fontSize: 9, color: C_MUTED, italics: true };
          }
          return {
            columns: opNames.map((name: string) => ({
              table: {
                widths: [16, '*'],
                body: [[
                  {
                    canvas: [{ type: 'ellipse' as const, x: 8, y: 9, r1: 8, r2: 8, color: C_PRIMARY }],
                    width: 16, height: 18
                  },
                  {
                    text: name, fontSize: 8, bold: true, color: C_DARK,
                    margin: [4, 2, 4, 2] as [number, number, number, number]
                  }
                ]]
              },
              layout: 'noBorders',
              margin: [0, 0, 6, 0] as [number, number, number, number]
            })),
            columnGap: 4
          };
        };

        // ── Stopp-lista ──────────────────────────────────────────────────────
        const buildStops = (): any => {
          if (!detectedStops.length) {
            return { text: 'Inga driftstopp registrerade', fontSize: 9, color: C_MUTED, italics: true };
          }
          const rows: any[][] = [[
            { text: 'Fr\u00e5n', bold: true, fontSize: 8, fillColor: C_LIGHTBG },
            { text: 'Till', bold: true, fontSize: 8, fillColor: C_LIGHTBG },
            { text: 'Minuter', bold: true, fontSize: 8, fillColor: C_LIGHTBG }
          ]];
          for (const s of detectedStops) {
            rows.push([
              { text: s.start, fontSize: 8 },
              { text: s.end, fontSize: 8 },
              { text: String(s.durationMin) + ' min', fontSize: 8 }
            ]);
          }
          return {
            table: { widths: ['*', '*', '*'], body: rows },
            layout: 'lightHorizontalLines',
            margin: [0, 4, 0, 0] as [number, number, number, number]
          };
        };

        // ── Chip-celle (f\u00f6r meta-rad) ────────────────────────────────────────
        const chip = (label: string, value: string): any => ({
          table: {
            widths: ['*'],
            body: [
              [{ text: label, fontSize: 7, color: C_MUTED, bold: true, fillColor: C_LIGHTBG,
                 margin: [6, 4, 6, 0] as [number, number, number, number] }],
              [{ text: value, fontSize: 11, bold: true, color: C_DARK, fillColor: C_LIGHTBG,
                 margin: [6, 0, 6, 6] as [number, number, number, number] }]
            ]
          },
          layout: {
            hLineWidth: () => 0.5,
            vLineWidth: () => 0.5,
            hLineColor: () => C_BORDER,
            vLineColor: () => C_BORDER
          }
        });

        // ── TID & TAKT-cell ──────────────────────────────────────────────────
        const taktCell = (label: string, value: string, warn = false): any => ({
          table: {
            widths: ['*'],
            body: [
              [{ text: label, fontSize: 7, color: C_MUTED, bold: true, fillColor: C_LIGHTBG,
                 margin: [5, 4, 5, 0] as [number, number, number, number] }],
              [{ text: value, fontSize: 10, bold: true,
                 color: warn ? C_YELLOW : C_DARK, fillColor: C_LIGHTBG,
                 margin: [5, 0, 5, 5] as [number, number, number, number] }]
            ]
          },
          layout: {
            hLineWidth: () => 0.5,
            vLineWidth: () => 0.5,
            hLineColor: () => C_BORDER,
            vLineColor: () => C_BORDER
          }
        });

        // ── Tal-med-prick (f\u00f6r produktion-sektion) ──────────────────────────
        const dotRow = (dotColor: string, label: string, value: number): any => ({
          columns: [
            { canvas: [{ type: 'ellipse' as const, x: 5, y: 5, r1: 4, r2: 4, color: dotColor }],
              width: 14, height: 12 },
            { text: `${label}: `, fontSize: 9, color: C_MUTED, width: 'auto' },
            { text: String(value), fontSize: 9, bold: true, color: C_WHITE }
          ],
          columnGap: 3,
          margin: [0, 2, 0, 2] as [number, number, number, number]
        });

        // ── Idag-datum f\u00f6r footer ────────────────────────────────────────────
        const todayStr = new Date().toLocaleDateString('sv-SE');

        // ── DOKUMENT ─────────────────────────────────────────────────────────
        pdfMake.createPdf({
          pageSize: 'A4' as const,
          pageMargins: [28, 28, 28, 28] as [number, number, number, number],
          content: [

            // ── (1) HEADER ─────────────────────────────────────────────────
            {
              columns: [
                {
                  stack: [
                    { text: 'NOREKO \u00c4LV\u00c4NGEN \u00b7 MAUSER PACKAGING SOLUTIONS',
                      fontSize: 7.5, color: C_MUTED, bold: true,
                      margin: [0, 0, 0, 2] as [number, number, number, number] },
                    { text: `Skiftrapport \u2014 ${this.config.lineName}`,
                      fontSize: 22, bold: true, color: C_PRIMARY,
                      margin: [0, 0, 0, 2] as [number, number, number, number] },
                    { text: 'Daglig produktionssammanst\u00e4llning',
                      fontSize: 9, color: C_MUTED }
                  ],
                  width: '*'
                },
                {
                  stack: [
                    { text: veckodag, fontSize: 10, bold: true, color: C_DARK,
                      alignment: 'right' as const },
                    { text: `Skift #${lopnr} \u00b7 ETT skift/dag`,
                      fontSize: 8, color: C_MUTED, alignment: 'right' as const,
                      margin: [0, 2, 0, 4] as [number, number, number, number] },
                    {
                      table: {
                        widths: ['*'],
                        body: [[{
                          text: malUppnatt ? 'GODK\u00c4ND' : 'EJ GODK\u00c4ND',
                          fontSize: 8, bold: true, color: C_WHITE,
                          fillColor: malUppnatt ? C_GREEN : C_YELLOW,
                          alignment: 'center' as const,
                          margin: [10, 4, 10, 4] as [number, number, number, number]
                        }]]
                      },
                      layout: 'noBorders',
                      alignment: 'right' as const
                    }
                  ],
                  width: 180
                }
              ],
              margin: [0, 0, 0, 6] as [number, number, number, number]
            },
            // 3pt PRIMARY-linje
            {
              canvas: [{ type: 'rect' as const, x: 0, y: 0, w: 536, h: 3, color: C_PRIMARY }],
              margin: [0, 0, 0, 10] as [number, number, number, number]
            },

            // ── (2) META-RAD ──────────────────────────────────────────────
            {
              columns: [
                chip('PRODUKT', produkt),
                chip('L\u00d6PNUMMER', '#' + lopnr),
                chip('OPERAT\u00d6RER', String(activeOps)),
                chip('INSKICKAD', inskickadStr)
              ],
              columnGap: 8,
              margin: [0, 0, 0, 10] as [number, number, number, number]
            },

            // ── (3) NYCKELTAL ─ 4 donuts ──────────────────────────────────
            {
              columns: [
                { svg: buildDonutSvg(tillgPct, C_GREEN,  'Tillg\u00e4nglighet'), width: 120, height: 130 },
                { svg: buildDonutSvg(oeePct,   C_BLUE,   'OEE'),                  width: 120, height: 130 },
                { svg: buildDonutSvg(effPct,   C_TEAL,   'Effektivitet'),          width: 120, height: 130 },
                { svg: buildDonutSvg(kvalPct,  C_PURPLE, 'Kvalitet'),              width: 120, height: 130 }
              ],
              columnGap: 14,
              margin: [0, 0, 0, 10] as [number, number, number, number]
            },

            // ── (4) PRODUKTION ─ stor box ──────────────────────────────────
            {
              table: {
                widths: ['*', 180],
                body: [[
                  {
                    stack: [
                      { text: 'IBC TOTALT', fontSize: 9, color: C_WHITE, bold: true,
                        margin: [0, 0, 0, 2] as [number, number, number, number] },
                      { text: String(totalt), fontSize: 36, bold: true, color: C_WHITE,
                        margin: [0, 0, 0, 2] as [number, number, number, number] },
                      { text: malIbc > 0
                          ? `M\u00e5l ${malIbc} \u00b7 M\u00e5luppfyllnad ${malPct}%`
                          : 'Inget produktionsm\u00e5l ber\u00e4knat',
                        fontSize: 8, color: C_WHITE,
                        margin: [0, 0, 0, 6] as [number, number, number, number] },
                      ...buildProgressBar(malPct, 270)
                    ],
                    fillColor: C_PRIMARY,
                    margin: [12, 12, 12, 12] as [number, number, number, number]
                  },
                  {
                    stack: [
                      dotRow(C_GREEN,  'Godk\u00e4nda OK', antalOk),
                      dotRow(C_RED,    'Ej godk\u00e4nda',  antalEjOk),
                      dotRow(C_YELLOW, 'Omtv\u00e4tt',      omtvaatt)
                    ],
                    fillColor: C_DARK,
                    margin: [12, 12, 12, 12] as [number, number, number, number]
                  }
                ]]
              },
              layout: 'noBorders',
              margin: [0, 0, 0, 10] as [number, number, number, number]
            },

            // ── (5) TID & TAKT ─ 4x2 ──────────────────────────────────────
            {
              columns: [
                taktCell('DRIFTTID', drifttidRaw > 0 ? this.formatDrifttid(drifttidRaw) : '\u2013', drifttidRaw >= 600),
                taktCell('DRIFTSTOPP', driftstopp > 0 ? `${driftstopp} min` : '0 min', driftstopp > 120),
                taktCell('RAST', rasttime > 0 ? `${rasttime} min` : '0 min'),
                taktCell('IBC / TIMME', ibcH != null ? ibcH.toFixed(1) : '\u2013')
              ],
              columnGap: 6,
              margin: [0, 0, 0, 6] as [number, number, number, number]
            },
            {
              columns: [
                taktCell('SNITT CYKELTID', totalt > 0 && nettoMin > 0 ? `${minPerIbc} min/IBC` : '\u2013'),
                taktCell('M\u00c5LTAKT', malMinPerIbc !== '\u2013' ? `${malMinPerIbc} min/IBC` : '\u2013'),
                taktCell('MIN / IBC', minPerIbc !== '\u2013' ? `${minPerIbc} min` : '\u2013'),
                taktCell('DRIFTTID-STATUS',
                  drifttidRaw >= 600 ? '\u26a0 Max 10h' : (drifttidRaw > 0 ? 'OK' : '\u2013'),
                  drifttidRaw >= 600)
              ],
              columnGap: 6,
              margin: [0, 0, 0, 10] as [number, number, number, number]
            },

            // ── (6) TV\u00c5 KORT ──────────────────────────────────────────────
            {
              columns: [
                {
                  stack: [
                    { text: 'OPERAT\u00d6RER', fontSize: 8, bold: true, color: C_MUTED,
                      margin: [0, 0, 0, 4] as [number, number, number, number] },
                    buildOpChips(),
                    { text: 'DRIFTSTOPP', fontSize: 8, bold: true, color: C_MUTED,
                      margin: [0, 10, 0, 4] as [number, number, number, number] },
                    buildStops()
                  ],
                  width: '50%'
                },
                {
                  stack: [
                    { text: 'PLC-SIGNALER', fontSize: 8, bold: true, color: C_MUTED,
                      margin: [0, 0, 0, 4] as [number, number, number, number] },
                    {
                      table: {
                        widths: ['*', '*'],
                        body: [
                          [
                            { text: 'Cykler (PLC)', fontSize: 7, color: C_MUTED, bold: true, fillColor: C_LIGHTBG,
                              margin: [4, 3, 4, 0] as [number, number, number, number] },
                            { text: 'Median cykeltid', fontSize: 7, color: C_MUTED, bold: true, fillColor: C_LIGHTBG,
                              margin: [4, 3, 4, 0] as [number, number, number, number] }
                          ],
                          [
                            { text: totalCycles, fontSize: 10, bold: true, color: C_DARK, fillColor: C_LIGHTBG,
                              margin: [4, 0, 4, 4] as [number, number, number, number] },
                            { text: plcKpi.medianCycleMin > 0 ? `${plcKpi.medianCycleMin} min` : '\u2013',
                              fontSize: 10, bold: true, color: C_DARK, fillColor: C_LIGHTBG,
                              margin: [4, 0, 4, 4] as [number, number, number, number] }
                          ]
                        ]
                      },
                      layout: {
                        hLineWidth: () => 0.5, vLineWidth: () => 0.5,
                        hLineColor: () => C_BORDER, vLineColor: () => C_BORDER
                      },
                      margin: [0, 0, 0, 10] as [number, number, number, number]
                    },
                    { text: 'KOMMENTAR', fontSize: 8, bold: true, color: C_MUTED,
                      margin: [0, 0, 0, 4] as [number, number, number, number] },
                    { text: kommentar, fontSize: 9, color: C_MUTED, italics: true }
                  ],
                  width: '50%'
                }
              ],
              columnGap: 16,
              margin: [0, 0, 0, 10] as [number, number, number, number]
            },

            // ── (7) FOOTER ─────────────────────────────────────────────────
            { canvas: [{ type: 'rect' as const, x: 0, y: 0, w: 536, h: 0.5, color: C_BORDER }],
              margin: [0, 0, 0, 4] as [number, number, number, number] },
            {
              columns: [
                { text: `Genererad ${todayStr} \u00b7 dev.mauserdb.com`,
                  fontSize: 8, color: C_MUTED },
                { text: `${this.config.lineName} \u00b7 Skiftrapport ${datum} \u00b7 Sida 1/1`,
                  fontSize: 8, color: C_MUTED, alignment: 'right' as const }
              ]
            }
          ],
          defaultStyle: { font: 'Roboto', fontSize: 10 }
        }).download(`Skiftrapport_${this.config.lineName}_${datum}.pdf`);
      });
    });
  }

  exportHandoverPDF() {
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const today = new Date().toLocaleDateString('sv-SE');
        const rows = this.filteredReports.map(r => [
          { text: (r.datum || '').substring(0, 10), alignment: 'center' as const },
          { text: this.getShiftTid(r) },
          { text: this.getProductName(r.product_id) || '–' },
          { text: [r.op1, r.op2, r.op3].filter(Boolean).map((n: number) => this.getOpName(n)).join(', ') || '–' },
          { text: String(r.antal_ok), alignment: 'center' as const, bold: true },
          { text: String(r.antal_ej_ok || 0), alignment: 'center' as const },
          { text: r.drifttid ? this.formatDrifttid(Math.min(r.drifttid, 600)) : '–', alignment: 'center' as const },
          { text: r.kommentar || '' }
        ]);
        pdfMake.createPdf({
          content: [
            { text: `Skiftöverlämning – ${this.config.lineName}`, style: 'header' },
            { text: `Genererad: ${today}`, style: 'subheader' },
            { text: '\n' },
            {
              table: {
                headerRows: 1,
                widths: ['auto', 'auto', 'auto', '*', 'auto', 'auto', 'auto', '*'],
                body: [
                  ['Datum', 'Tid', 'Produkt', 'Operatörer', 'OK', 'Ej OK', 'Drifttid', 'Kommentar'].map(h => ({ text: h, bold: true, fillColor: '#eeeeee' })),
                  ...rows
                ]
              },
              layout: 'lightHorizontalLines'
            },
            { text: '\n' },
            { text: `Totalt: ${this.summaryTotalIbc} IBC OK`, bold: true, fontSize: 12 }
          ],
          styles: {
            header: { fontSize: 18, bold: true, margin: [0, 0, 0, 4] },
            subheader: { fontSize: 10, color: '#555', margin: [0, 0, 0, 10] }
          },
          defaultStyle: { fontSize: 9 }
        }).download(`${this.config.line}-skiftoverlamning-${today}.pdf`);
      });
    });
  }

  showSuccess(msg: string) {
    this.successMessage = msg;
    this.showSuccessMessage = true;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.showSuccessMessage = false;
    }, 3000);
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }

  formatMinutes(min: number | null | undefined): string {
    if (min == null || min <= 0) return '0m';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  ibcPerHour(report: any): string {
    if (!report) return '–';
    const c = this.reportCache.get(report.id);
    const v = c !== undefined ? c.ibcH : this._computeIbcPerHour(report);
    return v != null ? v.toFixed(1) : '–';
  }

  getNetDrifttidMin(r: any): number {
    return Math.max(0, Math.min(r?.drifttid || 0, 600) - (r?.rasttime || 0));
  }

  formatNetDrifttid(r: any): string {
    const m = this.getNetDrifttidMin(r);
    if (m <= 0) return '–';
    return this.formatDrifttid(m);
  }

  getPlcDiagnostikUrl(report: any): string {
    const date = (report?.datum || report?.created_at || '').substring(0, 10);
    return `/${this.config.line}/plc-diagnostik${date ? '?datum=' + date : ''}`;
  }

  getSubMinPerIbc(sub: any): string {
    const ok = sub?.ibc_ok ?? 0;
    const dt = Math.min(sub?.runtime_plc ?? 0, 600);
    return ok > 0 && dt > 0 ? (dt / ok).toFixed(1) : '-';
  }

  get groupedDays(): Array<{ date: string; reports: any[]; totalIbc: number; totalDrift: number; driftWarning: boolean; avgEff: number | null; operators: string[]; products: string[]; submittedCount: number; hasPreliminary: boolean; unreportedCount: number; }> {
    const dayMap: { [date: string]: any[] } = {};

    // Alla rapporter visas i sin datum-grupp (inlämningsdag)
    this.filteredReports.forEach(r => {
      const d = (r.datum || '').substring(0, 10);
      if (!dayMap[d]) dayMap[d] = [];
      dayMap[d].push(r);
    });

    // Inkludera syntetiska rader i rätt daggrupp (passets eget lokala datum, ej alltid idag)
    const today = localToday();
    const synthRows = [
      ...(this.preliminaryReport ? [this.preliminaryReport] : []),
      ...this.unreportedPasses
    ];
    synthRows.forEach(s => {
      const d = (s.datum || today).substring(0, 10);
      if (!dayMap[d]) dayMap[d] = [];
      if (!dayMap[d].find((r: any) => r.id === s.id)) dayMap[d].push(s);
    });

    return Object.entries(dayMap).map(([date, reports]) => {
      const submittedOnly = reports.filter((r: any) => !r.isPreliminary && !r.isUnreported);
      // Sortera: inskickade chronologiskt (created_at), sedan ej inskickade, sist pågående
      const sorted = [...reports].sort((a: any, b: any) => {
        const order = (r: any) => r.isPreliminary ? 2 : r.isUnreported ? 1 : 0;
        if (order(a) !== order(b)) return order(a) - order(b);
        const ta = a._firstTime ?? new Date(String(a.created_at || '').replace(' ', 'T')).getTime() ?? 0;
        const tb = b._firstTime ?? new Date(String(b.created_at || '').replace(' ', 'T')).getTime() ?? 0;
        return ta - tb;
      });
      // Dag-summor: inskickade rapporter visar PLC-värden direkt (D4004/D4007)
      const totalIbc   = submittedOnly.reduce((s, r) => s + (r.totalt || ((r.antal_ok || 0) + (r.antal_ej_ok || 0))), 0);
      const rawDrift   = submittedOnly.reduce((s, r) => s + this.getNetDrifttidMin(r), 0);
      const totalDrift = Math.min(rawDrift, 600);
      const driftWarning = rawDrift > 600;
      const effVals = submittedOnly.map(r => this.getEfficiencyPct(r)).filter((v): v is number => v != null);
      const avgEff = effVals.length ? Math.round(effVals.reduce((s, v) => s + v, 0) / effVals.length) : null;
      const opSet = new Set<string>();
      submittedOnly.forEach(r => {
        [[r.op1_name, r.op1], [r.op2_name, r.op2], [r.op3_name, r.op3]].forEach(([nm, n]) => {
          if (n) opSet.add(this.resolveOpName(nm as string | null, n as number));
        });
      });
      const prodSet = new Set<string>();
      submittedOnly.forEach(r => { if (r.product_id) { const name = this.getProductName(r.product_id); if (name) prodSet.add(name); } });
      return {
        date, reports: sorted, totalIbc, totalDrift, driftWarning, avgEff,
        operators: Array.from(opSet), products: Array.from(prodSet),
        submittedCount: submittedOnly.length,
        hasPreliminary: reports.some((r: any) => r.isPreliminary),
        unreportedCount: reports.filter((r: any) => r.isUnreported).length,
      };
    }).sort((a, b) => b.date.localeCompare(a.date));
  }

  get hasSynthRows(): boolean {
    return !!(this.preliminaryReport || this.unreportedPasses.length > 0);
  }

  toggleDay(date: string): void { this.expandedDays[date] = !this.expandedDays[date]; }
  isDayExpanded(date: string): boolean { return !!this.expandedDays[date]; }
  isDayAllSelected(reports: any[]): boolean {
    const real = reports.filter(r => r.id > 0);
    return real.length > 0 && real.every(r => this.selectedIds.has(r.id));
  }
  toggleDaySelect(reports: any[]): void {
    const real = reports.filter(r => r.id > 0);
    if (this.isDayAllSelected(reports)) real.forEach(r => this.selectedIds.delete(r.id));
    else real.forEach(r => this.selectedIds.add(r.id));
  }
  trackByDate(_index: number, day: any): string { return day.date; }

  setTab(id: number, tab: string): void {
    this.activeTab[id] = tab;
    // Rita om timvis-diagram när PLC-data-fliken öppnas (canvas är nu i plcdata-fliken)
    if (tab === 'plcdata' && this.plcStatsCache.has(id)) {
      const report = id === this.PRELIMINARY_ID
        ? this.preliminaryReport
        : id < 0
          ? this.unreportedPasses.find(u => u.id === id)
          : this.reports.find(r => r.id === id);
      setTimeout(() => this.renderHourlyChart(id, report), 50);
    }
  }
  getTab(id: number): string { return this.activeTab[id] || 'oversikt'; }

  getLopnummerPills(id: number): string[] {
    const s = this.lopnummerMap[id];
    if (!s || s === '–') return [];
    return s.split(', ').filter(p => p.trim().length > 0);
  }

  getSubCycleEff(subs: any[], i: number, productId: number | null): number | null {
    if (i === 0 || !subs[i - 1]?.datum || !subs[i]?.datum) return null;
    const t0 = new Date(String(subs[i - 1].datum).replace(' ', 'T')).getTime();
    const t1 = new Date(String(subs[i].datum).replace(' ', 'T')).getTime();
    const diffMin = (t1 - t0) / 60000;
    if (diffMin <= 0 || diffMin > 30) return null;
    const target = this.products.find(p => p.id === productId)?.cycle_time_minutes ?? 3.0;
    return Math.min(100, Math.round((target / diffMin) * 100));
  }

  visibleSubShifts(reportId: number): any[] {
    const all = this.subShiftsMap[reportId] || [];
    return this.subShiftsShowAll[reportId] ? all : all.slice(0, this.SUB_PAGE);
  }

  resolveOpName(nameField: string | null | undefined, numField: number | null): string {
    if (nameField && nameField.trim()) return nameField;
    return this.getOpName(numField);
  }

  getShiftTid(report: any): string {
    if (!report) return '–';
    // Submittad rapport: PLC-data är alltid korrekt — använd plc_start→plc_end direkt
    const isSubmitted = !report.isPreliminary && !report.isUnreported;
    if (isSubmitted && report.plc_start && report.plc_end) {
      return `${String(report.plc_start).substring(11, 16)}→${String(report.plc_end).substring(11, 16)}`;
    }
    // Ej inskickad/live: prioritera faktiska cykeltider från PLC-cache (spec C)
    const plcStats = this.plcStatsCache.get(report.id);
    if (plcStats?.firstCycleTime && plcStats?.lastCycleTime) {
      const s = new Date(plcStats.firstCycleTime).toTimeString().substring(0, 5);
      const e = new Date(plcStats.lastCycleTime).toTimeString().substring(0, 5);
      if (s && e) return s !== e ? `${s}→${e}` : s;
    }
    const c = this.reportCache.get(report.id);
    return c !== undefined ? c.tid : this._computeShiftTid(report);
  }

  isLateSubmission(reportId: number): boolean {
    return this.plcStatsCache.get(reportId)?.sentInskickad ?? false;
  }

  /** Öppnar formuläret förfyllt med data från ett syntetiskt pass (ej inskickad eller pågående). */
  openAddFormPrefilled(pass: any): void {
    // Hämta operatörer från första cykelraden (subs[0]) om tillgänglig
    const subs = this.subShiftsMap[pass.id] || [];
    const firstSub = subs.length > 0 ? subs[0] : null;
    const op1 = firstSub?.op1 ? Number(firstSub.op1) : null;
    const op2 = firstSub?.op2 ? Number(firstSub.op2) : null;
    const op3 = firstSub?.op3 ? Number(firstSub.op3) : null;
    this.newReport = {
      datum: pass.datum || localToday(),
      antal_ok: pass.antal_ok || 0,
      antal_ej_ok: pass.antal_ej_ok || 0,
      kommentar: '',
      op1, op2, op3,
      product_id: pass.product_id ?? null,
    };
    this.errorMessage = '';
    this.showAddForm = true;
  }

  getOpInitials(num: number | null): string {
    const name = this.getOpName(num);
    if (!name || name.startsWith('#')) return '?';
    return name.trim().split(/\s+/).map((p: string) => p[0]).slice(0, 2).join('').toUpperCase();
  }

  opAvatarColor(num: number | null): string {
    const colors = ['#4a5568', '#2f6a3f', '#6b4c11', '#553580', '#1a4a6b', '#1a5252', '#5a2020'];
    return colors[((num ?? 0) % colors.length)];
  }

  getPlcKpi(reportId: number): { totalCycles: number; medianCycleMin: number; totalStopMin: number; avgCycleMin: number } {
    return this.plcStatsCache.get(reportId)?.kpi ?? { totalCycles: 0, medianCycleMin: 0, totalStopMin: 0, avgCycleMin: 0 };
  }

  getDetectedStops(reportId: number): Array<{ start: string; end: string; durationMin: number }> {
    return this.plcStatsCache.get(reportId)?.stops ?? [];
  }

  getHourlyBuckets(reportId: number): Array<{ hour: number; count: number }> {
    return this.plcStatsCache.get(reportId)?.hourlyBuckets ?? [];
  }

  getHourlyMax(reportId: number): number {
    return this.plcStatsCache.get(reportId)?.hourlyMax ?? 1;
  }

  getCycleStats(reportId: number): { avg: number | null; median: number | null; min: number | null; max: number | null } {
    return this.plcStatsCache.get(reportId)?.cycleStats ?? { avg: null, median: null, min: null, max: null };
  }

  /** Skapar Chart.js timvis stapeldiagram för givet report-id */
  private renderHourlyChart(id: number, report: any): void {
    if (this.destroy$.closed) return;
    // Destroy existing
    const existing = this.plcCharts.get(id);
    if (existing) {
      try { existing.hourly.destroy(); } catch (_e) { /* ignore */ }
      this.plcCharts.delete(id);
    }

    const canvas = document.getElementById(`plc-hourly-${id}`) as HTMLCanvasElement | null;
    if (!canvas) return;

    const buckets = this.plcStatsCache.get(id)?.hourlyBuckets ?? [];
    if (!buckets.length) return;

    const product = this.products.find(p => p.id === (report?.product_id ?? null));
    const cycleTimeMin = product?.cycle_time_minutes ?? 3.0;
    const targetPerHour = Math.round((60 / cycleTimeMin) * 10) / 10;

    const labels = buckets.map(b => `${b.hour}:00`);
    const data = buckets.map(b => b.count);

    let chart!: Chart;
    this.zone.runOutsideAngular(() => {
      chart = new Chart(canvas, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            {
              label: 'IBC/timme',
              data,
              backgroundColor: 'rgba(49,130,206,0.8)',
              borderColor: '#3182ce',
              borderWidth: 1,
              borderRadius: 3,
            },
            {
              label: `Mål (${targetPerHour} IBC/h)`,
              data: labels.map(() => targetPerHour),
              type: 'line' as const,
              borderColor: 'rgba(72,187,120,0.85)',
              borderWidth: 2,
              borderDash: [6, 4],
              pointRadius: 0,
              fill: false,
              backgroundColor: 'transparent',
            } as any,
          ],
        },
        options: {
          animation: false,
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              labels: { color: '#e2e8f0', font: { size: 11 }, boxWidth: 14 },
            },
            tooltip: {
              backgroundColor: 'rgba(15,17,23,0.95)',
              titleColor: '#fff',
              bodyColor: '#e0e0e0',
              borderColor: '#3182ce',
              borderWidth: 1,
            },
          },
          scales: {
            x: {
              ticks: { color: '#e2e8f0', font: { size: 11 } },
              grid: { color: '#4a5568' },
            },
            y: {
              beginAtZero: true,
              ticks: { color: '#e2e8f0', font: { size: 11 }, stepSize: 1 },
              grid: { color: '#4a5568' },
              title: { display: true, text: 'Antal IBC', color: '#e2e8f0', font: { size: 11 } },
            },
          },
        },
      });
    });

    this.plcCharts.set(id, { hourly: chart });
  }
}
