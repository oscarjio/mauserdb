import { Component, OnInit, OnDestroy, Input } from '@angular/core';
import { CommonModule, DatePipe, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';
import { AuthService } from '../../services/auth.service';
import { localToday } from '../../utils/date-utils';

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
  imports: [CommonModule, FormsModule, DatePipe, DecimalPipe],
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
  readonly SUB_PAGE = 30;
  activeTab: { [id: number]: string } = {};
  expandedDays: { [date: string]: boolean } = {};
  loading = false;
  errorMessage = '';
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

  constructor(
    private service: LineSkiftrapportService,
    private auth: AuthService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(u => {
      this.user = u;
      this.isAdmin = u?.role === 'admin';
    });
    this.fetchReports();
    this.loadOperatorsAndProducts();
    this.updateInterval = setInterval(() => {
      if (!this.destroy$.closed) this.fetchReports(true);
    }, 15000);
  }

  ngOnDestroy() {
    clearInterval(this.updateInterval);
    clearTimeout(this.successTimerId);
    this.fetchSub?.unsubscribe();
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
      // Operatörsfilter
      if (this.selectedOperatorId !== null) {
        const opNum = this.operators.find(o => o.id === this.selectedOperatorId)?.number;
        if (opNum == null) return false;
        if (Number(r.op1) !== Number(opNum) && Number(r.op2) !== Number(opNum) && Number(r.op3) !== Number(opNum)) return false;
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
    const totalIbc = this.cachedTotalOk + this.cachedTotalEjOk;
    this.cachedTotalIbc = totalIbc;
    this.cachedAvgQuality = totalIbc === 0 ? 0 : Math.round((this.cachedTotalOk / totalIbc) * 1000) / 10;
    this.cachedAvgIbcPerSkift = filtered.length === 0 ? 0 : Math.round((totalIbc / filtered.length) * 10) / 10;
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

  onSearchInput(): void { /* filteredReports-gettern räknas om automatiskt */ }
  onOperatorFilterChange(): void { /* filteredReports-gettern räknas om automatiskt */ }

  getSelectedOperatorName(): string {
    if (this.selectedOperatorId == null) return '';
    return this.operators.find(o => o.id === this.selectedOperatorId)?.name || '';
  }

  // ========== KPI getters (computed per change-detection) ==========

  get summaryTotalIbc(): number {
    return this.filteredReports.reduce((s, r) => s + (r.totalt || 0), 0);
  }

  get summaryAvgIbcH(): number | null {
    const totalDrift = this.filteredReports.reduce((s, r) => s + (r.drifttid || 0), 0);
    const totalOk = this.filteredReports.reduce((s, r) => s + (r.antal_ok || 0), 0);
    if (totalDrift <= 0 || totalOk <= 0) return null;
    return Math.round(totalOk / (totalDrift / 60) * 10) / 10;
  }

  get summaryAvgEfficiency(): number | null {
    const totalDrift = this.filteredReports.reduce((s, r) => s + (r.drifttid || 0), 0);
    const totalRast = this.filteredReports.reduce((s, r) => s + (r.rasttime || 0), 0);
    const schema = totalDrift + totalRast;
    if (schema <= 0) return null;
    return Math.round((totalDrift / schema) * 100);
  }

  get summaryAvgOee(): number | null {
    const valid = this.filteredReports.filter(r => this.getOeePct(r) != null);
    if (!valid.length) return null;
    return Math.round(valid.reduce((s, r) => s + (this.getOeePct(r) ?? 0), 0) / valid.length);
  }

  get summaryTotalDrift(): number {
    return this.filteredReports.reduce((s, r) => s + (r.drifttid || 0), 0);
  }

  // ========== Per-rad helpers ==========

  getIbcPerHour(r: any): number | null {
    if (!(r.drifttid > 0) || !(r.antal_ok > 0)) return null;
    return Math.round(r.antal_ok / (r.drifttid / 60) * 10) / 10;
  }

  getEfficiencyPct(r: any): number | null {
    const tot = (r.drifttid || 0) + (r.rasttime || 0);
    if (!tot) return null;
    return Math.round((r.drifttid / tot) * 100);
  }

  getOeePct(r: any): number | null {
    const totalIbc = r.totalt ?? 0;
    const okIbc = r.antal_ok ?? 0;
    if (totalIbc <= 0) return null;

    // Kvalitet (Q)
    const kvalitet = okIbc / totalIbc;

    // Tillgänglighet (A) = drifttid / (drifttid + rasttime)
    // drifttid är i minuter
    const drifttidMin = r.drifttid ?? 0;
    const rasttimeMin = r.rasttime ?? 0;
    const schemaMin = drifttidMin + rasttimeMin;
    const tillganglighet = schemaMin > 0 ? Math.min(drifttidMin / schemaMin, 1) : null;
    if (tillganglighet == null) return null;

    // Prestanda (P) = (totalIbc × ideal_cycle_sek) / drifttid_sek, cap 1.0
    // drifttid i minuter → sekunder
    const drifttidSek = drifttidMin * 60;
    const product = this.products.find(p => p.id === (r.product_id ?? null));
    const IDEAL_CYCLE_SEK = ((product?.cycle_time_minutes ?? 3.0) * 60);
    const prestanda = drifttidSek > 0
      ? Math.min((totalIbc * IDEAL_CYCLE_SEK) / drifttidSek, 1)
      : 1.0;

    return Math.round(tillganglighet * prestanda * kvalitet * 100);
  }

  formatDrifttid(min: number): string {
    if (!min || min <= 0) return '–';
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  getProductName(productId: number | null): string {
    if (!productId) return '';
    const p = this.products.find(p => p.id === productId);
    return p?.name || `#${productId}`;
  }

  getOpName(num: number | null): string {
    if (!num) return '';
    const n = Number(num);
    const op = this.operators.find(o => Number(o.number) === n);
    return op?.name || `#${num}`;
  }

  private loadOperatorsAndProducts(): void {
    this.service.getOperators(this.config.line)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => { if (res?.success) this.operators = res.data || []; });
    this.service.getProducts(this.config.line)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => { if (res?.success) this.products = res.data || []; });
  }

  getQualityPct(r: any): number | null {
    if (!r.totalt) return null;
    return Math.round((r.antal_ok / r.totalt) * 1000) / 10;
  }

  minPerIbc(r: any): string {
    const ok = r.antal_ok ?? 0;
    const dt = r.drifttid ?? 0;
    if (ok <= 0 || dt <= 0) return '–';
    return (dt / ok).toFixed(1);
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
    this.expanded[id] = !this.expanded[id];
    if (this.expanded[id]) {
      const report = this.reports.find(r => r.id === id);
      if (report?.skiftraknare && this.lopnummerMap[id] === undefined) {
        this.loadLopnummer(report);
      }
      if (report?.skiftraknare && this.subShiftsMap[id] === undefined) {
        this.loadSubShifts(report);
      }
    }
  }

  private loadSubShifts(report: any): void {
    const id = report.id;
    this.subShiftsLoading[id] = true;
    this.service.getSubShifts(this.config.line, report.skiftraknare)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.subShiftsLoading[id] = false;
        this.subShiftsMap[id] = res?.success ? (res.data || []) : [];
      });
  }

  private loadLopnummer(report: any): void {
    const id = report.id;
    this.lopnummerLoading[id] = true;
    this.service.getLopnummer(this.config.line, report.skiftraknare)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.lopnummerLoading[id] = false;
        this.lopnummerMap[id] = res?.success ? res.ranges : '–';
      });
  }

  fetchReports(silent = false) {
    if (!silent) this.loading = true;
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
          } else {
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
        const q = this.getQualityPct(report);
        pdfMake.createPdf({
          content: [
            { text: 'Skiftrapport – ' + this.config.lineName, style: 'header' },
            { text: report.datum + '  |  Skift av ' + (report.user_name || '-'), style: 'subheader' },
            { text: '\n' },
            { text: 'Produktion', style: 'sectionHeader' },
            {
              table: {
                widths: ['*', '*', '*', '*'],
                body: [
                  [
                    { text: 'Antal OK', bold: true, fillColor: '#eeeeee' },
                    { text: 'Antal ej OK', bold: true, fillColor: '#eeeeee' },
                    { text: 'Totalt', bold: true, fillColor: '#eeeeee' },
                    { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }
                  ],
                  [
                    { text: String(report.antal_ok), alignment: 'center' },
                    { text: String(report.antal_ej_ok), alignment: 'center' },
                    { text: String(report.totalt), bold: true, alignment: 'center' },
                    { text: q != null ? q + '%' : '\u2013', alignment: 'center', color: q != null && q >= 90 ? 'green' : (q != null && q < 70 ? 'red' : 'black') }
                  ]
                ]
              },
              layout: 'lightHorizontalLines'
            },
            { text: '\n' },
            ...(report.kommentar ? [{ text: 'Kommentar: ' + report.kommentar, style: 'meta' }, { text: '\n' }] : []),
            { text: 'Skiftansvarig: ' + (report.user_name || '-'), style: 'meta' },
            { text: 'Inlagd: ' + (report.inlagd == 1 ? 'Ja' : 'Nej'), style: 'meta' },
            { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
          ],
          styles: {
            header: { fontSize: 20, bold: true, margin: [0, 0, 0, 4] },
            subheader: { fontSize: 12, color: '#555', margin: [0, 0, 0, 10] },
            sectionHeader: { fontSize: 13, bold: true, margin: [0, 8, 0, 4] },
            meta: { fontSize: 10, color: '#777', margin: [0, 2, 0, 0] }
          },
          defaultStyle: { fontSize: 11 }
        }).download(`${this.config.line}-skiftrapport-${report.datum}-${report.id}.pdf`);
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
          { text: r.drifttid ? this.formatDrifttid(r.drifttid) : '–', alignment: 'center' as const },
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
    const ok = report.antal_ok ?? 0;
    const dt = report.drifttid ?? 0;
    if (dt <= 0 || ok <= 0) return '–';
    return (ok / (dt / 60)).toFixed(1);
  }

  getSubIbcPerHour(sub: any): string {
    const ok = sub.ibc_ok ?? 0;
    const dt = sub.runtime_plc ?? 0;
    if (ok <= 0 || dt <= 0) return '–';
    return (ok / (dt / 60)).toFixed(1);
  }

  get groupedDays(): Array<{ date: string; reports: any[]; totalIbc: number; totalDrift: number; avgEff: number | null; operators: string[]; products: string[]; }> {
    const dayMap: { [date: string]: any[] } = {};
    this.filteredReports.forEach(r => {
      const d = (r.datum || '').substring(0, 10);
      if (!dayMap[d]) dayMap[d] = [];
      dayMap[d].push(r);
    });
    return Object.entries(dayMap).map(([date, reports]) => {
      const totalIbc = reports.reduce((s, r) => s + (r.antal_ok || 0), 0);
      const totalDrift = reports.reduce((s, r) => s + (r.drifttid || 0), 0);
      const effVals = reports.map(r => this.getEfficiencyPct(r)).filter((v): v is number => v != null);
      const avgEff = effVals.length ? Math.round(effVals.reduce((s, v) => s + v, 0) / effVals.length) : null;
      const opSet = new Set<string>();
      reports.forEach(r => { [r.op1, r.op2, r.op3].forEach((n: number | null) => { if (n) { const name = this.getOpName(n); if (name) opSet.add(name); } }); });
      const prodSet = new Set<string>();
      reports.forEach(r => { if (r.product_id) { const name = this.getProductName(r.product_id); if (name) prodSet.add(name); } });
      return { date, reports, totalIbc, totalDrift, avgEff, operators: Array.from(opSet), products: Array.from(prodSet) };
    }).sort((a, b) => b.date.localeCompare(a.date));
  }

  toggleDay(date: string): void { this.expandedDays[date] = !this.expandedDays[date]; }
  isDayExpanded(date: string): boolean { return !!this.expandedDays[date]; }
  isDayAllSelected(reports: any[]): boolean { return reports.length > 0 && reports.every(r => this.selectedIds.has(r.id)); }
  toggleDaySelect(reports: any[]): void {
    if (this.isDayAllSelected(reports)) reports.forEach(r => this.selectedIds.delete(r.id));
    else reports.forEach(r => this.selectedIds.add(r.id));
  }
  trackByDate(_index: number, day: any): string { return day.date; }

  setTab(id: number, tab: string): void { this.activeTab[id] = tab; }
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

  resolveOpName(nameField: string | null, numField: number | null): string {
    if (nameField) return nameField;
    return this.getOpName(numField);
  }

  getShiftTid(report: any): string {
    const drifttidMs = (report.drifttid ?? 0) * 60 * 1000;
    const fmt = (raw: string) => {
      const d = new Date(raw.replace(' ', 'T'));
      return isNaN(d.getTime()) ? null : d.toTimeString().substring(0, 5);
    };
    // PLC real times (from tvattlinje_ibc correlated subquery in backend)
    if (report.plc_start && report.plc_end) {
      const s = fmt(String(report.plc_start));
      const e = fmt(String(report.plc_end));
      if (s && e && s !== e) return `${s}→${e}`;
      if (s) return s;
    }
    // Datum with time component (rebotling style)
    if (report.datum) {
      const raw = String(report.datum);
      if (raw.length > 10) {
        const s = fmt(raw);
        if (s) {
          if (drifttidMs > 0) {
            const d = new Date(raw.replace(' ', 'T'));
            return `${s}→${new Date(d.getTime() + drifttidMs).toTimeString().substring(0, 5)}`;
          }
          return s;
        }
      }
    }
    // Last resort: created_at (only time, not as range since created_at is submit time)
    if (report.created_at) {
      const s = fmt(String(report.created_at));
      if (s) return s;
    }
    return '–';
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
}
